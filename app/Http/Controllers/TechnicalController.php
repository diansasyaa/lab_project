<?php

namespace App\Http\Controllers;

use App\Models\LabRequest;
use App\Models\LabRequestTestItem;
use App\Models\TestItem;
use App\Models\TestResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TechnicalController extends Controller
{
    private const TABLE1_COMPONENTS = [
        'Length wise',
        'Width wise',
        'Back Length',
        'Front Length',
        'Sleeve Length',
        'Armhole',
        'Raglan',
        'Body Width (Chest)',
        'Bottom Width',
        'Shoulder',
        'Cuff Width',
    ];

    private const TABLE2_COMPONENTS = [
        'Discoloration / Color Change (grade)',
        'Staining (grade)',
        'Pilling (grade)',
        'Shrinkage & Spirality',
        'Print / Heat Transfer',
        'Embroidery',
        'Label',
        'Zipper / Snap Button / Button / etc.',
        'Appearance of Garment After Wash',
    ];

    private const RESULT_STATUSES = ['P', 'F', 'N/A', 'D'];

    public function index(): View
    {
        $labRequests = LabRequest::query()
            ->whereIn('status', ['queued', 'draft'])
            ->whereHas('testItems', fn ($query) => $query->where('lab_request_test_items.selected', true))
            ->with('testPackage')
            ->orderBy('id')
            ->paginate(10);

        return view('technical.requests.index', compact('labRequests'));
    }

    public function show(LabRequest $labRequest): View
    {
        $this->ensureTechnicianCanEdit($labRequest);

        $testItems = $labRequest->testItems()
            ->wherePivot('selected', true)
            ->orderBy('test_items.code')
            ->get();

        $results = TestResult::query()
            ->with('details')
            ->whereIn('lab_request_test_item_id', $testItems->pluck('pivot.id'))
            ->get()
            ->keyBy('lab_request_test_item_id');

        abort_if($results->count() !== $testItems->count(), 409, 'Hasil tes belum diinisialisasi. Hubungi CS Lab.');

        return view('technical.requests.show', [
            'labRequest' => $labRequest,
            'testItems' => $testItems,
            'results' => $results,
            'table1Components' => self::TABLE1_COMPONENTS,
            'table2Components' => self::TABLE2_COMPONENTS,
            'resultStatuses' => self::RESULT_STATUSES,
        ]);
    }

    public function updateResult(Request $request, LabRequest $labRequest, TestItem $testItem): RedirectResponse
    {
        $validated = $request->validate([
            'matrix_type' => ['required', Rule::in(['table1', 'table2'])],
            'result_status' => ['required', Rule::in(self::RESULT_STATUSES)],
            'remark' => ['nullable', 'string', 'max:5000'],
        ]);

        $details = $validated['matrix_type'] === 'table1'
            ? $this->table1Details($request)
            : $this->table2Details($request);

        DB::transaction(function () use ($request, $labRequest, $testItem, $validated, $details) {
            $this->ensureTechnicianCanEdit($labRequest);

            $requestTestItem = LabRequestTestItem::query()
                ->where('lab_request_id', $labRequest->id)
                ->where('test_item_id', $testItem->id)
                ->where('selected', true)
                ->firstOrFail();

            $testResult = TestResult::query()
                ->where('lab_request_test_item_id', $requestTestItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $testResult->update([
                'last_updated_by_id' => $request->user()->id,
                'matrix_type' => $validated['matrix_type'],
                'result_status' => $validated['result_status'],
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
                'submitted_at' => now(),
            ]);

            $testResult->details()->delete();
            foreach ($details as $detail) {
                $testResult->details()->create($detail);
            }
        });

        $this->setRequestDraftWhenComplete($labRequest->id);

        return redirect()
            ->route('technical.requests.show', $labRequest)
            ->with('status', 'Hasil item tes berhasil disimpan.');
    }

    private function table1Details(Request $request): array
    {
        $rules = [
            'table1' => ['required', 'array', 'size:'.count(self::TABLE1_COMPONENTS)],
            'table1.*' => ['required', 'array'],
        ];

        foreach (self::TABLE1_COMPONENTS as $index => $component) {
            foreach (['original_cm', 'after_1_wash_cm', 'after_5_washes_cm'] as $field) {
                $rules["table1.{$index}.{$field}"] = ['nullable', 'numeric', 'min:0', 'max:1000000'];
            }
            $rules["table1.{$index}.requirement"] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);
        $details = [];

        foreach (self::TABLE1_COMPONENTS as $index => $component) {
            $row = $validated['table1'][$index] ?? [];
            $original = $this->numericValue($row['original_cm'] ?? null);
            $afterOneWash = $this->numericValue($row['after_1_wash_cm'] ?? null);
            $afterFiveWashes = $this->numericValue($row['after_5_washes_cm'] ?? null);

            if (($afterOneWash !== null || $afterFiveWashes !== null) && ($original === null || $original === 0.0)) {
                throw ValidationException::withMessages([
                    "table1.{$index}.original_cm" => 'Nilai Original harus lebih dari 0 untuk menghitung perubahan dimensi.',
                ]);
            }

            $details[] = [
                'component_name' => $component,
                'sort_order' => $index + 1,
                'measurements' => [
                    'original_cm' => $original,
                    'after_1_wash_cm' => $afterOneWash,
                    'dimensional_change_after_1_percent' => $this->dimensionalChange($original, $afterOneWash),
                    'after_5_washes_cm' => $afterFiveWashes,
                    'dimensional_change_after_5_percent' => $this->dimensionalChange($original, $afterFiveWashes),
                    'requirement' => $row['requirement'] ?? null,
                ],
            ];
        }

        return $details;
    }

    private function table2Details(Request $request): array
    {
        $rules = [
            'table2' => ['required', 'array', 'size:'.count(self::TABLE2_COMPONENTS)],
            'table2.*' => ['required', 'array'],
        ];

        foreach (self::TABLE2_COMPONENTS as $index => $component) {
            foreach (['after_1_wash', 'after_3_washes', 'requirement'] as $field) {
                $rules["table2.{$index}.{$field}"] = ['nullable', 'string', 'max:255'];
            }
        }

        $validated = $request->validate($rules);
        $details = [];

        foreach (self::TABLE2_COMPONENTS as $index => $component) {
            $row = $validated['table2'][$index] ?? [];
            $details[] = [
                'component_name' => $component,
                'sort_order' => $index + 1,
                'measurements' => [
                    'after_1_wash' => $row['after_1_wash'] ?? null,
                    'after_3_washes' => $row['after_3_washes'] ?? null,
                    'requirement' => $row['requirement'] ?? null,
                ],
            ];
        }

        return $details;
    }

    private function numericValue(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function dimensionalChange(?float $original, ?float $after): ?float
    {
        if ($original === null || $after === null || $original === 0.0) {
            return null;
        }

        return round((($after - $original) / $original) * 100, 2);
    }

    private function ensureTechnicianCanEdit(LabRequest $labRequest): void
    {
        abort_unless(in_array($labRequest->status, ['queued', 'draft'], true), 404);
        abort_if(
            DB::table('final_reports')->where('lab_request_id', $labRequest->id)->exists(),
            409,
            'Hasil tidak dapat diedit setelah laporan final diterbitkan.',
        );
    }

    private function setRequestDraftWhenComplete(int $labRequestId): void
    {
        DB::transaction(function () use ($labRequestId) {
            $labRequest = LabRequest::query()->lockForUpdate()->findOrFail($labRequestId);
            if ($labRequest->status !== 'queued') {
                return;
            }

            $hasPendingResults = DB::table('lab_request_test_items')
                ->leftJoin('test_results', 'test_results.lab_request_test_item_id', '=', 'lab_request_test_items.id')
                ->where('lab_request_test_items.lab_request_id', $labRequest->id)
                ->where('lab_request_test_items.selected', true)
                ->where(function ($query) {
                    $query->whereNull('test_results.id')
                        ->orWhere('test_results.status', '!=', 'draft');
                })
                ->exists();

            if (! $hasPendingResults) {
                $labRequest->update(['status' => 'draft']);
            }
        });
    }
}
