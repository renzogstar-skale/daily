const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

const routes = [
    { path: '/today', name: 'HOY · Operación del Día' },
    { path: '/alerts', name: 'Centro de Alertas' },
    { path: '/kpis', name: 'Panel Ejecutivo (Mes)' },
    { path: '/kpis?period=day', name: 'Panel Ejecutivo (Día)' },
    { path: '/mrp', name: 'MRP · Planeación' },
    { path: '/suppliers', name: 'Proveedores' },
    { path: '/branches', name: 'Sucursales' },
    { path: '/merchandise', name: 'Recepción de Mercancías' },
    { path: '/stockoverview', name: 'Stock e Inventario' },
    { path: '/scan', name: 'Escaneo UPC' },
    { path: '/transfers', name: 'Traslados' },
    { path: '/kardex', name: 'Kardex' },
    { path: '/inventory-control', name: 'Control de Inventarios' },
    { path: '/inventory', name: 'Conteos Físicos' },
    { path: '/reconciliation', name: 'Conciliación' },
    { path: '/sales', name: 'Ventas y Rentabilidad' },
    { path: '/pos', name: 'Punto de Venta POS' },
    { path: '/cost-centers', name: 'Centros de Costo y Márgenes' },
    { path: '/dispatch', name: 'Despachos' },
    { path: '/returns', name: 'Devoluciones' },
    { path: '/quality', name: 'Calidad y Vencimientos' },
    { path: '/waste', name: 'Centro de Control de Mermas' },
    { path: '/loss-prevention', name: 'Prevención de Pérdidas' },
    { path: '/workforce', name: 'Operaciones en Tienda' },
    { path: '/schedule', name: 'Horarios y Turnos' },
    { path: '/attendance', name: 'Asistencia y Marcaciones' },
    { path: '/reports', name: 'Reportes y Analítica' },
    { path: '/reports/loss-executive', name: 'Reporte Ejecutivo Pérdidas' },
    { path: '/ai-advisor', name: 'Plan de Acción IA / Asesor' },
    { path: '/users', name: 'Usuarios y Permisos' },
    { path: '/userfields', name: 'Ajustes y Sistema' }
];

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    console.log(`\n======================================================`);
    console.log(`AUDITANDO ${routes.length} DASHBOARDS EN DAILY...`);
    console.log(`======================================================\n`);

    const results = [];
    let allOk = true;

    for (let i = 0; i < routes.length; i++) {
        const r = routes[i];
        const url = `http://localhost:8080${r.path}`;
        let status = 0;
        let ok = true;
        let errMsg = '';
        const startTime = Date.now();

        try {
            const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
            status = resp ? resp.status() : 0;
            const elapsed = Date.now() - startTime;

            // Wait brief moment for client rendering
            await page.waitForTimeout(400);

            // Check for PHP fatal / slim exception
            const pageText = await page.evaluate(() => document.body.innerText.substring(0, 500));
            const hasPhpError = pageText.includes('Fatal error') || pageText.includes('Slim Application Error') || pageText.includes('Uncaught Error');

            if (status !== 200 || hasPhpError) {
                ok = false;
                errMsg = hasPhpError ? 'PHP Error en contenido' : `HTTP ${status}`;
                allOk = false;
            }

            console.log(`[${ok ? 'OK' : 'FAIL'}] ${String(i + 1).padStart(2, '0')}/${routes.length} - ${r.name.padEnd(32)} | Status: ${status} | ${elapsed}ms ${errMsg ? '-> ' + errMsg : ''}`);
            results.push({ name: r.name, path: r.path, status, ok, elapsed, errMsg });
        } catch (e) {
            allOk = false;
            console.log(`[FAIL] ${String(i + 1).padStart(2, '0')}/${routes.length} - ${r.name.padEnd(32)} | ERROR: ${e.message}`);
            results.push({ name: r.name, path: r.path, status: 0, ok: false, elapsed: 0, errMsg: e.message });
        }
    }

    await browser.close();

    console.log(`\n======================================================`);
    console.log(`RESULTADO FINAL: ${allOk ? 'TODOS LOS DASHBOARDS CARGAN AL 100%' : 'HUBO ERRORES'}`);
    console.log(`Total probados: ${results.length} | Éxito: ${results.filter(r => r.ok).length} | Fallos: ${results.filter(r => !r.ok).length}`);
    console.log(`======================================================\n`);
})();
