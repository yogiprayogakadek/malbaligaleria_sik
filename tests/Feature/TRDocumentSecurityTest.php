<?php

namespace Tests\Feature;

use App\Models\LoadingPermit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class TRDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_can_open_document_with_a_valid_temporary_signature(): void
    {
        Storage::fake('local');
        $validator = $this->createUser('validator', 'TR', '082200000010');
        $permit = $this->createPermit();
        Storage::disk('local')->put($permit->id_doc_path, 'private document');

        $response = $this->actingAs($validator)->get($this->signedDocumentUrl($permit));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_unsigned_or_tampered_document_urls_are_rejected(): void
    {
        Storage::fake('local');
        $validator = $this->createUser('validator', 'TR', '082200000011');
        $permit = $this->createPermit();
        Storage::disk('local')->put($permit->id_doc_path, 'private document');

        $this->actingAs($validator)
            ->get(route('tr.id-doc', ['documentToken' => $permit->document_token]))
            ->assertForbidden();

        $signedUrl = $this->signedDocumentUrl($permit);
        $tamperedUrl = route('tr.id-doc', ['documentToken' => Str::random(64)])
            .'?'.parse_url($signedUrl, PHP_URL_QUERY);

        $this->actingAs($validator)->get($tamperedUrl)->assertForbidden();
    }

    public function test_tenant_cannot_open_a_document_even_with_a_valid_signature(): void
    {
        Storage::fake('local');
        $tenant = $this->createUser('tenant', null, '082200000012');
        $permit = $this->createPermit();
        Storage::disk('local')->put($permit->id_doc_path, 'private document');

        $this->actingAs($tenant)
            ->get($this->signedDocumentUrl($permit))
            ->assertForbidden();
    }

    private function createUser(string $role, ?string $division, string $phone): User
    {
        return User::factory()->create(compact('role', 'division', 'phone'));
    }

    private function createPermit(): LoadingPermit
    {
        return LoadingPermit::create([
            'permit_number' => 'MBG/SIK/TEST/'.Str::upper(Str::random(8)),
            'tenant_name' => 'Tenant Pengujian',
            'applicant_name' => 'Pemohon Pengujian',
            'applicant_phone' => '081200000001',
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
    }

    private function signedDocumentUrl(LoadingPermit $permit): string
    {
        return URL::temporarySignedRoute(
            'tr.id-doc',
            now()->addMinutes(5),
            ['documentToken' => $permit->document_token],
        );
    }
}
