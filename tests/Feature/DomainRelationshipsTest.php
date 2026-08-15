<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CustomerTrustLevel;
use App\Enums\DriverApprovalStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverBusiness;
use App\Models\User;
use Illuminate\Database\QueryException;

test('user can have a customer profile', function () {
    $user = User::factory()->customer()->create();

    $customer = Customer::factory()->forUser($user)->create([
        'trust_level' => CustomerTrustLevel::New,
    ]);

    expect($user->fresh()->customer)->not->toBeNull()
        ->and($customer->user->is($user))->toBeTrue();
});

test('user can have a driver profile', function () {
    $user = User::factory()->driver()->create();
    $admin = User::factory()->systemAdmin()->create();

    $driver = Driver::factory()->forUser($user)->approved($admin)->create();

    expect($user->fresh()->driver)->not->toBeNull()
        ->and($driver->approval_status)->toBe(DriverApprovalStatus::Approved)
        ->and($driver->user->is($user))->toBeTrue();
});

test('business has branches', function () {
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    expect($business->branches)->toHaveCount(1)
        ->and($branch->business->is($business))->toBeTrue();
});

test('business admin belongs to business', function () {
    $user = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    expect($user->fresh()->businessMemberships)->toHaveCount(1)
        ->and($business->users()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('business employee belongs to business', function () {
    $user = User::factory()->businessEmployee()->create();
    $business = Business::factory()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    expect($business->memberships()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('driver can belong to business', function () {
    $driver = Driver::factory()->approved()->create();
    $business = Business::factory()->create();

    $driver->businesses()->attach($business->id);

    expect($driver->fresh()->businesses)->toHaveCount(1)
        ->and($business->fresh()->drivers)->toHaveCount(1);
});

test('same user cannot be duplicated in same business', function () {
    $user = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ]);

    expect(fn () => BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]))->toThrow(QueryException::class);
});

test('same driver cannot be duplicated in same business', function () {
    $driver = Driver::factory()->create();
    $business = Business::factory()->create();

    DriverBusiness::query()->create([
        'driver_id' => $driver->id,
        'business_id' => $business->id,
    ]);

    expect(fn () => DriverBusiness::query()->create([
        'driver_id' => $driver->id,
        'business_id' => $business->id,
    ]))->toThrow(QueryException::class);
});
