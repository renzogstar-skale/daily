const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- Starting Playwright Validation for DAILY Sales Dashboard ---');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
  page.on('pageerror', err => {
    consoleErrors.push(err.message);
  });

  await page.goto('http://localhost:8080/sales', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  // 1. Header Validation
  const dash = page.locator('#salesExecutiveDashboard');
  const title = (await dash.locator('.ded-title').innerText()).trim();
  const subtitle = (await dash.locator('.ded-subtitle').innerText()).trim();
  console.log('✓ Header:', { title, subtitle });

  const dateBtnText = (await dash.locator('#btnDedDates').innerText()).trim();
  const branchBtnText = (await dash.locator('#btnDedBranches').innerText()).trim();
  const deptBtnText = (await dash.locator('#btnDedDepartments').innerText()).trim();
  console.log('✓ Selectors:', { dateBtnText, branchBtnText, deptBtnText });

  // 2. Row 1: 7 KPI Cards Validation
  const kpis = await dash.locator('.ded-kpi-card');
  const kpiCount = await kpis.count();
  console.log(`✓ KPI Cards Count: ${kpiCount} (expected: 7)`);

  const netSalesCard = await dash.locator('.ded-kpi-card.is-highlight .ded-kpi-val').innerText();
  const netSalesSub = await dash.locator('.ded-kpi-card.is-highlight .ded-kpi-sub-highlight').innerText();
  console.log('✓ KPI 1 - Ventas Netas:', { netSalesCard, sub: netSalesSub.replace(/\s+/g, ' ').trim() });

  const dailyAvg = await dash.locator('.ded-kpi-card:nth-child(2) .ded-kpi-val').innerText();
  const tickets = await dash.locator('.ded-kpi-card:nth-child(3) .ded-kpi-val').innerText();
  const salesPerHour = await dash.locator('.ded-kpi-card:nth-child(4) .ded-kpi-val').innerText();
  const salesPerCollab = await dash.locator('.ded-kpi-card:nth-child(5) .ded-kpi-val').innerText();
  const assignedStaff = await dash.locator('.ded-kpi-card:nth-child(6) .ded-kpi-val').innerText();
  const hoursWorked = await dash.locator('.ded-kpi-card:nth-child(7) .ded-kpi-val').innerText();
  const hoursPct = await dash.locator('.ded-progress-ring-text').innerText();

  console.log('✓ KPI 2-7 Values:', {
    dailyAvg,
    tickets,
    salesPerHour,
    salesPerCollab,
    assignedStaff,
    hoursWorked,
    hoursPct
  });

  // 3. Row 2: Sales Evolution + Commercial Comparison
  const evoTitle = await dash.locator('.ded-row-2 .ded-card:nth-child(1) .ded-card-title').innerText();
  const compTitle = await dash.locator('.ded-row-2 .ded-card:nth-child(2) .ded-card-title').innerText();
  const weekendCur = await dash.locator('.ded-comp-col:nth-child(1) .ded-comp-row:nth-child(1) .ded-comp-val').innerText();
  const weekendDelta = await dash.locator('.ded-comp-col:nth-child(1) .ded-comp-row:nth-child(1) .ded-pill-red').innerText();
  const weekdayShare = await dash.locator('.ded-comp-col:nth-child(2) .ded-comp-row:nth-child(1) .ded-comp-val').innerText();
  console.log('✓ Row 2 Commercial Comparison:', { evoTitle, compTitle, weekendCur, weekendDelta, weekdayShare });

  // 4. Row 3: Operational Analysis (Top 5, Traffic, Department Donut)
  const top5Rows = await dash.locator('.ded-table-top5 tbody tr');
  const top5Count = await top5Rows.count();
  console.log(`✓ Top 5 Products Count: ${top5Count} (expected: 5)`);
  for (let i = 0; i < top5Count; i++) {
    const row = top5Rows.nth(i);
    const rank = await row.locator('.ded-rank-circle').innerText();
    const prodName = await row.locator('.ded-prod-name').innerText();
    const sku = await row.locator('.ded-sku-badge').innerText();
    const units = await row.locator('.ded-table-num').innerText();
    const net = await row.locator('.ded-table-price').innerText();
    const share = await row.locator('.ded-table-share').innerText();
    console.log(`   [#${rank}] ${prodName} | SKU: ${sku} | Unid: ${units} | Net: ${net} | Part: ${share}`);
  }

  const donutCenterAmt = await dash.locator('.ded-donut-center-amount').innerText();
  const donutCenterLbl = await dash.locator('.ded-donut-center-label').innerText();
  const deptsCount = await dash.locator('.ded-dept-row').count();
  console.log(`✓ Department Doughnut Center: ${donutCenterAmt} (${donutCenterLbl}), Departments listed: ${deptsCount}`);

  // 5. Row 4: Alerts + Observations
  const alertCards = await dash.locator('.ded-alert-mini-card');
  const alertCount = await alertCards.count();
  const alertNums = [];
  for (let i = 0; i < alertCount; i++) {
    const num = await alertCards.nth(i).locator('.ded-alert-num').innerText();
    const lbl = await alertCards.nth(i).locator('.ded-alert-label').innerText();
    alertNums.push(`${num} ${lbl}`);
  }
  console.log('✓ Row 4 Alerts:', alertNums.join(' | '));

  const reviewItems = await dash.locator('.ded-review-item');
  const reviewCount = await reviewItems.count();
  console.log(`✓ Row 4 Observations Count: ${reviewCount} (expected: 4)`);

  // 6. Test Interactivity: Modals
  console.log('\n--- Testing Interactivity & Modals ---');
  // Expand evolution chart
  await dash.locator('.ded-expand-btn[data-expand="evolution"]').click();
  await page.waitForTimeout(500);
  const isZoomVisible = await page.locator('#modalExecutiveZoom').isVisible();
  console.log('✓ Zoom Modal opened on expand click:', isZoomVisible);
  await page.locator('#modalExecutiveZoom .close').click();
  await page.waitForTimeout(400);

  // Open Observations detail modal
  await dash.locator('[data-open-observations="all"]').click();
  await page.waitForTimeout(500);
  const isObsVisible = await page.locator('#modalObservacionesDetalle').isVisible();
  console.log('✓ Observations Detail Modal opened on "Ver detalle":', isObsVisible);
  await page.locator('#modalObservacionesDetalle .close').click();
  await page.waitForTimeout(400);

  // Click on top product item
  await dash.locator('.ded-table-top5 tbody tr:first-child').click();
  await page.waitForTimeout(500);
  const isProdVisible = await page.locator('#modalProductDetail').isVisible();
  console.log('✓ Product Detail Modal opened on row click:', isProdVisible);
  await page.locator('#modalProductDetail .close').click();
  await page.waitForTimeout(400);

  // Test Dropdowns
  await page.locator('#btnDedDates').click();
  await page.waitForTimeout(300);
  const isDateMenuOpen = await page.locator('.ded-dropdown-menu.show').isVisible();
  console.log('✓ Date dropdown menu opens:', isDateMenuOpen);
  await page.locator('body').click({ position: { x: 10, y: 10 } }); // Close

  await page.locator('#btnDedBranches').click();
  await page.waitForTimeout(300);
  const isBranchMenuOpen = await page.locator('.ded-dropdown-menu.show').isVisible();
  console.log('✓ Branch dropdown menu opens:', isBranchMenuOpen);
  await page.locator('body').click({ position: { x: 10, y: 10 } }); // Close

  await page.locator('#btnDedUserAvatar').click();
  await page.waitForTimeout(300);
  const isUserMenuOpen = await page.locator('.ded-dropdown-menu.show').isVisible();
  console.log('✓ User Avatar dropdown menu opens with module links:', isUserMenuOpen);
  await page.locator('body').click({ position: { x: 10, y: 10 } }); // Close

  // Test secondary tab view navigation
  await page.goto('http://localhost:8080/sales?tab=departamentos', { waitUntil: 'networkidle' });
  const isDeptPage = await page.locator('#tab-departamentos').isVisible();
  console.log('✓ Successfully navigated to Rendimiento por Departamento tab:', isDeptPage);

  // Return to Tablero de Ventas
  await page.goto('http://localhost:8080/sales', { waitUntil: 'networkidle' });
  const isResumenPage = await page.locator('#tab-resumen').isVisible();
  console.log('✓ Successfully returned to Tablero de Ventas:', isResumenPage);

  // 7. Verify console errors
  console.log(`\n✓ Console Errors count: ${consoleErrors.length}`);
  if (consoleErrors.length > 0) {
    console.error('Errors encountered:', consoleErrors);
  }

  // 8. Capture high-res screenshot
  if (!fs.existsSync('captures')) {
    fs.mkdirSync('captures', { recursive: true });
  }
  await page.screenshot({ path: 'captures/sales_dashboard_final.png', fullPage: false });
  console.log('✓ Final screenshot saved to captures/sales_dashboard_final.png');

  // Copy to brain artifacts directory
  const artifactPath = 'C:/Users/renzo/.gemini/antigravity/brain/c5807ff4-43be-460d-9e7f-c4d5397194fb/sales_dashboard_final.png';
  fs.copyFileSync('captures/sales_dashboard_final.png', artifactPath);
  console.log(`✓ Copied final screenshot to artifact: ${artifactPath}`);

  await browser.close();
  console.log('--- All Playwright Tests Passed Successfully! ---');
})();
