<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── uploads ──────────────────────────────────────────────────────────
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // ── flights ──────────────────────────────────────────────────────────
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->string('flight_number', 20);
            $table->string('airline_code', 10)->nullable();
            $table->string('aircraft_type', 20)->nullable();
            $table->string('origin', 10)->nullable();
            $table->string('destination', 10)->nullable();
            
            // Standard slot times (HH:MM or HH:MM:SS)
            $table->time('scheduled_time'); 
            
            $table->string('operating_days', 7)->nullable(); // e.g. 1234567
            
            // flight_type: departure_domestic, departure_international, arrival_domestic, arrival_international
            $table->string('flight_type', 50); 
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        // ── timeline_positions ───────────────────────────────────────────────
        Schema::create('timeline_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->foreignId('flight_id')->constrained('flights')->onDelete('cascade');
            
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedTinyInteger('row')->default(0); // stacking row
            $table->unsignedSmallInteger('offset_minutes')->default(0);
            $table->string('color_hex', 10);
            $table->unsignedSmallInteger('duration_minutes')->default(45); // default block size for visual representation
            $table->string('section', 20); // departure, arrival
            $table->timestamps();

            $table->unique(['upload_id', 'flight_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_positions');
        Schema::dropIfExists('flights');
        Schema::dropIfExists('uploads');
    }
};
