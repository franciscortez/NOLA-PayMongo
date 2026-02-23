<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProviderConfigService;
use App\Models\LocationToken;

class ProviderConfigController extends Controller
{
    protected ProviderConfigService $providerConfigService;

    public function __construct(ProviderConfigService $providerConfigService)
    {
        $this->providerConfigService = $providerConfigService;
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

        return view('provider.config', ['locationId' => $locationId]);
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
                'live_publishable_key' => env('PAYMONGO_LIVE_PUBLISHABLE_KEY'),
                'live_secret_key' => env('PAYMONGO_LIVE_SECRET_KEY'),
                'test_publishable_key' => env('PAYMONGO_TEST_PUBLISHABLE_KEY'),
                'test_secret_key' => env('PAYMONGO_TEST_SECRET_KEY'),
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
}
