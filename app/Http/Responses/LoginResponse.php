<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        $home = $user?->homeRoute() ?? route('home');

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false, 'redirect' => $home]);
        }

        return new RedirectResponse($home);
    }
}
