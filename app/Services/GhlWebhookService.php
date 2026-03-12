<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\LocationToken;

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
      $ghlReferenceId = $transaction->ghl_transaction_id ?: $transaction->ghl_invoice_id;

      if (!$ghlReferenceId || !$transaction->ghl_location_id) {
         Log::info('GhlWebhookService: Skipping — no GHL reference (transaction/invoice) or location ID', [
            'transaction_id' => $transaction->id,
            'ghl_transaction_id' => $transaction->ghl_transaction_id,
            'ghl_invoice_id' => $transaction->ghl_invoice_id,
         ]);
         return false;
      }

      $chargeId = $transaction->payment_id
         ?? $transaction->payment_intent_id
         ?? $transaction->checkout_session_id;

      $payload = [
         'event' => 'payment.captured',
         'chargeId' => $chargeId,
         'ghlTransactionId' => $ghlReferenceId,
         'chargeSnapshot' => [
            'status' => 'succeeded',
            'amount' => $transaction->amount, // minor units
            'chargeId' => $chargeId,
            'chargedAt' => ($transaction->paid_at ?? now())->timestamp,
         ],
         'locationId' => $transaction->ghl_location_id,
         'apiKey' => $this->resolveApiKey($transaction),
      ];

      Log::info('GHL webhook payment.captured sent', [
         'ghl_transaction_id' => $transaction->ghl_transaction_id,
         'charge_id' => $chargeId,
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
            Log::error('GHL webhook delivery failed', [
               'ghl_transaction_id' => $transaction->ghl_transaction_id,
               'status' => $response->status(),
            ]);
            Log::error('GhlWebhookService: GHL webhook call failed', [
               'status' => $response->status(),
               'body' => $response->body(),
               'payload' => $payload,
            ]);
            return false;
         }

         return true;
      } catch (\Exception $e) {
         Log::error('GHL webhook exception', [
            'ghl_transaction_id' => $transaction->ghl_transaction_id,
            'error' => $e->getMessage(),
         ]);
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
      $locationId = $transaction->ghl_location_id;

      if ($locationId) {
         $token = LocationToken::where('location_id', $locationId)->first();
         if ($token) {
            $key = $isProduction ? $token->paymongo_live_secret_key : $token->paymongo_test_secret_key;
            if ($key) {
               return $key;
            }
         }
      }

      // Fallback to global config if no location-specific key is found
      return $isProduction
         ? config('services.paymongo.live_secret_key')
         : config('services.paymongo.test_secret_key');
   }
}
