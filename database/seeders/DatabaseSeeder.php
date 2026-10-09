<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with roles: admin, tenant, validators (TR, MEP, EP, CL).
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');
        $accounts = [
            [
                'name' => 'Administrator MBG',
                'email' => 'admin@malbaligaleria.com',
                'phone' => '08111111111',
                'role' => 'admin',
                'division' => null,
                'tenant_name' => null,
            ],
            [
                'name' => 'Validator TR (Loading)',
                'email' => 'validator.tr@malbaligaleria.com',
                'phone' => '08222222222',
                'role' => 'validator',
                'division' => 'TR',
                'tenant_name' => null,
            ],
            [
                'name' => 'Validator MEP (Kerja/SIK)',
                'email' => 'validator.mep@malbaligaleria.com',
                'phone' => '08333333333',
                'role' => 'validator',
                'division' => 'MEP',
                'tenant_name' => null,
            ],
            [
                'name' => 'Validator Finance',
                'email' => 'validator.finance@malbaligaleria.com',
                'phone' => '08888888888',
                'role' => 'validator',
                'division' => 'FIN',
                'tenant_name' => null,
            ],
            [
                'name' => 'Validator EP (Event)',
                'email' => 'validator.ep@malbaligaleria.com',
                'phone' => '08444444444',
                'role' => 'validator',
                'division' => 'EP',
                'tenant_name' => null,
            ],
            [
                'name' => 'Validator CL (Pameran)',
                'email' => 'validator.cl@malbaligaleria.com',
                'phone' => '08555555555',
                'role' => 'validator',
                'division' => 'CL',
                'tenant_name' => null,
            ],
            [
                'name' => 'Michael Raharja',
                'email' => 'tenant@starbucks.co.id',
                'phone' => '081234567890',
                'role' => 'tenant',
                'division' => null,
                'tenant_name' => 'Starbucks Coffee GF-12',
            ],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                $account + [
                    'is_active' => true,
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'password' => $defaultPassword,
                ],
            );
        }
    }
}
