<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSetting;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class PermitDocxRenderer
{
    public function __construct(private readonly LibreOfficeConverterClient $converter) {}

    public function loading(LoadingPermit $permit, DocumentTemplateSetting $setting): string
    {
        $url = route('scanner.verify', ['token' => $permit->barcode_token]);

        return $this->render('loading', $this->loadingValues($permit, $url), $url, $setting);
    }

    public function work(WorkPermit $permit, DocumentTemplateSetting $setting): string
    {
        $type = $permit->isEventPermit() ? 'event' : 'work';
        $url = route('scanner.verify', ['token' => $permit->public_token]);
        $values = $permit->isEventPermit()
            ? $this->eventValues($permit, $url)
            : $this->workValues($permit, $url);

        return $this->render($type, $values, $url, $setting, $permit);
    }

    /** @param array<string, string> $values */
    private function render(string $type, array $values, string $verificationUrl, DocumentTemplateSetting $setting, ?WorkPermit $permit = null): string
    {
        $template = DocumentTemplate::activeFor($type);
        if (! $template || ! Storage::disk('local')->exists($template->storage_path)) {
            throw new RuntimeException('Template DOCX aktif tidak ditemukan.');
        }

        $source = Storage::disk('local')->path($template->storage_path);
        $actualHash = hash_file('sha256', $source);
        if ($actualHash === false || ! hash_equals($template->sha256, $actualHash)) {
            throw new RuntimeException('Integritas template DOCX tidak valid.');
        }
        $output = tempnam(sys_get_temp_dir(), 'mbg-docx-');
        if ($output === false) {
            throw new RuntimeException('File sementara DOCX tidak dapat dibuat.');
        }
        $docx = $output.'.docx';
        @unlink($output);

        try {
            $processor = new TemplateProcessor($source);
            $variables = $processor->getVariables();
            if ($type === 'work' && $permit && in_array('worker_name', $variables, true)) {
                $rows = $permit->workers->values()->map(fn ($worker, int $index): array => [
                    'worker_number' => (string) ($index + 1),
                    'worker_name' => (string) $worker->name,
                    'worker_identity_number' => (string) ($worker->identity_number ?: '-'),
                ])->all();
                if ($rows !== []) {
                    $processor->cloneRowAndSetValues('worker_name', $rows);
                }
            }
            $processor->setValues($values);
            if (in_array('logo', $variables, true)) {
                $processor->setImageValue('logo', ['path' => public_path('logo.png'), 'width' => 110, 'ratio' => true]);
            }
            if (in_array('qr_code', $variables, true)) {
                $qrPath = $this->qrPng($verificationUrl);
                try {
                    $processor->setImageValue('qr_code', ['path' => $qrPath, 'width' => 112, 'height' => 112]);
                } finally {
                    @unlink($qrPath);
                }
            }
            $processor->saveAs($docx);

            return $this->converter->convert($docx, "permit-{$type}.docx", $setting);
        } finally {
            @unlink($docx);
        }
    }

    /** @return array<string, string> */
    private function loadingValues(LoadingPermit $permit, string $url): array
    {
        return $this->common($permit, 'Surat Izin Loading / Unloading', $url) + [
            'tenant_name' => (string) $permit->tenant_name,
            'direction' => $permit->direction_label,
            'movement_time' => $permit->movement_time_label,
            'item_count' => (string) $permit->item_count,
            'item_unit' => (string) $permit->item_unit,
            'item_description' => (string) $permit->item_description,
            'vehicle_plate' => (string) ($permit->vehicle_plate ?: '-'),
            'review_notes' => (string) ($permit->review_notes ?: '-'),
        ];
    }

    /** @return array<string, string> */
    private function workValues(WorkPermit $permit, string $url): array
    {
        $needs = collect([$permit->needs_water ? 'Air' : null, $permit->needs_lighting ? 'Penerangan' : null])->filter()->implode(', ');

        return $this->common($permit, 'Surat Izin Kerja', $url) + [
            'contractor_name' => (string) ($permit->contractor_name ?: '-'),
            'tenant_name' => (string) $permit->tenant_name,
            'work_location' => (string) $permit->work_location,
            'work_category' => $permit->work_category_label,
            'work_type' => (string) $permit->work_type,
            'work_schedule' => $permit->work_schedule_label,
            'other_work_type' => (string) ($permit->other_work_type_label ?: '-'),
            'notes' => (string) ($permit->notes ?: '-'),
            'needs' => $needs ?: 'Tidak ada',
            'deposit_amount' => $permit->deposit_required ? 'Rp '.number_format((float) $permit->deposit_amount, 0, ',', '.') : 'Tidak ada',
            'review_notes' => (string) ($permit->review_notes ?: '-'),
        ];
    }

    /** @return array<string, string> */
    private function eventValues(WorkPermit $permit, string $url): array
    {
        $requirements = collect($permit->event_requirements)->pluck('name')->filter()->implode(', ');

        return $this->common($permit, 'Surat Izin Event', $url) + [
            'organizer_name' => (string) $permit->contractor_name,
            'event_type' => (string) $permit->event_type_label,
            'work_location' => (string) $permit->work_location,
            'work_category' => $permit->work_category_label,
            'work_type' => (string) $permit->work_type,
            'work_schedule' => $permit->work_schedule_label,
            'event_requirements' => $requirements ?: 'Tidak ada',
            'includes_loading' => $permit->includes_loading_material ? 'Ya, surat loading / unloading tambahan diterbitkan' : 'Tidak',
            'notes' => (string) $permit->notes,
            'review_notes' => (string) ($permit->review_notes ?: '-'),
        ];
    }

    /** @return array<string, string> */
    private function common(LoadingPermit|WorkPermit $permit, string $title, string $url): array
    {
        return [
            'permit_number' => (string) $permit->permit_number,
            'permit_title' => $title,
            'applicant_name' => (string) $permit->applicant_name,
            'applicant_phone' => (string) $permit->applicant_phone,
            'applicant_email' => (string) $permit->applicant_email,
            'start_date' => $permit->start_date?->translatedFormat('d F Y') ?? '-',
            'end_date' => $permit->end_date?->translatedFormat('d F Y') ?? '-',
            'approved_by' => (string) ($permit->reviewer?->name ?: '-'),
            'approved_at' => $permit->reviewed_at?->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i').' WITA' ?? '-',
            'verification_url' => $url,
        ];
    }

    private function qrPng(string $url): string
    {
        try {
            $content = (string) QrCode::format('png')->errorCorrection('H')->size(240)->margin(1)->generate($url);
        } catch (Throwable $exception) {
            throw new RuntimeException('QR PNG memerlukan ekstensi Imagick pada Web CT.', previous: $exception);
        }
        $path = tempnam(sys_get_temp_dir(), 'mbg-qr-');
        if ($path === false || file_put_contents($path, $content) === false) {
            throw new RuntimeException('QR sementara tidak dapat dibuat.');
        }

        return $path;
    }
}
