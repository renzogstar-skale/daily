/**
 * E2E — Comparativo de Ventas TY vs. LY (Ventas y Rentabilidad → Rendimiento por Departamento)
 * node tests/dairy/e2e_ty_ly.js   (PW_DIR = ruta a node_modules/playwright; SHOTS = carpeta de capturas opcional)
 */
const path = require('path');
const { chromium } = require(process.env.PW_DIR || 'playwright');
const BASE = process.env.BASE_URL || 'http://localhost:8080';
const SHOTS = process.env.SHOTS || null;

let failed = 0;
const check = (name, ok, extra) => { console.log(`${ok ? '  OK  ' : '  FAIL'} ${name}${extra ? '  ' + extra : ''}`); if (!ok) failed++; };

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
	const errors = [];
	page.on('pageerror', e => errors.push(e.message));
	page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });

	await page.goto(BASE + '/sales?tab=departamentos', { waitUntil: 'domcontentloaded', timeout: 120000 });
	const section = page.locator('#tylySection');
	await section.waitFor({ state: 'visible', timeout: 60000 });
	await section.scrollIntoViewIfNeeded();

	const rows = section.locator('.tyly-row');
	check('Filas de departamentos renderizadas en servidor', await rows.count() > 0, `(${await rows.count()})`);
	const source = (await section.locator('#tylySource').innerText()).trim();
	check('Origen de LY rotulado', /LY real|LY de ejemplo/.test(source), source);
	check('Indicadores verde/rojo presentes', await section.locator('.tyly-pill.is-up, .tyly-pill.is-down').count() > 0);
	if (SHOTS) await section.screenshot({ path: path.join(SHOTS, 'tyly_desktop.png') });

	// Orden por caída: la primera fila tiene la menor diferencia
	await section.locator('[data-tyly-sort="drop"]').click();
	const diffs = await section.locator('.tyly-row .tyly-diff').allInnerTexts();
	const nums = diffs.map(t => (t.includes('−') ? -1 : 1) * Number(t.replace(/[^0-9]/g, '')));
	check('Orden por caída ascendente', nums.every((v, i) => i === 0 || nums[i - 1] <= v), nums.slice(0, 4).join(', '));

	// Orden por crecimiento
	await section.locator('[data-tyly-sort="growth"]').click();
	check('Botón de crecimiento activo', await section.locator('[data-tyly-sort="growth"]').getAttribute('aria-pressed') === 'true');
	await section.locator('[data-tyly-sort="drop"]').click();

	// Desglose del departamento con mayor caída
	await rows.first().click();
	const drill = section.locator('.tyly-drill:not(.is-loading)');
	await drill.waitFor({ state: 'visible', timeout: 30000 });
	check('Desglose abierto con señales', await drill.locator('.tyly-signal').count() === 3);
	check('Desglose con productos y SKU', await drill.locator('.tyly-sku').count() > 0);
	check('Desglose con sucursales', await drill.locator('.tyly-panel').nth(1).locator('tbody tr').count() > 0);
	check('Enlace a agotados con filtros', /inventory\/stockouts\?.*department=/.test(await drill.locator('.tyly-signal.is-out').getAttribute('href')));
	if (SHOTS) await section.screenshot({ path: path.join(SHOTS, 'tyly_drill.png') });

	// Cerrar con teclado
	await rows.first().focus();
	await page.keyboard.press('Enter');
	check('Desglose se cierra con Enter', await section.locator('.tyly-drill-row').count() === 0);

	// Categoría (filtro compartido, sin recargar)
	const opts = await section.locator('#tylyCategory option').count();
	if (opts > 1) {
		await page.evaluate(() => { window.__tylyNoReload = true; });
		const resp = page.waitForResponse(r => r.url().includes('/api/sales/ty-ly') && r.url().includes('group_id='), { timeout: 30000 });
		await section.locator('#tylyCategory').selectOption({ index: 1 });
		const r = await resp;
		check('Categoría consulta la API', r.ok());
		await page.waitForFunction(() => !document.getElementById('tylyTableWrap').classList.contains('is-loading'));
		check('Sin recarga de página al filtrar', await page.evaluate(() => window.__tylyNoReload === true));
		check('URL conserva group_id', page.url().includes('group_id='));
		await section.locator('#tylyCategory').selectOption('');
		await page.waitForFunction(() => !document.getElementById('tylyTableWrap').classList.contains('is-loading'));
	}

	// Error de red: estado de error con reintento
	await page.route('**/api/sales/ty-ly**', route => route.abort());
	await section.locator('#tylyCategory').selectOption({ index: Math.min(1, opts - 1) });
	await section.locator('.tyly-state.is-error').waitFor({ timeout: 15000 });
	check('Estado de error con reintento', await section.locator('.tyly-retry').count() === 1);
	await page.unroute('**/api/sales/ty-ly**');
	await section.locator('.tyly-retry').click();
	await section.locator('.tyly-row').first().waitFor({ timeout: 30000 });
	check('Reintento recupera la tabla', await section.locator('.tyly-row').count() > 0);

	// Móvil
	await page.setViewportSize({ width: 390, height: 860 });
	await section.scrollIntoViewIfNeeded();
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
	check('Sin desbordamiento horizontal en móvil', overflow <= 1, `(${overflow}px)`);
	if (SHOTS) await section.screenshot({ path: path.join(SHOTS, 'tyly_mobile.png') });

	const own = errors.filter(e => /tyly|ty-ly/i.test(e));
	check('Sin errores de consola de la sección', own.length === 0, own.join(' | '));

	await browser.close();
	console.log(`\n${failed === 0 ? 'Todas las verificaciones pasaron' : failed + ' verificaciones fallidas'}`);
	process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
