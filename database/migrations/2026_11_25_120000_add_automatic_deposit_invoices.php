<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_invoice_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 20)->default('manual');
            $table->text('payment_instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('deposit_invoice_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setting_id')->constrained('deposit_invoice_settings')->cascadeOnDelete();
            $table->string('bank_name', 100);
            $table->text('account_number');
            $table->string('account_holder', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('deposit_invoice_number', 60)->nullable()->unique()->after('deposit_invoice_uploaded_at');
            $table->timestamp('deposit_invoice_issued_at')->nullable()->after('deposit_invoice_number');
            $table->date('deposit_invoice_due_date')->nullable()->after('deposit_invoice_issued_at');
            $table->string('deposit_invoice_source', 20)->nullable()->after('deposit_invoice_due_date');
            $table->string('deposit_invoice_sha256', 64)->nullable()->after('deposit_invoice_source');
        });

        DB::table('deposit_invoice_settings')->insert([
            'id' => 1,
            'mode' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropUnique(['deposit_invoice_number']);
            $table->dropColumn([
                'deposit_invoice_number',
                'deposit_invoice_issued_at',
                'deposit_invoice_due_date',
                'deposit_invoice_source',
                'deposit_invoice_sha256',
            ]);
        });

        Schema::dropIfExists('deposit_invoice_accounts');
        Schema::dropIfExists('deposit_invoice_settings');
    }
};
