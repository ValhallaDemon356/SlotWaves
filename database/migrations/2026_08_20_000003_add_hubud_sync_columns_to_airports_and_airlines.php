<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Enhance airports table with Hubud Kemenhub attributes ─────────
        Schema::table('airports', function (Blueprint $table) {
            if (!Schema::hasColumn('airports', 'icao_code')) {
                $table->string('icao_code', 10)->nullable()->after('iata_code')->index();
            }
            if (!Schema::hasColumn('airports', 'usage_type')) {
                $table->string('usage_type', 50)->nullable()->default('Komersial')->after('management_name');
            }
            if (!Schema::hasColumn('airports', 'classification')) {
                $table->string('classification', 50)->nullable()->after('usage_type');
            }
            if (!Schema::hasColumn('airports', 'data_incomplete')) {
                $table->boolean('data_incomplete')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('airports', 'source')) {
                $table->string('source', 100)->default('PROJECT_REFERENCE')->after('data_incomplete');
            }
            if (!Schema::hasColumn('airports', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('source');
            }
            if (!Schema::hasColumn('airports', 'source_checked_at')) {
                $table->timestamp('source_checked_at')->nullable()->after('source_url');
            }
        });

        // ── 2. Enhance airlines table with Hubud Kemenhub attributes ─────────
        Schema::table('airlines', function (Blueprint $table) {
            if (!Schema::hasColumn('airlines', 'organization_code')) {
                $table->string('organization_code', 50)->nullable()->after('airline_name')->index();
            }
            if (!Schema::hasColumn('airlines', 'country')) {
                $table->string('country', 100)->default('Indonesia')->after('category');
            }
            if (!Schema::hasColumn('airlines', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            if (!Schema::hasColumn('airlines', 'source')) {
                $table->string('source', 100)->default('PROJECT_REFERENCE')->after('is_active');
            }
            if (!Schema::hasColumn('airlines', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('source');
            }
            if (!Schema::hasColumn('airlines', 'source_checked_at')) {
                $table->timestamp('source_checked_at')->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $cols = ['icao_code', 'usage_type', 'classification', 'data_incomplete', 'source', 'source_url', 'source_checked_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('airports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('airlines', function (Blueprint $table) {
            $cols = ['organization_code', 'country', 'is_active', 'source', 'source_url', 'source_checked_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('airlines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
