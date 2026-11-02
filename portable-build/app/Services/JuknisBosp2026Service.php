<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\ArkasKode;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\LpjSubmission;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mesin aturan & validasi kepatuhan resmi:
 * 1. Buku Panduan ARKAS 4 v4.3.0 (September 2026) & rilis ARKAS v4.2.18–v4.2.19
 * 2. Permendikdasmen Nomor 8 Tahun 2026 (Juknis Pengelolaan Dana BOSP 2026)
 *
 * Seluruh proporsi TA 2026 dihitung dari 100% total pagu alokasi tahunan.
 */
class JuknisBosp2026Service
{
    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public const KODE_SNP_DEFAULT = [
        '02.02' => '02.02 Pengembangan Perpustakaan dan Pojok Baca',
        '03.01' => '03.01 Penerimaan Peserta Didik Baru (SPMB)',
        '03.03' => '03.03 Pelaksanaan Kegiatan Pembelajaran dan Ekstrakurikuler',
        '04.06' => '04.06 Pelaksanaan Asesmen Nasional & Evaluasi Pembelajaran',
        '05.08' => '05.08 Pemeliharaan Sarana dan Prasarana Sekolah',
        '05.09' => '05.09 Penyediaan Alat Multimedia Pembelajaran',
        '06.05' => '06.05 Pelaksanaan Administrasi Kegiatan Sekolah',
        '06.07' => '06.07 Pembiayaan Langganan Daya dan Jasa',
        '07.05' => '07.05 Pengembangan Profesi Pendidik & Komunitas Belajar',
        '07.12' => '07.12 Pembayaran Honorarium Guru/Tenaga Kependidikan Non-ASN',
    ];

    /** Apakah sumber dana termasuk BOSP Reguler (tunduk batas proporsi Pasal 38/42/48). */
    public static function isReguler(string $sumberDana): bool
    {
        $s = strtolower(trim($sumberDana));

        return str_contains($s, 'reguler');
    }

    /**
     * Aturan batas proporsi Permendikdasmen No. 8 Tahun 2026 per jenjang, status negeri/swasta, dan sumber dana.
     */
    public static function batasProporsi(School $school, string $sumberDana = 'BOS Reguler'): array
    {
        if (! self::isReguler($sumberDana)) {
            $dasar = 'Permendikdasmen No. 8 Tahun 2026 & ARKAS 4 v4.3.0 (Non-Reguler — Proporsi Antar Jenis Belanja)';

            return [
                'mode' => 'antar_jenis',
                'dasar_hukum' => $dasar,
                'label_aturan' => $dasar,
                'buku_min_pct' => null,
                'sarpras_max_pct' => null,
                'honor_max_pct' => null,
                'tahap1_target_pct' => null,
                'buku_min_persen' => null,
                'sarpras_max_persen' => null,
                'honor_max_persen' => null,
                'tahap1_target_persen' => null,
                'komite_wajib' => false,
                'catatan' => 'Sumber dana Non-Reguler tidak memiliki batas Honor/Buku Reguler; hanya Grafik Proporsi Antar Jenis Belanja yang ditampilkan sesuai ARKAS 4 v4.2.19.',
            ];
        }

        $kat = $school->kategoriJuknis();
        $swasta = $school->isSwasta();

        return match ($kat) {
            'paud' => [
                'mode' => 'reguler',
                'dasar_hukum' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 38 (BOP PAUD Reguler)',
                'label_aturan' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 38 (BOP PAUD Reguler)',
                'buku_min_pct' => 5.0,
                'sarpras_max_pct' => 20.0,
                'honor_max_pct' => 40.0,
                'tahap1_target_pct' => 50.0,
                'buku_min_persen' => 5.0,
                'sarpras_max_persen' => 20.0,
                'honor_max_persen' => 40.0,
                'tahap1_target_persen' => 50.0,
                'komite_wajib' => false,
                'catatan' => 'BOP PAUD Reguler: Buku/Pojok Baca wajib minimal 5%, Pemeliharaan Sarpras maks. 20%, Pembayaran Honor maks. 40% dari total pagu tahunan.',
            ],
            'kesetaraan' => [
                'mode' => 'reguler',
                'dasar_hukum' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 48 (BOP Kesetaraan Reguler)',
                'label_aturan' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 48 (BOP Kesetaraan Reguler)',
                'buku_min_pct' => 10.0,
                'sarpras_max_pct' => 20.0,
                'honor_max_pct' => 40.0,
                'tahap1_target_pct' => 50.0,
                'buku_min_persen' => 10.0,
                'sarpras_max_persen' => 20.0,
                'honor_max_persen' => 40.0,
                'tahap1_target_persen' => 50.0,
                'komite_wajib' => false,
                'catatan' => 'BOP Kesetaraan Reguler: Buku wajib minimal 10%, Pemeliharaan Sarpras maks. 20%, Pembayaran Honor maks. 40% dari total pagu tahunan.',
            ],
            default => [
                'mode' => 'reguler',
                'dasar_hukum' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 42 (BOS Reguler '.($swasta ? 'Swasta' : 'Negeri').')',
                'label_aturan' => 'Permendikdasmen No. 8 Tahun 2026 Pasal 42 (BOS Reguler '.($swasta ? 'Swasta' : 'Negeri').')',
                'buku_min_pct' => 10.0,
                'sarpras_max_pct' => 20.0,
                'honor_max_pct' => $swasta ? 40.0 : 20.0,
                'tahap1_target_pct' => 50.0,
                'buku_min_persen' => 10.0,
                'sarpras_max_persen' => 20.0,
                'honor_max_persen' => $swasta ? 40.0 : 20.0,
                'tahap1_target_persen' => 50.0,
                'komite_wajib' => true,
                'catatan' => 'BOS Reguler '.($swasta ? 'Swasta' : 'Negeri').': Buku/Perpustakaan wajib minimal 10%, Pemeliharaan Sarpras maks. 20%, Honor Non-ASN ber-NUPTK maks. '.($swasta ? '40%' : '20%').', serta alokasi Tahap 1 (Jan–Jun) maks. 50% pagu.',
            ],
        };
    }

    /**
     * Ambil pagu sumber dana sekolah pada satu tahun anggaran.
     *
     * Sumber utama: tabel `allocations` (ditetapkan Dinas). Bila kosong,
     * `$pakaiEstimasi` memungkinkan fallback ke jumlah siswa × biaya satuan —
     * angka yang DIISI SEKOLAH, jadi hanya layak dipakai di halaman sekolah
     * sendiri. Halaman tinjauan/pengesahan Dinas wajib memanggil dengan
     * `$pakaiEstimasi = false` agar tidak tertipu angka self-report.
     */
    public static function paguSumberDana(School $school, int $tahun, ?string $sumberDana = null, bool $pakaiEstimasi = true): int
    {
        $q = Allocation::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('tahun', $tahun);

        if ($sumberDana !== null && $sumberDana !== '') {
            $kodeMap = match (strtolower(trim($sumberDana))) {
                'bos reguler', 'bosp reguler' => 'reguler',
                'bos kinerja', 'bosp kinerja' => 'kinerja',
                'silpa bosp kinerja' => 'silpa_kinerja',
                'bosp afirmasi' => 'afirmasi',
                'bosp daerah', 'bosda' => 'bosda',
                'silpa', 'silpa tahun sebelumnya' => 'silpa',
                'sumber dana lainnya', 'lainnya' => 'lainnya',
                default => null,
            };
            if ($kodeMap) {
                $bos = BosType::where('kode', $kodeMap)->first();
                if ($bos) {
                    $spesifik = (int) (clone $q)->where('bos_type_id', $bos->id)->sum('nilai');
                    if ($spesifik > 0) {
                        return $spesifik;
                    }
                }
            }
        }

        $total = (int) $q->sum('nilai');
        if ($total > 0) {
            return $total;
        }

        if (! $pakaiEstimasi) {
            return 0;
        }

        return ($sumberDana === null || self::isReguler($sumberDana)) ? $school->estimasiPaguReguler() : 0;
    }

    /**
     * Review Kertas Kerja 3 Mode ala ARKAS 4 v4.3.0 (Bab VIII.1):
     * 1. Kontrol Pagu (Dana Pagu, Sudah Dianggarkan, Belum Dianggarkan = Rp0)
     * 2. Per Tahapan (Tahap 1 Jan–Jun vs Tahap 2 Jul–Des + rincian 12 bulan)
     * 3. Per Tahun (rekap per kode kegiatan SNP & rekening)
     * 4. Grafik Proporsi (Buku, Sarpras, Honor sesuai Permendikdasmen 8/2026 + Antar Jenis Belanja)
     */
    public static function reviewKertasKerja(RkasHeader $header, School $school, ?int $paguOverride = null, bool $pakaiEstimasi = true): array
    {
        $header->loadMissing('details.rekening.komponen');
        $sumber = $header->sumber_dana ?: ($header->details->first()->sumber_dana ?? 'BOS Reguler');
        $aturan = self::batasProporsi($school, $sumber);

        $sudahDianggarkan = (int) $header->details->sum('jumlah');
        $pagu = $paguOverride ?? self::paguSumberDana($school, (int) $header->tahun, $sumber, $pakaiEstimasi);
        $basisPagu = $pagu > 0 ? $pagu : $sudahDianggarkan;
        $belumDianggarkan = $pagu > 0 ? ($pagu - $sudahDianggarkan) : 0;

        $perBulan = array_fill(1, 12, 0);
        $perKegiatan = [];
        $bukuNominal = 0;
        $sarprasNominal = 0;
        $honorNominal = 0;
        $antarJenis = [
            '5.1.02 Belanja Operasional' => 0,
            '5.2.02 Belanja Modal Peralatan dan Mesin' => 0,
            '5.2.05 Belanja Modal Aset Tetap Lainnya' => 0,
        ];
        $rekapTahunan = [];
        $kodeNama = ArkasKode::peta($school->id, (int) $header->tahun) + self::KODE_SNP_DEFAULT;

        foreach ($header->details as $d) {
            $jml = (int) $d->jumlah;
            $kodeRek = (string) ($d->rekening->kode ?? '');
            $kompKode = (string) ($d->rekening->komponen->kode ?? '');
            $uraianLow = strtolower((string) $d->uraian.' '.($d->rekening->nama ?? ''));

            foreach ($d->nominalPerBulan() as $m => $val) {
                $perBulan[$m] += $val;
            }

            if ($kompKode === '02' || str_starts_with($kodeRek, '5.2.05') || str_contains($uraianLow, 'buku') || str_contains($uraianLow, 'perpustakaan') || str_contains($uraianLow, 'pojok baca')) {
                $bukuNominal += $jml;
            }

            if ($kompKode === '08' || str_starts_with($kodeRek, '5.1.02.03') || str_starts_with($kodeRek, '5.1.02.01.01.0001')) {
                $sarprasNominal += $jml;
            }

            if ($kompKode === '12' || str_starts_with($kodeRek, '5.1.01') || $kodeRek === '5.1.02.02.01.0013') {
                $honorNominal += $jml;
            }

            $kel = RkasDetail::kelompokAkunDariKode($kodeRek);
            $antarJenis[$kel] = ($antarJenis[$kel] ?? 0) + $jml;

            $keg = (string) ($d->kode_kegiatan ?: '06.05');
            $perKegiatan[$keg] = ($perKegiatan[$keg] ?? 0) + $jml;

            $key = $keg.'|'.$kodeRek;
            $rekapTahunan[$key] ??= [
                'kode_kegiatan' => $keg,
                'nama_kegiatan' => $kodeNama[$keg] ?? $keg,
                'kode_rekening' => $kodeRek,
                'nama_rekening' => $d->rekening->nama ?? '—',
                'jumlah' => 0,
                'tahap1' => 0,
                'tahap2' => 0,
            ];
            $rekapTahunan[$key]['jumlah'] += $jml;
            $rekapTahunan[$key]['tahap1'] += $d->nominalTahap1();
            $rekapTahunan[$key]['tahap2'] += $d->nominalTahap2();
        }

        $tahap1 = array_sum(array_slice($perBulan, 0, 6));
        $tahap2 = array_sum(array_slice($perBulan, 6, 6));

        $pct = fn (int $n): float => $basisPagu > 0 ? round($n / $basisPagu * 100, 2) : 0.0;
        $bukuPct = $pct($bukuNominal);
        $sarprasPct = $pct($sarprasNominal);
        $honorPct = $pct($honorNominal);
        $tahap1Pct = $pct($tahap1);
        $tahap2Pct = $pct($tahap2);

        $temuan = [];
        if ($pagu > 0 && $belumDianggarkan !== 0) {
            $temuan[] = [
                'level' => $belumDianggarkan < 0 ? 'blokir' : 'peringatan',
                'kode' => 'pagu_belum_nol',
                'pesan' => $belumDianggarkan > 0
                    ? 'Masih ada Belum Dianggarkan sebesar '.rp($belumDianggarkan).'. Di ARKAS 4, Belum Dianggarkan harus Rp0 sebelum Ajukan Pengesahan.'
                    : 'Total anggaran melampaui Pagu Sumber Dana sebesar '.rp(abs($belumDianggarkan)).'.',
            ];
        }

        if ($aturan['mode'] === 'reguler' && $basisPagu > 0) {
            if ($aturan['buku_min_pct'] !== null && $bukuPct < $aturan['buku_min_pct']) {
                $temuan[] = [
                    'level' => 'peringatan',
                    'kode' => 'buku_kurang',
                    'pesan' => "Proporsi Buku/Perpustakaan baru {$bukuPct}% (".rp($bukuNominal)."), kurang dari kewajiban minimal {$aturan['buku_min_pct']}% pada {$aturan['dasar_hukum']}.",
                ];
            }
            if ($aturan['sarpras_max_pct'] !== null && $sarprasPct > $aturan['sarpras_max_pct']) {
                $temuan[] = [
                    'level' => 'blokir',
                    'kode' => 'sarpras_lebih',
                    'pesan' => "Proporsi Pemeliharaan Sarpras {$sarprasPct}% (".rp($sarprasNominal).") melebihi batas maksimal {$aturan['sarpras_max_pct']}% pada {$aturan['dasar_hukum']}.",
                ];
            }
            if ($aturan['honor_max_pct'] !== null && $honorPct > $aturan['honor_max_pct']) {
                $temuan[] = [
                    'level' => 'blokir',
                    'kode' => 'honor_lebih',
                    'pesan' => "Proporsi Pembayaran Honor {$honorPct}% (".rp($honorNominal).") melebihi batas maksimal {$aturan['honor_max_pct']}% pada {$aturan['dasar_hukum']}.",
                ];
            }
            if ($aturan['tahap1_target_pct'] !== null && $tahap1Pct > $aturan['tahap1_target_pct']) {
                $temuan[] = [
                    'level' => 'peringatan',
                    'kode' => 'tahap1_lebih_50',
                    'pesan' => "Alokasi Tahap 1 (Jan–Jun) sebesar {$tahap1Pct}% (".rp($tahap1).') melebihi porsi penyaluran Tahap 1 sebesar 50% pagu.',
                ];
            }
            if ($aturan['komite_wajib'] && (empty($school->nama_komite) || empty($school->email_komite))) {
                $temuan[] = [
                    'level' => 'info',
                    'kode' => 'komite_belum_lengkap',
                    'pesan' => 'Nama & Email Komite Sekolah belum dilengkapi pada Profil Sekolah (dibutuhkan saat Aktivasi Kertas Kerja di ARKAS 4).',
                ];
            }
        }

        if ($school->isPergantianKepsekMidYear()) {
            $blnBatas = (int) $school->akhir_jabatan_bulan;
            $anggaranSetelah = array_sum(array_slice($perBulan, $blnBatas));
            if ($anggaranSetelah > 0) {
                $nmBatas = self::NAMA_BULAN[$blnBatas] ?? (string) $blnBatas;
                $temuan[] = [
                    'level' => 'peringatan',
                    'kode' => 'akhir_jabatan_kepsek',
                    'pesan' => "Masa jabatan Kepala Sekolah berakhir pada {$nmBatas}, namun terdapat alokasi ".rp($anggaranSetelah).' setelah bulan tersebut (sesuai ARKAS 4 Bab VI, anggaran sisa tahun disusun oleh Kepala Sekolah pengganti).',
                ];
            }
        }

        $adaBlokir = collect($temuan)->contains(fn ($t) => $t['level'] === 'blokir');
        $bukuOk = $aturan['buku_min_pct'] === null || $bukuPct >= $aturan['buku_min_pct'];
        $sarprasOk = $aturan['sarpras_max_pct'] === null || $sarprasPct <= $aturan['sarpras_max_pct'];
        $honorOk = $aturan['honor_max_pct'] === null || $honorPct <= $aturan['honor_max_pct'];

        return [
            'sumber_dana' => $sumber,
            'aturan' => $aturan,
            'pagu' => $pagu,
            'basis_pagu' => $basisPagu,
            'total_anggaran' => $sudahDianggarkan,
            'sudah_dianggarkan' => $sudahDianggarkan,
            'belum_dianggarkan' => $belumDianggarkan,
            'per_bulan' => $perBulan,
            'per_kegiatan' => $perKegiatan,
            'tahap1' => $tahap1,
            'tahap1_pct' => $tahap1Pct,
            'tahap2' => $tahap2,
            'tahap2_pct' => $tahap2Pct,
            'proporsi' => [
                'buku' => [
                    'label' => '📚 Buku & Perpustakaan',
                    'nilai' => $bukuNominal,
                    'nominal' => $bukuNominal,
                    'pct' => $bukuPct,
                    'persen' => $bukuPct,
                    'batas_label' => 'min '.$aturan['buku_min_pct'].'%',
                    'ok' => $bukuOk,
                    'patuh' => $bukuOk,
                ],
                'sarpras' => [
                    'label' => '🛠️ Pemeliharaan Sarpras',
                    'nilai' => $sarprasNominal,
                    'nominal' => $sarprasNominal,
                    'pct' => $sarprasPct,
                    'persen' => $sarprasPct,
                    'batas_label' => 'maks '.$aturan['sarpras_max_pct'].'%',
                    'ok' => $sarprasOk,
                    'patuh' => $sarprasOk,
                ],
                'honor' => [
                    'label' => '👩‍🏫 Pembayaran Honor Non-ASN',
                    'nilai' => $honorNominal,
                    'nominal' => $honorNominal,
                    'pct' => $honorPct,
                    'persen' => $honorPct,
                    'batas_label' => 'maks '.$aturan['honor_max_pct'].'%',
                    'ok' => $honorOk,
                    'patuh' => $honorOk,
                ],
            ],
            'antar_jenis' => collect($antarJenis)->map(fn ($nom, $label) => [
                'label' => $label,
                'nilai' => $nom,
                'nominal' => $nom,
                'pct' => $pct($nom),
                'persen' => $pct($nom),
            ])->values()->all(),
            'rekap_tahunan' => array_values($rekapTahunan),
            'temuan' => $temuan,
            'patuh_penuh' => count($temuan) === 0,
            'ada_blokir' => $adaBlokir,
        ];
    }

    /**
     * Validasi aturan ketat Pergeseran (Bab IX) vs Perubahan (Bab XI) di ARKAS 4 v4.3.0.
     *
     * @param  array<int, array{rekening_id:int, jumlah:int}>  $barisBaru
     * @return array<int, string>
     */
    public static function validasiRevisiArkas(
        string $jenisRkas,
        ?RkasHeader $acuan,
        array $barisBaru,
        int $schoolId,
        int $tahun
    ): array {
        if ($jenisRkas === 'murni' || ! $acuan) {
            return [];
        }

        $galat = [];
        $acuan->loadMissing('details.rekening');
        $rekeningKode = Rekening::pluck('kode', 'id');

        $realisasiPerRek = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $schoolId)
            ->where('t.tahun', $tahun)
            ->where('t.type', 'belanja')
            ->selectRaw('d.rekening_id, sum(d.nilai) as total')
            ->groupBy('d.rekening_id')
            ->pluck('total', 'rekening_id');

        $baruPerRek = [];
        foreach ($barisBaru as $b) {
            $rid = (int) $b['rekening_id'];
            $baruPerRek[$rid] = ($baruPerRek[$rid] ?? 0) + (int) $b['jumlah'];
        }

        foreach ($realisasiPerRek as $rid => $realisasi) {
            $realisasi = (int) $realisasi;
            if ($realisasi <= 0) {
                continue;
            }
            $angBaru = (int) ($baruPerRek[$rid] ?? 0);
            if ($angBaru < $realisasi) {
                $kode = $rekeningKode[$rid] ?? '#'.$rid;
                $galat[] = "Rekening {$kode} sudah terealisasi di BKU sebesar ".rp($realisasi).
                    ' sehingga anggarannya tidak boleh dikurangi menjadi '.rp($angBaru).' (aturan ARKAS 4).';
            }
        }

        if ($jenisRkas === 'pergeseran') {
            $totalLama = (int) $acuan->details->sum('jumlah');
            $totalBaru = array_sum(array_map(fn ($b) => (int) $b['jumlah'], $barisBaru));
            if ($totalLama !== $totalBaru) {
                $galat[] = 'Pada Pergeseran RKAS, total anggaran harus tetap sama dengan RKAS acuan ('.
                    rp($totalLama).'), sedangkan rancangan baru berjumlah '.rp($totalBaru).
                    ' (selisih '.rp(abs($totalBaru - $totalLama)).'). Gunakan Perubahan RKAS bila total berubah.';
            }

            $kelompokLama = [];
            foreach ($acuan->details as $d) {
                $kel = RkasDetail::kelompokAkunDariKode((string) ($d->rekening->kode ?? ''));
                $kelompokLama[$kel] = ($kelompokLama[$kel] ?? 0) + (int) $d->jumlah;
            }
            $kelompokBaru = [];
            foreach ($barisBaru as $b) {
                $kel = RkasDetail::kelompokAkunDariKode((string) ($rekeningKode[$b['rekening_id']] ?? ''));
                $kelompokBaru[$kel] = ($kelompokBaru[$kel] ?? 0) + (int) $b['jumlah'];
            }
            $semuaKel = array_unique(array_merge(array_keys($kelompokLama), array_keys($kelompokBaru)));
            foreach ($semuaKel as $kel) {
                $l = (int) ($kelompokLama[$kel] ?? 0);
                $n = (int) ($kelompokBaru[$kel] ?? 0);
                if ($l !== $n) {
                    $galat[] = "Pergeseran RKAS tidak boleh lintas kelompok akun belanja: {$kel} berubah dari ".
                        rp($l).' menjadi '.rp($n).'. Di ARKAS 4, lintas jenis akun hanya dapat dilakukan melalui Perubahan RKAS.';
                    break;
                }
            }
        }

        return $galat;
    }

    /**
     * Kalkulator Rekomendasi Pajak Belanja ala ARKAS 4 v4.3.0 (Bab XV.4–XV.5 & XVI).
     */
    public static function rekomendasiPajakArkas(
        string $sumberPengadaan,
        int $nilaiSebelumPajak,
        ?string $kodeRekening = null,
        bool $punyaNpwp = true,
        int $tarifPpn = 11
    ): array {
        if ($sumberPengadaan === 'siplah') {
            $msg = 'Transaksi SIPLah: pemungutan, penyetoran, dan pelaporan pajak diproses otomatis oleh marketplace SIPLah (tidak perlu input NTPN manual).';

            return [
                'wajib_lapor_manual' => false,
                'otomatis_marketplace' => true,
                'catatan' => $msg,
                'ringkasan' => $msg,
                'items' => [],
            ];
        }

        $kode = (string) $kodeRekening;
        $items = [];

        if (in_array($kode, ['5.1.02.01.01.0052', '5.1.02.01.01.0053'], true)) {
            $tarifPph = $punyaNpwp ? 2.0 : 4.0;
            $items[] = ['kode' => 'sspd', 'tarif' => 10.0, 'nilai' => (int) round($nilaiSebelumPajak * 0.10), 'keterangan' => 'Pajak Daerah (SSPD Mamin 10%)'];
            $items[] = ['kode' => 'pph23', 'tarif' => $tarifPph, 'nilai' => (int) round($nilaiSebelumPajak * ($tarifPph / 100)), 'keterangan' => 'PPh Pasal 23 Jasa Katering'];
            $msg = 'Belanja makan-minum non-SIPLah dikenakan Pajak Daerah (SSPD 10% — bebas PPN) serta PPh Pasal 23.';

            return [
                'wajib_lapor_manual' => true,
                'otomatis_marketplace' => false,
                'catatan' => $msg,
                'ringkasan' => $msg,
                'items' => $items,
            ];
        }

        if (str_starts_with($kode, '5.1.01') || in_array($kode, ['5.1.02.02.01.0003', '5.1.02.02.01.0013', '5.1.02.02.01.0016', '5.1.02.02.01.0029', '5.1.02.02.01.0037'], true)) {
            $tarif21 = $punyaNpwp ? 5.0 : 6.0;
            $items[] = ['kode' => 'pph21', 'tarif' => $tarif21, 'nilai' => (int) round($nilaiSebelumPajak * ($tarif21 / 100)), 'keterangan' => 'PPh Pasal 21 Honorarium/Jasa Orang Pribadi'];
            $msg = 'Honorarium/jasa orang pribadi dikenakan PPh Pasal 21 dan wajib dilaporkan NTPN-nya.';

            return [
                'wajib_lapor_manual' => true,
                'otomatis_marketplace' => false,
                'catatan' => $msg,
                'ringkasan' => $msg,
                'items' => $items,
            ];
        }

        if (str_starts_with($kode, '5.1.02.02.04') || str_starts_with($kode, '5.1.02.03')) {
            if ($nilaiSebelumPajak > 2_000_000) {
                $items[] = ['kode' => 'ppn', 'tarif' => (float) $tarifPpn, 'nilai' => (int) round($nilaiSebelumPajak * ($tarifPpn / 100)), 'keterangan' => "PPN {$tarifPpn}% (> Rp2.000.000)"];
            }
            $tarif23 = $punyaNpwp ? 2.0 : 4.0;
            $items[] = ['kode' => 'pph23', 'tarif' => $tarif23, 'nilai' => (int) round($nilaiSebelumPajak * ($tarif23 / 100)), 'keterangan' => 'PPh Pasal 23 Jasa/Pemeliharaan/Sewa'];
            $msg = 'Belanja jasa/sewa/pemeliharaan non-SIPLah dikenakan PPh Pasal 23'.($nilaiSebelumPajak > 2_000_000 ? ' serta PPN.' : ' (bebas pungut PPN karena ≤ Rp2.000.000).');

            return [
                'wajib_lapor_manual' => true,
                'otomatis_marketplace' => false,
                'catatan' => $msg,
                'ringkasan' => $msg,
                'items' => $items,
            ];
        }

        if ($nilaiSebelumPajak <= 2_000_000) {
            $msg = 'Nilai transaksi sebelum pajak ≤ Rp2.000.000: tidak terutang pemungutan PPN/PPh 22 Bendahara BOSP (Pajak Wajib Lapor = Rp0 sesuai ARKAS 4).';

            return [
                'wajib_lapor_manual' => false,
                'otomatis_marketplace' => false,
                'catatan' => $msg,
                'ringkasan' => $msg,
                'items' => [],
            ];
        }

        $tarif22 = $punyaNpwp ? 1.5 : 3.0;
        $items[] = ['kode' => 'ppn', 'tarif' => (float) $tarifPpn, 'nilai' => (int) round($nilaiSebelumPajak * ($tarifPpn / 100)), 'keterangan' => "PPN {$tarifPpn}% (> Rp2.000.000)"];
        $items[] = ['kode' => 'pph22', 'tarif' => $tarif22, 'nilai' => (int) round($nilaiSebelumPajak * ($tarif22 / 100)), 'keterangan' => 'PPh Pasal 22 Pembelian Barang (> Rp2.000.000)'];
        $msg = 'Transaksi barang non-SIPLah > Rp2.000.000: wajib memungut & melaporkan NTPN PPN dan PPh Pasal 22 paling lambat tanggal 20 bulan berikutnya.';

        return [
            'wajib_lapor_manual' => true,
            'otomatis_marketplace' => false,
            'catatan' => $msg,
            'ringkasan' => $msg,
            'items' => $items,
        ];
    }

    /**
     * Status Tenggat Pelaporan & Simulasi Pemotongan Penyaluran sesuai Permendikdasmen No. 8 Tahun 2026 Pasal 59.
     */
    public static function statusTenggatPelaporan(int $schoolId, int $tahun, ?Carbon $acuanTanggal = null): array
    {
        $kini = $acuanTanggal ?? now();
        $pagu = (int) Allocation::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('tahun', $tahun)->sum('nilai');
        $paguTahap2 = intdiv($pagu, 2);

        $stage1 = Stage::where('tahun', $tahun)->where('urutan', 1)->first();
        $lpjTahap1 = $stage1 ? LpjSubmission::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('tahun', $tahun)->where('stage_id', $stage1->id)
            ->whereIn('status', ['dikirim', 'diterima'])->exists() : false;
        $bkuJuniTutup = BkuTutupBulan::terkunciBulan($schoolId, sprintf('%d-06', $tahun));
        $tahap1Tuntas = $lpjTahap1 || $bkuJuniTutup;

        $batasTahap1 = Carbon::create($tahun, 7, 31, 23, 59, 59);

        $potonganPersen = 0;
        $statusTahap1 = 'aman';
        $pesanTahap1 = "Batas konfirmasi pelaporan Tahap I: 31 Juli {$tahun} (Permendikdasmen No. 8 Tahun 2026 Pasal 59).";

        if ($tahap1Tuntas) {
            $statusTahap1 = 'selesai';
            $pesanTahap1 = "Pelaporan Tahap I TA {$tahun} sudah ditutup/dikirim (aman dari pengurangan salur Tahap II).";
        } elseif ($kini->gt($batasTahap1) && (int) $kini->year === $tahun) {
            $m = (int) $kini->month;
            $d = (int) $kini->day;
            if ($m === 8) {
                $potonganPersen = 2;
                $statusTahap1 = 'potongan';
                $pesanTahap1 = 'Lewat 31 Juli (pelaporan di bulan Agustus): pengurangan penyaluran Tahap II sebesar 2% ('.rp((int) round($paguTahap2 * 0.02)).').';
            } elseif ($m === 9) {
                $potonganPersen = 3;
                $statusTahap1 = 'potongan';
                $pesanTahap1 = 'Lewat 31 Juli (pelaporan di bulan September): pengurangan penyaluran Tahap II sebesar 3% ('.rp((int) round($paguTahap2 * 0.03)).').';
            } elseif ($m === 10 && $d <= 25) {
                $potonganPersen = 4;
                $statusTahap1 = 'potongan';
                $pesanTahap1 = 'Kritis! Pelaporan 1–25 Oktober dikenakan pengurangan penyaluran Tahap II sebesar 4% ('.rp((int) round($paguTahap2 * 0.04)).'). Batas akhir mutlak 25 Oktober!';
            } else {
                $potonganPersen = 100;
                $statusTahap1 = 'hangus';
                $pesanTahap1 = "Melewati 25 Oktober {$tahun}: berisiko tidak menerima penyaluran Dana BOSP Reguler Tahap II.";
            }
        } elseif (! $tahap1Tuntas && $kini->betweenIncluded(Carbon::create($tahun, 7, 1), $batasTahap1)) {
            $hariSisa = (int) $kini->diffInDays($batasTahap1, false);
            $statusTahap1 = 'peringatan';
            $pesanTahap1 = "Pengingat H-{$hariSisa}: segera tutup BKU s.d. Juni & kirim LPJ Tahap I sebelum 31 Juli {$tahun} agar bebas potongan 2%–4%!";
        }

        $bkuDesTutup = BkuTutupBulan::terkunciBulan($schoolId, sprintf('%d-12', $tahun));

        return [
            'tahap1_tuntas' => $tahap1Tuntas,
            'tahap1_terlambat' => in_array($statusTahap1, ['potongan', 'hangus'], true),
            'status_tahap1' => $statusTahap1,
            'potongan_pct' => $potonganPersen,
            'potongan_persen' => $potonganPersen,
            'potongan_rupiah' => $potonganPersen === 100 ? $paguTahap2 : (int) round($paguTahap2 * ($potonganPersen / 100)),
            'pesan' => $pesanTahap1,
            'pesan_tahap1' => $pesanTahap1,
            'batas_tahap1' => "31 Juli {$tahun}",
            'batas_tahunan' => '31 Januari '.($tahun + 1),
            'tahunan_tuntas' => $bkuDesTutup,
        ];
    }

    /**
     * Cek apakah realisasi belanja suatu rekening pada bulan `Y-m` melebihi kuota kumulatif
     * Kertas Kerja RKAS sampai bulan tersebut.
     */
    public static function cekKuotaBulananRkas(
        int $schoolId,
        int $tahun,
        string $ym,
        array $tambahPerRekening = []
    ): array {
        $rkas = RkasHeader::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('tahun', $tahun)
            ->where('status', '!=', 'draft')
            ->orderByDesc('revisi_ke')
            ->first()
            ?? RkasHeader::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('tahun', $tahun)
                ->orderByDesc('revisi_ke')
                ->first();

        if (! $rkas) {
            return [];
        }

        $rkas->loadMissing('details.rekening');
        $bulanKe = max(1, min(12, (int) substr($ym, 5, 2)));

        $kuotaSdBulanIni = [];
        $kuotaTahunan = [];
        $kodeMap = [];
        foreach ($rkas->details as $d) {
            $rid = (int) $d->rekening_id;
            $kodeMap[$rid] = $d->rekening->kode ?? '#'.$rid;
            $nb = $d->nominalPerBulan();
            $kuotaSdBulanIni[$rid] = ($kuotaSdBulanIni[$rid] ?? 0) + array_sum(array_slice($nb, 0, $bulanKe));
            $kuotaTahunan[$rid] = ($kuotaTahunan[$rid] ?? 0) + (int) $d->jumlah;
        }

        $realSdBulanIni = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->where('t.school_id', $schoolId)
            ->where('t.tahun', $tahun)
            ->where('t.type', 'belanja')
            ->whereRaw('substr(CAST(t.tanggal AS VARCHAR), 1, 7) <= ?', [$ym])
            ->selectRaw('d.rekening_id, sum(d.nilai) as j')
            ->groupBy('d.rekening_id')
            ->pluck('j', 'rekening_id');

        $peringatan = [];
        foreach ($tambahPerRekening as $rid => $nilaiTambah) {
            $rid = (int) $rid;
            $kuotaSd = (int) ($kuotaSdBulanIni[$rid] ?? 0);
            $kuotaTh = (int) ($kuotaTahunan[$rid] ?? 0);
            $sudahSd = (int) ($realSdBulanIni[$rid] ?? 0) + (int) $nilaiTambah;
            $kode = $kodeMap[$rid] ?? (Rekening::find($rid)?->kode ?? '#'.$rid);

            if ($kuotaTh > 0 && $sudahSd > $kuotaSd && $sudahSd <= $kuotaTh) {
                $peringatan[] = "Belanja rekening {$kode} pada {$ym} (kumulatif ".rp($sudahSd).") mendahului kuota rencana RKAS s.d. bulan ke-{$bulanKe} (".rp($kuotaSd).' dari pagu tahunan '.rp($kuotaTh).'). Di ARKAS 4, lakukan Pergeseran bulan rencana terlebih dahulu.';
            }
        }

        return $peringatan;
    }
}
