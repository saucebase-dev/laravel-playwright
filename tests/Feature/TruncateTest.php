<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\Helpers\UserModel;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class TruncateTest extends TestCase
{

    public function testTruncates(): void
    {
        UserModel::factory()->count(3)->create();
        $this->assertCount(3, UserModel::all());

        $this->postJson('/playwright/truncate');

        $this->assertCount(0, UserModel::all());
    }

    public function testRejectsNonArrayConnections(): void
    {
        $this->postJson('/playwright/truncate', [
            'connections' => 'not-an-array',
        ])->assertUnprocessable();
    }

}