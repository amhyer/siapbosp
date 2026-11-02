<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surat notifikasi umum SiapBOSP: judul + salam + paragraf + tombol aksi.
 */
class NotifMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $judulEmail,
        public string $salam,
        public array $baris,
        public ?string $aksiUrl = null,
        public ?string $aksiLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SiapBOSP] '.$this->judulEmail);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.notifikasi');
    }
}
