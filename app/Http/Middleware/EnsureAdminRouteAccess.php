<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRouteAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRouteAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requirement = AdminRouteAccess::requirement($request->route()?->getName());

        if ($requirement === AdminRouteAccess::ALLOW) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($requirement === AdminRouteAccess::OWNER) {
            abort_unless($user?->isPlatformOwner() === true, 403);

            return $next($request);
        }

        abort_unless(
            AdminAccess::allows($user, $requirement['section'], $requirement['ability']),
            403,
        );

        return $next($request);
    }
}
