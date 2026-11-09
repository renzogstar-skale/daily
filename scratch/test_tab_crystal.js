const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  
  // Click on Sucursales tab
  await page.locator('.ded-nav-tab:has-text("Sucursales"):visible').click();
  await page.waitForTimeout(400);

  const sucursalesCards = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('#tab-sucursales .branch-card-panel, #tab-sucursales .branch-kpi-card')).map(el => ({
      cls: el.className,
      hasCrystal: el.classList.contains('crystal-surface'),
      bg: window.getComputedStyle(el).backgroundColor,
      bgImg: window.getComputedStyle(el).backgroundImage.substring(0, 30)
    }));
  });
  console.log('Sucursales cards after clicking tab:', sucursalesCards.slice(0, 5));
  await browser.close();
})();

