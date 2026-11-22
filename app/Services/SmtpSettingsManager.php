<?php

namespace App\Services;

use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SmtpSettingsManager
{
    public function mailer(User $user): Mailer
    {
        $settings = SmtpSetting::query()->where('user_id', $user->id)->first();

        if (!$settings) {
            throw new \RuntimeException('Configure as credenciais SMTP da sua conta antes de enviar e-mails.');
        }

        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'scheme' => $settings->scheme,
            'url' => null,
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password' => $settings->password,
            'timeout' => 15,
        ]));

        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', $settings->from_address);
        Config::set('mail.from.name', $settings->from_name);

        Mail::purge('smtp');

        return Mail::mailer('smtp');
    }
}
