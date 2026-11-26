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

        // 1. BRIN index on created_at: tiny footprint, fast for time-range scans
        $db->statement('
            CREATE INDEX IF NOT EXISTS idx_api_logs_created_at_brin
            ON api_logs USING BRIN (created_at)
        ');

        // 2. Composite B-tree index matching ApiLogController::index() filters & latest() sort
        $db->statement('
            CREATE INDEX IF NOT EXISTS idx_api_logs_method_status_created_at
            ON api_logs (method, status_code, created_at DESC)
        ');

        $db->statement('ANALYZE api_logs');
    }

    public function down(): void
    {
        $db = DB::connection($this->connection);

        $db->statement('DROP INDEX IF EXISTS idx_api_logs_created_at_brin');
        $db->statement('DROP INDEX IF EXISTS idx_api_logs_method_status_created_at');
    }
};
