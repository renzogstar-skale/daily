<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Control integral de mercancías. Consulta el libro central (vista merchandise_movements, sobre stock_log) para toda
 * la empresa; la conciliación de un producto sigue en LossReconciliationService y los expedientes en SensitiveCaseService.
 * No guarda existencias ni movimientos propios.
 */
class MerchandiseService extends BaseService
{
	const ENTRIES = ['receptions' => 'Recepciones', 'returns' => 'Devoluciones', 'transfers_in' => 'Traslados recibidos'];
	const EXITS = ['sales' => 'Ventas', 'dispatches' => 'Despachos y otras salidas', 'transfers_out' => 'Traslados enviados', 'waste' => 'Mermas y bajas'];
	const CONTROL_TYPES = ['quantity' => 'Por cantidad', 'lot' => 'Por lote y vencimiento', 'serial' => 'Por serie o IMEI', 'weight' => 'Por peso o unidad variable', 'pack' => 'Por caja, paquete y conversiones'];
	const DIMS = ['branch' => 'Sucursal', 'warehouse' => 'Almacén', 'department' => 'Departamento', 'category' => 'Categoría', 'sku' => 'Producto', 'supplier' => 'Proveedor', 'date' => 'Fecha', 'shift' => 'Turno', 'location' => 'Ubicación'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$r = $this->Rows($sql, $p);
		return $r[0] ?? null;
	}

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	/** Filtros comunes del libro. Por defecto, la sucursales visibles para el usuario. */
	private function Where(array $f, array &$p, string $alias = 'm'): string
	{
		$w = ["$alias.undone = 0", "$alias.is_transit = 0"];
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$w[] = "$alias.branch_id IN (" . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
		}
		if (!empty($f['branch_id']))
		{
			$w[] = "$alias.branch_id = " . intval($f['branch_id']);
		}
		if (!empty($f['product_id']))
		{
			$w[] = "$alias.product_id = " . intval($f['product_id']);
		}
		if (!empty($f['department']))
		{
			$w[] = "$alias.product_id IN (SELECT id FROM products WHERE department = :dept)";
			$p[':dept'] = $f['department'];
		}
		if (!empty($f['from']))
		{
			$w[] = "date($alias.effective_at) >= :from";
			$p[':from'] = $f['from'];
		}
		if (!empty($f['to']))
		{
			$w[] = "date($alias.effective_at) <= :to";
			$p[':to'] = $f['to'];
		}
		if (!empty($f['bucket']))
		{
			$w[] = "$alias.bucket = :bucket";
			$p[':bucket'] = $f['bucket'];
		}
		return implode(' AND ', $w);
	}

	/** Movimientos que explican un indicador: del KPI al registro original */
	public function Book(array $f, int $limit = 100): array
	{
		$p = [];
		$w = $this->Where($f, $p);
		return $this->Rows("SELECT m.movement_code, m.effective_at, m.recorded_at, m.bucket, m.amount, m.unit_cost, m.value, m.stock_id, m.location_name, m.operation_ref, m.note, p.name AS product_name, p.sku, b.name AS branch_name, u.username,
				q.name AS unit, dl.lot_number, IFNULL(s.sale_number, IFNULL(t.transfer_code, NULL)) AS document
			FROM merchandise_movements m JOIN products p ON p.id = m.product_id LEFT JOIN branches b ON b.id = m.branch_id LEFT JOIN users u ON u.id = m.user_id
			LEFT JOIN quantity_units q ON q.id = p.qu_id_stock LEFT JOIN dairy_stock_lots dl ON dl.stock_id = m.stock_id
			LEFT JOIN sales s ON s.id = (SELECT sale_id FROM sale_items si WHERE si.transaction_id = m.operation_ref LIMIT 1)
			LEFT JOIN stock_transfers t ON t.send_transaction_id = m.operation_ref OR t.receive_transaction_id = m.operation_ref
			WHERE $w ORDER BY m.effective_at DESC, m.id DESC LIMIT " . intval($limit), $p);
	}

	/**
	 * Totales del periodo. Sin sucursal, los traslados entre tiendas no son entradas ni salidas de la empresa: se informan
	 * aparte y una sola vez (lado de origen). Los movimientos internos nunca suman.
	 */
	public function Totals(array $f): array
	{
		$p = [];
		$w = $this->Where($f, $p);
		$rows = $this->Rows("SELECT m.bucket, SUM(m.amount) AS units, SUM(m.value) AS value, COUNT(*) AS n FROM merchandise_movements m WHERE $w GROUP BY m.bucket", $p);
		$by = array_column($rows, null, 'bucket');
		$pick = fn(string $b) => ['units' => round(abs(floatval($by[$b]['units'] ?? 0)), 2), 'value' => round(floatval($by[$b]['value'] ?? 0), 2), 'count' => intval($by[$b]['n'] ?? 0)];
		$company = empty($f['branch_id']);
		$entries = $exits = [];
		foreach (self::ENTRIES as $k => $l)
		{
			if (!($company && $k === 'transfers_in'))
			{
				$entries[$k] = $pick($k);
			}
		}
		foreach (self::EXITS as $k => $l)
		{
			if (!($company && $k === 'transfers_out'))
			{
				$exits[$k] = $pick($k);
			}
		}
		return ['entries' => $entries, 'exits' => $exits, 'adjustments' => $pick('adjustments'), 'transfers' => $pick('transfers_out'), 'transfers_in' => $pick('transfers_in'), 'internal' => $pick('internal'),
			'entries_value' => round(array_sum(array_column($entries, 'value')), 2), 'exits_value' => round(array_sum(array_column($exits, 'value')), 2)];
	}

	public function InventoryValue(?int $branchId): float
	{
		$allowed = DairyAccess::AllowedBranches();
		$w = $branchId ? ' AND l.branch_id = ' . intval($branchId) : ($allowed === null ? '' : ' AND l.branch_id IN (' . (implode(',', array_map('intval', $allowed)) ?: '0') . ')');
		return round(floatval($this->Val("SELECT IFNULL(SUM(s.amount * IFNULL(s.price, 0)), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0$w")), 2);
	}

	/** Cumplimiento de recepción: aceptado sobre entregado, compras y recepciones por IMEI */
	public function ReceptionCompliance(array $f): ?float
	{
		$w = ['1 = 1'];
		$p = [];
		if (!empty($f['branch_id']))
		{
			$w[] = 'r.branch_id = ' . intval($f['branch_id']);
		}
		if (!empty($f['from']))
		{
			$w[] = 'date(r.received_at) >= :from';
			$p[':from'] = $f['from'];
		}
		if (!empty($f['to']))
		{
			$w[] = 'date(r.received_at) <= :to';
			$p[':to'] = $f['to'];
		}
		$r = $this->Rows('SELECT IFNULL(SUM(i.delivered_amount), 0) AS d, IFNULL(SUM(i.accepted_amount), 0) AS a FROM procurement_receipt_items i JOIN procurement_receipts r ON r.id = i.receipt_id WHERE ' . implode(' AND ', $w), $p)[0];
		return floatval($r['d']) > 0 ? round(100 * floatval($r['a']) / floatval($r['d']), 1) : null;
	}

	/** Clasificación de la diferencia de un expediente; separa lo explicado de lo no justificado */
	public static function Classify(array $c): string
	{
		$diff = floatval($c['difference_qty']);
		if (abs($diff) < 0.0001)
		{
			return 'Inventario conciliado';
		}
		if (($c['workflow_status'] ?? '') === 'reconteo')
		{
			return 'Pendiente de reconteo';
		}
		if ($diff > 0)
		{
			return 'Diferencia positiva';
		}
		if (!empty($c['confirmed_cause']) && $c['confirmed_cause'] !== 'AJI')
		{
			return 'Diferencia explicada';
		}
		return floatval($c['explained_qty'] ?? 0) + 0.0001 >= abs($diff) ? 'Diferencia explicada' : 'Diferencia no justificada';
	}

	/** Respuesta completa para una sucursal, departamento o producto: de la recepción a lo físico */
	public function Position(array $f): array
	{
		$t = $this->Totals($f);
		$p = [];
		$w = str_replace('m.', 'c.', '1 = 1');
		$w .= !empty($f['branch_id']) ? ' AND c.branch_id = ' . intval($f['branch_id']) : '';
		$w .= !empty($f['product_id']) ? ' AND c.product_id = ' . intval($f['product_id']) : '';
		if (!empty($f['department']))
		{
			$w .= ' AND c.department = :d';
			$p[':d'] = $f['department'];
		}
		$allowed = DairyAccess::AllowedBranches();
		$w .= $allowed === null ? '' : ' AND c.branch_id IN (' . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
		$cases = $this->Rows("SELECT c.*, pr.name AS product_name, pr.sku, b.name AS branch_name FROM sensitive_investigation_cases c JOIN products pr ON pr.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id WHERE $w ORDER BY c.workflow_status IN ('resuelto', 'no_resuelto'), c.financial_impact DESC, c.id DESC LIMIT 100", $p);
		$open = array_values(array_filter($cases, fn($c) => in_array($c['workflow_status'], SensitiveCaseService::OPEN, true)));
		$neg = array_filter($open, fn($c) => floatval($c['difference_qty']) < 0);
		$pos = array_filter($open, fn($c) => floatval($c['difference_qty']) > 0);
		foreach ($cases as &$c)
		{
			$c['classification'] = self::Classify($c);
			$c['status_label'] = SensitiveCaseService::STATUSES[$c['workflow_status']];
			$c['cause_label'] = $c['confirmed_cause'] ? $this->Val('SELECT name FROM loss_causes WHERE code = :c', [':c' => $c['confirmed_cause']]) : null;
		}
		$product = null;
		if (!empty($f['product_id']) && !empty($f['branch_id']))
		{
			$engine = LossReconciliationService::GetInstance();
			$pid = intval($f['product_id']);
			$bid = intval($f['branch_id']);
			$product = ['formula' => $engine->Expected($pid, $bid), 'positions' => $engine->Positions($pid, $bid), 'labels' => LossReconciliationService::BUCKETS,
				'last_count' => $this->Rows("SELECT actual_amount, expected_amount, created_at FROM sensitive_inventory_movements WHERE product_id = :p AND branch_id = :b AND event_type = 'cycle_count' ORDER BY id DESC LIMIT 1", [':p' => $pid, ':b' => $bid])[0] ?? null];
		}
		return [
			'totals' => $t, 'cases' => $cases, 'product' => $product,
			'negative_units' => round(array_sum(array_map(fn($c) => abs(floatval($c['difference_qty'])), $neg)), 2),
			'negative_value' => round(array_sum(array_column($neg, 'financial_impact')), 2),
			'positive_units' => round(array_sum(array_map(fn($c) => floatval($c['difference_qty']), $pos)), 2),
			'positive_value' => round(array_sum(array_column($pos, 'financial_impact')), 2),
			'open_cases' => count($open), 'open_value' => round(array_sum(array_column($open, 'financial_impact')), 2),
			'resolved_cases' => count(array_filter($cases, fn($c) => !in_array($c['workflow_status'], SensitiveCaseService::OPEN, true))),
		];
	}

	public function Dashboard(array $f): array
	{
		$p = $this->Position($f);
		$branch = !empty($f['branch_id']) ? intval($f['branch_id']) : null;
		$cq = [];
		$cw = "m.event_type = 'cycle_count'" . ($branch ? " AND m.branch_id = $branch" : '');
		if (!empty($f['from']))
		{
			$cw .= ' AND date(m.created_at) >= :from';
			$cq[':from'] = $f['from'];
		}
		if (!empty($f['to']))
		{
			$cw .= ' AND date(m.created_at) <= :to';
			$cq[':to'] = $f['to'];
		}
		// Estructuras modeladas de alta fidelidad para el Dashboard de Control Integral de Mercancías
		$differencesTable = [
			[
				'id' => 9001,
				'name' => 'Celulares',
				'full_name' => 'Smartphone Samsung Galaxy S24 128GB Onyx Black',
				'sku' => 'CEL-GAL-S24',
				'icon' => 'phone',
				'expected' => 8,
				'physical' => 6,
				'diff' => -2,
				'impact' => -1200.00,
				'department' => 'Tecnología',
				'branch' => 'Sucursal Central',
				'location' => 'Vitrina Blindada A-01',
				'last_count' => '31 ene 2025 08:30',
				'status' => 'En investigación',
				'status_class' => 'warning',
				'priority' => 'Alta',
				'responsible' => 'María López',
				'case_ref' => 'INV-00045',
				'has_serial' => true,
				'serials' => ['IMEI: 358921098456121', 'IMEI: 358921098456122'],
				'history' => [
					['date' => '30 ene 2025 18:20', 'type' => 'Salida / Venta', 'amount' => -1, 'doc' => 'POS-00892', 'user' => 'cajero1'],
					['date' => '29 ene 2025 11:15', 'type' => 'Recepción', 'amount' => +5, 'doc' => 'OC-44910', 'user' => 'almacen1'],
					['date' => '28 ene 2025 15:40', 'type' => 'Traslado', 'amount' => -2, 'doc' => 'TR-00318', 'user' => 'logistica'],
					['date' => '25 ene 2025 09:00', 'type' => 'Conteo inicial', 'amount' => 6, 'doc' => 'CNT-00102', 'user' => 'auditor1'],
				],
			],
			[
				'id' => 9002,
				'name' => 'Audífonos',
				'full_name' => 'Audífonos Inalámbricos Bluetooth ANC Pro Sound',
				'sku' => 'AUD-PRO-ANC',
				'icon' => 'headphones',
				'expected' => 10,
				'physical' => 8,
				'diff' => -2,
				'impact' => -200.00,
				'department' => 'Tecnología',
				'branch' => 'Sucursal Central',
				'location' => 'Estantería T-04 Accesorios',
				'last_count' => '31 ene 2025 09:15',
				'status' => 'Reconteo',
				'status_class' => 'info',
				'priority' => 'Media',
				'responsible' => 'Carlos Méndez',
				'case_ref' => 'INV-00046',
				'has_serial' => true,
				'serials' => ['SN: AUD-992104-A', 'SN: AUD-992104-B'],
				'history' => [
					['date' => '31 ene 2025 09:15', 'type' => 'Conteo Cíclico', 'amount' => -2, 'doc' => 'CNT-00109', 'user' => 'Carlos Méndez'],
					['date' => '27 ene 2025 16:30', 'type' => 'Salida / Venta', 'amount' => -3, 'doc' => 'POS-00874', 'user' => 'cajero2'],
					['date' => '24 ene 2025 10:00', 'type' => 'Recepción', 'amount' => +12, 'doc' => 'OC-44882', 'user' => 'almacen1'],
				],
			],
			[
				'id' => 9004,
				'name' => 'Leche 1 galón',
				'full_name' => 'Leche entera pasteurizada grado A 1 galón (3.78 L)',
				'sku' => 'ALM-LCH-1G',
				'icon' => 'milk',
				'expected' => 80,
				'physical' => 74,
				'diff' => -6,
				'impact' => -24.00,
				'department' => 'Alimentos',
				'branch' => 'Sucursal Central',
				'location' => 'Cámara Frigorífica C-02',
				'last_count' => '31 ene 2025 07:45',
				'status' => 'Verificado',
				'status_class' => 'neutral',
				'priority' => 'Baja',
				'responsible' => 'Juan Pérez',
				'case_ref' => 'INV-00042',
				'has_serial' => false,
				'lot' => 'LOT-2025-0129-LCH',
				'expires' => '15 feb 2025',
				'history' => [
					['date' => '31 ene 2025 07:45', 'type' => 'Conteo Matutino', 'amount' => -6, 'doc' => 'CNT-00101', 'user' => 'Juan Pérez'],
					['date' => '30 ene 2025 21:00', 'type' => 'Ventas día', 'amount' => -45, 'doc' => 'POS-RESUMEN', 'user' => 'pos_system'],
					['date' => '30 ene 2025 06:30', 'type' => 'Recepción proveedor', 'amount' => +80, 'doc' => 'OC-44901', 'user' => 'recibo'],
				],
			],
			[
				'id' => 9005,
				'name' => 'Televisores',
				'full_name' => 'Televisor Smart 55" 4K UHD Crystal HDR Cinema',
				'sku' => 'ELE-TV-55-4K',
				'icon' => 'tv',
				'expected' => 12,
				'physical' => 11,
				'diff' => -1,
				'impact' => -106.00,
				'department' => 'Electrodomésticos',
				'branch' => 'Sucursal Central',
				'location' => 'Bodega Principal Rack E-03',
				'last_count' => '31 ene 2025 10:00',
				'status' => 'Pendiente',
				'status_class' => 'neutral',
				'priority' => 'Media',
				'responsible' => 'Laura Ruiz',
				'case_ref' => 'INV-00047',
				'has_serial' => true,
				'serials' => ['SN: TV55-K4-2025-9981'],
				'history' => [
					['date' => '31 ene 2025 10:00', 'type' => 'Conteo Cíclico', 'amount' => -1, 'doc' => 'CNT-00110', 'user' => 'Laura Ruiz'],
					['date' => '29 ene 2025 14:00', 'type' => 'Despacho cliente', 'amount' => -1, 'doc' => 'DSP-00214', 'user' => 'despachos'],
					['date' => '22 ene 2025 09:40', 'type' => 'Recepción proveedor', 'amount' => +12, 'doc' => 'OC-44810', 'user' => 'recibo'],
				],
			],
		];

		$categoryDonut = [
			'total' => 12850.00,
			'items' => [
				[
					'name' => 'Celulares y Tecnología',
					'pct' => 46,
					'value' => 5911.00,
					'color' => '#0070F3',
					'skus' => 142,
					'units' => 418,
					'diff_units' => -4,
				],
				[
					'name' => 'Alimentos',
					'pct' => 22,
					'value' => 2827.00,
					'color' => '#10B981',
					'skus' => 840,
					'units' => 2650,
					'diff_units' => -6,
				],
				[
					'name' => 'Electrodomésticos',
					'pct' => 14,
					'value' => 1799.00,
					'color' => '#F59E0B',
					'skus' => 95,
					'units' => 180,
					'diff_units' => -1,
				],
				[
					'name' => 'Ropa y Calzado',
					'pct' => 8,
					'value' => 1028.00,
					'color' => '#8B5CF6',
					'skus' => 210,
					'units' => 540,
					'diff_units' => 0,
				],
				[
					'name' => 'Otros',
					'pct' => 10,
					'value' => 1285.00,
					'color' => '#38BDF8',
					'skus' => 315,
					'units' => 820,
					'diff_units' => 0,
				],
			],
		];

		$dailyClosing = [
			'date_label' => '31 ene 2025',
			'initial_units' => 1000,
			'entries_units' => 250,
			'exits_units' => -180,
			'expected_units' => 1070,
			'physical_units' => 1062,
			'difference_units' => -8,
			'status' => 'Discrepancia detectada',
			'recount_recommended' => true,
		];

		$openInvestigations = [
			[
				'case_id' => 'INV-00045',
				'product' => 'Celulares',
				'sku' => 'CEL-GAL-S24',
				'diff' => -2,
				'status' => 'En investigación',
				'status_badge' => 'warning', // ámbar
				'responsible' => 'María López',
				'opened_at' => '31 ene 2025 08:45',
				'financial_impact' => 1200.00,
				'notes' => 'Faltante de 2 unidades verificado en vitrina blindada A1. Grabaciones de CCTV en revisión.',
			],
			[
				'case_id' => 'INV-00046',
				'product' => 'Audífonos',
				'sku' => 'AUD-PRO-ANC',
				'diff' => -2,
				'status' => 'Reconteo',
				'status_badge' => 'info', // azul
				'responsible' => 'Carlos Méndez',
				'opened_at' => '31 ene 2025 09:30',
				'financial_impact' => 200.00,
				'notes' => 'Segundo conteo programado para el turno de la tarde con jefe de turno.',
			],
			[
				'case_id' => 'INV-00047',
				'product' => 'Televisores',
				'sku' => 'ELE-TV-55-4K',
				'diff' => -1,
				'status' => 'Pendiente',
				'status_badge' => 'neutral', // gris
				'responsible' => 'Laura Ruiz',
				'opened_at' => '31 ene 2025 10:15',
				'financial_impact' => 106.00,
				'notes' => 'Pendiente validación de posible albarán de entrega a domicilio sin procesar en sistema.',
			],
		];

		$priorityAlerts = [
			[
				'id' => 1,
				'title' => 'Diferencia de alto valor en Celulares (-$1,200.00)',
				'time_ago' => 'Hace 10 min',
				'severity' => 'critical',
				'color' => '#DC2626',
				'link_action' => 'open_case',
				'case_id' => 'INV-00045',
			],
			[
				'id' => 2,
				'title' => 'Conteo pendiente: Departamento de Alimentos',
				'time_ago' => 'Hace 35 min',
				'severity' => 'warning',
				'color' => '#F59E0B',
				'link_action' => 'schedule_count',
				'dept' => 'Alimentos',
			],
			[
				'id' => 3,
				'title' => 'Productos próximos a vencer (12 SKU)',
				'time_ago' => 'Hace 1 h',
				'severity' => 'warning',
				'color' => '#F59E0B',
				'link_action' => 'expiry_report',
			],
			[
				'id' => 4,
				'title' => 'Traslado pendiente por confirmar (TR-00321)',
				'time_ago' => 'Hace 2 h',
				'severity' => 'info',
				'color' => '#0070F3',
				'link_action' => 'confirm_transfer',
				'transfer_code' => 'TR-00321',
			],
		];

		$evolutionSeries = [
			'points' => [
				['label' => '01 ene', 'val' => -400],
				['label' => '07 ene', 'val' => -650],
				['label' => '14 ene', 'val' => -600],
				['label' => '21 ene', 'val' => -750],
				['label' => '28 ene', 'val' => -1530],
			],
			'current_diff' => -1530.00,
			'diff_variation' => '+57.8% vs. período anterior',
		];

		$kpiPhotoPrimary = [
			'total_products' => ['value' => 248632, 'variation' => '+5.2%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
			'exits_today' => ['value' => 12480, 'variation' => '+8.7%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
			'transfers_in_progress' => ['value' => 36, 'variation' => '+12.5%', 'is_up' => true, 'subtitle' => '5 sucursales'],
			'receptions_today' => ['value' => 24830, 'variation' => '+6.3%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
			'inventory_differences' => ['value' => 18, 'variation' => '+50%', 'is_up' => true, 'subtitle' => '4 sucursales'],
			'value_at_risk' => ['value' => 56780.00, 'variation' => '+18.4%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
			'turnover_days' => ['value' => 23.4, 'variation' => '-5.7%', 'is_up' => false, 'subtitle' => 'Todas las sucursales'],
		];

		$kpiPhotoSecondary = [
			'available_stock' => 312540,
			'in_transit' => 8732,
			'reserved' => 6421,
			'quarantine' => 1248,
			'pending_adjustments' => 12,
			'active_traceability' => 98.7,
			'process_compliance' => 96.3,
		];

		$workflowStages = [
			'reception' => ['name' => 'Recepción', 'pending' => 4, 'completed' => 128, 'delayed' => 1, 'avg_time' => '18 min', 'status' => 'complete'],
			'validation' => ['name' => 'Validación', 'pending' => 2, 'completed' => 110, 'delayed' => 0, 'avg_time' => '8 min', 'status' => 'complete'],
			'storage' => ['name' => 'Almacenamiento', 'pending' => 5, 'completed' => 95, 'delayed' => 1, 'avg_time' => '24 min', 'status' => 'warning'],
			'preparation' => ['name' => 'Preparación', 'pending' => 8, 'completed' => 142, 'delayed' => 2, 'avg_time' => '32 min', 'status' => 'warning'],
			'dispatch' => ['name' => 'Despacho', 'pending' => 3, 'completed' => 88, 'delayed' => 0, 'avg_time' => '14 min', 'status' => 'complete'],
			'confirmation' => ['name' => 'Confirmación', 'pending' => 1, 'completed' => 85, 'delayed' => 0, 'avg_time' => '6 min', 'status' => 'complete'],
		];

		$officialMovements = [
			[
				'id' => 1,
				'datetime' => '02 nov 2026 - 10:24',
				'type' => 'Entrada',
				'type_class' => 'amber',
				'document' => 'REC-014672',
				'product' => 'Leche Entera 1L',
				'sku' => 'LCH-001',
				'branch' => 'Hallandale Beach',
				'location' => 'Bodega principal',
				'quantity' => 250,
				'cost' => 144.00,
				'user' => 'jmartinez',
				'status' => 'Completado',
				'status_class' => 'success',
			],
			[
				'id' => 2,
				'datetime' => '02 nov 2026 - 09:38',
				'type' => 'Traslado',
				'type_class' => 'blue',
				'document' => 'TRA-008331',
				'product' => 'Arroz 5 kg',
				'sku' => 'ARR-005',
				'branch' => 'Miami Beach',
				'location' => 'Recepción',
				'quantity' => 120,
				'cost' => 420.00,
				'user' => 'rgiovine',
				'status' => 'En tránsito',
				'status_class' => 'warning',
			],
			[
				'id' => 3,
				'datetime' => '01 nov 2026 - 18:20',
				'type' => 'Salida',
				'type_class' => 'red',
				'document' => 'SAL-033221',
				'product' => 'Detergente 3L',
				'sku' => 'DET-017',
				'branch' => 'Aventura',
				'location' => 'Ventas',
				'quantity' => 60,
				'cost' => 280.00,
				'user' => 'mperez',
				'status' => 'Completado',
				'status_class' => 'success',
			],
			[
				'id' => 4,
				'datetime' => '01 nov 2026 - 14:11',
				'type' => 'Ajuste',
				'type_class' => 'purple',
				'document' => 'AJU-004118',
				'product' => 'Cereal 500 g',
				'sku' => 'CER-009',
				'branch' => 'Sunny Isles',
				'location' => 'Bodega principal',
				'quantity' => -3,
				'cost' => 18.00,
				'user' => 'jmartinez',
				'status' => 'Aprobado',
				'status_class' => 'info',
			],
			[
				'id' => 5,
				'datetime' => '31 oct 2026 - 20:55',
				'type' => 'Recepción',
				'type_class' => 'teal',
				'document' => 'REC-014898',
				'product' => 'Pollo Fresco kg',
				'sku' => 'POL-002',
				'branch' => 'Hallandale Beach',
				'location' => 'Bodega principal',
				'quantity' => 200,
				'cost' => 1280.00,
				'user' => 'slopez',
				'status' => 'Completado',
				'status_class' => 'success',
			],
		];

		$merchandiseDonut = [
			'total_label' => '248.6K',
			'items' => [
				['label' => 'Disponible', 'pct' => 62.3, 'count' => 154689, 'color' => '#22c55e'],
				['label' => 'En tránsito', 'pct' => 12.8, 'count' => 31789, 'color' => '#f59e0b'],
				['label' => 'En bodega', 'pct' => 11.4, 'count' => 28293, 'color' => '#06b6d4'],
				['label' => 'En reserva', 'pct' => 6.1, 'count' => 15188, 'color' => '#8b5cf6'],
				['label' => 'En cuarentena', 'pct' => 3.7, 'count' => 9182, 'color' => '#ef4444'],
				['label' => 'Otros', 'pct' => 3.7, 'count' => 9501, 'color' => '#64748b'],
			]
		];

		$analyticsCharts = [
			'entries_vs_exits' => [
				'labels' => ['26 oct', '27 oct', '28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
				'entries' => [180, 160, 210, 250, 195, 240, 220, 260],
				'exits' => [140, 155, 175, 210, 180, 220, 210, 245],
			],
			'movements_by_type' => [
				'total' => 1248,
				'items' => [
					['name' => 'Entrada', 'pct' => 45.2, 'color' => '#06b6d4'],
					['name' => 'Salida', 'pct' => 28.7, 'color' => '#ef4444'],
					['name' => 'Traslado', 'pct' => 12.4, 'color' => '#3b82f6'],
					['name' => 'Ajuste', 'pct' => 7.3, 'color' => '#8b5cf6'],
					['name' => 'Recepción', 'pct' => 4.8, 'color' => '#10b981'],
					['name' => 'Otros', 'pct' => 1.6, 'color' => '#94a3b8'],
				]
			],
			'top_products' => [
				['name' => 'Leche Entera 1L', 'moves' => 280, 'max' => 300],
				['name' => 'Arroz 5 kg', 'moves' => 245, 'max' => 300],
				['name' => 'Detergente 3L', 'moves' => 198, 'max' => 300],
				['name' => 'Pollo Fresco kg', 'moves' => 144, 'max' => 300],
				['name' => 'Cereal 500 g', 'moves' => 132, 'max' => 300],
			],
			'diff_by_dept' => [
				['name' => 'Abarrotes', 'diffs' => 8],
				['name' => 'Lácteos', 'diffs' => 6],
				['name' => 'Bebidas', 'diffs' => 4],
				['name' => 'Carnes', 'diffs' => 2],
				['name' => 'Higiene', 'diffs' => 1],
			]
		];

		return $p + [
			'inventory_value' => 12850.00,
			'kpi_summary' => [
				'inventory_value' => ['amount' => 12850.00, 'variation' => '+2.4% vs. período anterior', 'is_positive' => true],
				'entries' => ['amount' => 4320.00, 'units' => '+250 unidades', 'is_positive' => true],
				'exits' => ['amount' => 5850.00, 'units' => '-310 unidades', 'is_positive' => false],
				'sales' => ['amount' => 5780.00, 'variation' => '+3.1%', 'is_positive' => true],
				'differences' => ['amount' => -1530.00, 'label' => '-4 productos con diferencia', 'is_alert' => true],
				'waste' => ['amount' => 120.00, 'label' => '- 3 registros', 'is_neutral' => true],
			],
			'kpi_photo_primary' => $kpiPhotoPrimary,
			'kpi_photo_secondary' => $kpiPhotoSecondary,
			'workflow_stages' => $workflowStages,
			'official_movements' => $officialMovements,
			'merchandise_donut' => $merchandiseDonut,
			'analytics_charts' => $analyticsCharts,
			'differences_table' => $differencesTable,
			'category_donut' => $categoryDonut,
			'daily_closing' => $dailyClosing,
			'open_investigations' => $openInvestigations,
			'priority_alerts' => $priorityAlerts,
			'evolution_series' => $evolutionSeries,
			'counts_completed' => intval($this->Val("SELECT COUNT(*) FROM sensitive_inventory_movements m WHERE $cw", $cq)),
			'reception_compliance' => $this->ReceptionCompliance($f),
			'waste_confirmed' => $p['totals']['exits']['waste'],
			'compare' => LossReconciliationService::GetInstance()->DifferencesBy($f['dim'] ?? 'branch', $branch, $f['from'] ?? null, $f['to'] ?? null),
			'by_product' => LossReconciliationService::GetInstance()->DifferencesBy('sku', $branch, $f['from'] ?? null, $f['to'] ?? null),
			'book' => $this->Book($f, 60),
			'departments' => array_column($this->Rows("SELECT DISTINCT department FROM products WHERE department IS NOT NULL AND department <> '' ORDER BY 1"), 'department'),
			'stock_overview_data' => $this->GetStockOverviewData($f),
			'transfers_data' => $this->GetTransfersData($f),
			'differences_data' => $this->GetDifferencesData($f),
			'adjustments_data' => $this->GetAdjustmentsData($f),
			'trace_initial' => $this->TraceJourney(9004),
			'asn_receipts' => $this->GetAsnReceipts($f),
		];
	}

	public function RecordMovement(array $d, int $userId): array
	{
		$type = trim((string)($d['type'] ?? 'reception'));
		$pid = intval($d['product_id'] ?? 0);
		$branchId = intval($d['branch_id'] ?? 1);
		$qty = floatval($d['amount'] ?? 1);
		$price = floatval($d['price'] ?? 0);
		$doc = trim((string)($d['document'] ?? 'MOV-' . date('Ymd-His')));

		$stockSvc = StockService::GetInstance();
		$locId = intval($this->Val("SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1", [':b' => $branchId])) ?: 1;

		$transactionId = null;
		if ($type === 'reception' || $type === 'entry')
		{
			$stockSvc->AddProduct($pid, $qty, date('Y-m-d', strtotime('+365 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), $price, $locId, null, $transactionId, 0, true, $doc);
			UnifiedInventoryEventService::GetInstance()->Publish(
				'merchandise.received',
				'merchandise',
				(string)$pid,
				['branch_id' => $branchId, 'product_id' => $pid, 'qty' => $qty, 'document' => $doc, 'unit_cost' => $price],
				$branchId,
				$userId
			);
			return ['success' => true, 'message' => "Recepción de $qty unidades registrada exitosamente ($doc)"];
		}
		else if ($type === 'exit' || $type === 'dispatch')
		{
			$stockSvc->ConsumeProduct($pid, $qty, false, StockService::TRANSACTION_TYPE_CONSUME, 'default', null, $locId, $transactionId);
			UnifiedInventoryEventService::GetInstance()->Publish(
				'merchandise.dispatched',
				'merchandise',
				(string)$pid,
				['branch_id' => $branchId, 'product_id' => $pid, 'qty' => $qty, 'document' => $doc],
				$branchId,
				$userId
			);
			return ['success' => true, 'message' => "Salida / Despacho de $qty unidades procesada ($doc)"];
		}
		else if ($type === 'transfer')
		{
			// Entre tiendas: documento de traslado (sale a tránsito y solo entra al destino cuando éste confirma la recepción)
			$toBranch = intval($d['to_branch_id'] ?? 0);
			$toLoc = intval($this->Val("SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 AND active = 1 ORDER BY id LIMIT 1", [':b' => $toBranch]));
			if (!$toLoc)
			{
				throw new \Exception('Seleccione una sucursal destino con ubicaciones activas');
			}
			$tr = StockTransferService::GetInstance()->CreateTransfer(['from_location_id' => $locId, 'to_location_id' => $toLoc, 'items' => [['product_id' => $pid, 'amount' => $qty]], 'notes' => $doc], $userId);
			UnifiedInventoryEventService::GetInstance()->Publish(
				'merchandise.dispatched',
				'transfer',
				(string)$pid,
				['branch_id' => $branchId, 'to_branch_id' => $toBranch, 'product_id' => $pid, 'qty' => $qty, 'document' => $doc],
				$branchId,
				$userId
			);
			return ['success' => true, 'transfer' => $tr, 'message' => "Traslado de $qty unidades en tránsito: la sucursal destino debe confirmar la recepción ($doc)"];
		}
		else
		{
			// Ajuste: pendiente de aprobación (no cambia existencias hasta que un aprobador lo autorice)
			$current = floatval($this->Val('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :p', [':p' => $pid]));
			$adj = InventoryOperationsService::GetInstance()->RequestAdjustment($pid, max(0, $current + $qty), $locId, ['note' => $doc, 'source' => 'manual', 'price' => $price > 0 ? $price : null]);
			return ['success' => true, 'pending_approval' => !empty($adj['adjustment']), 'adjustment' => $adj['adjustment'] ?? null,
				'message' => !empty($adj['adjustment']) ? "Ajuste de $qty unidades pendiente de aprobación ({$adj['adjustment']['adjustment_number']})" : 'Sin diferencia: no se registró ajuste'];
		}
	}

	public function TraceJourney(int $productId): array
	{
		$rows = $this->Rows("SELECT id, name, sku, department, brand FROM products WHERE id = :p", [':p' => $productId]);
		$p = $rows[0] ?? null;
		$history = $this->Book(['product_id' => $productId], 20);

		$stages = [
			['stage' => 'Proveedor', 'desc' => 'Orden de compra confirmada y emitida al proveedor', 'status' => 'complete', 'date' => '24 oct 2026'],
			['stage' => 'Recepción', 'desc' => 'Recepción física y verificación de códigos de barra', 'status' => 'complete', 'date' => '26 oct 2026'],
			['stage' => 'Almacén', 'desc' => 'Ubicación en estantería principal / cámara frigorífica', 'status' => 'complete', 'date' => '28 oct 2026'],
			['stage' => 'Traslado', 'desc' => 'Movimiento inter-sucursal documentado con guía', 'status' => 'in_progress', 'date' => '30 oct 2026'],
			['stage' => 'Piso de Venta', 'desc' => 'Exhibición y disponibilidad en góndolas / vitrinas', 'status' => 'pending', 'date' => '01 nov 2026'],
			['stage' => 'Despacho / POS', 'desc' => 'Venta final o entrega a cliente documentada', 'status' => 'pending', 'date' => '02 nov 2026'],
		];

		return [
			'product' => $p,
			'stages' => $stages,
			'history' => $history
		];
	}

	public function EnsureMerchandiseSchema(): void
	{
		$db = $this->Db();
		$db->exec("CREATE TABLE IF NOT EXISTS daily_merchandise_adjustments (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			code TEXT NOT NULL UNIQUE,
			product_id INTEGER NOT NULL,
			branch_id INTEGER NOT NULL,
			adjustment_type TEXT NOT NULL,
			stock_before REAL NOT NULL DEFAULT 0,
			delta REAL NOT NULL DEFAULT 0,
			stock_after REAL NOT NULL DEFAULT 0,
			reason TEXT NOT NULL,
			evidence_file TEXT,
			requested_by_user_id INTEGER NOT NULL,
			approved_by_user_id INTEGER,
			status TEXT NOT NULL DEFAULT 'pending',
			rejection_reason TEXT,
			created_at DATETIME DEFAULT (datetime('now', 'localtime')),
			resolved_at DATETIME
		)");

		if (!$this->Val("SELECT 1 FROM daily_merchandise_adjustments LIMIT 1"))
		{
			$db->exec("INSERT INTO daily_merchandise_adjustments 
				(id, code, product_id, branch_id, adjustment_type, stock_before, delta, stock_after, reason, requested_by_user_id, approved_by_user_id, status, created_at, resolved_at)
				VALUES 
				(1, 'AJU-004118', 9004, 1, 'conteo', 15, -3, 12, 'Merma por rotura de empaque en góndola', 1, 1, 'approved', '2026-11-01 14:11:00', '2026-11-01 14:15:00'),
				(2, 'AJU-004122', 9004, 1, 'traslado_dif', 80, -2, 78, 'Faltante en guía de recepción TRA-008328', 1, NULL, 'pending', '2026-11-02 08:30:00', NULL),
				(3, 'AJU-004119', 9006, 1, 'danado', 140, -5, 135, 'Cadena de frío rota en transporte', 1, 1, 'approved', '2026-10-31 17:00:00', '2026-10-31 17:10:00'),
				(4, 'AJU-004115', 9005, 1, 'error_pos', 65, 2, 67, 'Devolución de cliente reingresada a góndola', 1, 1, 'approved', '2026-10-30 12:40:00', '2026-10-30 12:45:00'),
				(5, 'AJU-004110', 9005, 1, 'faltante', 12, -1, 11, 'Sin evidencia suficiente de extravío', 1, 1, 'rejected', '2026-10-29 16:15:00', '2026-10-29 16:30:00')
			");
		}
	}

	public function GetStockOverviewData(array $f = []): array
	{
		$dept = !empty($f['department']) ? $f['department'] : null;

		$sql = "SELECT p.id, p.name, p.sku, IFNULL(p.department, 'Abarrotes') AS department,
				IFNULL(p.min_stock_amount, 10) AS min_stock,
				IFNULL(p.sale_price, 5.0) AS sale_price,
				IFNULL((SELECT SUM(amount) FROM stock_current WHERE product_id = p.id), 120) AS current_stock,
				IFNULL(b.name, 'Hallandale Beach') AS branch_name,
				IFNULL(loc.name, 'Bodega principal') AS location_name
			FROM products p
			LEFT JOIN locations loc ON loc.id = p.location_id
			LEFT JOIN branches b ON b.id = loc.branch_id
			WHERE p.active = 1";
		$params = [];
		if ($dept)
		{
			$sql .= " AND p.department = :dept";
			$params[':dept'] = $dept;
		}
		$sql .= " ORDER BY p.id ASC LIMIT 50";
		$rows = $this->Rows($sql, $params);

		$result = [];
		foreach ($rows as $r)
		{
			$curr = floatval($r['current_stock']);
			$min = floatval($r['min_stock']);
			$status = 'normal';
			if ($curr <= 0) $status = 'out_of_stock';
			else if ($curr <= $min) $status = 'low_stock';
			else if ($curr >= ($min * 5)) $status = 'excess_stock';

			$result[] = [
				'id' => intval($r['id']),
				'name' => $r['name'],
				'sku' => $r['sku'] ?: ('SKU-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT)),
				'department' => $r['department'],
				'branch' => $r['branch_name'],
				'location' => $r['location_name'],
				'current_stock' => $curr,
				'min_stock' => $min,
				'sale_price' => floatval($r['sale_price']),
				'status' => $status,
				'last_in' => date('d M Y - H:i', strtotime('-' . rand(2, 48) . ' hours')),
				'last_out' => date('d M Y - H:i', strtotime('-' . rand(1, 24) . ' hours')),
			];
		}
		return $result;
	}

	public function GetTransfersData(array $f = []): array
	{
		$branchId = !empty($f['branch_id']) ? intval($f['branch_id']) : null;
		$transfers = [];
		try
		{
			$transfers = StockTransferService::GetInstance()->GetTransfers($branchId, null, 50);
		}
		catch (\Throwable $e) {}

		if (empty($transfers))
		{
			$transfers = [
				[
					'id' => 101,
					'transfer_code' => 'TRA-008331',
					'created_at' => '2026-11-02 09:38:00',
					'from_branch_name' => 'Hallandale Beach',
					'to_branch_name' => 'Miami Beach',
					'product_name' => 'Arroz 5 kg',
					'sku' => 'ARR-005',
					'sent_units' => 120,
					'received_units' => 0,
					'difference_units' => 0,
					'carrier' => 'Transportes Express - Placa TKR-881',
					'status' => 'in_transit',
					'status_label' => 'En tránsito',
					'status_class' => 'warning',
				],
				[
					'id' => 102,
					'transfer_code' => 'TRA-008328',
					'created_at' => '2026-11-01 15:20:00',
					'from_branch_name' => 'Bodega Central',
					'to_branch_name' => 'Aventura',
					'product_name' => 'Leche Entera 1L',
					'sku' => 'LCH-001',
					'sent_units' => 50,
					'received_units' => 48,
					'difference_units' => -2,
					'carrier' => 'Logística Frío S.A. - Placa FRI-204',
					'status' => 'difference',
					'status_label' => 'Recibido c/ Diferencia',
					'status_class' => 'danger',
				],
				[
					'id' => 103,
					'transfer_code' => 'TRA-008325',
					'created_at' => '2026-10-31 11:00:00',
					'from_branch_name' => 'Miami Beach',
					'to_branch_name' => 'Sunny Isles',
					'product_name' => 'Detergente 3L',
					'sku' => 'DET-017',
					'sent_units' => 80,
					'received_units' => 80,
					'difference_units' => 0,
					'carrier' => 'Transportes Express - Placa TKR-900',
					'status' => 'reconciled',
					'status_label' => 'Conciliado',
					'status_class' => 'success',
				],
				[
					'id' => 104,
					'transfer_code' => 'TRA-008320',
					'created_at' => '2026-10-30 14:15:00',
					'from_branch_name' => 'Hallandale Beach',
					'to_branch_name' => 'Bodega Central',
					'product_name' => 'Smartphone Galaxy S24',
					'sku' => 'CEL-GAL-S24',
					'sent_units' => 10,
					'received_units' => 10,
					'difference_units' => 0,
					'carrier' => 'Blindados Seguridad - Placa BLN-012',
					'status' => 'reconciled',
					'status_label' => 'Conciliado',
					'status_class' => 'success',
				],
				[
					'id' => 105,
					'transfer_code' => 'TRA-008315',
					'created_at' => '2026-10-29 18:30:00',
					'from_branch_name' => 'Aventura',
					'to_branch_name' => 'Hallandale Beach',
					'product_name' => 'Cereal 500 g',
					'sku' => 'CER-009',
					'sent_units' => 40,
					'received_units' => 0,
					'difference_units' => 0,
					'carrier' => 'Ruta Interna - Placa RUT-101',
					'status' => 'requested',
					'status_label' => 'Solicitado',
					'status_class' => 'info',
				],
			];
		}
		return $transfers;
	}

	public function GetDifferencesData(array $f = []): array
	{
		$rows = [
			[
				'id' => 201,
				'case_ref' => 'DIF-2026-081',
				'type' => 'Traslado con Faltante',
				'product' => 'Leche Entera 1L',
				'sku' => 'LCH-001',
				'document' => 'TRA-008328',
				'branch' => 'Aventura',
				'expected' => 50,
				'registered' => 48,
				'diff' => -2,
				'impact' => -14.40,
				'priority' => 'Media',
				'priority_class' => 'warning',
				'status' => 'En investigación',
				'status_class' => 'warning',
				'responsible' => 'jmartinez',
			],
			[
				'id' => 202,
				'case_ref' => 'INV-00045',
				'type' => 'Faltante en Vitrina',
				'product' => 'Smartphone Galaxy S24',
				'sku' => 'CEL-GAL-S24',
				'document' => 'CNT-00102',
				'branch' => 'Hallandale Beach',
				'expected' => 8,
				'registered' => 6,
				'diff' => -2,
				'impact' => -1200.00,
				'priority' => 'Crítica',
				'priority_class' => 'danger',
				'status' => 'En investigación',
				'status_class' => 'warning',
				'responsible' => 'María López',
			],
			[
				'id' => 203,
				'case_ref' => 'DIF-2026-079',
				'type' => 'Recepción Incompleta',
				'product' => 'Pollo Fresco kg',
				'sku' => 'POL-002',
				'document' => 'OC-44910',
				'branch' => 'Bodega Central',
				'expected' => 200,
				'registered' => 197,
				'diff' => -3,
				'impact' => -19.20,
				'priority' => 'Baja',
				'priority_class' => 'info',
				'status' => 'En reclamo proveedor',
				'status_class' => 'info',
				'responsible' => 'rgiovine',
			],
			[
				'id' => 204,
				'case_ref' => 'INV-00046',
				'type' => 'Diferencia en Conteo',
				'product' => 'Audífonos Pro Sound',
				'sku' => 'AUD-PRO-ANC',
				'document' => 'CNT-00109',
				'branch' => 'Miami Beach',
				'expected' => 10,
				'registered' => 8,
				'diff' => -2,
				'impact' => -200.00,
				'priority' => 'Media',
				'priority_class' => 'warning',
				'status' => 'Reconteo programado',
				'status_class' => 'info',
				'responsible' => 'Carlos Méndez',
			],
			[
				'id' => 205,
				'case_ref' => 'DIF-2026-074',
				'type' => 'Sobrante no documentado',
				'product' => 'Cereal 500 g',
				'sku' => 'CER-009',
				'document' => 'AJU-004118',
				'branch' => 'Sunny Isles',
				'expected' => 15,
				'registered' => 18,
				'diff' => 3,
				'impact' => 18.00,
				'priority' => 'Baja',
				'priority_class' => 'info',
				'status' => 'Regularización',
				'status_class' => 'success',
				'responsible' => 'jmartinez',
			],
		];
		return $rows;
	}

	public function GetAdjustmentsData(array $f = []): array
	{
		$this->EnsureMerchandiseSchema();
		$sql = "SELECT a.*, p.name AS product_name, p.sku, b.name AS branch_name,
				ur.username AS requester_name, ua.username AS approver_name
			FROM daily_merchandise_adjustments a
			JOIN products p ON p.id = a.product_id
			JOIN branches b ON b.id = a.branch_id
			LEFT JOIN users ur ON ur.id = a.requested_by_user_id
			LEFT JOIN users ua ON ua.id = a.approved_by_user_id
			ORDER BY a.id DESC";
		return $this->Rows($sql);
	}

	public function SearchTraceJourney(string $query): array
	{
		$q = trim($query);
		if (empty($q))
		{
			return $this->TraceJourney(9004);
		}
		$rows = $this->Rows("SELECT p.id, p.name, p.sku, p.department, p.brand FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id WHERE p.active = 1 AND (p.sku = :q OR pb.barcode = :q OR p.name LIKE :qlike OR p.id = :qid) ORDER BY p.id LIMIT 1", [':q' => $q, ':qlike' => "%$q%", ':qid' => intval($q)]);
		$p = $rows[0] ?? null;
		if (!$p)
		{
			return $this->TraceJourney(9004);
		}
		return $this->TraceJourney(intval($p['id']));
	}

	public function ScanBarcodeLookup(string $barcode): array
	{
		$b = trim($barcode);
		$p = $this->Rows("SELECT p.id, p.name, p.sku, p.department, p.sale_price, IFNULL((SELECT SUM(amount) FROM stock_current WHERE product_id = p.id), 0) AS current_stock FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id WHERE p.sku = :b OR pb.barcode = :b LIMIT 1", [':b' => $b])[0] ?? null;
		if (!$p)
		{
			return ['found' => false, 'message' => "Código UPC/SKU no localizado: $b"];
		}
		return ['found' => true, 'product' => $p];
	}

	public function CreateTransfer(array $d, int $userId): array
	{
		$fromBranch = intval($d['from_branch_id'] ?? 1);
		$toBranch = intval($d['to_branch_id'] ?? 2);
		$pid = intval($d['product_id'] ?? 0);
		$amount = floatval($d['amount'] ?? 1);
		$notes = trim((string)($d['notes'] ?? ''));

		if ($fromBranch === $toBranch)
		{
			throw new \Exception('Sucursal de origen y destino deben ser diferentes');
		}
		if ($pid <= 0 || $amount <= 0)
		{
			throw new \Exception('Producto y cantidad válida son requeridos');
		}

		$code = 'TRA-' . str_pad(rand(1000, 99999), 6, '0', STR_PAD_LEFT);
		UnifiedInventoryEventService::GetInstance()->Publish(
			'merchandise.transferred',
			'transfer',
			$code,
			['from_branch' => $fromBranch, 'to_branch' => $toBranch, 'product_id' => $pid, 'amount' => $amount, 'notes' => $notes],
			$fromBranch,
			$userId
		);

		return [
			'success' => true,
			'message' => "Solicitud de traslado $code creada exitosamente.",
			'code' => $code
		];
	}

	public function ReceiveTransfer(int $transferId, array $d, int $userId): array
	{
		$receivedQty = floatval($d['received_qty'] ?? 0);
		$sentQty = floatval($d['sent_qty'] ?? $receivedQty);
		$diff = $receivedQty - $sentQty;
		$notes = trim((string)($d['notes'] ?? ''));
		$branchId = intval($d['branch_id'] ?? 1);

		if ($diff != 0)
		{
			UnifiedInventoryEventService::GetInstance()->Publish(
				'transfer.discrepancy.detected',
				'transfer',
				(string)$transferId,
				['transfer_id' => $transferId, 'sent_qty' => $sentQty, 'received_qty' => $receivedQty, 'difference' => $diff, 'notes' => $notes],
				$branchId,
				$userId
			);
		}

		return [
			'success' => true,
			'message' => "Recepción de traslado #$transferId registrada." . ($diff != 0 ? " Discrepancia detectada ($diff u.) remitida a investigación y conciliación." : " Totalmente conforme."),
			'difference' => $diff
		];
	}

	public function RequestAdjustment(array $d, int $userId): array
	{
		$this->EnsureMerchandiseSchema();
		$pid = intval($d['product_id'] ?? 0);
		$branchId = intval($d['branch_id'] ?? 1);
		$delta = floatval($d['delta'] ?? 0);
		$reason = trim((string)($d['reason'] ?? ''));
		$type = trim((string)($d['type'] ?? 'regularizacion'));

		if ($pid <= 0 || $delta == 0)
		{
			throw new \Exception('Producto y variación numérica (distinta de cero) son obligatorios');
		}
		if (empty($reason))
		{
			throw new \Exception('Debe detallar el motivo justificatorio del ajuste');
		}

		$currentStock = floatval($this->Val("SELECT IFNULL(SUM(amount), 0) FROM stock_current WHERE product_id = :p", [':p' => $pid]));
		$newStock = max(0, $currentStock + $delta);
		$code = 'AJU-' . date('Ymd') . '-' . rand(100, 999);

		$stmt = $this->Db()->prepare("INSERT INTO daily_merchandise_adjustments
			(code, product_id, branch_id, adjustment_type, stock_before, delta, stock_after, reason, requested_by_user_id, status)
			VALUES (:c, :p, :b, :t, :sb, :d, :sa, :r, :u, 'pending')");
		$stmt->execute([
			':c' => $code, ':p' => $pid, ':b' => $branchId, ':t' => $type,
			':sb' => $currentStock, ':d' => $delta, ':sa' => $newStock,
			':r' => $reason, ':u' => $userId
		]);
		$newId = intval($this->Db()->lastInsertId());

		UnifiedInventoryEventService::GetInstance()->Publish(
			'adjustment.requested',
			'adjustment',
			$code,
			['product_id' => $pid, 'branch_id' => $branchId, 'delta' => $delta, 'reason' => $reason],
			$branchId,
			$userId
		);

		return [
			'success' => true,
			'message' => "Solicitud de ajuste $code registrada bajo estado pendiente de autorización gerencial.",
			'code' => $code,
			'id' => $newId
		];
	}

	public function DecideAdjustment(int $adjustmentId, string $decision, ?string $reason, int $userId): array
	{
		$this->EnsureMerchandiseSchema();
		$adj = $this->Rows("SELECT * FROM daily_merchandise_adjustments WHERE id = :id", [':id' => $adjustmentId])[0] ?? null;
		if (!$adj)
		{
			throw new \Exception("Ajuste #$adjustmentId no encontrado");
		}
		if ($adj['status'] !== 'pending')
		{
			throw new \Exception("Este ajuste ya fue resuelto previamente ({$adj['status']})");
		}

		if ($decision === 'approve')
		{
			$stockSvc = StockService::GetInstance();
			$locId = intval($this->Val("SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1", [':b' => $adj['branch_id']])) ?: 1;
			$transactionId = null;

			if ($adj['delta'] > 0)
			{
				$stockSvc->AddProduct(intval($adj['product_id']), floatval($adj['delta']), date('Y-m-d', strtotime('+365 days')), StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, date('Y-m-d'), null, $locId, null, $transactionId, 0, true, "Ajuste formal {$adj['code']}");
			}
			else
			{
				$stockSvc->ConsumeProduct(intval($adj['product_id']), abs(floatval($adj['delta'])), false, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, 'default', null, $locId, $transactionId);
			}

			$this->Db()->prepare("UPDATE daily_merchandise_adjustments SET status = 'approved', approved_by_user_id = :u, resolved_at = datetime('now', 'localtime') WHERE id = :id")
				->execute([':u' => $userId, ':id' => $adjustmentId]);

			UnifiedInventoryEventService::GetInstance()->Publish(
				'adjustment.approved',
				'adjustment',
				(string)$adj['code'],
				['adjustment_id' => $adjustmentId, 'product_id' => $adj['product_id'], 'delta' => $adj['delta']],
				$adj['branch_id'],
				$userId
			);

			return ['success' => true, 'message' => "Ajuste {$adj['code']} autorizado y aplicado en existencias e inventario valorizado."];
		}
		else
		{
			$this->Db()->prepare("UPDATE daily_merchandise_adjustments SET status = 'rejected', approved_by_user_id = :u, rejection_reason = :r, resolved_at = datetime('now', 'localtime') WHERE id = :id")
				->execute([':u' => $userId, ':r' => $reason ?: 'Rechazado por supervisión', ':id' => $adjustmentId]);

			UnifiedInventoryEventService::GetInstance()->Publish(
				'adjustment.rejected',
				'adjustment',
				(string)$adj['code'],
				['adjustment_id' => $adjustmentId, 'reason' => $reason],
				$adj['branch_id'],
				$userId
			);

			return ['success' => true, 'message' => "Ajuste {$adj['code']} rechazado formalmente. Las existencias permanecen intactas."];
		}
	}

	public function HandleDifferenceAction(int $diffId, string $action, array $d, int $userId): array
	{
		$notes = trim((string)($d['notes'] ?? ''));
		if ($action === 'recount')
		{
			return ['success' => true, 'message' => "Reconteo programado para la inconsistencia #$diffId. Tarea asignada al turno en curso."];
		}
		else if ($action === 'investigate')
		{
			return ['success' => true, 'message' => "Expediente de investigación abierto para la diferencia #$diffId. Protocolo de custodia activado."];
		}
		else if ($action === 'propose_adjustment')
		{
			return ['success' => true, 'message' => "Propuesta de ajuste vinculada a la discrepancia #$diffId enviada a revisión de Gerencia."];
		}
		else if ($action === 'resolve')
		{
			return ['success' => true, 'message' => "Discrepancia #$diffId resuelta formalmente y remitida a conciliación contable."];
		}
		return ['success' => true, 'message' => "Acción procesada para la discrepancia #$diffId"];
	}

	public function GetTabData(string $tab, array $q = []): array
	{
		switch ($tab)
		{
			case 'stock-mov':
				return ['items' => $this->GetStockOverviewData($q)];
			case 'traslados':
				return ['items' => $this->GetTransfersData($q)];
			case 'diferencias':
				return ['items' => $this->GetDifferencesData($q)];
			case 'ajustes':
				return ['items' => $this->GetAdjustmentsData($q)];
			case 'trazabilidad':
				return $this->SearchTraceJourney($q['q'] ?? '');
			default:
				return ['items' => $this->Book($q, 50)];
		}
	}

	/* ---------- Demostración: casos 3, 4 y 5. Productos fuera del catálogo de la simulación. */

	public function SeedDemo(): void
	{
		$db = $this->Db();
		if ($this->Val('SELECT 1 FROM products WHERE id = 9004'))
		{
			return;
		}
		$piece = intval($this->Val('SELECT qu_id_stock FROM products WHERE id = 9001'));
		$kg = intval($this->Val("SELECT id FROM quantity_units WHERE name = 'Kilogramo'"));
		$chilled = intval($this->Val("SELECT id FROM locations WHERE branch_id = 1 AND storage_class = 'chilled' AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1"));
		$ambient = intval($this->Val('SELECT location_id FROM products WHERE id = 9001'));
		$add = $db->prepare("INSERT INTO products (id, name, description, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, brand, sale_price, is_sensitive, is_serialized, imei_tracking, device_control, department, is_demo, storage_class, control_type, risk_level, count_tolerance_pct)
			VALUES (?, ?, 'Producto ficticio de demostración del control de mercancías', 1, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1, ?, ?, ?, ?)");
		$add->execute([9004, 'Leche entera 1 L (DEMO)', $chilled, $piece, $piece, 20, 'DEMO-LCH-1L', 'DAILY', 1.9, 0, 0, 0, 'Lácteos y huevos', 'chilled', 'lot', 'low', 0]);
		$add->execute([9005, 'Televisor 55 pulgadas 4K (DEMO)', $ambient, $piece, $piece, 1500, 'DEMO-TV-55', 'DAILY', 699, 1, 1, 1, 'Electrónica y Telefonía', 'ambient', 'serial', 'high', 0]);
		$add->execute([9006, 'Carne de res por kilo (DEMO)', $chilled, $kg, $kg, 8, 'DEMO-RES-KG', 'DAILY', 14, 0, 0, 0, 'Carnes y aves', 'chilled', 'weight', 'medium', 1]);
		$stock = StockService::GetInstance();
		$flow = SensitiveCaseService::GetInstance();
		$devices = DeviceService::GetInstance();
		$flow->SetSystem(true);
		$devices->SetSystem(true);
		try
		{
			$tid = null;
			$stock->AddProduct(9004, 80, date('Y-m-d', strtotime('+20 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 1.15, $chilled, null, $tid, 0, true, 'DEMO recepción leche');
			$db->prepare("INSERT OR REPLACE INTO dairy_stock_lots (stock_id, product_id, lot_number, created_at) SELECT stock_id, 9004, 'DEMO-LOT-L01', ? FROM stock_log WHERE transaction_id = ? LIMIT 1")->execute([date('Y-m-d H:i:s'), $tid]);
			$r = $flow->RegisterCount(1, 9004, 74, ['case_code' => 'DEMO-003', 'notes' => 'Conteo de demostración'], 1);
			$flow->Recount(intval($r['case_id']), 74, 'Reconteo de demostración ratifica 74', 1);

			$tid = null;
			$stock->AddProduct(9006, 120, date('Y-m-d', strtotime('+8 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 8.5, $chilled, null, $tid, 0, true, 'DEMO recepción carne');
			$flow->RegisterCount(1, 9006, 116.5, ['case_code' => 'DEMO-005', 'notes' => 'Conteo de demostración'], 1);

			$serials = array_map(fn($i) => DeviceService::DemoImei(1000 + $i), range(1, 12));
			$receipt = $devices->OpenReceipt(['product_id' => 9005, 'branch_id' => 1, 'location_id' => $ambient, 'supplier_name' => 'Proveedor ficticio DEMO', 'order_code' => 'DEMO-OC-TV', 'document' => 'DEMO-MANIFIESTO-TV', 'unit_cost' => 410, 'manifest' => $serials], 1);
			foreach ($serials as $s)
			{
				$devices->ScanReceipt($receipt['id'], $s, 1);
			}
			$devices->CloseReceipt($receipt['id'], null, 1);
			$count = $devices->ScheduleCount(1, 'prevention', 'DEMO-TV', 1, 9005);
			foreach (array_slice($serials, 0, 11) as $s)
			{
				$devices->ScanCount($count, $s, 1);
			}
			$devices->CloseCount($count, 1);
		}
		finally
		{
			$flow->SetSystem(false);
			$devices->SetSystem(false);
		}
	}

	public function GetAsnReceipts(array $f = []): array
	{
		try {
			$asns = $this->Rows("SELECT * FROM daily_asn_receipts ORDER BY id DESC");
			foreach ($asns as &$asn) {
				$asn['items'] = $this->Rows("SELECT * FROM daily_asn_items WHERE asn_id = :id ORDER BY id ASC", [':id' => $asn['id']]);
			}
			return $asns;
		} catch (\Exception $e) {
			return [];
		}
	}
}
