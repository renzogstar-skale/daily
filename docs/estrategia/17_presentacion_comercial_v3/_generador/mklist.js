// Lista de capturas v3. Uso: node mklist.js -> list.json
const L = []; const a = (d, f, u, o = {}) => L.push({ d, f, u, ...o });
a('01_ejecutivo', 'today', '/today', { w: 5000 }); a('01_ejecutivo', 'kpis', '/kpis');
for (const t of ['centro-control', 'causales', 'investigaciones', 'fefo', 'conteos', 'robo']) a('02_mermas', 'waste_' + t, '/waste', { click: `a[href="#tab-${t}"]` });
a('02_mermas', 'waste_approvals', '/waste/approvals'); a('02_mermas', 'waste_report', '/waste/report'); a('02_mermas', 'loss_executive', '/reports/loss-executive', { maxh: 2400 });
a('03_inventario', 'stockoverview', '/stockoverview'); a('03_inventario', 'kardex', '/kardex'); a('03_inventario', 'kardex_producto', '/kardex?product_id=9001');
a('03_inventario', 'conciliacion', '/reconciliation?product_id=9001'); a('03_inventario', 'conciliacion_ficha', '/reconciliation?product_id=9001', { click: '.rc-btn-callout-investigate', vp: 1 });
a('03_inventario', 'location_map', '/locations/map'); a('03_inventario', 'transfers', '/transfers'); a('03_inventario', 'scan', '/scan'); a('03_inventario', 'inventory', '/inventory'); a('03_inventario', 'inventory_control', '/inventory-control'); a('03_inventario', 'traceability', '/traceability');
a('04_calidad', 'quality', '/quality'); a('04_calidad', 'quality_cold', '/quality', { click: 'a[href="#tab-cold"]' }); a('04_calidad', 'recalls', '/recalls');
a('05_ventas', 'sales', '/sales'); a('05_ventas', 'sales_analisis', '/sales', { click: '#pill-analisis' }); a('05_ventas', 'pos', '/pos'); a('05_ventas', 'reports', '/reports');
a('06_personal', 'scheduling', '/operations/scheduling'); a('06_personal', 'scheduling_mensual', '/operations/scheduling', { click: '#viewPillMonthly' }); a('06_personal', 'attendance', '/operations/attendance'); a('06_personal', 'workforce', '/workforce', { maxh: 2400 });
a('07_multisucursal', 'branches', '/branches', { maxh: 2400 });
a('08_alertas', 'alerts', '/alerts');
a('09_compras', 'mrp', '/mrp', { maxh: 2400 }); a('09_compras', 'suppliers', '/suppliers'); a('09_compras', 'ai_advisor', '/ai-advisor');
a('11_prevencion', 'lp', '/loss-prevention'); a('11_prevencion', 'lp_anomalias', '/loss-prevention', { click: '#tab-btn-anomalias' }); a('11_prevencion', 'lp_custodia', '/loss-prevention', { click: '#tab-btn-custodia-imei' });
a('11_prevencion', 'lp_counts', '/loss-prevention/counts'); a('11_prevencion', 'lp_devices', '/loss-prevention/devices', { maxh: 2400 });
a('12_procesos', 'process_flow', '/process-flow', { maxh: 2600 });
for (const h of ['home', 'tasks', 'scan', 'control', 'notifications', 'inv', 'waste', 'team', 'sales']) a('10_movil', 'm_' + h, '/m#/' + h, { m: 1, vp: 1, w: 3500 });
require('fs').writeFileSync(__dirname + '/list.json', JSON.stringify(L)); console.log(L.length);
