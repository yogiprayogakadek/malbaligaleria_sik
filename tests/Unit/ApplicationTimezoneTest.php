<?php

namespace Tests\Unit;

use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_application_uses_bali_local_time(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('+08:00', now()->format('P'));
    }
}
