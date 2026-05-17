# Currency Storage and Conversion Module — Project Review

## Назначение проекта

Это тестовый проект, выполненный для демонстрации работы с AI-ассистентом (Claude Code) и практики
Specification-Driven Development (SDD). Задача — реализовать модуль хранения и конвертации валют в
рамках Laravel-приложения, опираясь на формальные спецификации и методологию Spec Kit.

---

## Методология: Spec Kit + SDD

Весь процесс разработки строился на последовательности артефактов, которые генерировались
AI-ассистентом перед написанием кода:

```
Constitution → Spec → Research → Data Model → Contracts → Plan → Tasks → Implementation
```

Каждый этап — отдельный документ в `specs/001-currency-converter/`. Задачи нумеровались (T001–T068)
и выполнялись в порядке зависимостей. Реализация велась по принципу TDD: сначала падающий тест,
потом код.

---

## Что реализовано

### Функциональность (все 4 User Story)

| Story | Приоритет | Что сделано |
|---|---|---|
| US1 — Конвертация валют | P1 MVP | `CurrencyConverter::convert(amount, from, to)` — основной сервис |
| US2 — Ежедневное обновление курсов | P2 | `RateRefresher` + `FreeCurrencyApiClient` + планировщик |
| US3 — Страница курсов в админке | P3 | `/admin/rates` — таблица всех сохранённых курсов |
| US4 — Страница валют в админке | P3 | `/admin/currencies` — список поддерживаемых валют |

### Ключевые технические решения

**Сервис конвертации** ([app/Services/Currency/CurrencyConverter.php](app/Services/Currency/CurrencyConverter.php)):
- Арифметика через `bcmath` (scale 10), округление до 2 знаков half-up
- Конвертация через базовую валюту (USD): `FROM → USD → TO`
- Нормализация кодов: trim + uppercase перед lookup
- Выброс `InvalidConversionAmountException` для отрицательных сумм

**HTTP-клиент** ([app/Services/Currency/FreeCurrencyApiClient.php](app/Services/Currency/FreeCurrencyApiClient.php)):
- Laravel `Http` facade (Guzzle), без сторонних SDK для freecurrencyapi.com
- API-ключ только через `config('currency.freecurrencyapi.api_key')`, никаких `env()` в бизнес-коде

**Retry-логика** ([app/Services/Currency/RateRefresher.php](app/Services/Currency/RateRefresher.php)):
- До 2 повторных попыток с exponential backoff (~30s, ~5m)
- Атомарный lock через Laravel Cache для защиты от конкурентных запусков
- Логирование каждой попытки в `refresh_job_logs`

**Консольные команды**:
- `php artisan currency:refresh-rates` — ручное обновление курсов (FR-012)
- `php artisan currency:convert 100 USD RUB` — smoke-test конвертации из CLI

**Админка** (Blade, без SPA/Filament):
- `/admin/login` — форма входа (session-based auth)
- `/admin/rates` — курсы с метаданными последнего обновления
- `/admin/currencies` — список валют (ISO-код, имя, статус)
- Middleware `EnsureUserIsAdmin`, красный баннер при дефолтных credentials в не-local окружении

**База данных** (SQLite, migrations):
- `currencies` — поддерживаемые валюты (USD, EUR, RUB, GBP, CNY, JPY, CHF, CAD, AUD, PLN)
- `exchange_rates` — курсы `DECIMAL(20,10)`, upsert по `[base_code, target_currency_id]`
- `refresh_job_logs` — история попыток обновления

### Качество кода

- **PHPUnit**: 50 тестов (feature + unit), покрывают happy path, failure path, edge cases
- **PHPStan**: level 6, 0 ошибок
- **Pint**: код отформатирован, PSR-12
- `declare(strict_types=1)` на всех PHP-файлах
- PHPDoc на публичных методах сервисов (на русском, по требованию constitution)

---

## На что обратить внимание при проверке

### 1. Соответствие спецификации
Спецификация детально описывает 24 Functional Requirements (FR-001..FR-024) и 10 Success Criteria
(SC-001..SC-010). Рекомендую сопоставить реализацию со
[spec.md](specs/001-currency-converter/spec.md) — особенно FR-008 (bcmath), FR-013 (api-key via
config), FR-014 (concurrent lock), FR-020..FR-022 (admin seed).

### 2. Конвертация через базовую валюту
Все курсы хранятся как `USD → X`. При конвертации `EUR → RUB` вычисление идёт через цепочку:
`EUR → USD → RUB`. Логика в `CurrencyConverter::convert` (строки 58–68). Тесты покрывают этот
сценарий в `tests/Unit/Services/CurrencyConverterTest.php`.

### 3. Идемпотентность сидера
`AdminUserSeeder` создаёт пользователя только при отсутствии. Повторный запуск — без изменений.
Тест сценария SC-009 в `tests/Feature/Seeders/AdminUserSeederTest.php`.

### 4. Безопасность API-ключа
Ключ `FREECURRENCYAPI_KEY` в `.env` (git-ignored). В коде — только через `config('currency...')`.
Проверить: `git grep FREECURRENCYAPI_KEY` должен вернуть 0 совпадений в tracked files.

### 5. Предупреждение о дефолтных credentials
`DefaultCredentialWatcher` в `AppServiceProvider` логирует предупреждение при старте, если пароль
admin не сменён в не-`local` окружении (FR-022).

### 6. Retry при ошибке API
`RateRefresher` делает до 3 попыток (1 + 2 retry) с задержками. Тесты с `Http::fake()` проверяют
все 3 исхода: success, 3 failures (все попытки исчерпаны), skip при concurrent lock.

---

## Структура документации

```
specs/
├── conversation/
│   ├── session-1.md              ← все сообщения пользователя в сессии спецификации
│   └── summary-implementation.md ← итоговый summary реализации
└── 001-currency-converter/
    ├── spec.md                   ← требования, user stories, acceptance criteria
    ├── plan.md                   ← архитектурный план, structure decision
    ├── research.md               ← исследование перед планированием
    ├── data-model.md             ← схема БД
    ├── tasks.md                  ← 68 задач (T001–T068), все выполнены [X]
    ├── quickstart.md             ← инструкция запуска за 5 минут
    ├── checklists/requirements.md
    └── contracts/
        ├── currency-converter.md ← публичный контракт сервиса
        └── freecurrencyapi.md    ← контракт внешнего API
```

Файлы `specs/conversation/` содержат историю взаимодействия с AI:
- **session-1.md** — все сообщения пользователя в сессии создания спецификации
- **summary-implementation.md** — итоговый отчёт по реализации

---

## Быстрый старт

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
# Добавить FREECURRENCYAPI_KEY=<ваш ключ> в .env
php artisan migrate --force
php artisan db:seed --force

# Проверить конвертацию (требуются курсы в БД)
php artisan currency:refresh-rates
php artisan currency:convert 100 USD RUB

# Запустить тесты
php artisan test --compact
```

Админка доступна по адресу `https://currytest.test/admin/login` (логин: `admin`, пароль: `Aqaz`).
