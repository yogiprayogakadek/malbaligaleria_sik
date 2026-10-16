<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('permit_type', 30);
            $table->string('period', 30);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['permit_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_number_sequences');
    }
};
