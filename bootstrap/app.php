<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\KeamananHeader;
use App\Http\Middleware\PastikanAkunAktif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => EnsureRole::class, 'auth.api' => ApiTokenAuth::class]);
        $middleware->append(KeamananHeader::class);

        // Akun/sekolah yang dinonaktifkan Dinas harus langsung kehilangan sesi,
        // termasuk pada rute yang hanya memakai middleware `auth`.
        $middleware->web(append: [PastikanAkunAktif::class]);

        // Jangan percaya SEMUA proxy: `at: '*'` membuat header X-Forwarded-For
        // bisa dipalsukan siapa pun, sehingga pembatas percobaan login (dan
        // jejak audit IP) dapat dilewati. Isi TRUSTED_PROXIES hanya dengan IP
        // proxy/CDN milik sendiri (dipisah koma).
        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));
        $middleware->trustProxies(at: $proxies === [] ? null : $proxies);

        // R-07: batasi header `Host` yang diterima. Tanpa ini, tautan di email
        // notifikasi dapat diarahkan ke domain penyerang (password reset /
        // tautan verifikasi palsu). Kosong = perilaku bawaan Laravel
        // (hanya host APP_URL + subdomainnya); isi TRUSTED_HOSTS bila aplikasi
        // dilayani dengan beberapa nama domain, mis. "siapbosp.go.id,*.go.id".
        $hosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_HOSTS', ''))
        )));
        if ($hosts !== []) {
            $middleware->trustHosts(at: $hosts);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) { /* */
    })->create();
