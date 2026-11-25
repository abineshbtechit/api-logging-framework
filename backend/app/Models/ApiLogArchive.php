<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLogArchive extends Model
{
    protected $connection = 'log_pgsql';

    protected $fillable = [
        'filename',
        'disk',
        'path',
        'format',
        'record_count',
        'period_start',
        'period_end',
        'checksum',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'record_count' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];
}