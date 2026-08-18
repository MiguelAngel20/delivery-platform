<?php

use App\Enums\BranchStatus;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\BusinessHours;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('system admin can list businesses', function () {
    $admin = User::factory()->systemAdmin()->create();
    Business::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.businesses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/index')
            ->has('businesses.data', 2));
});

test('system admin can view a business with branch hour options', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    BusinessBranch::factory()->for($business)->create();

    $this->actingAs($admin)
        ->get(route('admin.businesses.show', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/show')
            ->has('options.weekdays', 7)
            ->has('options.default_opening_hours', 7)
            ->has('business.branches', 1)
            ->has('business.branches.0.opening_hours'));
});

test('system admin can create business', function () {
    $admin = User::factory()->systemAdmin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.businesses.store'), [
            'name' => 'Pollo Güero',
            'description' => 'Empresa de prueba',
            'business_type' => 'Restaurante',
            'operation_mode' => BusinessOperationMode::Partner->value,
            'delivery_mode' => BusinessDeliveryMode::Hybrid->value,
            'phone' => '+50255551212',
            'email' => 'hola@polloguero.test',
            'status' => BusinessStatus::PendingApproval->value,
            'logo' => UploadedFile::fake()->image('logo.jpg'),
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ]);

    $business = Business::query()->where('email', 'hola@polloguero.test')->first();

    expect($business)->not->toBeNull()
        ->and($business?->slug)->toStartWith('pollo-guero')
        ->and($business?->logo_path)->not->toBeNull()
        ->and($business?->banner_path)->not->toBeNull();

    $response->assertRedirect(route('admin.businesses.show', $business));
});

test('system admin can update business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'name' => 'Empresa Original',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.businesses.update', $business), [
            'name' => 'Empresa Actualizada',
            'description' => 'Nueva descripción',
            'business_type' => 'Cafetería',
            'operation_mode' => BusinessOperationMode::Directory->value,
            'delivery_mode' => BusinessDeliveryMode::None->value,
            'phone' => '+50255559999',
            'email' => 'nuevo@empresa.test',
            'status' => BusinessStatus::Active->value,
        ])
        ->assertRedirect(route('admin.businesses.show', $business));

    $business->refresh();

    expect($business->name)->toBe('Empresa Actualizada')
        ->and($business->business_type)->toBe('Cafetería');
});

test('system admin can update business via multipart form post spoofing put', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'name' => 'Empresa Original',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.update', [
            'business' => $business,
            '_method' => 'PUT',
        ]), [
            'name' => 'Empresa Multipart',
            'description' => 'Actualizada con form multipart',
            'business_type' => 'Restaurante',
            'operation_mode' => BusinessOperationMode::Partner->value,
            'delivery_mode' => BusinessDeliveryMode::PlatformDrivers->value,
            'phone' => '+50255551111',
            'email' => 'multipart@empresa.test',
            'status' => BusinessStatus::Active->value,
        ])
        ->assertRedirect(route('admin.businesses.show', $business));

    expect($business->fresh()->name)->toBe('Empresa Multipart')
        ->and($business->fresh()->email)->toBe('multipart@empresa.test');
});

test('system admin can approve pending business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->pending()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.approve', $business))
        ->assertRedirect();

    $business->refresh();

    expect($business->status)->toBe(BusinessStatus::Active)
        ->and($business->approved_by_user_id)->toBe($admin->id)
        ->and($business->approved_at)->not->toBeNull();
});

test('system admin can suspend business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.suspend', $business), [
            'reason' => 'Incumplimiento de políticas del portal.',
        ])
        ->assertRedirect();

    expect($business->fresh()->status)->toBe(BusinessStatus::Suspended)
        ->and($business->fresh()->suspension_reason)->toContain('Incumplimiento');
});

test('system admin can create branch', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Sucursal Centro',
            'phone' => '+50255551111',
            'address_text' => 'Zona 1, Ciudad',
            'reference' => 'Frente al parque',
            'latitude' => '14.6349000',
            'longitude' => '-90.5069000',
            'google_maps_url' => 'https://maps.google.com/?q=14.6349,-90.5069',
            'status' => BranchStatus::Active->value,
            'opening_hours' => BusinessHours::defaults(),
        ])
        ->assertRedirect();

    $branch = $business->branches()->where('name', 'Sucursal Centro')->first();

    expect($branch)->not->toBeNull()
        ->and($branch?->opening_hours)->toHaveCount(7)
        ->and(collect($branch?->opening_hours)->firstWhere('day', 'monday')['is_open'] ?? false)->toBeTrue();
});

test('system admin can update branch', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Sucursal Vieja',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.businesses.branches.update', [$business, $branch]), [
            'name' => 'Sucursal Nueva',
            'phone' => '+50255552222',
            'address_text' => 'Zona 10',
            'reference' => null,
            'latitude' => '14.6000000',
            'longitude' => '-90.5000000',
            'google_maps_url' => null,
            'status' => BranchStatus::Active->value,
            'opening_hours' => collect(BusinessHours::defaults())
                ->map(function (array $row): array {
                    if ($row['day'] === 'saturday') {
                        return [
                            'day' => 'saturday',
                            'is_open' => true,
                            'opens_at' => '10:00',
                            'closes_at' => '14:00',
                        ];
                    }

                    return $row;
                })
                ->all(),
        ])
        ->assertRedirect();

    $branch->refresh();

    expect($branch->name)->toBe('Sucursal Nueva')
        ->and(collect($branch->opening_hours)->firstWhere('day', 'saturday'))
        ->toMatchArray([
            'day' => 'saturday',
            'is_open' => true,
            'opens_at' => '10:00',
            'closes_at' => '14:00',
        ]);
});

test('driver cannot access admin businesses', function () {
    $driver = User::factory()->driver()->create();

    $this->actingAs($driver)
        ->get(route('admin.businesses.index'))
        ->assertForbidden();
});

test('customer cannot access admin businesses', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.businesses.index'))
        ->assertForbidden();
});

test('business employee cannot modify another business', function () {
    $employee = User::factory()->businessEmployee()->create();
    $ownBusiness = Business::factory()->create();
    $otherBusiness = Business::factory()->create();

    BusinessUser::query()->create([
        'business_id' => $ownBusiness->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    expect($employee->can('update', $otherBusiness))->toBeFalse()
        ->and($employee->can('update', $ownBusiness))->toBeFalse();
});

test('business slug is unique', function () {
    Business::factory()->create([
        'name' => 'Pollo Guero',
        'slug' => 'pollo-guero',
    ]);

    $slug = UniqueSlug::forBusiness('Pollo Güero');

    expect($slug)->toBe('pollo-guero-2');
});

test('branch belongs to business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($otherBusiness)->create();

    $this->actingAs($admin)
        ->put(route('admin.businesses.branches.update', [$business, $branch]), [
            'name' => 'Hack',
            'phone' => null,
            'address_text' => 'X',
            'reference' => null,
            'latitude' => '14.6',
            'longitude' => '-90.5',
            'google_maps_url' => null,
            'status' => BranchStatus::Active->value,
        ])
        ->assertNotFound();
});

test('business portal receives real business context', function () {
    $user = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create(['name' => 'Empresa Demo']);
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Sucursal Centro',
    ]);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $this->actingAs($user)
        ->get(route('business.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('businessContext.business.name', 'Empresa Demo')
            ->where('businessContext.current_branch_id', $branch->id)
            ->has('businessContext.branches', 1));
});
