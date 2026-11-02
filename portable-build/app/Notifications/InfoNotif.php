<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notifikasi database generik: ikon + judul + isi + tautan tujuan. */
class InfoNotif extends Notification
{
    use Queueable;

    public function __construct(
        private string $ikon,
        private string $judul,
        private string $isi,
        private ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['ikon' => $this->ikon, 'judul' => $this->judul, 'isi' => $this->isi, 'url' => $this->url];
    }
}
