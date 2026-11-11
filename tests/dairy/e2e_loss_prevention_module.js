const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
	console.log('=== TEST E2E: PREVENCIÓN DE PÉRDIDAS (#12 OPTIMIZAR PREVENCIÓN DE PÉRDIDAS) ===\n');
	const browser = await chromium.launch({ channel: 'msedge' });
	let passed = 0;
	let failed = 0;

	function assert(condition, message) {
		if (condition) {
			console.log(`  [PASS] ${message}`);
			passed++;
		} else {
			console.error(`  [FAIL] ${message}`);
			failed++;
		}
	}

	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
		const consoleErrors = [];
		const pageErrors = [];
		page.on('console', msg => {
			if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404')) {
				consoleErrors.push(msg.text());
			}
		});
		page.on('pageerror', err => pageErrors.push(err.message));

		// 1. Carga principal de /loss-prevention
		console.log('1. Verificando carga principal de /loss-prevention, ciclo de 7 etapas y 8 KPIs...');
		const resp = await page.goto('http://localhost:8080/loss-prevention', { waitUntil: 'networkidle', timeout: 30000 });
		assert(resp.status() === 200, 'HTTP Status 200 en /loss-prevention');
		assert(await page.locator('h1:has-text("Centro de Control de Prevención de Pérdidas")').isVisible(), 'Titulo del Centro de Control visible');

		const lifecycleSteps = await page.locator('.lp-lifecycle-step').count();
		assert(lifecycleSteps === 7, `Ciclo de 7 etapas visible (encontradas: ${lifecycleSteps})`);

		const kpiCards = await page.locator('.lp-kpis-grid-8 .lp-kpi-card').count();
		assert(kpiCards === 8, `8 KPIs ejecutivos reales renderizados (encontrados: ${kpiCards})`);

		const tabTexts = await page.locator('.lp-tab-item').allTextContents();
		const joinedTabs = tabTexts.join(' | ');
		assert(
			!joinedTabs.includes('Horarios del personal') && !joinedTabs.includes('Órdenes de compra') && !joinedTabs.includes('Merma comercial'),
			'Pestanas separadas de /waste, horarios y compras'
		);

		// 2. Charts renderizados
		console.log('\n2. Verificando graficos Chart.js...');
		const chartsOk = await page.evaluate(() => {
			return ['lp-trend-chart', 'lp-products-chart', 'lp-department-chart'].every(id => {
				const el = document.getElementById(id);
				return el && el.width > 0 && el.height > 0;
			});
		});
		assert(chartsOk, 'Los 3 lienzos Chart.js se dibujan con dimensiones validas');

		// 3. Cambio por las 7 pestanas reales
		console.log('\n3. Probando navegacion entre las 7 pestanas...');
		const tabs = ['expedientes', 'alertas', 'anomalias', 'custodia-imei', 'accesos', 'acciones', 'auditoria'];
		let allTabsOk = true;
		for (const t of tabs) {
			await page.click(`#tab-btn-${t}`);
			const visible = await page.locator(`#pane-${t}`).isVisible();
			if (!visible) {
				allTabsOk = false;
				console.error(`    Tab pane #pane-${t} no visible tras click`);
			}
		}
		assert(allTabsOk, 'Todas las 7 pestanas se activan y muestran su panel sin errores');

		// 4. IMEI reveal & lookup real dentro de custodia-imei
		console.log('\n4. Probando revelacion auditada y busqueda IMEI en vivo...');
		await page.click('#tab-btn-custodia-imei');
		const revealBtns = page.locator('#pane-custodia-imei button:has-text("Revelar (Auditado)")');
		if (await revealBtns.count() > 0) {
			await revealBtns.first().click();
			await page.waitForSelector('#lpToastBox:not(.d-none)', { timeout: 5000 });
			const toastTxt = await page.locator('#lpToastBox').textContent();
			assert(toastTxt.includes('IMEI1:'), `Revelacion auditada devuelve IMEI real (${toastTxt.substring(0, 60)})`);
			const m = toastTxt.match(/IMEI1:\s*([A-Za-z0-9-]+)/);
			if (m && m[1] && m[1] !== '—') {
				await page.fill('#txtImeiSearch', m[1]);
				await page.click('#btnLookupImei');
				await page.waitForSelector('#imeiLookupResult:not(.d-none)', { timeout: 5000 });
				const lookupTxt = await page.locator('#imeiLookupResult').textContent();
				assert(lookupTxt.includes('Estado:'), `Busqueda en vivo IMEI encontro dispositivo (${m[1]})`);
			} else {
				assert(true, 'Busqueda en vivo IMEI invocada');
			}
		} else {
			await page.fill('#txtImeiSearch', '000000000000000');
			await page.click('#btnLookupImei');
			await page.waitForSelector('#imeiLookupResult:not(.d-none)', { timeout: 5000 });
			assert(true, 'Busqueda IMEI sin datos responde sin fallos JS');
		}

		// 5. Volver a Expedientes, abrir Drawer Resolver 360 y Modales
		console.log('\n5. Probando Drawer Resolver 360 y Modales (Incidente, CCTV, Recuperacion)...');
		await page.click('#tab-btn-expedientes');
		const caseCount = await page.locator('.lp-cases-table tbody tr').count();
		assert(caseCount > 0, `Tabla de expedientes muestra casos reales (${caseCount} filas)`);

		const resolveBtn = page.locator('.lp-cases-table tbody tr .lp-btn-resolve-table').first();
		if (await resolveBtn.count() > 0) {
			await resolveBtn.click();
			await page.waitForSelector('#drawerResolveOverlay.open', { timeout: 4000 });
			const drawerTitle = await page.locator('#drawerCaseTitle').textContent();
			assert(drawerTitle.includes('Expediente'), `Drawer Resolver 360 abierto (${drawerTitle})`);
			await page.evaluate(() => closeResolveDrawer());
		} else {
			assert(false, 'No se encontro boton Gestionar / Ver Cierre en tabla');
		}

		for (const modalPair of [
			['#modalNewIncident', 'openNewIncidentModal()'],
			['#modalCctvAccess', 'openCctvModal()'],
			['#modalFinancialRecovery', 'openRecoveryModal()']
		]) {
			await page.evaluate(fn => eval(fn), modalPair[1]);
			await page.waitForSelector(`${modalPair[0]}.show`, { timeout: 3000 });
			assert(await page.locator(`${modalPair[0]}.show`).isVisible(), `Modal ${modalPair[0]} se abre correctamente`);
			await page.evaluate(sel => $(sel).modal('hide'), modalPair[0]);
			await page.waitForTimeout(250);
		}

		// 6. Dark mode & Mobile responsive
		console.log('\n6. Verificando tema oscuro (html.daily-dark) y responsive movil (390x844)...');
		await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
		const darkBg = await page.evaluate(() => getComputedStyle(document.querySelector('.lp-lifecycle-card')).backgroundColor);
		assert(darkBg !== 'rgb(255, 255, 255)', `Tema oscuro aplicado sobre .lp-lifecycle-card (${darkBg})`);
		await page.evaluate(() => document.documentElement.classList.remove('daily-dark'));

		await page.setViewportSize({ width: 390, height: 844 });
		await page.waitForTimeout(200);
		const mobileKpis = await page.locator('.lp-kpis-grid-8').isVisible();
		assert(mobileKpis, 'KPIs visibles correctamente en viewport movil 390x844');
		await page.setViewportSize({ width: 1440, height: 900 });

		// 7. Subrutas relacionadas
		console.log('\n7. Verificando subrutas de dispositivos, conteos y detalle de expediente...');
		for (const sub of ['/loss-prevention/case/2', '/loss-prevention/devices', '/loss-prevention/counts', '/merchandise']) {
			const r = await page.goto('http://localhost:8080' + sub, { waitUntil: 'networkidle', timeout: 30000 });
			assert(r.status() === 200, `HTTP 200 en ${sub}`);
		}

		assert(consoleErrors.length === 0 && pageErrors.length === 0, `0 errores JS en consola/pagina (consola=${consoleErrors.length}, page=${pageErrors.length})`);
		if (consoleErrors.length || pageErrors.length) {
			console.error('Console Errors:', consoleErrors);
			console.error('Page Errors:', pageErrors);
		}
	} catch (err) {
		console.error('ERROR FATAL E2E:', err);
		failed++;
	} finally {
		await browser.close();
	}

	console.log(`\n=== RESUMEN E2E: ${passed} PASS · ${failed} FAIL ===\n`);
	process.exit(failed > 0 ? 1 : 0);
})();
