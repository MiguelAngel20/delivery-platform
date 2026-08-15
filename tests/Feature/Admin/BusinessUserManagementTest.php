<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;

test('system admin can list users of any business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $employee = User::factory()->businessEmployee()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.businesses.users.index', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/users/index')
            ->has('users', 1));
});

test('system admin can create business employee', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $business), [
            'first_name' => 'Ana',
            'last_name' => 'Empleada',
            'email' => 'ana.empleado@ride.test',
            'phone' => '+50255557701',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branch->id],
        ]);

    $membership = BusinessUser::query()
        ->where('business_id', $business->id)
        ->whereHas('user', fn ($query) => $query->where('email', 'ana.empleado@ride.test'))
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership?->role)->toBe(BusinessUserRole::BusinessEmployee)
        ->and($membership?->branches()->pluck('business_branches.id')->all())->toBe([$branch->id]);

    $response->assertRedirect(route('admin.businesses.users.edit', [$business, $membership]));
});

test('system admin can create business admin', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $business), [
            'first_name' => 'Luis',
            'last_name' => 'Admin',
            'email' => 'luis.admin@ride.test',
            'phone' => '+50255557702',
            'role' => BusinessUserRole::BusinessAdmin->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [],
        ])
        ->assertRedirect();

    $membership = BusinessUser::query()
        ->where('business_id', $business->id)
        ->whereHas('user', fn ($query) => $query->where('email', 'luis.admin@ride.test'))
        ->first();

    expect($membership?->role)->toBe(BusinessUserRole::BusinessAdmin)
        ->and($membership?->branches)->toHaveCount(0);
});

test('system admin can assign employee to multiple branches', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();
    $employee = User::factory()->businessEmployee()->create();

    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.businesses.users.update', [$business, $membership]), [
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

test('system admin can change employee branches', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();
    $employee = User::factory()->businessEmployee()->create();

    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$branchA->id]);

    $this->actingAs($admin)
        ->put(route('admin.businesses.users.update', [$business, $membership]), [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchB->id],
        ])
        ->assertRedirect();

    expect($membership->fresh()->branches->pluck('id')->all())->toBe([$branchB->id]);
});

test('system admin can deactivate membership', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $employee = User::factory()->businessEmployee()->create();

    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.deactivate', [$business, $membership]))
        ->assertRedirect();

    expect($membership->fresh()->status)->toBe(BusinessUserStatus::Inactive)
        ->and(User::query()->find($employee->id))->not->toBeNull();
});

test('existing user is reused instead of duplicated', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();
    $existing = User::factory()->businessEmployee()->create([
        'email' => 'reutilizado@ride.test',
        'phone' => '+50255557703',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $business), [
            'first_name' => 'Re',
            'last_name' => 'Utilizado',
            'email' => 'reutilizado@ride.test',
            'phone' => '+50255557703',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect();

    expect(User::query()->where('email', 'reutilizado@ride.test')->count())->toBe(1)
        ->and(BusinessUser::query()->where('user_id', $existing->id)->where('business_id', $business->id)->exists())->toBeTrue();
});

test('employee cannot be assigned to branch belonging to another business', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $foreignBranch = BusinessBranch::factory()->for($otherBusiness)->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $business), [
            'first_name' => 'Hack',
            'last_name' => 'Branch',
            'email' => 'hack.branch@ride.test',
            'phone' => '+50255557704',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$foreignBranch->id],
        ])
        ->assertSessionHasErrors('branch_ids.0');
});
