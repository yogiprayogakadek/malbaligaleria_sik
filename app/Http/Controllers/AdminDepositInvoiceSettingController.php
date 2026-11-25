<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDepositInvoice;
use App\Models\DepositInvoiceSetting;
use App\Models\WorkPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDepositInvoiceSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.deposit-invoices', [
            'setting' => DepositInvoiceSetting::current()->load('accounts'),
            'backlogCount' => WorkPermit::query()->where('status', 'awaiting_invoice')->where('deposit_required', true)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in([DepositInvoiceSetting::MODE_MANUAL, DepositInvoiceSetting::MODE_AUTOMATIC])],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
            'accounts' => ['nullable', 'array', 'max:10'],
            'accounts.*.bank_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.account_number' => ['nullable', 'string', 'max:80'],
            'accounts.*.account_holder' => ['nullable', 'string', 'max:150'],
            'current_password' => ['required', 'current_password'],
        ], ['current_password.current_password' => 'Kata sandi admin tidak sesuai.']);

        $accounts = collect($data['accounts'] ?? [])
            ->map(fn (array $account): array => Arr::map($account, fn ($value) => trim((string) $value)))
            ->filter(fn (array $account): bool => collect($account)->filter()->isNotEmpty())
            ->values();

        foreach ($accounts as $index => $account) {
            if (blank($account['bank_name'] ?? null) || blank($account['account_number'] ?? null) || blank($account['account_holder'] ?? null)) {
                throw ValidationException::withMessages(["accounts.{$index}.bank_name" => 'Nama bank, nomor rekening, dan pemilik rekening wajib diisi lengkap.']);
            }
        }
        if ($data['mode'] === DepositInvoiceSetting::MODE_AUTOMATIC && $accounts->isEmpty()) {
            throw ValidationException::withMessages(['accounts' => 'Tambahkan minimal satu rekening sebelum mengaktifkan invoice otomatis.']);
        }

        $setting = DepositInvoiceSetting::current();
        $wasAutomatic = $setting->isAutomatic();
        DB::transaction(function () use ($setting, $data, $accounts): void {
            $setting->update([
                'mode' => $data['mode'],
                'payment_instructions' => trim((string) ($data['payment_instructions'] ?? '')) ?: null,
            ]);
            $setting->accounts()->delete();
            $accounts->each(fn (array $account, int $index) => $setting->accounts()->create([...$account, 'sort_order' => $index]));
        });

        if (! $wasAutomatic && $setting->fresh()->isAutomatic()) {
            WorkPermit::query()
                ->where('status', 'awaiting_invoice')
                ->where('deposit_required', true)
                ->whereNull('deposit_invoice_path')
                ->select('id')
                ->chunkById(100, fn ($permits) => $permits->each(fn (WorkPermit $permit) => GenerateDepositInvoice::dispatch($permit->id)->afterCommit()));
        }

        return redirect()->route('admin.settings.deposit-invoices.edit')->with('success', 'Pengaturan invoice deposit berhasil disimpan.');
    }
}
