<?php

namespace Tests\Feature;

use App\Models\LabRequest;
use App\Models\TestPackage;
use App\Models\TestResult;
use App\Models\User;
use Database\Seeders\TestPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $csUser;

    private LabRequest $labRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TestPackageSeeder::class);
        $this->csUser = User::factory()->create(['role' => User::ROLE_CS]);

        $requestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $package = TestPackage::query()->where('slug', 'basic-garment')->firstOrFail();
        $this->labRequest = LabRequest::query()->create([
            'requestor_id' => $requestor->id,
            'test_package_id' => $package->id,
            'pro_number' => 'PRO-00000001',
            'applicant' => 'Applicant',
            'brand' => 'Brand',
            'sample_description' => 'Sample description',
            'season' => 'W26',
            'style' => 'STYLE-01',
            'status' => 'submitted',
        ]);
        $this->labRequest->testItems()->attach(
            $package->testItems->mapWithKeys(fn ($item) => [$item->id => ['selected' => true]])->all(),
        );
        $this->labRequest->specimen()->create(['initial_quantity' => 2]);
    }

    public function test_cs_can_edit_optional_fields_and_deselect_package_items(): void
    {
        $testItemIds = $this->labRequest->testItems()->pluck('test_items.id')->all();

        $this->actingAs($this->csUser)
            ->put(route('cs.requests.update', $this->labRequest), [
                'applicant' => 'Changed applicant',
                'brand' => 'Changed brand',
                'season' => 'Changed season',
                'style' => 'Changed style',
                'colour' => 'Navy',
                'sample_description' => 'Corrected description',
                'selected_test_item_ids' => [$testItemIds[0]],
            ])
            ->assertRedirect(route('cs.requests.show', $this->labRequest));

        $this->labRequest->refresh();
        $this->assertSame('Applicant', $this->labRequest->applicant);
        $this->assertSame('Brand', $this->labRequest->brand);
        $this->assertSame('W26', $this->labRequest->season);
        $this->assertSame('STYLE-01', $this->labRequest->style);
        $this->assertSame('Navy', $this->labRequest->colour);
        $this->assertSame(1, $this->labRequest->testItems()->wherePivot('selected', true)->count());
    }

    public function test_cs_cannot_validate_without_before_photos_and_a_manual_estimate(): void
    {
        $this->actingAs($this->csUser)
            ->post(route('cs.requests.validate', $this->labRequest), [])
            ->assertSessionHasErrors(['estimated_completion_date', 'before_photos']);

        $this->assertSame('submitted', $this->labRequest->fresh()->status);
    }

    public function test_cs_cannot_upload_more_than_three_before_photos(): void
    {
        Storage::fake('local');
        $files = [
            UploadedFile::fake()->image('before-1.jpg'),
            UploadedFile::fake()->image('before-2.jpg'),
            UploadedFile::fake()->image('before-3.jpg'),
            UploadedFile::fake()->image('before-4.jpg'),
        ];

        $this->actingAs($this->csUser)
            ->post(route('cs.requests.photos.store', $this->labRequest), ['before_photos' => $files])
            ->assertSessionHasErrors('before_photos');

        $this->assertDatabaseCount('specimen_photos', 0);
    }

    public function test_cs_validation_records_manual_estimate_and_queues_request(): void
    {
        Storage::fake('local');
        $this->actingAs($this->csUser)
            ->post(route('cs.requests.photos.store', $this->labRequest), [
                'before_photos' => [UploadedFile::fake()->image('before.jpg')],
            ])
            ->assertSessionHasNoErrors();

        $estimate = now()->addDays(5)->toDateString();
        $this->actingAs($this->csUser)
            ->post(route('cs.requests.validate', $this->labRequest), [
                'estimated_completion_date' => $estimate,
                'notes' => 'Conditioning completed',
            ])
            ->assertRedirect(route('cs.requests.index'));

        $this->assertSame('queued', $this->labRequest->fresh()->status);
        $this->assertDatabaseHas('cs_validations', [
            'lab_request_id' => $this->labRequest->id,
            'cs_user_id' => $this->csUser->id,
            'estimated_completion_date' => $estimate,
            'notes' => 'Conditioning completed',
        ]);
        $this->assertSame(8, TestResult::query()->count());
    }
}
