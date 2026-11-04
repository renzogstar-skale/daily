<?php

namespace Grocy\Services;

class SupplierProcurementService extends BaseService
{
	/**
	 * Retorna lista de proveedores con métricas resumidas
	 */
	public function GetSuppliers(?bool $activeOnly = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				sl.id,
				sl.name,
				sl.description,
				sl.active,
				sm.contact_name,
				sm.email,
				sm.phone,
				sm.tax_id,
				sm.address,
				IFNULL(sm.lead_time_days, 2) AS lead_time_days,
				IFNULL(sm.min_order_amount, 0.0) AS min_order_amount,
				IFNULL(sm.payment_terms, 'Contado') AS payment_terms,
				IFNULL(sm.rating, 5.0) AS rating,
				sm.notes,
				(SELECT COUNT(*) FROM supplier_product_prices spp WHERE spp.supplier_id = sl.id AND spp.is_current = 1) AS products_count,
				(SELECT COUNT(*) FROM supplier_deliveries sd WHERE sd.supplier_id = sl.id) AS deliveries_count,
				(
					SELECT ROUND((SUM(CASE WHEN sd.status = 'on_time' THEN 1.0 ELSE 0.0 END) / COUNT(*)) * 100, 1)
					FROM supplier_deliveries sd 
					WHERE sd.supplier_id = sl.id
				) AS otif_percentage,
				(SELECT COUNT(*) FROM supplier_incidents si WHERE si.supplier_id = sl.id AND si.status IN ('open', 'claimed')) AS pending_incidents_count
			FROM shopping_locations sl
			LEFT JOIN suppliers_meta sm ON sl.id = sm.id
			WHERE 1=1
		";

		if ($activeOnly === true)
		{
			$sql .= " AND sl.active = 1";
		}
		elseif ($activeOnly === false)
		{
			$sql .= " AND sl.active = 0";
		}

		$sql .= " ORDER BY sl.name ASC";

		$stmt = $dbRaw->query($sql);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Retorna un proveedor por su ID con sus detalles
	 */
	public function GetSupplier(int $id): ?array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT 
				sl.id,
				sl.name,
				sl.description,
				sl.active,
				sm.contact_name,
				sm.email,
				sm.phone,
				sm.tax_id,
				sm.address,
				IFNULL(sm.lead_time_days, 2) AS lead_time_days,
				IFNULL(sm.min_order_amount, 0.0) AS min_order_amount,
				IFNULL(sm.payment_terms, 'Contado') AS payment_terms,
				IFNULL(sm.rating, 5.0) AS rating,
				sm.notes
			FROM shopping_locations sl
			LEFT JOIN suppliers_meta sm ON sl.id = sm.id
			WHERE sl.id = :id
		");
		$stmt->execute([':id' => $id]);
		$supplier = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$supplier)
		{
			return null;
		}

		// Precios / Catálogo
		$stmtPrices = $dbRaw->prepare("
			SELECT 
				spp.*,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name
			FROM supplier_product_prices spp
			JOIN products p ON spp.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			WHERE spp.supplier_id = :id AND spp.is_current = 1
			ORDER BY p.name ASC
		");
		$stmtPrices->execute([':id' => $id]);
		$supplier['prices'] = $stmtPrices->fetchAll(\PDO::FETCH_ASSOC);

		// Entregas recientes
		$stmtDeliv = $dbRaw->prepare("
			SELECT sd.*, b.name AS branch_name
			FROM supplier_deliveries sd
			JOIN branches b ON sd.branch_id = b.id
			WHERE sd.supplier_id = :id
			ORDER BY sd.order_date DESC
			LIMIT 10
		");
		$stmtDeliv->execute([':id' => $id]);
		$supplier['deliveries'] = $stmtDeliv->fetchAll(\PDO::FETCH_ASSOC);

		// Incidencias
		$stmtInc = $dbRaw->prepare("
			SELECT si.*, p.name AS product_name
			FROM supplier_incidents si
			LEFT JOIN products p ON si.product_id = p.id
			WHERE si.supplier_id = :id
			ORDER BY si.incident_date DESC
		");
		$stmtInc->execute([':id' => $id]);
		$supplier['incidents'] = $stmtInc->fetchAll(\PDO::FETCH_ASSOC);

		return $supplier;
	}

	/**
	 * Crea o edita un proveedor
	 */
	public function SaveSupplier(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$id = isset($data['id']) && !empty($data['id']) ? intval($data['id']) : 0;
		$name = trim($data['name'] ?? '');
		$description = trim($data['description'] ?? '');
		$contactName = trim($data['contact_name'] ?? '');
		$email = trim($data['email'] ?? '');
		$phone = trim($data['phone'] ?? '');
		$taxId = trim($data['tax_id'] ?? '');
		$address = trim($data['address'] ?? '');
		$leadTimeDays = intval($data['lead_time_days'] ?? 2);
		$minOrderAmount = floatval($data['min_order_amount'] ?? 0.0);
		$paymentTerms = trim($data['payment_terms'] ?? 'Crédito 15 días');
		$rating = floatval($data['rating'] ?? 5.0);
		$notes = trim($data['notes'] ?? '');
		$active = isset($data['active']) ? intval($data['active']) : 1;

		if ($id > 0)
		{
			// Actualizar shopping_locations
			$stmt = $dbRaw->prepare("UPDATE shopping_locations SET name = :name, description = :description, active = :active WHERE id = :id");
			$stmt->execute([':name' => $name, ':description' => $description, ':active' => $active, ':id' => $id]);

			// Actualizar suppliers_meta
			$metaExists = $dbRaw->query("SELECT COUNT(*) FROM suppliers_meta WHERE id = " . $id)->fetchColumn();
			if ($metaExists > 0)
			{
				$stmtMeta = $dbRaw->prepare("
					UPDATE suppliers_meta 
					SET contact_name = :contact_name, email = :email, phone = :phone, tax_id = :tax_id, 
					    address = :address, lead_time_days = :lead_time_days, min_order_amount = :min_order_amount, 
					    payment_terms = :payment_terms, rating = :rating, notes = :notes, active = :active
					WHERE id = :id
				");
			}
			else
			{
				$stmtMeta = $dbRaw->prepare("
					INSERT INTO suppliers_meta (id, contact_name, email, phone, tax_id, address, lead_time_days, min_order_amount, payment_terms, rating, notes, active)
					VALUES (:id, :contact_name, :email, :phone, :tax_id, :address, :lead_time_days, :min_order_amount, :payment_terms, :rating, :notes, :active)
				");
			}
			$stmtMeta->execute([
				':id' => $id,
				':contact_name' => $contactName,
				':email' => $email,
				':phone' => $phone,
				':tax_id' => $taxId,
				':address' => $address,
				':lead_time_days' => $leadTimeDays,
				':min_order_amount' => $minOrderAmount,
				':payment_terms' => $paymentTerms,
				':rating' => $rating,
				':notes' => $notes,
				':active' => $active
			]);

			return $id;
		}
		else
		{
			// Crear en shopping_locations
			$stmt = $dbRaw->prepare("INSERT INTO shopping_locations (name, description, active) VALUES (:name, :description, :active)");
			$stmt->execute([':name' => $name, ':description' => $description, ':active' => $active]);
			$newId = intval($dbRaw->lastInsertId());

			// Crear en suppliers_meta
			$stmtMeta = $dbRaw->prepare("
				INSERT INTO suppliers_meta (id, contact_name, email, phone, tax_id, address, lead_time_days, min_order_amount, payment_terms, rating, notes, active)
				VALUES (:id, :contact_name, :email, :phone, :tax_id, :address, :lead_time_days, :min_order_amount, :payment_terms, :rating, :notes, :active)
			");
			$stmtMeta->execute([
				':id' => $newId,
				':contact_name' => $contactName,
				':email' => $email,
				':phone' => $phone,
				':tax_id' => $taxId,
				':address' => $address,
				':lead_time_days' => $leadTimeDays,
				':min_order_amount' => $minOrderAmount,
				':payment_terms' => $paymentTerms,
				':rating' => $rating,
				':notes' => $notes,
				':active' => $active
			]);

			return $newId;
		}
	}

	/**
	 * Activa o desactiva un proveedor
	 */
	public function ToggleSupplierActive(int $id, int $active): bool
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt1 = $dbRaw->prepare("UPDATE shopping_locations SET active = :active WHERE id = :id");
		$stmt1->execute([':active' => $active, ':id' => $id]);

		$stmt2 = $dbRaw->prepare("UPDATE suppliers_meta SET active = :active WHERE id = :id");
		$stmt2->execute([':active' => $active, ':id' => $id]);

		return true;
	}

	/**
	 * Retorna historial de precios
	 */
	public function GetPriceHistory(?int $supplierId = null, ?int $productId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				spp.*,
				sl.name AS supplier_name,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name
			FROM supplier_product_prices spp
			JOIN shopping_locations sl ON spp.supplier_id = sl.id
			JOIN products p ON spp.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			WHERE 1=1
		";
		$params = [];

		if ($supplierId !== null && $supplierId > 0)
		{
			$sql .= " AND spp.supplier_id = :sid";
			$params[':sid'] = $supplierId;
		}

		if ($productId !== null && $productId > 0)
		{
			$sql .= " AND spp.product_id = :pid";
			$params[':pid'] = $productId;
		}

		$sql .= " ORDER BY spp.valid_from DESC, spp.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Agrega un nuevo precio/cotización
	 */
	public function AddPriceRecord(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$supplierId = intval($data['supplier_id']);
		$productId = intval($data['product_id']);
		$price = floatval($data['price'] ?? 0);
		if ($price <= 0 || $supplierId <= 0 || $productId <= 0)
		{
			throw new \Exception('Indique proveedor, producto y un precio mayor que 0');
		}
		$minQty = floatval($data['min_order_qty'] ?? 1.0);
		$leadDays = intval($data['delivery_lead_days'] ?? 2);
		$validFrom = $data['valid_from'] ?? date('Y-m-d');
		$notes = trim($data['notes'] ?? '');

		// Marcar registros anteriores como no actuales
		$dbRaw->prepare("UPDATE supplier_product_prices SET is_current = 0 WHERE supplier_id = :sid AND product_id = :pid")
		      ->execute([':sid' => $supplierId, ':pid' => $productId]);

		$stmt = $dbRaw->prepare("
			INSERT INTO supplier_product_prices (supplier_id, product_id, price, min_order_qty, delivery_lead_days, valid_from, is_current, notes)
			VALUES (:sid, :pid, :price, :min_order_qty, :delivery_lead_days, :valid_from, 1, :notes)
		");
		$stmt->execute([
			':sid' => $supplierId,
			':pid' => $productId,
			':price' => $price,
			':min_order_qty' => $minQty,
			':delivery_lead_days' => $leadDays,
			':valid_from' => $validFrom,
			':notes' => $notes
		]);

		return intval($dbRaw->lastInsertId());
	}

	/**
	 * Comparador de costos y condiciones comerciales para un producto
	 */
	public function GetCostComparison(int $productId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT 
				spp.id AS price_id,
				spp.price,
				spp.min_order_qty,
				spp.delivery_lead_days,
				spp.valid_from,
				spp.notes AS price_notes,
				sl.id AS supplier_id,
				sl.name AS supplier_name,
				sm.payment_terms AS payment_terms,
				sm.rating AS supplier_rating,
				IFNULL(sm.lead_time_days, spp.delivery_lead_days) AS lead_time_days,
				(
					SELECT ROUND((SUM(CASE WHEN sd.status = 'on_time' THEN 1.0 ELSE 0.0 END) / COUNT(*)) * 100, 1)
					FROM supplier_deliveries sd 
					WHERE sd.supplier_id = sl.id
				) AS otif_percentage,
				(
					SELECT COUNT(*)
					FROM supplier_incidents si
					WHERE si.supplier_id = sl.id AND si.status IN ('open', 'claimed')
				) AS pending_incidents
			FROM supplier_product_prices spp
			JOIN shopping_locations sl ON spp.supplier_id = sl.id
			LEFT JOIN suppliers_meta sm ON sl.id = sm.id
			WHERE spp.product_id = :pid AND spp.is_current = 1 AND sl.active = 1
			ORDER BY spp.price ASC
		");
		$stmt->execute([':pid' => $productId]);
		$options = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($options))
		{
			return [];
		}

		// Encontrar mejor precio y entrega más rápida
		$minPrice = min(array_column($options, 'price'));
		$minLead = min(array_column($options, 'delivery_lead_days'));

		foreach ($options as &$opt)
		{
			$opt['is_best_price'] = ($opt['price'] == $minPrice);
			$opt['is_fastest_delivery'] = ($opt['delivery_lead_days'] == $minLead);
			// Puntaje 0–100 solo con datos registrados: precio relativo al mejor (50), OTIF real (30), plazo relativo al más rápido (20).
			// Sin entregas registradas el OTIF no puntúa (no se supone 100 %); el peso se reparte entre lo conocido.
			$parts = ['price' => [floatval($opt['price']) > 0 ? $minPrice / floatval($opt['price']) : 0, 50], 'lead' => [floatval($opt['delivery_lead_days']) > 0 ? max(1, $minLead) / floatval($opt['delivery_lead_days']) : 1, 20]];
			if ($opt['otif_percentage'] !== null)
			{
				$parts['otif'] = [floatval($opt['otif_percentage']) / 100, 30];
			}
			$w = array_sum(array_column($parts, 1));
			$opt['score'] = round(array_sum(array_map(fn($p) => $p[0] * $p[1], $parts)) / $w * 100, 1);
			$opt['score_basis'] = array_keys($parts);
		}

		return $options;
	}

	/**
	 * Entregas y evaluación de cumplimiento (OTIF)
	 */
	public function GetDeliveries(?int $supplierId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				sd.*,
				sl.name AS supplier_name,
				b.name AS branch_name,
				ROUND(julianday(IFNULL(sd.actual_delivery_date, date('now'))) - julianday(sd.expected_delivery_date)) AS delay_days
			FROM supplier_deliveries sd
			JOIN shopping_locations sl ON sd.supplier_id = sl.id
			JOIN branches b ON sd.branch_id = b.id
			WHERE 1=1
		";
		$params = [];

		if ($supplierId !== null && $supplierId > 0)
		{
			$sql .= " AND sd.supplier_id = :sid";
			$params[':sid'] = $supplierId;
		}

		$sql .= " ORDER BY sd.order_date DESC, sd.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Registra una entrega recibida
	 */
	public function RecordDelivery(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Número de OC secuencial si no se informa uno
		$orderReference = trim($data['order_reference'] ?? '');
		if ($orderReference === '')
		{
			$next = intval($dbRaw->query("SELECT IFNULL(MAX(id), 0) + 1 FROM supplier_deliveries")->fetchColumn());
			$orderReference = 'OC-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
		}

		$expected = strval($data['expected_delivery_date'] ?? '');
		$actual = strval($data['actual_delivery_date'] ?? '') ?: date('Y-m-d');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actual))
		{
			throw new \Exception('Indique la fecha de entrega comprometida y la real (AAAA-MM-DD)');
		}
		$ordered = floatval($data['items_ordered'] ?? 0);
		$received = floatval($data['items_received'] ?? 0);
		if ($ordered < 0 || $received < 0)
		{
			throw new \Exception('Cantidades inválidas');
		}
		// OTIF: «on_time» solo si llegó a tiempo Y completa; rechazada se respeta si se informa
		$data['status'] = ($data['status'] ?? '') === 'rejected' ? 'rejected' : ($actual > $expected ? 'delayed' : ($received + 0.0001 < $ordered ? 'partial' : 'on_time'));
		$data['actual_delivery_date'] = $actual;

		$stmt = $dbRaw->prepare("
			INSERT INTO supplier_deliveries (supplier_id, branch_id, order_reference, order_date, expected_delivery_date, actual_delivery_date, status, items_ordered, items_received, temperature_on_arrival, compliance_rating, notes)
			VALUES (:supplier_id, :branch_id, :order_reference, :order_date, :expected_delivery_date, :actual_delivery_date, :status, :items_ordered, :items_received, :temperature_on_arrival, :compliance_rating, :notes)
		");
		$stmt->execute([
			':supplier_id' => intval($data['supplier_id']),
			':branch_id' => intval($data['branch_id'] ?? 1),
			':order_reference' => $orderReference,
			':order_date' => $data['order_date'] ?? date('Y-m-d'),
			':expected_delivery_date' => $data['expected_delivery_date'],
			':actual_delivery_date' => $data['actual_delivery_date'] ?? date('Y-m-d'),
			':status' => $data['status'] ?? 'on_time',
			':items_ordered' => floatval($data['items_ordered'] ?? 0),
			':items_received' => floatval($data['items_received'] ?? 0),
			':temperature_on_arrival' => isset($data['temperature_on_arrival']) ? floatval($data['temperature_on_arrival']) : null,
			':compliance_rating' => intval($data['compliance_rating'] ?? 5),
			':notes' => trim($data['notes'] ?? '')
		]);

		return intval($dbRaw->lastInsertId());
	}

	/**
	 * Incidencias con proveedores
	 */
	public function GetIncidents(?int $supplierId = null, ?string $status = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				si.*,
				sl.name AS supplier_name,
				p.name AS product_name,
				b.name AS branch_name
			FROM supplier_incidents si
			JOIN shopping_locations sl ON si.supplier_id = sl.id
			LEFT JOIN products p ON si.product_id = p.id
			JOIN branches b ON si.branch_id = b.id
			WHERE 1=1
		";
		$params = [];

		if ($supplierId !== null && $supplierId > 0)
		{
			$sql .= " AND si.supplier_id = :sid";
			$params[':sid'] = $supplierId;
		}

		if ($status !== null && !empty($status) && $status !== 'all')
		{
			$sql .= " AND si.status = :st";
			$params[':st'] = $status;
		}

		$sql .= " ORDER BY si.incident_date DESC, si.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Registra o actualiza una incidencia
	 */
	public function SaveIncident(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$id = isset($data['id']) && !empty($data['id']) ? intval($data['id']) : 0;

		if ($id > 0)
		{
			$stmt = $dbRaw->prepare("
				UPDATE supplier_incidents 
				SET status = :status, resolution_notes = :resolution_notes, cost_impact = :cost_impact
				WHERE id = :id
			");
			$stmt->execute([
				':id' => $id,
				':status' => $data['status'] ?? 'resolved',
				':resolution_notes' => trim($data['resolution_notes'] ?? ''),
				':cost_impact' => floatval($data['cost_impact'] ?? 0.0)
			]);
			return $id;
		}
		else
		{
			$stmt = $dbRaw->prepare("
				INSERT INTO supplier_incidents (supplier_id, product_id, branch_id, incident_date, incident_type, severity, status, description, resolution_notes, cost_impact)
				VALUES (:supplier_id, :product_id, :branch_id, :incident_date, :incident_type, :severity, :status, :description, :resolution_notes, :cost_impact)
			");
			$stmt->execute([
				':supplier_id' => intval($data['supplier_id']),
				':product_id' => !empty($data['product_id']) ? intval($data['product_id']) : null,
				':branch_id' => intval($data['branch_id'] ?? 1),
				':incident_date' => $data['incident_date'] ?? date('Y-m-d'),
				':incident_type' => $data['incident_type'] ?? 'other',
				':severity' => $data['severity'] ?? 'medium',
				':status' => $data['status'] ?? 'open',
				':description' => trim($data['description'] ?? ''),
				':resolution_notes' => trim($data['resolution_notes'] ?? ''),
				':cost_impact' => floatval($data['cost_impact'] ?? 0.0)
			]);
			return intval($dbRaw->lastInsertId());
		}
	}

	/**
	 * Recomendaciones de reabastecimiento: vista resumida del cálculo MRP (un solo motor de reposición).
	 * Solo productos bajo punto de pedido, con proveedor, costo y fecha de llegada del MRP.
	 */
	public function GetSmartProcurementRecommendations(?int $branchId = 1): array
	{
		$mrp = MrpService::GetInstance()->CalculateMrp($branchId);
		$recommendations = [];
		foreach ($mrp['items'] as $i)
		{
			if ($i['suggested_order'] <= 0)
			{
				continue;
			}
			$outOfStock = $i['usable_stock'] <= 0;
			$recommendations[] = [
				'product_id' => $i['product_id'],
				'product_name' => $i['product_name'],
				'sku' => '',
				'qu_name' => $i['qu_name'],
				'current_stock' => $i['usable_stock'],
				'min_stock_amount' => $i['rop'],
				'recommended_order_qty' => $i['suggested_order'],
				'estimated_cost' => $i['estimated_cost'],
				'urgency' => $outOfStock ? 'critical' : 'high',
				'reason' => ($outOfStock ? 'Sin stock utilizable. ' : '') . "Disponible neto {$i['net_available']} ≤ punto de pedido {$i['rop']} (VMD {$i['vmd']}, LT {$i['lead_time_days']} d)." . (empty($i['warnings']) ? '' : ' ' . implode(' ', $i['warnings'])),
				'suggested_supplier_id' => $i['supplier_id'] ?: null,
				'suggested_supplier_name' => $i['supplier_name'],
				'unit_price' => $i['purchase_price'],
				'lead_time_days' => $i['lead_time_days'],
				'expected_delivery_date' => date('Y-m-d', strtotime('+' . intval($i['lead_time_days']) . ' days'))
			];
		}
		usort($recommendations, fn($a, $b) => [$a['urgency'] !== 'critical', -$a['estimated_cost']] <=> [$b['urgency'] !== 'critical', -$b['estimated_cost']]);

		return $recommendations;
	}

	/**
	 * Presupuesto y compromisos de compras del mes, calculado desde los registros:
	 *  - Asignado: no hay presupuesto de compras cargado; se deriva de la meta de ventas de cada sucursal
	 *    (meta × (1 − margen objetivo) = costo planificado), repartido por departamento según su peso en el costo de ventas del mes.
	 *  - Registrado: recepciones aceptadas de órdenes de compra (procurement_receipt_items).
	 *  - Comprometido: líneas de órdenes aprobadas o enviadas aún pendientes de recibir.
	 * Sin facturas ni pagos inventados: los documentos listados son recepciones y órdenes reales.
	 */
	public function BudgetOverview(?string $month = null, ?array $allowedBranches = null): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dm = DailyMetricsService::GetInstance();
		$month = ($month && preg_match('/^\d{4}-\d{2}$/', $month)) ? $month : date('Y-m');
		$from = "$month-01";
		$to = min(date('Y-m-t', strtotime($from)), date('Y-m-d'));
		$months = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
		$icons = ['dairy' => 'fa-cheese', 'bebidas' => 'fa-bottle-water', 'fruver' => 'fa-carrot', 'carnes' => 'fa-drumstick-bite', 'bakery' => 'fa-bread-slice', 'abarrotes' => 'fa-jar', 'congelados' => 'fa-snowflake', 'electronica' => 'fa-mobile-screen', 'otros' => 'fa-box'];
		$branches = $db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
		if ($allowedBranches !== null)
		{
			$branches = array_intersect_key($branches, array_flip($allowedBranches));
		}

		$cats = [];
		$sum = ['assigned' => 0.0, 'registered' => 0.0, 'committed' => 0.0];
		$targets = [];
		$cogsTotal = [];
		foreach (DailyMetricsService::DEPARTMENTS as $k => [$name])
		{
			$dept = $dm->DeptSql([$k]);
			$cat = ['key' => $k, 'name' => $name, 'icon' => $icons[$k] ?? 'fa-box', 'assigned' => 0.0, 'registered' => 0.0, 'committed' => 0.0, 'branches' => []];
			foreach ($branches as $bid => $bname)
			{
				// Costo planificado de la sucursal × peso del departamento en su costo de ventas del mes
				$t = $targets[$bid] ??= $dm->Targets($bid, $from);
				$plan = ($t['sales_target'] !== null && ($t['sources']['sales'] ?? '') === 'budget_branch') ? $t['sales_target'] * (1 - floatval($t['margin_target_pct']) / 100) : 0.0;
				$cogsAll = $cogsTotal[$bid] ??= $dm->Sales($bid, $from, $to)['cogs'];
				$cogsDept = $dm->Sales($bid, $from, $to, [$k])['cogs'];
				$assigned = ($plan > 0 && $cogsAll > 0) ? round($plan * $cogsDept / $cogsAll, 2) : 0.0;

				$rec = $db->query("SELECT pr.receipt_code AS code, sl.name AS supplier, date(pr.received_at) AS date, SUM(pri.accepted_amount * pri.unit_price) AS amount
					FROM procurement_receipt_items pri JOIN procurement_receipts pr ON pr.id = pri.receipt_id JOIN products p ON p.id = pri.product_id LEFT JOIN shopping_locations sl ON sl.id = pr.supplier_id
					WHERE pr.branch_id = $bid AND date(pr.received_at) BETWEEN '$from' AND '$to' AND $dept GROUP BY pr.id ORDER BY pr.received_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
				$po = $db->query("SELECT po.order_code AS code, sl.name AS supplier, IFNULL(po.expected_delivery_date, date(po.created_at)) AS date, po.status,
						SUM((IFNULL(poi.approved_amount, poi.suggested_amount) - poi.received_amount - poi.rejected_amount) * poi.unit_price) AS amount
					FROM procurement_order_items poi JOIN procurement_orders po ON po.id = poi.order_id JOIN products p ON p.id = poi.product_id LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
					WHERE po.branch_id = $bid AND po.status IN ('approved', 'sent', 'partially_received') AND $dept GROUP BY po.id HAVING amount > 0.009")->fetchAll(\PDO::FETCH_ASSOC);
				$registered = round(array_sum(array_column($rec, 'amount')), 2);
				$committed = round(array_sum(array_column($po, 'amount')), 2);
				if ($assigned + $registered + $committed <= 0)
				{
					continue;
				}
				$records = [];
				foreach (array_slice($rec, 0, 5) as $r)
				{
					$records[] = ['type' => 'Recepción', 'code' => $r['code'], 'supplier' => $r['supplier'] ?? '—', 'date' => $r['date'], 'amount' => round($r['amount'], 2), 'status' => 'Recibida', 'status_class' => 'badge-success'];
				}
				foreach ($po as $r)
				{
					$records[] = ['type' => 'Orden de Compra', 'code' => $r['code'], 'supplier' => $r['supplier'] ?? '—', 'date' => $r['date'], 'amount' => round($r['amount'], 2),
						'status' => $r['status'] === 'approved' ? 'Aprobada · por enviar' : 'Comprometida en tránsito', 'status_class' => 'badge-warning text-dark'];
				}
				$cat['branches'][] = ['branch_id' => $bid, 'branch_name' => $bname, 'assigned' => $assigned, 'registered' => $registered, 'committed' => $committed,
					'available' => round($assigned - $registered - $committed, 2), 'receipts' => count($rec), 'records' => $records];
				$cat['assigned'] += $assigned;
				$cat['registered'] += $registered;
				$cat['committed'] += $committed;
			}
			if (!$cat['branches'])
			{
				continue;
			}
			$cat['available'] = round($cat['assigned'] - $cat['registered'] - $cat['committed'], 2);
			$cat['pct'] = $cat['assigned'] > 0 ? round(($cat['registered'] + $cat['committed']) / $cat['assigned'] * 100, 1) : 0;
			foreach (['assigned', 'registered', 'committed'] as $f)
			{
				$cat[$f] = round($cat[$f], 2);
				$sum[$f] += $cat[$f];
			}
			$cats[] = $cat;
		}
		usort($cats, fn($a, $b) => $b['registered'] + $b['committed'] <=> $a['registered'] + $a['committed']);

		$commitments = [];
		$bList = $branches ? implode(',', array_map('intval', array_keys($branches))) : '0';
		foreach ($db->query("SELECT po.id, po.order_code, po.status, po.total_estimated_cost, IFNULL(po.expected_delivery_date, date(po.created_at)) AS date, sl.name AS supplier, b.name AS branch, u.username AS responsible
			FROM procurement_orders po LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id LEFT JOIN branches b ON b.id = po.branch_id LEFT JOIN users u ON u.id = po.approved_by_user_id
			WHERE po.status IN ('approved', 'sent', 'partially_received') AND po.branch_id IN ($bList) ORDER BY date LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) as $o)
		{
			$commitments[] = ['date' => $o['date'], 'concept' => $o['order_code'] . ' · ' . $o['branch'], 'supplier' => $o['supplier'] ?? '—', 'responsible' => $o['responsible'] ?? 'Sin aprobador',
				'amount' => round(floatval($o['total_estimated_cost']), 2), 'status' => $o['status'] === 'approved' ? 'Por enviar' : 'En tránsito',
				'status_class' => $o['status'] === 'approved' ? 'badge-info' : 'badge-warning text-dark', 'href' => '/mrp'];
		}

		return [
			'period' => $months[intval(substr($month, 5, 2))] . ' ' . substr($month, 0, 4),
			'is_derived' => true,
			'note' => 'Asignado = meta de ventas × (1 − margen objetivo), repartido por el peso de cada departamento en el costo de ventas del mes. Registrado = recepciones; comprometido = órdenes abiertas.',
			'summary' => ['assigned' => round($sum['assigned'], 2), 'registered' => round($sum['registered'], 2), 'committed' => round($sum['committed'], 2),
				'available' => round($sum['assigned'] - $sum['registered'] - $sum['committed'], 2)],
			'categories' => $cats,
			'commitments' => $commitments
		];
	}
}
