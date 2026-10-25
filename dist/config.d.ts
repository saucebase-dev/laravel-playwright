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
export declare function laravelServer(options?: LaravelServerOptions): {
    command: string;
    url: string;
    cwd: string;
    env: {
        APP_ENV: string;
        PHP_CLI_SERVER_WORKERS: string;
        APP_CONFIG_CACHE: string;
        APP_ROUTES_CACHE: string;
        APP_EVENTS_CACHE: string;
    };
    reuseExistingServer: boolean;
};
/**
 * Whether a request goes to the app: the base URL's host or one of its subdomains,
 * on any port, so subdomain multi-tenancy is covered. Third parties are not, so the
 * test header never turns their requests into failing CORS preflights.
 */
export declare function isAppUrl(url: URL, baseURL: string | undefined): boolean;
