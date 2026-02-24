<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WebhookProcessingService;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
   protected WebhookProcessingService $webhookProcessingService;

   public function __construct(WebhookProcessingService $webhookProcessingService)
   {
      $this->webhookProcessingService = $webhookProcessingService;
   }

   /**
    * Handle incoming PayMongo webhook events.
    * POST /api/webhook/paymongo
    */
   public function handle(Request $request)
   {
      $payload = $request->all();

      Log::info('PayMongo Webhook: Received', [
         'type' => $payload['data']['attributes']['type'] ?? 'unknown',
      ]);

      $eventType = $payload['data']['attributes']['type'] ?? null;
      $eventData = $payload['data']['attributes']['data'] ?? null;

      if (!$eventType || !$eventData) {
         Log::warning('PayMongo Webhook: Missing event type or data');
         return response()->json(['message' => 'Invalid webhook payload'], 400);
      }

      $handled = match ($eventType) {
         'checkout_session.payment.paid' => $this->webhookProcessingService->processCheckoutSessionPaid($eventData),
         'payment.paid' => $this->webhookProcessingService->processPaymentPaid($eventData),
         'payment.failed' => $this->webhookProcessingService->processPaymentFailed($eventData),
         'payment.refunded' => $this->webhookProcessingService->processPaymentRefunded($eventData),
         default => null,
      };

      if ($handled === null) {
         return $this->handleUnknownEvent($eventType);
      }

      return response()->json(['message' => 'OK'], 200);
   }

   /**
    * Handle unknown webhook events (log and acknowledge).
    */
   protected function handleUnknownEvent(string $eventType)
   {
      Log::info('PayMongo Webhook: Unhandled event type', ['type' => $eventType]);
      return response()->json(['message' => 'OK'], 200);
   }
}
