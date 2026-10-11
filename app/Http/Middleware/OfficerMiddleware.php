<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OfficerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('dashboard.login');
        }

        if (!$request->user()->isOfficer()) {
            if ($request->expectsJson()) {
                abort(403);
            }
            abort(403, 'Akses dibatasi untuk petugas dan admin.');
        }

        return $next($request);
    }
}
