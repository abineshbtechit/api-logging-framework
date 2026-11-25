<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use App\Models\ApiLogArchive;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ArchiveApiLogs extends Command
{
    protected $signature = 'logs:archive
                            {--days=90 : Archive logs older than this number of days}
                            {--chunk=1000 : Number of logs processed per chunk}';

    protected $description = 'Archive old API logs into a CSV file and safely remove them from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');

        if ($days < 1) {
            $this->error('The days value must be at least 1.');

            return self::FAILURE;
        }

        if ($chunkSize < 1) {
            $this->error('The chunk size must be at least 1.');

            return self::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $query = ApiLog::query()
            ->where('created_at', '<', $cutoffDate);

        $totalLogs = (clone $query)->count();

        if ($totalLogs === 0) {
            $this->info('No API logs are available for archiving.');

            return self::SUCCESS;
        }

        $firstLogDate = (clone $query)->min('created_at');
        $lastLogDate = (clone $query)->max('created_at');

        $filename = sprintf(
            'api-logs-%s-to-%s-%s.csv',
            Carbon::parse($firstLogDate)->format('Y-m-d'),
            Carbon::parse($lastLogDate)->format('Y-m-d'),
            now()->format('Ymd-His')
        );

        $relativePath = 'api-log-archives/' . $filename;
        $temporaryPath = storage_path('app/tmp/' . $filename);

        if (! is_dir(dirname($temporaryPath))) {
            mkdir(dirname($temporaryPath), 0755, true);
        }

        $file = fopen($temporaryPath, 'w');

        if ($file === false) {
            $this->error('Unable to create the temporary archive file.');

            return self::FAILURE;
        }

        $archiveRecord = ApiLogArchive::create([
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relativePath,
            'format' => 'csv',
            'record_count' => 0,
            'period_start' => $firstLogDate,
            'period_end' => $lastLogDate,
            'status' => 'processing',
        ]);

        $archivedIds = [];
        $recordCount = 0;

        try {
            fputcsv($file, [
                'id',
                'user_id',
                'user_role',
                'method',
                'endpoint',
                'request_headers',
                'request_body',
                'response_body',
                'status_code',
                'response_time',
                'ip_address',
                'user_agent',
                'created_at',
                'updated_at',
            ]);

            $query
                ->orderBy('id')
                ->chunkById(
                    $chunkSize,
                    function ($logs) use (
                        $file,
                        &$archivedIds,
                        &$recordCount
                    ): void {
                        foreach ($logs as $log) {
                            fputcsv($file, [
                                $log->id,
                                $log->user_id,
                                $log->user_role,
                                $log->method,
                                $log->endpoint,
                                json_encode($log->request_headers),
                                json_encode($log->request_body),
                                json_encode($log->response_body),
                                $log->status_code,
                                $log->response_time,
                                $log->ip_address,
                                $log->user_agent,
                                $log->created_at,
                                $log->updated_at,
                            ]);

                            $archivedIds[] = $log->id;
                            $recordCount++;
                        }
                    }
                );

            fclose($file);

            $checksum = hash_file('sha256', $temporaryPath);

            if ($checksum === false) {
                throw new \RuntimeException(
                    'Unable to calculate the archive checksum.'
                );
            }

            $stored = Storage::disk('local')->put(
                $relativePath,
                file_get_contents($temporaryPath)
            );

            if (! $stored) {
                throw new \RuntimeException(
                    'Unable to store the archive file.'
                );
            }

            if (! Storage::disk('local')->exists($relativePath)) {
                throw new \RuntimeException(
                    'Archive verification failed.'
                );
            }

            DB::connection('log_pgsql')->transaction(
                function () use (
                    $archivedIds,
                    $archiveRecord,
                    $recordCount,
                    $checksum
                ): void {
                    foreach (array_chunk($archivedIds, 1000) as $idChunk) {
                        ApiLog::query()
                            ->whereIn('id', $idChunk)
                            ->delete();
                    }

                    $archiveRecord->update([
                        'record_count' => $recordCount,
                        'checksum' => $checksum,
                        'status' => 'completed',
                    ]);
                }
            );

            @unlink($temporaryPath);

            $this->info("Archived {$recordCount} API logs.");
            $this->info("Archive path: {$relativePath}");
            $this->info("Checksum: {$checksum}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_resource($file)) {
                fclose($file);
            }

            @unlink($temporaryPath);

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