// Pantallas blancas reales: graba un screencast (CDP) mientras se navega por clic entre dashboards y analiza
// cada fotograma. Un fotograma "en blanco" es casi uniforme (desviación de luminancia baja) o no muestra el
// encabezado/menú. Uso: PW_DIR=... node tests/dairy/nav_flash_frames.js [desktop|mobile] [vueltas]
const { chromium } = require(process.env.PW_DIR || 'playwright');
const MODE = process.argv[2] || 'desktop', LAPS = +(process.argv[3] || 1);
const SEQ = ['/sales', '/stockoverview', '/waste', '/kpis?period=day', '/loss-prevention', '/quality', '/orders', '/dispatch', '/schedule', '/reports', '/today', '/alerts', '/kardex', '/pos'];
(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const mobile = MODE === 'mobile';
	const ctx = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	await page.goto('http://localhost:8080/today', { waitUntil: 'load', timeout: 180000 });
	const cdp = await ctx.newCDPSession(page);
	const frames = [];
	cdp.on('Page.screencastFrame', f => { frames.push({ t: Date.now(), url: page.url(), data: f.data }); cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => { }); });
	await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 40, everyNthFrame: 1 });
	for (let lap = 0; lap < LAPS; lap++) {
		for (const r of SEQ) {
			const ok = await page.evaluate(r => {
				const a = [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].find(a => { const u = new URL(a.href); return u.pathname + u.search === r; });
				if (!a || innerWidth < 992) return false; a.click(); return true;
			}, r).catch(() => false);
			if (!ok) await page.goto('http://localhost:8080' + r, { waitUntil: 'commit', timeout: 60000 }).catch(() => { });
			await page.waitForLoadState('load', { timeout: 60000 }).catch(() => { });
			await page.waitForTimeout(250);
		}
	}
	await cdp.send('Page.stopScreencast');
	// Análisis en una página auxiliar con canvas
	const an = await ctx.newPage();
	await an.setContent('<canvas id=c></canvas>');
	const res = [];
	for (let i = 0; i < frames.length; i += 40) res.push(...await an.evaluate(async (frames) => {
		const c = document.getElementById('c'), x = c.getContext('2d', { willReadFrequently: true });
		const out = [];
		for (const f of frames) {
			const im = new Image(); im.src = 'data:image/jpeg;base64,' + f.data; await im.decode();
			c.width = im.width; c.height = im.height; x.drawImage(im, 0, 0);
			const d = x.getImageData(0, 0, im.width, im.height).data;
			let s = 0, s2 = 0, n = 0;
			for (let i = 0; i < d.length; i += 4 * 16) { const l = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; s += l; s2 += l * l; n++; }
			const mean = s / n, sd = Math.sqrt(Math.max(0, s2 / n - mean * mean));
			// Franja del encabezado (primeros 50 px): con contenido tiene variación
			const h = x.getImageData(0, 0, im.width, Math.min(50, im.height)).data;
			let hs = 0, hs2 = 0, hn = 0;
			for (let i = 0; i < h.length; i += 4 * 8) { const l = 0.299 * h[i] + 0.587 * h[i + 1] + 0.114 * h[i + 2]; hs += l; hs2 += l * l; hn++; }
			const hsd = Math.sqrt(Math.max(0, hs2 / hn - (hs / hn) ** 2));
			out.push({ url: f.url.replace(/^https?:\/\/[^/]+/, ''), sd: Math.round(sd), hsd: Math.round(hsd), mean: Math.round(mean) });
		}
		return out;
	}, frames.slice(i, i + 40)));
	const blank = res.filter(r => r.sd < 12 || r.hsd < 4);
	console.log(JSON.stringify({ mode: MODE, frames: res.length, blankFrames: blank.length, minSd: Math.min(...res.map(r => r.sd)), minHeaderSd: Math.min(...res.map(r => r.hsd)), examples: blank.slice(0, 5) }));
	await browser.close();
})();
