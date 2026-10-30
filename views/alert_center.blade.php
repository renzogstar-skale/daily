@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Centro de Alertas · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/dairy_alerts.css?v=', true) }}{{ time() }}" rel="stylesheet">
@endpush

@section('content')
@php
	$branchName = 'Red Consolidada';
	foreach ($branches as $b) { 
		if (!empty($filters['branch_id']) && intval($b['id']) === intval($filters['branch_id'])) {
			$branchName = $b['name']; 
		}
	}
@endphp

<div class="daily-alerts-container" id="daily-alerts-app">

	<!-- ============================================================== -->
	<!-- 1. ENCABEZADO SUPERIOR                                         -->
	<!-- ============================================================== -->
	<header class="daily-alerts-header">
		<div class="daily-alerts-header-left">
			<div class="daily-header-icon-box">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
			<div>
				<h1 class="daily-page-title">Centro de Alertas</h1>
				<p class="daily-page-subtitle">Monitorea, asigna y resuelve incidencias detectadas en los registros para una operación más segura y eficiente.</p>
			</div>
		</div>

		<div class="daily-alerts-header-right">
			<!-- Selector de Período de Fecha -->
			<div class="dropdown">
				<button class="daily-date-card dropdown-toggle" type="button" id="dailyPeriodDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-regular fa-calendar text-muted" style="font-size: 1.15rem;"></i>
					<div>
						<div class="date-period" id="daily-period-label">Últimos 7 días</div>
						<div class="date-sub" id="daily-period-sub"></div>
					</div>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" aria-labelledby="dailyPeriodDropdown">
					<a class="dropdown-item small font-weight-600 is-active" href="#" data-period="7d">Últimos 7 días</a>
					<a class="dropdown-item small" href="#" data-period="today">Hoy</a>
					<a class="dropdown-item small" href="#" data-period="30d">Últimos 30 días</a>
					<a class="dropdown-item small" href="#" data-period="month">Mes en curso</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item small text-primary" href="#" data-period="custom"><i class="fa-regular fa-calendar-plus mr-2"></i>Rango personalizado…</a>
				</div>
			</div>

			<!-- Botón Nueva Alerta -->
			<div class="dropdown">
				<button class="daily-btn-primary-green dropdown-toggle" type="button" id="dailyNewAlertDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-plus mr-1"></i> Nueva alerta
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" aria-labelledby="dailyNewAlertDropdown">
					<a class="dropdown-item small font-weight-600" href="#" id="alc-btn-create-manual"><i class="fa-solid fa-file-circle-plus text-success mr-2"></i>Registrar incidencia manual</a>
					<a class="dropdown-item small" href="#" id="alc-btn-scan-rules"><i class="fa-solid fa-bolt text-warning mr-2"></i>Escanear reglas de control</a>
					<a class="dropdown-item small" href="#" id="alc-btn-bulk-assign"><i class="fa-solid fa-user-plus text-primary mr-2"></i>Asignación masiva</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item small text-muted" href="#" id="alc-btn-rules-cfg"><i class="fa-solid fa-gear mr-2"></i>Configurar parámetros de reglas</a>
				</div>
			</div>
		</div>
	</header>

	<!-- ============================================================== -->
	<!-- 2. FILA DE KPIS SUPERIORES (6 TARJETAS EXACTAS DE LA FOTO)     -->
	<!-- ============================================================== -->
	<section class="daily-kpi-grid-6" aria-label="Indicadores Clave">
		<!-- Card 1: Alertas activas -->
		<div class="daily-kpi-stat-card" data-filter-tab="all" role="button" tabindex="0">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-red-soft">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<!-- Red micro sparkline -->
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 18L10 16L20 19L30 11L40 13L49 6L57 8" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">127</span>
				<span class="daily-kpi-badge is-danger"><i class="fa-solid fa-arrow-up"></i> 12%</span>
			</div>
			<h3 class="daily-kpi-label">Alertas activas</h3>
			<p class="daily-kpi-subtext">Total en proceso</p>
		</div>

		<!-- Card 2: Críticas -->
		<div class="daily-kpi-stat-card" data-filter-tab="critical" role="button" tabindex="0">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-red-solid">
					<i class="fa-solid fa-circle-exclamation"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 19L12 17L22 20L32 14L42 16L50 8L57 10" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">27</span>
				<span class="daily-kpi-badge is-danger"><i class="fa-solid fa-arrow-up"></i> 3%</span>
			</div>
			<h3 class="daily-kpi-label">Críticas</h3>
			<p class="daily-kpi-subtext">Requieren atención inmediata</p>
		</div>

		<!-- Card 3: Vencidas -->
		<div class="daily-kpi-stat-card" data-filter-tab="overdue" role="button" tabindex="0">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-orange">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 18L10 15L20 17L30 10L40 12L48 6L57 9" stroke="#F97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">65</span>
				<span class="daily-kpi-badge is-warning"><i class="fa-solid fa-arrow-up"></i> 18%</span>
			</div>
			<h3 class="daily-kpi-label">Vencidas</h3>
			<p class="daily-kpi-subtext">Fuera de tiempo objetivo</p>
		</div>

		<!-- Card 4: Sin asignar -->
		<div class="daily-kpi-stat-card" data-filter-tab="unassigned" role="button" tabindex="0">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-yellow">
					<i class="fa-regular fa-user"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 12L12 15L22 10L32 14L42 9L50 12L57 16" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">75</span>
				<span class="daily-kpi-badge is-success"><i class="fa-solid fa-arrow-down"></i> 6%</span>
			</div>
			<h3 class="daily-kpi-label">Sin asignar</h3>
			<p class="daily-kpi-subtext">Requieren responsable</p>
		</div>

		<!-- Card 5: Resueltas hoy -->
		<div class="daily-kpi-stat-card" data-filter-tab="resolved" role="button" tabindex="0">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-green-solid">
					<i class="fa-solid fa-check"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 20L12 16L22 14L32 11L42 8L50 6L57 3" stroke="#008E48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">48</span>
				<span class="daily-kpi-badge is-success"><i class="fa-solid fa-arrow-up"></i> 32%</span>
			</div>
			<h3 class="daily-kpi-label">Resueltas hoy</h3>
			<p class="daily-kpi-subtext">Incidencias cerradas</p>
		</div>

		<!-- Card 6: Tiempo medio de resolución -->
		<div class="daily-kpi-stat-card">
			<div class="daily-kpi-top-row">
				<div class="daily-kpi-icon-pill is-green-soft">
					<i class="fa-solid fa-stopwatch"></i>
				</div>
				<div class="daily-kpi-sparkline">
					<svg viewBox="0 0 58 24" fill="none">
						<path d="M1 8L12 10L22 13L32 15L42 17L50 19L57 22" stroke="#008E48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<div class="daily-kpi-value-row">
				<span class="daily-kpi-big-number">19 h</span>
				<span class="daily-kpi-badge is-success"><i class="fa-solid fa-arrow-down"></i> 28%</span>
			</div>
			<h3 class="daily-kpi-label">Tiempo medio de resolución</h3>
			<p class="daily-kpi-subtext">Promedio general</p>
		</div>
	</section>

	<!-- ============================================================== -->
	<!-- 3. FILA DE ANALÍTICA (4 TARJETAS CON GRÁFICOS INTERACTIVOS)    -->
	<!-- ============================================================== -->
	<section class="daily-analytics-grid-4" aria-label="Analítica de Operaciones">
		<!-- Card 1: Estado general de resolución -->
		<div class="daily-analytics-card">
			<div class="daily-analytics-card-header">
				<h4 class="daily-analytics-title">
					<i class="fa-solid fa-chart-column"></i>
					Estado general de resolución
				</h4>
			</div>
			<div class="daily-donut-with-legend">
				<div class="daily-donut-chart-box">
					<canvas id="chartStatusResolution"></canvas>
					<div class="daily-donut-center-text">
						<span class="daily-donut-center-pct">68%</span>
					</div>
				</div>
				<div class="daily-donut-legend-list">
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #008E48;"></span>Resueltas</span>
						<b>48</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #F59E0B;"></span>En proceso</span>
						<b>38</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #94A3B8;"></span>Sin actividad</span>
						<b>25</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #EF4444;"></span>Vencidas</span>
						<b>16</b>
					</div>
				</div>
			</div>
		</div>

		<!-- Card 2: Alertas por categoría -->
		<div class="daily-analytics-card">
			<div class="daily-analytics-card-header">
				<h4 class="daily-analytics-title">
					<i class="fa-solid fa-chart-pie"></i>
					Alertas por categoría
				</h4>
			</div>
			<div class="daily-donut-with-legend">
				<div class="daily-donut-chart-box">
					<canvas id="chartCategoryAlerts"></canvas>
					<div class="daily-donut-center-text">
						<span class="daily-donut-center-pct" style="font-size: 1.15rem;">127</span>
						<span class="daily-donut-center-label" style="display: block;">Total</span>
					</div>
				</div>
				<div class="daily-donut-legend-list" style="font-size: 0.72rem; gap: 4px;">
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #3B82F6;"></span>Inventario</span>
						<b>32</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #F97316;"></span>Mermas</span>
						<b>28</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #EAB308;"></span>Calidad</span>
						<b>20</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #B45309;"></span>Despachos</span>
						<b>16</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #06B6D4;"></span>Personal</span>
						<b>14</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #008E48;"></span>Ventas</span>
						<b>9</b>
					</div>
					<div class="daily-legend-row">
						<span><span class="daily-dot-legend" style="background: #94A3B8;"></span>Otros</span>
						<b>8</b>
					</div>
				</div>
			</div>
		</div>

		<!-- Card 3: Impacto estimado -->
		<div class="daily-analytics-card">
			<div class="daily-analytics-card-header">
				<h4 class="daily-analytics-title">
					<i class="fa-solid fa-dollar-sign"></i>
					Impacto estimado
				</h4>
			</div>
			<div class="daily-impact-stats-box">
				<div class="daily-impact-val-row">
					<span class="daily-impact-main-amount">$9,747.29</span>
					<span class="daily-kpi-badge is-danger"><i class="fa-solid fa-arrow-up"></i> 12%</span>
				</div>
				<div class="daily-impact-sub">Pérdida potencial<br>En incidencias activas</div>
				<div class="daily-bars-spark-container">
					<canvas id="chartImpactLossBars"></canvas>
				</div>
			</div>
		</div>

		<!-- Card 4: Tendencia de alertas -->
		<div class="daily-analytics-card">
			<div class="daily-analytics-card-header">
				<h4 class="daily-analytics-title">
					<i class="fa-solid fa-chart-line"></i>
					Tendencia de alertas
				</h4>
				<div class="daily-analytics-legend-top">
					<span><span class="daily-dot-legend" style="background: #EF4444;"></span>Creadas</span>
					<span><span class="daily-dot-legend" style="background: #008E48;"></span>Resueltas</span>
				</div>
			</div>
			<div class="daily-trend-chart-box">
				<canvas id="chartAlertsTrend"></canvas>
			</div>
		</div>
	</section>

	<!-- ============================================================== -->
	<!-- 4. BARRA DE PESTAÑAS (FILTROS PILL) Y SELECTOR DE VISTA        -->
	<!-- ============================================================== -->
	<div class="daily-pills-and-views-row">
		<!-- Filtros Pill de la izquierda -->
		<div class="daily-filter-pills-group" id="daily-filter-pills">
			<button type="button" class="daily-filter-pill-btn is-active" data-filter="all">
				<i class="fa-solid fa-layer-group"></i> Activas (0)
			</button>
			<button type="button" class="daily-filter-pill-btn" data-filter="critical">
				<i class="fa-solid fa-triangle-exclamation text-danger"></i> Críticas (27)
			</button>
			<button type="button" class="daily-filter-pill-btn" data-filter="overdue">
				<i class="fa-solid fa-triangle-exclamation text-warning"></i> Vencidas (65)
			</button>
			<button type="button" class="daily-filter-pill-btn" data-filter="unassigned">
				<i class="fa-solid fa-triangle-exclamation" style="color: #F59E0B;"></i> Sin asignar (75)
			</button>
			<button type="button" class="daily-filter-pill-btn" data-filter="in_progress">
				<span class="dot-blue"></span> En proceso (38)
			</button>
			<button type="button" class="daily-filter-pill-btn" data-filter="resolved">
				<i class="fa-solid fa-check text-success"></i> Resueltas (48)
			</button>
		</div>

		<!-- Selector de Modo de Vista (Lista / Kanban / Cronología) -->
		<div class="daily-views-toggle-group">
			<button type="button" class="daily-view-mode-btn is-active" id="btnViewList" data-view="list">
				<i class="fa-solid fa-list"></i> Lista
			</button>
			<button type="button" class="daily-view-mode-btn" id="btnViewKanban" data-view="kanban">
				<i class="fa-solid fa-table-columns"></i> Kanban
			</button>
			<button type="button" class="daily-view-mode-btn" id="btnViewTimeline" data-view="timeline">
				<i class="fa-solid fa-timeline"></i> Cronología
			</button>
		</div>
	</div>

	<!-- ============================================================== -->
	<!-- 5. BARRA DE BÚSQUEDA Y FILTROS DESPLEGABLES                    -->
	<!-- ============================================================== -->
	<div class="daily-search-and-filters-bar">
		<div class="daily-search-input-wrap">
			<i class="fa-solid fa-magnifying-glass"></i>
			<input type="search" class="daily-search-input" id="alcTableSearch" placeholder="Buscar alerta, producto, responsable...">
		</div>

		<select class="daily-filter-select-drop" id="alcFilterCategory">
			<option value="">Todas las categorías</option>
			<option value="Inventario">Inventario</option>
			<option value="Mermas">Mermas</option>
			<option value="Calidad">Calidad</option>
			<option value="Despachos">Despachos</option>
			<option value="Seguridad">Seguridad</option>
			<option value="Ventas">Ventas</option>
			<option value="Personal">Personal</option>
		</select>

		<select class="daily-filter-select-drop" id="alcFilterBranch">
			<option value="">Todas las sucursales</option>
			@foreach($branches as $b)
				<option value="{{ $b['name'] }}">{{ $b['name'] }}</option>
			@endforeach
		</select>

		<select class="daily-filter-select-drop" id="alcFilterStatus">
			<option value="">Todos los estados</option>
			<option value="En investigación">En investigación</option>
			<option value="Pendiente">Pendiente</option>
			<option value="En proceso">En proceso</option>
			<option value="En validación">En validación</option>
			<option value="Resuelta">Resuelta</option>
			<option value="Sin asignar">Sin asignar</option>
		</select>

		<button type="button" class="daily-btn-filter-icon" id="alcMoreFiltersBtn">
			<i class="fa-solid fa-sliders text-muted"></i> Más filtros
		</button>

		<button type="button" class="daily-btn-icon-square" id="alcColumnsBtn" title="Configurar columnas">
			<i class="fa-solid fa-gear"></i>
		</button>
	</div>

	<!-- ============================================================== -->
	<!-- 6. GRID SPLIT PRINCIPAL (TABLA 63% / PANEL DERECHO 37%)        -->
	<!-- ============================================================== -->
	<div class="daily-workspace-split-view" id="dailyWorkspaceArea">

		<!-- ============================================================ -->
		<!-- COLUMNA IZQUIERDA: BANDEJA EN MODO LISTA / KANBAN / CRONOLOGÍA-->
		<!-- ============================================================ -->
		<div class="daily-left-column-area">

			<!-- VISTA 1: TABLA PRINCIPAL DE INCIDENCIAS (MODO LISTA) -->
			<div class="daily-table-container-card" id="viewModeList">
				<div class="daily-table-wrap">
					<table class="daily-incidents-table" id="dailyIncidentsTable">
						<thead>
							<tr>
								<th style="width: 32px;"><input type="checkbox" id="checkAllRows" title="Marcar las alertas de esta página para acciones masivas" aria-label="Marcar las alertas de esta página para acciones masivas"></th>
								<th>PRIORIDAD <i class="fa-solid fa-chevron-down ml-1" style="font-size: 9px;"></i></th>
								<th>ID <i class="fa-solid fa-chevron-right ml-1" style="font-size: 9px;"></i></th>
								<th>INCIDENCIA</th>
								<th>CATEGORÍA</th>
								<th>SUCURSAL</th>
								<th>RESPONSABLE</th>
								<th>TIEMPO</th>
								<th>ESTADO</th>
								<th>IMPACTO</th>
								<th style="width: 28px;"></th>
							</tr>
						</thead>
						<tbody id="incidentsTableBody">
							<!-- Filas generadas desde las alertas registradas (alert_center.js, /api/alerts/list) -->
						</tbody>
					</table>
				</div>

				<!-- Acciones masivas sobre las alertas marcadas con la casilla (independiente de la fila abierta en el detalle) -->
				<div class="daily-bulk-bar" id="alcBulkInfo" style="display: none;">
					<span><i class="fa-regular fa-square-check mr-1"></i><b id="alcBulkCount">0 marcadas</b> para acción masiva</span>
					<button type="button" class="btn btn-sm btn-outline-success" id="alcBulkAssign"><i class="fa-solid fa-user-plus mr-1"></i>Asignar marcadas</button>
					<button type="button" class="btn btn-sm btn-link text-muted" id="alcBulkClear">Desmarcar</button>
				</div>

				<!-- Pie de la tabla con paginación (calculada desde los registros filtrados) -->
				<div class="daily-table-footer-bar">
					<span id="dailyTableSummary" aria-live="polite">Cargando alertas…</span>
					<div class="d-flex align-items-center">
						<div class="daily-pagination-controls" id="dailyPagination" aria-label="Paginación de alertas"></div>
						<select class="daily-page-size-select" id="pageSizeSelect" aria-label="Alertas por página">
							<option value="5">5 por página</option>
							<option value="10" selected>10 por página</option>
							<option value="25">25 por página</option>
						</select>
					</div>
				</div>
			</div>

			<!-- VISTA 2: TABLERO KANBAN (POR ETAPA DE RESOLUCIÓN) -->
			<div class="daily-kanban-board-wrap" id="viewModeKanban" style="display: none;">
				<div class="daily-kanban-col" data-stage="detected">
					<div class="daily-kanban-col-head">
						<span class="daily-kanban-col-name">Detectadas</span>
						<span class="badge badge-light border">15</span>
					</div>
					<div class="daily-kanban-cards-box" id="kanban-detected"></div>
				</div>
				<div class="daily-kanban-col" data-stage="assigned">
					<div class="daily-kanban-col-head">
						<span class="daily-kanban-col-name">Asignadas</span>
						<span class="badge badge-light border">18</span>
					</div>
					<div class="daily-kanban-cards-box" id="kanban-assigned"></div>
				</div>
				<div class="daily-kanban-col" data-stage="investigating">
					<div class="daily-kanban-col-head">
						<span class="daily-kanban-col-name">En investigación</span>
						<span class="badge badge-light border">38</span>
					</div>
					<div class="daily-kanban-cards-box" id="kanban-investigating"></div>
				</div>
				<div class="daily-kanban-col" data-stage="validation">
					<div class="daily-kanban-col-head">
						<span class="daily-kanban-col-name">En validación</span>
						<span class="badge badge-light border">8</span>
					</div>
					<div class="daily-kanban-cards-box" id="kanban-validation"></div>
				</div>
				<div class="daily-kanban-col" data-stage="resolved">
					<div class="daily-kanban-col-head">
						<span class="daily-kanban-col-name">Cerradas / Resueltas</span>
						<span class="badge badge-light border">48</span>
					</div>
					<div class="daily-kanban-cards-box" id="kanban-resolved"></div>
				</div>
			</div>

			<!-- VISTA 3: CRONOLOGÍA DE SLA Y TIEMPOS -->
			<div class="daily-timeline-board-wrap" id="viewModeTimeline" style="display: none;">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<div>
						<h4 class="h6 font-weight-bold text-dark mb-0"><i class="fa-solid fa-clock-rotate-left text-success mr-2"></i>Monitoreo de tiempos y cumplimiento de SLA</h4>
						<small class="text-muted">Proyección cronológica de plazos límite y vencimiento por prioridad.</small>
					</div>
					<span class="badge badge-subtle-danger text-danger font-weight-bold px-2 py-1" id="timelineOverdueBadge"><i class="fa-solid fa-triangle-exclamation mr-1"></i>— fuera de SLA objetivo</span>
				</div>
				<div id="timelineTimelineContent" style="min-height: 380px;"></div>
			</div>

		</div>

		<!-- ============================================================ -->
		<!-- COLUMNA DERECHA: PANEL DERECHO DE SEGUIMIENTO (EXPEDIENTE)   -->
		<!-- ============================================================ -->
		<aside class="daily-right-detail-card" id="dailyDetailPanel" aria-label="Detalle de la alerta seleccionada">
			<!-- Estado vacío: ninguna alerta seleccionada -->
			<div class="daily-panel-empty">
				<i class="fa-regular fa-hand-pointer"></i>
				<b>Sin alerta seleccionada</b>
				<span>Elige una fila de la tabla para ver su resumen, actividad, evidencias y plazo.</span>
			</div>

			<!-- Cierre del detalle en móvil -->
			<button type="button" class="daily-panel-mobile-close" id="panelMobileClose"><i class="fa-solid fa-arrow-left mr-1"></i>Volver a la lista</button>

			<!-- Encabezado del expediente -->
			<div class="daily-detail-top-meta">
				<div class="daily-detail-id-badge-wrap">
					<div class="daily-detail-circle-icon">
						<i class="fa-solid fa-circle-exclamation"></i>
					</div>
					<span class="daily-detail-id-text" id="panelDetailId">#4581</span>
					<span class="daily-prio-badge is-critical" id="panelDetailPriority">▲ Crítica</span>
				</div>
				<div class="dropdown">
					<button class="daily-detail-status-drop dropdown-toggle" type="button" id="panelDetailStatusBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="dot-blue mr-1"></span> <span id="panelDetailStatusText">En investigación</span>
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 small">
						<a class="dropdown-item" href="#" data-set-status="investigating"><i class="fa-solid fa-magnifying-glass text-primary mr-2"></i>En investigación</a>
						<a class="dropdown-item" href="#" data-set-status="pending"><i class="fa-solid fa-hourglass-start text-warning mr-2"></i>Pendiente</a>
						<a class="dropdown-item" href="#" data-set-status="in_progress"><i class="fa-solid fa-play text-info mr-2"></i>En proceso</a>
						<a class="dropdown-item" href="#" data-set-status="validation"><i class="fa-solid fa-check-double text-success mr-2"></i>En validación</a>
						<a class="dropdown-item text-success font-weight-bold" href="#" data-set-status="resolved"><i class="fa-solid fa-circle-check mr-2"></i>Resuelta (Cerrar)</a>
					</div>
				</div>
			</div>

			<!-- Título y subtítulo de la incidencia seleccionada -->
			<h2 class="daily-detail-title" id="panelDetailTitle">Falla de sincronización POS ↔ Kardex</h2>
			<p class="daily-detail-subtitle" id="panelDetailSubtitle">43 casos agrupados</p>
			<a class="btn btn-sm btn-outline-success mb-2" id="panelDetailSource" href="#" style="display:none;"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Abrir registro de origen</a>

			<!-- Pestañas internas del expediente -->
			<div class="daily-detail-tabs-bar">
				<button type="button" class="daily-detail-tab-btn is-active" data-panel-tab="summary">Resumen</button>
				<button type="button" class="daily-detail-tab-btn" data-panel-tab="activity">Actividad</button>
				<button type="button" class="daily-detail-tab-btn" data-panel-tab="evidence">Evidencias</button>
				<button type="button" class="daily-detail-tab-btn" data-panel-tab="products" id="panelTabProductsBtn">Productos (43)</button>
				<button type="button" class="daily-detail-tab-btn" data-panel-tab="actions">Acciones</button>
			</div>

			<!-- CONTENIDO DE PESTAÑA: RESUMEN (DEFAULT DE LA FOTO) -->
			<div class="daily-panel-tab-content" id="panelContentSummary">
				<div class="daily-detail-2cols-body">
					<!-- Columna izquierda: Datos generales -->
					<div class="daily-detail-facts-list">
						<div class="daily-fact-item">
							<i class="fa-solid fa-store"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Sucursal</span>
								<span class="daily-fact-val" id="panelFactBranch">Hallandale Beach</span>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-solid fa-boxes-stacked"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Categoría</span>
								<span class="daily-fact-val" id="panelFactCategory">Inventario / Integración</span>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-regular fa-calendar"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Creada</span>
								<span class="daily-fact-val" id="panelFactCreated">27 oct. 2026 - 10:22</span>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-regular fa-clock"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Última actividad</span>
								<span class="daily-fact-val" id="panelFactActivity">27 oct. 2026 - 12:37</span>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-solid fa-dollar-sign"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Impacto estimado</span>
								<span class="daily-fact-val is-red" id="panelFactImpact">$4,820.00</span>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-regular fa-user"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Responsable</span>
								<div class="d-flex align-items-center gap-2 mt-1">
									<div class="daily-avatar-circle" id="panelFactAvatar" style="background: #F59E0B; width: 22px; height: 22px; font-size: 9px;">JM</div>
									<div>
										<span class="daily-fact-val d-block" id="panelFactOwnerName" style="font-size: 0.74rem;">Juan Morales</span>
										<small class="text-muted" id="panelFactOwnerRole">Supervisor</small>
									</div>
								</div>
							</div>
						</div>
						<div class="daily-fact-item">
							<i class="fa-solid fa-hourglass-half"></i>
							<div class="daily-fact-content">
								<span class="daily-fact-label">Tiempo transcurrido</span>
								<span class="daily-fact-val" id="panelFactElapsed">2 h 15 m <span class="text-danger small font-weight-bold" id="panelFactDueNotice">(Vence en 1 h)</span></span>
							</div>
						</div>
					</div>

					<!-- Columna derecha: Stepper de progreso de resolución -->
					<div class="daily-stepper-box">
						<div class="daily-stepper-title">
							<i class="fa-solid fa-route text-success"></i>
							Progreso de resolución
						</div>
						<div class="daily-stepper-list" id="panelStepperList">
							<!-- Paso 1 -->
							<div class="daily-step-item is-completed" id="step1">
								<div class="daily-step-circle"><i class="fa-solid fa-check"></i></div>
								<div class="daily-step-texts">
									<span class="daily-step-name">Detectada</span>
									<span class="daily-step-time">27 oct. 10:22</span>
								</div>
							</div>
							<!-- Paso 2 -->
							<div class="daily-step-item is-completed" id="step2">
								<div class="daily-step-circle"><i class="fa-solid fa-check"></i></div>
								<div class="daily-step-texts">
									<span class="daily-step-name">Asignada</span>
									<span class="daily-step-time">27 oct. 10:35</span>
								</div>
							</div>
							<!-- Paso 3 -->
							<div class="daily-step-item is-current" id="step3">
								<div class="daily-step-circle">3</div>
								<div class="daily-step-texts">
									<span class="daily-step-name">Investigación</span>
									<span class="daily-step-time">En curso (2 h 15 m)</span>
								</div>
							</div>
							<!-- Paso 4 -->
							<div class="daily-step-item is-pending" id="step4">
								<div class="daily-step-circle">4</div>
								<div class="daily-step-texts">
									<span class="daily-step-name">Validación</span>
									<span class="daily-step-time">Pendiente</span>
								</div>
							</div>
							<!-- Paso 5 -->
							<div class="daily-step-item is-pending" id="step5">
								<div class="daily-step-circle">5</div>
								<div class="daily-step-texts">
									<span class="daily-step-name">Cerrada</span>
									<span class="daily-step-time">Pendiente</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Tarjeta Callout: SLA en Riesgo -->
				<div class="daily-sla-callout-card" id="panelSlaCard">
					<div class="daily-sla-callout-left">
						<i class="fa-solid fa-triangle-exclamation"></i>
						<div>
							<h5 class="daily-sla-title" id="panelSlaTitle">▲ SLA en riesgo</h5>
							<p class="daily-sla-desc" id="panelSlaDesc">Tiempo límite: 3 horas para incidencias críticas.</p>
						</div>
					</div>
					<div class="daily-sla-callout-right">
						<div class="daily-sla-rem-lbl">Queda</div>
						<div class="daily-sla-rem-val" id="panelSlaRemaining">45 min</div>
					</div>
				</div>
			</div>

			<!-- CONTENIDO DE PESTAÑA: ACTIVIDAD -->
			<div class="daily-panel-tab-content" id="panelContentActivity" style="display: none;">
				<div class="p-2" id="panelActivityList" style="max-height: 290px; overflow-y: auto;">
					<!-- Lista de eventos temporales -->
				</div>
			</div>

			<!-- CONTENIDO DE PESTAÑA: EVIDENCIAS -->
			<div class="daily-panel-tab-content" id="panelContentEvidence" style="display: none;">
				<div class="p-2" id="panelEvidenceList" style="max-height: 290px; overflow-y: auto;">
					<!-- Documentos y fotos adjuntas -->
				</div>
			</div>

			<!-- CONTENIDO DE PESTAÑA: PRODUCTOS (43) -->
			<div class="daily-panel-tab-content" id="panelContentProducts" style="display: none;">
				<div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
					<table class="table table-sm table-hover mb-0 text-sm">
						<thead class="thead-light">
							<tr>
								<th>Código</th>
								<th>Producto</th>
								<th>Teórico</th>
								<th>POS</th>
								<th class="text-right">Diferencia</th>
							</tr>
						</thead>
						<tbody id="panelProductsTableBody">
							<!-- Filas de productos agrupados -->
						</tbody>
					</table>
				</div>
			</div>

			<!-- CONTENIDO DE PESTAÑA: ACCIONES RECOMENDADAS -->
			<div class="daily-panel-tab-content" id="panelContentActions" style="display: none;">
				<div class="p-2" id="panelActionsChecklist" style="max-height: 290px; overflow-y: auto;">
					<!-- Checklist de resolución -->
				</div>
			</div>

			<!-- Botones de Acción de la Incidencia -->
			<div class="daily-detail-actions-bar">
				<button type="button" class="daily-btn-register-action" id="btnRegisterAction">
					<i class="fa-solid fa-pen-to-square"></i> Registrar acción
				</button>
				<button type="button" class="daily-btn-outline-white" id="btnAttachEvidence">
					<i class="fa-solid fa-camera"></i> Adjuntar evidencia
				</button>
				<button type="button" class="daily-btn-outline-white" id="btnAssignResponsible">
					<i class="fa-solid fa-user-plus"></i> Asignar
				</button>
				<div class="dropdown d-inline-block">
					<button type="button" class="daily-btn-outline-white dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						Más
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 small">
						<a class="dropdown-item" href="#" id="menuActInvestigate"><i class="fa-solid fa-magnifying-glass text-primary mr-2"></i>Iniciar investigación</a>
						<a class="dropdown-item" href="#" id="menuActApproval"><i class="fa-solid fa-stamp text-warning mr-2"></i>Solicitar aprobación</a>
						<a class="dropdown-item" href="#" id="menuActEscalate"><i class="fa-solid fa-arrow-up-right-dots text-danger mr-2"></i>Escalar a nivel superior</a>
						<a class="dropdown-item" href="#" id="menuActComment"><i class="fa-solid fa-comment text-muted mr-2"></i>Añadir nota o comentario</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item text-danger" href="#" id="menuActDismiss"><i class="fa-solid fa-ban mr-2"></i>Descartar (falso positivo)</a>
					</div>
				</div>
			</div>
		</aside>

	</div>

</div>

<!-- Reglas de detección y automatizaciones (SI evento + condición → ENTONCES acción) -->
<div class="modal fade" id="alcRulesModal" tabindex="-1" role="dialog" aria-labelledby="alcRulesModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
		<div class="modal-content border-0">
			<div class="modal-header">
				<h5 class="modal-title" id="alcRulesModalTitle"><i class="fa-solid fa-gear mr-2 text-success"></i>Reglas y automatizaciones</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body small">
				<ul class="nav nav-tabs mb-3" role="tablist">
					<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#alcTabAuto" role="tab">Automatizaciones</a></li>
					<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#alcTabDetect" role="tab">Reglas de detección</a></li>
					<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#alcTabExec" role="tab">Historial de ejecuciones</a></li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane fade show active" id="alcTabAuto" role="tabpanel">
						<p class="text-muted mb-2">SI ocurre el evento y se cumple la condición → ENTONCES se ejecuta la acción. Cada evento se procesa una sola vez por automatización; las fallas se reintentan.</p>
						<div class="table-responsive"><table class="table table-sm table-hover mb-2"><thead><tr><th>Activa</th><th>Automatización</th><th>SI (evento · condición)</th><th>ENTONCES</th><th>Alcance</th><th class="text-right">Ejecuciones</th><th></th></tr></thead><tbody id="alcAutoRows"></tbody></table></div>
						<button type="button" class="daily-btn-primary-green" id="alcAutoNew"><i class="fa-solid fa-plus mr-1"></i>Nueva automatización</button>
						<form id="alcAutoForm" class="border rounded p-3 mt-3 d-none" autocomplete="off">
							<input type="hidden" name="id">
							<div class="form-row">
								<div class="form-group col-md-6"><label>Nombre</label><input class="form-control form-control-sm" name="name" required minlength="4"></div>
								<div class="form-group col-md-3"><label>Prioridad</label><select class="form-control form-control-sm" name="priority"><option>P1</option><option>P2</option><option selected>P3</option><option>P4</option></select></div>
								<div class="form-group col-md-3 d-flex align-items-end"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="alcAutoActive" name="is_active" value="1" checked><label class="custom-control-label" for="alcAutoActive">Activa</label></div></div>
							</div>
							<div class="form-group"><label>Descripción</label><input class="form-control form-control-sm" name="description"></div>
							<div class="form-row">
								<div class="form-group col-md-4"><label>SI ocurre (evento)</label><select class="form-control form-control-sm" name="event_trigger"></select></div>
								<div class="form-group col-md-8"><label>Condición (JSON) <span class="text-muted">p. ej. {"rule_code": "count_difference", "value": "&gt;=2"}</span></label><input class="form-control form-control-sm text-monospace" name="condition_json"></div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-4"><label>ENTONCES (acción)</label><select class="form-control form-control-sm" name="action_type"></select></div>
								<div class="form-group col-md-8"><label>Parámetros (JSON) <span class="text-muted">título con {title}, due_hours, task_type, priority, to: managers|owner</span></label><input class="form-control form-control-sm text-monospace" name="action_payload_json"></div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-3"><label>Sucursal</label><select class="form-control form-control-sm" name="branch_id"><option value="">Todas</option></select></div>
								<div class="form-group col-md-3"><label>Departamento</label><input class="form-control form-control-sm" name="department" placeholder="Todos"></div>
								<div class="form-group col-md-3"><label>Responsable</label><select class="form-control form-control-sm" name="owner_user_id"><option value="">El de la alerta o la sucursal</option></select></div>
								<div class="form-group col-md-3"><label>Horario (desde · hasta)</label><div class="d-flex"><input type="time" class="form-control form-control-sm mr-1" name="active_from"><input type="time" class="form-control form-control-sm" name="active_to"></div></div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-3"><label>Enfriamiento (min)</label><input type="number" min="0" class="form-control form-control-sm" name="cooldown_minutes" value="0"></div>
								<div class="form-group col-md-3"><label>Intentos máximos</label><input type="number" min="1" max="10" class="form-control form-control-sm" name="max_retries" value="3"></div>
							</div>
							<button type="submit" class="daily-btn-primary-green">Guardar</button>
							<button type="button" class="btn btn-sm btn-light ml-2" id="alcAutoCancel">Cancelar</button>
							<div id="alcAutoChanges" class="mt-3"></div>
						</form>
					</div>
					<div class="tab-pane fade" id="alcTabDetect" role="tabpanel">
						<p class="text-muted mb-2">Condiciones que abren alertas al escanear. Los cambios quedan en el historial de la regla.</p>
						<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Activa</th><th>Regla</th><th>Umbral</th><th>Severidad</th><th>Plazo (h)</th><th>Escalar tras (h)</th><th>Enfriamiento (h)</th><th></th></tr></thead><tbody id="alcDetectRows"></tbody></table></div>
					</div>
					<div class="tab-pane fade" id="alcTabExec" role="tabpanel">
						<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Fecha</th><th>Automatización</th><th>Evento · entidad</th><th>Sucursal</th><th>Estado</th><th>Resultado</th><th></th></tr></thead><tbody id="alcExecRows"></tbody></table></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Inclusión de Escáner y Scripts -->
@include('components.camerabarcodescanner')
@stop

{{-- Una sola carga del script, después de jQuery y sin caché obsoleta (antes se cargaba dos veces y la primera fallaba) --}}
@php $viewJsExists = false; @endphp
@push('pageScripts')
<script src="{{ $U('/viewjs/alert_center.js?v=', true) }}{{ time() }}"></script>
@endpush
