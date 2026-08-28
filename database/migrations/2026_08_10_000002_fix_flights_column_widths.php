<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix column widths that were too narrow for real airport/aircraft data.
 *
 * aircraft_type(20) → (20) — already fine for "ATR 72", "B 738", etc.
 * origin(10)        → (60) — "HALIM PERDANAKUSUMA" is 19 chars; airports
 *                            like "SULTAN HASANUDDIN" are even longer.
 * destination(10)   → (60) — same reason.
 * airline_code(10)  → (10) — fine, IATA/ICAO codes are ≤4 chars.
 *
 * NOTE: The root fix is in PdfParser.php. This migration only ensures the
 * column is wide enough for the correctly-parsed values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('origin', 60)->nullable()->change();
            $table->string('destination', 60)->nullable()->change();
            $table->string('aircraft_type', 30)->nullable()->change();
            $table->text('raw_data')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('origin', 10)->nullable()->change();
            $table->string('destination', 10)->nullable()->change();
            $table->string('aircraft_type', 20)->nullable()->change();
            $table->dropColumn('raw_data');
        });
    }
};
