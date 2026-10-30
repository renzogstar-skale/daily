<?php

// DAIRY Etapa 30: regla «Lotes por vencer» en el Centro de Alertas (flujo vencimiento → alerta → responsable → acción FEFO → cierre).
// Una alerta por lote (stock_id + fecha) que vence dentro del umbral de días; se cierra sola si el lote se vende, se traslada o se da de baja.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->prepare('INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, threshold, threshold_label, severity, due_hours, requires_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
	->execute(['expiring_lots', 'Lotes por vencer', 'Lotes con stock que vencen dentro del umbral y no están en cuarentena: rebajar (FEFO), trasladar o reubicar al frente antes de que sean merma. Umbral = días.', 3, 'días', 'warning', 24, 0]);
