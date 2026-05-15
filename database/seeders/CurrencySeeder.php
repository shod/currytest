<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    private const CURRENCIES = [
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

    public function run(): void
    {
        foreach (self::CURRENCIES as $code => $name) {
            Currency::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_enabled' => true]
            );
        }
    }
}
