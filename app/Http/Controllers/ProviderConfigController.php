<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderConfigRequest;
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
    public function show(ProviderConfigRequest $request)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? ($validated['locationId'] ?? null);

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
    public function connect(ProviderConfigRequest $request)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? $validated['locationId'];

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
            $details = $providerResult['details'] ?? [];
            $apiMessage = is_array($details) ? ($details['message'] ?? $details['error'] ?? '') : '';

            $errorMessage = 'Failed to register the Custom Provider in GHL.';
            if ($apiMessage) {
                $errorMessage .= ' Reason: ' . $apiMessage;
            }

            return back()->with([
                'error' => $errorMessage,
                'error_details' => $providerResult['details'] ?? null
            ]);
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
            $details = $configResult['details'] ?? [];
            $apiMessage = is_array($details) ? ($details['message'] ?? $details['error'] ?? '') : '';

            $errorMessage = 'Failed to push the PayMongo keys to the Connect Config API in GHL.';
            if ($apiMessage) {
                $errorMessage .= ' Reason: ' . $apiMessage;
            }

            return back()->with([
                'error' => $errorMessage,
                'error_details' => $configResult['details'] ?? null
            ]);
        }

        return back()->with('success', 'Successfully registered PayMongo integration in GHL using your .env keys!');
    }

    /**
     * Remove the Custom Provider from GHL.
     */
    public function delete(ProviderConfigRequest $request)
    {
        $validated = $request->validated();
        $locationId = $validated['location_id'] ?? $validated['locationId'];

        $locationToken = LocationToken::where('location_id', $locationId)->first();

        if (!$locationToken) {
            return back()->with('error', 'Location token not found. Please re-authenticate.');
        }

        $result = $this->providerConfigService->deleteProvider($locationId, $locationToken->access_token);

        if (!$result['success']) {
            return back()->with([
                'error' => 'Failed to remove the provider from GHL: ' . ($result['error'] ?? ''),
                'error_details' => $result['details'] ?? null
            ]);
        }

        return back()->with('success', 'Provider successfully removed from GoHighLevel!');
    }

}
