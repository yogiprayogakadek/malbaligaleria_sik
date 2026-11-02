<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSetting;
use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Carbon\Carbon;
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
        $validatorUrl = route('permit-approvals.verify', ['documentId' => $permit->official_document_id]);

        return $this->render($this->activeTemplate('loading'), $this->loadingValues($permit, $url, $validatorUrl), $url, $validatorUrl, $setting);
    }

    public function work(WorkPermit $permit, DocumentTemplateSetting $setting): string
    {
        $type = $permit->isEventPermit() ? 'event' : 'work';
        $url = route('scanner.verify', ['token' => $permit->public_token]);
        $validatorUrl = route('permit-approvals.verify', ['documentId' => $permit->official_document_id]);
        $values = $permit->isEventPermit()
            ? $this->eventValues($permit, $url, $validatorUrl)
            : $this->workValues($permit, $url, $validatorUrl);

        $workerRows = $type === 'work'
            ? $permit->workers->values()->map(fn ($worker, int $index): array => [
                'worker_number' => (string) ($index + 1),
                'worker_name' => (string) $worker->name,
                'worker_identity_number' => (string) ($worker->identity_number ?: '-'),
            ])->all()
            : [];

        return $this->render($this->activeTemplate($type), $values, $url, $validatorUrl, $setting, $workerRows);
    }

    /** @param array<string, string> $data */
    public function preview(DocumentTemplate $template, array $data, DocumentTemplateSetting $setting): string
    {
        $verificationUrl = route('portal.help');
        $common = [
            'permit_number' => 'MBG/CONTOH/'.strtoupper($template->permit_type).'/'.now()->format('Ymd'),
            'permit_title' => $template->typeLabel(),
            'applicant_name' => $data['applicant_name'],
            'applicant_phone' => '080000000000',
            'applicant_email' => 'preview@example.test',
            'start_date' => Carbon::parse($data['start_date'])->translatedFormat('d F Y'),
            'end_date' => Carbon::parse($data['end_date'])->translatedFormat('d F Y'),
            'approved_by' => 'Validator Contoh',
            'approved_at' => now()->translatedFormat('d F Y, H:i').' WITA',
            'verification_url' => $verificationUrl,
            'validator_verification_url' => $verificationUrl,
        ];
        $specific = match ($template->permit_type) {
            'loading' => [
                'tenant_name' => $data['organization_name'],
                'direction' => 'Loading barang',
                'movement_time' => '22:30 WITA',
                'item_count' => '2',
                'item_unit' => 'koli',
                'item_description' => $data['work_detail'],
                'vehicle_plate' => 'DK 0000 XX',
                'review_notes' => 'Dokumen preview — bukan surat resmi',
            ],
            'work' => [
                'contractor_name' => $data['organization_name'],
                'tenant_name' => 'Tenant Contoh',
                'work_location' => $data['work_location'],
                'work_category' => 'Pekerjaan Lainnya',
                'work_type' => $data['work_detail'],
                'work_schedule' => 'Dalam toko, 22.00–10.00 WITA',
                'other_work_type' => 'Lainnya',
                'notes' => 'Dokumen preview — bukan surat resmi',
                'needs' => 'Penerangan',
                'deposit_amount' => 'Tidak ada',
                'review_notes' => 'Data contoh untuk menguji tata letak template',
            ],
            'event' => [
                'organizer_name' => $data['organization_name'],
                'event_type' => 'Surat Izin Event Tenant',
                'work_location' => $data['work_location'],
                'work_category' => 'In-store event (Tenant)',
                'work_type' => $data['work_detail'],
                'work_schedule' => '10.00–22.00 WITA',
                'event_requirements' => 'Penerangan / Lampu area',
                'includes_loading' => 'Tidak',
                'notes' => 'Dokumen preview — bukan surat resmi',
                'review_notes' => 'Data contoh untuk menguji tata letak template',
            ],
            default => throw new RuntimeException('Jenis template tidak didukung.'),
        };
        $workerRows = $template->permit_type === 'work' ? [[
            'worker_number' => '1',
            'worker_name' => 'Pekerja Contoh',
            'worker_identity_number' => 'ID-CONTOH',
        ]] : [];

        return $this->render($template, $common + $specific, $verificationUrl, $verificationUrl, $setting, $workerRows);
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<int, array<string, string>>  $workerRows
     */
    private function render(DocumentTemplate $template, array $values, string $verificationUrl, string $validatorVerificationUrl, DocumentTemplateSetting $setting, array $workerRows = []): string
    {
        if (! Storage::disk('local')->exists($template->storage_path)) {
            throw new RuntimeException('Template DOCX tidak ditemukan.');
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
            if ($template->permit_type === 'work' && $workerRows !== [] && in_array('worker_name', $variables, true)) {
                $processor->cloneRowAndSetValues('worker_name', $workerRows);
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
            if (in_array('validator_qr_code', $variables, true)) {
                $qrPath = $this->qrPng($validatorVerificationUrl);
                try {
                    $processor->setImageValue('validator_qr_code', ['path' => $qrPath, 'width' => 96, 'height' => 96]);
                } finally {
                    @unlink($qrPath);
                }
            }
            $processor->saveAs($docx);

            return $this->converter->convert($docx, "permit-{$template->permit_type}.docx", $setting);
        } finally {
            @unlink($docx);
        }
    }

    private function activeTemplate(string $type): DocumentTemplate
    {
        return DocumentTemplate::activeFor($type)
            ?? throw new RuntimeException('Template DOCX aktif tidak ditemukan.');
    }

    /** @return array<string, string> */
    private function loadingValues(LoadingPermit $permit, string $url, string $validatorUrl): array
    {
        return $this->common($permit, 'Surat Izin Loading / Unloading', $url, $validatorUrl) + [
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
    private function workValues(WorkPermit $permit, string $url, string $validatorUrl): array
    {
        $needs = collect([$permit->needs_water ? 'Air' : null, $permit->needs_lighting ? 'Penerangan' : null])->filter()->implode(', ');

        return $this->common($permit, 'Surat Izin Kerja', $url, $validatorUrl) + [
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
    private function eventValues(WorkPermit $permit, string $url, string $validatorUrl): array
    {
        $requirements = collect($permit->event_requirements)->pluck('name')->filter()->implode(', ');

        return $this->common($permit, 'Surat Izin Event', $url, $validatorUrl) + [
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
    private function common(LoadingPermit|WorkPermit $permit, string $title, string $url, string $validatorUrl): array
    {
        return [
            'permit_number' => (string) $permit->permit_number,
            'permit_title' => $title,
            'applicant_name' => (string) $permit->applicant_name,
            'applicant_phone' => (string) $permit->applicant_phone,
            'applicant_email' => (string) $permit->applicant_email,
            'start_date' => $permit->start_date?->translatedFormat('d F Y') ?? '-',
            'end_date' => $permit->end_date?->translatedFormat('d F Y') ?? '-',
            'approved_by' => (string) ($permit->official_approved_by_name ?: $permit->reviewer?->name ?: '-'),
            'approved_at' => $permit->reviewed_at?->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i').' WITA' ?? '-',
            'verification_url' => $url,
            'validator_verification_url' => $validatorUrl,
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
