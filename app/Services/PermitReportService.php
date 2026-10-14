<?php

namespace App\Services;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class PermitReportService
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('record_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (stdClass $row): stdClass => $this->formatRow($row));
    }

    public function summary(array $filters): array
    {
        $query = $this->query($filters);
        $totals = (clone $query)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status_group = 'active' THEN 1 ELSE 0 END) AS active")
            ->selectRaw("SUM(CASE WHEN status_group = 'approved' THEN 1 ELSE 0 END) AS approved")
            ->selectRaw("SUM(CASE WHEN status_group = 'rejected' THEN 1 ELSE 0 END) AS rejected")
            ->first();

        $byType = (clone $query)
            ->select('permit_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('permit_type')
            ->pluck('total', 'permit_type');

        $byStatus = (clone $query)
            ->select('status', 'permit_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status', 'permit_type')
            ->orderByDesc('total')
            ->get()
            ->map(function (stdClass $row): stdClass {
                $row->label = $this->statusLabel($row->permit_type, $row->status);

                return $row;
            });

        return [
            'total' => (int) ($totals->total ?? 0),
            'active' => (int) ($totals->active ?? 0),
            'approved' => (int) ($totals->approved ?? 0),
            'rejected' => (int) ($totals->rejected ?? 0),
            'loading' => (int) ($byType['loading'] ?? 0),
            'work' => (int) ($byType['work'] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    public function trend(array $filters): Collection
    {
        $from = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);
        $monthly = $from->diffInDays($to) > 45;
        $expression = $monthly ? 'SUBSTR(created_at, 1, 7)' : 'DATE(created_at)';

        return $this->query($filters)
            ->selectRaw("{$expression} AS period_key")
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw($expression)
            ->orderBy('period_key')
            ->get()
            ->map(function (stdClass $row) use ($monthly): stdClass {
                $row->label = $monthly
                    ? Carbon::createFromFormat('Y-m', $row->period_key)->translatedFormat('M Y')
                    : Carbon::parse($row->period_key)->format('d M');
                $row->total = (int) $row->total;

                return $row;
            });
    }

    public function exportRows(array $filters, int $limit): array
    {
        $rows = $this->query($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('record_id')
            ->limit($limit + 1)
            ->get();
        $truncated = $rows->count() > $limit;

        return [
            $rows->take($limit)->map(fn (stdClass $row): stdClass => $this->formatRow($row)),
            $truncated,
        ];
    }

    private function query(array $filters): Builder
    {
        $loading = DB::table('loading_permits')
            ->select([
                'id as record_id',
                'permit_number',
                'permit_number as detail_key',
                'created_at',
                'start_date',
                'end_date',
                'status',
                'tenant_name as entity_name',
                'applicant_name',
                DB::raw("'loading' as permit_type"),
                'direction as subtype',
                DB::raw("'TR' as assigned_division"),
                DB::raw("CASE WHEN status = 'approved' THEN 'approved' WHEN status = 'rejected' THEN 'rejected' ELSE 'active' END as status_group"),
            ]);
        $work = DB::table('work_permits')
            ->select([
                'id as record_id',
                'permit_number',
                'public_token as detail_key',
                'created_at',
                'start_date',
                'end_date',
                'status',
                'contractor_name as entity_name',
                'applicant_name',
                DB::raw("'work' as permit_type"),
                'work_category as subtype',
                'assigned_division',
                DB::raw("CASE WHEN status IN ('approved', 'completed', 'refund_processing', 'refunded') THEN 'approved' WHEN status = 'rejected' THEN 'rejected' ELSE 'active' END as status_group"),
            ]);

        $this->applyFilters($loading, $filters, 'tenant_name');
        $this->applyFilters($work, $filters, 'contractor_name');

        $query = match ($filters['permit_type']) {
            'loading' => DB::query()->fromSub($loading, 'permit_report'),
            'work' => DB::query()->fromSub($work, 'permit_report'),
            default => DB::query()->fromSub($loading->unionAll($work), 'permit_report'),
        };

        return $query->when(
            $filters['status_group'] !== 'all',
            fn (Builder $builder): Builder => $builder->where('status_group', $filters['status_group']),
        );
    }

    private function applyFilters(Builder $query, array $filters, string $entityColumn): void
    {
        $query->whereBetween('created_at', [
            Carbon::parse($filters['date_from'])->startOfDay(),
            Carbon::parse($filters['date_to'])->endOfDay(),
        ]);

        if ($filters['search'] === '') {
            return;
        }

        $search = '%'.$filters['search'].'%';
        $query->where(function (Builder $searchQuery) use ($entityColumn, $search): void {
            $searchQuery
                ->where('permit_number', 'like', $search)
                ->orWhere($entityColumn, 'like', $search)
                ->orWhere('applicant_name', 'like', $search);
        });
    }

    private function formatRow(stdClass $row): stdClass
    {
        $row->created_at = Carbon::parse($row->created_at);
        $row->start_date = Carbon::parse($row->start_date);
        $row->end_date = Carbon::parse($row->end_date);
        $row->type_label = $row->permit_type === 'loading' ? 'Loading / Unloading' : 'Surat Izin Kerja';
        $row->subtype_label = $row->permit_type === 'loading'
            ? (new LoadingPermit(['direction' => $row->subtype]))->direction_label
            : (new WorkPermit(['work_category' => $row->subtype]))->work_category_label;
        $row->status_label = $this->statusLabel($row->permit_type, $row->status);

        return $row;
    }

    private function statusLabel(string $permitType, string $status): string
    {
        return $permitType === 'loading'
            ? (new LoadingPermit(['status' => $status]))->status_label
            : (new WorkPermit(['status' => $status]))->status_label;
    }
}
