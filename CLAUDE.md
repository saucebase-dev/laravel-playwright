# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Git

- Commit messages must be a single line — no body, no bullet points
- No `Co-Authored-By` or AI attribution lines in commits

## Commands

```bash
# PHP tests
./vendor/bin/phpunit                          # all tests
./vendor/bin/phpunit --filter=SecurityTest    # single test class
./vendor/bin/phpunit --filter=testRunsAQuery  # single test method

# Static analysis (max level, covers src/ and tests/)
./vendor/bin/phpstan analyse --memory-limit=512M

# JS/TS build (outputs to dist/)
npm run build   # tsc type-check + vite build
```

## Architecture

This is a dual-language package: a **PHP Laravel package** (`src/`) paired with a **TypeScript Playwright client** (`src/playwright/src/`).

### How it works

The PHP side registers HTTP routes (under a configurable prefix, default `playwright/`) that are only active in configured environments (`local`, `testing`). The TypeScript `Laravel` class calls these routes via Playwright's `APIRequestContext`, then `tearDown()` is called automatically after each test via a Playwright fixture.

**Dynamic config** (`DynamicConfig`) persists test state across multiple requests within a single test by writing to `storage/laravel-playwright-config.json`. This file is loaded on every request (in `ServiceProvider::boot`) and deleted on `tearDown`. It's used for time travel and boot functions.

### PHP package structure

- **`src/ServiceProvider.php`** — registers/merges `config/laravel-playwright.php`, loads routes and `DynamicConfig` only in allowed environments
- **`src/Http/Controllers/`** — one invokable controller per endpoint: `ArtisanController`, `TruncateController`, `FactoryController`, `QueryController`, `SelectController`, `FunctionController`, `DynamicConfigController`, `TravelController`, `RegisterBootFunctionController`, `TearDownController`
- **`src/Services/Config.php`** — reads `laravel-playwright.*` config keys; `prefix()`, `envs()`, `secret()`
- **`src/Services/DynamicConfig.php`** — file-backed per-test state store; loaded at boot, deleted at tearDown
- **`src/Services/Truncate.php`** — truncates DB tables across connections; includes SQLite fallback (catches `QueryException` when `sqlite_sequence` doesn't exist)
- **`src/Http/Middleware/VerifyPlaywrightSecret.php`** — checks `X-Playwright-Secret` header or `_secret` body field against `PLAYWRIGHT_SECRET` env; no-op when secret is not configured

### Config (`config/laravel-playwright.php`)

```php
'prefix'       => env('PLAYWRIGHT_PREFIX', 'playwright'),
'environments' => ['local', 'testing'],
'secret'       => env('PLAYWRIGHT_SECRET', null),
```

### TypeScript client (`src/playwright/src/index.ts`)

Exports `test` (a Playwright fixture extension) and `Laravel` class. The fixture injects a `laravel` object and auto-calls `tearDown()`. The `LaravelOptions` interface adds `laravelBaseUrl` and `laravelSecret` as Playwright config options. The secret, if set, is forwarded as the `X-Playwright-Secret` header on every request.

### Tests

- Tests use **SQLite `:memory:`** (see `phpunit.xml`)
- `tests/Helpers/Migrations.php` uses `Schema::create()` (not raw SQL — raw `bigserial` is Postgres-only)
- PHPStan runs at **max level** on both `src/` and `tests/`
- `/** @var Type $var */` annotations are required before `Config::get()` assignments to satisfy PHPStan
- Middleware closures need `@param Closure(Request): Response $next` to satisfy return type inference
