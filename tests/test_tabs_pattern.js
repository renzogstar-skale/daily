const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  console.log('================================================================');
  console.log('--- VALIDACIÓN: PATRÓN UNIFICADO DE ENCABEZADO Y PESTAÑAS ---');
  console.log('================================================================');

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(err.message));

  const capturesDir = path.join(__dirname, '../captures');
  if (!fs.existsSync(capturesDir)) fs.mkdirSync(capturesDir, { recursive: true });

  // 1. Cargar Tablero de Ventas
  console.log('1. Cargando Tablero de Ventas (/sales?tab=resumen)...');
  await page.goto('http://localhost:8080/sales?tab=resumen', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);

  let resumenPaneVisible = await page.locator('#tab-resumen').isVisible();
  let titleResumen = (await page.locator('#salesExecutiveDashboard .ded-title').innerText()).trim();
  let activeTabInResumen = (await page.locator('#salesExecutiveDashboard .ded-nav-tab.is-active, #salesExecutiveDashboard .ded-nav-tab.active').innerText()).trim();
  console.log(`✓ Tablero de Ventas: Título = "${titleResumen}", Pestaña activa = "${activeTabInResumen}"`);
  await page.screenshot({ path: path.join(capturesDir, 'patron_1_tablero_ventas.png') });

  // 2. Hacer clic en "Rendimiento por departamento" desde el botón visible
  console.log('2. Haciendo clic en "Rendimiento por departamento"...');
  await page.locator('.ded-nav-tab:has-text("Rendimiento por departamento"):visible').click();
  await page.waitForTimeout(600);

  let deptPaneVisible = await page.locator('#tab-departamentos').isVisible();
  let titleDept = (await page.locator('#deptExecutiveDashboard .ded-title').innerText()).trim();
  let activeTabInDept = (await page.locator('#deptExecutiveDashboard .ded-nav-tab.is-active, #deptExecutiveDashboard .ded-nav-tab.active').innerText()).trim();
  console.log(`✓ Rendimiento por Departamento:`);
  console.log(`  - Pane visible: ${deptPaneVisible}`);
  console.log(`  - Título superior: "${titleDept}"`);
  console.log(`  - Línea de pestañas presente: ${await page.locator('#deptExecutiveDashboard .ded-nav-tabs-bar').isVisible()}`);
  console.log(`  - Pestaña activa en la línea: "${activeTabInDept}"`);
  await page.screenshot({ path: path.join(capturesDir, 'patron_2_rendimiento_departamento.png') });

  // 3. Hacer clic en "Productos" desde el botón visible
  console.log('3. Haciendo clic en "Productos"...');
  await page.locator('.ded-nav-tab:has-text("Productos"):visible').click();
  await page.waitForTimeout(600);

  let prodPaneVisible = await page.locator('#tab-productos').isVisible();
  let titleProd = (await page.locator('#productCatalogDashboard .prod-dash-title').innerText()).trim();
  let activeTabInProd = (await page.locator('#productCatalogDashboard .ded-nav-tab.is-active, #productCatalogDashboard .ded-nav-tab.active').innerText()).trim();
  console.log(`✓ Productos:`);
  console.log(`  - Pane visible: ${prodPaneVisible}`);
  console.log(`  - Título superior: "${titleProd}"`);
  console.log(`  - Línea de pestañas presente: ${await page.locator('#productCatalogDashboard .ded-nav-tabs-bar').isVisible()}`);
  console.log(`  - Pestaña activa en la línea: "${activeTabInProd}"`);
  await page.screenshot({ path: path.join(capturesDir, 'patron_3_productos.png') });

  // 4. Hacer clic de regreso a "Rendimiento por departamento" desde Productos
  console.log('4. Clic de regreso a "Rendimiento por departamento" desde Productos...');
  await page.locator('.ded-nav-tab:has-text("Rendimiento por departamento"):visible').click();
  await page.waitForTimeout(600);
  deptPaneVisible = await page.locator('#tab-departamentos').isVisible();
  activeTabInDept = (await page.locator('#deptExecutiveDashboard .ded-nav-tab.is-active, #deptExecutiveDashboard .ded-nav-tab.active').innerText()).trim();
  console.log(`✓ Regreso a Rendimiento: visible=${deptPaneVisible}, activa="${activeTabInDept}"`);

  // 5. Cargar directamente /sales?tab=departamentos
  console.log('5. Acceso directo por URL a /sales?tab=departamentos...');
  await page.goto('http://localhost:8080/sales?tab=departamentos', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  deptPaneVisible = await page.locator('#tab-departamentos').isVisible();
  titleDept = (await page.locator('#deptExecutiveDashboard .ded-title').innerText()).trim();
  activeTabInDept = (await page.locator('#deptExecutiveDashboard .ded-nav-tab.is-active, #deptExecutiveDashboard .ded-nav-tab.active').innerText()).trim();
  console.log(`✓ Carga directa: visible=${deptPaneVisible}, título="${titleDept}", activa="${activeTabInDept}"`);

  if (consoleErrors.length === 0) {
    console.log('✓ CERO errores de consola.');
  } else {
    console.log('⚠ Errores de consola:', consoleErrors);
  }

  await browser.close();
  console.log('================================================================');
  console.log('--- PRUEBA COMPLETADA CON ÉXITO ---');
  console.log('================================================================');
})();
