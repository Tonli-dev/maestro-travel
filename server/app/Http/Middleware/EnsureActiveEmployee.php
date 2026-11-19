<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveEmployee
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }
        abort_unless($request->user()->canAccessPanel(Filament::getPanel('admin')), 403);

        return $next($request);
    }
}
