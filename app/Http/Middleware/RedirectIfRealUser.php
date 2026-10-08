<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same as the stock `guest` middleware, except guest (trial) users may still reach the register page.
 */
class RedirectIfRealUser extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->user()?->is_guest && $request->routeIs('register', 'register.store')) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
