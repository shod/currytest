# Implementation Plan: Currency Storage and Conversion Module

**Branch**: `001-currency-converter` | **Date**: 2026-05-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-currency-converter/spec.md`

## Summary

Build a self-contained currency module inside this Laravel 13 / PHP 8.4 / SQLite project. It
exposes a `CurrencyConverter` service (`$converter->convert(123, 'USD', 'RUB')`), persists a
supported-currency list and exchange-rate store in SQLite, refreshes rates daily from
freecurrencyapi.com via Laravel's HTTP client (Guzzle-backed) with bounded retries, exposes two
Blade admin pages (Currency Rates, Available Currencies) behind a minimal session-based admin
login, and computes monetary results with `bcmath` at scale 10 rounded to 2 decimal places
half-up. A seeded admin user (`admin` / `Aqaz`) bootstraps access; default-credential use in
non-development environments emits a clearly identifiable warning.

## Technical Context

**Language/Version**: PHP 8.4 (composer requires `^8.3`; Boost reports running interpreter 8.4)

**Primary Dependencies**: laravel/framework 13.9, laravel/boost 2.4, laravel/pail 1.2,
laravel/pint 1.29, laravel/prompts 0.3, phpunit/phpunit 12.5, tailwindcss 4.3. New first-party
addition planned: `larastan/larastan` (dev) to satisfy Principle VI quality-automation gate —
requires user approval per CLAUDE.md dependency rule; tracked under Complexity Tracking.

**Storage**: SQLite (`database/database.sqlite`). All feature tables delivered via Laravel
migrations. Cache + cache_locks tables already present and used for atomic locks.

**Testing**: PHPUnit 12 feature tests by default (factories for `User`, `Currency`,
`ExchangeRate`); unit tests reserved for `CurrencyConverter` math and code-normalisation logic.
`Http::fake()` for outbound provider calls in tests; `RefreshDatabase` trait for isolation.

**Target Platform**: Local development via Laravel Herd (project URL
`https://currytest.test`). Production deployment via Laravel Cloud (per `herd` constitution
guidance) — not in scope for this feature.

**Project Type**: Web application (Laravel monolith, Blade server-side rendering, no
SPA / JS framework, no third-party admin scaffolding).

**Performance Goals**: SC-004 / SC-004a — both admin pages render in under 5 seconds.
SC-005 — conversion requests perform zero outbound HTTP calls (rates served from SQLite).

**Constraints**: SQLite-only persistence; Blade-only rendering; no `everapihq/freecurrencyapi-php`
or any other vendor-specific SDK; HTTP integration via Laravel's `Http` facade (Guzzle-backed,
permitted by FR-003 as a generic HTTP mechanism); `bcmath` for all conversion arithmetic;
`config(...)` for API-key access (no `env()` / `getenv()` / `$_ENV` outside `config/`).

**Scale/Scope**: Supported currency list O(10–20) rows; exchange-rate store one row per
supported currency (base → target), so O(10–20) rows. One admin user. Daily scheduled refresh
is the only outbound HTTP traffic.

## Constitution Check

*Gate evaluated against `.specify/memory/constitution.md` v1.1.0 (7 principles).*

| Principle | Status | Notes |
|---|---|---|
| I. Test-First Development | PASS | Every implementation task in the upcoming `tasks.md` will be preceded by a failing PHPUnit test (feature for HTTP/admin paths, unit for math + code normalisation). |
| II. Do Things the Laravel Way | PASS | `php artisan make:` for migrations, models, factories, seeders, requests, controllers, commands, tests; named routes for admin pages; Eloquent for all DB access; framework scheduler for daily refresh; framework cache locks for concurrency control. |
| III. Code Quality & Formatting | PASS | `vendor/bin/pint --dirty --format agent` after each PHP edit batch. |
| IV. Agentic Development with Laravel Boost | PASS | Boost `search-docs` consulted during planning (scheduling, HTTP retries, cache locks, decimal migrations); `database-schema` consulted before drafting migrations; `get-absolute-url` will be used before sharing admin URLs. |
| V. PHPUnit Testing Discipline | PASS | Feature tests by default, unit tests for isolated logic; factories with states for `User`, `Currency`, `ExchangeRate`; happy paths + failure paths + edge cases per FR/SC. |
| VI. Code Quality (NON-NEGOTIABLE) | PASS-with-action | `declare(strict_types=1)` on every new PHP file; constructor DI; PSR-12 + Pint; PHPDoc in Russian on public service APIs; camelCase PHP / snake_case columns. LaraStan ≥6 is required by the principle but not yet installed — see Complexity Tracking; an early setup task will add it with user approval. |
| VII. Documentation-First Integration | PASS | Boost docs cited in `research.md`. freecurrencyapi.com vendor docs (`https://freecurrencyapi.com/docs`) MUST be re-verified during the first integration task before request shapes / response parsing are committed; no endpoint paths, query parameters, or response field names will be invented. |

No principle is violated. The single quality-automation gap (LaraStan installation) is tracked
explicitly and gated on user approval.

## Project Structure

### Documentation (this feature)

```text
specs/001-currency-converter/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   ├── currency-converter.md   # Public service contract
│   └── freecurrencyapi.md      # External provider contract (cited from vendor docs)
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

Standard Laravel layout. No new base folders; the feature lives inside existing directories.

```text
app/
├── Console/
│   └── Commands/
│       ├── RefreshCurrencyRates.php          # Manual refresh (FR-012) + scheduler entry
│       └── ConvertCurrency.php               # Dev/operator smoke-test wrapper around CurrencyConverter (research Decision 11)
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       ├── LoginController.php           # Minimal session-based login (FR-011 prerequisite)
│   │       ├── CurrencyRatesController.php   # FR-010 admin page
│   │       └── AvailableCurrenciesController.php # FR-016 admin page
│   ├── Middleware/
│   │   └── EnsureUserIsAdmin.php             # FR-011 authorisation guard
│   └── Requests/
│       └── Admin/LoginRequest.php
├── Models/
│   ├── User.php                              # Existing — extended with username column
│   ├── Currency.php                          # FR-001 / Key Entity
│   ├── ExchangeRate.php                      # FR-002 / Key Entity
│   └── RefreshJobLog.php                     # FR-009 / FR-014 / Key Entity
├── Providers/
│   └── AppServiceProvider.php                # Existing — registers Schedule + boot-time warning (FR-022)
└── Services/
    └── Currency/
        ├── CurrencyConverter.php             # FR-005 / FR-006 / FR-007 / FR-008 / FR-023 / FR-024
        ├── RateRefresher.php                 # FR-004 / FR-009 / FR-014 / FR-015
        ├── FreeCurrencyApiClient.php         # FR-003 — Http facade wrapper, no third-party SDK
        └── Exceptions/
            ├── UnsupportedCurrencyException.php
            └── InvalidConversionAmountException.php

config/
└── currency.php                              # FR-013 — feature config; reads env() only here

database/
├── factories/
│   ├── CurrencyFactory.php
│   ├── ExchangeRateFactory.php
│   └── UserFactory.php                       # Existing — extended with admin() state
├── migrations/
│   ├── YYYY_..._add_username_to_users_table.php
│   ├── YYYY_..._create_currencies_table.php
│   ├── YYYY_..._create_exchange_rates_table.php
│   └── YYYY_..._create_refresh_job_logs_table.php
└── seeders/
    ├── AdminUserSeeder.php                   # FR-020 / FR-021 — idempotent
    └── CurrencySeeder.php                    # FR-001 — canonical supported list

resources/views/admin/
├── layout.blade.php                          # Shared minimal Blade layout
├── login.blade.php
├── rates.blade.php                           # FR-010
└── currencies.blade.php                      # FR-016

routes/
├── console.php                               # Existing — adds Schedule::command(...) for daily refresh
└── web.php                                   # Existing — adds named admin routes

tests/
├── Feature/
│   ├── Admin/
│   │   ├── LoginTest.php
│   │   ├── CurrencyRatesPageTest.php
│   │   └── AvailableCurrenciesPageTest.php
│   ├── Console/
│   │   ├── RefreshCurrencyRatesCommandTest.php
│   │   └── ConvertCurrencyCommandTest.php
│   ├── Services/
│   │   └── RateRefresherTest.php
│   └── Seeders/
│       └── AdminUserSeederTest.php
└── Unit/
    └── Services/
        └── CurrencyConverterTest.php
```

**Structure Decision**: Single-project Laravel monolith using the standard Laravel directory
tree. Feature services live under `app/Services/Currency/` to keep them grouped and
discoverable; admin controllers live under `app/Http/Controllers/Admin/` because more
admin-only routes are likely in follow-up features. No new top-level folders.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Add `larastan/larastan` dev dependency | Principle VI mandates "LaraStan (minimum level 6) must pass without errors" but the package is not installed. | Skipping installation leaves Principle VI structurally unsatisfiable; the principle is NON-NEGOTIABLE. Will be added in an early setup task with explicit user approval, separately from feature work. |
| Add `username` column to `users` table | Spec (FR-020) requires login identifier `admin`, but the existing `users` schema uses `email` as the login. | Repurposing `email = 'admin'` mixes display and identifier semantics and breaks the unique-email assumption baked into Laravel auth defaults. A dedicated unique `username` column is the smallest clean change and leaves `email` free for future use. |
