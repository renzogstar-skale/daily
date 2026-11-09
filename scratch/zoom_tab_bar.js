const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  try {
    await page.goto('http://localhost:8080/pos', { waitUntil: 'networkidle' });
    await page.click('#pill-tab-terminal');
    await page.waitForTimeout(300);
    const navBar = await page.$('.ded-nav-tabs-bar');
    if (navBar) {
      await navBar.screenshot({ path: 'captures/pos_nav_tabs_zoom.png' });
      console.log('SUCCESS: Captured zoom of tabs bar');
    }
  } finally {
    await browser.close();
  }
})();

