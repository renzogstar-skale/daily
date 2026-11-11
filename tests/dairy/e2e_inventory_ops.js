// E2E — Inventario y Almacenes (sección «Operación de inventario» de /stockoverview). Solo lectura: no modifica la BD.
// Uso: node tests/dairy/e2e_inventory_ops.js   (servidor en DAILY_URL, por defecto http://localhost:8080)
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright');
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const OUT = path.join(__dirname, '..', '..', '.playwright-mcp', 'inventory_ops');
fs.mkdirSync(OUT, { recursive: true });

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  ✓ ' + m); } else { fail++; console.log('  ✗ ' + m); } };
const TABS = ['positions', 'locations', 'kardex', 'counts', 'replenishment', 'transfers', 'anomalies', 'coverage', 'lots', 'reservations', 'audit'];

async function run(viewport, label, barcode) {
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await (await browser.newContext({ viewport })).newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(e.message));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_ABORTED/.test(m.text())) errors.push(m.text()); });
	console.log(`\n[${label}] ${viewport.width}×${viewport.height}`);
	await page.goto(BASE + '/stockoverview#inv-ops', { waitUntil: 'domcontentloaded', timeout: 120000 });
	await page.waitForSelector('#iop-kpis .iop-kpi', { timeout: 60000 });
	ok(await page.locator('#iop-kpis .iop-kpi').count() === 8, 'ocho indicadores de inventario');
	ok(await page.locator('#iop-cycle .iop-step').count() === 6, 'ciclo de seis etapas');
	const kpiText = await page.locator('#iop-kpis').innerText();
	ok(/físico/i.test(kpiText) && /disponible/i.test(kpiText) && !/Ventas netas|Ticket promedio/i.test(kpiText), 'KPI de inventario sin métricas comerciales');

	for (const t of TABS) {
		await page.click(`.iop-tab[data-tab=${t}]`);
		await page.waitForFunction(() => !document.querySelector('#iop-panel .iop-loading'), null, { timeout: 60000 }).catch(() => {});
		const hasErr = await page.locator('#iop-panel .iop-error').count();
		const txt = (await page.locator('#iop-panel').innerText()).trim();
		ok(!hasErr && txt.length > 0, `pestaña ${t} carga sin error`);
	}

	await page.fill('#iop-scan-input', barcode);
	await page.press('#iop-scan-input', 'Enter');
	await page.waitForSelector('#iop-scan-result .iop-scan-num', { timeout: 30000 }).catch(() => {});
	ok(await page.locator('#iop-scan-result .iop-scan-num').count() === 6, 'escaneo UPC muestra físico/reservado/disponible/bodega/sala');
	await page.click('#iop-scan-kx');
	await page.waitForSelector('#iop-kx-body .iop-box', { timeout: 30000 }).catch(() => {});
	ok(await page.locator('#iop-kx-body .iop-box').count() === 1, 'kardex abierto desde el escaneo con saldos');

	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
	ok(overflow <= 1, `sin desplazamiento horizontal de página (${overflow}px)`);
	await page.locator('#inv-ops').scrollIntoViewIfNeeded(); await page.screenshot({ path: path.join(OUT, `${label}.png`) });
	ok(await page.evaluate(() => document.getElementById('inv-ops').offsetHeight) < 4000, 'sección acotada en altura');
	ok(errors.length === 0, 'sin errores de JavaScript' + (errors.length ? ': ' + errors.slice(0, 3).join(' | ') : ''));
	await browser.close();
}

(async () => {
	const barcode = process.argv[2] || '750100000001';
	await run({ width: 1440, height: 900 }, 'escritorio', barcode);
	await run({ width: 390, height: 844 }, 'iphone', barcode);
	console.log(`\n${pass} correctas, ${fail} fallidas`);
	process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
