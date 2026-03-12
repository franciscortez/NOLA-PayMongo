<?php

namespace App\Services;

use App\Models\LocationToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhlService
{
   /**
    * Exchanges the authorization code for an OAuth access token and saves it.
    */
   public function exchangeCodeForToken(string $code)
   {
      $response = Http::asForm()->post(config('services.ghl.api_base') . '/oauth/token', [
         'client_id' => config('services.ghl.client_id'),
         'client_secret' => config('services.ghl.client_secret'),
         'grant_type' => 'authorization_code',
         'code' => $code,
         'redirect_uri' => config('services.ghl.redirect_uri'),
         'user_type' => 'Location'
      ]);

      if (!$response->successful()) {
         Log::error('HighLevel Token Exchange Failed', $response->json());
         return [
            'success' => false,
            'error' => 'Authentication Failed',
         ];
      }

      $data = $response->json();

      $locationToken = $this->saveTokenData($data);

      if ($locationToken) {
         $details = $this->getLocationDetails($locationToken->location_id, $data['access_token']);
         if ($details && isset($details['name'])) {
            $locationToken->update(['location_name' => $details['name']]);
         }
      }

      return [
         'success' => true,
         'token_data' => $data,
         'location_token' => $locationToken
      ];
   }

   /**
    * Refreshes the token using the refresh_token.
    */
   public function refreshToken(LocationToken $locationToken)
   {
      $response = Http::asForm()->post(config('services.ghl.api_base') . '/oauth/token', [
         'client_id' => config('services.ghl.client_id'),
         'client_secret' => config('services.ghl.client_secret'),
         'grant_type' => 'refresh_token',
         'refresh_token' => $locationToken->refresh_token,
      ]);

      if (!$response->successful()) {
         Log::error('HighLevel Token Refresh Failed', $response->json());
         return false;
      }

      return $this->saveTokenData($response->json());
   }

   /**
    * Fetches location details (like name) from HighLevel API.
    */
   public function getLocationDetails(string $locationId, string $accessToken)
   {
      $response = Http::withToken($accessToken)
         ->withHeaders([
            'Version' => config('services.ghl.api_version', '2021-07-28'),
            'Accept' => 'application/json',
         ])
         ->get(config('services.ghl.api_base') . "/locations/{$locationId}");

      if (!$response->successful()) {
         Log::error('HighLevel Get Location Details Failed', [
            'location_id' => $locationId,
            'status' => $response->status(),
            'body' => $response->json(),
         ]);
         return null;
      }

      return $response->json('location');
   }

   /**
    * Saves or updates the token data in the database.
    */
   protected function saveTokenData(array $data)
   {
      $locationId = $data['locationId'] ?? ($data['companyId'] ?? null);

      if (!$locationId) {
         return null;
      }

      return LocationToken::updateOrCreate(
         ['location_id' => $locationId],
         [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'user_type' => $data['userType'] ?? 'Location',
            // Typically name isn't here, but if it was we'd save it
         ]
      );
   }
}
