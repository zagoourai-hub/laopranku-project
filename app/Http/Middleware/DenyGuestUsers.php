<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyGuestUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_guest, 403, 'Akun tamu tidak bisa memakai fitur ini. Daftar akun dulu.');

        return $next($request);
    }
}
