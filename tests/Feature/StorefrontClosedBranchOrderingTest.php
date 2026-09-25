<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Support\BusinessHours;

test('closed partner restaurant still allows browsing the cart on the menu', function () {
    $business = Business::factory()->create([
        'name' => 'Taquería Cerrada',
        'slug' => 'taqueria-cerrada',
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    BusinessBranch::factory()->for($business)->create([
        'opening_hours' => collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->all(),
    ]);

    $this->get(route('restaurants.show', 'taqueria-cerrada'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('restaurant.open', false)
            ->where('restaurant.canOrder', true)
            ->where('restaurant.modeLabel', 'Cerrado ahora'));
});

test('branch ordering status reports closed hours for checkout gates', function () {
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'opening_hours' => collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->all(),
    ]);

    $this->getJson(route('cart.branches.ordering-status', $branch))
        ->assertOk()
        ->assertJsonPath('open', false)
        ->assertJson(fn ($json) => $json
            ->whereType('closed_message', 'string')
            ->etc());
});

test('directory businesses cannot order even when marked closed', function () {
    $business = Business::factory()->create([
        'slug' => 'solo-directorio',
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Directory,
    ]);
    BusinessBranch::factory()->for($business)->create([
        'opening_hours' => collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->all(),
    ]);

    $this->get(route('restaurants.show', 'solo-directorio'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('restaurant.open', false)
            ->where('restaurant.canOrder', false));
});
