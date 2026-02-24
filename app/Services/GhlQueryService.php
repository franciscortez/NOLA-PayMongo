<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class GhlQueryService
{
   /**
    * Verify a payment.
    */
   public function verifyPayment(string $chargeId, PayMongoService $service): array
   {
      if (!$chargeId) {
         Log::warning('GhlQueryService: Verify called without chargeId');
         return ['success' => false, 'failed' => true];
      }

      $transaction = Transaction::where('ghl_transaction_id', $chargeId)
         ->orWhere('checkout_session_id', $chargeId)
         ->orWhere('payment_intent_id', $chargeId)
         ->first();

      if ($transaction && $transaction->isPaid()) {
         Log::info('GhlQueryService: Verify from DB — paid', [
            'chargeId' => $chargeId,
            'transaction_id' => $transaction->id,
         ]);

         return [
            'success' => true,
            'chargeSnapshot' => [
               'id' => $transaction->payment_id ?? $chargeId,
               'status' => 'succeeded',
               'amount' => $transaction->amount / 100,
               'chargeId' => $chargeId,
               'chargedAt' => ($transaction->paid_at ?? $transaction->updated_at)->timestamp,
            ],
         ];
      }

      if ($transaction && $transaction->isPending()) {
         return ['success' => false, 'failed' => false];
      }

      if ($transaction && $transaction->status === 'failed') {
         return ['success' => false, 'failed' => true];
      }

      $result = $service->retrievePaymentIntent($chargeId);

      if (!$result['success']) {
         Log::error('GhlQueryService: Failed to verify', ['chargeId' => $chargeId]);
         return ['success' => false, 'failed' => true];
      }

      $status = $result['status'];

      if ($status === 'succeeded') {
         $payment = $result['payments'][0] ?? null;
         $paymentId = $payment['id'] ?? $chargeId;

         return [
            'success' => true,
            'chargeSnapshot' => [
               'id' => $paymentId,
               'status' => 'succeeded',
               'amount' => $result['amount'] / 100,
               'chargeId' => $chargeId,
               'chargedAt' => now()->timestamp,
            ],
         ];
      }

      if (in_array($status, ['processing', 'awaiting_next_action'])) {
         return ['success' => false, 'failed' => false];
      }

      return ['success' => false, 'failed' => true];
   }

   /**
    * Process a refund.
    */
   public function refundPayment(string $chargeId, float $amount, PayMongoService $service): array
   {
      if (!$chargeId || !$amount) {
         return [
            'success' => false,
            'message' => 'Missing chargeId or amount',
         ];
      }

      $piResult = $service->retrievePaymentIntent($chargeId);

      if (!$piResult['success'] || empty($piResult['payments'])) {
         return [
            'success' => false,
            'message' => 'Could not find the payment to refund',
         ];
      }

      $paymentId = $piResult['payments'][0]['id'];
      $amountInCents = (int) round($amount * 100);

      $result = $service->refundPayment($paymentId, $amountInCents);

      if (!$result['success']) {
         return [
            'success' => false,
            'message' => 'Refund failed: ' . json_encode($result['error'] ?? ''),
         ];
      }

      $transaction = Transaction::where('payment_id', $paymentId)
         ->orWhere('payment_intent_id', $chargeId)
         ->first();

      if ($transaction) {
         $transaction->update(['status' => 'refunded']);
      }

      return [
         'success' => true,
         'message' => 'Refund successful',
         'id' => $result['id'],
         'amount' => $amount,
         'currency' => strtoupper($result['currency'] ?? 'PHP'),
      ];
   }
}
