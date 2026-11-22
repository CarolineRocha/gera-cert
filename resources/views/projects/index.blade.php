<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projetos — GeraCert</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px; background: #f3f6f4; color: #20352f; font-family: Arial, sans-serif; }
        main { width: min(900px, 100%); margin: 0 auto; }
        h1, h2 { color: #245144; }
        .card { background: #fff; border: 1px solid #dbe5df; border-radius: 12px; padding: 22px; margin: 18px 0; }
        input { width: 100%; padding: 11px 12px; border: 1px solid #b8c9c0; border-radius: 7px; font: inherit; }
        button { border: 0; border-radius: 7px; padding: 11px 16px; margin-top: 12px; background: #245144; color: white; font-weight: 700; cursor: pointer; }
        nav { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
        a { color: #17624a; }
        .muted { color: #52645c; }
        .notice { padding: 12px; border-radius: 7px; background: #eaf4ed; }
        li { margin: 10px 0; }
    </style>
</head>
<body>
<main>
    <h1>Projetos</h1>
    <nav>
        <a href="{{ route('home') }}">Início</a>
        <a href="{{ route('smtp.settings.edit') }}">Configurações SMTP</a>
        <a href="{{ route('certificate.index') }}">Certificados</a>
        <a href="{{ route('participants.import.form') }}">Importar lista</a>
        <a href="{{ route('certificate.generate.form') }}">Gerar certificados</a>
        <a href="{{ route('email.send.form') }}">Enviar certificados</a>
        <a href="{{ route('audit.index') }}">Auditoria</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sair</button></form>
    </nav>

    @if (session('status')) <p class="notice">{{ session('status') }}</p> @endif
    @if ($errors->any()) <p class="notice">{{ $errors->first() }}</p> @endif

    <section class="card">
        <h2>Criar projeto</h2>
        <p class="muted">Cada projeto organiza seus participantes e certificados e pertence à sua conta administrativa.</p>
        <form method="POST" action="{{ route('projects.store') }}">
            @csrf
            <label for="name">Nome do projeto</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Ex.: Treinamento de novembro">
            <button type="submit">Criar projeto</button>
        </form>
    </section>

    <section class="card">
        <h2>Seus projetos</h2>
        @forelse ($projects as $project)
            <li><strong>{{ $project->name }}</strong> — {{ $project->participants_count }} participantes</li>
        @empty
            <p class="muted">Você ainda não criou projetos.</p>
        @endforelse
    </section>
</main>
</body>
</html>
