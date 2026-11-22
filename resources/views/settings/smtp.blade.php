<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurações de SMTP</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #20352f; background: #f3f6f4; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; }
        main { width: min(820px, 100%); margin: 0 auto; }
        h1, h2 { color: #245144; }
        .card { background: #fff; border: 1px solid #dbe5df; border-radius: 12px; padding: 24px; margin: 18px 0; box-shadow: 0 4px 16px #163d3010; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 700; margin: 12px 0 6px; }
        input, select { width: 100%; padding: 11px 12px; border: 1px solid #b8c9c0; border-radius: 7px; font: inherit; background: #fff; }
        small, .muted { color: #52645c; line-height: 1.5; }
        button { border: 0; border-radius: 7px; padding: 12px 18px; margin-top: 18px; background: #245144; color: #fff; font-weight: 700; cursor: pointer; }
        .notice { padding: 12px 14px; border-radius: 7px; margin: 12px 0; background: #eaf4ed; }
        .error { background: #fff0ef; color: #8d241c; }
        .actions { display: flex; gap: 12px; align-items: end; }
        a { color: #17624a; }
        code { background: #eef2ef; padding: 2px 5px; border-radius: 4px; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } .card { padding: 18px; } }
    </style>
</head>
<body>
<main>
    <h1>Configurações de SMTP</h1>
    <p class="muted">As configurações pertencem à sua conta administrativa. A senha é armazenada criptografada e não aparece novamente depois de salva.</p>
    <nav style="display:flex;gap:16px;align-items:center"><a href="{{ route('home') }}">Início</a><a href="{{ route('projects.index') }}">Projetos</a><a href="{{ route('audit.index') }}">Consultar histórico de ações</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" style="margin:0;padding:8px 12px">Sair</button></form></nav>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card">
        <h2>Servidor de saída</h2>
        <form method="POST" action="{{ route('smtp.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="grid">
                <div>
                    <label for="host">Host SMTP</label>
                    <input id="host" name="host" value="{{ old('host', $settings['host']) }}" required maxlength="255" placeholder="smtp.gmail.com">
                </div>
                <div>
                    <label for="port">Porta</label>
                    <input id="port" name="port" type="number" min="1" max="65535" value="{{ old('port', $settings['port']) }}" required>
                </div>
                <div>
                    <label for="scheme">Segurança da conexão</label>
                    <select id="scheme" name="scheme" required>
                        <option value="smtp" @selected(old('scheme', $settings['scheme']) === 'smtp')>TLS / STARTTLS (normalmente porta 587)</option>
                        <option value="smtps" @selected(old('scheme', $settings['scheme']) === 'smtps')>SSL implícito (normalmente porta 465)</option>
                    </select>
                </div>
                <div>
                    <label for="username">Usuário SMTP</label>
                    <input id="username" name="username" value="{{ old('username', $settings['username']) }}" maxlength="255" autocomplete="username">
                </div>
                <div>
                    <label for="from_address">E-mail do remetente</label>
                    <input id="from_address" name="from_address" type="email" value="{{ old('from_address', $settings['from_address']) }}" required maxlength="255">
                </div>
                <div>
                    <label for="from_name">Nome do remetente</label>
                    <input id="from_name" name="from_name" value="{{ old('from_name', $settings['from_name']) }}" required maxlength="255">
                </div>
            </div>

            <label for="password">Senha SMTP / senha de app</label>
            <input id="password" name="password" type="password" value="" autocomplete="new-password" maxlength="500" placeholder="{{ $settings['password_configured'] ? 'Deixe em branco para manter a senha salva' : 'Digite a senha SMTP' }}">
            <small>
                @if ($settings['password_from_env'])
                    Há uma senha carregada do <code>.env</code>. Ao salvar, ela será copiada para o banco de forma criptografada, sem ser mostrada nesta tela.
                @elseif ($settings['password_configured'])
                    Uma senha está salva e protegida. O campo permanece vazio; informe outra somente para substituí-la.
                @else
                    A senha será armazenada criptografada com a chave <code>APP_KEY</code> da aplicação.
                @endif
            </small>

            <button type="submit">Salvar configurações</button>
        </form>
    </section>

    <section class="card">
        <h2>Testar o SMTP</h2>
        <p class="muted">Salve as configurações antes. Ao clicar, o sistema enviará um e-mail real somente para o endereço informado abaixo.</p>
        <form method="POST" action="{{ route('smtp.settings.test') }}" class="actions">
            @csrf
            <div style="flex:1">
                <label for="recipient">Enviar teste para</label>
                <input id="recipient" name="recipient" type="email" value="{{ old('recipient') }}" required placeholder="seu-email@exemplo.com">
            </div>
            <button type="submit">Enviar e-mail de teste</button>
        </form>
    </section>

    <section class="card">
        <h2>Gmail: criar uma senha de app</h2>
        <p>Para SMTP, use <code>smtp.gmail.com</code>, porta <code>587</code>, segurança <code>TLS / STARTTLS</code>, e seu endereço Gmail completo como usuário.</p>
        <ol>
            <li>Ative a verificação em duas etapas na sua Conta Google.</li>
            <li>Abra <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">Senhas de app</a> e faça login novamente, se solicitado.</li>
            <li>Crie uma senha com um nome como <strong>GeraCert</strong> e copie o código gerado para o campo de senha desta tela.</li>
        </ol>
        <p class="muted">O Google exige verificação em duas etapas. A opção pode não aparecer em contas de organizações, contas com Proteção Avançada ou algumas configurações de verificação. Consulte a <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener noreferrer">ajuda oficial do Google sobre senhas de app</a>.</p>
        <p class="muted">Nunca informe aqui a senha normal da sua Conta Google. A senha de app não é exibida novamente após salvar; se esquecê-la, revogue-a no Google e gere outra.</p>
    </section>
</main>
</body>
</html>
