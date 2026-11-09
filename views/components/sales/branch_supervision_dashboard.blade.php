@php
	$dash = $branchSupervisionDashboard ?? [];
	$k = $dash['kpis'] ?? [];
	$branches = $dash['branches'] ?? [];
	$selectedBranch = $dash['selected_branch'] ?? ($branches[0] ?? []);
	$rankingBhi = $dash['ranking_bhi'] ?? [];
	$supervisionMatrix = $dash['supervision_matrix'] ?? [];
	$incidents = $dash['incidents'] ?? [];
	$patterns = $dash['multisite_patterns'] ?? [];
	$interventionCounters = $dash['intervention_counters'] ?? [];
	$interventions = $dash['interventions'] ?? [];
	$responsibleFollowup = $dash['responsible_followup'] ?? [];
	$comparatorBranches = $dash['comparator_branches'] ?? [];
	$visitLog = $dash['visit_log'] ?? [];
@endphp

<div class="branch-dash-wrapper">
	<!-- ====================================================================
	     ENCABEZADO COMERCIAL — EXACTAMENTE EQUILIBRADO CON TABLERO Y PRODUCTOS
	     ==================================================================== -->
	<header class="branch-dash-header">
		<div class="branch-dash-header-left">
			<h1 class="branch-dash-title">Sucursales</h1>
			<p class="branch-dash-subtitle">Centro de gestión multisucursal: qué tienda está mal, qué ocurre, quién lo resuelve y si quedó solucionado.</p>
		</div>
		<div class="branch-dash-header-right">
			<span class="branch-dash-mockup-badge">Mockup con datos demo · sin direcciones ni coordenadas reales</span>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA DE DAILY (NAVEGACIÓN DIRECTA ENTRE MÓDULOS)
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Ventas y Rentabilidad">
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-branch">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-branch">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Rendimiento por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-branch">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-branch" aria-selected="true">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-branch">
			<i class="fa-solid fa-list-ul mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-branch">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-branch">
			<i class="fa-solid fa-tags mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-branch">
			<i class="fa-solid fa-bullhorn mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>

	<!-- ====================================================================
	     FILTROS ENCABEZADO — 8 SELECTORES REACTIVOS
	     ==================================================================== -->
	<div class="branch-filters-bar mb-3">
		<div class="branch-filter-item">
			<label class="branch-filter-label">Periodo</label>
			<select class="branch-filter-select" id="branchFilterPeriod">
				<option value="current" selected>08/10 – 06/11/2026</option>
				<option value="30d">Últimos 30 días</option>
				<option value="month">Este mes</option>
				<option value="quarter">Trimestre móvil</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Región</label>
			<select class="branch-filter-select" id="branchFilterRegion">
				<option value="all" selected>Todas</option>
				<option value="costa">Costa Este</option>
				<option value="norte">Norte</option>
				<option value="centro">Centro</option>
				<option value="oeste">Oeste</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Ciudad</label>
			<select class="branch-filter-select" id="branchFilterCity">
				<option value="all" selected>Todas</option>
				<option value="Hallandale Beach">Hallandale Beach</option>
				<option value="Miami Beach">Miami Beach</option>
				<option value="Sunny Isles Beach">Sunny Isles</option>
				<option value="Aventura">Aventura</option>
				<option value="North Miami">North Miami</option>
				<option value="Miami">Miami Centro</option>
				<option value="Hollywood">Hollywood</option>
				<option value="Fort Lauderdale">Fort Lauderdale</option>
				<option value="Doral">Doral</option>
				<option value="Hialeah">Hialeah</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Sucursal</label>
			<select class="branch-filter-select" id="branchFilterStore">
				<option value="all" selected>Todas (10)</option>
				@foreach($branches as $b)
				<option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
				@endforeach
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Gerente responsable</label>
			<select class="branch-filter-select" id="branchFilterManager">
				<option value="all" selected>Todos</option>
				<option value="Gerente 02">Gerente 02</option>
				<option value="Gerente 03">Gerente 03</option>
				<option value="Gerente 04">Gerente 04</option>
				<option value="Gerente 05">Gerente 05</option>
				<option value="Supervisor 05">Supervisor 05</option>
				<option value="Supervisor 06">Supervisor 06</option>
				<option value="Supervisor 07">Supervisor 07</option>
				<option value="Gerente 08">Gerente 08</option>
				<option value="Gerente 09">Gerente 09</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Estado operativo</label>
			<select class="branch-filter-select" id="branchFilterStatus">
				<option value="all" selected>Todos</option>
				<option value="operativa">Operativa (4)</option>
				<option value="atencion">En atención (3)</option>
				<option value="critico">Crítica (2)</option>
				<option value="sin_datos">Sin información (1)</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Prioridad</label>
			<select class="branch-filter-select" id="branchFilterPriority">
				<option value="all" selected>Todas</option>
				<option value="alta">Alta</option>
				<option value="media">Media</option>
				<option value="baja">Baja</option>
				<option value="critica">Crítica</option>
			</select>
		</div>
		<div class="branch-filter-item">
			<label class="branch-filter-label">Tipo de incidencia</label>
			<select class="branch-filter-select" id="branchFilterIncType">
				<option value="all" selected>Todas</option>
				<option value="Operativo">Operativo</option>
				<option value="Inventario">Inventario</option>
				<option value="Personal">Personal</option>
				<option value="Despachos">Despachos</option>
				<option value="Mermas">Mermas</option>
				<option value="Prevención">Prevención</option>
				<option value="Sistemas">Sistemas</option>
			</select>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 1 — SIETE INDICADORES KPI
	     ==================================================================== -->
	<div class="branch-kpis-grid mb-3">
		<!-- KPI 1: Supervisadas -->
		<div class="branch-kpi-card" data-kpi-filter="all" title="Ver todas las tiendas del registro maestro">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Sucursales supervisadas</span>
				<div class="branch-kpi-icon icon-gray"><i class="fa-solid fa-store"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiSupervised">{{ $k['supervised_count'] ?? 10 }}</div>
			<div class="branch-kpi-sub">registro maestro</div>
		</div>

		<!-- KPI 2: Operativas -->
		<div class="branch-kpi-card" data-kpi-filter="operativa" title="Filtrar sucursales operativas dentro de parámetros">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Sucursales operativas</span>
				<div class="branch-kpi-icon icon-green"><i class="fa-regular fa-circle-check"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiOperational">{{ $k['operational_count'] ?? 4 }}</div>
			<div class="branch-kpi-sub">dentro de parámetros</div>
		</div>

		<!-- KPI 3: En atención -->
		<div class="branch-kpi-card" data-kpi-filter="atencion" title="Filtrar sucursales que requieren seguimiento">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Sucursales en atención</span>
				<div class="branch-kpi-icon icon-amber"><i class="fa-solid fa-circle-exclamation"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiAttention">{{ $k['attention_count'] ?? 3 }}</div>
			<div class="branch-kpi-sub">requieren seguimiento</div>
		</div>

		<!-- KPI 4: Críticas -->
		<div class="branch-kpi-card" data-kpi-filter="critico" title="Filtrar sucursales en estado crítico">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Sucursales críticas</span>
				<div class="branch-kpi-icon icon-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiCritical">{{ $k['critical_count'] ?? 2 }}</div>
			<div class="branch-kpi-sub text-danger font-weight-600">+1 sin información</div>
		</div>

		<!-- KPI 5: Incidencias multisucursal -->
		<div class="branch-kpi-card" data-kpi-filter="incidents" title="Ver detalle de incidencias abiertas">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Incidencias multisucursal</span>
				<div class="branch-kpi-icon icon-blue"><i class="fa-solid fa-list-check"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiIncidents">{{ $k['multisite_incidents_count'] ?? 23 }}</div>
			<div class="branch-kpi-sub">abiertas · 6 afectan a 2+ tiendas</div>
		</div>

		<!-- KPI 6: Cumplimiento de acciones -->
		<div class="branch-kpi-card" data-kpi-filter="actions" title="Ver porcentaje de acciones cerradas a tiempo">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title">Cumplimiento de acciones</span>
				<div class="branch-kpi-icon icon-teal"><i class="fa-regular fa-clipboard"></i></div>
			</div>
			<div class="branch-kpi-value" id="kpiCompliance">{{ $k['action_compliance_pct'] ?? 78 }} %</div>
			<div class="branch-kpi-sub">cerradas dentro del plazo</div>
		</div>

		<!-- KPI 7: Tiendas que requieren intervención (Fondo verde sólido) -->
		<div class="branch-kpi-card branch-kpi-card-green" data-kpi-filter="interventions" title="Sucursales prioritarias para intervención del Manager">
			<div class="branch-kpi-header">
				<span class="branch-kpi-title text-white">Tiendas que requieren intervención</span>
				<div class="branch-kpi-icon icon-white"><i class="fa-solid fa-flag"></i></div>
			</div>
			<div class="branch-kpi-value text-white" id="kpiIntervention">{{ $k['require_intervention_count'] ?? 4 }}</div>
			<div class="branch-kpi-sub text-white-50">decisión del Manager</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 2 — MAPA INTERACTIVO (60%) Y RANKING BHI (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- MAPA INTERACTIVO DE SUCURSALES (~60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header">
					<div>
						<h2 class="branch-panel-title">Mapa interactivo de sucursales</h2>
						<p class="branch-panel-subtitle">Clic en un marcador abre su tarjeta. Posiciones ilustrativas.</p>
					</div>
					<div class="branch-view-toggle">
						<button type="button" class="branch-toggle-btn active" id="btnViewMap"><i class="fa-solid fa-map mr-1"></i>Mapa</button>
						<button type="button" class="branch-toggle-btn" id="btnViewCards"><i class="fa-solid fa-table-cells mr-1"></i>Tarjetas</button>
					</div>
				</div>

				<!-- CONTENIDO DEL MAPA INTERACTIVO -->
				<div class="branch-map-wrapper" id="branchMapContainer">
					<div class="branch-map-canvas" id="branchMapCanvas">
						<!-- Rutas interconectadas de fondo (SVG) -->
						<svg class="branch-map-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
							<line x1="16" y1="32" x2="42" y2="35" class="branch-route-line"></line>
							<line x1="34" y1="22" x2="42" y2="35" class="branch-route-line"></line>
							<line x1="42" y1="35" x2="48" y2="28" class="branch-route-line"></line>
							<line x1="48" y1="28" x2="55" y2="38" class="branch-route-line"></line>
							<line x1="42" y1="35" x2="38" y2="45" class="branch-route-line"></line>
							<line x1="38" y1="45" x2="25" y2="52" class="branch-route-line"></line>
							<line x1="38" y1="45" x2="45" y2="60" class="branch-route-line"></line>
							<line x1="45" y1="60" x2="58" y2="66" class="branch-route-line"></line>
							<line x1="45" y1="60" x2="20" y2="68" class="branch-route-line"></line>
						</svg>

						<!-- Puntos de sucursales interactivos -->
						@foreach($branches as $b)
						<div class="branch-map-node node-{{ $b['status'] }} @if($b['id'] == 1) is-selected @endif"
							 style="left: {{ $b['map_coords']['x'] }}%; top: {{ $b['map_coords']['y'] }}%;"
							 data-branch-id="{{ $b['id'] }}"
							 data-branch-name="{{ $b['name'] }}"
							 title="{{ $b['name'] }} ({{ $b['status_label'] }})">
							<div class="branch-node-dot"></div>
							<span class="branch-node-label">{{ $b['name'] }}</span>
						</div>
						@endforeach

						<!-- Tarjeta flotante interactiva de sucursal seleccionada (Mockup: Hallandale Beach) -->
						<div class="branch-floating-card" id="branchFloatingCard" style="left: 56%; top: 12%;">
							<div class="branch-float-header">
								<div>
									<h4 class="branch-float-title" id="floatName">Hallandale Beach</h4>
									<div class="small text-muted" id="floatMeta">Hallandale Beach</div>
								</div>
								<span class="branch-status-pill badge-atencion" id="floatStatus">Atención</span>
							</div>
							<div class="branch-float-body">
								<div class="branch-float-row">
									<span class="text-muted">Gerente:</span>
									<strong id="floatManager">Gerente 02</strong>
								</div>
								<div class="branch-float-row">
									<span class="text-muted">BHI:</span>
									<strong class="text-dark" id="floatBhi">63</strong>
								</div>
								<div class="branch-float-row">
									<span class="text-muted">Incidencias activas:</span>
									<strong class="text-danger" id="floatIncidents">5</strong>
								</div>
								<div class="branch-float-row">
									<span class="text-muted">Acciones pendientes:</span>
									<strong id="floatActions">4 (2 vencidas)</strong>
								</div>
								<div class="branch-float-row small text-muted pt-1">
									<span>Actualizado:</span>
									<span id="floatUpdated">06/11 16:59</span>
								</div>
							</div>
							<div class="branch-float-footer">
								<button type="button" class="btn btn-sm btn-success btn-block font-weight-bold" id="btnFloatOpenBranch">
									<i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Abrir sucursal
								</button>
							</div>
						</div>
					</div>

					<!-- Leyenda del mapa -->
					<div class="branch-map-legend">
						<div class="branch-legend-items">
							<span class="legend-item"><span class="legend-dot dot-green"></span>Estable</span>
							<span class="legend-item"><span class="legend-dot dot-amber"></span>Requiere atención</span>
							<span class="legend-item"><span class="legend-dot dot-red"></span>Crítica</span>
							<span class="legend-item"><span class="legend-dot dot-gray"></span>Sin información suficiente</span>
						</div>
						<div class="branch-legend-note">Mapa ilustrativo, sin coordenadas reales</div>
					</div>
				</div>

				<!-- VISTA ALTERNATIVA EN TARJETAS (OCULTA POR DEFECTO) -->
				<div class="branch-cards-grid d-none" id="branchCardsGrid">
					@foreach($branches as $b)
					<div class="branch-grid-card border-{{ $b['status'] }}" data-branch-id="{{ $b['id'] }}">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<h5 class="font-weight-bold mb-0 text-dark">{{ $b['name'] }}</h5>
							<span class="branch-status-pill {{ $b['status_badge_class'] }}">{{ $b['status_label'] }}</span>
						</div>
						<div class="small text-muted mb-2">{{ $b['city'] }} · {{ $b['manager'] }}</div>
						<div class="d-flex justify-content-between align-items-center mb-1 small">
							<span>BHI:</span>
							<strong>{{ $b['bhi'] !== null ? $b['bhi'] : '—' }}</strong>
						</div>
						<div class="d-flex justify-content-between align-items-center mb-1 small">
							<span>Incidencias activas:</span>
							<span class="text-danger font-weight-bold">{{ $b['active_incidents'] }}</span>
						</div>
						<div class="d-flex justify-content-between align-items-center mb-2 small">
							<span>Acciones vencidas:</span>
							<span class="text-warning font-weight-bold">{{ $b['overdue_actions'] }}</span>
						</div>
						<button type="button" class="btn btn-xs btn-outline-success btn-block btn-select-branch-card" data-branch-id="{{ $b['id'] }}">
							Seleccionar
						</button>
					</div>
					@endforeach
				</div>
			</div>
		</div>

		<!-- RANKING BHI (~40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header flex-column align-items-start pb-2">
					<div class="d-flex justify-content-between align-items-center w-100 mb-1">
						<h2 class="branch-panel-title">Ranking BHI</h2>
						<button type="button" class="btn btn-xs btn-link text-muted p-0" id="btnOpenBhiWeights" title="Configurar ponderaciones del BHI">
							<i class="fa-solid fa-sliders mr-1"></i>Ponderaciones
						</button>
					</div>
					<p class="branch-panel-subtitle mb-2">Branch Health Index · 0 a 100 · ponderaciones configurables</p>
					
					<!-- Píldoras de filtro de Ranking -->
					<div class="branch-ranking-pills" id="rankingPillsBar">
						<button type="button" class="ranking-pill active" data-sort="best">Mejor desempeño</button>
						<button type="button" class="ranking-pill" data-sort="worst">Peor desempeño</button>
						<button type="button" class="ranking-pill" data-sort="risk">Mayor riesgo</button>
						<button type="button" class="ranking-pill" data-sort="improvement">Mayor mejora</button>
						<button type="button" class="ranking-pill" data-sort="deterioration">Mayor deterioro</button>
					</div>
				</div>

				<div class="table-responsive branch-table-wrap">
					<table class="table branch-table mb-0" id="tableRankingBhi">
						<thead>
							<tr>
								<th style="width: 30px;">#</th>
								<th>Sucursal</th>
								<th class="text-center">BHI</th>
								<th>Estado</th>
								<th class="text-center" title="Incidencias críticas">Crít.</th>
								<th class="text-center" title="Acciones vencidas">Venc.</th>
								<th class="text-center">Tend.</th>
							</tr>
						</thead>
						<tbody id="rankingBhiTbody">
							@foreach($rankingBhi as $rb)
							<tr class="branch-rank-row @if($rb['real_branch_id'] == 1) is-selected @endif" data-branch-id="{{ $rb['real_branch_id'] }}">
								<td class="font-weight-600 text-muted">{{ $rb['rank'] }}</td>
								<td class="font-weight-bold text-dark">{{ $rb['branch_name'] }}</td>
								<td class="text-center font-weight-700">{{ $rb['bhi'] !== null ? $rb['bhi'] : '—' }}</td>
								<td><span class="branch-status-pill {{ $rb['status_badge'] }}">{{ $rb['status'] }}</span></td>
								<td class="text-center {{ ($rb['critical_incidents'] ?? 0) > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
									{{ $rb['critical_incidents'] !== null ? $rb['critical_incidents'] : '—' }}
								</td>
								<td class="text-center {{ ($rb['overdue_actions'] ?? 0) > 0 ? 'text-warning font-weight-bold' : 'text-muted' }}">
									{{ $rb['overdue_actions'] !== null ? $rb['overdue_actions'] : '—' }}
								</td>
								<td class="text-center {{ $rb['trend_class'] }} font-weight-600">{{ $rb['trend'] }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="branch-card-footer small text-muted">
					<i class="fa-solid fa-circle-info mr-1"></i>Sucursal 10: sin BHI por cobertura de datos de 40 %.
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 3 — MATRIZ DE SUPERVISIÓN (60%) Y FICHA 360° (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- MATRIZ DE SUPERVISIÓN MULTISUCURSAL (~60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header">
					<div>
						<h2 class="branch-panel-title">Matriz de supervisión multisucursal</h2>
						<p class="branch-panel-subtitle">Estado de cada proceso por tienda (sin copiar indicadores internos). Clic en una celda para ver las incidencias que lo justifican y abrir el módulo.</p>
					</div>
				</div>

				<div class="table-responsive branch-table-wrap">
					<table class="table branch-matrix-table mb-0" id="matrixSupervisionTable">
						<thead>
							<tr>
								<th>Sucursal</th>
								<th class="text-center">Operación</th>
								<th class="text-center">Inventario</th>
								<th class="text-center">Personal</th>
								<th class="text-center">Despachos</th>
								<th class="text-center">Mermas</th>
								<th class="text-center">Prevención</th>
								<th class="text-center">Cumplim.</th>
							</tr>
						</thead>
						<tbody>
							@foreach($supervisionMatrix as $sm)
							<tr class="branch-matrix-row @if(($sm['selected'] ?? false) || $sm['branch_id'] == 1) is-selected @endif" data-branch-id="{{ $sm['branch_id'] }}">
								<td class="font-weight-bold text-dark text-nowrap">{{ $sm['branch_name'] }}</td>
								@foreach(['operacion' => 'Operación', 'inventario' => 'Inventario', 'personal' => 'Personal', 'despachos' => 'Despachos', 'mermas' => 'Mermas', 'prevencion' => 'Prevención', 'cumplimiento' => 'Cumplimiento'] as $pkey => $plabel)
								@php $p = $sm['processes'][$pkey] ?? ['status' => 'normal', 'label' => 'Normal', 'detail' => '']; @endphp
								<td class="text-center">
									<button type="button" class="matrix-cell-btn cell-{{ $p['status'] }} @if($p['highlight'] ?? false) is-highlight @endif"
											data-branch="{{ $sm['branch_name'] }}"
											data-branch-id="{{ $sm['branch_id'] }}"
											data-process="{{ $plabel }}"
											data-process-key="{{ $pkey }}"
											data-status="{{ $p['status'] }}"
											data-detail="{{ $p['detail'] }}">
										{{ $p['label'] }}
									</button>
								</td>
								@endforeach
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<div class="branch-map-legend pt-2">
					<div class="branch-legend-items">
						<span class="legend-item"><span class="legend-dot dot-green"></span>Normal</span>
						<span class="legend-item"><span class="legend-dot dot-amber"></span>Atención</span>
						<span class="legend-item"><span class="legend-dot dot-red"></span>Crítico</span>
						<span class="legend-item"><span class="legend-dot dot-gray"></span>Sin datos</span>
					</div>
				</div>
			</div>
		</div>

		<!-- FICHA 360° DE SUCURSAL (~40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="branch-card-panel h-100" id="panelFicha360">
				<div class="branch-card-header pb-1">
					<div>
						<h2 class="branch-panel-title" id="fichaTitle">Ficha 360° · Hallandale Beach</h2>
						<p class="branch-panel-subtitle">Panel lateral. Los accesos abren los módulos originales con la sucursal preseleccionada.</p>
					</div>
				</div>

				<div class="ficha-store-meta mb-3">
					<div class="small text-muted" id="fichaAddress">Código y dirección: DIR-HB-01 · 1000 E Hallandale Beach Blvd (registro maestro)</div>
					<div class="small text-dark font-weight-600" id="fichaRegionManager">
						Región: <span id="fichaRegion">Costa Este</span> · <span id="fichaManager">Gerente 02</span> · <span class="branch-status-pill badge-atencion" id="fichaStatusBadge">Atención</span>
					</div>
				</div>

				<!-- Bloques de métricas 360° (3 filas de 2 chips) -->
				<div class="ficha-kpis-grid mb-3">
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">BHI</div>
						<div class="ficha-kpi-val"><span id="fichaBhiVal">63</span> <span class="trend-down font-weight-bold small" id="fichaBhiTrend">▼ 6</span></div>
					</div>
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">Incidencias</div>
						<div class="ficha-kpi-val text-danger" id="fichaIncidentsVal">5</div>
					</div>
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">Acciones venc.</div>
						<div class="ficha-kpi-val text-warning" id="fichaOverdueVal">2</div>
					</div>
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">Procesos críticos</div>
						<div class="ficha-kpi-val text-dark" id="fichaCriticalProcVal">0</div>
					</div>
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">Última revisión</div>
						<div class="ficha-kpi-val text-muted" id="fichaLastReviewVal">28/10</div>
					</div>
					<div class="ficha-kpi-block">
						<div class="ficha-kpi-label">Próxima</div>
						<div class="ficha-kpi-val text-success" id="fichaNextReviewVal">11/11</div>
					</div>
				</div>

				<!-- ACCESOS RÁPIDOS (2 columnas × 4 filas) -->
				<div class="ficha-quick-access-title mb-2">Accesos rápidos</div>
				<div class="ficha-quick-grid">
					<a href="{{ $U('/sales?tab=resumen&branch_id=1') }}" class="btn ficha-quick-btn" id="linkSales" target="_self">
						<i class="fa-solid fa-chart-column mr-1"></i>Ver ventas
					</a>
					<a href="{{ $U('/sales?tab=departamentos&branch_id=1') }}" class="btn ficha-quick-btn" id="linkDepts" target="_self">
						<i class="fa-solid fa-boxes-stacked mr-1"></i>Ver departamentos
					</a>
					<a href="{{ $U('/sales?tab=productos&branch_id=1') }}" class="btn ficha-quick-btn" id="linkProducts" target="_self">
						<i class="fa-solid fa-barcode mr-1"></i>Ver productos
					</a>
					<a href="{{ $U('/stockoverview?branch_id=1') }}" class="btn ficha-quick-btn" id="linkStock" target="_self">
						<i class="fa-solid fa-warehouse mr-1"></i>Abrir inventario
					</a>
					<a href="{{ $U('/workforce?branch_id=1') }}" class="btn ficha-quick-btn" id="linkStaff" target="_self">
						<i class="fa-solid fa-users mr-1"></i>Abrir personal
					</a>
					<a href="{{ $U('/orders?branch_id=1') }}" class="btn ficha-quick-btn" id="linkDispatch" target="_self">
						<i class="fa-solid fa-truck-fast mr-1"></i>Abrir despachos
					</a>
					<a href="{{ $U('/waste?branch_id=1') }}" class="btn ficha-quick-btn" id="linkWaste" target="_self">
						<i class="fa-solid fa-trash-can mr-1"></i>Abrir mermas
					</a>
					<a href="{{ $U('/security?branch_id=1') }}" class="btn ficha-quick-btn" id="linkPrevention" target="_self">
						<i class="fa-solid fa-shield-halved mr-1"></i>Prev. de pérdidas
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 4 — CENTRO DE INCIDENCIAS (60%) Y PATRONES MULTISUCURSAL (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- CENTRO DE INCIDENCIAS POR SUCURSAL (~60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header">
					<div>
						<h2 class="branch-panel-title">Centro de incidencias por sucursal</h2>
						<p class="branch-panel-subtitle">Reutiliza el sistema de incidencias existente: asignar responsable, cambiar estado y consultar evidencias.</p>
					</div>
					<button type="button" class="btn btn-xs btn-outline-primary" id="btnNewIncident">
						<i class="fa-solid fa-plus mr-1"></i>Nueva incidencia
					</button>
				</div>

				<div class="table-responsive branch-table-wrap">
					<table class="table branch-table mb-0" id="tableIncidents">
						<thead>
							<tr>
								<th>Sucursal</th>
								<th>Tipo</th>
								<th>Descripción</th>
								<th>Gravedad</th>
								<th>Responsable</th>
								<th class="text-center">Abierta</th>
								<th>Estado</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@foreach($incidents as $inc)
							<tr data-incident-id="{{ $inc['id'] }}" data-branch-id="{{ $inc['branch_id'] }}">
								<td class="font-weight-bold text-dark">{{ $inc['branch_name'] }}</td>
								<td><span class="branch-type-pill">{{ $inc['type'] }}</span></td>
								<td class="text-truncate" style="max-width: 170px;" title="{{ $inc['description'] }}">{{ $inc['description'] }}</td>
								<td><span class="branch-status-pill {{ $inc['severity_badge'] }}">{{ $inc['severity'] }}</span></td>
								<td>
									@if($inc['responsible_assigned'])
									<span class="text-dark">{{ $inc['responsible'] }}</span>
									@else
									<span class="text-danger font-weight-bold"><i class="fa-solid fa-circle-exclamation mr-1"></i>Sin responsable</span>
									@endif
								</td>
								<td class="text-center font-weight-600 text-muted">{{ $inc['days_open'] }}</td>
								<td><span class="branch-status-pill {{ $inc['status_badge'] }}">{{ $inc['status'] }}</span></td>
								<td class="text-right">
									<button type="button" class="btn btn-xs btn-light border btn-open-incident" data-incident-id="{{ $inc['id'] }}">
										{{ $inc['action_label'] }}
									</button>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- PATRONES MULTISUCURSAL (~40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header pb-1">
					<div>
						<h2 class="branch-panel-title">Patrones multisucursal</h2>
						<p class="branch-panel-subtitle">Problemas repetidos entre tiendas. Se distingue hipótesis, correlación y causa confirmada.</p>
					</div>
				</div>

				<div class="branch-patterns-list">
					@foreach($patterns as $pat)
					<div class="branch-pattern-card">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<h4 class="branch-pattern-title mb-0">{{ $pat['title'] }}</h4>
							<span class="branch-nature-pill {{ $pat['nature_badge'] }}">{{ $pat['nature'] }}</span>
						</div>
						<div class="small text-muted mb-1 font-weight-500">
							{{ $pat['stores_affected'] }} · {{ $pat['cases_count'] }} · <span class="{{ $pat['trend_class'] }} font-weight-bold">{{ $pat['trend_icon'] }} {{ $pat['trend'] }}</span>
						</div>
						<div class="small text-dark mb-1">
							{{ $pat['cause_description'] }} <span class="text-muted">Resp. regional:</span> <strong>{{ $pat['regional_responsible'] }}</strong>
						</div>
						<div class="small text-muted font-italic">
							Acción: {{ $pat['recommended_action'] }}
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 5 — INTERVENCIONES REGIONALES (60%) Y SEGUIMIENTO (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- CENTRO DE INTERVENCIONES REGIONALES (~60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header">
					<div>
						<h2 class="branch-panel-title">Centro de intervenciones regionales</h2>
						<p class="branch-panel-subtitle">Cada intervención mantiene trazabilidad y se valida contra el resultado.</p>
					</div>
				</div>

				<!-- Contadores de estado de intervención -->
				<div class="branch-interv-counters-bar mb-3">
					<div class="interv-counter-pill">
						<span class="interv-pill-label">Pendiente</span>
						<span class="interv-pill-num">{{ $interventionCounters['pendiente'] ?? 3 }}</span>
					</div>
					<div class="interv-counter-pill">
						<span class="interv-pill-label">En diagnóstico</span>
						<span class="interv-pill-num">{{ $interventionCounters['diagnostico'] ?? 2 }}</span>
					</div>
					<div class="interv-counter-pill">
						<span class="interv-pill-label">En ejecución</span>
						<span class="interv-pill-num">{{ $interventionCounters['ejecucion'] ?? 3 }}</span>
					</div>
					<div class="interv-counter-pill">
						<span class="interv-pill-label">En validación</span>
						<span class="interv-pill-num">{{ $interventionCounters['validacion'] ?? 1 }}</span>
					</div>
					<div class="interv-counter-pill is-resolved">
						<span class="interv-pill-label">Resuelta</span>
						<span class="interv-pill-num">{{ $interventionCounters['resuelta'] ?? 7 }}</span>
					</div>
				</div>

				<div class="table-responsive branch-table-wrap">
					<table class="table branch-table mb-0" id="tableInterventions">
						<thead>
							<tr>
								<th>Sucursal</th>
								<th>Problema</th>
								<th>Prior.</th>
								<th>Responsable</th>
								<th>Acción correctiva</th>
								<th class="text-center">Vence</th>
								<th>Evidencia</th>
								<th class="text-right">Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach($interventions as $int)
							<tr data-intervention-id="{{ $int['id'] }}">
								<td class="font-weight-bold text-dark">{{ $int['branch_name'] }}</td>
								<td class="text-truncate" style="max-width: 140px;">{{ $int['problem'] }}</td>
								<td><span class="branch-status-pill {{ $int['priority_badge'] }}">{{ $int['priority'] }}</span></td>
								<td>{{ $int['responsible'] }}</td>
								<td class="text-truncate" style="max-width: 160px;">{{ $int['corrective_action'] }}</td>
								<td class="text-center font-weight-600">{{ $int['due_date'] }}</td>
								<td class="small text-muted">{{ $int['evidence'] }}</td>
								<td class="text-right">
									<span class="branch-status-pill {{ $int['status_badge'] }}">{{ $int['status'] }}</span>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="branch-card-footer small text-muted">
					<i class="fa-solid fa-shield-check mr-1 text-success"></i>Resuelto solo si el BHI del proceso vuelve a su objetivo en la siguiente revisión.
				</div>
			</div>
		</div>

		<!-- SEGUIMIENTO DE RESPONSABLES (~40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header pb-1">
					<div>
						<h2 class="branch-panel-title">Seguimiento de responsables</h2>
						<p class="branch-panel-subtitle">Acciones asignadas y cumplimiento dentro del plazo.</p>
					</div>
				</div>

				<div class="branch-responsible-list mb-3">
					@foreach($responsibleFollowup as $rf)
					<div class="responsible-row mb-3">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<div>
								<strong class="text-dark">{{ $rf['responsible_name'] }}</strong>
								<span class="small text-muted ml-1">· {{ $rf['branch_name'] }}</span>
							</div>
							<div class="font-weight-700 text-dark">{{ $rf['compliance_pct'] }} %</div>
						</div>
						<div class="progress branch-progress-bar">
							<div class="progress-bar {{ $rf['bar_color_class'] }}" role="progressbar" style="width: {{ $rf['compliance_pct'] }}%;" aria-valuenow="{{ $rf['compliance_pct'] }}" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
						<div class="d-flex justify-content-between align-items-center small text-muted mt-1">
							<span>{{ $rf['assigned_actions'] }} acciones asignadas</span>
							<span>{{ $rf['overdue_actions'] }} vencidas</span>
						</div>
					</div>
					@endforeach
				</div>

				<div class="alert alert-light border small text-muted d-flex align-items-center mb-0" style="background: #f8fafc; border-color: #e2e8f0;">
					<i class="fa-solid fa-triangle-exclamation text-warning mr-2 fa-lg"></i>
					<div>2 incidencias sin responsable: disponer escalamiento al Centro de Alertas.</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 6 — COMPARADOR DE SUCURSALES (60%) Y BITÁCORA DE VISITAS (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- COMPARADOR DE SUCURSALES (~60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header">
					<div>
						<h2 class="branch-panel-title">Comparador de sucursales</h2>
						<p class="branch-panel-subtitle">2 a 4 tiendas. Tasas normalizadas por tamaño y volumen: las sucursales grandes no se penalizan por registrar más incidencias.</p>
					</div>
				</div>

				<!-- Selector de sucursales para el comparador -->
				<div class="branch-comp-selector-bar mb-2">
					<span class="small font-weight-bold text-muted mr-2">Comparar:</span>
					@foreach($branches as $b)
					@php $isChecked = in_array($b['id'], [1, 3, 2]); @endphp
					<label class="comp-check-chip @if($isChecked) is-active @endif" data-branch-id="{{ $b['id'] }}">
						<input type="checkbox" class="comp-branch-cb d-none" value="{{ $b['id'] }}" @if($isChecked) checked @endif>
						{{ $b['name'] }}
					</label>
					@endforeach
				</div>

				<div class="table-responsive branch-table-wrap">
					<table class="table branch-comp-table mb-0" id="tableComparator">
						<thead>
							<tr>
								<th>Indicador</th>
								<th class="text-center col-comp-1">Hallandale Beach</th>
								<th class="text-center col-comp-3">Sunny Isles</th>
								<th class="text-center col-comp-2">Miami Beach</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-600">BHI</td>
								<td class="text-center font-weight-700 col-comp-1">63</td>
								<td class="text-center font-weight-700 col-comp-3">68</td>
								<td class="text-center font-weight-700 col-comp-2">47</td>
							</tr>
							<tr>
								<td class="font-weight-600">Cumplimiento de procesos</td>
								<td class="text-center col-comp-1">81 %</td>
								<td class="text-center col-comp-3">84 %</td>
								<td class="text-center col-comp-2">62 %</td>
							</tr>
							<tr>
								<td class="font-weight-600">Resolución de incidencias</td>
								<td class="text-center col-comp-1">5.2 d</td>
								<td class="text-center col-comp-3">4.1 d</td>
								<td class="text-center col-comp-2">9.8 d</td>
							</tr>
							<tr>
								<td class="font-weight-600">Cobertura operativa</td>
								<td class="text-center col-comp-1">88 %</td>
								<td class="text-center col-comp-3">91 %</td>
								<td class="text-center col-comp-2">78 %</td>
							</tr>
							<tr>
								<td class="font-weight-600">Acciones completadas</td>
								<td class="text-center col-comp-1">72 %</td>
								<td class="text-center col-comp-3">78 %</td>
								<td class="text-center col-comp-2">51 %</td>
							</tr>
							<tr>
								<td class="font-weight-600">Recurrentes (por 1,000 tickets)</td>
								<td class="text-center col-comp-1">2.1</td>
								<td class="text-center col-comp-3">1.8</td>
								<td class="text-center col-comp-2">3.4</td>
							</tr>
							<tr>
								<td class="font-weight-600">Nivel de riesgo</td>
								<td class="text-center col-comp-1"><span class="branch-status-pill badge-atencion">Medio</span></td>
								<td class="text-center col-comp-3"><span class="branch-status-pill badge-atencion">Medio</span></td>
								<td class="text-center col-comp-2"><span class="branch-status-pill badge-critico">Alto</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- BITÁCORA DE VISITAS Y AUDITORÍAS (~40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="branch-card-panel h-100">
				<div class="branch-card-header pb-1">
					<div>
						<h2 class="branch-panel-title">Bitácora de visitas y auditorías</h2>
						<p class="branch-panel-subtitle">Conectada con tareas e incidencias.</p>
					</div>
					<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnRegisterVisit">
						<i class="fa-solid fa-plus mr-1"></i>Registrar visita
					</button>
				</div>

				<div class="branch-visits-list">
					@foreach($visitLog as $v)
					<div class="branch-visit-card mb-2" data-visit-id="{{ $v['id'] }}">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<strong class="text-dark">{{ $v['branch_name'] }} · {{ $v['visit_date'] }}</strong>
							<span class="badge badge-warning-subtle text-amber font-weight-700 px-2 py-1 rounded-pill">{{ $v['findings_badge'] }}</span>
						</div>
						<div class="small text-muted mb-1">{{ $v['visit_type'] }}</div>
						<div class="small text-muted font-italic mb-1">{{ $v['findings_details'] }}</div>
						<div class="small text-dark font-weight-500">Próxima revisión: {{ $v['next_review'] }}</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

	<!-- PIE DE PÁGINA DEL DASHBOARD -->
	<footer class="branch-dash-footer text-center py-2 text-muted small">
		DAILY · Sucursales · Mockup con datos demo, sin cifras reales
	</footer>
</div>

<!-- ====================================================================
     MODALES INTERACTIVOS
     ==================================================================== -->

<!-- MODAL: REGISTRAR VISITA / AUDITORÍA -->
<div class="modal fade" id="modalRegisterVisit" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-2">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-clipboard-check mr-2"></i>Registrar Visita y Auditoría</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<form id="formRegisterVisit">
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Sucursal a auditar</label>
						<select class="form-control form-control-sm" id="visitBranchSelect" required>
							@foreach($branches as $b)
							<option value="{{ $b['id'] }}">{{ $b['name'] }} ({{ $b['city'] }})</option>
							@endforeach
						</select>
					</div>
					<div class="form-row mb-2">
						<div class="col">
							<label class="small font-weight-bold">Fecha de visita</label>
							<input type="date" class="form-control form-control-sm" id="visitDateInput" value="{{ date('Y-m-d') }}" required>
						</div>
						<div class="col">
							<label class="small font-weight-bold">Tipo de visita</label>
							<select class="form-control form-control-sm" id="visitTypeSelect">
								<option value="Auditoría de reposición">Auditoría de reposición</option>
								<option value="Seguimiento de acciones vencidas">Seguimiento de acciones vencidas</option>
								<option value="Revisión de despachos">Revisión de despachos</option>
								<option value="Auditoría sanitaria / FEFO">Auditoría sanitaria / FEFO</option>
								<option value="Arqueo integral de cajas">Arqueo integral de cajas</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Responsable / Auditor</label>
						<input type="text" class="form-control form-control-sm" id="visitAuditorInput" value="Auditor Regional" required>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Hallazgos observados</label>
						<textarea class="form-control form-control-sm" id="visitFindingsInput" rows="2" placeholder="Describa desvíos, quiebres o incumplimientos detectados..."></textarea>
					</div>
					<div class="form-row mb-2">
						<div class="col">
							<label class="small font-weight-bold">Cantidad de hallazgos</label>
							<input type="number" class="form-control form-control-sm" id="visitFindingsCount" value="1" min="0" max="10">
						</div>
						<div class="col">
							<label class="small font-weight-bold">Próxima revisión</label>
							<input type="date" class="form-control form-control-sm" id="visitNextReviewInput" value="{{ date('Y-m-d', strtotime('+14 days')) }}">
						</div>
					</div>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="visitCreateTaskCb" checked>
						<label class="form-check-label small" for="visitCreateTaskCb">
							Crear tarea correctiva vinculada automáticamente en DAILY
						</label>
					</div>
				</form>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSaveVisit"><i class="fa-solid fa-save mr-1"></i>Guardar visita</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: DETALLE DE CELDA DE MATRIZ DE SUPERVISIÓN -->
<div class="modal fade" id="modalCellDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header py-2 bg-dark text-white">
				<h5 class="modal-title font-weight-bold" id="cellModalTitle">Supervisión de Proceso</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<span class="font-weight-bold text-muted" id="cellModalStore">Sucursal</span>
					<span class="branch-status-pill" id="cellModalStatus">Estado</span>
				</div>
				<p class="small text-dark mb-3" id="cellModalDetail">Detalle del proceso observado en la última sincronización.</p>
				<div class="alert alert-light border small text-muted mb-0">
					<i class="fa-solid fa-circle-info mr-1 text-primary"></i>Este estado se calculó con base en eventos registrados en el período y justificado por las incidencias abiertas de la tienda.
				</div>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
				<a href="#" class="btn btn-sm btn-primary font-weight-bold" id="cellModalLink"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Ir al módulo</a>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: DETALLE / EDICIÓN DE INCIDENCIA -->
<div class="modal fade" id="modalIncidentDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header py-2 bg-primary text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-circle-exclamation mr-2"></i>Gestión de Incidencia</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<form id="formIncidentDetail">
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Sucursal</label>
						<input type="text" class="form-control form-control-sm" id="incModalBranch" readonly>
					</div>
					<div class="form-row mb-2">
						<div class="col">
							<label class="small font-weight-bold">Tipo</label>
							<input type="text" class="form-control form-control-sm" id="incModalType" readonly>
						</div>
						<div class="col">
							<label class="small font-weight-bold">Gravedad</label>
							<select class="form-control form-control-sm" id="incModalSeverity">
								<option value="Crítica">Crítica</option>
								<option value="Alta">Alta</option>
								<option value="Media">Media</option>
								<option value="Baja">Baja</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Descripción</label>
						<input type="text" class="form-control form-control-sm" id="incModalDesc">
					</div>
					<div class="form-row mb-2">
						<div class="col">
							<label class="small font-weight-bold">Responsable asignado</label>
							<select class="form-control form-control-sm" id="incModalResponsible">
								<option value="Gerente 02">Gerente 02</option>
								<option value="Gerente 03">Gerente 03</option>
								<option value="Supervisor 02">Supervisor 02</option>
								<option value="Supervisor 05">Supervisor 05</option>
								<option value="Supervisor 07">Supervisor 07</option>
								<option value="Supervisor 09">Supervisor 09</option>
								<option value="Gerente Regional">Gerente Regional</option>
							</select>
						</div>
						<div class="col">
							<label class="small font-weight-bold">Estado</label>
							<select class="form-control form-control-sm" id="incModalStatus">
								<option value="Abierta">Abierta</option>
								<option value="Asignada">Asignada</option>
								<option value="En diagnóstico">En diagnóstico</option>
								<option value="Escalado">Escalado</option>
								<option value="Resuelta">Resuelta</option>
							</select>
						</div>
					</div>
					<div class="form-group mb-0">
						<label class="small font-weight-bold">Evidencia / Archivo adjunto</label>
						<div class="small text-muted" id="incModalEvidenceText">Sin archivos adjuntos.</div>
					</div>
				</form>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnSaveIncident"><i class="fa-solid fa-save mr-1"></i>Actualizar incidencia</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: PONDERACIONES CONFIGURABLES DEL BHI -->
<div class="modal fade" id="modalBhiWeights" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header py-2 bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-sliders mr-2"></i>Ponderaciones BHI (Branch Health Index)</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<p class="small text-muted mb-3">Ajusta los pesos para el cálculo del índice compuesto de salud por sucursal (0 a 100). La suma total debe ser 100%.</p>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Cumplimiento operativo</span>
						<span>25 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="25" disabled>
				</div>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Disponibilidad comercial</span>
						<span>20 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="20" disabled>
				</div>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Control de incidencias</span>
						<span>20 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="20" disabled>
				</div>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Cobertura de personal</span>
						<span>15 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="15" disabled>
				</div>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Cumplimiento de procesos</span>
						<span>10 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="10" disabled>
				</div>
				<div class="form-group mb-2">
					<div class="d-flex justify-content-between small font-weight-600 mb-1">
						<span>Resolución de acciones</span>
						<span>10 %</span>
					</div>
					<input type="range" class="custom-range" min="5" max="50" step="5" value="10" disabled>
				</div>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" data-dismiss="modal">Aceptar</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     LÓGICA JAVASCRIPT REACTIVA DEL DASHBOARD MULTISUCURSAL
     ==================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Catálogo de sucursales en memoria cliente para sincronización instantánea
	const branchMaster = @json($branches);
	let currentBranchId = 1; // Hallandale Beach por defecto

	// Mapeo de módulos para enlaces de celda
	const moduleLinks = {
		operacion: '{{ $U("/sales?tab=resumen") }}',
		inventario: '{{ $U("/stockoverview") }}',
		personal: '{{ $U("/workforce") }}',
		despachos: '{{ $U("/orders") }}',
		mermas: '{{ $U("/waste") }}',
		prevencion: '{{ $U("/security") }}',
		cumplimiento: '{{ $U("/tasks") }}'
	};

	// Función para seleccionar sucursal y sincronizar mapa, Ficha 360°, matriz y ranking
	window.selectBranchSupervision = function(branchId) {
		branchId = parseInt(branchId, 10);
		const b = branchMaster.find(x => x.id === branchId);
		if (!b) return;
		currentBranchId = branchId;

		// 1. Actualizar Ficha 360°
		const fTitle = document.getElementById('fichaTitle');
		if (fTitle) fTitle.innerText = 'Ficha 360° · ' + b.name;

		const fAddr = document.getElementById('fichaAddress');
		if (fAddr) fAddr.innerText = 'Código y dirección: ' + b.code + ' · ' + (b.address || 'Registro maestro') + ' (registro maestro)';

		const fReg = document.getElementById('fichaRegion');
		if (fReg) fReg.innerText = b.region;

		const fMan = document.getElementById('fichaManager');
		if (fMan) fMan.innerText = b.manager;

		const fBadge = document.getElementById('fichaStatusBadge');
		if (fBadge) {
			fBadge.className = 'branch-status-pill ' + b.status_badge_class;
			fBadge.innerText = b.status_label;
		}

		const fBhiVal = document.getElementById('fichaBhiVal');
		if (fBhiVal) fBhiVal.innerText = b.bhi !== null ? b.bhi : '—';

		const fBhiTrend = document.getElementById('fichaBhiTrend');
		if (fBhiTrend) {
			fBhiTrend.innerText = b.bhi_trend_text;
			fBhiTrend.className = (b.bhi_trend_class || '') + ' font-weight-bold small';
		}

		const fInc = document.getElementById('fichaIncidentsVal');
		if (fInc) fInc.innerText = b.active_incidents;

		const fOver = document.getElementById('fichaOverdueVal');
		if (fOver) fOver.innerText = b.overdue_actions;

		const fCrit = document.getElementById('fichaCriticalProcVal');
		if (fCrit) fCrit.innerText = b.critical_processes;

		const fLast = document.getElementById('fichaLastReviewVal');
		if (fLast) fLast.innerText = b.last_review;

		const fNext = document.getElementById('fichaNextReviewVal');
		if (fNext) fNext.innerText = b.next_review;

		// Actualizar enlaces de la Ficha 360° con ?branch_id=X
		const setLink = (id, base) => {
			const el = document.getElementById(id);
			if (el) el.href = base + (base.includes('?') ? '&' : '?') + 'branch_id=' + branchId;
		};
		setLink('linkSales', '{{ $U("/sales?tab=resumen") }}');
		setLink('linkDepts', '{{ $U("/sales?tab=departamentos") }}');
		setLink('linkProducts', '{{ $U("/sales?tab=productos") }}');
		setLink('linkStock', '{{ $U("/stockoverview") }}');
		setLink('linkStaff', '{{ $U("/workforce") }}');
		setLink('linkDispatch', '{{ $U("/orders") }}');
		setLink('linkWaste', '{{ $U("/waste") }}');
		setLink('linkPrevention', '{{ $U("/security") }}');

		// 2. Actualizar tarjeta flotante del mapa
		const flCard = document.getElementById('branchFloatingCard');
		if (flCard) {
			const flName = document.getElementById('floatName');
			if (flName) flName.innerText = b.name;

			const flMeta = document.getElementById('floatMeta');
			if (flMeta) flMeta.innerText = b.city;

			const flStat = document.getElementById('floatStatus');
			if (flStat) {
				flStat.className = 'branch-status-pill ' + b.status_badge_class;
				flStat.innerText = b.status_label;
			}

			const flMan = document.getElementById('floatManager');
			if (flMan) flMan.innerText = b.manager;

			const flBhi = document.getElementById('floatBhi');
			if (flBhi) flBhi.innerText = b.bhi !== null ? b.bhi : '—';

			const flInc = document.getElementById('floatIncidents');
			if (flInc) flInc.innerText = b.active_incidents;

			const flAct = document.getElementById('floatActions');
			if (flAct) flAct.innerText = b.pending_actions + ' (' + b.overdue_actions + ' vencidas)';

			// Posicionar la tarjeta flotante cerca del nodo
			const x = Math.min(Math.max(b.map_coords.x + 4, 10), 65);
			const y = Math.min(Math.max(b.map_coords.y - 12, 5), 55);
			flCard.style.left = x + '%';
			flCard.style.top = y + '%';
			flCard.style.display = 'block';
		}

		// 3. Resaltar en el mapa
		document.querySelectorAll('.branch-map-node').forEach(node => {
			if (parseInt(node.getAttribute('data-branch-id'), 10) === branchId) {
				node.classList.add('is-selected');
			} else {
				node.classList.remove('is-selected');
			}
		});

		// 4. Resaltar en el Ranking BHI
		document.querySelectorAll('.branch-rank-row').forEach(row => {
			if (parseInt(row.getAttribute('data-branch-id'), 10) === branchId) {
				row.classList.add('is-selected');
			} else {
				row.classList.remove('is-selected');
			}
		});

		// 5. Resaltar en la Matriz
		document.querySelectorAll('.branch-matrix-row').forEach(row => {
			if (parseInt(row.getAttribute('data-branch-id'), 10) === branchId) {
				row.classList.add('is-selected');
			} else {
				row.classList.remove('is-selected');
			}
		});
	};

	// Evento clic en nodos del mapa
	document.querySelectorAll('.branch-map-node').forEach(node => {
		node.addEventListener('click', function() {
			const bId = this.getAttribute('data-branch-id');
			selectBranchSupervision(bId);
		});
	});

	// Evento clic en filas del Ranking BHI
	document.querySelectorAll('.branch-rank-row').forEach(row => {
		row.addEventListener('click', function() {
			const bId = this.getAttribute('data-branch-id');
			selectBranchSupervision(bId);
		});
	});

	// Evento clic en filas de la matriz
	document.querySelectorAll('.branch-matrix-row').forEach(row => {
		row.addEventListener('click', function(e) {
			if (e.target.closest('.matrix-cell-btn')) return; // Manejado por el botón de celda
			const bId = this.getAttribute('data-branch-id');
			selectBranchSupervision(bId);
		});
	});

	// Botón "Abrir sucursal" en la tarjeta flotante
	const btnFloat = document.getElementById('btnFloatOpenBranch');
	if (btnFloat) {
		btnFloat.addEventListener('click', function() {
			window.location.href = '{{ $U("/sales?tab=resumen") }}&branch_id=' + currentBranchId;
		});
	}

	// Toggle entre Mapa y Tarjetas
	const btnMap = document.getElementById('btnViewMap');
	const btnCards = document.getElementById('btnViewCards');
	const mapContainer = document.getElementById('branchMapContainer');
	const cardsGrid = document.getElementById('branchCardsGrid');

	if (btnMap && btnCards && mapContainer && cardsGrid) {
		btnMap.addEventListener('click', function() {
			btnMap.classList.add('active');
			btnCards.classList.remove('active');
			mapContainer.classList.remove('d-none');
			cardsGrid.classList.add('d-none');
		});

		btnCards.addEventListener('click', function() {
			btnCards.classList.add('active');
			btnMap.classList.remove('active');
			cardsGrid.classList.remove('d-none');
			mapContainer.classList.add('d-none');
		});
	}

	// Clic en tarjetas de la vista cuadrícula
	document.querySelectorAll('.btn-select-branch-card').forEach(btn => {
		btn.addEventListener('click', function() {
			const bId = this.getAttribute('data-branch-id');
			selectBranchSupervision(bId);
			if (btnMap) btnMap.click();
		});
	});

	// Filtros de orden en Ranking BHI
	const rankingPills = document.querySelectorAll('.ranking-pill');
	rankingPills.forEach(pill => {
		pill.addEventListener('click', function() {
			rankingPills.forEach(p => p.classList.remove('active'));
			this.classList.add('active');
			const sortMode = this.getAttribute('data-sort');
			const tbody = document.getElementById('rankingBhiTbody');
			if (!tbody) return;
			const rows = Array.from(tbody.querySelectorAll('.branch-rank-row'));

			rows.sort((a, b) => {
				const idA = parseInt(a.getAttribute('data-branch-id'), 10);
				const idB = parseInt(b.getAttribute('data-branch-id'), 10);
				const bA = branchMaster.find(x => x.id === idA) || {};
				const bB = branchMaster.find(x => x.id === idB) || {};

				if (sortMode === 'best') return (bB.bhi || 0) - (bA.bhi || 0);
				if (sortMode === 'worst') return (bA.bhi || 999) - (bB.bhi || 999);
				if (sortMode === 'risk') return (bB.critical_incidents || 0) - (bA.critical_incidents || 0);
				if (sortMode === 'improvement') return (bB.bhi_trend || 0) - (bA.bhi_trend || 0);
				if (sortMode === 'deterioration') return (bA.bhi_trend || 0) - (bB.bhi_trend || 0);
				return 0;
			});

			rows.forEach((r, idx) => {
				r.querySelector('td:first-child').innerText = idx + 1;
				tbody.appendChild(r);
			});
		});
	});

	// Clic en celda de la Matriz para abrir Modal con justificativo
	document.querySelectorAll('.matrix-cell-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.stopPropagation();
			const bName = this.getAttribute('data-branch');
			const bId = this.getAttribute('data-branch-id');
			const pName = this.getAttribute('data-process');
			const pKey = this.getAttribute('data-process-key');
			const status = this.getAttribute('data-status');
			const detail = this.getAttribute('data-detail');

			const mTitle = document.getElementById('cellModalTitle');
			const mStore = document.getElementById('cellModalStore');
			const mStatus = document.getElementById('cellModalStatus');
			const mDetail = document.getElementById('cellModalDetail');
			const mLink = document.getElementById('cellModalLink');

			if (mTitle) mTitle.innerText = 'Supervisión · ' + pName;
			if (mStore) mStore.innerText = bName;
			if (mStatus) {
				mStatus.className = 'branch-status-pill badge-' + status;
				mStatus.innerText = status === 'operativa' || status === 'normal' ? 'Normal' : (status === 'atencion' ? 'Atención' : (status === 'critico' ? 'Crítico' : 'Sin datos'));
			}
			if (mDetail) mDetail.innerText = detail || 'Proceso en estado evaluado.';
			if (mLink) {
				const targetModule = moduleLinks[pKey] || '{{ $U("/sales") }}';
				mLink.href = targetModule + (targetModule.includes('?') ? '&' : '?') + 'branch_id=' + bId;
			}

			$('#modalCellDetail').modal('show');
		});
	});

	// Clic en botón "Abrir" / "Asignar" incidencia
	document.querySelectorAll('.btn-open-incident').forEach(btn => {
		btn.addEventListener('click', function() {
			const incId = this.getAttribute('data-incident-id');
			const row = this.closest('tr');
			if (!row) return;

			const bName = row.querySelector('td:nth-child(1)').innerText.trim();
			const type = row.querySelector('td:nth-child(2)').innerText.trim();
			const desc = row.querySelector('td:nth-child(3)').innerText.trim();
			const sev = row.querySelector('td:nth-child(4)').innerText.trim();
			const resp = row.querySelector('td:nth-child(5)').innerText.trim();
			const stat = row.querySelector('td:nth-child(7)').innerText.trim();

			document.getElementById('incModalBranch').value = bName;
			document.getElementById('incModalType').value = type;
			document.getElementById('incModalDesc').value = desc;
			document.getElementById('incModalSeverity').value = sev;
			document.getElementById('incModalStatus').value = stat;

			$('#modalIncidentDetail').modal('show');
		});
	});

	// Guardar actualización de incidencia
	const btnSaveInc = document.getElementById('btnSaveIncident');
	if (btnSaveInc) {
		btnSaveInc.addEventListener('click', function() {
			toastr.success('Incidencia actualizada correctamente en el sistema.');
			$('#modalIncidentDetail').modal('hide');
		});
	}

	// Modal Ponderaciones BHI
	const btnWeights = document.getElementById('btnOpenBhiWeights');
	if (btnWeights) {
		btnWeights.addEventListener('click', function() {
			$('#modalBhiWeights').modal('show');
		});
	}

	// Modal Registrar Visita
	const btnRegVis = document.getElementById('btnRegisterVisit');
	if (btnRegVis) {
		btnRegVis.addEventListener('click', function() {
			$('#modalRegisterVisit').modal('show');
		});
	}

	const btnSaveVis = document.getElementById('btnSaveVisit');
	if (btnSaveVis) {
		btnSaveVis.addEventListener('click', function() {
			const branchSel = document.getElementById('visitBranchSelect');
			const branchName = branchSel.options[branchSel.selectedIndex].text.split('(')[0].trim();
			const dateVal = document.getElementById('visitDateInput').value;
			const typeVal = document.getElementById('visitTypeSelect').value;
			const findings = document.getElementById('visitFindingsInput').value || 'Inspección regular ejecutada.';

			toastr.success('Visita registrada y vinculada al sistema de auditorías de DAILY.');
			$('#modalRegisterVisit').modal('hide');
		});
	}

	// Selector de sucursales en el comparador (checkboxes)
	document.querySelectorAll('.comp-branch-cb').forEach(cb => {
		cb.addEventListener('change', function() {
			const bId = this.value;
			const chip = this.closest('.comp-check-chip');
			const colClass = 'col-comp-' + bId;

			if (this.checked) {
				chip.classList.add('is-active');
				document.querySelectorAll('.' + colClass).forEach(c => c.style.display = '');
			} else {
				// Mínimo 2 seleccionadas
				const checkedCount = document.querySelectorAll('.comp-branch-cb:checked').length;
				if (checkedCount < 2) {
					this.checked = true;
					toastr.warning('El comparador requiere al menos 2 sucursales.');
					return;
				}
				chip.classList.remove('is-active');
				document.querySelectorAll('.' + colClass).forEach(c => c.style.display = 'none');
			}
		});
	});

	// Filtros reactivos de la barra superior
	['branchFilterPeriod', 'branchFilterRegion', 'branchFilterCity', 'branchFilterStore', 'branchFilterManager', 'branchFilterStatus', 'branchFilterPriority', 'branchFilterIncType'].forEach(id => {
		const el = document.getElementById(id);
		if (el) {
			el.addEventListener('change', function() {
				const filterVal = this.value;
				if (id === 'branchFilterStore' && filterVal !== 'all') {
					selectBranchSupervision(filterVal);
				}
			});
		}
	});

	// Clic en KPIs para filtrar
	document.querySelectorAll('.branch-kpi-card').forEach(kCard => {
		kCard.addEventListener('click', function() {
			const kFilter = this.getAttribute('data-kpi-filter');
			if (kFilter === 'operativa') {
				selectBranchSupervision(4);
			} else if (kFilter === 'atencion') {
				selectBranchSupervision(1);
			} else if (kFilter === 'critico') {
				selectBranchSupervision(2);
			} else if (kFilter === 'interventions') {
				selectBranchSupervision(2);
			}
		});
	});
});
</script>

