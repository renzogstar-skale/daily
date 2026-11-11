<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;

/**
 * CRM de clientes retail. Todas las métricas salen de ventas completadas con cliente identificado (netas de
 * devoluciones del POS); el cliente anónimo de mostrador y los registros fusionados nunca entran en ellas.
 * Los datos personales (documento, correo, teléfono, dirección, nacimiento) se enmascaran salvo con CUSTOMERS_PII,
 * y cada consulta o cambio queda en customer_audit_log.
 */
class CustomerCrmService extends BaseService
{
	private static $instance = null;

	const CONSENT_PURPOSES = [
		'data_processing' => 'Tratamiento de datos personales',
		'marketing_email' => 'Comunicaciones comerciales por correo',
		'marketing_sms' => 'Comunicaciones comerciales por SMS',
		'marketing_whatsapp' => 'Comunicaciones comerciales por WhatsApp',
		'profiling' => 'Análisis de hábitos de compra para ofertas personalizadas'
	];
	const CONSENT_SOURCES = ['store' => 'En tienda', 'pos' => 'En caja', 'phone' => 'Teléfono', 'web' => 'Web', 'form' => 'Formulario firmado'];
	const PREFERENCES = [
		'preferred_channel' => ['label' => 'Canal preferido', 'options' => ['' => '—', 'email' => 'Correo', 'phone' => 'Llamada', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'store' => 'En tienda']],
		'contact_time' => ['label' => 'Horario de contacto', 'options' => ['' => '—', 'morning' => 'Mañana', 'afternoon' => 'Tarde', 'evening' => 'Noche']],
		'language' => ['label' => 'Idioma', 'options' => ['' => '—', 'es' => 'Español', 'en' => 'Inglés']],
		'remarks' => ['label' => 'Observaciones', 'options' => null]
	];
	const REQUEST_CATEGORIES = [
		'complaint' => 'Calidad / Atención',
		'delivery' => 'Despacho y entregas',
		'credit' => 'Crédito y finanzas',
		'invoice' => 'Facturación',
		'quote' => 'Cotización',
		'special_order' => 'Pedido especial',
		'product' => 'Producto / disponibilidad',
		'other' => 'Otra'
	];
	const REQUEST_STATUSES = ['open' => 'Abierta', 'in_progress' => 'En gestión', 'waiting_customer' => 'Esperando al cliente', 'resolved' => 'Resuelta', 'cancelled' => 'Anulada'];
	const REQUEST_PRIORITIES = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];
	const INTERACTION_KINDS = ['call' => 'Llamada', 'visit' => 'Visita en tienda', 'email' => 'Correo', 'whatsapp' => 'WhatsApp', 'complaint' => 'Reclamo', 'note' => 'Nota interna', 'request' => 'Solicitud'];
	const FINANCIAL_STATUSES = ['pending_finance' => 'Pendiente Finanzas', 'approved_finance' => 'Aprobado Finanzas', 'rejected_finance' => 'Rechazado Finanzas'];
	const CAMPAIGN_CHANNELS = ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email', 'pos' => 'POS / Ticket', 'multichannel' => 'Email + WhatsApp'];
	const REGIONS = [
		'CO' => ['country_name' => 'Colombia', 'tax_id_label' => 'NIT / Cédula', 'tax_id_placeholder' => 'Ej. 900.123.456-7 o CC 1020304506', 'phone_code' => '+57', 'phone_placeholder' => 'Ej. +57 310 456 7890', 'locale' => 'es-CO'],
		'US' => ['country_name' => 'Estados Unidos', 'tax_id_label' => 'EIN / Tax ID', 'tax_id_placeholder' => 'Ej. 12-3456789', 'phone_code' => '+1', 'phone_placeholder' => 'Ej. +1 (305) 555-0148', 'locale' => 'en-US'],
		'MX' => ['country_name' => 'México', 'tax_id_label' => 'RFC / CURP', 'tax_id_placeholder' => 'Ej. XAXX010101000', 'phone_code' => '+52', 'phone_placeholder' => 'Ej. +52 55 1234 5678', 'locale' => 'es-MX'],
		'VE' => ['country_name' => 'Venezuela', 'tax_id_label' => 'RIF / Cédula', 'tax_id_placeholder' => 'Ej. J-30491823-1 o V-12345678', 'phone_code' => '+58', 'phone_placeholder' => 'Ej. +58 414-234-5678', 'locale' => 'es-VE'],
		'ES' => ['country_name' => 'España', 'tax_id_label' => 'NIF / CIF', 'tax_id_placeholder' => 'Ej. B12345678', 'phone_code' => '+34', 'phone_placeholder' => 'Ej. +34 612 345 678', 'locale' => 'es-ES']
	];
	const LIFECYCLE = [
		'prospect' => ['label' => 'Sin compras', 'tone' => 'muted'],
		'new' => ['label' => 'Nuevo', 'tone' => 'info'],
		'recurrent' => ['label' => 'Recurrente', 'tone' => 'good'],
		'occasional' => ['label' => 'Ocasional', 'tone' => 'muted'],
		'at_risk' => ['label' => 'En riesgo', 'tone' => 'warn'],
		'inactive' => ['label' => 'Inactivo', 'tone' => 'bad']
	];
	const SEGMENTS = [
		'champions' => 'Campeones',
		'loyal' => 'Leales',
		'potential' => 'Potenciales',
		'attention' => 'Necesitan atención',
		'at_risk' => 'Valiosos alejándose',
		'hibernating' => 'Hibernando',
		'none' => 'Sin compras'
	];

	public static function GetInstance(): self
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	// ------------------------------------------------------------------ acceso y protección de datos

	public static function CanSeePii(): bool
	{
		return DairyAccess::GetSimulatedRole() === 'manager' && DairyAccess::HasAny([User::PERMISSION_CUSTOMERS_PII]);
	}

	public static function CanManage(): bool
	{
		return DairyAccess::GetSimulatedRole() === 'manager' && DairyAccess::HasAny([User::PERMISSION_CUSTOMERS_MANAGE]);
	}

	public static function CanApproveFinance(): bool
	{
		return DairyAccess::GetSimulatedRole() === 'manager' && DairyAccess::HasAny([User::PERMISSION_ADMIN, User::PERMISSION_BUDGET_MANAGE]);
	}

	private function RequireManage(): void
	{
		if (!self::CanManage())
		{
			throw new \Exception('Su rol no puede modificar fichas de clientes (permiso CUSTOMERS_MANAGE).');
		}
	}

	public static function MaskEmail(?string $v): ?string
	{
		if (!$v)
		{
			return null;
		}
		[$user, $domain] = array_pad(explode('@', $v, 2), 2, '');
		return mb_substr($user, 0, 1) . '•••@' . ($domain !== '' ? $domain : '•••');
	}

	public static function MaskTail(?string $v, int $keep = 3): ?string
	{
		if (!$v)
		{
			return null;
		}
		$plain = preg_replace('/\s+/', '', $v);
		return '••••' . mb_substr($plain, -$keep);
	}

	/** Ficha con los datos personales enmascarados si el rol no está autorizado */
	public function Protect(array $c): array
	{
		$c['pii_visible'] = self::CanSeePii();
		if (!$c['pii_visible'])
		{
			$c['email'] = self::MaskEmail($c['email'] ?? null);
			$c['phone'] = self::MaskTail($c['phone'] ?? null, 3);
			$c['tax_id'] = self::MaskTail($c['tax_id'] ?? null, 3);
			$c['address'] = !empty($c['address']) ? 'Reservado' : null;
			$c['birth_date'] = null;
		}
		unset($c['tax_id_norm'], $c['email_norm'], $c['phone_norm'], $c['name_norm']);
		return $c;
	}

	public static function NormalizeIdentifiers(array $r): array
	{
		$tax = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($r['tax_id'] ?? '')));
		$email = strtolower(trim((string)($r['email'] ?? '')));
		$phone = preg_replace('/\D/', '', (string)($r['phone'] ?? ''));
		$name = (string)($r['name'] ?? '');
		$name = strtolower(strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n']));
		$name = trim(preg_replace('/[^a-z0-9]+/', ' ', $name));
		return [
			'tax_id_norm' => strlen($tax) >= 5 ? $tax : null,
			'email_norm' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
			// Últimos 10 dígitos: el mismo número con o sin prefijo de país coincide
			'phone_norm' => strlen($phone) >= 7 ? substr($phone, -10) : null,
			'name_norm' => $name !== '' ? $name : null
		];
	}

	/** Calidad de identificación: cuántos identificadores fuertes (documento, correo, teléfono) tiene la ficha */
	public static function IdentificationQuality(array $c): array
	{
		$n = (!empty($c['tax_id_norm']) ? 1 : 0) + (!empty($c['email_norm']) ? 1 : 0) + (!empty($c['phone_norm']) ? 1 : 0);
		if ($n >= 2)
		{
			return ['key' => 'complete', 'label' => 'Completa', 'score' => $n];
		}
		return $n === 1 ? ['key' => 'basic', 'label' => 'Básica', 'score' => 1] : ['key' => 'insufficient', 'label' => 'Insuficiente', 'score' => 0];
	}

	private function Audit(?int $customerId, string $action, $detail = null): void
	{
		$this->Db()->prepare('INSERT INTO customer_audit_log (customer_id, user_id, action, detail) VALUES (?, ?, ?, ?)')
			->execute([$customerId, defined('GROCY_USER_ID') ? GROCY_USER_ID : null, $action, $detail === null ? null : (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE))]);
	}

	// ------------------------------------------------------------------ utilidades

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$r = $this->Rows($sql, $p);
		return $r[0] ?? null;
	}

	public function RegionalConfig(): array
	{
		$keys = ['crm_country', 'default_currency', 'currency_symbol', 'timezone', 'company_tax_id'];
		$placeholders = implode(',', array_fill(0, count($keys), '?'));
		$rows = $this->Rows("SELECT setting_key, setting_value FROM daily_system_settings WHERE setting_key IN ($placeholders)", $keys);
		$s = array_column($rows, 'setting_value', 'setting_key');
		$country = strtoupper(trim((string)($s['crm_country'] ?? '')));
		if (!isset(self::REGIONS[$country]))
		{
			$tz = (string)($s['timezone'] ?? '');
			$tax = strtoupper((string)($s['company_tax_id'] ?? ''));
			if (str_contains($tz, 'Bogota') || str_starts_with($tax, 'NIT'))
			{
				$country = 'CO';
			}
			elseif (str_contains($tz, 'Mexico'))
			{
				$country = 'MX';
			}
			elseif (str_contains($tz, 'Lima'))
			{
				$country = 'PE';
			}
			elseif (str_contains($tz, 'Santiago'))
			{
				$country = 'CL';
			}
			elseif (str_contains($tz, 'Caracas'))
			{
				$country = 'VE';
			}
			else
			{
				$country = 'CO';
			}
		}
		$meta = self::REGIONS[$country] ?? self::REGIONS['CO'];
		$currencyCode = strtoupper(trim((string)($s['default_currency'] ?? (defined('GROCY_CURRENCY') ? GROCY_CURRENCY : 'USD'))));
		$currencySymbol = trim((string)($s['currency_symbol'] ?? '$')) ?: '$';
		return [
			'country' => $country,
			'country_label' => $meta['country_name'],
			'currency_code' => $currencyCode,
			'currency_symbol' => $currencySymbol,
			'tax_id_label' => $meta['tax_id_label'],
			'tax_id_placeholder' => $meta['tax_id_placeholder'],
			'phone_prefix' => $meta['phone_code'],
			'phone_placeholder' => $meta['phone_placeholder'],
			'locale' => $meta['locale'] ?? 'es-CO',
			'timezone' => (string)($s['timezone'] ?? 'America/Bogota')
		];
	}

	public function Settings(): array
	{
		$s = array_column($this->Rows("SELECT setting_key, setting_value FROM daily_system_settings WHERE setting_key LIKE 'crm\\_%' ESCAPE '\\'"), 'setting_value', 'setting_key');
		$regional = $this->RegionalConfig();
		return [
			'loyalty_enabled' => ($s['crm_loyalty_enabled'] ?? '0') === '1',
			'amount_per_point' => max(0.01, floatval($s['crm_loyalty_amount_per_point'] ?? 10)),
			'point_value' => max(0.0, floatval($s['crm_loyalty_point_value'] ?? 0.10)),
			'loyalty_start' => $s['crm_loyalty_start'] ?? date('Y-m-d'),
			'inactive_days' => max(7, intval($s['crm_inactive_days'] ?? 60)),
			'new_days' => max(7, intval($s['crm_new_days'] ?? 30)),
			'repurchase_goal_pct' => min(95.0, max(5.0, floatval($s['crm_repurchase_goal_pct'] ?? 65))),
			'country' => $regional['country'],
			'regional' => $regional
		];
	}

	/** Condición SQL sobre la sucursal de la venta (alias s) según filtro y sucursales permitidas */
	private function BranchSql(?int $branchId, string $col = 's.branch_id'): string
	{
		$allowed = DairyAccess::AllowedBranches();
		if ($branchId)
		{
			if ($allowed !== null && !in_array($branchId, $allowed, true))
			{
				return ' AND 0';
			}
			return " AND $col = " . intval($branchId);
		}
		return $allowed === null ? '' : " AND $col IN (" . implode(',', array_map('intval', $allowed ?: [0])) . ')';
	}

	private function NetSalesSql(?int $branchId): string
	{
		return "SELECT s.id, s.customer_id, s.branch_id, s.created_timestamp AS ts, s.total_amount - IFNULL(r.amt, 0) AS net
			FROM sales s
			LEFT JOIN (SELECT sale_id, SUM(amount) AS amt FROM daily_pos_returns GROUP BY sale_id) r ON r.sale_id = s.id
			JOIN customers c ON c.id = s.customer_id AND c.is_anonymous = 0 AND c.merged_into_id IS NULL
			WHERE s.status = 'completed'" . $this->BranchSql($branchId);
	}

	// ------------------------------------------------------------------ métricas por cliente

	/**
	 * Clientes registrados (sin anónimo ni fusionados) con métricas de compra, ciclo de vida, segmento RFM, puntos y tier de valor.
	 * Con sucursal o sucursales restringidas solo cuenta compras de esas sucursales y clientes vinculados a ellas.
	 */
	public function CustomersWithMetrics(?int $branchId = null, ?int $days = null): array
	{
		$cfg = $this->Settings();
		if ($cfg['loyalty_enabled'])
		{
			$this->SyncEarned();
		}
		$days = in_array($days, [30, 90, 365], true) ? $days : 30;
		$now = time();
		$from90 = date('Y-m-d H:i:s', strtotime('-90 days'));
		$fromPeriod = date('Y-m-d H:i:s', strtotime("-$days days"));
		$agg = [];
		foreach ($this->Rows('SELECT customer_id, COUNT(*) AS tx, SUM(net) AS revenue, MIN(ts) AS first_ts, MAX(ts) AS last_ts,
				COUNT(DISTINCT date(ts)) AS visit_days,
				SUM(CASE WHEN ts >= ? THEN 1 ELSE 0 END) AS tx90, SUM(CASE WHEN ts >= ? THEN net ELSE 0 END) AS rev90,
				SUM(CASE WHEN ts >= ? THEN 1 ELSE 0 END) AS tx_period, SUM(CASE WHEN ts >= ? THEN net ELSE 0 END) AS rev_period
				FROM (' . $this->NetSalesSql($branchId) . ') GROUP BY customer_id', [$from90, $from90, $fromPeriod, $fromPeriod]) as $a)
		{
			$agg[intval($a['customer_id'])] = $a;
		}

		$ptsMap = [];
		foreach ($this->Rows("SELECT customer_id, IFNULL(SUM(points), 0) AS balance, IFNULL(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS earned FROM customer_loyalty_ledger GROUP BY customer_id") as $p)
		{
			$ptsMap[intval($p['customer_id'])] = [
				'balance' => max(0, intval($p['balance'])),
				'earned' => max(0, intval($p['earned']))
			];
		}

		$where = 'IFNULL(c.is_anonymous, 0) = 0 AND c.merged_into_id IS NULL';
		$scope = $this->BranchSql($branchId, 'c.branch_id');
		$customers = $this->Rows("SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON b.id = c.branch_id WHERE $where ORDER BY c.name");
		$out = [];
		foreach ($customers as $c)
		{
			$id = intval($c['id']);
			$a = $agg[$id] ?? null;
			// Fuera de alcance: ni registrado en la sucursal ni con compras en ella
			if ($scope !== '' && $a === null)
			{
				$allowed = $branchId ? [$branchId] : (DairyAccess::AllowedBranches() ?? []);
				if (!in_array(intval($c['branch_id']), $allowed, true))
				{
					continue;
				}
			}
			$tx = intval($a['tx'] ?? 0);
			$revenue = round(floatval($a['revenue'] ?? 0), 2);
			$visitDays = intval($a['visit_days'] ?? 0);
			$recency = $a ? intval(floor(($now - strtotime($a['last_ts'])) / 86400)) : null;
			$tenure = $a ? max(1, intval(floor(($now - strtotime($a['first_ts'])) / 86400))) : null;
			$freqDays = $visitDays > 1 ? round((strtotime($a['last_ts']) - strtotime($a['first_ts'])) / 86400 / ($visitDays - 1), 1) : null;
			$avgTicket = $tx > 0 ? round($revenue / $tx, 2) : 0.0;
			// Compras por año observadas desde la primera compra (mínimo 30 días de antigüedad para no extrapolar de más)
			$perYear = $tx > 0 ? round($tx / max(30, $tenure) * 365, 1) : 0.0;
			$monthlyFreq = $perYear > 0 ? round($perYear / 12, 2) : 0.0;

			if ($tx === 0)
			{
				$life = 'prospect';
			}
			elseif ($recency > $cfg['inactive_days'])
			{
				$life = 'inactive';
			}
			elseif ($tenure <= $cfg['new_days'])
			{
				$life = 'new';
			}
			elseif ($visitDays >= 2 && $recency > max(intval($cfg['inactive_days'] / 2), intval(($freqDays ?? 0) * 2)))
			{
				$life = 'at_risk';
			}
			else
			{
				$life = $visitDays >= 2 ? 'recurrent' : 'occasional';
			}

			$pts = $ptsMap[$id] ?? ['balance' => 0, 'earned' => 0];
			$earned = $pts['earned'];
			$loyaltyTier = $earned >= 5000 ? 'Platinum' : ($earned >= 1500 ? 'Gold' : ($earned >= 300 ? 'Silver' : 'Bronze'));

			$c += self::NormalizeIdentifiers($c);
			$out[$id] = $c + [
				'tx' => $tx,
				'revenue' => $revenue,
				'avg_ticket' => $avgTicket,
				'first_purchase' => $a['first_ts'] ?? null,
				'last_purchase' => $a['last_ts'] ?? null,
				'recency_days' => $recency,
				'frequency_days' => $freqDays,
				'visit_days' => $visitDays,
				'tx_90d' => intval($a['tx90'] ?? 0),
				'revenue_90d' => round(floatval($a['rev90'] ?? 0), 2),
				'tx_period' => intval($a['tx_period'] ?? 0),
				'revenue_period' => round(floatval($a['rev_period'] ?? 0), 2),
				'purchases_per_year' => $perYear,
				'monthly_frequency' => $monthlyFreq,
				'annual_value' => round($avgTicket * $perYear, 2),
				'points_balance' => $pts['balance'],
				'points_earned' => $earned,
				'loyalty_tier' => $loyaltyTier,
				'lifecycle' => $life,
				'quality' => self::IdentificationQuality($c)
			];
		}
		$this->ScoreRfm($out);
		return $out;
	}

	/**
	 * RFM 1–5: recencia por umbrales absolutos (relativos al umbral de inactividad), frecuencia y monto por quintiles
	 * entre compradores con empates al mismo puntaje. Tier de valor por Pareto de ingresos (A 20 %, B 30 %, C 50 %).
	 */
	private function ScoreRfm(array &$list): void
	{
		$buyers = array_filter($list, fn($c) => $c['tx'] > 0);
		$n = count($buyers);
		$rank = function (string $field) use ($buyers, $n): array
		{
			$vals = array_map(fn($c) => $c[$field], $buyers);
			$sorted = array_values($vals);
			sort($sorted);
			$scores = [];
			foreach ($vals as $id => $v)
			{
				// Rango medio de los empatados: valores iguales reciben el mismo quintil
				$lo = count(array_filter($sorted, fn($x) => $x < $v));
				$eq = count(array_filter($sorted, fn($x) => $x == $v));
				$scores[$id] = min(5, intval(floor(($lo + ($eq - 1) / 2) / max(1, $n) * 5)) + 1);
			}
			return $scores;
		};
		$inactive = $this->Settings()['inactive_days'];
		$r = array_map(fn($c) => match (true) {
			$c['recency_days'] <= 7 => 5,
			$c['recency_days'] <= 15 => 4,
			$c['recency_days'] <= intval($inactive / 2) => 3,
			$c['recency_days'] <= $inactive => 2,
			default => 1
		}, $buyers);
		$f = $rank('visit_days');
		$m = $rank('revenue');

		$byRevenue = array_map(fn($c) => $c['revenue'], $buyers);
		arsort($byRevenue);
		$tier = [];
		$i = 0;
		foreach ($byRevenue as $id => $v)
		{
			$tier[$id] = $i < ceil($n * 0.2) ? 'A' : ($i < ceil($n * 0.5) ? 'B' : 'C');
			$i++;
		}

		foreach ($list as $id => &$c)
		{
			if ($c['tx'] === 0)
			{
				$c['rfm'] = null;
				$c['segment'] = 'none';
				$c['value_tier'] = null;
				continue;
			}
			[$R, $F, $M] = [$r[$id], $f[$id], $m[$id]];
			if ($R >= 4 && $F >= 4 && $M >= 4)
			{
				$seg = 'champions';
			}
			elseif ($F >= 4 && $R >= 3)
			{
				$seg = 'loyal';
			}
			elseif ($R >= 4)
			{
				$seg = 'potential';
			}
			elseif ($R === 3)
			{
				$seg = 'attention';
			}
			elseif ($F >= 3 || $M >= 4)
			{
				$seg = 'at_risk';
			}
			else
			{
				$seg = 'hibernating';
			}
			$c['rfm'] = ['r' => $R, 'f' => $F, 'm' => $M];
			$c['segment'] = $seg;
			$c['value_tier'] = $tier[$id];
		}
	}

	// ------------------------------------------------------------------ panel y directorio

	public function Overview(?int $branchId, int $days): array
	{
		$days = in_array($days, [30, 90, 365], true) ? $days : 30;
		$cfg = $this->Settings();
		$list = $this->CustomersWithMetrics($branchId, $days);
		$from = date('Y-m-d H:i:s', strtotime("-$days days"));
		$prevFrom = date('Y-m-d H:i:s', strtotime('-' . ($days * 2) . ' days'));
		$net = $this->NetSalesSql($branchId);

		$cur = $this->Row("SELECT COUNT(DISTINCT customer_id) AS buyers, COUNT(*) AS tx, SUM(net) AS revenue FROM ($net) WHERE ts >= ?", [$from]);
		$newBuyers = intval($this->Row("SELECT COUNT(*) AS n FROM (SELECT customer_id, MIN(ts) AS f FROM ($net) GROUP BY customer_id) WHERE f >= ?", [$from])['n']);
		$prevSet = array_map('intval', array_column($this->Rows("SELECT DISTINCT customer_id FROM ($net) WHERE ts >= ? AND ts < ?", [$prevFrom, $from]), 'customer_id'));
		$curSet = array_map('intval', array_column($this->Rows("SELECT DISTINCT customer_id FROM ($net) WHERE ts >= ?", [$from]), 'customer_id'));
		$retained = count(array_intersect($prevSet, $curSet));

		// Cobertura: qué parte de la venta total del período (incl. Público General) está identificada con un cliente
		$all = $this->Row("SELECT SUM(s.total_amount) AS revenue, COUNT(*) AS tx FROM sales s WHERE s.status = 'completed' AND s.created_timestamp >= ?" . $this->BranchSql($branchId), [$from]);
		$anon = $this->Row("SELECT SUM(s.total_amount) AS revenue, COUNT(*) AS tx FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.status = 'completed' AND IFNULL(c.is_anonymous, 0) = 1 AND s.created_timestamp >= ?" . $this->BranchSql($branchId), [$from]);

		$reqScope = $this->BranchSql($branchId, 'q.branch_id');
		$reqWhere = $reqScope === '' ? '' : ' AND (q.branch_id IS NULL' . str_replace(' AND ', ' OR ', $reqScope) . ')';
		$req = $this->Row("SELECT SUM(CASE WHEN status IN ('open','in_progress','waiting_customer') THEN 1 ELSE 0 END) AS open,
			SUM(CASE WHEN status IN ('open','in_progress','waiting_customer') AND due_date < date('now','localtime') THEN 1 ELSE 0 END) AS overdue,
			SUM(CASE WHEN status = 'resolved' AND due_date IS NOT NULL AND date(IFNULL(resolved_at, updated_at)) <= due_date THEN 1 ELSE 0 END) AS on_time_resolved,
			SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS total_resolved,
			SUM(CASE WHEN category = 'credit' AND IFNULL(financial_status, 'pending_finance') = 'pending_finance' AND status IN ('open','in_progress','waiting_customer') THEN 1 ELSE 0 END) AS pending_credit,
			SUM(CASE WHEN category = 'delivery' AND status IN ('open','in_progress','waiting_customer') THEN 1 ELSE 0 END) AS pending_delivery
			FROM customer_requests q WHERE 1 = 1" . $reqWhere);

		// Evolución de clientes e ingresos identificados por mes (últimos 12 meses calendario)
		$monthly = [];
		for ($i = 11; $i >= 0; $i--)
		{
			$ym = date('Y-m', strtotime("-$i months"));
			$monthly[$ym] = ['new' => 0, 'recurrent' => 0, 'tx' => 0, 'revenue' => 0.0, 'avg_value' => 0.0];
		}
		$firsts = array_column($this->Rows("SELECT customer_id, MIN(ts) AS f FROM ($net) GROUP BY customer_id"), 'f', 'customer_id');
		foreach ($this->Rows("SELECT strftime('%Y-%m', ts) AS ym, customer_id, COUNT(*) AS tx, SUM(net) AS rev FROM ($net) WHERE ts >= ? GROUP BY ym, customer_id", [date('Y-m-01', strtotime('-11 months'))]) as $r)
		{
			$ym = $r['ym'];
			if (!isset($monthly[$ym]))
			{
				continue;
			}
			substr($firsts[$r['customer_id']], 0, 7) === $ym ? $monthly[$ym]['new']++ : $monthly[$ym]['recurrent']++;
			$monthly[$ym]['tx'] += intval($r['tx']);
			$monthly[$ym]['revenue'] = round($monthly[$ym]['revenue'] + floatval($r['rev']), 2);
		}
		foreach ($monthly as $ym => $m)
		{
			$totalBuyersMonth = $m['new'] + $m['recurrent'];
			$monthly[$ym]['avg_value'] = $totalBuyersMonth > 0 ? round($m['revenue'] / $totalBuyersMonth, 2) : 0.0;
		}
		ksort($monthly);

		$count = fn(callable $fn) => count(array_filter($list, $fn));
		$segments = [];
		foreach (self::SEGMENTS as $k => $label)
		{
			$items = array_filter($list, fn($c) => $c['segment'] === $k);
			$segments[$k] = [
				'label' => $label,
				'count' => count($items),
				'share_pct' => count($list) > 0 ? round(count($items) / count($list) * 100, 1) : 0.0,
				'revenue' => round(array_sum(array_column($items, 'revenue')), 2),
				'revenue_period' => round(array_sum(array_column($items, 'revenue_period')), 2),
				'avg_ticket' => count($items) > 0 ? round(array_sum(array_column($items, 'avg_ticket')) / count($items), 2) : 0.0
			];
		}
		$lifecycle = [];
		foreach (self::LIFECYCLE as $k => $meta)
		{
			$lifecycle[$k] = $meta + ['count' => $count(fn($c) => $c['lifecycle'] === $k)];
		}

		$buyersList = array_filter($list, fn($c) => $c['tx'] > 0);
		$activeRegistered = $count(fn($c) => intval($c['active'] ?? 1) === 1);
		$totalRevenue = array_sum(array_column($buyersList, 'revenue'));
		$tierA = array_filter($buyersList, fn($c) => $c['value_tier'] === 'A');
		$buyersPeriod = intval($cur['buyers'] ?? 0);
		$repurchaseBuyers = $count(fn($c) => $c['tx_period'] >= 2);
		$repurchasePct = $buyersPeriod > 0 ? round($repurchaseBuyers / $buyersPeriod * 100, 1) : 0.0;
		$goalPct = floatval($cfg['repurchase_goal_pct']);
		$repurchaseDiffPp = round($repurchasePct - $goalPct, 1);

		// CLV promedio anual estrictamente sobre compradores reales identificados (sin anónimos)
		$avgClv = count($buyersList) > 0 ? round(array_sum(array_column($buyersList, 'annual_value')) / count($buyersList), 2) : 0.0;
		$avgFreqMonth = count($buyersList) > 0 ? round(array_sum(array_column($buyersList, 'monthly_frequency')) / count($buyersList), 2) : 0.0;

		$atRiskRfm = $segments['at_risk']['count'];
		$duplicateGroups = count($this->DuplicateGroups());
		$insufficientId = $count(fn($c) => $c['quality']['key'] === 'insufficient');
		$identifiedSharePct = floatval($all['revenue'] ?? 0) > 0 ? round(floatval($cur['revenue'] ?? 0) / floatval($all['revenue']) * 100, 1) : null;

		$totalResolved = intval($req['total_resolved'] ?? 0);
		$overdueRequests = intval($req['overdue'] ?? 0);
		$openRequests = intval($req['open'] ?? 0);
		$slaDenom = $totalResolved + $overdueRequests;
		$slaCompliancePct = $slaDenom > 0 ? round(intval($req['on_time_resolved'] ?? 0) / $slaDenom * 100, 1) : 100.0;

		// Alertas accionables 100% reales (sin datos inventados)
		$alerts = [];
		if ($overdueRequests > 0)
		{
			$alerts[] = [
				'severity' => 'critical',
				'title' => "$overdueRequests caso(s) con SLA vencido",
				'detail' => 'Requieren respuesta o cierre inmediato para proteger la retención.',
				'action_label' => 'Atender casos',
				'target_tab' => 'cases',
				'filter_key' => 'req_status',
				'filter_val' => 'pending'
			];
		}
		if ($atRiskRfm > 0)
		{
			$alerts[] = [
				'severity' => 'warning',
				'title' => "$atRiskRfm cliente(s) en riesgo de abandono (RFM)",
				'detail' => 'Superaron su ciclo habitual sin recomprar en el período.',
				'action_label' => 'Ver segmento RFM',
				'target_tab' => 'directory',
				'filter_key' => 'segment',
				'filter_val' => 'at_risk'
			];
		}
		if (intval($req['pending_credit'] ?? 0) > 0)
		{
			$nCred = intval($req['pending_credit']);
			$alerts[] = [
				'severity' => 'info',
				'title' => "$nCred solicitud(es) de crédito pendientes de evaluación financiera",
				'detail' => 'Esperando aprobación o rechazo por Finanzas/Administración.',
				'action_label' => 'Revisar crédito',
				'target_tab' => 'cases',
				'filter_key' => 'req_category',
				'filter_val' => 'credit'
			];
		}
		if (intval($req['pending_delivery'] ?? 0) > 0)
		{
			$nDel = intval($req['pending_delivery']);
			$alerts[] = [
				'severity' => 'info',
				'title' => "$nDel reclamo(s) de entrega en seguimiento",
				'detail' => 'La resolución operativa y reposición logística se ejecuta en Despachos.',
				'action_label' => 'Ir a Despachos',
				'target_url' => '/dispatches'
			];
		}
		if ($duplicateGroups > 0 || $insufficientId > 0)
		{
			$parts = [];
			if ($duplicateGroups > 0)
			{
				$parts[] = "$duplicateGroups grupo(s) duplicados";
			}
			if ($insufficientId > 0)
			{
				$parts[] = "$insufficientId con datos incompletos";
			}
			$alerts[] = [
				'severity' => 'warning',
				'title' => 'Calidad de base: ' . implode(' y ', $parts),
				'detail' => 'Consolidar duplicados y completar documento/correo evita métricas distorsionadas.',
				'action_label' => 'Depurar base',
				'target_tab' => 'directory',
				'filter_key' => 'quality',
				'filter_val' => $insufficientId > 0 ? 'insufficient' : ''
			];
		}
		if ($identifiedSharePct !== null && $identifiedSharePct < 40.0)
		{
			$anonPct = round(100 - $identifiedSharePct, 1);
			$alerts[] = [
				'severity' => 'info',
				'title' => "$anonPct % de las ventas en POS sigue como Mostrador",
				'detail' => 'Identificar compradores en caja eleva la cobertura real de fidelización y recompra.',
				'action_label' => 'Configurar captura',
				'target_tab' => 'loyalty'
			];
		}

		// Resumen rápido de fidelización y automatizaciones para badges y tabs
		$loyaltyTiers = ['Platinum' => 0, 'Gold' => 0, 'Silver' => 0, 'Bronze' => 0];
		$totalPtsBalance = 0;
		foreach ($list as $c)
		{
			$loyaltyTiers[$c['loyalty_tier']] = ($loyaltyTiers[$c['loyalty_tier']] ?? 0) + 1;
			$totalPtsBalance += intval($c['points_balance']);
		}

		return [
			'days' => $days,
			'registered' => count($list),
			'active_registered' => $activeRegistered,
			'buyers_period' => $buyersPeriod,
			'new_period' => $newBuyers,
			'recurrent_period' => max(0, $buyersPeriod - $newBuyers),
			'repurchase_buyers' => $repurchaseBuyers,
			'repurchase_pct' => $repurchasePct,
			'repurchase_goal_pct' => $goalPct,
			'repurchase_diff_pp' => $repurchaseDiffPp,
			'retention_pct' => count($prevSet) > 0 ? round($retained / count($prevSet) * 100, 1) : null,
			'retained' => $retained,
			'prev_buyers' => count($prevSet),
			'avg_ticket' => intval($cur['tx'] ?? 0) > 0 ? round(floatval($cur['revenue']) / intval($cur['tx']), 2) : 0.0,
			'avg_clv' => $avgClv,
			'avg_freq_month' => $avgFreqMonth,
			'identified_revenue' => round(floatval($cur['revenue'] ?? 0), 2),
			'total_pos_revenue' => round(floatval($all['revenue'] ?? 0), 2),
			'anonymous_revenue' => round(floatval($anon['revenue'] ?? 0), 2),
			'identified_share_pct' => $identifiedSharePct,
			'identified_tx_share_pct' => intval($all['tx'] ?? 0) > 0 ? round(intval($cur['tx'] ?? 0) / intval($all['tx']) * 100, 1) : null,
			'inactive' => $lifecycle['inactive']['count'],
			'at_risk' => $atRiskRfm,
			'at_risk_rfm' => $atRiskRfm,
			'at_risk_lifecycle' => $lifecycle['at_risk']['count'],
			'open_requests' => $openRequests,
			'overdue_requests' => $overdueRequests,
			'pending_credit_requests' => intval($req['pending_credit'] ?? 0),
			'pending_delivery_requests' => intval($req['pending_delivery'] ?? 0),
			'sla_compliance_pct' => $slaCompliancePct,
			'insufficient_id' => $insufficientId,
			'duplicate_groups' => $duplicateGroups,
			'tier_a_share_pct' => $totalRevenue > 0 ? round(array_sum(array_column($tierA, 'revenue')) / $totalRevenue * 100, 1) : null,
			'avg_frequency_days' => ($fr = array_filter(array_column($buyersList, 'frequency_days'))) ? round(array_sum($fr) / count($fr), 1) : null,
			'segments' => $segments,
			'lifecycle' => $lifecycle,
			'monthly' => $monthly,
			'alerts' => $alerts,
			'loyalty_summary' => [
				'enabled' => $cfg['loyalty_enabled'],
				'tiers' => $loyaltyTiers,
				'total_points' => $totalPtsBalance,
				'points_liability' => round($totalPtsBalance * $cfg['point_value'], 2)
			],
			'settings' => $cfg,
			'regional' => $cfg['regional']
		];
	}

	/** Directorio filtrado: q busca por nombre y, sin revelarlos, por documento, correo o teléfono */
	public function Directory(?int $branchId, array $f = []): array
	{
		$daysRaw = intval($f['days'] ?? 30);
		$days = in_array($daysRaw, [30, 90, 365], true) ? $daysRaw : 30;
		$list = $this->CustomersWithMetrics($branchId, $days);
		$q = trim((string)($f['q'] ?? ''));
		if ($q !== '')
		{
			$nq = self::NormalizeIdentifiers(['tax_id' => $q, 'email' => $q, 'phone' => $q, 'name' => $q]);
			$list = array_filter($list, fn($c) => ($nq['name_norm'] && str_contains((string)$c['name_norm'], $nq['name_norm']))
				|| ($nq['tax_id_norm'] && $c['tax_id_norm'] && str_contains($c['tax_id_norm'], $nq['tax_id_norm']))
				|| ($nq['email_norm'] && $c['email_norm'] === $nq['email_norm'])
				|| ($nq['phone_norm'] && $c['phone_norm'] && str_ends_with($c['phone_norm'], $nq['phone_norm'])));
		}
		foreach (['segment' => 'segment', 'lifecycle' => 'lifecycle', 'tier' => 'value_tier', 'loyalty_tier' => 'loyalty_tier'] as $param => $field)
		{
			if (!empty($f[$param]))
			{
				$list = array_filter($list, fn($c) => (string)$c[$field] === (string)$f[$param]);
			}
		}
		if (!empty($f['quality']))
		{
			$list = array_filter($list, fn($c) => $c['quality']['key'] === $f['quality']);
		}
		if (isset($f['active']) && $f['active'] !== '')
		{
			$list = array_filter($list, fn($c) => intval($c['active']) === intval($f['active']));
		}
		$sort = $f['sort'] ?? 'revenue';
		$sorters = [
			'name' => fn($a, $b) => strcmp($a['name_norm'] ?? '', $b['name_norm'] ?? ''),
			'revenue' => fn($a, $b) => $b['revenue'] <=> $a['revenue'],
			'recency' => fn($a, $b) => ($a['recency_days'] ?? PHP_INT_MAX) <=> ($b['recency_days'] ?? PHP_INT_MAX),
			'tx' => fn($a, $b) => $b['tx'] <=> $a['tx'],
			'ticket' => fn($a, $b) => $b['avg_ticket'] <=> $a['avg_ticket'],
			'points' => fn($a, $b) => $b['points_balance'] <=> $a['points_balance']
		];
		uasort($list, $sorters[$sort] ?? $sorters['revenue']);
		return array_values(array_map(fn($c) => $this->Protect($c), $list));
	}

	// ------------------------------------------------------------------ ficha individual

	public function Profile(int $id, ?int $branchId = null): array
	{
		$raw = $this->Row('SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON b.id = c.branch_id WHERE c.id = ?', [$id]);
		if (!$raw || intval($raw['is_anonymous']) === 1)
		{
			throw new \Exception('Cliente no encontrado.');
		}
		if (!empty($raw['merged_into_id']))
		{
			return ['merged_into_id' => intval($raw['merged_into_id'])];
		}
		$metrics = $this->CustomersWithMetrics($branchId)[$id] ?? null;
		if ($metrics === null)
		{
			throw new \Exception('El cliente no pertenece a las sucursales que puede consultar.');
		}
		$customer = $this->Protect($metrics);
		if ($customer['pii_visible'])
		{
			$this->Audit($id, 'view_pii');
		}

		$branch = $this->BranchSql($branchId);
		$history = $this->Rows("SELECT s.id, s.sale_number, s.created_timestamp AS ts, s.total_amount, s.discount_amount, s.payment_method, b.name AS branch_name,
				(SELECT COUNT(*) FROM sale_items i WHERE i.sale_id = s.id) AS lines, IFNULL((SELECT SUM(amount) FROM daily_pos_returns r WHERE r.sale_id = s.id), 0) AS returned
			FROM sales s LEFT JOIN branches b ON b.id = s.branch_id
			WHERE s.customer_id = ? AND s.status = 'completed' $branch ORDER BY s.created_timestamp DESC, s.id DESC LIMIT 200", [$id]);
		$monthly = $this->Rows("SELECT strftime('%Y-%m', s.created_timestamp) AS ym, COUNT(*) AS tx, SUM(s.total_amount) AS revenue
			FROM sales s WHERE s.customer_id = ? AND s.status = 'completed' AND s.created_timestamp >= ? $branch GROUP BY ym ORDER BY ym", [$id, date('Y-m-01', strtotime('-11 months'))]);
		$departments = $this->Rows("SELECT IFNULL(NULLIF(p.department, ''), 'General') AS department, SUM(i.subtotal) AS revenue, COUNT(DISTINCT s.id) AS tickets
			FROM sale_items i JOIN sales s ON s.id = i.sale_id JOIN products p ON p.id = i.product_id
			WHERE s.customer_id = ? AND s.status = 'completed' $branch GROUP BY 1 ORDER BY revenue DESC LIMIT 6", [$id]);
		$products = $this->Rows("SELECT p.name, SUM(i.amount) AS qty, SUM(i.subtotal) AS revenue, COUNT(DISTINCT s.id) AS tickets, MAX(s.created_timestamp) AS last_ts
			FROM sale_items i JOIN sales s ON s.id = i.sale_id JOIN products p ON p.id = i.product_id
			WHERE s.customer_id = ? AND s.status = 'completed' $branch GROUP BY p.id ORDER BY tickets DESC, revenue DESC LIMIT 8", [$id]);
		$orders = $this->Row("SELECT COUNT(*) AS total, SUM(CASE WHEN status NOT IN ('dispatched','cancelled') THEN 1 ELSE 0 END) AS open FROM customer_orders WHERE customer_id = ?", [$id]);

		return [
			'customer' => $customer,
			'history' => $history,
			'monthly' => $monthly,
			'departments' => $departments,
			'products' => $products,
			'orders' => ['total' => intval($orders['total'] ?? 0), 'open' => intval($orders['open'] ?? 0)],
			'consents' => $this->Consents($id),
			'preferences' => $this->Preferences($id),
			'interactions' => $this->Rows('SELECT i.*, u.username FROM customer_interactions i LEFT JOIN users u ON u.id = i.user_id WHERE i.customer_id = ? ORDER BY i.created_at DESC, i.id DESC LIMIT 100', [$id]),
			'requests' => $this->Requests(['customer_id' => $id]),
			'loyalty' => $this->Loyalty($id),
			'duplicates' => array_values(array_filter($this->DuplicateGroups(), fn($g) => in_array($id, array_column($g['customers'], 'id'), true))),
			'audit' => self::CanManage() ? $this->Rows('SELECT a.*, u.username FROM customer_audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.customer_id = ? ORDER BY a.id DESC LIMIT 40', [$id]) : null
		];
	}

	// ------------------------------------------------------------------ alta, edición y duplicados

	public function SaveCustomer(array $d): array
	{
		$db = $this->Db();
		$id = intval($d['id'] ?? 0) ?: null;
		$existing = $id ? $this->Row('SELECT * FROM customers WHERE id = ? AND merged_into_id IS NULL AND is_anonymous = 0', [$id]) : null;
		if ($id && !$existing)
		{
			throw new \Exception('Cliente no encontrado o fusionado.');
		}
		if ($id)
		{
			$this->RequireManage();
		}
		$pii = self::CanSeePii();
		$val = fn(string $k) => trim((string)($d[$k] ?? '')) ?: null;

		$row = [
			'name' => $val('name'),
			'customer_type' => in_array($d['customer_type'] ?? '', ['Minorista', 'Comercial', 'Corporativo', 'Institucional'], true) ? $d['customer_type'] : 'Minorista',
			'branch_id' => intval($d['branch_id'] ?? 0) ?: null,
			'tags' => $val('tags'),
			'notes' => $val('notes'),
			'credit_limit' => ($d['credit_limit'] ?? '') !== '' ? max(0.0, round(floatval($d['credit_limit']), 2)) : null
		];
		// Los datos personales solo se escriben con permiso de verlos (o al dar de alta); un rol sin permiso no puede
		// reemplazar lo que no ve
		$piiFields = ['tax_id', 'email', 'phone', 'address', 'birth_date'];
		foreach ($piiFields as $k)
		{
			$row[$k] = (!$existing || $pii) ? $val($k) : $existing[$k];
		}
		if (!$row['name'] || mb_strlen($row['name']) < 3)
		{
			throw new \Exception('Indique el nombre completo o la razón social del cliente.');
		}
		if ($row['email'] !== null && !filter_var($row['email'], FILTER_VALIDATE_EMAIL))
		{
			throw new \Exception('El correo electrónico no es válido.');
		}
		if ($row['birth_date'] !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['birth_date']) || $row['birth_date'] > date('Y-m-d')))
		{
			throw new \Exception('La fecha de nacimiento no es válida.');
		}
		$norm = self::NormalizeIdentifiers($row);
		if ($row['phone'] !== null && $norm['phone_norm'] === null)
		{
			throw new \Exception('El teléfono debe tener al menos 7 dígitos.');
		}
		if ($row['tax_id'] !== null && $norm['tax_id_norm'] === null)
		{
			throw new \Exception('El documento debe tener al menos 5 caracteres alfanuméricos.');
		}
		// Un cliente identificado necesita al menos un identificador fuerte; solo el nombre no basta
		if (!$norm['tax_id_norm'] && !$norm['email_norm'] && !$norm['phone_norm'])
		{
			throw new \Exception('Registre al menos un identificador: documento, correo o teléfono. Con el nombre solo no se puede distinguir al cliente.');
		}

		$dups = $this->FindMatches($norm, $id);
		$hard = array_filter($dups, fn($m) => in_array('documento', $m['reasons'], true));
		if ($hard)
		{
			$m = reset($hard);
			throw new \Exception('Ya existe un cliente con ese documento (#' . $m['id'] . ' ' . $m['name'] . '). Abra esa ficha o fusione los registros.');
		}
		if ($dups && empty($d['confirm_duplicate']))
		{
			return ['saved' => false, 'duplicate_warning' => true, 'duplicates' => array_values($dups),
				'message' => 'Hay clientes con datos coincidentes. Revíselos antes de crear otro registro.'];
		}

		$row += $norm;
		$row['updated_at'] = date('Y-m-d H:i:s');
		if ($existing)
		{
			$changed = array_values(array_filter(array_keys($row), fn($k) => !in_array($k, ['updated_at', 'tax_id_norm', 'email_norm', 'phone_norm', 'name_norm'], true) && (string)$existing[$k] !== (string)$row[$k]));
			$sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($row)));
			$db->prepare("UPDATE customers SET $sets WHERE id = :id")->execute(array_combine(array_map(fn($k) => ":$k", array_keys($row)), array_values($row)) + [':id' => $id]);
			// Solo nombres de campos: el registro de auditoría no copia datos personales
			$this->Audit($id, 'update', ['fields' => $changed]);
		}
		else
		{
			$row['active'] = 1;
			$row['created_by_user_id'] = defined('GROCY_USER_ID') ? GROCY_USER_ID : null;
			$cols = array_keys($row);
			$db->prepare('INSERT INTO customers (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_map(fn($k) => ":$k", $cols)) . ')')
				->execute(array_combine(array_map(fn($k) => ":$k", $cols), array_values($row)));
			$id = intval($db->lastInsertId());
			$this->Audit($id, 'create', ['duplicate_confirmed' => !empty($dups)]);
			if (!empty($d['consent_data_processing']))
			{
				$this->SetConsent($id, 'data_processing', true, $d['consent_source'] ?? 'store', null);
			}
		}
		return ['saved' => true, 'id' => $id];
	}

	public function SetActive(int $id, bool $active): array
	{
		$this->RequireManage();
		$this->Db()->prepare('UPDATE customers SET active = ?, updated_at = ? WHERE id = ? AND is_anonymous = 0 AND merged_into_id IS NULL')->execute([$active ? 1 : 0, date('Y-m-d H:i:s'), $id]);
		$this->Audit($id, 'update', ['fields' => ['active'], 'active' => $active]);
		return ['ok' => true];
	}

	/** Coincidencias con otros clientes vigentes por identificador normalizado o nombre idéntico */
	private function FindMatches(array $norm, ?int $exceptId): array
	{
		$matches = [];
		foreach (['tax_id_norm' => 'documento', 'email_norm' => 'correo', 'phone_norm' => 'teléfono', 'name_norm' => 'nombre'] as $col => $reason)
		{
			if (empty($norm[$col]))
			{
				continue;
			}
			foreach ($this->Rows("SELECT id, name FROM customers WHERE $col = ? AND id != ? AND is_anonymous = 0 AND merged_into_id IS NULL", [$norm[$col], $exceptId ?? 0]) as $r)
			{
				$matches[$r['id']] ??= ['id' => intval($r['id']), 'name' => $r['name'], 'reasons' => []];
				$matches[$r['id']]['reasons'][] = $reason;
			}
		}
		return $matches;
	}

	/** Grupos de posibles duplicados (componentes conexos por documento, correo, teléfono o nombre idéntico) */
	public function DuplicateGroups(): array
	{
		$rows = $this->Rows('SELECT id, name, tax_id_norm, email_norm, phone_norm, name_norm, created_timestamp FROM customers WHERE is_anonymous = 0 AND merged_into_id IS NULL');
		$parent = [];
		$find = function ($x) use (&$parent, &$find) { return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x])); };
		$reasons = [];
		foreach ($rows as $r)
		{
			$parent[$r['id']] = $r['id'];
		}
		foreach (['tax_id_norm' => 'documento', 'email_norm' => 'correo', 'phone_norm' => 'teléfono', 'name_norm' => 'nombre'] as $col => $label)
		{
			$seen = [];
			foreach ($rows as $r)
			{
				if (empty($r[$col]))
				{
					continue;
				}
				if (isset($seen[$r[$col]]))
				{
					$a = $find($seen[$r[$col]]);
					$b = $find($r['id']);
					$parent[$b] = $a;
					$reasons[$r['id']][$label] = true;
					$reasons[$seen[$r[$col]]][$label] = true;
				}
				else
				{
					$seen[$r[$col]] = $r['id'];
				}
			}
		}
		$groups = [];
		foreach ($rows as $r)
		{
			$groups[$find($r['id'])][] = $r;
		}
		$out = [];
		foreach ($groups as $g)
		{
			if (count($g) < 2)
			{
				continue;
			}
			$why = [];
			foreach ($g as $r)
			{
				$why += $reasons[$r['id']] ?? [];
			}
			$out[] = [
				'reasons' => array_keys($why),
				// Coincidencia solo por nombre: requiere revisión humana, no es evidencia suficiente
				'confidence' => array_diff(array_keys($why), ['nombre']) ? 'alta' : 'baja',
				'customers' => array_map(fn($r) => [
					'id' => intval($r['id']), 'name' => $r['name'], 'created' => $r['created_timestamp'],
					'quality' => self::IdentificationQuality($r)['label'],
					'tx' => intval($this->Row("SELECT COUNT(*) AS n FROM sales WHERE customer_id = ? AND status = 'completed'", [$r['id']])['n'])
				], $g)
			];
		}
		return $out;
	}

	/** Fusiona $sourceId en $targetId: traslada todo el historial y deja el origen inactivo apuntando al destino */
	public function Merge(int $sourceId, int $targetId): array
	{
		$this->RequireManage();
		if ($sourceId === $targetId)
		{
			throw new \Exception('Elija dos clientes distintos.');
		}
		$src = $this->Row('SELECT * FROM customers WHERE id = ? AND is_anonymous = 0 AND merged_into_id IS NULL', [$sourceId]);
		$dst = $this->Row('SELECT * FROM customers WHERE id = ? AND is_anonymous = 0 AND merged_into_id IS NULL', [$targetId]);
		if (!$src || !$dst)
		{
			throw new \Exception('Uno de los clientes no existe, es el cliente de mostrador o ya fue fusionado.');
		}
		if ($src['tax_id_norm'] && $dst['tax_id_norm'] && $src['tax_id_norm'] !== $dst['tax_id_norm'])
		{
			throw new \Exception('Los clientes tienen documentos distintos: no son la misma persona o empresa.');
		}
		$db = $this->Db();
		$own = !$db->inTransaction();
		$own && $db->beginTransaction();
		try
		{
			$moved = [];
			foreach (['sales', 'customer_orders', 'customer_returns_exchanges', 'pos_held_sales', 'daily_pos_returns', 'daily_credit_notes',
				'customer_requests', 'customer_interactions', 'customer_loyalty_ledger', 'customer_consents'] as $t)
			{
				$st = $db->prepare("UPDATE $t SET customer_id = ? WHERE customer_id = ?");
				$st->execute([$targetId, $sourceId]);
				$moved[$t] = $st->rowCount();
			}
			$db->prepare('INSERT OR IGNORE INTO customer_preferences (customer_id, pref_key, pref_value, updated_by, updated_at)
				SELECT ?, pref_key, pref_value, updated_by, updated_at FROM customer_preferences WHERE customer_id = ?')->execute([$targetId, $sourceId]);
			$db->prepare('DELETE FROM customer_preferences WHERE customer_id = ?')->execute([$sourceId]);
			// El destino completa los datos que le falten con los del origen
			$fill = [];
			foreach (['tax_id', 'email', 'phone', 'address', 'birth_date', 'branch_id', 'tags', 'notes'] as $k)
			{
				if (empty($dst[$k]) && !empty($src[$k]))
				{
					$fill[$k] = $src[$k];
				}
			}
			if ($fill)
			{
				$merged = array_merge($dst, $fill);
				$fill += array_intersect_key(self::NormalizeIdentifiers($merged), ['tax_id_norm' => 1, 'email_norm' => 1, 'phone_norm' => 1]);
				$sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fill)));
				$db->prepare("UPDATE customers SET $sets WHERE id = ?")->execute([...array_values($fill), $targetId]);
			}
			$db->prepare('UPDATE customers SET merged_into_id = ?, merged_at = ?, active = 0, updated_at = ? WHERE id = ?')->execute([$targetId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $sourceId]);
			$this->Audit($targetId, 'merge', ['from' => $sourceId, 'moved' => $moved, 'filled' => array_keys(array_diff_key($fill, ['tax_id_norm' => 1, 'email_norm' => 1, 'phone_norm' => 1]))]);
			$this->Audit($sourceId, 'merge', ['into' => $targetId]);
			$own && $db->commit();
		}
		catch (\Throwable $e)
		{
			$own && $db->rollBack();
			throw $e;
		}
		return ['ok' => true, 'target_id' => $targetId, 'moved' => $moved];
	}

	// ------------------------------------------------------------------ consentimientos y preferencias

	public function Consents(int $id): array
	{
		$hist = $this->Rows('SELECT c.*, u.username FROM customer_consents c LEFT JOIN users u ON u.id = c.user_id WHERE c.customer_id = ? ORDER BY c.id DESC', [$id]);
		$current = [];
		foreach (self::CONSENT_PURPOSES as $k => $label)
		{
			$last = null;
			foreach ($hist as $h)
			{
				if ($h['purpose'] === $k)
				{
					$last = $h;
					break;
				}
			}
			$current[$k] = ['label' => $label, 'granted' => $last ? intval($last['granted']) === 1 : null, 'at' => $last['created_at'] ?? null, 'source' => $last['source'] ?? null];
		}
		return ['current' => $current, 'history' => $hist];
	}

	public function SetConsent(int $id, string $purpose, bool $granted, string $source, ?string $evidence): array
	{
		if (!isset(self::CONSENT_PURPOSES[$purpose]) || !isset(self::CONSENT_SOURCES[$source]))
		{
			throw new \Exception('Finalidad u origen de consentimiento no válidos.');
		}
		$this->AssertCustomer($id);
		$this->Db()->prepare('INSERT INTO customer_consents (customer_id, purpose, granted, source, evidence, user_id) VALUES (?, ?, ?, ?, ?, ?)')
			->execute([$id, $purpose, $granted ? 1 : 0, $source, trim((string)$evidence) ?: null, defined('GROCY_USER_ID') ? GROCY_USER_ID : null]);
		$this->Audit($id, 'consent', ['purpose' => $purpose, 'granted' => $granted, 'source' => $source]);
		return ['ok' => true, 'consents' => $this->Consents($id)];
	}

	public function Preferences(int $id): array
	{
		return array_column($this->Rows('SELECT pref_key, pref_value FROM customer_preferences WHERE customer_id = ?', [$id]), 'pref_value', 'pref_key');
	}

	public function SetPreferences(int $id, array $prefs): array
	{
		$this->AssertCustomer($id);
		$st = $this->Db()->prepare('INSERT INTO customer_preferences (customer_id, pref_key, pref_value, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)
			ON CONFLICT (customer_id, pref_key) DO UPDATE SET pref_value = excluded.pref_value, updated_by = excluded.updated_by, updated_at = excluded.updated_at');
		$changed = [];
		foreach (self::PREFERENCES as $k => $meta)
		{
			if (!array_key_exists($k, $prefs))
			{
				continue;
			}
			$v = trim((string)$prefs[$k]);
			if ($meta['options'] !== null && !array_key_exists($v, $meta['options']))
			{
				throw new \Exception('Valor no válido para ' . $meta['label'] . '.');
			}
			$st->execute([$id, $k, mb_substr($v, 0, 500) ?: null, defined('GROCY_USER_ID') ? GROCY_USER_ID : null, date('Y-m-d H:i:s')]);
			$changed[] = $k;
		}
		$this->Audit($id, 'preference', ['fields' => $changed]);
		return ['ok' => true, 'preferences' => $this->Preferences($id)];
	}

	private function AssertCustomer(int $id): array
	{
		$c = $this->Row('SELECT * FROM customers WHERE id = ? AND is_anonymous = 0 AND merged_into_id IS NULL', [$id]);
		if (!$c)
		{
			throw new \Exception('Cliente no encontrado.');
		}
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null && !in_array(intval($c['branch_id']), $allowed, true)
			&& !$this->Row('SELECT 1 AS x FROM sales WHERE customer_id = ? AND branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . ') LIMIT 1', [$id]))
		{
			throw new \Exception('El cliente no pertenece a las sucursales que puede gestionar.');
		}
		return $c;
	}

	// ------------------------------------------------------------------ historial de atención y solicitudes

	public function AddInteraction(int $id, array $d): array
	{
		$c = $this->AssertCustomer($id);
		$kind = (string)($d['kind'] ?? '');
		$summary = trim((string)($d['summary'] ?? ''));
		if (!isset(self::INTERACTION_KINDS[$kind]) || mb_strlen($summary) < 5)
		{
			throw new \Exception('Indique el tipo de contacto y un resumen de al menos 5 caracteres.');
		}
		$requestId = intval($d['request_id'] ?? 0) ?: null;
		if ($requestId && !$this->Row('SELECT 1 AS x FROM customer_requests WHERE id = ? AND customer_id = ?', [$requestId, $id]))
		{
			throw new \Exception('La solicitud no pertenece a este cliente.');
		}
		$this->Db()->prepare('INSERT INTO customer_interactions (customer_id, kind, summary, outcome, request_id, branch_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
			->execute([$id, $kind, mb_substr($summary, 0, 2000), trim((string)($d['outcome'] ?? '')) ?: null, $requestId, intval($d['branch_id'] ?? 0) ?: ($c['branch_id'] ?: null), defined('GROCY_USER_ID') ? GROCY_USER_ID : null]);
		$this->Audit($id, 'interaction', ['kind' => $kind]);
		return ['ok' => true];
	}

	public function Requests(array $f = []): array
	{
		$where = ['1 = 1'];
		$p = [];
		if (!empty($f['customer_id']))
		{
			$where[] = 'q.customer_id = ?';
			$p[] = intval($f['customer_id']);
		}
		if (!empty($f['category']) && isset(self::REQUEST_CATEGORIES[$f['category']]))
		{
			$where[] = 'q.category = ?';
			$p[] = $f['category'];
		}
		if (!empty($f['status']))
		{
			if ($f['status'] === 'pending')
			{
				$where[] = "q.status IN ('open','in_progress','waiting_customer')";
			}
			else
			{
				$where[] = 'q.status = ?';
				$p[] = $f['status'];
			}
		}
		if (!empty($f['days']) && in_array(intval($f['days']), [30, 90, 365], true))
		{
			$where[] = "(q.created_at >= ? OR q.status IN ('open','in_progress','waiting_customer'))";
			$p[] = date('Y-m-d H:i:s', strtotime('-' . intval($f['days']) . ' days'));
		}
		$scope = $this->BranchSql(intval($f['branch_id'] ?? 0) ?: null, 'q.branch_id');
		if ($scope !== '')
		{
			$where[] = '(q.branch_id IS NULL' . str_replace(' AND ', ' OR ', $scope) . ')';
		}
		$rows = $this->Rows('SELECT q.*, c.name AS customer_name, u.username AS assigned_to, cu.username AS created_by_name, fu.username AS financial_decided_by_name, b.name AS branch_name
			FROM customer_requests q JOIN customers c ON c.id = q.customer_id LEFT JOIN users u ON u.id = q.assigned_user_id
			LEFT JOIN users cu ON cu.id = q.created_by LEFT JOIN users fu ON fu.id = q.financial_decided_by LEFT JOIN branches b ON b.id = q.branch_id
			WHERE ' . implode(' AND ', $where) . " ORDER BY CASE WHEN q.status IN ('open','in_progress','waiting_customer') THEN 0 ELSE 1 END,
			CASE q.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, q.due_date IS NULL, q.due_date, q.id DESC LIMIT 300", $p);
		$today = date('Y-m-d');
		foreach ($rows as &$r)
		{
			$r['overdue'] = in_array($r['status'], ['open', 'in_progress', 'waiting_customer'], true) && $r['due_date'] && $r['due_date'] < $today;
		}
		return $rows;
	}

	public function SaveRequest(array $d): array
	{
		$db = $this->Db();
		$id = intval($d['id'] ?? 0) ?: null;
		$uid = defined('GROCY_USER_ID') ? GROCY_USER_ID : null;
		if ($id)
		{
			$cur = $this->Row('SELECT * FROM customer_requests WHERE id = ?', [$id]);
			if (!$cur)
			{
				throw new \Exception('Solicitud no encontrada.');
			}
			$this->AssertCustomer(intval($cur['customer_id']));
			$status = (string)($d['status'] ?? $cur['status']);
			if (!isset(self::REQUEST_STATUSES[$status]))
			{
				throw new \Exception('Estado no válido.');
			}
			if ($cur['category'] === 'credit' && $status === 'resolved' && ($cur['financial_status'] ?? 'pending_finance') === 'pending_finance')
			{
				throw new \Exception('Una solicitud de crédito debe recibir aprobación o rechazo financiero antes de cerrarse como resuelta.');
			}
			$resolution = trim((string)($d['resolution'] ?? $cur['resolution'] ?? ''));
			if (in_array($status, ['resolved', 'cancelled'], true) && mb_strlen($resolution) < 5)
			{
				throw new \Exception('Para cerrar la solicitud describa la resolución o el motivo de anulación.');
			}
			$assigned = array_key_exists('assigned_user_id', $d) ? (intval($d['assigned_user_id']) ?: null) : $cur['assigned_user_id'];
			$due = array_key_exists('due_date', $d) ? ($d['due_date'] ?: null) : $cur['due_date'];
			$dispatchId = array_key_exists('dispatch_order_id', $d) ? (intval($d['dispatch_order_id']) ?: null) : ($cur['dispatch_order_id'] ?? null);
			$db->prepare('UPDATE customer_requests SET status = ?, resolution = ?, assigned_user_id = ?, due_date = ?, dispatch_order_id = ?, updated_at = ?, resolved_at = ? WHERE id = ?')
				->execute([$status, $resolution ?: null, $assigned, $due, $dispatchId, date('Y-m-d H:i:s'), in_array($status, ['resolved', 'cancelled'], true) ? ($cur['resolved_at'] ?: date('Y-m-d H:i:s')) : null, $id]);
			if ($status !== $cur['status'])
			{
				$db->prepare('INSERT INTO customer_interactions (customer_id, kind, summary, outcome, request_id, branch_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
					->execute([$cur['customer_id'], 'request', $cur['code'] . ': ' . self::REQUEST_STATUSES[$cur['status']] . ' → ' . self::REQUEST_STATUSES[$status], $resolution ?: null, $id, $cur['branch_id'], $uid]);
			}
			$this->Audit(intval($cur['customer_id']), 'request', ['id' => $id, 'status' => $status]);
			return ['ok' => true, 'id' => $id];
		}

		$customerId = intval($d['customer_id'] ?? 0);
		$c = $this->AssertCustomer($customerId);
		$category = (string)($d['category'] ?? '');
		$priority = (string)($d['priority'] ?? 'normal');
		$subject = trim((string)($d['subject'] ?? ''));
		if (!isset(self::REQUEST_CATEGORIES[$category]) || !isset(self::REQUEST_PRIORITIES[$priority]) || mb_strlen($subject) < 5)
		{
			throw new \Exception('Indique categoría, prioridad y un asunto de al menos 5 caracteres.');
		}
		$due = ($d['due_date'] ?? '') ?: date('Y-m-d', strtotime(['urgent' => '+1 day', 'high' => '+2 days', 'normal' => '+5 days', 'low' => '+10 days'][$priority]));
		$saleId = intval($d['sale_id'] ?? 0) ?: null;
		if ($saleId && !$this->Row('SELECT 1 AS x FROM sales WHERE id = ? AND customer_id = ?', [$saleId, $customerId]))
		{
			throw new \Exception('La venta indicada no pertenece a este cliente.');
		}
		$dispatchId = intval($d['dispatch_order_id'] ?? 0) ?: null;
		$reqCreditLimit = $category === 'credit' && isset($d['requested_credit_limit']) && $d['requested_credit_limit'] !== ''
			? max(0.0, round(floatval($d['requested_credit_limit']), 2))
			: null;
		$finStatus = $category === 'credit' ? 'pending_finance' : null;
		$branchId = intval($d['branch_id'] ?? 0) ?: ($c['branch_id'] ? intval($c['branch_id']) : null);
		$own = !$db->inTransaction();
		$own && $db->beginTransaction();
		try
		{
			$seq = intval($this->Row("SELECT COUNT(*) AS n FROM customer_requests WHERE code LIKE ?", ['SOL-' . date('Y') . '-%'])['n']) + 1;
			$code = sprintf('SOL-%s-%04d', date('Y'), $seq);
			$db->prepare('INSERT INTO customer_requests (code, customer_id, branch_id, category, subject, detail, priority, assigned_user_id, due_date, sale_id, dispatch_order_id, requested_credit_limit, financial_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
				->execute([$code, $customerId, $branchId, $category, mb_substr($subject, 0, 200), trim((string)($d['detail'] ?? '')) ?: null, $priority, intval($d['assigned_user_id'] ?? 0) ?: null, $due, $saleId, $dispatchId, $reqCreditLimit, $finStatus, $uid]);
			$newId = intval($db->lastInsertId());
			$db->prepare('INSERT INTO customer_interactions (customer_id, kind, summary, request_id, branch_id, user_id) VALUES (?, ?, ?, ?, ?, ?)')
				->execute([$customerId, 'request', $code . ' registrada: ' . $subject, $newId, $branchId, $uid]);
			$this->Audit($customerId, 'request', ['id' => $newId, 'created' => true, 'category' => $category]);
			$own && $db->commit();
		}
		catch (\Throwable $e)
		{
			$own && $db->rollBack();
			throw $e;
		}
		return ['ok' => true, 'id' => $newId, 'code' => $code];
	}

	/** Aprobación financiera estrictamente separada del CRM para solicitudes de crédito (ADMIN / BUDGET_MANAGE) */
	public function DecideCreditRequest(int $requestId, string $decision, string $note = '', ?float $approvedLimit = null): array
	{
		if (!self::CanApproveFinance())
		{
			throw new \Exception('Solo Finanzas / Administración puede aprobar o rechazar solicitudes de crédito.');
		}
		if (!in_array($decision, ['approved_finance', 'rejected_finance'], true))
		{
			throw new \Exception('Decisión financiera no válida.');
		}
		$cur = $this->Row('SELECT * FROM customer_requests WHERE id = ?', [$requestId]);
		if (!$cur)
		{
			throw new \Exception('Solicitud no encontrada.');
		}
		if ($cur['category'] !== 'credit')
		{
			throw new \Exception('Esta solicitud no corresponde a evaluación de crédito.');
		}
		$note = trim($note);
		if (mb_strlen($note) < 5)
		{
			throw new \Exception('Indique el sustento financiero (al menos 5 caracteres).');
		}
		$uid = defined('GROCY_USER_ID') ? GROCY_USER_ID : null;
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		$own = !$db->inTransaction();
		$own && $db->beginTransaction();
		try
		{
			$newLimit = $approvedLimit !== null ? max(0.0, round($approvedLimit, 2)) : ($cur['requested_credit_limit'] !== null ? floatval($cur['requested_credit_limit']) : null);
			$finalStatus = $decision === 'approved_finance' ? 'resolved' : 'cancelled';
			$resolution = '[Finanzas: ' . self::FINANCIAL_STATUSES[$decision] . ($decision === 'approved_finance' && $newLimit !== null ? ' · Cupo ' . number_format($newLimit, 2) : '') . '] ' . $note;
			$db->prepare('UPDATE customer_requests SET financial_status = ?, financial_decided_by = ?, financial_decided_at = ?, financial_note = ?, status = ?, resolution = ?, updated_at = ?, resolved_at = ? WHERE id = ?')
				->execute([$decision, $uid, $now, $note, $finalStatus, $resolution, $now, $now, $requestId]);
			if ($decision === 'approved_finance' && $newLimit !== null)
			{
				$db->prepare('UPDATE customers SET credit_limit = ? WHERE id = ?')->execute([$newLimit, intval($cur['customer_id'])]);
			}
			$db->prepare('INSERT INTO customer_interactions (customer_id, kind, summary, outcome, request_id, branch_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
				->execute([$cur['customer_id'], 'request', $cur['code'] . ' — Evaluación financiera: ' . self::FINANCIAL_STATUSES[$decision], $note, $requestId, $cur['branch_id'], $uid]);
			$this->Audit(intval($cur['customer_id']), 'credit_decision', ['request_id' => $requestId, 'decision' => $decision, 'limit' => $newLimit]);
			$own && $db->commit();
		}
		catch (\Throwable $e)
		{
			$own && $db->rollBack();
			throw $e;
		}
		return ['ok' => true, 'decision' => $decision];
	}

	// ------------------------------------------------------------------ fidelización (si el plan la contempla)

	public function Loyalty(int $id): array
	{
		$cfg = $this->Settings();
		if (!$cfg['loyalty_enabled'])
		{
			return ['enabled' => false];
		}
		$this->SyncEarned($id);
		$bal = intval($this->Row('SELECT IFNULL(SUM(points), 0) AS b FROM customer_loyalty_ledger WHERE customer_id = ?', [$id])['b']);
		return [
			'enabled' => true,
			'balance' => $bal,
			'balance_value' => round($bal * $cfg['point_value'], 2),
			'ledger' => $this->Rows('SELECT l.*, u.username, s.sale_number FROM customer_loyalty_ledger l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN sales s ON s.id = l.sale_id
				WHERE l.customer_id = ? ORDER BY l.created_at DESC, l.id DESC LIMIT 60', [$id]),
			'settings' => $cfg
		];
	}

	/** Acumula puntos de compras netas desde el inicio del programa (idempotente por venta) */
	public function SyncEarned(?int $customerId = null): int
	{
		$cfg = $this->Settings();
		if (!$cfg['loyalty_enabled'])
		{
			return 0;
		}
		$st = $this->Db()->prepare("INSERT OR IGNORE INTO customer_loyalty_ledger (customer_id, kind, points, sale_id, reason, created_at)
			SELECT s.customer_id, 'earn', CAST((s.total_amount - IFNULL((SELECT SUM(amount) FROM daily_pos_returns r WHERE r.sale_id = s.id), 0)) / ? AS INTEGER), s.id, 'Compra ' || s.sale_number, s.created_timestamp
			FROM sales s JOIN customers c ON c.id = s.customer_id AND c.is_anonymous = 0 AND c.merged_into_id IS NULL
			WHERE s.status = 'completed' AND date(s.created_timestamp) >= ?" . ($customerId ? ' AND s.customer_id = ' . intval($customerId) : '') . "
			AND CAST((s.total_amount - IFNULL((SELECT SUM(amount) FROM daily_pos_returns r WHERE r.sale_id = s.id), 0)) / ? AS INTEGER) > 0");
		$st->execute([$cfg['amount_per_point'], $cfg['loyalty_start'], $cfg['amount_per_point']]);
		return $st->rowCount();
	}

	public function LoyaltyMove(int $id, string $kind, int $points, string $reason): array
	{
		$this->RequireManage();
		$this->AssertCustomer($id);
		if (!$this->Settings()['loyalty_enabled'])
		{
			throw new \Exception('El programa de puntos no está activo.');
		}
		if (!in_array($kind, ['redeem', 'adjust'], true) || $points === 0 || mb_strlen(trim($reason)) < 5)
		{
			throw new \Exception('Indique puntos distintos de cero y un motivo de al menos 5 caracteres.');
		}
		$this->SyncEarned($id);
		$bal = intval($this->Row('SELECT IFNULL(SUM(points), 0) AS b FROM customer_loyalty_ledger WHERE customer_id = ?', [$id])['b']);
		$delta = $kind === 'redeem' ? -abs($points) : $points;
		if ($bal + $delta < 0)
		{
			throw new \Exception("Saldo insuficiente: el cliente tiene $bal puntos.");
		}
		$this->Db()->prepare('INSERT INTO customer_loyalty_ledger (customer_id, kind, points, reason, user_id) VALUES (?, ?, ?, ?, ?)')
			->execute([$id, $kind, $delta, trim($reason), defined('GROCY_USER_ID') ? GROCY_USER_ID : null]);
		$this->Audit($id, 'loyalty', ['kind' => $kind, 'points' => $delta]);
		return ['ok' => true, 'loyalty' => $this->Loyalty($id)];
	}

	/** Resumen global de fidelización para Tab 4 (solo configuración, saldos e historial; el canje operativo ocurre en POS) */
	public function LoyaltyOverview(?int $branchId = null, int $days = 30): array
	{
		$cfg = $this->Settings();
		if ($cfg['loyalty_enabled'])
		{
			$this->SyncEarned();
		}
		$list = $this->CustomersWithMetrics($branchId, $days);
		$tiers = ['Platinum' => 0, 'Gold' => 0, 'Silver' => 0, 'Bronze' => 0];
		$totalPts = 0;
		$totalEarned = 0;
		foreach ($list as $c)
		{
			$tiers[$c['loyalty_tier']] = ($tiers[$c['loyalty_tier']] ?? 0) + 1;
			$totalPts += intval($c['points_balance']);
			$totalEarned += intval($c['points_earned']);
		}
		$balances = array_values(array_filter($list, fn($c) => $c['points_earned'] > 0 || $c['points_balance'] > 0));
		usort($balances, fn($a, $b) => $b['points_balance'] <=> $a['points_balance']);
		$balances = array_map(fn($c) => $this->Protect($c), array_slice($balances, 0, 50));

		$from = date('Y-m-d H:i:s', strtotime("-$days days"));
		$ledgerScope = $this->BranchSql($branchId, 'c.branch_id');
		$ledger = $this->Rows("SELECT l.*, c.name AS customer_name, u.username, s.sale_number
			FROM customer_loyalty_ledger l
			JOIN customers c ON c.id = l.customer_id AND IFNULL(c.is_anonymous, 0) = 0 AND c.merged_into_id IS NULL
			LEFT JOIN users u ON u.id = l.user_id
			LEFT JOIN sales s ON s.id = l.sale_id
			WHERE l.created_at >= ?" . $ledgerScope . "
			ORDER BY l.created_at DESC, l.id DESC LIMIT 80", [$from]);

		$redeemedPeriod = 0;
		$earnedPeriod = 0;
		foreach ($ledger as $row)
		{
			$pts = intval($row['points']);
			if ($pts > 0)
			{
				$earnedPeriod += $pts;
			}
			else
			{
				$redeemedPeriod += abs($pts);
			}
		}

		return [
			'enabled' => $cfg['loyalty_enabled'],
			'settings' => $cfg,
			'tiers' => $tiers,
			'total_points' => $totalPts,
			'total_earned' => $totalEarned,
			'points_liability' => round($totalPts * $cfg['point_value'], 2),
			'earned_period' => $earnedPeriod,
			'redeemed_period' => $redeemedPeriod,
			'balances' => $balances,
			'ledger' => $ledger
		];
	}

	// ------------------------------------------------------------------ campañas y retención (Tab 6)

	public function Campaigns(?int $branchId = null, int $days = 30): array
	{
		$days = in_array($days, [30, 90, 365], true) ? $days : 30;
		$list = $this->CustomersWithMetrics($branchId, $days);
		$consents = [];
		foreach ($this->Rows("SELECT cc.customer_id, cc.purpose, cc.granted
			FROM customer_consents cc
			JOIN (SELECT customer_id, purpose, MAX(id) AS mid FROM customer_consents GROUP BY customer_id, purpose) m ON m.mid = cc.id") as $r)
		{
			$consents[intval($r['customer_id'])][$r['purpose']] = intval($r['granted']) === 1;
		}
		$channelPurpose = ['whatsapp' => 'marketing_whatsapp', 'email' => 'marketing_email', 'sms' => 'marketing_sms', 'pos' => null, 'multichannel' => 'marketing_whatsapp'];
		$rows = $this->Rows('SELECT cp.*, cp.segment AS target_segment, cp.offer AS offer_text, cp.starts_at AS starts_on, cp.ends_at AS ends_on, b.name AS branch_name FROM crm_campaigns cp LEFT JOIN branches b ON b.id = cp.branch_id ORDER BY cp.id DESC');
		$net = $this->NetSalesSql($branchId);
		$out = [];
		foreach ($rows as $cp)
		{
			if ($branchId && $cp['branch_id'] && intval($cp['branch_id']) !== $branchId)
			{
				continue;
			}
			$seg = $cp['segment'] ?: 'all';
			$targets = array_filter($list, fn($c) => $seg === 'all' || $c['segment'] === $seg || $c['lifecycle'] === $seg);
			$purpose = $channelPurpose[$cp['channel']] ?? null;
			$eligible = $purpose
				? array_filter($targets, fn($c) => ($consents[intval($c['id'])][$purpose] ?? false) === true)
				: $targets;
			$startTs = ($cp['starts_at'] ?: date('Y-m-d', strtotime("-$days days"))) . ' 00:00:00';
			$ids = array_map(fn($c) => intval($c['id']), $eligible);
			$convCount = 0;
			$convRev = 0.0;
			if ($ids)
			{
				$ph = implode(',', $ids);
				$conv = $this->Row("SELECT COUNT(DISTINCT customer_id) AS buyers, IFNULL(SUM(net), 0) AS revenue FROM ($net) WHERE customer_id IN ($ph) AND ts >= ?", [$startTs]);
				$convCount = intval($conv['buyers'] ?? 0);
				$convRev = round(floatval($conv['revenue'] ?? 0), 2);
			}
			$elCount = count($eligible);
			$out[] = $cp + [
				'target_count' => count($targets),
				'eligible_count' => $elCount,
				'converted_count' => $convCount,
				'converted_revenue' => $convRev,
				'conversion_pct' => $elCount > 0 ? round($convCount / $elCount * 100, 1) : 0.0
			];
		}
		return $out;
	}

	public function SaveCampaign(array $d): array
	{
		$this->RequireManage();
		$id = intval($d['id'] ?? 0) ?: null;
		$db = $this->Db();
		if ($id && isset($d['status']) && count($d) <= 2)
		{
			$status = in_array($d['status'], ['scheduled', 'active', 'paused', 'completed'], true) ? $d['status'] : 'active';
			$db->prepare('UPDATE crm_campaigns SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, date('Y-m-d H:i:s'), $id]);
			$this->Audit(null, 'campaign', ['id' => $id, 'status' => $status]);
			return ['ok' => true, 'id' => $id];
		}
		$name = trim((string)($d['name'] ?? ''));
		$channel = (string)($d['channel'] ?? 'whatsapp');
		$seg = trim((string)($d['target_segment'] ?? $d['segment'] ?? 'all'));
		if (mb_strlen($name) < 3 || !isset(self::CAMPAIGN_CHANNELS[$channel]))
		{
			throw new \Exception('Indique nombre (mínimo 3 caracteres) y un canal válido.');
		}
		$status = in_array($d['status'] ?? 'active', ['scheduled', 'active', 'paused', 'completed'], true) ? ($d['status'] ?? 'active') : 'active';
		$startsAt = ($d['starts_on'] ?? $d['starts_at'] ?? '') ?: date('Y-m-d');
		$endsAt = ($d['ends_on'] ?? $d['ends_at'] ?? '') ?: date('Y-m-d', strtotime('+30 days'));
		$branchId = intval($d['branch_id'] ?? 0) ?: null;
		$uid = defined('GROCY_USER_ID') ? GROCY_USER_ID : null;
		$seq = intval($this->Row('SELECT COUNT(*) AS n FROM crm_campaigns')['n']) + 1;
		$code = sprintf('CMP-%s-%02d', date('Y'), $seq);
		$offer = trim((string)($d['offer_text'] ?? $d['offer'] ?? '')) ?: 'Beneficio comercial para clientes identificados';
		$db->prepare('INSERT INTO crm_campaigns (code, name, branch_id, segment, channel, offer, status, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$code, mb_substr($name, 0, 120), $branchId, $seg, $channel, $offer, $status, $startsAt, $endsAt, $uid]);
		$newId = intval($db->lastInsertId());
		$this->Audit(null, 'campaign', ['id' => $newId, 'code' => $code, 'created' => true, 'channel' => $channel]);
		return ['ok' => true, 'id' => $newId, 'code' => $code];
	}

	// ------------------------------------------------------------------ inteligencia y automatización (Tab 7)

	private function CountRuleMatches(array $rule, ?int $branchId, int $days): int
	{
		$cfg = $this->Settings();
		$list = $this->CustomersWithMetrics($branchId, $days);
		$key = $rule['trigger_type'] ?? '';
		if ($key === 'inactivity_risk')
		{
			$thresh = max(7, intval($rule['threshold_days'] ?? 30));
			return count(array_filter($list, fn($c) => $c['segment'] === 'at_risk' || ($c['recency_days'] !== null && $c['recency_days'] >= $thresh && $c['tx'] > 0)));
		}
		if ($key === 'welcome_first_purchase')
		{
			return count(array_filter($list, fn($c) => $c['lifecycle'] === 'new' || ($c['segment'] === 'potential' && $c['tx'] >= 1)));
		}
		if ($key === 'sla_escalation')
		{
			$reqScope = $this->BranchSql($branchId, 'q.branch_id');
			$reqWhere = $reqScope === '' ? '' : ' AND (q.branch_id IS NULL' . str_replace(' AND ', ' OR ', $reqScope) . ')';
			return intval($this->Row("SELECT COUNT(*) AS n FROM customer_requests q WHERE q.status IN ('open','in_progress','waiting_customer') AND q.due_date IS NOT NULL AND q.due_date < date('now','localtime')" . $reqWhere)['n']);
		}
		if ($key === 'incomplete_profile')
		{
			return count(array_filter($list, fn($c) => $c['quality']['key'] === 'insufficient'));
		}
		return count(array_filter($list, fn($c) => !empty($rule['target_segment']) && $c['segment'] === $rule['target_segment']));
	}

	public function Automations(?int $branchId = null, int $days = 30): array
	{
		$rules = $this->Rows('SELECT * FROM crm_automation_rules ORDER BY id');
		foreach ($rules as &$r)
		{
			$r['live_matches'] = $this->CountRuleMatches($r, $branchId, $days);
		}
		return $rules;
	}

	public function ToggleAutomation(int $ruleId, bool $active): array
	{
		$this->RequireManage();
		$r = $this->Row('SELECT * FROM crm_automation_rules WHERE id = ?', [$ruleId]);
		if (!$r)
		{
			throw new \Exception('Regla de automatización no encontrada.');
		}
		$this->Db()->prepare('UPDATE crm_automation_rules SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $ruleId]);
		$this->Audit(null, 'automation_toggle', ['id' => $ruleId, 'active' => $active]);
		return ['ok' => true, 'active' => $active];
	}

	public function RunAutomations(?int $branchId = null, int $days = 30): array
	{
		$this->RequireManage();
		$rules = $this->Rows('SELECT * FROM crm_automation_rules WHERE active = 1 ORDER BY id');
		$now = date('Y-m-d H:i:s');
		$st = $this->Db()->prepare('UPDATE crm_automation_rules SET last_matches_count = ?, total_runs = IFNULL(total_runs, 0) + 1, last_run_at = ? WHERE id = ?');
		$totalMatches = 0;
		foreach ($rules as $r)
		{
			$m = $this->CountRuleMatches($r, $branchId, $days);
			$st->execute([$m, $now, intval($r['id'])]);
			$totalMatches += $m;
		}
		$this->Audit(null, 'automation_run', ['rules' => count($rules), 'matches' => $totalMatches]);
		return ['ok' => true, 'rules_evaluated' => count($rules), 'total_matches' => $totalMatches, 'rules' => $this->Automations($branchId, $days)];
	}

	public function SaveSettings(array $d): array
	{
		$this->RequireManage();
		$map = [
			'loyalty_enabled' => ['crm_loyalty_enabled', fn($v) => !empty($v) && $v !== '0' ? '1' : '0'],
			'amount_per_point' => ['crm_loyalty_amount_per_point', fn($v) => (string)max(0.01, round(floatval($v), 2))],
			'point_value' => ['crm_loyalty_point_value', fn($v) => (string)max(0, round(floatval($v), 4))],
			'loyalty_start' => ['crm_loyalty_start', fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : date('Y-m-d')],
			'inactive_days' => ['crm_inactive_days', fn($v) => (string)min(365, max(7, intval($v)))],
			'new_days' => ['crm_new_days', fn($v) => (string)min(180, max(7, intval($v)))],
			'repurchase_goal_pct' => ['crm_repurchase_goal_pct', fn($v) => (string)min(95, max(5, round(floatval($v), 1)))],
			'country' => ['crm_country', fn($v) => isset(self::REGIONS[strtoupper((string)$v)]) ? strtoupper((string)$v) : 'CO']
		];
		$st = $this->Db()->prepare("INSERT INTO daily_system_settings (setting_key, setting_value, category, updated_by_user_id, updated_at) VALUES (?, ?, 'crm', ?, ?)
			ON CONFLICT (setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_by_user_id = excluded.updated_by_user_id, updated_at = excluded.updated_at");
		$changed = [];
		foreach ($map as $k => [$key, $fn])
		{
			if (array_key_exists($k, $d))
			{
				$st->execute([$key, $fn($d[$k]), defined('GROCY_USER_ID') ? GROCY_USER_ID : null, date('Y-m-d H:i:s')]);
				$changed[$k] = $fn($d[$k]);
			}
		}
		$this->Audit(null, 'settings', $changed);
		return ['ok' => true, 'settings' => $this->Settings()];
	}

	/** Exportación del directorio (sin datos personales salvo permiso), registrada en auditoría */
	public function ExportCsv(?int $branchId, array $f): string
	{
		$rows = $this->Directory($branchId, $f);
		$reg = $this->RegionalConfig();
		$this->Audit(null, 'export', ['rows' => count($rows), 'pii' => self::CanSeePii()]);
		$out = fopen('php://temp', 'r+');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, ['ID', 'Cliente', 'Tipo', 'Sucursal', $reg['tax_id_label'], 'Correo', 'Teléfono', 'Compras', 'Venta neta (' . $reg['currency_code'] . ')', 'Ticket promedio (' . $reg['currency_code'] . ')', 'Frecuencia (días)', 'Última compra', 'Ciclo de vida', 'Segmento', 'Tier', 'Puntos', 'Identificación']);
		foreach ($rows as $c)
		{
			fputcsv($out, [$c['id'], $c['name'], $c['customer_type'], $c['branch_name'], $c['tax_id'], $c['email'], $c['phone'], $c['tx'], $c['revenue'], $c['avg_ticket'],
				$c['frequency_days'], $c['last_purchase'], self::LIFECYCLE[$c['lifecycle']]['label'], self::SEGMENTS[$c['segment']], $c['value_tier'], $c['points_balance'], $c['quality']['label']]);
		}
		rewind($out);
		return stream_get_contents($out);
	}

	public function AuditLog(int $limit = 100): array
	{
		$this->RequireManage();
		return $this->Rows('SELECT a.*, u.username, c.name AS customer_name FROM customer_audit_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.id DESC LIMIT ' . intval($limit));
	}

	public function Users(): array
	{
		return $this->Rows("SELECT id, username, TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')) AS display FROM users ORDER BY username");
	}
}
