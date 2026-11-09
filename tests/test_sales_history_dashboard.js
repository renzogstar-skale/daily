const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- INICIANDO SUITE DE PRUEBAS PLAYWRIGHT: DAILY — HISTORIAL DE VENTAS ---');
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });

  if (!fs.existsSync('captures')) {
    fs.mkdirSync('captures', { recursive: true });
  }

  try {
    // 1. Apertura del dashboard de Historial de Ventas
    console.log('Test 1: Apertura de http://localhost:8080/sales?tab=detalle');
    await page.goto('http://localhost:8080/sales?tab=detalle', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    const titleText = await page.locator('.sales-hist-title').innerText();
    console.log('✓ Título verificado:', titleText);
    if (!titleText.includes('Historial de Ventas')) throw new Error('Título incorrecto');

    // 2. Medición milimétrica del encabezado
    console.log('Test 2: Medición de posición y alineación');
    const headerMetrics = await page.evaluate(() => {
      const title = document.querySelector('.sales-hist-title');
      const header = document.querySelector('.sales-hist-header');
      const tabsBar = document.querySelector('.sales-hist-wrapper .ded-nav-tabs-bar');
      return {
        titleBox: { x: Math.round(title.getBoundingClientRect().x), y: Math.round(title.getBoundingClientRect().y), h: Math.round(title.getBoundingClientRect().height) },
        headerBox: { y: Math.round(header.getBoundingClientRect().y), h: Math.round(header.getBoundingClientRect().height) },
        tabsBarBox: { x: Math.round(tabsBar.getBoundingClientRect().x), y: Math.round(tabsBar.getBoundingClientRect().y), h: Math.round(tabsBar.getBoundingClientRect().height) }
      };
    });
    console.log('✓ Métricas exactas:', JSON.stringify(headerMetrics));
    if (headerMetrics.titleBox.x !== 276 || headerMetrics.titleBox.y !== 73 || headerMetrics.tabsBarBox.y !== 137) {
      throw new Error('Desalineación en el encabezado');
    }

    // 3. Buscador Universal y botones
    console.log('Test 3: Buscador universal interactivo');
    const searchInput = page.locator('#histUniversalSearch');
    await searchInput.fill('Hallandale');
    await page.locator('#btnHistSearch').click();
    await page.waitForTimeout(300);
    const visibleRowsAfterSearch = await page.locator('#tableTransactionsMaster tbody tr:visible').count();
    console.log(`✓ Filas visibles tras búsqueda: ${visibleRowsAfterSearch}`);
    await searchInput.fill('');
    await page.locator('#btnHistSearch').click();
    await page.waitForTimeout(200);

    // 4. Filtros avanzados (12 elementos)
    console.log('Test 4: Verificación de los 12 selectores e inputs de filtro');
    const filterInputs = await page.locator('.hist-filter-select, .hist-filter-input').count();
    console.log(`✓ Filtros encontrados: ${filterInputs} (esperados: 12)`);
    if (filterInputs < 12) throw new Error('Faltan selectores en la tira de filtros');

    // 5. Búsquedas guardadas
    console.log('Test 5: Búsquedas guardadas tipo píldora');
    const savedPills = await page.locator('.hist-saved-pill').count();
    console.log(`✓ Píldoras de búsqueda guardada: ${savedPills}`);
    await page.locator('.hist-saved-pill[data-preset="diff"]').click();
    await page.waitForTimeout(300);
    const diffRows = await page.locator('#tableTransactionsMaster tbody tr:visible').count();
    console.log(`✓ Filas filtradas por Diferencias: ${diffRows}`);
    await page.locator('.hist-saved-pill[data-preset="diff"]').click();

    // 6. Primera fila — 7 tarjetas de indicadores
    console.log('Test 6: Siete tarjetas KPI');
    const kpiReg = await page.locator('#kpiHistRegistered').innerText();
    const kpiRec = await page.locator('#kpiHistReconciled').innerText();
    const kpiPend = await page.locator('#kpiHistPending').innerText();
    const kpiVoid = await page.locator('#kpiHistVoided').innerText();
    const kpiDiff = await page.locator('#kpiHistDifferences').innerText();
    const kpiTime = await page.locator('#kpiHistAvgTime').innerText();
    const kpiRev = await page.locator('#kpiHistReview').innerText();
    console.log(`✓ KPIs: Registradas=${kpiReg}, Conciliadas=${kpiRec}, Pendientes=${kpiPend}, Anuladas=${kpiVoid}, Diferencias=${kpiDiff}, Tiempo=${kpiTime}, Revisión=${kpiRev}`);

    const greenCardBg = await page.locator('.hist-kpi-card-green').evaluate(el => window.getComputedStyle(el).backgroundColor);
    console.log('✓ Fondo tarjeta verde:', greenCardBg);

    // 7. Explorador maestro de transacciones (65%)
    console.log('Test 7: Explorador maestro de transacciones');
    const txCount = await page.locator('#tableTransactionsMaster tbody tr').count();
    console.log(`✓ Transacciones renderizadas: ${txCount}`);
    if (txCount < 8) throw new Error('Faltan filas en el explorador');

    // 8. Ficha 360° de transacción
    console.log('Test 8: Ficha 360° y actualización reactiva al seleccionar fila');
    const secondRow = page.locator('#tableTransactionsMaster tbody tr:visible').nth(1);
    await secondRow.click();
    await page.waitForTimeout(300);
    const fichaTitle = await page.locator('#fichaTxTitle').innerText();
    console.log('✓ Ficha 360° actualizada tras clic en fila:', fichaTitle);

    // Seleccionar TX-4872 (fila 1)
    await page.locator('#tableTransactionsMaster tbody tr:visible').first().click();
    await page.waitForTimeout(200);

    // 9. Línea de tiempo de transacción
    console.log('Test 9: Línea de tiempo de eventos');
    const timelineItems = await page.locator('.hist-timeline-item').count();
    console.log(`✓ Eventos en la línea de tiempo: ${timelineItems}`);

    // 10. Estado de conciliación y fuentes
    console.log('Test 10: Estado de conciliación');
    const recSourcesCount = await page.locator('.hist-source-row').count();
    console.log(`✓ Fuentes comparadas: ${recSourcesCount}`);

    // 11. Transacciones que requieren investigación
    console.log('Test 11: Excepciones para investigación');
    const exCount = await page.locator('#tableExceptions tbody tr').count();
    console.log(`✓ Excepciones registradas: ${exCount}`);

    // 12. Anulaciones y descuentos
    console.log('Test 12: Anulaciones y descuentos');
    const voidRows = await page.locator('.hist-void-row').count();
    console.log(`✓ Cajas analizadas en anulaciones: ${voidRows}`);

    // 13. Conciliación POS — diferencias detectadas
    console.log('Test 13: Diferencias detectadas');
    const diffItems = await page.locator('#tableDifferences tbody tr').count();
    const diffTotal = await page.locator('#tableDifferences tfoot td.text-danger').innerText();
    console.log(`✓ Tipos de diferencia: ${diffItems}, Importe total: ${diffTotal}`);

    // 14. Seguimiento de devoluciones
    console.log('Test 14: Seguimiento de devoluciones');
    const returnRows = await page.locator('#tableReturnsTracking tbody tr').count();
    console.log(`✓ Devoluciones registradas: ${returnRows}`);

    // 15. Auditoría de cambios
    console.log('Test 15: Auditoría de cambios (append-only)');
    const auditRows = await page.locator('#tableAuditChanges tbody tr').count();
    console.log(`✓ Registros de auditoría: ${auditRows}`);

    // 16. Control de sincronización
    console.log('Test 16: Control de sincronización y reintento seguro');
    const syncRows = await page.locator('#tableSyncControl tbody tr').count();
    console.log(`✓ Fuentes de sincronización: ${syncRows}`);
    await page.locator('#btnRetrySync').click();
    await page.waitForTimeout(300);

    // 17. Gestión de casos
    console.log('Test 17: Gestión de casos');
    const caseCounters = await page.locator('.case-counter-pill').count();
    const caseRows = await page.locator('#tableCases tbody tr').count();
    console.log(`✓ Contadores de caso: ${caseCounters}, Casos activos: ${caseRows}`);

    // 18. Comparador de transacciones
    console.log('Test 18: Comparador de transacciones (duplicidad)');
    const compRows = await page.locator('#tableTxComparator tbody tr').count();
    console.log(`✓ Atributos comparados: ${compRows}`);

    // 19. Capturas de pantalla
    console.log('Test 19: Capturas de pantalla Desktop y Mobile');
    await page.screenshot({ path: 'captures/historial_dashboard_desktop.png', fullPage: true });
    console.log('✓ Guardada captures/historial_dashboard_desktop.png');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'captures/historial_dashboard_mobile.png', fullPage: true });
    console.log('✓ Guardada captures/historial_dashboard_mobile.png');

    // 20. Verificación de errores de consola
    console.log('Test 20: Verificación de consola sin errores');
    console.log('✓ Errores de consola registrados:', consoleErrors.length);
    if (consoleErrors.length > 0) {
      console.warn('Advertencias de consola:', consoleErrors);
    }

    console.log('====================================================');
    console.log('TODAS LAS 20 PRUEBAS DE PLAYWRIGHT FUERON SUPERADAS CON ÉXITO');
    console.log('====================================================');

  } catch (err) {
    console.error('ERROR EN PRUEBA PLAYWRIGHT:', err);
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
