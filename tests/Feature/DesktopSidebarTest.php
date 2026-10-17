<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminMenuCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_staff_and_admin_use_the_same_collapsible_desktop_sidebar(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081200000121',
        ]);
        $validator = User::factory()->create([
            'role' => 'validator',
            'division' => 'TR',
            'phone' => '081200000122',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200000123',
        ]);

        $pages = [
            [$tenant, route('portal.dashboard'), 'Permohonan'],
            [$validator, route('tr.index'), 'Verifikasi surat'],
            [$admin, route('admin.dashboard'), 'Pencarian perizinan'],
        ];

        foreach ($pages as [$user, $url, $menuLabel]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('data-desktop-sidebar', false)
                ->assertSee('data-sidebar-toggle', false)
                ->assertSee('class="topbar-btn topbar-sidebar-toggle"', false)
                ->assertSee('aria-controls="desktopSidebar"', false)
                ->assertSee('aria-expanded="false"', false)
                ->assertSee('<use href="#i-menu"/>', false)
                ->assertSee('class="nav-label"', false)
                ->assertSee($menuLabel)
                ->assertSee('class="mobile-nav', false)
                ->assertDontSee('nav-tooltip', false);
        }
    }

    public function test_sidebar_assets_support_persistence_scroll_and_expanded_labels(): void
    {
        $styles = file_get_contents(public_path('styles.css'));
        $script = file_get_contents(public_path('sidebar.js'));
        $layout = file_get_contents(resource_path('views/layouts/master.blade.php'));

        $this->assertStringContainsString('--sidebar-expanded-w: 264px', $styles);
        $this->assertStringContainsString('.sidebar-expanded .nav-label', $styles);
        $this->assertStringContainsString('overflow-y: auto', $styles);
        $this->assertStringContainsString('gap: 12px', $styles);
        $this->assertStringContainsString('.sidebar-expanded .sidebar-nav-heading', $styles);
        $this->assertStringContainsString('text-indent: -9999px', $styles);
        $this->assertStringContainsString('border-top: 1px solid var(--c-border)', $styles);
        $this->assertStringContainsString('min-height: 46px', $styles);
        $this->assertStringContainsString('.topbar-sidebar-toggle { display: none; }', $styles);
        $this->assertStringContainsString('mbg.desktop-sidebar-expanded', $script);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $script);
        $this->assertStringContainsString('window.localStorage.setItem', $script);
        $this->assertStringContainsString("asset('sidebar.js')", $layout);

        $adminLayout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $menuComponent = file_get_contents(resource_path('views/components/admin-menu-groups.blade.php'));
        $this->assertStringContainsString('<x-admin-menu-groups variant="sidebar"', $adminLayout);
        $this->assertStringContainsString('sidebar-nav-heading', $menuComponent);
        $this->assertSame(
            ['Perizinan', 'Komunikasi', 'Sistem', 'Akun'],
            collect(AdminMenuCatalog::groups())->pluck('label')->all(),
        );
    }

    public function test_admin_mobile_menu_exposes_all_navigation_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000299']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-menu-open', false)
            ->assertSee('id="adminMobileMenu"', false)
            ->assertSee('Konfigurasi WhatsApp')
            ->assertSee('Retensi dokumen')
            ->assertSee('Profil akun');
    }
}
