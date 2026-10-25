import {APIRequestContext, test as playwrightTest} from '@playwright/test';
import {isAppUrl} from './config.ts';

export {isAppUrl, laravelServer, type LaravelServerOptions} from './config.ts';

/** Ties a request to the test that made it, so its state applies to it alone. */
export const TEST_HEADER = 'X-Playwright-Test';

export interface LaravelOptions {
    /**
     * The base URL to the endpoints
     * @default <playwright-base-url>/playwright
     */
    laravelBaseUrl: string | undefined;
    /**
     * Optional secret token sent as X-Playwright-Secret header
     */
    laravelSecret: string | undefined;
    /**
     * Which browser requests go to the app and carry the test header.
     * @default the base URL's host and its subdomains
     */
    laravelIsAppUrl: ((url: URL) => boolean) | undefined;
}

interface LaravelFixtures {
    laravel: Laravel;
    /** Unique per test; the server keeps each test's config, time and boot functions apart by it. */
    laravelTestId: string;
}

export const test = playwrightTest.extend<LaravelFixtures & LaravelOptions>({

    laravelBaseUrl: [undefined, {option: true}],
    laravelSecret: [undefined, {option: true}],
    laravelIsAppUrl: [undefined, {option: true}],

    laravelTestId: async ({}, use) => {
        await use(crypto.randomUUID());
    },

    /**
     * Adds the test header to the browser's requests to the app only. `extraHTTPHeaders`
     * would send it to third parties too, turning their requests into CORS preflights
     * that fail. `fallback()` lets any route registered later still handle the request.
     */
    context: async ({ context, baseURL, laravelIsAppUrl, laravelTestId }, use) => {
        const matches = laravelIsAppUrl ?? ((url: URL) => isAppUrl(url, baseURL));
        await context.route(matches, (route) => route.fallback({
            headers: {...route.request().headers(), [TEST_HEADER]: laravelTestId},
        }));
        await use(context);
    },

    laravel: async ({ laravelBaseUrl, laravelSecret, laravelTestId, baseURL, request }, use) => {
        const baseUrl = laravelBaseUrl || baseURL + '/playwright'
        const laravel = new Laravel(baseUrl, request, laravelSecret, laravelTestId);
        await use(laravel);
        await laravel.tearDown();
    }

})

export class Laravel {

    constructor(
        private baseUrl: string,
        private request: APIRequestContext,
        private secret: string | undefined = undefined,
        public readonly testId: string = crypto.randomUUID(),
    ) {}

    async call<T = unknown>(endpoint: string, data: object = {}) : Promise<T> {
        const url = this.baseUrl.replace(/\/$/, '') + endpoint;
        const headers: Record<string, string> = {'Accept': 'application/json', [TEST_HEADER]: this.testId};
        if (this.secret) headers['X-Playwright-Secret'] = this.secret;
        const response = await this.request.post(url, {data, headers});
        if (response.status() !== 200) {
            throw new Error(`
                Failed to call Laravel ${endpoint}.
                Status: ${response.status()}
                Response: ${await response.text()}
            `);
        }

        return await response.json() as T;
    }

    async artisan(command: string, parameters: string[] = []) {
        return await this.call<{code: number, output: string}>('/artisan', {command, parameters});
    }

    async truncate(connections: (string|null)[] = []) {
        return await this.call('/truncate', {connections});
    }

    async factory(
        model: string,
        attrs?: Record<string, unknown>,
        count?: undefined
    ): Promise<Record<string, unknown>>;
    async factory(
        model: string,
        attrs: Record<string, unknown>,
        count: number
    ): Promise<Record<string, unknown>[]>;
    async factory(
        model: string,
        attrs: Record<string, unknown> = {},
        count?: number
    ): Promise<Record<string, unknown> | Record<string, unknown>[]> {
        return await this.call('/factory', {model, count, attrs});
    }

    async query(
        query: string,
        bindings: unknown[] = [],
        options: {
            connection?: string | null,
            unprepared?: boolean
        } = {}
    ) {

        const { connection = null, unprepared = false } = options;

        if (unprepared && bindings.length > 0) {
            throw new Error('Cannot use unprepared with bindings');
        }

        return await this.call<{
            success: boolean
        }>('/query', {
            query,
            bindings,
            connection,
            unprepared
        });

    }

    async select(
        query: string,
        bindings: Record<string, unknown> = {},
        options: {
            connection?: string | null,
        } = {}
    ) {
        const { connection = null } = options;
        return await this.call<Record<string, unknown>[]>('/select', {query, bindings, connection});
    }

    async callFunction<T = unknown>(func: string, args: unknown[]|Record<string, unknown> = []) {
        return await this.call<T>('/function', {function: func, args});
    }

    /**
     * Sets a laravel config value until tearDown is called (or the test ends)
     */
    async config(key: string, value: unknown) {
        return await this.call('/dynamicConfig', {key, value});
    }

    /**
     * Travel to a specific time
     * ex: travel('2021-01-01 00:00:00')
     */
    async travel(to: string) {
        return await this.call('/travel', {to});
    }

    async registerBootFunction(func: string) {
        return await this.call('/registerBootFunction', {function: func});
    }

    async tearDown() {
        return await this.call('/tearDown');
    }

}
