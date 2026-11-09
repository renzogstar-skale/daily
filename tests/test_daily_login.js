const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- Starting Playwright Validation for DAILY Login Redesign ---');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(err.message));
  page.on('response', resp => {
    if (resp.status() >= 400) console.log('HTTP Error Response:', resp.status(), resp.url());
  });

  // 1. Cargar pantalla de login
  await page.goto('http://localhost:8080/login', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  // 2. Verificar elementos estructurales
  const brand = await page.locator('.daily-login-brand-link .daily-brand-text').innerText();
  console.log('✓ Brand logo text:', brand);

  const title = await page.locator('.daily-form-title').innerText();
  const subtitle = await page.locator('.daily-form-tagline').innerText();
  console.log('✓ Form Title & Subtitle:', { title, subtitle });

  // 3. Probar Mostrar/Ocultar contraseña
  const pwdInput = page.locator('#password_input');
  await pwdInput.fill('SecretoRetail2026!');
  let pwdType = await pwdInput.getAttribute('type');
  console.log('✓ Initial password type:', pwdType); // expect password

  await page.locator('#togglePasswordBtn').click();
  await page.waitForTimeout(200);
  pwdType = await pwdInput.getAttribute('type');
  console.log('✓ After clicking eye button, password type:', pwdType); // expect text

  await page.locator('#togglePasswordBtn').click();
  await page.waitForTimeout(200);
  pwdType = await pwdInput.getAttribute('type');
  console.log('✓ After clicking eye again, password type:', pwdType); // expect password
  await pwdInput.fill(''); // clear for validation test

  // 4. Probar Validación de campos vacíos
  await page.locator('#login-button').click();
  await page.waitForTimeout(300);
  const errorVisible = await page.locator('#login-error').isVisible();
  const errorText = await page.locator('#login-error-text').innerText();
  console.log('✓ Validation error visible on empty submit:', errorVisible, '| Text:', errorText);

  // 5. Probar "¿Olvidaste tu contraseña?" modal
  await page.locator('#linkForgotPassword').click();
  await page.waitForTimeout(400);
  const isForgotModalOpen = await page.locator('#modalForgotPassword').isVisible();
  console.log('✓ Forgot Password Modal opened:', isForgotModalOpen);

  await page.locator('#recoveryUserOrEmail').fill('admin@daily.com');
  await page.locator('#btnSubmitRecovery').click();
  await page.waitForTimeout(600);
  const isRecoverySuccess = await page.locator('#recoverySuccessAlert').isVisible();
  console.log('✓ Recovery instructions confirmation visible:', isRecoverySuccess);

  await page.locator('#modalForgotPassword .close').click();
  await page.waitForTimeout(300);

  // 6. Probar botones SSO (Microsoft, Google, Apple)
  await page.locator('[data-sso-provider="microsoft"]').click();
  await page.waitForTimeout(400);
  let ssoModalVisible = await page.locator('#modalSsoProvider').isVisible();
  let ssoTitle = await page.locator('#ssoModalTitle').innerText();
  console.log('✓ Microsoft SSO modal opened:', ssoModalVisible, '| Title:', ssoTitle);
  await page.locator('#modalSsoProvider .close').click();
  await page.waitForTimeout(300);

  await page.locator('[data-sso-provider="google"]').click();
  await page.waitForTimeout(400);
  ssoTitle = await page.locator('#ssoModalTitle').innerText();
  console.log('✓ Google SSO modal opened with title:', ssoTitle);
  await page.locator('#modalSsoProvider .close').click();
  await page.waitForTimeout(300);

  await page.locator('[data-sso-provider="apple"]').click();
  await page.waitForTimeout(400);
  ssoTitle = await page.locator('#ssoModalTitle').innerText();
  console.log('✓ Apple SSO modal opened with title:', ssoTitle);
  await page.locator('#modalSsoProvider .close').click();
  await page.waitForTimeout(300);

  // 7. Probar Conmutador de Tema Claro / Oscuro
  await page.locator('#dailyLoginThemeToggle').click();
  await page.waitForTimeout(400);
  const isDarkMode = await page.evaluate(() => document.body.classList.contains('night-mode'));
  console.log('✓ Dark mode toggled on successfully:', isDarkMode);

  // Capturar pantalla en modo oscuro
  await page.screenshot({ path: 'scratch/login_page_dark.png', fullPage: true });
  console.log('✓ Saved scratch/login_page_dark.png');

  // Volver a modo claro
  await page.locator('#dailyLoginThemeToggle').click();
  await page.waitForTimeout(400);
  const isLightMode = await page.evaluate(() => !document.body.classList.contains('night-mode'));
  console.log('✓ Light mode restored successfully:', isLightMode);

  // 8. Probar vista móvil
  await page.setViewportSize({ width: 375, height: 812 });
  await page.waitForTimeout(400);
  await page.screenshot({ path: 'scratch/login_page_mobile.png', fullPage: true });
  console.log('✓ Saved scratch/login_page_mobile.png');

  // 9. Restaurar vista escritorio y verificar login real
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.waitForTimeout(200);

  // Probar login con credenciales válidas
  await page.locator('#username').fill('rgiovine');
  await page.locator('#password_input').fill('admin');
  console.log('✓ Filled login form with credentials (rgiovine/admin)');

  // Clic en Iniciar sesión
  await Promise.all([
    page.waitForNavigation({ timeout: 10000 }).catch(e => console.log('Navigation event:', e.message)),
    page.locator('#login-button').click()
  ]);
  await page.waitForTimeout(1000);

  const currentUrl = page.url();
  console.log('✓ Post-login URL:', currentUrl);
  const isAuthSuccess = !currentUrl.includes('/login');
  console.log('✓ Real authentication successful:', isAuthSuccess);

  console.log('✓ Console Errors count:', consoleErrors.length);
  if (consoleErrors.length > 0) {
    console.error('Console errors:', consoleErrors);
  }

  await browser.close();
  console.log('--- All Playwright Tests for DAILY Login Completed! ---');
})();
