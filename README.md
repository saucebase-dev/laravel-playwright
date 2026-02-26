# Laravel Playwright [WORK IN PROGRESS]


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
     * CAUTION: Enabling testing endpoints in production is a critical security issue.
     */
    'environments' => ['local', 'testing'],

    /**
     * Optional secret token to authenticate Playwright requests.
     * Set PLAYWRIGHT_SECRET in your .env and laravelSecret in playwright.config.ts.
     */
    'secret' => env('PLAYWRIGHT_SECRET', null),
];
```

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

You can update Laravel config for **ALL** subsequent requests until the test ends.

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

> **Note:** The helper class only needs to exist in your `local` and `testing` environments. You can guard it with an environment check or keep it out of production deployments entirely.

## 🙏 Credits

This package is a fork of [hyvor/laravel-playwright](https://github.com/hyvor/laravel-playwright) by [Hyvor](https://hyvor.com).
