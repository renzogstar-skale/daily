const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  
  const consoleMessages = [];
  page.on('console', msg => consoleMessages.push(`[${msg.type()}] ${msg.text()}`));
  page.on('pageerror', err => consoleMessages.push(`[PAGEERROR] ${err.message}`));

  const response = await page.goto('http://localhost:8080/sales', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);
  console.log('HTTP Status:', response.status());
  console.log('Page Title:', await page.title());
  console.log('Console messages:', consoleMessages);
  
  const titleText = await page.locator('.ded-title, h1, .dairy-page-title').first().innerText().catch(() => 'none');
  console.log('Title text:', titleText);

  await page.screenshot({ path: 'captures/check_sales_load.png' });
  console.log('Screenshot saved to captures/check_sales_load.png');
  await browser.close();
})();

