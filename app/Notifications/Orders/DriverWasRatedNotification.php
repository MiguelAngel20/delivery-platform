<?php

namespace App\Notifications\Orders;

use App\Enums\NotificationCategory;
use App\Models\DriverRating;
use App\Notifications\RideNotification;

final class DriverWasRatedNotification extends RideNotification
{
    public function __construct(public DriverRating $rating) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Order;
    }

    public function title(): string
    {
        return 'Te calificaron';
    }

    public function body(): string
    {
        $stars = (int) $this->rating->overall_rating;

        return "Un cliente te calificó con {$stars} ".($stars === 1 ? 'estrella' : 'estrellas').'.';
    }

    public function dedupeKey(): ?string
    {
        return 'driver-rated:'.$this->rating->id;
    }

    public function targetType(): ?string
    {
        return 'order';
    }

    public function targetId(): ?int
    {
        return $this->rating->order_id;
    }

    public function clickPath(): ?string
    {
        return '/driver/earnings';
    }
}
