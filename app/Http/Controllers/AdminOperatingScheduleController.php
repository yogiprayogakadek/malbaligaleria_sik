<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperatingScheduleExceptionRequest;
use App\Http\Requests\UpdateOperatingScheduleRequest;
use App\Models\OperatingSchedule;
use App\Models\OperatingScheduleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminOperatingScheduleController extends Controller
{
    public function edit()
    {
        return view('admin.schedule.edit', [
            'schedules' => OperatingSchedule::weekly(),
            'siteIsOpen' => OperatingSchedule::siteIsOpen(),
            'nextOpening' => OperatingSchedule::nextOpening(),
            'exceptions' => OperatingScheduleException::query()->whereDate('date', '>=', today())->orderBy('date')->take(20)->get(),
        ]);
    }

    public function update(UpdateOperatingScheduleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('days') as $day) {
                $isOpen = (bool) $day['is_open'];
                $is24Hours = $isOpen && (bool) $day['is_24_hours'];

                OperatingSchedule::query()->updateOrCreate(
                    ['day_of_week' => $day['day_of_week']],
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
            ->with('success', 'Jam operasional berhasil diperbarui.');
    }

    public function storeException(StoreOperatingScheduleExceptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isOpen = (bool) $data['is_open'];
        $is24Hours = $isOpen && (bool) $data['is_24_hours'];

        OperatingScheduleException::query()->create([
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
