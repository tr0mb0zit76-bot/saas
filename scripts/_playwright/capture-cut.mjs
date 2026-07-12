import { chromium } from "playwright";

const out = "storage/app/landing-shots";
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto("http://saas.local/", { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(1200);

const info = await page.evaluate(() => {
  const brand = document.getElementById("traklo-brand");
  const clips = [...document.querySelectorAll(".showcase-rail *")]
    .filter((el) => el.style.clipPath)
    .map((el) => ({
      cls: el.className.slice(0, 80),
      clip: el.style.clipPath,
      pad: el.style.paddingLeft,
    }));
  const shot = document.querySelector("#features .showcase-rail__shot");
  const toc = document.querySelector(".traklo-chapters");
  return {
    brandLeft: brand?.getBoundingClientRect().left ?? null,
    tocRight: toc?.getBoundingClientRect().right ?? null,
    shotLeft: shot?.getBoundingClientRect().left ?? null,
    clips,
  };
});
console.log(JSON.stringify(info, null, 2));

const handle = await page.locator("#features").elementHandle();
const box = await handle.boundingBox();
const y = await page.evaluate(() => window.scrollY);
await page.evaluate(({ top, y }) => window.scrollTo(0, y + top + 20), { top: box.y, y });
await page.waitForTimeout(500);
await page.screenshot({ path: `${out}/base-cut-start.png`, fullPage: false });
await page.evaluate(
  ({ top, y, h }) => window.scrollTo(0, y + top + h * 0.4),
  { top: box.y, y, h: box.height - 900 },
);
await page.waitForTimeout(500);
await page.screenshot({ path: `${out}/base-cut-mid.png`, fullPage: false });
await browser.close();
