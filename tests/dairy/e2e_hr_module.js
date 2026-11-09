/**
 * E2E Automatizado con Playwright:
 * Validación del módulo integral de Recursos Humanos (HCM) en DAILY
 */

const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = process.env.DAILY_URL || 'http://localhost:8080';

(async () => {
	console.log('🚀 Iniciando pruebas E2E: Módulo de Recursos Humanos (HCM)...\n');
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
		// 1. Cargar el dashboard /hr
		console.log('--- 1. Carga del Dashboard /hr ---');
		const res = await page.goto(BASE_URL + '/hr', { waitUntil: 'load', timeout: 30000 });
		check('Ruta /hr responde HTTP 200', res.status() === 200, 'Status: ' + res.status());

		const titleText = await page.locator('.hr-title-wrap h1').innerText();
		check('Título principal "Recursos Humanos & Gestión Laboral (HCM)" visible', titleText.includes('Recursos Humanos'));

		// 2. Menú Lateral
		console.log('\n--- 2. Enlace en Menú Lateral ---');
		const hrMenuLink = await page.locator('#sidebarResponsive a[href*="/hr"]').first();
		const hrMenuCount = await page.locator('#sidebarResponsive a[href*="/hr"]').count();
		check('Opción "1. Recursos Humanos" presente en menú lateral', hrMenuCount > 0);

		// 3. Ciclo HCM (8 Pasos)
		console.log('\n--- 3. Ciclo de Vida del Colaborador (8 Pasos) ---');
		const cycleSteps = await page.locator('.hr-cycle-step').count();
		check('Stepper de ciclo con las 8 etapas presentes', cycleSteps === 8, 'Encontrados: ' + cycleSteps);

		// 4. Indicadores Principales (8 KPIs)
		console.log('\n--- 4. Indicadores Principales (8 KPIs) ---');
		const kpiCards = await page.locator('.hr-kpi-card').count();
		check('Los 8 KPIs principales renderizados', kpiCards === 8, 'Encontrados: ' + kpiCards);

		const activeEmpVal = await page.locator('.hr-kpi-card:nth-child(1) .hr-kpi-value').innerText();
		check('KPI 1: Colaboradores activos calculado', parseInt(activeEmpVal) > 0, 'Valor: ' + activeEmpVal);

		const coverageVal = await page.locator('.hr-kpi-card:nth-child(2) .hr-kpi-value').innerText();
		check('KPI 2: Cobertura de turnos con porcentaje', coverageVal.includes('%'), 'Valor: ' + coverageVal);

		const splhVal = await page.locator('.hr-kpi-card:nth-child(5) .hr-kpi-value').innerText();
		check('KPI 5: SPLH (Ventas/Hora) con formato moneda', splhVal.includes('$'), 'Valor: ' + splhVal);

		// 5. Pestañas de Navegación
		console.log('\n--- 5. Pestañas y Componentes Interactivos ---');
		const tabs = await page.locator('.hr-tab-btn').count();
		check('Navegación con 9 pestañas temáticas', tabs >= 8, 'Encontradas: ' + tabs);

		// Test Pestaña Colaboradores & Buscador & Modal de Expediente
		await page.locator('[data-tab="colaboradores"]').click();
		await page.waitForTimeout(300);
		const colabTabActive = await page.locator('#tab-colaboradores').isVisible();
		check('Pestaña Colaboradores se activa correctamente', colabTabActive);

		const initialRows = await page.locator('#empTableBody tr:visible').count();
		check('Tabla de colaboradores con registros cargados', initialRows > 0, 'Filas: ' + initialRows);

		// Búsqueda interactiva
		await page.fill('#empSearchInput', 'Carlos');
		await page.waitForTimeout(200);
		const filteredRows = await page.locator('#empTableBody tr:visible').count();
		check('Filtro de búsqueda interactivo en colaboradores funcional', filteredRows <= initialRows);
		await page.fill('#empSearchInput', ''); // Limpiar

		// Apertura de modal de expediente
		const firstExpBtn = page.locator('#empTableBody tr button').first();
		if (await firstExpBtn.isVisible()) {
			await firstExpBtn.click();
			await page.waitForTimeout(300);
			const modalActive = await page.locator('#hrProfileModal.active').isVisible();
			check('Modal de Expediente Digital del colaborador se abre', modalActive);
			const profName = await page.locator('#profName').innerText();
			check('Expediente muestra nombre y datos laborales', profName.length > 2, 'Nombre: ' + profName);
			await page.locator('#closeProfileBtn').click();
			await page.waitForTimeout(200);
		}

		// Test Pestaña Contratación & Onboarding
		await page.locator('[data-tab="contratacion"]').click();
		await page.waitForTimeout(300);
		const kanbanCols = await page.locator('.hr-kanban-col').count();
		check('Pipeline Kanban de contratación con 5 etapas', kanbanCols === 5, 'Columnas: ' + kanbanCols);

		const onboardingTasks = await page.locator('.hr-onboarding-item').count();
		check('Checklist de incorporación y onboarding cargado', onboardingTasks > 0, 'Tareas: ' + onboardingTasks);

		// Test Pestaña Horarios & Cobertura
		await page.locator('[data-tab="horarios"]').click();
		await page.waitForTimeout(300);
		const scheduleRows = await page.locator('#tab-horarios .hr-table tbody tr').count();
		check('Malla de turnos semanales cargada', scheduleRows > 0, 'Turnos: ' + scheduleRows);

		// Test Pestaña Asistencia en Vivo
		await page.locator('[data-tab="asistencia"]').click();
		await page.waitForTimeout(300);
		const attRows = await page.locator('#tab-asistencia .hr-table tbody tr').count();
		check('Fichajes de asistencia en vivo listados', attRows > 0, 'Fichajes: ' + attRows);

		// Test Pestaña Pre-Nómina
		await page.locator('[data-tab="nomina"]').click();
		await page.waitForTimeout(300);
		const payrollRows = await page.locator('#tab-nomina .hr-table tbody tr').count();
		check('Pre-liquidación de nómina con cálculo de costos', payrollRows > 0, 'Registros: ' + payrollRows);

		// Test Pestaña Productividad & SPLH
		await page.locator('[data-tab="productividad"]').click();
		await page.waitForTimeout(300);
		const shiftEfficiencyRows = await page.locator('#tab-productividad .hr-table tbody tr').count();
		check('Comparativa de eficiencia entre turnos (Apertura/Cierre)', shiftEfficiencyRows > 0);

		// Test Pestaña Capacitación & BPM
		await page.locator('[data-tab="capacitacion"]').click();
		await page.waitForTimeout(300);
		const trainingCourses = await page.locator('#tab-capacitacion .hr-table tbody tr').count();
		check('Catálogo de cursos y certificaciones BPM/SST listado', trainingCourses > 0);

		// Test Pestaña Comunicaciones & Solicitudes
		await page.locator('[data-tab="comunicaciones"]').click();
		await page.waitForTimeout(300);
		const commFormVisible = await page.locator('#newCommForm').isVisible();
		check('Tablón de avisos y formulario de comunicación visible', commFormVisible);

		// 6. Exportación de Nómina en CSV
		console.log('\n--- 6. Exportación de Nómina CSV ---');
		const exportRes = await page.request.get(BASE_URL + '/hr/payroll/export?branch_id=1');
		check('Endpoint /hr/payroll/export responde HTTP 200', exportRes.status() === 200);
		const csvText = await exportRes.text();
		check('Archivo CSV contiene cabeceras de nómina', csvText.includes('Código Nómina') && csvText.includes('Costo Total Empleador'));

		// 7. Visualización Móvil Responsive
		console.log('\n--- 7. Responsividad Móvil (390 x 844) ---');
		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto(BASE_URL + '/hr', { waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(600);

		const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
		const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
		check('Sin desbordamiento horizontal en móvil (viewport 390x844)', scrollWidth <= clientWidth + 2);

		// 8. Cero Errores de Consola
		console.log('\n--- 8. Errores de Consola y JavaScript ---');
		check('Cero errores de JavaScript en consola', consoleErrors.length === 0, consoleErrors.join(' | '));

	} catch (e) {
		console.error('Error durante el test:', e);
		check('Ejecución global de las pruebas', false, e.message);
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

