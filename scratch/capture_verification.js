const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const artifactDir = 'C:/Users/renzo/.gemini/antigravity/brain/9cb989c6-05ae-4a92-bfbb-e019aaa0dfd8/captures';

    const captures = [
        { url: '/mrp', file: 'mrp_lleno.png' },
        { url: '/sales', file: 'sales_devoluciones_lleno.png' },
        { url: '/inventory-control', file: 'inventory_control_lleno.png' },
        { url: '/schedule', file: 'schedule_audit_lleno.png' }
    ];

    for (const c of captures) {
        await page.goto('http://localhost:8080' + c.url, { waitUntil: 'networkidle', timeout: 15000 });
        await page.screenshot({ path: path.join(artifactDir, c.file), fullPage: false });
        console.log('Captured:', c.file);
    }

    await browser.close();
})();

