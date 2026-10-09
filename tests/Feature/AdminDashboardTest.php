<?php

namespace Tests\Feature;

use App\Events\ValidatorAccountDeactivated;
use App\Models\LoadingPermit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_open_admin_dashboard(): void
    {
        $admin = $this->user('admin');
        $tenant = $this->user('tenant');
        $validator = $this->user('validator', 'TR');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Kendali operasional perizinan');
        $this->actingAs($tenant)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($validator)->get(route('admin.dashboard'))->assertForbidden();

        $admin->update(['is_active' => false]);
        $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_create_validator_but_cannot_control_the_role_from_input(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.validators.store'), [
            'name' => 'Validator Security',
            'email' => 'VALIDATOR@EXAMPLE.TEST',
            'phone' => '081234567890',
            'division' => 'ep',
            'role' => 'admin',
            'password' => 'Strong!Pass123',
            'password_confirmation' => 'Strong!Pass123',
        ])->assertRedirect(route('admin.validators.index'));

        $validator = User::where('email', 'validator@example.test')->firstOrFail();
        $this->assertSame('validator', $validator->role);
        $this->assertSame('EP', $validator->division);
        $this->assertTrue($validator->is_active);
        $this->assertTrue($validator->must_change_password);
        $this->assertTrue(Hash::check('Strong!Pass123', $validator->password));
    }

    public function test_weak_validator_password_is_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.validators.store'), [
            'name' => 'Validator Lemah',
            'email' => 'weak@example.test',
            'phone' => '081234567891',
            'division' => 'TR',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.test']);
    }

    public function test_admin_can_deactivate_only_validator_accounts(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', 'MEP');
        $otherAdmin = $this->user('admin');
        Event::fake([ValidatorAccountDeactivated::class]);

        $this->actingAs($admin)
            ->patch(route('admin.validators.status', $validator), ['is_active' => false])
            ->assertRedirect();
        $this->assertFalse($validator->fresh()->is_active);
        Event::assertDispatched(ValidatorAccountDeactivated::class, fn ($event) => $event->validator->is($validator));

        $this->actingAs($admin)
            ->patch(route('admin.validators.status', $otherAdmin), ['is_active' => false])
            ->assertNotFound();
        $this->assertTrue($otherAdmin->fresh()->is_active);
    }

    public function test_validator_management_uses_separate_pages_and_can_update_data(): void
    {
        $admin = $this->user('admin');
        $validator = $this->user('validator', 'TR');
        $originalPassword = $validator->password;

        $this->actingAs($admin)
            ->get(route('admin.validators.index'))
            ->assertOk()
            ->assertSee('validatorDataTable')
            ->assertSee('Cari nama, email, telepon')
            ->assertSee(route('admin.validators.create'))
            ->assertSee(route('admin.validators.edit', $validator));

        $this->actingAs($admin)
            ->get(route('admin.validators.create'))
            ->assertOk()
            ->assertSee('Tambah akun validator');

        $this->actingAs($admin)
            ->get(route('admin.validators.edit', $validator))
            ->assertOk()
            ->assertSee('Edit akun validator');

        $this->actingAs($admin)
            ->put(route('admin.validators.update', $validator), [
                'name' => 'Validator Diperbarui',
                'email' => 'updated@example.test',
                'phone' => '081234567899',
                'division' => 'MEP',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.validators.index'));

        $validator->refresh();
        $this->assertSame('Validator Diperbarui', $validator->name);
        $this->assertSame('MEP', $validator->division);
        $this->assertSame($originalPassword, $validator->password);
    }

    public function test_realtime_availability_check_is_admin_only_and_ignores_current_validator(): void
    {
        $admin = $this->user('admin');
        $tenant = $this->user('tenant');
        $validator = $this->user('validator', 'TR');

        $this->actingAs($admin)
            ->postJson(route('admin.validators.availability'), [
                'field' => 'email',
                'value' => $validator->email,
            ])
            ->assertOk()
            ->assertJson(['available' => false]);

        $this->actingAs($admin)
            ->postJson(route('admin.validators.availability.edit', $validator), [
                'field' => 'email',
                'value' => $validator->email,
            ])
            ->assertOk()
            ->assertJson(['available' => true]);

        $this->actingAs($tenant)
            ->postJson(route('admin.validators.availability'), [
                'field' => 'email',
                'value' => 'new@example.test',
            ])
            ->assertForbidden();
    }

    public function test_loading_menu_separates_statuses_and_protects_detail(): void
    {
        $admin = $this->user('admin');
        $tenant = $this->user('tenant');
        $permit = $this->permit('approved');

        $this->actingAs($admin)
            ->get(route('admin.loading.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Permohonan disetujui')
            ->assertSee($permit->permit_number);

        $this->actingAs($admin)->get(route('admin.loading.show', $permit->permit_number))->assertOk();
        $this->actingAs($tenant)->get(route('admin.loading.show', $permit->permit_number))->assertForbidden();
    }

    public function test_inactive_account_cannot_login_and_logout_requires_post(): void
    {
        $admin = $this->user('admin');
        $admin->update(['is_active' => false, 'password' => 'Strong!Pass123']);

        $this->post(route('login.post'), [
            'login' => $admin->email,
            'password' => 'Strong!Pass123',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_existing_session_is_terminated_after_account_is_deactivated(): void
    {
        $validator = $this->user('validator', 'TR');
        $validator->update(['is_active' => false]);

        $this->actingAs($validator)
            ->get(route('tr.notifications.feed'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    private function user(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
            'division' => $division,
        ]);
    }

    private function permit(string $status): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/ADMIN/0001',
            'tenant_name' => 'Tenant Admin Test',
            'applicant_name' => 'Pemohon Admin',
            'applicant_phone' => '081200000001',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian admin',
            'id_doc_path' => 'permits/id-docs/admin-test.jpg',
            'id_doc_type' => 'ktp',
            'status' => $status,
        ]);
    }
}
