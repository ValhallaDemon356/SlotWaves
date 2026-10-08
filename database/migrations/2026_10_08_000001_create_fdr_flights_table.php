<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fdr_flights')) {
            Schema::create('fdr_flights', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('upload_id')->index('idx_fdr_fl_upload_id');
                $table->foreign('upload_id')->references('id')->on('uploads')->onDelete('cascade');

                $table->date('flight_date')->index('idx_fdr_fl_flight_date');
                $table->string('flight_number', 20)->index('idx_fdr_fl_flight_no');
                $table->string('flight_number_base', 20)->nullable();
                $table->string('flight_suffix', 10)->nullable();
                $table->string('airline_code', 10)->index('idx_fdr_fl_airline_code');
                $table->string('paired_flight_number', 20)->nullable();
                $table->string('aircraft_type', 100)->nullable();
                $table->string('registration_number', 20)->nullable();

                // Direction and Movement type
                $table->string('leg', 20)->nullable();
                $table->string('direction', 20)->index('idx_fdr_fl_direction'); // ARRIVAL / DEPARTURE
                $table->string('movement_type', 20)->default('SCHEDULED')->index('idx_fdr_fl_movement_type'); // SCHEDULED / UNSCHEDULED

                // Airports and Traffic
                $table->string('origin_airport', 10)->nullable()->index('idx_fdr_fl_origin');
                $table->string('destination_airport', 10)->nullable()->index('idx_fdr_fl_destination');
                $table->string('report_airport', 10)->default('CGK')->index('idx_fdr_fl_report_airport');
                $table->string('traffic_type', 20)->default('DOMESTIC')->index('idx_fdr_fl_traffic_type'); // DOMESTIC / INTERNATIONAL

                // Times and Datetimes
                $table->time('scheduled_time')->nullable()->index('idx_fdr_fl_sched_time');
                $table->time('actual_time')->nullable()->index('idx_fdr_fl_act_time');
                $table->timestamp('scheduled_datetime')->nullable()->index('idx_fdr_fl_sched_dt');
                $table->timestamp('actual_datetime')->nullable()->index('idx_fdr_fl_act_dt');
                $table->string('scheduled_time_str', 30)->nullable();
                $table->string('actual_time_str', 30)->nullable();

                // Hours and Delay
                $table->smallInteger('scheduled_hour')->nullable();
                $table->smallInteger('actual_hour')->nullable();
                $table->smallInteger('operational_hour')->default(0)->index('idx_fdr_fl_op_hour');
                $table->integer('delay_minutes')->default(0)->index('idx_fdr_fl_delay_min');

                // Status and Flags
                $table->string('status', 20)->default('ON_TIME')->index('idx_fdr_fl_status'); // ON_TIME / DELAYED / IRREGULAR / UNREALIZED
                $table->boolean('is_realized')->default(false)->index('idx_fdr_fl_is_realized');
                $table->boolean('is_irregular')->default(false);

                // Passengers and Load
                $table->integer('passenger_capacity')->default(0);
                $table->integer('passenger_load')->default(0);
                $table->decimal('load_factor', 5, 2)->nullable();
                $table->integer('pax_adult')->default(0);
                $table->integer('pax_child')->default(0);
                $table->integer('pax_infant')->default(0);
                $table->integer('pax_transit')->default(0);
                $table->integer('pax_transfer')->default(0);

                // Cargo, Baggage, Pos
                $table->decimal('cargo_kg', 12, 2)->default(0);
                $table->decimal('baggage_kg', 12, 2)->default(0);
                $table->decimal('pos_kg', 12, 2)->default(0);

                // Ground Operations
                $table->string('stand', 30)->nullable()->index('idx_fdr_fl_stand');
                $table->string('runway', 30)->nullable()->index('idx_fdr_fl_runway');
                $table->string('mtow', 20)->nullable();

                // Raw JSON payload for detail view
                $table->jsonb('raw_data')->nullable();

                $table->timestamps();

                // Composite indexes for ultra-fast dashboard queries
                $table->index(['upload_id', 'flight_date'], 'idx_fdr_fl_up_date');
                $table->index(['upload_id', 'airline_code'], 'idx_fdr_fl_up_air');
                $table->index(['upload_id', 'direction'], 'idx_fdr_fl_up_dir');
                $table->index(['upload_id', 'traffic_type'], 'idx_fdr_fl_up_traf');
                $table->index(['upload_id', 'is_realized'], 'idx_fdr_fl_up_real');
                $table->index(['upload_id', 'status'], 'idx_fdr_fl_up_stat');
                $table->index(['upload_id', 'flight_date', 'direction'], 'idx_fdr_fl_up_date_dir');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fdr_flights');
    }
};
