<?php

namespace Grocy\Services;

class MarkdownService extends BaseService
{
	public function GetRules(bool $onlyActive = false): array
	{
		$sql = "SELECT * FROM markdown_rules" . ($onlyActive ? " WHERE active = 1" : "") . " ORDER BY min_days ASC";
		return DatabaseService::GetInstance()->GetDbConnectionRaw()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function SaveRules(array $rules): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dbRaw->beginTransaction();
		try
		{
			$stmt = $dbRaw->prepare("UPDATE markdown_rules SET name = :name, min_days = :min, max_days = :max, discount_pct = :pct, active = :active WHERE id = :id");

			foreach ($rules as $r)
			{
				$min = intval($r['min_days'] ?? 0);
				$max = intval($r['max_days'] ?? 0);
				$pct = floatval($r['discount_pct'] ?? 0);

				if ($min < 0 || $max < $min)
				{
					throw new \Exception('Rango de días inválido en la regla "' . ($r['name'] ?? '') . '"');
				}
				if ($pct <= 0 || $pct > 90)
				{
					throw new \Exception('El % de rebaja debe estar entre 1 y 90');
				}

				$stmt->execute([
					':id' => intval($r['id']),
					':name' => trim($r['name'] ?? 'Regla'),
					':min' => $min,
					':max' => $max,
					':pct' => $pct,
					':active' => !empty($r['active']) ? 1 : 0
				]);
			}

			$active = $this->GetRules(true);
			for ($i = 1; $i < count($active); $i++)
			{
				if ($active[$i]['min_days'] <= $active[$i - 1]['max_days'])
				{
					throw new \Exception('Las reglas activas se superponen: "' . $active[$i - 1]['name'] . '" y "' . $active[$i]['name'] . '"');
				}
			}
			$dbRaw->commit();
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}

		return $this->GetRules();
	}

	// Productos con rebaja vigente según el lote FEFO (vencimiento más próximo, no vencido)
	public function GetActiveMarkdowns(): array
	{
		$rules = $this->GetRules(true);
		if (empty($rules))
		{
			return [];
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$rows = $dbRaw->query("
			SELECT
				s.product_id,
				p.name AS product_name,
				MIN(s.best_before_date) AS best_before_date,
				IFNULL(NULLIF(p.sale_price, 0), ROUND(NULLIF(plp.price, 0) * (1 + " . floatval(GROCY_SALE_PRICE_DEFAULT_MARKUP_PCT) . " / 100.0), 2)) AS list_price,
				IFNULL(plp.price, 0) AS unit_cost
			FROM stock s
			JOIN products p ON s.product_id = p.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			WHERE s.amount > 0
				AND s.best_before_date IS NOT NULL
				AND s.best_before_date < '2999-12-31'
				AND date(s.best_before_date) >= date('now', 'localtime')
				AND p.active = 1
			GROUP BY s.product_id
		")->fetchAll(\PDO::FETCH_ASSOC);

		$lotStmt = $dbRaw->prepare("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :pid AND best_before_date = :bbd AND amount > 0");

		$result = [];
		foreach ($rows as $row)
		{
			$days = intval((strtotime($row['best_before_date']) - strtotime(date('Y-m-d'))) / 86400);
			foreach ($rules as $rule)
			{
				if ($days >= $rule['min_days'] && $days <= $rule['max_days'])
				{
					$lotStmt->execute([':pid' => $row['product_id'], ':bbd' => $row['best_before_date']]);
					$listPrice = floatval($row['list_price']);
					$pct = floatval($rule['discount_pct']);
					$result[intval($row['product_id'])] = [
						'product_id' => intval($row['product_id']),
						'product_name' => $row['product_name'],
						'best_before_date' => $row['best_before_date'],
						'days_left' => $days,
						'lot_amount' => floatval($lotStmt->fetchColumn()),
						'rule_name' => $rule['name'],
						'discount_pct' => $pct,
						'list_price' => $listPrice,
						'markdown_price' => round($listPrice * (1 - $pct / 100), 2),
						'unit_cost' => floatval($row['unit_cost'])
					];
					break;
				}
			}
		}

		uasort($result, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
		return $result;
	}

	public function GetMaxDiscountPct(int $productId): float
	{
		$active = $this->GetActiveMarkdowns();
		return isset($active[$productId]) ? $active[$productId]['discount_pct'] : 0.0;
	}
}
