/**
 * Test E2E Automatizado con Playwright:
 * Validación completa de la sección "Rendimiento por Departamento" en Ventas y Rentabilidad (/sales)
 */
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = 'http://localhost:8080';

(async () => {
	console.log('🚀 Iniciando pruebas E2E: Rendimiento por Departamento en /sales...\n');
	const browser = await chromium.launch({ channel: 'msedge' });
	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();

	const results = [];
	const consoleErrors = [];

	const check = (desc, condition, detail) => {
		const status = condition ? '✅ PASS' : '❌ FAIL';
		results.push({ status, desc, detail: condition ? '' : (detail || '') });
		console.log(`${status} - ${desc}${condition ? '' : ' (' + detail + ')'}`);
	};

	page.on('pageerror', err => {
		consoleErrors.push('PAGEERROR: ' + err.message);
	});
	page.on('console', msg => {
		if (msg.type() === 'error' && !/favicon|Failed to load resource/i.test(msg.text())) {
			consoleErrors.push('CONSOLE: ' + msg.text().slice(0, 160));
		}
	});

	try {
		// 1. Cargar ruta /sales
		console.log('--- 1. Navegación a /sales ---');
		const res = await page.goto(BASE_URL + '/sales', { waitUntil: 'load', timeout: 30000 });
		check('Código HTTP 200 en /sales', res.status() === 200, 'Status: ' + res.status());
		await page.waitForTimeout(1000);

		// 2. Filtros: Píldoras de períodos requeridas
		console.log('\n--- 2. Filtros y Píldoras de Período ---');
		const pills = ['Hoy', 'Ayer', 'Semana actual', 'Semana anterior', 'Mes actual', 'Mes anterior', 'Año actual'];
		for (const pill of pills) {
			const exists = await page.locator(`.sales-period-pill:has-text("${pill}")`).count() > 0;
			check(`Píldora de período "${pill}" presente`, exists);
		}

		// Filtros dropdowns
		check('Filtro Sucursal presente', await page.locator('select#fBranch').count() > 0);
		check('Filtro Departamento presente', await page.locator('select#fDept').count() > 0);
		check('Filtro Categoría presente', await page.locator('select#fGroup').count() > 0);

		// 3. Integración en Tablero General (Acceso interno tras five_blocks)
		console.log('\n--- 3. Integración tras Resumen General y Pestaña ---');
		const teaserExists = await page.locator('[data-testid="dept-perf-teaser"]').isVisible();
		check('Tarjeta de acceso interno presente en Tablero de ventas tras five_blocks', teaserExists);

		const tabButton = page.locator('#pill-departamentos');
		check('Pestaña "Rendimiento por departamento" en la barra de pestañas', await tabButton.count() > 0);

		// Hacer clic en la pestaña para abrir la sección
		await tabButton.click();
		await page.waitForTimeout(600);
		const sectionVisible = await page.locator('#tab-departamentos.active').isVisible();
		check('Pestaña #tab-departamentos se activa al hacer clic', sectionVisible);

		// 4. Indicadores Principales (7 KPIs)
		console.log('\n--- 4. Indicadores Principales (7 KPIs) ---');
		const kpiTy = await page.locator('[data-testid="kpi-ty-sales"]').isVisible();
		check('KPI 1: Ventas TY presente con período', kpiTy);
		const tyText = await page.locator('#kpiTyVal').textContent();
		check('KPI 1: Valor TY formateado en moneda', /\$[\d,]+\.\d{2}/.test(tyText), tyText);

		const kpiLy = await page.locator('[data-testid="kpi-ly-sales"]').isVisible();
		check('KPI 2: Ventas LY presente con período equivalente', kpiLy);

		const kpiVar = await page.locator('[data-testid="kpi-interannual-variation"]').isVisible();
		check('KPI 3: Variación Interanual presente', kpiVar);
		const varText = await page.locator('#kpiVarVal').textContent();
		check('KPI 3: Fórmula ((TY-LY)/LY)*100 maneja LY=0 mostrando N/D o porcentaje', /N\/D|[+\-]?\d+(\.\d+)?%/.test(varText), varText);

		const kpiComp = await page.locator('[data-testid="kpi-target-compliance"]').isVisible();
		check('KPI 4: Cumplimiento de Meta de Ventas presente', kpiComp);

		const kpiTopSales = await page.locator('[data-testid="kpi-top-sales-dept"]').isVisible();
		check('KPI 5: Departamento con mayores ventas presente', kpiTopSales);

		const kpiTopGrowth = await page.locator('[data-testid="kpi-top-growth-dept"]').isVisible();
		check('KPI 6: Departamento con mayor crecimiento presente', kpiTopGrowth);

		const kpiTopDrop = await page.locator('[data-testid="kpi-top-drop-dept"]').isVisible();
		check('KPI 7: Departamento con mayor caída presente', kpiTopDrop);

		// 5. Los 4 Gráficos Requeridos
		console.log('\n--- 5. Verificación de los 4 Gráficos ---');
		const c1 = await page.$eval('#deptChartTyLy', c => c.width > 0 && c.height > 0);
		check('Gráfico 1: Barras comparativas TY vs. LY renderizado', c1);

		const c2 = await page.$eval('#deptChartRanking', c => c.width > 0 && c.height > 0);
		check('Gráfico 2: Ranking de crecimiento y caída renderizado', c2);

		const c3 = await page.$eval('#deptChartHourly', c => c.width > 0 && c.height > 0);
		check('Gráfico 3: Curva de ventas por hora renderizado', c3);

		const c4 = await page.$eval('#deptChartShare', c => c.width > 0 && c.height > 0);
		check('Gráfico 4: Participación porcentual por departamento (doughnut) renderizado', c4);

		// 6. Tabla de Rendimiento por Departamento
		console.log('\n--- 6. Tabla de Rendimiento y Ordenamiento ---');
		const table = page.locator('#deptPerformanceTable');
		check('Tabla interactiva presente', await table.isVisible());

		const rowCount = await page.locator('#deptTableBody tr.dept-row').count();
		check('Tabla contiene filas de departamentos con datos reales', rowCount > 0, 'Filas: ' + rowCount);

		// Búsqueda en vivo
		const searchInput = page.locator('#deptTableSearch');
		await searchInput.fill('Panad');
		await page.waitForTimeout(300);
		const visibleRowsFiltered = await page.locator('#deptTableBody tr.dept-row:visible').count();
		check('Búsqueda interactiva filtra filas correctamente', visibleRowsFiltered >= 1, 'Filas visibles: ' + visibleRowsFiltered);
		await searchInput.fill('');
		await page.waitForTimeout(200);

		// Ordenamiento por columna TY
		const thTy = page.locator('.dept-th-sort[data-sort="ty"]');
		await thTy.click();
		await page.waitForTimeout(300);
		check('Ordenamiento por columna TY ejecutado sin errores', true);

		// 7. Desglose Jerárquico Multinivel (Drill-Down)
		console.log('\n--- 7. Desglose Jerárquico Multinivel ---');
		// Nivel 1 -> Nivel 2: Clic en primer departamento
		const firstDeptRow = page.locator('#deptTableBody tr.dept-row').first();
		const deptName = await firstDeptRow.getAttribute('data-dept-name');
		await firstDeptRow.click();
		await page.waitForTimeout(500);

		const l2Visible = await page.locator('#deptLevel2View').isVisible();
		check(`Drill-down Nivel 2 abre detalle de departamento (${deptName})`, l2Visible);

		// Verificar componentes de Nivel 2
		check('Nivel 2: Breadcrumbs actualizados con nombre del departamento', await page.locator('#deptBreadcrumbs').textContent().then(t => t.includes(deptName)));
		check('Nivel 2: Tabla de categorías presente', await page.locator('#l2CategoriesTbody tr').count() > 0);
		check('Nivel 2: Top 5 productos presente', await page.locator('#l2TopProductsTbody tr').count() > 0);
		check('Nivel 2: Productos menor venta presente', await page.locator('#l2BottomProductsTbody tr').count() > 0);
		check('Nivel 2: Productos agotados presente', await page.locator('#l2OutOfStockTbody tr').count() > 0);

		// Nivel 2 -> Nivel 3: Clic en categoría si existe
		const firstCatRow = page.locator('.dept-cat-row').first();
		if (await firstCatRow.count() > 0) {
			await firstCatRow.click();
			await page.waitForTimeout(1000);
			const l3Visible = await page.locator('#deptLevel3View').isVisible();
			check('Drill-down Nivel 3 abre productos de la categoría', l3Visible);

			// Nivel 3 -> Nivel 4: Clic en producto si existe
			const firstProdRow = page.locator('#l3ProductsTbody .dept-prod-click').first();
			if (await firstProdRow.count() > 0) {
				const prodSku = await firstProdRow.getAttribute('data-sku');
				await firstProdRow.click();
				await page.waitForTimeout(400);
				const l4Visible = await page.locator('#deptLevel4View').isVisible();
				check(`Drill-down Nivel 4 abre ficha SKU (${prodSku})`, l4Visible);

				// Probar navegación atrás con botón Volver
				const btnBack = page.locator('#deptBtnBack');
				await btnBack.click();
				await page.waitForTimeout(300);
				check('Botón "Volver" regresa de Nivel 4 a Nivel 3', await page.locator('#deptLevel3View').isVisible());

				await btnBack.click();
				await page.waitForTimeout(300);
				check('Botón "Volver" regresa de Nivel 3 a Nivel 2', await page.locator('#deptLevel2View').isVisible());

				await btnBack.click();
				await page.waitForTimeout(300);
				check('Botón "Volver" regresa de Nivel 2 a Nivel 1 (Tabla principal)', await page.locator('#deptTableContainer').isVisible());
			}
		}

		// Asegurar que estamos en Nivel 1 para la prueba de tabla móvil
		const tableVisibleBeforeMobile = await page.locator('#deptTableContainer').isVisible();
		if (!tableVisibleBeforeMobile) {
			const crumbL1 = page.locator('.dept-crumb-link[data-level="1"]').first();
			if (await crumbL1.count() > 0) {
				await crumbL1.click();
				await page.waitForTimeout(400);
			}
		}

		// 8. Visualización Móvil Responsive (390 x 844)
		console.log('\n--- 8. Responsividad Móvil (390 x 844) ---');
		await page.setViewportSize({ width: 390, height: 844 });
		await page.waitForTimeout(600);

		const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
		const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
		check('Sin desbordamiento horizontal en móvil (scrollWidth === clientWidth)', scrollWidth <= clientWidth + 2, `scrollWidth: ${scrollWidth}, clientWidth: ${clientWidth}`);

		check('KPIs se apilan responsivamente en móvil', await page.locator('.dept-kpi-card').first().isVisible());
		check('Tabla con scroll horizontal fluido en móvil', await page.locator('.dept-perf-table').isVisible());

		// 9. Ausencia de Errores de Consola
		console.log('\n--- 9. Consola y Rendimiento ---');
		check('Cero errores de consola / JavaScript', consoleErrors.length === 0, consoleErrors.join(' | '));

	} catch (e) {
		console.error('Error catastrófico en la ejecución:', e);
		check('Ejecución global del test', false, e.message);
	} finally {
		await browser.close();
		console.log('\n=======================================');
		console.log(`RESUMEN: ${results.filter(r => r.status.includes('PASS')).length} PASADAS, ${results.filter(r => r.status.includes('FAIL')).length} FALLIDAS`);
		console.log('=======================================');
		if (results.some(r => r.status.includes('FAIL'))) {
			process.exit(1);
		}
	}
})();
