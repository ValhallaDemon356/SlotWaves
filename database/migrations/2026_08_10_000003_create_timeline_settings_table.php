<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store operating hours setting per upload.
 * This is a DISPLAY/VIEWPORT setting only — it never modifies flight STA/STD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->unsignedTinyInteger('ops_start')->default(6);  // 06:00
            $table->unsignedTinyInteger('ops_end')->default(19);   // 19:00
            $table->timestamps();

            $table->unique('upload_id'); // one settings row per upload
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_settings');
    }
};
