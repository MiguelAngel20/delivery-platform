<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUpgradeRequest;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\BusinessHours;

test('system admin can create branch within limit', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $business->limits()->update(['max_branches' => 2]);
    BusinessBranch::factory()->for($business)->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Sucursal Norte',
            'phone' => null,
            'address_text' => 'Zona 10',
            'reference' => null,
            'latitude' => '14.6',
            'longitude' => '-90.5',
            'google_maps_url' => null,
            'status' => 'active',
            'opening_hours' => BusinessHours::defaults(),
        ])
        ->assertRedirect();

    expect($business->branches()->count())->toBe(2);
});

test('system admin cannot create branch beyond limit', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $business->limits()->update(['max_branches' => 1]);
    BusinessBranch::factory()->for($business)->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Extra',
            'phone' => null,
            'address_text' => 'Zona 1',
            'reference' => null,
            'latitude' => '14.6',
            'longitude' => '-90.5',
            'google_maps_url' => null,
            'status' => 'active',
            'opening_hours' => BusinessHours::defaults(),
        ])
        ->assertSessionHasErrors('name');
});

test('system admin can update business limits', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.businesses.limits.update', $business), [
            'max_branches' => 3,
            'max_business_admins' => 2,
            'max_employees_per_branch' => 5,
        ])
        ->assertRedirect();

    expect($business->fresh()->limits->max_branches)->toBe(3)
        ->and($business->fresh()->limits->max_business_admins)->toBe(2)
        ->and($business->fresh()->limits->max_employees_per_branch)->toBe(5);
});

test('system admin can approve upgrade request', function () {
    $admin = User::factory()->systemAdmin()->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();
    $business->limits()->update(['max_employees_per_branch' => 3]);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $request = BusinessUpgradeRequest::query()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $businessAdmin->id,
        'type' => UpgradeRequestType::AdditionalEmployees,
        'requested_quantity' => 2,
        'status' => UpgradeRequestStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.upgrade-requests.approve', [$business, $request]), [
            'apply_limit_increase' => true,
            'quantity' => 2,
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(UpgradeRequestStatus::Approved)
        ->and($business->fresh()->limits->max_employees_per_branch)->toBe(5);
});

test('system admin can reject upgrade request', function () {
    $admin = User::factory()->systemAdmin()->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();

    $request = BusinessUpgradeRequest::query()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $businessAdmin->id,
        'type' => UpgradeRequestType::AdditionalBranch,
        'requested_quantity' => 1,
        'status' => UpgradeRequestStatus::Pending,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.upgrade-requests.reject', [$business, $request]), [
            'notes' => 'Sin cobertura por ahora',
        ])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(UpgradeRequestStatus::Rejected);
});
