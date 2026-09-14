<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('upload_chunks')) {
            Schema::create('upload_chunks', function (Blueprint $table) {
                $table->id();
                $table->string('upload_token', 64)->index('idx_upload_chunks_token');
                $table->integer('chunk_index');
                $table->integer('total_chunks');
                $table->integer('chunk_size')->default(0);
                $table->binary('chunk_data');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['upload_token', 'chunk_index'], 'uniq_token_chunk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_chunks');
    }
};
