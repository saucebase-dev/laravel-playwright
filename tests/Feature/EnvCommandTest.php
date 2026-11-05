<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
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
            DB_URL="sqlite:///database/e2e.sqlite?transaction_mode=IMMEDIATE&busy_timeout=10000"
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

    public function testForceReplacesWithoutAsking(): void
    {
        $this->existingFile();

        $this->command('playwright:env', ['--force' => true])->assertSuccessful();

        $this->assertStringContainsString('APP_ENV=e2e', $this->read('.env.e2e'));
        $this->assertSame([], $this->backups());
    }

    public function testBackupKeepsThePreviousFileWithoutAsking(): void
    {
        $this->existingFile();

        $this->command('playwright:env', ['--backup' => true])
            ->expectsOutputToContain('.env.e2e.backup')
            ->assertSuccessful();

        $this->assertStringContainsString('APP_ENV=e2e', $this->read('.env.e2e'));
        $this->assertSame("MINE=1\n", File::get($this->backups()[0]));
    }

    public function testASecondBackupReplacesTheFirst(): void
    {
        $this->existingFile();

        $this->command('playwright:env', ['--backup' => true])->assertSuccessful();
        File::put($this->root . '/.env.e2e', "SECOND=1\n");
        $this->command('playwright:env', ['--backup' => true])->assertSuccessful();

        $this->assertSame("SECOND=1\n", $this->read('.env.e2e.backup'));
        $this->assertCount(1, $this->backups());
    }

    public function testUnattendedKeepsTheFileAndSaysSo(): void
    {
        $this->existingFile();

        $this->command('playwright:env', ['--no-interaction' => true])
            ->expectsOutputToContain('kept')
            ->assertSuccessful();

        $this->assertSame("MINE=1\n", $this->read('.env.e2e'));
    }

    public function testAsksWhatToDoWithAnExistingFile(): void
    {
        foreach ([
            ['Back up and replace', 'APP_ENV=e2e', 1],
            ['Replace', 'APP_ENV=e2e', 0],
            ['Keep', 'MINE=1', 0],
        ] as [$answer, $expected, $backups]) {
            $this->existingFile();
            File::delete($this->backups());

            $this->command('playwright:env')
                ->expectsChoice('.env.e2e already exists. What should happen to it?', $answer, ['Back up and replace', 'Replace', 'Keep'])
                ->assertSuccessful();

            $this->assertStringContainsString($expected, $this->read('.env.e2e'));
            $this->assertCount($backups, $this->backups());
        }
    }

    public function testEnvironmentVariablesWinOverTheDefaults(): void
    {
        $this->writeEnv("APP_ENV=local\nMAIL_MAILER=smtp\nE2E_MAIL_MAILER=array\nE2E_CUSTOM=\"a \$1 b\"\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $env = $this->read('.env.e2e');
        $this->assertStringContainsString("\nMAIL_MAILER=array\n", $env);
        $this->assertStringContainsString("\nCUSTOM=\"a \$1 b\"\n", $env);
    }

    public function testThePrefixFollowsTheEnvironment(): void
    {
        $this->writeEnv("E2E_MAIL_MAILER=array\nBROWSER_MAIL_MAILER=smtp\n");

        $this->assertSame(0, Artisan::call('playwright:env', ['environment' => 'browser']));

        $this->assertStringContainsString("\nMAIL_MAILER=smtp\n", $this->read('.env.browser'));
    }

    public function testASqliteDatabaseKeepsTheLockOptions(): void
    {
        $this->writeEnv("E2E_DB_DATABASE=database/custom.sqlite\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $env = $this->read('.env.e2e');
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString("\nDB_DATABASE=database/custom.sqlite\n", $env);
        $this->assertStringContainsString('DB_URL="sqlite:///database/custom.sqlite?transaction_mode=IMMEDIATE&busy_timeout=10000"', $env);
    }

    public function testAUrlIsUsedAsWritten(): void
    {
        $this->writeEnv("E2E_DB_URL=\"sqlite:///database/mine.sqlite?busy_timeout=5000\"\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $env = $this->read('.env.e2e');
        $this->assertStringContainsString('DB_URL="sqlite:///database/mine.sqlite?busy_timeout=5000"', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringNotContainsString('transaction_mode', $env);
    }

    public function testAServerDatabaseClearsTheUrl(): void
    {
        $this->writeEnv(<<<'ENV'
            APP_SLUG=shop
            DB_CONNECTION=mysql
            DB_URL=mysql://root@127.0.0.1/shop
            DB_DATABASE=shop
            E2E_DB_CONNECTION=mysql
            E2E_DB_HOST=127.0.0.1
            E2E_DB_PORT=3307
            E2E_DB_DATABASE=${APP_SLUG}_e2e
            E2E_DB_PASSWORD="p$1ss"

            ENV);

        $this->assertSame(0, Artisan::call('playwright:env'));

        $env = $this->read('.env.e2e');
        $this->assertStringContainsString("\nDB_URL=\n", $env);
        $this->assertStringContainsString("\nDB_CONNECTION=mysql\n", $env);
        $this->assertStringContainsString("\nDB_PORT=3307\n", $env);
        // Playwright's dotenv doesn't resolve variables, and the server inherits what it reads.
        $this->assertStringContainsString("\nDB_DATABASE=shop_e2e\n", $env);
        $this->assertStringContainsString("\nDB_PASSWORD=\"p\$1ss\"\n", $env);
        $this->assertStringNotContainsString('sqlite', $env);
    }

    public function testAResolvedValueIsQuotedWhenItNeedsTo(): void
    {
        $this->writeEnv("APP_NAME=\"My Shop\"\nE2E_MAIL_FROM_NAME=\${APP_NAME}-test\n");

        $this->assertSame(0, Artisan::call('playwright:env'));

        $this->assertStringContainsString("\nMAIL_FROM_NAME=\"My Shop-test\"\n", $this->read('.env.e2e'));
    }

    public function testRefusesTheAppsOwnDatabase(): void
    {
        foreach ([
            'resolved variable' => "APP_SLUG=shop\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=shop\nE2E_DB_CONNECTION=mysql\nE2E_DB_HOST=127.0.0.1\nE2E_DB_DATABASE=\${APP_SLUG}\n",
            'localhost and default port' => "DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=shop\nE2E_DB_CONNECTION=mysql\nE2E_DB_HOST=localhost\nE2E_DB_DATABASE=shop\n",
            'url against settings' => "DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=shop\nE2E_DB_URL=mysql://root@127.0.0.1:3306/shop\n",
            'app url' => "DB_CONNECTION=mysql\nDB_URL=mysql://root@db:3306/shop\nE2E_DB_CONNECTION=mysql\nE2E_DB_HOST=db\nE2E_DB_DATABASE=shop\n",
            'sqlite file' => "DB_CONNECTION=sqlite\nDB_DATABASE=database/app.sqlite\nE2E_DB_DATABASE=database/app.sqlite\n",
            'sqlite default' => "E2E_DB_DATABASE=database/database.sqlite\n",
        ] as $case => $env) {
            $this->writeEnv($env);

            $this->assertSame(1, Artisan::call('playwright:env'), $case);
            $this->assertFileDoesNotExist($this->root . '/.env.e2e', $case);
        }
    }

    public function testARefusalLeavesAnExistingFileAndMakesNoBackup(): void
    {
        $this->writeEnv("DB_CONNECTION=sqlite\nDB_DATABASE=database/app.sqlite\nE2E_DB_DATABASE=database/app.sqlite\n");
        File::put($this->root . '/.env.e2e', "MINE=1\n");

        $this->command('playwright:env', ['--backup' => true])->assertFailed();

        $this->assertSame("MINE=1\n", $this->read('.env.e2e'));
        $this->assertSame([], $this->backups());
    }

    public function testAnotherServerWithTheSameNameIsAllowed(): void
    {
        $this->writeEnv("DB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=shop\nE2E_DB_CONNECTION=mysql\nE2E_DB_HOST=127.0.0.1\nE2E_DB_PORT=3307\nE2E_DB_DATABASE=shop\n");

        $this->assertSame(0, Artisan::call('playwright:env'));
    }

    private function existingFile(): void
    {
        $this->writeEnv("APP_ENV=local\n");
        File::put($this->root . '/.env.e2e', "MINE=1\n");
    }

    /** @param  array<string, mixed>  $parameters */
    private function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        assert($pending instanceof PendingCommand);

        return $pending;
    }

    /** @return list<string> */
    private function backups(): array
    {
        /** @var list<string> $backups */
        $backups = array_values(File::glob($this->root . '/.env.e2e.backup*'));

        return $backups;
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
