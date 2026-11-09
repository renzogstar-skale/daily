const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: CONSOLIDACIÓN DE MERMAS Y PREVENCIÓN (/waste & /loss-prevention) ===\n');
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

        // Capturar errores de consola
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404')) {
                consoleErrors.push(msg.text());
            }
        });

        // 1. CARGA DE /waste
        console.log('1. Verificando carga principal de /waste...');
        const response = await page.goto('http://localhost:8080/waste', { waitUntil: 'networkidle', timeout: 30000 });
        assert(response.status() === 200, 'HTTP Status 200 en /waste');

        // 2. TÍTULO Y CABECERA DEL MÓDULO
        const pageHeader = await page.locator('h4:has-text("Cuadro de Control de Mermas")').isVisible();
        assert(pageHeader, 'Cabecera "Cuadro de Control de Mermas" visible');

        // 3. FLUJO OPERATIVO (9 PASOS OBLIGATORIOS)
        console.log('\n2. Verificando Flujo Operativo de Mermas y Prevención (9 Pasos)...');
        const stepper = page.locator('[data-testid="waste-flow-stepper"]');
        assert(await stepper.isVisible(), 'Contenedor del stepper de ciclo de vida visible');

        const steps = [
            { num: '1', title: 'Detectar' },
            { num: '2', title: 'Registrar' },
            { num: '3', title: 'Clasificar' },
            { num: '4', title: 'Investigar' },
            { num: '5', title: 'Aprobar' },
            { num: '6', title: 'Corregir' },
            { num: '7', title: 'Recuperar' },
            { num: '8', title: 'Cerrar' },
            { num: '9', title: 'Prevenir' }
        ];

        for (const st of steps) {
            const hasStep = await stepper.locator(`.dsp-step-title:has-text("${st.title}")`).isVisible();
            assert(hasStep, `Paso ${st.num}: "${st.title}" presente y rotulado`);
        }

        // 4. LOS 8 INDICADORES PRINCIPALES CONSOLIDADOS
        console.log('\n3. Verificando los 8 Indicadores Clave de Mermas y Prevención...');
        const kpisGrid = page.locator('[data-testid="waste-eight-kpis"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 8 KPIs visible');

        const kpiTests = [
            { id: 'kpi-merma-valor', name: 'Merma en valor ($)', label: 'Merma en valor' },
            { id: 'kpi-merma-porcentual', name: 'Merma porcentual (% sobre ventas)', label: 'Merma porcentual' },
            { id: 'kpi-top-sku', name: 'Top SKU con pérdidas', label: 'Top SKU con pérdidas' },
            { id: 'kpi-casos-investigacion', name: 'Casos abiertos de investigación', label: 'Casos de investigación' },
            { id: 'kpi-recuperacion-financiera', name: 'Recuperación financiera ($)', label: 'Recuperación financiera' },
            { id: 'kpi-cumplimiento-fefo', name: 'Cumplimiento FEFO (%)', label: 'Cumplimiento FEFO' },
            { id: 'kpi-tiempo-resolucion', name: 'Tiempo de resolución (días)', label: 'Tiempo de resolución' },
            { id: 'kpi-tasa-reincidencias', name: 'Tasa de reincidencias (%)', label: 'Tasa de reincidencias' }
        ];

        for (const k of kpiTests) {
            const card = page.locator(`[data-testid="${k.id}"]`);
            const isVisible = await card.isVisible();
            assert(isVisible, `KPI "${k.name}" visible`);
            if (isVisible) {
                const valText = await card.locator('.dsp-kpi-val').innerText();
                assert(valText.trim().length > 0, `KPI "${k.name}" tiene valor: ${valText.trim()}`);
            }
        }

        // 5. BARRA DE POLÍTICAS Y CONTROL DUAL
        console.log('\n4. Verificando salvaguardas y políticas retail...');
        const policyBar = page.locator('text=Control Dual & Políticas Retail:');
        assert(await policyBar.isVisible(), 'Barra de políticas y control seguro de inventario visible');

        // 6. NAVEGACIÓN BIDIRECCIONAL CON /loss-prevention
        console.log('\n5. Verificando conexión bidireccional /waste <-> /loss-prevention...');
        const lpLink = page.locator('a[href*="/loss-prevention"]').first();
        assert(await lpLink.isVisible(), 'Enlace a /loss-prevention presente en /waste');

        const lpResponse = await page.goto('http://localhost:8080/loss-prevention', { waitUntil: 'networkidle', timeout: 20000 });
        assert(lpResponse.status() === 200, 'HTTP Status 200 en /loss-prevention');

        const wasteLink = page.locator('a[href*="/waste"]:has-text("Cuadro de Mermas")');
        assert(await wasteLink.isVisible(), 'Enlace recíproco a Cuadro de Mermas (/waste) visible en /loss-prevention');

        // Volver a /waste para probar las pestañas de componentes
        await page.goto('http://localhost:8080/waste', { waitUntil: 'networkidle' });

        // 7. COMPONENTES PRINCIPALES (PESTAÑAS)
        console.log('\n6. Verificando pestañas operativas de los 9 componentes...');
        
        // Tab 1: Centro de Control
        assert(await page.locator('#tab-centro-control').isVisible(), 'Componente 1: Cuadro de Control activo');

        // Tab 2: Registro de Mermas
        await page.click('#tab-registro-link');
        await page.waitForTimeout(400);
        assert(await page.locator('#daily-registro-container').isVisible(), 'Componente 2: Registro de Mermas con formulario y fotos visible');

        // Tab 3: Clasificación
        await page.click('#tab-clasificacion-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-clasificacion').isVisible(), 'Componente 3: Clasificación Operativa / Comercial activa');

        // Tab 4: Prevención y Conteos Selectivos
        await page.click('#tab-conteos-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-conteos').isVisible(), 'Componente 4: Prevención y Conteos Selectivos activo');

        // Tab 5: Investigaciones
        await page.click('#tab-investigaciones-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-investigaciones').isVisible(), 'Componente 5: Investigaciones y Expedientes activo');

        // Tab 6: FEFO y Vencimientos
        await page.click('#tab-fefo-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-fefo').isVisible(), 'Componente 6: Control FEFO y Cadena de Frío activo');

        // Tab 7: Recuperación Financiera
        await page.click('#tab-recuperacion-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-recuperacion').isVisible(), 'Componente 8: Recuperación Financiera activo');

        // Tab 8: Causales / Pareto 80/20
        await page.click('#tab-causales-link');
        await page.waitForTimeout(600);
        assert(await page.locator('#tab-causales').isVisible(), 'Componente 9: Matriz de Causales y Pareto 80/20 activo');

        // 8. INTERACTIVIDAD DEL STEPPER
        console.log('\n7. Verificando interactividad de los pasos del stepper...');
        await page.click('.dsp-step-item:has-text("Registrar")');
        await page.waitForTimeout(400);
        assert(await page.locator('#tab-registro').isVisible(), 'Click en Paso 2 "Registrar" activa pestaña de Registro');

        await page.click('.dsp-step-item:has-text("Clasificar")');
        await page.waitForTimeout(400);
        assert(await page.locator('#tab-clasificacion').isVisible(), 'Click en Paso 3 "Clasificar" activa pestaña de Clasificación');

        // 9. RESPONSIVIDAD MÓVIL (390x844 - iPhone 12/13/14)
        console.log('\n8. Verificando visualización en móvil (390x844)...');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(300);
        const mobileStepperVisible = await page.locator('[data-testid="waste-flow-stepper"]').isVisible();
        const mobileKpisVisible = await page.locator('[data-testid="waste-eight-kpis"]').isVisible();
        assert(mobileStepperVisible, 'Stepper visible y con scroll horizontal en móvil');
        assert(mobileKpisVisible, 'Rejilla de 8 KPIs visible y adaptada en móvil');

        // Errores de consola
        assert(consoleErrors.length === 0, `0 errores críticos de consola JS (encontrados: ${consoleErrors.length})`);

    } catch (err) {
        console.error('Error durante la ejecución del test:', err);
        failed++;
    } finally {
        await browser.close();
    }

    console.log(`\n========================================`);
    console.log(`RESULTADO FINAL: ${passed} PASADAS, ${failed} FALLIDAS`);
    console.log(`========================================\n`);

    if (failed > 0) process.exit(1);
})();
