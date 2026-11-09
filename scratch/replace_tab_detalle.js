const fs = require('fs');
const path = 'views/sales_history.blade.php';
let content = fs.readFileSync(path, 'utf8');

const startStr = '<!-- PESTAÑA 3: DETALLE DE TRANSACCIONES Y REGISTROS                -->';
const endStr = '<!-- MODAL DETALLE DE TICKET -->';

const startIndex = content.indexOf(startStr);
const endIndex = content.indexOf(endStr);

if (startIndex === -1 || endIndex === -1) {
  console.error('Markers not found', { startIndex, endIndex });
  process.exit(1);
}

const replacement = "<!-- ============================================================== -->\n<!-- PESTAÑA 3: CENTRO DE TRAZABILIDAD Y AUDITORÍA (HISTORIAL DE VENTAS) -->\n<!-- ============================================================== -->\n<div class=\"tab-pane fade @if($activeTab === 'detalle') show active @endif\" id=\"tab-detalle\" role=\"tabpanel\">\n\t@include('components.sales.sales_history_dashboard')\n</div>\n\n\n";

const before = content.slice(0, startIndex);
const after = content.slice(endIndex);

content = before + replacement + after;
fs.writeFileSync(path, content, 'utf8');
console.log('Successfully replaced tab-detalle content');

