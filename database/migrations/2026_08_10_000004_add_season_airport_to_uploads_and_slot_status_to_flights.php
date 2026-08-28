<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add season and airport_id to uploads
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('season', 20)->default('summer')->after('stored_path'); // summer, winter
            $table->foreignId('airport_id')->nullable()->after('season')->constrained('airports')->nullOnDelete();
        });

        // Add slot_status and paired_flight_id to flights
        Schema::table('flights', function (Blueprint $table) {
            $table->enum('slot_status', ['available', 'used'])->default('available')->after('flight_type');
            $table->foreignId('paired_flight_id')->nullable()->after('slot_status')->constrained('flights')->nullOnDelete();
        });

        // Ensure key airports exist (CGK, BDO, HLP, KJT)
        $airports = [
            ['iata_code' => 'CGK', 'name' => 'Soekarno-Hatta', 'city' => 'Jakarta', 'country' => 'Indonesia'],
            ['iata_code' => 'BDO', 'name' => 'Husein Sastranegara', 'city' => 'Bandung', 'country' => 'Indonesia'],
            ['iata_code' => 'HLP', 'name' => 'Halim Perdanakusuma', 'city' => 'Jakarta', 'country' => 'Indonesia'],
            ['iata_code' => 'KJT', 'name' => 'Kertajati', 'city' => 'Majalengka', 'country' => 'Indonesia'],
        ];

        foreach ($airports as $ap) {
            DB::table('airports')->updateOrInsert(
                ['iata_code' => $ap['iata_code']],
                array_merge($ap, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['paired_flight_id']);
            $table->dropColumn(['slot_status', 'paired_flight_id']);
        });

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropForeign(['airport_id']);
            $table->dropColumn(['season', 'airport_id']);
        });
    }
};
