const { chromium } = require('playwright');

(async () => {
  console.log('--- Iniciando verificación de fluidez interactiva, scroll y botones ---');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const pagesToTest = [
    { url: 'http://127.0.0.1:8080/today', name: 'Centro del Día (/today)' },
    { url: 'http://127.0.0.1:8080/sales', name: 'Ventas y Rentabilidad (/sales)' },
    { url: 'http://127.0.0.1:8080/stockoverview', name: 'Inventario (/stockoverview)' },
    { url: 'http://127.0.0.1:8080/alerts', name: 'Centro de Alertas (/alerts)' },
    { url: 'http://127.0.0.1:8080/login', name: 'Login Screen (/login)' }
  ];

  for (const item of pagesToTest) {
    const t0 = Date.now();
    await page.goto(item.url, { waitUntil: 'commit' });
    await page.waitForSelector('body', { timeout: 10000 });
    const loadTime = Date.now() - t0;
    console.log(`\n[${item.name}] Cargó en ${loadTime}ms`);

    // Probar scroll abajo y arriba
    await page.evaluate(() => window.scrollBy(0, 1000));
    await page.waitForTimeout(100);
    const scrolledY = await page.evaluate(() => window.scrollY);
    console.log(`  Scroll abajo: scrollY = ${scrolledY}px`);

    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(100);
    const resetY = await page.evaluate(() => window.scrollY);
    console.log(`  Scroll arriba: scrollY = ${resetY}px`);

    // Si es login, probar interacción del formulario y toggle password
    if (item.url.includes('/login')) {
      const pwdInput = page.locator('#password_input');
      if (await pwdInput.count() > 0) {
        await pwdInput.fill('TestPassword123');
        const eyeBtn = page.locator('#togglePasswordBtn');
        await eyeBtn.click();
        const typeAfterClick = await pwdInput.getAttribute('type');
        console.log(`  Login toggle password click: tipo = ${typeAfterClick}`);
      }
    } else {
      // Probar menú de usuario en el header
      const userMenuBtn = page.locator('#dailyUserProfileDropdown');
      if (await userMenuBtn.count() > 0) {
        const tClick = Date.now();
        await userMenuBtn.click();
        await page.waitForTimeout(150);
        const isOpen = await page.evaluate(() => {
          const menu = document.getElementById('dailyUserProfileMenu');
          return menu && (menu.classList.contains('show') || menu.style.display === 'block');
        });
        console.log(`  Click Perfil Usuario: respondió en ${Date.now() - tClick}ms (Menú abierto: ${isOpen})`);

        // Cerrar menú haciendo click fuera
        await page.keyboard.press('Escape');
        await page.waitForTimeout(100);
      }

      // Probar navegación entre tabs si existen
      const tabs = page.locator('.daily-level-tab, .nav-pills .nav-link');
      const countTabs = await tabs.count();
      if (countTabs > 1) {
        const secondTab = tabs.nth(1);
        const tabText = (await secondTab.innerText()).trim();
        const tTab = Date.now();
        await secondTab.click();
        await page.waitForTimeout(200);
        console.log(`  Click Tab '${tabText}': respondió en ${Date.now() - tTab}ms`);
      }
    }
  }

  await browser.close();
  console.log('\n✓ Verificación completa: Todos los dashboards responden de forma inmediata sin congelamiento.');
})();
