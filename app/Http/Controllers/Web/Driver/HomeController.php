<?php

namespace App\Http\Controllers\Web\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\AvailableOrdersQuery;
use App\Services\Dispatch\DriverActiveOrderService;
use App\Services\Dispatch\DriverRankingService;
use App\Support\OrderData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        DriverActiveOrderService $activeOrders,
        AvailableOrdersQuery $availableOrders,
        DriverRankingService $ranking,
    ): Response {
        $driver = $this->currentDriver($request);
        $active = $activeOrders->activeOrdersFor($driver);

        $grouped = $active
            ->groupBy(fn (Order $order): string => (string) $order->branch_id)
            ->map(function ($orders) {
                /** @var Order $first */
                $first = $orders->first();

                return [
                    'branch_id' => $first->branch_id,
                    'business' => $first->branch?->business?->name,
                    'branch_name' => $first->branch?->name,
                    'order_numbers' => $orders->pluck('order_number')->values()->all(),
                    'orders' => $orders
                        ->map(fn (Order $order): array => OrderData::driverActiveCard($order))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $compatible = $availableOrders->forDriver($driver)
            ->filter(function (Order $order) use ($active): bool {
                if ($active->isEmpty()) {
                    return false;
                }

                return (int) $order->branch_id === (int) $active->first()?->branch_id;
            })
            ->take(3)
            ->map(fn (Order $order): array => OrderData::driverAvailableCard(
                $order,
                $ranking->distanceToPickupMeters($driver, $order),
            ))
            ->values()
            ->all();

        return Inertia::render('driver/home', [
            'availabilityStatus' => $driver->availability_status->value,
            'stats' => [
                'active_orders' => $active->count(),
            ],
            'activeGroups' => $grouped,
            'compatibleOrders' => $compatible,
        ]);
    }

    private function currentDriver(Request $request): Driver
    {
        /** @var User $user */
        $user = $request->user();
        $driver = $user->driver;

        abort_unless($driver !== null, 403);

        return $driver;
    }
}
