<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar — GeraCert</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 40px 16px; background: #f3f6f4; color: #20352f; font-family: Arial, sans-serif; }
        main { width: min(440px, 100%); margin: 6vh auto; padding: 30px; background: #fff; border: 1px solid #dbe5df; border-radius: 12px; }
        h1 { margin-top: 0; color: #245144; }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input { width: 100%; padding: 11px 12px; border: 1px solid #b8c9c0; border-radius: 7px; font: inherit; }
        button { width: 100%; margin-top: 20px; padding: 12px; border: 0; border-radius: 7px; background: #245144; color: #fff; font-weight: 700; cursor: pointer; }
        .notice { margin: 12px 0; padding: 12px; border-radius: 7px; background: #eaf4ed; }
        .error { background: #fff0ef; color: #8d241c; }
        .links { margin-top: 18px; }
        a { color: #17624a; }
    </style>
</head>
<body>
<main>
    <h1>Entrar no GeraCert</h1>
    @if (session('status')) <div class="notice">{{ session('status') }}</div> @endif
    @if ($errors->any())
        <div class="notice error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <label><input type="checkbox" name="remember" value="1" style="width:auto"> Manter conectado</label>
        <button type="submit">Entrar</button>
    </form>
    <p class="links"><a href="{{ route('password.request') }}">Esqueci minha senha</a></p>
</main>
</body>
</html>
