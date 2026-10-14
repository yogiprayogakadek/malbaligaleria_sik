<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->boolean('includes_loading_material')->default(false)->after('work_type');
            $table->boolean('is_rooftop_work')->default(false)->after('includes_loading_material');
            $table->string('rooftop_network_type', 40)->nullable()->after('is_rooftop_work');
            $table->boolean('is_atm_repair')->default(false)->after('rooftop_network_type');
            $table->string('assignment_letter_path')->nullable()->after('id_doc_type');
            $table->string('assignment_letter_type', 10)->nullable()->after('assignment_letter_path');
        });

        Schema::table('work_permit_workers', function (Blueprint $table): void {
            $table->string('name', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('work_permit_workers')->whereNull('name')->update(['name' => '-']);

        Schema::table('work_permit_workers', function (Blueprint $table): void {
            $table->string('name', 120)->nullable(false)->change();
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropColumn([
                'includes_loading_material',
                'is_rooftop_work',
                'rooftop_network_type',
                'is_atm_repair',
                'assignment_letter_path',
                'assignment_letter_type',
            ]);
        });
    }
};
