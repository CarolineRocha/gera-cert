<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportLocalTestDatabase extends Command
{
    protected $signature = 'app:export-test-database';

    protected $description = 'Gera um dump SQL local com estrutura e dados de teste anonimizados';

    private const DATALESS_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'jobs', 'password_reset_tokens', 'sessions',
    ];

    public function handle(): int
    {
        if (!app()->environment('local', 'testing')) {
            $this->error('Este dump só pode ser gerado nos ambientes local ou testing.');

            return self::FAILURE;
        }

        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('A exportação requer MySQL ou MariaDB.');

            return self::FAILURE;
        }

        $outputPath = base_path('docs/sql/gera-cert_dump.sql');
        File::ensureDirectoryExists(dirname($outputPath));
        $lines = [
            '-- GeraCert local test dump; credentials, tokens, and personal data are redacted.',
            '-- Recreate an empty database before importing this file.',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        $tables = array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"'));
        sort($tables);
        $firstUserId = DB::table('users')->min('id');

        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $createRow = DB::select('SHOW CREATE TABLE '.$quotedTable)[0] ?? null;
            if (!$createRow) {
                throw new RuntimeException("Não foi possível exportar a definição da tabela {$table}.");
            }

            $createSql = array_values((array) $createRow)[1] ?? null;
            if (!$createSql) {
                throw new RuntimeException("A tabela {$table} não retornou o comando CREATE TABLE.");
            }

            $lines[] = 'DROP TABLE IF EXISTS '.$quotedTable.';';
            $lines[] = $createSql.';';
            $lines[] = '';
        }

        foreach ($tables as $table) {
            if (in_array($table, self::DATALESS_TABLES, true)) {
                continue;
            }

            $rows = DB::table($table)->get();
            foreach ($rows->chunk(100) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $record = $this->redactRow($table, (array) $row, $firstUserId);
                    $values[] = '('.implode(', ', array_map(fn ($value) => $this->quoteValue($value), array_values($record))).')';
                }

                if ($values === []) {
                    continue;
                }

                $columns = array_keys($this->redactRow($table, (array) $chunk->first(), $firstUserId));
                $columnSql = implode(', ', array_map($this->quoteIdentifier(...), $columns));
                $lines[] = 'INSERT INTO '.$this->quoteIdentifier($table).' ('.$columnSql.') VALUES';
                $lines[] = implode(",\n", $values).';';
                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        File::put($outputPath, implode("\n", $lines)."\n");

        $this->info('Dump local criado em docs/sql/gera-cert_dump.sql com estrutura e dados anonimizados.');
        $this->line('Usuários, SMTP, participantes, PDFs, conteúdo renderizado, auditoria identificável, tokens e filas foram redigidos ou neutralizados.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function redactRow(string $table, array $row, mixed $firstUserId): array
    {
        switch ($table) {
            case 'users':
                $id = (int) ($row['id'] ?? 0);
                $row['name'] = $id === (int) $firstUserId ? 'Administrador de teste' : "Usuário de teste {$id}";
                $row['email'] = $id === (int) $firstUserId ? 'admin@example.test' : "usuario{$id}@example.test";
                $row['password'] = '!';
                $row['remember_token'] = null;
                if (array_key_exists('is_admin', $row)) {
                    $row['is_admin'] = false;
                }
                break;
            case 'projects':
                $row['name'] = 'Projeto de teste '.($row['id'] ?? '');
                $row['user_id'] = $firstUserId;
                break;
            case 'participants':
                $id = (int) ($row['id'] ?? 0);
                $row['name'] = "Participante de teste {$id}";
                $row['email'] = "participante{$id}@example.test";
                break;
            case 'smtp_settings':
                $row['user_id'] = $firstUserId;
                $row['host'] = 'smtp.example.test';
                $row['username'] = null;
                $row['password'] = null;
                $row['from_address'] = 'mailer@example.test';
                $row['from_name'] = 'GeraCert Teste';
                break;
            case 'templates':
                $isEmail = ($row['type'] ?? null) === 'email';
                $row['name'] = $isEmail ? 'Template de teste' : 'Certificado de teste';
                $row['html'] = $isEmail
                    ? '<!doctype html><html lang="pt-BR"><body><p>Olá, {{name}}!</p><p>Seu certificado está anexado.</p></body></html>'
                    : '<!doctype html><html lang="pt-BR"><body><h1>{{name}}</h1><p>Certificado de teste</p></body></html>';
                break;
            case 'certificates':
                $row['pdf_path'] = null;
                $row['rendered_html'] = null;
                $row['error_message'] = null;
                break;
            case 'email_logs':
                $participantId = (int) ($row['participant_id'] ?? 0);
                $row['sent_by_user_id'] = $row['sent_by_user_id'] === null ? $firstUserId : $firstUserId;
                $row['recipient'] = $participantId ? "participante{$participantId}@example.test" : 'participante@example.test';
                $row['subject'] = 'E-mail de teste';
                $row['rendered_html'] = null;
                $row['error_message'] = null;
                break;
            case 'audit_logs':
                $row['user_id'] = $row['user_id'] === null ? null : $firstUserId;
                $row['properties'] = null;
                $row['ip_address'] = null;
                $row['user_agent'] = null;
                break;
        }

        return $row;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (string) (int) $value;
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }
}
