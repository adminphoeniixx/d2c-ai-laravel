<?php
namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Http;

class BrevoService
{
    private string $apiKey;
    private string $senderEmail;
    private string $senderName;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey      = PaymentSetting::getValue('brevo_api_key', '');
        $this->senderEmail = PaymentSetting::getValue('brevo_sender_email', 'noreply@heyd2c.ai');
        $this->senderName  = PaymentSetting::getValue('brevo_sender_name', 'heyd2c');
        $this->enabled     = (bool) PaymentSetting::getValue('emails_enabled', '1');
    }

    public function sendTemplate(string $slug, string $toEmail, string $toName, array $vars): bool
    {
        if (!$this->enabled || !$this->apiKey) {
            return false;
        }

        $template = EmailTemplate::where('slug', $slug)->where('is_active', true)->first();
        if (!$template) return false;

        $rendered = $template->render($vars);

        try {
            $response = Http::withHeaders([
                'api-key'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender'      => ['email' => $this->senderEmail, 'name' => $this->senderName],
                'to'          => [['email' => $toEmail, 'name'  => $toName]],
                'subject'     => $rendered['subject'],
                'htmlContent' => $rendered['html'],
                'textContent' => $rendered['text'],
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            return false;
        }
    }

    // Convenience methods
    public function sendWelcome(string $email, string $name, string $companyName, string $dashboardUrl): bool
    {
        return $this->sendTemplate('register', $email, $name, [
            'owner_name'    => $name,
            'company_name'  => $companyName,
            'login_url'     => url('/login/otp'),
            'dashboard_url' => $dashboardUrl,
        ]);
    }

    public function sendSubscriptionActivated(string $email, string $name, array $data): bool
    {
        return $this->sendTemplate('subscription_activated', $email, $name, $data);
    }

    public function sendSubscriptionExpiring(string $email, string $name, array $data): bool
    {
        return $this->sendTemplate('subscription_expiring', $email, $name, $data);
    }

    public function sendSubscriptionExpired(string $email, string $name, array $data): bool
    {
        return $this->sendTemplate('subscription_expired', $email, $name, $data);
    }

    public function testConnection(): array
    {
        if (!$this->apiKey) return ['success' => false, 'error' => 'No API key configured'];

        try {
            $res = Http::withHeaders(['api-key' => $this->apiKey])
                ->get('https://api.brevo.com/v3/account');

            return $res->successful()
                ? ['success' => true, 'account' => $res->json('companyName') ?? $res->json('email')]
                : ['success' => false, 'error' => $res->json('message') ?? 'API error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
