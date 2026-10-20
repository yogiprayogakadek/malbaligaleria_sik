<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('component', 80)->index();
            $table->string('severity', 20)->default('error');
            $table->text('detail');
            $table->dateTime('first_detected_at');
            $table->dateTime('last_detected_at');
            $table->dateTime('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
