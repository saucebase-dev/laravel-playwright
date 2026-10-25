---
name: laravel-playwright-development
description: "Write and run Playwright E2E tests for a Laravel app with saucebase/laravel-playwright. Activate when writing or debugging E2E specs; configuring playwright.config.ts or .env.e2e; seeding data, running artisan, querying or changing config and time from a spec; testing subdomains or multi-tenancy; or when parallel tests interfere with each other."
license: MIT
metadata:
  author: saucebase
---

# Laravel Playwright

A Laravel package plus a Playwright fixture. The package adds endpoints under `/playwright` (artisan, factory, query, select, function, config, travel, boot functions, tearDown); the `laravel` fixture calls them from a spec. Full API and examples: `vendor/saucebase/laravel-playwright/README.md`.

## The e2e environment

The suite runs on its own server and database, never the developer's. Three pieces:

1. **`.env.e2e`**, written by `php artisan playwright:env` (`--force` to rewrite it, `--url` for another port): a full copy of `.env` with `APP_ENV=e2e`, `APP_URL=http://localhost:8100`, SQLite `database/e2e.sqlite` and service-free drivers. Laravel loads one env file only, so it stays a full copy. Keep it out of git like `.env`.
2. **`webServer: laravelServer()`** in `playwright.config.ts`. It runs `migrate --force`, then `php artisan serve --env=e2e` on `APP_URL`'s port, waits on `APP_URL`, and gives the server its own config/route/event caches under `bootstrap/cache/e2e/`.
3. **`use.baseURL`** set to the same `APP_URL`.

To read values from the env file in the config, load `.env.e2e` (`config({ path: '.env.e2e' })` from dotenv). `laravelServer()` throws when the Playwright process holds another `APP_ENV`, because a loaded `.env` would hand the server the developer's database through inherited environment variables.

`laravelServer()` options: `env` (default `e2e`), `host` (bind address, `127.0.0.1`), `workers` (`PHP_CLI_SERVER_WORKERS`, 4), `cwd`, `migrate` (`true`).

## Writing specs

Import `test` from `@saucebase/laravel-playwright` (or the app's fixture extending it) and `expect` from `@playwright/test`. In an app with many specs, extend `test` once into a project fixture.

- Seed with `laravel.factory('User', attrs, count?)`: a bare name resolves to `App\Models\<Name>`; pass the full class for module or package models.
- Run app code with `laravel.callFunction('App\\E2E\\Helpers::method', args)`: any callable, positional or named args, JSON-encoded return. Prefer a static helper class over long `query()` strings.
- Reset with `laravel.artisan('migrate:fresh')` / `db:seed` in a setup project that runs before the others, rather than per test.
- `laravel.config(key, value)`, `laravel.travel(time)` and `laravel.registerBootFunction(callable)` last until the test ends.

## Per-test state

Config, time and boot functions are kept per test. The fixture gives each test an id and sends it as `X-Playwright-Test` on its own calls and on the browser's requests to the app, so parallel tests never see or clear each other's state.

- The header goes only to the `baseURL` host and its subdomains, through `context.route()`. Third parties (Stripe, CDNs) stay untouched. Change the match with the `laravelIsAppUrl: (url: URL) => boolean` option.
- A spec's own `page.route()` / `context.route()` for app URLs ends with `route.fallback()`, so the header is still added.
- A context made with `browser.newContext()` carries no header, so it sees none of the test's state.
- State endpoints return 400 without the header; `tearDown` removes only its own test's state.

## Subdomains and multi-tenancy

Chromium resolves every `*.localhost` to the machine, so with `APP_URL=http://localhost:8100` a tenant lives at `http://acme.localhost:8100` with no DNS setup, and the test header reaches it. Build tenant URLs from the server's own URL helpers rather than hand-written hosts. For a real wildcard host (Herd or Valet `*.app.test`), drop `laravelServer()` and point `baseURL` at that host served with `APP_ENV=e2e`.

## Debugging

| Symptom | Cause |
| --- | --- |
| `Timed out waiting ... from config.webServer` | The app answers 500 or 404 at `APP_URL`: read `storage/logs/laravel.log` for the `e2e.ERROR` line |
| 404 from `/playwright/*` | The server is not in `e2e`/`testing`, or a cached route file from another environment is in use |
| A spec's config or time does not apply in the browser | The request bypassed the header: a custom route using `continue()`, a new context, or a host outside `laravelIsAppUrl` |
| Tests pass alone, fail together | Shared rows (fixed emails, unique slugs): give each test unique data, or seed shared accounts once in the setup project |
| `Failed to call Laravel /...` with 401 | `PLAYWRIGHT_SECRET` is set; pass it as `use.laravelSecret` |
