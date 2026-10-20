<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('security_settings', 'login_alerts_enabled')) {
            Schema::table('security_settings', function (Blueprint $table): void {
                $table->boolean('login_alerts_enabled')->default(true)->after('staff_two_factor_required');
                $table->unsignedTinyInteger('failed_login_alert_threshold')->default(3)->after('login_alerts_enabled');
            });
        }

        Schema::create('trusted_login_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('client_signature', 64);
            $table->string('device_label', 80);
            $table->string('browser_label', 80);
            $table->text('last_ip')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::create('staff_login_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('device_label', 80);
            $table->string('browser_label', 80);
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedTinyInteger('attempt_count')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_login_events');
        Schema::dropIfExists('trusted_login_devices');

        Schema::table('security_settings', function (Blueprint $table): void {
            $table->dropColumn(['login_alerts_enabled', 'failed_login_alert_threshold']);
        });
    }
};
