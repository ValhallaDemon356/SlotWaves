<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fdr_processing_jobs')) {
            Schema::create('fdr_processing_jobs', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->unsignedBigInteger('upload_id')->nullable()->index('idx_fdr_jobs_upload');
                $table->string('upload_token', 64)->index('idx_fdr_jobs_token');
                $table->string('filename');
                $table->string('stored_path');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('file_hash', 64)->nullable();
                $table->string('report_type', 32)->default('fdr');
                $table->string('status', 32)->default('QUEUED')->index('idx_fdr_jobs_status');
                $table->string('stage_label')->nullable();
                $table->integer('progress')->default(0);
                $table->integer('processed_rows')->default(0);
                $table->integer('total_rows')->default(0);
                $table->json('meta')->nullable();
                $table->json('diagnostics')->nullable();
                $table->text('error_message')->nullable();
                $table->text('result_url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fdr_processing_jobs');
    }
};
