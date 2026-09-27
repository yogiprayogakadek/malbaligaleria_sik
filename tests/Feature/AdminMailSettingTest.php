<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\User;
use App\Services\MailSettingsConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMailSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_open_email_settings(): void
    {
        $admin = $this->user('admin');
        $tenant = $this->user('tenant');

        $this->actingAs($admin)->get(route('admin.settings.mail.edit'))->assertOk();
        $this->actingAs($tenant)->get(route('admin.settings.mail.edit'))->assertForbidden();
    }

    public function test_admin_can_store_an_encrypted_mail_password(): void
    {
        $password = 'smtp-secret-password';

        $this->actingAs($this->user('admin'))
            ->put(route('admin.settings.mail.update'), $this->validData(['password' => $password]))
            ->assertRedirect(route('admin.settings.mail.edit'));

        $setting = MailSetting::current();

        $this->assertNotNull($setting);
        $this->assertSame($password, $setting->password);
        $this->assertNotSame($password, DB::table('mail_settings')->value('password'));
        $this->actingAs($this->user('admin'))
            ->get(route('admin.settings.mail.edit'))
            ->assertDontSee($password);
    }

    public function test_blank_password_preserves_the_existing_secret(): void
    {
        $setting = MailSetting::create($this->validData(['password' => 'original-password']));

        $this->actingAs($this->user('admin'))
            ->put(route('admin.settings.mail.update'), $this->validData([
                'password' => '',
                'from_name' => 'MBG Property Management',
            ]))
            ->assertSessionHasNoErrors();

        $setting->refresh();
        $this->assertSame('original-password', $setting->password);
        $this->assertSame('MBG Property Management', $setting->from_name);
    }

    public function test_mail_server_and_port_are_strictly_validated(): void
    {
        $this->actingAs($this->user('admin'))
            ->from(route('admin.settings.mail.edit'))
            ->put(route('admin.settings.mail.update'), $this->validData([
                'host' => 'https://mail.example.test/path',
                'port' => 8080,
            ]))
            ->assertSessionHasErrors(['host', 'port']);

        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_active_database_settings_are_applied_to_mail_configuration(): void
    {
        MailSetting::create($this->validData());

        app(MailSettingsConfigurator::class)->apply(true);

        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('sender@example.test', config('mail.from.address'));
    }

    public function test_disabling_database_settings_restores_server_configuration(): void
    {
        config([
            'mail.managed_fallback.default' => 'log',
            'mail.managed_fallback.smtp.host' => 'fallback.example.test',
            'mail.managed_fallback.smtp.port' => 587,
            'mail.managed_fallback.smtp.scheme' => 'smtp',
            'mail.managed_fallback.smtp.url' => null,
            'mail.managed_fallback.smtp.username' => 'fallback@example.test',
            'mail.managed_fallback.smtp.password' => 'fallback-secret',
            'mail.managed_fallback.smtp.timeout' => 12,
            'mail.managed_fallback.from.address' => 'fallback@example.test',
            'mail.managed_fallback.from.name' => 'Fallback Sender',
        ]);
        MailSetting::create($this->validData(['is_active' => false]));

        app(MailSettingsConfigurator::class)->apply(true);

        $this->assertSame('log', config('mail.default'));
        $this->assertSame('fallback.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('fallback@example.test', config('mail.from.address'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'host' => 'mail.example.test',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'sender@example.test',
            'password' => 'smtp-secret-password',
            'from_address' => 'sender@example.test',
            'from_name' => 'Mal Bali Galeria',
            'timeout' => 10,
            'is_active' => true,
        ], $overrides);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('0812########'),
            'role' => $role,
            'tenant_name' => $role === 'tenant' ? 'Tenant Test' : null,
        ]);
    }
}
