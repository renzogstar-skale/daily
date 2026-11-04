const { chromium } = require('playwright');

(async () => {
	const browser = await chromium.launch({ channel: 'msedge', headless: true });
	const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
	const errors = [];
	page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
	page.on('pageerror', err => errors.push(err.message));

	await page.goto('http://localhost:8080/kpis', { waitUntil: 'networkidle' });

	// 1. KPI Cards count
	const kpisCount = await page.locator('.ep-kpi-card').count();
	console.log('KPI Cards count:', kpisCount);

	// 2. Map pins count
	const pinsCount = await page.locator('.ep-branch-pin').count();
	console.log('Map pins count:', pinsCount);

	// 3. Branch rows count
	const branchRowsCount = await page.locator('.ep-branch-item-row').count();
	console.log('Branch rows count:', branchRowsCount);

	// 4. Sales chart canvas exists
	const chartExists = await page.locator('#execVentasTrendChart').count();
	console.log('Sales chart exists:', chartExists === 1);

	// 5. Top departments count
	const deptsCount = await page.locator('.ep-dept-row').count();
	console.log('Top departments count:', deptsCount);

	// 6. Priority alerts count
	const alertsCount = await page.locator('.ep-alert-item-row').count();
	console.log('Priority alerts count:', alertsCount);

	// 7. Urgent actions count
	const urgentCount = await page.locator('.ep-urgent-action-row').count();
	console.log('Urgent actions count:', urgentCount);

	// 8. Process cards count
	const processCount = await page.locator('.ep-process-mini-card').count();
	console.log('Process cards count:', processCount);

	// 9. Quick buttons count
	const quickCount = await page.locator('.ep-quick-btn-card').count();
	console.log('Quick buttons count:', quickCount);

	// Check interactive click on urgent action
	await page.click('.ep-urgent-action-row:first-child');
	await page.waitForTimeout(400);
	const modalVisible = await page.locator('#modalExecutiveUrgentAction').isVisible();
	console.log('Urgent action modal visible on click:', modalVisible);

	await page.click('#modalExecutiveUrgentAction .close');
	await page.waitForTimeout(400);

	console.log('Console errors:', errors.length);

	await browser.close();
	const allOk = (kpisCount === 6 && pinsCount === 10 && branchRowsCount === 10 && chartExists === 1 && deptsCount === 5 && alertsCount === 4 && urgentCount === 4 && processCount === 7 && quickCount === 4 && errors.length === 0);
	console.log('All assertions passed:', allOk);
	process.exit(allOk ? 0 : 1);
})();

