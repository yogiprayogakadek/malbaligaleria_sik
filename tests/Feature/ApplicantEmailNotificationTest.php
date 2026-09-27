<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Notifications\LoadingPermitApplicantMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_sends_email_to_the_optional_applicant_address(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $response = $this->post(route('loading.store'), $this->validPermitData([
            'applicant_email' => ' PEMOHON@EXAMPLE.TEST ',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('loading_permits', ['applicant_email' => 'pemohon@example.test']);
        Notification::assertSentOnDemand(
            LoadingPermitApplicantMail::class,
            fn (LoadingPermitApplicantMail $notification, array $channels, object $notifiable): bool => $notification->type === LoadingPermitApplicantMail::SUBMITTED
                && in_array('mail', $channels, true)
                && array_key_exists('pemohon@example.test', $notifiable->routeNotificationFor('mail')),
        );
    }

    public function test_submission_without_email_does_not_send_applicant_mail(): void
    {
        Event::fake([LoadingPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $this->post(route('loading.store'), $this->validPermitData([
            'applicant_email' => '',
        ]))->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_status_changes_send_approved_and_rejected_emails(): void
    {
        Notification::fake();
        $validator = User::factory()->create([
            'phone' => '081200001201',
            'role' => 'validator',
            'division' => 'TR',
        ]);
        $approvedPermit = $this->permit('MBG/SIK/MAIL/0001');
        $rejectedPermit = $this->permit('MBG/SIK/MAIL/0002');

        $this->actingAs($validator)
            ->post(route('tr.approve', $approvedPermit->permit_number), ['review_notes' => 'Data sudah sesuai.'])
            ->assertRedirect(route('tr.index'));

        $this->actingAs($validator)
            ->post(route('tr.reject', $rejectedPermit->permit_number), ['review_notes' => 'Jadwal loading belum dapat disetujui.'])
            ->assertRedirect(route('tr.index'));

        Notification::assertSentOnDemand(
            LoadingPermitApplicantMail::class,
            fn (LoadingPermitApplicantMail $notification): bool => $notification->permit->is($approvedPermit)
                && $notification->type === LoadingPermitApplicantMail::APPROVED,
        );
        Notification::assertSentOnDemand(
            LoadingPermitApplicantMail::class,
            fn (LoadingPermitApplicantMail $notification): bool => $notification->permit->is($rejectedPermit)
                && $notification->type === LoadingPermitApplicantMail::REJECTED,
        );
    }

    public function test_email_status_link_is_signed_and_tampering_is_rejected(): void
    {
        $permit = $this->permit('MBG/SIK/MAIL/0003');
        $mail = (new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::SUBMITTED))
            ->toMail((object) []);

        $this->get($mail->actionUrl)
            ->assertOk()
            ->assertSee($permit->permit_number);

        $this->get($mail->actionUrl.'&tampered=1')->assertForbidden();
        $this->get(route('loading.show', $permit->permit_number))->assertForbidden();
    }

    private function permit(string $number): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => $number,
            'tenant_name' => 'Tenant Email Test',
            'applicant_name' => 'Pemohon Email',
            'applicant_phone' => '081200001299',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian email',
            'id_doc_path' => 'permits/id-docs/mail-test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPermitData(array $overrides = []): array
    {
        return array_merge([
            'tenant_name' => 'Tenant Email Test',
            'applicant_name' => 'Pemohon Email',
            'applicant_phone' => '081200001299',
            'applicant_email' => 'pemohon@example.test',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian email',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'id_doc_type' => 'ktp',
        ], $overrides);
    }
}
