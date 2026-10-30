<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Etapa 32 — Ingesta de ventas de POS externos (CSV, webhook/API). Un solo camino para todos los canales:
 * venta normalizada → validación (sucursal, moneda, zona horaria, productos) → SalesService::CreateSale (stock FEFO,
 * kardex, costo) → indicadores y pronóstico laboral (leen `sales`). Exactamente una vez por (origen, id externo):
 * reenvío idéntico = duplicado; mismo id con otro contenido = conflicto (no se toca lo importado); fila «processing»
 * huérfana tras un corte se recupera por el número de venta determinista. Devolución/anulación total → CancelSale
 * (reintegra stock). Nunca se inventan ventas: un conector de extracción sin credenciales queda «pendiente».
 */
class PosIngestService extends BaseService
{
	const STALE_SECONDS = 120;
	const KINDS = ['sale', 'refund', 'void'];
	const CSV_COLUMNS = [
		'external_id' => ['external_id', 'ticket', 'id_ticket', 'transaccion'], 'kind' => ['kind', 'type', 'tipo'],
		'original_external_id' => ['original_external_id', 'original_ticket', 'ticket_original'], 'branch' => ['branch', 'branch_code', 'branch_id', 'sucursal'],
		'occurred_at' => ['occurred_at', 'datetime', 'fecha', 'fecha_hora'], 'payment_method' => ['payment_method', 'metodo_pago', 'pago'],
		'currency' => ['currency', 'moneda'], 'barcode' => ['barcode', 'codigo_barras', 'ean'], 'sku' => ['sku'], 'product_id' => ['product_id', 'producto_id'],
		'amount' => ['amount', 'quantity', 'cantidad'], 'unit_price' => ['unit_price', 'precio_unitario', 'precio'], 'tax_amount' => ['tax', 'tax_amount', 'impuesto'],
		'discount_amount' => ['discount', 'discount_amount', 'descuento']
	];

	private static function Cfg(string $name, $default)
	{
		return defined('GROCY_' . $name) ? constant('GROCY_' . $name) : $default;
	}

	private function Q(string $sql, array $p = []): \PDOStatement
	{
		$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$st->execute($p);
		return $st;
	}

	public static function ValidSource(string $s): string
	{
		$s = strtolower(trim($s));
		if (!preg_match('/^[a-z0-9_-]{2,30}$/', $s))
		{
			throw new \Exception('Origen POS inválido (2–30 letras, números, - o _)');
		}
		return $s;
	}

	public static function SaleNumber(string $source, string $externalId): string
	{
		return 'POS-' . strtoupper($source) . '-' . $externalId;
	}

	// Fecha del POS → hora local del servidor. Con zona explícita (ISO 8601) se convierte; sin zona se asume POS_TIMEZONE.
	public static function LocalTime(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '')
		{
			throw new \Exception('Fecha/hora requerida');
		}
		$tz = trim(strval(self::Cfg('POS_TIMEZONE', ''))) ?: date_default_timezone_get();
		try
		{
			$hasZone = (bool)preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $raw);
			$dt = new \DateTime($raw, new \DateTimeZone($tz));
		}
		catch (\Throwable $e)
		{
			throw new \Exception("Fecha/hora inválida: $raw");
		}
		if (!$hasZone && !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $raw))
		{
			throw new \Exception("Fecha/hora inválida (use AAAA-MM-DD HH:MM[:SS] o ISO 8601): $raw");
		}
		$dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		if ($dt->getTimestamp() > time() + 600)
		{
			throw new \Exception('Fecha/hora en el futuro: ' . $dt->format('Y-m-d H:i:s'));
		}
		return $dt->format('Y-m-d H:i:s');
	}

	private function ResolveBranch($v): int
	{
		$v = trim(strval($v ?? ''));
		if ($v === '')
		{
			throw new \Exception('Sucursal requerida');
		}
		$id = ctype_digit($v) ? $this->Q('SELECT id FROM branches WHERE id = :v', [':v' => intval($v)])->fetchColumn() : false;
		if ($id === false)
		{
			$id = $this->Q('SELECT id FROM branches WHERE UPPER(code) = UPPER(:v) OR name = :v', [':v' => $v])->fetchColumn();
		}
		if ($id === false)
		{
			throw new \Exception("Sucursal desconocida: $v");
		}
		if (!DairyAccess::CanSeeBranch(intval($id)))
		{
			throw new \Exception("Sin acceso a la sucursal $v");
		}
		return intval($id);
	}

	private function ResolveProduct(array $it): int
	{
		if (!empty($it['product_id']) && ($id = $this->Q('SELECT id FROM products WHERE id = :v', [':v' => intval($it['product_id'])])->fetchColumn()) !== false)
		{
			return intval($id);
		}
		if (!empty($it['barcode']) && ($id = $this->Q('SELECT product_id FROM product_barcodes WHERE barcode = :v', [':v' => trim(strval($it['barcode']))])->fetchColumn()) !== false)
		{
			return intval($id);
		}
		if (!empty($it['sku']) && ($id = $this->Q('SELECT id FROM products WHERE sku = :v', [':v' => trim(strval($it['sku']))])->fetchColumn()) !== false)
		{
			return intval($id);
		}
		throw new \Exception('Producto no encontrado: ' . ($it['barcode'] ?? $it['sku'] ?? ('#' . ($it['product_id'] ?? '?'))));
	}

	// Valida y traduce una venta externa a los datos que espera CreateSale (sin escribir)
	private function Prepare(string $source, array $s): array
	{
		$kind = strtolower(trim(strval($s['kind'] ?? 'sale'))) ?: 'sale';
		if (!in_array($kind, self::KINDS, true))
		{
			throw new \Exception("Tipo inválido: $kind (sale, refund, void)");
		}
		$currency = strtoupper(trim(strval($s['currency'] ?? '')));
		$own = strtoupper(strval(self::Cfg('CURRENCY', 'USD')));
		if ($currency !== '' && $currency !== $own)
		{
			throw new \Exception("Moneda $currency distinta de la configurada ($own): no se convierte sin tipo de cambio autorizado");
		}
		$out = ['kind' => $kind, 'branch_id' => $this->ResolveBranch($s['branch_id'] ?? $s['branch'] ?? $s['branch_code'] ?? null), 'occurred_at' => self::LocalTime(strval($s['occurred_at'] ?? ''))];
		$items = is_array($s['items'] ?? null) ? $s['items'] : [];
		if ($kind !== 'sale')
		{
			$orig = trim(strval($s['original_external_id'] ?? ''));
			if ($orig === '')
			{
				throw new \Exception('Devolución/anulación sin ticket original (original_external_id)');
			}
			$out['original_external_id'] = $orig;
			$out['refund_total'] = $items ? round(array_sum(array_map(fn($i) => floatval($i['amount'] ?? 0) * floatval($i['unit_price'] ?? 0), $items)), 2) : null;
			return $out;
		}
		if (!$items)
		{
			throw new \Exception('Venta sin productos');
		}
		$lines = [];
		foreach ($items as $n => $it)
		{
			$amount = floatval($it['amount'] ?? 0);
			$price = floatval($it['unit_price'] ?? -1);
			if ($amount <= 0)
			{
				throw new \Exception('Línea ' . ($n + 1) . ': cantidad debe ser > 0 (las devoluciones van como tipo refund)');
			}
			if ($price < 0)
			{
				throw new \Exception('Línea ' . ($n + 1) . ': precio unitario inválido');
			}
			$lines[] = ['product_id' => $this->ResolveProduct($it), 'amount' => $amount, 'unit_price' => round($price, 4)];
		}
		$pm = strtolower(trim(strval($s['payment_method'] ?? ''))) ?: 'unknown';
		$out['sale'] = ['branch_id' => $out['branch_id'], 'payment_method' => mb_substr($pm, 0, 30), 'notes' => "POS externo $source", 'items' => $lines,
			'_external' => ['sale_number' => self::SaleNumber($source, strval($s['external_id'])), 'timestamp' => $out['occurred_at'],
				'tax_amount' => round(floatval($s['tax_amount'] ?? 0), 2), 'discount_amount' => round(floatval($s['discount_amount'] ?? 0), 2)]];
		return $out;
	}

	private function Execute(string $source, array $p, int $userId): int
	{
		if ($p['kind'] === 'sale')
		{
			return intval(SalesService::GetInstance()->CreateSale($p['sale'], $userId)->id);
		}
		$orig = $this->Q("SELECT * FROM pos_external_sales WHERE source = :s AND external_id = :e AND kind = 'sale'", [':s' => $source, ':e' => $p['original_external_id']])->fetch(\PDO::FETCH_ASSOC);
		if (!$orig || $orig['status'] === 'failed' || !$orig['sale_id'])
		{
			throw new \Exception("Ticket original {$p['original_external_id']} aún no importado: reintente cuando llegue");
		}
		if ($orig['status'] === 'refunded')
		{
			throw new \Exception("Ticket original {$p['original_external_id']} ya fue devuelto/anulado");
		}
		$sale = $this->Q('SELECT id, total_amount, branch_id FROM sales WHERE id = :id', [':id' => $orig['sale_id']])->fetch(\PDO::FETCH_ASSOC);
		if (intval($sale['branch_id']) !== $p['branch_id'])
		{
			throw new \Exception('La devolución es de otra sucursal que la venta original');
		}
		if ($p['refund_total'] !== null && abs($p['refund_total'] - floatval($sale['total_amount'])) > 0.01)
		{
			throw new \Exception('Devolución parcial (' . $p['refund_total'] . ' de ' . $sale['total_amount'] . '): no soportada por la integración; regístrela en DAIRY');
		}
		SalesService::GetInstance()->CancelSale(intval($sale['id']), $userId);
		$this->Q("UPDATE pos_external_sales SET status = 'refunded', updated_timestamp = datetime('now', 'localtime') WHERE id = :id", [':id' => $orig['id']]);
		return intval($sale['id']);
	}

	private static function Hash(array $s): string
	{
		$norm = function ($v) use (&$norm) {
			if (is_array($v))
			{
				if (array_keys($v) !== range(0, count($v) - 1))
				{
					ksort($v);
				}
				return array_map($norm, $v);
			}
			return is_numeric($v) ? strval(0 + $v) : trim(strval($v));
		};
		return sha1(json_encode($norm($s), JSON_UNESCAPED_UNICODE));
	}

	// Procesa una venta externa; devuelve ['external_id', 'status' => imported|duplicate|failed|valid, 'sale_id', 'error']
	public function ProcessOne(string $source, array $s, ?int $batchId, int $userId, bool $dryRun = false): array
	{
		$ext = trim(strval($s['external_id'] ?? ''));
		$res = ['external_id' => $ext, 'status' => 'failed', 'sale_id' => null, 'error' => null];
		if ($ext === '' || mb_strlen($ext) > 80 || !preg_match('/^[\w.:\/#-]+$/u', $ext))
		{
			$res['error'] = 'external_id requerido (≤ 80, sin espacios)';
			return $res;
		}
		$s['external_id'] = $ext;
		$hash = self::Hash($s);
		$row = $this->Q('SELECT * FROM pos_external_sales WHERE source = :s AND external_id = :e', [':s' => $source, ':e' => $ext])->fetch(\PDO::FETCH_ASSOC);
		if ($row && $row['payload_hash'] !== $hash && $row['status'] !== 'failed')
		{
			$res['error'] = 'Conflicto: el ticket ya se importó con otro contenido; no se modifica (corrija en el POS con una devolución)';
			$res['sale_id'] = $row['sale_id'] ? intval($row['sale_id']) : null;
			return $res;
		}
		if ($row && in_array($row['status'], ['imported', 'refunded'], true))
		{
			return ['external_id' => $ext, 'status' => 'duplicate', 'sale_id' => $row['sale_id'] ? intval($row['sale_id']) : null, 'error' => null];
		}
		try
		{
			$p = $this->Prepare($source, $s);
		}
		catch (\Exception $e)
		{
			$res['error'] = $e->getMessage();
			if (!$dryRun)
			{
				$this->Record($row, $source, $ext, $s, $hash, $batchId, null, null, 'failed', null, $res['error']);
			}
			return $res;
		}
		if ($dryRun)
		{
			return ['external_id' => $ext, 'status' => 'valid', 'sale_id' => null, 'error' => null, 'kind' => $p['kind'], 'occurred_at' => $p['occurred_at']];
		}

		// Reserva la clave (UNIQUE): un envío simultáneo del mismo ticket no puede crear dos ventas
		if ($row && $row['status'] === 'processing')
		{
			if (strtotime($row['updated_timestamp']) > time() - self::STALE_SECONDS)
			{
				$res['error'] = 'Ticket en proceso por otro envío; reintente en unos minutos';
				return $res;
			}
			$saleId = $this->Q('SELECT id FROM sales WHERE sale_number = :n', [':n' => self::SaleNumber($source, $ext)])->fetchColumn();
			if ($saleId !== false && $p['kind'] === 'sale')
			{
				$this->Record($row, $source, $ext, $s, $hash, $batchId, $p['branch_id'], $p['occurred_at'], 'imported', intval($saleId), null);
				return ['external_id' => $ext, 'status' => 'imported', 'sale_id' => intval($saleId), 'error' => null, 'recovered' => true];
			}
		}
		if ($row)
		{
			$claimed = $this->Q("UPDATE pos_external_sales SET status = 'processing', attempts = attempts + 1, payload_json = :j, payload_hash = :h, kind = :k, updated_timestamp = datetime('now', 'localtime')
				WHERE id = :id AND status IN ('failed', 'processing') AND updated_timestamp = :u", [':j' => json_encode($s, JSON_UNESCAPED_UNICODE), ':h' => $hash, ':k' => $p['kind'], ':id' => $row['id'], ':u' => $row['updated_timestamp']])->rowCount();
		}
		else
		{
			$claimed = $this->Q("INSERT OR IGNORE INTO pos_external_sales (batch_id, source, external_id, kind, branch_id, occurred_at, payload_json, payload_hash, status) VALUES (:b, :s, :e, :k, :br, :o, :j, :h, 'processing')", [
				':b' => $batchId, ':s' => $source, ':e' => $ext, ':k' => $p['kind'], ':br' => $p['branch_id'], ':o' => $p['occurred_at'], ':j' => json_encode($s, JSON_UNESCAPED_UNICODE), ':h' => $hash
			])->rowCount();
		}
		if (!$claimed)
		{
			$again = $this->Q('SELECT status, sale_id FROM pos_external_sales WHERE source = :s AND external_id = :e', [':s' => $source, ':e' => $ext])->fetch(\PDO::FETCH_ASSOC);
			if ($again && in_array($again['status'], ['imported', 'refunded'], true))
			{
				return ['external_id' => $ext, 'status' => 'duplicate', 'sale_id' => intval($again['sale_id']), 'error' => null];
			}
			$res['error'] = 'Ticket en proceso por otro envío; reintente en unos minutos';
			return $res;
		}
		$row = $this->Q('SELECT * FROM pos_external_sales WHERE source = :s AND external_id = :e', [':s' => $source, ':e' => $ext])->fetch(\PDO::FETCH_ASSOC);
		try
		{
			$saleId = $this->Execute($source, $p, $userId);
			$this->Record($row, $source, $ext, $s, $hash, $batchId, $p['branch_id'], $p['occurred_at'], 'imported', $saleId, null);
			return ['external_id' => $ext, 'status' => 'imported', 'sale_id' => $saleId, 'error' => null, 'kind' => $p['kind']];
		}
		catch (\Exception $e)
		{
			$this->Record($row, $source, $ext, $s, $hash, $batchId, $p['branch_id'], $p['occurred_at'], 'failed', null, $e->getMessage());
			$res['error'] = $e->getMessage();
			return $res;
		}
	}

	private function Record($row, string $source, string $ext, array $s, string $hash, ?int $batchId, ?int $branchId, ?string $at, string $status, ?int $saleId, ?string $error): void
	{
		$p = [':j' => json_encode($s, JSON_UNESCAPED_UNICODE), ':h' => $hash, ':k' => strtolower(strval($s['kind'] ?? 'sale')) ?: 'sale', ':br' => $branchId, ':o' => $at, ':st' => $status, ':sid' => $saleId, ':err' => $error !== null ? mb_substr($error, 0, 500) : null, ':b' => $batchId];
		if ($row)
		{
			$this->Q("UPDATE pos_external_sales SET payload_json = :j, payload_hash = :h, kind = :k, branch_id = IFNULL(:br, branch_id), occurred_at = IFNULL(:o, occurred_at), status = :st, sale_id = :sid, error = :err,
				batch_id = IFNULL(:b, batch_id), updated_timestamp = datetime('now', 'localtime') WHERE id = :id", $p + [':id' => $row['id']]);
		}
		else
		{
			$this->Q('INSERT OR IGNORE INTO pos_external_sales (batch_id, source, external_id, kind, branch_id, occurred_at, payload_json, payload_hash, status, sale_id, error) VALUES (:b, :s, :e, :k, :br, :o, :j, :h, :st, :sid, :err)', $p + [':s' => $source, ':e' => $ext]);
		}
	}

	// Lote: CSV o webhook. Las devoluciones se procesan después de las ventas del mismo lote.
	public function Ingest(string $source, array $sales, string $channel, ?string $fileName, int $userId, bool $dryRun = false): array
	{
		$source = self::ValidSource($source);
		if (count($sales) === 0)
		{
			throw new \Exception('No hay ventas para importar');
		}
		if (count($sales) > 5000)
		{
			throw new \Exception('Máximo 5 000 ventas por envío');
		}
		usort($sales, fn($a, $b) => (strtolower(strval($a['kind'] ?? 'sale')) === 'sale' ? 0 : 1) <=> (strtolower(strval($b['kind'] ?? 'sale')) === 'sale' ? 0 : 1));
		$batchId = null;
		if (!$dryRun)
		{
			$this->Q('INSERT INTO pos_ingest_batches (source, channel, file_name, received, user_id) VALUES (:s, :c, :f, :r, :u)', [':s' => $source, ':c' => $channel, ':f' => $fileName, ':r' => count($sales), ':u' => $userId]);
			$batchId = intval($this->Q('SELECT last_insert_rowid()')->fetchColumn());
		}
		$results = [];
		foreach ($sales as $s)
		{
			$results[] = $this->ProcessOne($source, is_array($s) ? $s : [], $batchId, $userId, $dryRun);
		}
		$count = fn($st) => count(array_filter($results, fn($r) => $r['status'] === $st));
		$sum = ['batch_id' => $batchId, 'source' => $source, 'channel' => $channel, 'dry_run' => $dryRun, 'received' => count($sales), 'imported' => $count('imported'),
			'valid' => $count('valid'), 'duplicates' => $count('duplicate'), 'failed' => $count('failed'), 'results' => $results];
		if ($batchId)
		{
			$this->Q('UPDATE pos_ingest_batches SET imported = :i, duplicates = :d, failed = :f WHERE id = :id', [':i' => $sum['imported'], ':d' => $sum['duplicates'], ':f' => $sum['failed'], ':id' => $batchId]);
		}
		return $sum;
	}

	// CSV: una fila por línea de producto; las filas con el mismo ticket forman una venta (impuesto/descuento se suman)
	public static function ParseCsv(string $text): array
	{
		$text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
		$lines = preg_split('/\r\n|\n|\r/', trim($text));
		if (count($lines) < 2)
		{
			throw new \Exception('CSV vacío o sin filas de datos');
		}
		$delim = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
		$head = array_map(fn($h) => strtolower(trim($h)), str_getcsv($lines[0], $delim, '"', ''));
		$map = [];
		foreach (self::CSV_COLUMNS as $key => $aliases)
		{
			foreach ($aliases as $a)
			{
				if (($i = array_search($a, $head, true)) !== false)
				{
					$map[$key] = $i;
					break;
				}
			}
		}
		foreach (['external_id', 'branch', 'occurred_at'] as $req)
		{
			if (!isset($map[$req]))
			{
				throw new \Exception("Falta la columna obligatoria «{$req}» (alias: " . implode(', ', self::CSV_COLUMNS[$req]) . ')');
			}
		}
		$sales = [];
		for ($n = 1; $n < count($lines); $n++)
		{
			if (trim($lines[$n]) === '')
			{
				continue;
			}
			$c = str_getcsv($lines[$n], $delim, '"', '');
			$v = fn($k) => isset($map[$k]) ? trim(strval($c[$map[$k]] ?? '')) : '';
			$num = fn($k) => $v($k) === '' ? 0.0 : floatval(str_replace(',', '.', $v($k)));
			$id = $v('external_id');
			if (!isset($sales[$id]))
			{
				$sales[$id] = ['external_id' => $id, 'kind' => $v('kind') ?: 'sale', 'branch' => $v('branch'), 'occurred_at' => $v('occurred_at'), 'payment_method' => $v('payment_method'),
					'currency' => $v('currency'), 'tax_amount' => 0.0, 'discount_amount' => 0.0, 'items' => []];
				if ($v('original_external_id') !== '')
				{
					$sales[$id]['original_external_id'] = $v('original_external_id');
				}
			}
			$sales[$id]['tax_amount'] += $num('tax_amount');
			$sales[$id]['discount_amount'] += $num('discount_amount');
			if ($v('barcode') !== '' || $v('sku') !== '' || $v('product_id') !== '')
			{
				$sales[$id]['items'][] = array_filter(['barcode' => $v('barcode'), 'sku' => $v('sku'), 'product_id' => $v('product_id')], fn($x) => $x !== '') + ['amount' => $num('amount'), 'unit_price' => $v('unit_price') === '' ? -1 : $num('unit_price')];
			}
		}
		return array_values($sales);
	}

	public function Retry(int $id, int $userId): array
	{
		$row = $this->Q('SELECT * FROM pos_external_sales WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			throw new \Exception('Venta externa no encontrada');
		}
		if ($row['status'] !== 'failed')
		{
			throw new \Exception('Solo se reintentan ventas fallidas');
		}
		return $this->ProcessOne($row['source'], json_decode($row['payload_json'], true) ?: [], null, $userId);
	}

	public function GetExternalBranch(int $id): ?int
	{
		$b = $this->Q('SELECT branch_id FROM pos_external_sales WHERE id = :id', [':id' => $id])->fetchColumn();
		if ($b === false)
		{
			throw new \Exception('Venta externa no encontrada');
		}
		return $b !== null ? intval($b) : null;
	}

	public static function WebhookSecret(): string
	{
		return trim(strval(self::Cfg('POS_WEBHOOK_SECRET', '')));
	}

	// Firma opcional: si hay secreto configurado, X-Dairy-Signature: sha256=<hmac del cuerpo crudo>
	public static function VerifySignature(string $rawBody, string $header): void
	{
		$secret = self::WebhookSecret();
		if ($secret === '')
		{
			return;
		}
		$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
		if (!hash_equals($expected, trim($header)))
		{
			throw new \Exception('Firma del webhook inválida (X-Dairy-Signature)');
		}
	}

	// Estado de la integración: nunca se declara conectado algo que no lo está
	public function Status(?array $branchIds): array
	{
		$provider = trim(strval(self::Cfg('POS_CONNECTOR', '')));
		$url = trim(strval(self::Cfg('POS_API_URL', '')));
		$key = trim(strval(self::Cfg('POS_API_KEY', '')));
		if ($provider === '')
		{
			$pull = ['state' => 'not_configured', 'message' => 'Sin conector de extracción configurado (POS_CONNECTOR). Use CSV o el webhook.'];
		}
		elseif ($url === '' || $key === '')
		{
			$pull = ['state' => 'pending_credentials', 'message' => "Conector «{$provider}» declarado sin URL/credenciales autorizadas: inactivo."];
		}
		else
		{
			$pull = ['state' => 'pending_validation', 'message' => "Credenciales de «{$provider}» presentes; la extracción programada requiere validar la API del proveedor antes de activarse. No se sincroniza."];
		}
		$bf = $branchIds === null ? '' : ' AND branch_id IN (' . (implode(',', array_map('intval', $branchIds)) ?: '0') . ')';
		$sources = $this->Q("SELECT source, COUNT(*) total, SUM(status IN ('imported','refunded')) imported, SUM(status = 'failed') failed, MAX(CASE WHEN status IN ('imported','refunded') THEN occurred_at END) last_sale_at, MAX(updated_timestamp) last_activity
			FROM pos_external_sales WHERE 1 = 1 $bf GROUP BY source ORDER BY last_activity DESC")->fetchAll(\PDO::FETCH_ASSOC);
		$failed = $this->Q("SELECT id, source, external_id, kind, branch_id, occurred_at, error, attempts, updated_timestamp FROM pos_external_sales WHERE status = 'failed' $bf ORDER BY updated_timestamp DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
		$batches = $this->Q('SELECT * FROM pos_ingest_batches ORDER BY id DESC LIMIT 10')->fetchAll(\PDO::FETCH_ASSOC);
		return [
			'connected' => count($sources) > 0,
			'pull_connector' => ['provider' => $provider ?: null] + $pull,
			'webhook' => ['endpoint' => '/api/pos/ingest', 'signature_required' => self::WebhookSecret() !== '', 'auth' => 'GROCY-API-KEY con permiso POS_INTEGRATION'],
			'timezone' => trim(strval(self::Cfg('POS_TIMEZONE', ''))) ?: date_default_timezone_get(),
			'currency' => strval(self::Cfg('CURRENCY', 'USD')),
			'sources' => $sources,
			'failed' => $failed,
			'batches' => $branchIds === null ? $batches : [],
			'csv_columns' => array_map(fn($a) => $a[0], self::CSV_COLUMNS)
		];
	}
}
