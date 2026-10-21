<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Masuk ke SiapBOSP — Sistem Akuntansi Dana BOSP.">
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5">
<link rel="apple-touch-icon" href="/icons/icon.svg">
<title>{{ $title ?? 'Masuk' }} — SiapBOSP</title>
@fonts
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="/tailwind.js"></script>
@endif
</head>
<body class="min-h-screen bg-slate-950">
@yield('content')
@stack('scripts')
</body>
</html>
