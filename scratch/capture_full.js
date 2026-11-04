const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    await page.goto("http://localhost:8080/stockoverview", { waitUntil: "networkidle", timeout: 20000 });
    await page.screenshot({ path: "C:/Users/renzo/.gemini/antigravity/brain/9cb989c6-05ae-4a92-bfbb-e019aaa0dfd8/captures/stockoverview_completo_balanceado.png" });
    console.log("Full screenshot captured!");
    await browser.close();
})();
