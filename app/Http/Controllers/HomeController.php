<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
   /**
    * Show the installation welcome page.
    */
   public function index(Request $request)
   {
      // Generation of state removed for Marketplace compliance per user request


      $clientId = config('services.ghl.client_id');

      // The version_id is the first part of the client_id before the hyphen
      $versionIdArr = explode('-', $clientId);
      $versionId = $versionIdArr[0] ?? '';

      $redirectUri = urlencode(url('/oauth/callback'));
      $scopes = 'payments/orders.readonly payments/orders.write payments/subscriptions.readonly payments/transactions.readonly payments/custom-provider.readonly payments/custom-provider.write products.readonly products/prices.readonly locations.readonly contacts.readonly payments/orders.collectPayment invoices.readonly invoices.write';
      $scopesEncoded = urlencode($scopes);

      // Standard GHL
      $standardUrl = "https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&redirect_uri={$redirectUri}&client_id={$clientId}&scope={$scopesEncoded}&version_id={$versionId}";

      // White Label (LeadConnector)
      $whiteLabelUrl = "https://marketplace.leadconnectorhq.com/oauth/chooselocation?response_type=code&redirect_uri={$redirectUri}&client_id={$clientId}&scope={$scopesEncoded}&version_id={$versionId}";

      return view('welcome', [
         'standardUrl' => $standardUrl,
         'whiteLabelUrl' => $whiteLabelUrl,
      ]);
   }
   /**
    * Check the overall health of the application.
    */
   public function healthCheck()
   {
      // 1. Check DB
      try {
         DB::connection()->getPdo();
         $dbStatus = 'connected';
      } catch (\Exception $e) {
         $dbStatus = 'disconnected';
      }

      // 2. Check Cache
      try {
         Cache::put('health_check', true, 10);
         $cacheStatus = Cache::get('health_check') ? 'ok' : 'failed';
      } catch (\Exception $e) {
         $cacheStatus = 'failed';
      }

      // We don't check for PayMongo keys globally here anymore, as they are dynamic per location.
      $isHealthy = $dbStatus === 'connected' && $cacheStatus === 'ok';

      return response()->json([
         'status' => $isHealthy ? 'OK' : 'ERROR',
         'database' => $dbStatus,
         'cache' => $cacheStatus,
         'timestamp' => now()->toIso8601String(),
      ], $isHealthy ? 200 : 503);
   }
}
