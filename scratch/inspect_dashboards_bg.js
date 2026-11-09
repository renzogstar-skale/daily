const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  
  const urls = [
    'http://localhost:8080/sales?tab=resumen',
    'http://localhost:8080/sales?tab=departamentos',
    'http://localhost:8080/sales?tab=productos',
    'http://localhost:8080/sales?tab=sucursales',
    'http://localhost:8080/sales?tab=detalle',
    'http://localhost:8080/pos',
    'http://localhost:8080/pos_returns',
    'http://localhost:8080/customers'
  ];

  for (const url of urls) {
    await page.goto(url, { waitUntil: 'networkidle' });
    const res = await page.evaluate(() => {
      const b = document.body;
      const bStyle = window.getComputedStyle(b);
      // find cards
      const cards = Array.from(document.querySelectorAll('.card, [class*="-card"], [class*="-panel"]'))
        .filter(el => el.offsetWidth > 100 && el.offsetHeight > 50)
        .slice(0, 3)
        .map(el => {
          const s = window.getComputedStyle(el);
          return {
            cls: el.className,
            bg: s.backgroundColor,
            bgImg: s.backgroundImage.substring(0, 30)
          };
        });
      return {
        url: window.location.pathname + window.location.search,
        bodyBg: bStyle.backgroundColor,
        cards
      };
    });
    console.log(JSON.stringify(res, null, 2));
  }
  await browser.close();
})();

