<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_security_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('malware_scanning_enabled')->default(true);
            $table->string('disabled_reason', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('upload_security_settings')->insert([
            'id' => 1,
            'malware_scanning_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_security_settings');
    }
};
