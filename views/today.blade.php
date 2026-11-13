@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Hoy')

@push('pageStyles')
<link href="{{ $U('/css/dairy_today.css?v=', true) }}{{ daily_asset_version('/css/dairy_today.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_today_executive.css?v=', true) }}{{ daily_asset_version('/css/daily_today_executive.css') }}" rel="stylesheet">
@endpush

@section('content')
@php
	// Mini-curvas con datos reales (7 días hasta la fecha); sin serie no se dibuja una tendencia inventada
	$sparkLine = function (array $v, string $grad, string $stroke) {
		$v = array_values(array_map('floatval', $v));
		if (count($v) < 2)
		{
			return '<span class="sr-only">Sin historial</span>';
		}
		[$lo, $hi] = [min($v), max($v)];
		$pts = [];
		foreach ($v as $k => $y)
		{
			$pts[] = round($k * 90 / (count($v) - 1), 1) . ',' . round($hi > $lo ? 32 - ($y - $lo) / ($hi - $lo) * 28 : 18, 1);
		}
		$d = 'M ' . implode(' L ', $pts);
		return '<svg viewBox="0 0 90 36" class="today-exec-sparkline-svg" role="img" aria-label="Últimos ' . count($v) . ' días"><path d="' . $d . ' L 90,36 L 0,36 Z" fill="url(#' . $grad . ')" /><path d="' . $d . '" fill="none" stroke="' . $stroke . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>';
	};
	$sparkBars = function (array $v, string $color) {
		$v = array_values(array_map('floatval', $v));
		if (count($v) < 2 || max($v) <= 0)
		{
			return '<span class="sr-only">Sin historial</span>';
		}
		$out = '';
		foreach ($v as $k => $y)
		{
			$h = max(2, round($y / max($v) * 34, 1));
			$out .= '<rect x="' . (2 + $k * 10) . '" y="' . (36 - $h) . '" width="6" height="' . $h . '" rx="3" fill="' . $color . '" />';
		}
		return '<svg viewBox="0 0 85 36" class="today-exec-minibars-svg" role="img" aria-label="Próximos ' . count($v) . ' días">' . $out . '</svg>';
	};
	$A = \Grocy\Helpers\DairyAccess::class;
	$s = $d['summary'];
	$y = $d['yesterday'];
	$ex = $d['execution'];
	$al = $d['alerts'];
	$ap = $d['approvals'];
	$rk = $d['risks'];
	$cmp = $d['comparison'];
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$moneyShort = fn($v) => abs($v) >= 100000 ? '$' . number_format($v / 1000, 1) . 'k' : '$' . number_format(floatval($v), 2);
	$qty = fn($v) => rtrim(rtrim(number_format(floatval($v), 2), '0'), '.');
	$docTypes = $A::DOCUMENTS;
	$link = function (string $href) use ($U, $selectedBranchId) {
		$parts = explode('#', $href, 2);
		$sep = str_contains($parts[0], '?') ? '&' : '?';
		return $U($parts[0]) . ($selectedBranchId ? $sep . 'branch_id=' . $selectedBranchId : '') . (isset($parts[1]) ? '#' . $parts[1] : '');
	};
	$canGo = fn(string $href) => $A::CanOpen(explode('#', explode('?', $href)[0])[0]);
	$docLink = fn($doc) => $doc && isset($docTypes[$doc[0]]) && $A::HasAny($docTypes[$doc[0]]) ? $U('/document/' . $doc[0] . '/' . $doc[1]) : null;
	$ago = fn($t) => $t ? \Carbon\Carbon::parse($t)->locale('es')->diffForHumans(['parts' => 1]) : null;
	$when = function (?string $t) {
		if (!$t) return '—';
		$day = substr($t, 0, 10);
		$hm = strlen($t) > 10 ? substr($t, 11, 5) : '';
		if ($day === date('Y-m-d')) return 'Hoy' . ($hm ? ' ' . $hm : '');
		if ($day === date('Y-m-d', strtotime('-1 day'))) return 'Ayer' . ($hm ? ' ' . $hm : '');
		return date('d/m', strtotime($day)) . ($hm && $hm !== '23:59' ? ' ' . $hm : '');
	};
	$initials = function (?string $n) {
		$p = preg_split('/\s+/', trim((string) $n));
		return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1)) ?: '?';
	};
	// Variación contra el último registro de ayer (solo si existe; nunca se inventa)
	$delta = function (string $key, bool $upIsBad = true, bool $isMoney = false) use ($y, $s) {
		if (!$y || ($key !== 'value_total' && !array_key_exists($key, $y))) return null;
		$cur = $key === 'value_total' ? $s['value_confirmed'] + $s['value_estimated'] : $s[$key];
		$prev = $key === 'value_total' ? ($y['value_confirmed'] ?? 0) + ($y['value_estimated'] ?? 0) : $y[$key];
		$diff = round($cur - $prev, 2);
		$arrow = $diff > 0 ? '<i class="fa-solid fa-arrow-up"></i>' : ($diff < 0 ? '<i class="fa-solid fa-arrow-down"></i>' : '');
		$txt = ($diff > 0 ? '+' : ($diff < 0 ? '−' : '±')) . ($isMoney ? '$' . number_format(abs($diff), 2) : abs($diff));
		return [$diff == 0 ? 'is-flat' : (($diff > 0) === $upIsBad ? 'is-bad' : 'is-good'), $arrow . $txt . ' vs. ayer'];
	};
	$deltaHtml = function (?array $dl) {
		return $dl ? '<span class="cc-delta ' . $dl[0] . '">' . $dl[1] . '</span>' : '<span class="cc-muted" title="La tendencia aparece cuando existe el registro de ayer de este mismo alcance">Sin histórico de ayer</span>';
	};
	$totalValue = $s['value_confirmed'] + $s['value_estimated'];
	$tasksLate = $ex['overdue'] + $ex['backlog'];
	$tasksOpen = $ex['pending'] + $ex['in_progress'] + $ex['overdue'];
	$riskActive = $rk['cold']['n'] + $rk['expiring']['n'] + $rk['quarantine']['n'] + $rk['counts']['n'] + $rk['counts']['diff'];

	// Estado operativo: reglas objetivas, explicadas en el título
	$state = $s['urgent'] > 0 ? ['Crítico', 'danger', 'Hay decisiones con prioridad crítica.']
		: ($s['overdue'] > 0 || $tasksLate > 0 ? ['Requiere atención', 'warning', 'Hay decisiones o tareas fuera de plazo, sin ninguna crítica.']
		: ['En orden', 'success', 'Sin decisiones críticas ni atrasos registrados.']);
	$pl = fn($n, $one, $many) => '<b>' . $n . '</b> ' . ($n === 1 ? $one : $many);

	$branchName = 'Red Consolidada';
	foreach ($branches as $b) { if ($selectedBranchId && intval($b->id) === $selectedBranchId) $branchName = $b->name; }
	$formats = array_values(array_unique(array_filter(array_map(fn($b) => $b->format ?? null, is_array($branches) ? $branches : iterator_to_array($branches)))));

	$cats = \Grocy\Services\TodayCommandService::CATEGORIES;
	$catStyle = ['alerts' => ['#EF4444', 'fa-bell'], 'waste' => ['#10B981', 'fa-trash-can'], 'counts' => ['#06B6D4', 'fa-scale-balanced'], 'quality' => ['#8B5CF6', 'fa-flask'], 'purchasing' => ['#F59E0B', 'fa-cart-shopping'], 'operations' => ['var(--daily-primary, #00C83B)', 'fa-gears'], 'other' => ['#94A3B8', 'fa-ellipsis']];
	$perPage = 7;
	$showBranchFilter = !$selectedBranchId && count($branches) > 1;
	$apDoc = fn($it) => $it['kind'] === 'waste' ? $docLink(['waste', $it['id']]) : ($it['kind'] === 'count' ? $docLink(['adjustment', $it['id']]) : null);
	$apKinds = array_count_values(array_column($ap['items'], 'kind'));
	$apTabs = array_filter(['waste' => 'Mermas', 'count' => 'Ajustes', 'alert' => 'Cierres'], fn($k) => !empty($apKinds[$k]), ARRAY_FILTER_USE_KEY);
	$nCritical = count(array_filter($d['decisions'], fn($q) => $q['score'] >= 60));
	$nOverdue = count(array_filter($d['decisions'], fn($q) => $q['state'] === 'overdue'));
	$nUnowned = count(array_filter($d['decisions'], fn($q) => !$q['owner']));
	$now = \Carbon\Carbon::now()->locale('es');
@endphp

<div class="today-exec-container" id="today-exec-container">
	<!-- 1. Encabezado Ejecutivo & Filtros -->
	<header class="today-exec-header">
		<div class="today-exec-title-area daily-head">
			<div class="dairy-page-icon is-primary"><i class="fa-solid fa-list-check"></i></div>
			<h1 class="today-exec-title">HOY</h1>
			<p class="today-exec-subtitle">
				Resumen ejecutivo · Ventas y operación comercial
				<nav class="today-level-jump" aria-label="Vistas de HOY">
					<a href="?tab=commercial" data-level="#daily-tab-commercial" aria-current="page">Comercial</a>
					<a href="?tab=processes" data-level="#daily-tab-processes">Procesos</a>
					<a href="?tab=operations" data-level="#daily-tab-operations">Decisiones</a>
				</nav>
			</p>
		</div>
		<div class="today-exec-filters">
			<!-- Date Selector -->
			<div class="today-exec-select-box today-exec-date-picker" title="Seleccionar fecha">
				<i class="fa-regular fa-calendar" style="color: #64748b; margin-right: 6px;"></i>
				<span style="color: #334155;">Hoy</span>
				<span class="today-exec-date-text" id="exec-date-display">{{ ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][intval(date('N', strtotime($selectedDate)))] }}, {{ date('d', strtotime($selectedDate)) }} {{ ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][intval(date('n', strtotime($selectedDate)))] }} {{ date('Y', strtotime($selectedDate)) }}</span>
				<i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #94a3b8; margin-left: 6px;"></i>
				<input type="date" id="exec-filter-date" value="{{ $selectedDate }}">
			</div>

			<!-- Branch Selector -->
			<div class="today-exec-select-box">
				<span style="color: #64748b; margin-right: 4px;">Sucursal</span>
				<select id="exec-filter-branch" aria-label="Sucursal">
					@if(empty($restricted))
						<option value="all" @if(!$selectedBranchId) selected @endif>Todas</option>
					@endif
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId === intval($b->id)) selected @endif>{{ $b->name }}{{ !empty($b->code) && !str_contains($b->name, $b->code) ? ' · ' . $b->code : '' }}</option>
					@endforeach
				</select>
			</div>

			<!-- Department Selector -->
			<div class="today-exec-select-box">
				<span style="color: #64748b; margin-right: 4px;">Departamento</span>
				<select id="exec-filter-dept" aria-label="Departamento">
					<option value="todos" @if($selectedDept === 'todos') selected @endif>Todos</option>
					@foreach(($departments ?? \Grocy\Services\DailyMetricsService::Departments()) as $dk => $dv)
					<option value="{{ $dk }}" @if($selectedDept === $dk) selected @endif>{{ \Grocy\Services\DailyMetricsService::DepartmentLabel($dk) }}</option>
					@endforeach
				</select>
			</div>

			<!-- Category Selector -->
			<div class="today-exec-select-box">
				<span style="color: #64748b; margin-right: 4px;">Categoría</span>
				<select id="exec-filter-category" aria-label="Categoría">
					<option value="todas" @if($selectedCategory === 'todas') selected @endif>Todas</option>
					<option value="frescos" @if($selectedCategory === 'frescos') selected @endif>Frescos</option>
					<option value="refrigerados" @if($selectedCategory === 'refrigerados') selected @endif>Refrigerados</option>
					<option value="congelados" @if($selectedCategory === 'congelados') selected @endif>Congelados</option>
					<option value="abarrotes" @if($selectedCategory === 'abarrotes') selected @endif>Abarrotes</option>
					<option value="non_food" @if($selectedCategory === 'non_food') selected @endif>Non-Food</option>
				</select>
			</div>
			<span class="today-exec-scope" id="exec-scope-note" role="status" aria-live="polite"></span>

			<!-- Status Indicator -->
			<div class="today-exec-status">
				<span class="today-exec-dot"></span>
				<span>Última sincronización <strong id="exec-last-update">{{ $exec['last_updated'] }}</strong></span>
				<span>· Última comprobación <strong id="exec-last-check">{{ $exec['checked_at'] ?? date('d/m/Y H:i:s') }}</strong></span>
				<span id="exec-refresh-note" class="today-exec-refresh-note" role="status" aria-live="polite"></span>
			</div>

			<!-- Refresh Button -->
			<button type="button" class="today-exec-refresh-btn" id="exec-refresh-btn" title="Actualizar datos">
				<i class="fa-solid fa-rotate-right"></i>
			</button>
		</div>
	</header>

	<!-- ==================== PULSO OPERATIVO DEL TURNO: qué pasó, dónde, quién responde y a qué hora vence ==================== -->
	@php
		$pulseItems = array_slice(array_values(array_filter($d['decisions'], fn($q) => $q['score'] >= 60)), 0, 3);
		if (!$pulseItems) $pulseItems = array_slice(array_values(array_filter($d['decisions'], fn($q) => $q['state'] === 'overdue')), 0, 3);
		$pulseDue = function ($due) use ($now) {
			if (!$due) return 'Sin plazo';
			$c = \Carbon\Carbon::parse($due)->locale('es');
			$h = $c->format('H:i') === '23:59' ? '' : ' ' . $c->format('H:i');
			return ($c->isToday() ? 'hoy' : ($c->isTomorrow() ? 'mañana' : ($c->isYesterday() ? 'ayer' : $c->isoFormat('D MMM')))) . $h;
		};
		$pulseHead = $s['urgent'] > 0 ? $s['urgent'] . ' ' . ($s['urgent'] === 1 ? 'decisión crítica abierta' : 'decisiones críticas abiertas')
			: ($s['overdue'] > 0 || $tasksLate > 0 ? ($s['overdue'] + $tasksLate) . ' decisión(es) o tarea(s) fuera de plazo' : 'Sin decisiones críticas ni atrasos');
	@endphp
	<section class="daily-pulse-banner today-pulse" aria-label="Pulso operativo del turno">
		<div class="daily-pulse-status">
			<div class="daily-pulse-indicator is-{{ $state[1] === 'danger' ? 'danger' : ($state[1] === 'warning' ? 'warning' : 'good') }}">
				<i class="fa-solid {{ $state[1] === 'danger' ? 'fa-triangle-exclamation' : ($state[1] === 'warning' ? 'fa-bell' : 'fa-circle-check') }}"></i>
			</div>
			<div class="daily-pulse-info">
				<div class="d-flex align-items-center gap-2 mb-1">
					<span class="daily-badge daily-badge-{{ $state[1] === 'danger' ? 'danger' : ($state[1] === 'warning' ? 'warning' : 'success') }}">{{ $state[0] }}</span>
					<span class="today-pulse-scope">Turno activo · {{ $branchName }} · toda la sucursal</span>
				</div>
				<h3 class="today-pulse-head">{{ $pulseHead }}</h3>
			</div>
		</div>
		@if($pulseItems)
		<ol class="today-pulse-list">
			@foreach($pulseItems as $q)
			@php $qHref = $docLink($q['doc']) ?: ($canGo($q['href']) ? $link($q['href']) : null); @endphp
			<li class="today-pulse-item {{ $q['state'] === 'overdue' ? 'is-overdue' : '' }}">
				<div class="today-pulse-what">
					@if($qHref)<a href="{{ $qHref }}">{{ $q['type'] }}: {{ $q['subject'] }}</a>@else<span>{{ $q['type'] }}: {{ $q['subject'] }}</span>@endif
				</div>
				<div class="today-pulse-meta">
					<span><i class="fa-solid fa-store"></i> {{ $q['branch'] ?: $branchName }}</span>
					<span><i class="fa-regular fa-user"></i> @if($q['owner']){{ $q['owner'] }}@else<span class="t-warning">Sin responsable</span>@endif</span>
					<span class="{{ $q['state'] === 'overdue' ? 't-danger' : '' }}"><i class="fa-regular fa-clock"></i> {{ $q['state'] === 'overdue' ? 'Venció ' : 'Vence ' }}{{ $pulseDue($q['due']) }}</span>
				</div>
			</li>
			@endforeach
		</ol>
		@else
		<p class="today-pulse-empty">No hay decisiones críticas ni vencidas en este alcance.</p>
		@endif
		@if(count($d['decisions']) > count($pulseItems))
		<a class="today-pulse-more" href="?tab=operations" data-level="#daily-tab-operations">Ver la agenda completa ({{ count($d['decisions']) }}) y el detalle de prioridad</a>
		@endif
	</section>

	<!-- ==================== CONTROL DE NIVELES (SEGMENTED TABS) ==================== -->
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
		<div class="daily-segmented-tabs" role="tablist">
			<button type="button" class="daily-tab-btn active" data-tab-target="#daily-tab-commercial">
				<i class="fa-solid fa-chart-line mr-1 text-success"></i> Comercial <span class="today-tab-sub">· ventas y margen</span>
			</button>
			<button type="button" class="daily-tab-btn" data-tab-target="#daily-tab-processes">
				<i class="fa-solid fa-bars-progress mr-1 text-warning"></i> Procesos <span class="today-tab-sub">· estado y desviaciones</span>
			</button>
			<button type="button" class="daily-tab-btn" data-tab-target="#daily-tab-operations">
				<i class="fa-solid fa-list-check mr-1 text-primary"></i> Decisiones <span class="today-tab-sub">· tareas y aprobaciones</span>
				@if($nCritical > 0 || count($ap['items']) > 0)
					<span class="daily-badge daily-badge-danger ml-1" style="font-size: 10px; padding: 1px 6px;" title="Avisos críticos + aprobaciones de toda la sucursal (no se filtra por Departamento ni Categoría)">{{ $nCritical + count($ap['items']) }}</span>
				@endif
			</button>
		</div>
		<div class="text-muted small">
			<i class="fa-solid fa-layer-group mr-1 text-secondary"></i> Navegación guiada en 3 niveles
		</div>
	</div>

	<!-- TAB 1: COMERCIAL -->
	<div class="daily-tab-pane active" id="daily-tab-commercial">
	@php
		$k = $exec['kpis'];
		$tone = fn($o) => ['good' => 'delta-up', 'bad' => 'delta-down'][$o['tone'] ?? ''] ?? '';
		$sTone = fn($o) => ['good' => 'delta-up', 'bad' => 'delta-up-bad'][$o['tone'] ?? ''] ?? '';
		$op = $exec['operational_kpis'];
		$pr = $exec['profitability_kpis'];
		$ri = $exec['risk_inventory'];
		$wf = $exec['workforce_kpis'];
	@endphp
	<!-- Lecturas comerciales: una por concepto, con corte y comparación -->
	<div class="today-exec-kpi-grid today-exec-kpi-grid-auto">
		<!-- Ventas netas -->
		<div class="today-exec-kpi-card" id="card-kpi-sales">
			<div class="today-exec-kpi-icon is-green"><i class="fa-solid fa-chart-simple"></i></div>
			<div class="today-exec-kpi-content">
				<span class="today-exec-kpi-label">Ventas netas <span class="today-exec-period" id="kpi-sales-cut">{{ $k['sales_net']['cutoff_text'] }}</span></span>
				<span class="today-exec-kpi-value" id="kpi-sales-val">{{ $k['sales_net']['value'] }}</span>
				<span class="today-exec-kpi-sub">
					<span class="{{ $tone($k['sales_net']) }}" id="kpi-sales-delta" data-spark="0">{{ $k['sales_net']['delta_pct'] }}</span>
					<span id="kpi-sales-comp">{{ $k['sales_net']['comparison_text'] }}</span>
				</span>
			</div>
		</div>

		<!-- Avance contra la meta acumulada al corte -->
		<div class="today-exec-kpi-card" id="card-kpi-target" title="Venta al corte ÷ meta acumulada a la misma hora (la meta del día se reparte según el perfil horario del mismo día de la semana pasada)">
			<div class="today-exec-kpi-icon is-red"><i class="fa-solid fa-bullseye"></i></div>
			<div class="today-exec-kpi-content">
				<span class="today-exec-kpi-label">Avance vs. meta al corte</span>
				<span class="today-exec-kpi-value" id="kpi-target-val">{{ $k['target_compliance']['value'] }}</span>
				<span class="today-exec-kpi-sub">
					<span class="{{ $tone($k['target_compliance']) }}" id="kpi-target-delta" data-spark="0">{{ $k['target_compliance']['gap_pp'] }}</span>
				</span>
				<span class="today-exec-kpi-fields">
					<span id="kpi-target-cut">{{ $k['target_compliance']['cutoff_target_text'] }}</span>
					<span id="kpi-target-meta">{{ $k['target_compliance']['target_text'] }}</span>
				</span>
			</div>
		</div>

		<!-- Margen bruto: % y $ con el alcance del costo -->
		<div class="today-exec-kpi-card" id="card-kpi-margin">
			<div class="today-exec-kpi-icon is-green"><i class="fa-solid fa-percent"></i></div>
			<div class="today-exec-kpi-content">
				<span class="today-exec-kpi-label">Margen y utilidad bruta</span>
				<span class="today-exec-kpi-value"><span id="kpi-margin-val">{{ $k['gross_margin']['value'] }}</span> <span class="today-exec-kpi-value-2" id="kpi-profit-val">{{ $k['gross_margin']['numeric'] !== null ? $k['gross_margin']['amount'] : '' }}</span></span>
				<span class="today-exec-kpi-sub">
					<span class="{{ $tone($k['gross_margin']) }}" id="kpi-margin-delta" data-spark="0">{{ $k['gross_margin']['delta_pp'] }}</span>
					<span id="kpi-margin-comp">{{ $k['gross_margin']['comparison_text'] }}</span>
				</span>
				<span class="today-exec-kpi-fields">
					<span id="kpi-margin-cogs">{{ $k['gross_margin']['cogs_text'] }}</span>
					<span id="kpi-margin-scope" class="{{ $k['gross_margin']['missing_cost_lines'] ? 't-warning' : '' }}">{{ $k['gross_margin']['cogs_scope'] }}</span>
				</span>
			</div>
		</div>

		<!-- Ticket promedio, transacciones y unidades por ticket -->
		<div class="today-exec-kpi-card" id="card-kpi-ticket">
			<div class="today-exec-kpi-icon is-green"><i class="fa-solid fa-cart-shopping"></i></div>
			<div class="today-exec-kpi-content">
				<span class="today-exec-kpi-label">Ticket y transacciones</span>
				<div class="today-exec-mini-stats">
					<div><span class="today-exec-mini-val" id="kpi-ticket-val">{{ $k['avg_ticket']['value'] }}</span><span class="today-exec-mini-lbl">Ticket promedio</span></div>
					<div><span class="today-exec-mini-val" id="kpi-tx-val">{{ $k['transactions']['value'] }}</span><span class="today-exec-mini-lbl">Transacciones</span></div>
					<div><span class="today-exec-mini-val" id="kpi-upt-val">{{ $k['units_per_tx']['value'] }}</span><span class="today-exec-mini-lbl">Unid. por ticket</span></div>
				</div>
				<span class="today-exec-kpi-sub">
					<span class="{{ $tone($k['avg_ticket']) }}" id="kpi-ticket-delta" data-spark="0">{{ $k['avg_ticket']['delta_pct'] ? 'Ticket ' . $k['avg_ticket']['delta_pct'] : '' }}</span>
					<span class="{{ $tone($k['transactions']) }}" id="kpi-tx-delta" data-spark="0">{{ $k['transactions']['delta_pct'] ? 'Transacciones ' . $k['transactions']['delta_pct'] : '' }}</span>
				</span>
			</div>
		</div>

		<!-- Venta por hora: fichada si hay asistencia, programada si solo hay turnos -->
		<div class="today-exec-kpi-card" id="card-kpi-labor" title="{{ $k['labor_sales_per_hour']['tooltip'] }}">
			<div class="today-exec-kpi-icon is-green"><i class="fa-regular fa-clock"></i></div>
			<div class="today-exec-kpi-content">
				<span class="today-exec-kpi-label" id="kpi-labor-title">{{ $k['labor_sales_per_hour']['title'] }}</span>
				<span class="today-exec-kpi-value" id="kpi-labor-val">{{ $k['labor_sales_per_hour']['value'] }}</span>
				<span class="today-exec-kpi-sub">
					<span class="{{ $tone($k['labor_sales_per_hour']) }}" id="kpi-labor-delta" data-spark="0">{{ $k['labor_sales_per_hour']['delta_pct'] }}</span>
					<span id="kpi-labor-sub">{{ $k['labor_sales_per_hour']['subtext'] }}</span>
				</span>
			</div>
		</div>
	</div>

	<!-- Ventas reales vs. meta y proyección · Ventas por departamento -->
	<div class="today-exec-middle-row">
		<div class="today-exec-card">
			<div class="today-exec-card-head">
				<h3 class="today-exec-card-title">Ventas del día: real, meta y proyección</h3>
				<div class="today-exec-legend">
					<div class="today-exec-legend-item"><span class="today-exec-legend-dot" style="background: #00C83B;"></span><span id="leg-real">{{ $exec['main_chart']['legend']['real'] }}</span></div>
					<div class="today-exec-legend-item"><span class="today-exec-legend-dash" style="border-color: #94A3B8;"></span><span>{{ $exec['main_chart']['legend']['target'] }}</span></div>
					<div class="today-exec-legend-item"><span class="today-exec-legend-dash" style="border-color: #0EA5E9;"></span><span>{{ $exec['main_chart']['legend']['projection'] }}</span></div>
				</div>
			</div>
			<div class="today-exec-main-chart-body">
				<div class="today-exec-chart-canvas-wrap">
					<canvas id="execMainChart"></canvas>
				</div>
				<div class="today-exec-projection-box">
					<span class="today-exec-proj-label">Proyección de cierre</span>
					<span class="today-exec-proj-val" id="exec-proj-val">{{ $exec['main_chart']['projected_closing']['amount'] }}</span>
					<span class="today-exec-proj-sub">
						<span class="{{ $tone($exec['main_chart']['projected_closing']) }}" id="exec-proj-delta" data-spark="0">{{ $exec['main_chart']['projected_closing']['variance_pct'] }}</span>
						<span id="exec-proj-text">{{ $exec['main_chart']['projected_closing']['variance_text'] }}</span>
					</span>
					<span class="today-exec-proj-basis" id="exec-proj-basis">{{ $exec['main_chart']['projected_closing']['basis_text'] }}</span>
				</div>
			</div>
		</div>

		<div class="today-exec-card">
			<div class="today-exec-card-head">
				<h3 class="today-exec-card-title">Ventas por departamento</h3>
				<select class="today-exec-select-sm" id="exec-donut-metric" aria-label="Métrica por departamento">
					<option value="sales" selected>Ventas netas</option>
					<option value="margin">Margen</option>
					<option value="profit">Utilidad bruta</option>
					<option value="units">Unidades</option>
				</select>
			</div>
			<div class="today-exec-donut-body">
				<div class="today-exec-donut-canvas-wrap">
					<canvas id="execDonutChart"></canvas>
					<div class="today-exec-donut-center">
						<div class="today-exec-donut-center-val" id="donut-center-val">{{ $exec['donut_chart']['total_sales'] }}</div>
						<div class="today-exec-donut-center-sub" id="donut-center-sub">100%</div>
					</div>
				</div>
				<div class="today-exec-dept-list" id="exec-dept-list">
					@foreach(collect($exec['donut_chart']['departments'])->sortByDesc('sales') as $dept)
					<div class="today-exec-dept-row" data-dept-id="{{ $dept['id'] }}" role="button" tabindex="0" title="Abrir {{ $dept['name'] }}">
						<div class="today-exec-dept-left"><span class="today-exec-dept-dot" style="background: {{ $dept['color'] }};"></span><span>{{ $dept['name'] }}</span></div>
						<div class="today-exec-dept-right"><span class="today-exec-dept-pct">{{ number_format($dept['share_pct'], 1) }}%</span><span class="today-exec-dept-amount">${{ number_format($dept['sales'], 0) }}</span></div>
					</div>
					@endforeach
					@if(empty($exec['donut_chart']['departments']))
					<div class="text-muted small py-2">{{ $k['sales_net']['state'] === 'no_data' ? 'Pendiente de sincronización: sin ventas del día por departamento' : 'Sin ventas por departamento en este filtro' }}</div>
					@endif
				</div>
			</div>
		</div>
	</div>

	<!-- Actividad de venta · Comparativo · Frentes de atención -->
	<div class="today-exec-bottom-row">
		<div class="today-exec-card">
			<div class="today-exec-card-head">
				<h3 class="today-exec-card-title" style="white-space: nowrap; flex-shrink: 0;">Actividad de venta</h3>
				<select class="today-exec-select-sm" id="exec-heatmap-metric" aria-label="Métrica de actividad">
					<option value="sales" selected>Por ventas</option>
					<option value="tx">Por transacciones</option>
				</select>
			</div>
			<p class="today-exec-card-period" id="exec-heat-period">{{ $exec['heatmap']['period_text'] ?? 'Histórico de 4 semanas' }} · promedio por día de la semana</p>
			<div class="today-exec-heatmap-body">
				<div class="today-exec-heatmap-table" id="exec-heatmap-table">
					@foreach($exec['heatmap']['grid'] as $row)
					<div class="today-exec-heatmap-row">
						<span class="today-exec-heatmap-day-label">{{ $row['day'] }}</span>
						@foreach($row['cells'] as $cell)
						<div class="today-exec-heatmap-cell" style="background-color: {{ $cell['color'] }};" title="{{ $cell['day'] }} {{ $cell['hour'] }}: {{ $cell['formatted_sales'] }} promedio (intensidad {{ $cell['intensity'] }}%)" data-day="{{ $cell['day'] }}" data-hour="{{ $cell['hour'] }}" data-sales="{{ $cell['formatted_sales'] }}"></div>
						@endforeach
					</div>
					@endforeach
					<div class="today-exec-heatmap-hours-row">
						<span class="today-exec-heatmap-day-label"></span>
						@foreach($exec['heatmap']['hours'] as $h)<span class="today-exec-heatmap-hour-label">{{ $h }}</span>@endforeach
					</div>
				</div>
				<div class="today-exec-heatmap-scale"><span>Menor actividad</span><div class="today-exec-scale-bar"></div><span>Mayor actividad</span></div>
				@php $hi = $exec['heatmap']['insights'] ?? null; @endphp
				<div class="today-exec-heatmap-insights border-top pt-2 mt-2">
					<div class="today-exec-heatmap-insights-grid">
						<div class="today-exec-insight-item is-peak">
							<div class="today-exec-insight-head"><span class="today-exec-insight-tag is-peak">Pico</span><strong>{{ $hi['peak'] ?? 'Sin información' }}</strong></div>
							<div class="today-exec-insight-text">@if($hi)<strong>{{ $hi['peak_sales'] }}</strong> promedio por día en el período.@else Sin ventas en el período.@endif</div>
						</div>
						<div class="today-exec-insight-item is-valley">
							<div class="today-exec-insight-head"><span class="today-exec-insight-tag is-valley">Valle</span><strong>{{ $hi['valley'] ?? 'Sin información' }}</strong></div>
							<div class="today-exec-insight-text">@if($hi)<strong>{{ $hi['valley_sales'] }}</strong> promedio: franja para recepción y conteos.@else Sin ventas en el período.@endif</div>
						</div>
					</div>
					<div class="today-exec-card-foot">
						<span>@if($hi){{ $hi['top_share'] }}% de la venta en 18 de 91 franjas @else Sin información @endif</span>
						<a href="{{ $U('/sales#section-heatmap') }}" class="daily-link-action">Ver franjas <i class="fa-solid fa-chevron-right ml-1"></i></a>
					</div>
				</div>
			</div>
		</div>

		<div class="today-exec-card">
			<div class="today-exec-card-head">
				<h3 class="today-exec-card-title">Comparativo de ventas</h3>
				<select class="today-exec-select-sm" id="exec-comparative-metric" aria-label="Métrica comparativo">
					<option value="sales" @if(($selectedCompMetric ?? 'sales') === 'sales') selected @endif>Ventas netas</option>
					<option value="margin" @if(($selectedCompMetric ?? 'sales') === 'margin') selected @endif>Margen</option>
					<option value="profit" @if(($selectedCompMetric ?? 'sales') === 'profit') selected @endif>Utilidad bruta</option>
				</select>
			</div>
			<p class="today-exec-card-period" id="exec-comp-note">{{ $exec['comparative']['note'] ?? '' }}</p>
			<div class="today-exec-comparative-body"><canvas id="execComparativeChart"></canvas></div>
			<div class="today-exec-comparative-insights border-top pt-2 mt-2" id="exec-comp-list">
				@foreach($exec['comparative']['metrics']['sales']['items'] ?? [] as $ci)
				<div class="today-exec-comp-row {{ $ci['is_current'] ? 'is-current' : '' }}">
					<span class="today-exec-comp-label">{{ $ci['label'] }} · {{ $ci['date'] }} · {{ $ci['cut'] }}</span>
					<span class="today-exec-comp-val">{{ $ci['formatted'] }}</span>
					<span class="today-exec-comp-diff">
						@if(!$ci['is_current'])
							@if($ci['diff_abs'] !== null)
								<strong class="{{ $ci['diff_abs'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $ci['diff_text'] }}@if($ci['diff_pct'] !== null) ({{ $ci['diff_pct'] >= 0 ? '+' : '' }}{{ number_format($ci['diff_pct'], 1) }}%)@endif</strong>
							@else
								<span class="text-muted">Sin base para comparar</span>
							@endif
						@endif
					</span>
				</div>
				@endforeach
				<div class="today-exec-card-foot"><span>Hoy vs. cada fecha, al mismo corte</span><a href="{{ $U('/sales') }}" class="daily-link-action">Auditar <i class="fa-solid fa-chevron-right ml-1"></i></a></div>
			</div>
		</div>

		<div class="today-exec-card">
			<div class="today-exec-card-head">
				<h3 class="today-exec-card-title">Frentes de atención</h3>
				<span class="badge badge-warning text-dark font-weight-normal" id="exec-decisions-count" style="font-size: 12px; white-space: nowrap; flex-shrink: 0;">{{ count($exec['priority_decisions'] ?? []) }} frentes de atención</span>
			</div>
			<div class="today-exec-decisions-list" id="exec-decisions-list">
				@forelse($exec['priority_decisions'] ?? [] as $dec)
				<a href="{{ $U($dec['href']) }}" class="today-exec-decision-card is-{{ $dec['severity'] }}" title="Abrir el detalle filtrado por este frente">
					<div class="today-exec-decision-left">
						<div class="today-exec-decision-icon"><i class="fa-solid {{ $dec['icon'] }}"></i></div>
						<div class="today-exec-decision-info">
							<div class="d-flex align-items-center flex-wrap mb-1" style="gap: 5px;">
								@if(!empty($dec['module_label']))<span class="today-exec-module-tag">{{ $dec['module_label'] }}</span>@endif
								<span class="today-exec-decision-title">{{ $dec['title'] }}</span>
								@if(isset($dec['count']))<span class="today-exec-count-tag">{{ number_format($dec['count']) }} registro(s)</span>@endif
							</div>
							<span class="today-exec-decision-desc">{{ $dec['description'] }}</span>
							<div class="today-exec-decision-meta">
								<span class="today-exec-decision-impact {{ $dec['impact_class'] }}">{{ $dec['impact_text'] }}</span>
								@if(!empty($dec['owner']))<span><i class="fa-regular fa-user mr-1"></i>{{ $dec['owner'] }}</span>@endif
								@if(!empty($dec['due_date']))<span><i class="fa-regular fa-clock mr-1"></i>{{ $dec['due_date'] }}</span>@endif
							</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right today-exec-decision-arrow"></i>
				</a>
				@empty
				<div class="text-center py-4 text-muted small"><i class="fa-regular fa-circle-check mr-1"></i>Sin frentes de atención abiertos</div>
				@endforelse
			</div>
		</div>
	</div>

	<!-- Indicadores operativos: cada valor con su período -->
	<div class="today-exec-section">
		<div class="today-exec-section-header">
			<h2 class="today-exec-section-title"><i class="fa-solid fa-chart-simple"></i> Indicadores operativos</h2>
			<a href="{{ $U('/kpis') }}" class="today-exec-ver-todas">Ver todas</a>
		</div>
		<div class="today-exec-cards-row-4">
			@foreach([['on_shelf_availability', 'op-disp', 'fa-warehouse', 'is-green', 'availability', 'execGreenGrad', '#10b981', '/stockoverview'], ['returns', 'op-returns', 'fa-arrow-rotate-left', 'is-red', 'returns', 'execRedGrad', '#ef4444', '/returns'], ['staff_coverage', 'op-staff', 'fa-user-group', 'is-amber', 'coverage', 'execGreenGrad', '#10b981', '/operations/scheduling'], ['on_time_delivery', 'op-otif', 'fa-truck-fast', 'is-green', 'on_time', 'execGreenGrad', '#10b981', '/dispatches']] as [$key, $idp, $icon, $cls, $spark, $grad, $col, $href])
			<div class="today-exec-spark-card">
				<div class="today-exec-spark-head">
					<div class="today-exec-spark-icon {{ $cls }}"><i class="fa-solid {{ $icon }}"></i></div>
					<span class="today-exec-spark-title">{{ $op[$key]['title'] }}</span>
				</div>
				<span class="today-exec-period" id="{{ $idp }}-period">{{ $op[$key]['period'] ?? '' }}</span>
				<div class="today-exec-spark-body">
					<div class="today-exec-spark-val-col">
						<span class="today-exec-spark-val" id="{{ $idp }}-val">{{ $op[$key]['value'] }}</span>
						@if(!empty($op[$key]['secondary']))<span class="today-exec-spark-subline" id="{{ $idp }}-sub">{{ $op[$key]['secondary'] }}</span>@endif
						<span class="today-exec-spark-delta {{ $sTone($op[$key]) }}" id="{{ $idp }}-delta" data-spark="1">{{ $op[$key]['delta'] }}</span>
					</div>
					<div class="today-exec-sparkline-box">{!! $sparkLine($exec['sparks'][$spark] ?? [], $grad, $col) !!}</div>
				</div>
				<a href="{{ $link($href) }}" class="today-exec-spark-link" title="{{ $op[$key]['tooltip'] }}">Ver detalle →</a>
			</div>
			@endforeach
		</div>
	</div>

	<!-- Rentabilidad: abre el desglose en Ventas › Rentabilidad -->
	<div class="today-exec-section">
		<div class="today-exec-section-header">
			<h2 class="today-exec-section-title"><i class="fa-solid fa-circle-dot"></i> Costo y descuentos</h2>
			<a href="{{ $U($pr['cogs']['href']) }}" class="today-exec-ver-todas">Ver rentabilidad</a>
		</div>
		<div class="today-exec-cards-row-3" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
			@foreach([['cogs', 'prof-cogs', 'fa-chart-pie', 'is-purple', 'cogs', 'execRedGrad', '#ef4444'], ['discounts', 'prof-disc', 'fa-tag', 'is-amber', 'discounts', 'execGreenGrad', '#10b981']] as [$key, $idp, $icon, $cls, $spark, $grad, $col])
			<div class="today-exec-spark-card">
				<div class="today-exec-spark-head">
					<div class="today-exec-spark-icon {{ $cls }}"><i class="fa-solid {{ $icon }}"></i></div>
					<span class="today-exec-spark-title">{{ $pr[$key]['title'] }}</span>
				</div>
				<span class="today-exec-period">{{ $pr[$key]['period'] ?? '' }}</span>
				<div class="today-exec-spark-body">
					<div class="today-exec-spark-val-col">
						<span class="today-exec-spark-val" id="{{ $idp }}-val">{{ $pr[$key]['value'] }}</span>
						<span class="today-exec-spark-subline" id="{{ $idp }}-sub">{{ $pr[$key]['subline'] }}</span>
						<span class="today-exec-spark-delta {{ $sTone($pr[$key]) }}" id="{{ $idp }}-delta" data-spark="1">{{ $pr[$key]['delta'] }}</span>
					</div>
					<div class="today-exec-sparkline-box">{!! $sparkLine($exec['sparks'][$spark] ?? [], $grad, $col) !!}</div>
				</div>
				<a href="{{ $U($pr[$key]['href']) }}" class="today-exec-spark-link" title="{{ $pr[$key]['tooltip'] }}">Ver desglose →</a>
			</div>
			@endforeach
		</div>
	</div>

	<!-- Inventario en riesgo: agotados y bajo mínimo separados, costo expuesto, acciones distintas -->
	<div class="today-exec-section">
		<div class="today-exec-section-header">
			<h2 class="today-exec-section-title"><i class="fa-solid fa-boxes-stacked"></i> Inventario en riesgo</h2>
			<a href="{{ $link('/stockoverview') }}" class="today-exec-ver-todas">Ver inventario</a>
		</div>
		<div class="today-exec-cards-row-3">
			<div class="today-exec-spark-card">
				<div class="today-exec-spark-head"><div class="today-exec-spark-icon is-red"><i class="fa-solid fa-box-open"></i></div><span class="today-exec-spark-title">{{ $ri['low_stock']['title'] }}</span></div>
				<span class="today-exec-period">{{ $ri['low_stock']['period'] }}</span>
				<div class="today-exec-split" title="{{ $ri['low_stock']['tooltip'] }}">
					<a href="{{ $U($ri['low_stock']['href_out']) }}"><span class="today-exec-split-val t-danger" id="risk-out-val">{{ number_format($ri['low_stock']['out']) }}</span><span class="today-exec-split-lbl">Agotados</span></a>
					<a href="{{ $U($ri['low_stock']['href']) }}"><span class="today-exec-split-val" id="risk-below-val">{{ number_format($ri['low_stock']['below']) }}</span><span class="today-exec-split-lbl">Bajo mínimo con stock</span></a>
				</div>
			</div>
			<div class="today-exec-spark-card">
				<div class="today-exec-spark-head"><div class="today-exec-spark-icon is-amber"><i class="fa-regular fa-calendar-xmark"></i></div><span class="today-exec-spark-title">{{ $ri['expiring']['title'] }}</span></div>
				<span class="today-exec-period">{{ $ri['expiring']['period'] }}</span>
				<div class="today-exec-spark-val-col">
					<span class="today-exec-spark-val" id="risk-expiring-val">{{ $ri['expiring']['value'] }}</span>
					<span class="today-exec-spark-delta {{ $sTone($ri['expiring']) }}" id="risk-expiring-delta" data-spark="1">{{ $ri['expiring']['delta'] }}</span>
				</div>
				<a href="{{ $U($ri['expiring']['href']) }}" class="today-exec-spark-link" title="{{ $ri['expiring']['tooltip'] }}">Rebajar o trasladar →</a>
			</div>
			<div class="today-exec-spark-card">
				<div class="today-exec-spark-head"><div class="today-exec-spark-icon is-orange"><i class="fa-solid fa-triangle-exclamation"></i></div><span class="today-exec-spark-title">{{ $ri['sensitive_diffs']['title'] }}</span></div>
				<span class="today-exec-period">{{ $ri['sensitive_diffs']['period'] }}</span>
				<div class="today-exec-split" title="{{ $ri['sensitive_diffs']['tooltip'] }}">
					<a href="{{ $U($ri['sensitive_diffs']['href_adjust']) }}"><span class="today-exec-split-val" id="risk-adjust-val">{{ $ri['sensitive_diffs']['adjust'] }}</span><span class="today-exec-split-lbl">Ajustes por aprobar →</span></a>
					<a href="{{ $U($ri['sensitive_diffs']['href_investigations']) }}"><span class="today-exec-split-val" id="risk-inv-val">{{ $ri['sensitive_diffs']['investigations'] }}</span><span class="today-exec-split-lbl">Investigaciones abiertas →</span></a>
				</div>
			</div>
		</div>
	</div>

	<!-- Prioridades por sucursal: ordenadas por impacto -->
	<div class="today-exec-section">
		<div class="today-exec-section-header">
			<h2 class="today-exec-section-title"><i class="fa-solid fa-location-dot"></i> Prioridades por sucursal</h2>
			<span class="today-exec-period">Ordenadas por impacto en dólares</span>
		</div>
		<div class="today-exec-table-card">
			<table class="today-exec-table">
				<thead>
					<tr><th style="width: 20%;">Sucursal</th><th style="width: 14%;">Excepción</th><th style="width: 18%;">Impacto</th><th>Causa principal</th><th style="width: 30px;"></th></tr>
				</thead>
				<tbody id="exec-branch-priorities-body">
					@forelse($exec['branch_priorities'] as $bp)
					<tr class="today-exec-table-row" data-href="{{ $U($bp['href']) }}" tabindex="0">
						<td class="today-exec-branch-name">{{ $bp['branch'] }}</td>
						<td><span class="today-exec-table-state"><i class="fa-solid {{ $bp['status_icon'] }}" style="color: {{ $bp['status_icon_color'] }}; font-size: 13px;"></i> {{ $bp['status_text'] }}</span></td>
						<td><span class="today-exec-prio-badge {{ $bp['priority_class'] }}">{{ $bp['impact'] }}</span></td>
						<td class="today-exec-table-detail">{{ $bp['detail'] }}</td>
						<td><a href="{{ $U($bp['href']) }}" aria-label="Abrir {{ $bp['status_text'] }} en {{ $bp['branch'] }}"><i class="fa-solid fa-chevron-right today-exec-chevron"></i></a></td>
					</tr>
					@empty
					<tr><td colspan="5" class="text-center text-muted py-3">Sin excepciones por sucursal</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<!-- Carga laboral: programadas, fichadas y déficit de cobertura -->
	<div class="today-exec-section">
		<div class="today-exec-section-header">
			<h2 class="today-exec-section-title"><i class="fa-solid fa-user-check"></i> Carga laboral del día</h2>
			<a href="{{ $U($wf['scheduled']['href']) }}" class="today-exec-ver-todas">Ver turnos</a>
		</div>
		<div class="today-exec-cards-row-3">
			@foreach(['scheduled' => ['fa-clock', 'is-purple'], 'punched' => ['fa-user-check', 'is-blue'], 'deficit' => ['fa-user-clock', 'is-amber']] as $key => [$icon, $cls])
			<a class="today-exec-spark-card today-exec-plain-card" href="{{ $U($wf[$key]['href']) }}">
				<div class="today-exec-spark-head"><div class="today-exec-spark-icon {{ $cls }}"><i class="fa-solid {{ $icon }}"></i></div><span class="today-exec-spark-title">{{ $wf[$key]['title'] }}</span></div>
				<span class="today-exec-spark-val {{ ($wf[$key]['tone'] ?? '') === 'bad' ? 't-danger' : '' }}" id="wf-{{ $key }}-val">{{ $wf[$key]['value'] }}</span>
				<span class="today-exec-spark-subline" id="wf-{{ $key }}-sub">{{ $wf[$key]['sub'] }}</span>
			</a>
			@endforeach
		</div>
	</div>
	</div><!-- /#daily-tab-commercial -->

	<!-- ==================== TAB 2: ESTADO DE PROCESOS & DESVIACIONES (NIVEL 2) ==================== -->
	<div class="daily-tab-pane" id="daily-tab-processes">
		<section class="today-process-card" id="today-process-card" aria-labelledby="today-process-title">
			<div class="today-process-header">
				<div class="today-process-title-group">
					<h2 class="today-process-title" id="today-process-title">
						<i class="fa-solid fa-bars-progress" style="color: var(--daily-green-700); font-size: 18px; margin-right: 8px;"></i>
						Estado de los procesos
					</h2>
					<span class="today-process-subtitle">Supervisión operativa transversal · Desviación, impacto económico, tendencia y responsable</span>
				</div>
				<div class="today-process-header-meta">
					<div class="today-process-legend" aria-label="Leyenda de estados">
						<span class="today-process-legend-item" title="Datos disponibles y vigentes, sin desviaciones"><i class="fa-solid fa-circle-check" style="color: var(--daily-green-700);"></i> En objetivo</span>
						<span class="today-process-legend-item" title="Desviación que requiere revisión"><i class="fa-solid fa-circle-exclamation" style="color: var(--daily-amber-600);"></i> Atención</span>
						<span class="today-process-legend-item" title="Se supera un umbral crítico definido"><i class="fa-solid fa-circle-xmark" style="color: var(--daily-red-600);"></i> Crítico</span>
						<span class="today-process-legend-item" title="Faltan datos suficientes o vigentes para evaluar"><i class="fa-solid fa-circle-question" style="color: #64748B;"></i> Sin información</span>
					</div>
					<div class="today-process-eval-stamp">
						<i class="fa-regular fa-clock" style="color: #64748b; font-size: 11px;"></i>
						<span>Último registro: <strong id="process-demo-clock">{{ $exec['last_updated'] }}</strong></span>
					</div>
				</div>
			</div>

			<!-- Tabla compacta (Desktop) / Tarjetas (Móvil) -->
			<div class="today-process-table-wrap">
				<table class="today-process-table" id="today-process-table">
					<thead>
						<tr>
							<th style="width: 14%;">Proceso</th>
							<th style="width: 12%;">Estado</th>
							<th style="width: 13%;">Desviación</th>
							<th style="width: 12%;">Impacto</th>
							<th style="width: 11%;">Tendencia</th>
							<th style="width: 25%;">Situación y responsable</th>
							<th style="width: 13%; text-align: right;">Módulo</th>
						</tr>
					</thead>
					<tbody id="today-process-tbody">
						@if(!empty($exec['process_statuses']))
							@foreach($exec['process_statuses'] as $proc)
								<tr class="today-process-row" data-key="{{ $proc['key'] }}">
									<td class="today-process-name-cell">
										<div class="today-process-name-badge">
											<span class="today-process-icon-wrap" style="color: {{ $proc['color'] }}; background: {{ $proc['color'] }}18;">
												<i class="fa-solid {{ $proc['icon'] }}"></i>
											</span>
											<strong>{{ $proc['process'] }}</strong>
										</div>
									</td>
									<td class="today-process-status-cell">
										<span class="today-proc-badge is-{{ $proc['status'] }}">
											<i class="fa-solid {{ $proc['status_icon'] }}"></i>
											<span>{{ $proc['status_label'] }}</span>
										</span>
									</td>
									<td><strong style="font-size: 12px;">{{ $proc['deviation'] ?? '—' }}</strong></td>
									<td style="font-size: 12px;" class="{{ ($proc['economic_impact'] ?? 0) < 0 ? 'text-danger font-weight-bold' : '' }}">
										{{ $proc['economic_impact_label'] ?? 'No calculable' }}
									</td>
									<td style="font-size: 12px;">
										<span class="{{ ($proc['trend'] ?? '') === 'down' ? 'text-danger' : (($proc['trend'] ?? '') === 'up' ? 'text-success' : 'text-muted') }}">
											{{ $proc['trend_label'] ?? 'Sin datos' }}
										</span>
									</td>
									<td class="today-process-situation-cell">
										<div class="today-proc-situation-content">
											<span class="today-proc-situation-text">{{ $proc['situation'] }}</span>
											<small class="text-muted d-block" style="font-size: 10.5px;">Responsable: <strong>{{ $proc['owner'] ?? 'Operaciones' }}</strong> · {{ $proc['last_updated'] ?? $exec['last_updated'] }}</small>
											@if(!empty($proc['scope_note']))
												<span class="today-proc-scope-tag" title="El filtro actual de departamento no subdivide este proceso global">{{ $proc['scope_note'] }}</span>
											@endif
										</div>
									</td>
									<td class="today-process-action-cell text-right">
										<div class="today-proc-action-group">
											<a href="{{ $link($proc['href']) }}" class="today-proc-action-link" title="{{ $proc['action_text'] }} en su módulo correspondiente">
												{{ $proc['action_text'] }}
												<i class="fa-solid fa-arrow-right"></i>
											</a>
										</div>
									</td>
								</tr>
							@endforeach
						@endif
					</tbody>
				</table>
			</div>
		</section>
	</div><!-- /#daily-tab-processes -->

	<!-- ==================== TAB 3: DECISIONES, TAREAS & APROBACIONES (NIVEL 3) ==================== -->
	<div class="daily-tab-pane" id="daily-tab-operations">
		<div class="cc-page" id="cc-page">

{{-- Encabezado --}}
<header class="cc-head">
	<div class="cc-head-main">
		<div class="cc-eyebrow">
			@if($d['shift'])<span class="cc-badge-pill is-slate" title="Turnos definidos en TODAY_SHIFTS"><i class="fa-regular fa-clock" style="color: var(--cc-purple)"></i>Turno {{ $d['shift']['name'] }} · {{ $d['shift']['from'] }}–{{ $d['shift']['to'] }}</span>@endif
			<span class="cc-badge-pill is-green"><i class="fa-solid {{ $selectedBranchId ? 'fa-store' : 'fa-network-wired' }}"></i>{{ $branchName }}</span>
			<span class="cc-state is-{{ $state[1] }}" title="{{ $state[2] }}"><span class="cc-dot"></span>{{ $state[0] }}</span>
		</div>
		<h1 class="cc-title">Hoy <span>{{ ucfirst($now->isoFormat('dddd D [de] MMMM')) }}</span></h1>
		<p class="cc-sentence">
			Resumen operativo {{ $selectedBranchId ? 'de tu tienda' : 'de la red' }}.
			{!! $pl($s['urgent'], 'decisión crítica', 'decisiones críticas') !!}, {!! $pl($al['action'], 'alerta', 'alertas') !!},
			{!! $pl($tasksOpen, 'tarea en curso', 'tareas en curso') !!} y {!! $pl($ap['total'], 'aprobación', 'aprobaciones') !!}.
		</p>
	</div>
	<div class="cc-head-side">
		<div class="cc-headtools">
			<select class="cc-select cc-scope-select" id="cc-scope-select" aria-label="Sucursal">
				@if(!$restricted)<option value="" @if(!$selectedBranchId) selected @endif>Red Consolidada</option>@endif
				@foreach($branches as $b)<option value="{{ $b->id }}" @if($selectedBranchId === intval($b->id)) selected @endif>{{ $b->name }}{{ !empty($b->code) && !str_contains($b->name, $b->code) ? ' · ' . $b->code : '' }}</option>@endforeach
			</select>
			@if($canGo('/alerts'))
			<a class="cc-icon-btn" href="{{ $link('/alerts') }}" title="{{ $al['critical'] }} alerta(s) crítica(s) abierta(s)" aria-label="Alertas críticas: {{ $al['critical'] }}"><i class="fa-solid fa-bell"></i>@if($al['critical'] > 0)<span class="cc-badge">{{ $al['critical'] > 99 ? '99+' : $al['critical'] }}</span>@endif</a>
			@endif
			<button type="button" class="cc-icon-btn" id="cc-refresh" title="Actualizar ahora" aria-label="Actualizar"><i class="fa-solid fa-rotate-right"></i></button>
		</div>
		<div class="cc-clock-box">
			<span class="cc-clock-day">{{ ucfirst($now->isoFormat('ddd D MMM YYYY')) }}</span>
			<time class="cc-clock-time" id="cc-clock" datetime="{{ $d['now'] }}">{{ substr($d['now'], 11, 5) }}</time>
			<span class="cc-sync" title="Hora de cálculo de esta pantalla; se actualiza sola cada 5 minutos">Actualizado <b id="cc-updated">{{ substr($d['now'], 11, 5) }}</b>@if($d['last_scan']) · alertas {{ $when($d['last_scan']) }}@endif</span>
		</div>
	</div>
</header>

{{-- Fila 1 · KPI --}}
<section class="cc-kpis" aria-label="Indicadores del día">
	<a class="cc-kpi k-blue" href="#cc-queue" data-st="all" title="Decisiones únicas abiertas en este alcance (mermas, ajustes, alertas, lotes, órdenes…). Crítica = puntuación de prioridad ≥ 60.">
		<span class="cc-kpi-ic"><i class="fa-solid fa-clipboard-list"></i></span>
		<span class="cc-kpi-label">Decisiones pendientes</span>
		<span class="cc-kpi-value">{{ $s['decisions'] }}</span>
		<span class="cc-kpi-sub"><b class="{{ $s['urgent'] ? 't-danger' : '' }}">{{ $s['urgent'] }}</b> críticas · <b class="{{ $s['overdue'] ? 't-warning' : '' }}">{{ $s['overdue'] }}</b> vencidas</span>
		<span class="cc-kpi-foot">{!! $deltaHtml($delta('decisions')) !!}<span class="cc-go" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
	</a>

	<div class="cc-kpi cc-has-pop k-green" title="Suma única del valor de las decisiones abiertas. Confirmado: pérdida registrada o documento con importe. Estimado: costo del stock expuesto.">
		<span class="cc-kpi-ic"><i class="fa-solid fa-dollar-sign"></i></span>
		<span class="cc-kpi-label">Valor en riesgo</span>
		<span class="cc-kpi-value" title="{{ $money($totalValue) }}">{{ $moneyShort($totalValue) }}</span>
		<span class="cc-kpi-sub"><b>{{ $money($s['value_confirmed']) }}</b> confirmado · <b>{{ $money($s['value_estimated']) }}</b> en riesgo</span>
		<span class="cc-kpi-foot">{!! $deltaHtml($delta('value_total', true, true)) !!}<button type="button" class="cc-go cc-pop-toggle" aria-expanded="false" aria-label="Desglose"><i class="fa-solid fa-chevron-down"></i></button></span>
		<div class="cc-pop" hidden>
			<div class="cc-pop-title">Por tipo de decisión</div>
			@forelse($s['value_by_type'] as $t => $v)
			<div class="cc-pop-row"><span>{{ $t }}</span><b>{{ $money($v) }}</b></div>
			@empty
			<div class="cc-muted">Ninguna decisión abierta tiene valor económico.</div>
			@endforelse
			<p class="cc-note">Cada decisión se suma una sola vez (las alertas vinculadas a su lote o retiro no duplican el valor).</p>
		</div>
	</div>

	<a class="cc-kpi k-amber" href="{{ $canGo('/alerts') ? $link('/alerts') : '#cc-queue' }}" title="Alertas abiertas críticas o con plazo vencido. Gestión completa en el Centro de Alertas.">
		<span class="cc-kpi-ic"><i class="fa-solid fa-bell"></i></span>
		<span class="cc-kpi-label">Alertas que requieren acción</span>
		<span class="cc-kpi-value">{{ $al['action'] }}</span>
		<span class="cc-kpi-sub"><b class="{{ $al['critical'] ? 't-danger' : '' }}">{{ $al['critical'] }}</b> críticas · <b>{{ $al['new'] }}</b> nuevas hoy</span>
		<span class="cc-kpi-foot"><span class="cc-delta {{ $al['overdue'] ? 'is-bad' : 'is-flat' }}">{{ $al['overdue'] }} atrasadas · {{ max(0, $al['action'] - $al['overdue']) }} en plazo</span><span class="cc-go" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
	</a>

	<a class="cc-kpi k-purple" href="#cc-approvals" title="Solicitudes que esperan a un aprobador: mermas, ajustes de inventario, cierres de alerta, órdenes en borrador y facturas con diferencias.">
		<span class="cc-kpi-ic"><i class="fa-solid fa-file-signature"></i></span>
		<span class="cc-kpi-label">Aprobaciones pendientes</span>
		<span class="cc-kpi-value">{{ $ap['total'] }}</span>
		<span class="cc-kpi-sub"><b>{{ $ap['waste'] }}</b> mermas · <b>{{ $ap['counts'] }}</b> ajustes @if($ap['alerts'] + $ap['orders'] + $ap['invoices']) · <b>{{ $ap['alerts'] + $ap['orders'] + $ap['invoices'] }}</b> otras @endif</span>
		<span class="cc-kpi-foot">@if($ap['oldest'])<span class="cc-muted">Más antigua {{ $ago($ap['oldest']) }}</span>@else{!! $deltaHtml($delta('approvals')) !!}@endif<span class="cc-go" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
	</a>

	<a class="cc-kpi k-red" href="#cc-prevent" title="Riesgos activos: ubicaciones con temperatura fuera de rango hoy, lotes que vencen en ≤ 3 días, lotes en cuarentena y conteos atrasados o con diferencia.">
		<span class="cc-kpi-ic"><i class="fa-solid fa-triangle-exclamation"></i></span>
		<span class="cc-kpi-label">Riesgos operativos</span>
		<span class="cc-kpi-value">{{ $riskActive }}<small>activos</small></span>
		<span class="cc-kpi-sub"><b class="{{ $rk['expiring']['n'] ? 't-warning' : '' }}">{{ $rk['expiring']['n'] }}</b> por vencer · <b class="{{ $rk['cold']['n'] ? 't-danger' : '' }}">{{ $rk['cold']['n'] }}</b> frío</span>
		<span class="cc-kpi-foot"><span class="cc-delta {{ $rk['quarantine']['n'] + $rk['counts']['n'] ? 'is-bad' : 'is-flat' }}">Cuarentena {{ $rk['quarantine']['n'] }} · Conteos {{ $rk['counts']['n'] + $rk['counts']['diff'] }}</span><span class="cc-go" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
	</a>
</section>

{{-- Fila 2 · Pendientes vs. resueltos + Carga operativa --}}
<div class="cc-row cc-row-2">
	<section class="cc-card" aria-labelledby="cc-trend-t">
		<div class="cc-card-head">
			<div>
				<h2 id="cc-trend-t">Pendientes vs. resueltos</h2>
				<p class="cc-card-sub" id="cc-trend-stats">&nbsp;</p>
			</div>
			<div class="cc-tabs" role="tablist" aria-label="Período">
				<button type="button" class="cc-tab" data-days="7">7d</button>
				<button type="button" class="cc-tab is-on" data-days="14">14d</button>
				<button type="button" class="cc-tab" data-days="30">30d</button>
				<button type="button" class="cc-tab" data-days="year" title="Año en curso, agrupado por semana">YoY</button>
			</div>
		</div>
		<div class="cc-legend"><span><i style="background: var(--cc-blue)"></i>Decisiones</span><span><i style="background: var(--cc-green)"></i>Resueltas</span><span><i style="background: var(--cc-red); border-radius: 50%"></i>Vencidas</span></div>
		<div class="cc-chart" style="height: 190px;"><canvas id="cc-trend" aria-label="Decisiones generadas, resueltas y vencidas por día" role="img"></canvas></div>
	</section>

	<section class="cc-card" aria-labelledby="cc-load-t">
		<div class="cc-card-head">
			<h2 id="cc-load-t">Carga operativa <span class="cc-muted cc-h-sub">({{ $s['decisions'] }} decisiones)</span></h2>
			<a class="cc-link" href="#cc-queue" data-st="all">Ver detalle</a>
		</div>
		@php $loadTotal = max(1, array_sum($d['by_category'])); @endphp
		<ul class="cc-load">
			@foreach($cats as $k => $label)
			@php $n = $d['by_category'][$k] ?? 0; $p = round(100 * $n / $loadTotal); @endphp
			<li>
				<button type="button" class="cc-load-row" data-cat-go="{{ $k }}" @if(!$n) disabled @endif title="Filtrar la agenda: {{ $label }}">
					<span class="cc-load-name">{{ $label }}</span>
					<span class="cc-load-bar"><span style="width: {{ $p }}%; background: {{ $catStyle[$k][0] }}"></span></span>
					<b>{{ $n }}</b>
					<small>{{ $p }}%</small>
				</button>
			</li>
			@endforeach
		</ul>
	</section>
</div>

{{-- Fila 3 · Seguimiento resumido de planes y acciones correctivas (analítica de mermas vive en /waste) --}}
@php $caToday = $exec['corrective_actions'] ?? []; @endphp
<section class="cc-card" aria-labelledby="cc-ca-t" style="margin-bottom: 14px;">
	<div class="cc-card-head">
		<h2 id="cc-ca-t"><i class="fa-solid fa-list-check text-success mr-2"></i>Seguimiento de acciones correctivas</h2>
		<div class="d-flex align-items-center" style="gap: 10px;">
			@if($canGo('/waste'))<a class="cc-link" href="{{ $link('/waste') }}">Abrir Centro de Mermas →</a>@endif
			@if($canGo('/alerts'))<a class="cc-link" href="{{ $link('/alerts') }}">Centro de Alertas →</a>@endif
		</div>
	</div>
	<div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px; padding: 4px 0;">
		<div class="d-flex flex-wrap" style="gap: 18px; font-size: 13px;">
			<span>Abiertas: <strong>{{ intval($caToday['open'] ?? 0) }}</strong></span>
			<span>En curso: <strong class="text-primary">{{ intval($caToday['in_progress'] ?? 0) }}</strong></span>
			<span>Vencidas: <strong class="{{ intval($caToday['overdue'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ intval($caToday['overdue'] ?? 0) }}</strong></span>
			<span>Costo evitado acumulado: <strong class="text-success">{{ $money($caToday['cost_avoided_total'] ?? 0) }}</strong></span>
		</div>
		@if(!empty($caToday['top']))
			<div class="small text-muted">
				Última activa: <strong>{{ $caToday['top'][0]['title'] ?? ($caToday['top'][0]['reason_name'] ?? 'Plan correctivo') }}</strong>
				@if(!empty($caToday['top'][0]['assigned_username'])) · Resp.: {{ $caToday['top'][0]['assigned_username'] }}@endif
			</div>
		@endif
	</div>
</section>

{{-- Fila 4 · Agenda de decisiones + Aprobaciones --}}
<div class="cc-row cc-row-4">
	<section class="cc-card cc-queue" id="cc-queue" aria-labelledby="cc-queue-t">
		<div class="cc-card-head">
			<h2 id="cc-queue-t">Agenda de decisiones</h2>
			<div class="cc-pills" role="tablist" aria-label="Estado">
				<button type="button" class="cc-pill is-on" data-st="all">Todas <b>{{ count($d['decisions']) }}</b></button>
				<button type="button" class="cc-pill" data-st="critical">Críticas <b>{{ $nCritical }}</b></button>
				<button type="button" class="cc-pill" data-st="overdue">Vencidas <b>{{ $nOverdue }}</b></button>
				<button type="button" class="cc-pill" data-st="unowned">Sin responsable <b>{{ $nUnowned }}</b></button>
			</div>
			@if(count($d['decisions']) > $perPage)<button type="button" class="cc-link" id="cc-q-all">Ver todas</button>@endif
		</div>
		@if(empty($d['decisions']))
		<div class="cc-empty"><i class="fa-solid fa-circle-check"></i><b>Sin decisiones pendientes</b><span>No hay mermas, ajustes, alertas, lotes ni órdenes esperando una decisión en este alcance.</span></div>
		@else
		<div class="cc-toolbar">
			<label class="cc-search-wrap"><span class="cc-sr">Buscar</span><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="cc-search" id="cc-q-search" placeholder="Buscar producto, documento, responsable…" autocomplete="off"></label>
			<select class="cc-select" id="cc-q-cat" aria-label="Tipo">
				<option value="all">Todos los tipos</option>
				@foreach($d['by_category'] as $k => $n)@if($n)<option value="{{ $k }}">{{ $cats[$k] }} ({{ $n }})</option>@endif @endforeach
			</select>
			@if($showBranchFilter)
			<select class="cc-select" id="cc-q-branch" aria-label="Sucursal">
				<option value="all">Todas las sucursales</option>
				@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}{{ !empty($b->code) && !str_contains($b->name, $b->code) ? ' · ' . $b->code : '' }}</option>@endforeach
			</select>
			@endif
		</div>
		@php
			// Sin responsable: primero una cola manejable, agrupada por sucursal y causa (la lista completa sigue abajo)
			$unownedGroups = [];
			foreach ($d['decisions'] as $q)
			{
				if ($q['owner']) continue;
				$gk = ($q['branch_id'] ?? '') . '|' . $q['cat'];
				$unownedGroups[$gk] = $unownedGroups[$gk] ?? ['br' => (string) ($q['branch_id'] ?? ''), 'branch' => $q['branch'] ?: $branchName, 'cat' => $q['cat'], 'n' => 0, 'crit' => 0, 'over' => 0];
				$unownedGroups[$gk]['n']++;
				if ($q['score'] >= 60) $unownedGroups[$gk]['crit']++;
				if ($q['state'] === 'overdue') $unownedGroups[$gk]['over']++;
			}
			uasort($unownedGroups, fn($x, $y) => [$y['crit'], $y['over'], $y['n']] <=> [$x['crit'], $x['over'], $x['n']]);
			$unownedTop = array_slice(array_values($unownedGroups), 0, 8);
		@endphp
		@if($unownedTop)
		<div class="cc-groups" id="cc-unowned-groups" hidden>
			<p class="cc-groups-t">Empiece por aquí: {{ count($unownedTop) }} de {{ count($unownedGroups) }} grupos sin responsable, por sucursal y causa. Asigne un responsable por grupo.@if(count($d['decisions']) > 150) La lista de abajo muestra las {{ 150 }} de mayor prioridad.@endif</p>
			<ul class="cc-groups-list">
				@foreach($unownedTop as $g)
				<li><button type="button" class="cc-group" data-gbr="{{ $g['br'] }}" data-gcat="{{ $g['cat'] }}">
					<b>{{ $g['n'] }}</b> {{ $cats[$g['cat']] ?? $g['cat'] }} · {{ $g['branch'] }}
					@if($g['crit'])<span class="t-danger">{{ $g['crit'] }} críticas</span>@endif
					@if($g['over'])<span class="t-warning">{{ $g['over'] }} vencidas</span>@endif
				</button></li>
				@endforeach
			</ul>
		</div>
		@endif
		<div class="cc-q-head" aria-hidden="true"><span>Prioridad</span><span>Tipo</span><span>Descripción</span><span style="text-align:right">Impacto</span><span>Responsable</span><span>Límite</span><span style="text-align:right">Acción</span></div>
		<ol class="cc-q" id="cc-q">
			@php $visibleDecisions = array_slice($d['decisions'], 0, 150); @endphp
			@foreach($visibleDecisions as $i => $q)
			@php $dl = $docLink($q['doc']); $cs = $catStyle[$q['cat']] ?? $catStyle['other']; @endphp
			{{-- Las filas fuera de la primera página van en un <template>: el JS las inserta al filtrar o paginar --}}
			@if($i === $perPage)
		</ol>
		<template id="cc-q-rest" data-count="{{ count($visibleDecisions) - $perPage }}">
			@endif
			<li class="cc-q-row tone-{{ $q['tone'] }}" id="dec-{{ str_replace(':', '-', $q['uid']) }}" data-uid="{{ $q['uid'] }}" data-cat="{{ $q['cat'] }}" data-critical="{{ $q['score'] >= 60 ? 1 : 0 }}" data-overdue="{{ $q['state'] === 'overdue' ? 1 : 0 }}" data-unowned="{{ $q['owner'] ? 0 : 1 }}" data-branch="{{ $q['branch_id'] }}"
				data-search="{{ mb_strtolower($q['type'] . ' ' . $q['subject'] . ' ' . $q['detail'] . ' ' . ($q['owner'] ?? '') . ' ' . ($q['branch'] ?? '') . ' ' . $q['uid']) }}" @if($i >= $perPage) hidden @endif>
				<button type="button" class="cc-score" aria-expanded="false" title="Puntuación {{ $q['score'] }} · ver factores"><span class="cc-sev"></span><span class="cc-prio">{{ $q['priority'] }}</span></button>
				<div class="cc-q-type"><span class="cc-cat-ic" style="--c: {{ $cs[0] }}"><i class="fa-solid {{ $cs[1] }}"></i></span><span>{{ $q['type'] }}</span></div>
				<div class="cc-q-main">
					<div class="cc-q-title">
						@if($dl)<a href="{{ $dl }}" title="Abrir expediente · {{ $q['subject'] }}">{{ $q['subject'] }}</a>@else<span title="{{ $q['subject'] }}">{{ $q['subject'] }}</span>@endif
					</div>
					<div class="cc-q-meta">
						@if(!$selectedBranchId && $q['branch'])<span><i class="fa-solid fa-store"></i>{{ $q['branch'] }}</span>@endif
						@if($q['detail'])<span title="{{ $q['detail'] }}">{{ \Illuminate\Support\Str::limit($q['detail'], 70) }}</span>@endif
						@if($q['food'])<span class="t-danger"><i class="fa-solid fa-utensils"></i>Inocuidad</span>@endif
						@if($q['linked'])<span title="{{ implode(', ', $q['linked']) }}"><i class="fa-solid fa-link"></i>{{ count($q['linked']) }}</span>@endif
					</div>
					<div class="cc-q-mobile">
						<span class="cc-chip">{{ $q['type'] }}</span>
						@if($q['value'] > 0)<span><b>{{ $money($q['value']) }}</b></span>@endif
						<span>@if($q['owner'])<i class="fa-regular fa-user"></i> {{ $q['owner'] }}@else<span class="t-warning">Sin responsable</span>@endif</span>
						@if($q['due'])<span class="{{ $q['state'] === 'overdue' ? 't-danger' : '' }}"><i class="fa-regular fa-clock"></i> {{ $when($q['due']) }}</span>@endif
					</div>
					<div class="cc-factors" hidden>
						<span>Puntuación <b>{{ $q['score'] }}</b></span><span>{{ $q['status'] }}</span>
						@foreach($q['factors'] as $f)<span>{{ $f[0] }} <b>+{{ $f[1] }}</b></span>@endforeach
					</div>
				</div>
				<div class="cc-q-val" title="{{ $q['value_kind'] === 'confirmed' ? 'Confirmado' : 'Estimado' }}">@if($q['value'] > 0)<b>{{ $money($q['value']) }}</b>@else<span class="cc-muted">—</span>@endif</div>
				<div class="cc-q-owner">@if($q['owner'])<span class="cc-avatar">{{ $initials($q['owner']) }}</span><span class="cc-ellipsis">{{ $q['owner'] }}</span>@else<span class="t-warning cc-ellipsis">Sin responsable</span>@endif</div>
				<div class="cc-q-due {{ $q['state'] === 'overdue' ? 't-danger' : '' }}">{{ $when($q['due']) }}</div>
				<div class="cc-q-act">
					@if($canGo($q['href']))<a class="cc-btn is-sm {{ $q['score'] >= 60 ? 'is-primary' : '' }}" href="{{ $link($q['href']) }}">{{ $q['action'] }}</a>@endif
					@if($dl)<a class="cc-chev" href="{{ $dl }}" title="Abrir detalle" aria-label="Abrir detalle"><i class="fa-solid fa-chevron-right"></i></a>@endif
				</div>
			</li>
			@endforeach
		@if(count($d['decisions']) > $perPage)
		</template>
		@else
		</ol>
		@endif
		<div class="cc-empty is-small" id="cc-q-none" hidden><i class="fa-solid fa-filter-circle-xmark" style="color: var(--cc-muted)"></i><b>Ninguna decisión coincide con los filtros</b><button type="button" class="cc-link" id="cc-q-reset">Quitar filtros</button></div>
		<div class="cc-pager" id="cc-pager">
			<span id="cc-pager-txt"></span>
			<span class="cc-pager-btns">
				<button type="button" class="cc-btn is-sm" id="cc-prev" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></button>
				<button type="button" class="cc-btn is-sm" id="cc-next" aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></button>
			</span>
		</div>
		@if($s['linked_alerts'] > 0)<p class="cc-note">{{ $s['linked_alerts'] }} alerta(s) fusionada(s) con su lote o retiro: no se cuentan dos veces.</p>@endif
		@endif
	</section>

	<section class="cc-card" id="cc-approvals" aria-labelledby="cc-appr-t">
		<div class="cc-card-head">
			<h2 id="cc-appr-t"><span class="cc-h-ic is-pink"><i class="fa-solid fa-stamp"></i></span>Necesita tu aprobación</h2>
			@if($canGo('/waste/approvals'))<a class="cc-link" href="{{ $link('/waste/approvals') }}">Ver todas</a>@endif
		</div>
		@if(empty($ap['items']))
		<div class="cc-empty is-small"><i class="fa-solid fa-circle-check"></i><b>Nada por aprobar</b>@if($ap['orders'] + $ap['invoices'] > 0)<span>{{ $ap['orders'] }} órdenes y {{ $ap['invoices'] }} facturas esperan en MRP.</span>@endif</div>
		@else
		@if(count($apTabs) > 1)
		<div class="cc-tabs cc-appr-tabs" role="tablist" aria-label="Tipo de solicitud">
			@foreach($apTabs as $k => $label)<button type="button" class="cc-tab @if($loop->first) is-on @endif" data-apk="{{ $k }}">{{ $label }} <b>{{ $apKinds[$k] }}</b></button>@endforeach
		</div>
		@endif
		<ul class="cc-appr" id="cc-appr">
			@foreach($ap['items'] as $it)
			@php $open = $apDoc($it) ?? ($canGo($it['href']) ? $link($it['href']) : null); @endphp
			<li class="cc-appr-row" id="cc-appr-{{ $it['kind'] }}-{{ $it['id'] }}" data-kind="{{ $it['kind'] }}">
				<span class="cc-thumb">
					@if(!empty($it['picture']))<img src="{{ $U('/api/files/productpictures/' . base64_encode($it['picture']) . '?force_serve_as=picture&best_fit_width=112&best_fit_height=112') }}" alt="" loading="lazy" onerror="this.remove()">
					@elseif(!empty($it['photo']))<img src="{{ $U('/api/waste/photo/' . rawurlencode($it['photo'])) }}" alt="Evidencia" loading="lazy" onerror="this.remove()">
					@endif
					<i class="fa-solid {{ $it['kind'] === 'waste' ? 'fa-box' : ($it['kind'] === 'count' ? 'fa-scale-balanced' : 'fa-bell') }}"></i>
				</span>
				<div class="cc-appr-body">
					<div class="cc-appr-top">
						<span class="cc-appr-code">{{ $it['code'] ?? $it['label'] }}</span>
						<b class="cc-money">{{ $money($it['value']) }}</b>
					</div>
					<div class="cc-appr-subject" title="{{ $it['subject'] }}">{{ $it['subject'] }}</div>
					<div class="cc-q-meta">
						@if($it['qty'] !== null)<span>{{ $qty($it['qty']) }} u.</span>@endif
						@if($it['reason'])<span title="{{ $it['reason'] }}">{{ \Illuminate\Support\Str::limit($it['reason'], 30) }}</span>@endif
						@if(!$selectedBranchId && $it['branch'])<span><i class="fa-solid fa-store"></i>{{ $it['branch'] }}</span>@endif
						<span class="{{ $it['evidence'] ? 't-success' : ($it['evidence_required'] ? 't-danger' : 'cc-muted') }}" title="{{ $it['evidence'] ? 'Con evidencia' : ($it['evidence_required'] ? 'Falta evidencia' : 'Sin evidencia') }}"><i class="fa-solid fa-paperclip"></i></span>
						<span title="{{ $it['since'] }}{{ $it['by'] ? ' · ' . $it['by'] : '' }}"><i class="fa-regular fa-clock"></i>{{ $ago($it['since']) }}</span>
					</div>
				</div>
				<div class="cc-appr-foot">
					@if($it['status'] === 'Información solicitada')<span class="cc-st st-in_progress">{{ $it['status'] }}</span>@endif
					@php $menu = []; @endphp
					@if($it['kind'] === 'waste' && $can['waste'] && !$it['self'])
						@if(empty($it['missing']))
						<button type="button" class="cc-btn is-ok cc-do" data-do="waste-approve" data-id="{{ $it['id'] }}" data-name="{{ $it['subject'] }}"><i class="fa-solid fa-check"></i>Aprobar</button>
						@else
						<span class="t-danger cc-missing" title="No se aprueba con datos obligatorios faltantes">Falta {{ implode(', ', $it['missing']) }}</span>
						@endif
						<button type="button" class="cc-btn cc-do" data-do="waste-info" data-id="{{ $it['id'] }}" data-name="{{ $it['subject'] }}">Pedir info</button>
						@php $menu[] = 'waste-reject'; @endphp
					@elseif($it['kind'] === 'count' && $can['adjust'] && !$it['self'])
						<button type="button" class="cc-btn is-ok cc-do" data-do="count-approve" data-id="{{ $it['id'] }}" data-name="{{ $it['subject'] }}"><i class="fa-solid fa-check"></i>Aprobar</button>
					@elseif($it['kind'] === 'alert' && $can['alerts'] && !$it['self'])
						<button type="button" class="cc-btn is-ok cc-do" data-do="alert-approve" data-id="{{ $it['id'] }}" data-name="{{ $it['subject'] }}"><i class="fa-solid fa-check"></i>Aprobar</button>
						@php $menu[] = 'alert-reject'; @endphp
					@elseif($it['self'])
						<span class="cc-muted cc-missing" title="Segregación de funciones: nadie aprueba su propia solicitud">Lo aprueba otra persona</span>
					@endif
					@if($menu || $open)
					<span class="dropdown cc-more">
						<button type="button" class="cc-btn is-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Más acciones"><i class="fa-solid fa-ellipsis-vertical"></i></button>
						<div class="dropdown-menu dropdown-menu-right">
							@if($open)<a class="dropdown-item" href="{{ $open }}"><i class="fa-solid fa-up-right-from-square mr-2"></i>Abrir detalle</a>@endif
							@foreach($menu as $m)<button type="button" class="dropdown-item text-danger cc-do" data-do="{{ $m }}" data-id="{{ $it['id'] }}" data-name="{{ $it['subject'] }}"><i class="fa-solid fa-xmark mr-2"></i>{{ $m === 'alert-reject' ? 'Rechazar cierre' : 'Rechazar' }}</button>@endforeach
						</div>
					</span>
					@endif
				</div>
			</li>
			@endforeach
		</ul>
		<div class="cc-foot" id="cc-appr-more" hidden></div>
		@endif
	</section>
</div>

{{-- Fila 5 · Cumplimiento, franjas, comparativo --}}
<div class="cc-row cc-row-3eq">
	<section class="cc-card" id="cc-exec" aria-labelledby="cc-exec-t">
		<div class="cc-card-head">
			<h2 id="cc-exec-t">Cumplimiento del día</h2>
			@if($canGo('/operations/scheduling'))<a class="cc-link" href="{{ $link('/operations/scheduling') }}">Ver turnos →</a>@endif
		</div>
		@php $pct = $ex['pct'] ?? 0; $circ = 2 * M_PI * 46; @endphp
		<div class="cc-ring-wrap">
			<div class="cc-ring" role="img" aria-label="{{ $ex['pct'] === null ? 'Sin tareas' : $ex['pct'] . '% completado' }}">
				<svg viewBox="0 0 112 112"><circle class="bg" cx="56" cy="56" r="46"/><circle class="fg" cx="56" cy="56" r="46" stroke-dasharray="{{ round($circ, 2) }}" stroke-dashoffset="{{ round($circ * (1 - $pct / 100), 2) }}"/></svg>
				<div class="cc-ring-num"><b>{{ $ex['pct'] === null ? '—' : $ex['pct'] . '%' }}</b></div>
			</div>
			<p class="cc-ring-txt">@if($ex['total'])<b>{{ $ex['done'] }}</b> de <b>{{ $ex['total'] }}</b> tareas con plazo hoy completadas · <b>{{ $ex['pending'] + $ex['in_progress'] }}</b> pendientes @if($ex['backlog'])· {{ $ex['backlog'] }} atrasadas de días previos @endif @else Sin tareas con plazo hoy en este alcance.@if($canGo('/workforce')) <a href="{{ $link('/workforce') }}">Abrir piso de tienda</a>@endif @endif</p>
		</div>
		<div class="cc-minis">
			<div class="is-green"><b>{{ $ex['done'] }}/{{ $ex['total'] }}</b><span>Tareas resueltas</span></div>
			<div class="is-blue"><b>{{ $ex['in_progress'] }}</b><span>En proceso</span></div>
			<div class="is-red"><b>{{ $tasksLate }}</b><span>Atrasadas</span></div>
		</div>
	</section>

	<section class="cc-card" aria-labelledby="cc-hour-t">
		<div class="cc-card-head">
			<h2 id="cc-hour-t" title="Crítico: alertas críticas y lecturas críticas de frío · Alto: alertas de advertencia, lecturas en advertencia y controles de frío vencidos · Medio: mermas registradas y tareas vencidas · Bajo: alertas informativas y mermas por aprobar.">Riesgos por franja horaria</h2>
		</div>
		<div class="cc-legend"><span><i style="background: var(--cc-red)"></i>Crítico</span><span><i style="background: var(--cc-amber)"></i>Alto</span><span><i style="background: var(--cc-yellow)"></i>Medio</span><span><i style="background: var(--cc-green)"></i>Bajo</span></div>
		<div class="cc-chart" style="height: 150px;"><canvas id="cc-hourly" aria-label="Eventos de hoy por franja horaria y severidad" role="img"></canvas></div>
		<p class="cc-card-sub" id="cc-hour-peak">Eventos registrados hoy</p>
	</section>

	<section class="cc-card" aria-labelledby="cc-cmp-t">
		@php $c0 = $cmp['today']; $c7 = $cmp['ref']; $refLabel = date('d/m', strtotime($cmp['ref_date'])); @endphp
		<div class="cc-card-head">
			<h2 id="cc-cmp-t">Desempeño vs. semana anterior</h2>
			<span class="cc-pill is-on is-static" title="Hoy vs. {{ \Carbon\Carbon::parse($cmp['ref_date'])->locale('es')->isoFormat('dddd') }} {{ $refLabel }}, ambos hasta las {{ $cmp['cutoff'] }}">Hoy</span>
		</div>
		<table class="cc-cmp">
			<tbody>
			@foreach([['waste', 'Merma total', true, '$'], ['tasks', 'Tareas resueltas', false, ''], ['mttr', 'Tiempo medio de resolución', true, ' h'], ['overdue_alerts', 'Alertas vencidas', true, ''], ['pending', 'Decisiones pendientes', true, ''], ['closed_risks', 'Riesgos cerrados', false, '']] as [$k, $label, $upBad, $unit])
			@php
				$a = $c0[$k] ?? null; $b = $c7[$k] ?? null;
				$fmt = fn($v) => $v === null ? '—' : ($unit === '$' ? $money($v) : $v . $unit);
				$cls = ($a === null || $b === null || $a == $b) ? 'is-flat' : ((($a > $b) === $upBad) ? 'is-bad' : 'is-good');
				$varTxt = ($a === null || $b === null || $a == $b) ? null : ($b != 0 ? round(100 * ($a - $b) / abs($b)) . '%' : ($unit === '$' ? $money(abs($a - $b)) : abs(round($a - $b, 1))));
			@endphp
			<tr>
				<td>{{ $label }}</td>
				<td title="{{ $refLabel }}: {{ $fmt($b) }}"><b>{{ $fmt($a) }}</b></td>
				<td>@if($varTxt !== null)<span class="cc-trend {{ $cls }}"><i class="fa-solid {{ $a > $b ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>{{ ltrim($varTxt, '-') }}</span>@else<span class="cc-muted">—</span>@endif</td>
			</tr>
			@endforeach
			</tbody>
		</table>
	</section>
</div>

{{-- Fila 6 · Equipo, actividad, riesgos evitables --}}
<div class="cc-row cc-row-3eq">
	<section class="cc-card" aria-labelledby="cc-team-t">
		@php
			$tm = $d['team'];
			$coverage = $tm['has_schedule'] && $tm['present'] !== null && $tm['scheduled'] ? round(100 * $tm['present'] / $tm['scheduled']) : null;
			$load = $tm['has_schedule'] && $tm['scheduled'] ? round($tasksOpen / $tm['scheduled'], 1) : null;
		@endphp
		<div class="cc-card-head">
			<h2 id="cc-team-t">Equipo y operación del turno</h2>
			@if($canGo('/workforce'))<a class="cc-link" href="{{ $link('/workforce') }}">Ver detalles</a>@endif
		</div>
		<div class="cc-team-stats">
			<div><b>{{ $tm['has_schedule'] ? $tm['present'] ?? $tm['scheduled'] : '—' }}</b><span>{{ $tm['has_attendance'] ? 'en turno ahora' : 'programadas hoy' }}</span></div>
			<div title="Pronóstico de personal: ventas proyectadas, productividad histórica y carga operativa del día">@if($staffing)<b class="{{ $staffing['extra'] > 0 ? 't-warning' : '' }}">{{ $staffing['recommended'] }}</b><span>necesarias @if($staffing['extra'] > 0)(faltan {{ $staffing['extra'] }})@endif</span>@else<b>{{ $load ?? '—' }}</b><span>tareas / persona</span>@endif</div>
			<div><b class="{{ $tm['uncovered'] ? 't-danger' : '' }}">{{ $tm['uncovered'] }}</b><span>riesgos sin responsable</span></div>
			<div><b>{{ $coverage === null ? '—' : $coverage . '%' }}</b><span>cobertura del turno</span></div>
		</div>
		@if(!empty($tm['uncovered_top']))
		<div class="cc-opalerts">
			<div class="cc-opalerts-t"><i class="fa-solid fa-circle-exclamation"></i>Alertas operativas</div>
			<ul>
				@foreach($tm['uncovered_top'] as $q)
				<li>
					@if($canGo($q['href']))<a href="{{ $link($q['href']) }}">@else<span>@endif
						<i class="fa-solid fa-user-slash"></i><span class="cc-ellipsis" title="{{ $q['subject'] }}">{{ $q['subject'] }}</span>@if(!$selectedBranchId && $q['branch'])<small>{{ $q['branch'] }}</small>@endif<i class="fa-solid fa-chevron-right cc-op-go"></i>
					@if($canGo($q['href']))</a>@else</span>@endif
				</li>
				@endforeach
			</ul>
		</div>
		@elseif(!empty($tm['roles']))
		<table class="cc-cmp">
			<thead><tr><th>Rol</th><th>Hechas</th><th>Abiertas</th></tr></thead>
			<tbody>
			@foreach(array_slice($tm['roles'], 0, 3) as $r)
			<tr><td>{{ $r['role'] }}</td><td>{{ $r['done'] }}/{{ $r['assigned'] }}</td><td class="{{ $r['overdue'] ? 't-danger' : '' }}">{{ $r['open'] }}</td></tr>
			@endforeach
			</tbody>
		</table>
		@endif
		@if(!$tm['has_schedule'])<p class="cc-note">Sin horarios cargados hoy: cobertura y carga no se calculan.@if($canGo('/operations/scheduling')) <a href="{{ $link('/operations/scheduling') }}">Cargar horarios</a>.@endif</p>@endif
	</section>

	<section class="cc-card" aria-labelledby="cc-act-t">
		<div class="cc-card-head">
			<h2 id="cc-act-t">Actividad reciente</h2>
			@if(count($d['activity']) > 5)<button type="button" class="cc-link" id="cc-act-all">Ver todas</button>@endif
		</div>
		@if(empty($d['activity']))
		<div class="cc-empty is-small"><i class="fa-regular fa-clock"></i><b>Sin resoluciones en los últimos 7 días</b></div>
		@else
		<ol class="cc-tl" id="cc-tl">
			@foreach($d['activity'] as $i => $e)
			<li class="tone-{{ $e['tone'] }}" @if($i >= 5) hidden @endif>
				<span class="cc-tl-time" title="{{ $e['at'] }}">{{ $when($e['at']) }}</span>
				<span class="cc-tl-dot"></span>
				<div class="cc-tl-main">
					<b>{{ $e['action'] }}@if($e['count'] > 1)<em>×{{ $e['count'] }}</em>@endif</b>
					<small title="{{ $e['subject'] }} · {{ $e['result'] }}">{{ \Illuminate\Support\Str::limit($e['subject'], 60) }}@if(!$selectedBranchId && $e['branch']) · {{ $e['branch'] }}@endif</small>
				</div>
				@if($canGo($e['href']))<a class="cc-chev" href="{{ str_starts_with($e['href'], '/document/') ? $U($e['href']) : $link($e['href']) }}" title="Abrir registro original" aria-label="Abrir registro original"><i class="fa-solid fa-chevron-right"></i></a>@endif
			</li>
			@endforeach
		</ol>
		@endif
	</section>

	<section class="cc-card" id="cc-prevent" aria-labelledby="cc-prev-t">
		<div class="cc-card-head">
			<h2 id="cc-prev-t" title="Condiciones detectadas en los datos; el impacto es costo de inventario expuesto, no pérdida confirmada">Riesgos que podemos evitar hoy</h2>
		</div>
		@if(empty($d['prevention']))
		<div class="cc-empty is-small"><i class="fa-solid fa-circle-check"></i><b>Sin riesgos evitables detectados</b></div>
		@else
		<ul class="cc-prev">
			@foreach($d['prevention'] as $p)
			<li class="sev-{{ $p['sev'] }}">
				<span class="cc-prev-ic"><i class="fa-solid {{ $p['icon'] }}"></i></span>
				<div class="cc-prev-main">
					<b>{{ $p['title'] }}</b>
					<small>{{ $p['detail'] }}@if($p['value']) · {{ $money($p['value']) }}@endif</small>
				</div>
				@if($canGo($p['href']))<a class="cc-btn is-sm" href="{{ $link($p['href']) }}">{{ $p['action'] }}</a>@endif
			</li>
			@endforeach
		</ul>
		@endif
	</section>
</div><!-- /#cc-page -->
</div><!-- /#daily-tab-operations -->
</div><!-- /#today-exec-container -->
@stop

@push('pageScripts')
<script>
(function() {
	var HISTORY = @json($d['history']);
	var HOURLY = @json($d['hourly']);
	var BRANCH = @json($selectedBranchId);
	var PER_PAGE = {{ $perPage }};
	var C = { blue: (getComputedStyle(document.documentElement).getPropertyValue('--daily-primary') || '#00C83B').trim(), green: '#10B981', red: '#EF4444', amber: '#F59E0B', yellow: '#FACC15', purple: '#8B5CF6', cyan: '#06B6D4', pink: '#EC4899', gray: '#94A3B8', grid: 'rgba(100,116,139,.12)' };
	var API = Grocy.BaseUrl.replace(/\/$/, '') + '/api';

	function api(method, path, body, retries) {
		retries = retries !== undefined ? retries : 2;
		return fetch(API + path, {
			method: method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: body ? JSON.stringify(body) : undefined
		}).then(function(res) {
			return res.json().catch(function() { return {}; }).then(function(data) {
				if (!res.ok || data.success === false) { throw new Error(data.error_message || ('Error ' + res.status)); }
				return data;
			});
		}).catch(function(err) {
			if (retries > 0) {
				return new Promise(function(resolve) { setTimeout(resolve, 800); }).then(function() {
					return api(method, path, body, retries - 1);
				});
			}
			throw err;
		});
	}
	function post(path, body) { return api('POST', path, body || {}); }
	function qs(o) { return Object.keys(o).filter(function(k) { return o[k] !== null && o[k] !== '' && o[k] !== undefined; }).map(function(k) { return k + '=' + encodeURIComponent(o[k]); }).join('&'); }
	function esc(t) { return $('<div>').text(t).html(); }
	function money(v) { return '$' + Number(v || 0).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function afterAction(msg) { toastr.success(msg); setTimeout(function() { location.reload(); }, 700); }
	function ymd(d) { var p = function(x) { return (x < 10 ? '0' : '') + x; }; return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()); }
	var TODAY = ymd(new Date());

	// ---- Encabezado: reloj, sucursal, actualización automática
	var clock = document.getElementById('cc-clock');
	function tick() { var n = new Date(); clock.textContent = n.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }); }
	tick(); setInterval(tick, 15000);
	$('#cc-scope-select').on('change', function() { location.href = Grocy.BaseUrl.replace(/\/$/, '') + '/today?branch_id=' + encodeURIComponent(this.value); });
	document.getElementById('cc-refresh').addEventListener('click', function() { this.classList.add('is-spin'); location.reload(); });
	setInterval(function() {
		// No recarga si el usuario está interactuando (modal, campo con foco, desglose o factores abiertos, filtros activos)
		var busy = document.hidden || $('.modal.show').length || $(document.activeElement).is('input, select, textarea')
			|| $('.cc-factors:not([hidden]), .cc-pop:not([hidden])').length || F.q || F.st !== 'all' || F.cat !== 'all' || F.br !== 'all' || F.page > 0 || F.all;
		if (!busy) { location.reload(); }
	}, 5 * 60 * 1000);

	// ---- Desglose del valor en riesgo
	$(document).on('click', '.cc-pop-toggle', function(e) {
		e.preventDefault(); e.stopPropagation();
		var pop = $(this).closest('.cc-has-pop').find('.cc-pop'), open = pop.prop('hidden');
		pop.prop('hidden', !open); this.setAttribute('aria-expanded', open);
	});
	$(document).on('click', function(e) { if (!$(e.target).closest('.cc-pop').length) { $('.cc-pop').prop('hidden', true); $('.cc-pop-toggle').attr('aria-expanded', 'false'); } });

	// ---- Agenda: búsqueda, filtros, paginación
	var F = { q: '', st: 'all', cat: 'all', br: 'all', page: 0, all: false };
	var rows = $('#cc-q .cc-q-row');
	// Filas diferidas (ver <template id="cc-q-rest">): en el estado inicial todas coinciden y no se muestran
	var rest = document.getElementById('cc-q-rest');
	function inflate() {
		document.getElementById('cc-q').appendChild(rest.content);
		rest.remove(); rest = null;
		rows = $('#cc-q .cc-q-row');
	}
	function render() {
		if (rest && (F.q || F.st !== 'all' || F.cat !== 'all' || F.br !== 'all' || F.page > 0 || F.all)) inflate();
		var extra = rest ? parseInt(rest.getAttribute('data-count'), 10) || 0 : 0;
		var match = rows.filter(function() {
			var r = this.dataset;
			if (F.st === 'critical' && r.critical !== '1') return false;
			if (F.st === 'overdue' && r.overdue !== '1') return false;
			if (F.st === 'unowned' && r.unowned !== '1') return false;
			if (F.cat !== 'all' && r.cat !== F.cat) return false;
			if (F.br !== 'all' && r.branch !== F.br) return false;
			return !F.q || r.search.indexOf(F.q) !== -1;
		});
		var total = match.length + extra, all = rows.length + extra;
		var per = F.all ? Math.max(1, total) : PER_PAGE;
		var pages = Math.max(1, Math.ceil(total / per));
		F.page = Math.min(F.page, pages - 1);
		var from = F.page * per;
		rows.prop('hidden', true);
		match.slice(from, from + per).prop('hidden', false);
		$('#cc-q-none').prop('hidden', total > 0);
		$('#cc-pager').prop('hidden', total === 0);
		$('.cc-pager-btns').prop('hidden', pages <= 1);
		$('#cc-pager-txt').text(total ? (from + 1) + '–' + Math.min(from + per, total) + ' de ' + total + (total !== all ? ' (filtradas de ' + all + ')' : '') : '');
		$('#cc-prev').prop('disabled', F.page === 0);
		$('#cc-next').prop('disabled', F.page >= pages - 1);
		$('#cc-q-all').text(F.all ? 'Ver menos' : 'Ver todas');
		$('.cc-pill[data-st]').removeClass('is-on').filter('[data-st="' + F.st + '"]').addClass('is-on');
		$('#cc-unowned-groups').prop('hidden', F.st !== 'unowned');
		$('.cc-group').removeClass('is-on').filter(function() { return this.getAttribute('data-gbr') === F.br && this.getAttribute('data-gcat') === F.cat; }).addClass('is-on');
	}
	function toQueue() { var el = document.getElementById('cc-queue'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	var searchT;
	$('#cc-q-search').on('input', function() { var v = this.value; clearTimeout(searchT); searchT = setTimeout(function() { F.q = v.trim().toLowerCase(); F.page = 0; render(); }, 120); });
	$('#cc-q-cat').on('change', function() { F.cat = this.value; F.page = 0; render(); });
	$('#cc-q-branch').on('change', function() { F.br = this.value; F.page = 0; render(); });
	$(document).on('click', '[data-st]', function(e) {
		F.st = this.getAttribute('data-st'); F.page = 0; render();
		if (this.tagName === 'A') { e.preventDefault(); toQueue(); }
	});
	$(document).on('click', '[data-cat-go]', function() {
		F.cat = this.getAttribute('data-cat-go'); F.st = 'all'; F.page = 0;
		$('#cc-q-cat').val(F.cat); render(); toQueue();
	});
	$(document).on('click', '.cc-group', function() {
		var on = $(this).hasClass('is-on');
		F.br = on ? 'all' : this.getAttribute('data-gbr'); F.cat = on ? 'all' : this.getAttribute('data-gcat'); F.page = 0;
		$('#cc-q-branch').val(F.br); $('#cc-q-cat').val(F.cat); render();
	});
	$('#cc-q-all').on('click', function() { F.all = !F.all; F.page = 0; render(); });
	$('#cc-prev').on('click', function() { F.page--; render(); });
	$('#cc-next').on('click', function() { F.page++; render(); toQueue(); });
	$('#cc-q-reset').on('click', function() { F = { q: '', st: 'all', cat: 'all', br: 'all', page: 0, all: false }; $('#cc-q-search').val(''); $('#cc-q-cat, #cc-q-branch').val('all'); render(); });
	$(document).on('click', '.cc-score', function() {
		var f = $(this).closest('.cc-q-row').find('.cc-factors'), open = f.prop('hidden');
		f.prop('hidden', !open); this.setAttribute('aria-expanded', open);
	});
	// ?qcat=<categoría> (desde «Frentes de atención»): abre la agenda filtrada por ese frente
	var qcat = new URLSearchParams(location.search).get('qcat');
	if (qcat && $('#cc-q-cat option[value="' + qcat + '"]').length) { F.cat = qcat; $('#cc-q-cat').val(qcat); }
	if (rows.length) { render(); }

	// ---- Aprobaciones: pestañas por tipo, 3 visibles
	var APK = $('[data-apk]').first().attr('data-apk') || null;
	function renderAppr() {
		var items = $('#cc-appr .cc-appr-row'), match = APK ? items.filter('[data-kind="' + APK + '"]') : items;
		items.prop('hidden', true); match.slice(0, 3).prop('hidden', false);
		var rest = match.length - 3;
		$('#cc-appr-more').prop('hidden', rest <= 0).text(rest > 0 ? 'Y ' + rest + ' más · use «Ver todas».' : '');
	}
	$(document).on('click', '[data-apk]', function() { $(this).addClass('is-on').siblings().removeClass('is-on'); APK = this.getAttribute('data-apk'); renderAppr(); });
	renderAppr();

	// ---- Actividad: ver todas
	$('#cc-act-all').on('click', function() {
		var hid = $('#cc-tl li[hidden]').length;
		$('#cc-tl li').each(function(i) { this.hidden = hid ? false : i >= 5; });
		$(this).text(hid ? 'Ver menos' : 'Ver todas');
	});

	// ---- Segmentación del cumplimiento
	$('#cc-seg-toggle').on('click', function() { var b = document.getElementById('cc-seg-box'), open = b.hidden; b.hidden = !open; this.setAttribute('aria-expanded', open); });
	$(document).on('click', '[data-seg]', function() {
		var g = this.getAttribute('data-seg');
		$(this).addClass('is-on').siblings().removeClass('is-on');
		$('[data-seg-group]').each(function() { this.hidden = this.getAttribute('data-seg-group') !== g; });
	});

	// ---- Aprobaciones: flujos y permisos existentes (el servidor vuelve a validar segregación, nivel y sucursal, y registra auditoría)
	$(document).on('click', '.cc-do', function() {
		var btn = this, act = btn.getAttribute('data-do'), id = btn.getAttribute('data-id'), name = btn.getAttribute('data-name');
		var busy = function(on) { $(btn).closest('.cc-appr-row').find('button').prop('disabled', on); };
		var run = function(p, msg) { busy(true); p.then(function(d) { afterAction(d.message || msg); }).catch(function(e) { toastr.error(e.message); busy(false); }); };
		var ask = function(title, help, label, cls, min, cb) {
			bootbox.prompt({ title: title, message: '<p class="text-muted small mb-2">' + help + '</p>', inputType: 'textarea',
				buttons: { confirm: { label: label, className: cls }, cancel: { label: 'Cancelar', className: 'btn-light' } },
				callback: function(v) { if (v === null) return; if (v.trim().length < min) { toastr.warning('Mínimo ' + min + ' caracteres'); return false; } cb(v.trim()); } });
		};
		var confirm = function(title, msg, label, cb) {
			bootbox.confirm({ title: title, message: msg, buttons: { confirm: { label: label, className: 'btn-success' }, cancel: { label: 'Cancelar', className: 'btn-light' } }, callback: function(ok) { if (ok) cb(); } });
		};
		if (act === 'waste-approve') confirm('Aprobar merma', '¿Aprobar <strong>' + esc(name) + '</strong>?<br><span class="text-muted small">Se descuenta el inventario y la pérdida se asienta en los indicadores.</span>', 'Aprobar', function() { run(post('/waste/' + id + '/approve'), 'Merma aprobada'); });
		if (act === 'waste-reject') ask('Rechazar ' + esc(name), 'Motivo del rechazo. Lo verá quien reportó la merma.', 'Rechazar', 'btn-danger', 5, function(v) { run(post('/waste/' + id + '/reject', { rejection_reason: v }), 'Merma rechazada'); });
		if (act === 'waste-info') ask('Pedir información · ' + esc(name), 'Qué falta para decidir (evidencia, lote, causa…). Queda en la bitácora del caso; sigue pendiente.', 'Solicitar', 'btn-primary', 5, function(v) { run(post('/waste/' + id + '/request-info', { note: v }), 'Información solicitada'); });
		if (act === 'count-approve') confirm('Aprobar ajuste', '¿Ajustar el inventario de <strong>' + esc(name) + '</strong> al conteo físico?', 'Aprobar ajuste', function() { run(post('/stock/cycle-counts/' + id + '/approve-adjustment'), 'Ajuste aprobado'); });
		if (act === 'alert-approve') confirm('Aprobar cierre', '¿Aprobar el cierre de <strong>' + esc(name) + '</strong>?', 'Aprobar', function() { run(post('/alerts/' + id + '/decide', { approve: true }), 'Cierre aprobado'); });
		if (act === 'alert-reject') ask('Rechazar cierre', 'Por qué no se aprueba: la alerta vuelve a En curso.', 'Rechazar', 'btn-danger', 5, function(v) { run(post('/alerts/' + id + '/decide', { approve: false, note: v }), 'Cierre rechazado'); });
	});

	if (typeof Chart === 'undefined') { return; }
	var night = document.body.classList.contains('night-mode');
	Chart.defaults.global.defaultFontFamily = "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
	Chart.defaults.global.defaultFontColor = night ? '#94A3B8' : '#64748B';
	Chart.defaults.global.defaultFontSize = 11;
	var NOPLUG = { colorschemes: false, outlabels: false, doughnutlabel: false };
	var TIP = { backgroundColor: 'rgba(15, 23, 42, 0.94)', titleFontColor: '#FFFFFF', bodyFontColor: '#FFFFFF', footerFontColor: '#E2E8F0', borderColor: 'rgba(255, 255, 255, 0.15)', borderWidth: 1, titleFontStyle: 'bold', xPadding: 12, yPadding: 10, cornerRadius: 8, caretSize: 5, displayColors: true };
	function axisY(extra) { return $.extend(true, { ticks: { beginAtZero: true, precision: 0, maxTicksLimit: 5 }, gridLines: { color: C.grid, zeroLineColor: C.grid, drawBorder: false } }, extra || {}); }
	function axisX(extra) { return $.extend(true, { gridLines: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 8 } }, extra || {}); }

	// ---- A · Pendientes vs. resueltos
	var trend = null;
	function weekly(h) {
		var out = [];
		for (var i = 0; i < h.length; i += 7) {
			var w = h.slice(i, i + 7), last = w[w.length - 1];
			out.push({ d: w[0].d, d2: last.d, 'new': w.reduce(function(a, r) { return a + r['new']; }, 0), resolved: w.reduce(function(a, r) { return a + r.resolved; }, 0), overdue: last.overdue, open: last.open });
		}
		return out;
	}
	function dm(s) { var p = s.split('-'); return p[2] + '/' + p[1]; }
	function drawTrend(days) {
		var isYear = days === 'year';
		var jan1 = HISTORY[HISTORY.length - 1].d.slice(0, 4) + '-01-01';
		var h = isYear ? weekly(HISTORY.filter(function(r) { return r.d >= jan1; })) : HISTORY.slice(-days);
		var labels = h.map(function(r) { return dm(r.d); });
		if (!isYear) { labels[labels.length - 1] = 'Hoy'; }
		var sumNew = h.reduce(function(a, r) { return a + r['new']; }, 0), sumRes = h.reduce(function(a, r) { return a + r.resolved; }, 0);
		var lastOver = h[h.length - 1].overdue;
		document.getElementById('cc-trend-stats').innerHTML = '<b>' + sumNew + '</b> generadas · <b>' + sumRes + '</b> resueltas · ' +
			(sumNew ? Math.round(100 * sumRes / sumNew) + '% resueltas' : 'sin decisiones nuevas') + ' · <span class="' + (lastOver ? 't-danger' : 't-success') + '">' + lastOver + ' vencidas hoy</span>' + (isYear ? ' · por semana' : '');
		var cfg = {
			type: 'bar',
			data: { labels: labels, datasets: [
				{ type: 'line', label: 'Vencidas', data: h.map(function(r) { return r.overdue; }), borderColor: C.red, backgroundColor: '#fff', pointBorderColor: C.red, pointBorderWidth: 2, borderWidth: 2, pointRadius: h.length > 20 ? 0 : 3, pointHoverRadius: 5, lineTension: .3, fill: false },
				{ label: 'Decisiones', data: h.map(function(r) { return r['new']; }), backgroundColor: C.blue, barPercentage: .85, categoryPercentage: .6 },
				{ label: 'Resueltas', data: h.map(function(r) { return r.resolved; }), backgroundColor: C.green, barPercentage: .85, categoryPercentage: .6 }
			] },
			options: { maintainAspectRatio: false, animation: { duration: 200 }, legend: { display: false },
				tooltips: $.extend({ mode: 'index', intersect: false, callbacks: {
					title: function(t) { var r = h[t[0].index]; return isYear ? 'Semana ' + dm(r.d) + ' – ' + dm(r.d2) : (t[0].index === h.length - 1 ? 'Hoy ' : '') + dm(r.d); },
					footer: function(t) {
						var i = t[0].index, r = h[i], p = h[i - 1];
						var lines = ['Abiertas al cierre: ' + r.open];
						if (p) { var dv = r.open - p.open; lines.push('Variación de abiertas: ' + (dv > 0 ? '+' : dv < 0 ? '−' : '±') + Math.abs(dv)); }
						return lines;
					} } }, TIP),
				plugins: NOPLUG,
				scales: { xAxes: [axisX({ ticks: { maxTicksLimit: h.length > 14 ? 10 : 14 } })], yAxes: [axisY()] } }
		};
		if (trend) { trend.destroy(); }
		trend = new Chart(document.getElementById('cc-trend'), cfg);
	}
	$(document).on('click', '[data-days]', function() {
		$(this).addClass('is-on').siblings().removeClass('is-on');
		var v = this.getAttribute('data-days');
		drawTrend(v === 'year' ? 'year' : parseInt(v, 10));
	});
	drawTrend(14);

	// ---- D · Riesgos por franja horaria (severidad, franjas de 4 h)
	var BANDS = [[6, 10], [10, 14], [14, 18], [18, 22]];
	var sev = [['critical', 'Crítico', C.red], ['high', 'Alto', C.amber], ['medium', 'Medio', C.yellow], ['low', 'Bajo', C.green]];
	var outside = 0, sum = 0;
	var bandData = sev.map(function(s) {
		return BANDS.map(function(b) { var t = 0; for (var h = b[0]; h < b[1]; h++) t += HOURLY[s[0]][h] || 0; return t; });
	});
	sev.forEach(function(s) { HOURLY[s[0]].forEach(function(v, h) { sum += v; if (h < 6 || h >= 22) outside += v; }); });
	var bandTot = BANDS.map(function(b, i) { return bandData.reduce(function(a, r) { return a + r[i]; }, 0); });
	var pk = bandTot.indexOf(Math.max.apply(null, bandTot));
	document.getElementById('cc-hour-peak').innerHTML = sum ? '<b>' + sum + '</b> eventos hoy · franja más cargada <b>' + BANDS[pk][0] + '–' + BANDS[pk][1] + ' h</b>' + (outside ? ' · ' + outside + ' fuera de 6–22 h' : '') : 'Sin eventos registrados hoy';
	var hourlyChart = new Chart(document.getElementById('cc-hourly'), {
		type: 'bar',
		data: { labels: BANDS.map(function(b) { return b[0] + '–' + b[1]; }), datasets: sev.map(function(s, i) { return { label: s[1], data: bandData[i], backgroundColor: s[2], barPercentage: .9, categoryPercentage: .7 }; }) },
		options: { maintainAspectRatio: false, animation: { duration: 200 }, legend: { display: false },
			tooltips: $.extend({ mode: 'index', intersect: false, callbacks: { title: function(t) { return t[0].label + ' h'; } } }, TIP),
			plugins: NOPLUG,
			scales: { xAxes: [axisX()], yAxes: [axisY({ ticks: { maxTicksLimit: 4 } })] } }
	});
	window.__dailyTodayResizeOps = function() {
		if (trend && typeof trend.resize === 'function') trend.resize();
		if (hourlyChart && typeof hourlyChart.resize === 'function') hourlyChart.resize();
	};
})();

// ==========================================
// DAILY — HOY: Centro Ejecutivo de Ventas JS
// ==========================================
(function() {
	var execData = {!! json_encode($exec) !!};
	var execMainChart = null;
	var execDonutChart = null;
	var execCompChart = null;
	function esc(t) { return t == null ? '' : $('<div>').text(String(t)).html(); }

	function initExecCharts(data) {
		if (!data) return;
		initMainChart(data.main_chart);
		initDonutChart(data.donut_chart, $('#exec-donut-metric').val() || 'sales');
		initCompChart(data.comparative, $('#exec-comparative-metric').val() || 'sales');
	}

	function initMainChart(main) {
		var el = document.getElementById('execMainChart');
		if (!el) return;
		var ctx = el.getContext('2d');
		if (execMainChart) { execMainChart.destroy(); }

		var grad = ctx.createLinearGradient(0, 0, 0, 240);
		grad.addColorStop(0, 'rgba(0, 200, 59, 0.22)');
		grad.addColorStop(1, 'rgba(0, 200, 59, 0.01)');

		execMainChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: main.labels,
				datasets: [
					{
						label: (main.legend && main.legend.real) || 'Real al corte',
						data: main.real_sales,
						borderColor: '#00C83B',
						borderWidth: 2.5,
						backgroundColor: grad,
						fill: true,
						pointBackgroundColor: '#00C83B',
						pointBorderColor: '#ffffff',
						pointBorderWidth: 2,
						pointRadius: 4.5,
						pointHoverRadius: 6.5,
						lineTension: 0.35
					},
					{
						label: (main.legend && main.legend.target) || 'Meta al corte',
						data: main.target_sales,
						borderColor: '#94A3B8',
						borderWidth: 2,
						borderDash: [5, 5],
						backgroundColor: 'transparent',
						fill: false,
						pointRadius: 0,
						pointHoverRadius: 0,
						lineTension: 0.35
					},
					{
						label: (main.legend && main.legend.projection) || 'Proyección de cierre',
						data: main.projection_sales || [],
						borderColor: '#0EA5E9',
						borderWidth: 2,
						borderDash: [2, 4],
						backgroundColor: 'transparent',
						fill: false,
						spanGaps: true,
						pointRadius: 3,
						pointBackgroundColor: '#0EA5E9',
						lineTension: 0
					}
				]
			},
			options: {
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontColor: '#FFFFFF',
					titleFontStyle: 'bold',
					titleFontSize: 12,
					bodyFontColor: '#FFFFFF',
					bodyFontSize: 11,
					footerFontColor: '#E2E8F0',
					borderColor: 'rgba(255, 255, 255, 0.15)',
					borderWidth: 1,
					cornerRadius: 8,
					xPadding: 12,
					yPadding: 10,
					displayColors: true,
					callbacks: {
						label: function(t, d) {
							if (t.yLabel === null || isNaN(t.yLabel)) return null;
							return ' ' + d.datasets[t.datasetIndex].label + ': $' + Number(t.yLabel).toLocaleString();
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontColor: '#64748b', fontSize: 11 }
					}],
					yAxes: [{
						gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0' },
						ticks: {
							fontColor: '#64748b',
							fontSize: 11,
							callback: function(v) { return '$' + Number(v).toLocaleString(); },
							beginAtZero: true,
							maxTicksLimit: 5,
							suggestedMax: (function() {
								var vals = (main.real_sales || []).concat(main.target_sales || [], main.projection_sales || []).filter(function(v) { return v !== null && !isNaN(v); });
								var mx = vals.length ? Math.max.apply(null, vals) : 0;
								return mx > 0 ? mx * 1.1 : 100;
							})()
						}
					}]
				}
			}
		});
	}

	function initDonutChart(donut, metricKey) {
		var el = document.getElementById('execDonutChart');
		if (!el || !donut) return;
		var ctx = el.getContext('2d');
		if (execDonutChart) { execDonutChart.destroy(); }

		metricKey = metricKey || $('#exec-donut-metric').val() || 'sales';
		var sortVal = function(d) { return metricKey === 'margin' ? Number(d.margin_pct || 0) : (metricKey === 'profit' ? Number(d.gross_profit || 0) : (metricKey === 'units' ? Number(d.units || 0) : Number(d.sales || 0))); };
		var depts = (donut.departments || []).slice().sort(function(a, b) { return sortVal(b) - sortVal(a); });

		var values = [];
		var formatFn;
		var centerVal = '';
		var centerSub = '';

		if (metricKey === 'margin') {
			values = depts.map(function(d){ return d.margin_pct; });
			formatFn = function(v){ return Number(v).toFixed(1) + '%'; };
			centerVal = donut.avg_margin || 'Sin información';
			centerSub = 'Margen prom.';
		} else if (metricKey === 'profit') {
			values = depts.map(function(d){ return d.scaled_profit || d.gross_profit || 0; });
			formatFn = function(v){ return '$' + Number(v).toLocaleString(); };
			centerVal = donut.total_profit || 'Sin información';
			centerSub = 'Utilidad bruta';
		} else if (metricKey === 'units') {
			values = depts.map(function(d){ return d.scaled_units || d.units || 0; });
			formatFn = function(v){ return Number(v).toLocaleString() + ' un.'; };
			centerVal = donut.total_units || 'Sin información';
			centerSub = 'Unidades';
		} else {
			values = depts.map(function(d){ return d.scaled_sales || d.sales || 0; });
			formatFn = function(v){ return '$' + Number(v).toLocaleString(); };
			centerVal = donut.total_sales || 'Sin información';
			centerSub = '100%';
		}

		$('#donut-center-val').text(centerVal);
		$('#donut-center-sub').text(centerSub);

		updateDonutDeptList(depts, metricKey);

		execDonutChart = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: depts.map(function(d){ return d.name; }),
				datasets: [{
					data: values,
					backgroundColor: depts.map(function(d){ return d.color; }),
					borderWidth: 2,
					borderColor: '#ffffff',
					hoverBorderColor: '#ffffff',
					hoverBorderWidth: 2
				}]
			},
			options: {
				maintainAspectRatio: false,
				cutoutPercentage: 72,
				legend: { display: false },
				animation: { duration: 400 },
				tooltips: {
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontColor: '#FFFFFF',
					titleFontStyle: 'bold',
					titleFontSize: 12,
					bodyFontColor: '#FFFFFF',
					bodyFontSize: 11,
					footerFontColor: '#E2E8F0',
					borderColor: 'rgba(255, 255, 255, 0.15)',
					borderWidth: 1,
					cornerRadius: 8,
					xPadding: 12,
					yPadding: 10,
					displayColors: true,
					callbacks: {
						label: function(t, d) {
							var label = d.labels[t.index] || '';
							var val = d.datasets[0].data[t.index];
							return ' ' + label + ': ' + formatFn(val);
						}
					}
				}
			}
		});
	}

	function updateDonutDeptList(depts, metricKey) {
		var $list = $('#exec-dept-list');
		if (!$list.length || !depts) return;

		var html = '';
		depts.forEach(function(dept) {
			var pctStr = '';
			var amountStr = '';

			if (metricKey === 'margin') {
				pctStr = Number(dept.margin_pct).toFixed(1) + '%';
				amountStr = 'Margen';
			} else if (metricKey === 'profit') {
				var share = dept.profit_share_pct || dept.share_pct;
				pctStr = Number(share).toFixed(1) + '%';
				amountStr = '$' + Number(dept.scaled_profit || dept.gross_profit || 0).toLocaleString();
			} else if (metricKey === 'units') {
				var share = dept.units_share_pct || dept.share_pct;
				pctStr = Number(share).toFixed(1) + '%';
				amountStr = Number(dept.scaled_units || dept.units || 0).toLocaleString() + ' un.';
			} else {
				pctStr = Number(dept.share_pct).toFixed(1) + '%';
				amountStr = '$' + Number(dept.scaled_sales || dept.sales || 0).toLocaleString();
			}

			html += '<div class="today-exec-dept-row" data-dept-id="' + esc(dept.id) + '" role="button" tabindex="0" title="Abrir ' + esc(dept.name) + '">' +
				'<div class="today-exec-dept-left">' +
					'<span class="today-exec-dept-dot" style="background: ' + dept.color + ';"></span>' +
					'<span>' + esc(dept.name) + '</span>' +
				'</div>' +
				'<div class="today-exec-dept-right">' +
					'<span class="today-exec-dept-pct">' + pctStr + '</span>' +
					'<span class="today-exec-dept-amount">' + amountStr + '</span>' +
				'</div>' +
			'</div>';
		});
		$list.html(html);
	}

	function renderCompList(comp) {
		var key = $('#exec-comparative-metric').val() || 'sales';
		var m = comp.metrics && comp.metrics[key] ? comp.metrics[key] : null;
		if (!m) return;
		var html = '';
		m.items.forEach(function(ci) {
			var diff = '';
			if (!ci.is_current) {
				diff = ci.diff_abs !== null && ci.diff_abs !== undefined
					? '<strong class="' + (ci.diff_abs >= 0 ? 'text-success' : 'text-danger') + '">' + esc(ci.diff_text) + (ci.diff_pct !== null ? ' (' + (ci.diff_pct >= 0 ? '+' : '') + Number(ci.diff_pct).toFixed(1) + '%)' : '') + '</strong>'
					: '<span class="text-muted">Sin base para comparar</span>';
			}
			html += '<div class="today-exec-comp-row' + (ci.is_current ? ' is-current' : '') + '"><span class="today-exec-comp-label">' + esc(ci.label + ' · ' + ci.date + ' · ' + ci.cut) + '</span>' +
				'<span class="today-exec-comp-val">' + esc(ci.formatted) + '</span><span class="today-exec-comp-diff">' + diff + '</span></div>';
		});
		var $foot = $('#exec-comp-list .today-exec-card-foot').detach();
		$('#exec-comp-list').html(html).append($foot);
	}

	function initCompChart(comp, metricKey) {
		var el = document.getElementById('execComparativeChart');
		if (!el || !comp) return;
		var ctx = el.getContext('2d');
		if (execCompChart) { execCompChart.destroy(); }

		metricKey = metricKey || $('#exec-comparative-metric').val() || 'sales';

		var metricData = (comp.metrics && comp.metrics[metricKey]) ? comp.metrics[metricKey] : null;
		var items = metricData ? metricData.items : comp.items;
		if (!items || !items.length) return;

		var metricTitle = metricData ? metricData.title : (metricKey === 'margin' ? 'Margen' : (metricKey === 'profit' ? 'Utilidad bruta' : 'Ventas netas'));
		var isPercentage = (metricData && metricData.unit === '%') || metricKey === 'margin';
		var suggestedMax = metricData && metricData.suggested_max ? metricData.suggested_max : (isPercentage ? 35 : (metricKey === 'profit' ? 16000 : 50000));

		var labels = items.map(function(it) {
			return [it.label, it.date];
		});

		var values = items.map(function(it) {
			if (it.no_data) return null;
			if (typeof it.value !== 'undefined') return it.value;
			if (metricKey === 'margin' && typeof it.margin !== 'undefined') return it.margin;
			if (metricKey === 'profit' && typeof it.profit !== 'undefined') return it.profit;
			return it.sales || 0;
		});

		var formattedValues = items.map(function(it) {
			if (it.formatted) return it.formatted;
			if (isPercentage) return Number(it.value || it.margin || 0).toFixed(1) + '%';
			return '$' + Number(it.value || it.sales || it.profit || 0).toLocaleString();
		});

		var colors = items.map(function(it) {
			return it.color || '#00C83B';
		});

		execCompChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: colors,
					maxBarThickness: 46
				}]
			},
			options: {
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontColor: '#FFFFFF',
					titleFontStyle: 'bold',
					titleFontSize: 12,
					bodyFontColor: '#FFFFFF',
					bodyFontSize: 11,
					footerFontColor: '#E2E8F0',
					borderColor: 'rgba(255, 255, 255, 0.15)',
					borderWidth: 1,
					cornerRadius: 8,
					xPadding: 12,
					yPadding: 10,
					displayColors: true,
					callbacks: {
						label: function(t) {
							var formatted = formattedValues[t.index] || (isPercentage ? (Number(t.yLabel).toFixed(1) + '%') : ('$' + Number(t.yLabel).toLocaleString()));
							return ' ' + metricTitle + ': ' + formatted;
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontColor: '#334155', fontSize: 11, fontStyle: 'bold' }
					}],
					yAxes: [{
						display: false,
						gridLines: { display: false },
						ticks: { min: 0, suggestedMax: suggestedMax }
					}]
				},
				plugins: [{
					afterDatasetsDraw: function(chart) {
						var chartCtx = chart.ctx;
						chartCtx.save();
						chartCtx.font = 'bold 12px sans-serif';
						chartCtx.textAlign = 'center';
						chartCtx.textBaseline = 'bottom';
						chartCtx.fillStyle = '#0f172a';

						chart.data.datasets.forEach(function(dataset, i) {
							var meta = chart.getDatasetMeta(i);
							meta.data.forEach(function(bar, index) {
								var text = formattedValues[index];
								chartCtx.fillText(text, bar._model.x, bar._model.y - 6);
							});
						});
						chartCtx.restore();
					}
				}],
				animation: {
					onComplete: function() {
						var chartInstance = this.chart;
						var chartCtx = chartInstance.ctx;
						chartCtx.save();
						chartCtx.font = 'bold 12px sans-serif';
						chartCtx.textAlign = 'center';
						chartCtx.textBaseline = 'bottom';
						chartCtx.fillStyle = '#0f172a';

						this.data.datasets.forEach(function(dataset, i) {
							var meta = chartInstance.controller.getDatasetMeta(i);
							meta.data.forEach(function(bar, index) {
								var text = formattedValues[index];
								chartCtx.fillText(text, bar._model.x, bar._model.y - 6);
							});
						});
						chartCtx.restore();
					}
				}
			}
		});
	}

	function fetchExecSales(manual) {
		manual = manual === true;
		$('#exec-refresh-note').text(manual ? 'Comprobando…' : '');
		var date = $('#exec-filter-date').val();
		var branch = $('#exec-filter-branch').val();
		var dept = $('#exec-filter-dept').val();
		var category = $('#exec-filter-category').val();
		var donutMetric = $('#exec-donut-metric').val() || 'sales';
		var compMetric = $('#exec-comparative-metric').val() || 'sales';

		$('#exec-refresh-btn').addClass('spinning');

		var baseUrl = (window.Grocy && window.Grocy.BaseUrl) ? window.Grocy.BaseUrl.replace(/\/$/, '') : '';
		$.ajax({
			url: baseUrl + '/api/today/sales-executive',
			method: 'GET',
			data: {
				date: date,
				branch_id: branch === 'all' ? '' : branch,
				department: dept,
				category: category,
				metric: donutMetric,
				comparative_metric: compMetric
			},
			success: function(res) {
				$('#exec-refresh-btn').removeClass('spinning');
				if (!res) return;
				if (manual) {
					var prevSync = execData ? execData.last_updated_iso : null;
					$('#exec-refresh-note').text(prevSync && res.last_updated_iso === prevSync ? 'Comprobado: sin novedades desde la última sincronización' : 'Datos nuevos cargados');
				}
				execData = res;
				updateDOM(res);
				initExecCharts(res);
			},
			error: function(err) {
				$('#exec-refresh-btn').removeClass('spinning');
				if (manual) $('#exec-refresh-note').text('No se pudo comprobar: intente de nuevo');
				console.error('Error fetching executive sales:', err);
			}
		});
	}

	// Flecha y tono (favorable/desfavorable) vienen calculados desde los registros
	function setDelta(sel, obj, field) {
		var el = $(sel);
		if (!el.length || !obj) return;
		var spark = el.attr('data-spark') === '1';
		el.removeClass('delta-up delta-down delta-up-bad delta-down-good');
		if (obj.tone === 'good') el.addClass('delta-up');
		else if (obj.tone === 'bad') el.addClass(spark ? 'delta-up-bad' : 'delta-down');
		el.text(obj[field] || '');
	}

	function markPending() {
		$('#kpi-sales-val, #kpi-target-val, #kpi-margin-val, #kpi-labor-val, .today-exec-mini-val, #exec-proj-val').each(function() {
			$(this).toggleClass('is-pending', /^(Pendiente|Sin )/.test($.trim($(this).text())));
		});
		$('.today-exec-mini-stats').toggleClass('all-pending', /^Pendiente/.test($.trim($('#kpi-tx-val').text())));
	}
	$(markPending);

	function updateDOM(data) {
		var k = data.kpis;
		$('#exec-last-update').text(data.last_updated);
		$('#exec-last-check').text(data.checked_at || '');
		$('#kpi-sales-val').text(k.sales_net.value);
		$('#kpi-sales-cut').text(k.sales_net.cutoff_text || '');
		setDelta('#kpi-sales-delta', k.sales_net, 'delta_pct');
		$('#kpi-sales-comp').text(k.sales_net.comparison_text);
		$('#kpi-target-val').text(k.target_compliance.value);
		setDelta('#kpi-target-delta', k.target_compliance, 'gap_pp');
		$('#kpi-target-cut').text(k.target_compliance.cutoff_target_text || '');
		$('#kpi-target-meta').text(k.target_compliance.target_text);
		$('#kpi-margin-val').text(k.gross_margin.value);
		$('#kpi-profit-val').text(k.gross_margin.numeric !== null ? k.gross_margin.amount : '');
		setDelta('#kpi-margin-delta', k.gross_margin, 'delta_pp');
		$('#kpi-margin-comp').text(k.gross_margin.comparison_text);
		$('#kpi-margin-cogs').text(k.gross_margin.cogs_text || '');
		$('#kpi-margin-scope').text(k.gross_margin.cogs_scope || '').toggleClass('t-warning', !!k.gross_margin.missing_cost_lines);
		$('#kpi-ticket-val').text(k.avg_ticket.value);
		$('#kpi-tx-val').text(k.transactions.value);
		$('#kpi-upt-val').text(k.units_per_tx ? k.units_per_tx.value : '');
		setDelta('#kpi-ticket-delta', $.extend({}, k.avg_ticket, { label: k.avg_ticket.delta_pct ? 'Ticket ' + k.avg_ticket.delta_pct : '' }), 'label');
		setDelta('#kpi-tx-delta', $.extend({}, k.transactions, { label: k.transactions.delta_pct ? 'Transacciones ' + k.transactions.delta_pct : '' }), 'label');
		$('#kpi-labor-title').text(k.labor_sales_per_hour.title || 'Venta por hora');
		$('#card-kpi-labor').attr('title', k.labor_sales_per_hour.tooltip || '');
		$('#kpi-labor-val').text(k.labor_sales_per_hour.value);
		setDelta('#kpi-labor-delta', k.labor_sales_per_hour, 'delta_pct');
		$('#kpi-labor-sub').text(k.labor_sales_per_hour.subtext);

		var pc = data.main_chart.projected_closing;
		$('#exec-proj-val').text(pc.amount);
		setDelta('#exec-proj-delta', pc, 'variance_pct');
		$('#exec-proj-text').text(pc.variance_text);
		$('#exec-proj-basis').text(pc.basis_text || '');
		if (data.main_chart.legend) $('#leg-real').text(data.main_chart.legend.real);
		if (data.heatmap && data.heatmap.period_text) $('#exec-heat-period').text(data.heatmap.period_text + ' · promedio por día de la semana');
		if (data.comparative) { $('#exec-comp-note').text(data.comparative.note || ''); renderCompList(data.comparative); }

		$('#donut-center-val').text(data.donut_chart.total_sales);

		markPending();

		if (data.operational_kpis) {
			var op = data.operational_kpis;
			[['on_shelf_availability', 'op-disp'], ['returns', 'op-returns'], ['staff_coverage', 'op-staff'], ['on_time_delivery', 'op-otif']].forEach(function(m) {
				var o = op[m[0]];
				if (!o) return;
				$('#' + m[1] + '-val').text(o.value);
				$('#' + m[1] + '-period').text(o.period || '');
				if (o.secondary) $('#' + m[1] + '-sub').text(o.secondary);
				setDelta('#' + m[1] + '-delta', o, 'delta');
			});
		}

		if (data.profitability_kpis) {
			var pr = data.profitability_kpis;
			$('#prof-cogs-val').text(pr.cogs.value);
			$('#prof-cogs-sub').text(pr.cogs.subline);
			setDelta('#prof-cogs-delta', pr.cogs, 'delta');
			$('#prof-disc-val').text(pr.discounts.value);
			$('#prof-disc-sub').text(pr.discounts.subline);
			setDelta('#prof-disc-delta', pr.discounts, 'delta');
		}

		if (data.risk_inventory) {
			var rk = data.risk_inventory, nf = function(v) { return Number(v || 0).toLocaleString('en-US'); };
			$('#risk-out-val').text(nf(rk.low_stock.out));
			$('#risk-below-val').text(nf(rk.low_stock.below));
			$('#risk-expiring-val').text(rk.expiring.value);
			setDelta('#risk-expiring-delta', rk.expiring, 'delta');
			$('#risk-adjust-val').text(rk.sensitive_diffs.adjust);
			$('#risk-inv-val').text(rk.sensitive_diffs.investigations);
		}

		if (data.workforce_kpis) {
			['scheduled', 'punched', 'deficit'].forEach(function(key) {
				var w = data.workforce_kpis[key];
				if (!w) return;
				$('#wf-' + key + '-val').text(w.value).toggleClass('t-danger', w.tone === 'bad');
				$('#wf-' + key + '-sub').text(w.sub || '');
			});
		}

		if (data.branch_priorities) {
			var bpHtml = '';
			var baseUrl = (window.Grocy && window.Grocy.BaseUrl) ? window.Grocy.BaseUrl.replace(/\/$/, '') : '';
			data.branch_priorities.forEach(function(bp) {
				bpHtml += '<tr class="today-exec-table-row" data-href="' + esc(baseUrl + bp.href) + '" tabindex="0">' +
					'<td class="today-exec-branch-name">' + esc(bp.branch) + '</td>' +
					'<td><span class="today-exec-table-state"><i class="fa-solid ' + esc(bp.status_icon) + '" style="color: ' + esc(bp.status_icon_color) + '; font-size: 13px;"></i> ' + esc(bp.status_text) + '</span></td>' +
					'<td><span class="today-exec-prio-badge ' + esc(bp.priority_class) + '">' + esc(bp.impact || bp.priority) + '</span></td>' +
					'<td class="today-exec-table-detail">' + esc(bp.detail) + '</td>' +
					'<td><a href="' + esc(baseUrl + bp.href) + '" aria-label="Abrir"><i class="fa-solid fa-chevron-right today-exec-chevron"></i></a></td>' +
				'</tr>';
			});
			if (!bpHtml) {
				bpHtml = '<tr><td colspan="5" class="text-center text-muted py-3">Sin excepciones por sucursal</td></tr>';
			}
			$('#exec-branch-priorities-body').html(bpHtml);
		}

		if (data.priority_decisions) {
			var decBase = (window.Grocy && window.Grocy.BaseUrl) ? window.Grocy.BaseUrl.replace(/\/$/, '') : '';
			$('#exec-decisions-count').text(data.priority_decisions.length + (data.priority_decisions.length === 1 ? ' frente de atención' : ' frentes de atención'));
			if (data.priority_decisions.length > 0) {
				var decHtml = '';
				data.priority_decisions.forEach(function(dec) {
					decHtml += '<a href="' + decBase + esc(dec.href) + '" class="today-exec-decision-card is-' + esc(dec.severity) + '" title="Abrir el detalle filtrado por este frente">' +
						'<div class="today-exec-decision-left">' +
							'<div class="today-exec-decision-icon"><i class="fa-solid ' + esc(dec.icon) + '"></i></div>' +
							'<div class="today-exec-decision-info">' +
								'<div class="d-flex align-items-center flex-wrap mb-1" style="gap: 5px;">' +
									(dec.module_label ? '<span class="today-exec-module-tag">' + esc(dec.module_label) + '</span>' : '') +
									'<span class="today-exec-decision-title">' + esc(dec.title) + '</span>' +
									(dec.count !== undefined ? '<span class="today-exec-count-tag">' + Number(dec.count).toLocaleString('en-US') + ' registro(s)</span>' : '') +
								'</div>' +
								'<span class="today-exec-decision-desc">' + esc(dec.description) + '</span>' +
								'<div class="today-exec-decision-meta">' +
									'<span class="today-exec-decision-impact ' + esc(dec.impact_class) + '">' + esc(dec.impact_text) + '</span>' +
									(dec.owner ? '<span><i class="fa-regular fa-user mr-1"></i>' + esc(dec.owner) + '</span>' : '') +
									(dec.due_date ? '<span><i class="fa-regular fa-clock mr-1"></i>' + esc(dec.due_date) + '</span>' : '') +
								'</div>' +
							'</div>' +
						'</div>' +
						'<i class="fa-solid fa-chevron-right today-exec-decision-arrow"></i>' +
					'</a>';
				});
				$('#exec-decisions-list').html(decHtml);
			} else {
				$('#exec-decisions-list').html('<div class="text-center py-4 text-muted small"><i class="fa-regular fa-circle-check mr-1"></i>Sin frentes de atención abiertos</div>');
			}
		}

		if (data.date) {
			var parts = data.date.split('-');
			if (parts.length === 3) {
				var d = new Date(parts[0], parts[1] - 1, parts[2]);
				var days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
				var months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
				$('#exec-date-display').text(days[d.getDay()] + ', ' + parts[2] + ' ' + months[d.getMonth()] + ' ' + parts[0]);
			}
		}

		if (data.process_statuses) {
			renderProcessStatuses(data.process_statuses, data.last_updated);
		}
	}

	// ==========================================
	// Estado de los procesos: se evalúa con los registros; se resuelve en cada módulo (enlace de acción)
	// ==========================================
	function renderProcessStatuses(statuses, lastUpdated) {
		if (!statuses || !statuses.length) return;
		var tbodyHtml = '';
		var baseUrl = (window.Grocy && window.Grocy.BaseUrl) ? window.Grocy.BaseUrl.replace(/\/$/, '') : '';
		var selectedBranch = $('#exec-filter-branch').val();
		var branchParam = (selectedBranch && selectedBranch !== 'all') ? 'branch_id=' + selectedBranch : '';

		statuses.forEach(function(p) {
			var status = p.status;
			var statusLabel = p.status_label;
			var statusIcon = p.status_icon;
			var situation = p.situation;
			var trendClass = p.trend === 'down' ? 'text-danger' : (p.trend === 'up' ? 'text-success' : 'text-muted');
			var impactClass = (p.economic_impact !== null && Number(p.economic_impact) < 0) ? 'text-danger font-weight-bold' : '';

			var href = p.href;
			if (branchParam) {
				var hp = href.split('#'), path = hp[0].replace(/([?&])branch_id=[^&]*&?/, '$1').replace(/[?&]$/, '');
				href = path + (path.indexOf('?') === -1 ? '?' : '&') + branchParam + (hp[1] ? '#' + hp[1] : '');
			}

			tbodyHtml += '<tr class="today-process-row" data-key="' + esc(p.key) + '">' +
				'<td class="today-process-name-cell">' +
					'<div class="today-process-name-badge">' +
						'<span class="today-process-icon-wrap" style="color: ' + esc(p.color) + '; background: ' + esc(p.color) + '18;">' +
							'<i class="fa-solid ' + esc(p.icon) + '"></i>' +
						'</span>' +
						'<strong>' + esc(p.process) + '</strong>' +
					'</div>' +
				'</td>' +
				'<td class="today-process-status-cell">' +
					'<span class="today-proc-badge is-' + esc(status) + '">' +
						'<i class="fa-solid ' + esc(statusIcon) + '"></i>' +
						'<span>' + esc(statusLabel) + '</span>' +
					'</span>' +
				'</td>' +
				'<td><strong style="font-size: 12px;">' + esc(p.deviation || '—') + '</strong></td>' +
				'<td style="font-size: 12px;" class="' + impactClass + '">' + esc(p.economic_impact_label || 'No calculable') + '</td>' +
				'<td style="font-size: 12px;"><span class="' + trendClass + '">' + esc(p.trend_label || 'Sin datos') + '</span></td>' +
				'<td class="today-process-situation-cell">' +
					'<div class="today-proc-situation-content">' +
						'<span class="today-proc-situation-text">' + esc(situation) + '</span>' +
						'<small class="text-muted d-block" style="font-size: 10.5px;">Responsable: <strong>' + esc(p.owner || 'Operaciones') + '</strong> · ' + esc(p.last_updated || lastUpdated || '') + '</small>' +
						(p.scope_note ? '<span class="today-proc-scope-tag" title="El filtro actual de departamento no subdivide este proceso global">' + esc(p.scope_note) + '</span>' : '') +
						((p.details && p.details.length) ? '<ul class="today-proc-details">' + p.details.map(function(dt) {
							var t = esc(dt.text);
							return '<li class="is-' + esc(dt.tone || 'neutral') + '">' + (dt.href ? '<a href="' + baseUrl + esc(dt.href) + '">' + t + '</a>' : t) + '</li>';
						}).join('') + '</ul>' : '') +
					'</div>' +
				'</td>' +
				'<td class="today-process-action-cell text-right">' +
					'<div class="today-proc-action-group">' +
						'<a href="' + baseUrl + href + '" class="today-proc-action-link" title="' + esc(p.action_text) + ' en su módulo correspondiente">' +
							esc(p.action_text) + ' <i class="fa-solid fa-arrow-right"></i>' +
						'</a>' +
					'</div>' +
				'</td>' +
			'</tr>';
		});

		$('#today-process-tbody').html(tbodyHtml);
		if (lastUpdated) {
			$('#process-demo-clock').text(lastUpdated);
		}
	}

	$('#exec-comparative-metric').on('change', function() {
		var metric = $(this).val();
		if (execData && execData.comparative) {
			initCompChart(execData.comparative, metric);
			renderCompList(execData.comparative);
		}
	});

	$('#exec-donut-metric').on('change', function() {
		var metric = $(this).val();
		if (execData && execData.donut_chart) {
			initDonutChart(execData.donut_chart, metric);
		}
	});

	$('#exec-filter-date').on('change', fetchExecSales);
	// Departamento y categoría filtran solo los cuadros comerciales; avisos, alertas y agenda mantienen toda la sucursal
	function updateScopeNote() {
		var dept = $('#exec-filter-dept').val(), cat = $('#exec-filter-category').val();
		var parts = [];
		if (dept && dept !== 'todos') parts.push($('#exec-filter-dept option:selected').text());
		if (cat && cat !== 'todas') parts.push($('#exec-filter-category option:selected').text());
		$('#exec-scope-note').text(parts.length ? 'Filtro comercial: ' + parts.join(' › ') + ' (ventas, margen y tickets). Avisos, alertas y agenda: toda la sucursal.' : '');
	}
	updateScopeNote();
	$('#exec-filter-dept, #exec-filter-category').on('change', updateScopeNote);
	$('#exec-filter-branch, #exec-filter-dept, #exec-filter-category').on('change', fetchExecSales);
	$('#exec-heatmap-metric').on('change', fetchExecSales);
	$('#exec-refresh-btn').on('click', function () { fetchExecSales(true); });

	// Segmented Tabs Navigation
	$(document).on('click', '.daily-tab-btn', function() {
		var target = $(this).data('tab-target');
		$('.daily-tab-btn').removeClass('active').attr('aria-selected', 'false');
		$(this).addClass('active').attr('aria-selected', 'true');
		$('.today-level-jump a').removeAttr('aria-current').filter('[data-level="' + target + '"]').attr('aria-current', 'page');
		$('.daily-tab-pane').removeClass('active');
		$(target).addClass('active');

		if (target === '#daily-tab-commercial') {
			if (execMainChart) execMainChart.resize();
			if (execDonutChart) execDonutChart.resize();
			if (execCompChart) execCompChart.resize();
		} else if (target === '#daily-tab-operations' && typeof window.__dailyTodayResizeOps === 'function') {
			window.__dailyTodayResizeOps();
		}
	});

	$('#btn-show-all-decisions').on('click', function(e) {
		e.preventDefault();
		$('.daily-tab-btn[data-tab-target="#daily-tab-operations"]').trigger('click');
		$('html, body').animate({ scrollTop: $('#daily-tab-operations').offset().top - 40 }, 250);
	});
	$('.today-level-jump a, .today-pulse-more').on('click', function(e) {
		e.preventDefault();
		var target = $(this).data('level');
		$('.daily-tab-btn[data-tab-target="' + target + '"]').trigger('click');
		$('html, body').animate({ scrollTop: $(target).offset().top - 40 }, 250);
	});

	var urlParams = new URLSearchParams(window.location.search);
	$(document).on('click keydown', '.today-exec-table-row[data-href]', function(e) {
		if (e.type === 'keydown' && e.key !== 'Enter') return;
		if ($(e.target).closest('a').length) return;
		window.location = this.getAttribute('data-href');
	});
	$(document).on('keydown', '.today-exec-dept-row', function(e) { if (e.key === 'Enter') $(this).trigger('click'); });
	if (urlParams.get('tab') === 'processes' || urlParams.get('tab') === 'proc') {
		$('.daily-tab-btn[data-tab-target="#daily-tab-processes"]').trigger('click');
	} else if (urlParams.get('tab') === 'operations' || urlParams.get('tab') === 'ops') {
		$('.daily-tab-btn[data-tab-target="#daily-tab-operations"]').trigger('click');
	}

	$(document).on('mouseenter', '.today-exec-dept-row', function() {
		if (!execDonutChart) return;
		var idx = $(this).index();
		var meta = execDonutChart.getDatasetMeta(0);
		if (meta && meta.data && meta.data[idx]) {
			execDonutChart.updateHoverStyle([meta.data[idx]], null, true);
			execDonutChart.tooltip._active = [meta.data[idx]];
			execDonutChart.tooltip.update(true);
			execDonutChart.render();
		}
	}).on('mouseleave', '.today-exec-dept-row', function() {
		if (!execDonutChart) return;
		var meta = execDonutChart.getDatasetMeta(0);
		if (meta && meta.data) {
			execDonutChart.updateHoverStyle(meta.data, null, false);
			execDonutChart.tooltip._active = [];
			execDonutChart.tooltip.update(true);
			execDonutChart.render();
		}
	});

	$(document).on('click', '.today-exec-dept-row', function() {
		var deptId = $(this).data('dept-id');
		if (deptId) {
			$('#exec-filter-dept').val(deptId).trigger('change');
		}
	});

	$(function() {
		initExecCharts(execData);
		if (execData && execData.process_statuses) {
			renderProcessStatuses(execData.process_statuses, execData.last_updated);
		}
	});
})();
</script>
@endpush
