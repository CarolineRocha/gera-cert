# GeraCert

Aplicação Laravel para organizar participantes por projeto, gerar certificados em PDF e enviá-los por e-mail. O sistema tem acesso administrativo fechado, configurações SMTP por conta e registro de auditoria.

## Estado do projeto

O fluxo local disponível cobre:

1. Autenticação administrativa, sem cadastro público.
2. Criação de projetos e importação de participantes por CSV ou XLSX.
3. Geração de certificados PDF a partir do template ativo.
4. Busca, paginação, visualização e download dos certificados gerados.
5. Configuração e teste de SMTP por administrador.
6. Enfileiramento de e-mails com certificado anexo e acompanhamento de ações na auditoria.

## Tecnologias

- Laravel 13 e PHP 8.3 ou superior.
- MySQL/MariaDB no ambiente local WAMP.
- DomPDF para geração de PDF.
- Extensões PHP `pdo_mysql`, `zip`, `simplexml`, `mbstring` e `dom`.
- PHPUnit para testes automatizados, executados em um banco MySQL de teste separado.

## Início rápido no WAMP

```bash
cd /c/wamp64/www/gera-cert
composer install
test -f .env || cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan app:create-admin
php artisan db:seed --class='Database\Seeders\EmailTemplateSeeder'
```

Antes das migrations, crie o banco `db_gera_cert` e configure a conexão MySQL em `.env`. Acesse `http://localhost/gera-cert/public/` depois de iniciar Apache e MySQL no WAMP. O administrador inicial é criado no terminal; não há rota de cadastro público.

Para processar a fila de e-mails, mantenha um terminal adicional aberto:

```bash
php artisan queue:work --tries=1
```

## Documentação

- [Instalação e configuração WAMP](docs/INSTALACAO-WAMP.md)
- [Fluxo operacional](docs/FLUXO-OPERACIONAL.md)
- [Arquitetura e dados](docs/ARQUITETURA.md)
- [Segurança e backups](docs/SEGURANCA-E-DADOS.md)
- [Testes automatizados](docs/TESTES.md)
- [Guia de testes local](docs/GUIA-DE-TESTES.md)
- [Dump local do banco](docs/sql/README.md)

## Rotas principais

| Recurso | Caminho |
| --- | --- |
| Painel | `/` |
| Projetos | `/projetos` |
| Importação de participantes | `/importar` |
| Certificados gerados | `/certificados` |
| Gerar PDFs | `/generate` |
| Enviar certificados | `/send` |
| SMTP | `/configuracoes/smtp` |
| Auditoria | `/admin/auditoria` |

As rotas da aplicação, exceto autenticação e recuperação de senha, exigem sessão autenticada e permissão administrativa.
