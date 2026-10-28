<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function index(Request $request): View
    {
        $roles = ['admin', 'tenant', 'validator', 'secretary'];
        $statuses = ['active', 'inactive'];
        $allowedPerPage = [10, 20, 50, 100];
        $search = trim((string) $request->query('q', ''));
        $role = in_array($request->query('role'), $roles, true) ? $request->query('role') : null;
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : null;
        $division = array_key_exists((string) $request->query('division'), config('divisions'))
            ? $request->query('division')
            : null;
        $perPage = in_array((int) $request->query('per_page'), $allowedPerPage, true)
            ? (int) $request->query('per_page')
            : 20;

        $accounts = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('tenant_name', 'like', "%{$search}%");
                });
            })
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($division, fn ($query) => $query->where('division', $division))
            ->when($status, fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total' => User::query()->count(),
            'active' => User::query()->where('is_active', true)->count(),
            'inactive' => User::query()->where('is_active', false)->count(),
            'staff' => User::query()->whereIn('role', ['admin', 'validator', 'secretary'])->count(),
        ];

        return view('admin.accounts.index', compact(
            'accounts', 'summary', 'search', 'role', 'status', 'division', 'perPage',
        ));
    }

    public function updateStatus(
        Request $request,
        User $account,
        AccountStatusService $accountStatusService,
    ): RedirectResponse {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $account = $accountStatusService->update(
            $request->user(),
            $account,
            (bool) $validated['is_active'],
        );

        return back()->with(
            'success',
            $account->is_active
                ? "Akun {$account->name} berhasil diaktifkan."
                : "Akun {$account->name} dinonaktifkan dan seluruh sesinya dicabut.",
        );
    }

    public function destroy(
        Request $request,
        User $account,
        AccountStatusService $accountStatusService,
    ): RedirectResponse {
        $accountName = $account->name;
        $accountStatusService->delete($request->user(), $account);

        return redirect()->route('admin.accounts.index')
            ->with('success', "Akun {$accountName} berhasil dihapus permanen.");
    }
}
