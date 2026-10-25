<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class DynamicConfigTest extends TestCase
{

    public function testCreateDynamicConfig(): void
    {
        $this->postJson('/playwright/dynamicConfig', [
            'key' => 'test_key',
            'value' => 'test_value',
        ], $this->testHeader())->assertOk();

        $this->assertSame(['test_key' => 'test_value'], DynamicConfig::getAll('test-id'));
    }

    public function testAppliesTheConfigOnTheNextRequestOfTheSameTest(): void
    {
        $this->postJson('/playwright/dynamicConfig', [
            'key' => 'app.name',
            'value' => 'Changed',
        ], $this->testHeader())->assertOk();

        $this->bootFor('test-id');

        $this->assertSame('Changed', config('app.name'));
    }

    public function testGetAllReturnsEmptyArrayWithoutState(): void
    {
        $this->assertSame([], DynamicConfig::getAll('test-id'));
        $this->assertSame([], DynamicConfig::getAll(null));
    }

    public function testLoadDoesNotThrowWithoutState(): void
    {
        DynamicConfig::load('test-id');
        DynamicConfig::load(null);
        $this->addToAssertionCount(1);
    }

}
