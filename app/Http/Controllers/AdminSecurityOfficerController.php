<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\SecurityOfficer;
use App\Services\SecurityOfficerPin;
use App\Services\WhatsappNotificationDispatcher;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminSecurityOfficerController extends Controller
{
    private const MAX_IMPORT_ROWS = 500;

    public function index(Request $request): View
    {
        $status = $request->string('status', 'active')->toString();
        abort_unless(in_array($status, ['active', 'inactive', 'all'], true), 404);
        $perPageRaw = $request->string('per_page', '20')->toString();
        abort_unless(in_array($perPageRaw, ['10', '20', '50', '100'], true), 404);

        $officers = SecurityOfficer::query()
            ->with(['latestCredentialMessage' => fn ($query) => $query->select([
                'whatsapp_message_logs.id',
                'whatsapp_message_logs.permit_id',
                'whatsapp_message_logs.status',
                'whatsapp_message_logs.sent_at',
                'whatsapp_message_logs.created_at',
            ])])
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate((int) $perPageRaw)
            ->withQueryString();

        return view('admin.security-officers.index', compact('officers', 'status', 'perPageRaw'));
    }

    public function store(Request $request, SecurityOfficerPin $pins, WhatsappNotificationDispatcher $whatsapp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'officer_id' => ['required', 'string', 'max:60', 'regex:/\A[A-Za-z0-9._\/-]+\z/'],
            'whatsapp' => ['required', 'string', 'max:30'],
        ]);
        $data['officer_id'] = mb_strtoupper(trim($data['officer_id']));
        $data['whatsapp'] = $this->validPhone($data['whatsapp'], 'whatsapp');

        validator($data, [
            'officer_id' => [Rule::unique('security_officers', 'officer_id')],
            'whatsapp' => [Rule::unique('security_officers', 'whatsapp')],
        ], [
            'officer_id.unique' => 'Nomor ID petugas sudah terdaftar.',
            'whatsapp.unique' => 'Nomor WhatsApp sudah digunakan petugas lain.',
        ])->validate();

        $pin = $pins->generate();
        $officer = SecurityOfficer::query()->create([
            'name' => trim($data['name']),
            'officer_id' => $data['officer_id'],
            'whatsapp' => $data['whatsapp'],
            'pin_hash' => Hash::make($pin),
            'is_active' => true,
            'pin_rotated_at' => now(),
        ]);
        $queued = $whatsapp->securityOfficerCredentials($officer, $pin);

        return redirect()->route('admin.security-officers.index')
            ->with('success', 'Petugas security berhasil ditambahkan.')
            ->with('whatsapp_delivery', $queued
                ? 'Akses scanner telah dimasukkan ke antrean WhatsApp.'
                : 'Akses belum dikirim karena integrasi WhatsApp tidak aktif atau tidak tersedia.')
            ->with('generated_officer', [
                'name' => trim($data['name']),
                'officer_id' => $data['officer_id'],
                'pin' => $pin,
            ]);
    }

    public function import(
        Request $request,
        SecurityOfficerPin $pins,
        MalwareScanner $malwareScanner,
        WhatsappNotificationDispatcher $whatsapp,
    ): StreamedResponse {
        $request->validate([
            'officer_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);
        $file = $request->file('officer_file');
        $malwareScanner->scan($file, 'officer_file');

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $sheet = $reader->load($file->getRealPath())->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (Throwable) {
            throw ValidationException::withMessages(['officer_file' => 'File Excel tidak dapat dibaca atau rusak.']);
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['officer_file' => 'File belum memiliki data petugas.']);
        }
        if (count($rows) - 1 > self::MAX_IMPORT_ROWS) {
            throw ValidationException::withMessages(['officer_file' => 'Maksimal '.self::MAX_IMPORT_ROWS.' petugas dalam satu file.']);
        }

        $headers = array_map(fn ($value) => $this->header((string) $value), array_shift($rows));
        foreach (['nama', 'nomor_id_petugas', 'nomor_whatsapp'] as $header) {
            if (! in_array($header, $headers, true)) {
                throw ValidationException::withMessages(['officer_file' => 'Kolom wajib: Nama, Nomor ID Petugas, dan Nomor WhatsApp.']);
            }
        }
        $indexes = array_flip($headers);
        $results = [];
        $seenIds = [];
        $seenPhones = [];
        $createdCount = 0;

        DB::transaction(function () use ($rows, $indexes, $pins, $whatsapp, &$results, &$seenIds, &$seenPhones, &$createdCount): void {
            foreach ($rows as $offset => $row) {
                $line = $offset + 2;
                $raw = [
                    'name' => trim((string) ($row[$indexes['nama']] ?? '')),
                    'officer_id' => mb_strtoupper(trim((string) ($row[$indexes['nomor_id_petugas']] ?? ''))),
                    'whatsapp' => trim((string) ($row[$indexes['nomor_whatsapp']] ?? '')),
                ];
                if ($raw['name'] === '' && $raw['officer_id'] === '' && $raw['whatsapp'] === '') {
                    continue;
                }
                if (collect($raw)->contains(fn ($value) => str_starts_with($value, '='))) {
                    $results[] = $this->result($line, $raw, 'Ditolak', 'Formula Excel tidak diizinkan.');

                    continue;
                }

                $validation = validator($raw, [
                    'name' => ['required', 'string', 'max:120'],
                    'officer_id' => ['required', 'string', 'max:60', 'regex:/\A[A-Z0-9._\/-]+\z/'],
                    'whatsapp' => ['required', 'string', 'max:30'],
                ]);
                $phone = IndonesianPhoneNumber::normalize($raw['whatsapp']);
                if ($validation->fails() || ! $phone) {
                    $results[] = $this->result($line, $raw, 'Ditolak', $validation->errors()->first() ?: 'Nomor WhatsApp tidak valid.');

                    continue;
                }
                $raw['whatsapp'] = $phone;

                if (isset($seenIds[$raw['officer_id']]) || isset($seenPhones[$phone])) {
                    $results[] = $this->result($line, $raw, 'Dilewati', 'Data duplikat di dalam file.');

                    continue;
                }
                $seenIds[$raw['officer_id']] = true;
                $seenPhones[$phone] = true;

                $existing = SecurityOfficer::query()
                    ->where('officer_id', $raw['officer_id'])
                    ->orWhere('whatsapp', $phone)
                    ->first();
                if ($existing) {
                    $results[] = $this->result($line, $raw, 'Sudah ada', 'Nomor ID atau WhatsApp telah terdaftar.');

                    continue;
                }

                $pin = $pins->generate();
                $officer = SecurityOfficer::query()->create([
                    'name' => $raw['name'],
                    'officer_id' => $raw['officer_id'],
                    'whatsapp' => $phone,
                    'pin_hash' => Hash::make($pin),
                    'is_active' => true,
                    'pin_rotated_at' => now(),
                ]);
                $queued = $whatsapp->securityOfficerCredentials(
                    $officer,
                    $pin,
                    'credentials_created',
                    $createdCount * 5,
                );
                $createdCount++;
                $results[] = $this->result(
                    $line,
                    $raw,
                    'Berhasil dibuat',
                    $queued ? 'Akses masuk antrean WhatsApp.' : 'WhatsApp belum aktif; bagikan PIN secara manual.',
                    $pin,
                );
            }
        });

        if ($results === []) {
            throw ValidationException::withMessages(['officer_file' => 'Tidak ditemukan baris data yang dapat diproses.']);
        }

        return $this->importReport($results);
    }

    public function template(): StreamedResponse
    {
        return $this->spreadsheetDownload('template-petugas-security.xlsx', function (Spreadsheet $book): void {
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Petugas Security');
            $sheet->fromArray([
                ['Nama', 'Nomor ID Petugas', 'Nomor WhatsApp'],
                ['Contoh Petugas', 'SEC-001', '081234567890'],
            ]);
            $sheet->getStyle('A1:C1')->getFont()->setBold(true);
            foreach (['A' => 28, 'B' => 22, 'C' => 22] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        });
    }

    public function updateStatus(Request $request, SecurityOfficer $securityOfficer): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $securityOfficer->update(['is_active' => (bool) $data['is_active']]);

        return back()->with('success', $securityOfficer->is_active ? 'Petugas diaktifkan.' : 'Petugas dinonaktifkan.');
    }

    public function resetPin(
        SecurityOfficer $securityOfficer,
        SecurityOfficerPin $pins,
        WhatsappNotificationDispatcher $whatsapp,
    ): RedirectResponse {
        abort_unless($securityOfficer->is_active, 422, 'Aktifkan petugas sebelum mengirim ulang akses scanner.');

        $pin = $pins->generate();
        $securityOfficer->update(['pin_hash' => Hash::make($pin), 'pin_rotated_at' => now()]);
        $queued = $whatsapp->securityOfficerCredentials($securityOfficer, $pin, 'credentials_resent');

        return back()->with('success', $queued
            ? 'Nomor ID dan PIN baru dimasukkan ke antrean WhatsApp.'
            : 'PIN baru dibuat, tetapi WhatsApp belum aktif sehingga pesan tidak dapat dikirim.')
            ->with('whatsapp_delivery', $queued ? 'Pesan akan dikirim oleh queue worker.' : 'Bagikan PIN baru secara manual.')
            ->with('generated_officer', ['name' => $securityOfficer->name, 'officer_id' => $securityOfficer->officer_id, 'pin' => $pin]);
    }

    private function validPhone(string $phone, string $field): string
    {
        return IndonesianPhoneNumber::normalize($phone)
            ?? throw ValidationException::withMessages([$field => 'Nomor WhatsApp Indonesia tidak valid.']);
    }

    private function header(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }

    private function result(int $line, array $data, string $status, string $note, string $pin = ''): array
    {
        return [$line, $data['name'], $data['officer_id'], $data['whatsapp'], $status, $pin, $note];
    }

    private function importReport(array $results): StreamedResponse
    {
        return $this->spreadsheetDownload('hasil-impor-petugas-security-'.now()->format('Ymd-His').'.xlsx', function (Spreadsheet $book) use ($results): void {
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Hasil Impor');
            $sheet->fromArray([['Baris', 'Nama', 'Nomor ID Petugas', 'Nomor WhatsApp', 'Status', 'PIN', 'Keterangan']]);
            foreach ($results as $index => $row) {
                foreach ($row as $column => $value) {
                    $sheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
                }
            }
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            foreach (['A' => 10, 'B' => 28, 'C' => 22, 'D' => 22, 'E' => 18, 'F' => 12, 'G' => 42] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        });
    }

    private function spreadsheetDownload(string $filename, callable $build): StreamedResponse
    {
        return response()->streamDownload(function () use ($build): void {
            $book = new Spreadsheet;
            $build($book);
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
