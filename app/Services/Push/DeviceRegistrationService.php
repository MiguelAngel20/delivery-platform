<?php

namespace App\Services\Push;

use App\Enums\PushDeviceType;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeviceRegistrationService
{
    /**
     * @param  array{
     *     token: string,
     *     device_type?: string|null,
     *     browser?: string|null,
     *     platform?: string|null,
     *     device_name?: string|null
     * }  $payload
     */
    public function register(User $user, array $payload): PushDevice
    {
        $token = trim($payload['token']);

        return DB::transaction(function () use ($user, $payload, $token): PushDevice {
            /** @var PushDevice|null $existing */
            $existing = PushDevice::query()->where('token', $token)->lockForUpdate()->first();

            $attributes = [
                'user_id' => $user->id,
                'provider' => 'fcm',
                'device_type' => PushDeviceType::tryFrom((string) ($payload['device_type'] ?? 'web'))
                    ?? PushDeviceType::Web,
                'browser' => $payload['browser'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'device_name' => $payload['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();

                return $existing->fresh();
            }

            return PushDevice::query()->create([
                'token' => $token,
                ...$attributes,
            ]);
        });
    }

    public function deactivateToken(User $user, string $token): void
    {
        PushDevice::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->update(['is_active' => false]);
    }

    public function deactivateAllForUser(User $user): void
    {
        PushDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);
    }
}
