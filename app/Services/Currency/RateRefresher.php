<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\RefreshJobLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RateRefresher
{
    public function __construct(
        private readonly FreeCurrencyApiClient $client,
    ) {}

    /**
     * Запускает обновление курсов валют из внешнего API.
     *
     * Создаёт запись в журнале обновлений, захватывает атомарную блокировку,
     * получает курсы через FreeCurrencyApiClient, атомарно обновляет таблицу
     * exchange_rates в транзакции и финализирует запись журнала.
     *
     * @param  string  $triggeredBy  Источник запуска: 'manual' или 'scheduler'
     * @return RefreshJobLog Финализированная запись журнала
     */
    public function run(string $triggeredBy): RefreshJobLog
    {
        $log = RefreshJobLog::create([
            'started_at' => now(),
            'status' => 'failure',
            'attempts' => 1,
            'triggered_by' => $triggeredBy,
        ]);

        $lock = Cache::lock('currency:refresh', 600);

        if (! $lock->get()) {
            $log->update([
                'status' => 'skipped_overlap',
                'finished_at' => now(),
            ]);

            return $log->refresh();
        }

        try {
            $baseCurrency = config('currency.freecurrencyapi.base_currency', 'USD');
            $currencies = Currency::where('is_enabled', true)
                ->where('code', '!=', $baseCurrency)
                ->get()
                ->keyBy('code');

            $targetCodes = $currencies->keys()->all();

            $rates = $this->client->fetchLatest($baseCurrency, $targetCodes);

            // Validate completeness — all supported currencies must be in the response
            foreach ($targetCodes as $code) {
                if (! array_key_exists($code, $rates)) {
                    throw new \RuntimeException(
                        "Partial response: currency \"{$code}\" missing from provider response."
                    );
                }
            }

            $now = now();
            $upsertRows = [];

            foreach ($rates as $code => $rate) {
                if (! $currencies->has($code)) {
                    continue;
                }

                $upsertRows[] = [
                    'base_code' => $baseCurrency,
                    'target_currency_id' => $currencies[$code]->id,
                    'rate' => number_format((float) $rate, 10, '.', ''),
                    'fetched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($upsertRows) {
                ExchangeRate::upsert(
                    $upsertRows,
                    ['base_code', 'target_currency_id'],
                    ['rate', 'fetched_at', 'updated_at']
                );
            });

            $log->update([
                'status' => 'success',
                'currencies_updated' => count($upsertRows),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'error_summary' => mb_substr(get_class($e).': '.$e->getMessage(), 0, 255),
                'error_detail' => mb_substr($e->getTraceAsString(), 0, 4096),
                'finished_at' => now(),
            ]);
        } finally {
            $lock->release();
        }

        return $log->refresh();
    }
}
