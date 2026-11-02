<?php

namespace App\Jobs;

use App\Mail\NotifMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pengirim satu surat notifikasi di latar belakang (antrean).
 * Mengulang otomatis beberapa kali bila server SMTP sempat down;
 * bila tetap gagal, tugas masuk failed_jobs tanpa mengganggu aplikasi.
 */
class KirimEmailNotif implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Coba kirim maks. 3× dengan jeda bertingkat (30 detik, 2 menit). */
    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        public string $namaPenerima,
        public string $emailPenerima,
        public string $subjek,
        public array $baris,
        public ?string $url = null,
    ) {}

    public function handle(): void
    {
        Mail::to($this->emailPenerima)->send(new NotifMail(
            judulEmail: $this->subjek,
            salam: 'Yth. '.$this->namaPenerima.',',
            baris: $this->baris,
            aksiUrl: $this->url && ! Str::startsWith($this->url, 'http')
                ? url($this->url) : $this->url,
            aksiLabel: 'Buka di SiapBOSP',
        ));
    }

    /** Tercatat saat semua percobaan gagal — aplikasi tetal tenang. */
    public function failed(\Throwable $e): void
    {
        Log::warning("KirimEmailNotif: surat '{$this->subjek}' ke {$this->emailPenerima} gagal permanen: {$e->getMessage()}");
    }
}
