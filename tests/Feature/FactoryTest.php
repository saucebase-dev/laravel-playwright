<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Saucebase\LaravelPlaywright\Tests\Helpers\UserModel;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class FactoryTest extends TestCase
{

    public function testCreatesModelFromFactory(): void
    {
        $this->postJson('playwright/factory', [
            'model' => '\Saucebase\LaravelPlaywright\Tests\Helpers\UserModel',
            'attrs' => [
                'name' => 'John Doe',
            ]
        ])
            ->assertOk()
            ->assertJsonPath('name', 'John Doe');

        $this->assertEquals(1, UserModel::count());
    }

    public function testCreatesModelFromFactoryWithCount(): void
    {
        $this->postJson('playwright/factory', [
            'model' => '\Saucebase\LaravelPlaywright\Tests\Helpers\UserModel',
            'count' => 3
        ])
            ->assertOk()
            ->assertJsonCount(3);

        $this->assertEquals(3, UserModel::count());
    }

    public function testRequiresModel(): void
    {
        $this->postJson('/playwright/factory', [])
            ->assertUnprocessable();
    }

    public function testRejectsNonExistentClass(): void
    {
        $this->postJson('/playwright/factory', [
            'model' => 'NonExistentModelClass',
        ])->assertUnprocessable();
    }

    public function testRejectsModelWithoutFactory(): void
    {
        $this->postJson('/playwright/factory', [
            'model' => 'stdClass',
        ])->assertUnprocessable();
    }

}
