<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\CustomerTrustLevel;
use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeders temporales de desarrollo para identidad y dominio base.
 * Contraseña común: password
 */
class DemoDomainSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->upsertUser(
            email: 'admin@ride.test',
            firstName: 'Admin',
            lastName: 'RIDE',
            phone: '+50255550001',
            role: UserRole::SystemAdmin,
        );

        $businessAdmin = $this->upsertUser(
            email: 'business@ride.test',
            firstName: 'Business',
            lastName: 'Admin',
            phone: '+50255550002',
            role: UserRole::BusinessAdmin,
        );

        $businessEmployee = $this->upsertUser(
            email: 'employee@ride.test',
            firstName: 'Business',
            lastName: 'Employee',
            phone: '+50255550003',
            role: UserRole::BusinessEmployee,
        );

        $driverUser = $this->upsertUser(
            email: 'driver@ride.test',
            firstName: 'Driver',
            lastName: 'RIDE',
            phone: '+50255550004',
            role: UserRole::Driver,
        );

        $miguelUser = $this->upsertUser(
            email: 'miguel@ride.test',
            firstName: 'Miguel',
            lastName: 'Rivera',
            phone: '+50255550014',
            role: UserRole::Driver,
        );

        $customerUser = $this->upsertUser(
            email: 'customer@ride.test',
            firstName: 'Customer',
            lastName: 'RIDE',
            phone: '+50255550005',
            role: UserRole::Customer,
        );

        $this->upsertUser(
            email: 'suspended@ride.test',
            firstName: 'Suspended',
            lastName: 'RIDE',
            phone: '+50255550006',
            role: UserRole::Customer,
            status: UserStatus::Suspended,
        );

        $customer = Customer::query()->updateOrCreate(
            ['user_id' => $customerUser->id],
            ['trust_level' => CustomerTrustLevel::New],
        );

        CustomerAddress::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'label' => 'Casa',
            ],
            [
                'address_text' => '12 Calle 4-56 Zona 10',
                'reference' => 'Portón negro',
                'latitude' => '14.5995000',
                'longitude' => '-90.5143000',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $driver = Driver::query()->updateOrCreate(
            ['user_id' => $driverUser->id],
            [
                'approval_status' => DriverApprovalStatus::Approved,
                'availability_status' => DriverAvailabilityStatus::Available,
                'driver_scope' => DriverScope::Platform,
                'payment_model' => DriverPaymentModel::PlatformRate,
                'approved_by_user_id' => $admin->id,
                'approved_at' => now(),
            ],
        );

        $miguel = Driver::query()->updateOrCreate(
            ['user_id' => $miguelUser->id],
            [
                'approval_status' => DriverApprovalStatus::Approved,
                'availability_status' => DriverAvailabilityStatus::Available,
                'driver_scope' => DriverScope::BusinessOnly,
                'payment_model' => DriverPaymentModel::BusinessRate,
                'approved_by_user_id' => $admin->id,
                'approved_at' => now(),
            ],
        );

        $business = Business::query()->updateOrCreate(
            ['slug' => 'pollo-guero-demo'],
            [
                'name' => 'Pollo Güero Demo',
                'description' => 'Empresa demo para desarrollo local.',
                'business_type' => 'Restaurante',
                'operation_mode' => BusinessOperationMode::Partner,
                'delivery_mode' => BusinessDeliveryMode::Hybrid,
                'status' => BusinessStatus::Active,
                'phone' => '+50255551000',
                'email' => 'contacto@polloguero.test',
                'created_by_user_id' => $admin->id,
                'approved_by_user_id' => $admin->id,
                'approved_at' => now(),
            ],
        );

        $branch = BusinessBranch::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'name' => 'Sucursal Centro',
            ],
            [
                'phone' => '+50255551001',
                'address_text' => 'Av. Central 120, Zona 1',
                'reference' => 'Frente al parque',
                'latitude' => '14.6349000',
                'longitude' => '-90.5069000',
                'google_maps_url' => 'https://maps.google.com/?q=14.6349,-90.5069',
                'status' => BranchStatus::Active,
            ],
        );

        BusinessUser::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'user_id' => $businessAdmin->id,
            ],
            [
                'role' => BusinessUserRole::BusinessAdmin,
                'status' => BusinessUserStatus::Active,
            ],
        )->branches()->sync([$branch->id]);

        $employeeMembership = BusinessUser::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'user_id' => $businessEmployee->id,
            ],
            [
                'role' => BusinessUserRole::BusinessEmployee,
                'status' => BusinessUserStatus::Active,
            ],
        );

        $employeeMembership->branches()->sync([$branch->id]);

        $business->limits()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'max_branches' => (int) config('business.defaults.max_branches'),
                'max_business_admins' => (int) config('business.defaults.max_business_admins'),
                'max_employees_per_branch' => (int) config('business.defaults.max_employees_per_branch'),
            ],
        );

        $driver->businesses()->syncWithoutDetaching([$business->id]);
        $miguel->businesses()->syncWithoutDetaching([$business->id]);
        $miguel->branches()->syncWithoutDetaching([$branch->id]);

        $this->call(CatalogDemoSeeder::class);
        $this->call(ReputationDemoSeeder::class);
    }

    private function upsertUser(
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        UserRole $role,
        UserStatus $status = UserStatus::Active,
    ): User {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim($firstName.' '.$lastName),
                'phone' => $phone,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'role' => $role,
                'status' => $status,
            ],
        );
    }
}
