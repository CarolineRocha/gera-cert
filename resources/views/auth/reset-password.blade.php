<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar nova senha — GeraCert</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 40px 16px; background: #f3f6f4; color: #20352f; font-family: Arial, sans-serif; }
        main { width: min(500px, 100%); margin: 6vh auto; padding: 30px; background: #fff; border: 1px solid #dbe5df; border-radius: 12px; }
        h1 { margin-top: 0; color: #245144; }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input { width: 100%; padding: 11px 12px; border: 1px solid #b8c9c0; border-radius: 7px; font: inherit; }
        button { margin-top: 20px; padding: 12px 18px; border: 0; border-radius: 7px; background: #245144; color: #fff; font-weight: 700; cursor: pointer; }
        .notice { margin: 12px 0; padding: 12px; border-radius: 7px; background: #fff0ef; color: #8d241c; }
    </style>
</head>
<body>
<main>
    <h1>Criar nova senha</h1>
    @if ($errors->any()) <div class="notice"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
        <label for="password">Nova senha (mínimo 12 caracteres)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirme a nova senha</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <button type="submit">Salvar nova senha</button>
    </form>
</main>
</body>
</html>
