<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== SEEDING ENTERPRISE RETAIL DATA ===\n";

// 1. DRAFT & TRANSIT PROCUREMENT ORDERS
// Set 4 orders to 'draft' so "Aprobar Borradores (4)" is populated and actionable
$db->query("UPDATE procurement_orders SET status = 'draft' WHERE id IN (1, 2, 3, 4)");
echo "[OK] Updated 4 procurement orders to 'draft'\n";

// Set 4 orders to 'sent' (in transit) with expected delivery in next 2-3 days
$db->query("UPDATE procurement_orders SET status = 'sent', expected_delivery_date = DATE('now', '+2 days') WHERE id IN (5, 6, 7, 8)");
echo "[OK] Updated 4 procurement orders to 'sent' (in transit)\n";

// 2. SUPPLIER INVOICES (Conciliación 3-Vías SAP MIRO / Retail)
// Clean existing supplier invoices if any
$db->query("DELETE FROM supplier_invoice_items");
$db->query("DELETE FROM supplier_invoices");

$invoicesToInsert = [
    [
        'invoice_code' => 'FAC-2026-00101',
        'supplier_id' => 1,
        'order_id' => 7,
        'branch_id' => 2,
        'invoice_number' => 'FE-9920141',
        'invoice_date' => date('Y-m-d', strtotime('-2 days')),
        'subtotal' => 433.45,
        'tax_amount' => 82.35,
        'total' => 515.80,
        'amount_at_risk' => 0.00,
        'status' => 'matched',
        'notes' => 'Factura electrónica recibida y cotejada 100% contra OC y remisión de muelle.'
    ],
    [
        'invoice_code' => 'FAC-2026-00102',
        'supplier_id' => 3,
        'order_id' => 8,
        'branch_id' => 2,
        'invoice_number' => 'FAC-ALP-88421',
        'invoice_date' => date('Y-m-d', strtotime('-1 days')),
        'subtotal' => 160.67,
        'tax_amount' => 30.53,
        'total' => 191.20,
        'amount_at_risk' => 0.00,
        'status' => 'matched',
        'notes' => 'Lácteos y refrigerados entregados con cadena de frío certificada.'
    ],
    [
        'invoice_code' => 'FAC-2026-00103',
        'supplier_id' => 4,
        'order_id' => 9,
        'branch_id' => 2,
        'invoice_number' => 'INV-COL-33120',
        'invoice_date' => date('Y-m-d'),
        'subtotal' => 134.96,
        'tax_amount' => 25.64,
        'total' => 160.60,
        'amount_at_risk' => 12.50,
        'status' => 'discrepancy',
        'notes' => 'Diferencia en 2 unidades de bebidas no recibidas en muelle. Pendiente nota crédito.'
    ],
    [
        'invoice_code' => 'FAC-2026-00104',
        'supplier_id' => 2,
        'order_id' => 12,
        'branch_id' => 2,
        'invoice_number' => 'FE-SAM-00441',
        'invoice_date' => date('Y-m-d'),
        'subtotal' => 17.31,
        'tax_amount' => 3.29,
        'total' => 20.60,
        'amount_at_risk' => 0.00,
        'status' => 'approved_with_difference',
        'notes' => 'Aprobada por Gerencia de Operaciones con ajuste de flete autorizado.'
    ],
    [
        'invoice_code' => 'FAC-2026-00105',
        'supplier_id' => 6,
        'order_id' => 13,
        'branch_id' => 2,
        'invoice_number' => 'FE-KMB-77192',
        'invoice_date' => date('Y-m-d'),
        'subtotal' => 8.99,
        'tax_amount' => 1.71,
        'total' => 10.70,
        'amount_at_risk' => 0.00,
        'status' => 'matched',
        'notes' => 'Suministros de aseo y papel institucional conforme a recepción.'
    ]
];

$stmtInv = $db->prepare("INSERT INTO supplier_invoices (invoice_code, supplier_id, order_id, branch_id, invoice_number, invoice_date, subtotal, tax_amount, total, amount_at_risk, status, notes, created_by_user_id, created_timestamp) VALUES (:invoice_code, :supplier_id, :order_id, :branch_id, :invoice_number, :invoice_date, :subtotal, :tax_amount, :total, :amount_at_risk, :status, :notes, 1, datetime('now', 'localtime'))");

$stmtItem = $db->prepare("INSERT INTO supplier_invoice_items (invoice_id, order_item_id, product_id, ordered_amount, received_amount, previously_invoiced, invoiced_amount, order_unit_price, invoice_unit_price, qty_difference, price_difference_pct, amount_at_risk, issues) VALUES (:invoice_id, :order_item_id, :product_id, :ordered_amount, :received_amount, :previously_invoiced, :invoiced_amount, :order_unit_price, :invoice_unit_price, :qty_difference, :price_difference_pct, :amount_at_risk, :issues)");

foreach ($invoicesToInsert as $invData) {
    $stmtInv->execute($invData);
    $invId = $db->lastInsertId();

    // Query order items for this order
    $oItems = $db->query("SELECT * FROM procurement_order_items WHERE order_id = " . $invData['order_id'])->fetchAll(PDO::FETCH_ASSOC);
    foreach ($oItems as $oi) {
        $stmtItem->execute([
            ':invoice_id' => $invId,
            ':order_item_id' => $oi['id'],
            ':product_id' => $oi['product_id'],
            ':ordered_amount' => $oi['approved_amount'] ?: 10,
            ':received_amount' => $oi['received_amount'] ?: 10,
            ':previously_invoiced' => 0,
            ':invoiced_amount' => $oi['received_amount'] ?: 10,
            ':order_unit_price' => $oi['unit_price'] ?: 5.0,
            ':invoice_unit_price' => $oi['unit_price'] ?: 5.0,
            ':qty_difference' => 0,
            ':price_difference_pct' => 0,
            ':amount_at_risk' => 0,
            ':issues' => null
        ]);
    }
}
echo "[OK] Inserted 5 supplier invoices with items\n";

// 3. CANCELLED SALES / RETURNS ON TODAY/RECENT DATES
$recentSales = $db->query("SELECT id, sale_number FROM sales WHERE status = 'completed' ORDER BY created_timestamp DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$returnNotes = [
    'Devolución por garantía técnica - Falla en encendido (Alkosto Servicio)',
    'Cambio de talla y referencia en prendas de vestir',
    'Desistimiento de compra en caja antes del despacho',
    'Devolución autorizada por Supervisor - Producto no conforme',
    'Reintegro de dinero a cliente por duplicidad de cobro',
    'Retorno por paquete sellado en devolución dentro de 5 días hábiles'
];

$idx = 0;
foreach ($recentSales as $s) {
    $note = $returnNotes[$idx % count($returnNotes)];
    $db->query("UPDATE sales SET status = 'cancelled', notes = " . $db->quote($note) . " WHERE id = " . $s['id']);
    $idx++;
}
echo "[OK] Updated 6 recent sales to 'cancelled' with realistic retail notes\n";

// 4. WORKFORCE SHIFT AUDIT LOG (Horarios)
$collabs = $db->query("SELECT DISTINCT payroll_code FROM workforce_planilla_collaborators LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
$db->query("DELETE FROM workforce_shift_audit_log");

$auditReasons = [
    ['action' => 'modificar', 'old' => '08:00 - 16:30', 'new' => '06:00 - 14:30', 'reason' => 'Refuerzo de apertura y descarga de camión C-04', 'user' => 'Carlos Mendoza (Gerente Turno)'],
    ['action' => 'aprobar', 'old' => 'Sin extras', 'new' => '+2h Extras Diurnas', 'reason' => 'Extensión autorizada por inventario cíclico', 'user' => 'Laura Rincón (Jefe RRHH)'],
    ['action' => 'crear', 'old' => '—', 'new' => '14:00 - 22:30 (Cierre)', 'reason' => 'Cobertura de incapacidad médica de colaborador', 'user' => 'Carlos Mendoza (Gerente Turno)'],
    ['action' => 'modificar', 'old' => 'LIBRE', 'new' => '07:00 - 15:30 (Turno M)', 'reason' => 'Cambio de día de descanso concertado con colaborador', 'user' => 'Laura Rincón (Jefe RRHH)'],
    ['action' => 'compensar', 'old' => 'Turno Domingo', 'new' => 'Compensatorio Asignado', 'reason' => 'Compensatorio de ley por dominical festivo trabajado', 'user' => 'Sistema Planilla Retail']
];

$stmtAudit = $db->prepare("INSERT INTO workforce_shift_audit_log (shift_id, employee_id, payroll_code, shift_date, action, old_value, new_value, reason, user_id, user_name, timestamp) VALUES (1, 1, :code, :sdate, :action, :old, :new, :reason, 1, :user, :ts)");

foreach ($collabs as $code) {
    foreach ($auditReasons as $i => $ar) {
        $stmtAudit->execute([
            ':code' => $code,
            ':sdate' => date('Y-m-d', strtotime("-" . ($i + 1) . " days")),
            ':action' => $ar['action'],
            ':old' => $ar['old'],
            ':new' => $ar['new'],
            ':reason' => $ar['reason'],
            ':user' => $ar['user'],
            ':ts' => date('Y-m-d H:i:s', strtotime("-" . ($i * 14 + 2) . " hours"))
        ]);
    }
}
echo "[OK] Inserted " . (count($collabs) * count($auditReasons)) . " workforce shift audit log entries\n";

// 5. STOCK TRANSFERS (IN TRANSIT)
$db->query("UPDATE stock_transfers SET status = 'in_transit' WHERE id IN (1, 2, 3)");
echo "[OK] Updated 3 stock transfers to 'in_transit'\n";

echo "=== ALL ENTERPRISE DATA SEEDED SUCCESSFULLY ===\n";

