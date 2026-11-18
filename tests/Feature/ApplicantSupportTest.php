<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\ApplicantSupportSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_applicant_support_and_it_only_appears_on_permit_pages(): void
    {
        $this->withoutMiddleware([RequireSensitiveActionConfirmation::class, EnsureWebsiteOperating::class]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'phone' => '081200009999']);

        $this->actingAs($admin)->put(route('admin.settings.applicant-support.update'), [
            'is_active' => '1',
            'contact_name' => 'IT Support MBG',
            'whatsapp_number' => '081234567890',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.settings.applicant-support.edit'));

        $setting = ApplicantSupportSetting::current();
        $this->assertTrue($setting->is_active);
        $this->assertSame('6281234567890', $setting->whatsapp_number);

        auth()->logout();
        $this->get(route('work-permits.create'))
            ->assertOk()
            ->assertSee('Butuh bantuan?')
            ->assertSee('Hubungi IT Support melalui WhatsApp')
            ->assertDontSee('applicant_token');
        $this->get(route('portal.dashboard'))->assertOk()->assertDontSee('Butuh bantuan?');
    }

    public function test_inactive_support_does_not_render_the_bubble(): void
    {
        $this->withoutMiddleware(EnsureWebsiteOperating::class);
        ApplicantSupportSetting::current()->update(['is_active' => false]);

        $this->get(route('event-permits.create'))->assertOk()->assertDontSee('Butuh bantuan?');
    }
}
