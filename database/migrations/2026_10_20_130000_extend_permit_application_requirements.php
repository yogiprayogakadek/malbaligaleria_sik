<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->string('instruction_letter_path')->nullable()->after('id_doc_type');
            $table->string('instruction_letter_type', 80)->nullable()->after('instruction_letter_path');
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('tenant_name', 150)->nullable()->after('contractor_name');
            $table->string('other_work_type', 30)->nullable()->after('work_type');
            $table->string('other_work_description', 180)->nullable()->after('other_work_type');
            $table->boolean('needs_lighting')->default(false)->after('needs_water');
        });

        Schema::create('work_permit_duration_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('default_days')->default(7);
            $table->unsignedTinyInteger('fit_out_days')->default(30);
            $table->unsignedTinyInteger('demolition_days')->default(14);
            $table->timestamps();
        });

        DB::table('work_permit_duration_settings')->insert([
            'id' => 1,
            'default_days' => 7,
            'fit_out_days' => 30,
            'demolition_days' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('work_permit_categories')
            ->whereIn('code', ['kol_event', 'grand_opening'])
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('users')
            ->where('role', 'validator')
            ->where('is_active', true)
            ->update(['whatsapp_notifications_enabled' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('work_permit_categories')
            ->whereIn('code', ['kol_event', 'grand_opening'])
            ->update(['is_active' => true, 'updated_at' => now()]);

        Schema::dropIfExists('work_permit_duration_settings');

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropColumn(['tenant_name', 'other_work_type', 'other_work_description', 'needs_lighting']);
        });

        Schema::table('loading_permits', function (Blueprint $table): void {
            $table->dropColumn(['instruction_letter_path', 'instruction_letter_type']);
        });
    }
};
