<?php

namespace Tests\Feature;

use App\Events\WorkPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
use App\Models\WorkPermitDurationSetting;
use App\Notifications\WorkPermitSubmittedPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkPermitTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_permit_form_uses_a_wizard_and_displays_the_rules(): void
    {
        $this->get(route('work-permits.create'))
            ->assertOk()
            ->assertSee('data-wizard-step="1"', false)
            ->assertSee('data-wizard-step="4"', false)
            ->assertSee('Nomor ID (Opsional)')
            ->assertSee('General Cleaning')
            ->assertSee('Sebar Flyer')
            ->assertSee('Stock Opname')
            ->assertSee('Pest Control')
            ->assertDontSee('KOL Event')
            ->assertDontSee('Grand Opening')
            ->assertSee('Loading / unloading material')
            ->assertSee('Fit Out / Renovasi')
            ->assertSee('Pembongkaran')
            ->assertSee('Tambahan Penerangan')
            ->assertSee('Pekerjaan di rooftop')
            ->assertSee('Perbaikan ATM')
            ->assertSee('Maintenance AC')
            ->assertSee('Pilih satu rincian yang paling sesuai dengan pekerjaan.')
            ->assertSee('name="other_work_type" value="rooftop"', false)
            ->assertSee('name="other_work_type" value="atm_repair"', false)
            ->assertSee('id="other_work_description"', false)
            ->assertSee('class="pf-wrap"><svg class="pf-icon"', false)
            ->assertSee('data-max-duration-days="7"', false)
            ->assertSee('const allowsRooftop = isOther && outsideSchedule.checked;', false)
            ->assertSee('rooftopWorkOption.hidden = !allowsRooftop;', false)
            ->assertSee('Surat Tugas')
            ->assertSee('Boleh pilih keduanya')
            ->assertSee('Maksimal 7 hari kalender')
            ->assertSee('Setiap pekerja wajib membawa KTP')
            ->assertSee('Unggah foto KTP / SIM')
            ->assertSee('id="workUploadPreview"', false)
            ->assertSee('form-image.js')
            ->assertSee('id="permitSubmitProgress"', false)
            ->assertSee("window.showPermitSubmitProgress?.('Mengirim surat izin kerja')", false)
            ->assertSee('Saya telah membaca, memahami, dan menyetujui')
            ->assertSeeInOrder(['Waktu Kerja', 'Lokasi Pekerjaan']);
    }

    public function test_guest_can_submit_a_work_permit_with_optional_worker_ids(): void
    {
        Notification::fake();
        Storage::fake('local');

        $response = $this->post(route('work-permits.store'), $this->validData());

        $response->assertRedirect();
        $permit = WorkPermit::query()->with('workers')->firstOrFail();

        $this->assertSame('mep_review', $permit->status);
        $this->assertSame('Kontraktor Pengujian', $permit->contractor_name);
        $this->assertSame('Tenant Pengujian', $permit->tenant_name);
        $this->assertCount(2, $permit->workers);
        $this->assertNull($permit->workers->last()->identity_number);
        $this->assertSame(['inside_store', 'outside_store'], $permit->work_schedules);
        $this->assertFalse($permit->security_deposit);
        $this->assertSame(64, strlen($permit->public_token));
        Storage::disk('local')->assertExists($permit->id_doc_path);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertSee('2 orang');
    }

    public function test_success_page_requires_a_valid_signature_and_random_token(): void
    {
        $permit = WorkPermit::create($this->modelData());

        $this->get(route('work-permits.success', $permit->public_token))->assertForbidden();
        $this->get(route('work-permits.success', str_repeat('A', 64)))->assertForbidden();
    }

    public function test_backend_rejects_missing_workers_consent_and_invalid_identity_document(): void
    {
        Notification::fake();
        Storage::fake('local');

        $data = $this->validData();
        $data['workers'] = [];
        $data['consent'] = '0';
        $data['work_category'] = 'mep';
        $data['id_doc'] = UploadedFile::fake()->create('identitas.pdf', 100, 'application/pdf');

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors(['workers', 'work_category', 'consent', 'id_doc']);

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_backend_accepts_a_portrait_camera_photo_as_an_identity_document(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['id_doc'] = UploadedFile::fake()->image('android-camera.jpg', 900, 1600);

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('work_permits', 1);
    }

    public function test_backend_rejects_an_empty_schedule_and_a_period_longer_than_seven_days(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = [];
        $data['end_date'] = now()->addDays(7)->toDateString();

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors(['work_schedules', 'end_date']);

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_work_permit_accepts_exactly_seven_calendar_days(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['end_date'] = now()->addDays(6)->toDateString();

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('work_permits', 1);
    }

    public function test_other_work_duration_uses_the_admin_configuration(): void
    {
        Notification::fake();
        Storage::fake('local');
        WorkPermitDurationSetting::current()->update([
            'default_days' => 7,
            'fit_out_days' => 30,
            'demolition_days' => 12,
        ]);

        $fitOut = $this->validData();
        $fitOut['other_work_type'] = 'fit_out';
        unset($fitOut['other_work_description']);
        $fitOut['end_date'] = now()->addDays(29)->toDateString();
        $this->post(route('work-permits.store'), $fitOut)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $demolition = $this->validData();
        $demolition['other_work_type'] = 'demolition';
        unset($demolition['other_work_description']);
        $demolition['end_date'] = now()->addDays(12)->toDateString();
        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $demolition)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('work_permits', 1);
    }

    public function test_admin_can_update_work_duration_limits_with_password_confirmation(): void
    {
        $admin = $this->staff('admin');
        $session = ['sensitive_action.confirmed_at' => now()->timestamp];

        $this->actingAs($admin)->withSession($session)
            ->get(route('admin.settings.work-permit-durations.edit'))
            ->assertOk()
            ->assertSee('Batas durasi izin kerja');

        $this->actingAs($admin)->withSession($session)
            ->put(route('admin.settings.work-permit-durations.update'), [
                'default_days' => 7,
                'fit_out_days' => 31,
                'demolition_days' => 10,
                'current_password' => 'password',
            ])->assertRedirect();

        $this->assertDatabaseHas('work_permit_duration_settings', [
            'default_days' => 7,
            'fit_out_days' => 31,
            'demolition_days' => 10,
        ]);
    }

    public function test_shared_date_picker_uses_each_forms_duration_limit(): void
    {
        $script = file_get_contents(resource_path('js/loading-permit-dates.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('endInput?.dataset.maxDurationDays', $script);
        $this->assertStringContainsString('maxDurationDays - 1', $script);
        $this->assertStringNotContainsString('addDays(selectedDate, 2)', $script);
    }

    public function test_hidden_rooftop_option_is_not_overridden_by_checkbox_styles(): void
    {
        $styles = file_get_contents(public_path('styles.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('.work-extra-options label[hidden] { display: none; }', $styles);
    }

    public function test_at_least_one_worker_name_is_required(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['workers'] = [['name' => '', 'identity_number' => '']];

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasErrors('workers');

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_other_rooftop_or_atm_work_requires_an_assignment_letter(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = ['outside_store'];
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['rooftop_network_type'] = 'internet_installation';

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('assignment_letter');

        $data['other_work_type'] = 'atm_repair';
        unset($data['rooftop_network_type']);
        $data['id_doc'] = UploadedFile::fake()->image('ktp-second.jpg', 600, 400);
        $data['assignment_letter'] = UploadedFile::fake()->create('surat-tugas.pdf', 300, 'application/pdf');

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permit = WorkPermit::query()->firstOrFail();
        $this->assertFalse($permit->includes_loading_material);
        $this->assertFalse($permit->is_rooftop_work);
        $this->assertTrue($permit->is_atm_repair);
        $this->assertNull($permit->rooftop_network_type);
        Storage::disk('local')->assertExists($permit->assignment_letter_path);
    }

    public function test_server_derives_only_one_work_detail_from_the_canonical_choice(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = ['outside_store'];
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['is_rooftop_work'] = '1';
        $data['is_atm_repair'] = '1';
        $data['includes_loading_material'] = '1';
        $data['rooftop_network_type'] = 'internet_installation';
        $data['assignment_letter'] = UploadedFile::fake()->create('surat-tugas.pdf', 300, 'application/pdf');

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permit = WorkPermit::query()->firstOrFail();
        $this->assertTrue($permit->is_rooftop_work);
        $this->assertFalse($permit->is_atm_repair);
        $this->assertFalse($permit->includes_loading_material);
    }

    public function test_rooftop_work_is_allowed_when_inside_and_outside_store_are_selected(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['rooftop_network_type'] = 'ac_cleaning';

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permit = WorkPermit::query()->firstOrFail();
        $this->assertSame(['inside_store', 'outside_store'], $permit->work_schedules);
        $this->assertTrue($permit->is_rooftop_work);
    }

    public function test_rooftop_work_is_rejected_without_the_outside_store_schedule(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = ['inside_store'];
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['rooftop_network_type'] = 'ac_cleaning';

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('is_rooftop_work');

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_rooftop_ac_cleaning_does_not_require_an_assignment_letter(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = ['outside_store'];
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['rooftop_network_type'] = 'ac_cleaning';

        $this->post(route('work-permits.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permit = WorkPermit::query()->firstOrFail();
        $this->assertTrue($permit->is_rooftop_work);
        $this->assertSame('ac_cleaning', $permit->rooftop_network_type);
        $this->assertNull($permit->assignment_letter_path);
    }

    public function test_other_work_rejects_invalid_rooftop_network_type(): void
    {
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_schedules'] = ['outside_store'];
        $data['other_work_type'] = 'rooftop';
        unset($data['other_work_description']);
        $data['rooftop_network_type'] = 'arbitrary-network-work';
        $data['assignment_letter'] = UploadedFile::fake()->create('surat-tugas.pdf', 300, 'application/pdf');

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('rooftop_network_type');
    }

    public function test_kol_event_cannot_be_submitted_through_the_work_permit_form(): void
    {
        Event::fake([WorkPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');
        $data = $this->validData();
        $data['work_category'] = 'kol_event';

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('work_category');

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_grand_opening_is_hidden_and_cannot_be_submitted_as_a_work_permit(): void
    {
        Event::fake([WorkPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');
        $this->get(route('work-permits.create'))
            ->assertOk()
            ->assertDontSee('<option value="grand_opening"', false)
            ->assertDontSee('Grand Opening');

        $data = $this->validData();
        $data['work_category'] = 'grand_opening';

        $this->from(route('work-permits.create'))
            ->post(route('work-permits.store'), $data)
            ->assertRedirect(route('work-permits.create'))
            ->assertSessionHasErrors('work_category');

        $this->assertDatabaseCount('work_permits', 0);
    }

    public function test_assignment_letter_requires_a_signed_authorized_staff_url(): void
    {
        Storage::fake('local');
        $this->putPdf('permits/work/assignment-letters/task.pdf');
        $permit = WorkPermit::query()->create(array_merge($this->modelData(), [
            'assigned_division' => 'EP',
            'status' => 'ep_review',
            'work_category' => 'kol_event',
            'assignment_letter_path' => 'permits/work/assignment-letters/task.pdf',
            'assignment_letter_type' => 'pdf',
        ]));
        $ep = $this->staff('validator', 'EP');
        $mep = $this->staff('validator', 'MEP');
        $url = URL::temporarySignedRoute('staff.work-permits.assignment-letter', now()->addMinutes(5), ['token' => $permit->public_token]);

        $this->actingAs($ep)->get(route('staff.work-permits.assignment-letter', $permit->public_token))->assertForbidden();
        $this->actingAs($mep)->get($url)->assertForbidden();
        $this->actingAs($ep)->get($url)->assertOk();
    }

    public function test_logged_in_tenant_is_linked_to_the_work_permit(): void
    {
        Notification::fake();
        Storage::fake('local');
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'tenant_name' => 'Tenant Login',
            'phone' => '081234567899',
        ]);

        $this->actingAs($tenant)->post(route('work-permits.store'), $this->validData())
            ->assertRedirect();

        $this->assertDatabaseHas('work_permits', ['user_id' => $tenant->id]);
    }

    public function test_submission_notifies_only_active_mep_validators_and_admins(): void
    {
        Event::fake([WorkPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $mep = $this->staff('validator', 'MEP');
        $admin = $this->staff('admin');
        $tr = $this->staff('validator', 'TR');
        $inactiveMep = $this->staff('validator', 'MEP', false);

        $this->post(route('work-permits.store'), $this->validData())->assertRedirect();
        $permit = WorkPermit::firstOrFail();

        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $mep->id,
            'work_permit_id' => $permit->id,
            'permit_id' => null,
        ]);
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $admin->id,
            'work_permit_id' => $permit->id,
        ]);
        $this->assertDatabaseMissing('permit_notifications', ['user_id' => $tr->id]);
        $this->assertDatabaseMissing('permit_notifications', ['user_id' => $inactiveMep->id]);

        Event::assertDispatchedTimes(WorkPermitSubmitted::class, 2);
        Notification::assertSentTo($mep, WorkPermitSubmittedPush::class);
        Notification::assertSentTo($admin, WorkPermitSubmittedPush::class);
        Notification::assertNotSentTo($tr, WorkPermitSubmittedPush::class);
        Notification::assertNotSentTo($inactiveMep, WorkPermitSubmittedPush::class);

        $this->actingAs($mep)
            ->get(route('mep.index'))
            ->assertOk()
            ->assertSee($permit->permit_number);

        $notification = PermitNotification::where('user_id', $mep->id)->firstOrFail();
        $this->actingAs($mep)
            ->post(route('mep.notifications.read', $notification))
            ->assertRedirect(route('staff.work-permits.show', $permit->public_token));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_selected_categories_are_routed_to_tr_by_the_server(): void
    {
        Event::fake([WorkPermitSubmitted::class]);
        Notification::fake();
        Storage::fake('local');

        $tr = $this->staff('validator', 'TR');
        $mep = $this->staff('validator', 'MEP');
        $admin = $this->staff('admin');

        foreach (WorkPermit::TR_CATEGORIES as $category) {
            $data = $this->validData();
            $data['work_category'] = $category;
            $data['assigned_division'] = 'MEP';

            $this->post(route('work-permits.store'), $data)->assertRedirect();
        }

        $this->assertSame(4, WorkPermit::where('assigned_division', 'TR')->where('status', 'tr_review')->count());
        $this->assertSame(0, WorkPermit::where('assigned_division', 'MEP')->count());
        $this->assertSame(4, PermitNotification::where('user_id', $tr->id)->whereNotNull('work_permit_id')->count());
        $this->assertSame(4, PermitNotification::where('user_id', $admin->id)->whereNotNull('work_permit_id')->count());
        $this->assertSame(0, PermitNotification::where('user_id', $mep->id)->whereNotNull('work_permit_id')->count());
        Event::assertDispatchedTimes(WorkPermitSubmitted::class, 8);

        $permit = WorkPermit::firstOrFail();
        $this->actingAs($tr)->get(route('tr.work-permits.index'))
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertSee($permit->work_category_label);
    }

    public function test_tr_can_review_only_work_permits_assigned_to_tr(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->putImage('permits/work/id-docs/test.jpg');

        $tr = $this->staff('validator', 'TR');
        $mep = $this->staff('validator', 'MEP');
        $permit = WorkPermit::create(array_merge($this->modelData(), [
            'work_category' => 'general_cleaning',
            'assigned_division' => 'TR',
            'status' => 'tr_review',
        ]));

        $detailUrl = route('staff.work-permits.show', $permit->public_token);
        $this->actingAs($mep)->get($detailUrl)->assertForbidden();
        $this->actingAs($tr)->get($detailUrl)->assertOk()->assertSee('Keputusan TR');

        $this->actingAs($tr)->post(route('tr.work-permits.decision', $permit->public_token), [
            'decision' => 'approved',
            'review_notes' => 'Jadwal dan pekerja telah sesuai.',
        ])->assertRedirect(route('tr.work-permits.index'));

        $permit->refresh();
        $this->assertSame('approved', $permit->status);
        $this->assertSame($tr->id, $permit->reviewed_by);
        $this->assertFalse($permit->deposit_required);
        $this->assertNotNull($permit->official_document_id);
        $this->assertNotNull($permit->official_document_sha256);
        Storage::disk('local')->assertExists($permit->official_document_path);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'tr_decision',
            'to_status' => 'approved',
        ]);
    }

    public function test_work_permit_staff_pages_are_restricted_to_mep_and_admin(): void
    {
        Storage::fake('local');
        $this->putImage('permits/work/id-docs/test.jpg');
        $permit = WorkPermit::create($this->modelData());
        $mep = $this->staff('validator', 'MEP');
        $admin = $this->staff('admin');
        $tr = $this->staff('validator', 'TR');
        $tenant = $this->staff('tenant');

        $detailUrl = route('staff.work-permits.show', $permit->public_token);
        $documentUrl = URL::temporarySignedRoute(
            'staff.work-permits.document',
            now()->addMinutes(5),
            ['token' => $permit->public_token],
        );

        $this->actingAs($mep)->get($detailUrl)->assertOk()->assertSee($permit->permit_number);
        $this->actingAs($admin)->get($detailUrl)->assertOk()->assertSee($permit->contractor_name);
        $this->actingAs($tr)->get($detailUrl)->assertForbidden();
        $this->actingAs($tenant)->get($detailUrl)->assertForbidden();
        $this->actingAs($mep)->get($documentUrl)->assertOk();
        $previewUrl = URL::temporarySignedRoute(
            'staff.work-permits.document.preview',
            now()->addMinutes(5),
            ['token' => $permit->public_token],
        );
        $this->actingAs($mep)
            ->get($previewUrl)
            ->assertOk()
            ->assertSee('data-document-back', false)
            ->assertSee(route('staff.work-permits.show', $permit->public_token), false);
        $this->actingAs($tenant)->get($documentUrl)->assertForbidden();
        $this->actingAs($mep)
            ->get(route('staff.work-permits.document', $permit->public_token))
            ->assertForbidden();
    }

    public function test_admin_can_view_work_permits_but_cannot_run_validator_actions(): void
    {
        $admin = $this->staff('admin');
        $mepPermit = WorkPermit::create($this->modelData());

        $this->actingAs($admin)
            ->get(route('staff.work-permits.show', $mepPermit->public_token))
            ->assertOk()
            ->assertDontSee(route('mep.work-permits.deposit', $mepPermit->public_token), false);
        $this->actingAs($admin)
            ->post(route('mep.work-permits.deposit', $mepPermit->public_token), [
                'deposit_required' => '0',
            ])
            ->assertForbidden();
        $this->assertSame('mep_review', $mepPermit->fresh()->status);

        $trPermit = WorkPermit::create(array_merge($this->modelData(), [
            'assigned_division' => 'TR',
            'status' => 'tr_review',
            'work_category' => 'general_cleaning',
        ]));
        $this->actingAs($admin)
            ->get(route('staff.work-permits.show', $trPermit->public_token))
            ->assertOk()
            ->assertDontSee(route('tr.work-permits.decision', $trPermit->public_token), false);
        $this->actingAs($admin)
            ->post(route('tr.work-permits.decision', $trPermit->public_token), [
                'decision' => 'approved',
            ])
            ->assertForbidden();
        $this->assertSame('tr_review', $trPermit->fresh()->status);

        $financePermit = WorkPermit::create(array_merge($this->modelData(), [
            'status' => 'payment_review',
            'deposit_required' => true,
            'deposit_amount' => 1000000,
        ]));
        $this->actingAs($admin)
            ->post(route('finance.work-permits.verify', $financePermit->public_token), [
                'decision' => 'verified',
            ])
            ->assertForbidden();
        $this->assertSame('payment_review', $financePermit->fresh()->status);
    }

    public function test_applicant_status_link_redirects_staff_to_their_authorized_layout(): void
    {
        $permit = WorkPermit::create($this->modelData());
        $admin = $this->staff('admin');
        $mep = $this->staff('validator', 'MEP');
        $statusUrl = route('work-permits.status', $permit->applicant_token);
        $staffUrl = route('staff.work-permits.show', $permit->public_token);

        $this->actingAs($admin)
            ->get($statusUrl)
            ->assertRedirect($staffUrl);
        $this->followingRedirects()
            ->get($statusUrl)
            ->assertOk()
            ->assertSee('aria-label="Navigasi Admin"', false)
            ->assertDontSee('Tindakan MEP');

        $this->actingAs($mep)
            ->get($statusUrl)
            ->assertRedirect($staffUrl);
        $this->followingRedirects()
            ->get($statusUrl)
            ->assertOk()
            ->assertSee('Tindakan Engineering');
    }

    public function test_mep_can_subscribe_to_push_and_open_its_realtime_channel(): void
    {
        $mep = $this->staff('validator', 'MEP');

        $this->actingAs($mep)
            ->postJson(route('mep.push-subscriptions.store'), [
                'endpoint' => 'https://push.example.test/mep-device',
                'keys' => [
                    'p256dh' => str_repeat('A', 87),
                    'auth' => str_repeat('B', 22),
                ],
                'content_encoding' => 'aes128gcm',
            ])
            ->assertCreated();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->actingAs($mep)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-validators.{$mep->id}",
            ])
            ->assertOk();
    }

    public function test_deposit_workflow_runs_from_mep_amount_to_finance_verification(): void
    {
        Event::fake([WorkPermitWorkflowUpdated::class]);
        Notification::fake();
        Storage::fake('local');

        $permit = WorkPermit::create($this->modelData());
        $mep = $this->staff('validator', 'MEP');
        $finance = $this->staff('validator', 'FIN');
        $tr = $this->staff('validator', 'TR');

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '1',
            'deposit_amount' => '5000000',
            'deposit_notes' => 'Risiko pekerjaan instalasi listrik.',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('awaiting_payment', $permit->status);
        $this->assertSame('5000000.00', $permit->deposit_amount);
        $this->assertTrue($permit->deposit_required);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'deposit_requested',
        ]);

        $this->actingAs($tr)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '0',
        ])->assertForbidden();

        $this->post(route('work-permits.payment-proof.store', $permit->applicant_token), [
            'payment_proof' => UploadedFile::fake()->image('transfer.jpg', 800, 500),
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('payment_review', $permit->status);
        Storage::disk('local')->assertExists($permit->payment_proof_path);
        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $finance->id,
            'work_permit_id' => $permit->id,
        ]);
        $this->assertDatabaseMissing('permit_notifications', [
            'user_id' => $tr->id,
            'work_permit_id' => $permit->id,
        ]);

        $this->actingAs($finance)->post(route('finance.work-permits.verify', $permit->public_token), [
            'decision' => 'verified',
            'notes' => 'Dana sesuai mutasi rekening.',
        ])->assertRedirect(route('finance.index'));

        $permit->refresh();
        $this->assertSame('mep_final_review', $permit->status);
        $this->assertSame($finance->id, $permit->payment_verified_by);
        $this->assertNotNull($permit->payment_verified_at);
    }

    public function test_mep_decision_notifies_the_linked_tenant(): void
    {
        Notification::fake();
        $tenant = $this->staff('tenant');
        $mep = $this->staff('validator', 'MEP');
        $permit = WorkPermit::create(array_merge($this->modelData(), [
            'user_id' => $tenant->id,
            'applicant_email' => 'tenant@example.com',
        ]));

        $this->actingAs($mep)->post(route('mep.work-permits.deposit', $permit->public_token), [
            'deposit_required' => '0',
            'deposit_notes' => 'Tidak ada pekerjaan berisiko deposit.',
        ])->assertRedirect();

        $this->assertDatabaseHas('permit_notifications', [
            'user_id' => $tenant->id,
            'work_permit_id' => $permit->id,
            'type' => 'workflow_update',
            'title' => 'Permohonan Izin Kerja Disetujui',
        ]);
    }

    public function test_mep_can_complete_work_and_record_deposit_refund(): void
    {
        Event::fake([WorkPermitWorkflowUpdated::class]);
        Notification::fake();
        Storage::fake('local');

        $mep = $this->staff('validator', 'MEP');
        $permit = WorkPermit::create(array_merge($this->modelData(), [
            'status' => 'mep_final_review',
            'security_deposit' => true,
            'deposit_required' => true,
            'deposit_amount' => 5000000,
            'payment_verified_at' => now(),
        ]));

        $this->actingAs($mep)->post(route('mep.work-permits.decision', $permit->public_token), [
            'decision' => 'approved',
            'review_notes' => 'Pekerjaan dapat dilaksanakan.',
        ])->assertRedirect();
        $this->assertSame('approved', $permit->fresh()->status);

        $this->actingAs($mep)->post(route('mep.work-permits.complete', $permit->public_token))->assertRedirect();
        $this->assertSame('completed', $permit->fresh()->status);

        $this->actingAs($mep)->post(route('mep.work-permits.refund.start', $permit->public_token))->assertRedirect();
        $this->assertSame('refund_processing', $permit->fresh()->status);

        $this->actingAs($mep)->post(route('mep.work-permits.refund.finish', $permit->public_token), [
            'refund_amount' => 5000000,
            'refund_proof' => UploadedFile::fake()->create('refund.pdf', 300, 'application/pdf'),
            'refund_notes' => 'Dikembalikan penuh.',
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('refunded', $permit->status);
        $this->assertSame('5000000.00', $permit->refund_amount);
        Storage::disk('local')->assertExists($permit->refund_proof_path);
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'refund_completed',
        ]);
    }

    public function test_random_applicant_link_and_private_financial_documents_are_protected(): void
    {
        Storage::fake('local');
        $permit = WorkPermit::create(array_merge($this->modelData(), [
            'status' => 'payment_review',
            'deposit_required' => true,
            'deposit_amount' => 1000000,
            'payment_proof_path' => 'permits/work/payment-proofs/test.pdf',
        ]));
        $this->putPdf($permit->payment_proof_path);
        $finance = $this->staff('validator', 'FIN');
        $tenant = $this->staff('tenant');

        $this->get(route('work-permits.status', str_repeat('X', 64)))->assertNotFound();
        $this->get(route('work-permits.status', $permit->applicant_token))->assertOk();
        $this->get(route('staff.work-permits.payment-proof', $permit->public_token))->assertRedirect(route('login'));

        $signedProof = URL::temporarySignedRoute('staff.work-permits.payment-proof', now()->addMinutes(5), [
            'token' => $permit->public_token,
        ]);
        $this->actingAs($tenant)->get($signedProof)->assertForbidden();
        $this->actingAs($finance)->get($signedProof)->assertOk();

        $permit->update(['refund_proof_path' => 'permits/work/refund-proofs/test.pdf']);
        Storage::disk('local')->put($permit->refund_proof_path, 'private-refund');
        $signedRefund = URL::temporarySignedRoute('staff.work-permits.refund-proof', now()->addMinutes(5), [
            'token' => $permit->public_token,
        ]);
        $this->actingAs($finance)->get($signedRefund)->assertForbidden();
    }

    public function test_only_the_owner_can_open_and_upload_proof_for_an_authenticated_application(): void
    {
        Event::fake([WorkPermitWorkflowUpdated::class]);
        Notification::fake();
        Storage::fake('local');

        $owner = $this->staff('tenant');
        $otherTenant = $this->staff('tenant');
        $permit = WorkPermit::create(array_merge($this->modelData(), [
            'user_id' => $owner->id,
            'status' => 'awaiting_payment',
            'deposit_required' => true,
            'deposit_amount' => 1500000,
        ]));

        $statusUrl = route('work-permits.status', $permit->applicant_token);
        $uploadUrl = route('work-permits.payment-proof.store', $permit->applicant_token);

        $this->get($statusUrl)->assertForbidden();
        $this->actingAs($otherTenant)->get($statusUrl)->assertForbidden();
        $this->actingAs($otherTenant)->post($uploadUrl, [
            'payment_proof' => UploadedFile::fake()->image('other-tenant.jpg'),
        ])->assertForbidden();

        $this->actingAs($owner)->get($statusUrl)->assertOk();
        $this->actingAs($owner)->post($uploadUrl, [
            'payment_proof' => UploadedFile::fake()->create('deposit.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $permit->refresh();
        $this->assertSame('payment_review', $permit->status);
        Storage::disk('local')->assertExists($permit->payment_proof_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        return [
            'submission_token' => $this->submissionToken('work'),
            'contractor_name' => 'Kontraktor Pengujian',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'pemohon@example.test',
            'workers' => [
                ['name' => 'Pekerja Pertama', 'identity_number' => 'ID-001'],
                ['name' => 'Pekerja Kedua', 'identity_number' => ''],
            ],
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'work_type' => 'Perbaikan instalasi listrik',
            'other_work_type' => 'other',
            'other_work_description' => 'Perbaikan instalasi listrik',
            'work_schedules' => ['inside_store', 'outside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => '1',
            'notes' => 'Koordinasi dengan petugas keamanan.',
            'id_doc_type' => 'ktp',
            'id_doc' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'consent' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelData(): array
    {
        return [
            'contractor_name' => 'Kontraktor Pengujian',
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'applicant_email' => 'pemohon@example.test',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
            'other_work_type' => 'other',
            'other_work_description' => 'Perbaikan instalasi listrik',
            'work_schedule' => 'inside_store',
            'work_schedules' => ['inside_store'],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'needs_water' => false,
            'security_deposit' => false,
            'id_doc_type' => 'ktp',
            'id_doc_path' => 'permits/work/id-docs/test.jpg',
            'status' => 'mep_review',
        ];
    }

    private function staff(string $role, ?string $division = null, bool $active = true): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('08##########'),
            'role' => $role,
            'division' => $division,
            'is_active' => $active,
        ]);
    }

    private function putImage(string $path): void
    {
        $image = UploadedFile::fake()->image('document.jpg', 800, 500);
        Storage::disk('local')->put($path, file_get_contents($image->getRealPath()));
    }

    private function putPdf(string $path): void
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Dokumen sensitif pengujian');
        Storage::disk('local')->put($path, $pdf->Output('S'));
    }
}
