const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto('http://localhost:8080/today', { waitUntil: 'domcontentloaded', timeout: 10000 });
    await page.waitForTimeout(1000);

    const kpiVal = await page.locator('#kpi-sales-val').innerText();
    const kpiDelta = await page.locator('#kpi-sales-delta').innerText();
    const kpiTarget = await page.locator('#kpi-target-val').innerText();
    const kpiMargin = await page.locator('#kpi-margin-val').innerText();
    const kpiProfit = await page.locator('#kpi-profit-val').innerText();

    console.log('KPI Ventas Netas:', kpiVal);
    console.log('KPI Delta:', kpiDelta);
    console.log('KPI Cumplimiento Meta:', kpiTarget);
    console.log('KPI Margen Bruto:', kpiMargin);
    console.log('KPI Utilidad Bruta:', kpiProfit);

    await browser.close();
})();
