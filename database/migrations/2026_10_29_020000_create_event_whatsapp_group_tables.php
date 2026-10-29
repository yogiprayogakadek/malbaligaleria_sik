<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_whatsapp_group_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('group_name', 120);
            $table->text('group_id');
            $table->string('group_id_hash', 64)->unique();
            $table->text('base_message');
            $table->timestamps();
        });

        Schema::create('event_whatsapp_group_members', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->text('phone');
            $table->string('phone_hash', 64)->unique();
            $table->boolean('mention_by_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_whatsapp_group_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('requirement_group', 80);
            $table->foreignId('event_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_whatsapp_group_rule_members', function (Blueprint $table): void {
            $table->foreignId('rule_id')->constrained('event_whatsapp_group_rules')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('event_whatsapp_group_members')->cascadeOnDelete();
            $table->primary(['rule_id', 'member_id']);
        });

        Schema::create('event_whatsapp_group_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('setting_id')->nullable()->constrained('event_whatsapp_group_settings')->nullOnDelete();
            $table->string('unique_key', 64)->unique();
            $table->text('group_id');
            $table->string('group_id_hash', 64)->index();
            $table->longText('message');
            $table->longText('mentions')->nullable();
            $table->string('filename', 180);
            $table->string('text_status', 20)->default('queued')->index();
            $table->string('file_status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('text_provider_message_id')->nullable();
            $table->string('file_provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique('work_permit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_whatsapp_group_deliveries');
        Schema::dropIfExists('event_whatsapp_group_rule_members');
        Schema::dropIfExists('event_whatsapp_group_rules');
        Schema::dropIfExists('event_whatsapp_group_members');
        Schema::dropIfExists('event_whatsapp_group_settings');
    }
};
