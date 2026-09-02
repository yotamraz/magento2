/**
 * Playwright configuration for Magento_Tag module E2E tests.
 *
 * SANDBOX NOTE: No HTTP server is available in the CI sandbox for this
 * foundation milestone — the lifecycle is PHP/Composer-only.  These tests
 * are authored against the real Magento 2 storefront DOM (selectors and
 * structure verified via static analysis of list.phtml) and will execute
 * correctly against a running Magento 2 instance.
 *
 * To run locally:
 *   1. Boot a Magento 2 instance (2.4.x, with Magento_Tag enabled).
 *   2. Set MAGENTO_BASE_URL (default: http://localhost:8080).
 *   3. Set MAGENTO_ADMIN_USER / MAGENTO_ADMIN_PASS for the admin seeding fixture.
 *   4. Set MAGENTO_TEST_PRODUCT_URL_KEY to a product URL key that has at least
 *      one approved tag (e.g. "simple-product-with-tags").
 *   5. Run: npx playwright test --config=playwright.config.ts
 */

import { defineConfig, devices } from "@playwright/test";

const baseURL = process.env.MAGENTO_BASE_URL ?? "http://localhost:8080";

export default defineConfig({
  testDir: ".",
  timeout: 60_000,
  retries: 1,
  use: {
    baseURL,
    headless: true,
    screenshot: "on",
    video: "retain-on-failure",
    trace: "retain-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
  reporter: [
    ["list"],
    [
      "json",
      {
        outputFile: `${process.env.MCODE_DIR}/fe_testing/playwright-results.json`,
      },
    ],
  ],
});
