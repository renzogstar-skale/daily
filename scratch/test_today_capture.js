const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    
    await page.goto('http://localhost:8080/today', { waitUntil: 'load', timeout: 15000 });
    await page.waitForTimeout(1500);

    const kpiVal = await page.locator('#kpi-sales-val').innerText();
    const kpiMargin = await page.locator('#kpi-margin-val').innerText();
    const kpiProfit = await page.locator('#kpi-profit-val').innerText();
    const kpiTx = await page.locator('#card-kpi-tx .today-exec-kpi-value, #kpi-tx-val').innerText().catch(() => 'N/A');

    console.log('Today rendered successfully:');
    console.log('Ventas Netas:', kpiVal);
    console.log('Margen Bruto:', kpiMargin);
    console.log('Utilidad Bruta:', kpiProfit);
    console.log('Transacciones:', kpiTx);

    const artifactDir = 'C:/Users/renzo/.gemini/antigravity/brain/9cb989c6-05ae-4a92-bfbb-e019aaa0dfd8/captures';
    await page.screenshot({ path: path.join(artifactDir, 'today_con_ventas_lleno.png'), fullPage: false });
    console.log('Screenshot saved: today_con_ventas_lleno.png');

    await browser.close();
})();

