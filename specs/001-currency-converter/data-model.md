# Phase 1 Data Model: Currency Storage and Conversion Module

**Branch**: `001-currency-converter`
**Date**: 2026-05-15
**Inputs**: [spec.md](spec.md) Key Entities, [research.md](research.md) Decisions 4, 6, 9, 10

All tables persist in the project's SQLite database via Laravel migrations. Column types use
the framework's portable Blueprint API.

---

## Entity: `currencies`

Represents a monetary unit recognised by the module (FR-001, Key Entity *Currency*).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint, auto-inc | PK | Surrogate ID for FK references. |
| `code` | string(3) | NOT NULL, UNIQUE | Uppercase ISO-4217 (e.g. `USD`). Normalised by application before persistence; DB-level CHECK not enforced (SQLite portability). |
| `name` | string(64) | NOT NULL | Human-readable display name (English) — e.g. `"US Dollar"`. |
| `is_enabled` | boolean | NOT NULL, default `true` | Whether the currency participates in conversions and refreshes. Disabling does not delete history. |
| `created_at` | datetime | NOT NULL | `$table->timestamps()`. |
| `updated_at` | datetime | NOT NULL | |

**Indexes**: `UNIQUE(code)` (implicit via `unique()`).

**Validation rules** (enforced at the service/seeder boundary, not the DB):
- `code` matches `/^[A-Z]{3}$/` after normalisation.
- `name` is non-empty after `trim()`.

**Lifecycle**:
- Created exclusively by `CurrencySeeder` (FR-001). The admin **Available Currencies** page is
  read-only; there are no `POST`/`PUT`/`DELETE` routes for currencies in this feature.
- A row may be soft-disabled by setting `is_enabled = false` via a seeder rewrite + re-run.

**Relationships**:
- One-to-many with `exchange_rates` via `target_currency_id`.

---

## Entity: `exchange_rates`

Represents the most recent rate from the configured base currency to a target supported
currency (FR-002, Key Entity *Exchange Rate*).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint, auto-inc | PK | |
| `base_code` | string(3) | NOT NULL | Source currency ISO code. Stored as a flat string (not FK) so historical rows survive currency-list edits. |
| `target_currency_id` | foreignId | NOT NULL, FK → `currencies.id` ON DELETE CASCADE | Target currency in the supported list. |
| `rate` | decimal(20, 10) | NOT NULL | `1 base_code = rate target_currency`. Stored as fixed-precision decimal per research Decision 4. |
| `fetched_at` | datetime | NOT NULL | Timestamp of the upstream response that produced this rate (UTC). Drives "no more than 24 h old" assertions (SC-001). |
| `created_at` | datetime | NOT NULL | |
| `updated_at` | datetime | NOT NULL | |

**Indexes**:
- `UNIQUE(base_code, target_currency_id)` — exactly one current rate per `(base, target)` pair (FR-015 + research Decision 4).

**Validation rules**:
- `base_code` matches `/^[A-Z]{3}$/`.
- `rate > 0`.
- `fetched_at <= now()`.

**Lifecycle**:
- Created/updated only by `RateRefresher` via `ExchangeRate::upsert(...)` inside
  `DB::transaction(...)` (FR-014 / FR-015 / SC-006).
- Deleted only when the corresponding `currencies` row is removed (cascade).

**Relationships**:
- Belongs to one `Currency` (target side).
- `base_code` is stored as a code rather than an FK to preserve historical integrity when
  rows are repointed; the current MVP only writes one base currency (`USD` by default).

---

## Entity: `refresh_job_logs`

Operational record of every refresh attempt (FR-009, FR-014, Key Entity *Refresh Job Log*,
research Decision 9).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint, auto-inc | PK | |
| `started_at` | datetime | NOT NULL, indexed | When the invocation began. |
| `finished_at` | datetime | NULLABLE | NULL while in flight. |
| `status` | string(24) | NOT NULL | One of `success`, `failure`, `skipped_overlap`. |
| `attempts` | tinyint | NOT NULL, default `1` | 1..3, incremented on each retry inside the run. |
| `currencies_updated` | int | NULLABLE | Count of `exchange_rates` rows upserted (NULL when status ≠ `success`). |
| `error_summary` | string(255) | NULLABLE | E.g. `"HTTP 429"`, `"ConnectionException"`. |
| `error_detail` | text | NULLABLE | Body excerpt or stack frame; truncated to 4 KB by the writer. |
| `triggered_by` | string(16) | NOT NULL | `scheduler` or `manual`. |
| `created_at` / `updated_at` | datetime | NOT NULL | |

**Indexes**:
- `INDEX(started_at)` — for "latest run" lookups.
- `INDEX(status, started_at)` — for "latest success" lookups on the admin page.

**Validation rules**:
- `status` ∈ {`success`, `failure`, `skipped_overlap`}.
- `attempts` ∈ [1, 3].
- `triggered_by` ∈ {`scheduler`, `manual`}.

**Lifecycle**:
- Created on invocation start with `status = failure`, `finished_at = NULL`, `attempts = 1`.
  Updated in-place on each retry and again on terminal outcome.
- Never deleted by this feature.

---

## Entity: `users` (modification of existing table)

The skeleton `users` table is extended with a single column to support FR-020's
`admin`-username login (research Decision 6).

| Column added | Type | Constraints | Notes |
|---|---|---|---|
| `username` | string(32) | NULLABLE, UNIQUE | Lowercase ASCII. NULL for users created by other features (which do not exist yet). |

Existing columns (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`,
timestamps) are unchanged. The Key Entity *Admin User* (spec.md) maps to a single row in this
table where `username = 'admin'`.

**Validation rules** (application side):
- `username` matches `/^[a-z][a-z0-9_-]{0,31}$/`.
- `password` stored as the `bcrypt` hash produced by `Hash::make()` (FR-021).

**Lifecycle**:
- The admin row is created idempotently by `AdminUserSeeder` (FR-020 / SC-009 / SC-010): on
  re-run the seeder checks for a row with the configured username and skips when present,
  never overwriting the password.

---

## Cross-entity invariants

| Invariant | Enforced by | Verified by |
|---|---|---|
| Exactly one current rate per (base, target). | `UNIQUE(base_code, target_currency_id)` index + `upsert()` on refresh. | `RateRefresherTest`. |
| No `exchange_rates` row references a missing currency. | FK with `ON DELETE CASCADE`. | Migration test. |
| Negative or zero amount handling matches FR-023. | `CurrencyConverter::convert(...)` boundary check. | `CurrencyConverterTest`. |
| Unsupported currency code raises `UnsupportedCurrencyException`. | `CurrencyConverter` lookup. | `CurrencyConverterTest`. |
| `refresh_job_logs.finished_at IS NOT NULL` once the row is terminal. | `RateRefresher` write order. | `RateRefresherTest`. |
| Admin seed runs are idempotent (SC-009). | `AdminUserSeeder` existence check. | `AdminUserSeederTest`. |
| The literal `Aqaz` is never persisted in the `users` table (SC-010). | `Hash::make()` before insert. | `AdminUserSeederTest` (queries `users.password`, asserts `Hash::check('Aqaz', $row->password)` and that `$row->password !== 'Aqaz'`). |

---

## Migration order

1. `…_add_username_to_users_table.php` — adds `username` column.
2. `…_create_currencies_table.php`.
3. `…_create_exchange_rates_table.php` (references `currencies.id`).
4. `…_create_refresh_job_logs_table.php`.

Migration file names use the timestamp prefix produced by `php artisan make:migration`.
