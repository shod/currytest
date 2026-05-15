# Feature Specification: Currency Storage and Conversion Module

**Feature Branch**: `001-currency-converter`

**Created**: 2026-05-15

**Status**: Draft

**Input**: User description: "Create a module for storing and converting currencies. The module must have a predefined list of currencies (at the discretion of the developer - hardcoded in the module or added via the admin panel). Exchange rates should be downloaded from https://freecurrencyapi.com/ for all available currencies and stored in the database. Rates should be updated once a day. The module should provide a service for converting prices from one currency to another (using something like this $converter->convert(123, 'USD', 'RUB')). Also, a page in the admin panel should be created, where all saved exchange rates should be displayed. Libraries implementing integration with freecurrencyapi.com shouldn't be used. Integration should be implemented with Guzzle, curl, file_get_contents or any other tool aimed to make http requests or network requests."

**Amendments (2026-05-15)**:

1. Persistence uses **SQLite** as the project database.
2. Add an admin panel page that displays the list of available (supported) currencies.
3. Retain an admin panel page that displays stored currency exchange rates (already covered by Story 3).
4. The freecurrencyapi.com API key MUST be stored in `.env` and consumed by the application via a dedicated configuration file (e.g. `config/services.php` or a feature-specific config file) — code MUST NOT read `getenv()`/`$_ENV` directly.

**Amendments — Round 2 (2026-05-15)**:

5. The admin panel for this feature is **deliberately minimal for MVP**: no third-party admin scaffolding, no client-side framework, no advanced filtering/sorting/pagination beyond what the listed data volumes require.
6. A **seed admin user** MUST be created with default credentials `admin` / `Aqaz`. These are **MVP defaults only** and MUST be overridable through environment configuration; production deployments MUST change them before the application is exposed beyond local development.
7. Admin panel pages MUST be rendered with **Blade** (the framework's native server-side templating). No SPA / JS-framework dependency is introduced.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Convert a price between two currencies (Priority: P1)

A developer (or any consumer of the module) needs to convert a monetary amount from one currency
into another using the most recent stored exchange rates, so that prices displayed across the
application can be presented in the user's preferred currency without re-fetching live rates on
every request.

**Why this priority**: This is the core value proposition of the module. Without conversion, the
stored rates have no practical use. Every other story (admin viewing, scheduled refresh) exists to
make this conversion accurate and reliable.

**Independent Test**: Can be fully tested by seeding the database with a known set of exchange
rates and invoking the conversion service for various source/target currency pairs and amounts.
The returned converted amount must equal the expected mathematical result within a documented
precision tolerance.

**Acceptance Scenarios**:

1. **Given** exchange rates are present for USD and RUB, **When** a caller requests conversion of
   `100` from `USD` to `RUB`, **Then** the service returns the amount in RUB calculated from the
   currently stored rates.
2. **Given** the source and target currencies are identical, **When** a caller requests conversion
   of any amount from `USD` to `USD`, **Then** the service returns the original amount unchanged.
3. **Given** a requested currency code is not present in the stored list of supported currencies,
   **When** a caller requests conversion involving that code, **Then** the service rejects the
   request with a clear, actionable error indicating the unsupported currency.
4. **Given** a requested amount is negative or zero, **When** a caller requests conversion,
   **Then** the service handles the value according to a documented rule (zero returns zero;
   negative values are either rejected with a clear error or converted preserving sign — the
   chosen behaviour is documented and consistently applied).

---

### User Story 2 - Automatic daily refresh of exchange rates (Priority: P2)

The system must keep stored exchange rates fresh by retrieving the latest values from the
external rate provider (freecurrencyapi.com) once per day, so that conversions performed by the
module always reflect rates that are no more than 24 hours old.

**Why this priority**: Without scheduled refresh, the conversion service will quickly serve stale
rates and lose user trust. However, the conversion service (Story 1) can operate against any
seeded data, so this story can be delivered after the MVP conversion path is proven.

**Independent Test**: Can be fully tested by triggering the refresh routine (manually or via the
scheduler) and verifying that (a) the external provider is contacted, (b) returned rates for every
supported currency are persisted, and (c) subsequent conversions use the newly stored values.

**Acceptance Scenarios**:

1. **Given** the system has not refreshed rates today, **When** the daily refresh runs, **Then**
   the system retrieves the latest rates for every supported currency and stores them, replacing
   or superseding the previous values.
2. **Given** the external rate provider is unreachable or returns an error, **When** the daily
   refresh runs, **Then** previously stored rates remain intact, the failure is logged with enough
   context to diagnose, and a retry is scheduled or the next daily run resumes the cycle.
3. **Given** the external provider returns a rate for a currency not in the supported list,
   **When** the refresh persists data, **Then** the unsupported currency is ignored (or stored
   according to a documented policy) without breaking the run.
4. **Given** the refresh has already succeeded today, **When** the refresh is triggered again
   on the same day, **Then** the system avoids unnecessary external requests (idempotent within a
   24-hour window) unless an explicit force-refresh is requested.

---

### User Story 3 - Administrator views stored exchange rates (Priority: P3)

An administrator opens the **Currency Rates** page in the admin panel, which lists every
exchange rate currently stored in the database, so that they can verify which rates are in use,
when they were last refreshed, and detect anomalies without querying the database directly.

**Why this priority**: This story increases operational confidence and visibility but is not
required for the conversion service or the daily refresh to function. It is delivered after the
core conversion and refresh slices because it consumes data produced by them.

**Independent Test**: Can be fully tested by seeding the database with rates and visiting the
admin panel **Currency Rates** page; the page must render every stored rate together with its
associated metadata (source currency, target currency, value, last refresh timestamp).

**Acceptance Scenarios**:

1. **Given** the database contains exchange rates, **When** an authenticated administrator opens
   the admin panel **Currency Rates** page, **Then** every stored rate is listed with its source
   currency, target currency, current value, and the timestamp of the last refresh.
2. **Given** no rates have been stored yet, **When** an administrator opens the page, **Then** an
   informative empty state is displayed (rather than an error) telling them that no rates have
   been retrieved yet.
3. **Given** a non-administrator user attempts to open the page, **When** the request is
   processed, **Then** access is denied per the existing admin panel authorisation rules.

---

### User Story 4 - Administrator views available currencies (Priority: P3)

An administrator opens the **Available Currencies** page in the admin panel, which lists every
currency the module recognises as supported, so that they can confirm at a glance which currency
codes can participate in conversions and refresh operations.

**Why this priority**: This story increases operational visibility into the configured currency
list and complements Story 3, but the module remains functional without it. It can be delivered
independently of Stories 1–3 because its sole data source is the supported currency list
(FR-001), not the exchange-rate store.

**Independent Test**: Can be fully tested by configuring the supported currency list (in code,
seed data, or via the admin panel — per FR-001) and visiting the admin panel **Available
Currencies** page; the page must render every supported currency together with its display
metadata (ISO code, human-readable name, enabled flag).

**Acceptance Scenarios**:

1. **Given** the module has a non-empty list of supported currencies, **When** an authenticated
   administrator opens the admin panel **Available Currencies** page, **Then** every supported
   currency is listed with its ISO code, human-readable name, and an indicator of whether it is
   currently enabled.
2. **Given** the supported currency list is empty (a misconfiguration), **When** an administrator
   opens the page, **Then** an informative empty state is displayed (rather than an error)
   telling them no currencies are configured and what to do next.
3. **Given** a non-administrator user attempts to open the page, **When** the request is
   processed, **Then** access is denied per the existing admin panel authorisation rules.

---

### Edge Cases

- **External API failure**: Provider returns HTTP errors, timeouts, malformed JSON, or rate
  payloads missing certain currencies. The system must not corrupt existing stored rates.
- **Provider rate-limit / quota exceeded**: The system must record the failure, surface it for
  operations, and not retry aggressively in a way that worsens the situation.
- **Clock drift / time zone mismatch**: "Once per day" must be interpreted against a clearly
  defined time zone (system default) so that refresh cadence is predictable.
- **Currency code casing and validation**: Convert is invoked with lower-case, mixed-case, or
  whitespace-padded ISO codes. The service must either normalise input or reject it with a clear
  error consistently.
- **Conversion through base currency**: When the provider only returns rates relative to a base
  currency (e.g. USD), conversions between two non-base currencies must be computed via the base
  with documented precision rules.
- **Rounding & precision**: Multiplying floating-point amounts by floating-point rates can lose
  precision. The system must document and apply a consistent rounding/precision strategy for
  monetary results.
- **Concurrent refresh executions**: If two refresh attempts run simultaneously (e.g. manual
  trigger overlapping with the scheduled run), the system must prevent duplicate writes or
  partial updates.
- **Newly added supported currency**: Adding a currency to the supported list must not require
  manual back-fill; the next refresh must populate its rate automatically.
- **Empty supported currency list**: A misconfiguration that leaves the supported list empty
  must produce a clear empty state on the admin **Available Currencies** page and must cause the
  refresh routine to log a no-op result rather than failing silently.
- **Missing API key configuration**: If the freecurrencyapi.com API key is not present in the
  configuration when the refresh runs, the failure must be reported with an actionable error
  message (referring the operator to the relevant config/env key) and must not corrupt any
  previously stored rates.
- **Repeated seed execution**: Re-running the admin-user seed (for example after a database
  refresh) must be idempotent: if an admin user with the configured login already exists, the
  seed MUST NOT overwrite that user's password, MUST NOT duplicate the row, and MUST log that
  the user already exists. The seed only creates the account when it is absent.
- **Unchanged default credentials in non-development environment**: The system MUST surface a
  clear warning (e.g. log entry on application boot and/or banner on the admin panel) whenever
  the seeded admin user still has the documented MVP default password in a non-development
  environment, so operators cannot ship the default to production unnoticed.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST maintain a predefined list of supported currencies. The list MAY be
  hardcoded in the module or managed through the admin panel; whichever option is chosen MUST be
  documented and consistent.
- **FR-002**: The system MUST persist exchange rates for every supported currency in the
  application database, together with metadata identifying the source currency, target currency,
  rate value, and the timestamp of the most recent refresh.
- **FR-003**: The system MUST retrieve exchange rates from freecurrencyapi.com using a generic
  HTTP/network mechanism (e.g. Guzzle, cURL, file_get_contents) and MUST NOT depend on
  third-party SDKs that wrap that provider's API (such as
  `everapihq/freecurrencyapi-php`).
- **FR-004**: The system MUST automatically refresh stored exchange rates once every 24 hours
  without manual intervention.
- **FR-005**: The system MUST expose a conversion service accepting an amount, a source currency
  code, and a target currency code, and returning the converted amount calculated from the
  currently stored rates (callable as `$converter->convert(123, 'USD', 'RUB')` or equivalent).
- **FR-006**: The conversion service MUST return the original amount unchanged when the source
  and target currency codes are equal.
- **FR-007**: The conversion service MUST reject requests involving unsupported currency codes
  with a clear, actionable error.
- **FR-008**: The system MUST apply a documented, consistent rounding/precision strategy to
  monetary conversion results so that callers receive predictable values.
- **FR-009**: When the external rate provider fails, the system MUST preserve previously stored
  rates, log the failure with sufficient diagnostic context, and arrange for the next scheduled
  attempt to recover automatically.
- **FR-010**: The system MUST provide a **Currency Rates** page in the admin panel that lists
  every stored exchange rate together with its metadata (source currency, target currency, value,
  last refresh timestamp).
- **FR-011**: All admin panel pages introduced by this feature (the **Currency Rates** page and
  the **Available Currencies** page) MUST be accessible only to users authorised to view
  administrative content, per the application's existing admin authorisation rules.
- **FR-012**: The system MUST allow operators to manually trigger an immediate refresh of
  exchange rates (e.g. via a console command) so that issues can be remediated outside the
  scheduled cycle.
- **FR-013**: The system MUST store the freecurrencyapi.com API key in the application's `.env`
  file (never committed to source control) and MUST expose it to application code exclusively
  through a dedicated configuration file (e.g. `config/services.php` or a feature-specific
  config file). Application code MUST resolve the key through the framework configuration helper
  and MUST NOT read environment variables directly (no `getenv()` / `$_ENV` / `env()` calls
  outside the config layer).
- **FR-014**: The system MUST prevent concurrent refresh executions from producing duplicate or
  partial rate writes (for example by using a lock, a single scheduled invocation, or an
  idempotent upsert).
- **FR-015**: The system MUST guarantee that conversions performed at any time use the most
  recent successfully stored rates and never read partially updated data.
- **FR-016**: The system MUST provide an **Available Currencies** page in the admin panel that
  lists every currency in the supported currency list, together with its ISO code, human-readable
  name, and enabled/disabled indicator.
- **FR-017**: The system MUST persist all data introduced by this feature (supported currencies,
  exchange rates, refresh job log) in the project's SQLite database, using the framework's
  standard migration mechanism so that the schema is reproducible across environments.
- **FR-018**: All admin panel pages introduced by this feature MUST be rendered with **Blade**
  (the framework's native server-side templating engine). The feature MUST NOT introduce a
  client-side single-page-application framework or a third-party admin scaffolding package.
- **FR-019**: The admin panel UI for this feature MUST remain intentionally minimal for the
  MVP: pages list data in a simple tabular layout, use standard form controls only, and MUST
  NOT include advanced UX features (faceted filtering, column sorting, search-as-you-type,
  pagination, etc.) unless data volumes for the supported currency list or the rate store make
  such features necessary to meet the Success Criteria.
- **FR-020**: A database seed MUST create a single administrative user when one is absent,
  using the credentials login `admin` and password `Aqaz` as the **MVP default**. Both values
  MUST be overridable through environment configuration (resolved via the same config-file
  pattern required by FR-013), and the seed MUST NOT alter an existing admin user.
- **FR-021**: The admin user's password MUST be stored using a one-way password hash (never in
  plaintext) so that even the MVP default password cannot be read directly from the database.
- **FR-022**: The system MUST emit a clearly identifiable warning (application log on boot
  and/or visible banner on the admin panel) whenever the seeded admin user still has the
  documented MVP default password while running in any non-`local` / non-development
  environment.

### Key Entities *(include if feature involves data)*

- **Currency**: A monetary unit that the module recognises and can convert between. Attributes
  include the ISO-4217 currency code (e.g. `USD`, `RUB`), a human-readable name (e.g.
  "US Dollar"), and an indicator of whether it is currently enabled for use.
- **Exchange Rate**: A stored numeric ratio describing how much of a target currency one unit of a
  source currency is worth at a given point in time. Attributes include the source currency, the
  target currency, the rate value, and the timestamp at which the rate was last refreshed from
  the external provider.
- **Refresh Job Log** (operational record): Captures each attempt to retrieve rates from the
  external provider, including start/finish timestamps, success/failure status, and any error
  details — used to support FR-009 and FR-014 and to power the admin visibility story.
- **Admin User** (authentication record): The seeded administrative account that authorises
  access to the admin panel pages introduced by this feature. Attributes include a login
  identifier (default `admin`), a one-way password hash (default seeded from `Aqaz`, MVP-only),
  and the role/permission attributes already used by the application's admin authorisation
  rules.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of conversion calls for supported currencies return a result computed from
  rates that are no more than 24 hours old.
- **SC-002**: The scheduled daily refresh successfully updates every supported currency on at
  least 99% of attempts measured over any rolling 30-day window; the remaining 1% must leave
  previously stored rates intact and produce a diagnosable error record.
- **SC-003**: A new developer can integrate the conversion service into another feature using a
  single-line call (e.g. `$converter->convert(123, 'USD', 'RUB')`) without needing to read more
  than one page of documentation.
- **SC-004**: An administrator can view every stored exchange rate, including its last refresh
  timestamp, within 5 seconds of opening the admin panel **Currency Rates** page.
- **SC-004a**: An administrator can view every supported currency, including its enabled status,
  within 5 seconds of opening the admin panel **Available Currencies** page.
- **SC-005**: The module performs zero outbound calls to the external rate provider during a
  conversion request (rates are always served from local storage).
- **SC-006**: Manual refresh, scheduled refresh, and conversion all complete deterministically
  even when invoked simultaneously: no test run produces duplicate rate records or partially
  updated state.
- **SC-007**: The freecurrencyapi.com API key never appears in source-controlled files: a
  repository scan (e.g. `git grep` for the live key value) returns zero matches, and the key is
  reachable from application code only via the configuration helper, not via direct environment
  reads.
- **SC-008**: A fresh checkout, followed by running the documented setup commands, results in a
  working admin login (`admin` / `Aqaz`) in under 5 minutes of operator effort; the admin can
  open both admin pages immediately after logging in.
- **SC-009**: Running the admin-user seed two or more times against the same database yields
  exactly one admin user row and never overwrites a password that was changed after the initial
  seed (verifiable via an automated test that runs the seed, changes the password, runs the
  seed again, and asserts the new password is preserved).
- **SC-010**: The admin password is never stored in plaintext: a query of the user table
  returns only one-way hashed values for the password field, and the literal MVP default
  (`Aqaz`) does not appear anywhere in the persisted data.

## Assumptions

- **Supported currency list**: A reasonable default starter set (e.g. USD, EUR, RUB, GBP, plus a
  handful of widely used currencies) is acceptable for the initial release. Maintaining the list
  via configuration (hardcoded constants or seed data) is acceptable; an admin-managed UI for the
  list itself is out of scope unless explicitly requested.
- **Time zone for daily refresh**: The "once per day" cadence is interpreted against the
  application's configured default time zone; no per-user or per-tenant scheduling is required.
- **Base currency**: freecurrencyapi.com exposes rates relative to a base currency (typically
  USD). Conversions between two non-base currencies are computed by chaining through that base
  using stored rates; precision tolerances are documented when the conversion service is
  implemented.
- **Admin panel**: An admin panel already exists (or is being introduced as part of the broader
  project). This feature contributes two new pages to it (**Currency Rates** and **Available
  Currencies**) and reuses the existing authentication and authorisation mechanisms; building a
  new admin panel from scratch is out of scope.
- **Persistence**: All data introduced by this feature (supported currencies, exchange rates,
  refresh job log) is stored in the project's **SQLite** database alongside other application
  data. SQLite is mandated by the user; no alternative database engines are supported by this
  feature, and no new datastore is introduced. Schema is delivered via the framework's standard
  migration mechanism so that environments stay reproducible.
- **Locale & formatting**: This module returns numeric converted amounts. Locale-aware currency
  formatting (symbols, decimal separators, thousands separators) is the caller's responsibility
  and is out of scope for this feature.
- **Historical rates**: Only the current/latest rate per currency pair is required. Storing a
  long-term history of past rates is out of scope for the initial release, though the schema
  should not prevent adding history later.
- **API credentials**: A freecurrencyapi.com account and API key are available. The API key is
  placed in the application's `.env` file and surfaced to application code through a dedicated
  configuration file (see FR-013). Obtaining the account and provisioning the key are
  operational prerequisites, not part of this feature.
- **Admin panel UI scope**: The admin panel for this feature is deliberately minimal for the
  MVP. Pages render with Blade (the framework's native templating engine), use the application's
  existing layout/styling where one exists, and avoid SPA/JS frameworks, third-party admin
  scaffolding (e.g. Filament, Nova), and advanced UX features (faceted filtering, column
  sorting, search, pagination) unless the data volumes for the supported currency list or the
  rate store make them necessary to meet the Success Criteria. Richer admin UX is out of scope
  for the MVP and may be revisited in a follow-up feature.
- **Seed admin credentials**: The seeded admin user is created with login `admin` and password
  `Aqaz` **only when the seed runs and no admin user already exists**. These values are MVP
  defaults intended for local development; both MUST be overridable through environment
  configuration. The password is stored as a one-way hash (FR-021), never in plaintext. Any
  non-development environment still using the documented default MUST emit the warning required
  by FR-022. Rotating or removing the default credentials before exposing the application
  beyond local development is an operational prerequisite, not part of this feature.
