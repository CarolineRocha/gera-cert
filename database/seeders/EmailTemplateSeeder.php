<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (Template::query()->where('type', 'email')->where('name', 'Template de teste')->exists()) {
            $this->command?->info('O template de teste já existe; nenhum conteúdo foi alterado.');

            return;
        }

        Template::unguarded(function (): void {
            Template::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Template de teste',
                'type' => 'email',
                'version' => 1,
                'html' => <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Seu certificado</title></head>
<body style="margin:0;padding:24px;background:#f3f6f4;font-family:Arial,sans-serif;color:#20352f">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dbe5df;border-radius:10px">
        <tr><td style="padding:28px">
            <h1 style="margin:0 0 20px;color:#245144;font-size:22px">Olá, {{name}}!</h1>
            <p style="font-size:16px;line-height:1.6">Este é um e-mail de teste do GeraCert.</p>
            <p style="font-size:16px;line-height:1.6">Seu certificado está anexado a esta mensagem.</p>
            <p style="margin:28px 0 0;font-size:14px;color:#52645c">Atenciosamente,<br>Equipe GeraCert</p>
        </td></tr>
    </table>
</body>
</html>
HTML,
            ]);
        });

        $this->command?->info('Template de teste de e-mail criado.');
    }
}
