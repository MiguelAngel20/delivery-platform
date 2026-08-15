<?php

namespace App\Services\Notifications;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\IncidentSeverity;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\Notifications\SendDriverRatingPromptJob;
use App\Models\BusinessUser;
use App\Models\CustomOrderRequest;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CustomOrders\CustomQuoteReadyNotification;
use App\Notifications\CustomOrders\CustomRequestCreatedNotification;
use App\Notifications\Incidents\IncidentAlertNotification;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\NewDriverOfferNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Notifications\Orders\PlatformOrderPendingNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RideNotificationDispatcher
{
    /**
     * @var list<OrderStatus>
     */
    private const CUSTOMER_STATUS_EVENTS = [
        OrderStatus::Accepted,
        OrderStatus::Preparing,
        OrderStatus::DriverAssigned,
        OrderStatus::PickedUp,
        OrderStatus::OnTheWay,
        OrderStatus::Delivered,
        OrderStatus::Cancelled,
        OrderStatus::Rejected,
        OrderStatus::PendingCustomerConfirmation,
    ];

    public function orderCreated(Order $order): void
    {
        $this->afterCommit(function () use ($order): void {
            $order->loadMissing(['branch.business', 'customer.user']);

            if ($order->isPlatformManaged()) {
                $this->notifySystemAdmins(
                    fn () => new PlatformOrderPendingNotification($order),
                );

                return;
            }

            if ($order->branch_id === null) {
                return;
            }

            foreach ($this->usersForBranch($order) as $user) {
                $user->notify(new NewBusinessOrderNotification($order));
            }
        });
    }

    public function statusChanged(Order $order, OrderStatus $previous): void
    {
        $this->afterCommit(function () use ($order, $previous): void {
            $order->loadMissing(['branch.business', 'customer.user', 'assignedDriver.user']);

            $status = $order->order_status;

            if (in_array($status, self::CUSTOMER_STATUS_EVENTS, true)) {
                $customerUser = $order->customer?->user;

                if ($customerUser !== null) {
                    $customerUser->notify(new OrderStatusChangedNotification(
                        $order,
                        $status,
                        UserRole::Customer,
                    ));
                }
            }

            if (in_array($status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)
                && ! $order->isPlatformManaged()
            ) {
                foreach ($this->usersForBranch($order) as $user) {
                    $user->notify(new OrderStatusChangedNotification(
                        $order,
                        $status,
                        UserRole::BusinessAdmin,
                    ));
                }
            }

            if ($order->assignedDriver?->user !== null
                && in_array($status, [
                    OrderStatus::ReadyForPickup,
                    OrderStatus::Cancelled,
                    OrderStatus::Rejected,
                ], true)
            ) {
                $order->assignedDriver->user->notify(new OrderStatusChangedNotification(
                    $order,
                    $status,
                    UserRole::Driver,
                ));
            }

            if ($status === OrderStatus::Delivered && $previous !== OrderStatus::Delivered) {
                $delay = now()->addMinutes((int) config('push.rating_prompt_delay_minutes', 5));
                SendDriverRatingPromptJob::dispatch($order->id)->delay($delay);
            }
        });
    }

    public function driverAssigned(Order $order): void
    {
        $this->afterCommit(function () use ($order): void {
            $order->loadMissing(['customer.user']);

            $customerUser = $order->customer?->user;

            if ($customerUser === null) {
                return;
            }

            $customerUser->notify(new OrderStatusChangedNotification(
                $order,
                OrderStatus::DriverAssigned,
                UserRole::Customer,
            ));
        });
    }

    public function driverOffer(Order $order, Driver $driver): void
    {
        $driver->loadMissing('user');

        if ($driver->user === null) {
            return;
        }

        $driver->user->notify(new NewDriverOfferNotification($order));
    }

    public function customOrderRequested(CustomOrderRequest $request): void
    {
        $this->afterCommit(function () use ($request): void {
            $this->notifySystemAdmins(
                fn () => new CustomRequestCreatedNotification($request),
            );
        });
    }

    public function customQuoteReady(CustomOrderRequest $request): void
    {
        $this->afterCommit(function () use ($request): void {
            $request->loadMissing('customer.user');

            $user = $request->customer?->user;

            if ($user === null) {
                return;
            }

            $user->notify(new CustomQuoteReadyNotification($request));
        });
    }

    public function incidentCreated(Incident $incident): void
    {
        $this->afterCommit(function () use ($incident): void {
            $incident->loadMissing('order');

            if (! in_array($incident->severity, [
                IncidentSeverity::High,
                IncidentSeverity::Critical,
            ], true)) {
                return;
            }

            $this->notifySystemAdmins(
                fn () => new IncidentAlertNotification($incident),
            );
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForBranch(Order $order): Collection
    {
        if ($order->branch_id === null) {
            return collect();
        }

        $branchId = $order->branch_id;
        $businessId = $order->branch?->business_id;

        if ($businessId === null) {
            return collect();
        }

        return BusinessUser::query()
            ->with(['user', 'branches'])
            ->where('business_id', $businessId)
            ->where('status', BusinessUserStatus::Active)
            ->get()
            ->filter(function (BusinessUser $membership) use ($branchId): bool {
                if ($membership->user === null) {
                    return false;
                }

                if ($membership->role === BusinessUserRole::BusinessAdmin) {
                    return true;
                }

                return $membership->branches->contains(
                    fn ($branch): bool => (int) $branch->id === (int) $branchId,
                );
            })
            ->map(fn (BusinessUser $membership): User => $membership->user)
            ->unique('id')
            ->values();
    }

    /**
     * @param  callable(): object  $notificationFactory
     */
    private function notifySystemAdmins(callable $notificationFactory): void
    {
        User::query()
            ->where('role', UserRole::SystemAdmin)
            ->where('status', UserStatus::Active)
            ->each(fn (User $user) => $user->notify($notificationFactory()));
    }

    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
