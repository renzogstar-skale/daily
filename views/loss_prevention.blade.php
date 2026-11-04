@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Prevención de Pérdidas 360° · Centro de Control')

@push('pageStyles')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@endpush

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$s = $data['summary'] ?? [];
	$k = $data['kpis'] ?? [];
	$cases = $data['cases'] ?? [];
	$f = $filters ?? [];
	$branches = $branches ?? [];
	$departments = $departments ?? [];
	$usersList = $usersList ?? [];
	$selectedBranchId = $selectedBranchId ?? null;

	// Helper para enlaces preservando contexto
	$ctxUrl = function(string $path) use ($U, $selectedBranchId) {
		$sep = (strpos($path, '?') !== false) ? '&' : '?';
		return $selectedBranchId ? $U($path . $sep . 'branch_id=' . $selectedBranchId) : $U($path);
	};

	// 8 Casos Oficiales de la Captura de Referencia
	$casesList = [
		[
			'id' => 2,
			'case_code' => 'DEMO-001',
			'product_name' => 'Celular Galaxy S24 128GB',
			'product_thumb' => '/img/control_center/prod_galaxy_s24.svg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Despensa · Electrónica y Telefonía',
			'difference' => -2,
			'value_at_risk' => 1040.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'En investigación',
			'status_class' => 'status-investigacion',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 1,
			'case_code' => 'INV-2026-0001',
			'product_name' => 'Galaxy Pro 5G 128GB',
			'product_thumb' => '/img/control_center/prod_galaxy_s24.svg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Zona Custodia · Electrónica y Telefonía',
			'difference' => -2,
			'value_at_risk' => 1040.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 8,
			'case_code' => 'DEMO-IMEI-00008',
			'product_name' => 'Televisor 55" (DEMO)',
			'product_thumb' => '/img/control_center/depto_electronica.jpg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Bodega principal · Electrónica y Telefonía',
			'difference' => -1,
			'value_at_risk' => 410.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 5,
			'case_code' => 'DEMO-IMEI-00005',
			'product_name' => 'Galaxy A55 256GB',
			'product_thumb' => '/img/control_center/prod_galaxy_s24.svg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Bodega principal · Electrónica y Telefonía',
			'difference' => -1,
			'value_at_risk' => 310.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 4,
			'case_code' => 'DEMO-IMEI-00004',
			'product_name' => 'Galaxy A55 256GB',
			'product_thumb' => '/img/control_center/prod_galaxy_s24.svg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Bodega principal · Electrónica y Telefonía',
			'difference' => -1,
			'value_at_risk' => 310.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 3,
			'case_code' => 'DEMO-002',
			'product_name' => 'Audífonos Pro (DEMO)',
			'product_thumb' => '/img/control_center/prod_audifonos.svg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Despensa · Electrónica y Telefonía',
			'difference' => -2,
			'value_at_risk' => 90.00,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 7,
			'case_code' => 'DEMO-005',
			'product_name' => 'Carne de res por kilo',
			'product_thumb' => '/img/control_center/carne_picada.jpg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Refrigerador · Carnes y aves',
			'difference' => -3.5,
			'value_at_risk' => 29.75,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'Reconteo',
			'status_class' => 'status-reconteo',
			'responsible' => 'rgiovine'
		],
		[
			'id' => 6,
			'case_code' => 'DEMO-003',
			'product_name' => 'Leche entera 1L',
			'product_thumb' => '/img/control_center/card_leche.jpg',
			'branch_name' => 'Hallandale Beach',
			'zone' => 'Refrigerador · Lácteos y huevos',
			'difference' => -6,
			'value_at_risk' => 6.90,
			'priority' => 'Media',
			'priority_class' => 'priority-media',
			'status' => 'En investigación',
			'status_class' => 'status-investigacion',
			'responsible' => 'rgiovine'
		]
	];
@endphp

@section('content')
<div class="lp-page-wrapper">
	@include('components.execution_context_bar')

	<!-- ====================================================================
	     1. HERO BANNER PRINCIPAL (CENTRO DE CONTROL)
	     ==================================================================== -->
	<header class="lp-hero-card">
		<div class="lp-hero-left">
			<span class="lp-hero-kicker">PREVENCIÓN DE PÉRDIDAS 360°</span>
			<h1 class="lp-hero-title">Centro de Control de Prevención de Pérdidas</h1>
			<p class="lp-hero-subtitle">Incidentes · Activos · Custodia IMEI · Investigaciones · Resolución y Recuperación</p>
		</div>

		<!-- Imagen Aisle + Scanner Handheld -->
		<div class="lp-hero-banner-image" style="background-image: url('{{ $U('/img/control_center/daily_scan_phone.jpg') }}');"></div>

		<!-- 3 Métricas Stackeadas a la Derecha -->
		<div class="lp-hero-stats-stack">
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-green">
					<i class="fa-solid fa-mobile-screen"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Activos controlados</span>
					<span class="lp-hero-stat-value">10,842</span>
				</div>
			</div>
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-blue">
					<i class="fa-solid fa-mobile-screen-button"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Celulares en operación</span>
					<span class="lp-hero-stat-value">312</span>
				</div>
			</div>
			<div class="lp-hero-stat-pill">
				<div class="lp-hero-stat-icon icon-green">
					<i class="fa-solid fa-shield-halved"></i>
				</div>
				<div class="lp-hero-stat-text">
					<span class="lp-hero-stat-label">Tasa de recuperación</span>
					<span class="lp-hero-stat-value">96.8%</span>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     2. BOTONES DE ACCIÓN PRINCIPALES
	     ==================================================================== -->
	<div class="lp-action-bar">
		<button type="button" class="lp-btn-action-primary" onclick="openNewIncidentModal()">
			<i class="fa-solid fa-bolt"></i> Registrar Incidencia
		</button>
		<a href="{{ $ctxUrl('/loss-prevention/counts') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-barcode"></i> Conteos Sensibles
		</a>
		<a href="{{ $ctxUrl('/loss-prevention/devices') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-magnifying-glass"></i> Buscar Activo / IMEI
		</a>
		<a href="{{ $ctxUrl('/scan') }}" class="lp-btn-action-outline">
			<i class="fa-solid fa-camera"></i> Escanear UPC
		</a>
		<div class="dropdown d-inline-block">
			<button type="button" class="lp-btn-dots" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más opciones de Prevención de Pérdidas">
				<i class="fa-solid fa-ellipsis"></i>
			</button>
			<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.81rem;">
				<a class="dropdown-item py-2" href="{{ $ctxUrl('/merchandise') }}"><i class="fa-solid fa-warehouse mr-2 text-success"></i>Control de Activos</a>
				<a class="dropdown-item py-2" href="{{ $ctxUrl('/reports/loss-executive') }}"><i class="fa-solid fa-file-invoice-dollar mr-2 text-primary"></i>Reporte Ejecutivo de Pérdidas</a>
				<a class="dropdown-item py-2" href="#" onclick="openCallModal(1, 'DEMO-001', 1, 'General'); return false;"><i class="fa-solid fa-phone mr-2 text-info"></i>Centro de Llamadas</a>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     3. BARRA DE FILTROS SUPERIOR
	     ==================================================================== -->
	<form method="GET" action="{{ $U('/loss-prevention') }}" id="lp-filters-form" class="lp-filters-bar">
		<div class="d-flex align-items-center text-muted mr-1" style="font-size:0.85rem;">
			<i class="fa-solid fa-filter"></i>
		</div>

		<!-- Selector Red -->
		<div class="lp-filter-pill">
			<select name="scope" onchange="this.form.submit()">
				<option value="all" selected>Toda la red</option>
				<option value="colombia">Red Alkosto Colombia</option>
				<option value="us">Red Estados Unidos</option>
			</select>
		</div>

		<!-- Selector Sucursal -->
		<div class="lp-filter-pill">
			<select name="branch_id" onchange="this.form.submit()">
				<option value="">Todas las sucursales</option>
				@foreach($branches as $b)
					<option value="{{ $b->id }}" @if((int)$selectedBranchId === (int)$b->id) selected @endif>
						{{ $b->name }}
					</option>
				@endforeach
			</select>
		</div>

		<!-- Selector Departamento -->
		<div class="lp-filter-pill">
			<select name="department" onchange="this.form.submit()">
				<option value="">Todos los deptos</option>
				@foreach($departments as $dept)
					<option value="{{ $dept }}" @if(($f['department'] ?? '') === $dept) selected @endif>
						{{ $dept }}
					</option>
				@endforeach
			</select>
		</div>

		<!-- Selector Rango de Fecha -->
		<div class="lp-filter-pill">
			<i class="fa-regular fa-calendar text-muted" style="font-size:0.75rem;"></i>
			<select name="period" onchange="this.form.submit()">
				<option value="7days" selected>26 oct 2026 - 02 nov 2026</option>
				<option value="today">Hoy</option>
				<option value="month">Este mes</option>
			</select>
		</div>

		<!-- Selector Responsable -->
		<div class="lp-filter-pill">
			<select name="assigned_user_id" onchange="this.form.submit()">
				<option value="">Responsable</option>
				@foreach($usersList as $u)
					<option value="{{ $u['id'] }}" @if(intval($f['assigned_user_id'] ?? 0) === intval($u['id'])) selected @endif>
						{{ $u['username'] }}
					</option>
				@endforeach
			</select>
		</div>
	</form>

	<!-- ====================================================================
	     4. 8 TARJETAS KPI HORIZONTALES (PREVENCIÓN DE PÉRDIDAS 360°)
	     ==================================================================== -->
	<section class="lp-kpis-grid-8" aria-label="Indicadores ejecutivos">

		<!-- 1. Incidentes Reportados -->
		<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable('all'); return false;" title="Ver todos los incidentes">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-amber">
					<i class="fa-solid fa-flag"></i>
				</div>
				<span class="lp-kpi-title">1. Incidentes Reportados</span>
			</div>
			<div class="lp-kpi-value">12</div>
			<span class="lp-kpi-delta delta-red">
				<i class="fa-solid fa-arrow-up" style="font-size:0.65rem;"></i> 4 críticos en turno
			</span>
		</a>

		<!-- 2. Alto Valor Custodia Especial -->
		<a href="#tab-custodia-imei" class="lp-kpi-card" onclick="switchLpTab('custodia-imei')">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green">
					<i class="fa-solid fa-mobile-screen"></i>
				</div>
				<span class="lp-kpi-title">2. Custodia Alto Valor</span>
			</div>
			<div class="lp-kpi-value">312 act.</div>
			<span class="lp-kpi-delta delta-green">
				● Celulares, TV y Licores
			</span>
		</a>

		<!-- 3. Diferencias en Conteos Ciegos -->
		<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable('differences'); return false;" title="Filtrar incidencias con diferencias de conteo">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-red">
					<i class="fa-solid fa-barcode"></i>
				</div>
				<span class="lp-kpi-title">3. Dif. Conteos Ciegos</span>
			</div>
			<div class="lp-kpi-value">-18.5 u.</div>
			<span class="lp-kpi-delta delta-green">
				<i class="fa-solid fa-arrow-down" style="font-size:0.65rem;"></i> -12% vs. anterior
			</span>
		</a>

		<!-- 4. Pérdida Estimada Robo -->
		<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable('theft'); return false;" title="Filtrar casos de alta criticidad / robo">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-red">
					<i class="fa-solid fa-user-secret"></i>
				</div>
				<span class="lp-kpi-title">4. Pérdida Est. Robo</span>
			</div>
			<div class="lp-kpi-value">$2,840</div>
			<span class="lp-kpi-delta delta-red">
				● Interno: 40% · Ext: 60%
			</span>
		</a>

		<!-- 5. Investigaciones Abiertas vs Cerradas -->
		<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable('investigacion'); return false;" title="Filtrar casos en investigación">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-amber">
					<i class="fa-solid fa-folder-open"></i>
				</div>
				<span class="lp-kpi-title">5. Investigaciones</span>
			</div>
			<div class="lp-kpi-value">5 / 7</div>
			<span class="lp-kpi-delta delta-green">
				5 abiertas · 7 cerradas
			</span>
		</a>

		<!-- 6. Efectividad Controles -->
		<a href="#tab-planes" class="lp-kpi-card" onclick="switchLpTab('planes')">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green">
					<i class="fa-solid fa-shield-halved"></i>
				</div>
				<span class="lp-kpi-title">6. Efectividad Control</span>
			</div>
			<div class="lp-kpi-value">94.8%</div>
			<span class="lp-kpi-delta delta-green">
				<i class="fa-solid fa-check" style="font-size:0.65rem;"></i> Incidentes prevenidos
			</span>
		</a>

		<!-- 7. Valor Recuperado -->
		<a href="#tab-expedientes" class="lp-kpi-card">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-green">
					<i class="fa-solid fa-hand-holding-dollar"></i>
				</div>
				<span class="lp-kpi-title">7. Valor Recuperado</span>
			</div>
			<div class="lp-kpi-value">$5,420</div>
			<span class="lp-kpi-delta delta-green">
				✓ 96.8% tasa de éxito
			</span>
		</a>

		<!-- 8. Nivel Riesgo Promedio -->
		<a href="#tab-mapa" class="lp-kpi-card" onclick="switchLpTab('mapa')">
			<div class="lp-kpi-top">
				<div class="lp-kpi-circle-icon circle-amber">
					<i class="fa-solid fa-gauge-high"></i>
				</div>
				<span class="lp-kpi-title">8. Nivel de Riesgo</span>
			</div>
			<div class="lp-kpi-value">Bajo (1.8)</div>
			<span class="lp-kpi-delta delta-green">
				● Red Operativa Segura
			</span>
		</a>
	</section>

	<!-- ====================================================================
	     5. PESTAÑAS SEGMENTADAS
	     ==================================================================== -->
	<nav class="lp-tabs-bar" aria-label="Secciones de Prevención de Pérdidas">
		<button type="button" class="lp-tab-item active" id="tab-btn-expedientes" onclick="switchLpTab('expedientes')">
			<i class="fa-regular fa-clipboard"></i> Incidencias y Expedientes (8)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-mapa" onclick="switchLpTab('mapa')">
			<i class="fa-solid fa-map-location-dot"></i> Mapa de Sucursales (10)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-disponibilidad" onclick="switchLpTab('disponibilidad')">
			<i class="fa-solid fa-users"></i> Disponibilidad (112)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-tareas" onclick="switchLpTab('tareas')">
			<i class="fa-solid fa-list-check"></i> Tareas Multi-Área (8)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-planes" onclick="switchLpTab('planes')">
			<i class="fa-solid fa-shield"></i> Planes Preventivos (6)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-anomalias" onclick="switchLpTab('anomalias')" style="color: #dc2626;">
			<i class="fa-solid fa-triangle-exclamation"></i> Detección de Anomalías (6)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-custodia-imei" onclick="switchLpTab('custodia-imei')" style="color: #2563eb;">
			<i class="fa-solid fa-mobile-screen-button"></i> Cadena de Custodia IMEI
		</button>
	</nav>

	<!-- ====================================================================
	     6. TABLA PRINCIPAL: INCIDENCIAS Y EXPEDIENTES 360°
	     ==================================================================== -->
	<div class="lp-tab-pane" id="pane-expedientes">
		<div class="lp-table-card">
			<div class="lp-table-header">
				<div class="lp-table-title-group">
					<h2>Incidencias y Expedientes 360°</h2>
					<p>Detección, investigación y resolución de pérdidas en toda la red</p>
				</div>
				<button type="button" class="lp-btn-new-incident" onclick="openNewIncidentModal()">
					<i class="fa-solid fa-plus"></i> Nueva Incidencia
				</button>
			</div>

			<div class="table-responsive">
				<table class="lp-cases-table">
					<thead>
						<tr>
							<th>Caso / Código</th>
							<th>Producto / Activo</th>
							<th>Sucursal</th>
							<th>Zona / Depto</th>
							<th>Diferencia</th>
							<th>Valor en Riesgo</th>
							<th>Prioridad</th>
							<th>Estado</th>
							<th>Responsable</th>
							<th style="text-align:right;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@foreach($casesList as $c)
							<tr id="case-row-{{ $c['id'] }}">
								<td class="lp-case-code"><a href="{{ $U('/loss-prevention/case/' . $c['id']) }}" class="font-weight-bold" style="color:#0f172a; text-decoration:none;" title="Abrir expediente 360°" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">{{ $c['case_code'] }} <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size:0.68rem; color:#64748b;"></i></a></td>
								<td>
									<div class="lp-product-cell">
										<img src="{{ $U($c['product_thumb']) }}" class="lp-product-thumb" alt="" onerror="this.src='{{ $U('/img/control_center/prod_galaxy_s24.svg') }}'">
										<span class="lp-product-name">{{ $c['product_name'] }}</span>
									</div>
								</td>
								<td>{{ $c['branch_name'] }}</td>
								<td>{{ $c['zone'] }}</td>
								<td class="lp-diff-red">{{ $c['difference'] }}</td>
								<td class="lp-value-red">${{ number_format($c['value_at_risk'], 2, '.', ',') }}</td>
								<td>
									<span class="lp-badge-priority">{{ $c['priority'] }}</span>
								</td>
								<td>
									<span class="lp-badge-status {{ $c['status_class'] }}">{{ $c['status'] }}</span>
								</td>
								<td>{{ $c['responsible'] }}</td>
								<td>
									<div class="lp-action-cell">
										<button type="button" class="lp-btn-resolve-table" onclick="openResolveDrawer({{ json_encode($c) }})">
											Resolver
										</button>
										<span class="lp-btn-dots-row" title="Acciones rápidas" onclick="openActionMenu({{ $c['id'] }}, '{{ $c['case_code'] }}')">
											<i class="fa-solid fa-ellipsis-vertical"></i>
										</span>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<!-- ====================================================================
		     7. 4 TARJETAS DE ANALÍTICA (GRID 2x2 RÉPLICA EXACTA)
		     ==================================================================== -->
		<div class="lp-analytics-grid-4">

			<!-- 7.1 Tendencia de Diferencias -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Tendencia de diferencias</h3>
						<p>Valor en riesgo por periodo</p>
					</div>
					<span class="lp-badge-delta-red">
						<i class="fa-solid fa-arrow-trend-up"></i> +18% vs. periodo anterior
					</span>
				</div>
				<div class="lp-chart-box-220">
					<canvas id="lp-trend-chart"></canvas>
				</div>
			</div>

			<!-- 7.2 Productos con Mayor Exposición -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Productos con mayor exposición</h3>
						<p>Valor en riesgo (USD)</p>
					</div>
				</div>
				<div class="lp-chart-box-220">
					<canvas id="lp-products-chart"></canvas>
				</div>
			</div>

			<!-- 7.3 Sucursales con Mayor Exposición -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Sucursales con mayor exposición</h3>
						<p>Top 5 por valor en riesgo</p>
					</div>
				</div>
				<div class="lp-branch-bars-stack">
					<!-- Hallandale Beach (Red) -->
					<div class="lp-branch-bar-row">
						<span class="lp-branch-bar-name">Hallandale Beach</span>
						<div class="lp-branch-bar-track">
							<div class="lp-branch-bar-fill bar-red" style="width: 100%;"></div>
						</div>
						<span class="lp-branch-bar-val">$2,236.65</span>
					</div>
					<!-- Miami Beach (Yellow) -->
					<div class="lp-branch-bar-row">
						<span class="lp-branch-bar-name">Miami Beach</span>
						<div class="lp-branch-bar-track">
							<div class="lp-branch-bar-fill bar-yellow" style="width: 66%;"></div>
						</div>
						<span class="lp-branch-bar-val">$1,480.20</span>
					</div>
					<!-- Aventura (Yellow) -->
					<div class="lp-branch-bar-row">
						<span class="lp-branch-bar-name">Aventura</span>
						<div class="lp-branch-bar-track">
							<div class="lp-branch-bar-fill bar-yellow" style="width: 50%;"></div>
						</div>
						<span class="lp-branch-bar-val">$1,120.00</span>
					</div>
					<!-- Sunny Isles (Green) -->
					<div class="lp-branch-bar-row">
						<span class="lp-branch-bar-name">Sunny Isles</span>
						<div class="lp-branch-bar-track">
							<div class="lp-branch-bar-fill bar-green" style="width: 28%;"></div>
						</div>
						<span class="lp-branch-bar-val">$620.50</span>
					</div>
					<!-- Bayside (Green) -->
					<div class="lp-branch-bar-row">
						<span class="lp-branch-bar-name">Bayside</span>
						<div class="lp-branch-bar-track">
							<div class="lp-branch-bar-fill bar-green" style="width: 14%;"></div>
						</div>
						<span class="lp-branch-bar-val">$310.00</span>
					</div>
				</div>
			</div>

			<!-- 7.4 Exposición por Departamento (Donut) -->
			<div class="lp-analytics-card">
				<div class="lp-analytics-card-header">
					<div>
						<h3>Exposición por departamento</h3>
						<p>Participación del valor en riesgo</p>
					</div>
				</div>
				<div class="lp-donut-split">
					<div class="lp-donut-canvas-wrap">
						<canvas id="lp-department-chart"></canvas>
						<div class="lp-donut-center-info">
							<div class="lp-donut-center-num">$5,767.35</div>
							<div class="lp-donut-center-label">Total en riesgo</div>
						</div>
					</div>
					<div class="lp-donut-legend-stack">
						<div class="lp-donut-legend-item">
							<div class="lp-donut-legend-left">
								<span class="lp-legend-dot dot-red"></span>
								<span>Electrónica y Telefonía</span>
							</div>
							<span class="lp-donut-legend-pct">78.5%</span>
						</div>
						<div class="lp-donut-legend-item">
							<div class="lp-donut-legend-left">
								<span class="lp-legend-dot dot-yellow"></span>
								<span>Carnes y aves</span>
							</div>
							<span class="lp-donut-legend-pct">15.9%</span>
						</div>
						<div class="lp-donut-legend-item">
							<div class="lp-donut-legend-left">
								<span class="lp-legend-dot dot-green"></span>
								<span>Lácteos y huevos</span>
							</div>
							<span class="lp-donut-legend-pct">5.6%</span>
						</div>
					</div>
				</div>
			</div>

		</div>

		<!-- ====================================================================
		     8. ACCIONES RÁPIDAS INFERIORES
		     ==================================================================== -->
		<section class="lp-quick-actions-section" aria-label="Acciones rápidas">
			<div class="lp-quick-header">
				<h3><i class="fa-solid fa-bolt text-success"></i> Acciones rápidas</h3>
			</div>
			<div class="lp-quick-cards-grid">
				<a href="{{ $ctxUrl('/loss-prevention/devices') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
					<span class="lp-quick-tile-label">Control de Celulares</span>
				</a>
				<a href="{{ $ctxUrl('/merchandise') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
					<span class="lp-quick-tile-label">Control de Activos</span>
				</a>
				<a href="#" onclick="openCctvModal(); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-video"></i></div>
					<span class="lp-quick-tile-label">CCTV y Evidencias</span>
				</a>
				<a href="#" onclick="openRoundsModal(); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-shield-halved"></i></div>
					<span class="lp-quick-tile-label">Rondas y Recorridos</span>
				</a>
				<a href="#" onclick="switchLpTab('planes'); return false;" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-bullseye"></i></div>
					<span class="lp-quick-tile-label">Planes Preventivos</span>
				</a>
				<a href="{{ $ctxUrl('/reports/loss-executive') }}" class="lp-quick-action-tile">
					<div class="lp-quick-tile-icon"><i class="fa-solid fa-chart-column"></i></div>
					<span class="lp-quick-tile-label">Reportes / KPI</span>
				</a>
			</div>
		</section>
	</div>

	<!-- ====================================================================
	     PANEL TAB 2: MAPA Y ESTADO OPERATIVO DE SUCURSALES (10)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-mapa">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-map-location-dot text-success mr-2"></i>Mapa y Monitor de Sucursales en Red (10)</h3>
					<p class="text-muted small m-0">Monitoreo 360° en tiempo real de sucursales, nivel de merma, alertas de seguridad y cobertura de guardia.</p>
				</div>
				<div class="d-flex align-items-center gap-2">
					<span class="badge badge-success px-3 py-2 mr-2 font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i> 7 Operativas</span>
					<span class="badge badge-warning px-3 py-2 mr-2 font-weight-bold text-dark"><i class="fa-solid fa-triangle-exclamation mr-1"></i> 2 En Atención</span>
					<span class="badge badge-danger px-3 py-2 mr-2 font-weight-bold"><i class="fa-solid fa-circle-xmark mr-1"></i> 1 Crítica</span>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			<div class="row">
				@php
				$branchDetails = [
					['name' => 'Alkosto Calle 80', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.18%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$1,299.00', 'guard' => 'Enrique Peña (Líder)', 'phone' => '+57 311 405 9812', 'cctv' => '98.5% uptime'],
					['name' => 'Alkosto Envigado', 'city' => 'Medellín, Antioquia', 'status' => 'Atención', 'badge' => 'badge-warning text-dark', 'shrink' => '1.62%', 'target' => '1.50%', 'cases' => 2, 'risk_val' => '$3,420.00', 'guard' => 'Jorge Valencia (Muelle)', 'phone' => '+57 314 821 7701', 'cctv' => '94.0% uptime'],
					['name' => 'Alkosto Cali Sur', 'city' => 'Cali, Valle del Cauca', 'status' => 'Crítica', 'badge' => 'badge-danger', 'shrink' => '2.15%', 'target' => '1.50%', 'cases' => 3, 'risk_val' => '$4,850.00', 'guard' => 'Mario Ospina (CCTV)', 'phone' => '+57 310 933 1184', 'cctv' => '91.2% uptime'],
					['name' => 'Alkosto Barranquilla', 'city' => 'Barranquilla, Atlántico', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.05%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Andrea Torres (Supervisor)', 'phone' => '+57 318 644 2090', 'cctv' => '99.1% uptime'],
					['name' => 'Alkosto Bucaramanga', 'city' => 'Bucaramanga, Santander', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.24%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$450.00', 'guard' => 'Camilo Duarte (Piso)', 'phone' => '+57 312 550 4918', 'cctv' => '97.8% uptime'],
					['name' => 'Alkosto Tintal', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.30%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Diana Pineda (Bóveda)', 'phone' => '+57 315 771 8832', 'cctv' => '98.0% uptime'],
					['name' => 'Alkosto Villas', 'city' => 'Bogotá, D.C.', 'status' => 'Atención', 'badge' => 'badge-warning text-dark', 'shrink' => '1.58%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$890.00', 'guard' => 'Felipe Gómez (Cajas)', 'phone' => '+57 317 220 5410', 'cctv' => '95.5% uptime'],
					['name' => 'Alkosto 170', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '0.98%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Claudia Morales (Seguridad)', 'phone' => '+57 313 449 0182', 'cctv' => '99.5% uptime'],
					['name' => 'Alkosto Suba', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.10%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Sandra Milena (CCTV)', 'phone' => '+57 316 880 7741', 'cctv' => '98.8% uptime'],
					['name' => 'Alkosto Cali Norte', 'city' => 'Cali, Valle del Cauca', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.35%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Mauricio Vélez (Control)', 'phone' => '+57 319 332 5599', 'cctv' => '97.0% uptime']
				];
				@endphp

				@foreach($branchDetails as $bd)
					<div class="col-lg-4 col-md-6 mb-3">
						<div class="p-3 border rounded shadow-sm h-100 d-flex flex-column justify-content-between" style="background:#ffffff; border-radius:12px; border-color:#e2e8f0 !important;">
							<div>
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div>
										<h5 class="font-weight-bold mb-0 text-dark" style="font-size: 1.05rem;">{{ $bd['name'] }}</h5>
										<span class="text-muted small"><i class="fa-solid fa-location-dot mr-1 text-danger"></i>{{ $bd['city'] }}</span>
									</div>
									<span class="badge {{ $bd['badge'] }} px-2 py-1 font-weight-bold">{{ $bd['status'] }}</span>
								</div>
								
								<div class="bg-light p-2 rounded mb-2" style="font-size: 0.82rem;">
									<div class="d-flex justify-content-between mb-1">
										<span class="text-muted">Merma actual:</span>
										<strong class="{{ floatval($bd['shrink']) > 1.5 ? 'text-danger' : 'text-success' }}">{{ $bd['shrink'] }} <span class="text-muted font-weight-normal">(meta {{ $bd['target'] }})</span></strong>
									</div>
									<div class="d-flex justify-content-between mb-1">
										<span class="text-muted">Expedientes activos:</span>
										<strong class="{{ $bd['cases'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $bd['cases'] }} ({{ $bd['risk_val'] }})</strong>
									</div>
									<div class="d-flex justify-content-between">
										<span class="text-muted">CCTV & Antenas EAS:</span>
										<span class="text-dark font-weight-600"><i class="fa-solid fa-video text-muted mr-1"></i>{{ $bd['cctv'] }}</span>
									</div>
								</div>

								<div class="small mb-3 text-muted">
									<i class="fa-solid fa-user-shield text-primary mr-1"></i> Oficial a cargo: <strong class="text-dark">{{ $bd['guard'] }}</strong><br>
									<i class="fa-solid fa-phone text-muted mr-1"></i> {{ $bd['phone'] }}
								</div>
							</div>

							<div class="d-flex gap-2">
								<button type="button" class="btn btn-xs btn-outline-success font-weight-bold flex-fill py-1" onclick="switchLpTab('expedientes')">
									<i class="fa-solid fa-folder-open mr-1"></i> Ver Casos
								</button>
								<button type="button" class="btn btn-xs btn-outline-primary font-weight-bold flex-fill py-1" onclick="openCallModal(1, '{{ $bd['name'] }}', 1, '{{ $bd['guard'] }}')">
									<i class="fa-solid fa-phone mr-1"></i> Contactar
								</button>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANEL TAB 3: DISPONIBILIDAD DE PERSONAL (112 COLABORADORES EN TURNO)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-disponibilidad">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-users text-primary mr-2"></i>Directorio de Personal y Disponibilidad Operativa (112)</h3>
					<p class="text-muted small m-0">Colaboradores en turno activo, guardias de seguridad, operadores CCTV y custodios de mercancía sensible.</p>
				</div>
				<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
			</div>

			{{-- KPIs de Personal --}}
			<div class="row mb-3">
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PERSONAL EN TURNO</span>
						<h4 class="font-weight-bold text-dark mb-0">112 activos</h4>
						<small class="text-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i> Cobertura 100% de puestos</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PREVENCIÓN Y SEGURIDAD</span>
						<h4 class="font-weight-bold text-success mb-0">24 oficiales</h4>
						<small class="text-muted">10 tiendas + centro monitoreo</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">BODEGA Y MUELLES WMS</span>
						<h4 class="font-weight-bold text-info mb-0">38 operadores</h4>
						<small class="text-muted">Recepciones y transferencias</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">CAJAS Y SERVICIO</span>
						<h4 class="font-weight-bold text-primary mb-0">50 cajeros/jefes</h4>
						<small class="text-muted">Líneas de cobro y devoluciones</small>
					</div>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th>Colaborador</th>
							<th>Cargo / Función</th>
							<th>Área Operativa</th>
							<th>Sucursal Asignada</th>
							<th>Horario / Turno</th>
							<th>Estado</th>
							<th class="text-center" style="width: 170px;">Acciones Rápidas</th>
						</tr>
					</thead>
					<tbody>
						@php
						$staffRoster = [
							['name' => 'Renzo Giovine', 'role' => 'Gerente de Prevención 360°', 'dept' => 'Dirección de Pérdidas', 'branch' => 'Corporativo · Calle 80', 'shift' => '08:00 – 18:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 311 000 1122'],
							['name' => 'Carlos Mendoza', 'role' => 'Custodio de Bóveda y Tecnología', 'dept' => 'Bóveda de Seguridad', 'branch' => 'Alkosto Calle 80', 'shift' => '07:00 – 16:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 311 405 9812'],
							['name' => 'Lucía Restrepo', 'role' => 'Jefe de Cajas y Arqueos', 'dept' => 'Cajas y Facturación', 'branch' => 'Alkosto Calle 80', 'shift' => '09:00 – 19:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 312 901 3344'],
							['name' => 'Jorge Valencia', 'role' => 'Supervisor de Recepción WMS', 'dept' => 'Muelle de Carga', 'branch' => 'Alkosto Envigado', 'shift' => '06:00 – 15:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 314 821 7701'],
							['name' => 'Mario Ospina', 'role' => 'Analista Central CCTV', 'dept' => 'Monitoreo y Alarmas', 'branch' => 'Alkosto Cali Sur', 'shift' => '14:00 – 22:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 310 933 1184'],
							['name' => 'Andrea Torres', 'role' => 'Auditora de Calidad y Devoluciones', 'dept' => 'Servicio al Cliente', 'branch' => 'Alkosto Barranquilla', 'shift' => '08:00 – 17:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 318 644 2090'],
							['name' => 'Camilo Duarte', 'role' => 'Oficial de Seguridad en Piso', 'dept' => 'Pasillos de Alta Gama', 'branch' => 'Alkosto Bucaramanga', 'shift' => '10:00 – 20:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 312 550 4918'],
							['name' => 'Diana Pineda', 'role' => 'Líder de Control FEFO', 'dept' => 'Cámaras de Frío / Carnes', 'branch' => 'Alkosto Tintal', 'shift' => '06:00 – 15:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 315 771 8832'],
							['name' => 'Felipe Gómez', 'role' => 'Supervisor de Cajas', 'dept' => 'POS y Descuentos', 'branch' => 'Alkosto Villas', 'shift' => '12:00 – 21:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 317 220 5410'],
							['name' => 'Claudia Morales', 'role' => 'Guardia de Muelle y Acceso', 'dept' => 'Control de Despachos', 'branch' => 'Alkosto 170', 'shift' => '07:00 – 16:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 313 449 0182'],
							['name' => 'Sandra Milena', 'role' => 'Operadora CCTV y Antenas EAS', 'dept' => 'Seguridad Electrónica', 'branch' => 'Alkosto Suba', 'shift' => '08:00 – 18:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 316 880 7741'],
							['name' => 'Mauricio Vélez', 'role' => 'Coordinador de Inventario Cíclico', 'dept' => 'Conteos Físicos', 'branch' => 'Alkosto Cali Norte', 'shift' => '07:00 – 16:30', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 319 332 5599']
						];
						@endphp

						@foreach($staffRoster as $st)
							<tr>
								<td>
									<strong>{{ $st['name'] }}</strong><br>
									<span class="text-muted small"><i class="fa-solid fa-phone mr-1" style="font-size:10px;"></i>{{ $st['phone'] }}</span>
								</td>
								<td>{{ $st['role'] }}</td>
								<td><span class="badge badge-light border text-dark">{{ $st['dept'] }}</span></td>
								<td>{{ $st['branch'] }}</td>
								<td class="small">{{ $st['shift'] }}</td>
								<td><span class="badge {{ $st['badge'] }} px-2 py-1">{{ $st['status'] }}</span></td>
								<td class="text-center">
									<div class="btn-group btn-group-sm">
										<button type="button" class="btn btn-outline-success btn-xs px-2" title="Llamada de voz inmediata" onclick="openCallModal(1, '{{ $st['name'] }}', 1, '{{ $st['dept'] }}')">
											<i class="fa-solid fa-phone"></i>
										</button>
										<button type="button" class="btn btn-outline-primary btn-xs px-2" title="Mensaje WhatsApp / Interno" onclick="openMsgModal(1, '{{ $st['name'] }}')">
											<i class="fa-solid fa-comment-dots"></i>
										</button>
										<button type="button" class="btn btn-outline-dark btn-xs px-2" title="Asignar tarea de prevención" onclick="switchLpTab('tareas')">
											<i class="fa-solid fa-list-check"></i>
										</button>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANEL TAB 4: TAREAS MULTI-ÁREA (8 ASIGNACIONES EN CURSO)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-tareas">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-list-check text-success mr-2"></i>Tareas Operativas Multi-Área de Prevención (8)</h3>
					<p class="text-muted small m-0">Verificaciones de stock en bóveda, auditoría de precintos de muelle, calibración de antenas EAS y reconteos ciegos.</p>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Formulario de nueva asignación express de prevención abierto.');">
						<i class="fa-solid fa-plus mr-1"></i> Nueva Asignación
					</button>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			{{-- Filtros Rápidos de Tareas --}}
			<div class="d-flex gap-2 mb-3">
				<button class="btn btn-xs btn-dark font-weight-bold px-3">Todas (8)</button>
				<button class="btn btn-xs btn-outline-danger font-weight-bold px-3">Urgentes (3)</button>
				<button class="btn btn-xs btn-outline-primary font-weight-bold px-3">En Proceso (3)</button>
				<button class="btn btn-xs btn-outline-success font-weight-bold px-3">Verificadas (2)</button>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th style="width: 120px;">Código</th>
							<th>Descripción de la Tarea</th>
							<th>Área / Proceso</th>
							<th>Sucursal</th>
							<th>Responsable</th>
							<th>Vencimiento</th>
							<th>Prioridad</th>
							<th>Estado</th>
							<th class="text-center" style="width: 140px;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@php
						$lpTasks = [
							['code' => 'TAR-SEC-0104', 'title' => 'Reconteo ciego de Smartphones en Bóveda', 'sub' => 'Cotejo físico de 28 equipos iPhone 15 vs. ledger serial', 'area' => 'Tecnología · Bóveda', 'branch' => 'Alkosto Calle 80', 'resp' => 'Carlos Mendoza', 'due' => 'Hoy 18:00', 'prio' => 'Urgente', 'prio_badge' => 'badge-danger', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0105', 'title' => 'Auditoría de Precintos en Muelle de Descarga', 'sub' => 'Validación de candados satelitales en camión T-8821', 'area' => 'Logística WMS', 'branch' => 'Alkosto Envigado', 'resp' => 'Jorge Valencia', 'due' => 'Hoy 20:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0106', 'title' => 'Calibración y Prueba de Antenas EAS Sensormatic', 'sub' => 'Prueba con tag de prueba en acceso principal y cajas', 'area' => 'Seguridad Física', 'branch' => 'Alkosto Cali Sur', 'resp' => 'Mario Ospina', 'due' => 'Mañana 10:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0107', 'title' => 'Inspección de Jaula de Licores Importados', 'sub' => 'Verificación de collarines magnéticos y stock físico', 'area' => 'Bebidas Premium', 'branch' => 'Alkosto Tintal', 'resp' => 'Diana Pineda', 'due' => 'Hoy 19:30', 'prio' => 'Media', 'prio_badge' => 'badge-info', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0108', 'title' => 'Peritaje Técnico de Devoluciones de TV 65"', 'sub' => 'Comprobación de accesorios, pantalla y número de serie', 'area' => 'Servicio al Cliente', 'branch' => 'Alkosto Barranquilla', 'resp' => 'Andrea Torres', 'due' => 'Mañana 12:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0109', 'title' => 'Arqueo Sorpresivo de Líneas de Cajas 01 a 08', 'sub' => 'Validación de efectivo, transferencias y notas de crédito', 'area' => 'Cajas y POS', 'branch' => 'Alkosto Bucaramanga', 'resp' => 'Camilo Duarte', 'due' => 'Hoy 17:00', 'prio' => 'Urgente', 'prio_badge' => 'badge-danger', 'status' => 'Verificada', 'status_badge' => 'badge-success'],
							['code' => 'TAR-SEC-0110', 'title' => 'Ajuste de Ángulo en Cámaras Domo Pasillo 4', 'sub' => 'Eliminar punto ciego en góndola de perfumes de lujo', 'area' => 'CCTV Electrónico', 'branch' => 'Alkosto Suba', 'resp' => 'Sandra Milena', 'due' => '05 Nov 14:00', 'prio' => 'Media', 'prio_badge' => 'badge-info', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0111', 'title' => 'Verificación de Registro Termógrafo Cámaras', 'sub' => 'Cotejo de temperatura continua (2.8 °C) en lácteos y carnes', 'area' => 'Cadena de Frío', 'branch' => 'Alkosto Villas', 'resp' => 'Felipe Gómez', 'due' => 'Hoy 16:30', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'Verificada', 'status_badge' => 'badge-success']
						];
						@endphp

						@foreach($lpTasks as $t)
							<tr>
								<td><strong>{{ $t['code'] }}</strong></td>
								<td>
									<strong class="text-dark">{{ $t['title'] }}</strong><br>
									<span class="text-muted small">{{ $t['sub'] }}</span>
								</td>
								<td><span class="badge badge-light border text-dark">{{ $t['area'] }}</span></td>
								<td>{{ $t['branch'] }}</td>
								<td><i class="fa-solid fa-user-check text-muted mr-1"></i>{{ $t['resp'] }}</td>
								<td class="small font-weight-bold text-dark">{{ $t['due'] }}</td>
								<td><span class="badge {{ $t['prio_badge'] }} px-2 py-1">{{ $t['prio'] }}</span></td>
								<td><span class="badge {{ $t['status_badge'] }} px-2 py-1">{{ $t['status'] }}</span></td>
								<td class="text-center">
									@if($t['status'] === 'Verificada')
										<button class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="alert('Tarea verificada satisfactoriamente con acta digital.');">
											<i class="fa-solid fa-file-circle-check mr-1 text-success"></i> Acta
										</button>
									@else
										<button class="btn btn-xs btn-success font-weight-bold" onclick="alert('Marcando tarea como completada y registrando auditoría.');">
											<i class="fa-solid fa-check mr-1"></i> Completar
										</button>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANEL TAB 5: PLANES PREVENTIVOS DE MITIGACIÓN 360° (6)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-planes">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-shield text-success mr-2"></i>Planes Preventivos y Matriz de Mitigación de Riesgos (6)</h3>
					<p class="text-muted small m-0">Estrategias operativas preventivas en red contra merma desconocida, hurto de alta gama y fallas de frío.</p>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-success font-weight-bold" onclick="alert('Formulario de nuevo plan preventivo anual abierto.');">
						<i class="fa-solid fa-shield-plus mr-1"></i> Nuevo Plan Preventivo
					</button>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			{{-- KPIs de Efectividad Preventiva --}}
			<div class="row mb-3">
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PLANES ACTIVOS</span>
						<h4 class="font-weight-bold text-success mb-0">6 programas</h4>
						<small class="text-muted">Implementados en 10 sucursales</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">COBERTURA DE MITIGACIÓN</span>
						<h4 class="font-weight-bold text-primary mb-0">94.2%</h4>
						<small class="text-success font-weight-bold"><i class="fa-solid fa-shield-check mr-1"></i> Riesgos críticos cubiertos</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">REDUCCIÓN MENSUAL ESTIMADA</span>
						<h4 class="font-weight-bold text-dark mb-0">-$38,400</h4>
						<small class="text-success font-weight-bold">&darr; 28% merma evitada</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">AUDITORÍAS DE VERIFICACIÓN</span>
						<h4 class="font-weight-bold text-info mb-0">100% al día</h4>
						<small class="text-muted">Sin desviaciones normativas</small>
					</div>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th style="width: 140px;">Código Plan</th>
							<th>Programa Preventivo / Objetivo</th>
							<th>Riesgo Crítico Mitigado</th>
							<th>Medida Operativa Implementada</th>
							<th>Sucursales</th>
							<th>Meta de Merma</th>
							<th style="width: 110px;">Avance</th>
							<th>Estado</th>
							<th class="text-center" style="width: 120px;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@php
						$preventivePlans = [
							[
								'code' => 'PLN-PREV-2026-01',
								'title' => 'Protocolo Anti-Hurto en Dispositivos Móviles y Bóveda',
								'risk' => 'Hurto interno y sustracción de smartphones de gama alta (IMEI)',
								'measure' => 'Bóveda con doble cerradura biométrica, precinto con serial único y escaneo obligatorio 1:1 en cada traslado.',
								'branches' => '10 Tiendas (Red Completa)',
								'target' => '0 faltantes en equipos celulares',
								'progress' => 96,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-02',
								'title' => 'Cadena de Frío Inteligente y Control FEFO Dinámico',
								'risk' => 'Descomposición prematura y rotura de empaque en lácteos y carnes',
								'measure' => 'Sensores termógrafos IoT con alarma acústica en cámaras y rebaja preventiva automatizada a 4 días de caducar.',
								'branches' => 'Calle 80, Envigado, Tintal',
								'target' => 'Merma perecederos ≤ 1.10%',
								'progress' => 92,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-03',
								'title' => 'Blindaje de Línea de Cajas contra "Sweethearting"',
								'risk' => 'Omisión intencional de escaneo y descuentos manuales no autorizados en POS',
								'measure' => 'Cámaras cenitales IA sobre balanza de caja y requerimiento obligatorio de token gerencial para descuentos >15%.',
								'branches' => '10 Tiendas (Red Completa)',
								'target' => 'Diferencias de arqueo ≤ $15/día',
								'progress' => 95,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-04',
								'title' => 'Recepción Ciega Estandarizada en Muelle de Proveedores',
								'risk' => 'Recepción de bultos incompletos o mercancía averiada no declarada',
								'measure' => 'Conteo ciego con terminales Zebra TC57 antes de cotejo con Orden de Compra; rechazo automático ante faltante.',
								'branches' => 'Envigado, Cali Sur, Barranquilla',
								'target' => '100% reclamaciones a proveedor conciliadas',
								'progress' => 88,
								'status' => 'En Ejecución',
								'badge' => 'badge-primary'
							],
							[
								'code' => 'PLN-PREV-2026-05',
								'title' => 'Trazabilidad y Peritaje Técnico en Devoluciones de Clientes',
								'risk' => 'Reingreso de electrodomésticos quemados, manipulados o con seriales falsos',
								'measure' => 'Protocolo de cuarentena técnica 24h con registro fotográfico antes de emitir nota crédito o reposición.',
								'branches' => 'Calle 80, Bucaramanga, Suba',
								'target' => 'Cero garantías falsas aprobadas',
								'progress' => 100,
								'status' => 'Verificado',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-06',
								'title' => 'Protección Electrónica EAS en Pasillos de Licores y Conservas',
								'risk' => 'Hurto por ocultamiento de botellas premium y enlatados gourmet en piso de venta',
								'measure' => 'Collarines magnéticos blindados para botellas y etiquetas spider en cajas de alta gama con desactivador en caja.',
								'branches' => 'Villas, 170, Cali Norte',
								'target' => 'Merma en licores ≤ 0.70%',
								'progress' => 90,
								'status' => 'Activo',
								'badge' => 'badge-success'
							]
						];
						@endphp

						@foreach($preventivePlans as $pl)
							<tr>
								<td><strong>{{ $pl['code'] }}</strong></td>
								<td>
									<strong class="text-dark">{{ $pl['title'] }}</strong><br>
									<span class="text-muted small"><i class="fa-solid fa-bullseye mr-1 text-success"></i>Meta: {{ $pl['target'] }}</span>
								</td>
								<td><span class="badge badge-light border text-danger font-weight-bold">{{ $pl['risk'] }}</span></td>
								<td class="small text-dark">{{ $pl['measure'] }}</td>
								<td><span class="badge badge-secondary px-2 py-1">{{ $pl['branches'] }}</span></td>
								<td class="small font-weight-bold text-success">{{ $pl['target'] }}</td>
								<td>
									<div class="d-flex align-items-center">
										<div class="progress flex-fill mr-2" style="height: 6px;">
											<div class="progress-bar bg-success" style="width: {{ $pl['progress'] }}%;"></div>
										</div>
										<span class="small font-weight-bold">{{ $pl['progress'] }}%</span>
									</div>
								</td>
								<td><span class="badge {{ $pl['badge'] }} px-2 py-1">{{ $pl['status'] }}</span></td>
								<td class="text-center">
									<button class="btn btn-xs btn-outline-success font-weight-bold" onclick="alert('Detalle del plan {{ $pl['code'] }} y métricas de efectividad abiertas.');">
										<i class="fa-solid fa-chart-line mr-1"></i> Métricas
									</button>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     9. SLIDE-OVER DRAWER / MODAL: RESOLVER INCIDENCIA 360°
	     ==================================================================== -->
	<div class="lp-slideover-overlay" id="drawerResolveOverlay" onclick="closeResolveDrawer()">
		<div class="lp-slideover-panel" onclick="event.stopPropagation()">
			<div class="lp-drawer-header">
				<div>
					<h3 id="drawerCaseTitle">Expediente DEMO-001</h3>
					<span class="text-muted small" id="drawerCaseSubtitle">Celular Galaxy S24 128GB · Hallandale Beach</span>
				</div>
				<button type="button" class="lp-drawer-close" onclick="closeResolveDrawer()">&times;</button>
			</div>

			<!-- Flujo Guiado 8 Etapas -->
			<div class="lp-flow-stepper">
				<div class="lp-flow-step active"><span class="lp-flow-circle">1</span><span>Detectar</span></div>
				<div class="lp-flow-step active"><span class="lp-flow-circle">2</span><span>Contactar</span></div>
				<div class="lp-flow-step active"><span class="lp-flow-circle">3</span><span>Asignar</span></div>
				<div class="lp-flow-step active"><span class="lp-flow-circle">4</span><span>Acudir</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">5</span><span>Investigar</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">6</span><span>Corregir</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">7</span><span>Verificar</span></div>
				<div class="lp-flow-step"><span class="lp-flow-circle">8</span><span>Cerrar</span></div>
			</div>

			<div class="lp-drawer-body">
				<!-- Detalle Clave de la Incidencia -->
				<div class="p-3 border rounded" style="background:#f8fafc;">
					<div class="row text-center mb-2">
						<div class="col-4">
							<small class="text-muted d-block font-weight-bold">Diferencia</small>
							<span class="text-danger font-weight-bold" style="font-size:1.1rem;" id="drawerDiff">-2 u.</span>
						</div>
						<div class="col-4">
							<small class="text-muted d-block font-weight-bold">Valor en Riesgo</small>
							<span class="text-danger font-weight-bold" style="font-size:1.1rem;" id="drawerValue">$1,040.00</span>
						</div>
						<div class="col-4">
							<small class="text-muted d-block font-weight-bold">Ubicación</small>
							<span class="text-dark font-weight-bold" style="font-size:0.9rem;" id="drawerLoc">Despensa</span>
						</div>
					</div>
					<div class="small text-muted" id="drawerCustody">
						<i class="fa-solid fa-shield mr-1 text-success"></i> Custodia asignada: <strong>rgiovine</strong> (Gerente / Administrador)
					</div>
				</div>

				<!-- Acceso directo al Expediente Completo (15 Pasos) -->
				<div class="mt-2 mb-2">
					<a href="#" id="drawerFullCaseLink" class="btn btn-sm btn-block text-white font-weight-bold shadow-sm" style="background:#16a34a; border-radius:8px; height:38px; display:flex; align-items:center; justify-content:center; gap:8px;">
						<i class="fa-solid fa-folder-open"></i> Abrir Expediente Central 360° (15 Pasos)
					</a>
				</div>

				<!-- Acciones Inmediatas de Resolución -->
				<div>
					<h5 class="font-weight-bold mb-2" style="font-size:0.88rem;">Acciones Inmediatas del Manager</h5>
					<div class="lp-drawer-actions-grid">
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerCall()">
							<i class="fa-solid fa-phone text-success"></i> Llamar
						</button>
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerMsg()">
							<i class="fa-solid fa-comment-dots text-primary"></i> Mensaje
						</button>
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerCheckin()">
							<i class="fa-solid fa-location-crosshairs text-info"></i> Acudir (Check-in)
						</button>
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerTask()">
							<i class="fa-solid fa-clipboard-check text-warning"></i> Asignar Tarea
						</button>
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerCctv()">
							<i class="fa-solid fa-video text-secondary"></i> CCTV
						</button>
						<button type="button" class="lp-btn-drawer-action" onclick="triggerDrawerEscalate()">
							<i class="fa-solid fa-triangle-exclamation text-danger"></i> Escalar
						</button>
					</div>
				</div>

				<!-- Formulario de Resolución Definitiva -->
				<div class="p-3 border rounded bg-white">
					<h5 class="font-weight-bold mb-2" style="font-size:0.88rem;">Resolución y Cierre de Expediente</h5>
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Causa Raíz Determinada</label>
						<select class="form-control form-control-sm" id="drawerRootCause" style="border-radius:8px;">
							<option value="robo_externo">Hurto externo comprobado (con evidencia)</option>
							<option value="error_conteo">Diferencia administrativa por error de conteo</option>
							<option value="traslado_no_ingresado">Mercancía localizada en otra ubicación/tienda</option>
							<option value="averia_no_registrada">Avería o merma sin registrar en Kardex</option>
							<option value="fraude_interno">Apropiación o fraude interno investigado</option>
						</select>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Acción Correctiva Ejecutada</label>
						<textarea class="form-control form-control-sm" id="drawerResolutionNote" rows="2" placeholder="Describa la verificación física, evidencia analizada y decisión..." style="border-radius:8px;"></textarea>
					</div>
					<div class="d-flex gap-2">
						<button type="button" class="btn btn-sm btn-success font-weight-bold flex-grow-1" style="border-radius:8px; height:38px;" onclick="submitDrawerResolution('resuelto')">
							<i class="fa-solid fa-check mr-1"></i> Cerrar como Resuelto
						</button>
						<button type="button" class="btn btn-sm btn-outline-danger font-weight-bold" style="border-radius:8px; height:38px;" onclick="submitDrawerResolution('no_resuelto')">
							Confirmar Pérdida
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     10. MODALES OPERATIVOS (LLAMADA, MENSAJE, NUEVA INCIDENCIA, ETC.)
	     ==================================================================== -->
	@include('components.loss_strip')

	<!-- Modal Registrar Nueva Incidencia -->
	<div class="modal fade" id="modalNewIncident" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius:14px; border:1px solid #e2e8f0;">
				<div class="modal-header py-3 px-4">
					<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-plus-circle text-success mr-2"></i> Registrar Nueva Incidencia</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body px-4 py-3">
					<form id="formNewIncident" onsubmit="submitNewIncident(event)">
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Producto / Activo</label>
							<select class="form-control form-control-sm" id="newIncProduct" required>
								<option value="1">Celular Samsung Galaxy S24 128GB</option>
								<option value="2">Smartphone Galaxy Pro 5G 128GB</option>
								<option value="3">Televisor 55 pulgadas 4K</option>
								<option value="4">Galaxy A55 256GB Azul</option>
								<option value="5">Audífonos inalámbricos Pro</option>
							</select>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-bold">Sucursal</label>
							<select class="form-control form-control-sm" id="newIncBranch" required>
								@foreach($branches as $b)
									<option value="{{ $b->id }}">{{ $b->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-row mb-2">
							<div class="col-6">
								<label class="small font-weight-bold">Diferencia Faltante (u.)</label>
								<input type="number" step="1" class="form-control form-control-sm" id="newIncQty" value="1" required>
							</div>
							<div class="col-6">
								<label class="small font-weight-bold">Prioridad</label>
								<select class="form-control form-control-sm" id="newIncPriority">
									<option value="urgent">Urgente (P1)</option>
									<option value="high">Alta (P2)</option>
									<option value="medium" selected>Media (P3)</option>
								</select>
							</div>
						</div>
						<div class="form-group mb-3">
							<label class="small font-weight-bold">Observación / Hallazgo Inicial</label>
							<textarea class="form-control form-control-sm" id="newIncNote" rows="2" placeholder="Detalle ubicación, turno o discrepancia detectada..." required></textarea>
						</div>
						<button type="submit" class="btn btn-sm btn-success btn-block font-weight-bold" style="height:38px; border-radius:8px;">
							Registrar y Abrir Expediente
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

</div>


	<!-- ====================================================================
	     PANE: DETECCIÓN DE ANOMALÍAS DE INVENTARIO Y POS
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-anomalias">
		<div class="lp-table-card">
			<div class="lp-table-header">
				<div class="lp-table-title-group">
					<h2>Detección Inteligente de Anomalías Operativas</h2>
					<p>Monitoreo continuo de ajustes inusuales, devoluciones reiteradas, descuentos anormales y quiebres atípicos.</p>
				</div>
				<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
					<i class="fa-solid fa-shield-halved mr-1"></i> DAILY Action Center
				</a>
			</div>

			{{-- KPIs de Anomalías --}}
			<div class="p-3 bg-light border-bottom">
				<div class="row">
					<div class="col-md-3">
						<div class="card border-0 shadow-sm p-3 bg-white">
							<div class="text-muted small font-weight-bold">ANOMALÍAS ACTIVAS</div>
							<div class="h4 font-weight-bold text-danger mb-0">4 alertas</div>
							<div class="small text-muted mt-1"><i class="fa-solid fa-bell text-danger mr-1"></i> Detectadas en últimas 48h</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="card border-0 shadow-sm p-3 bg-white">
							<div class="text-muted small font-weight-bold">VALOR EN DISCREPANCIA</div>
							<div class="h4 font-weight-bold text-dark mb-0">$2,840.00</div>
							<div class="small text-muted mt-1">Impacto económico acumulado</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="card border-0 shadow-sm p-3 bg-white">
							<div class="text-muted small font-weight-bold">DEVOLUCIONES REITERADAS</div>
							<div class="h4 font-weight-bold text-warning mb-0">3 eventos</div>
							<div class="small text-muted mt-1">Mismo cliente / misma caja en 24h</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="card border-0 shadow-sm p-3 bg-white">
							<div class="text-muted small font-weight-bold">DESCUENTOS ATÍPICOS</div>
							<div class="h4 font-weight-bold text-info mb-0">2 ventas</div>
							<div class="small text-muted mt-1">Descuento manual &gt; 30% en POS</div>
						</div>
					</div>
				</div>
			</div>

			{{-- Tabla de Anomalías Detectadas --}}
			<div class="table-responsive p-3">
				<table class="table table-hover table-sm border mb-0">
					<thead class="thead-light">
						<tr>
							<th>Tipo de Anomalía</th>
							<th>Entidad / Documento</th>
							<th>Sucursal y Depto</th>
							<th>Desviación Detectada</th>
							<th class="text-right">Impacto ($)</th>
							<th>Nivel de Riesgo</th>
							<th>Fecha y Hora</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						<tr class="table-danger">
							<td><span class="badge badge-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Ajuste Inusual</span></td>
							<td><strong>AJU-004118</strong><br><small class="text-muted">Ajuste de inventario negativo (-12 unids)</small></td>
							<td>Alkosto Calle 80 · Bodega</td>
							<td>Frecuencia 4x superior al promedio semanal</td>
							<td class="text-right font-weight-bold text-danger">$1,040.00</td>
							<td><span class="badge badge-danger">CRÍTICO</span></td>
							<td class="small text-muted">03 nov 2026 14:11</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-eye mr-1"></i> Investigar
								</a>
							</td>
						</tr>
						<tr class="table-danger">
							<td><span class="badge badge-danger font-weight-bold"><i class="fa-solid fa-barcode mr-1"></i> Serial Duplicado en Venta</span></td>
							<td><strong>POS-TKT-9042</strong><br><small class="text-muted">IMEI 354892019948210</small></td>
							<td>Alkosto Cali Sur · Cajas</td>
							<td>Mismo IMEI vendido simultáneamente en 2 sucursales distintas</td>
							<td class="text-right font-weight-bold text-danger">$1,299.00</td>
							<td><span class="badge badge-danger">CRÍTICO</span></td>
							<td class="small text-muted">03 nov 2026 13:20</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-shield-halved mr-1"></i> Bloquear Serial
								</a>
							</td>
						</tr>
						<tr class="table-warning">
							<td><span class="badge badge-warning text-dark font-weight-bold"><i class="fa-solid fa-rotate-left mr-1"></i> Devolución Reiterada</span></td>
							<td><strong>DEV-2026-091</strong><br><small class="text-muted">Ticket POS #44901</small></td>
							<td>DAILY Norte · Cajas</td>
							<td>3 devoluciones de electrodoméstico sin ticket en 24h</td>
							<td class="text-right font-weight-bold text-warning">$580.00</td>
							<td><span class="badge badge-warning text-dark">ALTO</span></td>
							<td class="small text-muted">02 nov 2026 11:30</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-user-shield mr-1"></i> Auditar Cajero
								</a>
							</td>
						</tr>
						<tr class="table-warning">
							<td><span class="badge badge-warning text-dark font-weight-bold"><i class="fa-solid fa-percent mr-1"></i> Descuento Excesivo</span></td>
							<td><strong>POS-TKT-8812</strong><br><small class="text-muted">Caja 02 - Turno Tarde</small></td>
							<td>DAILY Central · Piso</td>
							<td>Descuento manual del 45% aplicado sin autorización supervisor</td>
							<td class="text-right font-weight-bold text-warning">$320.00</td>
							<td><span class="badge badge-warning text-dark">ALTO</span></td>
							<td class="small text-muted">01 nov 2026 18:45</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-lock mr-1"></i> Exigir PIN
								</a>
							</td>
						</tr>
						<tr>
							<td><span class="badge badge-info font-weight-bold"><i class="fa-solid fa-chart-line mr-1"></i> Variación Fantasma</span></td>
							<td><strong>SKU-1008</strong><br><small class="text-muted">Atún en Trozos 170g</small></td>
							<td>DAILY Central · Abarrotes</td>
							<td>Déficit recurrente de 8 latas por conteo de fin de semana</td>
							<td class="text-right font-weight-bold text-dark">$90.00</td>
							<td><span class="badge badge-info">MEDIO</span></td>
							<td class="small text-muted">01 nov 2026 09:15</td>
							<td class="text-center">
								<button class="btn btn-xs btn-outline-secondary py-0 px-2 font-weight-bold" onclick="alert('Conteo selectivo programado en góndola.');">
									<i class="fa-solid fa-barcode mr-1"></i> Recuento
								</button>
							</td>
						</tr>
						<tr class="table-warning">
							<td><span class="badge badge-warning text-dark font-weight-bold"><i class="fa-solid fa-box-open mr-1"></i> Devolución Sin Accesorios</span></td>
							<td><strong>DEV-2026-104</strong><br><small class="text-muted">AirPods Pro 2da Gen</small></td>
							<td>Alkosto Envigado · Post-Venta</td>
							<td>Devolución aceptada con estuche vacío sin almohadillas</td>
							<td class="text-right font-weight-bold text-warning">$249.00</td>
							<td><span class="badge badge-warning text-dark">ALTO</span></td>
							<td class="small text-muted">02 nov 2026 16:40</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-user-shield mr-1"></i> Ver Peritaje
								</a>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANE: CADENA DE CUSTODIA DE MERCANCÍA SENSIBLE (CELULARES & IMEI)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-custodia-imei">
		<div class="lp-table-card">
			<div class="lp-table-header">
				<div class="lp-table-title-group">
					<h2>Cadena de Custodia 360° · Mercancía Sensible (Celulares y Terminales)</h2>
					<p>Trazabilidad individualizada por IMEI, serial, manifiesto de ingreso, custodios responsables y firma de traspaso.</p>
				</div>
				<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Iniciando recuento ciego de celulares con escáner láser.');">
					<i class="fa-solid fa-barcode mr-1"></i> Recuento Ciego de IMEIs
				</button>
			</div>

			{{-- Buscador 360° por IMEI o Serial --}}
			<div class="p-3 bg-light border-bottom">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<label class="small font-weight-bold text-dark mb-1"><i class="fa-solid fa-magnifying-glass text-primary mr-1"></i> Búsqueda 360° por IMEI, Número de Serie o Código de Dispositivo:</label>
					<div class="input-group">
						<input type="text" class="form-control font-weight-bold" id="txtImeiSearch" value="354890123984711" placeholder="Ingresa los 15 dígitos del IMEI o serial del equipo...">
						<div class="input-group-append">
							<button class="btn btn-primary font-weight-bold" type="button" onclick="alert('Consultando trazabilidad de IMEI en toda la red...');">
								<i class="fa-solid fa-search mr-1"></i> Consultar Expediente 360°
							</button>
						</div>
					</div>
				</div>
			</div>

			{{-- Detalle del Dispositivo y Línea de Tiempo de Custodia --}}
			<div class="p-3">
				<div class="row">
					<div class="col-md-4 mb-3">
						<div class="card border p-3 bg-white shadow-sm h-100">
							<div class="text-center mb-3">
								<img src="{{ $U('/img/control_center/prod_galaxy_s24.svg') }}" style="width: 80px; height: 80px;" alt="S24">
								<h5 class="font-weight-bold text-dark mt-2 mb-0">Samsung Galaxy S24 128GB</h5>
								<div class="small text-muted">SKU: TEL-S24-128-BLK · Color Ónix</div>
							</div>
							<ul class="list-group list-group-flush small">
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">IMEI Primario:</span>
									<strong class="text-dark">354890123984711</strong>
								</li>
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">Número de Serie:</span>
									<strong>R5CW1098JXZ</strong>
								</li>
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">Costo Contable:</span>
									<strong>$520.00</strong>
								</li>
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">Ubicación Actual:</span>
									<span class="badge badge-info">Bóveda Blindada (Hallandale)</span>
								</li>
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">Custodio Actual:</span>
									<strong class="text-primary"><i class="fa-solid fa-user-shield mr-1"></i> Roberto Giovine</strong>
								</li>
								<li class="list-group-item d-flex justify-content-between px-0">
									<span class="text-muted">Estado del Activo:</span>
									<span class="badge badge-success"><i class="fa-solid fa-lock mr-1"></i> Custodiado y Verificado</span>
								</li>
							</ul>
							<div class="mt-3">
								<button class="btn btn-sm btn-outline-primary btn-block font-weight-bold" onclick="alert('Abriendo acta digital de transferencia de custodia.');">
									<i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Traspaso de Custodia
								</button>
							</div>
						</div>
					</div>

					<div class="col-md-8 mb-3">
						<div class="card border p-3 bg-white shadow-sm h-100">
							<h6 class="font-weight-bold text-dark mb-3"><i class="fa-solid fa-clock-rotate-left text-primary mr-1"></i> Cronología de Custodia y Trazabilidad del Terminal</h6>
							
							<div class="timeline-wrapper" style="border-left: 3px solid #e2e8f0; margin-left: 15px; padding-left: 20px;">
								<div class="mb-3 position-relative">
									<div class="small font-weight-bold text-dark">02 nov 2026 · 16:30 — Conteo Físico Cíclico en Bóveda</div>
									<div class="small text-muted">Escaneo láser conforme en recuento selectivo de celulares de alta gama. Precinto #P-8812 verificado.</div>
									<div class="small font-weight-600 text-primary mt-1"><i class="fa-solid fa-user-check mr-1"></i> Custodio: Roberto Giovine (Jefe Prevención)</div>
								</div>
								<div class="mb-3 position-relative">
									<div class="small font-weight-bold text-dark">01 nov 2026 · 11:20 — Recepción de Traslado Inter-Tienda (TRA-008331)</div>
									<div class="small text-muted">Recepción en tienda Hallandale Beach con verificación de IMEI individual e ingreso inmediato a caja fuerte blindada.</div>
									<div class="small font-weight-600 text-muted mt-1"><i class="fa-solid fa-truck mr-1"></i> Transportista: Transportes Blindados S.A. (Guía #TB-9912)</div>
								</div>
								<div class="mb-3 position-relative">
									<div class="small font-weight-bold text-dark">31 oct 2026 · 18:00 — Despacho desde Bodega Central</div>
									<div class="small text-muted">Alistamiento con doble chequeo de seguridad y embalaje en caja de seguridad inviolable.</div>
									<div class="small font-weight-600 text-muted mt-1"><i class="fa-solid fa-user mr-1"></i> Custodio emisor: Andrés Muelle (Logística Central)</div>
								</div>
								<div class="mb-1 position-relative">
									<div class="small font-weight-bold text-dark">30 oct 2026 · 10:15 — Recepción en Muelle Contra Orden PO-2026-041</div>
									<div class="small text-muted">Ingreso al sistema con manifiesto ASN #ASN-2026-081 proveedor Samsung Electronics Corp.</div>
									<div class="small font-weight-600 text-success mt-1"><i class="fa-solid fa-circle-check mr-1"></i> Registro de origen completado</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@push('pageScripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

	// 1. Gráfico: Tendencia de Diferencias (Área roja con degradado suave)
	var ctxTrend = document.getElementById('lp-trend-chart');
	if (ctxTrend) {
		var chartTrend = ctxTrend.getContext('2d');
		var gradRed = chartTrend.createLinearGradient(0, 0, 0, 200);
		gradRed.addColorStop(0, 'rgba(239, 68, 68, 0.35)');
		gradRed.addColorStop(0.7, 'rgba(239, 68, 68, 0.08)');
		gradRed.addColorStop(1, 'rgba(239, 68, 68, 0.00)');

		new Chart(chartTrend, {
			type: 'line',
			data: {
				labels: ['26 oct', '27 oct', '28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
				datasets: [{
					label: 'Valor en riesgo ($)',
					data: [1200, 1350, 1500, 1620, 1800, 1850, 2000, 2220],
					borderColor: '#ef4444',
					borderWidth: 2.2,
					backgroundColor: gradRed,
					fill: true,
					tension: 0.35,
					pointRadius: 4,
					pointBackgroundColor: '#ef4444',
					pointBorderColor: '#ffffff',
					pointBorderWidth: 1.5,
					pointHoverRadius: 6
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#0f172a',
						padding: 8,
						cornerRadius: 8,
						callbacks: {
							label: function(c) { return ' $' + Number(c.raw).toLocaleString(); }
						}
					}
				},
				scales: {
					x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 10 } } },
					y: {
						min: 1000,
						max: 2400,
						ticks: {
							stepSize: 200,
							color: '#64748b',
							font: { size: 10 },
							callback: function(v) { return '$' + v.toLocaleString(); }
						},
						grid: { color: '#f1f5f9' }
					}
				}
			}
		});
	}

	// 2. Gráfico: Productos con Mayor Exposición (Barras Verdes Verticales)
	var ctxProd = document.getElementById('lp-products-chart');
	if (ctxProd) {
		new Chart(ctxProd.getContext('2d'), {
			type: 'bar',
			data: {
				labels: ['Galaxy S24', 'Galaxy Pro', 'TV 55"', 'A55 256GB', 'A55 (2)', 'Audífonos'],
				datasets: [{
					label: 'Valor (USD)',
					data: [1040, 1040, 410, 310, 310, 90],
					backgroundColor: '#22c55e',
					borderRadius: 4,
					barThickness: 34
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#0f172a',
						callbacks: { label: function(c) { return ' $' + c.raw; } }
					}
				},
				scales: {
					x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 9.5 } } },
					y: {
						min: 0,
						max: 1200,
						ticks: { color: '#64748b', font: { size: 10 }, callback: function(v) { return '$' + v; } },
						grid: { color: '#f1f5f9' }
					}
				}
			}
		});
	}

	// 3. Gráfico: Exposición por Departamento (Donut 3 Colores)
	var ctxDept = document.getElementById('lp-department-chart');
	if (ctxDept) {
		new Chart(ctxDept.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: ['Electrónica y Telefonía', 'Carnes y aves', 'Lácteos y huevos'],
				datasets: [{
					data: [78.5, 15.9, 5.6],
					backgroundColor: ['#ef4444', '#f59e0b', '#22c55e'],
					borderWidth: 2,
					borderColor: '#ffffff',
					hoverOffset: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '72%',
				plugins: { legend: { display: false } }
			}
		});
	}
});

// Navegación entre Pestañas Segmentadas
function switchLpTab(tabName) {
	document.querySelectorAll('.lp-tab-item').forEach(function(b) { b.classList.remove('active'); });
	var activeBtn = document.getElementById('tab-btn-' + tabName);
	if (activeBtn) activeBtn.classList.add('active');

	document.querySelectorAll('.lp-tab-pane').forEach(function(p) { p.classList.add('d-none'); });
	var targetPane = document.getElementById('pane-' + tabName);
	if (targetPane) targetPane.classList.remove('d-none');
}

// Drawer RESOLVER 360°
var currentDrawerCase = null;

function openResolveDrawer(caseData) {
	currentDrawerCase = caseData;
	document.getElementById('drawerCaseTitle').textContent = 'Expediente ' + caseData.case_code;
	document.getElementById('drawerCaseSubtitle').textContent = caseData.product_name + ' · ' + caseData.branch_name;
	document.getElementById('drawerDiff').textContent = caseData.difference + ' u.';
	document.getElementById('drawerValue').textContent = '$' + Number(caseData.value_at_risk).toFixed(2);
	document.getElementById('drawerLoc').textContent = caseData.zone;
	document.getElementById('drawerCustody').innerHTML = '<i class="fa-solid fa-shield mr-1 text-success"></i> Custodia asignada: <strong>' + (caseData.responsible || 'rgiovine') + '</strong>';
	var fullCaseLink = document.getElementById('drawerFullCaseLink');
	if (fullCaseLink) {
		fullCaseLink.href = '{{ $U('/loss-prevention/case/') }}' + caseData.id;
	}

	var overlay = document.getElementById('drawerResolveOverlay');
	if (overlay) overlay.classList.add('open');
}

function closeResolveDrawer() {
	var overlay = document.getElementById('drawerResolveOverlay');
	if (overlay) overlay.classList.remove('open');
}

function triggerDrawerCall() {
	if (!currentDrawerCase) return;
	var phone = '+1 (555) 492-3810';
	var conf = confirm('¿Iniciar llamada con el responsable (' + (currentDrawerCase.responsible || 'rgiovine') + ')?\n\nNúmero: ' + phone);
	if (conf) {
		fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/call', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ dialed_number: phone, call_outcome: 'contestada', notes: 'Llamada iniciada desde panel de resolución' })
		}).then(function() {
			alert('Llamada registrada en la bitácora del expediente.');
		});
	}
}

function triggerDrawerMsg() {
	if (!currentDrawerCase) return;
	var msg = prompt('Escriba el mensaje para el supervisor responsable:');
	if (msg && msg.trim().length > 3) {
		fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/message', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: msg.trim() })
		}).then(function() {
			alert('Mensaje enviado y vinculado al expediente.');
		});
	}
}

function triggerDrawerCheckin() {
	if (!currentDrawerCase) return;
	var conf = confirm('Registrar check-in presencial en ' + currentDrawerCase.zone + '?');
	if (conf) {
		fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/checkin', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ zone_name: currentDrawerCase.zone })
		}).then(function() {
			alert('Check-in registrado con éxito.');
		});
	}
}

function triggerDrawerTask() {
	if (!currentDrawerCase) return;
	var desc = prompt('Describa la tarea a asignar en tienda:');
	if (desc && desc.trim().length > 3) {
		fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/tasks', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ task_type: 'recount', title: desc.trim(), due_at: new Date(Date.now() + 86400000).toISOString() })
		}).then(function() {
			alert('Tarea asignada en tienda.');
		});
	}
}

function triggerDrawerCctv() {
	alert('Módulo CCTV: Conexión con grabador de seguridad NVR-Hallandale activa.\nCámaras 04 y 07 vinculadas.');
}

function triggerDrawerEscalate() {
	if (!currentDrawerCase) return;
	var conf = confirm('¿Desea escalar este expediente al Gerente Regional?');
	if (conf) {
		fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/escalate', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ target_role: 'manager', notes: 'Escalado por superar umbral de 24h sin reconteo conforme' })
		}).then(function() {
			alert('Expediente escalado a Gerencia.');
		});
	}
}

function submitDrawerResolution(finalStatus) {
	if (!currentDrawerCase) return;
	var note = document.getElementById('drawerResolutionNote').value.trim();
	if (note.length < 5) {
		alert('Por favor redacte una nota de justificación mínima para el cierre.');
		return;
	}
	var cause = document.getElementById('drawerRootCause').value;
	fetch('{{ $U('/api/loss-control/cases/') }}' + currentDrawerCase.id + '/close', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ outcome: finalStatus, status: finalStatus, justification: note, notes: note, root_cause: cause })
	}).then(function(r) {
		return r.json();
	}).then(function(res) {
		alert(res.message || 'Expediente cerrado correctamente.');
		closeResolveDrawer();
		window.location.reload();
	}).catch(function(err) {
		alert('Expediente actualizado correctamente.');
		closeResolveDrawer();
		window.location.reload();
	});
}

function openNewIncidentModal() {
	$('#modalNewIncident').modal('show');
}

function submitNewIncident(e) {
	e.preventDefault();
	var prod = document.getElementById('newIncProduct').value;
	var branch = document.getElementById('newIncBranch').value;
	var qty = document.getElementById('newIncQty').value;
	var priority = document.getElementById('newIncPriority').value;
	var note = document.getElementById('newIncNote').value;

	fetch('{{ $U('/api/loss-control/cases') }}', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			product_id: prod,
			branch_id: branch,
			difference_qty: qty,
			priority: priority,
			notes: note
		})
	}).then(function(r) {
		return r.json();
	}).then(function(res) {
		if (res.success) {
			alert(res.message || 'Incidencia registrada con éxito.');
			$('#modalNewIncident').modal('hide');
			window.location.reload();
		} else {
			alert(res.error_message || 'Error al registrar la incidencia.');
		}
	}).catch(function(err) {
		alert('Error al conectar con el servidor.');
	});
}

function openActionMenu(caseId, caseCode) {
	var row = document.getElementById('case-row-' + caseId);
	var opt = prompt('Acciones para caso ' + caseCode + ':\n1. Llamar\n2. Mensaje\n3. Ver detalle\n\nElija opción (1-3):');
	if (opt === '1') {
		openCallModal(caseId, caseCode, 1, 'General');
	} else if (opt === '2') {
		openMsgModal(caseId, caseCode);
	} else if (opt === '3') {
		window.location.href = '{{ $U('/loss-prevention/case/') }}' + caseId;
	}
}

function openCctvModal() {
	alert('CCTV y Evidencias: 8 cámaras autorizadas en red.\nÚltima grabación archivada: Hace 14 min.');
}

function openRoundsModal() {
	alert('Rondas y Recorridos: Ronda de prevención activa en Hallandale Beach.\nSupervisor en recorrido.');
}

function filterBy(param) {
	window.location.href = '{{ $U('/loss-prevention') }}?' + param;
}


function filterCasesTable(criteria) {
	switchLpTab('expedientes');
	var rows = document.querySelectorAll('.lp-cases-table tbody tr');
	var matchCount = 0;
	rows.forEach(function(r) {
		var show = true;
		var diffEl = r.querySelector('.lp-diff-red');
		var diffText = diffEl ? diffEl.textContent.trim() : '';
		var statusEl = r.querySelector('.lp-badge-status');
		var statusText = statusEl ? statusEl.textContent.trim().toLowerCase() : '';
		var prioEl = r.querySelector('.lp-badge-priority');
		var prioText = prioEl ? prioEl.textContent.trim().toLowerCase() : '';

		if (criteria === 'all') {
			show = true;
		} else if (criteria === 'differences') {
			show = diffText.indexOf('-') !== -1;
		} else if (criteria === 'theft') {
			show = prioText.indexOf('alta') !== -1 || prioText.indexOf('media') !== -1;
		} else if (criteria === 'investigacion') {
			show = statusText.indexOf('investigaci') !== -1;
		} else if (criteria === 'reconteo') {
			show = statusText.indexOf('reconteo') !== -1;
		}

		r.style.display = show ? '' : 'none';
		if (show) matchCount++;
	});

	var titleP = document.querySelector('.lp-table-title-group p');
	if (titleP) {
		var label = criteria === 'all' ? 'Todos los casos' :
					criteria === 'differences' ? 'Casos con Diferencia de Conteo' :
					criteria === 'theft' ? 'Pérdidas Críticas / Estimadas' :
					criteria === 'investigacion' ? 'Expedientes En Investigación' : criteria;
		titleP.innerHTML = 'Filtro activo: <span class="badge badge-warning text-dark font-weight-bold">' + label + '</span> (' + matchCount + ' casos) · <a href="#" onclick="filterCasesTable(\'all\'); return false;" style="color:#16a34a; font-weight:bold; text-decoration:underline;">Restablecer filtro</a>';
	}
}

</script>
@endpush
@endsection
