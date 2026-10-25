import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { isAppUrl, laravelServer } from '../src/config.ts';

function appWith(files: Record<string, string>): string {
    const dir = mkdtempSync(join(tmpdir(), 'laravel-playwright-'));
    Object.entries(files).forEach(([file, content]) => writeFileSync(join(dir, file), content));
    return dir;
}

const e2eApp = (appUrl = 'http://localhost:8100') => appWith({ '.env.e2e': `APP_ENV=e2e\nAPP_URL=${appUrl}\n` });

test('laravelServer migrates, then serves the e2e environment at its APP_URL', () => {
    const server = laravelServer({ cwd: e2eApp() });

    assert.equal(
        server.command,
        'php artisan migrate --env=e2e --force && php artisan serve --env=e2e --host=127.0.0.1 --port=8100 --no-reload',
    );
    assert.equal(server.reuseExistingServer, !process.env.CI);
});

test('laravelServer waits on APP_URL, the host the app answers on (a tenant app 404s on any other)', () => {
    assert.equal(laravelServer({ cwd: e2eApp('http://app.localhost:9100') }).url, 'http://app.localhost:9100');
});

test('laravelServer serves APP_URL port 80 when it has none', () => {
    assert.match(laravelServer({ cwd: e2eApp('http://localhost') }).command, /--port=80 /);
});

test('laravelServer hands APP_ENV and the worker count to the PHP server', () => {
    const server = laravelServer({ cwd: e2eApp(), workers: 8 });

    assert.equal(server.env.APP_ENV, 'e2e');
    assert.equal(server.env.PHP_CLI_SERVER_WORKERS, '8');
});

test('laravelServer keeps the developer\'s config, route and event caches out of the e2e server', () => {
    // A cached config skips every env file, so the e2e server (and its migrate)
    // would run on the developer's database; cached routes lack the e2e endpoints.
    const { env } = laravelServer({ cwd: e2eApp() });

    assert.equal(env.APP_CONFIG_CACHE, 'bootstrap/cache/e2e/config.php');
    assert.equal(env.APP_ROUTES_CACHE, 'bootstrap/cache/e2e/routes.php');
    assert.equal(env.APP_EVENTS_CACHE, 'bootstrap/cache/e2e/events.php');
});

test('laravelServer takes its options', () => {
    const cwd = appWith({ '.env.testing': 'APP_URL=http://localhost:9000\n' });
    const server = laravelServer({ env: 'testing', host: '0.0.0.0', cwd, migrate: false });

    assert.equal(server.command, 'php artisan serve --env=testing --host=0.0.0.0 --port=9000 --no-reload');
    assert.equal(server.cwd, cwd);
    assert.equal(server.env.APP_ENV, 'testing');
});

test('laravelServer refuses to start without the env file, which would fall back to .env', () => {
    assert.throws(() => laravelServer({ cwd: appWith({ '.env': 'APP_URL=http://localhost\n' }) }), /php artisan playwright:env/);
});

test('laravelServer needs an http APP_URL, which is all artisan serve speaks', () => {
    assert.throws(() => laravelServer({ cwd: appWith({ '.env.e2e': 'APP_ENV=e2e\n' }) }), /APP_URL/);
    assert.throws(() => laravelServer({ cwd: e2eApp('https://app.test') }), /http:/);
});

test('laravelServer refuses to inherit another environment loaded into the Playwright process', (t) => {
    const cwd = e2eApp();
    process.env.APP_ENV = 'local';
    t.after(() => delete process.env.APP_ENV);

    // `import 'dotenv/config'` loads `.env`: the server would inherit its DB_* as real
    // environment variables, which beat `.env.e2e`.
    assert.throws(() => laravelServer({ cwd }), /APP_ENV=local/);

    process.env.APP_ENV = 'e2e';
    assert.doesNotThrow(() => laravelServer({ cwd }));
});

test('isAppUrl matches the base host and its subdomains, on any port', () => {
    const base = 'http://localhost:8100';

    assert.equal(isAppUrl(new URL('http://localhost:8100/dashboard'), base), true);
    assert.equal(isAppUrl(new URL('http://acme.localhost:8100/'), base), true);
    assert.equal(isAppUrl(new URL('https://app.test/'), 'https://app.test'), true);
    assert.equal(isAppUrl(new URL('https://acme.app.test/'), 'https://app.test'), true);
});

test('isAppUrl leaves third parties alone', () => {
    assert.equal(isAppUrl(new URL('https://js.stripe.com/v3'), 'https://app.test'), false);
    assert.equal(isAppUrl(new URL('https://evilapp.test/'), 'https://app.test'), false);
    assert.equal(isAppUrl(new URL('https://app.test.evil.com/'), 'https://app.test'), false);
});

test('isAppUrl matches nothing without a base URL', () => {
    assert.equal(isAppUrl(new URL('http://localhost/'), undefined), false);
});
