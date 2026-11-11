<?php

namespace App\Services;

use App\Models\NationalHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

class NationalHolidaySyncService
{
    /**
     * @return array{years: array<int, int>, total: int, added: int, updated: int, removed: int}
     */
    public function sync(): array
    {
        $apiKey = (string) config('operating-hours.holidays.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Kunci API kalender hari libur belum dikonfigurasi.');
        }

        $currentYear = CarbonImmutable::now(config('operating-hours.timezone'))->year;
        $years = [$currentYear, $currentYear + 1];
        $syncedAt = now();
        $rows = [];

        foreach ($years as $year) {
            $response = Http::acceptJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 200)
                ->get((string) config('operating-hours.holidays.api_url'), ['tahun' => $year])
                ->throw();

            $holidays = $response->json('data');
            if (! is_array($holidays) || $holidays === []) {
                throw new UnexpectedValueException("API tidak mengembalikan kalender tahun {$year}.");
            }

            $yearHolidayCount = 0;

            foreach ($holidays as $holiday) {
                if (! is_array($holiday) || (bool) ($holiday['is_joint_leave'] ?? false) || ! (bool) ($holiday['is_active'] ?? true)) {
                    continue;
                }

                $rawDate = (string) ($holiday['date'] ?? '');
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $rawDate);
                $externalId = trim((string) ($holiday['id'] ?? ''));
                $name = trim((string) ($holiday['name'] ?? ''));

                if (! $date || $date->toDateString() !== $rawDate || $date->year !== $year || $externalId === '' || $name === '') {
                    throw new UnexpectedValueException("Data kalender tahun {$year} tidak valid.");
                }

                $rows[$externalId] = [
                    'external_id' => $externalId,
                    'date' => $date->toDateString(),
                    'name' => $name,
                    'source' => filled($holiday['source'] ?? null) ? (string) $holiday['source'] : null,
                    'year' => $year,
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
                $yearHolidayCount++;
            }

            if ($yearHolidayCount === 0) {
                throw new UnexpectedValueException("API tidak mengembalikan hari libur nasional tahun {$year}.");
            }
        }

        if ($rows === []) {
            throw new UnexpectedValueException('API tidak mengembalikan hari libur nasional.');
        }

        $existing = NationalHoliday::query()
            ->whereIn('year', $years)
            ->get()
            ->keyBy('external_id');
        $added = 0;
        $updated = 0;

        foreach ($rows as $externalId => $row) {
            $holiday = $existing->get($externalId);
            if (! $holiday) {
                $added++;

                continue;
            }

            if ($holiday->date->toDateString() !== $row['date'] || $holiday->name !== $row['name'] || $holiday->source !== $row['source']) {
                $updated++;
            }
        }

        $removed = DB::transaction(function () use ($rows, $years): int {
            $removed = NationalHoliday::query()
                ->whereIn('year', $years)
                ->whereNotIn('external_id', array_keys($rows))
                ->delete();

            NationalHoliday::query()->upsert(
                array_values($rows),
                ['external_id'],
                ['date', 'name', 'source', 'year', 'synced_at', 'updated_at'],
            );

            return $removed;
        });

        return [
            'years' => $years,
            'total' => count($rows),
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }
}
