const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    await page.goto("http://localhost:8080/loss-prevention", { waitUntil: "networkidle", timeout: 20000 });

    const tabs = [
        { id: 'tab-btn-expedientes', pane: '#pane-expedientes', name: '1_expedientes' },
        { id: 'tab-btn-mapa', pane: '#pane-mapa', name: '2_mapa_sucursales' },
        { id: 'tab-btn-disponibilidad', pane: '#pane-disponibilidad', name: '3_disponibilidad' },
        { id: 'tab-btn-tareas', pane: '#pane-tareas', name: '4_tareas' },
        { id: 'tab-btn-planes', pane: '#pane-planes', name: '5_planes_preventivos' },
        { id: 'tab-btn-anomalias', pane: '#pane-anomalias', name: '6_anomalias' }
    ];

    for (const t of tabs) {
        await page.click('#' + t.id);
        await page.waitForTimeout(300);
        const visible = await page.$eval(t.pane, el => !el.classList.contains('d-none'));
        const textSample = await page.$eval(t.pane, el => el.innerText.slice(0, 150).replace(/\n+/g, ' '));
        console.log(`Tab: ${t.id} -> Visible: ${visible} | Text: "${textSample}..."`);
        
        await page.screenshot({ 
            path: `C:/Users/renzo/.gemini/antigravity/brain/9cb989c6-05ae-4a92-bfbb-e019aaa0dfd8/captures/lp_tab_${t.name}.png`
        });
    }

    console.log("All tabs verified and screenshots saved successfully!");
    await browser.close();
})();

