<?php

namespace Tests\Feature;

use App\Models\LabRequest;
use App\Models\TestPackage;
use App\Models\User;
use Database\Seeders\TestPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestorLabRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requestor_submission_creates_pro_specimen_and_package_items(): void
    {
        $this->seed(TestPackageSeeder::class);
        $requestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $package = TestPackage::query()->where('slug', 'basic-garment')->firstOrFail();

        $response = $this->actingAs($requestor)->post(route('requestor.requests.store'), [
            'test_package_id' => $package->id,
            'applicant' => 'Lab Applicant',
            'brand' => 'Sample Brand',
            'sample_description' => 'Test garment',
            'season' => 'W26',
            'style' => 'ST-001',
            'initial_quantity' => 3,
        ]);

        $response->assertRedirect();
        $labRequest = LabRequest::query()->firstOrFail();

        $this->assertSame('PRO-00000001', $labRequest->pro_number);
        $this->assertSame(8, $labRequest->testItems()->count());
        $this->assertSame(3, $labRequest->specimen->initial_quantity);

        $this->actingAs($requestor)->post(route('requestor.requests.store'), [
            'test_package_id' => $package->id,
            'applicant' => 'Lab Applicant',
            'brand' => 'Sample Brand',
            'sample_description' => 'Second test garment',
            'season' => 'W26',
            'style' => 'ST-002',
            'initial_quantity' => 1,
        ])->assertRedirect();

        $this->assertSame(
            ['PRO-00000001', 'PRO-00000002'],
            LabRequest::query()->orderBy('id')->pluck('pro_number')->all(),
        );
    }

    public function test_requestor_must_choose_exactly_one_active_test_package(): void
    {
        $this->seed(TestPackageSeeder::class);
        $requestor = User::factory()->create(['role' => User::ROLE_REQUESTOR]);
        $packageIds = TestPackage::query()->pluck('id')->all();

        $this->actingAs($requestor)
            ->from(route('requestor.requests.create'))
            ->post(route('requestor.requests.store'), [
                'test_package_id' => $packageIds,
                'applicant' => 'Lab Applicant',
                'brand' => 'Sample Brand',
                'sample_description' => 'Test garment',
                'season' => 'W26',
                'style' => 'ST-001',
                'initial_quantity' => 3,
            ])
            ->assertSessionHasErrors('test_package_id');
    }

    public function test_only_requestors_can_open_requestor_routes(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CS]))
            ->get(route('requestor.requests.index'))
            ->assertForbidden();
    }
}
