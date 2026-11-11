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
	dashboard: ['srepDashTrend', 'srepDashHourly', 'srepDashVsPrev', 'srepDashCum', 'srepDashChannels'],
	departments: ['srepDeptBars', 'srepDeptDonut', 'srepDeptEvo', 'srepDeptVsPrev', 'srepDeptVsLy', 'srepDeptHourly', 'srepDeptCumGoal', 'srepDeptGrowth'],
	products: ['srepProdNet', 'srepProdUnits', 'srepProdEvo', 'srepProdBranch', 'srepProdVar'],
	branches: ['srepBranchBars', 'srepBranchDonut', 'srepBranchVsPrev', 'srepBranchGrowth', 'srepBranchTicket', 'srepBranchMargin', 'srepBranchHourly', 'srepBranchEvo', 'srepBranchHistory'],
	history: ['srepHistTimeline', 'srepHistYears', 'srepHistWeekday', 'srepHistOverlay', 'srepHistWeekends', 'srepHistCumulative', 'srepHistWow', 'srepHistDim'],
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
	const tab_ = (t) => page.evaluate((t) => { const b = [...document.querySelectorAll('[id="pill-' + t + '"], [data-target="#tab-' + t + '"]')].find(x => x.offsetParent !== null) || document.querySelector('[id="pill-' + t + '"], [data-target="#tab-' + t + '"]'); b.click(); }, t);
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

	console.log('\n2a. Tablero de ventas: solo ventas, sin cifras de maqueta');
	const dashTxt = await page.locator('#tab-resumen').innerText();
	ok('Sin cifras de maqueta (669,540 · 41,521 · 1,512.5 % · 14,119 h · 90 Frozen 28.6 %)', !/669,540|41,521|1,512\.5|14,119|15,463|Frozen\s*28\.6/.test(dashTxt));
	ok('Sin KPIs de personal, horas, m² ni mermas', !/Personal asignado|Horas trabajadas|metro cuadrado|Ventas por colaborador|Mermas|Picking|Despacho/i.test(dashTxt));
	ok('Sin dona de departamentos duplicada', !(await page.$('#dedChartDept')) && !(await page.$('#srepDashGoal')));
	const D0 = data.dashboard;
	ok('Bruto − descuentos = neto', Math.abs(D0.gross - D0.discounts - D0.net) < 0.05, [D0.gross, D0.discounts, D0.net]);
	ok('Cumplimiento sin tope (venta ÷ meta)', D0.goal === null || Math.abs(D0.goal_pct - D0.net / D0.goal * 100) < 0.1, [D0.goal_pct, D0.net, D0.goal]);
	ok('Comparaciones de igual duración', D0.comparisons.every(c => c.key === 'mtd' ? c.prev_days <= c.days : c.days === c.prev_days), D0.comparisons.map(c => [c.key, c.days, c.prev_days]));
	ok('Tabla de comparaciones con todas las filas', (await page.locator('[data-testid="srep-dash-comparisons"] tbody tr').count()) === D0.comparisons.length);
	ok('Top 5 = Productos', JSON.stringify(D0.top5.map(r => r.id)) === JSON.stringify(data.products.rows.slice(0, 5).map(r => r.id)));
	await page.click('[data-srep-mix="payments"]'); await page.waitForTimeout(250);
	ok('Canal → medio de pago', await page.evaluate((n) => window.DailySalesReports.charts.srepDashChannels.data.datasets.length === n, D0.payments.length));
	await page.click('[data-srep-mix="channels"]');
	await page.locator('[data-testid="srep-dash-top5"] [data-srep-goto="productos"]').first().click(); await page.waitForTimeout(500);
	ok('Top 5 lleva a Productos', await page.locator('#srep-products').isVisible());
	await tab_('resumen'); await page.waitForTimeout(400);

	await tab_('departamentos'); await page.waitForTimeout(400);
	ok('Departamentos: 5 KPIs comerciales exactos', (await page.locator('#srep-departments .srep-dept-kpis-5 .ded-kpi-card').count()) === 5);
	const deptKey = data.departments.rows[1].key;
	await page.selectOption('#srepDeptSelect', deptKey);
	await page.waitForTimeout(350);
	const dd = await page.locator('#srepDeptDetail').innerText();
	ok('Selección de departamento actualiza detalle', dd.includes('Participación') && dd.includes((data.departments.rows[1].share_pct).toFixed(1)), dd.slice(0, 120));
	ok('Selección actualiza evolución (una serie)', await page.evaluate(() => window.DailySalesReports.charts.srepDeptEvo.data.datasets.length) === 1);
	ok('Selección resalta fila de tabla', (await page.locator(`#srep-departments tr.is-selected[data-srep-dept-row="${deptKey}"]`).count()) >= 1);
	// Drilldown Departamento -> Subcategoría -> SKU + granularidades de tiempo
	for (const g of ['hour', 'week', 'month', 'day']) await page.click(`#srepDeptDetail [data-srep-detail-gran="${g}"]`);
	ok('Detalle depto: gráfico hora/día/semana/mes dibujado', await page.evaluate(() => !!window.DailySalesReports.charts.srepDeptDetailTimeChart));
	if (await page.locator('#srepDeptDetail [data-testid="srep-dept-subcats"] tr[data-drill-sub]').count() > 0) {
		await page.locator('#srepDeptDetail [data-testid="srep-dept-subcats"] tr[data-drill-sub]').first().click();
		await page.waitForTimeout(250);
		ok('Drilldown Subcategoría muestra tabla de SKUs', await page.locator('#srepDeptDetail [data-testid="srep-subcat-skus"]').isVisible());
		if (await page.locator('#srepDeptDetail [data-testid="srep-subcat-skus"] tr[data-drill-sku]').count() > 0) {
			await page.locator('#srepDeptDetail [data-testid="srep-subcat-skus"] tr[data-drill-sku]').first().click();
			await page.waitForTimeout(200);
			ok('Drilldown SKU muestra ficha comercial', await page.locator('#srepDeptDetail [data-testid="srep-sku-detail"]').isVisible());
		}
	}
	await page.selectOption('#srepDeptSelect', '');

	// Solo información comercial, crecimiento, evo semanal, acumulados vs meta, hora, mapa por sucursal y tabla analítica
	const deptText = await page.locator('#tab-departamentos').innerText();
	ok('Departamentos sin indicadores operativos ni rentabilidad ajena', !/Productividad laboral|Costo laboral|Eficiencia operativa|reposición|Merma|Disponibilidad|What-If|Margen bruto departamental/i.test(deptText));
	ok('Conciliación y origen de datos visibles', /Σ departamentos/.test(await page.locator('[data-testid="srep-dept-reconciliation"]').innerText()) && await page.locator('[data-testid="srep-dept-origin"]').isVisible());
	await page.click('#srep-departments [data-srep-evo="weekly"]'); await page.waitForTimeout(250);
	ok('Evolución semanal alterna correctamente', await page.evaluate(() => /Sem /.test(String(window.DailySalesReports.charts.srepDeptEvo.data.labels[0] || ''))));
	await page.click('#srep-departments [data-srep-evo="daily"]');
	await page.click('#srep-departments [data-srep-cum="curve"]'); await page.waitForTimeout(250);
	ok('Curva acumulada vs meta dibujada', await page.evaluate(() => window.DailySalesReports.charts.srepDeptCumGoal.config.type === 'line'));
	await page.click('#srep-departments [data-srep-cum="dept"]');
	await page.click('#srep-departments [data-srep-growth="year"]'); await page.waitForTimeout(300);
	ok('Crecimiento anual visible', await page.locator('#srep-departments [data-srep-growth-note="year"]').isVisible());
	await page.click('#srep-departments [data-srep-hour="share"]'); await page.waitForTimeout(300);
	ok('Ventas por hora en % del día', await page.evaluate(() => window.DailySalesReports.charts.srepDeptHourly.options.scales.yAxes[0].ticks.callback(10) === '10 %'));
	await page.click('#srep-departments [data-srep-heat="share"]'); await page.waitForTimeout(200);
	ok('Mapa por sucursal en % de la sucursal', /%/.test(await page.locator('#srepDeptHeat tbody td.srep-heat-cell').first().innerText()));
	ok('Tabla analítica tiene las 10 columnas comerciales exactas', (await page.locator('#srepDeptTable thead th').count()) === 10);
	// Expandir subcategorías + multi-selección + búsqueda
	if (await page.locator('#srepDeptTable [data-srep-expand]').count() > 0) {
		await page.locator('#srepDeptTable [data-srep-expand]').first().click();
		ok('Tabla: expande subcategorías inline', (await page.locator('#srepDeptTable tbody tr.srep-subcat-row').count()) >= 1);
	}
	await page.locator('#srepDeptTable .srep-dept-chk').nth(0).check();
	await page.locator('#srepDeptTable .srep-dept-chk').nth(1).check();
	ok('Tabla: multi-selección activa resumen comparativo', await page.locator('#srepDeptMultiBar').isVisible());
	await page.click('#srepDeptMultiClear');
	await page.fill('#srepDeptSearch', data.departments.rows[0].name.slice(0, 4));
	ok('Tabla: buscador filtra departamentos', (await page.locator('#srepDeptTable tbody tr[data-srep-dept-row]:visible').count()) >= 1);
	await page.fill('#srepDeptSearch', '');
	const deptSheets = await page.evaluate(() => window.DailySalesReports.sheets('departments').map(s => [s[0], s[1].length]));
	ok('Excel Departamentos incluye 5 KPIs y hoja Subcategorías', deptSheets.some(s => s[0] === 'Indicadores' && s[1] === 11) && deptSheets.some(s => s[0] === 'Subcategorías' && s[1] >= 2), deptSheets);
	await page.click('#srepDeptConfig > summary'); await page.waitForTimeout(200);
	ok('Editor de departamentos con filas', (await page.locator('#srepDeptConfigTable tbody tr').count()) >= 10);
	const wasDark = await page.evaluate(() => document.documentElement.classList.contains('daily-dark'));
	if (wasDark) { await page.evaluate(() => { if (window.DailyTheme && window.DailyTheme.set) window.DailyTheme.set('light', { save: false }); else document.documentElement.classList.remove('daily-dark'); }); await page.waitForTimeout(350); }
	await page.screenshot({ path: path.join(out, 'departamentos.png'), fullPage: true });
	if (wasDark) { await page.evaluate(() => { if (window.DailyTheme && window.DailyTheme.set) window.DailyTheme.set('dark', { save: false }); else document.documentElement.classList.add('daily-dark'); }); await page.waitForTimeout(250); }

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
	await page.click(`#srep-branches .ded-dept-row[data-srep-branch="${br.id}"]`);
	await page.waitForTimeout(300);
	ok('Clic en la participación selecciona sucursal', (await page.inputValue('#srepBranchSelect')) === String(br.id) && (await page.locator('#srepBranchDetail').innerText()).includes('3 de'));
	ok('Selección filtra la evolución histórica (una serie)', await page.evaluate(() => window.DailySalesReports.charts.srepBranchHistory.data.datasets.length) === 1);
	await page.selectOption('#srepBranchSelect', '');
	const ranks = await page.$$eval('#srep-branches .srep-sortable tbody tr', trs => trs.map(t => parseFloat(t.cells[3].getAttribute('data-v'))));
	ok('Ranking de sucursales descendente', ranks.every((v, i) => i === 0 || ranks[i - 1] >= v), ranks);
	if (data.branches.with_coords > 0) ok('Mapa con las sucursales con coordenadas', (await page.locator('#srepBranchMap .branch-map-node').count()) === data.branches.with_coords);
	else ok('Sin coordenadas: mapa no inventa posiciones', (await page.locator('#srepBranchMap').count()) === 0 && await page.locator('[data-testid="srep-branch-map-empty"]').isVisible());

	// Prompt 04: solo información comercial; sin datos ≠ cero; ranking con períodos completos; mismas fechas
	const brText = await page.locator('#tab-sucursales').innerText();
	const brOps = brText.match(/.{0,40}(Incidencia|Intervenci|Bitácora|BHI|Matriz de supervisión|Mockup|Inventario|Traslado|Robo|Merma|Horarios? del personal|Turnos).{0,40}/i);
	ok('Sucursales sin herramientas operativas (incidencias, visitas, BHI, inventario, personal)', !brOps, brOps && brOps[0]);
	ok('Conciliación Σ sucursales visible', /Σ sucursales/.test(await page.locator('[data-testid="srep-branch-reconciliation"]').innerText()));
	const nd = data.branches.rows.filter(r => r.no_data);
	if (nd.length) ok('Sucursal sin datos se muestra «Sin datos», no en cero', /Sin datos/.test(await page.locator(`#srep-branches tr[data-srep-branch-row="${nd[0].id}"]`).innerText()));
	ok('Ranking solo con períodos completos', data.branches.rows.every(r => (r.rank !== null) === r.complete));
	await page.click('#srep-branches [data-srep-bgrowth="year"]'); await page.waitForTimeout(300);
	ok('Crecimiento anual visible', await page.locator('#srep-branches [data-srep-bgrowth-note="year"]').isVisible());
	await page.click('#srep-branches [data-srep-bhour="share"]'); await page.waitForTimeout(300);
	ok('Ventas por hora en % del día', await page.evaluate(() => window.DailySalesReports.charts.srepBranchHourly.options.scales.yAxes[0].ticks.callback(10) === '10 %'));
	await page.screenshot({ path: path.join(out, 'sucursales.png'), fullPage: true });
	await tab_('detalle'); await page.waitForTimeout(400);
	for (const g of ['week', 'month', 'year', 'day']) await page.click(`[data-srep-grain="${g}"]`);
	ok('Línea temporal día/semana/mes/año', await page.evaluate(() => window.DailySalesReports.charts.srepHistTimeline.data.labels.length) === data.history.timeline.day.labels.length);
	ok('Calendario de calor', (await page.locator('#srepHistCalendar i:not(.is-out)').count()) === data.history.calendar.length);
	for (const d of ['dept', 'sku', 'channel', 'branch']) { await page.click(`[data-srep-hdim="${d}"]`); await page.waitForTimeout(150); }
	ok('Histórico por sucursal/departamento/SKU/canal', await page.evaluate(() => window.DailySalesReports.charts.srepHistDim.data.labels.length === 13 && !document.querySelector('[data-srep-hdim-table="branch"]').hidden && document.querySelector('[data-srep-hdim-table="sku"]').hidden));
	ok('Curva acumulada termina en la venta del período', await page.evaluate((n) => Math.abs(window.DailySalesReports.charts.srepHistCumulative.data.datasets[0].data.slice(-1)[0] - n) < 1, data.history.net));
	ok('Auditoría: duplicados, registro tardío, devoluciones, cambios, correcciones y cierres', (await page.locator('#srepHistAudit [data-testid^="srep-hist-"]').count()) === 6);
	const [cdl] = await Promise.all([page.waitForEvent('download', { timeout: 20000 }), page.click('#srep-history [data-srep-export="csv"]')]);
	const csv = fs.readFileSync(await cdl.path(), 'utf8');
	ok('CSV del historial con indicadores y tablas', /\.csv$/.test(cdl.suggestedFilename()) && csv.includes('Indicadores') && csv.includes('Histórico por sucursal') && csv.includes(';'), cdl.suggestedFilename());
	await page.locator('#srep-history').screenshot({ path: path.join(out, 'historial.png') });

	console.log('\n3. Exportaciones');
	await tab_('rentabilidad'); await page.waitForTimeout(500);
	ok('Rentabilidad: sin herramientas de otros procesos (mermas, kardex, conteos, despachos, horarios)', await page.evaluate(() => !document.querySelector('#tab-rentabilidad a[href*="/waste"], #tab-rentabilidad a[href*="/kardex"], #tab-rentabilidad a[href*="/inventory"], #tab-rentabilidad a[href*="/dispatch"], #tab-rentabilidad a[href*="/schedule"]') && !document.querySelector('#tab-rentabilidad [data-testid="block-margins"]')));
	ok('Rentabilidad: comparación, descuentos, departamento, sucursal, SKU, margen negativo, sin costo, precio/volumen/costo y contribución', (await page.locator('#srep-profitability [data-testid^="srep-pf-"]').count()) >= 9);
	const skuRows = await page.locator('#srepPfSkuTable tbody tr:not(.srep-hidden)').count();
	await page.fill('#srep-profitability [data-srep-search="#srepPfSkuTable"]', data.profitability.products[0].name.slice(0, 6));
	const skuFiltered = await page.locator('#srepPfSkuTable tbody tr:not(.srep-hidden)').count();
	const q6 = data.profitability.products[0].name.slice(0, 6).toLowerCase();
	ok('Rentabilidad por SKU: búsqueda filtra', skuFiltered >= 1 && skuFiltered === data.profitability.products.filter(p => (p.sku + ' ' + p.name + ' ' + p.dept).toLowerCase().includes(q6)).length, [skuRows, skuFiltered]);
	await page.fill('#srep-profitability [data-srep-search="#srepPfSkuTable"]', '');
	const [dl] =await Promise.all([page.waitForEvent('download', { timeout: 20000 }), page.click('#srep-profitability [data-srep-export="excel"]')]);
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
	await tab_('departamentos'); await page.waitForTimeout(500);
	await page.locator('#srep-departments').screenshot({ path: path.join(out, 'mobile_departamentos.png') });
	await page.setViewportSize({ width: 1440, height: 900 });
	await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
	await tab_('departamentos'); await page.waitForTimeout(500);
	await page.locator('#srep-departments').screenshot({ path: path.join(out, 'dark_departamentos.png') });
	await tab_('rentabilidad'); await page.waitForTimeout(500);
	await page.locator('#srep-profitability').screenshot({ path: path.join(out, 'dark_profitability.png') });
	await page.evaluate(() => document.documentElement.classList.remove('daily-dark'));

	ok('Sin errores de JavaScript', errors.length === 0, errors);
	await browser.close();
	const fails = results.filter(r => !r.ok).length;
	console.log(`\n${results.length - fails}/${results.length} OK`);
	process.exit(fails ? 1 : 0);
})();
