import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { parseEnv } from 'node:util';

export interface LaravelServerOptions {
    /** The environment to serve; `.env.<env>` is loaded, and its APP_URL is where the app is served. @default 'e2e' */
    env?: string;
    /** The address to bind. @default '127.0.0.1' */
    host?: string;
    /** PHP_CLI_SERVER_WORKERS, so parallel tests are not served one request at a time. @default 4 */
    workers?: number;
    /** The application root. @default process.cwd() */
    cwd?: string;
    /**
     * Migrate before serving. An app that reads the database on every request (a
     * tenant lookup at boot) cannot be migrated over HTTP while its tables are missing.
     * @default true
     */
    migrate?: boolean;
}

/**
 * A Playwright `webServer` entry serving the app in its own environment.
 *
 * The suite then never touches the developer's own server or database, whatever
 * serves those (Herd, Valet, Sail, Docker or `artisan serve`): `.env.<env>` names
 * its own database and APP_URL, and nothing else is shared.
 */
export function laravelServer(options: LaravelServerOptions = {}) {
    const { env = 'e2e', host = '127.0.0.1', workers = 4, cwd = process.cwd(), migrate = true } = options;
    const file = join(cwd, `.env.${env}`);

    // Without it Laravel falls back to `.env`, and the suite would wipe the
    // developer's own database.
    if (!existsSync(file)) {
        throw new Error(`laravelServer: create .env.${env} in ${cwd}, with its own database, before running the suite.`);
    }

    // The server inherits this process's environment, and real environment variables
    // beat any env file. Another env file loaded here (`import 'dotenv/config'` loads
    // `.env`) would hand the server the developer's database.
    if (process.env.APP_ENV !== undefined && process.env.APP_ENV !== env) {
        throw new Error(
            `laravelServer: the Playwright process has APP_ENV=${process.env.APP_ENV}, so another env file was loaded and its values would override .env.${env}. ` +
            `Load .env.${env} instead (dotenv config({ path: '.env.${env}' })) or none.`,
        );
    }

    // Waiting on APP_URL rather than the bound address: an app that tells tenants
    // apart by host (subdomain tenancy) answers 404 on any other host.
    const appUrl = parseEnv(readFileSync(file, 'utf8')).APP_URL;
    if (!appUrl) {
        throw new Error(`laravelServer: set APP_URL in .env.${env}, e.g. http://localhost:8100.`);
    }
    const url = new URL(appUrl);
    if (url.protocol !== 'http:') {
        throw new Error(`laravelServer: APP_URL in .env.${env} must be http: (artisan serve has no TLS), got ${appUrl}.`);
    }

    return {
        command: [
            migrate && `php artisan migrate --env=${env} --force`,
            // --no-reload: without it artisan serve ignores PHP_CLI_SERVER_WORKERS.
            `php artisan serve --env=${env} --host=${host} --port=${url.port || 80} --no-reload`,
        ].filter(Boolean).join(' && '),
        url: url.origin,
        cwd,
        env: {
            APP_ENV: env,
            PHP_CLI_SERVER_WORKERS: String(workers),
            // A cached config skips every env file, so the developer's `config:cache`
            // would hand this server (and its migrate) their database. Cached routes
            // were built without the e2e endpoints. Laravel reads these paths from the
            // environment, so the e2e server gets caches of its own.
            APP_CONFIG_CACHE: `bootstrap/cache/${env}/config.php`,
            APP_ROUTES_CACHE: `bootstrap/cache/${env}/routes.php`,
            APP_EVENTS_CACHE: `bootstrap/cache/${env}/events.php`,
        },
        reuseExistingServer: !process.env.CI,
    };
}

/**
 * Whether a request goes to the app: the base URL's host or one of its subdomains,
 * on any port, so subdomain multi-tenancy is covered. Third parties are not, so the
 * test header never turns their requests into failing CORS preflights.
 */
export function isAppUrl(url: URL, baseURL: string | undefined): boolean {
    if (!baseURL) {
        return false;
    }

    const host = new URL(baseURL).hostname;

    return url.hostname === host || url.hostname.endsWith(`.${host}`);
}
