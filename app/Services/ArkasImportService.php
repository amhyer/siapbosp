<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\ArkasKode;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasNota;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor ARKAS → SiapBOS via Excel (.xlsx hasil export.js).
 *
 * Spesifikasi: docs/IMPOR-ARKAS-EXCEL.md. Prinsip: idempoten (updateOrCreate
 * berdasar arkas_id), hormati tutup buku, rekening tak dikenal diskip +
 * dilaporkan, tanpa penghapusan, satu transaksi DB, laporan ke activity_logs.
 */
class ArkasImportService
{
    /** jenis_bku ARKAS → aksi impor. Kode_bku TIDAK dipakai (ambigu: PBS/BBU ganda). */
    public const AKSI_PENDAPATAN = ['Terima Dana BOS'];

    public const AKSI_BELANJA_TUNAI = ['Kas Keluar'];

    public const AKSI_BELANJA_TRANSFER = ['Kas Keluar Non Tunai'];

    public const AKSI_AMBIL_SIMPANAN = ['Tarik Tunai'];

    /** Kaki kedua dari Tarik Tunai (tgl+nominal sama) — diskip agar tak dobel catat. */
    public const AKSI_SKIP_COUNTERPART = ['Pergeseran Tunai'];

    public const AKSI_TERIMA_PAJAK = ['Pajak Belanja Terima'];

    public const AKSI_SETOR_PAJAK = ['Pajak Belanja Setor'];

    /** Saldo awal: SiapBOS menghitung SILPA otomatis — diskip. */
    public const AKSI_SKIP_SALDO = ['Saldo Awal Bank', 'Saldo Awal Tunai'];

    public const AKSI_SKIP_LAIN = ['Bunga Bank', 'Pajak Bunga'];

    /** Flag pajak KAS_Umum → kode rekening_pajak SiapBOS. */
    public const PAJAK_MAP = [
        'is_ppn' => 'ppn',
        'is_pph_21' => 'pph21',
        'is_pph_22' => 'pph22',
        'is_pph_23' => 'pph23',
        'is_pph_4' => 'pph42',
    ];

    public static function import(string $path, int $schoolId, int $userId, ?int $tahun = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($path);

        $lap = [
            'tahun' => null, 'npsn' => null,
            'dibuat' => ['rkas' => 0, 'transaksi' => 0, 'pajak_terima' => 0, 'pajak_setor' => 0, 'penjab' => 0, 'alokasi' => 0],
            'diperbarui' => ['rkas' => 0, 'transaksi' => 0, 'pajak_terima' => 0, 'pajak_setor' => 0, 'profil' => 0, 'alokasi' => 0],
            'digabung' => ['transaksi' => 0],
            'dilewati' => ['rkas' => 0, 'transaksi' => 0, 'pajak' => 0],
            'alasan_lewat' => [],
            'rekening_tak_dikenal' => [],
            'rekening_asing_nama' => [],
            'selisih_pagu' => [],
            'info_salur' => null,
            'info_saldo_awal' => null,
            'info_ptk' => null,
            'periode_terkunci' => [],
            'selisih_hapus' => [],
            'peringatan' => [],
        ];

        return DB::transaction(function () use ($wb, $schoolId, $userId, $tahun, &$lap) {
            $sekolah = School::findOrFail($schoolId);

            // ---- 1. Identitas: NPSN dari sheet Sekolah ----
            $sekRows = self::sheetAssoc($wb, 'Sekolah');
            if (! $sekRows) {
                throw new \RuntimeException('Sheet Sekolah tidak ditemukan / kosong — identitas sekolah tak bisa dipastikan.');
            }
            $npsnFile = array_unique(array_filter(array_map(
                fn ($r) => trim((string) ($r['npsn'] ?? '')), $sekRows)));
            $lap['npsn'] = implode(', ', $npsnFile);
            if (! in_array($sekolah->npsn, $npsnFile, true)) {
                throw new \RuntimeException("NPSN sekolah ({$sekolah->npsn}) tidak ada di file (isi: {$lap['npsn']}). Impor ditolak.");
            }
            if (count($npsnFile) > 1) {
                throw new \RuntimeException('File memuat >1 NPSN ('.$lap['npsn'].') — baris KAS tak bisa diatribusikan. Ekspor per sekolah dulu.');
            }
            if (! $sekolah->is_active) {
                throw new \RuntimeException("Sekolah {$sekolah->nama} nonaktif. Impor ditolak.");
            }

            // ---- 2. Tahun: dari tahun_anggaran dominan KAS_Umum ----
            $kasRows = self::sheetAssoc($wb, 'KAS_Umum');
            if (! $kasRows) {
                throw new \RuntimeException('Sheet KAS_Umum tidak ditemukan / kosong.');
            }
            if ($tahun === null) {
                $freq = [];
                foreach ($kasRows as $r) {
                    $t = trim((string) ($r['tahun_anggaran'] ?? ''));
                    if (ctype_digit($t)) {
                        $freq[$t] = ($freq[$t] ?? 0) + 1;
                    }
                }
                if (! $freq) {
                    throw new \RuntimeException('Kolom tahun_anggaran kosong di seluruh KAS_Umum — tentukan tahun manual.');
                }
                arsort($freq);
                $tahun = (int) array_key_first($freq);
            }
            $lap['tahun'] = $tahun;

            $rekeningMap = Rekening::pluck('id', 'kode')->all();
            $pajakMap = RekeningPajak::pluck('id', 'kode')->all();
            // Jenis sumber dana baru (BOSP Afirmasi, Lainnya) — pastikan ada
            // walau master belum di-seed di instalasi lama.
            foreach (['afirmasi' => 'BOSP Afirmasi', 'lainnya' => 'Lainnya (Banpem/Dana Lain)'] as $k => $n) {
                BosType::firstOrCreate(['kode' => $k], ['nama' => $n]);
            }
            $bosMap = BosType::pluck('id', 'kode')->all();
            $stageMap = Stage::where('tahun', $tahun)->orderBy('urutan')->pluck('id', 'urutan')->all();

            $kasRows = array_values(array_filter($kasRows,
                fn ($r) => (int) ($r['tahun_anggaran'] ?? 0) === $tahun));

            // Peta kode kegiatan ARKAS (Ref_Kode ← RAPBS ← RAPBS_Periode).
            [$rapbsKeg, $periodeKeg, $volTahap, $refList] = self::petaKegiatan($wb);
            self::imporKodes($sekolah, $tahun, $refList, $lap);
            self::imporNotas($wb, $sekolah, $tahun, $lap);

            // ---- 3. RAPBS → RKAS ----
            self::imporRkas($wb, $sekolah, $userId, $tahun, $rekeningMap, $lap, $rapbsKeg, $volTahap);

            // ---- 3b. Pelengkap tulis: profil, penjab, pagu ----
            self::imporPelengkap($wb, $sekolah, $tahun, $bosMap, $lap);

            // ---- 4. KAS → kas_transactions (+ kas_details) ----
            $seenTrx = [];
            $belanjaIds = self::imporKas($wb, $kasRows, $sekolah, $userId, $tahun,
                $rekeningMap, $bosMap, $stageMap, $lap, $seenTrx, $periodeKeg);

            // ---- 5. Pajak: terima menempel ke belanja, setor menandai sudah ----
            self::imporPajak($wb, $kasRows, $sekolah, $userId, $tahun,
                $pajakMap, $stageMap, $belanjaIds, $lap, $seenTrx);

            // ---- 6. Selisih hapus (hanya daftar — TIDAK menghapus) ----
            self::selisihHapus($sekolah->id, $tahun, $seenTrx, $lap);

            // ---- 6b. Info silang (setelah semua transaksi tertulis) ----
            self::infoSilang($wb, $sekolah, $tahun, $lap);

            // ---- 7. Sanity saldo akhir (peringatan saja, tanpa rollback) ----
            $saldo = KasService::saldo($sekolah->id, $tahun);
            if ($saldo['tunai'] < 0 || $saldo['bank'] < 0) {
                $lap['peringatan'][] = 'Saldo akhir negatif setelah impor (tunai '.rp($saldo['tunai'])
                    .', bank '.rp($saldo['bank']).') — periksa kelengkapan data ARKAS.';
            }

            ActivityLog::create([
                'school_id' => $sekolah->id, 'user_id' => $userId,
                'user_name' => User::find($userId)?->name ?? 'Impor ARKAS',
                'aksi' => 'impor_arkas',
                'keterangan' => self::ringkasan($lap),
            ]);

            return $lap;
        });
    }

    /** Peta kegiatan: id_rapbs → kode + id_rapbs_periode → kode + volume per tahap. */
    protected static function petaKegiatan($wb): array
    {
        $refKode = [];
        $refList = []; // kode → ['uraian'=>, 'level'=>] (untuk arkas_kodes)
        try {
            foreach (self::sheetAssoc($wb, 'Ref_Kode') as $k) {
                $rk = trim((string) ($k['id_ref_kode'] ?? ''));
                if ($rk === '') {
                    continue;
                }
                $idKode = trim((string) ($k['id_kode'] ?? ''));
                $refKode[$rk] = $idKode;
                if ($idKode !== '') {
                    $refList[$idKode] = [
                        'uraian' => trim((string) ($k['uraian_kode'] ?? '')),
                        'level' => (int) ($k['id_level_kode'] ?? 2),
                    ];
                }
            }
        } catch (\RuntimeException) {
        }
        $rapbsKeg = [];
        try {
            foreach (self::sheetAssoc($wb, 'RAPBS') as $r) {
                $id = trim((string) ($r['id_rapbs'] ?? ''));
                $rk = trim((string) ($r['id_ref_kode'] ?? ''));
                if ($id !== '' && isset($refKode[$rk])) {
                    $rapbsKeg[$id] = $refKode[$rk];
                }
            }
        } catch (\RuntimeException) {
        }
        $periodeKeg = [];
        $volTahap = []; // id_rapbs → [tahap => volume]
        try {
            $urutanPerRapbs = [];
            foreach (self::sheetAssoc($wb, 'RAPBS_Periode') as $p) {
                $id = trim((string) ($p['id_rapbs_periode'] ?? ($p['id'] ?? '')));
                $ir = trim((string) ($p['id_rapbs'] ?? ''));
                if ($id !== '' && isset($rapbsKeg[$ir])) {
                    $periodeKeg[$id] = $rapbsKeg[$ir];
                }
                if ($ir === '') {
                    continue;
                }
                // Volume per tahap: kolom v1..v4 bila terisi, selain itu urutan baris.
                $v = (float) ($p['volume'] ?? 0);
                $vs = [(float) ($p['v1'] ?? 0), (float) ($p['v2'] ?? 0), (float) ($p['v3'] ?? 0), (float) ($p['v4'] ?? 0)];
                if (array_sum($vs) > 0) {
                    foreach ($vs as $i => $vi) {
                        if ($vi > 0) {
                            $volTahap[$ir][$i + 1] = ($volTahap[$ir][$i + 1] ?? 0) + $vi;
                        }
                    }
                } elseif ($v > 0) {
                    $n = $urutanPerRapbs[$ir] ?? 1;
                    $volTahap[$ir][$n] = ($volTahap[$ir][$n] ?? 0) + $v;
                    $urutanPerRapbs[$ir] = $n + 1;
                }
            }
        } catch (\RuntimeException) {
        }

        return [$rapbsKeg, $periodeKeg, $volTahap, $refList];
    }

    /** Master kode kegiatan ARKAS (nama program/kegiatan untuk Laporan ARKAS). */
    protected static function imporKodes(School $sekolah, int $tahun, array $refList, array &$lap): void
    {
        foreach ($refList as $kode => $k) {
            if ($kode === '') {
                continue;
            }
            ArkasKode::updateOrCreate(
                ['school_id' => $sekolah->id, 'tahun' => $tahun, 'kode' => $kode],
                ['uraian' => $k['uraian'], 'level' => max(1, min(3, $k['level']))]
            );
        }
        $lap['master_kode'] = count($refList);
    }

    /** Daftar nota ARKAS → kas_notas (idempoten via arkas_id = nota:id_kas_nota). */
    protected static function imporNotas($wb, School $sekolah, int $tahun, array &$lap): void
    {
        try {
            foreach (self::sheetAssoc($wb, 'Nota') as $n) {
                if ((string) ($n['soft_delete'] ?? '') === '1') {
                    continue;
                }
                $tgl = substr(trim((string) ($n['tanggal_nota'] ?? '')), 0, 10);
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) || (int) substr($tgl, 0, 4) !== $tahun) {
                    continue;
                }
                $id = trim((string) ($n['id_kas_nota'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $total = (int) ($n['total'] ?? 0);
                KasNota::updateOrCreate(
                    ['school_id' => $sekolah->id, 'arkas_id' => 'nota:'.$id],
                    ['tahun' => $tahun,
                        'no_bukti' => trim((string) ($n['no_bukti'] ?? '')) ?: null,
                        'tanggal_nota' => $tgl,
                        'no_nota' => trim((string) ($n['no_nota'] ?? '')) ?: null,
                        'nama_toko' => trim((string) ($n['nama_toko'] ?? '')) ?: null,
                        'alamat_toko' => trim((string) ($n['alamat_toko'] ?? '')) ?: null,
                        'no_telp' => trim((string) ($n['no_telp'] ?? '')) ?: null,
                        'npwp' => trim((string) ($n['npwp'] ?? '')) ?: null,
                        'total' => $total, 'lunas_bayar' => $total]
                );
                $lap['dibuat']['nota'] = ($lap['dibuat']['nota'] ?? 0);
            }
            $lap['dibuat']['nota'] = KasNota::where('school_id', $sekolah->id)->where('tahun', $tahun)->count();
        } catch (\RuntimeException) {
        }
    }

    // ==================== RAPBS → RKAS ====================

    /**
     * Revisi anggaran aktif terbaru per sumber dana.
     *
     * ARKAS menyimpan beberapa revisi RKAS per tahun (tiap revisi punya pagu
     * penuh). Hanya revisi TERAKHIR yang dipakai — kalau semua dijumlahkan,
     * pagu ikut terduplikasi (terbukti: 3 revisi × Rp342,9 jt = Rp905,5 jt).
     *
     * @return array<int,array{id_anggaran:string,nama:string,jumlah:int}> kunci id_ref_sumber_dana
     */
    protected static function anggaranTerpilih($wb, int $tahun): array
    {
        try {
            $aggs = self::sheetAssoc($wb, 'Anggaran');
        } catch (\RuntimeException) {
            return [];
        }
        $aktif = array_values(array_filter($aggs, fn ($a) => (int) ($a['tahun_anggaran'] ?? 0) === $tahun
            && (string) ($a['soft_delete'] ?? '') !== '1' && (string) ($a['is_aktif'] ?? '') === '1'));
        if (! $aktif) {
            return [];
        }
        usort($aktif, fn ($a, $b) => strcmp((string) ($a['last_update'] ?? ''), (string) ($b['last_update'] ?? '')));
        $perSumber = [];
        foreach ($aktif as $a) {
            $perSumber[(int) ($a['id_ref_sumber_dana'] ?? 0)] = $a;
        }
        $terpilih = [];
        foreach ($perSumber as $sumberId => $a) {
            $terpilih[$sumberId] = [
                'id_anggaran' => trim((string) ($a['id_anggaran'] ?? '')),
                'nama' => (string) ($a['sumber_dana'] ?? 'BOS Reguler') ?: 'BOS Reguler',
                'jumlah' => (int) ($a['jumlah'] ?? 0),
            ];
        }

        return $terpilih;
    }

    protected static function imporRkas($wb, School $sekolah, int $userId, int $tahun, array $rekeningMap, array &$lap, array $rapbsKeg = [], array $volTahap = []): void
    {
        try {
            $rows = self::sheetAssoc($wb, 'RAPBS');
        } catch (\RuntimeException) {
            $rows = [];
        }
        if (! $rows) {
            return;
        }
        $rows = array_values(array_filter($rows, fn ($r) => (int) ($r['tahun_anggaran'] ?? 0) === $tahun && (string) ($r['soft_delete'] ?? '') !== '1'));

        // Hanya revisi RKAS terbaru per sumber dana yang diimpor.
        $terpilih = self::anggaranTerpilih($wb, $tahun);
        if ($terpilih) {
            $idAktif = array_column($terpilih, 'id_anggaran');
            $idAktif = array_filter($idAktif, fn ($v) => $v !== '');
            if ($idAktif) {
                $sebelum = count($rows);
                $rows = array_values(array_filter($rows, function ($r) use ($idAktif) {
                    $id = trim((string) ($r['id_anggaran'] ?? ''));

                    return $id === '' || in_array($id, $idAktif, true);
                }));
                $selisih = $sebelum - count($rows);
                if ($selisih > 0) {
                    $lap['alasan_lewat'][] = 'RKAS: '.$selisih.' baris dari revisi lama diabaikan — dipakai revisi terbaru per sumber dana.';
                }
            }
        }

        $header = RkasHeader::withoutGlobalScope('school')
            ->where('school_id', $sekolah->id)->where('tahun', $tahun)
            ->where('status', 'draft')->orderByDesc('revisi_ke')->first();
        if (! $header) {
            $adaNonDraft = RkasHeader::withoutGlobalScope('school')
                ->where('school_id', $sekolah->id)->where('tahun', $tahun)->exists();
            if ($adaNonDraft) {
                $lap['alasan_lewat'][] = 'RKAS: header tahun '.$tahun.' sudah disetujui/dikunci — impor RKAS dilewati ('.count($rows).' baris), sheet lain tetap diproses.';
                $lap['dilewati']['rkas'] += count($rows);

                return;
            }
            $header = RkasHeader::withoutGlobalScope('school')->create([
                'school_id' => $sekolah->id, 'tahun' => $tahun, 'revisi_ke' => 1,
                'status' => 'draft', 'catatan' => 'Impor ARKAS '.now()->format('d/m/Y')." (NPSN {$sekolah->npsn})",
            ]);
        }

        foreach ($rows as $r) {
            $kode = trim((string) ($r['kode_rekening'] ?? ''));
            $rid = $rekeningMap[$kode] ?? null;
            if (! $rid) {
                self::catatKodeAsing($lap, $kode);
                $lap['dilewati']['rkas']++;

                continue;
            }
            $banyak = max(1, (int) ($r['volume'] ?? 1));
            $harga = (int) ($r['harga_satuan'] ?? 0);
            $jumlah = (int) ($r['jumlah'] ?? 0) ?: $banyak * $harga;
            $d = RkasDetail::updateOrCreate(
                ['rkas_header_id' => $header->id, 'arkas_id' => 'rapbs:'.($r['id_rapbs'] ?? '')],
                ['rekening_id' => $rid, 'uraian' => (string) ($r['uraian'] ?? $r['uraian_text'] ?? 'Item ARKAS'),
                    'satuan' => (string) ($r['satuan'] ?? 'paket') ?: 'paket',
                    'banyak' => $banyak, 'harga_satuan' => $harga, 'jumlah' => $jumlah,
                    'kode_kegiatan' => $rapbsKeg[trim((string) ($r['id_rapbs'] ?? ''))] ?? null,
                    'periode_volume' => isset($volTahap[trim((string) ($r['id_rapbs'] ?? ''))])
                        ? json_encode($volTahap[trim((string) ($r['id_rapbs'] ?? ''))]) : null,
                    'sumber_dana' => (string) ($r['sumber_dana'] ?? 'BOS Reguler') ?: 'BOS Reguler']
            );
            $lap[$d->wasRecentlyCreated ? 'dibuat' : 'diperbarui']['rkas']++;
        }

        // Bersihkan baris dari revisi lama (hanya pada header draf impor).
        // Tanpa ini pagu & anggaran terduplikasi (3 revisi ARKAS = 3× pagu).
        if ($rows) {
            $tersimpan = RkasDetail::where('rkas_header_id', $header->id)
                ->whereNotNull('arkas_id')->where('arkas_id', 'like', 'rapbs:%')->pluck('arkas_id')->all();
            $dipakai = array_map(fn ($r) => 'rapbs:'.($r['id_rapbs'] ?? ''), $rows);
            $basi = array_values(array_diff($tersimpan, $dipakai));
            if ($basi) {
                RkasDetail::where('rkas_header_id', $header->id)->whereIn('arkas_id', $basi)->delete();
                $lap['alasan_lewat'][] = 'RKAS: '.count($basi).' baris dari revisi lama dihapus dari draf (revisi terbaru ARKAS yang dipakai).';
            }
        }
    }

    // ==================== Pelengkap: profil, penjab, pagu, info ====================

    /** id_ref_sumber_dana ARKAS → kode bos_types SiapBOS (null = tak dipetakan). */
    public const SUMBER_MAP = [
        1 => 'reguler', 3 => 'bosda', 12 => 'kinerja',
        33 => 'silpa', 34 => 'silpa', 35 => 'silpa',
        5 => 'lainnya', 6 => 'reguler',
        11 => 'afirmasi', // BOS Afirmasi (ARKAS 4.2.16+ / Permendikdasmen 8/2026 Pasal 46)
    ];

    protected static function imporPelengkap($wb, School $sekolah, int $tahun, array $bosMap, array &$lap): void
    {
        // Nama rekening asing (untuk usulan master ke Dinas).
        try {
            foreach (self::sheetAssoc($wb, 'Ref_Rekening') as $rr) {
                $kd = trim((string) ($rr['kode_rekening'] ?? ''));
                if ($kd !== '' && ! isset($lap['rekening_asing_nama'][$kd])) {
                    $lap['rekening_asing_nama'][$kd] = trim((string) ($rr['rekening'] ?? ''));
                }
            }
        } catch (\RuntimeException) {
        }

        // Profil: isi yang kosong saja (nama & data Dinas tak ditimpa).
        $sekRows = self::sheetAssoc($wb, 'Sekolah');
        $prof = $sekRows[0] ?? [];
        $isi = [];
        if (! $sekolah->alamat && trim((string) ($prof['alamat'] ?? '')) !== '') {
            $isi['alamat'] = trim((string) $prof['alamat']);
        }
        if ($isi) {
            $sekolah->update($isi);
            $lap['diperbarui']['profil']++;
        }

        // Penandatangan dari Penjab tahun berjalan (terbaru).
        try {
            $penjabs = array_values(array_filter(
                self::sheetAssoc($wb, 'Penjab'),
                fn ($p) => (int) ($p['tahun'] ?? 0) === $tahun && (string) ($p['soft_delete'] ?? '') !== '1'
            ));
            usort($penjabs, fn ($a, $b) => strcmp((string) ($a['tanggal_mulai'] ?? ''), (string) ($b['tanggal_mulai'] ?? '')));
            $pj = end($penjabs);
            if ($pj) {
                foreach (['ks' => 'Kepala Sekolah', 'bendahara' => 'Bendahara BOSP', 'komite' => 'Komite Sekolah'] as $kol => $jab) {
                    $nama = trim((string) ($pj[$kol] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $nip = trim((string) ($pj['nip_'.$kol] ?? ''));
                    $ada = Penandatangan::where('school_id', $sekolah->id)
                        ->where('nama', $nama)->where('jabatan', $jab)->first();
                    if (! $ada) {
                        Penandatangan::create([
                            'school_id' => $sekolah->id, 'nama' => $nama,
                            'nip' => $nip ?: null, 'jabatan' => $jab,
                        ]);
                        $lap['dibuat']['penjab']++;
                    } elseif (! $ada->nip && $nip !== '') {
                        $ada->update(['nip' => $nip]);
                    }
                }
            }
        } catch (\RuntimeException) {
        }

        // Pagu dari Anggaran (revisi aktif terbaru per sumber). Hanya isi yang
        // belum ada — pagu Dinas tak ditimpa; selisih dilaporkan.
        try {
            $perSumber = self::anggaranTerpilih($wb, $tahun);
            foreach ($perSumber as $sumberId => $a) {
                $kodeBos = self::SUMBER_MAP[$sumberId] ?? null;
                if (! $kodeBos || ! isset($bosMap[$kodeBos])) {
                    continue;
                }
                $nilai = (int) $a['jumlah'];
                $eksis = Allocation::where('school_id', $sekolah->id)
                    ->where('tahun', $tahun)->where('bos_type_id', $bosMap[$kodeBos])
                    ->whereNull('stage_id')->first();
                if (! $eksis) {
                    Allocation::create([
                        'school_id' => $sekolah->id, 'tahun' => $tahun,
                        'bos_type_id' => $bosMap[$kodeBos], 'nilai' => $nilai,
                    ]);
                    $lap['dibuat']['alokasi']++;
                } elseif ((int) $eksis->nilai !== $nilai) {
                    $lap['selisih_pagu'][] = $kodeBos.' '.$tahun.': SiapBOS '.rp((int) $eksis->nilai).' vs ARKAS '.rp($nilai);
                }
            }
        } catch (\RuntimeException) {
        }

        // Validasi silang: dana salur vs dana cair tercatat.
        // (Dipindah ke infoSilang agar dibaca setelah transaksi tertulis.)
        try {
            $ptk = array_filter(
                self::sheetAssoc($wb, 'PTK'),
                fn ($p) => (string) ($p['soft_delete'] ?? '') !== '1'
            );
            if ($ptk) {
                $lap['info_ptk'] = count($ptk).' GTK di ARKAS (modul PTK belum ada di SiapBOS — tidak diimpor).';
            }
        } catch (\RuntimeException) {
        }
    }

    /** Info baca-saja SETELAH transaksi tertulis: salur vs cair + saldo awal. */
    protected static function infoSilang($wb, School $sekolah, int $tahun, array &$lap): void
    {
        try {
            $salur = 0;
            foreach (self::sheetAssoc($wb, 'Salur') as $s) {
                if ((int) ($s['tahun'] ?? 0) === $tahun
                    && ($s['jenis'] ?? '') === 'dana_salur' && (string) ($s['tidak_terima_dana'] ?? '') !== '1') {
                    $salur += (int) ($s['nilai_salur_bersih'] ?? 0);
                }
            }
            if ($salur > 0) {
                $cair = (int) KasTransaction::withoutGlobalScope('school')
                    ->where('school_id', $sekolah->id)->where('tahun', $tahun)
                    ->where('type', 'pendapatan')->sum('total');
                $lap['info_salur'] = ['salur' => $salur, 'cair' => $cair];
                if ($cair < $salur) {
                    $lap['peringatan'][] = 'Dana salur ARKAS ('.rp($salur).') melebihi penerimaan tercatat ('.rp($cair).').';
                }
            }
        } catch (\RuntimeException) {
        }

        try {
            $bank = 0;
            $tunai = 0;
            foreach (self::sheetAssoc($wb, 'Aktivasi_BKU') as $v) {
                $tgl = substr(trim((string) ($v['tanggal_aktivasi'] ?? '')), 0, 7);
                if ($tgl === $tahun.'-01' && (string) ($v['soft_delete'] ?? '') !== '1') {
                    $bank = max($bank, (int) ($v['saldo_awal_bank'] ?? 0));
                    $tunai = max($tunai, (int) ($v['saldo_awal_tunai'] ?? 0));
                }
            }
            $lap['info_saldo_awal'] = ['bank' => $bank, 'tunai' => $tunai];
        } catch (\RuntimeException) {
        }
    }

    // ==================== KAS → transaksi ====================

    protected static function imporKas($wb, array $kasRows, School $sekolah, int $userId, int $tahun, array $rekeningMap, array $bosMap, array $stageMap, array &$lap, array &$seenTrx, array $periodeKeg = []): array
    {
        $belanjaIds = []; // arkasKey => id transaksi (untuk penempelan pajak)
        $notaSiplah = [];
        try {
            foreach (self::sheetAssoc($wb, 'Nota') as $n) {
                if (substr((string) ($n['tanggal_nota'] ?? ''), 0, 4) === (string) $tahun
                    && (string) ($n['soft_delete'] ?? '') !== '1'
                    && (string) ($n['is_beli_di_siplah'] ?? '') === '1'
                    && trim((string) ($n['no_bukti'] ?? '')) !== '') {
                    $notaSiplah[trim((string) $n['no_bukti'])] = true;
                }
            }
        } catch (\RuntimeException) {
        }
        foreach ($kasRows as $r) {
            // Baris yang dihapus di ARKAS (kolom hanya ada di ekspor baru; file
            // lama tanpa kolom ini tetap diproses seperti sebelumnya).
            if ((string) ($r['soft_delete'] ?? '') === '1') {
                $lap['dilewati']['transaksi']++;

                continue;
            }
            $jenis = trim((string) ($r['jenis_bku'] ?? ''));
            $nominal = (int) ($r['saldo'] ?? 0);
            $tgl = substr(trim((string) ($r['tanggal_transaksi'] ?? '')), 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                $lap['dilewati']['transaksi']++;
                $lap['alasan_lewat'][] = "KAS: tanggal tak valid '{$r['tanggal_transaksi']}' ({$r['uraian']}) — dilewati.";

                continue;
            }

            $keyBaru = trim((string) ($r['id_kas_umum'] ?? ''));
            $legacyKey = 'kas:legacy:'.sha1(implode('|', [
                $tgl, trim((string) ($r['no_bukti'] ?? '')), trim((string) ($r['uraian'] ?? '')),
                trim((string) ($r['kode_rekening'] ?? '')), $jenis, trim((string) ($r['kode_bku'] ?? '')),
                $nominal, $tahun, trim((string) ($r['sumber_dana'] ?? '')),
                trim((string) ($r['volume'] ?? '')),
            ]));
            $arkasKey = $keyBaru !== '' ? 'kas:'.$keyBaru : $legacyKey;

            // Baris non-transaksional / counterpart → lewati + catat.
            if (in_array($jenis, [...self::AKSI_SKIP_SALDO, ...self::AKSI_SKIP_COUNTERPART], true)
                || in_array($jenis, self::AKSI_TERIMA_PAJAK, true)
                || in_array($jenis, self::AKSI_SETOR_PAJAK, true)) {
                continue; // ditangani di imporPajak / memang bukan transaksi
            }
            if (in_array($jenis, self::AKSI_SKIP_LAIN, true)) {
                if ($jenis === 'Bunga Bank' && $nominal > 0) {
                    // jatuh ke bawah sebagai pendapatan
                } else {
                    $lap['dilewati']['transaksi']++;

                    continue;
                }
            }

            if ($nominal <= 0) {
                $lap['dilewati']['transaksi']++;
                $lap['alasan_lewat'][] = "KAS: nominal kosong ({$jenis} — {$r['uraian']}) — dilewati.";

                continue;
            }

            $map = self::petakanJenis($jenis, $r, $nominal);
            if ($map === null) {
                $lap['dilewati']['transaksi']++;
                $lap['alasan_lewat'][] = "KAS: jenis_bku tak dikenal '{$jenis}' ({$r['uraian']}) — dilewati.";

                continue;
            }
            [$type, $metode] = $map;

            $stageId = self::stageUntuk($r, $tgl, $tahun, $stageMap);
            if (BkuTutupBulan::terkunciBulan($sekolah->id, substr($tgl, 0, 7))) {
                $lap['dilewati']['transaksi']++;
                $lap['periode_terkunci'][] =
                    'BKU bulan '.substr($tgl, 0, 7)." tertutup: {$tgl} {$jenis} {$r['uraian']} (".rp($nominal).')';

                continue;
            }
            if ($stageId && TutupBuku::terkunci($sekolah->id, $tahun, $stageId)) {
                $lap['dilewati']['transaksi']++;
                $lap['periode_terkunci'][] = "{$tgl} {$jenis} {$r['uraian']} (".rp($nominal).')';

                continue;
            }
            if ($stageId && ! self::stageTerbuka($stageId)) {
                $lap['dilewati']['transaksi']++;
                $lap['alasan_lewat'][] = "KAS: tahap ditutup Dinas ({$tgl} {$r['uraian']}) — dilewati.";

                continue;
            }

            // Tulis idempoten + migrasi kunci legacy→baru (sekolah yang dulu
            // impor via file lama lalu beralih ke agen). Kembaran digabung.
            $t = self::simpanTransaksi($sekolah->id, $arkasKey, $legacyKey, [
                'tahun' => $tahun, 'type' => $type,
                'no_bukti' => trim((string) ($r['no_bukti'] ?? '')) ?: null,
                'arkas_bku' => trim((string) ($r['no_bukti'] ?? '')) ?: null,
                'arkas_jenis_bku' => $jenis,
                'arkas_nota_id' => trim((string) ($r['id_kas_nota'] ?? '')) ?: null,
                'tanggal' => $tgl,
                'bos_type_id' => self::bosTypeUntuk($r['sumber_dana'] ?? null, $bosMap),
                'stage_id' => $stageId, 'metode_bayar' => $metode,
                'keterangan' => trim((string) ($r['uraian'] ?? '')) ?: $jenis,
                'total' => $nominal, 'created_by' => $userId,
            ], $lap);
            // Nomor BKU: hanya untuk baris baru (impor ulang tak menggeser penomoran).
            if ($t->wasRecentlyCreated && ! $t->no_bku) {
                $t->no_bku = KasService::nextNoBku($sekolah->id, $tgl);
                $t->save();
            }
            $lap[$t->wasRecentlyCreated ? 'dibuat' : 'diperbarui']['transaksi']++;
            $seenTrx[] = $t->arkas_id;

            // Rincian rekening (hanya kode yang dikenal).
            $kode = trim((string) ($r['kode_rekening'] ?? ''));
            if ($kode !== '' && in_array($type, ['belanja', 'pendapatan'], true)) {
                $rid = $rekeningMap[$kode] ?? null;
                if ($rid) {
                    $t->details()->updateOrCreate(
                        ['rekening_id' => $rid],
                        ['uraian' => $t->keterangan, 'nilai' => $nominal,
                            'kode_kegiatan' => $periodeKeg[trim((string) ($r['id_rapbs_periode'] ?? ''))] ?? null,
                            'volume' => (float) ($r['volume'] ?? 0) ?: null]
                    );
                    // Tandai belanja SIPLah (dari flag nota) untuk HPP & rekomendasi revisi.
                    $nb = trim((string) ($r['no_bukti'] ?? ''));
                    if ($type === 'belanja' && $nb !== '' && isset($notaSiplah[$nb])) {
                        $t->details()->update(['sumber' => 'siplah']);
                    }
                } else {
                    self::catatKodeAsing($lap, $kode);
                }
            }

            if ($type === 'belanja') {
                $belanjaIds[$t->arkas_id] = $t->id;
            }
        }

        return $belanjaIds;
    }

    // ==================== Pajak ====================

    protected static function imporPajak($wb, array $kasRows, School $sekolah, int $userId, int $tahun, array $pajakMap, array $stageMap, array $belanjaIds, array &$lap, array &$seenTrx): void
    {
        // Indeks belanja sekolah tahun ini per (tanggal|kode_rekening), total terkecil dulu.
        $belanja = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $sekolah->id)->where('tahun', $tahun)->where('type', 'belanja')
            ->with('details')->get();
        $idx = [];
        foreach ($belanja as $b) {
            foreach ($b->details as $d) {
                $kode = $d->rekening?->kode;
                if ($kode) {
                    $idx[$b->tanggal->format('Y-m-d').'|'.$kode][] = $b;
                }
            }
        }
        foreach ($idx as &$list) {
            usort($list, fn ($a, $b) => $a->total <=> $b->total);
        }
        unset($list);

        // NTPN: rantai no_bukti belanja → Nota.id_kas_nota → Pajak_NTPN.ntpn.
        $ntpn = self::petaNtpn($wb, $tahun);

        // Dua pass: TERIMA dulu semuanya (agar urutan baris di file tak
        // berpengaruh), baru SETOR (butuh pungutan 'belum' sudah ada).
        $pajakRows = [];
        foreach ($kasRows as $r) {
            if ((string) ($r['soft_delete'] ?? '') === '1') {
                continue;
            }
            $jenis = trim((string) ($r['jenis_bku'] ?? ''));
            if (in_array($jenis, self::AKSI_TERIMA_PAJAK, true)) {
                $pajakRows[] = [$r, true];
            } elseif (in_array($jenis, self::AKSI_SETOR_PAJAK, true)) {
                $pajakRows[] = [$r, false];
            }
        }
        usort($pajakRows, fn ($a, $b) => ($b[1] <=> $a[1]));

        foreach ($pajakRows as [$r, $isTerima]) {
            $jenis = trim((string) ($r['jenis_bku'] ?? ''));
            $isSetor = ! $isTerima;
            $nominal = (int) ($r['saldo'] ?? 0);
            if ($nominal <= 0) {
                $lap['dilewati']['pajak']++;

                continue;
            }
            $flagJenis = null;
            foreach (self::PAJAK_MAP as $flag => $kodePajak) {
                if (trim((string) ($r[$flag] ?? '')) === '1') {
                    $flagJenis = $kodePajak;
                    break;
                }
            }
            if (! $flagJenis || ! isset($pajakMap[$flagJenis])) {
                $lap['dilewati']['pajak']++;
                $lap['alasan_lewat'][] = "Pajak: flag tak dikenali ({$r['uraian']}) — dilewati.";

                continue;
            }
            $tgl = substr(trim((string) ($r['tanggal_transaksi'] ?? '')), 0, 10);
            $kodeRek = trim((string) ($r['kode_rekening'] ?? ''));
            $keyBaru = trim((string) ($r['id_kas_umum'] ?? ''));
            $legacyBase = 'legacy:'.sha1(implode('|', [$tgl, $r['uraian'] ?? '', $kodeRek, $jenis, $nominal, $tahun]));
            $base = $keyBaru !== '' ? $keyBaru : $legacyBase;
            $taxKey = ($isTerima ? 'pterima:' : 'psetor:').$base.':'.$flagJenis;
            $legacyTaxKey = ($isTerima ? 'pterima:' : 'psetor:').$legacyBase.':'.$flagJenis;

            // Cari belanja induk (hanya dipakai TERIMA): tanggal sama + kode
            // rekening sama, total >= nominal, terkecil. SETOR tak butuh ini
            // karena tanggal setor boleh beda dari tanggal belanja.
            $induk = null;
            if ($isTerima) {
                foreach ($idx[$tgl.'|'.$kodeRek] ?? [] as $b) {
                    if ($b->total >= $nominal) {
                        $induk = $b;
                        break;
                    }
                }
                // Fallback: tanggal sama, kode apa pun, total >= nominal (PBT kadang beda kode).
                if (! $induk) {
                    $kandidat = [];
                    foreach ($belanja as $b) {
                        if ($b->tanggal->format('Y-m-d') === $tgl && $b->total >= $nominal) {
                            $kandidat[] = $b;
                        }
                    }
                    usort($kandidat, fn ($a, $b) => $a->total <=> $b->total);
                    $induk = $kandidat[0] ?? null;
                }
                if (! $induk) {
                    $lap['dilewati']['pajak']++;
                    $lap['alasan_lewat'][] = "Pajak: {$r['uraian']} (".rp($nominal).") tak menemukan belanja induk tgl {$tgl} — dilewati.";

                    continue;
                }
            }

            if ($isTerima) {
                // firstOrCreate + update parsial: impor ulang TIDAK BOLEH
                // menurunkan status 'sudah' (pajak yang telah disetor) jadi 'belum'.
                // Migrasi kunci legacy→baru bila perlu (gabung kembaran).
                $lama = ($legacyTaxKey !== $taxKey)
                    ? KasTax::where('kas_transaction_id', $induk->id)->where('arkas_id', $legacyTaxKey)->first()
                    : null;
                $tax = KasTax::firstOrCreate(
                    ['kas_transaction_id' => $induk->id, 'arkas_id' => $taxKey],
                    ['rekening_pajak_id' => $pajakMap[$flagJenis],
                        'kode_billing' => trim((string) ($r['no_bukti'] ?? '')) ?: null,
                        'nilai' => $nominal, 'status' => 'belum']
                );
                if ($lama && $lama->id !== $tax->id) {
                    if ($tax->status === 'belum' && $lama->status === 'sudah') {
                        $tax->update(['status' => 'sudah', 'ntpn' => $lama->ntpn ?? $tax->ntpn,
                            'paid_transaction_id' => $lama->paid_transaction_id ?? $tax->paid_transaction_id]);
                    }
                    $lama->delete();
                    $lap['digabung']['transaksi']++;
                }
                if (! $tax->wasRecentlyCreated) {
                    $tax->update([
                        'nilai' => $nominal,
                        'kode_billing' => trim((string) ($r['no_bukti'] ?? '')) ?: $tax->kode_billing,
                    ]);
                }
                $lap[$tax->wasRecentlyCreated ? 'dibuat' : 'diperbarui']['pajak_terima']++;
            } else {
                // Sudah pernah diproses (impor ulang)? Lewati diam-diam,
                // sekalian migrasikan kunci legacy→baru bila bertemu.
                $baruKey = 'kas:'.$base;
                $pernah = KasTransaction::withoutGlobalScope('school')
                    ->where('school_id', $sekolah->id)
                    ->whereIn('arkas_id', [$baruKey, 'kas:'.$legacyBase])->orderBy('id')->get();
                if ($pernah->isNotEmpty()) {
                    $target = $pernah->firstWhere('arkas_id', $baruKey) ?? $pernah->first();
                    foreach ($pernah->where('id', '!=', $target->id) as $dup) {
                        self::gabungKembar($target, $dup);
                        $lap['digabung']['transaksi']++;
                    }
                    if ($target->arkas_id !== $baruKey) {
                        $target->update(['arkas_id' => $baruKey]);
                    }
                    $lap['diperbarui']['pajak_setor']++;
                    $seenTrx[] = $baruKey;

                    continue;
                }
                // Setor boleh beda tanggal dengan belanja/pungutannya: cari
                // pungutan 'belum' sejenis+senilai, prioritaskan tgl ≤ tgl setor.
                $belum = KasTax::whereHas('transaksi', fn ($q) => $q
                    ->where('school_id', $sekolah->id)->where('tahun', $tahun)->where('type', 'belanja'))
                    ->where('rekening_pajak_id', $pajakMap[$flagJenis])
                    ->where('nilai', $nominal)->where('status', 'belum')
                    ->with('transaksi')->get()
                    ->sortByDesc(fn ($t) => $t->transaksi->tanggal->format('Y-m-d') <= $tgl)
                    ->sortBy(fn ($t) => abs(strtotime($tgl) - strtotime($t->transaksi->tanggal->format('Y-m-d'))))
                    ->first();
                if (! $belum) {
                    $lap['dilewati']['pajak']++;
                    $lap['alasan_lewat'][] = "Setor pajak: {$r['uraian']} (".rp($nominal).") tak ada pungutan 'belum' yang cocok — dilewati.";

                    continue;
                }
                $induk = $belum->transaksi;
                $stageId = self::stageUntuk($r, $tgl, $tahun, $stageMap);
                if (BkuTutupBulan::terkunciBulan($sekolah->id, substr($tgl, 0, 7))) {
                    $lap['dilewati']['pajak']++;
                    $lap['periode_terkunci'][] = 'BKU bulan '.substr($tgl, 0, 7)." tertutup: {$tgl} Setor {$r['uraian']} (".rp($nominal).')';

                    continue;
                }
                if ($stageId && TutupBuku::terkunci($sekolah->id, $tahun, $stageId)) {
                    $lap['dilewati']['pajak']++;
                    $lap['periode_terkunci'][] = "{$tgl} Setor {$r['uraian']} (".rp($nominal).')';

                    continue;
                }
                $setor = self::simpanTransaksi($sekolah->id, 'kas:'.$base, 'kas:'.$legacyBase, [
                    'tahun' => $tahun, 'type' => 'setor_pajak',
                    'belanja_transaction_id' => $induk->id,
                    'tanggal' => $tgl, 'metode_bayar' => 'transfer',
                    'keterangan' => 'Setor '.($belum->rekeningPajak?->nama ?? $flagJenis)." (impor ARKAS {$tgl})",
                    'total' => $nominal, 'created_by' => $userId,
                ], $lap);
                if ($setor->wasRecentlyCreated && ! $setor->no_bku) {
                    $setor->no_bku = KasService::nextNoBku($sekolah->id, $tgl);
                    $setor->save();
                }
                $seenTrx[] = $setor->arkas_id;
                $ntpnVal = $ntpn[$induk->no_bukti ?? ''] ?? null;
                $belum->update(['status' => 'sudah', 'ntpn' => $ntpnVal ?? $belum->ntpn,
                    'paid_transaction_id' => $setor->id]);
                $setor->taxes()->updateOrCreate(
                    ['arkas_id' => $taxKey],
                    ['rekening_pajak_id' => $pajakMap[$flagJenis],
                        'kode_billing' => $belum->kode_billing, 'ntpn' => $ntpnVal ?? $belum->ntpn,
                        'nilai' => $nominal, 'status' => 'sudah']
                );
                $lap[$setor->wasRecentlyCreated ? 'dibuat' : 'diperbarui']['pajak_setor']++;
            }
        }
    }

    /**
     * Ekstrak baris KAS transaksional dari file untuk rekonsiliasi
     * (tanpa menulis DB). Kunci legacy & baru ikut dihitung.
     */
    public static function ekstrakKas(string $path, int $tahun): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($path);
        $rows = array_values(array_filter(
            self::sheetAssoc($wb, 'KAS_Umum'),
            fn ($r) => (int) ($r['tahun_anggaran'] ?? 0) === $tahun
                && (string) ($r['soft_delete'] ?? '') !== '1'
        ));
        $out = [];
        foreach ($rows as $r) {
            $jenis = trim((string) ($r['jenis_bku'] ?? ''));
            $tgl = substr(trim((string) ($r['tanggal_transaksi'] ?? '')), 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                continue;
            }
            if (in_array($jenis, [...self::AKSI_SKIP_SALDO, ...self::AKSI_SKIP_COUNTERPART, ...self::AKSI_SKIP_LAIN], true)) {
                continue;
            }
            // Terima pajak menempel sebagai kas_taxes (bukan transaksi) — diskip
            // di sini ( tercakup implisit via transaksi setor pasangannya).
            if (in_array($jenis, self::AKSI_TERIMA_PAJAK, true)) {
                continue;
            }
            $nominal = (int) ($r['saldo'] ?? 0);
            if ($nominal <= 0) {
                continue;
            }
            $keyBaru = trim((string) ($r['id_kas_umum'] ?? ''));
            // Baris setor memakai skema kunci transaksi setor (lihat imporPajak).
            $legacySetor = 'kas:legacy:'.sha1(implode('|', [
                $tgl, trim((string) ($r['uraian'] ?? '')), trim((string) ($r['kode_rekening'] ?? '')),
                $jenis, $nominal, $tahun,
            ]));
            $isSetor = in_array($jenis, self::AKSI_SETOR_PAJAK, true);
            $out[] = [
                'tgl' => $tgl, 'jenis' => $jenis, 'nominal' => $nominal,
                'no_bukti' => trim((string) ($r['no_bukti'] ?? '')),
                'uraian' => trim((string) ($r['uraian'] ?? '')),
                'kode_rekening' => trim((string) ($r['kode_rekening'] ?? '')),
                'key_baru' => $keyBaru !== '' ? 'kas:'.$keyBaru : null,
                'key_legacy' => $isSetor ? $legacySetor : 'kas:legacy:'.sha1(implode('|', [
                    $tgl, trim((string) ($r['no_bukti'] ?? '')), trim((string) ($r['uraian'] ?? '')),
                    trim((string) ($r['kode_rekening'] ?? '')), $jenis, trim((string) ($r['kode_bku'] ?? '')),
                    $nominal, $tahun, trim((string) ($r['sumber_dana'] ?? '')),
                    trim((string) ($r['volume'] ?? '')),
                ])),
            ];
        }

        return $out;
    }

    // ==================== Util ====================

    /**
     * Simpan transaksi idempoten dengan migrasi kunci legacy→baru.
     * Bila kembaran (legacy + baru) sama-sama ada: anak (rincian+pajak)
     * digabung ke baris baru, kembaran lama dihapus, dilaporkan.
     */
    protected static function simpanTransaksi(int $schoolId, string $arkasKey, string $legacyKey, array $isi, array &$lap): KasTransaction
    {
        $cari = fn (string $k) => KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('arkas_id', $k)->first();
        $baru = $cari($arkasKey);
        $lama = ($legacyKey !== $arkasKey) ? $cari($legacyKey) : null;
        if ($baru && $lama && $baru->id !== $lama->id) {
            self::gabungKembar($baru, $lama);
            $lap['digabung']['transaksi']++;
        }
        $t = $baru ?? $lama;
        if ($t) {
            $t->update($isi + ['arkas_id' => $arkasKey]);
            $t->wasRecentlyCreated = false;

            return $t;
        }
        $t = KasTransaction::withoutGlobalScope('school')
            ->create(['school_id' => $schoolId, 'arkas_id' => $arkasKey] + $isi);
        $t->wasRecentlyCreated = true;

        return $t;
    }

    /** Pindahkan rincian + pajak dari kembaran lama ke baris baru, lalu hapus lama. */
    protected static function gabungKembar(KasTransaction $baru, KasTransaction $lama): void
    {
        foreach ($lama->details as $d) {
            $ada = $baru->details()->where('rekening_id', $d->rekening_id)->where('nilai', $d->nilai)->exists();
            if (! $ada) {
                $baru->details()->create([
                    'rekening_id' => $d->rekening_id, 'uraian' => $d->uraian, 'nilai' => $d->nilai,
                ]);
            }
        }
        foreach ($lama->taxes as $p) {
            $sama = $baru->taxes()->where('rekening_pajak_id', $p->rekening_pajak_id)->where('nilai', $p->nilai)->first();
            if ($sama) {
                // Pertahankan status termaju (sudah > belum) beserta NTPN & penaut setor.
                if ($sama->status === 'belum' && $p->status === 'sudah') {
                    $sama->update(['status' => 'sudah', 'ntpn' => $p->ntpn ?? $sama->ntpn,
                        'kode_billing' => $p->kode_billing ?? $sama->kode_billing,
                        'paid_transaction_id' => $p->paid_transaction_id ?? $sama->paid_transaction_id]);
                }
                $p->delete();
            } else {
                $p->update(['kas_transaction_id' => $baru->id]);
            }
        }
        $lama->delete();
    }

    /** jenis_bku → [type SiapBOS, metode]. null = tak dikenal. */
    protected static function petakanJenis(string $jenis, array $r, int $nominal): ?array
    {
        if (in_array($jenis, self::AKSI_PENDAPATAN, true) || $jenis === 'Bunga Bank') {
            return ['pendapatan', 'transfer'];
        }
        if (in_array($jenis, self::AKSI_BELANJA_TUNAI, true)) {
            return ['belanja', 'tunai'];
        }
        if (in_array($jenis, self::AKSI_BELANJA_TRANSFER, true)) {
            return ['belanja', 'transfer'];
        }
        if (in_array($jenis, self::AKSI_AMBIL_SIMPANAN, true)) {
            return ['ambil_simpanan', 'tunai'];
        }

        return null;
    }

    /** stage: parse "Tahap N" dari uraian, fallback bulan → urutan (5 tahap/tahun). */
    protected static function stageUntuk(array $r, string $tgl, int $tahun, array $stageMap): ?int
    {
        $teks = ($r['uraian'] ?? '').' '.($r['no_bukti'] ?? '');
        if (preg_match('/tahap\s*(\d)/i', $teks, $m)) {
            $u = (int) $m[1];
            if (isset($stageMap[$u])) {
                return $stageMap[$u];
            }
        }
        $bulan = (int) substr($tgl, 5, 2);
        $u = min(5, max(1, (int) ceil($bulan * 5 / 12)));

        return $stageMap[$u] ?? null;
    }

    protected static function stageTerbuka(int $stageId): bool
    {
        return (bool) Stage::where('id', $stageId)->where('is_open', true)->exists();
    }

    protected static function bosTypeUntuk(?string $sumber, array $bosMap): ?int
    {
        $s = strtolower((string) $sumber);
        if (str_contains($s, 'afirmasi')) {
            return $bosMap['afirmasi'] ?? null;
        }
        if (str_contains($s, 'reguler')) {
            return $bosMap['reguler'] ?? null;
        }
        if (str_contains($s, 'kinerja')) {
            return $bosMap['kinerja'] ?? null;
        }
        if (str_contains($s, 'bosda') || str_contains($s, 'daerah')) {
            return $bosMap['bosda'] ?? null;
        }
        if (str_contains($s, 'silpa')) {
            return $bosMap['silpa'] ?? null;
        }
        if (str_contains($s, 'lainnya') || str_contains($s, 'banpem')) {
            return $bosMap['lainnya'] ?? null;
        }

        return null;
    }

    /** NTPN: no_bukti belanja → Nota aktif tahun tsb → Pajak_NTPN aktif. */
    protected static function petaNtpn($wb, int $tahun): array
    {
        try {
            $nota = self::sheetAssoc($wb, 'Nota');
            $pajak = self::sheetAssoc($wb, 'Pajak_NTPN');
        } catch (\RuntimeException) {
            return [];
        }
        $notaIds = [];
        foreach ($nota as $n) {
            if (substr((string) ($n['tanggal_nota'] ?? ''), 0, 4) !== (string) $tahun) {
                continue;
            }
            if ((string) ($n['soft_delete'] ?? '') === '1') {
                continue;
            }
            $nb = trim((string) ($n['no_bukti'] ?? ''));
            if ($nb !== '') {
                $notaIds[$n['id_kas_nota']][] = $nb;
            }
        }
        $byNota = [];
        foreach ($pajak as $p) {
            if ((string) ($p['soft_delete'] ?? '') === '1') {
                continue;
            }
            $v = trim((string) ($p['ntpn'] ?? ''));
            if ($v !== '' && isset($notaIds[$p['id_kas_nota']])) {
                foreach ($notaIds[$p['id_kas_nota']] as $nb) {
                    $byNota[$nb] ??= $v;
                }
            }
        }

        return $byNota;
    }

    /** Baris SiapBOS ber-arkas_id yang tak ada di file → daftar selisih (tanpa hapus). */
    protected static function selisihHapus(int $schoolId, int $tahun, array $seenTrx, array &$lap): void
    {
        $trx = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $schoolId)->where('tahun', $tahun)
            ->whereNotNull('arkas_id')
            ->when($seenTrx !== [], fn ($q) => $q->whereNotIn('arkas_id', $seenTrx))
            ->orderBy('tanggal')->limit(50)
            ->get(['no_bku', 'tanggal', 'keterangan', 'total']);
        foreach ($trx as $t) {
            $lap['selisih_hapus'][] = '#'.$t->no_bku.' '.$t->tanggal->format('d/m/Y')
                .' '.mb_substr($t->keterangan, 0, 50).' ('.rp($t->total).')';
        }
    }

    protected static function catatKodeAsing(array &$lap, string $kode): void
    {
        if ($kode === '') {
            return;
        }
        if (! in_array($kode, $lap['rekening_tak_dikenal'], true)) {
            $lap['rekening_tak_dikenal'][] = $kode;
        }
    }

    protected static function ringkasan(array $lap): string
    {
        return 'Impor ARKAS NPSN '.$lap['npsn'].' tahun '.$lap['tahun']
            .': transaksi +'.$lap['dibuat']['transaksi'].'/~'.$lap['diperbarui']['transaksi']
            .', RKAS +'.$lap['dibuat']['rkas'].'/~'.$lap['diperbarui']['rkas']
            .', terima pajak +'.$lap['dibuat']['pajak_terima']
            .', setor pajak +'.$lap['dibuat']['pajak_setor']
            .', penjab +'.$lap['dibuat']['penjab']
            .', pagu +'.$lap['dibuat']['alokasi']
            .', digabung '.$lap['digabung']['transaksi']
            .', dilewati '.array_sum($lap['dilewati']).' baris.';
    }

    /**
     * Sheet → array assoc. Lewati baris navigasi (baris 1); header = baris
     * pertama yang memuat salah satu kolom kunci yang diharapkan.
     */
    protected static function sheetAssoc($wb, string $name, array $wajib = []): array
    {
        $sheet = $wb->getSheetByName($name);
        if (! $sheet) {
            throw new \RuntimeException("Sheet {$name} tidak ditemukan di file.");
        }
        $rows = $sheet->toArray(null, true, true, false);
        // Deteksi header: baris bernilai "terbaik" di 15 baris teratas. Seri →
        // baris yang lebih bawah menang (file ARKAS baru memuat header duplikat).
        $kunci = ['npsn', 'tanggal_transaksi', 'tanggal_aktivasi', 'id_rapbs', 'id_ref_kode',
            'id_kode', 'id_kas_nota', 'ntpn', 'kode_rekening', 'no_bukti', 'tahun_anggaran',
            'tahun', 'tahap', 'nilai_salur_bersih', 'ks', 'bendahara', 'ptk_id'];
        $headerIdx = null;
        $bestScore = -1;
        foreach (array_slice($rows, 0, 15, true) as $i => $row) {
            $norm = array_map(fn ($c) => strtolower(trim((string) $c)), $row);
            $score = count(array_intersect($norm, $kunci));
            if ($wajib) {
                $score += 2 * count(array_intersect($wajib, $norm));
            }
            if ($score > 0 && $score >= $bestScore) {
                $bestScore = $score;
                $headerIdx = $i;
            }
        }
        if ($headerIdx === null) {
            throw new \RuntimeException("Header sheet {$name} tak dikenali.");
        }
        $header = array_map(fn ($c) => trim((string) $c), $rows[$headerIdx]);
        $out = [];
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $assoc = [];
            foreach ($header as $c => $nama) {
                if ($nama !== '') {
                    $assoc[$nama] = $rows[$i][$c] ?? null;
                }
            }
            if (count(array_filter($assoc, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $out[] = $assoc;
        }

        return $out;
    }
}
