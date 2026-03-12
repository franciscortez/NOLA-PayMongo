<?php

namespace App\Http\Controllers;

use App\Http\Requests\QueryUrlRequest;
use App\Services\PayMongoService;
use App\Services\GhlQueryService;
use App\Models\LocationToken;
use Illuminate\Support\Facades\Log;

class QueryController extends Controller
{
   protected PayMongoService $payMongoService;
   protected GhlQueryService $ghlQueryService;

   public function __construct(PayMongoService $payMongoService, GhlQueryService $ghlQueryService)
   {
      $this->payMongoService = $payMongoService;
      $this->ghlQueryService = $ghlQueryService;
   }

   /**
    * Handle all incoming queryUrl requests from GHL.
    * GHL POSTs JSON with a "type" field to dispatch the action.
    */
   public function handle(QueryUrlRequest $request)
   {
      $payload = $request->validated();

      $type = $payload['type'];
      $apiKey = $payload['apiKey'] ?? null;
      $locationId = $payload['locationId'] ?? null;

      Log::info('GHL query received', [
         'type' => $type,
         'charge_id' => $payload['chargeId'] ?? null,
         'location_id' => $locationId,
      ]);

      $service = $this->resolveService($apiKey, $locationId);

      return match ($type) {
         'verify' => $this->handleVerify($request, $service),
         'refund' => $this->handleRefund($request, $service),
         'list_payment_methods' => $this->handleListPaymentMethods($request),
         'charge_payment' => $this->handleChargePayment($request, $service),
         default => response()->json(['error' => 'Unknown query type: ' . $type], 400),
      };
   }

   /**
    * Resolve the PayMongoService instance for this request.
    *
    * If GHL sends an apiKey, we use it directly (per-location / multi-account).
    * If the key is new or changed for this location, we automatically provision
    * a webhook in that PayMongo account and persist everything to the DB.
    *
    * Falls back to .env config if no apiKey is received from GHL.
    */
   protected function resolveService(?string $apiKey, ?string $locationId): PayMongoService
   {
      // No key from GHL — fall back to .env config (legacy single-account behaviour)
      if (!$apiKey) {
         $isProduction = ($apiKey !== config('services.paymongo.test_secret_key'));
         return $this->payMongoService->setProduction($isProduction);
      }

      $isLive = str_starts_with($apiKey, 'sk_live_');
      $keyColumn = $isLive ? 'paymongo_live_secret_key' : 'paymongo_test_secret_key';
      $webhookIdColumn = $isLive ? 'paymongo_live_webhook_id' : 'paymongo_test_webhook_id';
      $webhookSecretColumn = $isLive ? 'paymongo_live_webhook_secret' : 'paymongo_test_webhook_secret';

      // If we have a locationId, attempt to auto-provision the webhook when needed
      if ($locationId) {
         $token = LocationToken::where('location_id', $locationId)->first();

         if ($token) {
            $storedKey = $token->{$keyColumn};
            $storedWebhookId = $token->{$webhookIdColumn};

            // Provision a webhook if:
            // - No webhook exists yet for this location+mode, OR
            // - The API key has changed (user updated their keys in GHL)
            if (!$storedWebhookId || $storedKey !== $apiKey) {
               $this->provisionWebhook($token, $apiKey, $locationId, $isLive);
            }
         }
      }

      return $this->payMongoService->setDynamicKeys($apiKey);
   }

   /**
    * Create a webhook in the PayMongo account and save the credentials to the DB.
    */
   protected function provisionWebhook(LocationToken $token, string $apiKey, string $locationId, bool $isLive): void
   {
      $webhookIdColumn = $isLive ? 'paymongo_live_webhook_id' : 'paymongo_test_webhook_id';
      $webhookSecretColumn = $isLive ? 'paymongo_live_webhook_secret' : 'paymongo_test_webhook_secret';
      $secretKeyColumn = $isLive ? 'paymongo_live_secret_key' : 'paymongo_test_secret_key';

      Log::info('PayMongo: Provisioning webhook for location', [
         'location_id' => $locationId,
         'is_live' => $isLive,
      ]);

      $webhook = $this->payMongoService->createWebhook($apiKey, $locationId);

      if ($webhook) {
         $token->update([
            $secretKeyColumn => $apiKey,
            $webhookIdColumn => $webhook['id'],
            $webhookSecretColumn => $webhook['secret_key'],
         ]);

         Log::info('PayMongo: Webhook provisioned and saved', [
            'location_id' => $locationId,
            'webhook_id' => $webhook['id'],
            'is_live' => $isLive,
         ]);
      } else {
         // Still save the key even if webhook creation failed so we can retry later
         $token->update([$secretKeyColumn => $apiKey]);
         Log::warning('PayMongo: Webhook creation failed, key saved without webhook', [
            'location_id' => $locationId,
         ]);
      }
   }

   /**
    * Verify a payment after the iFrame reports success.
    *
    * GHL sends: { type: "verify", transactionId, apiKey, chargeId, subscriptionId? }
    * We must return: { success: true } or { failed: true }
    */
   protected function handleVerify(QueryUrlRequest $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId', '');
      $result = $this->ghlQueryService->verifyPayment($chargeId, $service);
      Log::info('GHL verify result', [
         'charge_id' => $chargeId,
         'success' => $result['success'] ?? !($result['failed'] ?? false),
      ]);
      return response()->json($result);
   }

   /**
    * Process a refund.
    *
    * GHL sends: { type: "refund", amount, transactionId, chargeId, apiKey }
    * We must return: { success: true, id, amount, currency, message }
    */
   protected function handleRefund(QueryUrlRequest $request, PayMongoService $service)
   {
      $chargeId = $request->input('chargeId', '');
      $amount = (float) $request->input('amount', 0);
      $result = $this->ghlQueryService->refundPayment($chargeId, $amount, $service);
      Log::info('GHL refund result', [
         'charge_id' => $chargeId,
         'amount' => $amount,
         'success' => $result['success'] ?? false,
      ]);
      return response()->json($result);
   }

   /**
    * List saved payment methods for a contact.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleListPaymentMethods(QueryUrlRequest $request)
   {
      Log::info('QueryController: list_payment_methods called (not yet implemented)');

      // Return empty array — no saved methods yet
      return response()->json([]);
   }

   /**
    * Charge a saved payment method.
    * Placeholder — will be implemented with Card Vaulting in a future step.
    */
   protected function handleChargePayment(QueryUrlRequest $request, PayMongoService $service)
   {
      Log::info('QueryController: charge_payment called (not yet implemented)');

      return response()->json([
         'success' => false,
         'failed' => true,
         'message' => 'Saved payment method charging is not yet implemented',
      ]);
   }
}
