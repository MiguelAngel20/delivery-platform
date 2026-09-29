<?php

namespace App\Http\Controllers\Web\Customer;

use App\Actions\Customers\ConfirmCustomerPhone;
use App\Actions\Customers\UpdateCustomerPhone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ConfirmCustomerPhoneRequest;
use App\Http\Requests\Customer\UpdateCustomerPhoneRequest;
use App\Support\PhoneDialCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function confirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->phone_verified_at !== null) {
            return redirect()->route('customer.checkout');
        }

        $phone = PhoneDialCodes::customerSmsParts((string) $user->phone);

        return Inertia::render('customer/phone/confirm', [
            'phone' => $user->phone,
            'phone_verified' => false,
            'phone_dial_code' => $phone['dial'],
            'phone_national' => $phone['national'],
            'phone_dial_options' => PhoneDialCodes::customerSmsOptions(),
            'firebase_phone_auth_ready' => $this->firebasePhoneAuthReady(),
        ]);
    }

    public function update(
        UpdateCustomerPhoneRequest $request,
        UpdateCustomerPhone $action,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $action->handle($user, (string) $request->validated('phone'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Guardamos tu número. Verifícalo con el código SMS.',
        ]);

        return back();
    }

    public function store(
        ConfirmCustomerPhoneRequest $request,
        ConfirmCustomerPhone $action,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $action->handle($user, (string) $request->validated('firebase_id_token'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Teléfono verificado.',
        ]);

        return back();
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
