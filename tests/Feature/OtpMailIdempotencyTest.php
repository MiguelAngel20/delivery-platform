<?php

use App\Models\NotificationIdempotencyKey;
use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use App\Services\Auth\EmailVerificationCodeService;
use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Support\Facades\Notification;

test('retry of the same otp issuance does not open the mail channel twice', function () {
    $user = User::factory()->customer()->create([
        'email_verified_at' => null,
    ]);

    $notification = new CustomerEmailVerificationCode('123456', 'issuance-retry-1');

    expect($notification->via($user))->toBe(['mail'])
        ->and($notification->via($user))->toBe([])
        ->and(NotificationIdempotencyKey::query()->count())->toBe(1);
});

test('explicit otp reissue is allowed with a new issuance id', function () {
    Notification::fake();

    $user = User::factory()->customer()->create([
        'email_verified_at' => null,
    ]);

    $codes = app(EmailVerificationCodeService::class);

    $first = $codes->issue($user);
    $second = $codes->issue($user);

    expect($first)->not->toBe($second);

    Notification::assertSentToTimes($user, CustomerEmailVerificationCode::class, 2);
});

test('explicit resend invalidates the previous otp code', function () {
    Notification::fake();

    $user = User::factory()->customer()->create([
        'email_verified_at' => null,
    ]);

    $codes = app(EmailVerificationCodeService::class);

    $codes->issue($user);
    $previousCode = Notification::sent($user, CustomerEmailVerificationCode::class)->last()->code;

    $codes->issue($user);
    $latestCode = Notification::sent($user, CustomerEmailVerificationCode::class)->last()->code;

    expect($previousCode)->not->toBe($latestCode)
        ->and($codes->verify($user, $previousCode))->toBeFalse()
        ->and($codes->verify($user, $latestCode))->toBeTrue()
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('failed otp delivery marks claim failed so a technical retry can send', function () {
    $user = User::factory()->customer()->create([
        'email_verified_at' => null,
    ]);

    $idempotency = app(NotificationIdempotencyService::class);
    $notification = new CustomerEmailVerificationCode('123456', 'issuance-fail-1');
    $key = $idempotency->otpMailKey('issuance-fail-1');

    expect($notification->via($user))->toBe(['mail']);

    $idempotency->markFailed($key, 'SMTP down');

    $row = NotificationIdempotencyKey::query()->where('idempotency_key', $key)->first();

    expect($row->status)->toBe(NotificationIdempotencyKey::STATUS_FAILED)
        ->and($notification->via($user))->toBe(['mail']);

    $row->refresh();
    expect($row->status)->toBe(NotificationIdempotencyKey::STATUS_CLAIMED)
        ->and($row->attempts)->toBe(2)
        ->and(NotificationIdempotencyKey::query()->count())->toBe(1);
});

test('otp notification unique id is the issuance id', function () {
    $notification = new CustomerEmailVerificationCode('000000', 'issuance-unique-xyz');

    expect($notification->uniqueId())->toBe('issuance-unique-xyz')
        ->and($notification->uniqueFor())->toBe(3600);
});
