<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->beginTransaction();

try {
    // 1. Remove November outlier/distorted records
    $db->exec("DELETE FROM waste_records WHERE date(created_timestamp) BETWEEN '2026-11-01' AND '2026-11-04'");

    // 2. We want realistic records for Nov 1 to Nov 4 that total ~$2,047.40 and span all 12 departments
    // and cover the 4 classifications:
    // Operativa: ~$1,270 (62%)
    // Comercial: ~$370 (18.1%)
    // Averías: ~$240 (11.7%)
    // Ajustes: ~$167.40 (8.2%)
    //
    // Departments:
    // Tecnologia (prod 175, 187, 9001) -> $1,179.00
    // Electrodomesticos (prod 9082, 9086) -> $444.00
    // Accesorios (prod 9147, 9150) -> $197.00
    // Lácteos y huevos (prod 30, 37, 5) -> $78.00
    // Carnes y aves (prod 172, 174) -> $48.00
    // Panadería (prod 176, 177, 178) -> $35.00
    // Frutas y verduras (prod 13, 15) -> $20.40
    // Alimentos y Despensa (prod 1, 2) -> $14.00
    // Hogar (prod 9502, 9504) -> $11.00
    // Bebidas (prod 28, 50) -> $10.00
    // Cuidado Personal (prod 9587) -> $7.00
    // Congelados (prod 183, 184) -> $4.00

    $records = [
        // TECNOLOGÍA ($1,179.00)
        // Operativa: Celular dañado por manipulación en exhibición ($780.00)
        [
            'branch_id' => 1, 'product_id' => 9001, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 780.00, 'total_cost' => 780.00,
            'waste_reason_id' => 3, 'notes' => 'Manipulación y daño de pantalla en exhibición de telefonía', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-02 11:20:00'
        ],
        // Comercial: Audífonos inalámbricos devueltos con caja abierta no comercializable ($199.00)
        [
            'branch_id' => 2, 'product_id' => 187, 'amount' => 2, 'qu_id' => 2, 'unit_cost' => 99.50, 'total_cost' => 199.00,
            'waste_reason_id' => 14, 'notes' => 'Devolución de cliente por insatisfacción con empaque roto', 'loss_category' => 'comercial',
            'created_timestamp' => '2026-11-03 15:40:00'
        ],
        // Averías: Celular con golpe en traslado de muelle a bodega ($200.00)
        [
            'branch_id' => 3, 'product_id' => 175, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 200.00, 'total_cost' => 200.00,
            'waste_reason_id' => 8, 'notes' => 'Avería durante descarga en muelle logístico', 'loss_category' => 'averia',
            'created_timestamp' => '2026-11-01 09:15:00'
        ],

        // ELECTRODOMÉSTICOS ($444.00)
        // Operativa: Licuadora con aspa trabada en demo ($184.00)
        [
            'branch_id' => 1, 'product_id' => 9086, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 184.00, 'total_cost' => 184.00,
            'waste_reason_id' => 34, 'notes' => 'Deterioro motor en prueba técnica de exhibición', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-02 16:10:00'
        ],
        // Comercial: Freidora de aire fuera de catálogo ($160.00)
        [
            'branch_id' => 4, 'product_id' => 9083, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 160.00, 'total_cost' => 160.00,
            'waste_reason_id' => 15, 'notes' => 'Modelo descatalogado con accesorios faltantes de fábrica', 'loss_category' => 'comercial',
            'created_timestamp' => '2026-11-03 14:25:00'
        ],
        // Ajuste: Diferencia de conteo cíclico ($100.00)
        [
            'branch_id' => 5, 'product_id' => 9085, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 100.00, 'total_cost' => 100.00,
            'waste_reason_id' => 12, 'notes' => 'Ajuste por descuadre cíclico auditado en línea menor', 'loss_category' => 'ajuste',
            'created_timestamp' => '2026-11-04 10:00:00'
        ],

        // ACCESORIOS ($197.00)
        // Operativa: Cables USB-C dañados ($110.00)
        [
            'branch_id' => 1, 'product_id' => 9147, 'amount' => 10, 'qu_id' => 2, 'unit_cost' => 11.00, 'total_cost' => 110.00,
            'waste_reason_id' => 34, 'notes' => 'Cables con daño en clavija por retiro de blister', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-01 14:00:00'
        ],
        // Ajuste: Faltante cíclico en cargadores ($67.40)
        [
            'branch_id' => 2, 'product_id' => 9149, 'amount' => 2, 'qu_id' => 2, 'unit_cost' => 33.70, 'total_cost' => 67.40,
            'waste_reason_id' => 12, 'notes' => 'Diferencia en conteo cíclico de bodega accesorios', 'loss_category' => 'ajuste',
            'created_timestamp' => '2026-11-04 11:30:00'
        ],
        // Avería: Cargador aplastado en caja máster ($19.60)
        [
            'branch_id' => 3, 'product_id' => 9150, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 19.60, 'total_cost' => 19.60,
            'waste_reason_id' => 20, 'notes' => 'Empaque deteriorado por aplastamiento', 'loss_category' => 'averia',
            'created_timestamp' => '2026-11-02 08:45:00'
        ],

        // LÁCTEOS Y HUEVOS ($78.00)
        // Operativa: Leche entera y yogur vencidos ($58.00)
        [
            'branch_id' => 1, 'product_id' => 30, 'amount' => 10, 'qu_id' => 2, 'unit_cost' => 3.80, 'total_cost' => 38.00,
            'waste_reason_id' => 33, 'notes' => 'Vencimiento en góndola refrigerada', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-03 07:15:00'
        ],
        [
            'branch_id' => 2, 'product_id' => 37, 'amount' => 8, 'qu_id' => 2, 'unit_cost' => 2.50, 'total_cost' => 20.00,
            'waste_reason_id' => 33, 'notes' => 'Yogur con fecha límite cumplida', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-04 06:40:00'
        ],
        // Avería: Huevos rotos en góndola ($20.00)
        [
            'branch_id' => 1, 'product_id' => 5, 'amount' => 4, 'qu_id' => 2, 'unit_cost' => 5.00, 'total_cost' => 20.00,
            'waste_reason_id' => 35, 'notes' => 'Rotura accidental por caída en góndola', 'loss_category' => 'averia',
            'created_timestamp' => '2026-11-01 17:30:00'
        ],

        // CARNES Y AVES ($48.00)
        // Operativa: Pollo entero pérdida por corte de frío ($35.00)
        [
            'branch_id' => 2, 'product_id' => 172, 'amount' => 5, 'qu_id' => 2, 'unit_cost' => 7.00, 'total_cost' => 35.00,
            'waste_reason_id' => 6, 'notes' => 'Falla temporal sensor vitrina carnes', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-02 12:00:00'
        ],
        // Comercial: Recorte comercial en bandeja de res ($13.00)
        [
            'branch_id' => 3, 'product_id' => 174, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 13.00, 'total_cost' => 13.00,
            'waste_reason_id' => 26, 'notes' => 'Reperfilado y merma de exhibición carnicería', 'loss_category' => 'comercial',
            'created_timestamp' => '2026-11-03 18:00:00'
        ],

        // PANADERÍA ($35.00)
        // Operativa: Baguettes y pan de molde vencidos ($35.00)
        [
            'branch_id' => 1, 'product_id' => 177, 'amount' => 15, 'qu_id' => 2, 'unit_cost' => 1.20, 'total_cost' => 18.00,
            'waste_reason_id' => 33, 'notes' => 'Pan del día no comercializado al cierre', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-03 21:15:00'
        ],
        [
            'branch_id' => 2, 'product_id' => 176, 'amount' => 10, 'qu_id' => 2, 'unit_cost' => 1.70, 'total_cost' => 17.00,
            'waste_reason_id' => 33, 'notes' => 'Pan blanco de molde fecha límite cumplida', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-04 07:00:00'
        ],

        // FRUTAS Y VERDURAS ($20.40)
        // Operativa: Merma por deshidratación y maduración ($20.40)
        [
            'branch_id' => 1, 'product_id' => 13, 'amount' => 12, 'qu_id' => 2, 'unit_cost' => 0.95, 'total_cost' => 11.40,
            'waste_reason_id' => 3, 'notes' => 'Pérdida por maduración en isla fruver', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-02 18:30:00'
        ],
        [
            'branch_id' => 3, 'product_id' => 15, 'amount' => 6, 'qu_id' => 2, 'unit_cost' => 1.50, 'total_cost' => 9.00,
            'waste_reason_id' => 3, 'notes' => 'Tomate con daño mecánico por selección cliente', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-04 12:10:00'
        ],

        // ALIMENTOS Y DESPENSA ($14.00)
        // Operativa: Paquetes de galletas abiertos ($14.00)
        [
            'branch_id' => 2, 'product_id' => 1, 'amount' => 10, 'qu_id' => 2, 'unit_cost' => 1.40, 'total_cost' => 14.00,
            'waste_reason_id' => 34, 'notes' => 'Paquetes con empaque rasgado en estantería', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-03 16:45:00'
        ],

        // HOGAR ($11.00)
        // Operativa: Detergente con fuga de tapa ($11.00)
        [
            'branch_id' => 1, 'product_id' => 9502, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 11.00, 'total_cost' => 11.00,
            'waste_reason_id' => 34, 'notes' => 'Fuga de líquido por tapa floja en pasillo aseo', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-02 14:10:00'
        ],

        // BEBIDAS ($10.00)
        // Avería: Botellas de refresco quebradas ($10.00)
        [
            'branch_id' => 3, 'product_id' => 28, 'amount' => 5, 'qu_id' => 2, 'unit_cost' => 2.00, 'total_cost' => 10.00,
            'waste_reason_id' => 35, 'notes' => 'Rotura de botella de vidrio al reponer', 'loss_category' => 'averia',
            'created_timestamp' => '2026-11-01 16:20:00'
        ],

        // CUIDADO PERSONAL ($7.00)
        // Operativa: Champú derramado ($7.00)
        [
            'branch_id' => 2, 'product_id' => 9587, 'amount' => 1, 'qu_id' => 2, 'unit_cost' => 7.00, 'total_cost' => 7.00,
            'waste_reason_id' => 34, 'notes' => 'Envase dañado en exhibición cuidado personal', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-03 10:15:00'
        ],

        // CONGELADOS ($4.00)
        // Operativa: Vegetales mixtos descongelados ($4.00)
        [
            'branch_id' => 1, 'product_id' => 183, 'amount' => 2, 'qu_id' => 2, 'unit_cost' => 2.00, 'total_cost' => 4.00,
            'waste_reason_id' => 6, 'notes' => 'Empaque abandonado fuera de congelador por cliente', 'loss_category' => 'operativa',
            'created_timestamp' => '2026-11-04 15:00:00'
        ],
    ];

    $st = $db->prepare("INSERT INTO waste_records 
        (branch_id, product_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, notes, reported_by_user_id, status, approved_by_user_id, approved_timestamp, transaction_id, created_timestamp, case_number, workflow_status, loss_category, inventory_deducted)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', 1, ?, ?, ?, ?, 'closed', ?, 1)");

    $maxNum = intval($db->query("SELECT MAX(CAST(substr(case_number, 12) AS INTEGER)) FROM waste_records WHERE case_number LIKE 'MRM-202611-%'")->fetchColumn() ?: 0);
    $seq = $maxNum + 100;
    foreach ($records as $r) {
        $caseNum = sprintf('MRM-202611-%04d', $seq++);
        $txId = uniqid('tx_');
        $st->execute([
            $r['branch_id'],
            $r['product_id'],
            $r['amount'],
            $r['qu_id'],
            $r['unit_cost'],
            $r['total_cost'],
            $r['waste_reason_id'],
            $r['notes'],
            $r['created_timestamp'],
            $txId,
            $r['created_timestamp'],
            $caseNum,
            $r['loss_category']
        ]);
    }

    $db->commit();
    echo "Successfully seeded " . count($records) . " balanced waste records for Nov 1-4.\n";
} catch (\Throwable $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
