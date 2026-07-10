<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLoggerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('ApiLogger handle running');

        $startTime = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $logData = [
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'method' => $request->method(),
                'endpoint' => $request->fullUrl(),
                'request_headers' => $request->headers->all(),
                'request_body' => $request->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 500,
                'response_body' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'response_time' => $responseTime,
            ];

            try {
                ApiLog::create($logData);
            } catch (Throwable $ex) {
                Log::error('Failed to write API log exception', [
                    'error' => $ex->getMessage()
                ]);
            }

            throw $e;
        }

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        $content = $response->getContent();

        $logData = [
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
            'method' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_body' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'response_body' => json_decode($content, true) ?? [
                'raw_response' => $content
            ],
            'response_time' => $responseTime,
        ];

        $request->attributes->set('api_log_data', $logData);

        Log::info('API log data set', $logData);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        Log::info('ApiLogger terminate running');

        $logData = $request->attributes->get('api_log_data', []);

        Log::info('Log data before insert', $logData);

        if (!empty($logData)) {
            try {
                $log = ApiLog::create($logData);

                Log::info('API log inserted successfully', [
                    'id' => $log->id
                ]);
            } catch (Throwable $ex) {
                Log::error('Failed to write API log in terminate', [
                    'error' => $ex->getMessage()
                ]);
            }
        }
    }
}
