<?php

namespace Tests\Feature;

use App\Models\RkasHeader;
use Tests\TestCase;

/**
 * Pemetaan nama sumber dana ARKAS 4.3.1 → tab SiapBOSP.
 *
 * Nama yang diuji diambil apa adanya dari tabel `ref_sumber_dana` paket ARKAS
 * Desktop 4.3.1 (docs/arkas-ref/ref_sumber_dana.csv). Kalau ARKAS memakai
 * ejaan "BOS", tab SiapBOSP wajib tetap menemukan sumber dana itu; kalau
 * belum ada tabnya, pemetaan harus mengembalikan null — bukan menebak.
 */
class SumberDanaArkasTest extends TestCase
{
    /** @return array<string,string> nama ref_sumber_dana ARKAS => tab SiapBOSP */
    private const PETA_RESMI = [
        'BOS Reguler' => 'reguler',
        'BOS Daerah' => 'daerah',
        'BOS Kinerja' => 'kinerja',
        'SiLPA BOS Kinerja' => 'silpa_kinerja',
        'Lainnya' => 'lainnya',
        'BOS Afirmasi' => 'tambahan',
    ];

    public function test_nama_resmi_arkas_terpetakan_ke_tab_yang_benar(): void
    {
        foreach (self::PETA_RESMI as $nama => $tab) {
            $this->assertSame($tab, RkasHeader::tabDariSumber($nama),
                'Sumber dana ARKAS "'.$nama.'" tidak jatuh ke tab '.$tab.'.');
        }
    }

    public function test_ejaan_bosp_yang_dipakai_layar_arkas_tetap_dikenali(): void
    {
        $this->assertSame('reguler', RkasHeader::tabDariSumber('BOSP Reguler'));
        $this->assertSame('daerah', RkasHeader::tabDariSumber('BOSP Daerah'));
        $this->assertSame('kinerja', RkasHeader::tabDariSumber('BOSP Kinerja'));
        $this->assertSame('silpa_kinerja', RkasHeader::tabDariSumber('SiLPA BOSP Kinerja'));
        $this->assertSame('tambahan', RkasHeader::tabDariSumber('BOSP Afirmasi'));
        $this->assertSame('silpa_kinerja', RkasHeader::tabDariSumber('SILPA'));
    }

    /**
     * ARKAS 4.3.1 punya baris ref_sumber_dana "SiLPA BOS Reguler" dan
     * "SiLPA BOS Afirmasi", sedangkan pita tab ARKAS tidak punya tab
     * SiLPA terpisah untuk keduanya. Karena itu keduanya ikut sumber dana
     * induknya — bukan tab SiLPA Kinerja, dan bukan tab Lainnya.
     */
    public function test_silpa_reguler_dan_afirmasi_ikut_sumber_induk(): void
    {
        $this->assertSame('reguler', RkasHeader::tabDariSumber('SiLPA BOS Reguler'));
        $this->assertSame('tambahan', RkasHeader::tabDariSumber('SiLPA BOS Afirmasi'));
    }

    /** Nama yang benar-benar asing tetap masuk tab Lainnya (perilaku lama). */
    public function test_nama_asing_masuk_tab_lainnya(): void
    {
        $this->assertSame('lainnya', RkasHeader::tabDariSumber('Saldo Awal'));
        $this->assertSame('lainnya', RkasHeader::tabDariSumber('Dana Komite Sekolah'));
    }

    public function test_pencocokan_tetap_longgar_untuk_berkas_impor(): void
    {
        // Berkas ekspor ARKAS sering menambahkan tahun / spasi di ujung nama.
        $this->assertSame('reguler', RkasHeader::tabDariSumber('BOS Reguler 2026'));
        $this->assertSame('daerah', RkasHeader::tabDariSumber(' BOS Daerah '));
    }
}
