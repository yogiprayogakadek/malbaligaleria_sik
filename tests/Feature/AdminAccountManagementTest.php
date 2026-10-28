<?php

namespace Tests\Feature;

use App\Events\ValidatorAccountDeactivated;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_view_registered_accounts(): void
    {
        $admin = $this->admin();
        $tenant = User::factory()->create([
            'name' => 'Tenant Penguji',
            'role' => 'tenant',
            'phone' => '081234567801',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee('Daftar akun terdaftar')
            ->assertSee($tenant->email)
            ->assertSee('data-account-realtime="'.$admin->id.'"', false);

        $this->actingAs($tenant)
            ->get(route('admin.accounts.index'))
            ->assertForbidden();
    }

    public function test_admin_can_disable_account_and_revoke_every_session(): void
    {
        Event::fake([ValidatorAccountDeactivated::class]);
        $admin = $this->admin();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081234567802',
            'remember_token' => Str::random(40),
        ]);
        $this->insertSession($tenant);
        $this->insertSession($tenant);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.status', $tenant), ['is_active' => false])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertFalse($tenant->is_active);
        $this->assertNull($tenant->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $tenant->id]);
        Event::assertDispatched(ValidatorAccountDeactivated::class);
    }

    public function test_admin_can_reenable_an_account_without_restoring_old_sessions(): void
    {
        $admin = $this->admin();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081234567803',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.status', $tenant), ['is_active' => true])
            ->assertRedirect();

        $this->assertTrue($tenant->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $tenant->id]);
    }

    public function test_admin_cannot_disable_the_current_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.accounts.status', $admin), ['is_active' => false])
            ->assertSessionHasErrors('account');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_account_list_can_be_filtered_by_role_and_status(): void
    {
        $admin = $this->admin();
        $activeTenant = User::factory()->create([
            'name' => 'Tenant Aktif Dicari',
            'role' => 'tenant',
            'phone' => '081234567804',
        ]);
        User::factory()->create([
            'name' => 'Validator Tidak Cocok',
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081234567805',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index', [
                'q' => 'Dicari',
                'role' => 'tenant',
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee($activeTenant->name)
            ->assertDontSee('Validator Tidak Cocok');
    }

    public function test_admin_can_permanently_delete_an_unused_seed_account_and_revoke_its_sessions(): void
    {
        Event::fake([ValidatorAccountDeactivated::class]);
        $admin = $this->admin();
        $seedAccount = User::factory()->create([
            'name' => 'Akun Seeder Lama',
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081234567806',
            'is_active' => false,
        ]);
        $this->insertSession($seedAccount);

        $this->actingAs($admin)
            ->delete(route('admin.accounts.destroy', $seedAccount))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $seedAccount->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $seedAccount->id]);
        $this->assertTrue(AuditLog::query()
            ->where('event', 'deleted')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $seedAccount->id)
            ->exists());
        Event::assertDispatched(ValidatorAccountDeactivated::class);
    }

    public function test_admin_must_disable_an_account_before_permanent_deletion(): void
    {
        $admin = $this->admin();
        $activeAccount = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081234567807',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.accounts.destroy', $activeAccount))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $activeAccount->id]);
    }

    public function test_admin_cannot_delete_the_current_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.accounts.destroy', $admin))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'phone' => fake()->unique()->numerify('0812########'),
            'is_active' => true,
        ]);
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
    }
}
