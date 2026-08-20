<?php

namespace App\Notifications\Business;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Business;
use App\Notifications\RideNotification;

final class AdminBusinessPendingNotification extends RideNotification
{
    public function __construct(public Business $business) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Business;
    }

    public function title(): string
    {
        return 'Empresa pendiente de aprobación';
    }

    public function body(): string
    {
        return "{$this->business->name} solicitó entrar a la plataforma.";
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'admin-business-pending:'.$this->business->id;
    }

    public function targetType(): ?string
    {
        return 'business';
    }

    public function targetId(): ?int
    {
        return $this->business->id;
    }

    public function clickPath(): ?string
    {
        return '/admin/businesses/'.$this->business->id;
    }
}
