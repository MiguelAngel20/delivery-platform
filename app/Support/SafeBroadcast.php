<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcast is a side effect: business flows (orders, dispatch, etc.) must not fail
 * when Reverb/Pusher is unreachable (common in local without `reverb:start`).
 */
final class SafeBroadcast
{
    public static function event(mixed $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Broadcast failed; continuing without realtime', [
                'event' => is_object($event) ? $event::class : get_debug_type($event),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
