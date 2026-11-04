<?php

// DAILY — Reglas de integridad del ciclo operativo (auditoría de ciclos 2/11/2026).
// Entran al Centro de Alertas existente: responsable, plazo, resolución en el módulo de origen y autocierre
// cuando la condición desaparece. Solo inserta reglas nuevas (INSERT OR IGNORE): no modifica datos operativos.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$rule = $db->prepare('INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, enabled, threshold, threshold_label, severity, due_hours, category, alert_type, impact_bucket, impact_kind, recommended_action, frequency_minutes)
	VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 60)');
foreach ([
	['expired_lot_unblocked', 'Lote vencido disponible para venta', 'Lote con stock y fecha de vencimiento pasada que no está en cuarentena', null, null, 'danger', 2, 'quality', 'expiry', 'waste', 'confirmed', 'Bloquear el lote en cuarentena y decidir merma o devolución al proveedor'],
	['waste_approval_stale', 'Merma sin decisión', 'Merma pendiente de aprobación por más días que el umbral', 3, 'días', 'warning', 24, 'waste', 'waste', 'waste', 'estimated', 'Aprobar o rechazar la merma en Aprobaciones de Merma'],
	['waste_pending_deducted', 'Merma descontada sin aprobación', 'Merma pendiente de aprobación cuyo inventario ya fue descontado (incumple segregación de funciones)', null, null, 'danger', 24, 'systems', 'waste', 'inventory', 'confirmed', 'Aprobar con segregación o revertir el descuento con ajuste autorizado'],
	['order_unconciliated', 'Despacho sin conciliar', 'Pedido despachado sin conciliación de lo preparado contra lo entregado', 2, 'días', 'warning', 48, 'operations', 'other', 'process', 'estimated', 'Conciliar el pedido en el Centro de Despachos'],
	['cash_shift_stale', 'Turno de caja sin cerrar', 'Turno de caja abierto más horas que el umbral', 24, 'horas', 'danger', 4, 'sales', 'sales', 'sales_risk', 'estimated', 'Cerrar y arquear el turno de caja en el POS'],
	['po_not_sent', 'Orden de compra aprobada sin enviar', 'Orden aprobada que no se envió al proveedor dentro del umbral', 1, 'días', 'warning', 24, 'purchasing', 'purchasing', 'sales_risk', 'estimated', 'Enviar la orden al proveedor o anularla con motivo'],
	['return_no_destination', 'Devolución cerrada sin destino', 'Devolución conciliada o cerrada sin reingreso a stock ni merma registrada', null, null, 'warning', 48, 'operations', 'waste', 'inventory', 'estimated', 'Registrar el destino de la mercadería devuelta (stock, cuarentena o merma)'],
	['transfer_without_movement', 'Traslado en tránsito sin salida de stock', 'Traslado marcado en tránsito sin movimiento de salida en el origen', null, null, 'danger', 24, 'systems', 'other', 'inventory', 'confirmed', 'Revisar el traslado: registrar la salida o anularlo con motivo'],
	['tasks_unassigned_overdue', 'Tareas vencidas sin responsable', 'Tareas de tienda vencidas que nadie tiene asignadas (agrupadas por sucursal)', null, null, 'warning', 8, 'staff', 'staff', 'process', 'estimated', 'Asignar responsables por rol y turno en Tareas y Roles'],
] as $r)
{
	$rule->execute($r);
}
