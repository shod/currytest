<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Currency\RateRefresher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('currency:refresh-rates
    {--triggered-by= : Source of invocation (manual or scheduler)}')]
#[Description('Refresh stored exchange rates from freecurrencyapi.com.')]
class RefreshCurrencyRates extends Command
{
    public function __construct(private readonly RateRefresher $refresher)
    {
        parent::__construct();
    }

    public function isolatableId(): string
    {
        return 'currency-refresh-rates';
    }

    public function handle(): int
    {
        $triggeredBy = $this->option('triggered-by') ?: 'manual';

        $log = $this->refresher->run((string) $triggeredBy);

        return match ($log->status) {
            'success' => 0,
            default => 1,
        };
    }
}
