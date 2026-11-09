@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Reportes & Analítica')

@section('content')
@php
	$k = $kpiData;
	$periodLabels = ['day' => 'Hoy', 'week' => 'Esta Semana', 'month' => 'Este Mes', 'year' => 'Este Año'];
	$wasteRef = $censoShrink['benchmark']['shrink_on_sales_pct'];
	$otif = $k['procurement_stats']['avg_otif'];
	$hubKpis = [
		['Ventas Netas', '$' . number_format($k['net_sales'] ?? $k['total_sales'], 2), $k['total_tickets'] . ' tickets emitidos', 'fa-cash-register', '#ecfdf5', '#059669', $U('/sales'), 'success'],
		['Margen Bruto', number_format($k['margin_percentage'], 1) . '%', '$' . number_format($k['gross_margin'], 2) . ' utilidad bruta', 'fa-chart-pie', '#ecfdf5', '#059669', $U('/kpis'), 'success'],
		['Merma s/Ventas', number_format($k['waste_ratio_percentage'], 2) . '%', '$' . number_format($k['total_waste_cost'], 2) . ' · ref. sector ' . $wasteRef . '%', 'fa-trash-can', '#fef2f2', '#dc2626', $U('/waste'), 'danger'],
		['Desconocida', number_format($censoShrink['desconocida']['pct'], 1) . '%', 'del total · ref. ' . $censoShrink['benchmark']['desconocida_pct'] . '%', 'fa-circle-question', '#fffbeb', '#d97706', $U('/waste'), 'warning'],
		['Rotación', number_format($k['inventory_turnover'], 2) . 'x', 'CMV / inventario promedio', 'fa-arrows-rotate', '#f5f3ff', '#7c3aed', $U('/stockoverview'), 'purple'],
		['OTIF Proveedores', $otif === null ? '—' : number_format($otif, 1) . '%', $otif === null ? 'sin entregas registradas' : $k['procurement_stats']['pending_incidents'] . ' incidencias abiertas', 'fa-truck-fast', '#ecfeff', '#0891b2', $U('/suppliers'), 'info'],
	];
	$catalog = [
		['Mermas y Prevención', '#dc2626', 'fa-trash-can', [
			['Informe ejecutivo de pérdidas', 'Control 360: consolidado multisucursal para el Director de Mermas (diario 09:00)', '/reports/loss-executive'],
			['Tablero de mermas', 'Pérdida bruta, neta, recuperación y casos críticos', '/waste'],
			['Conocida vs. desconocida', 'Clasificación Censo FENALCO por causal', '/waste'],
			['Matriz de causales', 'Catálogo, riesgo, áreas responsables', '/waste/reasons'],
			['Bandeja de autorizaciones', 'Mermas pendientes de aprobación gerencial', '/waste/approvals'],
		]],
		['Ventas y Rentabilidad', '#059669', 'fa-sack-dollar', [
			['Panel ejecutivo', 'Ventas, margen, proyección y alertas IA', '/kpis'],
			['Historial de ventas', 'Tickets, costo (CMV) y margen por operación', '/sales'],
			['Clientes', 'Directorio y análisis comercial', '/customers'],
		]],
		['Inventario', 'var(--daily-primary, #00C83B)', 'fa-boxes-stacked', [
			['Resumen de inventario', 'Existencias, valuación y conciliación', '/stockoverview'],
			['Kardex / movimientos', 'Historial completo de transacciones', '/stockjournal'],
			['Resumen del kardex', 'Movimientos agregados por producto', '/stockjournal/summary'],
			['Gastos de compra', 'Distribución del gasto por producto y grupo', '/stockreports/spendings'],
		]],
		['Abastecimiento y Trazabilidad', '#1d4ed8', 'fa-truck-field', [
			['Trazabilidad de lotes', 'Seguimiento 360° de lotes, movimientos y traslados', '/traceability'],
			['Proveedores y OTIF', 'Cumplimiento de entregas, precios e incidencias', '/suppliers'],
			['Planificación MRP', 'Reabastecimiento sugerido, stock de seguridad y punto de reorden', '/mrp'],
		]],
		['Red de Sucursales', '#7c3aed', 'fa-shop', [
			['Scorecard de sucursales', 'Ventas, margen y merma por tienda', '/branches'],
			['Operaciones de piso', 'Checklists, barrido FEFO y averías', '/workforce'],
		]],
	];
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div class="dairy-page-icon is-purple"><i class="fa-solid fa-chart-pie"></i></div>
		<div>
			<h4 class="dairy-page-title">Reportes & Analítica</h4>
			<p class="dairy-page-subtitle">Indicadores transversales de ventas, mermas, inventario y abastecimiento, con el catálogo completo de informes. Período: {{ date('d/m/Y', strtotime($k['start_date'])) }} – {{ date('d/m/Y', strtotime($k['end_date'])) }}.</p>
		</div>
	</div>
	<div class="col-lg-5 dairy-page-actions">
		<div class="btn-group">
			@foreach($periodLabels as $pKey => $pLabel)
			<a href="{{ $U('/reports?period=' . $pKey) }}" class="btn {{ $selectedPeriod === $pKey ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $pLabel }}</a>
			@endforeach
		</div>
		<button class="btn btn-outline-secondary" onclick="window.print();"><i class="fa-solid fa-print mr-1"></i>Imprimir</button>
	</div>
</div>

<!-- FILA 1: 6 KPI TRANSVERSALES -->
@include('components.dairy_kpi_row', ['kpis' => array_map(function($h) { return ['label' => $h[0], 'value' => $h[1], 'sub' => $h[2], 'icon' => $h[3], 'tone' => $h[7], 'href' => $h[6]]; }, $hubKpis)])

<!-- MIS REPORTES: personalizables y programados (Etapa 34) -->
<div class="card mb-3" id="rb-card">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<span class="font-weight-bold"><i class="fa-solid fa-sliders text-primary mr-2"></i>Mis reportes · personalizables y programados</span>
		<button type="button" class="btn btn-sm btn-primary" id="rb-new"><i class="fa-solid fa-plus mr-1"></i>Nuevo reporte</button>
	</div>
	<div class="card-body">
		<form id="rb-form" class="d-none mb-3 border rounded p-3" autocomplete="off">
			<input type="hidden" id="rb-id">
			<div class="form-row">
				<div class="form-group col-md-4"><label for="rb-name" class="small font-weight-bold">Nombre</label><input id="rb-name" class="form-control form-control-sm" maxlength="120" required></div>
				<div class="form-group col-md-4"><label for="rb-dataset" class="small font-weight-bold">Datos</label><select id="rb-dataset" class="custom-select custom-select-sm"></select></div>
				<div class="form-group col-md-2"><label for="rb-period" class="small font-weight-bold">Período</label><select id="rb-period" class="custom-select custom-select-sm"></select></div>
				<div class="form-group col-md-2"><label for="rb-branch" class="small font-weight-bold">Sucursal</label><select id="rb-branch" class="custom-select custom-select-sm"><option value="">Todas las permitidas</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
			</div>
			<fieldset class="form-group mb-2"><legend class="small font-weight-bold mb-1">Columnas</legend><div id="rb-columns" class="d-flex flex-wrap dairy-gap-2"></div></fieldset>
			<div class="form-row">
				<div class="form-group col-md-3"><label for="rb-sort" class="small font-weight-bold">Ordenar por</label><select id="rb-sort" class="custom-select custom-select-sm"></select></div>
				<div class="form-group col-md-2"><label for="rb-dir" class="small font-weight-bold">Sentido</label><select id="rb-dir" class="custom-select custom-select-sm"><option value="desc">Descendente</option><option value="asc">Ascendente</option></select></div>
				<div class="form-group col-md-2"><label for="rb-limit" class="small font-weight-bold">Máx. filas</label><input id="rb-limit" type="number" min="1" max="5000" value="1000" class="form-control form-control-sm"></div>
				<div class="form-group col-md-3"><label for="rb-schedule" class="small font-weight-bold">Programación</label><select id="rb-schedule" class="custom-select custom-select-sm"></select></div>
				<div class="form-group col-md-2"><label for="rb-hour" class="small font-weight-bold">Hora</label><input id="rb-hour" type="number" min="0" max="23" value="7" class="form-control form-control-sm"></div>
			</div>
			<div class="d-flex flex-wrap dairy-gap-2">
				<button type="button" class="btn btn-sm btn-outline-primary" id="rb-preview"><i class="fa-solid fa-eye mr-1"></i>Vista previa</button>
				<button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-floppy-disk mr-1"></i>Guardar</button>
				<button type="button" class="btn btn-sm btn-light border" id="rb-cancel">Cancelar</button>
			</div>
			<p class="small text-muted mt-2 mb-0">Los programados se ejecutan con sus permisos y sucursales: al abrir esta página o con <code>php bin/dairy_scheduler.php</code> (Programador de tareas de Windows / cron). Cada ejecución guarda su CSV.</p>
			<div id="rb-preview-out" class="mt-3"></div>
		</form>
		<div class="table-responsive">
			<table class="table table-sm mb-2" id="rb-list">
				<thead><tr><th>Reporte</th><th>Datos</th><th>Período</th><th>Programación</th><th>Última ejecución</th><th class="text-right">Acciones</th></tr></thead>
				<tbody><tr><td colspan="6" class="text-muted text-center">Cargando…</td></tr></tbody>
			</table>
		</div>
		<details><summary class="small font-weight-bold">Ejecuciones recientes</summary>
			<div class="table-responsive"><table class="table table-sm mb-0" id="rb-runs"><thead><tr><th>Fecha</th><th>Reporte</th><th>Origen</th><th>Período</th><th class="text-right">Filas</th><th>Estado</th><th></th></tr></thead><tbody></tbody></table></div>
		</details>
	</div>
</div>
<!-- FILA 2: TENDENCIA VENTAS VS MERMA + COMPOSICIÓN CENSO -->
<div class="row">
	<div class="col-xl-8 mb-3">
		<div class="dairy-op-card">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<i class="fa-solid fa-chart-column text-primary mr-2"></i>
					<span class="font-weight-700 text-dark dairy-fs-md">Ventas vs. Merma y % de Merma</span>
				</div>
				<span class="text-muted dairy-fs-sm">Línea punteada: referencia sectorial {{ $wasteRef }}%</span>
			</div>
			<div class="dairy-op-body" style="height: 300px;">
				<canvas id="hub-trend-chart"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-4 mb-3">
		<div class="dairy-op-card">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<i class="fa-solid fa-magnifying-glass-chart text-danger mr-2"></i>
					<span class="font-weight-700 text-dark dairy-fs-md">Composición de la Merma</span>
				</div>
				<span class="dairy-pill-badge dairy-bg-subtle dairy-c-text-secondary">Censo FENALCO</span>
			</div>
			<div class="dairy-op-body">
				@php
					$allCats = [];
					foreach (['conocida' => '#059669', 'desconocida' => '#dc2626'] as $t => $col)
						foreach ($censoShrink[$t]['categories'] as $cat) $allCats[] = $cat + ['color' => $col, 'type' => $t];
					usort($allCats, fn($a, $b) => $b['cost'] <=> $a['cost']);
				@endphp
				<div class="d-flex mb-3 dairy-radius-xs overflow-hidden dairy-bg-subtle" style="height: 10px;">
					<div style="width: {{ $censoShrink['conocida']['pct'] }}%; background: var(--dairy-success);" title="Conocida"></div>
					<div style="width: {{ $censoShrink['desconocida']['pct'] }}%; background: var(--dairy-danger);" title="Desconocida"></div>
				</div>
				<div class="d-flex justify-content-between mb-3 dairy-fs-sm">
					<span><i class="fa-solid fa-circle mr-1 dairy-c-success dairy-fs-2xs"></i>Conocida <strong>{{ number_format($censoShrink['conocida']['pct'], 1) }}%</strong></span>
					<span><i class="fa-solid fa-circle mr-1 dairy-c-danger dairy-fs-2xs"></i>Desconocida <strong>{{ number_format($censoShrink['desconocida']['pct'], 1) }}%</strong></span>
				</div>
				@forelse($allCats as $cat)
				@php $catPct = $censoShrink['total_cost'] > 0 ? $cat['cost'] / $censoShrink['total_cost'] * 100 : 0; @endphp
				<div class="mb-2">
					<div class="d-flex justify-content-between dairy-fs-sm">
						<span class="text-dark">{{ $cat['label'] }}@if($cat['type'] === 'desconocida') <span class="text-danger dairy-fs-xs">· desconocida</span>@endif</span>
						<span class="font-weight-600">${{ number_format($cat['cost'], 2) }} <span class="text-muted font-weight-normal">{{ number_format($catPct, 1) }}%</span></span>
					</div>
					<div class="progress dairy-bg-subtle" style="height: 5px;">
						<div class="progress-bar" style="width: {{ $catPct }}%; background: {{ $cat['color'] }};"></div>
					</div>
				</div>
				@empty
				<div class="text-muted text-center py-4 dairy-fs-sm">Sin mermas aprobadas en el período.</div>
				@endforelse
			</div>
		</div>
	</div>
</div>

<!-- FILA 3: RANKING SUCURSALES + TOP PRODUCTOS CON MERMA + REBAJAS -->
<div class="row">
	<div class="col-xl-5 mb-3">
		<div class="dairy-op-card">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<i class="fa-solid fa-ranking-star text-primary mr-2"></i>
					<span class="font-weight-700 text-dark dairy-fs-md">Ranking de Sucursales</span>
				</div>
				<a href="{{ $U('/branches') }}" class="text-primary font-weight-600 dairy-fs-sm">Ver scorecard</a>
			</div>
			<div class="dairy-op-body" style="height: 260px;">
				<canvas id="hub-branch-chart"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-4 mb-3">
		<div class="dairy-op-card">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<i class="fa-solid fa-ranking-star text-danger mr-2"></i>
					<span class="font-weight-700 text-dark dairy-fs-md">Productos con Mayor Merma</span>
				</div>
			</div>
			<div class="dairy-op-body">
				@php $maxWaste = max(array_map(fn($p) => floatval($p->total_waste_cost), $k['top_waste_products']) ?: [0]); @endphp
				@forelse(array_slice($k['top_waste_products'], 0, 6) as $i => $p)
				<div class="d-flex align-items-center mb-2 dairy-fs-sm">
					<span class="hub-rank mr-2">{{ $i + 1 }}</span>
					<div class="flex-grow-1" style="min-width: 0;">
						<div class="d-flex justify-content-between">
							<span class="text-dark text-truncate mr-2">{{ $p->product_name }}</span>
							<span class="font-weight-600 text-danger">${{ number_format($p->total_waste_cost, 2) }}</span>
						</div>
						<div class="progress dairy-bg-subtle" style="height: 4px;">
							<div class="progress-bar" style="width: {{ $maxWaste > 0 ? $p->total_waste_cost / $maxWaste * 100 : 0 }}%; background: var(--dairy-danger);"></div>
						</div>
					</div>
				</div>
				@empty
				<div class="text-muted text-center py-4 dairy-fs-sm">Sin mermas en el período.</div>
				@endforelse
			</div>
		</div>
	</div>
	<div class="col-xl-3 mb-3">
		<div class="dairy-op-card">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<i class="fa-solid fa-tags text-warning mr-2"></i>
					<span class="font-weight-700 text-dark dairy-fs-md">Prevención Activa</span>
				</div>
			</div>
			<div class="dairy-op-body">
				@php
					$pw = $k['perishables_widget'];
					$mdRecoverable = array_sum(array_map(fn($m) => $m['lot_amount'] * $m['markdown_price'], $activeMarkdowns));
				@endphp
				<div class="hub-mini-stat" style="--hub-color: var(--dairy-danger);">
					<div class="text-muted">Lotes que vencen ≤ 3 días</div>
					<strong>{{ $pw['urgent_3d']['count'] }} lotes · ${{ number_format($pw['urgent_3d']['cost'], 2) }}</strong>
				</div>
				<div class="hub-mini-stat" style="--hub-color: var(--dairy-warning);">
					<div class="text-muted">Lotes que vencen en 4–7 días</div>
					<strong>{{ $pw['warning_7d']['count'] }} lotes · ${{ number_format($pw['warning_7d']['cost'], 2) }}</strong>
				</div>
				<div class="hub-mini-stat" style="--hub-color: var(--dairy-success);">
					<div class="text-muted">Rebajas FEFO activas en POS</div>
					<strong>{{ count($activeMarkdowns) }} productos · ${{ number_format($mdRecoverable, 2) }} recuperable</strong>
				</div>
				<a href="{{ $U('/waste#prevencion') }}" class="btn btn-outline-secondary btn-sm btn-block mt-2">Gestionar reglas de rebaja</a>
			</div>
		</div>
	</div>
</div>

<!-- FILA 4: CATÁLOGO DE INFORMES POR ÁREA -->
<div class="d-flex align-items-center justify-content-between mb-2 mt-1">
	<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-folder-tree text-primary mr-2"></i>Catálogo de Informes</span>
	<span class="text-muted dairy-fs-sm">Acceso directo a cada reporte operativo y financiero</span>
</div>
<div class="row" style="margin-left: -6px; margin-right: -6px;">
	@foreach($catalog as $area)
	<div class="col-xl col-lg-4 col-md-6 mb-3" style="padding-left: 6px; padding-right: 6px;">
		<div class="dairy-op-card hub-catalog" style="--hub-color: {{ $area[1] }};">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<div class="dairy-kpi-icon-box mr-2" style="background: color-mix(in srgb, {{ $area[1] }} 12%, var(--dairy-surface));"><i class="fa-solid {{ $area[2] }}" style="color: {{ $area[1] }};"></i></div>
					<span class="font-weight-700 text-dark dairy-fs-base">{{ $area[0] }}</span>
				</div>
			</div>
			<div class="py-1">
				@foreach($area[3] as $rep)
				<a href="{{ $U($rep[2]) }}" class="hub-report-link">
					<div>
						<div class="font-weight-600 text-dark">{{ $rep[0] }}</div>
						<div class="text-muted">{{ $rep[1] }}</div>
					</div>
					<i class="fa-solid fa-chevron-right"></i>
				</a>
				@endforeach
			</div>
		</div>
	</div>
	@endforeach
</div>
@stop

@push('pageScripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
	var series = {!! json_encode(array_values($k['time_series'])) !!};
	var ref = {{ $wasteRef }};
	var labels = series.map(function(x) { return x.label || x.date; });
	var sales = series.map(function(x) { return parseFloat(x.sales) || 0; });
	var waste = series.map(function(x) { return parseFloat(x.waste) || 0; });
	var pct = sales.map(function(s, i) { return s > 0 ? +(waste[i] / s * 100).toFixed(2) : null; });
	var money = function(v) { return '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }); };

	var trend = document.getElementById('hub-trend-chart');
	if (trend) {
		new Chart(trend, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{ type: 'line', label: '% Merma s/ventas', data: pct, borderColor: '#d97706', backgroundColor: '#d97706', fill: false, lineTension: 0.35, pointRadius: 2, spanGaps: true, yAxisID: 'pct' },
					{ type: 'line', label: 'Ref. sector', data: labels.map(function() { return ref; }), borderColor: '#94a3b8', borderDash: [5, 4], fill: false, pointRadius: 0, borderWidth: 1.5, yAxisID: 'pct' },
					{ label: 'Ventas', data: sales, backgroundColor: 'rgba(0, 142, 72, 0.75)', yAxisID: 'money' },
					{ label: 'Merma', data: waste, backgroundColor: 'rgba(239,68,68,0.8)', yAxisID: 'money' }
				]
			},
			options: {
				maintainAspectRatio: false,
				tooltips: { mode: 'index', intersect: false },
				legend: { position: 'top', align: 'end', labels: { boxWidth: 10, fontSize: 11 } },
				scales: {
					xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10 }, barPercentage: 0.8, categoryPercentage: 0.7 }],
					yAxes: [
						{ id: 'money', position: 'left', ticks: { beginAtZero: true, callback: money, fontSize: 10 }, gridLines: { color: '#f1f5f9' } },
						{ id: 'pct', position: 'right', ticks: { beginAtZero: true, callback: function(v) { return v + '%'; }, fontSize: 10 }, gridLines: { display: false } }
					]
				}
			}
		});
	}

	var branches = {!! json_encode(array_map(fn($s) => ['name' => $s['name'], 'sales' => floatval($s['net_sales']), 'waste_pct' => !empty($s['has_data']) ? floatval($s['waste_pct']) : null], $scorecards)) !!};
	var bc = document.getElementById('hub-branch-chart');
	if (bc) {
		new Chart(bc, {
			type: 'horizontalBar',
			data: {
				labels: branches.map(function(b) { return b.name; }),
				datasets: [
					{ label: 'Ventas netas', data: branches.map(function(b) { return b.sales; }), backgroundColor: 'rgba(71, 85, 105, 0.55)', xAxisID: 'money' },
					{ label: '% Merma', data: branches.map(function(b) { return b.waste_pct; }), backgroundColor: branches.map(function(b) { return b.waste_pct <= ref ? '#059669' : (b.waste_pct <= 2.5 ? '#d97706' : '#dc2626'); }), xAxisID: 'pct' }
				]
			},
			options: {
				maintainAspectRatio: false,
				legend: { position: 'top', align: 'end', labels: { boxWidth: 10, fontSize: 11 } },
				scales: {
					xAxes: [
						{ id: 'money', type: 'linear', position: 'bottom', ticks: { beginAtZero: true, callback: money, fontSize: 10 }, gridLines: { color: '#f1f5f9' } },
						{ id: 'pct', type: 'linear', position: 'top', ticks: { beginAtZero: true, callback: function(v) { return v + '%'; }, fontSize: 10 }, gridLines: { display: false } }
					],
					yAxes: [{ gridLines: { display: false }, ticks: { fontSize: 11 } }]
				}
			}
		});
	}
});
</script>
@endpush
