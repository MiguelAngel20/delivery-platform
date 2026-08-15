<?php

namespace App\Http\Controllers\Web\Customer;

use App\Enums\CustomerTrustLevel;
use App\Http\Controllers\Controller;
use App\Support\ReputationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $customer = $user->customer;

        if ($customer !== null) {
            $customer->loadMissing(['user', 'metrics']);
        }

        return Inertia::render('customer/profile/index', [
            'reputation' => $customer !== null
                ? ReputationPresenter::customerForSelf($customer)
                : [
                    'verified' => false,
                    'public_label' => CustomerTrustLevel::New->publicLabel(),
                    'is_frequent' => false,
                    'completed_orders' => 0,
                ],
            'phone' => $user->phone,
        ]);
    }
}
