<?php

use App\Models\User;
use App\Notifications\Auth\DriverEmailVerification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('driver can verify email from signed link and see welcome page', function () {
    $user = User::factory()->driver()->unverified()->create([
        'first_name' => 'Carla',
        'email' => 'carla.driver@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'driver.verification.verify',
        now()->addHour(),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/driver-email-verified')
            ->where('firstName', 'Carla'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('invalid verification signature is rejected', function () {
    $user = User::factory()->driver()->unverified()->create();

    $this->get(route('driver.verification.verify', [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]))->assertForbidden();
});

test('unverified driver cannot log in', function () {
    $user = User::factory()->driver()->unverified()->create([
        'email' => 'sin.verificar@example.com',
        'password' => 'password',
    ]);

    $this->get(route('driver.login'))->assertOk();

    $this->from(route('driver.login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('driver.login'));

    $this->assertGuest();
});

test('verified driver can log in', function () {
    $user = User::factory()->driver()->create([
        'email' => 'verificado@example.com',
        'password' => 'password',
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $this->get(route('driver.login'))->assertOk();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('driver.home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('verification notification builds a signed action url', function () {
    Notification::fake();

    $user = User::factory()->driver()->unverified()->create();
    $user->notify(new DriverEmailVerification);

    Notification::assertSentTo($user, DriverEmailVerification::class, function (DriverEmailVerification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $url = $mail->actionUrl ?? null;

        expect($url)->not->toBeNull()
            ->and($url)->toContain((string) $user->id);

        return true;
    });
});
