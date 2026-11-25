<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_gate_events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('item_quantity')->nullable()->after('event');
            $table->string('security_note', 1000)->nullable()->after('item_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('security_gate_events', function (Blueprint $table): void {
            $table->dropColumn(['item_quantity', 'security_note']);
        });
    }
};
