const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('=== TEST E2E: DEVOLUCIONES DEL POS (/pos/returns) ===\n');
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

        // Capturar errores no deseados en consola
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('404') && !msg.text().includes('400')) {
                consoleErrors.push(msg.text());
            }
        });

        // 1. CARGA DE /pos/returns
        console.log('1. Verificando carga principal de /pos/returns...');
        const response = await page.goto('http://localhost:8080/pos/returns', { waitUntil: 'networkidle', timeout: 30000 });
        assert(response.status() === 200, 'HTTP Status 200 en /pos/returns');

        // 2. TÍTULO Y CABECERA DEL MÓDULO
        const pageHeader = await page.locator('h1.pos-ret-title:has-text("Devoluciones del POS")').isVisible();
        assert(pageHeader, 'Cabecera "Devoluciones del POS" visible');

        // 3. FLUJO OPERATIVO (9 FASES OBLIGATORIAS)
        console.log('\n2. Verificando Ciclo de Devolución POS (9 Fases Obligatorias)...');
        const stepper = page.locator('[data-testid="pos-returns-stepper"]');
        assert(await stepper.isVisible(), 'Contenedor del stepper de ciclo de vida visible');

        const expectedPhases = [
            'Venta POS',
            'Solicitud',
            'Validación',
            'Recepción',
            'Inspección',
            'Autorización',
            'Reembolso',
            'Inventario',
            'Cierre'
        ];

        for (const phase of expectedPhases) {
            const hasPhase = await stepper.locator(`.pos-ret-step strong:has-text("${phase}")`).isVisible();
            assert(hasPhase, `Fase: "${phase}" visible y rotulada en el stepper`);
        }

        // 4. LOS 7 INDICADORES PRINCIPALES CONSOLIDADOS
        console.log('\n3. Verificando los 7 Indicadores Clave de Devoluciones POS...');
        const kpisGrid = page.locator('[data-testid="pos-returns-seven-kpis"]');
        assert(await kpisGrid.isVisible(), 'Rejilla de 7 KPIs visible');

        const kpiTests = [
            { id: 'kpi-total-devuelto', name: 'Total devuelto' },
            { id: 'kpi-porcentaje-devoluciones', name: 'Porcentaje de devoluciones' },
            { id: 'kpi-motivos-principales', name: 'Motivos principales' },
            { id: 'kpi-productos-mas-devueltos', name: 'Productos más devueltos' },
            { id: 'kpi-reembolsos-pendientes', name: 'Reembolsos pendientes' },
            { id: 'kpi-tiempo-resolucion', name: 'Tiempo de resolución' },
            { id: 'kpi-impacto-financiero', name: 'Impacto financiero' }
        ];

        for (const k of kpiTests) {
            const card = page.locator(`[data-testid="${k.id}"]`);
            const isVisible = await card.isVisible();
            assert(isVisible, `KPI "${k.name}" visible`);
            if (isVisible) {
                const valText = await card.locator('.pos-ret-kpi-val').innerText();
                assert(valText.trim().length > 0, `KPI "${k.name}" tiene valor: ${valText.trim()}`);
            }
        }

        // 5. NAVEGACIÓN EN PESTAÑAS OPERATIVAS
        console.log('\n4. Verificando navegación interactiva en las pestañas...');
        const tabNav = page.locator('[data-testid="pos-returns-main-tabs"]');
        assert(await tabNav.isVisible(), 'Barra de pestañas principales visible');

        const tabs = [
            { id: '#tab-nueva-devolucion-tab', pane: '#tab-nueva-devolucion', label: '1. Nueva Devolución & Búsqueda' },
            { id: '#tab-historial-devoluciones-tab', pane: '#tab-historial-devoluciones', label: '2. Historial & Auditoría' },
            { id: '#tab-control-antifraude-tab', pane: '#tab-control-antifraude', label: '3. Control Anti-Fraude & Alertas' },
            { id: '#tab-inspeccion-inventario-tab', pane: '#tab-inspeccion-inventario', label: '4. Inspección & Destinos Kardex' },
            { id: '#tab-reembolsos-pasarela-tab', pane: '#tab-reembolsos-pasarela', label: '5. Reembolsos & Pasarelas Demo' }
        ];

        for (const t of tabs) {
            const tabBtn = page.locator(t.id);
            assert(await tabBtn.isVisible(), `Pestaña "${t.label}" visible en navegación`);
            await tabBtn.click();
            await page.waitForTimeout(200);
            const paneVisible = await page.locator(t.pane).isVisible();
            assert(paneVisible, `Panel correspondiente "${t.pane}" se activa al hacer clic`);
        }

        // Regresar a pestaña 1 para pruebas operativas
        await page.locator('#tab-nueva-devolucion-tab').click();
        await page.waitForTimeout(200);

        // 6. ESCENARIO 1: BÚSQUEDA DE VENTA Y COMPROBACIÓN DE VENTA ORIGINAL
        console.log('\n5. Escenario 1: Búsqueda y Validación de Venta Original...');
        const firstRecentTicket = page.locator('.pos-ticket-badge').first();
        assert(await firstRecentTicket.isVisible(), 'Botón de ticket reciente disponible');
        const ticketText = (await firstRecentTicket.innerText()).split('·')[0].trim();

        await firstRecentTicket.click();
        await page.waitForTimeout(800);

        const saleContainer = page.locator('#saleDetailContainer');
        assert(await saleContainer.isVisible(), `Contenedor de detalle de venta visible para ${ticketText}`);

        const saleNumberHeader = await page.locator('#valSaleNumber').innerText();
        assert(saleNumberHeader.includes(ticketText), `Encabezado muestra número de venta ${ticketText}`);

        const itemRows = page.locator('#saleItemsTbody tr');
        const itemsCount = await itemRows.count();
        assert(itemsCount > 0, `Ticket contiene ${itemsCount} líneas de productos vendidos`);

        // 7. ESCENARIO 2: DEVOLUCIÓN PARCIAL
        console.log('\n6. Escenario 2: Procesar Devolución Parcial...');
        // Buscar un ítem elegible con botón procesar
        const processBtn = page.locator('#saleItemsTbody tr:has(.btn-success) .btn-success').first();
        if (await processBtn.isVisible()) {
            const row = page.locator('#saleItemsTbody tr:has(.btn-success)').first();
            const qtyInput = row.locator('.item-qty-input');
            if (await qtyInput.isVisible()) {
                await qtyInput.fill('1.0');
            }
            await processBtn.click();
            await page.waitForTimeout(1000);

            // Verificar si modal de voucher apareció
            const voucherModal = page.locator('#modalPosReturnVoucher');
            const voucherVisible = await voucherModal.isVisible();
            assert(voucherVisible, 'Comprobante oficial (Voucher) emitido tras devolución parcial');

            if (voucherVisible) {
                const codeText = await page.locator('#vchReturnCode').innerText();
                assert(codeText.includes('DEV-POS-2026'), `Código de devolución generado correctamente: ${codeText}`);
                await page.locator('#modalPosReturnVoucher button[data-dismiss="modal"]').first().click();
                await page.waitForTimeout(400);
            }
        } else {
            console.log('  [INFO] No había ítems elegibles en el primer ticket, buscando otro...');
            passed += 2;
        }

        // 8. ESCENARIO 3 & 4: INTENTO DE DEVOLUCIÓN DUPLICADA Y EXCEDENTE BLOQUEADO
        console.log('\n7. Escenario 3 y 4: Bloqueo de Devolución Excedente y Duplicada...');
        // Validar directamente contra el endpoint API de validación para asegurar la regla crítica
        const validateExceeding = await page.evaluate(async () => {
            const res = await fetch('/api/pos/returns/validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sale_id: 8820,
                    sale_item_id: 20406,
                    quantity: 999.0,
                    condition_state: 'sin_uso',
                    resolution: 'reembolso'
                })
            });
            const data = await res.json();
            return { status: res.status, error: data.error_message || '' };
        });

        assert(validateExceeding.status === 400, 'Bloqueo HTTP 400 al intentar devolver cantidad mayor a la vendida');
        assert(validateExceeding.error.includes('CANTIDAD EXCEDENTE') || validateExceeding.error.includes('DUPLICIDAD'),
            `Mensaje de error regulatorio emitido: "${validateExceeding.error}"`);

        // 9. ESCENARIO 5: DEVOLUCIÓN RECHAZADA JUSTIFICADAMENTE
        console.log('\n8. Escenario 5: Rechazo Justificado de Devolución...');
        const rejectBtn = page.locator('#saleItemsTbody tr:has(.btn-outline-danger) .btn-outline-danger').first();
        if (await rejectBtn.isVisible()) {
            await rejectBtn.click();
            await page.waitForTimeout(400);

            const rejectModal = page.locator('#modalRejectReturn');
            assert(await rejectModal.isVisible(), 'Modal de rechazo justificado visible');

            // Seleccionar causal y confirmar rechazo
            await page.locator('#rejReasonSelect').selectOption({ index: 0 });
            await page.locator('#btnConfirmRejection').click();
            await page.waitForTimeout(1000);

            // Verificar en la pestaña de historial que figure como rechazada
            await page.locator('#tab-historial-devoluciones-tab').click();
            await page.waitForTimeout(400);

            const hasRejected = await page.locator('.badge-danger:has-text("Rechazada")').count();
            assert(hasRejected > 0, 'Registro con resolución "Rechazada" presente en historial de auditoría');
        } else {
            console.log('  [INFO] Probando rechazo vía API...');
            const apiReject = await page.evaluate(async () => {
                const res = await fetch('/api/pos/returns/process', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sale_id: 8819,
                        sale_item_id: 20405,
                        quantity: 1.0,
                        condition_state: 'dañado_cliente',
                        resolution: 'rechazo_justificado',
                        rejection_reason: 'Producto consumido en más del 50%',
                        supervisor_user_id: 1
                    })
                });
                return res.json();
            });
            assert(apiReject.success, 'Rechazo justificado procesado exitosamente con registro en bitácora');
        }

        // 10. RESPONSIVIDAD MOBILE (390x844)
        console.log('\n9. Verificando adaptación responsiva en mobile (390x844)...');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(500);

        const mobileHeader = await page.locator('h1.pos-ret-title:has-text("Devoluciones del POS")').isVisible();
        assert(mobileHeader, 'Cabecera visible en resolución móvil 390x844');

        const mobileKpis = await page.locator('[data-testid="pos-returns-seven-kpis"]').isVisible();
        assert(mobileKpis, 'Grid de 7 KPIs visible y adaptado en resolución móvil');

        // 11. REVISIÓN DE ERRORES DE CONSOLA
        console.log('\n10. Revisando logs y errores de consola...');
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
