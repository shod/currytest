<!-- Sync Impact Report
VERSION: 1.0.0 → 1.1.0 (MINOR — new Principle VII added)
RATIFIED: 2026-05-15 | LAST_AMENDED: 2026-05-15

Bump rationale: MINOR — a new principle (VII. Documentation-First Integration) is added, and
Principle IV is expanded with a cross-reference to it. Principle VI, which was added by the
user/linter after the v1.0.0 ratification, is hereby formally rolled into the versioned
constitution (no rule changes). No backward-incompatible changes.

PRINCIPLES (current state):
- I.   Test-First Development (NON-NEGOTIABLE)
- II.  Do Things the Laravel Way
- III. Code Quality & Formatting
- IV.  Agentic Development with Laravel Boost              (expanded with cross-reference to VII)
- V.   PHPUnit Testing Discipline
- VI.  Code Quality (NON-NEGOTIABLE)                       (formalised; added in v1.0.0 hotpatch)
- VII. Documentation-First Integration                     (NEW in v1.1.0)

SECTIONS: Core Principles (7) + Laravel Development Workflow + Governance

TEMPLATES REQUIRING UPDATES:
- .specify/templates/plan-template.md  — ⚠ pending review: the Constitution Check section
  should now gate on documentation-first lookups (Boost search-docs / context7 / vendor API
  docs) for any task that touches an external integration or non-trivial framework feature.
- .specify/templates/spec-template.md  — ✅ no change required (Principle VII is about
  implementation discipline, not spec authoring).
- .specify/templates/tasks-template.md — ⚠ pending review: integration tasks should call
  documentation lookup out as an explicit prerequisite step before implementation.

FOLLOW-UP TODOs: none.
-->

# Curry Test Constitution

## Core Principles

### I. Test-First Development (NON-NEGOTIABLE)

All features must be developed using Test-Driven Development (TDD):
- Write tests before implementation; tests must fail initially
- User approves test design before implementation proceeds
- Red-Green-Refactor cycle strictly enforced
- Every test file covers happy paths, failure paths, and edge cases
- Tests use PHPUnit with factories and realistic data fixtures
- Verification: Feature tests are the source of truth, not tinker scripts

**Rationale**: Tests serve as living documentation and prevent regression. TDD catches design issues early and ensures all code paths are exercised.

### II. Do Things the Laravel Way

Features must follow Laravel conventions and idioms:
- Use `php artisan make:` commands for all file generation (migrations, models, controllers, tests)
- Stick to existing directory structure; no new base folders without approval
- Use named routes and the `route()` function for URL generation
- Apply Eloquent ORM patterns; avoid raw SQL at application boundaries
- For APIs: use Eloquent API Resources and API versioning (unless existing code dictates otherwise)
- Constructor property promotion for dependency injection: `public function __construct(public GitHub $github) {}`
- Explicit return types and type hints on all methods
- Prefer configuration over code; check `config/` directory before hardcoding values

**Rationale**: Laravel's opinionated structure reduces cognitive load and makes code predictable for agents and future maintainers. Consistency enables better AI assistance.

### III. Code Quality & Formatting

All PHP code must pass Pint formatting and meet quality standards:
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files
- Use curly braces for all control structures, even single-line bodies
- Use TitleCase for Enum keys (`FavoritePerson`, `BestLake`, `Monthly`)
- PHPDoc blocks preferred over inline comments; inline comments only for non-obvious logic
- Use array shape type definitions in PHPDoc blocks
- No trailing whitespace; proper indentation (follow existing files)

**Rationale**: Consistent formatting ensures code review clarity and prevents style debates.

### IV. Agentic Development with Laravel Boost

Leverage Boost MCP tools for efficient development:
- Use `database-query` for read-only SQL instead of raw tinker
- Use `database-schema` to inspect table structure before migrations/models
- Use `search-docs` before making code changes (don't skip); scope queries by package when relevant
- Use `get-absolute-url` to resolve URLs instead of guessing domains
- Use `browser-logs` to debug frontend issues instead of manual inspection
- Run Artisan commands directly (`php artisan route:list`, `php artisan config:show`)
- Tinker is for exploration only; prefer tests and Artisan commands for verification

Boost is the **primary** documentation source for the Laravel ecosystem. For non-Laravel
libraries and for external HTTP APIs, see **Principle VII (Documentation-First Integration)**.

**Rationale**: Boost tools reduce context-switching and provide version-specific guidance, enabling faster and more accurate development.

### V. PHPUnit Testing Discipline

All tests must follow PHPUnit conventions and best practices:
- Create tests using `php artisan make:test [options] {name}` (feature tests by default, `--unit` for unit tests)
- Use factories for test data; leverage factory states before manual setup
- Faker methods: `$this->faker->word()` or `fake()->randomDigit()` (follow existing conventions)
- Feature tests are the default; unit tests for isolated logic only
- Never remove test files or tests without approval
- Run modified tests in isolation: `php artisan test --compact --filter=testName`
- All tests in a suite pass before finalizing changes

**Rationale**: Comprehensive tests prevent regressions and serve as the contract for features.

### VI. Code Quality (NON-NEGOTIABLE)

All code MUST comply with the following quality standards:
- **OOP principles**: SOLID, DRY, KISS, GOF design patterns
- **Laravel Best Practices**: Follow the guidelines in the [code-style](code-style.md) file
- **PSR-12**: Strict adherence to the PSR-12 coding standard
- **Documentation**: All public APIs must be documented in PHPDoc and OpenAPI 3 formats and must be in Russian
- **Dependency Injection**: Constructor DI by default for all classes. `app()` for service resolution is allowed ONLY in classes with a fixed constructor (JsonResource, Blade Component). For complete rules, see [code-style.md](code-style.md), "Dependency Injection" section
- **Strict Types**: All PHP files MUST begin with `declare(strict_types=1)` (NON-NEGOTIABLE)
- **Quality Automation**: Laravel Pint and LaraStan (minimum level 6) must pass without errors
- **Naming**: camelCase for PHP, snake_case for database columns.

**Rationale**: High code quality ensures maintainability and readability and reduces technical debt.

### VII. Documentation-First Integration

Before integrating with any external service, Laravel/PHP package, or non-trivial framework
feature, consult its authoritative documentation. Inference from training data or stale memory
is not acceptable when an authoritative source is reachable.

Required practices:
- **Laravel ecosystem — Boost `search-docs`**: For every Laravel-ecosystem package installed
  in this project (see `composer.json`), use the Boost MCP `search-docs` tool *before* writing
  code that touches that package. Use multiple broad topic-based queries; do not include
  package names in the query (Boost already scopes by installed version).
- **Non-Laravel libraries — MCP `context7`**: When documentation for a non-Laravel library or
  framework is required and is not available through Boost, use the MCP `context7` server (if
  configured for this environment) to retrieve up-to-date library documentation rather than
  relying on memory. Treat the version `context7` returns as authoritative for the version it
  reports.
- **External HTTP APIs — vendor docs**: For third-party HTTP APIs (e.g. freecurrencyapi.com,
  whose documentation lives at https://freecurrencyapi.com/docs), consult the vendor's
  published documentation before drafting endpoints, request shapes, authentication headers,
  error contracts, pagination, or rate-limit assumptions. Cite the relevant endpoint and
  parameter list in PR descriptions and/or commit messages when implementing an integration.
- **Verification over inference**: When documentation contradicts an existing assumption (in
  code, comments, or memory), the documentation wins. Update the code and any caching notes to
  match. Never paper over a doc/code conflict with a workaround.
- **No invented identifiers**: Do not invent endpoint paths, query parameters, configuration
  keys, package class names, or method signatures. If the doc lookup does not produce the
  identifier, stop and surface the gap rather than guess.

**Rationale**: Correctness here depends on tightly versioned ecosystem packages
(Laravel 13, PHP 8.4, PHPUnit 12) and on external API contracts set by vendors. The most
common failure mode of agentic coding is confidently calling APIs that have changed shape,
been renamed, or never existed. Documentation-first integration eliminates that class of bug
at its source and produces commits that are easy for a reviewer to verify against the cited
documentation.

## Laravel Development Workflow

### Migrations & Database

- Use `php artisan make:migration {name}` for schema changes
- Inspect schema with `database-schema` before writing queries
- Prefer Eloquent relationships over manual joins
- Always provide type safety in models (type hints, PHPDoc)

### Models & Factories

- Use `php artisan make:model` with `--migration --factory --seeder` flags for new models
- Factories must provide realistic defaults and custom states
- Seeders should reflect real-world data patterns

### Vite Asset Bundling

- If frontend changes don't appear in the browser, run `npm run build`, `npm run dev`, or `composer run dev`
- Check for "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" errors

### Backend Architecture

- Controllers and routes follow existing directory conventions
- Use form requests for validation: `php artisan make:request`
- Policies handle authorization: `php artisan make:policy`
- Jobs handle async work: `php artisan make:job`
- Service classes encapsulate business logic (check for reusable patterns first)

## Governance

### Amendment Procedure

Constitution changes require:
1. Explicit identification of the principle or section being amended
2. Clear rationale for the change
3. Impact assessment on dependent templates (plan, spec, tasks)
4. Version bump following semantic versioning rules

### Versioning Policy

**Version Format**: MAJOR.MINOR.PATCH
- **MAJOR**: Principle removal or backward-incompatible redefinition
- **MINOR**: New principle, section addition, or materially expanded guidance
- **PATCH**: Clarifications, wording corrections, non-semantic refinements

### Compliance Review

All PRs/reviews must:
- Verify code follows the seven Core Principles
- Check that tests are written before implementation (TDD)
- Confirm Pint formatting has been applied
- Verify Boost tools were considered for database/URL/doc tasks (and that Principle VII was
  applied for any external-integration or non-Laravel-library work)
- Ensure PHPUnit tests pass and cover critical paths

### Runtime Guidance

Development activities reference `CLAUDE.md` for:
- Specific Laravel, PHP, and package versions
- Tool preferences and permissions
- Deployment and Herd-specific configurations
- Framework-specific conventions and best practices

**Version**: 1.1.0 | **Ratified**: 2026-05-15 | **Last Amended**: 2026-05-15
