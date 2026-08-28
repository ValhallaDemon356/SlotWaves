<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->string('iata_code', 10)->nullable()->change();

            if (!Schema::hasColumn('airports', 'bandara_id')) {
                $table->unsignedInteger('bandara_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('airports', 'area')) {
                $table->string('area', 255)->nullable()->after('city');
            }
            if (!Schema::hasColumn('airports', 'operating_status')) {
                $table->string('operating_status', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('airports', 'latitude')) {
                $table->decimal('latitude', 11, 8)->nullable()->after('country');
            }
            if (!Schema::hasColumn('airports', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            if (Schema::hasColumn('airports', 'bandara_id')) {
                $table->dropColumn('bandara_id');
            }
            if (Schema::hasColumn('airports', 'area')) {
                $table->dropColumn('area');
            }
            if (Schema::hasColumn('airports', 'operating_status')) {
                $table->dropColumn('operating_status');
            }
            if (Schema::hasColumn('airports', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('airports', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
