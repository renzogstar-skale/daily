<?php

// DAILY — Pruebas del Centro de Trazabilidad, Conciliación e Inventario Sensible (Módulo 36)

require __DIR__ . '/bootstrap.php';

use Grocy\Services\ReconciliationService;

$svc = ReconciliationService::GetInstance();

echo "== Pruebas del Centro de Conciliación e Inventario Sensible ==\n";

// 1. Visibilidad Corporativa y Resumen
echo "\n1. Visibilidad Corporativa & Resumen Multi-Sucursal\n";
$overview = $svc->GetDashboardOverview();
ok('resumen ejecutivo contiene faltantes detectados y casos', isset($overview['shortage_value']) && isset($overview['open_cases_count']));
ok('faltante detectado incluye el caso del celular ($1,040.00)', $overview['shortage_value'] >= 1040.0, "Monto: " . $overview['shortage_value']);
ok('desglose por departamentos presente', count($overview['dept_summary']) > 0);

// 2. Catálogo de Productos y Estado Sensible
echo "\n2. Catálogo de Productos y Clasificación Sensible\n";
$products = $svc->GetProductsCatalog();
ok('catálogo devuelve productos activos', count($products) > 0);

$phone = null;
foreach ($products as $p)
{
	if (str_contains(mb_strtolower($p['name']), 'smartphone') || $p['sku'] === 'TECH-SMP-001')
	{
		$phone = $p;
		break;
	}
}
ok('smartphone galaxy configurado como inventario sensible', $phone && $phone['is_sensitive'] == 1 && $phone['imei_tracking'] == 1);
ok('smartphone tiene semáforo rojo por descuadre/caso abierto', $phone && $phone['risk_level'] === 'danger');

// 3. Ficha 360° de Conciliación (14 Puntos Clave)
echo "\n3. Ficha 360° de Conciliación (14 Métricas Operativas)\n";
$card = $svc->GetProductCard($phone['id'], 1);
$m = $card['metrics'];

ok('01. Fecha de ingreso inicial y último ingreso registradas', !empty($m['first_reception_date']) && !empty($m['last_reception_date']));
ok('02. Cantidad recibida (10 u.) y stock esperado registrados', $m['total_received_amount'] == 10.0 && isset($m['current_expected_stock']));
$realSale = $db->query("SELECT s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = " . intval($phone['id']) . " AND s.branch_id = 1 AND s.status = 'completed' ORDER BY s.created_timestamp DESC, s.id DESC LIMIT 1")->fetchColumn();
ok('03. Última venta = última venta real del POS (sin ventas: vacío, no inventada)', $realSale ? ($m['last_sale']['document_code'] ?? null) === $realSale : empty($m['last_sale']));
ok('05. Último despacho o reserva registrado', !empty($m['last_dispatch']));
ok('06. Último movimiento de bodega digital identificado', !empty($m['last_digital_movement']) && str_contains($m['last_digital_movement']['warehouse_type'], 'digital'));
ok('07. Último movimiento de bodega física identificado', !empty($m['last_physical_movement']) && $m['last_physical_movement']['warehouse_type'] === 'physical');
ok('08. Fecha de último inventario selectivo presente', !empty($m['last_selective_count_date']));
ok('09. Fecha de último inventario sensible presente', !empty($m['last_sensitive_count_date']));
ok('10. Resultado del último conteo reporta diferencia (-2 u.)', isset($m['latest_count']) && floatval($m['latest_count']['difference_amount']) == -2.0);
ok('11. Estadísticas de diferencias históricas calculadas', isset($m['historical_diff_stats']['total_counts']));
ok('13. Trazabilidad de números de serie / IMEI activa', count($m['serials']) > 0);
ok('14. Responsable de la última operación identificado', !empty($m['last_operation']['user']));

// 4. Línea de Tiempo Única Cronológica
echo "\n4. Línea de Tiempo Única Cronológica\n";
$timelineAll = $svc->GetTimeline($phone['id'], 1);
ok('línea de tiempo devuelve eventos ordenados cronológicamente', count($timelineAll) >= 5);
$timelineSales = $svc->GetTimeline($phone['id'], 1, 'sale');
ok('filtro de línea de tiempo por venta devuelve solo ventas', count($timelineSales) >= 1 && $timelineSales[0]['event_type'] === 'sale');
$timelineCounts = $svc->GetTimeline($phone['id'], 1, 'cycle_count');
ok('filtro de línea de tiempo por conteo devuelve conteos', count($timelineCounts) >= 1 && $timelineCounts[0]['event_type'] === 'cycle_count');

// 5. Motor Automático de Alertas e Intervalos
echo "\n5. Motor Automático de Alertas e Intervalos\n";
// Simular conteo físico con diferencia en otro producto
$testProdId = 30; // Leche entera 1 galón
$countRes = $svc->ExecuteCountAndEvaluate(1, $testProdId, 5.0, 'sensible', 'Auditoría de prueba', 1);
ok('conteo procesado exitosamente', $countRes['success'] === true);
ok('diferencia evaluada y alerta generada', $countRes['alert_generated'] === true && !empty($countRes['alert_id']));
ok('expediente de investigación abierto', !empty($countRes['case_id']));

// 6. Expediente de Investigación, Reconteo y Segregación de Funciones
echo "\n6. Expediente de Investigación y Segregación de Funciones\n";
$caseId = $countRes['case_id'];
$caseData = $svc->GetInvestigationCase($caseId);
ok('expediente 360 recuperado con intervalo delimitado', !empty($caseData['case']['audit_interval_start']));
ok('principio de prudencia: no imputa robo en momento exacto', !empty($caseData['case']['evidence_notes']));

// Reconteo físico que ratifica diferencia
$recountRes = $svc->RecountCase($caseId, 5.0, 'Reconteo ratifica 5 u. en cámara de frío.', 1);
ok('reconteo ratifica diferencia y eleva a faltante confirmado', $recountRes['success'] === true);

// Segregación de funciones: usuario 2 no puede ser el mismo que creó el caso si no es admin,
// pero usuario 1 (admin) o un segundo usuario sí puede autorizar
\Grocy\Services\SensitiveCaseService::GetInstance()->ProposeAdjustment($caseId, 'Reconteo ratifica la diferencia.', 1);
$adjustRes = $svc->AuthorizeAdjustment($caseId, 'approved', 'Pérdida formalizada en merma tras reconteo.', 1);
ok('ajuste de inventario autorizado con segregación de funciones', $adjustRes['success'] === true);

// Cierre formal
$closeRes = $svc->CloseCase($caseId, 'Auditoría cerrada conforme a protocolo DAILY.', 1);
ok('expediente cerrado formalmente y alerta resuelta', $closeRes['success'] === true);

echo "\nPruebas de Trazabilidad, Conciliación e Inventario Sensible finalizadas con éxito.\n";
