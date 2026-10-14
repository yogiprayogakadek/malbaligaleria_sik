<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('label', 120);
            $table->boolean('is_open')->default(false);
            $table->boolean('is_24_hours')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_schedule_exceptions');
    }
};
