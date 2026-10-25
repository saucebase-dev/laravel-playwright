<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Writes `.env.e2e`: the app's own `.env` with the E2E suite's server and database.
 *
 * Laravel loads one env file, never one on top of another, so the file has to be
 * complete. Copying `.env` keeps everything the app needs (APP_KEY, secrets, mail)
 * and changes only what keeps the suite off the developer's server and data.
 */
class EnvCommand extends Command
{

    protected $signature = 'playwright:env
                            {environment=e2e : The environment the suite runs in; writes .env.<environment>}
                            {--url=http://localhost:8100 : APP_URL for the suite\'s server}
                            {--force : Replace an existing file}';

    protected $description = 'Write the env file the Playwright suite runs on, from the app\'s .env';

    public function handle(Filesystem $files): int
    {
        $environment = $this->argument('environment');
        $url = $this->option('url');
        if (!is_string($environment) || preg_match('/^[A-Za-z0-9_-]+$/', $environment) !== 1 || !is_string($url)) {
            $this->components->error('Invalid environment or URL.');

            return self::FAILURE;
        }

        $target = base_path(".env.{$environment}");
        if ($files->exists($target) && !$this->option('force')) {
            $this->components->error(".env.{$environment} already exists. Run again with --force to replace it.");

            return self::FAILURE;
        }

        $source = collect([base_path('.env'), base_path('.env.example')])->first(fn (string $path) => $files->exists($path));
        if ($source === null) {
            $this->components->error('There is no .env or .env.example to copy.');

            return self::FAILURE;
        }

        $content = $files->get($source);
        $prefix = trim(self::value($content, 'REDIS_PREFIX') ?? '', '"\'');

        foreach ($this->overrides($environment, $url, $prefix) as $key => $value) {
            $content = self::set($content, $key, $value);
        }

        $files->put($target, $content);
        $this->components->info(".env.{$environment} written from " . basename($source) . '.');

        return self::SUCCESS;
    }

    /**
     * Its own server and database; drivers that need no service of their own.
     *
     * @return array<string, string>
     */
    private function overrides(string $environment, string $url, string $redisPrefix): array
    {
        return [
            'APP_ENV' => $environment,
            'APP_URL' => $url,
            'DB_CONNECTION' => 'sqlite',
            // A DB_URL wins over DB_DATABASE, and would keep the app's database.
            'DB_URL' => '',
            'DB_DATABASE' => "database/{$environment}.sqlite",
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
            'REDIS_PREFIX' => "{$redisPrefix}{$environment}_",
        ];
    }

    private static function value(string $content, string $key): ?string
    {
        return preg_match('/^' . $key . '=(.*)$/m', $content, $match) === 1 ? $match[1] : null;
    }

    /**
     * Replaces the key's line where the file sets it, else where it is commented out,
     * else appends it. Dotenv keeps the first definition, so the set one goes first.
     */
    private static function set(string $content, string $key, string $value): string
    {
        $line = "{$key}={$value}";

        foreach (['/^' . $key . '=.*$/m', '/^#[ \t]*' . $key . '=.*$/m'] as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return (string) preg_replace($pattern, $line, $content, 1);
            }
        }

        return rtrim($content, "\n") . "\n{$line}\n";
    }

}
