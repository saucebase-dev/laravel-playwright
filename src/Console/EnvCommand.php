<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Console;

use Dotenv\Dotenv;
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

    private const BACKUP = 'Back up and replace';

    private const REPLACE = 'Replace';

    private const KEEP = 'Keep';

    protected $signature = 'playwright:env
                            {environment=e2e : The environment the suite runs in; writes .env.<environment>}
                            {--url=http://localhost:8100 : APP_URL for the suite\'s server}
                            {--force : Replace an existing file without asking}
                            {--backup : Back up an existing file, then replace it, without asking}';

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

        $source = collect([base_path('.env'), base_path('.env.example')])->first(fn (string $path) => $files->exists($path));
        if ($source === null) {
            $this->components->error('There is no .env or .env.example to copy.');

            return self::FAILURE;
        }

        $original = $files->get($source);
        $prefix = trim(self::value($original, 'REDIS_PREFIX') ?? '', '"\'');
        $content = $original;

        foreach ($this->overrides($environment, $url, $prefix, $original) as $key => $value) {
            $content = self::set($content, $key, $value);
        }

        if (self::sameDatabase($original, $content)) {
            $this->components->error(".env.{$environment} would use the app's own database, and the suite wipes it. Point it at another database.");

            return self::FAILURE;
        }

        if ($files->exists($target)) {
            $action = $this->actionForExisting(".env.{$environment}");

            if ($action === self::KEEP) {
                $this->components->info(".env.{$environment} already exists and was kept.");

                return self::SUCCESS;
            }

            if ($action === self::BACKUP) {
                $files->copy($target, "{$target}.backup");
                $this->components->info("Previous file saved as .env.{$environment}.backup.");
            }
        }

        $files->put($target, $content);
        $this->components->info(".env.{$environment} written from " . basename($source) . '.');

        return self::SUCCESS;
    }

    private function actionForExisting(string $name): string
    {
        if ($this->option('backup')) {
            return self::BACKUP;
        }

        if ($this->option('force')) {
            return self::REPLACE;
        }

        if (!$this->input->isInteractive()) {
            return self::KEEP;
        }

        $answer = $this->choice("{$name} already exists. What should happen to it?", [self::BACKUP, self::REPLACE, self::KEEP], self::BACKUP);

        return is_string($answer) ? $answer : self::KEEP;
    }

    /**
     * Its own server and database and drivers that need no service of their own, then
     * whatever `.env` sets for this environment: E2E_MAIL_MAILER becomes MAIL_MAILER.
     *
     * @return array<string, string>
     */
    private function overrides(string $environment, string $url, string $redisPrefix, string $content): array
    {
        $custom = self::customValues($content, $environment);

        return [
            'APP_ENV' => $environment,
            'APP_URL' => $url,
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
            'REDIS_PREFIX' => "{$redisPrefix}{$environment}_",
            ...self::database($environment, $custom),
            ...$custom,
        ];
    }

    /**
     * @param  array<string, string>  $custom
     * @return array<string, string>
     */
    private static function database(string $environment, array $custom): array
    {
        $url = $custom['DB_URL'] ?? null;
        $connection = trim($custom['DB_CONNECTION'] ?? ($url !== null ? (string) strtok(trim($url, '"\''), ':') : 'sqlite'), '"\'');

        if ($url !== null) {
            return ['DB_CONNECTION' => $connection];
        }

        if ($connection !== 'sqlite') {
            return ['DB_URL' => ''];
        }

        $database = trim($custom['DB_DATABASE'] ?? "database/{$environment}.sqlite", '"\'');

        return [
            'DB_CONNECTION' => 'sqlite',
            // A DB_URL wins over DB_DATABASE, so this one replaces the app's. Its options reach a
            // config that hardcodes them: parallel workers share this file, and a transaction that
            // reads then writes fails at once if another is writing, unless it locks up front.
            'DB_URL' => "\"sqlite:///{$database}?transaction_mode=IMMEDIATE&busy_timeout=10000\"",
            'DB_DATABASE' => $database,
        ];
    }

    /**
     * Raw, so quotes reach the new file as written. A ${VARIABLE} is resolved: Playwright's
     * dotenv reads this file without resolving them, and the server inherits what it read.
     *
     * @return array<string, string>
     */
    private static function customValues(string $content, string $environment): array
    {
        $prefix = strtoupper(str_replace('-', '_', $environment)) . '_';
        preg_match_all('/^' . preg_quote($prefix, '/') . '([A-Z0-9_]+)=(.*)$/m', $content, $matches, PREG_SET_ORDER);
        $resolved = Dotenv::parse($content);

        return collect($matches)->mapWithKeys(fn (array $match) => [
            $match[1] => str_contains($match[2], '${') ? self::quoted((string) $resolved[$prefix . $match[1]]) : $match[2],
        ])->all();
    }

    private static function quoted(string $value): string
    {
        return preg_match('/^[\w.\/:@-]*$/', $value) === 1 ? $value : '"' . addcslashes($value, '"\\') . '"';
    }

    /** Whether two env files reach one database, read as Laravel would: variables resolved, a URL first. */
    private static function sameDatabase(string $first, string $second): bool
    {
        return self::databaseIdentity($first) === self::databaseIdentity($second);
    }

    /** @return list<string> */
    private static function databaseIdentity(string $content): array
    {
        $env = Dotenv::parse($content);
        $connection = $env['DB_CONNECTION'] ?? 'sqlite';
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? null;
        $database = $env['DB_DATABASE'] ?? ($connection === 'sqlite' ? 'database/database.sqlite' : '');

        if (($env['DB_URL'] ?? '') !== '') {
            // As Laravel's own parser: parse_url refuses `sqlite:///` with no host.
            $parts = parse_url((string) preg_replace('#^(sqlite3?):///#', '$1://null/', (string) $env['DB_URL'])) ?: [];
            $connection = $parts['scheme'] ?? $connection;
            $host = $parts['host'] ?? $host;
            $port = isset($parts['port']) ? (string) $parts['port'] : $port;
            $database = ltrim($parts['path'] ?? $database, '/');
        }

        if ($connection === 'sqlite') {
            return ['sqlite', ltrim(str_replace(base_path(), '', $database), '/')];
        }

        return [
            in_array($host, ['localhost', '::1'], true) ? '127.0.0.1' : $host,
            $port ?? (['pgsql' => '5432', 'sqlsrv' => '1433'][$connection] ?? '3306'),
            $database,
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
                // A callback, so a `$1` in a password stays as written.
                return (string) preg_replace_callback($pattern, fn () => $line, $content, 1);
            }
        }

        return rtrim($content, "\n") . "\n{$line}\n";
    }

}
