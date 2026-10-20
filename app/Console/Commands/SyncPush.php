<?php

namespace App\Console\Commands;

use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\TutupBuku;
use App\Support\KonfigLokal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Dorong transaksi lokal → website pusat (Tahap C2, docs/SINKRON-PUSAT.md).
 * Identitas dibaca dari launcher-windows/akun-sekolah.conf (dibuat saat unduh per akun).
 * Idempoten: server updateOrCreate per (school, kunci=arkas_id).
 */
class SyncPush extends Command
{
    protected $signature = 'siapbos:sync-push {--tahun= : Tahun anggaran (default: tahun berjalan)} {--kering : Tampilkan payload tanpa mengirim}';

    protected $description = 'Sinkronisasi transaksi lokal ke website SiapBOSP pusat';

    public function handle(): int
    {
        $conf = $this->bacaConf();
        if (! $conf) {
            $this->error('akun-sekolah.conf tidak ditemukan. Unduh aplikasi dari website pusat (per akun sekolah).');

            return 1;
        }
        foreach (['PUSAT_URL', 'TOKEN', 'NPSN'] as $wajib) {
            if (empty($conf[$wajib])) {
                $this->error("{$wajib} kosong di akun-sekolah.conf.");

                return 1;
            }
        }
        $tahun = (int) ($this->option('tahun') ?: now()->year);
        $lokal = School::orderBy('id')->first();
        if (! $lokal || (string) $lokal->npsn !== (string) $conf['NPSN']) {
            $this->error('NPSN lokal ('.($lokal->npsn ?? '-').') beda dengan akun unduhan ('.$conf['NPSN'].'). Pakai paket milik sekolah ini.');

            return 1;
        }
        $terakhir = DB::table('sync_states')->where('kunci', 'terakhir_dorong')->value('nilai');
        $query = KasTransaction::with(['details.rekening', 'taxes.rekeningPajak', 'bosType', 'stage', 'attachments'])
            ->where('tahun', $tahun)->orderBy('updated_at')->limit(500);
        if ($terakhir) {
            $query->where(function ($q) use ($terakhir) {
                $q->where('updated_at', '>', $terakhir)
                    ->orWhereHas('attachments', fn ($a) => $a->where('updated_at', '>', $terakhir));
            });
        }
        $rows = $query->get();
        $paket = ['tahun' => $tahun, 'perangkat' => gethostname() ?: 'laptop', 'data' => []];
        if ($rows->isNotEmpty()) {
            $paket['data']['transaksi'] = $rows->map(fn ($t) => [
                'kunci' => $t->arkas_id ?: 'lokal:'.$t->id,
                'type' => $t->type, 'tanggal' => $t->tanggal->format('Y-m-d'),
                'stage' => $t->stage?->urutan, 'metode' => $t->metode_bayar,
                'keterangan' => $t->keterangan, 'total' => (int) $t->total,
                'bos' => $t->bosType?->kode, 'no_bukti' => $t->no_bukti,
                'rincian' => $t->details->map(fn ($d) => [
                    'rekening' => $d->rekening?->kode, 'uraian' => $d->uraian, 'nilai' => (int) $d->nilai])->values()->all(),
                'pajak' => $t->taxes->map(fn ($p) => [
                    'jenis' => $p->rekeningPajak?->kode, 'nilai' => (int) $p->nilai,
                    'kode_billing' => $p->kode_billing, 'ntpn' => $p->ntpn])->values()->all(),
                'lampiran' => $t->attachments->filter(fn ($a) => $a->ukuran <= 5242880 && is_file(storage_path('app/'.$a->path)))
                    ->take(3)->map(fn ($a) => [
                        'nama' => $a->nama, 'base64' => base64_encode(file_get_contents(storage_path('app/'.$a->path)))])->values()->all(),
            ])->all();
        }
        if ($rkas = $this->paketRkas($tahun)) {
            $paket['data']['rkas'] = $rkas;
        }
        $paket['data']['tandatangan'] = Penandatangan::orderBy('id')->get()
            ->map(fn ($p) => ['nama' => $p->nama, 'jabatan' => $p->jabatan, 'nip' => $p->nip, 'pangkat' => $p->pangkat])->all();
        $paket['data']['tutup'] = TutupBuku::where('tahun', $tahun)->with('stage')
            ->get()->map(fn ($b) => $b->stage?->urutan)->filter()->values()->all();
        $paket['data']['lpj_kumpulkan'] = LpjSubmission::where('tahun', $tahun)->where('status', 'dikirim')
            ->with('stage')->get()->map(fn ($s) => $s->stage?->urutan)->filter()->values()->all();
        if (empty(array_filter($paket['data']))) {
            $this->info('Tidak ada perubahan sejak dorongan terakhir.');

            return 0;
        }
        if ($this->option('kering')) {
            $this->line(json_encode($paket, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }
        try {
            $res = Http::timeout(120)->withToken($conf['TOKEN'])
                ->post(rtrim($conf['PUSAT_URL'], '/').'/api/v1/sync-push', $paket);
        } catch (\Throwable $e) {
            $this->error('Tidak bisa menghubungi pusat: '.$e->getMessage());

            return 1;
        }
        if (! $res->ok() || ! ($res->json('ok') ?? false)) {
            $this->error('Pusat menolak: '.($res->json('pesan') ?? 'HTTP '.$res->status()));

            return 1;
        }
        DB::table('sync_states')->updateOrInsert(['kunci' => 'terakhir_dorong'],
            ['nilai' => now()->toIso8601String(), 'updated_at' => now(), 'created_at' => now()]);
        $this->info('Terkirim +'.$res->json('diterima').' / diperbarui '.$res->json('diperbarui').' / ditolak '.count($res->json('ditolak', [])));
        foreach ($res->json('ditolak', []) as $tolak) {
            $this->warn('- '.$tolak['kunci'].': '.$tolak['alasan']);
        }
        foreach ($res->json('peringatan', []) as $w) {
            $this->warn('! '.$w);
        }

        return 0;
    }

    /** Paket RKAS: header DRAFT tahun berjalan + rinciannya (idempoten via arkas_id). */
    protected function paketRkas(int $tahun): ?array
    {
        $h = RkasHeader::with('details.rekening')->where('tahun', $tahun)
            ->where('status', 'draft')->orderByDesc('revisi_ke')->first();
        if (! $h || $h->details->isEmpty()) {
            return null;
        }

        return ['no_rkas' => $h->no_rkas, 'catatan' => $h->catatan,
            'details' => $h->details->map(fn ($d) => [
                'kunci' => $d->arkas_id ?: 'lokal-rkas:'.$d->id,
                'rekening' => $d->rekening?->kode, 'uraian' => $d->uraian,
                'satuan' => $d->satuan, 'banyak' => $d->banyak, 'harga' => $d->harga_satuan,
                'jumlah' => $d->jumlah, 'sumber' => $d->sumber_dana, 'kode_kegiatan' => $d->kode_kegiatan])->all()];
    }

    /** Baca launcher-windows/akun-sekolah.conf (lihat App\Support\KonfigLokal). */
    public function bacaConf(): ?array
    {
        $conf = KonfigLokal::baca(base_path('launcher-windows/akun-sekolah.conf'));

        return $conf ?: null;
    }
}
