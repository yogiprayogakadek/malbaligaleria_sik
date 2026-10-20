<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('unit_number', 50)->nullable();
            $table->string('floor', 50)->nullable();
            $table->string('pic_name', 120)->nullable();
            $table->string('pic_email', 120)->nullable();
            $table->string('pic_phone', 25)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['name', 'unit_number']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('tenant_name')->constrained()->nullOnDelete();
        });
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('work_permit_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('division', 10)->index();
            $table->unsignedTinyInteger('max_duration_days')->default(7);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $categories = [
            ['general_cleaning', 'General Cleaning', 'TR', 10],
            ['flyer_distribution', 'Sebar Flyer', 'TR', 20],
            ['stock_opname', 'Stock Opname', 'TR', 30],
            ['pest_control', 'Pest Control', 'TR', 40],
            ['kol_event', 'KOL Event', 'EP', 50],
            ['grand_opening', 'Grand Opening', 'EP', 60],
            ['other', 'Pekerjaan Lainnya', 'MEP', 100],
        ];
        foreach ($categories as [$code, $name, $division, $sortOrder]) {
            DB::table('work_permit_categories')->insert([
                'code' => $code,
                'name' => $name,
                'division' => $division,
                'max_duration_days' => 7,
                'is_active' => true,
                'is_system' => $code === 'other',
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('security_gate_events', function (Blueprint $table): void {
            $table->id();
            $table->string('permit_type', 20)->index();
            $table->unsignedBigInteger('permit_id')->index();
            $table->string('event', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('token_hash', 64);
            $table->char('ip_hash', 64)->nullable()->index();
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->index(['permit_type', 'permit_id', 'occurred_at']);
        });

        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('channel', 20)->index();
            $table->string('subject', 180)->nullable();
            $table->text('body');
            $table->json('allowed_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        DB::table('message_templates')->insert([
            [
                'key' => 'permit_submitted',
                'name' => 'Permohonan diterima',
                'channel' => 'all',
                'subject' => 'Permohonan Diterima',
                'body' => 'Permohonan {{permit_number}} telah diterima dan masuk ke antrean pemeriksaan {{division}}.',
                'allowed_variables' => json_encode(['permit_number', 'division', 'tenant_name']),
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'system_health_alert',
                'name' => 'Gangguan sistem',
                'channel' => 'webhook',
                'subject' => 'Gangguan {{component}}',
                'body' => '{{detail}}',
                'allowed_variables' => json_encode(['component', 'detail', 'detected_at']),
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::create('monitoring_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->text('webhook_url')->nullable();
            $table->text('signing_secret')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(8);
            $table->timestamps();
        });
        DB::table('monitoring_settings')->insert([
            'id' => 1,
            'enabled' => false,
            'timeout_seconds' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('security_settings', function (Blueprint $table): void {
            $table->boolean('sensitive_actions_require_2fa')->default(false);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('division', ['TR', 'MEP', 'FIN', 'EP', 'CL', 'SEC'])
                ->nullable()
                ->comment('Divisi validator per jenis verifikasi izin')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('division', ['TR', 'MEP', 'FIN', 'EP', 'CL'])->nullable()->change();
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::table('loading_permits', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('work_permits', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('security_settings', fn (Blueprint $table) => $table->dropColumn('sensitive_actions_require_2fa'));
        Schema::dropIfExists('monitoring_settings');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('security_gate_events');
        Schema::dropIfExists('work_permit_categories');
        Schema::dropIfExists('tenants');
    }
};
