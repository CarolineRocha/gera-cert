<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auditoria — GeraCert</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px; background: #f3f6f4; color: #20352f; font-family: Arial, sans-serif; }
        main { width: min(1200px, 100%); margin: 0 auto; }
        h1 { color: #245144; }
        nav { display: flex; gap: 18px; margin: 16px 0; align-items: center; }
        a { color: #17624a; }
        .table-wrap { overflow-x: auto; background: white; border: 1px solid #dbe5df; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { padding: 12px; border-bottom: 1px solid #e5ece8; text-align: left; vertical-align: top; }
        th { background: #eaf2ed; }
        code { white-space: pre-wrap; overflow-wrap: anywhere; }
        .muted { color: #52645c; }
        button { border: 0; background: transparent; color: #17624a; text-decoration: underline; cursor: pointer; font: inherit; padding: 0; }
    </style>
</head>
<body>
<main>
    <h1>Auditoria</h1>
    <p class="muted">Últimas 200 ações registradas. Senhas e conteúdo dos e-mails não são armazenados nesta trilha.</p>
    <nav>
        <a href="{{ route('home') }}">Início</a>
        <a href="{{ route('smtp.settings.edit') }}">Configurações SMTP</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sair</button></form>
    </nav>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Data</th><th>Administrador</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr>
            </thead>
            <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $entry->user?->name ?? 'Conta removida' }}<br><span class="muted">{{ $entry->user?->email }}</span></td>
                    <td>{{ $entry->action }}</td>
                    <td><code>{{ json_encode($entry->properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></td>
                    <td>{{ $entry->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Ainda não há ações registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
