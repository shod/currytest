# Specification Quality Checklist: Currency Storage and Conversion Module

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-15
**Last Updated**: 2026-05-15 (after Amendment #2)
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Amendment #1 — 2026-05-15

Four user-driven amendments were folded into the spec:

1. **SQLite is mandated** as the persistence engine for this feature. Captured as a binding
   constraint in the "Persistence" Assumption and in **FR-017**. This is technically an
   implementation detail, but it was explicitly mandated by the user; documenting it here keeps
   the constraint visible to the planning phase rather than discovering it later.
2. **Admin "Available Currencies" page** added as **User Story 4** (P3), with matching **FR-016**
   and **SC-004a**. Sits alongside the existing rates page (Story 3) and reuses the same admin
   authorisation rules (FR-011 generalised).
3. **Admin "Currency Rates" page** retained (Story 3); wording aligned to the new page-naming
   convention.
4. **API key handling** tightened in **FR-013** and the "API credentials" Assumption: key lives
   in `.env`, is exposed via a dedicated config file, and code MUST resolve it through the
   framework configuration helper (no direct `env()`/`getenv()`/`$_ENV` calls outside the config
   layer). Verifiable via **SC-007**.

Cross-references added to support traceability:

- Empty supported-currency list — new edge case + scenario 2 of Story 4.
- Missing API key configuration — new edge case + behavioural requirement folded into FR-009 /
  FR-013.

## Amendment #2 — 2026-05-15

Three further user-driven amendments were folded into the spec:

5. **Admin panel is deliberately minimal for MVP**. Captured as a scope constraint in the new
   "Admin panel UI scope" Assumption and in **FR-019**. No SPA framework, no third-party admin
   scaffolding, no advanced UX features unless data volumes require them.
6. **Seed admin user with default credentials `admin` / `Aqaz`** added as **FR-020**, with
   matching safety requirements: **FR-021** (password stored as a one-way hash, never
   plaintext), **FR-022** (warning when the documented default is still in use in a
   non-development environment), and three Success Criteria (**SC-008** quickstart, **SC-009**
   seed idempotency, **SC-010** no plaintext password storage). A new **Admin User** Key
   Entity records the seeded account. Two new edge cases (repeated seed execution, unchanged
   default credentials in non-development environment) bound the behaviour.
7. **Blade for admin pages** added as **FR-018**, with a matching Assumption clarifying the
   UI scope.

### Security note on `Aqaz`

The literal default password is recorded in the spec because the user explicitly mandated it.
The spec immediately bounds its use:

- It is an **MVP default**, overridable via environment configuration (FR-020, "Seed admin
  credentials" Assumption).
- It is stored only as a one-way hash (FR-021, SC-010); the literal value never lives in the
  database.
- It triggers an explicit warning in any non-development environment (FR-022).
- Rotating the default is called out as an operational prerequisite before exposing the
  application beyond local development.

This is documented intent, not approval of the default for production use.

## Notes

- The spec intentionally retains references to freecurrencyapi.com and example client tooling
  (Guzzle, cURL, file_get_contents) because the user explicitly named both the provider and the
  forbidden/allowed integration mechanisms; these are treated as scope constraints, not
  implementation guidance.
- SQLite, Blade, and the seeded admin credentials are similarly user-mandated constraints and
  are recorded as such in Assumptions/FRs.
- The "config file" requirement for the API key is a user-mandated security/configuration
  pattern (consistent with the Constitution's "Do Things the Laravel Way" principle); it is
  expressed as a behavioural requirement (FR-013) rather than naming a specific framework
  helper, so the spec stays as technology-agnostic as the user's directive allows.
- The decision between hardcoding the supported currency list vs. managing it via the admin
  panel remains the developer's per the original brief (FR-001 / "Supported currency list"
  assumption).
- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`.
