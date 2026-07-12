import { chromium } from "playwright";

const out = "storage/app/landing-shots";
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.setDefaultTimeout(90000);
await page.goto("http://saas.local/", { waitUntil: "domcontentloaded", timeout: 90000 });
await page.waitForTimeout(1200);

async function captureSection(id, prefix) {
  const handle = await page.locator(`#${id}`).elementHandle();
  const box = await handle.boundingBox();
  if (!box) return;
  // Pin section top to viewport top so rail progress ≈ 0
  await page.evaluate((top) => window.scrollTo(0, top), box.y + (await page.evaluate(() => window.scrollY)));
  await page.waitForTimeout(400);
  const info = await page.evaluate((sid) => {
    const el = document.getElementById(sid);
    const sticky = el?.querySelector(".showcase-rail");
    const counter = el?.querySelector(".traklo-display")?.closest("div")?.parentElement?.innerText?.slice(0, 80);
    return {
      sectionHeight: el?.offsetHeight ?? 0,
      railHeight: sticky?.offsetHeight ?? 0,
      hasRail: !!sticky,
      counterHint: counter ?? "",
      scenePanels: el?.querySelectorAll(".showcase-rail__panel").length ?? 0,
    };
  }, id);
  console.log(prefix, info);
  await page.screenshot({ path: `${out}/${prefix}-start.png`, fullPage: false });

  const y0 = await page.evaluate(() => window.scrollY);
  for (const t of [0.35, 0.7]) {
    await page.evaluate(({ y0, h, t }) => window.scrollTo(0, y0 + h * t), {
      y0,
      h: box.height - 900,
      t,
    });
    await page.waitForTimeout(450);
    await page.screenshot({
      path: `${out}/${prefix}-${String(t).replace(".", "p")}.png`,
      fullPage: false,
    });
  }
}

await captureSection("features", "base");
await captureSection("features-pro", "pro");
await captureSection("features-enterprise", "ent");

await browser.close();
console.log("done");
