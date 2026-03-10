<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\TestCase;

class DynamicConfigTest extends TestCase
{

    public function testCreateDynamicConfig(): void
    {

        $this->postJson('/playwright/dynamicConfig', [
            'key' => 'test_key',
            'value' => 'test_value',
        ])->assertOk();

        $file = storage_path('laravel-playwright-config.json');
        $this->assertTrue(file_exists($file));
        $content= (string) file_get_contents($file);

        $json = json_decode($content, true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('test_key', $json);
        $this->assertEquals('test_value', $json['test_key']);

    }

    public function testGetAllReturnEmptyArrayWhenFileDoesNotExist(): void
    {
        $file = \Saucebase\LaravelPlaywright\Services\DynamicConfig::getFilePath();
        if (file_exists($file)) {
            unlink($file);
        }
        $result = \Saucebase\LaravelPlaywright\Services\DynamicConfig::getAll();
        $this->assertSame([], $result);
    }

    public function testLoadDoesNotThrowWhenFileIsAbsent(): void
    {
        $file = \Saucebase\LaravelPlaywright\Services\DynamicConfig::getFilePath();
        if (file_exists($file)) {
            unlink($file);
        }
        \Saucebase\LaravelPlaywright\Services\DynamicConfig::load();
        $this->addToAssertionCount(1);
    }

}