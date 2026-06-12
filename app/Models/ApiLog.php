<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'method',
        'endpoint',
        'request_headers',
        'request_body',
        'ip_address',
        'user_agent',
        'status_code',
        'response_body',
        'response_time',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
    ];
}