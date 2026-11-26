<?php

namespace App\Http\Controllers;

use App\Models\CsValidation;
use App\Models\LabRequest;
use App\Models\SpecimenPhoto;
use App\Models\TestResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CsController extends Controller
{
    public function index(): View
    {
        $incomingRequests = LabRequest::query()
            ->where('status', 'submitted')
            ->with('testPackage', 'requestor')
            ->oldest()
            ->get();

        $queueRequests = LabRequest::query()
            ->where('status', 'queued')
            ->with('testPackage', 'csValidation')
            ->orderBy('id')
            ->get();

        return view('cs.requests.index', compact('incomingRequests', 'queueRequests'));
    }

    public function show(LabRequest $labRequest): View
    {
        $labRequest->load([
            'testPackage',
            'testItems',
            'specimen',
            'specimenPhotos' => fn ($query) => $query
                ->where('phase', 'before')
                ->orderBy('sort_order'),
            'csValidation',
        ]);

        return view('cs.requests.show', compact('labRequest'));
    }

    public function update(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $validated = $request->validate([
            'sample_description' => ['required', 'string', 'max:5000'],
            'phase' => ['nullable', 'string', 'max:255'],
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
            'selected_test_item_ids' => ['sometimes', 'array'],
            'selected_test_item_ids.*' => ['required', 'integer'],
        ]);

        $selectedIds = array_map('intval', $validated['selected_test_item_ids'] ?? []);
        unset($validated['selected_test_item_ids']);
        $validated['previous_test'] = $request->boolean('previous_test');
        $validated['retest'] = $request->boolean('retest');

        DB::transaction(function () use ($labRequest, $validated, $selectedIds) {
            $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            $this->ensureAwaitingCs($lockedRequest);

            $eligibleIds = DB::table('lab_request_test_items')
                ->where('lab_request_id', $lockedRequest->id)
                ->pluck('test_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (array_diff($selectedIds, $eligibleIds) !== []) {
                throw ValidationException::withMessages([
                    'selected_test_item_ids' => 'Pilihan item tes tidak sesuai dengan paket request ini.',
                ]);
            }

            $lockedRequest->fill($validated)->save();

            DB::table('lab_request_test_items')
                ->where('lab_request_id', $lockedRequest->id)
                ->update(['selected' => false, 'updated_at' => now()]);

            if ($selectedIds !== []) {
                DB::table('lab_request_test_items')
                    ->where('lab_request_id', $lockedRequest->id)
                    ->whereIn('test_item_id', $selectedIds)
                    ->update(['selected' => true, 'updated_at' => now()]);
            }
        });

        return redirect()
            ->route('cs.requests.show', $labRequest)
            ->with('status', 'Perubahan validasi CS berhasil disimpan.');
    }

    public function storePhotos(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $validated = $request->validate([
            'before_photos' => ['required', 'array', 'min:1'],
            'before_photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photos = $validated['before_photos'];
        $storedPaths = [];

        try {
            DB::transaction(function () use ($labRequest, $photos, $request, &$storedPaths) {
                $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
                $this->ensureAwaitingCs($lockedRequest);

                $currentCount = SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'before')
                    ->count();

                if ($currentCount + count($photos) > 3) {
                    throw ValidationException::withMessages([
                        'before_photos' => 'Maksimal 3 foto Before Test untuk satu request.',
                    ]);
                }

                $sortOrder = (int) SpecimenPhoto::query()
                    ->where('lab_request_id', $lockedRequest->id)
                    ->where('phase', 'before')
                    ->max('sort_order');

                foreach ($photos as $photo) {
                    $path = $photo->store("specimen-photos/{$lockedRequest->id}/before", 'local');
                    if ($path === false) {
                        throw new RuntimeException('Foto specimen gagal disimpan.');
                    }

                    $storedPaths[] = $path;
                    $sortOrder++;

                    SpecimenPhoto::query()->create([
                        'lab_request_id' => $lockedRequest->id,
                        'uploaded_by_id' => $request->user()->id,
                        'phase' => 'before',
                        'path' => $path,
                        'sort_order' => $sortOrder,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return redirect()
            ->route('cs.requests.show', $labRequest)
            ->with('status', 'Foto Before Test berhasil diunggah.');
    }

    public function destroyPhoto(LabRequest $labRequest, SpecimenPhoto $specimenPhoto): RedirectResponse
    {
        DB::transaction(function () use ($labRequest, $specimenPhoto) {
            $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            $this->ensureAwaitingCs($lockedRequest);

            abort_unless(
                $specimenPhoto->lab_request_id === $lockedRequest->id
                    && $specimenPhoto->phase === 'before',
                404,
            );

            $deleted = Storage::disk('local')->delete($specimenPhoto->path);
            if (! $deleted) {
                throw new RuntimeException('Foto specimen gagal dihapus dari penyimpanan.');
            }

            $specimenPhoto->delete();
        });

        return redirect()
            ->route('cs.requests.show', $labRequest)
            ->with('status', 'Foto Before Test berhasil dihapus.');
    }

    public function showPhoto(SpecimenPhoto $specimenPhoto): BinaryFileResponse
    {
        abort_unless($specimenPhoto->phase === 'before', 404);
        abort_unless(Storage::disk('local')->exists($specimenPhoto->path), 404);

        return response()->file(Storage::disk('local')->path($specimenPhoto->path));
    }

    public function validateRequest(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'estimated_completion_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validator->after(function ($validator) use ($labRequest) {
            $photoCount = SpecimenPhoto::query()
                ->where('lab_request_id', $labRequest->id)
                ->where('phase', 'before')
                ->count();

            if ($photoCount < 1 || $photoCount > 3) {
                $validator->errors()->add(
                    'before_photos',
                    'Unggah 1 sampai 3 foto Before Test sebelum validasi.',
                );
            }
        });
        $validated = $validator->validate();

        DB::transaction(function () use ($request, $labRequest, $validated) {
            $lockedRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequest->id);
            $this->ensureAwaitingCs($lockedRequest);

            $photoCount = SpecimenPhoto::query()
                ->where('lab_request_id', $lockedRequest->id)
                ->where('phase', 'before')
                ->count();

            if ($photoCount < 1 || $photoCount > 3) {
                throw ValidationException::withMessages([
                    'before_photos' => 'Unggah 1 sampai 3 foto Before Test sebelum validasi.',
                ]);
            }

            $selectedCount = DB::table('lab_request_test_items')
                ->where('lab_request_id', $lockedRequest->id)
                ->where('selected', true)
                ->count();

            if ($selectedCount < 1) {
                throw ValidationException::withMessages([
                    'selected_test_item_ids' => 'Pilih minimal satu item tes sebelum request masuk antrean.',
                ]);
            }

            CsValidation::query()->create([
                'lab_request_id' => $lockedRequest->id,
                'cs_user_id' => $request->user()->id,
                'validated_at' => now(),
                'estimated_completion_date' => $validated['estimated_completion_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $selectedItems = DB::table('lab_request_test_items')
                ->where('lab_request_id', $lockedRequest->id)
                ->where('selected', true)
                ->pluck('id');

            foreach ($selectedItems as $requestTestItemId) {
                TestResult::query()->create([
                    'lab_request_test_item_id' => $requestTestItemId,
                    'status' => 'pending',
                ]);
            }

            $lockedRequest->update(['status' => 'queued']);
        });

        return redirect()
            ->route('cs.requests.index')
            ->with('status', 'Request tervalidasi dan masuk antrean Technical Lab.');
    }

    private function ensureAwaitingCs(LabRequest $labRequest): void
    {
        abort_unless($labRequest->status === 'submitted', 409, 'Request ini tidak lagi menunggu validasi CS.');
    }
}
