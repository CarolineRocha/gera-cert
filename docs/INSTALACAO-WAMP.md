# Instalação local no WAMP

## Requisitos

- WAMP com Apache e MySQL/MariaDB iniciados.
- PHP 8.3 ou superior com `pdo_mysql`, `zip`, `simplexml`, `mbstring` e `dom` habilitadas no CLI e no Apache.
- Composer instalado e usando uma versão PHP compatível com o WAMP.
- Git e, opcionalmente, Node.js/npm para compilar os assets.

## Preparar o projeto

No Git Bash:

```bash
cd /c/wamp64/www/gera-cert
composer install
test -f .env || cp .env.example .env
php artisan key:generate
```

Crie um banco vazio chamado `db_gera_cert` no phpMyAdmin ou cliente MySQL. Configure `.env` com `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=db_gera_cert` e as credenciais locais do MySQL. `.env` contém configuração privada e não deve ser adicionado ao Git.

Crie as tabelas e a conta inicial:

```bash
php artisan migrate
php artisan app:create-admin
php artisan db:seed --class='Database\Seeders\EmailTemplateSeeder'
```

O comando de administrador pede nome, e-mail e senha com pelo menos 12 caracteres. Se o banco já continha projetos sem proprietário, a conta administrativa inicial os adota. O seeder adiciona um template de e-mail chamado **Template de teste** se esse nome ainda não existir; ele não altera outros templates.

## Acessar pelo navegador

Com Apache e MySQL ativos:

- Aplicação: `http://localhost/gera-cert/public/`
- Login: `http://localhost/gera-cert/public/login`

O diretório `public` é o document root da aplicação Laravel. Em WAMP, o uso de `/public` na URL é necessário quando não há VirtualHost configurado. O `.htaccess` do projeto depende de `mod_rewrite` e `AllowOverride All`.

## Assets opcionais

Se houver mudanças em CSS ou JavaScript:

```bash
npm install
npm run build
```

## Erros comuns

- `php: command not found`: selecione o PHP do WAMP no PATH do Git Bash. O executável instalado atualmente costuma estar em `C:\wamp64\bin\php\php8.x.x\php.exe`.
- Erro SQLSTATE 2002: confirme se o MySQL/MariaDB está iniciado e se host/porta correspondem ao `.env`.
- `Template não encontrado`: confirme que há uma linha ativa em `templates` com `type = 'certificate'`.
- Arquivos XLSX não abrem: habilite a extensão PHP `zip` no CLI e no Apache.
- As rotas exigem login: use `php artisan app:create-admin` para criar a primeira conta administrativa.
