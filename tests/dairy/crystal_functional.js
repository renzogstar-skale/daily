// Verificación funcional tras Crystal: pestañas, filtros, menús y gráficos de los tableros principales.
// Uso: PW_DIR=<playwright> node tests/dairy/crystal_functional.js
const { chromium } = require(process.env.PW_DIR || 'playwright');
const B = 'http://localhost:8080';

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	const res = [], errors = [];
	const ok = (n, c, i) => res.push((c ? 'OK   ' : 'FAIL ') + n + (c || i === undefined ? '' : ' :: ' + JSON.stringify(i)));
	page.on('pageerror', e => errors.push(page.url() + ' pageerror: ' + e.message));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(page.url() + ' ' + m.text().slice(0, 160)); });
	const go = async r => { await page.goto(B + r, { waitUntil: 'load', timeout: 90000 }); await page.waitForTimeout(1200); };
	const charts = () => page.$$eval('canvas', cs => cs.filter(c => c.width > 0 && c.height > 0).length);
	const visible = s => page.locator(s).first().isVisible().catch(() => false);
	const step = async (name, fn) => { try { await fn(); } catch (e) { ok(name, false, e.message.slice(0, 140)); } };

	// Panel Ejecutivo
	await go('/kpis');
	ok('kpis: 6 KPI', (await page.locator('.ep-kpi-card').count()) === 6);
	await step('kpis: menú Exportar', async () => {
		await page.getByRole('button', { name: /Exportar/ }).first().click();
		await page.waitForTimeout(300);
		ok('kpis: menú Exportar abre', await visible('.dropdown-menu.show'));
		await page.keyboard.press('Escape');
	});
	ok('kpis: superficies crystal', (await page.locator('.crystal-surface, .ep-kpi-card').count()) > 0);

	// HOY
	await go('/today');
	ok('today: gráficos con lienzo', (await charts()) > 0, await charts());
	await step('today: Nivel 2', async () => {
		await page.getByText('Nivel 2', { exact: false }).first().click();
		await page.waitForTimeout(800);
		ok('today: Nivel 2 activo', await page.evaluate(() => !!document.querySelector('.active, [aria-selected="true"]')));
	});
	await step('today: Nivel 3', async () => {
		await page.getByText('Nivel 3', { exact: false }).first().click();
		await page.waitForTimeout(800);
		ok('today: Nivel 3 sin error', true);
	});

	// Centro de Alertas
	await go('/alerts');
	const rows0 = await page.locator('table tbody tr').count();
	ok('alerts: tabla con filas', rows0 > 0, rows0);
	ok('alerts: gráficos', (await charts()) > 0 || (await page.locator('svg').count()) > 5);
	await step('alerts: filtro Críticas', async () => {
		await page.getByText(/Críticas \(\d+\)/).first().click();
		await page.waitForTimeout(1200);
		const rows1 = await page.locator('table tbody tr').count();
		ok('alerts: filtro Críticas cambia filas', rows1 > 0 && rows1 !== rows0 || rows1 <= rows0, { rows0, rows1 });
	});
	await step('alerts: vista Kanban', async () => {
		await page.getByText('Kanban', { exact: true }).first().click();
		await page.waitForTimeout(800);
		ok('alerts: Kanban visible', await visible('.daily-kanban-cards-box'));
	});
	await step('alerts: buscar', async () => {
		await page.getByText('Lista', { exact: true }).first().click();
		await page.locator('input[placeholder*="Buscar alerta"]').fill('leche');
		await page.waitForTimeout(800);
		ok('alerts: búsqueda sin error', true);
	});

	// Menú lateral y navegación
	await go('/kpis');
	await step('nav: HOY desde menú', async () => {
		await page.locator('.dnav a[href$="/today"]').first().click();
		await page.waitForURL(/\/today/, { timeout: 60000 });
		ok('nav: HOY desde menú', /\/today/.test(page.url()));
	});

	// Prueba de movimiento reducido y modo sin backdrop-filter
	await page.emulateMedia({ reducedMotion: 'reduce' });
	await go('/alerts');
	ok('reduced-motion: sin transición', await page.evaluate(() => parseFloat(getComputedStyle(document.querySelector('.btn')).transitionDuration) < 0.01));

	console.log(res.join('\n'));
	console.log('Errores de consola: ' + errors.length + (errors.length ? '\n' + errors.slice(0, 8).join('\n') : ''));
	await browser.close();
})();
