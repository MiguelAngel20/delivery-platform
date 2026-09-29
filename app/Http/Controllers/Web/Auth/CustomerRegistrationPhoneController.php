<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Customers\ConfirmCustomerPhone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyCustomerRegistrationPhoneRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegistrationPhoneController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('register');
        }

        return Inertia::render('public/register/verify-phone', [
            'phone' => $user->phone,
            'maskedPhone' => PhoneNumber::mask((string) $user->phone),
            'firebaseReady' => $this->firebaseReady(),
        ]);
    }

    public function store(
        VerifyCustomerRegistrationPhoneRequest $request,
        ConfirmCustomerPhone $confirm,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('register');
        }

        $confirm->handle($user, (string) $request->validated('firebase_id_token'));

        $request->session()->forget('pending_customer_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        $continue = $request->session()->pull('register.continue', route('cart'));

        return redirect()->to($continue);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('pending_customer_user_id');

        if (! is_numeric($id)) {
            return null;
        }

        $user = User::query()->find((int) $id);

        if ($user === null || $user->phone_verified_at !== null) {
            return null;
        }

        return $user;
    }

    private function firebaseReady(): bool
    {
        $projectId = (string) (config('push.fcm.project_id') ?: config('push.web.project_id'));

        return $projectId !== ''
            && filled(config('push.web.api_key'))
            && filled(config('push.web.auth_domain'))
            && filled(config('push.web.app_id'))
            && filled(config('push.web.messaging_sender_id'));
    }
}
