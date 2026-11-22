<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\SmtpSetting;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Cria a conta administrativa inicial do GeraCert';

    public function handle(): int
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->error('Já existe uma conta administrativa.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nome'));
        $email = strtolower(trim((string) $this->ask('E-mail')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Informe um nome e um endereço de e-mail válidos.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Senha (mínimo de 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
            $this->error('As senhas devem coincidir e ter pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->is_admin = true;
        $user->save();

        // Claim an existing installation-wide SMTP row when bootstrapping the first admin.
        SmtpSetting::query()->whereNull('user_id')->orderBy('id')->first()?->update(['user_id' => $user->id]);
        Project::query()->whereNull('user_id')->update(['user_id' => $user->id]);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'auth.admin_created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'properties' => ['source' => 'artisan'],
        ]);

        $this->info('Conta administrativa criada.');

        return self::SUCCESS;
    }
}
