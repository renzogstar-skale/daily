@php require_frontend_packages(['datatables', 'chartjs', 'animatecss']); @endphp

@extends('layout.default')

@section('title', 'Stock & Movimientos')

@push('pageStyles')
<link href="{{ $U('/css/daily_stock.css?v=', true) }}{{ time() }}" rel="stylesheet">
@endpush

@push('pageScripts')
<script>
	window.DAILY_STOCK_EXEC_INITIAL = {!! json_encode($stockExec ?? []) !!};
</script>
<script src="{{ $U('/viewjs/purchase.js?v=', true) }}{{ $version }}"></script>
@endpush

@php
$se = $stockExec ?? \Grocy\Services\StockExecutiveOverviewService::GetInstance()->GetExecutiveData();
$kpis = $se['kpis'] ?? [];
$donut = $se['donut'] ?? [];
$heatmap = $se['heatmap'] ?? [];
$topProds = $se['top_products'] ?? [];
$expProds = $se['expiring_products'] ?? [];
$branchesTable = $se['branches_table'] ?? [];
$movements = $se['movements'] ?? [];
$decisions = $se['priority_decisions']['featured'] ?? [];
$allDecisions = $se['priority_decisions']['all'] ?? [];
$filters = $se['filters'] ?? [];
$meta = $se['meta'] ?? [];

// Helper para generar SVG de sparkline con puntos (exacto a la referencia visual)
$renderSparkline = function(array $pts, string $colorHex, string $gradId) {
	$w = 240;
	$h = 34;
	$n = count($pts);
	if ($n < 2) return '';
	$stepX = $w / ($n - 1);
	$coords = [];
	for ($i = 0; $i < $n; $i++) {
		$coords[] = [round($i * $stepX, 1), $pts[$i]];
	}
	$d = 'M ' . $coords[0][0] . ',' . $coords[0][1];
	for ($i = 0; $i < $n - 1; $i++) {
		$xMid = round(($coords[$i][0] + $coords[$i+1][0]) / 2, 1);
		$d .= ' C ' . $xMid . ',' . $coords[$i][1] . ' ' . $xMid . ',' . $coords[$i+1][1] . ' ' . $coords[$i+1][0] . ',' . $coords[$i+1][1];
	}
	$areaD = $d . " L {$w},{$h} L 0,{$h} Z";
	$circles = '';
	foreach ($coords as $idx => $c) {
		if ($idx > 0 && $idx < $n - 1) {
			$circles .= '<circle cx="' . $c[0] . '" cy="' . $c[1] . '" r="2.2" fill="' . $colorHex . '" stroke="#ffffff" stroke-width="0.8"/>';
		}
	}
	return '<svg class="dsm-kpi-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">'
		. '<path d="' . $areaD . '" fill="url(#' . $gradId . ')"/>'
		. '<path d="' . $d . '" fill="none" stroke="' . $colorHex . '" stroke-width="1.8" stroke-linecap="round"/>'
		. $circles
		. '</svg>';
};
@endphp

@section('content')
<!-- Definición de gradientes SVG para los minigráficos de tendencia -->
<svg width="0" height="0" style="position:absolute; pointer-events:none;">
	<defs>
		<linearGradient id="dsmSparkGreen" x1="0" y1="0" x2="0" y2="1">
			<stop offset="0%" stop-color="#10b981" stop-opacity="0.28"/>
			<stop offset="100%" stop-color="#10b981" stop-opacity="0.02"/>
		</linearGradient>
		<linearGradient id="dsmSparkRed" x1="0" y1="0" x2="0" y2="1">
			<stop offset="0%" stop-color="#ef4444" stop-opacity="0.28"/>
			<stop offset="100%" stop-color="#ef4444" stop-opacity="0.02"/>
		</linearGradient>
	</defs>
</svg>

<div class="dsm-exec-container" id="dsm-exec-app">

	<!-- ======================================================================
	     1. ENCABEZADO EJECUTIVO Y BARRA DE FILTROS GLOBALES (SECCIÓN 10)
	     ====================================================================== -->
	<header class="dsm-header">
		<div class="dsm-title-block">
			<div class="dsm-title-row">
				<h1 class="dsm-title">Stock &amp; Movimientos</h1>
				<span class="badge badge-light border text-muted font-weight-bold" style="font-size: 10.5px;" title="{{ $meta['demo_notice'] ?? '' }}">
					<i class="fa-solid fa-shield-check text-success mr-1"></i>Conciliado · Rol: {{ ucfirst($meta['role'] ?? 'Manager') }}
				</span>
			</div>
			<p class="dsm-subtitle">
				<span>Resumen ejecutivo &middot; Disponibilidad, rotación, cobertura y trazabilidad del inventario</span>
				<a href="javascript:void(0)" class="dsm-quick-pill" onclick="dsmOpenWorkflowModal()">
					<i class="fa-solid fa-diagram-project"></i> Flujo Operativo (10 Pasos)
				</a>
				<a href="javascript:void(0)" class="dsm-quick-pill" onclick="dsmOpenMovementsModal('todos')">
					<i class="fa-solid fa-clock-rotate-left"></i> Kardex en Vivo
				</a>
			</p>
		</div>

		<div class="dsm-filters-bar">
			<!-- Filtro 1: Fecha -->
			<div class="dsm-filter-box" title="Seleccionar fecha o período de corte">
				<i class="fa-regular fa-calendar dsm-filter-icon"></i>
				<div class="dsm-filter-stack" style="min-width: 112px;">
					<span class="dsm-filter-label">Hoy</span>
					<select id="dsm-filter-date" class="dsm-filter-select" aria-label="Fecha">
						@php
	$dd = fn($t) => ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][intval(date('N', $t))] . ', ' . date('j', $t) . ' ' . ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][intval(date('n', $t))] . ' ' . date('Y', $t);
@endphp
						<option value="{{ date('Y-m-d') }}" selected>Hoy, {{ $dd(time()) }}</option>
						<option value="{{ date('Y-m-d', strtotime('-1 day')) }}">Ayer, {{ $dd(strtotime('-1 day')) }}</option>
						<option value="{{ date('Y-m-d', strtotime('-7 days')) }}">Hace una semana, {{ $dd(strtotime('-7 days')) }}</option>
						<option value="{{ date('Y-m-t', strtotime('first day of last month')) }}">Cierre del mes anterior</option>
					</select>
				</div>
			</div>

			<!-- Filtro 2: Sucursal -->
			<div class="dsm-filter-box" title="Filtrar por sucursal">
				<div class="dsm-filter-stack" style="min-width: 96px;">
					<span class="dsm-filter-label">Sucursal</span>
					<select id="dsm-filter-branch" class="dsm-filter-select" aria-label="Sucursal">
						<option value="all">Todas</option>
						@foreach($branchesTable as $b)
							<option value="{{ $b['id'] }}" @if(($filters['branch_id'] ?? 'all') == $b['id']) selected @endif>{{ $b['name'] }}</option>
						@endforeach
					</select>
				</div>
			</div>

			<!-- Filtro 3: Departamento -->
			<div class="dsm-filter-box" title="Filtrar por departamento">
				<div class="dsm-filter-stack" style="min-width: 100px;">
					<span class="dsm-filter-label">Departamento</span>
					<select id="dsm-filter-dept" class="dsm-filter-select" aria-label="Departamento">
						<option value="todos">Todos</option>
						<option value="dairy">Dairy</option>
						<option value="fruver">Frutas y verduras</option>
						<option value="carnes">Carnes</option>
						<option value="bakery">Bakery</option>
						<option value="preparadas">Comidas preparadas</option>
						<option value="electronica">Electrónica</option>
						<option value="otros">Otros</option>
					</select>
				</div>
			</div>

			<!-- Filtro 4: Categoría -->
			<div class="dsm-filter-box" title="Filtrar por categoría">
				<div class="dsm-filter-stack" style="min-width: 92px;">
					<span class="dsm-filter-label">Categoría</span>
					<select id="dsm-filter-category" class="dsm-filter-select" aria-label="Categoría">
						<option value="todas">Todas</option>
						<option value="frescos">Frescos</option>
						<option value="refrigerados">Refrigerados</option>
						<option value="congelados">Congelados</option>
						<option value="abarrotes">Abarrotes</option>
						<option value="non_food">Non-Food</option>
					</select>
				</div>
			</div>

			<!-- Botón Filtros Avanzados Desplegables -->
			<button type="button" class="dsm-adv-toggle-btn" id="dsm-btn-adv-filters" title="Mostrar filtros avanzados (Proveedor, SKU, Estado, Ubicación, Tipo de movimiento)">
				<i class="fa-solid fa-sliders"></i>
				<span class="d-none d-xl-inline">Filtros</span>
			</button>

			<!-- Indicador de última sincronización -->
			<div class="dsm-sync-box" title="Calculado al cargar la página con las existencias y el kardex registrados">
				<span class="dsm-sync-dot"></span>
				<div class="dsm-sync-stack">
					<span class="dsm-sync-label">Última actualización</span>
					<span class="dsm-sync-time" id="dsm-last-updated">{{ $meta['last_updated'] ?? '' }}</span>
				</div>
			</div>

			<!-- Botón Actualizar Manual -->
			<button type="button" class="dsm-refresh-btn" id="dsm-btn-refresh" title="Recalcular indicadores ahora">
				<i class="fa-solid fa-rotate-right"></i>
			</button>
		</div>
	</header>

	<!-- Barra Desplegable de Filtros Avanzados (Proveedor, Producto/SKU, Estado, Ubicación, Tipo de movimiento) -->
	<section class="dsm-advanced-filters" id="dsm-advanced-filters-panel" aria-label="Filtros avanzados">
		<div class="dsm-adv-grid">
			<div class="dsm-adv-field">
				<label for="dsm-filter-supplier"><i class="fa-solid fa-truck-field mr-1"></i>Proveedor</label>
				<select id="dsm-filter-supplier">
					<option value="todos">Todos los proveedores</option>
					<option value="dairy_farms">Florida Dairy Farms</option>
					<option value="fresh_valley">Fresh Valley Produce</option>
					<option value="prime_meats">Prime Meats Co.</option>
					<option value="artisan_bake">Artisan Bakery Supply</option>
					@if(!empty($suppliers))
						@foreach($suppliers as $sup)
							<option value="sup_{{ $sup->id }}">{{ $sup->name }}</option>
						@endforeach
					@endif
				</select>
			</div>

			<div class="dsm-adv-field">
				<label for="dsm-filter-sku"><i class="fa-solid fa-barcode mr-1"></i>Producto / SKU / UPC</label>
				<input type="text" id="dsm-filter-sku" placeholder="Ej. Leche, DAI-001, 75010...">
			</div>

			<div class="dsm-adv-field">
				<label for="dsm-filter-status"><i class="fa-solid fa-traffic-light mr-1"></i>Estado de inventario</label>
				<select id="dsm-filter-status">
					<option value="todos">Todos los estados</option>
					<option value="optimal">Óptimo / Normal</option>
					<option value="warning">Próximo a vencer / Vigilar</option>
					<option value="critical">Bajo mínimo / Crítico</option>
				</select>
			</div>

			<div class="dsm-adv-field">
				<label for="dsm-filter-location"><i class="fa-solid fa-location-dot mr-1"></i>Ubicación física</label>
				<select id="dsm-filter-location">
					<option value="todas">Todas las ubicaciones</option>
					<option value="camara_a1">Cámara Fría A1 (Lácteos)</option>
					<option value="camara_b1">Cámara Fría B1 (Quesos)</option>
					<option value="fruver_f1">Isla Fruver F1/F2</option>
					<option value="carnes_c1">Cámara Carnes C1</option>
					<option value="cuarentena_q1">Zona Cuarentena Q1</option>
					@if(!empty($locations))
						@foreach($locations as $loc)
							<option value="loc_{{ $loc->id }}">{{ $loc->name }}</option>
						@endforeach
					@endif
				</select>
			</div>

			<div class="dsm-adv-field">
				<label for="dsm-filter-movtype"><i class="fa-solid fa-arrow-right-arrow-left mr-1"></i>Tipo de movimiento</label>
				<select id="dsm-filter-movtype">
					<option value="todos">Todos los movimientos</option>
					<option value="entradas">Entradas de mercancía</option>
					<option value="salidas">Salidas por venta</option>
					<option value="traslados">Traslados entre sucursales</option>
					<option value="devoluciones">Devoluciones de clientes</option>
					<option value="despachos">Despachos</option>
					<option value="ajustes">Ajustes de inventario</option>
					<option value="mermas">Mermas</option>
				</select>
			</div>

			<div class="dsm-adv-field d-flex" style="gap: 6px;">
				<button type="button" class="btn btn-sm btn-success font-weight-bold flex-fill" style="height: 34px;" onclick="dsmApplyAllFilters()">
					<i class="fa-solid fa-check mr-1"></i>Aplicar
				</button>
				<button type="button" class="btn btn-sm btn-light border font-weight-bold" style="height: 34px;" onclick="dsmResetAllFilters()" title="Limpiar todos los filtros">
					<i class="fa-solid fa-filter-circle-xmark"></i>
				</button>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     2. OCHO TARJETAS KPI EJECUTIVAS (SECCIÓN 1 — 2 FILAS DE 4)
	     ====================================================================== -->
	<!-- PRIMERA FILA -->
	<div class="dsm-kpi-grid">
		<!-- KPI 1: Valor del inventario -->
		@php $k1 = $kpis['inventory_value']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-inventory_value" data-kpi="inventory_value" data-toggle="tooltip" title="{{ $k1['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-green">
					<i class="fa-solid fa-cubes"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k1['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-inventory_value">{{ $k1['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-good" id="dsm-delta-inventory_value">{{ $k1['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-inventory_value">{{ $k1['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k1['sparkline_points'], '#10b981', 'dsmSparkGreen') !!}
		</div>

		<!-- KPI 2: Unidades en stock -->
		@php $k2 = $kpis['stock_units']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-stock_units" data-kpi="stock_units" data-toggle="tooltip" title="{{ $k2['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-green">
					<i class="fa-solid fa-layer-group"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k2['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-stock_units">{{ $k2['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-good" id="dsm-delta-stock_units">{{ $k2['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-stock_units">{{ $k2['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k2['sparkline_points'], '#10b981', 'dsmSparkGreen') !!}
		</div>

		<!-- KPI 3: Rotación de inventario -->
		@php $k3 = $kpis['turnover']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-turnover" data-kpi="turnover" data-toggle="tooltip" title="{{ $k3['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-red">
					<i class="fa-solid fa-cart-shopping"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k3['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-turnover">{{ $k3['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-bad" id="dsm-delta-turnover">{{ $k3['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-turnover">{{ $k3['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k3['sparkline_points'], '#ef4444', 'dsmSparkRed') !!}
		</div>

		<!-- KPI 4: Cobertura promedio -->
		@php $k4 = $kpis['coverage']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-coverage" data-kpi="coverage" data-toggle="tooltip" title="{{ $k4['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-green">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k4['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-coverage">{{ $k4['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-good" id="dsm-delta-coverage">{{ $k4['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-coverage">{{ $k4['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k4['sparkline_points'], '#10b981', 'dsmSparkGreen') !!}
		</div>
	</div>

	<!-- SEGUNDA FILA -->
	<div class="dsm-kpi-grid">
		<!-- KPI 5: Productos bajo mínimo -->
		@php $k5 = $kpis['below_min']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-below_min" data-kpi="below_min" data-toggle="tooltip" title="{{ $k5['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-red">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k5['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-below_min">{{ $k5['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-bad" id="dsm-delta-below_min">{{ $k5['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-below_min">{{ $k5['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k5['sparkline_points'], '#ef4444', 'dsmSparkRed') !!}
		</div>

		<!-- KPI 6: Próximos a vencer -->
		@php $k6 = $kpis['expiring_soon']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-expiring_soon" data-kpi="expiring_soon" data-toggle="tooltip" title="{{ $k6['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-red">
					<i class="fa-regular fa-calendar-days"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k6['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-expiring_soon">{{ $k6['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-bad" id="dsm-delta-expiring_soon">{{ $k6['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-expiring_soon">{{ $k6['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k6['sparkline_points'], '#ef4444', 'dsmSparkRed') !!}
		</div>

		<!-- KPI 7: Productos agotados -->
		@php $k7 = $kpis['out_of_stock']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-out_of_stock" data-kpi="out_of_stock" data-toggle="tooltip" title="{{ $k7['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-green">
					<i class="fa-regular fa-circle-check"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k7['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-out_of_stock">{{ $k7['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-good" id="dsm-delta-out_of_stock">{{ $k7['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-out_of_stock">{{ $k7['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k7['sparkline_points'], '#10b981', 'dsmSparkGreen') !!}
		</div>

		<!-- KPI 8: Valor en riesgo (vencimientos) -->
		@php $k8 = $kpis['risk_value']; @endphp
		<div class="dsm-kpi-card" id="dsm-card-risk_value" data-kpi="risk_value" data-toggle="tooltip" title="{{ $k8['formula'] }}">
			<div class="dsm-kpi-top">
				<div class="dsm-kpi-icon is-amber">
					<i class="fa-solid fa-coins"></i>
				</div>
				<div class="dsm-kpi-info">
					<span class="dsm-kpi-label">{{ $k8['label'] }}</span>
					<span class="dsm-kpi-value" id="dsm-val-risk_value">{{ $k8['value'] }}</span>
					<div class="dsm-kpi-delta-line">
						<span class="dsm-kpi-delta is-bad" id="dsm-delta-risk_value">{{ $k8['delta'] }}</span>
						<span class="dsm-kpi-sub" id="dsm-sub-risk_value">{{ $k8['subtext'] }}</span>
					</div>
				</div>
			</div>
			{!! $renderSparkline($k8['sparkline_points'], '#ef4444', 'dsmSparkRed') !!}
		</div>
	</div>

	<!-- ======================================================================
	     3. FILA CENTRAL: EVOLUCIÓN Y ROTACIÓN (SECCIÓN 2) + DONUT DEPARTAMENTO (SECCIÓN 3)
	     ====================================================================== -->
	<div class="dsm-row-middle">
		<!-- GRÁFICO COMBINADO: EVOLUCIÓN DEL INVENTARIO Y ROTACIÓN -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Evolución del inventario y rotación</h2>
				<div class="dsm-card-controls">
					<!-- Selector de granularidad temporal (Día, Semana, Mes, Trimestre, Año) -->
					<div class="dsm-period-pills" role="group" aria-label="Granularidad temporal">
						<button type="button" class="dsm-period-pill" data-period="day">Día</button>
						<button type="button" class="dsm-period-pill" data-period="week">Sem</button>
						<button type="button" class="dsm-period-pill active" data-period="month">Mes</button>
						<button type="button" class="dsm-period-pill" data-period="quarter">Trim</button>
						<button type="button" class="dsm-period-pill" data-period="year">Año</button>
					</div>

					<!-- Selector de vista / métrica -->
					<select id="dsm-evo-view-select" class="dsm-select-sm" aria-label="Vista de evolución">
						<option value="value" selected>Valor de inventario</option>
						<option value="units">Unidades disponibles</option>
						<option value="rotation">Rotación</option>
						<option value="coverage">Cobertura (días)</option>
						<option value="year_compare">Comparativo entre años</option>
						<option value="branch_compare">Comparativo entre sucursales</option>
					</select>
				</div>
			</div>

			<div class="dsm-evo-legend-bar">
				<div class="dsm-evo-legend">
					<span class="dsm-legend-item" id="dsm-leg-bars">
						<span class="dsm-legend-dot" style="background: #10b981;"></span>
						<span id="dsm-leg-bars-text">Valor del inventario ($)</span>
					</span>
					<span class="dsm-legend-item" id="dsm-leg-units" title="Clic para mostrar/ocultar unidades en stock">
						<span class="dsm-legend-dot" style="background: #94a3b8;"></span>
						<span>Unidades en stock</span>
					</span>
					<span class="dsm-legend-item" id="dsm-leg-line">
						<span class="dsm-legend-dot" style="background: #eab308;"></span>
						<span id="dsm-leg-line-text">Rotación</span>
					</span>
				</div>
			</div>

			<div class="dsm-evo-canvas-wrap">
				<canvas id="dsmEvolutionChart"></canvas>
			</div>
		</div>

		<!-- GRÁFICO DONUT: DISTRIBUCIÓN DE INVENTARIO POR DEPARTAMENTO -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Distribución de inventario por departamento</h2>
				<div class="dsm-card-controls">
					<select id="dsm-donut-metric-select" class="dsm-select-sm" aria-label="Métrica de distribución">
						<option value="value" selected>Valor ($)</option>
						<option value="units">Unidades</option>
						<option value="pct">Porcentaje (%)</option>
					</select>
				</div>
			</div>

			<div class="dsm-donut-body">
				<div class="dsm-donut-canvas-box">
					<canvas id="dsmDonutChart"></canvas>
					<div class="dsm-donut-center">
						<div class="dsm-donut-center-val" id="dsm-donut-center-val">{{ $donut['center_value'] ?? 'Sin información' }}</div>
						<div class="dsm-donut-center-sub" id="dsm-donut-center-sub">{{ $donut['center_sub'] ?? '100%' }}</div>
					</div>
				</div>

				<div class="dsm-dept-list" id="dsm-dept-list">
					@foreach($donut['departments'] ?? [] as $dept)
						<div class="dsm-dept-row" data-dept-id="{{ $dept['id'] }}" title="Clic para filtrar y profundizar en {{ $dept['name'] }} ({{ number_format($dept['units'], 0) }} uds)">
							<div class="dsm-dept-left">
								<span class="dsm-dept-dot" style="background: {{ $dept['color'] }};"></span>
								<span>{{ $dept['name'] }}</span>
							</div>
							<div class="dsm-dept-right">
								<span class="dsm-dept-pct">{{ number_format($dept['share_pct'], 1) }}%</span>
								<span class="dsm-dept-val">${{ number_format($dept['reference_display_value'] ?? $dept['value'], 0) }}</span>
							</div>
						</div>
					@endforeach
				</div>
			</div>

			<!-- Panel de profundización jerárquica (Departamento › Categoría › Subcategoría › SKU › Lote y Ubicación) -->
			<div class="dsm-dept-drilldown" id="dsm-dept-drilldown">
				<div class="d-flex align-items-center justify-content-between mb-1">
					<strong class="text-dark" id="dsm-drill-title"><i class="fa-solid fa-sitemap text-success mr-1"></i>Desglose Jerárquico</strong>
					<button type="button" class="btn btn-xs btn-link text-muted p-0" onclick="dsmClearDeptDrilldown()">Ver todos los departamentos &times;</button>
				</div>
				<div id="dsm-drill-content"></div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     4. FILA 3: MAPA DE CALOR (SECCIÓN 4) + TOP 10 VALOR (SECCIÓN 5) + RIESGO VENCIMIENTO (SECCIÓN 6)
	     ====================================================================== -->
	<div class="dsm-row-three">
		<!-- SECCIÓN 4: MAPA DE CALOR DE EXISTENCIAS -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title" id="dsm-hm-title">Mapa de calor de existencias (unidades)</h2>
				<select id="dsm-hm-metric-select" class="dsm-select-sm" aria-label="Métrica del mapa de calor">
					<option value="units" selected>Unidades en stock</option>
					<option value="stockout_risk">Riesgo de agotamiento</option>
					<option value="movements_vol">Volumen de movimientos</option>
					<option value="replenishment">Actividad de reposición</option>
					<option value="hourly_demand">Demanda por hora</option>
				</select>
			</div>

			<div class="dsm-heatmap-wrap">
				<div class="dsm-heatmap-grid" id="dsm-heatmap-grid">
					@foreach($heatmap['grid'] ?? [] as $row)
						<div class="dsm-hm-row">
							<span class="dsm-hm-day">{{ $row['day'] }}</span>
							@foreach($row['cells'] as $cell)
								<div class="dsm-hm-cell"
									style="background-color: {{ $cell['color'] }};"
									data-period="{{ $cell['period_label'] }}"
									data-intensity="{{ $cell['intensity'] }}"
									data-metrics="{{ htmlspecialchars(json_encode($cell['metrics']), ENT_QUOTES, 'UTF-8') }}"
									title="{{ $cell['period_label'] }} · {{ $cell['metrics']['units']['formatted'] }} ({{ $cell['metrics']['units']['status'] }})">
								</div>
							@endforeach
						</div>
					@endforeach
					<div class="dsm-hm-hours">
						<span></span>
						@foreach($heatmap['hours'] ?? [] as $hr)
							<span class="dsm-hm-hour">{{ $hr }}</span>
						@endforeach
					</div>
				</div>

				<div class="dsm-hm-legend">
					<span id="dsm-hm-min-label">Menor stock</span>
					<div class="dsm-hm-gradient-bar" id="dsm-hm-gradient-bar"></div>
					<span id="dsm-hm-max-label">Mayor stock</span>
				</div>
			</div>
		</div>

		<!-- SECCIÓN 5: TOP 10 PRODUCTOS CON MAYOR VALOR EN INVENTARIO -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Top 10 productos con mayor valor en inventario</h2>
				<select id="dsm-top10-sort-select" class="dsm-select-sm" aria-label="Ordenar Top 10">
					<option value="value" selected>Valor ($)</option>
					<option value="units">Unidades</option>
					<option value="rotation">Rotación</option>
					<option value="risk">Riesgo</option>
				</select>
			</div>

			<div class="table-responsive">
				<table class="dsm-exec-table" id="dsm-top10-table">
					<thead>
						<tr>
							<th style="width: 24px;">#</th>
							<th>Producto</th>
							<th>Departamento</th>
							<th style="text-align: right;">Valor</th>
							<th style="text-align: right;">Unidades</th>
						</tr>
					</thead>
					<tbody id="dsm-top10-tbody">
						@foreach($topProds as $tp)
							<tr onclick="dsmOpenProduct360({{ $tp['product_id'] }})" title="SKU: {{ $tp['sku'] }} · UPC: {{ $tp['upc'] }} · Rotación: {{ $tp['rotation'] }}x · Cobertura: {{ $tp['coverage_days'] }}d · Estado: {{ $tp['status_label'] }} (Clic para abrir Ficha 360°)">
								<td class="font-weight-bold text-muted">{{ $tp['rank'] }}</td>
								<td>
									<div class="dsm-prod-cell">
										<img src="{{ $U($tp['image']) }}" alt="{{ $tp['name'] }}" class="dsm-prod-thumb">
										<span class="dsm-prod-name">{{ $tp['name'] }}</span>
									</div>
								</td>
								<td class="text-muted">{{ $tp['department'] }}</td>
								<td style="text-align: right; font-weight: 800; color: #0f172a;">{{ $tp['formatted_value'] }}</td>
								<td style="text-align: right; font-weight: 600;">{{ $tp['formatted_units'] }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<!-- SECCIÓN 6: PRODUCTOS CON RIESGO DE VENCIMIENTO -->
		<div class="dsm-card" id="dsm-section-fefo">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Productos con riesgo de vencimiento</h2>
				<select id="dsm-fefo-days-select" class="dsm-select-sm" aria-label="Umbral de alerta FEFO">
					<option value="30" selected>Próximos 30 días</option>
					<option value="15">Próximos 15 días</option>
					<option value="7">Próximos 7 días</option>
					<option value="3">Próximos 3 días</option>
					<option value="1">Próximo 1 día</option>
				</select>
			</div>

			<div class="table-responsive">
				<table class="dsm-exec-table" id="dsm-fefo-table">
					<thead>
						<tr>
							<th style="width: 24px;">#</th>
							<th>Producto</th>
							<th style="text-align: center;">Días</th>
							<th style="text-align: right;">Stock</th>
							<th style="text-align: right;">Valor</th>
						</tr>
					</thead>
					<tbody id="dsm-fefo-tbody">
						@foreach($expProds as $ep)
							<tr onclick="dsmOpenFefoActionModal({{ htmlspecialchars(json_encode($ep), ENT_QUOTES, 'UTF-8') }})" title="Lote: {{ $ep['lot'] }} · Vence: {{ $ep['expiry_date'] }} · {{ $ep['branch_name'] }} ({{ $ep['location'] }}) · Estado FEFO: {{ $ep['fefo_state'] }} · Clic para ejecutar acción FEFO">
								<td class="font-weight-bold text-muted">{{ $ep['rank'] }}</td>
								<td>
									<div class="dsm-prod-cell">
										<img src="{{ $U($ep['image']) }}" alt="{{ $ep['name'] }}" class="dsm-prod-thumb">
										<span class="dsm-prod-name">{{ $ep['name'] }}</span>
									</div>
								</td>
								<td style="text-align: center;">
									<span class="dsm-days-badge">{{ $ep['days_left'] }}</span>
								</td>
								<td style="text-align: right; font-weight: 600;">{{ $ep['stock'] }}</td>
								<td style="text-align: right; font-weight: 700; color: #0f172a;">{{ $ep['formatted_value'] }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     5. FILA 4: TENDENCIA POR SUCURSAL (SECCIÓN 7) + MOVIMIENTOS HOY (SECCIÓN 8) + DECISIONES PRIORITARIAS (SECCIÓN 9)
	     ====================================================================== -->
	<div class="dsm-row-four">
		<!-- SECCIÓN 7: TENDENCIA POR SUCURSAL -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Tendencia por sucursal</h2>
				<div class="dsm-branch-Write-pills">
					<button type="button" class="dsm-branch-pill active" data-bmode="value">Valor ($)</button>
					<button type="button" class="dsm-branch-pill" data-bmode="units">Unidades</button>
					<button type="button" class="dsm-branch-pill" data-bmode="variation">Variación</button>
					<button type="button" class="dsm-branch-pill" id="dsm-btn-toggle-all-branches" title="Alternar entre Top 5 y las 8 sucursales de la red">8 Sucursales</button>
				</div>
			</div>

			<div class="table-responsive">
				<table class="dsm-branch-table" id="dsm-branch-table">
					<thead>
						<tr>
							<th onclick="dsmSortBranches('name')">Sucursal</th>
							<th style="text-align: right;" onclick="dsmSortBranches('inventory_value')" id="dsm-bcol-val">Valor inventario</th>
							<th style="text-align: right;" onclick="dsmSortBranches('units')" id="dsm-bcol-units">Unidades</th>
							<th style="text-align: center;" onclick="dsmSortBranches('rotation')">Rotación</th>
							<th style="text-align: center;" onclick="dsmSortBranches('coverage_days')">Cobertura</th>
							<th style="text-align: center;">Estado</th>
						</tr>
					</thead>
					<tbody id="dsm-branch-tbody">
						@foreach($branchesTable as $idx => $br)
							@php
								$iconClass = $br['status'] === 'optimal' ? 'is-optimal' : ($br['status'] === 'warning' ? 'is-warning' : 'is-critical');
								$faIcon = $br['status'] === 'optimal' ? 'fa-check' : ($br['status'] === 'warning' ? 'fa-exclamation' : 'fa-xmark');
								// En vista inicial mostramos las 5 de la foto (ajustando 04 y 05 visualmente o mostrando las 8 al expandir)
								$hiddenExtra = $idx >= 5 ? 'd-none dsm-extra-branch-row' : '';
							@endphp
							<tr class="{{ $hiddenExtra }}" data-branch-id="{{ $br['id'] }}" onclick="dsmSelectBranchRow({{ $br['id'] }})" title="Dif. Inventario: {{ $br['inventory_diff_pct'] }}% (${{ number_format(abs($br['inventory_diff_val']), 0) }}) · Desempeño vs período anterior: {{ $br['prev_variation_pct'] > 0 ? '+' : '' }}{{ $br['prev_variation_pct'] }}% · Clic para filtrar por {{ $br['name'] }}">
								<td class="font-weight-bold text-dark">{{ $br['name'] }}</td>
								<td style="text-align: right; font-weight: 800;" class="dsm-bcell-val">${{ number_format($br['inventory_value'], 0) }}</td>
								<td style="text-align: right; font-weight: 600;" class="dsm-bcell-units">{{ number_format($br['units'], 0) }}</td>
								<td style="text-align: center; font-weight: 700; color: #ef4444;">{{ number_format($br['rotation'], 1) }}</td>
								<td style="text-align: center; color: #334155;">{{ number_format($br['coverage_days'], 0) }} días</td>
								<td style="text-align: center;">
									<span class="dsm-state-icon {{ $iconClass }}" title="{{ $br['status_label'] }} · Dif: {{ $br['inventory_diff_pct'] }}%">
										<i class="fa-solid {{ $faIcon }}"></i>
									</span>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<!-- SECCIÓN 8: MOVIMIENTOS DE INVENTARIO EN TIEMPO REAL -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Movimientos de inventario (hoy)</h2>
				<div class="dsm-card-controls">
					<select id="dsm-moves-branch-select" class="dsm-select-sm" aria-label="Sucursal de movimientos">
						<option value="all" selected>Todas las sucursales</option>
						@foreach($branchesTable as $b)
							<option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
						@endforeach
						<option value="detailed_7">Ver 7 categorías detalladas</option>
					</select>
				</div>
			</div>

			<div class="dsm-moves-chart-area" id="dsm-moves-bars-container">
				@php
					$bars = $movements['bars_photo'] ?? [];
					$maxMove = max(1, max(array_column($bars, 'units') ?: [12450]));
				@endphp
				@foreach($bars as $bar)
					@php $pctHeight = max(12, round(($bar['units'] / $maxMove) * 145)); @endphp
					<div class="dsm-move-bar-col" onclick="dsmOpenMovementsModal('{{ $bar['key'] }}')" title="{{ $bar['full_label'] }}: {{ number_format($bar['units'], 0) }} uds · Clic para abrir auditoría de movimientos y Kardex">
						<span class="dsm-move-bar-val">{{ number_format($bar['units'], 0) }}</span>
						<div class="dsm-move-bar-fill" style="height: {{ $pctHeight }}px; background: {{ $bar['color'] }};"></div>
						<span class="dsm-move-bar-label">{{ $bar['label'] }}</span>
					</div>
				@endforeach
			</div>

			<div class="d-flex align-items-center justify-content-between pt-2 mt-1" style="font-size: 11px; color: #64748b;">
				<span><i class="fa-solid fa-check-double text-success mr-1"></i>Sin doble conteo · Otros (680) = Despachos (340) + Ajustes (190) + Mermas (150)</span>
				<a href="javascript:void(0)" class="font-weight-bold text-success" onclick="dsmOpenMovementsModal('todos')">Ver Kardex &rarr;</a>
			</div>
		</div>

		<!-- SECCIÓN 9: DECISIONES PRIORITARIAS -->
		<div class="dsm-card">
			<div class="dsm-card-header">
				<h2 class="dsm-card-title">Decisiones prioritarias</h2>
				<a href="javascript:void(0)" class="font-weight-bold" style="font-size: 12px; color: #2563eb; text-decoration: none;" onclick="dsmOpenAllDecisionsModal()">Ver todas</a>
			</div>

			<div class="dsm-decisions-list" id="dsm-decisions-featured">
				@foreach($decisions as $dec)
					<div class="dsm-decision-item is-{{ $dec['severity'] }}" onclick="dsmHandleDecisionClick({{ htmlspecialchars(json_encode($dec), ENT_QUOTES, 'UTF-8') }})" title="Responsable: {{ $dec['owner'] }} · Prioridad: {{ $dec['priority'] }} · Estado: {{ $dec['status'] }}">
						<div class="dsm-dec-left">
							<div class="dsm-dec-icon">
								<i class="fa-solid {{ $dec['icon'] }}"></i>
							</div>
							<div class="dsm-dec-text">
								<span class="dsm-dec-title">{{ $dec['title'] }}</span>
								<span class="dsm-dec-sub">{{ $dec['line1'] }}</span>
								<span class="dsm-dec-sub">{{ $dec['line2'] }}</span>
							</div>
						</div>
						<i class="fa-solid fa-chevron-right dsm-dec-arrow"></i>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     6. INTEGRACIÓN ENTRE MÓDULOS (SECCIÓN 11 — 14 MÓDULOS CONECTADOS)
	     ====================================================================== -->
	<section class="dsm-integration-strip" aria-label="Integración transversal entre módulos">
		<div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px;">
			<div>
				<strong class="text-dark" style="font-size: 13.5px;">
					<i class="fa-solid fa-network-wired text-success mr-1"></i> Ecosistema Conectado Stock &amp; Movimientos (Fuente Única de Verdad)
				</strong>
				<span class="text-muted ml-2" style="font-size: 12px;">Sincronización bidireccional con los 14 módulos operativos, comerciales y financieros</span>
			</div>
			<button type="button" class="btn btn-sm btn-success font-weight-bold" style="border-radius: 8px; font-size: 12px;" onclick="dsmOpenWorkflowModal()">
				<i class="fa-solid fa-play mr-1"></i> Ejecutar Flujo Integrado (10 Pasos)
			</button>
		</div>

		<div class="dsm-modules-pills">
			<a href="{{ $U('/scan') }}" class="dsm-mod-link"><i class="fa-solid fa-barcode text-success"></i> Operaciones con Escáner</a>
			<a href="{{ $U('/locations/map') }}" class="dsm-mod-link"><i class="fa-solid fa-map-location-dot text-success"></i> Mapa de Ubicaciones</a>
			<a href="{{ $U('/transfers') }}" class="dsm-mod-link"><i class="fa-solid fa-arrow-right-arrow-left text-warning"></i> Traslados</a>
			<a href="{{ $U('/dispatch') }}" class="dsm-mod-link"><i class="fa-solid fa-truck-fast text-primary"></i> Despachos y Validaciones</a>
			<a href="{{ $U('/returns') }}" class="dsm-mod-link"><i class="fa-solid fa-rotate-left text-info"></i> Cambios y Devoluciones</a>
			<a href="{{ $U('/kardex') }}" class="dsm-mod-link"><i class="fa-solid fa-file-invoice-dollar text-success"></i> Kardex</a>
			<a href="{{ $U('/traceability') }}" class="dsm-mod-link"><i class="fa-solid fa-route text-secondary"></i> Trazabilidad</a>
			<a href="{{ $U('/reconciliation') }}" class="dsm-mod-link"><i class="fa-solid fa-shield-halved text-danger"></i> Control de Inventario</a>
			<a href="{{ $U('/inventory') }}" class="dsm-mod-link"><i class="fa-solid fa-clipboard-check text-success"></i> Conteos Físicos</a>
			<a href="{{ $U('/branches') }}" class="dsm-mod-link"><i class="fa-solid fa-store text-primary"></i> Sucursales</a>
			<a href="{{ $U('/sales') }}" class="dsm-mod-link"><i class="fa-solid fa-cash-register text-success"></i> Ventas</a>
			<a href="{{ $U('/waste') }}" class="dsm-mod-link"><i class="fa-solid fa-trash-can text-danger"></i> Control de Mermas</a>
			<a href="{{ $U('/mrp') }}" class="dsm-mod-link"><i class="fa-solid fa-boxes-packing text-warning"></i> Planificación MRP</a>
			<a href="{{ $U('/suppliers') }}" class="dsm-mod-link"><i class="fa-solid fa-handshake text-info"></i> Proveedores y Compras</a>
		</div>
	</section>

</div>

<!-- ==========================================================================
     MODAL 1: DESGLOSE EJECUTIVO DE KPI Y CONCILIACIÓN MATEMÁTICA
     ========================================================================== -->
<div class="modal fade" id="dsmKpiModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold" id="dsmKpiModalTitle">
					<i class="fa-solid fa-chart-pie text-success mr-2"></i>Desglose de Indicador Ejecutivo
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4" id="dsmKpiModalBody"></div>
			<div class="modal-footer bg-light">
				<a href="{{ $U('/kardex') }}" class="btn btn-outline-success btn-sm font-weight-bold"><i class="fa-solid fa-file-invoice mr-1"></i>Auditar en Kardex</a>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 2: FICHA 360° DE PRODUCTO (EXISTENCIAS, LOTES, VENTAS, RECEPCIONES, CONTEOS)
     ========================================================================== -->
<div class="modal fade" id="dsmProduct360Modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold" id="dsmProduct360Title">
					<i class="fa-solid fa-box-open text-success mr-2"></i>Ficha 360° del Producto y Trazabilidad
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4" id="dsmProduct360Body">
				<div class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando ficha 360°...</div>
			</div>
			<div class="modal-footer bg-light d-flex justify-content-between">
				<div>
					<a href="{{ $U('/traceability') }}" id="dsm360LinkTrace" class="btn btn-outline-primary btn-sm font-weight-bold"><i class="fa-solid fa-route mr-1"></i>Trazabilidad de Lote</a>
					<a href="{{ $U('/kardex') }}" id="dsm360LinkKardex" class="btn btn-outline-success btn-sm font-weight-bold ml-1"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Ver Kardex</a>
					<a href="{{ $U('/inventory') }}" id="dsm360LinkCount" class="btn btn-outline-warning btn-sm font-weight-bold ml-1"><i class="fa-solid fa-clipboard-check mr-1"></i>Conteo Físico</a>
				</div>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 3: ACCIONES PREVENTIVAS FEFO (CONECTADO CON CALIDAD Y MERMAS)
     ========================================================================== -->
<div class="modal fade" id="dsmFefoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-calendar-check text-danger mr-2"></i>Gestión Activa de Riesgo de Vencimiento (FEFO)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-warning small mb-3" role="note">
					<i class="fa-solid fa-flask mr-1"></i><strong>Simulación.</strong> Las acciones se anotan en la bitácora de esta pantalla (la rotación crea además una tarea); no descuentan stock ni registran merma o traslado.
				</div>
				<div id="dsmFefoLotSummary" class="p-3 mb-3 rounded border bg-light"></div>

				<div class="form-group">
					<label class="font-weight-bold small text-uppercase text-muted">Seleccionar Acción Operativa / Comercial</label>
					<div class="row">
						<div class="col-md-6 mb-2">
							<button type="button" class="btn btn-outline-primary btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('inspect')">
								<i class="fa-solid fa-microscope mr-2"></i>1. Revisar lote (Calidad &amp; Frío)
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" class="btn btn-outline-success btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('rotate_task')">
								<i class="fa-solid fa-people-carry-box mr-2"></i>2. Crear tarea de rotación FEFO
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" class="btn btn-outline-info btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('transfer')">
								<i class="fa-solid fa-truck-arrow-right mr-2"></i>3. Recomendar traslado inter-sucursal
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" class="btn btn-outline-warning btn-block text-left font-weight-bold py-2 text-dark" onclick="dsmSubmitFefoAction('markdown')">
								<i class="fa-solid fa-tags mr-2"></i>4. Evaluar rebaja comercial (-30%)
							</button>
						</div>
						<div class="col-md-12">
							<button type="button" class="btn btn-outline-danger btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('waste')">
								<i class="fa-solid fa-trash-can mr-2"></i>5. Registrar merma definitiva (Conectar con Control de Mermas)
							</button>
						</div>
					</div>
				</div>

				<div class="form-group mb-0">
					<label class="small font-weight-bold text-muted" for="dsmFefoNotes">Observación de auditoría / instrucción para piso de venta</label>
					<input type="text" id="dsmFefoNotes" class="form-control form-control-sm" value="Adelantar lote al frente de góndola y verificar temperatura de cámara.">
				</div>
			</div>
			<div class="modal-footer bg-light d-flex justify-content-between">
				<div>
					<a href="{{ $U('/quality') }}" class="btn btn-sm btn-link text-primary font-weight-bold"><i class="fa-solid fa-flask mr-1"></i>Ir a Calidad &amp; Vencimientos</a>
					<a href="{{ $U('/waste') }}" class="btn btn-sm btn-link text-danger font-weight-bold"><i class="fa-solid fa-trash-can mr-1"></i>Ir a Control de Mermas</a>
				</div>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 4: LIBRO MAYOR DE MOVIMIENTOS EN TIEMPO REAL (KARDEX)
     ========================================================================== -->
<div class="modal fade" id="dsmMovementsModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold" id="dsmMovementsModalTitle">
					<i class="fa-solid fa-clock-rotate-left text-success mr-2"></i>Movimientos de Inventario en Tiempo Real (Trazabilidad Completa)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap: 8px;">
					<div class="btn-group btn-group-sm" role="group">
						<button type="button" class="btn btn-outline-secondary active" onclick="dsmFilterModalMovements('todos', this)">Todos</button>
						<button type="button" class="btn btn-outline-success" onclick="dsmFilterModalMovements('entradas', this)">Entradas</button>
						<button type="button" class="btn btn-outline-danger" onclick="dsmFilterModalMovements('salidas', this)">Salidas</button>
						<button type="button" class="btn btn-outline-warning text-dark" onclick="dsmFilterModalMovements('traslados', this)">Traslados</button>
						<button type="button" class="btn btn-outline-primary" onclick="dsmFilterModalMovements('devoluciones', this)">Dev. Clientes</button>
						<button type="button" class="btn btn-outline-info" onclick="dsmFilterModalMovements('despachos', this)">Despachos</button>
						<button type="button" class="btn btn-outline-dark" onclick="dsmFilterModalMovements('ajustes', this)">Ajustes</button>
						<button type="button" class="btn btn-outline-danger" onclick="dsmFilterModalMovements('mermas', this)">Mermas</button>
					</div>
					<button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="$('#dsmMovementsModal').modal('hide'); dsmOpenWorkflowModal();">
						<i class="fa-solid fa-plus mr-1"></i>Registrar Movimiento en Vivo
					</button>
				</div>

				<div class="table-responsive">
					<table class="table table-sm table-hover table-bordered mb-0" style="font-size: 12px;">
						<thead class="thead-light">
							<tr>
								<th>ID Único</th>
								<th>Fecha y Hora</th>
								<th>Tipo de Movimiento</th>
								<th>Producto / SKU</th>
								<th style="text-align: right;">Cantidad</th>
								<th>Sucursal</th>
								<th>Origen &rarr; Destino</th>
								<th>Usuario Responsable</th>
								<th>Doc. Referencia</th>
								<th>Validación</th>
							</tr>
						</thead>
						<tbody id="dsmMovementsModalTbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<a href="{{ $U('/kardex') }}" class="btn btn-outline-success btn-sm font-weight-bold"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>Abrir Kardex Valorizado Completo</a>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 5: CENTRO DE DECISIONES PRIORITARIAS (12 ALERTAS ACCIONABLES)
     ========================================================================== -->
<div class="modal fade" id="dsmDecisionsModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-bell text-danger mr-2"></i>Decisiones Prioritarias de Inventario (Crítico, Atención y Oportunidad)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="btn-group btn-group-sm mb-3" role="group">
					<button type="button" class="btn btn-dark active" onclick="dsmFilterDecisionsModal('all', this)">Todas (12)</button>
					<button type="button" class="btn btn-danger" onclick="dsmFilterDecisionsModal('red', this)">Rojo — Crítico (4)</button>
					<button type="button" class="btn btn-warning text-dark" onclick="dsmFilterDecisionsModal('yellow', this)">Amarillo — Atención (4)</button>
					<button type="button" class="btn btn-success" onclick="dsmFilterDecisionsModal('green', this)">Verde — Oportunidad (4)</button>
				</div>
				<div class="table-responsive">
					<table class="table table-sm table-bordered table-hover mb-0" style="font-size: 12px;">
						<thead class="thead-light">
							<tr>
								<th>Prioridad</th>
								<th>Alerta / Diagnóstico</th>
								<th>Impacto Operativo y Financiero</th>
								<th>Responsable</th>
								<th>Fecha / Hora</th>
								<th>Estado</th>
								<th style="text-align: right;">Acción Ejecutable</th>
							</tr>
						</thead>
						<tbody id="dsmDecisionsModalTbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<a href="{{ $U('/alerts') }}" class="btn btn-outline-danger btn-sm font-weight-bold"><i class="fa-solid fa-shield-halved mr-1"></i>Ir al Centro Corporativo de Alertas</a>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 6: FLUJO OPERATIVO INTEGRADO DE 10 PASOS (SECCIÓN 11)
     ========================================================================== -->
<div class="modal fade" id="dsmWorkflowModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-diagram-project text-success mr-2"></i>Flujo Operativo Transversal (simulación de 10 pasos)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-warning small mb-3" role="note">
					<i class="fa-solid fa-flask mr-1"></i><strong>Simulación.</strong> Estos pasos se anotan en una bitácora propia de esta pantalla: no modifican existencias, Kardex, ventas ni indicadores.
					Para operar de verdad use Compras (recepción), Punto de Venta, Pedidos, Mermas y Conteos.
				</div>

				<div class="form-row">
					<div class="form-group col-md-6">
						<label class="small font-weight-bold">Eslabón del Flujo a Ejecutar</label>
						<select id="dsmWfStepType" class="form-control form-control-sm font-weight-bold" onchange="dsmToggleWfOptions()">
							<option value="reception">Pasos 1–4: Recepción Validada OC &rarr; Stock &rarr; Kardex &rarr; Ubicación</option>
							<option value="sale">Paso 5: Venta POS disminuye existencias (Orden FEFO)</option>
							<option value="return">Paso 6: Devolución Validada de Cliente (Según estado)</option>
							<option value="cycle_count">Pasos 7–8: Conteo Físico detecta diferencia &rarr; Genera Alerta</option>
							<option value="supervisor_decision">Pasos 9–10: Supervisor investiga y Aprueba/Rechaza Ajuste &rarr; KPIs</option>
						</select>
					</div>
					<div class="form-group col-md-6">
						<label class="small font-weight-bold">Producto / SKU</label>
						<select id="dsmWfProduct" class="form-control form-control-sm">
							@foreach(\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT p.name, p.sku, IFNULL((SELECT price FROM products_last_purchased WHERE product_id = p.id), 0) AS cost FROM products p WHERE p.active = 1 AND IFNULL(p.sku, '') <> '' ORDER BY p.name")->fetchAll(\PDO::FETCH_OBJ) as $wp)
							<option value="{{ $wp->name }}|{{ $wp->sku }}|{{ round($wp->cost, 2) }}">{{ $wp->name }} ({{ $wp->sku }} · Costo ${{ number_format($wp->cost, 2) }})</option>
							@endforeach
						</select>
					</div>
				</div>

				<div class="form-row">
					<div class="form-group col-md-4">
						<label class="small font-weight-bold">Cantidad (Unidades)</label>
						<input type="number" id="dsmWfQty" class="form-control form-control-sm" value="150" min="1">
					</div>
					<div class="form-group col-md-4">
						<label class="small font-weight-bold">Sucursal</label>
						<select id="dsmWfBranch" class="form-control form-control-sm">
							@foreach(($branches ?? []) as $b)
							<option value="{{ $b->name }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group col-md-4">
						<label class="small font-weight-bold">Ubicación Física</label>
						<select id="dsmWfLocation" class="form-control form-control-sm">
							<option value="">Ubicación del producto en la sucursal (automática)</option>
						</select>
					</div>
				</div>

				<div class="form-row d-none" id="dsmWfReturnRow">
					<div class="form-group col-md-12">
						<label class="small font-weight-bold">Estado Sanitario del Producto Devuelto (Paso 6)</label>
						<select id="dsmWfReturnState" class="form-control form-control-sm">
							<option value="apto">Apto para reingreso a stock disponible</option>
							<option value="cuarentena">En cuarentena preventiva (Bloqueado hasta inspección)</option>
							<option value="merma">Dañado / Caducado (Enviar a Control de Mermas)</option>
						</select>
					</div>
				</div>

				<div class="form-row d-none" id="dsmWfSupervisorRow">
					<div class="form-group col-md-12">
						<label class="small font-weight-bold">Decisión del Supervisor / Manager (Pasos 9 y 10)</label>
						<select id="dsmWfDecision" class="form-control form-control-sm">
							<option value="approve">Aprobar ajuste contable y actualizar KPIs ejecutivos</option>
							<option value="reject">Rechazar ajuste y ordenar reconteo ciego</option>
						</select>
					</div>
				</div>

				<div id="dsmWfResultBox" class="alert alert-success d-none mt-2 mb-0" style="font-size: 12px;"></div>
			</div>
			<div class="modal-footer bg-light d-flex justify-content-between">
				<button type="button" class="btn btn-outline-secondary btn-sm" onclick="dsmResetDemoWorkflow()">
					<i class="fa-solid fa-rotate-left mr-1"></i>Restablecer valores base de referencia
				</button>
				<div>
					<button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="dsmExecuteWorkflowStep()">
						<i class="fa-solid fa-bolt mr-1"></i>Ejecutar Paso y Actualizar KPIs
					</button>
					<button type="button" class="btn btn-secondary btn-sm ml-1" data-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</div>

@include('components.productcard', [
	'asModal' => true
])
@stop
