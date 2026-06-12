<?php

declare(strict_types=1);

namespace App\Services\Integrations\Meta;

use Illuminate\Support\Facades\Http;

class MetaOAuth
{
    protected string $appId;
    protected string $appSecret;

    public function __construct()
    {
        $this->appId = config('services.meta.app_id') ?? '';
        $this->appSecret = config('services.meta.app_secret') ?? '';
    }

    public function buildAuthorizeUrl(string $redirectUri, string $state): string
    {
        $scopes = config('services.meta.scopes', 'ads_read,ads_management,read_insights');
        return 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => $scopes,
            'response_type' => 'code',
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Meta OAuth token exchange failed: ' . $response->body());
        }

        $data = $response->json();
        $shortToken = $data['access_token'];

        // Exchange for long-lived token (60 days)
        $longResp = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'grant_type'    => 'fb_exchange_token',
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        if ($longResp->successful()) {
            $longData = $longResp->json();
            return [
                'access_token' => $longData['access_token'],
                'expires_in'   => $longData['expires_in'] ?? 5184000,
            ];
        }

        return ['access_token' => $shortToken, 'expires_in' => $data['expires_in'] ?? 3600];
    }
}
