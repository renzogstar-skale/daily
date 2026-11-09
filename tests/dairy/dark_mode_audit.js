// Auditoría del modo Noche: fondos claros residuales, textos sin contraste y errores de consola.
// Uso: node tests/dairy/dark_mode_audit.js <carpeta_salida> [ruta1,ruta2,...] [desk|mob]
// Requiere PW_DIR (Playwright del caché npx) y el servidor en localhost:8080.
const path = require('path');
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR || 'playwright');

const ALL = ['/today', '/alerts', '/kpis', '/mrp', '/suppliers', '/branches', '/merchandise', '/scan',
	'/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation',
	'/sales', '/pos', '/orders', '/customers', '/dispatch', '/returns', '/quality', '/traceability', '/recalls',
	'/waste', '/waste/report', '/waste/approvals', '/waste/reasons', '/loss-prevention', '/loss-prevention/devices',
	'/reports/loss-executive', '/operations/scheduling', '/operations/attendance', '/workforce', '/reports',
	'/cost-centers', '/settings', '/users', '/products', '/data-import', '/locations/labels', '/audit-log',
	'/system-map', '/admin/economics', '/subscription', '/usersettings'];

// Se evalúa en la página: muestras de superficies claras y textos ilegibles en todo el documento
function probe()
{
	const parse = c => { const m = c && c.match(/^rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)$/); return m ? [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]] : null; };
	const lum = c => { const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
	const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
	const skip = 'canvas, img, video, iframe, svg, .leaflet-container, [data-dk-skip], .dk-keep, script, style';
	const id = e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.classList.length ? '.' + [...e.classList].filter(c => !c.startsWith('dk-') && !c.startsWith('crystal-')).slice(0, 2).join('.') : '');
	const vis = e => { const b = e.getBoundingClientRect(); if (b.width < 1 || b.height < 1) return null; const cs = getComputedStyle(e); if (cs.visibility === 'hidden' || +cs.opacity === 0) return null; return b; };
	function backOf(e)
	{
		for (let p = e; p && p.nodeType === 1; p = p.parentElement)
		{
			const cs = getComputedStyle(p), c = parse(cs.backgroundColor);
			if (c && c[3] >= 0.35) return c;
			if (cs.backgroundImage !== 'none')
			{
				if (cs.backgroundImage.includes('url(')) return null;
				const m = cs.backgroundImage.match(/rgba?\([^)]+\)/g) || [];
				const s = m.map(parse).find(x => x && x[3] >= 0.35);
				if (s) return s;
			}
		}
		return [11, 18, 32, 1];
	}
	const light = [], ink = [];
	let lightN = 0, inkN = 0;
	const all = document.body.querySelectorAll('*');
	for (const e of all)
	{
		if (e.closest(skip)) continue;
		const b = vis(e);
		if (!b) continue;
		const cs = getComputedStyle(e);
		const bg = parse(cs.backgroundColor);
		if (bg && bg[3] >= 0.5 && lum(bg) > 0.6 && b.width >= 40 && b.height >= 18)
		{
			lightN++;
			if (light.length < 8) light.push(id(e) + ' ' + cs.backgroundColor + ' ' + Math.round(b.width) + 'x' + Math.round(b.height));
		}
		let txt = false;
		for (let n = e.firstChild; n; n = n.nextSibling) if (n.nodeType === 3 && n.nodeValue.trim()) { txt = true; break; }
		if (!txt) continue;
		const col = parse(cs.color);
		if (!col || col[3] < 0.5) continue;
		const back = backOf(e);
		if (!back) continue;
		const r = ratio(col, back);
		if (r < 3)
		{
			inkN++;
			if (ink.length < 8) ink.push(id(e) + ' "' + e.textContent.trim().slice(0, 24) + '" ' + cs.color + ' on ' + back.slice(0, 3).join(',') + ' (' + r.toFixed(2) + ')');
		}
	}
	return {
		dark: document.documentElement.classList.contains('daily-dark'),
		bodyBg: getComputedStyle(document.body).backgroundColor,
		lightN, light, inkN, ink
	};
}

(async () => {
	const out = process.argv[2] || '.playwright-mcp/dark';
	const routes = process.argv[3] ? process.argv[3].split(',') : ALL;
	const vp = process.argv[4] || 'desk';
	fs.mkdirSync(out, { recursive: true });
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: vp === 'mob' ? { width: 390, height: 844 } : { width: 1440, height: 900 }, deviceScaleFactor: 1 });
	await ctx.addCookies([{ name: 'daily_mode', value: 'dark', domain: 'localhost', path: '/' }]);
	await ctx.addInitScript(() => { try { localStorage.setItem('daily_mode', 'dark'); } catch (e) { } });
	const page = await ctx.newPage();
	const report = [];
	for (const r of routes)
	{
		const errors = [];
		const onErr = e => errors.push('pageerror: ' + e.message.slice(0, 200));
		const onCon = m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text().slice(0, 200)); };
		page.on('pageerror', onErr); page.on('console', onCon);
		let status = 0, res = {};
		try
		{
			const resp = await page.goto('http://localhost:8080' + r, { waitUntil: 'load', timeout: 120000 });
			status = resp ? resp.status() : 0;
			await page.waitForTimeout(1500);
			res = await page.evaluate(probe);
		}
		catch (e) { errors.push('nav: ' + e.message.slice(0, 160)); }
		const name = r.replace(/^\//, '').replace(/[\/#?=&]/g, '_') || 'root';
		await page.screenshot({ path: path.join(out, `${name}.${vp}.png`) }).catch(() => {});
		page.off('pageerror', onErr); page.off('console', onCon);
		report.push(Object.assign({ route: r, status, errors }, res));
		console.log(`${r} ${status} dark=${res.dark} light=${res.lightN} ink=${res.inkN} err=${errors.length}`);
	}
	fs.writeFileSync(path.join(out, `report.${vp}.json`), JSON.stringify(report, null, 1));
	await browser.close();
})();
