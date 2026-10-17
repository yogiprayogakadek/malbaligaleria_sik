<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scanner_settings', function (Blueprint $table): void {
            $table->boolean('risk_monitoring_enabled')->default(true);
            $table->boolean('auto_block_enabled')->default(true);
            $table->unsignedSmallInteger('risk_threshold')->default(5);
            $table->unsignedSmallInteger('risk_window_minutes')->default(15);
            $table->unsignedSmallInteger('block_duration_minutes')->default(60);
            $table->boolean('risk_alerts_enabled')->default(true);
        });

        Schema::create('scanner_ip_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_hash', 64)->unique();
            $table->text('ip_address');
            $table->unsignedSmallInteger('trigger_count');
            $table->string('last_result', 40);
            $table->timestamp('blocked_until')->nullable()->index();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_ip_blocks');

        Schema::table('scanner_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'risk_monitoring_enabled',
                'auto_block_enabled',
                'risk_threshold',
                'risk_window_minutes',
                'block_duration_minutes',
                'risk_alerts_enabled',
            ]);
        });
    }
};
