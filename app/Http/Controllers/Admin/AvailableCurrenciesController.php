<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\View\View;

class AvailableCurrenciesController extends Controller
{
    public function index(): View
    {
        $currencies = Currency::orderBy('code')->get();

        return view('admin.currencies', compact('currencies'));
    }
}
