<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    protected $connection = 'log_pgsql';

    public function up(): void
    {
        $db = DB::connection($this->connection);

        $isPartitioned = $db->selectOne("
            SELECT c.relkind
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relname = 'api_logs'
        ");

        if ($isPartitioned && $isPartitioned->relkind === 'p') {
            return;
        }

        $db->statement('DROP TABLE IF EXISTS api_logs_partitioned CASCADE');

        $db->statement("
            CREATE TABLE api_logs_partitioned (
                id BIGSERIAL NOT NULL,
                user_id BIGINT NULL,
                user_role VARCHAR(255) NULL,
                method VARCHAR(255) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                request_headers JSON NULL,
                request_body JSON NULL,
                ip_address VARCHAR(255) NULL,
                user_agent VARCHAR(255) NULL,
                status_code INTEGER NULL,
                response_body JSON NULL,
                response_time DOUBLE PRECISION NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        ");

        // Create monthly partitions + default fallback partition
        $db->statement("
            CREATE TABLE api_logs_2026_11 PARTITION OF api_logs_partitioned
            FOR VALUES FROM ('2026-11-01 00:00:00') TO ('2026-12-01 00:00:00')
        ");
        $db->statement("
            CREATE TABLE api_logs_2026_12 PARTITION OF api_logs_partitioned
            FOR VALUES FROM ('2026-12-01 00:00:00') TO ('2027-01-01 00:00:00')
        ");
        $db->statement("
            CREATE TABLE api_logs_2027_01 PARTITION OF api_logs_partitioned
            FOR VALUES FROM ('2027-01-01 00:00:00') TO ('2027-02-01 00:00:00')
        ");
        $db->statement("
            CREATE TABLE api_logs_default PARTITION OF api_logs_partitioned DEFAULT
        ");

        // Migrate existing rows
        $db->statement("
            INSERT INTO api_logs_partitioned (
                id, user_id, user_role, method, endpoint,
                request_headers, request_body, ip_address, user_agent,
                status_code, response_body, response_time, created_at, updated_at
            )
            SELECT
                id, user_id, user_role, method, endpoint,
                request_headers, request_body, ip_address, user_agent,
                status_code, response_body, response_time,
                COALESCE(created_at, NOW()), updated_at
            FROM api_logs
        ");

        $db->statement("
            SELECT setval(
                pg_get_serial_sequence('api_logs_partitioned', 'id'),
                COALESCE((SELECT MAX(id) FROM api_logs_partitioned), 1),
                true
            )
        ");

        $db->statement('ALTER TABLE api_logs RENAME TO api_logs_legacy');
        $db->statement('ALTER TABLE api_logs_partitioned RENAME TO api_logs');
        $db->statement('DROP TABLE api_logs_legacy');
    }

    public function down(): void
    {
        // Converting a partitioned table back to non-partitioned is not automated in down()
    }
};
