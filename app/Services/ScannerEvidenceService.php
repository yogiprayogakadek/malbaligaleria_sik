<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScannerEvidenceService
{
    public function store(UploadedFile $file, string $permitNumber, string $officerId, string $event): array
    {
        $bytes = file_get_contents($file->getRealPath());
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (! $size || $size[0] < 320 || $size[1] < 320 || ($size[0] * $size[1]) > 24000000) {
            throw ValidationException::withMessages(['evidence_photo' => 'Foto tidak dapat dibaca atau resolusinya tidak sesuai.']);
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['evidence_photo' => 'Pemrosesan foto sedang tidak tersedia. Hubungi administrator.']);
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages(['evidence_photo' => 'Isi file bukan foto yang valid.']);
        }

        $scale = min(1, 1920 / max($size[0], $size[1]));
        $width = max(1, (int) round($size[0] * $scale));
        $height = max(1, (int) round($size[1] * $scale));
        $output = imagecreatetruecolor($width, $height);
        imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        imagedestroy($source);

        $stamp = sprintf('%s | %s | %s | %s WITA', $permitNumber, $officerId, $event === 'check_in' ? 'MASUK' : 'KELUAR', now()->format('d-m-Y H:i:s'));
        $barHeight = 34;
        imagefilledrectangle($output, 0, max(0, $height - $barHeight), $width, $height, imagecolorallocatealpha($output, 15, 23, 42, 15));
        imagestring($output, 3, 10, max(6, $height - 23), mb_substr($stamp, 0, 150), imagecolorallocate($output, 255, 255, 255));

        ob_start();
        imagejpeg($output, null, 86);
        $encoded = (string) ob_get_clean();
        imagedestroy($output);

        $path = 'scanner/evidence/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $encoded)) {
            throw ValidationException::withMessages(['evidence_photo' => 'Foto belum dapat disimpan. Silakan coba kembali.']);
        }

        return ['path' => $path, 'sha256' => hash('sha256', $encoded)];
    }
}
