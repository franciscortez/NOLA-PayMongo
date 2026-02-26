<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHttps
{
    /**
     * Redirect HTTP to HTTPS in non-local environments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldRedirectToHttps($request)) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }

    private function shouldRedirectToHttps(Request $request): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return ! $request->secure();
    }
}
