<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterPayMongoWebhook extends Command
{
   protected $signature = 'paymongo:register-webhook {url? : The webhook URL (defaults to APP_URL/api/webhook/paymongo)}';
   protected $description = 'Register a webhook endpoint with PayMongo';

   public function handle(PayMongoService $payMongoService): int
   {
      $url = $this->argument('url') ?? url('/api/webhook/paymongo');

      $this->info("Registering webhook: {$url}");

      $events = [
         'checkout_session.payment.paid',
         'payment.paid',
         'payment.failed',
         'payment.refunded',
      ];



      $response = Http::withBasicAuth($payMongoService->getSecretKey(), '')
         ->post('https://api.paymongo.com/v1/webhooks', [
            'data' => [
               'attributes' => [
                  'url' => $url,
                  'events' => $events,
               ],
            ],
         ]);

      if (!$response->successful()) {
         $this->error('Failed to register webhook:');
         $this->error($response->body());
         return self::FAILURE;
      }

      $data = $response->json('data');
      $this->info("✅ Webhook registered!");
      $this->table(['Field', 'Value'], [
         ['ID', $data['id']],
         ['URL', $data['attributes']['url']],
         ['Secret Key', $data['attributes']['secret_key'] ?? 'N/A'],
         ['Events', implode(', ', $data['attributes']['events'])],
         ['Status', $data['attributes']['status']],
      ]);

      return self::SUCCESS;
   }
}
