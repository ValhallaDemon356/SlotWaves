<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            if (!Schema::hasColumn('airports', 'arrival_capacity')) {
                $table->unsignedSmallInteger('arrival_capacity')->default(6)->after('operating_status');
            }
            if (!Schema::hasColumn('airports', 'departure_capacity')) {
                $table->unsignedSmallInteger('departure_capacity')->default(6)->after('arrival_capacity');
            }
        });

        // Non-destructive data migration: copy existing aircraft_capacity to both arrival_capacity and departure_capacity
        try {
            DB::table('airports')
                ->whereNotNull('aircraft_capacity')
                ->update([
                    'arrival_capacity'   => DB::raw('aircraft_capacity'),
                    'departure_capacity' => DB::raw('aircraft_capacity'),
                ]);
        } catch (\Throwable $e) {
            // Safe fallback
        }
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $cols = ['arrival_capacity', 'departure_capacity'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('airports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
