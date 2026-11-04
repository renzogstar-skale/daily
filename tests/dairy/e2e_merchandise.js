// DAILY — E2E del Centro de Control de Mercancías en escritorio y móvil.
// Uso: node tests/dairy/e2e_merchandise.js <ruta a node_modules/playwright> [http://localhost:8080]
const { chromium } = require(process.argv[2]);
const base = process.argv[3] || 'http://localhost:8080';
const shots = 'C:/Users/renzo/grocy/grocy/.playwright-mcp/';

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	let fails = 0;
	const ok = (label, cond, info = '') => { if (!cond) fails++; console.log((cond ? '  OK   ' : '  FAIL ') + label + (info !== '' ? `  [${info}]` : '')); };

	for (const [name, viewport] of [['escritorio', { width: 1366, height: 900 }], ['movil', { width: 390, height: 844 }]])
	{
		console.log('\n' + name);
		const ctx = await browser.newContext({ viewport, isMobile: name === 'movil' });
		const page = await ctx.newPage();
		const errors = [];
		page.on('pageerror', e => errors.push(e.message));
		const overflow = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

		const t0 = Date.now();
		await page.goto(base + '/merchandise', { waitUntil: 'domcontentloaded', timeout: 120000 });
		await page.waitForSelector('.lp-kpis', { timeout: 60000 });
		ok('carga en menos de 15 s', Date.now() - t0 < 15000, (Date.now() - t0) + ' ms');
		const text = await page.innerText('.lp-page');
		for (const c of ['DEMO-001', 'DEMO-002', 'DEMO-003', 'DEMO-005'])
		{
			ok(`muestra ${c}`, text.includes(c));
		}
		ok('muestra el televisor', text.includes('Televisor 55'));
		ok('muestra clasificación', text.includes('Pendiente de reconteo') && text.includes('Diferencia no justificada'));
		ok('sin scroll horizontal', (await overflow()) <= 1, await overflow());
		await page.screenshot({ path: `${shots}mc_dashboard_${name}.png`, fullPage: true });

		await page.click('.lp-table a:has-text("Ventas") >> nth=0');
		await page.waitForSelector('#libro', { timeout: 60000 });
		const book = await page.innerText('#libro');
		ok('del indicador al movimiento original', /MOV-\d+/.test(book) && book.includes('Ventas'));

		await page.goto(base + '/merchandise?branch_id=1&product_id=9002', { waitUntil: 'domcontentloaded', timeout: 120000 });
		await page.waitForSelector('.lp-kpis', { timeout: 60000 });
		const pos = await page.innerText('.lp-page');
		ok('producto: debería tener y encontró', pos.includes('Debería tener') && pos.includes('Encontró físicamente'));

		await page.goto(base + '/merchandise', { waitUntil: 'domcontentloaded', timeout: 120000 });
		await page.click('text=DEMO-003');
		await page.waitForSelector('.lp-steps', { timeout: 30000 });
		ok('expediente ofrece causa confirmada', await page.locator('#lpCause').count() === 1);
		ok('sin errores de JavaScript', errors.length === 0, errors.slice(0, 2).join(' | '));
		await ctx.close();
	}
	await browser.close();
	console.log(`\n${fails} fallos`);
	process.exit(fails ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
