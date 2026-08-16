<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Promotion;
use App\Support\BusinessHours;
use App\Support\BusinessTypes;

test('home filters restaurants by business type category', function () {
    $tacos = Business::factory()->create([
        'name' => 'Tacos El Norte',
        'slug' => 'tacos-el-norte',
        'business_type' => 'Comida rápida',
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($tacos)->create();

    $cafe = Business::factory()->create([
        'name' => 'Café Central',
        'slug' => 'cafe-central',
        'business_type' => 'Cafetería',
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($cafe)->create();

    $this->get(route('home', ['category' => 'comida-rapida']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->where('filters.category', 'comida-rapida')
            ->has('restaurants', 1)
            ->where('restaurants.0.slug', 'tacos-el-norte')
            ->where('storefront.categories.0.name', BusinessTypes::options()[0]));
});

test('home marks restaurants closed outside opening hours', function () {
    $business = Business::factory()->create([
        'name' => 'Cerrado Demo',
        'slug' => 'cerrado-demo',
        'business_type' => 'Restaurante',
        'status' => BusinessStatus::Active,
        'opening_hours' => collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->all(),
    ]);
    BusinessBranch::factory()->for($business)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->where('restaurants.0.slug', 'cerrado-demo')
            ->where('restaurants.0.open', false)
            ->where('restaurants.0.canOrder', false)
            ->where('restaurants.0.modeLabel', 'Cerrado ahora'));
});

test('home without category shows the default restaurant list', function () {
    $business = Business::factory()->create([
        'business_type' => 'Restaurante',
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($business)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->where('filters.category', null)
            ->has('storefront.categories', count(BusinessTypes::options())));
});

test('home carousel only includes affiliated partner businesses with banner', function () {
    $partnerWithBanner = Business::factory()->create([
        'name' => 'Partner Grill',
        'slug' => 'partner-grill',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'banner_path' => 'businesses/banners/partner-grill.jpg',
    ]);
    BusinessBranch::factory()->for($partnerWithBanner)->create();

    $partnerWithoutBanner = Business::factory()->create([
        'name' => 'Partner Sin Banner',
        'slug' => 'partner-sin-banner',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
        'banner_path' => null,
    ]);
    BusinessBranch::factory()->for($partnerWithoutBanner)->create();

    $platform = Business::factory()->create([
        'name' => 'Ride Kitchen',
        'slug' => 'ride-kitchen',
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
        'banner_path' => 'businesses/banners/platform.jpg',
    ]);
    BusinessBranch::factory()->for($platform)->create();

    $directory = Business::factory()->create([
        'name' => 'Directory Cafe',
        'slug' => 'directory-cafe',
        'operation_mode' => BusinessOperationMode::Directory,
        'status' => BusinessStatus::Active,
        'banner_path' => 'businesses/banners/directory.jpg',
    ]);
    BusinessBranch::factory()->for($directory)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->has('affiliatedPartners', 1)
            ->where('affiliatedPartners.0.slug', 'partner-grill')
            ->where('affiliatedPartners.0.banner_url', fn ($url) => is_string($url) && str_contains($url, 'partner-grill.jpg')));
});

test('home promotions list affiliated businesses before others', function () {
    $partner = Business::factory()->create([
        'name' => 'Partner Grill',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $partnerBranch = BusinessBranch::factory()->for($partner)->create();

    $platform = Business::factory()->create([
        'name' => 'Ride Kitchen',
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
    ]);
    $platformBranch = BusinessBranch::factory()->for($platform)->create();

    $nonAffiliatePromo = Promotion::factory()->create([
        'branch_id' => $platformBranch->id,
        'name' => 'Promo no afiliada',
        'status' => PromotionStatus::Active,
        'created_at' => now()->subMinute(),
    ]);

    $affiliatePromo = Promotion::factory()->create([
        'branch_id' => $partnerBranch->id,
        'name' => 'Promo afiliada',
        'status' => PromotionStatus::Active,
        'created_at' => now()->subHour(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->has('promotions', 2)
            ->where('promotions.0.id', (string) $affiliatePromo->id)
            ->where('promotions.0.is_affiliated', true)
            ->where('promotions.1.id', (string) $nonAffiliatePromo->id)
            ->where('promotions.1.is_affiliated', false));
});

test('home restaurants list affiliated businesses before others', function () {
    $platform = Business::factory()->create([
        'name' => 'AAA Platform',
        'slug' => 'aaa-platform',
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($platform)->create();

    $partner = Business::factory()->create([
        'name' => 'ZZZ Partner',
        'slug' => 'zzz-partner',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($partner)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->has('restaurants', 2)
            ->where('restaurants.0.slug', 'zzz-partner')
            ->where('restaurants.0.is_affiliated', true)
            ->where('restaurants.1.slug', 'aaa-platform')
            ->where('restaurants.1.is_affiliated', false));
});

test('restaurants index lists affiliated businesses before others', function () {
    $platform = Business::factory()->create([
        'name' => 'AAA Platform',
        'slug' => 'aaa-platform-index',
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($platform)->create();

    $partner = Business::factory()->create([
        'name' => 'ZZZ Partner',
        'slug' => 'zzz-partner-index',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($partner)->create();

    $this->get(route('restaurants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/index')
            ->has('restaurants.data', 2)
            ->where('restaurants.data.0.slug', 'zzz-partner-index')
            ->where('restaurants.data.0.is_affiliated', true)
            ->where('restaurants.data.1.slug', 'aaa-platform-index')
            ->where('restaurants.data.1.is_affiliated', false));
});
