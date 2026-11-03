<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validator_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('validator_id')->constrained('users')->cascadeOnDelete();
            $table->string('division', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['division', 'starts_on', 'ends_on'], 'validator_delegations_active_index');
            $table->index(['validator_id', 'starts_on', 'ends_on'], 'validator_delegations_validator_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validator_delegations');
    }
};
