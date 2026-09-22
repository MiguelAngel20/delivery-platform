<?php

namespace App\Http\Controllers\Web\Driver;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderData;
use App\Support\ReputationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $driver = $this->currentDriver($request);
        [$from, $to] = $this->resolveDateRange($request);

        $completedOrders = Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->where('order_status', OrderStatus::Delivered)
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$from, $to])
            ->with(['branch.business', 'financial', 'driverRating', 'items.options', 'addresses'])
            ->latest('delivered_at')
            ->limit(30)
            ->get()
            ->map(fn (Order $order): array => OrderData::driverCompletedCard($order))
            ->values()
            ->all();

        $ratings = DriverRating::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['order', 'customer.user'])
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(function (DriverRating $rating): array {
                $customer = $rating->customer;

                return [
                    'id' => $rating->id,
                    'order_id' => $rating->order_id,
                    'order_number' => $rating->order?->order_number,
                    'overall_rating' => $rating->overall_rating,
                    'speed_rating' => $rating->speed_rating,
                    'service_rating' => $rating->service_rating,
                    'care_rating' => $rating->care_rating,
                    'respect_rating' => $rating->respect_rating,
                    'communication_rating' => $rating->communication_rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at?->toIso8601String(),
                    'customer_name' => $customer !== null
                        ? ReputationPresenter::customerForDriver($customer)['name']
                        : 'Cliente',
                ];
            })
            ->values()
            ->all();

        return Inertia::render('driver/home', [
            'completedOrders' => $completedOrders,
            'ratings' => $ratings,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $from = $request->date('from') ?? Carbon::today();
        $to = $request->date('to') ?? Carbon::today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
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
