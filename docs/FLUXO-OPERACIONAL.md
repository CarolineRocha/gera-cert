# Fluxo operacional

## 1. Entrar

Acesse `/login` com a conta administrativa criada pelo comando `php artisan app:create-admin`. Não há cadastro público. A home resume projetos, participantes e certificados, com atalhos para as etapas disponíveis.

## 2. Criar um projeto

Em `/projetos`, crie um projeto para cada lista ou atividade. Cada projeto pertence à conta administrativa autenticada; geração, consulta e envio verificam essa propriedade.

## 3. Importar participantes

Em `/importar`, escolha o projeto e envie `.csv` ou `.xlsx`. A primeira linha deve conter as colunas `nome` e `email` (também aceitos `name` e `e-mail`). XLSX usa a primeira aba. O limite atual é 10 MB e 5.000 linhas.

O importador aceita linhas válidas mesmo quando outras linhas são recusadas. Nomes e e-mails inválidos e e-mails repetidos dentro do arquivo ou já presentes no projeto aparecem no resultado. Arquivos enviados são processados temporariamente e não são armazenados pela aplicação.

## 4. Gerar e consultar certificados

Em `/generate`, escolha um projeto. A aplicação cria um PDF por participante desse projeto usando o template ativo de certificado. Falhas e quantidades geradas são registradas na auditoria.

Em `/certificados`, busque por nome ou e-mail, filtre por projeto e navegue pelas páginas de resultados. Cada linha representa um certificado gerado e oferece visualização e download do PDF. O histórico pode conter mais de um certificado por participante se a geração for executada novamente.

## 5. Configurar e enviar e-mails

Em `/configuracoes/smtp`, configure host, porta, segurança, usuário e senha de app SMTP. A senha é armazenada criptografada com a chave `APP_KEY`. Para Gmail, use verificação em duas etapas e uma senha de app, não a senha normal da conta. O formulário de teste envia uma mensagem real ao endereço informado.

Em `/send`, selecione um projeto. Só participantes desse projeto com certificado serão adicionados à fila. Cada job guarda o ID do administrador que iniciou o envio, usa o SMTP dessa conta e anexa o certificado correspondente. A fila usa atraso de 40 segundos entre participantes para espaçar os envios.

O worker pode ser iniciado em um terminal separado:

```bash
php artisan queue:work --tries=1
```

`email_logs` registra status, destinatário, certificado e administrador remetente. A rota de fila não envia diretamente; com `QUEUE_CONNECTION=database`, o worker precisa estar rodando para processar os jobs.

## 6. Auditoria

Em `/admin/auditoria`, consulte até as 200 ações mais recentes: criação de projeto, login/logout, alterações SMTP, testes SMTP, importação, geração de certificados, criação de fila e visualização/download de PDFs. A auditoria evita armazenar senhas SMTP e conteúdo completo dos e-mails.
