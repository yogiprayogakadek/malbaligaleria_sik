<?php

namespace Tests\Feature;

use App\Events\WorkPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use App\Models\PermitNotification;
use App\Models\User;
use App\Models\WorkPermit;
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
            ->assertSee('Boleh pilih keduanya')
            ->assertSee('Maksimal 7 hari kalender')
            ->assertSee('Setiap pekerja wajib membawa KTP')
            ->assertSee('Unggah foto KTP / SIM');
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
        Storage::disk('local')->put('permits/work/id-docs/test.jpg', 'private-document');

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
        $this->assertDatabaseHas('work_permit_status_logs', [
            'work_permit_id' => $permit->id,
            'action' => 'tr_decision',
            'to_status' => 'approved',
        ]);
    }

    public function test_work_permit_staff_pages_are_restricted_to_mep_and_admin(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('permits/work/id-docs/test.jpg', 'private-document');
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
        Storage::disk('local')->put($permit->payment_proof_path, 'private-payment');
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
            'contractor_name' => 'Kontraktor Pengujian',
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
            'applicant_name' => 'Penanggung Jawab',
            'applicant_phone' => '081234567890',
            'work_location' => 'Unit GF-12',
            'work_category' => 'other',
            'assigned_division' => 'MEP',
            'work_type' => 'Perbaikan instalasi listrik',
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
}
