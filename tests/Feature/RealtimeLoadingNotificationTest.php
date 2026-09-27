<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Models\User;
use App\Notifications\LoadingPermitSubmittedPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealtimeLoadingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_submission_notifies_each_tr_validator_in_realtime(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $firstValidator = $this->createValidator('TR');
        $secondValidator = $this->createValidator('TR');
        $otherValidator = $this->createValidator('MEP');

        $response = $this->post(route('loading.store'), $this->validPermitData());

        $response->assertRedirect();
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $firstValidator->id,
            'type' => 'pending_review',
        ]);
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $secondValidator->id,
            'type' => 'pending_review',
        ]);
        $this->assertDatabaseMissing('permit_notifications', [
            'user_id' => $otherValidator->id,
        ]);
        Event::assertDispatchedTimes(LoadingPermitSubmitted::class, 2);
        Notification::assertSentToTimes($firstValidator, LoadingPermitSubmittedPush::class, 1);
        Notification::assertSentToTimes($secondValidator, LoadingPermitSubmittedPush::class, 1);
        Notification::assertNotSentTo($otherValidator, LoadingPermitSubmittedPush::class);
        Event::assertDispatched(
            LoadingPermitSubmitted::class,
            fn (LoadingPermitSubmitted $event) => $event->permit->user_id === null
                && $event->pendingCount === 1
        );
    }

    public function test_logged_in_tenant_submission_notifies_tr_validator_in_realtime(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $validator = $this->createValidator('TR');
        $tenant = User::factory()->create([
            'phone' => '081200000091',
            'tenant_name' => 'Tenant Login',
            'role' => 'tenant',
            'division' => null,
        ]);

        $response = $this->actingAs($tenant)->post(route('loading.store'), $this->validPermitData());

        $response->assertRedirect();
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $validator->id,
            'title' => 'Permohonan Baru',
        ]);
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Permohonan Diterima',
        ]);
        Event::assertDispatched(
            LoadingPermitSubmitted::class,
            fn (LoadingPermitSubmitted $event) => $event->notification->user_id === $validator->id
                && $event->permit->user_id === $tenant->id
        );
        Notification::assertSentTo($validator, LoadingPermitSubmittedPush::class);
        Notification::assertNotSentTo($tenant, LoadingPermitSubmittedPush::class);
    }

    public function test_validator_can_only_authorize_own_realtime_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $validator = $this->createValidator('TR');
        $otherValidator = $this->createValidator('TR');

        $this->actingAs($validator)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-validators.{$validator->id}",
            ])
            ->assertOk();

        $this->actingAs($validator)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-validators.{$otherValidator->id}",
            ])
            ->assertForbidden();
    }

    private function createValidator(string $division): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => 'validator',
            'division' => $division,
        ]);
    }

    private function validPermitData(): array
    {
        return [
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000099',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 3,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'id_doc_type' => 'ktp',
        ];
    }
}
