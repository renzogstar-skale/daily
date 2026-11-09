const { chromium } = require('playwright');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('request', r => console.log('REQ START:', r.resourceType(), r.url()));
  page.on('response', r => console.log('RES:', r.status(), r.url()));

  try {
    await page.goto('http://127.0.0.1:8080/today', { timeout: 15000 });
  } catch (err) {
    console.error('Failed:', err.message);
  } finally {
    await browser.close();
  }
}

main();
