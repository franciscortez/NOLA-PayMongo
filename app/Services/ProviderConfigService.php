<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderConfigService
{
   /**
    * Registers the Custom Payment Provider for the location.
    */
   public function registerCustomProvider(string $locationId, string $accessToken)
   {
      $payload = [
         'name' => 'NOLA PayMongo',
         'description' => 'PayMongo integration for NOLA. Powered by the GoHighLevel Custom Payment Provider.',
         'imageUrl' => 'https://i.imgur.com/upHBjxs.png',
         'locationId' => $locationId,
         'queryUrl' => rtrim(config('app.url'), '/') . '/api/query',
         'paymentsUrl' => rtrim(config('app.url'), '/') . '/checkout'
      ];

      Log::info("Sending Custom Provider Registration payload", $payload);

      $response = Http::withHeaders([
         'Authorization' => 'Bearer ' . $accessToken,
         'Version' => config('services.ghl.api_version', '2021-07-28')
      ])->post(config('services.ghl.api_base') . '/payments/custom-provider/provider?locationId=' . $locationId, $payload);

      if (!$response->successful()) {
         Log::error('HighLevel Custom Provider Registration Failed', $response->json());
         return [
            'success' => false,
            'error' => 'HighLevel Provider Registration Failed.',
            'details' => $response->json()
         ];
      }

      return [
         'success' => true,
         'data' => $response->json()
      ];
   }

   /**
    * Pushes the public/secret keys to GHL via the Connect Config API.
    * GHL expects a nested `live` and `test` structure in a single POST call.
    */
   public function updateConnectConfig(string $locationId, string $accessToken, array $keys)
   {
      $response = Http::withHeaders([
         'Authorization' => 'Bearer ' . $accessToken,
         'Version' => config('services.ghl.api_version', '2021-07-28')
      ])->post(config('services.ghl.api_base') . '/payments/custom-provider/connect?locationId=' . $locationId, [
               'locationId' => $locationId,
               'live' => [
                  'apiKey' => $keys['live_secret_key'],
                  'publishableKey' => $keys['live_publishable_key'],
               ],
               'test' => [
                  'apiKey' => $keys['test_secret_key'],
                  'publishableKey' => $keys['test_publishable_key'],
               ],
            ]);

      if (!$response->successful()) {
         Log::error('HighLevel Connect Config Failed', $response->json());
         return ['success' => false, 'error' => 'Failed to push keys to connect config.', 'details' => $response->json()];
      }

      Log::info('HighLevel Connect Config Success', $response->json());
      return ['success' => true];
   }


   /**
    * Check if the Custom Payment Provider is currently registered in GHL.
    */
   public function isProviderRegistered(string $locationId, string $accessToken): bool
   {
      $response = Http::withHeaders([
         'Authorization' => 'Bearer ' . $accessToken,
         'Version' => config('services.ghl.api_version', '2021-07-28')
      ])->get(config('services.ghl.api_base') . '/payments/custom-provider/connect?locationId=' . $locationId);

      $data = $response->json();

      return $response->successful() && isset($data['_id']);
   }

   /**
    * Deletes the Custom Payment Provider integration for the location.
    */
   public function deleteProvider(string $locationId, string $accessToken)
   {
      $response = Http::withHeaders([
         'Authorization' => 'Bearer ' . $accessToken,
         'Version' => config('services.ghl.api_version', '2021-07-28')
      ])->delete(config('services.ghl.api_base') . '/payments/custom-provider/provider?locationId=' . $locationId);

      if (!$response->successful()) {
         Log::error('HighLevel Delete Provider Failed', $response->json());
         return [
            'success' => false,
            'error' => 'Failed to delete provider from GHL.',
            'details' => $response->json()
         ];
      }

      return ['success' => true];
   }
}
