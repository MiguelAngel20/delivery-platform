<?php

namespace App\Http\Responses;

use App\Support\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse('', 204);
        }

        $portal = Portal::current($request);

        $target = $portal === Portal::STOREFRONT
            ? route('home')
            : route(Portal::loginRouteName($portal));

        $forgetPortalCookie = cookie()->forget('login_portal');

        if ($request->header('X-Inertia') && Portal::isCrossOriginUrl($target, $request)) {
            $response = Inertia::location($target);
            $response->headers->setCookie($forgetPortalCookie);

            return $response;
        }

        return (new RedirectResponse($target))
            ->withCookie($forgetPortalCookie);
    }
}
