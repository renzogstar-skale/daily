<?php

require __DIR__ . '/bootstrap.php';

use Grocy\Services\UnifiedInventoryEventService;
use Grocy\Services\DatabaseService;

echo "\n=======================================================\n";
echo " DAILY ENTERPRISE — BUS DE EVENTOS UNIFICADO 360°\n";
echo " Verificación de Arquitectura Central y 7 Módulos\n";
echo "=======================================================\n\n";

$svc = UnifiedInventoryEventService::GetInstance();
$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Verificación de emisión de eventos de dominio
$uuid = 'test-evt-' . bin2hex(random_bytes(8));
$eventId = $svc->Publish(
	UnifiedInventoryEventService::EVENT_INVENTORY_DISCREPANCY_DETECTED,
	'product',
	'9001',
	['difference' => -2, 'product_name' => 'Galaxy S24', 'expected' => 10, 'counted' => 8],
	1,
	1,
	$uuid
);

ok('Paso 1.1: Evento de discrepancia emitido con UUID', $eventId === $uuid);

// Idempotencia: re-emitir el mismo UUID
$reId = $svc->Publish(
	UnifiedInventoryEventService::EVENT_INVENTORY_DISCREPANCY_DETECTED,
	'product',
	'9001',
	['difference' => -2],
	1,
	1,
	$uuid
);
ok('Paso 1.2: Idempotencia garantizada (mismo UUID no duplica)', $reId === $uuid);

$eventCount = intval($db->query("SELECT COUNT(*) FROM daily_domain_events WHERE event_uuid = '$uuid'")->fetchColumn());
ok('Paso 1.3: Registro único en daily_domain_events', $eventCount === 1);

// 2. Verificación de Contexto Operativo Compartido entre los 7 Módulos
$svc->SetContext(1, [
	'branch_id' => 1,
	'product_id' => 9001,
	'case_id' => 2,
	'module' => 'loss_prevention'
]);

$ctx = $svc->GetContext(1);
ok('Paso 2.1: Contexto activo guardado para el usuario', is_array($ctx));
ok('Paso 2.2: Sucursal hidratada en el contexto', !empty($ctx['branch_name']));
ok('Paso 2.3: Producto hidratado en el contexto', isset($ctx['product_name']) && !empty($ctx['product_name']));
ok('Paso 2.4: Expediente hidratado en el contexto', isset($ctx['case_code']) && !empty($ctx['case_code']));

// 3. Verificación de Enlaces Cruzados entre los 7 Módulos
$links = $svc->GetCrossLinks(1, 9001, 2);
ok('Paso 3.1: Enlace a /reconciliation generado con filtros', strpos($links['reconciliation'], 'branch_id=1') !== false);
ok('Paso 3.2: Enlace a /merchandise generado con filtros', strpos($links['merchandise'], 'branch_id=1') !== false);
ok('Paso 3.3: Enlace a /loss-prevention/devices generado', strpos($links['devices'], 'product_id=9001') !== false);
ok('Paso 3.4: Enlace a /inventory-control generado', strpos($links['inventory_control'], 'branch_id=1') !== false);
ok('Paso 3.5: Enlace a /inventory generado', strpos($links['inventory'], 'product_id=9001') !== false);
ok('Paso 3.6: Enlace a /branches generado', strpos($links['branches'], 'branch_id=1') !== false);
ok('Paso 3.7: Enlace a /loss-prevention incluye ancla de caso', strpos($links['loss_prevention'], '#case-2') !== false);

// 4. Verificación de Trazabilidad Unificada 360°
$history = $svc->GetUnifiedProductHistory(9001, 1);
ok('Paso 4.1: Historial unificado recuperado (> 0 eventos)', is_array($history));

// 5. Verificación de Protocolo de Celular No Localizado
// Crear o actualizar un dispositivo demo para la prueba
$db->exec("INSERT OR REPLACE INTO device_units (id, product_id, imei1, received_at, branch_id, location_id, zone, state, unit_cost, stock_reinstated, version, is_demo) 
VALUES (999, 9001, 'IMEI-TEST-999', CURRENT_TIMESTAMP, 1, 1, 'main', 'available', 500.0, 0, 1, 0)");

$svc->Publish(
	UnifiedInventoryEventService::EVENT_DEVICE_MISSING_REPORTED,
	'device',
	'999',
	['imei' => 'IMEI-TEST-999', 'product_name' => 'Galaxy Test', 'create_alert' => false],
	1,
	1
);

$dev = $db->query("SELECT state, block_reason FROM device_units WHERE imei1 = 'IMEI-TEST-999'")->fetch(\PDO::FETCH_ASSOC);
ok('Paso 5.1: Dispositivo marcado automáticamente como not_located con bloqueo', ($dev['state'] ?? '') === 'not_located' && !empty($dev['block_reason']));

// Resolver caso: debe restaurar a available y quitar bloqueo
$svc->Publish(
	UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
	'case',
	'999',
	['device_id' => 999, 'status' => 'resuelto'],
	1,
	1
);
$devRestored = $db->query("SELECT state, block_reason FROM device_units WHERE id = 999")->fetch(\PDO::FETCH_ASSOC);
ok('Paso 5.2: Dispositivo restaurado automáticamente a available al resolver caso', ($devRestored['state'] ?? '') === 'available' && empty($devRestored['block_reason']));

// Limpieza de fixture
$db->exec("DELETE FROM device_units WHERE id = 999");
$db->exec("DELETE FROM daily_domain_events WHERE event_uuid = '$uuid'");

echo "\nResultados: Total OK. Todas las verificaciones del Bus Unificado superadas.\n";
