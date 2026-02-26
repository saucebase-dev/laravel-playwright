<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class TearDownTest extends TestCase
{

    public function testTearDown(): void
    {
        DynamicConfig::set('myconfig', true);

        $content = (string) file_get_contents(storage_path('laravel-playwright-config.json'));
        /** @var array<mixed> $data */
        $data = @json_decode($content, true);
        $this->assertEquals(true, $data['myconfig']);

        $this->postJson('/playwright/tearDown')->assertOk();

        $this->assertFileDoesNotExist(storage_path('laravel-playwright-config.json'));
    }

}
