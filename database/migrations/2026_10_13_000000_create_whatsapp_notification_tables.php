<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url');
            $table->text('api_key');
            $table->string('session', 100)->default('default');
            $table->unsignedSmallInteger('timeout')->default(10);
            $table->boolean('is_active')->default(false);
            $table->boolean('notify_applicants')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications_enabled')->default(false)->after('is_active');
        });

        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('unique_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('permit_type', 30);
            $table->unsignedBigInteger('permit_id');
            $table->string('event', 80);
            $table->string('recipient_kind', 20);
            $table->string('recipient_name')->nullable();
            $table->text('recipient_phone');
            $table->string('recipient_phone_hash', 64)->index();
            $table->longText('message');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['permit_type', 'permit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_notifications_enabled');
        });

        Schema::dropIfExists('whatsapp_settings');
    }
};
