<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        $portal = $request->cookie('login_portal');

        if (! is_string($portal) || $portal === '') {
            $referer = (string) $request->headers->get('referer', '');

            $portal = match (true) {
                str_contains($referer, '/admin') => 'admin.login',
                str_contains($referer, '/business') => 'business.login',
                str_contains($referer, '/driver') => 'driver.login',
                default => 'login',
            };
        }

        $redirectTo = match ($portal) {
            'admin.login' => route('admin.login'),
            'business.login' => route('business.login'),
            'driver.login' => route('driver.login'),
            default => route('home'),
        };

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return new RedirectResponse($redirectTo);
    }
}
