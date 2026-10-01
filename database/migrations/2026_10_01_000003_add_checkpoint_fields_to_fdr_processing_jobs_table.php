<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fdr_processing_jobs')) {
            Schema::table('fdr_processing_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('fdr_processing_jobs', 'stage')) {
                    $table->string('stage', 64)->nullable()->after('status');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'current_offset')) {
                    $table->unsignedInteger('current_offset')->default(0)->after('total_rows');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'current_batch')) {
                    $table->unsignedInteger('current_batch')->default(0)->after('current_offset');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'failed_rows')) {
                    $table->unsignedInteger('failed_rows')->default(0)->after('current_batch');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'error_code')) {
                    $table->string('error_code', 64)->nullable()->after('failed_rows');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('result_url');
                }
                if (!Schema::hasColumn('fdr_processing_jobs', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('started_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fdr_processing_jobs')) {
            Schema::table('fdr_processing_jobs', function (Blueprint $table) {
                $columns = ['stage', 'current_offset', 'current_batch', 'failed_rows', 'error_code', 'started_at', 'completed_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('fdr_processing_jobs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
