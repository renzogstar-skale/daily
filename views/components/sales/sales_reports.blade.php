{{--
	Informes comerciales de Ventas y Rentabilidad (SalesReportsService). Se incluye al final de cada pestaña con
	['section' => dashboard|departments|products|branches|history|profitability]; reutiliza las tarjetas y gráficos ded-*.
	Los gráficos se dibujan en public/js/daily_sales_reports.js al mostrarse la pestaña.
--}}
@php
	$R = $salesReports ?? null;
	$na = 'Sin información';
	$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
	$fm = fn($v) => $v === null ? $na : '$' . number_format((float) $v, 0);
	$fm2 = fn($v) => $v === null ? $na : '$' . number_format((float) $v, 2);
	$fp = fn($v) => $v === null ? $na : number_format((float) $v, 1) . ' %';
	$badge = function ($v, $suffix = ' %') use ($na) {
		if ($v === null) return '<span class="ded-text-muted-italics">' . $na . '</span>';
		return $v >= 0 ? '<span class="ded-kpi-badge-green">▲ +' . number_format($v, 1) . $suffix . '</span>' : '<span class="ded-pill-red">▼ ' . number_format($v, 1) . $suffix . '</span>';
	};
	$kpi = function ($icon, $label, $value, $sub, $title = '', $id = '', $highlight = false) use ($h) {
		return '<article class="ded-kpi-card' . ($highlight ? ' is-highlight' : '') . '"' . ($id ? ' data-testid="srep-kpi-' . $h($id) . '"' : '') . ' title="' . $h($title ?: $label) . '">'
			. '<div class="ded-kpi-top"><span class="ded-kpi-icon"><i data-lucide="' . $h($icon) . '"></i></span></div>'
			. '<div class="ded-kpi-label">' . $h($label) . '</div><div class="ded-kpi-val">' . $h($value) . '</div><div class="ded-kpi-sub">' . $sub . '</div></article>';
	};
	$titles = [
		'dashboard' => ['Tablero de ventas', '¿Cuánto estamos vendiendo y cómo evolucionan las ventas?'],
		'departments' => ['Ventas por departamento', '¿Qué departamento genera más ventas?'],
		'products' => ['Productos de venta', '¿Cuáles son los productos más vendidos de toda la empresa?'],
		'branches' => ['Sucursales', '¿Cuál tienda vende más y cuál tiene mejor desempeño comercial?'],
		'history' => ['Historial de ventas', '¿Cómo han evolucionado las ventas a lo largo del tiempo?'],
		'profitability' => ['Rentabilidad', '¿Qué ventas generan mayor utilidad bruta?']
	];
	$P = $R['period'] ?? null;
	$scope = ($filterNames['branch'] ?? 'Todas las sucursales') . ' · ' . ($filterNames['department'] ?? 'Todos los departamentos')
		. (!empty($sharedFilters['group_id']) ? ' · ' . ($filterNames['group'] ?? '') : '') . (!empty($sharedFilters['product']) ? ' · Producto: ' . $sharedFilters['product'] : '');
	$fd = fn($d) => date('d/m/Y', strtotime($d));
@endphp

<section class="ded-wrapper srep" id="srep-{{ $section }}" data-srep="{{ $section }}" data-testid="srep-{{ $section }}">
	<header class="ded-card-header srep-head">
		<div>
			<h2 class="ded-card-title">{{ $titles[$section][0] }}</h2>
			<p class="ded-subtitle mb-0">{{ $titles[$section][1] }}</p>
			@if($P)
			<p class="srep-period mb-0" data-testid="srep-period">
				<i data-lucide="calendar-range"></i>
				<span>Período: <strong>{{ $fd($P['from']) }} – {{ $fd($P['to']) }}</strong> ({{ $P['days'] }} días) · vs. {{ $P['prev_name'] }} {{ $fd($P['prev_from']) }} – {{ $fd($P['prev_to']) }} · {{ $scope }}</span>
			</p>
			@if($R && ($R['archive']['in_period'] || $R['archive']['in_prev']))
			<p class="srep-period mb-0" data-testid="srep-archive-note">
				<i data-lucide="archive"></i>
				<span>Antes del</span> <strong>{{ $fd($R['archive']['cutover']) }}</strong>: <span>{{ $R['archive']['usable'] ? 'archivo histórico consolidado por día, sucursal y departamento; el detalle por producto y por hora se reparte con la mezcla de ventas actual.' : 'el archivo histórico no tiene detalle por categoría ni producto; esas fechas no suman con este filtro.' }}</span>
			</p>
			@endif
			@endif
		</div>
		@if($R)
		<div class="ded-card-tools srep-export">
			@if($section === 'departments')
			<div class="srep-seg" role="group" aria-label="Período de comparación" data-testid="srep-dept-compare">
				<button type="button" class="ded-select-btn{{ ($P['compare_with'] ?? 'prev_period') !== 'year_ago' ? ' is-active' : '' }}" data-srep-compare="prev_period" title="Comparar contra el período equivalente inmediatamente anterior">Período anterior</button>
				<button type="button" class="ded-select-btn{{ ($P['compare_with'] ?? 'prev_period') === 'year_ago' ? ' is-active' : '' }}" data-srep-compare="year_ago" title="Comparar contra las mismas fechas del año anterior">Año anterior</button>
			</div>
			@endif
			<button type="button" class="ded-select-btn" data-srep-export="excel" data-section="{{ $section }}" title="Descargar el informe con los filtros aplicados (Excel)">
				<i data-lucide="file-spreadsheet"></i><span>Excel</span>
			</button>
			@if($section === 'history')
			<button type="button" class="ded-select-btn" data-srep-export="csv" data-section="{{ $section }}" title="Descargar el historial con los filtros aplicados (CSV)">
				<i data-lucide="file-down"></i><span>CSV</span>
			</button>
			@endif
			<button type="button" class="ded-select-btn" data-srep-export="pdf" data-section="{{ $section }}" title="Imprimir o guardar el informe como PDF">
				<i data-lucide="file-text"></i><span>PDF</span>
			</button>
		</div>
		@endif
	</header>

	@if(!$R)
	<article class="ded-card"><span class="ded-text-muted-italics">Sin información: no fue posible calcular el informe comercial con los filtros actuales.</span></article>
	@else

	{{-- ================================================================ 1. TABLERO DE VENTAS --}}
	@if($section === 'dashboard')
	@php
		$D = $R['dashboard'];
		$acc = $D['accumulated'];
		$goalSub = $D['goal'] === null
			? ($R['product_filtered'] ? 'Sin meta para este filtro' : 'Sin meta registrada para todo el período')
			: 'Meta ' . $fm($D['goal']) . ' · ' . ($D['goal_gap'] >= 0 ? 'supera por ' : 'faltan ') . $fm(abs($D['goal_gap']));
	@endphp
	<section class="srep-kpis srep-kpis-8" aria-label="Indicadores del tablero de ventas">
		{!! $kpi('dollar-sign', 'Ventas netas', $fm($D['net']), $badge($D['growth_pct']) . '&nbsp;vs. ' . $h($P['prev_name']), 'Ventas brutas − descuentos (sin IVA) de ventas completadas', 'net', true) !!}
		{!! $kpi('banknote', 'Ventas brutas', $fm($D['gross']), 'Anterior: ' . $fm($D['prev_gross']), 'Precio de lista × cantidad antes del descuento del ticket (sin IVA)', 'gross') !!}
		{!! $kpi('tag', 'Descuentos', $fm($D['discounts']), $D['discount_pct'] === null ? $na : $fp($D['discount_pct']) . ' de las ventas brutas', 'Ventas brutas − ventas netas', 'discounts') !!}
		{!! $kpi('target', 'Cumplimiento de meta', $fp($D['goal_pct']), $goalSub, 'Ventas netas ÷ meta del período × 100 (sin tope: puede superar 100 %)', 'goal-pct') !!}
		{!! $kpi('receipt', 'Ticket promedio', $fm2($D['avg_ticket']), 'Anterior: ' . $fm2($D['prev_avg_ticket']), 'Ventas netas ÷ transacciones', 'avg-ticket') !!}
		{!! $kpi('shopping-cart', 'Transacciones', number_format($D['tickets']), $badge($D['tickets_delta']) . '&nbsp;vs. ' . $h($P['prev_name']), 'Tickets de venta completados', 'tickets') !!}
		{!! $kpi('calendar-check-2', 'Venta diaria promedio', $fm($D['daily_avg']), 'Anterior: ' . $fm($D['prev_daily_avg']), 'Ventas netas ÷ días del período', 'daily-avg') !!}
		<article class="ded-kpi-card" data-testid="srep-kpi-accumulated" title="Ventas acumuladas a la fecha final del período">
			<div class="ded-kpi-top"><span class="ded-kpi-icon"><i data-lucide="layers"></i></span></div>
			<div class="ded-kpi-label">Ventas acumuladas</div>
			<div class="srep-acc">
				<span title="{{ $acc['day']['label'] }}">Día <strong>{{ $fm($acc['day']['net']) }}</strong></span>
				<span title="{{ $acc['week']['label'] }}">Semana <strong>{{ $fm($acc['week']['net']) }}</strong></span>
				<span title="{{ $acc['month']['label'] }}">Mes <strong>{{ $fm($acc['month']['net']) }}</strong></span>
			</div>
			<div class="ded-kpi-sub">Al {{ $fd($P['to']) }}</div>
		</article>
	</section>

	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Evolución de ventas</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Ventana de días">
					<button type="button" class="ded-select-btn" data-srep-window="7">7 días</button>
					<button type="button" class="ded-select-btn is-active" data-srep-window="30">30 días</button>
					<button type="button" class="ded-select-btn" data-srep-window="90">90 días</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashTrend" aria-label="Evolución de ventas de los últimos días"></canvas></div>
			<p class="srep-note"><span>Días hasta el</span> <span>{{ $fd($P['to']) }}</span></p>
		</article>
		<article class="ded-card" data-testid="srep-dash-comparisons">
			<header class="ded-card-header"><h2 class="ded-card-title">Comparación comercial</h2></header>
			<div class="srep-scroll">
				<table class="ded-table-top5" data-export-name="Comparación comercial" aria-label="Comparación de ventas netas entre períodos de igual duración">
					<thead><tr><th>Comparación</th><th class="text-right">Actual</th><th class="text-right">Referencia</th><th class="text-right">Var.</th></tr></thead>
					<tbody>
						@foreach($D['comparisons'] as $cp)
						<tr data-testid="srep-cmp-{{ $cp['key'] }}" title="{{ $fd($cp['from']) }}{{ $cp['to'] !== $cp['from'] ? ' – ' . $fd($cp['to']) : '' }} vs. {{ $fd($cp['prev_from']) }}{{ $cp['prev_to'] !== $cp['prev_from'] ? ' – ' . $fd($cp['prev_to']) : '' }} ({{ $cp['days'] }} {{ $cp['days'] === 1 ? 'día' : 'días' }})">
							<td><div class="ded-prod-name">{{ $cp['label'] }}</div></td>
							<td class="ded-table-num" data-export-value="{{ $cp['net'] }}">{{ $fm($cp['net']) }}</td>
							<td class="ded-table-num" data-export-value="{{ $cp['prev_net'] ?? '' }}">{{ $fm($cp['prev_net']) }}</td>
							<td class="ded-table-share" data-export-value="{{ $cp['delta_pct'] ?? '' }}">{!! $badge($cp['delta_pct']) !!}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			<p class="srep-note">
				<span>Cada fila compara la misma cantidad de días.</span>
				@if($D['cut_time'])<span>Día en curso: la referencia se corta a las</span> <span>{{ $D['cut_time'] }}</span>.@endif
			</p>
		</article>
	</section>

	<section class="ded-row-3">
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Curva comercial por hora</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Ventas netas</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot"></span><span>Transacciones</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashHourly" aria-label="Ventas y transacciones por hora del día"></canvas></div>
			<p class="srep-note" data-testid="srep-dash-per-hour"><span>Venta por hora con ventas:</span> <strong>{{ $fm($D['per_hour']) }}</strong> <span>({{ number_format($D['active_hours']) }} h)</span></p>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Actual vs. {{ $P['prev_name'] }}</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Actual</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#94a3b8;"></span><span>Anterior</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashVsPrev" aria-label="Ventas actuales frente al período anterior"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Acumulado vs. meta</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Acumulado</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot"></span><span>Meta</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashCum" aria-label="Ventas acumuladas frente a la meta"></canvas></div>
			<p class="srep-note">@if($D['goal'] === null)<span>Meta: Sin información.</span>@else<span>Cumplimiento:</span> <strong>{{ $fp($D['goal_pct']) }}</strong> <span>de</span> {{ $fm($D['goal']) }}@endif</p>
		</article>
	</section>

	<section class="ded-row-3">
		<article class="ded-card" data-testid="srep-dash-top5">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Top 5 productos</h2>
				<button type="button" class="ded-select-btn" data-srep-goto="productos" title="Ver el análisis completo en Productos"><span>Ver todos</span></button>
			</header>
			@if(empty($D['top5']))
				<span class="ded-text-muted-italics">Sin información: no hay ventas de productos en el período.</span>
			@else
			<div class="srep-scroll">
				<table class="ded-table-top5" data-export-name="Top 5 productos" aria-label="Top 5 productos por ventas netas">
					<thead><tr><th>#</th><th>Producto</th><th class="text-right">Unid.</th><th class="text-right">Ventas netas</th><th class="text-right">Part.</th></tr></thead>
					<tbody>
						@foreach($D['top5'] as $i => $p)
						<tr class="is-clickable" data-srep-goto="productos" title="{{ $p['name'] }} · {{ $p['sku'] }} · {{ $p['dept'] }}">
							<td><span class="ded-rank-circle">{{ $i + 1 }}</span></td>
							<td><div class="ded-prod-name">{{ $p['name'] }}</div></td>
							<td class="ded-table-num" data-export-value="{{ $p['units'] }}">{{ number_format($p['units'], 0) }}</td>
							<td class="ded-table-price" data-export-value="{{ $p['net'] }}">{{ $fm($p['net']) }}</td>
							<td class="ded-table-share" data-export-value="{{ $p['share_pct'] ?? '' }}">{{ $fp($p['share_pct']) }}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@endif
		</article>
		<article class="ded-card" data-testid="srep-dash-channels">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Ventas por canal</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Agrupar por">
					<button type="button" class="ded-select-btn is-active" data-srep-mix="channels">Canal</button>
					<button type="button" class="ded-select-btn" data-srep-mix="payments">Medio de pago</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashChannels" aria-label="Evolución diaria de ventas netas por canal"></canvas></div>
			<p class="srep-note" id="srepDashMixNote">
				@foreach($D['channels'] as $ch)<span>{{ $ch['name'] }}</span> <strong>{{ $fp($ch['share_pct']) }}</strong>@if(!$loop->last) · @endif @endforeach
			</p>
		</article>
		<article class="ded-card" data-testid="srep-dash-alerts">
			<header class="ded-card-header"><h2 class="ded-card-title">Alertas comerciales</h2></header>
			@if(empty($D['alerts']))
				<span class="ded-text-muted-italics">Sin desviaciones de venta frente a los umbrales del tablero.</span>
			@else
			<div class="ded-comp-col">
				@foreach(array_slice($D['alerts'], 0, 6) as $al)
				<div class="ded-comp-row is-clickable" data-srep-goto="{{ $al['tab'] }}" data-testid="srep-alert-{{ $al['kind'] }}" title="{{ $al['detail'] }}" style="cursor:pointer;">
					<span class="ded-comp-label">{{ $al['title'] }}<br><small>{{ $al['detail'] }}</small></span>
					<span class="{{ $al['severity'] === 'danger' ? 'ded-pill-red' : 'ded-text-muted-italics' }}">{{ $al['severity'] === 'danger' ? 'Crítica' : 'Atención' }}</span>
				</div>
				@endforeach
			</div>
			@if(count($D['alerts']) > 6)<p class="srep-note"><span>Y</span> {{ count($D['alerts']) - 6 }} <span>más.</span></p>@endif
			@endif
		</article>
	</section>
	@endif

	{{-- ================================================================ 2. VENTAS POR DEPARTAMENTO --}}
	@if($section === 'departments')
	@php
		$DP = $R['departments'];
		$rows = $DP['rows'];
		$sold = array_values(array_filter($rows, fn($r) => !$r['no_sales']));
		$lead = $sold[0] ?? null;
		$withDelta = array_values(array_filter($sold, fn($r) => $r['delta_pct'] !== null));
		usort($withDelta, fn($a, $b) => $b['delta_pct'] <=> $a['delta_pct'] ?: ($b['delta_abs'] <=> $a['delta_abs']));
		$best = $withDelta[0] ?? null;
		$worst = count($withDelta) > 0 ? end($withDelta) : null;
		$withGoal = array_filter($rows, fn($r) => $r['goal'] !== null);
		$deptGoal = $DP['total_goal'] ?? array_sum(array_column($withGoal, 'goal'));
		$deptNetWithGoal = array_sum(array_column($withGoal, 'net'));
		$deptWithGoal = count($withGoal);
		$deptMet = count(array_filter($withGoal, fn($r) => $r['goal_pct'] >= 100));
		$RC = $DP['reconciliation'];
		$G = $DP['growth'];
		$PS = $DP['period_status'] ?? [];
		$OR = $DP['data_origin'] ?? [];
		$periodSpan = $fd($P['from']) . ' – ' . $fd($P['to']);
		$prevSpan = $fd($P['prev_from']) . ' – ' . $fd($P['prev_to']);
		$dn = fn($r) => ($r['code'] ? $r['code'] . ' · ' : '') . $r['name'];
		$fSignedM = fn($v) => $v === null ? $na : (($v >= 0 ? '+$' : '-$') . number_format(abs((float) $v), 0));
		$fSignedM2 = fn($v) => $v === null ? $na : (($v >= 0 ? '+$' : '-$') . number_format(abs((float) $v), 2));
		$rankBadge = function ($r) {
			if ($r['rank_change'] === null) return $r['rank'] !== null && $r['prev_rank'] === null ? '<span class="ded-text-muted-italics">Nuevo</span>' : '';
			if ($r['rank_change'] === 0) return '';
			return $r['rank_change'] > 0 ? '<span class="ded-kpi-badge-green">▲ ' . $r['rank_change'] . '</span>' : '<span class="ded-pill-red">▼ ' . abs($r['rank_change']) . '</span>';
		};
		$originText = 'POS real';
		$originClass = 'srep-origin-real';
		if (!empty($OR['has_pos']) && !empty($OR['has_archive'])) {
			$originText = 'Mixto: POS real + Archivo (' . implode('/', $OR['archive_sources'] ?: ['demo']) . ')';
			$originClass = 'srep-origin-mixed';
		} elseif (!empty($OR['has_archive']) && empty($OR['has_pos'])) {
			$originText = 'Archivo histórico (' . implode('/', $OR['archive_sources'] ?: ['demo']) . ')';
			$originClass = 'srep-origin-demo';
		} elseif (!empty($OR['has_demo_products'])) {
			$originText = 'POS real (incluye SKUs demo)';
			$originClass = 'srep-origin-mixed';
		}
		$selectedDeptKey = $sharedFilters['department'] ?? '';
		$hmax = 0;
		foreach ($DP['heat'] as $hr) {
			$hmax = max($hmax, max($hr ?: [0]));
		}
	@endphp

	{{-- FASE 2: 5 KPIs COMERCIALES OBLIGATORIOS --}}
	<section class="ded-kpis-grid-5 srep-kpis srep-dept-kpis-5" aria-label="Indicadores comerciales por departamento" data-testid="srep-dept-kpis">
		{!! $kpi(
			'dollar-sign',
			'Ventas netas del período',
			$fm2($DP['total_net'] ?? $R['dashboard']['net']),
			$badge($DP['total_net'] > 0 || $DP['prev_total'] > 0 ? \Grocy\Services\SalesReportsService::Delta($DP['total_net'], $DP['prev_total'] > 0 ? $DP['prev_total'] : null) : null)
				. ' <span>vs. ' . $h($P['prev_name']) . ' (' . $h($prevSpan) . ')</span>',
			'Período: ' . $periodSpan . ' · Ventas netas: ' . $fm2($DP['total_net'] ?? 0) . ' · Anterior: ' . $fm2($DP['prev_total'] ?? 0),
			'dept-net',
			true
		) !!}
		{!! $kpi(
			'target',
			'Cumplimiento de meta comercial',
			$deptGoal ? number_format($deptNetWithGoal / $deptGoal * 100, 1) . ' %' : $na,
			$deptGoal
				? ($deptMet . ' de ' . $deptWithGoal . ' deptos cumplen · Meta ' . $fm2($deptGoal) . ' (' . $h($periodSpan) . ')')
				: ('Sin metas asignadas (' . $h($periodSpan) . ')'),
			'Período: ' . $periodSpan . ' · Ventas con meta ' . $fm2($deptNetWithGoal) . ' ÷ Meta ' . $fm2($deptGoal),
			'dept-goal'
		) !!}
		{!! $kpi(
			'award',
			'Departamento con mayor venta',
			$lead ? $dn($lead) : $na,
			$lead ? ('<strong>' . $fm2($lead['net']) . '</strong> · ' . $fp($lead['share_pct']) . ' · ' . $h($periodSpan)) : $na,
			$lead ? ('Período: ' . $periodSpan . ' · ' . $dn($lead) . ': ' . $fm2($lead['net']) . ' (' . $fp($lead['share_pct']) . ')') : $na,
			'dept-lead'
		) !!}
		{!! $kpi(
			'trending-up',
			'Departamento con mayor crecimiento',
			$best ? $dn($best) : $na,
			$best ? ($badge($best['delta_pct']) . ' <span>(' . $fSignedM($best['delta_abs']) . ') vs. ' . $h($prevSpan) . '</span>') : ('Sin período comparable para ' . $h($periodSpan)),
			$best ? ('Período: ' . $periodSpan . ' vs. ' . $prevSpan . ' · Variación ' . $fSignedM2($best['delta_abs'])) : $na,
			'dept-best'
		) !!}
		{!! $kpi(
			'trending-down',
			'Departamento con mayor caída',
			$worst && $worst['delta_pct'] < 0 ? $dn($worst) : ($withDelta ? 'Ninguno' : $na),
			$worst && $worst['delta_pct'] < 0
				? ($badge($worst['delta_pct']) . ' <span>(' . $fSignedM($worst['delta_abs']) . ') vs. ' . $h($prevSpan) . '</span>')
				: ($withDelta ? ('Todos los departamentos crecen (' . $h($periodSpan) . ')') : ('Sin período comparable para ' . $h($periodSpan))),
			$worst && $worst['delta_pct'] < 0 ? ('Período: ' . $periodSpan . ' vs. ' . $prevSpan . ' · Variación ' . $fSignedM2($worst['delta_abs'])) : $na,
			'dept-worst'
		) !!}
	</section>

	{{-- Conciliación, consistencia de períodos y origen real/archivo (Fase 6) --}}
	<article class="ded-card mb-3" data-testid="srep-dept-reconciliation">
		<div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
			<p class="srep-note mb-0">
				<i data-lucide="{{ $RC['ok'] ? 'check-circle-2' : 'alert-triangle' }}"></i>
				<span>Σ departamentos</span> <strong>{{ $fm2($RC['sum']) }}</strong>
				@if($RC['ok'])
					<span>= total comercial consolidado</span> <strong>{{ $fm2($RC['total']) }}</strong>.
				@else
					<span>frente a total comercial</span> <strong>{{ $fm2($RC['total']) }}</strong> (<span>diferencia</span> {{ $fm2($RC['diff']) }}).
				@endif
				<span>Cada ticket se prorratea por línea con su descuento, sin IVA (sin duplicar ventas entre departamentos).</span>
				@if($RC['other_net'] > 0)
					<span>«Otros» suma</span> <strong>{{ $fm2($RC['other_net']) }}</strong>: <span>productos sin departamento</span>@if($RC['unmapped']) ({{ implode(', ', array_map(fn($u) => ($u['value'] ?? 'vacío') . ' · ' . $u['products'], $RC['unmapped'])) }})@endif.
				@endif
			</p>
			<span class="srep-origin-badge {{ $originClass }}" data-testid="srep-dept-origin" title="Origen de datos del período consultado">{{ $originText }}</span>
		</div>
		@if($DP['without_sales'])
		<p class="srep-note mb-0 mt-1" data-testid="srep-dept-no-sales"><i data-lucide="circle-slash"></i> <span>Sin ventas en el período:</span> <strong>{{ implode(', ', $DP['without_sales']) }}</strong>. <span>Aparecen en la tabla con «Sin ventas».</span></p>
		@endif
		@if(!empty($PS['cut_time']))
		<p class="srep-note mb-0 mt-1" data-testid="srep-dept-partial-note"><i data-lucide="clock"></i> <span>Período en curso (incluye hoy): cada comparación corta su último día a las</span> <strong>{{ $PS['cut_time'] }}</strong> <span>para comparar tramos horarios equivalentes.</span></p>
		@elseif(!empty($PS['includes_future']))
		<p class="srep-note mb-0 mt-1" data-testid="srep-dept-partial-note"><i data-lucide="alert-circle"></i> <span>El rango seleccionado incluye fechas futuras aún sin ventas registradas.</span></p>
		@endif
		@if(empty($DP['ly_has_data']))
		<p class="srep-note mb-0 mt-1" data-testid="srep-dept-no-ly"><i data-lucide="info"></i> <span>Año anterior ({{ $fd(date('Y-m-d', strtotime($P['from'] . ' -1 year'))) }} – {{ $fd(date('Y-m-d', strtotime($P['to'] . ' -1 year'))) }}): <strong>Sin información</strong> histórica registrada; no se estiman cifras.</span></p>
		@endif
	</article>

	@if(!$sold)
	<article class="ded-card mb-3" data-testid="srep-dept-empty"><span class="ded-text-muted-italics">Sin información: no hay ventas en el período con estos filtros.</span></article>
	@else

	{{-- FASE 4: DETALLE Y DRILLDOWN COMERCIAL POR DEPARTAMENTO -> SUBCATEGORÍA -> SKU --}}
	<article class="ded-card mb-3 srep-select-card" id="srepDeptDrillCard">
		<header class="ded-card-header">
			<div>
				<h2 class="ded-card-title">Detalle comercial por departamento</h2>
				<p class="srep-note mb-0">Haz clic en cualquier departamento en gráficos o tabla para explorar subcategorías y SKUs sin salir del análisis</p>
			</div>
			<select class="ded-select-btn srep-select" id="srepDeptSelect" aria-label="Seleccionar departamento">
				<option value="">Todos los departamentos</option>
				@foreach($sold as $r)<option value="{{ $r['key'] }}" @if($selectedDeptKey === $r['key']) selected @endif>{{ $dn($r) }}</option>@endforeach
			</select>
		</header>
		<div class="srep-detail" id="srepDeptDetail" aria-live="polite"></div>
	</article>

	{{-- FASE 3: LOS 8 GRÁFICOS OBLIGATORIOS --}}
	{{-- 1. Ranking por ventas netas | 2. Participación porcentual --}}
	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">1. Ranking de departamentos por ventas netas</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(205, count($sold) * 28 + 30) }}px;"><canvas id="srepDeptBars" aria-label="Ranking de departamentos por ventas netas"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">2. Participación porcentual por departamento</h2></header>
			<div class="ded-dept-donut-layout">
				<div class="ded-donut-wrap">
					<canvas id="srepDeptDonut" aria-label="Participación porcentual de cada departamento"></canvas>
					<div class="ded-donut-center-info">
						<span class="ded-donut-center-amount">{{ $fm($DP['total_net'] ?? $R['dashboard']['net']) }}</span>
						<span class="ded-donut-center-label">Ventas netas</span>
					</div>
				</div>
				<div class="ded-dept-list">
					@foreach($sold as $r)
					<div class="ded-dept-row" data-srep-dept="{{ $r['key'] }}" title="{{ $r['name'] }}: {{ $fm2($r['net']) }} ({{ $fp($r['share_pct']) }})">
						<div class="ded-dept-name-box"><span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span><span>{{ $dn($r) }}</span></div>
						<span class="ded-dept-pct">{{ $fp($r['share_pct']) }}</span>
					</div>
					@endforeach
				</div>
			</div>
		</article>
	</section>

	{{-- 3. Evolución de ventas diarias y semanales | 4. Ventas actuales vs. período anterior --}}
	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title">3. Evolución de ventas por departamento</h2>
					<span class="srep-note mb-0" id="srepDeptEvoNote">Cinco principales · {{ $periodSpan }}</span>
				</div>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Periodicidad de evolución">
					<button type="button" class="ded-select-btn is-active" data-srep-evo="daily">Diaria</button>
					<button type="button" class="ded-select-btn" data-srep-evo="weekly">Semanal</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDeptEvo" aria-label="Evolución diaria y semanal por departamento"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">4. Ventas actuales vs. período anterior</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Actual</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#94a3b8;"></span><span>Período anterior</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDeptVsPrev" aria-label="Ventas actuales frente al período anterior por departamento"></canvas></div>
		</article>
	</section>

	{{-- 5. Comparación contra el año anterior | 6. Curva de ventas por hora --}}
	<section class="ded-row-2">
		<article class="ded-card" data-testid="srep-dept-vs-ly">
			<header class="ded-card-header">
				<h2 class="ded-card-title">5. Comparación contra el año anterior (TY vs. LY)</h2>
				@if(!empty($DP['ly_has_data']))
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Año actual</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#64748b;"></span><span>Año anterior</span></span>
				</div>
				@endif
			</header>
			@if(!empty($DP['ly_has_data']))
			<div class="ded-chart-canvas-box"><canvas id="srepDeptVsLy" aria-label="Comparación de ventas contra el año anterior por departamento"></canvas></div>
			<p class="srep-note">
				<span>{{ $periodSpan }} vs. mismo período LY</span> · <span>Total LY:</span> <strong>{{ $fm2($DP['ly_total']) }}</strong> · {!! $badge($DP['ly_delta_pct']) !!}
				@if(!empty($OR['ly_has_archive']) && empty($OR['ly_has_pos']))<span> · Origen LY: archivo histórico ({{ implode('/', $OR['ly_archive_sources'] ?: ['demo']) }}).</span>@endif
			</p>
			@else
			<div class="srep-empty-chart" data-testid="srep-dept-vs-ly-empty">
				<i data-lucide="calendar-off"></i>
				<div>
					<strong>Sin información</strong>
					<p class="srep-note mb-0">No existen ventas registradas para el mismo período del año anterior.</p>
				</div>
			</div>
			@endif
		</article>
		<article class="ded-card" data-testid="srep-dept-hourly">
			<header class="ded-card-header">
				<h2 class="ded-card-title">6. Curva de ventas por hora</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Medida horaria">
					<button type="button" class="ded-select-btn is-active" data-srep-hour="net">Ventas</button>
					<button type="button" class="ded-select-btn" data-srep-hour="share">% del día</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDeptHourly" aria-label="Curva de ventas por hora por departamento"></canvas></div>
			<p class="srep-note" id="srepDeptHourNote"><span>Cinco principales.</span> <span>«% del día» compara el perfil horario de cada departamento.</span>@if($DP['hourly']['archive_spread']) <span>Los días del archivo histórico se reparten con la curva horaria de referencia.</span>@endif</p>
		</article>
	</section>

	{{-- 8. Ventas acumuladas vs. metas por departamento | Crecimiento multi-horizonte --}}
	<section class="ded-row-2">
		<article class="ded-card" data-testid="srep-dept-cum-goal">
			<header class="ded-card-header">
				<h2 class="ded-card-title">8. Ventas acumuladas vs. metas por departamento</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Vista de metas acumuladas">
					<button type="button" class="ded-select-btn is-active" data-srep-cum="dept">Por departamento</button>
					<button type="button" class="ded-select-btn" data-srep-cum="curve">Curva acumulada</button>
				</div>
			</header>
			@if($DP['has_goals'])
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(205, count($sold) * 28 + 30) }}px;"><canvas id="srepDeptCumGoal" aria-label="Ventas acumuladas frente a metas por departamento"></canvas></div>
			<p class="srep-note" id="srepDeptCumNote"><span>Período {{ $periodSpan }}: compara el acumulado real frente a la meta comercial prorrateada de cada departamento.</span></p>
			@else
			<div class="srep-empty-chart">
				<i data-lucide="target"></i>
				<div>
					<strong>Sin información</strong>
					<p class="srep-note mb-0">No hay metas comerciales configuradas para el filtro o período actual.</p>
				</div>
			</div>
			@endif
		</article>
		<article class="ded-card" data-testid="srep-dept-growth">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Crecimiento por horizonte temporal</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Horizonte de crecimiento">
					@foreach($G as $gk => $gw)
					<button type="button" class="ded-select-btn{{ $loop->first ? ' is-active' : '' }}" data-srep-growth="{{ $gk }}" title="{{ $gw['desc'] }}">{{ $gw['label'] }}</button>
					@endforeach
				</div>
			</header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(205, count($sold) * 28 + 30) }}px;"><canvas id="srepDeptGrowth" aria-label="Crecimiento semanal, mensual y anual por departamento"></canvas></div>
			@foreach($G as $gk => $gw)
			<p class="srep-note" data-srep-growth-note="{{ $gk }}" @if(!$loop->first) hidden @endif>
				<span>{{ $gw['desc'] }}:</span> <strong>{{ $fd($gw['from']) }} – {{ $fd($gw['to']) }}</strong> vs. {{ $fd($gw['prev_from']) }} – {{ $fd($gw['prev_to']) }}
				({{ $gw['days'] }} vs. {{ $gw['prev_days'] }} días{{ $gw['cut_time'] ? ', último día hasta las ' . $gw['cut_time'] : '' }}) · <span>Total</span> {!! $badge($gw['delta_pct']) !!}
			</p>
			@endforeach
		</article>
	</section>

	{{-- 7. Mapa de calor departamento × sucursal (exclusivamente ventas) --}}
	<article class="ded-card mb-3" data-testid="srep-dept-heatmap">
		<header class="ded-card-header">
			<h2 class="ded-card-title">7. Mapa de calor departamento × sucursal (solo ventas)</h2>
			<div class="ded-card-tools srep-seg" role="group" aria-label="Medida mapa de calor">
				<button type="button" class="ded-select-btn is-active" data-srep-heat="net">Ventas netas</button>
				<button type="button" class="ded-select-btn" data-srep-heat="share">% de la sucursal</button>
				<button type="button" class="ded-select-btn" data-srep-heat="dept_share">% del depto.</button>
			</div>
		</header>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-heat" id="srepDeptHeat" data-export-name="Departamento x sucursal">
				<thead><tr><th>Departamento</th>@foreach($DP['heat_branches'] as $b)<th class="text-right">{{ $b['name'] }}</th>@endforeach<th class="text-right">Total depto.</th></tr></thead>
				<tbody>
				@foreach($sold as $r)
					<tr data-srep-dept-row="{{ $r['key'] }}">
						<td><span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span> {{ $dn($r) }}</td>
						@foreach($DP['heat_branches'] as $b)
							@php
								$v = $DP['heat'][$r['key']][$b['id']] ?? 0;
								$bt = $DP['branch_totals'][$b['id']] ?? 0;
								$shBranch = $bt > 0 ? round($v / $bt * 100, 1) : null;
								$shDept = $r['net'] > 0 ? round($v / $r['net'] * 100, 1) : null;
								$a = $hmax > 0 ? round($v / $hmax, 2) : 0;
							@endphp
							<td class="text-right srep-heat-cell" style="--srep-a: {{ $a }};" data-export-value="{{ round($v, 2) }}" data-net="{{ $v > 0 ? $fm($v) : '—' }}" data-share="{{ $v > 0 ? $fp($shBranch) : '—' }}" data-dept-share="{{ $v > 0 ? $fp($shDept) : '—' }}" title="{{ $r['name'] }} · {{ $b['name'] }} ({{ $periodSpan }}): {{ $fm2($v) }} ({{ $fp($shBranch) }} de la sucursal · {{ $fp($shDept) }} del depto.)">{{ $v > 0 ? $fm($v) : '—' }}</td>
						@endforeach
						<td class="text-right ded-table-price" title="{{ $r['name'] }}: {{ $fm2($r['net']) }}">{{ $fm2($r['net']) }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
	</article>
	@endif

	{{-- FASE 5: TABLA ANALÍTICA COMERCIAL (10 COLUMNAS EXACTAS + BÚSQUEDA + MULTI-SELECCIÓN + SUBCATEGORÍAS) --}}
	<article class="ded-card mb-3">
		<header class="ded-card-header srep-table-head">
			<div>
				<h2 class="ded-card-title">Tabla analítica por departamento</h2>
				<p class="srep-note mb-0">Haz clic en «+» para expandir subcategorías, marca varios departamentos para comparar o haz clic en la fila para ver su detalle.</p>
			</div>
			<div class="srep-table-controls">
				<div class="srep-search-wrap">
					<i data-lucide="search"></i>
					<input type="search" id="srepDeptSearch" class="srep-search-input" placeholder="Buscar departamento..." aria-label="Buscar departamento">
				</div>
			</div>
		</header>
		<div class="srep-multi-bar" id="srepDeptMultiBar" hidden data-testid="srep-dept-multi-bar"></div>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable srep-dept-analytical" id="srepDeptTable" data-export-name="Departamentos" data-testid="srep-dept-table">
				<thead><tr>
					<th data-sort="text" data-col-sticky>Departamento</th>
					<th class="text-right" data-sort="num">Ventas netas</th>
					<th class="text-right" data-sort="num">Participación %</th>
					<th class="text-right" data-sort="num">Ventas anteriores</th>
					<th class="text-right" data-sort="num">Variación $</th>
					<th class="text-right" data-sort="num">Variación %</th>
					<th class="text-right" data-sort="num">Unidades</th>
					<th class="text-right" data-sort="num">Tickets</th>
					<th class="text-right" data-sort="num">Meta $</th>
					<th class="text-right" data-sort="num">Cumplimiento %</th>
				</tr></thead>
				<tbody>
				@foreach($rows as $r)
					<tr data-srep-dept-row="{{ $r['key'] }}" data-dept-name="{{ strtolower($dn($r)) }}" @if($r['no_sales']) class="srep-muted" @endif>
						<td data-v="{{ $r['name'] }}" data-col-sticky title="{{ $r['sources'] ? 'Agrupa: ' . implode(', ', $r['sources']) : 'Productos sin departamento configurado' }}">
							<div class="srep-dept-cell">
								<input type="checkbox" class="srep-dept-chk" value="{{ $r['key'] }}" aria-label="Seleccionar {{ $r['name'] }}" title="Comparar {{ $r['name'] }}">
								@if(!empty($r['subcategories']))
								<button type="button" class="srep-expand-btn" data-srep-expand="{{ $r['key'] }}" aria-expanded="false" title="Expandir subcategorías de {{ $r['name'] }}"><i data-lucide="chevron-right"></i></button>
								@else
								<span class="srep-expand-spacer"></span>
								@endif
								<span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span>
								<span class="srep-dept-label">{{ $dn($r) }}</span>
								@if($r['rank']){!! $rankBadge($r) !!}@endif
								@if($r['no_sales']) <span class="ded-text-muted-italics">· Sin ventas</span>@endif
							</div>
						</td>
						<td class="ded-table-price" data-v="{{ $r['net'] }}" title="{{ $r['name'] }} · Ventas netas: {{ $fm2($r['net']) }}">{{ $fm2($r['net']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['share_pct'] ?? -1 }}" title="{{ $fp($r['share_pct']) }} del total comercial">{{ $fp($r['share_pct']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['prev_net'] }}" title="Anterior ({{ $prevSpan }}): {{ $fm2($r['prev_net']) }}">{{ $fm2($r['prev_net']) }}</td>
						<td class="text-right ded-table-num {{ $r['delta_abs'] >= 0 ? 'srep-val-pos' : 'srep-val-neg' }}" data-v="{{ $r['delta_abs'] }}" title="Variación monetaria: {{ $fSignedM2($r['delta_abs']) }}">{{ $fSignedM2($r['delta_abs']) }}</td>
						<td class="text-right" data-v="{{ $r['delta_pct'] ?? -99999 }}">{!! $badge($r['delta_pct']) !!}</td>
						<td class="ded-table-num" data-v="{{ $r['units'] }}">{{ number_format($r['units'], 0) }}</td>
						<td class="ded-table-num" data-v="{{ $r['tickets'] }}" title="Tickets que contienen productos de {{ $r['name'] }}">{{ number_format($r['tickets']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['goal'] ?? -1 }}" title="Meta comercial ({{ $periodSpan }}): {{ $fm2($r['goal']) }}">{{ $fm2($r['goal']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['goal_pct'] ?? -1 }}">{{ $fp($r['goal_pct']) }}</td>
					</tr>
				@endforeach
				</tbody>
				@php
					$totNet = $DP['total_net'] ?? 0.0;
					$totPrev = $DP['prev_total'] ?? 0.0;
					$totDeltaAbs = round($totNet - $totPrev, 2);
					$totDeltaPct = $totPrev > 0 ? \Grocy\Services\SalesReportsService::Delta($totNet, $totPrev) : null;
					$totShare = ($RC['total'] ?? 0) > 0 ? round($totNet / $RC['total'] * 100, 1) : 0.0;
				@endphp
				<tfoot>
					<tr class="srep-tfoot-row" id="srepDeptTableFoot">
						<td data-col-sticky><strong>Total consolidado ({{ count($sold) }} deptos con venta)</strong></td>
						<td class="ded-table-price" title="Total neto: {{ $fm2($totNet) }}"><strong>{{ $fm2($totNet) }}</strong></td>
						<td class="ded-table-share"><strong>{{ number_format($totShare, 1) }} %</strong></td>
						<td class="ded-table-num" title="Total anterior: {{ $fm2($totPrev) }}"><strong>{{ $fm2($totPrev) }}</strong></td>
						<td class="text-right ded-table-num {{ $totDeltaAbs >= 0 ? 'srep-val-pos' : 'srep-val-neg' }}" title="Variación neta: {{ $fSignedM2($totDeltaAbs) }}"><strong>{{ $fSignedM2($totDeltaAbs) }}</strong></td>
						<td class="text-right">{!! $badge($totDeltaPct) !!}</td>
						<td class="ded-table-num"><strong>{{ number_format($DP['total_units'] ?? 0, 0) }}</strong></td>
						<td class="ded-table-num" title="Tickets únicos del período (un ticket con varios departamentos cuenta una vez en el total consolidado)"><strong>{{ number_format($DP['total_tickets'] ?? 0) }}*</strong></td>
						<td class="ded-table-num" title="Meta total consolidada: {{ $fm2($deptGoal ?: null) }}"><strong>{{ $fm2($deptGoal ?: null) }}</strong></td>
						<td class="ded-table-share"><strong>{{ $deptGoal ? number_format($deptNetWithGoal / $deptGoal * 100, 1) . ' %' : $na }}</strong></td>
					</tr>
				</tfoot>
			</table>
		</div>
		<p class="srep-note">* Tickets por fila = tickets que contienen productos de ese departamento; en el total consolidado se muestran los tickets únicos del período ({{ number_format($DP['total_tickets'] ?? 0) }}) sin duplicar tickets compartidos.</p>
	</article>

	{{-- Configuración: códigos, nombres y valores de producto propios de cada empresa --}}
	<details class="ded-card mb-3 srep-config" id="srepDeptConfig" data-testid="srep-dept-config">
		<summary class="ded-card-header" style="cursor:pointer;">
			<h2 class="ded-card-title">Configurar departamentos</h2>
			<span class="srep-note mb-0">Códigos, nombres y valores de producto de su empresa</span>
		</summary>
		<p class="srep-note">Cada empresa define sus códigos (p. ej. 90 Congelados, 91 Lácteos); no hay códigos universales. Un valor de producto (campo «Departamento» del producto) solo puede pertenecer a un departamento. Lo que no se asigne suma en «Otros».</p>
		<div class="srep-scroll">
			<table class="ded-table-top5" id="srepDeptConfigTable">
				<thead><tr><th>Orden</th><th>Código</th><th>Nombre</th><th>Color</th><th>Valores de producto (separados por coma)</th><th></th></tr></thead>
				<tbody>
				@if(!empty($DP['config']))
					@foreach($DP['config'] as $idx => $cfg)
					<tr>
						<td>{{ $loop->iteration }}</td>
						<td><span class="badge badge-secondary">{{ $cfg['code'] ?? '—' }}</span></td>
						<td class="font-weight-600">{{ $cfg['name'] ?? '' }}</td>
						<td><span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:{{ $cfg['color'] ?? '#94a3b8' }};"></span> {{ $cfg['color'] ?? '' }}</td>
						<td><small class="text-muted">{{ is_array($cfg['values'] ?? null) ? implode(', ', $cfg['values']) : ($cfg['values'] ?? '') }}</small></td>
						<td></td>
					</tr>
					@endforeach
				@endif
				</tbody>
			</table>
		</div>
		<p class="srep-note" id="srepDeptConfigValues"></p>
		<div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
			<button type="button" class="ded-select-btn" id="srepDeptConfigAdd"><i data-lucide="plus"></i><span>Agregar departamento</span></button>
			<button type="button" class="ded-select-btn is-active" id="srepDeptConfigSave"><i data-lucide="save"></i><span>Guardar y recalcular</span></button>
			<span class="srep-note mb-0" id="srepDeptConfigMsg" role="status" aria-live="polite"></span>
		</div>
		<script type="application/json" id="srepDeptConfigData">{!! json_encode($DP['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
	</details>
	@endif

	{{-- ================================================================ 3. PRODUCTOS DE VENTA --}}
	@if($section === 'products')
	@php
		$PR = $R['products'];
		$tn = $PR['top_net'][0] ?? null;
		$tu = $PR['top_units'][0] ?? null;
		$g1 = $PR['gainers'][0] ?? null;
		$top10share = array_sum(array_map(fn($r) => $r['share_pct'] ?? 0, $PR['top_net']));
	@endphp
	<section class="srep-kpis" aria-label="Indicadores de productos">
		{!! $kpi('badge-dollar-sign', 'Mayor facturación', $tn['name'] ?? $na, $tn ? $fm($tn['net']) . ' · ' . $fp($tn['share_pct']) : $na, 'Producto con mayores ventas netas', 'prod-net') !!}
		{!! $kpi('package', 'Más unidades vendidas', $tu['name'] ?? $na, $tu ? number_format($tu['units'], 0) . ' unidades' : $na, 'Producto con más unidades vendidas', 'prod-units') !!}
		{!! $kpi('rocket', 'Mayor crecimiento', $g1['name'] ?? $na, $g1 ? '+' . $fm($g1['delta_abs']) . ' · ' . $badge($g1['delta_pct']) : 'Sin productos comparables', 'Mayor aumento de ventas frente al ' . $P['prev_name'], 'prod-growth') !!}
		{!! $kpi('pie-chart', 'Participación top 10', $PR['top_net'] ? number_format($top10share, 1) . ' %' : $na, 'de las ventas netas totales', 'Suma de la participación de los 10 productos con más ventas', 'prod-top10') !!}
		{!! $kpi('barcode', 'Productos vendidos', number_format($PR['count']), 'SKU con venta en el período', 'Productos distintos con ventas', 'prod-count') !!}
	</section>

	@if(!$PR['rows'])
	<article class="ded-card mb-3"><span class="ded-text-muted-italics">Sin información: no hay ventas de productos en el período con estos filtros.</span></article>
	@else
	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Top 10 productos por facturación</h2></header>
			<div class="ded-chart-canvas-box srep-tall"><canvas id="srepProdNet" aria-label="Top 10 productos por ventas netas"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Top 10 productos por unidades</h2></header>
			<div class="ded-chart-canvas-box srep-tall"><canvas id="srepProdUnits" aria-label="Top 10 productos por unidades vendidas"></canvas></div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución comercial · cinco principales</h2></header>
			<div class="ded-chart-canvas-box"><canvas id="srepProdEvo" aria-label="Evolución diaria de los cinco productos principales"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Participación por facturación</h2><span class="srep-note mb-0">20 principales y resto</span></header>
			<div class="srep-treemap" id="srepProdTreemap" role="img" aria-label="Mapa de participación por facturación"></div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Cinco principales por sucursal</h2></header>
			<div class="ded-chart-canvas-box srep-tall"><canvas id="srepProdBranch" aria-label="Ventas de los cinco productos principales por sucursal"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Variación vs. {{ $P['prev_name'] }}</h2><span class="srep-note mb-0">Mayores aumentos y caídas</span></header>
			@if(!$PR['gainers'] && !$PR['losers'])
				<span class="ded-text-muted-italics">Sin información: no hay productos con ventas en ambos períodos.</span>
			@else
				<div class="ded-chart-canvas-box srep-tall"><canvas id="srepProdVar" aria-label="Productos que aumentaron o disminuyeron sus ventas"></canvas></div>
			@endif
		</article>
	</section>

	<article class="ded-card mb-3">
		<header class="ded-card-header">
			<h2 class="ded-card-title">Tabla analítica de productos</h2>
			<div class="ded-card-tools">
				<input type="search" class="ded-select-btn srep-search" data-srep-search="#srepProdTable" placeholder="Buscar producto o SKU" aria-label="Buscar producto o SKU">
			</div>
		</header>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable srep-paged" id="srepProdTable" data-export-name="Productos">
				<thead><tr>
					<th data-sort="num">#</th><th data-sort="text">Producto</th><th data-sort="text">SKU</th><th data-sort="text">Departamento</th><th data-sort="text">Categoría</th>
					<th class="text-right" data-sort="num">Unidades</th><th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Participación</th>
					<th class="text-right" data-sort="num">Variación</th><th data-sort="text">Sucursal líder</th>
				</tr></thead>
				{{-- Filas generadas en el navegador desde srepData al abrir la pestaña (evita ~0,5 MB de HTML en cada carga) --}}
				<tbody data-srep-rows="products"></tbody>
			</table>
		</div>
		@if(count($PR['rows']) > 25)
		<button type="button" class="ded-select-btn mt-2" data-srep-more="#srepProdTable" data-total="{{ count($PR['rows']) }}">Ver los {{ count($PR['rows']) }} productos</button>
		@endif
	</article>
	@endif
	@endif

	{{-- ================================================================ 4. SUCURSALES --}}
	@if($section === 'branches')
	@php
		$BR = $R['branches'];
		$rows = $BR['rows'];
		$data = array_values(array_filter($rows, fn($r) => !$r['no_data']));
		$ranked = array_values(array_filter($rows, fn($r) => $r['rank'] !== null));
		$top = $ranked[0] ?? null;
		$bottom = count($ranked) > 1 ? end($ranked) : null;
		$D = $R['dashboard'];
		$RC = $BR['reconciliation'];
		$G = $BR['growth'];
		$bn = fn($r) => $r['name'] . ($r['code'] ? ' (' . $r['code'] . ')' : '');
		$mg = array_values(array_filter($data, fn($r) => $r['gross_profit'] !== null));
		$mgNet = array_sum(array_column($mg, 'net'));
		$mgProfit = array_sum(array_column($mg, 'gross_profit'));
		$bestMargin = $mg ? array_reduce($mg, fn($a, $r) => $a === null || $r['margin_pct'] > $a['margin_pct'] ? $r : $a) : null;
		$rankBadge = function ($r) {
			if ($r['rank_change'] === null) return $r['rank'] !== null && $r['prev_rank'] === null ? '<span class="ded-text-muted-italics">Nueva</span>' : '';
			if ($r['rank_change'] === 0) return '<span class="ded-text-muted-italics">=</span>';
			return $r['rank_change'] > 0 ? '<span class="ded-kpi-badge-green">▲ ' . $r['rank_change'] . '</span>' : '<span class="ded-pill-red">▼ ' . abs($r['rank_change']) . '</span>';
		};
		$withCoords = array_values(array_filter($data, fn($r) => $r['coords'] !== null));
		$maxNet = $data ? max(array_column($data, 'net')) : 0;
	@endphp
	<section class="srep-kpis" aria-label="Indicadores de sucursales">
		{!! $kpi('crown', 'Sucursal con mayores ventas', $top['name'] ?? $na, $top ? $fm($top['net']) . ' · ' . $fp($top['share_pct']) : $na, 'Primera del ranking por ventas netas (período completo)', 'branch-top') !!}
		{!! $kpi('arrow-down-circle', 'Sucursal con menores ventas', $bottom['name'] ?? ($top['name'] ?? $na), $bottom ? $fm($bottom['net']) . ' · ' . $fp($bottom['share_pct']) : ($top ? 'Única sucursal del ranking' : $na), 'Última del ranking por ventas netas (período completo)', 'branch-bottom') !!}
		{!! $kpi('target', 'Cumplimiento de metas', $fp($D['goal_pct']), $D['goal'] === null ? 'Sin meta para todo el período' : 'Meta ' . $fm($D['goal']), 'Ventas netas ÷ meta del período (sucursales filtradas)', 'branch-goal') !!}
		{!! $kpi('receipt', 'Ticket promedio', $fm2($D['avg_ticket']), number_format($D['tickets']) . ' transacciones', 'Ventas netas ÷ transacciones', 'branch-ticket') !!}
		{!! $kpi('trending-up', 'Crecimiento comercial', $D['growth_pct'] === null ? $na : (($D['growth_pct'] >= 0 ? '+' : '') . number_format($D['growth_pct'], 1) . ' %'), 'vs. ' . $h($P['prev_name']), '(Ventas actuales − anteriores) ÷ anteriores × 100', 'branch-growth') !!}
		{!! $kpi('coins', 'Margen bruto de la red', $mgNet > 0 ? number_format($mgProfit / $mgNet * 100, 1) . ' %' : $na, $bestMargin ? 'Mayor margen: ' . $h($bestMargin['name']) . ' (' . $fp($bestMargin['margin_pct']) . ')' : $na, 'Utilidad bruta ÷ ventas netas de las sucursales con costo confiable', 'branch-margin') !!}
	</section>

	{{-- Conciliación y cobertura: Σ sucursales = total comercial; sin datos e incompletas se explican, no se muestran en cero --}}
	<article class="ded-card mb-3" data-testid="srep-branch-reconciliation">
		<p class="srep-note mb-0">
			<i data-lucide="{{ $RC['ok'] ? 'check-circle-2' : 'alert-triangle' }}"></i>
			<span>Σ sucursales</span> <strong>{{ $fm2($RC['sum']) }}</strong>
			@if($RC['ok'])
				<span>= total comercial consolidado</span> <strong>{{ $fm2($RC['total']) }}</strong>.
			@else
				<span>frente a total comercial consolidado</span> <strong>{{ $fm2($RC['total']) }}</strong> (<span>diferencia</span> {{ $fm2($RC['diff']) }}).
			@endif
			<span>Ranking solo con sucursales que operaron al menos el 90 % de los</span> {{ $BR['network_days'] }} <span>días con ventas de la red.</span>
		</p>
		@if($BR['incomplete'])
		<p class="srep-note mb-0" data-testid="srep-branch-incomplete"><i data-lucide="calendar-x"></i> <span>Período incompleto (sin posición en el ranking):</span>
			<strong>{{ implode(', ', array_map(fn($x) => $x['name'] . ' · ' . $x['active_days'] . ' de ' . $BR['network_days'] . ' días', $BR['incomplete'])) }}</strong>.</p>
		@endif
		@if($BR['no_data'])
		<p class="srep-note mb-0" data-testid="srep-branch-no-data"><i data-lucide="circle-slash"></i> <span>Sin datos de venta en el período:</span> <strong>{{ implode(', ', $BR['no_data']) }}</strong>. <span>Aparecen en la tabla como «Sin datos», no en cero ni en los gráficos.</span></p>
		@endif
		@if($BR['cut_time'])
		<p class="srep-note mb-0"><i data-lucide="clock"></i> <span>El período termina hoy: cada comparación corta su último día a las</span> <strong>{{ $BR['cut_time'] }}</strong> <span>(mismo tramo horario).</span></p>
		@endif
	</article>

	@if(!$data)
	<article class="ded-card mb-3"><span class="ded-text-muted-italics">Sin información: no hay ventas en el período con estos filtros.</span></article>
	@else
	<article class="ded-card mb-3 srep-select-card">
		<header class="ded-card-header">
			<h2 class="ded-card-title">Sucursal seleccionada</h2>
			<select class="ded-select-btn srep-select" id="srepBranchSelect" aria-label="Seleccionar sucursal">
				<option value="">Toda la red</option>
				@foreach($data as $r)<option value="{{ $r['id'] }}">{{ $r['rank'] ? $r['rank'] . '. ' : '' }}{{ $bn($r) }}</option>@endforeach
			</select>
		</header>
		<div class="srep-detail" id="srepBranchDetail" aria-live="polite"></div>
	</article>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Ranking de sucursales por ventas</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($data) * 26 + 30) }}px;"><canvas id="srepBranchBars" aria-label="Ranking de sucursales por ventas netas"></canvas></div>
			@if($BR['incomplete'])<p class="srep-note"><span>En gris: período incompleto, sin posición.</span></p>@endif
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Participación en las ventas consolidadas</h2></header>
			<div class="ded-dept-donut-layout">
				<div class="ded-donut-wrap">
					<canvas id="srepBranchDonut" aria-label="Participación de cada sucursal en las ventas consolidadas"></canvas>
					<div class="ded-donut-center-info">
						<span class="ded-donut-center-amount">{{ $fm($RC['total']) }}</span>
						<span class="ded-donut-center-label">Ventas netas</span>
					</div>
				</div>
				<div class="ded-dept-list">
					@foreach($data as $i => $r)
					<div class="ded-dept-row" data-srep-branch="{{ $r['id'] }}" title="{{ $r['name'] }}: {{ $fp($r['share_pct']) }}">
						<div class="ded-dept-name-box"><span class="ded-dept-dot" data-srep-color="{{ $i }}"></span><span>{{ $r['name'] }}</span></div>
						<span class="ded-dept-pct">{{ $fp($r['share_pct']) }}</span>
					</div>
					@endforeach
				</div>
			</div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Actual vs. {{ $P['prev_name'] }}</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Actual</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#94a3b8;"></span><span>Anterior</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepBranchVsPrev" aria-label="Ventas por sucursal frente al período anterior"></canvas></div>
			<p class="srep-note"><span>Anterior:</span> {{ $fd($P['prev_from']) }} – {{ $fd($P['prev_to']) }} ({{ $P['days'] }} <span>días</span>{{ $BR['cut_time'] ? ', último día hasta las ' . $BR['cut_time'] : '' }}).</p>
		</article>
		<article class="ded-card" data-testid="srep-branch-growth">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Crecimiento comercial por sucursal</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Horizonte de crecimiento">
					@foreach($G as $gk => $gw)
					<button type="button" class="ded-select-btn{{ $loop->first ? ' is-active' : '' }}" data-srep-bgrowth="{{ $gk }}" title="{{ $gw['desc'] }}">{{ $gw['label'] }}</button>
					@endforeach
				</div>
			</header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($data) * 26 + 30) }}px;"><canvas id="srepBranchGrowth" aria-label="Crecimiento de ventas por sucursal"></canvas></div>
			@foreach($G as $gk => $gw)
			<p class="srep-note" data-srep-bgrowth-note="{{ $gk }}" @if(!$loop->first) hidden @endif>
				<span>{{ $gw['desc'] }}:</span> <strong>{{ $fd($gw['from']) }} – {{ $fd($gw['to']) }}</strong> vs. {{ $fd($gw['prev_from']) }} – {{ $fd($gw['prev_to']) }}
				({{ $gw['days'] }} vs. {{ $gw['prev_days'] }} días{{ $gw['cut_time'] ? ', último día hasta las ' . $gw['cut_time'] : '' }}) · <span>Red</span> {!! $badge($gw['delta_pct']) !!}
			</p>
			@endforeach
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-branch-ticket">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Ticket promedio y transacciones</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#cbd5e1;"></span><span>Transacciones</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Ticket promedio</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepBranchTicket" aria-label="Ticket promedio y transacciones por sucursal"></canvas></div>
		</article>
		<article class="ded-card" data-testid="srep-branch-profit">
			<header class="ded-card-header"><h2 class="ded-card-title">Margen bruto por tienda</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($data) * 26 + 30) }}px;"><canvas id="srepBranchMargin" aria-label="Margen bruto de cada sucursal"></canvas></div>
			<p class="srep-note"><span>Margen = (ventas netas − costo) ÷ ventas netas. Sucursales con costo no confiable quedan fuera.</span></p>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-branch-hourly">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Ventas por hora</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Medida">
					<button type="button" class="ded-select-btn is-active" data-srep-bhour="net">Ventas</button>
					<button type="button" class="ded-select-btn" data-srep-bhour="share">% del día</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepBranchHourly" aria-label="Ventas por hora de cada sucursal"></canvas></div>
			<p class="srep-note" id="srepBranchHourNote"><span>Cinco principales.</span> <span>«% del día» compara la forma de la curva de cada tienda.</span>@if($BR['hourly']['archive_spread']) <span>Los días del archivo histórico se reparten con la curva por hora de referencia.</span>@endif</p>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución comercial del período</h2><span class="srep-note mb-0" id="srepBranchEvoNote">Cinco principales</span></header>
			<div class="ded-chart-canvas-box"><canvas id="srepBranchEvo" aria-label="Evolución diaria de las sucursales"></canvas></div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-branch-history">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución histórica mensual</h2><span class="srep-note mb-0" id="srepBranchHistNote">Cinco principales</span></header>
			@if(count($BR['history']['months']) > 1)
			<div class="ded-chart-canvas-box"><canvas id="srepBranchHistory" aria-label="Ventas netas mensuales de cada sucursal"></canvas></div>
			<p class="srep-note"><span>Ventas netas por mes, hasta 36 meses.</span>@if($BR['history']['partial_last']) <span>El último mes está en curso (hasta el día</span> {{ $BR['history']['partial_last'] }}).@endif <span>Meses sin ventas de una tienda quedan vacíos.</span></p>
			@else
			<span class="ded-text-muted-italics">Sin información: se necesita más de un mes de ventas.</span>
			@endif
		</article>
		<article class="ded-card" data-testid="srep-branch-map">
			<header class="ded-card-header"><h2 class="ded-card-title">Mapa comercial</h2>@if($withCoords)<span class="srep-note mb-0">Tamaño = ventas netas · color = cumplimiento</span>@endif</header>
			@if($withCoords)
			@php
				$lats = array_map(fn($r) => $r['coords']['lat'], $withCoords);
				$lngs = array_map(fn($r) => $r['coords']['lng'], $withCoords);
				$latR = max($lats) - min($lats);
				$lngR = max($lngs) - min($lngs);
				$span = max($latR, $lngR, 0.0001);
			@endphp
			<div class="branch-map-wrapper">
				<div class="branch-map-canvas srep-map" id="srepBranchMap">
					@foreach($withCoords as $r)
						@php
							$x = 50 + ($r['coords']['lng'] - (min($lngs) + $lngR / 2)) / $span * 80;
							$y = 50 - ($r['coords']['lat'] - (min($lats) + $latR / 2)) / $span * 80;
							$size = $maxNet > 0 ? round(12 + 22 * sqrt(max(0, $r['net']) / $maxNet)) : 12;
							$st = $r['goal_pct'] === null ? ($r['delta_pct'] === null || $r['delta_pct'] >= 0 ? 'operativa' : 'atencion') : ($r['goal_pct'] >= 100 ? 'operativa' : ($r['goal_pct'] >= 85 ? 'atencion' : 'critico'));
						@endphp
						<div class="branch-map-node node-{{ $st }}" style="left: {{ round($x, 1) }}%; top: {{ round($y, 1) }}%;" data-srep-branch="{{ $r['id'] }}"
							title="{{ $r['name'] }} · {{ $fm($r['net']) }}{{ $r['goal_pct'] !== null ? ' · ' . $fp($r['goal_pct']) . ' de la meta' : '' }}">
							<div class="branch-node-dot" style="width: {{ $size }}px; height: {{ $size }}px;"></div>
							<span class="branch-node-label">{{ $r['name'] }} · {{ $fm($r['net']) }}</span>
						</div>
					@endforeach
				</div>
				<div class="ded-legend-inline srep-map-legend">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#16a34a;"></span><span>Meta cumplida o en crecimiento</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#f59e0b;"></span><span>85–99 % de la meta o en caída</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#ef4444;"></span><span>Menos del 85 %</span></span>
				</div>
				@if(count($withCoords) < count($data))<p class="srep-note"><span>Sin coordenadas válidas:</span> {{ implode(', ', array_map(fn($r) => $r['name'], array_filter($data, fn($r) => $r['coords'] === null))) }}.</p>@endif
			</div>
			@else
			<p class="ded-text-muted-italics mb-1" data-testid="srep-branch-map-empty">Sin información: ninguna sucursal tiene latitud y longitud válidas.</p>
			<p class="srep-note mb-0"><span>Registre las coordenadas de cada tienda en</span> <a href="{{ $U('/branches') }}">Sucursales</a> <span>para ver el mapa comercial. No se usan posiciones ilustrativas.</span></p>
			@endif
		</article>
	</section>

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Mapa de calor · sucursal × {{ $BR['heat_by_day'] ? 'día' : 'día de la semana' }}</h2><span class="srep-note mb-0">Ventas netas</span></header>
		@php
			$hmax = 0; foreach ($BR['heat'] as $hr) { $hmax = max($hmax, max($hr ?: [0])); }
			$dowNames = ['0' => 'Dom', '1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb'];
		@endphp
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-heat srep-heat-compact" data-export-name="Sucursal x dia">
				<thead><tr><th>Sucursal</th>@foreach($BR['heat_cols'] as $col)<th class="text-right" data-export-value="{{ $col }}">{{ $BR['heat_by_day'] ? date('d/m', strtotime($col)) : $dowNames[$col] }}</th>@endforeach</tr></thead>
				<tbody>
				@foreach($data as $r)
					<tr data-srep-branch-row="{{ $r['id'] }}">
						<td>{{ $r['name'] }}</td>
						@foreach($BR['heat_cols'] as $col)
							@php $v = $BR['heat'][$r['id']][$col] ?? 0; $a = $hmax > 0 ? round($v / $hmax, 2) : 0; @endphp
							<td class="text-right srep-heat-cell" style="--srep-a: {{ $a }};" data-export-value="{{ round($v, 2) }}" title="{{ $r['name'] }} · {{ $BR['heat_by_day'] ? $fd($col) : $dowNames[$col] }}: {{ $fm2($v) }}">{{ $v > 0 ? number_format($v / 1000, 1) . 'k' : '—' }}</td>
						@endforeach
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
	</article>
	@endif

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Tabla comparativa de sucursales</h2></header>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable srep-branch-table" data-export-name="Sucursales" data-testid="srep-branch-table">
				<thead><tr>
					<th data-sort="num">Pos.</th><th data-sort="text">Código</th><th data-sort="text">Sucursal</th><th class="text-right" data-sort="num">Ventas netas</th>
					<th class="text-right" data-sort="num">Participación</th><th class="text-right" data-sort="num">Anterior</th><th class="text-right" data-sort="num">Variación</th>
					<th class="text-right" data-sort="num">Semanal</th><th class="text-right" data-sort="num">Mensual</th><th class="text-right" data-sort="num">Anual</th>
					<th class="text-right" data-sort="num">Meta</th><th class="text-right" data-sort="num">Cumplimiento</th><th class="text-right" data-sort="num">Transacciones</th>
					<th class="text-right" data-sort="num">Ticket promedio</th><th class="text-right" data-sort="num">Var. ticket</th><th class="text-right" data-sort="num">Utilidad bruta</th>
					<th class="text-right" data-sort="num">Margen</th><th class="text-right" data-sort="num">Días operados</th>
				</tr></thead>
				<tbody>
				@foreach($rows as $r)
					<tr data-srep-branch-row="{{ $r['id'] }}" @if($r['no_data']) class="srep-muted" @endif>
						<td data-v="{{ $r['rank'] ?? 9999 }}">@if($r['rank'])<span class="ded-rank-circle">{{ $r['rank'] }}</span> {!! $rankBadge($r) !!}@else<span class="ded-text-muted-italics">{{ $r['no_data'] ? '—' : 'Incompleto' }}</span>@endif</td>
						<td>{{ $r['code'] ?? '—' }}</td>
						<td><div class="ded-prod-name">{{ $r['name'] }}</div>@if($r['format'])<small class="text-muted">{{ $r['format'] }}</small>@endif</td>
						@if($r['no_data'])
						<td class="ded-table-price" data-v="-1"><span class="ded-text-muted-italics">Sin datos</span></td>
						<td class="ded-table-share" data-v="-1">—</td>
						@else
						<td class="ded-table-price" data-v="{{ $r['net'] }}">{{ $fm2($r['net']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['share_pct'] ?? -1 }}">{{ $fp($r['share_pct']) }}</td>
						@endif
						<td class="ded-table-num" data-v="{{ $r['prev_net'] }}">{{ $r['prev_net'] > 0 ? $fm2($r['prev_net']) : '—' }}</td>
						<td class="text-right" data-v="{{ $r['delta_pct'] ?? -99999 }}">{!! $r['no_data'] ? '—' : $badge($r['delta_pct']) !!}</td>
						@foreach(['week', 'month', 'year'] as $gk)
						<td class="text-right" data-v="{{ $r['growth'][$gk] ?? -99999 }}">{!! $r['no_data'] ? '—' : $badge($r['growth'][$gk]) !!}</td>
						@endforeach
						<td class="ded-table-num" data-v="{{ $r['goal'] ?? -1 }}">{{ $fm($r['goal']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['goal_pct'] ?? -1 }}">{{ $r['no_data'] ? '—' : $fp($r['goal_pct']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['tickets'] ?? -1 }}">{{ $r['tickets'] === null ? '—' : number_format($r['tickets']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['avg_ticket'] ?? -1 }}">{{ $r['no_data'] ? '—' : $fm2($r['avg_ticket']) }}</td>
						<td class="text-right" data-v="{{ $r['avg_ticket_delta'] ?? -99999 }}">{!! $r['no_data'] ? '—' : $badge($r['avg_ticket_delta']) !!}</td>
						<td class="ded-table-num" data-v="{{ $r['gross_profit'] ?? -1 }}">{{ $r['no_data'] ? '—' : $fm2($r['gross_profit']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['margin_pct'] ?? -1 }}">{{ $r['no_data'] ? '—' : $fp($r['margin_pct']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['active_days'] }}">{{ $r['active_days'] }} / {{ $r['network_days'] }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
		<p class="srep-note"><span>Semanal: últimos 7 días vs. 7 anteriores · Mensual: mes a la fecha vs. mismos días del mes anterior · Anual: período vs. mismo período del año anterior.</span></p>
	</article>
	@endif

	{{-- ================================================================ 5. HISTORIAL DE VENTAS --}}
	@if($section === 'history')
	@php
		$H = $R['history'];
		$mNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
		$monthLabel = fn($ym) => ($mNames[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 0, 4);
		$weLast = $H['weekend_last'];
		$weLy = $H['weekend_ly'];
		$A = $salesHistoryAudit ?? null;
		$W = $H['wow'];
		$fdt = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
		$dims = ['branch' => ['Sucursal', $H['by_branch']], 'dept' => ['Departamento', $H['by_dept']], 'sku' => ['SKU', $H['by_sku']], 'channel' => ['Canal', $H['by_channel']]];
		$fieldNames = ['created_timestamp' => 'fecha de venta', 'status' => 'estado', 'total_amount' => 'total', 'tax_amount' => 'IVA', 'subtotal' => 'subtotal', 'discount_amount' => 'descuento',
			'amount' => 'cantidad', 'unit_price' => 'precio', 'branch_id' => 'sucursal', 'payment_method' => 'medio de pago', 'notes' => 'notas', 'total_cost' => 'costo', 'unit_cost' => 'costo unitario'];
	@endphp
	<section class="srep-kpis" aria-label="Indicadores históricos">
		{!! $kpi('wallet', 'Ventas del período', $fm($H['net']), number_format($H['tickets']) . ' transacciones', 'Ventas netas del período seleccionado', 'hist-net') !!}
		{!! $kpi('calendar-clock', 'Crecimiento interanual', $H['yoy']['pct'] === null ? $na : (($H['yoy']['pct'] >= 0 ? '+' : '') . number_format($H['yoy']['pct'], 1) . ' %'), $H['yoy']['net'] === null ? 'Sin ventas registradas ' . $fd($H['yoy']['from']) . ' – ' . $fd($H['yoy']['to']) : 'Año anterior: ' . $fm($H['yoy']['net']), 'Mismo período del año anterior', 'hist-yoy') !!}
		{!! $kpi('calendar-range', 'Variación mensual', $H['mom']['pct'] === null ? $na : (($H['mom']['pct'] >= 0 ? '+' : '') . number_format($H['mom']['pct'], 1) . ' %'), $h($H['mom']['label']) . ' vs. ' . $h($H['mom']['prev_label']), 'Mes en curso frente al mismo tramo del mes anterior', 'hist-mom') !!}
		{!! $kpi('star', 'Mejor día de ventas', $H['best_day'] ? $fd($H['best_day']['date']) : $na, $H['best_day'] ? $fm($H['best_day']['net']) : $na, 'Día con mayores ventas netas en el período', 'hist-best-day') !!}
		{!! $kpi('calendar-check', 'Mejor mes de ventas', $H['best_month'] ? $monthLabel($H['best_month']['month']) : $na, $H['best_month'] ? $fm($H['best_month']['net']) : $na, 'Mes con mayores ventas netas en todo el historial', 'hist-best-month') !!}
		{!! $kpi('bar-chart-3', 'Promedio diario', $fm($H['daily_avg']), 'por día del período', 'Ventas netas ÷ días del período', 'hist-avg') !!}
		@if($A)
		{!! $kpi('undo-2', 'Devoluciones reembolsadas', $fm($A['refunds']['total']), number_format($A['refunds']['count']) . ' devoluciones de clientes', 'Reembolsos y créditos en tienda completados, en la fecha de la devolución', 'hist-refunds') !!}
		{!! $kpi('receipt', 'Venta neta tras devoluciones', $fm(round($H['net'] - $A['refunds']['total'], 2)), 'ventas netas − devoluciones', 'Ventas netas del período menos las devoluciones reembolsadas del período', 'hist-net-after-returns') !!}
		@endif
	</section>

	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Línea temporal de ventas</h2>
				<div class="ded-card-tools srep-seg" role="group" aria-label="Granularidad">
					<button type="button" class="ded-select-btn is-active" data-srep-grain="day">Diaria</button>
					<button type="button" class="ded-select-btn" data-srep-grain="week">Semanal</button>
					<button type="button" class="ded-select-btn" data-srep-grain="month">Mensual</button>
					<button type="button" class="ded-select-btn" data-srep-grain="year">Anual</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistTimeline" aria-label="Línea temporal de ventas"></canvas></div>
			<p class="srep-note" id="srepHistGrainNote"><span>Diaria y semanal: período seleccionado. Mensual y anual: todo el historial hasta el</span> <span>{{ $fd($P['to']) }}</span></p>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Comparación interanual</h2></header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistYears" aria-label="Ventas anuales por año"></canvas></div>
			<p class="srep-note">
				@foreach($H['years'] as $y)
					{{ $y['year'] }}: {{ $y['has_data'] ? $fm($y['net']) : $na }}@if(!$loop->last) · @endif
				@endforeach
			</p>
		</article>
	</section>

	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Calendario de calor de ventas</h2><span class="srep-note mb-0">53 semanas hasta el {{ $fd($P['to']) }}</span></header>
			<div class="srep-scroll"><div class="srep-calendar" id="srepHistCalendar" role="img" aria-label="Calendario de calor de ventas diarias"></div></div>
			<div class="srep-cal-legend"><span>Menos</span><i style="--srep-a:0"></i><i style="--srep-a:.25"></i><i style="--srep-a:.5"></i><i style="--srep-a:.75"></i><i style="--srep-a:1"></i><span>Más</span></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Ventas por día de la semana</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Total</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot"></span><span>Promedio por día</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistWeekday" aria-label="Ventas por día de la semana"></canvas></div>
		</article>
	</section>

	<section class="ded-row-3">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Años superpuestos por mes</h2></header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistOverlay" aria-label="Ventas mensuales superpuestas por año"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Comparación entre fines de semana</h2><span class="srep-note mb-0">Sábado + domingo</span></header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistWeekends" aria-label="Ventas de los últimos fines de semana"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Mismo fin de semana del año anterior</h2></header>
			<div class="ded-comp-col">
				<div class="ded-comp-row"><span class="ded-comp-label">Último fin de semana<br><small>{{ $fd($weLast['sat']) }} – {{ $fd($weLast['sun']) }}</small></span><span class="ded-comp-val">{{ $fm($weLast['net']) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Mismo fin de semana año anterior<br><small>{{ $fd($weLy['sat']) }} – {{ $fd($weLy['sun']) }}</small></span>
					@if($weLy['net'] === null)<span class="ded-text-muted-italics">{{ $na }}</span>@else<span class="ded-comp-val">{{ $fm($weLy['net']) }}</span>@endif
				</div>
				<div class="ded-comp-row"><span class="ded-comp-label">Variación</span>{!! $badge($H['weekend_ly_pct']) !!}</div>
			</div>
			<p class="srep-note">El mismo fin de semana es el de 52 semanas antes (mismos días de la semana).</p>
		</article>
	</section>

	<section class="ded-row-2">
		<article class="ded-card" data-testid="srep-hist-cumulative">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Curva acumulada de ventas</h2>
				<span class="srep-note mb-0">Día 1 a {{ count($H['cumulative']['labels']) }} del período</span>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistCumulative" aria-label="Ventas acumuladas día a día frente al período de comparación y al año anterior"></canvas></div>
			<p class="srep-note">Período actual vs. {{ $H['cumulative']['prev_label'] }}{{ $H['cumulative']['ly'] === null ? ' · año anterior: ' . $na : ' · mismo período del año anterior' }}.</p>
		</article>
		<article class="ded-card" data-testid="srep-hist-wow">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Semana contra semana</h2>
				{!! $badge($W['pct']) !!}
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepHistWow" aria-label="Ventas por día de la semana actual frente a la semana anterior"></canvas></div>
			<p class="srep-note">{{ $fd($W['from']) }} – {{ $fd($W['to']) }}: <strong>{{ $fm($W['net']) }}</strong> ({{ number_format($W['tickets']) }} transacciones) · mismos días de la semana anterior {{ $fd($W['prev_from']) }} – {{ $fd($W['prev_to']) }}: <strong>{{ $fm($W['prev_net']) }}</strong> ({{ number_format($W['prev_tickets']) }}).</p>
		</article>
	</section>

	<article class="ded-card srep-block" data-testid="srep-hist-dims">
		<header class="ded-card-header">
			<div>
				<h2 class="ded-card-title">Histórico mensual por dimensión</h2>
				<p class="srep-note mb-0">13 meses hasta el {{ $fd($P['to']) }} · ventas netas por mes</p>
			</div>
			<div class="ded-card-tools srep-seg" role="group" aria-label="Dimensión">
				@foreach($dims as $dk => $dv)
				<button type="button" class="ded-select-btn @if($loop->first) is-active @endif" data-srep-hdim="{{ $dk }}">{{ $dv[0] }}</button>
				@endforeach
			</div>
		</header>
		<div class="ded-chart-canvas-box"><canvas id="srepHistDim" aria-label="Ventas mensuales por dimensión"></canvas></div>
		@foreach($dims as $dk => $dv)
		<div class="srep-scroll" data-srep-hdim-table="{{ $dk }}" @if(!$loop->first) hidden @endif>
			@if(!$dv[1])
			<p class="ded-text-muted-italics mb-0">{{ $na }}</p>
			@else
			<table class="ded-table-top5" data-export-name="Histórico por {{ strtolower($dv[0]) }}">
				<thead><tr><th>{{ $dv[0] }}</th>@foreach($H['months'] as $m)<th class="text-right" data-export-value="{{ $m }}">{{ $monthLabel($m) }}</th>@endforeach<th class="text-right">Total</th></tr></thead>
				<tbody>
				@foreach($dv[1] as $r)
					<tr><td>{{ $r['name'] }}</td>@foreach($r['months'] as $v)<td class="text-right" data-export-value="{{ $v ?? '' }}">{{ $v === null ? '—' : $fm($v) }}</td>@endforeach<td class="text-right font-weight-bold" data-export-value="{{ $r['total'] }}">{{ $fm($r['total']) }}</td></tr>
				@endforeach
				</tbody>
			</table>
			@endif
		</div>
		@endforeach
		<p class="srep-note">SKU: los 10 productos más vendidos del período{{ $H['sku_from'] > $H['months'][0] . '-01' ? '; el archivo histórico no tiene detalle por producto, por eso los meses anteriores al ' . $fd($H['sku_from']) . ' quedan vacíos' : '' }}. Canal: pedido a domicilio o para recoger vinculado al ticket; el resto es venta en caja.</p>
	</article>

	@if($A)
	@php
		$cl = $A['closures'];
		$changedClosures = count(array_filter($cl['rows'], fn($c) => !$c['intact']));
	@endphp
	<section class="srep-audit" id="srepHistAudit" data-testid="srep-hist-audit" aria-label="Auditoría de la historia comercial">
		<header class="srep-audit-head">
			<h2 class="ded-card-title">Auditoría de la historia comercial</h2>
			<p class="srep-note mb-0">Cada cambio a una venta ya registrada queda con usuario, fecha, antes y después. Las correcciones exigen motivo y autorización.</p>
		</header>
		<section class="srep-kpis" aria-label="Indicadores de auditoría">
			{!! $kpi('copy', 'Tickets duplicados', number_format(count($A['duplicates'])), count($A['duplicates']) ? 'exceso ' . $fm($A['duplicates_excess']) . ' en el período' : 'sin duplicados en el período', 'Misma sucursal, fecha y hora, total y líneas', 'hist-dups') !!}
			{!! $kpi('clock-alert', 'Registro posterior a la venta', number_format($A['late_count']), 'se cuentan en su fecha de venta', 'Líneas grabadas en un día posterior a la fecha de venta', 'hist-late') !!}
			{!! $kpi('history', 'Cambios sin corrección registrada', number_format($A['changes_unexplained']), 'modificaciones a ventas de días anteriores', 'Cambios a ventas de días anteriores sin corrección autorizada ni cancelación de caja', 'hist-unexplained') !!}
			{!! $kpi('lock', 'Cierres comerciales', number_format(count($cl['rows'])), $changedClosures ? $changedClosures . ' con cifras cambiadas después del cierre' : (count($cl['pending']) . ' períodos terminados sin cierre'), 'Semanas y meses cerrados con la foto de sus cifras', 'hist-closures') !!}
		</section>

		<section class="ded-row-2">
			<article class="ded-card" data-testid="srep-hist-duplicates">
				<header class="ded-card-header"><h2 class="ded-card-title">Tickets duplicados</h2><span class="srep-note mb-0">{{ $A['duplicates_all_time'] }} grupos en todo el historial</span></header>
				@if(!$A['duplicates'])
				<p class="ded-text-muted-italics mb-0">Sin duplicados en el período.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5 srep-sortable srep-paged" id="srepHistDups" data-export-name="Duplicados">
					<thead><tr><th>Fecha y hora</th><th>Sucursal</th><th>Ticket original</th><th>Copia</th><th class="text-right">Venta neta</th><th></th></tr></thead>
					<tbody>
					@foreach($A['duplicates'] as $g)
						@foreach($g['copies'] as $cp)
						<tr><td>{{ $fdt($g['datetime']) }}</td><td>{{ $g['branch'] }}</td><td>{{ $g['keep']['number'] }}</td><td>{{ $cp['number'] }}</td><td class="text-right" data-export-value="{{ $g['net'] }}">{{ $fm2($g['net']) }}</td>
							<td><button type="button" class="ded-select-btn" data-hist-void="{{ $cp['id'] }}" data-ticket="{{ $cp['number'] }}" data-keep="{{ $g['keep']['number'] }}">Anular copia</button></td></tr>
						@endforeach
					@endforeach
					</tbody>
				</table></div>
				@php $nDup = array_sum(array_map(fn($g) => count($g['copies']), $A['duplicates'])); @endphp
				@if($nDup > 25)<button type="button" class="ded-select-btn mt-2" data-srep-more="#srepHistDups">Ver los {{ $nDup }} duplicados</button>@endif
				@endif
				<p class="srep-note">Se conserva el primer ticket; anular la copia reintegra su inventario y deja la corrección registrada.</p>
			</article>
			<article class="ded-card" data-testid="srep-hist-late">
				<header class="ded-card-header"><h2 class="ded-card-title">Fecha de venta vs. fecha de registro</h2></header>
				@if(!$A['late'])
				<p class="ded-text-muted-italics mb-0">Todas las ventas del período se registraron el mismo día.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Registro posterior">
					<thead><tr><th>Ticket</th><th>Sucursal</th><th>Fecha de venta</th><th>Registrado</th><th class="text-right">Días</th><th class="text-right">Venta neta</th><th></th></tr></thead>
					<tbody>
					@foreach($A['late'] as $r)
						<tr><td>{{ $r['number'] }}</td><td>{{ $r['branch'] }}</td><td>{{ $fdt($r['sold_at']) }}</td><td>{{ $fdt($r['recorded_at']) }}</td><td class="text-right">{{ $r['lag_days'] }}</td><td class="text-right" data-export-value="{{ $r['net'] }}">{{ $fm2($r['net']) }}</td>
							<td><button type="button" class="ded-select-btn" data-hist-date="{{ $r['id'] }}" data-ticket="{{ $r['number'] }}" data-sold="{{ substr($r['sold_at'], 0, 16) }}" data-recorded="{{ substr($r['recorded_at'], 0, 16) }}">Corregir fecha</button></td></tr>
					@endforeach
					</tbody>
				</table></div>
				@endif
				<p class="srep-note">Los informes cuentan cada venta en su fecha de venta, no en la de registro. Si la fecha de venta está mal, corríjala con motivo.</p>
			</article>
		</section>

		<section class="ded-row-2">
			<article class="ded-card" data-testid="srep-hist-refunds">
				<header class="ded-card-header"><h2 class="ded-card-title">Devoluciones de clientes</h2><span class="srep-note mb-0">{{ $fm($A['refunds']['total']) }}</span></header>
				@if(!$A['refunds']['rows'])
				<p class="ded-text-muted-italics mb-0">Sin devoluciones reembolsadas en el período.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Devoluciones">
					<thead><tr><th>Fecha</th><th>Devolución</th><th>Ticket</th><th>Producto</th><th>Resolución</th><th class="text-right">Importe</th></tr></thead>
					<tbody>
					@foreach($A['refunds']['rows'] as $r)
						<tr><td>{{ $fd($r['day']) }}</td><td>{{ $r['code'] }}</td><td>{{ $r['ticket'] ?: '—' }}@if($r['other_period'])<br><small class="text-muted">venta del {{ $fd($r['sold_at']) }}</small>@endif</td><td>{{ $r['product'] }}</td>
							<td>{{ $r['resolution'] === 'credito_tienda' ? 'Crédito en tienda' : 'Reembolso' }}</td><td class="text-right" data-export-value="{{ $r['amount'] }}">{{ $fm2($r['amount']) }}</td></tr>
					@endforeach
					</tbody>
				</table></div>
				@endif
				<p class="srep-note">Restan en la fecha de la devolución, sin reescribir el día de la venta original.@if($A['refunds']['excluded']) <span>No restan:</span> @foreach($A['refunds']['excluded'] as $why => $n)<span class="text-nowrap">{{ $n }} {{ $why }}</span>@if(!$loop->last) · @endif @endforeach @endif</p>
			</article>
			<article class="ded-card" data-testid="srep-hist-changes">
				<header class="ded-card-header"><h2 class="ded-card-title">Cambios a ventas de días anteriores</h2></header>
				@if(!$A['changes'])
				<p class="ded-text-muted-italics mb-0">Sin cambios a ventas históricas en el período.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Cambios históricos">
					<thead><tr><th>Cuándo</th><th>Ticket</th><th>Venta del</th><th>Cambio</th><th>Usuario</th><th>Respaldo</th></tr></thead>
					<tbody>
					@foreach($A['changes'] as $c)
						<tr><td>{{ $fdt($c['changed_at']) }}</td><td>{{ $c['ticket'] }}</td><td>{{ $fd($c['sale_day']) }}</td>
							<td>@if($c['action'] === 'delete')Borrado {{ $c['table'] === 'sales' ? 'del ticket' : 'de una línea' }}@else @foreach($c['fields'] as $fld){{ $fieldNames[$fld['field']] ?? $fld['field'] }}: {{ $fld['old'] }} → {{ $fld['new'] }}@if(!$loop->last); @endif @endforeach @endif</td>
							<td>{{ $c['user'] }}</td>
							<td>@if($c['correction_id'])<span class="ded-kpi-badge-green">Corrección #{{ $c['correction_id'] }}</span>@elseif($c['pos_cancel'])<span class="ded-kpi-badge-green">Cancelación en caja</span>@else<span class="ded-pill-red">Sin corrección registrada</span>@endif</td></tr>
					@endforeach
					</tbody>
				</table></div>
				@endif
			</article>
		</section>

		<section class="ded-row-2">
			<article class="ded-card" data-testid="srep-hist-corrections">
				<header class="ded-card-header"><h2 class="ded-card-title">Correcciones y ajustes autorizados</h2></header>
				@if(!$A['corrections'])
				<p class="ded-text-muted-italics mb-0">Aún no hay correcciones registradas.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Correcciones">
					<thead><tr><th>#</th><th>Fecha</th><th>Ticket</th><th>Tipo</th><th>Antes → después</th><th>Motivo</th><th>Autorizó</th></tr></thead>
					<tbody>
					@foreach($A['corrections'] as $k)
						<tr><td>{{ $k['id'] }}</td><td>{{ $fdt($k['created_at']) }}</td><td>{{ $k['ticket'] }}</td><td>{{ $k['kind'] === 'duplicate' ? 'Duplicado anulado' : 'Fecha de venta' }}</td>
							<td>@if($k['kind'] === 'date'){{ $fdt($k['before']['created_timestamp'] ?? null) }} → {{ $fdt($k['after']['created_timestamp'] ?? null) }}@else{{ $k['before']['status'] ?? '' }} → {{ $k['after']['status'] ?? '' }} (se conserva {{ $k['after']['kept'] ?? '' }})@endif</td>
							<td>{{ $k['reason'] }}</td><td>{{ $k['user'] }}</td></tr>
					@endforeach
					</tbody>
				</table></div>
				@endif
			</article>
			<article class="ded-card" data-testid="srep-hist-closures">
				<header class="ded-card-header"><h2 class="ded-card-title">Cierres comerciales</h2></header>
				@if($cl['rows'])
				<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Cierres">
					<thead><tr><th>Período</th><th class="text-right">Venta al cierre</th><th class="text-right">Venta hoy</th><th>Estado</th><th>Cerró</th></tr></thead>
					<tbody>
					@foreach($cl['rows'] as $c)
						<tr><td>{{ $c['type'] === 'week' ? 'Semana' : 'Mes' }} {{ $fd($c['from']) }} – {{ $fd($c['to']) }}</td><td class="text-right" data-export-value="{{ $c['net'] }}">{{ $fm2($c['net']) }}</td><td class="text-right" data-export-value="{{ $c['now_net'] }}">{{ $fm2($c['now_net']) }}</td>
							<td>@if($c['intact'])<span class="ded-kpi-badge-green">Sin cambios</span>@else<span class="ded-pill-red">Cambió {{ $c['delta_net'] >= 0 ? '+' : '' }}{{ $fm2($c['delta_net']) }} · {{ $c['delta_tickets'] >= 0 ? '+' : '' }}{{ $c['delta_tickets'] }} tickets</span><br><small class="text-muted">{{ $c['corrections_after'] }} correcciones autorizadas después del cierre</small>@endif</td>
							<td>{{ $c['user'] }}<br><small class="text-muted">{{ $fdt($c['closed_at']) }}</small></td></tr>
					@endforeach
					</tbody>
				</table></div>
				@else
				<p class="ded-text-muted-italics">Aún no hay períodos cerrados.</p>
				@endif
				@if($cl['pending'])
				<p class="srep-note mb-1">Períodos terminados sin cierre:</p>
				<div class="srep-close-list">
					@foreach($cl['pending'] as $p)
					<button type="button" class="ded-select-btn" data-hist-close="{{ $p['type'] }}" data-from="{{ $p['from'] }}" data-label="{{ ($p['type'] === 'week' ? 'Semana ' : 'Mes ') . $fd($p['from']) . ' – ' . $fd($p['to']) }}">
						<i data-lucide="lock"></i><span>{{ $p['type'] === 'week' ? 'Semana del ' . date('d/m', strtotime($p['from'])) : $monthLabel(substr($p['from'], 0, 7)) }}</span>
					</button>
					@endforeach
				</div>
				@endif
				<p class="srep-note">El cierre guarda la venta neta, los tickets y las devoluciones de toda la empresa; si cambian después, aquí se ve cuánto y con qué correcciones.</p>
			</article>
		</section>
	</section>
	@endif
	@endif

	{{-- ================================================================ 6. RENTABILIDAD --}}
	@if($section === 'profitability')
	@php
		$PF = $R['profitability'];
		$bd = $PF['by_department'][0] ?? null;
		$bb = $PF['by_branch'][0] ?? null;
		$V = $PF['pvc'];
		$CT = $PF['contribution'];
		$pp = fn($v) => $v === null ? $na : (($v >= 0 ? '+' : '') . number_format($v, 1) . ' pp');
		$sm = fn($v) => $v === null ? $na : (($v >= 0 ? '+' : '−') . '$' . number_format(abs($v), 0));
		$pfRow = function ($r) use ($fm2, $fp, $na) {
			return '<td class="ded-table-price" data-v="' . $r['net'] . '">' . $fm2($r['net']) . '</td>'
				. '<td class="ded-table-num" data-v="' . $r['cogs'] . '">' . $fm2($r['cogs']) . '</td>'
				. '<td class="ded-table-price" data-v="' . ($r['profit'] ?? '') . '">' . ($r['profit'] === null ? '<span class="ded-text-muted-italics">' . $na . '</span>' : $fm2($r['profit'])) . '</td>'
				. '<td class="ded-table-share" data-v="' . ($r['margin_pct'] ?? '') . '">' . ($r['margin_pct'] !== null && $r['margin_pct'] < 0 ? '<span class="ded-pill-red">' . $fp($r['margin_pct']) . '</span>' : $fp($r['margin_pct'])) . '</td>';
		};
	@endphp
	@if(!$PF['reliable'])
	<article class="ded-card mb-3"><span class="ded-pill-red">Dato no confiable: el costo de ventas registrado supera 3 veces la venta. Revise los costos antes de usar la utilidad y el margen.</span></article>
	@endif
	@if($PF['duplicates']['groups'] > 0 || $PF['net_missing'] > 0 || $PF['net_offlot'] + $PF['net_nolot'] > 0)
	<article class="ded-card mb-3 srep-quality" data-testid="srep-pf-quality">
		<header class="ded-card-header"><h2 class="ded-card-title">Calidad de los datos de rentabilidad</h2></header>
		<ul class="srep-quality-list">
			@if($PF['duplicates']['groups'] > 0)
			<li><span class="ded-pill-red">{{ $PF['duplicates']['groups'] }} tickets duplicados</span> <span>suman</span> {{ $fm2($PF['duplicates']['net']) }} <span>de venta neta en el período. Se corrigen en Historial de ventas (Auditoría) con autorización.</span></li>
			@endif
			@if($PF['net_missing'] > 0)
			<li><strong>{{ $fm2($PF['net_missing']) }}</strong> <span>de ventas ({{ number_format($PF['lines_missing']) }} líneas) no tienen costo registrado: quedan fuera de la utilidad y del margen en vez de contarse con costo cero.</span> <span>Cobertura de costo:</span> <strong>{{ $fp($PF['coverage_pct']) }}</strong>.</li>
			@endif
			@if($PF['net_offlot'] + $PF['net_nolot'] > 0)
			<li><strong>{{ $fm2($PF['net_offlot']) }}</strong> <span>de ventas tienen un costo que difiere más de 10 % del precio registrado de su lote y</span> <strong>{{ $fm2($PF['net_nolot']) }}</strong> <span>no tienen precio de lote. Con el costo del lote, la utilidad bruta sería</span> <strong>{{ $fm2($PF['profit_lot']) }}</strong> ({{ $fp($PF['margin_lot_pct']) }}).</li>
			@endif
		</ul>
	</article>
	@endif

	<section class="srep-kpis srep-kpis-8" aria-label="Indicadores de rentabilidad">
		{!! $kpi('wallet', 'Ventas netas', $fm($PF['net']), 'sin IVA, después de descuentos', 'Misma venta neta que el resto de los informes (Σ total − IVA de los tickets completados)', 'pf-net') !!}
		{!! $kpi('package', 'Costo de mercancía vendida', $fm($PF['cogs']), 'de ' . $fm($PF['net_c']) . ' con costo', 'Costo del lote vendido de las líneas con costo registrado', 'pf-cogs') !!}
		{!! $kpi('trending-up', 'Utilidad bruta', $fm($PF['profit']), 'ventas con costo − costo', 'Utilidad bruta = ventas netas con costo − costo de mercancía vendida. No es utilidad neta: no descuenta gastos de operación', 'pf-profit') !!}
		{!! $kpi('percent', 'Margen bruto', $fp($PF['margin_pct']), $PF['margin_delta_pp'] === null ? 'Anterior: ' . $na : $badge($PF['margin_delta_pp'], ' pp') . '&nbsp;vs. ' . $h($P['prev_name']), 'Margen bruto = utilidad bruta ÷ ventas netas con costo × 100 (no sobre costo ni sobre venta con IVA)', 'pf-margin') !!}
		{!! $kpi('badge-percent', 'Efecto de descuentos', $PF['discount'] > 0 ? '−' . $fm($PF['discount']) : $fm(0), $PF['margin_nodisc_pct'] === null || $PF['margin_pct'] === null ? $na : 'margen ' . $pp($PF['margin_pct'] - $PF['margin_nodisc_pct']), 'Descuentos de los tickets: reducen la utilidad bruta peso a peso', 'pf-discount') !!}
		{!! $kpi('shield-check', 'Cobertura de costo', $fp($PF['coverage_pct']), 'verificado con el lote: ' . $fp($PF['verified_pct']), 'Ventas netas con costo registrado ÷ ventas netas', 'pf-coverage') !!}
		{!! $kpi('triangle-alert', 'Productos con margen negativo', number_format(count($PF['negative'])), count($PF['negative']) ? 'pérdida ' . $fm(abs(array_sum(array_column($PF['negative'], 'profit')))) : 'ninguno en el período', 'Productos cuyo costo supera su venta neta', 'pf-negative') !!}
		{!! $kpi('boxes', 'Departamento más rentable', $bd['name'] ?? $na, $bd ? $fm($bd['profit']) . ' · ' . $fp($bd['margin_pct']) : $na, 'Mayor utilidad bruta por departamento', 'pf-dept') !!}
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-pf-compare">
			<header class="ded-card-header"><h2 class="ded-card-title">Comparación de márgenes entre períodos</h2></header>
			<div class="srep-scroll">
				<table class="ded-table-top5" data-export-name="Margen por período">
					<thead><tr><th>Período</th><th class="text-right">Ventas netas</th><th class="text-right">Costo</th><th class="text-right">Utilidad bruta</th><th class="text-right">Margen</th><th class="text-right">Cobertura</th></tr></thead>
					<tbody>
					@foreach($PF['compare'] as $cp)
						<tr @if($cp['key'] === 'period') class="font-weight-bold" @endif><td>{{ $cp['label'] }}<br><small class="text-muted">{{ $fd($cp['from']) }}@if($cp['from'] !== $cp['to']) – {{ $fd($cp['to']) }}@endif</small></td>
							<td class="text-right" data-export-value="{{ $cp['net'] }}">{{ $cp['net'] > 0 ? $fm($cp['net']) : $na }}</td><td class="text-right" data-export-value="{{ $cp['cogs'] }}">{{ $cp['net'] > 0 ? $fm($cp['cogs']) : '—' }}</td>
							<td class="text-right" data-export-value="{{ $cp['profit'] ?? '' }}">{{ $fm($cp['profit']) }}</td><td class="text-right" data-export-value="{{ $cp['margin_pct'] ?? '' }}">{{ $fp($cp['margin_pct']) }}</td><td class="text-right">{{ $fp($cp['coverage_pct']) }}</td></tr>
					@endforeach
					</tbody>
				</table>
			</div>
			<p class="srep-note">Cada margen se calcula sobre su propia venta con costo; un período sin ventas muestra «Sin información», no 0 %.</p>
		</article>
		<article class="ded-card" data-testid="srep-pf-discounts">
			<header class="ded-card-header"><h2 class="ded-card-title">Efecto financiero de los descuentos</h2></header>
			<div class="ded-comp-col">
				<div class="ded-comp-row"><span class="ded-comp-label">Venta antes de descuentos (sin IVA)</span><span class="ded-comp-val">{{ $fm2($PF['gross']) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Descuentos concedidos</span><span class="ded-comp-val">{{ $PF['discount'] > 0 ? '−' . $fm2($PF['discount']) : $fm2(0) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Ventas netas</span><span class="ded-comp-val">{{ $fm2($PF['net']) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Utilidad bruta sin descuentos</span><span class="ded-comp-val">{{ $fm2($PF['profit_nodisc']) }} · {{ $fp($PF['margin_nodisc_pct']) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Utilidad bruta real</span><span class="ded-comp-val">{{ $fm2($PF['profit']) }} · {{ $fp($PF['margin_pct']) }}</span></div>
				<div class="ded-comp-row"><span class="ded-comp-label">Utilidad cedida en descuentos</span><span class="ded-comp-val">{{ $PF['profit'] === null ? $na : $fm2($PF['profit_nodisc'] - $PF['profit']) }}@if($PF['profit'] !== null && $PF['profit_nodisc'] > 0) ({{ number_format(($PF['profit_nodisc'] - $PF['profit']) / $PF['profit_nodisc'] * 100, 1) }} %)@endif</span></div>
			</div>
			@php $dd = array_values(array_filter($PF['by_department'], fn($r) => $r['discount'] > 0.005)); usort($dd, fn($a, $b) => $b['discount'] <=> $a['discount']); @endphp
			@if($dd)
			<p class="srep-note mb-1">Descuentos por departamento:</p>
			<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Descuentos por departamento">
				<thead><tr><th>Departamento</th><th class="text-right">Descuento</th><th class="text-right">% de su venta</th><th class="text-right">Margen sin / con</th></tr></thead>
				<tbody>
				@foreach(array_slice($dd, 0, 6) as $r)
					<tr><td>{{ $r['name'] }}</td><td class="text-right" data-export-value="{{ $r['discount'] }}">{{ $fm2($r['discount']) }}</td><td class="text-right">{{ $fp($r['net'] + $r['discount'] > 0 ? $r['discount'] / ($r['net'] + $r['discount']) * 100 : null) }}</td><td class="text-right">{{ $fp($r['margin_nodisc_pct']) }} / {{ $fp($r['margin_pct']) }}</td></tr>
				@endforeach
				</tbody>
			</table></div>
			@endif
			<p class="srep-note">Un descuento reduce la utilidad bruta en el mismo monto: el costo no cambia.@if($PF['net_archive'] > 0) Los días del archivo histórico no tienen detalle de descuentos.@endif</p>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución del margen bruto</h2><span class="srep-note mb-0">Diario · %</span></header>
			<div class="ded-chart-canvas-box"><canvas id="srepPfMargin" aria-label="Evolución diaria del margen bruto"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Ventas netas vs. costo de mercancía vendida</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Ventas netas</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#f59e0b;"></span><span>Costo</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepPfNetCogs" aria-label="Ventas netas frente al costo de mercancía vendida"></canvas></div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Departamentos por utilidad bruta</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($PF['by_department']) * 26 + 30) }}px;"><canvas id="srepPfDept" aria-label="Ranking de departamentos por utilidad bruta"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Sucursales por utilidad bruta</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($PF['by_branch']) * 26 + 30) }}px;"><canvas id="srepPfBranch" aria-label="Ranking de sucursales por utilidad bruta"></canvas></div>
		</article>
	</section>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-pf-dept-table">
			<header class="ded-card-header"><h2 class="ded-card-title">Rentabilidad por departamento</h2></header>
			<div class="srep-scroll"><table class="ded-table-top5 srep-sortable" data-export-name="Rentabilidad por departamento">
				<thead><tr><th data-sort="text">Departamento</th><th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Costo</th><th class="text-right" data-sort="num">Utilidad bruta</th><th class="text-right" data-sort="num">Margen</th><th class="text-right" data-sort="num">% de la utilidad</th></tr></thead>
				<tbody>
				@foreach($PF['by_department'] as $r)
					<tr><td><span class="ded-legend-rect" style="background:{{ $r['color'] }};"></span> {{ $r['name'] }}</td>{!! $pfRow($r) !!}<td class="ded-table-num" data-v="{{ $r['profit_share_pct'] ?? '' }}">{{ $fp($r['profit_share_pct']) }}</td></tr>
				@endforeach
				</tbody>
			</table></div>
		</article>
		<article class="ded-card" data-testid="srep-pf-branch-table">
			<header class="ded-card-header"><h2 class="ded-card-title">Rentabilidad por sucursal</h2></header>
			<div class="srep-scroll"><table class="ded-table-top5 srep-sortable" data-export-name="Rentabilidad por sucursal">
				<thead><tr><th data-sort="text">Sucursal</th><th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Costo</th><th class="text-right" data-sort="num">Utilidad bruta</th><th class="text-right" data-sort="num">Margen</th><th class="text-right" data-sort="num">% de la utilidad</th></tr></thead>
				<tbody>
				@foreach($PF['by_branch'] as $r)
					<tr><td>{{ $r['name'] }}</td>{!! $pfRow($r) !!}<td class="ded-table-num" data-v="{{ $r['profit_share_pct'] ?? '' }}">{{ $fp($r['profit_share_pct']) }}</td></tr>
				@endforeach
				</tbody>
			</table></div>
		</article>
	</section>

	<article class="ded-card mb-3" data-testid="srep-pf-sku">
		<header class="ded-card-header">
			<h2 class="ded-card-title">Rentabilidad por SKU</h2>
			<div class="ded-card-tools">
				<input type="search" class="ded-select-btn srep-search" data-srep-search="#srepPfSkuTable" placeholder="Buscar producto o SKU" aria-label="Buscar producto o SKU">
			</div>
		</header>
		@if(!$PF['products'])
			<span class="ded-text-muted-italics">Sin información: no hay ventas de productos en el período.</span>
		@else
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable srep-paged" id="srepPfSkuTable" data-export-name="Rentabilidad por SKU">
				<thead><tr>
					<th data-sort="text">SKU</th><th data-sort="text">Producto</th><th data-sort="text">Departamento</th><th class="text-right" data-sort="num">Unidades</th>
					<th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Costo</th><th class="text-right" data-sort="num">Utilidad bruta</th><th class="text-right" data-sort="num">Margen</th><th class="text-right" data-sort="num">Cobertura de costo</th>
				</tr></thead>
				<tbody>
				@foreach($PF['products'] as $r)
					<tr>
						<td><span class="ded-sku-badge">{{ $r['sku'] ?: '—' }}</span></td>
						<td><div class="ded-prod-name">{{ $r['name'] }}</div></td>
						<td>{{ $r['dept'] }}</td>
						<td class="ded-table-num" data-v="{{ $r['units'] }}">{{ number_format($r['units'], 0) }}</td>
						{!! $pfRow($r) !!}
						<td class="ded-table-num" data-v="{{ $r['coverage_pct'] ?? '' }}">{{ $fp($r['coverage_pct']) }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
		@if(count($PF['products']) > 25)<button type="button" class="ded-select-btn mt-2" data-srep-more="#srepPfSkuTable">Ver los {{ count($PF['products']) }} productos</button>@endif
		@endif
	</article>

	<section class="ded-row-2 srep-even">
		<article class="ded-card" data-testid="srep-pf-negative">
			<header class="ded-card-header"><h2 class="ded-card-title">Productos con margen negativo</h2></header>
			@if(!$PF['negative'])
			<p class="ded-text-muted-italics mb-0">Ningún producto con costo registrado se vendió por debajo de su costo en el período.</p>
			@else
			<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Margen negativo">
				<thead><tr><th>Producto</th><th class="text-right">Ventas netas</th><th class="text-right">Costo</th><th class="text-right">Pérdida</th><th class="text-right">Margen</th></tr></thead>
				<tbody>
				@foreach(array_slice($PF['negative'], 0, 25) as $r)
					<tr><td>{{ $r['name'] }}<br><small class="text-muted">{{ $r['sku'] }}</small></td>{!! $pfRow($r) !!}</tr>
				@endforeach
				</tbody>
			</table></div>
			@endif
		</article>
		<article class="ded-card" data-testid="srep-pf-missing">
			<header class="ded-card-header"><h2 class="ded-card-title">Ventas sin costo registrado</h2>@if($PF['missing_cost_products'])<span class="srep-note mb-0">{{ $PF['missing_cost_products'] }} productos</span>@endif</header>
			@if(!$PF['missing_cost'])
			<p class="ded-text-muted-italics mb-0">Todas las ventas del período tienen costo registrado.</p>
			@else
			<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Sin costo">
				<thead><tr><th>Producto</th><th>Departamento</th><th class="text-right">Ventas sin costo</th><th class="text-right">Líneas</th></tr></thead>
				<tbody>
				@foreach($PF['missing_cost'] as $r)
					<tr><td>{{ $r['name'] }}<br><small class="text-muted">{{ $r['sku'] }}</small></td><td>{{ $r['dept'] }}</td><td class="text-right" data-export-value="{{ $r['net_missing'] }}">{{ $fm2($r['net_missing']) }}</td><td class="text-right">{{ number_format($r['lines_missing']) }}</td></tr>
				@endforeach
				</tbody>
			</table></div>
			@endif
			<p class="srep-note">No se les asigna costo cero: se excluyen del margen hasta que el costo se registre.</p>
		</article>
	</section>

	<article class="ded-card mb-3" data-testid="srep-pf-pvc">
		<header class="ded-card-header">
			<div>
				<h2 class="ded-card-title">Variación de la utilidad bruta: precio, volumen y costo</h2>
				<p class="srep-note mb-0">{{ $P['label'] }} vs. {{ $P['prev_name'] }} ({{ $fd($P['prev_from']) }} – {{ $fd($P['prev_to']) }}) · solo ventas con costo</p>
			</div>
		</header>
		@if($V === null)
			<p class="ded-text-muted-italics mb-0">Sin información: el período de comparación está en el archivo histórico consolidado, que no tiene precio, volumen ni costo por producto.</p>
		@else
		<div class="srep-pvc">
			<div class="srep-pvc-step"><span>Utilidad anterior</span><strong>{{ $fm($V['prev_profit']) }}</strong></div>
			<div class="srep-pvc-step"><span>Volumen</span><strong>{{ $sm($V['volume']) }}</strong></div>
			<div class="srep-pvc-step"><span>Precio</span><strong>{{ $sm($V['price']) }}</strong></div>
			<div class="srep-pvc-step"><span>Costo</span><strong>{{ $sm($V['cost']) }}</strong></div>
			<div class="srep-pvc-step"><span>Productos nuevos</span><strong>{{ $sm($V['mix_new']) }}</strong></div>
			<div class="srep-pvc-step"><span>Dejaron de venderse</span><strong>{{ $sm($V['mix_lost']) }}</strong></div>
			<div class="srep-pvc-step is-total"><span>Utilidad actual</span><strong>{{ $fm($V['profit']) }}</strong></div>
		</div>
		<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Precio volumen costo">
			<thead><tr><th>Producto</th><th class="text-right">Unidades</th><th class="text-right">Precio neto unitario</th><th class="text-right">Costo unitario</th><th class="text-right">Volumen</th><th class="text-right">Precio</th><th class="text-right">Costo</th><th class="text-right">Nuevo / retirado</th><th class="text-right">Variación</th></tr></thead>
			<tbody>
			@foreach($V['rows'] as $r)
				<tr><td>{{ $r['name'] }}<br><small class="text-muted">{{ $r['sku'] }}</small></td>
					<td class="text-right">{{ number_format($r['prev_units'], 0) }} → {{ number_format($r['units'], 0) }}</td>
					<td class="text-right">{{ $r['prev_unit_price'] === null ? '—' : $fm2($r['prev_unit_price']) }} → {{ $r['unit_price'] === null ? '—' : $fm2($r['unit_price']) }}</td>
					<td class="text-right">{{ $r['prev_unit_cost'] === null ? '—' : $fm2($r['prev_unit_cost']) }} → {{ $r['unit_cost'] === null ? '—' : $fm2($r['unit_cost']) }}</td>
					<td class="text-right" data-export-value="{{ $r['volume'] }}">{{ $sm($r['volume']) }}</td><td class="text-right" data-export-value="{{ $r['price'] }}">{{ $sm($r['price']) }}</td>
					<td class="text-right" data-export-value="{{ $r['cost'] }}">{{ $sm($r['cost']) }}</td><td class="text-right" data-export-value="{{ $r['mix'] }}">{{ $sm($r['mix']) }}</td>
					<td class="text-right font-weight-bold" data-export-value="{{ $r['delta'] }}">{{ $sm($r['delta']) }}</td></tr>
			@endforeach
			</tbody>
		</table></div>
		<p class="srep-note">Volumen = cambio de unidades × utilidad unitaria anterior · Precio = cambio de precio neto × unidades actuales · Costo = cambio de costo unitario × unidades actuales. Los {{ $V['products'] }} productos suman exactamente la variación total; aquí, los 20 de mayor efecto.</p>
		@endif
	</article>

	<article class="ded-card mb-3" data-testid="srep-pf-contribution">
		<header class="ded-card-header"><h2 class="ded-card-title">Contribución comercial por sucursal</h2></header>
		@if($CT['product_filtered'])
			<p class="ded-text-muted-italics mb-0">Sin información: con filtro de departamento, categoría o producto no hay costos asignables confiables (la nómina es por sucursal).</p>
		@elseif(!$CT['reliable_count'])
			<p class="ded-text-muted-italics mb-0">Sin información: ninguna sucursal tiene nóminas aprobadas que cubran todos los días del período. No se estima la contribución con costos incompletos.</p>
		@endif
		@if(!$CT['product_filtered'])
		<div class="srep-scroll"><table class="ded-table-top5" data-export-name="Contribución">
			<thead><tr><th>Sucursal</th><th class="text-right">Utilidad bruta</th><th class="text-right">Costo laboral asignado</th><th class="text-right">Contribución</th><th class="text-right">% de la venta</th><th class="text-right">Cobertura de nómina</th></tr></thead>
			<tbody>
			@foreach($CT['rows'] as $r)
				<tr><td>{{ $r['name'] }}</td><td class="text-right">{{ $fm2($r['profit']) }}</td><td class="text-right">{{ $r['labor_cost'] === null ? '—' : $fm2($r['labor_cost']) }}</td>
					<td class="text-right font-weight-bold">{{ $r['contribution'] === null ? '—' : $fm2($r['contribution']) }}</td><td class="text-right">{{ $r['contribution_pct'] === null ? '—' : $fp($r['contribution_pct']) }}</td><td class="text-right">{{ $fp($r['labor_coverage_pct']) }}</td></tr>
			@endforeach
			</tbody>
		</table></div>
		@endif
		<p class="srep-note">Contribución = utilidad bruta − costo laboral de nóminas aprobadas, prorrateado por días. Solo se muestra cuando la nómina cubre el 100 % del período.</p>
	</article>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Volumen de ventas vs. margen por producto</h2></header>
			<div class="ded-chart-canvas-box srep-tall"><canvas id="srepPfScatter" aria-label="Dispersión de ventas frente a margen por producto"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Tendencia histórica de rentabilidad</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Utilidad</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot"></span><span>Margen %</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box srep-tall"><canvas id="srepPfTrend" aria-label="Utilidad y margen mensuales"></canvas></div>
		</article>
	</section>
	<p class="srep-note">Utilidad bruta = ventas netas con costo − costo de mercancía vendida (costo del lote vendido). Margen bruto = utilidad bruta ÷ ventas netas con costo × 100. La utilidad neta, que además descuenta gastos de operación, no se calcula en este tablero.</p>
	@endif

	@endif
</section>

@if(!empty($emitData) && $R)
<script type="application/json" id="srepData">{!! json_encode($R, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
