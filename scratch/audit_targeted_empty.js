const { chromium } = require('C:/Users/renzo/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    const routes = [
        { path: '/today', name: 'HOY' },
        { path: '/alerts', name: 'Alertas' },
        { path: '/kpis', name: 'Panel Ejecutivo' },
        { path: '/mrp', name: 'MRP' },
        { path: '/suppliers', name: 'Proveedores' },
        { path: '/branches', name: 'Sucursales' },
        { path: '/merchandise', name: 'Mercancías' },
        { path: '/stockoverview', name: 'Stock' },
        { path: '/scan', name: 'Escáner' },
        { path: '/transfers', name: 'Traslados' },
        { path: '/kardex', name: 'Kardex' },
        { path: '/inventory-control', name: 'Control Inventario' },
        { path: '/inventory', name: 'Conteos Físicos' },
        { path: '/reconciliation', name: 'Conciliación' },
        { path: '/sales', name: 'Ventas' },
        { path: '/pos', name: 'POS' },
        { path: '/dispatch', name: 'Despachos' },
        { path: '/returns', name: 'Devoluciones' },
        { path: '/schedule', name: 'Horarios' },
        { path: '/tasks', name: 'Tareas' },
        { path: '/quality', name: 'Calidad' },
        { path: '/waste', name: 'Mermas' },
        { path: '/loss-prevention', name: 'Prevención' },
        { path: '/devices', name: 'Dispositivos' },
        { path: '/reports', name: 'Reportes' },
        { path: '/admin/audit-log', name: 'Auditoría' }
    ];

    console.log("=== COMPREHENSIVE EMPTY STATE AUDIT ===");
    for (const r of routes) {
        try {
            await page.goto('http://localhost:8080' + r.path, { waitUntil: 'networkidle', timeout: 15000 });
            
            const results = await page.evaluate(() => {
                const issues = [];
                // Check all tables
                document.querySelectorAll('table').forEach((t, idx) => {
                    if (t.closest('.dataTables_scrollHead')) {
                        return; // Ignore cloned DataTables fixed header
                    }
                    const tb = t.querySelector('tbody');
                    const title = t.closest('.card, .lp-table-card, .tab-pane, section')?.querySelector('h2, h3, h4, h5, h6, .card-title, .lp-table-title-group')?.innerText.trim() || `Tabla #${idx}`;
                    if (!tb || tb.querySelectorAll('tr').length === 0) {
                        issues.push(`Tabla vacía (0 filas): "${title}"`);
                    } else {
                        const trs = tb.querySelectorAll('tr');
                        if (trs.length === 1 && (trs[0].innerText.toLowerCase().includes('sin ') || trs[0].innerText.toLowerCase().includes('no hay') || trs[0].innerText.toLowerCase().includes('no se encontraron') || trs[0].innerText.toLowerCase().includes('vacio') || trs[0].innerText.toLowerCase().includes('vacío'))) {
                            issues.push(`Tabla con mensaje vacío: "${title}" -> "${trs[0].innerText.trim()}"`);
                        }
                    }
                });

                // Check tab buttons with (0)
                document.querySelectorAll('button, a.nav-link, .nav-item a, .lp-tab-item, .sch-view-pill').forEach(b => {
                    const txt = b.innerText.trim();
                    if (txt.includes('(0)') || txt.includes('( 0 )') || txt.endsWith(' 0')) {
                        issues.push(`Pestaña/Pill con 0: "${txt}"`);
                    }
                });

                // Check card containers or panes that say "No hay..." or "Sin..."
                document.querySelectorAll('.tab-pane, .card-body, .lp-tab-pane').forEach(p => {
                    if (p.children.length <= 1 && p.innerText.trim().length < 80 && (p.innerText.toLowerCase().includes('no hay') || p.innerText.toLowerCase().includes('sin registros') || p.innerText.toLowerCase().includes('sin datos'))) {
                        issues.push(`Panel con texto vacío: "${p.innerText.trim()}"`);
                    }
                });

                return issues;
            });

            if (results.length > 0) {
                console.log(`\n[!] ${r.name} (${r.path}):`);
                results.forEach(i => console.log(`    - ${i}`));
            } else {
                console.log(`[OK] ${r.name} (${r.path})`);
            }
        } catch (e) {
            console.log(`[ERROR] ${r.name} (${r.path}): ${e.message}`);
        }
    }

    await browser.close();
})();
