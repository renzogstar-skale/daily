const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('http://localhost:8080/schedule');
    
    // Switch to tab "Colaborador" if available
    const tabBtn = page.locator('button:has-text("Colaborador"), a:has-text("Colaborador")').first();
    if (await tabBtn.count()) {
        await tabBtn.click();
        await page.waitForTimeout(500);
    }
    
    const auditRows = await page.locator('h6:has-text("Historial de Modificaciones")').locator('..').locator('..').locator('tbody tr').count();
    console.log('Schedule audit rows:', auditRows);
    const auditText = await page.locator('h6:has-text("Historial de Modificaciones")').locator('..').locator('..').locator('tbody').innerText();
    console.log('Schedule audit text:', auditText.substring(0, 300));
    await browser.close();
})();

