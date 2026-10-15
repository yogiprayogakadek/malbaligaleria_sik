<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminPermitSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_is_restricted_to_active_admins(): void
    {
        $this->get(route('admin.search'))->assertRedirect(route('login'));

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'phone' => '081211111111',
        ]);
        $this->actingAs($tenant)->get(route('admin.search'))->assertForbidden();
        $this->actingAs($tenant)->post(route('admin.search.store'), ['q' => 'MBG'])->assertForbidden();
    }

    public function test_admin_can_search_loading_by_number_tenant_pic_phone_and_status_label(): void
    {
        $admin = $this->admin();
        $permit = $this->loadingPermit();

        foreach (['MBG/LOAD/X/SEARCH-01', 'Toko Pencarian', 'Made Pemohon', '081234567890', 'Disetujui'] as $search) {
            $this->searchAs($admin, ['q' => $search])
                ->assertOk()
                ->assertSee($permit->permit_number)
                ->assertSee('Toko Pencarian');
        }

        $this->searchAs($admin, ['q' => 'Toko Pencarian'])
            ->assertSee('081****890')
            ->assertDontSee($permit->applicant_email ?? 'alamat-yang-tidak-ada');
    }

    public function test_admin_can_search_work_category_and_apply_type_and_status_filters(): void
    {
        $admin = $this->admin();
        $loading = $this->loadingPermit();
        $work = $this->workPermit();

        $this->searchAs($admin, [
            'q' => 'KOL Event',
            'type' => 'work',
            'status' => 'ep_review',
        ])
            ->assertOk()
            ->assertSee($work->permit_number)
            ->assertDontSee($loading->permit_number)
            ->assertSee('Pemeriksaan EP')
            ->assertDontSee($work->applicant_token);

        $this->searchAs($admin, [
            'q' => 'KOL Event',
            'type' => 'loading',
        ])->assertSee('Data tidak ditemukan');
    }

    public function test_search_rejects_short_or_wildcard_only_queries(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('admin.search'))
            ->post(route('admin.search.store'), ['q' => 'a'])
            ->assertRedirect(route('admin.search'))
            ->assertSessionHasErrors('q');

        $this->actingAs($admin)->from(route('admin.search'))
            ->post(route('admin.search.store'), ['q' => '%_'])
            ->assertRedirect(route('admin.search'))
            ->assertSessionHasErrors('q');

        $this->actingAs($admin)->get(route('admin.search', ['token' => 'tampered']))->assertNotFound();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'division' => null,
            'phone' => '081299999999',
        ]);
    }

    /** @param array<string, string> $filters */
    private function searchAs(User $admin, array $filters): TestResponse
    {
        $redirect = $this->actingAs($admin)->post(route('admin.search.store'), $filters)
            ->assertRedirect();
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringNotContainsString($filters['q'], urldecode($location));

        return $this->get($location);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/LOAD/X/SEARCH-01',
            'tenant_name' => 'Toko Pencarian',
            'applicant_name' => 'Made Pemohon',
            'applicant_phone' => '081234567890',
            'applicant_email' => null,
            'direction' => 'in',
            'movement_time' => '22:30',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'item_count' => 3,
            'item_unit' => 'koli',
            'id_doc_path' => 'permits/id-docs/search.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
        ]);
    }

    private function workPermit(): WorkPermit
    {
        return WorkPermit::create([
            'permit_number' => 'MBG/WORK/X/SEARCH-01',
            'contractor_name' => 'Kontraktor Kreatif',
            'applicant_name' => 'Komang PIC',
            'applicant_phone' => '081298765432',
            'applicant_email' => null,
            'work_location' => 'Atrium utama',
            'work_category' => 'kol_event',
            'assigned_division' => 'EP',
            'work_type' => 'Persiapan KOL Event',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_path' => 'permits/work/id-docs/search.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'ep_review',
        ]);
    }
}
