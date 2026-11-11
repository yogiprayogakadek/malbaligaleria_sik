<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id', 80)->unique();
            $table->date('date')->index();
            $table->string('name', 160);
            $table->string('source', 120)->nullable();
            $table->unsignedSmallInteger('year')->index();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_holidays');
    }
};
