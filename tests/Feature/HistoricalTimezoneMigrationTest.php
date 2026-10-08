<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalTimezoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_utc_timestamps_are_shifted_to_wita_once(): void
    {
        DB::table('mail_settings')->insert([
            'host' => 'mail.example.test',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'mailer@example.test',
            'password' => encrypt('secret'),
            'from_address' => 'mailer@example.test',
            'from_name' => 'Mailer',
            'timeout' => 10,
            'is_active' => true,
            'created_at' => '2026-09-30 02:22:00',
            'updated_at' => '2026-09-30 02:22:00',
        ]);

        $migration = require database_path('migrations/2026_09_30_120000_convert_existing_utc_timestamps_to_wita.php');
        $migration->up();

        $this->assertDatabaseHas('mail_settings', [
            'created_at' => '2026-09-30 10:22:00',
            'updated_at' => '2026-09-30 10:22:00',
        ]);

        $migration->down();

        $this->assertDatabaseHas('mail_settings', [
            'created_at' => '2026-09-30 02:22:00',
            'updated_at' => '2026-09-30 02:22:00',
        ]);
    }
}
