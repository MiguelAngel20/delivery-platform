<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;

test('business user can only belong to one company', function () {
    $user = User::factory()->businessEmployee()->create();

    $firstBusiness = Business::factory()->create(['name' => 'Primera']);
    $firstBranch = BusinessBranch::factory()->for($firstBusiness)->create();

    BusinessUser::query()->create([
        'business_id' => $firstBusiness->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$firstBranch->id]);

    $secondBusiness = Business::factory()->create(['name' => 'Segunda']);
    $secondBranch = BusinessBranch::factory()->for($secondBusiness)->create();
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $secondBusiness), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$secondBranch->id],
        ])
        ->assertSessionHasErrors('email');

    expect(BusinessUser::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('business user can only be assigned to one branch', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create();
    $branchA = BusinessBranch::factory()->for($business)->create();
    $branchB = BusinessBranch::factory()->for($business)->create();

    $this->actingAs($admin)
        ->post(route('admin.businesses.users.store', $business), [
            'first_name' => 'Una',
            'last_name' => 'Sucursal',
            'email' => 'una.sucursal@ride.test',
            'phone' => '+50255559901',
            'role' => BusinessUserRole::BusinessEmployee->value,
            'status' => BusinessUserStatus::Active->value,
            'branch_ids' => [$branchA->id, $branchB->id],
        ])
        ->assertSessionHasErrors('branch_ids');
});

test('user active business membership resolves to their only company', function () {
    $user = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create(['name' => 'Pollo robins']);
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    expect($user->fresh()->activeBusinessMembership()?->business->name)->toBe('Pollo robins');
});
