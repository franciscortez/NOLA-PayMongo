<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LocationToken;
use App\Services\GhlService;

class CheckGhlToken
{
    protected GhlService $ghlService;

    public function __construct(GhlService $ghlService)
    {
        $this->ghlService = $ghlService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locationId = $request->input('location_id') ?? $request->input('locationId');

        if ($locationId) {
            $token = LocationToken::where('location_id', $locationId)->first();

            if ($token && $token->expires_at) {
                // If token is expired or expiring within 5 minutes
                if (now()->addMinutes(5)->gt($token->expires_at)) {
                    $this->ghlService->refreshToken($token);
                }
            }
        }

        return $next($request);
    }
}
