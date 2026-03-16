<?php

namespace App\Services;

use App\Models\LocationToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderConfigService
{
   protected PayMongoService $payMongoService;

   public function __construct(PayMongoService $payMongoService)
   {
      $this->payMongoService = $payMongoService;
   }

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

   /**
    * setupPayMongoIntegration handles the full lifecycle of connecting PayMongo:
    * 1. Registration of the Provider
    * 2. Updating the keys for Connect Config
    * 3. Eagerly provisioning the webhooks
    * 4. Persisting everything to the local database
    */
   public function setupPayMongoIntegration(LocationToken $token, array $keys): array
   {
      $locationId = $token->location_id;
      $accessToken = $token->access_token;

      // 1. Register Custom Provider in GHL
      $regResult = $this->registerCustomProvider($locationId, $accessToken);
      if (!$regResult['success']) {
         return $regResult;
      }

      // 2. Push user's keys to GHL Connect Config API
      $configResult = $this->updateConnectConfig($locationId, $accessToken, $keys);
      if (!$configResult['success']) {
         return $configResult;
      }

      // 3. Provision Webhooks Eagerly
      $liveSecret = $keys['live_secret_key'];
      $testSecret = $keys['test_secret_key'];

      $liveWebhookId = $token->paymongo_live_webhook_id;
      $liveWebhookSecret = $token->paymongo_live_webhook_secret;
      if (!$liveWebhookSecret || $token->paymongo_live_secret_key !== $liveSecret) {
         $liveWebhook = $this->payMongoService->setDynamicKeys($liveSecret)->createWebhook($liveSecret, $locationId);
         if ($liveWebhook) {
            $liveWebhookId = $liveWebhook['id'];
            $liveWebhookSecret = $liveWebhook['secret_key'];
         }
      }

      $testWebhookId = $token->paymongo_test_webhook_id;
      $testWebhookSecret = $token->paymongo_test_webhook_secret;
      if (!$testWebhookSecret || $token->paymongo_test_secret_key !== $testSecret) {
         $testWebhook = $this->payMongoService->setDynamicKeys($testSecret)->createWebhook($testSecret, $locationId);
         if ($testWebhook) {
            $testWebhookId = $testWebhook['id'];
            $testWebhookSecret = $testWebhook['secret_key'];
         }
      }

      // 4. Save the keys and webhook secrets to the local database
      $token->update([
         'paymongo_live_publishable_key' => $keys['live_publishable_key'],
         'paymongo_live_secret_key' => $liveSecret,
         'paymongo_live_webhook_id' => $liveWebhookId,
         'paymongo_live_webhook_secret' => $liveWebhookSecret,
         'paymongo_test_publishable_key' => $keys['test_publishable_key'],
         'paymongo_test_secret_key' => $testSecret,
         'paymongo_test_webhook_id' => $testWebhookId,
         'paymongo_test_webhook_secret' => $testWebhookSecret,
      ]);

      return ['success' => true];
   }

   /**
    * disconnectPayMongoIntegration removes the provider from GHL and clears local DB keys.
    */
   public function disconnectPayMongoIntegration(LocationToken $token): array
   {
      $locationId = $token->location_id;
      
      // Cleanup webhooks from PayMongo before deleting local tokens
      if ($token->paymongo_live_secret_key) {
         $this->payMongoService->deleteWebhooksForLocation($token->paymongo_live_secret_key, $locationId);
      }
      if ($token->paymongo_test_secret_key) {
         $this->payMongoService->deleteWebhooksForLocation($token->paymongo_test_secret_key, $locationId);
      }

      $result = $this->deleteProvider($locationId, $token->access_token);

      if ($result['success']) {
         $token->update([
            'paymongo_live_publishable_key' => null,
            'paymongo_live_secret_key' => null,
            'paymongo_live_webhook_id' => null,
            'paymongo_live_webhook_secret' => null,
            'paymongo_test_publishable_key' => null,
            'paymongo_test_secret_key' => null,
            'paymongo_test_webhook_id' => null,
            'paymongo_test_webhook_secret' => null,
         ]);
      }

      return $result;
   }
}
