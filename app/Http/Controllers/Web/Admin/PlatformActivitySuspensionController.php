<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PlatformSuspensionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformActivitySuspensionRequest;
use App\Models\PlatformActivitySuspension;
use App\Services\Platform\PlatformActivitySuspensionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformActivitySuspensionController extends Controller
{
    public function edit(PlatformActivitySuspensionService $suspensions): Response
    {
        $current = $suspensions->current();

        return Inertia::render('admin/settings/activity-suspension', [
            'suspension' => [
                'is_active' => $current->is_active,
                'reason' => $current->reason->value,
                'updated_at' => $current->updated_at?->toIso8601String(),
            ],
            'reasons' => collect(PlatformSuspensionReason::cases())
                ->map(fn (PlatformSuspensionReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'title' => $reason->title(),
                    'body' => $reason->body(),
                    'footnote' => $reason->footnote(),
                    'active_order_message' => $reason->activeOrderMessage(),
                    'image_url' => $reason->imagePath(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(
        UpdatePlatformActivitySuspensionRequest $request,
        PlatformActivitySuspensionService $suspensions,
    ): RedirectResponse {
        $suspension = PlatformActivitySuspension::current();
        $suspension->update([
            'is_active' => $request->boolean('is_active'),
            'reason' => $request->enum('reason', PlatformSuspensionReason::class),
            'updated_by' => $request->user()?->id,
        ]);

        $suspensions->forgetCache();

        return back()->with('success', $request->boolean('is_active')
            ? 'Suspensión de actividad activada.'
            : 'Suspensión de actividad desactivada.');
    }
}
