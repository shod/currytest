<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_job_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at')->index();
            $table->dateTime('finished_at')->nullable();
            $table->string('status', 24);
            $table->tinyInteger('attempts')->default(1);
            $table->integer('currencies_updated')->nullable();
            $table->string('error_summary', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->string('triggered_by', 16);
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_job_logs');
    }
};
