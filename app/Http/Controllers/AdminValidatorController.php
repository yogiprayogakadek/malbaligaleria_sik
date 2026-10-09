<?php

namespace App\Http\Controllers;

use App\Events\ValidatorAccountDeactivated;
use App\Http\Requests\StoreValidatorRequest;
use App\Http\Requests\UpdateValidatorRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminValidatorController extends Controller
{
    public function index(Request $request): View
    {
        $allowedPerPage = [5, 10, 20, 50, 100];
        $perPageRaw = $request->query('per_page', 10);
        $showAll = $perPageRaw === 'all';
        $perPage = in_array((int) $perPageRaw, $allowedPerPage, true) ? (int) $perPageRaw : 10;
        $query = User::query()
            ->where('role', 'validator')
            ->orderBy('division')
            ->orderBy('name');

        $validators = $showAll
            ? (clone $query)->paginate((clone $query)->count() ?: 1)->withQueryString()
            : $query->paginate($perPage)->withQueryString();

        return view('admin.validators.index', compact('validators', 'perPageRaw'));
    }

    public function create(): View
    {
        return view('admin.validators.create');
    }

    public function store(StoreValidatorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'tenant_name' => null,
            'role' => 'validator',
            'division' => $data['division'],
            'is_active' => true,
            'must_change_password' => true,
            'password' => $data['password'],
        ]);

        return redirect()->route('admin.validators.index')
            ->with('success', "Akun validator {$data['division']} berhasil dibuat.");
    }

    public function edit(User $validator): View
    {
        $this->ensureValidator($validator);

        return view('admin.validators.edit', compact('validator'));
    }

    public function update(UpdateValidatorRequest $request, User $validator): RedirectResponse
    {
        $data = $request->validated();
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'division' => $data['division'],
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
            $attributes['must_change_password'] = true;
            $attributes['password_changed_at'] = null;
        }

        $validator->update($attributes);

        return redirect()->route('admin.validators.index')
            ->with('success', 'Data validator berhasil diperbarui.');
    }

    public function checkAvailability(Request $request, ?User $validator = null): JsonResponse
    {
        if ($validator) {
            $this->ensureValidator($validator);
        }

        $data = $request->validate([
            'field' => ['required', Rule::in(['email', 'phone'])],
            'value' => ['required', 'string', 'max:120'],
        ]);

        $field = $data['field'];
        $value = $field === 'email'
            ? strtolower(trim($data['value']))
            : trim($data['value']);
        $rules = $field === 'email'
            ? ['email:rfc', 'max:120']
            : ['min:9', 'max:20', 'regex:/^[0-9+\-\s()]+$/'];
        $rules[] = Rule::unique('users', $field)->ignore($validator?->id);

        $validation = validator([$field => $value], [$field => $rules]);
        $available = ! $validation->fails();

        return response()->json([
            'available' => $available,
            'message' => $available
                ? ($field === 'email' ? 'Email tersedia.' : 'Nomor telepon tersedia.')
                : $validation->errors()->first($field),
        ]);
    }

    public function updateStatus(Request $request, User $validator): RedirectResponse
    {
        $this->ensureValidator($validator);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $wasActive = $validator->is_active;
        $validator->update(['is_active' => $validated['is_active']]);

        if ($wasActive && ! $validator->is_active) {
            try {
                ValidatorAccountDeactivated::dispatch($validator);
            } catch (Throwable $exception) {
                Log::warning('Logout realtime validator gagal dikirim.', [
                    'validator_id' => $validator->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with(
            'success',
            $validated['is_active'] ? 'Akun validator diaktifkan.' : 'Akun validator dinonaktifkan.',
        );
    }

    private function ensureValidator(User $user): void
    {
        abort_unless($user->isValidator(), 404);
    }
}
