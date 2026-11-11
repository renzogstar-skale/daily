<?php
/**
 * CRM de clientes (CustomerCrmService): métricas solo de clientes identificados, ciclo de vida y segmentos,
 * protección de datos personales, validación de identificación, duplicados y fusión, consentimientos, solicitudes,
 * atención, puntos y trazabilidad. Uso: php tests/dairy/test_crm.php [base.db]
 */
require __DIR__ . '/bootstrap.php';

use Grocy\Services\CustomerCrmService as CRM;

$crm = CRM::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

echo "Esquema\n";
$cols = array_column($db->query('PRAGMA table_info(customers)')->fetchAll(PDO::FETCH_ASSOC), 'name');
ok('customers tiene identificadores normalizados y fusión', !array_diff(['tax_id_norm', 'email_norm', 'phone_norm', 'is_anonymous', 'merged_into_id'], $cols));
foreach (['customer_consents', 'customer_preferences', 'customer_requests', 'customer_interactions', 'customer_loyalty_ledger', 'customer_audit_log'] as $t)
{
	ok("tabla $t", (bool)$val("SELECT 1 FROM sqlite_master WHERE name = '$t'"));
}
ok('cliente 1 marcado como anónimo', intval($val('SELECT is_anonymous FROM customers WHERE id = 1')) === 1);
ok('permisos CUSTOMERS_PII y CUSTOMERS_MANAGE', intval($val("SELECT COUNT(*) FROM permission_hierarchy WHERE name IN ('CUSTOMERS_PII','CUSTOMERS_MANAGE')")) === 2);

echo "Métricas\n";
$list = $crm->CustomersWithMetrics();
ok('el cliente de mostrador no entra en el CRM', !isset($list[1]));
$anyBuyer = null;
foreach ($list as $c)
{
	if ($c['tx'] > 0)
	{
		$anyBuyer = $c;
		break;
	}
}
ok('hay clientes con compras', $anyBuyer !== null);
$sqlTx = intval($val("SELECT COUNT(*) FROM sales WHERE status = 'completed' AND customer_id = " . intval($anyBuyer['id'])));
ok('compras coinciden con ventas completadas', $anyBuyer['tx'] === $sqlTx, $anyBuyer['tx'] . ' vs ' . $sqlTx);
$sqlNet = round(floatval($val("SELECT SUM(s.total_amount) - IFNULL((SELECT SUM(r.amount) FROM daily_pos_returns r JOIN sales x ON x.id = r.sale_id WHERE x.customer_id = " . intval($anyBuyer['id']) . " AND x.status = 'completed'), 0) FROM sales s WHERE s.status = 'completed' AND s.customer_id = " . intval($anyBuyer['id']))), 2);
ok('venta neta descuenta devoluciones', abs($anyBuyer['revenue'] - $sqlNet) < 0.02, $anyBuyer['revenue'] . ' vs ' . $sqlNet);
ok('ticket promedio = venta / compras', abs($anyBuyer['avg_ticket'] - round($anyBuyer['revenue'] / $anyBuyer['tx'], 2)) < 0.01);
ok('cada comprador tiene segmento y tier', !array_filter($list, fn($c) => $c['tx'] > 0 && (!$c['value_tier'] || $c['segment'] === 'none')));
ok('ciclo de vida válido', !array_filter($list, fn($c) => !isset(CRM::LIFECYCLE[$c['lifecycle']])));
$o = $crm->Overview(null, 90);
ok('panel: nuevos + recurrentes = compradores', $o['new_period'] + $o['recurrent_period'] === $o['buyers_period']);
ok('panel: venta identificada ≤ 100 %', $o['identified_share_pct'] === null || $o['identified_share_pct'] <= 100);
ok('panel: no expone métricas de inventario', !array_intersect(array_keys($o), ['stock', 'kardex', 'waste', 'picking', 'purchase_orders']));

echo "Alta y calidad de identificación\n";
expectError('alta sin identificador fuerte', fn() => $crm->SaveCustomer(['name' => 'Cliente Sin Datos']));
expectError('correo inválido', fn() => $crm->SaveCustomer(['name' => 'Cliente Correo', 'email' => 'no-es-correo']));
$a = $crm->SaveCustomer(['name' => 'María Pérez Prueba', 'tax_id' => 'V-12.345.678', 'phone' => '+58 414-555-0101', 'email' => 'maria.prueba@example.com', 'consent_data_processing' => 1, 'consent_source' => 'store']);
ok('alta con identificadores', $a['saved'] === true && $a['id'] > 0);
$idA = $a['id'];
ok('documento normalizado', $val("SELECT tax_id_norm FROM customers WHERE id = $idA") === 'V12345678');
ok('consentimiento inicial registrado', $crm->Consents($idA)['current']['data_processing']['granted'] === true);
expectError('mismo documento con otro formato se rechaza', fn() => $crm->SaveCustomer(['name' => 'Otra María', 'tax_id' => 'v12345678']));
$w = $crm->SaveCustomer(['name' => 'M. Pérez', 'phone' => '0414 555 0101']);
ok('mismo teléfono avisa duplicado', ($w['duplicate_warning'] ?? false) && in_array('teléfono', reset($w['duplicates'])['reasons'], true));
$b = $crm->SaveCustomer(['name' => 'M. Pérez', 'phone' => '0414 555 0101', 'confirm_duplicate' => 1]);
ok('duplicado confirmado se crea', $b['saved'] === true);
$idB = $b['id'];
$groups = $crm->DuplicateGroups();
$g = array_values(array_filter($groups, fn($g) => in_array($idA, array_column($g['customers'], 'id'), true)));
ok('grupo de duplicados detectado con confianza alta', $g && $g[0]['confidence'] === 'alta' && in_array($idB, array_column($g[0]['customers'], 'id'), true));

echo "Protección de datos\n";
$_COOKIE['daily_simulated_role'] = 'supervisor';
$masked = $crm->Protect($db->query("SELECT * FROM customers WHERE id = $idA")->fetch(PDO::FETCH_ASSOC));
ok('rol sin permiso ve documento enmascarado', $masked['tax_id'] === '••••678' && !$masked['pii_visible'], $masked['tax_id']);
ok('rol sin permiso ve correo enmascarado', $masked['email'] === 'm•••@example.com');
ok('rol sin permiso no ve dirección ni nacimiento', $masked['birth_date'] === null);
ok('identificadores normalizados nunca salen', !isset($masked['tax_id_norm']));
$found = array_filter($crm->Directory(null, ['q' => '04145550101']), fn($c) => $c['id'] === $idA);
ok('búsqueda por teléfono completo funciona sin revelarlo', count($found) === 1 && reset($found)['phone'] === '••••101');
expectError('rol sin permiso no puede editar', fn() => $crm->SaveCustomer(['id' => $idA, 'name' => 'Cambio', 'phone' => '1234567']));
expectError('rol sin permiso no puede fusionar', fn() => $crm->Merge($idB, $idA));
unset($_COOKIE['daily_simulated_role']);
$beforeAudit = intval($val("SELECT COUNT(*) FROM customer_audit_log WHERE customer_id = $idA AND action = 'view_pii'"));
$p = $crm->Profile($idA);
ok('gerente ve datos completos', $p['customer']['pii_visible'] && $p['customer']['email'] === 'maria.prueba@example.com');
ok('consulta de datos personales queda auditada', intval($val("SELECT COUNT(*) FROM customer_audit_log WHERE customer_id = $idA AND action = 'view_pii'")) === $beforeAudit + 1);
$crm->SaveCustomer(['id' => $idA, 'name' => 'María Pérez Prueba', 'tax_id' => 'V-12.345.678', 'phone' => '+58 414-555-0101', 'email' => 'maria.p@example.com', 'confirm_duplicate' => 1]);
$upd = json_decode($val("SELECT detail FROM customer_audit_log WHERE customer_id = $idA AND action = 'update' ORDER BY id DESC LIMIT 1"), true);
ok('auditoría guarda campos cambiados sin valores', ($upd['fields'] ?? null) === ['email'] && !str_contains(json_encode($upd), 'maria.p@'), json_encode($upd));

echo "Solicitudes y atención\n";
$r = $crm->SaveRequest(['customer_id' => $idB, 'category' => 'complaint', 'priority' => 'high', 'subject' => 'Producto en mal estado']);
ok('solicitud creada con código', str_starts_with($r['code'], 'SOL-'));
ok('vencimiento según prioridad alta (+2 días)', $val("SELECT due_date FROM customer_requests WHERE id = {$r['id']}") === date('Y-m-d', strtotime('+2 days')));
expectError('cerrar sin resolución', fn() => $crm->SaveRequest(['id' => $r['id'], 'status' => 'resolved']));
$crm->SaveRequest(['id' => $r['id'], 'status' => 'resolved', 'resolution' => 'Se cambió el producto en tienda']);
ok('solicitud resuelta con fecha', (bool)$val("SELECT resolved_at FROM customer_requests WHERE id = {$r['id']}"));
$crm->AddInteraction($idB, ['kind' => 'call', 'summary' => 'Llamada de seguimiento', 'request_id' => $r['id']]);
ok('historial de atención incluye alta, cambio de estado y llamada', intval($val("SELECT COUNT(*) FROM customer_interactions WHERE customer_id = $idB")) === 3);
expectError('interacción sin resumen', fn() => $crm->AddInteraction($idB, ['kind' => 'call', 'summary' => 'x']));

echo "Consentimientos y preferencias\n";
$crm->SetConsent($idA, 'marketing_whatsapp', true, 'pos', 'Formulario 001');
$crm->SetConsent($idA, 'marketing_whatsapp', false, 'phone', null);
$cs = $crm->Consents($idA);
ok('consentimiento vigente = último registro', $cs['current']['marketing_whatsapp']['granted'] === false);
ok('historial conserva autorización y revocación', count(array_filter($cs['history'], fn($h) => $h['purpose'] === 'marketing_whatsapp')) === 2);
ok('sin registro no se asume consentimiento', $cs['current']['marketing_sms']['granted'] === null);
expectError('finalidad inválida', fn() => $crm->SetConsent($idA, 'spam', true, 'pos', null));
$crm->SetPreferences($idA, ['preferred_channel' => 'whatsapp', 'remarks' => 'Prefiere entregas por la tarde']);
ok('preferencias guardadas', $crm->Preferences($idA)['preferred_channel'] === 'whatsapp');
expectError('preferencia con valor inválido', fn() => $crm->SetPreferences($idA, ['preferred_channel' => 'paloma']));

echo "Fusión\n";
$saleId = intval($val('SELECT id FROM sales WHERE customer_id = ' . intval($anyBuyer['id']) . " AND status = 'completed' LIMIT 1"));
$db->exec("UPDATE sales SET customer_id = $idB WHERE id = $saleId");
$m = $crm->Merge($idB, $idA);
ok('fusión traslada ventas y solicitudes', $m['moved']['sales'] === 1 && $m['moved']['customer_requests'] === 1);
ok('origen queda fusionado e inactivo', intval($val("SELECT merged_into_id FROM customers WHERE id = $idB")) === $idA && intval($val("SELECT active FROM customers WHERE id = $idB")) === 0);
ok('fusionado no aparece en el directorio', !array_filter($crm->Directory(null, []), fn($c) => $c['id'] === $idB));
ok('perfil fusionado redirige al destino', ($crm->Profile($idB)['merged_into_id'] ?? null) === $idA);
ok('destino suma la compra trasladada', $crm->CustomersWithMetrics()[$idA]['tx'] === 1);
$c3 = $crm->SaveCustomer(['name' => 'Empresa Distinta', 'tax_id' => 'J-99999999-1', 'confirm_duplicate' => 1])['id'];
expectError('no fusiona documentos distintos', fn() => $crm->Merge($c3, $idA));
expectError('no fusiona el cliente de mostrador', fn() => $crm->Merge(1, $idA));

echo "Fidelización\n";
ok('programa desactivado por defecto: sin puntos', $crm->Loyalty($idA) === ['enabled' => false]);
expectError('no se canjea con programa inactivo', fn() => $crm->LoyaltyMove($idA, 'redeem', 10, 'Canje de prueba'));
$crm->SaveSettings(['loyalty_enabled' => 1, 'amount_per_point' => 10, 'loyalty_start' => '2020-01-01']);
$l = $crm->Loyalty($idA);
$net = $crm->CustomersWithMetrics()[$idA]['revenue'];
ok('puntos acumulados por compras netas', $l['enabled'] && $l['balance'] === intval(floor($net / 10)), $l['balance'] . ' vs ' . floor($net / 10));
$crm->SyncEarned();
ok('acumulación idempotente', $crm->Loyalty($idA)['balance'] === $l['balance']);
expectError('canje mayor al saldo', fn() => $crm->LoyaltyMove($idA, 'redeem', $l['balance'] + 1, 'Canje excesivo'));
if ($l['balance'] > 0)
{
	$crm->LoyaltyMove($idA, 'redeem', 1, 'Canje de prueba');
	ok('canje descuenta saldo', $crm->Loyalty($idA)['balance'] === $l['balance'] - 1);
}

echo "CRM Retail 7 módulos, finanzas separadas y regionalización\n";
$ov2 = $crm->Overview(null, 30);
$segSum = array_sum(array_column($ov2['segments'], 'count'));
ok('RFM suma = clientes registrados', $segSum === $ov2['registered'], $segSum . ' vs ' . $ov2['registered']);
ok('en riesgo KPI = en riesgo RFM', $ov2['at_risk'] === $ov2['segments']['at_risk']['count']);
ok('meta recompra diferencia exacta', abs($ov2['repurchase_diff_pp'] - round($ov2['repurchase_pct'] - $ov2['repurchase_goal_pct'], 1)) < 0.05);
ok('CLV promedio excluye mostrador', $ov2['avg_clv'] >= 0 && $ov2['avg_clv'] < 60000);
ok('regional adaptado a configuración', !empty($ov2['regional']['tax_id_label']) && !empty($ov2['regional']['currency_code']));

$crReq = $crm->SaveRequest(['customer_id' => $idA, 'category' => 'credit', 'priority' => 'normal', 'subject' => 'Solicitud de cupo prueba', 'requested_credit_limit' => 2500]);
expectError('crédito no se cierra sin dictamen financiero', fn() => $crm->SaveRequest(['id' => $crReq['id'], 'status' => 'resolved', 'resolution' => 'Intentar cerrar desde CRM']));
$_COOKIE['daily_simulated_role'] = 'supervisor';
expectError('rol sin permiso financiero no aprueba crédito', fn() => $crm->DecideCreditRequest($crReq['id'], 'approved_finance', 'Intento no autorizado', 2500));
unset($_COOKIE['daily_simulated_role']);
$dec = $crm->DecideCreditRequest($crReq['id'], 'approved_finance', 'Historial de pagos verificado', 2500);
ok('finanzas aprueba crédito y actualiza cupo', $dec['decision'] === 'approved_finance' && floatval($val("SELECT credit_limit FROM customers WHERE id = $idA")) === 2500.0);

$cmp = $crm->SaveCampaign(['name' => 'Campaña Retención Prueba', 'target_segment' => 'at_risk', 'channel' => 'whatsapp', 'offer_text' => '10% dto']);
ok('campaña creada con código CMP-', str_starts_with($cmp['code'], 'CMP-') && count($crm->Campaigns(null, 30)) >= 1);
$runAuto = $crm->RunAutomations(null, 30);
ok('automatizaciones evalúan coincidencias reales', $runAuto['ok'] === true && $runAuto['rules_evaluated'] >= 1);

echo "\n$count verificaciones, $fails fallas\n";
exit($fails ? 1 : 0);
