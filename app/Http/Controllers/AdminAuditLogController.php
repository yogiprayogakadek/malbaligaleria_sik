<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoadingPermit;
use App\Models\MailSetting;
use App\Models\OperatingSchedule;
use App\Models\OperatingScheduleException;
use App\Models\ScannerSetting;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'event' => ['nullable', 'in:created,updated,deleted'],
            'model' => ['nullable', 'in:user,loading,work,schedule,exception,scanner,email,whatsapp'],
        ]);

        $modelTypes = [
            'user' => User::class,
            'loading' => LoadingPermit::class,
            'work' => WorkPermit::class,
            'schedule' => OperatingSchedule::class,
            'exception' => OperatingScheduleException::class,
            'scanner' => ScannerSetting::class,
            'email' => MailSetting::class,
            'whatsapp' => WhatsappSetting::class,
        ];

        $logs = AuditLog::query()
            ->with('actor:id,name,role,division')
            ->when($validated['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($validated['model'] ?? null, fn ($query, $model) => $query->where('auditable_type', $modelTypes[$model]))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'event' => $validated['event'] ?? '',
            'model' => $validated['model'] ?? '',
            'modelTypes' => $modelTypes,
            'modelLabels' => [
                User::class => 'Akun pengguna',
                LoadingPermit::class => 'Loading barang',
                WorkPermit::class => 'Surat izin kerja',
                OperatingSchedule::class => 'Jam operasional',
                OperatingScheduleException::class => 'Jadwal khusus',
                ScannerSetting::class => 'Pengaturan scanner',
                MailSetting::class => 'Konfigurasi email',
                WhatsappSetting::class => 'Konfigurasi WhatsApp',
            ],
            'totalLogs' => AuditLog::query()->count(),
            'todayLogs' => AuditLog::query()->where('created_at', '>=', now()->startOfDay())->count(),
        ]);
    }
}
