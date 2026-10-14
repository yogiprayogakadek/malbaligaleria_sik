<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReportRequest;
use App\Services\PermitReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(AdminReportRequest $request, PermitReportService $reports): View
    {
        $filters = $request->filters();

        return view('admin.reports.index', [
            'filters' => $filters,
            'rows' => $reports->paginate($filters),
            'summary' => $reports->summary($filters),
            'trend' => $reports->trend($filters),
        ]);
    }

    public function excel(AdminReportRequest $request, PermitReportService $reports): StreamedResponse
    {
        $filters = $request->filters();
        [$rows, $truncated] = $reports->exportRows($filters, 10000);
        if ($truncated) {
            throw ValidationException::withMessages([
                'export' => 'Hasil Excel melebihi 10.000 baris. Persempit periode atau filter laporan.',
            ]);
        }

        $summary = $reports->summary($filters);
        $spreadsheet = $this->spreadsheet($filters, $summary, $rows);
        $filename = 'laporan-perizinan-'.$filters['date_from'].'-'.$filters['date_to'].'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pdf(AdminReportRequest $request, PermitReportService $reports): Response
    {
        $filters = $request->filters();
        [$rows, $truncated] = $reports->exportRows($filters, 2000);
        if ($truncated) {
            throw ValidationException::withMessages([
                'export' => 'Hasil PDF melebihi 2.000 baris. Persempit periode atau filter laporan.',
            ]);
        }

        $logoPath = public_path('logo.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return Pdf::loadView('admin.reports.pdf', [
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $reports->summary($filters),
            'logoData' => $logoData,
            'generatedAt' => now(config('operating-hours.timezone')),
        ])->setPaper('a4', 'landscape')->download(
            'laporan-perizinan-'.$filters['date_from'].'-'.$filters['date_to'].'.pdf',
        );
    }

    private function spreadsheet(array $filters, array $summary, $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Sistem Informasi Perizinan Mal Bali Galeria')
            ->setTitle('Laporan Perizinan')
            ->setSubject('Rekap loading, unloading, dan surat izin kerja');

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Ringkasan');
        $summarySheet->mergeCells('A1:D1')->setCellValue('A1', 'LAPORAN PERIZINAN MAL BALI GALERIA');
        $summarySheet->mergeCells('A2:D2')->setCellValue('A2', 'Periode '.$filters['date_from'].' s.d. '.$filters['date_to']);
        $summarySheet->fromArray([
            ['METRIK', 'JUMLAH', 'JENIS PERMOHONAN', 'JUMLAH'],
            ['Total permohonan', $summary['total'], 'Loading / Unloading', $summary['loading']],
            ['Masih diproses', $summary['active'], 'Surat Izin Kerja', $summary['work']],
            ['Disetujui', $summary['approved'], '', ''],
            ['Ditolak', $summary['rejected'], '', ''],
        ], null, 'A4');
        $summarySheet->fromArray([['DISTRIBUSI STATUS', 'JENIS', 'JUMLAH']], null, 'A11');
        $statusRow = 12;
        foreach ($summary['by_status'] as $status) {
            $summarySheet->setCellValueExplicit("A{$statusRow}", $status->label, DataType::TYPE_STRING);
            $summarySheet->setCellValueExplicit("B{$statusRow}", $status->permit_type === 'loading' ? 'Loading / Unloading' : 'Surat Izin Kerja', DataType::TYPE_STRING);
            $summarySheet->setCellValue("C{$statusRow}", (int) $status->total);
            $statusRow++;
        }
        $summarySheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF173E70');
        $summarySheet->getStyle('A1:D2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summarySheet->getStyle('A4:D4')->applyFromArray($this->headerStyle());
        $summarySheet->getStyle('A11:C11')->applyFromArray($this->headerStyle());
        $summarySheet->getStyle('A4:D8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDCE4EE');
        if ($statusRow > 12) {
            $summarySheet->getStyle('A11:C'.($statusRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDCE4EE');
        }
        foreach (['A' => 28, 'B' => 18, 'C' => 28, 'D' => 16] as $column => $width) {
            $summarySheet->getColumnDimension($column)->setWidth($width);
        }

        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('Data');
        $headers = ['No.', 'Nomor surat', 'Jenis', 'Tenant / kontraktor', 'Pemohon', 'Kategori', 'Divisi', 'Diajukan', 'Periode izin', 'Status'];
        $dataSheet->fromArray($headers, null, 'A1');
        $dataSheet->getStyle('A1:J1')->applyFromArray($this->headerStyle());
        foreach ($rows->values() as $index => $row) {
            $excelRow = $index + 2;
            $dataSheet->setCellValue("A{$excelRow}", $index + 1);
            $values = [
                'B' => $row->permit_number,
                'C' => $row->type_label,
                'D' => $row->entity_name,
                'E' => $row->applicant_name,
                'F' => $row->subtype_label,
                'G' => $row->assigned_division,
                'H' => $row->created_at->format('d/m/Y H:i').' WITA',
                'I' => $row->start_date->format('d/m/Y').' - '.$row->end_date->format('d/m/Y'),
                'J' => $row->status_label,
            ];
            foreach ($values as $column => $value) {
                $dataSheet->setCellValueExplicit("{$column}{$excelRow}", (string) $value, DataType::TYPE_STRING);
            }
        }
        $lastRow = max(2, $rows->count() + 1);
        $dataSheet->setAutoFilter("A1:J{$lastRow}");
        $dataSheet->freezePane('A2');
        $dataSheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE3E9F0');
        $dataSheet->getStyle("A2:J{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach (['A' => 7, 'B' => 24, 'C' => 20, 'D' => 28, 'E' => 24, 'F' => 22, 'G' => 10, 'H' => 20, 'I' => 25, 'J' => 22] as $column => $width) {
            $dataSheet->getColumnDimension($column)->setWidth($width);
        }
        $dataSheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F5C9E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
    }
}
