<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerar certificados — GeraCert</title>
    <style>
        * { box-sizing: border-box; } body { margin: 0; padding: 28px 16px; background: #f3f6f4; color: #20352f; font-family: Arial, sans-serif; }
        main { width: min(760px,100%); margin: 0 auto; } h1 { color: #245144; } .card { background:white; border:1px solid #dbe5df; border-radius:12px; padding:22px; margin:18px 0; }
        select { width:100%; padding:11px; border:1px solid #b8c9c0; border-radius:7px; font:inherit; } button { border:0; border-radius:7px; padding:12px 16px; margin-top:16px; background:#245144; color:#fff; font-weight:700; cursor:pointer; }
        nav { display:flex; flex-wrap:wrap; gap:16px; align-items:center; } a { color:#17624a; } .notice { padding:12px; border-radius:7px; background:#eaf4ed; }
    </style>
</head>
<body><main>
    <h1>Gerar certificados</h1>
    <nav><a href="{{ route('home') }}">Início</a><a href="{{ route('projects.index') }}">Projetos</a><a href="{{ route('certificate.index') }}">Certificados gerados</a><a href="{{ route('email.send.form') }}">Enviar certificados</a><a href="{{ route('audit.index') }}">Auditoria</a></nav>
    @if (session('status')) <p class="notice">{{ session('status') }}</p> @endif
    @if ($errors->any()) <p class="notice">{{ $errors->first() }}</p> @endif
    <section class="card">
        <p>A geração será limitada aos participantes do projeto selecionado.</p>
        <form method="POST" action="{{ route('certificate.generate') }}">
            @csrf
            <label for="project_id">Projeto</label>
            <select id="project_id" name="project_id" required>
                <option value="">Selecione</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }} ({{ $project->participants_count }} participantes)</option>
                @endforeach
            </select>
            <button type="submit" @disabled($projects->isEmpty())>Gerar certificados do projeto</button>
        </form>
    </section>
</main></body>
</html>
