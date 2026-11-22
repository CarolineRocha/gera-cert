<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmtpSettingsManager;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly SmtpSettingsManager $smtpSettingsManager,
        private readonly AuditLogger $auditLogger
    )
    {
    }

    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'E-mail ou senha inválidos.',
            ])->onlyInput('email');
        }

        if (!$request->user()?->is_admin) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Esta conta não tem acesso administrativo.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->auditLogger->record($request->user(), 'auth.login', $request->user(), [], $request);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $this->auditLogger->record($request->user(), 'auth.logout', $request->user(), [], $request);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $admin = User::query()->where('email', $data['email'])->where('is_admin', true)->first();
            if ($admin) {
                $this->smtpSettingsManager->mailer($admin);
                Password::sendResetLink(['email' => $data['email'], 'is_admin' => true]);
            }
        } catch (Throwable $exception) {
            Log::warning('Password reset email could not be sent.', [
                'exception' => class_basename($exception),
            ]);
        }

        return back()->with(
            'status',
            'Se o e-mail pertencer à conta administrativa, enviaremos as instruções de redefinição.'
        );
    }

    public function resetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::reset($data + ['is_admin' => true], function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
            $this->auditLogger->record($user, 'auth.password_reset', $user);
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Senha redefinida. Entre com sua nova senha.');
        }

        return back()->withErrors([
            'email' => __($status),
        ])->withInput($request->only('email'));
    }
}
