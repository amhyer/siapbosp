<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\RkasHeader;
use App\Models\School;

/**
 * Cek Juknis BOSP 2026 (Permendikdasmen No 8 Tahun 2026, Pasal 42).
 *
 * Tiga batas komponen dihitung dari PAGU (100% total pagu anggaran 2026):
 * - Honor      : maksimum 20% (dasmen negeri) / 40% (dasmen swasta),
 *                40% (PAUD & Kesetaraan, negeri & swasta).
 * - Buku       : minimum 10% (dasmen & kesetaraan) / 5% (PAUD).
 * - Sarpras    : maksimum 20% (semua jenjang).
 *
 * Klasifikasi baris memakai kode kegiatan resmi (Ref_Kode ARKAS: 07.12.x,
 * 05.08.x, 05.02.x, 03.02.02) yang tersimpan di `rkas_details.kode_kegiatan`;
 * bila kosong, dipakai rekening resmi (5.1.02.02.01.0013 honor,
 * 5.2.02.05.02.0001 meubel modal, 5.1.02.03.05.x buku).
 */
class JuknisService
{
    /** Sub program honor (Pasal 42 ayat 4 poin a-b). */
    public const KODE_HONOR = ['07.12.01', '07.12.02', '07.12.03', '07.12.04'];

    /** Rekening belanja yang dipakai pada komponen honor. */
    public const REKENING_HONOR = '5.1.02.02.01.0013';

    /** Sub program pemeliharaan sarpras (Pasal 42 ayat 3). */
    public const KODE_SARPRAS = [
        '05.08.01', '05.08.03', '05.08.05', '05.08.06',
        '05.08.08', '05.08.09', '05.08.10', '05.08.12',
    ];

    /** Rekening modal meubel (bagian sarpras). */
    public const REKENING_SARPRAS_MOBIL = '5.2.02.05.02.0001';

    /** Rekening pemeliharaan bahan perpustakaan (buku). */
    public const REKENING_BUKU = '5.1.02.03.05';

    /** Kode kegiatan buku per jenjang (Pasal 42 ayat 2). */
    public const KODE_BUKU_DASMEN = ['03.02.02', '05.02.02', '05.02.03', '05.02.04', '05.02.05'];

    public const KODE_BUKU_PAUD = ['05.02.03', '05.02.05', '05.02.07'];

    public const KODE_BUKU_KESETARAAN = ['05.02.03', '05.02.04', '05.02.05', '05.02.06'];

    public const JENJANG_PAUD = ['TK', 'KB', 'TPA', 'SPS'];

    public const JENJANG_KESETARAAN = ['PKBM', 'SKB'];

    /**
     * Periksa RKAS terhadap 3 batas komponen Juknis 8/2026.
     *
     * @return array{tahun:int,sumber:string,pagu:int,total:int,aturan:array,catatan:array}
     */
    public static function cek(RkasHeader $h, School $sekolah, int $tahun): array
    {
        $h->loadMissing('details.rekening');
        $baris = $h->details->filter(fn ($d) => $d->rekening_id !== null);

        // Baris milik BOS Reguler (batas juknis berlaku pada dana reguler).
        $reguler = $baris->filter(fn ($d) => str_contains(mb_strtolower((string) $d->sumber_dana), 'reguler'));
        $dipakai = $reguler->isNotEmpty() ? $reguler : $baris;
        $sumber = $reguler->isNotEmpty() ? 'BOS Reguler' : 'Seluruh sumber dana';

        $pagu = self::pagu($sekolah->id, $tahun);
        $total = (int) $dipakai->sum('jumlah');

        $jenjang = strtoupper((string) $sekolah->jenjang);
        $sifat = strtolower((string) ($sekolah->sifat ?? 'negeri'));
        $paud = in_array($jenjang, self::JENJANG_PAUD, true);
        $kesetaraan = in_array($jenjang, self::JENJANG_KESETARAAN, true);
        $kodeBuku = self::kodeBuku($jenjang);

        $klasifikasi = ['honor' => 0, 'buku' => 0, 'sarpras' => 0];
        $tanpaKlasifikasi = 0;
        $tanpaKegiatan = 0;
        foreach ($dipakai as $d) {
            $kode = self::normalisasi($d->kode_kegiatan);
            if ($kode === '') {
                $tanpaKegiatan++;
            }
            $tipe = self::klasifikasi($kode, (string) ($d->rekening?->kode ?? ''), $kodeBuku);
            if ($tipe === null) {
                $tanpaKlasifikasi++;

                continue;
            }
            $klasifikasi[$tipe] += (int) $d->jumlah;
        }

        $batasHonor = ($paud || $kesetaraan) ? 40 : ($sifat === 'swasta' ? 40 : 20);
        $batasBuku = $paud ? 5 : 10;

        $aturan = [
            self::aturan('honor', 'Komponen Honor', 'maks', $batasHonor, $klasifikasi['honor'], $pagu,
                'Batas '.($paud || $kesetaraan ? 'PAUD & Kesetaraan' : ($sifat === 'swasta' ? 'dasmen swasta' : 'dasmen negeri'))
                .' '.($paud || $kesetaraan ? '40%' : ($sifat === 'swasta' ? '40%' : '20%')).' dari pagu (kode 07.12.01–07.12.04, rekening 5.1.02.02.01.0013).'),
            self::aturan('buku', 'Komponen Buku', 'min', $batasBuku, $klasifikasi['buku'], $pagu,
                'Minimal '.($paud ? '5% (PAUD)' : '10%').' dari pagu (kode kegiatan 03.02.02/05.02.x, rekening 5.1.02.03.05.x).'),
            self::aturan('sarpras', 'Komponen Sarana & Prasarana', 'maks', 20, $klasifikasi['sarpras'], $pagu,
                'Maksimal 20% dari pagu (kode 05.08.x, rekening meubel modal 5.2.02.05.02.0001).'),
        ];

        $catatan = [];
        if ($pagu <= 0) {
            $catatan[] = 'Pagu belum terisi (Master Pagu / impor ARKAS) — batas dalam persen belum bisa dihitung.';
        }
        if ($tanpaKegiatan > 0) {
            $catatan[] = $tanpaKegiatan.' baris tanpa kode kegiatan — diperiksa lewat kode rekening saja.';
        }
        if ($tanpaKlasifikasi > 0) {
            $catatan[] = $tanpaKlasifikasi.' baris tidak masuk komponen mana pun (tidak dihitung di atas).';
        }
        if ($reguler->isEmpty() && $baris->isNotEmpty()) {
            $catatan[] = 'Tidak ada baris berlabel BOS Reguler — seluruh sumber dana diperiksa.';
        }

        return [
            'tahun' => $tahun,
            'sumber' => $sumber,
            'pagu' => $pagu,
            'total' => $total,
            'aturan' => $aturan,
            'catatan' => $catatan,
        ];
    }

    /** Pagu dana tahun berjalan (BOS Reguler bila ada, else seluruh sumber). */
    public static function pagu(int $schoolId, int $tahun): int
    {
        $reguler = BosType::where('kode', 'reguler')->pluck('id');
        $pagu = (int) Allocation::where('school_id', $schoolId)->where('tahun', $tahun)
            ->when($reguler->isNotEmpty(), fn ($q) => $q->whereIn('bos_type_id', $reguler))
            ->sum('nilai');
        if ($pagu > 0) {
            return $pagu;
        }

        return (int) Allocation::where('school_id', $schoolId)->where('tahun', $tahun)->sum('nilai');
    }

    /** Susun satu baris hasil cek (batas, persen, sisa ruang). */
    protected static function aturan(string $kunci, string $nama, string $jenis, int $batasPersen, int $anggaran, int $pagu, string $keterangan): array
    {
        $batasNominal = (int) round($pagu * $batasPersen / 100);
        $persen = $pagu > 0 ? round($anggaran * 100 / $pagu, 1) : 0.0;
        $selisih = $batasNominal - $anggaran;

        if ($pagu <= 0) {
            $status = 'tak_tercek';
        } elseif ($jenis === 'maks') {
            $status = $selisih < 0 ? 'terlampaui' : 'aman';
        } else {
            $status = $selisih > 0 ? 'kurang' : 'aman';
        }

        return [
            'kunci' => $kunci,
            'nama' => $nama,
            'jenis' => $jenis,
            'batas_persen' => $batasPersen,
            'batas_nominal' => $batasNominal,
            'anggaran' => $anggaran,
            'persen' => $persen,
            // Maks → sisa ruang yang boleh dipakai; min → kekurangan (kurang).
            'sisa' => $jenis === 'maks' ? $selisih : abs($selisih),
            'status' => $status,
            'keterangan' => $keterangan,
        ];
    }

    /** Kode kegiatan dari ARKAS: '05.08.01.' → '05.08.01'. */
    public static function normalisasi(?string $kode): string
    {
        return rtrim(trim((string) $kode), '.');
    }

    /** Klasifikasi satu baris: honor | buku | sarpras | null (saling lepas). */
    public static function klasifikasi(string $kodeKegiatan, string $kodeRekening, array $kodeBuku = []): ?string
    {
        $kodeBuku = $kodeBuku ?: self::kodeBuku();
        if ($kodeKegiatan !== '') {
            if (in_array($kodeKegiatan, self::KODE_HONOR, true)) {
                return 'honor';
            }
            if (in_array($kodeKegiatan, $kodeBuku, true)) {
                return 'buku';
            }
            if (in_array($kodeKegiatan, self::KODE_SARPRAS, true)) {
                return 'sarpras';
            }

            return null;
        }

        // Tanpa kode kegiatan → pakai rekening resmi.
        if ($kodeRekening === self::REKENING_HONOR) {
            return 'honor';
        }
        if (str_starts_with($kodeRekening, self::REKENING_BUKU)) {
            return 'buku';
        }
        if ($kodeRekening === self::REKENING_SARPRAS_MOBIL
            || str_starts_with($kodeRekening, '5.1.02.03')
            || str_starts_with($kodeRekening, '5.2.02.03')) {
            return 'sarpras';
        }

        return null;
    }

    /** Kode kegiatan buku sesuai jenjang (dasmen / PAUD / kesetaraan). */
    public static function kodeBuku(?string $jenjang = null): array
    {
        $j = strtoupper((string) $jenjang);
        if (in_array($j, self::JENJANG_PAUD, true)) {
            return self::KODE_BUKU_PAUD;
        }
        if (in_array($j, self::JENJANG_KESETARAAN, true)) {
            return self::KODE_BUKU_KESETARAAN;
        }

        return self::KODE_BUKU_DASMEN;
    }
}
