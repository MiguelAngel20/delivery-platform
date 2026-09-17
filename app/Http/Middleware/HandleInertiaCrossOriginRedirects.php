<?php

namespace App\Http\Middleware;

use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia XHR cannot follow cross-origin redirects (CORS). Convert those to
 * Inertia::location() so the browser does a full navigation between portal hosts.
 */
class HandleInertiaCrossOriginRedirects
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->header('X-Inertia') || ! $response->isRedirection()) {
            return $response;
        }

        $target = $response->headers->get('Location');

        if (! is_string($target) || $target === '') {
            return $response;
        }

        if (! Portal::isCrossOriginUrl($target, $request)) {
            return $response;
        }

        return Inertia::location($target);
    }
}
