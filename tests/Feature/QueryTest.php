<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\Helpers\UserModel;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class QueryTest extends TestCase
{

    public function testRunsAQuery(): void
    {
        $users = UserModel::factory()
            ->count(3)
            ->create();

        $this->postJson('/playwright/query', [
            'query' => "update users set name = 'John Doe' where id = " . $users[0]?->id
        ])->assertOk();

        $this->assertEquals('John Doe', $users[0]?->refresh()->name);
    }

    public function testRequiresQuery(): void
    {
        $this->postJson('/playwright/query', [])
            ->assertUnprocessable();
    }

}