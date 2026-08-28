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
            if (!Schema::hasColumn('airports', 'aircraft_capacity')) {
                $table->unsignedSmallInteger('aircraft_capacity')->default(6)->after('operating_status');
            }
            if (!Schema::hasColumn('airports', 'timezone')) {
                $table->string('timezone', 50)->default('Asia/Jakarta')->after('aircraft_capacity');
            }
            if (!Schema::hasColumn('airports', 'ops_start_time')) {
                $table->string('ops_start_time', 5)->default('06:00')->after('timezone');
            }
            if (!Schema::hasColumn('airports', 'ops_end_time')) {
                $table->string('ops_end_time', 5)->default('20:00')->after('ops_start_time');
            }
        });

        // Set realistic capacities and timezones for common airports
        $airportsConfig = [
            'BDO' => ['aircraft_capacity' => 6,  'timezone' => 'Asia/Jakarta',  'ops_start_time' => '06:00', 'ops_end_time' => '20:00'],
            'CGK' => ['aircraft_capacity' => 30, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '00:00', 'ops_end_time' => '23:59'],
            'DPS' => ['aircraft_capacity' => 20, 'timezone' => 'Asia/Makassar', 'ops_start_time' => '00:00', 'ops_end_time' => '23:59'],
            'SUB' => ['aircraft_capacity' => 15, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '00:00', 'ops_end_time' => '23:59'],
            'UPG' => ['aircraft_capacity' => 15, 'timezone' => 'Asia/Makassar', 'ops_start_time' => '00:00', 'ops_end_time' => '23:59'],
            'MDC' => ['aircraft_capacity' => 10, 'timezone' => 'Asia/Makassar', 'ops_start_time' => '06:00', 'ops_end_time' => '23:00'],
            'LOP' => ['aircraft_capacity' => 10, 'timezone' => 'Asia/Makassar', 'ops_start_time' => '06:00', 'ops_end_time' => '22:00'],
            'JOG' => ['aircraft_capacity' => 12, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '06:00', 'ops_end_time' => '22:00'],
            'SRG' => ['aircraft_capacity' => 10, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '06:00', 'ops_end_time' => '22:00'],
            'PKU' => ['aircraft_capacity' => 10, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '06:00', 'ops_end_time' => '22:00'],
            'KNO' => ['aircraft_capacity' => 18, 'timezone' => 'Asia/Jakarta',  'ops_start_time' => '00:00', 'ops_end_time' => '23:59'],
            'DJJ' => ['aircraft_capacity' => 8,  'timezone' => 'Asia/Jayapura', 'ops_start_time' => '06:00', 'ops_end_time' => '18:00'],
        ];

        foreach ($airportsConfig as $iata => $cfg) {
            DB::table('airports')->where('iata_code', $iata)->update($cfg);
        }
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $cols = ['aircraft_capacity', 'timezone', 'ops_start_time', 'ops_end_time'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('airports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
