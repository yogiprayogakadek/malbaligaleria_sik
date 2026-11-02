<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_frequent_operational_queries_have_composite_indexes(): void
    {
        $this->assertTableHasIndex('loading_permits', 'loading_status_sla_idx');
        $this->assertTableHasIndex('work_permits', 'work_kind_status_sla_idx');
        $this->assertTableHasIndex('users', 'users_role_div_active_idx');
    }

    private function assertTableHasIndex(string $table, string $index): void
    {
        $indexes = collect(Schema::getIndexes($table))->pluck('name');

        $this->assertTrue($indexes->contains($index), "Indeks {$index} tidak ditemukan pada tabel {$table}.");
    }
}
