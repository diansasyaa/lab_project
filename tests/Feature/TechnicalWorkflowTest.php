<?php

namespace Tests\Feature;

use App\Models\LabRequest;
use App\Models\LabRequestTestItem;
use App\Models\TestItem;
use App\Models\TestPackage;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\TestPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;

    private LabRequest $labRequest;

    private TestItem $firstItem;

    private TestItem $secondItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TestPackageSeeder::class);
        $this->technician = User::factory()->create(['role' => User::ROLE_TECHNICIAN]);
        $requestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $package = TestPackage::query()->where('slug', 'basic-garment')->firstOrFail();
        $this->firstItem = TestItem::query()->where('code', 'LAB-0004')->firstOrFail();
        $this->secondItem = TestItem::query()->where('code', 'LAB-0005')->firstOrFail();
        $this->labRequest = LabRequest::query()->create([
            'requestor_id' => $requestor->id,
            'test_package_id' => $package->id,
            'pro_number' => 'PRO-00000001',
            'applicant' => 'Applicant',
            'brand' => 'Brand',
            'sample_description' => 'Sample description',
            'season' => 'W26',
            'style' => 'STYLE-01',
            'status' => 'queued',
        ]);

        foreach ([$this->firstItem, $this->secondItem] as $item) {
            $pivot = LabRequestTestItem::query()->create([
                'lab_request_id' => $this->labRequest->id,
                'test_item_id' => $item->id,
                'selected' => true,
            ]);

            TestResult::query()->create([
                'lab_request_test_item_id' => $pivot->id,
                'status' => 'pending',
            ]);
        }
    }

    public function test_technicians_can_submit_either_matrix_for_selected_items_until_all_are_done(): void
    {
        $this->actingAs($this->technician)
            ->get(route('technical.requests.index'))
            ->assertOk()
            ->assertSee($this->labRequest->pro_number);

        $this->actingAs($this->technician)
            ->get(route('technical.requests.show', $this->labRequest))
            ->assertOk()
            ->assertSee('Tabel 1 — Dimensional Change')
            ->assertSee('Tabel 2 — Evaluation After Wash');

        $table1 = array_fill(0, 11, [
            'original_cm' => null,
            'after_1_wash_cm' => null,
            'after_5_washes_cm' => null,
            'requirement' => null,
        ]);
        $table1[0] = [
            'original_cm' => '50',
            'after_1_wash_cm' => '45',
            'after_5_washes_cm' => '52',
            'requirement' => 'Within tolerance',
        ];

        $this->actingAs($this->technician)
            ->put(route('technical.requests.items.update', [$this->labRequest, $this->firstItem]), [
                'matrix_type' => 'table1',
                'result_status' => 'P',
                'table1' => $table1,
            ])
            ->assertRedirect(route('technical.requests.show', $this->labRequest));

        $this->assertSame('queued', $this->labRequest->fresh()->status);
        $firstResult = TestResult::query()->whereHas('requestTestItem', fn ($query) => $query
            ->where('lab_request_id', $this->labRequest->id)
            ->where('test_item_id', $this->firstItem->id))->firstOrFail();
        $this->assertSame('table1', $firstResult->matrix_type);
        $this->assertSame('P', $firstResult->result_status);
        $this->assertSame(-10.0, (float) $firstResult->details->first()->measurements['dimensional_change_after_1_percent']);
        $this->assertSame(4.0, (float) $firstResult->details->first()->measurements['dimensional_change_after_5_percent']);

        $table2 = array_fill(0, 9, [
            'after_1_wash' => null,
            'after_3_washes' => null,
            'requirement' => null,
        ]);
        $table2[0] = [
            'after_1_wash' => '4',
            'after_3_washes' => '4-5',
            'requirement' => 'Minimum grade 4',
        ];

        $this->actingAs($this->technician)
            ->put(route('technical.requests.items.update', [$this->labRequest, $this->secondItem]), [
                'matrix_type' => 'table2',
                'result_status' => 'D',
                'remark' => 'Data recorded',
                'table2' => $table2,
            ])
            ->assertRedirect(route('technical.requests.show', $this->labRequest));

        $this->assertSame('draft', $this->labRequest->fresh()->status);
        $secondResult = TestResult::query()->whereHas('requestTestItem', fn ($query) => $query
            ->where('lab_request_id', $this->labRequest->id)
            ->where('test_item_id', $this->secondItem->id))->firstOrFail();
        $this->assertSame('table2', $secondResult->matrix_type);
        $this->assertSame('D', $secondResult->result_status);
        $this->assertSame('4-5', $secondResult->details->first()->measurements['after_3_washes']);
    }

    public function test_technician_cannot_submit_results_for_unselected_items(): void
    {
        $pivot = LabRequestTestItem::query()
            ->where('lab_request_id', $this->labRequest->id)
            ->where('test_item_id', $this->secondItem->id)
            ->firstOrFail();
        $pivot->update(['selected' => false]);

        $this->actingAs($this->technician)
            ->put(route('technical.requests.items.update', [$this->labRequest, $this->secondItem]), [
                'matrix_type' => 'table2',
                'result_status' => 'P',
                'table2' => array_fill(0, 9, [
                    'after_1_wash' => null,
                    'after_3_washes' => null,
                    'requirement' => null,
                ]),
            ])
            ->assertNotFound();
    }
}
