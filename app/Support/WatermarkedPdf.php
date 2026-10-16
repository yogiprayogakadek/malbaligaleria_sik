<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

class WatermarkedPdf extends Fpdi
{
    private float $rotationAngle = 0;

    public function addIdentityWatermark(string $text): void
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text) ?: 'MBG STAFF';
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(174, 181, 194);

        for ($y = 18; $y < $this->GetPageHeight() + 20; $y += 34) {
            for ($x = -45; $x < $this->GetPageWidth() + 30; $x += 105) {
                $this->rotate(30, $x, $y);
                $this->Text($x, $y, $encoded);
                $this->rotate(0);
            }
        }
    }

    public function addLogoWatermark(string $path): void
    {
        $width = min(76.0, $this->GetPageWidth() * .42);
        $x = ($this->GetPageWidth() - $width) / 2;
        $y = ($this->GetPageHeight() - ($width * .81)) / 2;

        $this->Image($path, $x, $y, $width, 0, 'PNG');
    }

    protected function _endpage(): void
    {
        if ($this->rotationAngle !== 0.0) {
            $this->rotate(0);
        }

        parent::_endpage();
    }

    private function rotate(float $angle, float $x = -1, float $y = -1): void
    {
        if ($x === -1.0) {
            $x = $this->x;
        }
        if ($y === -1.0) {
            $y = $this->y;
        }
        if ($this->rotationAngle !== 0.0) {
            $this->_out('Q');
        }

        $this->rotationAngle = $angle;
        if ($angle === 0.0) {
            return;
        }

        $radians = $angle * M_PI / 180;
        $cosine = cos($radians);
        $sine = sin($radians);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf(
            'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
            $cosine,
            $sine,
            -$sine,
            $cosine,
            $cx,
            $cy,
            -$cx,
            -$cy,
        ));
    }
}
