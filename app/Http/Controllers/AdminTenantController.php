<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTenantController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');
        abort_unless(in_array($status, ['active', 'inactive', 'all'], true), 404);

        $query = Tenant::query()->withCount('users')->orderBy('name');
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return view('admin.tenants.index', [
            'tenants' => $query->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('admin.tenants.form', [
            'tenant' => null,
            'tenantUsers' => User::query()->where('role', 'tenant')->whereNull('tenant_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $userId = $data['user_id'] ?? null;
        unset($data['user_id'], $data['current_password']);
        DB::transaction(function () use ($data, $userId): void {
            $tenant = Tenant::query()->create($data);
            $this->linkUser($tenant, $userId);
        });

        return redirect()->route('admin.tenants.index')->with('success', 'Data tenant berhasil ditambahkan.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.form', [
            'tenant' => $tenant,
            'tenantUsers' => User::query()->where('role', 'tenant')
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $this->validated($request, $tenant);
        $userId = $data['user_id'] ?? null;
        unset($data['user_id'], $data['current_password']);
        DB::transaction(function () use ($tenant, $data, $userId): void {
            $tenant->update($data);
            $this->linkUser($tenant, $userId);
        });

        return redirect()->route('admin.tenants.index')->with('success', 'Data tenant berhasil diperbarui.');
    }

    private function validated(Request $request, ?Tenant $tenant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'unit_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('tenants')->where(fn ($query) => $query->where('name', $request->input('name')))->ignore($tenant?->id),
            ],
            'floor' => ['nullable', 'string', 'max:50'],
            'pic_name' => ['nullable', 'string', 'max:120'],
            'pic_email' => ['nullable', 'email:rfc', 'max:120'],
            'pic_phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'is_active' => ['required', 'boolean'],
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($tenant): void {
                    $query->where('role', 'tenant')->where(function ($accountQuery) use ($tenant): void {
                        $accountQuery->whereNull('tenant_id');
                        if ($tenant) {
                            $accountQuery->orWhere('tenant_id', $tenant->id);
                        }
                    });
                }),
            ],
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Kata sandi admin tidak sesuai.',
        ]);
    }

    private function linkUser(Tenant $tenant, mixed $userId): void
    {
        User::query()->where('tenant_id', $tenant->id)->update(['tenant_id' => null]);

        if (! $userId) {
            return;
        }

        User::query()->whereKey($userId)->where('role', 'tenant')->update([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->displayName(),
        ]);
    }
}
