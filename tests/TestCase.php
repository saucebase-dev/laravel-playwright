<?php

namespace Saucebase\LaravelPlaywright\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Saucebase\LaravelPlaywright\ServiceProvider;
use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Saucebase\LaravelPlaywright\Tests\Helpers\Migrations;

class TestCase extends \Orchestra\Testbench\TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Migrations::run();
    }


    protected function tearDown(): void
    {
        File::deleteDirectory(DynamicConfig::directory());

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    /** @return array<string, string> */
    protected function testHeader(string $id = 'test-id'): array
    {
        return [DynamicConfig::HEADER => $id];
    }

    /**
     * Boot the provider the way a real request does: the HTTP kernel binds the
     * request before the providers boot, so boot can read its header.
     */
    protected function bootFor(?string $id = 'test-id'): void
    {
        $this->reloadApplication();
        assert($this->app !== null);

        $server = $id === null ? [] : ['HTTP_'.strtoupper(str_replace('-', '_', DynamicConfig::HEADER)) => $id];
        $this->app->instance('request', Request::create('/', 'GET', server: $server));

        (new ServiceProvider($this->app))->boot();
    }

}
