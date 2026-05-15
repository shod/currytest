<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\RefreshJobLog;
use Illuminate\View\View;

class CurrencyRatesController extends Controller
{
    public function index(): View
    {
        $rates = ExchangeRate::with('targetCurrency')
            ->orderBy('base_code')
            ->orderBy('target_currency_id')
            ->get();

        $lastRefresh = RefreshJobLog::where('status', 'success')
            ->orderByDesc('started_at')
            ->first();

        return view('admin.rates', compact('rates', 'lastRefresh'));
    }
}
