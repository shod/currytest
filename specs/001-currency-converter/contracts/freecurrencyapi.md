# Contract: freecurrencyapi.com (external)

**Scope**: External-provider HTTP contract consumed by
`App\Services\Currency\FreeCurrencyApiClient`. This document is the *planning-time* shape we
design against. Per **Constitution Principle VII (Documentation-First Integration)**, the
authoritative source is the vendor's published documentation at
**https://freecurrencyapi.com/docs** — the implementation task MUST re-verify this contract
against the live docs *before* HTTP code is committed, cite the relevant section in the PR
description, and treat any divergence as a documentation-wins correction.

---

## Endpoint (planning assumption)

```
GET https://api.freecurrencyapi.com/v1/latest
```

| Query parameter | Required | Source | Notes |
|---|---|---|---|
| `apikey` | yes | `config('currency.freecurrencyapi.api_key')` | Resolved via `config()` — never via `env()` outside the config file (FR-013, SC-007). |
| `base_currency` | no | `config('currency.freecurrencyapi.base_currency')` | Defaults to `USD`. |
| `currencies` | yes | Comma-separated list of supported codes from `Currency::where('is_enabled', true)->pluck('code')` | Excludes the base currency. |

The implementation MUST confirm during the verification step whether the API key is accepted
as a query parameter, an HTTP header (`apikey: ...`), or both. The implementation also MUST
confirm any rate-limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`,
`X-RateLimit-Reset` — names are illustrative) and surface them to the Refresh Job Log.

---

## Response (planning assumption)

```json
{
  "data": {
    "EUR": 0.92345,
    "RUB": 91.4123,
    "GBP": 0.78921
  }
}
```

- `data` is an object keyed by ISO-4217 code with a numeric rate value (rate is `1 base_currency = value target_currency`).
- Codes not requested may or may not appear; the implementation MUST treat any code outside
  the supported list as ignored (FR-002 / Story 2 scenario 3).
- The base currency itself is not included in `data` and does not need a self-rate row.

### Error envelope (planning assumption)

```json
{ "errors": { "<field>": ["<message>"] } }
```

The implementation MUST confirm the precise error envelope shape, the use of HTTP status codes
(401 for bad key, 422 for invalid request, 429 for rate-limit), and any vendor-specific
quota-exhausted body fields.

---

## Client-side behaviour requirements

These are independent of the exact wire shape and are enforced by `FreeCurrencyApiClient`
regardless of upstream details:

1. **Authentication via config** — The API key is read only from `config('currency.freecurrencyapi.api_key')`.
   The client MUST throw a typed configuration exception if the key is missing or empty, with
   a message pointing operators at the `.env` key and `config/currency.php` (FR-013, edge
   case "Missing API key configuration").
2. **No vendor SDK** — Only `Illuminate\Support\Facades\Http` (Guzzle-backed) is permitted
   (FR-003). No third-party `freecurrencyapi` or `everapihq/*` package may be added.
3. **Bounded retries** — `Http::retry([30_000, 300_000], throw: false)` applied per request,
   matching the FR-009 / Q5 retry policy. Retries do **not** cascade across attempts handled
   by the calling `RateRefresher`.
4. **Timeouts** — `timeout(config('currency.freecurrencyapi.timeout_seconds'))`; default 10 s.
5. **Faking in tests** — Every test that exercises a code path involving the client uses
   `Http::fake(...)` (no real network access). The client MUST resolve through the container
   so `Http::preventStrayRequests()` can be asserted in feature tests.
6. **Response parsing safety** — Missing `data` key, non-numeric rate values, and unexpected
   types raise a typed `MalformedRateResponseException` rather than producing partial writes;
   the caller `RateRefresher` translates this into a `refresh_job_logs` failure row without
   touching `exchange_rates` (FR-009 / FR-015).
7. **No invented identifiers** — Per Principle VII, the client MUST NOT include query
   parameters, headers, or body fields not present in the verified vendor documentation. If a
   capability is needed and not documented, the implementer surfaces the gap rather than
   guessing.

---

## Verification checklist (to be completed during implementation, before merge)

This checklist is the implementation gate for the integration task. The implementer MUST tick
every item by citing the matching section of https://freecurrencyapi.com/docs in the commit
message or PR body.

- [ ] Confirmed exact endpoint path and HTTP method.
- [ ] Confirmed authentication placement (query vs header) and parameter/header name.
- [ ] Confirmed `base_currency` parameter name and acceptable values.
- [ ] Confirmed `currencies` parameter name, separator, and maximum count.
- [ ] Confirmed success-response JSON structure and rate value type.
- [ ] Confirmed error-response JSON structure for 401 / 422 / 429.
- [ ] Confirmed rate-limit headers (names + units) and recorded them in `refresh_job_logs.error_summary` on 429.
- [ ] Confirmed quota/free-tier limits and that the daily refresh cadence stays within them.

Any item that cannot be confirmed from the live documentation is a blocker — surface it
rather than guess.
