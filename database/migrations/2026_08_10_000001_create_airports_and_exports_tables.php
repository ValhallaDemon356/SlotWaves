<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── airports ──────────────────────────────────────────────────────────
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code', 10)->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
        });

        // Seed common Indonesian airports
        DB::table('airports')->insert([
            ['iata_code' => 'BDO', 'name' => 'Husein Sastranegara',  'city' => 'Bandung',   'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'CGK', 'name' => 'Soekarno-Hatta',       'city' => 'Jakarta',   'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'DPS', 'name' => 'Ngurah Rai',           'city' => 'Denpasar',  'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'SUB', 'name' => 'Juanda',               'city' => 'Surabaya',  'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'UPG', 'name' => 'Sultan Hasanuddin',    'city' => 'Makassar',  'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'MDC', 'name' => 'Sam Ratulangi',        'city' => 'Manado',    'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'LOP', 'name' => 'Zainuddin Abdul Madjid', 'city' => 'Lombok', 'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'JOG', 'name' => 'Yogyakarta International', 'city' => 'Yogyakarta', 'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'SRG', 'name' => 'Ahmad Yani',           'city' => 'Semarang',  'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['iata_code' => 'PKU', 'name' => 'Sultan Syarif Kasim II', 'city' => 'Pekanbaru', 'country' => 'Indonesia', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── exports ───────────────────────────────────────────────────────────
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->string('type', 20)->default('combined'); // time, dos, combined
            $table->string('filename');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('airports');
    }
};
