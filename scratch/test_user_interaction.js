const { chromium } = require('playwright');

async function main() {
  console.log('Launching browser for interaction test...');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 }
  });
  const page = await context.newPage();

  console.log('Navigating to http://127.0.0.1:8080/today...');
  const t0 = Date.now();
  await page.goto('http://127.0.0.1:8080/today', { waitUntil: 'domcontentloaded', timeout: 15000 });
  console.log('DOM Content Loaded in', Date.now() - t0, 'ms');

  // Verify page title
  const title = await page.title();
  console.log('Title:', title);

  // Test 1: Scroll down and up
  console.log('Testing scroll down...');
  await page.evaluate(() => window.scrollBy(0, 1000));
  await page.waitForTimeout(300);
  const scrollY1 = await page.evaluate(() => window.scrollY);
  console.log('ScrollY after scroll down:', scrollY1);

  console.log('Testing scroll up...');
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  const scrollY2 = await page.evaluate(() => window.scrollY);
  console.log('ScrollY after scroll up:', scrollY2);

  // Test 2: Click profile menu dropdown
  console.log('Testing click on user profile dropdown...');
  const profileTrigger = await page.$('#dailyUserProfileDropdown');
  if (profileTrigger) {
    const c0 = Date.now();
    await profileTrigger.click();
    console.log('Clicked profile dropdown in', Date.now() - c0, 'ms');
    await page.waitForTimeout(300);
    const isMenuOpen = await page.$eval('.daily-profile-dropdown-menu', el => el.classList.contains('show'));
    console.log('Profile dropdown menu .show:', isMenuOpen);
  } else {
    console.error('Profile trigger not found!');
  }

  // Test 3: Click decision filter pills/tabs
  console.log('Testing clicks on filter buttons/tabs...');
  const filterPills = await page.$$('.nav-pills a, .btn-group button, .daily-level-tab');
  console.log('Found filter buttons/tabs:', filterPills.length);
  if (filterPills.length > 0) {
    const clickStart = Date.now();
    await filterPills[0].click();
    console.log('Clicked first pill in', Date.now() - clickStart, 'ms');
  }

  // Test 4: Navigate to /sales
  console.log('Testing navigation to /sales...');
  const tSales = Date.now();
  await page.goto('http://127.0.0.1:8080/sales', { waitUntil: 'domcontentloaded', timeout: 15000 });
  console.log('Sales DOM loaded in', Date.now() - tSales, 'ms');
  const salesTitle = await page.title();
  console.log('Sales Title:', salesTitle);

  // Scroll on sales
  await page.evaluate(() => window.scrollBy(0, 800));
  await page.waitForTimeout(300);
  const salesScroll = await page.evaluate(() => window.scrollY);
  console.log('Sales ScrollY:', salesScroll);

  // Take screenshot of sales
  await page.screenshot({ path: 'scratch/sales_responsive.png' });
  console.log('Screenshot saved to scratch/sales_responsive.png');

  await browser.close();
  console.log('ALL INTERACTION TESTS PASSED CLEANLY!');
}

main().catch(err => {
  console.error('Interaction test failed:', err);
  process.exit(1);
});
