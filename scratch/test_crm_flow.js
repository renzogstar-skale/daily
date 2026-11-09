const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  if (!fs.existsSync('captures')) {
    fs.mkdirSync('captures', { recursive: true });
  }

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });

  page.on('pageerror', err => {
    consoleErrors.push(err.message);
  });

  try {
    console.log('Navigating to http://localhost:8080/customers ...');
    const response = await page.goto('http://localhost:8080/customers', { waitUntil: 'networkidle' });
    console.log('Response status:', response.status());

    await page.waitForTimeout(1000);

    // Check header and title
    const title = await page.title();
    console.log('Page Title:', title);

    // Check KPIs
    const kpiCount = await page.$$eval('.ded-kpi-card', els => els.length);
    console.log(`Found ${kpiCount} KPI cards`);

    // Verify Tab Exclusivity
    const checkActiveTabs = async (expectedTabId) => {
      const activeTabs = await page.$$eval('.ded-nav-tabs-bar .ded-nav-tab.active, .ded-nav-tabs-bar .ded-nav-tab.is-active', els => els.map(e => e.id));
      console.log(`Active tabs count: ${activeTabs.length}, IDs:`, activeTabs);
      if (activeTabs.length !== 1) {
        console.error(`WARNING: Expected exactly 1 active tab, got ${activeTabs.length}!`);
      } else if (activeTabs[0] === expectedTabId) {
        console.log(`PASS: Exactly 1 active tab (${expectedTabId})`);
      } else {
        console.warn(`Mismatch: Expected ${expectedTabId}, found ${activeTabs[0]}`);
      }
    };

    console.log('\n--- Checking Initial Tab 1 (Overview) ---');
    await checkActiveTabs('pill-tab-overview');
    await page.screenshot({ path: 'captures/crm_1_resumen.png' });
    console.log('Saved captures/crm_1_resumen.png');

    console.log('\n--- Clicking Tab 2 (Directorio 360°) ---');
    await page.click('#pill-tab-directory');
    await page.waitForTimeout(600);
    await checkActiveTabs('pill-tab-directory');
    await page.screenshot({ path: 'captures/crm_2_directorio.png' });
    console.log('Saved captures/crm_2_directorio.png');

    // Test clicking another customer row in Directorio 360°
    const customerRows = await page.$$('.crm-customer-row');
    console.log(`Found ${customerRows.length} customer rows`);
    if (customerRows.length > 1) {
      await customerRows[1].click();
      await page.waitForTimeout(600);
      const selectedName = await page.$eval('#crm-name', el => el.textContent.trim());
      console.log('Selected customer name in 360 Hero:', selectedName);
      await page.screenshot({ path: 'captures/crm_2_directorio_selected.png' });
      console.log('Saved captures/crm_2_directorio_selected.png');
    }

    console.log('\n--- Clicking Tab 3 (RFM Segmentation) ---');
    await page.click('#pill-tab-rfm');
    await page.waitForTimeout(600);
    await checkActiveTabs('pill-tab-rfm');
    await page.screenshot({ path: 'captures/crm_3_rfm.png' });
    console.log('Saved captures/crm_3_rfm.png');

    console.log('\n--- Clicking Tab 4 (Loyalty Club) ---');
    await page.click('#pill-tab-loyalty');
    await page.waitForTimeout(600);
    await checkActiveTabs('pill-tab-loyalty');
    await page.screenshot({ path: 'captures/crm_4_loyalty.png' });
    console.log('Saved captures/crm_4_loyalty.png');

    console.log('\n--- Clicking Tab 5 (Service Cases) ---');
    await page.click('#pill-tab-cases');
    await page.waitForTimeout(600);
    await checkActiveTabs('pill-tab-cases');
    await page.screenshot({ path: 'captures/crm_5_cases.png' });
    console.log('Saved captures/crm_5_cases.png');

    console.log('\n--- Clicking Tab 6 (Campaigns & Retention) ---');
    await page.click('#pill-tab-campaigns');
    await page.waitForTimeout(600);
    await checkActiveTabs('pill-tab-campaigns');
    await page.screenshot({ path: 'captures/crm_6_campaigns.png' });
    console.log('Saved captures/crm_6_campaigns.png');

    console.log('\n--- Clicking Tab 7 (Advanced Intelligence) ---');
    await page.click('#pill-tab-analytics');
    await page.waitForTimeout(800);
    await checkActiveTabs('pill-tab-analytics');
    await page.screenshot({ path: 'captures/crm_7_analytics.png' });
    console.log('Saved captures/crm_7_analytics.png');

    console.log('\n--- Checking Console Errors ---');
    if (consoleErrors.length === 0) {
      console.log('No console errors detected! PERFECT.');
    } else {
      console.log('Console errors:', consoleErrors);
    }

  } catch (err) {
    console.error('Error during execution:', err);
  } finally {
    await browser.close();
  }
})();
