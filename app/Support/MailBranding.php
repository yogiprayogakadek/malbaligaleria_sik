<?php

namespace App\Support;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class MailBranding
{
    public const LOGO_CONTENT_ID = 'mbg-logo@malbaligaleria.com';

    public static function embedLogo(object $message): void
    {
        $message->withSymfonyMessage(function (Email $email): void {
            $logo = (new DataPart(
                new File(public_path('email-logo.png')),
                'mal-bali-galeria.png',
                'image/png',
            ))->asInline();
            $logo->setContentId(self::LOGO_CONTENT_ID);
            $email->addPart($logo);
        });
    }
}
