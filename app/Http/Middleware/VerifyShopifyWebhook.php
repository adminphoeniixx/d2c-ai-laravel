<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');

        if (!$hmacHeader) {
            return response('Unauthorized', 401);
        }

        $secret = config('services.shopify.secret');

        if (!$secret) {
            return response('Server configuration error', 500);
        }

        $rawBody        = $request->getContent();
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        if (!hash_equals($calculatedHmac, $hmacHeader)) {
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
