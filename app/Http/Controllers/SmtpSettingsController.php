<?php

namespace App\Http\Controllers;

use App\Models\SmtpSetting;
use App\Services\AuditLogger;
use App\Services\SmtpSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SmtpSettingsController extends Controller
{
    public function __construct(
        private readonly SmtpSettingsManager $smtpSettingsManager,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function edit(Request $request)
    {
        $this->ensureAdminAccess($request);

        $stored = SmtpSetting::query()->where('user_id', $request->user()->id)->first();
        $envPassword = config('mail.mailers.smtp.password');

        return view('settings.smtp', [
            'settings' => [
                'scheme' => $stored?->scheme ?? config('mail.mailers.smtp.scheme') ?? 'smtp',
                'host' => $stored?->host ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                'port' => $stored?->port ?? config('mail.mailers.smtp.port', 587),
                'username' => $stored?->username ?? config('mail.mailers.smtp.username'),
                'from_address' => $stored?->from_address ?? config('mail.from.address'),
                'from_name' => $stored?->from_name ?? config('mail.from.name'),
                'password_configured' => filled($stored?->password ?? $envPassword),
                'password_from_env' => !$stored && filled($envPassword),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'scheme' => ['required', 'in:smtp,smtps'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SmtpSetting::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);
        $hadStoredPassword = $setting->exists && filled($setting->password);

        $setting->fill([
            'scheme' => $data['scheme'],
            'host' => $data['host'],
            'port' => $data['port'],
            'username' => $data['username'] ?? null,
            'from_address' => $data['from_address'],
            'from_name' => $data['from_name'],
        ]);

        if (filled($data['password'] ?? null)) {
            $setting->password = preg_replace('/\s+/', '', $data['password']);
        } elseif (!$hadStoredPassword && filled(config('mail.mailers.smtp.password'))) {
            // Carry forward an existing .env password on first save without exposing it in the form.
            $setting->password = config('mail.mailers.smtp.password');
        }

        $setting->save();

        $this->auditLogger->record($request->user(), 'smtp.updated', $setting, [
            'host' => $setting->host,
            'port' => $setting->port,
            'scheme' => $setting->scheme,
            'username' => $setting->username,
            'from_address' => $setting->from_address,
            'from_name' => $setting->from_name,
        ], $request);

        return redirect()
            ->route('smtp.settings.edit')
            ->with('status', 'Configurações SMTP salvas. A senha fica protegida e não é exibida novamente.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);

        if (!SmtpSetting::query()->where('user_id', $request->user()->id)->exists()) {
            return back()->withErrors([
                'recipient' => 'Salve as configurações SMTP antes de enviar um teste.',
            ])->withInput();
        }

        try {
            $this->smtpSettingsManager->mailer($request->user())->raw(
                'Este é um e-mail de teste das configurações SMTP do GeraCert.',
                function ($message) use ($data) {
                    $message->to($data['recipient'])
                        ->subject('Teste de configuração SMTP - GeraCert');
                }
            );
        } catch (Throwable $exception) {
            $this->auditLogger->record($request->user(), 'smtp.test_failed', null, [
                'recipient' => $data['recipient'],
                'exception' => class_basename($exception),
            ], $request);

            return back()->withErrors([
                'recipient' => 'Não foi possível enviar. Confira host, porta, segurança, usuário e senha de app.',
            ])->withInput();
        }

        $this->auditLogger->record($request->user(), 'smtp.test_sent', null, [
            'recipient' => $data['recipient'],
        ], $request);

        return redirect()
            ->route('smtp.settings.edit')
            ->with('status', 'E-mail de teste enviado para '.$data['recipient'].'.');
    }

    private function ensureAdminAccess(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
