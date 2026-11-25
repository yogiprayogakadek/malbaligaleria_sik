<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AdminMenuCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_menu_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200008901']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Atur sidebar')
            ->assertSee('Panduan sistem')
            ->assertSee('Audit log');

        $this->actingAs($admin)->get(route('admin.settings.sidebar.edit'))
            ->assertOk()
            ->assertSee('Pilih menu sidebar')
            ->assertSee('Tampilkan semua')
            ->assertSee('Selalu tampil');
    }

    public function test_sidebar_preferences_are_personal_and_do_not_revoke_route_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200008902']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'phone' => '081200008903']);
        $hidden = ['admin.guide.index', 'admin.audit.index', 'admin.loading.index'];
        $visible = array_values(array_diff(AdminMenuCatalog::hideableRoutes(), $hidden));

        $this->actingAs($admin)->put(route('admin.settings.sidebar.update'), [
            'action' => 'save',
            'visible_routes' => $visible,
        ])->assertRedirect(route('admin.settings.sidebar.edit'));

        $admin->refresh();
        $this->assertEqualsCanonicalizing($hidden, $admin->admin_hidden_menu_routes);
        $this->assertTrue(AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $admin->id)
            ->where('event', 'updated')
            ->exists());

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertDontSee('Panduan sistem')
            ->assertDontSee('Audit log')
            ->assertDontSee('Data loading barang')
            ->assertSee('Atur sidebar')
            ->assertSee('>Loading</span>', false);

        $this->actingAs($otherAdmin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Panduan sistem')
            ->assertSee('Audit log')
            ->assertSee('Data loading barang');

        $this->actingAs($admin)->get(route('admin.guide.index'))
            ->assertOk()
            ->assertSee('Panduan sistem')
            ->assertSee('Audit log');
    }

    public function test_invalid_routes_are_ignored_and_all_menus_can_be_restored(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200008904']);

        $this->actingAs($admin)->put(route('admin.settings.sidebar.update'), [
            'action' => 'save',
            'visible_routes' => ['route.palsu', 'admin.settings.sidebar.edit'],
        ])->assertRedirect(route('admin.settings.sidebar.edit'));

        $admin->refresh();
        $this->assertNotContains('route.palsu', $admin->admin_hidden_menu_routes);
        $this->assertNotContains('admin.settings.sidebar.edit', $admin->admin_hidden_menu_routes);
        $this->assertEqualsCanonicalizing(AdminMenuCatalog::hideableRoutes(), $admin->admin_hidden_menu_routes);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('Atur sidebar')
            ->assertDontSee('sidebar-nav-heading">Audit', false);

        $this->actingAs($admin)->put(route('admin.settings.sidebar.update'), [
            'action' => 'show_all',
            'visible_routes' => [],
        ])->assertRedirect(route('admin.settings.sidebar.edit'));

        $this->assertSame([], $admin->fresh()->admin_hidden_menu_routes);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Panduan sistem')
            ->assertSee('Audit log');
    }

    public function test_non_admin_cannot_manage_sidebar_preferences(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200008905']);

        $this->actingAs($tenant)->get(route('admin.settings.sidebar.edit'))->assertForbidden();
        $this->actingAs($tenant)->put(route('admin.settings.sidebar.update'), [
            'action' => 'show_all',
        ])->assertForbidden();
    }
}
