import { chromium } from "playwright";

const out = "storage/app/landing-shots";
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.setDefaultTimeout(90000);
await page.goto("http://saas.local/", { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(1500);
await page.screenshot({ path: `${out}/01-hero.png`, fullPage: false });
await page.locator("#features-pro").scrollIntoViewIfNeeded();
await page.waitForTimeout(600);
await page.screenshot({ path: `${out}/02-pro-start.png`, fullPage: false });
const handle = await page.locator("#features-pro").elementHandle();
const box = await handle.boundingBox();
if (box) {
  const y0 = await page.evaluate(() => window.scrollY);
  const start = y0 + box.y;
  for (const t of [0.2, 0.4, 0.6, 0.85]) {
    await page.evaluate(({ start, h, t }) => window.scrollTo(0, start + h * t - 160), { start, h: box.height, t });
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${out}/03-pro-${String(t).replace(".", "p")}.png`, fullPage: false });
  }
}
await browser.close();
console.log("done");
