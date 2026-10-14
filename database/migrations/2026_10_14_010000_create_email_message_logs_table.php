<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_message_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->text('recipient');
            $table->char('recipient_hash', 64)->index();
            $table->string('notification_type');
            $table->string('event_key', 80)->nullable();
            $table->nullableMorphs('emailable');
            $table->string('subject')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_message_logs');
    }
};
