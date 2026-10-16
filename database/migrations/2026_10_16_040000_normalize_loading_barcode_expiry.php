<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('loading_permits')
            ->select(['id', 'end_date'])
            ->whereNotNull('barcode_expires_at')
            ->orderBy('id')
            ->chunkById(200, function ($permits): void {
                foreach ($permits as $permit) {
                    DB::table('loading_permits')
                        ->where('id', $permit->id)
                        ->update([
                            'barcode_expires_at' => Carbon::parse($permit->end_date, config('app.timezone'))
                                ->endOfDay()
                                ->format('Y-m-d H:i:s'),
                        ]);
                }
            });
    }

    public function down(): void {}
};
