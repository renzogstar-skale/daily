const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    
    // Inspect stockoverview
    await page.goto('http://localhost:8080/stockoverview');
    const stockTables = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('table')).map((t, idx) => {
            const tb = t.querySelector('tbody');
            const rowCount = tb ? tb.querySelectorAll('tr').length : 0;
            const id = t.id || t.className;
            const parent = t.closest('.modal, .card, section')?.className || '';
            return { idx, id, rowCount, parent };
        });
    });
    console.log('Stockoverview tables:', stockTables);
    
    // Inspect sales
    await page.goto('http://localhost:8080/sales');
    const salesTables = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('table')).map((t, idx) => {
            const tb = t.querySelector('tbody');
            const rowCount = tb ? tb.querySelectorAll('tr').length : 0;
            const id = t.id || t.className;
            const parent = t.closest('.modal, .card, section')?.className || '';
            return { idx, id, rowCount, parent };
        });
    });
    console.log('Sales tables:', salesTables);

    await browser.close();
})();

