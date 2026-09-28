<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('division', ['TR', 'MEP', 'FIN', 'EP', 'CL'])
                ->nullable()
                ->comment('Divisi validator per jenis verifikasi izin')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('division', ['TR', 'MEP', 'EP', 'CL'])->nullable()->change();
        });
    }
};
