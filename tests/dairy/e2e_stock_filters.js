// Prueba de navegador de los filtros de tarjetas de /stockoverview. Se ejecuta con Playwright: función (page) => resultados.
async (page) => {
	const res = [];
	const ok = (name, cond, info) => res.push((cond ? 'OK   ' : 'FAIL ') + name + (cond || info === undefined ? '' : ' :: ' + JSON.stringify(info)));
	const errors = [];
	page.on('pageerror', e => errors.push('pageerror: ' + e.message));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text()); });
	const apiCalls = [];
	page.on('request', r => { if (/api\/stockoverview\/(heatmap|top-products|expiring|executive)/.test(r.url())) apiCalls.push(r.url()); });

	const idle = async () => { await page.waitForFunction(() => !document.querySelector('.dsm-widget-busy')); };
	const menu = () => page.locator('.dsm-fdd-menu');
	const pick = async (btn, label) => {
		await page.click('#' + btn);
		await menu().getByRole('option', { name: label, exact: true }).click();
		await page.waitForTimeout(30);
		await idle();
	};
	const text = s => page.locator(s).first().innerText();
	const cols = () => page.$$eval('#dsm-heatmap-grid .dsm-hm-row:first-child .dsm-hm-cell', e => e.length);
	const colors = () => page.$$eval('#dsm-heatmap-grid .dsm-hm-cell', e => e.map(x => x.style.backgroundColor).join());
	const topRows = () => page.$$eval('#dsm-top10-tbody tr[data-product-id]', e => e.length);
	const money = s => Number(s.replace(/[^0-9.-]/g, ''));
	const noOverlay = async () => page.evaluate(() => !document.querySelector('.modal-backdrop') && !document.querySelector('.dsm-fdd-menu') && !document.body.classList.contains('modal-open'));

	const run = async (label) => {
		await page.goto('http://localhost:8080/stockoverview');
		await page.waitForLoadState('load'); await page.evaluate(() => sessionStorage.clear());
		await page.waitForLoadState('load'); await page.waitForTimeout(300); await page.goto('http://localhost:8080/stockoverview');
		await page.waitForSelector('#dsm-heatmap-grid .dsm-hm-cell');
		await idle();

		// Apertura, cierre y recorte
		for (const id of ['dsm-hm-metric-btn', 'dsm-hm-period-btn', 'dsm-top10-sort-btn', 'dsm-top10-limit-btn', 'dsm-top10-dept-btn', 'dsm-fefo-days-btn', 'dsm-fefo-dept-btn']) {
			await page.locator('#' + id).scrollIntoViewIfNeeded();
			await page.click('#' + id);
			const box = await menu().boundingBox();
			const vp = page.viewportSize();
			ok(label + ' abre ' + id, !!box && await page.getAttribute('#' + id, 'aria-expanded') === 'true');
			ok(label + ' no cortado ' + id, box && box.x >= 0 && box.y >= 0 && box.x + box.width <= vp.width && box.y + box.height <= vp.height, box);
			const hit = await page.evaluate(({ x, y }) => !!document.elementFromPoint(x, y)?.closest('.dsm-fdd-menu'), { x: box.x + box.width / 2, y: box.y + box.height - 8 });
			ok(label + ' encima de todo ' + id, hit);
			await page.mouse.click(2, 2);
			ok(label + ' cierra fuera ' + id, await menu().count() === 0);
		}

		// Mapa de calor
		const c14 = await cols();
		const base = await colors();
		const avg0 = await text('#dsm-hm-stat-avg');
		ok(label + ' 14 columnas', c14 === 14, c14);
		await pick('dsm-hm-metric-btn', 'Valor del inventario ($)');
		ok(label + ' métrica cambia título y promedio', /valor/i.test(await text('#dsm-hm-title')) && (await text('#dsm-hm-stat-avg')).includes('$') && (await text('#dsm-hm-stat-avg')) !== avg0);
		await pick('dsm-hm-metric-btn', 'Productos con stock crítico');
		ok(label + ' colores recalculados', (await colors()) !== base);
		ok(label + ' descripción de métrica', /mínimo o agotados/.test(await text('#dsm-hm-desc')));
		await pick('dsm-hm-period-btn', 'Últimos 90 días');
		ok(label + ' 90 columnas', (await cols()) === 90);
		const scroll = await page.$eval('.dsm-heatmap-wrap', w => ({ sw: w.scrollWidth, cw: w.clientWidth }));
		ok(label + ' scroll horizontal en el mapa', scroll.sw > scroll.cw, scroll);
		ok(label + ' página sin scroll horizontal', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
		await page.click('#dsm-hm-period-btn');
		await menu().getByRole('option', { name: 'Rango personalizado' }).click();
		await menu().locator('[name=from]').fill('2026-10-10');
		await menu().locator('[name=to]').fill('2026-10-01');
		await menu().getByRole('button', { name: 'Aplicar rango' }).click();
		ok(label + ' rango inválido avisa', (await text('.dsm-fdd-err')).length > 0);
		await menu().locator('[name=to]').fill('2026-10-20');
		await menu().getByRole('button', { name: 'Aplicar rango' }).click();
		await page.waitForTimeout(30);
		await idle();
		ok(label + ' rango personalizado 11 columnas', (await cols()) === 11);
		await page.click('.dsm-fdd-clear[data-widget=hm]');
		await page.waitForTimeout(30);
		await idle();
		ok(label + ' restablecer mapa', (await cols()) === 14 && (await text('#dsm-hm-metric-btn')).includes('Unidades'));

		// Top
		await pick('dsm-top10-limit-btn', 'Top 5');
		ok(label + ' Top 5 filas y título', (await topRows()) === 5 && (await text('#dsm-top10-title')).startsWith('Top 5') && (await text('#dsm-top10-foot-label')) === 'Total Top 5');
		const f5 = await text('#dsm-top10-foot-total');
		await pick('dsm-top10-sort-btn', 'Menor valor de inventario');
		ok(label + ' menor valor título y total', /menor valor/.test(await text('#dsm-top10-title')) && (await text('#dsm-top10-foot-total')) !== f5);
		const vals = await page.$$eval('#dsm-top10-tbody tr', e => e.map(t => Number(t.children[3].innerText.replace(/[^0-9.]/g, ''))));
		ok(label + ' orden ascendente en pantalla', vals.every((v, i) => !i || v >= vals[i - 1]), vals);
		ok(label + ' total = suma de filas', Math.abs(vals.reduce((a, b) => a + b, 0) - money(await text('#dsm-top10-foot-total'))) <= vals.length, vals);
		await pick('dsm-top10-sort-btn', 'Mayor rotación');
		ok(label + ' rotación visible', (await text('#dsm-top10-tbody .dsm-top-note')).includes('Rotación'));

		await page.click('#dsm-top10-dept-btn');
		await menu().locator('.dsm-fdd-search').fill('carn');
		ok(label + ' búsqueda de departamento', (await menu().locator('.dsm-fdd-opt:visible').count()) === 1);
		await menu().locator('.dsm-fdd-search').fill('zzz');
		ok(label + ' búsqueda sin resultados', (await menu().locator('.dsm-fdd-empty').count()) === 1);
		await menu().locator('.dsm-fdd-search').fill('');
		await menu().getByRole('option', { name: 'Carnes', exact: true }).click();
		await menu().getByRole('option', { name: 'Panadería', exact: true }).click();
		await menu().getByRole('button', { name: 'Aplicar' }).click();
		await page.waitForTimeout(30);
		await idle();
		const depts = await page.$$eval('#dsm-top10-tbody tr[data-product-id]', e => [...new Set(e.map(t => t.children[2].innerText))]);
		ok(label + ' multidepartamento', depts.length > 0 && depts.every(d => ['Carnes', 'Panadería'].includes(d)), depts);
		ok(label + ' etiqueta multi', (await text('#dsm-top10-dept-btn')).includes('2 departamentos'));

		// Vencimientos
		const riskOf = async () => money(await text('#dsm-fefo-foot-risk'));
		const r30 = await riskOf();
		await pick('dsm-fefo-days-btn', 'Próximos 7 días');
		const r7 = await riskOf();
		await pick('dsm-fefo-days-btn', 'Próximos 60 días');
		const r60 = await riskOf();
		ok(label + ' valor en riesgo sube con la ventana', r7 <= r30 && r30 <= r60 && r7 < r60, [r7, r30, r60]);
		await pick('dsm-fefo-days-btn', 'Vencidos');
		const days = await page.$$eval('#dsm-fefo-tbody .dsm-days-pill', e => e.map(x => Number(x.innerText)));
		ok(label + ' vencidos: días negativos o mensaje vacío', days.every(d => d < 0) && (days.length > 0 || /Sin lotes/.test(await text('#dsm-fefo-tbody'))), days);
		await pick('dsm-fefo-days-btn', 'Próximos 60 días');
		await page.click('#dsm-fefo-dept-btn');
		await menu().getByRole('option', { name: 'Lácteos y huevos' }).click();
		await menu().getByRole('button', { name: 'Aplicar' }).click();
		await page.waitForTimeout(30);
		await idle();
		ok(label + ' ventana + departamento', (await riskOf()) < r60 && (await riskOf()) > 0);

		// Teclado
		await page.focus('#dsm-fefo-days-btn');
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Home');
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Enter');
		await page.waitForTimeout(30);
		await idle();
		ok(label + ' teclado elige opción', (await text('#dsm-fefo-days-btn')).includes('24 horas'));
		ok(label + ' foco vuelve al botón', await page.evaluate(() => document.activeElement.id === 'dsm-fefo-days-btn'));
		await page.keyboard.press('Enter');
		await page.keyboard.press('Escape');
		ok(label + ' Escape cierra', await menu().count() === 0);

		// Persistencia
		await page.waitForLoadState('load'); await page.waitForTimeout(300); await page.goto('http://localhost:8080/stockoverview');
		await page.waitForSelector('#dsm-heatmap-grid .dsm-hm-cell');
		await idle();
		ok(label + ' filtros conservados', (await text('#dsm-top10-limit-btn')).includes('Top 5') && (await text('#dsm-fefo-days-btn')).includes('24 horas'));

		// Filtro global: sucursal
		const riskBefore = await text('#dsm-top10-foot-total');
		const opts = await page.$$eval('#dsm-filter-branch option', o => o.map(x => x.value).filter(v => v !== 'all'));
		if (opts.length > 1) {
			await page.selectOption('#dsm-filter-branch', opts[0]);
			await page.waitForResponse(/stockoverview\/top-products/);
			await idle();
			ok(label + ' sucursal global llega a las tarjetas', apiCalls.some(u => u.includes('top-products') && u.includes('branch_id=' + opts[0])), riskBefore);
			await page.selectOption('#dsm-filter-branch', 'all');
			await page.waitForResponse(/stockoverview\/top-products/);
			await idle();
		}

		// Carrera: clics rápidos, gana el último
		await page.click('.dsm-fdd-clear[data-widget=top]');
		for (const n of ['Top 20', 'Top 50', 'Top 5', 'Top 10']) {
			await page.click('#dsm-top10-limit-btn');
			await menu().getByRole('option', { name: n, exact: true }).click();
		}
		await page.waitForTimeout(50);
		await idle();
		ok(label + ' carrera: gana la última selección', (await text('#dsm-top10-title')).startsWith('Top 10') && (await topRows()) === 10);

		// Reinicio global
		await page.evaluate(() => window.dsmResetAllFilters());
		await page.waitForResponse(/stockoverview\/expiring/);
		await idle();
		ok(label + ' reinicio global', (await text('#dsm-fefo-days-btn')).includes('30') && await page.$eval('.dsm-fdd-clear[data-widget=fefo]', b => b.hidden));
		ok(label + ' sin overlays', await noOverlay());
		await page.evaluate(() => window.dsmOpenFefoAllModal());
		await page.waitForSelector('#dsmFefoAllModal.show');
		await page.waitForTimeout(400);
		await page.click('#dsmFefoAllModal .modal-footer [data-dismiss=modal]');
		await page.waitForFunction(() => !document.querySelector('.modal.show'));
		await page.waitForTimeout(400);
		ok(label + ' modal no deja neblina', await noOverlay());
	};

	await page.setViewportSize({ width: 1440, height: 900 });
	await run('[escritorio]');
	await page.setViewportSize({ width: 820, height: 1100 });
	await run('[tablet]');
	await page.setViewportSize({ width: 390, height: 800 });
	await run('[móvil]');
	await page.setViewportSize({ width: 1440, height: 900 });

	// Otras páginas
	for (const p of ['/today', '/kardex', '/quality-expiry', '/stockentries', '/waste']) {
		const r = await page.goto('http://localhost:8080' + p, { waitUntil: 'domcontentloaded', timeout: 90000 });
		ok('página ' + p + ' responde', r.status() < 400, r.status());
	}
	ok('sin errores de consola', errors.length === 0, [...new Set(errors)].slice(0, 6));
	const fails = res.filter(r => r.startsWith('FAIL'));
	return { total: res.length, failed: fails.length, fails };
}
