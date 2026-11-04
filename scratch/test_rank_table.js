const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    await page.goto("http://localhost:8080/stockoverview", { waitUntil: "networkidle", timeout: 20000 });
    
    const rows = await page.$$eval('#dsm-rank-tbody tr', trs => {
        return trs.map(tr => {
            const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.replace(/\n+/g, ' ').trim());
            return cells;
        });
    });

    console.log("=== RENDERED DEPARTMENTS TABLE (" + rows.length + " rows) ===");
    rows.forEach(r => console.log(r.slice(0, 7).join(' | ')));

    const donutCenter = await page.$eval('#dsm-donut-center-val', el => el.innerText);
    const donutBadge = await page.$eval('#dsm-donut-center-sub', el => el.innerText);
    console.log("\nDonut Center:", donutCenter, "|", donutBadge);

    // Capture screenshot of the distribution section
    const donutCard = await page.$('.dsm-dept-level2-grid');
    if (donutCard) {
        await donutCard.screenshot({ path: "C:/Users/renzo/.gemini/antigravity/brain/9cb989c6-05ae-4a92-bfbb-e019aaa0dfd8/captures/distribucion_departamentos_repartido.png" });
        console.log("Screenshot saved to captures/distribucion_departamentos_repartido.png");
    }

    await browser.close();
})();
