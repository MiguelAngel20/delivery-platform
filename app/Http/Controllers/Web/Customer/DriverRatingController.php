<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreDriverRatingRequest;
use App\Models\Order;
use App\Services\Reputation\DriverReputationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DriverRatingController extends Controller
{
    public function store(
        StoreDriverRatingRequest $request,
        Order $order,
        DriverReputationService $ratings,
    ): RedirectResponse {
        $customer = $request->user()?->customer;
        abort_unless($customer !== null, 403);

        $ratings->rate($order, $customer, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Gracias por calificar a tu repartidor.',
        ]);

        return back();
    }
}
