<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DefaultCredentialWatcher
{
    private ?bool $cachedResult = null;

    public function usingDocumentedDefault(): bool
    {
        if ($this->cachedResult !== null) {
            return $this->cachedResult;
        }

        $adminUser = User::where('username', config('currency.admin.username'))->first();

        if ($adminUser === null) {
            return $this->cachedResult = false;
        }

        return $this->cachedResult = Hash::check(
            config('currency.admin.password'),
            $adminUser->password
        );
    }

    public function shouldWarn(): bool
    {
        return app()->environment() !== 'local' && $this->usingDocumentedDefault();
    }
}
