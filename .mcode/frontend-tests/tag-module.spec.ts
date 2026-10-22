import { test, expect } from "@playwright/test";

test.describe("Magento_Tag module storefront surface", () => {
  test("home page loads with 200 and title", async ({ page }) => {
    const res = await page.goto("/index.php/");
    expect(res!.status()).toBe(200);
    await expect(page).toHaveTitle(/Home page/);
    await expect(page.locator(".logo").first()).toBeVisible();
  });

  for (const path of ["/index.php/tag/", "/index.php/tag/customer/"]) {
    test(`${path} renders the normal storefront 404 page`, async ({ page }) => {
      const res = await page.goto(path);
      expect(res!.status()).toBe(404);
      await expect(page).toHaveTitle(/404 Not Found/);
      await expect(page.locator("h1.page-title .base")).toHaveText(/Whoops, our bad/);
      await expect(page.getByText("The page you requested was not found")).toBeVisible();
      const body = await page.content();
      expect(body).not.toMatch(/Fatal error|Stack trace|Exception|Report ID/i);
    });
  }
});
