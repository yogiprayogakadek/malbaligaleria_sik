<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $columns = [
        'users' => ['email_verified_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'loading_permits' => ['reviewed_at', 'barcode_expires_at', 'created_at', 'updated_at'],
        'permit_notifications' => ['read_at', 'created_at', 'updated_at'],
        'push_subscriptions' => ['created_at', 'updated_at'],
        'operating_schedules' => ['created_at', 'updated_at'],
        'mail_settings' => ['created_at', 'updated_at'],
        'work_permits' => [
            'deposit_set_at',
            'payment_submitted_at',
            'payment_verified_at',
            'completed_at',
            'refund_processed_at',
            'reviewed_at',
            'created_at',
            'updated_at',
        ],
        'work_permit_workers' => ['created_at', 'updated_at'],
        'work_permit_status_logs' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift(8);
    }

    public function down(): void
    {
        $this->shift(-8);
    }

    private function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        DB::transaction(function () use ($driver, $hours): void {
            foreach ($this->columns as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $availableColumns = array_values(array_filter(
                    $columns,
                    fn (string $column): bool => Schema::hasColumn($table, $column),
                ));

                if ($availableColumns === []) {
                    continue;
                }

                $updates = [];
                foreach ($availableColumns as $column) {
                    $updates[$column] = DB::raw($this->shiftExpression($driver, $column, $hours));
                }

                DB::table($table)->update($updates);
            }
        });
    }

    private function shiftExpression(string $driver, string $column, int $hours): string
    {
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        return match ($driver) {
            'sqlite' => sprintf("datetime(%s, '%+d hours')", $wrapped, $hours),
            'mysql', 'mariadb' => sprintf('DATE_ADD(%s, INTERVAL %d HOUR)', $wrapped, $hours),
            'pgsql' => sprintf("%s + INTERVAL '%d hours'", $wrapped, $hours),
            default => throw new RuntimeException("Driver database {$driver} tidak didukung untuk konversi timezone."),
        };
    }
};
