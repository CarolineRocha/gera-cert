<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificados — GeraCert</title>
    <style>
        * { box-sizing: border-box; } body { margin: 0; padding: 28px 16px; background: #f3f6f4; color: #20352f; font-family: Arial,sans-serif; }
        main { width: min(1100px,100%); margin: auto; } h1 { color: #245144; } nav { display:flex; flex-wrap:wrap; gap:16px; margin:16px 0; } a { color:#17624a; }
        .filters { display:flex; flex-wrap:wrap; align-items:end; gap:12px; background:#fff; border:1px solid #dbe5df; border-radius:10px; padding:16px; margin:20px 0; }
        .filters label { display:block; font-weight:700; margin-bottom:6px; } .filters input,.filters select { min-width:220px; padding:10px; border:1px solid #b8c9c0; border-radius:7px; font:inherit; }
        .filters button { border:0; border-radius:7px; padding:11px 16px; background:#245144; color:white; font-weight:700; cursor:pointer; } .filters .clear { padding:10px 0; }
        .wrap { overflow:auto; background:#fff; border:1px solid #dbe5df; border-radius:10px; } table { width:100%; border-collapse:collapse; min-width:700px; }
        th,td { padding:12px; border-bottom:1px solid #e5ece8; text-align:left; } th { background:#eaf2ed; } .muted { color:#52645c; }
        .actions { display:flex; gap:12px; } .actions a { white-space:nowrap; }
    </style>
</head>
<body><main>
    <h1>Certificados gerados</h1>
    <nav><a href="{{ route('home') }}">Início</a><a href="{{ route('projects.index') }}">Projetos</a><a href="{{ route('certificate.generate.form') }}">Gerar PDFs</a><a href="{{ route('email.send.form') }}">Enviar e-mails</a></nav>
    <p class="muted">Busque por participante ou filtre por projeto. São exibidos somente certificados dos seus projetos.</p>
    <form class="filters" method="GET" action="{{ route('certificate.index') }}">
        <div><label for="q">Buscar por nome ou e-mail</label><input id="q" name="q" type="search" value="{{ $search }}" maxlength="255" placeholder="Ex.: Ana ou ana@exemplo.com"></div>
        <div><label for="project_id">Projeto</label><select id="project_id" name="project_id"><option value="">Todos os projetos</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
        <button type="submit">Buscar</button><a class="clear" href="{{ route('certificate.index') }}">Limpar</a>
    </form>
    <p class="muted">{{ $certificates->total() }} certificado(s) encontrado(s).</p>
    <div class="wrap"><table>
        <thead><tr><th>Participante</th><th>E-mail</th><th>Projeto</th><th>Gerado em</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        @forelse ($certificates as $certificate)
            <tr>
                <td>{{ $certificate->participant?->name ?? 'Participante removido' }}</td>
                <td>{{ $certificate->participant?->email }}</td>
                <td>{{ $certificate->participant?->project?->name }}</td>
                <td>{{ $certificate->generated_at?->format('d/m/Y H:i') ?? $certificate->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $certificate->status }}</td>
                <td class="actions"><a href="{{ route('certificate.view', $certificate) }}" target="_blank" rel="noopener">Visualizar</a><a href="{{ route('certificate.download', $certificate) }}">Baixar PDF</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Nenhum certificado corresponde aos filtros selecionados.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="margin-top:16px">{{ $certificates->links() }}</div>
</main></body>
</html>
