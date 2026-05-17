<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AvailableCurrenciesController;
use App\Http\Controllers\Admin\CurrencyRatesController;
use App\Http\Controllers\Admin\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])->middleware('admin')->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('rates', [CurrencyRatesController::class, 'index'])->name('rates.index');
        Route::get('currencies', [AvailableCurrenciesController::class, 'index'])->name('currencies.index');
    });
});
