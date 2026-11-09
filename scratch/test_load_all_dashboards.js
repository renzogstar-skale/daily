const { chromium } = require('playwright');

const DASHBOARDS = [
  { name: 'HOY · Operación del Día', url: '/today' },
  { name: 'Centro de Alertas', url: '/alerts' },
  { name: 'Panel Ejecutivo', url: '/kpis' },
  { name: 'Tablero de Ventas', url: '/sales' },
  { name: 'Historial de Ventas (Detalle)', url: '/sales?tab=detalle' },
  { name: 'Rentabilidad de Ventas', url: '/sales?tab=rentabilidad' },
  { name: 'Punto de Venta (POS)', url: '/pos' },
  { name: 'Devoluciones del POS', url: '/pos/returns' },
  { name: 'Gestión de Clientes', url: '/customers' },
  { name: 'Stock y Movimientos', url: '/stockoverview' },
  { name: 'Productos Agotados', url: '/inventory/stockouts' },
  { name: 'Control de Inventario', url: '/inventory-control' },
  { name: 'Kardex Valorizado', url: '/kardex' },
  { name: 'Conteos Físicos', url: '/inventory' },
  { name: 'Conciliación e Inventario Sensible', url: '/reconciliation' },
  { name: 'Traslados entre Tiendas', url: '/transfers' },
  { name: 'Almacén y Bodega (WMS)', url: '/warehouse' },
  { name: 'Operaciones con Escáner', url: '/scan' },
  { name: 'Mapa de Ubicaciones', url: '/locations/map' },
  { name: 'Sucursales y Almacenes', url: '/branches' },
  { name: 'Trazabilidad de Lotes', url: '/traceability' },
  { name: 'Pedidos y Entregas', url: '/orders' },
  { name: 'Gestión de Despachos', url: '/dispatches' },
  { name: 'Devoluciones e Incidencias', url: '/returns' },
  { name: 'Retiros de Producto (Recalls)', url: '/recalls' },
  { name: 'Cuadro de Control de Mermas', url: '/waste' },
  { name: 'Registro de Merma', url: '/waste/report' },
  { name: 'Aprobaciones de Merma', url: '/waste/approvals' },
  { name: 'Matriz de Causales', url: '/waste/reasons' },
  { name: 'Prevención de Pérdidas', url: '/loss-prevention' },
  { name: 'Control de Celulares/Activos', url: '/loss-prevention/devices' },
  { name: 'Indicadores de Prevención', url: '/reports/loss-executive' },
  { name: 'Recursos Humanos', url: '/hr' },
  { name: 'Horarios y Cobertura', url: '/operations/scheduling' },
  { name: 'Asistencia y Fichajes', url: '/operations/attendance' },
  { name: 'Tareas y Roles', url: '/workforce' },
  { name: 'Configuración del Sistema', url: '/settings' },
  { name: 'Usuarios y Permisos', url: '/users' },
  { name: 'Importar / Exportar', url: '/data-import' },
  { name: 'Etiquetas de Ubicación', url: '/locations/labels' },
  { name: 'Bitácora de Auditoría', url: '/audit-log' },
  { name: 'Mapa del Sistema', url: '/system-map' },
  { name: 'Suscripción y Planes', url: '/subscription' },
  { name: 'Planificación MRP', url: '/mrp' },
  { name: 'Proveedores y Compras', url: '/suppliers' },
  { name: 'Centros de Costo', url: '/cost-centers' },
  { name: 'Reportes e Informes', url: '/reports' },
  { name: 'DAILY Móvil', url: '/m' }
];

(async () => {
  console.log(`=== INICIANDO AUDITORÍA DE CARGA EN VIVO: ${DASHBOARDS.length} DASHBOARDS ===\n`);
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const results = [];
  let stuckCount = 0;
  let errorCount = 0;

  for (let i = 0; i < DASHBOARDS.length; i++) {
    const d = DASHBOARDS[i];
    const fullUrl = `http://localhost:8080${d.url}`;
    const pageErrors = [];
    const consoleErrors = [];

    const onPageError = err => pageErrors.push(err.message);
    const onConsole = msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Ignore harmless favicon or small missing analytics
        if (!text.includes('favicon.ico')) {
          consoleErrors.push(text);
        }
      }
    };

    page.on('pageerror', onPageError);
    page.on('console', onConsole);

    const startTime = Date.now();
    let status = 'OK';
    let httpStatus = 0;
    let loadDuration = 0;

    try {
      // 10s max timeout per page to detect if anything hangs
      const response = await page.goto(fullUrl, { waitUntil: 'load', timeout: 12000 });
      httpStatus = response ? response.status() : 0;
      await page.waitForTimeout(200); // Allow brief JS executions
      loadDuration = Date.now() - startTime;

      if (httpStatus >= 400) {
        status = `HTTP ${httpStatus}`;
        errorCount++;
      } else if (pageErrors.length > 0) {
        status = `JS ERROR (${pageErrors[0].substring(0, 40)}...)`;
        errorCount++;
      } else {
        status = 'OK';
      }
    } catch (e) {
      loadDuration = Date.now() - startTime;
      if (e.name === 'TimeoutError' || e.message.includes('timeout')) {
        status = 'STUCK / TIMEOUT (>12s)';
        stuckCount++;
      } else {
        status = `CRASH: ${e.message.substring(0, 50)}`;
        errorCount++;
      }
    } finally {
      page.off('pageerror', onPageError);
      page.off('console', onConsole);
    }

    const icon = status === 'OK' ? '✓' : '✗';
    console.log(`[${i + 1}/${DASHBOARDS.length}] ${icon} ${d.name} (${d.url}) -> ${status} [${loadDuration}ms]`);
    results.push({ ...d, status, httpStatus, loadDuration, pageErrors, consoleErrors });
  }

  await browser.close();

  console.log('\n=== RESUMEN DE EJECUCIÓN ===');
  console.log(`Total Dashboards: ${DASHBOARDS.length}`);
  console.log(`Exitosos (OK): ${results.filter(r => r.status === 'OK').length}`);
  console.log(`Pegados / Timeout: ${stuckCount}`);
  console.log(`Con Error: ${errorCount}`);

  if (stuckCount > 0 || errorCount > 0) {
    console.log('\n--- DETALLE DE PROBLEMAS ENCONTRADOS ---');
    results.filter(r => r.status !== 'OK').forEach(r => {
      console.log(`- ${r.name} (${r.url}): ${r.status}`);
      if (r.pageErrors.length) console.log(`  Page Errors: ${r.pageErrors.join(' | ')}`);
      if (r.consoleErrors.length) console.log(`  Console Errors: ${r.consoleErrors.join(' | ')}`);
    });
  } else {
    console.log('\n¡Todos los dashboards cargaron correctamente y ninguno se quedó pegado!');
  }
})();

