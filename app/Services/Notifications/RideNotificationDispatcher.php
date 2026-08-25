<?php

namespace App\Services\Notifications;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CancelledByType;
use App\Enums\IncidentSeverity;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\Notifications\SendDriverRatingPromptJob;
use App\Models\Business;
use App\Models\BusinessUpgradeRequest;
use App\Models\BusinessUser;
use App\Models\CustomOrderRequest;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Incident;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Business\AdminBusinessPendingNotification;
use App\Notifications\Business\AdminUpgradeRequestNotification;
use App\Notifications\CustomOrders\CustomQuoteReadyNotification;
use App\Notifications\CustomOrders\CustomRequestCreatedNotification;
use App\Notifications\Incidents\IncidentAlertNotification;
use App\Notifications\Orders\AdminAffiliateOrderNotification;
use App\Notifications\Orders\DriverOrderReadyNotification;
use App\Notifications\Orders\DriverWasRatedNotification;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\NewDriverOfferNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderDriverAssignedNotification;
use App\Notifications\Orders\OrderStatusChangedNotification;
use App\Notifications\Orders\PlatformOrderPendingNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RideNotificationDispatcher
{
    /**
     * @var list<OrderStatus>
     */
    private const CUSTOMER_ACCEPTED_STATUSES = [
        OrderStatus::Accepted,
        OrderStatus::Preparing,
    ];

    /**
     * @var list<CancelledByType>
     */
    private const BUSINESS_CANCELLATION_ACTORS = [
        CancelledByType::Customer,
        CancelledByType::Driver,
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

            if ($order->branch_id !== null) {
                foreach ($this->usersForBranch($order) as $user) {
                    $user->notify(new NewBusinessOrderNotification($order));
                }
            }

            if ($this->usesRideDrivers($order)) {
                $this->notifySystemAdmins(
                    fn () => new AdminAffiliateOrderNotification($order),
                );
            }
        });
    }

    public function statusChanged(Order $order, OrderStatus $previous): void
    {
        $this->afterCommit(function () use ($order, $previous): void {
            $order->loadMissing([
                'branch.business',
                'customer.user',
                'assignedDriver.user',
                'cancellation',
            ]);

            $status = $order->order_status;

            $this->notifyCustomerOfStatus($order, $status, $previous);
            $this->notifyBusinessAndAdminOfCancellation($order, $status);
            $this->notifyAssignedDriverWhenReady($order, $status);

            if ($status === OrderStatus::Delivered && $previous !== OrderStatus::Delivered) {
                $delay = now()->addMinutes((int) config('push.rating_prompt_delay_minutes', 1440));
                SendDriverRatingPromptJob::dispatch($order->id)->delay($delay);
            }
        });
    }

    public function driverAssigned(Order $order): void
    {
        $this->afterCommit(function () use ($order): void {
            $order->loadMissing([
                'customer.user',
                'assignedDriver.user',
            ]);

            $customerUser = $order->customer?->user;

            if ($customerUser === null || $order->assigned_driver_id === null) {
                return;
            }

            $customerUser->notify(new OrderDriverAssignedNotification($order));
        });
    }

    public function driverOffer(Order $order, Driver $driver): void
    {
        $driver->loadMissing('user');
        $order->loadMissing(['branch.business']);

        if ($driver->user === null) {
            return;
        }

        $driver->user->notify(new NewDriverOfferNotification($order));
    }

    public function driverReady(Order $order, Driver $driver): void
    {
        $driver->loadMissing('user');
        $order->loadMissing(['branch.business']);

        if ($driver->user === null) {
            return;
        }

        $driver->user->notify(new DriverOrderReadyNotification($order));
    }

    public function driverRated(DriverRating $rating): void
    {
        $this->afterCommit(function () use ($rating): void {
            $rating->loadMissing('driver.user');
            $user = $rating->driver?->user;

            if ($user === null) {
                return;
            }

            $user->notify(new DriverWasRatedNotification($rating));
        });
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

    public function upgradeRequested(BusinessUpgradeRequest $upgradeRequest): void
    {
        $this->afterCommit(function () use ($upgradeRequest): void {
            $this->notifySystemAdmins(
                fn () => new AdminUpgradeRequestNotification($upgradeRequest),
            );
        });
    }

    public function businessPendingApproval(Business $business): void
    {
        $this->afterCommit(function () use ($business): void {
            $this->notifySystemAdmins(
                fn () => new AdminBusinessPendingNotification($business),
                $business->created_by_user_id,
            );
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

    private function notifyCustomerOfStatus(Order $order, OrderStatus $status, OrderStatus $previous): void
    {
        $customerUser = $order->customer?->user;

        if ($customerUser === null) {
            return;
        }

        $shouldNotify = in_array($status, self::CUSTOMER_ACCEPTED_STATUSES, true)
            || $status === OrderStatus::PickedUp
            || ($status === OrderStatus::OnTheWay && $previous !== OrderStatus::PickedUp)
            || $status === OrderStatus::Delivered;

        if (! $shouldNotify) {
            return;
        }

        $customerUser->notify(new OrderStatusChangedNotification(
            $order,
            $status,
            UserRole::Customer,
        ));
    }

    private function notifyBusinessAndAdminOfCancellation(Order $order, OrderStatus $status): void
    {
        if ($status !== OrderStatus::Cancelled) {
            return;
        }

        $cancelledBy = $order->cancellation?->cancelled_by_type;

        if (in_array($cancelledBy, self::BUSINESS_CANCELLATION_ACTORS, true)) {
            foreach ($this->usersForBranch($order) as $user) {
                $audience = $user->role === UserRole::BusinessEmployee
                    ? UserRole::BusinessEmployee
                    : UserRole::BusinessAdmin;

                $user->notify(new OrderCancelledNotification($order, $audience, $cancelledBy));
            }
        }

        if ($this->isAffiliateOrManaged($order)) {
            $this->notifySystemAdmins(
                fn () => new OrderCancelledNotification($order, UserRole::SystemAdmin, $cancelledBy),
            );
        }
    }

    private function notifyAssignedDriverWhenReady(Order $order, OrderStatus $status): void
    {
        if ($status !== OrderStatus::ReadyForPickup || $order->assignedDriver?->user === null) {
            return;
        }

        $order->assignedDriver->user->notify(new DriverOrderReadyNotification($order));
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
                    return $membership->branches->contains(
                        fn ($branch): bool => (int) $branch->id === (int) $branchId,
                    );
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
    private function notifySystemAdmins(callable $notificationFactory, ?int $excludeUserId = null): void
    {
        User::query()
            ->where('role', UserRole::SystemAdmin)
            ->where('status', UserStatus::Active)
            ->when(
                $excludeUserId !== null,
                fn ($query) => $query->where('id', '!=', $excludeUserId),
            )
            ->each(fn (User $user) => $user->notify($notificationFactory()));
    }

    private function usesRideDrivers(Order $order): bool
    {
        if ($order->operation_mode !== BusinessOperationMode::Partner) {
            return false;
        }

        $deliveryMode = $order->branch?->business?->delivery_mode;

        return in_array($deliveryMode, [
            BusinessDeliveryMode::PlatformDrivers,
            BusinessDeliveryMode::Hybrid,
        ], true);
    }

    private function isAffiliateOrManaged(Order $order): bool
    {
        return $order->isPlatformManaged()
            || $order->operation_mode === BusinessOperationMode::Partner;
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
