const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('===============================================================');
    console.log('=== TEST E2E: REDISEÑO PROFESIONAL UI/UX DAILY MOBILE 2026 ===');
    console.log('===============================================================\n');

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
        const page = await browser.newPage({ 
            viewport: { width: 390, height: 844 },
            deviceScaleFactor: 3,
            isMobile: true,
            hasTouch: true
        });

        // 1. Cargar DAILY MOBILE
        console.log('--- 1. CARGA DE DAILY MOBILE (/m) ---');
        page.on('console', msg => console.log('  [BROWSER CONSOLE]', msg.text()));
        page.on('pageerror', err => console.error('  [BROWSER ERROR]', err.message));

        const res = await page.goto('http://localhost:8080/m', { waitUntil: 'load', timeout: 30000 });
        assert(res.status() === 200, 'DAILY Mobile responde HTTP 200');

        // Esperar a que desaparezca el skeleton y se renderice la vista
        const greetingTitle = page.locator('.m-greeting-title');
        await greetingTitle.waitFor({ state: 'visible', timeout: 15000 });

        // 2. Encabezado superior
        console.log('\n--- 2. ENCABEZADO SUPERIOR Y BRANDING ---');
        const logo = page.locator('.m-brand-logo');
        assert(await logo.isVisible(), 'Logo DAILY con símbolo verde visible en el encabezado');

        const bellBtn = page.locator('#m-notif-btn');
        assert(await bellBtn.isVisible(), 'Icono de notificaciones presente');

        const notifBadge = page.locator('#m-notif-badge');
        assert(await notifBadge.isVisible(), 'Contador de notificaciones con badge rojo presente');
        const badgeVal = parseInt(await notifBadge.innerText(), 10);
        assert(badgeVal >= 1, `Badge de notificaciones numérico presente (${badgeVal})`);

        const optsBtn = page.locator('#m-opts-btn');
        assert(await optsBtn.isVisible(), 'Acceso a perfil y opciones (tres puntos) presente');

        // 3. Saludo dinámico y subtítulo
        console.log('\n--- 3. SALUDO DINÁMICO Y SUBTÍTULO ---');
        assert(await greetingTitle.isVisible(), 'Título de saludo visible');
        const gText = await greetingTitle.innerText();
        console.log(`  Texto de saludo: "${gText}"`);
        assert(gText.includes('Buenas') || gText.includes('Good') || gText.includes('Gerente') || gText.includes('Manager'), 'Saludo dinámico apropiado');

        const greetingSub = page.locator('.m-greeting-sub');
        assert(await greetingSub.isVisible(), 'Subtítulo con fecha y sucursal visible');
        console.log(`  Subtítulo: "${await greetingSub.innerText()}"`);

        // 4. Tarjeta de alerta prioritaria (Rotación FEFO - Yogur de frutilla)
        console.log('\n--- 4. TARJETA DE ALERTA PRIORITARIA (ROTACIÓN FEFO) ---');
        const fefoCard = page.locator('#m-fefo-alert-card');
        assert(await fefoCard.isVisible(), 'Tarjeta verde de alerta prioritaria presente');

        const priorityPill = fefoCard.locator('.m-fefo-priority-pill');
        assert(await priorityPill.isVisible(), 'Insignia roja de prioridad "● Prioridad" visible');

        const aiTag = fefoCard.locator('.m-fefo-priority-pill');
        assert(await aiTag.isVisible(), 'Insignia de prioridad visible');

        const fefoTitle = await fefoCard.locator('.m-fefo-title').innerText();
        assert(fefoTitle.includes('Rotación FEFO') || fefoTitle.includes('FEFO Rotation'), `Título de alerta correcto: "${fefoTitle}"`);

        const fefoDesc = await fefoCard.locator('.m-fefo-desc').innerText();
        assert(fefoDesc.includes('Yogur') || fefoDesc.includes('Yogurt'), `Producto identificado en alerta: "${fefoDesc}"`);

        const yogurtVisual = fefoCard.locator('.m-fefo-visual img');
        assert(await yogurtVisual.isVisible(), 'Imagen del producto Yogur de frutilla con distintivo de rotación visible');

        // 5. Accesos rápidos (6 botones en cuadrícula 3x2)
        console.log('\n--- 5. ACCESOS RÁPIDOS (3x2) ---');
        const quickGrid = page.locator('#m-six-quick-actions');
        assert(await quickGrid.isVisible(), 'Cuadrícula de accesos rápidos presente');

        const qBtns = quickGrid.locator('.m-qbtn');
        assert(await qBtns.count() === 6, `Exactamente 6 botones en la cuadrícula (Encontrados: ${await qBtns.count()})`);

        // Botón 1: Escanear verde destacado
        const scanBtn = page.locator('#m-qbtn-scan');
        assert(await scanBtn.isVisible(), 'Botón 1 "Escanear" visible');
        const isScanGreen = await scanBtn.evaluate(el => el.classList.contains('m-qbtn--scan'));
        assert(isScanGreen, 'Botón "Escanear" destaca con fondo verde corporativo');

        // Botones 2 a 6
        assert(await page.locator('#m-qbtn-count').isVisible(), 'Botón 2 "Conteos" presente');
        assert(await page.locator('#m-qbtn-receive').isVisible(), 'Botón 3 "Recepción" presente');
        assert(await page.locator('#m-qbtn-transfer').isVisible(), 'Botón 4 "Traslados" presente');
        assert(await page.locator('#m-qbtn-waste').isVisible(), 'Botón 5 "Mermas" presente');
        assert(await page.locator('#m-qbtn-more').isVisible(), 'Botón 6 "Más herramientas" presente');

        // 6. Progreso del día
        console.log('\n--- 6. PROGRESO DEL DÍA (TODAY\'S PROGRESS) ---');
        const progCard = page.locator('#m-todays-progress-card');
        assert(await progCard.isVisible(), 'Tarjeta de progreso del día presente');

        const donutText = await progCard.locator('.m-prog-donut-text').innerText();
        assert(donutText.includes('%'), `Indicador circular de cumplimiento: ${donutText}`);

        const progText = await progCard.locator('.m-prog-text').innerText();
        assert(progText.includes('tareas') || progText.includes('tasks'), `Texto de cumplimiento: "${progText}"`);

        const progBar = progCard.locator('.m-prog-bar-fill');
        assert(await progBar.isVisible(), 'Barra de progreso horizontal verde visible');

        // 7. Navegación inferior (5 accesos + botón central elevado)
        console.log('\n--- 7. NAVEGACIÓN INFERIOR (TABS DOCK) ---');
        const tabs = page.locator('#m-tabs');
        assert(await tabs.isVisible(), 'Barra de navegación fija presente');

        const homeTab = page.locator('#m-tabs a[data-tab="home"]');
        assert(await homeTab.isVisible(), 'Acceso 1 "Inicio" presente');
        assert(await homeTab.evaluate(el => el.classList.contains('on')), 'Acceso "Inicio" marcado como activo en verde');

        const tasksTab = page.locator('#m-tabs a[data-tab="tasks"]');
        assert(await tasksTab.isVisible(), 'Acceso 2 "Tareas" presente');

        const scanFab = page.locator('#m-tabs .m-tab-scan-fab');
        assert(await scanFab.isVisible(), 'Acceso 3 central "Escanear" circular elevado presente');

        const notifTab = page.locator('#m-tabs a[data-tab="notifications"]');
        assert(await notifTab.isVisible(), 'Acceso 4 "Alertas" presente');
        const alertsBadge = page.locator('#m-alerts-badge');
        assert(await alertsBadge.isVisible(), 'Badge de Alertas con indicador numérico presente');

        const menuTab = page.locator('#m-tabs a[data-tab="menu"]');
        assert(await menuTab.isVisible(), 'Acceso 5 "Menú" presente');

        const homeIndicator = page.locator('.m-home-indicator');
        assert(await homeIndicator.isVisible(), 'Barra indicadora de gestos nativa iOS presente');

        // Captura de pantalla de la réplica móvil principal
        await page.screenshot({ path: 'captures/mobile_replica_home.png' });
        console.log('  [CAPTURA] Pantalla principal guardada en captures/mobile_replica_home.png');

        // 8. Escáner Inteligente interactivo
        console.log('\n--- 8. ESCÁNER INTELIGENTE INTERACTIVO ---');
        // Presionar botón Escanear
        await scanBtn.click();
        await page.waitForTimeout(400);

        const scannerModal = page.locator('#m-scanner-modal');
        assert(await scannerModal.isVisible(), 'Escáner inteligente a pantalla completa abierto');

        const reticle = page.locator('.m-scanner-reticle');
        assert(await reticle.isVisible(), 'Marco de lectura presente');

        const laser = page.locator('.m-scanner-laser');
        assert(await laser.isVisible(), 'Línea verde animada (escaneo láser) activa');

        const torchBtn = page.locator('#m-scanner-torch-btn');
        assert(await torchBtn.isVisible(), 'Control de activación de linterna presente');

        await page.screenshot({ path: 'captures/mobile_replica_scanner.png' });
        console.log('  [CAPTURA] Escáner inteligente guardado en captures/mobile_replica_scanner.png');

        // Simular escaneo de Yogur de Frutilla
        const simYogurtBtn = page.locator('.m-sim-pill:has-text("Yogur Frutilla")');
        await simYogurtBtn.click();
        await page.waitForTimeout(500);

        // Ficha del producto escaneado
        const resultSheet = page.locator('#m-scan-result-sheet');
        assert(await resultSheet.isVisible(), 'Ficha del producto escaneado desplegada tras detección');

        const sheetText = await page.locator('#m-scan-result-content').innerText();
        assert(sheetText.includes('Yogur') || sheetText.includes('Yogurt'), 'Producto Yogur reconocido correctamente');
        assert(sheetText.includes('Cantidad') || sheetText.includes('Quantity'), 'Selector de cantidad presente');

        await page.screenshot({ path: 'captures/mobile_replica_product_sheet.png' });
        console.log('  [CAPTURA] Ficha de producto guardada en captures/mobile_replica_product_sheet.png');

        // Confirmar recepción
        const confirmBtn = page.locator('#m-scan-act-receive');
        await confirmBtn.click();
        await page.waitForTimeout(600);

        const toast = page.locator('#m-toast');
        assert(await toast.isVisible(), 'Confirmación de operación ejecutada mediante animación toast');

        // 9. Hoja de "Más herramientas"
        console.log('\n--- 9. HERRAMIENTAS ADICIONALES ---');
        const moreBtn = page.locator('#m-qbtn-more');
        await moreBtn.click();
        await page.waitForTimeout(400);

        const sheetWrap = page.locator('#m-sheet');
        assert(await sheetWrap.isVisible(), 'Hoja de Más Herramientas desplegada correctamente');
        const toolsSheetText = await page.locator('#m-sheet-body').innerText();
        assert(toolsSheetText.includes('Ventas') || toolsSheetText.includes('Sales'), 'Acceso a Ventas integrado');
        assert(toolsSheetText.includes('Despachos') || toolsSheetText.includes('Dispatches'), 'Acceso a Despachos integrado');
        assert(toolsSheetText.includes('Recursos Humanos') || toolsSheetText.includes('Human Resources'), 'Acceso a RRHH integrado');
        assert(toolsSheetText.includes('Mermas') || toolsSheetText.includes('Loss Prevention'), 'Acceso a Mermas integrado');

        // Cerrar hoja
        await page.locator('#m-sheet button[data-close]').click();
        await page.waitForTimeout(300);

    } catch (err) {
        console.error('Error durante la validación del rediseño móvil:', err);
        failed++;
    } finally {
        await browser.close();
        console.log(`\n===============================================================`);
        console.log(`TOTAL PRUEBAS: ${passed + failed} | EXITOSAS: ${passed} | FALLIDAS: ${failed}`);
        console.log(`===============================================================\n`);
        process.exit(failed > 0 ? 1 : 0);
    }
})();
