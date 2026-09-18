<?php

namespace App\Actions\Orders;

use App\Enums\BusinessOperationMode;
use App\Enums\OrderAddressSource;
use App\Enums\OrderAddressType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomOrderRequest;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderQuote;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Finance\OrderFinancialService;
use App\Services\Geo\OrderLogisticsService;
use App\Services\Loyalty\CustomerLoyaltyService;
use App\Services\Orders\OrderNumberGenerator;
use App\Services\Platform\PlatformActivitySuspensionService;
use App\Services\Realtime\OrderRealtimePublisher;
use App\Support\GeoPoint;
use App\Support\GoogleMapsUrl;
use Illuminate\Validation\ValidationException;

final class ConvertCustomQuoteToOrder
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly OrderFinancialService $financials,
        private readonly OrderRealtimePublisher $realtime,
        private readonly OrderLogisticsService $logistics,
        private readonly CustomerLoyaltyService $loyalty,
        private readonly PlatformActivitySuspensionService $activitySuspension,
    ) {}

    public function handle(CustomOrderRequest $request, OrderQuote $quote, User $actor): Order
    {
        if ($request->quoted_order_id !== null) {
            $existing = Order::query()->find($request->quoted_order_id);

            if ($existing !== null) {
                return $existing->load([
                    'items',
                    'addresses',
                    'financial',
                    'payment',
                    'branch.business',
                    'customer.user',
                ]);
            }
        }

        $this->activitySuspension->assertOrderingAllowed();

        $quote->loadMissing('items');

        if ($quote->items->isEmpty()) {
            throw ValidationException::withMessages([
                'quote' => 'La cotización no tiene partidas.',
            ]);
        }

        $branch = $request->branch_id !== null
            ? BusinessBranch::query()->with('business')->find($request->branch_id)
            : null;

        $subtotal = (string) $quote->subtotal;
        $discount = (string) $quote->discount_amount;
        $afterDiscount = bcsub($subtotal, $discount, 2);
        $serviceFee = (string) $quote->service_fee;

        $deliveryCoords = $this->resolveDeliveryCoordinates($request);
        $pickupPoint = $this->resolvePickupPoint($branch, $request);

        $logisticsSnapshot = $this->logistics->assertAndMeasure(
            $branch,
            $deliveryCoords['latitude'],
            $deliveryCoords['longitude'],
            $pickupPoint,
        );

        $deliveryFee = $logisticsSnapshot['delivery_fee'];
        $total = bcadd(bcadd($afterDiscount, $serviceFee, 2), $deliveryFee, 2);

        $merchantName = $request->establishment_name
            ?: $branch?->business?->name;
        $merchantAddress = $request->merchant_address
            ?: $branch?->address_text
            ?: $request->merchant_formatted_address;

        $order = Order::query()->create([
            'order_number' => $this->orderNumbers->next(),
            'customer_id' => $request->customer_id,
            'branch_id' => $branch?->id,
            'created_by_user_id' => $actor->id,
            'type' => OrderType::Custom,
            'operation_mode' => BusinessOperationMode::PlatformOperated,
            'order_status' => OrderStatus::PendingPlatform,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Cash,
            'subtotal_before_discount' => $subtotal,
            'discount_total' => $discount,
            'subtotal_after_discount' => $afterDiscount,
            'service_fee' => $serviceFee,
            'service_fee_discount' => '0.00',
            'loyalty_reward_type' => null,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'notes' => $this->composeNotes($request),
            'merchant_name_snapshot' => $merchantName,
            'merchant_address_snapshot' => $merchantAddress,
            'merchant_phone_snapshot' => $request->merchant_phone,
        ]);

        $customer = Customer::query()->findOrFail($request->customer_id);
        $loyalty = $this->loyalty->applyToNewOrder($customer, $order, $serviceFee);

        if (bccomp($loyalty['service_fee_discount'], '0', 2) === 1) {
            $netServiceFee = $this->loyalty->netServiceFee($serviceFee, $loyalty['service_fee_discount']);
            $total = bcadd(bcadd($afterDiscount, $netServiceFee, 2), $deliveryFee, 2);

            $order->forceFill([
                'service_fee_discount' => $loyalty['service_fee_discount'],
                'loyalty_reward_type' => $loyalty['loyalty_reward_type'],
                'total' => $total,
            ])->save();
        }

        foreach ($quote->items as $line) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => null,
                'product_name' => $line->description,
                'quantity' => $line->quantity,
                'unit_list_price' => $line->unit_price,
                'unit_discount' => '0.00',
                'unit_final_price' => $line->unit_price,
                'unit_acquisition_cost' => $line->acquisition_cost,
                'subtotal' => $line->subtotal,
                'notes' => $line->notes,
            ]);
        }

        $this->storePickupAddress($order, $branch, $request, $merchantAddress);
        $this->storeDeliveryAddress($order, $request);
        $this->logistics->storeSnapshot($order, $logisticsSnapshot);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'status' => OrderStatus::PendingPlatform,
            'changed_by_user_id' => $actor->id,
            'notes' => 'Pedido personalizado convertido desde cotización',
            'created_at' => now(),
        ]);

        $this->financials->createSnapshot($order);

        $fresh = $order->fresh([
            'items',
            'addresses',
            'statusHistory',
            'branch.business',
            'customer.user',
            'financial',
            'payment',
        ]);

        $this->realtime->orderCreated($fresh);

        return $fresh;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function resolveDeliveryCoordinates(CustomOrderRequest $request): array
    {
        if ($request->delivery_address_id !== null) {
            $address = $request->deliveryAddress;

            if ($address === null) {
                throw ValidationException::withMessages([
                    'delivery' => 'La dirección de entrega ya no es válida.',
                ]);
            }

            return [
                'latitude' => (float) $address->latitude,
                'longitude' => (float) $address->longitude,
            ];
        }

        $temporary = $request->temporary_delivery_address ?? [];

        foreach (['latitude', 'longitude'] as $field) {
            if (! is_numeric($temporary[$field] ?? null)) {
                throw ValidationException::withMessages([
                    'delivery' => 'La dirección de entrega es incompleta.',
                ]);
            }
        }

        return [
            'latitude' => (float) $temporary['latitude'],
            'longitude' => (float) $temporary['longitude'],
        ];
    }

    private function resolvePickupPoint(?BusinessBranch $branch, CustomOrderRequest $request): GeoPoint
    {
        if ($branch !== null) {
            return GeoPoint::make($branch->latitude, $branch->longitude);
        }

        if ($request->merchant_latitude === null || $request->merchant_longitude === null) {
            throw ValidationException::withMessages([
                'pickup' => 'Captura la ubicación de recogida del comercio externo antes de convertir.',
            ]);
        }

        return GeoPoint::make($request->merchant_latitude, $request->merchant_longitude);
    }

    private function composeNotes(CustomOrderRequest $request): string
    {
        $parts = array_filter([
            'Pedido personalizado: '.$request->description,
            filled($request->customer_notes) ? 'Notas: '.$request->customer_notes : null,
        ]);

        return implode("\n", $parts);
    }

    private function storePickupAddress(
        Order $order,
        ?BusinessBranch $branch,
        CustomOrderRequest $request,
        ?string $merchantAddress,
    ): void {
        if ($branch !== null) {
            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => OrderAddressType::Pickup,
                'source' => OrderAddressSource::Business,
                'address_text' => $branch->address_text,
                'formatted_address' => $branch->formatted_address,
                'reference' => $branch->reference,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'place_id' => $branch->place_id,
                'google_maps_url' => GoogleMapsUrl::resolve(
                    $branch->google_maps_url,
                    $branch->latitude,
                    $branch->longitude,
                ),
                'created_at' => now(),
            ]);

            return;
        }

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => OrderAddressType::Pickup,
            'source' => OrderAddressSource::Temporary,
            'address_text' => $merchantAddress ?: ($order->merchant_name_snapshot ?: 'Establecimiento'),
            'formatted_address' => $request->merchant_formatted_address,
            'reference' => $request->merchant_reference,
            'latitude' => $request->merchant_latitude,
            'longitude' => $request->merchant_longitude,
            'place_id' => $request->merchant_place_id,
            'google_maps_url' => GoogleMapsUrl::resolve(
                null,
                $request->merchant_latitude,
                $request->merchant_longitude,
            ),
            'created_at' => now(),
        ]);
    }

    private function storeDeliveryAddress(Order $order, CustomOrderRequest $request): void
    {
        if ($request->delivery_address_id !== null) {
            $address = $request->deliveryAddress;

            if ($address === null) {
                throw ValidationException::withMessages([
                    'delivery' => 'La dirección de entrega ya no es válida.',
                ]);
            }

            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => OrderAddressType::Delivery,
                'source' => OrderAddressSource::SavedAddress,
                'address_text' => $address->address_text,
                'formatted_address' => $address->formatted_address,
                'reference' => $address->reference,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'place_id' => $address->place_id,
                'google_maps_url' => GoogleMapsUrl::resolve(
                    $address->google_maps_url,
                    $address->latitude,
                    $address->longitude,
                ),
                'created_at' => now(),
            ]);

            return;
        }

        $temporary = $request->temporary_delivery_address ?? [];

        foreach (['address_text', 'latitude', 'longitude'] as $field) {
            if (blank($temporary[$field] ?? null)) {
                throw ValidationException::withMessages([
                    'delivery' => 'La dirección de entrega es incompleta.',
                ]);
            }
        }

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => OrderAddressType::Delivery,
            'source' => OrderAddressSource::Temporary,
            'address_text' => $temporary['address_text'],
            'formatted_address' => $temporary['formatted_address'] ?? null,
            'reference' => $temporary['reference'] ?? null,
            'latitude' => $temporary['latitude'],
            'longitude' => $temporary['longitude'],
            'place_id' => $temporary['place_id'] ?? null,
            'google_maps_url' => GoogleMapsUrl::resolve(
                $temporary['google_maps_url'] ?? null,
                $temporary['latitude'],
                $temporary['longitude'],
            ),
            'created_at' => now(),
        ]);
    }
}
