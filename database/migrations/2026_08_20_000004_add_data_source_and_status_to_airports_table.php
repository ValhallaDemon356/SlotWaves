<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            if (!Schema::hasColumn('airports', 'data_source')) {
                $table->string('data_source', 50)->default('INJOURNEY_AIRPORTS')->after('source');
            }
            if (!Schema::hasColumn('airports', 'status')) {
                $table->string('status', 50)->default('active')->after('usage_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            if (Schema::hasColumn('airports', 'data_source')) {
                $table->dropColumn('data_source');
            }
            if (Schema::hasColumn('airports', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
