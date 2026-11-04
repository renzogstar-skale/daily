// DAILY — E2E del Control de Celulares en escritorio y móvil.
// Uso: node tests/dairy/e2e_device_control.js <ruta a node_modules/playwright> [http://localhost:8080]
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

		await page.goto(base + '/loss-prevention/devices', { waitUntil: 'domcontentloaded', timeout: 90000 });
		await page.waitForSelector('.lp-kpis', { timeout: 30000 });
		const text = await page.innerText('.lp-page');
		ok('lista los equipos de demostración', text.includes('Galaxy A55'));
		ok('muestra no localizados', /No localizado/.test(text));
		ok('IMEI enmascarados', text.includes('•••') && !/99000099\d{7}/.test(text));
		ok('sin scroll horizontal', (await overflow()) <= 1, await overflow());
		await page.screenshot({ path: `${shots}dv_dashboard_${name}.png`, fullPage: true });

		await page.click('.lp-table a >> nth=0');
		await page.waitForSelector('#dvImei1', { timeout: 30000 });
		ok('expediente del equipo enmascarado', (await page.innerText('#dvImei1')).includes('•'));
		await page.click('#dvReveal');
		await page.waitForFunction(() => /^\d{15}$/.test(document.getElementById('dvImei1').textContent), null, { timeout: 15000 });
		ok('revelar muestra el IMEI', true);
		ok('sin scroll horizontal en equipo', (await overflow()) <= 1, await overflow());
		await page.screenshot({ path: `${shots}dv_device_${name}.png`, fullPage: true });
		ok('sin errores de JavaScript', errors.length === 0, errors.slice(0, 2).join(' | '));
		await ctx.close();
	}

	// POS real: pide el IMEI y el servidor rechaza uno ya no localizado
	console.log('\nPOS');
	const page = await (await browser.newContext({ viewport: { width: 1366, height: 900 } })).newPage();
	await page.goto(base + '/pos', { waitUntil: 'domcontentloaded', timeout: 90000 });
	await page.waitForTimeout(1500);
	const required = await page.evaluate(async () => (await fetch('/api/devices/required?product_ids=9003,30')).json());
	ok('el POS identifica los productos controlados', JSON.stringify(required) === '[9003]', JSON.stringify(required));
	const sale = await page.evaluate(async () => {
		const r = await fetch('/api/sales', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ branch_id: 1, customer_id: 1, payment_method: 'cash', items: [{ product_id: 9003, amount: 1, unit_price: 449 }] }) });
		return { status: r.status, body: await r.json().catch(() => null) };
	});
	ok('API de venta exige IMEI', sale.status >= 400, JSON.stringify(sale.body).slice(0, 120));
	await browser.close();
	console.log(`\n${fails} fallos`);
	process.exit(fails ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
