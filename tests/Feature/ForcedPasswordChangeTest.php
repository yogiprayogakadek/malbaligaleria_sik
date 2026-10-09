<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_accounts_receive_a_temporary_password_without_resetting_existing_accounts(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@malbaligaleria.com')->firstOrFail();
        $finance = User::where('email', 'validator.finance@malbaligaleria.com')->firstOrFail();

        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($finance->must_change_password);
        $this->assertTrue(Hash::check('password123', $admin->password));
        $this->assertSame('FIN', $finance->division);

        $admin->update([
            'password' => 'Personal!Password456',
            'must_change_password' => false,
        ]);

        $this->seed();

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('Personal!Password456', $admin->password));
    }

    public function test_seeded_user_is_redirected_after_login_and_cannot_bypass_the_password_page(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@malbaligaleria.com')->firstOrFail();

        $this->post(route('login.post'), [
            'login' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('password.required.edit'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertRedirect(route('password.required.edit'));
        $this->get(route('portal.dashboard'))->assertRedirect(route('password.required.edit'));
        $this->get(route('password.required.edit'))
            ->assertOk()
            ->assertSee('Ganti kata sandi awal')
            ->assertDontSee('Lanjut tanpa menggunakan akun');

        $this->getJson(route('admin.dashboard'))
            ->assertStatus(409)
            ->assertJsonPath('redirect', route('password.required.edit'));
    }

    public function test_password_change_requires_the_current_password_and_a_strong_new_password(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200000090',
            'password' => 'Temporary!123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put(route('password.required.update'), [
            'current_password' => 'salah',
            'password' => 'NewSecure!Password456',
            'password_confirmation' => 'NewSecure!Password456',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('password.required.update'), [
            'current_password' => 'Temporary!123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_valid_password_change_unlocks_the_account_and_invalidates_the_temporary_password(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200000091',
            'password' => 'Temporary!123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put(route('password.required.update'), [
            'current_password' => 'Temporary!123',
            'password' => 'NewSecure!Password456',
            'password_confirmation' => 'NewSecure!Password456',
        ])->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NewSecure!Password456', $user->password));
        $this->assertFalse(Hash::check('Temporary!123', $user->password));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_user_without_the_flag_cannot_reopen_the_required_password_form(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200000092',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('password.required.edit'))
            ->assertRedirect(route('admin.dashboard'));
    }
}
