<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fdr_upload_sessions')) {
            Schema::create('fdr_upload_sessions', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index('idx_fdr_sessions_user');
                $table->string('upload_token', 64)->unique('fdr_upload_sessions_upload_token_unique');
                $table->string('original_filename');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type', 128)->nullable();
                $table->string('file_hash', 64)->nullable()->index('idx_fdr_sessions_hash');
                $table->unsignedInteger('chunk_size')->default(0);
                $table->unsignedInteger('total_chunks')->default(0);
                $table->unsignedBigInteger('uploaded_bytes')->default(0);
                $table->json('uploaded_chunks')->nullable();
                $table->string('storage_bucket', 128)->nullable();
                $table->string('storage_path', 255)->nullable();
                $table->string('status', 32)->default('CREATED')->index('idx_fdr_sessions_status');
                $table->integer('progress')->default(0);
                $table->integer('last_confirmed_chunk')->nullable()->default(-1);
                $table->integer('failed_chunk')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fdr_upload_sessions');
    }
};
