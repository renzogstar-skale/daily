/**
 * Test E2E Automatizado con Playwright:
 * Validación de la eliminación completa del módulo CALIDAD
 */
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = 'http://localhost:8080';

(async () => {
	console.log('🚀 Iniciando pruebas E2E: Verificación de eliminación del módulo CALIDAD...\n');
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
		// 1. Cargar la aplicación
		console.log('--- 1. Navegación e Inspección del Menú Lateral ---');
		await page.goto(BASE_URL + '/today', { waitUntil: 'domcontentloaded', timeout: 30000 });
		await page.waitForTimeout(1000);

		// Verificar que la categoría CALIDAD no existe en el menú
		const menuText = await page.locator('#sidebarResponsive').innerText();
		check('Categoría "5. CALIDAD Y VENCIMIENTOS" no aparece en el menú', !menuText.includes('CALIDAD Y VENCIMIENTOS'));
		check('Opción "Calidad & Vencimientos" no aparece en el menú', !menuText.includes('Calidad & Vencimientos'));

		// Verificar renumeración de módulos (1 al 7 + Indicadores)
		check('Módulo 1: VENTAS Y RENTABILIDAD presente', menuText.includes('1. VENTAS Y RENTABILIDAD'));
		check('Módulo 2: INVENTARIO Y ALMACENES presente', menuText.includes('2. INVENTARIO Y ALMACENES'));
		check('Módulo 3: DESPACHOS Y DEVOLUCIONES presente', menuText.includes('3. DESPACHOS Y DEVOLUCIONES'));
		check('Módulo 4: MERMAS Y PREVENCIÓN presente', menuText.includes('4. MERMAS Y PREVENCIÓN'));
		check('Módulo 5: OPERACIONES Y PERSONAL renumerado correctamente', menuText.includes('5. OPERACIONES Y PERSONAL'));
		check('Módulo 6: ADMINISTRACIÓN Y SISTEMA renumerado correctamente', menuText.includes('6. ADMINISTRACIÓN Y SISTEMA'));
		check('Módulo 7: PLANEACIÓN Y COMPRAS renumerado correctamente', menuText.includes('7. PLANEACIÓN Y COMPRAS'));

		// 2. Funcionalidades compartidas conservadas y reubicadas
		console.log('\n--- 2. Funcionalidades Compartidas Conservadas ---');
		const navLinks = await page.$$eval('#sidebarResponsive a', as => as.map(a => ({ text: a.textContent.trim(), href: a.getAttribute('href') })));
		check('Trazabilidad de Lotes presente en menú (reubicada en Inventario)', navLinks.some(l => l.text.includes('Trazabilidad de Lotes') && l.href.includes('/traceability')));
		check('Retiros de Producto presente en menú (reubicada en Despachos)', navLinks.some(l => l.text.includes('Retiros de Producto') && l.href.includes('/recalls')));

		const resTrace = await page.goto(BASE_URL + '/traceability', { waitUntil: 'domcontentloaded' });
		check('Ruta conservada /traceability responde HTTP 200', resTrace.status() === 200, 'Status: ' + resTrace.status());

		const resRecalls = await page.goto(BASE_URL + '/recalls', { waitUntil: 'domcontentloaded' });
		check('Ruta conservada /recalls responde HTTP 200', resRecalls.status() === 200, 'Status: ' + resRecalls.status());

		// 3. Rutas exclusivas de Calidad no accesibles (404)
		console.log('\n--- 3. Inaccesibilidad de Rutas Exclusivas ---');
		const resQuality = await page.goto(BASE_URL + '/quality', { waitUntil: 'domcontentloaded' });
		check('Ruta exclusiva /quality ya no es accesible (404 Not Found)', resQuality.status() === 404, 'Status: ' + resQuality.status());

		const resQualityExp = await page.goto(BASE_URL + '/quality-expiry', { waitUntil: 'domcontentloaded' });
		check('Ruta exclusiva /quality-expiry ya no es accesible (404 Not Found)', resQualityExp.status() === 404, 'Status: ' + resQualityExp.status());

		// API exclusiva (al no existir controlador, la ruta no devuelve datos / Slim CORS fallback retorna vacío)
		const apiBatches = await page.request.get(BASE_URL + '/api/quality/batches');
		const bodyBatches = await apiBatches.text();
		check('API /api/quality/batches ya no retorna datos ni controlador (cuerpo vacío)', bodyBatches.trim() === '');

		const apiTemps = await page.request.get(BASE_URL + '/api/quality/temperatures');
		const bodyTemps = await apiTemps.text();
		check('API /api/quality/temperatures ya no retorna datos ni controlador (cuerpo vacío)', bodyTemps.trim() === '');

		// 4. Verificación de Command Palette (Ctrl+K)
		console.log('\n--- 4. Command Palette y Accesos Rápidos ---');
		await page.goto(BASE_URL + '/today', { waitUntil: 'domcontentloaded' });
		const cmdItems = await page.locator('.dairy-command-item').allInnerTexts();
		const hasQualityCmd = cmdItems.some(t => /calidad & vencimientos/i.test(t));
		check('Acceso a Calidad retirado del Command Palette', !hasQualityCmd);

		// 5. Verificación de los demás módulos principales
		console.log('\n--- 5. Funcionamiento de los Demás Módulos ---');
		const mainRoutes = ['/sales', '/stockoverview', '/waste', '/orders', '/schedule', '/kpis', '/mrp', '/suppliers'];
		for (const r of mainRoutes) {
			const resp = await page.goto(BASE_URL + r, { waitUntil: 'domcontentloaded', timeout: 60000 });
			check(`Módulo ${r} responde HTTP 200 sin errores`, resp.status() === 200, 'Status: ' + resp.status());
		}

		// 6. Visualización Móvil Responsive
		console.log('\n--- 6. Responsividad Móvil (390 x 844) ---');
		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto(BASE_URL + '/sales', { waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(600);

		const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
		const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
		check('Sin desbordamiento horizontal en móvil tras eliminación', scrollWidth <= clientWidth + 2);

		// 7. Consola
		console.log('\n--- 7. Consola y Errores de JavaScript ---');
		check('Cero errores de consola en la navegación', consoleErrors.length === 0, consoleErrors.join(' | '));

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
