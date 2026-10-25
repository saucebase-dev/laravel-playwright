import { APIRequestContext } from '@playwright/test';
export { isAppUrl, laravelServer, type LaravelServerOptions } from './config.ts';
/** Ties a request to the test that made it, so its state applies to it alone. */
export declare const TEST_HEADER = "X-Playwright-Test";
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
export declare const test: import('playwright/test').TestType<import('playwright/test').PlaywrightTestArgs & import('playwright/test').PlaywrightTestOptions & LaravelFixtures & LaravelOptions, import('playwright/test').PlaywrightWorkerArgs & import('playwright/test').PlaywrightWorkerOptions>;
export declare class Laravel {
    private baseUrl;
    private request;
    private secret;
    readonly testId: string;
    constructor(baseUrl: string, request: APIRequestContext, secret?: string | undefined, testId?: string);
    call<T = unknown>(endpoint: string, data?: object): Promise<T>;
    artisan(command: string, parameters?: string[]): Promise<{
        code: number;
        output: string;
    }>;
    truncate(connections?: (string | null)[]): Promise<unknown>;
    factory(model: string, attrs?: Record<string, unknown>, count?: undefined): Promise<Record<string, unknown>>;
    factory(model: string, attrs: Record<string, unknown>, count: number): Promise<Record<string, unknown>[]>;
    query(query: string, bindings?: unknown[], options?: {
        connection?: string | null;
        unprepared?: boolean;
    }): Promise<{
        success: boolean;
    }>;
    select(query: string, bindings?: Record<string, unknown>, options?: {
        connection?: string | null;
    }): Promise<Record<string, unknown>[]>;
    callFunction<T = unknown>(func: string, args?: unknown[] | Record<string, unknown>): Promise<T>;
    /**
     * Sets a laravel config value until tearDown is called (or the test ends)
     */
    config(key: string, value: unknown): Promise<unknown>;
    /**
     * Travel to a specific time
     * ex: travel('2021-01-01 00:00:00')
     */
    travel(to: string): Promise<unknown>;
    registerBootFunction(func: string): Promise<unknown>;
    tearDown(): Promise<unknown>;
}
