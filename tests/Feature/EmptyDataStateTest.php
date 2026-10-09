<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmptyDataStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_tables_use_the_shared_empty_state(): void
    {
        $admin = $this->staff('admin');

        foreach ([
            [route('admin.dashboard'), 'Belum ada data loading barang'],
            [route('admin.loading.index'), 'Belum ada data loading barang'],
            [route('admin.validators.index'), 'Belum ada akun validator'],
        ] as [$url, $heading]) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('dt-empty-state', false)
                ->assertSee($heading);
        }

        $tr = $this->staff('validator', 'TR');
        $this->actingAs($tr)
            ->get(route('tr.index'))
            ->assertOk()
            ->assertSee('dt-empty-state', false)
            ->assertSee('Belum ada permohonan');

        $this->actingAs($tr)
            ->get(route('tr.work-permits.index'))
            ->assertOk()
            ->assertSee('dt-empty-state', false)
            ->assertSee('Belum ada izin kerja');

        $this->actingAs($this->staff('validator', 'MEP'))
            ->get(route('mep.index'))
            ->assertOk()
            ->assertSee('dt-empty-state', false)
            ->assertSee('Belum ada izin kerja');

        $this->actingAs($this->staff('validator', 'FIN'))
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('dt-empty-state', false)
            ->assertSee('Belum ada pembayaran');

        $this->actingAs($this->staff('secretary'))
            ->get(route('secretary.index'))
            ->assertOk()
            ->assertSee('dt-empty-state', false)
            ->assertSee('Belum ada permohonan');
    }

    private function staff(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
            'division' => $division,
        ]);
    }
}
