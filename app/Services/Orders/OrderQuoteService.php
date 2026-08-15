<?php

namespace App\Services\Orders;

use App\Actions\Orders\ConvertCustomQuoteToOrder;
use App\Enums\CustomOrderRequestStatus;
use App\Enums\OrderQuoteStatus;
use App\Enums\OrderQuoteType;
use App\Enums\OrderStatus;
use App\Models\CustomOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderQuote;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;
use App\Services\Realtime\CustomOrderRealtimePublisher;
use App\Services\Realtime\OrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderQuoteService
{
    public function __construct(
        private readonly ConvertCustomQuoteToOrder $convertCustomQuote,
        private readonly OrderStateService $stateService,
        private readonly OrderFinancialService $financials,
        private readonly CustomOrderRealtimePublisher $customRealtime,
        private readonly OrderRealtimePublisher $orderRealtime,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function createCustomQuote(
        CustomOrderRequest $request,
        User $admin,
        array $items,
        ?string $serviceFee = null,
        string $discountAmount = '0.00',
    ): OrderQuote {
        $quote = DB::transaction(function () use ($request, $admin, $items, $serviceFee, $discountAmount): OrderQuote {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal() || $locked->quoted_order_id !== null) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud ya no acepta cotizaciones.',
                ]);
            }

            if ($locked->assigned_admin_user_id !== null
                && (int) $locked->assigned_admin_user_id !== (int) $admin->id) {
                throw ValidationException::withMessages([
                    'request' => 'Atendida por otro operador.',
                ]);
            }

            if (! in_array($locked->status, [
                CustomOrderRequestStatus::PendingReview,
                CustomOrderRequestStatus::Reviewing,
                CustomOrderRequestStatus::Quoted,
            ], true)) {
                throw ValidationException::withMessages([
                    'request' => 'No se puede cotizar esta solicitud.',
                ]);
            }

            $totals = $this->computeTotals($items, $serviceFee, $discountAmount);

            $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->update([
                    'status' => OrderQuoteStatus::Expired,
                ]);

            $quote = OrderQuote::query()->create([
                'order_id' => null,
                'custom_order_request_id' => $locked->id,
                'created_by_user_id' => $admin->id,
                'type' => OrderQuoteType::Custom,
                'subtotal' => $totals['subtotal'],
                'service_fee' => $totals['service_fee'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'status' => OrderQuoteStatus::Pending,
            ]);

            foreach ($totals['lines'] as $line) {
                $quote->items()->create($line);
            }

            $locked->forceFill([
                'status' => CustomOrderRequestStatus::Quoted,
                'assigned_admin_user_id' => $admin->id,
            ])->save();

            return $quote->fresh('items');
        });

        $this->customRealtime->quoteCreated($request->fresh(['customer.user', 'quotes']));

        return $quote;
    }

    public function acceptCustomQuote(CustomOrderRequest $request, User $customerUser): Order
    {
        $result = DB::transaction(function () use ($request, $customerUser): array {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->customer_id !== (int) $customerUser->customer?->id) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud no te pertenece.',
                ]);
            }

            if ($locked->quoted_order_id !== null) {
                $existing = Order::query()->findOrFail($locked->quoted_order_id);

                return ['order' => $existing, 'created' => false, 'request' => $locked];
            }

            /** @var OrderQuote|null $quote */
            $quote = $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->where('type', OrderQuoteType::Custom)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($quote === null || $locked->status !== CustomOrderRequestStatus::Quoted) {
                throw ValidationException::withMessages([
                    'quote' => 'No hay una cotización pendiente para aceptar.',
                ]);
            }

            $order = $this->convertCustomQuote->handle($locked, $quote, $customerUser);

            $quote->forceFill([
                'status' => OrderQuoteStatus::Accepted,
                'accepted_at' => now(),
                'order_id' => $order->id,
            ])->save();

            $locked->forceFill([
                'status' => CustomOrderRequestStatus::ConvertedToOrder,
                'quoted_order_id' => $order->id,
            ])->save();

            return ['order' => $order, 'created' => true, 'request' => $locked->fresh()];
        });

        if ($result['created']) {
            $this->customRealtime->quoteAccepted($result['request']);
            $this->customRealtime->converted($result['request']);
        }

        return $result['order'];
    }

    public function rejectCustomQuote(CustomOrderRequest $request, User $customerUser): CustomOrderRequest
    {
        $updated = DB::transaction(function () use ($request, $customerUser): CustomOrderRequest {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->customer_id !== (int) $customerUser->customer?->id) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud no te pertenece.',
                ]);
            }

            if ($locked->quoted_order_id !== null) {
                throw ValidationException::withMessages([
                    'request' => 'El pedido ya fue creado.',
                ]);
            }

            if ($locked->status !== CustomOrderRequestStatus::Quoted) {
                throw ValidationException::withMessages([
                    'quote' => 'No hay una cotización pendiente para rechazar.',
                ]);
            }

            $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->update([
                    'status' => OrderQuoteStatus::Rejected,
                    'rejected_at' => now(),
                ]);

            $locked->forceFill([
                'status' => CustomOrderRequestStatus::Rejected,
            ])->save();

            return $locked->fresh(['customer.user', 'quotes']);
        });

        $this->customRealtime->quoteAccepted($updated);

        return $updated;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function proposePriceAdjustment(
        Order $order,
        User $admin,
        array $items,
        ?string $serviceFee = null,
        string $discountAmount = '0.00',
    ): OrderQuote {
        $order->loadMissing('items');

        $result = DB::transaction(function () use ($order, $admin, $items, $serviceFee, $discountAmount): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPlatformManaged() || $locked->order_status !== OrderStatus::PendingPlatform) {
                throw ValidationException::withMessages([
                    'order' => 'Solo se puede ajustar el precio de un pedido pendiente de RIDE.',
                ]);
            }

            $totals = $this->computeTotals($items, $serviceFee ?? (string) $locked->service_fee, $discountAmount);

            $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->where('type', OrderQuoteType::PriceAdjustment)
                ->update([
                    'status' => OrderQuoteStatus::Expired,
                ]);

            $quote = OrderQuote::query()->create([
                'order_id' => $locked->id,
                'custom_order_request_id' => null,
                'created_by_user_id' => $admin->id,
                'type' => OrderQuoteType::PriceAdjustment,
                'subtotal' => $totals['subtotal'],
                'service_fee' => $totals['service_fee'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'status' => OrderQuoteStatus::Pending,
            ]);

            foreach ($totals['lines'] as $line) {
                $quote->items()->create($line);
            }

            $customerTotalChanged = bccomp($totals['total'], (string) $locked->total, 2) !== 0;

            if ($customerTotalChanged) {
                $previous = $locked->order_status;
                $this->stateService->assertCanTransition($locked->order_status, OrderStatus::PendingCustomerConfirmation);
                $locked->forceFill([
                    'order_status' => OrderStatus::PendingCustomerConfirmation,
                ])->save();
                $locked->statusHistory()->create([
                    'status' => OrderStatus::PendingCustomerConfirmation,
                    'changed_by_user_id' => $admin->id,
                    'notes' => 'Cambio de precio pendiente de confirmación del cliente',
                    'created_at' => now(),
                ]);

                return [
                    'quote' => $quote->fresh('items'),
                    'order' => $locked->fresh(['items', 'branch.business', 'customer.user']),
                    'previous' => $previous,
                    'needs_customer' => true,
                ];
            }

            $this->applyQuoteToOrderItems($locked, $quote->fresh('items'));
            $this->applyQuoteTotals($locked, $quote);
            $this->financials->refreshSnapshot($locked);

            $quote->forceFill([
                'status' => OrderQuoteStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            return [
                'quote' => $quote->fresh('items'),
                'order' => $locked->fresh(['items', 'financial', 'branch.business', 'customer.user']),
                'previous' => null,
                'needs_customer' => false,
            ];
        });

        if ($result['needs_customer'] && $result['previous'] instanceof OrderStatus) {
            $this->orderRealtime->statusChanged($result['order'], $result['previous']);
        }

        return $result['quote'];
    }

    public function acceptPriceAdjustment(Order $order, User $customerUser): Order
    {
        $result = DB::transaction(function () use ($order, $customerUser): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->customer_id !== (int) $customerUser->customer?->id) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido no te pertenece.',
                ]);
            }

            if ($locked->order_status !== OrderStatus::PendingCustomerConfirmation) {
                throw ValidationException::withMessages([
                    'order' => 'No hay un cambio de precio pendiente.',
                ]);
            }

            /** @var OrderQuote|null $quote */
            $quote = $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->where('type', OrderQuoteType::PriceAdjustment)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages([
                    'quote' => 'No hay una cotización pendiente.',
                ]);
            }

            $this->applyQuoteToOrderItems($locked, $quote->load('items'));
            $this->applyQuoteTotals($locked, $quote);
            $this->financials->refreshSnapshot($locked);

            $quote->forceFill([
                'status' => OrderQuoteStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $previous = $locked->order_status;
            $this->stateService->assertCanTransition($locked->order_status, OrderStatus::PendingPlatform);
            $locked->forceFill([
                'order_status' => OrderStatus::PendingPlatform,
            ])->save();
            $locked->statusHistory()->create([
                'status' => OrderStatus::PendingPlatform,
                'changed_by_user_id' => $customerUser->id,
                'notes' => 'Cliente aceptó el nuevo total',
                'created_at' => now(),
            ]);

            return [
                'order' => $locked->fresh([
                    'items',
                    'financial',
                    'payment',
                    'branch.business',
                    'customer.user',
                    'statusHistory',
                ]),
                'previous' => $previous,
            ];
        });

        $this->orderRealtime->statusChanged($result['order'], $result['previous']);

        return $result['order'];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{subtotal: string, service_fee: string, discount_amount: string, total: string, lines: list<array<string, mixed>>}
     */
    private function computeTotals(array $items, ?string $serviceFee, string $discountAmount): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Agrega al menos una partida.',
            ]);
        }

        $subtotal = '0.00';
        $lines = [];

        foreach ($items as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = number_format((float) ($item['quantity'] ?? 0), 2, '.', '');
            $unitPrice = number_format((float) ($item['unit_price'] ?? 0), 2, '.', '');

            if ($description === '') {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => 'La descripción es obligatoria.',
                ]);
            }

            if (bccomp($quantity, '0.01', 2) === -1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'La cantidad debe ser mayor a 0.',
                ]);
            }

            if (bccomp($unitPrice, '0.00', 2) === -1) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => 'El precio no puede ser negativo.',
                ]);
            }

            $lineSubtotal = bcmul($quantity, $unitPrice, 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);

            $acquisition = $item['acquisition_cost'] ?? null;

            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'acquisition_cost' => $acquisition === null || $acquisition === ''
                    ? null
                    : number_format((float) $acquisition, 2, '.', ''),
                'notes' => $item['notes'] ?? null,
            ];
        }

        $resolvedService = $serviceFee === null || $serviceFee === ''
            ? number_format((float) config('business.orders.service_fee', 50), 2, '.', '')
            : number_format((float) $serviceFee, 2, '.', '');

        $discount = number_format((float) $discountAmount, 2, '.', '');

        if (bccomp($discount, $subtotal, 2) === 1) {
            $discount = $subtotal;
        }

        $afterDiscount = bcsub($subtotal, $discount, 2);
        $total = bcadd($afterDiscount, $resolvedService, 2);

        return [
            'subtotal' => $subtotal,
            'service_fee' => $resolvedService,
            'discount_amount' => $discount,
            'total' => $total,
            'lines' => $lines,
        ];
    }

    private function applyQuoteTotals(Order $order, OrderQuote $quote): void
    {
        $subtotal = (string) $quote->subtotal;
        $discount = (string) $quote->discount_amount;

        $order->forceFill([
            'subtotal_before_discount' => $subtotal,
            'discount_total' => $discount,
            'subtotal_after_discount' => bcsub($subtotal, $discount, 2),
            'service_fee' => $quote->service_fee,
            'total' => $quote->total,
        ])->save();
    }

    private function applyQuoteToOrderItems(Order $order, OrderQuote $quote): void
    {
        $order->loadMissing('items');
        $quoteItems = $quote->items->values();

        foreach ($order->items->values() as $index => $item) {
            $line = $quoteItems->get($index);

            if ($line === null) {
                continue;
            }

            /** @var OrderItem $item */
            $item->forceFill([
                'product_name' => $line->description,
                'quantity' => $line->quantity,
                'unit_list_price' => $line->unit_price,
                'unit_final_price' => $line->unit_price,
                'unit_acquisition_cost' => $line->acquisition_cost,
                'subtotal' => $line->subtotal,
                'notes' => $line->notes,
            ])->save();
        }
    }
}
