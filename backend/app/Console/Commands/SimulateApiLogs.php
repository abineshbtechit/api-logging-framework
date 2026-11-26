<?php

namespace App\Console\Commands;

use App\Jobs\StoreApiLogJob;
use Illuminate\Console\Command;

class SimulateApiLogs extends Command
{
    protected $signature = 'logs:simulate
                            {--count=4000 : Number of StoreApiLogJob jobs to enqueue}';

    protected $description = 'Enqueue simulated StoreApiLogJob records onto the api-logs queue for load testing';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $startedAt = microtime(true);

        $this->info("Enqueuing {$count} StoreApiLogJob jobs to api-logs queue...");

        for ($i = 1; $i <= $count; $i++) {
            StoreApiLogJob::dispatch([
                'user_id' => null,
                'user_role' => 'guest',
                'method' => 'GET',
                'endpoint' => 'http://127.0.0.1:8000/api/getting',
                'request_headers' => ['accept' => ['application/json']],
                'request_body' => [],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SimulateApiLogs/1.0',
                'status_code' => 200,
                'response_body' => ['message' => 'Hello Abi'],
                'response_time' => round(mt_rand(50, 250) / 10, 2),
            ])->onQueue('api-logs');
        }

        $elapsed = microtime(true) - $startedAt;
        $this->info(sprintf('Enqueued %d jobs in %.2fs.', $count, $elapsed));

        return self::SUCCESS;
    }
}
