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

        // 1. Administrator
        User::updateOrCreate(
            ['email' => 'admin@malbaligaleria.com'],
            [
                'name'        => 'Administrator MBG',
                'phone'       => '08111111111',
                'role'        => 'admin',
                'division'    => null,
                'tenant_name' => null,
                'password'    => $defaultPassword,
            ]
        );

        // 2. Validator Tenant Relationship (TR) - Loading / Unloading
        User::updateOrCreate(
            ['email' => 'validator.tr@malbaligaleria.com'],
            [
                'name'        => 'Validator TR (Loading)',
                'phone'       => '08222222222',
                'role'        => 'validator',
                'division'    => 'TR',
                'tenant_name' => null,
                'password'    => $defaultPassword,
            ]
        );

        // 3. Validator MEP - Surat Izin Kerja (SIK)
        User::updateOrCreate(
            ['email' => 'validator.mep@malbaligaleria.com'],
            [
                'name'        => 'Validator MEP (Kerja/SIK)',
                'phone'       => '08333333333',
                'role'        => 'validator',
                'division'    => 'MEP',
                'tenant_name' => null,
                'password'    => $defaultPassword,
            ]
        );

        // 4. Validator Event Promotion (EP) - Surat Izin Event
        User::updateOrCreate(
            ['email' => 'validator.ep@malbaligaleria.com'],
            [
                'name'        => 'Validator EP (Event)',
                'phone'       => '08444444444',
                'role'        => 'validator',
                'division'    => 'EP',
                'tenant_name' => null,
                'password'    => $defaultPassword,
            ]
        );

        // 5. Validator Casual Leasing (CL) - Surat Izin Pameran
        User::updateOrCreate(
            ['email' => 'validator.cl@malbaligaleria.com'],
            [
                'name'        => 'Validator CL (Pameran)',
                'phone'       => '08555555555',
                'role'        => 'validator',
                'division'    => 'CL',
                'tenant_name' => null,
                'password'    => $defaultPassword,
            ]
        );

        // 6. Contoh Akun Tenant
        User::updateOrCreate(
            ['email' => 'tenant@starbucks.co.id'],
            [
                'name'        => 'Michael Raharja',
                'phone'       => '081234567890',
                'role'        => 'tenant',
                'division'    => null,
                'tenant_name' => 'Starbucks Coffee GF-12',
                'password'    => $defaultPassword,
            ]
        );
    }
}
