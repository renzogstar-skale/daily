const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    const browser = await chromium.launch({ channel: 'msedge' });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await page.goto('http://localhost:8080/m', { waitUntil: 'load' });
    await page.waitForTimeout(1000);
    await page.screenshot({ path: 'captures/mobile_before.png', fullPage: true });
    await browser.close();
    console.log('Mobile screenshot captured: captures/mobile_before.png');
})();

