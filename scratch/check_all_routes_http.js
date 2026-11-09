const http = require('http');

const urls = [
  '/today',
  '/alerts',
  '/kpis',
  '/sales',
  '/sales?tab=detalle',
  '/sales?tab=rentabilidad',
  '/pos',
  '/pos/returns',
  '/customers',
  '/stockoverview',
  '/inventory/stockouts',
  '/inventory-control',
  '/kardex',
  '/inventory',
  '/reconciliation',
  '/transfers',
  '/warehouse',
  '/scan',
  '/locations/map',
  '/branches',
  '/traceability',
  '/orders',
  '/dispatches',
  '/returns',
  '/recalls',
  '/waste',
  '/waste/report',
  '/waste/approvals',
  '/waste/reasons',
  '/loss-prevention',
  '/loss-prevention/devices',
  '/reports/loss-executive',
  '/hr',
  '/operations/scheduling',
  '/operations/attendance',
  '/workforce',
  '/settings',
  '/users',
  '/data-import',
  '/locations/labels',
  '/audit-log',
  '/system-map',
  '/subscription',
  '/mrp',
  '/suppliers',
  '/cost-centers',
  '/reports',
  '/m'
];

async function checkUrl(url) {
  return new Promise((resolve) => {
    const t0 = Date.now();
    const req = http.get(`http://localhost:8080${url}`, {
      agent: false,
      headers: {
        'Connection': 'close',
        'User-Agent': 'DailyAudit/1.0'
      }
    }, (res) => {
      let bytes = 0;
      res.on('data', chunk => bytes += chunk.length);
      res.on('end', () => {
        resolve({ url, status: res.statusCode, time: Date.now() - t0, bytes, error: null });
      });
    });
    req.on('error', (err) => {
      resolve({ url, status: 0, time: Date.now() - t0, bytes: 0, error: err.message });
    });
    req.setTimeout(25000, () => {
      req.destroy();
      resolve({ url, status: 0, time: Date.now() - t0, bytes: 0, error: 'TIMEOUT (>25s)' });
    });
  });
}

async function main() {
  console.log(`Auditing HTTP response for ${urls.length} dashboards with connection: close...\n`);
  let passed = 0;
  let failed = 0;
  
  for (let i = 0; i < urls.length; i++) {
    const u = urls[i];
    const res = await checkUrl(u);
    const sizeKb = Math.round(res.bytes / 1024);
    if (res.status === 200) {
      console.log(`[${i+1}/${urls.length}] ✓ ${u} -> HTTP 200 [${res.time}ms, ${sizeKb} KB]`);
      passed++;
    } else {
      console.log(`[${i+1}/${urls.length}] ✗ ${u} -> HTTP ${res.status} [${res.time}ms, ${sizeKb} KB] (${res.error})`);
      failed++;
    }
    // Small 100ms pause between requests to let Windows socket cycle clean
    await new Promise(r => setTimeout(r, 100));
  }
  
  console.log(`\nAudit completed: ${passed}/${urls.length} passed, ${failed} failed.`);
}

main();
