<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importar participantes — GeraCert</title>
    <style>
        * { box-sizing:border-box; } body { margin:0; padding:28px 16px; background:#f3f6f4; color:#20352f; font-family:Arial,sans-serif; }
        main { width:min(850px,100%); margin:auto; } h1,h2 { color:#245144; } nav { display:flex; flex-wrap:wrap; gap:16px; margin:16px 0; } a { color:#17624a; }
        .card { background:white; border:1px solid #dbe5df; border-radius:12px; padding:22px; margin:18px 0; }
        label { display:block; margin:14px 0 6px; font-weight:700; } input,select { width:100%; padding:11px 12px; border:1px solid #b8c9c0; border-radius:7px; font:inherit; background:white; }
        button { border:0; border-radius:7px; padding:12px 18px; margin-top:18px; background:#245144; color:white; font-weight:700; cursor:pointer; }
        .notice { padding:13px 15px; border-radius:7px; background:#eaf4ed; line-height:1.5; } .error { background:#fff0ef; color:#8d241c; }
        .muted { color:#52645c; line-height:1.55; } code { background:#eef2ef; padding:2px 5px; border-radius:4px; }
        @media(max-width:600px){ .card{padding:18px;} }
    </style>
</head>
<body><main>
    <h1>Importar lista de participantes</h1>
    <nav>
        <a href="{{ route('home') }}">Início</a>
        <a href="{{ route('projects.index') }}">Projetos</a>
        <a href="{{ route('certificate.index') }}">Certificados gerados</a>
        <a href="{{ route('certificate.index') }}">Certificados</a>
        <a href="{{ route('certificate.generate.form') }}">Gerar PDFs</a>
        <a href="{{ route('email.send.form') }}">Enviar e-mails</a>
    </nav>

    @if ($errors->any())
        <div class="notice error"><strong>Não foi possível concluir a importação:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($result = session('import_result'))
        <div class="notice">
            <strong>Importação do projeto {{ session('import_project_name') }} concluída.</strong><br>
            {{ $result['added'] }} participante(s) adicionado(s); {{ $result['rejected'] }} linha(s) ignorada(s).
            @if ($result['rejected'] > count($result['errors']))<br>Exibindo até 100 motivos de rejeição.@endif
            @if ($result['errors'])
                <ul>@foreach ($result['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
        </div>
    @endif

    <section class="card">
        <h2>Enviar planilha</h2>
        <p class="muted">Envie um arquivo <code>.xlsx</code> ou <code>.csv</code> com as colunas <code>nome</code> e <code>email</code> na primeira linha. O XLSX usa a primeira aba. Arquivos de até 10 MB e 5.000 linhas são aceitos.</p>
        <p class="muted">Linhas inválidas e e-mails repetidos no mesmo projeto serão ignorados e listados no resultado. E-mails que já constam no projeto também são tratados como repetidos. A planilha não será guardada após o processamento.</p>
        <p><a href="{{ route('participants.import.template') }}">Baixar modelo CSV</a></p>

        @if ($projects->isEmpty())
            <p class="notice">Crie um projeto antes de importar participantes.</p>
            <a href="{{ route('projects.index') }}">Ir para Projetos</a>
        @else
            <form method="POST" action="{{ route('participants.import.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="project_id">Projeto de destino</label>
                <select id="project_id" name="project_id" required>
                    <option value="">Selecione um projeto</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }} ({{ $project->participants_count }} participantes)</option>
                    @endforeach
                </select>

                <label for="file">Arquivo da planilha</label>
                <input id="file" name="file" type="file" accept=".xlsx,.csv,.txt" required>
                <button type="submit">Validar e importar</button>
            </form>
        @endif
    </section>
</main></body>
</html>
