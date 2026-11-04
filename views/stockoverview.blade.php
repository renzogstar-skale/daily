@php require_frontend_packages(['datatables', 'chartjs', 'animatecss']); @endphp

@extends('layout.default')

@section('title', 'Stock & Movimientos')

@push('pageStyles')
<link href="{{ $U('/css/daily_stock.css?v=', true) }}{{ daily_asset_version('/css/daily_stock.css') }}" rel="stylesheet">
@endpush

@push('pageScripts')
<script>
	window.DAILY_STOCK_EXEC_INITIAL = {!! json_encode($stockExec ?? [], JSON_HEX_TAG | JSON_HEX_AMP) !!};
</script>
<script src="{{ $U('/viewjs/purchase.js?v=', true) }}{{ $version }}"></script>
@endpush

@php
$se = $stockExec ?? \Grocy\Services\StockExecutiveOverviewService::GetInstance()->GetExecutiveData();
$kpis = $se['kpis'] ?? [];
$donut = $se['donut'] ?? [];
$topProds = $se['top_products'] ?? [];
$expProds = $se['expiring_products'] ?? [];
$branchesTable = $se['branches_table'] ?? [];
$movements = $se['movements'] ?? [];
$decisions = $se['priority_decisions']['featured'] ?? [];
$allDecisions = $se['priority_decisions']['all'] ?? [];
$filters = $se['filters'] ?? [];
$meta = $se['meta'] ?? [];
@endphp

@section('content')
@include('components.loss_strip')
<div class="dsm-exec-container" id="dsm-exec-app">

	<!-- ======================================================================
	     1. ENCABEZADO EJECUTIVO Y BARRA DE FILTROS GLOBALES (SECCIÓN 10)
	     ====================================================================== -->
	<header class="dsm-header">
		<div class="dsm-title-block">
			<div class="dsm-title-row">
				<h1 class="dsm-title">Stock &amp; Movimientos</h1>
				<span class="badge badge-light border text-muted font-weight-bold" style="font-size: 10.5px;" title="{{ $meta['demo_notice'] ?? '' }}">Rol: {{ ucfirst($meta['role'] ?? 'Manager') }}</span>
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
						@foreach(\Grocy\Services\DailyMetricsService::DEPARTMENTS as $dKey => $dDef)
							<option value="{{ $dKey }}" @if(($filters['department'] ?? 'todos') === $dKey) selected @endif>{{ $dDef[0] }}</option>
						@endforeach
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
					@foreach($suppliers ?? [] as $sup)
						<option value="{{ $sup->id }}">{{ $sup->name }}</option>
					@endforeach
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
					@foreach($locations ?? [] as $loc)
						<option value="{{ $loc->id }}">{{ $loc->name }}</option>
					@endforeach
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

	<!-- Contexto activo: departamento elegido, conciliación y aviso de fecha -->
	<div class="dsm-context-bar" id="dsm-context-bar" aria-live="polite"></div>

	<!-- ======================================================================
	     2. OCHO TARJETAS KPI (4 COLUMNAS × 2 FILAS - DISEÑO PREMIUM DAILY)
	     ====================================================================== -->
	<div class="dsm-kpi-grid" id="dsm-kpi-grid">
		@php
			$glowMap = [
				'inventory_value' => 'green',
				'stock_units' => 'green',
				'turnover' => 'amber',
				'coverage' => 'green',
				'below_min' => 'red',
				'expiring_soon' => 'amber',
				'out_of_stock' => 'red',
				'risk_value' => 'green'
			];
		@endphp
		@foreach(['inventory_value', 'stock_units', 'turnover', 'coverage', 'below_min', 'expiring_soon', 'out_of_stock', 'risk_value'] as $kKey)
			@php $k = $kpis[$kKey]; @endphp
			<div class="dsm-kpi-card is-glow-{{ $glowMap[$kKey] ?? 'green' }}" id="dsm-card-{{ $kKey }}" data-kpi="{{ $kKey }}" role="button" tabindex="0" aria-label="{{ $k['label'] }}: ver detalle" title="{{ $k['formula'] }}">
				<div class="dsm-kpi-top">
					<div class="dsm-kpi-icon is-{{ $k['state'] }}" id="dsm-icon-{{ $kKey }}">
						<i class="fa-solid {{ $k['icon'] }}"></i>
					</div>
					<div class="dsm-kpi-info">
						<div class="dsm-kpi-header-row">
							<span class="dsm-kpi-label">{{ $k['label'] }}</span>
							<button type="button" class="dsm-kpi-info-btn" data-kpi="{{ $kKey }}" title="Información y fórmula de {{ $k['label'] }}" aria-label="Información">
								<i class="fa-solid fa-circle-info"></i>
							</button>
						</div>
						<div class="dsm-kpi-val-row">
							<span class="dsm-kpi-value" id="dsm-val-{{ $kKey }}">{{ $k['value'] }}</span>
							<span class="dsm-kpi-delta {{ $k['delta_is_good'] ? 'is-good' : 'is-bad' }}" id="dsm-delta-{{ $kKey }}">{{ $k['delta'] }}</span>
						</div>
						<span class="dsm-kpi-sub" id="dsm-sub-{{ $kKey }}">{{ $k['subtext'] }}</span>
					</div>
				</div>
				<div class="dsm-kpi-content" id="dsm-foot-{{ $kKey }}"></div>
			</div>
		@endforeach
	</div>

	<!-- ======================================================================
	     3. TRES PANELES: EVOLUCIÓN · PRODUCTOS EN ALERTA · MOVIMIENTOS RECIENTES
	     ====================================================================== -->
	<div class="dsm-row-panels">
		<!-- 1. EVOLUCIÓN DEL INVENTARIO -->
		<div class="dsm-card dsm-card-pro" id="dsm-panel-evolution">
			<div class="dsm-evo-header">
				<div class="dsm-evo-header-left">
					<div class="dsm-evo-header-icon" title="Módulo de Evolución del Inventario">
						<i class="fa-solid fa-boxes-stacked"></i>
					</div>
					<div>
						<h2 class="dsm-evo-header-title">Evolución del Inventario</h2>
						<p class="dsm-evo-header-sub">Valor, unidades, rotación y cobertura</p>
					</div>
				</div>
				<div class="dropdown">
					<button type="button" class="dsm-evo-export-btn dropdown-toggle" id="dsm-evo-export-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Opciones de exportación">
						<i class="fa-solid fa-download mr-1"></i> Exportar
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow border-0" aria-labelledby="dsm-evo-export-menu" style="border-radius: 12px; font-size: 12px; min-width: 190px;">
						<a class="dropdown-item py-2 font-weight-bold" href="javascript:void(0)" onclick="dsmExportEvolution('excel')"><i class="fa-regular fa-file-excel text-success mr-2"></i>Exportar a Excel (.xlsx)</a>
						<a class="dropdown-item py-2 font-weight-bold" href="javascript:void(0)" onclick="dsmExportEvolution('csv')"><i class="fa-solid fa-file-csv text-primary mr-2"></i>Exportar a CSV (.csv)</a>
						<a class="dropdown-item py-2 font-weight-bold" href="javascript:void(0)" onclick="dsmExportEvolution('pdf')"><i class="fa-regular fa-file-pdf text-danger mr-2"></i>Exportar a PDF (.pdf)</a>
					</div>
				</div>
			</div>

			<div class="dsm-evo-body">
				<!-- 4 Pestañas / Píldoras de Métrica -->
				<div class="dsm-evo-metric-pills" role="tablist" aria-label="Métrica de evolución" id="dsm-evo-tabs">
					<button type="button" role="tab" class="dsm-evo-metric-pill active" data-metric="value">
						<i class="fa-solid fa-chart-simple mr-1"></i> Valor
					</button>
					<button type="button" role="tab" class="dsm-evo-metric-pill" data-metric="units">
						<i class="fa-solid fa-cube mr-1"></i> Unidades
					</button>
					<button type="button" role="tab" class="dsm-evo-metric-pill" data-metric="rotation">
						<i class="fa-solid fa-arrows-rotate mr-1"></i> Rotación
					</button>
					<button type="button" role="tab" class="dsm-evo-metric-pill" data-metric="coverage">
						<i class="fa-regular fa-calendar-days mr-1"></i> Cobertura
					</button>
				</div>

				<!-- Controles de Período y Comparación -->
				<div class="dsm-evo-controls-row">
					<div class="dsm-period-group" role="group" aria-label="Período">
						@foreach(['day' => 'Día', 'week' => 'Sem', 'month' => 'Mes', 'quarter' => 'Trim', 'year' => 'Año'] as $pKey => $pLabel)
							<button type="button" class="dsm-period-btn {{ ($filters['period'] ?? 'month') === $pKey ? 'active' : '' }}" data-period="{{ $pKey }}">{{ $pLabel }}</button>
						@endforeach
					</div>
					<div class="dsm-compare-wrap">
						<i class="fa-solid fa-chart-column dsm-compare-icon"></i>
						<select id="dsm-evo-compare" class="dsm-compare-select" aria-label="Comparación">
							<option value="prev" selected>vs. período anterior</option>
							<option value="none">Sin comparar</option>
							<option value="branches">Entre sucursales</option>
						</select>
						<i class="fa-solid fa-chevron-down dsm-compare-arrow"></i>
					</div>
				</div>

				<!-- Barra Dinámica de Indicadores Clave -->
				<div class="dsm-evo-kpi-summary" id="dsm-evo-summary">
					<div class="dsm-evo-kpi-col-main">
						<div class="dsm-evo-kpi-val" id="dsm-evo-kpi-val">$18,817</div>
						<div class="dsm-evo-kpi-lbl" id="dsm-evo-kpi-lbl">Valor del inventario</div>
					</div>
					<div class="dsm-evo-kpi-col-delta">
						<div class="dsm-evo-delta-tag is-up" id="dsm-evo-delta-tag">
							<i class="fa-solid fa-chevron-up"></i> <span id="dsm-evo-delta-pct">+6.3%</span>
						</div>
						<div class="dsm-evo-delta-lbl" id="dsm-evo-delta-lbl">vs. período anterior</div>
					</div>
					<div class="dsm-evo-kpi-col-sec">
						<div class="dsm-evo-sec-val" id="dsm-evo-sec-units">128,432</div>
						<div class="dsm-evo-sec-lbl">Unidades</div>
					</div>
					<div class="dsm-evo-kpi-col-sec">
						<div class="dsm-evo-sec-val" id="dsm-evo-sec-rot">21.4</div>
						<div class="dsm-evo-sec-lbl">Rotación</div>
					</div>
				</div>

				<!-- Canvas del Gráfico -->
				<div class="dsm-evo-canvas-wrap">
					<canvas id="dsmEvolutionChart" aria-label="Gráfico de evolución del inventario" role="img"></canvas>
				</div>
			</div>
		</div>

		<!-- 2. TOP PRODUCTOS EN ALERTA -->
		<div class="dsm-card dsm-card-pro" id="dsm-panel-alerts">
			<div class="dsm-card-header-clean">
				<div class="dsm-card-header-left">
					<div class="dsm-header-icon-circle is-red">
						<i class="fa-regular fa-bell"></i>
					</div>
					<div>
						<h2 class="dsm-clean-title">Top productos en alerta</h2>
						<p class="dsm-clean-sub">Productos que requieren acción inmediata</p>
					</div>
				</div>
				<span class="dsm-showing-count" id="dsm-alert-note">Mostrando 30 de 128</span>
			</div>

			<!-- 3 Tarjetas Superiores de Alertas Operativas -->
			<div class="dsm-alert-kpis-grid">
				<div class="dsm-alert-kpi-box is-green" data-tab-target="below_min" role="button" tabindex="0" title="Ver productos bajo mínimo">
					<div class="dsm-alert-kpi-icon"><i class="fa-solid fa-arrow-down"></i></div>
					<div class="dsm-alert-kpi-info">
						<div class="dsm-alert-kpi-lbl">Bajo mínimo</div>
						<div class="dsm-alert-kpi-num" id="dsm-alert-kpi-min">128</div>
					</div>
				</div>
				<div class="dsm-alert-kpi-box is-yellow" data-tab-target="expiring" role="button" tabindex="0" title="Ver productos próximos a vencer">
					<div class="dsm-alert-kpi-icon"><i class="fa-regular fa-calendar-days"></i></div>
					<div class="dsm-alert-kpi-info">
						<div class="dsm-alert-kpi-lbl">Próximos a vencer</div>
						<div class="dsm-alert-kpi-num" id="dsm-alert-kpi-exp">159</div>
					</div>
				</div>
				<div class="dsm-alert-kpi-box is-red" data-tab-target="out_of_stock" role="button" tabindex="0" title="Ver productos agotados">
					<div class="dsm-alert-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
					<div class="dsm-alert-kpi-info">
						<div class="dsm-alert-kpi-lbl">Agotados</div>
						<div class="dsm-alert-kpi-num" id="dsm-alert-kpi-out">197</div>
					</div>
				</div>
			</div>

			<!-- Pestañas de Alertas -->
			<div class="dsm-alert-pills-row" role="tablist" aria-label="Tipo de alerta" id="dsm-alert-tabs"></div>

			<!-- Tabla de Alertas -->
			<div class="dsm-table-wrap">
				<table class="dsm-clean-table dsm-table-alerts-pro">
					<thead>
						<tr>
							<th>Producto</th>
							<th>UPC</th>
							<th>Departamento</th>
							<th class="text-center">Stock</th>
							<th class="text-center">Mínimo</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody id="dsm-alert-tbody"></tbody>
				</table>
			</div>

			<!-- Pie: Botón Ver lista completa -->
			<div class="dsm-card-footer-center">
				<button type="button" class="dsm-btn-full-list" onclick="dsmOpenAllAlertsModal()">
					Ver lista completa (<span id="dsm-alert-total-btn">128</span>) <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
				</button>
			</div>
		</div>

		<!-- 3. MOVIMIENTOS RECIENTES -->
		<div class="dsm-card dsm-card-pro" id="dsm-panel-moves">
			<div class="dsm-card-header-clean">
				<div class="dsm-card-header-left">
					<div class="dsm-header-icon-circle is-green">
						<i class="fa-solid fa-arrow-right-arrow-left"></i>
					</div>
					<div>
						<h2 class="dsm-clean-title">Movimientos recientes</h2>
						<p class="dsm-clean-sub">Últimas transacciones de inventario</p>
					</div>
				</div>
				<a href="javascript:void(0)" class="dsm-btn-ver-todos" onclick="dsmOpenMovementsModal('todos')">
					Ver todos <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>

			<!-- Pestañas de Movimientos -->
			<div class="dsm-moves-pills-row" role="tablist" aria-label="Tipo de movimiento" id="dsm-move-tabs">
				<button type="button" role="tab" class="dsm-move-pill active" data-tab="todos">Todos</button>
				<button type="button" role="tab" class="dsm-move-pill" data-tab="entradas"><i class="fa-solid fa-boxes-packing mr-1"></i> Recepciones</button>
				<button type="button" role="tab" class="dsm-move-pill" data-tab="ventas"><i class="fa-solid fa-cart-shopping mr-1"></i> Ventas</button>
				<button type="button" role="tab" class="dsm-move-pill" data-tab="traslados"><i class="fa-solid fa-right-left mr-1"></i> Traslados</button>
				<button type="button" role="tab" class="dsm-move-pill" data-tab="devoluciones"><i class="fa-solid fa-rotate-left mr-1"></i> Devoluciones</button>
				<button type="button" role="tab" class="dsm-move-pill" data-tab="ajustes"><i class="fa-solid fa-sliders mr-1"></i> Ajustes</button>
			</div>

			<!-- Tabla de Movimientos -->
			<div class="dsm-table-wrap dsm-moves-table-wrap">
				<table class="dsm-clean-table dsm-table-moves-pro">
					<thead>
						<tr>
							<th>Fecha y hora</th>
							<th>Tipo</th>
							<th>Producto</th>
							<th class="text-right">Cantidad</th>
						</tr>
					</thead>
					<tbody id="dsm-move-tbody"></tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     4. DISTRIBUCIÓN DE INVENTARIO POR DEPARTAMENTO
	     ====================================================================== -->
	<section class="dsm-dept-super-section" id="dsm-section-dept" aria-labelledby="dsm-dept-title">
		<div class="d-flex align-items-center justify-content-between mb-2">
			<h2 class="sr-only" id="dsm-dept-title">Distribución de Inventario por Departamento</h2>
			<button type="button" class="dsm-link-btn d-none ml-auto" id="dsm-dept-clear"><i class="fa-solid fa-filter-circle-xmark"></i> Limpiar departamento</button>
		</div>

		<!-- NIVEL SUPERIOR: 6 INDICADORES KPI -->
		<div class="dsm-dept-kpi-grid">
			<!-- KPI 1: Valor total del inventario -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-green">
					<i class="fa-solid fa-coins"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-val">{{ $donut['center_value'] ?? '$18,282' }}</div>
					<div class="dsm-dept-kpi-title">Valor total del inventario</div>
					<div class="dsm-dept-kpi-badge is-up"><i class="fa-solid fa-arrow-up"></i> +4.8% <span class="dsm-kpi-note font-weight-normal text-muted" style="font-size: 11px;">vs. período anterior</span></div>
				</div>
			</div>
			<!-- KPI 2: Unidades totales -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-green">
					<i class="fa-solid fa-box"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-units">{{ number_format($donut['center_numeric_units'] ?? 1805, 0) }}</div>
					<div class="dsm-dept-kpi-title">Unidades totales</div>
					<div class="dsm-dept-kpi-badge is-up"><i class="fa-solid fa-arrow-up"></i> +2.9%</div>
				</div>
			</div>
			<!-- KPI 3: Rotación promedio -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-amber">
					<i class="fa-solid fa-arrows-rotate"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-rot">1.52x</div>
					<div class="dsm-dept-kpi-title">Rotación promedio</div>
					<div class="dsm-dept-kpi-badge is-down"><i class="fa-solid fa-arrow-down"></i> -0.3</div>
				</div>
			</div>
			<!-- KPI 4: Cobertura promedio -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-green">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-cov">5.6 días</div>
					<div class="dsm-dept-kpi-title">Cobertura promedio</div>
					<div class="dsm-dept-kpi-badge is-up"><i class="fa-solid fa-arrow-up"></i> +0.6 días</div>
				</div>
			</div>
			<!-- KPI 5: Departamentos analizados -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-red">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-count">{{ count($donut['departments'] ?? []) ?: 8 }}</div>
					<div class="dsm-dept-kpi-title">Departamentos</div>
					<div class="dsm-dept-kpi-subtext" id="dsm-dept-kpi-count-sub">100% analizados</div>
				</div>
			</div>
			<!-- KPI 6: Con riesgo crítico -->
			<div class="dsm-dept-kpi-card">
				<div class="dsm-dept-kpi-icon is-red">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<div class="dsm-dept-kpi-content">
					<div class="dsm-dept-kpi-val" id="dsm-dept-kpi-crit">8</div>
					<div class="dsm-dept-kpi-title">Con riesgo crítico</div>
					<div class="dsm-dept-kpi-subtext" id="dsm-dept-kpi-crit-sub">100% de los departamentos</div>
				</div>
			</div>
		</div>

		<!-- NIVEL CENTRAL: ANÁLISIS DE DISTRIBUCIÓN (DONUT + TABLA) -->
		<div class="dsm-dept-level2-grid">
			<!-- Columna Izquierda: Gráfico de dona con leyenda integrada -->
			<div class="dsm-card dsm-card-clean dsm-donut-panel-card" style="padding: 20px; margin-bottom: 0;">
				<div class="dsm-donut-layout">
					<div class="dsm-donut-canvas-box">
						<canvas id="dsmDonutChart" role="img" aria-label="Participación del valor del inventario por departamento"></canvas>
						<div class="dsm-donut-center">
							<div class="dsm-donut-center-val" id="dsm-donut-center-val">{{ $donut['center_value'] ?? '$18,282' }}</div>
							<div class="dsm-donut-center-label">Valor total del inventario</div>
							<div class="dsm-donut-center-badge" id="dsm-donut-center-sub">{{ count($donut['departments'] ?? []) ?: 8 }} departamentos</div>
						</div>
					</div>
					<div class="dsm-donut-legend-col" id="dsm-donut-legend"></div>
				</div>
			</div>

			<!-- Columna Derecha: Tabla Comparativa por Departamento -->
			<div class="dsm-card dsm-card-clean dsm-table-panel-card" style="padding: 0; margin-bottom: 0; overflow: hidden;">
				<div class="dsm-table-scroll dsm-table-scroll--free" style="padding: 0; margin: 0;">
					<table class="dsm-exec-table dsm-dept-comp-table" id="dsm-rank-table">
						<thead>
							<tr>
								<th style="width: 32px; text-align: center;">#</th>
								<th>Departamento</th>
								<th class="text-right">Valor</th>
								<th style="width: 130px;">Participación <i class="fa-solid fa-arrows-up-down text-muted" style="font-size: 10px;"></i></th>
								<th class="text-right">Unidades</th>
								<th class="text-right">Rotación</th>
								<th class="text-right">Cobertura</th>
								<th class="text-center">Riesgo</th>
								<th class="text-center">Tendencia</th>
							</tr>
						</thead>
						<tbody id="dsm-rank-tbody"></tbody>
						<tfoot id="dsm-rank-tfoot">
							<tr class="dsm-dept-total-row">
								<td colspan="2" class="font-weight-bold" style="padding-left: 14px;">Total</td>
								<td class="text-right font-weight-bold" id="dsm-dept-total-val">{{ $donut['center_value'] ?? '$18,282' }}</td>
								<td class="font-weight-bold" id="dsm-dept-total-share">100.0%</td>
								<td class="text-right font-weight-bold" id="dsm-dept-total-units">{{ number_format($donut['center_numeric_units'] ?? 1805, 0) }}</td>
								<td class="text-right font-weight-bold" id="dsm-dept-total-rot">1.52x</td>
								<td class="text-right font-weight-bold" id="dsm-dept-total-cov">5.6 d</td>
								<td colspan="2"></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>

		<div class="dsm-unclassified d-none" id="dsm-unclassified"></div>

		<!-- Productos y categorías del departamento elegido -->
		<div class="dsm-dept-drilldown" id="dsm-dept-drilldown">
			<strong class="text-dark" id="dsm-drill-title"></strong>
			<div id="dsm-drill-content"></div>
		</div>

		<!-- NIVEL INFERIOR: RIESGOS POR DEPARTAMENTO -->
		<div class="dsm-dept-level3-header">
			<h3 class="dsm-dept-section-heading">
				<i class="fa-solid fa-chart-simple text-success"></i> Indicadores de riesgo por departamento
			</h3>
		</div>
		<div class="dsm-risk-grid-pro" id="dsm-risk-grid"></div>
	</section>

	<!-- ======================================================================
	     5. ANÁLISIS DE OPORTUNIDADES DE INVENTARIO (5 TARJETAS HORIZONTALES)
	     ====================================================================== -->
	<section class="dsm-opps-super-section" id="dsm-section-opps" aria-labelledby="dsm-opps-title">
		<h2 class="sr-only" id="dsm-opps-title">Análisis de oportunidades</h2>
		<div class="dsm-opps-grid" id="dsm-opps-grid"></div>
	</section>

	<!-- ======================================================================
	     4. FILA 3: MAPA DE CALOR + TOP 10 VALOR + RIESGO VENCIMIENTO
	     ====================================================================== -->
	<div class="dsm-row-three">
		<!-- SECCIÓN 4: MAPA DE CALOR DE EXISTENCIAS (UNIDADES) -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-heatmap" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="background: linear-gradient(135deg, #008744 0%, #00a651 100%); color: #ffffff; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div class="dsm-header-icon-clean" style="background: rgba(255,255,255,0.2); color: #ffffff; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px;">
						<i class="fa-solid fa-layer-group"></i>
					</div>
					<div>
						<h2 class="dsm-clean-title" id="dsm-hm-title" style="color: #ffffff; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Mapa de calor de existencias (unidades)</h2>
						<span class="dsm-clean-sub" style="color: rgba(255,255,255,0.85); font-size: 11px;">Nivel de stock por departamento y día</span>
					</div>
				</div>
				<button type="button" class="dsm-info-btn-white" onclick="dsmOpenHeatmapInfo()" title="Definición operativa e interpretación" style="background: transparent; border: 1.5px solid rgba(255,255,255,0.6); color: #ffffff; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
					<i class="fa-solid fa-info"></i>
				</button>
			</div>

			<div class="dsm-card-body" style="padding: 14px 16px;">
				<!-- Controles / Filtros de tarjeta -->
				<div class="dsm-fdd-row mb-2">
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-hm-metric-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Métrica del mapa de calor">
							<i class="fa-solid fa-layer-group text-success mr-1"></i>
							<span class="dsm-fdd-label">Unidades en stock</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-hm-period-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Período del mapa de calor">
							<i class="fa-regular fa-calendar text-muted mr-1"></i>
							<span class="dsm-fdd-label">Últimos 14 días</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-fdd-clear" data-widget="hm" hidden title="Restablecer filtros de esta tarjeta" aria-label="Restablecer filtros de esta tarjeta"><i class="fa-solid fa-rotate-left"></i></button>
				</div>
				<p class="dsm-hm-desc" id="dsm-hm-desc"></p>

				<!-- Grid del Mapa de Calor -->
				<div class="dsm-heatmap-wrap">
					<div class="dsm-heatmap-grid" id="dsm-heatmap-grid">
						<div class="dsm-widget-msg"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Calculando desde el kardex…</div>
					</div>
				</div>

				<!-- Barra Gauge / Indicador Mín - Máx -->
				<div class="d-flex align-items-center justify-content-between mt-3 mb-2" style="gap: 10px; background: #fafafa; border: 1px solid #f1f5f9; border-radius: 12px; padding: 7px 12px;">
					<div class="d-flex align-items-center font-weight-bold" style="font-size: 11px; color: #1e293b; gap: 5px;">
						<i class="fa-solid fa-box text-success"></i> <span id="dsm-hm-gauge-min">—</span>
					</div>
					<div class="dsm-hm-gauge" id="dsm-hm-gauge"></div>
					<div class="d-flex align-items-center font-weight-bold" style="font-size: 11px; color: #1e293b; gap: 5px;">
						<i class="fa-solid fa-box text-danger"></i> <span id="dsm-hm-gauge-max">—</span>
					</div>
				</div>
			</div>

			<!-- Resumen Inferior: Promedio, Variación, Días críticos -->
			<div class="dsm-card-kpi-footer" style="background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 12px 18px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
				<div>
					<div class="text-muted font-weight-bold" style="font-size: 10.5px; display: flex; align-items: center; justify-content: center; gap: 4px;">
						<i class="fa-solid fa-boxes-stacked text-success"></i> Promedio
					</div>
					<div class="font-weight-bold text-dark mt-1" id="dsm-hm-stat-avg" style="font-size: 13.5px;">—</div>
				</div>
				<div>
					<div class="text-muted font-weight-bold" style="font-size: 10.5px; display: flex; align-items: center; justify-content: center; gap: 4px;">
						<i class="fa-solid fa-arrow-trend-up text-success"></i> Variación
					</div>
					<div class="mt-1">
						<span class="badge badge-success font-weight-bold px-2 py-1" id="dsm-hm-stat-delta" style="font-size: 11px; background: #dcfce7; color: #00a651; border: 1px solid #86efac; border-radius: 6px;">
							—
						</span>
					</div>
				</div>
				<div>
					<div class="text-muted font-weight-bold" style="font-size: 10.5px; display: flex; align-items: center; justify-content: center; gap: 4px;">
						<i class="fa-solid fa-chart-simple text-danger"></i> Días críticos
					</div>
					<div class="mt-1">
						<span class="badge badge-danger font-weight-bold px-2 py-1" id="dsm-hm-stat-critical" style="font-size: 12px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px;">
							—
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- SECCIÓN 5: TOP 10 PRODUCTOS CON MAYOR VALOR EN INVENTARIO -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-top10" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div class="dsm-header-icon-clean" style="background: #fef3c7; color: #d97706; border: 1px solid #fde68a; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px;">
						<i class="fa-solid fa-trophy"></i>
					</div>
					<div>
						<h2 class="dsm-clean-title" id="dsm-top10-title" style="color: #0f172a; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Top 10 productos con mayor valor en inventario</h2>
						<span class="dsm-clean-sub" id="dsm-top10-sub" style="color: #64748b; font-size: 11px;">Productos que concentran el mayor valor del inventario</span>
					</div>
				</div>
				<button type="button" class="dsm-info-btn" onclick="dsmOpenTop10Info()" title="Explicación del ranking financiero" style="background: transparent; border: 1.5px solid #cbd5e1; color: #64748b; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
					<i class="fa-solid fa-info"></i>
				</button>
			</div>

			<div class="dsm-card-body" style="padding: 14px 16px;">
				<!-- Controles de la tabla -->
				<div class="dsm-fdd-row mb-3">
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-top10-sort-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Ordenar productos">
							<i class="fa-solid fa-coins text-success mr-1"></i>
							<span class="dsm-fdd-label">Mayor valor de inventario</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn dsm-top10-badge-pill" id="dsm-top10-limit-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Cantidad de resultados">
							<span class="dsm-fdd-label">Top 10</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-top10-dept-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Filtrar por departamento">
							<span class="dsm-fdd-label">Departamentos</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-fdd-clear" data-widget="top" hidden title="Restablecer filtros de esta tarjeta" aria-label="Restablecer filtros de esta tarjeta"><i class="fa-solid fa-rotate-left"></i></button>
				</div>

				<!-- Tabla Top 10 -->
				<div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
					<table class="dsm-exec-table" id="dsm-top10-table" style="width: 100%; border-collapse: separate; border-spacing: 0 4px; font-size: 12px;">
						<thead>
							<tr style="color: #64748b; font-size: 10.5px; text-transform: uppercase;">
								<th style="width: 20px; padding: 6px 4px;">#</th>
								<th style="padding: 6px 6px;">Producto</th>
								<th style="padding: 6px 6px;">Depto.</th>
								<th style="text-align: right; padding: 6px 6px;">Valor <i class="fa-solid fa-arrow-down" style="font-size: 9px;"></i></th>
								<th style="text-align: right; padding: 6px 6px;">Uds.</th>
								<th style="text-align: right; padding: 6px 6px;">% valor</th>
							</tr>
						</thead>
						<tbody id="dsm-top10-tbody">
							@foreach($topProds as $tp)
								<tr onclick="dsmOpenProduct360({{ $tp['product_id'] }})" title="SKU: {{ $tp['sku'] }} · UPC: {{ $tp['upc'] }} · Rotación: {{ $tp['rotation'] }}x · Cobertura: {{ $tp['coverage_days'] }}d · Estado: {{ $tp['status_label'] }} (Clic para abrir Ficha 360°)" style="cursor: pointer;">
									<td class="font-weight-bold text-muted">{{ $tp['rank'] }}</td>
									<td>
										<div class="d-flex align-items-center" style="gap: 8px;">
											<img src="{{ $U($tp['image']) }}" alt="{{ $tp['name'] }}" onerror="this.onerror=null; this.src='{{ $U('/img/control_center/prod_pan_molde.svg') }}';" style="width: 26px; height: 26px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">
											<span class="font-weight-bold text-dark text-truncate" style="max-width: 130px;">{{ $tp['name'] }}</span>
										</div>
									</td>
									<td class="text-muted">{{ $tp['department'] }}</td>
									<td style="text-align: right; font-weight: 800; color: #0f172a;">{{ $tp['formatted_value'] }}</td>
									<td style="text-align: right; font-weight: 600; color: #475569;">{{ $tp['formatted_units'] }}</td>
									<td style="text-align: right;">
										<div class="dsm-prog-wrap">
											<span class="font-weight-bold text-dark" style="font-size: 11px;">{{ number_format($tp['pct_of_total'] ?? 0, 1) }}%</span>
											<div class="dsm-prog-bar-bg">
												<div class="dsm-prog-bar-fill" style="width: {{ min(100, max(4, ($tp['pct_of_total'] ?? 0))) }}%;"></div>
											</div>
										</div>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			<!-- Resumen Inferior: Total Top 10, Participación, Ver detalle -->
			<div class="dsm-card-kpi-footer d-flex align-items-center justify-content-between" style="background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 12px 18px; font-size: 12px;">
				<div class="d-flex align-items-center font-weight-bold" style="color: #0f172a; gap: 6px;">
					<i class="fa-solid fa-coins text-success"></i> <span id="dsm-top10-foot-label">Total Top 10</span>: <span id="dsm-top10-foot-total" class="font-weight-bold" style="color: #008744;">{{ $stockExec['top10_stats']['formatted_total_value'] ?? '$0' }}</span>
				</div>
				<div class="d-flex align-items-center font-weight-bold" style="color: #0f172a; gap: 6px;">
					<i class="fa-regular fa-clock text-success"></i> Participación: <span id="dsm-top10-foot-share" class="font-weight-bold" style="color: #008744;">{{ $stockExec['top10_stats']['participation_pct'] ?? '0' }}%</span>
				</div>
				<a href="javascript:void(0)" onclick="dsmOpenTop10AllModal()" class="font-weight-bold text-success d-inline-flex align-items-center" style="gap: 4px; text-decoration: none;">
					Ver detalle <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
				</a>
			</div>
		</div>

		<!-- SECCIÓN 6: PRODUCTOS CON RIESGO DE VENCIMIENTO -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-fefo" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div class="dsm-header-icon-clean" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px;">
						<i class="fa-solid fa-triangle-exclamation"></i>
					</div>
					<div>
						<h2 class="dsm-clean-title" style="color: #0f172a; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Productos con riesgo de vencimiento</h2>
						<span class="dsm-clean-sub" id="dsm-fefo-sub" style="color: #64748b; font-size: 11px;">Productos que vencen en los próximos días</span>
					</div>
				</div>
				<button type="button" class="dsm-info-btn" onclick="dsmOpenFefoInfo()" title="Criterios y alertas de vencimiento FEFO" style="background: transparent; border: 1.5px solid #cbd5e1; color: #64748b; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
					<i class="fa-solid fa-info"></i>
				</button>
			</div>

			<div class="dsm-card-body" style="padding: 14px 16px;">
				<!-- Controles de la tabla FEFO -->
				<div class="dsm-fdd-row mb-3">
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-fefo-days-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Ventana de vencimiento">
							<i class="fa-regular fa-calendar-days text-success mr-1"></i>
							<span class="dsm-fdd-label">Próximos 30 días</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-subcard-select-wrap dsm-fdd-btn " id="dsm-fefo-dept-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Filtrar por departamento">
							<i class="fa-solid fa-filter text-muted mr-1"></i>
							<span class="dsm-fdd-label">Todos los departamentos</span>
							<i class="fa-solid fa-chevron-down text-muted ml-1 dsm-fdd-caret"></i>
						</button>
						<button type="button" class="dsm-fdd-clear" data-widget="fefo" hidden title="Restablecer filtros de esta tarjeta" aria-label="Restablecer filtros de esta tarjeta"><i class="fa-solid fa-rotate-left"></i></button>
				</div>

				<!-- Tabla FEFO -->
				<div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
					<table class="dsm-exec-table" id="dsm-fefo-table" style="width: 100%; border-collapse: separate; border-spacing: 0 4px; font-size: 12px;">
						<thead>
							<tr style="color: #64748b; font-size: 10.5px; text-transform: uppercase;">
								<th style="width: 20px; padding: 6px 4px;">#</th>
								<th style="padding: 6px 6px;">Producto</th>
								<th style="text-align: center; padding: 6px 6px;">Días <i class="fa-solid fa-sort" style="font-size: 9px;"></i></th>
								<th style="text-align: right; padding: 6px 6px;">Stock</th>
								<th style="text-align: right; padding: 6px 6px;">Valor</th>
							</tr>
						</thead>
						<tbody id="dsm-fefo-tbody">
							@foreach($expProds as $ep)
								<tr onclick="dsmOpenFefoActionModal({{ htmlspecialchars(json_encode($ep), ENT_QUOTES, 'UTF-8') }})" title="Lote: {{ $ep['lot'] }} · Vence: {{ $ep['expiry_date'] }} · {{ $ep['branch_name'] }} ({{ $ep['location'] }}) · Estado FEFO: {{ $ep['fefo_state'] }} · Clic para ejecutar acción FEFO" style="cursor: pointer;">
									<td class="font-weight-bold text-muted">{{ $ep['rank'] }}</td>
									<td>
										<div class="d-flex align-items-center" style="gap: 8px;">
											<img src="{{ $U($ep['image']) }}" alt="{{ $ep['name'] }}" onerror="this.onerror=null; this.src='{{ $U('/img/control_center/prod_baguette.svg') }}';" style="width: 26px; height: 26px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">
											<span class="font-weight-bold text-dark text-truncate" style="max-width: 130px;">{{ $ep['name'] }}</span>
										</div>
									</td>
									<td style="text-align: center;">
										<span class="dsm-days-pill {{ $ep['days_left'] <= 7 ? 'is-danger' : ($ep['days_left'] <= 15 ? 'is-warning' : 'is-info') }}">{{ $ep['days_left'] }}</span>
									</td>
									<td style="text-align: right; font-weight: 600; color: #475569;">{{ $ep['stock'] }}</td>
									<td style="text-align: right; font-weight: 700; color: #0f172a;">{{ $ep['formatted_value'] }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			<!-- Resumen Inferior: Total en riesgo, Productos, Ver detalle -->
			<div class="dsm-card-kpi-footer d-flex align-items-center justify-content-between" style="background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 12px 18px; font-size: 12px;">
				<div class="d-flex align-items-center font-weight-bold" style="color: #b91c1c; gap: 6px;">
					<i class="fa-solid fa-box text-danger"></i> Total en riesgo: <span id="dsm-fefo-foot-risk" class="font-weight-bold" style="color: #dc2626;">{{ $stockExec['fefo_stats']['formatted_risk'] ?? '$0' }}</span>
				</div>
				<div class="d-flex align-items-center font-weight-bold" style="color: #b91c1c; gap: 6px;">
					<i class="fa-solid fa-box text-danger"></i> Productos: <span id="dsm-fefo-foot-count" class="font-weight-bold" style="color: #dc2626;">{{ $stockExec['fefo_stats']['products_count'] ?? count($expProds) }}</span>
				</div>
				<a href="javascript:void(0)" onclick="dsmOpenFefoAllModal()" class="font-weight-bold text-success d-inline-flex align-items-center" style="gap: 4px; text-decoration: none;">
					Ver detalle <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
				</a>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     5. FILA 4: TENDENCIA POR SUCURSAL (SECCIÓN 7) + MOVIMIENTOS HOY (SECCIÓN 8) + DECISIONES PRIORITARIAS (SECCIÓN 9)
	     ====================================================================== -->
	<div class="dsm-row-four">
		<!-- SECCIÓN 7: TENDENCIA POR SUCURSAL -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-branches" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="background: linear-gradient(135deg, #008744 0%, #00a651 100%); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; color: #fff;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: rgba(255, 255, 255, 0.2); width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; color: #fff;">
						<i class="fa-solid fa-shop"></i>
					</div>
					<div>
						<h2 style="color: #fff; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Tendencia por sucursal</h2>
						<span style="color: rgba(255, 255, 255, 0.85); font-size: 11px;">Valor, unidades, variación, rotación y cobertura</span>
					</div>
				</div>
				<div class="dropdown">
					<button type="button" class="btn btn-sm text-white font-weight-bold d-flex align-items-center" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="background: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.35); border-radius: 999px; font-size: 11.5px; padding: 4px 12px; gap: 5px;">
						<i class="fa-solid fa-file-export mr-1" style="font-size: 10px;"></i> Exportar <i class="fa-solid fa-chevron-down ml-1" style="font-size: 9px;"></i>
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm" style="font-size: 12px; border-radius: 8px;">
						<a class="dropdown-item" href="javascript:void(0)" onclick="dsmExportTable('csv', 'dsm-branch-table')"><i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar CSV</a>
						<a class="dropdown-item" href="javascript:void(0)" onclick="dsmExportTable('excel', 'dsm-branch-table')"><i class="fa-solid fa-file-excel text-success mr-2"></i>Exportar Excel</a>
						<a class="dropdown-item" href="javascript:void(0)" onclick="window.print()"><i class="fa-solid fa-file-pdf text-danger mr-2"></i>Exportar PDF</a>
					</div>
				</div>
			</div>

			<!-- Píldoras de Modos de Vista / Filtro -->
			<div class="d-flex align-items-center justify-content-between p-3 pb-2" style="gap: 6px;">
				<button type="button" class="dsm-branch-pill active" data-bmode="value" style="background: #00a651; color: #ffffff; border: 1px solid #00a651; border-radius: 8px; font-weight: 700; font-size: 11px; padding: 5px 12px; display: inline-flex; align-items: center; gap: 5px;">
					<i class="fa-solid fa-money-bill-wave"></i> Valor ($)
				</button>
				<button type="button" class="dsm-branch-pill" data-bmode="units" style="background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; font-size: 11px; padding: 5px 12px; display: inline-flex; align-items: center; gap: 5px;">
					<i class="fa-solid fa-box text-muted"></i> Unidades
				</button>
				<button type="button" class="dsm-branch-pill" data-bmode="variation" style="background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; font-size: 11px; padding: 5px 12px;">
					Variación %
				</button>
				<button type="button" class="dsm-branch-pill" id="dsm-btn-toggle-all-branches" style="background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; font-size: 11px; padding: 5px 12px; display: inline-flex; align-items: center; gap: 5px;" title="Alternar entre Top 5 y las 8 sucursales de la red">
					<i class="fa-solid fa-shop text-success"></i> 8 Sucursales
				</button>
			</div>

			<!-- 4 Cajas KPI Superiores (Valor total, Unidades, Rotación, Cobertura) -->
			<div class="px-3 pb-2">
				<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
					<div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 8px 6px; text-align: center;">
						<div style="color: #008744; font-size: 14px; margin-bottom: 2px;"><i class="fa-solid fa-coins"></i></div>
						<div style="font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-b-kpi-val">{{ $stockExec['branch_kpis']['formatted_total_value'] ?? '$11,914' }}</div>
						<div style="font-size: 10px; color: #64748b; font-weight: 600;">Valor total</div>
						<div class="mt-1"><span class="badge" style="background: #dcfce7; color: #008744; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">▲ +4.8%</span></div>
					</div>
					<div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 8px 6px; text-align: center;">
						<div style="color: #008744; font-size: 14px; margin-bottom: 2px;"><i class="fa-solid fa-box"></i></div>
						<div style="font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-b-kpi-units">{{ $stockExec['branch_kpis']['formatted_total_units'] ?? '1,043' }}</div>
						<div style="font-size: 10px; color: #64748b; font-weight: 600;">Unidades</div>
						<div class="mt-1"><span class="badge" style="background: #dcfce7; color: #008744; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">▲ +2.9%</span></div>
					</div>
					<div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 8px 6px; text-align: center;">
						<div style="color: #008744; font-size: 14px; margin-bottom: 2px;"><i class="fa-solid fa-rotate"></i></div>
						<div style="font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-b-kpi-rot">{{ $stockExec['branch_kpis']['rotation'] ?? '1.52' }}</div>
						<div style="font-size: 10px; color: #64748b; font-weight: 600;">Rotación</div>
						<div class="mt-1"><span class="badge" style="background: #fee2e2; color: #dc2626; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">▼ -0.3</span></div>
					</div>
					<div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 8px 6px; text-align: center;">
						<div style="color: #008744; font-size: 14px; margin-bottom: 2px;"><i class="fa-regular fa-calendar-days"></i></div>
						<div style="font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-b-kpi-cov">{{ $stockExec['branch_kpis']['coverage'] ?? '5.6 días' }}</div>
						<div style="font-size: 10px; color: #64748b; font-weight: 600;">Cobertura</div>
						<div class="mt-1"><span class="badge" style="background: #dcfce7; color: #008744; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">▲ +0.6 días</span></div>
					</div>
				</div>
			</div>

			<!-- Tabla de Sucursales -->
			<div class="table-responsive px-3" style="max-height: 290px; overflow-y: auto;">
				<table class="dsm-branch-table" id="dsm-branch-table" style="width: 100%; font-size: 12px;">
					<thead>
						<tr style="color: #64748b; font-size: 10.5px; text-transform: uppercase;">
							<th onclick="dsmSortBranches('name')" style="padding: 6px 4px; cursor: pointer;">Sucursal</th>
							<th style="text-align: right; padding: 6px 6px; cursor: pointer;" onclick="dsmSortBranches('inventory_value')" id="dsm-bcol-val">Valor inventario</th>
							<th style="text-align: right; padding: 6px 6px; cursor: pointer;" onclick="dsmSortBranches('units')" id="dsm-bcol-units">Unidades</th>
							<th style="text-align: center; padding: 6px 6px; cursor: pointer;" onclick="dsmSortBranches('rotation')">Rotación</th>
							<th style="text-align: right; padding: 6px 6px; cursor: pointer;" onclick="dsmSortBranches('coverage_days')">Cobertura</th>
							<th style="text-align: right; padding: 6px 4px;">Tendencia</th>
						</tr>
					</thead>
					<tbody id="dsm-branch-tbody">
						@foreach($branchesTable as $idx => $br)
							@php
								$hiddenExtra = $idx >= 5 ? 'd-none dsm-extra-branch-row' : '';
								$trendDir = $br['trend_dir'] ?? ($br['prev_variation_pct'] >= 0 ? 'up' : 'down');
								$trendBadge = $br['trend_badge'] ?? (($br['prev_variation_pct'] > 0 ? '+' : '') . $br['prev_variation_pct'] . '%');
								$isUp = ($trendDir === 'up');
							@endphp
							<tr class="{{ $hiddenExtra }}" data-branch-id="{{ $br['id'] }}" onclick="dsmSelectBranchRow({{ $br['id'] }})" title="Sucursal: {{ $br['name'] }} · Variación: {{ $trendBadge }} · Clic para filtrar todo el dashboard por esta sucursal" style="cursor: pointer;">
								<td>
									<div class="d-flex align-items-center" style="gap: 6px;">
										<i class="fa-solid fa-shop text-success" style="font-size: 12px;"></i>
										<span class="font-weight-bold text-dark text-truncate" style="max-width: 110px;">{{ $br['name'] }}</span>
									</div>
								</td>
								<td style="text-align: right; font-weight: 800; color: #0f172a;" class="dsm-bcell-val">${{ number_format($br['inventory_value'], 0) }}</td>
								<td style="text-align: right; font-weight: 600; color: #475569;" class="dsm-bcell-units">{{ number_format($br['units'], 0) }}</td>
								<td style="text-align: center; font-weight: 800; color: #ef4444;">{{ number_format($br['rotation'], 1) }}</td>
								<td style="text-align: right; color: #334155;">{{ number_format($br['coverage_days'], 0) }} días</td>
								<td style="text-align: right;">
									<div class="d-inline-flex align-items-center" style="gap: 4px;">
										<svg width="22" height="12" viewBox="0 0 22 12" style="overflow: visible;">
											@if($isUp)
												<polyline points="1,11 7,7 13,8 21,1" fill="none" stroke="#00a651" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
												<polyline points="16,1 21,1 21,6" fill="none" stroke="#00a651" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											@else
												<polyline points="1,1 7,5 13,4 21,11" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
												<polyline points="16,11 21,11 21,6" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											@endif
										</svg>
										<span class="badge font-weight-bold" style="background: {{ $isUp ? '#dcfce7' : '#fee2e2' }}; color: {{ $isUp ? '#008744' : '#dc2626' }}; font-size: 9.5px; padding: 2px 5px; border-radius: 4px;">
											{{ $isUp ? '▲' : '▼' }} {{ $trendBadge }}
										</span>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<!-- Botón Inferior: Ver comparación entre sucursales -->
			<div class="p-3 pt-2">
				<a href="javascript:void(0)" onclick="dsmOpenBranchComparisonModal()" class="d-flex align-items-center justify-content-between p-2 px-3 font-weight-bold" style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 10px; color: #008744; text-decoration: none; font-size: 12px; transition: background 0.15s ease;">
					<span class="d-flex align-items-center" style="gap: 8px;">
						<i class="fa-solid fa-chart-simple text-success"></i> Ver comparación entre sucursales
					</span>
					<i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i>
				</a>
			</div>
		</div>

		<!-- SECCIÓN 8: MOVIMIENTOS DE INVENTARIO DEL PERÍODO -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-movements" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: #fef3c7; color: #d97706; border: 1px solid #fde68a; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px;">
						<i class="fa-solid fa-arrow-right-arrow-left"></i>
					</div>
					<div>
						<h2 style="color: #0f172a; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Movimientos de inventario del período</h2>
						<span style="color: #64748b; font-size: 11px;">Entradas, salidas, traslados, devoluciones y otros</span>
					</div>
				</div>
				<div class="d-flex align-items-center" style="gap: 6px;">
					<span class="badge" style="background: #f0fdf4; border: 1px solid #86efac; color: #008744; border-radius: 999px; font-weight: 700; padding: 4px 10px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
						<i class="fa-regular fa-calendar-days"></i> Mes actual <i class="fa-solid fa-chevron-down" style="font-size: 9px;"></i>
					</span>
				</div>
			</div>

			<!-- Selector de Sucursal de movimientos -->
			<div class="px-3 pt-3 pb-2">
				<div class="dsm-subcard-select-wrap" style="width: 100%;">
					<i class="fa-solid fa-shop text-success mr-2"></i>
					<select id="dsm-moves-branch-select" class="dsm-subcard-select" style="width: 100%;" aria-label="Sucursal de movimientos">
						<option value="all" selected>Todas las sucursales</option>
						@foreach($branchesTable as $b)
							<option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
						@endforeach
						<option value="detailed_7">Ver 7 categorías detalladas</option>
					</select>
					<i class="fa-solid fa-chevron-down text-muted ml-1" style="font-size: 10px;"></i>
				</div>
			</div>

			<!-- Indicadores Superiores (Movimientos totales & Productos con movimiento) -->
			<div class="px-3 pb-2">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #fafafa; border: 1px solid #f1f5f9; border-radius: 12px; padding: 10px 14px;">
					<div class="d-flex align-items-center" style="gap: 12px;">
						<div style="background: #dcfce7; color: #008744; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
							<i class="fa-solid fa-arrow-right-arrow-left"></i>
						</div>
						<div>
							<div style="font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-move-kpi-total">{{ $movements['stats']['formatted_total'] ?? '21,600' }}</div>
							<div style="font-size: 11px; color: #64748b; font-weight: 600;">Movimientos totales</div>
							<div style="font-size: 10px; color: #008744; font-weight: 700;">▲ +6.4% vs. período anterior</div>
						</div>
					</div>
					<div class="d-flex align-items-center" style="gap: 12px; border-left: 1px solid #e2e8f0; padding-left: 12px;">
						<div style="background: #fef3c7; color: #d97706; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
							<i class="fa-solid fa-tag"></i>
						</div>
						<div>
							<div style="font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.1;" id="dsm-move-kpi-prods">{{ $movements['stats']['formatted_active_products'] ?? '4,982' }}</div>
							<div style="font-size: 11px; color: #64748b; font-weight: 600;">Productos con movimiento</div>
							<div style="font-size: 10px; color: #64748b;">de 8,245 en inventario (60.4%)</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Gráfico Interactivo de 5 Barras con Eje Y -->
			<div class="px-3 py-2">
				<div class="dsm-moves-chart-wrapper" style="display: flex; height: 180px; align-items: stretch; gap: 8px;">
					<!-- Eje Y -->
					<div style="display: flex; flex-direction: column; justify-content: space-between; font-size: 10px; color: #94a3b8; font-weight: 600; text-align: right; width: 42px; padding-bottom: 22px;">
						<span>12,000</span>
						<span>10,000</span>
						<span>8,000</span>
						<span>6,000</span>
						<span>4,000</span>
						<span>2,000</span>
						<span>0</span>
					</div>

					<!-- Área de Barras con líneas de cuadrícula horizontal -->
					<div class="dsm-moves-chart-area" id="dsm-moves-bars-container" style="flex: 1; display: flex; align-items: flex-end; justify-content: space-around; height: 100%; border-bottom: 1px solid #e2e8f0; position: relative; padding: 0 4px 6px 4px; gap: 12px;">
						@php
							$bars = $movements['bars_photo'] ?? [];
							$maxBarUnits = 12000;
							$iconsMap = [
								'entradas' => 'fa-box',
								'salidas' => 'fa-box',
								'traslados' => 'fa-arrow-right-arrow-left',
								'devoluciones' => 'fa-arrow-rotate-left',
								'otros' => 'fa-layer-group'
							];
							$colorsMap = [
								'entradas' => '#00a651',
								'salidas' => '#ef4444',
								'traslados' => '#f59e0b',
								'devoluciones' => '#8b5cf6',
								'otros' => '#64748b'
							];
						@endphp
						@foreach($bars as $bar)
							@php
								$h = max(18, round(($bar['units'] / $maxBarUnits) * 140));
								$bColor = $colorsMap[$bar['key']] ?? $bar['color'];
								$bIcon = $iconsMap[$bar['key']] ?? 'fa-box';
							@endphp
							<div class="dsm-move-bar-col" onclick="dsmOpenMovementsModal('{{ $bar['key'] }}')" title="{{ $bar['full_label'] }}: {{ number_format($bar['units'], 0) }} uds · Clic para ver detalle" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; cursor: pointer;">
								<span class="dsm-move-bar-val font-weight-bold" style="font-size: 11px; color: #0f172a; margin-bottom: 4px;">{{ number_format($bar['units'], 0) }}</span>
								<div class="dsm-move-bar-fill d-flex align-items-center justify-content-center" style="width: 100%; max-width: 44px; height: {{ $h }}px; background: {{ $bColor }}; border-radius: 8px 8px 4px 4px; color: #ffffff; font-size: 13px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); transition: transform 0.15s ease;">
									<div style="background: rgba(255, 255, 255, 0.25); width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
										<i class="fa-solid {{ $bIcon }}" style="font-size: 12px;"></i>
									</div>
								</div>
								<span class="dsm-move-bar-label font-weight-bold" style="font-size: 10.5px; color: #475569; margin-top: 6px;">{{ $bar['label'] }}</span>
							</div>
						@endforeach
					</div>
				</div>
			</div>

			<!-- Resumen Inferior de 5 Bloques Porcentuales -->
			<div class="px-3 pt-2 pb-2">
				<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px;">
					<div class="dsm-move-share-pill" style="background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px; padding: 6px 4px; text-align: center;">
						<div class="dsm-move-share-label text-success" style="font-size: 9.5px; font-weight: 700;">
							<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Entradas
						</div>
						<div class="dsm-move-share-val font-weight-bold text-dark" style="font-size: 12px;">48.9%</div>
					</div>
					<div class="dsm-move-share-pill" style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 6px 4px; text-align: center;">
						<div class="dsm-move-share-label text-danger" style="font-size: 9.5px; font-weight: 700;">
							<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Salidas
						</div>
						<div class="dsm-move-share-val font-weight-bold text-dark" style="font-size: 12px;">47.9%</div>
					</div>
					<div class="dsm-move-share-pill" style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 6px 4px; text-align: center;">
						<div class="dsm-move-share-label text-warning" style="font-size: 9.5px; font-weight: 700;">
							<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Traslados
						</div>
						<div class="dsm-move-share-val font-weight-bold text-dark" style="font-size: 12px;">0.2%</div>
					</div>
					<div class="dsm-move-share-pill" style="background: #f3e8ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 6px 4px; text-align: center;">
						<div class="dsm-move-share-label" style="color: #7e22ce; font-size: 9.5px; font-weight: 700;">
							<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Dev. clientes
						</div>
						<div class="dsm-move-share-val font-weight-bold text-dark" style="font-size: 12px;">0.2%</div>
					</div>
					<div class="dsm-move-share-pill" style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 4px; text-align: center;">
						<div class="dsm-move-share-label text-secondary" style="font-size: 9.5px; font-weight: 700;">
							<i class="fa-solid fa-circle" style="font-size: 6px;"></i> Otros
						</div>
						<div class="dsm-move-share-val font-weight-bold text-dark" style="font-size: 12px;">2.8%</div>
					</div>
				</div>
			</div>

			<!-- Pie Informativo & Enlace Ver Detalle -->
			<div class="dsm-card-kpi-footer d-flex align-items-center justify-content-between" style="background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 10px 18px; font-size: 11px;">
				<div class="text-muted">
					<i class="fa-solid fa-circle-info text-primary mr-1"></i> Sin doble conteo &middot; Otros = Despachos (210) + Ajustes (3) + Mermas (390)
				</div>
				<a href="javascript:void(0)" onclick="dsmOpenMovementsModal('todos')" class="font-weight-bold text-success d-inline-flex align-items-center" style="gap: 4px; text-decoration: none;">
					Ver detalle <i class="fa-solid fa-arrow-right" style="font-size: 10px;"></i>
				</a>
			</div>
		</div>

		<!-- SECCIÓN 9: DECISIONES PRIORITARIAS -->
		<div class="dsm-card dsm-card-pro" id="dsm-section-decisions" style="overflow: hidden; padding: 0;">
			<div class="dsm-card-header-clean" style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px;">
						<i class="fa-solid fa-bullseye"></i>
					</div>
					<div>
						<h2 style="color: #0f172a; font-size: 14.5px; font-weight: 800; margin: 0; line-height: 1.2;">Decisiones prioritarias</h2>
						<span style="color: #64748b; font-size: 11px;">Acciones inmediatas para proteger el inventario</span>
					</div>
				</div>
				<button type="button" class="btn btn-sm text-success font-weight-bold" onclick="dsmOpenAllDecisionsModal()" style="border: 1px solid #cbd5e1; border-radius: 999px; font-size: 11.5px; padding: 4px 12px; background: transparent;">
					Ver todas &rarr;
				</button>
			</div>

			<div class="dsm-card-body p-3">
				<!-- Alerta 1: Próximos a vencer -->
				<div class="dsm-decision-card is-danger mb-2" onclick="dsmOpenFefoAllModal()" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: transform 0.15s ease;">
					<div class="d-flex align-items-center" style="gap: 12px;">
						<div style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;">
							<i class="fa-solid fa-triangle-exclamation"></i>
						</div>
						<div>
							<div style="font-weight: 800; color: #991b1b; font-size: 13.5px; line-height: 1.2;">140 productos próximos a vencer</div>
							<div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Valor en riesgo: <strong class="text-dark">$3,878</strong> &middot; &le; 30 días</div>
							<div style="font-size: 11px; color: #64748b;">Sucursales: <strong>5</strong> &middot; Lotes: <strong>184</strong></div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size: 12px;"></i>
				</div>

				<!-- Alerta 2: Productos agotados -->
				<div class="dsm-decision-card is-danger mb-2" onclick="window.location.href='{{ $U('/mrp') }}'" style="background: #fff5f5; border: 1px solid #fed7d7; border-radius: 12px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: transform 0.15s ease;">
					<div class="d-flex align-items-center" style="gap: 12px;">
						<div style="background: #fed7d7; color: #c53030; border: 1px solid #feb2b2; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;">
							<i class="fa-solid fa-box-open"></i>
						</div>
						<div>
							<div style="font-weight: 800; color: #991b1b; font-size: 13.5px; line-height: 1.2;">231 productos agotados</div>
							<div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Pares producto-sucursal con existencia 0 y stock mínimo.</div>
							<div style="font-size: 11px; color: #64748b;">Impacto estimado en ventas: <strong class="text-dark">$12,460/mes</strong></div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size: 12px;"></i>
				</div>

				<!-- Alerta 3: Productos bajo mínimo -->
				<div class="dsm-decision-card is-warning mb-3" onclick="window.location.href='{{ $U('/mrp') }}'" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: transform 0.15s ease;">
					<div class="d-flex align-items-center" style="gap: 12px;">
						<div style="background: #fef3c7; color: #d97706; border: 1px solid #fde68a; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0;">
							<i class="fa-solid fa-arrow-down-short-wide"></i>
						</div>
						<div>
							<div style="font-weight: 800; color: #92400e; font-size: 13.5px; line-height: 1.2;">97 productos bajo mínimo</div>
							<div style="font-size: 11.5px; color: #475569; margin-top: 2px;">Existencia mayor a 0 y menor al mínimo de la sucursal.</div>
							<div style="font-size: 11px; color: #64748b;">Faltante estimado: <strong class="text-dark">1,284 unidades</strong></div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size: 12px;"></i>
				</div>

				<!-- Sección de Acciones Rápidas -->
				<div class="mt-2 pt-2 border-top">
					<div class="font-weight-bold mb-2 d-flex align-items-center" style="font-size: 13px; color: #0f172a; gap: 6px;">
						<i class="fa-solid fa-bolt text-success"></i> Acciones rápidas
					</div>
					<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
						<a href="{{ $U('/mrp') }}" class="dsm-action-btn" title="Crear compra en Planeación y Compras">
							<div class="dsm-action-icon"><i class="fa-solid fa-cart-shopping"></i></div>
							<span class="dsm-action-label">Crear compra</span>
						</a>
						<a href="{{ $U('/transfers') }}" class="dsm-action-btn" title="Crear traslado entre tiendas">
							<div class="dsm-action-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
							<span class="dsm-action-label">Crear traslado</span>
						</a>
						<a href="javascript:void(0)" onclick="dsmOpenMarkdownAction()" class="dsm-action-btn" title="Aplicar rebaja de precio autorizada en POS">
							<div class="dsm-action-icon"><i class="fa-solid fa-tag"></i></div>
							<span class="dsm-action-label">Aplicar rebaja</span>
						</a>
						<a href="javascript:void(0)" onclick="dsmOpenFefoAllModal()" class="dsm-action-btn" title="Revisar vencimientos y lotes FEFO">
							<div class="dsm-action-icon"><i class="fa-regular fa-calendar-check"></i></div>
							<span class="dsm-action-label">Revisar FEFO</span>
						</a>
					</div>
				</div>
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
				<button type="button" class="btn btn-success btn-sm font-weight-bold" id="dsmKpiDetailBtn">Ver detalle en el panel</button>
				<a href="{{ $U('/kardex') }}" class="btn btn-outline-success btn-sm font-weight-bold" data-perm="kardex"><i class="fa-solid fa-file-invoice mr-1"></i>Auditar en Kardex</a>
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
					<i class="fa-solid fa-circle-info mr-1"></i>Revisar y rotar crean una tarea; traslado abre el módulo de Traslados. <strong>Registrar merma es una operación real</strong> y pide confirmación.
				</div>
				<div id="dsmFefoLotSummary" class="p-3 mb-3 rounded border bg-light"></div>

				<div class="form-group">
					<label class="font-weight-bold small text-uppercase text-muted">Seleccionar Acción Operativa / Comercial</label>
					<div class="row">
						<div class="col-md-6 mb-2">
							<button type="button" data-perm="task" class="btn btn-outline-primary btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('inspect')">
								<i class="fa-solid fa-microscope mr-2"></i>1. Revisar lote (Calidad &amp; Frío)
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" data-perm="task" class="btn btn-outline-success btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('rotate_task')">
								<i class="fa-solid fa-people-carry-box mr-2"></i>2. Crear tarea de rotación FEFO
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" data-perm="transfer" class="btn btn-outline-info btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('transfer')">
								<i class="fa-solid fa-truck-arrow-right mr-2"></i>3. Recomendar traslado inter-sucursal
							</button>
						</div>
						<div class="col-md-6 mb-2">
							<button type="button" class="btn btn-outline-warning btn-block text-left font-weight-bold py-2 text-dark" onclick="dsmSubmitFefoAction('markdown')">
								<i class="fa-solid fa-tags mr-2"></i>4. Consultar rebaja FEFO del POS
							</button>
						</div>
						<div class="col-md-12">
							<button type="button" data-perm="waste" class="btn btn-outline-danger btn-block text-left font-weight-bold py-2" onclick="dsmSubmitFefoAction('waste')">
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
     MODAL 5: CENTRO DE DECISIONES PRIORITARIAS
     ========================================================================== -->
<div class="modal fade" id="dsmDecisionsModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-bell text-danger mr-2"></i>Decisiones Prioritarias de Inventario 
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="btn-group btn-group-sm mb-3" role="group">
					<button type="button" class="btn btn-dark active" onclick="dsmFilterDecisionsModal('all', this)">Todas</button>
					<button type="button" class="btn btn-danger" onclick="dsmFilterDecisionsModal('red', this)">Rojo — Crítico</button>
					<button type="button" class="btn btn-warning text-dark" onclick="dsmFilterDecisionsModal('yellow', this)">Amarillo — Atención</button>
					<button type="button" class="btn btn-success" onclick="dsmFilterDecisionsModal('green', this)">Verde — Oportunidad</button>
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
					<i class="fa-solid fa-diagram-project text-success mr-2"></i>Flujo Operativo Transversal (operaciones reales)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-warning small mb-3" role="note">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i><strong>Operaciones reales.</strong> Cada paso registra recepción, venta, devolución o conteo en el inventario y el Kardex; se pide confirmación antes de ejecutarlo.
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
			<div class="modal-footer bg-light d-flex justify-content-end">
				<div>
					<button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="dsmExecuteWorkflowStep()">
						<i class="fa-solid fa-bolt mr-1"></i>Ejecutar paso
					</button>
					<button type="button" class="btn btn-secondary btn-sm ml-1" data-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: ACCIONES SOBRE UN PRODUCTO EN ALERTA (SEGÚN PERMISOS DEL USUARIO)
     ========================================================================== -->
<div class="modal fade" id="dsmAlertModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
			<div class="modal-header text-white" style="background: linear-gradient(135deg, #008744 0%, #00a651 100%);">
				<h5 class="modal-title font-weight-bold" id="dsmAlertModalTitle"><i class="fa-solid fa-bolt mr-2"></i>Acciones Operativas</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4" id="dsmAlertModalBody"></div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: LISTA COMPLETA DE ALERTAS OPERATIVAS (BÚSQUEDA Y PAGINACIÓN)
     ========================================================================== -->
<div class="modal fade" id="dsmAllAlertsModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
			<div class="modal-header bg-white border-bottom py-3 px-4">
				<div class="d-flex align-items-center">
					<div class="dsm-header-icon-circle is-red mr-3" style="width: 38px; height: 38px;">
						<i class="fa-regular fa-bell"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold mb-0 text-dark" id="dsmAllAlertsModalTitle">Centro de Alertas de Inventario</h5>
						<small class="text-muted">Gestión integral de alertas operativas con criticidad y trazabilidad</small>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 10px;">
					<div class="dsm-alert-pills-row mb-0 p-0" id="dsm-all-alerts-tabs"></div>
					<div style="min-width: 250px;">
						<input type="text" id="dsm-all-alerts-search" class="form-control form-control-sm" placeholder="Buscar por producto, UPC o sucursal..." style="border-radius: 20px;">
					</div>
				</div>
				<div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
					<table class="table table-hover table-sm dsm-clean-table mb-0">
						<thead class="bg-light sticky-top">
							<tr>
								<th>Producto</th>
								<th>UPC</th>
								<th>Departamento</th>
								<th>Sucursal</th>
								<th class="text-center">Stock actual</th>
								<th class="text-center">Stock mínimo</th>
								<th class="text-center">Diferencia</th>
								<th>Estado</th>
								<th class="text-center">Acciones</th>
							</tr>
						</thead>
						<tbody id="dsm-all-alerts-tbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4 justify-content-between">
				<span class="small text-muted font-weight-bold" id="dsm-all-alerts-summary"></span>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: DETALLE DE UN MOVIMIENTO Y ENLACE AL DOCUMENTO ORIGINAL
     ========================================================================== -->
<div class="modal fade" id="dsmMoveModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold" id="dsmMoveModalTitle"></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body" id="dsmMoveModalBody"></div>
			<div class="modal-footer bg-light" id="dsmMoveModalFooter"></div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: AUDITORÍA Y DEFINICIÓN DE CADA INDICADOR KPI (INFO BUTTON ⓘ)
     ========================================================================== -->
<div class="modal fade" id="dsmKpiInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
		<div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 45px rgba(0,0,0,0.18); overflow: hidden;">
			<div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 18px 24px;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div id="dsm-modal-kpi-icon" class="dsm-kpi-icon is-green" style="width: 44px; height: 44px; font-size: 19px; border-radius: 12px;">
						<i class="fa-solid fa-boxes-stacked"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" id="dsm-modal-kpi-title" style="font-size: 16.5px; margin: 0;">Valor del inventario</h5>
						<span class="text-muted" style="font-size: 12px;">Definición operativa y fórmula en DAILY</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar" style="outline: none;">&times;</button>
			</div>
			<div class="modal-body" style="padding: 22px 24px;">
				<div class="mb-3">
					<label class="text-uppercase text-muted font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Concepto & Propósito</label>
					<p id="dsm-modal-kpi-desc" class="text-secondary" style="font-size: 13.5px; line-height: 1.5; margin: 0;"></p>
				</div>
				<div class="mb-3" style="background: #f1f5f9; border-radius: 12px; padding: 14px 16px;">
					<label class="text-uppercase text-muted font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.05em; display: flex; align-items: center; gap: 5px; margin-bottom: 6px;">
						<i class="fa-solid fa-calculator text-primary"></i> Fórmula de cálculo en DAILY
					</label>
					<div id="dsm-modal-kpi-formula" class="font-weight-bold text-dark" style="font-size: 12.5px; font-family: monospace; line-height: 1.45;"></div>
				</div>
				<div class="row" style="margin: 0 -6px;">
					<div class="col-6" style="padding: 0 6px;">
						<div style="background: #fafafa; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px;">
							<span class="text-muted d-block font-weight-bold" style="font-size: 10.5px; text-transform: uppercase;">Meta / Tolerancia</span>
							<b id="dsm-modal-kpi-target" class="text-dark" style="font-size: 12.5px;"></b>
						</div>
					</div>
					<div class="col-6" style="padding: 0 6px;">
						<div style="background: #fafafa; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px;">
							<span class="text-muted d-block font-weight-bold" style="font-size: 10.5px; text-transform: uppercase;">Estado de Operación</span>
							<b id="dsm-modal-kpi-state" style="font-size: 12.5px;"></b>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px; justify-content: space-between;">
				<button type="button" class="btn btn-light border font-weight-bold" data-dismiss="modal" style="font-size: 12.5px; border-radius: 8px;">Cerrar</button>
				<button type="button" class="btn btn-success font-weight-bold" id="dsm-modal-kpi-action-btn" style="font-size: 12.5px; border-radius: 8px;">
					<i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Explorar detalle
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: AUDITORÍA DEL MAPA DE CALOR (INFO ⓘ)
     ========================================================================== -->
<div class="modal fade" id="dsmHeatmapInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
		<div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 45px rgba(0,0,0,0.18); overflow: hidden;">
			<div class="modal-header" style="background: linear-gradient(135deg, #008744 0%, #00a651 100%); color: #ffffff; padding: 18px 24px;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="background: rgba(255,255,255,0.2); width: 44px; height: 44px; font-size: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
						<i class="fa-solid fa-layer-group"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-white" style="font-size: 16.5px; margin: 0;">Mapa de calor de existencias</h5>
						<span style="color: rgba(255,255,255,0.85); font-size: 12px;">Interpretación de niveles por departamento y día</span>
					</div>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar" style="outline: none; opacity: 0.9;">&times;</button>
			</div>
			<div class="modal-body" style="padding: 22px 24px;">
				<div class="mb-3">
					<label class="text-uppercase text-muted font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Propósito Operativo</label>
					<p class="text-secondary" style="font-size: 13.5px; line-height: 1.5; margin: 0;">
						Cada fila es un departamento y cada columna un día del período. Las cifras salen del kardex y de las ventas registradas, sin estimaciones: los días anteriores al primer movimiento se muestran como «sin datos». Un día es crítico cuando su total cae en la franja roja de la métrica elegida.
					</p>
				</div>
				<div class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
					<label class="text-uppercase text-muted font-weight-bold mb-2 d-block" style="font-size: 10.5px;">Código de colores en DAILY</label>
					<div class="d-flex align-items-center mb-1" style="gap: 8px; font-size: 12px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: #22c55e;"></span> <strong>Verde:</strong> Nivel adecuado de stock / demanda equilibrada.</div>
					<div class="d-flex align-items-center mb-1" style="gap: 8px; font-size: 12px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: #facc15;"></span> <strong>Amarillo:</strong> Advertencia / rotación acelerada que amerita revisión.</div>
					<div class="d-flex align-items-center mb-1" style="gap: 8px; font-size: 12px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: #fb923c;"></span> <strong>Naranja:</strong> Nivel crítico / riesgo inminente de quiebre en góndola.</div>
					<div class="d-flex align-items-center" style="gap: 8px; font-size: 12px;"><span style="width: 14px; height: 14px; border-radius: 4px; background: #ef4444;"></span> <strong>Rojo:</strong> Riesgo elevado / venta pico que supera la reposición diaria.</div>
				</div>
			</div>
			<div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px;">
				<button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Entendido</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: AUDITORÍA DEL TOP 10 PRODUCTOS (INFO ⓘ)
     ========================================================================== -->
<div class="modal fade" id="dsmTop10InfoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
		<div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 45px rgba(0,0,0,0.18); overflow: hidden;">
			<div class="modal-header" style="background: #fef3c7; border-bottom: 1px solid #fde68a; padding: 18px 24px;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="background: #ffffff; color: #d97706; width: 44px; height: 44px; font-size: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(217,119,6,0.15);">
						<i class="fa-solid fa-trophy"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="font-size: 16.5px; margin: 0;">Top 10 Productos por Valor</h5>
						<span class="text-muted" style="font-size: 12px;">Concentración de capital y principio de Pareto</span>
					</div>
				</div>
				<button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar" style="outline: none;">&times;</button>
			</div>
			<div class="modal-body" style="padding: 22px 24px;">
				<div class="mb-3">
					<label class="text-uppercase text-muted font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Concepto Financiero</label>
					<p class="text-secondary" style="font-size: 13.5px; line-height: 1.5; margin: 0;">
						Representa los artículos que inmovilizan la mayor porción del capital de trabajo. La participación se calcula como: <code>(Valor del Producto / Valor Total del Inventario) &times; 100</code>. En retail, el top 10 suele concentrar entre el 60% y el 90% del valor total.
					</p>
				</div>
			</div>
			<div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px;">
				<button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: AUDITORÍA DE VENCIMIENTOS FEFO (INFO ⓘ)
     ========================================================================== -->
<div class="modal fade" id="dsmFefoInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
		<div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 45px rgba(0,0,0,0.18); overflow: hidden;">
			<div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca; padding: 18px 24px;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="background: #ffffff; color: #dc2626; width: 44px; height: 44px; font-size: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(220,38,38,0.15);">
						<i class="fa-solid fa-triangle-exclamation"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="font-size: 16.5px; margin: 0;">Riesgo de Vencimiento (FEFO)</h5>
						<span class="text-muted" style="font-size: 12px;">Prioridad First Expired, First Out</span>
					</div>
				</div>
				<button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar" style="outline: none;">&times;</button>
			</div>
			<div class="modal-body" style="padding: 22px 24px;">
				<div class="mb-3">
					<label class="text-uppercase text-muted font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Bandas de Severidad FEFO</label>
					<p class="text-secondary" style="font-size: 13.5px; line-height: 1.5; margin: 0;">
						Clasifica lotes según los días restantes hasta su fecha de caducidad:
					</p>
					<ul class="mt-2 text-secondary" style="font-size: 12.5px; padding-left: 20px;">
						<li><strong>Rojo (0–7 días):</strong> Requiere acción inmediata: rebaja agresiva en POS, traslado a tienda con alta rotación o retiro de góndola.</li>
						<li><strong>Amarillo (8–15 días):</strong> Adelantar lote al frente de góndola y revisar velocidad de venta.</li>
						<li><strong>Azul / Verde (16–30 días):</strong> Seguimiento preventivo estándar de inventario.</li>
					</ul>
				</div>
			</div>
			<div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 24px;">
				<button type="button" class="btn btn-secondary btn-sm font-weight-bold" data-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: LISTADO COMPLETO DEL TOP 10 Y RANKING DE VALOR
     ========================================================================== -->
<div class="modal fade" id="dsmTop10AllModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 22px;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: #fef3c7; color: #d97706; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
						<i class="fa-solid fa-trophy"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="margin: 0; font-size: 16px;">Ranking de Productos por Valor en Inventario</h5>
						<span class="text-muted" style="font-size: 12px;">Todos los productos valorizados en DAILY</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body" style="padding: 18px 22px;">
				<div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
					<table class="table table-sm table-hover mb-0" id="dsm-top10-all-modal-table" style="font-size: 12.5px;">
						<thead class="thead-light">
							<tr>
								<th>#</th>
								<th>Producto</th>
								<th>Departamento</th>
								<th class="text-right">Valor</th>
								<th class="text-right">Unidades</th>
								<th class="text-right">% Participación</th>
							</tr>
						</thead>
						<tbody id="dsm-top10-all-modal-tbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4 justify-content-between">
				<span class="small text-muted font-weight-bold" id="dsm-top10-all-modal-summary"></span>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: LISTADO COMPLETO FEFO Y LOTES PRÓXIMOS A VENCER
     ========================================================================== -->
<div class="modal fade" id="dsmFefoAllModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header" style="background: #fff1f2; border-bottom: 1px solid #fecdd3; padding: 16px 22px;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: #ffe4e6; color: #e11d48; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
						<i class="fa-solid fa-triangle-exclamation"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="margin: 0; font-size: 16px;">Lotes con Riesgo de Vencimiento (FEFO)</h5>
						<span class="text-muted" style="font-size: 12px;">Auditoría integral y trazabilidad preventiva de lotes por caducar</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body" style="padding: 18px 22px;">
				<div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
					<table class="table table-sm table-hover mb-0" id="dsm-fefo-all-modal-table" style="font-size: 12.5px;">
						<thead class="thead-light">
							<tr>
								<th>#</th>
								<th>Producto</th>
								<th>Lote</th>
								<th>Sucursal / Ubicación</th>
								<th>Vencimiento</th>
								<th class="text-center">Días</th>
								<th class="text-right">Stock</th>
								<th class="text-right">Valor</th>
								<th class="text-center">Acción</th>
							</tr>
						</thead>
						<tbody id="dsm-fefo-all-modal-tbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4 justify-content-between">
				<span class="small text-muted font-weight-bold" id="dsm-fefo-all-modal-summary"></span>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: COMPARACIÓN COMPLETA ENTRE SUCURSALES (BENCHMARK RED)
     ========================================================================== -->
<div class="modal fade" id="dsmBranchComparisonModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header" style="background: #f0fdf4; border-bottom: 1px solid #dcfce7; padding: 16px 22px;">
				<div class="d-flex align-items-center" style="gap: 12px;">
					<div style="background: #00a651; color: #ffffff; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px;">
						<i class="fa-solid fa-shop"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="margin: 0; font-size: 16.5px;">Comparativa Integral entre Sucursales</h5>
						<span class="text-muted" style="font-size: 12px;">Ranking, rotación, cobertura, productos críticos y tendencias de la red</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body" style="padding: 18px 22px;">
				<div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
					<table class="table table-sm table-hover mb-0" id="dsm-branch-comparison-table" style="font-size: 12.5px;">
						<thead class="thead-light">
							<tr>
								<th>#</th>
								<th>Sucursal</th>
								<th class="text-right">Valor Inventario</th>
								<th class="text-right">Unidades</th>
								<th class="text-center">Rotación</th>
								<th class="text-right">Cobertura</th>
								<th class="text-center">Agotados</th>
								<th class="text-center">Bajo Mínimo</th>
								<th class="text-center">Lotes FEFO</th>
								<th class="text-right">Tendencia</th>
								<th class="text-center">Acción</th>
							</tr>
						</thead>
						<tbody id="dsm-branch-comparison-tbody">
							@foreach($branchesTable as $idx => $br)
								@php
									$trendDir = $br['trend_dir'] ?? ($br['prev_variation_pct'] >= 0 ? 'up' : 'down');
									$trendBadge = $br['trend_badge'] ?? (($br['prev_variation_pct'] > 0 ? '+' : '') . $br['prev_variation_pct'] . '%');
									$isUp = ($trendDir === 'up');
								@endphp
								<tr onclick="dsmSelectBranchRow({{ $br['id'] }}); $('#dsmBranchComparisonModal').modal('hide');" style="cursor: pointer;" title="Clic para filtrar el panel por esta sucursal">
									<td class="font-weight-bold text-muted">{{ $idx + 1 }}</td>
									<td>
										<div class="d-flex align-items-center" style="gap: 8px;">
											<i class="fa-solid fa-shop text-success"></i>
											<strong>{{ $br['name'] }}</strong>
										</div>
									</td>
									<td class="text-right font-weight-bold text-dark">${{ number_format($br['inventory_value'], 0) }}</td>
									<td class="text-right">{{ number_format($br['units'], 0) }}</td>
									<td class="text-center font-weight-bold text-danger">{{ number_format($br['rotation'], 1) }}x</td>
									<td class="text-right text-muted">{{ number_format($br['coverage_days'], 0) }} días</td>
									<td class="text-center"><span class="badge badge-light border">{{ $br['out_of_stock'] }}</span></td>
									<td class="text-center"><span class="badge badge-light border">{{ $br['below_min'] }}</span></td>
									<td class="text-center"><span class="badge badge-light border">{{ $br['expiring'] }}</span></td>
									<td class="text-right">
										<span class="badge font-weight-bold" style="background: {{ $isUp ? '#dcfce7' : '#fee2e2' }}; color: {{ $isUp ? '#008744' : '#dc2626' }}; font-size: 10px; padding: 3px 6px;">
											{{ $isUp ? '▲' : '▼' }} {{ $trendBadge }}
										</span>
									</td>
									<td class="text-center">
										<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" style="border-radius: 6px; font-size: 11px;">Filtrar</button>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-4 justify-content-between">
				<span class="small text-muted font-weight-bold">
					<i class="fa-solid fa-store mr-1 text-success"></i> {{ count($branchesTable) }} sucursales registradas en DAILY &middot; Consolidado: ${{ number_format(array_sum(array_column($branchesTable, 'inventory_value')), 0) }}
				</span>
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: APLICAR REBAJA DE PRECIO AUTORIZADA
     ========================================================================== -->
<div class="modal fade" id="dsmMarkdownModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
			<div class="modal-header" style="background: #fef3c7; border-bottom: 1px solid #fde68a; padding: 16px 20px;">
				<div class="d-flex align-items-center" style="gap: 10px;">
					<div style="background: #ffffff; color: #d97706; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
						<i class="fa-solid fa-tag"></i>
					</div>
					<div>
						<h5 class="modal-title font-weight-bold text-dark" style="margin: 0; font-size: 16px;">Aplicar Rebaja Autorizada en POS</h5>
						<span class="text-muted" style="font-size: 11.5px;">Liquidación preventiva para acelerar rotación FEFO</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
			</div>
			<div class="modal-body p-4">
				<div class="form-group mb-3">
					<label class="small font-weight-bold text-dark mb-1">Producto o Lote a Rebajar</label>
					<select id="dsmMarkdownProductSelect" class="form-control form-control-sm" style="border-radius: 8px;">
						@foreach(array_slice($stockExec['expiring_products'] ?? [], 0, 8) as $ep)
							<option value="{{ $ep['stock_id'] }}">{{ $ep['name'] }} (Lote: {{ $ep['lot'] }} · {{ $ep['days_left'] }}d rest.)</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-3">
						<label class="small font-weight-bold text-dark mb-1">Porcentaje de Descuento</label>
						<select id="dsmMarkdownPctSelect" class="form-control form-control-sm" style="border-radius: 8px;">
							<option value="15">15% Descuento</option>
							<option value="25" selected>25% Descuento</option>
							<option value="40">40% Descuento</option>
							<option value="50">50% Liquidación</option>
						</select>
					</div>
					<div class="col-6 form-group mb-3">
						<label class="small font-weight-bold text-dark mb-1">Motivo Comercial</label>
						<select id="dsmMarkdownReasonSelect" class="form-control form-control-sm" style="border-radius: 8px;">
							<option value="fefo_expiry">Vencimiento Próximo (FEFO)</option>
							<option value="overstock">Sobrestock / Baja Rotación</option>
							<option value="packaging">Detalle Estético de Empaque</option>
						</select>
					</div>
				</div>
				<div class="form-group mb-0">
					<label class="small font-weight-bold text-dark mb-1">Nota o Justificación de Auditoría</label>
					<input type="text" id="dsmMarkdownNotes" class="form-control form-control-sm" placeholder="Aprobado por jefatura para góndola principal" style="border-radius: 8px;">
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-3 justify-content-between">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
				<button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="dsmSubmitMarkdown()" style="border-radius: 8px;">
					<i class="fa-solid fa-check mr-1"></i> Autorizar y Aplicar en POS
				</button>
			</div>
		</div>
	</div>
</div>

@include('components.productcard', [
	'asModal' => true
])
@stop
