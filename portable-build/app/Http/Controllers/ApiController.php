<?php

namespace App\Http\Controllers;

use App\Exceptions\ArkasRunAktifException;
use App\Models\ActivityLog;
use App\Models\ArkasImportRun;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Komponen;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use App\Notifications\InfoNotif;
use App\Services\AntreanArkasService;
use App\Services\KasService;
use App\Services\RingkasanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * API baca-saja v1 SiapBOSP.
 * Semua endpoint (kecuali /ping) butuh: Authorization: Bearer <api_token>.
 */
class ApiController extends Controller
{
    private function tahun(): int
    {
        return (int) request('tahun', now()->year);
    }

    public function ping()
    {
        return response()->json([
            'ok' => true, 'app' => 'SiapBOSP API', 'versi' => 'v1',
            'waktu' => now()->toIso8601String(), 'dokumentasi' => 'Authorization: Bearer <token>',
        ]);
    }

    public function profil(Request $r)
    {
        $u = $r->user();
        $data = ['ok' => true, 'pengguna' => ['nama' => $u->name, 'email' => $u->email, 'peran' => $u->role]];
        if ($u->school_id) {
            $s = $u->school;
            $data['sekolah'] = [
                'id' => $s->id, 'npsn' => $s->npsn, 'nama' => $s->nama, 'jenjang' => $s->jenjang,
                'kecamatan' => $s->kecamatan, 'kab_kota' => $s->kab_kota, 'provinsi' => $s->provinsi,
            ];
        }

        return response()->json($data);
    }

    public function tahap()
    {
        $tahun = $this->tahun();
        $terkunci = auth()->user()->school_id
            ? TutupBuku::where('tahun', $tahun)->pluck('stage_id')->toArray() : [];
        $stages = Stage::where('tahun', $tahun)->orderBy('urutan')->get()->map(fn ($s) => [
            'id' => $s->id, 'nama' => $s->nama, 'urutan' => $s->urutan,
            'dibuka_dinas' => (bool) $s->is_open,
            'tutup_buku_sekolah' => in_array($s->id, $terkunci),
        ]);

        return response()->json(['ok' => true, 'tahun' => $tahun, 'tahap' => $stages]);
    }

    public function saldo()
    {
        $u = auth()->user();
        if (! $u->school_id) {
            return response()->json(['ok' => false, 'pesan' => 'Endpoint ini untuk akun sekolah.'], 403);
        }
        $tahun = $this->tahun();
        $sd = KasService::saldo($u->school_id, $tahun);
        $ringkas = RingkasanService::sekolah($u->school, $tahun);

        return response()->json(['ok' => true, 'tahun' => $tahun, 'sekolah' => $u->school->nama,
            'pagu' => $ringkas->pagu, 'dana_cair' => $ringkas->pendapatan, 'belanja' => $ringkas->belanja,
            'serapan_persen' => $ringkas->serapan, 'pajak_belum_disetor' => $ringkas->pajakBelum,
            'saldo' => ['tunai' => $sd['tunai'], 'bank' => $sd['bank'], 'total' => $sd['total']]]);
    }

    public function transaksi(Request $r)
    {
        $u = auth()->user();
        if (! $u->school_id) {
            return response()->json(['ok' => false, 'pesan' => 'Endpoint ini untuk akun sekolah.'], 403);
        }
        $q = KasTransaction::with(['details.rekening', 'taxes.rekeningPajak'])
            ->where('tahun', $this->tahun())->orderBy('tanggal')->orderBy('no_bku');
        if ($r->filled('stage')) {
            $q->where('stage_id', (int) $r->stage);
        }
        if ($r->filled('jenis')) {
            $q->where('type', $r->jenis);
        }
        $data = $q->paginate(min(100, max(1, (int) $r->query('per_page', 25))));
        $data->getCollection()->transform(fn ($t) => [
            'id' => $t->id, 'no_bku' => $t->no_bku, 'tanggal' => $t->tanggal->format('Y-m-d'),
            'jenis' => $t->type, 'uraian' => $t->keterangan, 'metode' => $t->metode_bayar, 'total' => (int) $t->total,
            'stage_id' => $t->stage_id,
            'rincian' => $t->details->map(fn ($d) => [
                'rekening' => $d->rekening->kode ?? null, 'nama_rekening' => $d->rekening->nama ?? null,
                'uraian' => $d->uraian, 'nilai' => (int) $d->nilai])->values(),
            'pajak' => $t->taxes->map(fn ($p) => [
                'jenis' => $p->rekeningPajak->nama ?? null, 'nilai' => (int) $p->nilai,
                'status' => $p->status, 'kode_billing' => $p->kode_billing, 'ntpn' => $p->ntpn])->values(),
        ]);

        return response()->json(['ok' => true, 'transaksi' => $data]);
    }

    /** Agen satu-klik: daftarkan Excel ARKAS untuk diproses di antrean. */
    public function arkasPush(Request $request, AntreanArkasService $antrean)
    {
        $user = $request->user();
        if (! $user->school_id) {
            return response()->json(['ok' => false, 'pesan' => 'Endpoint ini untuk akun sekolah.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'berkas' => 'required|file|mimes:xlsx|max:20480',
            'tahun' => 'nullable|integer|min:2020|max:2030',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Berkas Excel ARKAS (.xlsx, maks 20 MB) wajib diunggah. '
                    .implode(' ', $validator->errors()->all()),
            ], 422);
        }

        $data = $validator->validated();
        try {
            $run = $antrean->daftarkan(
                $data['berkas'],
                $user,
                ArkasImportRun::JENIS_IMPOR,
                isset($data['tahun']) ? (int) $data['tahun'] : (int) now()->year,
                'Agen ARKAS via API'
            );
        } catch (ArkasRunAktifException $exception) {
            return response()->json(['ok' => false, 'pesan' => $exception->getMessage()], 409);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['ok' => false, 'pesan' => 'Antrean ARKAS tidak tersedia. Coba lagi nanti.'], 503);
        }

        return response()->json([
            'ok' => true,
            'status' => $run->status,
            'import_id' => $run->id,
            'tahun' => $run->tahun,
            'polling_interval_seconds' => 3,
        ], 202);
    }

    /** Status/hasil impor API hanya dapat dibaca akun dan sekolah pemilik run. */
    public function arkasImportStatus(Request $request, ArkasImportRun $impor)
    {
        abort_unless(
            $impor->user_id === $request->user()->id
                && $impor->school_id === $request->user()->school_id,
            404
        );

        if ($impor->status === 'completed') {
            return response()->json([
                'ok' => true,
                'status' => 'completed',
                'import_id' => $impor->id,
                'laporan' => $impor->result,
            ]);
        }
        if ($impor->status === 'failed') {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'import_id' => $impor->id,
                'pesan' => $impor->error,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'status' => $impor->status,
            'import_id' => $impor->id,
            'tahun' => $impor->tahun,
            'polling_interval_seconds' => 3,
        ], 202);
    }

    public function dinasSekolah()
    {
        $tahun = $this->tahun();
        $rows = School::orderBy('nama')->get()->map(fn ($s) => [
            'id' => $s->id, 'npsn' => $s->npsn, 'nama' => $s->nama, 'jenjang' => $s->jenjang,
            'kecamatan' => $s->kecamatan, 'aktif' => (bool) $s->is_active,
        ]);

        return response()->json(['ok' => true, 'tahun' => $tahun, 'jumlah' => $rows->count(), 'sekolah' => $rows->values()]);
    }

    public function dinasKonsolidasi()
    {
        $tahun = $this->tahun();
        $rows = School::orderBy('nama')->get()->map(function ($s) use ($tahun) {
            $s = RingkasanService::sekolah($s, $tahun);

            return ['id' => $s->id, 'npsn' => $s->npsn, 'nama' => $s->nama, 'pagu' => $s->pagu,
                'dana_cair' => $s->pendapatan, 'belanja' => $s->belanja, 'serapan_persen' => $s->serapan,
                'pajak_belum_disetor' => $s->pajakBelum, 'transaksi_terakhir' => $s->last, 'tak_aktif' => $s->notAktif];
        });

        return response()->json(['ok' => true, 'tahun' => $tahun,
            'total' => ['pagu' => $rows->sum('pagu'), 'dana_cair' => $rows->sum('dana_cair'),
                'belanja' => $rows->sum('belanja'), 'pajak_belum_disetor' => $rows->sum('pajak_belum_disetor')],
            'sekolah' => $rows->values()]);
    }

    /**
     * Sync lokal → pusat (Tahap C, desain: docs/SINKRON-PUSAT.md).
     * Idempoten per (school_id, kunci); hormati tutup buku; tanpa hapus.
     */
    public function syncPush(Request $r)
    {
        $u = $r->user();
        if (! $u->school_id) {
            return response()->json(['ok' => false, 'pesan' => 'Endpoint ini untuk akun sekolah.'], 403);
        }
        $v = Validator::make($r->all(), [
            'tahun' => 'required|integer|min:2020|max:2030',
            'perangkat' => 'nullable|string|max:64',
            'data.transaksi' => 'sometimes|array|max:500',
            'data.rkas' => 'sometimes|array',
            'data.rkas.details' => 'sometimes|array|max:500',
            'data.tandatangan' => 'sometimes|array|max:20',
            'data.tutup' => 'sometimes|array|max:12',
            'data.lpj_kumpulkan' => 'sometimes|array|max:12',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'pesan' => $v->errors()->first()], 422);
        }
        $adaIsi = collect(['transaksi', 'rkas', 'tandatangan', 'tutup', 'lpj_kumpulkan'])
            ->contains(fn ($k) => ! empty($r->input("data.{$k}")));
        if (! $adaIsi) {
            return response()->json(['ok' => false, 'pesan' => 'Tidak ada data untuk disinkronkan.'], 422);
        }
        $tahun = (int) $r->tahun;
        $sid = $u->school_id;
        $rekMap = Rekening::pluck('id', 'kode')->all();
        $pajakMap = RekeningPajak::pluck('id', 'kode')->all();
        $bosMap = BosType::pluck('id', 'kode')->all();
        $stageMap = Stage::where('tahun', $tahun)->pluck('id', 'urutan')->all();
        $hasil = ['diterima' => 0, 'diperbarui' => 0, 'ditolak' => [], 'peringatan' => []];

        DB::transaction(function () use ($r, $tahun, $sid, $u, $rekMap, $pajakMap, $bosMap, $stageMap, &$hasil) {
            foreach ($r->input('data.transaksi', []) as $i => $t) {
                $kunci = trim((string) ($t['kunci'] ?? ''));
                if ($kunci === '' || strlen($kunci) > 64) {
                    $hasil['ditolak'][] = ['kunci' => $kunci ?: "#$i", 'alasan' => 'kunci kosong/melebihi 64 karakter'];

                    continue;
                }
                $type = $t['type'] ?? null;
                if (! in_array($type, ['pendapatan', 'ambil_simpanan', 'mutasi_tunai_ke_bank', 'belanja', 'setor_pajak', 'setor_utang_pajak'], true)) {
                    $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "type tak dikenal: {$type}"];

                    continue;
                }
                $tgl = substr(trim((string) ($t['tanggal'] ?? '')), 0, 10);
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) || (int) substr($tgl, 0, 4) !== $tahun) {
                    $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "tanggal tak valid/bukan tahun {$tahun}"];

                    continue;
                }
                $total = (int) ($t['total'] ?? -1);
                if ($total < 0) {
                    $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => 'total negatif'];

                    continue;
                }
                $metode = $t['metode'] ?? 'tunai';
                if (! in_array($metode, ['tunai', 'transfer'], true)) {
                    $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "metode tak dikenal: {$metode}"];

                    continue;
                }
                $stageId = null;
                if (isset($t['stage']) && $t['stage'] !== null && $t['stage'] !== '') {
                    $stageId = $stageMap[(int) $t['stage']] ?? null;
                    if (! $stageId) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "tahap tak dikenal: {$t['stage']}"];

                        continue;
                    }
                    if (TutupBuku::terkunci($sid, $tahun, $stageId)) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => 'periode sudah tutup buku'];

                        continue;
                    }
                    if (! Stage::where('id', $stageId)->where('is_open', true)->exists()) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => 'tahap ditutup Dinas'];

                        continue;
                    }
                }
                $bosId = null;
                if (! empty($t['bos'])) {
                    $bosId = $bosMap[strtolower(trim((string) $t['bos']))] ?? null;
                    if (! $bosId) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "jenis BOS tak dikenal: {$t['bos']}"];

                        continue;
                    }
                }
                $rincian = is_array($t['rincian'] ?? null) ? $t['rincian'] : [];
                $baris = [];
                $gagal = false;
                foreach ($rincian as $det) {
                    $kode = trim((string) ($det['rekening'] ?? ''));
                    if (! isset($rekMap[$kode])) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "rekening tak dikenal: {$kode}"];

                        $gagal = true;
                        break;
                    }
                    $nilai = (int) ($det['nilai'] ?? -1);
                    if ($nilai < 0) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "nilai rincian negatif: {$kode}"];

                        $gagal = true;
                        break;
                    }
                    $baris[] = ['rekening_id' => $rekMap[$kode], 'uraian' => trim((string) ($det['uraian'] ?? '')) ?: '-', 'nilai' => $nilai];
                }
                if ($gagal) {
                    continue;
                }
                $pajaks = is_array($t['pajak'] ?? null) ? $t['pajak'] : [];
                $barisPajak = [];
                foreach ($pajaks as $p) {
                    $kode = trim((string) ($p['jenis'] ?? ''));
                    if (! isset($pajakMap[$kode])) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "pajak tak dikenal: {$kode}"];

                        $gagal = true;
                        break;
                    }
                    $nilai = (int) ($p['nilai'] ?? -1);
                    if ($nilai <= 0) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "nilai pajak tak valid: {$kode}"];

                        $gagal = true;
                        break;
                    }
                    $barisPajak[] = ['rekening_pajak_id' => $pajakMap[$kode], 'nilai' => $nilai,
                        'kode_billing' => trim((string) ($p['kode_billing'] ?? '')) ?: null,
                        'ntpn' => trim((string) ($p['ntpn'] ?? '')) ?: null];
                }
                if ($gagal) {
                    continue;
                }

                $trx = KasTransaction::withoutGlobalScope('school')
                    ->where('school_id', $sid)->where('arkas_id', $kunci)->first();
                $isi = ['tahun' => $tahun, 'type' => $type, 'tanggal' => $tgl,
                    'bos_type_id' => $bosId, 'stage_id' => $stageId, 'metode_bayar' => $metode,
                    'keterangan' => trim((string) ($t['keterangan'] ?? '')) ?: $type,
                    'total' => $total, 'created_by' => $u->id,
                    'no_bukti' => trim((string) ($t['no_bukti'] ?? '')) ?: null];
                if ($trx) {
                    // Last-write-wins; no_bku pusat dipertahankan (tidak ikut payload).
                    $trx->update($isi);
                    $hasil['diperbarui']++;
                } else {
                    $trx = KasTransaction::withoutGlobalScope('school')
                        ->create(['school_id' => $sid, 'arkas_id' => $kunci] + $isi);
                    $trx->no_bku = KasService::nextNoBku($sid, $tgl);
                    $trx->save();
                    $trx->wasRecentlyCreated = true;
                    $hasil['diterima']++;
                }
                $trx->details()->delete();
                foreach ($baris as $b) {
                    $trx->details()->create($b);
                }
                $kirimJenis = array_column($barisPajak, 'rekening_pajak_id');
                foreach ($trx->taxes()->where('status', 'belum')->whereNotIn('rekening_pajak_id', $kirimJenis ?: [0])->get() as $lama) {
                    $lama->delete();
                }
                foreach ($barisPajak as $b) {
                    $ada = $trx->taxes()->where('rekening_pajak_id', $b['rekening_pajak_id'])->first();
                    if ($ada) {
                        if ($ada->status === 'sudah') {
                            $hasil['peringatan'][] = "{$kunci}: pajak sudah disetor — nilai dipertahankan.";
                        } else {
                            $ada->update(['nilai' => $b['nilai'], 'kode_billing' => $b['kode_billing'] ?? $ada->kode_billing,
                                'ntpn' => $b['ntpn'] ?? $ada->ntpn]);
                        }
                    } else {
                        $trx->taxes()->create($b + ['status' => 'belum']);
                    }
                }
                // Lampiran (C3d): base64 ≤5 MB/berkas, pdf/jpg/png; dedup nama+ukuran.
                foreach (array_slice(is_array($t['lampiran'] ?? null) ? $t['lampiran'] : [], 0, 3) as $lamp) {
                    $nama = trim((string) ($lamp['nama'] ?? ''));
                    $b64 = (string) ($lamp['base64'] ?? '');
                    $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
                    $bin = $b64 !== '' ? base64_decode($b64, true) : false;
                    if ($nama === '' || $bin === false) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => 'lampiran rusak: nama/base64 tak valid'];

                        continue;
                    }
                    if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) || strlen($bin) > 5242880) {
                        $hasil['ditolak'][] = ['kunci' => $kunci, 'alasan' => "lampiran ditolak: {$nama} (tipe/ukuran)"];

                        continue;
                    }
                    $aman = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($nama));
                    if ($trx->attachments()->where('nama', $aman)->where('ukuran', strlen($bin))->exists()) {
                        continue;
                    }
                    $path = "lampiran/{$sid}/{$trx->id}_{$aman}";
                    Storage::put($path, $bin);
                    $trx->attachments()->create(['path' => $path, 'nama' => $aman, 'ukuran' => strlen($bin)]);
                }
            }
            $hasil['rkas'] = $this->syncRkas($r, $tahun, $sid, $u, $rekMap, $hasil);
            $hasil['tandatangan'] = $this->syncTandatangan($r, $sid);
            $hasil['tutup'] = $this->syncTutup($r, $tahun, $sid, $u);
            $hasil['lpj'] = $this->syncLpj($r, $tahun, $sid, $u);
            ActivityLog::create(['school_id' => $sid, 'user_id' => $u->id, 'user_name' => $u->name,
                'aksi' => 'sync_push',
                'keterangan' => 'Sync '.($r->perangkat ?? 'perangkat').": trx +{$hasil['diterima']}/~{$hasil['diperbarui']}"
                    .", rkas +{$hasil['rkas']['dibuat']}/~{$hasil['rkas']['diperbarui']}"
                    .", ttd +{$hasil['tandatangan']}, tutup ".count($hasil['tutup']['ditutup'])
                    .', lpj '.count($hasil['lpj']['dikirim']).', ditolak '.count($hasil['ditolak'])]);
        });

        return response()->json(['ok' => true, 'tahun' => $tahun] + $hasil);
    }

    /** RKAS via sync (C3a): hanya ke header DRAFT tahun berjalan. */
    protected function syncRkas(Request $r, int $tahun, int $sid, $u, array $rekMap, array &$hasil): array
    {
        $out = ['dibuat' => 0, 'diperbarui' => 0, 'ditolak' => []];
        $paket = $r->input('data.rkas');
        if (! is_array($paket) || empty($paket['details'])) {
            return $out;
        }
        $header = RkasHeader::where('school_id', $sid)->where('tahun', $tahun)
            ->where('status', 'draft')->orderByDesc('revisi_ke')->first();
        if (! $header) {
            if (RkasHeader::where('school_id', $sid)->where('tahun', $tahun)->exists()) {
                $out['ditolak'][] = "RKAS {$tahun} sudah disetujui/dikunci — revisi lewat web.";

                return $out;
            }
            $header = RkasHeader::create(['school_id' => $sid, 'tahun' => $tahun, 'revisi_ke' => 1,
                'status' => 'draft', 'no_rkas' => trim((string) ($paket['no_rkas'] ?? '')) ?: null,
                'catatan' => trim((string) ($paket['catatan'] ?? '')) ?: 'Sync lokal '.now()->format('d/m/Y')]);
        }
        foreach ($paket['details'] as $i => $d) {
            $kunci = trim((string) ($d['kunci'] ?? ''));
            $kode = trim((string) ($d['rekening'] ?? ''));
            if ($kunci === '' || ! isset($rekMap[$kode])) {
                $out['ditolak'][] = ($kunci ?: "#$i").': kunci/rekening tak valid ('.$kode.')';

                continue;
            }
            $banyak = max(1, (int) ($d['banyak'] ?? 1));
            $harga = max(0, (int) ($d['harga'] ?? 0));
            $jumlah = (int) ($d['jumlah'] ?? 0) ?: $banyak * $harga;
            $det = RkasDetail::updateOrCreate(
                ['rkas_header_id' => $header->id, 'arkas_id' => $kunci],
                ['rekening_id' => $rekMap[$kode], 'uraian' => trim((string) ($d['uraian'] ?? '')) ?: 'Item sync',
                    'satuan' => trim((string) ($d['satuan'] ?? '')) ?: 'paket',
                    'banyak' => $banyak, 'harga_satuan' => $harga, 'jumlah' => $jumlah,
                    'sumber_dana' => trim((string) ($d['sumber'] ?? '')) ?: 'BOS Reguler',
                    'kode_kegiatan' => trim((string) ($d['kode_kegiatan'] ?? '')) ?: null]
            );
            $out[$det->wasRecentlyCreated ? 'dibuat' : 'diperbarui']++;
        }

        return $out;
    }

    /** Penandatangan via sync (C3b): cocok (nama+jabatan), lengkapi NIP kosong. */
    protected function syncTandatangan(Request $r, int $sid): int
    {
        $n = 0;
        foreach ($r->input('data.tandatangan', []) as $p) {
            $nama = trim((string) ($p['nama'] ?? ''));
            $jab = trim((string) ($p['jabatan'] ?? ''));
            if ($nama === '' || $jab === '') {
                continue;
            }
            $ada = Penandatangan::where('school_id', $sid)->where('nama', $nama)->where('jabatan', $jab)->first();
            if (! $ada) {
                Penandatangan::create(['school_id' => $sid, 'nama' => $nama, 'jabatan' => $jab,
                    'nip' => trim((string) ($p['nip'] ?? '')) ?: null,
                    'pangkat' => trim((string) ($p['pangkat'] ?? '')) ?: null]);
                $n++;
            } elseif (! $ada->nip && trim((string) ($p['nip'] ?? '')) !== '') {
                $ada->update(['nip' => trim((string) $p['nip'])]);
            }
        }

        return $n;
    }

    /** Tutup buku via sync (C3c): aksi idempoten, sama seperti tombol web. */
    protected function syncTutup(Request $r, int $tahun, int $sid, $u): array
    {
        $out = ['ditutup' => [], 'ditolak' => []];
        $map = Stage::where('tahun', $tahun)->pluck('id', 'urutan')->all();
        foreach ($r->input('data.tutup', []) as $ord) {
            $stageId = $map[(int) $ord] ?? null;
            if (! $stageId) {
                $out['ditolak'][] = "tahap tak dikenal: {$ord}";

                continue;
            }
            TutupBuku::firstOrCreate(['school_id' => $sid, 'tahun' => $tahun, 'stage_id' => $stageId],
                ['ditutup_oleh' => $u->name.' (sync)']);
            $out['ditutup'][] = (int) $ord;
        }

        return $out;
    }

    /** Kirim LPJ via sync (C3c): wajib tutup buku; dilewati bila sudah dikirim/diterima. */
    protected function syncLpj(Request $r, int $tahun, int $sid, $u): array
    {
        $out = ['dikirim' => [], 'dilewati' => []];
        $map = Stage::where('tahun', $tahun)->pluck('id', 'urutan')->all();
        foreach ($r->input('data.lpj_kumpulkan', []) as $ord) {
            $stageId = $map[(int) $ord] ?? null;
            if (! $stageId) {
                $out['dilewati'][] = "tahap tak dikenal: {$ord}";

                continue;
            }
            if (! TutupBuku::terkunci($sid, $tahun, $stageId)) {
                $out['dilewati'][] = "tahap {$ord}: belum tutup buku";

                continue;
            }
            $last = LpjSubmission::terakhir($sid, $tahun, $stageId);
            if ($last && in_array($last->status, ['dikirim', 'diterima'], true)) {
                $out['dilewati'][] = "tahap {$ord}: sudah {$last->status}";

                continue;
            }
            LpjSubmission::create(['school_id' => $sid, 'stage_id' => $stageId, 'tahun' => $tahun,
                'status' => 'dikirim', 'dikirim_oleh' => $u->name.' (sync)', 'catatan_dinas' => null]);
            $out['dikirim'][] = (int) $ord;
            User::whereIn('role', ['dinas', 'superadmin'])->get()->each(fn ($d) => $d->notify(
                new InfoNotif('📨', 'LPJ masuk (sync): '.$u->school->nama,
                    "Tahap {$ord} {$tahun} menunggu tinjauan Anda.", route('dinas.lpj'))));
        }

        return $out;
    }

    /** Snapshot master pusat → lokal (Tahap C). */
    public function syncReferensi(Request $r)
    {
        $u = $r->user();
        $tahun = (int) ($r->query('tahun', now()->year));
        $q = ['tahun' => $tahun,
            'versi_master' => Rekening::count().'/'.Rekening::max('updated_at'),
            'rekenings' => Rekening::orderBy('kode')->get(['kode', 'nama', 'tipe'])->values(),
            'pajaks' => RekeningPajak::orderBy('kode')->get(['kode', 'nama'])->values(),
            'komponens' => Komponen::orderBy('kode')->get(['kode', 'nama'])->values(),
            'stages' => Stage::where('tahun', $tahun)->orderBy('urutan')->get(['nama', 'urutan', 'is_open'])->values(),
        ];
        if ($u->school_id) {
            $q['tutup_buku'] = TutupBuku::where('tahun', $tahun)->pluck('stage_id')->all();
        }

        return response()->json(['ok' => true] + $q);
    }
}
