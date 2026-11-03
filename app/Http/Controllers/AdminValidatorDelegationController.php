<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ValidatorDelegation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminValidatorDelegationController extends Controller
{
    public function index(): View
    {
        return view('admin.validator-delegations.index', [
            'delegations' => ValidatorDelegation::query()
                ->with(['validator', 'creator', 'revoker'])
                ->orderByRaw('CASE WHEN revoked_at IS NULL AND starts_on <= ? AND ends_on >= ? THEN 0 ELSE 1 END', [today(), today()])
                ->latest('ends_on')
                ->paginate(20),
            'validators' => User::query()
                ->where('role', 'validator')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'division']),
            'divisions' => collect(ValidatorDelegation::DIVISIONS)
                ->mapWithKeys(fn (string $division): array => [$division => ValidatorDelegation::divisionLabel($division)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'validator_id' => ['required', 'integer', 'exists:users,id'],
            'division' => ['required', Rule::in(ValidatorDelegation::DIVISIONS)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'current_password'],
        ]);
        $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
        $endsOn = Carbon::parse($data['ends_on'])->startOfDay();
        if ($startsOn->diffInDays($endsOn) > 90) {
            throw ValidationException::withMessages(['ends_on' => 'Masa delegasi maksimal 90 hari kalender.']);
        }

        DB::transaction(function () use ($request, $data): void {
            $validator = User::query()->lockForUpdate()->findOrFail($data['validator_id']);
            if (! $validator->is_active || ! $validator->isValidator()) {
                throw ValidationException::withMessages(['validator_id' => 'Pilih akun validator yang masih aktif.']);
            }
            if ($validator->division === $data['division']) {
                throw ValidationException::withMessages(['division' => 'Akun tersebut sudah memiliki akses tetap ke divisi ini.']);
            }

            $overlaps = ValidatorDelegation::query()
                ->where('validator_id', $validator->id)
                ->where('division', $data['division'])
                ->whereNull('revoked_at')
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->lockForUpdate()
                ->exists();
            if ($overlaps) {
                throw ValidationException::withMessages(['starts_on' => 'Periode delegasi bertumpuk dengan delegasi yang sudah ada.']);
            }

            ValidatorDelegation::query()->create([
                'validator_id' => $data['validator_id'],
                'division' => $data['division'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'reason' => $data['reason'],
                'created_by' => $request->user()->id,
            ]);
        }, 3);

        return back()->with('success', 'Validator pengganti berhasil dijadwalkan. Akses aktif otomatis sesuai periode.');
    }

    public function revoke(Request $request, ValidatorDelegation $validatorDelegation): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        DB::transaction(function () use ($request, $validatorDelegation): void {
            $delegation = ValidatorDelegation::query()->lockForUpdate()->findOrFail($validatorDelegation->id);
            abort_if($delegation->revoked_at, 409, 'Delegasi ini sudah dihentikan.');
            $delegation->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Akses validator pengganti telah dihentikan.');
    }
}
