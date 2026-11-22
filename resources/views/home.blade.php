<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel — GeraCert</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #20352f; background: #f3f6f4; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        header { background: #245144; color: white; padding: 20px 24px; }
        header > div, main { width: min(1100px, 100%); margin: auto; }
        header nav { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; }
        header a { color: white; text-decoration: none; }
        header form { margin: 0; }
        header button { color: white; border: 1px solid #c8ddd1; border-radius: 6px; background: transparent; padding: 8px 12px; cursor: pointer; }
        main { padding: 30px 18px 50px; }
        h1, h2 { color: #245144; }
        .welcome { margin-bottom: 22px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 22px 0 30px; }
        .stat, .tile { background: white; border: 1px solid #dbe5df; border-radius: 12px; padding: 20px; box-shadow: 0 4px 16px #163d3008; }
        .stat strong { display: block; font-size: 28px; color: #245144; margin-bottom: 4px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .tile { min-height: 160px; text-decoration: none; color: inherit; transition: transform .15s, box-shadow .15s; }
        a.tile:hover { transform: translateY(-2px); box-shadow: 0 8px 22px #163d3018; }
        .tile h2 { margin: 0 0 9px; font-size: 19px; }
        .tile p { color: #52645c; line-height: 1.5; margin: 0; }
        .tile .icon { font-size: 24px; margin-bottom: 14px; }
        .soon { background: #f8faf8; border-style: dashed; }
        .badge { display: inline-block; margin-top: 12px; padding: 4px 8px; background: #eaf4ed; color: #245144; border-radius: 20px; font-size: 12px; }
        @media (max-width: 760px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 520px) { .grid, .stats { grid-template-columns: 1fr; } header { padding: 16px; } }
    </style>
</head>
<body>
<header>
    <div>
        <nav>
            <a href="{{ route('home') }}"><strong>GeraCert</strong></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sair</button></form>
        </nav>
    </div>
</header>
<main>
    <section class="welcome">
        <h1>Painel de certificados</h1>
        <p>Olá, {{ auth()->user()->name }}. Escolha uma etapa para continuar.</p>
    </section>

    <section class="stats" aria-label="Resumo da conta">
        <div class="stat"><strong>{{ $projectCount }}</strong>projetos</div>
        <div class="stat"><strong>{{ $participantCount }}</strong>participantes</div>
        <div class="stat"><strong>{{ $certificateCount }}</strong>certificados gerados</div>
    </section>

    <section class="grid" aria-label="Opções do sistema">
        <a class="tile" href="{{ route('projects.index') }}">
            <div class="icon">▦</div><h2>Projetos</h2><p>Crie e organize os participantes por projeto.</p>
        </a>
        <a class="tile" href="{{ route('certificate.index') }}">
            <div class="icon">▤</div><h2>Certificados gerados</h2><p>Busque por participante, filtre por projeto e abra ou baixe os PDFs.</p>
        </a>
        <a class="tile" href="{{ route('certificate.generate.form') }}">
            <div class="icon">✦</div><h2>Gerar PDFs</h2><p>Gere certificados para os participantes de um projeto.</p>
        </a>
        <a class="tile" href="{{ route('participants.import.form') }}">
            <div class="icon">⇧</div><h2>Importar lista</h2><p>Adicione participantes a um projeto usando uma planilha.</p>
        </a>
        <a class="tile" href="{{ route('email.send.form') }}">
            <div class="icon">✉</div><h2>Enviar e-mails</h2><p>Enfileire os certificados de um projeto usando seu SMTP.</p>
        </a>
        <a class="tile" href="{{ route('certificate.preview') }}?nome=Participante">
            <div class="icon">◉</div><h2>Prévia do certificado</h2><p>Veja o modelo de certificado atual no navegador.</p>
        </a>
        <a class="tile" href="{{ route('email.preview') }}?nome=Participante">
            <div class="icon">▱</div><h2>Prévia do e-mail</h2><p>Confira o modelo de mensagem antes do envio.</p>
        </a>
        <a class="tile" href="{{ route('smtp.settings.edit') }}">
            <div class="icon">⚙</div><h2>Configurações SMTP</h2><p>Configure e teste as credenciais de envio da sua conta.</p>
        </a>
        <a class="tile" href="{{ route('audit.index') }}">
            <div class="icon">☷</div><h2>Auditoria</h2><p>Consulte as ações administrativas e o histórico de envio.</p>
        </a>
    </section>
</main>
</body>
</html>
