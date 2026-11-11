const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: GESTIÓN DE DESPACHOS OMS/WMS (/dispatches & /dispatch) ===\n');
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
        const pageErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404')) {
                consoleErrors.push(msg.text());
            }
        });
        page.on('pageerror', err => pageErrors.push(err.message));

        // 1. Carga de /dispatches y /dispatch
        console.log('1. Verificando carga principal de /dispatches y /dispatch...');
        const responseDispatches = await page.goto('http://localhost:8080/dispatches', { waitUntil: 'networkidle', timeout: 30000 });
        assert(responseDispatches.status() === 200, 'HTTP Status 200 en /dispatches');

        const responseDispatch = await page.goto('http://localhost:8080/dispatch', { waitUntil: 'networkidle', timeout: 30000 });
        assert(responseDispatch.status() === 200, 'HTTP Status 200 en /dispatch');

        // 2. Cabecera y ausencia de accesos fuera de alcance (/returns, /kardex en tarjeta hero/inferior)
        const pageHeader = await page.locator('h1.dp-hero-title:has-text("Gestión de Despachos")').isVisible();
        assert(pageHeader, 'Cabecera "Gestión de Despachos" visible');
        assert(await page.locator('.dp-hero-actions a[href*="/returns"], .dp-bottom-grid a[href*="/returns"], .dp-bottom-grid a[href*="/kardex"]').count() === 0, 'Sin enlaces fuera de alcance (/returns, /kardex) dentro del workspace de despachos');

        // 3. Ciclo canónico de 7 etapas en Stepper superior y detalle
        console.log('\n2. Verificando Ciclo Canónico de 7 Etapas (Pedido -> Reserva -> Picking -> Packing -> Despacho -> Entrega -> Conciliación)...');
        const stepper = page.locator('[data-testid="dispatch-lifecycle-stepper"]');
        assert(await stepper.isVisible(), 'Contenedor del stepper de ciclo de vida visible');

        const canonicalStages = ['Pedido', 'Reserva', 'Picking', 'Packing', 'Despacho', 'Entrega', 'Conciliación'];
        assert(await stepper.locator('.dp-lifecycle-step').count() === 7, 'Exactamente 7 etapas en el ciclo superior');
        for (const stage of canonicalStages) {
            const hasStage = await stepper.locator(`.dp-lifecycle-step strong:has-text("${stage}")`).isVisible();
            assert(hasStage, `Etapa "${stage}" visible en el stepper superior`);
        }
        assert(await page.locator('#dpStepperTrack .dp-stepper-step').count() === 7, 'Detalle del pedido sincronizado con exactamente 7 etapas');

        // 4. Los 8 KPIs reales
        console.log('\n3. Verificando los 8 Indicadores Reales de Despachos...');
        const kpisGrid = page.locator('[data-testid="dispatch-eight-kpis"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 8 KPIs visible');

        const kpiIds = [
            'kpi-pedidos-pendientes',
            'kpi-pedidos-despachados',
            'kpi-entregas-atrasadas',
            'kpi-otif',
            'kpi-exactitud-picking',
            'kpi-tiempo-preparacion',
            'kpi-incidencias',
            'kpi-productividad'
        ];
        for (const kid of kpiIds) {
            const card = page.locator(`[data-testid="${kid}"]`);
            const vis = await card.isVisible();
            assert(vis, `KPI "${kid}" visible`);
            if (vis) {
                const txt = (await card.locator('.dp-kpi-val').innerText()).trim();
                assert(txt.length >= 1, `KPI "${kid}" tiene valor real: ${txt}`);
            }
        }

        // 5. Cambio reactivo entre pedidos & toggle 7 columnas
        console.log('\n4. Verificando selección reactiva de pedidos y etiqueta imprimible...');
        const cardsCount = await page.locator('.dp-order-card').count();
        assert(cardsCount > 1, `Lista izquierda con ${cardsCount} pedidos`);
        if (cardsCount > 1) {
            const secondCard = page.locator('.dp-order-card').nth(1);
            await secondCard.click();
            await page.waitForTimeout(200);
            assert(await secondCard.evaluate(el => el.classList.contains('is-active')), 'Cambio reactivo al segundo pedido funciona');
        }

        await page.locator('label[for="toggle7Stages"]').click();
        await page.waitForTimeout(150);
        assert(await page.locator('#dpReconcileTable th.dp-col-7stage').first().isVisible(), 'Toggle "Ver 7 etapas" muestra columnas Devuelta y Dif. Final');

        // 6. Probar apertura de etiqueta de despacho real vía UI
        const labelBtn = page.locator('.dp-detail-actions button:has-text("Etiqueta")').first();
        if (await labelBtn.isVisible()) {
            await labelBtn.click();
            await page.waitForTimeout(400);
            assert(await page.locator('#modalDispatchLabel').isVisible(), 'Modal de Etiqueta de Despacho se abre con datos reales');
            const tagCode = (await page.locator('#lblTagCode').innerText()).trim();
            assert(tagCode.startsWith('ETQ-'), `Código de etiqueta generado: ${tagCode}`);
            await page.locator('#modalDispatchLabel button[data-dismiss="modal"]').first().click();
            await page.waitForTimeout(250);
        }

        // 7. Navegación por las 7 pestañas y contenido real
        console.log('\n5. Verificando navegación interactiva en las 7 pestañas...');
        const tabs = [
            { id: '#tab-seguimiento-link', pane: '#tab-despachos-seguimiento' },
            { id: '#tab-pedidos-link', pane: '#tab-pedidos-prioridades' },
            { id: '#tab-picking-link', pane: '#tab-preparacion-picking' },
            { id: '#tab-transporte-link', pane: '#tab-programacion-rutas' },
            { id: '#tab-salidas-link', pane: '#tab-salidas-documentacion' },
            { id: '#tab-entregas-link', pane: '#tab-entregas-evidencias' },
            { id: '#tab-conciliacion-link', pane: '#tab-conciliacion-variaciones' }
        ];
        for (const t of tabs) {
            await page.locator(t.id).click();
            await page.waitForTimeout(200);
            assert(await page.locator(t.pane).isVisible(), `Pestaña ${t.id} activa su panel`);
        }

        await page.locator('#tab-transporte-link').click();
        await page.waitForTimeout(200);
        assert(await page.locator('h6:has-text("Unidades de Transporte Activas")').isVisible(), 'Catálogo de flota visible en Transporte');

        await page.locator('#tab-salidas-link').click();
        await page.waitForTimeout(200);
        assert(await page.locator('text=Control de Salida en Muelle:').isVisible(), 'Control de salida en muelle visible');

        await page.locator('#tab-conciliacion-link').click();
        await page.waitForTimeout(200);
        assert(await page.locator('text=Regla de Cierre Logístico Obligatorio:').isVisible(), 'Regla de cierre obligatorio con POD visible en Conciliación');
        assert(await page.locator('h6:has-text("Registro de Incidencias Logísticas")').isVisible(), 'Tabla de Incidencias Logísticas visible en Tab 7');

        // 8. Modales en DOM
        console.log('\n6. Verificando modales en DOM...');
        for (const m of ['#modalNewOrder', '#modalPackOrder', '#modalDispatchLabel', '#modalScheduleRoute', '#modalConfirmExit', '#modalFastScanVerify', '#modalRecordDelivery', '#modalVerifyPickup', '#modalReportIncident', '#modalResolveVariance']) {
            assert(await page.locator(m).count() === 1, `Modal ${m} presente en DOM`);
        }

        // 9. Dark mode toggle y Crystal Light coherencia
        console.log('\n7. Verificando tema oscuro/claro y responsividad móvil/PDA (390x844)...');
        await page.evaluate(() => {
            document.documentElement.classList.add('daily-dark');
        });
        await page.waitForTimeout(150);
        const darkBg = await page.evaluate(() => getComputedStyle(document.querySelector('.dp-page-container')).backgroundColor);
        assert(darkBg && darkBg !== 'rgba(0, 0, 0, 0)', `Estilos dark mode aplicados en contenedor (${darkBg})`);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(350);
        assert(await page.locator('h1.dp-hero-title:has-text("Gestión de Despachos")').isVisible(), 'Cabecera visible en móvil 390x844');
        assert(await page.locator('[data-testid="dispatch-eight-kpis"]').isVisible(), 'KPIs visibles en móvil 390x844');

        // 10. Errores de consola y JS
        console.log('\n8. Revisando consola y errores JS...');
        assert(consoleErrors.length === 0 && pageErrors.length === 0, `Cero errores de consola/página (console: ${consoleErrors.length}, page: ${pageErrors.length})`);
        if (consoleErrors.length > 0) console.error('Console errors:', consoleErrors);
        if (pageErrors.length > 0) console.error('Page errors:', pageErrors);

    } catch (err) {
        console.error('Error durante la ejecución del test E2E:', err);
        failed++;
    } finally {
        await browser.close();
        console.log(`\n========================================`);
        console.log(`TOTAL PRUEBAS: ${passed + failed} | EXITOSAS: ${passed} | FALLIDAS: ${failed}`);
        console.log(`========================================\n`);
        process.exit(failed > 0 ? 1 : 0);
    }
})();
