// Barrido de Ventas y Rentabilidad: todos los rangos de fecha, modos de comparación y filtros principales.
// Por combinación revisa en las 6 pestañas: «Sin información», gráficos sin datos, NaN/undefined y errores JS.
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/sweep_sales_reports.js [salida.json]
const fs = require('fs');
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE = process.env.DAILY_URL || 'http://localhost:8080';

const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const now = new Date();
const D = (y, m, d) => iso(new Date(y, m, d));
const Y = now.getFullYear(), M = now.getMonth(), T = now.getDate();
const monday = new Date(Y, M, T - ((now.getDay() + 6) % 7));
const RANGES = {
	'Hoy': [iso(now), iso(now)],
	'Ayer': [D(Y, M, T - 1), D(Y, M, T - 1)],
	'Semana actual': [iso(monday), iso(now)],
	'Semana anterior': [iso(new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() - 7)), iso(new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() - 1))],
	'Mes actual': [D(Y, M, 1), iso(now)],
	'Mes anterior': [D(Y, M - 1, 1), D(Y, M, 0)],
	'Trimestre actual': [D(Y, Math.floor(M / 3) * 3, 1), iso(now)],
	'Año actual': [D(Y, 0, 1), iso(now)],
	'30 días': [D(Y, M, T - 29), iso(now)],
	'Año 2025': ['2025-01-01', '2025-12-31']
};
const CASES = [];
for (const [name, [a, b]] of Object.entries(RANGES)) {
	CASES.push({ name, qs: `start_date=${a}&end_date=${b}` });
	CASES.push({ name: name + ' · interanual', qs: `start_date=${a}&end_date=${b}&compare_with=year_ago` });
}
CASES.push({ name: '30 días · sucursal 1', qs: `start_date=${RANGES['30 días'][0]}&end_date=${iso(now)}&branch_id=1` });
CASES.push({ name: '30 días · Lácteos', qs: `start_date=${RANGES['30 días'][0]}&end_date=${iso(now)}&department=dairy` });
CASES.push({ name: '30 días · sucursal 4 + Tecnología', qs: `start_date=${RANGES['30 días'][0]}&end_date=${iso(now)}&branch_id=4&department=tecnologia` });
CASES.push({ name: 'Año actual · Carnes', qs: `start_date=${D(Y, 0, 1)}&end_date=${iso(now)}&department=carnes` });

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
	let errors = [];
	page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
	page.on('console', m => { if ((m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) || /Informe /.test(m.text())) errors.push(m.text().slice(0, 160)); });
	const report = [];
	for (const c of CASES) {
		errors = [];
		const t0 = Date.now();
		await page.goto(`${BASE}/sales?tab=resumen&${c.qs}`, { waitUntil: 'load', timeout: 180000 });
		const ms = Date.now() - t0;
		const r = await page.evaluate(async () => {
			const out = {};
			const keys = { dashboard: 'resumen', departments: 'departamentos', products: 'productos', branches: 'sucursales', history: 'detalle', profitability: 'rentabilidad' };
			for (const [k, tab] of Object.entries(keys)) {
				const btn = [...document.querySelectorAll('[id="pill-' + tab + '"]')].find(b => b.offsetParent !== null) || document.querySelector('[id="pill-' + tab + '"]');
				btn.click();
				await new Promise(res => setTimeout(res, 450));
				const sec = document.getElementById('srep-' + k);
				if (!sec) { out[k] = { missing: true }; continue; }
				const empties = new Set();
				const w = document.createTreeWalker(sec, NodeFilter.SHOW_TEXT);
				let n;
				while ((n = w.nextNode())) {
					if (/Sin información|sin información/.test(n.textContent)) {
						const card = n.parentElement.closest('.ded-kpi-card, .ded-card, .ded-comp-row, td, th');
						const label = card ? ((card.querySelector('.ded-kpi-label, .ded-card-title, .ded-comp-label') || {}).textContent || card.tagName) : '?';
						empties.add(label.trim().slice(0, 50));
					}
				}
				const emptyCharts = [];
				sec.querySelectorAll('canvas').forEach(cv => {
					const ch = window.DailySalesReports.charts[cv.id];
					if (!ch) { emptyCharts.push(cv.id + ' (sin dibujar)'); return; }
					const vals = ch.data.datasets.flatMap(d => d.data.map(v => (v && typeof v === 'object') ? v.y : v)).filter(v => v !== null && v !== undefined && v !== 0);
					if (!vals.length) emptyCharts.push(cv.id);
				});
				const txt = sec.innerText;
				// Tab completa (componentes existentes) también
				const pane = document.getElementById('tab-' + tab);
				const paneEmpty = (pane.innerText.match(/Sin información/g) || []).length - (txt.match(/Sin información/g) || []).length;
				out[k] = { empties: [...empties], emptyCharts, nan: /NaN|undefined|Infinity/.test(txt), paneEmptyOutside: paneEmpty };
			}
			const data = JSON.parse(document.getElementById('srepData').textContent);
			out._net = data.dashboard.net;
			out._consistency = data.consistency.ok;
			return out;
		});
		report.push({ case: c.name, ms, errors: [...errors], ...r });
		const flag = Object.entries(r).filter(([k]) => !k.startsWith('_')).map(([k, v]) => `${k}: ${v.empties.length} SI, ${v.emptyCharts.length} vacíos${v.nan ? ', NaN' : ''}`).join(' | ');
		console.log(`${c.name.padEnd(36)} ${String(ms).padStart(5)}ms net=${Math.round(r._net)} ok=${r._consistency} err=${errors.length} :: ${flag}`);
	}
	fs.writeFileSync(process.argv[2] || 'sweep_sales_reports.json', JSON.stringify(report, null, 1));
	await browser.close();
})();
