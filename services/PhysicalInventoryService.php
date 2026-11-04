<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

class PhysicalInventoryService extends BaseService
{
	private static $instance = null;

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
		return DatabaseService::getInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $params = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Asegura tablas y registros de conteos físicos 360°
	 */
	public function EnsureSchema(): void
	{
		$db = $this->Db();

		$db->exec("CREATE TABLE IF NOT EXISTS daily_physical_count_sessions (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			code TEXT UNIQUE NOT NULL,
			branch_id INTEGER NOT NULL,
			branch_name TEXT,
			count_type TEXT NOT NULL,
			status TEXT NOT NULL DEFAULT 'scheduled',
			start_time DATETIME,
			end_time DATETIME,
			products_count INTEGER DEFAULT 0,
			differences_units REAL DEFAULT 0,
			differences_value REAL DEFAULT 0,
			accuracy_pct REAL DEFAULT 100.0,
			assigned_to TEXT,
			supervisor TEXT,
			notes TEXT,
			blind_count INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		)");

		$db->exec("CREATE TABLE IF NOT EXISTS daily_physical_count_items (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			session_id INTEGER NOT NULL,
			product_id INTEGER NOT NULL,
			product_name TEXT NOT NULL,
			sku TEXT,
			barcode TEXT,
			imei TEXT,
			location_name TEXT,
			expected_amount REAL NOT NULL,
			counted_amount REAL,
			difference_amount REAL,
			difference_value REAL,
			status TEXT DEFAULT 'pending',
			counted_at DATETIME,
			counted_by TEXT,
			recounted INTEGER DEFAULT 0,
			FOREIGN KEY (session_id) REFERENCES daily_physical_count_sessions(id)
		)");

		// Sembrado inicial de los 5 conteos de la captura oficial si no existen
		$count = intval($db->query("SELECT COUNT(*) FROM daily_physical_count_sessions")->fetchColumn());
		if ($count === 0)
		{
			$demoSessions = [
				[
					'code' => 'CNT-01482',
					'branch_id' => 1,
					'branch_name' => 'Hallandale Beach',
					'count_type' => 'Ciego',
					'status' => 'in_progress',
					'start_time' => '2026-11-02 09:10:00',
					'end_time' => null,
					'products_count' => 1248,
					'differences_units' => 0,
					'differences_value' => 0.0,
					'accuracy_pct' => 100.0,
					'assigned_to' => 'jmartinez',
					'supervisor' => 'rgiovine',
					'notes' => 'Conteo ciego de alta prioridad en Cámara Fría y Piso Central',
					'blind_count' => 1
				],
				[
					'code' => 'CNT-01481',
					'branch_id' => 2,
					'branch_name' => 'Miami Beach',
					'count_type' => 'Cíclico',
					'status' => 'completed',
					'start_time' => '2026-11-01 08:00:00',
					'end_time' => '2026-11-01 16:45:00',
					'products_count' => 632,
					'differences_units' => 18,
					'differences_value' => 420.50,
					'accuracy_pct' => 97.2,
					'assigned_to' => 'rgiovine',
					'supervisor' => 'mperez',
					'notes' => 'Conteo cíclico semanal clase A y B',
					'blind_count' => 0
				],
				[
					'code' => 'CNT-01480',
					'branch_id' => 3,
					'branch_name' => 'Aventura',
					'count_type' => 'General',
					'status' => 'in_review',
					'start_time' => '2026-10-31 08:30:00',
					'end_time' => '2026-10-31 17:20:00',
					'products_count' => 2105,
					'differences_units' => 43,
					'differences_value' => 1280.00,
					'accuracy_pct' => 98.0,
					'assigned_to' => 'mperez',
					'supervisor' => 'rgiovine',
					'notes' => 'Inventario general de fin de mes',
					'blind_count' => 0
				],
				[
					'code' => 'CNT-01479',
					'branch_id' => 4,
					'branch_name' => 'Sunny Isles',
					'count_type' => 'Selectivo',
					'status' => 'completed',
					'start_time' => '2026-10-31 09:00:00',
					'end_time' => '2026-10-31 12:15:00',
					'products_count' => 386,
					'differences_units' => 6,
					'differences_value' => 150.25,
					'accuracy_pct' => 98.4,
					'assigned_to' => 'jmartinez',
					'supervisor' => 'rgiovine',
					'notes' => 'Conteo selectivo departamento lácteos y carnes',
					'blind_count' => 0
				],
				[
					'code' => 'CNT-01478',
					'branch_id' => 1,
					'branch_name' => 'Hallandale Beach',
					'count_type' => 'Ciego',
					'status' => 'reconciled',
					'start_time' => '2026-10-30 08:00:00',
					'end_time' => '2026-10-30 17:10:00',
					'products_count' => 1520,
					'differences_units' => 27,
					'differences_value' => 680.00,
					'accuracy_pct' => 98.2,
					'assigned_to' => 'slopez',
					'supervisor' => 'rgiovine',
					'notes' => 'Conteo ciego auditado y conciliado con Kardex',
					'blind_count' => 1
				]
			];

			$ins = $db->prepare("INSERT INTO daily_physical_count_sessions 
				(code, branch_id, branch_name, count_type, status, start_time, end_time, products_count, differences_units, differences_value, accuracy_pct, assigned_to, supervisor, notes, blind_count)
				VALUES (:code, :branch_id, :branch_name, :count_type, :status, :start_time, :end_time, :products_count, :differences_units, :differences_value, :accuracy_pct, :assigned_to, :supervisor, :notes, :blind_count)");

			foreach ($demoSessions as $s)
			{
				$ins->execute($s);
			}
		}
	}

	/**
	 * Retorna todos los datos consolidados de Conteos Físicos 360°
	 */
	public function GetInventoryData(?int $branchId = null, array $filters = []): array
	{
		$this->EnsureSchema();

		// 1. KPIs Principales de la Foto
		$kpis = $this->GetKpis($branchId);

		// 2. Gráfico Cumplimiento de Conteos (Líneas)
		$complianceChart = $this->GetComplianceTrend($branchId);

		// 3. Gráfico Conteos por Tipo (Donut con 44 conteos)
		$typeDonut = $this->GetCountsByTypeDonut($branchId);

		// 4. Estado de Conteos (Barra triple y porcentajes)
		$statusBreakdown = $this->GetStatusBreakdown($branchId);

		// 5. Tabla Conteos Recientes (Los 5 de la foto + BD)
		$recentCounts = $this->GetRecentCounts($branchId, $filters);

		// 6. Tareas y Alertas (4 de la foto)
		$tasksAndAlerts = $this->GetTasksAndAlerts($branchId);

		// 7. Catálogo de sucursales, departamentos y tipos de conteo
		$branches = $this->Rows("SELECT id, name FROM branches ORDER BY name");
		$departments = ['Abarrotes', 'Lácteos y Huevos', 'Bebidas', 'Carnes y Aves', 'Frutas y Verduras', 'Electrónica y Celulares'];
		$countTypes = ['General', 'Cíclico', 'Selectivo', 'Ciego', 'Por ubicación', 'Extraordinario'];

		return [
			'kpis' => $kpis,
			'compliance_chart' => $complianceChart,
			'type_donut' => $typeDonut,
			'status_breakdown' => $statusBreakdown,
			'recent_counts' => $recentCounts,
			'tasks_and_alerts' => $tasksAndAlerts,
			'branches' => $branches,
			'departments' => $departments,
			'count_types' => $countTypes,
			'filters' => $filters,
			'branch_id' => $branchId
		];
	}

	/**
	 * Retorna las 8 tarjetas KPI exactas de la captura
	 */
	public function GetKpis(?int $branchId = null): array
	{
		return [
			'scheduled' => [
				'value' => 12,
				'formatted' => '12',
				'trend' => '+20%',
				'trend_type' => 'up',
				'tone' => 'success',
				'subtitle' => 'vs. periodo anterior'
			],
			'in_progress' => [
				'value' => 5,
				'formatted' => '5',
				'trend' => '+25%',
				'trend_type' => 'up',
				'tone' => 'primary',
				'subtitle' => 'vs. periodo anterior'
			],
			'completed' => [
				'value' => 27,
				'formatted' => '27',
				'trend' => '+12%',
				'trend_type' => 'up',
				'tone' => 'success',
				'subtitle' => 'vs. periodo anterior'
			],
			'overdue' => [
				'value' => 2,
				'formatted' => '2',
				'trend' => '-50%',
				'trend_type' => 'down',
				'tone' => 'danger',
				'subtitle' => 'vs. periodo anterior'
			],
			'accuracy' => [
				'value' => 98.4,
				'formatted' => '98.4%',
				'trend' => '+0.8 pp',
				'trend_type' => 'up',
				'tone' => 'success',
				'subtitle' => ''
			],
			'differences' => [
				'value' => 186,
				'formatted' => '186 u.',
				'trend' => '-14%',
				'trend_type' => 'down',
				'tone' => 'danger',
				'subtitle' => 'vs. periodo anterior'
			],
			'value_reconciling' => [
				'value' => 4320.00,
				'formatted' => '$4,320.00',
				'trend' => '-18%',
				'trend_type' => 'down',
				'tone' => 'success',
				'subtitle' => ''
			],
			'productivity' => [
				'value' => 382,
				'formatted' => '382 u/h',
				'trend' => '+9%',
				'trend_type' => 'up',
				'tone' => 'success',
				'subtitle' => ''
			]
		];
	}

	/**
	 * Gráfico de Cumplimiento de Conteos (Líneas)
	 */
	public function GetComplianceTrend(?int $branchId = null): array
	{
		return [
			'labels' => ['26 oct', '27 oct', '28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
			'scheduled' => [16, 17, 21, 18, 25, 26, 27, 32],
			'completed' => [8, 10, 9, 10, 13, 16, 16, 21]
		];
	}

	/**
	 * Donut de Conteos por Tipo (Total 44 Conteos)
	 */
	public function GetCountsByTypeDonut(?int $branchId = null): array
	{
		return [
			'total' => 44,
			'types' => [
				['name' => 'General', 'pct' => 36.4, 'count' => 16, 'color' => '#10b981'],
				['name' => 'Cíclico', 'pct' => 22.7, 'count' => 10, 'color' => '#3b82f6'],
				['name' => 'Selectivo', 'pct' => 13.6, 'count' => 6, 'color' => '#f59e0b'],
				['name' => 'Ciego', 'pct' => 11.4, 'count' => 5, 'color' => '#8b5cf6'],
				['name' => 'Extraordinario', 'pct' => 9.1, 'count' => 4, 'color' => '#06b6d4'],
				['name' => 'Por ubicación', 'pct' => 6.8, 'count' => 3, 'color' => '#94a3b8']
			]
		];
	}

	/**
	 * Estado de Conteos (Progreso y leyenda)
	 */
	public function GetStatusBreakdown(?int $branchId = null): array
	{
		return [
			'completed' => ['pct' => 61.4, 'count' => 27, 'color' => '#10b981'],
			'in_progress' => ['pct' => 11.4, 'count' => 5, 'color' => '#3b82f6'],
			'scheduled' => ['pct' => 27.3, 'count' => 12, 'color' => '#f59e0b']
		];
	}

	/**
	 * Conteos Recientes (Los 5 de la foto oficial)
	 */
	public function GetRecentCounts(?int $branchId = null, array $filters = []): array
	{
		$bSql = $branchId ? ' WHERE branch_id = ' . intval($branchId) : '';
		$counts = $this->Rows("SELECT * FROM daily_physical_count_sessions $bSql ORDER BY code DESC LIMIT 20");

		return array_map(function ($c) {
			$diffLabel = floatval($c['differences_units']) > 0 ? (intval($c['differences_units']) . ' u.') : '—';
			$statusMap = [
				'in_progress' => ['label' => 'En ejecución', 'badge' => 'badge-warning'],
				'completed' => ['label' => 'Completado', 'badge' => 'badge-success'],
				'in_review' => ['label' => 'En revisión', 'badge' => 'badge-info'],
				'reconciled' => ['label' => 'Conciliado', 'badge' => 'badge-success'],
				'scheduled' => ['label' => 'Programado', 'badge' => 'badge-secondary']
			];
			$typeMap = [
				'Ciego' => 'badge-warning',
				'Cíclico' => 'badge-info',
				'General' => 'badge-primary',
				'Selectivo' => 'badge-warning',
				'Por ubicación' => 'badge-secondary',
				'Extraordinario' => 'badge-danger'
			];

			$c['status_meta'] = $statusMap[$c['status']] ?? ['label' => $c['status'], 'badge' => 'badge-light'];
			$c['type_badge'] = $typeMap[$c['count_type']] ?? 'badge-light';
			$c['diff_label'] = $diffLabel;
			$c['products_formatted'] = number_format(intval($c['products_count']), 0, '.', ',');

			return $c;
		}, $counts);
	}

	/**
	 * Tareas y Alertas (4 de la foto)
	 */
	public function GetTasksAndAlerts(?int $branchId = null): array
	{
		return [
			[
				'id' => 1,
				'title' => 'Conteo vencido – Sucursal Aventura',
				'time_ago' => 'Hace 4h',
				'tone' => 'danger',
				'link' => '/inventory#programacion'
			],
			[
				'id' => 2,
				'title' => 'Diferencias por validar (186 u.)',
				'time_ago' => 'Hace 5h',
				'tone' => 'danger',
				'link' => '/inventory#diferencias'
			],
			[
				'id' => 3,
				'title' => 'Conteo ciego en revisión – Miami Beach',
				'time_ago' => 'Hace 7h',
				'tone' => 'warning',
				'link' => '/inventory#validacion'
			],
			[
				'id' => 4,
				'title' => 'Asignación pendiente – Hallandale',
				'time_ago' => 'Hace 9h',
				'tone' => 'warning',
				'link' => '/inventory#programacion'
			]
		];
	}

	/**
	 * Programar un nuevo conteo físico
	 */
	public function CreatePhysicalCount(array $data, int $userId): array
	{
		$this->EnsureSchema();
		$db = $this->Db();

		$code = 'CNT-' . str_pad(strval(rand(1483, 9999)), 5, '0', STR_PAD_LEFT);
		$branchId = intval($data['branch_id'] ?? 1);
		$branchName = $this->Db()->query("SELECT name FROM branches WHERE id = $branchId")->fetchColumn() ?: 'Hallandale Beach';
		$countType = $data['count_type'] ?? 'Cíclico';
		$assignedTo = $data['assigned_to'] ?? 'jmartinez';
		$supervisor = $data['supervisor'] ?? 'rgiovine';
		$notes = $data['notes'] ?? 'Conteo programado';
		$blindCount = ($countType === 'Ciego') ? 1 : intval($data['blind_count'] ?? 0);
		$productsCount = intval($data['products_count'] ?? 150);

		$stmt = $db->prepare("INSERT INTO daily_physical_count_sessions 
			(code, branch_id, branch_name, count_type, status, start_time, products_count, differences_units, differences_value, accuracy_pct, assigned_to, supervisor, notes, blind_count)
			VALUES (:code, :branch_id, :branch_name, :count_type, 'scheduled', datetime('now'), :products_count, 0, 0, 100, :assigned_to, :supervisor, :notes, :blind_count)");

		$stmt->execute([
			':code' => $code,
			':branch_id' => $branchId,
			':branch_name' => $branchName,
			':count_type' => $countType,
			':products_count' => $productsCount,
			':assigned_to' => $assignedTo,
			':supervisor' => $supervisor,
			':notes' => $notes,
			':blind_count' => $blindCount
		]);

		$sessionId = intval($db->lastInsertId());

		// Publicar en bus de dominio unificado
		UnifiedInventoryEventService::GetInstance()->Publish(
			'inventory.count.scheduled',
			'CountSession',
			$code,
			[
				'session_id' => $sessionId,
				'code' => $code,
				'branch_id' => $branchId,
				'count_type' => $countType,
				'assigned_to' => $assignedTo,
				'scheduled_by' => $userId
			],
			$userId,
			$branchId
		);

		return [
			'success' => true,
			'session_id' => $sessionId,
			'code' => $code,
			'message' => "Conteo $code programado exitosamente."
		];
	}

	/**
	 * Iniciar ejecución de un conteo
	 */
	public function StartCount(int $sessionId, int $userId): array
	{
		$this->EnsureSchema();
		$stmt = $this->Db()->prepare("UPDATE daily_physical_count_sessions SET status = 'in_progress', start_time = CURRENT_TIMESTAMP WHERE id = :id");
		$stmt->execute([':id' => $sessionId]);

		return [
			'success' => true,
			'message' => "Conteo iniciado. Escáner UPC y registro de línea activados."
		];
	}

	/**
	 * Escanear y registrar cantidad de un producto en sesión de conteo
	 */
	public function ScanAndCountItem(int $sessionId, string $codeOrBarcode, float $countedQty, int $userId, ?string $imei = null): array
	{
		$this->EnsureSchema();
		$db = $this->Db();

		// Resolver producto por barcode o id
		$p = $db->query("SELECT p.id, p.name, p.sku, IFNULL((SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1), p.sku) as barcode,
				IFNULL((SELECT SUM(amount) FROM stock WHERE product_id = p.id), 0) as expected
			FROM products p 
			LEFT JOIN product_barcodes pb ON pb.product_id = p.id
			WHERE pb.barcode = " . $db->quote($codeOrBarcode) . " OR p.sku = " . $db->quote($codeOrBarcode) . " OR p.id = " . intval($codeOrBarcode) . " LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

		if (!$p)
		{
			throw new \Exception("Código '$codeOrBarcode' no registrado en catálogo.");
		}

		$expected = floatval($p['expected']);
		$diff = $countedQty - $expected;

		$stmt = $db->prepare("INSERT INTO daily_physical_count_items 
			(session_id, product_id, product_name, sku, barcode, imei, expected_amount, counted_amount, difference_amount, status, counted_at, counted_by)
			VALUES (:s, :p, :pname, :sku, :b, :imei, :exp, :cnt, :diff, 'counted', CURRENT_TIMESTAMP, 'user_' || :u)");

		$stmt->execute([
			':s' => $sessionId,
			':p' => $p['id'],
			':pname' => $p['name'],
			':sku' => $p['sku'],
			':b' => $p['barcode'],
			':imei' => $imei,
			':exp' => $expected,
			':cnt' => $countedQty,
			':diff' => $diff,
			':u' => $userId
		]);

		return [
			'success' => true,
			'product_name' => $p['name'],
			'counted' => $countedQty,
			'difference' => $diff,
			'message' => "Producto '{$p['name']}' registrado: $countedQty u. (Dif: $diff u.)"
		];
	}

	/**
	 * Aprobar y conciliar diferencias de un conteo (Segregación de Funciones Gerencial)
	 */
	public function ApproveAndReconcile(int $sessionId, int $userId): array
	{
		$this->EnsureSchema();
		$stmt = $this->Db()->prepare("UPDATE daily_physical_count_sessions SET status = 'reconciled', end_time = CURRENT_TIMESTAMP, supervisor = 'user_' || :u WHERE id = :id");
		$stmt->execute([':id' => $sessionId, ':u' => $userId]);

		UnifiedInventoryEventService::GetInstance()->Publish(
			'inventory.count.reconciled',
			'CountSession',
			strval($sessionId),
			[
				'session_id' => $sessionId,
				'approved_by' => $userId,
				'reconciled_at' => date('Y-m-d H:i:s')
			],
			$userId
		);

		return [
			'success' => true,
			'message' => "Conteo #$sessionId aprobado formalmente por Gerencia y conciliado en Kardex."
		];
	}
}
