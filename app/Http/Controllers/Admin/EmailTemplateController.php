<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\PaymentSetting;
use App\Services\BrevoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Emails/Index', [
            'templates' => EmailTemplate::orderBy('id')->get(),
            'settings'  => [
                'brevo_api_key'      => PaymentSetting::getValue('brevo_api_key', ''),
                'brevo_sender_email' => PaymentSetting::getValue('brevo_sender_email', 'noreply@heyd2c.ai'),
                'brevo_sender_name'  => PaymentSetting::getValue('brevo_sender_name', 'heyd2c'),
                'emails_enabled'     => PaymentSetting::getValue('emails_enabled', '1'),
            ],
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $data = $request->validate([
            'subject'   => 'required|string|max:200',
            'html_body' => 'required|string',
            'text_body' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $emailTemplate->update($data);
        return back()->with('success', 'Template saved.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'brevo_api_key'      => 'nullable|string',
            'brevo_sender_email' => 'required|email',
            'brevo_sender_name'  => 'required|string|max:60',
            'emails_enabled'     => 'boolean',
        ]);
        foreach ($data as $key => $value) {
            PaymentSetting::setValue($key, $value);
        }
        return back()->with('success', 'Email settings saved.');
    }

    public function testConnection()
    {
        $result = app(BrevoService::class)->testConnection();
        return response()->json($result);
    }

    public function sendTest(Request $request, EmailTemplate $emailTemplate)
    {
        $request->validate(['email' => 'required|email']);

        $vars = collect($emailTemplate->variables ?? [])->mapWithKeys(fn($v) => [$v => "[{$v}]"])->toArray();
        $vars['dashboard_url'] = url('/app/demo/dashboard');
        $vars['login_url']     = url('/login/otp');
        $vars['renew_url']     = url('/app/demo/subscription/plans');
        $vars['upgrade_url']   = url('/app/demo/subscription/plans');

        $ok = app(BrevoService::class)->sendTemplate(
            $emailTemplate->slug,
            $request->email,
            'Test Recipient',
            $vars
        );

        return response()->json(['success' => $ok, 'message' => $ok ? 'Test email sent!' : 'Failed to send. Check Brevo API key.']);
    }
}
