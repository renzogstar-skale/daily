// Prueba rápida del idioma global: ES → EN → ES sin recarga, persistencia y errores de consola.
// Uso: PW_DIR=... node tests/dairy/i18n_smoke.js [ruta] [captura.png]
const { chromium } = require(process.env.PW_DIR || 'playwright');
(async () => {
	const route = process.argv[2] || '/stockoverview';
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push('pageerror: ' + e.message));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text().slice(0, 200)); });
	await page.goto('http://localhost:8080' + route, { waitUntil: 'load', timeout: 120000 });
	await page.waitForTimeout(1000);
	const snap = () => page.evaluate(() => ({ lang: DailyI18n.lang, html: document.documentElement.lang, h1: (document.querySelector('h1,h2,.page-title') || {}).innerText, side: [...document.querySelectorAll('.dairy-sidebar a, #sidebarResponsive a')].slice(0, 4).map(a => a.innerText.trim()).join(' | '), btn: (document.querySelector('[data-daily-lang-current]') || {}).textContent, pending: document.documentElement.classList.contains('daily-i18n-pending') }));
	const es1 = await snap();
	await page.click('.daily-lang-switch > button');
	await page.click('[data-daily-lang="en"]');
	await page.waitForTimeout(800);
	const en = await snap();
	const missing = await page.evaluate(() => Object.keys(DailyI18n.missing()).length);
	if (process.argv[3]) await page.screenshot({ path: process.argv[3] });
	await page.reload({ waitUntil: 'load' });
	await page.waitForTimeout(800);
	const enReload = await snap();
	await page.click('.daily-lang-switch > button');
	await page.click('[data-daily-lang="es"]');
	await page.waitForTimeout(500);
	const es2 = await snap();
	console.log(JSON.stringify({ es1, en, missing, enReload, es2, errors }, null, 1));
	await browser.close();
})();
