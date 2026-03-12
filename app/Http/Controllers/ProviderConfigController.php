<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderConfigRequest;
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
     * Show the configuration UI for this location.
     */
    public function show(ProviderConfigRequest $request, \App\Services\GhlService $ghlService)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? ($validated['locationId'] ?? null);

        if (!$locationId) {
            return response('Location ID is missing', 400);
        }

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        $isConnected = false;
        $locationName = $locationId; // Default to ID if name is unknown

        if ($locationToken) {
            $isConnected = $this->providerConfigService->isProviderRegistered($locationId, $locationToken->access_token);
            
            // Try to get the name from DB or API
            $locationName = $locationToken->location_name ?? $locationId;

            if (!$locationToken->location_name) {
                $details = $ghlService->getLocationDetails($locationId, $locationToken->access_token);
                if ($details && isset($details['name'])) {
                    $locationName = $details['name'];
                    $locationToken->update(['location_name' => $locationName]);
                }
            }
        }

        return view('provider.config', [
            'locationId' => $locationId,
            'locationName' => $locationName,
            'isConnected' => $isConnected,
            'keys' => [
                'live_secret_key' => $locationToken ? $locationToken->paymongo_live_secret_key : '',
                'live_publishable_key' => $locationToken ? $locationToken->paymongo_live_publishable_key : '',
                'test_secret_key' => $locationToken ? $locationToken->paymongo_test_secret_key : '',
                'test_publishable_key' => $locationToken ? $locationToken->paymongo_test_publishable_key : '',
            ]
        ]);
    }

    /**
     * Register the Custom Provider in GHL and push the user-provided API keys.
     */
    public function connect(ProviderConfigRequest $request, \App\Services\PayMongoService $payMongoService)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? $validated['locationId'];

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return back()->with('error', 'Location token not found. Please re-authenticate.');
        }

        // Validate the keys were provided
        $liveSecretKey = $validated['live_secret_key'] ?? null;
        $livePublishableKey = $validated['live_publishable_key'] ?? null;
        $testSecretKey = $validated['test_secret_key'] ?? null;
        $testPublishableKey = $validated['test_publishable_key'] ?? null;

        if (!$liveSecretKey || !$livePublishableKey || !$testSecretKey || !$testPublishableKey) {
            return back()->with('error', 'All 4 PayMongo keys are required.');
        }

        // Validate keys against PayMongo API
        if (!$payMongoService->validateKey($liveSecretKey)) {
            return back()->with('error', 'The Live Secret Key is invalid.');
        }
        if (!$payMongoService->validateKey($testSecretKey)) {
            return back()->with('error', 'The Test Secret Key is invalid.');
        }

        // Delegate the complex setup (Registration, Keys, Webhooks, DB) to the service
        $result = $this->providerConfigService->setupPayMongoIntegration($locationToken, [
            'live_secret_key' => $liveSecretKey,
            'live_publishable_key' => $livePublishableKey,
            'test_secret_key' => $testSecretKey,
            'test_publishable_key' => $testPublishableKey,
        ]);

        if (!$result['success']) {
            $details = $result['details'] ?? [];
            $apiMessage = is_array($details) ? ($details['message'] ?? $details['error'] ?? '') : '';
            $errorMessage = $result['error'] ?? 'Setup failed.';
            if ($apiMessage) {
                $errorMessage .= ' Reason: ' . $apiMessage;
            }

            return back()->with([
                'error' => $errorMessage,
                'error_details' => $result['details'] ?? null
            ]);
        }

        return back()->with('success', 'PayMongo provider successfully connected and keys verified!');
    }

    /**
     * Remove the Custom Provider from GHL for this location.
     */
    public function delete(ProviderConfigRequest $request)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? $validated['locationId'];

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return back()->with('error', 'Location token not found. Please re-authenticate.');
        }

        $result = $this->providerConfigService->disconnectPayMongoIntegration($locationToken);

        if (!$result['success']) {
            return back()->with([
                'error' => 'Failed to remove the provider from GHL: ' . ($result['error'] ?? ''),
                'error_details' => $result['details'] ?? null
            ]);
        }

        return back()->with('success', 'Provider successfully removed from GoHighLevel!');
    }
}
