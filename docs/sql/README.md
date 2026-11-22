# Dump local do banco

`gera-cert_dump.sql` é gerado localmente em `docs/sql/` com esquema e dados de teste anonimizados. O arquivo é explicitamente ignorado pelo Git para não versionar registros, hashes de senha, configurações SMTP ou destinatários.

Depois de iniciar o MySQL no WAMP, gere/atualize o dump na raiz do projeto:

```bash
php artisan app:export-test-database
```

O comando exporta todas as definições de tabelas e os dados funcionais anonimizados. Ele redige usuários, SMTP, destinatários, auditoria identificável e conteúdo renderizado; também omite linhas de sessões, tokens, cache e filas. Não exporta PDFs físicos. Ao restaurar, use um banco vazio. A conta de teste desativada tem e-mail `admin@example.test`; recrie-a com `php artisan app:create-admin` usando esse e-mail para adotar os projetos existentes no dump.
