const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  
  const pages = [
    { url: 'http://localhost:8080/sales?tab=resumen', name: 'current_sales_resumen.png' },
    { url: 'http://localhost:8080/sales?tab=departamentos', name: 'current_sales_dept.png' },
    { url: 'http://localhost:8080/sales?tab=productos', name: 'current_sales_prod.png' },
    { url: 'http://localhost:8080/sales?tab=sucursales', name: 'current_sales_branch.png' },
    { url: 'http://localhost:8080/sales?tab=detalle', name: 'current_sales_historial.png' },
    { url: 'http://localhost:8080/pos', name: 'current_pos.png' },
    { url: 'http://localhost:8080/pos_returns', name: 'current_pos_returns.png' },
    { url: 'http://localhost:8080/customers', name: 'current_customers.png' }
  ];

  for (const p of pages) {
    await page.goto(p.url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(__dirname, '../captures', p.name), fullPage: false });
    console.log('Captured:', p.name);
  }
  await browser.close();
})();

