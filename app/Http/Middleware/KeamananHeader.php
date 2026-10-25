<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh respons web (R-03).
 *
 * Bingkai dibatasi ke origin sendiri (`SAMEORIGIN` + `frame-ancestors 'self'`):
 * pratinjau cetak memakai iframe internal sehingga bingkai se-origin tetap
 * diizinkan, sementara clickjacking dari situs lain ditolak.
 *
 * CSP sengaja minimal (hanya frame-ancestors/base-uri/object-src) karena
 * antarmuka memakai atribut `onsubmit`/`onclick` inline; `default-src` baru
 * bisa dipasang setelah skrip inline dipindahkan ke berkas terpisah.
 */
class KeamananHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");

        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
