<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Loyalty\CustomerLoyaltyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __invoke(Request $request, CustomerLoyaltyService $loyalty): Response
    {
        $customer = $this->currentCustomer($request);

        $addresses = $customer->addresses()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get()
            ->map(fn (CustomerAddress $address): array => [
                'id' => (string) $address->id,
                'label' => $address->label,
                'line' => $address->address_text,
                'address_text' => $address->address_text,
                'reference' => $address->reference,
                'latitude' => (string) $address->latitude,
                'longitude' => (string) $address->longitude,
                'isDefault' => $address->is_default,
            ]);

        $progress = $loyalty->progressFor($customer);

        return Inertia::render('customer/checkout/index', [
            'addresses' => $addresses,
            'loyalty' => $progress,
            'orderSettings' => [
                'service_fee' => (float) $progress['service_fee'],
                'service_fee_discount' => (float) $progress['next_service_fee_discount'],
                'delivery_fee' => (float) config('business.orders.delivery_fee', 0),
            ],
        ]);
    }

    private function currentCustomer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        abort_unless($customer !== null, 403);

        return $customer;
    }
}
