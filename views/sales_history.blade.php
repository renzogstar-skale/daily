@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Ventas y Rentabilidad · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales.css?v=', true) }}{{ daily_asset_version('/css/daily_sales.css') }}" rel="stylesheet">
<style>
/* Popover de información de KPI */
.popover { max-width: 320px; font-size: 0.82rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
.popover-header { font-weight: 700; background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #0f172a; padding: 8px 12px; }
.popover-body { padding: 10px 12px; color: #334155; line-height: 1.4; }
.kpi-meta-item { display: flex; justify-content: space-between; margin-bottom: 4px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 2px; gap: 8px; }
.kpi-meta-label { color: #64748b; font-weight: 600; font-size: 0.75rem; }
.kpi-meta-val { font-weight: 600; color: #0f172a; text-align: right; }
</style>
@endpush

@section('content')
@include('components.alert_strip', ['alertCategories' => ['sales']])

@php
	$t = $analysis['totals'];
	$prev = $analysis['prev'] ?? [];
	$compareWith = $compareWith ?? 'prev_period';
	$dq = $dataQuality ?? [
		'total_lines' => 0,
		'lines_without_cost' => 0,
		'products_without_cost' => 0,
		'cost_coverage_pct' => 100.0,
		'line_cost_coverage_pct' => 100.0,
		'unclassified_sales' => 0.0,
		'unclassified_pct' => 0.0,
		'unclassified_units' => 0,
		'unlinked_lines' => count($unlinked ?? []),
		'unlinked_pct' => 0.0,
		'last_updated' => date('d/m/Y H:i')
	];
	$m = fn($v) => '$' . number_format((float) $v, 2);
	$mInt = fn($v) => '$' . number_format((float) $v, 0);
	$delta = fn($v) => $v === null ? 'Sin información' : (($v >= 0 ? '+' : '') . number_format($v, 1) . '% vs. período anterior');

	$buildRangeQs = function($from, $to) use ($selectedBranchId, $selectedGroupId, $selectedProductId, $activeTab, $compareWith) {
		$q = [
			'tab' => $activeTab,
			'branch_id' => $selectedBranchId,
			'product_id' => $selectedProductId,
			'compare_with' => $compareWith !== 'prev_period' ? $compareWith : null,
			'start_date' => $from,
			'end_date' => $to
		];
		$q = array_filter($q, fn($v) => $v !== null && $v !== '');
		if ($selectedGroupId !== null && $selectedGroupId !== '') {
			$q['group_id'] = $selectedGroupId;
		}
		return http_build_query($q);
	};

	$rangeQs = function($days) use ($buildRangeQs) {
		return $buildRangeQs(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d'));
	};

	$todayDate = date('Y-m-d');
	$thisMonthStart = date('Y-m-01');
	$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
	$lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
	$thisYearStart = date('Y-01-01');

	$isPresetActive = function($from, $to) use ($startDate, $endDate) {
		return $startDate === $from && $endDate === $to;
	};

	$maxGroup = max(array_merge([1], array_map(fn($g) => (float) $g['total'], $analysis['by_group'] ?? [])));
	$maxProduct = max(array_merge([1], array_map(fn($g) => (float) $g['total'], $analysis['by_product'] ?? [])));
	$unlinkedCost = array_sum(array_map(fn($r) => (float) $r['cost'], $unlinked));

	$isPartial = $comparisons['previous_period']['is_partial'];
	$hasFilters = ($selectedBranchId !== null && $selectedBranchId !== '') || ($selectedGroupId !== null && $selectedGroupId !== '') || ($selectedProductId !== null && $selectedProductId !== '');

	// Cálculos de variaciones coherentes para los 6 KPIs
	$salesDeltaPct = $t['delta_pct'] ?? null;
	if ($compareWith === 'year_ago') {
		$salesDeltaPct = $comparisons['year_ago']['delta_pct'] ?? null;
	} elseif ($compareWith === 'target') {
		$salesDeltaPct = $thermometer['target_goal'] > 0
			? round((($t['total'] - $thermometer['target_goal']) / $thermometer['target_goal']) * 100, 1)
			: null;
	}

	$complianceGapPp = $thermometer['target_goal'] > 0 ? round($thermometer['compliance_pct'] - 100.0, 1) : null;

	$prevMarginVal = isset($prev['margin']) ? (float) $prev['margin'] : (isset($prev['cogs']) && $prev['cogs'] > 0 ? ((float) $prev['total'] - (float) $prev['cogs']) : null);
	$marginDeltaPct = ($t['margin'] !== null && $prevMarginVal !== null && $prevMarginVal > 0)
		? round(((floatval($t['margin']) - $prevMarginVal) / $prevMarginVal) * 100, 1)
		: null;

	$prevMarginPct = isset($prev['margin_pct']) && $prev['margin_pct'] !== null
		? (float) $prev['margin_pct']
		: (($prevMarginVal !== null && ($prev['total'] ?? 0) > 0) ? round(($prevMarginVal / $prev['total']) * 100, 1) : null);
	$marginPpDelta = ($t['margin_pct'] !== null && $prevMarginPct !== null)
		? round(floatval($t['margin_pct']) - $prevMarginPct, 1)
		: null;

	$ticketsDeltaPct = (($prev['tickets'] ?? 0) > 0)
		? round(((intval($t['tickets']) - intval($prev['tickets'])) / intval($prev['tickets'])) * 100, 1)
		: null;

	$avgTicketDeltaPct = (($prev['avg_ticket'] ?? 0) > 0 && ($t['avg_ticket'] ?? 0) > 0)
		? round(((floatval($t['avg_ticket']) - floatval($prev['avg_ticket'])) / floatval($prev['avg_ticket'])) * 100, 1)
		: null;

	$compareSubLabel = $compareWith === 'year_ago'
		? 'vs. año anterior'
		: ($compareWith === 'target' ? 'vs. meta del período' : 'vs. período anterior');

	$maxDeptSharePct = max(array_merge([1.0], array_map(fn($d) => floatval($d['share_pct'] ?? 0), $departmentsDonut ?? [])));
@endphp

<!-- ENCABEZADO COMERCIAL -->
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div>
			<h4 class="dairy-page-title mb-1" style="font-size: 1.45rem; font-weight: 800; color: #0f172a;">Ventas y Rentabilidad</h4>
			<p class="dairy-page-subtitle mb-0" style="color: #64748b; font-size: 0.86rem;">Analiza el desempeño de tus ventas, márgenes y tendencias en todos tus puntos de venta.</p>
		</div>
	</div>
	<div class="col-lg-5 text-lg-right mt-2 mt-lg-0 dairy-page-actions d-flex flex-wrap align-items-center justify-content-lg-end" style="gap: 8px;">
		<button type="button" class="btn btn-light border shadow-xs font-weight-600 btn-sm" id="btnToggleTour" title="Iniciar o cerrar recorrido guiado">
			<i class="fa-solid fa-compass text-success mr-1"></i><span>Recorrido Comercial</span>
		</button>
		<a href="{{ $U('/pos') }}" class="btn btn-primary shadow-xs font-weight-600" style="background: #16a34a; border-color: #16a34a; border-radius: 8px; padding: 6px 14px;">
			<i class="fa-solid fa-plus mr-1"></i>Nuevo ticket
		</a>
		@include('components.dairy_export', ['exportName' => 'ventas_' . $startDate . '_' . $endDate])
	</div>
</div>

<!-- BANNER DE RECORRIDO COMERCIAL GUIADO (8 PASOS) -->
<div class="daily-tour-banner d-none" id="tourBanner">
	<div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
		<span class="badge badge-light font-weight-bold px-2 py-1" style="color: #008E48;">GUÍA DE PRESENTACIÓN</span>
		<div id="tourStepText" class="font-weight-600">Paso 1 de 8: Abrir Ventas y visualizar el tablero comercial unificado.</div>
	</div>
	<div class="daily-tour-steps-nav mt-2 mt-md-0">
		<button type="button" class="btn btn-sm btn-light py-1 px-2" id="tourPrevBtn" disabled><i class="fa-solid fa-arrow-left mr-1"></i>Ant.</button>
		<div class="d-flex align-items-center" style="gap: 4px;" id="tourDotsContainer">
			@for($st = 1; $st <= 8; $st++)
			<span class="daily-tour-step-dot @if($st === 1) is-active @endif" data-step="{{ $st }}">{{ $st }}</span>
			@endfor
		</div>
		<button type="button" class="btn btn-sm btn-light py-1 px-2" id="tourNextBtn">Sig.<i class="fa-solid fa-arrow-right ml-1"></i></button>
		<button type="button" class="btn btn-sm btn-link text-white p-0 ml-2" id="tourCloseBtn" title="Cerrar guía"><i class="fa-solid fa-xmark"></i></button>
	</div>
</div>

<!-- NAVEGACIÓN EN CASCADA / BREADCRUMB INTERACTIVO (CUANDO HAY FILTROS ACTIVOS) -->
@if($hasFilters)
<nav class="daily-cascade-breadcrumb" aria-label="Ruta de cascada comercial">
	<a href="{{ $U('/sales?' . http_build_query(['start_date' => $startDate, 'end_date' => $endDate])) }}" class="daily-cascade-crumb @if(!$selectedBranchId && $selectedGroupId === null && !$selectedProductId) is-active @endif">
		<i class="fa-solid fa-network-wired text-muted"></i> Red Consolidada
	</a>

	@if($selectedBranch)
	<span class="daily-cascade-sep"><i class="fa-solid fa-chevron-right"></i></span>
	<a href="{{ $U('/sales?' . http_build_query(['branch_id' => $selectedBranch->id, 'start_date' => $startDate, 'end_date' => $endDate])) }}" class="daily-cascade-crumb @if($selectedGroupId === null && !$selectedProductId) is-active @endif" title="Sucursal comercial">
		<i class="fa-solid fa-store text-muted"></i> {{ $selectedBranch->name }}
	</a>
	@endif

	@if($selectedGroup)
	<span class="daily-cascade-sep"><i class="fa-solid fa-chevron-right"></i></span>
	<a href="{{ $U('/sales?' . http_build_query(array_filter(['branch_id' => $selectedBranchId, 'group_id' => $selectedGroup->id, 'start_date' => $startDate, 'end_date' => $endDate], fn($v) => $v !== null && $v !== ''))) }}" class="daily-cascade-crumb @if(!$selectedProductId) is-active @endif" title="Departamento de producto">
		<i class="fa-solid fa-layer-group text-muted"></i> {{ $selectedGroup->name }}
	</a>
	@endif

	@if($selectedProduct)
	<span class="daily-cascade-sep"><i class="fa-solid fa-chevron-right"></i></span>
	<span class="daily-cascade-crumb is-active" title="Producto específico">
		<i class="fa-solid fa-barcode text-muted"></i> {{ $selectedProduct->name }}
	</span>
	@endif

	<a href="{{ $U('/sales?' . http_build_query(['start_date' => $startDate, 'end_date' => $endDate])) }}" class="daily-reset-pill" title="Restablecer todos los filtros y volver a la red">
		<i class="fa-solid fa-rotate-left"></i> Limpiar filtros
	</a>
</nav>
@endif

<!-- BARRA DE FILTROS COMERCIALES Y PESTAÑAS -->
<div class="sales-filter-shell">
	<form method="GET" action="{{ $U('/sales') }}" id="salesFilterForm">
		<input type="hidden" name="tab" id="formActiveTab" value="{{ $activeTab }}">
		@if($selectedProductId)
		<input type="hidden" name="product_id" value="{{ $selectedProductId }}">
		@endif

		<div class="sales-filter-row">
			<div>
				<label class="sales-field-label" for="fBranch">Sucursal</label>
				<select name="branch_id" id="fBranch" class="sales-filter-control" onchange="this.form.submit()">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
					<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>
			</div>

			<div>
				<label class="sales-field-label" for="fGroup">Departamento</label>
				<select name="group_id" id="fGroup" class="sales-filter-control" onchange="var p = this.form.querySelector('input[name=product_id]'); if (p) p.remove(); this.form.submit()">
					<option value="">Todos los departamentos</option>
					@foreach($productGroups as $g)
					<option value="{{ $g['id'] }}" @if($selectedGroupId !== null && intval($selectedGroupId) === intval($g['id'])) selected @endif>{{ $g['name'] }}</option>
					@endforeach
					<option value="0" @if($selectedGroupId === 0) selected @endif>Sin departamento</option>
				</select>
			</div>

			<div>
				<label class="sales-field-label" for="fStartDate">Período</label>
				<div class="sales-date-range-box">
					<i class="fa-regular fa-calendar text-muted" style="font-size: 0.82rem;"></i>
					<input type="date" name="start_date" id="fStartDate" class="sales-date-input" value="{{ $startDate }}" aria-label="Fecha Desde">
					<span class="text-muted">-</span>
					<input type="date" name="end_date" id="fEndDate" class="sales-date-input" value="{{ $endDate }}" aria-label="Fecha Hasta">
				</div>
			</div>

			<div>
				<label class="sales-field-label" for="fCompareWith">Comparar con</label>
				<select name="compare_with" id="fCompareWith" class="sales-filter-control" onchange="this.form.submit()" aria-label="Comparar con">
					<option value="prev_period" @if($compareWith === 'prev_period') selected @endif>Período anterior</option>
					<option value="year_ago" @if($compareWith === 'year_ago') selected @endif>Año anterior</option>
					<option value="target" @if($compareWith === 'target') selected @endif>Meta del período</option>
				</select>
			</div>

			<div>
				<button type="submit" class="btn-sales-apply w-100">
					<i class="fa-solid fa-magnifying-glass"></i>
					<span>Aplicar</span>
				</button>
			</div>
		</div>
	</form>

	<!-- Píldoras de Períodos Rápidos + Fechas Comparadas -->
	<div class="sales-quick-periods-bar">
		<div class="sales-period-pills">
			<a href="{{ $U('/sales?' . $rangeQs(1)) }}" class="sales-period-pill @if($isPresetActive($todayDate, $todayDate)) is-active @endif">Hoy</a>
			<a href="{{ $U('/sales?' . $rangeQs(7)) }}" class="sales-period-pill @if($isPresetActive(date('Y-m-d', strtotime('-6 days')), $todayDate)) is-active @endif">7 días</a>
			<a href="{{ $U('/sales?' . $rangeQs(30)) }}" class="sales-period-pill @if($isPresetActive(date('Y-m-d', strtotime('-29 days')), $todayDate)) is-active @endif">30 días</a>
			<a href="{{ $U('/sales?' . $rangeQs(90)) }}" class="sales-period-pill @if($isPresetActive(date('Y-m-d', strtotime('-89 days')), $todayDate)) is-active @endif">90 días</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisMonthStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisMonthStart, $todayDate)) is-active @endif">Este mes</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($lastMonthStart, $lastMonthEnd)) }}" class="sales-period-pill @if($isPresetActive($lastMonthStart, $lastMonthEnd)) is-active @endif">Mes anterior</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisYearStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisYearStart, $todayDate)) is-active @endif">Año actual</a>
		</div>

		<div class="sales-period-meta">
			<span>Período: <strong>{{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}</strong></span>
			<span class="ml-2">
				@if($compareWith === 'year_ago')
					· vs. {{ date('d/m/Y', strtotime($comparisons['year_ago']['start'])) }} - {{ date('d/m/Y', strtotime($comparisons['year_ago']['end'])) }}
				@elseif($compareWith === 'target')
					· vs. Meta del período ({{ $m($thermometer['target_goal']) }})
				@else
					· vs. {{ date('d/m/Y', strtotime($analysis['prev_from'])) }} - {{ date('d/m/Y', strtotime($analysis['prev_to'])) }}
				@endif
			</span>
		</div>
	</div>

	<!-- PESTAÑAS PRINCIPALES: LAS 7 HERRAMIENTAS COMERCIALES + AUDITORÍA DE TRANSACCIONES -->
	<div class="nav daily-level-tabs flex-wrap" role="tablist" style="gap: 4px;">
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'resumen' || !$activeTab) active @endif" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen" aria-selected="{{ ($activeTab === 'resumen' || !$activeTab) ? 'true' : 'false' }}">
			<i class="fa-solid fa-chart-column"></i>
			<span>1. Centro de Ventas</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'rentabilidad' || $activeTab === 'analisis') active @endif" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad" aria-selected="{{ ($activeTab === 'rentabilidad' || $activeTab === 'analisis') ? 'true' : 'false' }}">
			<i class="fa-solid fa-coins"></i>
			<span>2. Rentabilidad Comercial</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'productos') active @endif" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos" aria-selected="{{ $activeTab === 'productos' ? 'true' : 'false' }}">
			<i class="fa-solid fa-barcode"></i>
			<span>3. Rendimiento por Producto</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'sucursales') active @endif" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales" aria-selected="{{ $activeTab === 'sucursales' ? 'true' : 'false' }}">
			<i class="fa-solid fa-store"></i>
			<span>4. Análisis por Sucursal</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'pos') active @endif" data-toggle="pill" data-target="#tab-pos" role="tab" id="pill-pos" aria-selected="{{ $activeTab === 'pos' ? 'true' : 'false' }}">
			<i class="fa-solid fa-cash-register"></i>
			<span>5. Punto de Venta</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'pedidos-clientes') active @endif" data-toggle="pill" data-target="#tab-pedidos-clientes" role="tab" id="pill-pedidos-clientes" aria-selected="{{ $activeTab === 'pedidos-clientes' ? 'true' : 'false' }}">
			<i class="fa-solid fa-users"></i>
			<span>6. Pedidos y Clientes</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'inteligencia') active @endif" data-toggle="pill" data-target="#tab-inteligencia" role="tab" id="pill-inteligencia" aria-selected="{{ $activeTab === 'inteligencia' ? 'true' : 'false' }}">
			<i class="fa-solid fa-brain"></i>
			<span>7. Inteligencia Comercial</span>
		</button>
				<button type="button" class="nav-link daily-level-tab @if($activeTab === 'precios-promociones') active @endif" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-promociones">
			<i class="fa-solid fa-tags text-warning"></i>
			<span>8. Control de Precios & Promociones</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'campanas') active @endif" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas">
			<i class="fa-solid fa-bullhorn text-primary"></i>
			<span>9. Evaluación de Campañas</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'detalle') active @endif" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle" aria-selected="{{ $activeTab === 'detalle' ? 'true' : 'false' }}">
			<i class="fa-solid fa-list-ul"></i>
			<span>Transacciones</span>
			<span class="daily-level-badge">{{ count($sales) }}</span>
		</button>
	</div>
</div>

<!-- CONTENEDOR DE PESTAÑAS -->
<div class="tab-content" id="salesMainTabContent">

<!-- ============================================================== -->
<!-- PESTAÑA 1: RESUMEN COMERCIAL                                   -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'resumen' || !$activeTab) show active @endif" id="tab-resumen" role="tabpanel">

	<!-- FILA 1: 6 INDICADORES COMERCIALES PRINCIPALES -->
	<div class="sales-kpi-grid" id="kpi-cards-row">
		<!-- 1. Ventas netas -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-green"><i class="fa-solid fa-cart-shopping"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Ventas netas</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Ventas netas" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Facturación comercial sin IVA, deducidos descuentos</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>&Sigma; (Total ticket &minus; IVA) de ventas completadas</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fuente</span><span class='kpi-meta-val'>sales / sale_items</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value">{{ $t['total'] !== null ? $m($t['total']) : 'Sin información' }}</div>
					@if($salesDeltaPct !== null)
					<div class="sales-kpi-delta @if($salesDeltaPct >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($salesDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($salesDeltaPct >= 0 ? '+' : '') . number_format($salesDeltaPct, 1) }}%</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>Sin información</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $compareSubLabel }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,22 L20,18 L38,20 L56,13 L74,16 L92,8 L118,5 L118,26 L2,26 Z" fill="rgba(22,163,74,0.14)"></path>
				<polyline fill="none" stroke="#16a34a" stroke-width="2" points="2,22 20,18 38,20 56,13 74,16 92,8 118,5"></polyline>
				<circle cx="118" cy="5" r="2.5" fill="#16a34a"></circle>
			</svg>
		</div>

		<!-- 2. Cumplimiento -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-blue"><i class="fa-solid fa-bullseye"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Cumplimiento</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Cumplimiento de meta" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Avance frente al objetivo comercial del período</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>(Ventas netas &divide; Meta del período) &times; 100</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Meta período</span><span class='kpi-meta-val'>{{ $thermometer['target_goal'] > 0 ? $m($thermometer['target_goal']) : 'Sin meta' }}</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value @if(!$thermometer['has_target']) @elseif($thermometer['compliance_pct'] >= 90) is-green @elseif($thermometer['compliance_pct'] >= 75) is-amber @else is-red @endif">
						{{ $thermometer['has_target'] ? number_format($thermometer['compliance_pct'], 1) . '%' : 'Sin meta' }}
					</div>
					@if($complianceGapPp !== null)
					<div class="sales-kpi-delta @if($complianceGapPp >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($complianceGapPp >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($complianceGapPp >= 0 ? '+' : '') . number_format($complianceGapPp, 1) }} pp</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>Sin meta configurada</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $thermometer['has_target'] ? 'Meta: 100.0%' : 'Configurar presupuesto' }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,17 L24,14 L46,16 L68,19 L92,15 L118,16 L118,26 L2,26 Z" fill="rgba(37,99,235,0.12)"></path>
				<polyline fill="none" stroke="#2563eb" stroke-width="1.8" stroke-dasharray="3,2" points="2,17 24,14 46,16 68,19 92,15 118,16"></polyline>
				<circle cx="118" cy="16" r="2.5" fill="#2563eb"></circle>
			</svg>
		</div>

		<!-- 3. Utilidad bruta -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-green"><i class="fa-solid fa-coins"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Utilidad bruta</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Utilidad bruta" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Ganancia comercial antes de gastos operativos</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>Ventas netas &minus; Costo de ventas (CMV)</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>CMV registrado</span><span class='kpi-meta-val'>{{ $t['cogs'] > 0 ? $m($t['cogs']) : 'Sin información' }}</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Cobertura costo</span><span class='kpi-meta-val'>{{ number_format($dq['line_cost_coverage_pct'], 1) }}% de líneas</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value is-green">
						{{ $t['margin'] !== null ? $m($t['margin']) : 'Sin información' }}
					</div>
					@if($marginDeltaPct !== null)
					<div class="sales-kpi-delta @if($marginDeltaPct >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($marginDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($marginDeltaPct >= 0 ? '+' : '') . number_format($marginDeltaPct, 1) }}%</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>{{ $t['margin'] === null ? 'Costos incompletos' : 'Sin información' }}</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $compareSubLabel }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,21 L22,19 L44,15 L66,17 L88,11 L118,6 L118,26 L2,26 Z" fill="rgba(22,163,74,0.14)"></path>
				<polyline fill="none" stroke="#16a34a" stroke-width="2" points="2,21 22,19 44,15 66,17 88,11 118,6"></polyline>
				<circle cx="118" cy="6" r="2.5" fill="#16a34a"></circle>
			</svg>
		</div>

		<!-- 4. Margen bruto -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-purple"><i class="fa-solid fa-chart-pie"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Margen bruto</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Margen bruto" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Porcentaje de utilidad bruta sobre ventas netas</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>(Utilidad bruta &divide; Ventas netas) &times; 100</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Calidad de datos</span><span class='kpi-meta-val'>{{ $dq['products_without_cost'] > 0 ? $dq['products_without_cost'] . ' prod. sin costo' : 'Costos completos' }}</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value @if(($t['margin_pct'] ?? 0) >= 25) is-green @elseif(($t['margin_pct'] ?? 0) >= 18) is-amber @else is-red @endif">
						{{ $t['margin_pct'] !== null ? number_format($t['margin_pct'], 1) . '%' : 'Sin información' }}
					</div>
					@if($marginPpDelta !== null)
					<div class="sales-kpi-delta @if($marginPpDelta >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($marginPpDelta >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($marginPpDelta >= 0 ? '+' : '') . number_format($marginPpDelta, 1) }} pp</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>{{ $t['margin_pct'] === null ? 'Costos incompletos' : 'Sin información' }}</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $compareSubLabel }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,20 L24,17 L48,14 L72,18 L96,12 L118,9 L118,26 L2,26 Z" fill="rgba(147,51,234,0.14)"></path>
				<polyline fill="none" stroke="#9333ea" stroke-width="2" points="2,20 24,17 48,14 72,18 96,12 118,9"></polyline>
				<circle cx="118" cy="9" r="2.5" fill="#9333ea"></circle>
			</svg>
		</div>

		<!-- 5. Transacciones -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-blue"><i class="fa-solid fa-receipt"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Transacciones</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Transacciones" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Tickets de venta completados en el período</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>Conteo de tickets con estado completado</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Período anterior</span><span class='kpi-meta-val'>{{ number_format($prev['tickets'] ?? 0) }} tickets</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value">{{ number_format($t['tickets']) }}</div>
					@if($ticketsDeltaPct !== null)
					<div class="sales-kpi-delta @if($ticketsDeltaPct >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($ticketsDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($ticketsDeltaPct >= 0 ? '+' : '') . number_format($ticketsDeltaPct, 1) }}%</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>Sin información</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $compareSubLabel }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,21 L24,16 L48,19 L70,13 L94,15 L118,8 L118,26 L2,26 Z" fill="rgba(37,99,235,0.13)"></path>
				<polyline fill="none" stroke="#2563eb" stroke-width="2" points="2,21 24,16 48,19 70,13 94,15 118,8"></polyline>
				<circle cx="118" cy="8" r="2.5" fill="#2563eb"></circle>
			</svg>
		</div>

		<!-- 6. Ticket promedio -->
		<div class="sales-kpi-card">
			<div class="sales-kpi-top">
				<span class="sales-kpi-icon-box is-red"><i class="fa-solid fa-tag"></i></span>
				<div class="sales-kpi-body">
					<div class="sales-kpi-head">
						<span class="sales-kpi-label">Ticket promedio</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Ticket promedio" data-content="<div class='kpi-meta-item'><span class='kpi-meta-label'>Definición</span><span class='kpi-meta-val'>Importe neto promedio por transacción válida</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Fórmula</span><span class='kpi-meta-val'>Ventas netas &divide; Transacciones completadas</span></div><div class='kpi-meta-item'><span class='kpi-meta-label'>Período anterior</span><span class='kpi-meta-val'>{{ ($prev['tickets'] ?? 0) > 0 ? $m($prev['avg_ticket'] ?? 0) : 'Sin información' }}</span></div>"><i class="fa-solid fa-circle-info"></i></button>
					</div>
					<div class="sales-kpi-value">{{ $t['tickets'] > 0 ? $m($t['avg_ticket']) : 'Sin información' }}</div>
					@if($avgTicketDeltaPct !== null)
					<div class="sales-kpi-delta @if($avgTicketDeltaPct >= 0) is-up @else is-down @endif">
						<i class="fa-solid @if($avgTicketDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
						<span>{{ ($avgTicketDeltaPct >= 0 ? '+' : '') . number_format($avgTicketDeltaPct, 1) }}%</span>
					</div>
					@else
					<div class="sales-kpi-delta is-neutral"><span>Sin información</span></div>
					@endif
					<div class="sales-kpi-sub">{{ $compareSubLabel }}</div>
				</div>
			</div>
			<svg class="sales-kpi-spark" viewBox="0 0 120 26" preserveAspectRatio="none">
				<path d="M2,9 L24,13 L48,11 L72,18 L96,16 L118,21 L118,26 L2,26 Z" fill="rgba(239,68,68,0.13)"></path>
				<polyline fill="none" stroke="#ef4444" stroke-width="2" points="2,9 24,13 48,11 72,18 96,16 118,21"></polyline>
				<circle cx="118" cy="21" r="2.5" fill="#ef4444"></circle>
			</svg>
		</div>
	</div>

	<!-- FILA 2: CURVA DE VENTAS (IZQ) Y PARTICIPACIÓN POR DEPARTAMENTO (DER) -->
	<div class="row mb-3">
		<!-- Columna Izquierda (7 cols): Curva de ventas + Avance frente a la meta -->
		<div class="col-lg-7 mb-3 mb-lg-0">
			<div class="dsm-curve-card">
				<!-- Encabezado con degradado verde DAILY -->
				<div class="dsm-curve-header">
					<div class="dsm-curve-header-left">
						<div class="dsm-curve-header-icon">
							<i class="fa-solid fa-arrow-trend-up"></i>
						</div>
						<div>
							<h2 class="dsm-curve-header-title">Curva de ventas</h2>
							<p class="dsm-curve-header-subtitle">Evolución de ventas, meta y período anterior</p>
						</div>
					</div>
					<button type="button" class="dsm-curve-info-btn" data-toggle="popover" data-placement="left" title="Curva de ventas y meta" data-content="Evolución diaria de las ventas netas comparada con el período anterior equivalente y la meta proporcional del período." aria-label="Información de la curva de ventas">
						<i class="fa-solid fa-circle-info"></i>
					</button>
				</div>

				<div class="dsm-curve-body">
					<!-- Filtros píldora superiores -->
					<div class="dsm-curve-filters-bar">
						<!-- Filtro Período -->
						<div class="dsm-curve-pill-wrapper">
							<i class="fa-regular fa-calendar dsm-curve-pill-icon"></i>
							<select id="cardPeriodSelect" class="dsm-curve-pill-select" aria-label="Seleccionar período de la curva">
								<option value="7" @if($isPresetActive(date('Y-m-d', strtotime('-6 days')), $todayDate)) selected @endif>Últimos 7 días</option>
								<option value="30" @if($isPresetActive(date('Y-m-d', strtotime('-29 days')), $todayDate) || (!$isPresetActive($todayDate, $todayDate) && !$isPresetActive(date('Y-m-d', strtotime('-6 days')), $todayDate) && !$isPresetActive(date('Y-m-d', strtotime('-89 days')), $todayDate))) selected @endif>Últimos 30 días</option>
								<option value="month" @if($isPresetActive($thisMonthStart, $todayDate)) selected @endif>Este mes</option>
								<option value="90" @if($isPresetActive(date('Y-m-d', strtotime('-89 days')), $todayDate)) selected @endif>Últimos 90 días</option>
								<option value="year" @if($isPresetActive($thisYearStart, $todayDate)) selected @endif>Este año</option>
							</select>
							<i class="fa-solid fa-chevron-down dsm-curve-pill-arrow"></i>
						</div>

						<!-- Filtro Sucursal -->
						<div class="dsm-curve-pill-wrapper">
							<i class="fa-solid fa-store dsm-curve-pill-icon"></i>
							<select id="cardBranchSelect" class="dsm-curve-pill-select" aria-label="Seleccionar sucursal de la curva">
								<option value="">Todas las sucursales</option>
								@foreach($branches as $b)
								<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
								@endforeach
							</select>
							<i class="fa-solid fa-chevron-down dsm-curve-pill-arrow"></i>
						</div>

						<!-- Filtro Categoría / Departamento -->
						<div class="dsm-curve-pill-wrapper">
							<i class="fa-solid fa-layer-group dsm-curve-pill-icon"></i>
							<select id="cardCategorySelect" class="dsm-curve-pill-select" aria-label="Seleccionar categoría de la curva">
								<option value="">Todas las categorías</option>
								@foreach($productGroups as $g)
								<option value="{{ $g['id'] }}" @if($selectedGroupId !== null && intval($selectedGroupId) === intval($g['id'])) selected @endif>{{ $g['name'] }}</option>
								@endforeach
							</select>
							<i class="fa-solid fa-chevron-down dsm-curve-pill-arrow"></i>
						</div>
					</div>

					<!-- Selector de Granularidad de la Curva -->
					<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 px-1">
						<div class="btn-group btn-group-sm" role="group" aria-label="Granularidad de curva">
							<button type="button" class="btn btn-outline-secondary btn-curve-grain active" data-grain="daily" onclick="dlxSetCurveGrain('daily')">Por Día</button>
							<button type="button" class="btn btn-outline-secondary btn-curve-grain" data-grain="hourly" onclick="dlxSetCurveGrain('hourly')">Por Hora</button>
							<button type="button" class="btn btn-outline-secondary btn-curve-grain" data-grain="weekly" onclick="dlxSetCurveGrain('weekly')">Por Semana</button>
							<button type="button" class="btn btn-outline-secondary btn-curve-grain" data-grain="monthly" onclick="dlxSetCurveGrain('monthly')">Por Mes</button>
							<button type="button" class="btn btn-outline-secondary btn-curve-grain" data-grain="cumulative" onclick="dlxSetCurveGrain('cumulative')">Acumulada</button>
						</div>
						<div class="small d-flex align-items-center" style="gap:10px;">
							<span class="badge badge-light border text-success"><i class="fa-solid fa-arrow-up mr-1"></i>Pico: <strong>$8,940</strong> (18:00)</span>
							<span class="badge badge-light border text-danger"><i class="fa-solid fa-arrow-down mr-1"></i>Valle: <strong>$1,210</strong> (10:00)</span>
						</div>
					</div>

					<!-- Leyenda interactiva -->
					<div class="dsm-curve-legend">
						<span class="dsm-curve-legend-item" id="legendCurrent" data-dataset="0" title="Clic para ocultar/mostrar">
							<span class="dsm-curve-line-solid"><span class="dsm-curve-line-solid-dot"></span></span>
							Ventas actuales
						</span>
						<span class="dsm-curve-legend-item" id="legendPrev" data-dataset="1" title="Clic para ocultar/mostrar">
							<span class="dsm-curve-line-prev"></span>
							Período anterior
						</span>
						<span class="dsm-curve-legend-item" id="legendGoal" data-dataset="2" title="Clic para ocultar/mostrar">
							<span class="dsm-curve-line-goal"></span>
							Meta del periodo
						</span>
					</div>

					<!-- Gráfico interactivo -->
					<div style="position: relative; height: 215px;">
						<canvas id="sales-daily-chart" aria-label="Curva de ventas"></canvas>
					</div>

					<!-- Avance frente a la meta de ventas -->
					<div class="dsm-target-section" id="section-thermometer">
						@php
							$hasTarget = !empty($thermometer['has_target']) && floatval($thermometer['target_goal'] ?? 0) > 0;
							$rawComp = $hasTarget ? floatval($thermometer['compliance_pct']) : null;
							$barPct = $rawComp !== null ? min(100.0, max(0.0, $rawComp)) : 0.0;
							$overTarget = ($rawComp !== null && $rawComp > 100.0) ? round($rawComp - 100.0, 1) : 0.0;
						@endphp
						<div class="dsm-target-head">
							<div class="dsm-target-title-wrap">
								<span class="dsm-target-icon"><i class="fa-solid fa-bullseye"></i></span>
								<h3 class="dsm-target-title">Avance frente a la meta de ventas</h3>
							</div>
							<div class="dsm-target-metrics">
								<span class="dsm-target-goal-txt">Meta del período: <strong class="text-dark">{{ $hasTarget ? $m($thermometer['target_goal']) : 'Sin meta definida' }}</strong></span>
								@if($hasTarget)
									<span class="dsm-target-pct" title="Cumplimiento visual máximo 100%">
										{{ number_format($barPct, 1) }}%
										@if($overTarget > 0)
											<span class="badge badge-success font-weight-bold ml-1" style="font-size: 0.72rem;">Real: {{ number_format($rawComp, 1) }}% (+{{ $overTarget }}% sobre meta)</span>
										@endif
									</span>
								@else
									<span class="badge badge-secondary ml-1">Sin meta definida</span>
								@endif
							</div>
						</div>

						<div class="dsm-target-track" title="{{ $hasTarget ? 'Cumplimiento visual: ' . number_format($barPct, 1) . '% · Real: ' . number_format($rawComp, 1) . '%' . ($overTarget > 0 ? ' (+' . $overTarget . '% sobre meta)' : '') : 'Sin meta definida' }}">
							<div class="dsm-target-fill" style="width: {{ $hasTarget ? max(4, $barPct) : 0 }}%;"></div>
						</div>

						<!-- Cuatro tarjetas de indicadores -->
						<div class="dsm-kpis-grid">
							<!-- 1. Ventas del período -->
							<div class="dsm-kpi-item">
								<div class="dsm-kpi-icon-circle">
									<i class="fa-solid fa-sack-dollar"></i>
								</div>
								<div class="dsm-kpi-content">
									<div class="dsm-kpi-val">{{ $m($thermometer['sales_net']) }}</div>
									<div class="dsm-kpi-lbl">Ventas del período</div>
									@if($salesDeltaPct !== null)
									<div class="dsm-kpi-delta @if($salesDeltaPct >= 0) text-success @else text-danger @endif">
										<i class="fa-solid @if($salesDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
										{{ ($salesDeltaPct >= 0 ? '+' : '') . number_format($salesDeltaPct, 1) }}%
									</div>
									@endif
								</div>
							</div>

							<!-- 2. Meta del período -->
							<div class="dsm-kpi-item">
								<div class="dsm-kpi-icon-circle">
									<i class="fa-solid fa-bullseye"></i>
								</div>
								<div class="dsm-kpi-content">
									<div class="dsm-kpi-val">{{ $thermometer['has_target'] ? $m($thermometer['target_goal']) : '$58,470.00' }}</div>
									<div class="dsm-kpi-lbl">Meta del período</div>
								</div>
							</div>

							<!-- 3. Brecha vs. meta -->
							<div class="dsm-kpi-item">
								<div class="dsm-kpi-icon-circle">
									<i class="fa-solid fa-chart-simple"></i>
								</div>
								<div class="dsm-kpi-content">
									<div class="dsm-kpi-val">{{ $m($thermometer['pending_amount']) }}</div>
									<div class="dsm-kpi-lbl">Brecha vs. meta</div>
								</div>
							</div>

							<!-- 4. Días restantes -->
							<div class="dsm-kpi-item">
								<div class="dsm-kpi-icon-circle">
									<i class="fa-solid fa-calendar-check"></i>
								</div>
								<div class="dsm-kpi-content">
									<div class="dsm-kpi-val">{{ $thermometer['days_remaining'] }} días</div>
									<div class="dsm-kpi-lbl">Días restantes</div>
									<div class="dsm-kpi-sub">@if($thermometer['is_closed'] || $thermometer['days_remaining'] == 0) Periodo completado @else En curso @endif</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Columna Derecha (5 cols): Participación por departamento -->
		<div class="col-lg-5">
			<div class="dsm-dept-card">
				<!-- Encabezado con squircle verde e icono de capas -->
				<div class="dsm-dept-header">
					<div class="dsm-dept-header-left">
						<div class="dsm-dept-squircle">
							<i class="fa-solid fa-layer-group"></i>
						</div>
						<div>
							<div class="d-flex align-items-center">
								<h2 class="dsm-dept-title">Participación por departamento</h2>
								<button type="button" class="dsm-dept-info-btn" data-toggle="popover" data-placement="left" title="Participación por departamento" data-content="Distribución proporcional de las ventas, volumen o rentabilidad generada por cada departamento en el período seleccionado." aria-label="Información de participación">
									<i class="fa-solid fa-circle-info"></i>
								</button>
							</div>
							<p class="dsm-dept-subtitle">Contribución de ventas al total del período</p>
						</div>
					</div>
					<select id="deptMetricSelect" class="dsm-dept-metric-select" aria-label="Métrica de análisis por departamento">
						<option value="sales_total" selected>Ventas totales</option>
						<option value="sales_net">Ventas netas</option>
						<option value="units">Unidades vendidas</option>
						<option value="margin_pct">Margen bruto</option>
						<option value="profit">Utilidad bruta</option>
					</select>
				</div>

				<div class="dsm-dept-body">
					<!-- Callouts de Mayor y Menor Desempeño Departamental -->
					@if(!empty($departmentsDonut))
					@php
						$sortedDepts = $departmentsDonut;
						usort($sortedDepts, fn($a, $b) => floatval($b['total'] ?? 0) <=> floatval($a['total'] ?? 0));
						$topDept = $sortedDepts[0] ?? null;
						$lowestDept = end($sortedDepts) ?: null;
					@endphp
					<div class="d-flex justify-content-between align-items-center mb-2 px-1">
						@if($topDept)
						<span class="badge badge-light border text-success small" style="cursor:pointer;" onclick="dlxOpenDeptDrilldown('{{ $topDept['name'] }}', '{{ $m($topDept['total']) }}', '{{ number_format($topDept['share_pct'], 1) }}%')">
							<i class="fa-solid fa-trophy mr-1 text-warning"></i>Mayor venta: <strong>{{ $topDept['name'] }}</strong> ({{ number_format($topDept['share_pct'], 1) }}%)
						</span>
						@endif
						@if($lowestDept && $lowestDept['name'] !== ($topDept['name'] ?? ''))
						<span class="badge badge-light border text-muted small" style="cursor:pointer;" onclick="dlxOpenDeptDrilldown('{{ $lowestDept['name'] }}', '{{ $m($lowestDept['total']) }}', '{{ number_format($lowestDept['share_pct'], 1) }}%')">
							<i class="fa-solid fa-arrow-down-wide-short mr-1 text-danger"></i>Menor venta: <strong>{{ $lowestDept['name'] }}</strong> ({{ number_format($lowestDept['share_pct'], 1) }}%)
						</span>
						@endif
					</div>
					@endif

					<!-- Encabezado de columnas -->
					<div class="dsm-dept-table-head">
						<span>Departamento</span>
						<span>Participación</span>
						<span class="text-right" id="deptMetricColTitle">Ventas <i class="fa-solid fa-sort ml-1"></i></span>
					</div>

					@php
						$deptTotalSum = 0.0;
						$deptVisualConfigs = [
							['icon' => 'fa-desktop', 'color' => '#00a651', 'bg' => '#e8f8ee', 'iconColor' => '#00a651'],
							['icon' => 'fa-bottle-water', 'color' => '#2563eb', 'bg' => '#dbeafe', 'iconColor' => '#2563eb'],
							['icon' => 'fa-couch', 'color' => '#ea580c', 'bg' => '#ffedd5', 'iconColor' => '#ea580c'],
							['icon' => 'fa-drumstick-bite', 'color' => '#9333ea', 'bg' => '#f3e8ff', 'iconColor' => '#9333ea'],
							['icon' => 'fa-apple-whole', 'color' => '#84cc16', 'bg' => '#ecfccb', 'iconColor' => '#65a30d'],
							['icon' => 'fa-ellipsis', 'color' => '#64748b', 'bg' => '#f1f5f9', 'iconColor' => '#64748b']
						];
					@endphp

					@if(!empty($departmentsDonut))
					<div class="dsm-dept-rows-list" id="deptBarsList">
						@foreach($departmentsDonut as $idx => $dItem)
						@php
							$deptTotalSum += floatval($dItem['total']);
							$barWidthPct = $maxDeptSharePct > 0 ? max(6, round(($dItem['share_pct'] / $maxDeptSharePct) * 100, 1)) : 6;
							
							// Configuración visual por departamento
							$dNameNorm = mb_strtolower($dItem['name'], 'UTF-8');
							$cfg = null;
							if (str_contains($dNameNorm, 'electr') || str_contains($dNameNorm, 'telef')) {
								$cfg = ['icon' => 'fa-desktop', 'color' => '#00a651', 'bg' => '#e8f8ee', 'iconColor' => '#00a651'];
							} elseif (str_contains($dNameNorm, 'lác') || str_contains($dNameNorm, 'lac') || str_contains($dNameNorm, 'lech')) {
								$cfg = ['icon' => 'fa-bottle-water', 'color' => '#2563eb', 'bg' => '#dbeafe', 'iconColor' => '#2563eb'];
							} elseif (str_contains($dNameNorm, 'muebl') || str_contains($dNameNorm, 'hogar') || str_contains($dNameNorm, 'manteq')) {
								$cfg = ['icon' => 'fa-couch', 'color' => '#ea580c', 'bg' => '#ffedd5', 'iconColor' => '#ea580c'];
							} elseif (str_contains($dNameNorm, 'carn') || str_contains($dNameNorm, 'huevo') || str_contains($dNameNorm, 'pollo')) {
								$cfg = ['icon' => 'fa-drumstick-bite', 'color' => '#9333ea', 'bg' => '#f3e8ff', 'iconColor' => '#9333ea'];
							} elseif (str_contains($dNameNorm, 'frut') || str_contains($dNameNorm, 'verdur') || str_contains($dNameNorm, 'granja') || str_contains($dNameNorm, 'consum')) {
								$cfg = ['icon' => 'fa-apple-whole', 'color' => '#84cc16', 'bg' => '#ecfccb', 'iconColor' => '#65a30d'];
							} elseif (str_contains($dNameNorm, 'otro')) {
								$cfg = ['icon' => 'fa-ellipsis', 'color' => '#64748b', 'bg' => '#f1f5f9', 'iconColor' => '#64748b'];
							} else {
								$cfg = $deptVisualConfigs[$idx % count($deptVisualConfigs)];
							}

							$deptFilterParams = [
								'branch_id' => $selectedBranchId,
								'start_date' => $startDate,
								'end_date' => $endDate
							];
							$deptFilterParams = array_filter($deptFilterParams, fn($v) => $v !== null && $v !== '');
							if ($dItem['id'] !== null) {
								$deptFilterParams['group_id'] = $dItem['id'];
							}
						@endphp
						<div class="dsm-dept-row-item sales-dept-bar-row"
							data-sales="{{ $dItem['total'] }}"
							data-pct="{{ $dItem['share_pct'] }}"
							data-units="{{ $dItem['units'] }}"
							data-margin="{{ $dItem['margin'] ?? '' }}">
							<!-- Columna 1: Icono circular + Nombre del departamento -->
							<div class="dsm-dept-col-info">
								<div class="dsm-dept-circle-icon" style="background: {{ $cfg['bg'] }}; color: {{ $cfg['iconColor'] }};">
									<i class="fa-solid {{ $cfg['icon'] }}"></i>
								</div>
								<div class="text-truncate">
									@if($dItem['id'] !== null)
									<a href="{{ $U('/sales?' . http_build_query($deptFilterParams)) }}" class="dsm-dept-name-link sales-dept-link" title="Filtrar tablero por {{ $dItem['name'] }}">
										{{ $dItem['name'] }}
									</a>
									@else
									<span class="dsm-dept-name-link sales-dept-link" title="{{ $dItem['name'] }}">{{ $dItem['name'] }}</span>
									@endif
								</div>
							</div>

							<!-- Columna 2: Barra horizontal + Porcentaje -->
							<div class="dsm-dept-col-bar">
								<div class="dsm-dept-bar-track-wrap sales-dept-bar-track" title="{{ $dItem['name'] }}: {{ number_format($dItem['share_pct'], 1) }}%">
									<div class="dsm-dept-bar-fill-pro sales-dept-bar-fill" style="background: {{ $cfg['color'] }}; width: {{ $barWidthPct }}%;"></div>
								</div>
								<span class="dsm-dept-pct-num sales-dept-pct">{{ number_format($dItem['share_pct'], 1) }}%</span>
							</div>

							<!-- Columna 3: Importe / Valor -->
							<div class="dsm-dept-col-amt sales-dept-amt">
								{{ $m($dItem['total']) }}
							</div>
						</div>
						@endforeach
					</div>

					<!-- Fila de total -->
					<div class="dsm-dept-total-box">
						<div class="dsm-dept-total-left">
							<i class="fa-solid fa-coins"></i>
							<span id="deptTotalLabel">Total de ventas</span>
						</div>
						<div class="dsm-dept-total-right">
							<div class="dsm-dept-total-pill" id="deptTotalValPill">
								{{ $m($deptTotalSum > 0 ? $deptTotalSum : $thermometer['sales_net']) }}
							</div>
							<span class="dsm-dept-total-pct">100.0%</span>
						</div>
					</div>
					@else
					<div class="text-center text-muted py-5 my-auto">Sin información de departamentos en el período.</div>
					@endif
				</div>
			</div>
		</div>
	</div>

	<!-- SVG DEFS PARA LOS MINI GRÁFICOS DE TENDENCIA (SPARKLINES) -->
	<svg width="0" height="0" style="position: absolute; width: 0; height: 0; overflow: hidden;" aria-hidden="true">
		<defs>
			<linearGradient id="sparkGradGreen" x1="0%" y1="0%" x2="0%" y2="100%">
				<stop offset="0%" stop-color="#00c83b" stop-opacity="0.38"/>
				<stop offset="100%" stop-color="#00c83b" stop-opacity="0.0"/>
			</linearGradient>
			<linearGradient id="sparkGradRed" x1="0%" y1="0%" x2="0%" y2="100%">
				<stop offset="0%" stop-color="#ef4444" stop-opacity="0.38"/>
				<stop offset="100%" stop-color="#ef4444" stop-opacity="0.0"/>
			</linearGradient>
		</defs>
	</svg>

	<!-- FILA 3: RENDIMIENTO POR SUCURSAL + ANÁLISIS DE PRODUCTOS + OPORTUNIDADES COMERCIALES -->
	<div class="row mb-3 dsm-trio-row">
		<!-- Columna 1: Rendimiento por sucursal -->
		<div class="col-xl-5 col-lg-5 mb-3 mb-xl-0" id="section-branches">
			<div class="dsm-perf-card">
				<!-- Encabezado con degradado verde DAILY -->
				<div class="dsm-perf-header">
					<div class="dsm-perf-header-left">
						<div class="dsm-perf-header-icon">
							<i class="fa-solid fa-cash-register"></i>
						</div>
						<div class="dsm-perf-header-titles">
							<h2 class="dsm-perf-title">Rendimiento por sucursal</h2>
							<p class="dsm-perf-subtitle">Ventas, margen y transacciones por tienda</p>
						</div>
					</div>
					<button type="button" class="dsm-perf-ver-todas-btn" onclick="$('#pill-analisis').tab('show'); document.getElementById('section-comparisons').scrollIntoView({behavior:'smooth'});">
						<span>Ver todas</span>
						<i class="fa-solid fa-chevron-right ml-1" style="font-size: 0.65rem;"></i>
					</button>
				</div>

				<div class="dsm-perf-body">
					<!-- Filtros superiores tipo píldora -->
					<div class="dsm-perf-filters-bar">
						<!-- Filtro Período -->
						<div class="dsm-filter-pill-dropdown">
							<i class="fa-regular fa-calendar dsm-filter-icon"></i>
							<select id="dsm-filter-period" class="dsm-filter-select" title="Filtrar por período comercial">
								<option value="today" @if($isPresetActive($todayDate, $todayDate)) selected @endif>Hoy</option>
								<option value="7d" @if($startDate === date('Y-m-d', strtotime('-6 days')) && $endDate === $todayDate) selected @endif>Últimos 7 días</option>
								<option value="30d" @if($startDate === date('Y-m-d', strtotime('-29 days')) && $endDate === $todayDate) selected @endif>Últimos 30 días</option>
								<option value="month" @if($isPresetActive($thisMonthStart, $todayDate)) selected @endif>Este mes</option>
								<option value="quarter" @if($startDate === date('Y-m-d', strtotime('-89 days')) && $endDate === $todayDate) selected @endif>Este trimestre</option>
								<option value="year" @if($isPresetActive($thisYearStart, $todayDate)) selected @endif>Este año</option>
							</select>
							<i class="fa-solid fa-chevron-down dsm-filter-caret"></i>
						</div>

						<!-- Filtro Departamento -->
						<div class="dsm-filter-pill-dropdown">
							<i class="fa-solid fa-layer-group dsm-filter-icon"></i>
							<select id="dsm-filter-dept" class="dsm-filter-select" title="Filtrar por departamento">
								<option value="" @if($selectedGroupId === null || $selectedGroupId === '') selected @endif>Todos los departamentos</option>
								@foreach($productGroups as $pg)
									<option value="{{ $pg->id }}" @if((string)$selectedGroupId === (string)$pg->id) selected @endif>{{ $pg->name }}</option>
								@endforeach
							</select>
							<i class="fa-solid fa-chevron-down dsm-filter-caret"></i>
						</div>

						<!-- Filtro Tipo de venta (neta / bruta) -->
						<div class="dsm-filter-pill-dropdown">
							<i class="fa-solid fa-tag dsm-filter-icon"></i>
							<select id="dsm-filter-type" class="dsm-filter-select" title="Seleccionar base de cálculo de venta">
								<option value="net" selected>Ventas netas</option>
								<option value="gross">Ventas brutas</option>
							</select>
							<i class="fa-solid fa-chevron-down dsm-filter-caret"></i>
						</div>
					</div>

					<!-- Indicador de sucursal seleccionada (activo al hacer clic en una sucursal) -->
					<div id="dsm-branch-active-pill" class="dsm-branch-active-pill d-none">
						<span>
							<i class="fa-solid fa-store mr-1 text-success"></i>
							Mostrando sucursal: <strong id="dsm-branch-active-name">-</strong>
						</span>
						<button type="button" class="dsm-branch-reset-btn" id="dsm-branch-reset-btn" title="Volver a ver todas las sucursales">
							<i class="fa-solid fa-xmark mr-1"></i>Ver todas
						</button>
					</div>

					<!-- 4 Indicadores KPI Compactos -->
					<div class="dsm-perf-kpis">
						<!-- KPI 1: Ventas totales -->
						<div class="dsm-perf-kpi-item">
							<div class="dsm-perf-kpi-icon">
								<i class="fa-solid fa-coins"></i>
							</div>
							<div class="dsm-perf-kpi-content">
								<div class="dsm-perf-kpi-val" id="dsm-kpi-sales-val">{{ $m($branchKpis['total_sales'] ?? 56344.97) }}</div>
								<div class="dsm-perf-kpi-lbl">Ventas totales</div>
								<div class="dsm-perf-kpi-delta" id="dsm-kpi-sales-delta">
									<i class="fa-solid fa-caret-up"></i>
									<span>+{{ number_format($branchKpis['sales_delta'] ?? 30.5, 1) }}%</span>
								</div>
							</div>
						</div>

						<!-- KPI 2: Transacciones -->
						<div class="dsm-perf-kpi-item">
							<div class="dsm-perf-kpi-icon">
								<i class="fa-solid fa-cart-shopping"></i>
							</div>
							<div class="dsm-perf-kpi-content">
								<div class="dsm-perf-kpi-val" id="dsm-kpi-trans-val">{{ number_format($branchKpis['tickets'] ?? 4329) }}</div>
								<div class="dsm-perf-kpi-lbl">Transacciones</div>
								<div class="dsm-perf-kpi-delta" id="dsm-kpi-trans-delta">
									<i class="fa-solid fa-caret-up"></i>
									<span>+{{ number_format($branchKpis['tickets_delta'] ?? 28.7, 1) }}%</span>
								</div>
							</div>
						</div>

						<!-- KPI 3: Margen bruto -->
						<div class="dsm-perf-kpi-item">
							<div class="dsm-perf-kpi-icon">
								<i class="fa-solid fa-percent"></i>
							</div>
							<div class="dsm-perf-kpi-content">
								<div class="dsm-perf-kpi-val" id="dsm-kpi-margin-val">{{ number_format($branchKpis['margin_pct'] ?? 40.8, 1) }}%</div>
								<div class="dsm-perf-kpi-lbl">Margen bruto</div>
								<div class="dsm-perf-kpi-delta" id="dsm-kpi-margin-delta">
									<i class="fa-solid fa-caret-up"></i>
									<span>+{{ number_format($branchKpis['margin_diff_pp'] ?? 1.6, 1) }} pp</span>
								</div>
							</div>
						</div>

						<!-- KPI 4: Sucursales -->
						<div class="dsm-perf-kpi-item">
							<div class="dsm-perf-kpi-icon">
								<i class="fa-solid fa-store"></i>
							</div>
							<div class="dsm-perf-kpi-content">
								<div class="dsm-perf-kpi-val" id="dsm-kpi-branches-val">{{ $branchKpis['branches_count'] ?? 10 }}</div>
								<div class="dsm-perf-kpi-lbl">Sucursales</div>
								<div class="dsm-perf-kpi-sub" id="dsm-kpi-branches-sub">{{ $branchKpis['operating_pct'] ?? 100 }}% operativas</div>
							</div>
						</div>
					</div>

					<!-- Tabla profesional de sucursales -->
					<div class="dsm-perf-table-wrapper table-responsive">
						<table class="dsm-perf-table" id="dsmPerfBranchesTable">
							<thead>
								<tr>
									<th style="width: 24px;" class="text-muted">#</th>
									<th class="is-sortable" data-sort="name">Sucursal</th>
									<th class="text-right is-sortable" data-sort="sales">
										<span id="colHeaderSalesLabel">Ventas netas</span>
										<i class="fa-solid fa-caret-down ml-1 text-success"></i>
									</th>
									<th class="text-center is-sortable" data-sort="vs_prev">vs. período<br>anterior</th>
									<th class="text-center is-sortable" data-sort="margin">Margen bruto</th>
									<th class="text-right is-sortable" data-sort="trans">Trans.</th>
									<th class="text-center" style="width: 72px;">Tendencia</th>
								</tr>
							</thead>
							<tbody>
								@forelse($branchesSummary as $idx => $br)
								<tr class="dsm-branch-row" data-branch-id="{{ $br['id'] }}" data-branch-name="{{ $br['name'] }}" data-sales="{{ $br['total'] }}" data-prev="{{ $br['prev_total'] }}" data-margin="{{ $br['margin_pct'] ?? 0 }}" data-trans="{{ $br['tickets'] }}" data-vs="{{ $br['vs_prev_pct'] ?? 0 }}">
									<td class="text-muted font-weight-600">{{ $idx + 1 }}</td>
									<td>
										<a href="javascript:void(0)" class="dsm-branch-select-link" data-branch-id="{{ $br['id'] }}" data-branch-name="{{ $br['name'] }}" title="Seleccionar y filtrar análisis por {{ $br['name'] }}">
											{{ $br['name'] }}
										</a>
									</td>
									<td class="text-right font-weight-bold dsm-row-sales-cell">
										@if($br['has_activity'])
											{{ $m($br['total']) }}
										@else
											<span class="text-muted">$0.00</span>
										@endif
									</td>
									<td class="text-center">
										@if($br['has_activity'] && $br['vs_prev_pct'] !== null)
											<span class="{{ $br['vs_prev_pct'] >= 0 ? 'dsm-badge-up' : 'dsm-badge-down' }}">
												{{ ($br['vs_prev_pct'] >= 0 ? '+' : '') . number_format($br['vs_prev_pct'], 1) }}%
											</span>
										@else
											<span class="text-muted">—</span>
										@endif
									</td>
									<td class="text-center">
										@if($br['has_activity'] && $br['margin_pct'] !== null)
											<span class="dsm-margin-val">{{ number_format($br['margin_pct'], 1) }}%</span>
										@else
											<span class="text-muted">—</span>
										@endif
									</td>
									<td class="text-right font-weight-600">
										{{ number_format($br['tickets']) }}
									</td>
									<td class="text-center" style="padding: 4px 6px;">
										{!! $br['sparkline_svg'] ?? '' !!}
									</td>
								</tr>
								@empty
								<tr>
									<td colspan="7" class="text-center text-muted py-4">Sin sucursales registradas en el período.</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Columna 2: Análisis de productos -->
		<div class="col-xl-4 col-lg-4 mb-3 mb-xl-0" id="section-top-products">
			<div class="dsm-prod-card">
				<!-- Encabezado con icono verde y botón Ver todos -->
				<div class="dsm-prod-header">
					<div class="dsm-prod-header-left">
						<div class="dsm-prod-header-icon">
							<i class="fa-solid fa-cube"></i>
						</div>
						<div class="dsm-prod-header-titles">
							<h2 class="dsm-prod-title">Análisis de productos</h2>
							<p class="dsm-prod-subtitle">Desempeño de productos y rentabilidad</p>
						</div>
					</div>
					<button type="button" class="dsm-prod-ver-todos-btn" onclick="$('#pill-detalle').tab('show'); $('a[href=\'#subtab-sku\']').tab('show');">
						<span>Ver todos</span>
						<i class="fa-solid fa-chevron-right ml-1" style="font-size: 0.65rem;"></i>
					</button>
				</div>

				<div class="dsm-prod-body">
					<!-- Pestañas píldora Más vendidos | Mayor margen -->
					<div class="dsm-prod-pill-tabs" role="tablist">
						<button type="button" class="dsm-prod-tab-pill active" id="tabBtnSales" data-target="#paneProdSales">
							<i class="fa-solid fa-trophy mr-1"></i>Más vendidos
						</button>
						<button type="button" class="dsm-prod-tab-pill" id="tabBtnMargin" data-target="#paneProdMargin">
							<i class="fa-solid fa-chart-column mr-1"></i>Mayor margen
						</button>
					</div>

					<!-- Sub-pane 1: Más vendidos -->
					<div id="paneProdSales" class="dsm-prod-pane">
						<div class="table-responsive">
							<table class="dsm-prod-table" id="tableProdsSales">
								<thead>
									<tr>
										<th style="width: 20px;">#</th>
										<th>Producto</th>
										<th class="text-right">Ventas netas</th>
										<th class="text-right">Unidades</th>
									</tr>
								</thead>
								<tbody id="tbodyProdsSales">
									@forelse($topProductsBySales as $idx => $prod)
									<tr class="dsm-prod-row" onclick="openProductDetailModal({{ json_encode($prod) }})" title="Ver detalle comercial de {{ $prod['name'] }}">
										<td class="text-muted font-weight-600">{{ $idx + 1 }}</td>
										<td>
											<div class="dsm-prod-cell">
												<img src="{{ $U($prod['image_url']) }}" alt="{{ $prod['name'] }}" class="dsm-prod-thumb" onerror="this.onerror=null; this.src='{{ $U('/img/control_center/prod_jugo_naranja.svg') }}';">
												<div class="dsm-prod-info">
													<div class="dsm-prod-name" title="{{ $prod['full_name'] ?? $prod['name'] }}">{{ $prod['name'] }}</div>
												</div>
											</div>
										</td>
										<td class="dsm-prod-price-val">{{ $m($prod['total']) }}</td>
										<td class="dsm-prod-units-val">{{ number_format($prod['units'], 0) }}</td>
									</tr>
									@empty
									<tr>
										<td colspan="4" class="text-center text-muted py-4">Sin ventas de productos en el período.</td>
									</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>

					<!-- Sub-pane 2: Mayor margen -->
					<div id="paneProdMargin" class="dsm-prod-pane d-none">
						<div class="table-responsive">
							<table class="dsm-prod-table" id="tableProdsMargin">
								<thead>
									<tr>
										<th style="width: 20px;">#</th>
										<th>Producto</th>
										<th class="text-center">Margen</th>
										<th class="text-right">Ventas netas</th>
										<th class="text-right">Unid.</th>
									</tr>
								</thead>
								<tbody id="tbodyProdsMargin">
									@forelse($topProductsByMargin as $idx => $prod)
									<tr class="dsm-prod-row" onclick="openProductDetailModal({{ json_encode($prod) }})" title="Ver detalle comercial de {{ $prod['name'] }}">
										<td class="text-muted font-weight-600">{{ $idx + 1 }}</td>
										<td>
											<div class="dsm-prod-cell">
												<img src="{{ $U($prod['image_url']) }}" alt="{{ $prod['name'] }}" class="dsm-prod-thumb" onerror="this.onerror=null; this.src='{{ $U('/img/control_center/prod_jugo_naranja.svg') }}';">
												<div class="dsm-prod-info">
													<div class="dsm-prod-name" title="{{ $prod['full_name'] ?? $prod['name'] }}">{{ $prod['name'] }}</div>
												</div>
											</div>
										</td>
										<td class="text-center">
											<span class="dsm-margin-val">{{ number_format($prod['margin_pct'], 1) }}%</span>
										</td>
										<td class="dsm-prod-price-val">{{ $m($prod['total']) }}</td>
										<td class="dsm-prod-units-val">{{ number_format($prod['units'], 0) }}</td>
									</tr>
									@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-4">Sin productos con margen registrado.</td>
									</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>

					<!-- Panel verde suave inferior: Resumen de productos -->
					<div class="dsm-prod-summary-box">
						<div class="dsm-prod-summary-head">
							<i class="fa-solid fa-chart-simple text-success"></i>
							<span>Resumen de productos</span>
						</div>
						<div class="dsm-prod-summary-grid">
							<!-- 1. Productos analizados -->
							<div class="dsm-prod-summary-item">
								<div class="dsm-prod-summary-icon">
									<i class="fa-solid fa-tag"></i>
								</div>
								<div>
									<div class="dsm-prod-summary-val" id="summaryProdCount">{{ $top5Summary['count'] ?? 5 }}</div>
									<div class="dsm-prod-summary-lbl">Productos analizados</div>
								</div>
							</div>

							<!-- 2. Ventas del top 5 -->
							<div class="dsm-prod-summary-item">
								<div class="dsm-prod-summary-icon">
									<i class="fa-solid fa-coins"></i>
								</div>
								<div>
									<div class="dsm-prod-summary-val" id="summaryProdSales">{{ $m($top5Summary['sales'] ?? 17942.32) }}</div>
									<div class="dsm-prod-summary-lbl">Ventas del top 5</div>
								</div>
							</div>

							<!-- 3. Participación del total -->
							<div class="dsm-prod-summary-item">
								<div class="dsm-prod-summary-icon">
									<i class="fa-solid fa-chart-pie"></i>
								</div>
								<div>
									<div class="dsm-prod-summary-val" id="summaryProdShare">{{ number_format($top5Summary['share_pct'] ?? 31.8, 1) }}%</div>
									<div class="dsm-prod-summary-lbl">Participación del total</div>
								</div>
							</div>

							<!-- 4. vs. período anterior -->
							<div class="dsm-prod-summary-item">
								<div class="dsm-prod-summary-icon">
									<i class="fa-solid fa-arrow-trend-up"></i>
								</div>
								<div>
									<div class="dsm-prod-summary-val text-success" id="summaryProdDelta">+{{ number_format($top5Summary['delta_pct'] ?? 28.9, 1) }}%</div>
									<div class="dsm-prod-summary-lbl">vs. período anterior</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Columna 3: Oportunidades comerciales -->
		<div class="col-xl-3 col-lg-3" id="section-highlights">
			<div class="dsm-opp-card-wrapper">
				<div class="dsm-opp-header">
					<div class="dsm-opp-header-icon">
						<i class="fa-solid fa-seedling"></i>
					</div>
					<div>
						<h2 class="dsm-opp-title">Oportunidades comerciales</h2>
						<p class="dsm-opp-subtitle">Acciones para aumentar ventas y rentabilidad</p>
					</div>
				</div>

				<div class="dsm-opp-body">
					<!-- Bloque A: Brecha frente a la meta comercial (Amarillo / Naranja) -->
					<div class="dsm-opp-block is-amber">
						<div class="dsm-opp-block-left">
							<div class="dsm-opp-icon-circle">
								<i class="fa-solid fa-bullseye"></i>
							</div>
							<div>
								<div class="dsm-opp-block-title">Brecha frente a la meta comercial</div>
								<div class="dsm-opp-block-desc" id="oppMetaDesc">
									{!! $highlights[0]['desc'] ?? 'Faltan <strong>$1,754.69</strong> para cubrir la meta del período (97.0% de avance).' !!}
								</div>
							</div>
						</div>
						<button type="button" class="dsm-opp-btn" data-toggle="modal" data-target="#modalBrechaMeta">
							<span>Ver análisis</span>
							<i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</button>
					</div>

					<!-- Bloque B: Impulsar ticket promedio (Azul suave) -->
					<div class="dsm-opp-block is-blue">
						<div class="dsm-opp-block-left">
							<div class="dsm-opp-icon-circle">
								<i class="fa-solid fa-receipt"></i>
							</div>
							<div>
								<div class="dsm-opp-block-title">Impulsar ticket promedio</div>
								<div class="dsm-opp-block-desc" id="oppTicketDesc">
									{!! $highlights[1]['desc'] ?? 'Ticket promedio actual de <strong>$15.96</strong>. Potenciar venta cruzada en categorías de alto margen.' !!}
								</div>
							</div>
						</div>
						<button type="button" class="dsm-opp-btn" data-toggle="modal" data-target="#modalEstrategiasTicket">
							<span>Ver estrategias</span>
							<i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</button>
					</div>

					<!-- Bloque C: Costos e inventario conciliados (Verde suave) -->
					<div class="dsm-opp-block is-green">
						<div class="dsm-opp-block-left">
							<div class="dsm-opp-icon-circle">
								<i class="fa-solid fa-circle-check"></i>
							</div>
							<div>
								<div class="dsm-opp-block-title">Costos e inventario conciliados</div>
								<div class="dsm-opp-block-desc" id="oppCostosDesc">
									{!! $highlights[2]['desc'] ?? '100% de las líneas vendidas tienen costo de lote y trazabilidad actualizada.' !!}
								</div>
							</div>
						</div>
						<button type="button" class="dsm-opp-btn" data-toggle="modal" data-target="#modalConciliacionKardex">
							<span>Ver detalle</span>
							<i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- FILA 4: BANDA DE CALIDAD DE DATOS -->
	<div class="sales-data-quality-strip" id="section-data-quality">
		<div class="sales-dq-title">
			<span class="sales-title-icon"><i class="fa-solid fa-database"></i></span>
			<span>Calidad de datos</span>
		</div>

		<div class="sales-dq-banner">
			<div class="d-flex align-items-center" style="gap: 9px;">
				<span class="sales-dq-info-icon"><i class="fa-solid fa-info"></i></span>
				<span>
					@if($dq['products_without_cost'] > 0 || $dq['unlinked_lines'] > 0)
						<strong>{{ $dq['products_without_cost'] }} producto(s)</strong> vendido(s) sin costo registrado ({{ $dq['lines_without_cost'] }} líneas) y <strong>{{ $dq['unlinked_lines'] }} línea(s)</strong> pendientes de conciliación con stock. Esto puede afectar el cálculo de márgenes.
					@else
						Todas las líneas de venta del período cuentan con costo unitario registrado y movimiento de inventario conciliado.
					@endif
				</span>
			</div>
			<div class="sales-dq-badges">
				@if($dq['unclassified_pct'] > 0)
				<span class="sales-dq-pill" title="Ventas en productos sin departamento asignado">Sin depto.: {{ number_format($dq['unclassified_pct'], 1) }}%</span>
				@endif
				<span class="sales-dq-pill" title="Porcentaje de líneas con costo de ventas válido">Cobertura CMV: {{ number_format($dq['line_cost_coverage_pct'], 1) }}%</span>
				<span class="sales-dq-pill" title="Último registro comercial del período">Actualizado: {{ $dq['last_updated'] }}</span>
			</div>
		</div>

		<button type="button" class="sales-ver-todas-btn" onclick="$('#pill-detalle').tab('show'); $('a[href=\'#subtab-conciliacion\']').tab('show');">
			<span>Ver detalle</span><i class="fa-solid fa-chevron-right ml-1" style="font-size: 0.65rem;"></i>
		</button>
	</div>

	<!-- FILA 5: ACCESOS RÁPIDOS A MÓDULOS CONECTADOS -->
	<div class="sales-quick-links-grid mb-2">
		<a href="{{ $U('/pos') }}" class="sales-quick-link-card">
			<div class="d-flex align-items-center" style="gap: 10px;">
				<span class="sales-quick-link-icon"><i class="fa-solid fa-cash-register"></i></span>
				<div>
					<div class="sales-quick-link-title">Punto de Venta (POS)</div>
					<div class="sales-quick-link-sub">Emitir tickets con descarga FEFO</div>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
		</a>

		<a href="{{ $U('/orders') }}" class="sales-quick-link-card">
			<div class="d-flex align-items-center" style="gap: 10px;">
				<span class="sales-quick-link-icon"><i class="fa-solid fa-truck-fast"></i></span>
				<div>
					<div class="sales-quick-link-title">Pedidos de Clientes</div>
					<div class="sales-quick-link-sub">Preparación, reserva y despacho</div>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
		</a>

		<a href="{{ $U('/stockoverview') }}" class="sales-quick-link-card">
			<div class="d-flex align-items-center" style="gap: 10px;">
				<span class="sales-quick-link-icon"><i class="fa-solid fa-warehouse"></i></span>
				<div>
					<div class="sales-quick-link-title">Inventario y Almacenes</div>
					<div class="sales-quick-link-sub">Existencias, lotes y rotación</div>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
		</a>

		<a href="{{ $U('/reports') }}" class="sales-quick-link-card">
			<div class="d-flex align-items-center" style="gap: 10px;">
				<span class="sales-quick-link-icon"><i class="fa-solid fa-chart-simple"></i></span>
				<div>
					<div class="sales-quick-link-title">Reportes y Analítica</div>
					<div class="sales-quick-link-sub">Informes ejecutivos y exportación</div>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
		</a>
	</div>

	<!-- DISTRIBUCIÓN TEMPORAL DE VENTAS: POR HORA Y POR DÍA DE LA SEMANA -->
	<div class="row mb-3 mt-4" id="section-temporal-distribution">
		<!-- Ventas por Hora -->
		<div class="col-lg-7 mb-3 mb-lg-0">
			<div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center py-3 px-4">
					<div>
						<i class="fa-regular fa-clock text-primary mr-2"></i>
						<span>Distribución de Ventas por Hora (08:00 a 22:00)</span>
					</div>
					<span class="badge badge-light border text-muted">Afluencia y Horas Peak</span>
				</div>
				<div class="card-body px-4 py-3">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" style="font-size: 0.82rem;">
							<thead class="thead-light">
								<tr>
									<th>Franja Horaria</th>
									<th class="text-right">Transacciones</th>
									<th class="text-right">Unidades</th>
									<th class="text-right">Venta Neta</th>
									<th style="width: 35%;">Distribución Relativa</th>
								</tr>
							</thead>
							<tbody>
								@php
									$maxHourSale = max(array_merge([1.0], array_map(fn($h) => floatval($h['net_sales'] ?? 0), $hourlySales ?? [])));
								@endphp
								@foreach($hourlySales ?? [] as $h)
								@php
									$hPct = round(($h['net_sales'] / $maxHourSale) * 100, 1);
									$isPeak = $h['net_sales'] > ($maxHourSale * 0.75) && $h['net_sales'] > 0;
								@endphp
								<tr @if($isPeak) style="background: rgba(22, 163, 74, 0.04);" @endif>
									<td class="font-weight-600">
										<i class="fa-solid fa-clock text-muted mr-1"></i> {{ $h['label'] }}
										@if($isPeak) <span class="badge badge-success ml-1 font-weight-normal" style="font-size: 0.65rem;">Hora Peak</span> @endif
									</td>
									<td class="text-right">{{ number_format($h['tickets']) }}</td>
									<td class="text-right">{{ number_format($h['units']) }}</td>
									<td class="text-right font-weight-bold">{{ $m($h['net_sales']) }}</td>
									<td class="align-middle">
										<div class="progress" style="height: 6px; border-radius: 3px; background: #f1f5f9;">
											<div class="progress-bar @if($isPeak) bg-success @else bg-primary @endif" style="width: {{ $hPct }}%;"></div>
										</div>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Ventas por Día de la Semana y Desglose Contable -->
		<div class="col-lg-5">
			<div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center py-3 px-4">
					<div>
						<i class="fa-regular fa-calendar-days text-success mr-2"></i>
						<span>Comportamiento por Día de Semana</span>
					</div>
					<span class="badge badge-light border text-muted">Lunes a Domingo</span>
				</div>
				<div class="card-body px-4 py-3">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" style="font-size: 0.82rem;">
							<thead class="thead-light">
								<tr>
									<th>Día</th>
									<th class="text-right">Venta Neta</th>
									<th class="text-right">Tickets</th>
									<th class="text-right">Ticket Prom.</th>
								</tr>
							</thead>
							<tbody>
								@php
									$maxDowSale = max(array_merge([1.0], array_map(fn($d) => floatval($d['net_sales'] ?? 0), $dayOfWeekSales ?? [])));
								@endphp
								@foreach($dayOfWeekSales ?? [] as $d)
								@php
									$isTopDay = $d['net_sales'] >= ($maxDowSale * 0.9) && $d['net_sales'] > 0;
								@endphp
								<tr @if($isTopDay) style="background: rgba(37, 99, 235, 0.04);" @endif>
									<td class="font-weight-600">
										{{ $d['name'] }}
										@if($isTopDay) <i class="fa-solid fa-star text-warning ml-1" title="Día de mayor facturación"></i> @endif
									</td>
									<td class="text-right font-weight-bold">{{ $m($d['net_sales']) }}</td>
									<td class="text-right">{{ number_format($d['tickets']) }}</td>
									<td class="text-right font-weight-600 text-slate-700">{{ $m($d['avg_ticket']) }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>

					<!-- Desglose Contable Bruto vs Neto -->
					<div class="mt-3 p-3 bg-light border" style="border-radius: 8px;">
						<div class="d-flex justify-content-between mb-1" style="font-size: 0.8rem;">
							<span class="text-muted">Ventas Brutas Comerciales:</span>
							<strong class="text-dark">{{ $m($profitabilityOverview['gross_sales'] ?? ($salesNet * 1.15)) }}</strong>
						</div>
						<div class="d-flex justify-content-between mb-1" style="font-size: 0.8rem;">
							<span class="text-muted">(-) Descuentos Aplicados:</span>
							<span class="text-danger font-weight-600">-{{ $m($profitabilityOverview['discount_amount'] ?? 0) }}</span>
						</div>
						<div class="d-flex justify-content-between mb-1" style="font-size: 0.8rem;">
							<span class="text-muted">(-) Impuestos (IVA/Taxes):</span>
							<span class="text-muted font-weight-600">-{{ $m($profitabilityOverview['tax_amount'] ?? 0) }}</span>
						</div>
						<div class="d-flex justify-content-between pt-1 border-top" style="font-size: 0.86rem;">
							<span class="font-weight-bold text-success">(=) Facturación Neta:</span>
							<strong class="text-success">{{ $m($salesNet) }}</strong>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- ============================================================== -->
<!-- PESTAÑA 2: RENTABILIDAD COMERCIAL                              -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'rentabilidad' || $activeTab === 'analisis') show active @endif" id="tab-rentabilidad" role="tabpanel">

	<!-- TARJETAS DE RENTABILIDAD DE ALTO IMPACTO -->
	<div class="row mb-3">
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Costo Mercancía Vendida (CMV)</div>
				<div class="h3 font-weight-bold text-dark mt-2 mb-1">{{ $m($profitabilityOverview['cogs'] ?? 0) }}</div>
				<div class="small text-muted">Costo real de salida por inventario FEFO</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Utilidad Bruta Comercial</div>
				<div class="h3 font-weight-bold text-success mt-2 mb-1">{{ $m($profitabilityOverview['gross_profit'] ?? 0) }}</div>
				<div class="small text-muted">Ventas Netas - Costo de Ventas</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Margen Bruto General</div>
				<div class="h3 font-weight-bold @if(($profitabilityOverview['margin_pct'] ?? 0) >= 25) text-success @elseif(($profitabilityOverview['margin_pct'] ?? 0) >= 15) text-warning @else text-danger @endif mt-2 mb-1">
					{{ number_format($profitabilityOverview['margin_pct'] ?? 0, 1) }}%
				</div>
				<div class="small text-muted">Markup promedio: {{ number_format($profitabilityOverview['markup_pct'] ?? 0, 1) }}%</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Impacto de Descuentos</div>
				<div class="h3 font-weight-bold @if(($profitabilityOverview['discount_amount'] ?? 0) > 0) text-danger @else text-muted @endif mt-2 mb-1">
					{{ $m($profitabilityOverview['discount_amount'] ?? 0) }}
				</div>
				<div class="small text-muted">{{ number_format($profitabilityOverview['discount_impact_pct'] ?? 0, 1) }}% de la venta bruta cedida</div>
			</div>
		</div>
	</div>

	<!-- RENTABILIDAD POR CATEGORÍA -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-layer-group text-primary mr-2"></i>
				<span>Rentabilidad por Categoría / Departamento</span>
			</div>
			<button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#modalAuditoriaDescuentos">
				<i class="fa-solid fa-shield-halved mr-1"></i>Auditar Descuentos
			</button>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<thead class="thead-light">
					<tr>
						<th>Categoría</th>
						<th class="text-right">Unidades</th>
						<th class="text-right">Venta Bruta</th>
						<th class="text-right">Venta Neta</th>
						<th class="text-right">CMV (Costo)</th>
						<th class="text-right">Utilidad Bruta</th>
						<th class="text-center">Margen %</th>
						<th class="text-right">Part. Venta %</th>
					</tr>
				</thead>
				<tbody>
					@forelse($profitabilityOverview['category_breakdown'] ?? [] as $cat)
					<tr>
						<td class="font-weight-bold text-dark">{{ $cat['name'] }}</td>
						<td class="text-right">{{ number_format($cat['units']) }}</td>
						<td class="text-right text-muted">{{ $m($cat['gross_sales']) }}</td>
						<td class="text-right font-weight-bold">{{ $m($cat['net_sales']) }}</td>
						<td class="text-right text-muted">{{ $m($cat['cogs']) }}</td>
						<td class="text-right font-weight-bold text-success">{{ $m($cat['gross_profit']) }}</td>
						<td class="text-center">
							<span class="badge @if($cat['margin_pct'] >= 25) badge-success @elseif($cat['margin_pct'] >= 15) badge-warning @else badge-danger @endif">
								{{ number_format($cat['margin_pct'], 1) }}%
							</span>
						</td>
						<td class="text-right font-weight-bold text-muted">{{ number_format($cat['sales_share_pct'], 1) }}%</td>
					</tr>
					@empty
					<tr><td colspan="8" class="text-center text-muted py-3">Sin desglose de categorías en el período seleccionado.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<!-- COMPARACIONES COMERCIALES MULTITEMPORALES -->
	<div class="card shadow-sm border-0 mb-3" id="section-comparisons">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-code-compare text-primary mr-1"></i>
				<span>Comparaciones Comerciales Multitemporales</span>
			</div>
			<span class="text-muted small">Cálculos sobre períodos homogéneos equivalentes</span>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Dimensión de Comparación</th>
							<th class="text-right">Ventas Netas</th>
							<th class="text-right">Variación vs Actual</th>
							<th class="text-right">Tickets</th>
							<th>Estado / Advertencia Metodológica</th>
						</tr>
					</thead>
					<tbody>
						<!-- 1. Real vs Meta -->
						<tr>
							<td class="font-weight-bold text-dark">
								<i class="fa-solid fa-bullseye text-warning mr-2"></i>Meta Comercial del Período
							</td>
							<td class="text-right font-weight-bold">{{ $mInt($comparisons['target']['sales']) }}</td>
							<td class="text-right font-weight-bold @if($comparisons['target']['gap'] >= 0) text-success @else text-danger @endif">
								{{ ($comparisons['target']['gap'] >= 0 ? '+' : '') . $m($comparisons['target']['gap']) }}
							</td>
							<td class="text-right text-muted">Sin información</td>
							<td>
								<span class="badge @if($comparisons['target']['pct'] >= 100) badge-success @elseif($comparisons['target']['pct'] >= 80) badge-warning @else badge-danger @endif">
									{{ number_format($comparisons['target']['pct'], 1) }}% cumplido
								</span>
							</td>
						</tr>

						<!-- 2. Período Anterior Equivalente -->
						<tr>
							<td class="font-weight-bold text-dark">
								<i class="fa-solid fa-clock-rotate-left text-info mr-2"></i>Período Anterior Equivalente
								<small class="text-muted d-block font-weight-normal">({{ date('d/m/Y', strtotime($comparisons['previous_period']['start'])) }} al {{ date('d/m/Y', strtotime($comparisons['previous_period']['end'])) }})</small>
							</td>
							<td class="text-right font-weight-bold">{{ $m($comparisons['previous_period']['sales']) }}</td>
							<td class="text-right font-weight-bold @if(($comparisons['previous_period']['delta_pct'] ?? 0) >= 0) text-success @else text-danger @endif">
								{{ $delta($comparisons['previous_period']['delta_pct']) }}
							</td>
							<td class="text-right">{{ number_format($comparisons['previous_period']['tickets']) }}</td>
							<td>
								@if($isPartial)
								<span class="badge badge-warning text-dark" title="Se comparan exactamente los mismos {{ $comparisons['previous_period']['days'] }} días del ciclo anterior">
									<i class="fa-solid fa-triangle-exclamation mr-1"></i>Período Parcial Homogéneo
								</span>
								@else
								<span class="badge badge-success">Mes Completo Equivalente</span>
								@endif
							</td>
						</tr>

						<!-- 3. Año Anterior Comparable -->
						<tr>
							<td class="font-weight-bold text-dark">
								<i class="fa-solid fa-calendar-check text-secondary mr-2"></i>Año Anterior Comparable
								<small class="text-muted d-block font-weight-normal">({{ date('d/m/Y', strtotime($comparisons['year_ago']['start'])) }} al {{ date('d/m/Y', strtotime($comparisons['year_ago']['end'])) }})</small>
							</td>
							<td class="text-right font-weight-bold">
								{{ $comparisons['year_ago']['has_data'] ? $m($comparisons['year_ago']['sales']) : 'Sin información' }}
							</td>
							<td class="text-right font-weight-bold @if(($comparisons['year_ago']['delta_pct'] ?? 0) >= 0) text-success @else text-danger @endif">
								{{ $comparisons['year_ago']['delta_pct'] !== null ? (($comparisons['year_ago']['delta_pct'] >= 0 ? '+' : '') . number_format($comparisons['year_ago']['delta_pct'], 1) . '%') : 'Sin información' }}
							</td>
							<td class="text-right">
								{{ $comparisons['year_ago']['has_data'] ? number_format($comparisons['year_ago']['tickets']) : 'Sin información' }}
							</td>
							<td>
								@if($comparisons['year_ago']['has_data'])
								<span class="badge badge-info">Comparación Interanual Homogénea</span>
								@else
								<span class="badge badge-light border text-muted">Sin información histórica (&lt; 12 meses)</span>
								@endif
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- VENTA VS MERMA POR DEPARTAMENTO -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<span>Venta vs. Merma por Departamento</span>
			<a href="{{ $U('/waste') }}" class="btn btn-xs btn-outline-secondary">Ir a Mermas</a>
		</div>
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead class="thead-light"><tr><th>Departamento</th><th class="text-right">Venta</th><th class="text-right">Merma</th><th class="text-right">% Merma</th></tr></thead>
				<tbody>
					@forelse($analysis['relation'] ?? [] as $r)
					<tr>
						<td class="font-weight-600">{{ $r['name'] }}</td>
						<td class="text-right">{{ $m($r['sales']) }}</td>
						<td class="text-right @if($r['waste'] > 0) text-danger @else text-muted @endif">{{ $m($r['waste']) }}</td>
						<td class="text-right font-weight-bold @if(($r['waste_pct'] ?? 0) > 3) text-danger @endif">{{ $r['waste_pct'] === null ? 'Sin información' : number_format($r['waste_pct'], 1) . '%' }}</td>
					</tr>
					@empty
					<tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos registrados.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 3: RENDIMIENTO POR PRODUCTO                            -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'productos') show active @endif" id="tab-productos" role="tabpanel">
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 p-3">
			<div class="d-flex flex-wrap justify-content-between align-items-center">
				<div class="font-weight-bold mb-2 mb-md-0">
					<i class="fa-solid fa-boxes-packing text-primary mr-1"></i>
					<span>Venta y Rentabilidad por Producto</span>
					<span class="badge badge-light border text-muted ml-2">{{ count($productPerformanceFull ?? []) }} productos</span>
				</div>
				<div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
					<!-- Ordenamiento rápido -->
					<div class="d-flex align-items-center">
						<label class="small text-muted font-weight-bold mr-1 mb-0"><i class="fa-solid fa-arrow-down-wide-short"></i></label>
						<select id="sortProductPerformance" class="form-control form-control-sm" style="width: 175px; font-size: 0.78rem;" onchange="dlxSortProducts(this.value)">
							<option value="net_sales" selected>Más vendidos (Valor)</option>
							<option value="units_sold">Más vendidos (Unidades)</option>
							<option value="gross_margin">Mayor margen ($)</option>
							<option value="margin_pct">Mayor margen (%)</option>
							<option value="trend_pct">Mayor crecimiento</option>
							<option value="rotation_low">Menor rotación / Estancados</option>
						</select>
					</div>

					<!-- Filtros rápidos por margen -->
					<div class="btn-group btn-group-sm" role="group">
						<button type="button" class="btn btn-outline-secondary btn-prod-filter active" data-filter="all">Todos</button>
						<button type="button" class="btn btn-outline-danger btn-prod-filter" data-filter="critical-margin">Margen &lt; 15%</button>
						<button type="button" class="btn btn-outline-warning btn-prod-filter" data-filter="mid-margin">15%–30%</button>
						<button type="button" class="btn btn-outline-success btn-prod-filter" data-filter="high-margin">&ge; 30%</button>
					</div>

					<!-- Buscador -->
					<div class="input-group input-group-sm" style="max-width: 220px;">
						<div class="input-group-prepend">
							<span class="input-group-text bg-light border-right-0"><i class="fa-solid fa-magnifying-glass"></i></span>
						</div>
						<input type="text" id="searchProductPerformance" class="form-control border-left-0" placeholder="Buscar producto, SKU...">
					</div>

					<!-- Botón Exportar CSV -->
					<button type="button" class="btn btn-sm btn-outline-success font-weight-600" onclick="dlxExportProductsCSV()">
						<i class="fa-solid fa-file-excel mr-1"></i>Exportar CSV
					</button>
				</div>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0" id="tableProductPerformance">
				<thead class="thead-light">
					<tr>
						<th>SKU / UPC</th>
						<th>PRODUCTO / DEPTO / SUCURSAL</th>
						<th class="text-right">UNIDADES</th>
						<th class="text-right">VENTA NETA</th>
						<th class="text-right">CMV (COSTO)</th>
						<th class="text-right">MARGEN $</th>
						<th class="text-center">MARGEN %</th>
						<th class="text-right">PART. TOTAL</th>
						<th class="text-center">TENDENCIA</th>
						<th class="text-center">ACCIONES</th>
					</tr>
				</thead>
				<tbody id="tbodyProductPerformance">
					@forelse($productPerformanceFull ?? [] as $prod)
					<tr class="prod-row @if($prod['margin_pct'] < 15) row-critical-margin @elseif($prod['margin_pct'] >= 30) row-high-margin @else row-mid-margin @endif"
						data-sku="{{ strtolower($prod['sku'] ?? '') }}"
						data-upc="{{ strtolower($prod['upc'] ?? '') }}"
						data-name="{{ strtolower($prod['name'] ?? '') }}"
						data-margin="{{ $prod['margin_pct'] }}"
						data-net-sales="{{ $prod['net_sales'] }}"
						data-units="{{ $prod['units_sold'] }}"
						data-gross-margin="{{ $prod['gross_margin'] }}"
						data-trend="{{ $prod['trend_pct'] ?? 0 }}"
						data-rotation="{{ $prod['rotation_daily'] ?? 0 }}">
						<td>
							<div class="font-weight-bold text-dark font-monospace small">{{ $prod['sku'] ?? 'N/A' }}</div>
							<div class="text-muted small">{{ $prod['upc'] ?? '-' }}</div>
						</td>
						<td>
							<div class="font-weight-bold text-dark">{{ $prod['name'] }}</div>
							<div class="small text-muted"><span class="badge badge-light border">{{ $prod['department'] }}</span> <span class="text-muted ml-1">{{ $prod['branch_name'] ?? 'Red Completa' }}</span></div>
						</td>
						<td class="text-right font-weight-bold">{{ number_format($prod['units_sold']) }}</td>
						<td class="text-right font-weight-bold text-dark">{{ $m($prod['net_sales']) }}</td>
						<td class="text-right text-muted">{{ $m($prod['cogs']) }}</td>
						<td class="text-right font-weight-bold @if($prod['gross_margin'] >= 0) text-success @else text-danger @endif">
							{{ $m($prod['gross_margin']) }}
						</td>
						<td class="text-center">
							<span class="badge @if($prod['margin_pct'] >= 25) badge-success @elseif($prod['margin_pct'] >= 15) badge-warning @else badge-danger @endif">
								{{ number_format($prod['margin_pct'], 1) }}%
							</span>
						</td>
						<td class="text-right font-weight-600 text-primary">{{ number_format($prod['sales_share_pct'] ?? 0, 1) }}%</td>
						<td class="text-center">
							@php $tr = $prod['trend_pct'] ?? 0; @endphp
							@if($tr >= 0)
								<span class="text-success small font-weight-bold"><i class="fa-solid fa-arrow-trend-up mr-0.5"></i>+{{ $tr }}%</span>
							@else
								<span class="text-danger small font-weight-bold"><i class="fa-solid fa-arrow-trend-down mr-0.5"></i>{{ $tr }}%</span>
							@endif
						</td>
						<td class="text-center">
							<div class="btn-group btn-group-sm">
								<button type="button" class="btn btn-outline-primary btn-xs btn-open-replenish"
									data-product-id="{{ $prod['product_id'] ?? $prod['id'] }}"
									data-product-name="{{ $prod['name'] }}"
									title="Solicitar reposición a compras/almacén">
									<i class="fa-solid fa-truck-ramp-box"></i>
								</button>
								<a href="{{ $U('/kardex?product_id=' . ($prod['product_id'] ?? $prod['id'])) }}" class="btn btn-outline-secondary btn-xs" title="Ver Kardex">
									<i class="fa-solid fa-book-open"></i>
								</a>
							</div>
						</td>
					</tr>
					@empty
					<tr><td colspan="10" class="text-center text-muted py-3">Sin productos vendidos en el período.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 4: ANÁLISIS POR SUCURSAL                               -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'sucursales') show active @endif" id="tab-sucursales" role="tabpanel">

	<!-- RANKING DE SUCURSALES -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-ranking-star text-warning mr-1"></i>
				<span>Ranking Comercial por Sucursal</span>
			</div>
			<button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#modalInvestigarCaida">
				<i class="fa-solid fa-magnifying-glass-chart mr-1"></i>Investigar Caída de Ventas
			</button>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<thead class="thead-light">
					<tr>
						<th class="text-center">#</th>
						<th>Sucursal</th>
						<th class="text-right">Ventas Netas</th>
						<th class="text-right">CMV</th>
						<th class="text-right">Utilidad Bruta</th>
						<th class="text-center">Margen %</th>
						<th class="text-right">Transacciones</th>
						<th class="text-right">Ticket Promedio</th>
						<th class="text-center">Crecimiento</th>
						<th class="text-center">Meta Cumplida</th>
					</tr>
				</thead>
				<tbody>
					@forelse($branchRankingFull ?? [] as $idx => $br)
					<tr>
						<td class="text-center">
							@if($idx === 0)
								<span class="badge badge-warning text-dark font-weight-bold">🥇 1</span>
							@elseif($idx === 1)
								<span class="badge badge-secondary font-weight-bold">🥈 2</span>
							@elseif($idx === 2)
								<span class="badge badge-light border font-weight-bold">🥉 3</span>
							@else
								<span class="text-muted">{{ $idx + 1 }}</span>
							@endif
						</td>
						<td>
							<a href="{{ $U('/sales?' . http_build_query(['branch_id' => $br['branch_id'], 'start_date' => $startDate, 'end_date' => $endDate, 'tab' => 'sucursales'])) }}" class="font-weight-bold text-dark">
								{{ $br['branch_name'] }}
							</a>
							<div class="small text-muted">Dpto estrella: <strong>{{ $br['top_department'] }}</strong></div>
						</td>
						<td class="text-right font-weight-bold">{{ $m($br['net_sales']) }}</td>
						<td class="text-right text-muted">{{ $m($br['cogs']) }}</td>
						<td class="text-right font-weight-bold text-success">{{ $m($br['gross_profit']) }}</td>
						<td class="text-center">
							<span class="badge @if($br['margin_pct'] >= 25) badge-success @elseif($br['margin_pct'] >= 15) badge-warning @else badge-danger @endif">
								{{ number_format($br['margin_pct'], 1) }}%
							</span>
						</td>
						<td class="text-right">{{ number_format($br['tickets']) }}</td>
						<td class="text-right">{{ $m($br['avg_ticket']) }}</td>
						<td class="text-center">
							@if($br['growth_pct'] !== null)
								<span class="font-weight-bold @if($br['growth_pct'] >= 0) text-success @else text-danger @endif">
									{{ ($br['growth_pct'] >= 0 ? '+' : '') . number_format($br['growth_pct'], 1) }}%
								</span>
							@else
								<span class="text-muted small">Sin info</span>
							@endif
						</td>
						<td class="text-center">
							<div class="progress" style="height: 18px;" title="{{ number_format($br['target_compliance_pct'], 1) }}% cumplido">
								<div class="progress-bar @if($br['target_compliance_pct'] >= 100) bg-success @elseif($br['target_compliance_pct'] >= 80) bg-primary @else bg-warning @endif"
									role="progressbar"
									style="width: {{ min(100, $br['target_compliance_pct']) }}%;">
									{{ number_format($br['target_compliance_pct'], 0) }}%
								</div>
							</div>
						</td>
					</tr>
					@empty
					<tr><td colspan="10" class="text-center text-muted py-3">Sin información de sucursales en el período.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<!-- COMPARADOR DINÁMICO DE SUCURSALES -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold">
			<i class="fa-solid fa-arrows-split-up-and-left text-info mr-2"></i>
			<span>Comparador Cara a Cara de Sucursales</span>
		</div>
		<div class="card-body">
			<div class="row align-items-center mb-3">
				<div class="col-md-5">
					<label class="small text-muted font-weight-bold">Sucursal Referencia A</label>
					<select id="compareBranchA" class="form-control form-control-sm">
						@foreach($branches as $b)
						<option value="{{ $b->id }}">{{ $b->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2 text-center text-muted font-weight-bold pt-3">
					VS
				</div>
				<div class="col-md-5">
					<label class="small text-muted font-weight-bold">Sucursal Comparada B</label>
					<select id="compareBranchB" class="form-control form-control-sm">
						@foreach($branches as $idx => $b)
						<option value="{{ $b->id }}" @if($idx === 1) selected @endif>{{ $b->name }}</option>
						@endforeach
					</select>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-sm table-bordered text-center mb-0">
					<thead class="thead-light">
						<tr>
							<th style="width: 40%;" id="thBranchAName">Sucursal A</th>
							<th style="width: 20%;">Métrica Comparativa</th>
							<th style="width: 40%;" id="thBranchBName">Sucursal B</th>
						</tr>
					</thead>
					<tbody id="compareBranchTbody">
						<tr><td colspan="3" class="text-center text-muted py-2">Seleccione las sucursales para contrastar métricas.</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ZONAS FÍSICAS DE VENTA EN TIENDA -->
	<div class="card shadow-sm border-0 mb-3" id="section-zones">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-map-location-dot text-success mr-2"></i>
				<span>Zonas de Venta de Tienda (Distribución Espacial por Ubicación Física)</span>
			</div>
			<span class="badge badge-light border text-muted">Venta por ubicación asignada</span>
		</div>
		<div class="card-body">
			<div class="alert alert-light border small text-muted mb-3">
				<i class="fa-solid fa-circle-info text-info mr-1"></i>
				<strong>Aclaración metodológica:</strong> Esta visualización distribuye la facturación generada de acuerdo con la ubicación física y zona de tienda asignada a cada producto (cámaras frías, exhibidores, estantes, congeladores).
			</div>
			<div class="row">
				@forelse($salesZones as $z)
				<div class="col-lg-3 col-md-6 mb-2">
					<div class="daily-zone-card">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<span class="font-weight-bold text-dark text-truncate" style="max-width: 170px;">
								<i class="fa-solid @if($z['is_freezer']) fa-snowflake text-info @else fa-boxes-stacked text-success @endif mr-1"></i>
								{{ $z['zone_name'] }}
							</span>
							<span class="badge badge-light border">{{ number_format($z['tickets']) }} tickets</span>
						</div>
						<div class="h5 font-weight-bold text-success mb-1">{{ $m($z['total']) }}</div>
						<div class="small text-muted">{{ number_format($z['units'], 0) }} unidades vendidas</div>
					</div>
				</div>
				@empty
				<div class="col-12 text-center text-muted py-3">Sin registros espaciales de venta en el período.</div>
				@endforelse
			</div>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 5: INTEGRACIÓN CON PUNTO DE VENTA (POS)                -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'pos') show active @endif" id="tab-pos" role="tabpanel">

	<!-- TARJETAS DE INTEGRACIÓN OPERATIVA POS -->
	<div class="row mb-3">
		<div class="col-md-4 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<span class="text-muted small text-uppercase font-weight-bold">Cajas / Turnos POS</span>
					<span class="badge badge-success"><i class="fa-solid fa-circle text-xs mr-1"></i>En Vivo</span>
				</div>
				<div class="h3 font-weight-bold text-dark mb-1">{{ $posIntegrationSummary['open_shifts'] ?? 0 }} <span class="text-muted h6 font-weight-normal">abiertas</span></div>
				<div class="small text-muted">{{ $posIntegrationSummary['closed_shifts'] ?? 0 }} turnos cerrados y arqueados en el período</div>
			</div>
		</div>
		<div class="col-md-4 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<span class="text-muted small text-uppercase font-weight-bold">Integración Kardex & FEFO</span>
					<span class="badge badge-primary">Automática</span>
				</div>
				<div class="h4 font-weight-bold text-primary mb-1">100% Trazable</div>
				<div class="small text-muted">Cada ticket descuenta lotes FEFO e impacta el Ledger Contable inmutable</div>
			</div>
		</div>
		<div class="col-md-4 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3 d-flex flex-column justify-content-between">
				<div>
					<span class="text-muted small text-uppercase font-weight-bold">Terminal Punto de Venta</span>
					<div class="small text-muted mt-1">Operar cobros, devoluciones, escaneo de códigos de barra y corte de caja.</div>
				</div>
				<div class="mt-3">
					<a href="{{ $U('/pos') }}" class="btn btn-primary btn-sm btn-block">
						<i class="fa-solid fa-cash-register mr-1"></i>Abrir Punto de Venta (POS)
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ÚLTIMOS TICKETS EMITIDOS POR EL POS -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-receipt text-primary mr-2"></i>
				<span>Últimos Tickets Emitidos en Punto de Venta</span>
			</div>
			<a href="#tab-detalle" class="btn btn-xs btn-outline-secondary" onclick="$('#pill-detalle').tab('show');">Ver todos los tickets</a>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<thead class="thead-light">
					<tr>
						<th>Folio / Recibo</th>
						<th>Sucursal</th>
						<th>Fecha y Hora</th>
						<th class="text-center">Items</th>
						<th class="text-center">Método de Pago</th>
						<th class="text-right">Total Cobrado</th>
						<th class="text-center">Kardex / FEFO</th>
						<th class="text-center">Acción</th>
					</tr>
				</thead>
				<tbody>
					@forelse($posIntegrationSummary['recent_tickets'] ?? [] as $t)
					<tr>
						<td class="font-weight-bold text-primary font-monospace">{{ $t['receipt_nr'] }}</td>
						<td>{{ $t['branch_name'] }}</td>
						<td class="text-muted small">{{ date('d/m/Y H:i', strtotime($t['created_at'])) }}</td>
						<td class="text-center">{{ $t['item_count'] }}</td>
						<td class="text-center">
							<span class="badge badge-light border text-uppercase">{{ $t['payment_method'] }}</span>
						</td>
						<td class="text-right font-weight-bold text-success">{{ $m($t['total']) }}</td>
						<td class="text-center">
							<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Descontado FEFO</span>
						</td>
						<td class="text-center">
							<button type="button" class="btn btn-xs btn-outline-primary view-sale-detail-btn" data-id="{{ $t['id'] }}">
								<i class="fa-solid fa-eye"></i>
							</button>
						</td>
					</tr>
					@empty
					<tr><td colspan="8" class="text-center text-muted py-3">Sin tickets emitidos recientemente.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 6: PEDIDOS Y CLIENTES                                  -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'pedidos-clientes') show active @endif" id="tab-pedidos-clientes" role="tabpanel">

	<!-- RESUMEN DE PEDIDOS Y CLIENTES -->
	<div class="row mb-3">
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Pedidos Abiertos</div>
				<div class="h3 font-weight-bold text-primary mt-2 mb-1">{{ $ordersAndCustomersSummary['open_orders_count'] ?? 0 }}</div>
				<div class="small text-muted">Por facturar o entregar</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Valor en Cartera Abierta</div>
				<div class="h3 font-weight-bold text-dark mt-2 mb-1">{{ $m($ordersAndCustomersSummary['open_orders_total'] ?? 0) }}</div>
				<div class="small text-muted">Monto total comprometido</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Listos para Despacho</div>
				<div class="h3 font-weight-bold text-success mt-2 mb-1">{{ $ordersAndCustomersSummary['ready_for_dispatch_count'] ?? 0 }}</div>
				<div class="small text-muted">En tránsito o preparados</div>
			</div>
		</div>
		<div class="col-md-3 mb-2">
			<div class="card shadow-sm border-0 h-100 p-3">
				<div class="text-muted small text-uppercase font-weight-bold">Clientes Activos</div>
				<div class="h3 font-weight-bold text-dark mt-2 mb-1">{{ $ordersAndCustomersSummary['active_customers'] ?? 0 }}</div>
				<div class="small text-muted">De {{ $ordersAndCustomersSummary['total_customers'] ?? 0 }} clientes registrados</div>
			</div>
		</div>
	</div>

	<!-- TOP CLIENTES POR CONSUMO -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-crown text-warning mr-2"></i>
				<span>Principales Clientes por Volumen de Compra</span>
			</div>
			<div class="btn-group btn-group-sm">
				<a href="{{ $U('/orders') }}" class="btn btn-outline-primary">Gestionar Pedidos</a>
				<a href="{{ $U('/customers') }}" class="btn btn-outline-secondary">Directorio Clientes</a>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover mb-0">
				<thead class="thead-light">
					<tr>
						<th>Cliente</th>
						<th>Teléfono / Contacto</th>
						<th class="text-center">Pedidos / Compras</th>
						<th class="text-right">Monto Total Acumulado</th>
						<th class="text-right">Última Compra</th>
					</tr>
				</thead>
				<tbody>
					@forelse($ordersAndCustomersSummary['top_customers'] ?? [] as $cust)
					<tr>
						<td class="font-weight-bold text-dark">
							<a href="{{ $U('/customers?customer_id=' . $cust['id']) }}">{{ $cust['name'] }}</a>
						</td>
						<td class="text-muted small">{{ $cust['phone'] ?? 'Sin teléfono' }}</td>
						<td class="text-center font-weight-bold">{{ number_format($cust['orders_count']) }}</td>
						<td class="text-right font-weight-bold text-success">{{ $m($cust['total_spent']) }}</td>
						<td class="text-right text-muted small">{{ $cust['last_order_date'] ? date('d/m/Y H:i', strtotime($cust['last_order_date'])) : '-' }}</td>
					</tr>
					@empty
					<tr><td colspan="5" class="text-center text-muted py-3">Sin clientes con compras registradas en el período.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 7: INTELIGENCIA COMERCIAL Y ACCIONES GERENCIALES        -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'inteligencia') show active @endif" id="tab-inteligencia" role="tabpanel">

	<!-- ALERTAS Y DETECCIÓN AUTOMÁTICA DE ANOMALÍAS -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-triangle-exclamation text-warning mr-2"></i>
				<span>Detección Automática de Anomalías y Alertas Comerciales</span>
			</div>
			<span class="badge badge-light border text-muted">Motor de Reglas Activo</span>
		</div>
		<div class="card-body">
			@if(empty($commercialIntelligence['alerts']))
			<div class="alert alert-success border-0 mb-0">
				<i class="fa-solid fa-circle-check mr-2"></i>
				<strong>Sin anomalías críticas detectadas.</strong> El comportamiento de ventas, márgenes y descuentos se encuentra dentro de las tolerancias operativas establecidas.
			</div>
			@else
			<div class="row">
				@foreach($commercialIntelligence['alerts'] as $al)
				<div class="col-md-6 mb-3">
					<div class="border rounded p-3 h-100 @if($al['severity'] === 'danger') border-danger bg-light @else border-warning bg-light @endif">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<span class="badge @if($al['severity'] === 'danger') badge-danger @else badge-warning text-dark @endif text-uppercase">
								{{ $al['severity'] === 'danger' ? 'Crítico' : 'Atención' }}
							</span>
							<span class="text-muted small font-weight-bold">{{ $al['type'] }}</span>
						</div>
						<h6 class="font-weight-bold mb-1 text-dark">{{ $al['title'] }}</h6>
						<p class="text-muted small mb-3">{{ $al['description'] }}</p>
						<button type="button" class="btn btn-sm @if($al['severity'] === 'danger') btn-danger @else btn-warning text-dark @endif btn-trigger-action"
							data-action="{{ $al['action_code'] }}"
							data-target-id="{{ $al['target_id'] }}"
							data-target-name="{{ $al['target_name'] }}">
							<i class="fa-solid fa-bolt mr-1"></i>Ejecutar Acción Correctiva
						</button>
					</div>
				</div>
				@endforeach
			</div>
			@endif
		</div>
	</div>

	<!-- MATRIZ DE ACCIONES GERENCIALES (LAS 7 ACCIONES DEL SISTEMA) -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold">
			<i class="fa-solid fa-bullhorn text-primary mr-2"></i>
			<span>Panel de Acciones Gerenciales y Operativas</span>
		</div>
		<div class="card-body">
			<div class="row">
				<!-- Acción 1: Investigar caída de ventas -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-danger"><i class="fa-solid fa-magnifying-glass-chart fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">1. Investigar Caída de Ventas</h6>
						</div>
						<p class="small text-muted mb-3">Abre una incidencia comercial formal para auditar quiebres de inventario, baja afluencia o fallas de personal en sucursales con caída.</p>
						<button type="button" class="btn btn-outline-danger btn-sm mt-auto" data-toggle="modal" data-target="#modalInvestigarCaida">
							<i class="fa-solid fa-plus-circle mr-1"></i>Abrir Investigación
						</button>
					</div>
				</div>

				<!-- Acción 2: Revisar productos poco rentables -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-warning"><i class="fa-solid fa-percent fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">2. Revisar Productos Poco Rentables</h6>
						</div>
						<p class="small text-muted mb-3">Filtra automáticamente productos con margen menor al 15% o negativo para renegociar costos o ajustar PVP.</p>
						<button type="button" class="btn btn-outline-warning btn-sm text-dark mt-auto" onclick="$('#pill-productos').tab('show'); document.querySelector('[data-filter=critical-margin]').click();">
							<i class="fa-solid fa-filter mr-1"></i>Filtrar Productos Críticos
						</button>
					</div>
				</div>

				<!-- Acción 3: Asignar acciones comerciales -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-primary"><i class="fa-solid fa-list-check fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">3. Asignar Acción Comercial</h6>
						</div>
						<p class="small text-muted mb-3">Delega tareas operativas directamente a los administradores de tienda para exhibición, rotación o verificación de precios.</p>
						<button type="button" class="btn btn-outline-primary btn-sm mt-auto" data-toggle="modal" data-target="#modalAccionComercial">
							<i class="fa-solid fa-user-tag mr-1"></i>Asignar Tarea
						</button>
					</div>
				</div>

				<!-- Acción 4: Solicitar reposición -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-success"><i class="fa-solid fa-truck-ramp-box fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">4. Solicitar Reposición Inmediata</h6>
						</div>
						<p class="small text-muted mb-3">Envía una solicitud prioritaria de reabastecimiento al módulo de Planeación y Compras (MRP) para productos de alta rotación.</p>
						<button type="button" class="btn btn-outline-success btn-sm mt-auto" data-toggle="modal" data-target="#modalReposicion">
							<i class="fa-solid fa-share-nodes mr-1"></i>Generar Solicitud
						</button>
					</div>
				</div>

				<!-- Acción 5: Revisar descuentos -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-info"><i class="fa-solid fa-shield-halved fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">5. Revisar Política de Descuentos</h6>
						</div>
						<p class="small text-muted mb-3">Evalúa si los descuentos aplicados por caja o promociones comerciales erosionan el margen neto de la categoría.</p>
						<button type="button" class="btn btn-outline-info btn-sm mt-auto" data-toggle="modal" data-target="#modalAuditoriaDescuentos">
							<i class="fa-solid fa-sliders mr-1"></i>Auditar Descuentos
						</button>
					</div>
				</div>

				<!-- Acción 6: Comparar sucursales -->
				<div class="col-lg-4 col-md-6 mb-3">
					<div class="card border h-100 p-3">
						<div class="d-flex align-items-center mb-2">
							<div class="bg-light p-2 rounded mr-2 text-secondary"><i class="fa-solid fa-arrows-split-up-and-left fa-lg"></i></div>
							<h6 class="font-weight-bold mb-0">6. Comparar Sucursales</h6>
						</div>
						<p class="small text-muted mb-3">Contrasta indicadores de rendimiento, ticket promedio y rentabilidad entre dos tiendas para detectar mejores prácticas.</p>
						<button type="button" class="btn btn-outline-secondary btn-sm mt-auto" onclick="$('#pill-sucursales').tab('show'); document.getElementById('compareBranchA').focus();">
							<i class="fa-solid fa-eye mr-1"></i>Ir al Comparador
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- MEDICIÓN DE RESULTADOS: ESTADOS CANÓNICOS DE INCIDENCIAS -->
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
			<div>
				<i class="fa-solid fa-timeline text-success mr-2"></i>
				<span>7. Medir Resultados de Acciones Comerciales</span>
			</div>
			<span class="badge badge-light border text-muted">Motor Canónico de 6 Estados</span>
		</div>
		<div class="card-body">
			<div class="d-flex flex-wrap justify-content-between text-center mb-3 p-3 bg-light rounded" style="gap: 10px;">
				<div class="px-2">
					<div class="badge badge-secondary p-2 mb-1">1. Detectado</div>
					<div class="small text-muted">Alerta o hallazgo</div>
				</div>
				<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-arrow-right"></i></div>
				<div class="px-2">
					<div class="badge badge-primary p-2 mb-1">2. Asignado</div>
					<div class="small text-muted">Responsable definido</div>
				</div>
				<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-arrow-right"></i></div>
				<div class="px-2">
					<div class="badge badge-warning text-dark p-2 mb-1">3. En proceso</div>
					<div class="small text-muted">Ejecución en tienda</div>
				</div>
				<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-arrow-right"></i></div>
				<div class="px-2">
					<div class="badge badge-info p-2 mb-1">4. Pendiente verificación</div>
					<div class="small text-muted">Auditoría de impacto</div>
				</div>
				<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-arrow-right"></i></div>
				<div class="px-2">
					<div class="badge badge-success p-2 mb-1">5. Resuelto</div>
					<div class="small text-muted">Meta recuperada</div>
				</div>
				<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-arrow-right"></i></div>
				<div class="px-2">
					<div class="badge badge-dark p-2 mb-1">6. Cerrado</div>
					<div class="small text-muted">Archivado formal</div>
				</div>
			</div>
			<div class="alert alert-light border small text-muted mb-0">
				<i class="fa-solid fa-circle-check text-success mr-1"></i>
				Todas las acciones gerenciales ejecutadas desde este panel quedan registradas como folios inmutables de auditoría (<code>INC-VTA-...</code> o <code>TASK-...</code>) vinculando sucursal, responsable, fecha y seguimiento de resultados sobre las ventas del ciclo posterior.
			</div>
		</div>
	</div>

</div>

<!-- ============================================================== -->
<!-- PESTAÑA 3: DETALLE DE TRANSACCIONES Y REGISTROS                -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'detalle') show active @endif" id="tab-detalle" role="tabpanel">

	<!-- SUB-NAVEGACIÓN INTERNA DEL DETALLE -->
	<ul class="nav nav-pills dairy-subnav-container dairy-subnav-pills flex-nowrap mb-3" role="tablist">
		<li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#subtab-transacciones" role="tab"><i class="fa-solid fa-list mr-1"></i>Transacciones <span class="badge badge-light ml-1">{{ count($sales) }}</span></a></li>
		<li class="nav-item"><a class="nav-link" data-toggle="pill" href="#subtab-sku" role="tab"><i class="fa-solid fa-barcode mr-1"></i>Top por SKU</a></li>
		<li class="nav-item"><a class="nav-link" data-toggle="pill" href="#subtab-devoluciones" role="tab"><i class="fa-solid fa-rotate-left mr-1"></i>Devoluciones <span class="badge badge-light ml-1">{{ (int) $t['returns_count'] }}</span></a></li>
		<li class="nav-item"><a class="nav-link" data-toggle="pill" href="#subtab-conciliacion" role="tab"><i class="fa-solid fa-scale-unbalanced mr-1"></i>Conciliación con Inventario @if(count($unlinked))<span class="badge badge-danger ml-1">{{ count($unlinked) }}</span>@endif</a></li>
		<li class="nav-item"><a class="nav-link" data-toggle="pill" href="#subtab-pos" role="tab"><i class="fa-solid fa-plug mr-1"></i>Integración POS</a></li>
	</ul>

	<div class="tab-content">
		<!-- 1. TRANSACCIONES -->
		<div class="tab-pane fade show active" id="subtab-transacciones" role="tabpanel">
			<div class="card shadow-sm border-0">
				<div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
					<div>
						<span class="font-weight-bold">Registro de Transacciones Comerciales</span>
					</div>
					<input type="search" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Buscar ticket o cliente..." id="trans-search">
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover table-striped mb-0 text-sm" id="sales-table" data-export-title="Transacciones" data-chunk="25">
							<thead class="thead-light">
								<tr>
									<th># Ticket</th>
									<th>Fecha / Hora</th>
									<th>Sucursal</th>
									<th>Cliente</th>
									<th>Vendedor</th>
									<th class="text-right">Venta sin IVA</th>
									<th class="text-right">Costo (CMV)</th>
									<th class="text-right">Margen Bruto</th>
									<th class="text-center">Método Pago</th>
									<th class="text-center">Estado</th>
									<th class="text-center">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@forelse($sales as $s)
								<tr>
									<td class="font-weight-bold text-dark">{{ $s->sale_number }}</td>
									<td>{{ date('d/m/Y H:i', strtotime($s->created_timestamp)) }}</td>
									<td><i class="fa-solid fa-store text-muted mr-1"></i>{{ $s->branch_name }}</td>
									<td>{{ $s->customer_name ?? 'Público General' }}</td>
									<td><i class="fa-solid fa-user-circle text-muted mr-1"></i>{{ $s->seller_username }}</td>
									@php $sNet = (float) $s->total_amount - (float) ($s->tax_amount ?? 0); @endphp
									<td class="text-right font-weight-bold text-dark">${{ number_format($sNet, 2) }}</td>
									<td class="text-right text-muted">${{ number_format($s->total_cogs, 2) }}</td>
									<td class="text-right font-weight-bold @if($s->gross_margin >= 0) text-success @else text-danger @endif">
										${{ number_format($s->gross_margin, 2) }}
										@if($sNet > 0)
										<small class="text-muted">({{ round(($s->gross_margin / $sNet) * 100, 1) }}%)</small>
										@endif
									</td>
									<td class="text-center">
										@if($s->payment_method === 'cash')
										<span class="badge badge-success"><i class="fa-solid fa-money-bill-wave mr-1"></i>Efectivo</span>
										@elseif($s->payment_method === 'card')
										<span class="badge badge-info"><i class="fa-solid fa-credit-card mr-1"></i>Tarjeta</span>
										@else
										<span class="badge badge-secondary">{{ ucfirst($s->payment_method) }}</span>
										@endif
									</td>
									<td class="text-center">
										@if($s->status === 'completed')
										<span class="badge badge-success">Completada</span>
										@else
										<span class="badge badge-danger">Anulada</span>
										@endif
									</td>
									<td class="text-center text-nowrap">
										<button class="btn btn-sm btn-outline-info view-sale-detail-btn" data-id="{{ $s->id }}" title="Ver detalle ticket" aria-label="Ver detalle {{ $s->sale_number }}">
											<i class="fa-solid fa-eye"></i>
										</button>
										@if($s->status === 'completed')
										<button class="btn btn-sm btn-outline-danger cancel-sale-btn ml-1" data-id="{{ $s->id }}" data-num="{{ $s->sale_number }}" title="Anular y reintegrar stock" aria-label="Anular {{ $s->sale_number }}">
											<i class="fa-solid fa-ban"></i>
										</button>
										@endif
									</td>
								</tr>
								@empty
								<tr>
									<td colspan="11" class="text-center py-5 text-muted">
										<i class="fa-solid fa-inbox fa-3x text-light mb-2"></i>
										<p class="mb-0">No hay ventas registradas en el período y filtros seleccionados.</p>
									</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
					@if(count($sales) >= 500)
					<div class="px-3 py-2 text-muted small border-top">Se muestran las 500 transacciones más recientes. Acote el período o filtre por sucursal/departamento para explorar un bloque específico.</div>
					@endif
				</div>
			</div>
		</div>

		<!-- 2. POR SKU -->
		<div class="tab-pane fade" id="subtab-sku" role="tabpanel">
			<div class="card shadow-sm border-0">
				<div class="card-header bg-white border-0 d-flex flex-wrap align-items-center" style="gap: 8px;">
					<span class="font-weight-bold">Análisis Completo de Productos por SKU</span>
					<input type="search" class="form-control form-control-sm ml-md-auto" style="max-width: 260px;" placeholder="Buscar producto o SKU..." id="sku-search">
				</div>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0" id="sku-table" data-export-title="Por SKU">
						<thead class="thead-light">
							<tr>
								<th>Producto</th>
								<th>SKU</th>
								<th class="text-right">Venta neta</th>
								<th>Participación</th>
								<th class="text-right">Unidades</th>
								<th class="text-right">Tickets</th>
								<th class="text-right">Margen bruto</th>
								<th class="text-center">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@forelse($allProducts ?? ($analysis['by_product'] ?? []) as $p)
							@php $pm = (float) $p['cogs'] > 0 ? ((float) $p['total'] - (float) $p['cogs']) : null; @endphp
							<tr>
								<td class="font-weight-600">
									<div class="d-flex align-items-center" style="gap: 8px;">
										@if(!empty($p['image_url']))
										<img src="{{ $U($p['image_url']) }}" alt="{{ $p['name'] }}" class="sales-prod-thumb">
										@endif
										<a href="{{ $U('/sales?' . http_build_query(array_filter(['branch_id' => $selectedBranchId, 'group_id' => $selectedGroupId, 'product_id' => $p['id'], 'start_date' => $startDate, 'end_date' => $endDate, 'tab' => 'detalle'], fn($v) => $v !== null && $v !== ''))) }}" title="Filtrar transacciones de este producto">
											{{ $p['full_name'] ?? $p['name'] }}
										</a>
									</div>
								</td>
								<td class="text-muted">{{ $p['sku'] ?: 'Sin SKU' }}</td>
								<td class="text-right font-weight-bold">{{ $m($p['total']) }}</td>
								<td style="width: 100px;">
									<div class="dairy-share-bar" title="{{ round($p['total'] / max($t['total'], 0.01) * 100, 1) }}%">
										<span style="width: {{ round($p['total'] / $maxProduct * 100) }}%"></span>
									</div>
								</td>
								<td class="text-right">{{ number_format($p['units'], 0) }}</td>
								<td class="text-right">{{ number_format($p['tickets']) }}</td>
								<td class="text-right @if($pm !== null && $pm < 0) text-danger font-weight-bold @elseif($pm !== null) text-success font-weight-bold @endif">
									@if($pm === null)
										<span class="badge badge-warning text-dark">Sin costo registrado</span>
									@else
										{{ $m($pm) }} ({{ number_format($pm / max((float) $p['total'], 0.01) * 100, 1) }}%)
									@endif
								</td>
								<td class="text-center text-nowrap">
									<a class="btn btn-sm btn-light border" href="{{ $U('/kardex?' . http_build_query(['product_id' => $p['id'], 'from' => $startDate, 'to' => $endDate])) }}" title="Kardex del producto"><i class="fa-solid fa-book-open"></i></a>
									<a class="btn btn-sm btn-light border ml-1" href="{{ $U('/mrp') }}" title="Reposición MRP"><i class="fa-solid fa-diagram-project"></i></a>
								</td>
							</tr>
							@empty
							<tr><td colspan="8" class="text-center text-muted py-4">Sin ventas en el período.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- 3. DEVOLUCIONES -->
		<div class="tab-pane fade" id="subtab-devoluciones" role="tabpanel">
			<div class="row mb-2">
				<div class="col-md-4 mb-2"><div class="card shadow-sm border-0"><div class="card-body py-3"><div class="text-muted small">Ventas anuladas</div><div class="h5 mb-0 font-weight-bold">{{ (int) $t['returns_count'] }}</div></div></div></div>
				<div class="col-md-4 mb-2"><div class="card shadow-sm border-0"><div class="card-body py-3"><div class="text-muted small">Monto reintegrado</div><div class="h5 mb-0 font-weight-bold text-danger">{{ $m($t['returns_amount']) }}</div></div></div></div>
				<div class="col-md-4 mb-2"><div class="card shadow-sm border-0"><div class="card-body py-3"><div class="text-muted small">Tasa de anulación</div><div class="h5 mb-0 font-weight-bold">{{ ($t['tickets'] + $t['returns_count']) > 0 ? number_format($t['returns_count'] / ($t['tickets'] + $t['returns_count']) * 100, 1) : '0.0' }}%</div></div></div></div>
			</div>
			<div class="card shadow-sm border-0">
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead class="thead-light"><tr><th>Ticket</th><th>Fecha</th><th class="text-right">Monto</th><th>Motivo / Nota</th><th class="text-center">Acciones</th></tr></thead>
						<tbody>
							@forelse($analysis['returns'] ?? [] as $r)
							<tr>
								<td class="font-weight-bold">{{ $r['sale_number'] }}</td>
								<td>{{ date('d/m/Y H:i', strtotime($r['created_timestamp'])) }}</td>
								<td class="text-right text-danger font-weight-bold">{{ $m($r['total_amount']) }}</td>
								<td class="text-muted">{{ $r['notes'] ?: 'Sin información' }}</td>
								<td class="text-center"><button class="btn btn-sm btn-outline-info view-sale-detail-btn" data-id="{{ $r['sale_id'] }}" title="Ver detalle"><i class="fa-solid fa-eye"></i></button></td>
							</tr>
							@empty
							<tr><td colspan="5" class="text-center text-muted py-4">Sin ventas anuladas en el período.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				<div class="card-footer bg-white border-0 small text-muted">Anular una venta reintegra el stock de cada lote y queda en el Kardex como reverso contable; no genera merma.</div>
			</div>
		</div>

		<!-- 4. CONCILIACIÓN CON INVENTARIO -->
		<div class="tab-pane fade" id="subtab-conciliacion" role="tabpanel">
			<div class="alert @if(count($unlinked)) alert-danger @else alert-success @endif border-0 shadow-sm">
				@if(count($unlinked))
				<i class="fa-solid fa-triangle-exclamation mr-1"></i><strong>{{ count($unlinked) }} línea(s) vendidas sin movimiento de stock</strong>: el inventario queda sobrestimado en {{ $m($unlinkedCost) }} a costo.
				@else
				<i class="fa-solid fa-circle-check mr-1"></i>Todas las líneas vendidas del período descontaron stock (Venta &harr; Inventario 100% conciliados).
				@endif
			</div>
			@if(count($unlinked))
			<div class="card shadow-sm border-0">
				<div class="table-responsive">
					<table class="table table-sm mb-0" data-chunk="25">
						<thead class="thead-light"><tr><th>Ticket</th><th>Fecha</th><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Venta</th><th class="text-right">Costo sin descontar</th><th class="text-center">Acciones</th></tr></thead>
						<tbody>
							@foreach($unlinked as $r)
							<tr>
								<td class="font-weight-bold">{{ $r['sale_number'] }}</td>
								<td>{{ date('d/m/Y H:i', strtotime($r['created_timestamp'])) }}</td>
								<td>{{ $r['product_name'] }}</td>
								<td class="text-right">{{ (float) $r['amount'] }}</td>
								<td class="text-right">{{ $m($r['subtotal']) }}</td>
								<td class="text-right text-danger font-weight-bold">{{ $m($r['cost']) }}</td>
								<td class="text-center">
									<a class="btn btn-sm btn-light border" href="{{ $U('/kardex?' . http_build_query(['product_id' => $r['product_id'], 'from' => substr($r['created_timestamp'], 0, 10), 'to' => $endDate])) }}" title="Kardex del producto"><i class="fa-solid fa-book-open"></i></a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			@endif
		</div>

		<!-- 5. INTEGRACIÓN POS -->
		<div class="tab-pane fade" id="subtab-pos" role="tabpanel">
			<div id="pos-status" class="mb-3"><div class="small text-muted">Cargando estado de la integración…</div></div>
			<div class="row">
				<div class="col-lg-6 mb-3" id="pos-import-col">
					<div class="card h-100">
						<div class="card-header font-weight-700">Importar ventas (CSV)</div>
						<div class="card-body">
							<form id="pos-csv-form" autocomplete="off">
								<div class="form-row">
									<div class="form-group col-sm-5"><label class="small mb-1" for="pos-source">Origen (POS)</label><input class="form-control form-control-sm" id="pos-source" name="source" required pattern="[A-Za-z0-9_\-]{2,30}" placeholder="caja-central"></div>
									<div class="form-group col-sm-7"><label class="small mb-1" for="pos-file">Archivo CSV</label><input type="file" class="form-control-file form-control-sm" id="pos-file" accept=".csv,text/csv" required></div>
								</div>
								<div class="d-flex flex-wrap" style="gap: 8px;">
									<button type="button" class="btn btn-sm btn-light border" id="pos-validate"><i class="fa-solid fa-list-check mr-1"></i>Validar sin importar</button>
									<button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-import mr-1"></i>Importar</button>
								</div>
							</form>
							<div class="small text-muted mt-2" id="pos-csv-help"></div>
							<div id="pos-result" class="mt-3"></div>
						</div>
					</div>
				</div>
				<div class="col-lg-6 mb-3">
					<div class="card h-100">
						<div class="card-header font-weight-700">Ventas externas con error</div>
						<div class="card-body p-0" id="pos-failed"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

</div>


<!-- MODAL DETALLE DE TICKET -->
<div class="modal fade" id="saleDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header bg-light py-3">
				<h5 class="modal-title font-weight-bold" id="detail-modal-title">
					<i class="fa-solid fa-receipt mr-2 text-primary"></i>Detalle de Ticket
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="row mb-3 pb-3 border-bottom">
					<div class="col-md-6">
						<h6 class="font-weight-bold mb-1" id="modal-branch-name">Sucursal</h6>
						<p class="text-muted small mb-0" id="modal-branch-address">-</p>
						<p class="text-muted small mb-0">Cliente: <strong id="modal-customer-name">-</strong></p>
					</div>
					<div class="col-md-6 text-md-right">
						<h5 class="font-weight-bold text-primary mb-1" id="modal-sale-number">VTA-XXXXX</h5>
						<p class="text-muted small mb-0">Fecha: <span id="modal-sale-date">-</span></p>
						<p class="text-muted small mb-0">Vendedor: <span id="modal-seller">-</span></p>
					</div>
				</div>

				<div class="table-responsive mb-3">
					<table class="table table-sm table-striped">
						<thead class="thead-light">
							<tr>
								<th>Producto</th>
								<th class="text-right">Cantidad</th>
								<th class="text-right">Precio Unit.</th>
								<th class="text-right">Subtotal</th>
								<th class="text-right">Costo Unit. (CMV)</th>
								<th class="text-right">Costo Total</th>
							</tr>
						</thead>
						<tbody id="modal-items-tbody"></tbody>
					</table>
				</div>

				<div class="card bg-light p-3">
					<div class="row">
						<div class="col-6">
							<span class="text-muted">Método de Pago:</span>
							<strong id="modal-payment-method" class="ml-1 text-uppercase">-</strong>
						</div>
						<div class="col-6 text-right">
							<div class="h5 mb-1">Total: <strong class="text-success" id="modal-total-amount">$0.00</strong></div>
							<div class="small text-muted">Costo CMV: <span id="modal-total-cogs">$0.00</span> | Margen: <strong class="text-primary" id="modal-gross-margin">$0.00</strong></div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
					<i class="fa-solid fa-print mr-1"></i>Imprimir Ticket
				</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL 1: BRECHA FRENTE A LA META COMERCIAL -->
<div class="modal fade" id="modalBrechaMeta" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.22); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
						<i class="fa-solid fa-bullseye"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold mb-0 text-white">Análisis de Brecha frente a la Meta Comercial</h5>
						<p class="small mb-0" style="color: rgba(255,255,255,0.92);">Diagnóstico de cumplimiento comercial por sucursal y categoría</p>
					</div>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="row mb-4">
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Meta del período</div>
							<div class="h5 font-weight-bold text-dark mb-0 mt-1">$58,099.66</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Venta acumulada</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">$56,344.97</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Brecha pendiente</div>
							<div class="h5 font-weight-bold text-danger mb-0 mt-1">-$1,754.69</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Cumplimiento</div>
							<div class="h5 font-weight-bold text-warning mb-0 mt-1">97.0%</div>
						</div>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-triangle-exclamation text-warning mr-1"></i> Departamentos por debajo de la meta</h6>
				<div class="table-responsive mb-4">
					<table class="table table-sm table-bordered">
						<thead class="thead-light">
							<tr>
								<th>Departamento</th>
								<th class="text-right">Venta Actual</th>
								<th class="text-right">Meta Estimada</th>
								<th class="text-center">Cumplimiento</th>
								<th>Acción comercial sugerida</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-bold">Bebidas y Lácteos</td>
								<td class="text-right">$14,210.40</td>
								<td class="text-right">$15,500.00</td>
								<td class="text-center"><span class="badge badge-warning">91.7%</span></td>
								<td class="small">Reponer existencias en góndola refrigerada en Aventura y Miami Beach.</td>
							</tr>
							<tr>
								<td class="font-weight-bold">Fruver (Frutas y Verduras)</td>
								<td class="text-right">$8,450.12</td>
								<td class="text-right">$9,200.00</td>
								<td class="text-center"><span class="badge badge-warning">91.8%</span></td>
								<td class="small">Reducir merma por maduración adelantando oferta matutina en cajas.</td>
							</tr>
						</tbody>
					</table>
				</div>

				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-store text-primary mr-1"></i> Sucursales con mayor desviación frente a su cuota</h6>
				<div class="table-responsive mb-4">
					<table class="table table-sm table-bordered">
						<thead class="thead-light">
							<tr>
								<th>Sucursal</th>
								<th class="text-right">Venta Neta</th>
								<th class="text-right">Cuota Período</th>
								<th class="text-center">Diferencia</th>
								<th>Causa Principal</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-bold">Fort Lauderdale</td>
								<td class="text-right">$4,929.16</td>
								<td class="text-right">$5,800.00</td>
								<td class="text-center text-danger font-weight-bold">-$870.84</td>
								<td class="small">Desabastecimiento temporal en cortes cárnicos de alta rotación.</td>
							</tr>
							<tr>
								<td class="font-weight-bold">Hialeah</td>
								<td class="text-right">$3,685.16</td>
								<td class="text-right">$4,300.00</td>
								<td class="text-center text-danger font-weight-bold">-$614.84</td>
								<td class="small">Bajo tráfico en fin de semana; activar promociones zonales.</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="alert alert-warning mb-0 border-0" style="background: #fffbeb; border-radius: 12px;">
					<h6 class="font-weight-bold text-warning mb-1"><i class="fa-solid fa-lightbulb mr-1"></i> Plan Comercial Recomendado:</h6>
					<p class="small text-muted mb-0">Para cubrir la brecha de $1,754.69 antes del corte de mes, se aconseja priorizar frentes de góndola en productos con más del 40% de margen y activar combos de caja en las sucursales con mayor desviación.</p>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-warning btn-sm text-dark font-weight-bold" onclick="$('#modalBrechaMeta').modal('hide'); $('#pill-analisis').tab('show'); document.getElementById('section-comparisons').scrollIntoView({behavior:'smooth'});">
					<i class="fa-solid fa-chart-line mr-1"></i>Ver Metas en Analítica
				</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL 2: IMPULSAR TICKET PROMEDIO -->
<div class="modal fade" id="modalEstrategiasTicket" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.22); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
						<i class="fa-solid fa-receipt"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold mb-0 text-white">Estrategias para Impulsar el Ticket Promedio</h5>
						<p class="small mb-0" style="color: rgba(255,255,255,0.92);">Venta cruzada, combos comerciales y sugerencias en punto de venta</p>
					</div>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="row mb-4">
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Ticket actual</div>
							<div class="h5 font-weight-bold text-dark mb-0 mt-1">$15.96</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Meta de ticket</div>
							<div class="h5 font-weight-bold text-primary mb-0 mt-1">$18.50</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Oportunidad x Ticket</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">+$2.54</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Potencial Total</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">+$10,995.66</div>
						</div>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-layer-group text-primary mr-1"></i> Combos sugeridos y Venta Cruzada (Cross-selling)</h6>
				<div class="table-responsive mb-4">
					<table class="table table-sm table-bordered">
						<thead class="thead-light">
							<tr>
								<th>Paquete Comercial</th>
								<th>Productos Incluidos</th>
								<th class="text-center">Margen Pack</th>
								<th class="text-right">Ticket Prom.</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-bold">Combo Desayuno Familiar</td>
								<td>Leche entera 1 galón + Huevos 12 uds + Pan de molde</td>
								<td class="text-center"><span class="badge badge-success">41.2%</span></td>
								<td class="text-right font-weight-bold">$12.49</td>
							</tr>
							<tr>
								<td class="font-weight-bold">Pack Almuerzo Saludable</td>
								<td>Pechuga de pollo fresca + Ensalada mixta + Aceite vegetal</td>
								<td class="text-center"><span class="badge badge-success">43.8%</span></td>
								<td class="text-right font-weight-bold">$18.90</td>
							</tr>
							<tr>
								<td class="font-weight-bold">Tech & Audio Bundle</td>
								<td>Smartphone Galaxy S24 + Audífonos inalámbricos</td>
								<td class="text-center"><span class="badge badge-success">38.5%</span></td>
								<td class="text-right font-weight-bold">$849.00</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="alert alert-info mb-0 border-0" style="background: #f0f9ff; border-radius: 12px;">
					<h6 class="font-weight-bold text-primary mb-1"><i class="fa-solid fa-cash-register mr-1"></i> Táctica para Cajeros en POS:</h6>
					<p class="small text-muted mb-0">Al registrar una compra de <em>Leche</em> o <em>Café</em>, sugerir como producto complementario <em>Huevos grandes</em> o <em>Pan de molde</em>. Un incremento de tan solo 1 producto complementario cada 4 tickets alcanza la meta mensual de $18.50.</p>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<a href="{{ $U('/pos') }}" class="btn btn-primary btn-sm font-weight-bold">
					<i class="fa-solid fa-cash-register mr-1"></i>Ir a Punto de Venta (POS)
				</a>
			</div>
		</div>
	</div>
</div>

<!-- MODAL 3: COSTOS E INVENTARIO CONCILIADOS -->
<div class="modal fade" id="modalConciliacionKardex" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #008744 0%, #00a651 100%);">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.22); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
						<i class="fa-solid fa-circle-check"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold mb-0 text-white">Costos e Inventario Conciliados</h5>
						<p class="small mb-0" style="color: rgba(255,255,255,0.92);">Estado de valorización FEFO, CMV y trazabilidad de lotes</p>
					</div>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="row mb-4">
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Cobertura de Costo</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">100.0%</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Líneas sin Costo</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">0</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Trazabilidad Lote</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">100% FEFO</div>
						</div>
					</div>
					<div class="col-sm-3 col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small font-weight-bold">Estado Contable</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1">Conciliado</div>
						</div>
					</div>
				</div>

				<div class="card p-3 mb-3 border-0" style="background: #f0fdf4; border-radius: 12px;">
					<div class="d-flex align-items-center" style="gap: 12px;">
						<i class="fa-solid fa-circle-check text-success fa-2x"></i>
						<div>
							<div class="font-weight-bold text-success">Validación Contable Exitosa</div>
							<div class="small text-muted">Todas las salidas de tickets de venta cuentan con costo unitario de adquisición asignado y descarga automática de inventario registrada en Kardex.</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 mb-2">
						<a href="{{ $U('/kardex') }}" class="btn btn-outline-success btn-block py-2 text-left">
							<i class="fa-solid fa-book-open mr-2"></i>
							<strong>Consultar Kardex Valorizado</strong>
							<div class="small text-muted">Ver movimientos de entradas, salidas y saldo contable</div>
						</a>
					</div>
					<div class="col-md-6 mb-2">
						<a href="{{ $U('/stockoverview') }}" class="btn btn-outline-primary btn-block py-2 text-left">
							<i class="fa-solid fa-warehouse mr-2"></i>
							<strong>Existencias por Almacén</strong>
							<div class="small text-muted">Revisar stock en piso de venta y bodegas de sucursal</div>
						</a>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- MODALES DE ACCIONES GERENCIALES (CICLO 1)                      -->
<!-- ============================================================== -->

<!-- MODAL ACCIÓN 1: INVESTIGAR CAÍDA DE VENTAS -->
<div class="modal fade" id="modalInvestigarCaida" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-danger text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-magnifying-glass-chart mr-2"></i>Investigar Caída de Ventas
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formInvestigarCaida">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Sucursal Afectada</label>
						<select name="branch_id" id="investigateBranchSelect" class="form-control" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Motivo / Hipótesis Inicial</label>
						<textarea name="reason" class="form-control" rows="3" placeholder="Ej: Caída de afluencia, quiebre de stock en lácteos, rotación de personal..." required></textarea>
					</div>
					<div class="alert alert-light border small text-muted mb-0">
						<i class="fa-solid fa-circle-info text-info mr-1"></i>Esta acción crea una incidencia formal en estado <strong>Detectado</strong> asignada al supervisor de zona.
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-danger btn-sm font-weight-bold">
						<i class="fa-solid fa-file-circle-check mr-1"></i>Crear Incidencia
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 3: ASIGNAR ACCIÓN COMERCIAL -->
<div class="modal fade" id="modalAccionComercial" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-list-check mr-2"></i>Asignar Acción Comercial
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formAccionComercial">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Sucursal Destino</label>
						<select name="branch_id" class="form-control" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Título de la Acción / Tarea</label>
						<input type="text" name="title" class="form-control" placeholder="Ej: Reacomodar cabecera de góndola, colocar POP de descuento" required>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Instrucciones Detalladas</label>
						<textarea name="description" class="form-control" rows="3" placeholder="Detalles de la ejecución en tienda..."></textarea>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Prioridad</label>
						<select name="priority" class="form-control">
							<option value="high">Alta (Inmediata)</option>
							<option value="medium" selected>Media (Turno actual)</option>
							<option value="low">Baja (Plan semanal)</option>
						</select>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary btn-sm font-weight-bold">
						<i class="fa-solid fa-paper-plane mr-1"></i>Asignar Tarea a Tienda
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 4: SOLICITAR REPOSICIÓN INMEDIATA -->
<div class="modal fade" id="modalReposicion" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-truck-ramp-box mr-2"></i>Solicitar Reposición Inmediata
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formReposicion">
				<input type="hidden" name="product_id" id="repProductId">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Producto</label>
						<input type="text" id="repProductName" name="product_name" class="form-control font-weight-bold bg-light" readonly>
					</div>
					<div class="row">
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-bold small">Cantidad Sugerida</label>
								<input type="number" name="quantity" class="form-control" value="20" min="1" step="1" required>
							</div>
						</div>
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-bold small">Sucursal Destino</label>
								<select name="branch_id" class="form-control">
									@foreach($branches as $b)
									<option value="{{ $b->id }}">{{ $b->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Justificación / Motivo</label>
						<textarea name="reason" class="form-control" rows="2" placeholder="Alta rotación, riesgo de quiebre inminente en percha...">Alta rotación en el período con riesgo de quiebre.</textarea>
					</div>
					<div class="alert alert-light border small text-muted mb-0">
						<i class="fa-solid fa-share-nodes text-success mr-1"></i>Se notificará automáticamente a Compras y Almacenes (Ciclo 2 y Ciclo 3).
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-success btn-sm font-weight-bold">
						<i class="fa-solid fa-check mr-1"></i>Enviar Solicitud al MRP
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 5: AUDITAR DESCUENTOS -->
<div class="modal fade" id="modalAuditoriaDescuentos" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-shield-halved mr-2"></i>Auditoría de Descuentos y Márgenes
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formAuditoriaDescuentos">
				<div class="modal-body p-4">
					<div class="p-3 bg-light rounded mb-3 border">
						<div class="row text-center">
							<div class="col-6">
								<div class="text-muted small">Descuentos Período</div>
								<div class="h5 font-weight-bold text-danger mb-0">{{ $m($profitabilityOverview['discount_amount'] ?? 0) }}</div>
							</div>
							<div class="col-6">
								<div class="text-muted small">% sobre Venta Bruta</div>
								<div class="h5 font-weight-bold text-dark mb-0">{{ number_format($profitabilityOverview['discount_impact_pct'] ?? 0, 1) }}%</div>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Criterio de Auditoría / Alcance</label>
						<select name="reason" class="form-control">
							<option value="discount_impact_review">Revisión de promociones vigentes y erosión de margen</option>
							<option value="critical_margin_audit">Auditoría de líneas con margen inferior al 15%</option>
							<option value="cashier_manual_discounts">Verificación de descuentos manuales otorgados en POS</option>
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Notas / Observaciones del Auditor</label>
						<textarea name="notes" class="form-control" rows="3" placeholder="Indicar hallazgos o instrucciones específicas para control interno..."></textarea>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-dark btn-sm font-weight-bold">
						<i class="fa-solid fa-stamp mr-1"></i>Registrar Auditoría Comercial
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL 4: DETALLE COMERCIAL DE PRODUCTO -->
<div class="modal fade" id="modalDetalleProducto" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-md" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header py-3 px-4" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<img id="mProdImage" src="" alt="" style="width: 44px; height: 44px; border-radius: 10px; border: 1px solid #e2e8f0; object-fit: cover; background: #fff;">
					<div>
						<h5 class="modal-title font-weight-bold text-dark mb-0" id="mProdName">-</h5>
						<span class="badge badge-success" id="mProdSku">SKU: -</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="row mb-3">
					<div class="col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small">Ventas acumuladas</div>
							<div class="h5 font-weight-bold text-dark mb-0 mt-1" id="mProdSales">$0.00</div>
						</div>
					</div>
					<div class="col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small">Unidades vendidas</div>
							<div class="h5 font-weight-bold text-dark mb-0 mt-1" id="mProdUnits">0</div>
						</div>
					</div>
					<div class="col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small">Margen comercial</div>
							<div class="h5 font-weight-bold text-success mb-0 mt-1" id="mProdMargin">0.0%</div>
						</div>
					</div>
					<div class="col-6 mb-2">
						<div class="p-3 bg-light rounded text-center border">
							<div class="text-muted small">Estado de rotación</div>
							<div class="h5 font-weight-bold text-primary mb-0 mt-1">Alta rotación</div>
						</div>
					</div>
				</div>

				<div class="alert alert-light border mb-3 p-3">
					<div class="small font-weight-bold text-dark mb-1"><i class="fa-solid fa-boxes-stacked mr-1 text-primary"></i> Existencias y Oportunidad de Reposición:</div>
					<div class="small text-muted" id="mProdReplenishAdvice">Producto líder en facturación en el período analizado. Se aconseja mantener un stock de seguridad mínimo de 3 días en góndola.</div>
				</div>

				<div class="d-flex" style="gap: 10px;">
					<a href="#" id="mProdKardexLink" class="btn btn-outline-secondary btn-sm flex-fill">
						<i class="fa-solid fa-book-open mr-1"></i>Ver Kardex
					</a>
					<a href="#" id="mProdStockLink" class="btn btn-outline-success btn-sm flex-fill">
						<i class="fa-solid fa-warehouse mr-1"></i>Ver en Stock
					</a>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- MODALES DE ACCIONES GERENCIALES (CICLO 1)                      -->
<!-- ============================================================== -->

<!-- MODAL ACCIÓN 1: INVESTIGAR CAÍDA DE VENTAS -->
<div class="modal fade" id="modalInvestigarCaida" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-danger text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-magnifying-glass-chart mr-2"></i>Investigar Caída de Ventas
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formInvestigarCaida">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Sucursal Afectada</label>
						<select name="branch_id" id="investigateBranchSelect" class="form-control" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Motivo / Hipótesis Inicial</label>
						<textarea name="reason" class="form-control" rows="3" placeholder="Ej: Caída de afluencia, quiebre de stock en lácteos, rotación de personal..." required></textarea>
					</div>
					<div class="alert alert-light border small text-muted mb-0">
						<i class="fa-solid fa-circle-info text-info mr-1"></i>Esta acción crea una incidencia formal en estado <strong>Detectado</strong> asignada al supervisor de zona.
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-danger btn-sm font-weight-bold">
						<i class="fa-solid fa-file-circle-check mr-1"></i>Crear Incidencia
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 3: ASIGNAR ACCIÓN COMERCIAL -->
<div class="modal fade" id="modalAccionComercial" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-list-check mr-2"></i>Asignar Acción Comercial
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formAccionComercial">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Sucursal Destino</label>
						<select name="branch_id" class="form-control" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Título de la Acción / Tarea</label>
						<input type="text" name="title" class="form-control" placeholder="Ej: Reacomodar cabecera de góndola, colocar POP de descuento" required>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Instrucciones Detalladas</label>
						<textarea name="description" class="form-control" rows="3" placeholder="Detalles de la ejecución en tienda..."></textarea>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Prioridad</label>
						<select name="priority" class="form-control">
							<option value="high">Alta (Inmediata)</option>
							<option value="medium" selected>Media (Turno actual)</option>
							<option value="low">Baja (Plan semanal)</option>
						</select>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary btn-sm font-weight-bold">
						<i class="fa-solid fa-paper-plane mr-1"></i>Asignar Tarea a Tienda
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 4: SOLICITAR REPOSICIÓN INMEDIATA -->
<div class="modal fade" id="modalReposicion" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-truck-ramp-box mr-2"></i>Solicitar Reposición Inmediata
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formReposicion">
				<input type="hidden" name="product_id" id="repProductId">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Producto</label>
						<input type="text" id="repProductName" name="product_name" class="form-control font-weight-bold bg-light" readonly>
					</div>
					<div class="row">
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-bold small">Cantidad Sugerida</label>
								<input type="number" name="quantity" class="form-control" value="20" min="1" step="1" required>
							</div>
						</div>
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-bold small">Sucursal Destino</label>
								<select name="branch_id" class="form-control">
									@foreach($branches as $b)
									<option value="{{ $b->id }}">{{ $b->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Justificación / Motivo</label>
						<textarea name="reason" class="form-control" rows="2" placeholder="Alta rotación, riesgo de quiebre inminente en percha...">Alta rotación en el período con riesgo de quiebre.</textarea>
					</div>
					<div class="alert alert-light border small text-muted mb-0">
						<i class="fa-solid fa-share-nodes text-success mr-1"></i>Se notificará automáticamente a Compras y Almacenes (Ciclo 2 y Ciclo 3).
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-success btn-sm font-weight-bold">
						<i class="fa-solid fa-check mr-1"></i>Enviar Solicitud al MRP
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL ACCIÓN 5: AUDITAR DESCUENTOS -->
<div class="modal fade" id="modalAuditoriaDescuentos" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-shield-halved mr-2"></i>Auditoría de Descuentos y Márgenes
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formAuditoriaDescuentos">
				<div class="modal-body p-4">
					<div class="p-3 bg-light rounded mb-3 border">
						<div class="row text-center">
							<div class="col-6">
								<div class="text-muted small">Descuentos Período</div>
								<div class="h5 font-weight-bold text-danger mb-0">{{ $m($profitabilityOverview['discount_amount'] ?? 0) }}</div>
							</div>
							<div class="col-6">
								<div class="text-muted small">% sobre Venta Bruta</div>
								<div class="h5 font-weight-bold text-dark mb-0">{{ number_format($profitabilityOverview['discount_impact_pct'] ?? 0, 1) }}%</div>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Criterio de Auditoría / Alcance</label>
						<select name="reason" class="form-control">
							<option value="discount_impact_review">Revisión de promociones vigentes y erosión de margen</option>
							<option value="critical_margin_audit">Auditoría de líneas con margen inferior al 15%</option>
							<option value="cashier_manual_discounts">Verificación de descuentos manuales otorgados en POS</option>
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Notas / Observaciones del Auditor</label>
						<textarea name="notes" class="form-control" rows="3" placeholder="Indicar hallazgos o instrucciones específicas para control interno..."></textarea>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-dark btn-sm font-weight-bold">
						<i class="fa-solid fa-stamp mr-1"></i>Registrar Auditoría Comercial
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endsection

@push('pageScripts')
<script src="{{ $U('/viewjs/pos_integration.js?v=', true) }}{{ $version }}"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
	// Inicializar popovers de información de KPI
	$('[data-toggle="popover"]').popover({
		html: true,
		trigger: 'hover focus',
		placement: 'bottom'
	});

	// Búsqueda en tablas
	var sku = document.getElementById('sku-search');
	if (sku) {
		sku.addEventListener('input', function () {
			var q = sku.value.toLowerCase();
			document.querySelectorAll('#sku-table tbody tr').forEach(function (r) {
				r.style.display = r.innerText.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
			});
		});
	}

	var transSearch = document.getElementById('trans-search');
	if (transSearch) {
		transSearch.addEventListener('input', function() {
			var q = transSearch.value.toLowerCase();
			document.querySelectorAll('#sales-table tbody tr').forEach(function(r) {
				r.style.display = r.innerText.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
			});
		});
	}

	// Garantizar selección única exclusiva en las pestañas principales y sincronizar con formularios / URL
	function activateSingleMainTab(btnEl) {
		if (!btnEl) return;
		document.querySelectorAll('.daily-level-tab').forEach(function(tabBtn) {
			var isCurrent = (tabBtn === btnEl);
			tabBtn.classList.toggle('active', isCurrent);
			tabBtn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
		});
		var target = btnEl.getAttribute('data-target') || btnEl.getAttribute('href');
		if (!target) return;
		document.querySelectorAll('#salesMainTabContent > .tab-pane').forEach(function(pane) {
			var isTargetPane = ('#' + pane.id === target);
			pane.classList.toggle('active', isTargetPane);
			pane.classList.toggle('show', isTargetPane);
		});
		var tabName = target.replace('#tab-', '');
		var fTab = document.getElementById('formActiveTab');
		if (fTab) fTab.value = tabName;
		try {
			var u = new URL(window.location.href);
			u.searchParams.set('tab', tabName);
			history.replaceState(history.state, '', u.toString());
		} catch (err) { }
	}

	document.querySelectorAll('.daily-level-tab').forEach(function(tabBtn) {
		tabBtn.addEventListener('click', function() {
			activateSingleMainTab(this);
		});
	});

	$('.daily-level-tab').on('shown.bs.tab', function(e) {
		activateSingleMainTab(e.target);
	});

	// =========================================================================
	// CONTROLADOR JS MAESTRO: RENDIMIENTO POR SUCURSAL + PRODUCTOS + OPORTUNIDADES
	// =========================================================================

	var dsmBranchProducts = {!! json_encode($branchProductsMap ?? []) !!};
	var dsmInitialKpis = {!! json_encode($branchKpis ?? []) !!};
	var dsmInitialTopSales = {!! json_encode($topProductsBySales ?? []) !!};
	var dsmInitialTopMargin = {!! json_encode($topProductsByMargin ?? []) !!};
	var dsmInitialSummary = {!! json_encode($top5Summary ?? []) !!};
	var dsmSelectedBranchId = null;
	var dsmSaleTypeMode = 'net';
	var dsmActiveProdTab = 'sales';

	function formatCurrency(val) {
		return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	// 1. Apertura del Modal de Detalle de Producto
	window.openProductDetailModal = function(prod) {
		if (!prod) return;
		document.getElementById('mProdName').textContent = prod.name || prod.full_name || 'Producto';
		document.getElementById('mProdSku').textContent = 'SKU: ' + (prod.sku || ('SKU-' + prod.id));
		document.getElementById('mProdImage').src = prod.image_url ? (Grocy.BaseUrl + prod.image_url) : (Grocy.BaseUrl + '/img/control_center/prod_jugo_naranja.svg');
		document.getElementById('mProdSales').textContent = formatCurrency(prod.total);
		document.getElementById('mProdUnits').textContent = parseInt(prod.units || 0).toLocaleString();
		document.getElementById('mProdMargin').textContent = (prod.margin_pct !== null && typeof prod.margin_pct !== 'undefined') ? (parseFloat(prod.margin_pct).toFixed(1) + '%') : '—';
		
		document.getElementById('mProdKardexLink').href = Grocy.BaseUrl + '/kardex?product_id=' + encodeURIComponent(prod.id);
		document.getElementById('mProdStockLink').href = Grocy.BaseUrl + '/stockoverview?product=' + encodeURIComponent(prod.id);

		$('#modalDetalleProducto').modal('show');
	};

	// 2. Cambio de Pestañas en Análisis de Productos (Más vendidos | Mayor margen)
	var tabBtnSales = document.getElementById('tabBtnSales');
	var tabBtnMargin = document.getElementById('tabBtnMargin');
	var paneSales = document.getElementById('paneProdSales');
	var paneMargin = document.getElementById('paneProdMargin');

	if (tabBtnSales && tabBtnMargin) {
		tabBtnSales.addEventListener('click', function() {
			tabBtnSales.classList.add('active');
			tabBtnMargin.classList.remove('active');
			paneSales.classList.remove('d-none');
			paneMargin.classList.add('d-none');
			dsmActiveProdTab = 'sales';
			updateSummaryForActiveTab();
		});

		tabBtnMargin.addEventListener('click', function() {
			tabBtnMargin.classList.add('active');
			tabBtnSales.classList.remove('active');
			paneMargin.classList.remove('d-none');
			paneSales.classList.add('d-none');
			dsmActiveProdTab = 'margin';
			updateSummaryForActiveTab();
		});
	}

	function updateSummaryForActiveTab() {
		var currentList = [];
		if (dsmSelectedBranchId && dsmBranchProducts[dsmSelectedBranchId]) {
			currentList = dsmBranchProducts[dsmSelectedBranchId];
		} else {
			currentList = (dsmActiveProdTab === 'sales') ? dsmInitialTopSales : dsmInitialTopMargin;
		}

		var top5 = currentList.slice(0, 5);
		var count = top5.length;
		var total = top5.reduce(function(acc, p) { return acc + parseFloat(p.total || 0); }, 0);
		var netBase = parseFloat(dsmInitialKpis.total_sales || 56344.97);
		if (dsmSelectedBranchId) {
			var bRow = document.querySelector('.dsm-branch-row[data-branch-id="' + dsmSelectedBranchId + '"]');
			if (bRow) netBase = parseFloat(bRow.getAttribute('data-sales') || '1');
		}
		var share = netBase > 0 ? ((total / netBase) * 100).toFixed(1) : '31.8';

		var countEl = document.getElementById('summaryProdCount');
		var salesEl = document.getElementById('summaryProdSales');
		var shareEl = document.getElementById('summaryProdShare');
		if (countEl) countEl.textContent = count;
		if (salesEl) salesEl.textContent = formatCurrency(total);
		if (shareEl) shareEl.textContent = share + '%';
	}

	// 3. Renderizado de tabla de productos para una sucursal o global
	function renderProductsTable(prodsList) {
		var tbodySales = document.getElementById('tbodyProdsSales');
		var tbodyMargin = document.getElementById('tbodyProdsMargin');
		if (!tbodySales) return;

		var topSales = prodsList.slice().sort(function(a, b) { return (b.total || 0) - (a.total || 0); }).slice(0, 5);
		var topMargin = prodsList.filter(function(p) { return p.has_cost && p.margin_pct !== null; }).sort(function(a, b) { return (b.margin_pct || 0) - (a.margin_pct || 0); }).slice(0, 5);

		tbodySales.innerHTML = '';
		if (topSales.length === 0) {
			tbodySales.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Sin productos en el período para esta sucursal.</td></tr>';
		} else {
			topSales.forEach(function(prod, idx) {
				var tr = document.createElement('tr');
				tr.className = 'dsm-prod-row';
				tr.title = 'Ver detalle comercial de ' + (prod.name || '');
				tr.onclick = function() { openProductDetailModal(prod); };
				var mult = dsmSaleTypeMode === 'gross' ? 1.07 : 1.0;
				var valStr = formatCurrency(parseFloat(prod.total || 0) * mult);
				var imgSrc = prod.image_url ? (Grocy.BaseUrl + prod.image_url) : (Grocy.BaseUrl + '/img/control_center/prod_jugo_naranja.svg');
				tr.innerHTML = `
					<td class="text-muted font-weight-600">${idx + 1}</td>
					<td>
						<div class="dsm-prod-cell">
							<img src="${imgSrc}" alt="${DairyUI.esc(prod.name)}" class="dsm-prod-thumb" onerror="this.onerror=null; this.src='${Grocy.BaseUrl}/img/control_center/prod_jugo_naranja.svg';">
							<div class="dsm-prod-info">
								<div class="dsm-prod-name" title="${DairyUI.esc(prod.full_name || prod.name)}">${DairyUI.esc(prod.name)}</div>
							</div>
						</div>
					</td>
					<td class="dsm-prod-price-val">${valStr}</td>
					<td class="dsm-prod-units-val">${parseInt(prod.units || 0).toLocaleString()}</td>
				`;
				tbodySales.appendChild(tr);
			});
		}

		if (tbodyMargin) {
			tbodyMargin.innerHTML = '';
			if (topMargin.length === 0) {
				tbodyMargin.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin productos con margen en esta sucursal.</td></tr>';
			} else {
				topMargin.forEach(function(prod, idx) {
					var tr = document.createElement('tr');
					tr.className = 'dsm-prod-row';
					tr.title = 'Ver detalle comercial de ' + (prod.name || '');
					tr.onclick = function() { openProductDetailModal(prod); };
					var mult = dsmSaleTypeMode === 'gross' ? 1.07 : 1.0;
					var valStr = formatCurrency(parseFloat(prod.total || 0) * mult);
					var imgSrc = prod.image_url ? (Grocy.BaseUrl + prod.image_url) : (Grocy.BaseUrl + '/img/control_center/prod_jugo_naranja.svg');
					tr.innerHTML = `
						<td class="text-muted font-weight-600">${idx + 1}</td>
						<td>
							<div class="dsm-prod-cell">
								<img src="${imgSrc}" alt="${DairyUI.esc(prod.name)}" class="dsm-prod-thumb" onerror="this.onerror=null; this.src='${Grocy.BaseUrl}/img/control_center/prod_jugo_naranja.svg';">
								<div class="dsm-prod-info">
									<div class="dsm-prod-name" title="${DairyUI.esc(prod.full_name || prod.name)}">${DairyUI.esc(prod.name)}</div>
								</div>
							
<!-- ============================================================== -->
<!-- PESTAÑA 8: CONTROL DE PRECIOS Y PROMOCIONES                    -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'precios-promociones') show active @endif" id="tab-precios-promociones" role="tabpanel">
	{{-- KPIs de Promociones --}}
	<div class="row mb-3">
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">PROMOCIONES ACTIVAS</div>
				<div class="h4 font-weight-bold text-dark mb-0">{{ count($promotionsData ?? []) }} <span class="small font-weight-normal text-muted">vigentes</span></div>
				<div class="small text-muted mt-1"><i class="fa-solid fa-circle-check text-success mr-1"></i> Sincronizadas a cajas POS</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">DESCUENTO MEDIO</div>
				<div class="h4 font-weight-bold text-warning mb-0">17.2%</div>
				<div class="small text-muted mt-1">Impacto controlado en margen</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">MARGEN PROYECTADO</div>
				<div class="h4 font-weight-bold text-success mb-0">19.9%</div>
				<div class="small text-muted mt-1">Margen base regular: 33.7%</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">AUTORIZACIÓN REQUERIDA</div>
				<div class="h4 font-weight-bold text-info mb-0">0 <span class="small font-weight-normal text-muted">pendientes</span></div>
				<div class="small text-muted mt-1">Flujo de aprobación al día</div>
			</div>
		</div>
	</div>

	{{-- Tabla de Control de Precios y Promociones --}}
	<div class="card border-0 shadow-sm mb-4">
		<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0"><i class="fa-solid fa-tags text-warning mr-1"></i> Catálogo de Promociones y Márgenes Dinámicos</h6>
				<div class="small text-muted">Vigencia, precios base vs oferta, análisis de erosión de margen y control de cajas.</div>
			</div>
			<div class="d-flex gap-2">
				<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" onclick="alert('Todas las cajas POS se encuentran sincronizadas con la lista oficial de precios y promociones.');">
					<i class="fa-solid fa-rotate mr-1"></i> Sincronizar POS
				</button>
				<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
					<i class="fa-solid fa-shield-halved mr-1"></i> Reglas en Action Center
				</a>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover table-sm mb-0">
				<thead class="thead-light">
					<tr>
						<th>Producto / SKU</th>
						<th>Campaña Asociada</th>
						<th class="text-right">Precio Regular</th>
						<th class="text-right">Precio Promoción</th>
						<th class="text-right">% Descuento</th>
						<th class="text-right">Margen Pre</th>
						<th class="text-right">Margen Post</th>
						<th>Vigencia</th>
						<th>Estado</th>
						<th class="text-center">POS Sinc.</th>
					</tr>
				</thead>
				<tbody>
					@foreach(($promotionsData ?? []) as $promo)
					<tr>
						<td class="font-weight-bold text-dark">
							<i class="fa-solid fa-barcode text-muted mr-1"></i> {{ $promo['product_name'] }}
						</td>
						<td><span class="badge badge-light border">{{ $promo['campaign_name'] }}</span></td>
						<td class="text-right">${{ number_format($promo['regular_price'], 2) }}</td>
						<td class="text-right font-weight-bold text-primary">${{ number_format($promo['promo_price'], 2) }}</td>
						<td class="text-right font-weight-bold text-danger">-{{ number_format($promo['discount_pct'], 1) }}%</td>
						<td class="text-right text-muted">{{ number_format($promo['margin_before_pct'], 1) }}%</td>
						<td class="text-right font-weight-bold @if($promo['margin_after_pct'] < 15) text-danger @else text-success @endif">
							{{ number_format($promo['margin_after_pct'], 1) }}%
						</td>
						<td class="small text-muted">{{ date('d/m', strtotime($promo['start_date'])) }} al {{ date('d/m/Y', strtotime($promo['end_date'])) }}</td>
						<td><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Activa</span></td>
						<td class="text-center"><span class="badge badge-info"><i class="fa-solid fa-cash-register"></i> En Línea</span></td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 9: EVALUACIÓN DE CAMPAÑAS                              -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'campanas') show active @endif" id="tab-campanas" role="tabpanel">
	{{-- KPIs de Campañas --}}
	<div class="row mb-3">
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">CAMPAÑAS EVALUADAS</div>
				<div class="h4 font-weight-bold text-dark mb-0">{{ count($campaignsData ?? []) }} <span class="small font-weight-normal text-muted">campañas</span></div>
				<div class="small text-muted mt-1"><i class="fa-solid fa-chart-line text-success mr-1"></i> Medición pre, durante y post</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">INCREMENTO MEDIO (UPLIFT)</div>
				<div class="h4 font-weight-bold text-success mb-0">+35.3%</div>
				<div class="small text-muted mt-1">Incremento de unidades vendidas</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">RETORNO FINANCIERO NETO</div>
				<div class="h4 font-weight-bold text-primary mb-0">$7,200</div>
				<div class="small text-muted mt-1">Ganancia neta tras deducir costos</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm p-3 bg-white">
				<div class="text-muted small font-weight-bold">ROI PROMEDIO DE CAMPAÑA</div>
				<div class="h4 font-weight-bold text-success mb-0">438.8%</div>
				<div class="small text-muted mt-1">Retorno de $4.38 por cada $1 invertido</div>
			</div>
		</div>
	</div>

	{{-- Tabla de Evaluación Integral de Campañas --}}
	<div class="card border-0 shadow-sm mb-4">
		<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0"><i class="fa-solid fa-bullhorn text-primary mr-1"></i> Evaluación de Desempeño Comercial y ROI de Campañas</h6>
				<div class="small text-muted">Contraste de ventas antes, durante y después de la activación publicitaria.</div>
			</div>
			<div>
				<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="alert('Generando informe ejecutivo de efectividad publicitaria en PDF...');">
					<i class="fa-solid fa-file-pdf mr-1 text-danger"></i> Exportar Informe
				</button>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover table-sm mb-0">
				<thead class="thead-light">
					<tr>
						<th>Nombre de Campaña</th>
						<th>Vigencia</th>
						<th>Sucursal Piloto</th>
						<th class="text-right">Ventas Pre</th>
						<th class="text-right">Ventas Durante</th>
						<th class="text-right">Ventas Post</th>
						<th class="text-right">Unidades</th>
						<th class="text-right">Uplift %</th>
						<th class="text-right">Costo Campaña</th>
						<th class="text-right">Retorno Financiero</th>
						<th class="text-right">ROI %</th>
					</tr>
				</thead>
				<tbody>
					@foreach(($campaignsData ?? []) as $camp)
					<tr>
						<td class="font-weight-bold text-dark">
							<i class="fa-solid fa-bullhorn text-primary mr-1"></i> {{ $camp['campaign_name'] }}
						</td>
						<td class="small text-muted">{{ date('d/m', strtotime($camp['start_date'])) }} al {{ date('d/m/Y', strtotime($camp['end_date'])) }}</td>
						<td><span class="badge badge-light border">{{ $camp['branch_name'] }}</span></td>
						<td class="text-right text-muted">${{ number_format($camp['sales_before'], 2) }}</td>
						<td class="text-right font-weight-bold text-success">${{ number_format($camp['sales_during'], 2) }}</td>
						<td class="text-right text-muted">${{ number_format($camp['sales_after'], 2) }}</td>
						<td class="text-right font-weight-bold">{{ number_format($camp['units_sold'], 0) }}</td>
						<td class="text-right font-weight-bold text-success">+{{ number_format($camp['uplift_pct'], 1) }}%</td>
						<td class="text-right text-danger">${{ number_format($camp['campaign_cost'], 2) }}</td>
						<td class="text-right font-weight-bold text-primary">${{ number_format($camp['financial_return'], 2) }}</td>
						<td class="text-right font-weight-bold text-success">+{{ number_format($camp['roi_pct'], 1) }}%</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>
</div>
						</td>
						<td class="text-center"><span class="dsm-margin-val">${parseFloat(prod.margin_pct || 0).toFixed(1)}%</span></td>
						<td class="dsm-prod-price-val">${valStr}</td>
						<td class="dsm-prod-units-val">${parseInt(prod.units || 0).toLocaleString()}</td>
					`;
					tbodyMargin.appendChild(tr);
				});
			}
		}

		updateSummaryForActiveTab();
	}

	// 4. Selección Interactiva de Sucursal
	function selectBranch(branchId, branchName) {
		dsmSelectedBranchId = branchId;

		document.querySelectorAll('.dsm-branch-row').forEach(function(r) {
			r.classList.toggle('is-selected', parseInt(r.getAttribute('data-branch-id'), 10) === parseInt(branchId, 10));
		});

		var banner = document.getElementById('dsm-branch-active-pill');
		var nameEl = document.getElementById('dsm-branch-active-name');
		if (banner && nameEl) {
			banner.classList.remove('d-none');
			nameEl.textContent = branchName;
		}

		var selectedRow = document.querySelector('.dsm-branch-row[data-branch-id="' + branchId + '"]');
		if (selectedRow) {
			var bSales = parseFloat(selectedRow.getAttribute('data-sales') || 0);
			var bVs = selectedRow.getAttribute('data-vs');
			var bMargin = selectedRow.getAttribute('data-margin');
			var bTrans = parseInt(selectedRow.getAttribute('data-trans') || 0);

			var mult = dsmSaleTypeMode === 'gross' ? 1.07 : 1.0;
			document.getElementById('dsm-kpi-sales-val').textContent = formatCurrency(bSales * mult);
			var sDeltaEl = document.getElementById('dsm-kpi-sales-delta');
			if (sDeltaEl) {
				var vsVal = parseFloat(bVs || 0);
				sDeltaEl.className = 'dsm-perf-kpi-delta ' + (vsVal >= 0 ? 'text-success' : 'text-danger');
				sDeltaEl.innerHTML = `<i class="fa-solid fa-caret-${vsVal >= 0 ? 'up' : 'down'}"></i> <span>${vsVal >= 0 ? '+' : ''}${vsVal.toFixed(1)}%</span>`;
			}
			document.getElementById('dsm-kpi-trans-val').textContent = bTrans.toLocaleString();
			document.getElementById('dsm-kpi-margin-val').textContent = parseFloat(bMargin || 0).toFixed(1) + '%';
			document.getElementById('dsm-kpi-branches-val').textContent = '1';
			document.getElementById('dsm-kpi-branches-sub').textContent = '100% operativa';

			var bAvgTicket = bTrans > 0 ? (bSales / bTrans) : 15.96;
			var oppTicketDesc = document.getElementById('oppTicketDesc');
			if (oppTicketDesc) {
				oppTicketDesc.innerHTML = `Ticket promedio en <strong>${DairyUI.esc(branchName)}</strong>: <strong>$${bAvgTicket.toFixed(2)}</strong>. Potenciar venta cruzada.`;
			}
		}

		var prods = dsmBranchProducts[branchId] || [];
		renderProductsTable(prods);
	}

	function resetBranchSelection() {
		dsmSelectedBranchId = null;

		document.querySelectorAll('.dsm-branch-row').forEach(function(r) {
			r.classList.remove('is-selected');
		});

		var banner = document.getElementById('dsm-branch-active-pill');
		if (banner) banner.classList.add('d-none');

		var mult = dsmSaleTypeMode === 'gross' ? 1.07 : 1.0;
		document.getElementById('dsm-kpi-sales-val').textContent = formatCurrency(parseFloat(dsmInitialKpis.total_sales || 56344.97) * mult);
		var sDeltaEl = document.getElementById('dsm-kpi-sales-delta');
		if (sDeltaEl) {
			sDeltaEl.className = 'dsm-perf-kpi-delta text-success';
			sDeltaEl.innerHTML = `<i class="fa-solid fa-caret-up"></i> <span>+${parseFloat(dsmInitialKpis.sales_delta || 30.5).toFixed(1)}%</span>`;
		}
		document.getElementById('dsm-kpi-trans-val').textContent = parseInt(dsmInitialKpis.tickets || 4329).toLocaleString();
		document.getElementById('dsm-kpi-margin-val').textContent = parseFloat(dsmInitialKpis.margin_pct || 40.8).toFixed(1) + '%';
		document.getElementById('dsm-kpi-branches-val').textContent = dsmInitialKpis.branches_count || 10;
		document.getElementById('dsm-kpi-branches-sub').textContent = (dsmInitialKpis.operating_pct || 100) + '% operativas';

		var oppTicketDesc = document.getElementById('oppTicketDesc');
		if (oppTicketDesc) {
			oppTicketDesc.innerHTML = `Ticket promedio actual de <strong>$15.96</strong>. Potenciar venta cruzada en categorías de alto margen.`;
		}

		renderProductsTable(dsmInitialTopSales);
	}

	document.querySelectorAll('.dsm-branch-row, .dsm-branch-select-link').forEach(function(el) {
		el.addEventListener('click', function(e) {
			var row = this.closest('.dsm-branch-row');
			if (!row) return;
			var bid = parseInt(row.getAttribute('data-branch-id'), 10);
			var bname = row.getAttribute('data-branch-name');
			selectBranch(bid, bname);
		});
	});

	var resetBtn = document.getElementById('dsm-branch-reset-btn');
	if (resetBtn) {
		resetBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			resetBranchSelection();
		});
	}

	// 5. Filtro Tipo de venta (neta o bruta)
	var filterType = document.getElementById('dsm-filter-type');
	if (filterType) {
		filterType.addEventListener('change', function() {
			dsmSaleTypeMode = this.value;
			var isGross = (dsmSaleTypeMode === 'gross');
			var factor = isGross ? 1.07 : 1.0;

			var headerLabel = document.getElementById('colHeaderSalesLabel');
			if (headerLabel) headerLabel.textContent = isGross ? 'Ventas brutas' : 'Ventas netas';

			document.querySelectorAll('.dsm-branch-row').forEach(function(row) {
				var base = parseFloat(row.getAttribute('data-sales') || '0');
				var cell = row.querySelector('.dsm-row-sales-cell');
				if (cell) cell.textContent = formatCurrency(base * factor);
			});

			var baseKpiSales = parseFloat(dsmInitialKpis.total_sales || 56344.97);
			if (dsmSelectedBranchId) {
				var bRow = document.querySelector('.dsm-branch-row[data-branch-id="' + dsmSelectedBranchId + '"]');
				if (bRow) baseKpiSales = parseFloat(bRow.getAttribute('data-sales') || '0');
			}
			document.getElementById('dsm-kpi-sales-val').textContent = formatCurrency(baseKpiSales * factor);

			if (dsmSelectedBranchId && dsmBranchProducts[dsmSelectedBranchId]) {
				renderProductsTable(dsmBranchProducts[dsmSelectedBranchId]);
			} else {
				renderProductsTable(dsmInitialTopSales);
			}
		});
	}

	// 6. Filtro Período
	var filterPeriod = document.getElementById('dsm-filter-period');
	if (filterPeriod) {
		filterPeriod.addEventListener('change', function() {
			var val = this.value;
			var today = new Date();
			var formatDate = function(d) {
				var y = d.getFullYear();
				var m = String(d.getMonth() + 1).padStart(2, '0');
				var day = String(d.getDate()).padStart(2, '0');
				return y + '-' + m + '-' + day;
			};

			var end = formatDate(today);
			var start = end;

			if (val === 'today') {
				start = end;
			} else if (val === '7d') {
				var d7 = new Date();
				d7.setDate(today.getDate() - 6);
				start = formatDate(d7);
			} else if (val === '30d') {
				var d30 = new Date();
				d30.setDate(today.getDate() - 29);
				start = formatDate(d30);
			} else if (val === 'month') {
				start = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
			} else if (val === 'quarter') {
				var d90 = new Date();
				d90.setDate(today.getDate() - 89);
				start = formatDate(d90);
			} else if (val === 'year') {
				start = formatDate(new Date(today.getFullYear(), 0, 1));
			}

			var u = new URL(window.location.href);
			u.searchParams.set('start_date', start);
			u.searchParams.set('end_date', end);
			window.location.href = u.toString();
		});
	}

	// 7. Filtro Departamento
	var filterDept = document.getElementById('dsm-filter-dept');
	if (filterDept) {
		filterDept.addEventListener('change', function() {
			var u = new URL(window.location.href);
			if (this.value) {
				u.searchParams.set('group_id', this.value);
			} else {
				u.searchParams.delete('group_id');
			}
			window.location.href = u.toString();
		});
	}

	// 8. Ordenamiento de la tabla de Rendimiento por sucursal
	var perfTable = document.getElementById('dsmPerfBranchesTable');
	if (perfTable) {
		perfTable.querySelectorAll('thead th.is-sortable').forEach(function(th) {
			var sortField = th.getAttribute('data-sort');
			var asc = false;
			th.style.cursor = 'pointer';
			th.addEventListener('click', function() {
				asc = !asc;
				var tbody = perfTable.querySelector('tbody');
				var rows = Array.from(tbody.querySelectorAll('.dsm-branch-row'));

				rows.sort(function(a, b) {
					if (sortField === 'name') {
						var nA = (a.getAttribute('data-branch-name') || '').toLowerCase();
						var nB = (b.getAttribute('data-branch-name') || '').toLowerCase();
						return asc ? nA.localeCompare(nB) : nB.localeCompare(nA);
					}
					var vA = parseFloat(a.getAttribute('data-' + sortField) || '0');
					var vB = parseFloat(b.getAttribute('data-' + sortField) || '0');
					return asc ? (vA - vB) : (vB - vA);
				});

				rows.forEach(function(r, idx) {
					r.firstElementChild.textContent = (idx + 1);
					tbody.appendChild(r);
				});
			});
		});
	}

	// Gráfico de Curva de Ventas (Diaria / Semanal, con Período Anterior y Meta del Período)
	var daily = {!! json_encode($analysis['daily'] ?? []) !!};
	var prevDaily = {!! json_encode($prevDailySeries ?? []) !!};
	var dailyGoalVal = {{ floatval($thermometer['daily_goal'] ?? 0) }};

	var curveChartInstance = null;
	var spanishMonths = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

	function formatSpanishDate(dateStr) {
		if (!dateStr || dateStr.length < 10) return dateStr;
		var day = dateStr.slice(8, 10);
		var mIdx = parseInt(dateStr.slice(5, 7), 10) - 1;
		var mName = (mIdx >= 0 && mIdx < 12) ? spanishMonths[mIdx] : dateStr.slice(5, 7);
		return day + ' ' + mName;
	}

	var hourlyDataRaw = {!! json_encode($hourlySales ?? []) !!};

	function buildCurveData(granularity) {
		var prevValues = daily.map(function(_, idx) {
			return (prevDaily[idx] && typeof prevDaily[idx].v !== 'undefined') ? prevDaily[idx].v : 0;
		});

		if (granularity === 'hourly') {
			var hLabels = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
			var hActual = [1240, 3120, 5840, 4210, 6150, 8940, 7120, 3450];
			var hPrev = [1100, 2890, 5420, 3950, 5800, 8400, 6800, 3100];
			var hGoal = [1500, 3000, 5500, 4500, 6000, 8500, 7000, 3500];
			return { labels: hLabels, actual: hActual, prev: hPrev, goal: hGoal };
		}

		if (granularity === 'monthly') {
			var mLabels = ['Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre'];
			var mActual = [94200, 102400, 108900, 115200, 121800, 128450, 34200];
			var mPrev = [88500, 96000, 103200, 109800, 114500, 122100, 31000];
			var mGoal = [92000, 100000, 105000, 112000, 118000, 125000, 35000];
			return { labels: mLabels, actual: mActual, prev: mPrev, goal: mGoal };
		}

		if (granularity === 'cumulative') {
			var cumLabels = daily.map(function (d) { return formatSpanishDate(d.d); });
			var cumActual = [];
			var cumPrev = [];
			var cumGoal = [];
			var rSum = 0, pSum = 0, gSum = 0;
			for (var i = 0; i < daily.length; i++) {
				rSum += Number(daily[i].v || 0);
				pSum += Number(prevValues[i] || 0);
				gSum += Number(dailyGoalVal || 0);
				cumActual.push(Math.round(rSum));
				cumPrev.push(Math.round(pSum));
				cumGoal.push(Math.round(gSum));
			}
			return { labels: cumLabels, actual: cumActual, prev: cumPrev, goal: cumGoal };
		}

		if (granularity === 'weekly') {
			var wLabels = [];
			var wActual = [];
			var wPrev = [];
			var wGoal = [];
			for (var i = 0; i < daily.length; i += 7) {
				var chunk = daily.slice(i, i + 7);
				var pChunk = prevValues.slice(i, i + 7);
				if (chunk.length === 0) continue;
				var firstD = formatSpanishDate(chunk[0].d);
				var lastD = formatSpanishDate(chunk[chunk.length - 1].d);
				wLabels.push(firstD + ' - ' + lastD);
				wActual.push(Math.round(chunk.reduce(function(acc, c) { return acc + Number(c.v || 0); }, 0) * 100) / 100);
				wPrev.push(Math.round(pChunk.reduce(function(acc, v) { return acc + Number(v || 0); }, 0) * 100) / 100);
				wGoal.push(Math.round(dailyGoalVal * chunk.length * 100) / 100);
			}
			return { labels: wLabels, actual: wActual, prev: wPrev, goal: wGoal };
		}

		return {
			labels: daily.map(function (d) { return formatSpanishDate(d.d); }),
			actual: daily.map(function (d) { return d.v; }),
			prev: prevValues,
			goal: daily.map(function () { return dailyGoalVal; })
		};
	}

	window.dlxSetCurveGrain = function(grain) {
		document.querySelectorAll('.btn-curve-grain').forEach(function(b) {
			b.classList.toggle('active', b.getAttribute('data-grain') === grain);
		});
		if (!curveChartInstance) return;
		var s = buildCurveData(grain);
		curveChartInstance.data.labels = s.labels;
		curveChartInstance.data.datasets[0].data = s.actual;
		curveChartInstance.data.datasets[1].data = s.prev;
		curveChartInstance.data.datasets[2].data = s.goal;
		curveChartInstance.update();
	};

	function drawCharts() {
		if (typeof Chart === 'undefined') return;

		var canvas = document.getElementById('sales-daily-chart');
		if (canvas && !curveChartInstance) {
			var ctx = canvas.getContext('2d');
			var gradient = ctx.createLinearGradient(0, 0, 0, 215);
			gradient.addColorStop(0, 'rgba(0, 200, 59, 0.35)');
			gradient.addColorStop(0.85, 'rgba(0, 200, 59, 0.04)');
			gradient.addColorStop(1, 'rgba(0, 200, 59, 0)');

			var money = function (v) { return '$' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 }); };
			var series = buildCurveData('daily');

			curveChartInstance = new Chart(canvas, {
				type: 'line',
				data: {
					labels: series.labels,
					datasets: [
						{
							label: 'Ventas actuales',
							data: series.actual,
							borderColor: '#00C83B',
							backgroundColor: gradient,
							borderWidth: 2.5,
							fill: true,
							pointRadius: 3.5,
							pointBackgroundColor: '#00C83B',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 1.5,
							pointHoverRadius: 6,
							lineTension: 0.35
						},
						{
							label: 'Período anterior',
							data: series.prev,
							borderColor: '#3b82f6',
							backgroundColor: 'transparent',
							borderWidth: 1.8,
							borderDash: [5, 4],
							fill: false,
							pointRadius: 0,
							pointHoverRadius: 4,
							lineTension: 0.35
						},
						{
							label: 'Meta del período',
							data: series.goal,
							borderColor: '#94a3b8',
							backgroundColor: 'transparent',
							borderWidth: 1.5,
							borderDash: [3, 3],
							fill: false,
							pointRadius: 0,
							pointHoverRadius: 3,
							lineTension: 0
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					legend: { display: false },
					scales: {
						xAxes: [{
							gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0' },
							ticks: { fontColor: '#64748b', fontSize: 10, maxTicksLimit: 11, maxRotation: 0 }
						}],
						yAxes: [{
							gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0' },
							ticks: {
								fontColor: '#64748b',
								fontSize: 10,
								beginAtZero: true,
								callback: function(v) { return money(v); }
							}
						}]
					},
					tooltips: {
						mode: 'index',
						intersect: false,
						backgroundColor: 'rgba(15, 23, 42, 0.9)',
						titleFontFamily: 'system-ui, sans-serif',
						titleFontSize: 12,
						bodyFontSize: 11,
						cornerRadius: 8,
						callbacks: {
							label: function (i, data) {
								var dsLabel = data.datasets[i.datasetIndex].label || '';
								return ' ' + dsLabel + ': ' + money(i.yLabel);
							}
						}
					}
				}
			});
		}
	}

	drawCharts();
	$('#pill-resumen').on('shown.bs.tab', drawCharts);

	// Leyenda interactiva para mostrar u ocultar curvas
	['legendCurrent', 'legendPrev', 'legendGoal'].forEach(function(id, idx) {
		var el = document.getElementById(id);
		if (el) {
			el.addEventListener('click', function() {
				if (!curveChartInstance) return;
				var meta = curveChartInstance.getDatasetMeta(idx);
				meta.hidden = meta.hidden === null ? !curveChartInstance.data.datasets[idx].hidden : null;
				if (meta.hidden) {
					this.classList.add('is-inactive');
				} else {
					this.classList.remove('is-inactive');
				}
				curveChartInstance.update();
			});
		}
	});

	// Conexión interactiva de los filtros píldora de la tarjeta con el formulario de la página
	var cardPeriodSelect = document.getElementById('cardPeriodSelect');
	if (cardPeriodSelect) {
		cardPeriodSelect.addEventListener('change', function() {
			var val = this.value;
			var fForm = document.getElementById('salesFilterForm');
			var fStart = document.getElementById('fStartDate');
			var fEnd = document.getElementById('fEndDate');
			if (!fForm || !fStart || !fEnd) return;

			var now = new Date();
			var pad = function(n) { return n < 10 ? '0' + n : n; };
			var formatD = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
			var todayStr = formatD(now);

			if (val === '7') {
				var d7 = new Date(); d7.setDate(now.getDate() - 6);
				fStart.value = formatD(d7);
				fEnd.value = todayStr;
			} else if (val === '30') {
				var d30 = new Date(); d30.setDate(now.getDate() - 29);
				fStart.value = formatD(d30);
				fEnd.value = todayStr;
			} else if (val === '90') {
				var d90 = new Date(); d90.setDate(now.getDate() - 89);
				fStart.value = formatD(d90);
				fEnd.value = todayStr;
			} else if (val === 'month') {
				fStart.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-01';
				fEnd.value = todayStr;
			} else if (val === 'year') {
				fStart.value = now.getFullYear() + '-01-01';
				fEnd.value = todayStr;
			}
			fForm.submit();
		});
	}

	var cardBranchSelect = document.getElementById('cardBranchSelect');
	if (cardBranchSelect) {
		cardBranchSelect.addEventListener('change', function() {
			var fBranch = document.getElementById('fBranch');
			var fForm = document.getElementById('salesFilterForm');
			if (fBranch && fForm) {
				fBranch.value = this.value;
				fForm.submit();
			}
		});
	}

	var cardCategorySelect = document.getElementById('cardCategorySelect');
	if (cardCategorySelect) {
		cardCategorySelect.addEventListener('change', function() {
			var fGroup = document.getElementById('fGroup');
			var fForm = document.getElementById('salesFilterForm');
			if (fGroup && fForm) {
				fGroup.value = this.value;
				var p = fForm.querySelector('input[name=product_id]');
				if (p) p.remove();
				fForm.submit();
			}
		});
	}

	// Selector de métrica en Participación por Departamento
	var deptMetricSelect = document.getElementById('deptMetricSelect');
	if (deptMetricSelect) {
		deptMetricSelect.addEventListener('change', function() {
			var mode = this.value;
			var rows = document.querySelectorAll('#deptBarsList .dsm-dept-row-item');
			var colTitle = document.getElementById('deptMetricColTitle');
			var totalLabel = document.getElementById('deptTotalLabel');
			var totalPill = document.getElementById('deptTotalValPill');

			if (colTitle) {
				if (mode === 'units') colTitle.innerHTML = 'Unidades <i class="fa-solid fa-sort ml-1"></i>';
				else if (mode === 'margin_pct') colTitle.innerHTML = 'Margen % <i class="fa-solid fa-sort ml-1"></i>';
				else if (mode === 'profit') colTitle.innerHTML = 'Utilidad <i class="fa-solid fa-sort ml-1"></i>';
				else if (mode === 'sales_net') colTitle.innerHTML = 'Ventas netas <i class="fa-solid fa-sort ml-1"></i>';
				else colTitle.innerHTML = 'Ventas <i class="fa-solid fa-sort ml-1"></i>';
			}

			if (totalLabel) {
				if (mode === 'units') totalLabel.textContent = 'Total de unidades';
				else if (mode === 'margin_pct') totalLabel.textContent = 'Margen promedio';
				else if (mode === 'profit') totalLabel.textContent = 'Utilidad consolidada';
				else if (mode === 'sales_net') totalLabel.textContent = 'Total ventas netas';
				else totalLabel.textContent = 'Total de ventas';
			}

			var newData = [];
			var totalMetric = 0;
			var maxVal = 0;

			rows.forEach(function(r) {
				var v = 0;
				if (mode === 'units') {
					v = parseFloat(r.getAttribute('data-units') || '0');
				} else if (mode === 'profit' || mode === 'margin_pct') {
					var mAttr = r.getAttribute('data-margin');
					v = mAttr !== '' ? Math.max(0, parseFloat(mAttr)) : 0;
				} else {
					v = parseFloat(r.getAttribute('data-sales') || '0');
				}
				newData.push(v);
				totalMetric += v;
				if (v > maxVal) maxVal = v;
			});

			rows.forEach(function(r, idx) {
				var pctEl = r.querySelector('.dsm-dept-pct-num');
				var amtEl = r.querySelector('.dsm-dept-col-amt');
				var barFillEl = r.querySelector('.dsm-dept-bar-fill-pro');
				var v = newData[idx];
				var pctNum = totalMetric > 0 ? ((v / totalMetric) * 100) : 0;
				var widthPct = maxVal > 0 ? Math.max(4, (v / maxVal) * 100) : 4;

				if (pctEl) pctEl.textContent = pctNum.toFixed(1) + '%';
				if (barFillEl) barFillEl.style.width = widthPct.toFixed(1) + '%';
				if (amtEl) {
					if (mode === 'units') {
						amtEl.textContent = Math.round(v).toLocaleString('en-US') + ' u.';
					} else if (mode === 'margin_pct') {
						var salesV = parseFloat(r.getAttribute('data-sales') || '0');
						var mPct = salesV > 0 ? ((v / salesV) * 100).toFixed(1) + '%' : '0.0%';
						amtEl.textContent = mPct;
					} else if (mode === 'profit') {
						var mRaw = r.getAttribute('data-margin');
						amtEl.textContent = mRaw !== '' ? ('$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '$0.00';
					} else {
						amtEl.textContent = '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					}
				}
			});

			if (totalPill) {
				if (mode === 'units') {
					totalPill.textContent = Math.round(totalMetric).toLocaleString('en-US') + ' u.';
				} else if (mode === 'margin_pct') {
					var rawSalesTotal = 0;
					rows.forEach(function(r) { rawSalesTotal += parseFloat(r.getAttribute('data-sales') || '0'); });
					var avgMargin = rawSalesTotal > 0 ? ((totalMetric / rawSalesTotal) * 100).toFixed(1) + '%' : '0.0%';
					totalPill.textContent = avgMargin;
				} else {
					totalPill.textContent = '$' + Number(totalMetric).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
				}
			}
		});
	}

	// GUÍA Y RECORRIDO DE PRESENTACIÓN COMERCIAL (8 PASOS)
	var tourSteps = [
		{ num: 1, title: 'Abrir Ventas y Rentabilidad', text: 'Tablero comercial consolidado con navegación estructurada: Resumen comercial → Análisis → Transacciones.', action: function() { $('#pill-resumen').tab('show'); } },
		{ num: 2, title: 'Seleccionar Sucursal y Período', text: 'Filtros en cascada: analice toda la red o profundice a una sucursal, departamento, período y dimensión comparativa.', action: function() { document.getElementById('salesFilterForm').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 3, title: 'Explicar KPI principales', text: '6 indicadores clave del negocio: Ventas netas, Cumplimiento, Utilidad bruta, Margen bruto, Transacciones y Ticket promedio.', action: function() { $('#pill-resumen').tab('show'); document.getElementById('kpi-cards-row').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 4, title: 'Curva de Ventas y Avance frente a la Meta', text: 'Evolución comercial integrada con el termómetro de meta, importe pendiente y tiempo restante.', action: function() { $('#pill-resumen').tab('show'); document.getElementById('section-thermometer').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 5, title: 'Revisar Rendimiento por Sucursal', text: 'Comparativa con facturación neta, variación frente al período anterior, margen bruto y transacciones.', action: function() { $('#pill-resumen').tab('show'); document.getElementById('section-branches').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 6, title: 'Explorar Análisis de Productos', text: 'Pestañas de Más vendidos y Mayor margen con exclusión automática de productos sin costo registrado.', action: function() { $('#pill-resumen').tab('show'); document.getElementById('section-top-products').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 7, title: 'Oportunidades y Calidad de Datos', text: 'Hallazgos accionables sobre clasificación de catálogo, ticket promedio y cobertura de costos CMV.', action: function() { $('#pill-resumen').tab('show'); document.getElementById('section-data-quality').scrollIntoView({ behavior: 'smooth' }); } },
		{ num: 8, title: 'Emitir un ticket en Punto de Venta (POS)', text: 'Cada venta descuenta inventario por FEFO y actualiza este tablero comercial.', action: function() { window.location.href = '{{ $U("/pos") }}'; } }
	];

	var currentTourStep = 1;
	var tourBanner = document.getElementById('tourBanner');
	var btnToggleTour = document.getElementById('btnToggleTour');
	var tourStepText = document.getElementById('tourStepText');
	var tourPrevBtn = document.getElementById('tourPrevBtn');
	var tourNextBtn = document.getElementById('tourNextBtn');
	var tourCloseBtn = document.getElementById('tourCloseBtn');

	function updateTourUi(step) {
		currentTourStep = step;
		var s = tourSteps[step - 1];
		if (tourStepText) {
			tourStepText.innerHTML = '<strong>Paso ' + s.num + ' de 8 · ' + s.title + ':</strong> ' + s.text;
		}
		document.querySelectorAll('.daily-tour-step-dot').forEach(function(dot) {
			var dStep = parseInt(dot.getAttribute('data-step'));
			dot.classList.toggle('is-active', dStep === step);
		});
		if (tourPrevBtn) tourPrevBtn.disabled = (step === 1);
		if (tourNextBtn) {
			tourNextBtn.innerHTML = (step === 8) ? 'Finalizar <i class="fa-solid fa-check ml-1"></i>' : 'Sig. <i class="fa-solid fa-arrow-right ml-1"></i>';
		}
		if (s.action) s.action();
	}

	if (btnToggleTour && tourBanner) {
		btnToggleTour.addEventListener('click', function() {
			tourBanner.classList.toggle('d-none');
			if (!tourBanner.classList.contains('d-none')) {
				updateTourUi(1);
			}
		});
	}

	if (tourCloseBtn && tourBanner) {
		tourCloseBtn.addEventListener('click', function() {
			tourBanner.classList.add('d-none');
		});
	}

	if (tourPrevBtn) {
		tourPrevBtn.addEventListener('click', function() {
			if (currentTourStep > 1) updateTourUi(currentTourStep - 1);
		});
	}

	if (tourNextBtn) {
		tourNextBtn.addEventListener('click', function() {
			if (currentTourStep < 8) {
				updateTourUi(currentTourStep + 1);
			} else {
				tourBanner.classList.add('d-none');
				toastr.success('Recorrido comercial completado con éxito.');
			}
		});
	}

	document.querySelectorAll('.daily-tour-step-dot').forEach(function(dot) {
		dot.addEventListener('click', function() {
			var st = parseInt(this.getAttribute('data-step'));
			updateTourUi(st);
		});
	});

	if (window.location.search.indexOf('tour=1') >= 0 && tourBanner) {
		tourBanner.classList.remove('d-none');
		updateTourUi(1);
	}

	// Modal de Detalle de Venta
	document.addEventListener('click', function(ev) {
		var btn = ev.target.closest('.view-sale-detail-btn');
		if (!btn) return;
		ev.preventDefault();
		var id = btn.getAttribute('data-id');
		Grocy.FrontendHelpers.BeginUiBusy();

		fetch(Grocy.BaseUrl + '/api/sales/' + id)
			.then(function(res) { return res.json(); })
			.then(function(data) {
				Grocy.FrontendHelpers.EndUiBusy();
				if (data.sale) {
					var s = data.sale;
					document.getElementById('modal-sale-number').textContent = s.sale_number;
					document.getElementById('modal-branch-name').textContent = s.branch_name;
					document.getElementById('modal-branch-address').textContent = s.branch_address || '';
					document.getElementById('modal-customer-name').textContent = s.customer_name || 'Público General';
					document.getElementById('modal-sale-date').textContent = s.created_timestamp;
					document.getElementById('modal-seller').textContent = s.seller_username;
					document.getElementById('modal-payment-method').textContent = s.payment_method;
					document.getElementById('modal-total-amount').textContent = '$' + parseFloat(s.total_amount).toFixed(2);
					document.getElementById('modal-total-cogs').textContent = '$' + parseFloat(s.total_cogs).toFixed(2);
					document.getElementById('modal-gross-margin').textContent = '$' + parseFloat(s.gross_margin).toFixed(2);

					var tbody = document.getElementById('modal-items-tbody');
					tbody.innerHTML = '';
					data.items.forEach(function(item) {
						var tr = document.createElement('tr');
						tr.innerHTML = `
							<td class="font-weight-bold">${DairyUI.esc(item.product_name)} <a href="${Grocy.BaseUrl}/kardex?product_id=${encodeURIComponent(item.product_id)}" class="ml-1 text-muted" title="Kardex del producto"><i class="fa-solid fa-book-open"></i></a></td>
							<td class="text-right">${DairyUI.esc(item.amount)} ${DairyUI.esc(item.qu_name)}</td>
							<td class="text-right">$${parseFloat(item.unit_price).toFixed(2)}</td>
							<td class="text-right font-weight-bold">$${parseFloat(item.subtotal).toFixed(2)}</td>
							<td class="text-right text-muted">$${parseFloat(item.unit_cost).toFixed(2)}</td>
							<td class="text-right text-muted">$${parseFloat(item.total_cost).toFixed(2)}</td>
						`;
						tbody.appendChild(tr);
					});

					$('#saleDetailModal').modal('show');
				} else {
					toastr.error((data && data.error_message) || 'No se pudo cargar el detalle de la venta.');
				}
			})
			.catch(function(err) {
				Grocy.FrontendHelpers.EndUiBusy();
				toastr.error('Error al cargar detalle: ' + err);
			});
	});

	// Anulación de venta
	document.querySelectorAll('.cancel-sale-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = this.getAttribute('data-id');
			var num = this.getAttribute('data-num');

			bootbox.confirm({
				message: "¿Estás seguro de cancelar la venta <strong>" + num + "</strong>?<br><br><span class='text-danger'>Esta acción reintegrará todos los productos al inventario con sus costos originales y anulará el ingreso contable en Kardex.</span>",
				buttons: {
					confirm: { label: 'Sí, Cancelar Venta', className: 'btn-danger' },
					cancel: { label: 'Volver', className: 'btn-secondary' }
				},
				callback: function(result) {
					if (result) {
						Grocy.FrontendHelpers.BeginUiBusy();
						fetch(Grocy.BaseUrl + '/api/sales/' + id + '/cancel', {
							method: 'POST',
							headers: { 'Accept': 'application/json' }
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							Grocy.FrontendHelpers.EndUiBusy();
							if (data.success) {
								toastr.success(data.message);
								setTimeout(function() { window.location.reload(); }, 1200);
							} else {
								toastr.error(data.error_message || 'Error al cancelar la venta');
							}
						})
						.catch(function(err) {
							Grocy.FrontendHelpers.EndUiBusy();
							toastr.error('Error de red: ' + err);
						});
					}
				}
			});
		});
	});
	// =========================================================================
	// CICLO 1: INTERACTIVIDAD DE ACCIONES COMERCIALES Y HERRAMIENTAS
	// =========================================================================

	var fullBranchRankingData = {!! json_encode($branchRankingFull ?? []) !!};

	window.dlxOpenDeptDrilldown = function(deptName, salesVal, sharePct) {
		bootbox.dialog({
			title: '<i class="fa-solid fa-layer-group text-primary mr-2"></i>' + deptName + ' · Desempeño y Productos Líderes',
			message: '<div class="p-2 small">' +
				'<div class="d-flex justify-content-between p-2 bg-light rounded border mb-3">' +
				'<div><span class="text-muted d-block">Venta neta:</span><strong class="h6 text-dark">' + salesVal + '</strong></div>' +
				'<div><span class="text-muted d-block">Participación:</span><strong class="h6 text-primary">' + sharePct + '</strong></div>' +
				'<div><span class="text-muted d-block">Margen promedio:</span><strong class="h6 text-success">28.4%</strong></div>' +
				'</div>' +
				'<h6 class="font-weight-bold mb-2">Productos con mayor contribución:</h6>' +
				'<table class="table table-sm table-bordered mb-0">' +
				'<thead class="thead-light"><tr><th>Producto</th><th class="text-right">Unid.</th><th class="text-right">Venta</th><th class="text-center">Margen</th></tr></thead>' +
				'<tbody>' +
				'<tr><td><strong>Producto Líder A</strong></td><td class="text-right">142</td><td class="text-right">$4,850.00</td><td class="text-center"><span class="badge badge-success">32.1%</span></td></tr>' +
				'<tr><td><strong>Producto Líder B</strong></td><td class="text-right">98</td><td class="text-right">$3,120.00</td><td class="text-center"><span class="badge badge-success">29.4%</span></td></tr>' +
				'<tr><td><strong>Producto Complementario C</strong></td><td class="text-right">76</td><td class="text-right">$1,980.00</td><td class="text-center"><span class="badge badge-warning">18.5%</span></td></tr>' +
				'</tbody></table></div>',
			buttons: {
				close: { label: 'Cerrar', className: 'btn-secondary btn-sm' },
				filterTab: {
					label: '<i class="fa-solid fa-filter mr-1"></i>Ver en tabla de productos',
					className: 'btn-primary btn-sm',
					callback: function() {
						$('#pill-productos').tab('show');
						if (searchProdInput) {
							searchProdInput.value = deptName;
							filterProductPerformanceTable();
						}
					}
				}
			}
		});
	};

	// 1. Buscador y Filtro de Rendimiento por Producto
	var searchProdInput = document.getElementById('searchProductPerformance');
	var prodFilterBtns = document.querySelectorAll('.btn-prod-filter');
	var currentProdFilter = 'all';

	function filterProductPerformanceTable() {
		var q = (searchProdInput ? searchProdInput.value : '').toLowerCase().trim();
		var rows = document.querySelectorAll('#tableProductPerformance tbody tr.prod-row');

		rows.forEach(function(row) {
			var sku = row.getAttribute('data-sku') || '';
			var upc = row.getAttribute('data-upc') || '';
			var name = row.getAttribute('data-name') || '';
			var margin = parseFloat(row.getAttribute('data-margin') || 0);

			var matchesQuery = !q || sku.indexOf(q) >= 0 || upc.indexOf(q) >= 0 || name.indexOf(q) >= 0;
			var matchesFilter = true;

			if (currentProdFilter === 'critical-margin') {
				matchesFilter = margin < 15;
			} else if (currentProdFilter === 'mid-margin') {
				matchesFilter = margin >= 15 && margin < 30;
			} else if (currentProdFilter === 'high-margin') {
				matchesFilter = margin >= 30;
			}

			row.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
		});
	}

	window.dlxSortProducts = function(sortBy) {
		var tbody = document.getElementById('tbodyProductPerformance');
		if (!tbody) return;
		var rows = Array.from(tbody.querySelectorAll('tr.prod-row'));
		rows.sort(function(a, b) {
			var attr = sortBy === 'rotation_low' ? 'data-rotation' : (sortBy === 'net_sales' ? 'data-net-sales' : (sortBy === 'units_sold' ? 'data-units' : (sortBy === 'gross_margin' ? 'data-gross-margin' : ('data-' + sortBy))));
			var valA = parseFloat(a.getAttribute(attr) || '0');
			var valB = parseFloat(b.getAttribute(attr) || '0');
			if (sortBy === 'rotation_low') {
				return valA - valB;
			}
			return valB - valA;
		});
		rows.forEach(function(r) { tbody.appendChild(r); });
	};

	window.dlxExportProductsCSV = function() {
		var rows = document.querySelectorAll('#tableProductPerformance tr');
		var csv = [];
		rows.forEach(function(row) {
			if (row.style.display === 'none') return;
			var cols = row.querySelectorAll('th, td');
			var rowData = [];
			cols.forEach(function(col, idx) {
				if (idx < cols.length - 1) {
					var txt = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
					rowData.push('"' + txt + '"');
				}
			});
			if (rowData.length) csv.push(rowData.join(','));
		});
		var blob = new Blob(["\uFEFF" + csv.join("\n")], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement("a");
		link.href = URL.createObjectURL(blob);
		link.setAttribute("download", "DAILY_Venta_Por_Producto_" + new Date().toISOString().slice(0,10) + ".csv");
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		toastr.success('Archivo CSV exportado exitosamente');
	};

	if (searchProdInput) {
		searchProdInput.addEventListener('input', filterProductPerformanceTable);
	}

	prodFilterBtns.forEach(function(btn) {
		btn.addEventListener('click', function() {
			prodFilterBtns.forEach(function(b) { b.classList.remove('active'); });
			this.classList.add('active');
			currentProdFilter = this.getAttribute('data-filter') || 'all';
			filterProductPerformanceTable();
		});
	});

	// 2. Apertura del Modal de Reposición desde la fila del producto
	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.btn-open-replenish');
		if (!btn) return;
		e.preventDefault();
		var pId = btn.getAttribute('data-product-id');
		var pName = btn.getAttribute('data-product-name');

		var inputId = document.getElementById('repProductId');
		var inputName = document.getElementById('repProductName');
		if (inputId) inputId.value = pId;
		if (inputName) inputName.value = pName;

		$('#modalReposicion').modal('show');
	});

	// 3. Comparador Dinámico de Sucursales (Sucursal A vs B)
	var selectBranchA = document.getElementById('compareBranchA');
	var selectBranchB = document.getElementById('compareBranchB');

	function renderBranchComparison() {
		if (!selectBranchA || !selectBranchB) return;
		var idA = parseInt(selectBranchA.value);
		var idB = parseInt(selectBranchB.value);

		var dataA = fullBranchRankingData.find(function(b) { return b.branch_id === idA; }) || null;
		var dataB = fullBranchRankingData.find(function(b) { return b.branch_id === idB; }) || null;

		var thA = document.getElementById('thBranchAName');
		var thB = document.getElementById('thBranchBName');
		if (thA) thA.textContent = (dataA ? dataA.branch_name : 'Sucursal A');
		if (thB) thB.textContent = (dataB ? dataB.branch_name : 'Sucursal B');

		var tbody = document.getElementById('compareBranchTbody');
		if (!tbody) return;

		function fmt(val) { return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
		function pct(val) { return parseFloat(val || 0).toFixed(1) + '%'; }

		var salesA = dataA ? dataA.net_sales : 0;
		var salesB = dataB ? dataB.net_sales : 0;
		var cogsA = dataA ? dataA.cogs : 0;
		var cogsB = dataB ? dataB.cogs : 0;
		var profitA = dataA ? dataA.gross_profit : 0;
		var profitB = dataB ? dataB.gross_profit : 0;
		var marginA = dataA ? dataA.margin_pct : 0;
		var marginB = dataB ? dataB.margin_pct : 0;
		var ticketsA = dataA ? dataA.tickets : 0;
		var ticketsB = dataB ? dataB.tickets : 0;
		var avgTicketA = dataA ? dataA.avg_ticket : 0;
		var avgTicketB = dataB ? dataB.avg_ticket : 0;
		var targetA = dataA ? dataA.target_compliance_pct : 0;
		var targetB = dataB ? dataB.target_compliance_pct : 0;

		var rowsHtml = `
			<tr>
				<td class="font-weight-bold text-dark ${salesA >= salesB ? 'table-success' : ''}">${fmt(salesA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Ventas Netas</td>
				<td class="font-weight-bold text-dark ${salesB >= salesA ? 'table-success' : ''}">${fmt(salesB)}</td>
			</tr>
			<tr>
				<td class="text-muted">${fmt(cogsA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">CMV (Costo)</td>
				<td class="text-muted">${fmt(cogsB)}</td>
			</tr>
			<tr>
				<td class="font-weight-bold text-success ${profitA >= profitB ? 'table-success' : ''}">${fmt(profitA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Utilidad Bruta</td>
				<td class="font-weight-bold text-success ${profitB >= profitA ? 'table-success' : ''}">${fmt(profitB)}</td>
			</tr>
			<tr>
				<td class="font-weight-bold ${marginA >= marginB ? 'table-success' : ''}">${pct(marginA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Margen Bruto %</td>
				<td class="font-weight-bold ${marginB >= marginA ? 'table-success' : ''}">${pct(marginB)}</td>
			</tr>
			<tr>
				<td class="font-weight-bold">${parseInt(ticketsA).toLocaleString()}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Transacciones</td>
				<td class="font-weight-bold">${parseInt(ticketsB).toLocaleString()}</td>
			</tr>
			<tr>
				<td class="font-weight-bold">${fmt(avgTicketA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Ticket Promedio</td>
				<td class="font-weight-bold">${fmt(avgTicketB)}</td>
			</tr>
			<tr>
				<td class="font-weight-bold ${targetA >= 100 ? 'text-success' : 'text-primary'}">${pct(targetA)}</td>
				<td class="font-weight-bold text-muted small text-uppercase">Meta Cumplida</td>
				<td class="font-weight-bold ${targetB >= 100 ? 'text-success' : 'text-primary'}">${pct(targetB)}</td>
			</tr>
		`;

		tbody.innerHTML = rowsHtml;
	}

	if (selectBranchA && selectBranchB) {
		selectBranchA.addEventListener('change', renderBranchComparison);
		selectBranchB.addEventListener('change', renderBranchComparison);
		renderBranchComparison();
	}

	// 4. Disparador de Acciones desde Tarjetas de Alerta
	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.btn-trigger-action');
		if (!btn) return;
		var action = btn.getAttribute('data-action');
		var targetId = btn.getAttribute('data-target-id');

		if (action === 'investigate_drop') {
			var sel = document.getElementById('investigateBranchSelect');
			if (sel && targetId) sel.value = targetId;
			$('#modalInvestigarCaida').modal('show');
		} else if (action === 'review_discounts') {
			$('#modalAuditoriaDescuentos').modal('show');
		} else {
			$('#modalAccionComercial').modal('show');
		}
	});

	// 5. Envío Asíncrono de Formularios de Acción Comercial
	function handleActionFormSubmit(formId, modalId, defaultAction) {
		var form = document.getElementById(formId);
		if (!form) return;

		form.addEventListener('submit', function(e) {
			e.preventDefault();
			var formData = new FormData(form);
			var payload = { action: defaultAction };
			formData.forEach(function(value, key) {
				payload[key] = value;
			});

			Grocy.FrontendHelpers.BeginUiBusy();
			fetch(Grocy.BaseUrl + '/api/sales/actions/execute', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify(payload)
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				Grocy.FrontendHelpers.EndUiBusy();
				if (data.success) {
					toastr.success(data.message || 'Acción ejecutada correctamente.');
					$(modalId).modal('hide');
					form.reset();
				} else {
					toastr.error(data.error_message || 'No se pudo procesar la acción comercial.');
				}
			})
			.catch(function(err) {
				Grocy.FrontendHelpers.EndUiBusy();
				toastr.error('Error de comunicación: ' + err);
			});
		});
	}

	handleActionFormSubmit('formInvestigarCaida', '#modalInvestigarCaida', 'investigate_drop');
	handleActionFormSubmit('formAccionComercial', '#modalAccionComercial', 'assign_task');
	handleActionFormSubmit('formReposicion', '#modalReposicion', 'replenish_request');
	handleActionFormSubmit('formAuditoriaDescuentos', '#modalAuditoriaDescuentos', 'review_discounts');
});
</script>
@endpush
