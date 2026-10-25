<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\TestCase;

class TravelTest extends TestCase
{

    public function testTravel(): void
    {

        $this->postJson('/playwright/travel', [
            'to' => '2025-01-01',
        ], $this->testHeader())->assertOk();

        $this->bootFor('test-id');
        $this->assertEquals('2025-01-01', now()->format('Y-m-d'));

    }

}