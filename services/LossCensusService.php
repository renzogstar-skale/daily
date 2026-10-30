<?php

namespace Grocy\Services;

/**
 * Etapa 36: metodología del XXI Censo Nacional de Mermas (Fenalco/Sensormatic, 2023, datos 2022) sobre la fuente
 * única de eventos de LossIntelligenceService::Events (sin otra fuente de pérdida → sin doble conteo).
 *
 * Jerarquía (cada evento cae en UNA sola rama según su clase):
 *   operativa conocida = operativa · desconocida = investigación + robo interno + robo externo (incluye fraude POS y
 *   electrónico, que son causales de esas clases) · comercial · ajustes · averías y desperdicios.
 * Vista «método Censo»: conocida = operativa + comercial + averías; desconocida = ajustes + investigación + robos.
 * Las cifras del Censo solo se muestran como benchmark identificado (loss_benchmarks), nunca como datos de DAIRY.
 */
class LossCensusService extends BaseService
{
	const BRANCHES = [
		'conocida' => ['label' => 'Merma operativa conocida', 'classes' => ['operativa']],
		'desconocida' => ['label' => 'Merma operativa desconocida / en investigación', 'classes' => ['investigacion', 'robo_interno', 'robo_externo']],
		'comercial' => ['label' => 'Merma comercial', 'classes' => ['comercial']],
		'ajuste' => ['label' => 'Ajustes contables y de inventario', 'classes' => ['ajuste']],
		'averia' => ['label' => 'Averías y desperdicios', 'classes' => ['averia']],
	];

	// Código => [nombre, área responsable, cómo se detecta automáticamente]
	const ADMIN_CATEGORIES = [
		'DOC' => ['Elaboración y registro de documentos', 'Administración de tienda', 'Recepciones sin documento del proveedor; mermas y compras registradas sin costo'],
		'REC' => ['Recepción de mercancía', 'Recepción', 'Entregado ≠ aceptado + rechazado; rechazo sin motivo'],
		'BAJ' => ['Bajas y desperdicios no registrados', 'Operaciones de tienda', 'Lotes vencidos hace más de 3 días aún en existencias'],
		'CON' => ['Conteos físicos incorrectos', 'Control de inventario', 'Conteo ejecutado con diferencia y sin ajuste conciliado'],
		'PRE' => ['Variaciones de precios', 'Comercial / Precios', 'Gestionado en «Precios y marcación» (no se duplica aquí)'],
		'INT' => ['Integridad de sistemas', 'Sistemas', 'Existencias negativas; merma aprobada sin descuento de inventario'],
		'DES' => ['Despacho a destino equivocado', 'Logística', 'Traslado recibido en ubicación de otra sucursal; tránsito > 7 días sin recibir'],
		'MAR' => ['Marcación incorrecta de productos', 'Comercial / Precios', 'Mismo código de barras en más de un producto'],
		'CAJ' => ['Errores en caja registradora', 'Cajas', 'Venta con total ≠ subtotal + impuesto − descuento; transacción POS rechazada'],
		'DDS' => ['Dobles despachos', 'Logística', 'Traslado repetido (mismo origen, destino y productos en 24 h); documento de proveedor recibido dos veces'],
		'EPC' => ['Errores EPC / RFID', 'Control de inventario', 'Requiere lector RFID: se registra manualmente o desde «Precios y marcación»'],
	];

	const ADMIN_FLOW = ['detected', 'evidence', 'reconciled', 'approved', 'corrected', 'verified', 'closed'];
	const ADMIN_STATUS = ['detected' => 'Detectado', 'evidence' => 'Con evidencia', 'reconciled' => 'Conciliado', 'approved' => 'Aprobado',
		'corrected' => 'Corregido', 'verified' => 'Verificado', 'closed' => 'Cerrado', 'dismissed' => 'Descartado'];

	const MODALITIES = [
		'interno' => ['complicidad_externa' => 'Complicidad externa', 'camuflada' => 'Mercancía camuflada en ropa o maletines', 'cajero_no_factura' => 'Cajero: sale sin facturar',
			'consumo_tienda' => 'Consumo de alimentos en tienda', 'entra_no_factura' => 'Mercancía que no entra pero se factura', 'complicidad_seguridad' => 'Complicidad del personal de seguridad',
			'cambio_codigo' => 'Cambio de códigos', 'oculta_mercancia' => 'Ocultar mercancía en otras mercancías o basuras', 'cambio_empaque' => 'Cambio de empaques',
			'fraude_documentos' => 'Fraude de documentos', 'otra' => 'Otra'],
		'externo' => ['bolsa_aluminio' => 'Bolsas de aluminio', 'bolsa_almacen' => 'Bolsa plástica del almacén', 'consumo_tienda' => 'Consumo de alimentos en tienda',
			'prendas_bolsos' => 'Ocultar en prendas o bolsos', 'cambio_codigo' => 'Cambio de código de la mercancía', 'desactivador' => 'Uso de desactivadores',
			'cambio_empaque' => 'Cambio de empaques', 'complicidad_interna' => 'Complicidad con personal interno', 'devolucion_fraudulenta' => 'Devolución fraudulenta',
			'documentos_valores' => 'Fraude con documentos valores', 'banda' => 'Robo organizado (banda)', 'otra' => 'Otra'],
		'fraude_pos' => ['devolucion_ficticia' => 'Devolución ficticia', 'monedero_tarjeta_regalo' => 'Monedero electrónico o tarjeta regalo', 'bono_descuento' => 'Bono de descuento',
			'entrega_sin_registro' => 'Entrega sin registro', 'anulacion' => 'Anulación indebida', 'otra' => 'Otra'],
		'fraude_electronico' => ['tarjeta_credito' => 'Tarjeta de crédito', 'transferencia_falsa' => 'Transferencia falsa', 'doble_despacho' => 'Doble despacho',
			'clonacion_factura' => 'Clonación de facturas', 'tienda_virtual' => 'Tienda virtual', 'otra' => 'Otra'],
	];
	const CHANNELS = ['cctv' => 'Videovigilancia (CCTV)', 'seguridad' => 'Personal de seguridad', 'eas' => 'Alarma / antena EAS', 'inventario' => 'Inventario o auditoría',
		'analitica_pos' => 'Analítica POS / datos', 'linea_etica' => 'Denuncia / línea ética', 'empleado' => 'Reporte de empleado', 'cliente' => 'Reporte de cliente', 'otro' => 'Otro'];
	const DESTINATIONS = ['destruccion' => 'Destrucción', 'donacion' => 'Donación', 'compostaje' => 'Compostaje / aprovechamiento orgánico', 'alimento_animal' => 'Alimento animal',
		'devolucion_proveedor' => 'Devolución a proveedor', 'reproceso' => 'Reproceso / transformación', 'reciclaje' => 'Reciclaje'];
	const CONTROL_TYPES = ['cctv' => 'Videovigilancia', 'eas' => 'Antenas / etiquetas EAS', 'rfid' => 'RFID', 'conteo_ciclico' => 'Conteos cíclicos', 'auditoria_pos' => 'Auditoría POS',
		'guardia' => 'Seguridad física', 'capacitacion' => 'Capacitación', 'cadena_frio' => 'Monitoreo de frío', 'fefo' => 'Rotación FEFO / rebajas', 'otro' => 'Otro'];
	// Familias de perecederos: palabras clave sobre el nombre del departamento (grupo de producto)
	const FAMILIES = [
		'fruver' => ['Fruver', '/fruver|fruta|verdura|hortaliza|legumbre/iu'],
		// Palabras completas para «res», «ave» y «pan»: evita que «Yogures», «Llaves» o «Panela» caigan en otra familia
		'carnes' => ['Carnes y pollo', '/carne|carnic|pollo|\bres\b|cerdo|\baves?\b|embutido|charcut|pescado|marisco/iu'],
		'lacteos' => ['Lácteos', '/l[aá]cteo|leche|queso|yogur|mantequilla|crema|refrigerad/iu'],
		'panaderia' => ['Panadería', '/\bpan\b|panader|bizcoch|pasteler|reposter/iu'],
	];

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

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	private static function Pct(float $part, float $whole): ?float
	{
		return $whole != 0.0 ? round($part / $whole * 100, 2) : null;
	}

	private static function Sum(array $ev, ?callable $w = null, string $col = 'cost'): float
	{
		$s = 0.0;
		foreach ($ev as $e)
		{
			if ($w === null || $w($e))
			{
				$s += $e[$col];
			}
		}
		return $s;
	}

	private function LI(): LossIntelligenceService
	{
		return LossIntelligenceService::GetInstance();
	}

	private static function Drill(array $f, string $from, string $to, array $extra, string $hash): string
	{
		return '?' . http_build_query(array_filter(array_merge(['branch_id' => $f['branch_id'] ?? null, 'start_date' => $from, 'end_date' => $to], $extra), fn($v) => $v !== null && $v !== '')) . $hash;
	}

	/* ============================================================ Benchmarks y metas */

	public function Benchmarks(?string $metric = null): array
	{
		$rows = $this->Rows('SELECT * FROM loss_benchmarks' . ($metric ? ' WHERE metric = :m' : '') . ' ORDER BY metric, id', $metric ? [':m' => $metric] : []);
		foreach ($rows as &$r)
		{
			$r['value'] = floatval($r['value']);
			$r['label'] = $r['source'] . ' · edición ' . $r['edition'] . ' · datos ' . $r['data_year'];
		}
		return $rows;
	}

	private function Bench(string $metric, string $segment): ?float
	{
		$v = $this->Val('SELECT value FROM loss_benchmarks WHERE metric = :m AND segment = :s ORDER BY data_year DESC LIMIT 1', [':m' => $metric, ':s' => $segment]);
		return $v === false ? null : floatval($v);
	}

	/**
	 * Meta vigente: la de la sucursal (si hay filtro y existe) y si no la global. null = el negocio no la ha definido.
	 */
	public function Target(int $year, ?int $branchId = null, ?string $department = null): ?array
	{
		$tries = [];
		if ($department !== null)
		{
			$tries[] = ['department', $department];
		}
		if ($branchId)
		{
			$tries[] = ['branch', strval($branchId)];
		}
		$tries[] = ['global', ''];
		foreach ($tries as [$t, $v])
		{
			$r = $this->Rows('SELECT * FROM loss_targets WHERE year = :y AND scope_type = :t AND scope_value = :v', [':y' => $year, ':t' => $t, ':v' => $v]);
			if ($r)
			{
				$r = $r[0];
				return ['scope' => $t, 'scope_value' => $v, 'tolerated_pct' => $r['tolerated_pct'] !== null ? floatval($r['tolerated_pct']) : null,
					'budget_pct' => $r['budget_pct'] !== null ? floatval($r['budget_pct']) : null, 'basis' => $r['basis'], 'source' => $r['source']];
			}
		}
		return null;
	}

	public function Targets(): array
	{
		return $this->Rows('SELECT t.*, b.name AS branch_name FROM loss_targets t LEFT JOIN branches b ON t.scope_type = \'branch\' AND b.id = CAST(t.scope_value AS INTEGER) ORDER BY t.year DESC, t.scope_type, t.scope_value');
	}

	public function SaveTarget(array $d, int $userId): void
	{
		$year = intval($d['year'] ?? 0);
		if ($year < 2000 || $year > 2100)
		{
			throw new \Exception('Año inválido');
		}
		$type = strval($d['scope_type'] ?? 'global');
		if (!in_array($type, ['global', 'department', 'branch']))
		{
			throw new \Exception('Alcance inválido');
		}
		$val = $type === 'global' ? '' : trim(strval($d['scope_value'] ?? ''));
		if ($type !== 'global' && $val === '')
		{
			throw new \Exception('Indique la sucursal o el departamento');
		}
		$num = function ($k) use ($d) {
			if (!isset($d[$k]) || $d[$k] === '' || $d[$k] === null)
			{
				return null;
			}
			$v = floatval(str_replace(',', '.', strval($d[$k])));
			if ($v < 0 || $v > 100)
			{
				throw new \Exception('Los porcentajes deben estar entre 0 y 100');
			}
			return $v;
		};
		$tol = $num('tolerated_pct');
		$bud = $num('budget_pct');
		if ($tol === null && $bud === null)
		{
			throw new \Exception('Indique la merma tolerada o la presupuestada');
		}
		$basis = ($d['basis'] ?? 'cost') === 'sale' ? 'sale' : 'cost';
		$this->Db()->prepare("INSERT INTO loss_targets (year, scope_type, scope_value, tolerated_pct, budget_pct, basis, source, updated_by_user_id, updated_timestamp)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))
			ON CONFLICT (year, scope_type, scope_value) DO UPDATE SET tolerated_pct = excluded.tolerated_pct, budget_pct = excluded.budget_pct, basis = excluded.basis,
				source = excluded.source, updated_by_user_id = excluded.updated_by_user_id, updated_timestamp = excluded.updated_timestamp")
			->execute([$year, $type, $val, $tol, $bud, $basis, trim(strval($d['source'] ?? '')) ?: null, $userId]);
	}

	/* ============================================================ Fase 2: jerarquía, real vs. tolerada */

	public function Hierarchy(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$f2['cause'] = null;
		$ev = $li->Events($f2, $from, $to);
		[$sales, $gm] = $li->NetSales($f2, $from, $to);
		$causes = array_column($this->Rows('SELECT code, name FROM loss_causes'), 'name', 'code');
		$total = self::Sum($ev);
		$totalSale = self::Sum($ev, null, 'sale_value');
		$nodes = [];
		$assigned = 0;
		foreach (self::BRANCHES as $k => $b)
		{
			$rows = array_values(array_filter($ev, fn($e) => in_array($e['loss_class'], $b['classes'], true)));
			$assigned += count($rows);
			$children = [];
			foreach ($rows as $e)
			{
				$ck = $k === 'desconocida' ? $e['loss_class'] . ':' . (in_array($e['cause'], ['FPO', 'FEL']) ? $e['cause'] : '') : $e['cause'];
				if ($k === 'ajuste')
				{
					$ck = $e['cost'] >= 0 ? 'faltante' : 'sobrante';
				}
				$children[$ck] ??= ['key' => $ck, 'cost' => 0.0, 'sale_value' => 0.0, 'units' => 0.0, 'events' => 0];
				$children[$ck]['cost'] += $e['cost'];
				$children[$ck]['sale_value'] += $e['sale_value'];
				$children[$ck]['units'] += $e['units'];
				$children[$ck]['events']++;
			}
			$labels = ['investigacion:' => 'Bajo investigación (no es robo)', 'robo_interno:' => 'Robo interno confirmado', 'robo_externo:' => 'Robo externo confirmado',
				'robo_interno:FPO' => 'Fraude POS confirmado', 'robo_externo:FEL' => 'Fraude electrónico confirmado', 'faltante' => 'Faltantes ajustados', 'sobrante' => 'Sobrantes ajustados (resta)'];
			foreach ($children as &$c)
			{
				$c['label'] = $labels[$c['key']] ?? ($causes[$c['key']] ?? $c['key']);
				$c['share'] = self::Pct($c['cost'], $total);
				$c['cost'] = round($c['cost'], 2);
				$c['sale_value'] = round($c['sale_value'], 2);
			}
			unset($c);
			usort($children, fn($a, $b) => $b['cost'] <=> $a['cost']);
			$cost = self::Sum($rows);
			$sv = self::Sum($rows, null, 'sale_value');
			$nodes[$k] = ['key' => $k, 'label' => $b['label'], 'classes' => $b['classes'], 'cost' => round($cost, 2), 'sale_value' => round($sv, 2), 'units' => round(self::Sum($rows, null, 'units'), 2),
				'events' => count($rows), 'share' => self::Pct($cost, $total), 'pct_sales_cost' => self::Pct($cost, $sales), 'pct_sales_sale' => self::Pct($sv, $sales), 'children' => $children,
				'drill' => self::Drill($f, $from, $to, count($b['classes']) === 1 ? ['loss_class' => $b['classes'][0]] : [], $k === 'desconocida' ? '#tab-robo' : '#tab-clasificacion')];
		}
		$uids = array_column($ev, 'uid');
		$known = $nodes['conocida']['cost'] + $nodes['comercial']['cost'] + $nodes['averia']['cost'];
		$unknown = $nodes['ajuste']['cost'] + $nodes['desconocida']['cost'];
		$year = intval(substr($to, 0, 4));
		$target = $this->Target($year, $f['branch_id'] ?? null);
		$basisValue = ($target['basis'] ?? 'cost') === 'sale' ? $totalSale : $total;
		$realPct = self::Pct($basisValue, $sales);
		$tolValue = $target && $target['tolerated_pct'] !== null ? round($sales * $target['tolerated_pct'] / 100, 2) : null;
		$budValue = $target && $target['budget_pct'] !== null ? round($sales * $target['budget_pct'] / 100, 2) : null;
		return [
			'from' => $from, 'to' => $to, 'net_sales' => round($sales, 2), 'gross_margin' => round($gm, 2),
			'total' => ['cost' => round($total, 2), 'sale_value' => round($totalSale, 2), 'events' => count($ev),
				'pct_sales_cost' => self::Pct($total, $sales), 'pct_sales_sale' => self::Pct($totalSale, $sales)],
			'nodes' => array_values($nodes),
			// Consistencia: cada evento en una sola rama y la suma de ramas = total
			'consistency' => ['events' => count($ev), 'assigned' => $assigned, 'unique_ids' => count(array_unique($uids)) === count($uids),
				'sum_ok' => abs(array_sum(array_column($nodes, 'cost')) - round($total, 2)) < 0.05],
			'census_split' => ['known' => round($known, 2), 'unknown' => round($unknown, 2), 'known_pct' => self::Pct($known, $known + $unknown), 'unknown_pct' => self::Pct($unknown, $known + $unknown),
				'bench_known' => $this->Bench('composicion', 'conocida'), 'bench_unknown' => $this->Bench('composicion', 'desconocida')],
			'tolerance' => [
				'year' => $year, 'target' => $target, 'basis' => $target['basis'] ?? 'cost',
				'real_value' => round($basisValue, 2), 'real_pct' => $realPct,
				'tolerated_value' => $tolValue, 'budget_value' => $budValue,
				'excess_value' => $tolValue !== null ? round(max(0, $basisValue - $tolValue), 2) : null,
				'excess_pp' => $target && $target['tolerated_pct'] !== null && $realPct !== null ? round($realPct - $target['tolerated_pct'], 2) : null,
				'vs_budget_value' => $budValue !== null ? round($basisValue - $budValue, 2) : null,
				'bench' => ['real_sale' => $this->Bench('merma_real_venta', 'total'), 'tolerated_sale' => $this->Bench('merma_tolerada_venta', 'total'),
					'real_cost' => $this->Bench('merma_real_costo', 'total'), 'tolerated_cost' => $this->Bench('merma_tolerada_costo', 'total')],
			],
		];
	}

	/**
	 * Mensual del año: real ($ y % a costo y a venta), tolerada y presupuestada, y acumulado (YTD) para comparar contra la meta.
	 */
	public function Monthly(array $f, int $year): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$f2['cause'] = null;
		$target = $this->Target($year, $f['branch_id'] ?? null);
		$basis = $target['basis'] ?? 'cost';
		$ev = $li->Events($f2, "$year-01-01", "$year-12-31");
		$lastMonth = $year < intval(date('Y')) ? 12 : ($year > intval(date('Y')) ? 0 : intval(date('n')));
		$out = [];
		$cumReal = 0.0;
		$cumSales = 0.0;
		$cumTol = 0.0;
		for ($m = 1; $m <= 12; $m++)
		{
			$mm = sprintf('%04d-%02d', $year, $m);
			if ($m > $lastMonth)
			{
				$out[] = ['month' => $mm, 'label' => LossIntelligenceService::MONTHS[$m - 1], 'future' => true];
				continue;
			}
			$rows = array_filter($ev, fn($e) => substr($e['d'], 0, 7) === $mm);
			[$sales] = $li->NetSales($f2, "$mm-01", date('Y-m-t', strtotime("$mm-01")));
			$cost = self::Sum($rows);
			$sv = self::Sum($rows, null, 'sale_value');
			$real = $basis === 'sale' ? $sv : $cost;
			$tol = $target && $target['tolerated_pct'] !== null ? $sales * $target['tolerated_pct'] / 100 : null;
			$cumReal += $real;
			$cumSales += $sales;
			$cumTol += $tol ?? 0;
			$out[] = ['month' => $mm, 'label' => LossIntelligenceService::MONTHS[$m - 1], 'future' => false, 'net_sales' => round($sales, 2),
				'cost' => round($cost, 2), 'sale_value' => round($sv, 2), 'real' => round($real, 2), 'real_pct' => self::Pct($real, $sales),
				'tolerated' => $tol !== null ? round($tol, 2) : null, 'excess' => $tol !== null ? round(max(0, $real - $tol), 2) : null,
				'ytd_real' => round($cumReal, 2), 'ytd_pct' => self::Pct($cumReal, $cumSales), 'ytd_tolerated' => $target && $target['tolerated_pct'] !== null ? round($cumTol, 2) : null];
		}
		return ['year' => $year, 'basis' => $basis, 'target' => $target, 'months' => $out];
	}

	/**
	 * Participación por departamento: $, % de la merma, % de las ventas del departamento, tolerada del departamento y
	 * benchmark del Censo cuando el nombre coincide.
	 */
	public function Departments(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$ev = $li->Events($f2, $from, $to);
		$names = $li->ProductNames(array_unique(array_column($ev, 'product_id')));
		$total = self::Sum($ev);
		$p = [':f' => $from, ':t' => $to];
		$bw = '';
		if ($f['branch_id'] ?? null)
		{
			$bw = ' AND s.branch_id = :b';
			$p[':b'] = $f['branch_id'];
		}
		$sales = [];
		foreach ($this->Rows("SELECT IFNULL(pg.name, 'Sin departamento') AS d, SUM(si.subtotal) AS v FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$bw GROUP BY 1", $p) as $r)
		{
			$sales[$r['d']] = floatval($r['v']);
		}
		$bench = [];
		foreach ($this->Benchmarks('merma_depto_venta') as $b)
		{
			$bench[mb_strtolower($b['segment'])] = $b['value'];
		}
		$year = intval(substr($to, 0, 4));
		$dept = [];
		foreach ($ev as $e)
		{
			$d = $names[$e['product_id']]['department'] ?? 'Sin departamento';
			$dept[$d] ??= ['department' => $d, 'cost' => 0.0, 'sale_value' => 0.0, 'units' => 0.0, 'events' => 0, 'by_class' => []];
			$dept[$d]['cost'] += $e['cost'];
			$dept[$d]['sale_value'] += $e['sale_value'];
			$dept[$d]['units'] += $e['units'];
			$dept[$d]['events']++;
			$dept[$d]['by_class'][$e['loss_class']] = ($dept[$d]['by_class'][$e['loss_class']] ?? 0) + $e['cost'];
		}
		foreach ($dept as &$x)
		{
			$s = $sales[$x['department']] ?? 0.0;
			$t = $this->Target($year, null, $x['department']);
			$x['net_sales'] = round($s, 2);
			$x['share'] = self::Pct($x['cost'], $total);
			$x['pct_sales_cost'] = self::Pct($x['cost'], $s);
			$x['pct_sales_sale'] = self::Pct($x['sale_value'], $s);
			$x['tolerated_pct'] = $t && $t['scope'] === 'department' ? $t['tolerated_pct'] : null;
			$x['bench_sale_pct'] = $bench[mb_strtolower($x['department'])] ?? null;
			$x['cost'] = round($x['cost'], 2);
			$x['sale_value'] = round($x['sale_value'], 2);
			$x['by_class'] = array_map(fn($v) => round($v, 2), $x['by_class']);
			$x['drill'] = self::Drill($f, $from, $to, [], '#tab-clasificacion');
		}
		unset($x);
		usort($dept, fn($a, $b) => $b['cost'] <=> $a['cost']);
		return ['total' => round($total, 2), 'departments' => array_values($dept)];
	}

	/* ============================================================ Fase 3: errores administrativos */

	private function BranchWhere(?int $branchId, string $col, array &$p): string
	{
		if (!$branchId)
		{
			return '';
		}
		$p[':bw'] = $branchId;
		return " AND $col = :bw";
	}

	/**
	 * Auditorías automáticas. Cada hallazgo tiene una clave estable (detection_key): volver a detectar no duplica casos.
	 * Devuelve el conteo de nuevos por categoría. Precio (PRE) y EPC viven en «Precios y marcación»: no se duplican.
	 */
	public function DetectAdminErrors(?int $branchId, string $from, string $to, int $userId): array
	{
		$found = [];
		$add = function (string $cat, string $key, array $r) use (&$found) {
			$found[] = $r + ['category' => $cat, 'detection_key' => $cat . ':' . $key];
		};
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'pr.branch_id', $p);
		// DOC: recepción sin documento del proveedor
		foreach ($this->Rows("SELECT pr.id, pr.receipt_code, pr.branch_id, pr.total_accepted_cost FROM procurement_receipts pr
			WHERE IFNULL(TRIM(pr.supplier_document), '') = '' AND date(pr.received_at) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('DOC', 'receipt:' . $r['id'], ['branch_id' => $r['branch_id'], 'ref_type' => 'procurement_receipt', 'ref_id' => $r['id'], 'ref_doc' => $r['receipt_code'],
				'title' => 'Recepción sin documento del proveedor', 'detail' => 'La recepción ' . $r['receipt_code'] . ' no tiene factura o remisión registrada.',
				'expected_value' => 'Número de factura/remisión', 'actual_value' => 'vacío', 'impact_cost' => 0]);
		}
		// DOC: merma aprobada sin costo
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'w.branch_id', $p);
		foreach ($this->Rows("SELECT w.id, w.case_number, w.branch_id, w.product_id, w.amount FROM waste_records w
			WHERE w.status = 'approved' AND IFNULL(w.total_cost, 0) = 0 AND w.amount > 0 AND date(w.created_timestamp) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('DOC', 'waste_nocost:' . $r['id'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'waste', 'ref_id' => $r['id'], 'ref_doc' => $r['case_number'],
				'title' => 'Merma aprobada sin costo', 'detail' => 'El documento ' . $r['case_number'] . ' descuenta ' . floatval($r['amount']) . ' u. con costo $0: la pérdida no queda valorizada.',
				'expected_value' => 'Costo > 0', 'actual_value' => '0', 'impact_cost' => 0]);
		}
		// REC: entregado ≠ aceptado + rechazado; rechazo sin motivo
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'pr.branch_id', $p);
		foreach ($this->Rows("SELECT i.*, pr.receipt_code, pr.branch_id FROM procurement_receipt_items i JOIN procurement_receipts pr ON pr.id = i.receipt_id
			WHERE date(pr.received_at) BETWEEN :f AND :t$w
				AND (ABS(IFNULL(i.delivered_amount, 0) - IFNULL(i.accepted_amount, 0) - IFNULL(i.rejected_amount, 0)) > 0.0001
					OR (IFNULL(i.rejected_amount, 0) > 0 AND IFNULL(TRIM(i.reject_reason), '') = ''))", $p) as $r)
		{
			$gap = floatval($r['delivered_amount']) - floatval($r['accepted_amount']) - floatval($r['rejected_amount']);
			$add('REC', 'item:' . $r['id'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'procurement_receipt', 'ref_id' => $r['receipt_id'], 'ref_doc' => $r['receipt_code'],
				'title' => abs($gap) > 0.0001 ? 'Recepción descuadrada' : 'Rechazo sin motivo',
				'detail' => abs($gap) > 0.0001 ? 'Entregado ' . floatval($r['delivered_amount']) . ' ≠ aceptado ' . floatval($r['accepted_amount']) . ' + rechazado ' . floatval($r['rejected_amount']) : 'Se rechazaron ' . floatval($r['rejected_amount']) . ' u. sin registrar el motivo.',
				'expected_value' => abs($gap) > 0.0001 ? strval(floatval($r['delivered_amount'])) : 'Motivo del rechazo', 'actual_value' => abs($gap) > 0.0001 ? strval(floatval($r['accepted_amount']) + floatval($r['rejected_amount'])) : 'vacío',
				'impact_cost' => round(abs($gap) * floatval($r['unit_price']), 2)]);
		}
		// BAJ: lotes vencidos hace > 3 días y aún en existencias (baja no registrada)
		$p = [];
		$w = $this->BranchWhere($branchId, 'IFNULL(loc.branch_id, 1)', $p);
		foreach ($this->Rows("SELECT s.id, s.product_id, s.amount, s.best_before_date, IFNULL(s.price, 0) AS price, IFNULL(loc.branch_id, 1) AS branch_id, p.name FROM stock s
			JOIN products p ON p.id = s.product_id LEFT JOIN locations loc ON loc.id = s.location_id
			WHERE s.amount > 0 AND s.best_before_date < date('now', 'localtime', '-3 days') AND s.best_before_date > '1900-01-01' AND s.best_before_date < '2999-01-01'$w", $p) as $r)
		{
			$add('BAJ', 'stock:' . $r['id'] . ':' . $r['best_before_date'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'stock', 'ref_id' => $r['id'], 'ref_doc' => null,
				'title' => 'Vencido sin baja registrada', 'detail' => $r['name'] . ': ' . floatval($r['amount']) . ' u. vencidas el ' . $r['best_before_date'] . ' siguen en existencias.',
				'expected_value' => 'Merma registrada o lote retirado', 'actual_value' => floatval($r['amount']) . ' u. en stock', 'impact_cost' => round(floatval($r['amount']) * floatval($r['price']), 2)]);
		}
		// CON: conteo con diferencia sin ajuste conciliado
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'c.branch_id', $p);
		foreach ($this->Rows("SELECT c.* FROM stock_cycle_counts c WHERE c.executed_date IS NOT NULL AND ABS(IFNULL(c.difference_amount, 0)) > 0.0001
			AND IFNULL(c.adjustment_transaction_id, '') = '' AND date(c.executed_date) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('CON', 'count:' . $r['id'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'cycle_count', 'ref_id' => $r['id'], 'ref_doc' => 'CC-' . $r['id'],
				'title' => 'Conteo con diferencia sin conciliar', 'detail' => 'Teórico ' . floatval($r['theoretical_stock']) . ' vs. contado ' . floatval($r['counted_stock']) . ': requiere reconteo ciego antes de ajustar.',
				'expected_value' => strval(floatval($r['theoretical_stock'])), 'actual_value' => strval(floatval($r['counted_stock'])), 'impact_cost' => round(abs(floatval($r['difference_cost'])), 2)]);
		}
		// INT: existencias negativas
		$p = [];
		$w = $this->BranchWhere($branchId, 'IFNULL(loc.branch_id, 1)', $p);
		foreach ($this->Rows("SELECT s.id, s.product_id, s.amount, IFNULL(loc.branch_id, 1) AS branch_id, p.name FROM stock s JOIN products p ON p.id = s.product_id
			LEFT JOIN locations loc ON loc.id = s.location_id WHERE s.amount < 0$w", $p) as $r)
		{
			$add('INT', 'neg:' . $r['id'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'stock', 'ref_id' => $r['id'],
				'title' => 'Existencia negativa', 'detail' => $r['name'] . ' tiene ' . floatval($r['amount']) . ' u. en una entrada de stock.', 'expected_value' => '≥ 0', 'actual_value' => strval(floatval($r['amount'])), 'impact_cost' => 0]);
		}
		// INT: merma aprobada sin descuento de inventario
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'w.branch_id', $p);
		foreach ($this->Rows("SELECT w.id, w.case_number, w.branch_id, w.product_id, w.total_cost FROM waste_records w WHERE w.status = 'approved' AND IFNULL(w.inventory_deducted, 0) = 0
			AND IFNULL(w.transaction_id, '') = '' AND date(w.created_timestamp) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('INT', 'waste_nodeduct:' . $r['id'], ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'ref_type' => 'waste', 'ref_id' => $r['id'], 'ref_doc' => $r['case_number'],
				'title' => 'Merma aprobada sin descuento de inventario', 'detail' => 'El caso ' . $r['case_number'] . ' está aprobado pero el inventario no se descontó: el stock queda sobrevaluado.',
				'expected_value' => 'Transacción de salida', 'actual_value' => 'sin transacción', 'impact_cost' => round(floatval($r['total_cost']), 2)]);
		}
		// DES: traslado recibido en una ubicación de otra sucursal; en tránsito > 7 días
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 't.to_branch_id', $p);
		foreach ($this->Rows("SELECT t.*, loc.branch_id AS loc_branch FROM stock_transfers t LEFT JOIN locations loc ON loc.id = t.to_location_id
			WHERE date(IFNULL(t.sent_at, t.received_at)) BETWEEN :f AND :t$w
				AND ((t.to_location_id IS NOT NULL AND loc.branch_id IS NOT NULL AND loc.branch_id <> t.to_branch_id)
					OR (t.received_at IS NULL AND t.sent_at IS NOT NULL AND t.sent_at < datetime('now', 'localtime', '-7 days') AND t.status NOT IN ('cancelled', 'received')))", $p) as $r)
		{
			$wrong = $r['loc_branch'] !== null && intval($r['loc_branch']) !== intval($r['to_branch_id']);
			$add('DES', ($wrong ? 'loc:' : 'transit:') . $r['id'], ['branch_id' => $r['to_branch_id'], 'ref_type' => 'transfer', 'ref_id' => $r['id'], 'ref_doc' => $r['transfer_code'],
				'title' => $wrong ? 'Traslado a ubicación de otra sucursal' : 'Traslado en tránsito sin recibir (> 7 días)',
				'detail' => $wrong ? 'El destino declarado es la sucursal #' . $r['to_branch_id'] . ' pero la ubicación pertenece a la #' . $r['loc_branch'] . '.' : 'Enviado el ' . $r['sent_at'] . ' y no recibido: confirmar destino real.',
				'expected_value' => 'Sucursal #' . $r['to_branch_id'], 'actual_value' => $wrong ? 'Sucursal #' . $r['loc_branch'] : 'sin recepción', 'impact_cost' => round(floatval($r['total_sent_cost']), 2)]);
		}
		// MAR: mismo código de barras en más de un producto
		foreach ($this->Rows("SELECT barcode, GROUP_CONCAT(DISTINCT product_id) AS pids, COUNT(DISTINCT product_id) AS n FROM product_barcodes WHERE IFNULL(TRIM(barcode), '') <> '' GROUP BY barcode HAVING n > 1") as $r)
		{
			$pids = explode(',', $r['pids']);
			$add('MAR', 'barcode:' . $r['barcode'], ['product_id' => intval($pids[0]), 'ref_type' => 'barcode', 'ref_doc' => $r['barcode'],
				'title' => 'Código de barras en ' . $r['n'] . ' productos', 'detail' => 'El código ' . $r['barcode'] . ' está asignado a los productos #' . implode(', #', $pids) . ': el POS puede cobrar el producto equivocado.',
				'expected_value' => '1 producto', 'actual_value' => $r['n'] . ' productos', 'impact_cost' => 0]);
		}
		// CAJ: descuadre de la venta; transacción POS rechazada
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 's.branch_id', $p);
		foreach ($this->Rows("SELECT s.* FROM sales s WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$w
			AND ABS(IFNULL(s.total_amount, 0) - (IFNULL(s.subtotal, 0) + IFNULL(s.tax_amount, 0) - IFNULL(s.discount_amount, 0))) > 0.01", $p) as $r)
		{
			$diff = floatval($r['total_amount']) - (floatval($r['subtotal']) + floatval($r['tax_amount']) - floatval($r['discount_amount']));
			$add('CAJ', 'sale:' . $r['id'], ['branch_id' => $r['branch_id'], 'ref_type' => 'sale', 'ref_id' => $r['id'], 'ref_doc' => $r['sale_number'],
				'title' => 'Venta descuadrada', 'detail' => 'Total ' . number_format(floatval($r['total_amount']), 2) . ' ≠ subtotal + impuesto − descuento (diferencia ' . number_format($diff, 2) . ').',
				'expected_value' => number_format(floatval($r['total_amount']) - $diff, 2, '.', ''), 'actual_value' => number_format(floatval($r['total_amount']), 2, '.', ''), 'impact_cost' => round(abs($diff), 2)]);
		}
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'x.branch_id', $p);
		foreach ($this->Rows("SELECT x.id, x.external_id, x.branch_id, x.error FROM pos_external_sales x WHERE x.status IN ('error', 'failed', 'rejected')
			AND date(x.occurred_at) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('CAJ', 'pos:' . $r['id'], ['branch_id' => $r['branch_id'], 'ref_type' => 'pos_external_sale', 'ref_id' => $r['id'], 'ref_doc' => $r['external_id'],
				'title' => 'Transacción POS rechazada', 'detail' => 'La venta ' . $r['external_id'] . ' del POS no se registró: ' . mb_substr(strval($r['error']), 0, 200),
				'expected_value' => 'Venta registrada', 'actual_value' => 'rechazada', 'impact_cost' => 0]);
		}
		// DDS: traslado repetido en 24 h; documento de proveedor recibido dos veces
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'a.from_branch_id', $p);
		foreach ($this->Rows("SELECT a.id AS a, b.id AS b, a.transfer_code AS ca, b.transfer_code AS cb, a.from_branch_id, b.total_sent_cost FROM stock_transfers a JOIN stock_transfers b
			ON b.id > a.id AND b.from_branch_id = a.from_branch_id AND b.to_branch_id = a.to_branch_id AND ABS(julianday(b.sent_at) - julianday(a.sent_at)) <= 1
			WHERE a.sent_at IS NOT NULL AND b.sent_at IS NOT NULL AND a.status <> 'cancelled' AND b.status <> 'cancelled' AND date(a.sent_at) BETWEEN :f AND :t$w
				AND (SELECT GROUP_CONCAT(product_id || 'x' || sent_amount) FROM (SELECT product_id, sent_amount FROM stock_transfer_items WHERE transfer_id = a.id ORDER BY product_id, sent_amount))
					= (SELECT GROUP_CONCAT(product_id || 'x' || sent_amount) FROM (SELECT product_id, sent_amount FROM stock_transfer_items WHERE transfer_id = b.id ORDER BY product_id, sent_amount))", $p) as $r)
		{
			$add('DDS', 'transfer:' . $r['a'] . ':' . $r['b'], ['branch_id' => $r['from_branch_id'], 'ref_type' => 'transfer', 'ref_id' => $r['b'], 'ref_doc' => $r['cb'],
				'title' => 'Posible doble despacho', 'detail' => 'Los traslados ' . $r['ca'] . ' y ' . $r['cb'] . ' tienen el mismo origen, destino y productos en menos de 24 h.',
				'expected_value' => '1 despacho', 'actual_value' => '2 despachos', 'impact_cost' => round(floatval($r['total_sent_cost']), 2)]);
		}
		$p = [':f' => $from, ':t' => $to];
		$w = $this->BranchWhere($branchId, 'a.branch_id', $p);
		foreach ($this->Rows("SELECT a.id AS a, b.id AS b, a.receipt_code AS ca, b.receipt_code AS cb, a.supplier_document, a.branch_id, b.total_accepted_cost FROM procurement_receipts a JOIN procurement_receipts b
			ON b.id > a.id AND b.supplier_id = a.supplier_id AND TRIM(b.supplier_document) = TRIM(a.supplier_document)
			WHERE IFNULL(TRIM(a.supplier_document), '') <> '' AND date(b.received_at) BETWEEN :f AND :t$w", $p) as $r)
		{
			$add('DDS', 'receipt:' . $r['a'] . ':' . $r['b'], ['branch_id' => $r['branch_id'], 'ref_type' => 'procurement_receipt', 'ref_id' => $r['b'], 'ref_doc' => $r['cb'],
				'title' => 'Documento de proveedor recibido dos veces', 'detail' => 'El documento ' . $r['supplier_document'] . ' aparece en ' . $r['ca'] . ' y ' . $r['cb'] . ': riesgo de pago o ingreso doble.',
				'expected_value' => '1 recepción', 'actual_value' => '2 recepciones', 'impact_cost' => round(floatval($r['total_accepted_cost']), 2)]);
		}

		$created = [];
		$due = date('Y-m-d', strtotime('+' . max(1, intval($this->LI()->Setting('admin_error_due_days', 5))) . ' days'));
		foreach ($found as $r)
		{
			if ($this->InsertCase($r + ['source' => 'auto', 'due_date' => $due], $userId))
			{
				$created[$r['category']] = ($created[$r['category']] ?? 0) + 1;
			}
		}
		return ['found' => count($found), 'created' => array_sum($created), 'by_category' => $created];
	}

	private function InsertCase(array $r, int $userId): ?int
	{
		$db = $this->Db();
		$stmt = $db->prepare('INSERT OR IGNORE INTO admin_error_cases (category, source, detection_key, branch_id, product_id, ref_type, ref_id, ref_doc, title, detail, expected_value, actual_value, impact_cost, due_date, created_by_user_id)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$r['category'], $r['source'], $r['detection_key'] ?? null, $r['branch_id'] ?? null, $r['product_id'] ?? null, $r['ref_type'] ?? null, $r['ref_id'] ?? null, $r['ref_doc'] ?? null,
			$r['title'], $r['detail'] ?? null, $r['expected_value'] ?? null, $r['actual_value'] ?? null, floatval($r['impact_cost'] ?? 0), $r['due_date'] ?? null, $userId]);
		if ($stmt->rowCount() === 0)
		{
			return null;
		}
		$id = intval($db->lastInsertId());
		$db->prepare('UPDATE admin_error_cases SET case_number = ? WHERE id = ?')->execute([sprintf('EA-%s-%05d', $r['category'], $id), $id]);
		$this->CaseLog($id, null, 'detected', $r['source'] === 'auto' ? 'Detección automática' : 'Registro manual', $userId);
		return $id;
	}

	private function CaseLog(int $id, ?string $from, string $to, ?string $notes, int $userId): void
	{
		$this->Db()->prepare('INSERT INTO admin_error_case_log (case_id, from_status, to_status, notes, user_id) VALUES (?, ?, ?, ?, ?)')->execute([$id, $from, $to, $notes, $userId]);
	}

	public function CreateAdminError(array $d, int $userId): int
	{
		$cat = strval($d['category'] ?? '');
		if (!isset(self::ADMIN_CATEGORIES[$cat]))
		{
			throw new \Exception('Categoría de error administrativo inválida');
		}
		$title = trim(strval($d['title'] ?? ''));
		if (mb_strlen($title) < 5)
		{
			throw new \Exception('Describa el error (mínimo 5 caracteres)');
		}
		$key = !empty($d['idempotency_key']) ? 'manual:' . mb_substr(strval($d['idempotency_key']), 0, 80) : null;
		if ($key && ($existing = $this->Val('SELECT id FROM admin_error_cases WHERE detection_key = ?', [$key])))
		{
			return intval($existing);
		}
		$id = $this->InsertCase(['category' => $cat, 'source' => 'manual', 'detection_key' => $key, 'branch_id' => !empty($d['branch_id']) ? intval($d['branch_id']) : null,
			'product_id' => !empty($d['product_id']) ? intval($d['product_id']) : null, 'ref_type' => 'manual', 'ref_doc' => trim(strval($d['ref_doc'] ?? '')) ?: null, 'title' => $title,
			'detail' => trim(strval($d['detail'] ?? '')) ?: null, 'expected_value' => trim(strval($d['expected_value'] ?? '')) ?: null, 'actual_value' => trim(strval($d['actual_value'] ?? '')) ?: null,
			'impact_cost' => max(0, floatval($d['impact_cost'] ?? 0)), 'due_date' => date('Y-m-d', strtotime('+' . max(1, intval($this->LI()->Setting('admin_error_due_days', 5))) . ' days'))], $userId);
		if (!$id)
		{
			throw new \Exception('El caso ya existe');
		}
		return $id;
	}

	/**
	 * Avanza un caso un paso del flujo. Reglas: evidencia y conciliación obligatorias; aprobar y verificar exigen
	 * permiso y segregación (quien aprueba ≠ quien concilió; quien verifica ≠ quien corrigió); el cierre exige resolución.
	 */
	public function AdvanceAdminError(int $id, string $to, array $d, int $userId, bool $canApprove): array
	{
		$rows = $this->Rows('SELECT * FROM admin_error_cases WHERE id = ?', [$id]);
		if (!$rows)
		{
			throw new \Exception('Caso no encontrado');
		}
		$c = $rows[0];
		$cur = $c['status'];
		if (in_array($cur, ['closed', 'dismissed']))
		{
			throw new \Exception('El caso ya está cerrado');
		}
		$notes = trim(strval($d['notes'] ?? ''));
		$set = ['status' => $to];
		if ($to === 'dismissed')
		{
			if (!$canApprove)
			{
				throw new \Exception('Descartar un caso requiere permiso de aprobación');
			}
			if (mb_strlen($notes) < 10)
			{
				throw new \Exception('Explique por qué no es un error (mínimo 10 caracteres)');
			}
			$set['resolution'] = $notes;
			$set['closed_timestamp'] = date('Y-m-d H:i:s');
		}
		else
		{
			$ci = array_search($cur, self::ADMIN_FLOW, true);
			$ti = array_search($to, self::ADMIN_FLOW, true);
			if ($ti === false || $ti !== $ci + 1)
			{
				throw new \Exception('Paso inválido: el siguiente es «' . self::ADMIN_STATUS[self::ADMIN_FLOW[$ci + 1]] . '»');
			}
			$lastBy = function (string $status) use ($id) {
				$v = $this->Val('SELECT user_id FROM admin_error_case_log WHERE case_id = ? AND to_status = ? ORDER BY id DESC LIMIT 1', [$id, $status]);
				return $v === false ? null : intval($v);
			};
			switch ($to)
			{
				case 'evidence':
					$path = trim(strval($d['evidence_path'] ?? ''));
					if ($path === '' && mb_strlen($notes) < 10)
					{
						throw new \Exception('Adjunte una foto o documento, o describa la evidencia (mínimo 10 caracteres)');
					}
					$set['evidence_path'] = $path ?: $c['evidence_path'];
					$set['evidence_notes'] = $notes ?: $c['evidence_notes'];
					break;
				case 'reconciled':
					if (mb_strlen($notes) < 10)
					{
						throw new \Exception('Registre la conciliación: qué se comparó y qué resultó (mínimo 10 caracteres)');
					}
					$set['reconciliation_notes'] = $notes;
					if (isset($d['impact_cost']) && $d['impact_cost'] !== '')
					{
						$set['impact_cost'] = max(0, floatval($d['impact_cost']));
					}
					break;
				case 'approved':
					if (!$canApprove)
					{
						throw new \Exception('Aprobar la corrección requiere permiso de aprobación');
					}
					if ($lastBy('reconciled') === $userId)
					{
						throw new \Exception('Segregación de funciones: quien concilió no puede aprobar');
					}
					$set['approved_by_user_id'] = $userId;
					break;
				case 'corrected':
					if (mb_strlen($notes) < 10)
					{
						throw new \Exception('Describa la corrección aplicada (documento, ajuste o cambio)');
					}
					$set['correction_notes'] = $notes;
					$set['corrected_by_user_id'] = $userId;
					break;
				case 'verified':
					if (!$canApprove)
					{
						throw new \Exception('Verificar requiere permiso de aprobación');
					}
					if (intval($c['corrected_by_user_id']) === $userId)
					{
						throw new \Exception('Segregación de funciones: quien corrigió no puede verificar');
					}
					$set['verified_by_user_id'] = $userId;
					break;
				case 'closed':
					if (mb_strlen($notes) < 5)
					{
						throw new \Exception('Indique la resolución final');
					}
					$set['resolution'] = $notes;
					$set['closed_timestamp'] = date('Y-m-d H:i:s');
					break;
			}
		}
		$set['updated_timestamp'] = date('Y-m-d H:i:s');
		$sql = 'UPDATE admin_error_cases SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set))) . ' WHERE id = :id AND status = :cur';
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)) + [':id' => $id, ':cur' => $cur]);
		if ($stmt->rowCount() === 0)
		{
			throw new \Exception('El caso cambió mientras lo editaba; recargue');
		}
		$this->CaseLog($id, $cur, $to, $notes ?: null, $userId);
		return $this->Rows('SELECT * FROM admin_error_cases WHERE id = ?', [$id])[0];
	}

	public function AdminErrors(?int $branchId, ?string $status = null, ?string $category = null): array
	{
		$p = [];
		$w = $this->BranchWhere($branchId, 'c.branch_id', $p);
		if ($status === 'open')
		{
			$w .= " AND c.status NOT IN ('closed', 'dismissed')";
		}
		elseif ($status && isset(self::ADMIN_STATUS[$status]))
		{
			$w .= ' AND c.status = :st';
			$p[':st'] = $status;
		}
		if ($category && isset(self::ADMIN_CATEGORIES[$category]))
		{
			$w .= ' AND c.category = :cat';
			$p[':cat'] = $category;
		}
		$cases = $this->Rows("SELECT c.*, p.name AS product_name, b.name AS branch_name FROM admin_error_cases c LEFT JOIN products p ON p.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id
			WHERE 1 = 1$w ORDER BY CASE WHEN c.status IN ('closed', 'dismissed') THEN 1 ELSE 0 END, c.id DESC LIMIT 300", $p);
		$p2 = [];
		$w2 = $this->BranchWhere($branchId, 'branch_id', $p2);
		$stats = [];
		foreach (self::ADMIN_CATEGORIES as $k => $c)
		{
			$stats[$k] = ['code' => $k, 'name' => $c[0], 'area' => $c[1], 'rule' => $c[2], 'total' => 0, 'open' => 0, 'overdue' => 0, 'closed' => 0, 'dismissed' => 0, 'impact' => 0.0, 'bench_pct' => null];
		}
		foreach ($this->Rows("SELECT category, status, COUNT(*) AS n, SUM(impact_cost) AS impact, SUM(CASE WHEN due_date < date('now', 'localtime') AND status NOT IN ('closed', 'dismissed') THEN 1 ELSE 0 END) AS overdue
			FROM admin_error_cases WHERE 1 = 1$w2 GROUP BY category, status", $p2) as $r)
		{
			$k = $r['category'];
			$n = intval($r['n']);
			$stats[$k]['overdue'] += intval($r['overdue']);
			if ($r['status'] === 'dismissed')
			{
				$stats[$k]['dismissed'] += $n;
				continue;
			}
			$stats[$k]['total'] += $n;
			$stats[$k]['impact'] += floatval($r['impact']);
			$stats[$k][$r['status'] === 'closed' ? 'closed' : 'open'] += $n;
		}
		// Precios: se cuentan desde «Precios y marcación» (no se duplican casos)
		$pp = [];
		$pw = $this->BranchWhere($branchId, 'branch_id', $pp);
		$price = $this->Rows("SELECT kind, status, COUNT(*) AS n, SUM(IFNULL(impact_amount, 0)) AS impact FROM price_discrepancies WHERE 1 = 1$pw GROUP BY kind, status", $pp);
		foreach ($price as $r)
		{
			if ($r['status'] === 'rejected')
			{
				continue;
			}
			$k = $r['kind'] === 'epc' ? 'EPC' : (in_array($r['kind'], ['wrong_label', 'barcode_sku']) ? 'MAR' : 'PRE');
			$n = intval($r['n']);
			$stats[$k]['total'] += $n;
			$stats[$k]['impact'] += floatval($r['impact']);
			$stats[$k][$r['status'] === 'closed' ? 'closed' : 'open'] += $n;
			$stats[$k]['from_prices'] = ($stats[$k]['from_prices'] ?? 0) + $n;
		}
		$tot = array_sum(array_column($stats, 'total'));
		$bench = [];
		foreach ($this->Benchmarks('error_admin') as $b)
		{
			$bench[$b['segment']] = $b['value'];
		}
		foreach ($stats as $k => &$s)
		{
			$s['freq_pct'] = self::Pct($s['total'], $tot);
			$s['bench_pct'] = $bench[$k] ?? null;
			$s['impact'] = round($s['impact'], 2);
		}
		unset($s);
		$freq = array_values($stats);
		usort($freq, fn($a, $b) => $b['total'] <=> $a['total']);
		return ['cases' => $cases, 'stats' => $freq, 'total' => $tot, 'open' => array_sum(array_column($stats, 'open')), 'overdue' => array_sum(array_column($stats, 'overdue')),
			'impact' => round(array_sum(array_column($stats, 'impact')), 2), 'categories' => self::ADMIN_CATEGORIES, 'statuses' => self::ADMIN_STATUS, 'flow' => self::ADMIN_FLOW,
			'rfid_ready' => intval($this->Val("SELECT COUNT(*) FROM price_discrepancies WHERE kind = 'epc'")) > 0];
	}

	public function CaseHistory(int $id): array
	{
		return $this->Rows("SELECT l.*, TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')) AS user_name, u.username AS user FROM admin_error_case_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.case_id = ? ORDER BY l.id", [$id]);
	}

	/* ============================================================ Fase 4: robo y fraude */

	/**
	 * Detalle de una merma: destino final y peso (cualquiera que registre) y, con permiso de prevención, modalidad,
	 * canal de detección y recuperación. No guarda datos personales.
	 */
	public function SetWasteDetails(int $wasteId, array $d, bool $canTheft): void
	{
		$w = $this->Rows('SELECT w.id, r.loss_cause_code FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.id = ?', [$wasteId]);
		if (!$w)
		{
			throw new \Exception('Merma no encontrada');
		}
		$set = [];
		if (array_key_exists('final_destination', $d))
		{
			$v = strval($d['final_destination'] ?? '');
			if ($v !== '' && !isset(self::DESTINATIONS[$v]))
			{
				throw new \Exception('Destino final inválido');
			}
			$set['final_destination'] = $v ?: null;
		}
		if (array_key_exists('weight_kg', $d))
		{
			$v = $d['weight_kg'] === '' || $d['weight_kg'] === null ? null : floatval($d['weight_kg']);
			if ($v !== null && $v < 0)
			{
				throw new \Exception('El peso no puede ser negativo');
			}
			$set['weight_kg'] = $v;
		}
		$theftKeys = array_intersect(array_keys($d), ['theft_modality', 'detection_channel', 'recovered_goods_value', 'financial_recovery_amount']);
		if ($theftKeys)
		{
			if (!$canTheft)
			{
				throw new \Exception('Los datos de hurto requieren permiso de prevención de pérdidas');
			}
			$all = array_merge(...array_values(array_map('array_keys', self::MODALITIES)));
			if (array_key_exists('theft_modality', $d))
			{
				$v = strval($d['theft_modality'] ?? '');
				if ($v !== '' && !in_array($v, $all, true))
				{
					throw new \Exception('Modalidad inválida');
				}
				$set['theft_modality'] = $v ?: null;
			}
			if (array_key_exists('detection_channel', $d))
			{
				$v = strval($d['detection_channel'] ?? '');
				if ($v !== '' && !isset(self::CHANNELS[$v]))
				{
					throw new \Exception('Canal de detección inválido');
				}
				$set['detection_channel'] = $v ?: null;
			}
			foreach (['recovered_goods_value', 'financial_recovery_amount'] as $k)
			{
				if (array_key_exists($k, $d))
				{
					$v = $d[$k] === '' || $d[$k] === null ? 0.0 : floatval($d[$k]);
					if ($v < 0)
					{
						throw new \Exception('La recuperación no puede ser negativa');
					}
					$set[$k] = $v;
				}
			}
		}
		if (!$set)
		{
			return;
		}
		$stmt = $this->Db()->prepare('UPDATE waste_records SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set))) . ' WHERE id = :id');
		$stmt->execute(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)) + [':id' => $wasteId]);
	}

	/**
	 * Inteligencia de hurto: tipos (interno, externo, fraude POS, fraude electrónico), sospechosos en investigación,
	 * modalidades, canal de detección, recuperación de mercancía y financiera, tasa de incidentes y tendencia.
	 */
	public function TheftIntel(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$ev = $li->Events($f2, $from, $to);
		$theft = array_values(array_filter($ev, fn($e) => str_starts_with($e['loss_class'], 'robo_')));
		$inv = array_values(array_filter($ev, fn($e) => $e['loss_class'] === 'investigacion'));
		$ids = array_map(fn($e) => intval($e['ref']), array_filter($theft, fn($e) => $e['src'] === 'waste'));
		$det = [];
		if ($ids)
		{
			foreach ($this->Rows('SELECT id, theft_modality, detection_channel, IFNULL(recovered_goods_value, 0) AS goods, IFNULL(units_recovered, 0) AS units_rec FROM waste_records WHERE id IN (' . implode(',', $ids) . ')') as $r)
			{
				$det[intval($r['id'])] = $r;
			}
		}
		$modLabels = [];
		foreach (self::MODALITIES as $g)
		{
			$modLabels += $g;
		}
		$type = fn($e) => $e['cause'] === 'FPO' ? 'fraude_pos' : ($e['cause'] === 'FEL' ? 'fraude_electronico' : ($e['loss_class'] === 'robo_interno' ? 'interno' : 'externo'));
		$typeLabels = ['interno' => 'Robo interno confirmado', 'externo' => 'Robo externo confirmado', 'fraude_pos' => 'Fraude POS', 'fraude_electronico' => 'Fraude electrónico'];
		$byType = array_map(fn($l) => ['label' => $l, 'cost' => 0.0, 'incidents' => 0, 'units' => 0.0], $typeLabels);
		$mod = [];
		$chan = [];
		$monthly = [];
		$goods = 0.0;
		$fin = 0.0;
		$unitsRec = 0.0;
		foreach ($theft as $e)
		{
			$t = $type($e);
			$byType[$t]['cost'] += $e['cost'];
			$byType[$t]['units'] += $e['units'];
			$byType[$t]['incidents']++;
			$d = $det[intval($e['ref'])] ?? null;
			$mk = $d['theft_modality'] ?? null;
			$mk = $mk ? $mk : 'sin_registrar';
			$mod[$t . '|' . $mk] ??= ['type' => $t, 'key' => $mk, 'label' => $modLabels[$mk] ?? 'Sin registrar', 'incidents' => 0, 'cost' => 0.0];
			$mod[$t . '|' . $mk]['incidents']++;
			$mod[$t . '|' . $mk]['cost'] += $e['cost'];
			$ck = ($d['detection_channel'] ?? null) ?: 'sin_registrar';
			$chan[$ck] ??= ['key' => $ck, 'label' => self::CHANNELS[$ck] ?? 'Sin registrar', 'incidents' => 0, 'cost' => 0.0];
			$chan[$ck]['incidents']++;
			$chan[$ck]['cost'] += $e['cost'];
			$goods += floatval($d['goods'] ?? 0);
			$unitsRec += floatval($d['units_rec'] ?? 0);
			$fin += $e['recovered'];
			$m = substr($e['d'], 0, 7);
			$monthly[$m] ??= ['incidents' => 0, 'cost' => 0.0];
			$monthly[$m]['incidents']++;
			$monthly[$m]['cost'] += $e['cost'];
		}
		ksort($monthly);
		$total = self::Sum($theft);
		$p = [':f' => $from, ':t' => $to];
		$bw = '';
		if ($f['branch_id'] ?? null)
		{
			$bw = ' AND branch_id = :b';
			$p[':b'] = $f['branch_id'];
		}
		$tickets = intval($this->Val("SELECT COUNT(*) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t$bw", $p));
		$months = max(1, (intval(substr($to, 0, 4)) - intval(substr($from, 0, 4))) * 12 + intval(substr($to, 5, 2)) - intval(substr($from, 5, 2)) + 1);
		$mods = array_values($mod);
		usort($mods, fn($a, $b) => $b['cost'] <=> $a['cost']);
		$chans = array_values($chan);
		usort($chans, fn($a, $b) => $b['incidents'] <=> $a['incidents']);
		return [
			'by_type' => array_map(fn($x) => ['label' => $x['label'], 'cost' => round($x['cost'], 2), 'incidents' => $x['incidents'], 'units' => round($x['units'], 2), 'share' => self::Pct($x['cost'], $total)], $byType),
			'total' => round($total, 2), 'incidents' => count($theft),
			'suspected' => ['count' => count($inv), 'cost' => round(self::Sum($inv), 2)],
			'modalities' => array_map(fn($x) => $x + ['cost' => round($x['cost'], 2)], $mods),
			'channels' => array_map(fn($x) => $x + ['cost' => round($x['cost'], 2)], $chans),
			'recovery' => ['goods' => round($goods, 2), 'financial' => round($fin, 2), 'units' => round($unitsRec, 2), 'total' => round($goods + $fin, 2), 'pct' => self::Pct($goods + $fin, $total),
				'bench_pct' => $this->Bench('recuperacion', 'merma_reducida')],
			'rate' => ['tickets' => $tickets, 'per_1000_tickets' => $tickets > 0 ? round(count($theft) / $tickets * 1000, 3) : null, 'per_month' => round(count($theft) / $months, 2)],
			'monthly' => array_map(fn($x) => ['incidents' => $x['incidents'], 'cost' => round($x['cost'], 2)], $monthly),
			'pending_details' => count(array_filter($theft, fn($e) => $e['src'] === 'waste' && empty($det[intval($e['ref'])]['theft_modality']))),
			'events' => array_slice(array_map(fn($e) => ['ref' => $e['ref'], 'doc' => $e['doc'], 'd' => $e['d'], 'type' => $typeLabels[$type($e)], 'cost' => $e['cost'], 'units' => $e['units'],
				'modality' => $modLabels[$det[intval($e['ref'])]['theft_modality'] ?? ''] ?? null, 'channel' => self::CHANNELS[$det[intval($e['ref'])]['detection_channel'] ?? ''] ?? null,
				'type_key' => $type($e), 'theft_modality' => $det[intval($e['ref'])]['theft_modality'] ?? null, 'detection_channel' => $det[intval($e['ref'])]['detection_channel'] ?? null,
				'goods' => floatval($det[intval($e['ref'])]['goods'] ?? 0), 'recovered' => $e['recovered']], array_reverse(array_filter($theft, fn($e) => $e['src'] === 'waste'))), 0, 60),
			'catalog' => ['modalities' => self::MODALITIES, 'channels' => self::CHANNELS],
		];
	}

	/* ============================================================ Fase 5: perecederos y destinos */

	public static function FamilyOf(?string $department): ?string
	{
		if ($department === null || $department === '')
		{
			return null;
		}
		foreach (self::FAMILIES as $k => [, $re])
		{
			if (preg_match($re, $department))
			{
				return $k;
			}
		}
		return null;
	}

	public function PerishableDestinations(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$ev = $li->Events($f2, $from, $to);
		$names = $li->ProductNames(array_unique(array_column($ev, 'product_id')));
		$causes = array_column($this->Rows('SELECT code, name FROM loss_causes'), 'name', 'code');
		$wIds = array_map(fn($e) => intval($e['ref']), array_filter($ev, fn($e) => $e['src'] === 'waste'));
		$wd = [];
		if ($wIds)
		{
			foreach ($this->Rows('SELECT w.id, w.final_destination, w.weight_kg, w.evidence_photo_path, LOWER(IFNULL(qu.name, \'\')) AS qu FROM waste_records w LEFT JOIN quantity_units qu ON qu.id = w.qu_id WHERE w.id IN (' . implode(',', $wIds) . ')') as $r)
			{
				$wd[intval($r['id'])] = $r;
			}
		}
		$weight = function ($e) use ($wd) {
			$d = $wd[intval($e['ref'])] ?? null;
			if (!$d)
			{
				return null;
			}
			if ($d['weight_kg'] !== null)
			{
				return floatval($d['weight_kg']);
			}
			return preg_match('/^(kg|kilo|kilogramo)/', $d['qu']) ? $e['units'] : null;
		};
		$fam = [];
		foreach (self::FAMILIES as $k => [$label])
		{
			$fam[$k] = ['key' => $k, 'label' => $label, 'cost' => 0.0, 'units' => 0.0, 'weight_kg' => 0.0, 'events' => 0, 'with_evidence' => 0, 'causes' => []];
		}
		$dest = [];
		foreach (self::DESTINATIONS as $k => $l)
		{
			$dest[$k] = ['key' => $k, 'label' => $l, 'cost' => 0.0, 'units' => 0.0, 'weight_kg' => 0.0, 'events' => 0];
		}
		$dest['sin_registrar'] = ['key' => 'sin_registrar', 'label' => 'Sin destino registrado', 'cost' => 0.0, 'units' => 0.0, 'weight_kg' => 0.0, 'events' => 0];
		$list = [];
		foreach ($ev as $e)
		{
			$dep = $names[$e['product_id']]['department'] ?? 'Sin departamento';
			$k = self::FamilyOf($dep);
			$wkg = $weight($e);
			if ($e['src'] === 'waste')
			{
				$dk = ($wd[intval($e['ref'])]['final_destination'] ?? null) ?: 'sin_registrar';
				$dest[$dk]['cost'] += $e['cost'];
				$dest[$dk]['units'] += $e['units'];
				$dest[$dk]['weight_kg'] += $wkg ?? 0;
				$dest[$dk]['events']++;
			}
			if ($k === null)
			{
				continue;
			}
			$fam[$k]['cost'] += $e['cost'];
			$fam[$k]['units'] += $e['units'];
			$fam[$k]['weight_kg'] += $wkg ?? 0;
			$fam[$k]['events']++;
			$fam[$k]['with_evidence'] += !empty($wd[intval($e['ref'])]['evidence_photo_path']) ? 1 : 0;
			$fam[$k]['causes'][$e['cause']] = ($fam[$k]['causes'][$e['cause']] ?? 0) + $e['cost'];
			$list[] = ['uid' => $e['uid'], 'src' => $e['src'], 'product_id' => $e['product_id'], 'ref' => $e['ref'], 'doc' => $e['doc'], 'd' => $e['d'], 'family' => self::FAMILIES[$k][0], 'product' => $names[$e['product_id']]['name'] ?? '',
				'units' => $e['units'], 'weight_kg' => $wkg, 'cost' => $e['cost'], 'cause' => $causes[$e['cause']] ?? $e['cause'],
				'destination' => self::DESTINATIONS[$wd[intval($e['ref'])]['final_destination'] ?? ''] ?? null, 'evidence' => $wd[intval($e['ref'])]['evidence_photo_path'] ?? null];
		}
		// Ventas por familia para el %
		$p = [':f' => $from, ':t' => $to];
		$bw = '';
		if ($f['branch_id'] ?? null)
		{
			$bw = ' AND s.branch_id = :b';
			$p[':b'] = $f['branch_id'];
		}
		$famSales = array_fill_keys(array_keys(self::FAMILIES), 0.0);
		foreach ($this->Rows("SELECT IFNULL(pg.name, '') AS d, SUM(si.subtotal) AS v FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$bw GROUP BY 1", $p) as $r)
		{
			$k = self::FamilyOf($r['d']);
			if ($k)
			{
				$famSales[$k] += floatval($r['v']);
			}
		}
		$totalPer = array_sum(array_column($fam, 'cost'));
		foreach ($fam as $k => &$x)
		{
			arsort($x['causes']);
			$x['causes'] = array_map(fn($v) => round($v, 2), array_slice($x['causes'], 0, 5, true));
			$x['cause_names'] = array_map(fn($c) => $causes[$c] ?? $c, array_keys($x['causes']));
			$x['net_sales'] = round($famSales[$k], 2);
			$x['pct_sales'] = self::Pct($x['cost'], $famSales[$k]);
			$x['share'] = self::Pct($x['cost'], $totalPer);
			$x['cost'] = round($x['cost'], 2);
			$x['weight_kg'] = round($x['weight_kg'], 2);
		}
		unset($x);
		// Devoluciones a proveedor (no son merma de DAIRY si se acreditan) y drenaje medido en lotes de cárnicos/fruver
		$p = [':f' => $from, ':t' => $to];
		$bw = '';
		if ($f['branch_id'] ?? null)
		{
			$bw = ' AND branch_id = :b';
			$p[':b'] = $f['branch_id'];
		}
		$ret = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(amount), 0) AS units, IFNULL(SUM(total_loss_cost), 0) AS cost, IFNULL(SUM(credit_recovered_amount), 0) AS rec FROM supplier_returns WHERE date(created_timestamp) BETWEEN :f AND :t$bw", $p)[0];
		$yl = $this->Rows("SELECT IFNULL(SUM(drain_water_kg), 0) AS water, IFNULL(SUM(drain_blood_kg), 0) AS blood, IFNULL(SUM(dehydration_kg), 0) AS dehyd, IFNULL(SUM(transport_kg), 0) AS transport, IFNULL(SUM(received_kg), 0) AS received, COUNT(*) AS n
			FROM perishable_yield_lots WHERE status <> 'cancelled' AND date(IFNULL(received_date, created_timestamp)) BETWEEN :f AND :t$bw", $p);
		$transport = self::Sum($ev, fn($e) => $e['cause'] === 'TRA');
		$byCause = fn(array $codes) => ['cost' => round(self::Sum($ev, fn($e) => in_array($e['cause'], $codes)), 2), 'events' => count(array_filter($ev, fn($e) => in_array($e['cause'], $codes)))];
		$destTotal = array_sum(array_column($dest, 'cost'));
		foreach ($dest as &$x)
		{
			$x['share'] = self::Pct($x['cost'], $destTotal);
			$x['cost'] = round($x['cost'], 2);
			$x['weight_kg'] = round($x['weight_kg'], 2);
		}
		unset($x);
		usort($list, fn($a, $b) => strcmp($b['d'], $a['d']));
		return [
			'families' => array_values($fam),
			'destinations' => array_values(array_filter($dest, fn($x) => $x['events'] > 0 || $x['key'] === 'donacion' || $x['key'] === 'compostaje')),
			'donated' => $dest['donacion'], 'composted' => $dest['compostaje'],
			'supplier_returns' => ['count' => intval($ret['n']), 'units' => round(floatval($ret['units']), 2), 'cost' => round(floatval($ret['cost']), 2), 'recovered' => round(floatval($ret['rec']), 2)],
			'yield' => $yl ? array_map('floatval', $yl[0]) : null,
			'transport' => round($transport, 2),
			'breakage' => $byCause(['ROT']), 'external_damage' => $byCause(['DEX']), 'expiry' => $byCause(['EXP', 'FEF']), 'cold' => $byCause(['CFR']), 'damage' => $byCause(['AVE']),
			'events' => array_slice($list, 0, 80),
			'destinations_catalog' => self::DESTINATIONS,
		];
	}

	/* ============================================================ Fase 6: impacto, controles y equivalencias */

	public function Controls(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$p = [];
		$w = '';
		if ($f['branch_id'] ?? null)
		{
			$w = ' AND (c.branch_id IS NULL OR c.branch_id = :b)';
			$p[':b'] = $f['branch_id'];
		}
		$rows = $this->Rows("SELECT c.*, b.name AS branch_name FROM loss_prevention_controls c LEFT JOIN branches b ON b.id = c.branch_id WHERE 1 = 1$w ORDER BY c.active DESC, c.start_date DESC", $p);
		$days = fn($a, $b) => max(0, (strtotime($b) - strtotime($a)) / 86400 + 1);
		$totCost = 0.0;
		$totSaved = 0.0;
		foreach ($rows as &$c)
		{
			$cf = ['branch_id' => $c['branch_id'] ? intval($c['branch_id']) : ($f['branch_id'] ?? null)] + LossIntelligenceService::Filters([]);
			$cf['loss_class'] = $c['target_class'] && isset(LossIntelligenceService::CLASSES[$c['target_class']]) ? $c['target_class'] : null;
			$cf['cause'] = $c['target_cause'] ?: null;
			// Costo dentro del período consultado
			$s = max($from, $c['start_date']);
			$e = min($to, $c['end_date'] ?: $to);
			$activeDays = $s <= $e ? $days($s, $e) : 0;
			$c['period_cost'] = round(floatval($c['monthly_cost']) * $activeDays / 30.4375, 2);
			// Efectividad: tasa diaria de pérdida del objetivo antes (misma duración) vs. después del inicio
			$afterTo = min(date('Y-m-d'), $c['end_date'] ?: date('Y-m-d'));
			$afterDays = $c['start_date'] <= $afterTo ? $days($c['start_date'], $afterTo) : 0;
			$c['after_days'] = intval($afterDays);
			if ($afterDays > 0)
			{
				$beforeTo = date('Y-m-d', strtotime($c['start_date'] . ' -1 day'));
				$beforeFrom = date('Y-m-d', strtotime($c['start_date'] . ' -' . intval($afterDays) . ' days'));
				$before = self::Sum($li->Events($cf, $beforeFrom, $beforeTo));
				$after = self::Sum($li->Events($cf, $c['start_date'], $afterTo));
				$hasBefore = intval($this->Val('SELECT COUNT(*) FROM sales WHERE date(created_timestamp) BETWEEN ? AND ?', [$beforeFrom, $beforeTo])) > 0 || $before != 0.0;
				$cost = floatval($c['monthly_cost']) * $afterDays / 30.4375;
				$saved = $before - $after;
				$c['before'] = round($before, 2);
				$c['after'] = round($after, 2);
				$c['has_baseline'] = $hasBefore;
				$c['reduction_pct'] = $hasBefore ? self::Pct($saved, $before) : null;
				$c['saved'] = $hasBefore ? round($saved, 2) : null;
				$c['cost_to_date'] = round($cost, 2);
				$c['roi_pct'] = $hasBefore && $cost > 0 ? round(($saved - $cost) / $cost * 100, 1) : null;
				if ($hasBefore)
				{
					$totSaved += $saved;
				}
			}
			else
			{
				$c += ['before' => null, 'after' => null, 'has_baseline' => false, 'reduction_pct' => null, 'saved' => null, 'cost_to_date' => 0, 'roi_pct' => null];
			}
			$totCost += $c['period_cost'];
			$c['type_label'] = self::CONTROL_TYPES[$c['control_type']] ?? $c['control_type'];
		}
		unset($c);
		return ['controls' => $rows, 'period_cost' => round($totCost, 2), 'saved' => round($totSaved, 2), 'types' => self::CONTROL_TYPES];
	}

	public function SaveControl(array $d, int $userId): int
	{
		$name = trim(strval($d['name'] ?? ''));
		if (mb_strlen($name) < 3)
		{
			throw new \Exception('Indique el nombre de la medida');
		}
		$type = strval($d['control_type'] ?? '');
		if (!isset(self::CONTROL_TYPES[$type]))
		{
			throw new \Exception('Tipo de control inválido');
		}
		$start = substr(strval($d['start_date'] ?? ''), 0, 10);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start))
		{
			throw new \Exception('Fecha de inicio inválida');
		}
		$end = !empty($d['end_date']) ? substr(strval($d['end_date']), 0, 10) : null;
		if ($end && $end < $start)
		{
			throw new \Exception('La fecha de fin es anterior al inicio');
		}
		$cost = floatval($d['monthly_cost'] ?? 0);
		if ($cost < 0)
		{
			throw new \Exception('El costo no puede ser negativo');
		}
		$class = !empty($d['target_class']) && isset(LossIntelligenceService::CLASSES[$d['target_class']]) ? $d['target_class'] : null;
		$cause = !empty($d['target_cause']) ? strval($d['target_cause']) : null;
		if ($cause && !$this->Val('SELECT 1 FROM loss_causes WHERE code = ?', [$cause]))
		{
			throw new \Exception('Causal inválida');
		}
		$vals = [$name, $type, !empty($d['branch_id']) ? intval($d['branch_id']) : null, $class, $cause, $start, $end, $cost, trim(strval($d['notes'] ?? '')) ?: null, isset($d['active']) ? (intval($d['active']) ? 1 : 0) : 1];
		if (!empty($d['id']))
		{
			$this->Db()->prepare('UPDATE loss_prevention_controls SET name = ?, control_type = ?, branch_id = ?, target_class = ?, target_cause = ?, start_date = ?, end_date = ?, monthly_cost = ?, notes = ?, active = ? WHERE id = ?')
				->execute(array_merge($vals, [intval($d['id'])]));
			return intval($d['id']);
		}
		$this->Db()->prepare('INSERT INTO loss_prevention_controls (name, control_type, branch_id, target_class, target_cause, start_date, end_date, monthly_cost, notes, active, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute(array_merge($vals, [$userId]));
		return intval($this->Db()->lastInsertId());
	}

	/**
	 * Equivalencias sociales OPCIONALES: solo con supuestos configurados (país, moneda, año, valor y fuente).
	 * Son una forma de dimensionar la pérdida, no empleos ni viviendas realmente perdidos.
	 */
	public function Social(float $loss): array
	{
		$li = $this->LI();
		$s = fn($k) => trim(strval($li->Setting($k, '')));
		$job = floatval($s('social_job_annual_cost'));
		$vis = floatval($s('social_vis_value'));
		$benchLoss = $this->Bench('impacto', 'merma_estimada');
		$benchJobs = $this->Bench('impacto', 'empleos_equivalentes');
		$benchVis = $this->Bench('impacto', 'vis_equivalentes');
		return [
			'country' => $s('social_country'), 'currency' => $s('social_currency'), 'year' => $s('social_year'),
			'jobs' => ['assumption' => $job ?: null, 'source' => $s('social_job_source') ?: null, 'value' => $job > 0 && $s('social_job_source') !== '' ? round($loss / $job, 2) : null],
			'vis' => ['assumption' => $vis ?: null, 'source' => $s('social_vis_source') ?: null, 'value' => $vis > 0 && $s('social_vis_source') !== '' ? round($loss / $vis, 2) : null],
			// Supuestos implícitos en las cifras publicadas por el Censo (datos 2022): solo referencia
			'census' => ['loss' => $benchLoss, 'jobs' => $benchJobs, 'vis' => $benchVis,
				'implied_job_cost' => $benchLoss && $benchJobs ? round($benchLoss / $benchJobs, 0) : null, 'implied_vis_value' => $benchLoss && $benchVis ? round($benchLoss / $benchVis, 0) : null],
		];
	}

	public function Economic(array $f, string $from, string $to): array
	{
		$li = $this->LI();
		$f2 = $f;
		$f2['loss_class'] = null;
		$s = $li->Summary($f2, $from, $to);
		$h = $this->Hierarchy($f2, $from, $to);
		$dept = $this->Departments($f2, $from, $to);
		$formats = $li->Formats($f2, $from, $to);
		$causes = $li->CauseMatrix($f2, $from, $to);
		$controls = $this->Controls($f2, $from, $to);
		$total = $s['total']['value'];
		$rec = $s['recovery'];
		$net = $total - $rec['recovered'];
		return [
			'total' => $total, 'sale_value' => $h['total']['sale_value'], 'net_sales' => $s['net_sales'],
			'pct_sales_cost' => $h['total']['pct_sales_cost'], 'pct_sales_sale' => $h['total']['pct_sales_sale'],
			'theft' => $s['robo']['value'], 'margin' => $s['margin_impact'], 'gross_margin' => $s['gross_margin'],
			'lost_capital' => round($net, 2), 'recovery' => $rec,
			'prevention_cost' => $controls['period_cost'], 'prevention_saved' => $controls['saved'],
			'prevention_roi_pct' => $controls['period_cost'] > 0 ? round(($controls['saved'] - $controls['period_cost']) / $controls['period_cost'] * 100, 1) : null,
			'by_department' => array_slice($dept['departments'], 0, 15),
			'by_format' => $formats['formats'],
			'by_cause' => array_values(array_filter($causes['causes'], fn($c) => $c['cost'] != 0)),
			'controls' => $controls,
			'social' => $this->Social($total),
		];
	}
}
