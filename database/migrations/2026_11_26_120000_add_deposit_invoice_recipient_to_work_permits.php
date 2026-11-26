<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->string('deposit_invoice_recipient_name', 200)->nullable()->after('deposit_notes');
            $table->text('deposit_invoice_recipient_address')->nullable()->after('deposit_invoice_recipient_name');
            $table->string('deposit_invoice_recipient_source', 20)->nullable()->after('deposit_invoice_recipient_address');
            $table->foreignId('deposit_invoice_recipient_confirmed_by')
                ->nullable()
                ->after('deposit_invoice_recipient_source')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('deposit_invoice_recipient_confirmed_at')
                ->nullable()
                ->after('deposit_invoice_recipient_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropForeign(['deposit_invoice_recipient_confirmed_by']);
            $table->dropColumn([
                'deposit_invoice_recipient_name',
                'deposit_invoice_recipient_address',
                'deposit_invoice_recipient_source',
                'deposit_invoice_recipient_confirmed_by',
                'deposit_invoice_recipient_confirmed_at',
            ]);
        });
    }
};
