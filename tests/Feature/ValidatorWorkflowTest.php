<?php

namespace Tests\Feature;

use App\Models\FinalReport;
use App\Models\LabRequest;
use App\Models\LabRequestTestItem;
use App\Models\SpecimenPhoto;
use App\Models\TestItem;
use App\Models\TestItemDetail;
use App\Models\TestPackage;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\TestPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValidatorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $validator;

    private LabRequest $labRequest;

    private TestResult $testResult;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(TestPackageSeeder::class);
        $this->validator = User::factory()->create(['role' => User::ROLE_VALIDATOR]);
        $requestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $package = TestPackage::query()->where('slug', 'basic-garment')->firstOrFail();
        $item = TestItem::query()->where('code', 'LAB-0004')->firstOrFail();
        $this->labRequest = LabRequest::query()->create([
            'requestor_id' => $requestor->id,
            'test_package_id' => $package->id,
            'pro_number' => 'PRO-00000001',
            'applicant' => 'Applicant',
            'brand' => 'Brand',
            'sample_description' => 'Sample description',
            'season' => 'W26',
            'style' => 'STYLE-01',
            'status' => 'draft',
        ]);
        $requestTestItem = LabRequestTestItem::query()->create([
            'lab_request_id' => $this->labRequest->id,
            'test_item_id' => $item->id,
            'selected' => true,
        ]);
        $this->testResult = TestResult::query()->create([
            'lab_request_test_item_id' => $requestTestItem->id,
            'matrix_type' => 'table1',
            'result_status' => 'P',
            'status' => 'draft',
            'remark' => 'Technician remark',
        ]);
        TestItemDetail::query()->create([
            'test_result_id' => $this->testResult->id,
            'component_name' => 'Length wise',
            'sort_order' => 1,
            'measurements' => [
                'original_cm' => 50,
                'after_1_wash_cm' => 49,
                'dimensional_change_after_1_percent' => -2,
                'after_5_washes_cm' => 48,
                'dimensional_change_after_5_percent' => -4,
                'requirement' => 'Within tolerance',
            ],
        ]);

        $beforePath = UploadedFile::fake()->image('before.jpg')
            ->store("specimen-photos/{$this->labRequest->id}/before", 'local');
        SpecimenPhoto::query()->create([
            'lab_request_id' => $this->labRequest->id,
            'uploaded_by_id' => $requestor->id,
            'phase' => 'before',
            'path' => $beforePath,
            'sort_order' => 1,
        ]);
    }

    public function test_validator_cannot_finalize_without_matching_after_photo(): void
    {
        $this->actingAs($this->validator)
            ->post(route('validator.requests.finalize', $this->labRequest), [
                'confirm_finalization' => '1',
            ])
            ->assertSessionHasErrors('after_photos');

        $this->assertSame('draft', $this->labRequest->fresh()->status);
        $this->assertDatabaseCount('final_reports', 0);
    }

    public function test_validator_corrects_data_uploads_after_photo_and_issues_pdf(): void
    {
        $this->actingAs($this->validator)
            ->put(route('validator.requests.update', $this->labRequest), [
                'applicant' => 'Corrected Applicant',
                'brand' => 'Corrected Brand',
                'sample_description' => 'Corrected sample description',
                'season' => 'W27',
                'style' => 'STYLE-01',
                'colour' => 'Navy',
                'previous_test' => '0',
                'retest' => '0',
            ])
            ->assertRedirect(route('validator.requests.show', $this->labRequest));

        $this->actingAs($this->validator)
            ->put(route('validator.requests.results.update', $this->labRequest), [
                'result_statuses' => [$this->testResult->id => 'F'],
            ])
            ->assertRedirect(route('validator.requests.show', $this->labRequest));

        $this->actingAs($this->validator)
            ->put(route('validator.requests.photos.update', [$this->labRequest, 1]), [
                'after_photo' => UploadedFile::fake()->image('after.jpg'),
                'remark' => 'No visible change after washing',
            ])
            ->assertRedirect(route('validator.requests.show', $this->labRequest));

        $this->actingAs($this->validator)
            ->get(route('validator.requests.show', $this->labRequest))
            ->assertOk()
            ->assertSee('Corrected Applicant')
            ->assertSee('No visible change after washing');

        $response = $this->actingAs($this->validator)
            ->post(route('validator.requests.finalize', $this->labRequest), [
                'confirm_finalization' => '1',
            ])
            ->assertRedirect();

        $finalReport = FinalReport::query()->firstOrFail();
        $this->assertSame('approved', $this->labRequest->fresh()->status);
        $this->assertSame('Corrected Applicant', $this->labRequest->fresh()->applicant);
        $this->assertSame('F', $this->testResult->fresh()->result_status);
        $this->assertSame('F', $finalReport->report_snapshot['tests'][0]['result_status']);
        $this->assertTrue(Storage::disk('local')->exists($finalReport->pdf_path));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($finalReport->pdf_path));
        $this->assertSame(
            route('reports.download', $finalReport),
            $response->headers->get('Location'),
        );

        $this->get(route('reports.download', $finalReport))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $otherRequestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $this->actingAs($otherRequestor)
            ->get(route('reports.download', $finalReport))
            ->assertNotFound();
    }
}
