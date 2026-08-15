<?php

namespace App\Services\Orders;

use App\Enums\CustomOrderRequestStatus;
use App\Enums\OrderAddressSource;
use App\Enums\OrderQuoteStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomOrderRequest;
use App\Models\User;
use App\Services\Geo\CoverageService;
use App\Services\Realtime\CustomOrderRealtimePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomOrderRequestService
{
    public function __construct(
        private readonly CustomOrderRealtimePublisher $realtime,
        private readonly CoverageService $coverage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Customer $customer, User $actor, array $payload): CustomOrderRequest
    {
        if ($customer->isBlocked()) {
            throw ValidationException::withMessages([
                'customer' => 'Tu cuenta no puede solicitar pedidos personalizados.',
            ]);
        }

        $max = (int) config('business.custom_orders.max_active_requests', 2);
        $active = CustomOrderRequest::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                CustomOrderRequestStatus::PendingReview->value,
                CustomOrderRequestStatus::Reviewing->value,
                CustomOrderRequestStatus::Quoted->value,
            ])
            ->count();

        if ($active >= $max) {
            throw ValidationException::withMessages([
                'customer' => "Solo puedes tener {$max} solicitudes personalizadas activas.",
            ]);
        }

        $delivery = $this->resolveDelivery($customer, $payload['delivery'] ?? []);
        $this->assertDeliveryCovered($payload['branch_id'] ?? null, $delivery);

        $request = CustomOrderRequest::query()->create([
            'customer_id' => $customer->id,
            'business_id' => $payload['business_id'] ?? null,
            'branch_id' => $payload['branch_id'] ?? null,
            'establishment_name' => $payload['establishment_name'] ?? null,
            'description' => $payload['description'],
            'customer_notes' => $payload['customer_notes'] ?? null,
            'delivery_address_id' => $delivery['delivery_address_id'],
            'temporary_delivery_address' => $delivery['temporary'],
            'merchant_address' => $payload['merchant_address'] ?? null,
            'merchant_phone' => $payload['merchant_phone'] ?? null,
            'merchant_latitude' => $payload['merchant_latitude'] ?? null,
            'merchant_longitude' => $payload['merchant_longitude'] ?? null,
            'merchant_formatted_address' => $payload['merchant_formatted_address'] ?? null,
            'merchant_place_id' => $payload['merchant_place_id'] ?? null,
            'merchant_reference' => $payload['merchant_reference'] ?? null,
            'status' => CustomOrderRequestStatus::PendingReview,
        ]);

        $this->realtime->requested($request->fresh(['customer.user']));

        return $request;
    }

    public function claim(CustomOrderRequest $request, User $admin): CustomOrderRequest
    {
        $updated = DB::transaction(function () use ($request, $admin): CustomOrderRequest {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CustomOrderRequestStatus::Reviewing
                && $locked->assigned_admin_user_id !== null
                && (int) $locked->assigned_admin_user_id !== (int) $admin->id) {
                throw ValidationException::withMessages([
                    'request' => 'Atendida por otro operador.',
                ]);
            }

            if (! in_array($locked->status, [
                CustomOrderRequestStatus::PendingReview,
                CustomOrderRequestStatus::Reviewing,
            ], true)) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud ya no está disponible para tomar.',
                ]);
            }

            $locked->forceFill([
                'status' => CustomOrderRequestStatus::Reviewing,
                'assigned_admin_user_id' => $admin->id,
            ])->save();

            return $locked->fresh(['customer.user', 'assignedAdmin']);
        });

        $this->realtime->requested($updated);

        return $updated;
    }

    public function reject(CustomOrderRequest $request, User $admin, ?string $notes = null): CustomOrderRequest
    {
        $updated = DB::transaction(function () use ($request, $admin, $notes): CustomOrderRequest {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud ya está cerrada.',
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
                'assigned_admin_user_id' => $locked->assigned_admin_user_id ?? $admin->id,
                'customer_notes' => $notes !== null && $notes !== ''
                    ? trim(($locked->customer_notes ? $locked->customer_notes."\n" : '').'Admin: '.$notes)
                    : $locked->customer_notes,
            ])->save();

            return $locked->fresh(['customer.user']);
        });

        $this->realtime->requested($updated);

        return $updated;
    }

    public function cancelByCustomer(CustomOrderRequest $request, Customer $customer): CustomOrderRequest
    {
        if ((int) $request->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'request' => 'Esta solicitud no te pertenece.',
            ]);
        }

        $updated = DB::transaction(function () use ($request): CustomOrderRequest {
            /** @var CustomOrderRequest $locked */
            $locked = CustomOrderRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->quoted_order_id !== null || $locked->status === CustomOrderRequestStatus::ConvertedToOrder) {
                throw ValidationException::withMessages([
                    'request' => 'El pedido ya fue creado. Cancélalo desde el pedido.',
                ]);
            }

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'request' => 'Esta solicitud ya está cerrada.',
                ]);
            }

            $locked->quotes()
                ->where('status', OrderQuoteStatus::Pending)
                ->update([
                    'status' => OrderQuoteStatus::Rejected,
                    'rejected_at' => now(),
                ]);

            $locked->forceFill([
                'status' => CustomOrderRequestStatus::Cancelled,
            ])->save();

            return $locked->fresh();
        });

        $this->realtime->requested($updated);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $delivery
     * @return array{delivery_address_id: int|null, temporary: array<string, mixed>|null}
     */
    private function resolveDelivery(Customer $customer, array $delivery): array
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
                'delivery_address_id' => $address->id,
                'temporary' => null,
            ];
        }

        foreach (['address_text', 'latitude', 'longitude'] as $field) {
            if (blank($delivery[$field] ?? null)) {
                throw ValidationException::withMessages([
                    "delivery.{$field}" => 'La dirección temporal es incompleta.',
                ]);
            }
        }

        return [
            'delivery_address_id' => null,
            'temporary' => [
                'address_text' => $delivery['address_text'],
                'formatted_address' => $delivery['formatted_address'] ?? null,
                'reference' => $delivery['reference'] ?? null,
                'latitude' => $delivery['latitude'],
                'longitude' => $delivery['longitude'],
                'place_id' => $delivery['place_id'] ?? null,
                'google_maps_url' => $delivery['google_maps_url'] ?? null,
            ],
        ];
    }

    /**
     * @param  array{delivery_address_id: int|null, temporary: array<string, mixed>|null}  $delivery
     */
    private function assertDeliveryCovered(mixed $branchId, array $delivery): void
    {
        $latitude = null;
        $longitude = null;
        $branch = null;

        if ($delivery['delivery_address_id'] !== null) {
            $address = CustomerAddress::query()->find($delivery['delivery_address_id']);
            $latitude = $address?->latitude;
            $longitude = $address?->longitude;
        } elseif (is_array($delivery['temporary'])) {
            $latitude = $delivery['temporary']['latitude'] ?? null;
            $longitude = $delivery['temporary']['longitude'] ?? null;
        }

        if ($latitude === null || $longitude === null) {
            throw ValidationException::withMessages([
                'delivery' => 'La dirección de entrega es incompleta.',
            ]);
        }

        if ($branchId !== null) {
            $branch = \App\Models\BusinessBranch::query()->find($branchId);
        }

        if (! $this->coverage->isOrderCovered($branch, (float) $latitude, (float) $longitude)) {
            throw ValidationException::withMessages([
                'delivery' => 'Por el momento no realizamos entregas en esta ubicación.',
            ]);
        }
    }
}
