const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const fs = require('fs');
const path = require('path');

(async () => {
    console.log('--- GENERANDO CAPTURA DEL CELULAR PARA LOCALHOST:8080 ---');
    const browser = await chromium.launch({ channel: 'msedge' });
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true
    });

    const page = await context.newPage();
    await page.goto('http://localhost:8080/m', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
        try {
            localStorage.setItem('daily_lang', 'en');
        } catch(e) {}
    });
    await page.goto('http://localhost:8080/m', { waitUntil: 'load' });
    await page.locator('.m-greeting-title').waitFor({ state: 'visible', timeout: 15000 });
    // Asegurar hora y fecha de Miami en tiempo real
    const miamiTime = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/New_York',
        hour: 'numeric',
        minute: '2-digit',
        hour12: false
    }).format(new Date());

    const miamiDateEn = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/New_York',
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    }).format(new Date());

    await page.evaluate(({ time, date }) => {
        // Hora de Miami en barra de estado
        var timeEl = document.getElementById('m-status-time');
        if (timeEl) timeEl.textContent = time;

        // Subtítulo con fecha exacta de Miami
        var sub = document.querySelector('.m-greeting-sub');
        if (sub) sub.textContent = date + ' | Miami Beach';

        // Badge de notificaciones en 1
        var nb = document.getElementById('m-notif-badge');
        if (nb) { nb.textContent = '1'; nb.hidden = false; }

        // En Tasks no hay badge en la foto de referencia
        var ib = document.getElementById('m-inbox-badge');
        if (ib) ib.hidden = true;

        // Badge de alertas en 3
        var ab = document.getElementById('m-alerts-badge');
        if (ab) { ab.textContent = '3'; ab.hidden = false; }
    }, { time: miamiTime, date: miamiDateEn });
    await page.waitForTimeout(600);

    // 1. Tomar captura de pantalla de m_home en 390x844
    const homePng = await page.screenshot({ clip: { x: 0, y: 0, width: 390, height: 844 } });

    // Convertir a WebP en el navegador
    const homeWebpBase64 = await page.evaluate(async (pngBase64) => {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = 390;
                canvas.height = 844;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, 390, 844);
                const dataUrl = canvas.toDataURL('image/webp', 0.94);
                resolve(dataUrl.split(',')[1]);
            };
            img.src = 'data:image/png;base64,' + pngBase64;
        });
    }, homePng.toString('base64'));

    const homeWebpBuffer = Buffer.from(homeWebpBase64, 'base64');
    fs.writeFileSync(path.join(__dirname, '../public/img/site/m_home.webp'), homeWebpBuffer);
    console.log('✓ public/img/site/m_home.webp actualizado exitosamente (' + homeWebpBuffer.length + ' bytes)');

    // 2. Abrir escáner y tomar captura de pantalla de m_scan en 390x844
    await page.locator('#m-qbtn-scan').click();
    await page.waitForTimeout(400);
    const scanPng = await page.screenshot({ clip: { x: 0, y: 0, width: 390, height: 844 } });

    const scanWebpBase64 = await page.evaluate(async (pngBase64) => {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = 390;
                canvas.height = 844;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, 390, 844);
                const dataUrl = canvas.toDataURL('image/webp', 0.94);
                resolve(dataUrl.split(',')[1]);
            };
            img.src = 'data:image/png;base64,' + pngBase64;
        });
    }, scanPng.toString('base64'));

    const scanWebpBuffer = Buffer.from(scanWebpBase64, 'base64');
    fs.writeFileSync(path.join(__dirname, '../public/img/site/m_scan.webp'), scanWebpBuffer);
    console.log('✓ public/img/site/m_scan.webp actualizado exitosamente (' + scanWebpBuffer.length + ' bytes)');

    await browser.close();
    console.log('--- ACTUALIZACIÓN COMPLETADA CON ÉXITO ---');
})();

