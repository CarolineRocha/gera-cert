# Testes automatizados

## Banco isolado

O aplicativo local usa MySQL. A suíte também usa MySQL, mas exclusivamente no banco separado `db_gera_cert_test`, configurado em `phpunit.xml`. **Não altere esse nome para `db_gera_cert`**: o trait `RefreshDatabase` reinicializa o esquema do banco de teste.

Crie o banco de teste uma vez no phpMyAdmin:

```sql
CREATE DATABASE db_gera_cert_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ou, com o usuário MySQL configurado no `.env`, crie esse schema em phpMyAdmin antes da primeira execução. Os testes nunca devem usar `db_gera_cert`.

O usuário/host do teste são lidos da configuração MySQL local (`.env`). Confirme que o MySQL está ativo e que essa conta pode criar/alterar tabelas no banco de teste.

## Executar

Na raiz do projeto:

```bash
php artisan test
```

Também é possível executar um grupo:

```bash
php artisan test --filter=ParticipantImportFlowTest
php artisan test --filter=CertificateFlowsTest
php artisan test --filter=EmailAndSmtpFlowsTest
```

## Cobertura

- Autenticação administrativa, recusa de usuário comum, logout e redefinição de senha.
- Criação/escopo de projetos.
- Importação CSV e XLSX, cabeçalhos, validação, duplicados e tentativa de importar em projeto alheio.
- Busca e paginação dos certificados, geração por projeto, prévia, visualização/download autorizados e auditoria.
- Configuração SMTP criptografada, teste SMTP com mailer simulado, fila sem envio real e trilha de sucesso/falha.
- Template de teste de e-mail idempotente e escaping de valores de template.

Os testes criam registros fictícios em cada execução. Fila, mailer e PDF de geração são simulados onde aplicável; nenhum e-mail é enviado a uma conta real. O teste de visualização usa um PDF temporário e o remove ao terminar.
