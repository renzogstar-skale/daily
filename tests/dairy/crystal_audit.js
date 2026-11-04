// Auditoría visual Crystal: capturas escritorio/móvil, errores de consola y desbordamiento horizontal.
// Uso: node tests/dairy/crystal_audit.js <carpeta_salida> [ruta1,ruta2,...]
const path = require('path');
const fs = require('fs');
const pwDir = process.env.PW_DIR;
const { chromium } = require(pwDir);

const ALL = ['/today', '/alerts', '/kpis', '/mrp', '/ai-advisor', '/suppliers', '/branches', '/merchandise', '/scan',
	'/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation',
	'/sales', '/pos', '/orders', '/customers', '/dispatch', '/returns', '/quality', '/traceability', '/recalls',
	'/waste', '/waste/report', '/waste/approvals', '/waste/reasons', '/loss-prevention', '/loss-prevention/devices',
	'/reports/loss-executive', '/operations/scheduling', '/operations/attendance', '/workforce', '/reports',
	'/cost-centers', '/settings', '/users', '/products', '/data-import', '/locations/labels', '/audit-log',
	'/system-map', '/admin/economics'];

(async () => {
	const out = process.argv[2];
	const routes = process.argv[3] ? process.argv[3].split(',') : ALL;
	fs.mkdirSync(out, { recursive: true });
	const browser = await chromium.launch({ channel: 'msedge' });
	const report = [];
	for (const [vp, size] of [['desk', { width: 1440, height: 900 }], ['mob', { width: 390, height: 844 }]]) {
		const ctx = await browser.newContext({ viewport: size, deviceScaleFactor: 1 });
		const page = await ctx.newPage();
		for (const r of routes) {
			const errors = [];
			const onErr = e => errors.push('pageerror: ' + e.message);
			const onCon = m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text().slice(0, 200)); };
			page.on('pageerror', onErr); page.on('console', onCon);
			let status = 0, ms = 0;
			try {
				const t = Date.now();
				const resp = await page.goto('http://localhost:8080' + r, { waitUntil: 'load', timeout: 90000 });
				status = resp ? resp.status() : 0;
				await page.waitForTimeout(1200);
				ms = Date.now() - t;
			} catch (e) { errors.push('nav: ' + e.message.slice(0, 120)); }
			const ov = await page.evaluate(() => {
				const w = document.documentElement.clientWidth;
				const wide = [...document.querySelectorAll('body *')].filter(e => {
					const b = e.getBoundingClientRect();
					return b.right > w + 2 && b.width > 0 && getComputedStyle(e).position !== 'fixed' && !e.closest('.table-responsive,.dataTables_wrapper,[style*="overflow"]');
				}).slice(0, 3).map(e => e.tagName + '.' + [...e.classList].slice(0, 2).join('.'));
				// Contenido que se sale de su tarjeta (desbordamiento recortado o visible)
				const spill = [];
				document.querySelectorAll('.crystal-surface:not(.crystal-surface--nested), .card').forEach(c => {
					const cb = c.getBoundingClientRect();
					if (!cb.width || c.closest('.modal, .dropdown-menu')) return;
					const bad = [...c.querySelectorAll('*')].find(k => {
						const kb = k.getBoundingClientRect();
						return kb.width > 0 && kb.height > 0 && k.childElementCount === 0 && (k.textContent || '').trim() && kb.right > cb.right + 6 && getComputedStyle(k).visibility !== 'hidden' && !k.closest('.table-responsive,.dataTables_wrapper,[style*="overflow"],.dropdown-menu');
					});
					if (bad && spill.length < 3) spill.push(c.className.split(' ')[0] + '>' + bad.tagName + ':' + bad.textContent.trim().slice(0, 20));
				});
				return { scroll: document.documentElement.scrollWidth > w + 1, wide, spill };
			}).catch(() => ({}));
			const name = r.replace(/^\//, '').replace(/[\/#]/g, '_') || 'root';
			await page.screenshot({ path: path.join(out, `${name}.${vp}.png`) }).catch(() => {});
			page.off('pageerror', onErr); page.off('console', onCon);
			report.push({ route: r, vp, status, ms, hscroll: ov.scroll, wide: ov.wide, spill: ov.spill, errors });
			console.log(`${vp} ${r} ${status} ${ms}ms hscroll=${ov.scroll} spill=${(ov.spill || []).length} err=${errors.length}`);
		}
		await ctx.close();
	}
	fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 1));
	await browser.close();
})();
