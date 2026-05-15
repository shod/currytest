<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Currency\DefaultCredentialWatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private static bool $credentialWarningLogged = false;

    public function register(): void {}

    public function boot(): void
    {
        try {
            if (! self::$credentialWarningLogged && app(DefaultCredentialWatcher::class)->shouldWarn()) {
                self::$credentialWarningLogged = true;
                Log::warning('Admin account is still using the documented MVP default password. Change ADMIN_PASSWORD in .env immediately.');
            }
        } catch (\Throwable) {
            // Table may not exist yet (migrations not run); skip warning.
        }
    }
}
