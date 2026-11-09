const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  const b1 = await page.locator('#salesExecutiveDashboard .ded-nav-tabs-bar').boundingBox();
  console.log('1. Tablero de Ventas tabs bar:', b1);

  await page.locator('.ded-nav-tab:has-text("Rendimiento por departamento"):visible').click();
  await page.waitForTimeout(500);
  const b2 = await page.locator('#deptExecutiveDashboard .ded-nav-tabs-bar').boundingBox();
  console.log('2. Rendimiento tabs bar:', b2);

  await page.locator('.ded-nav-tab:has-text("Productos"):visible').click();
  await page.waitForTimeout(500);
  const b3 = await page.locator('#productCatalogDashboard .ded-nav-tabs-bar').boundingBox();
  console.log('3. Productos tabs bar:', b3);

  await browser.close();
})();

