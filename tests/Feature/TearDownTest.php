<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class TearDownTest extends TestCase
{

    public function testTearDown(): void
    {
        DynamicConfig::set('test-id', 'myconfig', true);
        $this->assertTrue(DynamicConfig::get('test-id', 'myconfig'));

        $this->postJson('/playwright/tearDown', [], $this->testHeader())->assertOk();

        $this->assertSame([], DynamicConfig::getAll('test-id'));
    }

    public function testTearDownWithoutTheHeaderIsANoOp(): void
    {
        DynamicConfig::set('test-id', 'myconfig', true);

        $this->postJson('/playwright/tearDown')->assertOk();

        $this->assertSame(['myconfig' => true], DynamicConfig::getAll('test-id'));
    }

}
