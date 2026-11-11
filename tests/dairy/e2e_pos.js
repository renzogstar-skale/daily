// E2E del POS: apertura → escaneo → carrito → descuento autorizado → pago mixto → recibo → reintento sin doble cobro →
// suspensión/recuperación → reimpresión controlada → anulación autorizada → consulta → retiro → arqueo y cierre.
// Respalda la base antes y la restaura al terminar (e2e_pos_setup.php).
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/e2e_pos.js <carpeta_salida>
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require(process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright');
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const setup = (a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'e2e_pos_setup.php'), a], { encoding: 'utf8' }).split('\n').filter(l => l.startsWith('{')).pop());
let pass = 0, fail = 0;
const ok = (n, c, info) => { c ? pass++ : fail++; console.log((c ? '  OK   ' : '  FAIL ') + n + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 200) + ']' : '')); };

(async () => {
	const S = setup('setup');
	const [A, Bp] = S.products;
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	const errors = [];
	page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
	if (process.env.POS_DEBUG) { page.on('response', r => { if (r.url().includes('/api/pos')) r.text().then(t => console.log('  <', r.status(), r.url().replace(BASE, ''), t.slice(0, 160))).catch(() => {}); }); page.on('console', m => console.log('  console:', m.text().slice(0, 160))); }
	const api = (m, url, body) => page.evaluate(([m, url, body]) => new Promise((ok, ko) => m === 'GET' ? Grocy.Api.Get(url, ok, x => ko(x.responseText)) : Grocy.Api.Post(url, body, ok, x => ko(x.responseText))), [m, url, body]);
	const auth = async (reason) => {
		await page.waitForSelector('#modal-pos-auth.show');
		if (reason) await page.fill('#auth-reason', reason);
		await page.fill('#auth-user', 'sup_e2e'); await page.fill('#auth-pass', 'Clave-E2E-1');
		await page.click('#form-pos-auth button[type=submit]');
		await page.waitForSelector('#modal-pos-auth', { state: 'hidden' });
	};
	try {
		console.log('\n1. Apertura de caja');
		await page.goto(BASE + '/pos?branch_id=1&register=Caja%20E2E', { waitUntil: 'load', timeout: 180000 });
		ok('Página del POS sin maqueta (sin cajeros ni KPI inventados)', !(await page.content()).includes('Carlos Ruiz') && await page.locator('[data-testid="pos-page"]').count() === 1);
		ok('Caja cerrada: cobro bloqueado', await page.locator('#pos-closed-banner').isVisible());
		await page.click('#btn-banner-open-shift');
		await page.waitForSelector('#modal-pos-open-shift.show');
		await page.fill('#shift-register', 'Caja E2E'); await page.fill('#shift-opening', '150');
		await page.click('#form-open-shift button[type=submit]');
		await page.waitForSelector('#pos-shift-pill.is-open', { timeout: 15000 });
		ok('Turno abierto con fondo', (await page.textContent('#pos-shift-pill-text')).includes('Turno abierto'));

		console.log('\n2. Escaneo, carrito, descuento autorizado y pago mixto');
		for (const code of [A.code, A.code, Bp.code]) { await page.fill('#pos-search-input', code); await page.press('#pos-search-input', 'Enter'); await page.waitForTimeout(250); }
		const cart = await page.evaluate(() => window.DailyPos.cart().map(l => [l.product_id, l.amount]));
		ok('Escaneo agrega y agrupa cantidades', cart.length === 2 && cart[0][1] === 2, cart);
		await page.fill('#pos-discount-input', '10');
		await page.fill('#pos-discount-reason', 'Cliente frecuente');
		const q = await page.evaluate(() => window.DailyPos.quote());
		const sq = await api('POST', 'pos/quote', { items: cart.map(c => ({ product_id: c[0], amount: c[1] })), discount: { type: 'pct', value: 10 } });
		ok('Total de la terminal = total del servidor (impuesto incluido)', Math.abs(q.total - sq.total) < 0.005 && Math.abs(q.tax - sq.tax) < 0.005, [q.total, sq.total, q.tax]);
		await page.click('.pos-pay-tab[data-method="mixed"]');
		const card = Math.round(q.total / 2 * 100) / 100, cash = Math.round((q.total - card) * 100) / 100;
		await page.fill('#pos-mixed-cash', String(cash)); await page.fill('#pos-mixed-card', String(card)); await page.fill('#pos-payment-ref', 'AUT-E2E-01');
		await page.click('.pos-pay-tab[data-method="card"]');
		await page.fill('#pos-payment-ref', '4111 1111 1111 1111');
		await page.click('#btn-submit-sale');
		await auth();
		await page.waitForTimeout(1500);
		ok('Número de tarjeta rechazado (no se guarda)', (await page.content()).includes('No registre números de tarjeta') || (await page.evaluate(() => window.DailyPos.cart().length)) > 0);
		await page.click('.pos-pay-tab[data-method="mixed"]');
		await page.fill('#pos-payment-ref', 'AUT-E2E-01');
		await page.evaluate(() => toastr.remove());
		console.log('  estado:', await page.evaluate(() => [document.getElementById('btn-submit-sale').disabled, window.DailyPos.cart().length, document.querySelector('.pos-pay-tab.active').dataset.method, document.querySelectorAll('.modal.show').length]));
		const ref = await page.evaluate(() => window.DailyPos.ref());
		await page.click('#btn-submit-sale');
		await page.waitForSelector('#modal-pos-receipt.show, .toast', { timeout: 20000 });
		if (await page.locator('.toast').count()) console.log('  toast:', await page.locator('.toast').last().textContent());
		await page.waitForSelector('#modal-pos-receipt.show', { timeout: 20000 });
		const rc = await page.textContent('#pos-receipt');
		ok('Venta cobrada con descuento autorizado y recibo', rc.includes('Descuentos') && rc.includes('AUT-E2E-01') && !rc.includes('4111'));
		const sale = await api('GET', 'pos/transactions?branch_id=1');
		const mine = sale.find(s => s.register === 'Caja E2E');
		ok('Total registrado = total cobrado', mine && Math.abs(mine.total - q.total) < 0.005, [mine && mine.total, q.total]);
		const replay = await api('POST', 'pos/checkout', { client_ref: ref, branch_id: 1, register: 'Caja E2E', items: cart.map(c => ({ product_id: c[0], amount: c[1] })), expected_total: q.total, payments: [{ method: 'cash', amount: q.total, tendered: q.total }], discount: {} });
		ok('Reintento con el mismo ticket: no cobra dos veces', replay.replayed === true && replay.sale.id === mine.id);
		await page.screenshot({ path: path.join(out, 'pos_recibo.png') });

		console.log('\n3. Reimpresión y anulación autorizadas');
		await page.evaluate(() => { window.print = () => { window.__printed = (window.__printed || 0) + 1; }; });
		await page.click('#btn-receipt-reprint'); await page.waitForTimeout(800);
		ok('Primera reimpresión numerada como copia', (await page.textContent('#pos-receipt')).includes('COPIA N.º 1'));
		await page.click('#btn-receipt-reprint'); await auth('Cliente perdió el recibo'); await page.waitForTimeout(800);
		ok('Segunda reimpresión con autorización', (await page.textContent('#pos-receipt')).includes('COPIA N.º 2'));
		await page.click('#btn-receipt-void'); await auth('Error de cobro'); await page.waitForTimeout(1500);
		ok('Venta anulada con autorización', (await page.textContent('#pos-receipt')).includes('ANULADA'));
		await page.keyboard.press('Escape'); await page.waitForTimeout(400);

		console.log('\n4. Suspensión y recuperación');
		await page.fill('#pos-search-input', Bp.code); await page.press('#pos-search-input', 'Enter'); await page.waitForTimeout(300);
		await page.click('#btn-hold-sale');
		await page.waitForSelector('.bootbox.show input[name=label]');
		await page.fill('.bootbox input[name=label]', 'Cliente vuelve'); await page.click('.bootbox .btn-primary'); await page.waitForTimeout(800);
		ok('Ticket suspendido y carrito vacío', (await page.evaluate(() => window.DailyPos.cart().length)) === 0);
		await page.waitForSelector('.bootbox', { state: 'detached' }); await page.click('#btn-recall-sale'); await page.waitForSelector('.bootbox.show [data-hold]');
		await page.locator('.bootbox [data-hold]').first().click(); await page.waitForTimeout(1000);
		ok('Ticket recuperado', (await page.evaluate(() => window.DailyPos.cart().length)) === 1);
		await page.click('.pos-pay-tab[data-method="cash"]');
		await page.click('[data-quick-cash="exact"]').catch(() => {});
		await page.click('#btn-submit-sale');
		await page.waitForSelector('#modal-pos-receipt.show', { timeout: 20000 });
		await page.keyboard.press('Escape'); await page.waitForTimeout(400);

		console.log('\n5. Consulta, retiro, arqueo y cierre');
		await page.click('#pill-tab-transacciones'); await page.waitForTimeout(1200);
		const txt = await page.textContent('#pos-tx-tbody');
		ok('Consulta muestra la venta anulada y la cobrada', txt.includes('Anulada') && txt.includes('Completada'));
		await page.click('#pill-tab-caja'); await page.waitForTimeout(500);
		const sh = await api('GET', 'pos/shifts/current?branch_id=1&register=Caja%20E2E');
		ok('Esperado = fondo + efectivo de ventas vigentes', Math.abs(sh.shift.expected_cash - (150 + sh.shift.by_method.cash)) < 0.005 && sh.shift.voided_count === 1, sh.shift.expected_cash);
		await page.click('#btn-cash-drop');
		await page.waitForSelector('.bootbox.show input[name=amount]');
		await page.fill('.bootbox input[name=amount]', '100'); await page.fill('.bootbox input[name=reason]', 'Retiro a bóveda'); await page.click('.bootbox .btn-primary'); await page.waitForTimeout(800);
		const exp = (await api('GET', 'pos/shifts/current?branch_id=1&register=Caja%20E2E')).shift.expected_cash;
		const bills = Math.floor(exp), cents = Math.round((exp - bills) * 100);
		await page.fill('#pos-denoms input[data-denom="1"]', String(bills - 20));
		await page.fill('#pos-denoms input[data-denom="0.01"]', String(cents));
		await page.fill('#pos-close-reason', 'Faltante en gaveta');
		await page.click('#btn-close-shift'); await auth(); await page.waitForTimeout(1500);
		const closed = await api('GET', 'pos/transactions?branch_id=1').then(() => page.evaluate(() => document.getElementById('pos-shift-pill-text').textContent));
		const sumTxt = await page.textContent('#pos-shift-summary');
		ok('Turno cerrado con faltante autorizado', closed.includes('Caja cerrada') && sumTxt.includes('-$20.00'), [closed, sumTxt.slice(0, 160)]);
		await page.screenshot({ path: path.join(out, 'pos_cierre.png'), fullPage: true });

		console.log('\n6. Móvil');
		await page.setViewportSize({ width: 390, height: 844 });
		await page.click('#pill-tab-terminal'); await page.waitForTimeout(500);
		ok('Sin desplazamiento horizontal en 390 px', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
		ok('Sin errores de JavaScript', errors.length === 0, errors);
	} catch (e) {
		ok('Excepción: ' + e.message.slice(0, 1500), false);
	} finally {
		await browser.close();
		setup('restore');
		console.log('\n' + pass + '/' + (pass + fail) + ' OK · base restaurada');
		process.exit(fail ? 1 : 0);
	}
})();
