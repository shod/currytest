# Quickstart: Currency Storage and Conversion Module

**Audience**: A developer (or future Claude session) picking up this feature for the first
time. This document is the on-ramp; the canonical sources of truth are [spec.md](spec.md),
[plan.md](plan.md), [research.md](research.md), and the [contracts](contracts/) directory.

---

## 1. One-time setup

Run from the project root (`c:\Project\Herd\currytest`).

```powershell
# Composer dependencies (already installed but safe to re-run after a fresh clone).
composer install

# Ensure a local .env exists and add the freecurrencyapi key (see § 2 below).
Copy-Item .env.example .env -ErrorAction SilentlyContinue
php artisan key:generate

# Apply migrations + run the feature seeders (admin + supported currencies).
php artisan migrate --force
php artisan db:seed --force
```

After seeding, the admin login is `admin` / `Aqaz` (MVP default — see § 5). The application
is served by Laravel Herd at **https://currytest.test/admin/login** (resolve URLs with the
Boost `get-absolute-url` tool — do not hard-code the host).

---

## 2. Environment configuration

Add to `.env`:

```ini
FREECURRENCYAPI_KEY=your-real-key-from-freecurrencyapi.com
FREECURRENCYAPI_BASE_CURRENCY=USD
FREECURRENCYAPI_TIMEOUT=10

# Optional overrides for the seeded admin (FR-020).
ADMIN_USERNAME=admin
ADMIN_PASSWORD=Aqaz
```

Application code reads these **only** via `config('currency.*')` — never via `env()` /
`getenv()` / `$_ENV` outside `config/currency.php` (FR-013, SC-007).

The key MUST NOT be committed. `.env` is git-ignored by Laravel by default; verify with
`git check-ignore .env` if in doubt.

---

## 3. Manually refresh exchange rates

Run on demand from the CLI (FR-012):

```powershell
php artisan currency:refresh-rates
```

The command writes a row to `refresh_job_logs` (status `success` / `failure` /
`skipped_overlap`) and either upserts every supported currency's rate or leaves the existing
rates untouched (FR-009 / FR-015).

A scheduled run at **03:15 application-timezone daily** does the same automatically.
Scheduler setup on Windows / Herd: arrange for `php artisan schedule:run` to fire every
minute via Windows Task Scheduler. (Linux / Laravel Cloud uses a standard cron entry.)

---

## 3a. Smoke-test the conversion service from the CLI

A thin Artisan wrapper exists for one-off conversion checks (research Decision 11). It calls
the same `CurrencyConverter` service the application uses, performs zero outbound HTTP, and
prints a single line of output:

```powershell
php artisan currency:convert 100 USD RUB
# USD 100 -> RUB 9140.00      (exit 0)

php artisan currency:convert 100 usd " rub "
# USD 100 -> RUB 9140.00      (normalisation, FR-024)

php artisan currency:convert -1 USD RUB
# error: amount must be non-negative      (exit 1, FR-023)

php artisan currency:convert 50 USD XYZ
# error: currency code "XYZ" is not in the supported list      (exit 2, FR-007)
```

Use this command in support runbooks, manual smoke tests, or when reproducing a reported
conversion bug. It is **not** part of the spec's Functional Requirements — application code
should call `CurrencyConverter::convert(...)` directly (see § 4 below).

---

## 4. Use the conversion service from application code

```php
use App\Services\Currency\CurrencyConverter;

public function priceInRubles(int $usdCents, CurrencyConverter $converter): string
{
    // Returns a numeric string with 2 decimal places, e.g. "9140.00".
    return $converter->convert($usdCents / 100, 'USD', 'RUB');
}
```

Behaviour highlights (full contract in
[contracts/currency-converter.md](contracts/currency-converter.md)):

- Negative amounts → `InvalidConversionAmountException` (FR-023).
- Zero amount → `"0.00"` returned without DB access.
- Codes with surrounding whitespace or in lower/mixed case are accepted and normalised (FR-024).
- Unknown codes → `UnsupportedCurrencyException` (FR-007).
- No outbound HTTP traffic during a conversion call (SC-005).

---

## 5. Admin panel

Two Blade pages live under `/admin`:

| Route name | URL | Purpose | Spec ref |
|---|---|---|---|
| `admin.login` | `/admin/login` | Username/password login form. | research Decision 6 |
| `admin.currencies.index` | `/admin/currencies` | Read-only list of supported currencies. | FR-016, Story 4 |
| `admin.rates.index` | `/admin/rates` | Read-only list of stored exchange rates with last-refresh metadata. | FR-010, Story 3 |

Both pages are protected by the `EnsureUserIsAdmin` middleware. The seeded admin user is the
only account that satisfies this gate (this feature does not introduce a role/permission
model). A red banner is rendered on both pages when the seeded admin still has the documented
MVP default password while the application is running outside the `local` environment
(FR-022).

---

## 6. Running tests

Tests live under `tests/Feature` (default) and `tests/Unit` (for the converter math and code
normalisation). Use the project's PHPUnit setup:

```powershell
# Whole suite.
php artisan test --compact

# Single file (used during TDD red/green cycles).
php artisan test --compact tests/Unit/Services/CurrencyConverterTest.php

# Single test method.
php artisan test --compact --filter=converts_through_base_currency
```

The Laravel `Http` facade is faked in every test that touches the provider integration
(`Http::fake()` + `Http::preventStrayRequests()`); no real network access happens in CI.

---

## 7. Code-quality gates before opening a PR

1. `vendor/bin/pint --dirty --format agent` — formats only files in your working diff.
2. `php artisan test --compact` — must be green.
3. LaraStan level ≥ 6 (Principle VI). This package will be added under user approval before
   feature work begins; once installed, the command is `vendor/bin/phpstan analyse`.
4. Re-verify [contracts/freecurrencyapi.md](contracts/freecurrencyapi.md) against the live
   vendor docs and cite the relevant sections in the PR description (Principle VII).
