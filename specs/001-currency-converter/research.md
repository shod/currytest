# Phase 0 Research: Currency Storage and Conversion Module

**Branch**: `001-currency-converter`
**Date**: 2026-05-15
**Inputs**: [spec.md](spec.md), [plan.md](plan.md), constitution v1.1.0

All `NEEDS CLARIFICATION` items in Technical Context were resolved during the `/speckit-clarify`
session (see Clarifications block in spec.md). This document records the remaining technical
decisions needed to start implementation, each consulted against authoritative sources per
Principle VII.

---

## Decision 1 — Scheduling the daily refresh

**Decision**: Use Laravel's framework scheduler in `routes/console.php`:

```php
Schedule::command('currency:refresh-rates')
    ->dailyAt('03:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30)
    ->onFailure(fn () => /* log */ );
```

Daily at 03:15 in the application timezone. `withoutOverlapping(30)` guards against a manual
refresh overlapping the scheduled run (FR-014). The Artisan command itself is invokable for
FR-012 manual triggers.

**Rationale**:
- `dailyAt('HH:MM')` is the documented Laravel-13 idiom for once-a-day jobs (Boost
  `search-docs` → *Scheduling > Schedule Frequency Options*, laravel/framework@13.x).
- `withoutOverlapping($minutes)` is the documented Laravel-13 lock primitive backed by the
  application cache (Boost `search-docs` → *Scheduling > Preventing Task Overlaps*,
  laravel/framework@13.x). Project already has `cache_locks` table.
- 03:15 (non-zero minute, off-peak) avoids the top-of-hour cron stampede; choosing the
  application's configured timezone meets the edge-case requirement that "once per day" must
  be predictable.

**Alternatives considered**:
- A custom Eloquent-flag idempotency check inside the command (e.g. "skip if a successful
  Refresh Job Log exists for today"). Rejected: duplicates framework functionality
  (`withoutOverlapping`) and adds clock-edge bugs.
- Queue-based `ShouldBeUnique` job. Rejected: introduces queue worker requirement on local
  dev (Herd) without benefit; SQLite jobs table is already present but queue execution adds
  operational complexity not justified by once-a-day cadence.

---

## Decision 2 — HTTP client and retry policy

**Decision**: Use the Laravel `Http` facade (Guzzle-backed) with the documented retry helper:

```php
Http::baseUrl(config('currency.freecurrencyapi.base_url'))
    ->retry([30_000, 300_000], throw: false)   // 30s, 5m
    ->timeout(config('currency.freecurrencyapi.timeout_seconds'))
    ->get('/v1/latest', [
        'apikey'         => config('currency.freecurrencyapi.api_key'),
        'base_currency'  => $base,
        'currencies'     => $codes,
    ]);
```

`Http::retry([30_000, 300_000])` performs up to two additional attempts (3 total) with sleep
of 30 s then 5 min, matching the clarified retry policy (Q5 / FR-009). `throw: false` lets the
service inspect the final response and produce a structured Refresh Job Log entry rather than
relying on exception flow.

**Rationale**:
- `retry($times|array, $sleep, $when, throw: bool)` is the documented Laravel-13 HTTP client
  API (Boost `search-docs` → *Http Client > Retries*, laravel/framework@13.x). Passing an
  array directly encodes the backoff schedule without a closure, keeping the call site
  declarative.
- FR-003 forbids vendor-specific SDKs but explicitly allows Guzzle. The `Http` facade is a
  thin Guzzle wrapper shipped with the framework — it is *not* a freecurrencyapi-specific
  client and therefore satisfies FR-003.
- Configuration values live in `config/currency.php`, so the HTTP call is reachable in tests
  via `Http::fake()` (Principle V).

**Alternatives considered**:
- Direct Guzzle (`new GuzzleHttp\Client(...)`). Rejected: re-implements retry, fake-ing,
  timeout, and logging that the `Http` facade already provides; not idiomatic Laravel.
- `file_get_contents` / cURL. Rejected: harder to fake in tests and missing retry/backoff
  helpers; would require hand-rolled middleware to meet FR-009.

---

## Decision 3 — Concurrent-refresh safety

**Decision**: Two layers, both backed by the existing SQLite cache_locks table:
1. **Scheduler lock** — `withoutOverlapping(30)` (see Decision 1) prevents two scheduler-driven
   invocations from racing.
2. **Atomic write lock inside the command** — `Cache::lock('currency:refresh', 600)->block(0, fn ()
   => $refresher->run())` guards the manual Artisan invocation against overlapping with the
   scheduler (e.g. operator triggers `php artisan currency:refresh-rates` while the
   scheduled tick is mid-flight). When the lock is unavailable the command exits with a
   non-zero status and logs a "refresh already in progress" Refresh Job Log entry.

The rate-write step itself uses `DB::transaction(fn () => ExchangeRate::upsert(...))` so that a
partial provider response cannot leave the rate table half-updated (FR-014, FR-015, SC-006).

**Rationale**:
- Boost `search-docs` → *Cache > Atomic Locks* (laravel/framework@13.x) confirms `database`
  cache driver supports atomic locks; project's default is `database` and the `cache_locks`
  table is already migrated.
- The two-layer approach mirrors the recommended pattern in the Boost *Artisan > Isolatable
  Commands* docs (laravel/framework@13.x) and is robust against both scheduler-vs-scheduler
  and scheduler-vs-manual races without re-implementing locking.

**Alternatives considered**:
- A single SQLite advisory lock or `SELECT … FOR UPDATE`. Rejected: SQLite has no row-level
  advisory locks; relying on transaction scope alone does not prevent two processes from each
  reading "stale" state and writing competing upserts.
- Marking the Artisan command `isolatable`. Rejected: only protects same-name CLI
  invocations, not the in-process scheduler tick.

---

## Decision 4 — Decimal precision storage

**Decision**: Store exchange-rate values as `DECIMAL(20,10)` in SQLite via the migration
helper `$table->decimal('rate', total: 20, places: 10)`. Compute conversions with `bcmath` at
scale 10, then round the final returned value to 2 decimal places half-up. Amounts entering
the service may be `int|float|string`; they are immediately cast to a `bcmath` string at the
service boundary.

**Rationale**:
- `decimal($column, total, places)` is the documented Laravel-13 migration helper (Boost
  `search-docs` → *Migrations > Available Column Types > `decimal()`*,
  laravel/framework@13.x). SQLite stores DECIMAL as `NUMERIC` affinity, which Laravel reads
  back as string — preserving precision for `bcmath` arithmetic without float coercion.
- freecurrencyapi.com publishes rates with up to ~6 significant decimal places; scale 10
  provides a safety margin without bloating storage.
- Half-up rounding at 2 decimal places is the convention for monetary display in
  consumer-facing prices (and matches Q4 of the clarification session).

**Alternatives considered**:
- `brick/money`. Rejected: pulls a new Composer dependency that would need user approval
  (CLAUDE.md) and is overkill for a single-precision arithmetic path.
- Storing rates as floats. Rejected: defeats FR-008's precision requirement; even with
  `bcmath` at the service layer, float storage round-trips lose digits.

---

## Decision 5 — Currency-code normalisation entry point

**Decision**: Normalisation (`trim()` + `mb_strtoupper(…, 'UTF-8')`) happens **once** at the
public boundary of `CurrencyConverter::convert(...)`. Internal collaborators (`RateRefresher`,
`FreeCurrencyApiClient`, repository queries) receive already-normalised ISO-4217 codes and
trust them. The pre-normalisation form is preserved only for inclusion in error messages so
operators can see exactly what was passed.

**Rationale**: Concentrating normalisation at the boundary keeps internal call sites simple
and prevents double-normalisation bugs. Matches Q3 of the clarification session and FR-024.

**Alternatives considered**:
- Normalise inside the `Currency` Eloquent model via a mutator. Rejected: the service
  contract is the public surface; the model already stores codes in canonical form via
  seeders, so a mutator only adds an indirection without changing behaviour at the boundary.

---

## Decision 6 — Admin authentication for the new admin panel

**Decision**: Minimal Laravel session-based authentication backed by the default `web` guard
and the existing `users` table, with one new column:

- Add a `username` (string, unique, nullable) column to `users` via a focused migration.
- Configure the admin login form to authenticate against `username` + `password`, using
  `Auth::attempt(['username' => $request->validated('username'), 'password' => $request->validated('password')])`.
- Protect both admin pages with a custom `EnsureUserIsAdmin` middleware that checks the
  authenticated user's `username === 'admin'` (the seed creates a single admin user; the
  feature does not introduce a role/permission model).
- `AdminUserSeeder` seeds the admin user if absent, hashes the default password with
  `Hash::make()`, and reads overrides from `config/currency.php` keys
  (`admin.username`, `admin.password`) so callers comply with FR-013.

No first-party scaffolding (Breeze/Fortify/Jetstream) is installed — keeps Amendment 5 / FR-018
satisfied and the dependency surface minimal.

**Rationale**: Laravel's default `web` guard already handles session cookies, CSRF, and the
`auth` middleware. Boost `search-docs` confirms the `Auth` facade resolves to
`Illuminate\Auth\AuthManager` and supports custom credential keys directly. A username column
is the smallest clean schema change to honour FR-020's "login `admin`" requirement.

**Alternatives considered**:
- Laravel Breeze (Blade flavour). Rejected: adds dependencies and templates beyond what FR-019
  permits ("intentionally minimal for MVP"); also installs registration/password-reset views
  that are out of scope for this feature.
- Reuse the `name` column as login identifier. Rejected: `name` is not unique in the default
  schema and conventionally represents a display label rather than a credential.
- HTTP Basic auth on the admin routes. Rejected: less testable, no logout UX, and creates a
  poorer foundation for follow-up admin work (which other features in this project are likely
  to extend).

---

## Decision 7 — Boot-time and runtime warning for default credentials

**Decision**: Implement two complementary signals required by FR-022:

1. **Boot-time log warning** — In `App\Providers\AppServiceProvider::boot()`, when
   `app()->environment() !== 'local'` AND the seeded admin still has the documented MVP
   default password (detected via `Hash::check('Aqaz', $adminUser->password)`), call
   `Log::warning('Admin account is still using the documented MVP default password ...')`. The
   check runs at most once per request and is cached for the duration of the request to avoid
   repeated bcrypt comparisons.
2. **Admin-panel banner** — A small Blade partial (`resources/views/admin/_default-credential-warning.blade.php`)
   included in `admin/layout.blade.php` renders a red banner above the page content when the
   same condition holds. The banner pulls its visibility flag from a single service method to
   avoid duplicating the `Hash::check` call.

**Rationale**: Two signals (server log + UI) cover both operations dashboards and operator
walk-bys, as the spec lists both as acceptable. Caching the check per-request keeps the
detection cheap.

**Alternatives considered**:
- Hard-fail the boot in non-local environments while the default is in place. Rejected:
  prevents operators from logging in to rotate the password and would block production
  hot-fix scenarios; the spec explicitly only requires a warning.

---

## Decision 8 — freecurrencyapi.com endpoint, request shape, and response shape

**Decision**: Use the public `GET https://api.freecurrencyapi.com/v1/latest` endpoint with
query parameters `apikey`, `base_currency` (default `USD`), and `currencies` (comma-separated
ISO codes). The response envelope is `{ "data": { "EUR": 0.92, "RUB": 91.4, ... } }`. Rates
returned for currencies not in the supported list are ignored (FR-002 + Story 2 scenario 3).

**Source citation**: freecurrencyapi.com vendor documentation at
`https://freecurrencyapi.com/docs`. The implementation task that drafts
`FreeCurrencyApiClient` MUST open the live vendor documentation, confirm the endpoint path,
query parameter names, authentication header vs query placement, error response shape, and
rate-limit headers, and cite the relevant section in the resulting PR description. If the
documentation contradicts any assumption in this Decision block, the documentation wins
(Principle VII).

**Rationale**: Documenting the expected shape lets us draft contracts and tests now; the
verification step before HTTP code is committed is what keeps Principle VII honoured.

**Alternatives considered**:
- Hard-code the shape from training data without rechecking. Rejected outright — exactly the
  failure mode Principle VII exists to prevent.

---

## Decision 9 — Refresh Job Log entity & schema

**Decision**: A `refresh_job_logs` table with:

- `id` (PK)
- `started_at` (datetime, indexed)
- `finished_at` (nullable datetime)
- `status` (enum-style string: `success`, `failure`, `skipped_overlap`)
- `attempts` (tinyint, default 1 — tracks 1..3 within a single run)
- `currencies_updated` (nullable int)
- `error_summary` (nullable text — short HTTP status / exception class + message)
- `error_detail` (nullable text — provider response body or stack trace excerpt)
- `triggered_by` (enum-style string: `scheduler`, `manual`)
- `created_at` / `updated_at`

One row per scheduled or manual invocation. Retries inside a single invocation increment
`attempts` and overwrite the same row; the row is finalised when the run terminates. Rows are
not pruned by this feature (history retention is a future operational concern).

**Rationale**: Captures all data required by FR-009 (diagnostic context), FR-014
(distinguishing overlap-skip from real failure), and Story 3 (admin visibility into refresh
freshness via `finished_at` of the most recent `success`). The compact column set keeps the
table writable from the command without an additional service.

**Alternatives considered**:
- Use Laravel's `failed_jobs` table only. Rejected: it only captures failures, not the
  successful-run history needed to compute "rates are no more than 24 h old" trust signals.

---

## Decision 10 — Supported-currency starter set & seeder

**Decision**: `CurrencySeeder` populates the following starter set (idempotent — uses
`Currency::updateOrCreate(['code' => $code], …)`): `USD`, `EUR`, `RUB`, `GBP`, `CNY`, `JPY`,
`CHF`, `CAD`, `AUD`, `PLN`. Each row carries `code` (uppercase ISO-4217), `name` (English
display name), and `is_enabled` (default `true`).

The seeder is registered in `DatabaseSeeder::run()` alongside `AdminUserSeeder` (both
idempotent). Changing the supported list is, per FR-001, a "re-run the seeder" action — no UI
path.

**Rationale**: Ten currencies cover the spec's example set (USD/EUR/RUB/GBP) plus the most
commonly used additions, while keeping SC-002 ("update every supported currency on at least
99% of attempts") realistic against the provider's published latency.

**Alternatives considered**:
- Default to all ISO-4217 codes (~180). Rejected: every additional code is an additional
  freecurrencyapi response field the system must keep fresh; not justified for MVP.

---

## Decision 11 — Console command to exercise `CurrencyConverter` from the CLI

**Decision**: Ship an Artisan command `currency:convert {amount} {from} {to}` (implemented as
`App\Console\Commands\ConvertCurrency`) that resolves `CurrencyConverter` from the container,
calls `convert($amount, $from, $to)`, and prints the result to stdout. This is a
developer/operator smoke-test wrapper around the existing service — **not a new public
contract**.

Command signature:

```php
protected $signature = 'currency:convert
    {amount : Amount in the source currency (numeric string or float)}
    {from   : Source currency code (ISO-4217; case-insensitive)}
    {to     : Target currency code (ISO-4217; case-insensitive)}';

protected $description = 'Convert an amount between two supported currencies using stored rates.';
```

Behaviour:

- Resolves `CurrencyConverter` via constructor DI / `app(CurrencyConverter::class)` per Principle VI.
- On success prints exactly one line: `{normalised_from} {amount_in} -> {normalised_to} {result}`
  (e.g. `USD 100 -> RUB 9140.00`) and exits with status `0`.
- On `UnsupportedCurrencyException` / `InvalidConversionAmountException` /
  `MissingExchangeRateException` from the service: prints `error: <message>` to stderr and
  exits with a non-zero status (`1` for invalid input, `2` for missing rate / unsupported).
  Exception types map 1:1 to exit codes so scripts can branch on them.
- Performs no DB writes; performs no outbound HTTP (re-affirms SC-005 at the CLI surface).

Scope boundary (what this command is **not**):

- It is not part of the spec's Functional Requirements. The spec does not require a CLI
  conversion path; only the in-app `$converter->convert(...)` is in scope (FR-005 / SC-003).
- It does not introduce a new public contract beyond what
  [contracts/currency-converter.md](contracts/currency-converter.md) already specifies — the
  command is a thin shell over that contract.
- It does not perform refresh (`currency:refresh-rates` is the separate command for that —
  see Decision 1) and never falls back to live rates.

**Rationale**:

- Gives a developer or operator a one-line way to verify "is the seeded rate giving sensible
  output?" without writing a tinker snippet, satisfying the Boost guideline of *"prefer
  existing Artisan commands over custom tinker code"* (CLAUDE.md → Tinker section).
- Provides a documented entry point for support scenarios where the conversion result is
  suspect (e.g. paste the command into a ticket reproducer).
- Test surface is small: one feature test (`ConvertCurrencyCommandTest`) that seeds a known
  rate, runs `artisan('currency:convert', […])`, and asserts the printed line and exit code
  using `expectsOutput()` / `assertExitCode()`. The test does **not** re-cover the
  CC-01..CC-10 cases — those remain owned by `CurrencyConverterTest` per
  [contracts/currency-converter.md](contracts/currency-converter.md).

**Alternatives considered**:

- Add the conversion as a sub-mode of `currency:refresh-rates` (e.g. `--dry-run-convert`).
  Rejected: conflates two distinct operator intents and makes the help text muddier.
- Skip the command and direct operators to `php artisan tinker --execute …`. Rejected:
  tinker invocations are easy to fat-finger and harder to embed in support runbooks; an
  explicit `php artisan currency:convert 100 USD RUB` is significantly safer.
- Make the command interactive (Laravel Prompts `text()` / `select()`). Rejected: an
  interactive prompt cannot be embedded in CI smoke tests or runbook scripts; the positional
  signature stays scriptable.

---

## Open questions deferred to implementation

None that block planning. The only verification gates that still need a human-in-the-loop are:

1. User approval to add `larastan/larastan` (constitution VI) — captured under Complexity
   Tracking in [plan.md](plan.md).
2. The freecurrencyapi.com vendor-documentation re-check at implementation time
   (Decision 8) — explicitly required by Principle VII before HTTP code is committed.
