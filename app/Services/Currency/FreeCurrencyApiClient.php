<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Services\Currency\Exceptions\MalformedRateResponseException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FreeCurrencyApiClient
{
    /**
     * Получает последние курсы валют от freecurrencyapi.com.
     *
     * Использует HTTP-фасад Laravel (Guzzle) с ограниченным числом повторных попыток.
     * Выбрасывает исключение при отсутствии API-ключа или некорректном ответе сервера.
     *
     * @param  string  $baseCurrency  Код базовой валюты (ISO-4217)
     * @param  array<string>  $targetCodes  Список кодов целевых валют
     * @return array<string, float|int> Ключ — код валюты, значение — курс относительно базовой
     */
    public function fetchLatest(string $baseCurrency, array $targetCodes): array
    {
        $apiKey = config('currency.freecurrencyapi.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'FreeCurrencyAPI key is not configured. Set FREECURRENCYAPI_KEY in .env and ensure config/currency.php exposes it.'
            );
        }

        $response = Http::baseUrl(config('currency.freecurrencyapi.base_url'))
            ->retry([30_000, 300_000], throw: false)
            ->timeout(config('currency.freecurrencyapi.timeout_seconds'))
            ->get('/v1/latest', [
                'apikey' => $apiKey,
                'base_currency' => $baseCurrency,
                'currencies' => implode(',', $targetCodes),
            ]);

        $body = $response->json();

        if (! isset($body['data']) || ! is_array($body['data'])) {
            throw new MalformedRateResponseException(
                'FreeCurrencyAPI response missing "data" key. HTTP '.$response->status()
            );
        }

        foreach ($body['data'] as $code => $rate) {
            if (! is_numeric($rate)) {
                throw new MalformedRateResponseException(
                    "Non-numeric rate value for \"{$code}\": ".json_encode($rate)
                );
            }
        }

        return $body['data'];
    }
}
