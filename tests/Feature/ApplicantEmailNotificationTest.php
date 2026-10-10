<?php

namespace Tests\Feature;

use App\Events\LoadingPermitSubmitted;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use App\Notifications\LoadingPermitApplicantMail;
use App\Notifications\WorkPermitApplicantMail;
use App\Support\MailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
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
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Email notifikasi sedang dikirim')
            ->assertSee('inbox atau folder spam')
            ->assertSee('pemohon@example.test')
            ->assertSee('Waktu Unloading')
            ->assertSee('22:30 WITA');
        $this->assertDatabaseHas('loading_permits', ['applicant_email' => 'pemohon@example.test']);
        $this->assertSame('22:30 WITA', LoadingPermit::firstOrFail()->movement_time_label);
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

    public function test_email_template_uses_mal_bali_galeria_branding(): void
    {
        $this->assertFileExists(public_path('email-logo.png'));
        $permit = $this->permit('MBG/SIK/MAIL/0004');
        $message = (new LoadingPermitApplicantMail($permit, LoadingPermitApplicantMail::SUBMITTED))
            ->toMail((object) []);
        $html = $message->render()->toHtml();

        $this->assertStringContainsString('cid:'.MailBranding::LOGO_CONTENT_ID, $html);
        $this->assertStringContainsString('Mal Bali Galeria', $html);
        $this->assertStringContainsString('Yogi Prayoga', $html);
        $this->assertStringNotContainsString('Laravel Logo', $html);

        $email = new Email;
        foreach ($message->callbacks as $callback) {
            $callback($email);
        }

        $this->assertCount(1, $email->getAttachments());
        $this->assertSame(MailBranding::LOGO_CONTENT_ID, $email->getAttachments()[0]->getContentId());
        $this->assertSame('inline', $email->getAttachments()[0]->getDisposition());
    }

    public function test_work_permit_email_link_grants_temporary_read_access_to_the_linked_tenant(): void
    {
        $owner = User::factory()->create([
            'phone' => '081200001298',
            'role' => 'tenant',
        ]);
        $permit = WorkPermit::create([
            'user_id' => $owner->id,
            'contractor_name' => 'Kontraktor Email Test',
            'applicant_name' => 'Pemohon Izin Kerja',
            'applicant_phone' => '081200001297',
            'applicant_email' => 'kerja@example.test',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/email-test.jpg',
            'status' => 'mep_review',
        ]);
        $message = (new WorkPermitApplicantMail($permit, 'Permohonan Diterima', 'Permohonan sedang diperiksa.'))
            ->toMail((object) []);

        $this->get(route('work-permits.status', $permit->applicant_token))->assertForbidden();
        $this->get($message->actionUrl)
            ->assertOk()
            ->assertSee($permit->permit_number);
        $this->get($message->actionUrl.'&tampered=1')->assertForbidden();
    }

    public function test_global_footer_uses_the_current_year_and_credit(): void
    {
        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee((string) now()->year)
            ->assertSee('Yogi Prayoga');
    }

    public function test_standalone_scanner_also_has_the_global_credit(): void
    {
        $this->get(route('scanner.index'))
            ->assertOk()
            ->assertSee((string) now()->year)
            ->assertSee('Yogi Prayoga');
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
            'movement_time' => '22:30',
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
            'movement_time' => '22:30',
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian email',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'id_doc_type' => 'ktp',
        ], $overrides);
    }
}
