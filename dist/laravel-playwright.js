import { test as h } from "@playwright/test";
import { existsSync as p, readFileSync as u } from "node:fs";
import { join as v } from "node:path";
import { parseEnv as w } from "node:util";
function m(s = {}) {
  const { env: t = "e2e", host: e = "127.0.0.1", workers: a = 4, cwd: r = process.cwd(), migrate: n = !0 } = s, o = v(r, `.env.${t}`);
  if (!p(o))
    throw new Error(`laravelServer: create .env.${t} in ${r}, with its own database, before running the suite.`);
  if (process.env.APP_ENV !== void 0 && process.env.APP_ENV !== t)
    throw new Error(
      `laravelServer: the Playwright process has APP_ENV=${process.env.APP_ENV}, so another env file was loaded and its values would override .env.${t}. Load .env.${t} instead (dotenv config({ path: '.env.${t}' })) or none.`
    );
  const i = w(u(o, "utf8")).APP_URL;
  if (!i)
    throw new Error(`laravelServer: set APP_URL in .env.${t}, e.g. http://localhost:8100.`);
  const c = new URL(i);
  if (c.protocol !== "http:")
    throw new Error(`laravelServer: APP_URL in .env.${t} must be http: (artisan serve has no TLS), got ${i}.`);
  return {
    command: [
      n && `php artisan migrate --env=${t} --force`,
      // --no-reload: without it artisan serve ignores PHP_CLI_SERVER_WORKERS.
      `php artisan serve --env=${t} --host=${e} --port=${c.port || 80} --no-reload`
    ].filter(Boolean).join(" && "),
    url: c.origin,
    cwd: r,
    env: {
      APP_ENV: t,
      PHP_CLI_SERVER_WORKERS: String(a),
      // A cached config skips every env file, so the developer's `config:cache`
      // would hand this server (and its migrate) their database. Cached routes
      // were built without the e2e endpoints. Laravel reads these paths from the
      // environment, so the e2e server gets caches of its own.
      APP_CONFIG_CACHE: `bootstrap/cache/${t}/config.php`,
      APP_ROUTES_CACHE: `bootstrap/cache/${t}/routes.php`,
      APP_EVENTS_CACHE: `bootstrap/cache/${t}/events.php`
    },
    reuseExistingServer: !process.env.CI
  };
}
function d(s, t) {
  if (!t)
    return !1;
  const e = new URL(t).hostname;
  return s.hostname === e || s.hostname.endsWith(`.${e}`);
}
const l = "X-Playwright-Test", g = h.extend({
  laravelBaseUrl: [void 0, { option: !0 }],
  laravelSecret: [void 0, { option: !0 }],
  laravelIsAppUrl: [void 0, { option: !0 }],
  laravelTestId: async ({}, s) => {
    await s(crypto.randomUUID());
  },
  /**
   * Adds the test header to the browser's requests to the app only. `extraHTTPHeaders`
   * would send it to third parties too, turning their requests into CORS preflights
   * that fail. `fallback()` lets any route registered later still handle the request.
   */
  context: async ({ context: s, baseURL: t, laravelIsAppUrl: e, laravelTestId: a }, r) => {
    const n = e ?? ((o) => d(o, t));
    await s.route(n, (o) => o.fallback({
      headers: { ...o.request().headers(), [l]: a }
    })), await r(s);
  },
  laravel: async ({ laravelBaseUrl: s, laravelSecret: t, laravelTestId: e, baseURL: a, request: r }, n) => {
    const o = s || a + "/playwright", i = new f(o, r, t, e);
    await n(i), await i.tearDown();
  }
});
class f {
  constructor(t, e, a = void 0, r = crypto.randomUUID()) {
    this.baseUrl = t, this.request = e, this.secret = a, this.testId = r;
  }
  async call(t, e = {}) {
    const a = this.baseUrl.replace(/\/$/, "") + t, r = { Accept: "application/json", [l]: this.testId };
    this.secret && (r["X-Playwright-Secret"] = this.secret);
    const n = await this.request.post(a, { data: e, headers: r });
    if (n.status() !== 200)
      throw new Error(`
                Failed to call Laravel ${t}.
                Status: ${n.status()}
                Response: ${await n.text()}
            `);
    return await n.json();
  }
  async artisan(t, e = []) {
    return await this.call("/artisan", { command: t, parameters: e });
  }
  async truncate(t = []) {
    return await this.call("/truncate", { connections: t });
  }
  async factory(t, e = {}, a) {
    return await this.call("/factory", { model: t, count: a, attrs: e });
  }
  async query(t, e = [], a = {}) {
    const { connection: r = null, unprepared: n = !1 } = a;
    if (n && e.length > 0)
      throw new Error("Cannot use unprepared with bindings");
    return await this.call("/query", {
      query: t,
      bindings: e,
      connection: r,
      unprepared: n
    });
  }
  async select(t, e = {}, a = {}) {
    const { connection: r = null } = a;
    return await this.call("/select", { query: t, bindings: e, connection: r });
  }
  async callFunction(t, e = []) {
    return await this.call("/function", { function: t, args: e });
  }
  /**
   * Sets a laravel config value until tearDown is called (or the test ends)
   */
  async config(t, e) {
    return await this.call("/dynamicConfig", { key: t, value: e });
  }
  /**
   * Travel to a specific time
   * ex: travel('2021-01-01 00:00:00')
   */
  async travel(t) {
    return await this.call("/travel", { to: t });
  }
  async registerBootFunction(t) {
    return await this.call("/registerBootFunction", { function: t });
  }
  async tearDown() {
    return await this.call("/tearDown");
  }
}
export {
  f as Laravel,
  l as TEST_HEADER,
  d as isAppUrl,
  m as laravelServer,
  g as test
};
