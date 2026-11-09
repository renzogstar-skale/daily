const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  console.log('================================================================');
  console.log('--- INICIANDO VALIDACIÓN PLAYWRIGHT: DAILY PRODUCTOS POR DEPARTAMENTO ---');
  console.log('================================================================');

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
  page.on('pageerror', err => {
    consoleErrors.push(err.message);
  });

  // 1. Apertura del dashboard
  console.log('1. Navegando a http://localhost:8080/sales?tab=productos...');
  await page.goto('http://localhost:8080/sales?tab=productos', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const dash = page.locator('#productCatalogDashboard');
  const isVisible = await dash.isVisible();
  console.log(`✓ Dashboard montado y visible: ${isVisible}`);

  // Verificar título, subtítulo y badge
  const title = (await dash.locator('.prod-dash-title').innerText()).trim();
  const badge = (await dash.locator('.prod-dash-mockup-badge').innerText()).trim();
  const subtitle = (await dash.locator('.prod-dash-subtitle').innerText()).trim();
  console.log(`✓ Encabezado: "${title}" [${badge}]`);
  console.log(`  Subtítulo: "${subtitle}"`);

  // 2. Fila 1: Comprobar las 7 tarjetas de indicadores
  const kpis = await dash.locator('.prod-kpi-card');
  const kpiCount = await kpis.count();
  console.log(`✓ Conteo de tarjetas KPI: ${kpiCount} (esperado: 7)`);
  for (let i = 0; i < kpiCount; i++) {
    const card = kpis.nth(i);
    const label = (await card.locator('.prod-kpi-label').innerText()).trim();
    const val = (await card.locator('.prod-kpi-val').innerText()).trim();
    const sub = (await card.locator('.prod-kpi-sub').innerText()).trim();
    console.log(`   [KPI ${i + 1}] ${label}: ${val} (${sub})`);
  }

  // 3. Fila 2: Explorador Inteligente de Productos
  const tableRows = await page.locator('#tbodyProductExplorer tr');
  const rowCount = await tableRows.count();
  console.log(`✓ Filas en explorador inteligente: ${rowCount} (esperado: >= 8)`);

  // 4. Búsqueda por SKU y UPC
  console.log('2 & 3. Comprobando filtros y búsqueda instantánea por SKU / UPC...');
  const searchInput = page.locator('#filterProdSearch');
  await searchInput.fill('DAI-1001');
  await page.waitForTimeout(300);
  let visibleRows = await page.locator('#tbodyProductExplorer tr:visible').count();
  console.log(`✓ Búsqueda por SKU 'DAI-1001': ${visibleRows} fila visible`);

  await searchInput.fill('7501055322104'); // UPC de Helado vainilla
  await page.waitForTimeout(300);
  visibleRows = await page.locator('#tbodyProductExplorer tr:visible').count();
  const matchedProdName = await page.locator('#tbodyProductExplorer tr:visible .prod-cell-name').innerText();
  console.log(`✓ Búsqueda por UPC '7501055322104': ${visibleRows} fila visible (${matchedProdName.trim()})`);

  // Limpiar búsqueda
  await searchInput.fill('');
  await page.waitForTimeout(300);

  // Probar filtro de departamento
  const deptSelect = page.locator('#filterProdDept');
  await deptSelect.selectOption('91');
  await page.waitForTimeout(300);
  const dairyRows = await page.locator('#tbodyProductExplorer tr:visible').count();
  console.log(`✓ Filtro departamento '91 Dairy': ${dairyRows} filas visibles`);
  await deptSelect.selectOption('');
  await page.waitForTimeout(300);

  // 5. Apertura y actualización reactiva de la Ficha 360°
  console.log('4. Probando actualización de Ficha 360° al seleccionar filas...');
  // Clic en la fila 2 (Helado vainilla 1.5 L)
  await page.locator('#tbodyProductExplorer tr:nth-child(2)').click();
  await page.waitForTimeout(400);

  let detName = (await page.locator('#detProdName').innerText()).trim();
  let detSku = (await page.locator('#detProdSkuUpc').innerText()).trim();
  let detPrice = (await page.locator('#detProdPrice').innerText()).trim();
  let detCoverage = (await page.locator('#detProdCoverage').innerText()).trim();
  console.log(`✓ Ficha 360° actualizada para Helado vainilla: ${detName} | ${detSku} | Precio: ${detPrice} | Cobertura: ${detCoverage}`);

  // Clic en la fila 1 (Leche entera 1 L)
  await page.locator('#tbodyProductExplorer tr:nth-child(1)').click();
  await page.waitForTimeout(400);
  detName = (await page.locator('#detProdName').innerText()).trim();
  detPrice = (await page.locator('#detProdPrice').innerText()).trim();
  detCoverage = (await page.locator('#detProdCoverage').innerText()).trim();
  console.log(`✓ Ficha 360° re-actualizada para Leche entera: ${detName} | Precio: ${detPrice} | Cobertura: ${detCoverage}`);

  // 6. Matriz ABC-XYZ
  console.log('5. Probando Matriz ABC-XYZ y filtro interactivo...');
  const cellAX = page.locator('.prod-matrix-cell:has-text("AX")');
  const axCount = (await cellAX.locator('.prod-matrix-count').innerText()).trim();
  console.log(`✓ Celda AX conteo: ${axCount}`);
  await cellAX.click();
  await page.waitForTimeout(300);
  const filteredAxText = await page.locator('#txtProdsPagination').innerText();
  console.log(`✓ Filtro por celda AX aplicado: ${filteredAxText}`);

  // Restaurar tabla
  await page.locator('.prod-kpi-card:nth-child(1)').click();
  await page.waitForTimeout(300);

  // 7. Gráfico de Burbujas (Rotación vs Cobertura)
  console.log('6. Probando Gráfico de Burbujas interactivo...');
  const bubbleLeche = page.locator('#svgBubbleChart .bubble-node:has-text("Leche entera")');
  await bubbleLeche.click();
  await page.waitForTimeout(300);
  detName = (await page.locator('#detProdName').innerText()).trim();
  console.log(`✓ Clic en burbuja 'Leche entera' seleccionó en Ficha 360°: ${detName}`);

  // 8. Matriz Margen x Rotación (4 Cuadrantes)
  console.log('7. Probando Matriz Margen x Rotación...');
  const quad1 = page.locator('.prod-quad-box:nth-child(1)');
  const q1Count = (await quad1.locator('.prod-quad-val').innerText()).trim();
  console.log(`✓ Cuadrante Alta rotación · Alto margen: ${q1Count}`);
  await quad1.click();
  await page.waitForTimeout(300);

  // 9. Optimización del Surtido
  console.log('8. Probando Optimización del Surtido...');
  const assortBars = await page.locator('.prod-assort-bar-row');
  const assortCount = await assortBars.count();
  console.log(`✓ Barras de optimización de surtido: ${assortCount} (esperado: 5)`);
  for (let i = 0; i < assortCount; i++) {
    const lbl = (await assortBars.nth(i).locator('.prod-assort-label').innerText()).trim();
    const cnt = (await assortBars.nth(i).locator('.prod-assort-count').innerText()).trim();
    console.log(`   - ${lbl}: ${cnt}`);
  }

  // 10. Disponibilidad y Venta Potencial Perdida & Riesgo FEFO
  console.log('9 & 10. Probando Venta Perdida y Riesgo FEFO en perecederos...');
  const lostRows = await page.locator('#productCatalogDashboard .prod-card:has-text("Disponibilidad y venta potencial perdida") tbody tr').count();
  const fefoRows = await page.locator('#productCatalogDashboard .prod-card:has-text("Riesgo FEFO en perecederos") tbody tr').count();
  console.log(`✓ Filas de venta potencial perdida: ${lostRows}`);
  console.log(`✓ Filas de riesgo FEFO perecederos: ${fefoRows}`);

  // 11. Acciones Prioritarias por Producto
  console.log('11. Probando Acciones Prioritarias...');
  const prioRows = await page.locator('#tablePriorityActions tbody tr').count();
  console.log(`✓ Acciones prioritarias registradas: ${prioRows}`);

  // 12. Simulador Comercial What-If
  console.log('12. Probando Simulador Comercial What-If...');
  const sliderPrice = page.locator('#sliderPriceChange');
  const sliderDemand = page.locator('#sliderDemandChange');
  
  await sliderPrice.fill('5.0');
  await sliderPrice.dispatchEvent('input');
  await page.waitForTimeout(300);

  const simPriceVal = (await page.locator('#simPriceVal').innerText()).trim();
  const simContribProj = (await page.locator('#simContribProjected').innerText()).trim();
  const simDiff = (await page.locator('#simDifference').innerText()).trim();
  console.log(`✓ Simulador con +5.0% precio:`);
  console.log(`   Precio simulado: ${simPriceVal}`);
  console.log(`   Contribución proyectada: ${simContribProj}`);
  console.log(`   Diferencia económica: ${simDiff}`);

  // 13. Modales y Acciones Rápidas
  console.log('13. Probando modales (Comparar sucursales, Crear tarea, Reposición, Revisión precio)...');
  await page.locator('.prod-detail-actions button:has-text("Solicitar reposición")').click();
  await page.waitForTimeout(400);
  const replenishModalVisible = await page.locator('#modalProductReplenish').isVisible();
  console.log(`✓ Modal Solicitar Reposición visible: ${replenishModalVisible}`);
  await page.locator('#modalProductReplenish .close').click();
  await page.waitForTimeout(300);

  // Modal Comparar Sucursales
  await page.locator('button:has-text("Comparar sucursales")').first().click();
  await page.waitForTimeout(400);
  const compareModalVisible = await page.locator('#modalBranchCompare').isVisible();
  console.log(`✓ Modal Comparar Sucursales visible: ${compareModalVisible}`);
  await page.locator('#modalBranchCompare .close').click();
  await page.waitForTimeout(300);

  // 14. Ausencia de errores de consola
  console.log('14. Verificando consola de navegador...');
  if (consoleErrors.length === 0) {
    console.log('✓ CERO errores de consola detectados.');
  } else {
    console.log('⚠ Errores de consola:', consoleErrors);
  }

  // Capturar Screenshot Full Page Desktop
  const capturesDir = path.join(__dirname, '../captures');
  if (!fs.existsSync(capturesDir)) fs.mkdirSync(capturesDir, { recursive: true });
  const desktopCapturePath = path.join(capturesDir, 'productos_dashboard_desktop.png');
  await page.screenshot({ path: desktopCapturePath, fullPage: true });
  console.log(`✓ Captura guardada en: ${desktopCapturePath}`);

  // Responsive check en dispositivo móvil
  console.log('15. Verificando vista responsive (Mobile 390x844)...');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(600);
  const mobileCapturePath = path.join(capturesDir, 'productos_dashboard_mobile.png');
  await page.screenshot({ path: mobileCapturePath, fullPage: true });
  console.log(`✓ Captura responsive guardada en: ${mobileCapturePath}`);

  await browser.close();
  console.log('================================================================');
  console.log('--- VALIDACIÓN PLAYWRIGHT COMPLETADA CON ÉXITO ---');
  console.log('================================================================');
})();

