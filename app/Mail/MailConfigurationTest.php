<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailConfigurationTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $sentAt)
    {
        MailBranding::embedLogo($this);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[MBG] Pengujian Konfigurasi Email');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.configuration-test',
            with: ['sentAt' => $this->sentAt],
        );
    }
}
