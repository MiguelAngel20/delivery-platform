<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Events\Notifications\UnreadNotificationsUpdated;
use App\Http\Controllers\Controller;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => (string) data_get($notification->data, 'title', 'Notificación'),
                'body' => (string) data_get($notification->data, 'body', ''),
                'category' => data_get($notification->data, 'category'),
                'type' => data_get($notification->data, 'type'),
                'target_type' => data_get($notification->data, 'target_type'),
                'target_id' => data_get($notification->data, 'target_id'),
                'click_path' => data_get($notification->data, 'click_path'),
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->markAsRead();

        $unread = $request->user()->unreadNotifications()->count();
        SafeBroadcast::event(new UnreadNotificationsUpdated($request->user()->id, $unread));

        return response()->json([
            'ok' => true,
            'unread_count' => $unread,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        SafeBroadcast::event(new UnreadNotificationsUpdated($request->user()->id, 0));

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}
