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
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
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

            $this->addresses->create($customer, [
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
            ]);

            $this->notificationPreferences->forUser($user);

            return $user;
        });
    }
}
