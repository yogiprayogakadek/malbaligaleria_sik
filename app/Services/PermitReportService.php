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
            'event' => (int) ($byType['event'] ?? 0),
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

    public function processingMedians(array $filters): Collection
    {
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->endOfDay();
        $samples = collect([
            'TR' => collect(),
            'MEP' => collect(),
            'EP' => collect(),
            'FIN' => collect(),
        ]);

        $loadingPermits = DB::table('loading_permits')
            ->whereNotNull('reviewed_at')
            ->whereBetween('reviewed_at', [$from, $to])
            ->get(['id', 'created_at', 'reviewed_at']);
        $loadingRevisionStarts = DB::table('permit_revisions')
            ->where('revisable_type', (new LoadingPermit)->getMorphClass())
            ->whereIn('revisable_id', $loadingPermits->pluck('id'))
            ->whereNotNull('resolved_at')
            ->select('revisable_id')
            ->selectRaw('MAX(resolved_at) AS resolved_at')
            ->groupBy('revisable_id')
            ->pluck('resolved_at', 'revisable_id');
        $loadingPermits->each(function (stdClass $permit) use ($loadingRevisionStarts, $samples): void {
            $start = $loadingRevisionStarts[$permit->id] ?? $permit->created_at;
            $this->addDuration($samples['TR'], $start, $permit->reviewed_at);
        });

        $actions = [
            'tr_decision' => 'TR',
            'deposit_requested' => 'MEP',
            'approved_without_deposit' => 'MEP',
            'mep_final_decision' => 'MEP',
            'ep_decision' => 'EP',
            'payment_verified' => 'FIN',
            'payment_revision_requested' => 'FIN',
        ];
        $decisions = DB::table('work_permit_status_logs as decision_logs')
            ->join('work_permits', 'work_permits.id', '=', 'decision_logs.work_permit_id')
            ->whereIn('decision_logs.action', array_keys($actions))
            ->whereBetween('decision_logs.created_at', [$from, $to])
            ->get([
                'decision_logs.id',
                'decision_logs.work_permit_id',
                'decision_logs.action',
                'decision_logs.created_at as decided_at',
                'work_permits.created_at as submitted_at',
            ]);
        $histories = DB::table('work_permit_status_logs')
            ->whereIn('work_permit_id', $decisions->pluck('work_permit_id')->unique())
            ->whereIn('action', ['payment_proof_uploaded', 'payment_verified', 'revision_submitted'])
            ->orderBy('id')
            ->get(['id', 'work_permit_id', 'action', 'created_at'])
            ->groupBy('work_permit_id');

        $decisions->each(function (stdClass $decision) use ($actions, $histories, $samples): void {
            $start = $decision->submitted_at;
            $priorHistory = $histories->get($decision->work_permit_id, collect())
                ->where('id', '<', $decision->id);
            $start = match ($decision->action) {
                'payment_verified', 'payment_revision_requested' => $priorHistory->where('action', 'payment_proof_uploaded')->last()?->created_at,
                'mep_final_decision' => $priorHistory->whereIn('action', ['payment_verified', 'revision_submitted'])->last()?->created_at,
                default => $priorHistory->where('action', 'revision_submitted')->last()?->created_at ?? $start,
            };
            if ($start) {
                $this->addDuration($samples[$actions[$decision->action]], $start, $decision->decided_at);
            }
        });

        return collect([
            ['division' => 'TR', 'label' => 'Tenant Relationship', 'description' => 'Loading/unloading dan izin kerja kategori TR'],
            ['division' => 'MEP', 'label' => 'Engineering', 'description' => 'Keputusan awal deposit dan keputusan akhir Engineering'],
            ['division' => 'EP', 'label' => 'Event Promotion', 'description' => 'Keputusan Surat Izin Event'],
            ['division' => 'FIN', 'label' => 'Finance', 'description' => 'Verifikasi bukti pembayaran deposit'],
        ])->map(function (array $row) use ($samples): array {
            $divisionSamples = $samples[$row['division']]->sort()->values();
            $median = $this->median($divisionSamples);

            return [
                ...$row,
                'sample_count' => $divisionSamples->count(),
                'median_seconds' => $median,
                'median_label' => $median === null ? 'Belum ada data' : $this->durationLabel($median),
            ];
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
        $workBase = DB::table('work_permits')
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
                'permit_kind as permit_type',
                'work_category as subtype',
                'assigned_division',
                DB::raw("CASE WHEN status IN ('approved', 'completed', 'refund_processing', 'refunded') THEN 'approved' WHEN status = 'rejected' THEN 'rejected' ELSE 'active' END as status_group"),
            ]);

        $this->applyFilters($loading, $filters, 'tenant_name');
        $this->applyFilters($workBase, $filters, 'contractor_name');
        $work = (clone $workBase)->where('permit_kind', 'work');
        $event = (clone $workBase)->where('permit_kind', 'event');

        $query = match ($filters['permit_type']) {
            'loading' => DB::query()->fromSub($loading, 'permit_report'),
            'work' => DB::query()->fromSub($work, 'permit_report'),
            'event' => DB::query()->fromSub($event, 'permit_report'),
            default => DB::query()->fromSub($loading->unionAll($work)->unionAll($event), 'permit_report'),
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
        $row->type_label = match ($row->permit_type) {
            'loading' => 'Loading / Unloading',
            'event' => 'Surat Izin Event',
            default => 'Surat Izin Kerja',
        };
        $row->subtype_label = $row->permit_type === 'loading'
            ? (new LoadingPermit(['direction' => $row->subtype]))->direction_label
            : (new WorkPermit(['permit_kind' => $row->permit_type, 'work_category' => $row->subtype]))->work_category_label;
        $row->status_label = $this->statusLabel($row->permit_type, $row->status);

        return $row;
    }

    private function statusLabel(string $permitType, string $status): string
    {
        return $permitType === 'loading'
            ? (new LoadingPermit(['status' => $status]))->status_label
            : (new WorkPermit(['status' => $status]))->status_label;
    }

    private function addDuration(Collection $samples, mixed $startedAt, mixed $endedAt): void
    {
        $seconds = Carbon::parse($startedAt)->diffInSeconds(Carbon::parse($endedAt), false);
        if ($seconds >= 0) {
            $samples->push((int) $seconds);
        }
    }

    private function median(Collection $values): ?int
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (int) $values[$middle]
            : (int) round(((int) $values[$middle - 1] + (int) $values[$middle]) / 2);
    }

    private function durationLabel(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = max(1, intdiv($seconds % 3600, 60));

        return collect([
            $days > 0 ? "{$days} hari" : null,
            $hours > 0 ? "{$hours} jam" : null,
            $minutes > 0 && $days === 0 ? "{$minutes} menit" : null,
        ])->filter()->implode(' ');
    }
}
