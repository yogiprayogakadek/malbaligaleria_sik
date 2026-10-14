<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\WatermarkedPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SensitiveDocumentResponse
{
    private const MAX_IMAGE_PIXELS = 40_000_000;

    public function make(string $path, User $viewer, Model $permit, string $documentKind): Response
    {
        $absolutePath = Storage::disk('local')->path($path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        $watermark = $this->watermarkText($viewer);

        try {
            [$content, $responseMime, $extension] = match ($mimeType) {
                'application/pdf' => [$this->watermarkPdf($absolutePath, $watermark), 'application/pdf', 'pdf'],
                'image/jpeg' => [$this->watermarkImage($absolutePath, $watermark, 'jpeg'), 'image/jpeg', 'jpg'],
                'image/png' => [$this->watermarkImage($absolutePath, $watermark, 'png'), 'image/png', 'png'],
                'image/webp' => [$this->watermarkImage($absolutePath, $watermark, 'webp'), 'image/webp', 'webp'],
                default => throw new RuntimeException('unsupported_document_type'),
            };
        } catch (Throwable $exception) {
            Log::warning('Dokumen sensitif gagal diberi watermark.', [
                'viewer_id' => $viewer->id,
                'permit_type' => class_basename($permit),
                'permit_id' => $permit->getKey(),
                'document_kind' => $documentKind,
                'exception' => $exception::class,
            ]);

            abort(422, 'Dokumen tidak dapat ditampilkan dengan perlindungan watermark.');
        }

        $this->recordAccess($viewer, $permit, $documentKind);

        return response($content, 200, [
            'Content-Type' => $responseMime,
            'Content-Disposition' => 'inline; filename="dokumen-terlindungi.'.$extension.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Document-Watermark' => 'applied',
        ]);
    }

    private function watermarkPdf(string $path, string $watermark): string
    {
        $pdf = new WatermarkedPdf;
        $pdf->SetCompression(false);
        $pageCount = $pdf->setSourceFile($path);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $pdf->addIdentityWatermark($watermark);
        }

        return $pdf->Output('S');
    }

    private function watermarkImage(string $path, string $watermark, string $format): string
    {
        $dimensions = getimagesize($path);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new RuntimeException('invalid_image_dimensions');
        }
        if ($dimensions[0] * $dimensions[1] > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('image_dimensions_too_large');
        }

        $source = file_get_contents($path);
        $image = $source === false ? false : imagecreatefromstring($source);
        if ($image === false) {
            throw new RuntimeException('invalid_image');
        }

        try {
            $image = $this->orientJpeg($image, $path, $format);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            $width = imagesx($image);
            $height = imagesy($image);
            $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
            if (! is_file($font)) {
                throw new RuntimeException('watermark_font_missing');
            }

            $fontSize = max(11, min(24, (int) round($width / 58)));
            $color = imagecolorallocatealpha($image, 28, 63, 105, 72);
            $box = imagettfbbox($fontSize, 30, $font, $watermark);
            if ($box === false) {
                throw new RuntimeException('watermark_measurement_failed');
            }
            $textWidth = abs($box[4] - $box[0]);
            $xStep = max((int) ($width * .72), $textWidth + 100);
            $yStep = max(100, $fontSize * 7);

            for ($y = 35; $y < $height + $yStep; $y += $yStep) {
                for ($x = -((int) ($width * .3)); $x < $width; $x += $xStep) {
                    imagettftext($image, $fontSize, 30, $x, $y, $color, $font, $watermark);
                }
            }

            ob_start();
            match ($format) {
                'jpeg' => imagejpeg($image, null, 88),
                'png' => imagepng($image, null, 6),
                'webp' => imagewebp($image, null, 88),
            };

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }

    private function orientJpeg(\GdImage $image, string $path, string $format): \GdImage
    {
        if ($format !== 'jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            throw new RuntimeException('image_orientation_failed');
        }
        imagedestroy($image);

        return $rotated;
    }

    private function watermarkText(User $viewer): string
    {
        $name = Str::upper(Str::limit(Str::ascii($viewer->name), 24, ''));
        $role = $viewer->isValidator()
            ? 'VALIDATOR '.Str::upper((string) $viewer->division)
            : Str::upper($viewer->role);

        return sprintf(
            'MBG | %s | %s | U%d | %s WITA',
            $name,
            $role,
            $viewer->id,
            now()->format('d/m/y H:i'),
        );
    }

    private function recordAccess(User $viewer, Model $permit, string $documentKind): void
    {
        AuditLog::query()->create([
            'actor_id' => $viewer->id,
            'event' => 'viewed',
            'auditable_type' => $permit::class,
            'auditable_id' => $permit->getKey(),
            'route_name' => request()->route()?->getName(),
            'request_method' => request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => [],
            'new_values' => [
                'document_kind' => $documentKind,
                'watermark_applied' => true,
            ],
        ]);
    }
}
