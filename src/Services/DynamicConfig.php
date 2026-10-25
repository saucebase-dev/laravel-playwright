<?php declare(strict_types=1);

namespace Saucebase\LaravelPlaywright\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Usually, E2E tests call multiple routes in a single test.
 * Sometimes, we need to change a config in the Laravel app for a single test.
 *
 * State is kept per test: the Playwright fixture sends a test id in the
 * X-Playwright-Test header, on its own calls and on the browser's requests.
 * Each id gets its own file under storage/framework/playwright, applied only to
 * requests carrying that id, and deleted when that test calls tearDown.
 * Parallel tests therefore never see or remove each other's state, and a request
 * without the header (the developer's browser, a queue worker) sees none.
 */
class DynamicConfig
{

    const HEADER = 'X-Playwright-Test';

    /**
     * Used for time traveling
     */
    const KEY_TRAVEL = 'app.e2e.travel';

    /**
     * Used to run a function in the boot method of a service provider
     */
    const KEY_BOOT_FUNCTIONS = 'app.e2e.bootFunctions';

    /**
     * The test id of a request, or null when it has none or it is not a safe file name.
     */
    public static function id(Request $request): ?string
    {
        $id = $request->header(self::HEADER);

        return is_string($id) && self::isValidId($id) ? $id : null;
    }

    /**
     * The test id of a request that writes state; 400 without one.
     */
    public static function requireId(Request $request): string
    {
        return self::id($request) ?? abort(400, 'Missing or invalid ' . self::HEADER . ' header.');
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
    }

    public static function directory(): string
    {
        return storage_path('framework/playwright');
    }

    public static function path(?string $id): ?string
    {
        if ($id === null || !self::isValidId($id)) {
            return null;
        }

        return self::directory() . DIRECTORY_SEPARATOR . $id . '.json';
    }

    public static function load(?string $id): void
    {
        $data = self::getAll($id);

        foreach ($data as $key => $value) {
            config([$key => $value]);
        }
        self::loadTime($data);
        self::loadBootFunctions($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function loadBootFunctions(array $data): void
    {
        $functions = $data[self::KEY_BOOT_FUNCTIONS] ?? null;
        if (!is_array($functions)) {
            return;
        }

        foreach ($functions as $function) {
            if (is_callable($function)) {
                $function();
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function loadTime(array $data): void
    {
        $time = $data[self::KEY_TRAVEL] ?? null;
        if (!is_string($time)) {
            return;
        }
        $time = Carbon::parse($time);
        \Carbon\Carbon::setTestNow($time);
        \Carbon\CarbonImmutable::setTestNow($time);
    }

    public static function set(string $id, string $key, mixed $value): void
    {
        $file = self::path($id);
        if ($file === null) {
            throw new \InvalidArgumentException("Invalid test id [{$id}].");
        }

        $data = self::getAll($id);
        $data[$key] = $value;

        if (!is_dir(self::directory())) {
            @mkdir(self::directory(), 0755, true);
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    public static function get(?string $id, string $key, mixed $default = null): mixed
    {
        return self::getAll($id)[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getAll(?string $id): array
    {
        $file = self::path($id);
        $content = $file === null ? false : @file_get_contents($file);
        $json = $content === false ? null : json_decode($content, true);

        /** @var array<string, mixed> */
        return is_array($json) ? $json : [];
    }

    public static function delete(?string $id): void
    {
        $file = self::path($id);
        if ($file !== null && file_exists($file)) {
            unlink($file);
        }
    }

}
