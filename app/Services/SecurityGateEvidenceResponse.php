<?php

namespace App\Services;

use App\Models\SecurityGateEvent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SecurityGateEvidenceResponse
{
    public function make(SecurityGateEvent $event): BinaryFileResponse
    {
        abort_unless($event->permit_type === 'loading', 404);
        abort_unless($event->evidence_path && Storage::disk('local')->exists($event->evidence_path), 404);

        $path = Storage::disk('local')->path($event->evidence_path);
        $actualHash = hash_file('sha256', $path) ?: '';
        abort_unless(hash_equals((string) $event->evidence_sha256, $actualHash), 409, 'Integritas foto bukti tidak sesuai.');

        $response = response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
