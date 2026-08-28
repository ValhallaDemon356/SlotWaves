<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('timeline_positions') && !Schema::hasColumn('timeline_positions', 'is_locked')) {
            Schema::table('timeline_positions', function (Blueprint $table) {
                $table->boolean('is_locked')->default(false)->after('color_hex');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('timeline_positions') && Schema::hasColumn('timeline_positions', 'is_locked')) {
            Schema::table('timeline_positions', function (Blueprint $table) {
                $table->dropColumn('is_locked');
            });
        }
    }
};
