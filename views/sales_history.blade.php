@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Ventas y Rentabilidad · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales.css?v=', true) }}{{ time() }}" rel="stylesheet">
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

	<!-- PESTAÑAS PRINCIPALES: RESUMEN COMERCIAL · ANÁLISIS · TRANSACCIONES -->
	<div class="nav daily-level-tabs" role="tablist">
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'resumen' || !$activeTab) active @endif" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen" aria-selected="{{ ($activeTab === 'resumen' || !$activeTab) ? 'true' : 'false' }}">
			<i class="fa-solid fa-chart-column"></i>
			<span>Resumen comercial</span>
		</button>
		<button type="button" class="nav-link daily-level-tab @if($activeTab === 'analisis') active @endif" data-toggle="pill" data-target="#tab-analisis" role="tab" id="pill-analisis" aria-selected="{{ $activeTab === 'analisis' ? 'true' : 'false' }}">
			<i class="fa-solid fa-chart-pie"></i>
			<span>Análisis</span>
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

	<!-- FILA 2: CURVA DE VENTAS + TERMÓMETRO INTEGRADO (IZQ) Y PARTICIPACIÓN POR DEPARTAMENTO (DER) -->
	<div class="row mb-3">
		<!-- Columna Izquierda (7 cols): Curva de ventas + Avance frente a la meta -->
		<div class="col-lg-7 mb-3 mb-lg-0">
			<div class="sales-section-card">
				<div class="sales-section-header">
					<h2 class="sales-section-title">
						<span class="sales-title-icon"><i class="fa-solid fa-chart-Simple"></i></span>
						<span>Curva de ventas</span>
						<button type="button" class="daily-kpi-info-btn" data-toggle="popover" title="Curva de ventas y meta" data-content="Evolución diaria o semanal de las ventas netas sin IVA comparada con el período anterior y la meta diaria proporcional."><i class="fa-solid fa-circle-info"></i></button>
					</h2>
					<div class="d-flex align-items-center" style="gap: 8px;">
						<select id="curveGranularitySelect" class="sales-filter-control" style="width: auto; height: 30px; font-size: 0.76rem; padding: 0 10px;" aria-label="Granularidad de la curva">
							<option value="daily" selected>Diaria</option>
							<option value="weekly">Semanal</option>
						</select>
					</div>
				</div>

				<div class="sales-curve-legend">
					<span><span class="sales-curve-dot"></span>Ventas actuales</span>
					<span><span class="sales-curve-dash"></span>Período anterior</span>
					<span><span class="sales-curve-goal-dash"></span>Meta del período</span>
				</div>

				<div style="position: relative; height: 205px;">
					<canvas id="sales-daily-chart" aria-label="Curva de ventas"></canvas>
				</div>

				<!-- Termómetro integrado dentro de la misma tarjeta -->
				<div class="sales-integrated-thermometer" id="section-thermometer">
					@php
						$barPct = min(100, max(0, $thermometer['compliance_pct']));
					@endphp
					<div class="sales-therm-bar-row">
						<span class="sales-therm-target-icon"><i class="fa-solid fa-bullseye"></i></span>
						<div class="sales-therm-bar-col">
							<div class="sales-therm-bar-label">
								<span>Avance frente a la meta de ventas</span>
								@if($thermometer['is_closed'])
								<span class="text-muted font-weight-normal" style="font-size: 0.71rem;" title="{{ $thermometer['explanation'] }}">Cierre del período: {{ $m($thermometer['sales_net']) }}</span>
								@else
								<span class="text-success font-weight-600" style="font-size: 0.71rem;" title="{{ $thermometer['explanation'] }}">Proyección: {{ $mInt($thermometer['projected_closing']) }}</span>
								@endif
							</div>
							<div class="sales-therm-track">
								<div class="sales-therm-fill" style="width: {{ max(4, $barPct) }}%;"></div>
							</div>
						</div>
						<div class="sales-therm-pct-figure">
							{{ $thermometer['has_target'] ? number_format($thermometer['compliance_pct'], 1) . '%' : 'Sin meta' }}
						</div>
					</div>

					<div class="sales-therm-inline-grid">
						<div class="sales-therm-inline-item">
							<div class="sales-therm-inline-val">{{ $m($thermometer['sales_net']) }}</div>
							<div class="sales-therm-inline-lbl">Venta acumulada</div>
							@if($salesDeltaPct !== null)
							<div class="sales-therm-inline-delta @if($salesDeltaPct >= 0) text-success @else text-danger @endif">
								<i class="fa-solid @if($salesDeltaPct >= 0) fa-caret-up @else fa-caret-down @endif"></i>
								{{ ($salesDeltaPct >= 0 ? '+' : '') . number_format($salesDeltaPct, 1) }}%
							</div>
							@endif
						</div>

						<div class="sales-therm-inline-item">
							<div class="sales-therm-inline-val">{{ $thermometer['has_target'] ? $m($thermometer['target_goal']) : 'Sin meta' }}</div>
							<div class="sales-therm-inline-lbl">Meta del período</div>
						</div>

						<div class="sales-therm-inline-item">
							<div class="sales-therm-inline-val">{{ $thermometer['pending_amount'] > 0 ? $m($thermometer['pending_amount']) : '$0.00' }}</div>
							<div class="sales-therm-inline-lbl">Importe pendiente</div>
						</div>

						<div class="sales-therm-inline-item">
							<div class="sales-therm-inline-val">{{ $thermometer['days_remaining'] }} días</div>
							<div class="sales-therm-inline-lbl">Tiempo restante</div>
							<div class="sales-therm-inline-lbl" style="font-size: 0.69rem;">({{ $thermometer['days_elapsed'] }}/{{ $thermometer['days_total'] }} transcurridos)</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Columna Derecha (5 cols): Participación por departamento (Barras horizontales) -->
		<div class="col-lg-5">
			<div class="sales-section-card">
				<div class="sales-section-header">
					<h2 class="sales-section-title">
						<span class="sales-title-icon"><i class="fa-solid fa-shield-halved"></i></span>
						<span>Participación por departamento</span>
					</h2>
					<select id="deptMetricSelect" class="sales-filter-control" style="width: auto; height: 30px; font-size: 0.76rem; padding: 0 10px;" aria-label="Métrica de departamento">
						<option value="sales" selected>Ventas netas</option>
						<option value="units">Unidades</option>
						<option value="margin">Margen</option>
					</select>
				</div>

				@if(!empty($departmentsDonut))
				<div class="sales-dept-bars-list" id="deptBarsList">
					@foreach($departmentsDonut as $dItem)
					@php
						$barWidthPct = $maxDeptSharePct > 0 ? max(6, round(($dItem['share_pct'] / $maxDeptSharePct) * 100, 1)) : 6;
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
					<div class="sales-dept-bar-row"
						data-sales="{{ $dItem['total'] }}"
						data-pct="{{ $dItem['share_pct'] }}"
						data-units="{{ $dItem['units'] }}"
						data-margin="{{ $dItem['margin'] ?? '' }}">
						<div class="text-truncate">
							@if($dItem['id'] !== null)
							<a href="{{ $U('/sales?' . http_build_query($deptFilterParams)) }}" class="sales-dept-link" title="Filtrar tablero por {{ $dItem['name'] }}">
								{{ $dItem['name'] }}
							</a>
							@else
							<span class="sales-dept-link" title="{{ $dItem['name'] }}">{{ $dItem['name'] }}</span>
							@endif
						</div>
						<div class="sales-dept-bar-track" title="{{ $dItem['name'] }}: {{ number_format($dItem['share_pct'], 1) }}% ({{ $m($dItem['total']) }})">
							<div class="sales-dept-bar-fill" style="background: {{ $dItem['color'] }}; width: {{ $barWidthPct }}%;"></div>
						</div>
						<span class="sales-dept-pct">{{ number_format($dItem['share_pct'], 1) }}%</span>
						<span class="sales-dept-amt">{{ $m($dItem['total']) }}</span>
					</div>
					@endforeach
				</div>
				@else
				<div class="text-center text-muted py-5 my-auto">Sin información de departamentos en el período.</div>
				@endif
			</div>
		</div>
	</div>

	<!-- FILA 3: RENDIMIENTO POR SUCURSAL (5 COLS) + ANÁLISIS DE PRODUCTOS (4 COLS) + OPORTUNIDADES COMERCIALES (3 COLS) -->
	<div class="row mb-3">
		<!-- Columna 1: Rendimiento por sucursal -->
		<div class="col-lg-5 mb-3 mb-lg-0" id="section-branches">
			<div class="sales-section-card">
				<div class="sales-section-header">
					<h2 class="sales-section-title">
						<span class="sales-title-icon"><i class="fa-solid fa-store"></i></span>
						<span>Rendimiento por sucursal</span>
					</h2>
					<button type="button" class="sales-ver-todas-btn" onclick="$('#pill-analisis').tab('show');">Ver todas</button>
				</div>

				<div class="table-responsive">
					<table class="sales-clean-table" id="branchesPerfTable">
						<thead>
							<tr>
								<th style="width: 28px;">#</th>
								<th>Sucursal</th>
								<th class="text-right is-sortable" data-sort-col="2" data-sort-type="num">Ventas netas <i class="fa-solid fa-caret-down ml-1"></i></th>
								<th class="text-center is-sortable" data-sort-col="3" data-sort-type="num">vs. período<br>anterior</th>
								<th class="text-center is-sortable" data-sort-col="4" data-sort-type="num">Margen bruto</th>
								<th class="text-right is-sortable" data-sort-col="5" data-sort-type="num">Trans.</th>
							</tr>
						</thead>
						<tbody>
							@forelse($branchesSummary as $idx => $br)
							<tr data-has-activity="{{ $br['has_activity'] ? '1' : '0' }}">
								<td class="text-muted font-weight-600 row-idx">{{ $idx + 1 }}</td>
								<td>
									<a href="{{ $U('/sales?' . http_build_query(array_filter(['branch_id' => $br['id'], 'group_id' => $selectedGroupId, 'product_id' => $selectedProductId, 'start_date' => $startDate, 'end_date' => $endDate], fn($v) => $v !== null && $v !== ''))) }}" class="sales-row-link" title="Filtrar tablero por {{ $br['name'] }}">
										{{ $br['name'] }}
									</a>
								</td>
								<td class="text-right font-weight-bold" data-sort-val="{{ $br['total'] }}">
									@if($br['has_activity'])
										{{ $m($br['total']) }}
									@else
										<span class="text-muted" title="Sin ventas registradas en el período">$0.00</span>
									@endif
								</td>
								<td class="text-center" data-sort-val="{{ $br['vs_prev_pct'] ?? -9999 }}">
									@if($br['has_activity'] && $br['vs_prev_pct'] !== null)
										<span class="sales-vs-prev @if($br['vs_prev_pct'] >= 0) is-up @else is-down @endif">
											{{ ($br['vs_prev_pct'] >= 0 ? '+' : '') . number_format($br['vs_prev_pct'], 1) }}%
										</span>
									@else
										<span class="sales-no-info" title="Sin registros comparables">—</span>
									@endif
								</td>
								<td class="text-center" data-sort-val="{{ $br['margin_pct'] ?? -9999 }}">
									@if($br['has_activity'] && $br['margin_pct'] !== null)
										<span class="sales-margin-pill">{{ number_format($br['margin_pct'], 1) }}%</span>
									@else
										<span class="sales-no-info" title="Sin información de margen">—</span>
									@endif
								</td>
								<td class="text-right font-weight-600" data-sort-val="{{ $br['tickets'] }}">{{ number_format($br['tickets']) }}</td>
							</tr>
							@empty
							<tr>
								<td colspan="6" class="text-center text-muted py-4">Sin información de ventas por sucursal en el período.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Columna 2: Análisis de productos (con pestañas Más vendidos | Mayor margen) -->
		<div class="col-lg-4 mb-3 mb-lg-0" id="section-top-products">
			<div class="sales-section-card">
				<div class="sales-section-header">
					<h2 class="sales-section-title">
						<span class="sales-title-icon"><i class="fa-solid fa-box-archive"></i></span>
						<span>Análisis de productos</span>
					</h2>
					<button type="button" class="sales-ver-todas-btn" onclick="$('#pill-detalle').tab('show'); $('a[href=\'#subtab-sku\']').tab('show');">Ver todos</button>
				</div>

				<div class="sales-prod-subtabs" role="tablist">
					<button type="button" class="sales-prod-subtab active" id="prod-tab-sales" data-prod-pane="#prod-pane-sales">Más vendidos</button>
					<button type="button" class="sales-prod-subtab" id="prod-tab-margin" data-prod-pane="#prod-pane-margin">Mayor margen</button>
				</div>

				<!-- Sub-pane 1: Más vendidos -->
				<div id="prod-pane-sales">
					<div class="table-responsive">
						<table class="sales-clean-table">
							<thead>
								<tr>
									<th style="width: 26px;">#</th>
									<th>Producto</th>
									<th class="text-right">Ventas netas</th>
									<th class="text-right">Unidades</th>
								</tr>
							</thead>
							<tbody>
								@forelse($topProductsBySales as $idx => $prod)
								<tr>
									<td class="text-muted font-weight-600">{{ $idx + 1 }}</td>
									<td>
										<div class="sales-prod-cell">
											<img src="{{ $U($prod['image_url']) }}" alt="{{ $prod['name'] }}" class="sales-prod-thumb">
											<a href="{{ $U('/sales?' . http_build_query(array_filter(['branch_id' => $selectedBranchId, 'group_id' => $selectedGroupId, 'product_id' => $prod['id'], 'start_date' => $startDate, 'end_date' => $endDate], fn($v) => $v !== null && $v !== ''))) }}" class="sales-row-link text-truncate" style="max-width: 135px;" title="Filtrar por {{ $prod['full_name'] ?? $prod['name'] }}">
												{{ $prod['name'] }}
											</a>
										</div>
									</td>
									<td class="text-right font-weight-bold">{{ $m($prod['total']) }}</td>
									<td class="text-right">{{ number_format($prod['units'], 0) }}</td>
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

				<!-- Sub-pane 2: Mayor margen (solo productos con costo registrado > 0) -->
				<div id="prod-pane-margin" class="d-none">
					<div class="table-responsive">
						<table class="sales-clean-table">
							<thead>
								<tr>
									<th style="width: 26px;">#</th>
									<th>Producto</th>
									<th class="text-center">Margen</th>
									<th class="text-right">Ventas netas</th>
									<th class="text-right">Unid.</th>
								</tr>
							</thead>
							<tbody>
								@forelse($topProductsByMargin as $idx => $prod)
								<tr>
									<td class="text-muted font-weight-600">{{ $idx + 1 }}</td>
									<td>
										<div class="sales-prod-cell">
											<img src="{{ $U($prod['image_url']) }}" alt="{{ $prod['name'] }}" class="sales-prod-thumb">
											<a href="{{ $U('/sales?' . http_build_query(array_filter(['branch_id' => $selectedBranchId, 'group_id' => $selectedGroupId, 'product_id' => $prod['id'], 'start_date' => $startDate, 'end_date' => $endDate], fn($v) => $v !== null && $v !== ''))) }}" class="sales-row-link text-truncate" style="max-width: 115px;" title="Filtrar por {{ $prod['full_name'] ?? $prod['name'] }}">
												{{ $prod['name'] }}
											</a>
										</div>
									</td>
									<td class="text-center">
										<span class="sales-margin-pill">{{ number_format($prod['margin_pct'], 1) }}%</span>
									</td>
									<td class="text-right font-weight-bold">{{ $m($prod['total']) }}</td>
									<td class="text-right">{{ number_format($prod['units'], 0) }}</td>
								</tr>
								@empty
								<tr>
									<td colspan="5" class="text-center text-muted py-4">Sin productos con costo registrado en el período.</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
					@if($dq['products_without_cost'] > 0)
					<div class="small text-muted mt-2 pt-1 border-top" style="font-size: 0.7rem;">
						<i class="fa-solid fa-circle-info text-warning mr-1"></i>Se excluyen {{ $dq['products_without_cost'] }} producto(s) sin costo unitario registrado para no distorsionar el ranking de margen.
					</div>
					@endif
				</div>
			</div>
		</div>

		<!-- Columna 3: Oportunidades comerciales -->
		<div class="col-lg-3" id="section-highlights">
			<div class="sales-section-card">
				<div class="sales-section-header">
					<h2 class="sales-section-title">
						<span class="sales-title-icon"><i class="fa-solid fa-seedling"></i></span>
						<span>Oportunidades comerciales</span>
					</h2>
				</div>

				<div class="sales-opp-list-vertical">
					@foreach($highlights as $hl)
					<div class="sales-opp-hcard is-{{ $hl['tone'] ?? 'amber' }}">
						<div class="sales-opp-hleft">
							<span class="sales-opp-hicon"><i class="fa-solid {{ $hl['icon'] ?? 'fa-triangle-exclamation' }}"></i></span>
							<div>
								<div class="sales-opp-htitle">{{ $hl['title'] }}</div>
								<div class="sales-opp-hdesc">{!! $hl['desc'] !!}</div>
							</div>
						</div>
						@if(!empty($hl['action_url']))
						<a href="{{ $U('/sales' . $hl['action_url']) }}" class="sales-opp-hbtn">
							<span>{{ $hl['action_label'] }}</span><i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</a>
						@elseif(!empty($hl['action_subtab']))
						<button type="button" class="sales-opp-hbtn" onclick="$('#pill-detalle').tab('show'); $('a[href=\'#{{ $hl['action_subtab'] }}\']').tab('show');">
							<span>{{ $hl['action_label'] }}</span><i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</button>
						@else
						<button type="button" class="sales-opp-hbtn" onclick="$('#pill-{{ str_replace('tab-', '', $hl['action_tab'] ?? 'analisis') }}').tab('show');">
							<span>{{ $hl['action_label'] }}</span><i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
						</button>
						@endif
					</div>
					@endforeach
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

</div>

<!-- ============================================================== -->
<!-- PESTAÑA 2: ANÁLISIS Y TENDENCIAS                               -->
<!-- ============================================================== -->
<div class="tab-pane fade @if($activeTab === 'analisis') show active @endif" id="tab-analisis" role="tabpanel">

	<!-- COMPARACIONES COMERCIALES: REAL VS META VS ANTERIOR VS AÑO PASADO -->
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

	<!-- DESGLOSE POR SUCURSAL (ANCHO COMPLETO) Y VENTA VS MERMA POR DEPARTAMENTO -->
	<div class="row mb-3">
		<div class="col-12 mb-3">
			<div class="card shadow-sm border-0">
				<div class="card-header bg-white border-0 font-weight-bold">Desempeño Comercial por Sucursal</div>
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead class="thead-light">
							<tr>
								<th>#</th>
								<th>Sucursal</th>
								<th class="text-right">Ventas Netas</th>
								<th class="text-center">vs. Período Anterior</th>
								<th class="text-center">Margen Bruto</th>
								<th class="text-right">Transacciones</th>
								<th class="text-right">Ticket Promedio</th>
							</tr>
						</thead>
						<tbody>
							@forelse($branchesSummary as $idx => $b)
							<tr>
								<td class="text-muted">{{ $idx + 1 }}</td>
								<td class="font-weight-600">
									<a href="{{ $U('/sales?' . http_build_query(['branch_id' => $b['id'], 'start_date' => $startDate, 'end_date' => $endDate, 'tab' => 'analisis'])) }}">{{ $b['name'] }}</a>
								</td>
								<td class="text-right font-weight-bold">{{ $b['has_activity'] ? $m($b['total']) : '$0.00' }}</td>
								<td class="text-center">
									@if($b['has_activity'] && $b['vs_prev_pct'] !== null)
									<span class="@if($b['vs_prev_pct'] >= 0) text-success @else text-danger @endif font-weight-bold">
										{{ ($b['vs_prev_pct'] >= 0 ? '+' : '') . number_format($b['vs_prev_pct'], 1) }}%
									</span>
									@else
									<span class="text-muted small">Sin información</span>
									@endif
								</td>
								<td class="text-center">
									@if($b['has_activity'] && $b['margin_pct'] !== null)
									<span class="text-success font-weight-bold">{{ number_format($b['margin_pct'], 1) }}%</span>
									@else
									<span class="text-muted small">Sin información</span>
									@endif
								</td>
								<td class="text-right">{{ number_format($b['tickets']) }}</td>
								<td class="text-right">{{ $b['tickets'] > 0 ? $m($b['total'] / $b['tickets']) : 'Sin información' }}</td>
							</tr>
							@empty
							<tr><td colspan="7" class="text-center text-muted py-3">Sin información de sucursales en el período.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Venta vs Merma por Departamento -->
		<div class="col-12">
			<div class="card shadow-sm border-0 h-100">
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
				<strong>Aclaración metodológica:</strong> Esta visualización distribuye la facturación generada de acuerdo con la ubicación física y zona de tienda asignada a cada producto (cámaras frías, exhibidores, estantes, congeladores). No representa conteo peatonal ni tráfico de afluencia de personas sin sensores.
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

@push('pageScripts')
<script src="{{ $U('/viewjs/pos_integration.js?v=', true) }}{{ $version }}"></script>
@endpush

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

	// Subpestañas de Análisis de Productos (Más vendidos | Mayor margen)
	document.querySelectorAll('.sales-prod-subtab').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelectorAll('.sales-prod-subtab').forEach(function(b) { b.classList.remove('active'); });
			this.classList.add('active');
			var targetSel = this.getAttribute('data-prod-pane');
			['#prod-pane-sales', '#prod-pane-margin'].forEach(function(sel) {
				var el = document.querySelector(sel);
				if (el) el.classList.toggle('d-none', sel !== targetSel);
			});
		});
	});

	// Ordenamiento de la tabla de Rendimiento por sucursal
	var branchesTable = document.getElementById('branchesPerfTable');
	if (branchesTable) {
		branchesTable.querySelectorAll('thead th.is-sortable').forEach(function(th) {
			var asc = false;
			th.addEventListener('click', function() {
				var colIdx = parseInt(this.getAttribute('data-sort-col'), 10);
				var tbody = branchesTable.querySelector('tbody');
				var rows = Array.from(tbody.querySelectorAll('tr[data-has-activity]'));
				asc = !asc;
				rows.sort(function(a, b) {
					var cellA = a.children[colIdx];
					var cellB = b.children[colIdx];
					var valA = parseFloat(cellA ? (cellA.getAttribute('data-sort-val') || '0') : '0');
					var valB = parseFloat(cellB ? (cellB.getAttribute('data-sort-val') || '0') : '0');
					return asc ? (valA - valB) : (valB - valA);
				});
				rows.forEach(function(r, idx) {
					var idxCell = r.querySelector('.row-idx');
					if (idxCell) idxCell.textContent = (idx + 1);
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

	function buildCurveData(granularity) {
		var prevValues = daily.map(function(_, idx) {
			return (prevDaily[idx] && typeof prevDaily[idx].v !== 'undefined') ? prevDaily[idx].v : 0;
		});

		if (granularity === 'weekly') {
			var wLabels = [];
			var wActual = [];
			var wPrev = [];
			var wGoal = [];
			for (var i = 0; i < daily.length; i += 7) {
				var chunk = daily.slice(i, i + 7);
				var pChunk = prevValues.slice(i, i + 7);
				if (chunk.length === 0) continue;
				var firstD = chunk[0].d.slice(8, 10) + '/' + chunk[0].d.slice(5, 7);
				var lastD = chunk[chunk.length - 1].d.slice(8, 10) + '/' + chunk[chunk.length - 1].d.slice(5, 7);
				wLabels.push(firstD + ' - ' + lastD);
				wActual.push(Math.round(chunk.reduce(function(acc, c) { return acc + Number(c.v || 0); }, 0) * 100) / 100);
				wPrev.push(Math.round(pChunk.reduce(function(acc, v) { return acc + Number(v || 0); }, 0) * 100) / 100);
				wGoal.push(Math.round(dailyGoalVal * chunk.length * 100) / 100);
			}
			return { labels: wLabels, actual: wActual, prev: wPrev, goal: wGoal };
		}

		return {
			labels: daily.map(function (d) { return d.d.slice(8, 10) + '/' + d.d.slice(5, 7); }),
			actual: daily.map(function (d) { return d.v; }),
			prev: prevValues,
			goal: daily.map(function () { return dailyGoalVal; })
		};
	}

	function drawCharts() {
		if (typeof Chart === 'undefined') return;

		var canvas = document.getElementById('sales-daily-chart');
		if (canvas && !curveChartInstance) {
			var money = function (v) { return '$' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 }); };
			var series = buildCurveData('daily');

			curveChartInstance = new Chart(canvas, {
				type: 'line',
				data: {
					labels: series.labels,
					datasets: [
						{
							label: 'Ventas actuales ($)',
							data: series.actual,
							borderColor: '#00C83B',
							backgroundColor: 'rgba(0, 200, 59, 0.14)',
							borderWidth: 2.3,
							fill: true,
							pointRadius: 2.2,
							pointBackgroundColor: '#00C83B',
							pointHoverRadius: 5,
							lineTension: 0.32
						},
						{
							label: 'Período anterior ($)',
							data: series.prev,
							borderColor: '#3b82f6',
							backgroundColor: 'transparent',
							borderWidth: 1.8,
							borderDash: [5, 4],
							fill: false,
							pointRadius: 0,
							pointHoverRadius: 4,
							lineTension: 0.32
						},
						{
							label: 'Meta del período ($)',
							data: series.goal,
							borderColor: '#64748b',
							backgroundColor: 'transparent',
							borderWidth: 1.4,
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
							gridLines: { color: '#f1f5f9' },
							ticks: { fontColor: '#64748b', fontSize: 10, maxTicksLimit: 11 }
						}],
						yAxes: [{
							gridLines: { color: '#f1f5f9' },
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

	var curveGranularitySelect = document.getElementById('curveGranularitySelect');
	if (curveGranularitySelect) {
		curveGranularitySelect.addEventListener('change', function() {
			if (!curveChartInstance) return;
			var s = buildCurveData(this.value);
			curveChartInstance.data.labels = s.labels;
			curveChartInstance.data.datasets[0].data = s.actual;
			curveChartInstance.data.datasets[1].data = s.prev;
			curveChartInstance.data.datasets[2].data = s.goal;
			curveChartInstance.update();
		});
	}

	// Selector de métrica en Participación por Departamento (Ventas netas / Unidades / Margen)
	var deptMetricSelect = document.getElementById('deptMetricSelect');
	if (deptMetricSelect) {
		deptMetricSelect.addEventListener('change', function() {
			var mode = this.value;
			var rows = document.querySelectorAll('#deptBarsList .sales-dept-bar-row');
			var newData = [];
			var totalMetric = 0;
			var maxVal = 0;

			rows.forEach(function(r) {
				var v = 0;
				if (mode === 'units') {
					v = parseFloat(r.getAttribute('data-units') || '0');
				} else if (mode === 'margin') {
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
				var pctEl = r.querySelector('.sales-dept-pct');
				var amtEl = r.querySelector('.sales-dept-amt');
				var barFillEl = r.querySelector('.sales-dept-bar-fill');
				var v = newData[idx];
				var pctNum = totalMetric > 0 ? ((v / totalMetric) * 100) : 0;
				var widthPct = maxVal > 0 ? Math.max(4, (v / maxVal) * 100) : 4;

				if (pctEl) pctEl.textContent = pctNum.toFixed(1) + '%';
				if (barFillEl) barFillEl.style.width = widthPct.toFixed(1) + '%';
				if (amtEl) {
					if (mode === 'units') {
						amtEl.textContent = Math.round(v).toLocaleString('en-US') + ' u.';
					} else if (mode === 'margin') {
						var mRaw = r.getAttribute('data-margin');
						amtEl.textContent = mRaw !== '' ? ('$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : 'Sin costo';
					} else {
						amtEl.textContent = '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					}
				}
			});
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
});
</script>
@stop
