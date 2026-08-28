<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->foreignId('arrival_flight_id')->nullable()->constrained('flights')->onDelete('cascade');
            $table->foreignId('departure_flight_id')->nullable()->constrained('flights')->onDelete('cascade');
            $table->string('rotation_id', 50)->index();
            $table->unsignedTinyInteger('operating_day')->default(1); // 1 (Mon) .. 7 (Sun)
            $table->string('rotation_status', 30)->default('PAIRED'); // PAIRED, UNPAIRED_ARR, UNPAIRED_DEP
            $table->unsignedSmallInteger('turnaround_minutes')->nullable();
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['upload_id', 'operating_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_pairings');
    }
};
