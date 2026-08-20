<?php

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Services\Push\PushNotificationService;
use App\Support\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendPushToUserJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public PushMessage $message,
    ) {}

    public function handle(PushNotificationService $push): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $push->sendToUser($user, $this->message);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued push delivery failed', [
            'user_id' => $this->userId,
            'title' => $this->message->title,
            'message' => $exception->getMessage(),
        ]);
    }
}
