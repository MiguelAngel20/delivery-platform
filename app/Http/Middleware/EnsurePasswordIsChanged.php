<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->mustChangePassword()) {
            return $next($request);
        }

        if ($request->routeIs(
            'password.force.edit',
            'password.force.update',
            'logout',
        )) {
            return $next($request);
        }

        return redirect()->route('password.force.edit');
    }
}
