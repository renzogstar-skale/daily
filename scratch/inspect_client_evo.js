const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('http://localhost:8080/waste');
  const evoData = await page.evaluate(() => window.dwEvolutionData);
  console.log('window.dwEvolutionData:', JSON.stringify(evoData, null, 2));
  await browser.close();
})();

