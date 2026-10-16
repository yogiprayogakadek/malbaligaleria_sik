<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFailedJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_view_failed_jobs_without_exposing_payload_or_exception(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200000091']);
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200000092']);
        $uuid = $this->createFailedJob();

        $this->actingAs($admin)->get(route('admin.failed-jobs.index'))
            ->assertOk()
            ->assertSee('Data loading barang')
            ->assertSee('Antrean gagal')
            ->assertDontSee('nav-tooltip')
            ->assertSee('SendApplicantNotification')
            ->assertSee('RuntimeException')
            ->assertDontSee('private-applicant@example.test')
            ->assertDontSee('smtp-secret-value');

        $this->actingAs($admin)->get(route('admin.failed-jobs.confirm', $uuid))
            ->assertOk()
            ->assertDontSee('smtp-secret-value');

        $this->actingAs($tenant)->get(route('admin.failed-jobs.index'))->assertForbidden();
    }

    public function test_retry_requires_admin_password_and_is_audited(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081200000093',
            'password' => Hash::make('secure-password'),
        ]);
        $uuid = $this->createFailedJob();

        $this->actingAs($admin)->post(route('admin.failed-jobs.retry', $uuid), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);

        $this->actingAs($admin)->post(route('admin.failed-jobs.retry', $uuid), [
            'password' => 'secure-password',
        ])->assertRedirect(route('admin.failed-jobs.index'));

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'retried',
            'auditable_type' => 'queue_job',
        ]);

        $audit = AuditLog::query()->where('event', 'retried')->firstOrFail();
        $this->assertSame($uuid, $audit->new_values['job_uuid']);
        $this->assertArrayNotHasKey('payload', $audit->new_values);
    }

    private function createFailedJob(): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\SendApplicantNotification',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['recipient' => 'private-applicant@example.test'],
            ], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: smtp-secret-value in /var/www/sik/app/Jobs/Example.php:20',
            'failed_at' => now(),
        ]);

        return $uuid;
    }
}
