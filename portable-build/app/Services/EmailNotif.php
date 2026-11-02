<?php

namespace App\Services;

use App\Jobs\KirimEmailNotif;
use App\Mail\NotifMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pengirim notifikasi email yang TIDAK PERNAH mengganggu alur utama.
 *
 * Dua mode otomatis:
 *  - QUEUE_CONNECTION=sync (default pengujian): surat dikirim langsung.
 *  - Selain sync (mis. database/Redis di produksi): surat diantrekan dan
 *    dikerjakan php artisan queue:work/listen di latar belakang —
 *    aksi pengguna selesai dalam hitungan milidetik.
 */
class EmailNotif
{
    /**
     * @param  iterable  $users  Koleksi \App\Models\User
     * @param  array  $baris  Paragraf-paragraf isi surat
     * @return int Jumlah surat yang dikirim/diantrekan
     */
    public static function kirim(iterable $users, string $subjek, array $baris, ?string $url = null): int
    {
        $ok = 0;
        $mailer = (string) config('mail.default');
        $antri = (string) config('queue.default') !== 'sync';
        foreach ($users as $u) {
            $email = trim((string) ($u->email ?? ''));
            if ($email === '') {
                continue;
            }
            try {
                if ($antri) {
                    KirimEmailNotif::dispatch(
                        $u->name ?? 'Pengguna', $email, $subjek, $baris, $url);
                } else {
                    Mail::to($email)->send(new NotifMail(
                        judulEmail: $subjek,
                        salam: 'Yth. '.($u->name ?? 'Pengguna').',',
                        baris: $baris,
                        aksiUrl: $url
                            ? (Str::startsWith($url, 'http') ? $url : url($url))
                            : null,
                        aksiLabel: 'Buka di SiapBOSP',
                    ));
                }
                $ok++;
            } catch (\Throwable $e) {
                Log::warning("EmailNotif: gagal memproses '{$subjek}' ke {$email} [mailer={$mailer}]: {$e->getMessage()}");
            }
        }

        return $ok;
    }
}
