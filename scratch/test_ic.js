const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');
(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    await page.goto('http://localhost:8080/inventory-control', { waitUntil: 'networkidle' });
    const rows = await page.$$eval('#ic-items tbody tr', trs => trs.map(t => t.innerText.trim()));
    console.log('IC Items count:', rows.length);
    console.log('Sample rows:', rows.slice(0, 3));
    const emptyMsg = await page.evaluate(() => document.body.innerText.includes('Sin datos de matriz en esta sucursal.'));
    console.log('Empty msg still present?:', emptyMsg);
    await browser.close();
})();

