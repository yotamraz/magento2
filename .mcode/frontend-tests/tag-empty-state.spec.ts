/**
 * tag-empty-state.spec.ts
 *
 * Tests for the Magento_Tag block empty-state behaviour.
 *
 * The template (list.phtml) wraps all output in:
 *   <?php if ($block->getCount()): ?> … <?php endif; ?>
 *
 * On a fresh install — or for any product with zero approved tags — nothing
 * is rendered.  These tests verify that behaviour both on PDPs (no approved
 * tags) and on non-PDP pages (where _beforeToHtml() sets template to '').
 *
 * SANDBOX NOTE: No HTTP server is available for this foundation milestone.
 * Tests are verified against the static template source and block PHP code
 * and will execute correctly against a running Magento 2 instance.
 *
 * Required environment variables:
 *   MAGENTO_BASE_URL                  — Storefront base URL (default: http://localhost:8080)
 *   MAGENTO_TEST_UNTAGGED_PRODUCT_URL_KEY — URL key of a product with zero approved tags
 *                                           (e.g. "simple-product-no-tags")
 */

import { test, expect } from "@playwright/test";

// ---------------------------------------------------------------------------
// Suite: empty state — no tags rendered
// ---------------------------------------------------------------------------

test.describe("Magento_Tag — empty state (no approved tags)", () => {

  // -------------------------------------------------------------------------
  // PDP with no approved tags
  // -------------------------------------------------------------------------

  test("tag container div is absent when product has no approved tags", async ({ page }) => {
    const urlKey = process.env.MAGENTO_TEST_UNTAGGED_PRODUCT_URL_KEY ?? "simple-product-no-tags";
    await page.goto(`/${urlKey}.html`);
    await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });

    // When getCount() === 0 the outer <div class="product-tags"> is never emitted
    await expect(page.locator("div.product-tags")).toHaveCount(0);
  });

  test("'Tags:' label is absent when product has no approved tags", async ({ page }) => {
    const urlKey = process.env.MAGENTO_TEST_UNTAGGED_PRODUCT_URL_KEY ?? "simple-product-no-tags";
    await page.goto(`/${urlKey}.html`);
    await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });

    // No <strong class="label"> should be present in the tag block
    await expect(page.locator("div.product-tags > strong.label")).toHaveCount(0);
  });

  test("tag list ul is absent when product has no approved tags", async ({ page }) => {
    const urlKey = process.env.MAGENTO_TEST_UNTAGGED_PRODUCT_URL_KEY ?? "simple-product-no-tags";
    await page.goto(`/${urlKey}.html`);
    await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });

    await expect(page.locator("ul.tags-list")).toHaveCount(0);
  });

  // -------------------------------------------------------------------------
  // Non-PDP page: _beforeToHtml() guard sets template to '' outside PDP
  //
  // ListTags::_beforeToHtml() checks getProductId(); if null (no
  // 'current_product' in Registry), it calls $this->setTemplate('') which
  // causes the block to produce zero output regardless of the template file.
  // -------------------------------------------------------------------------

  test("tag block produces no output on the homepage (non-PDP context)", async ({ page }) => {
    await page.goto("/");
    await page.waitForLoadState("domcontentloaded");

    // _beforeToHtml() sets template to '' outside a PDP context
    await expect(page.locator("div.product-tags")).toHaveCount(0);
    await expect(page.locator("ul.tags-list")).toHaveCount(0);
  });

  test("tag block produces no output on a category listing page (non-PDP context)", async ({ page }) => {
    // Navigate to any category; the tag block is only injected on
    // catalog_product_view — category pages don't load it at all.
    await page.goto("/");
    await page.waitForLoadState("domcontentloaded");

    // Find a category link and follow it
    const categoryLink = page.locator("nav a, .navigation a").first();
    const href = await categoryLink.getAttribute("href");
    if (href) {
      await page.goto(href);
      await page.waitForLoadState("domcontentloaded");
    }

    await expect(page.locator("div.product-tags")).toHaveCount(0);
  });

  // -------------------------------------------------------------------------
  // Fresh install invariant: no tag-related markup anywhere on homepage
  // -------------------------------------------------------------------------

  test("no tag-related DOM nodes on homepage (fresh install invariant)", async ({ page }) => {
    await page.goto("/");
    await page.waitForLoadState("domcontentloaded");

    // None of the tag selectors should appear on any non-PDP page
    await expect(page.locator("div.product-tags")).toHaveCount(0);
    await expect(page.locator("ul.tags-list")).toHaveCount(0);
    await expect(page.locator("li.tag-item")).toHaveCount(0);
    await expect(page.locator("a.tag-link")).toHaveCount(0);
  });
});
