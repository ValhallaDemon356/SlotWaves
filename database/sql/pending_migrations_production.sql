-- ============================================================================
-- SLOTWAVES — PENDING MIGRATIONS FOR SUPABASE PRODUCTION (zvtkphkjczjobvvtvhbm)
-- Target Database: PostgreSQL (Supabase Production)
-- Mode: Idempotent DDL (Additive Only, Zero DROP/TRUNCATE)
-- Total Migrations Covered: 19 migrations (from 2026_08_10_000004 to 2026_10_27_000001)
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Migration: 2026_08_10_000004_add_season_airport_to_uploads_and_slot_status_to_flights
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "uploads" 
    ADD COLUMN IF NOT EXISTS "season" VARCHAR(20) DEFAULT 'summer',
    ADD COLUMN IF NOT EXISTS "airport_id" BIGINT NULL REFERENCES "airports"("id") ON DELETE SET NULL;

ALTER TABLE IF EXISTS "flights"
    ADD COLUMN IF NOT EXISTS "slot_status" VARCHAR(20) DEFAULT 'available',
    ADD COLUMN IF NOT EXISTS "paired_flight_id" BIGINT NULL REFERENCES "flights"("id") ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- 2. Migration: 2026_08_11_000001_create_flight_pairings_table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "flight_pairings" (
    "id" BIGSERIAL PRIMARY KEY,
    "upload_id" BIGINT NOT NULL REFERENCES "uploads"("id") ON DELETE CASCADE,
    "arrival_flight_id" BIGINT NULL REFERENCES "flights"("id") ON DELETE CASCADE,
    "departure_flight_id" BIGINT NULL REFERENCES "flights"("id") ON DELETE CASCADE,
    "rotation_id" VARCHAR(50) NOT NULL,
    "operating_day" SMALLINT NOT NULL DEFAULT 1,
    "rotation_status" VARCHAR(30) NOT NULL DEFAULT 'PAIRED',
    "turnaround_minutes" SMALLINT NULL,
    "confidence" SMALLINT NOT NULL DEFAULT 100,
    "remarks" VARCHAR(255) NULL,
    "created_at" TIMESTAMPTZ NULL,
    "updated_at" TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS "idx_flight_pairings_rotation" ON "flight_pairings" ("rotation_id");
CREATE INDEX IF NOT EXISTS "idx_flight_pairings_upload_day" ON "flight_pairings" ("upload_id", "operating_day");

-- ----------------------------------------------------------------------------
-- 3. Migration: 2026_08_11_000002_add_is_locked_to_timeline_positions_table
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "timeline_positions"
    ADD COLUMN IF NOT EXISTS "is_locked" BOOLEAN NOT NULL DEFAULT FALSE;

-- ----------------------------------------------------------------------------
-- 4. Migration: 2026_08_20_000001_create_master_airlines_and_update_airports_table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "airlines" (
    "id" BIGSERIAL PRIMARY KEY,
    "airline_code" VARCHAR(10) NOT NULL UNIQUE,
    "airline_name" VARCHAR(255) NOT NULL,
    "category" VARCHAR(50) NOT NULL DEFAULT 'domestic',
    "status" VARCHAR(50) NOT NULL DEFAULT 'active',
    "created_at" TIMESTAMPTZ NULL,
    "updated_at" TIMESTAMPTZ NULL
);

ALTER TABLE IF EXISTS "airports"
    ADD COLUMN IF NOT EXISTS "province" VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS "region" VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS "airport_type" VARCHAR(50) NOT NULL DEFAULT 'domestic',
    ADD COLUMN IF NOT EXISTS "management_type" VARCHAR(100) NOT NULL DEFAULT 'PT. Angkasa Pura Indonesia',
    ADD COLUMN IF NOT EXISTS "management_name" VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS "is_international" BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS "is_active" BOOLEAN NOT NULL DEFAULT TRUE;

-- ----------------------------------------------------------------------------
-- 5. Migration: 2026_08_20_000002_enhance_flights_and_uploads_staging_columns
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "flights"
    ADD COLUMN IF NOT EXISTS "direction" VARCHAR(20) NOT NULL DEFAULT 'arrival',
    ADD COLUMN IF NOT EXISTS "traffic_type" VARCHAR(20) NOT NULL DEFAULT 'domestic',
    ADD COLUMN IF NOT EXISTS "parse_status" VARCHAR(30) NOT NULL DEFAULT 'valid',
    ADD COLUMN IF NOT EXISTS "validation_status" VARCHAR(30) NOT NULL DEFAULT 'valid',
    ADD COLUMN IF NOT EXISTS "validation_errors" JSONB NULL;

CREATE INDEX IF NOT EXISTS "idx_flights_upload_flnum" ON "flights" ("upload_id", "flight_number");
CREATE INDEX IF NOT EXISTS "idx_flights_upload_time" ON "flights" ("upload_id", "scheduled_time");
CREATE INDEX IF NOT EXISTS "idx_flights_upload_dir" ON "flights" ("upload_id", "direction");
CREATE INDEX IF NOT EXISTS "idx_flights_upload_traffic" ON "flights" ("upload_id", "traffic_type");
CREATE INDEX IF NOT EXISTS "idx_flights_upload_val" ON "flights" ("upload_id", "validation_status");

ALTER TABLE IF EXISTS "uploads"
    ADD COLUMN IF NOT EXISTS "total_rows" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "valid_rows" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "invalid_rows" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "duplicate_rows" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "parsing_confidence" NUMERIC(5,2) NOT NULL DEFAULT 100.00,
    ADD COLUMN IF NOT EXISTS "validation_summary" JSONB NULL;

-- ----------------------------------------------------------------------------
-- 6. Migration: 2026_08_20_000003_add_hubud_sync_columns_to_airports_and_airlines
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "airports"
    ADD COLUMN IF NOT EXISTS "icao_code" VARCHAR(10) NULL,
    ADD COLUMN IF NOT EXISTS "usage_type" VARCHAR(50) DEFAULT 'Komersial',
    ADD COLUMN IF NOT EXISTS "classification" VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS "data_incomplete" BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS "source" VARCHAR(100) NOT NULL DEFAULT 'PROJECT_REFERENCE',
    ADD COLUMN IF NOT EXISTS "source_url" VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS "source_checked_at" TIMESTAMPTZ NULL;

CREATE INDEX IF NOT EXISTS "idx_airports_icao_code" ON "airports" ("icao_code");

ALTER TABLE IF EXISTS "airlines"
    ADD COLUMN IF NOT EXISTS "organization_code" VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS "country" VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
    ADD COLUMN IF NOT EXISTS "is_active" BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS "source" VARCHAR(100) NOT NULL DEFAULT 'PROJECT_REFERENCE',
    ADD COLUMN IF NOT EXISTS "source_url" VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS "source_checked_at" TIMESTAMPTZ NULL;

CREATE INDEX IF NOT EXISTS "idx_airlines_org_code" ON "airlines" ("organization_code");

-- ----------------------------------------------------------------------------
-- 7. Migration: 2026_08_20_000004_add_data_source_and_status_to_airports_table
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "airports"
    ADD COLUMN IF NOT EXISTS "data_source" VARCHAR(50) NOT NULL DEFAULT 'INJOURNEY_AIRPORTS',
    ADD COLUMN IF NOT EXISTS "status" VARCHAR(50) NOT NULL DEFAULT 'active';

-- ----------------------------------------------------------------------------
-- 8. Migration: 2026_08_20_000005_enhance_airports_for_full_hubud_registry
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "airports"
    ALTER COLUMN "iata_code" DROP NOT NULL,
    ADD COLUMN IF NOT EXISTS "bandara_id" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "area" VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS "operating_status" VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS "latitude" NUMERIC(11,8) NULL,
    ADD COLUMN IF NOT EXISTS "longitude" NUMERIC(11,8) NULL;

CREATE INDEX IF NOT EXISTS "idx_airports_bandara_id" ON "airports" ("bandara_id");

-- ----------------------------------------------------------------------------
-- 9. Migration: 2026_08_27_000001_add_operational_settings_to_airports_table
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "airports"
    ADD COLUMN IF NOT EXISTS "aircraft_capacity" SMALLINT NOT NULL DEFAULT 6,
    ADD COLUMN IF NOT EXISTS "timezone" VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta',
    ADD COLUMN IF NOT EXISTS "ops_start_time" VARCHAR(5) NOT NULL DEFAULT '06:00',
    ADD COLUMN IF NOT EXISTS "ops_end_time" VARCHAR(5) NOT NULL DEFAULT '20:00';

-- ----------------------------------------------------------------------------
-- 10. Migration: 2026_08_31_000001_create_sessions_and_cache_tables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "sessions" (
    "id" VARCHAR(255) PRIMARY KEY,
    "user_id" BIGINT NULL,
    "ip_address" VARCHAR(45) NULL,
    "user_agent" TEXT NULL,
    "payload" TEXT NOT NULL,
    "last_activity" INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS "idx_sessions_user_id" ON "sessions" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_sessions_last_activity" ON "sessions" ("last_activity");

CREATE TABLE IF NOT EXISTS "cache" (
    "key" VARCHAR(255) PRIMARY KEY,
    "value" TEXT NOT NULL,
    "expiration" INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS "cache_locks" (
    "key" VARCHAR(255) PRIMARY KEY,
    "owner" VARCHAR(255) NOT NULL,
    "expiration" INTEGER NOT NULL
);

-- ----------------------------------------------------------------------------
-- 11. Migration: 2026_09_03_000001_add_report_type_and_dau_data_to_uploads
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "uploads"
    ADD COLUMN IF NOT EXISTS "report_type" VARCHAR(50) NOT NULL DEFAULT 'slot_schedule',
    ADD COLUMN IF NOT EXISTS "report_data" JSONB NULL;

CREATE INDEX IF NOT EXISTS "idx_uploads_report_type" ON "uploads" ("report_type");

-- ----------------------------------------------------------------------------
-- 12. Migration: 2026_09_04_000001_add_arrival_and_departure_capacity_to_airports_table
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "airports"
    ADD COLUMN IF NOT EXISTS "arrival_capacity" SMALLINT NOT NULL DEFAULT 6,
    ADD COLUMN IF NOT EXISTS "departure_capacity" SMALLINT NOT NULL DEFAULT 6;

UPDATE "airports"
SET "arrival_capacity" = "aircraft_capacity",
    "departure_capacity" = "aircraft_capacity"
WHERE "aircraft_capacity" IS NOT NULL AND "arrival_capacity" = 6 AND "departure_capacity" = 6;

-- ----------------------------------------------------------------------------
-- 13. Migration: 2026_09_14_000001_create_upload_chunks_table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "upload_chunks" (
    "id" BIGSERIAL PRIMARY KEY,
    "upload_token" VARCHAR(64) NOT NULL,
    "chunk_index" INTEGER NOT NULL,
    "total_chunks" INTEGER NOT NULL,
    "chunk_size" INTEGER NOT NULL DEFAULT 0,
    "chunk_data" BYTEA NOT NULL,
    "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "uniq_token_chunk" UNIQUE ("upload_token", "chunk_index")
);
CREATE INDEX IF NOT EXISTS "idx_upload_chunks_token" ON "upload_chunks" ("upload_token");

-- ----------------------------------------------------------------------------
-- 14 & 16. Migrations: 2026_10_01_000001 & 2026_10_01_000003 (fdr_processing_jobs)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "fdr_processing_jobs" (
    "id" VARCHAR(64) PRIMARY KEY,
    "upload_id" BIGINT NULL,
    "upload_token" VARCHAR(64) NOT NULL,
    "filename" VARCHAR(255) NOT NULL,
    "stored_path" VARCHAR(255) NOT NULL,
    "file_size" BIGINT NOT NULL DEFAULT 0,
    "file_hash" VARCHAR(64) NULL,
    "report_type" VARCHAR(32) NOT NULL DEFAULT 'fdr',
    "status" VARCHAR(32) NOT NULL DEFAULT 'QUEUED',
    "stage" VARCHAR(64) NULL,
    "stage_label" VARCHAR(255) NULL,
    "progress" INTEGER NOT NULL DEFAULT 0,
    "processed_rows" INTEGER NOT NULL DEFAULT 0,
    "total_rows" INTEGER NOT NULL DEFAULT 0,
    "current_offset" INTEGER NOT NULL DEFAULT 0,
    "current_batch" INTEGER NOT NULL DEFAULT 0,
    "failed_rows" INTEGER NOT NULL DEFAULT 0,
    "error_code" VARCHAR(64) NULL,
    "meta" JSONB NULL,
    "diagnostics" JSONB NULL,
    "error_message" TEXT NULL,
    "result_url" TEXT NULL,
    "started_at" TIMESTAMPTZ NULL,
    "completed_at" TIMESTAMPTZ NULL,
    "created_at" TIMESTAMPTZ NULL,
    "updated_at" TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS "idx_fdr_jobs_upload" ON "fdr_processing_jobs" ("upload_id");
CREATE INDEX IF NOT EXISTS "idx_fdr_jobs_token" ON "fdr_processing_jobs" ("upload_token");
CREATE INDEX IF NOT EXISTS "idx_fdr_jobs_status" ON "fdr_processing_jobs" ("status");

-- ----------------------------------------------------------------------------
-- 15. Migration: 2026_10_01_000002_create_fdr_upload_sessions_table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "fdr_upload_sessions" (
    "id" VARCHAR(64) PRIMARY KEY,
    "user_id" BIGINT NULL,
    "upload_token" VARCHAR(64) NOT NULL UNIQUE,
    "original_filename" VARCHAR(255) NOT NULL,
    "file_size" BIGINT NOT NULL DEFAULT 0,
    "mime_type" VARCHAR(128) NULL,
    "file_hash" VARCHAR(64) NULL,
    "chunk_size" INTEGER NOT NULL DEFAULT 0,
    "total_chunks" INTEGER NOT NULL DEFAULT 0,
    "uploaded_bytes" BIGINT NOT NULL DEFAULT 0,
    "uploaded_chunks" JSONB NULL,
    "storage_bucket" VARCHAR(128) NULL,
    "storage_path" VARCHAR(255) NULL,
    "status" VARCHAR(32) NOT NULL DEFAULT 'CREATED',
    "progress" INTEGER NOT NULL DEFAULT 0,
    "last_confirmed_chunk" INTEGER NULL DEFAULT -1,
    "failed_chunk" INTEGER NULL,
    "expires_at" TIMESTAMPTZ NULL,
    "created_at" TIMESTAMPTZ NULL,
    "updated_at" TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS "idx_fdr_sessions_user" ON "fdr_upload_sessions" ("user_id");
CREATE INDEX IF NOT EXISTS "idx_fdr_sessions_hash" ON "fdr_upload_sessions" ("file_hash");
CREATE INDEX IF NOT EXISTS "idx_fdr_sessions_status" ON "fdr_upload_sessions" ("status");

-- ----------------------------------------------------------------------------
-- 17, 18, 19. Migrations: fdr_flights + crew columns + analytical indexes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS "fdr_flights" (
    "id" BIGSERIAL PRIMARY KEY,
    "upload_id" BIGINT NOT NULL REFERENCES "uploads"("id") ON DELETE CASCADE,
    "flight_date" DATE NOT NULL,
    "flight_number" VARCHAR(20) NOT NULL,
    "flight_number_base" VARCHAR(20) NULL,
    "flight_suffix" VARCHAR(10) NULL,
    "airline_code" VARCHAR(10) NOT NULL,
    "airline" VARCHAR(50) NULL,
    "paired_flight_number" VARCHAR(20) NULL,
    "aircraft_type" VARCHAR(100) NULL,
    "registration_number" VARCHAR(20) NULL,
    "leg" VARCHAR(20) NULL,
    "direction" VARCHAR(20) NOT NULL,
    "movement_type" VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
    "origin_airport" VARCHAR(10) NULL,
    "city_1" VARCHAR(20) NULL,
    "destination_airport" VARCHAR(10) NULL,
    "city_2" VARCHAR(20) NULL,
    "report_airport" VARCHAR(10) NOT NULL DEFAULT 'CGK',
    "traffic_type" VARCHAR(20) NOT NULL DEFAULT 'DOMESTIC',
    "scheduled_time" TIME NULL,
    "actual_time" TIME NULL,
    "scheduled_datetime" TIMESTAMPTZ NULL,
    "actual_datetime" TIMESTAMPTZ NULL,
    "scheduled_time_str" VARCHAR(30) NULL,
    "actual_time_str" VARCHAR(30) NULL,
    "scheduled_hour" SMALLINT NULL,
    "actual_hour" SMALLINT NULL,
    "operational_hour" SMALLINT NOT NULL DEFAULT 0,
    "delay_minutes" INTEGER NOT NULL DEFAULT 0,
    "status" VARCHAR(20) NOT NULL DEFAULT 'ON_TIME',
    "is_realized" BOOLEAN NOT NULL DEFAULT FALSE,
    "is_irregular" BOOLEAN NOT NULL DEFAULT FALSE,
    "passenger_capacity" INTEGER NOT NULL DEFAULT 0,
    "passenger_load" INTEGER NOT NULL DEFAULT 0,
    "load_factor" NUMERIC(5,2) NULL,
    "pax_adult" INTEGER NOT NULL DEFAULT 0,
    "pax_child" INTEGER NOT NULL DEFAULT 0,
    "pax_infant" INTEGER NOT NULL DEFAULT 0,
    "pax_transit" INTEGER NOT NULL DEFAULT 0,
    "pax_transfer" INTEGER NOT NULL DEFAULT 0,
    "cargo_kg" NUMERIC(12,2) NOT NULL DEFAULT 0,
    "baggage_kg" NUMERIC(12,2) NOT NULL DEFAULT 0,
    "pos_kg" NUMERIC(12,2) NOT NULL DEFAULT 0,
    "operating_crew" INTEGER NOT NULL DEFAULT 0,
    "extra_crew" INTEGER NOT NULL DEFAULT 0,
    "stand" VARCHAR(30) NULL,
    "runway" VARCHAR(30) NULL,
    "mtow" VARCHAR(20) NULL,
    "raw_data" JSONB NULL,
    "created_at" TIMESTAMPTZ NULL,
    "updated_at" TIMESTAMPTZ NULL
);

-- Core fdr_flights indexes
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_upload_id" ON "fdr_flights" ("upload_id");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_flight_date" ON "fdr_flights" ("flight_date");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_flight_no" ON "fdr_flights" ("flight_number");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_airline_code" ON "fdr_flights" ("airline_code");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_direction" ON "fdr_flights" ("direction");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_movement_type" ON "fdr_flights" ("movement_type");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_origin" ON "fdr_flights" ("origin_airport");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_destination" ON "fdr_flights" ("destination_airport");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_report_airport" ON "fdr_flights" ("report_airport");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_traffic_type" ON "fdr_flights" ("traffic_type");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_sched_time" ON "fdr_flights" ("scheduled_time");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_act_time" ON "fdr_flights" ("actual_time");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_sched_dt" ON "fdr_flights" ("scheduled_datetime");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_act_dt" ON "fdr_flights" ("actual_datetime");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_op_hour" ON "fdr_flights" ("operational_hour");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_delay_min" ON "fdr_flights" ("delay_minutes");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_status" ON "fdr_flights" ("status");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_is_realized" ON "fdr_flights" ("is_realized");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_stand" ON "fdr_flights" ("stand");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_runway" ON "fdr_flights" ("runway");

-- High-performance composite indexes
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_date" ON "fdr_flights" ("upload_id", "flight_date");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_air" ON "fdr_flights" ("upload_id", "airline_code");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_dir" ON "fdr_flights" ("upload_id", "direction");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_traf" ON "fdr_flights" ("upload_id", "traffic_type");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_real" ON "fdr_flights" ("upload_id", "is_realized");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_stat" ON "fdr_flights" ("upload_id", "status");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_date_dir" ON "fdr_flights" ("upload_id", "flight_date", "direction");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_leg" ON "fdr_flights" ("upload_id", "leg");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_airline" ON "fdr_flights" ("upload_id", "airline");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_delay" ON "fdr_flights" ("upload_id", "delay_minutes");
CREATE INDEX IF NOT EXISTS "idx_fdr_fl_up_cities" ON "fdr_flights" ("upload_id", "city_1", "city_2");

-- ----------------------------------------------------------------------------
-- 19. Migration: 2026_10_27_000001 (cached_analytics in uploads)
-- ----------------------------------------------------------------------------
ALTER TABLE IF EXISTS "uploads"
    ADD COLUMN IF NOT EXISTS "cached_analytics" JSONB NULL;

-- ----------------------------------------------------------------------------
-- Record all 19 applied migrations into the Laravel `migrations` table
-- ----------------------------------------------------------------------------
INSERT INTO "migrations" ("migration", "batch") VALUES
    ('2026_08_10_000004_add_season_airport_to_uploads_and_slot_status_to_flights', 2),
    ('2026_08_11_000001_create_flight_pairings_table', 2),
    ('2026_08_11_000002_add_is_locked_to_timeline_positions_table', 2),
    ('2026_08_20_000001_create_master_airlines_and_update_airports_table', 2),
    ('2026_08_20_000002_enhance_flights_and_uploads_staging_columns', 2),
    ('2026_08_20_000003_add_hubud_sync_columns_to_airports_and_airlines', 2),
    ('2026_08_20_000004_add_data_source_and_status_to_airports_table', 2),
    ('2026_08_20_000005_enhance_airports_for_full_hubud_registry', 2),
    ('2026_08_27_000001_add_operational_settings_to_airports_table', 2),
    ('2026_08_31_000001_create_sessions_and_cache_tables', 2),
    ('2026_09_03_000001_add_report_type_and_dau_data_to_uploads', 2),
    ('2026_09_04_000001_add_arrival_and_departure_capacity_to_airports_table', 2),
    ('2026_09_14_000001_create_upload_chunks_table', 2),
    ('2026_10_01_000001_create_fdr_processing_jobs_table', 2),
    ('2026_10_01_000002_create_fdr_upload_sessions_table', 2),
    ('2026_10_01_000003_add_checkpoint_fields_to_fdr_processing_jobs_table', 2),
    ('2026_10_08_000001_create_fdr_flights_table', 2),
    ('2026_10_19_000001_add_crew_columns_to_fdr_flights_table', 2),
    ('2026_10_27_000001_add_cached_analytics_and_fdr_flight_indexes', 2)
ON CONFLICT ("migration") DO NOTHING;

COMMIT;
