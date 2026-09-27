<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loading_permits', function (Blueprint $table) {
            $table->id();

            // Nomor surat unik: MBG/SIK/III/0001
            $table->string('permit_number', 40)->unique()->comment('Nomor surat: MBG/SIK/{Roman}/{Seq}');

            // Pemohon — bisa dari user yang login atau guest
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tenant_name', 120)->comment('Nama tenant / toko');
            $table->string('applicant_name', 120)->comment('Nama penanggung jawab / PIC');
            $table->string('applicant_phone', 25)->comment('Nomor HP PIC');
            $table->string('applicant_email', 120)->nullable();

            // Detail permohonan
            $table->enum('direction', ['in', 'out', 'both'])->comment('Arah: in = masuk, out = keluar, both = keduanya');
            $table->date('start_date')->comment('Tanggal mulai loading');
            $table->date('end_date')->comment('Tanggal selesai (maks. start + 3 hari)');
            $table->unsignedSmallInteger('item_count')->comment('Jumlah barang');
            $table->text('item_description')->comment('Deskripsi / keterangan barang');
            $table->string('vehicle_plate', 20)->nullable()->comment('Nomor polisi kendaraan');

            // Dokumen identitas (KTP/SIM)
            $table->string('id_doc_path')->comment('Path file KTP/SIM yang diupload');
            $table->string('id_doc_type', 10)->default('ktp')->comment('Jenis dokumen: ktp atau sim');

            // Status permohonan
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->comment('Catatan/alasan dari validator TR');
            $table->timestamp('reviewed_at')->nullable();

            // Token barcode untuk verifikasi surat (UUID-based, unik per surat)
            $table->string('barcode_token', 64)->nullable()->unique()->comment('Token unik untuk verifikasi surat');
            $table->timestamp('barcode_expires_at')->nullable()->comment('Masa berlaku surat izin');

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });

        // Tabel notifikasi in-app
        Schema::create('permit_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permit_id')->constrained('loading_permits')->cascadeOnDelete();
            $table->string('type', 30)->comment('approved | rejected | pending_review');
            $table->string('title', 120);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_notifications');
        Schema::dropIfExists('loading_permits');
    }
};
