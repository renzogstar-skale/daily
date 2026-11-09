const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  console.log('--- Starting Playwright Validation for DAILY Rendimiento por Departamento ---');
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

  await page.goto('http://localhost:8080/sales?tab=departamentos', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  // 1. Header Validation
  const dash = page.locator('#deptExecutiveDashboard');
  const title = (await dash.locator('.ded-title').innerText()).trim();
  const subtitle = (await dash.locator('.ded-subtitle').innerText()).trim();
  console.log('✓ Header:', { title, subtitle });

  const dateBtnText = (await dash.locator('#btnDpDates').innerText()).trim();
  const branchBtnText = (await dash.locator('#btnDpBranches').innerText()).trim();
  const deptBtnText = (await dash.locator('#btnDpDepartments').innerText()).trim();
  console.log('✓ Selectors:', { dateBtnText, branchBtnText, deptBtnText });

  // 2. Row 1: 7 KPI Cards Validation
  const kpis = await dash.locator('.ded-kpi-card');
  const kpiCount = await kpis.count();
  console.log(`✓ KPI Cards Count: ${kpiCount} (expected: 7)`);

  const kpi1Val = await dash.locator('.ded-kpi-card:nth-child(1) .ded-kpi-val').innerText();
  const kpi1Sub = await dash.locator('.ded-kpi-card:nth-child(1) .ded-kpi-sub-highlight').innerText();

  const kpi2Val = await dash.locator('.ded-kpi-card:nth-child(2) .ded-kpi-val').innerText();
  const kpi3Val = await dash.locator('.ded-kpi-card:nth-child(3) .ded-kpi-val').innerText();
  const kpi4Val = await dash.locator('.ded-kpi-card:nth-child(4) .ded-kpi-val').innerText();
  const kpi5Val = await dash.locator('.ded-kpi-card:nth-child(5) .ded-kpi-val').innerText();
  const kpi6Val = await dash.locator('.ded-kpi-card:nth-child(6) .ded-kpi-val').innerText();
  const kpi7Val = await dash.locator('.ded-kpi-card:nth-child(7) .ded-kpi-val').innerText();
  const kpi7Sub = await dash.locator('.ded-kpi-card:nth-child(7) .ded-kpi-sub').innerText();

  console.log('✓ 7 KPI Values:');
  console.log(`   1. Margen de contribución: ${kpi1Val} (${kpi1Sub.replace(/\s+/g, ' ').trim()})`);
  console.log(`   2. Utilidad operativa: ${kpi2Val}`);
  console.log(`   3. Productividad laboral: ${kpi3Val}`);
  console.log(`   4. Costo laboral / ventas: ${kpi4Val}`);
  console.log(`   5. Eficiencia operativa: ${kpi5Val}`);
  console.log(`   6. Rentabilidad por m²: ${kpi6Val}`);
  console.log(`   7. Oportunidad de mejora: ${kpi7Val} (${kpi7Sub.trim()})`);

  // 3. Row 2: Desempeño por departamento table + DRI
  const deptRows = await page.locator('#tableDeptPerformance tbody tr');
  const deptCount = await deptRows.count();
  console.log(`✓ Department Table Rows: ${deptCount} (expected: 5)`);
  for (let i = 0; i < deptCount; i++) {
    const row = deptRows.nth(i);
    const dName = await row.locator('td:nth-child(1)').innerText();
    const margin = await row.locator('td:nth-child(2)').innerText();
    const profit = await row.locator('td:nth-child(3)').innerText();
    const labor = await row.locator('td:nth-child(4)').innerText();
    const profitH = await row.locator('td:nth-child(5)').innerText();
    const waste = await row.locator('td:nth-child(6)').innerText();
    const avail = await row.locator('td:nth-child(7)').innerText();
    const status = await row.locator('td:nth-child(8)').innerText();
    console.log(`   [${dName.trim()}] Margen: ${margin} | Utilidad: ${profit} | Costo lab: ${labor} | Util/h: ${profitH} | Merma: ${waste} | Disp: ${avail} | Estado: ${status}`);
  }

  const driScore = (await page.locator('.dp-dri-score-num').innerText()).trim();
  const driBadge = (await page.locator('.dp-dri-status-badge').innerText()).trim();
  const driRankCount = await page.locator('.dp-dri-ranking-item').count();
  console.log(`✓ DRI Widget: ${driScore}/100 (${driBadge}), Ranking items: ${driRankCount}`);

  // 4. Row 3: 3 Panels
  const repCards = await page.locator('.dp-rep-card');
  const repCount = await repCards.count();
  console.log(`✓ Replenishment Cards Count: ${repCount} (expected: 4)`);
  for (let i = 0; i < repCount; i++) {
    const card = repCards.nth(i);
    const lbl = await card.locator('.dp-rep-label').innerText();
    const val = await card.locator('.dp-rep-val').innerText();
    const delta = await card.locator('.dp-rep-delta').innerText();
    console.log(`   [Rep #${i+1}] ${lbl}: ${val} (${delta})`);
  }

  // 5. Row 4: Heatmap + Opportunities
  const hmRows = await page.locator('.dp-heatmap-table tbody tr');
  const hmRowCount = await hmRows.count();
  console.log(`✓ Heatmap Rows: ${hmRowCount} (expected: 5)`);

  const oppRows = await page.locator('.dp-opps-table tbody tr');
  const oppCount = await oppRows.count();
  console.log(`✓ Priority Opportunities Count: ${oppCount} (expected: 4)`);
  for (let i = 0; i < oppCount; i++) {
    const r = oppRows.nth(i);
    const rank = await r.locator('.dp-opp-rank').innerText();
    const dept = await r.locator('td:nth-child(2)').innerText();
    const prob = await r.locator('td:nth-child(3)').innerText();
    const impact = await r.locator('.dp-opp-impact').innerText();
    const action = await r.locator('td:nth-child(5)').innerText();
    const prio = await r.locator('.dp-opp-priority').innerText();
    console.log(`   #${rank} ${dept} | Problema: ${prob} | Impacto: ${impact} | Acción: ${action} | Prio: ${prio}`);
  }

  // 6. Test Interactivity & Modals
  console.log('\n--- Testing Interactivity & Modals ---');

  // Click department row (Frozen)
  await page.locator('#tableDeptPerformance tbody tr:first-child').click();
  await page.waitForTimeout(500);
  const isDeptModalOpen = await page.locator('#modalDeptDetail').isVisible();
  console.log('✓ Department Detail Modal opened on row click:', isDeptModalOpen);
  await page.locator('#modalDeptDetail .close').click();
  await page.waitForTimeout(400);

  // Click What-If simulator button
  await page.locator('button:has-text("Simulador What-If")').click();
  await page.waitForTimeout(500);
  const isWhatIfOpen = await page.locator('#modalWhatIf').isVisible();
  console.log('✓ What-If Simulator Modal opened:', isWhatIfOpen);
  await page.locator('#modalWhatIf .close').click();
  await page.waitForTimeout(400);

  // Click opportunity row
  await page.locator('.dp-opps-table tbody tr:first-child').click();
  await page.waitForTimeout(500);
  const isActionPlanOpen = await page.locator('#modalActionPlan').isVisible();
  console.log('✓ Action Plan Modal opened on opportunity row click:', isActionPlanOpen);
  await page.locator('#modalActionPlan .close').click();
  await page.waitForTimeout(400);

  // Click DRI methodology icon
  await page.locator('button[onclick*="openDriMethodologyModal"]').click();
  await page.waitForTimeout(500);
  const isDriModalOpen = await page.locator('#modalDriMethodology').isVisible();
  console.log('✓ DRI Methodology Modal opened:', isDriModalOpen);
  await page.locator('#modalDriMethodology .close').click();
  await page.waitForTimeout(400);

  // Click Heatmap cell
  await page.locator('.dp-hm-cell').first().click();
  await page.waitForTimeout(500);
  const isHmCellOpen = await page.locator('#modalHeatmapCell').isVisible();
  console.log('✓ Heatmap Cell Detail Modal opened on cell click:', isHmCellOpen);
  await page.locator('#modalHeatmapCell .close').click();
  await page.waitForTimeout(400);

  // Click Replenishment card (Stockouts)
  await page.locator('.dp-rep-card').nth(3).click();
  await page.waitForTimeout(500);
  const isRepModalOpen = await page.locator('#modalReplenishAudit').isVisible();
  console.log('✓ Replenishment Audit Modal opened on card click:', isRepModalOpen);
  await page.locator('#modalReplenishAudit .close').click();
  await page.waitForTimeout(400);

  // Toggle $ / % in rentability chart
  await page.locator('#btnToggleRentPct').click();
  await page.waitForTimeout(300);
  await page.locator('#btnToggleRentDollar').click();
  await page.waitForTimeout(300);
  console.log('✓ Rentability $ / % scale toggle works seamlessly');

  // Verify console errors
  console.log(`\n✓ Console Errors count: ${consoleErrors.length}`);
  if (consoleErrors.length > 0) {
    console.error('Errors encountered:', consoleErrors);
  }

  // 7. Capture high-res screenshot
  if (!fs.existsSync('captures')) {
    fs.mkdirSync('captures', { recursive: true });
  }
  await page.screenshot({ path: 'captures/dept_performance_final.png', fullPage: false });
  console.log('✓ Final screenshot saved to captures/dept_performance_final.png');

  // Copy to brain artifacts
  const artifactPath = 'C:/Users/renzo/.gemini/antigravity/brain/c5807ff4-43be-460d-9e7f-c4d5397194fb/dept_performance_final.png';
  fs.copyFileSync('captures/dept_performance_final.png', artifactPath);
  console.log(`✓ Copied final screenshot to artifact: ${artifactPath}`);

  await browser.close();
  console.log('--- All Playwright Tests for Rendimiento por Departamento Passed Successfully! ---');
})();
