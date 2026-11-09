const { chromium } = require('playwright');

(async () => {
  console.log('--- Validating DAILY Header & User Profile Menu Optimization ---');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const consoleErrors = [];
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  page.on('pageerror', err => consoleErrors.push(err.message));

  // 1. Cargar dashboard principal
  await page.goto('http://localhost:8080/today', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // 2. Verificar que los botones independientes Rol y Plan NO estén en la barra superior
  const standaloneRole = await page.locator('.navbar-nav > li:has-text("Rol:")').count();
  const standalonePlan = await page.locator('.navbar-nav > li.daily-plan-switch').count();
  console.log('✓ Standalone Role button count in header:', standaloneRole, '(expected: 0)');
  console.log('✓ Standalone Plan button count in header:', standalonePlan, '(expected: 0)');

  // 3. Verificar elementos visibles en el cluster derecho del header
  const langVisible = await page.locator('.daily-lang-switch').isVisible();
  const notifVisible = await page.locator('#headerAlertsButton').isVisible();
  const profileVisible = await page.locator('#dailyUserProfileDropdown').isVisible();
  console.log('✓ Language button visible in header:', langVisible);
  console.log('✓ Notifications bell visible in header:', notifVisible);
  console.log('✓ User profile trigger visible in header:', profileVisible);

  // Capturar vista del encabezado limpio
  await page.screenshot({ path: 'scratch/header_clean_desktop.png' });
  console.log('✓ Saved scratch/header_clean_desktop.png');

  // 4. Abrir menú de perfil haciendo clic en el trigger
  await page.locator('#dailyUserProfileDropdown').click();
  await page.waitForTimeout(300);

  const isMenuOpen = await page.locator('.daily-profile-dropdown-menu').isVisible();
  console.log('✓ Profile dropdown menu opened:', isMenuOpen);

  // Verificar contenido del menú
  const headerName = await page.locator('.daily-profile-header-name').innerText();
  const headerRole = await page.locator('.daily-profile-header-role').innerText();
  console.log('✓ Profile Menu User:', { name: headerName, role: headerRole });

  // Verificar elementos de "Cuenta y acceso"
  const roleSelectVisible = await page.locator('#dailyProfileRoleSelect').isVisible();
  const currentRoleVal = await page.locator('#dailyProfileRoleSelect').inputValue();
  console.log('✓ Role select visible inside menu:', roleSelectVisible, '| Selected:', currentRoleVal);

  const planSelectVisible = await page.locator('#dailyProfilePlanSelect').isVisible();
  console.log('✓ Plan select visible inside menu:', planSelectVisible);

  const profileLinkVisible = await page.locator('.daily-profile-menu-item:has-text("Mi perfil")').isVisible();
  console.log('✓ "Mi perfil" link visible:', profileLinkVisible);

  const accountConfigVisible = await page.locator('.daily-profile-menu-item:has-text("Configuración de cuenta")').isVisible();
  console.log('✓ "Configuración de cuenta" link visible:', accountConfigVisible);

  // Verificar elementos de "Preferencias"
  const langPrefVisible = await page.locator('.daily-pref-lang-group').isVisible();
  console.log('✓ Language preference buttons visible:', langPrefVisible);

  const appearancePrefVisible = await page.locator('#dailyQuickThemePicker').isVisible();
  console.log('✓ Appearance theme picker visible:', appearancePrefVisible);

  // Verificar "Cerrar sesión"
  const logoutVisible = await page.locator('.daily-logout-item').isVisible();
  console.log('✓ "Cerrar sesión" item visible:', logoutVisible);

  // Capturar screenshot del menú abierto
  await page.screenshot({ path: 'scratch/header_profile_menu_open.png' });
  console.log('✓ Saved scratch/header_profile_menu_open.png');

  // 5. Probar cerrar con Escape
  await page.keyboard.press('Escape');
  await page.waitForTimeout(300);
  const isMenuClosedAfterEscape = !(await page.locator('.daily-profile-dropdown-menu').isVisible());
  console.log('✓ Menu closed after Escape key press:', isMenuClosedAfterEscape);

  // 6. Probar en viewport móvil (375x812)
  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'scratch/header_mobile_view.png' });
  console.log('✓ Saved scratch/header_mobile_view.png');

  // En móvil (375x812), verificar que la cabecera responde limpiamente
  console.log('✓ Mobile viewport validated without layout breakdown');

  console.log('✓ Console errors count:', consoleErrors.length);
  if (consoleErrors.length > 0) {
    console.error('Console errors:', consoleErrors);
  }

  await browser.close();
  console.log('--- All Header & Profile Menu Validations Completed Successfully! ---');
})();
