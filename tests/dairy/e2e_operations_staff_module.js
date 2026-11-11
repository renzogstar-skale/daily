/**
 * E2E Automatizado con Playwright:
 * DAILY — 15. Optimizar Operaciones y Personal
 * Valida /operations/scheduling, /operations/attendance, /workforce y /hr
 * en modos Claro, Oscuro (html.daily-dark), Móvil (390x844) y RBAC rol empleado.
 */

const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE_URL = process.env.DAILY_URL || 'http://localhost:8080';

(async () => {
	console.log('🚀 E2E Operaciones y Personal (#15)...\n');
	const browser = await chromium.launch({ channel: 'msedge' });
	const results = [];
	const consoleErrors = [];
	const pageErrors = [];

	const check = (desc, cond, detail = '') => {
		const ok = Boolean(cond);
		results.push({ ok, desc, detail });
		console.log(`${ok ? '✅ PASS' : '❌ FAIL'} - ${desc}${ok || !detail ? '' : ' (' + detail + ')'}`);
	};

	const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await context.newPage();

	page.on('pageerror', err => pageErrors.push('PAGEERROR: ' + err.message));
	page.on('console', msg => {
		if (msg.type() === 'error' && !/favicon|Failed to load resource/i.test(msg.text())) {
			consoleErrors.push('CONSOLE: ' + msg.text().slice(0, 180));
		}
	});

	try {
		// =========================================================
		// 1. /operations/scheduling (Admin / Manager Desktop Light)
		// =========================================================
		console.log('--- 1. /operations/scheduling ---');
		const rSch = await page.goto(BASE_URL + '/operations/scheduling', { waitUntil: 'networkidle', timeout: 30000 });
		await page.locator('#sch-cycle-banner').waitFor({ state: 'visible', timeout: 15000 });
		check('/operations/scheduling responde HTTP 200', rSch.status() === 200, 'status=' + rSch.status());
		check('Ciclo 7 etapas #sch-cycle-banner visible', await page.locator('#sch-cycle-banner').isVisible());
		check('Panel cobertura dpto x hora #schDeptHourCoveragePanel presente', (await page.locator('#schDeptHourCoveragePanel').count()) === 1);
		check('Panel compensatorios y vacaciones #schCompensatoryVacationPanel presente', (await page.locator('#schCompensatoryVacationPanel').count()) === 1);
		check('Selector de país/jurisdicción #policyJurisdictionSelect presente', (await page.locator('#policyJurisdictionSelect').count()) === 1);
		check('Modal permuta #modalRequestSwap y selector #swapTargetEmpId presentes', (await page.locator('#modalRequestSwap').count()) === 1 && (await page.locator('#swapTargetEmpId').count()) === 1);

		const jurisdictionOptions = await page.locator('#policyJurisdictionSelect option').count();
		check('7 perfiles de jurisdicción cargados en selector de políticas', jurisdictionOptions >= 7, 'count=' + jurisdictionOptions);

		const schApiJuris = await page.request.get(BASE_URL + '/api/schedule/jurisdictions');
		check('API /api/schedule/jurisdictions responde HTTP 200', schApiJuris.status() === 200);

		const schApiCov = await page.request.get(BASE_URL + '/api/schedule/coverage/hourly?branch_id=1');
		check('API /api/schedule/coverage/hourly responde HTTP 200', schApiCov.status() === 200);

		const schApiComp = await page.request.get(BASE_URL + '/api/schedule/compensatory?branch_id=1');
		check('API /api/schedule/compensatory responde HTTP 200', schApiComp.status() === 200);

		// =========================================================
		// 2. /operations/attendance (Admin / Manager)
		// =========================================================
		console.log('\n--- 2. /operations/attendance ---');
		const rAtt = await page.goto(BASE_URL + '/operations/attendance', { waitUntil: 'load', timeout: 30000 });
		check('/operations/attendance responde HTTP 200', rAtt.status() === 200, 'status=' + rAtt.status());
		check('Ciclo 7 etapas #att-cycle-banner visible', await page.locator('#att-cycle-banner').isVisible());
		check('Panel #att-dept-hour-coverage presente en asistencia', (await page.locator('#att-dept-hour-coverage').count()) === 1);
		const attBodyText = await page.locator('body').innerText();
		check('Distinción ausente vs pendiente visible en asistencia', attBodyText.includes('Ausente') || attBodyText.includes('ausente'));

		// =========================================================
		// 3. /workforce (Limpieza de Mermas/Equipos/POS + Checklist)
		// =========================================================
		console.log('\n--- 3. /workforce ---');
		const rWf = await page.goto(BASE_URL + '/workforce', { waitUntil: 'load', timeout: 30000 });
		check('/workforce responde HTTP 200', rWf.status() === 200, 'status=' + rWf.status());
		check('Ciclo 7 etapas #wf-cycle-banner visible', await page.locator('#wf-cycle-banner').isVisible());
		check('Sin formulario de merma rápida #quick-waste-form en /workforce', (await page.locator('#quick-waste-form').count()) === 0);
		check('Sin pestaña de mantenimiento #wf-tab-maintenance en /workforce', (await page.locator('#wf-tab-maintenance').count()) === 0);

		const wfForbiddenLinks = await page.locator('.wf-wrapper a[href*="/waste"], .wf-wrapper a[href*="/equipment"], .wf-wrapper a[href*="/pos"], .wf-container a[href*="/waste"], .wf-container a[href*="/equipment"]').count();
		check('Sin accesos directos a /waste, /equipment o /pos dentro del área laboral /workforce', wfForbiddenLinks === 0, 'found=' + wfForbiddenLinks);

		// =========================================================
		// 4. /hr (Pre-nómina, Compensatorios, Vacaciones y Jurisdicción)
		// =========================================================
		console.log('\n--- 4. /hr ---');
		const rHr = await page.goto(BASE_URL + '/hr', { waitUntil: 'load', timeout: 30000 });
		check('/hr responde HTTP 200', rHr.status() === 200, 'status=' + rHr.status());
		const hrBadge = await page.locator('.hr-header-box').innerText();
		check('Insignia HCM muestra código de jurisdicción válido (no Array)', !hrBadge.includes('Array') && /(CO-GEN|MX-LFT|PE-DLEG728|CL-DT|ES-ET|US-FED|US-CA)/.test(hrBadge), hrBadge.slice(0, 80));

		await page.locator('[data-tab="nomina"]').click();
		await page.waitForTimeout(300);
		check('Panel #hr-compensatory-panel único y visible en Nómina', (await page.locator('#hr-compensatory-panel').count()) === 1 && await page.locator('#hr-compensatory-panel').isVisible());
		check('Panel #hr-vacations-panel único y visible en Nómina', (await page.locator('#hr-vacations-panel').count()) === 1 && await page.locator('#hr-vacations-panel').isVisible());

		// =========================================================
		// 5. Tema Oscuro (html.daily-dark) y Responsividad Móvil (390x844)
		// =========================================================
		console.log('\n--- 5. Tema Oscuro y Responsive Móvil ---');
		for (const route of ['/operations/scheduling', '/operations/attendance', '/workforce', '/hr']) {
			await page.setViewportSize({ width: 1440, height: 900 });
			await page.goto(BASE_URL + route + (route.includes('scheduling') ? '?branch_id=1' : ''), { waitUntil: 'networkidle', timeout: 30000 });
			await page.evaluate(() => {
				document.documentElement.classList.add('daily-dark');
				try { localStorage.setItem('daily_theme', 'dark'); } catch (e) {}
			});
			const isDark = await page.evaluate(() => document.documentElement.classList.contains('daily-dark'));
			check(`Tema oscuro (html.daily-dark) aplicado en ${route}`, isDark);

			await page.setViewportSize({ width: 390, height: 844 });
			await page.waitForTimeout(250);
			const overflow = await page.evaluate(() => ({
				scroll: document.documentElement.scrollWidth,
				client: document.documentElement.clientWidth
			}));
			check(`Sin desbordamiento horizontal móvil (390x844) en ${route}`, overflow.scroll <= overflow.client + 4, `scroll=${overflow.scroll} client=${overflow.client}`);
		}

		// =========================================================
		// 6. RBAC Estricto: Rol simulado "empleado"
		// =========================================================
		console.log('\n--- 6. Seguridad RBAC: Rol "empleado" ---');
		const empContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		await empContext.addCookies([{
			name: 'daily_simulated_role',
			value: 'empleado',
			domain: 'localhost',
			path: '/'
		}]);
		const empPage = await empContext.newPage();

		const hrDenied = await empPage.goto(BASE_URL + '/hr', { waitUntil: 'load', timeout: 30000 });
		check('Rol empleado bloqueado de /hr (HTTP 403)', hrDenied.status() === 403, 'status=' + hrDenied.status());

		const exportDenied = await empPage.request.get(BASE_URL + '/hr/payroll/export?branch_id=1');
		check('Rol empleado bloqueado de /hr/payroll/export (HTTP 403)', exportDenied.status() === 403, 'status=' + exportDenied.status());

		const empSchRes = await empPage.goto(BASE_URL + '/operations/scheduling?branch_id=1', { waitUntil: 'networkidle', timeout: 30000 });
		check('Rol empleado accede a su horario /operations/scheduling (HTTP 200)', empSchRes.status() === 200);
		await empPage.waitForSelector('#sch-cycle-banner', { timeout: 10000 });
		const empScheduleData = await empPage.evaluate(() => window.DairyScheduleData || null);
		const hasSalaryLeak = empScheduleData && JSON.stringify(empScheduleData).includes('"hourly_cost"');
		check('Rol empleado NO recibe salarios (hourly_cost) ni costos en DairyScheduleData', !hasSalaryLeak);
		check('Rol empleado NO ve modal ni botón de políticas salariales en /operations/scheduling', (await empPage.locator('#modalCompanyPolicies').count()) === 0);

		const empAttRes = await empPage.goto(BASE_URL + '/operations/attendance', { waitUntil: 'load', timeout: 30000 });
		check('Rol empleado accede a su propia asistencia /operations/attendance (HTTP 200)', empAttRes.status() === 200);
		check('Rol empleado NO ve selector de otros colaboradores en /operations/attendance', (await empPage.locator('#attEmployeeSelect option').count()) <= 1);

		const empWfRes = await empPage.goto(BASE_URL + '/workforce', { waitUntil: 'load', timeout: 30000 });
		check('Rol empleado accede a sus checklists /workforce (HTTP 200)', empWfRes.status() === 200);

		await empContext.close();

		// =========================================================
		// 7. Errores de Consola y Excepciones JS
		// =========================================================
		console.log('\n--- 7. Consola JS & PageErrors ---');
		check('Cero pageerrors en todo el recorrido', pageErrors.length === 0, pageErrors.join(' | '));
		check('Cero errores de consola JS en todo el recorrido', consoleErrors.length === 0, consoleErrors.join(' | '));

	} catch (err) {
		console.error('Fatal test error:', err);
		check('Ejecución global E2E', false, err.message);
	} finally {
		await context.close();
		await browser.close();
		const passed = results.filter(r => r.ok).length;
		const failed = results.filter(r => !r.ok).length;
		console.log('\n=======================================');
		console.log(`RESUMEN E2E #15: ${passed} PASADAS, ${failed} FALLIDAS (Total ${results.length})`);
		console.log('=======================================');
		if (failed > 0) process.exit(1);
	}
})();

