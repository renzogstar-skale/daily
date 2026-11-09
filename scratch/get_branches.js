const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  const data = await page.evaluate(() => {
    const opts = document.querySelectorAll('select[name="branch_id"] option');
    return Array.from(opts).map(o => ({ id: o.value, name: o.innerText.trim() }));
  });
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();

