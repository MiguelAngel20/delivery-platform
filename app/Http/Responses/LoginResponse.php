<?php

namespace App\Http\Responses;

use App\Support\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        $home = $user?->homeRoute() ?? route('home');

        if ($user?->mustChangePassword()) {
            $home = route('password.force.edit');
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['two_factor' => false, 'redirect' => $home]);
        }

        if ($request->header('X-Inertia') && Portal::isCrossOriginUrl($home, $request)) {
            return Inertia::location($home);
        }

        return new RedirectResponse($home);
    }
}
