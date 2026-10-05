<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLoyaltyLaunchSettingRequest;
use App\Models\LoyaltyLaunchSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LoyaltyLaunchSettingController extends Controller
{
    public function update(UpdateLoyaltyLaunchSettingRequest $request): RedirectResponse
    {
        $settings = LoyaltyLaunchSetting::current();
        $enabled = $request->boolean('enabled');

        $settings->update([
            'enabled' => $enabled,
            'updated_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? 'Mitad de precio activada. Sigue hasta completar los 25 servicios.'
                : 'Mitad de precio desactivada. Los servicios se cobran completos.',
        ]);

        return back();
    }
}
