<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Customers\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Services\Auth\EmailVerificationCodeService;
use App\Support\PhoneDialCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/register/index', [
            'dialCodes' => PhoneDialCodes::options(),
            'defaultDialCode' => PhoneDialCodes::defaultDial(),
            'passwordRules' => PasswordRule::defaults()?->toPasswordRulesString()
                ?? 'min:8',
        ]);
    }

    public function store(
        RegisterCustomerRequest $request,
        RegisterCustomer $register,
        EmailVerificationCodeService $codes,
    ): RedirectResponse {
        $user = $register->handle($request->validated());

        $codes->issue($user);

        $request->session()->put('pending_customer_user_id', $user->id);
        $request->session()->put('register.continue', route('customer.checkout'));

        return redirect()->route('register.verify-email');
    }
}
