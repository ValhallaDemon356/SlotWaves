<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            if (!Schema::hasColumn('flights', 'direction')) {
                $table->string('direction', 20)->default('arrival')->after('flight_type');
            }
            if (!Schema::hasColumn('flights', 'traffic_type')) {
                $table->string('traffic_type', 20)->default('domestic')->after('direction');
            }
            if (!Schema::hasColumn('flights', 'parse_status')) {
                $table->string('parse_status', 30)->default('valid')->after('traffic_type');
            }
            if (!Schema::hasColumn('flights', 'validation_status')) {
                $table->string('validation_status', 30)->default('valid')->after('parse_status');
            }
            if (!Schema::hasColumn('flights', 'validation_errors')) {
                $table->json('validation_errors')->nullable()->after('validation_status');
            }
            if (!Schema::hasColumn('flights', 'paired_flight_id')) {
                $table->unsignedBigInteger('paired_flight_id')->nullable()->after('slot_status');
            }

            // Indexes for fast scoped queries
            $table->index(['upload_id', 'flight_number'], 'idx_flights_upload_flnum');
            $table->index(['upload_id', 'scheduled_time'], 'idx_flights_upload_time');
            $table->index(['upload_id', 'direction'], 'idx_flights_upload_dir');
            $table->index(['upload_id', 'traffic_type'], 'idx_flights_upload_traffic');
            $table->index(['upload_id', 'validation_status'], 'idx_flights_upload_val');
        });

        Schema::table('uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('uploads', 'total_rows')) {
                $table->unsignedInteger('total_rows')->default(0)->after('airport_id');
            }
            if (!Schema::hasColumn('uploads', 'valid_rows')) {
                $table->unsignedInteger('valid_rows')->default(0)->after('total_rows');
            }
            if (!Schema::hasColumn('uploads', 'invalid_rows')) {
                $table->unsignedInteger('invalid_rows')->default(0)->after('valid_rows');
            }
            if (!Schema::hasColumn('uploads', 'duplicate_rows')) {
                $table->unsignedInteger('duplicate_rows')->default(0)->after('invalid_rows');
            }
            if (!Schema::hasColumn('uploads', 'parsing_confidence')) {
                $table->decimal('parsing_confidence', 5, 2)->default(100.00)->after('duplicate_rows');
            }
            if (!Schema::hasColumn('uploads', 'validation_summary')) {
                $table->json('validation_summary')->nullable()->after('parsing_confidence');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropIndex('idx_flights_upload_flnum');
            $table->dropIndex('idx_flights_upload_time');
            $table->dropIndex('idx_flights_upload_dir');
            $table->dropIndex('idx_flights_upload_traffic');
            $table->dropIndex('idx_flights_upload_val');

            $table->dropColumn([
                'direction',
                'traffic_type',
                'parse_status',
                'validation_status',
                'validation_errors',
                'paired_flight_id',
            ]);
        });

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn([
                'total_rows',
                'valid_rows',
                'invalid_rows',
                'duplicate_rows',
                'parsing_confidence',
                'validation_summary',
            ]);
        });
    }
};
