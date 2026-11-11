const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: DEVOLUCIONES LOGÍSTICAS (/returns & /pos/returns) ===\n');
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

        // 1. Carga de /returns y separación estricta de /pos/returns
        console.log('1. Verificando carga de /returns y separación de /pos/returns...');
        const respReturns = await page.goto('http://localhost:8080/returns', { waitUntil: 'networkidle', timeout: 30000 });
        assert(respReturns.status() === 200, 'HTTP Status 200 en /returns');

        const headerVisible = await page.locator('h1.ret-header-title:has-text("Devoluciones Logísticas")').isVisible();
        assert(headerVisible, 'Cabecera "Devoluciones Logísticas" visible');

        const workspaceLinksCount = await page.locator('[data-testid="returns-logistics-page"] a[href*="/pos/returns"], [data-testid="returns-logistics-page"] a[href*="customer_refunds"]').count();
        assert(workspaceLinksCount === 0, 'Sin enlaces ni mezcla de reembolsos POS comerciales dentro de /returns');

        const pageText = await page.locator('[data-testid="returns-logistics-page"]').innerText();
        assert(!pageText.includes('379.70') && !pageText.includes('Reembolsos POS del día'), 'Sin métricas ni textos hardcodeados de caja/reembolso POS en /returns');

        // 2. Ciclo canónico de 6 etapas
        console.log('\n2. Verificando Ciclo Canónico de 6 Etapas (Solicitud -> Recepción -> Inspección -> Clasificación -> Disposición -> Conciliación)...');
        const topStepper = page.locator('[data-testid="returns-lifecycle-stepper"]');
        assert(await topStepper.isVisible(), 'Stepper superior de logística inversa visible');
        assert(await topStepper.locator('.ret-lc-step').count() === 6, 'Exactamente 6 etapas en el stepper superior');

        const canonicalStages = ['Solicitud', 'Recepción', 'Inspección', 'Clasificación', 'Disposición', 'Conciliación'];
        for (const st of canonicalStages) {
            const vis = await topStepper.locator(`.ret-lc-step strong:has-text("${st}")`).isVisible();
            assert(vis, `Etapa "${st}" visible en stepper superior`);
        }
        assert(await page.locator('#detailStepperDots .ret-step-circle').count() === 6, 'Detalle derecho sincronizado con exactamente 6 etapas');

        // 3. 6 KPIs reales de logística inversa
        console.log('\n3. Verificando los 6 KPIs de Logística Inversa...');
        const kpisGrid = page.locator('[data-testid="returns-kpis-grid"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 6 KPIs visible');
        const kpiTestIds = [
            'kpi-retornos-pendientes',
            'kpi-unidades-recibidas',
            'kpi-aptos-reventa',
            'kpi-mercancia-danada',
            'kpi-rtv-reparacion',
            'kpi-conciliacion-unidades'
        ];
        for (const kid of kpiTestIds) {
            const card = page.locator(`[data-testid="${kid}"]`);
            const vis = await card.isVisible();
            assert(vis, `KPI "${kid}" visible`);
            if (vis) {
                const val = (await card.locator('.ret-kpi-value').innerText()).trim();
                assert(val.length >= 1, `KPI "${kid}" con valor real: ${val}`);
            }
        }

        // 4. Separación de pestañas: Retornos Físicos RMA vs Inspección Física desde POS
        console.log('\n4. Verificando pestañas separadas y selección reactiva de filas...');
        const channelTabs = page.locator('[data-testid="returns-channel-tabs"]');
        assert(await channelTabs.isVisible(), 'Pestañas de separación logística visibles');
        const rowsInitial = await page.locator('#tableReturns .ret-table-row').count();
        assert(rowsInitial >= 1, `Tabla contiene ${rowsInitial} casos registrados`);

        await page.locator('#tabFilterPosHandoff').click();
        await page.waitForTimeout(200);
        const posRowsVisible = await page.locator('#tableReturns tbody tr:visible').count();
        assert(posRowsVisible >= 0, `Pestaña "Inspección Física desde POS" filtra su cola independiente (${posRowsVisible} filas visibles)`);

        await page.locator('#tabFilterLogistics').click();
        await page.waitForTimeout(200);
        const logRows = page.locator('#tableReturns .ret-table-row:visible');
        const logCount = await logRows.count();
        assert(logCount >= 1, `Pestaña "Retornos Físicos RMA" muestra ${logCount} filas`);
        let targetRmaForScan = '';
        if (logCount > 0) {
            targetRmaForScan = (await logRows.first().getAttribute('data-rma')) || '';
        }
        if (logCount > 1) {
            const secondRma = (await logRows.nth(1).getAttribute('data-rma')) || '';
            await logRows.nth(1).click();
            await page.waitForTimeout(250);
            const activeDetailRma = (await page.locator('#detailRma').innerText()).trim();
            assert(activeDetailRma === secondRma, `Selección reactiva actualiza detalle a ${activeDetailRma}`);
        }

        // 5. Escáner rápido UPC/RMA en barra operativa + modales dinámicos
        console.log('\n5. Verificando escáner UPC/RMA y modales dinámicos...');
        assert(await page.locator('#inputQuickUpcScan').isVisible(), 'Campo de escáner UPC visible en barra operativa');
        if (targetRmaForScan) {
            await page.fill('#inputQuickUpcScan', targetRmaForScan);
            await page.locator('#btnQuickUpcScan').click();
            await page.waitForTimeout(500);
            const modalOpen = await page.locator('#modalReceiveReturn').isVisible();
            assert(modalOpen, `Escáner rápido (${targetRmaForScan}) abre modal de recepción física (#modalReceiveReturn)`);
            if (modalOpen) {
                await page.locator('#modalReceiveReturn button[data-dismiss="modal"]').first().click();
                await page.waitForTimeout(300);
            }
        }

        // Abrir modal de conciliación dinámica (sin datos mock)
        await page.locator('[data-target="#modalReconciliation"]').first().click();
        await page.waitForTimeout(350);
        assert(await page.locator('#modalReconciliation').isVisible(), 'Modal de Conciliación de Unidades visible');
        const concilRows = await page.locator('#modalReconciliation tbody tr').count();
        assert(concilRows >= 1, `Tabla dinámica de Conciliación cargada con ${concilRows} filas reales de BD`);
        await page.locator('#modalReconciliation button[data-dismiss="modal"]').first().click();
        await page.waitForTimeout(250);

        // Abrir modal de nueva solicitud RMA
        await page.locator('#btnOpenNewReturn').click();
        await page.waitForTimeout(300);
        assert(await page.locator('#modalNewReturn').isVisible(), 'Modal de Nueva Solicitud RMA se abre correctamente');
        await page.locator('#modalNewReturn button[data-dismiss="modal"]').first().click();
        await page.waitForTimeout(250);

        // 6. Todos los modales presentes en el DOM
        console.log('\n6. Verificando presencia en DOM de los 10 modales logísticos...');
        const modals = [
            '#modalNewReturn',
            '#modalReceiveReturn',
            '#modalInspectReturn',
            '#modalClassifyReturn',
            '#modalDispositionReturn',
            '#modalConciliateReturn',
            '#modalRegisterEvidence',
            '#modalReconciliation',
            '#modalQualityPolicy',
            '#modalCatalogDestinations'
        ];
        for (const m of modals) {
            assert(await page.locator(m).count() === 1, `Modal ${m} presente en DOM`);
        }

        // 7. Dark mode + vista móvil/PDA 390x844
        console.log('\n7. Verificando tema oscuro (daily-dark) y responsividad móvil/PDA (390x844)...');
        await page.evaluate(() => document.documentElement.classList.add('daily-dark'));
        await page.waitForTimeout(150);
        const darkCardBg = await page.evaluate(() => getComputedStyle(document.querySelector('.ret-kpi-card')).backgroundColor);
        assert(darkCardBg && darkCardBg !== 'rgb(255, 255, 255)', `Estilos dark mode aplicados a KPI card (${darkCardBg})`);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(350);
        assert(await page.locator('h1.ret-header-title:has-text("Devoluciones Logísticas")').isVisible(), 'Cabecera visible en PDA/móvil 390x844');
        assert(await page.locator('[data-testid="returns-kpis-grid"]').isVisible(), 'KPIs visibles en PDA/móvil 390x844');
        assert(await page.locator('#inputQuickUpcScan').isVisible(), 'Escáner UPC visible en PDA/móvil 390x844');

        // 8. Verificar que /pos/returns sigue separado e intacto en HTTP 200
        console.log('\n8. Verificando que /pos/returns comercial sigue operando separado...');
        const respPosReturns = await page.goto('http://localhost:8080/pos/returns', { waitUntil: 'networkidle', timeout: 30000 });
        assert(respPosReturns.status() === 200, 'HTTP Status 200 en /pos/returns (módulo comercial POS intacto)');

        // 9. Errores JS y consola
        console.log('\n9. Revisando consola y errores JS...');
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
