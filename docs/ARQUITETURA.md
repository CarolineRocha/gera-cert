# Arquitetura e modelo de dados

## Estrutura da aplicação

- `routes/web.php`: rotas públicas de login/recuperação e rotas protegidas por `auth` e `admin`.
- `app/Http/Controllers`: autenticação, projetos, importação, certificados, envio SMTP e auditoria.
- `app/Services`: parser de participantes, renderização de templates, geração PDF, SMTP e trilha de auditoria.
- `app/Jobs/SendCertificateEmailJob.php`: envio assíncrono por participante/certificado.
- `database/migrations`: esquema incremental do MySQL/MariaDB.
- `database/seeders/EmailTemplateSeeder.php`: template de e-mail de teste idempotente.
- `resources/views`: telas Blade da aplicação.

## Entidades e relações

| Tabela | Responsabilidade | Relações principais |
| --- | --- | --- |
| `users` | Contas administrativas e autenticação | Possui projetos e uma configuração SMTP |
| `smtp_settings` | Configuração SMTP por usuário; senha criptografada por cast Eloquent | Pertence a `users` |
| `projects` | Agrupamento e propriedade dos dados | Pertence a um usuário; possui participantes |
| `participants` | Nome e e-mail importados | Pertence a um projeto; possui certificados |
| `templates` | HTML de certificado ou e-mail e versão | Compartilhado atualmente pela instalação |
| `certificates` | PDF gerado, template usado, estado e horário | Pertence a participante e template |
| `email_logs` | Destinatário, estado, certificado e administrador remetente | Referencia participante, certificado e usuário |
| `audit_logs` | Ações administrativas, propriedades não secretas, IP e agente | Referencia usuário e entidade afetada |
| `jobs`, `failed_jobs` | Fila e falhas de processamento assíncrono | Infraestrutura de filas do Laravel |

`projects.user_id` define a fronteira de propriedade. Participantes e certificados são consultados através do projeto para garantir o escopo do administrador. As rotas de visualização e download de PDF repetem essa verificação e só aceitam arquivos resolvidos dentro de `public/pdf`.

## Processamento de planilhas

`ParticipantImportService` lê CSV com `fgetcsv` e XLSX com `ZipArchive` e `SimpleXML`, sem dependência Composer adicional. O serviço valida cabeçalhos, nome, e-mail, duplicidade no projeto e limites de tamanho/linhas; grava linhas válidas em lotes e devolve rejeições com número de linha.

## Fluxo de e-mail

O controlador seleciona um projeto pertencente ao usuário e enfileira apenas participantes com certificado. O job revalida que o participante e o certificado correspondem e que o projeto continua pertencendo ao remetente. O `EmailService` carrega a configuração SMTP do usuário, envia a mensagem e registra sucesso/falha. O template de e-mail mais recente é usado para prévia e envio.

## Limites atuais

- Templates são globais, não vinculados a usuário ou projeto.
- O envio seleciona todos os participantes certificados do projeto; ainda não há seleção individual ou cancelamento de fila na interface.
- A conta administrativa inicial é criada no terminal. Não existe interface para cadastrar administradores adicionais.
- PDFs são arquivos locais em `public/pdf`; a pasta é ignorada pelo Git e tem acesso HTTP direto negado por `.htaccess`.
