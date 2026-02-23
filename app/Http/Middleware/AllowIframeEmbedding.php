<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowIframeEmbedding
{
   /**
    * Remove X-Frame-Options header so the page can be embedded in GHL's iFrame.
    */
   public function handle(Request $request, Closure $next)
   {
      $response = $next($request);

      // Remove X-Frame-Options to allow embedding in any iframe (GHL)
      $response->headers->remove('X-Frame-Options');

      return $response;
   }
}
