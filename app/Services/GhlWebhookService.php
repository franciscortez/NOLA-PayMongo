<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlWebhookService
{
   /**
    * GHL's webhook endpoint where we POST payment events.
    */
   protected string $webhookUrl = 'https://backend.leadconnectorhq.com/payments/custom-provider/webhook';

   /**
    * Send a payment.captured event to GHL when a payment succeeds.
    */
   public function sendPaymentCaptured(Transaction $transaction): bool
   {
      if (!$transaction->ghl_transaction_id || !$transaction->ghl_location_id) {
         Log::info('GhlWebhookService: Skipping — no GHL transaction/location ID', [
            'transaction_id' => $transaction->id,
         ]);
         return false;
      }

      $chargeId = $transaction->payment_id
         ?? $transaction->payment_intent_id
         ?? $transaction->checkout_session_id;

      $payload = [
         'event' => 'payment.captured',
         'chargeId' => $chargeId,
         'ghlTransactionId' => $transaction->ghl_transaction_id,
         'chargeSnapshot' => [
            'status' => 'succeeded',
            'amount' => $transaction->amount * 100, // minor units
            'chargeId' => $chargeId,
            'chargedAt' => ($transaction->paid_at ?? now())->timestamp,
         ],
         'locationId' => $transaction->ghl_location_id,
         'apiKey' => $this->resolveApiKey($transaction),
      ];

      Log::info('GhlWebhookService: Sending payment.captured to GHL', [
         'ghl_transaction_id' => $transaction->ghl_transaction_id,
         'chargeId' => $chargeId,
      ]);

      try {
         $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
         ])->post($this->webhookUrl, $payload);

         Log::info('GhlWebhookService: Response from GHL', [
            'status' => $response->status(),
            'body' => $response->json(),
         ]);

         if (!$response->successful()) {
            Log::error('GhlWebhookService: GHL webhook call failed', [
               'status' => $response->status(),
               'body' => $response->body(),
               'payload' => $payload,
            ]);
            return false;
         }

         return true;
      } catch (\Exception $e) {
         Log::error('GhlWebhookService: Exception sending webhook to GHL', [
            'message' => $e->getMessage(),
            'payload' => $payload,
         ]);
         return false;
      }
   }

   /**
    * Resolve which PayMongo API key to send as the apiKey field.
    * GHL uses this to match the provider config (test vs live).
    */
   protected function resolveApiKey(Transaction $transaction): string
   {
      $isProduction = $transaction->is_live_mode;

      return $isProduction
         ? config('services.paymongo.live_secret_key')
         : config('services.paymongo.test_secret_key');
   }
}
