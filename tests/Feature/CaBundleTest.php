<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CA bundle HTTPS (Tahap D): PHP Windows tanpa cacert.cainfo tidak
 * bisa HTTPS lewat curl — Sinkron ke Pusat akan gagal. Aplikasi
 * membawa resources/php-portable/cacert.pem dan memasangnya sebagai
 * opsi "verify" pada HTTP client.
 */
class CaBundleTest extends TestCase
{
    public function test_ca_bundle_ada_dan_dipasang_ke_http_client(): void
    {
        $ca = resource_path('php-portable/cacert.pem');
        $this->assertFileExists($ca);
        $this->assertGreaterThan(100000, filesize($ca));

        $factory = Http::getFacadeRoot();
        $ref = new \ReflectionProperty($factory, 'globalOptions');
        $ref->setAccessible(true);
        $opts = $ref->getValue($factory);

        $this->assertSame($ca, $opts['verify'] ?? null);
    }
}
