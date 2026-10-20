<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use App\Models\WorkPermit;
use App\Services\QueueReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminQueueReminderController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $type = in_array($type, ['all', 'loading', 'work'], true) ? $type : 'all';
        $items = $this->items($type);
        $page = max(1, $request->integer('page', 1));
        $perPage = 30;

        return view('admin.queue-reminders.index', [
            'items' => new LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'type' => $type,
            'counts' => [
                'all' => $this->items('all')->count(),
                'loading' => $this->items('loading')->count(),
                'work' => $this->items('work')->count(),
            ],
        ]);
    }

    public function store(Request $request, QueueReminderService $reminders): RedirectResponse
    {
        $data = $request->validate([
            'selection_mode' => ['required', Rule::in(['selected', 'all'])],
            'queue_items' => [Rule::requiredIf($request->input('selection_mode') === 'selected'), 'array', 'max:100'],
            'queue_items.*' => ['string', 'regex:/^(loading|work):[1-9][0-9]*$/'],
            'type' => ['required', Rule::in(['all', 'loading', 'work'])],
            'message' => ['nullable', 'string', 'max:500'],
            'current_password' => ['required', 'current_password'],
        ], [
            'queue_items.required' => 'Pilih sedikitnya satu antrean.',
        ]);

        $items = $data['selection_mode'] === 'all'
            ? $this->items($data['type'])->take(500)
            : $this->selectedItems($data['queue_items'] ?? []);

        $sent = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $reminders->send($item['permit'], $data['message'] ?? null) ? $sent++ : $skipped++;
        }

        $message = "Pengingat dikirim untuk {$sent} antrean.";
        if ($skipped > 0) {
            $message .= " {$skipped} antrean dilewati karena baru diingatkan atau statusnya sudah berubah.";
        }

        return back()->with('success', $message);
    }

    private function items(string $type): Collection
    {
        $items = collect();
        if ($type !== 'work') {
            $items = $items->concat(LoadingPermit::query()->where('status', 'pending')->slaPriority()->limit(500)->get()->map(fn (LoadingPermit $permit): array => $this->item($permit)));
        }
        if ($type !== 'loading') {
            $items = $items->concat(WorkPermit::query()
                ->where(function ($query): void {
                    $query->whereIn('status', ['tr_review', 'mep_review', 'ep_review', 'payment_review', 'mep_final_review', 'refund_processing'])
                        ->orWhere(fn ($completed) => $completed->where('status', 'completed')->where('deposit_required', true));
                })
                ->slaPriority()->limit(500)->get()->map(fn (WorkPermit $permit): array => $this->item($permit)));
        }

        return $items->sortBy(fn (array $item) => $item['permit']->sla_started_at?->timestamp ?? PHP_INT_MAX)->values();
    }

    private function selectedItems(array $keys): Collection
    {
        return collect($keys)->unique()->map(function (string $key): ?array {
            [$type, $id] = explode(':', $key, 2);
            $permit = $type === 'loading' ? LoadingPermit::query()->find($id) : WorkPermit::query()->find($id);

            return $permit?->requiresStaffSlaAction() ? $this->item($permit) : null;
        })->filter()->values();
    }

    private function item(LoadingPermit|WorkPermit $permit): array
    {
        return [
            'key' => ($permit instanceof LoadingPermit ? 'loading' : 'work').':'.$permit->id,
            'type' => $permit instanceof LoadingPermit ? 'Loading / unloading' : 'Surat izin kerja',
            'division' => $permit instanceof LoadingPermit ? 'TR' : $permit->slaDivision(),
            'permit' => $permit,
        ];
    }
}
