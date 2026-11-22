# Segurança e dados

## Credenciais

- `.env` contém a chave da aplicação, conexão do banco e opções locais; permanece fora do Git.
- `APP_KEY` deve ser estável no ambiente em que as credenciais SMTP foram salvas, pois o cast `encrypted` depende dela.
- Senhas SMTP nunca são exibidas após salvar. Não registrar senhas em logs, auditoria, mensagens de erro ou templates.
- A recuperação de senha é restrita a administradores e usa resposta genérica para reduzir enumeração de contas.

## Controle de acesso

- Rotas operacionais exigem login e `is_admin`.
- Projetos pertencem a usuários; ações e consultas são limitadas aos projetos próprios.
- Jobs revalidam propriedade no momento da execução, não apenas quando são enfileirados.
- PDFs são transmitidos pelo controlador após autorização; `public/pdf/.htaccess` bloqueia acesso HTTP direto.

## Auditoria e privacidade

`audit_logs` registra ações e metadados de operação, sem senhas SMTP ou HTML integral de mensagens. `email_logs` guarda os destinatários e o administrador responsável. Proteja o banco de desenvolvimento e evite dados reais de participantes em ambientes de teste.

## Arquivos locais e Git

- `.env`, `vendor`, assets compilados e PDFs gerados são ignorados pelo Git.
- `docs/sql/gera-cert_dump.sql` é um dump local ignorado pelo Git e não deve ser enviado ao repositório. Gere-o com `php artisan app:export-test-database`; esse comando anonimiza dados pessoais e redige segredos.
- Dumps podem conter pessoas, destinatários e metadados. Esta versão é destinada a dados de teste; campos de credenciais e identificadores sensíveis devem permanecer redigidos.
- Antes de compartilhar alterações, confira `git status --short` e `git check-ignore -v .env docs/sql/gera-cert_dump.sql public/pdf/arquivo.pdf`.

## Backups

O dump em `docs/sql/gera-cert_dump.sql` deve conter estrutura e dados de teste locais; ele não é backup versionado nem substitui cópias de segurança controladas. Para restaurar, crie um banco vazio, importe o SQL pelo phpMyAdmin ou cliente MySQL e mantenha a chave `APP_KEY` correta se optar por preservar qualquer configuração SMTP criptografada. O dump de teste deste fluxo remove credenciais e dados pessoais.
