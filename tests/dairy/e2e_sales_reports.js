// E2E de los informes comerciales de Ventas y Rentabilidad (sales_reports.blade.php + daily_sales_reports.js).
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/e2e_sales_reports.js <carpeta_salida>
const fs = require('fs');
const path = require('path');
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const results = [];
const ok = (name, cond, info) => { results.push({ name, ok: !!cond }); console.log((cond ? '  OK   ' : '  FAIL ') + name + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 220) + ']' : '')); };

const TABS = { resumen: 'dashboard', departamentos: 'departments', productos: 'products', sucursales: 'branches', detalle: 'history', rentabilidad: 'profitability' };
const CANVAS = {
	dashboard: ['srepDashTrend', 'srepDashGoal', 'srepDashHourly', 'srepDashVsPrev', 'srepDashCum'],
	departments: ['srepDeptBars', 'srepDeptDonut', 'srepDeptEvo', 'srepDeptVsPrev'],
	products: ['srepProdNet', 'srepProdUnits', 'srepProdEvo', 'srepProdBranch', 'srepProdVar'],
	branches: ['srepBranchBars', 'srepBranchVsPrev', 'srepBranchEvo'],
	history: ['srepHistTimeline', 'srepHistYears', 'srepHistWeekday', 'srepHistOverlay', 'srepHistWeekends'],
	profitability: ['srepPfMargin', 'srepPfNetCogs', 'srepPfDept', 'srepPfBranch', 'srepPfScatter', 'srepPfTrend']
};

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(page.url() + ' :: ' + e.message.slice(0, 160)));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(page.url() + ' :: ' + m.text().slice(0, 160)); });
	page.on('console', m => { if (m.type() === 'warning' && /Informe /.test(m.text())) errors.push('builder :: ' + m.text().slice(0, 160)); });
	const go = async (u) => { const r = await page.goto(BASE + u, { waitUntil: 'load', timeout: 180000 }); await page.waitForTimeout(900); return r ? r.status() : 0; };
	const tab_ = (t) => page.evaluate((t) => { const b = [...document.querySelectorAll('[id="pill-' + t + '"]')].find(x => x.offsetParent !== null) || document.querySelector('[id="pill-' + t + '"]'); b.click(); }, t);
	const drawn = (ids) => page.evaluate((ids) => ids.filter(id => { const c = document.getElementById(id); return c && c.width > 0 && window.DailySalesReports.charts[id]; }), ids);

	console.log('\n1. Cada pestaña muestra su informe con período, indicadores y gráficos');
	await go('/sales?tab=resumen');
	await page.evaluate(() => { try { localStorage.removeItem('daily.filters.v1'); } catch (e) { } });
	const data = await page.evaluate(() => JSON.parse(document.getElementById('srepData').textContent));
	ok('Datos del informe presentes', !!data && !!data.dashboard);
	ok('Consistencia total = Σ sucursal = Σ depto = Σ producto', data.consistency.ok, data.consistency);
	for (const [tab, key] of Object.entries(TABS)) {
		await tab_(tab);
		await page.waitForTimeout(700);
		const sec = `#srep-${key}`;
		ok(`[${tab}] sección visible`, await page.locator(sec).isVisible());
		ok(`[${tab}] período y rango visibles`, /Período: \d{2}\/\d{2}\/\d{4} – \d{2}\/\d{2}\/\d{4}/.test(await page.locator(`${sec} [data-testid="srep-period"]`).innerText()));
		ok(`[${tab}] indicadores`, (await page.locator(`${sec} .srep-kpis .ded-kpi-card`).count()) >= 5);
		const d = await drawn(CANVAS[key]);
		ok(`[${tab}] gráficos dibujados`, d.length === CANVAS[key].length, CANVAS[key].filter(x => !d.includes(x)));
		const txt = await page.locator(sec).innerText();
		ok(`[${tab}] sin NaN/undefined/Infinity`, !/NaN|undefined|Infinity/.test(txt));
		ok(`[${tab}] URL conserva la pestaña`, page.url().includes('tab=' + tab));
	}
	ok('Pestaña renombrada a «Ventas por departamento»', (await page.$$eval('[id="pill-departamentos"]', bs => bs.every(b => b.innerText.trim() === 'Ventas por departamento'))));
	ok('Sin «Rendimiento por departamento» visible', !(await page.evaluate(() => document.body.innerText.includes('Rendimiento por departamento'))));

	console.log('\n2. Interacciones');
	await tab_('resumen'); await page.waitForTimeout(400);
	for (const w of ['7', '90', '30']) await page.click(`[data-srep-window="${w}"]`);
	ok('Evolución 7/30/90 días', await page.evaluate(() => window.DailySalesReports.charts.srepDashTrend.data.labels.length) === 30);

	await tab_('departamentos'); await page.waitForTimeout(400);
	const deptKey = data.departments.rows[1].key;
	await page.selectOption('#srepDeptSelect', deptKey);
	await page.waitForTimeout(300);
	const dd = await page.locator('#srepDeptDetail').innerText();
	ok('Selección de departamento actualiza detalle', dd.includes('Participación') && dd.includes((data.departments.rows[1].share_pct).toFixed(1)), dd.slice(0, 120));
	ok('Selección actualiza evolución (una serie)', await page.evaluate(() => window.DailySalesReports.charts.srepDeptEvo.data.datasets.length) === 1);
	ok('Selección resalta fila de tabla', (await page.locator(`#srep-departments tr.is-selected[data-srep-dept-row="${deptKey}"]`).count()) >= 1);
	await page.selectOption('#srepDeptSelect', '');

	await tab_('productos'); await page.waitForTimeout(500);
	const rowsVisible = await page.locator('#srepProdTable tbody tr:not(.srep-hidden)').count();
	ok('Tabla de productos paginada (25)', rowsVisible === Math.min(25, data.products.rows.length), rowsVisible);
	const sku = data.products.rows[3].sku;
	await page.fill('[data-srep-search="#srepProdTable"]', sku);
	await page.waitForTimeout(1200);
	ok('Búsqueda por SKU', (await page.locator('#srepProdTable tbody tr:not(.srep-hidden)').count()) >= 1 && (await page.locator('#srepProdTable tbody tr:not(.srep-hidden)').first().innerText()).includes(sku));
	await page.fill('[data-srep-search="#srepProdTable"]', '');
	await page.click('#srepProdTable th:has-text("Unidades")'); await page.click('#srepProdTable th:has-text("Unidades")');
	const u = await page.$$eval('#srepProdTable tbody tr', trs => trs.slice(0, 5).map(t => parseFloat(t.cells[5].getAttribute('data-v'))));
	ok('Orden por columna (unidades desc)', u.every((v, i) => i === 0 || u[i - 1] >= v), u);
	ok('Treemap dibujado', (await page.locator('#srepProdTreemap .srep-tm-cell').count()) > 5);

	await tab_('sucursales'); await page.waitForTimeout(400);
	const br = data.branches.rows[2];
	await page.click(`#srepBranchMap [data-srep-branch="${br.id}"]`);
	await page.waitForTimeout(300);
	ok('Clic en el mapa selecciona sucursal', (await page.inputValue('#srepBranchSelect')) === String(br.id) && (await page.locator('#srepBranchDetail').innerText()).includes('3 de'));
	await page.selectOption('#srepBranchSelect', '');
	const ranks = await page.$$eval('#srep-branches .srep-sortable tbody tr', trs => trs.map(t => parseFloat(t.cells[2].getAttribute('data-v'))));
	ok('Ranking de sucursales descendente', ranks.every((v, i) => i === 0 || ranks[i - 1] >= v));
	ok('Mapa con todas las sucursales', (await page.locator('#srepBranchMap .branch-map-node').count()) === data.branches.rows.length);

	await tab_('detalle'); await page.waitForTimeout(400);
	for (const g of ['week', 'month', 'year', 'day']) await page.click(`[data-srep-grain="${g}"]`);
	ok('Línea temporal día/semana/mes/año', await page.evaluate(() => window.DailySalesReports.charts.srepHistTimeline.data.labels.length) === data.history.timeline.day.labels.length);
	ok('Calendario de calor', (await page.locator('#srepHistCalendar i:not(.is-out)').count()) === data.history.calendar.length);

	console.log('\n3. Exportaciones');
	await tab_('rentabilidad'); await page.waitForTimeout(500);
	const [dl] = await Promise.all([page.waitForEvent('download', { timeout: 20000 }), page.click('#srep-profitability [data-srep-export="excel"]')]);
	const xf = path.join(out, dl.suggestedFilename());
	await dl.saveAs(xf);
	const buf = fs.readFileSync(xf);
	const s = buf.toString('latin1');
	ok('Excel .xlsx válido (ZIP con hojas)', buf.slice(0, 2).toString() === 'PK' && /xl\/worksheets\/sheet1\.xml/.test(s) && /xl\/workbook\.xml/.test(s), dl.suggestedFilename());
	ok('Excel con indicadores y utilidad', s.includes('Utilidad bruta') && s.includes(data.period.from.slice(0, 4)));
	const sheets = await page.evaluate(() => window.DailySalesReports.sheets('profitability').map(s => [s[0], s[1].length]));
	ok('Excel incluye tablas y datos de gráficos', sheets.length >= 8, sheets);
	await tab_('productos'); await page.waitForTimeout(300);
	const prodSheet = await page.evaluate(() => window.DailySalesReports.sheets('products').find(s => s[0] === 'Productos'));
	ok('Excel de productos con todas las filas (no solo las 25 visibles)', prodSheet && prodSheet[1].length === data.products.rows.length + 1, prodSheet && prodSheet[1].length);
	const [pop] = await Promise.all([page.waitForEvent('popup', { timeout: 20000 }), page.evaluate(() => { window.__print = 0; document.querySelector('#srep-products [data-srep-export="pdf"]').click(); })]);
	await pop.evaluate(() => { window.print = () => { window.__printed = 1; }; });
	await pop.waitForLoadState('load');
	await pop.waitForTimeout(800);
	ok('PDF: ventana de impresión con el informe e imágenes de gráficos', (await pop.locator('.srep').count()) === 1 && (await pop.locator('.srep img').count()) >= 4 && (await pop.locator('.srep canvas').count()) === 0);
	await pop.close();

	console.log('\n4. Filtros sincronizados');
	await go('/sales?tab=sucursales&branch_id=' + br.id + '&start_date=' + data.period.from + '&end_date=' + data.period.to);
	const d2 = await page.evaluate(() => JSON.parse(document.getElementById('srepData').textContent));
	ok('Filtro de sucursal: total = venta de la sucursal', Math.abs(d2.dashboard.net - br.net) < 0.05, [d2.dashboard.net, br.net]);
	ok('Filtro de sucursal: Σ departamentos = total', Math.abs(d2.departments.rows.reduce((a, r) => a + r.net, 0) - d2.dashboard.net) < 1);
	ok('Filtro de sucursal: una sola fila en el ranking', d2.branches.rows.length === 1);
	await go('/sales?tab=productos&department=' + deptKey + '&compare_with=year_ago');
	const d3 = await page.evaluate(() => JSON.parse(document.getElementById('srepData').textContent));
	ok('Filtro de departamento aplicado a productos', d3.departments.rows.length === 1 && d3.departments.rows[0].key === deptKey);
	ok('Comparación interanual', d3.period.compare_with === 'year_ago' && /año anterior/.test(await page.locator('#srep-products [data-testid="srep-period"]').innerText()));
	ok('Departamento filtrado: meta del departamento', d3.dashboard.goal > 0, d3.dashboard.goal);
	const now = new Date(), iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	const qStart = iso(new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1));
	await go('/sales?tab=rentabilidad');
	await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 180000 }), page.click('.sales-period-pill:has-text("Trimestre actual")')]);
	ok('Filtro «Trimestre actual» (barra de filtros)', page.url().includes('start_date=' + qStart) && page.url().includes('tab=rentabilidad'), page.url());
	await go('/sales?tab=resumen');
	await page.waitForTimeout(1200);
	await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 180000 }), page.evaluate(() => document.querySelector('[data-select-range="quarter"]').click())]);
	ok('Filtro «Trimestre actual» (selector del Tablero)', page.url().includes('start_date=' + qStart) && page.url().includes('end_date=' + iso(now)), page.url());
	await page.waitForTimeout(1200);
	await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 180000 }), page.evaluate(() => document.querySelector('[data-select-range="yesterday"]').click())]);
	const yd = iso(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1));
	ok('Filtro «Ayer» (selector del Tablero)', page.url().includes('start_date=' + yd) && page.url().includes('end_date=' + yd), page.url());
	await go('/sales?tab=detalle&start_date=2001-01-01&end_date=2001-01-07');
	ok('Período sin ventas: «Sin información» sin errores', (await page.locator('#srep-history').innerText()).includes('Sin información'));

	console.log('\n5. Móvil, modo oscuro e inglés');
	await go('/sales?tab=resumen');
	await page.setViewportSize({ width: 390, height: 844 });
	for (const [tab, key] of Object.entries(TABS)) {
		await tab_(tab);
		await page.waitForTimeout(500);
		const over = await page.evaluate((k) => { const s = document.getElementById('srep-' + k); const vw = document.documentElement.clientWidth; return Math.max(0, ...[s, ...s.querySelectorAll('*')].filter(e => e.offsetParent !== null && !(e.parentElement && e.parentElement.closest('.srep-scroll, .chartjs-size-monitor'))).map(e => Math.round(e.getBoundingClientRect().right - vw))); }, key);
		ok(`[móvil ${tab}] sin desborde horizontal de la sección`, over <= 2, over);
	}
	await tab_('resumen'); await page.waitForTimeout(500);
	await page.locator('#srep-dashboard').screenshot({ path: path.join(out, 'mobile_dashboard.png') });
	await page.setViewportSize({ width: 1440, height: 900 });
	await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
	await tab_('rentabilidad'); await page.waitForTimeout(500);
	await page.locator('#srep-profitability').screenshot({ path: path.join(out, 'dark_profitability.png') });
	await page.evaluate(() => document.documentElement.classList.remove('daily-dark'));

	ok('Sin errores de JavaScript', errors.length === 0, errors);
	await browser.close();
	const fails = results.filter(r => !r.ok).length;
	console.log(`\n${results.length - fails}/${results.length} OK`);
	process.exit(fails ? 1 : 0);
})();
