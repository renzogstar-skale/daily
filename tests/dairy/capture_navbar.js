const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    const browser = await chromium.launch({ channel: 'msedge' });
    
    // Desktop screenshot
    const pageDesktop = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await pageDesktop.goto('http://localhost:8080/', { waitUntil: 'load' });
    await pageDesktop.screenshot({ path: 'captures/navbar_commercial_desktop.png', clip: { x: 0, y: 0, width: 1440, height: 180 } });

    // Mobile screenshot
    const pageMobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await pageMobile.goto('http://localhost:8080/', { waitUntil: 'load' });
    await pageMobile.locator('.s-burger').click();
    await pageMobile.waitForTimeout(300);
    await pageMobile.screenshot({ path: 'captures/navbar_commercial_mobile.png', clip: { x: 0, y: 0, width: 390, height: 450 } });

    await browser.close();
    console.log('Screenshots captured successfully.');
})();

