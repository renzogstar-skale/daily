<?php

namespace Grocy\Services;

class BranchService extends BaseService
{
	private static ?array $namesById = null;

	// Nombre vigente de una sucursal según el catálogo único `branches`
	public static function NameById(int $id): string
	{
		self::$namesById ??= DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM branches')->fetchAll(\PDO::FETCH_KEY_PAIR);
		return self::$namesById[$id] ?? 'Sin sucursal';
	}

	// Los escenarios demo calibrados marcan sus sucursales como [[B<id>]]; se resuelven con el catálogo único
	public static function FillBranchPlaceholders($data)
	{
		if (is_string($data))
		{
			return strpos($data, '[[B') === false ? $data : preg_replace_callback('/\[\[B(\d+)\]\]/', fn($m) => self::NameById(intval($m[1])), $data);
		}
		if (is_array($data))
		{
			foreach ($data as $k => $v)
			{
				$data[$k] = self::FillBranchPlaceholders($v);
			}
		}
		return $data;
	}

	public function GetBranches(bool $activeOnly = true)
	{
		$query = $this->DB->branches();
		if ($activeOnly)
		{
			$query = $query->where('active', 1);
		}
		return $query->orderBy('id')->fetchAll();
	}

	public function GetBranch(int $id)
	{
		return $this->DB->branches($id);
	}

	public function CreateBranch(string $name, string $code, ?string $address = null, ?string $phone = null)
	{
		$existing = $this->DB->branches()->where('code', $code)->fetch();
		if ($existing)
		{
			throw new \Exception("Ya existe una sucursal con el código '$code'");
		}

		$row = $this->DB->branches()->createRow([
			'name' => $name,
			'code' => $code,
			'address' => $address,
			'phone' => $phone,
			'active' => 1
		]);
		return $row->save();
	}

	public function EditBranch(int $id, string $name, string $code, ?string $address = null, ?string $phone = null, int $active = 1)
	{
		$branch = $this->DB->branches($id);
		if (!$branch)
		{
			throw new \Exception("Sucursal no encontrada");
		}

		$existing = $this->DB->branches()->where('code = :1 AND id != :2', $code, $id)->fetch();
		if ($existing)
		{
			throw new \Exception("Ya existe otra sucursal con el código '$code'");
		}

		$branch->update([
			'name' => $name,
			'code' => $code,
			'address' => $address,
			'phone' => $phone,
			'active' => $active
		]);
		return $branch;
	}

	public function GetBranchStock(int $branchId)
	{
		$sql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				qu.name AS qu_name,
				l.id AS location_id,
				l.name AS location_name,
				SUM(s.amount) AS total_amount,
				AVG(s.price) AS avg_price,
				SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) AS total_value
			FROM stock s
			JOIN products p ON s.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			JOIN locations l ON s.location_id = l.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			WHERE l.branch_id = :branch_id
			GROUP BY p.id, p.name, qu.name, l.id, l.name
			ORDER BY p.name ASC
		";
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':branch_id' => $branchId]);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	// Scorecard comparativo por sucursal: ventas, margen, merma y riesgo de vencimiento en el período
	public function GetBranchScorecards(string $startDate, string $endDate): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT
				b.id,
				b.name,
				b.code,
				(SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :s1 AND :e1) AS tickets,
				(SELECT IFNULL(SUM(s.total_amount), 0) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :s2 AND :e2) AS net_sales,
				(SELECT IFNULL(SUM(s.tax_amount), 0) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :s5 AND :e5) AS tax_amount,
				(SELECT IFNULL(SUM(s.gross_margin), 0) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :s3 AND :e3) AS gross_margin,
				(SELECT IFNULL(SUM(w.total_cost), 0) FROM waste_records w WHERE w.branch_id = b.id AND w.status = 'approved' AND date(w.created_timestamp) BETWEEN :s4 AND :e4) AS waste_cost,
				(SELECT COUNT(*) FROM waste_records w WHERE w.branch_id = b.id AND IFNULL(w.workflow_status, '') NOT IN ('closed', 'executed')) AS open_cases,
				(SELECT IFNULL(SUM(st.amount * IFNULL(st.price, 0)), 0) FROM stock st JOIN locations l ON st.location_id = l.id WHERE l.branch_id = b.id) AS stock_value,
				(SELECT IFNULL(SUM(st.amount * IFNULL(st.price, 0)), 0) FROM stock st JOIN locations l ON st.location_id = l.id
					WHERE l.branch_id = b.id AND st.best_before_date < '2999-12-31' AND julianday(st.best_before_date) - julianday(date('now', 'localtime')) BETWEEN 0 AND 7) AS expiring_value
			FROM branches b
			WHERE b.active = 1
			ORDER BY b.id
		");
		$params = [];
		for ($i = 1; $i <= 5; $i++)
		{
			$params[":s$i"] = $startDate;
			$params[":e$i"] = $endDate;
		}
		$stmt->execute($params);

		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$maxSales = max(array_column($rows, 'net_sales') ?: [0]);
		foreach ($rows as &$r)
		{
			$sales = floatval($r['net_sales']);
			$r['margin_pct'] = SalesService::MarginPct(floatval($r['gross_margin']), $sales - floatval($r['tax_amount'])) ?? 0;
			$r['waste_pct'] = $sales > 0 ? round($r['waste_cost'] / $sales * 100, 2) : 0;
			$r['avg_ticket'] = $r['tickets'] > 0 ? round($sales / $r['tickets'], 2) : 0;
			$r['sales_share'] = $maxSales > 0 ? round($sales / $maxSales * 100) : 0;
			// Semáforo de merma sobre ventas frente a la referencia sectorial (1.51 %) y tope corporativo (2.5 %)
			// Sin ventas en el período el indicador no existe (no es un cero real)
			$r['has_data'] = $sales > 0;
			$r['status'] = !$r['has_data'] ? 'nodata' : ($r['waste_pct'] <= 1.51 ? 'success' : ($r['waste_pct'] <= 2.5 ? 'warning' : 'danger'));
		}

		return $rows;
	}

	// Acceso por sucursal: usuarios con sus sucursales asignadas (vacío = toda la red)
	public function GetUserBranchAccess(): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$users = $db->query("SELECT u.id, u.username, TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')) AS display,
			EXISTS (SELECT 1 FROM user_permissions_resolved up WHERE up.user_id = u.id AND up.permission_name = 'ADMIN') AS is_admin
			FROM users u ORDER BY u.username")->fetchAll(\PDO::FETCH_ASSOC);
		$map = [];
		foreach ($db->query('SELECT user_id, branch_id FROM user_branches')->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$map[$r['user_id']][] = intval($r['branch_id']);
		}
		foreach ($users as &$u)
		{
			$u['branch_ids'] = $map[$u['id']] ?? [];
		}
		return $users;
	}

	public function SetUserBranches(int $userId, array $branchIds): void
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		if (!$db->query('SELECT 1 FROM users WHERE id = ' . $userId)->fetchColumn())
		{
			throw new \Exception('Usuario inexistente');
		}
		$valid = array_map('intval', $db->query('SELECT id FROM branches')->fetchAll(\PDO::FETCH_COLUMN));
		$ids = array_values(array_unique(array_map('intval', $branchIds)));
		if (array_diff($ids, $valid))
		{
			throw new \Exception('Sucursal inexistente');
		}
		$db->beginTransaction();
		try
		{
			$db->prepare('DELETE FROM user_branches WHERE user_id = :u')->execute([':u' => $userId]);
			$ins = $db->prepare('INSERT INTO user_branches (user_id, branch_id) VALUES (:u, :b)');
			foreach ($ids as $b)
			{
				$ins->execute([':u' => $userId, ':b' => $b]);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}
}
