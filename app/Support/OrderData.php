<?php

namespace App\Support;

use App\Enums\CancellationReasonCode;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\IncidentType;
use App\Enums\OrderAddressType;
use App\Enums\OrderQuoteStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderCancellationService;

final class OrderData
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(Order $order): array
    {
        $order->loadMissing([
            'items.options',
            'addresses',
            'statusHistory',
            'branch.business',
            'customer.user',
            'customer.metrics',
            'assignedDriver.user',
            'financial',
            'financialTransactions',
            'payment',
            'cancellation.cancelledBy',
            'incidents',
            'driverRating',
            'quotes.items',
        ]);

        $delivery = $order->addresses->firstWhere('type', OrderAddressType::Delivery);
        $pickup = $order->addresses->firstWhere('type', OrderAddressType::Pickup);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status->value,
            'order_status_label' => $order->order_status->customerLabel(),
            'business_status_label' => $order->order_status->label(),
            'payment_status' => $order->payment_status->value,
            'payment_status_label' => $order->payment_status->label(),
            'payment_method' => $order->payment_method->value,
            'payment_method_label' => $order->payment_method->label(),
            'operation_mode' => $order->operation_mode->value,
            'type' => $order->type->value,
            'is_custom' => $order->isCustom(),
            'is_platform_managed' => $order->isPlatformManaged(),
            'subtotal_before_discount' => (string) $order->subtotal_before_discount,
            'discount_total' => (string) $order->discount_total,
            'subtotal_after_discount' => (string) $order->subtotal_after_discount,
            'service_fee' => (string) $order->service_fee,
            'delivery_fee' => (string) $order->delivery_fee,
            'total' => (string) $order->total,
            'estimated_preparation_minutes' => $order->estimated_preparation_minutes,
            'notes' => $order->notes,
            'created_at' => $order->created_at?->toIso8601String(),
            'business_accepted_at' => $order->business_accepted_at?->toIso8601String(),
            'preparation_started_at' => $order->preparation_started_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'driver_arrived_at' => $order->driver_arrived_at?->toIso8601String(),
            'picked_up_at' => $order->picked_up_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'is_active' => $order->order_status->isActiveForCustomer(),
            'restaurant' => [
                'name' => $order->merchantDisplayName(),
                'slug' => $order->branch?->business?->slug,
                'branch_name' => $order->branch?->name,
            ],
            'customer' => self::customerSummary($order),
            'driver' => self::driverSummary($order),
            'items' => $order->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => (string) $item->quantity,
                'unit_final_price' => (string) $item->unit_final_price,
                'unit_acquisition_cost' => $item->unit_acquisition_cost !== null
                    ? (string) $item->unit_acquisition_cost
                    : null,
                'subtotal' => (string) $item->subtotal,
                'notes' => $item->notes,
                'options' => $item->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'option_name' => $option->option_name,
                    'option_type' => $option->option_type->value,
                    'price_modifier' => (string) $option->price_modifier,
                    'selection_action' => $option->selection_action?->value,
                    'display' => self::optionDisplay($option->option_name, $option->option_type->value, $option->selection_action?->value),
                ])->values()->all(),
            ])->values()->all(),
            'delivery_address' => $delivery ? [
                'address_text' => $delivery->address_text,
                'reference' => $delivery->reference,
                'source' => $delivery->source->value,
                'latitude' => $delivery->latitude,
                'longitude' => $delivery->longitude,
                'google_maps_url' => GoogleMapsUrl::resolve(
                    $delivery->google_maps_url,
                    $delivery->latitude,
                    $delivery->longitude,
                ),
            ] : null,
            'pickup_address' => $pickup ? [
                'address_text' => $pickup->address_text,
                'reference' => $pickup->reference,
                'latitude' => $pickup->latitude,
                'longitude' => $pickup->longitude,
                'google_maps_url' => GoogleMapsUrl::resolve(
                    $pickup->google_maps_url,
                    $pickup->latitude,
                    $pickup->longitude,
                ),
            ] : null,
            'customer_timeline' => self::customerTimeline($order),
            'timeline' => self::timeline($order),
            'financial' => self::financialSummary($order),
            'pending_quote' => self::pendingQuote($order),
            'cancellation' => self::cancellationSummary($order),
            'driver_rating' => self::driverRatingSummary($order),
            'can_rate_driver' => self::canRateDriver($order),
            'actions' => self::actorActions($order),
            'estimated_preparation_exceeded' => self::estimatedPreparationExceeded($order),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function financialSummary(Order $order): ?array
    {
        $order->loadMissing(['financial', 'financialTransactions', 'payment']);

        if ($order->financial === null) {
            return null;
        }

        $financial = $order->financial;
        $hasDriverToBusiness = $order->financialTransactions
            ->contains(fn ($tx): bool => $tx->transaction_type === FinancialTransactionType::DriverToBusiness
                && $tx->status === FinancialTransactionStatus::Completed);

        return [
            'products_amount' => (string) $financial->products_amount,
            'discount_amount' => (string) $financial->discount_amount,
            'service_fee' => (string) $financial->service_fee,
            'delivery_fee' => (string) $financial->delivery_fee,
            'customer_total' => (string) $financial->customer_total,
            'business_amount' => (string) $financial->business_amount,
            'driver_earning' => (string) $financial->driver_earning,
            'platform_earning' => (string) $financial->platform_earning,
            'payment_method' => $financial->payment_method->value,
            'payment_method_label' => $financial->payment_method->label(),
            'collection_party' => $financial->collection_party->value,
            'collection_party_label' => $financial->collection_party->label(),
            'settlement_status' => $financial->settlement_status->value,
            'settlement_status_label' => $financial->settlement_status->label(),
            'driver_paid_business' => $hasDriverToBusiness,
            'payment_status' => $order->payment?->status->value ?? $order->payment_status->value,
            'payment_status_label' => $order->payment?->status->label() ?? $order->payment_status->label(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function financialDetail(Order $order): ?array
    {
        $summary = self::financialSummary($order);

        if ($summary === null) {
            return null;
        }

        $order->loadMissing('financialTransactions');

        return [
            ...$summary,
            'transactions' => $order->financialTransactions->map(fn ($tx): array => [
                'id' => $tx->id,
                'transaction_type' => $tx->transaction_type->value,
                'transaction_type_label' => $tx->transaction_type->label(),
                'amount' => (string) $tx->amount,
                'payment_method' => $tx->payment_method->value,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'description' => $tx->description,
                'settled_at' => $tx->settled_at?->toIso8601String(),
                'created_at' => $tx->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function driverSummary(Order $order): ?array
    {
        $order->loadMissing('assignedDriver.user');

        if ($order->assignedDriver?->user === null) {
            return null;
        }

        return [
            'id' => $order->assignedDriver->id,
            'name' => $order->assignedDriver->user->name,
        ];
    }

    /**
     * Business portal payload: limited customer and driver contact details.
     *
     * @return array<string, mixed>
     */
    public static function forBusiness(Order $order): array
    {
        $data = self::transform($order);
        $order->loadMissing(['customer.user', 'customer.metrics', 'assignedDriver.user']);

        $data['customer'] = $order->customer !== null
            ? ReputationPresenter::customerForBusiness($order->customer)
            : [
                'name' => null,
                'phone' => null,
                'reputation_label' => null,
                'reputation_tone' => 'neutral',
                'completed_orders' => 0,
                'is_frequent' => false,
            ];

        $data['driver'] = $order->assignedDriver?->user !== null
            ? [
                'name' => $order->assignedDriver->user->name,
                'phone' => $order->assignedDriver->user->phone,
            ]
            : null;

        if (is_array($data['delivery_address'] ?? null)) {
            $data['delivery_address'] = [
                'address_text' => $data['delivery_address']['address_text'],
                'reference' => $data['delivery_address']['reference'] ?? null,
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerSummary(Order $order): array
    {
        $order->loadMissing(['customer.user', 'customer.metrics']);
        $customer = $order->customer;
        $public = $customer !== null ? ReputationPresenter::customerForDriver($customer) : null;

        return [
            'id' => $order->customer_id,
            'name' => $customer?->user?->name,
            'public_name' => $public['name'] ?? null,
            'phone' => $customer?->user?->phone,
            'completed_orders' => $public['completed_orders'] ?? 0,
            'verified' => $public['verified'] ?? false,
            'is_frequent' => $public['is_frequent'] ?? false,
            'public_label' => $public['public_label'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function driverRatingSummary(Order $order): ?array
    {
        $order->loadMissing('driverRating');

        if ($order->driverRating === null) {
            return null;
        }

        return [
            'overall_rating' => $order->driverRating->overall_rating,
            'comment' => $order->driverRating->comment,
        ];
    }

    public static function canRateDriver(Order $order): bool
    {
        $order->loadMissing('driverRating');

        return $order->order_status === OrderStatus::Delivered
            && $order->assigned_driver_id !== null
            && $order->driverRating === null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function driverAvailableCard(Order $order, ?int $distanceToPickupMeters = null): array
    {
        $data = self::transform($order);

        return [
            'id' => $data['id'],
            'order_number' => $data['order_number'],
            'order_status' => $data['order_status'],
            'business_status_label' => $data['business_status_label'],
            'estimated_preparation_minutes' => $data['estimated_preparation_minutes'],
            'service_fee' => $data['service_fee'],
            'is_custom' => $data['is_custom'],
            'restaurant' => $data['restaurant'],
            'delivery_address' => $data['delivery_address'],
            'pickup_address' => $data['pickup_address'],
            'distance_to_pickup_meters' => $distanceToPickupMeters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function driverActiveCard(Order $order): array
    {
        $data = self::transform($order);

        return [
            'id' => $data['id'],
            'order_number' => $data['order_number'],
            'order_status' => $data['order_status'],
            'business_status_label' => $data['business_status_label'],
            'service_fee' => $data['service_fee'],
            'ready_at' => $data['ready_at'],
            'is_custom' => $data['is_custom'],
            'restaurant' => $data['restaurant'],
            'customer' => [
                'name' => $data['customer']['public_name'] ?? $data['customer']['name'],
                'completed_orders' => $data['customer']['completed_orders'] ?? 0,
                'verified' => $data['customer']['verified'] ?? true,
                'is_frequent' => $data['customer']['is_frequent'] ?? false,
                'public_label' => $data['customer']['public_label'] ?? null,
            ],
            'delivery_address' => $data['delivery_address'],
            'pickup_address' => $data['pickup_address'],
            'actions' => self::driverActions($order),
            'cannot_continue_reasons' => CancellationReasonCode::options(CancellationReasonCode::forDriver()),
            'incident_types' => IncidentType::options(IncidentType::forDriver()),
        ];
    }

    /**
     * @return array{arrive: bool, pickup: bool, start_delivery: bool, deliver: bool}
     */
    public static function driverActions(Order $order): array
    {
        $ready = $order->ready_at !== null;
        $arrived = $order->driver_arrived_at !== null;

        return [
            'arrive' => in_array($order->order_status, [
                OrderStatus::DriverAssigned,
                OrderStatus::ReadyForPickup,
            ], true) && ! $arrived,
            'pickup' => ($order->order_status === OrderStatus::DriverAtBusiness && $ready)
                || ($order->order_status === OrderStatus::ReadyForPickup && $arrived),
            'start_delivery' => $order->order_status === OrderStatus::PickedUp,
            'deliver' => $order->order_status === OrderStatus::OnTheWay,
            'cannot_continue' => in_array($order->order_status, [
                OrderStatus::DriverAssigned,
                OrderStatus::DriverAtBusiness,
                OrderStatus::ReadyForPickup,
                OrderStatus::PickedUp,
                OrderStatus::OnTheWay,
            ], true),
            'report_problem' => in_array($order->order_status, [
                OrderStatus::DriverAssigned,
                OrderStatus::DriverAtBusiness,
                OrderStatus::ReadyForPickup,
                OrderStatus::PickedUp,
                OrderStatus::OnTheWay,
            ], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function actorActions(Order $order): array
    {
        $cancellations = app(OrderCancellationService::class);

        return [
            'customer_can_cancel' => $cancellations->customerCanCancel($order),
            'customer_can_report_problem' => $cancellations->customerCanReportProblem($order),
            'business_can_cancel' => $cancellations->businessCanCancel($order),
            'business_can_reject' => $order->order_status === OrderStatus::PendingBusiness
                && ! $order->isPlatformManaged(),
            'admin_can_confirm' => $order->isPlatformManaged()
                && $order->order_status === OrderStatus::PendingPlatform,
            'admin_can_reject' => $order->isPlatformManaged()
                && $order->order_status === OrderStatus::PendingPlatform,
            'customer_can_accept_quote' => $order->order_status === OrderStatus::PendingCustomerConfirmation,
            'admin_can_cancel' => $cancellations->adminCanCancel($order),
            'customer_cancel_reasons' => CancellationReasonCode::options(CancellationReasonCode::forCustomer()),
            'business_cancel_reasons' => CancellationReasonCode::options(CancellationReasonCode::forBusiness()),
            'driver_cannot_continue_reasons' => CancellationReasonCode::options(CancellationReasonCode::forDriver()),
            'customer_incident_types' => IncidentType::options(IncidentType::forCustomer()),
            'business_incident_types' => IncidentType::options(IncidentType::forBusiness()),
            'driver_incident_types' => IncidentType::options(IncidentType::forDriver()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function cancellationSummary(Order $order): ?array
    {
        $order->loadMissing(['cancellation.cancelledBy', 'cancellation.reviewedBy']);

        if ($order->cancellation === null) {
            return null;
        }

        $cancellation = $order->cancellation;

        return [
            'id' => $cancellation->id,
            'cancelled_by_type' => $cancellation->cancelled_by_type->value,
            'cancelled_by_type_label' => $cancellation->cancelled_by_type->label(),
            'cancelled_by_name' => $cancellation->cancelledBy?->name,
            'reason_code' => $cancellation->reason_code->value,
            'reason_code_label' => $cancellation->reason_code->label(),
            'reason' => $cancellation->reason,
            'previous_order_status' => $cancellation->previous_order_status->value,
            'previous_order_status_label' => $cancellation->previous_order_status->label(),
            'responsibility' => $cancellation->responsibility->value,
            'responsibility_label' => $cancellation->responsibility->label(),
            'review_status' => $cancellation->review_status->value,
            'review_status_label' => $cancellation->review_status->label(),
            'review_notes' => $cancellation->review_notes,
            'cancelled_at' => $cancellation->cancelled_at?->toIso8601String(),
            'reviewed_at' => $cancellation->reviewed_at?->toIso8601String(),
        ];
    }

    public static function estimatedPreparationExceeded(Order $order): bool
    {
        if ($order->estimated_preparation_minutes === null || $order->preparation_started_at === null) {
            return false;
        }

        if (in_array($order->order_status, [
            OrderStatus::ReadyForPickup,
            OrderStatus::PickedUp,
            OrderStatus::OnTheWay,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
            OrderStatus::Rejected,
        ], true)) {
            return false;
        }

        return $order->preparation_started_at
            ->copy()
            ->addMinutes($order->estimated_preparation_minutes)
            ->isPast();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pendingQuote(Order $order): ?array
    {
        $order->loadMissing('quotes.items');

        $quote = $order->quotes
            ->where('status', OrderQuoteStatus::Pending)
            ->sortByDesc('id')
            ->first();

        return $quote ? CustomOrderRequestData::quote($quote) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function customerTimeline(Order $order): array
    {
        $current = $order->order_status;

        if (in_array($current, [OrderStatus::Rejected, OrderStatus::Cancelled, OrderStatus::PendingCustomerConfirmation], true)) {
            return $order->statusHistory->map(fn ($entry): array => [
                'key' => $entry->status->value,
                'label' => $entry->status->customerLabel(),
                'done' => true,
                'current' => $entry->status === $current,
                'at' => $entry->created_at?->toIso8601String(),
                'notes' => $entry->notes,
            ])->values()->all();
        }

        $milestones = [
            ['key' => 'received', 'label' => 'Pedido recibido'],
            ['key' => 'preparing', 'label' => 'Preparando tu pedido'],
            ['key' => 'on_the_way', 'label' => 'Tu pedido va en camino'],
            ['key' => 'at_door', 'label' => 'Tu pedido ya está afuera de tu domicilio'],
            ['key' => 'delivered', 'label' => 'Entregado'],
        ];

        $currentIndex = self::customerTimelineIndex($current);
        $isDelivered = $current === OrderStatus::Delivered;

        return collect($milestones)->map(function (array $milestone, int $index) use ($currentIndex, $isDelivered, $order): array {
            $isCurrent = ! $isDelivered && $index === $currentIndex;
            $isDone = $isDelivered || $index < $currentIndex;

            return [
                'key' => $milestone['key'],
                'label' => $milestone['label'],
                'done' => $isDone,
                'current' => $isCurrent,
                'at' => self::customerTimelineTimestamp($order, $index),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function timeline(Order $order): array
    {
        $current = $order->order_status;

        return $order->statusHistory->map(fn ($entry): array => [
            'key' => $entry->status->value,
            'label' => $entry->status->label(),
            'done' => true,
            'current' => $entry->status === $current,
            'at' => $entry->created_at?->toIso8601String(),
            'notes' => $entry->notes,
        ])->values()->all();
    }

    private static function customerTimelineIndex(OrderStatus $status): int
    {
        return match ($status) {
            OrderStatus::PendingBusiness,
            OrderStatus::PendingPlatform => 0,
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::SearchingDriver,
            OrderStatus::ReadyForPickup,
            OrderStatus::DriverAssigned,
            OrderStatus::DriverAtBusiness => 1,
            OrderStatus::PickedUp => 2,
            OrderStatus::OnTheWay => 3,
            OrderStatus::Delivered => 4,
            default => 0,
        };
    }

    private static function customerTimelineTimestamp(Order $order, int $milestoneIndex): ?string
    {
        /** @var list<OrderStatus> $statuses */
        $statuses = match ($milestoneIndex) {
            0 => [OrderStatus::PendingBusiness, OrderStatus::PendingPlatform],
            1 => [
                OrderStatus::Accepted,
                OrderStatus::Preparing,
                OrderStatus::SearchingDriver,
                OrderStatus::ReadyForPickup,
                OrderStatus::DriverAssigned,
                OrderStatus::DriverAtBusiness,
            ],
            2 => [OrderStatus::PickedUp],
            3 => [OrderStatus::OnTheWay],
            4 => [OrderStatus::Delivered],
            default => [],
        };

        $entry = $order->statusHistory->first(
            fn ($historyEntry) => in_array($historyEntry->status, $statuses, true),
        );

        if ($milestoneIndex === 0 && $entry === null) {
            return $order->created_at?->toIso8601String();
        }

        return $entry?->created_at?->toIso8601String();
    }

    public static function optionDisplay(string $name, string $type, ?string $action): string
    {
        return match ($action) {
            'removed' => 'SIN '.mb_strtoupper($name),
            'added' => '+ '.mb_strtoupper($name),
            'selected' => mb_strtoupper($name),
            default => mb_strtoupper($name),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function listRow(Order $order): array
    {
        $data = self::transform($order);

        return [
            'id' => $data['id'],
            'order_number' => $data['order_number'],
            'order_status' => $data['order_status'],
            'order_status_label' => $data['order_status_label'],
            'business_status_label' => $data['business_status_label'],
            'total' => $data['total'],
            'created_at' => $data['created_at'],
            'restaurant' => $data['restaurant'],
            'customer' => $data['customer'],
            'driver' => $data['driver'],
            'is_custom' => $data['is_custom'],
            'is_platform_managed' => $data['is_platform_managed'],
            'operation_mode' => $data['operation_mode'],
            'type' => $data['type'],
            'estimated_preparation_minutes' => $data['estimated_preparation_minutes'],
            'is_active' => $data['is_active'],
            'items_summary' => collect($data['items'])
                ->map(fn (array $item): string => $item['quantity'].'x '.$item['product_name'])
                ->implode(', '),
        ];
    }
}
