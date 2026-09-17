<?php

namespace App\Http\Responses;

use App\Support\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $portal = Portal::current($request);

        $target = $portal === Portal::STOREFRONT
            ? route('home')
            : route(Portal::loginRouteName($portal));

        return (new RedirectResponse($target))
            ->withCookie(cookie()->forget('login_portal'));
    }
}
