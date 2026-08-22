<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyCustomerEmailRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerEmailVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('register');
        }

        return Inertia::render('public/register/verify-email', [
            'email' => $user->email,
            'maskedEmail' => $this->maskEmail($user->email),
            'phoneVerificationEnabled' => (bool) config('business.customers.phone_verification_enabled', false),
        ]);
    }

    public function store(
        VerifyCustomerEmailRequest $request,
        EmailVerificationCodeService $codes,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('register');
        }

        if (! $codes->verify($user, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya caducó. Solicita uno nuevo.',
            ]);
        }

        $request->session()->forget('pending_customer_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        $continue = $request->session()->pull('register.continue', route('customer.checkout'));

        return redirect()->to($continue);
    }

    public function resend(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('register');
        }

        $throttleKey = 'register-otp-resend:'.$request->ip().':'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages([
                'code' => 'Espera un momento antes de pedir otro código.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);
        $codes->issue($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Te enviamos un nuevo código a tu correo.',
        ]);

        return back();
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('pending_customer_user_id');

        if (! is_numeric($id)) {
            return null;
        }

        return User::query()->find((int) $id);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}
