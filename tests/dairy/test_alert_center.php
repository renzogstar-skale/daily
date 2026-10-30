<?php
/**
 * Centro de Alertas v2 sobre una COPIA de la base de datos:
 *   php tests/dairy/test_alert_center.php [ruta/a/grocy.db]
 * Cubre: migración, generación automática, deduplicación, agrupación y enfriamiento, prioridad (seguridad alimentaria),
 * alta manual idempotente, ciclo de vida completo (asignación, investigación, espera, aprobación con segregación,
 * resolución, verificación, reapertura), SLA, escalamiento y recordatorios sin duplicados, evidencias, plan de acción,
 * filtros, búsqueda, pestañas, consistencia matemática del tablero, impacto sin doble conteo, reportes, vistas y permisos.
 */

use Grocy\Services\AlertCenterService;
use Grocy\Services\StockService;

require __DIR__ . '/bootstrap.php';

$al = AlertCenterService::GetInstance();
$q = function (string $sql, array $p = []) use ($db) {
	$s = $db->prepare($sql);
	$s->execute($p);
	return $s;
};
$ACTIVE = AlertCenterService::In(AlertCenterService::ACTIVE);

// ---------------------------------------------------------------- 1. Migración
echo "\n1. Modelo de datos\n";
$cols = array_column($db->query('PRAGMA table_info(dairy_alerts)')->fetchAll(PDO::FETCH_ASSOC), 'name');
ok('columnas nuevas (prioridad, categoría, SLA, escalamiento)', !array_diff(['priority', 'priority_reason', 'category', 'alert_type', 'first_response_at', 'escalation_level', 'reopened_count', 'impact_bucket', 'impact_kind'], $cols));
foreach (['dairy_alert_keys', 'dairy_alert_evidence', 'dairy_alert_actions', 'dairy_alert_notifications', 'dairy_alert_views'] as $t)
{
	ok("tabla $t", (bool) $val("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '$t'"));
}
ok('reglas nuevas con metadatos', intval($val("SELECT COUNT(*) FROM dairy_alert_rules WHERE code IN ('low_stock','negative_stock','count_difference','count_overdue','transfer_pending','po_late','quarantine_stale','shift_unpublished','missing_punch','absence_pending','sales_drop','low_margin')")) === 12);
ok('ninguna regla queda sin categoría', intval($val("SELECT COUNT(*) FROM dairy_alert_rules WHERE category = 'other'")) === 0);
ok('alertas previas heredan categoría de su regla', intval($val('SELECT COUNT(*) FROM dairy_alerts a JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE a.category <> r.category')) === 0);

// ---------------------------------------------------------------- 2. Prioridad
echo "\n2. Priorización\n";
[$p1] = AlertCenterService::Prioritize('danger', true, 0, 1, false, null);
ok('cadena de frío crítica sin impacto monetario = P1', $p1 === 'P1');
[$p2, , $why] = AlertCenterService::Prioritize('info', true, 0, 1, false, null);
ok('seguridad alimentaria nunca baja de P2 aunque el monto sea 0', $p2 === 'P2' && str_contains($why, 'Piso P2'), $why);
[$p3] = AlertCenterService::Prioritize('warning', false, 10, 1, false, null);
[$p4] = AlertCenterService::Prioritize('warning', false, 1500, 3, true, null);
ok('impacto, recurrencia y SLA vencido suben la prioridad', $p3 === 'P3' && $p4 === 'P1', "$p3 → $p4");
[$p5] = AlertCenterService::Prioritize('info', false, null, 1, false, null);
ok('informativa sin impacto = P4', $p5 === 'P4');

// ---------------------------------------------------------------- 3. Generación automática, dedupe y agrupación
echo "\n3. Motor de reglas\n";
$stock = StockService::GetInstance();
$prod = $db->query('SELECT s.product_id, s.location_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 AND s.amount >= 4 AND s.price > 0 GROUP BY s.product_id ORDER BY s.product_id LIMIT 1')->fetch(PDO::FETCH_OBJ);
ok('producto con stock en sucursal 1', (bool) $prod);
$al->SaveRule('unexplained_outflow', ['threshold' => 0]);
$consume = fn() => $stock->ConsumeProduct(intval($prod->product_id), 1, false, StockService::TRANSACTION_TYPE_CONSUME, 'default', null, intval($prod->location_id));
$consume();
$s1 = $al->Scan(1, 1);
$outflow = $q("SELECT * FROM dairy_alerts WHERE rule_code = 'unexplained_outflow' AND product_id = ? AND status IN ($ACTIVE)", [$prod->product_id])->fetch(PDO::FETCH_ASSOC);
ok('salida sin documento genera alerta con categoría, tipo y producto', $outflow && $outflow['category'] === 'security' && $outflow['alert_type'] === 'waste', json_encode($s1));
ok('prioridad calculada con motivo visible', $outflow && in_array($outflow['priority'], ['P1', 'P2', 'P3', 'P4'], true) && str_contains((string) $outflow['priority_reason'], 'Severidad'), $outflow['priority_reason'] ?? '');
$s2 = $al->Scan(1, 1);
ok('re-escanear no duplica (dedupe)', $s2['created'] === 0, json_encode($s2));
$consume();
$s3 = $al->Scan(1, 1);
$after = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$outflow['id'] ?? 0])->fetch(PDO::FETCH_ASSOC);
ok('segundo hallazgo del mismo producto se agrupa (no crea otra alerta)', $after && $s3['grouped'] >= 1 && intval($after['occurrences']) === 2 && floatval($after['value']) > floatval($outflow['value']), json_encode($s3));
ok('las claves del grupo quedan registradas', intval($val('SELECT COUNT(*) FROM dairy_alert_keys WHERE alert_id = ' . intval($outflow['id'] ?? 0))) === 2);
$al->Dismiss(intval($outflow['id'] ?? 0), 'Consumo de prueba en test', 1);
$consume();
$s4 = $al->Scan(1, 1);
ok('durante el enfriamiento no se abre otra alerta del mismo producto', $s4['suppressed'] >= 1 && intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'unexplained_outflow' AND product_id = {$prod->product_id} AND status IN ($ACTIVE)")) === 0, json_encode($s4));
ok('la supresión queda en la bitácora de la alerta cerrada', intval($val("SELECT COUNT(*) FROM dairy_alert_events WHERE alert_id = " . intval($outflow['id'] ?? 0) . " AND action = 'suppressed'")) >= 1);

// Regla nueva: stock bajo mínimo con datos reales
$minP = $db->query('SELECT p.id FROM products p JOIN locations l ON l.id = p.location_id WHERE l.branch_id = 1 AND p.active = 1 ORDER BY p.id LIMIT 1')->fetchColumn();
$db->exec("UPDATE products SET min_stock_amount = 999999 WHERE id = $minP");
$al->Scan(1, 1);
ok('stock bajo mínimo detectado', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'low_stock' AND dedupe_key = 'low_stock:1:$minP'")) === 1);
$db->exec("UPDATE products SET min_stock_amount = 0 WHERE id = $minP");
$al->Scan(1, 1);
ok('sin intervención se cierra sola al desaparecer la condición', $val("SELECT status FROM dairy_alerts WHERE dedupe_key = 'low_stock:1:$minP'") === 'closed');

// ---------------------------------------------------------------- 4. Alta manual e idempotencia
echo "\n4. Alta manual\n";
expectError('título obligatorio', fn() => $al->Create(['title' => 'x', 'branch_id' => 1], 1));
expectError('impacto negativo rechazado', fn() => $al->Create(['title' => 'Prueba negativa', 'branch_id' => 1, 'value' => -5], 1));
$c1 = $al->Create(['title' => 'Cámara 2 con puerta dañada', 'detail' => 'Cierre irregular', 'category' => 'quality', 'alert_type' => 'cold_chain', 'branch_id' => 1, 'severity' => 'warning', 'value' => 80, 'due_hours' => 4, 'client_token' => 'test-tok-1'], 1);
$c2 = $al->Create(['title' => 'Cámara 2 con puerta dañada', 'branch_id' => 1, 'client_token' => 'test-tok-1'], 1);
ok('idempotente: el mismo token no duplica', $c1['created'] && !$c2['created'] && $c1['id'] === $c2['id']);
$m = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$c1['id']])->fetch(PDO::FETCH_ASSOC);
ok('calidad / cadena de frío manual: piso P2', $m['priority'] === 'P2' && $m['source'] === 'manual', $m['priority']);
ok('alta manual con categoría propia', $m['category'] === 'quality' && $m['alert_type'] === 'cold_chain');

// ---------------------------------------------------------------- 5. Ciclo de vida
echo "\n5. Ciclo de vida completo\n";
$id = $c1['id'];
$db->exec("DELETE FROM user_settings WHERE key = 'dairy_m_notif_prefs'");
$al->Assign($id, 2, 1);
ok('asignada → estado Asignada', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'assigned');
ok('notificación al responsable', intval($val("SELECT COUNT(*) FROM dairy_alert_notifications WHERE alert_id = $id AND user_id = 2 AND kind = 'assigned'")) === 1);
ok('primera respuesta registrada', $val("SELECT first_response_at FROM dairy_alerts WHERE id = $id") !== null);
expectError('cambio de prioridad exige justificación', fn() => $al->SetPriority($id, 'P1', '', 1));
expectError('no se baja una alerta de seguridad alimentaria a P3', fn() => $al->SetPriority($c1['id'], 'P3', 'Parece menor', 1));
$al->SetPriority($id, 'P1', 'Producto sensible expuesto', 1);
ok('prioridad manual queda bloqueada y explicada', $val("SELECT priority || '|' || priority_locked FROM dairy_alerts WHERE id = $id") === 'P1|1' && str_contains($val("SELECT priority_reason FROM dairy_alerts WHERE id = $id"), 'Manual'));
$al->Investigate($id, 'Revisando bisagras', 2);
ok('en investigación', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'investigating');
expectError('esperar información exige detalle', fn() => $al->WaitInfo($id, '', 2));
$al->WaitInfo($id, 'Esperando informe del técnico', 2);
ok('esperando información', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'waiting_info');
$al->RequestApproval($id, 'Cambio de empaque de puerta por $80', 2);
ok('esperando aprobación', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'waiting_approval');
expectError('no se resuelve mientras espera aprobación', fn() => $al->Resolve($id, 'Arreglado sin aprobar', 2));
expectError('quien solicitó no aprueba', fn() => $al->DecideRequest($id, true, null, 2));
$al->DecideRequest($id, true, 'Aprobado el gasto', 1);
ok('aprobada → en ejecución', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'in_progress');
$aid = $al->AddAction($id, ['description' => 'Cambiar empaque de puerta', 'owner_user_id' => 2], 1);
$al->CompleteAction($aid, 'Empaque cambiado', true, 2);
ok('plan de acción con resultado', $val("SELECT status FROM dairy_alert_actions WHERE id = $aid") === 'done');
$al->AddEvidence($id, 'test.jpg', 'foto_puerta.jpg', 'image/jpeg', 1234, 'Puerta reparada', 2);
ok('evidencia registrada en expediente y bitácora', count($al->Evidence($id)) === 1 && intval($val("SELECT COUNT(*) FROM dairy_alert_events WHERE alert_id = $id AND action = 'evidence'")) === 1);
$db->exec("UPDATE dairy_alerts SET due_at = datetime('now', 'localtime', '-1 hour') WHERE id = $id");
$al->Resolve($id, 'Empaque cambiado y temperatura estable 3 °C', 2);
$r = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
ok('alerta crítica (P1 / frío) siempre pasa por validación aunque la regla no la exija', $r['status'] === 'pending_approval');
$al->Decide($id, true, 'Temperatura verificada', 1);
$r = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
ok('validada por otra persona → cerrada', $r['status'] === 'closed' && $r['approved_by_user_id'] == 1);
ok('resuelta fuera de SLA (registrado)', $r['resolved_at'] > $r['due_at'] && str_contains($val("SELECT note FROM dairy_alert_events WHERE alert_id = $id AND action = 'resolved' ORDER BY id DESC LIMIT 1"), 'fuera de SLA'));
expectError('reabrir exige motivo', fn() => $al->Reopen($id, '', 1));
$al->Reopen($id, 'La temperatura volvió a subir', 1);
$r = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$id])->fetch(PDO::FETCH_ASSOC);
ok('reabierta con contador y nuevo plazo', $r['status'] === 'reopened' && intval($r['reopened_count']) === 1 && $r['resolved_at'] === null && $r['due_at'] > date('Y-m-d H:i:s'));
$al->Start($id, 2);
$al->Resolve($id, 'Técnico reemplazó el termostato', 2);
$al->Decide($id, true, 'Verificado en sitio', 1);
ok('se resuelve de nuevo', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'closed');
ok('la alerta resuelta no desaparece: conserva historial', count($al->GetEvents($id)) >= 10 && count($al->Detail($id)['events']) === count($al->GetEvents($id)));

// Verificación del cierre con segregación (regla con aprobación)
$c3 = $al->Create(['title' => 'Merma sospechosa en bodega', 'branch_id' => 1, 'category' => 'waste', 'alert_type' => 'waste', 'client_token' => 'test-tok-3'], 1);
$db->exec("UPDATE dairy_alerts SET rule_code = 'high_value_waste' WHERE id = {$c3['id']}");
$next = $al->Resolve($c3['id'], 'Causa: rotura en traslado', 2);
ok('regla con aprobación → por verificar', $next === 'pending_approval');
expectError('quien resolvió no verifica su cierre', fn() => $al->Decide($c3['id'], true, null, 2));
$al->Decide($c3['id'], true, null, 1);
ok('verificada por otra persona → cerrada', $val("SELECT status FROM dairy_alerts WHERE id = {$c3['id']}") === 'closed');

// ---------------------------------------------------------------- 6. SLA, recordatorios y escalamiento
echo "\n6. Escalamiento y notificaciones\n";
$c4 = $al->Create(['title' => 'Conteo crítico sin ejecutar', 'branch_id' => 1, 'owner_user_id' => 2, 'client_token' => 'test-tok-4'], 1);
$db->exec("UPDATE dairy_alerts SET due_at = datetime('now', 'localtime', '+10 minutes') WHERE id = {$c4['id']}");
$e1 = $al->RunEscalations();
$e2 = $al->RunEscalations();
ok('recordatorio al responsable cerca del vencimiento, sin duplicar', intval($val("SELECT COUNT(*) FROM dairy_alert_notifications WHERE alert_id = {$c4['id']} AND kind = 'reminder'")) === 1, json_encode([$e1, $e2]));
$db->exec("UPDATE dairy_alerts SET due_at = datetime('now', 'localtime', '-1 hour') WHERE id = {$c4['id']}");
$al->RunEscalations();
$al->RunEscalations();
ok('SLA vencido → escalada nivel 1 una sola vez', intval($val("SELECT escalation_level FROM dairy_alerts WHERE id = {$c4['id']}")) === 1 && intval($val("SELECT COUNT(*) FROM dairy_alert_events WHERE alert_id = {$c4['id']} AND action = 'escalated'")) === 1);
ok('notificación de escalamiento a gestores', intval($val("SELECT COUNT(*) FROM dairy_alert_notifications WHERE alert_id = {$c4['id']} AND kind = 'escalated'")) >= 1);
$db->exec("UPDATE dairy_alerts SET due_at = datetime('now', 'localtime', '-30 hours') WHERE id = {$c4['id']}");
$al->RunEscalations();
ok('sigue sin atención → nivel 2 (administración)', intval($val("SELECT escalation_level FROM dairy_alerts WHERE id = {$c4['id']}")) === 2);
$n = $al->UserNotifications(2);
ok('bandeja del usuario con no leídas', $n['unread'] >= 1);
$al->Acknowledge($c4['id'], 2);
ok('confirmación de lectura marca leídas las de la alerta', intval($val("SELECT COUNT(*) FROM dairy_alert_notifications WHERE alert_id = {$c4['id']} AND user_id = 2 AND read_at IS NULL")) === 0);
$db->exec("INSERT OR REPLACE INTO user_settings (user_id, key, value) VALUES (3, 'dairy_m_notif_prefs', '{\"alerts\":false}')");
ok('preferencia del usuario: sin avisos de alertas (excepto escalamiento)', $al->Notify($c4['id'], [3], 'reminder', 'x', null, 'pref') === 0 && $al->Notify($c4['id'], [3], 'escalated', 'x', null, 'pref') === 1);

// ---------------------------------------------------------------- 7. Filtros, búsqueda y pestañas
echo "\n7. Filtros y búsqueda\n";
$f = ['allowed' => null, 'me' => 1];
$l = $al->List($f + ['q' => 'ALR-' . $c4['id']], 'all');
ok('búsqueda por identificador ALR-n', $l['total'] === 1 && intval($l['rows'][0]['id']) === $c4['id']);
$l = $al->List($f + ['q' => 'Conteo crítico'], 'all');
ok('búsqueda por texto', $l['total'] >= 1);
$l = $al->List($f, 'all', 1, 200);
ok('pestañas por prioridad suman el total', $l['counts']['P1'] + $l['counts']['P2'] + $l['counts']['P3'] + $l['counts']['P4'] === $l['counts']['all'], json_encode($l['counts']));
ok('pestaña sin responsable coherente', $l['counts']['all'] > 200 || $l['counts']['unassigned'] === count(array_filter($l['rows'], fn($r) => !$r['owner_user_id'])));
$lv = $al->List($f, 'overdue', 1, 200);
ok('vencidas: todas con plazo pasado', $lv['total'] >= 1 && !array_filter($lv['rows'], fn($r) => !intval($r['overdue'])));
$lc = $al->List($f + ['category' => 'quality'], 'all', 1, 200);
ok('filtro de categoría', !array_filter($lc['rows'], fn($r) => $r['category'] !== 'quality'));
$lo = $al->List($f + ['owner' => 'none'], 'all', 1, 200);
ok('filtro sin responsable', !array_filter($lo['rows'], fn($r) => $r['owner_user_id']));
$lb = $al->List(['allowed' => [2], 'me' => 1], 'all', 1, 200);
ok('aislamiento por sucursal', !array_filter($lb['rows'], fn($r) => $r['branch_id'] !== null && intval($r['branch_id']) !== 2));
$ld = $al->List($f + ['status' => 'done'], 'all', 1, 200);
ok('vista de cerradas incluye las resueltas', count(array_filter($ld['rows'], fn($r) => intval($r['id']) === $id)) === 1);

// ---------------------------------------------------------------- 8. Tablero: consistencia matemática
echo "\n8. Tablero consistente\n";
$d = $al->Dashboard($f, '30d', 30);
$per = $d['period'];
$created = intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE created_at BETWEEN '{$per['fromTs']}' AND '{$per['toTs']}'"));
ok('donut de categorías: total = suma = creadas en el período', $d['categories']['total'] === $created && array_sum(array_column($d['categories']['items'], 'n')) === $created, "$created");
ok('donut de tipos con el mismo total', $d['types']['total'] === $created);
ok('barras por sucursal suman el total', array_sum(array_column($d['branches'], 'n')) === $created);
ok('porcentajes de categoría ≈ 100', $created === 0 || abs(array_sum(array_column($d['categories']['items'], 'pct')) - 100) < 0.6);
ok('KPI críticas = creadas P1 del período', $d['kpis']['critical']['value'] === intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE priority = 'P1' AND created_at BETWEEN '{$per['fromTs']}' AND '{$per['toTs']}'")));
ok('KPI resueltas = resueltas (no descartadas) en el período', $d['kpis']['resolved']['value'] === intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE status <> 'dismissed' AND resolved_at BETWEEN '{$per['fromTs']}' AND '{$per['toTs']}'")));
ok('sparkline con un punto por día', count($d['kpis']['critical']['spark']) === $per['days']);
ok('tendencia: series del mismo largo', count(array_unique([count($d['trend']['labels']), count($d['trend']['generated']), count($d['trend']['resolved']), count($d['trend']['overdue']), count($d['trend']['critical'])])) === 1);
ok('tendencia: críticas ≤ generadas cada día', !array_filter(array_keys($d['trend']['generated']), fn($i) => $d['trend']['critical'][$i] > $d['trend']['generated'][$i]));
$im = $d['impact'];
ok('impacto: total = suma de buckets = suma por tipo (sin doble conteo)', abs($im['total'] - array_sum(array_column($im['buckets'], 'value'))) < 0.01 && abs($im['total'] - ($im['kinds']['confirmed'] + $im['kinds']['estimated'] + $im['kinds']['recoverable'])) < 0.01);
ok('impacto = valor de las alertas activas', abs($im['total'] - floatval($val("SELECT IFNULL(SUM(value), 0) FROM dairy_alerts WHERE status IN ($ACTIVE)"))) < 0.01);
$rs = $d['resolution'];
ok('resolución: resueltas + abiertas + descartadas = total del período', $rs['resolved'] + $rs['open'] + $rs['dismissed'] === $rs['total'], json_encode($rs));
ok('cumplimiento SLA separado del % resuelto', array_key_exists('sla', $rs) && array_key_exists('pct', $rs));
ok('reabiertas contadas', $rs['reopened'] >= 1);
ok('próximas alertas con tramo de tiempo', !array_filter($d['upcoming'], fn($u) => !in_array($u['bucket'], ['En 15 minutos', 'En 30 minutos', 'En 1 hora', 'Hoy', 'Mañana'], true)));
ok('recomendaciones con acción, evidencia y confianza', !array_filter($d['recommendations'], fn($r) => !$r['action'] || !isset($r['evidence'], $r['confidence'])));
$db1 = $al->Dashboard($f + ['branch_id' => 1], '30d', 30);
ok('filtro de sucursal afecta gráficos', $db1['categories']['total'] === intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE branch_id = 1 AND created_at BETWEEN '{$per['fromTs']}' AND '{$per['toTs']}'")));

// ---------------------------------------------------------------- 9. Reportes y vistas
echo "\n9. Reportes y vistas guardadas\n";
$bad = [];
foreach (array_keys(AlertCenterService::REPORT_DIMS) as $dim)
{
	$rep = $al->Report($f, $dim, $per['from'], $per['to']);
	if (array_sum(array_column($rep['rows'], 'total')) !== $created)
	{
		$bad[] = $dim;
	}
}
ok('todos los reportes (sucursal…año) suman el total del período', !$bad, implode(',', $bad));
$rep = $al->Report($f, 'owner', $per['from'], $per['to']);
ok('efectividad de acciones correctivas calculada', $rep['effectiveness']['with_actions'] >= 1, json_encode($rep['effectiveness']));
$vid = $al->SaveView(1, 'Críticas sucursal 1', '?priority=P1&branch_id=1&evil=1');
$views = $al->Views(1);
ok('vista guardada solo con parámetros permitidos', count($views) === 1 && $views[0]['query'] === 'priority=P1&branch_id=1', $views[0]['query'] ?? '');
$al->SaveView(1, 'Críticas sucursal 1', 'priority=P2');
ok('guardar con el mismo nombre actualiza (sin duplicar)', count($al->Views(1)) === 1);
$al->DeleteView(2, $vid);
ok('otro usuario no borra la vista', count($al->Views(1)) === 1);

// ---------------------------------------------------------------- 10. Permisos (acciones permitidas)
echo "\n10. Permisos\n";
$row = ['status' => 'assigned', 'owner_user_id' => 2];
$emp = AlertCenterService::AllowedActions($row, false, 2);
$other = AlertCenterService::AllowedActions($row, false, 3);
$mgr = AlertCenterService::AllowedActions($row, true, 1);
ok('responsable sin rol de gestor: investiga y resuelve, no asigna ni descarta', in_array('resolve', $emp, true) && in_array('investigate', $emp, true) && !in_array('assign', $emp, true) && !in_array('dismiss', $emp, true));
ok('otra persona solo comenta y adjunta', !in_array('resolve', $other, true) && in_array('comment', $other, true) && in_array('evidence', $other, true));
ok('gestor asigna, escala, cambia prioridad y descarta', !array_diff(['assign', 'escalate', 'priority', 'dismiss'], $mgr));
ok('solo gestor reabre una cerrada', in_array('reopen', AlertCenterService::AllowedActions(['status' => 'closed', 'owner_user_id' => 2], true, 1), true) && !in_array('reopen', AlertCenterService::AllowedActions(['status' => 'closed', 'owner_user_id' => 2], false, 2), true));

// ---------------------------------------------------------------- 11. Integración: un solo registro
echo "\n11. Integración con otros módulos\n";
$err = '';
try
{
	$t = \Grocy\Services\TodayCommandService::GetInstance()->Get(null);
}
catch (\Throwable $e)
{
	$t = null;
	$err = $e->getMessage();
}
ok('HOY lee el mismo registro sin errores', $t !== null, $err);
if ($t)
{
	$ids = array_map(fn($x) => intval(substr($x['uid'], 6)), array_filter($t['decisions'], fn($x) => str_starts_with($x['uid'], 'alert:')));
	ok('HOY no crea copias: toda decisión de alerta existe con su id', !array_diff($ids, array_map('intval', $db->query('SELECT id FROM dairy_alerts')->fetchAll(PDO::FETCH_COLUMN))));
	ok('HOY: críticas = P1 activas del centro', intval($t['alerts']['critical']) === intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE priority = 'P1' AND status IN ($ACTIVE)")));
}

// ---------------------------------------------------------------- 12. Centro de mando v3: procedimiento, política de cierre, prevención
echo "\n12. Flujo completo v3 (detectar → prevenir)\n";
$cx = $al->Create(['title' => 'Diferencia de inventario en lácteos', 'detail' => 'Conteo con faltante', 'category' => 'inventory', 'alert_type' => 'count_diff', 'branch_id' => 1, 'severity' => 'danger', 'priority' => 'P1', 'value' => 900, 'client_token' => 'test-v3-1'], 1);
$xid = $cx['id'];
$d = $al->Detail($xid);
ok('flujo: alta manual sin responsable = «Nueva»', $d['alert']['flow_status'] === 'new', $d['alert']['flow_status']);
ok('procedimiento de inventario generado (Kardex → conteo → diferencia → aprobación → conciliación)', count($d['steps']) === 5 && str_contains($d['steps'][0]['href'] ?? '', '/kardex'), json_encode(array_column($d['steps'], 'href')));
$al->Detail($xid);
ok('el procedimiento no se duplica al reabrir el expediente', intval($val("SELECT COUNT(*) FROM dairy_alert_steps WHERE alert_id = $xid")) === 5);
$al->Assign($xid, 2, 1);
$al->Investigate($xid, 'Revisar kardex', 2);
$steps = $al->Steps($xid);
$al->CompleteStep(intval($steps[0]['id']), 'done', 'Movimientos revisados', 2);
ok('primer paso pasa la alerta a ejecución', $val("SELECT status FROM dairy_alerts WHERE id = $xid") === 'in_progress');
expectError('paso con evidencia exige adjunto', fn() => $al->CompleteStep(intval($steps[2]['id']), 'done', null, 2));
expectError('paso de aprobación exige aprobación concedida', fn() => $al->CompleteStep(intval($steps[3]['id']), 'done', null, 2));
expectError('omitir exige motivo', fn() => $al->CompleteStep(intval($steps[1]['id']), 'skipped', '', 2));
$al->CompleteStep(intval($steps[1]['id']), 'skipped', 'Conteo ya ejecutado hoy', 2);
expectError('no se resuelve con pasos pendientes', fn() => $al->Resolve($xid, 'Ajustado', 2));
$al->AddEvidence($xid, 'c.jpg', 'conteo.jpg', 'image/jpeg', 100, 'Hoja de conteo', 2);
$al->CompleteStep(intval($steps[2]['id']), 'done', 'Faltan 6 u.', 2);
$al->RequestApproval($xid, 'Ajuste de -6 u.', 2);
$al->DecideRequest($xid, true, 'Aprobado', 1);
$al->CompleteStep(intval($steps[3]['id']), 'done', null, 2);
$al->CompleteStep(intval($steps[4]['id']), 'done', 'Conciliado', 2);
$st = $al->Resolve($xid, 'Ajuste aprobado y conciliado', 2, ['outcome' => 'loss_confirmed', 'root_cause' => 'Recepción sin verificar', 'final_value' => 540]);
$r = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$xid])->fetch(PDO::FETCH_ASSOC);
ok('crítica → pendiente de validación con resultado, causa e impacto final', $st === 'pending_approval' && $r['outcome'] === 'loss_confirmed' && floatval($r['final_value']) === 540.0 && $r['root_cause'] === 'Recepción sin verificar');
ok('flujo visible: «Pendiente de validación»', AlertCenterService::FlowStatus($r) === 'validation');
expectError('quien resolvió no valida su propio cierre', fn() => $al->Decide($xid, true, null, 2));
$al->Decide($xid, true, 'Validado', 1);
ok('cerrada tras validación = «Cerrada»', AlertCenterService::FlowStatus($q('SELECT * FROM dairy_alerts WHERE id = ?', [$xid])->fetch(PDO::FETCH_ASSOC)) === 'closed');
$cy = $al->Create(['title' => 'Cámara sin evidencia de cierre', 'category' => 'quality', 'alert_type' => 'cold_chain', 'branch_id' => 1, 'severity' => 'danger', 'client_token' => 'test-v3-2'], 1);
$al->Start($cy['id'], 1);
$db->exec("DELETE FROM dairy_alert_steps WHERE alert_id = {$cy['id']}");
expectError('política: crítica sin evidencia no se cierra', fn() => $al->Resolve($cy['id'], 'Todo bien ya', 1));
expectError('plan preventivo exige responsable y fecha', fn() => $al->CreatePrevention($xid, ['root_cause' => 'Recepción sin verificar', 'corrective_action' => 'Doble firma en recepción'], 1));
$pid = $al->CreatePrevention($xid, ['root_cause' => 'Recepción sin verificar', 'corrective_action' => 'Doble firma en recepción', 'owner_user_id' => 2, 'target_date' => date('Y-m-d', strtotime('+14 days'))], 1);
ok('plan preventivo vinculado a la alerta', intval($val("SELECT prevention_id FROM dairy_alerts WHERE id = $xid")) === $pid);
$rec = $al->Recurrence($al->Get($xid) + ['recurrence_of' => null]);
ok('recurrencia: el expediente muestra el plan vigente', $rec['plan'] && intval($rec['plan']['id']) === $pid);
$db->exec("UPDATE dairy_alerts SET rule_code = 'count_difference' WHERE id = $xid");
$db->exec("UPDATE dairy_alert_prevention SET rule_code = 'count_difference' WHERE id = $pid");
$ref = new ReflectionMethod($al, 'Insert');
$nid = $ref->invoke($al, ['rule_code' => 'count_difference', 'dedupe_key' => 'test-rec-' . time(), 'branch_id' => 1, 'severity' => 'warning', 'title' => 'Diferencia otra vez', 'product_id' => null, 'status' => 'open', 'category' => 'inventory', 'alert_type' => 'count_diff', 'priority' => 'P3', 'due_at' => date('Y-m-d H:i:s', time() + 3600)], date('Y-m-d H:i:s', time() + 5));
$nr = $q('SELECT * FROM dairy_alerts WHERE id = ?', [$nid])->fetch(PDO::FETCH_ASSOC);
ok('reaparece: nuevo incidente vinculado al anterior y al plan', intval($nr['recurrence_of']) === $xid && intval($nr['prevention_id']) === $pid, json_encode([$nr['recurrence_of'], $nr['prevention_id']]));
expectError('plan con recurrencia no puede marcarse efectivo', fn() => $al->VerifyPrevention($pid, true, 'Sin repeticiones', 1));
$al->VerifyPrevention($pid, false, 'Reapareció a los días', 1);
ok('plan marcado no efectivo', $val("SELECT status FROM dairy_alert_prevention WHERE id = $pid") === 'ineffective');

// Tablero de mando: consistencia entre KPI, gráficos, tabla y «Hoy»
$per = AlertCenterService::Period('30d');
$cmd = $al->Command($f, $per);
$act = intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE status IN ($ACTIVE)"));
ok('KPI activas = alertas activas', $cmd['kpi']['active']['n'] === $act, $cmd['kpi']['active']['n'] . " vs $act");
ok('carga por grupo suma las activas (cada alerta en un solo grupo)', array_sum(array_column($cmd['load'], 'n')) === $act);
ok('dona de prioridad suma las activas', $cmd['priority']['total'] === $act);
ok('ranking por sucursal suma las activas', array_sum(array_column($cmd['branches'], 'n')) === $act);
$lt = $al->List($f, 'all', 1, 5);
ok('tabla (todas) = KPI activas', $lt['total'] === $act);
ok('KPI críticas = pestaña Críticas', $cmd['kpi']['critical']['n'] === $lt['counts']['P1']);
ok('KPI vencidas = pestaña Vencidas', $cmd['kpi']['critical']['overdue'] === $lt['counts']['overdue']);
ok('impacto = confirmado + potencial', abs($cmd['kpi']['impact']['total'] - $cmd['kpi']['impact']['confirmed'] - $cmd['kpi']['impact']['potential']) < 0.01);
if ($t)
{
	$t2 = \Grocy\Services\TodayCommandService::GetInstance()->Get(null);
	ok('«requieren acción» coincide con Hoy', $cmd['kpi']['critical']['action'] === intval($t2['alerts']['action']), $cmd['kpi']['critical']['action'] . ' vs ' . $t2['alerts']['action']);
	ok('críticas coinciden con Hoy', $cmd['kpi']['critical']['n'] === intval($t2['alerts']['critical']));
}
$ev = $al->Evolution($f, 30);
ok('evolución: pendientes del último día = activas sin reabiertas', end($ev['backlog']) <= $act + 2 && count($ev['labels']) === 30, end($ev['backlog']) . " vs $act");
$evy = $al->Evolution($f, 'year');
ok('evolución anual semanal', $evy['unit'] === 'week' && count($evy['labels']) <= 53 && array_sum($evy['generated']) === $evy['totals']['generated']);
$rk = $al->RiskTrend($f, 30);
ok('riesgo económico acumulado sin negativos', min($rk['accumulated']) >= 0 && count($rk['prev_accumulated']) === count($rk['accumulated']));
$lg = $al->List($f + ['group' => 'cold_chain'], 'all', 1, 200);
ok('filtro por grupo operativo (cadena de frío)', !array_filter($lg['rows'], fn($r) => $r['alert_type'] !== 'cold_chain'));
$lf = $al->List($f + ['flow' => 'new'], 'all', 1, 200);
ok('filtro por estado del flujo', !array_filter($lf['rows'], fn($r) => $r['flow_status'] !== 'new'));
ok('owners: suma = alertas en curso', array_sum(array_column($cmd['owners'], 'n')) === intval($val('SELECT COUNT(*) FROM dairy_alerts WHERE status IN (' . AlertCenterService::In(AlertCenterService::WORKING) . ')')));

// Supervisor: asigna y valida cierres no críticos; no valida críticos ni descarta
$sa = AlertCenterService::AllowedActions(['status' => 'open', 'owner_user_id' => 5, 'priority' => 'P3'], false, 9, true);
ok('supervisor puede asignar y escalar', in_array('assign', $sa, true) && in_array('escalate', $sa, true) && !in_array('dismiss', $sa, true));
$sv = AlertCenterService::AllowedActions(['status' => 'pending_approval', 'owner_user_id' => 5, 'priority' => 'P1'], false, 9, true);
ok('supervisor no valida cierres críticos', !in_array('decide', $sv, true));
$ea = AlertCenterService::AllowedActions(['status' => 'assigned', 'owner_user_id' => 9, 'priority' => 'P2'], false, 9);
ok('empleado ejecuta sus pasos, adjunta evidencia y pide ayuda; no asigna', in_array('step', $ea, true) && in_array('evidence', $ea, true) && in_array('help', $ea, true) && !in_array('assign', $ea, true));

echo "\n$count verificaciones · " . ($fails ? "$fails FALLAS" : 'todas OK') . "\n";
exit($fails ? 1 : 0);
