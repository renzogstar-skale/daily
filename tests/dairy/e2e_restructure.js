// E2E de la reestructuración DAILY: orden del menú, rutas, filtros compartidos, Ventas (5 bloques), Agotados, Panel y móvil.
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/e2e_restructure.js <carpeta_salida>
const fs = require('fs');
const path = require('path');
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const results = [];
const ok = (name, cond, info) => { results.push({ name, ok: !!cond, info: info === undefined ? '' : info }); console.log((cond ? '  OK   ' : '  FAIL ') + name + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 200) + ']' : '')); };

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [], failed = [];
	// /api es el explorador REST heredado de Grocy (su error de 'require' es previo y ajeno a DAILY)
	page.on('pageerror', e => { if (!/\/api$/.test(page.url())) errors.push(page.url() + ' :: ' + e.message.slice(0, 160)); });
	page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text()) && !/\/api$/.test(page.url())) errors.push(page.url() + ' :: ' + m.text().slice(0, 160)); });
	page.on('response', r => { if (r.status() >= 400 && !/favicon/.test(r.url())) failed.push(r.status() + ' ' + r.url().replace(BASE, '')); });
	const go = async (u) => { const r = await page.goto(BASE + u, { waitUntil: 'domcontentloaded', timeout: 120000 }); await page.waitForTimeout(1200); return r ? r.status() : 0; };
	const kpi = (id) => page.locator(`[data-testid="kpi-${id}"] .dkpi-value`).first().innerText().catch(() => null);

	console.log('\n1. Menú lateral');
	await go('/today');
	await page.evaluate(() => { try { localStorage.removeItem('daily.filters.v1'); } catch (e) { } });
	const cats = await page.evaluate(() => [...document.querySelectorAll('#sidebarResponsive *')].filter(e => e.children.length === 0 && /^(SUPERVISIÓN Y CONTROL|\d\. [A-ZÁÉÍÓÚ ]+|INDICADORES FINANCIEROS)$/.test((e.textContent || '').trim())).map(e => e.textContent.trim()));
	const expected = ['SUPERVISIÓN Y CONTROL', '1. VENTAS Y RENTABILIDAD', '2. INVENTARIO Y ALMACENES', '3. DESPACHOS Y DEVOLUCIONES', '4. MERMAS Y PREVENCIÓN', '5. OPERACIONES Y PERSONAL', '6. ADMINISTRACIÓN Y SISTEMA', '7. PLANEACIÓN Y COMPRAS', 'INDICADORES FINANCIEROS'];
	const uniq = [...new Set(cats)];
	ok('Orden de categorías', JSON.stringify(uniq) === JSON.stringify(expected), uniq);
	const links = await page.evaluate(() => [...new Set([...document.querySelectorAll('#sidebarResponsive a[href]')].map(a => a.getAttribute('href')).filter(h => h && !h.startsWith('#') && !h.startsWith('?') && !/logout|javascript|set-role/.test(h)))]);
	ok('Menú con enlaces', links.length > 40, links.length);

	console.log('\n2. Todas las rutas del menú responden y no quedan en blanco');
	const routeReport = [];
	for (const href of links) {
		const u = href.replace(BASE, '');
		let st = 0, len = 0;
		try { st = await go(u); len = await page.evaluate(() => document.querySelector('.error-page, [data-error]') || /Origen del error|Fatal error/.test(document.body.innerText) ? 0 : document.body.innerText.trim().length); } catch (e) { st = -1; }
		routeReport.push({ u, st, len });
		ok('Ruta ' + u, st === 200 && len > 0, { st, len });
	}
	for (const legacy of ['/inventory', '/orders', '/reports', '/customers', '/loss-prevention#tab-expedientes', '/waste#tab-investigaciones']) {
		const st = await go(legacy);
		ok('Ruta existente conservada ' + legacy, st === 200, st);
	}

	console.log('\n3. Ventas · cinco bloques');
	await go('/sales');
	for (const b of ['evolution', 'comparison', 'productivity', 'top5', 'traffic']) ok('Bloque ' + b, await page.locator(`[data-testid="block-${b}"]`).count() === 1);
	const netAll = await kpi('net-sales');
	ok('Ventas netas con valor', netAll && netAll.startsWith('$'), netAll);
	ok('KPI con período, definición y fuente', await page.locator('[data-testid="kpi-net-sales"] dt').allInnerTexts().then(t => ['Período', 'Definición', 'Fuente', 'Actualización'].every(x => t.includes(x))));
	ok('Tráfico sin información (no inventado)', (await kpi('traffic')) === 'Sin información');
	ok('Top 5 con filas', (await page.locator('[data-testid="top5-table"] tbody tr').count()) > 0);
	for (const v of ['hourly', 'weekly', 'cumulative', 'daily']) { await page.click(`[data-evo="${v}"]`); await page.waitForTimeout(200); }
	ok('Gráfico de evolución dibujado', await page.evaluate(() => { const c = document.getElementById('sdashEvo'); return !!c && c.width > 0; }));
	await page.screenshot({ path: path.join(out, 'sales_desk.png'), fullPage: false });

	console.log('\n4. Filtro por departamento');
	await Promise.all([page.waitForURL(/department=carnes/, { timeout: 120000 }), page.selectOption('[data-testid="filter-department"]', 'carnes')]);
	await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1200);
	ok('URL con departamento', page.url().includes('department=carnes'), page.url());
	const netMeat = await kpi('net-sales');
	ok('Ventas cambian al filtrar Carnes', netMeat !== netAll, [netAll, netMeat]);
	ok('Contexto muestra el departamento', (await page.locator('[data-testid="sales-context"]').innerText()).includes('Carnes'));
	const deps = await page.locator('[data-testid="top5-table"] tbody tr td:nth-child(4)').allInnerTexts();
	ok('Top 5 solo de Carnes', deps.length === 0 || deps.every(d => /Carnes/.test(d)), deps);
	ok('Productividad no aplica por departamento', await page.locator('[data-testid="productivity-na"]').count() === 1);
	ok('Análisis complementario oculto (no mezcla departamentos)', await page.locator('[data-testid="legacy-hidden"]').count() === 1);

	console.log('\n5. Persistencia entre tableros y restablecer');
	await go('/sales?department=carnes');
	const todayHref = await page.locator('#sidebarResponsive a[href*="/today"]').first().getAttribute('href');
	ok('Enlace a HOY lleva el departamento', /department=carnes/.test(todayHref || ''), todayHref);
	const soHref = await page.locator('#sidebarResponsive a[href*="/inventory/stockouts"]').first().getAttribute('href');
	ok('Enlace a Agotados lleva el departamento', /department=carnes/.test(soHref || ''), soHref);
	ok('Aviso de filtros activos', await page.locator('[data-testid="filters-chip"]').count() === 1);
	await page.click('[data-testid="filter-reset"]');
	await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1200);
	const after = await page.locator('#sidebarResponsive a[href*="/today"]').first().getAttribute('href');
	ok('Restablecer limpia los filtros', !/department=/.test(after || '') && !/department=/.test(page.url()), [after, page.url()]);

	console.log('\n6. Filtro por producto y fechas');
	await go('/sales?start_date=2026-10-01&end_date=2026-10-31');
	ok('Período en el contexto', (await page.locator('[data-testid="sales-context"]').innerText()).includes('1–31 de octubre de 2026'));
	ok('KPI con período correcto', (await page.locator('[data-testid="kpi-net-sales"] dd').first().innerText()).includes('1–31 de octubre de 2026'));
	const sku = await page.locator('[data-testid="top5-table"] tbody tr td:nth-child(3)').first().innerText();
	const topNet = await page.locator('[data-testid="top5-table"] tbody tr td:nth-child(6)').first().innerText();
	await page.fill('[data-testid="filter-product"]', sku.trim());
	await page.press('[data-testid="filter-product"]', 'Enter');
	await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1200);
	const rows = await page.locator('[data-testid="top5-table"] tbody tr').count();
	ok('Filtro por SKU deja un producto', rows === 1, rows);
	const skuNet = await kpi('net-sales');
	ok('Ventas del SKU = fila del Top 5', skuNet && topNet && Math.abs(parseFloat(skuNet.replace(/[$,]/g, '')) - parseFloat(topNet.replace(/[$,]/g, ''))) < 1, [skuNet, topNet]);
	await page.click('[data-testid="top5-detail"]');
	await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1000);
	ok('Detalle del producto abre', page.url().includes('product_id=') && (await page.locator('[data-testid="block-top5"]').count()) === 1, page.url());

	console.log('\n7. Productos agotados');
	await go('/inventory/stockouts');
	const outN = await kpi('stockouts');
	ok('Conteo de agotados', outN !== null && outN !== 'Sin información', outN);
	const firstSku = await page.locator('[data-testid="stockout-row"] td:nth-child(3)').first().innerText().catch(() => '');
	if (firstSku && firstSku !== '—') {
		await page.fill('[data-testid="filter-product"]', firstSku.trim());
		await page.click('[data-testid="filter-apply"]');
		await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1000);
		const skus = await page.locator('[data-testid="stockout-row"] td:nth-child(3)').allInnerTexts();
		ok('Búsqueda por SKU', skus.length > 0 && skus.every(s => s.trim() === firstSku.trim()), skus.slice(0, 3));
		await page.click('[data-testid="stockout-detail"]');
		await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1000);
		ok('Detalle (Kardex) abre', page.url().includes('/kardex') && page.url().includes('product_id='), page.url());
	}
	await go('/inventory/stockouts?department=carnes');
	const deptRows = await page.locator('[data-testid="stockout-row"] td:nth-child(4)').allInnerTexts();
	ok('Agotados filtrados por departamento', deptRows.every(d => /Carnes/.test(d)), deptRows.slice(0, 3));

	console.log('\n8. Panel ejecutivo y márgenes');
	await go('/kpis');
	ok('Panel con indicadores reales', await page.locator('[data-testid="exec-real-kpis"] .dkpi').count() === 6);
	ok('Sin cifra fija $128,450', !(await page.locator('[data-testid="exec-real-kpis"]').innerText()).includes('128,450'));
	ok('Sin marca de terceros en el selector', !(await page.locator('#exec-select-branch').innerText()).includes('Alkosto'));
	await go('/sales?tab=rentabilidad');
	ok('Margen diario/mensual/acumulado', await page.locator('[data-testid="block-margins"] .dkpi').count() === 3);

	console.log('\n9. Móvil');
	const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
	const mp = await mob.newPage();
	for (const u of ['/sales', '/inventory/stockouts', '/kpis', '/today']) {
		await mp.goto(BASE + u, { waitUntil: 'domcontentloaded', timeout: 120000 }); await mp.waitForTimeout(1200);
		const hs = await mp.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
		ok('Sin desplazamiento horizontal ' + u, !hs);
		await mp.screenshot({ path: path.join(out, u.replace(/\W/g, '_') + '_mob.png') });
	}

	console.log('\n10. Consola y solicitudes');
	ok('Sin errores de consola', errors.length === 0, errors.slice(0, 5));
	ok('Sin solicitudes fallidas', failed.length === 0, [...new Set(failed)].slice(0, 8));

	fs.writeFileSync(path.join(out, 'e2e_restructure.json'), JSON.stringify({ results, errors, failed: [...new Set(failed)], routes: routeReport }, null, 1));
	const f = results.filter(r => !r.ok).length;
	console.log(`\n${results.length} verificaciones, ${f} fallidas`);
	await browser.close();
	process.exit(f ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
