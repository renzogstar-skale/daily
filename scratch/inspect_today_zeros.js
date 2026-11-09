const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto('http://localhost:8080/today', { waitUntil: 'networkidle' });

    // Look for text with "$0" or "0" or "ventas netas"
    const text = await page.evaluate(() => {
        const cards = [];
        document.querySelectorAll('.card, .kpi-card, .col-md-3, .col-lg-3, .col-xl-3, .dairy-kpi, .today-kpi, div').forEach(el => {
            const t = el.innerText || '';
            if (t.includes('Venta') && (t.includes('$0') || t.includes('$ 0') || t.includes('0%'))) {
                cards.push(t.substring(0, 150));
            }
        });
        return cards.slice(0, 5);
    });
    console.log('Zero sales matches in /today:', text);

    // Let's get all KPI cards / indicators in /today
    const allKpis = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, .h2, .h3, .h4, .h5, .display-4, .kpi-value, .stat-value')).map(el => {
            return { tag: el.tagName, class: el.className, text: el.innerText.trim(), parent: el.parentElement?.innerText.trim().substring(0, 100) };
        });
    });
    console.log('All KPIs in /today:', allKpis.slice(0, 20));

    await browser.close();
})();

