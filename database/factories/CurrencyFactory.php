<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    private static array $isoCodes = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'RUB' => 'Russian Ruble',
        'GBP' => 'British Pound',
        'CNY' => 'Chinese Yuan',
        'JPY' => 'Japanese Yen',
        'CHF' => 'Swiss Franc',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'PLN' => 'Polish Zloty',
    ];

    private static array $usedCodes = [];

    public function definition(): array
    {
        $available = array_diff(array_keys(self::$isoCodes), self::$usedCodes);

        if (empty($available)) {
            $code = strtoupper($this->faker->unique()->lexify('???'));
            $name = $this->faker->word().' Currency';
        } else {
            $code = $this->faker->randomElement($available);
            $name = self::$isoCodes[$code];
        }

        self::$usedCodes[] = $code;

        return [
            'code' => $code,
            'name' => $name,
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
