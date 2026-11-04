<?php

// DAILY — Automatización «lote vencido → cuarentena» (conecta Alerta de vencimiento → Calidad → Bloqueo).
// Se crea INACTIVA: aislar stock cambia lo vendible, así que la activa un responsable desde Configuración / Automatizaciones.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
if (!$db->query("SELECT 1 FROM automation_rules WHERE code = 'QUA_EXPIRED_BLOCK'")->fetchColumn())
{
	$db->prepare("INSERT INTO automation_rules (name, event_trigger, condition_json, action_type, action_payload_json, is_active, code, description, priority, cooldown_minutes, max_retries, created_at, updated_at)
		VALUES (?, 'alert_created', ?, 'quarantine_lot', ?, 0, 'QUA_EXPIRED_BLOCK', ?, 'P1', 0, 3, datetime('now', 'localtime'), datetime('now', 'localtime'))")->execute([
		'Lote vencido → cuarentena',
		json_encode(['rule_code' => 'expired_lot_unblocked']),
		json_encode(['reason' => 'Lote vencido: bloqueo automático de venta'], JSON_UNESCAPED_UNICODE),
		'Aísla en cuarentena el stock libre de un lote vencido apenas se detecta, para que el POS no lo venda. Calidad decide luego merma o devolución.',
	]);
}
