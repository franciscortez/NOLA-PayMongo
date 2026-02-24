<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GhlService;

class GhlOAuthController extends Controller
{
    protected GhlService $ghlService;

    public function __construct(GhlService $ghlService)
    {
        $this->ghlService = $ghlService;
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if (!$code) {
            return response()->json(['error' => 'No authorization code provided in the callback URL.'], 400);
        }

        // Validate state parameter to prevent CSRF attacks
        $savedState = $request->session()->pull('oauth_state');
        if (!$savedState || $state !== $savedState) {
            return response()->json(['error' => 'Invalid state parameter. Authentication failed.'], 403);
        }

        // 1. Exchange Code & Save Token
        $tokenResult = $this->ghlService->exchangeCodeForToken($code);

        if (!$tokenResult['success']) {
            return response()->json([
                'error' => $tokenResult['error'],
                'details' => $tokenResult['details'] ?? null,
            ], 500);
        }

        $locationToken = $tokenResult['location_token'];
        return redirect()->away(url('/provider/config?location_id=' . $locationToken->location_id));
    }
}
