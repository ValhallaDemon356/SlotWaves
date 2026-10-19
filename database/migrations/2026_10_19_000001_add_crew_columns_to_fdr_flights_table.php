<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fdr_flights', function (Blueprint $table) {
            if (!Schema::hasColumn('fdr_flights', 'operating_crew')) {
                $table->integer('operating_crew')->default(0)->after('pos_kg');
            }
            if (!Schema::hasColumn('fdr_flights', 'extra_crew')) {
                $table->integer('extra_crew')->default(0)->after('operating_crew');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fdr_flights', function (Blueprint $table) {
            if (Schema::hasColumn('fdr_flights', 'extra_crew')) {
                $table->dropColumn('extra_crew');
            }
            if (Schema::hasColumn('fdr_flights', 'operating_crew')) {
                $table->dropColumn('operating_crew');
            }
        });
    }
};
