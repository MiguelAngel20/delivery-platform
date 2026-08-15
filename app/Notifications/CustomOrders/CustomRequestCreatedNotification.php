<?php

namespace App\Notifications\CustomOrders;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\CustomOrderRequest;
use App\Notifications\RideNotification;

final class CustomRequestCreatedNotification extends RideNotification
{
    public function __construct(public CustomOrderRequest $request) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::CustomOrder;
    }

    public function title(): string
    {
        return 'Nueva solicitud personalizada';
    }

    public function body(): string
    {
        return 'Hay una solicitud custom pendiente de cotizar.';
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::High;
    }

    public function dedupeKey(): ?string
    {
        return 'custom-request:'.$this->request->id;
    }

    public function targetType(): ?string
    {
        return 'custom_order';
    }

    public function targetId(): ?int
    {
        return $this->request->id;
    }

    public function clickPath(): ?string
    {
        return '/admin/custom-orders/'.$this->request->id;
    }
}
