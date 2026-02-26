import { APIRequestContext } from '@playwright/test';
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
}
interface LaravelFixtures {
    laravel: Laravel;
}
export declare const test: import('playwright/test').TestType<import('playwright/test').PlaywrightTestArgs & import('playwright/test').PlaywrightTestOptions & LaravelFixtures & LaravelOptions, import('playwright/test').PlaywrightWorkerArgs & import('playwright/test').PlaywrightWorkerOptions>;
export declare class Laravel {
    private baseUrl;
    private request;
    private secret;
    constructor(baseUrl: string, request: APIRequestContext, secret?: string | undefined);
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
export {};
