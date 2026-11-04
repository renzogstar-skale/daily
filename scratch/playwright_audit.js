const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const outDir = 'C:/Users/renzo/grocy/grocy/public/img/audit_captures';

const routes = [
	{ id: 'today', path: '/today', name: 'HOY · Operación del Día' },
	{ id: 'alerts', path: '/alerts', name: 'Centro de Alertas' },
	{ id: 'kpis', path: '/kpis', name: 'Panel Ejecutivo' },
	{ id: 'mrp', path: '/mrp', name: 'Planificación MRP' },
	{ id: 'suppliers', path: '/suppliers', name: 'Proveedores & Compras' },
	{ id: 'branches', path: '/branches', name: 'Sucursales y Almacenes' },
	{ id: 'merchandise', path: '/merchandise', name: 'Control de Mercancías' },
	{ id: 'scan', path: '/scan', name: 'Operaciones con Escáner' },
	{ id: 'locations_map', path: '/locations/map', name: 'Mapa de Ubicaciones' },
	{ id: 'stockoverview', path: '/stockoverview', name: 'Stock & Movimientos' },
	{ id: 'transfers', path: '/transfers', name: 'Traslados entre Tiendas' },
	{ id: 'kardex', path: '/kardex', name: 'Kardex Valorizado' },
	{ id: 'inventory_control', path: '/inventory-control', name: 'Control de Inventario' },
	{ id: 'inventory', path: '/inventory', name: 'Conteos Físicos' },
	{ id: 'reconciliation', path: '/reconciliation', name: 'Conciliación & Sensible' },
	{ id: 'sales', path: '/sales', name: 'Ventas y Rentabilidad' },
	{ id: 'pos', path: '/pos', name: 'Punto de Venta (POS)' },
	{ id: 'orders', path: '/orders', name: 'Pedidos de Clientes' },
	{ id: 'customers', path: '/customers', name: 'Clientes y Cuentas' },
	{ id: 'dispatch', path: '/dispatch', name: 'Centro de Despachos' },
	{ id: 'returns', path: '/returns', name: 'Devoluciones e Incidencias' },
	{ id: 'quality', path: '/quality', name: 'Calidad & Vencimientos' },
	{ id: 'traceability', path: '/traceability', name: 'Trazabilidad de Lotes' },
	{ id: 'recalls', path: '/recalls', name: 'Retiros de Producto' },
	{ id: 'waste', path: '/waste', name: 'Cuadro de Control de Mermas' },
	{ id: 'waste_reasons', path: '/waste/reasons', name: 'Matriz de Causales' },
	{ id: 'loss_prevention', path: '/loss-prevention', name: 'Centro de Control LP' },
	{ id: 'devices', path: '/loss-prevention/devices', name: 'Control de Celulares' },
	{ id: 'loss_case_1', path: '/loss-control/cases/1', name: 'Expediente Caso 1' },
	{ id: 'schedule', path: '/operations/scheduling', name: 'Horarios y Cobertura' },
	{ id: 'attendance', path: '/operations/attendance', name: 'Asistencia y Fichajes' },
	{ id: 'workforce', path: '/workforce', name: 'Tareas y Roles' },
	{ id: 'reports', path: '/reports', name: 'Reportes & Analítica' },
	{ id: 'settings', path: '/settings', name: 'Configuración' },
	{ id: 'audit_log', path: '/audit-log', name: 'Bitácora de Auditoría' }
];

(async () => {
	console.log('Iniciando auditoría visual con Playwright...');
	const browser = await chromium.launch({ channel: 'msedge', headless: true });
	const results = [];

	for (const target of routes) {
		const result = { ...target, desktopOk: false, mobileOk: false, errors: [], consoleLogs: [] };
		console.log(`Auditoría: ${target.name} (${target.path})`);

		// Desktop
		try {
			const ctxDesk = await browser.newContext({ viewport: { width: 1366, height: 900 } });
			const pageDesk = await ctxDesk.newPage();
			pageDesk.on('pageerror', err => result.errors.push(`[Desk Error] ${err.message}`));
			pageDesk.on('console', msg => {
				if (msg.type() === 'error') result.consoleLogs.push(`[Desk Console] ${msg.text()}`);
			});

			const t0 = Date.now();
			const resp = await pageDesk.goto(baseUrl + target.path, { waitUntil: 'domcontentloaded', timeout: 35000 });
			const loadTime = Date.now() - t0;
			result.status = resp ? resp.status() : 'No resp';
			result.loadTimeMs = loadTime;

			await pageDesk.waitForTimeout(1000);
			const shotPath = path.join(outDir, `${target.id}_desktop.png`);
			await pageDesk.screenshot({ path: shotPath, fullPage: false });
			result.desktopShot = shotPath;
			result.desktopOk = (resp && resp.status() < 400);

			// Check title & key elements
			result.title = await pageDesk.title();
			await ctxDesk.close();
		} catch (e) {
			result.errors.push(`[Desk Exception] ${e.message}`);
		}

		// Mobile
		try {
			const ctxMob = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
			const pageMob = await ctxMob.newPage();
			pageMob.on('pageerror', err => result.errors.push(`[Mob Error] ${err.message}`));

			const resp = await pageMob.goto(baseUrl + target.path, { waitUntil: 'domcontentloaded', timeout: 35000 });
			await pageMob.waitForTimeout(800);
			const shotMobPath = path.join(outDir, `${target.id}_mobile.png`);
			await pageMob.screenshot({ path: shotMobPath, fullPage: false });
			result.mobileShot = shotMobPath;
			result.mobileOk = (resp && resp.status() < 400);
			await ctxMob.close();
		} catch (e) {
			result.errors.push(`[Mob Exception] ${e.message}`);
		}

		results.push(result);
		console.log(`  -> Status: ${result.status} (${result.loadTimeMs}ms) | Desk: ${result.desktopOk ? 'OK' : 'FAIL'} | Mob: ${result.mobileOk ? 'OK' : 'FAIL'} | Errors: ${result.errors.length}`);
	}

	await browser.close();

	const reportJson = path.join(outDir, 'audit_results.json');
	fs.writeFileSync(reportJson, JSON.stringify(results, null, 2), 'utf8');
	console.log(`\nAuditoría completa guardada en ${reportJson}`);
})();

