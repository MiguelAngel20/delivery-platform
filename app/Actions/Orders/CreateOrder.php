<?php

namespace App\Actions\Orders;

use App\Enums\BusinessOperationMode;
use App\Enums\OptionSelectionAction;
use App\Enums\OrderAddressSource;
use App\Enums\OrderAddressType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductOptionGroupType;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Services\BusinessBranchContext;
use App\Services\Finance\OrderFinancialService;
use App\Services\Geo\OrderLogisticsService;
use App\Services\Orders\OrderNumberGenerator;
use App\Services\PricingEngine;
use App\Services\Realtime\OrderRealtimePublisher;
use App\Support\GoogleMapsUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateOrder
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly PricingEngine $pricingEngine,
        private readonly OrderFinancialService $financials,
        private readonly OrderRealtimePublisher $realtime,
        private readonly OrderLogisticsService $logistics,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Customer $customer, User $actor, array $payload): Order
    {
        $order = DB::transaction(function () use ($customer, $actor, $payload): Order {
            $branch = BusinessBranch::query()
                ->with('business')
                ->whereKey($payload['branch_id'])
                ->firstOrFail();

            if ($branch->business === null || ! $branch->business->operation_mode->canAcceptOrders()) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Este establecimiento no acepta pedidos.',
                ]);
            }

            $itemsInput = $payload['items'] ?? [];

            if ($itemsInput === []) {
                throw ValidationException::withMessages([
                    'items' => 'El carrito está vacío.',
                ]);
            }

            $builtItems = [];
            $productsForDiscount = [];
            $subtotalBeforeDiscount = '0.00';

            foreach ($itemsInput as $index => $itemInput) {
                $built = $this->buildItem($branch, $itemInput, $index);
                $builtItems[] = $built;
                $subtotalBeforeDiscount = bcadd($subtotalBeforeDiscount, $built['subtotal'], 2);

                for ($i = 0; $i < (int) ceil((float) $built['quantity']); $i++) {
                    $productsForDiscount[] = $built['product'];
                }
            }

            $quote = $this->pricingEngine->quoteForCash(
                $productsForDiscount,
                new BusinessBranchContext($branch->id),
            );

            $discountTotal = $branch->business->operation_mode === BusinessOperationMode::PlatformOperated
                ? $quote['discount']
                : '0.00';

            if (bccomp($discountTotal, $subtotalBeforeDiscount, 2) === 1) {
                $discountTotal = $subtotalBeforeDiscount;
            }

            $subtotalAfterDiscount = bcsub($subtotalBeforeDiscount, $discountTotal, 2);
            $serviceFee = number_format((float) config('business.orders.service_fee', 50), 2, '.', '');

            $deliveryPayload = $payload['delivery'] ?? [];
            $deliveryCoords = $this->resolveDeliveryCoordinates($customer, $deliveryPayload);

            $logisticsSnapshot = $this->logistics->assertAndMeasure(
                $branch,
                $deliveryCoords['latitude'],
                $deliveryCoords['longitude'],
            );

            $deliveryFee = $logisticsSnapshot['delivery_fee'];
            $total = bcadd(bcadd($subtotalAfterDiscount, $serviceFee, 2), $deliveryFee, 2);

            $initialStatus = $branch->business->operation_mode === BusinessOperationMode::PlatformOperated
                ? OrderStatus::PendingPlatform
                : OrderStatus::PendingBusiness;

            $order = Order::query()->create([
                'order_number' => $this->orderNumbers->next(),
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'created_by_user_id' => $actor->id,
                'type' => OrderType::Business,
                'operation_mode' => $branch->business->operation_mode,
                'order_status' => $initialStatus,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => PaymentMethod::Cash,
                'subtotal_before_discount' => $subtotalBeforeDiscount,
                'discount_total' => $discountTotal,
                'subtotal_after_discount' => $subtotalAfterDiscount,
                'service_fee' => $serviceFee,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($builtItems as $built) {
                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $built['product']->id,
                    'product_name' => $built['product']->name,
                    'quantity' => $built['quantity'],
                    'unit_list_price' => $built['unit_list_price'],
                    'unit_discount' => '0.00',
                    'unit_final_price' => $built['unit_final_price'],
                    'unit_acquisition_cost' => $built['unit_acquisition_cost'],
                    'subtotal' => $built['subtotal'],
                    'notes' => $built['notes'],
                ]);

                foreach ($built['options'] as $optionRow) {
                    OrderItemOption::query()->create([
                        ...$optionRow,
                        'order_item_id' => $orderItem->id,
                        'created_at' => now(),
                    ]);
                }
            }

            $this->storeAddresses($order, $branch, $customer, $deliveryPayload);
            $this->logistics->storeSnapshot($order, $logisticsSnapshot);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $initialStatus,
                'changed_by_user_id' => $actor->id,
                'notes' => 'Pedido creado',
                'created_at' => now(),
            ]);

            $this->financials->createSnapshot($order);

            return $order->fresh([
                'items.options',
                'addresses',
                'statusHistory',
                'branch.business',
                'customer.user',
                'financial',
                'payment',
                'logistics',
            ]);
        });

        $this->realtime->orderCreated($order);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $itemInput
     * @return array<string, mixed>
     */
    private function buildItem(BusinessBranch $branch, array $itemInput, int $index): array
    {
        $product = Product::query()
            ->with(['currentPrice', 'optionGroups.options'])
            ->whereKey($itemInput['product_id'] ?? null)
            ->where('branch_id', $branch->id)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => 'El producto no pertenece a la sucursal del pedido.',
            ]);
        }

        if (! $product->is_active || ! $product->is_available) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => "El producto {$product->name} no está disponible.",
            ]);
        }

        $listPrice = $product->currentPrice?->list_price;

        if ($listPrice === null) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => "El producto {$product->name} no tiene precio activo.",
            ]);
        }

        $quantity = (string) ($itemInput['quantity'] ?? 1);

        if (bccomp($quantity, '0.01', 2) === -1) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        $selectedOptions = $itemInput['selected_options'] ?? [];
        $optionRows = $this->resolveOptions($product, $selectedOptions, $index);

        $modifiers = '0.00';
        foreach ($optionRows as $optionRow) {
            if ($optionRow['selection_action'] === OptionSelectionAction::Added
                || $optionRow['selection_action'] === OptionSelectionAction::Selected) {
                $modifiers = bcadd($modifiers, (string) $optionRow['price_modifier'], 2);
            }
        }

        $unitFinal = bcadd((string) $listPrice, $modifiers, 2);
        $subtotal = bcmul($unitFinal, $quantity, 2);

        $notes = $itemInput['special_instructions'] ?? $itemInput['notes'] ?? null;

        if ($notes !== null && $notes !== '' && ! $product->allow_special_instructions) {
            throw ValidationException::withMessages([
                "items.{$index}.special_instructions" => 'Este producto no permite instrucciones especiales.',
            ]);
        }

        return [
            'product' => $product,
            'quantity' => $quantity,
            'unit_list_price' => (string) $listPrice,
            'unit_final_price' => $unitFinal,
            'unit_acquisition_cost' => $product->currentPrice?->acquisition_cost !== null
                ? (string) $product->currentPrice->acquisition_cost
                : null,
            'subtotal' => $subtotal,
            'notes' => $notes ?: null,
            'options' => $optionRows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $selectedOptions
     * @return list<array<string, mixed>>
     */
    private function resolveOptions(Product $product, array $selectedOptions, int $itemIndex): array
    {
        $groups = $product->optionGroups->where('is_active', true)->keyBy('id');
        $rows = [];
        $countsByGroup = [];

        foreach ($selectedOptions as $optionIndex => $selection) {
            $optionId = (int) ($selection['option_id'] ?? 0);
            $action = OptionSelectionAction::tryFrom((string) ($selection['action'] ?? ''));

            $option = ProductOption::query()
                ->with('group')
                ->whereKey($optionId)
                ->first();

            if ($option === null || $option->group === null || $option->group->product_id !== $product->id) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.selected_options.{$optionIndex}" => 'La opción no pertenece al producto.',
                ]);
            }

            if (! $groups->has($option->group->id) || ! $option->is_available) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.selected_options.{$optionIndex}" => 'La opción no está disponible.',
                ]);
            }

            $group = $option->group;

            // Storefront historically sent "selected" for addon extras; coerce to "added".
            if (
                $action === OptionSelectionAction::Selected
                && $group->type === ProductOptionGroupType::Addon
            ) {
                $action = OptionSelectionAction::Added;
            }

            $expectedAction = match ($group->type) {
                ProductOptionGroupType::Removable => OptionSelectionAction::Removed,
                ProductOptionGroupType::Addon => OptionSelectionAction::Added,
                ProductOptionGroupType::Choice => OptionSelectionAction::Selected,
            };

            if ($action !== $expectedAction) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.selected_options.{$optionIndex}" => 'Acción de opción inválida.',
                ]);
            }

            if ($action !== OptionSelectionAction::Removed) {
                $countsByGroup[$group->id] = ($countsByGroup[$group->id] ?? 0) + 1;
            }

            $rows[] = [
                'product_option_id' => $option->id,
                'option_name' => $option->name,
                'option_type' => $group->type,
                'price_modifier' => $action === OptionSelectionAction::Removed
                    ? '0.00'
                    : (string) $option->price_modifier,
                'selection_action' => $action,
            ];
        }

        foreach ($groups as $group) {
            $count = $countsByGroup[$group->id] ?? 0;

            if ($group->type === ProductOptionGroupType::Choice || $group->is_required) {
                if ($count < $group->min_selection || $count > $group->max_selection) {
                    throw ValidationException::withMessages([
                        "items.{$itemIndex}.selected_options" => "Selección inválida para {$group->name}.",
                    ]);
                }
            } elseif ($count > $group->max_selection) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.selected_options" => "Selección inválida para {$group->name}.",
                ]);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $delivery
     * @return array{latitude: float, longitude: float}
     */
    private function resolveDeliveryCoordinates(Customer $customer, array $delivery): array
    {
        $source = OrderAddressSource::tryFrom((string) ($delivery['source'] ?? ''))
            ?? OrderAddressSource::Temporary;

        if ($source === OrderAddressSource::SavedAddress) {
            $address = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->whereKey($delivery['customer_address_id'] ?? null)
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages([
                    'delivery.customer_address_id' => 'La dirección guardada no es válida.',
                ]);
            }

            return [
                'latitude' => (float) $address->latitude,
                'longitude' => (float) $address->longitude,
            ];
        }

        foreach (['latitude', 'longitude'] as $field) {
            if (! is_numeric($delivery[$field] ?? null)) {
                throw ValidationException::withMessages([
                    "delivery.{$field}" => 'La dirección temporal es incompleta.',
                ]);
            }
        }

        return [
            'latitude' => (float) $delivery['latitude'],
            'longitude' => (float) $delivery['longitude'],
        ];
    }

    /**
     * @param  array<string, mixed>  $delivery
     */
    private function storeAddresses(
        Order $order,
        BusinessBranch $branch,
        Customer $customer,
        array $delivery,
    ): void {
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

        $source = OrderAddressSource::tryFrom((string) ($delivery['source'] ?? ''))
            ?? OrderAddressSource::Temporary;

        if ($source === OrderAddressSource::SavedAddress) {
            $address = CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->whereKey($delivery['customer_address_id'] ?? null)
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages([
                    'delivery.customer_address_id' => 'La dirección guardada no es válida.',
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

        foreach (['address_text', 'latitude', 'longitude'] as $field) {
            if (blank($delivery[$field] ?? null)) {
                throw ValidationException::withMessages([
                    "delivery.{$field}" => 'La dirección temporal es incompleta.',
                ]);
            }
        }

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => OrderAddressType::Delivery,
            'source' => OrderAddressSource::Temporary,
            'address_text' => $delivery['address_text'],
            'formatted_address' => $delivery['formatted_address'] ?? null,
            'reference' => $delivery['reference'] ?? null,
            'latitude' => $delivery['latitude'],
            'longitude' => $delivery['longitude'],
            'place_id' => $delivery['place_id'] ?? null,
            'google_maps_url' => GoogleMapsUrl::resolve(
                $delivery['google_maps_url'] ?? null,
                $delivery['latitude'],
                $delivery['longitude'],
            ),
            'created_at' => now(),
        ]);
    }
}
