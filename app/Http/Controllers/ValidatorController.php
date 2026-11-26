<?php

namespace App\Http\Controllers;

use App\Models\FinalReport;
use App\Models\LabRequest;
use App\Models\SpecimenPhoto;
use App\Models\TestResult;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ValidatorController extends Controller
{
    private const RESULT_STATUSES = ['P', 'F', 'N/A', 'D'];

    public function index(): View
    {
        $pendingRequests = LabRequest::query()
            ->where('status', 'draft')
            ->with('testPackage', 'csValidation')
            ->oldest()
            ->get();

        $finalReports = FinalReport::query()
            ->with('labRequest')
            ->latest('approved_at')
            ->paginate(10);

        return view('validator.requests.index', compact('pendingRequests', 'finalReports'));
    }

    public function show(LabRequest $labRequest): View
    {
        $this->ensureAwaitingValidation($labRequest);

        $labRequest->load(['testPackage', 'requestor', 'specimen', 'csValidation']);
        $beforePhotos = $labRequest->specimenPhotos()
            ->where('phase', 'before')
            ->orderBy('sort_order')
            ->get();
        $afterPhotos = $labRequest->specimenPhotos()
            ->where('phase', 'after')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('sort_order');
        $testItems = $labRequest->testItems()
            ->wherePivot('selected', true)
            ->orderBy('test_items.code')
            ->get();
        $results = TestResult::query()
            ->with('details')
            ->whereIn('lab_request_test_item_id', $testItems->pluck('pivot.id'))
            ->get()
            ->keyBy('lab_request_test_item_id');

        abort_if($results->count() !== $testItems->count(), 409, 'Hasil tes belum lengkap.');

        return view('validator.requests.show', [
            'labRequest' => $labRequest,
            'beforePhotos' => $beforePhotos,
            'afterPhotos' => $afterPhotos,
            'testItems' => $testItems,
            'results' => $results,
            'resultStatuses' => self::RESULT_STATUSES,
        ]);
    }

    public function updateRequest(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $validated = $request->validate([
            'applicant' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'sample_description' => ['required', 'string', 'max:5000'],
            'season' => ['required', 'string', 'max:255'],
            'phase' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'string', 'max:255'],
            'colour' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:5000'],
            'fiber_content' => ['nullable', 'string', 'max:5000'],
            'care_instruction' => ['nullable', 'string', 'max:5000'],
            'previous_test' => ['sometimes', 'boolean'],
            'previous_report_no' => ['nullable', 'string', 'max:255'],
            'retest' => ['sometimes', 'boolean'],
            'previous_fail_test' => ['nullable', 'string', 'max:5000'],
            'previous_fail_report_no' => ['nullable', 'string', 'max:255'],
            'function' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['previous_test'] = $request->boolean('previous_test');
        $validated['retest'] = $request->boolean('retest');

        DB::transaction(function () use ($labRequest, $validated) {
            $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            $this->ensureAwaitingValidation($lockedRequest);
            $lockedRequest->update($validated);
        });

        return redirect()
            ->route('validator.requests.show', $labRequest)
            ->with('status', 'Koreksi data request berhasil disimpan.');
    }

    public function updateFinalResults(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $validated = $request->validate([
            'result_statuses' => ['required', 'array', 'min:1'],
            'result_statuses.*' => ['required', Rule::in(self::RESULT_STATUSES)],
        ]);

        DB::transaction(function () use ($request, $labRequest, $validated) {
            $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            $this->ensureAwaitingValidation($lockedRequest);

            $selectedResultIds = DB::table('lab_request_test_items')
                ->join('test_results', 'test_results.lab_request_test_item_id', '=', 'lab_request_test_items.id')
                ->where('lab_request_test_items.lab_request_id', $lockedRequest->id)
                ->where('lab_request_test_items.selected', true)
                ->pluck('test_results.id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();
            $submittedResultIds = array_map('intval', array_keys($validated['result_statuses']));
            sort($submittedResultIds);

            if ($selectedResultIds !== $submittedResultIds) {
                throw ValidationException::withMessages([
                    'result_statuses' => 'Status harus diisi untuk setiap item tes terpilih.',
                ]);
            }

            foreach ($validated['result_statuses'] as $resultId => $resultStatus) {
                TestResult::query()
                    ->whereKey($resultId)
                    ->where('status', 'draft')
                    ->whereHas('requestTestItem', fn ($query) => $query
                        ->where('lab_request_id', $lockedRequest->id)
                        ->where('selected', true))
                    ->update([
                        'result_status' => $resultStatus,
                        'last_updated_by_id' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('validator.requests.show', $labRequest)
            ->with('status', 'Status hasil tes berhasil diperbarui.');
    }

    public function updateAfterPhoto(
        Request $request,
        LabRequest $labRequest,
        int $sortOrder,
    ): RedirectResponse {
        $validated = $request->validate([
            'after_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remark' => ['required', 'string', 'max:1000'],
        ]);

        $newPath = null;
        $oldPath = null;

        try {
            if ($request->hasFile('after_photo')) {
                $newPath = $request->file('after_photo')->store(
                    "specimen-photos/{$labRequest->id}/after",
                    'local',
                );
                if ($newPath === false) {
                    throw new RuntimeException('Foto After Test gagal disimpan.');
                }
            }

            $oldPath = DB::transaction(function () use (
                $labRequest,
                $sortOrder,
                $validated,
                $newPath,
            ) {
                $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
                $this->ensureAwaitingValidation($lockedRequest);

                $beforeExists = SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'before')
                    ->where('sort_order', $sortOrder)
                    ->exists();
                abort_unless($beforeExists, 404);

                $afterPhoto = SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'after')
                    ->where('sort_order', $sortOrder)
                    ->lockForUpdate()
                    ->first();

                if ($newPath === null && $afterPhoto === null) {
                    throw ValidationException::withMessages([
                        'after_photo' => 'Unggah foto After Test untuk pasangan foto Before ini.',
                    ]);
                }

                $oldPath = $afterPhoto?->path;
                SpecimenPhoto::query()->updateOrCreate(
                    [
                        'lab_request_id' => $lockedRequest->id,
                        'phase' => 'after',
                        'sort_order' => $sortOrder,
                    ],
                    [
                        'uploaded_by_id' => auth()->id(),
                        'path' => $newPath ?? $afterPhoto->path,
                        'remark' => $validated['remark'],
                    ],
                );

                return $oldPath;
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null && $newPath !== null && $oldPath !== $newPath) {
            if (! Storage::disk('local')->delete($oldPath)) {
                throw new RuntimeException('Foto After lama gagal dihapus dari penyimpanan.');
            }
        }

        return redirect()
            ->route('validator.requests.show', $labRequest)
            ->with('status', 'Foto After Test dan remark berhasil disimpan.');
    }

    public function showPhoto(SpecimenPhoto $specimenPhoto): BinaryFileResponse
    {
        abort_unless(in_array($specimenPhoto->phase, ['before', 'after'], true), 404);
        abort_unless(Storage::disk('local')->exists($specimenPhoto->path), 404);

        return response()->file(Storage::disk('local')->path($specimenPhoto->path));
    }

    public function finalize(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $request->validate(['confirm_finalization' => ['accepted']]);
        $storedPdfPath = null;

        try {
            $finalReport = DB::transaction(function () use ($request, $labRequest, &$storedPdfPath) {
                $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
                $this->ensureAwaitingValidation($lockedRequest);

                $selectedItemCount = DB::table('lab_request_test_items')
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('selected', true)
                    ->count();
                $completedResults = DB::table('lab_request_test_items')
                    ->join('test_results', 'test_results.lab_request_test_item_id', '=', 'lab_request_test_items.id')
                    ->where('lab_request_test_items.lab_request_id', $lockedRequest->id)
                    ->where('lab_request_test_items.selected', true)
                    ->where('test_results.status', 'draft')
                    ->whereNotNull('test_results.result_status')
                    ->whereIn('test_results.result_status', self::RESULT_STATUSES)
                    ->whereNotNull('test_results.matrix_type')
                    ->count();

                if ($selectedItemCount === 0 || $completedResults !== $selectedItemCount) {
                    throw ValidationException::withMessages([
                        'test_results' => 'Semua item tes harus memiliki hasil lengkap sebelum finalisasi.',
                    ]);
                }

                $beforePhotos = SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'before')
                    ->orderBy('sort_order')
                    ->get();
                $afterPhotos = SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'after')
                    ->orderBy('sort_order')
                    ->get();

                if ($beforePhotos->isEmpty() || $beforePhotos->count() !== $afterPhotos->count()) {
                    throw ValidationException::withMessages([
                        'after_photos' => 'Jumlah foto After harus sama persis dengan foto Before.',
                    ]);
                }

                foreach ($beforePhotos as $beforePhoto) {
                    $afterPhoto = $afterPhotos->firstWhere('sort_order', $beforePhoto->sort_order);
                    if (! $afterPhoto || blank($afterPhoto->remark)) {
                        throw ValidationException::withMessages([
                            'after_photos' => 'Setiap foto Before harus memiliki foto After dan remark.',
                        ]);
                    }
                }

                foreach ($beforePhotos->concat($afterPhotos) as $photo) {
                    if (! Storage::disk('local')->exists($photo->path)) {
                        throw ValidationException::withMessages([
                            'after_photos' => 'Ada foto yang tidak dapat ditemukan di penyimpanan.',
                        ]);
                    }
                }

                $approvedAt = now();
                $lockedRequest->update([
                    'status' => 'approved',
                    'issue_date' => $approvedAt->toDateString(),
                ]);

                $snapshot = $this->buildReportSnapshot(
                    $lockedRequest->fresh(),
                    $request->user(),
                    $approvedAt,
                    $beforePhotos,
                    $afterPhotos,
                );
                $pdf = Pdf::loadView('reports.final', [
                    'report' => $this->preparePdfData($snapshot),
                ])->setPaper('a4', 'portrait')->setOptions([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                ]);
                $pdf->render();
                $font = $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica', 'normal');
                $pdf->getDomPDF()->getCanvas()->page_text(
                    500,
                    820,
                    'Page {PAGE_NUM} of {PAGE_COUNT}',
                    $font,
                    8,
                    [70, 80, 90],
                );

                $storedPdfPath = 'reports/'.$lockedRequest->pro_number.'.pdf';
                if (! Storage::disk('local')->put($storedPdfPath, $pdf->output())) {
                    $storedPdfPath = null;
                    throw new RuntimeException('PDF laporan gagal disimpan.');
                }

                return FinalReport::query()->create([
                    'lab_request_id' => $lockedRequest->id,
                    'validator_id' => $request->user()->id,
                    'approved_at' => $approvedAt,
                    'pdf_path' => $storedPdfPath,
                    'report_snapshot' => $snapshot,
                ]);
            });
        } catch (Throwable $exception) {
            if ($storedPdfPath !== null) {
                Storage::disk('local')->delete($storedPdfPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('reports.download', $finalReport)
            ->with('status', 'Laporan final berhasil diterbitkan.');
    }

    public function download(Request $request, FinalReport $finalReport): BinaryFileResponse
    {
        $finalReport->loadMissing('labRequest');
        if ($request->user()->role === 'requestor') {
            abort_unless($finalReport->labRequest->requestor_id === $request->user()->id, 404);
        }
        abort_unless($finalReport->pdf_path, 404);
        abort_unless(Storage::disk('local')->exists($finalReport->pdf_path), 404);

        return response()->download(
            Storage::disk('local')->path($finalReport->pdf_path),
            $finalReport->labRequest->pro_number.'.pdf',
        );
    }

    private function ensureAwaitingValidation(LabRequest $labRequest): void
    {
        abort_unless($labRequest->status === 'draft', 404);
        abort_if(
            DB::table('final_reports')->where('lab_request_id', $labRequest->id)->exists(),
            409,
            'Laporan final sudah diterbitkan.',
        );
    }

    private function buildReportSnapshot(
        LabRequest $labRequest,
        User $validator,
        CarbonInterface $approvedAt,
        Collection $beforePhotos,
        Collection $afterPhotos,
    ): array {
        $labRequest->load(['testPackage', 'specimen', 'csValidation']);
        $testItems = $labRequest->testItems()
            ->wherePivot('selected', true)
            ->orderBy('test_items.code')
            ->get();
        $results = TestResult::query()
            ->with('details')
            ->whereIn('lab_request_test_item_id', $testItems->pluck('pivot.id'))
            ->get()
            ->keyBy('lab_request_test_item_id');

        $tests = $testItems->map(function ($testItem) use ($results) {
            $testResult = $results->get($testItem->pivot->id);

            return [
                'code' => $testItem->code,
                'name' => $testItem->name,
                'method' => $testItem->test_method,
                'matrix_type' => $testResult->matrix_type,
                'result_status' => $testResult->result_status,
                'remark' => $testResult->remark,
                'details' => $testResult->details
                    ->sortBy('sort_order')
                    ->map(fn ($detail) => [
                        'component_name' => $detail->component_name,
                        'measurements' => $detail->measurements,
                    ])
                    ->values()
                    ->all(),
            ];
        })->all();

        return [
            'report_no' => $labRequest->pro_number,
            'package_name' => $labRequest->testPackage->name,
            'issued_at' => $approvedAt->toDateTimeString(),
            'sample' => [
                'applicant' => $labRequest->applicant,
                'brand' => $labRequest->brand,
                'sample_description' => $labRequest->sample_description,
                'season' => $labRequest->season,
                'phase' => $labRequest->phase,
                'style' => $labRequest->style,
                'colour' => $labRequest->colour,
                'product_type' => $labRequest->product_type,
                'po_number' => $labRequest->po_number,
                'destination' => $labRequest->destination,
                'fiber_content' => $labRequest->fiber_content,
                'care_instruction' => $labRequest->care_instruction,
                'previous_test' => $labRequest->previous_test,
                'previous_report_no' => $labRequest->previous_report_no,
                'retest' => $labRequest->retest,
                'previous_fail_test' => $labRequest->previous_fail_test,
                'previous_fail_report_no' => $labRequest->previous_fail_report_no,
                'function' => $labRequest->function,
                'test_start_date' => $labRequest->test_start_date?->format('Y-m-d'),
                'estimated_completion_date' => $labRequest->csValidation?->estimated_completion_date?->format('Y-m-d'),
                'issue_date' => $labRequest->issue_date?->format('Y-m-d'),
                'specimen_quantity' => $labRequest->specimen?->initial_quantity,
                'specimen_unit' => $labRequest->specimen?->unit,
            ],
            'tests' => $tests,
            'photos' => [
                'before' => $beforePhotos->map(fn ($photo) => [
                    'path' => $photo->path,
                    'sort_order' => $photo->sort_order,
                ])->values()->all(),
                'after' => $afterPhotos->map(fn ($photo) => [
                    'path' => $photo->path,
                    'remark' => $photo->remark,
                    'sort_order' => $photo->sort_order,
                ])->values()->all(),
            ],
            'approval' => [
                'validator_name' => $validator->name,
                'approved_at' => $approvedAt->toDateTimeString(),
            ],
        ];
    }

    private function preparePdfData(array $snapshot): array
    {
        foreach (['before', 'after'] as $phase) {
            foreach ($snapshot['photos'][$phase] as &$photo) {
                $extension = strtolower(pathinfo($photo['path'], PATHINFO_EXTENSION));
                $mime = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => throw new RuntimeException('Unsupported image type in report.'),
                };
                $photo['data_uri'] = 'data:'.$mime.';base64,'.base64_encode(
                    Storage::disk('local')->get($photo['path']),
                );
            }
            unset($photo);
        }

        return $snapshot;
    }
}
