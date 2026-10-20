<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailMessageLog;
use App\Models\LoadingPermit;
use App\Models\PermitNumberSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDataMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_data_maintenance_and_number_settings(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200009001']);
        $admin = $this->admin();

        $this->actingAs($tenant)->get(route('admin.data-maintenance.index'))->assertForbidden();
        $this->actingAs($tenant)->get(route('admin.settings.permit-numbers.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.data-maintenance.index'))->assertOk()->assertSee('Penghapusan data operasional');
        $this->actingAs($admin)->get(route('admin.settings.permit-numbers.edit'))->assertOk()->assertSee('Format nomor surat');
    }

    public function test_admin_can_change_number_pattern_with_restricted_placeholders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.permit-numbers.update'), [
            'permit_type' => 'loading',
            'pattern' => 'MBG/LOG/{year_short}/{month}/{sequence}',
            'sequence_padding' => 5,
            'reset_period' => 'yearly',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertSame('MBG/LOG/{year_short}/{month}/{sequence}', PermitNumberSetting::for('loading')->pattern);
        $this->assertMatchesRegularExpression('/^MBG\/LOG\/\d{2}\/\d{2}\/\d{5}$/', LoadingPermit::generatePermitNumber());

        $this->actingAs($admin)->from(route('admin.settings.permit-numbers.edit'))->put(route('admin.settings.permit-numbers.update'), [
            'permit_type' => 'loading',
            'pattern' => 'MBG/{tenant}/{sequence}',
            'sequence_padding' => 4,
            'reset_period' => 'global',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.permit-numbers.edit'))->assertSessionHasErrors('pattern');
    }

    public function test_admin_can_delete_selected_permit_and_its_private_document(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $deleted = $this->permit('MBG/SIK/X/0901', 'permits/id-docs/delete.jpg');
        $kept = $this->permit('MBG/SIK/X/0902', 'permits/id-docs/keep.jpg');
        Storage::disk('local')->put($deleted->id_doc_path, 'private');
        Storage::disk('local')->put($kept->id_doc_path, 'private');

        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), [
            'dataset' => 'loading',
            'delete_mode' => 'selected',
            'record_ids' => [$deleted->id],
            'reason' => 'Menghapus data pengujian yang tidak digunakan.',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.data-maintenance.index', ['dataset' => 'loading']));

        $this->assertDatabaseMissing('loading_permits', ['id' => $deleted->id]);
        $this->assertDatabaseHas('loading_permits', ['id' => $kept->id]);
        Storage::disk('local')->assertMissing($deleted->id_doc_path);
        Storage::disk('local')->assertExists($kept->id_doc_path);
        $this->assertTrue(AuditLog::query()->where('event', 'data_deleted')->exists());
    }

    public function test_delete_all_requires_exact_confirmation_phrase(): void
    {
        $admin = $this->admin();
        $this->permit('MBG/SIK/X/0911', 'permits/id-docs/one.jpg');
        $this->permit('MBG/SIK/X/0912', 'permits/id-docs/two.jpg');

        $payload = [
            'dataset' => 'loading',
            'delete_mode' => 'all',
            'confirmation' => 'hapus semua',
            'reason' => 'Membersihkan seluruh data pengujian lama.',
            'current_password' => 'password',
        ];
        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), $payload)
            ->assertSessionHasErrors('confirmation');
        $this->assertSame(2, LoadingPermit::query()->count());

        $payload['confirmation'] = 'HAPUS SEMUA';
        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), $payload)->assertRedirect();
        $this->assertSame(0, LoadingPermit::query()->count());
    }

    public function test_active_delivery_logs_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $queued = EmailMessageLog::query()->create([
            'recipient' => 'tenant@example.test',
            'recipient_hash' => hash('sha256', 'tenant@example.test'),
            'notification_type' => 'TestNotification',
            'subject' => 'Pesan mengantre',
            'status' => 'retrying',
            'attempts' => 1,
        ]);

        $this->actingAs($admin)->delete(route('admin.data-maintenance.destroy'), [
            'dataset' => 'email_logs',
            'delete_mode' => 'selected',
            'record_ids' => [$queued->id],
            'reason' => 'Menguji perlindungan log pengiriman aktif.',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('email_message_logs', ['id' => $queued->id, 'status' => 'retrying']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'phone' => '081200009000']);
    }

    private function permit(string $number, string $path): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => $number,
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081234567890',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:00',
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Data pengujian',
            'id_doc_path' => $path,
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }
}
