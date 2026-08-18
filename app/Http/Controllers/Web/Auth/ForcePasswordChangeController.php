<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcePasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->mustChangePassword()) {
            return redirect()->to($user?->homeRoute() ?? route('home'));
        }

        return Inertia::render('auth/force-password-change', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(ForcePasswordChangeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Contraseña actualizada. Ya puedes usar el sistema.',
        ]);

        return redirect()->to($user->homeRoute());
    }
}
