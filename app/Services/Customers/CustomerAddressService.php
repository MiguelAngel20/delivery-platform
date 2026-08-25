<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerAddressService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Customer $customer, array $data): CustomerAddress
    {
        try {
            return DB::transaction(function () use ($customer, $data): CustomerAddress {
                $locked = Customer::query()
                    ->whereKey($customer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeCount = $locked->addresses()
                    ->where('is_active', true)
                    ->count();

                $max = (int) config('business.orders.max_customer_addresses', 4);

                if ($activeCount >= $max) {
                    throw ValidationException::withMessages([
                        'label' => "Solo puedes tener hasta {$max} direcciones activas.",
                    ]);
                }

                $isDefault = (bool) ($data['is_default'] ?? false);

                if ($activeCount === 0) {
                    $isDefault = true;
                }

                if ($isDefault) {
                    $locked->addresses()
                        ->where('is_active', true)
                        ->update(['is_default' => false]);
                }

                return $locked->addresses()->create([
                    ...$data,
                    'is_default' => $isDefault,
                    'is_active' => true,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'is_default' => 'Ya existe una dirección predeterminada. Intenta de nuevo.',
            ]);
        }
    }

    public function makeDefault(CustomerAddress $address): CustomerAddress
    {
        try {
            return DB::transaction(function () use ($address): CustomerAddress {
                $lockedCustomer = Customer::query()
                    ->whereKey($address->customer_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedAddress = CustomerAddress::query()
                    ->whereKey($address->id)
                    ->where('customer_id', $lockedCustomer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedAddress->is_active || $lockedAddress->trashed()) {
                    throw ValidationException::withMessages([
                        'address' => 'La dirección no está activa.',
                    ]);
                }

                $lockedCustomer->addresses()
                    ->where('is_active', true)
                    ->whereKeyNot($lockedAddress->id)
                    ->update(['is_default' => false]);

                $lockedAddress->forceFill(['is_default' => true])->save();

                return $lockedAddress->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'is_default' => 'No se pudo actualizar la dirección predeterminada. Intenta de nuevo.',
            ]);
        }
    }

    public function delete(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $lockedCustomer = Customer::query()
                ->whereKey($address->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAddress = CustomerAddress::query()
                ->whereKey($address->id)
                ->where('customer_id', $lockedCustomer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $lockedAddress->is_default;

            $lockedAddress->forceFill([
                'is_default' => false,
                'is_active' => false,
            ])->save();

            $lockedAddress->delete();

            if (! $wasDefault) {
                return;
            }

            $next = $lockedCustomer->addresses()
                ->where('is_active', true)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($next !== null) {
                $next->forceFill(['is_default' => true])->save();
            }
        });
    }
}
