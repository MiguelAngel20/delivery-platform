<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use App\Models\Driver;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\PushMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class PushNotificationService
{
    public function __construct(
        private readonly PushProvider $provider,
    ) {}

    /**
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function sendToUser(User $user, PushMessage $message): array
    {
        $devices = PushDevice::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        return $this->deliver($devices, $message);
    }

    /**
     * @param  Collection<int, User>|list<User>  $users
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function sendToUsers(iterable $users, PushMessage $message): array
    {
        $ids = collect($users)->pluck('id')->filter()->unique()->values();

        $devices = PushDevice::query()
            ->whereIn('user_id', $ids)
            ->where('is_active', true)
            ->get();

        return $this->deliver($devices, $message);
    }

    /**
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function sendToDriver(Driver $driver, PushMessage $message): array
    {
        $driver->loadMissing('user');

        if ($driver->user === null) {
            return ['sent' => 0, 'failed' => 0, 'invalidated' => []];
        }

        return $this->sendToUser($driver->user, $message);
    }

    /**
     * @param  Collection<int, User>|list<User>  $users
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    public function sendToBusinessUsers(iterable $users, PushMessage $message): array
    {
        return $this->sendToUsers($users, $message);
    }

    /**
     * @param  Collection<int, PushDevice>  $devices
     * @return array{sent: int, failed: int, invalidated: list<string>}
     */
    private function deliver(Collection $devices, PushMessage $message): array
    {
        if ($devices->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'invalidated' => []];
        }

        $tokens = $devices->pluck('token')->all();
        $result = $this->provider->sendToMany($tokens, $message);

        if ($result['invalidated'] !== []) {
            PushDevice::query()
                ->whereIn('token', $result['invalidated'])
                ->update(['is_active' => false]);

            Log::info('Push devices invalidated', [
                'count' => count($result['invalidated']),
            ]);
        }

        PushDevice::query()
            ->whereIn('token', $tokens)
            ->where('is_active', true)
            ->update(['last_used_at' => now()]);

        return $result;
    }
}
