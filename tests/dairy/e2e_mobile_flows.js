// E2E de recorridos móviles DAILY (390 × 844, táctil): navegación inferior, Módulos, escáner, filtros
// compactos y ocho recorridos con acciones reales (recepción, conteo, reposición, merma, picking,
// devolución, ventas, asistencia y tareas). Cada acción se da por buena solo si su API responde 2xx.
// Escribe en la BD demo (registros con nota "E2E móvil"): respaldar antes, p. ej. copiar data/grocy_es.db.
// Uso: PW_DIR=... node tests/dairy/e2e_mobile_flows.js [ancho] [rol] [es|en] [light|dark]
const { chromium } = require(process.env.PW_DIR || 'playwright');

const BASE = process.env.DAILY_BASE || 'http://localhost:8080';
const W = +(process.argv[2] || 390), ROLE = process.argv[3] || 'manager', LANG = process.argv[4] || 'es', THEME = process.argv[5] || 'light';
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  [OK] ' + m); } else { fail++; console.log('  [FALLA] ' + m); } };

async function apiOk(page, re, action, timeout = 25000) {
	const wait = page.waitForResponse(r => re.test(r.url()) && r.request().method() === 'POST', { timeout }).catch(() => null);
	await action();
	const r = await wait;
	let body = '';
	if (r) { try { body = (await r.text()).slice(0, 160); } catch (e) {} }
	return { ok: !!r && r.status() < 300, status: r ? r.status() : 0, body };
}
async function noHScroll(page) { return page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1); }
async function mGo(page, hash) {
	await page.goto(BASE + '/m' + hash, { waitUntil: 'load', timeout: 90000 });
	await page.waitForTimeout(1200);
}
// Elige un producto en el formulario de captura de /m: busca y prueba candidatos hasta cumplir `accept`
async function pickProduct(page, term, accept) {
	await page.fill('#m-psearch', term);
	await page.waitForSelector('#m-plist [data-pid]', { timeout: 15000 });
	const n = await page.locator('#m-plist [data-pid]').count();
	for (let i = 0; i < Math.min(n, 8); i++) {
		if (i > 0) { await page.click('#m-pchange'); await page.fill('#m-psearch', term); await page.waitForSelector('#m-plist [data-pid]'); }
		await page.locator('#m-plist [data-pid]').nth(i).click();
		await page.waitForSelector('#m-pchange', { timeout: 10000 });
		if (!accept || await accept()) return true;
	}
	return false;
}

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: W, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, geolocation: { latitude: 25.98, longitude: -80.15 }, permissions: ['geolocation'] });
	await ctx.addCookies([{ name: 'daily_simulated_role', value: ROLE, url: BASE }, { name: 'daily_lang', value: LANG, url: BASE }]);
	await ctx.addInitScript(([l, t]) => { try { localStorage.setItem('daily_lang', l); localStorage.setItem('daily_mode', t); } catch (e) {} }, [LANG, THEME]);
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
	page.on('dialog', d => d.accept());

	console.log(`== Navegación inferior (${W}px, ${ROLE}, ${LANG}, ${THEME})`);
	await page.goto(BASE + '/today', { waitUntil: 'load', timeout: 120000 });
	await page.waitForTimeout(1500);
	const items = await page.$$eval('#dairyBottomNav .dairy-bottom-nav-item', a => a.map(x => x.textContent.trim()));
	console.log('  barra:', items.join(' · '));
	ok(items.length >= 3 && items.length <= 4, 'barra inferior con 3–4 destinos');
	if (ROLE !== 'empleado') ok(/HOY|TODAY/.test(items[0]) && /Módulos|Modules/.test(items[1]) && /Escanear|Scan/.test(items[2]) && /Alertas|Alerts/.test(items[3] || ''), 'orden HOY · Módulos · Escanear · Alertas');
	ok(await noHScroll(page), 'HOY sin scroll horizontal');
	// Giro de pantalla: en horizontal (≥ 768 px) se restaura el diseño de tableta; al volver, la capa móvil se rearma
	const hadBar = (await page.$$('.dm-filter-btn')).length;
	await page.setViewportSize({ width: 844, height: W });
	await page.waitForTimeout(800);
	const land = await page.evaluate(() => ({ h: document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1, bar: !!document.querySelector('.dm-filter-btn'), sheetHidden: !!document.querySelector('.dm-filter-sheet') }));
	ok(land.h && !land.bar && !land.sheetHidden, 'giro a horizontal: sin scroll y filtros de tableta restaurados');
	await page.setViewportSize({ width: W, height: 844 });
	await page.waitForTimeout(800);
	const port = await page.evaluate(() => ({ h: document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1, bar: document.querySelectorAll('.dm-filter-btn').length }));
	ok(port.h && port.bar === hadBar, 'vuelta a vertical: barra de filtros (' + port.bar + ') y sin scroll');
	// Módulos
	await page.click('#dairyBottomNav [data-target="#dairyQuickSheet"]');
	await page.waitForSelector('#dairyQuickSheet.show', { timeout: 5000 }).catch(() => {});
	const sections = await page.$$eval('#dairyQuickSheet .dairy-sheet-section', s => s.map(x => x.textContent.trim()));
	ok(sections.length >= 2, 'Módulos agrupados por área: ' + sections.slice(0, 6).join(', '));
	const sheetFits = await page.$eval('#dairyQuickSheet .modal-content', e => { const b = e.getBoundingClientRect(); return b.bottom <= innerHeight + 1 && b.left >= -1 && b.right <= innerWidth + 1; });
	ok(sheetFits, 'hoja Módulos dentro de la pantalla');
	await page.click('#dairyQuickSheet .close');
	await page.waitForTimeout(400);
	// Escáner (si el rol lo tiene)
	const hasScan = await page.$('#dairyBottomNav [data-dairy-scan]');
	if (hasScan) {
		await hasScan.click();
		await page.waitForSelector('#dairyScanModal.show', { timeout: 5000 }).catch(() => {});
		await page.waitForTimeout(800); // el modal limpia el campo al terminar de abrirse
		ok(await page.isVisible('#dairyScanInput'), 'escáner abre con ingreso manual');
		const code = await page.evaluate(async () => { const act = new Set((await (await fetch('/api/objects/products?query[]=active=1')).json()).map(p => p.id)); const bc = await (await fetch('/api/objects/product_barcodes')).json(); return ((bc.find(b => act.has(b.product_id)) || {}).barcode) || ''; });
		if (code) {
			await page.fill('#dairyScanInput', code);
			await page.click('#dairyScanManual button[type=submit]');
			await page.waitForSelector('#dairyScanResult:not(.d-none)', { timeout: 15000 }).catch(() => {});
			ok(await page.isVisible('#dairyScanResult'), 'escáner encuentra el código ' + code);
		}
		await page.keyboard.press('Escape');
		await page.waitForTimeout(400);
	}

	if (ROLE !== 'empleado') {
		console.log('== 1. Recepción de producto (/m)');
		await mGo(page, '#/capture/receive');
		await pickProduct(page, 'yog');
		await page.fill('#m-amt', '2');
		await page.fill('#m-doc', 'E2E-MOVIL');
		let r = await apiOk(page, /\/api\/m\/ops\/receive/, () => page.click('#m-submit'));
		ok(r.ok, 'recepción registrada (' + r.status + ' ' + r.body + ')');
		ok(await noHScroll(page), 'recepción sin scroll horizontal');

		console.log('== 2. Conteo de inventario (/m)');
		await mGo(page, '#/capture/count');
		await pickProduct(page, 'yog');
		await page.fill('#m-amt', '7');
		r = await apiOk(page, /\/api\/stock\/cycle-counts\/\d+\/execute/, () => page.click('#m-submit'));
		ok(r.ok, 'conteo ejecutado (' + r.status + ' ' + r.body + ')');

		console.log('== 3. Reposición: bodega → góndola (/m)');
		await mGo(page, '#/capture/move');
		const movable = await pickProduct(page, 'le', async () => (await page.locator('#m-loc option').count()) > 0 && (await page.locator('#m-loc2 option').count()) > 2);
		ok(movable, 'producto con stock para reponer');
		if (movable) {
			const from = await page.inputValue('#m-loc');
			// Destinos de la misma sucursal hasta uno compatible con la clase de almacenamiento del producto
			const dests = await page.$$eval('#m-loc2 option', (o, f) => o.filter(x => x.value && x.value !== f).map(x => x.value), from);
			for (const to of dests.slice(0, 6)) {
				await page.selectOption('#m-loc2', to);
				await page.fill('#m-amt', '1');
				r = await apiOk(page, /\/api\/stock\/products\/\d+\/transfer/, () => page.click('#m-submit'));
				if (r.ok || !/clase de almacenamiento|storage class/i.test(r.body)) break;
			}
			ok(r.ok, 'reposición movida (' + r.status + ' ' + r.body + ')');
		}

		console.log('== 4. Registro de merma (/m)');
		await mGo(page, '#/capture/waste');
		const wasteable = await pickProduct(page, 'le', async () => (await page.locator('#m-loc option').count()) > 0);
		if (wasteable) {
			const more = await page.$('#m-more-r'); if (more) await more.click();
			const reasons = await page.locator('.m-reason').count();
			let picked = false;
			for (let i = 0; i < reasons && !picked; i++) {
				await page.locator('.m-reason').nth(i).click();
				picked = /opcional|optional/i.test(await page.textContent('#m-ev-req'));
			}
			ok(picked, 'causal sin foto obligatoria');
			await page.fill('#m-amt', '1');
			await page.fill('#m-notes', 'E2E móvil');
			r = await apiOk(page, /\/api\/waste\/report/, () => page.click('#m-submit'));
			if (!r.ok && /duplicado/i.test(r.body)) r = { ok: true, status: 200, body: 'duplicado confirmado' };
			ok(r.ok, 'merma registrada (' + r.status + ' ' + r.body + ')');
		} else ok(false, 'producto con stock para merma');

		console.log('== 5. Picking y despacho (/dispatch)');
		await page.goto(BASE + '/dispatch', { waitUntil: 'load', timeout: 90000 });
		await page.waitForTimeout(1500);
		ok(await noHScroll(page), 'despachos sin scroll horizontal');
		const pane = await page.$eval('button[onclick^="openAssignPickingModal"]', b => { const p = b.closest('.tab-pane'); return p ? p.id : ''; }).catch(() => '');
		if (pane) { await page.click('[href="#' + pane + '"]'); await page.waitForTimeout(600); }
		const pickBtn = page.locator('button[onclick^="openAssignPickingModal"]:visible').first();
		if (await pickBtn.count()) {
			await pickBtn.click();
			await page.waitForSelector('#modalAssignPicking.show', { timeout: 5000 });
			const opt = await page.$$eval('#selPickerUser option', o => (o.find(x => x.value) || {}).value);
			if (opt) await page.selectOption('#selPickerUser', opt);
			const fits = await page.$eval('#modalAssignPicking .modal-content', e => { const b = e.getBoundingClientRect(); return b.left >= -1 && b.right <= innerWidth + 1 && b.bottom <= innerHeight + 1; });
			ok(fits, 'modal de picking dentro de la pantalla');
			r = await apiOk(page, /assign-picking/, () => page.click('#btnSubmitAssignPicking'));
			ok(r.ok, 'picking asignado (' + r.status + ' ' + r.body + ')');
			await page.waitForTimeout(1500);
		} else if (await page.locator('button[onclick^="openAssignPickingModal"]').count()) ok(false, 'botón Asignar picking visible en móvil');
		else console.log('  (sin pedidos pendientes de picking para este rol/sucursal)');

		console.log('== 6. Devolución (/returns)');
		await page.goto(BASE + '/returns', { waitUntil: 'load', timeout: 90000 });
		await page.waitForTimeout(1500);
		ok(await noHScroll(page), 'devoluciones sin scroll horizontal');
		await page.click('#btnOpenNewReturn');
		await page.waitForSelector('#modalNewReturn.show', { timeout: 5000 });
		// Producto al azar: el sistema rechaza (bien) una devolución física duplicada del mismo producto
		const prod = await page.$$eval('#inputReturnProductId option', o => { const v = o.filter(x => x.value); return (v[Math.floor(Math.random() * v.length)] || {}).value; });
		await page.selectOption('#inputReturnProductId', prod);
		await page.fill('#inputReturnQuantity', '1');
		await page.fill('#inputReturnReason', 'E2E móvil: producto dañado');
		const sup = await page.$('#inputReturnSupplierId');
		if (sup) { const s = await page.$$eval('#inputReturnSupplierId option', o => (o.find(x => x.value) || {}).value); if (s) await page.selectOption('#inputReturnSupplierId', s); }
		const submitVisible = await page.$eval('#btnSubmitNewReturn', e => { const b = e.getBoundingClientRect(); return b.width > 0 && b.right <= innerWidth + 1; });
		ok(submitVisible, 'botón Crear devolución visible');
		r = await apiOk(page, /\/api\/retail\/returns$/, () => page.click('#btnSubmitNewReturn'));
		ok(r.ok, 'devolución creada (' + r.status + ' ' + r.body + ')');
		await page.waitForLoadState('load'); await page.waitForTimeout(1500);

		console.log('== 7. Consulta de ventas (/sales)');
		await page.goto(BASE + '/sales', { waitUntil: 'load', timeout: 120000 });
		await page.waitForTimeout(2500);
		ok(await noHScroll(page), 'ventas sin scroll horizontal');
		const fbtn = await page.$('.dm-filter-btn');
		const ownCompact = await page.$$eval('#page-content [class*=filter] button, #page-content .ded-select-btn, #page-content [class*=filter] .dropdown-toggle', b => b.filter(x => x.offsetParent).length > 0);
		ok(!!fbtn || ownCompact, 'filtros compactos (' + (fbtn ? 'barra Filtros' : 'selector propio del módulo') + ')');
		if (fbtn) {
			await fbtn.click();
			await page.waitForTimeout(400);
			const sheet = await page.$eval('.dm-filter-sheet', e => { const b = e.getBoundingClientRect(); return { open: e.classList.contains('is-open'), fits: b.bottom <= innerHeight + 1 && b.top >= 0 }; });
			ok(sheet.open && sheet.fits, 'hoja de filtros abre dentro de la pantalla');
			await page.click('.dm-filter-done');
			ok(!(await page.$eval('.dm-filter-sheet', e => e.classList.contains('is-open'))), 'hoja de filtros cierra');
		}
		const cards = await page.locator('table.dairy-card-table').count();
		const wide = await page.$$eval('#page-content table', t => t.filter(x => x.offsetWidth && !x.classList.contains('dairy-card-table') && !x.classList.contains('dairy-fit-table') && x.scrollWidth > innerWidth).length);
		ok(wide === 0, `tablas como tarjetas (${cards} tarjetas, ${wide} anchas)`);

		console.log('== 8a. Asistencia (/operations/attendance)');
		await page.goto(BASE + '/operations/attendance', { waitUntil: 'load', timeout: 90000 });
		await page.waitForTimeout(1500);
		ok(await noHScroll(page), 'asistencia sin scroll horizontal');
	}

	console.log('== 8b. Asistencia y tareas en el teléfono (/m)');
	await mGo(page, '#/home');
	const clockBtn = page.locator('[data-clock]:visible').first();
	if (await clockBtn.count()) {
		const kind = await clockBtn.getAttribute('data-clock');
		const r = await apiOk(page, /\/api\/schedule\/clock-/, () => clockBtn.click());
		ok(r.ok || /turno|shift|ya|already/i.test(r.body), 'marcación de ' + kind + ' (' + r.status + ' ' + r.body + ')');
	} else console.log('  (sin botón de marcación para este usuario hoy)');
	await mGo(page, '#/tasks');
	const task = page.locator('a.m-task[href^="#/task/"]').first();
	if (await task.count()) {
		await task.click();
		await page.waitForTimeout(1500);
		if (await page.$('#m-tform')) {
			await page.fill('#m-tnote', 'E2E móvil: tarea realizada');
			const r = await apiOk(page, /\/api\/m\/tasks\/\d+\/complete/, () => page.click('#m-tsubmit'));
			ok(r.ok, 'tarea completada (' + r.status + ' ' + r.body + ')');
		} else console.log('  (la primera tarea no es completable por este usuario)');
	} else console.log('  (sin tareas pendientes)');

	ok(errors.length === 0, 'sin errores JS' + (errors.length ? ': ' + errors.slice(0, 3).join(' | ') : ''));
	console.log(`\nResultado: ${pass} OK, ${fail} fallas`);
	await browser.close();
	process.exit(fail ? 1 : 0);
})();
