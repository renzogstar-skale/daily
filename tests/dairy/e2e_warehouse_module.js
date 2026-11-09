/**
 * E2E Automatizado con Playwright:
 * Validación del módulo integral de Almacén y Bodega (WMS) en DAILY
 */

const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = process.env.DAILY_URL || 'http://localhost:8080';

(async () => {
	console.log('🚀 Iniciando pruebas E2E: Módulo de Almacén y Bodega (WMS)...\n');
	const browser = await chromium.launch({ channel: 'msedge' });
	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();

	const results = [];
	const consoleErrors = [];

	const check = (desc, condition, detail) => {
		const status = condition ? '✅ PASS' : '❌ FAIL';
		results.push({ status, desc, detail: condition ? '' : (detail || '') });
		console.log(`${status} - ${desc}${condition ? '' : ' (' + detail + ')'}`);
	};

	page.on('pageerror', err => {
		consoleErrors.push('PAGEERROR: ' + err.message);
	});
	page.on('console', msg => {
		if (msg.type() === 'error' && !/favicon|Failed to load resource/i.test(msg.text())) {
			consoleErrors.push('CONSOLE: ' + msg.text().slice(0, 160));
		}
	});

	try {
		// 1. Cargar el dashboard /warehouse
		console.log('--- 1. Carga del Dashboard /warehouse ---');
		const res = await page.goto(BASE_URL + '/warehouse', { waitUntil: 'domcontentloaded', timeout: 30000 });
		check('Ruta /warehouse responde HTTP 200', res.status() === 200, 'Status: ' + res.status());

		const titleText = await page.locator('.wms-title-wrap h1').innerText();
		check('Título principal "Almacén y Bodega • Centro de Gestión WMS" visible', titleText.includes('Almacén y Bodega'));

		// 2. Menú Lateral
		console.log('\n--- 2. Enlace en Menú Lateral ---');
		const wmsMenuCount = await page.locator('#sidebarResponsive a[href*="/warehouse"]').count();
		check('Opción "8. Almacén y Bodega (WMS)" presente en menú lateral', wmsMenuCount > 0);

		// 3. Ciclo Logístico WMS (10 Pasos)
		console.log('\n--- 3. Ciclo Logístico WMS (10 Pasos) ---');
		const cycleSteps = await page.locator('.wms-cycle-step').count();
		check('Stepper de ciclo con las 10 etapas logísticas presentes', cycleSteps === 10, 'Encontrados: ' + cycleSteps);

		// 4. Indicadores Principales (8 KPIs WMS)
		console.log('\n--- 4. Indicadores Principales (8 KPIs WMS) ---');
		const kpiCards = await page.locator('.wms-kpi-card').count();
		check('Los 8 KPIs principales de WMS renderizados', kpiCards === 8, 'Encontrados: ' + kpiCards);

		const iraVal = await page.locator('.wms-kpi-card:nth-child(1) .wms-kpi-value').innerText();
		check('KPI 1: Exactitud de Inventario (IRA) calculado con %', iraVal.includes('%'), 'Valor: ' + iraVal);

		const recProdVal = await page.locator('.wms-kpi-card:nth-child(2) .wms-kpi-value').innerText();
		check('KPI 2: Productividad de recepción calculada (u/h)', recProdVal.includes('u/h'), 'Valor: ' + recProdVal);

		const putawayVal = await page.locator('.wms-kpi-card:nth-child(3) .wms-kpi-value').innerText();
		check('KPI 3: Tiempo promedio de putaway calculado (min)', putawayVal.includes('min'), 'Valor: ' + putawayVal);

		// 5. Muelles de Carga y Descarga en Vivo
		console.log('\n--- 5. Muelles de Carga y Descarga ---');
		const dockCards = await page.locator('.wms-dock-card').count();
		check('Bahías y muelles de descarga renderizados', dockCards >= 4, 'Muelles: ' + dockCards);

		// 6. Pestañas y Componentes Interactivos
		console.log('\n--- 6. Pestañas y Componentes Interactivos ---');
		const tabs = await page.locator('.wms-tab-btn').count();
		check('Navegación con 8 pestañas temáticas de WMS', tabs === 8, 'Encontradas: ' + tabs);

		// Test Pestaña Programación de Recepción
		await page.locator('[data-tab="programacion"]').click();
		await page.waitForTimeout(300);
		const progTabActive = await page.locator('#tab-programacion').isVisible();
		check('Pestaña Programación de Recepción se activa correctamente', progTabActive);

		const appRows = await page.locator('#tab-programacion .wms-table tbody tr').count();
		check('Citas de proveedores y POs esperadas listadas', appRows > 0, 'Citas/POs: ' + appRows);

		// Test Pestaña Inspección & Temperatura
		await page.locator('[data-tab="recepcion"]').click();
		await page.waitForTimeout(300);
		const inspRows = await page.locator('#tab-recepcion .wms-table tbody tr').count();
		check('Inspecciones de entrada con control térmico listadas', inspRows > 0, 'Inspecciones: ' + inspRows);

		// Apertura de modal de inspección
		await page.locator('#openInspModalBtn').click();
		await page.waitForTimeout(300);
		const inspModalActive = await page.locator('#wmsInspectionModal.active').isVisible();
		check('Modal de Nueva Inspección en Muelle se abre correctamente', inspModalActive);
		await page.locator('#closeInspModalBtn').click();
		await page.waitForTimeout(200);

		// Test Pestaña Ubicación & Putaway
		await page.locator('[data-tab="putaway"]').click();
		await page.waitForTimeout(300);
		const putawayTasks = await page.locator('#tab-putaway .wms-table tbody tr').count();
		check('Cola de tareas de putaway en racks cargada', putawayTasks > 0, 'Tareas: ' + putawayTasks);

		const rackBoxes = await page.locator('.wms-rack-box').count();
		check('Mapa de racks y posiciones de almacenamiento presente', rackBoxes > 0, 'Racks: ' + rackBoxes);

		// Test Pestaña Movimientos Internos
		await page.locator('[data-tab="movimientos"]').click();
		await page.waitForTimeout(300);
		const movRows = await page.locator('#tab-movimientos .wms-table tbody tr').count();
		check('Movimientos internos y reabastecimiento de picking listados', movRows > 0, 'Movimientos: ' + movRows);

		// Test Pestaña Preparación de Pedidos (Picking)
		await page.locator('[data-tab="picking"]').click();
		await page.waitForTimeout(300);
		const pickRows = await page.locator('#tab-picking .wms-table tbody tr').count();
		check('Cola de pedidos de clientes en picking cargada', pickRows > 0, 'Pedidos: ' + pickRows);

		// Test Pestaña Control de Existencias & FEFO
		await page.locator('[data-tab="existencias"]').click();
		await page.waitForTimeout(300);
		const stockRows = await page.locator('#wmsStockTableBody tr').count();
		check('Existencias por ubicación con semáforo FEFO cargadas', stockRows > 0, 'Existencias: ' + stockRows);

		const cycleRows = await page.locator('#tab-existencias .wms-table').nth(1).locator('tbody tr').count();
		check('Historial de conteos cíclicos de inventario presente', cycleRows > 0, 'Conteos: ' + cycleRows);

		// Test Pestaña Devoluciones (Logística Inversa)
		await page.locator('[data-tab="devoluciones"]').click();
		await page.waitForTimeout(300);
		const returnRows = await page.locator('#tab-devoluciones .wms-table tbody tr').count();
		check('Módulo de devoluciones y clasificación RTV cargado', returnRows > 0, 'Devoluciones: ' + returnRows);

		// 7. Prueba Transaccional de APIs
		console.log('\n--- 7. APIs Transaccionales de WMS ---');
		const putawayApiRes = await page.evaluate(async () => {
			const r = await fetch('/api/warehouse/putaway/1/confirm', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ location_id: 2 })
			});
			return { status: r.status, data: await r.json() };
		});
		check('API /api/warehouse/putaway/1/confirm responde HTTP 200 y éxito', putawayApiRes.status === 200 && putawayApiRes.data.success);

		const dockApiRes = await page.evaluate(async () => {
			const r = await fetch('/api/warehouse/dock/1/status', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ status: 'disponible' })
			});
			return { status: r.status, data: await r.json() };
		});
		check('API /api/warehouse/dock/1/status responde HTTP 200 y éxito', dockApiRes.status === 200 && dockApiRes.data.success);

		// 8. Responsividad Móvil (390 x 844)
		console.log('\n--- 8. Responsividad Móvil (390 x 844) ---');
		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto(BASE_URL + '/warehouse', { waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(600);

		const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
		const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
		check('Sin desbordamiento horizontal en móvil (viewport 390x844)', scrollWidth <= clientWidth + 2);

		// 9. Cero Errores de Consola
		console.log('\n--- 9. Errores de Consola y JavaScript ---');
		check('Cero errores de JavaScript en consola', consoleErrors.length === 0, consoleErrors.join(' | '));

	} catch (e) {
		console.error('Error durante el test:', e);
		check('Ejecución global de las pruebas', false, e.message);
	} finally {
		await browser.close();
		console.log('\n=======================================');
		console.log(`RESUMEN: ${results.filter(r => r.status.includes('PASS')).length} PASADAS, ${results.filter(r => r.status.includes('FAIL')).length} FALLIDAS`);
		console.log('=======================================');
		if (results.some(r => r.status.includes('FAIL'))) {
			process.exit(1);
		}
	}
})();
