<?php

use App\Enums\BusinessTypeStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use App\Support\BusinessTypes;

test('system admin can list business types', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.business-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/business-types/index')
            ->has('types')
            ->has('options.statuses', 2));
});

test('system admin can create a business type', function () {
    $admin = User::factory()->systemAdmin()->create();
    BusinessTypes::forgetCache();

    $this->actingAs($admin)
        ->post(route('admin.business-types.store'), [
            'name' => 'Panadería',
            'description' => 'Pan y pasteles',
            'status' => BusinessTypeStatus::Active->value,
        ])
        ->assertRedirect();

    $type = BusinessType::query()->where('name', 'Panadería')->first();

    expect($type)->not->toBeNull()
        ->and($type->slug)->toBe('panaderia')
        ->and($type->description)->toBe('Pan y pasteles')
        ->and($type->status)->toBe(BusinessTypeStatus::Active)
        ->and(BusinessTypes::options())->toContain('Panadería');
});

test('system admin can update a business type and sync business labels', function () {
    $admin = User::factory()->systemAdmin()->create();
    $type = BusinessType::query()->where('name', 'Restaurante')->firstOrFail();
    $business = Business::factory()->create(['business_type' => 'Restaurante']);

    $this->actingAs($admin)
        ->put(route('admin.business-types.update', $type), [
            'name' => 'Restaurante gourmet',
            'description' => 'Comida de mesa',
            'status' => BusinessTypeStatus::Active->value,
        ])
        ->assertRedirect();

    expect($type->fresh()->name)->toBe('Restaurante gourmet')
        ->and($business->fresh()->business_type)->toBe('Restaurante gourmet');
});

test('inactive business types are excluded from options', function () {
    $type = BusinessType::factory()->inactive()->create([
        'name' => 'Temporal',
        'slug' => 'temporal',
    ]);
    BusinessTypes::forgetCache();

    expect(BusinessTypes::options())->not->toContain($type->name)
        ->and(BusinessTypes::findBySlug($type->slug))->toBeNull();
});

test('system admin can delete unused business type', function () {
    $admin = User::factory()->systemAdmin()->create();
    $type = BusinessType::factory()->create([
        'name' => 'Sin uso',
        'slug' => 'sin-uso',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.business-types.destroy', $type))
        ->assertRedirect();

    expect(BusinessType::query()->whereKey($type->id)->exists())->toBeFalse();
});

test('system admin cannot delete business type in use', function () {
    $admin = User::factory()->systemAdmin()->create();
    $type = BusinessType::query()->where('name', 'Restaurante')->firstOrFail();
    Business::factory()->create(['business_type' => 'Restaurante']);

    $this->actingAs($admin)
        ->delete(route('admin.business-types.destroy', $type))
        ->assertRedirect();

    expect(BusinessType::query()->whereKey($type->id)->exists())->toBeTrue();
});

test('non admin cannot manage business types', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('admin.business-types.index'))
        ->assertForbidden();
});
