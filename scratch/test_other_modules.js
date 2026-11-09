const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  
  for (const url of ['http://localhost:8080/pos_returns', 'http://localhost:8080/customers']) {
    await page.goto(url, { waitUntil: 'networkidle' });
    const panels = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('.card, [class*="panel"], [class*="kpi"], [class*="card"]')).slice(0, 5).map(el => ({
        cls: el.className,
        bg: window.getComputedStyle(el).backgroundColor,
        bgImg: window.getComputedStyle(el).backgroundImage.substring(0, 30),
        border: window.getComputedStyle(el).border
      }));
    });
    console.log(url, panels);
  }
  await browser.close();
})();

