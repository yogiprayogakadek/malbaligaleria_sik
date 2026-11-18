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
            $table->text('division_needs_notes')->nullable()->after('review_notes');
            $table->string('deposit_invoice_path')->nullable()->after('deposit_set_at');
            $table->string('deposit_invoice_type', 100)->nullable()->after('deposit_invoice_path');
            $table->foreignId('deposit_invoice_uploaded_by')->nullable()->after('deposit_invoice_type')->constrained('users')->nullOnDelete();
            $table->timestamp('deposit_invoice_uploaded_at')->nullable()->after('deposit_invoice_uploaded_by');
        });

        Schema::create('applicant_support_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('contact_name', 120)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->timestamps();
        });

        DB::table('applicant_support_settings')->insert([
            'id' => 1,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('work_permit_duration_settings')->update([
            'fit_out_days' => 30,
            'demolition_days' => 7,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_support_settings');

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deposit_invoice_uploaded_by');
            $table->dropColumn(['division_needs_notes', 'deposit_invoice_path', 'deposit_invoice_type', 'deposit_invoice_uploaded_at']);
        });

        DB::table('work_permit_duration_settings')->update(['demolition_days' => 14, 'updated_at' => now()]);
    }
};
