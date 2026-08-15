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

test('user can mark all notifications as read', function () {
    $user = User::factory()->customer()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Uno', 'body' => 'A'],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['title' => 'Dos', 'body' => 'B'],
    ]);

    $this->actingAs($user)
        ->postJson('/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});
