const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
  
  for (const url of ['http://localhost:8080/pos', 'http://localhost:8080/pos_returns', 'http://localhost:8080/customers']) {
    await page.goto(url, { waitUntil: 'networkidle' });
    const res = await page.evaluate(() => {
      // Find all elements with non-transparent background
      const elements = Array.from(document.querySelectorAll('*')).filter(el => {
        const s = window.getComputedStyle(el);
        const bg = s.backgroundColor;
        const hasBg = bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent';
        const isWhite = bg === 'rgb(255, 255, 255)' || bg === '#fff' || bg === '#ffffff';
        return hasBg && (el.offsetWidth > 200 && el.offsetHeight > 100);
      }).map(el => ({
        tag: el.tagName,
        cls: el.className,
        id: el.id,
        bg: window.getComputedStyle(el).backgroundColor,
        bgImg: window.getComputedStyle(el).backgroundImage.substring(0, 30)
      }));
      return { url: window.location.pathname, elements: elements.slice(0, 10) };
    });
    console.log(JSON.stringify(res, null, 2));
  }
  await browser.close();
})();

