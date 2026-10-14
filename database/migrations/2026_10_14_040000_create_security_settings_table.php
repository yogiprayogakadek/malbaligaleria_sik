<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('staff_two_factor_required')->default(true);
            $table->timestamps();
        });

        DB::table('security_settings')->insert([
            'id' => 1,
            'staff_two_factor_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
