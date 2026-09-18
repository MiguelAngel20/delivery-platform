<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Customers\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Services\Auth\EmailVerificationCodeService;
use App\Support\ApplicationPassword;
use App\Support\PhoneDialCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegisterController extends Controller
{
    public function create(Request $request): Response
    {
        if ($request->string('continue')->toString() === 'custom-order') {
            $request->session()->put(
                'register.continue',
                route('customer.custom-orders.create'),
            );
        }

        return Inertia::render('public/register/index', [
            'dialCodes' => PhoneDialCodes::options(),
            'defaultDialCode' => PhoneDialCodes::defaultDial(),
            'passwordRules' => ApplicationPassword::rule()->toPasswordRulesString(),
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

        if (! $request->session()->has('register.continue')) {
            // Land on cart (paso 1) so guests with localStorage lines see their order
            // before address/payment. Checkout alone starts at step 2 without line items.
            $request->session()->put('register.continue', route('cart'));
        }

        return redirect()->route('register.verify-email');
    }
}
