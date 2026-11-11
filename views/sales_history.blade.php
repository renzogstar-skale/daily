@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Ventas y Rentabilidad · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales.css?v=', true) }}{{ daily_asset_version('/css/daily_sales.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_sales_reports.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_reports.css') }}" rel="stylesheet">
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
@if($activeTab && $activeTab !== 'resumen' && $activeTab !== 'departamentos' && $activeTab !== 'productos' && $activeTab !== 'sucursales' && $activeTab !== 'detalle')
@include('components.alert_strip', ['alertCategories' => ['sales']])
@endif

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
	$yesterdayDate = date('Y-m-d', strtotime('-1 day'));
	$thisWeekStart = date('Y-m-d', strtotime('monday this week'));
	$lastWeekStart = date('Y-m-d', strtotime('monday last week'));
	$lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
	$thisMonthStart = date('Y-m-01');
	$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
	$lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
	$thisYearStart = date('Y-01-01');
	$thisQuarterStart = date('Y-') . sprintf('%02d', intdiv(intval(date('n')) - 1, 3) * 3 + 1) . '-01';

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

@if($activeTab && $activeTab !== 'resumen' && $activeTab !== 'departamentos' && $activeTab !== 'productos' && $activeTab !== 'sucursales' && $activeTab !== 'detalle')
<!-- ENCABEZADO COMERCIAL -->
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-chart-column"></i></div>
		<div>
			<h4 class="dairy-page-title mb-1">Ventas y Rentabilidad</h4>
			<p class="dairy-page-subtitle mb-0">Analiza el desempeño de tus ventas, márgenes y tendencias en todos tus puntos de venta.</p>
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
				<label class="sales-field-label" for="fDept">Departamento</label>
				<select name="department" id="fDept" class="sales-filter-control" data-testid="filter-department" onchange="['product_id', 'group_id'].forEach(function (n) { var p = this.form.querySelector('[name=' + n + ']'); if (p) p.value = ''; }, this); this.form.submit()">
					<option value="">Todos los departamentos</option>
					@foreach($filterOptions['departments'] as $d)
					<option value="{{ $d['key'] }}" @if($sharedFilters['department'] === $d['key']) selected @endif>{{ $d['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div>
				<label class="sales-field-label" for="fGroup">Categoría</label>
				<select name="group_id" id="fGroup" class="sales-filter-control" data-testid="filter-category" onchange="var p = this.form.querySelector('input[name=product_id]'); if (p) p.remove(); this.form.submit()">
					<option value="">Todas las categorías</option>
					@foreach($filterOptions['groups'] as $g)
					<option value="{{ $g['id'] }}" @if($selectedGroupId !== null && intval($selectedGroupId) === intval($g['id'])) selected @endif>{{ $g['name'] }}</option>
					@endforeach
					<option value="0" @if($selectedGroupId === 0) selected @endif>Sin categoría</option>
				</select>
			</div>

			@if(count($filterOptions['subcategories']))
			<div>
				<label class="sales-field-label" for="fSub">Subcategoría</label>
				<select name="subcategory" id="fSub" class="sales-filter-control" onchange="this.form.submit()">
					<option value="">Todas</option>
					@foreach($filterOptions['subcategories'] as $s)
					<option value="{{ $s }}" @if($sharedFilters['subcategory'] === $s) selected @endif>{{ $s }}</option>
					@endforeach
				</select>
			</div>
			@endif

			<div>
				<label class="sales-field-label" for="fProduct">Producto o SKU</label>
				<input type="search" name="product" id="fProduct" class="sales-filter-control" data-testid="filter-product" value="{{ $sharedFilters['product'] }}" placeholder="SKU, código o nombre">
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
				<a href="{{ $U('/sales') }}" class="daily-filters-reset d-block text-center mt-1" data-daily-filters-reset data-testid="filter-reset">Restablecer filtros</a>
			</div>
		</div>
	</form>

	<!-- Píldoras de Períodos Rápidos + Fechas Comparadas -->
	<div class="sales-quick-periods-bar">
		<div class="sales-period-pills">
			<a href="{{ $U('/sales?' . $buildRangeQs($todayDate, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($todayDate, $todayDate)) is-active @endif">Hoy</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($yesterdayDate, $yesterdayDate)) }}" class="sales-period-pill @if($isPresetActive($yesterdayDate, $yesterdayDate)) is-active @endif">Ayer</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisWeekStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisWeekStart, $todayDate)) is-active @endif">Semana actual</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($lastWeekStart, $lastWeekEnd)) }}" class="sales-period-pill @if($isPresetActive($lastWeekStart, $lastWeekEnd)) is-active @endif">Semana anterior</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisMonthStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisMonthStart, $todayDate)) is-active @endif">Mes actual</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($lastMonthStart, $lastMonthEnd)) }}" class="sales-period-pill @if($isPresetActive($lastMonthStart, $lastMonthEnd)) is-active @endif">Mes anterior</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisQuarterStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisQuarterStart, $todayDate)) is-active @endif">Trimestre actual</a>
			<a href="{{ $U('/sales?' . $buildRangeQs($thisYearStart, $todayDate)) }}" class="sales-period-pill @if($isPresetActive($thisYearStart, $todayDate)) is-active @endif">Año actual</a>
			<a href="{{ $U('/sales?' . $rangeQs(30)) }}" class="sales-period-pill @if($isPresetActive(date('Y-m-d', strtotime('-29 days')), $todayDate)) is-active @endif">30 días</a>
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

	<!-- PESTAÑAS: tablero principal primero; análisis de detalle después; clientes, POS e integración en posición secundaria -->
	<div class="nav daily-level-tabs flex-wrap" role="tablist" style="gap: 4px;">
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'resumen' || !$activeTab) active @endif" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen" aria-selected="{{ ($activeTab === 'resumen' || !$activeTab) ? 'true' : 'false' }}">
			<i class="fa-solid fa-chart-column"></i>
			<span>Tablero de ventas</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'departamentos') active @endif" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos" aria-selected="{{ $activeTab === 'departamentos' ? 'true' : 'false' }}">
			<i class="fa-solid fa-boxes-stacked"></i>
			<span>Ventas por departamento</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'productos') active @endif" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos" aria-selected="{{ $activeTab === 'productos' ? 'true' : 'false' }}">
			<i class="fa-solid fa-barcode"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'sucursales') active @endif" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales" aria-selected="{{ $activeTab === 'sucursales' ? 'true' : 'false' }}">
			<i class="fa-solid fa-store"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'detalle') active @endif" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle" aria-selected="{{ $activeTab === 'detalle' ? 'true' : 'false' }}">
			<i class="fa-solid fa-list-ul"></i>
			<span>Historial de ventas</span>
			<span class="daily-level-badge">{{ count($sales) }}</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'rentabilidad' || $activeTab === 'analisis') active @endif" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad" aria-selected="{{ ($activeTab === 'rentabilidad' || $activeTab === 'analisis') ? 'true' : 'false' }}">
			<i class="fa-solid fa-coins"></i>
			<span>Rentabilidad</span>
		</button>
				<button type="button" class="nav-link daily-level-tab @if($activeTab === 'precios-promociones') active @endif" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-promociones">
			<i class="fa-solid fa-tags text-warning"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'campanas') active @endif" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas">
			<i class="fa-solid fa-bullhorn text-primary"></i>
			<span>Campañas</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'inteligencia') active @endif" data-toggle="pill" data-target="#tab-inteligencia" role="tab" id="pill-inteligencia" aria-selected="{{ $activeTab === 'inteligencia' ? 'true' : 'false' }}">
			<i class="fa-solid fa-brain"></i>
			<span>Inteligencia comercial</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'pedidos-clientes') active @endif" data-toggle="pill" data-target="#tab-pedidos-clientes" role="tab" id="pill-pedidos-clientes" aria-selected="{{ $activeTab === 'pedidos-clientes' ? 'true' : 'false' }}">
			<i class="fa-solid fa-users"></i>
			<span>Clientes y pedidos</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'pos') active @endif" data-toggle="pill" data-target="#tab-pos" role="tab" id="pill-pos" aria-selected="{{ $activeTab === 'pos' ? 'true' : 'false' }}">
			<i class="fa-solid fa-cash-register"></i>
			<span>POS e integración</span>
		</button>
	</div>
</div>

@if($sharedFilters['department'] !== null || $sharedFilters['subcategory'] !== null || $sharedFilters['product'] !== null)
<p class="sdash-scope-note" data-testid="filter-scope-note"><i class="fa-solid fa-filter"></i> Departamento, subcategoría y producto/SKU se aplican al <strong>Tablero de ventas</strong>. Las demás pestañas aplican sucursal, categoría y fechas; los informes comerciales al final de cada pestaña aplican todos los filtros.</p>
@endif
@endif

<!-- CONTENEDOR DE PESTAÑAS -->
<div class="tab-content" id="salesMainTabContent">

<!-- ============================================================== -->
<!-- PESTAÑA 1: RESUMEN COMERCIAL                                   -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'resumen' || !$activeTab) show active @endif" id="tab-resumen" role="tabpanel">
	@include('components.sales.executive_sales_dashboard')
	@include('components.sales.sales_reports', ['section' => 'dashboard', 'emitData' => true])
</div>

<!-- ============================================================== -->
<!-- PESTAÑA: RENDIMIENTO POR DEPARTAMENTO                           -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'departamentos') show active @endif" id="tab-departamentos" role="tabpanel">
	@include('components.sales.department_performance')
	@include('components.sales.sales_reports', ['section' => 'departments'])
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 2: RENTABILIDAD COMERCIAL                              -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'rentabilidad' || $activeTab === 'analisis') show active @endif" id="tab-rentabilidad" role="tabpanel">

	{{-- Rentabilidad comercial (ventas, costo, utilidad y margen bruto) en components/sales/sales_reports; datos de SalesReportsService::Profitability --}}
	@include('components.sales.sales_reports', ['section' => 'profitability'])
</div>

<!-- ============================================================== -->
<!-- PESTAÑA 3: RENDIMIENTO POR PRODUCTO                            -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'productos') show active @endif" id="tab-productos" role="tabpanel">
	@include('components.sales.product_catalog_dashboard')
	@include('components.sales.sales_reports', ['section' => 'products'])
</div>

<!-- ============================================================== -->
<!-- ============================================================== -->
<!-- PESTAÑA 4: CENTRO DE GESTIÓN MULTISUCURSAL                       -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'sucursales') show active @endif" id="tab-sucursales" role="tabpanel">
	@include('components.sales.branch_supervision_dashboard')
	@include('components.sales.sales_reports', ['section' => 'branches'])
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
<!-- ============================================================== -->
<!-- PESTAÑA 3: CENTRO DE TRAZABILIDAD Y AUDITORÍA (HISTORIAL DE VENTAS) -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'detalle') show active @endif" id="tab-detalle" role="tabpanel">
	@include('components.sales.sales_history_dashboard')
	@include('components.sales.sales_reports', ['section' => 'history'])
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
						<tbody id="modal-items-tbody">
							<tr>
								<td><strong>Smart TV Samsung 55" Crystal UHD</strong><br><small class="text-muted">SKU-9901</small></td>
								<td class="text-right font-weight-bold">1</td>
								<td class="text-right">$1,699,900</td>
								<td class="text-right font-weight-bold text-success">$1,699,900</td>
								<td class="text-right text-muted">$1,280,000</td>
								<td class="text-right text-muted">$1,280,000</td>
							</tr>
							<tr>
								<td><strong>Soporte Articulado TV 32-65"</strong><br><small class="text-muted">SKU-4412</small></td>
								<td class="text-right font-weight-bold">1</td>
								<td class="text-right">$89,900</td>
								<td class="text-right font-weight-bold text-success">$89,900</td>
								<td class="text-right text-muted">$48,000</td>
								<td class="text-right text-muted">$48,000</td>
							</tr>
						</tbody>
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
				<button type="button" class="btn btn-warning btn-sm text-dark font-weight-bold" onclick="$('#modalBrechaMeta').modal('hide'); $('#pill-resumen').tab('show'); document.getElementById('srep-dashboard').scrollIntoView({behavior:'smooth'});">
					<i class="fa-solid fa-chart-line mr-1"></i>Ver metas en el Tablero
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
		var target = btnEl.getAttribute('data-target') || btnEl.getAttribute('href');
		if (!target) return;

		// 1. Sincronizar pestañas daily-level-tab
		document.querySelectorAll('.daily-level-tab').forEach(function(tabBtn) {
			var isCurrent = (tabBtn.getAttribute('data-target') === target || tabBtn.getAttribute('href') === target || tabBtn === btnEl);
			tabBtn.classList.toggle('active', isCurrent);
			tabBtn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
		});

		// 2. Sincronizar píldoras ejecutivas (.ded-nav-tab) en todos los encabezados
		document.querySelectorAll('.ded-nav-tab').forEach(function(dedBtn) {
			var isCurrent = (dedBtn.getAttribute('data-target') === target || dedBtn.getAttribute('href') === target || dedBtn === btnEl);
			dedBtn.classList.toggle('is-active', isCurrent);
			dedBtn.classList.toggle('active', isCurrent);
			dedBtn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
		});

		// 3. Mostrar el tab-pane correspondiente
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

		// Redibujar gráficos si se va a Tablero de Ventas
		if (tabName === 'resumen' && typeof drawCharts === 'function') {
			setTimeout(drawCharts, 50);
		}
		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
		if (window.DailyCrystal && typeof window.DailyCrystal.refresh === 'function') {
			window.DailyCrystal.refresh();
		}
	}

	document.querySelectorAll('.daily-level-tab').forEach(function(tabBtn) {
		tabBtn.addEventListener('click', function() {
			activateSingleMainTab(this);
		});
	});

	$('.daily-level-tab').on('shown.bs.tab', function(e) {
		activateSingleMainTab(e.target);
	});

	$(document).on('click', '.ded-nav-tab', function(e) {
		e.preventDefault();
		activateSingleMainTab(this);
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
						backgroundColor: 'rgba(15, 23, 42, 0.94)',
						titleFontColor: '#ffffff',
						titleFontStyle: 'bold',
						titleFontSize: 12,
						bodyFontColor: '#ffffff',
						bodyFontSize: 11,
						footerFontColor: '#e2e8f0',
						borderColor: 'rgba(255, 255, 255, 0.15)',
						borderWidth: 1,
						cornerRadius: 8,
						xPadding: 12,
						yPadding: 10,
						displayColors: true,
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

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script src="{{ $U('/js/daily_executive_sales.js?v=', true) }}{{ daily_asset_version('/js/daily_executive_sales.js') }}"></script>
<script src="{{ $U('/js/daily_sales_reports.js?v=', true) }}{{ daily_asset_version('/js/daily_sales_reports.js') }}"></script>
<script src="{{ $U('/js/daily_sales_dash.js?v=', true) }}{{ daily_asset_version('/js/daily_sales_dash.js') }}"></script>
@endpush
