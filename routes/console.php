<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('currency:refresh-rates', ['--triggered-by' => 'scheduler'])
    ->dailyAt('03:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30);
