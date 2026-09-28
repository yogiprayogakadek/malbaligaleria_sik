<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_permits', function (Blueprint $table) {
            $table->id();
            $table->string('permit_number', 60)->unique();
            $table->string('public_token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contractor_name', 150);
            $table->string('applicant_name', 120);
            $table->string('applicant_phone', 25);
            $table->string('applicant_email', 120)->nullable();
            $table->string('work_location', 180);
            $table->string('work_type', 180);
            $table->string('work_schedule', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('needs_water')->default(false);
            $table->boolean('security_deposit')->default(false);
            $table->text('notes')->nullable();
            $table->string('id_doc_path');
            $table->string('id_doc_type', 10);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('work_permit_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_permit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('identity_number', 50)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['work_permit_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_permit_workers');
        Schema::dropIfExists('work_permits');
    }
};
