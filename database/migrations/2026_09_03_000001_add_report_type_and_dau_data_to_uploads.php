<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('uploads', 'report_type')) {
                $table->string('report_type', 50)->default('slot_schedule')->after('airport_id');
                $table->index('report_type', 'idx_uploads_report_type');
            }
            if (!Schema::hasColumn('uploads', 'report_data')) {
                // Use jsonb for PostgreSQL/Supabase, fallback to json
                $table->jsonb('report_data')->nullable()->after('validation_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            if (Schema::hasColumn('uploads', 'report_type')) {
                $table->dropIndex('idx_uploads_report_type');
                $table->dropColumn('report_type');
            }
            if (Schema::hasColumn('uploads', 'report_data')) {
                $table->dropColumn('report_data');
            }
        });
    }
};
