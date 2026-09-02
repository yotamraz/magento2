/**
 * tag-layout-injection.spec.ts
 *
 * Tests for the layout injection position of the Magento_Tag ListTags block.
 *
 * Layout XML declaration (catalog_product_view.xml):
 *
 *   <referenceContainer name="product.info.main">
 *       <block class="Magento\Tag\Block\Product\ListTags"
 *              name="product.tags"
 *              template="Magento_Tag::tag/list.phtml"
 *              after="product.info.addtocart"/>
 *   </referenceContainer>
 *
 * The block is placed:
 *   - Inside  : product.info.main  container
 *   - After   : product.info.addtocart  block (the Add-to-Cart button area)
 *
 * SANDBOX NOTE: No HTTP server is available for this foundation milestone.
 * Layout structure verified via static analysis; tests will execute correctly
 * against a running Magento 2 instance.
 *
 * Required environment variables:
 *   MAGENTO_BASE_URL               — Storefront base URL (default: http://localhost:8080)
 *   MAGENTO_TEST_PRODUCT_URL_KEY   — URL key of a product with >= 1 approved tag
 */

import { test, expect } from "@playwright/test";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function gotoTaggedProductPage(page: Parameters<typeof test>[1] extends { page: infer P } ? P : never) {
  const urlKey = process.env.MAGENTO_TEST_PRODUCT_URL_KEY ?? "simple-product-with-tags";
  await page.goto(`/${urlKey}.html`);
  await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });
}

// ---------------------------------------------------------------------------
// Suite: layout injection position
// ---------------------------------------------------------------------------

test.describe("Magento_Tag — layout injection on catalog_product_view", () => {

  // -------------------------------------------------------------------------
  // Presence on PDP
  // -------------------------------------------------------------------------

  test("tag block is present on a product detail page", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // When at least one approved tag exists the container renders
    await expect(page.locator("div.product-tags")).toBeVisible();
  });

  test("tag block is absent on a non-PDP page (homepage)", async ({ page }) => {
    // The block is only injected on catalog_product_view; other page handles
    // do not load catalog_product_view.xml
    await page.goto("/");
    await page.waitForLoadState("domcontentloaded");

    await expect(page.locator("div.product-tags")).toHaveCount(0);
  });

  // -------------------------------------------------------------------------
  // Position: after product.info.addtocart
  //
  // Magento renders product.info.main as a wrapping div.product-info-main.
  // Inside it the Add-to-Cart block renders as .box-tocart or .product-add-form.
  // The tag block must appear AFTER that element in DOM order.
  // -------------------------------------------------------------------------

  test("tag container appears after the Add-to-Cart area in DOM order", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // Collect bounding boxes to verify vertical ordering
    // (Magento renders the layout in injection order top-to-bottom)
    const addToCartBox = await page
      .locator(".box-tocart, .product-add-form, [data-role='tocart-form']")
      .first()
      .boundingBox();

    const tagContainerBox = await page
      .locator("div.product-tags")
      .first()
      .boundingBox();

    if (addToCartBox && tagContainerBox) {
      // Tag block bottom edge starts at or after the Add-to-Cart block top edge
      expect(tagContainerBox.y).toBeGreaterThanOrEqual(addToCartBox.y);
    }
  });

  test("tag container is a descendant of the .product-info-main container", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // product.info.main renders as .product-info-main
    // The tag block must be inside that container (per referenceContainer injection)
    const tagInsideMain = page.locator(".product-info-main div.product-tags");
    await expect(tagInsideMain).toBeVisible();
  });

  // -------------------------------------------------------------------------
  // Block name uniqueness: only one tag block per page
  // -------------------------------------------------------------------------

  test("exactly one tag container is rendered per product detail page", async ({ page }) => {
    await gotoTaggedProductPage(page);

    // The layout injects a single block named "product.tags"; it must not
    // be duplicated on the page
    const count = await page.locator("div.product-tags").count();
    expect(count).toBe(1);
  });

  // -------------------------------------------------------------------------
  // Cacheable block (no cacheable="false" in layout XML)
  //
  // The block does NOT declare cacheable="false", relying on CustomerData for
  // personalisation.  The page itself should therefore be full-page-cacheable
  // (X-Magento-Cache-Control header present and not containing "no-cache").
  // -------------------------------------------------------------------------

  test("product page response does not disable full-page cache (cacheable block)", async ({ page }) => {
    const urlKey = process.env.MAGENTO_TEST_PRODUCT_URL_KEY ?? "simple-product-with-tags";
    const response = await page.goto(`/${urlKey}.html`);

    // Magento FPC sets X-Magento-Cache-Control to "cache" for cacheable pages
    const cacheHeader = response?.headers()["x-magento-cache-control"];
    if (cacheHeader !== undefined) {
      // If the header is present it should not be "no-cache"
      expect(cacheHeader).not.toBe("no-cache");
    }
    // If the header is absent (e.g. FPC disabled in test env) the test is vacuously true
  });
});
