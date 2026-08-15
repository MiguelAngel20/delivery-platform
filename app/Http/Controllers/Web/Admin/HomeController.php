<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CustomOrderRequestStatus;
use App\Enums\IncidentStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomOrderRequest;
use App\Models\Incident;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/home', [
            'operation' => [
                'pending_platform' => Order::query()
                    ->where('order_status', OrderStatus::PendingPlatform)
                    ->count(),
                'custom_pending' => CustomOrderRequest::query()
                    ->whereIn('status', [
                        CustomOrderRequestStatus::PendingReview->value,
                        CustomOrderRequestStatus::Reviewing->value,
                    ])
                    ->count(),
                'quotes_waiting' => CustomOrderRequest::query()
                    ->where('status', CustomOrderRequestStatus::Quoted)
                    ->count()
                    + Order::query()
                        ->where('order_status', OrderStatus::PendingCustomerConfirmation)
                        ->count(),
                'open_incidents' => Incident::query()
                    ->whereIn('status', [
                        IncidentStatus::Open->value,
                        IncidentStatus::UnderReview->value,
                    ])
                    ->count(),
            ],
        ]);
    }
}
