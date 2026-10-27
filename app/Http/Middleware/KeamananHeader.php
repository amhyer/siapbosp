<?php

namespace App\Http\Middleware;

use App\Support\Aset;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh respons web (R-03).
 *
 * CSP membatasi asal sumber secara eksplisit dan memberi nonce acak untuk blok
 * <script> inline. Seluruh handler atribut inline (`onclick`/`onsubmit`/…)
 * sudah dipindah ke JavaScript non-inline yang dipasang di layouts: atribut
 * `data-*` + addEventListener (lihat resources/js/app.js dan
 * resources/views/layouts/_interaksi.blade.php). Karena itu `script-src-attr`
 * kini ditutup penuh (`'none'`) — tidak ada lagi pengecualian 'unsafe-inline'
 * atau hash untuk event handler.
 */
class KeamananHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = rtrim(base64_encode(random_bytes(18)), '=');
        $request->attributes->set('csp_nonce', $nonce);

        // Beri tahu Vite nonce yang sama, supaya tag/script inline yang dicetak
        // @vite ikut sah menurut `script-src` (laporan pelanggarannya berbunyi
        // "Executing inline script violates … nonce is required").
        if (method_exists(\Illuminate\Support\Facades\Vite::getFacadeRoot(), 'useCspNonce')) {
            \Illuminate\Support\Facades\Vite::useCspNonce($nonce);
        }
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        $scriptSrc = "'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com";
        $connectSrc = "'self'";

        // Mode pengembangan (`npm run dev`): aset dilayani server Vite pada port
        // lain, jadi TIDAK tercakup 'self'. Tanpa pengecualian ini seluruh CSS
        // dev diblokir CSP dan halaman tampil polos — walau dev server hidup.
        if (($asalDev = Aset::hotAsal()) !== null) {
            $scriptSrc .= ' '.$asalDev;
            $styleSrc .= ' '.$asalDev;
            $connectSrc .= ' '.$asalDev;
            if (($wsDev = Aset::hotWs()) !== null) {
                $connectSrc .= ' '.$wsDev;
            }
        }

        $headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src {$scriptSrc}",
            "script-src-attr 'none'",
            "style-src {$styleSrc}",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "connect-src {$connectSrc}",
            "frame-src 'self'",
        ]));

        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
