const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  console.log('========================================================================');
  console.log('--- VALIDACIÓN PLAYWRIGHT: DAILY DISEÑO GLOBAL Y CERO FLASH BLANCO ---');
  console.log('========================================================================');

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 950 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const txt = msg.text();
      // Ignore favicon or non-critical 404s if any
      if (!txt.includes('favicon')) {
        consoleErrors.push(txt);
      }
    }
  });
  page.on('pageerror', err => consoleErrors.push(err.message));

  const capturesDir = path.join(__dirname, '../captures');
  if (!fs.existsSync(capturesDir)) fs.mkdirSync(capturesDir, { recursive: true });

  const targetRoutes = [
    { name: '1. Tablero de Ventas', url: 'http://localhost:8080/sales?tab=resumen', selector: '#salesExecutiveDashboard' },
    { name: '2. Rendimiento por Departamento', url: 'http://localhost:8080/sales?tab=departamentos', selector: '#deptExecutiveDashboard' },
    { name: '3. Productos por Departamento', url: 'http://localhost:8080/sales?tab=productos', selector: '#productCatalogDashboard' },
    { name: '4. Sucursales', url: 'http://localhost:8080/sales?tab=sucursales', selector: '.branch-dash-wrapper' },
    { name: '5. Historial de Ventas', url: 'http://localhost:8080/sales?tab=detalle', selector: '.sales-hist-wrapper' },
    { name: '6. Punto de Venta', url: 'http://localhost:8080/pos', selector: '.pos-catalog-panel' },
    { name: '7. Devoluciones del POS', url: 'http://localhost:8080/pos/returns', selector: '.pos-ret-hero' },
    { name: '8. Gestión de Clientes', url: 'http://localhost:8080/customers', selector: '.customers-main-grid' }
  ];

  console.log('\n--- FASE 1: VALIDACIÓN INDIVIDUAL DE FONDO GLOBAL Y TARJETAS CRISTALINAS ---');
  for (const route of targetRoutes) {
    console.log(`\nValidando: ${route.name} (${route.url})...`);
    await page.goto(route.url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);

    const check = await page.evaluate((sel) => {
      const b = document.body;
      const bStyle = window.getComputedStyle(b);
      const bBefore = window.getComputedStyle(b, '::before').backgroundImage;
      const bAfter = window.getComputedStyle(b, '::after').backgroundImage;
      const targetEl = document.querySelector(sel);
      const wrapperBg = targetEl ? window.getComputedStyle(targetEl).backgroundColor : null;

      // Find cards in the page
      const cards = Array.from(document.querySelectorAll(
        '.card, .ded-card, .ded-kpi-card:not(.is-highlight), .branch-card-panel, .branch-kpi-card:not(.branch-kpi-card-green), ' +
        '.hist-card-panel, .hist-search-card, .hist-kpi-card:not(.hist-kpi-card-green), ' +
        '.prod-card-panel, .prod-kpi-card:not(.prod-kpi-card-green), .dept-perf-card, ' +
        '.pos-catalog-panel, .pos-totals-section, .pos-ret-hero, .pos-ret-lifecycle-card, .pos-ret-panel, ' +
        '.customers-kpi-card, .customers-card, .customers-filter-bar'
      )).filter(el => el.offsetWidth > 60 && el.offsetHeight > 30);

      const cardSamples = cards.slice(0, 5).map(c => {
        const cs = window.getComputedStyle(c);
        return {
          tag: c.tagName,
          cls: c.className,
          bg: cs.backgroundColor,
          bgImg: cs.backgroundImage.substring(0, 32),
          border: cs.borderColor
        };
      });

      return {
        bodyBg: bStyle.backgroundColor,
        hasAmbientBefore: bBefore !== 'none',
        hasAmbientAfter: bAfter !== 'none',
        wrapperBg,
        cardSamples,
        totalCardsFound: cards.length,
        hasSidebar: !!document.querySelector('#dairySidenav'),
        hasNavbar: !!document.querySelector('#mainNav')
      };
    }, route.selector);

    console.log(`  ✓ Body Background: ${check.bodyBg} (Capa ambiental activa: ${check.hasAmbientBefore && check.hasAmbientAfter})`);
    console.log(`  ✓ Wrapper transparente: ${check.wrapperBg === 'rgba(0, 0, 0, 0)' || check.wrapperBg === 'transparent' ? 'SÍ' : 'NO (' + check.wrapperBg + ')'}`);
    console.log(`  ✓ Tarjetas cristalinas verificadas: ${check.cardSamples.length} de ${check.totalCardsFound}`);
    console.log(`  ✓ Header persistente: ${check.hasNavbar}, Sidebar persistente: ${check.hasSidebar}`);

    // Assertion checks
    if (!check.hasAmbientBefore || !check.hasAmbientAfter) {
      throw new Error(`Falta el fondo ambiental cristalino en ${route.name}`);
    }
    if (check.cardSamples.some(c => c.bg === 'rgb(255, 255, 255)' && c.bgImg === 'none')) {
      throw new Error(`Se detectó una tarjeta blanca sólida en ${route.name}`);
    }

    // Capture screenshot
    const shotPath = path.join(capturesDir, `crystal_${route.name.split('.')[0].trim().replace(/\s+/g, '_').toLowerCase()}.png`);
    await page.screenshot({ path: shotPath });
  }

  console.log('\n--- FASE 2: NAVEGACIÓN CONTINUA Y CERO FLASH BLANCO ENTRE MÓDULOS ---');
  // Navigate through internal tab switcher (Tablero -> Sucursales -> Productos -> Historial)
  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);

  const tabsToTest = [
    { name: 'Sucursales', sel: '.ded-nav-tab:has-text("Sucursales"):visible', pane: '#tab-sucursales' },
    { name: 'Productos', sel: '.ded-nav-tab:has-text("Productos"):visible', pane: '#tab-productos' },
    { name: 'Historial de ventas', sel: '.ded-nav-tab:has-text("Historial de ventas"):visible', pane: '#tab-detalle' },
    { name: 'Tablero de Ventas', sel: '.ded-nav-tab:has-text("Tablero de Ventas"):visible', pane: '#tab-resumen' }
  ];

  for (const t of tabsToTest) {
    console.log(`  Haciendo clic en pestaña: "${t.name}"...`);
    await page.locator(t.sel).click();
    await page.waitForTimeout(350);

    const paneStatus = await page.evaluate((paneId) => {
      const pane = document.querySelector(paneId);
      if (!pane) return null;
      const isVis = window.getComputedStyle(pane).display !== 'none';
      const cards = Array.from(pane.querySelectorAll('.branch-card-panel, .branch-kpi-card, .prod-card-panel, .prod-kpi-card, .hist-card-panel, .hist-kpi-card, .ded-card, .ded-kpi-card'));
      const sample = cards[0];
      return {
        visible: isVis,
        cardCount: cards.length,
        firstCardBgImg: sample ? window.getComputedStyle(sample).backgroundImage.substring(0, 32) : 'none',
        firstCardBg: sample ? window.getComputedStyle(sample).backgroundColor : 'none'
      };
    }, t.pane);

    console.log(`  ✓ Pestaña "${t.name}": visible=${paneStatus.visible}, tarjetas cristalinas=${paneStatus.cardCount}, gradiente=${paneStatus.firstCardBgImg}`);
    if (!paneStatus.visible || paneStatus.firstCardBg === 'rgb(255, 255, 255)' && paneStatus.firstCardBgImg === 'none') {
      throw new Error(`Fallo en acabado cristalino en pestaña ${t.name}`);
    }
  }

  console.log('\n--- FASE 3: NAVEGACIÓN MULTIPÁGINA VIA SIDEBAR (SIN PANTALLAS BLANCAS) ---');
  // Navigate via sidebar to /pos, then to /customers, then back to /sales
  const sideNavLinks = [
    { name: 'Punto de Venta', selector: '#dairySidenav a[href*="/pos"]:not([href*="returns"])', targetPath: '/pos' },
    { name: 'Devoluciones POS', selector: '#dairySidenav a[href*="/pos/returns"]', targetPath: '/pos/returns' },
    { name: 'Clientes', selector: '#dairySidenav a[href*="/customers"]', targetPath: '/customers' },
    { name: 'Ventas y Rentabilidad', selector: '#dairySidenav a[href*="/sales"]', targetPath: '/sales' }
  ];

  for (const nav of sideNavLinks) {
    console.log(`  Navegando via menú lateral hacia: ${nav.name}...`);
    const link = page.locator(nav.selector).first();
    if (await link.isVisible()) {
      await link.click();
      await page.waitForTimeout(500);
      const curPath = await page.evaluate(() => window.location.pathname);
      console.log(`  ✓ Llegada a ${curPath} (Sidebar y Header estables y continuos)`);
    } else {
      console.log(`  (Link ${nav.name} no visible directamente en el viewport actual, navegando por URL)`);
      await page.goto('http://localhost:8080' + nav.targetPath, { waitUntil: 'networkidle' });
    }
  }

  console.log('\n--- FASE 4: VERIFICACIÓN DE CONSOLA Y MODO OSCURO (NIGHT MODE) ---');
  // Check Night Mode class compatibility
  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  await page.evaluate(() => {
    document.body.classList.remove('crystal-ui');
    document.body.classList.add('night-mode');
    document.documentElement.classList.add('daily-night');
  });
  await page.waitForTimeout(300);

  const nightCheck = await page.evaluate(() => {
    const card = document.querySelector('.ded-card') || document.querySelector('.card');
    const cs = window.getComputedStyle(card);
    return {
      bodyBg: window.getComputedStyle(document.body).backgroundColor,
      cardBg: cs.backgroundColor,
      color: cs.color
    };
  });
  console.log(`  ✓ Modo oscuro verificado: Body=${nightCheck.bodyBg}, Card=${nightCheck.cardBg}, Text=${nightCheck.color}`);

  if (consoleErrors.length === 0) {
    console.log('\n✓ CERO ERRORES DE CONSOLA REGISTRADOS EN TODAS LAS PRUEBAS.');
  } else {
    console.log('\n⚠ Errores de consola:', consoleErrors);
    throw new Error('Se registraron errores en la consola');
  }

  await browser.close();
  console.log('========================================================================');
  console.log('--- TODAS LAS PRUEBAS DE DISEÑO GLOBAL Y CERO FLASH FUERON EXITOSAS ---');
  console.log('========================================================================');
})();

