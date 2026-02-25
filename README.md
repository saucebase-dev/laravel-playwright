# Laravel Playwright

This repository contains a Laravel and a Playwright library to help you write E2E tests for your Laravel application using [Playwright](https://playwright.dev/). It adds a set of endpoints to your Laravel application to allow Playwright to interact with it. You can do the following from your Playwright tests:

- Run artisan commands
- Create models using factories
- Run database queries
- Run PHP functions
- Update Laravel config while a test is running (until the test ends and calls `tearDown`).
- Registering a boot function to run on each Laravel request. You can use this feature to mock a service dependency, for example.
- Traveling to a specific time in the application during the test

## 📦 Installation

On Laravel side, install the package via composer:

```bash
composer require --dev saucebase/laravel-playwright
```

On Playwright side, install the package via npm:

```bash
npm install @saucebase/laravel-playwright
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

    /** 🚀 REGISTER A BOOT FUNCTION */
    /** useful to mock a service dependency, for example */
    await laravel.registerBootFunction('App\\E2EHelper::swapPaymentService');

});
```

## 🙏 Credits

This package is a fork of [hyvor/laravel-playwright](https://github.com/hyvor/laravel-playwright) by [Hyvor](https://hyvor.com).
