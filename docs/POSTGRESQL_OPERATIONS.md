# PostgreSQL Deployment & Operational Documentation

Comprehensive guide for deploying, scaling, monitoring, archiving, and operating the isolated PostgreSQL logging environment (`log_pgsql` on Neon + `pgsql` app database).

---

## 1. Architecture & Workload Isolation

The application separates transactional app data from high-volume API log writes across two independent PostgreSQL connections configured in `backend/config/database.php`:

| Connection | Database | Purpose | Tables |
| :--- | :--- | :--- | :--- |
| `pgsql` | Local PostgreSQL (`api_logging_db`) | App data & job queue | `students`, `notes`, `personal_access_tokens`, `jobs`, `failed_jobs` |
| `log_pgsql` | **Neon PostgreSQL** (`api-logs-db`) | Isolated API log storage | `api_logs` (partitioned), `api_log_archives` |

### Environment Configuration (`backend/.env`)
```env
LOG_DB_CONNECTION=pgsql
LOG_DB_HOST=<neon-primary-pooler-host>
LOG_DB_WRITE_HOST=<neon-primary-pooler-host>
LOG_DB_READ_HOSTS=<neon-replica-1-pooler-host>,<neon-replica-2-pooler-host>
LOG_DB_PORT=5432
LOG_DB_DATABASE=api-logs-db
LOG_DB_USERNAME=neondb_owner
LOG_DB_PASSWORD=<password>
LOG_DB_SSLMODE=require
```

> **Note on Neon PgBouncer Pooler:** All `migrations_log` migrations set `public $withinTransaction = false;` so DDL statements (`CREATE TABLE`, `CREATE INDEX`, `ALTER TABLE`) execute cleanly through Neon's `-pooler` endpoint.

---

## 2. High Availability & Load Balancing

### High Availability (Neon Storage Layer)
- **Multi-AZ Storage:** Neon decouples stateless PostgreSQL compute from multi-AZ storage (Safekeepers + Pageservers). WAL is replicated across availability zones before commit.
- **Automatic Compute Recovery:** If a compute node fails, Neon attaches a fresh stateless compute node to the same storage without data loss.
- **Connection Pooling:** The `-pooler` endpoint uses built-in **PgBouncer** (up to 10,000 pooled connections per compute).

### Read/Write Load Balancing (Laravel + Neon Read Replicas)
In `backend/config/database.php`, `log_pgsql` is configured with `'sticky' => false` and separate `'write'` and `'read'` hosts:
- **Writes (`INSERT` / `UPDATE` / `DELETE`):** Routed to `LOG_DB_WRITE_HOST` (Primary compute).
- **Reads (`SELECT` via `GET /api/logs`):** Load-balanced across `LOG_DB_READ_HOSTS` (Neon Read Replicas).

**Verify Read/Write Splitting:**
- Read connection: `SELECT pg_is_in_recovery()` returns `true` (Read Replica).
- Write connection: `SELECT pg_is_in_recovery()` returns `false` (Primary).

---

## 3. High-Volume Queued Ingestion (4,000+ Inserts/Min)

### Request & Queue Flow
1. `ApiLoggerMiddleware` (`handle()` + `terminate()`) captures request/response metadata and returns the HTTP response to the client immediately.
2. `terminate()` dispatches `StoreApiLogJob::dispatch($logData)->onQueue('api-logs')` to the local `jobs` table (`pgsql`), keeping client latency minimal.
3. `php artisan logs:process-batches --batch=100` (`backend/app/Console/Commands/ProcessApiLogBatches.php`) pops up to 100 queued jobs per batch and executes a single bulk `INSERT` into Neon (`log_pgsql.api_logs`).

### Observed Benchmark (Neon `us-east-2`)
Using `php artisan logs:simulate --count=4000` + `php artisan logs:process-batches --batch=100 --once`:
- **Enqueued:** `4,000` jobs in `2.15s`
- **Inserted into Neon:** `4,269` logs in `44.02s`
- **Measured Throughput:** **`5,819 inserts/min`** (exceeding the `4,000 inserts/min` target)

---

## 4. Indexing Strategy & Query Performance

Defined in `backend/database/migrations_log/2026_11_26_000002_add_optimized_indexes_to_api_logs.php`:

1. **`idx_api_logs_created_at_brin` (`BRIN` on `created_at`):**
   - Minimal storage footprint and negligible insert overhead for time-ordered log appends.
2. **`idx_api_logs_method_status_created_at` (`B-tree` on `(method, status_code, created_at DESC)`):**
   - Covers `ApiLogController::index()` filters (`method`, `status_code`) and `ORDER BY created_at DESC` in one index.

### Measured Before vs. After (`EXPLAIN (ANALYZE, BUFFERS)`)
Query: `SELECT * FROM api_logs WHERE method = 'GET' AND status_code = 200 ORDER BY created_at DESC LIMIT 50`

| Metric | Before Indexing | After Indexing |
| :--- | :--- | :--- |
| **Execution Plan** | `Seq Scan` + `Sort` | `Index Scan` + `Merge Append` |
| **Execution Time** | `10.284 ms` | **`1.668 ms`** (~6.2x faster) |
| **Buffers Touched** | `112` pages | **`11` pages** (~10x fewer) |

---

## 5. Partitioning & Data Archival (Parquet)

### Monthly Range Partitioning
Defined in `backend/database/migrations_log/2026_11_26_000001_partition_api_logs_by_month.php`:
- `api_logs` uses `PARTITION BY RANGE (created_at)` with `PRIMARY KEY (id, created_at)`.
- Active partitions: `api_logs_2026_11`, `api_logs_2026_12`, `api_logs_2027_01`, and `api_logs_default`.
- Date-range queries automatically prune unrelated monthly partitions.

### Parquet Archival (`logs:archive`)
- Command: `php artisan logs:archive --days=90 --format=parquet` (`--format=csv` also supported).
- Uses `backend/scripts/export_to_parquet.py` (`pyarrow` with `zstd` compression) to write `.parquet` files to `backend/storage/app/api-log-archives/`.
- Records `filename`, `path`, `format`, `record_count`, `checksum` (SHA-256), and `status` in Neon's `api_log_archives` table before removing archived rows from `api_logs`.

---

## 6. Deployment & Operational Runbook

### Initial Deployment
```bash
cd backend
composer install
php artisan config:clear
php artisan migrate --database=log_pgsql --path=database/migrations_log --force
```

### Running Services
```bash
# 1. Start Laravel API
php artisan serve --host=127.0.0.1 --port=8000

# 2. Start Batch Log Processor (writes queued logs to Neon)
php artisan logs:process-batches --batch=100

# 3. Run Scheduler (executes logs:archive automatically)
php artisan schedule:work
```

### Health & Monitoring Queries (Neon SQL Editor)
```sql
-- Check row distribution across monthly partitions
SELECT tableoid::regclass AS partition_name, COUNT(*) AS rows
FROM api_logs
GROUP BY tableoid
ORDER BY partition_name;

-- Check recent archive runs
SELECT id, filename, format, record_count, status, created_at
FROM api_log_archives
ORDER BY id DESC
LIMIT 10;
```

### Backup & Restore
```bash
# Logical backup of Neon log database (use direct non-pooler host for pg_dump)
pg_dump "postgresql://neondb_owner:<PASSWORD>@<DIRECT_HOST>/api-logs-db?sslmode=require" -Fc -f api_logs_backup.dump

# Restore
pg_restore -d "postgresql://neondb_owner:<PASSWORD>@<DIRECT_HOST>/api-logs-db?sslmode=require" --no-owner api_logs_backup.dump
```

### Common Troubleshooting
- **`SQLSTATE[25P02]` during migrations on Neon:** Ensure `public $withinTransaction = false;` is set on the migration or run migrations against the non-pooler Neon host.
- **Logs stuck in `jobs` table:** Confirm `php artisan logs:process-batches --batch=100` is running and `LOG_DB_WRITE_HOST` is reachable.
- **High latency on `performance_test.py` locally:** `php artisan serve` on Windows is single-threaded; use `php artisan logs:simulate --count=4000` + `logs:process-batches` to benchmark Neon write throughput, or deploy behind Nginx + PHP-FPM in production.
