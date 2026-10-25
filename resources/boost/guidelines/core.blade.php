# Laravel Playwright

- Playwright E2E tests run against a dedicated `e2e` environment: `.env.e2e` with its own database and `APP_URL`, served by `laravelServer()` in `playwright.config.ts`. The developer's own server and database stay untouched.
- The testing endpoints exist only in the `e2e` and `testing` environments.
- Import `test` from `@saucebase/laravel-playwright`, or from a fixture extending it, to get the `laravel` fixture.
- IMPORTANT: Activate `laravel-playwright-development` when writing or debugging Playwright E2E tests, configuring Playwright for a Laravel app, or calling `laravel.*` helpers.
