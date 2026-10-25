# Laravel Playwright


This repository contains a Laravel and a Playwright library to help you write E2E tests for your Laravel application using [Playwright](https://playwright.dev/). It adds a set of endpoints to your Laravel application to allow Playwright to interact with it. You can do the following from your Playwright tests:

- Run artisan commands
- Create models using factories
- Run database queries
- Run PHP functions
- Update Laravel config while a test is running (until the test ends and calls `tearDown`).
- Register a boot function to run on every subsequent Laravel request in the test — useful for swapping service bindings.
- Traveling to a specific time in the application during the test

## 📦 Installation

On Laravel side, install the package via composer:

```bash
composer require --dev saucebase/laravel-playwright
```

On Playwright side, install the package via npm:

```bash
npm install @saucebase/laravel-playwright --save-dev
```

## ⚙️ Laravel Config

Publish the config file:

```bash
php artisan vendor:publish --tag=laravel-playwright-config
```

This creates `config/laravel-playwright.php` with the following options:

```php
return [
    /**
     * The prefix for the testing endpoints used to interact with Playwright.
     * Make sure to update `use.laravelBaseUrl` in playwright.config.ts if you change this.
     */
    'prefix' => env('PLAYWRIGHT_PREFIX', 'playwright'),

    /**
     * The environments in which the testing endpoints are enabled.
     * CAUTION: the endpoints run arbitrary PHP and SQL. Never enable them in
     * production, nor in `local`, the environment your own dev server runs in.
     */
    'environments' => ['e2e', 'testing'],

    /**
     * Optional secret token to authenticate Playwright requests.
     * Set PLAYWRIGHT_SECRET in your .env and laravelSecret in playwright.config.ts.
     */
    'secret' => env('PLAYWRIGHT_SECRET', null),
];
```

## 💡 Example

If you need a good and more complex example of a repository using this package, look at https://github.com/saucebase-dev/saucebase

## 🎭 Playwright Config

Set `use.laravelBaseUrl` in your `playwright.config.ts` to the base URL of your testing endpoints (your application URL + the `prefix` from Laravel config).

```ts
export default defineConfig({
    /** ...other config */
    use: {
        laravelBaseUrl: 'http://localhost/playwright',
        laravelSecret: process.env.PLAYWRIGHT_SECRET,  // optional
    },
});
```

If you use TypeScript, include the `LaravelOptions` type in the `defineConfig` function.

```ts
import type { LaravelOptions } from '@saucebase/laravel-playwright';

export default defineConfig<LaravelOptions>({
    use: {
        laravelBaseUrl: 'http://localhost/playwright',
        laravelSecret: process.env.PLAYWRIGHT_SECRET,  // optional
    },
});
```

## 🏝️ A dedicated environment

Run the suite on its own server and database, so it never touches your dev app or its data. This works the same however you serve the app day to day: Herd, Valet, Sail, Docker or `artisan serve`.

**1. Create `.env.e2e`:**

```bash
php artisan playwright:env
```

This writes a copy of your `.env` with its own server and database. Laravel loads one env file, not one on top of another, so the copy keeps everything else your app needs (`APP_KEY`, secrets, mail). Only these change:

```dotenv
APP_ENV=e2e
APP_URL=http://localhost:8100
DB_CONNECTION=sqlite
DB_URL=
DB_DATABASE=database/e2e.sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
REDIS_PREFIX=<your prefix>e2e_
```

Options: `php artisan playwright:env <environment> --url=http://localhost:9000 --force`. Edit the file afterwards for anything else the suite needs. Keep `.env.e2e` out of git like `.env`, and in CI, run the command after creating `.env`.

**2. Let Playwright start the server:**

```ts
import { defineConfig } from '@playwright/test';
import { laravelServer, type LaravelOptions } from '@saucebase/laravel-playwright';

export default defineConfig<LaravelOptions>({
    use: { baseURL: 'http://localhost:8100' },
    webServer: laravelServer(),   // migrate, then php artisan serve --env=e2e on APP_URL's port
});
```

`laravelServer({ env, host, workers, cwd, migrate })` returns a Playwright `webServer` entry. It serves the app on the port of `.env.e2e`'s `APP_URL` and waits on that URL, because an app that tells tenants apart by host answers 404 on any other. It migrates the database first (creating the SQLite file if needed), because an app that reads the database on every request, such as a tenant lookup at boot, can't be migrated over HTTP while its tables are missing. Pass `migrate: false` to skip that. It sets `APP_ENV` and `PHP_CLI_SERVER_WORKERS` (4 by default) for the server, and reuses a running one outside CI. It gives the server its own config, route and event caches (`bootstrap/cache/e2e/`). A config you cached with `config:cache` would otherwise skip every env file and give the server your dev database. It refuses to start if:

- `.env.e2e` is missing, because Laravel would then fall back to `.env`.
- `APP_URL` is missing or not `http:`. `artisan serve` has no TLS.
- The Playwright process already has another `APP_ENV`. The server inherits the process's environment, and real environment variables override any env file, so `import 'dotenv/config'` (which loads `.env`) would give the server your dev database. To read values from `.env.e2e` in the config, use `config({ path: '.env.e2e' })` from dotenv.

**Subdomains and multi-tenancy.** Chromium resolves every `*.localhost` to your machine, so `http://acme.localhost:8100` reaches the same server with no DNS or hosts-file setup. To use a real wildcard host instead (Herd or Valet `*.app.test`), skip `laravelServer()` and point `baseURL` at that host, served with `APP_ENV=e2e`.

## 🔒 Security

The package supports an optional shared secret to prevent unauthorized access to the testing endpoints.

**Laravel `.env`:**
```
PLAYWRIGHT_SECRET=some-secret
```

**`playwright.config.ts`:**
```ts
use: {
    laravelSecret: process.env.PLAYWRIGHT_SECRET,
},
```

When configured, the TypeScript client sends the secret as an `X-Playwright-Secret` header on every request. Laravel returns `401 Unauthorized` if the header is missing or doesn't match. When `PLAYWRIGHT_SECRET` is not set, all requests pass through (backwards compatible).

## 🧪 Setting up tests

In your Playwright tests, swap the `test` import from `@playwright/test` to `@saucebase/laravel-playwright`.

```diff
- import { test } from '@playwright/test';
+ import { test } from '@saucebase/laravel-playwright';

test('example', async ({ laravel }) => {
    await laravel.artisan('migrate:fresh');
});
```

> **Note**: In practice, it is not recommended to import from `@saucebase/laravel-playwright` directly in every test file if you have many. Instead, create your own [test fixture](https://playwright.dev/docs/test-fixtures) extending `test` from `@saucebase/laravel-playwright` and import that fixture in your tests.

## 🚀 Basic Usage

```ts
import { test } from '@saucebase/laravel-playwright';

test('example', async ({ laravel }) => {

    /** 🏃 RUN ARTISAN COMMANDS */
    const output = await laravel.artisan('migrate:fresh');
    /**
     * output.code: number - The exit code of the command
     * output.output: string - The output of the command
     */
    /** with parameters */
    await laravel.artisan('db:seed', ['--class', 'DatabaseSeeder']);


    /** 🗑️ TRUNCATE TABLES */
    await laravel.truncate();
    /** in specific DB connections */
    await laravel.truncate(['connection1', 'connection2']);


    /** 🏭 CREATE MODELS FROM FACTORIES */
    /**
     * Create a App\Models\User model
     * user will be an object of the model
     */
    const user = await laravel.factory('User');
    /** Create a App\Models\User model with attributes */
    await laravel.factory('User', { name: 'John Doe' });
    /**
     * Create 5 App\Models\User models
     * users will be an array of the models
     */
    const users = await laravel.factory('User', {}, 5);
    /** Create a CustomModel model */
    await laravel.factory('CustomModel');


    /** 💾 RUN A DATABASE QUERY */
    await laravel.query('DELETE FROM users');
    /** with bindings */
    await laravel.query('DELETE FROM users WHERE id = ?', [1]);
    /** on a specific connection */
    await laravel.query('DELETE FROM users', [], { connection: 'connection1' });
    /** unprepared statement */
    await laravel.query(`
        DROP SCHEMA public CASCADE;
        CREATE SCHEMA public;
        GRANT ALL ON SCHEMA public TO public;
    `, [], { unprepared: true });


    /** 🔍 RUN A SELECT QUERY */
    /** returns an array of objects */
    const blogs = await laravel.select('SELECT * FROM blogs');
    /** with bindings */
    await laravel.select('SELECT * FROM blogs WHERE id = ?', [1]);
    /** on a specific connection */
    await laravel.select('SELECT * FROM blogs', {}, { connection: 'connection1' });


    /** ⚙️ RUN A PHP FUNCTION */
    /**
     * Output is JSON encoded in Laravel and decoded in Playwright
     * The following examples call this function:
     * function sayHello($name) { return "Hello, $name!"; }
     */
    const funcOutput = await laravel.callFunction('sayHello');
    /** with positional parameters */
    await laravel.callFunction('sayHello', ['John']);
    /** with named parameters */
    await laravel.callFunction('sayHello', { name: 'John' });
    /** static class method */
    await laravel.callFunction("App\\MyAwesomeClass::method");

});


```

## 🔄 Dynamic Configuration

You can update Laravel config for every subsequent request **of the same test**, until the test ends.

The state is kept per test. The `laravel` fixture gives each test an id and sends it in an `X-Playwright-Test` header, on its own calls and on the browser's requests to the app. The server applies a test's state only to requests that carry its id. Parallel tests never see or remove each other's config, time or boot functions, and requests without the header (your own browser, a queue worker) see none.

- Only requests to the app carry the header: the `baseURL` host and its subdomains. Third-party requests (Stripe, CDNs) are left alone, so no CORS preflight fails. Override the match with the `laravelIsAppUrl: (url: URL) => boolean` option.
- The header is added with `context.route()`. If you add your own route for app URLs, call `route.fallback()`, not `route.continue()`, so the header is still added.
- A context you create yourself (`browser.newContext()`) doesn't get the header.

```ts
import { test } from '@saucebase/laravel-playwright';

test('example', async ({ laravel }) => {

    /** 🔧 SET DYNAMIC CONFIG */
    /**
     * Persists for all subsequent requests until the test ends
     * and tearDown is called (done automatically)
     */
    await laravel.config('app.timezone', 'America/Sao_Paulo');

    /** ⏰ TRAVEL TO A TIME */
    /** similar to Laravel's `travelTo` method */
    await laravel.travel('2022-01-01 12:00:00');

});
```

## 🔁 Boot Functions

Boot functions let you run PHP code **at the start of every subsequent Laravel request** during a test. This is the right tool when you need a service binding or side effect to be in place for browser-driven requests — not just during test setup.

A common use case is swapping a real external service (payment gateway, mailer, SMS provider) with a fake for the duration of a test.

**1. Create a helper class in your app** (e.g. `app/E2EHelpers.php`):

```php
<?php

namespace App;

use App\Services\PaymentGateway;
use App\Services\FakePaymentGateway;
use App\Services\Mailer;
use App\Services\NullMailer;

class E2EHelpers
{
    /**
     * Swap real services for fakes. Called at boot on every request in the test.
     */
    public static function useFakeServices(): void
    {
        app()->bind(PaymentGateway::class, FakePaymentGateway::class);
        app()->bind(Mailer::class, NullMailer::class);
    }
}
```

**2. Register it in your Playwright test:**

```ts
import { test } from '@saucebase/laravel-playwright';

test('checkout with fake payment gateway', async ({ laravel, page }) => {

    // Register once — runs at boot on every subsequent request this test makes
    await laravel.registerBootFunction('App\\E2EHelpers::useFakeServices');

    // From here, every page load and API call the browser makes will use the fakes
    const user = await laravel.factory('User');
    await laravel.artisan('db:seed', ['--class', 'ProductSeeder']);

    await page.goto('/checkout');
    await page.fill('[name="card_number"]', '4242424242424242');
    await page.click('button[type="submit"]');

    await expect(page.locator('.order-confirmation')).toBeVisible();

});
```

> **Why not use `callFunction` instead?**
> `callFunction` runs once and returns. `registerBootFunction` runs at the start of **every** request the browser makes during the test, so service bindings registered in the boot phase are in place for the full request lifecycle — including middleware, controllers, and event listeners.

> **Note:** The helper class only needs to exist in your `e2e` and `testing` environments. You can guard it with an environment check or keep it out of production deployments entirely.

## 🤖 Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and a `laravel-playwright-development` skill, covering the e2e environment, per-test state, subdomains and common failures. Boost asks before including third-party packages: pick `saucebase/laravel-playwright` in `php artisan boost:install`, or add it to `packages` in `boost.json` and run `php artisan boost:update`.

## ⬆️ Upgrading from v1

- **The endpoints are off in `local`.** The default `environments` is now `['e2e', 'testing']`. Serve the suite with `APP_ENV=e2e` (see [A dedicated environment](#️-a-dedicated-environment)). If you published the config, update `environments` there.
- **Dynamic config, `travel()` and boot functions are per test.** State moved from `storage/laravel-playwright-config.json` to `storage/framework/playwright/<test-id>.json`, and only applies to requests that carry the test's `X-Playwright-Test` header. The fixture sends the header for you. If you call the endpoints from your own client, send it yourself; `dynamicConfig`, `travel` and `registerBootFunction` return 400 without it.
- `DynamicConfig::set()`, `get()`, `getAll()`, `load()` and `delete()` now take the test id as their first argument.
- **Artisan commands run from the application root**, like the CLI, rather than the web server's `public/`.
- Requires Laravel 12 or 13.

## 🙏 Credits

This package was inspired by [hyvor/laravel-playwright](https://github.com/hyvor/laravel-playwright)
