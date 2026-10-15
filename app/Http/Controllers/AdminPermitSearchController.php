<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminPermitSearchController extends Controller
{
    private const STATUS_LABELS = [
        'pending' => 'Menunggu Verifikasi',
        'tr_review' => 'Pemeriksaan TR',
        'mep_review' => 'Pemeriksaan MEP',
        'ep_review' => 'Pemeriksaan EP',
        'awaiting_payment' => 'Menunggu Pembayaran',
        'payment_review' => 'Verifikasi Finance',
        'payment_revision' => 'Revisi Bukti Pembayaran',
        'revision_required' => 'Perlu Diperbaiki',
        'mep_final_review' => 'Pemeriksaan Akhir MEP',
        'approved' => 'Disetujui',
        'completed' => 'Pekerjaan Selesai',
        'refund_processing' => 'Pengembalian Diproses',
        'refunded' => 'Deposit Dikembalikan',
        'rejected' => 'Ditolak',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filtersFromToken($request->query('token'));
        $search = $filters['q'] ?? '';
        $type = $filters['type'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $results = null;

        if ($search !== '') {
            $results = $this->search($this->safeNeedle($search), $type, $status);
        }

        return view('admin.search.index', [
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'statusOptions' => self::STATUS_LABELS,
            'results' => $results,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $filters = $request->validate($this->rules(), $this->messages());
        $filters['q'] = trim($filters['q']);
        $this->safeNeedle($filters['q']);
        $filters['type'] ??= 'all';
        $filters['status'] ??= 'all';

        return redirect()->route('admin.search', [
            'token' => Crypt::encryptString(json_encode($filters, JSON_THROW_ON_ERROR)),
        ]);
    }

    private function search(string $search, string $type, string $status): LengthAwarePaginator
    {
        $loading = DB::table('loading_permits')->select([
            'permit_number',
            'tenant_name as entity_name',
            'applicant_name',
            'applicant_phone',
            'direction as category_code',
            'status',
            'start_date',
            'end_date',
            'created_at',
            DB::raw("'loading' as permit_type"),
            DB::raw("'TR' as assigned_division"),
            DB::raw('NULL as public_token'),
        ]);
        $work = DB::table('work_permits')->select([
            'permit_number',
            'contractor_name as entity_name',
            'applicant_name',
            'applicant_phone',
            'work_category as category_code',
            'status',
            'start_date',
            'end_date',
            'created_at',
            DB::raw("'work' as permit_type"),
            'assigned_division',
            'public_token',
        ]);

        $union = match ($type) {
            'loading' => $loading,
            'work' => $work,
            default => $loading->unionAll($work),
        };
        $query = DB::query()->fromSub($union, 'permit_search');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $lowerSearch = Str::lower($search);
        $matchingStatuses = collect(self::STATUS_LABELS)
            ->filter(fn (string $label, string $key): bool => str_contains(Str::lower($label.' '.$key), $lowerSearch))
            ->keys()
            ->all();
        $matchingCategories = collect(WorkPermit::WORK_CATEGORIES)
            ->filter(fn (string $label, string $key): bool => str_contains(Str::lower($label.' '.$key), $lowerSearch))
            ->keys()
            ->all();
        $pattern = '%'.$search.'%';

        $query->where(function ($searchQuery) use ($pattern, $matchingStatuses, $matchingCategories): void {
            $searchQuery
                ->where('permit_number', 'like', $pattern)
                ->orWhere('entity_name', 'like', $pattern)
                ->orWhere('applicant_name', 'like', $pattern)
                ->orWhere('applicant_phone', 'like', $pattern)
                ->orWhere('category_code', 'like', $pattern)
                ->orWhere('status', 'like', $pattern);

            if ($matchingStatuses !== []) {
                $searchQuery->orWhereIn('status', $matchingStatuses);
            }
            if ($matchingCategories !== []) {
                $searchQuery->orWhereIn('category_code', $matchingCategories);
            }
        });

        $results = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $results->setCollection($results->getCollection()->map(function (object $row): object {
            $row->created_at = Carbon::parse($row->created_at);
            $row->start_date = Carbon::parse($row->start_date);
            $row->end_date = Carbon::parse($row->end_date);
            $row->phone_masked = $this->maskPhone((string) $row->applicant_phone);

            if ($row->permit_type === 'loading') {
                $permit = new LoadingPermit(['status' => $row->status, 'direction' => $row->category_code]);
                $row->type_label = 'Loading / Unloading';
                $row->category_label = $permit->direction_label;
                $row->status_label = $permit->status_label;
                $row->detail_url = route('admin.loading.show', $row->permit_number);
            } else {
                $permit = new WorkPermit(['status' => $row->status, 'work_category' => $row->category_code]);
                $row->type_label = 'Surat Izin Kerja';
                $row->category_label = $permit->work_category_label;
                $row->status_label = $permit->status_label;
                $row->detail_url = route('staff.work-permits.show', $row->public_token);
            }

            unset($row->applicant_phone, $row->public_token);

            return $row;
        }));

        return $results;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) <= 6) {
            return str_repeat('*', max(4, strlen($digits)));
        }

        return substr($digits, 0, 3).'****'.substr($digits, -3);
    }

    /** @return array{q?: string, type?: string, status?: string} */
    private function filtersFromToken(mixed $token): array
    {
        if (! is_string($token) || $token === '') {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);

            return validator($payload, $this->rules(), $this->messages())->validate();
        } catch (Throwable) {
            abort(404);
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', Rule::in(['all', 'loading', 'work'])],
            'status' => ['nullable', Rule::in(['all', ...array_keys(self::STATUS_LABELS)])],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'q.required' => 'Masukkan kata yang ingin dicari.',
            'q.min' => 'Masukkan minimal 2 karakter untuk mencari.',
            'q.max' => 'Kata pencarian maksimal 100 karakter.',
        ];
    }

    private function safeNeedle(string $search): string
    {
        $needle = trim(str_replace(['%', '_'], '', $search));
        if (mb_strlen($needle) < 2) {
            throw ValidationException::withMessages([
                'q' => 'Masukkan minimal 2 karakter selain simbol pencarian.',
            ]);
        }

        return $needle;
    }
}
