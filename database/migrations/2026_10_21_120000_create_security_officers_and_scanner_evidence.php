<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_officers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('officer_id', 60)->unique();
            $table->string('whatsapp', 20)->unique();
            $table->string('pin_hash');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('pin_rotated_at')->nullable();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('security_gate_events', function (Blueprint $table): void {
            $table->foreignId('security_officer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('evidence_path')->nullable()->after('accuracy');
            $table->char('evidence_sha256', 64)->nullable()->after('evidence_path');
        });
    }

    public function down(): void
    {
        Schema::table('security_gate_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('security_officer_id');
            $table->dropColumn(['evidence_path', 'evidence_sha256']);
        });

        Schema::dropIfExists('security_officers');
    }
};
