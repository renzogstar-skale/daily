const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: GESTIÓN DE DESPACHOS & ÚLTIMA MILLA (/dispatches & /dispatch) ===\n');
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

        // Capturar errores de consola no deseados
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404')) {
                consoleErrors.push(msg.text());
            }
        });

        // 1. CARGA DE /dispatches
        console.log('1. Verificando carga principal de /dispatches...');
        const responseDispatches = await page.goto('http://localhost:8080/dispatches', { waitUntil: 'networkidle', timeout: 30000 });
        assert(responseDispatches.status() === 200, 'HTTP Status 200 en /dispatches');

        // 2. CARGA DE /dispatch (alias funcional)
        console.log('2. Verificando alias /dispatch...');
        const responseDispatch = await page.goto('http://localhost:8080/dispatch', { waitUntil: 'networkidle', timeout: 30000 });
        assert(responseDispatch.status() === 200, 'HTTP Status 200 en /dispatch');

        // 3. TÍTULO Y CABECERA DEL MÓDULO
        const pageHeader = await page.locator('h1.dp-hero-title:has-text("Gestión de Despachos")').isVisible();
        assert(pageHeader, 'Cabecera "Gestión de Despachos" visible');

        // 4. FLUJO OPERATIVO (9 PASOS OBLIGATORIOS)
        console.log('\n3. Verificando Ciclo Operativo de Despacho (9 Fases Obligatorias)...');
        const stepper = page.locator('[data-testid="dispatch-lifecycle-stepper"]');
        assert(await stepper.isVisible(), 'Contenedor del stepper de ciclo de vida visible');

        const expectedPhases = [
            'Pedido',
            'Validación',
            'Reserva',
            'Picking',
            'Packing',
            'Verificación',
            'Despacho',
            'Entrega',
            'Conciliación'
        ];

        for (const phase of expectedPhases) {
            const hasPhase = await stepper.locator(`.dp-lifecycle-step strong:has-text("${phase}")`).isVisible();
            assert(hasPhase, `Fase: "${phase}" visible y rotulada en el stepper`);
        }

        // 5. LOS 8 INDICADORES PRINCIPALES CONSOLIDADOS
        console.log('\n4. Verificando los 8 Indicadores Clave de Despachos...');
        const kpisGrid = page.locator('[data-testid="dispatch-eight-kpis"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 8 KPIs visible');

        const kpiTests = [
            { id: 'kpi-pedidos-pendientes', name: 'Pedidos pendientes', minValLen: 1 },
            { id: 'kpi-pedidos-despachados', name: 'Pedidos despachados', minValLen: 1 },
            { id: 'kpi-entregas-atrasadas', name: 'Entregas atrasadas', minValLen: 1 },
            { id: 'kpi-otif', name: 'OTIF (On-Time In-Full)', minValLen: 1 },
            { id: 'kpi-exactitud-picking', name: 'Exactitud de picking', minValLen: 1 },
            { id: 'kpi-tiempo-preparacion', name: 'Tiempo de preparación', minValLen: 1 },
            { id: 'kpi-incidencias', name: 'Incidencias', minValLen: 1 },
            { id: 'kpi-productividad', name: 'Productividad', minValLen: 1 }
        ];

        for (const k of kpiTests) {
            const card = page.locator(`[data-testid="${k.id}"]`);
            const isVisible = await card.isVisible();
            assert(isVisible, `KPI "${k.name}" visible`);
            if (isVisible) {
                const valText = await card.locator('.dp-kpi-val').innerText();
                assert(valText.trim().length >= k.minValLen, `KPI "${k.name}" tiene valor: ${valText.trim()}`);
            }
        }

        // 6. LOS 7 COMPONENTES OPERATIVOS (PESTAÑAS)
        console.log('\n5. Verificando navegación interactiva en las 7 pestañas...');
        const tabNav = page.locator('[data-testid="dispatch-main-tabs"]');
        assert(await tabNav.isVisible(), 'Barra de pestañas principales visible');

        const tabs = [
            { id: '#tab-seguimiento-link', pane: '#tab-despachos-seguimiento', label: '1. Despachos y Seguimiento' },
            { id: '#tab-pedidos-link', pane: '#tab-pedidos-prioridades', label: '2. Pedidos y Prioridades' },
            { id: '#tab-picking-link', pane: '#tab-preparacion-picking', label: '3. Preparación y Picking' },
            { id: '#tab-transporte-link', pane: '#tab-programacion-rutas', label: '4. Programación y Transporte' },
            { id: '#tab-salidas-link', pane: '#tab-salidas-documentacion', label: '5. Salidas y Carga' },
            { id: '#tab-entregas-link', pane: '#tab-entregas-evidencias', label: '6. Entregas y Evidencias' },
            { id: '#tab-conciliacion-link', pane: '#tab-conciliacion-variaciones', label: '7. Conciliación y Variaciones' }
        ];

        for (const t of tabs) {
            const tabBtn = page.locator(t.id);
            assert(await tabBtn.isVisible(), `Pestaña "${t.label}" visible en navegación`);
            await tabBtn.click();
            await page.waitForTimeout(300);
            const paneVisible = await page.locator(t.pane).isVisible();
            assert(paneVisible, `Panel correspondiente "${t.pane}" se activa al hacer clic`);
        }

        // 7. VERIFICACIONES ESPECÍFICAS POR COMPONENTE
        console.log('\n6. Verificando funcionalidades específicas de cada componente...');

        // Tab 2: Pedidos & Prioridades (Canales POS, Web, B2B y Prioridades Urgente/Alta/Normal)
        await page.locator('#tab-pedidos-link').click();
        await page.waitForTimeout(200);
        const orderTable = page.locator('#tab-pedidos-prioridades table');
        assert(await orderTable.isVisible(), 'Tabla de pedidos y prioridades visible');
        const rowsCount = await orderTable.locator('tbody tr').count();
        assert(rowsCount > 0, `Tabla de pedidos contiene ${rowsCount} pedidos registrados`);

        // Tab 4: Programación de Rutas (Flota de transporte con capacidad kg/volumen)
        await page.locator('#tab-transporte-link').click();
        await page.waitForTimeout(200);
        const fleetTitle = page.locator('h6:has-text("Unidades de Transporte Activas")');
        assert(await fleetTitle.isVisible(), 'Catálogo de flota vehicular de transporte visible con capacidades');

        // Tab 5: Salidas & Documentación (Staging y Control de Seguridad en Muelle)
        await page.locator('#tab-salidas-link').click();
        await page.waitForTimeout(200);
        const stagingChecklist = page.locator('text=Control de Seguridad en Muelle:');
        assert(await stagingChecklist.isVisible(), 'Control de seguridad en muelle y salida de carga visible');

        // Tab 6: Entregas & Evidencias (Firma, PIN, QR y entregas parciales/rechazos)
        await page.locator('#tab-entregas-link').click();
        await page.waitForTimeout(200);
        const deliveryRows = page.locator('#tab-entregas-evidencias tbody tr');
        const deliveryCount = await deliveryRows.count();
        assert(deliveryCount > 0, `Se visualizan ${deliveryCount} registros de control de entrega y evidencias`);

        // Tab 7: Conciliación & Variaciones (Matriz de 7 cantidades obligatorias)
        await page.locator('#tab-conciliacion-link').click();
        await page.waitForTimeout(200);
        const varianceHeaders = [
            '1. Solicitada',
            '2. Preparada',
            '3. Escaneada',
            '4. Despachada',
            '5. Recibida',
            '6. Devuelta',
            '7. Dif. Final'
        ];
        for (const vh of varianceHeaders) {
            const hasCol = await page.locator(`th:has-text("${vh}")`).first().isVisible();
            assert(hasCol, `Columna de conciliación "${vh}" presente en matriz`);
        }

        // Salvaguarda: Política de NO doble contabilización en kardex
        const policyNoDouble = await page.locator('text=Principio de No Doble Contabilización:').first().isVisible();
        assert(policyNoDouble, 'Salvaguarda de no doble contabilización visible en conciliación');

        // 8. VERIFICACIÓN DE MODALES OPERATIVOS
        console.log('\n7. Verificando modales de acción en el DOM...');
        assert(await page.locator('#modalScheduleRoute').count() > 0, 'Modal de Programación de Ruta existe en DOM');
        assert(await page.locator('#modalAssignPicking').count() > 0, 'Modal de Asignación de Picking existe en DOM');
        assert(await page.locator('#modalConfirmExit').count() > 0, 'Modal de Confirmación de Salida existe en DOM');

        // 9. RESPONSIVIDAD MOBILE (390x844)
        console.log('\n8. Verificando adaptación responsiva en mobile (390x844)...');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(500);

        const mobileHeader = await page.locator('h1.dp-hero-title:has-text("Gestión de Despachos")').isVisible();
        assert(mobileHeader, 'Cabecera visible en resolución móvil 390x844');

        const mobileKpis = await page.locator('[data-testid="dispatch-eight-kpis"]').isVisible();
        assert(mobileKpis, 'Grid de KPIs visible y adaptado en resolución móvil');

        // 10. REVISIÓN DE ERRORES DE CONSOLA
        console.log('\n9. Revisando logs y errores de consola...');
        if (consoleErrors.length > 0) {
            console.error('  [WARN] Errores de consola detectados:', consoleErrors);
        } else {
            console.log('  [PASS] Cero errores críticos de JavaScript en consola');
            passed++;
        }

    } catch (err) {
        console.error('Error durante la ejecución del test E2E:', err);
        failed++;
    } finally {
        await browser.close();
        console.log(`\n========================================`);
        console.log(`TOTAL PRUEBAS: ${passed + failed} | EXITOSAS: ${passed} | FALLIDAS: ${failed}`);
        console.log(`========================================\n`);
        if (failed > 0) {
            process.exit(1);
        } else {
            process.exit(0);
        }
    }
})();
