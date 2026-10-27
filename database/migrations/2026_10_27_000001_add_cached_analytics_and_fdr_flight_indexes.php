<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable transaction wrapper to prevent pooler timeout on remote PostgreSQL/Supabase
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // 1. Add cached_analytics column to uploads table
        if (Schema::hasTable('uploads') && !Schema::hasColumn('uploads', 'cached_analytics')) {
            Schema::table('uploads', function (Blueprint $table) {
                $driver = DB::getDriverName();
                if ($driver === 'pgsql') {
                    $table->jsonb('cached_analytics')->nullable()->after('report_data');
                } else {
                    $table->longText('cached_analytics')->nullable()->after('report_data');
                }
            });
        }

        // 2. Ensure fdr_flights has columns airline, city_1, city_2 and high-performance composite indexes
        if (Schema::hasTable('fdr_flights')) {
            Schema::table('fdr_flights', function (Blueprint $table) {
                if (!Schema::hasColumn('fdr_flights', 'airline')) {
                    $table->string('airline', 50)->nullable()->after('airline_code');
                }
                if (!Schema::hasColumn('fdr_flights', 'city_1')) {
                    $table->string('city_1', 20)->nullable()->after('origin_airport');
                }
                if (!Schema::hasColumn('fdr_flights', 'city_2')) {
                    $table->string('city_2', 20)->nullable()->after('destination_airport');
                }
            });

            // Add high-performance indexing on foreign keys and frequently filtered columns
            Schema::table('fdr_flights', function (Blueprint $table) {
                try {
                    $table->index(['upload_id', 'leg'], 'idx_fdr_fl_up_leg');
                } catch (\Throwable $e) {}

                try {
                    $table->index(['upload_id', 'airline'], 'idx_fdr_fl_up_airline');
                } catch (\Throwable $e) {}

                try {
                    $table->index(['upload_id', 'delay_minutes'], 'idx_fdr_fl_up_delay');
                } catch (\Throwable $e) {}

                try {
                    $table->index(['upload_id', 'city_1', 'city_2'], 'idx_fdr_fl_up_cities');
                } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fdr_flights')) {
            Schema::table('fdr_flights', function (Blueprint $table) {
                try { $table->dropIndex('idx_fdr_fl_up_cities'); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_fdr_fl_up_delay'); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_fdr_fl_up_airline'); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_fdr_fl_up_leg'); } catch (\Throwable $e) {}

                if (Schema::hasColumn('fdr_flights', 'city_2')) {
                    $table->dropColumn('city_2');
                }
                if (Schema::hasColumn('fdr_flights', 'city_1')) {
                    $table->dropColumn('city_1');
                }
                if (Schema::hasColumn('fdr_flights', 'airline')) {
                    $table->dropColumn('airline');
                }
            });
        }

        if (Schema::hasTable('uploads') && Schema::hasColumn('uploads', 'cached_analytics')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->dropColumn('cached_analytics');
            });
        }
    }
};
