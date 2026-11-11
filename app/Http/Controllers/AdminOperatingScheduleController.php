<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperatingScheduleExceptionRequest;
use App\Http\Requests\UpdateOperatingScheduleRequest;
use App\Models\AuditLog;
use App\Models\NationalHoliday;
use App\Models\OperatingSchedule;
use App\Models\OperatingScheduleException;
use App\Services\NationalHolidaySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminOperatingScheduleController extends Controller
{
    public function edit()
    {
        $scheduleGroups = collect([
            OperatingSchedule::SCOPE_GENERAL => [
                'title' => 'Loading/Unloading dan Surat Izin Kerja',
                'description' => 'Jadwal pengajuan untuk Loading/Unloading dan Surat Izin Kerja.',
            ],
            OperatingSchedule::SCOPE_EVENT => [
                'title' => 'Surat Izin Event',
                'description' => 'Jadwal khusus pengajuan Surat Izin Event, terpisah dari jenis surat lainnya.',
            ],
        ])->map(function (array $group, string $scope): array {
            return $group + [
                'scope' => $scope,
                'schedules' => OperatingSchedule::weekly($scope),
                'siteIsOpen' => OperatingSchedule::siteIsOpen(scope: $scope),
                'exceptions' => OperatingScheduleException::query()
                    ->where('scope', $scope)
                    ->whereDate('date', '>=', today())
                    ->orderBy('date')
                    ->take(20)
                    ->get(),
            ];
        });

        return view('admin.schedule.edit', [
            'scheduleGroups' => $scheduleGroups,
            'holidayCalendar' => [
                'apiConfigured' => filled(config('operating-hours.holidays.api_key')),
                'count' => NationalHoliday::query()->whereDate('date', '>=', today())->count(),
                'lastSyncedAt' => NationalHoliday::query()->max('synced_at'),
                'upcoming' => NationalHoliday::query()->whereDate('date', '>=', today())->orderBy('date')->take(8)->get(),
            ],
        ]);
    }

    public function syncHolidays(NationalHolidaySyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.schedule.edit')
                ->withErrors(['holiday_sync' => 'Kalender gagal diperbarui. Data sebelumnya tetap digunakan. Periksa konfigurasi API lalu coba lagi.']);
        }

        AuditLog::query()->create([
            'actor_id' => auth()->id(),
            'event' => 'national_holidays_synced',
            'auditable_type' => NationalHoliday::class,
            'route_name' => request()->route()?->getName(),
            'request_method' => request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $result,
        ]);

        return redirect()->route('admin.schedule.edit')->with(
            'success',
            "Kalender {$result['years'][0]} dan {$result['years'][1]} diperbarui: {$result['total']} hari libur, {$result['added']} baru, {$result['updated']} berubah, {$result['removed']} dihapus.",
        );
    }

    public function update(UpdateOperatingScheduleRequest $request): RedirectResponse
    {
        $scope = $request->validated('scope');

        DB::transaction(function () use ($request): void {
            $scope = $request->validated('scope');

            foreach ($request->validated('days') as $day) {
                $isOpen = (bool) $day['is_open'];
                $is24Hours = $isOpen && (bool) $day['is_24_hours'];

                OperatingSchedule::query()->updateOrCreate(
                    ['scope' => $scope, 'day_of_week' => $day['day_of_week']],
                    [
                        'is_open' => $isOpen,
                        'is_24_hours' => $is24Hours,
                        'opens_at' => $isOpen && ! $is24Hours ? $day['opens_at'] : null,
                        'closes_at' => $isOpen && ! $is24Hours ? $day['closes_at'] : null,
                    ],
                );
            }
        });

        return redirect()->route('admin.schedule.edit')
            ->with('success', $scope === OperatingSchedule::SCOPE_EVENT
                ? 'Jam operasional Surat Izin Event berhasil diperbarui.'
                : 'Jam operasional Loading/Unloading dan Surat Izin Kerja berhasil diperbarui.');
    }

    public function storeException(StoreOperatingScheduleExceptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isOpen = (bool) $data['is_open'];
        $is24Hours = $isOpen && (bool) $data['is_24_hours'];

        OperatingScheduleException::query()->create([
            'scope' => $data['scope'],
            'date' => $data['date'],
            'label' => $data['label'],
            'is_open' => $isOpen,
            'is_24_hours' => $is24Hours,
            'opens_at' => $isOpen && ! $is24Hours ? $data['opens_at'] : null,
            'closes_at' => $isOpen && ! $is24Hours ? $data['closes_at'] : null,
        ]);

        return redirect()->route('admin.schedule.edit')->with('success', 'Jadwal khusus berhasil ditambahkan.');
    }

    public function destroyException(OperatingScheduleException $exception): RedirectResponse
    {
        $exception->delete();

        return redirect()->route('admin.schedule.edit')->with('success', 'Jadwal khusus berhasil dihapus.');
    }
}
