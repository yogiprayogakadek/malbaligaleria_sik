<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_invoice_settings', function (Blueprint $table): void {
            $table->string('issuer_name', 150)->default('Mal Bali Galeria')->after('mode');
            $table->string('issuer_title', 150)->default('Property Management')->after('issuer_name');
            $table->string('signature_method', 20)->default('barcode')->after('issuer_title');
            $table->string('signature_path')->nullable()->after('signature_method');
            $table->string('signature_sha256', 64)->nullable()->after('signature_path');
        });

        Schema::table('work_permits', function (Blueprint $table): void {
            $table->uuid('deposit_invoice_document_id')->nullable()->unique()->after('deposit_invoice_sha256');
            $table->string('deposit_invoice_issuer_name', 150)->nullable()->after('deposit_invoice_document_id');
            $table->string('deposit_invoice_issuer_title', 150)->nullable()->after('deposit_invoice_issuer_name');
            $table->string('deposit_invoice_signature_method', 20)->nullable()->after('deposit_invoice_issuer_title');
        });
    }

    public function down(): void
    {
        Schema::table('work_permits', function (Blueprint $table): void {
            $table->dropUnique(['deposit_invoice_document_id']);
            $table->dropColumn([
                'deposit_invoice_document_id',
                'deposit_invoice_issuer_name',
                'deposit_invoice_issuer_title',
                'deposit_invoice_signature_method',
            ]);
        });

        Schema::table('deposit_invoice_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'issuer_name',
                'issuer_title',
                'signature_method',
                'signature_path',
                'signature_sha256',
            ]);
        });
    }
};
