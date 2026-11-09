const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');
const path = require('path');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  console.log('Navigating to http://localhost:8080/waste ...');
  await page.goto('http://localhost:8080/waste', { waitUntil: 'networkidle', timeout: 30000 });

  // Ensure tab-centro-control is active
  await page.waitForSelector('#dwEvolutionChart', { timeout: 10000 });
  await page.waitForTimeout(1000);

  // Capture initial state (Merma / ventas %)
  const cardSection = await page.$('.dw-section-grid.dw-grid-3-col-a');
  if (cardSection) {
    await cardSection.screenshot({ path: path.join(__dirname, '../captures/waste_3_cards_initial.png') });
    console.log('Captured initial 3 cards screenshot.');
  }

  // Click on '$' pill
  console.log('Clicking on [$] pill button...');
  await page.click('#dw-unit-pills .dw-pill-btn:has-text("$")');
  await page.waitForTimeout(500);

  const currValMoney = await page.$eval('#dw-side-curr-val', el => el.innerText);
  const projValMoney = await page.$eval('#dw-side-proj-val', el => el.innerText);
  console.log(`Money mode side cards -> Current: ${currValMoney}, Projection: ${projValMoney}`);

  if (cardSection) {
    await cardSection.screenshot({ path: path.join(__dirname, '../captures/waste_3_cards_money.png') });
    console.log('Captured money mode screenshot.');
  }

  // Switch department sort to "Mayor pérdida ($)"
  console.log('Changing department sort to Mayor pérdida ($)...');
  await page.selectOption('#dw-dept-sort', 'val');
  await page.waitForTimeout(500);

  const topDeptLabel = await page.$eval('.dw-dept-bar-row .dw-dept-bar-label', el => el.innerText);
  const topDeptVal = await page.$eval('.dw-dept-bar-row .dw-dept-bar-val', el => el.innerText);
  console.log(`Top Dept in Val mode: ${topDeptLabel} with ${topDeptVal}`);

  // Switch donut to Porcentaje (%)
  console.log('Changing donut mode to Porcentaje (%)...');
  await page.selectOption('#dw-donut-mode', 'pct');
  await page.waitForTimeout(500);

  if (cardSection) {
    await cardSection.screenshot({ path: path.join(__dirname, '../captures/waste_3_cards_interactive_tested.png') });
    console.log('Captured interactive tested screenshot.');
  }

  // Switch back to '%' for general view
  console.log('Switching back to [%] pill...');
  await page.click('#dw-unit-pills .dw-pill-btn:has-text("%")');
  await page.selectOption('#dw-dept-sort', 'pct');
  await page.selectOption('#dw-donut-mode', 'money');
  await page.waitForTimeout(500);

  if (cardSection) {
    await cardSection.screenshot({ path: path.join(__dirname, '../captures/waste_3_cards_final_clean.png') });
    console.log('Captured final clean screenshot.');
  }

  await browser.close();
  console.log('DONE!');
})();
