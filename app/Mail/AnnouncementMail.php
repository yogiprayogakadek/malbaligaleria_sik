<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $announcementTitle,
        public string $announcementMessage,
        public ?string $recipientName,
    ) {}

    public function build(): self
    {
        MailBranding::embedLogo($this);

        return $this
            ->subject('[MBG] '.$this->announcementTitle)
            ->view('emails.announcement');
    }
}
