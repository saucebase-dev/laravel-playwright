<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class EnvCommandTest extends TestCase
{

    private string $root;

    private string $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        assert($this->app !== null);
        $this->originalBasePath = $this->app->basePath();
        $this->root = sys_get_temp_dir() . '/laravel-playwright-env-' . uniqid();
        File::makeDirectory($this->root);
        $this->laravelApp()->setBasePath($this->root);
    }

    protected function tearDown(): void
    {
        $this->laravelApp()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function testWritesTheE2eEnvFromTheAppEnvWithItsOwnServerAndDatabase(): void
    {
        $this->writeEnv(<<<'ENV'
            APP_NAME=Shop
            APP_ENV=local
            APP_KEY=base64:secret
            APP_URL=https://shop.test

            DB_CONNECTION=mysql
            DB_URL=mysql://root@127.0.0.1/shop
            # DB_DATABASE=shop

            SESSION_DRIVER=database
            CACHE_STORE=redis
            QUEUE_CONNECTION=redis
            MAIL_MAILER=smtp
            REDIS_PREFIX=shop_
            PLAYWRIGHT_SECRET=abc
            ENV);

        $this->assertSame(0, Artisan::call('playwright:env'));

        $this->assertSame(<<<'ENV'
            APP_NAME=Shop
            APP_ENV=e2e
            APP_KEY=base64:secret
            APP_URL=http://localhost:8100

            DB_CONNECTION=sqlite
            DB_URL=
            DB_DATABASE=database/e2e.sqlite

            SESSION_DRIVER=file
            CACHE_STORE=file
            QUEUE_CONNECTION=sync
            MAIL_MAILER=log
            REDIS_PREFIX=shop_e2e_
            PLAYWRIGHT_SECRET=abc

            ENV, $this->read('.env.e2e'));
    }

    public function testAppendsWhatTheAppEnvLacks(): void
    {
        $this->writeEnv("APP_KEY=base64:secret\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $env = $this->read('.env.e2e');
        $this->assertStringStartsWith("APP_KEY=base64:secret\n", $env);
        $this->assertStringContainsString("\nAPP_ENV=e2e\n", $env);
        $this->assertStringContainsString("\nDB_DATABASE=database/e2e.sqlite\n", $env);
        $this->assertStringContainsString("\nREDIS_PREFIX=e2e_\n", $env);
    }

    public function testTakesTheEnvironmentAndUrl(): void
    {
        $this->writeEnv("APP_ENV=local\nAPP_URL=http://localhost\n");

        $this->assertSame(0, Artisan::call('playwright:env', ['environment' => 'browser', '--url' => 'http://localhost:9000']));

        $env = $this->read('.env.browser');
        $this->assertStringContainsString("APP_ENV=browser\n", $env);
        $this->assertStringContainsString("APP_URL=http://localhost:9000\n", $env);
        $this->assertStringContainsString("DB_DATABASE=database/browser.sqlite\n", $env);
    }

    public function testKeepsAnExistingFileUnlessForced(): void
    {
        $this->writeEnv("APP_ENV=local\n");
        File::put($this->root . '/.env.e2e', "MINE=1\n");

        $this->assertSame(1, Artisan::call('playwright:env'));
        $this->assertSame("MINE=1\n", $this->read('.env.e2e'));

        $this->assertSame(0, Artisan::call('playwright:env', ['--force' => true]));
        $this->assertStringContainsString('APP_ENV=e2e', $this->read('.env.e2e'));
    }

    public function testFallsBackToTheExampleWithoutAnAppEnv(): void
    {
        File::put($this->root . '/.env.example', "APP_ENV=local\nAPP_KEY=\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $this->assertStringContainsString('APP_ENV=e2e', $this->read('.env.e2e'));
    }

    public function testFailsWithNothingToCopy(): void
    {
        $this->assertSame(1, Artisan::call('playwright:env'));
        $this->assertFileDoesNotExist($this->root . '/.env.e2e');
    }

    private function writeEnv(string $content): void
    {
        File::put($this->root . '/.env', $content . (str_ends_with($content, "\n") ? '' : "\n"));
    }

    private function read(string $file): string
    {
        return File::get($this->root . '/' . $file);
    }

    private function laravelApp(): \Illuminate\Foundation\Application
    {
        return app();
    }

}
