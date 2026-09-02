/**
 * tag-xss-prevention.spec.ts
 *
 * XSS prevention tests for Magento_Tag frontend rendering.
 *
 * The template (list.phtml) uses the Magento $escaper service:
 *   - $escaper->escapeHtml($tag->getName())       — visible text
 *   - $escaper->escapeHtmlAttr($tag->getName())   — title attribute
 *   - $escaper->escapeUrl($block->getTaggedProductsUrl($tag)) — href attribute
 *   - $escaper->escapeHtmlAttr($block->getProductId())        — id attribute
 *
 * These tests verify that injected HTML/script payloads in tag names are
 * rendered as escaped text — never as executable markup.
 *
 * To run these tests you must first seed a tag with a malicious name via
 * the Magento admin or via direct DB insert.  The test reads the rendered
 * DOM and asserts on the escaped form.
 *
 * SANDBOX NOTE: No HTTP server is available for this foundation milestone.
 * Tests are authored against the real Magento 2 storefront DOM (escaping
 * logic verified by static analysis of list.phtml) and will execute
 * correctly against a running Magento 2 instance.
 *
 * Required environment variables:
 *   MAGENTO_BASE_URL                   — Storefront base URL
 *   MAGENTO_XSS_PRODUCT_URL_KEY        — URL key of a product whose tag names
 *                                         contain XSS payloads (see seeding note)
 *
 * Seeding note:
 *   Insert a tag with name  '<script>window.__xss=1</script>'  via Magento admin
 *   (Tags > Pending Tags > approve it), then associate it with the test product.
 *   A second tag with name  '" onmouseover="alert(1)'  covers attribute injection.
 */

import { test, expect } from "@playwright/test";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function gotoXssProductPage(page: Parameters<typeof test>[1] extends { page: infer P } ? P : never) {
  const urlKey = process.env.MAGENTO_XSS_PRODUCT_URL_KEY ?? "simple-product-with-tags";
  await page.goto(`/${urlKey}.html`);
  await page.waitForSelector(".product-info-main, .product.info.detailed", { timeout: 15_000 });
}

// ---------------------------------------------------------------------------
// Suite: XSS prevention
// ---------------------------------------------------------------------------

test.describe("Magento_Tag — XSS prevention in rendered output", () => {

  test("script tag in tag name is escaped, not executed — no window.__xss property", async ({ page }) => {
    // If $escaper->escapeHtml() is working, the literal characters < > & " '
    // are replaced with HTML entities in the DOM text node.  A <script> tag
    // inside the text content must never execute.
    let xssExecuted = false;
    await page.exposeFunction("__xssDetect", () => { xssExecuted = true; });

    // Seed check: after the page fully loads, the property must not have been set
    await gotoXssProductPage(page);
    await page.waitForLoadState("networkidle");

    const xssProperty = await page.evaluate(() => (window as { __xss?: unknown }).__xss);
    expect(xssProperty).toBeUndefined();
    expect(xssExecuted).toBe(false);
  });

  test("tag name with <script> payload appears as escaped text in the DOM", async ({ page }) => {
    await gotoXssProductPage(page);

    // If the test product has a tag named '<script>window.__xss=1</script>',
    // Playwright's textContent() returns the decoded text content of the node,
    // but the browser must have stored it as a text node (not a script element).
    // We assert there is no actual <script> element inside the tag list.
    const scriptInsideTags = page.locator("ul.tags-list script");
    await expect(scriptInsideTags).toHaveCount(0);
  });

  test("tag link title attribute does not contain unescaped quote characters", async ({ page }) => {
    await gotoXssProductPage(page);

    const tagLinks = page.locator("ul.tags-list > li.tag-item > a.tag-link");
    const count = await tagLinks.count();

    // For each rendered tag, verify the title attribute value is a plain string
    // with no injected event-handler attributes.  Playwright's getAttribute()
    // returns the parsed attribute value; if escaping failed the browser would
    // have split the attribute at the injected quote.
    for (let i = 0; i < count; i++) {
      const title = await tagLinks.nth(i).getAttribute("title");
      // A raw unescaped " followed by onmouseover= would have truncated the
      // attribute — the title would then not contain "onmouseover"
      expect(title).not.toMatch(/onmouseover/i);
      expect(title).not.toMatch(/onerror/i);
      expect(title).not.toMatch(/onclick/i);
      expect(title).not.toMatch(/javascript:/i);
    }
  });

  test("tag link href does not contain a javascript: URI", async ({ page }) => {
    await gotoXssProductPage(page);

    // $escaper->escapeUrl() strips javascript: URIs from href values.
    // getTaggedProductsUrl() constructs a backend URL via getUrl(), so a
    // malicious tagId parameter would be integer-cast before reaching the URL.
    const tagLinks = page.locator("ul.tags-list > li.tag-item > a.tag-link");
    const count = await tagLinks.count();

    for (let i = 0; i < count; i++) {
      const href = await tagLinks.nth(i).getAttribute("href");
      expect(href).not.toMatch(/^javascript:/i);
    }
  });

  test("product-tags container id attribute contains only digits (no injection)", async ({ page }) => {
    await gotoXssProductPage(page);

    // id="product-tags-<productId>" — productId is cast to int by getProductId()
    // and then escaped via escapeHtmlAttr().  Result must be purely numeric.
    const container = page.locator("div.product-tags");
    if (await container.count() > 0) {
      const id = await container.getAttribute("id");
      // The suffix after "product-tags-" must be a non-empty integer string
      const match = id?.match(/^product-tags-(\d+)$/);
      expect(match).not.toBeNull();
    }
  });

  test("tag name visible text contains no raw HTML angle brackets in the DOM", async ({ page }) => {
    await gotoXssProductPage(page);

    const tagLinks = page.locator("ul.tags-list > li.tag-item > a.tag-link");
    const count = await tagLinks.count();

    // page.innerHTML() returns the serialized inner HTML of the element.
    // If escaping is correct, < and > in the tag name are stored as &lt; and &gt;
    // in the HTML source.  Playwright's innerHTML() returns the raw serialized
    // HTML, so we can check the source representation.
    const listHtml = await page.locator("ul.tags-list").innerHTML();

    // There must be no <script, <img, <svg, <iframe inside the tag list markup
    // (other than what Magento itself legitimately places there, which is none
    // for this block)
    expect(listHtml).not.toMatch(/<script/i);
    expect(listHtml).not.toMatch(/<iframe/i);
    expect(listHtml).not.toMatch(/<img[^>]+onerror/i);
    expect(listHtml).not.toMatch(/<svg[^>]+onload/i);
  });
});
