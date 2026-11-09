const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  const t1 = await page.locator('#salesExecutiveDashboard .ded-title').boundingBox();
  console.log('1. Tablero de Ventas (on load):', t1);

  // Click Rendimiento
  await page.locator('.ded-nav-tab:has-text("Rendimiento por departamento"):visible').click();
  await page.waitForTimeout(500);
  const t2 = await page.locator('#deptExecutiveDashboard .ded-title').boundingBox();
  console.log('2. Rendimiento (after click):', t2);

  // Click Productos
  await page.locator('.ded-nav-tab:has-text("Productos"):visible').click();
  await page.waitForTimeout(500);
  const t3 = await page.locator('#productCatalogDashboard .prod-dash-title').boundingBox();
  console.log('3. Productos (after click):', t3);

  await browser.close();
})();

