// Auditoría móvil DAILY: cada ruta a 320/375/390/430/768/1024 px con emulación táctil.
// Detecta scroll horizontal de página, elementos fuera del viewport, contenido que se sale de tarjetas,
// tablas anchas sin convertir, gráficos desbordados, objetivos táctiles pequeños, solapes con la barra
// inferior, cabecera/áreas seguras y errores de consola.
// Uso: PW_DIR=... node tests/dairy/mobile_audit.js <salida_dir> [rutas,...] [anchos,...] [rol] [es|en] [light|dark]
const path = require('path');
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR || 'playwright');

const BASE = process.env.DAILY_BASE || 'http://localhost:8080';
const ALL = ['/today', '/alerts', '/kpis', '/mrp', '/suppliers', '/branches', '/merchandise', '/scan',
	'/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation',
	'/sales', '/sales/reports', '/pos', '/orders', '/customers', '/dispatch', '/returns', '/quality', '/traceability', '/recalls',
	'/waste', '/waste/report', '/waste/approvals', '/loss-prevention', '/operations/scheduling', '/operations/attendance',
	'/workforce', '/reports', '/purchase', '/consume', '/settings', '/products', '/m'];

(async () => {
	const out = process.argv[2];
	const routes = process.argv[3] && process.argv[3] !== '-' ? process.argv[3].split(',') : ALL;
	const widths = (process.argv[4] && process.argv[4] !== '-' ? process.argv[4] : '320,375,390,430,768,1024').split(',').map(Number);
	const role = process.argv[5] || 'manager';
	const lang = process.argv[6] || 'es';
	const theme = process.argv[7] || 'light';
	fs.mkdirSync(out, { recursive: true });
	const browser = await chromium.launch({ channel: 'msedge' });
	const report = [];
	for (const w of widths) {
		const ctx = await browser.newContext({ viewport: { width: w, height: +(process.env.VH || (w < 768 ? 800 : 1024)) }, deviceScaleFactor: 1, isMobile: w < 1024, hasTouch: true });
		const cookies = [{ name: 'daily_simulated_role', value: role, url: BASE }, { name: 'daily_lang', value: lang, url: BASE }];
		if (theme === 'dark') cookies.push({ name: 'daily_mode', value: 'dark', url: BASE });
		await ctx.addCookies(cookies);
		await ctx.addInitScript(([l, t]) => { try { localStorage.setItem('daily_lang', l); localStorage.setItem('daily_mode', t); } catch (e) {} }, [lang, theme]);
		const page = await ctx.newPage();
		for (const r of routes) {
			const errors = [];
			const onErr = e => errors.push('pageerror: ' + e.message.slice(0, 160));
			const onCon = m => { if (m.type() === 'error' && !/favicon|Failed to load resource|net::ERR/.test(m.text())) errors.push(m.text().slice(0, 160)); };
			page.on('pageerror', onErr); page.on('console', onCon);
			let status = 0, ms = 0, landed = '';
			try {
				const t = Date.now();
				const resp = await page.goto(BASE + r, { waitUntil: 'load', timeout: 120000 });
				status = resp ? resp.status() : 0;
				await page.waitForTimeout(3000); // KPI y tablas que llegan por AJAX
				ms = Date.now() - t;
				landed = new URL(page.url()).pathname;
			} catch (e) { errors.push('nav: ' + e.message.slice(0, 120)); }
			const res = await page.evaluate(() => {
				// content-visibility obliga a un layout por cada medición en subárboles omitidos: se desactiva para medir
				const cvOff = document.createElement('style');
				cvOff.textContent = '*{content-visibility:visible!important;contain-intrinsic-size:none!important}';
				document.head.appendChild(cvOff);
				const vw = document.documentElement.clientWidth, vh = window.innerHeight;
				const vis = e => { const s = getComputedStyle(e); return s.visibility !== 'hidden' && s.display !== 'none' && +s.opacity !== 0; };
				const spCache = new Map();
				const scrolledParent = e => {
					const p = e.parentElement;
					if (!p || p === document.body) return null;
					if (spCache.has(p)) return spCache.get(p);
					const r = /(auto|scroll)/.test(getComputedStyle(p).overflowX) ? p : scrolledParent(p);
					spCache.set(p, r);
					return r;
				};
				const label = e => (e.id ? '#' + e.id : e.tagName.toLowerCase() + ([...e.classList].slice(0, 2).map(c => '.' + c).join(''))) + (e.textContent ? ':' + e.textContent.trim().replace(/\s+/g, ' ').slice(0, 24) : '');
				const pc = document.querySelector('#page-content') || document.body;
				// Hojas (sin hijos) y controles con caja: el rect primero (barato), estilos solo en candidatos
				const leaves = [...pc.querySelectorAll('*')].slice(0, 40000).filter(e => e.childElementCount === 0 || /^(BUTTON|A|INPUT|SELECT|IMG|CANVAS|svg)$/.test(e.tagName)).map(e => [e, e.getBoundingClientRect()]).filter(([, b]) => b.width > 0 && b.height > 0);
				// 1) Elementos que salen del viewport (cortados o fuera) sin un contenedor desplazable que los contenga
				const offscreen = leaves.filter(([e, b]) => (b.right > vw + 1 || b.left < -1) && ((e.textContent || '').trim() || /^(BUTTON|A|INPUT|SELECT|IMG)$/.test(e.tagName)) && !e.closest('.modal,.dropdown-menu,#sidebarResponsive,[aria-hidden="true"],.sr-only') && vis(e) && getComputedStyle(e).position !== 'fixed' && !scrolledParent(e)).slice(0, 5).map(([e]) => label(e));
				// 2) Texto que se sale de su tarjeta (sin contenedor desplazable)
				const spill = [], cardRect = new Map();
				for (const [k, kb] of leaves) {
					if (spill.length >= 4) break;
					if (k.closest('table.dairy-card-table thead')) continue; // cabecera oculta de las tarjetas (solo lectores de pantalla)
					const c = k.closest('.card, .crystal-surface:not(.crystal-surface--nested)');
					if (!c || c.closest('.modal, .dropdown-menu')) continue;
					if (!cardRect.has(c)) cardRect.set(c, c.getBoundingClientRect());
					const cb = cardRect.get(c);
					if (cb.width && (kb.right > cb.right + 4 || kb.left < cb.left - 4) && (k.textContent || '').trim() && !scrolledParent(k) && vis(k)) spill.push(label(k));
				}
				// 3) Tablas visibles más anchas que la pantalla (scroll interno) que no son tarjetas
				const wideTables = [...pc.querySelectorAll('table')].filter(t => vis(t) && t.offsetWidth > 0 && !t.classList.contains('dairy-card-table') && t.scrollWidth > vw - 8 && !t.closest('.modal')).map(t => (t.id || t.className.split(' ').slice(0, 2).join('.')) + '[' + t.scrollWidth + ']').slice(0, 5);
				// 4) Gráficos: canvas más ancho que su contenedor o demasiado bajos para leer
				const charts = [...pc.querySelectorAll('canvas')].filter(c => vis(c) && c.offsetWidth > 0).map(c => { const b = c.getBoundingClientRect(); return { id: c.id || c.parentElement.className.split(' ')[0], w: Math.round(b.width), h: Math.round(b.height), out: b.right > vw + 1 || b.left < -1 }; });
				const badCharts = charts.filter(c => c.out || (c.h >= 60 && c.h < 110 && c.w > 160)).map(c => `${c.id}:${c.w}x${c.h}${c.out ? ' fuera' : ''}`);
				// 5) Objetivos táctiles < 32 px de alto (botones, controles) visibles en el primer tramo de la página
				const small = [...pc.querySelectorAll('button, .btn, input:not([type=hidden]), select, [role=button], a.nav-link, .page-link')].filter(e => {
					const b = e.getBoundingClientRect();
					return vis(e) && b.width > 0 && b.height > 0 && b.height < 32 && b.top < vh * 3 && !e.closest('.dropdown-menu,.modal,.sr-only,[aria-hidden="true"]') && !(e.type === 'checkbox' || e.type === 'radio');
				}).slice(0, 6).map(e => label(e) + '[' + Math.round(e.getBoundingClientRect().height) + ']');
				// 6) Barra inferior: presencia, ítems y elementos fijos que la tapan
				const nav = document.querySelector('#dairyBottomNav');
				const navVis = !!nav && vis(nav) && nav.getBoundingClientRect().height > 0;
				const navItems = navVis ? [...nav.querySelectorAll('.dairy-bottom-nav-item')].map(i => i.textContent.trim()) : [];
				let navOverlap = [];
				if (navVis) {
					const nb = nav.getBoundingClientRect();
					// Lo que queda encima de la barra en 5 puntos de su borde superior (elementos fijos que la tapan)
					const set = new Set();
					for (let i = 1; i <= 5; i++) {
						const top = document.elementFromPoint(nb.left + nb.width * i / 6, nb.top + 6);
						if (top && !nav.contains(top) && !top.closest('.modal,.toast,#toast-container')) set.add(label(top));
					}
					navOverlap = [...set];
				}
				const padB = parseFloat(getComputedStyle(document.body).paddingBottom) || 0;
				// 7) Cabecera: altura y elementos de la cabecera que se salen
				const hdr = document.querySelector('#mainNav');
				const hdrH = hdr ? Math.round(hdr.getBoundingClientRect().height) : 0;
				const hdrOut = hdr ? [...hdr.querySelectorAll('*')].filter(e => { const b = e.getBoundingClientRect(); return vis(e) && b.width > 0 && b.height > 0 && (b.right > vw + 1 || b.left < -1) && !e.closest('#sidebarResponsive,.dropdown-menu,.collapse:not(.show)'); }).slice(0, 4).map(label) : [];
				// 8) KPIs visibles en el primer pantallazo
				const kpis = [...pc.querySelectorAll('.dairy-kpi-card, .kpi-hero-card, .daily-kpi, [class*="kpi-card"]')].filter(e => vis(e) && e.getBoundingClientRect().height > 0);
				const firstFold = kpis.filter(e => e.getBoundingClientRect().top < vh).length;
				// 9) Estados vacíos sin mensaje: contenedores vacíos visibles con altura
				const blankText = (pc.innerText || '').trim().length;
				const viewport = document.querySelector('meta[name=viewport]');
				return {
					hscroll: document.documentElement.scrollWidth > vw + 1, sw: document.documentElement.scrollWidth, vw,
					offscreen, spill, wideTables, badCharts, chartCount: charts.length, small,
					nav: navVis, navItems, navOverlap, padB, hdrH, hdrOut, kpis: kpis.length, firstFold, blankText,
					viewportFit: viewport ? /viewport-fit=cover/.test(viewport.content) : false,
					dark: document.body.classList.contains('night-mode') || document.documentElement.dataset.dailyMode === 'dark',
					lang: document.documentElement.lang
				};
			}).catch(e => ({ evalError: e.message.slice(0, 120) }));
			if (res.evalError) errors.push("eval: " + res.evalError);
			const name = (r.replace(/^\//, '').replace(/[\/#?=&]/g, '_') || 'root') + '.' + w;
			if (w === 390 || w === 320 || process.env.SHOTS === 'all') await page.screenshot({ path: path.join(out, name + '.png') }).catch(() => {});
			page.off('pageerror', onErr); page.off('console', onCon);
			const row = { route: r, w, role, lang, theme, status, ms, landed, ...res, errors };
			report.push(row);
			const flags = [res.hscroll && 'HSCROLL ' + res.sw, res.offscreen && res.offscreen.length && 'off:' + res.offscreen.length, res.spill && res.spill.length && 'spill:' + res.spill.length, res.wideTables && res.wideTables.length && 'tbl:' + res.wideTables.length, res.badCharts && res.badCharts.length && 'chart:' + res.badCharts.length, res.navOverlap && res.navOverlap.length && 'navOv:' + res.navOverlap.length, res.hdrOut && res.hdrOut.length && 'hdr:' + res.hdrOut.length, errors.length && 'err:' + errors.length].filter(Boolean).join(' ');
			console.log(`${w} ${r} ${status} ${ms}ms ${flags || 'ok'}`);
		}
		await ctx.close();
	}
	fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 1));
	await browser.close();
})();
