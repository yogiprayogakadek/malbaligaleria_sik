<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use App\Notifications\LoadingPermitSubmittedPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tr_validator_can_store_a_subscription_for_the_current_account(): void
    {
        $validator = $this->createUser('validator', 'TR');
        $payload = $this->validSubscription();

        $response = $this->actingAs($validator)
            ->postJson(route('tr.push-subscriptions.store'), $payload);

        $response->assertCreated()->assertExactJson(['subscribed' => true]);
        $response->assertDontSee($payload['endpoint']);
        $response->assertDontSee($payload['keys']['auth']);
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $validator->id,
            'subscribable_type' => User::class,
            'endpoint' => $payload['endpoint'],
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_non_tr_users_cannot_manage_push_subscriptions(): void
    {
        $tenant = $this->createUser('tenant');

        $this->actingAs($tenant)
            ->postJson(route('tr.push-subscriptions.store'), $this->validSubscription())
            ->assertForbidden();
    }

    public function test_admin_can_store_subscription_through_admin_route(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.push-subscriptions.store'), $this->validSubscription())
            ->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $admin->id,
            'subscribable_type' => User::class,
        ]);
    }

    public function test_subscription_rejects_insecure_endpoint_and_malformed_keys(): void
    {
        $validator = $this->createUser('validator', 'TR');
        $payload = $this->validSubscription();
        $payload['endpoint'] = 'http://push.example.test/subscription';
        $payload['keys']['p256dh'] = 'not valid key!';

        $this->actingAs($validator)
            ->postJson(route('tr.push-subscriptions.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh']);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_validator_can_only_delete_own_subscription(): void
    {
        $owner = $this->createUser('validator', 'TR');
        $other = $this->createUser('validator', 'TR');
        $otherEndpoint = 'https://push.example.test/other-device';

        $owner->updatePushSubscription(
            'https://push.example.test/owner-device',
            str_repeat('A', 87),
            str_repeat('B', 22),
            'aes128gcm',
        );
        $other->updatePushSubscription(
            $otherEndpoint,
            str_repeat('C', 87),
            str_repeat('D', 22),
            'aes128gcm',
        );

        $this->actingAs($owner)
            ->deleteJson(route('tr.push-subscriptions.destroy'), ['endpoint' => $otherEndpoint])
            ->assertNoContent();

        $this->assertDatabaseCount('push_subscriptions', 2);
        $this->assertTrue(PushSubscription::query()->where('endpoint', $otherEndpoint)->exists());
    }

    public function test_shared_browser_subscription_moves_to_the_current_validator(): void
    {
        $previousValidator = $this->createUser('validator', 'TR');
        $currentValidator = $this->createUser('validator', 'TR');
        $payload = $this->validSubscription();

        $previousValidator->updatePushSubscription(
            $payload['endpoint'],
            $payload['keys']['p256dh'],
            $payload['keys']['auth'],
            $payload['content_encoding'],
        );

        $this->actingAs($currentValidator)
            ->postJson(route('tr.push-subscriptions.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $currentValidator->id,
            'subscribable_type' => User::class,
            'endpoint' => $payload['endpoint'],
        ]);
        $this->assertDatabaseMissing('push_subscriptions', [
            'subscribable_id' => $previousValidator->id,
            'endpoint' => $payload['endpoint'],
        ]);
    }

    public function test_push_payload_contains_no_personal_contact_data(): void
    {
        $permit = LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/0099',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Nama Rahasia',
            'applicant_phone' => '081299999999',
            'applicant_email' => 'rahasia@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);

        $notification = new LoadingPermitSubmittedPush($permit);
        $payload = $notification->toWebPush($this->createUser('validator', 'TR'), $notification)->toArray();
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($permit->applicant_phone, $serialized);
        $this->assertStringNotContainsString($permit->applicant_email, $serialized);
        $this->assertStringNotContainsString($permit->applicant_name, $serialized);
        $this->assertStringContainsString('/tr/', $payload['data']['url']);
    }

    public function test_admin_push_opens_admin_loading_detail(): void
    {
        $permit = LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/0100',
            'tenant_name' => 'Tenant Admin Push',
            'applicant_name' => 'Pemohon',
            'applicant_phone' => '081288888888',
            'direction' => 'out',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 1,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc_path' => 'permits/id-docs/test-admin.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
        $notification = new LoadingPermitSubmittedPush($permit);
        $payload = $notification->toWebPush($this->createUser('admin'), $notification)->toArray();

        $this->assertStringContainsString('/admin/loading/', $payload['data']['url']);
    }

    public function test_service_worker_uses_a_neutral_fallback_and_accepts_staff_notification_paths(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertIsString($serviceWorker);
        $this->assertStringContainsString("new URL('/', self.location.origin)", $serviceWorker);
        $this->assertStringNotContainsString("new URL('/tr', self.location.origin)", $serviceWorker);
        foreach (['/admin', '/tr', '/mep', '/finance', '/secretary', '/staff/work-permits'] as $prefix) {
            $this->assertStringContainsString("'{$prefix}'", $serviceWorker);
        }
    }

    private function createUser(string $role, ?string $division = null): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
            'division' => $division,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validSubscription(): array
    {
        return [
            'endpoint' => 'https://push.example.test/subscription-id',
            'keys' => [
                'p256dh' => str_repeat('A', 87),
                'auth' => str_repeat('B', 22),
            ],
            'content_encoding' => 'aes128gcm',
        ];
    }
}
