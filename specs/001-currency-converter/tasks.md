---

description: "Implementation tasks for the Currency Storage and Conversion Module"
---

# Tasks: Currency Storage and Conversion Module

**Input**: Design documents from `specs/001-currency-converter/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/)

**Tests**: Required. Constitution Principle I (NON-NEGOTIABLE) mandates Test-First Development.
Every user story has Independent Test criteria in [spec.md](spec.md). Within each phase,
failing tests are written *before* implementation tasks (TDD red-green-refactor cycle).

**Organization**: Tasks are grouped by user story. US1 (P1) is the MVP — the conversion
service plus the `currency:convert` CLI smoke-test wrapper. US2 (P2) adds daily refresh. US3
and US4 (both P3) add the two admin pages and share an authentication scaffold built inside
US3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps the task to a user story (US1, US2, US3, US4)
- Exact file paths in every task description

## Path Conventions

Standard Laravel 13 layout. Repository root is `c:\Project\Herd\currytest\`. Paths are
project-relative throughout.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Toolchain and configuration prerequisites that every later phase relies on.

- [X] T001 Obtain user approval and add `larastan/larastan` dev dependency to satisfy Constitution Principle VI; run `composer require --dev larastan/larastan` and add `phpstan.neon` at repo root with `level: 6` and `paths: [app]`
- [X] T002 [P] Create `config/currency.php` exposing `freecurrencyapi.api_key`, `freecurrencyapi.base_url`, `freecurrencyapi.base_currency`, `freecurrencyapi.timeout_seconds`, `admin.username`, `admin.password` — read via `env()` ONLY in this file (FR-013)
- [X] T003 [P] Update `.env.example` with `FREECURRENCYAPI_KEY=`, `FREECURRENCYAPI_BASE_CURRENCY=USD`, `FREECURRENCYAPI_TIMEOUT=10`, `ADMIN_USERNAME=admin`, `ADMIN_PASSWORD=Aqaz`

**Checkpoint**: Toolchain and config keys ready; no schema or code changes yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, entities, factories, seeders, and exception types that every user story
depends on. Tests are written first for behaviour-bearing artifacts (seeders); factories and
exception classes do not need dedicated tests (they will be exercised by downstream tests).

**⚠️ CRITICAL**: No user story work begins until this phase is complete.

### Schema

- [X] T004 [P] Generate migration via `php artisan make:migration add_username_to_users_table --no-interaction`; in `database/migrations/<timestamp>_add_username_to_users_table.php` add nullable unique `string('username', 32)` after `name`
- [X] T005 [P] Generate migration via `php artisan make:migration create_currencies_table --no-interaction`; in the generated file create `currencies` table per [data-model.md](data-model.md) (`id`, `string('code', 3)->unique()`, `string('name', 64)`, `boolean('is_enabled')->default(true)`, timestamps)
- [X] T006 [P] Generate migration via `php artisan make:migration create_exchange_rates_table --no-interaction`; in the generated file create `exchange_rates` table per [data-model.md](data-model.md) with `decimal('rate', total: 20, places: 10)`, FK `target_currency_id` → `currencies.id` cascade-on-delete, `string('base_code', 3)`, `dateTime('fetched_at')`, timestamps, and `unique(['base_code', 'target_currency_id'])`
- [X] T007 [P] Generate migration via `php artisan make:migration create_refresh_job_logs_table --no-interaction`; in the generated file create `refresh_job_logs` table per [data-model.md](data-model.md), including indexes `index('started_at')` and `index(['status', 'started_at'])`
- [X] T008 Apply schema with `php artisan migrate --force` and verify via Boost `database-schema` tool

### Models

- [X] T009 [P] Generate Currency model via `php artisan make:model Currency --no-interaction`; in `app/Models/Currency.php` add `declare(strict_types=1)`, `#[Fillable(['code', 'name', 'is_enabled'])]`, casts (`is_enabled => bool`), and `hasMany(ExchangeRate::class, 'target_currency_id')` relationship
- [X] T010 [P] Generate ExchangeRate model via `php artisan make:model ExchangeRate --no-interaction`; in `app/Models/ExchangeRate.php` add `declare(strict_types=1)`, `#[Fillable(['base_code', 'target_currency_id', 'rate', 'fetched_at'])]`, casts (`rate => 'decimal:10'`, `fetched_at => 'datetime'`), and `belongsTo(Currency::class, 'target_currency_id')` relationship
- [X] T011 [P] Generate RefreshJobLog model via `php artisan make:model RefreshJobLog --no-interaction`; in `app/Models/RefreshJobLog.php` add `declare(strict_types=1)`, fillable for all log columns, and casts (`started_at`, `finished_at` => `datetime`)
- [X] T012 [P] Extend existing `app/Models/User.php` — add `'username'` to the `#[Fillable(...)]` attribute list (do not modify casts or relationships)

### Factories

- [X] T013 [P] Generate `database/factories/CurrencyFactory.php` via `php artisan make:factory CurrencyFactory --model=Currency --no-interaction`; provide realistic defaults (`code` from a fixed ISO list, unique; `name` matching code; `is_enabled => true`) plus `disabled()` state
- [X] T014 [P] Generate `database/factories/ExchangeRateFactory.php` via `php artisan make:factory ExchangeRateFactory --model=ExchangeRate --no-interaction`; defaults include `base_code => 'USD'`, `target_currency_id` via `Currency::factory()`, `rate` as a numeric string with 10 decimal places, `fetched_at => now()`
- [X] T015 [P] Extend `database/factories/UserFactory.php` — add `admin()` state returning `['username' => 'admin', 'password' => Hash::make('Aqaz'), 'name' => 'Admin', 'email' => 'admin@local.test']`

### Seeders (test-first)

- [X] T016 [P] Create failing test `tests/Feature/Seeders/AdminUserSeederTest.php` covering SC-009 (idempotency: re-running the seeder yields exactly one admin row and does not overwrite a changed password) and SC-010 (the literal string `Aqaz` never appears in the persisted `users.password` value; `Hash::check('Aqaz', $row->password)` is true on first seed)
- [X] T017 Generate seeder via `php artisan make:seeder AdminUserSeeder --no-interaction`; in `database/seeders/AdminUserSeeder.php` implement idempotent create-if-absent against `users.username = config('currency.admin.username')`, hashing `config('currency.admin.password')` with `Hash::make`, logging "admin user already exists" when the row is present (FR-020, FR-021)
- [X] T018 [P] Generate seeder via `php artisan make:seeder CurrencySeeder --no-interaction`; in `database/seeders/CurrencySeeder.php` implement idempotent `updateOrCreate(['code' => $code], …)` for the starter set defined in research Decision 10 (`USD, EUR, RUB, GBP, CNY, JPY, CHF, CAD, AUD, PLN`)
- [X] T019 Update `database/seeders/DatabaseSeeder.php` — replace the default `User::factory()->create(...)` call with `$this->call([CurrencySeeder::class, AdminUserSeeder::class])`
- [X] T020 Run `php artisan db:seed --force` and verify with Boost `database-query` that `users.username = 'admin'` exists and `currencies` contains the ten starter rows

### Exceptions

- [X] T021 [P] Create `app/Services/Currency/Exceptions/UnsupportedCurrencyException.php` extending `DomainException`, with a static `forCode(string $rawInput, string $normalised): self` factory that includes both forms in the message (FR-007)
- [X] T022 [P] Create `app/Services/Currency/Exceptions/InvalidConversionAmountException.php` extending `DomainException`, with static factories `negative(string $raw): self` and `nonNumeric(mixed $raw): self` (FR-023)
- [X] T023 [P] Create `app/Services/Currency/Exceptions/MissingExchangeRateException.php` extending `RuntimeException`, with `forCode(string $code): self` factory
- [X] T024 [P] Create `app/Services/Currency/Exceptions/MalformedRateResponseException.php` extending `RuntimeException` for the freecurrencyapi response-validation path

### Foundation gate

- [X] T025 Run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact tests/Feature/Seeders/AdminUserSeederTest.php` and confirm green

**Checkpoint**: Schema applied, entities + factories + seeders + exceptions ready. User story
work can now begin.

---

## Phase 3: User Story 1 — Convert a price (Priority: P1) 🎯 MVP

**Goal**: A consumer (developer or operator) can convert a monetary amount between two
supported currencies using stored rates, via either `$converter->convert(...)` from
application code or `php artisan currency:convert {amount} {from} {to}` from the CLI.

**Independent Test**: Seed `exchange_rates` with known fixed values (factory in T014),
invoke `CurrencyConverter::convert(100, 'USD', 'RUB')` and `php artisan currency:convert 100 USD RUB`,
assert the rounded 2-decimal-place string matches the expected `bcmath` calculation. All ten
CC-01..CC-10 test cases from [contracts/currency-converter.md](contracts/currency-converter.md)
pass.

### Tests for User Story 1 (test-first) ⚠️

- [X] T026 [P] [US1] Create failing unit test `tests/Unit/Services/CurrencyConverterTest.php` covering CC-01..CC-10 from [contracts/currency-converter.md](contracts/currency-converter.md): happy-path conversion, same-currency short-circuit (FR-006), case/whitespace normalisation (FR-024), zero amount returns `"0.00"`, negative amount throws `InvalidConversionAmountException` (FR-023), unsupported `from`/`to` throws `UnsupportedCurrencyException` (FR-007), cross-currency-through-base math, high-precision determinism, and missing rate throws `MissingExchangeRateException`. Use `RefreshDatabase` trait and seed rates via `ExchangeRate::factory()`
- [X] T027 [P] [US1] Create failing feature test `tests/Feature/Console/ConvertCurrencyCommandTest.php` covering: success line format (`USD 100 -> RUB 9140.00`) with exit code 0; non-numeric/negative input prints `error: …` to stderr with exit code 1; unsupported currency exits 2; missing rate exits 2

### Implementation for User Story 1

- [X] T028 [US1] Create `app/Services/Currency/ExchangeRateRepository.php` with `declare(strict_types=1)`, constructor DI of nothing (uses Eloquent), and methods `findRate(string $baseCode, string $targetCode): string` (throws `MissingExchangeRateException` when no row), `requireCurrency(string $code): Currency` (throws `UnsupportedCurrencyException`). Returns rate as a string for bcmath safety
- [X] T029 [US1] Create `app/Services/Currency/CurrencyConverter.php` with `declare(strict_types=1)`, `final` class, constructor-promoted private readonly `ExchangeRateRepository $rates`, and the `convert(int|float|string $amount, string $from, string $to): string` method implementing the contract in [contracts/currency-converter.md](contracts/currency-converter.md): normalise inputs, validate amount, short-circuit identical codes, look up the rate via the repository, apply `bcmath` at scale 10 with cross-currency-through-base chaining, round half-up to 2dp, return as numeric string. Add Russian PHPDoc on the public method per Constitution Principle VI
- [X] T030 [US1] Generate command via `php artisan make:command ConvertCurrency --no-interaction`; in `app/Console/Commands/ConvertCurrency.php` set signature `currency:convert {amount} {from} {to}`, constructor-inject `CurrencyConverter`, implement per research Decision 11 — print `{FROM} {amount_in} -> {TO} {result}` on success (exit 0), `error: {msg}` to stderr on `InvalidConversionAmountException` (exit 1) or `UnsupportedCurrencyException`/`MissingExchangeRateException` (exit 2)
- [X] T031 [US1] Run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact tests/Unit/Services/CurrencyConverterTest.php tests/Feature/Console/ConvertCurrencyCommandTest.php` and confirm all CC-01..CC-10 plus command tests green

**Checkpoint**: MVP. The conversion service works in-process and from the CLI; rates are
served entirely from SQLite (SC-005). Ship/demo possible here.

---

## Phase 4: User Story 2 — Automatic daily refresh of exchange rates (Priority: P2)

**Goal**: Stored rates are refreshed once per day from freecurrencyapi.com, with bounded
retries on transient failure, atomic locking against concurrent runs, and a Refresh Job Log
entry per attempt.

**Independent Test**: Run `php artisan currency:refresh-rates` with `Http::fake()` returning a
known payload; assert (a) every supported currency in `exchange_rates` is updated, (b) one
`refresh_job_logs` row exists with `status=success`, (c) re-running while the lock is held
exits with `skipped_overlap`, (d) a 5xx fake triggers two retries (30 s, 5 min) and produces a
single `status=failure` row with `attempts=3`.

### Tests for User Story 2 (test-first) ⚠️

- [X] T032 [P] [US2] Create failing feature test `tests/Feature/Services/FreeCurrencyApiClientTest.php` covering: successful payload parsed into a code-keyed array; missing `data` key raises `MalformedRateResponseException`; missing API-key config raises a typed configuration exception; `Http::preventStrayRequests()` enforced
- [X] T033 [P] [US2] Create failing feature test `tests/Feature/Services/RateRefresherTest.php` covering: successful refresh upserts every supported currency and creates a `success` Refresh Job Log row (FR-002, FR-009); provider failure leaves existing rates intact and creates a `failure` row with `attempts=3` (FR-009 / Q5); concurrent invocation while a lock is held creates a `skipped_overlap` row without touching rates (FR-014, FR-015); partial response (missing one currency) leaves all rate rows untouched and produces a `failure` row (no partial writes — SC-006)
- [X] T034 [P] [US2] Create failing feature test `tests/Feature/Console/RefreshCurrencyRatesCommandTest.php` covering: `php artisan currency:refresh-rates` exits 0 on success; non-zero on failure; `triggered_by='manual'` recorded; `triggered_by='scheduler'` when invoked via the Schedule

### Implementation for User Story 2

- [X] T035 [US2] **Per Constitution Principle VII**: open `https://freecurrencyapi.com/docs` and tick every item in [contracts/freecurrencyapi.md](contracts/freecurrencyapi.md) § "Verification checklist", recording confirmations in the PR body. Adjust the contract document and the implementation below if the live docs diverge — documentation wins
- [X] T036 [US2] Create `app/Services/Currency/FreeCurrencyApiClient.php` with `declare(strict_types=1)`, constructor-injected nothing (uses `Http` facade), method `fetchLatest(string $baseCurrency, array $targetCodes): array`. Use `Http::baseUrl(config('currency.freecurrencyapi.base_url'))->retry([30_000, 300_000], throw: false)->timeout(config('currency.freecurrencyapi.timeout_seconds'))->get('/v1/latest', […])`. Throw configuration exception if `config('currency.freecurrencyapi.api_key')` is empty; throw `MalformedRateResponseException` on missing `data` key or non-numeric rate value
- [X] T037 [US2] Create `app/Services/Currency/RateRefresher.php` with `declare(strict_types=1)`, constructor-injected `FreeCurrencyApiClient`. Method `run(string $triggeredBy): RefreshJobLog`: open a Refresh Job Log row with `status=failure`, `attempts=1`; acquire `Cache::lock('currency:refresh', 600)` non-blocking and on failure update row to `skipped_overlap`; call client (which handles retries internally — increment `attempts` from the retry callback); on success run `DB::transaction(fn () => ExchangeRate::upsert(…))` upserting per supported currency, set `status=success`, `currencies_updated=N`, `finished_at=now()`; on failure write `error_summary` + truncated `error_detail`. Release the lock in a `finally`. Add Russian PHPDoc on the public method
- [X] T038 [US2] Generate command via `php artisan make:command RefreshCurrencyRates --no-interaction`; in `app/Console/Commands/RefreshCurrencyRates.php` set signature `currency:refresh-rates`, constructor-inject `RateRefresher`, call `$refresher->run('manual')`, exit non-zero on `failure`/`skipped_overlap`. Override `isolatableId()` to a fixed string so concurrent CLI invocations also skip cleanly
- [X] T039 [US2] Register the scheduler entry in `routes/console.php`: `Schedule::command('currency:refresh-rates')->dailyAt('03:15')->timezone(config('app.timezone'))->withoutOverlapping(30)` per research Decision 1. Adjust the command's invocation to pass `--triggered-by=scheduler` (extend the signature with this option) so `RateRefresher::run(...)` records the source correctly
- [X] T040 [US2] Run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact tests/Feature/Services/FreeCurrencyApiClientTest.php tests/Feature/Services/RateRefresherTest.php tests/Feature/Console/RefreshCurrencyRatesCommandTest.php` and confirm green

**Checkpoint**: Daily refresh works end-to-end against `Http::fake()`. Manual trigger via
Artisan works. Concurrency-safe.

---

## Phase 5: User Story 3 — Administrator views stored exchange rates (Priority: P3)

**Goal**: An authenticated administrator opens `/admin/rates` and sees every stored exchange
rate with its metadata. Includes the shared admin authentication scaffold reused by US4.

**Independent Test**: Seed rates, sign in as `admin`/`Aqaz`, GET `/admin/rates`, assert HTTP
200, see every rate row with `code`, `rate`, `fetched_at`. Anonymous → 302 to login.
Authenticated non-admin (no admin user yet, but middleware logic) → 403. Default-password
banner visible when `APP_ENV != local`.

### Tests for User Story 3 (test-first) ⚠️

- [X] T041 [P] [US3] Create failing feature test `tests/Feature/Admin/LoginTest.php` covering: GET `/admin/login` returns 200 with CSRF-protected form; valid `admin`/`Aqaz` POST redirects to intended URL and authenticates the session; wrong password returns 422/redirects with `errors.username`; logout invalidates the session
- [X] T042 [P] [US3] Create failing feature test `tests/Feature/Admin/EnsureUserIsAdminMiddlewareTest.php` covering: anonymous request to a guarded route redirects to `admin.login`; authenticated user with `username != 'admin'` gets 403; authenticated user with `username = 'admin'` passes through
- [X] T043 [P] [US3] Create failing feature test `tests/Feature/Admin/DefaultCredentialBannerTest.php` covering: when `app.env=production` AND admin still has `Aqaz` hash → banner partial renders on `/admin/rates`; when `app.env=local` → banner is absent (FR-022)
- [X] T044 [P] [US3] Create failing feature test `tests/Feature/Admin/CurrencyRatesPageTest.php` covering Story 3 acceptance scenarios: empty state when no rates seeded; populated state lists every stored rate with `code`/`rate`/`fetched_at`; anonymous → redirect; non-admin → 403; page render uses Blade (assert response is HTML, not JSON) and contains a `<table>` (FR-018, FR-019)

### Admin auth scaffold (shared with US4)

- [X] T045 [US3] Create `app/Http/Requests/Admin/LoginRequest.php` via `php artisan make:request Admin/LoginRequest --no-interaction` with rules `username => ['required','string','max:32']`, `password => ['required','string']`; authorize() returns `true`
- [X] T046 [US3] Generate controller via `php artisan make:controller Admin/LoginController --no-interaction`; in `app/Http/Controllers/Admin/LoginController.php` implement `showLoginForm()` (returns `view('admin.login')`), `login(LoginRequest $request)` (calls `Auth::attempt(['username' => …, 'password' => …])`, on success regenerates session and redirects to `admin.rates.index`, on failure throws `ValidationException`), and `logout(Request)` (calls `Auth::logout()`, invalidates session, redirects to login)
- [X] T047 [US3] Generate middleware via `php artisan make:middleware EnsureUserIsAdmin --no-interaction`; in `app/Http/Middleware/EnsureUserIsAdmin.php` redirect anonymous → `route('admin.login')`, abort 403 when `$request->user()->username !== config('currency.admin.username')`. Register the middleware alias in `bootstrap/app.php` as `admin`
- [X] T048 [US3] Create `app/Services/Currency/DefaultCredentialWatcher.php` with `declare(strict_types=1)`, method `usingDocumentedDefault(): bool` that lazy-caches a single `Hash::check('Aqaz', $adminUser->password)` per request; method `shouldWarn(): bool` returning true when `app()->environment() !== 'local'` AND `usingDocumentedDefault()` is true (FR-022)
- [X] T049 [US3] Update `app/Providers/AppServiceProvider.php` boot() to call `Log::warning(...)` when `DefaultCredentialWatcher::shouldWarn()` returns true on application boot (rate-limited via a static guard so it logs at most once per process)
- [X] T050 [US3] Create `resources/views/admin/layout.blade.php` — minimal Blade layout including `<head>` with Vite CSS asset (Tailwind 4 already installed), `@yield('content')`, and an `@include('admin._default-credential-warning')` partial above the content area
- [X] T051 [US3] Create `resources/views/admin/_default-credential-warning.blade.php` rendering a red banner `<div role="alert">` when `app(DefaultCredentialWatcher::class)->shouldWarn()` is true; empty otherwise
- [X] T052 [US3] Create `resources/views/admin/login.blade.php` extending `admin.layout`, with a `<form method="POST" action="{{ route('admin.login.attempt') }}">` containing `@csrf`, `username` and `password` inputs, and error display via `$errors`

### US3-specific implementation

- [X] T053 [US3] Generate controller via `php artisan make:controller Admin/CurrencyRatesController --no-interaction`; in `app/Http/Controllers/Admin/CurrencyRatesController.php` implement `index()` that loads `ExchangeRate::with('targetCurrency')->orderBy('base_code')->orderBy('target_currency_id')->get()` plus the latest successful `RefreshJobLog`, and returns `view('admin.rates', [...])`
- [X] T054 [US3] Create `resources/views/admin/rates.blade.php` extending `admin.layout`, rendering a `<table>` with columns Source, Target, Rate, Fetched-at (formatted via `->toIso8601String()` or Laravel's `->format(...)`), plus an empty-state `<p>` when the collection is empty
- [X] T055 [US3] Add named admin routes in `routes/web.php`: `Route::prefix('admin')->name('admin.')->group(function () { Route::get('login', [LoginController::class, 'showLoginForm'])->name('login'); Route::post('login', [LoginController::class, 'login'])->name('login.attempt'); Route::post('logout', [LoginController::class, 'logout'])->middleware('admin')->name('logout'); Route::middleware('admin')->group(function () { Route::get('rates', [CurrencyRatesController::class, 'index'])->name('rates.index'); }); });`
- [X] T056 [US3] Run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact tests/Feature/Admin/LoginTest.php tests/Feature/Admin/EnsureUserIsAdminMiddlewareTest.php tests/Feature/Admin/DefaultCredentialBannerTest.php tests/Feature/Admin/CurrencyRatesPageTest.php` and confirm green; use Boost `get-absolute-url` to resolve `/admin/rates` and smoke-test in the browser

**Checkpoint**: Admin can log in and see the Currency Rates page. Auth scaffold ready for US4.

---

## Phase 6: User Story 4 — Administrator views available currencies (Priority: P3)

**Goal**: An authenticated administrator opens `/admin/currencies` and sees every supported
currency with its ISO code, display name, and enabled status.

**Dependency**: T045–T052 from US3 (admin auth scaffold). The rates-page tasks (T053–T056) are
*not* required by US4 — US3 and US4 can be implemented in either order once the auth scaffold
exists.

**Independent Test**: Seed currencies (already done in foundational T020), sign in as admin,
GET `/admin/currencies`, assert HTTP 200, see every supported currency with code, name, and
enabled indicator. Empty currencies → empty-state message. Anonymous → 302. Non-admin → 403.

### Tests for User Story 4 (test-first) ⚠️

- [X] T057 [P] [US4] Create failing feature test `tests/Feature/Admin/AvailableCurrenciesPageTest.php` covering Story 4 acceptance scenarios: populated state lists every currency with `code`/`name`/`is_enabled` indicator; empty state when no currencies seeded; anonymous → redirect; non-admin → 403; rendered as Blade HTML (FR-016, FR-018, FR-019)

### Implementation for User Story 4

- [X] T058 [US4] Generate controller via `php artisan make:controller Admin/AvailableCurrenciesController --no-interaction`; in `app/Http/Controllers/Admin/AvailableCurrenciesController.php` implement `index()` loading `Currency::orderBy('code')->get()` and returning `view('admin.currencies', [...])`
- [X] T059 [US4] Create `resources/views/admin/currencies.blade.php` extending `admin.layout`, rendering a `<table>` with columns Code, Name, Enabled (rendered as `Yes`/`No`), plus an empty-state `<p>` referencing the seeder path when the collection is empty
- [X] T060 [US4] Add to the admin route group in `routes/web.php`: `Route::get('currencies', [AvailableCurrenciesController::class, 'index'])->name('currencies.index');`
- [X] T061 [US4] Run `vendor/bin/pint --dirty --format agent`; run `php artisan test --compact tests/Feature/Admin/AvailableCurrenciesPageTest.php` and confirm green; smoke-test the page in the browser via Boost `get-absolute-url`

**Checkpoint**: All four user stories implemented and independently testable.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Suite-wide validation and Constitution gates before merge.

- [X] T062 [P] Run full PHPUnit suite: `php artisan test --compact` — entire feature green plus the existing skeleton `ExampleTest`s
- [X] T063 [P] Run `vendor/bin/phpstan analyse --memory-limit=512M` (LaraStan at level 6 from T001 / `phpstan.neon`); fix any reported issues without lowering the level — blocked on T001 approval
- [X] T064 [P] Run `vendor/bin/pint --dirty --format agent` and confirm no formatting drift
- [X] T065 Verify SC-007 via `git grep -F "$FREECURRENCYAPI_KEY"` (or `git grep` against the real key in your `.env`) returns zero matches in tracked files
- [X] T066 Walk through [quickstart.md](quickstart.md) end-to-end on a clean clone: setup → migrate → seed → `php artisan currency:convert 100 USD RUB` → admin login → both admin pages render
- [X] T067 Write PR description citing the specific freecurrencyapi.com documentation sections used (Principle VII): endpoint path, query parameters, auth placement, response shape, error envelope, rate-limit headers
- [X] T068 Open the PR with the title `feat(001-currency-converter): currency storage and conversion module` and link to [spec.md](spec.md), [plan.md](plan.md), and [tasks.md](tasks.md) in the body

---

## Dependencies & Execution Order

### Phase dependencies

- **Phase 1 (Setup)** — No dependencies; start immediately.
- **Phase 2 (Foundational)** — Depends on Phase 1 (`config/currency.php` is read by seeders).
  Blocks every user-story phase.
- **Phase 3 (US1)** — Depends on Phase 2. No dependency on US2/US3/US4. MVP scope.
- **Phase 4 (US2)** — Depends on Phase 2. Independent of US1 (the refresh path does not call
  the converter), but in practice US1 ships first so the seeded rates the operator inspects
  match how the converter will read them.
- **Phase 5 (US3)** — Depends on Phase 2. Contains the admin auth scaffold (T045–T052) that
  US4 reuses. The rates-page tasks (T053–T056) themselves don't block US4.
- **Phase 6 (US4)** — Depends on Phase 2 + T045–T052 from Phase 5 (auth scaffold). Does not
  depend on T053–T056.
- **Phase 7 (Polish)** — Depends on whichever user stories are in scope for the release
  (minimum US1 for MVP; all four for the full feature).

### User-story dependencies

- **US1 (P1)** — Standalone after Phase 2.
- **US2 (P2)** — Standalone after Phase 2.
- **US3 (P3)** — Standalone after Phase 2; introduces the shared auth scaffold.
- **US4 (P3)** — Depends on US3's auth scaffold tasks (T045–T052). Otherwise standalone.

### Within each user story

- Failing tests are written **first** for every behaviour-bearing implementation (TDD per
  Principle I — NON-NEGOTIABLE).
- Models → repositories/services → controllers/commands → routes/views.
- Pint + targeted test run at the end of each story phase.

### Parallel opportunities

- All of T002–T003 (Setup) — different files.
- All of T004–T007 (migrations), T009–T015 (models/factories), T018 (CurrencySeeder),
  T021–T024 (exception classes) within Phase 2 — different files.
- T016 (AdminUserSeederTest) is parallel to all other Phase 2 [P] tasks but blocks T017.
- T026 and T027 (US1 tests) — different files; both run before T028–T030.
- T032, T033, T034 (US2 tests) — different files.
- T041, T042, T043, T044 (US3 tests) — different files; T044 logically depends on the rates
  page existing, but the test file itself can be authored in parallel and will fail until
  T053–T055 land (standard red-green TDD).
- T062, T063, T064 (Polish) — three independent commands.

---

## Parallel Example: User Story 1

```bash
# After Phase 2 completes, kick off the US1 failing tests together:
Task: "Write CurrencyConverterTest CC-01..CC-10 in tests/Unit/Services/CurrencyConverterTest.php"
Task: "Write ConvertCurrencyCommandTest in tests/Feature/Console/ConvertCurrencyCommandTest.php"

# Then implementation sequentially (each depends on the prior):
Task: "Implement ExchangeRateRepository in app/Services/Currency/ExchangeRateRepository.php"
Task: "Implement CurrencyConverter in app/Services/Currency/CurrencyConverter.php"
Task: "Generate and implement ConvertCurrency command in app/Console/Commands/ConvertCurrency.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 → Phase 2 → Phase 3.
2. STOP and validate: `php artisan currency:convert 100 USD RUB` returns the expected string;
   `CurrencyConverterTest` is green.
3. Ship/demo. Rates are static at this point (seeded once); refresh is a P2 enhancement.

### Incremental delivery

1. MVP (US1) → demo.
2. Add US2 (Phase 4) → rates now refresh daily; demo `php artisan currency:refresh-rates`.
3. Add US3 (Phase 5) → admin can inspect the rate store visually; demo `/admin/rates`.
4. Add US4 (Phase 6) → admin can inspect the supported list; demo `/admin/currencies`.
5. Phase 7 polish → open PR.

### Single-developer order (this project)

Sequential: Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7.
Total tasks: **68**.

---

## Notes

- Every `php artisan make:` invocation must pass `--no-interaction` per CLAUDE.md.
- Every new PHP file must begin with `declare(strict_types=1)` and use constructor DI
  (Principle VI).
- Public service methods (`CurrencyConverter::convert`, `RateRefresher::run`,
  `FreeCurrencyApiClient::fetchLatest`) need Russian PHPDoc per Principle VI.
- Run Pint on each completed PHP edit batch before moving to the next task.
- Use Boost `database-schema`/`database-query` to inspect SQLite state, not raw SQL via
  tinker (Principle IV).
- Use Boost `get-absolute-url` whenever sharing or following a project URL.
- No third-party admin scaffolding (Filament/Nova/Breeze) at any point — Blade only,
  hand-rolled minimal auth (FR-018, FR-019, plan Decision 6).
- No `everapihq/freecurrencyapi-php` or any vendor SDK — only `Http` facade (FR-003).
- The freecurrencyapi vendor-doc re-verification (T035) is a hard gate before T036/T037 are
  committed (Principle VII).
- Commit boundaries: one commit per task is ideal; a logical group is acceptable. Never
  squash a failing-test commit into its implementation commit — the red-then-green history
  is the evidence that TDD was followed.
