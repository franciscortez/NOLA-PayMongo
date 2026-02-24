<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class WebhookProcessingService
{
   protected GhlWebhookService $ghlWebhookService;

   public function __construct(GhlWebhookService $ghlWebhookService)
   {
      $this->ghlWebhookService = $ghlWebhookService;
   }

   public function processCheckoutSessionPaid(array $eventData): bool
   {
      $checkoutSessionId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $payments = $attributes['payments'] ?? [];

      Log::info('WebhookProcessingService: checkout_session.payment.paid', [
         'checkout_session_id' => $checkoutSessionId,
         'payments_count' => count($payments),
      ]);

      $transaction = Transaction::where('checkout_session_id', $checkoutSessionId)->first();

      if (!$transaction) {
         Log::warning('WebhookProcessingService: Transaction not found for checkout session', [
            'checkout_session_id' => $checkoutSessionId,
         ]);
         return false;
      }

      $payment = $payments[0] ?? null;
      if ($payment) {
         $paymentAttrs = $payment['attributes'] ?? [];
         $paymentSource = $paymentAttrs['source'] ?? [];

         $transaction->update([
            'status' => 'paid',
            'payment_id' => $payment['id'] ?? null,
            'payment_intent_id' => $attributes['payment_intent']['id'] ?? $transaction->payment_intent_id,
            'payment_method' => $paymentSource['type'] ?? null,
            'paid_at' => now(),
            'metadata' => array_merge($transaction->metadata ?? [], [
               'webhook_payment' => $payment,
            ]),
         ]);

         Log::info('WebhookProcessingService: Transaction updated to paid', [
            'transaction_id' => $transaction->id,
            'payment_method' => $paymentSource['type'] ?? null,
            'payment_id' => $payment['id'] ?? null,
         ]);

         $this->ghlWebhookService->sendPaymentCaptured($transaction->fresh());
      }

      return true;
   }

   public function processPaymentPaid(array $eventData): bool
   {
      $paymentId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $paymentSource = $attributes['source'] ?? [];

      Log::info('WebhookProcessingService: payment.paid', ['payment_id' => $paymentId]);

      $paymentIntentId = $attributes['payment_intent_id'] ?? null;

      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $paymentIntentId)
         ->first();

      if ($transaction && $transaction->status !== 'paid') {
         $transaction->update([
            'status' => 'paid',
            'payment_id' => $paymentId,
            'payment_method' => $paymentSource['type'] ?? $transaction->payment_method,
            'paid_at' => now(),
         ]);

         Log::info('WebhookProcessingService: Transaction updated via payment.paid', [
            'transaction_id' => $transaction->id,
         ]);

         $this->ghlWebhookService->sendPaymentCaptured($transaction->fresh());
      }

      return true;
   }

   public function processPaymentFailed(array $eventData): bool
   {
      $paymentId = $eventData['id'] ?? null;
      $attributes = $eventData['attributes'] ?? [];
      $paymentIntentId = $attributes['payment_intent_id'] ?? null;

      Log::info('WebhookProcessingService: payment.failed', ['payment_id' => $paymentId]);

      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $paymentIntentId)
         ->first();

      if ($transaction) {
         $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], [
               'failure_reason' => $attributes['last_payment_error'] ?? null,
            ]),
         ]);
      }

      return true;
   }

   public function processPaymentRefunded(array $eventData): bool
   {
      $paymentId = $eventData['id'] ?? null;

      Log::info('WebhookProcessingService: payment.refunded', ['payment_id' => $paymentId]);

      $transaction = Transaction::where('payment_id', $paymentId)->first();

      if ($transaction) {
         $transaction->update(['status' => 'refunded']);
      }

      return true;
   }
}
