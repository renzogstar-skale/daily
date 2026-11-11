// E2E Auditoría Completa Módulo 17: Panel Ejecutivo (/kpis) y HOY (/today)
// Cubre: contenido sin maqueta, filtros/botones/formularios/gráficos, enlaces del ciclo retail, permisos/errores y responsive (Desktop, Tablet, Móvil) Light/Dark.
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

const BASE = process.env.DAILY_URL || 'http://127.0.0.1:18080';
const results = [];
function ok(name, cond, info) {
	results.push({ name, ok: !!cond, info: info === undefined ? '' : info });
	console.log((cond ? '  OK   ' : '  FAIL ') + name + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 180) + ']' : ''));
}

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();
	const consoleErrors = [];
	const pageErrors = [];
	const httpErrors = [];

	page.on('pageerror', e => pageErrors.push(page.url() + ' :: ' + e.message));
	page.on('console', m => {
		if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) {
			consoleErrors.push(page.url() + ' :: ' + m.text());
		}
	});
	page.on('response', r => {
		// 403 esperado se probará explícitamente aparte si aplica; aquí vigilamos errores inesperados
		if (r.status() >= 400 && !/favicon/.test(r.url()) && !r.url().includes('simulated_role=supervisor&branch_id=2')) {
			httpErrors.push(r.status() + ' ' + r.url().replace(BASE, ''));
		}
	});

	// =========================================================================
	// 1. PANEL EJECUTIVO (/kpis): botones, filtros, formulario custom, gráficos y enlaces
	// =========================================================================
	console.log('\n1. Panel Ejecutivo (/kpis)');
	const rKpis = await page.goto(BASE + '/kpis', { waitUntil: 'domcontentloaded', timeout: 60000 });
	await page.waitForTimeout(1000);
	ok('/kpis HTTP 200', rKpis && rKpis.status() === 200, rKpis && rKpis.status());
	ok('/kpis tiene 6 KPIs reales en [data-testid="exec-real-kpis"] .dkpi',
		await page.locator('[data-testid="exec-real-kpis"] .dkpi').count() === 6);

	const kpisText = await page.locator('#dailyExecutiveDashboardApp').innerText();
	ok('/kpis sin cifras falsas ni maqueta colombiana (669,540 / 128,450 / Alkosto / Renzo G. / 84.5%)',
		!/669,540|128,450|Alkosto|Renzo G\.|Envigado|Carlos Mendoza/.test(kpisText));
	ok('/kpis selector #exec-select-branch existe y excluye Alkosto',
		(await page.locator('#exec-select-branch').count()) === 1 && !(await page.locator('#exec-select-branch').innerText()).includes('Alkosto'));
	ok('/kpis no contiene enlace roto /kpis/settings',
		(await page.locator('#dailyExecutiveDashboardApp a[href*="/kpis/settings"]').count()) === 0);

	// Gráfico Chart.js #dedChartExecEvo
	const canvasBox = await page.locator('#dedChartExecEvo').boundingBox();
	ok('/kpis gráfico #dedChartExecEvo renderizado con dimensiones > 0',
		!!canvasBox && canvasBox.width > 100 && canvasBox.height > 100, canvasBox);

	// Secciones estratégicas clave (sin duplicar inventarios detallados / kardex / picking / horarios)
	ok('/kpis contiene Resumen Financiero, Ranking Sucursales, 7 Procesos, Acciones Correctivas y Definiciones KPI',
		kpisText.includes('Resumen Financiero Consolidado') &&
		kpisText.includes('Desempeño Comparativo por Sucursal') &&
		kpisText.includes('Estado Consolidado de los 7 Procesos') &&
		kpisText.includes('Seguimiento de Acciones Correctivas') &&
		kpisText.includes('Gobierno de Indicadores, Definiciones'));

	// Dropdown #btnExecDates, #btnExecDepartments y #btnExecUserAvatar funcionan
	await page.click('#btnExecDates');
	await page.waitForTimeout(150);
	ok('/kpis dropdown #btnExecDates abre menú (.show)',
		await page.locator('[aria-labelledby="btnExecDates"].show').count() === 1);

	// Formulario de rango personalizado dentro de #btnExecDates
	await Promise.all([
		page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
		page.click('[aria-labelledby="btnExecDates"] button[type="submit"]')
	]);
	await page.waitForTimeout(500);
	ok('/kpis formulario de rango personalizado aplica period=custom',
		page.url().includes('period=custom') && page.url().includes('start_date='), page.url());

	await page.click('#btnExecDepartments');
	await page.waitForTimeout(150);
	ok('/kpis dropdown #btnExecDepartments abre menú (.show)',
		await page.locator('[aria-labelledby="btnExecDepartments"].show').count() === 1);

	await page.click('#btnExecUserAvatar');
	await page.waitForTimeout(150);
	ok('/kpis dropdown #btnExecUserAvatar abre menú (.show)',
		await page.locator('[aria-labelledby="btnExecUserAvatar"].show').count() === 1);

	// Botones de navegación interna (.ded-nav-tab)
	const navTabCount = await page.locator('#dailyExecutiveDashboardApp .ded-nav-tab').count();
	if (navTabCount > 1) {
		await page.locator('#dailyExecutiveDashboardApp .ded-nav-tab').nth(1).click();
	}
	ok('/kpis pestañas internas de salto rápido presentes', navTabCount >= 4, navTabCount);

	// Filtro por sucursal en #exec-select-branch
	const branchVals = await page.evaluate(() => [...document.querySelectorAll('#exec-select-branch option')].map(o => o.value).filter(Boolean));
	if (branchVals.length > 0) {
		await Promise.all([
			page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
			page.selectOption('#exec-select-branch', branchVals[0])
		]);
		await page.waitForTimeout(500);
		ok('/kpis filtro sucursal actualiza URL', page.url().includes('branch_id=' + branchVals[0]), page.url());
	}

	// Filtro departamento en #btnExecDepartments
	await page.click('#btnExecDepartments');
	await page.waitForTimeout(150);
	const deptLink = page.locator('[aria-labelledby="btnExecDepartments"] a.ded-dropdown-item').nth(1);
	if (await deptLink.count()) {
		await Promise.all([
			page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
			deptLink.click()
		]);
		await page.waitForTimeout(500);
		ok('/kpis filtro departamento navega con ?department=', page.url().includes('department='), page.url());
	}

	// API /api/kpi/executive normal y manejo de rango personalizado malformado
	const apiExecCheck = await page.evaluate(async () => {
		const r1 = await fetch('/api/kpi/executive?period=month&branch_id=1');
		const d1 = await r1.json();
		const rBad = await fetch('/api/kpi/executive?period=custom&start_date=bad&end_date=nope');
		const dBad = await rBad.json();
		return { s1: r1.status, ok1: !!d1.executive_panel && Array.isArray(d1.branch_ranking) && !JSON.stringify(d1).includes('Alkosto'), sBad: rBad.status, startBad: dBad.start_date };
	});
	ok('/api/kpi/executive responde 200 con executive_panel y branch_ranking real', apiExecCheck.s1 === 200 && apiExecCheck.ok1);
	ok('/api/kpi/executive con fechas custom malformadas responde 200 seguro', apiExecCheck.sBad === 200 && /^\d{4}-\d{2}-\d{2}$/.test(apiExecCheck.startBad || ''));

	// Validar que TODOS los enlaces de módulos operativos dentro de #dailyExecutiveDashboardApp responden HTTP 200 (excl. /logout)
	const kpiLinks = await page.evaluate(() => [...new Set([...document.querySelectorAll('#dailyExecutiveDashboardApp a[href]')].map(a => a.getAttribute('href')).filter(h => h && !h.includes('/logout') && !h.startsWith('#') && !h.startsWith('javascript:')))]);
	const brokenKpiLinks = [];
	for (const href of kpiLinks) {
		const res = await page.evaluate(async (u) => (await fetch(u, { method: 'GET' })).status, href);
		if (res >= 400) brokenKpiLinks.push({ href, status: res });
	}
	ok('/kpis todos los enlaces de navegación y ciclo retail responden HTTP < 400 (' + kpiLinks.length + ' enlaces)', brokenKpiLinks.length === 0, brokenKpiLinks);

	// =========================================================================
	// 2. DASHBOARD HOY (/today): filtros AJAX, fechas sin datos, 3 niveles y 5 gráficos
	// =========================================================================
	console.log('\n2. Dashboard HOY (/today)');
	const rToday = await page.goto(BASE + '/today', { waitUntil: 'domcontentloaded', timeout: 60000 });
	await page.waitForTimeout(1000);
	ok('/today HTTP 200', rToday && rToday.status() === 200, rToday && rToday.status());

	ok('/today Pulse Banner tiene Ventas, Cumplimiento y Margen (#pulse-margin-val)',
		(await page.locator('#pulse-sales-val').count()) === 1 &&
		(await page.locator('#pulse-target-val').count()) === 1 &&
		(await page.locator('#pulse-margin-val').count()) === 1);

	ok('/today eliminó métrica duplicada #prod-cov-val y reclasificó "Horas programadas"',
		(await page.locator('#prod-cov-val').count()) === 0 &&
		(await page.locator('#daily-tab-commercial').innerText()).includes('Horas programadas') &&
		!(await page.locator('#daily-tab-commercial').innerText()).includes('Horas extra'));

	ok('/today enlaces corregidos (/dispatches en vez de /dispatch, sin /kpis?tab=cogs ni /inventory/stockouts en #today-exec-container)',
		(await page.locator('#today-exec-container a[href*="/dispatches"]').count()) >= 1 &&
		(await page.locator('#today-exec-container a[href*="/dispatch"]:not([href*="/dispatches"])').count()) === 0 &&
		(await page.locator('#today-exec-container a[href*="/kpis?tab=cogs"]').count()) === 0 &&
		(await page.locator('#today-exec-container a[href*="/inventory/stockouts"]').count()) === 0);

	ok('/today Tab 3 eliminó analítica pesada de mermas (#cc-day-grid, #cc-yoy, #cc-dist) y rosters granulares (#cc-seg-box)',
		(await page.locator('#cc-day-grid, #cc-yoy, #cc-dist, #cc-seg-box').count()) === 0);

	// Validar que los 3 gráficos Chart.js de Tab 1 renderizan con dimensiones > 0
	const cMain = await page.locator('#execMainChart').boundingBox();
	const cDonut = await page.locator('#execDonutChart').boundingBox();
	const cComp = await page.locator('#execComparativeChart').boundingBox();
	ok('/today Tab 1 renderiza los 3 gráficos Chart.js (#execMainChart, #execDonutChart, #execComparativeChart)',
		!!cMain && cMain.width > 100 && !!cDonut && cDonut.width > 80 && !!cComp && cComp.width > 100,
		{ cMain, cDonut, cComp });

	// Validar selects locales de gráficos y mapa de calor
	await page.selectOption('#exec-donut-metric', 'margin');
	await page.selectOption('#exec-comparative-metric', 'margin');
	await Promise.all([
		page.waitForResponse(r => r.url().includes('/api/today/sales-executive')),
		page.selectOption('#exec-heatmap-metric', 'tx')
	]);
	ok('/today controles de gráfico (#exec-donut-metric, #exec-comparative-metric) y mapa de calor (#exec-heatmap-metric) responden', true);

	// Cambio dinámico de sucursal, departamento y refresco en HOY via AJAX
	const todayBranches = await page.evaluate(() => [...document.querySelectorAll('#exec-filter-branch option')].map(o => o.value).filter(v => v && v !== 'all'));
	if (todayBranches.length > 0) {
		const [apiResp] = await Promise.all([
			page.waitForResponse(r => r.url().includes('/api/today/sales-executive')),
			page.selectOption('#exec-filter-branch', todayBranches[0])
		]);
		await page.waitForTimeout(300);
		ok('/today filtro sucursal llama a /api/today/sales-executive con 200', apiResp.status() === 200);
	}

	const [refreshResp] = await Promise.all([
		page.waitForResponse(r => r.url().includes('/api/today/sales-executive')),
		page.click('#exec-refresh-btn')
	]);
	ok('/today botón #exec-refresh-btn actualiza vía AJAX con 200', refreshResp.status() === 200);

	// Fecha sin información ("2019-01-05") no genera NaN / undefined / error HTTP
	await page.fill('#exec-filter-date', '2019-01-05');
	const [emptyResp] = await Promise.all([
		page.waitForResponse(r => r.url().includes('/api/today/sales-executive')),
		page.dispatchEvent('#exec-filter-date', 'change')
	]);
	await page.waitForTimeout(300);
	const emptyPulseText = await page.locator('#pulse-sales-val').innerText();
	ok('/today con fecha sin ventas muestra "Sin información" limpiamente', emptyResp.status() === 200 && emptyPulseText.includes('Sin información'), emptyPulseText);

	// Restaurar fecha 2026-11-10
	await page.fill('#exec-filter-date', '2026-11-10');
	await Promise.all([
		page.waitForResponse(r => r.url().includes('/api/today/sales-executive')),
		page.dispatchEvent('#exec-filter-date', 'change')
	]);
	await page.waitForTimeout(300);

	// Tab 2: Procesos y enlaces del ciclo retail
	await page.click('.daily-tab-btn[data-tab-target="#daily-tab-processes"]');
	await page.waitForTimeout(300);
	const procText = await page.locator('#today-process-table').innerText();
	ok('/today Tab 2 muestra Desviación, Impacto, Tendencia y Responsable',
		/desviaci[oó]n/i.test(procText) && /impacto/i.test(procText) && /tendencia/i.test(procText) && procText.includes('Responsable:'));

	const todayProcLinks = await page.evaluate(() => [...new Set([...document.querySelectorAll('#today-process-table a[href]')].map(a => a.getAttribute('href')))]);
	const brokenProcLinks = [];
	for (const href of todayProcLinks) {
		const st = await page.evaluate(async (u) => (await fetch(u)).status, href);
		if (st >= 400) brokenProcLinks.push({ href, st });
	}
	ok('/today Tab 2: todos los enlaces de los 7 procesos responden HTTP < 400 (' + todayProcLinks.length + ')', brokenProcLinks.length === 0, brokenProcLinks);

	// Tab 3: Operaciones, gráficos #cc-trend y #cc-hourly, filtros de días y pills
	await page.click('.daily-tab-btn[data-tab-target="#daily-tab-operations"]');
	await page.waitForTimeout(400);
	ok('/today Tab 3 muestra Seguimiento de acciones correctivas',
		(await page.locator('#cc-ca-t').count()) === 1);
	const ccTrendBox = await page.locator('#cc-trend').boundingBox();
	const ccHourlyBox = await page.locator('#cc-hourly').boundingBox();
	ok('/today Tab 3 gráficos #cc-trend y #cc-hourly renderizan al abrir pestaña',
		!!ccTrendBox && ccTrendBox.width > 100 && !!ccHourlyBox && ccHourlyBox.width > 100,
		{ ccTrendBox, ccHourlyBox });

	await page.click('#daily-tab-operations .cc-tab[data-days="7"]');
	await page.click('#daily-tab-operations .cc-pill[data-st="critical"]');
	await page.click('#daily-tab-operations .cc-pill[data-st="all"]');
	await page.click('.daily-tab-btn[data-tab-target="#daily-tab-commercial"]');
	await page.waitForTimeout(250);
	ok('/today controles de Tab 3 (7d, pills, retorno a Tab 1) funcionan sin errores JS', true);

	// =========================================================================
	// 3. PERMISOS (rol simulado supervisor: oculta /admin/economics y bloquea sucursal ajena)
	// =========================================================================
	console.log('\n3. Permisos y alcance por sucursal');
	await page.goto(BASE + '/kpis?simulated_role=supervisor', { waitUntil: 'domcontentloaded', timeout: 60000 });
	await page.waitForTimeout(400);
	const supHasEconLink = await page.locator('#dailyExecutiveDashboardApp a[href*="/admin/economics"]').count();
	const supForeignApi = await page.evaluate(async () => (await fetch('/api/kpi/executive?simulated_role=supervisor&branch_id=2')).status);
	ok('Supervisor en /kpis no expone enlaces de solo-admin (/admin/economics)', supHasEconLink === 0, supHasEconLink);
	ok('Supervisor solicitando branch_id=2 en /api/kpi/executive recibe HTTP 403', supForeignApi === 403, supForeignApi);

	// =========================================================================
	// 4. DARK MODE + ESCRITORIO (1440), TABLET (768x1024) Y CELULAR (390x844)
	// =========================================================================
	console.log('\n4. Responsive (Desktop, Tablet, Celular) + Modo Oscuro');
	const viewports = [
		{ label: 'Desktop 1440x900', width: 1440, height: 900 },
		{ label: 'Tablet 768x1024', width: 768, height: 1024 },
		{ label: 'Móvil 390x844', width: 390, height: 844 }
	];
	for (const url of ['/kpis', '/today']) {
		await page.setViewportSize({ width: 1440, height: 900 });
		await page.goto(BASE + url, { waitUntil: 'domcontentloaded', timeout: 60000 });
		await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
		await page.waitForTimeout(250);
		const darkBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
		ok('Dark mode aplicado en ' + url, darkBg !== 'rgb(255, 255, 255)', darkBg);

		for (const vp of viewports) {
			await page.setViewportSize({ width: vp.width, height: vp.height });
			await page.waitForTimeout(300);
			const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
			ok('Sin overflow horizontal (' + vp.label + ') en ' + url, !overflow);
		}
	}

	ok('Sin errores JS en consola ni excepciones no capturadas', consoleErrors.length === 0 && pageErrors.length === 0, { consoleErrors, pageErrors });
	ok('Sin respuestas HTTP >= 400 inesperadas', httpErrors.length === 0, httpErrors);

	await browser.close();
	const fails = results.filter(r => !r.ok).length;
	console.log(`\nResumen E2E: ${results.length} pruebas, ${fails} fallidas`);
	process.exit(fails ? 1 : 0);
})().catch(e => {
	console.error(e);
	process.exit(2);
});
