<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function __construct(
        private readonly CustomerAddressService $addresses,
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->currentCustomer($request);

        $addresses = $customer->addresses()
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn (CustomerAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'address_text' => $address->address_text,
                'reference' => $address->reference,
                'latitude' => (string) $address->latitude,
                'longitude' => (string) $address->longitude,
                'google_maps_url' => $address->google_maps_url,
                'is_default' => $address->is_default,
            ]);

        return Inertia::render('customer/addresses/index', [
            'addresses' => $addresses,
            'maxAddresses' => (int) config('business.orders.max_customer_addresses', 4),
        ]);
    }

    public function store(StoreCustomerAddressRequest $request): RedirectResponse
    {
        $customer = $this->currentCustomer($request);

        $this->addresses->create($customer, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dirección guardada.',
        ]);

        return back();
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($address->customer_id === $customer->id, 404);

        $this->addresses->delete($address);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dirección eliminada.',
        ]);

        return back();
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
