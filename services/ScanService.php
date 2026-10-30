<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Operaciones con escáner (Etapa 21). Independiente del lector: cámara, escáner tipo teclado (HID) o SDK industrial
 * entregan el mismo texto y el servidor lo interpreta.
 *  - ParseCode: GS1 (paréntesis, FNC1/GS, identificadores ]C1 ]d2 ]Q3 ]e0), etiquetas de ubicación LOC-…, EAN/UPC planos.
 *  - Resolve: producto / ubicación / lote con disponibilidad (físico, cuarentena, vencido, comprometido, disponible).
 *  - Sesiones: conteo guiado (→ conteos cíclicos y ajuste aprobado), movimiento entre ubicaciones de la sucursal y
 *    recepción contra OC (→ MrpService::ReceiveProcurementOrder). Cada lectura lleva client_uuid (idempotente, apta
 *    para cola sin conexión), dispositivo, usuario y hora; series duplicadas se rechazan y el rebote del gatillo se ignora.
 */
class ScanService extends BaseService
{
	// AI GS1 de longitud fija (sin separador); el resto es variable y termina en GS o fin de cadena
	const GS1_FIXED = ['00' => 18, '01' => 14, '02' => 14, '11' => 6, '12' => 6, '13' => 6, '15' => 6, '16' => 6, '17' => 6, '20' => 2];
	const GS1_VARIABLE = ['10' => 20, '21' => 20, '22' => 20, '30' => 8, '37' => 8, '240' => 30, '241' => 30, '250' => 30, '400' => 30, '90' => 30];
	const TYPES = ['count' => 'Conteo guiado', 'move' => 'Movimiento entre ubicaciones', 'receive' => 'Recepción contra OC', 'pick' => 'Preparación de pedido'];
	const STORAGE_LABEL = ['ambient' => 'ambiente', 'chilled' => 'refrigerado', 'frozen' => 'congelado'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		$r = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $r ?: null;
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/* ---------------------------------------------------------------- Interpretación del código */

	public function ParseCode(string $raw): array
	{
		$code = trim(str_replace(["\r", "\n"], '', $raw));
		$out = ['raw' => $raw, 'format' => 'plain', 'code' => $code, 'gtin' => null, 'lot' => null, 'serial' => null, 'best_before' => null, 'amount' => null, 'sscc' => null, 'location_code' => null];
		if ($code === '')
		{
			throw new \Exception('Código vacío');
		}

		$gs1 = false;
		if (preg_match('/^\](C1|d2|Q3|e0)/', $code))
		{
			$gs1 = true;
			$code = substr($code, 3);
		}
		elseif (preg_match('/^\][A-Za-z][0-9]/', $code))
		{
			$code = substr($code, 3); // otro identificador de simbología (EAN ]E0, Code128 ]C0…)
		}
		$code = ltrim($code, "\x1D");

		if (preg_match('/^LOC-[A-Z0-9\-]+$/i', $code))
		{
			$out['format'] = 'location';
			$out['location_code'] = strtoupper($code);
			$out['code'] = $out['location_code'];
			return $out;
		}

		if (str_starts_with($code, '('))
		{
			$ais = $this->ParseGs1Parenthesis($code);
		}
		elseif ($gs1 || str_contains($code, "\x1D") || (ctype_digit(substr($code, 0, 16)) && strlen($code) >= 16 && in_array(substr($code, 0, 2), ['00', '01', '02'])))
		{
			$ais = $this->ParseGs1Raw($code);
		}
		else
		{
			$ais = null;
		}

		if ($ais !== null)
		{
			$out['format'] = 'gs1';
			$out['ais'] = $ais;
			$out['gtin'] = $ais['01'] ?? $ais['02'] ?? null;
			$out['sscc'] = $ais['00'] ?? null;
			$out['lot'] = $ais['10'] ?? null;
			$out['serial'] = $ais['21'] ?? null;
			$date = $ais['17'] ?? $ais['15'] ?? $ais['16'] ?? null;
			$out['best_before'] = $date !== null ? $this->Gs1Date($date) : null;
			if (isset($ais['30']) || isset($ais['37']))
			{
				$out['amount'] = floatval($ais['30'] ?? $ais['37']);
			}
			if ($out['gtin'] !== null && !$this->ValidCheckDigit($out['gtin']))
			{
				throw new \Exception('GTIN ' . $out['gtin'] . ' con dígito verificador inválido: vuelva a escanear');
			}
			$out['code'] = $out['gtin'] ?? $code;
		}
		return $out;
	}

	private function ParseGs1Parenthesis(string $code): array
	{
		if (!preg_match_all('/\((\d{2,4})\)([^(]*)/', $code, $m, PREG_SET_ORDER))
		{
			throw new \Exception('Código GS1 mal formado');
		}
		$ais = [];
		foreach ($m as $part)
		{
			$ais[$part[1]] = trim($part[2]);
		}
		return $ais;
	}

	private function ParseGs1Raw(string $code): array
	{
		$ais = [];
		$i = 0;
		$len = strlen($code);
		while ($i < $len)
		{
			if ($code[$i] === "\x1D")
			{
				$i++;
				continue;
			}
			$ai = null;
			foreach ([2, 3, 4] as $n)
			{
				$cand = substr($code, $i, $n);
				if (isset(self::GS1_FIXED[$cand]) || isset(self::GS1_VARIABLE[$cand]))
				{
					$ai = $cand;
					break;
				}
				if ($n === 4 && preg_match('/^3[1-6]\d\d$/', $cand))
				{
					$ai = $cand; // medidas 31nn–36nn: 6 dígitos con decimales implícitos
				}
			}
			if ($ai === null)
			{
				throw new \Exception('Identificador GS1 no soportado en la posición ' . ($i + 1) . ': ' . substr($code, $i, 4));
			}
			$i += strlen($ai);
			if (isset(self::GS1_FIXED[$ai]) || preg_match('/^3[1-6]\d\d$/', $ai))
			{
				$n = self::GS1_FIXED[$ai] ?? 6;
				$value = substr($code, $i, $n);
				if (strlen($value) !== $n)
				{
					throw new \Exception("Código GS1 incompleto en AI ($ai)");
				}
				$i += $n;
				if (preg_match('/^3[1-6](\d)(\d)$/', $ai, $mm))
				{
					$value = (string) (intval($value) / (10 ** intval($mm[2])));
				}
			}
			else
			{
				$end = strpos($code, "\x1D", $i);
				$end = $end === false ? $len : $end;
				$value = substr($code, $i, min($end - $i, self::GS1_VARIABLE[$ai]));
				$i += strlen($value);
			}
			$ais[$ai] = $value;
		}
		return $ais;
	}

	private function Gs1Date(string $yymmdd): string
	{
		if (!preg_match('/^(\d{2})(\d{2})(\d{2})$/', $yymmdd, $m) || intval($m[2]) < 1 || intval($m[2]) > 12)
		{
			throw new \Exception("Fecha GS1 inválida: $yymmdd");
		}
		$y = 2000 + intval($m[1]);
		$d = intval($m[3]) === 0 ? intval(date('t', mktime(0, 0, 0, intval($m[2]), 1, $y))) : intval($m[3]); // DD=00 → fin de mes
		if (!checkdate(intval($m[2]), $d, $y))
		{
			throw new \Exception("Fecha GS1 inválida: $yymmdd");
		}
		return sprintf('%04d-%02d-%02d', $y, intval($m[2]), $d);
	}

	public function ValidCheckDigit(string $digits): bool
	{
		if (!ctype_digit($digits) || !in_array(strlen($digits), [8, 12, 13, 14, 18]))
		{
			return false;
		}
		$sum = 0;
		$body = substr($digits, 0, -1);
		$n = strlen($body);
		for ($k = 0; $k < $n; $k++)
		{
			$sum += intval($body[$n - 1 - $k]) * ($k % 2 === 0 ? 3 : 1);
		}
		return (10 - $sum % 10) % 10 === intval(substr($digits, -1));
	}

	/* ---------------------------------------------------------------- Resolución */

	public function FindProductByCode(string $code): ?array
	{
		$cands = [$code];
		if (ctype_digit($code))
		{
			$trim = ltrim($code, '0');
			foreach ([14, 13, 12, 8] as $len)
			{
				if (strlen($trim) <= $len)
				{
					$cands[] = str_pad($trim, $len, '0', STR_PAD_LEFT);
				}
			}
		}
		$cands = array_values(array_unique($cands));
		$in = implode(',', array_fill(0, count($cands), '?'));
		$row = $this->Row("SELECT p.id, p.name, pb.barcode, pb.amount AS barcode_amount FROM product_barcodes pb JOIN products p ON p.id = pb.product_id
			WHERE pb.barcode IN ($in) AND p.active = 1 ORDER BY p.id LIMIT 1", $cands);
		if ($row)
		{
			return $row;
		}
		$sku = $this->Row('SELECT id, name, NULL AS barcode, NULL AS barcode_amount FROM products WHERE active = 1 AND sku = :c LIMIT 1', [':c' => $code]);
		if ($sku)
		{
			return $sku;
		}
		// SKU interno «P<id>» de las etiquetas DAIRY
		if (preg_match('/^P(\d+)$/i', $code, $m))
		{
			return $this->Row('SELECT id, name, NULL AS barcode, NULL AS barcode_amount FROM products WHERE id = :id AND active = 1', [':id' => intval($m[1])]);
		}
		return null;
	}

	public function Resolve(string $raw, ?int $branchId): array
	{
		$parsed = $this->ParseCode($raw);
		if ($parsed['format'] === 'location')
		{
			$loc = $this->Row('SELECT l.*, b.name AS branch_name FROM locations l LEFT JOIN branches b ON b.id = l.branch_id WHERE upper(l.label_code) = :c', [':c' => $parsed['location_code']]);
			if (!$loc)
			{
				throw new \Exception("Ubicación {$parsed['location_code']} no registrada");
			}
			if ($loc['branch_id'] && !DairyAccess::CanSeeBranch(intval($loc['branch_id'])))
			{
				throw new \Exception('No tiene acceso a la sucursal de esta ubicación');
			}
			$stock = $this->Rows('SELECT p.id AS product_id, p.name, SUM(s.amount) AS amount, MIN(s.best_before_date) AS next_expiry, SUM(s.amount * IFNULL(s.price, 0)) AS value
				FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = :l GROUP BY p.id ORDER BY p.name', [':l' => $loc['id']]);
			return ['kind' => 'location', 'parsed' => $parsed, 'location' => $this->LocationView($loc), 'stock' => $stock];
		}

		$product = $this->FindProductByCode($parsed['code']);
		$lotMatches = [];
		if (!$product && $parsed['format'] === 'plain')
		{
			// Búsqueda por lote o serie registrada
			$lotMatches = $this->Rows('SELECT dl.*, p.name FROM dairy_stock_lots dl JOIN products p ON p.id = dl.product_id WHERE dl.lot_number = :c OR dl.serial_number = :c LIMIT 20', [':c' => $parsed['code']]);
			if (count($lotMatches) > 0)
			{
				$product = ['id' => $lotMatches[0]['product_id'], 'name' => $lotMatches[0]['name']];
				$parsed['lot'] = $lotMatches[0]['lot_number'];
			}
		}
		if (!$product)
		{
			$similar = $this->Rows('SELECT id, name FROM products WHERE active = 1 AND name LIKE :q ORDER BY name LIMIT 8', [':q' => '%' . $parsed['code'] . '%']);
			return ['kind' => 'unknown', 'parsed' => $parsed, 'candidates' => $similar, 'message' => 'Código no asociado a ningún producto' . (count($similar) ? '; coincidencias por nombre' : '')];
		}

		$pid = intval($product['id']);
		$branchFilter = $branchId ? ' AND l.branch_id = :b' : '';
		$params = $branchId ? [':p' => $pid, ':b' => $branchId] : [':p' => $pid];
		$entries = $this->Rows("SELECT s.stock_id, s.amount, s.best_before_date, s.price, s.location_id, l.name AS location_name, l.label_code, dl.lot_number, dl.serial_number
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE s.product_id = :p AND IFNULL(l.is_transit, 0) = 0 $branchFilter ORDER BY s.best_before_date, s.purchased_date", $params);
		if ($parsed['lot'])
		{
			foreach ($entries as &$e)
			{
				$e['matches_lot'] = $e['lot_number'] === $parsed['lot'];
			}
			unset($e);
		}
		return [
			'kind' => 'product',
			'parsed' => $parsed,
			'product' => $this->Row('SELECT p.id, p.name, p.sku, p.picture_file_name, p.brand, p.storage_class, p.min_stock_amount, p.default_best_before_days, qu.name AS unit, pg.name AS group_name FROM products p
				LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock LEFT JOIN product_groups pg ON pg.id = p.product_group_id WHERE p.id = :id', [':id' => $pid]),
			'availability' => $this->Availability($pid, $branchId),
			'entries' => $entries,
			'lots' => $lotMatches,
		];
	}

	private function LocationView(array $loc): array
	{
		$path = array_values(array_filter([$loc['branch_name'] ?? null, $loc['warehouse'] ?? null, $loc['zone'] ?? null, $loc['aisle'] ? 'Pasillo ' . $loc['aisle'] : null, $loc['shelf'] ? 'Estante ' . $loc['shelf'] : null, $loc['bin'] ? 'Posición ' . $loc['bin'] : null]));
		return ['id' => intval($loc['id']), 'name' => $loc['name'], 'label_code' => $loc['label_code'], 'branch_id' => $loc['branch_id'] ? intval($loc['branch_id']) : null,
			'path' => $path, 'storage_class' => $loc['storage_class'], 'capacity_units' => $loc['capacity_units'] !== null && $loc['capacity_units'] !== '' ? floatval($loc['capacity_units']) : null];
	}

	/**
	 * Stock por estado. Disponible = físico − cuarentena − vencido − merma pendiente de aprobación (aún sin descontar).
	 * Reservado: DAIRY no tiene reservas de clientes; se informa null (no 0) para no afirmar un dato inexistente.
	 */
	public function Availability(int $productId, ?int $branchId): array
	{
		$bl = $branchId ? ' AND l.branch_id = :b' : '';
		$p = $branchId ? [':p' => $productId, ':b' => $branchId] : [':p' => $productId];
		$today = date('Y-m-d');
		$physical = floatval($this->Row("SELECT IFNULL(SUM(s.amount), 0) AS v FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND IFNULL(l.is_transit, 0) = 0 $bl", $p)['v']);
		$expired = floatval($this->Row("SELECT IFNULL(SUM(s.amount), 0) AS v FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date < '$today' AND s.best_before_date <> '2999-12-31' $bl", $p)['v']);
		$quarantine = floatval($this->Row('SELECT IFNULL(SUM(q.amount), 0) AS v FROM quarantine_items q WHERE q.product_id = :p AND q.status = \'in_quarantine\'' . ($branchId ? ' AND q.branch_id = :b' : ''), $p)['v']);
		$committed = floatval($this->Row('SELECT IFNULL(SUM(w.amount), 0) AS v FROM waste_records w WHERE w.product_id = :p AND w.status = \'pending_approval\' AND IFNULL(w.inventory_deducted, 0) = 0' . ($branchId ? ' AND w.branch_id = :b' : ''), $p)['v']);
		$transit = floatval($this->Row('SELECT IFNULL(SUM(s.amount), 0) AS v FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.is_transit = 1', [':p' => $productId])['v']);
		$reserved = CustomerOrderService::GetInstance()->Reserved($productId, $branchId);
		$lastCount = $this->Row("SELECT executed_date, theoretical_stock, counted_stock, difference_amount, status FROM stock_cycle_counts WHERE product_id = :p AND status <> 'pending'" . ($branchId ? ' AND branch_id = :b' : '') . ' ORDER BY executed_date DESC, id DESC LIMIT 1', $p);
		return [
			'physical' => $physical,
			'quarantine' => $quarantine,
			'expired' => $expired,
			'committed_waste' => $committed,
			'reserved' => $reserved,
			'in_transit_network' => $transit,
			'available' => max(0, round($physical - $quarantine - $expired - $committed - $reserved, 4)),
			'last_count' => $lastCount,
		];
	}

	/* ---------------------------------------------------------------- Sesiones */

	public function OpenSession(array $data, int $userId): array
	{
		$type = $data['type'] ?? '';
		if (!isset(self::TYPES[$type]))
		{
			throw new \Exception('Tipo de operación inválido');
		}
		$branchId = intval($data['branch_id'] ?? 0);
		if ($branchId <= 0 || !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('Seleccione una sucursal a la que tenga acceso');
		}
		$locationId = !empty($data['location_id']) ? intval($data['location_id']) : null;
		$targetId = !empty($data['target_location_id']) ? intval($data['target_location_id']) : null;
		$orderId = !empty($data['order_id']) ? intval($data['order_id']) : null;
		$customerOrderId = $type === 'pick' && !empty($data['customer_order_id']) ? intval($data['customer_order_id']) : null;

		if (!in_array($type, ['receive', 'pick']) || $locationId)
		{
			$this->AssertBranchLocation($locationId, $branchId, $type === 'receive' ? 'Ubicación de recepción' : 'Ubicación');
		}
		if ($type === 'move')
		{
			$this->AssertBranchLocation($targetId, $branchId, 'Ubicación de destino');
			if ($targetId === $locationId)
			{
				throw new \Exception('Origen y destino deben ser distintos');
			}
		}
		if ($type === 'receive')
		{
			$order = $this->Row('SELECT * FROM procurement_orders WHERE id = :id', [':id' => $orderId]);
			if (!$order || intval($order['branch_id']) !== $branchId)
			{
				throw new \Exception('Seleccione una orden de compra de esta sucursal');
			}
			if (!in_array($order['status'], ['approved', 'sent', 'partially_received']))
			{
				throw new \Exception("La orden está en estado {$order['status']} y no admite recepción");
			}
		}
		if ($type === 'pick')
		{
			$co = $customerOrderId ? $this->Row('SELECT * FROM customer_orders WHERE id = :id', [':id' => $customerOrderId]) : null;
			if (!$co || intval($co['branch_id']) !== $branchId)
			{
				throw new \Exception('Seleccione un pedido de cliente de esta sucursal');
			}
			if (!in_array($co['status'], ['confirmed', 'picking']))
			{
				throw new \Exception('El pedido debe estar confirmado para prepararlo');
			}
		}
		$open = $this->Row("SELECT id, folio FROM dairy_scan_sessions WHERE status = 'open' AND user_id = :u AND type = :t AND branch_id = :b AND IFNULL(location_id, 0) = CAST(:l AS INTEGER) AND IFNULL(order_id, 0) = CAST(:o AS INTEGER) AND IFNULL(customer_order_id, 0) = CAST(:co AS INTEGER)",
			[':u' => $userId, ':t' => $type, ':b' => $branchId, ':l' => intval($locationId), ':o' => intval($orderId), ':co' => intval($customerOrderId)]);
		if ($open)
		{
			return $this->GetSession(intval($open['id'])) + ['resumed' => true];
		}

		$db = $this->Db();
		$year = date('Y');
		$seq = intval($db->query("SELECT COUNT(*) + 1 FROM dairy_scan_sessions WHERE folio LIKE 'SCN-$year-%'")->fetchColumn());
		$db->prepare('INSERT INTO dairy_scan_sessions (folio, type, branch_id, location_id, target_location_id, order_id, customer_order_id, device_id, user_id, notes, created_at)
			VALUES (:f, :t, :b, :l, :tl, :o, :co, :d, :u, :n, :c)')->execute([
			':f' => sprintf('SCN-%s-%05d', $year, $seq), ':t' => $type, ':b' => $branchId, ':l' => $locationId, ':tl' => $targetId, ':o' => $orderId, ':co' => $customerOrderId,
			':d' => mb_substr((string) ($data['device_id'] ?? ''), 0, 80) ?: null, ':u' => $userId, ':n' => trim((string) ($data['notes'] ?? '')) ?: null, ':c' => date('Y-m-d H:i:s')
		]);
		$newId = intval($db->lastInsertId());
		if ($customerOrderId)
		{
			CustomerOrderService::GetInstance()->MarkPicking($customerOrderId);
		}
		return $this->GetSession($newId);
	}

	private function AssertBranchLocation(?int $locationId, int $branchId, string $label): void
	{
		$loc = $locationId ? $this->Row('SELECT id, branch_id, is_transit FROM locations WHERE id = :id AND active = 1', [':id' => $locationId]) : null;
		if (!$loc || intval($loc['branch_id']) !== $branchId || intval($loc['is_transit']) === 1)
		{
			throw new \Exception("$label: seleccione una ubicación activa de la sucursal");
		}
	}

	private function Session(int $id, bool $mustBeOpen = true): array
	{
		$s = $this->Row('SELECT * FROM dairy_scan_sessions WHERE id = :id', [':id' => $id]);
		if (!$s)
		{
			throw new \Exception('Sesión de escaneo no encontrada');
		}
		if (!DairyAccess::CanSeeBranch(intval($s['branch_id'])))
		{
			throw new \Exception('No tiene acceso a la sucursal de esta sesión');
		}
		if ($mustBeOpen && $s['status'] !== 'open')
		{
			throw new \Exception("La sesión {$s['folio']} ya está " . ($s['status'] === 'closed' ? 'cerrada' : 'anulada'));
		}
		return $s;
	}

	public function GetSession(int $id): array
	{
		$s = $this->Session($id, false);
		$lines = $this->Rows('SELECT sl.*, p.name AS product_name FROM dairy_scan_lines sl JOIN products p ON p.id = sl.product_id WHERE sl.session_id = :s ORDER BY sl.id DESC', [':s' => $id]);
		$totals = [];
		foreach ($lines as $l)
		{
			if ($l['voided_at'])
			{
				continue;
			}
			$k = $l['product_id'];
			$totals[$k] ??= ['product_id' => intval($k), 'product_name' => $l['product_name'], 'amount' => 0, 'scans' => 0];
			$totals[$k]['amount'] += floatval($l['amount']);
			$totals[$k]['scans']++;
		}
		$expected = [];
		$blind = !defined('GROCY_CYCLE_COUNT_BLIND') || GROCY_CYCLE_COUNT_BLIND;
		if ($s['type'] === 'count' || $s['type'] === 'move')
		{
			$expected = $this->Rows('SELECT p.id AS product_id, p.name AS product_name, SUM(s.amount) AS system_amount FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = :l GROUP BY p.id ORDER BY p.name', [':l' => $s['location_id']]);
			if ($s['type'] === 'count' && $blind && $s['status'] === 'open')
			{
				foreach ($expected as &$e)
				{
					$e['system_amount'] = null; // conteo ciego: el sistema no revela su cantidad hasta cerrar
				}
				unset($e);
			}
		}
		elseif ($s['type'] === 'pick')
		{
			// Picking guiado: pendiente por línea y lotes sugeridos en orden FEFO (ubicación y vencimiento)
			$expected = array_map(fn($i) => ['product_id' => $i['product_id'], 'product_name' => $i['product_name'], 'ordered' => $i['amount'], 'picked' => $i['picked_amount'], 'pending' => $i['pending'], 'suggested' => $i['suggested']],
				CustomerOrderService::GetInstance()->Get(intval($s['customer_order_id']))['items']);
		}
		elseif ($s['type'] === 'receive')
		{
			$expected = $this->Rows('SELECT oi.id AS order_item_id, oi.product_id, p.name AS product_name, oi.approved_amount AS ordered, IFNULL(oi.received_amount, 0) AS received, IFNULL(oi.rejected_amount, 0) AS rejected,
				IFNULL(oi.approved_amount, 0) - IFNULL(oi.received_amount, 0) - IFNULL(oi.rejected_amount, 0) AS pending FROM procurement_order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :o', [':o' => $s['order_id']]);
		}
		$names = $this->Row('SELECT b.name AS branch_name, l.name AS location_name, t.name AS target_name, o.order_code AS order_number, co.order_code AS customer_order_code FROM dairy_scan_sessions s LEFT JOIN branches b ON b.id = s.branch_id LEFT JOIN customer_orders co ON co.id = s.customer_order_id
			LEFT JOIN locations l ON l.id = s.location_id LEFT JOIN locations t ON t.id = s.target_location_id LEFT JOIN procurement_orders o ON o.id = s.order_id WHERE s.id = :id', [':id' => $id]);
		return ['session' => $s + ($names ?? []) + ['type_label' => self::TYPES[$s['type']], 'blind' => $blind && $s['type'] === 'count'], 'lines' => $lines,
			'totals' => array_values($totals), 'expected' => $expected, 'result' => $s['result_json'] ? json_decode($s['result_json'], true) : null];
	}

	public function ListSessions(?int $branchId, int $userId, int $limit = 30): array
	{
		$where = $branchId ? 'WHERE s.branch_id = :b' : 'WHERE 1 = 1';
		$p = $branchId ? [':b' => $branchId] : [];
		$rows = $this->Rows("SELECT s.id, s.folio, s.type, s.status, s.branch_id, s.created_at, s.closed_at, s.user_id, s.device_id, b.name AS branch_name, l.name AS location_name,
			(SELECT COUNT(*) FROM dairy_scan_lines sl WHERE sl.session_id = s.id AND sl.voided_at IS NULL) AS scans
			FROM dairy_scan_sessions s LEFT JOIN branches b ON b.id = s.branch_id LEFT JOIN locations l ON l.id = s.location_id $where ORDER BY s.id DESC LIMIT " . intval($limit), $p);
		return array_values(array_filter($rows, fn($r) => DairyAccess::CanSeeBranch(intval($r['branch_id']))));
	}

	public function AddLine(int $sessionId, array $data, int $userId): array
	{
		$uuid = trim((string) ($data['client_uuid'] ?? ''));
		if (!preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $uuid))
		{
			throw new \Exception('Lectura sin identificador de cliente (client_uuid)');
		}
		$existing = $this->Row('SELECT * FROM dairy_scan_lines WHERE client_uuid = :u', [':u' => $uuid]);
		if ($existing)
		{
			if (intval($existing['session_id']) !== $sessionId)
			{
				throw new \Exception('El identificador de lectura ya pertenece a otra sesión');
			}
			return ['line' => $existing, 'duplicate' => true, 'message' => 'Lectura ya registrada (reintento)'] + $this->GetSession($sessionId);
		}
		$s = $this->Session($sessionId);

		$raw = (string) ($data['raw_code'] ?? '');
		$parsed = $raw !== '' ? $this->ParseCode($raw) : ['format' => 'manual', 'lot' => null, 'serial' => null, 'best_before' => null, 'amount' => null, 'code' => null];
		if ($parsed['format'] === 'location')
		{
			throw new \Exception('Escaneó una etiqueta de ubicación; escanee el producto');
		}
		$productId = !empty($data['product_id']) ? intval($data['product_id']) : null;
		$barcodeAmount = null;
		if ($productId === null)
		{
			$found = $parsed['code'] ? $this->FindProductByCode($parsed['code']) : null;
			if (!$found)
			{
				throw new \Exception('Código ' . ($parsed['code'] ?? $raw) . ' no asociado a ningún producto');
			}
			$productId = intval($found['id']);
			$barcodeAmount = $found['barcode_amount'] !== null && $found['barcode_amount'] !== '' ? floatval($found['barcode_amount']) : null;
		}
		elseif (!$this->Row('SELECT id FROM products WHERE id = :id AND active = 1', [':id' => $productId]))
		{
			throw new \Exception('Producto inválido o inactivo');
		}

		$amount = isset($data['amount']) && $data['amount'] !== '' ? floatval($data['amount']) : ($parsed['amount'] ?? ($barcodeAmount ?: 1));
		if (!is_finite($amount) || $amount <= 0 || $amount > 100000)
		{
			throw new \Exception('Cantidad inválida (mayor que 0 y hasta 100 000)');
		}
		$lot = trim((string) ($data['lot_number'] ?? '')) ?: $parsed['lot'];
		$serial = trim((string) ($data['serial_number'] ?? '')) ?: $parsed['serial'];
		$bbd = trim((string) ($data['best_before_date'] ?? '')) ?: $parsed['best_before'];
		if ($bbd !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bbd) || !checkdate(intval(substr($bbd, 5, 2)), intval(substr($bbd, 8, 2)), intval(substr($bbd, 0, 4)))))
		{
			throw new \Exception('Fecha de vencimiento inválida');
		}
		if ($serial !== null && $amount != 1)
		{
			throw new \Exception('Un número de serie identifica una sola unidad (cantidad 1)');
		}
		$device = mb_substr((string) ($data['device_id'] ?? ''), 0, 80) ?: null;
		// Hora de la lectura en el dispositivo (con milisegundos; en cola sin conexión conserva la hora real del escaneo)
		$ts = !empty($data['scanned_at']) ? (date_create((string) $data['scanned_at']) ?: date_create()) : date_create();
		$ts->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		$scannedAt = $ts->format('Y-m-d H:i:s.v');

		// Rebote del gatillo: misma lectura del mismo dispositivo a menos de SCAN_DUPLICATE_WINDOW_MS → se ignora (no suma).
		// Ventana corta a propósito: escanear unidad por unidad el mismo EAN (≈ 1 por segundo) sí debe sumar.
		$windowMs = defined('GROCY_SCAN_DUPLICATE_WINDOW_MS') ? intval(GROCY_SCAN_DUPLICATE_WINDOW_MS) : 0;
		if ($windowMs > 0 && $raw !== '' && $serial === null)
		{
			$bounce = $this->Row('SELECT id, scanned_at FROM dairy_scan_lines WHERE session_id = :s AND raw_code = :r AND IFNULL(device_id, \'\') = :d AND voided_at IS NULL ORDER BY id DESC LIMIT 1',
				[':s' => $sessionId, ':r' => $raw, ':d' => (string) $device]);
			$ms = fn(string $v) => ($d = date_create($v)) ? floatval($d->format('U.v')) * 1000 : 0;
			if ($bounce && abs($ms($bounce['scanned_at']) - $ms($scannedAt)) < $windowMs)
			{
				return ['ignored' => true, 'message' => "Lectura repetida en menos de {$windowMs} ms ignorada (rebote del lector)"] + $this->GetSession($sessionId);
			}
		}
		if ($serial !== null)
		{
			if ($this->Row('SELECT id FROM dairy_scan_lines WHERE session_id = :s AND product_id = :p AND serial_number = :n AND voided_at IS NULL', [':s' => $sessionId, ':p' => $productId, ':n' => $serial]))
			{
				throw new \Exception("Serie $serial ya escaneada en esta sesión");
			}
			if ($s['type'] === 'receive' && $this->Row('SELECT stock_id FROM dairy_stock_lots WHERE product_id = :p AND serial_number = :n', [':p' => $productId, ':n' => $serial]))
			{
				throw new \Exception("Serie $serial ya ingresó al inventario: posible etiqueta duplicada");
			}
		}

		$already = floatval($this->Row('SELECT IFNULL(SUM(amount), 0) AS v FROM dairy_scan_lines WHERE session_id = :s AND product_id = :p AND voided_at IS NULL', [':s' => $sessionId, ':p' => $productId])['v']);
		$warnings = [];
		if (in_array($s['type'], ['move', 'receive']))
		{
			$destId = intval($s['type'] === 'move' ? $s['target_location_id'] : ($s['location_id'] ?: 0));
			$dest = $destId ? $this->Row('SELECT l.name, l.storage_class, l.capacity_units, (SELECT IFNULL(SUM(amount), 0) FROM stock WHERE location_id = l.id) AS units FROM locations l WHERE l.id = :id', [':id' => $destId]) : null;
			$pClass = $this->Row('SELECT storage_class FROM products WHERE id = :id', [':id' => $productId])['storage_class'] ?? null;
			if ($dest && $pClass && $dest['storage_class'] && $pClass !== $dest['storage_class'])
			{
				throw new \Exception('Producto ' . (self::STORAGE_LABEL[$pClass] ?? $pClass) . ': no puede ir a «' . $dest['name'] . '» (' . (self::STORAGE_LABEL[$dest['storage_class']] ?? $dest['storage_class']) . ')');
			}
			if ($dest && floatval($dest['capacity_units']) > 0)
			{
				$inSession = floatval($this->Row('SELECT IFNULL(SUM(amount), 0) AS v FROM dairy_scan_lines WHERE session_id = :s AND voided_at IS NULL', [':s' => $sessionId])['v']);
				$after = floatval($dest['units']) + $inSession + $amount;
				if ($after > floatval($dest['capacity_units']))
				{
					$warnings[] = "«{$dest['name']}» quedaría al " . round($after / floatval($dest['capacity_units']) * 100) . ' % de su capacidad (' . round($after, 1) . ' / ' . floatval($dest['capacity_units']) . ' u.)';
				}
			}
		}
		if ($s['type'] === 'pick')
		{
			$item = $this->Row('SELECT i.amount - i.picked_amount AS pending FROM customer_order_items i WHERE i.order_id = :o AND i.product_id = :p', [':o' => $s['customer_order_id'], ':p' => $productId]);
			if (!$item)
			{
				throw new \Exception('El producto no está en el pedido');
			}
			if ($already + $amount > floatval($item['pending']) + 0.0001)
			{
				throw new \Exception('Excede lo pendiente del pedido (' . round(floatval($item['pending']), 2) . ')');
			}
			$co = $this->Row('SELECT branch_id FROM customer_orders WHERE id = :id', [':id' => $s['customer_order_id']]);
			$orders = CustomerOrderService::GetInstance();
			$entries = $orders->PickableEntries($productId, intval($co['branch_id']), $s['location_id'] ? intval($s['location_id']) : null, $lot ?: null, $serial ?: null);
			$free = array_sum(array_column($entries, 'free'));
			if ($free + 0.0001 < $already + $amount)
			{
				throw new \Exception(($lot ? "Lote $lot" : 'El producto') . ' no tiene stock libre suficiente' . ($s['location_id'] ? ' en la ubicación de picking' : '') . ' (libre ' . round($free, 2) . ')');
			}
			$first = $orders->PickableEntries($productId, intval($co['branch_id']))[0] ?? null;
			if ($lot && $first && $entries && $entries[0]['best_before_date'] > $first['best_before_date'])
			{
				$warnings[] = "FEFO: el lote {$lot} vence {$entries[0]['best_before_date']}, pero hay uno que vence antes ({$first['best_before_date']}" . ($first['lot_number'] ? ", lote {$first['lot_number']}" : '') . " en {$first['location_name']})";
			}
		}
		if ($s['type'] === 'move')
		{
			$inSource = floatval($this->Row('SELECT IFNULL(SUM(amount), 0) AS v FROM stock WHERE product_id = :p AND location_id = :l', [':p' => $productId, ':l' => $s['location_id']])['v']);
			if ($already + $amount > $inSource + 0.0001)
			{
				throw new \Exception('Solo hay ' . round($inSource, 2) . ' en el origen y ya se escanearon ' . round($already, 2));
			}
		}
		elseif ($s['type'] === 'receive')
		{
			$item = $this->Row('SELECT IFNULL(approved_amount, 0) - IFNULL(received_amount, 0) - IFNULL(rejected_amount, 0) AS pending FROM procurement_order_items WHERE order_id = :o AND product_id = :p', [':o' => $s['order_id'], ':p' => $productId]);
			if (!$item)
			{
				throw new \Exception('El producto no está en la orden de compra');
			}
			if ($already + $amount > floatval($item['pending']) + 0.0001)
			{
				throw new \Exception('Excede lo pendiente de la orden (' . round(floatval($item['pending']), 2) . '); registre el excedente como otra orden');
			}
		}

		try
		{
			$this->Db()->prepare('INSERT INTO dairy_scan_lines (session_id, client_uuid, raw_code, product_id, lot_number, serial_number, best_before_date, amount, device_id, user_id, scanned_at, received_at)
				VALUES (:s, :u, :r, :p, :lot, :ser, :bbd, :a, :d, :usr, :sa, :ra)')->execute([
				':s' => $sessionId, ':u' => $uuid, ':r' => $raw !== '' ? $raw : 'manual', ':p' => $productId, ':lot' => $lot, ':ser' => $serial, ':bbd' => $bbd,
				':a' => $amount, ':d' => $device, ':usr' => $userId, ':sa' => $scannedAt, ':ra' => date('Y-m-d H:i:s')
			]);
		}
		catch (\PDOException $e)
		{
			if (str_contains($e->getMessage(), 'UNIQUE'))
			{
				throw new \Exception('Lectura duplicada (serie o identificador ya registrado)');
			}
			throw $e;
		}
		$line = $this->Row('SELECT * FROM dairy_scan_lines WHERE id = :id', [':id' => $this->Db()->lastInsertId()]);
		return ['line' => $line, 'parsed' => $parsed, 'warnings' => $warnings] + $this->GetSession($sessionId);
	}

	public function VoidLine(int $lineId, int $userId): array
	{
		$line = $this->Row('SELECT * FROM dairy_scan_lines WHERE id = :id', [':id' => $lineId]);
		if (!$line)
		{
			throw new \Exception('Lectura no encontrada');
		}
		$this->Session(intval($line['session_id']));
		$this->Db()->prepare('UPDATE dairy_scan_lines SET voided_at = :t, voided_by_user_id = :u WHERE id = :id AND voided_at IS NULL')->execute([':t' => date('Y-m-d H:i:s'), ':u' => $userId, ':id' => $lineId]);
		return $this->GetSession(intval($line['session_id']));
	}

	public function CancelSession(int $sessionId, int $userId, string $reason): array
	{
		$this->Session($sessionId);
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo de anulación');
		}
		$this->Db()->prepare("UPDATE dairy_scan_sessions SET status = 'cancelled', closed_at = :t, closed_by_user_id = :u, notes = trim(IFNULL(notes, '') || ' · Anulada: ' || :r) WHERE id = :id AND status = 'open'")
			->execute([':t' => date('Y-m-d H:i:s'), ':u' => $userId, ':r' => trim($reason), ':id' => $sessionId]);
		return $this->GetSession($sessionId);
	}

	/**
	 * Cierre (contabilización). Idempotente: una sesión cerrada devuelve su resultado guardado. La sesión se reclama con
	 * UPDATE … WHERE status = 'open' para que dos cierres simultáneos no contabilicen dos veces.
	 */
	public function CloseSession(int $sessionId, array $data, int $userId): array
	{
		$s = $this->Session($sessionId, false);
		if ($s['status'] === 'closed')
		{
			return $this->GetSession($sessionId) + ['already_closed' => true];
		}
		if ($s['status'] !== 'open')
		{
			throw new \Exception('La sesión fue anulada');
		}
		$lines = $this->Rows('SELECT * FROM dairy_scan_lines WHERE session_id = :s AND voided_at IS NULL ORDER BY id', [':s' => $sessionId]);
		if (count($lines) === 0 && !($s['type'] === 'count' && !empty($data['include_missing'])))
		{
			throw new \Exception('La sesión no tiene lecturas');
		}
		$claim = $this->Db()->prepare("UPDATE dairy_scan_sessions SET status = 'closing' WHERE id = :id AND status = 'open'");
		$claim->execute([':id' => $sessionId]);
		if ($claim->rowCount() !== 1)
		{
			throw new \Exception('La sesión se está cerrando desde otro dispositivo');
		}

		try
		{
			$result = match ($s['type'])
			{
				'count' => $this->CloseCount($s, $lines, !empty($data['include_missing']), $userId),
				'move' => $this->CloseMove($s, $lines, $userId),
				'receive' => $this->CloseReceive($s, $lines, $data, $userId),
				'pick' => CustomerOrderService::GetInstance()->RegisterPicks(intval($s['customer_order_id']), $s, $lines, $userId),
			};
		}
		catch (\Throwable $e)
		{
			$this->Db()->prepare("UPDATE dairy_scan_sessions SET status = 'open' WHERE id = :id AND status = 'closing'")->execute([':id' => $sessionId]);
			throw $e;
		}
		$this->Db()->prepare("UPDATE dairy_scan_sessions SET status = 'closed', closed_at = :t, closed_by_user_id = :u, result_json = :r WHERE id = :id")
			->execute([':t' => date('Y-m-d H:i:s'), ':u' => $userId, ':r' => json_encode($result, JSON_UNESCAPED_UNICODE), ':id' => $sessionId]);
		return $this->GetSession($sessionId);
	}

	// Conteo guiado → un conteo cíclico por producto en la ubicación; las diferencias siguen el flujo de ajuste aprobado
	private function CloseCount(array $s, array $lines, bool $includeMissing, int $userId): array
	{
		$counted = [];
		foreach ($lines as $l)
		{
			$counted[intval($l['product_id'])] = ($counted[intval($l['product_id'])] ?? 0) + floatval($l['amount']);
		}
		if ($includeMissing)
		{
			foreach ($this->Rows('SELECT product_id FROM stock WHERE location_id = :l GROUP BY product_id HAVING SUM(amount) > 0', [':l' => $s['location_id']]) as $r)
			{
				$counted[intval($r['product_id'])] ??= 0.0;
			}
		}
		$db = $this->Db();
		$cc = CycleCountService::GetInstance();
		$out = [];
		foreach ($counted as $pid => $qty)
		{
			$pending = $this->Row("SELECT id FROM stock_cycle_counts WHERE product_id = :p AND branch_id = :b AND location_id = :l AND status = 'pending' ORDER BY id LIMIT 1", [':p' => $pid, ':b' => $s['branch_id'], ':l' => $s['location_id']]);
			if ($pending)
			{
				$countId = intval($pending['id']);
			}
			else
			{
				$sys = floatval($this->Row('SELECT IFNULL(SUM(amount), 0) AS v FROM stock WHERE product_id = :p AND location_id = :l', [':p' => $pid, ':l' => $s['location_id']])['v']);
				$db->prepare("INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id, notes) VALUES (:b, :p, :l, :d, 'pending', :t, :u, :n)")
					->execute([':b' => $s['branch_id'], ':p' => $pid, ':l' => $s['location_id'], ':d' => date('Y-m-d'), ':t' => $sys, ':u' => $userId, ':n' => "Conteo guiado {$s['folio']}"]);
				$countId = intval($db->lastInsertId());
			}
			$r = $cc->Execute($countId, $qty, "Conteo guiado {$s['folio']}", $userId);
			$row = $this->Row('SELECT c.id, c.theoretical_stock, c.counted_stock, c.difference_amount, c.difference_cost, c.status, p.name AS product_name FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id WHERE c.id = :id', [':id' => $countId]);
			$out[] = $row + ['message' => $r['message'] ?? null];
		}
		$diff = array_filter($out, fn($r) => abs(floatval($r['difference_amount'])) > 0.0001);
		return ['counts' => $out, 'differences' => count($diff), 'difference_cost' => round(array_sum(array_map(fn($r) => floatval($r['difference_cost']), $diff)), 2),
			'message' => count($out) . ' producto(s) contados · ' . count($diff) . ' con diferencia (pasan a aprobación de ajuste en Conteos; el stock no cambia hasta aprobar)'];
	}

	// Movimiento dentro de la sucursal: un TransferProduct por lectura (por lote si se escaneó y existe en el origen)
	private function CloseMove(array $s, array $lines, int $userId): array
	{
		$db = $this->Db();
		$stock = StockService::GetInstance();
		$own = !$db->inTransaction();
		if ($own)
		{
			$db->beginTransaction();
		}
		try
		{
			$moved = [];
			foreach ($lines as $l)
			{
				$entry = 'default';
				if ($l['lot_number'] || $l['serial_number'])
				{
					$e = $this->Row('SELECT s.stock_id FROM stock s JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id WHERE s.product_id = :p AND s.location_id = :l AND s.amount >= :a AND (dl.lot_number = :lot OR dl.serial_number = :ser) ORDER BY s.best_before_date LIMIT 1',
						[':p' => $l['product_id'], ':l' => $s['location_id'], ':a' => $l['amount'], ':lot' => $l['lot_number'], ':ser' => $l['serial_number']]);
					$entry = $e['stock_id'] ?? 'default';
				}
				$tx = null;
				$stock->TransferProduct(intval($l['product_id']), floatval($l['amount']), intval($s['location_id']), intval($s['target_location_id']), $entry, $tx);
				$moved[] = ['product_id' => intval($l['product_id']), 'amount' => floatval($l['amount']), 'stock_entry' => $entry, 'transaction_id' => $tx];
			}
			if ($own)
			{
				$db->commit();
			}
		}
		catch (\Throwable $e)
		{
			if ($own && $db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
		return ['moves' => $moved, 'message' => count($moved) . ' movimiento(s) contabilizado(s) en el kardex'];
	}

	// Recepción: una llamada a ReceiveProcurementOrder por fecha de vencimiento; lotes/series quedan asociados al stock_id creado
	private function CloseReceive(array $s, array $lines, array $data, int $userId): array
	{
		$items = array_column($this->Rows('SELECT id, product_id FROM procurement_order_items WHERE order_id = :o', [':o' => $s['order_id']]), 'id', 'product_id');
		$groups = [];
		foreach ($lines as $l)
		{
			$groups[$l['best_before_date'] ?? ''][] = $l;
		}
		$mrp = MrpService::GetInstance();
		$receipts = [];
		foreach ($groups as $bbd => $group)
		{
			$byItem = [];
			foreach ($group as $l)
			{
				$itemId = $items[$l['product_id']] ?? null;
				$byItem[$itemId] ??= ['order_item_id' => $itemId, 'delivered_amount' => 0, 'best_before_date' => $bbd !== '' ? $bbd : null, 'location_id' => $s['location_id'], 'lot_number' => $l['lot_number']];
				$byItem[$itemId]['delivered_amount'] += floatval($l['amount']);
			}
			$r = $mrp->ReceiveProcurementOrder(intval($s['order_id']), [
				'items' => array_values($byItem),
				'temperature_on_arrival' => $data['temperature_on_arrival'] ?? null,
				'supplier_document' => $data['supplier_document'] ?? null,
				'notes' => "Recepción por escáner {$s['folio']}",
			], $userId);
			$receipts[] = $r;
			if (!empty($r['stock_transaction_id']))
			{
				$this->RegisterLots($r['stock_transaction_id'], $group, $userId);
			}
		}
		return ['receipts' => $receipts, 'message' => implode(' · ', array_column($receipts, 'message'))];
	}

	// Asocia lote/serie a las entradas creadas por la transacción (por producto, en orden de lectura)
	private function RegisterLots(string $transactionId, array $lines, int $userId): void
	{
		$map = [];
		foreach ($lines as $l)
		{
			$pid = intval($l['product_id']);
			$map[$pid] ??= ['lot' => null, 'serials' => []];
			$map[$pid]['lot'] ??= $l['lot_number'] ?: null;
			if ($l['serial_number'])
			{
				$map[$pid]['serials'][] = $l['serial_number'];
			}
		}
		$map = array_map(fn($m) => ['lot' => $m['lot'], 'serial' => count($m['serials']) === 1 ? $m['serials'][0] : null], $map);
		TraceabilityService::GetInstance()->RegisterLots($transactionId, $map, $userId);
	}

	public function FormData(?int $branchId): array
	{
		$branches = DairyAccess::FilterBranches($this->Rows('SELECT id, name FROM branches ORDER BY id'));
		$locs = $this->Rows('SELECT l.*, b.name AS branch_name FROM locations l LEFT JOIN branches b ON b.id = l.branch_id WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL ORDER BY l.branch_id, l.name');
		$locs = array_values(array_filter(array_map(fn($l) => $this->LocationView($l), $locs), fn($l) => DairyAccess::CanSeeBranch($l['branch_id'])));
		$orders = $this->Rows("SELECT o.id, o.order_code AS order_number, o.branch_id, o.status, sl.name AS supplier_name FROM procurement_orders o LEFT JOIN shopping_locations sl ON sl.id = o.supplier_id
			WHERE o.status IN ('approved', 'sent', 'partially_received') ORDER BY o.id DESC LIMIT 100");
		$orders = array_values(array_filter($orders, fn($o) => DairyAccess::CanSeeBranch(intval($o['branch_id']))));
		$customerOrders = array_map(fn($o) => ['id' => intval($o['id']), 'order_code' => $o['order_code'], 'branch_id' => intval($o['branch_id']), 'customer_name' => $o['customer_name'], 'status' => $o['status']],
			array_values(array_filter(CustomerOrderService::GetInstance()->List(null, 'open'), fn($o) => in_array($o['status'], ['confirmed', 'picking']))));
		return ['branches' => array_values($branches), 'locations' => $locs, 'orders' => $orders, 'customer_orders' => $customerOrders, 'types' => self::TYPES];
	}
}
