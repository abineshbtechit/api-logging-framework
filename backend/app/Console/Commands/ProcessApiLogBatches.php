<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessApiLogBatches extends Command
{
    protected $signature = 'logs:process-batches
                            {--batch=100 : Number of queued jobs to insert per batch}
                            {--once : Exit after draining currently queued api-logs jobs}';

    protected $description = 'Batch-process StoreApiLogJob entries from the api-logs queue into Neon/log_pgsql';

    public function handle(): int
    {
        $batchSize = max(1, (int) $this->option('batch'));
        $runOnce = (bool) $this->option('once');

        $this->info("Starting batch processor (batch size: {$batchSize})...");

        $totalInserted = 0;
        $startedAt = microtime(true);

        while (true) {
            $jobs = DB::table('jobs')
                ->where('queue', 'api-logs')
                ->where('available_at', '<=', time())
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'payload']);

            if ($jobs->isEmpty()) {
                if ($runOnce) {
                    break;
                }
                usleep(250_000);
                continue;
            }

            $rows = [];
            $jobIds = [];
            $now = now();

            foreach ($jobs as $job) {
                $decoded = json_decode($job->payload, true);
                $commandSerialized = $decoded['data']['command'] ?? null;

                if (! $commandSerialized) {
                    $jobIds[] = $job->id;
                    continue;
                }

                $command = unserialize($commandSerialized);
                $data = $command->logData ?? null;

                if (! is_array($data)) {
                    $jobIds[] = $job->id;
                    continue;
                }

                $rows[] = [
                    'user_id' => $data['user_id'] ?? null,
                    'user_role' => $data['user_role'] ?? null,
                    'method' => $data['method'] ?? 'GET',
                    'endpoint' => $data['endpoint'] ?? '',
                    'request_headers' => json_encode($data['request_headers'] ?? []),
                    'request_body' => json_encode($data['request_body'] ?? []),
                    'ip_address' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'status_code' => $data['status_code'] ?? null,
                    'response_body' => json_encode($data['response_body'] ?? []),
                    'response_time' => $data['response_time'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $jobIds[] = $job->id;
            }

            if (! empty($rows)) {
                try {
                    DB::connection('log_pgsql')->table('api_logs')->insert($rows);
                    DB::table('jobs')->whereIn('id', $jobIds)->delete();
                    $totalInserted += count($rows);
                    $this->line("Inserted batch of " . count($rows) . " logs (total: {$totalInserted})");
                } catch (Throwable $e) {
                    $this->error("Batch insert failed: " . $e->getMessage());
                    sleep(1);
                }
            } elseif (! empty($jobIds)) {
                DB::table('jobs')->whereIn('id', $jobIds)->delete();
            }
        }

        $elapsed = max(0.001, microtime(true) - $startedAt);
        $ratePerMin = ($totalInserted / $elapsed) * 60;
        $this->info(sprintf(
            'Done. Inserted %d logs in %.2fs (%.0f inserts/min).',
            $totalInserted,
            $elapsed,
            $ratePerMin
        ));

        return self::SUCCESS;
    }
}
