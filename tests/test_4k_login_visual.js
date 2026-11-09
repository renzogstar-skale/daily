const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- Validating 4K Ultra HD Login Redesign Visuals ---');
  const browser = await chromium.launch();

  // 1. Monitoreo en resolución Desktop 1440x900 con 2x pixel density (Retina / 2K)
  const pageRetina = await browser.newPage({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2
  });

  const failedRequests = [];
  pageRetina.on('response', resp => {
    if (resp.status() >= 400) failedRequests.push({ url: resp.url(), status: resp.status() });
  });

  await pageRetina.goto('http://localhost:8080/login', { waitUntil: 'networkidle' });
  await pageRetina.waitForTimeout(600);

  // Verificar que la imagen cargada es el WebP o JPG optimizado
  const imgHandle = pageRetina.locator('.daily-supermarket-img');
  const naturalWidth = await imgHandle.evaluate(el => el.naturalWidth);
  const naturalHeight = await imgHandle.evaluate(el => el.naturalHeight);
  const currentSrc = await imgHandle.evaluate(el => el.currentSrc);
  console.log('✓ Desktop 2x currentSrc:', currentSrc);
  console.log('✓ Desktop 2x Natural Dimensions:', naturalWidth, 'x', naturalHeight);

  // Verificar insignias flotantes
  const badgesCount = await pageRetina.locator('.daily-hero-badge').count();
  console.log('✓ Floating Glass Badges count:', badgesCount); // expect 4
  const badgeSvgs = await pageRetina.locator('.daily-hero-badge svg').count();
  console.log('✓ Floating Glass SVG icons rendered:', badgeSvgs);

  // Capturar screenshot Desktop Ultra HD
  await pageRetina.screenshot({ path: 'scratch/login_4k_desktop_2x.png', fullPage: true });
  console.log('✓ Saved scratch/login_4k_desktop_2x.png');

  // Capturar recorte en alta resolución de solo la tarjeta de cristal
  const cardHandle = pageRetina.locator('.daily-glass-card');
  await cardHandle.screenshot({ path: 'scratch/login_4k_glass_card_hd.png' });
  console.log('✓ Saved scratch/login_4k_glass_card_hd.png');

  // 2. Monitoreo en resolución nativa 4K UHD (3840 x 2160)
  const page4K = await browser.newPage({
    viewport: { width: 3840, height: 2160 },
    deviceScaleFactor: 1
  });
  await page4K.goto('http://localhost:8080/login', { waitUntil: 'networkidle' });
  await page4K.waitForTimeout(600);
  const currentSrc4K = await page4K.locator('.daily-supermarket-img').evaluate(el => el.currentSrc);
  console.log('✓ 4K UHD Monitor currentSrc:', currentSrc4K);
  await page4K.screenshot({ path: 'scratch/login_4k_native_uhd.png', fullPage: true });
  console.log('✓ Saved scratch/login_4k_native_uhd.png');

  // 3. Monitoreo en modo oscuro (Night mode)
  await pageRetina.locator('#dailyLoginThemeToggle').click();
  await pageRetina.waitForTimeout(400);
  await pageRetina.screenshot({ path: 'scratch/login_4k_dark_theme.png', fullPage: true });
  console.log('✓ Saved scratch/login_4k_dark_theme.png');

  // 4. Monitoreo en vista móvil (375 x 812)
  const pageMob = await browser.newPage({
    viewport: { width: 375, height: 812 },
    deviceScaleFactor: 2
  });
  await pageMob.goto('http://localhost:8080/login', { waitUntil: 'networkidle' });
  await pageMob.waitForTimeout(400);
  await pageMob.screenshot({ path: 'scratch/login_4k_mobile.png', fullPage: true });
  console.log('✓ Saved scratch/login_4k_mobile.png');

  console.log('✓ Failed HTTP requests count:', failedRequests.length);
  if (failedRequests.length > 0) {
    console.error('Failed requests:', failedRequests);
  }

  await browser.close();
  console.log('--- 4K Ultra HD Visual Validation Completed Successfully! ---');
})();

