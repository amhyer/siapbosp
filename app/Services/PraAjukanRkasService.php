<?php

namespace App\Services;

use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\StandarHarga;

/**
 * Gerbang pra-pengajuan Kertas Kerja (paritas tombol "Ajukan Pengesahan" ARKAS 4).
 *
 * ARKAS 4 menolak pengajuan selama:
 *  - Belum Dianggarkan masih > Rp 0 (atau anggaran melampaui pagu),
 *  - masih ada rincian yang belum lengkap (volume/harga nol),
 *  - harga satuan melebihi harga yang ditetapkan Dinas (SSH/SBU).
 *
 * Empat hal di atas adalah **blokir**. Kepatuhan proporsi Juknis (buku/sarpras/
 * honor) sengaja hanya **peringatan** di tahap ini karena di SiapBOSP blokir
 * proporsi sudah ditegakkan saat "Kirim ke Dinas" (lihat JuknisBosp2026Service).
 *
 * Rujukan: docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md bagian P0-2.
 */
class PraAjukanRkasService
{
    /**
     * Batas panjang uraian pada Kertas Kerja ARKAS 4 (500 karakter).
     * Dipakai juga oleh migrasi yang melebarkan kolom `rkas_details.uraian`
     * dari 255 ke 500 agar isian sepanjang ini benar-benar tersimpan.
     */
    public const BATAS_URAIAN = 500;

    /** Maksimum baris pelanggaran yang dikirim ke tampilan per pemeriksaan. */
    private const MAKS_BARIS = 25;

    /**
     * Periksa kelayakan pengajuan Kertas Kerja.
     *
     * @return array{
     *     boleh_ajukan: bool,
     *     jumlah_blokir: int,
     *     jumlah_peringatan: int,
     *     pagu: int,
     *     sudah_dianggarkan: int,
     *     belum_dianggarkan: int,
     *     cek: array<int,array{kode:string,level:string,judul:string,pesan:string,baris:array<int,int>}>
     * }
     */
    public static function periksa(
        RkasHeader $header,
        School $school,
        ?int $paguOverride = null,
        ?array $review = null
    ): array {
        $header->loadMissing(['details.rekening.komponen']);

        // Hasil review Juknis boleh dipakai ulang (halaman Kertas Kerja sudah
        // menghitungnya) supaya tidak ada dua kali perhitungan proporsi.
        $review ??= JuknisBosp2026Service::reviewKertasKerja($header, $school, $paguOverride);
        $pagu = (int) $review['pagu'];
        $sudah = (int) $review['sudah_dianggarkan'];
        $belum = $pagu > 0 ? $pagu - $sudah : 0;

        $details = $header->details;
        $cek = [];

        // 1. Pagu sumber dana harus diketahui supaya "Belum Dianggarkan" bermakna.
        if ($pagu > 0) {
            $cek[] = [
                'kode' => 'belum_dianggarkan',
                'level' => $belum === 0 ? 'lolos' : 'blokir',
                'judul' => 'Belum Dianggarkan = Rp 0',
                'pesan' => match (true) {
                    $belum > 0 => 'Masih ada Belum Dianggarkan '.rp($belum).'. Di ARKAS 4 angka ini harus Rp 0 sebelum Ajukan Pengesahan.',
                    $belum < 0 => 'Total anggaran melampaui pagu sebesar '.rp(abs($belum)).'.',
                    default => 'Seluruh pagu '.rp($pagu).' sudah teranggarkan.',
                },
                'baris' => [],
            ];
        } else {
            $cek[] = [
                'kode' => 'pagu_belum_ditetapkan',
                'level' => 'info',
                'judul' => 'Pagu sumber dana belum ditetapkan',
                'pesan' => 'Pagu untuk sumber dana ini belum ada di menu Sumber Dana, sehingga syarat "Belum Dianggarkan = Rp 0" belum dapat diperiksa.',
                'baris' => [],
            ];
        }

        // 2. Harus ada rincian.
        if ($details->isEmpty()) {
            $cek[] = [
                'kode' => 'tanpa_rincian',
                'level' => 'blokir',
                'judul' => 'Belum ada rincian belanja',
                'pesan' => 'Kertas Kerja kosong belum bisa diajukan. Tambahkan minimal satu rincian belanja.',
                'baris' => [],
            ];
        }

        // 3. Rincian harus lengkap (volume, harga satuan, jumlah).
        $tidakLengkap = $details->filter(
            fn (RkasDetail $d) => (int) $d->banyak <= 0 || (int) $d->harga_satuan <= 0 || (int) $d->jumlah <= 0
        );
        if ($tidakLengkap->isNotEmpty()) {
            $cek[] = [
                'kode' => 'rincian_tidak_lengkap',
                'level' => 'blokir',
                'judul' => 'Rincian belum lengkap',
                'pesan' => $tidakLengkap->count().' rincian belum punya volume, harga satuan, atau jumlah yang sah (semuanya harus lebih dari 0).',
                'baris' => self::idBaris($tidakLengkap),
            ];
        }

        // 4. Harga satuan di atas harga Dinas (SSH/SBU) — peringatan, bukan blokir,
        //    karena SiapBOSP harus tetap bisa dipakai saat referensi harga kosong.
        $hargaDiAtas = self::hargaDiAtasStandar($details, (int) $school->id);
        if ($hargaDiAtas->isNotEmpty()) {
            $cek[] = [
                'kode' => 'harga_di_atas_standar',
                'level' => 'peringatan',
                'judul' => 'Harga satuan melebihi standar Dinas',
                'pesan' => $hargaDiAtas->count().' rincian memakai harga satuan di atas batas SSH/SBU yang berlaku. ARKAS 4 menolak harga di atas batas ini.',
                'baris' => self::idBaris($hargaDiAtas),
            ];
        }

        // 5. Temuan kepatuhan proporsi Juknis (sudah dihitung di reviewKertasKerja).
        //    Di sini hanya peringatan; blokirnya ditegakkan saat kirim ke Dinas.
        foreach (($review['temuan'] ?? []) as $t) {
            if (($t['kode'] ?? '') === 'pagu_belum_nol') {
                continue; // sudah diwakili pemeriksaan #1
            }
            $level = ($t['level'] ?? '') === 'blokir' ? 'peringatan' : ($t['level'] ?? 'info');
            $cek[] = [
                'kode' => (string) ($t['kode'] ?? 'juknis'),
                'level' => $level,
                'judul' => 'Kepatuhan Juknis BOSP',
                'pesan' => (string) ($t['pesan'] ?? ''),
                'baris' => [],
            ];
        }

        $jumlahBlokir = collect($cek)->where('level', 'blokir')->count();
        $jumlahPeringatan = collect($cek)->where('level', 'peringatan')->count();

        return [
            'boleh_ajukan' => $jumlahBlokir === 0,
            'jumlah_blokir' => $jumlahBlokir,
            'jumlah_peringatan' => $jumlahPeringatan,
            'pagu' => $pagu,
            'sudah_dianggarkan' => $sudah,
            'belum_dianggarkan' => $belum,
            'cek' => $cek,
        ];
    }

    /**
     * Rincian yang harga satuannya melebihi batas Standar Harga (SSH/SBU) sekolah/Dinas.
     *
     * @param  \Illuminate\Support\Collection<int,RkasDetail>  $details
     * @return \Illuminate\Support\Collection<int,RkasDetail>
     */
    private static function hargaDiAtasStandar($details, int $schoolId)
    {
        $standar = StandarHarga::untukSekolah($schoolId)->get(['id', 'kode_rekening', 'batas_harga']);
        if ($standar->isEmpty()) {
            return collect();
        }

        $perId = $standar->keyBy('id');
        $perKode = $standar->whereNotNull('kode_rekening')->keyBy('kode_rekening');

        return $details->filter(function (RkasDetail $d) use ($perId, $perKode) {
            $batas = null;

            if ($d->standar_harga_id && $perId->has($d->standar_harga_id)) {
                $batas = (int) $perId->get($d->standar_harga_id)->batas_harga;
            } else {
                $kodeRek = (string) ($d->rekening->kode ?? '');
                if ($kodeRek !== '' && $perKode->has($kodeRek)) {
                    $batas = (int) $perKode->get($kodeRek)->batas_harga;
                }
            }

            return $batas !== null && (int) $d->harga_satuan > $batas;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int,RkasDetail>  $details
     * @return array<int,int>
     */
    private static function idBaris($details): array
    {
        return $details->take(self::MAKS_BARIS)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
