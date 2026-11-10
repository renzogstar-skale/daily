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
	$kpi = function ($icon, $label, $value, $sub, $title = '', $id = '') use ($h) {
		return '<article class="ded-kpi-card"' . ($id ? ' data-testid="srep-kpi-' . $h($id) . '"' : '') . ' title="' . $h($title ?: $label) . '">'
			. '<div class="ded-kpi-top"><span class="ded-kpi-icon"><i data-lucide="' . $h($icon) . '"></i></span></div>'
			. '<div class="ded-kpi-label">' . $h($label) . '</div><div class="ded-kpi-val">' . $h($value) . '</div><div class="ded-kpi-sub">' . $sub . '</div></article>';
	};
	$titles = [
		'dashboard' => ['Informe comercial · Tablero de ventas', '¿Cuánto estamos vendiendo y cómo evolucionan las ventas?'],
		'departments' => ['Informe comercial · Ventas por departamento', '¿Qué departamento genera más ventas?'],
		'products' => ['Informe comercial · Productos de venta', '¿Cuáles son los productos más vendidos de toda la empresa?'],
		'branches' => ['Informe comercial · Sucursales', '¿Cuál tienda vende más y cuál tiene mejor desempeño comercial?'],
		'history' => ['Informe comercial · Historial de ventas', '¿Cómo han evolucionado las ventas a lo largo del tiempo?'],
		'profitability' => ['Informe comercial · Rentabilidad', '¿Qué ventas generan mayor utilidad bruta?']
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
			<button type="button" class="ded-select-btn" data-srep-export="excel" data-section="{{ $section }}" title="Descargar el informe con los filtros aplicados (Excel)">
				<i data-lucide="file-spreadsheet"></i><span>Excel</span>
			</button>
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
	@php $D = $R['dashboard']; $acc = $D['accumulated']; @endphp
	<section class="srep-kpis srep-kpis-8" aria-label="Indicadores del tablero de ventas">
		{!! $kpi('dollar-sign', 'Ventas netas totales', $fm($D['net']), $badge($D['growth_pct']) . '&nbsp;vs. ' . $h($P['prev_name']), 'Ventas netas (total − IVA, con descuentos) de ventas completadas', 'net') !!}
		{!! $kpi('target', 'Meta comercial', $fm($D['goal']), $D['goal'] === null ? ($R['product_filtered'] ? 'Sin metas por departamento o producto' : 'Sin meta registrada para todo el período') : 'Prorrateada por día del mes', 'Meta de ventas (Datos maestros › Metas) prorrateada al período', 'goal') !!}
		{!! $kpi('gauge', 'Cumplimiento de meta', $fp($D['goal_pct']), $D['goal_gap'] === null ? $na : (($D['goal_gap'] >= 0 ? 'Supera por ' : 'Faltan ') . $fm(abs($D['goal_gap']))), 'Ventas netas ÷ meta del período × 100', 'goal-pct') !!}
		{!! $kpi('trending-up', 'Crecimiento', $D['growth_pct'] === null ? $na : (($D['growth_pct'] >= 0 ? '+' : '') . number_format($D['growth_pct'], 1) . ' %'), 'Anterior: ' . $fm($D['prev_net']), '(Ventas actuales − anteriores) ÷ anteriores × 100', 'growth') !!}
		{!! $kpi('receipt', 'Ticket promedio', $fm2($D['avg_ticket']), 'Anterior: ' . $fm2($D['prev_avg_ticket']), 'Ventas netas ÷ transacciones', 'avg-ticket') !!}
		{!! $kpi('shopping-cart', 'Transacciones', number_format($D['tickets']), $badge($D['tickets_delta']), 'Tickets de venta completados', 'tickets') !!}
		{!! $kpi('clock', 'Ventas por hora de operación', $fm($D['per_hour']), number_format($D['active_hours']) . ' h con ventas', 'Ventas netas ÷ horas en las que hubo ventas', 'per-hour') !!}
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
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Cumplimiento de meta comercial</h2></header>
			@if($D['goal'] === null)
				<span class="ded-text-muted-italics">Sin información: {{ $R['product_filtered'] ? 'no hay metas de venta por departamento, categoría o producto.' : 'no hay meta de ventas registrada para todo el período.' }}</span>
			@else
			<div class="srep-gauge">
				<div class="ded-donut-wrap srep-gauge-wrap">
					<canvas id="srepDashGoal" aria-label="Cumplimiento de la meta"></canvas>
					<div class="ded-donut-center-info">
						<span class="ded-donut-center-amount">{{ $fp($D['goal_pct']) }}</span>
						<span class="ded-donut-center-label">de la meta</span>
					</div>
				</div>
				<div class="ded-comp-col">
					<div class="ded-comp-row"><span class="ded-comp-label">Ventas netas</span><span class="ded-comp-val">{{ $fm($D['net']) }}</span></div>
					<div class="ded-comp-row"><span class="ded-comp-label">Meta del período</span><span class="ded-comp-val">{{ $fm($D['goal']) }}</span></div>
					<div class="ded-comp-row"><span class="ded-comp-label">{{ $D['goal_gap'] >= 0 ? 'Excedente' : 'Brecha' }}</span><span class="ded-comp-val">{{ $fm(abs($D['goal_gap'])) }}</span></div>
				</div>
			</div>
			@endif
		</article>
	</section>

	<section class="ded-row-3">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Curva de ventas por hora</h2></header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashHourly" aria-label="Ventas por hora del día"></canvas></div>
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
				<h2 class="ded-card-title">Acumulado vs. objetivo</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Acumulado</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot"></span><span>Meta</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDashCum" aria-label="Ventas acumuladas frente a la meta"></canvas></div>
			@if($D['goal'] === null)<p class="srep-note">Meta: Sin información.</p>@endif
		</article>
	</section>
	@endif

	{{-- ================================================================ 2. VENTAS POR DEPARTAMENTO --}}
	@if($section === 'departments')
	@php
		$DP = $R['departments'];
		$rows = $DP['rows'];
		$lead = $rows[0] ?? null;
		$withDelta = array_values(array_filter($rows, fn($r) => $r['delta_pct'] !== null));
		usort($withDelta, fn($a, $b) => $b['delta_pct'] <=> $a['delta_pct']);
		$best = $withDelta[0] ?? null;
		$worst = count($withDelta) > 1 ? end($withDelta) : null;
		$top3 = array_sum(array_map(fn($r) => $r['share_pct'] ?? 0, array_slice($rows, 0, 3)));
		$withGoal = array_filter($rows, fn($r) => $r['goal'] !== null);
		$deptGoal = array_sum(array_column($withGoal, 'goal'));
		$deptNet = array_sum(array_column($withGoal, 'net'));
		$deptWithGoal = count($withGoal);
		$deptMet = count(array_filter($withGoal, fn($r) => $r['goal_pct'] >= 100));
	@endphp
	<section class="srep-kpis" aria-label="Indicadores por departamento">
		{!! $kpi('award', 'Departamento líder', $lead['name'] ?? $na, $lead ? $fm($lead['net']) . ' · ' . $fp($lead['share_pct']) : $na, 'Departamento con mayores ventas netas', 'dept-lead') !!}
		{!! $kpi('trending-up', 'Mayor crecimiento', $best['name'] ?? $na, $best ? $badge($best['delta_pct']) : $na, 'Variación de ventas frente al ' . $P['prev_name'], 'dept-best') !!}
		{!! $kpi('trending-down', 'Mayor caída', $worst && $worst['delta_pct'] < 0 ? $worst['name'] : ($withDelta ? 'Ninguno' : $na), $worst && $worst['delta_pct'] < 0 ? $badge($worst['delta_pct']) : ($withDelta ? 'Todos los departamentos crecen' : 'Sin período comparable'), 'Variación de ventas frente al ' . $P['prev_name'], 'dept-worst') !!}
		{!! $kpi('pie-chart', 'Participación top 3', $rows ? number_format($top3, 1) . ' %' : $na, count($rows) . ' departamentos con venta', 'Suma de la participación de los tres primeros', 'dept-top3') !!}
		{!! $kpi('target', 'Cumplimiento de metas', $deptGoal ? number_format($deptNet / $deptGoal * 100, 1) . ' %' : $na, $deptGoal ? $deptMet . ' de ' . $deptWithGoal . ' departamentos cumplen su meta' : ($sharedFilters['branch_id'] ? 'Las metas por departamento son de toda la red' : 'Sin metas de departamento para el período'), 'Ventas netas ÷ meta de los departamentos con meta', 'dept-goal') !!}
	</section>

	@if(!$rows)
	<article class="ded-card mb-3"><span class="ded-text-muted-italics">Sin información: no hay ventas en el período con estos filtros.</span></article>
	@else
	<article class="ded-card mb-3 srep-select-card">
		<header class="ded-card-header">
			<h2 class="ded-card-title">Departamento seleccionado</h2>
			<select class="ded-select-btn srep-select" id="srepDeptSelect" aria-label="Seleccionar departamento">
				<option value="">Todos los departamentos</option>
				@foreach($rows as $r)<option value="{{ $r['key'] }}">{{ $r['name'] }}</option>@endforeach
			</select>
		</header>
		<div class="srep-detail" id="srepDeptDetail" aria-live="polite"></div>
	</article>

	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Departamentos por ventas netas</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($rows) * 26 + 30) }}px;"><canvas id="srepDeptBars" aria-label="Ranking de departamentos por ventas"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Participación por departamento</h2></header>
			<div class="ded-dept-donut-layout">
				<div class="ded-donut-wrap">
					<canvas id="srepDeptDonut" aria-label="Participación de cada departamento"></canvas>
					<div class="ded-donut-center-info">
						<span class="ded-donut-center-amount">{{ $fm($R['dashboard']['net']) }}</span>
						<span class="ded-donut-center-label">Ventas netas</span>
					</div>
				</div>
				<div class="ded-dept-list">
					@foreach($rows as $r)
					<div class="ded-dept-row" data-srep-dept="{{ $r['key'] }}" title="{{ $r['name'] }}: {{ $fp($r['share_pct']) }}">
						<div class="ded-dept-name-box"><span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span><span>{{ $r['name'] }}</span></div>
						<span class="ded-dept-pct">{{ $fp($r['share_pct']) }}</span>
					</div>
					@endforeach
				</div>
			</div>
		</article>
	</section>

	<section class="ded-row-2">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución comercial por departamento</h2><span class="srep-note mb-0" id="srepDeptEvoNote">Cinco principales</span></header>
			<div class="ded-chart-canvas-box"><canvas id="srepDeptEvo" aria-label="Evolución diaria por departamento"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Actual vs. {{ $P['prev_name'] }}</h2>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Actual</span></span>
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#94a3b8;"></span><span>Anterior</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box"><canvas id="srepDeptVsPrev" aria-label="Ventas por departamento frente al período anterior"></canvas></div>
		</article>
	</section>

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Mapa de calor · departamento × sucursal</h2><span class="srep-note mb-0">Ventas netas del período</span></header>
		@php $hmax = 0; foreach ($DP['heat'] as $hr) { $hmax = max($hmax, max($hr ?: [0])); } @endphp
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-heat" data-export-name="Departamento x sucursal">
				<thead><tr><th>Departamento</th>@foreach($DP['heat_branches'] as $b)<th class="text-right">{{ $b['name'] }}</th>@endforeach</tr></thead>
				<tbody>
				@foreach($rows as $r)
					<tr data-srep-dept-row="{{ $r['key'] }}">
						<td><span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span> {{ $r['name'] }}</td>
						@foreach($DP['heat_branches'] as $b)
							@php $v = $DP['heat'][$r['key']][$b['id']] ?? 0; $a = $hmax > 0 ? round($v / $hmax, 2) : 0; @endphp
							<td class="text-right srep-heat-cell" style="--srep-a: {{ $a }};" data-export-value="{{ round($v, 2) }}" title="{{ $r['name'] }} · {{ $b['name'] }}: {{ $fm2($v) }}">{{ $v > 0 ? $fm($v) : '—' }}</td>
						@endforeach
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
	</article>

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Tabla analítica por departamento</h2></header>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable" data-export-name="Departamentos">
				<thead><tr>
					<th data-sort="text">Departamento</th><th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Participación</th>
					<th class="text-right" data-sort="num">Período anterior</th><th class="text-right" data-sort="num">Variación</th><th class="text-right" data-sort="num">Transacciones</th>
					<th class="text-right" data-sort="num">Unidades</th><th class="text-right">Meta</th><th class="text-right">Cumplimiento</th>
				</tr></thead>
				<tbody>
				@foreach($rows as $r)
					<tr data-srep-dept-row="{{ $r['key'] }}">
						<td><span class="ded-dept-dot" style="background-color: {{ $r['color'] }};"></span> {{ $r['name'] }}</td>
						<td class="ded-table-price" data-v="{{ $r['net'] }}">{{ $fm2($r['net']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['share_pct'] ?? -1 }}">{{ $fp($r['share_pct']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['prev_net'] }}">{{ $fm2($r['prev_net']) }}</td>
						<td class="text-right" data-v="{{ $r['delta_pct'] ?? -99999 }}">{!! $badge($r['delta_pct']) !!}</td>
						<td class="ded-table-num" data-v="{{ $r['tickets'] }}">{{ number_format($r['tickets']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['units'] }}">{{ number_format($r['units'], 0) }}</td>
						<td class="ded-table-num" data-v="{{ $r['goal'] ?? -1 }}">{{ $fm($r['goal']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['goal_pct'] ?? -1 }}">{{ $fp($r['goal_pct']) }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
		<p class="srep-note">Transacciones = tickets que incluyen el departamento (un ticket con varios departamentos cuenta en cada uno).</p>
	</article>
	@endif
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
		$top = $rows[0] ?? null;
		$bottom = count($rows) > 1 ? end($rows) : null;
		$D = $R['dashboard'];
		// Posiciones ilustrativas del mapa existente (Sucursales › Mapa interactivo); las demás se distribuyen en círculo
		$coords = [];
		foreach (($branchSupervisionDashboard['branches'] ?? []) as $sb) { if (isset($sb['name'], $sb['map_coords'])) $coords[$sb['name']] = $sb['map_coords']; }
		$maxNet = $top['net'] ?? 0;
	@endphp
	<section class="srep-kpis" aria-label="Indicadores de sucursales">
		{!! $kpi('crown', 'Sucursal con mayores ventas', $top['name'] ?? $na, $top ? $fm($top['net']) . ' · ' . $fp($top['share_pct']) : $na, 'Primera del ranking por ventas netas', 'branch-top') !!}
		{!! $kpi('arrow-down-circle', 'Sucursal con menores ventas', $bottom['name'] ?? ($top['name'] ?? $na), $bottom ? $fm($bottom['net']) . ' · ' . $fp($bottom['share_pct']) : ($top ? 'Única sucursal del filtro' : $na), 'Última del ranking por ventas netas', 'branch-bottom') !!}
		{!! $kpi('target', 'Cumplimiento de metas', $fp($D['goal_pct']), $D['goal'] === null ? 'Sin meta para todo el período' : 'Meta ' . $fm($D['goal']), 'Ventas netas ÷ meta del período (sucursales filtradas)', 'branch-goal') !!}
		{!! $kpi('receipt', 'Ticket promedio', $fm2($D['avg_ticket']), number_format($D['tickets']) . ' transacciones', 'Ventas netas ÷ transacciones', 'branch-ticket') !!}
		{!! $kpi('trending-up', 'Crecimiento comercial', $D['growth_pct'] === null ? $na : (($D['growth_pct'] >= 0 ? '+' : '') . number_format($D['growth_pct'], 1) . ' %'), 'vs. ' . $h($P['prev_name']), '(Ventas actuales − anteriores) ÷ anteriores × 100', 'branch-growth') !!}
	</section>

	@if(!$rows)
	<article class="ded-card mb-3"><span class="ded-text-muted-italics">Sin información: no hay ventas en el período con estos filtros.</span></article>
	@else
	<article class="ded-card mb-3 srep-select-card">
		<header class="ded-card-header">
			<h2 class="ded-card-title">Sucursal seleccionada</h2>
			<select class="ded-select-btn srep-select" id="srepBranchSelect" aria-label="Seleccionar sucursal">
				<option value="">Cinco principales</option>
				@foreach($rows as $r)<option value="{{ $r['id'] }}">{{ $r['rank'] }}. {{ $r['name'] }}</option>@endforeach
			</select>
		</header>
		<div class="srep-detail" id="srepBranchDetail" aria-live="polite"></div>
	</article>

	<section class="ded-row-2 srep-even">
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Ranking de sucursales por ventas</h2></header>
			<div class="ded-chart-canvas-box srep-tall" style="height: {{ max(195, count($rows) * 26 + 30) }}px;"><canvas id="srepBranchBars" aria-label="Ranking de sucursales por ventas netas"></canvas></div>
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Mapa comercial</h2><span class="srep-note mb-0">Tamaño = ventas netas · posiciones ilustrativas</span></header>
			<div class="branch-map-wrapper">
				<div class="branch-map-canvas srep-map" id="srepBranchMap">
					@foreach($rows as $i => $r)
						@php
							$c = $coords[$r['name']] ?? ['x' => 50 + 34 * cos(2 * M_PI * $i / max(1, count($rows))), 'y' => 50 + 36 * sin(2 * M_PI * $i / max(1, count($rows)))];
							$size = $maxNet > 0 ? round(12 + 22 * sqrt($r['net'] / $maxNet)) : 12;
							$st = $r['goal_pct'] === null ? ($r['delta_pct'] === null || $r['delta_pct'] >= 0 ? 'operativa' : 'atencion') : ($r['goal_pct'] >= 100 ? 'operativa' : ($r['goal_pct'] >= 85 ? 'atencion' : 'critico'));
						@endphp
						<div class="branch-map-node node-{{ $st }}" style="left: {{ round($c['x'], 1) }}%; top: {{ round($c['y'], 1) }}%;" data-srep-branch="{{ $r['id'] }}"
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
		</article>
		<article class="ded-card">
			<header class="ded-card-header"><h2 class="ded-card-title">Evolución comercial</h2><span class="srep-note mb-0" id="srepBranchEvoNote">Cinco principales</span></header>
			<div class="ded-chart-canvas-box"><canvas id="srepBranchEvo" aria-label="Evolución diaria de las sucursales"></canvas></div>
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
				@foreach($rows as $r)
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

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Tabla comparativa de sucursales</h2></header>
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable" data-export-name="Sucursales">
				<thead><tr>
					<th data-sort="num">Posición</th><th data-sort="text">Sucursal</th><th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Meta</th>
					<th class="text-right" data-sort="num">Cumplimiento</th><th class="text-right" data-sort="num">Transacciones</th><th class="text-right" data-sort="num">Ticket promedio</th>
					<th class="text-right" data-sort="num">Variación</th>
				</tr></thead>
				<tbody>
				@foreach($rows as $r)
					<tr data-srep-branch-row="{{ $r['id'] }}">
						<td data-v="{{ $r['rank'] }}"><span class="ded-rank-circle">{{ $r['rank'] }}</span></td>
						<td><div class="ded-prod-name">{{ $r['name'] }}</div></td>
						<td class="ded-table-price" data-v="{{ $r['net'] }}">{{ $fm2($r['net']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['goal'] ?? -1 }}">{{ $fm($r['goal']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['goal_pct'] ?? -1 }}">{{ $fp($r['goal_pct']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['tickets'] }}">{{ number_format($r['tickets']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['avg_ticket'] ?? -1 }}">{{ $fm2($r['avg_ticket']) }}</td>
						<td class="text-right" data-v="{{ $r['delta_pct'] ?? -99999 }}">{!! $badge($r['delta_pct']) !!}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
	</article>
	@endif
	@endif

	{{-- ================================================================ 5. HISTORIAL DE VENTAS --}}
	@if($section === 'history')
	@php
		$H = $R['history'];
		$mNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
		$monthLabel = fn($ym) => ($mNames[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 0, 4);
		$weLast = $H['weekend_last'];
		$weLy = $H['weekend_ly'];
	@endphp
	<section class="srep-kpis" aria-label="Indicadores históricos">
		{!! $kpi('wallet', 'Ventas del período', $fm($H['net']), number_format($H['tickets']) . ' transacciones', 'Ventas netas del período seleccionado', 'hist-net') !!}
		{!! $kpi('calendar-clock', 'Crecimiento interanual', $H['yoy']['pct'] === null ? $na : (($H['yoy']['pct'] >= 0 ? '+' : '') . number_format($H['yoy']['pct'], 1) . ' %'), $H['yoy']['net'] === null ? 'Sin ventas registradas ' . $fd($H['yoy']['from']) . ' – ' . $fd($H['yoy']['to']) : 'Año anterior: ' . $fm($H['yoy']['net']), 'Mismo período del año anterior', 'hist-yoy') !!}
		{!! $kpi('calendar-range', 'Variación mensual', $H['mom']['pct'] === null ? $na : (($H['mom']['pct'] >= 0 ? '+' : '') . number_format($H['mom']['pct'], 1) . ' %'), $h($H['mom']['label']) . ' vs. ' . $h($H['mom']['prev_label']), 'Mes en curso frente al mismo tramo del mes anterior', 'hist-mom') !!}
		{!! $kpi('star', 'Mejor día de ventas', $H['best_day'] ? $fd($H['best_day']['date']) : $na, $H['best_day'] ? $fm($H['best_day']['net']) : $na, 'Día con mayores ventas netas en el período', 'hist-best-day') !!}
		{!! $kpi('calendar-check', 'Mejor mes de ventas', $H['best_month'] ? $monthLabel($H['best_month']['month']) : $na, $H['best_month'] ? $fm($H['best_month']['net']) : $na, 'Mes con mayores ventas netas en todo el historial', 'hist-best-month') !!}
		{!! $kpi('bar-chart-3', 'Promedio diario', $fm($H['daily_avg']), 'por día del período', 'Ventas netas ÷ días del período', 'hist-avg') !!}
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
	@endif

	{{-- ================================================================ 6. RENTABILIDAD --}}
	@if($section === 'profitability')
	@php
		$PF = $R['profitability'];
		$bd = $PF['by_department'][0] ?? null;
		$bb = $PF['by_branch'][0] ?? null;
		$bp = $PF['top_products'][0] ?? null;
	@endphp
	@if(!$PF['reliable'])
	<article class="ded-card mb-3"><span class="ded-pill-red">Dato no confiable: el costo de ventas registrado supera 3 veces la venta. Revise los costos antes de usar la utilidad y el margen.</span></article>
	@endif
	<section class="srep-kpis" aria-label="Indicadores de rentabilidad">
		{!! $kpi('wallet', 'Utilidad bruta', $fm($PF['profit']), '<span>Ventas</span>&nbsp;' . $fm($PF['net']) . ' − <span>costo</span>&nbsp;' . $fm($PF['cogs']), 'Utilidad bruta = ventas netas − costo de mercancía vendida', 'pf-profit') !!}
		{!! $kpi('percent', 'Margen bruto', $fp($PF['margin_pct']), $PF['margin_delta_pp'] === null ? 'Anterior: ' . $na : $badge($PF['margin_delta_pp'], ' pp') . '&nbsp;vs. ' . $h($P['prev_name']), 'Margen bruto = utilidad bruta ÷ ventas netas × 100', 'pf-margin') !!}
		{!! $kpi('boxes', 'Departamento más rentable', $bd['name'] ?? $na, $bd ? $fm($bd['profit']) . ' · ' . $fp($bd['margin_pct']) : $na, 'Mayor utilidad bruta por departamento', 'pf-dept') !!}
		{!! $kpi('store', 'Sucursal más rentable', $bb['name'] ?? $na, $bb ? $fm($bb['profit']) . ' · ' . $fp($bb['margin_pct']) : $na, 'Mayor utilidad bruta por sucursal', 'pf-branch') !!}
		{!! $kpi('package-check', 'Producto con mayor utilidad', $bp['name'] ?? $na, $bp ? $fm($bp['profit']) . ' · ' . $fp($bp['margin_pct']) : $na, 'Mayor utilidad bruta por producto', 'pf-product') !!}
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

	<article class="ded-card mb-3">
		<header class="ded-card-header"><h2 class="ded-card-title">Productos con mayor utilidad bruta</h2></header>
		@if(!$PF['top_products'])
			<span class="ded-text-muted-italics">Sin información: no hay ventas de productos en el período.</span>
		@else
		<div class="srep-scroll">
			<table class="ded-table-top5 srep-sortable" data-export-name="Utilidad por producto">
				<thead><tr>
					<th data-sort="num">#</th><th data-sort="text">Producto</th><th data-sort="text">SKU</th><th class="text-right" data-sort="num">Unidades</th>
					<th class="text-right" data-sort="num">Ventas netas</th><th class="text-right" data-sort="num">Costo</th><th class="text-right" data-sort="num">Utilidad bruta</th><th class="text-right" data-sort="num">Margen</th>
				</tr></thead>
				<tbody>
				@foreach($PF['top_products'] as $i => $r)
					<tr>
						<td data-v="{{ $i + 1 }}"><span class="ded-rank-circle">{{ $i + 1 }}</span></td>
						<td><div class="ded-prod-name">{{ $r['name'] }}</div></td>
						<td><span class="ded-sku-badge">{{ $r['sku'] ?: '—' }}</span></td>
						<td class="ded-table-num" data-v="{{ $r['units'] }}">{{ number_format($r['units'], 0) }}</td>
						<td class="ded-table-price" data-v="{{ $r['net'] }}">{{ $fm2($r['net']) }}</td>
						<td class="ded-table-num" data-v="{{ $r['cogs'] }}">{{ $fm2($r['cogs']) }}</td>
						<td class="ded-table-price" data-v="{{ $r['profit'] ?? -1 }}">{{ $fm2($r['profit']) }}</td>
						<td class="ded-table-share" data-v="{{ $r['margin_pct'] ?? -999 }}">{{ $fp($r['margin_pct']) }}</td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
		@endif
		<p class="srep-note">Utilidad bruta = ventas netas − costo de mercancía vendida (costo FEFO del lote). Margen bruto = utilidad ÷ ventas netas × 100; sin ventas no se calcula.</p>
	</article>
	@endif

	@endif
</section>

@if(!empty($emitData) && $R)
<script type="application/json" id="srepData">{!! json_encode($R, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
