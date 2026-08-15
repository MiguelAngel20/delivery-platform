<?php

namespace App\Listeners\Notifications;

use App\Models\User;
use App\Services\Push\DeviceRegistrationService;
use Illuminate\Auth\Events\Logout;

final class DeactivatePushDeviceOnLogout
{
    public function __construct(
        private readonly DeviceRegistrationService $devices,
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $token = request()->input('push_token')
            ?? request()->header('X-Push-Token');

        if (! is_string($token) || $token === '') {
            return;
        }

        $this->devices->deactivateToken($user, $token);
    }
}
