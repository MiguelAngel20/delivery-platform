<?php

use App\Models\User;
use Illuminate\Support\Str;

test('notification belongs to correct user and can be marked read', function () {
    $user = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Orders\\OrderStatusChangedNotification',
        'data' => [
            'title' => 'Pedido entregado',
            'body' => 'Esperamos que todo haya llegado bien.',
            'category' => 'order',
        ],
    ]);

    $this->actingAs($user)
        ->getJson('/notifications/inbox')
        ->assertOk()
        ->assertJsonPath('data.0.id', $notification->id)
        ->assertJsonPath('unread_count', 1);

    $this->actingAs($other)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('inbox only returns notifications from today', function () {
    $user = User::factory()->customer()->create();

    $today = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Hoy', 'body' => 'A'],
    ]);

    $yesterday = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Ayer', 'body' => 'B'],
    ]);
    $yesterday->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->getJson('/notifications/inbox')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $today->id);
});

test('mark all as read only clears todays unread notifications', function () {
    $user = User::factory()->customer()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Hoy', 'body' => 'A'],
    ]);

    $yesterday = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Ayer', 'body' => 'B'],
    ]);
    $yesterday->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->postJson('/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($user->todaysUnreadNotificationCount())->toBe(0)
        ->and($yesterday->fresh()->read_at)->toBeNull();
});
