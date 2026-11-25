<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'log_pgsql';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('api_log_archives', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('disk');
                $table->string('path');
                $table->string('format', 20);
                $table->unsignedBigInteger('record_count');
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->string('checksum')->nullable();
                $table->string('status', 30)->default('completed');
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('period_end');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->dropIfExists('api_log_archives');
    }
};