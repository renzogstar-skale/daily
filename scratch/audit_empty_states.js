const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    const routes = [
        '/today',
        '/alerts',
        '/kpis',
        '/mrp',
        '/suppliers',
        '/branches',
        '/merchandise',
        '/stockoverview',
        '/scan',
        '/locations/map',
        '/transfers',
        '/kardex',
        '/inventory-control',
        '/inventory',
        '/reconciliation',
        '/sales',
        '/pos',
        '/dispatch',
        '/returns',
        '/schedule',
        '/tasks',
        '/quality',
        '/waste',
        '/loss-prevention',
        '/devices',
        '/reports',
        '/admin/audit-log'
    ];

    console.log("Auditing routes for empty states / zero items...");
    for (const r of routes) {
        try {
            const resp = await page.goto('http://localhost:8080' + r, { waitUntil: 'networkidle', timeout: 15000 });
            const status = resp.status();
            
            // Search for empty tables, "sin datos", "no hay", "(0)" in tabs/badges
            const emptyIndicators = await page.evaluate(() => {
                const text = document.body.innerText;
                const findings = [];
                
                // check table tbodies with 0 rows or with "sin..."
                document.querySelectorAll('tbody').forEach((tb, i) => {
                    const rows = tb.querySelectorAll('tr');
                    if (rows.length === 0) {
                        findings.push(`tbody #${i} is empty (0 tr)`);
                    } else if (rows.length === 1 && (rows[0].innerText.toLowerCase().includes('sin ') || rows[0].innerText.toLowerCase().includes('no hay') || rows[0].innerText.toLowerCase().includes('no data'))) {
                        findings.push(`tbody #${i} single empty row: "${rows[0].innerText.trim()}"`);
                    }
                });

                // check tab items or pills with (0)
                document.querySelectorAll('button, a, .nav-link, .badge').forEach(el => {
                    if (el.innerText.includes('(0)') || el.innerText.trim() === '0') {
                        findings.push(`Element with zero: "${el.innerText.trim()}" (${el.tagName})`);
                    }
                });

                return findings;
            });

            console.log(`\n=== ROUTE: ${r} (Status: ${status}) ===`);
            if (emptyIndicators.length === 0) {
                console.log("  [OK] No empty tables or (0) elements found.");
            } else {
                emptyIndicators.forEach(ind => console.log("  [EMPTY] " + ind));
            }
        } catch (e) {
            console.log(`\n=== ROUTE: ${r} ERROR: ${e.message} ===`);
        }
    }

    await browser.close();
})();

