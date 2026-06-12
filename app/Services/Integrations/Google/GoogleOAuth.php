<?php

declare(strict_types=1);

namespace App\Services\Integrations\Google;

use Illuminate\Support\Facades\Http;

class GoogleOAuth
{
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.google_ads.client_id') ?? '';
        $this->clientSecret = config('services.google_ads.client_secret') ?? '';
    }

    public function buildAuthorizeUrl(string $redirectUri, string $state): string
    {
        $scopes = 'https://www.googleapis.com/auth/adwords';
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => $scopes,
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Google OAuth token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Google token refresh failed: ' . $response->body());
        }

        return $response->json();
    }
}
