const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
  
  const consoleMessages = [];
  page.on('console', msg => consoleMessages.push(`[${msg.type()}] ${msg.text()}`));
  page.on('pageerror', err => consoleMessages.push(`[PAGEERROR] ${err.message}`));

  const response = await page.goto('http://localhost:8080/kpis', { waitUntil: 'domcontentloaded', timeout: 60000 });
  console.log('HTTP Status:', response.status());
  console.log('Page Title:', await page.title());
  
  await page.waitForTimeout(2000);
  console.log('Console messages:', consoleMessages);
  
  const titleText = await page.locator('.dsm-title').first().innerText().catch(() => 'none');
  console.log('Title text:', titleText);

  await page.screenshot({ path: 'captures/check_kpis_panel.png', fullPage: true });
  console.log('Screenshot saved to captures/check_kpis_panel.png');
  await browser.close();
})();
