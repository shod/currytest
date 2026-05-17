# Contract: `App\Services\Currency\CurrencyConverter`

**Scope**: Public service contract consumed by application code (controllers, jobs, other
features). This is the canonical surface promised by FR-005 / SC-003.

---

## Class

```php
namespace App\Services\Currency;

final class CurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateRepository $rates,
    ) {}

    public function convert(int|float|string $amount, string $from, string $to): string;
}
```

- The class is `final` — extension is not part of the contract.
- The constructor uses promoted properties and constructor DI (Principle VI).
- `ExchangeRateRepository` is an internal collaborator; it is not part of the public contract
  and may be refactored.

---

## Method: `convert(amount, from, to): string`

### Inputs

| Parameter | Type | Required | Description |
|---|---|---|---|
| `$amount` | `int \| float \| string` | yes | Monetary amount in the source currency. Strings must be numeric (`is_numeric()` true) and are preferred for callers that already track precision. Floats are accepted but immediately cast to a `bcmath`-safe string at the service boundary. |
| `$from` | `string` | yes | Source currency code. Trimmed and upper-cased before lookup (FR-024). |
| `$to` | `string` | yes | Target currency code. Trimmed and upper-cased before lookup (FR-024). |

### Output

Returns a numeric string with exactly two decimal places (e.g. `"123.45"`). String return
preserves precision and signals to callers that the value is post-rounding; further arithmetic
should use `bcmath`.

### Behaviour

1. Normalise `$from` and `$to`: `mb_strtoupper(trim($code), 'UTF-8')`. The pre-normalisation
   form is retained for error messages.
2. If `$amount` cannot be cast to a non-negative numeric string, raise
   `InvalidConversionAmountException` (FR-023). Specifically:
   - Non-numeric input → exception with message identifying the offending value.
   - Negative input → exception (FR-023).
   - Zero input → return `"0.00"` immediately without DB access (FR-006 short-circuit applies
     for zero too).
3. If normalised `$from === $to`, return the amount rounded to 2 decimal places half-up
   (FR-006).
4. Look up the rate(s) needed. If either code is not in the supported list, raise
   `UnsupportedCurrencyException` (FR-007). The exception message includes the original
   pre-normalisation input for clarity.
5. Apply the conversion:
   - For `$from === BASE`: `result = amount × rate(BASE → $to)`.
   - For `$to === BASE`:   `result = amount / rate(BASE → $from)`.
   - Otherwise (cross-currency through BASE): `result = amount × rate(BASE → $to) / rate(BASE → $from)`.
   All arithmetic uses `bcmath` at scale 10 (FR-008).
6. Round the final intermediate to 2 decimal places half-up and return as string.

### Error contract

| Condition | Exception | HTTP-style status (if surfaced through controllers) |
|---|---|---|
| Non-numeric or negative amount | `InvalidConversionAmountException extends DomainException` | 422 |
| Unknown / unsupported currency code (either side) | `UnsupportedCurrencyException extends DomainException` | 422 |
| No exchange rate present for a supported currency (refresh has not run yet) | `MissingExchangeRateException extends RuntimeException` | 503 |

Exceptions are framework-agnostic (no Laravel HTTP exceptions), so the service remains
callable from console commands, queues, and tests. The HTTP mapping above is suggested for
future API consumers and is **not** implemented by this feature.

### Determinism guarantees

- Calling `convert(...)` performs **zero** outbound HTTP requests (SC-005). All rate lookups
  go through `ExchangeRateRepository`, which reads exclusively from the local `exchange_rates`
  table.
- For the same `(amount, from, to)` inputs and the same stored rates, the output string is
  byte-identical across runs (SC-006).
- Concurrent calls are safe: the conversion path is read-only.

### Examples

```php
$c->convert(100, 'USD', 'RUB');          // "9140.00"   (assuming rate 91.4)
$c->convert(100, ' usd ', 'rub');        // "9140.00"   (normalisation, FR-024)
$c->convert('123.456', 'EUR', 'EUR');    // "123.46"    (rounding only, FR-006)
$c->convert(0, 'USD', 'RUB');            // "0.00"
$c->convert(-1, 'USD', 'RUB');           // InvalidConversionAmountException (FR-023)
$c->convert(50, 'USD', 'XYZ');           // UnsupportedCurrencyException (FR-007)
```

---

## Test surface required by this contract

The following PHPUnit cases are mandatory for Story 1's Independent Test condition:

| ID | Scenario | Expected |
|---|---|---|
| CC-01 | Convert known pair against seeded rates | Result equals `amount × rate` rounded 2dp half-up |
| CC-02 | Same source and target | Returns `amount` rounded 2dp half-up (FR-006) |
| CC-03 | Lowercase / mixed-case / whitespace input | Same result as canonical form (FR-024) |
| CC-04 | Zero amount | Returns `"0.00"` (FR-023) |
| CC-05 | Negative amount | Throws `InvalidConversionAmountException` (FR-023) |
| CC-06 | Unsupported source currency | Throws `UnsupportedCurrencyException` (FR-007) |
| CC-07 | Unsupported target currency | Throws `UnsupportedCurrencyException` (FR-007) |
| CC-08 | Cross-currency through base | Result matches the documented chained-rate formula |
| CC-09 | High-precision rate, large amount | No float-drift; result matches expected `bcmath` output |
| CC-10 | Missing rate row for a supported currency | Throws `MissingExchangeRateException` |

CC-09 specifically targets the SC-006 determinism guarantee; the test compares the returned
string to a precomputed `bcmath` reference value, not an `assertEquals` against a float.
