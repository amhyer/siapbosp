<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Masuk ke SiapBOSP — Sistem Akuntansi Dana BOSP.">
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0b5fef">
<link rel="apple-touch-icon" href="/icons/icon.svg">
@use('App\Support\Aset')
<title>{{ $title ?? 'Masuk' }} — SiapBOSP</title>
@fonts
@if (Aset::viteAktif())
    {{-- Server dev (npm run dev) hidup: biarkan Vite menangani aset + HMR. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@elseif (Aset::cssSiap())
    {{-- Jalur produksi: rujuk berkas hasil `npm run build` secara langsung.
         Tanpa Vite, tanpa manifest, tanpa berkas `hot` — sehingga halaman tidak
         bisa lagi tampil polos karena manifest rusak atau hot basi. --}}
    @foreach (Aset::cssUrl() as $css)
        <link rel="stylesheet" href="{{ $css }}">
    @endforeach
    @foreach (Aset::jsUrl() as $js)
        <script src="{{ $js }}" defer></script>
    @endforeach
@else
    {{-- Aset build tidak ada: pakai Tailwind di browser + gaya komponen layout. --}}
    @if (Aset::tailwindJsAda())
        <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="/tailwind.js"></script>
    @endif
    @include('layouts._gaya-darurat')
@endif
</head>
<body class="min-h-screen bg-slate-950">
@yield('content')
@stack('scripts')
</body>
</html>
