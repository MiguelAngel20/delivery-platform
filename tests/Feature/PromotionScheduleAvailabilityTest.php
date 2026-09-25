<?php

use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Support\BusinessHours;
use App\Support\PromotionSchedule;
use Illuminate\Support\Carbon;

/**
 * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
 */
function promotionHoursForDays(array $openDays, string $opensAt = '08:00', string $closesAt = '20:00'): array
{
    return collect(BusinessHours::dayKeys())
        ->map(fn (string $day): array => [
            'day' => $day,
            'is_open' => in_array($day, $openDays, true),
            'opens_at' => in_array($day, $openDays, true) ? $opensAt : null,
            'closes_at' => in_array($day, $openDays, true) ? $closesAt : null,
        ])
        ->values()
        ->all();
}

test('storefront hides promotions before start and after end', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

    $branch = BusinessBranch::factory()->create();

    $upcoming = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Upcoming promo',
        'starts_at' => Carbon::parse('2026-09-25 14:00:00'),
        'ends_at' => Carbon::parse('2026-09-25 20:00:00'),
    ]);
    PromotionItem::factory()->create(['promotion_id' => $upcoming->id]);

    $live = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Live promo',
        'starts_at' => Carbon::parse('2026-09-25 08:00:00'),
        'ends_at' => Carbon::parse('2026-09-25 20:00:00'),
    ]);
    PromotionItem::factory()->create(['promotion_id' => $live->id]);

    $ended = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Ended promo',
        'starts_at' => Carbon::parse('2026-09-25 08:00:00'),
        'ends_at' => Carbon::parse('2026-09-25 11:00:00'),
    ]);
    PromotionItem::factory()->create(['promotion_id' => $ended->id]);

    $openEnded = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Until deactivated',
        'starts_at' => Carbon::parse('2026-09-25 08:00:00'),
        'ends_at' => null,
    ]);
    PromotionItem::factory()->create(['promotion_id' => $openEnded->id]);

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 2)
            ->where(
                'promotions',
                fn ($promotions): bool => collect($promotions)
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all() === ['Live promo', 'Until deactivated'],
            ));

    Carbon::setTestNow();
});

test('inactive promotions stay hidden even inside schedule window', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

    $branch = BusinessBranch::factory()->create();

    Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Paused,
        'starts_at' => Carbon::parse('2026-09-25 08:00:00'),
        'ends_at' => Carbon::parse('2026-09-25 20:00:00'),
    ]);

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 0));

    Carbon::setTestNow();
});

test('cart promotion endpoint rejects unavailable promotions', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

    $branch = BusinessBranch::factory()->create();

    $upcoming = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(4),
    ]);

    $ended = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'starts_at' => now()->subHours(4),
        'ends_at' => now()->subHour(),
    ]);

    $inactive = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Paused,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->getJson(route('cart.promotions.show', $upcoming))->assertNotFound();
    $this->getJson(route('cart.promotions.show', $ended))->assertNotFound();
    $this->getJson(route('cart.promotions.show', $inactive))->assertNotFound();

    Carbon::setTestNow();
});

test('promotions availability endpoint returns only currently orderable ids', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

    $branch = BusinessBranch::factory()->create();

    $live = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $ended = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->subMinute(),
    ]);

    $inactive = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Paused,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->postJson(route('cart.promotions.availability'), [
        'ids' => [$live->id, $ended->id, $inactive->id],
    ])
        ->assertOk()
        ->assertJsonPath('available_ids', [$live->id]);

    Carbon::setTestNow();
});

test('recurring promotion shows only on enabled weekday inside hours', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', $tz));

    $branch = BusinessBranch::factory()->create();

    $mondayPromo = Promotion::factory()->recurring(
        Carbon::parse('2026-09-01'),
        null,
        promotionHoursForDays(['monday'], '08:00', '20:00'),
    )->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Monday only',
    ]);
    PromotionItem::factory()->create(['promotion_id' => $mondayPromo->id]);

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 1)
            ->where('promotions.0.name', 'Monday only'));

    Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', $tz));

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 0));

    Carbon::setTestNow(Carbon::parse('2026-09-21 21:30:00', $tz));

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 0));

    Carbon::setTestNow();
});

test('recurring promotion hides after recurrence end date', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', $tz));

    $branch = BusinessBranch::factory()->create();

    $promo = Promotion::factory()->recurring(
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-15'),
        promotionHoursForDays(['monday'], '08:00', '20:00'),
    )->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'name' => 'Ended recurrence',
    ]);
    PromotionItem::factory()->create(['promotion_id' => $promo->id]);

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 0));

    Carbon::setTestNow();
});

test('availability endpoint respects recurring weekly hours', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', $tz));

    $branch = BusinessBranch::factory()->create();

    $live = Promotion::factory()->recurring(
        Carbon::parse('2026-09-01'),
        null,
        promotionHoursForDays(['monday'], '08:00', '20:00'),
    )->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
    ]);

    $wrongDay = Promotion::factory()->recurring(
        Carbon::parse('2026-09-01'),
        null,
        promotionHoursForDays(['friday'], '08:00', '20:00'),
    )->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
    ]);

    $this->postJson(route('cart.promotions.availability'), [
        'ids' => [$live->id, $wrongDay->id],
    ])
        ->assertOk()
        ->assertJsonPath('available_ids', [$live->id]);

    Carbon::setTestNow();
});

test('recurring schedule attributes clear one-shot dates', function () {
    $payload = PromotionSchedule::attributesFromValidated([
        'is_recurring' => true,
        'recurrence_starts_on' => '2026-09-01',
        'recurrence_ends_on' => null,
        'recurring_hours' => promotionHoursForDays(['monday', 'friday'], '09:00', '12:00'),
        'starts_at' => '2026-09-01 08:00:00',
        'ends_at' => '2026-09-30 20:00:00',
    ]);

    expect($payload['is_recurring'])->toBeTrue()
        ->and($payload['starts_at'])->toBeNull()
        ->and($payload['ends_at'])->toBeNull()
        ->and($payload['recurrence_starts_on'])->toBe('2026-09-01')
        ->and($payload['recurring_hours'])->toHaveCount(7)
        ->and(collect($payload['recurring_hours'])->where('is_open', true)->pluck('day')->all())
        ->toBe(['monday', 'friday']);
});
