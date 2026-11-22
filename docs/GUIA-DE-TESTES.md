# Guia de validação local

Este guia cobre a validação manual no WAMP. A suíte automatizada e seu banco MySQL isolado estão descritos em [TESTES.md](TESTES.md).

## Preparação

Inicie Apache e MySQL no WAMP. No Git Bash, na raiz do projeto, confirme dependências, migrations, administrador e template de e-mail:

```bash
composer install
php artisan migrate
php artisan app:create-admin
php artisan db:seed --class='Database\Seeders\EmailTemplateSeeder'
```

O banco da aplicação é MySQL/MariaDB, configurado em `.env`. Entre em `http://localhost/gera-cert/public/login`.

## Fluxo recomendado

1. Em `http://localhost/gera-cert/public/projetos`, crie um projeto de teste.
2. Em `http://localhost/gera-cert/public/importar`, baixe o modelo CSV ou envie CSV/XLSX com cabeçalhos `nome,email`.
3. Confira o resultado da importação, incluindo contagens e linhas ignoradas.
4. Em `/generate`, escolha o projeto para criar os PDFs.
5. Em `/certificados`, pesquise por nome ou e-mail, filtre pelo projeto, navegue pelas páginas e visualize/baixe o PDF.
6. Configure SMTP em `/configuracoes/smtp`. O teste SMTP envia uma mensagem real; use um endereço controlado por você.
7. Em `/send`, selecione o projeto para criar a fila. Inicie `php artisan queue:work --tries=1` em outro terminal.
8. Confira `/admin/auditoria` e os registros de `email_logs`.

Prévia HTML: `http://localhost/gera-cert/public/preview?nome=Pessoa%20Teste`.

Prévia do e-mail: `http://localhost/gera-cert/public/email?nome=Pessoa%20Teste`.

## Importação de planilha

O arquivo deve ter até 10 MB e 5.000 linhas de dados. CSV aceita vírgula, ponto e vírgula ou tabulação; XLSX usa a primeira aba. Nome e e-mail são obrigatórios. O importador ignora e reporta valores inválidos e e-mails repetidos no mesmo projeto. Linhas válidas são importadas mesmo quando outras falham.

## Cuidados no ambiente de teste

- Use destinatários fictícios ou controlados por você.
- A rota de envio enfileira todos os participantes do projeto que tenham certificado; não selecione um subconjunto ainda.
- O worker processa a fila real. `Queue::fake()` e mailers simulados são usados somente nos testes automatizados.
- `public/pdf/` contém os arquivos locais gerados e é ignorada pelo Git.
- Não importe o dump de teste sobre o banco de trabalho sem antes selecionar um banco vazio.

## Resolução de problemas

- `Template não encontrado`: confira se existe um template ativo `type = 'certificate'`.
- Erro SQLSTATE 2002: inicie MySQL/MariaDB no WAMP e valide host/porta no `.env`.
- Erro de leitura XLSX: habilite `zip` e `simplexml` no PHP CLI e Apache.
- Rotas antigas em cache: execute `php artisan route:clear`.
- Aviso de DLL do Xdebug: é uma configuração do PHP CLI; se os comandos ainda executarem, o aviso não indica falha de migration ou aplicação.
