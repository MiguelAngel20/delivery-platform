<?php

use App\Models\PushDevice;
use App\Models\User;
use App\Services\Push\DeviceRegistrationService;

test('authenticated user can register device', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->postJson('/push/devices', [
            'token' => 'fcm-token-abc-123',
            'device_type' => 'web',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'device_name' => 'PC',
        ])
        ->assertCreated()
        ->assertJsonPath('token', 'fcm-token-abc-123')
        ->assertJsonPath('is_active', true);

    $this->assertDatabaseHas('push_devices', [
        'user_id' => $user->id,
        'token' => 'fcm-token-abc-123',
        'is_active' => true,
    ]);
});

test('token cannot create duplicate rows', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)->postJson('/push/devices', [
        'token' => 'same-token',
        'device_type' => 'web',
    ])->assertCreated();

    $this->actingAs($user)->postJson('/push/devices', [
        'token' => 'same-token',
        'device_type' => 'web',
        'device_name' => 'Updated',
    ])->assertCreated();

    expect(PushDevice::query()->where('token', 'same-token')->count())->toBe(1)
        ->and(PushDevice::query()->where('token', 'same-token')->value('device_name'))->toBe('Updated');
});

test('token switching user is handled safely', function () {
    $miguel = User::factory()->customer()->create();
    $pedro = User::factory()->customer()->create();

    app(DeviceRegistrationService::class)->register($miguel, [
        'token' => 'shared-browser-token',
        'device_type' => 'web',
    ]);

    app(DeviceRegistrationService::class)->register($pedro, [
        'token' => 'shared-browser-token',
        'device_type' => 'web',
    ]);

    $device = PushDevice::query()->where('token', 'shared-browser-token')->first();

    expect(PushDevice::query()->where('token', 'shared-browser-token')->count())->toBe(1)
        ->and($device?->user_id)->toBe($pedro->id)
        ->and($device?->is_active)->toBeTrue();
});

test('user cannot deactivate another users device', function () {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    PushDevice::factory()->create([
        'user_id' => $owner->id,
        'token' => 'owner-token',
        'is_active' => true,
    ]);

    $this->actingAs($other)
        ->deleteJson('/push/devices', ['token' => 'owner-token'])
        ->assertOk();

    $this->assertDatabaseHas('push_devices', [
        'user_id' => $owner->id,
        'token' => 'owner-token',
        'is_active' => true,
    ]);
});
