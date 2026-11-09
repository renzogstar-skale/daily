const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    const browser = await chromium.launch({ channel: 'msedge' });
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 3,
        isMobile: true,
        hasTouch: true
    });
    // Set cookie for english
    await context.addCookies([
        { name: 'daily_lang', value: 'en', domain: 'localhost', path: '/' }
    ]);
    const page = await context.newPage();
    await page.goto('http://localhost:8080/m', { waitUntil: 'load' });
    await page.locator('.m-greeting-title').waitFor({ state: 'visible', timeout: 15000 });
    await page.screenshot({ path: 'captures/mobile_replica_home_en.png' });
    console.log('English screenshot captured in captures/mobile_replica_home_en.png');
    await browser.close();
})();

