<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30)->index();
            $table->nullableMorphs('auditable');
            $table->string('route_name')->nullable()->index();
            $table->string('request_method', 10)->nullable();
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
