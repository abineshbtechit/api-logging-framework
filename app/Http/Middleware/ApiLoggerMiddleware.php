<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiLog;
use Throwable;

class ApiLoggerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            ApiLog::create([
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
            ]);

            throw $e;
        }

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        ApiLog::create([
            'method' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_body' => $request->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true) ?? [
                'raw_response' => $response->getContent()
            ],
            'response_time' => $responseTime,
        ]);

        return $response;
    }
}
