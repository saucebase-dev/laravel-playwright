<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class ArtisanTest extends TestCase
{
    public function test_runs_artisan_command(): void
    {

        /** @var array<string|int> $json */
        $json = $this->post('playwright/artisan', [
            'command' => 'route:list',
        ])
            ->assertOk()
            ->json();

        $this->assertEquals(0, $json['code']);
        $this->assertStringContainsString('playwright/artisan', (string) $json['output']);

    }

    public function test_runs_artisan_with_named_parameters(): void
    {
        Artisan::command('e2e:parameters {user} {--force} {--id=*}', function () {
            /** @var Command $this */
            $this->line(json_encode([
                'user' => $this->argument('user'),
                'force' => $this->option('force'),
                'ids' => $this->option('id'),
            ], JSON_THROW_ON_ERROR));
        });

        $this->postJson('playwright/artisan', [
            'command' => 'e2e:parameters',
            'parameters' => ['user' => '42', '--force' => true, '--id' => ['5', '13']],
        ])->assertOk()->assertJsonPath('code', 0)
            ->assertJsonPath('output', '{"user":"42","force":true,"ids":["5","13"]}'.PHP_EOL);
    }

    /**
     * A web server runs PHP from public/, but commands expect the CLI's working
     * directory: `migrate:fresh` on SQLite empties a relative DB_DATABASE path.
     */
    public function test_runs_commands_from_the_application_root(): void
    {
        Artisan::command('e2e:cwd', function () {
            /** @var Command $this */
            $this->line((string) getcwd());
        });

        $previous = (string) getcwd();
        chdir(sys_get_temp_dir());

        try {
            /** @var array<string|int> $json */
            $json = $this->post('playwright/artisan', ['command' => 'e2e:cwd'])->assertOk()->json();
        } finally {
            chdir($previous);
        }

        $this->assertSame(realpath(base_path()), realpath(trim((string) $json['output'])));
    }
}
