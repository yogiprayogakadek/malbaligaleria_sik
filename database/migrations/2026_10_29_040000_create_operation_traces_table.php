<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_traces', function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id')->unique();
            $table->string('operation', 80)->index();
            $table->string('route_name', 120)->nullable()->index();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('total_ms');
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedInteger('query_ms')->default(0);
            $table->unsignedSmallInteger('upload_count')->default(0);
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->string('slowest_step', 120)->nullable();
            $table->unsignedInteger('slowest_step_ms')->default(0);
            $table->json('steps');
            $table->foreignId('loading_permit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_permit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamps();

            $table->index(['operation', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_traces');
    }
};
