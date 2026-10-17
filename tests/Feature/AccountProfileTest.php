<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_role_can_open_profile(): void
    {
        $users = [
            $this->user(['role' => 'tenant', 'phone' => '081200000201']),
            $this->user(['role' => 'admin', 'phone' => '081200000202']),
            $this->user(['role' => 'secretary', 'phone' => '081200000203']),
            $this->user(['role' => 'validator', 'division' => 'TR', 'phone' => '081200000204']),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get(route('account.profile.edit'))
                ->assertOk()
                ->assertSee('Profil Anda');
        }
    }

    public function test_user_can_update_profile_with_current_password(): void
    {
        $user = $this->user(['role' => 'tenant', 'phone' => '081200000205', 'tenant_name' => 'Tenant Lama']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Nama Baru',
            'tenant_name' => 'Tenant Baru',
            'email' => 'baru@example.test',
            'phone' => '+62 812-0000-0206',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('Tenant Baru', $user->tenant_name);
        $this->assertSame('baru@example.test', $user->email);
        $this->assertSame('6281200000206', $user->phone);
    }

    public function test_profile_update_rejects_wrong_password_and_duplicate_normalized_phone(): void
    {
        $user = $this->user(['phone' => '0812-0000-0207']);
        $this->user(['phone' => '6281200000208']);

        $this->actingAs($user)->from(route('account.profile.edit'))->put(route('account.profile.update'), [
            'name' => $user->name,
            'tenant_name' => $user->tenant_name,
            'email' => $user->email,
            'phone' => '+62 812 0000 0208',
            'current_password' => 'salah',
        ])->assertRedirect(route('account.profile.edit'))->assertSessionHasErrors(['phone', 'current_password']);
    }

    private function user(array $attributes): User
    {
        return User::factory()->create(array_merge([
            'tenant_name' => 'Tenant Uji',
            'password' => Hash::make('password'),
        ], $attributes));
    }
}
