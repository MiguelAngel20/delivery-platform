<?php

namespace App\Services\Notifications;

use App\Models\NotificationIdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed claims for critical notification side effects.
 *
 * Complements short-lived Cache::add dedupe: survives Redis flush and long delays.
 * Status lifecycle preserves rows for audit: claimed → sent | failed (reclaimable).
 */
final class NotificationIdempotencyService
{
    public function claim(string $key): bool
    {
        try {
            NotificationIdempotencyKey::query()->create([
                'idempotency_key' => $key,
                'status' => NotificationIdempotencyKey::STATUS_CLAIMED,
                'attempts' => 1,
                'updated_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            $affected = NotificationIdempotencyKey::query()
                ->where('idempotency_key', $key)
                ->where('status', NotificationIdempotencyKey::STATUS_FAILED)
                ->update([
                    'status' => NotificationIdempotencyKey::STATUS_CLAIMED,
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => null,
                    'updated_at' => now(),
                    'completed_at' => null,
                ]);

            return $affected > 0;
        }
    }

    public function markSent(string $key): void
    {
        NotificationIdempotencyKey::query()
            ->where('idempotency_key', $key)
            ->whereIn('status', [
                NotificationIdempotencyKey::STATUS_CLAIMED,
                NotificationIdempotencyKey::STATUS_FAILED,
            ])
            ->update([
                'status' => NotificationIdempotencyKey::STATUS_SENT,
                'last_error' => null,
                'updated_at' => now(),
                'completed_at' => now(),
            ]);
    }

    /**
     * Allow a technical retry without deleting the claim row (audit trail).
     * Never demotes a successful `sent` claim.
     */
    public function markFailed(string $key, ?string $error = null): void
    {
        NotificationIdempotencyKey::query()
            ->where('idempotency_key', $key)
            ->where('status', NotificationIdempotencyKey::STATUS_CLAIMED)
            ->update([
                'status' => NotificationIdempotencyKey::STATUS_FAILED,
                'last_error' => $error !== null ? mb_substr($error, 0, 2000) : null,
                'updated_at' => now(),
            ]);
    }

    public function release(string $key): void
    {
        $this->markFailed($key, 'released');
    }

    public function exists(string $key): bool
    {
        return NotificationIdempotencyKey::query()
            ->where('idempotency_key', $key)
            ->whereIn('status', [
                NotificationIdempotencyKey::STATUS_CLAIMED,
                NotificationIdempotencyKey::STATUS_SENT,
            ])
            ->exists();
    }

    public function notificationKey(int $userId, string $dedupeKey): string
    {
        return 'notif:'.$userId.':'.$dedupeKey;
    }

    public function pushKey(int $userId, string $idempotencyKey): string
    {
        return 'push:'.$userId.':'.$idempotencyKey;
    }

    public function otpMailKey(string $issuanceId): string
    {
        return 'otp:mail:'.$issuanceId;
    }
}
