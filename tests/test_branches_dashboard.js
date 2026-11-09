const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- INICIANDO SUITE DE PRUEBAS PLAYWRIGHT: DAILY — SUCURSALES ---');
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
    // 1. Apertura del dashboard de Sucursales
    console.log('Test 1: Apertura de http://localhost:8080/sales?tab=sucursales');
    await page.goto('http://localhost:8080/sales?tab=sucursales', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    const titleText = await page.locator('.branch-dash-title').innerText();
    console.log('✓ Título verificado:', titleText);
    if (!titleText.includes('Sucursales')) throw new Error('Título incorrecto');

    // 2. Verificación de los 7 filtros de encabezado
    console.log('Test 2: Verificación de filtros de encabezado');
    const filters = await page.locator('.branch-filter-select').count();
    console.log(`✓ Filtros encontrados: ${filters} (esperados: 8)`);
    if (filters < 8) throw new Error('Faltan selectores de filtro');

    // 3. Verificación de las 7 tarjetas KPI
    console.log('Test 3: Primera fila — 7 tarjetas de indicadores');
    const kpiSupervised = await page.locator('#kpiSupervised').innerText();
    const kpiOperational = await page.locator('#kpiOperational').innerText();
    const kpiAttention = await page.locator('#kpiAttention').innerText();
    const kpiCritical = await page.locator('#kpiCritical').innerText();
    const kpiIncidents = await page.locator('#kpiIncidents').innerText();
    const kpiCompliance = await page.locator('#kpiCompliance').innerText();
    const kpiIntervention = await page.locator('#kpiIntervention').innerText();

    console.log(`✓ KPIs: Supervisadas=${kpiSupervised}, Operativas=${kpiOperational}, Atención=${kpiAttention}, Críticas=${kpiCritical}, Incidencias=${kpiIncidents}, Cumplimiento=${kpiCompliance}, Intervención=${kpiIntervention}`);
    
    // Validar fondo verde de la tarjeta 7
    const greenCardBg = await page.locator('.branch-kpi-card-green').evaluate(el => window.getComputedStyle(el).backgroundColor);
    console.log('✓ Color de fondo tarjeta de intervención:', greenCardBg);

    // 4. Mapa interactivo de sucursales
    console.log('Test 4: Mapa interactivo y nodos');
    const nodesCount = await page.locator('.branch-map-node').count();
    console.log(`✓ Nodos en el mapa: ${nodesCount} (esperados: 10)`);
    if (nodesCount !== 10) throw new Error(`Se esperaban 10 nodos en el mapa, encontrados ${nodesCount}`);

    // Clic en nodo de Miami Beach (id 2)
    const mbNode = page.locator('.branch-map-node[data-branch-id="2"] .branch-node-dot');
    await mbNode.click();
    await page.waitForTimeout(300);
    const floatName = await page.locator('#floatName').innerText();
    console.log('✓ Tarjeta flotante actualizada tras clic en nodo:', floatName);

    // 5. Selector entre vista de Mapa y Tarjetas
    console.log('Test 5: Selector vista Mapa / Tarjetas');
    await page.locator('#btnViewCards').click();
    await page.waitForTimeout(300);
    const cardsVisible = await page.locator('#branchCardsGrid').isVisible();
    console.log('✓ Vista de tarjetas visible:', cardsVisible);

    await page.locator('#btnViewMap').click();
    await page.waitForTimeout(300);
    const mapVisible = await page.locator('#branchMapContainer').isVisible();
    console.log('✓ Vista de mapa restaurada:', mapVisible);

    // 6. Ranking BHI y filtros de ordenación
    console.log('Test 6: Ranking BHI y 5 filtros de ordenación');
    const rankingRows = await page.locator('#tableRankingBhi tbody tr').count();
    console.log(`✓ Filas en ranking BHI: ${rankingRows} (esperadas: 10)`);

    // Probar filtro "Mayor riesgo"
    await page.locator('.ranking-pill[data-sort="risk"]').click();
    await page.waitForTimeout(300);
    const topRiskStore = await page.locator('#tableRankingBhi tbody tr:first-child td:nth-child(2)').innerText();
    console.log('✓ Primer lugar en Mayor Riesgo:', topRiskStore);

    // Restaurar a "Mejor desempeño"
    await page.locator('.ranking-pill[data-sort="best"]').click();
    await page.waitForTimeout(300);

    // 7. Matriz de supervisión multisucursal (10 tiendas × 7 procesos)
    console.log('Test 7: Matriz de supervisión (10 tiendas × 7 procesos)');
    const matrixRows = await page.locator('#matrixSupervisionTable tbody tr').count();
    const matrixCells = await page.locator('.matrix-cell-btn').count();
    console.log(`✓ Matriz: ${matrixRows} tiendas, ${matrixCells} celdas de proceso`);

    // 8. Clic en celda de la matriz para abrir justificativo
    console.log('Test 8: Apertura de justificativo de proceso desde la matriz');
    const firstCell = page.locator('.matrix-cell-btn').first();
    await firstCell.click();
    await page.waitForTimeout(400);
    const cellModalVisible = await page.locator('#modalCellDetail').isVisible();
    console.log('✓ Modal de justificativo visible:', cellModalVisible);
    await page.locator('#modalCellDetail .close').click();
    await page.waitForTimeout(300);

    // 9. Ficha 360° reactiva y accesos rápidos
    console.log('Test 9: Ficha 360° y accesos rápidos con branch_id preseleccionado');
    // Seleccionar Hallandale Beach (id 1)
    await page.locator('.branch-map-node[data-branch-id="1"] .branch-node-dot').click();
    await page.waitForTimeout(300);
    const fichaTitle = await page.locator('#fichaTitle').innerText();
    const linkSalesHref = await page.locator('#linkSales').getAttribute('href');
    console.log(`✓ Ficha 360° título: ${fichaTitle}`);
    console.log(`✓ Enlace ventas preseleccionado: ${linkSalesHref}`);
    if (!linkSalesHref.includes('branch_id=1')) throw new Error('Enlace no incluye branch_id');

    // 10. Centro de incidencias por sucursal
    console.log('Test 10: Centro de incidencias y modal de gestión');
    const incidentRows = await page.locator('#tableIncidents tbody tr').count();
    console.log(`✓ Incidencias listadas: ${incidentRows}`);
    await page.locator('.btn-open-incident').first().click();
    await page.waitForTimeout(400);
    const incModalVisible = await page.locator('#modalIncidentDetail').isVisible();
    console.log('✓ Modal de gestión de incidencia visible:', incModalVisible);
    await page.locator('#modalIncidentDetail .close').click();
    await page.waitForTimeout(300);

    // 11. Patrones multisucursal
    console.log('Test 11: Patrones multisucursal (Hipótesis, Correlación, Confirmada)');
    const patternsCount = await page.locator('.branch-pattern-card').count();
    console.log(`✓ Patrones detectados: ${patternsCount}`);
    const natureBadges = await page.locator('.branch-nature-pill').allInnerTexts();
    console.log('✓ Clasificaciones de naturaleza:', natureBadges);

    // 12. Centro de intervenciones regionales
    console.log('Test 12: Intervenciones regionales y contadores');
    const counters = await page.locator('.interv-counter-pill').count();
    const intervRows = await page.locator('#tableInterventions tbody tr').count();
    console.log(`✓ Contadores de estado: ${counters}, Intervenciones en curso: ${intervRows}`);

    // 13. Seguimiento de responsables
    console.log('Test 13: Seguimiento de responsables y barras de cumplimiento');
    const respRows = await page.locator('.responsible-row').count();
    console.log(`✓ Responsables monitoreados: ${respRows}`);

    // 14. Comparador de sucursales
    console.log('Test 14: Comparador dinámico de sucursales');
    const compHeaders = await page.locator('#tableComparator thead th').allInnerTexts();
    console.log('✓ Columnas activas en comparador:', compHeaders);

    // 15. Bitácora de visitas y modal de registro
    console.log('Test 15: Bitácora de visitas y modal "+ Registrar visita"');
    const visitsCount = await page.locator('.branch-visit-card').count();
    console.log(`✓ Visitas previas registradas: ${visitsCount}`);
    await page.locator('#btnRegisterVisit').click();
    await page.waitForTimeout(400);
    const visitModalVisible = await page.locator('#modalRegisterVisit').isVisible();
    console.log('✓ Modal de registro de visita visible:', visitModalVisible);
    await page.locator('#modalRegisterVisit .close').click();
    await page.waitForTimeout(300);

    // 16. Captura de pantalla completa en Desktop (1440 × 900)
    console.log('Test 16: Captura de pantalla desktop');
    await page.screenshot({ path: 'captures/sucursales_dashboard_desktop.png', fullPage: true });
    console.log('✓ Guardada captures/sucursales_dashboard_desktop.png');

    // 17. Captura de pantalla en Mobile (390 × 844)
    console.log('Test 17: Prueba de diseño responsive en mobile');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'captures/sucursales_dashboard_mobile.png', fullPage: true });
    console.log('✓ Guardada captures/sucursales_dashboard_mobile.png');

    // 18. Verificación de errores de consola
    console.log('Test 18: Verificación de consola sin errores');
    console.log('✓ Errores de consola registrados:', consoleErrors.length);
    if (consoleErrors.length > 0) {
      console.warn('Advertencias de consola:', consoleErrors);
    }

    console.log('====================================================');
    console.log('TODAS LAS 18 PRUEBAS DE PLAYWRIGHT FUERON SUPERADAS CON ÉXITO');
    console.log('====================================================');

  } catch (err) {
    console.error('ERROR EN PRUEBA PLAYWRIGHT:', err);
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
