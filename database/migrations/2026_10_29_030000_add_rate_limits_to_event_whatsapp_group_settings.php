<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_whatsapp_group_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('group_sync_max_attempts')->default(2)->after('base_message');
            $table->unsignedInteger('group_sync_decay_seconds')->default(60)->after('group_sync_max_attempts');
            $table->unsignedSmallInteger('outbound_max_attempts')->default(2)->after('group_sync_decay_seconds');
            $table->unsignedInteger('outbound_decay_seconds')->default(60)->after('outbound_max_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('event_whatsapp_group_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'group_sync_max_attempts',
                'group_sync_decay_seconds',
                'outbound_max_attempts',
                'outbound_decay_seconds',
            ]);
        });
    }
};
