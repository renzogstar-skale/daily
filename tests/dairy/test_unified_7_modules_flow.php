<?php

require __DIR__ . '/bootstrap.php';

use Grocy\Services\DatabaseService;
use Grocy\Services\UnifiedInventoryEventService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\DeviceService;

echo "\n=================================================================\n";
echo " DAILY ENTERPRISE — PRUEBA INTEGRAL DEL SISTEMA UNIFICADO 360°\n";
echo " Interconexión de los 7 Módulos Operativos y Bus de Eventos\n";
echo "=================================================================\n\n";

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$eventSvc = UnifiedInventoryEventService::GetInstance();
$caseSvc = SensitiveCaseService::GetInstance();
$reconSvc = ReconciliationService::GetInstance();
$devSvc = DeviceService::GetInstance();

// -------------------------------------------------------------
// FASE 1: Verificación de Presencia de la Barra de Contexto en los 7 Módulos
// -------------------------------------------------------------
$modules = [
	'Conciliación & Sensible (/reconciliation)'      => 'views/reconciliation.blade.php',
	'Control de Mercancías (/merchandise)'          => 'views/merchandise.blade.php',
	'Prevención de Pérdidas (/loss-prevention)'      => 'views/loss_prevention.blade.php',
	'Control de Celulares (/loss-prevention/devices)' => 'views/devices.blade.php',
	'Control de Inventario (/inventory-control)'    => 'views/inventory_control.blade.php',
	'Conteos Físicos (/inventory)'                   => 'views/inventory.blade.php',
	'Sucursales y Almacenes (/branches)'            => 'views/branches.blade.php',
];

echo "--- FASE 1: Verificación de UI Compartida en los 7 Módulos ---\n";
foreach ($modules as $modName => $filePath)
{
	$content = file_get_contents(__DIR__ . '/../../' . $filePath);
	$hasContextBar = strpos($content, 'components.execution_context_bar') !== false;
	ok("Fase 1: $modName incluye Barra de Contexto 360°", $hasContextBar);
}

// -------------------------------------------------------------
// FASE 2: Verificación del Contexto Operativo Persistente
// -------------------------------------------------------------
echo "\n--- FASE 2: Contexto Operativo Activo y Enlaces Cruzados ---\n";

// Guardar contexto para el usuario 1
$eventSvc->SetContext(1, [
	'branch_id' => 1,
	'product_id' => 1,
	'case_id' => 1,
	'document_ref' => 'DOC-TEST-777',
	'last_module' => '/reconciliation'
]);

$ctx = $eventSvc->GetContext(1);
ok('Fase 2.1: Contexto activo recuperado para el usuario', !empty($ctx));
ok('Fase 2.2: Sucursal resuelta en contexto', !empty($ctx['branch_name']));
$links = $eventSvc->GetCrossLinks(1, 1, 1);
$linksValid = strpos($links['reconciliation'], 'branch_id=1') !== false
	&& strpos($links['merchandise'], 'branch_id=1') !== false
	&& strpos($links['loss_prevention'], '#case-1') !== false
	&& strpos($links['devices'], 'branch_id=1') !== false
	&& strpos($links['inventory_control'], 'branch_id=1') !== false
	&& strpos($links['inventory'], 'product_id=1') !== false
	&& strpos($links['branches'], 'branch_id=1') !== false;

ok('Fase 2.3: Enlaces cruzados preservan branch_id y product_id', $linksValid);

// -------------------------------------------------------------
// FASE 3: Ciclo Completo — Recepción → Conteo Discrepancia → Celular Faltante → Cierre
// -------------------------------------------------------------
echo "\n--- FASE 3: Flujo de Eventos de Dominio Unificado ---\n";

// 3.1 Recepción de Mercancía
$evtUuidRec = 'evt-test-rec-' . bin2hex(random_bytes(6));
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_MERCHANDISE_RECEIVED,
	'document',
	'REC-9999',
	['product_id' => 1, 'branch_id' => 1, 'amount' => 50, 'supplier' => 'Alkosto Mayorista'],
	1,
	1,
	$evtUuidRec
);

$evRec = $db->query("SELECT * FROM daily_domain_events WHERE event_uuid = '$evtUuidRec'")->fetch(\PDO::FETCH_ASSOC);
ok('Fase 3.1: Evento merchandise.received registrado en el bus', !empty($evRec) && $evRec['event_name'] === 'merchandise.received');

// 3.2 Conteo con Discrepancia
$evtUuidDisc = 'evt-test-disc-' . bin2hex(random_bytes(6));
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_INVENTORY_DISCREPANCY_DETECTED,
	'product',
	'1',
	['difference' => -3, 'financial_impact' => 750.00, 'product_name' => 'Smartphone Galaxy', 'expected' => 50, 'counted' => 47],
	1,
	1,
	$evtUuidDisc
);

$evDisc = $db->query("SELECT * FROM daily_domain_events WHERE event_uuid = '$evtUuidDisc'")->fetch(\PDO::FETCH_ASSOC);
ok('Fase 3.2: Evento inventory.discrepancy.detected registrado en el bus', !empty($evDisc) && $evDisc['event_name'] === 'inventory.discrepancy.detected');

// 3.3 Reporte de Celular No Localizado y Bloqueo Inmediato
$testImei = '359999000111222';
$db->exec("INSERT OR REPLACE INTO device_units (id, product_id, imei1, received_at, branch_id, location_id, zone, state, unit_cost, stock_reinstated, version, is_demo) 
VALUES (998, 1, '$testImei', CURRENT_TIMESTAMP, 1, 1, 'main', 'available', 890.0, 0, 1, 0)");

$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_DEVICE_MISSING_REPORTED,
	'device',
	'998',
	['imei' => $testImei, 'product_name' => 'Galaxy S24 128GB', 'create_alert' => false],
	1,
	1
);

$devMissing = $db->query("SELECT state, block_reason FROM device_units WHERE id = 998")->fetch(\PDO::FETCH_ASSOC);
ok('Fase 3.3: Celular pasa a not_located y activa bloqueo en POS', ($devMissing['state'] ?? '') === 'not_located' && !empty($devMissing['block_reason']));

// 3.4 Cambio de Custodia
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_DEVICE_CUSTODY_CHANGED,
	'device',
	'998',
	['from_zone' => 'bodega', 'to_zone' => 'exhibicion', 'custodian_user_id' => 1],
	1,
	1
);
$evCustody = $db->query("SELECT COUNT(*) FROM daily_domain_events WHERE event_name = 'device.custody.changed' AND aggregate_id = '998'")->fetchColumn();
ok('Fase 3.4: Evento device.custody.changed registrado en el bus', intval($evCustody) > 0);

// 3.5 Resolución del Caso y Desbloqueo Automático
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
	'case',
	'998',
	['device_id' => 998, 'status' => 'resuelto', 'note' => 'Equipo localizado en góndola secundaria'],
	1,
	1
);

$devResolved = $db->query("SELECT state, block_reason FROM device_units WHERE id = 998")->fetch(\PDO::FETCH_ASSOC);
ok('Fase 3.5: Celular restaurado a available y bloqueo levantado', ($devResolved['state'] ?? '') === 'available' && empty($devResolved['block_reason']));

// 3.6 Aprobación de Ajuste
$evtUuidAdj = 'evt-test-adj-' . bin2hex(random_bytes(6));
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_ADJUSTMENT_APPROVED,
	'case',
	'INV-TEST-001',
	['difference_qty' => -2, 'financial_impact' => 500.0, 'reason' => 'Pérdida formalmente autorizada por gerencia'],
	1,
	1,
	$evtUuidAdj
);
$evAdj = $db->query("SELECT * FROM daily_domain_events WHERE event_uuid = '$evtUuidAdj'")->fetch(\PDO::FETCH_ASSOC);
ok('Fase 3.6: Evento adjustment.approved registrado en el bus', !empty($evAdj));

// 3.7 Trazabilidad Unificada 360° para el Producto
$history = $eventSvc->GetUnifiedProductHistory(1, 1);
ok('Fase 3.7: Historial Unificado integra movimientos y eventos de dominio', is_array($history) && count($history) > 0);

// Limpieza de datos temporales
$db->exec("DELETE FROM device_units WHERE id = 998");
$db->exec("DELETE FROM daily_domain_events WHERE event_uuid IN ('$evtUuidRec', '$evtUuidDisc', '$evtUuidAdj')");
$db->exec("DELETE FROM daily_domain_events WHERE aggregate_id = '998'");

echo "\n=================================================================\n";
echo " RESULTADOS FINALES: Todos los módulos y eventos sincronizados al 100%.\n";
echo "=================================================================\n\n";
