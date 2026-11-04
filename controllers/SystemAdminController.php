<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Services\DatabaseService;
use Grocy\Services\BranchService;
use Grocy\Helpers\DairyAccess;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SystemAdminController extends BaseController
{
	private function Db(): \PDO
	{
		return DatabaseService::getInstance()->GetDbConnectionRaw();
	}

	private function EnsureSchema(): void
	{
		$db = $this->Db();
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_system_settings (
				setting_key TEXT PRIMARY KEY,
				setting_value TEXT NOT NULL,
				category TEXT DEFAULT 'general',
				description TEXT,
				updated_by_user_id INTEGER,
				updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
			)
		");

		$defaults = [
			['company_name', 'DAILY Retail Enterprise S.A.S.', 'general', 'Nombre oficial de la empresa'],
			['company_tax_id', 'NIT 900.842.119-4', 'general', 'Identificación tributaria o fiscal'],
			['default_currency', 'USD', 'general', 'Moneda principal del sistema ($)'],
			['currency_symbol', '$', 'general', 'Símbolo monetario visual'],
			['timezone', 'America/Bogota', 'general', 'Zona horaria operativa estándar'],
			['multi_tenant_isolation', '1', 'security', 'Aislamiento estricto de datos por empresa y sucursal'],
			['auth_session_lifetime_hours', '12', 'security', 'Expiración automática de sesión inactiva'],
			['require_two_factor_sensitive', '0', 'security', 'Doble factor para aprobación de transferencias y mermas'],
			['pos_integration_active', '1', 'integrations', 'Conexión activa con terminales POS y cajas'],
			['erp_sync_interval_mins', '15', 'integrations', 'Frecuencia de sincronización de catálogo y asientos con ERP'],
			['bi_export_encryption', 'AES-256', 'security', 'Cifrado de informes ejecutivos y exportaciones'],
			['approval_po_threshold', '5000', 'approvals', 'Monto a partir del cual una compra requiere aprobación de Gerencia'],
			['approval_waste_threshold', '100', 'approvals', 'Monto a partir del cual una merma requiere aprobación nivel Alto'],
			['approval_inventory_diff_tolerance', '0.01', 'approvals', 'Tolerancia porcentual de descuadre antes de exigir investigación obligatoria']
		];

		$stmt = $db->prepare("INSERT OR IGNORE INTO daily_system_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)");
		foreach ($defaults as $d)
		{
			$stmt->execute($d);
		}
	}

	/**
	 * Centro de Configuración General, Parámetros, Permisos e Integraciones
	 */
	public function Settings(Request $request, Response $response, array $args): Response
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		$this->EnsureSchema();

		$db = $this->Db();
		$settingsRows = $db->query("SELECT * FROM daily_system_settings ORDER BY category, setting_key")->fetchAll(\PDO::FETCH_ASSOC);
		$settingsMap = [];
		foreach ($settingsRows as $r)
		{
			$settingsMap[$r['setting_key']] = $r['setting_value'];
		}

		$branchService = BranchService::GetInstance();
		$branches = $branchService->GetBranches();

		$users = $db->query("
			SELECT u.id, u.username, u.first_name, u.last_name,
			       COUNT(p.id) AS permission_count
			FROM users u
			LEFT JOIN user_permissions p ON p.user_id = u.id
			GROUP BY u.id
			ORDER BY u.id
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Roles estándar preconfigurados
		$rolesMatrix = [
			'Manager / Administrador' => [
				'badge' => 'badge-primary',
				'desc' => 'Control total del sistema, aprobaciones financieras, parámetros y auditoría.',
				'permissions' => ['ADMIN', 'BRANCH_MANAGE', 'INVENTORY_ADJUST_APPROVE', 'WASTE_APPROVE_HIGH', 'BUDGET_MANAGE', 'WORKFORCE_SCHEDULE_MANAGE']
			],
			'Supervisor de Tienda' => [
				'badge' => 'badge-info',
				'desc' => 'Operación diaria, conteos físicos, recepción, calidad, rotación FEFO y validación de turnos.',
				'permissions' => ['STOCK', 'STOCK_INVENTORY', 'PROCUREMENT_RECEIVE', 'QUALITY_INSPECT', 'WASTE_REPORT', 'WASTE_APPROVE', 'ORDERS_MANAGE']
			],
			'Colaborador / Operador' => [
				'badge' => 'badge-secondary',
				'desc' => 'Ejecución en piso de venta, terminal POS, preparación de pedidos (picking) y fichaje.',
				'permissions' => ['SALES_POS', 'ORDERS_PICK', 'SCAN_OPERATE', 'WORKFORCE']
			]
		];

		// Matriz de flujos de aprobación
		$approvalFlows = [
			['flow' => 'Aprobación de Órdenes de Compra', 'role' => 'Manager / Compras', 'perm' => 'PERMISSION_PROCUREMENT_MANAGE', 'rule' => 'Órdenes > $5,000 requieren doble firma'],
			['flow' => 'Ajustes de Inventario Valorizado', 'role' => 'Gerencia de Operaciones', 'perm' => 'PERMISSION_INVENTORY_ADJUST_APPROVE', 'rule' => 'Sin ajuste automático: exige expediente previo'],
			['flow' => 'Autorización de Cambios y Devoluciones', 'role' => 'Supervisor de Calidad', 'perm' => 'PERMISSION_QUALITY_QUARANTINE', 'rule' => 'Bloqueo sanitario automático para perecederos'],
			['flow' => 'Aprobación de Mermas y Bajas', 'role' => 'Auditor / Gerencia', 'perm' => 'PERMISSION_WASTE_APPROVE', 'rule' => 'Evidencia fotográfica obligatoria antes de descarte'],
			['flow' => 'Modificación de Turnos Publicados', 'role' => 'Supervisor WFM', 'perm' => 'PERMISSION_WORKFORCE_SCHEDULE_MANAGE', 'rule' => 'Respeta descanso 12h y regla dominical 3x1'],
			['flow' => 'Cierre de Investigaciones de Pérdida', 'role' => 'Prevención de Pérdidas', 'perm' => 'PERMISSION_LOSS_PREVENTION', 'rule' => 'Calificación causal obligatoria (AKCAL)'],
			['flow' => 'Modificación de Políticas de Empresa', 'role' => 'Administrador Global', 'perm' => 'PERMISSION_ADMIN', 'rule' => 'Registro inmutable en bitácora de auditoría']
		];

		return $this->RenderPage($response, 'admin_settings', [
			'settings' => $settingsMap,
			'settingsRows' => $settingsRows,
			'branches' => $branches,
			'users' => $users,
			'rolesMatrix' => $rolesMatrix,
			'approvalFlows' => $approvalFlows
		]);
	}

	/**
	 * Bitácora de Auditoría Integral: Registra usuario, fecha, operación, entidad, valores anteriores y nuevos
	 */
	public function AuditLog(Request $request, Response $response, array $args): Response
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		$db = $this->Db();
		$params = $request->getQueryParams();

		$entityFilter = !empty($params['entity']) ? trim(strval($params['entity'])) : null;
		$userFilter = !empty($params['user_id']) ? intval($params['user_id']) : null;
		$actionFilter = !empty($params['action']) ? trim(strval($params['action'])) : null;
		$limit = min(200, max(20, intval($params['limit'] ?? 50)));

		$where = ["1=1"];
		$sqlParams = [];

		if (!empty($entityFilter))
		{
			$where[] = "c.table_name = :tbl";
			$sqlParams[':tbl'] = $entityFilter;
		}
		if (!empty($userFilter))
		{
			$where[] = "c.user_id = :uid";
			$sqlParams[':uid'] = $userFilter;
		}
		if (!empty($actionFilter))
		{
			$where[] = "c.action = :act";
			$sqlParams[':act'] = $actionFilter;
		}

		$whereSql = implode(" AND ", $where);

		// Consultar dairy_change_log si existe
		$logs = [];
		$tableExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='dairy_change_log'")->fetchColumn();
		if ($tableExists)
		{
			$sql = "
				SELECT c.*, IFNULL(u.username, 'Sistema / Batch') AS username
				FROM dairy_change_log c
				LEFT JOIN users u ON u.id = c.user_id
				WHERE $whereSql
				ORDER BY c.id DESC
				LIMIT $limit
			";
			$stmt = $db->prepare($sql);
			$stmt->execute($sqlParams);
			$logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

		// Eventos de dominio unificados recientes
		$domainEvents = [];
		$eventsExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='daily_domain_events'")->fetchColumn();
		if ($eventsExists)
		{
			$domainEvents = $db->query("
				SELECT e.*, IFNULL(u.username, 'Automático') AS username
				FROM daily_domain_events e
				LEFT JOIN users u ON u.id = e.user_id
				ORDER BY e.id DESC
				LIMIT 30
			")->fetchAll(\PDO::FETCH_ASSOC);
		}

		$users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll(\PDO::FETCH_ASSOC);
		$entities = ['products', 'customer_orders', 'waste_records', 'branches', 'shopping_locations', 'stock_log', 'quarantine_items'];

		return $this->RenderPage($response, 'admin_audit_log', [
			'logs' => $logs,
			'domainEvents' => $domainEvents,
			'users' => $users,
			'entities' => $entities,
			'entityFilter' => $entityFilter,
			'userFilter' => $userFilter,
			'actionFilter' => $actionFilter
		]);
	}

	private function ApiResponse(Response $response, array $data, int $status = 200): Response
	{
		$response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
		return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
	}

	/**
	 * Guardar parámetros del sistema de forma segura
	 */
	public function SaveSettings(Request $request, Response $response, array $args): Response
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		$this->EnsureSchema();
		$body = (array)$request->getParsedBody();
		$db = $this->Db();

		$userId = GROCY_USER_ID;
		$stmt = $db->prepare("
			INSERT INTO daily_system_settings (setting_key, setting_value, updated_by_user_id, updated_at)
			VALUES (:key, :val, :u, CURRENT_TIMESTAMP)
			ON CONFLICT(setting_key) DO UPDATE SET
				setting_value = excluded.setting_value,
				updated_by_user_id = excluded.updated_by_user_id,
				updated_at = CURRENT_TIMESTAMP
		");

		foreach ($body as $k => $v)
		{
			if (strpos($k, 'setting_') === 0)
			{
				$key = substr($k, 8);
				$stmt->execute([':key' => $key, ':val' => trim(strval($v)), ':u' => $userId]);
			}
		}

		return $this->ApiResponse($response, ['success' => true, 'message' => 'Configuraciones actualizadas exitosamente']);
	}

	/**
	 * Exportar Bitácora de Auditoría en formato CSV
	 */
	public function AuditLogExport(Request $request, Response $response, array $args): Response
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		$db = $this->Db();

		$rows = $db->query("
			SELECT c.id, c.changed_at, IFNULL(u.username, 'Sistema') AS user, c.table_name, c.row_id, c.action, c.old_json, c.new_json
			FROM dairy_change_log c
			LEFT JOIN users u ON u.id = c.user_id
			ORDER BY c.id DESC
			LIMIT 5000
		")->fetchAll(\PDO::FETCH_ASSOC);

		$csv = "ID,Fecha,Usuario,Entidad,Registro_ID,Operacion,Valor_Anterior,Valor_Nuevo\n";
		foreach ($rows as $r)
		{
			$csv .= sprintf(
				"%d,\"%s\",\"%s\",\"%s\",%s,\"%s\",\"%s\",\"%s\"\n",
				$r['id'],
				$r['changed_at'],
				str_replace('"', '""', $r['user']),
				$r['table_name'],
				$r['row_id'],
				$r['action'],
				str_replace('"', '""', (string)$r['old_json']),
				str_replace('"', '""', (string)$r['new_json'])
			);
		}

		$response->getBody()->write($csv);
		return $response
			->withHeader('Content-Type', 'text/csv; charset=utf-8')
			->withHeader('Content-Disposition', 'attachment; filename="daily_audit_log_' . date('Ymd_His') . '.csv"');
	}
}
