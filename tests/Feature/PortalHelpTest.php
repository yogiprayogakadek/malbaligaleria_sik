<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWebsiteOperating;
use Tests\TestCase;

class PortalHelpTest extends TestCase
{
    public function test_help_page_presents_current_permit_guidance_and_shortcuts(): void
    {
        $this->withoutMiddleware(EnsureWebsiteOperating::class);

        $this->get(route('portal.help'))
            ->assertOk()
            ->assertSee('Panduan perizinan tenant')
            ->assertSee(route('loading.create'), false)
            ->assertSee(route('work-permits.create'), false)
            ->assertSee(route('portal.track'), false)
            ->assertSee('Maksimal 3 hari kalender')
            ->assertSee('Surat izin kerja umum maksimal 7 hari kalender')
            ->assertSee('Fit Out/Renovasi dan Pembongkaran mengikuti batas yang ditetapkan admin')
            ->assertSee('22.00 sampai 11.00 WITA')
            ->assertSee('Informasi yang sering dibutuhkan');
    }
}
