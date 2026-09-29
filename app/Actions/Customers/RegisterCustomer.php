<?php

namespace App\Actions\Customers;

use App\Enums\CustomerTrustLevel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customers\CustomerAddressService;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\GoogleMapsUrl;
use Illuminate\Support\Facades\DB;

class RegisterCustomer
{
    public function __construct(
        private readonly NotificationPreferenceService $notificationPreferences,
        private readonly CustomerAddressService $addresses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $existing = User::query()->where('phone', $data['phone'])->first();

            if ($existing instanceof User && $this->canResume($existing)) {
                $existing->forceFill([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => null,
                    'password' => $data['password'],
                    'phone_verified_at' => null,
                    'email_verified_at' => null,
                ])->save();

                $customer = $existing->customer;

                if ($customer === null) {
                    $customer = Customer::query()->create([
                        'user_id' => $existing->id,
                        'trust_level' => CustomerTrustLevel::New,
                    ]);
                }

                $this->saveAddress($customer, $data);
                $this->notificationPreferences->forUser($existing);

                return $existing;
            }

            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::Customer,
                'status' => UserStatus::Active,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'must_change_password' => false,
            ]);

            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'trust_level' => CustomerTrustLevel::New,
            ]);

            $this->saveAddress($customer, $data);

            $this->notificationPreferences->forUser($user);

            return $user;
        });
    }

    private function canResume(User $user): bool
    {
        return $user->role === UserRole::Customer
            && $user->email === null
            && $user->email_verified_at === null
            && $user->phone_verified_at === null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAddress(Customer $customer, array $data): void
    {
        $attributes = [
            'label' => $data['address_label'] ?? 'Casa',
            'address_text' => $data['address_text'],
            'formatted_address' => $data['formatted_address'] ?? null,
            'reference' => $data['reference'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'place_id' => $data['place_id'] ?? null,
            'google_maps_url' => GoogleMapsUrl::resolve(
                $data['google_maps_url'] ?? null,
                $data['latitude'],
                $data['longitude'],
            ),
            'is_default' => true,
            'is_active' => true,
        ];

        $address = $customer->addresses()->where('is_default', true)->first();

        if ($address !== null) {
            $address->update($attributes);

            return;
        }

        $this->addresses->create($customer, $attributes);
    }
}
