<?php

namespace Saucebase\LaravelPlaywright\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Saucebase\LaravelPlaywright\Tests\TestCase;

class ArtisanTest extends TestCase
{

    public function testRunsArtisanCommand(): void
    {

        /** @var array<string|int> $json */
        $json = $this->post('playwright/artisan', [
            'command' => 'route:list'
        ])
            ->assertOk()
            ->json();

        $this->assertEquals(0, $json['code']);
        $this->assertStringContainsString('playwright/artisan', (string) $json['output']);

    }

    /**
     * A web server runs PHP from public/, but commands expect the CLI's working
     * directory: `migrate:fresh` on SQLite empties a relative DB_DATABASE path.
     */
    public function testRunsCommandsFromTheApplicationRoot(): void
    {
        Artisan::command('e2e:cwd', function () {
            /** @var \Illuminate\Console\Command $this */
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
