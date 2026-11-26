<?php

namespace App\Http\Controllers\Requestor;

use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\TestPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class LabRequestController extends Controller
{
    public function index(Request $request): View
    {
        $labRequests = LabRequest::query()
            ->where('requestor_id', $request->user()->id)
            ->with('testPackage')
            ->latest()
            ->paginate(10);

        return view('requestor.requests.index', compact('labRequests'));
    }

    public function create(): View
    {
        return view('requestor.requests.create', [
            'packages' => TestPackage::query()
                ->where('is_active', true)
                ->with('testItems')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_package_id' => [
                'required',
                'integer',
                Rule::exists('test_packages', 'id')->where('is_active', true),
            ],
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
            'initial_quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $initialQuantity = $validated['initial_quantity'];
        unset($validated['initial_quantity']);
        $validated['previous_test'] = $request->boolean('previous_test');
        $validated['retest'] = $request->boolean('retest');

        $labRequest = DB::transaction(function () use ($validated, $initialQuantity, $request) {
            $package = TestPackage::query()
                ->whereKey($validated['test_package_id'])
                ->where('is_active', true)
                ->with('testItems')
                ->firstOrFail();

            $labRequest = LabRequest::query()->create([
                ...$validated,
                'requestor_id' => $request->user()->id,
                'status' => 'submitted',
            ]);

            $testItems = $package->testItems
                ->mapWithKeys(fn ($item) => [$item->id => ['selected' => true]])
                ->all();
            $labRequest->testItems()->attach($testItems);
            $labRequest->specimen()->create(['initial_quantity' => $initialQuantity]);

            $sequence = DB::table('pro_sequences')->where('id', 1)->lockForUpdate()->first();
            if (! $sequence) {
                throw new RuntimeException('PRO sequence is missing. Run the database migrations.');
            }

            $nextNumber = (int) $sequence->last_number + 1;
            DB::table('pro_sequences')->where('id', 1)->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);
            $labRequest->forceFill(['pro_number' => sprintf('PRO-%08d', $nextNumber)])->save();

            return $labRequest;
        });

        return redirect()
            ->route('requestor.requests.show', $labRequest)
            ->with('status', 'Pengajuan berhasil dibuat.');
    }

    public function show(Request $request, LabRequest $labRequest): View
    {
        abort_unless($labRequest->requestor_id === $request->user()->id, 404);

        $labRequest->load(['testPackage', 'testItems', 'specimen', 'finalReport']);

        return view('requestor.requests.show', compact('labRequest'));
    }
}
