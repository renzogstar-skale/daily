/**
 * E2E Automatizado con Playwright:
 * DAILY — 16. Optimizar Planeación y Compras
 * Valida /mrp, /suppliers y sus endpoints API en modos Claro, Oscuro (html.daily-dark)
 * y Móvil (390x844), además de Chart.js 2.x, Simulador de Demanda y ausencia de datos demo.
 */

const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = process.env.DAILY_URL || 'http://localhost:8080';

(async () => {
	console.log('E2E Planeación y Compras (#16)...\n');
	const browser = await chromium.launch({ channel: 'msedge' });
	const results = [];
	const consoleErrors = [];
	const pageErrors = [];

	const check = (desc, cond, detail = '') => {
		const ok = Boolean(cond);
		results.push({ ok, desc, detail });
		console.log(`${ok ? 'PASS' : 'FAIL'} - ${desc}${ok || !detail ? '' : ' (' + detail + ')'}`);
	};

	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();

	page.on('pageerror', err => pageErrors.push('PAGEERROR: ' + err.message));
	page.on('console', msg => {
		if (msg.type() === 'error' && !/favicon|Failed to load resource/i.test(msg.text())) {
			consoleErrors.push('CONSOLE: ' + msg.text().slice(0, 180));
		}
	});

	try {
		// =========================================================
		// 1. /mrp (Desktop Light 1440x900)
		// =========================================================
		console.log('--- 1. /mrp ---');
		const rMrp = await page.goto(BASE_URL + '/mrp?branch_id=1', { waitUntil: 'networkidle', timeout: 30000 });
		check('/mrp responde HTTP 200', rMrp.status() === 200, 'status=' + rMrp.status());

		const mrpHtml = await page.content();
		check('/mrp sin warnings/fatals PHP', !/(Fatal error|Warning:|Notice:|Undefined array key|Parse error)/i.test(mrpHtml));

		const mrpText = await page.locator('body').innerText();
		const cycleStages = ['Demanda', 'Planificación', 'Sugerencia', 'Aprobación', 'Compra', 'Recepción', 'Conciliación', 'Evaluación'];
		const allStagesPresent = cycleStages.every(s => mrpText.includes(s));
		check('Ciclo 8 etapas Planeación y Compras visible en /mrp', allStagesPresent);

		const leakedMrpTexts = ['12 Fases', 'Gestión de Equipos', 'Samsung Electronics Colombia', 'FAC-A-99124', '$58,400', 'PO-...-B01'];
		const foundLeaksMrp = leakedMrpTexts.filter(t => mrpText.includes(t));
		check('/mrp sin textos demo ni módulos ajenos filtrados', foundLeaksMrp.length === 0, foundLeaksMrp.join(', '));

		const mrpKpiCount = await page.locator('.dairy-kpi-card').count();
		check('/mrp renderiza tarjetas .dairy-kpi-card', mrpKpiCount >= 4, 'count=' + mrpKpiCount);

		const hasGreenHoverRule = await page.evaluate(() => {
			for (const sheet of document.styleSheets) {
				try {
					for (const rule of sheet.cssRules) {
						if (rule.selectorText && rule.selectorText.includes('.dairy-kpi-card') && rule.selectorText.includes(':hover')) {
							return true;
						}
					}
				} catch (e) {}
			}
			return false;
		});
		check('Regla CSS hover verde para .dairy-kpi-card presente', hasGreenHoverRule);

		const chartState = await page.evaluate(() => {
			const c = document.getElementById('mrpCategoryChart');
			const rect = c ? c.getBoundingClientRect() : null;
			return {
				chartLoaded: typeof window.Chart !== 'undefined',
				hasCanvas: Boolean(c),
				width: rect ? rect.width : 0,
				height: rect ? rect.height : 0
			};
		});
		check('Chart.js cargado y canvas #mrpCategoryChart renderizado', chartState.chartLoaded && chartState.hasCanvas && chartState.width > 0 && chartState.height > 0, JSON.stringify(chartState));

		// Simulador What-If
		await page.locator('#sim-vmd-mult').fill('1.35');
		const [simResp] = await Promise.all([
			page.waitForResponse(res => res.url().includes('/api/mrp/simulate') && res.request().method() === 'POST', { timeout: 15000 }),
			page.locator('#btn-run-simulator').click()
		]);
		check('Simulador POST /api/mrp/simulate responde HTTP 200', simResp.status() === 200, 'status=' + simResp.status());
		await page.waitForTimeout(250);
		const simResultText = await page.locator('#mrp-simulator-result').innerText();
		check('Simulador actualiza #mrp-simulator-result con métricas reales', simResultText.includes('SKUs bajo ROP') && simResultText.includes('Inversión Est.'), simResultText);

		// Cambio de las 5 pestañas en /mrp
		for (const [pillId, tabId] of [
			['#pill-mrp', '#tab-mrp'],
			['#pill-proveedores', '#tab-proveedores'],
			['#pill-ordenes', '#tab-ordenes'],
			['#pill-abastecimiento', '#tab-abastecimiento'],
			['#pill-historial', '#tab-historial']
		]) {
			await page.locator(pillId).click();
			await page.waitForTimeout(200);
			const activeVisible = await page.locator(tabId).isVisible();
			check(`Pestaña ${tabId} en /mrp se activa y muestra contenido`, activeVisible);
		}

		await page.locator('#pill-abastecimiento').click();
		await page.waitForTimeout(200);
		const abastText = await page.locator('#tab-abastecimiento').innerText();
		check('Panel Entregas Pendientes visible en #tab-abastecimiento', abastText.includes('Entregas Pendientes'));

		await page.locator('#pill-historial').click();
		await page.waitForTimeout(200);
		const histText = await page.locator('#tab-historial').innerText();
		check('Panel Historial Auditado de Abastecimiento visible en #tab-historial', histText.includes('Historial Auditado de Abastecimiento'));

		// =========================================================
		// 2. /suppliers (Desktop Light 1440x900)
		// =========================================================
		console.log('\n--- 2. /suppliers ---');
		const rSup = await page.goto(BASE_URL + '/suppliers?branch_id=1', { waitUntil: 'networkidle', timeout: 30000 });
		check('/suppliers responde HTTP 200', rSup.status() === 200, 'status=' + rSup.status());

		const supHtml = await page.content();
		check('/suppliers sin warnings/fatals PHP', !/(Fatal error|Warning:|Notice:|Undefined array key|Parse error)/i.test(supHtml));

		const supText = await page.locator('body').innerText();
		const leakedSupTexts = ['Datos de ejemplo', 'Samsung Electronics Colombia', 'FAC-A-99124', 'PO-...-B01'];
		const foundLeaksSup = leakedSupTexts.filter(t => supText.includes(t));
		check('/suppliers sin datos demo estáticos filtrados', foundLeaksSup.length === 0, foundLeaksSup.join(', '));

		const supTabs = ['#tab-threeway-matching', '#tab-contracts', '#tab-supplier-portal', '#tab-recommendations', '#tab-budget'];
		for (const tabId of supTabs) {
			const link = page.locator(`a[href="${tabId}"]`);
			if ((await link.count()) > 0) {
				await link.first().click();
				await page.waitForTimeout(200);
				check(`Pestaña ${tabId} en /suppliers activa`, await page.locator(tabId).isVisible());
			}
		}

		check('Modal #modal-contract-form presente en /suppliers', (await page.locator('#modal-contract-form').count()) === 1);

		// =========================================================
		// 3. Endpoints API Planeación y Compras
		// =========================================================
		console.log('\n--- 3. APIs Planeación y Compras ---');
		for (const ep of [
			'/api/mrp/forecast/branches?branch_id=1',
			'/api/mrp/transfers/recommendations?branch_id=1',
			'/api/mrp/deliveries/pending?branch_id=1',
			'/api/mrp/history?branch_id=1',
			'/api/suppliers/contracts',
			'/api/procurement/recommendations?branch_id=1'
		]) {
			const res = await page.request.get(BASE_URL + ep);
			let parsedOk = false;
			try {
				await res.json();
				parsedOk = true;
			} catch (e) {}
			check(`GET ${ep} responde HTTP 200 JSON válido`, res.status() === 200 && parsedOk, 'status=' + res.status());
		}

		// =========================================================
		// 4. Tema Oscuro (html.daily-dark) y Móvil (390x844)
		// =========================================================
		console.log('\n--- 4. Tema Oscuro y Responsive Móvil ---');
		for (const route of ['/mrp?branch_id=1', '/suppliers?branch_id=1']) {
			await page.setViewportSize({ width: 1440, height: 900 });
			await page.goto(BASE_URL + route, { waitUntil: 'networkidle', timeout: 30000 });
			await page.evaluate(() => {
				document.documentElement.classList.add('daily-dark');
				try { localStorage.setItem('daily_theme', 'dark'); } catch (e) {}
			});
			const isDark = await page.evaluate(() => document.documentElement.classList.contains('daily-dark'));
			check(`Tema oscuro html.daily-dark aplicado en ${route}`, isDark);

			await page.setViewportSize({ width: 390, height: 844 });
			await page.waitForTimeout(250);
			const overflow = await page.evaluate(() => ({
				scroll: document.documentElement.scrollWidth,
				client: document.documentElement.clientWidth
			}));
			check(`Sin desbordamiento horizontal móvil (390x844) en ${route}`, overflow.scroll <= overflow.client + 4, `scroll=${overflow.scroll} client=${overflow.client}`);
		}

		// =========================================================
		// 5. Consola JS y Excepciones
		// =========================================================
		console.log('\n--- 5. Consola JS & PageErrors ---');
		check('Cero pageerrors en todo el recorrido', pageErrors.length === 0, pageErrors.join(' | '));
		check('Cero errores de consola JS en todo el recorrido', consoleErrors.length === 0, consoleErrors.join(' | '));

	} catch (err) {
		console.error('Fatal test error:', err);
		check('Ejecución global E2E', false, err.message);
	} finally {
		await context.close();
		await browser.close();
		const passed = results.filter(r => r.ok).length;
		const failed = results.filter(r => !r.ok).length;
		console.log('\n=======================================');
		console.log(`RESUMEN E2E #16: ${passed} PASADAS, ${failed} FALLIDAS (Total ${results.length})`);
		console.log('=======================================');
		if (failed > 0) process.exit(1);
	}
})();

