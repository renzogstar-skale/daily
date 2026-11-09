const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);

(async () => {
    console.log('===============================================================');
    console.log('=== TEST E2E: SIMPLIFICACIÓN DE MENÚ COMERCIAL DAILY ===');
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
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

        console.log('--- 1. VERIFICACIÓN EN ESCRITORIO (1440x900) ---');
        const resHome = await page.goto('http://localhost:8080/', { waitUntil: 'load' });
        assert(resHome.status() === 200, 'Página comercial de inicio responde HTTP 200');

        // 1. Logo DAILY
        const logo = page.locator('.s-logo');
        assert(await logo.isVisible(), 'Logo DAILY visible');
        const logoHref = await logo.getAttribute('href');
        assert(logoHref.endsWith('/'), `Logo apunta a inicio (${logoHref})`);

        // 2. Elementos eliminados: Plataforma, Funcionalidades, Industrias, Recursos, Soluciones
        const navText = await page.locator('#s-nav').innerText();
        assert(!navText.includes('Plataforma'), '"Plataforma" eliminado del menú comercial');
        assert(!navText.includes('Funcionalidades'), '"Funcionalidades" eliminado del menú comercial');
        assert(!navText.includes('Industrias'), '"Industrias" eliminado del menú comercial');
        assert(!navText.includes('Recursos'), '"Recursos" eliminado del menú comercial');
        assert(!navText.includes('Soluciones'), '"Soluciones" eliminado del menú comercial');

        // Menús desplegables y flechas eliminados
        const dropdownCount = await page.locator('.s-nav .s-dd').count();
        assert(dropdownCount === 0, `Cero menús desplegables (.s-dd) en el navbar (Encontrados: ${dropdownCount})`);
        const ddBtnCount = await page.locator('.s-nav .s-dd__btn').count();
        assert(ddBtnCount === 0, `Cero botones con flechas desplegables en el navbar (Encontrados: ${ddBtnCount})`);

        // 3. Elementos restantes exactos en la lista de navegación
        const navLinks = page.locator('.s-nav__list .s-nav__link');
        const navLinksCount = await navLinks.count();
        assert(navLinksCount === 3, `Menú contiene exactamente 3 enlaces principales (Encontrados: ${navLinksCount})`);

        const navLinkTexts = [];
        for (let i = 0; i < navLinksCount; i++) {
            navLinkTexts.push((await navLinks.nth(i).innerText()).trim());
        }
        console.log('  Enlaces detectados:', navLinkTexts);
        assert(navLinkTexts.includes('Inicio'), 'Enlace "Inicio" presente');
        assert(navLinkTexts.includes('Precios'), 'Enlace "Precios" presente');
        assert(navLinkTexts.includes('Contacto'), 'Enlace "Contacto" presente');

        // 4. Botones CTA a la derecha
        const btnGhost = page.locator('.s-nav__cta .s-btn--ghost');
        assert(await btnGhost.isVisible(), 'Botón "Iniciar sesión" visible a la derecha');
        const btnPrimary = page.locator('.s-nav__cta .s-btn--primary');
        assert(await btnPrimary.isVisible(), 'Botón verde "Solicitar demostración" visible a la derecha');
        assert((await btnPrimary.innerText()).includes('Solicitar demostración'), 'Texto del botón es "Solicitar demostración"');

        // 5. Navegación interactiva y funcionamiento de enlaces
        console.log('\n--- 2. VERIFICACIÓN DE FUNCIONAMIENTO DE ENLACES ---');
        
        // Clic en Precios
        await page.locator('.s-nav__link:has-text("Precios")').click();
        await page.waitForLoadState('load');
        assert(page.url().includes('/pricing'), `Navegación a /pricing exitosa (URL: ${page.url()})`);
        assert(await page.locator('.s-nav__link:has-text("Precios")').getAttribute('class').then(c => c.includes('is-active')), 'Enlace "Precios" marcado como activo');

        // Clic en Contacto
        await page.locator('.s-nav__link:has-text("Contacto")').click();
        await page.waitForLoadState('load');
        assert(page.url().includes('/contact'), `Navegación a /contact exitosa (URL: ${page.url()})`);
        assert(await page.locator('.s-nav__link:has-text("Contacto")').getAttribute('class').then(c => c.includes('is-active')), 'Enlace "Contacto" marcado como activo');

        // Clic en Solicitar demostración
        await page.locator('.s-btn--primary:has-text("Solicitar demostración")').click();
        await page.waitForLoadState('load');
        assert(page.url().includes('/demo'), `Navegación a /demo exitosa (URL: ${page.url()})`);

        // Clic en Iniciar sesión ($loginUrl redirige a /login o /today si auth está desactivada en dev)
        const loginBtnHref = await page.locator('.s-btn--ghost:has-text("Iniciar sesión")').getAttribute('href');
        await page.locator('.s-btn--ghost:has-text("Iniciar sesión")').click();
        await page.waitForLoadState('load');
        assert(page.url().includes('/login') || page.url().includes('/today'), `Navegación de Iniciar sesión exitosa hacia ${loginBtnHref} (URL resultante: ${page.url()})`);

        // Regresar a Inicio mediante el logo
        await page.goto('http://localhost:8080/demo', { waitUntil: 'load' });
        await page.locator('.s-logo').click();
        await page.waitForLoadState('load');
        assert(page.url() === 'http://localhost:8080/' || page.url().endsWith(':8080/'), 'Clic en Logo regresa a inicio');

        // 6. Verificación Móvil (390x844)
        console.log('\n--- 3. VERIFICACIÓN RESPONSIVE MÓVIL (390x844) ---');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('http://localhost:8080/', { waitUntil: 'load' });

        const burger = page.locator('.s-burger');
        assert(await burger.isVisible(), 'Botón hamburguesa visible en viewport móvil');

        // Abrir menú hamburguesa
        await burger.click();
        await page.waitForTimeout(300);

        const mobileNavVisible = await page.locator('#s-nav').isVisible();
        assert(mobileNavVisible, 'Menú móvil desplegado correctamente');

        const mobileNavText = await page.locator('#s-nav').innerText();
        assert(!mobileNavText.includes('Plataforma'), 'Móvil: sin "Plataforma"');
        assert(!mobileNavText.includes('Funcionalidades'), 'Móvil: sin "Funcionalidades"');
        assert(!mobileNavText.includes('Industrias'), 'Móvil: sin "Industrias"');
        assert(!mobileNavText.includes('Recursos'), 'Móvil: sin "Recursos"');
        assert(!mobileNavText.includes('Soluciones'), 'Móvil: sin "Soluciones"');
        assert(mobileNavText.includes('Inicio'), 'Móvil: contiene "Inicio"');
        assert(mobileNavText.includes('Precios'), 'Móvil: contiene "Precios"');
        assert(mobileNavText.includes('Contacto'), 'Móvil: contiene "Contacto"');
        assert(mobileNavText.includes('Iniciar sesión'), 'Móvil: contiene "Iniciar sesión"');
        assert(mobileNavText.includes('Solicitar demostración'), 'Móvil: contiene "Solicitar demostración"');

        // 7. Verificación de integridad en dashboards operativos internos
        console.log('\n--- 4. INTEGRIDAD DE DASHBOARDS OPERATIVOS (NO MODIFICADOS) ---');
        const internalCheck = await page.goto('http://localhost:8080/today', { waitUntil: 'load' });
        assert(internalCheck.status() === 200, 'Dashboard operativo /today intacto');
        const sidebarExists = await page.locator('.sidebar, #sidebar, .daily-main-nav, .nav-item').count();
        assert(sidebarExists > 0, 'Menú y navegación operativa interna conservados');

    } catch (err) {
        console.error('Error durante la prueba E2E del menú comercial:', err);
        failed++;
    } finally {
        await browser.close();
        console.log(`\n===============================================================`);
        console.log(`TOTAL PRUEBAS: ${passed + failed} | EXITOSAS: ${passed} | FALLIDAS: ${failed}`);
        console.log(`===============================================================\n`);
        process.exit(failed > 0 ? 1 : 0);
    }
})();
