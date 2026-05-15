<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'base_code' => 'USD',
            'target_currency_id' => Currency::factory(),
            'rate' => number_format($this->faker->randomFloat(10, 0.01, 200.0), 10, '.', ''),
            'fetched_at' => now(),
        ];
    }
}
