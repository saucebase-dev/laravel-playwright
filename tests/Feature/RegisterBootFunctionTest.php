<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\TestCase;

class RegisterBootFunctionTest extends TestCase
{

    public function testRegistersBootFunction(): void
    {

        $this->postJson('/playwright/registerBootFunction', [
            'function' => 'Saucebase\LaravelPlaywright\Tests\Feature\RegisterBootFunctionTest::setGlobalVariable',
        ], $this->testHeader())
            ->assertOk();

        $this->bootFor('test-id');
        $this->assertEquals('Yes', $GLOBALS['bootRunning']);

    }

    /**
     * This is called via the service container for testing purposes from the above test
     */
    public static function setGlobalVariable(): void
    {
        $GLOBALS['bootRunning'] = 'Yes';
    }

}