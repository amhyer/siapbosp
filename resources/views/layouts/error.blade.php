<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>@yield('title', 'Terjadi kendala') · SiapBOSP</title>
  <style>
    :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; color: #0f172a; }
    main { width: min(90vw, 34rem); box-sizing: border-box; padding: 2.25rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: white; box-shadow: 0 12px 32px #0f172a12; }
    .code { color: #0369a1; font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
    h1 { margin: .65rem 0; font-size: 1.65rem; }
    p { line-height: 1.65; color: #475569; }
    a { display: inline-block; margin-top: 1rem; padding: .65rem 1rem; border-radius: .6rem; background: #0369a1; color: white; font-weight: 600; text-decoration: none; }
    a:focus-visible { outline: 3px solid #7dd3fc; outline-offset: 3px; }
  </style>
</head>
<body>
  <main role="main" aria-labelledby="error-title">
    <div class="code">SiapBOSP · HTTP @yield('code')</div>
    <h1 id="error-title">@yield('heading', 'Terjadi kendala')</h1>
    <p>@yield('message', 'Permintaan Anda belum dapat diproses.')</p>
    <a href="{{ route('login') }}">Ke halaman masuk</a>
  </main>
</body>
</html>
