// E2E de devoluciones del POS: venta en la terminal → búsqueda del ticket → devolución parcial con reembolso al medio
// original → comprobante → reintento sin duplicar → cambio con nota de crédito → canje de la nota en el POS → historial.
// Respalda la base antes y la restaura al terminar (e2e_pos_setup.php).
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/e2e_pos_returns.js <carpeta_salida>
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require(process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright');
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
const setup = (a) => JSON.parse(execFileSync('php', [path.join(__dirname, 'e2e_pos_setup.php'), a], { encoding: 'utf8' }).split('\n').filter(l => l.startsWith('{')).pop());
let pass = 0, fail = 0;
const ok = (n, c, info) => { c ? pass++ : fail++; console.log((c ? '  OK   ' : '  FAIL ') + n + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 220) + ']' : '')); };

(async () => {
	const S = setup('setup');
	const [A, Bp] = S.products;
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
	const errors = [];
	page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
	const api = (m, url, body) => page.evaluate(([m, url, body]) => new Promise((ok, ko) => m === 'GET' ? Grocy.Api.Get(url, ok, x => ko(x.responseText)) : Grocy.Api.Post(url, body, ok, x => ko(x.responseText))), [m, url, body]);
	try {
		console.log('\n1. Venta en la terminal');
		await page.goto(BASE + '/pos?branch_id=1&register=Caja%20E2E', { waitUntil: 'load', timeout: 180000 });
		await api('POST', 'pos/shifts/open', { branch_id: 1, register: 'Caja E2E', shift_name: 'E2E', opening_amount: 300 });
		await page.reload({ waitUntil: 'load' });
		for (const code of [A.code, A.code, Bp.code]) { await page.fill('#pos-search-input', code); await page.press('#pos-search-input', 'Enter'); await page.waitForTimeout(250); }
		await page.click('#btn-submit-sale');
		await page.waitForSelector('#modal-pos-receipt.show', { timeout: 20000 });
		const ticket = (await page.textContent('#pos-receipt')).match(/VTA-[0-9A-Z-]+/)[0];
		ok('Venta cobrada en efectivo', !!ticket, ticket);

		console.log('\n2. Devolución parcial con reembolso');
		await page.goto(BASE + '/pos/returns?branch_id=1', { waitUntil: 'load', timeout: 180000 });
		ok('Pantalla sin maquetas (sin pasarela demo ni Kardex)', !(await page.content()).includes('Sandbox') && !(await page.content()).includes('Destinos Kardex'));
		await page.fill('#posSearchQuery', ticket); await page.click('#btnSearchSale');
		await page.waitForSelector('#ret-sale tr[data-line]');
		const E = await page.evaluate(() => window.DailyPosReturns.eligibility());
		ok('Ticket encontrado y elegible', E.eligible && E.lines.length === 2);
		const lineA = E.lines.find(l => l.product_id === A.id);
		const rowA = `#ret-sale tr[data-line="${lineA.sale_item_id}"]`;
		await page.fill(rowA + ' .ret-qty', '3');
		ok('No permite más unidades que las vendidas', (await page.getAttribute(rowA + ' .ret-qty', 'max')) === '2');
		await page.fill(rowA + ' .ret-qty', '1');
		await page.selectOption(rowA + ' .ret-reason', 'no_deseado');
		await page.waitForTimeout(200);
		const sel = await page.evaluate(() => window.DailyPosReturns.selection());
		ok('Importe = lo pagado por una unidad', Math.abs(sel.total - lineA.unit_refund) < 0.011, [sel.total, lineA.unit_refund]);
		ok('Reembolso asignado al efectivo original', (await page.inputValue('#ret-refund-box [data-method="cash"] .ret-pay')) === sel.total.toFixed(2));
		await page.click('#ret-submit');
		await page.waitForSelector('#modalPosReturnVoucher.show, .toast', { timeout: 20000 });
		if (!(await page.locator('#modalPosReturnVoucher.show').count())) console.log('  aviso:', await page.locator('.toast').last().textContent());
		await page.waitForSelector('#modalPosReturnVoucher.show', { timeout: 20000 });
		const v = await page.textContent('#ret-voucher');
		ok('Comprobante con código, ticket y medio', /DEV-\d{8}-\d+/.test(v) && v.includes(ticket) && v.includes('Efectivo'));
		await page.screenshot({ path: path.join(out, 'devolucion_comprobante.png') });
		await page.click('#modalPosReturnVoucher .close'); await page.waitForSelector('#modalPosReturnVoucher', { state: 'hidden' }); await page.waitForTimeout(800);
		const E2 = await page.evaluate(() => window.DailyPosReturns.eligibility());
		ok('Quedan disponibles solo las no devueltas', E2.lines.find(l => l.product_id === A.id).returnable === 1);
		const hist = await api('GET', 'pos/returns/history?branch_id=1');
		const retId = hist[0].id;
		const again = await api('GET', 'pos/returns/' + retId);
		const dup = await api('POST', 'pos/returns/process', { return_ref: again['return'].return_ref, sale_id: E.sale.id, register: 'Caja E2E', resolution: 'refund', lines: [{ sale_item_id: lineA.sale_item_id, quantity: 1, reason: 'no_deseado', condition: 'sin_abrir' }], refunds: [{ method: 'cash', amount: sel.total }] });
		ok('Reintento con la misma referencia no duplica', dup.replayed === true && dup['return'].id === retId);
		const over = await api('POST', 'pos/returns/process', { return_ref: 'e2e-over-' + Date.now() + '-xxxxxxxx', sale_id: E.sale.id, register: 'Caja E2E', resolution: 'refund', lines: [{ sale_item_id: lineA.sale_item_id, quantity: 1, reason: 'no_deseado', condition: 'sin_abrir' }], refunds: [{ method: 'cash', amount: sel.total + 10 }] }).then(() => 'ok', e => e);
		ok('Reembolso mayor al importe permitido rechazado', /exactamente|supera/.test(String(over)), String(over).slice(0, 120));
		const insp = await page.evaluate(() => 1);
		ok('Va a inspección física (pendiente en el historial)', hist[0].pending_inspection >= 1 && insp === 1);

		console.log('\n3. Cambio con nota de crédito y canje en el POS');
		const lineB = E2.lines.find(l => l.product_id === Bp.id);
		const rowB = `#ret-sale tr[data-line="${lineB.sale_item_id}"]`;
		await page.fill(rowB + ' .ret-qty', '1');
		await page.selectOption(rowB + ' .ret-reason', 'equivocado');
		await page.click('input[name=ret-res][value=exchange]', { force: true });
		await page.waitForTimeout(200);
		await page.click('#ret-submit');
		await page.waitForSelector('#modalPosReturnVoucher.show', { timeout: 20000 });
		const vtxt = await page.textContent('#ret-voucher');
		const code = (vtxt.match(/NC-\d{8}-[0-9A-F]+/) || [])[0];
		ok('Cambio emite nota de crédito', !!code, code);
		await page.goto(BASE + '/pos?branch_id=1&register=Caja%20E2E', { waitUntil: 'load', timeout: 180000 });
		await page.fill('#pos-search-input', A.code); await page.press('#pos-search-input', 'Enter'); await page.waitForTimeout(300);
		await page.click('.pos-pay-tab[data-method="credit_note"]');
		await page.fill('#pos-credit-code', code); await page.click('#pos-credit-check'); await page.waitForTimeout(800);
		ok('Saldo de la nota consultado', (await page.textContent('#pos-credit-info')).includes('Saldo'));
		await page.click('#btn-submit-sale');
		await page.waitForSelector('#modal-pos-receipt.show', { timeout: 20000 });
		ok('Venta cobrada con la nota de crédito', (await page.textContent('#pos-receipt')).includes('Nota de crédito'));
		const note = await api('GET', 'pos/credit-notes/' + encodeURIComponent(code));
		ok('Saldo descontado de la nota', note.movements.length === 2 && parseFloat(note.balance) < parseFloat(note.amount));

		console.log('\n4. Historial, indicadores y móvil');
		await page.goto(BASE + '/pos/returns?branch_id=1', { waitUntil: 'load', timeout: 180000 });
		await page.click('#tab-historial-tab'); await page.waitForTimeout(1200);
		ok('Historial con las dos devoluciones', (await page.locator('#ret-h-body tr').count()) >= 2);
		ok('Indicador de tasa de devolución calculado', !(await page.textContent('[data-testid="kpi-tasa-importe"]')).includes('Sin información'));
		await page.setViewportSize({ width: 390, height: 844 });
		await page.click('#tab-nueva-devolucion-tab'); await page.waitForTimeout(400);
		ok('Sin desplazamiento horizontal en 390 px', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
		ok('Sin errores de JavaScript', errors.length === 0, errors);
	} catch (e) {
		ok('Excepción: ' + e.message.slice(0, 1200), false);
	} finally {
		await browser.close();
		setup('restore');
		console.log('\n' + pass + '/' + (pass + fail) + ' OK · base restaurada');
		process.exit(fail ? 1 : 0);
	}
})();
