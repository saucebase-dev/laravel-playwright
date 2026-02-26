import { test as i } from "@playwright/test";
const u = i.extend({
  laravelBaseUrl: [void 0, { option: !0 }],
  laravelSecret: [void 0, { option: !0 }],
  laravel: async ({ laravelBaseUrl: s, laravelSecret: t, baseURL: a, request: e }, n) => {
    const r = s || a + "/playwright", c = new l(r, e, t);
    await n(c), await c.tearDown();
  }
});
class l {
  constructor(t, a, e = void 0) {
    this.baseUrl = t, this.request = a, this.secret = e;
  }
  async call(t, a = {}) {
    const e = this.baseUrl.replace(/\/$/, "") + t, n = {};
    this.secret && (n["X-Playwright-Secret"] = this.secret);
    const r = await this.request.post(e, { data: a, headers: n });
    if (r.status() !== 200)
      throw new Error(`
                Failed to call Laravel ${t}.
                Status: ${r.status()}
                Response: ${await r.text()}
            `);
    return await r.json();
  }
  async artisan(t, a = []) {
    return await this.call("/artisan", { command: t, parameters: a });
  }
  async truncate(t = []) {
    return await this.call("/truncate", { connections: t });
  }
  async factory(t, a = {}, e) {
    return await this.call("/factory", { model: t, count: e, attrs: a });
  }
  async query(t, a = [], e = {}) {
    const { connection: n = null, unprepared: r = !1 } = e;
    if (r && a.length > 0)
      throw new Error("Cannot use unprepared with bindings");
    return await this.call("/query", {
      query: t,
      bindings: a,
      connection: n,
      unprepared: r
    });
  }
  async select(t, a = {}, e = {}) {
    const { connection: n = null } = e;
    return await this.call("/select", { query: t, bindings: a, connection: n });
  }
  async callFunction(t, a = []) {
    return await this.call("/function", { function: t, args: a });
  }
  /**
   * Sets a laravel config value until tearDown is called (or the test ends)
   */
  async config(t, a) {
    return await this.call("/dynamicConfig", { key: t, value: a });
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
  l as Laravel,
  u as test
};
