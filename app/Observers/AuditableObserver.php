<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'api_key',
        'document_token',
        'public_token',
        'applicant_token',
        'barcode_token',
        'id_doc_path',
        'payment_proof_path',
        'refund_proof_path',
        'assignment_letter_path',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_last_used_step',
        'signing_secret',
        'webhook_url',
    ];

    private const IGNORED_FIELDS = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), self::IGNORED_FIELDS);
        if ($changes === []) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $model->getRawOriginal($key);
        }

        $this->record($model, 'updated', $oldValues, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), []);
    }

    private function record(Model $model, string $event, array $oldValues, array $newValues): void
    {
        $request = app()->runningInConsole() ? null : request();

        AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'route_name' => $request?->route()?->getName(),
            'request_method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
        ]);
    }

    private function sanitize(array $values): array
    {
        $values = Arr::except($values, self::IGNORED_FIELDS);

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[dilindungi]';
            }
        }

        return $values;
    }
}
