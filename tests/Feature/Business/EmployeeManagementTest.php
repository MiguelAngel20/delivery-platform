<?php

use App\Enums\BranchStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;

function seedBusinessAdminWithBusiness(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($business)->create(['name' => 'Centro']);
    $branchB = BusinessBranch::factory()->for($business)->create(['name' => 'Norte']);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    return compact('admin', 'business', 'branchA', 'branchB');
}

test('business admin can list employees', function () {
    ['admin' => $admin, 'business' => $business] = seedBusinessAdminWithBusiness();

    $employee = User::factory()->businessEmployee()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->get(route('business.employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/employees/index')
            ->has('employees.data', 2));
});

test('business admin can create employee', function () {
    ['admin' => $admin, 'branchA' => $branchA] = seedBusinessAdminWithBusiness();

    $response = $this->actingAs($admin)
        ->post(route('business.employees.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.empleado@ride.test',
            'phone' => '+50255558888',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchA->id],
        ]);

    $membership = BusinessUser::query()
        ->whereHas('user', fn ($query) => $query->where('email', 'juan.empleado@ride.test'))
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership?->role)->toBe(BusinessUserRole::BusinessEmployee)
        ->and($membership?->branches()->pluck('business_branches.id')->all())->toBe([$branchA->id]);

    $response->assertRedirect(route('business.employees.edit', $membership));
});

test('business admin can assign employee to branch', function () {
    ['admin' => $admin, 'business' => $business, 'branchA' => $branchA] = seedBusinessAdminWithBusiness();

    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->put(route('business.employees.update', $membership), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchA->id],
        ])
        ->assertRedirect();

    expect($membership->fresh()->branches)->toHaveCount(1);
});

test('business admin can assign employee to multiple branches', function () {
    ['admin' => $admin, 'business' => $business, 'branchA' => $branchA, 'branchB' => $branchB] = seedBusinessAdminWithBusiness();

    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->put(route('business.employees.update', $membership), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchA->id, $branchB->id],
        ])
        ->assertRedirect();

    expect($membership->fresh()->branches)->toHaveCount(2);
});

test('business admin can deactivate employee', function () {
    ['admin' => $admin, 'business' => $business] = seedBusinessAdminWithBusiness();

    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->post(route('business.employees.deactivate', $membership))
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(BusinessUserStatus::Inactive)
        ->and($employee->fresh())->not->toBeNull();
});

test('business employee cannot manage employees', function () {
    $employee = User::factory()->businessEmployee()->create();
    $business = Business::factory()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($employee)
        ->get(route('business.employees.index'))
        ->assertForbidden();
});

test('admin business a cannot manage employees business b', function () {
    ['admin' => $adminA] = seedBusinessAdminWithBusiness();
    ['business' => $businessB, 'branchA' => $branchB] = seedBusinessAdminWithBusiness();

    $employeeB = User::factory()->businessEmployee()->create();
    $membershipB = BusinessUser::query()->create([
        'business_id' => $businessB->id,
        'user_id' => $employeeB->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($adminA)
        ->put(route('business.employees.update', $membershipB), [
            'first_name' => 'Hack',
            'last_name' => 'Attempt',
            'email' => $employeeB->email,
            'phone' => $employeeB->phone,
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchB->id],
        ])
        ->assertForbidden();
});

test('employee business a cannot access branch business b', function () {
    $employee = User::factory()->businessEmployee()->create();
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($businessA)->create();
    $branchB = BusinessBranch::factory()->for($businessB)->create();

    $membership = BusinessUser::query()->create([
        'business_id' => $businessA->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$branchA->id]);

    expect($employee->canAccessBranch($branchA))->toBeTrue()
        ->and($employee->canAccessBranch($branchB))->toBeFalse();
});

test('business user belongs to business and user and can belong to many branches', function () {
    $user = User::factory()->businessEmployee()->create();
    $business = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();

    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$branchA->id, $branchB->id]);

    expect($membership->business->is($business))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->branches)->toHaveCount(2)
        ->and($branchA->businessUsers()->where('business_users.id', $membership->id)->exists())->toBeTrue();
});

test('business employee context only includes assigned branches', function () {
    $employee = User::factory()->businessEmployee()->create();
    $business = Business::factory()->create();
    $assigned = BusinessBranch::factory()->for($business)->create([
        'name' => 'Asignada',
        'status' => BranchStatus::Active,
    ]);
    BusinessBranch::factory()->for($business)->create([
        'name' => 'No asignada',
        'status' => BranchStatus::Active,
    ]);

    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$assigned->id]);

    $this->actingAs($employee)
        ->get(route('business.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('businessContext.branches', 1)
            ->where('businessContext.branches.0.id', $assigned->id)
            ->where('businessContext.membership_role', BusinessUserRole::BusinessEmployee->value));
});

test('business admin context includes all branches', function () {
    ['admin' => $admin, 'branchA' => $branchA, 'branchB' => $branchB] = seedBusinessAdminWithBusiness();

    $this->actingAs($admin)
        ->get(route('business.home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('businessContext.branches', 2)
            ->where('businessContext.membership_role', UserRole::BusinessAdmin->value));
});
