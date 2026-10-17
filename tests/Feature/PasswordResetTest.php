<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_request_by_email_uses_a_generic_response_and_queues_mail(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['identifier' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, PasswordResetLinkNotification::class);

        $unknownResponse = $this->post(route('password.email'), ['identifier' => 'unknown@example.test']);
        $unknownResponse->assertRedirect()->assertSessionHas('success');
    }

    public function test_reset_request_by_phone_queues_an_encrypted_whatsapp_message(): void
    {
        Queue::fake();
        WhatsappSetting::create([
            'base_url' => 'http://waha.test',
            'api_key' => 'secret',
            'session' => 'default',
            'timeout' => 10,
            'is_active' => true,
            'notify_applicants' => true,
        ]);
        $user = $this->user(['phone' => '0812 3456-7890']);

        $this->post(route('password.email'), ['identifier' => '+62 812-3456-7890'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $log = WhatsappMessageLog::sole();
        $this->assertSame('account', $log->permit_type);
        $this->assertSame('password_reset', $log->event);
        $this->assertStringContainsString('/reset-password/', $log->message);
        $this->assertStringNotContainsString('reset-password', (string) DB::table('whatsapp_message_logs')->value('message'));
    }

    public function test_valid_token_changes_password_and_revokes_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->user();
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaru8',
            'password_confirmation' => 'PasswordBaru8',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('PasswordBaru8', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_inactive_account_does_not_receive_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->user(['is_active' => false]);

        $this->post(route('password.email'), ['identifier' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '081234567899',
            'role' => 'tenant',
        ], $attributes));
    }
}
