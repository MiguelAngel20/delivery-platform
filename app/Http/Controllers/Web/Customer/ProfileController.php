<?php

namespace App\Http\Controllers\Web\Customer;

use App\Enums\CustomerTrustLevel;
use App\Http\Controllers\Controller;
use App\Services\Loyalty\CustomerLoyaltyService;
use App\Support\PhoneDialCodes;
use App\Support\ReputationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request, CustomerLoyaltyService $loyalty): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $customer = $user->customer;

        if ($customer !== null) {
            $customer->loadMissing(['user', 'metrics']);
        }

        $phone = PhoneDialCodes::customerSmsParts((string) $user->phone);

        return Inertia::render('customer/profile/index', [
            'reputation' => $customer !== null
                ? ReputationPresenter::customerForSelf($customer)
                : [
                    'verified' => false,
                    'public_label' => CustomerTrustLevel::New->publicLabel(),
                    'is_frequent' => false,
                    'completed_orders' => 0,
                ],
            'loyalty' => $customer !== null ? $loyalty->progressFor($customer) : null,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
            'phone_dial_code' => $phone['dial'],
            'phone_national' => $phone['national'],
            'phone_dial_options' => PhoneDialCodes::customerSmsOptions(),
            'firebase_phone_auth_ready' => $this->firebasePhoneAuthReady(),
        ]);
    }

    private function firebasePhoneAuthReady(): bool
    {
        $projectId = (string) (config('push.fcm.project_id') ?: config('push.web.project_id'));

        return $projectId !== ''
            && filled(config('push.web.api_key'))
            && filled(config('push.web.auth_domain'))
            && filled(config('push.web.app_id'))
            && filled(config('push.web.messaging_sender_id'));
    }
}
