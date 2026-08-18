<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\User;

function attachBusinessMembership(User $user, BusinessUserRole $role): void
{
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);
}

test('business admin can open all business pages', function (string $routeName) {
    $user = User::factory()->businessAdmin()->create();
    attachBusinessMembership($user, BusinessUserRole::BusinessAdmin);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'home' => 'business.home',
    'orders' => 'business.orders.index',
    'products' => 'business.products.index',
    'categories' => 'business.categories.index',
    'promotions' => 'business.promotions.index',
    'employees' => 'business.employees.index',
    'upgrade-requests' => 'business.upgrade-requests.index',
    'settings' => 'business.settings.index',
]);

test('business employee can open operational pages', function (string $routeName) {
    $user = User::factory()->businessEmployee()->create();
    attachBusinessMembership($user, BusinessUserRole::BusinessEmployee);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'home' => 'business.home',
    'orders' => 'business.orders.index',
]);

test('business employee cannot open admin-only business pages', function (string $routeName) {
    $user = User::factory()->businessEmployee()->create();
    attachBusinessMembership($user, BusinessUserRole::BusinessEmployee);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'products' => 'business.products.index',
    'categories' => 'business.categories.index',
    'promotions' => 'business.promotions.index',
    'employees' => 'business.employees.index',
    'upgrade-requests' => 'business.upgrade-requests.index',
    'settings' => 'business.settings.index',
]);
