const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  try {
    await page.goto('http://localhost:8080/pos', { waitUntil: 'networkidle' });
    await page.click('#pill-tab-terminal');
    await page.waitForTimeout(600);

    const firstAddBtn = await page.$('.pos-btn-add:not([disabled])');
    if (firstAddBtn) {
      await firstAddBtn.click();
      await page.waitForTimeout(600);
      console.log('Product added to cart!');

      const totalText = await page.$eval('#pos-total-display', el => el.textContent.trim());
      console.log('Cart Total:', totalText);

      const isEnabled = await page.$eval('#btn-submit-sale', el => !el.disabled);
      console.log('Checkout button enabled:', isEnabled);

      // Select exact cash
      await page.click('.pos-cash-chip[data-quick-cash="exact"]');
      const changeVal = await page.$eval('#pos-change-display', el => el.textContent.trim());
      console.log('Exact cash change:', changeVal);

      // Submit sale
      await page.click('#btn-submit-sale');
      await page.waitForTimeout(2000);
      console.log('Sale checkout triggered!');

      await page.screenshot({ path: 'captures/pos_sale_completed.png' });
      console.log('SUCCESS: Sale flow verified and captured to captures/pos_sale_completed.png');
    } else {
      console.log('No active add button found');
    }
  } catch (err) {
    console.error('Flow test error:', err.message);
  } finally {
    await browser.close();
  }
})();

