<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use App\Models\ApiLogArchive;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class ArchiveApiLogs extends Command
{
    protected $signature = 'logs:archive
                            {--days=90 : Archive logs older than this number of days (0 = up to now)}
                            {--chunk=1000 : Number of logs processed per chunk}
                            {--format=parquet : Archive format: parquet or csv}';

    protected $description = 'Archive old API logs into a Parquet (or CSV) file and safely remove them from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');
        $format = strtolower((string) $this->option('format'));

        if ($days < 0) {
            $this->error('The days value must be 0 or greater.');
            return self::FAILURE;
        }

        if ($chunkSize < 1) {
            $this->error('The chunk size must be at least 1.');
            return self::FAILURE;
        }

        if (! in_array($format, ['parquet', 'csv'], true)) {
            $this->error('Format must be either "parquet" or "csv".');
            return self::FAILURE;
        }

        $cutoffDate = $days === 0 ? now() : now()->subDays($days);

        $query = ApiLog::query()->where('created_at', '<', $cutoffDate);
        $totalLogs = (clone $query)->count();

        if ($totalLogs === 0) {
            $this->info('No API logs are available for archiving.');
            return self::SUCCESS;
        }

        $firstLogDate = (clone $query)->min('created_at');
        $lastLogDate = (clone $query)->max('created_at');

        $filename = sprintf(
            'api-logs-%s-to-%s-%s.%s',
            Carbon::parse($firstLogDate)->format('Y-m-d'),
            Carbon::parse($lastLogDate)->format('Y-m-d'),
            now()->format('Ymd-His'),
            $format
        );

        $relativePath = 'api-log-archives/' . $filename;
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $finalTmpPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
        $stagingTmpPath = $format === 'parquet'
            ? $tmpDir . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '.jsonl'
            : $finalTmpPath;

        $file = fopen($stagingTmpPath, 'w');
        if ($file === false) {
            $this->error('Unable to create the temporary archive file.');
            return self::FAILURE;
        }

        $archiveRecord = ApiLogArchive::create([
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relativePath,
            'format' => $format,
            'record_count' => 0,
            'period_start' => $firstLogDate,
            'period_end' => $lastLogDate,
            'status' => 'processing',
        ]);

        $archivedIds = [];
        $recordCount = 0;

        try {
            if ($format === 'csv') {
                fputcsv($file, [
                    'id', 'user_id', 'user_role', 'method', 'endpoint',
                    'request_headers', 'request_body', 'response_body',
                    'status_code', 'response_time', 'ip_address', 'user_agent',
                    'created_at', 'updated_at',
                ]);
            }

            $query->orderBy('id')->chunkById(
                $chunkSize,
                function ($logs) use ($file, $format, &$archivedIds, &$recordCount): void {
                    foreach ($logs as $log) {
                        if ($format === 'csv') {
                            fputcsv($file, [
                                $log->id, $log->user_id, $log->user_role, $log->method, $log->endpoint,
                                json_encode($log->request_headers), json_encode($log->request_body),
                                json_encode($log->response_body), $log->status_code, $log->response_time,
                                $log->ip_address, $log->user_agent, $log->created_at, $log->updated_at,
                            ]);
                        } else {
                            fwrite($file, json_encode([
                                'id' => (int) $log->id,
                                'user_id' => $log->user_id !== null ? (int) $log->user_id : null,
                                'user_role' => $log->user_role,
                                'method' => (string) $log->method,
                                'endpoint' => (string) $log->endpoint,
                                'request_headers' => json_encode($log->request_headers),
                                'request_body' => json_encode($log->request_body),
                                'response_body' => json_encode($log->response_body),
                                'status_code' => $log->status_code !== null ? (int) $log->status_code : null,
                                'response_time' => $log->response_time !== null ? (float) $log->response_time : null,
                                'ip_address' => $log->ip_address,
                                'user_agent' => $log->user_agent,
                                'created_at' => $log->created_at?->toIso8601String(),
                                'updated_at' => $log->updated_at?->toIso8601String(),
                            ]) . "\n");
                        }

                        $archivedIds[] = $log->id;
                        $recordCount++;
                    }
                }
            );

            fclose($file);

            if ($format === 'parquet') {
                $script = base_path('scripts/export_to_parquet.py');
                $process = new Process(['python', $script, $stagingTmpPath, $finalTmpPath]);
                $process->setTimeout(120);
                $process->run();

                if (! $process->isSuccessful() || ! file_exists($finalTmpPath)) {
                    throw new \RuntimeException(
                        'Parquet conversion failed: ' . ($process->getErrorOutput() ?: $process->getOutput())
                    );
                }
                @unlink($stagingTmpPath);
            }

            $checksum = hash_file('sha256', $finalTmpPath);
            if ($checksum === false) {
                throw new \RuntimeException('Unable to calculate the archive checksum.');
            }

            $stored = Storage::disk('local')->put($relativePath, file_get_contents($finalTmpPath));
            if (! $stored || ! Storage::disk('local')->exists($relativePath)) {
                throw new \RuntimeException('Unable to store the archive file.');
            }

            DB::connection('log_pgsql')->transaction(function () use ($archivedIds, $archiveRecord, $recordCount, $checksum): void {
                foreach (array_chunk($archivedIds, 1000) as $idChunk) {
                    ApiLog::query()->whereIn('id', $idChunk)->delete();
                }

                $archiveRecord->update([
                    'record_count' => $recordCount,
                    'checksum' => $checksum,
                    'status' => 'completed',
                ]);
            });

            @unlink($finalTmpPath);

            $this->info("Archived {$recordCount} API logs ({$format}).");
            $this->info("Archive path: {$relativePath}");
            $this->info("Checksum: {$checksum}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_resource($file)) {
                fclose($file);
            }

            @unlink($stagingTmpPath);
            @unlink($finalTmpPath);

            $archiveRecord->update([
                'record_count' => $recordCount,
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);

            $this->error('Archive failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
