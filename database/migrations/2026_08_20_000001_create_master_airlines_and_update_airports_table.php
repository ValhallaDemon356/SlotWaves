<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create airlines table ──────────────────────────────────────────
        if (!Schema::hasTable('airlines')) {
            Schema::create('airlines', function (Blueprint $table) {
                $table->id();
                $table->string('airline_code', 10)->unique();
                $table->string('airline_name', 255);
                $table->string('category', 50)->default('domestic'); // domestic, international
                $table->string('status', 50)->default('active');      // active, inactive, off
                $table->timestamps();
            });
        }

        // ── 2. Update airports table with required classification columns ─────
        Schema::table('airports', function (Blueprint $table) {
            if (!Schema::hasColumn('airports', 'province')) {
                $table->string('province', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('airports', 'region')) {
                $table->string('region', 50)->nullable()->after('province');
            }
            if (!Schema::hasColumn('airports', 'airport_type')) {
                $table->string('airport_type', 50)->default('domestic')->after('country');
            }
            if (!Schema::hasColumn('airports', 'management_type')) {
                $table->string('management_type', 100)->default('PT. Angkasa Pura Indonesia')->after('airport_type');
            }
            if (!Schema::hasColumn('airports', 'management_name')) {
                $table->string('management_name', 255)->nullable()->after('management_type');
            }
            if (!Schema::hasColumn('airports', 'is_international')) {
                $table->boolean('is_international')->default(false)->after('management_name');
            }
            if (!Schema::hasColumn('airports', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_international');
            }
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $columns = [
                'province', 'region', 'airport_type', 'management_type',
                'management_name', 'is_international', 'is_active'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('airports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('airlines');
    }
};
