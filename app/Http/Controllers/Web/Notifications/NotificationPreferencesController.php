<?php

namespace App\Http\Controllers\Web\Notifications;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, NotificationPreferenceService $preferences): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $preference = $preferences->forUser($user);

        return Inertia::render($this->pageForRole($user->role), [
            'preferences' => $preference->only([
                'push_enabled',
                'order_updates',
                'new_orders',
                'driver_offers',
                'finance_updates',
                'incident_updates',
                'custom_order_updates',
                'system_updates',
            ]),
            'editable_keys' => $preferences->editableKeysForRole($user->role),
            'role' => $user->role->value,
            'update_url' => $this->updateUrlForRole($user->role),
        ]);
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        NotificationPreferenceService $preferences,
    ): RedirectResponse {
        $preferences->update($request->user(), $request->validated());

        return back();
    }

    private function pageForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::Customer => 'customer/notifications/preferences',
            UserRole::Driver => 'driver/notifications/preferences',
            UserRole::BusinessAdmin, UserRole::BusinessEmployee => 'business/notifications/preferences',
            UserRole::SystemAdmin => 'admin/notifications/preferences',
        };
    }

    private function updateUrlForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::Customer => route('customer.profile.notifications.update'),
            UserRole::Driver => route('driver.profile.notifications.update'),
            UserRole::BusinessAdmin, UserRole::BusinessEmployee => route('business.settings.notifications.update'),
            UserRole::SystemAdmin => route('admin.settings.notifications.update'),
        };
    }
}
