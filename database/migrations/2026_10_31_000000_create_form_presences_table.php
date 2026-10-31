<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_presences', function (Blueprint $table) {
            $table->id();
            $table->string('form_type', 24);
            $table->char('visitor_hash', 64);
            $table->char('tab_hash', 64);
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['form_type', 'tab_hash']);
            $table->index(['form_type', 'expires_at']);
            $table->index(['visitor_hash', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_presences');
    }
};
