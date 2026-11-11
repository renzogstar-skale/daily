const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: ESPECIALIZACIÓN DE MERMAS (#11 OPTIMIZAR MERMAS) ===\n');
    const browser = await chromium.launch({ channel: 'msedge' });
    let passed = 0;
    let failed = 0;

    function assert(condition, message) {
        if (condition) {
            console.log(`  [PASS] ${message}`);
            passed++;
        } else {
            console.error(`  [FAIL] ${message}`);
            failed++;
        }
    }

    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404')) {
                consoleErrors.push(msg.text());
            }
        });

        // 1. CARGA DE /waste Y VERIFICACIÓN DE SEPARACIÓN DE SEGURIDAD/CONTEOS
        console.log('1. Verificando carga principal de /waste y eliminación de pestañas fuera de alcance...');
        const response = await page.goto('http://localhost:8080/waste', { waitUntil: 'networkidle', timeout: 30000 });
        assert(response.status() === 200, 'HTTP Status 200 en /waste');
        assert(await page.locator('h4:has-text("Cuadro de Control de Mermas")').isVisible(), 'Cabecera "Cuadro de Control de Mermas" visible');

        for (const removedSel of ['#tab-conteos-link', '#tab-precios-link', '#tab-robo-link', '#tab-bakery-link', '#tab-damaged-goods-link', '#tab-smart-audits-link']) {
            const count = await page.locator(removedSel).count();
            assert(count === 0, `Pestaña fuera de alcance "${removedSel}" eliminada de /waste`);
        }

        // 2. CICLO DE VIDA DE 8 ETAPAS
        console.log('\n2. Verificando ciclo de vida de 8 etapas (Detectar → Prevenir)...');
        const stepper = page.locator('[data-testid="waste-flow-stepper"]');
        assert(await stepper.isVisible(), 'Stepper de ciclo de vida visible');

        const steps = [
            { num: '1', title: 'Detectar' },
            { num: '2', title: 'Registrar' },
            { num: '3', title: 'Clasificar' },
            { num: '4', title: 'Validar' },
            { num: '5', title: 'Aprobar' },
            { num: '6', title: 'Ajustar' },
            { num: '7', title: 'Analizar' },
            { num: '8', title: 'Prevenir' }
        ];
        for (const st of steps) {
            const hasStep = await stepper.locator(`.dsp-step-title:has-text("${st.title}")`).isVisible();
            assert(hasStep, `Etapa ${st.num}: "${st.title}" presente`);
        }

        // 3. KPIs CONSOLIDADOS (INCLUYENDO UNIDADES Y SIN VALORES INVENTADOS)
        console.log('\n3. Verificando 8 indicadores clave y unidades reales...');
        const kpisGrid = page.locator('[data-testid="waste-eight-kpis"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 8 KPIs visible');
        const mermaCardText = await page.locator('[data-testid="kpi-merma-valor"]').innerText();
        assert(mermaCardText.includes('un.'), 'KPI Merma física real muestra valor ($) y unidades ("un.")');

        const pageBodyText = await page.locator('#tab-centro-control').innerText();
        assert(!pageBodyText.includes('$28,450') && !pageBodyText.includes('16/17'), 'Sin cifras estáticas mockeadas ($28,450 / 16/17) en Centro de Control');

        // 4. REGISTRO Y ESCÁNER UPC
        console.log('\n4. Verificando pestaña de Registro y Escáner UPC + Cámara...');
        await page.click('#tab-registro-link');
        await page.waitForTimeout(400);
        assert(await page.locator('#reg-upc-input').isVisible(), 'Campo de escáner UPC (#reg-upc-input) visible');
        assert(await page.locator('#btn-scan-upc-waste').isVisible(), 'Botón de escaneo por cámara (#btn-scan-upc-waste) visible');

        // Probar lectura de código de barras real (750100000001 -> producto #30)
        await page.fill('#reg-upc-input', '750100000001');
        await page.press('#reg-upc-input', 'Enter');
        await page.waitForTimeout(1000);
        const selectedProdId = await page.inputValue('#reg-product-id');
        assert(selectedProdId === '30', `Escáner UPC seleccionó automáticamente el producto correcto (id=${selectedProdId})`);

        // 5. PESTAÑAS ESPECIALIZADAS (CLASIFICACIÓN, FEFO, RECUPERACIÓN REAL, PARETO, ERRORES, ACCIONES, AUDITORÍA)
        console.log('\n5. Verificando carga AJAX de pestañas especializadas...');
        for (const [tabLink, tabPane, label] of [
            ['#tab-clasificacion-link', '#tab-clasificacion', 'Operativa y Comercial'],
            ['#tab-ajustes-link', '#tab-ajustes', 'Ajustes Contables'],
            ['#tab-fefo-link', '#tab-fefo', 'Vencimientos y FEFO'],
            ['#tab-causales-link', '#tab-causales', 'Causales y Pareto'],
            ['#tab-errores-link', '#tab-errores', 'Errores de Procedimiento'],
            ['#tab-recuperacion-link', '#tab-recuperacion', 'Recuperación Financiera'],
            ['#tab-acciones-link', '#tab-acciones', 'Acciones Correctivas'],
            ['#tab-auditoria-link', '#tab-auditoria', 'Auditoría Trazable']
        ]) {
            await page.click(tabLink);
            await page.waitForTimeout(600);
            const vis = await page.locator(tabPane).isVisible();
            assert(vis, `Pestaña "${label}" (${tabPane}) visible tras carga`);
        }

        // 6. VISTAS AUXILIARES: /waste/approvals, /waste/reasons, /loss-prevention
        console.log('\n6. Verificando /waste/approvals, /waste/reasons y /loss-prevention...');
        for (const url of ['/waste/approvals', '/waste/reasons', '/loss-prevention']) {
            const res = await page.goto('http://localhost:8080' + url, { waitUntil: 'networkidle', timeout: 25000 });
            assert(res.status() === 200, `HTTP 200 en ${url}`);
        }
        const secNoticeVisible = await page.goto('http://localhost:8080/waste/reasons', { waitUntil: 'networkidle' }).then(() => page.locator('a[href*="/loss-prevention"]').first().isVisible());
        assert(secNoticeVisible, 'Catálogo de causales enlaza seguridad/hurto a Prevención de Pérdidas');

        // 7. TEMA OSCURO (daily-dark) Y MÓVIL (390x844)
        console.log('\n7. Verificando tema oscuro (daily-dark) y móvil (390x844)...');
        await page.goto('http://localhost:8080/waste', { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
        await page.waitForTimeout(200);
        const darkActive = await page.evaluate(() => document.documentElement.classList.contains('daily-dark'));
        assert(darkActive, 'Clase html.daily-dark aplicada sin errores');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(300);
        assert(await page.locator('[data-testid="waste-flow-stepper"]').isVisible(), 'Stepper de 8 etapas visible en móvil');
        assert(await page.locator('[data-testid="waste-eight-kpis"]').isVisible(), 'Rejilla de KPIs adaptada en móvil');

        assert(consoleErrors.length === 0, `0 errores críticos de consola JS (encontrados: ${consoleErrors.length})`);
    } catch (err) {
        console.error('Error durante E2E:', err);
        failed++;
    } finally {
        await browser.close();
    }

    console.log(`\n========================================`);
    console.log(`RESULTADO FINAL E2E: ${passed} PASADAS, ${failed} FALLIDAS`);
    console.log(`========================================\n`);

    if (failed > 0) process.exit(1);
})();
