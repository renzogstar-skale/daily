<?php

namespace Grocy\Services;

/**
 * Conciliación de 3 vías (referencia: SAP MIRO / Dynamics 365 three-way match):
 * cada línea de la factura del proveedor se compara con la orden de compra (cantidad y precio pactados)
 * y con lo realmente recibido (aceptado en recepción). Diferencias:
 *   qty_over_received → se factura más de lo recibido (acumulado con facturas anteriores)
 *   qty_over_ordered  → se factura más de lo pedido
 *   price_over_po     → precio unitario sobre el de la OC más allá de INVOICE_PRICE_TOLERANCE_PCT
 * Sin diferencias la factura queda "conciliada"; con diferencias requiere decisión de otro usuario.
 */
class SupplierInvoiceService extends BaseService
{
	const STATUS = [
		'matched' => ['Conciliada', 'success'],
		'discrepancy' => ['Con diferencias', 'danger'],
		'approved_with_difference' => ['Aprobada con diferencia', 'warning'],
		'rejected' => ['Rechazada', 'secondary']
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public function RegisterInvoice(int $orderId, array $data, int $userId): array
	{
		$db = $this->Db();
		$order = $db->query('SELECT * FROM procurement_orders WHERE id = ' . $orderId)->fetch(\PDO::FETCH_OBJ);
		if (!$order)
		{
			throw new \Exception('Orden de compra no encontrada');
		}
		if (!in_array($order->status, ['partially_received', 'received', 'closed']))
		{
			throw new \Exception('Solo se registran facturas de órdenes con recepción');
		}
		if (!\Grocy\Helpers\DairyAccess::CanSeeBranch($order->branch_id !== null ? intval($order->branch_id) : null))
		{
			throw new \Exception('La orden pertenece a otra sucursal');
		}
		$number = trim(strval($data['invoice_number'] ?? ''));
		$date = strval($data['invoice_date'] ?? '');
		if ($number === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
		{
			throw new \Exception('Indique número y fecha de la factura');
		}
		$dup = $db->prepare("SELECT invoice_code FROM supplier_invoices WHERE supplier_id = :s AND invoice_number = :n");
		$dup->execute([':s' => $order->supplier_id, ':n' => $number]);
		if ($existing = $dup->fetchColumn())
		{
			throw new \Exception("La factura $number del proveedor ya está registrada ($existing)");
		}
		$tax = max(0, floatval($data['tax_amount'] ?? 0));
		$tolerance = defined('GROCY_INVOICE_PRICE_TOLERANCE_PCT') ? floatval(GROCY_INVOICE_PRICE_TOLERANCE_PCT) : 2;

		$orderItems = [];
		foreach ($db->query('SELECT * FROM procurement_order_items WHERE order_id = ' . $orderId)->fetchAll(\PDO::FETCH_OBJ) as $oi)
		{
			$orderItems[$oi->id] = $oi;
		}
		$prevStmt = $db->prepare("SELECT IFNULL(SUM(ii.invoiced_amount), 0) FROM supplier_invoice_items ii JOIN supplier_invoices i ON i.id = ii.invoice_id WHERE ii.order_item_id = :oi AND i.status != 'rejected'");

		$lines = [];
		$subtotal = 0.0;
		$risk = 0.0;
		foreach ($data['items'] ?? [] as $it)
		{
			$oiId = intval($it['order_item_id'] ?? 0);
			$qty = floatval($it['invoiced_amount'] ?? 0);
			$price = floatval($it['unit_price'] ?? 0);
			if (!isset($orderItems[$oiId]))
			{
				throw new \Exception('Línea de factura que no pertenece a la orden');
			}
			if ($qty < 0 || $price < 0)
			{
				throw new \Exception('Cantidades y precios no pueden ser negativos');
			}
			if ($qty == 0)
			{
				continue;
			}
			$oi = $orderItems[$oiId];
			$ordered = floatval($oi->approved_amount ?? $oi->suggested_amount);
			$received = floatval($oi->received_amount);
			$prevStmt->execute([':oi' => $oiId]);
			$prev = floatval($prevStmt->fetchColumn());
			$poPrice = $oi->unit_price !== null ? floatval($oi->unit_price) : null;

			$issues = [];
			$qtyDiff = round($prev + $qty - $received, 4);
			$lineRisk = 0.0;
			if ($qtyDiff > 0.0001)
			{
				$issues[] = 'qty_over_received';
				$lineRisk += min($qty, $qtyDiff) * $price;
			}
			if ($prev + $qty - $ordered > 0.0001)
			{
				$issues[] = 'qty_over_ordered';
			}
			$pricePct = ($poPrice !== null && $poPrice > 0) ? round(($price - $poPrice) / $poPrice * 100, 2) : null;
			if ($pricePct !== null && $pricePct > $tolerance)
			{
				$issues[] = 'price_over_po';
				$lineRisk += max(0, min($qty, max(0, $received - $prev))) * ($price - $poPrice);
			}
			$subtotal += $qty * $price;
			$risk += $lineRisk;
			$lines[] = [
				':oi' => $oiId, ':p' => $oi->product_id, ':o' => $ordered, ':r' => $received, ':pv' => $prev, ':q' => $qty, ':op' => $poPrice, ':ip' => $price,
				':qd' => $qtyDiff, ':pd' => $pricePct, ':risk' => round($lineRisk, 2), ':iss' => implode(',', $issues) ?: null
			];
		}
		if (count($lines) === 0)
		{
			throw new \Exception('La factura no tiene líneas con cantidad');
		}
		$status = array_filter($lines, fn($l) => $l[':iss'] !== null) ? 'discrepancy' : 'matched';

		$db->beginTransaction();
		try
		{
			$next = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM supplier_invoices')->fetchColumn());
			$code = 'FAC-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
			$db->prepare("INSERT INTO supplier_invoices (invoice_code, supplier_id, order_id, branch_id, invoice_number, invoice_date, subtotal, tax_amount, total, amount_at_risk, status, notes, created_by_user_id, created_timestamp)
				VALUES (:c, :s, :o, :b, :n, :d, :st, :t, :tot, :r, :status, :notes, :u, :now)")->execute([
				':c' => $code, ':s' => $order->supplier_id, ':o' => $orderId, ':b' => $order->branch_id, ':n' => $number, ':d' => $date,
				':st' => round($subtotal, 2), ':t' => round($tax, 2), ':tot' => round($subtotal + $tax, 2), ':r' => round($risk, 2), ':status' => $status,
				':notes' => trim(strval($data['notes'] ?? '')) ?: null, ':u' => $userId, ':now' => date('Y-m-d H:i:s')
			]);
			$id = intval($db->lastInsertId());
			$ins = $db->prepare('INSERT INTO supplier_invoice_items (invoice_id, order_item_id, product_id, ordered_amount, received_amount, previously_invoiced, invoiced_amount, order_unit_price, invoice_unit_price, qty_difference, price_difference_pct, amount_at_risk, issues)
				VALUES (:id, :oi, :p, :o, :r, :pv, :q, :op, :ip, :qd, :pd, :risk, :iss)');
			foreach ($lines as $l)
			{
				$ins->execute($l + [':id' => $id]);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
		return ['id' => $id, 'invoice_code' => $code, 'status' => $status, 'amount_at_risk' => round($risk, 2), 'total' => round($subtotal + $tax, 2)];
	}

	public function DecideInvoice(int $id, bool $approve, string $reason, int $userId): void
	{
		$db = $this->Db();
		$inv = $db->query('SELECT * FROM supplier_invoices WHERE id = ' . $id)->fetch(\PDO::FETCH_OBJ);
		if (!$inv || $inv->status !== 'discrepancy')
		{
			throw new \Exception('Solo se deciden facturas con diferencias pendientes');
		}
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo de la decisión');
		}
		if (intval($inv->created_by_user_id) === $userId && !(defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL))
		{
			throw new \Exception('Segregación de funciones: quien registró la factura no puede aprobarla ni rechazarla');
		}
		$db->prepare('UPDATE supplier_invoices SET status = :s, decision_reason = :r, decided_by_user_id = :u, decided_timestamp = :now WHERE id = :id')->execute([
			':s' => $approve ? 'approved_with_difference' : 'rejected', ':r' => trim($reason), ':u' => $userId, ':now' => date('Y-m-d H:i:s'), ':id' => $id
		]);
	}

	public function GetInvoices(?int $branchId = null, int $limit = 50): array
	{
		$sql = "SELECT i.*, o.order_code, IFNULL(sl.name, 'Proveedor #' || i.supplier_id) AS supplier_name,
				(SELECT COUNT(*) FROM supplier_invoice_items ii WHERE ii.invoice_id = i.id AND ii.issues IS NOT NULL) AS issue_lines
			FROM supplier_invoices i JOIN procurement_orders o ON o.id = i.order_id LEFT JOIN shopping_locations sl ON sl.id = i.supplier_id";
		$params = [];
		if ($branchId)
		{
			$sql .= ' WHERE i.branch_id = :b';
			$params[':b'] = $branchId;
		}
		$stmt = $this->Db()->prepare($sql . ' ORDER BY i.id DESC LIMIT ' . max(1, $limit));
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function GetInvoiceItems(int $invoiceId): array
	{
		return $this->Db()->query("SELECT ii.*, IFNULL(p.name, 'Producto #' || ii.product_id) AS product_name FROM supplier_invoice_items ii LEFT JOIN products p ON p.id = ii.product_id WHERE ii.invoice_id = " . $invoiceId . ' ORDER BY ii.id')->fetchAll(\PDO::FETCH_ASSOC);
	}
}
