<?php

namespace App\Notifications\Business;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\BusinessUpgradeRequest;
use App\Notifications\RideNotification;

final class AdminUpgradeRequestNotification extends RideNotification
{
    public function __construct(public BusinessUpgradeRequest $upgradeRequest) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Business;
    }

    public function title(): string
    {
        return 'Solicitud de empresa';
    }

    public function body(): string
    {
        $this->upgradeRequest->loadMissing('business');
        $name = $this->upgradeRequest->business?->name ?: 'Una empresa';
        $type = $this->upgradeRequest->type->label();

        return "{$name} solicitó {$type}.";
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'admin-upgrade:'.$this->upgradeRequest->id;
    }

    public function targetType(): ?string
    {
        return 'business';
    }

    public function targetId(): ?int
    {
        return $this->upgradeRequest->business_id;
    }

    public function clickPath(): ?string
    {
        return '/admin/businesses/'.$this->upgradeRequest->business_id;
    }
}
