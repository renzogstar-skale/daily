const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  await page.goto('http://localhost:8080/sales?tab=departamentos', { waitUntil: 'networkidle' });
  const h1 = await page.locator('#deptExecutiveDashboard .ded-header').boundingBox();
  const hl = await page.locator('#deptExecutiveDashboard .ded-header-left').boundingBox();
  const hr = await page.locator('#deptExecutiveDashboard .ded-header-right').boundingBox();
  console.log('deptExecutiveDashboard .ded-header:', h1);
  console.log('deptExecutiveDashboard .ded-header-left:', hl);
  console.log('deptExecutiveDashboard .ded-header-right:', hr);

  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  const hRes = await page.locator('#salesExecutiveDashboard .ded-header').boundingBox();
  const hlRes = await page.locator('#salesExecutiveDashboard .ded-header-left').boundingBox();
  const hrRes = await page.locator('#salesExecutiveDashboard .ded-header-right').boundingBox();
  console.log('salesExecutiveDashboard .ded-header:', hRes);
  console.log('salesExecutiveDashboard .ded-header-left:', hlRes);
  console.log('salesExecutiveDashboard .ded-header-right:', hrRes);

  await page.goto('http://localhost:8080/sales?tab=productos', { waitUntil: 'networkidle' });
  const hProd = await page.locator('#productCatalogDashboard .ded-header').boundingBox();
  const hlProd = await page.locator('#productCatalogDashboard .ded-header-left').boundingBox();
  const hrProd = await page.locator('#productCatalogDashboard .ded-header-right').boundingBox();
  console.log('productCatalogDashboard .ded-header:', hProd);
  console.log('productCatalogDashboard .ded-header-left:', hlProd);
  console.log('productCatalogDashboard .ded-header-right:', hrProd);

  await browser.close();
})();

