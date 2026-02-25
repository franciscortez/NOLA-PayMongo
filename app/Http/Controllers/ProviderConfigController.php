<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProviderConfigService;
use App\Models\LocationToken;
use App\Services\PayMongoService;

class ProviderConfigController extends Controller
{
    protected ProviderConfigService $providerConfigService;
    protected PayMongoService $payMongoService;

    public function __construct(ProviderConfigService $providerConfigService, PayMongoService $payMongoService)
    {
        $this->providerConfigService = $providerConfigService;
        $this->payMongoService = $payMongoService;
    }

    /**
     * Show the configuration UI for installing PayMongo and setting keys.
     */
    public function show(Request $request)
    {
        $locationId = $request->query('location_id') ?? $request->query('locationId');

        if (!$locationId) {
            return response('Location ID is missing', 400);
        }

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        $isConnected = false;
        if ($locationToken) {
            $isConnected = $this->providerConfigService->isProviderRegistered($locationId, $locationToken->access_token);
        }

        return view('provider.config', [
            'locationId' => $locationId,
            'isConnected' => $isConnected
        ]);
    }

    /**
     * Handle the explicit installation of the Custom Provider and API keys connection.
     */
    public function connect(Request $request)
    {
        $request->validate([
            'location_id' => 'required|string',
        ]);

        $locationId = $request->input('location_id');

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return back()->with('error', 'Location token not found. Please re-authenticate.');
        }

        // 0. Validate PayMongo Keys from environment before proceeding
        $liveKey = config('services.paymongo.live_secret_key');
        $testKey = config('services.paymongo.test_secret_key');

        if (!$this->payMongoService->validateKey($testKey)) {
            return back()->with('error', 'The PayMongo TEST Secret Key is invalid. Please check your .env file.');
        }

        if (config('services.paymongo.is_production') && !$this->payMongoService->validateKey($liveKey)) {
            return back()->with('error', 'The PayMongo LIVE Secret Key is invalid. Please check your .env file.');
        }

        // 1. Register the generic Custom Provider settings
        $providerResult = $this->providerConfigService->registerCustomProvider($locationId, $locationToken->access_token);

        if (!$providerResult['success']) {
            return back()->with('error', 'Failed to register the Custom Provider in GHL.');
        }

        // 2. We can save the explicitly provided PayMongo keys to Ghl Connect Config API here 
        // using the environment variables as requested by the user.
        $configResult = $this->providerConfigService->updateConnectConfig(
            $locationId,
            $locationToken->access_token,
            [
                'live_publishable_key' => config('services.paymongo.live_publishable_key'),
                'live_secret_key' => $liveKey,
                'test_publishable_key' => config('services.paymongo.test_publishable_key'),
                'test_secret_key' => $testKey,
            ]
        );

        if (!$configResult['success']) {
            return back()->with('error', 'Failed to push the PayMongo Pay keys to the Connect Config API in GHL.');
        }

        return back()->with('success', 'Successfully registered PayMongo integration in GHL using your .env keys!');
    }

    /**
     * Remove the Custom Provider from GHL.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'location_id' => 'required|string',
        ]);

        $locationId = $request->input('location_id');

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return back()->with('error', 'Location token not found. Please re-authenticate.');
        }

        $result = $this->providerConfigService->deleteProvider($locationId, $locationToken->access_token);

        if (!$result['success']) {
            return back()->with('error', 'Failed to remove the provider from GHL: ' . ($result['error'] ?? ''));
        }

        return back()->with('success', 'Provider successfully removed from GoHighLevel!');
    }

    /**
     * Diagnostic endpoint: shows current GHL provider registration status.
     */
    public function diagnose(Request $request)
    {
        $locationId = $request->query('location_id') ?? $request->query('locationId');

        if (!$locationId) {
            return response()->json(['error' => 'location_id query param required'], 400);
        }

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return response()->json([
                'error' => 'No token found for this location',
                'location_id' => $locationId,
                'hint' => 'You need to re-authenticate via OAuth at /oauth/callback',
            ], 404);
        }

        // Check if token might be expired
        $tokenInfo = [
            'location_id' => $locationToken->location_id,
            'has_access_token' => !empty($locationToken->access_token),
            'has_refresh_token' => !empty($locationToken->refresh_token),
            'expires_at' => $locationToken->expires_at ?? 'not set',
            'is_expired' => $locationToken->expires_at ? now()->gt($locationToken->expires_at) : 'unknown',
        ];

        // Fetch current config from GHL
        $ghlConfig = $this->providerConfigService->fetchProviderConfig(
            $locationId,
            $locationToken->access_token
        );

        return response()->json([
            'token_info' => $tokenInfo,
            'ghl_provider' => $ghlConfig['provider'],
            'ghl_connect_config' => $ghlConfig['connectConfig'],
            'app_url' => config('app.url'),
            'expected_payments_url' => rtrim(config('app.url'), '/') . '/checkout',
            'expected_query_url' => rtrim(config('app.url'), '/') . '/api/webhook/ghl-query',
        ], 200);
    }
}
