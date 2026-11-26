<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\SecurityGateEvent;
use App\Models\WorkPermit;
use App\Services\PermitPdfService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecurityController extends Controller
{
    private const WORK_LETTER_STATUSES = ['approved', 'completed', 'refund_processing', 'refunded'];

    public function index(Request $request): View
    {
        $today = now()->toDateString();
        $status = in_array($request->query('status'), ['all', 'today'], true)
            ? (string) $request->query('status')
            : 'all';
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50], true)
            ? (int) $request->query('per_page')
            : 20;

        $loadingQuery = DB::table('loading_permits')
            ->select([
                'id',
                DB::raw("'loading' as permit_type"),
                'permit_number',
                DB::raw('tenant_name as entity_name'),
                DB::raw('item_description as work_summary'),
                DB::raw('NULL as work_location'),
                'start_date',
                'end_date',
                'status',
                DB::raw('barcode_token as scan_token'),
                'item_count',
                'reviewed_at',
                'created_at',
            ])
            ->where('status', 'approved')
            ->whereNull('revoked_at');
        $workQuery = DB::table('work_permits')
            ->select([
                'id',
                DB::raw("CASE WHEN permit_kind = 'event' THEN 'event' ELSE 'work' END as permit_type"),
                'permit_number',
                DB::raw('contractor_name as entity_name'),
                DB::raw('work_type as work_summary'),
                'work_location',
                'start_date',
                'end_date',
                'status',
                DB::raw('COALESCE(official_scan_token, public_token) as scan_token'),
                DB::raw('NULL as item_count'),
                'reviewed_at',
                'created_at',
            ])
            ->whereIn('status', self::WORK_LETTER_STATUSES)
            ->whereNull('revoked_at');

        if ($status === 'today') {
            foreach ([$loadingQuery, $workQuery] as $permitQuery) {
                $permitQuery->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today);
            }
        }
        if ($query !== '') {
            $needle = '%'.mb_strtolower($query).'%';
            $loadingQuery->where(fn ($search) => $search
                ->whereRaw('LOWER(permit_number) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(tenant_name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(item_description) LIKE ?', [$needle]));
            $workQuery->where(fn ($search) => $search
                ->whereRaw('LOWER(permit_number) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(contractor_name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(work_type) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(work_location) LIKE ?', [$needle]));
        }

        $permits = DB::query()
            ->fromSub($loadingQuery->unionAll($workQuery), 'validated_permits')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $loadingIds = $permits->getCollection()
            ->where('permit_type', 'loading')
            ->pluck('id');
        $workIds = $permits->getCollection()
            ->whereIn('permit_type', ['work', 'event'])
            ->pluck('id');
        $loadingProgress = LoadingPermit::query()
            ->with('gateEvents')
            ->whereIn('id', $loadingIds)
            ->get()
            ->mapWithKeys(fn (LoadingPermit $permit): array => [$permit->id => $permit->outboundProgress()]);
        $workMeta = WorkPermit::query()
            ->whereIn('id', $workIds)
            ->get()
            ->keyBy('id');
        $permits->getCollection()->transform(function (object $permit) use ($loadingProgress, $workMeta, $today): object {
            $isCurrent = Carbon::parse($permit->start_date)->toDateString() <= $today
                && Carbon::parse($permit->end_date)->toDateString() >= $today;
            $isCompletedLoading = $permit->permit_type === 'loading'
                && (bool) data_get($loadingProgress->get($permit->id), 'completed', false);
            $workRecord = $permit->permit_type === 'loading' ? null : $workMeta->get($permit->id);
            $permit->work_details = $workRecord
                ? collect([
                    ! $workRecord->isEventPermit() && $workRecord->work_category === 'other'
                        ? $workRecord->other_work_type_label
                        : null,
                    filled($workRecord->notes) ? trim((string) $workRecord->notes) : null,
                ])->filter()->unique()->implode(' · ')
                : null;
            $permit->can_verify = $isCurrent
                && ! $isCompletedLoading
                && ($permit->permit_type === 'loading' || $permit->status === 'approved');
            $permit->status_label = match (true) {
                $isCompletedLoading => 'Selesai',
                $permit->status === 'completed' => 'Pekerjaan Selesai',
                $permit->status === 'refund_processing' => 'Pengembalian Diproses',
                $permit->status === 'refunded' => 'Deposit Dikembalikan',
                Carbon::parse($permit->end_date)->toDateString() < $today => 'Kedaluwarsa',
                Carbon::parse($permit->start_date)->toDateString() > $today => 'Akan Berlaku',
                default => 'Aktif',
            };

            return $permit;
        });

        $gateEvents = SecurityGateEvent::query()->with('user')->latest('occurred_at')->limit(30)->get();
        $loadingNumbers = LoadingPermit::query()->whereIn('id', $gateEvents->where('permit_type', 'loading')->pluck('permit_id'))->pluck('permit_number', 'id');
        $workNumbers = WorkPermit::query()->whereIn('id', $gateEvents->where('permit_type', 'work')->pluck('permit_id'))->pluck('permit_number', 'id');

        $gateEvents->each(function (SecurityGateEvent $event) use ($loadingNumbers, $workNumbers): void {
            $event->display_reference = $event->permit_type === 'loading'
                ? $loadingNumbers->get($event->permit_id)
                : $workNumbers->get($event->permit_id);
        });

        return view('security.index', [
            'permits' => $permits,
            'status' => $status,
            'query' => $query,
            'perPage' => $perPage,
            'validatedPermitCount' => LoadingPermit::query()->where('status', 'approved')->whereNull('revoked_at')->count()
                + WorkPermit::query()->whereIn('status', self::WORK_LETTER_STATUSES)->whereNull('revoked_at')->count(),
            'todayPermitCount' => LoadingPermit::query()->where('status', 'approved')->whereNull('revoked_at')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count()
                + WorkPermit::query()->whereIn('status', self::WORK_LETTER_STATUSES)->whereNull('revoked_at')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
            'gateEvents' => $gateEvents,
            'checkedInCount' => SecurityGateEvent::query()->whereDate('occurred_at', $today)->where('event', 'check_in')->count(),
            'checkedOutCount' => SecurityGateEvent::query()->whereDate('occurred_at', $today)->where('event', 'check_out')->count(),
        ]);
    }

    public function downloadLoadingPermit(LoadingPermit $permit, PermitPdfService $pdf): Response
    {
        abort_if($permit->revoked_at, 404);
        abort_unless($permit->status === 'approved', 404);
        if (! $permit->barcode_token) {
            $permit->generateBarcodeToken();
        }

        return $pdf->loadingPermit($permit);
    }

    public function downloadWorkPermit(WorkPermit $permit, PermitPdfService $pdf): Response
    {
        abort_if($permit->revoked_at, 404);
        abort_unless(in_array($permit->status, self::WORK_LETTER_STATUSES, true), 404);

        return $pdf->workPermit($permit);
    }

    public function readNotification(Request $request, PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->markRead();

        return redirect()->route('security.index');
    }
}
