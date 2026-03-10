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
            return view('oauth.error', [
                'error' => 'No authorization code provided in the callback URL.',
            ]);
        }

        // Validate state parameter to prevent CSRF attacks if initiated from our home page
        $savedState = $request->session()->pull('oauth_state');
        if ($savedState && $state !== $savedState) {
            return view('oauth.error', [
                'error' => 'Invalid state parameter. Authentication failed.',
            ]);
        }

        // 1. Exchange Code & Save Token
        $tokenResult = $this->ghlService->exchangeCodeForToken($code);

        if (!$tokenResult['success']) {
            return view('oauth.error', [
                'error' => $tokenResult['error'],
                'details' => $tokenResult['details'] ?? null,
            ]);
        }

        $locationToken = $tokenResult['location_token'];
        return redirect()->away(url('/provider/config?location_id=' . $locationToken->location_id));

    }
}
