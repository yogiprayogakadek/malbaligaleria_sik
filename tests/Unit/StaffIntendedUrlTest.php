<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StaffIntendedUrl;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

class StaffIntendedUrlTest extends TestCase
{
    public function test_it_accepts_only_same_host_paths_allowed_for_the_staff_role(): void
    {
        $user = new User(['role' => 'validator', 'division' => 'TR']);
        $service = new StaffIntendedUrl;

        $allowed = $this->requestWithIntended('https://sik.example.test/tr/MBG/SIK/X/1');
        $this->assertSame(
            'https://sik.example.test/tr/MBG/SIK/X/1',
            $service->pull($allowed, $user),
        );

        $wrongRole = $this->requestWithIntended('https://sik.example.test/admin/loading/MBG/SIK/X/1');
        $this->assertNull($service->pull($wrongRole, $user));

        $external = $this->requestWithIntended('https://attacker.example/tr/MBG/SIK/X/1');
        $this->assertNull($service->pull($external, $user));

        $wrongPort = $this->requestWithIntended('https://sik.example.test:8443/tr/MBG/SIK/X/1');
        $this->assertNull($service->pull($wrongPort, $user));
    }

    private function requestWithIntended(string $intended): Request
    {
        $request = Request::create('https://sik.example.test/staff-access');
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $session->put('url.intended', $intended);
        $request->setLaravelSession($session);

        return $request;
    }
}
