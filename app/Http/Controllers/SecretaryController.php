<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecretaryController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['all', 'loading', 'work', 'today'], true)
            ? $request->query('status')
            : 'all';
        $loadingStatuses = [
            'pending' => 'Menunggu Verifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
        $workStatuses = [
            'tr_review',
            'mep_review',
            'awaiting_payment',
            'payment_review',
            'payment_revision',
            'mep_final_review',
            'approved',
            'completed',
            'refund_processing',
            'refunded',
            'rejected',
        ];
        $validPermitStatuses = match ($status) {
            'loading' => array_keys($loadingStatuses),
            'work' => $workStatuses,
            default => array_values(array_unique([...array_keys($loadingStatuses), ...$workStatuses])),
        };
        $permitStatus = in_array($request->query('permit_status'), $validPermitStatuses, true)
            ? $request->query('permit_status')
            : 'all';
        $workStatusLabels = collect($workStatuses)
            ->mapWithKeys(fn (string $workStatus): array => [
                $workStatus => (new WorkPermit(['status' => $workStatus]))->status_label,
            ])->all();
        $statusGroups = match ($status) {
            'loading' => ['Status Loading / Unloading' => $loadingStatuses],
            'work' => ['Status Surat Izin Kerja' => $workStatusLabels],
            default => [
                'Loading / Unloading' => ['pending' => $loadingStatuses['pending']],
                'Surat Izin Kerja' => array_diff_key($workStatusLabels, array_flip(['approved', 'rejected'])),
                'Hasil Akhir' => [
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                ],
            ],
        };
        $today = now(config('operating-hours.timezone'))->toDateString();

        $loadingQuery = DB::table('loading_permits')->select([
            'id as record_id',
            'permit_number',
            'created_at',
            'start_date',
            'end_date',
            'status',
            'tenant_name as entity_name',
            'applicant_name',
            DB::raw("'loading' as permit_type"),
            'direction as subtype',
            DB::raw('NULL as division'),
            'item_count as related_count',
            DB::raw('NULL as public_token'),
        ]);
        $workQuery = DB::table('work_permits')->select([
            'id as record_id',
            'permit_number',
            'created_at',
            'start_date',
            'end_date',
            'status',
            'contractor_name as entity_name',
            'applicant_name',
            DB::raw("'work' as permit_type"),
            'work_category as subtype',
            'assigned_division as division',
            DB::raw('(select count(*) from work_permit_workers where work_permit_id = work_permits.id) as related_count'),
            'public_token',
        ]);

        if ($status === 'today') {
            $loadingQuery->whereDate('created_at', $today);
            $workQuery->whereDate('created_at', $today);
        }

        $union = match ($status) {
            'loading' => $loadingQuery,
            'work' => $workQuery,
            default => $loadingQuery->unionAll($workQuery),
        };
        $permitRowsQuery = DB::query()->fromSub($union, 'permit_rows');
        if ($permitStatus !== 'all') {
            $permitRowsQuery->where('status', $permitStatus);
        }

        $allowedPerPage = [5, 10, 20, 50, 100];
        $perPageRaw = $request->query('per_page', 10);
        $perPage = in_array((int) $perPageRaw, $allowedPerPage, true) ? (int) $perPageRaw : 10;
        if ($perPageRaw === 'all') {
            $perPage = max(1, (clone $permitRowsQuery)->count());
        }

        $permits = $permitRowsQuery
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $permits->setCollection($permits->getCollection()->map(function (object $row): object {
            $row->start_date = Carbon::parse($row->start_date);
            $row->end_date = Carbon::parse($row->end_date);
            $row->created_at = Carbon::parse($row->created_at);

            if ($row->permit_type === 'loading') {
                $model = new LoadingPermit(['status' => $row->status, 'direction' => $row->subtype]);
                $row->type_label = 'Loading / Unloading';
                $row->subtype_label = $model->direction_label;
                $row->status_label = $model->status_label;
                $row->detail_label = $row->related_count.' barang';
                $row->view_url = route('secretary.loading.show', $row->permit_number);
            } else {
                $model = new WorkPermit(['status' => $row->status, 'work_category' => $row->subtype]);
                $row->type_label = 'Surat Izin Kerja';
                $row->subtype_label = $model->work_category_label;
                $row->status_label = $model->status_label;
                $row->detail_label = $row->related_count.' pekerja';
                $row->view_url = route('staff.work-permits.show', $row->public_token);
            }

            return $row;
        }));

        return view('secretary.index', [
            'status' => $status,
            'permitStatus' => $permitStatus,
            'statusGroups' => $statusGroups,
            'permits' => $permits,
            'perPageRaw' => $perPageRaw,
        ]);
    }

    public function showLoading(string $permitNumber): View
    {
        $permit = LoadingPermit::query()
            ->where('permit_number', $permitNumber)
            ->with('reviewer')
            ->firstOrFail();

        return view('secretary.loading-show', compact('permit'));
    }

    public function readNotification(Request $request, PermitNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->markRead();

        if ($notification->workPermit) {
            return redirect()->route('staff.work-permits.show', $notification->workPermit->public_token);
        }

        return $notification->permit
            ? redirect()->route('secretary.loading.show', $notification->permit->permit_number)
            : redirect()->route('secretary.index');
    }
}
