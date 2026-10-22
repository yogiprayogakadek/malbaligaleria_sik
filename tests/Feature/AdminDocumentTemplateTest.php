<?php

namespace Tests\Feature;

use App\Contracts\MalwareScanner;
use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSetting;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Services\PermitPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class AdminDocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequireSensitiveActionConfirmation::class);
        $this->app->instance(MalwareScanner::class, new class implements MalwareScanner
        {
            public function scan(UploadedFile $file, string $field): void {}
        });
        Storage::fake('local');
        Cache::forget('document-converter:unavailable');
    }

    public function test_admin_can_upload_versioned_docx_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001001']);

        $this->actingAs($admin)->post(route('admin.settings.document-templates.store'), [
            'permit_type' => 'loading',
            'template' => $this->docxUpload('loading-v1.docx'),
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $first = DocumentTemplate::query()->sole();
        $this->assertSame(1, $first->version);
        $this->assertTrue($first->is_active);
        $this->assertContains('permit_number', $first->placeholders);
        Storage::disk('local')->assertExists($first->storage_path);

        $this->actingAs($admin)->post(route('admin.settings.document-templates.store'), [
            'permit_type' => 'loading',
            'template' => $this->docxUpload('loading-v2.docx'),
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, DocumentTemplate::query()->count());
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue(DocumentTemplate::query()->where('version', 2)->sole()->is_active);
    }

    public function test_converter_settings_require_private_ip_token_and_active_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001002']);

        $this->actingAs($admin)->put(route('admin.settings.document-templates.update'), $this->settings([
            'converter_host' => '8.8.8.8',
        ]))->assertSessionHasErrors('converter_host');

        $this->actingAs($admin)->put(route('admin.settings.document-templates.update'), $this->settings([
            'converter_token' => str_repeat('a', 64),
            'loading_renderer' => 'libreoffice',
        ]))->assertSessionHasErrors('loading_renderer');
    }

    public function test_non_admin_cannot_open_document_template_settings(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'phone' => '081200001009']);

        $this->actingAs($tenant)
            ->get(route('admin.settings.document-templates.index'))
            ->assertForbidden();
    }

    public function test_upload_rejects_unknown_placeholders(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001010']);

        $this->actingAs($admin)->post(route('admin.settings.document-templates.store'), [
            'permit_type' => 'loading',
            'template' => UploadedFile::fake()->createWithContent(
                'unknown.docx',
                $this->docxContent('Nomor: ${permit_number} ${password_database}'),
            ),
            'current_password' => 'password',
        ])->assertSessionHasErrors('template');

        $this->assertDatabaseCount('document_templates', 0);
    }

    public function test_admin_can_save_and_test_converter_connection(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '081200001003']);
        $template = $this->storedTemplate('loading');

        $this->actingAs($admin)->put(route('admin.settings.document-templates.update'), $this->settings([
            'converter_token' => str_repeat('b', 64),
            'loading_renderer' => 'libreoffice',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        Http::fake(['http://192.168.0.249:3000/health' => Http::response(['status' => 'ok', 'version' => '24.2'], 200)]);
        $this->actingAs($admin)->post(route('admin.settings.document-templates.test'), [
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');

        $setting = DocumentTemplateSetting::query()->sole();
        $this->assertTrue($setting->last_test_succeeded);
        $this->assertSame('libreoffice', $setting->loading_renderer);
        $this->assertTrue($template->is_active);
    }

    public function test_libreoffice_pdf_is_sealed_when_converter_succeeds(): void
    {
        $this->storedTemplate('loading');
        DocumentTemplateSetting::query()->create($this->persistedSettings(['loading_renderer' => 'libreoffice']));
        Http::fake(['http://192.168.0.249:3000/convert' => Http::response('%PDF-1.4 converted-template', 200, ['Content-Type' => 'application/pdf'])]);
        $permit = $this->loadingPermit();

        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();

        $this->assertSame('%PDF-1.4 converted-template', Storage::disk('local')->get($permit->official_document_path));
        $this->assertSame(hash('sha256', '%PDF-1.4 converted-template'), $permit->official_document_sha256);
    }

    public function test_default_renderer_is_used_when_converter_fails_and_fallback_is_enabled(): void
    {
        $this->storedTemplate('loading');
        DocumentTemplateSetting::query()->create($this->persistedSettings(['loading_renderer' => 'libreoffice']));
        Http::fake(['http://192.168.0.249:3000/convert' => Http::response(['detail' => 'offline'], 503)]);
        $permit = $this->loadingPermit();

        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();

        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($permit->official_document_path));
        $this->assertTrue(Cache::has('document-converter:unavailable'));
    }

    public function test_tampered_docx_template_falls_back_to_default_renderer(): void
    {
        $template = $this->storedTemplate('loading');
        DocumentTemplateSetting::query()->create($this->persistedSettings(['loading_renderer' => 'libreoffice']));
        Storage::disk('local')->put($template->storage_path, 'tampered');
        $permit = $this->loadingPermit();

        app(PermitPdfService::class)->sealLoadingPermit($permit);
        $permit->refresh();

        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($permit->official_document_path));
        Http::assertNothingSent();
    }

    private function docxUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->docxContent());
    }

    private function docxContent(string $text = 'Nomor: ${permit_number} - ${applicant_name}'): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);
        $path = tempnam(sys_get_temp_dir(), 'mbg-test-docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $content = file_get_contents($path);
        @unlink($path);

        return (string) $content;
    }

    private function storedTemplate(string $type): DocumentTemplate
    {
        $path = "document-templates/{$type}/test.docx";
        $content = $this->docxContent();
        Storage::disk('local')->put($path, $content);

        return DocumentTemplate::query()->create([
            'permit_type' => $type,
            'version' => 1,
            'original_name' => 'test.docx',
            'storage_path' => $path,
            'sha256' => hash('sha256', $content),
            'size' => strlen($content),
            'placeholders' => ['permit_number', 'applicant_name'],
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'converter_enabled' => '1',
            'converter_scheme' => 'http',
            'converter_host' => '192.168.0.249',
            'converter_port' => 3000,
            'converter_token' => '',
            'converter_timeout' => 60,
            'loading_renderer' => 'default',
            'work_renderer' => 'default',
            'event_renderer' => 'default',
            'fallback_enabled' => '1',
            'current_password' => 'password',
        ], $overrides);
    }

    private function persistedSettings(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'converter_enabled' => true,
            'converter_scheme' => 'http',
            'converter_host' => '192.168.0.249',
            'converter_port' => 3000,
            'converter_token' => str_repeat('c', 64),
            'converter_timeout' => 60,
            'loading_renderer' => 'default',
            'work_renderer' => 'default',
            'event_renderer' => 'default',
            'fallback_enabled' => true,
        ], $overrides);
    }

    private function loadingPermit(): LoadingPermit
    {
        return LoadingPermit::query()->create([
            'permit_number' => 'MBG/SIK/TEMPLATE/'.Str::upper(Str::random(6)),
            'tenant_name' => 'Tenant Template',
            'applicant_name' => 'Pemohon Template',
            'applicant_phone' => '081200000001',
            'applicant_email' => 'template@example.com',
            'direction' => 'in',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'movement_time' => '22:30',
            'item_count' => 2,
            'item_unit' => 'koli',
            'item_description' => 'Barang pengujian template',
            'id_doc_path' => 'permits/id-docs/test.jpg',
            'id_doc_type' => 'ktp',
            'status' => 'approved',
            'barcode_token' => hash('sha256', Str::random(64)),
            'barcode_expires_at' => now()->addDay(),
            'reviewed_at' => now(),
        ]);
    }
}
