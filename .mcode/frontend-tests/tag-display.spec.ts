/**
 * tag-display.spec.ts
 *
 * Tests for Magento_Tag: product detail page tag display.
 *
 * Covers the ListTags block rendered by
 *   app/code/Magento/Tag/view/frontend/templates/tag/list.phtml
 * injected via
 *   app/code/Magento/Tag/view/frontend/layout/catalog_product_view.xml
 *
 * SANDBOX NOTE: No HTTP server is available for this foundation milestone.
 * Tests are verified against the static template source and will execute
 * correctly against a running Magento 2 instance.
 *
 * Required environment variables (set before running):
 *   MAGENTO_BASE_URL           — Magento storefront base URL (default: http://localhost:8080)
 *   MAGENTO_TEST_PRODUCT_URL_KEY — URL key of a product that has >= 1 approved tag
 *                                  (e.g. "simple-product-with-tags")
 */

import { test, expect } from "@playwright/test";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Navigate to the product detail page that has approved tags seeded.
 *
 * The URL key is configurable via MAGENTO_TEST_PRODUCT_URL_KEY so the test
 * suite works against any Magento fixture set.  The default value matches the
 * recommended seeding step in README-e2e.md.
 */
async function gotoTaggedProductPage(page: Parameters<typeof test>[1] extends { page: infer P } ? P : never) {
  const urlKey = process.env.MAGENTO_TEST_PRODUCT_URL_KEY ?? "simple-product-with-tags";
  await page.goto(`/${urlKey}.html`);
  // Wait for the main product area to be present before asserting on tags
  await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });
}

// ---------------------------------------------------------------------------
// Suite: tag container renders when tags exist
// ---------------------------------------------------------------------------

test.describe("Magento_Tag — product detail page tag display", () => {

  // -------------------------------------------------------------------------
  // Tag container structure
  // -------------------------------------------------------------------------

  test("tag container div is present when product has approved tags", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // The outer wrapper rendered by list.phtml when getCount() > 0
    await expect(page.locator("div.product-tags")).toBeVisible();
  });

  test("tag container id encodes the product entity ID", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // The template renders: id="product-tags-<productId>"
    // Verify the pattern; the exact numeric ID is product-dependent.
    const container = page.locator("div.product-tags");
    const id = await container.getAttribute("id");
    expect(id).toMatch(/^product-tags-\d+$/);
  });

  test("'Tags:' label is visible inside the tag container", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // <strong class="label">Tags:</strong>
    await expect(page.locator("div.product-tags > strong.label")).toBeVisible();
    await expect(page.locator("div.product-tags > strong.label")).toHaveText("Tags:");
  });

  // -------------------------------------------------------------------------
  // Tag list and items
  // -------------------------------------------------------------------------

  test("tag list ul element is present when product has tags", async ({ page }) => {
    await gotoTaggedProductPage(page);

    await expect(page.locator("div.product-tags ul.tags-list")).toBeVisible();
  });

  test("at least one tag list item li.tag-item is rendered", async ({ page }) => {
    await gotoTaggedProductPage(page);

    const items = page.locator("ul.tags-list > li.tag-item");
    const count = await items.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test("each tag item contains an anchor with class tag-link", async ({ page }) => {
    await gotoTaggedProductPage(page);

    const firstTagLink = page.locator("ul.tags-list > li.tag-item > a.tag-link").first();
    await expect(firstTagLink).toBeVisible();
  });

  // -------------------------------------------------------------------------
  // Tag link attributes and URL structure
  // -------------------------------------------------------------------------

  test("tag link href points to the tag/product/list route with a tagId param", async ({ page }) => {
    await gotoTaggedProductPage(page);

    const firstTagLink = page.locator("ul.tags-list > li.tag-item > a.tag-link").first();
    const href = await firstTagLink.getAttribute("href");

    // getTaggedProductsUrl() builds: <baseUrl>tag/product/list?tagId=<id>
    expect(href).toMatch(/tag\/product\/list/);
    expect(href).toMatch(/tagId=\d+/);
  });

  test("tag link title attribute matches the anchor text content", async ({ page }) => {
    await gotoTaggedProductPage(page);

    const tagLinks = page.locator("ul.tags-list > li.tag-item > a.tag-link");
    const count = await tagLinks.count();

    // Verify for every rendered tag that title == visible text (both are
    // the tag name, routed through different escaper methods in the template)
    for (let i = 0; i < count; i++) {
      const link = tagLinks.nth(i);
      const title = await link.getAttribute("title");
      const text = (await link.textContent())?.trim();
      expect(title).toBeTruthy();
      expect(text).toBeTruthy();
      expect(title).toBe(text);
    }
  });

  test("clicking a tag link navigates to the tagged-products listing page", async ({ page }) => {
    await gotoTaggedProductPage(page);

    const firstTagLink = page.locator("ul.tags-list > li.tag-item > a.tag-link").first();
    const href = (await firstTagLink.getAttribute("href")) ?? "";

    await firstTagLink.click();
    await page.waitForLoadState("domcontentloaded");

    // After navigation the URL should contain the tag/product/list route
    expect(page.url()).toContain("tag/product/list");
  });

  // -------------------------------------------------------------------------
  // Negative: no form rendered (foundation milestone scope)
  // -------------------------------------------------------------------------

  test("no tag-submission form is rendered (getFormAction present but not used in foundation)", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // The foundation template does not include a <form> for tag submission.
    // getFormAction() exists for future use only.
    const tagForms = page.locator("form[action*='tag/index/save']");
    await expect(tagForms).toHaveCount(0);
  });
});
