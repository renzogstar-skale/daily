<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * DAILY — Historial de Ventas
 * Centro de Trazabilidad, Auditoría y Conciliación Comercial
 * 
 * Localizar, reconstruir, investigar, conciliar y auditar cualquier transacción:
 * - Buscador universal y 12 filtros avanzados
 * - 7 tarjetas de indicadores (KPIs)
 * - Explorador maestro de transacciones (65%)
 * - Ficha 360° de transacción con detalle comercial y pagos (35%)
 * - Línea de tiempo completa de eventos por transacción
 * - Estado visual de conciliación por fuentes comparadas
 * - Excepciones que requieren investigación
 * - Gráfico de análisis de anulaciones y descuentos por caja
 * - Conciliación POS y diferencias detectadas
 * - Seguimiento de devoluciones vinculadas a tickets originales
 * - Registro append-only de auditoría de cambios
 * - Control de sincronización por pasarela, POS y cierres
 * - Gestión de casos de auditoría con contadores de estado
 * - Comparador cara a cara de posibles duplicidades
 */
class SalesAuditTraceService extends BaseService
{
	private static ?self $instance = null;

	public static function GetInstance(): self
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public function GetSalesAuditDashboard(array $filters = []): array
	{
		$db = $this->Db();

		// 1. KPI Cards (Fila 2)
		$kpis = [
			'registered_count' => 5611,
			'registered_sub' => 'en el período',
			'reconciled_count' => 5437,
			'reconciled_sub' => '96.9 % verificadas',
			'pending_count' => 118,
			'pending_sub' => '140 esperan conciliar',
			'voided_count' => 41,
			'voided_sub' => '7.3 por 1,000 operaciones',
			'differences_count' => 15,
			'differences_sub' => '$469.04 afectadas',
			'avg_time' => '42 min',
			'avg_time_sub' => 'registro → acreditación',
			'require_review_count' => 23,
			'require_review_sub' => 'con excepciones abiertas'
		];

		// 2. Explorador Maestro de Transacciones
		$transactionsMaster = [
			[
				'id' => 'TX-4872',
				'ticket' => 'T-48722',
				'datetime' => '05/11 16:41',
				'branch' => 'Hallandale',
				'branch_full' => 'Hallandale Beach',
				'register' => 'Caja 2',
				'cashier' => 'Cajero 02',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$57.06',
				'amount_val' => 57.06,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Completada',
				'commercial_status_badge' => 'badge-normal',
				'reconciliation_status' => 'Conciliado',
				'reconciliation_status_badge' => 'badge-normal',
				'selected' => false
			],
			[
				'id' => 'TX-4871',
				'ticket' => 'T-48721',
				'datetime' => '05/11 16:35',
				'branch' => 'Miami Beach',
				'branch_full' => 'Miami Beach',
				'register' => 'Caja 1',
				'cashier' => 'Cajero 03',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$82.20',
				'amount_val' => 82.20,
				'payment_method' => 'Efectivo',
				'commercial_status' => 'Completada',
				'commercial_status_badge' => 'badge-normal',
				'reconciliation_status' => 'Pendiente',
				'reconciliation_status_badge' => 'badge-atencion',
				'selected' => false
			],
			[
				'id' => 'TX-4870',
				'ticket' => 'T-48720',
				'datetime' => '05/11 16:22',
				'branch' => 'Sunny Isles',
				'branch_full' => 'Sunny Isles Beach',
				'register' => 'Caja 3',
				'cashier' => 'Cajero 05',
				'type' => 'Devolución',
				'type_badge' => 'badge-atencion',
				'amount' => '-$14.50',
				'amount_val' => -14.50,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Devuelto total',
				'commercial_status_badge' => 'badge-atencion',
				'reconciliation_status' => 'Conciliado',
				'reconciliation_status_badge' => 'badge-normal',
				'selected' => false
			],
			[
				'id' => 'TX-4869',
				'ticket' => 'T-48719',
				'datetime' => '05/11 15:50',
				'branch' => 'Sucursal 07',
				'branch_full' => 'Hollywood (Sucursal 07)',
				'register' => 'Caja 2',
				'cashier' => 'Cajero 07',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$12.50',
				'amount_val' => 12.50,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Anulado',
				'commercial_status_badge' => 'badge-sin-datos',
				'reconciliation_status' => 'Descuadre cobro',
				'reconciliation_status_badge' => 'badge-critico',
				'selected' => false
			],
			[
				'id' => 'TX-4735',
				'ticket' => 'T-48585',
				'datetime' => '05/11 12:10',
				'branch' => 'Miami Beach',
				'branch_full' => 'Miami Beach',
				'register' => 'Caja 1',
				'cashier' => 'Cajero 03',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$212.15',
				'amount_val' => 212.15,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Completada',
				'commercial_status_badge' => 'badge-normal',
				'reconciliation_status' => 'Conciliado',
				'reconciliation_status_badge' => 'badge-normal',
				'selected' => false
			],
			[
				'id' => 'TX-4732',
				'ticket' => 'T-48582',
				'datetime' => '05/11 10:30',
				'branch' => 'Hallandale',
				'branch_full' => 'Hallandale Beach',
				'register' => 'Caja 2',
				'cashier' => 'Cajero 02',
				'type' => 'Devolución',
				'type_badge' => 'badge-atencion',
				'amount' => '-$12.00',
				'amount_val' => -12.00,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Devuelto parcial',
				'commercial_status_badge' => 'badge-atencion',
				'reconciliation_status' => 'Pendiente',
				'reconciliation_status_badge' => 'badge-atencion',
				'selected' => false
			],
			[
				'id' => 'TX-4731',
				'ticket' => 'T-48581',
				'datetime' => '04/11 09:20',
				'branch' => 'Hallandale',
				'branch_full' => 'Hallandale Beach',
				'register' => 'Caja 2',
				'cashier' => 'Cajero 02',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$18.50',
				'amount_val' => 18.50,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Completada',
				'commercial_status_badge' => 'badge-normal',
				'reconciliation_status' => 'Descuadre cobro',
				'reconciliation_status_badge' => 'badge-critico',
				'selected' => false
			],
			[
				'id' => 'TX-4730',
				'ticket' => 'T-48580',
				'datetime' => '04/11 08:12',
				'branch' => 'Sunny Isles',
				'branch_full' => 'Sunny Isles Beach',
				'register' => 'Caja 1',
				'cashier' => 'Cajero 05',
				'type' => 'Venta',
				'type_badge' => 'badge-normal',
				'amount' => '$32.80',
				'amount_val' => 32.80,
				'payment_method' => 'Tarjeta',
				'commercial_status' => 'Completada',
				'commercial_status_badge' => 'badge-normal',
				'reconciliation_status' => 'Conciliado',
				'reconciliation_status_badge' => 'badge-normal',
				'selected' => false
			]
		];

		// 3. Ficha 360° de Transacción (TX-4712 por defecto según mockup)
		$ficha360 = [
			'tx_id' => 'TX-4712',
			'ticket' => 'T-48520',
			'datetime' => '05/11/2026 09:41:12 · ET',
			'branch' => 'Hallandale Beach',
			'register' => 'Caja 2',
			'cashier' => 'Cajero 02',
			'channel' => 'POS en tienda',
			'commercial_status' => 'Devuelto parcialmente',
			'commercial_status_badge' => 'badge-atencion',
			'reconciliation_status' => 'Diferencia de conciliación',
			'reconciliation_status_badge' => 'badge-critico',
			'lines_count' => 4,
			'items' => [
				[
					'name' => 'Filete de salmón',
					'qty' => 1,
					'price' => 14.50,
					'total' => 14.50
				],
				[
					'name' => 'Pizza 4 quesos',
					'qty' => 5,
					'price' => 6.98,
					'total' => 34.90
				],
				[
					'name' => 'Helado vainilla 1.5L',
					'qty' => 2,
					'price' => 5.98,
					'total' => 11.96
				]
			],
			'subtotal' => 61.36,
			'discount' => -3.07,
			'discount_label' => 'Descuento 5 %',
			'tax' => 0.00,
			'total' => 57.06,
			'payment_method' => 'Tarjeta',
			'auth_status' => 'Autorizado',
			'masked_ref' => 'AUTH-****-8761',
			'refund_amount' => '$14.50',
			'refund_status' => 'sin confirmar'
		];

		// 4. Línea de Tiempo de Eventos — TX-4712
		$timeline = [
			[
				'time' => '09:35',
				'title' => 'Venta iniciada',
				'desc' => 'Cajero 02 · POS Caja 2 → En curso · ref. POS',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '09:37',
				'title' => 'Productos escaneados',
				'desc' => 'Sistema POS · 3 líneas · En curso',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '09:39',
				'title' => 'Descuento aplicado (5 %)',
				'desc' => 'Cajero 02 · dentro de política · evidencia: regla de promoción',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '09:40',
				'title' => 'Pago autorizado',
				'desc' => 'Pasarela de pagos · AUTH-****-8761 · pendiente → autorizado',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '09:41',
				'title' => 'Venta completada',
				'desc' => 'Sistema POS · En curso → Completada',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '10:15',
				'title' => 'Solicitud de devolución (1 u. salmón)',
				'desc' => 'Cajero 02 · ref. DEV-1073',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '10:20',
				'title' => 'Devolución aprobada',
				'desc' => 'Supervisor 02 · Completada → Devuelto parcialmente',
				'dot_class' => 'dot-green',
				'is_warning' => false
			],
			[
				'time' => '10:21',
				'title' => 'Reembolso registrado en POS',
				'desc' => 'Sin confirmación en pasarela · genera diferencia de conciliación',
				'dot_class' => 'dot-amber',
				'is_warning' => true
			]
		];

		// 5. Estado de Conciliación por Fuentes Comparadas
		$reconciliationSources = [
			'totals' => [
				'reconciled' => 5437,
				'pending' => 118,
				'differences' => 15,
				'voided' => 41
			],
			'sources' => [
				[
					'name' => 'Operaciones del POS',
					'status' => 'Coincide',
					'badge_class' => 'badge-normal',
					'detail' => '5,611 tickets sincronizados'
				],
				[
					'name' => 'Pagos autorizados y capturados',
					'status' => 'Diferencia',
					'badge_class' => 'badge-critico',
					'detail' => '15 referencias sin cobro en pasarela'
				],
				[
					'name' => 'Devoluciones y anulaciones',
					'status' => 'Atención',
					'badge_class' => 'badge-atencion',
					'detail' => '3 reembolsos pendientes de cierre'
				],
				[
					'name' => 'Cierres de caja',
					'status' => 'Atención',
					'badge_class' => 'badge-atencion',
					'detail' => '1 terminal con arqueo desbalanceado'
				],
				[
					'name' => 'Movimientos relacionados',
					'status' => 'Sin datos',
					'badge_class' => 'badge-sin-datos',
					'detail' => 'Telemetría de pagos externos no disponible'
				]
			]
		];

		// 6. Transacciones que Requieren Investigación
		$exceptions = [
			[
				'id' => 'EX-9121',
				'tx_id' => 'TX-4859',
				'branch' => 'Sucursal 07',
				'exception_type' => 'Pago capturado sin venta',
				'amount' => '$70.00',
				'date' => '05/11',
				'priority' => 'Alta',
				'priority_badge' => 'badge-critico',
				'responsible' => 'Supervisor 07',
				'status' => 'En investigación',
				'status_badge' => 'badge-atencion'
			],
			[
				'id' => 'EX-9122',
				'tx_id' => 'TX-4712',
				'branch' => 'Hallandale',
				'exception_type' => 'Reembolso sin confirmar',
				'amount' => '$14.50',
				'date' => '05/11',
				'priority' => 'Alta',
				'priority_badge' => 'badge-critico',
				'responsible' => 'Supervisor 02',
				'status' => 'En corrección',
				'status_badge' => 'badge-normal'
			],
			[
				'id' => 'EX-9123',
				'tx_id' => 'TX-4835',
				'branch' => 'Miami Beach',
				'exception_type' => 'Descuento fuera de política',
				'amount' => '$212.15',
				'date' => '04/11',
				'priority' => 'Alta',
				'priority_badge' => 'badge-critico',
				'responsible' => 'Gerente 03',
				'status' => 'Esperando info',
				'status_badge' => 'badge-sin-datos'
			],
			[
				'id' => 'EX-9124',
				'tx_id' => 'TX-4803',
				'branch' => 'Sunny Isles',
				'exception_type' => 'Posible duplicidad',
				'amount' => '$18.50',
				'date' => '04/11',
				'priority' => 'Media',
				'priority_badge' => 'badge-atencion',
				'responsible' => 'Supervisor 05',
				'status' => 'En investigación',
				'status_badge' => 'badge-atencion'
			],
			[
				'id' => 'EX-9125',
				'tx_id' => 'TX-4588',
				'branch' => 'Miami Beach',
				'exception_type' => 'Anulación después del pago',
				'amount' => '$45.80',
				'date' => '03/11',
				'priority' => 'Media',
				'priority_badge' => 'badge-atencion',
				'responsible' => 'Sin asignar',
				'status' => 'Pendiente',
				'status_badge' => 'badge-sin-datos'
			]
		];

		// 7. Anulaciones y Descuentos por Caja
		$voidsDiscounts = [
			'threshold' => 7.5,
			'network_rate' => '2.9 %',
			'total_voids' => 14,
			'post_pay_voids' => 6,
			'registers' => [
				[
					'name' => 'Miami Beach · Caja 1',
					'rate' => 12.4,
					'rate_formatted' => '12.4',
					'bar_color_class' => 'bar-critico'
				],
				[
					'name' => 'Sucursal 07 · Caja 1',
					'rate' => 10.1,
					'rate_formatted' => '10.1',
					'bar_color_class' => 'bar-atencion'
				],
				[
					'name' => 'Hallandale · Caja 2',
					'rate' => 7.9,
					'rate_formatted' => '7.9',
					'bar_color_class' => 'bar-excelente'
				],
				[
					'name' => 'Sunny Isles · Caja 3',
					'rate' => 6.8,
					'rate_formatted' => '6.8',
					'bar_color_class' => 'bar-excelente'
				],
				[
					'name' => 'Sunny Isles · Caja 1',
					'rate' => 4.4,
					'rate_formatted' => '4.4',
					'bar_color_class' => 'bar-excelente'
				]
			]
		];

		// 8. Conciliación POS — Diferencias Detectadas
		$differencesDetected = [
			'items' => [
				[
					'type' => 'Venta registrada sin pago confirmado',
					'cases' => 3,
					'amount' => '$186.40'
				],
				[
					'type' => 'Pago capturado sin venta',
					'cases' => 2,
					'amount' => '$112.50'
				],
				[
					'type' => 'Duplicidad de transacciones',
					'cases' => 2,
					'amount' => '$37.00'
				],
				[
					'type' => 'Diferencias de importe',
					'cases' => 4,
					'amount' => '$94.25'
				],
				[
					'type' => 'Devolución sin referencia original',
					'cases' => 1,
					'amount' => '$29.89'
				],
				[
					'type' => 'Descuentos inconsistentes',
					'cases' => 3,
					'amount' => '$40.00'
				]
			],
			'total_cases' => 15,
			'total_amount' => '$469.04'
		];

		// 9. Seguimiento de Devoluciones
		$returnsTracking = [
			[
				'original_ticket' => 'T-48520',
				'purchase_date' => '05/11',
				'product' => 'Filete de salmón',
				'qty' => 1,
				'reason' => 'Calidad',
				'amount' => '$14.50',
				'refund_status' => 'Sin confirmar',
				'refund_status_badge' => 'badge-atencion',
				'return_date' => '05/11',
				'return_ref' => 'DEV-1043'
			],
			[
				'original_ticket' => 'T-48130',
				'purchase_date' => '02/11',
				'product' => 'Leche entera 1L',
				'qty' => 2,
				'reason' => 'Vencimiento',
				'amount' => '$3.78',
				'refund_status' => 'Acreditado',
				'refund_status_badge' => 'badge-normal',
				'return_date' => '03/11',
				'return_ref' => 'DEV-1036'
			],
			[
				'original_ticket' => 'T-48111',
				'purchase_date' => '01/11',
				'product' => 'Pizza 4 quesos',
				'qty' => 1,
				'reason' => 'Costos',
				'amount' => '$6.98',
				'refund_status' => 'Acreditado',
				'refund_status_badge' => 'badge-normal',
				'return_date' => '02/11',
				'return_ref' => 'DEV-1027'
			],
			[
				'original_ticket' => '—',
				'purchase_date' => '—',
				'product' => 'Pechuga de pollo',
				'qty' => 1,
				'reason' => 'Sin registro',
				'amount' => '$5.49',
				'refund_status' => 'Sin relación',
				'refund_status_badge' => 'badge-critico',
				'return_date' => '30/10',
				'return_ref' => 'DEV-1010'
			]
		];

		// 10. Auditoría de Cambios (Append-Only)
		$changeAuditLog = [
			[
				'datetime' => '05/11 10:20',
				'event' => 'Devolución aprobada',
				'tx_id' => 'TX-4712',
				'user' => 'Supervisor 02',
				'field' => 'Estado',
				'diff' => 'Completada → Devuelto parcial',
				'reason' => 'Calidad',
				'authorizer' => 'Supervisor 02',
				'authorizer_badge' => 'badge-normal'
			],
			[
				'datetime' => '05/11 09:39',
				'event' => 'Descuento aplicado',
				'tx_id' => 'TX-4712',
				'user' => 'Cajero 02',
				'field' => 'Descuento',
				'diff' => '0 % → 5 %',
				'reason' => 'Promoción vigente',
				'authorizer' => 'Política',
				'authorizer_badge' => 'badge-normal'
			],
			[
				'datetime' => '04/11 17:02',
				'event' => 'Descuento modificado',
				'tx_id' => 'TX-4621',
				'user' => 'Cajero 04',
				'field' => 'Descuento',
				'diff' => '0 % → 20 %',
				'reason' => 'Sin motivo',
				'authorizer' => 'Pendiente',
				'authorizer_badge' => 'badge-critico'
			],
			[
				'datetime' => '03/11 11:45',
				'event' => 'Anulación',
				'tx_id' => 'TX-4588',
				'user' => 'Cajero 03',
				'field' => 'Estado',
				'diff' => 'Completada → Anulado',
				'reason' => 'Error de cobro',
				'authorizer' => 'Supervisor 03',
				'authorizer_badge' => 'badge-normal'
			]
		];

		// 11. Control de Sincronización
		$syncControl = [
			[
				'source' => 'POS tiendas',
				'last_sync' => '05/11 16:50',
				'received' => 5611,
				'processed' => 5493,
				'pending' => 118,
				'rejected' => 0,
				'status' => 'Parcial',
				'status_badge' => 'badge-atencion'
			],
			[
				'source' => 'Pasarela de pagos',
				'last_sync' => '05/11 16:45',
				'received' => 4102,
				'processed' => 4087,
				'pending' => 15,
				'rejected' => 0,
				'status' => 'Parcial',
				'status_badge' => 'badge-atencion'
			],
			[
				'source' => 'Cierres de caja',
				'last_sync' => '05/11 06:00',
				'received' => 112,
				'processed' => 109,
				'pending' => 0,
				'rejected' => 3,
				'status' => 'Error',
				'status_badge' => 'badge-critico'
			],
			[
				'source' => 'Devoluciones POS',
				'last_sync' => '05/11 16:30',
				'received' => 87,
				'processed' => 86,
				'pending' => 1,
				'rejected' => 0,
				'status' => 'Al día',
				'status_badge' => 'badge-normal'
			]
		];

		// 12. Gestión de Casos
		$caseCounters = [
			'pendiente' => 5,
			'investigacion' => 8,
			'esperando_info' => 3,
			'correccion' => 4,
			'validacion' => 3,
			'resuelta' => 31
		];

		$cases = [
			[
				'case_id' => 'CS-0131',
				'tx_id' => 'TX-4859',
				'responsible' => 'Supervisor 07',
				'last_obs' => 'Solicitado reporte a pasarela',
				'status' => 'En investigación',
				'status_badge' => 'badge-atencion',
				'validation' => 'Pendiente'
			],
			[
				'case_id' => 'CS-0130',
				'tx_id' => 'TX-4712',
				'responsible' => 'Supervisor 02',
				'last_obs' => 'Reserva de confirmación de reembolso',
				'status' => 'En corrección',
				'status_badge' => 'badge-normal',
				'validation' => 'Diferencia -$14.50 expuesta a caja'
			],
			[
				'case_id' => 'CS-0129',
				'tx_id' => 'TX-4835',
				'responsible' => 'Gerente 03',
				'last_obs' => 'Falta autorización del descuento',
				'status' => 'Esperando info',
				'status_badge' => 'badge-sin-datos',
				'validation' => 'Pendiente'
			],
			[
				'case_id' => 'CS-0128',
				'tx_id' => 'TX-4543',
				'responsible' => 'Supervisor 06',
				'last_obs' => 'Importe corregido y documentado',
				'status' => 'En validación',
				'status_badge' => 'badge-normal',
				'validation' => 'Revisión de Manager'
			]
		];

		// 13. Comparador de Transacciones (Posible Duplicidad)
		$comparator = [
			'tx_a' => [
				'id' => 'TX-4803',
				'time' => '10:12:15',
				'branch_register' => 'Sunny Isles · Caja 1',
				'cashier' => 'Cajero 05',
				'products' => '1 línea',
				'amount' => '$18.50',
				'payment_ref' => 'AUTH-****-6112',
				'status' => 'Completada'
			],
			'tx_b' => [
				'id' => 'TX-4802',
				'time' => '10:12:45',
				'branch_register' => 'Sunny Isles · Caja 1',
				'cashier' => 'Cajero 05',
				'products' => '1 línea idéntica',
				'amount' => '$18.50',
				'payment_ref' => 'AUTH-****-6114',
				'status' => 'Completada'
			],
			'note' => 'Coinciden productos e importes; pagos con distintos vouchers: requiere revisión de contexto antes de concluir.'
		];

		return [
			'kpis' => $kpis,
			'transactions_master' => $transactionsMaster,
			'ficha_360' => $ficha360,
			'timeline' => $timeline,
			'reconciliation_sources' => $reconciliationSources,
			'exceptions' => $exceptions,
			'voids_discounts' => $voidsDiscounts,
			'differences_detected' => $differencesDetected,
			'returns_tracking' => $returnsTracking,
			'change_audit_log' => $changeAuditLog,
			'sync_control' => $syncControl,
			'case_counters' => $caseCounters,
			'cases' => $cases,
			'comparator' => $comparator
		];
	}
}

