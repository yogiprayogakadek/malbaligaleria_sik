<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\MessageTemplate;
use App\Models\MonitoringSetting;
use App\Models\SecuritySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WorkPermit;
use App\Models\WorkPermitCategory;
use App\Services\MessageTemplateRenderer;
use App\Services\MonitoringWebhookService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_category_and_server_uses_its_routing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001001']);

        $this->actingAs($admin)->post(route('admin.work-categories.store'), [
            'code' => 'Special Inspection',
            'name' => 'Special Inspection',
            'division' => 'EP',
            'max_duration_days' => 5,
            'sort_order' => 70,
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.work-categories.index'));

        $category = WorkPermitCategory::query()->where('code', 'special_inspection')->firstOrFail();
        $this->assertSame('EP', WorkPermit::divisionForCategory($category->code));
        $this->assertSame(5, $category->max_duration_days);
    }

    public function test_admin_can_link_and_unlink_a_tenant_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001002']);
        $account = User::factory()->create(['role' => 'tenant', 'phone' => '081200001003', 'tenant_name' => 'Nama Lama']);

        $this->actingAs($admin)->post(route('admin.tenants.store'), [
            'name' => 'Tenant Uji',
            'unit_number' => 'GF-10',
            'floor' => 'Ground Floor',
            'pic_name' => 'PIC Tenant',
            'pic_email' => 'pic@example.test',
            'pic_phone' => '081234567890',
            'is_active' => 1,
            'user_id' => $account->id,
            'current_password' => 'password',
        ])->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::query()->sole();
        $this->assertSame($tenant->id, $account->fresh()->tenant_id);
        $this->assertSame('Tenant Uji (GF-10)', $account->fresh()->tenant_name);

        $this->actingAs($admin)->put(route('admin.tenants.update', $tenant), [
            'name' => 'Tenant Uji',
            'unit_number' => 'GF-10',
            'is_active' => 1,
            'user_id' => '',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.tenants.index'));

        $this->assertNull($account->fresh()->tenant_id);
    }

    public function test_message_renderer_replaces_only_supported_variables(): void
    {
        $template = MessageTemplate::query()->where('key', 'permit_submitted')->firstOrFail();
        $template->update([
            'subject' => 'Surat {{permit_number}} diterima',
            'body' => '{{tenant_name}} masuk antrean {{division}}.',
        ]);

        $message = app(MessageTemplateRenderer::class)->render('permit_submitted', [
            'permit_number' => 'MBG/SIK/X/2026/1',
            'tenant_name' => 'Tenant Uji',
            'division' => 'TR',
        ], 'Fallback', 'Fallback');

        $this->assertSame('Surat MBG/SIK/X/2026/1 diterima', $message['subject']);
        $this->assertSame('Tenant Uji masuk antrean TR.', $message['body']);
    }

    public function test_sensitive_configuration_requires_recent_two_factor_confirmation_when_enabled(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200001004',
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);
        SecuritySetting::query()->findOrFail(1)->update(['sensitive_actions_require_2fa' => true]);

        $this->actingAs($admin)->get(route('admin.settings.mail.edit'))
            ->assertRedirect(route('admin.sensitive-confirm.show'))
            ->assertSessionHas('sensitive_action.intended');

        $this->withSession(['sensitive_action.confirmed_at' => now()->timestamp])
            ->get(route('admin.settings.mail.edit'))
            ->assertOk();
    }

    public function test_security_dashboard_is_restricted_to_security_division(): void
    {
        $security = User::factory()->create(['role' => 'validator', 'division' => 'SEC', 'phone' => '081200001005']);
        $tr = User::factory()->create(['role' => 'validator', 'division' => 'TR', 'phone' => '081200001006']);

        $this->actingAs($security)->get(route('security.index'))->assertOk()->assertSee('Surat berlaku hari ini');
        $this->actingAs($tr)->get(route('security.index'))->assertForbidden();
    }

    public function test_monitoring_webhook_uses_https_allowlist_and_hmac_signature(): void
    {
        config()->set('monitoring.allowed_hosts', ['monitor.example.test']);
        MonitoringSetting::query()->findOrFail(1)->update([
            'enabled' => true,
            'webhook_url' => 'https://monitor.example.test/hooks/mbg',
            'signing_secret' => str_repeat('s', 32),
            'timeout_seconds' => 5,
        ]);
        Http::fake(['https://monitor.example.test/*' => Http::response(['ok' => true])]);

        app(MonitoringWebhookService::class)->send('system.health.error', ['component' => 'Queue']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://monitor.example.test/hooks/mbg'
            && str_starts_with($request->header('X-MBG-Signature')[0] ?? '', 'sha256='));
    }

    public function test_whatsapp_fallback_cannot_redirect_a_staff_message_to_the_applicant(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001007']);
        $permit = LoadingPermit::query()->create([
            'permit_number' => 'MBG/SIK/X/1001',
            'tenant_name' => 'Tenant Uji',
            'applicant_name' => 'Pemohon Uji',
            'applicant_phone' => '081211112222',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'movement_time' => '22:00',
            'item_count' => 1,
            'item_unit' => 'koli',
            'status' => 'pending',
        ]);
        $log = WhatsappMessageLog::query()->create([
            'unique_key' => Str::uuid(),
            'permit_type' => 'loading',
            'permit_id' => $permit->id,
            'event' => 'submitted',
            'recipient_kind' => 'staff',
            'recipient_name' => 'Validator',
            'recipient_phone' => '628123456789',
            'recipient_phone_hash' => hash('sha256', '628123456789'),
            'message' => 'Pesan staf',
            'status' => 'failed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.communication-logs.whatsapp.fallback', $log))
            ->assertForbidden();
    }
}
