<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;

function businessAdminContext(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    $business->limits()->update([
        'max_branches' => 1,
        'max_business_admins' => 1,
        'max_employees_per_branch' => 3,
    ]);

    return compact('admin', 'business', 'branch');
}

test('business admin cannot create branch', function () {
    ['admin' => $admin, 'business' => $business] = businessAdminContext();

    expect($admin->can('create', [BusinessBranch::class, $business]))->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Nueva',
            'phone' => null,
            'address_text' => 'Calle 1',
            'reference' => null,
            'latitude' => '14.6',
            'longitude' => '-90.5',
            'google_maps_url' => null,
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('business admin can create employee while branch has capacity', function () {
    ['admin' => $admin, 'branch' => $branch] = businessAdminContext();

    $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Emp',
            'last_name' => 'Uno',
            'email' => 'emp.uno@ride.test',
            'phone' => '+50255558001',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();
});

test('business admin cannot exceed employee limit', function () {
    ['admin' => $admin, 'business' => $business, 'branch' => $branch] = businessAdminContext();

    foreach (range(1, 3) as $i) {
        $user = User::factory()->businessEmployee()->create([
            'email' => "fill{$i}@ride.test",
            'phone' => '+5025555810'.$i,
        ]);
        $membership = BusinessUser::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => BusinessUserRole::BusinessEmployee,
            'status' => BusinessUserStatus::Active,
        ]);
        $membership->branches()->sync([$branch->id]);
    }

    $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Emp',
            'last_name' => 'Cuatro',
            'email' => 'emp.cuatro@ride.test',
            'phone' => '+50255558004',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branch->id],
        ])
        ->assertSessionHasErrors('branch_ids');
});

test('business admin cannot exceed business admin limit', function () {
    ['admin' => $admin] = businessAdminContext();

    $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Otro',
            'last_name' => 'Admin',
            'email' => 'otro.admin@ride.test',
            'phone' => '+50255558005',
            'role' => BusinessUserRole::BusinessAdmin->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [],
        ])
        ->assertSessionHasErrors('role');
});

test('employee assigned to multiple branches counts against each branch', function () {
    ['admin' => $admin, 'business' => $business, 'branch' => $branchA] = businessAdminContext();
    $business->limits()->update(['max_branches' => 2, 'max_employees_per_branch' => 1]);
    $branchB = BusinessBranch::factory()->for($business)->create();

    $user = User::factory()->businessEmployee()->create([
        'email' => 'multi@ride.test',
        'phone' => '+50255558006',
    ]);
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$branchA->id, $branchB->id]);

    $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Extra',
            'last_name' => 'Centro',
            'email' => 'extra.centro@ride.test',
            'phone' => '+50255558007',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchA->id],
        ])
        ->assertSessionHasErrors('branch_ids');
});

test('inactive employee does not consume active employee capacity', function () {
    ['admin' => $admin, 'business' => $business, 'branch' => $branch] = businessAdminContext();
    $business->limits()->update(['max_employees_per_branch' => 1]);

    $inactive = User::factory()->businessEmployee()->create([
        'email' => 'inactive@ride.test',
        'phone' => '+50255558008',
    ]);
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $inactive->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Inactive,
    ]);
    $membership->branches()->sync([$branch->id]);

    $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Activo',
            'last_name' => 'Nuevo',
            'email' => 'activo.nuevo@ride.test',
            'phone' => '+50255558009',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();
});

test('business can submit upgrade request', function () {
    ['admin' => $admin, 'branch' => $branch] = businessAdminContext();

    $this->actingAs($admin)
        ->post(route('business.upgrade-requests.store'), [
            'type' => UpgradeRequestType::AdditionalEmployees->value,
            'requested_quantity' => 2,
            'branch_id' => $branch->id,
            'notes' => 'Necesitamos más personal',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('business_upgrade_requests', [
        'type' => UpgradeRequestType::AdditionalEmployees->value,
        'status' => UpgradeRequestStatus::Pending->value,
        'branch_id' => $branch->id,
    ]);
});
