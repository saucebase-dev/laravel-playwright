<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Services\DynamicConfig;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class PerTestStateTest extends TestCase
{

    public function testStateIsStoredPerTest(): void
    {
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'A'], $this->testHeader('test-a'))->assertOk();
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'B'], $this->testHeader('test-b'))->assertOk();

        $this->bootFor('test-a');
        $this->assertSame('A', config('app.name'));

        $this->bootFor('test-b');
        $this->assertSame('B', config('app.name'));
    }

    public function testRequestWithoutTheHeaderGetsNoState(): void
    {
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'A'], $this->testHeader('test-a'))->assertOk();

        $this->bootFor(null);

        $this->assertNotSame('A', config('app.name'));
    }

    public function testTearDownOnlyRemovesItsOwnState(): void
    {
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'A'], $this->testHeader('test-a'))->assertOk();
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'B'], $this->testHeader('test-b'))->assertOk();

        $this->postJson('/playwright/tearDown', [], $this->testHeader('test-a'))->assertOk();

        $this->assertSame([], DynamicConfig::getAll('test-a'));
        $this->assertSame(['app.name' => 'B'], DynamicConfig::getAll('test-b'));
    }

    public function testWritingStateRequiresTheHeader(): void
    {
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'A'])->assertStatus(400);
        $this->postJson('/playwright/travel', ['to' => '2025-01-01'])->assertStatus(400);
        $this->postJson('/playwright/registerBootFunction', ['function' => 'time'])->assertStatus(400);
    }

    public function testRejectsAnIdThatIsNotASafeFileName(): void
    {
        $this->postJson('/playwright/dynamicConfig', ['key' => 'app.name', 'value' => 'A'], $this->testHeader('../../evil'))->assertStatus(400);

        $this->assertNull(DynamicConfig::path('../../evil'));
    }

}
