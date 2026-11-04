@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Conteos Físicos 360° · Inventario y Almacenes')

@push('pageStyles')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@endpush

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$fmt = fn($v) => number_format(floatval($v), 0, '.', ',');

	$kpis = $kpis ?? [
		'scheduled' => ['value' => 12, 'formatted' => '12', 'trend' => '+20%', 'trend_type' => 'up', 'tone' => 'success', 'subtitle' => 'vs. periodo anterior'],
		'in_progress' => ['value' => 5, 'formatted' => '5', 'trend' => '+25%', 'trend_type' => 'up', 'tone' => 'primary', 'subtitle' => 'vs. periodo anterior'],
		'completed' => ['value' => 27, 'formatted' => '27', 'trend' => '+12%', 'trend_type' => 'up', 'tone' => 'success', 'subtitle' => 'vs. periodo anterior'],
		'overdue' => ['value' => 2, 'formatted' => '2', 'trend' => '-50%', 'trend_type' => 'down', 'tone' => 'danger', 'subtitle' => 'vs. periodo anterior'],
		'accuracy' => ['value' => 98.4, 'formatted' => '98.4%', 'trend' => '+0.8 pp', 'trend_type' => 'up', 'tone' => 'success', 'subtitle' => ''],
		'differences' => ['value' => 186, 'formatted' => '186 u.', 'trend' => '-14%', 'trend_type' => 'down', 'tone' => 'danger', 'subtitle' => 'vs. periodo anterior'],
		'value_reconciling' => ['value' => 4320.00, 'formatted' => '$4,320.00', 'trend' => '-18%', 'trend_type' => 'down', 'tone' => 'success', 'subtitle' => ''],
		'productivity' => ['value' => 382, 'formatted' => '382 u/h', 'trend' => '+9%', 'trend_type' => 'up', 'tone' => 'success', 'subtitle' => ''],
	];

	$complianceChart = $compliance_chart ?? [
		'labels' => ['26 oct', '27 oct', '28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
		'scheduled' => [16, 17, 21, 18, 25, 26, 27, 32],
		'completed' => [8, 10, 9, 10, 13, 16, 16, 21]
	];

	$typeDonut = $type_donut ?? [
		'total' => 44,
		'types' => [
			['name' => 'General', 'pct' => 36.4, 'count' => 16, 'color' => '#10b981'],
			['name' => 'Cíclico', 'pct' => 22.7, 'count' => 10, 'color' => '#3b82f6'],
			['name' => 'Selectivo', 'pct' => 13.6, 'count' => 6, 'color' => '#f59e0b'],
			['name' => 'Ciego', 'pct' => 11.4, 'count' => 5, 'color' => '#8b5cf6'],
			['name' => 'Extraordinario', 'pct' => 9.1, 'count' => 4, 'color' => '#06b6d4'],
			['name' => 'Por ubicación', 'pct' => 6.8, 'count' => 3, 'color' => '#94a3b8']
		]
	];

	$statusBreakdown = $status_breakdown ?? [
		'completed' => ['pct' => 61.4, 'count' => 27, 'color' => '#10b981'],
		'in_progress' => ['pct' => 11.4, 'count' => 5, 'color' => '#3b82f6'],
		'scheduled' => ['pct' => 27.3, 'count' => 12, 'color' => '#f59e0b']
	];

	$recentCounts = $recent_counts ?? [];
	$tasksAndAlerts = $tasks_and_alerts ?? [];
	$branches = $branches ?? [];
	$departments = $departments ?? [];
	$countTypes = $count_types ?? [];
	$f = $filters ?? [];
@endphp

@section('content')
<div class="dairy-container-fluid py-2">
	{{-- 1. CONTEXTO OPERATIVO ACTIVADO --}}
	@include('components.execution_context_bar')

	{{-- 2. ENCABEZADO CON BANNER FOTOGRÁFICO Y BOTONES DE ACCIÓN --}}
	<div class="lp-header-banner" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 55%, #e2fbe8 100%);">
		<div class="lp-banner-content">
			<div class="d-flex align-items-center gap-2 mb-1">
				<span class="lp-banner-tag" style="color: #008e48; font-weight: 800; font-size: 0.75rem; letter-spacing: 0.5px; text-transform: uppercase;">
					INVENTARIO Y ALMACENES
				</span>
			</div>
			<h1 class="lp-banner-title d-flex align-items-center gap-2" style="font-size: 1.6rem; font-weight: 900; color: #172033; margin: 4px 0;">
				<i class="fas fa-layer-group text-success mr-1"></i> Conteos Físicos 360°
			</h1>
			<p class="lp-banner-subtitle" style="font-size: 0.82rem; color: #64748b; margin: 0;">
				Planificación · Escaneo · Verificación · Diferencias · Conciliación
			</p>
		</div>

		<div class="d-flex align-items-center gap-2 mr-3 z-index-2">
			<button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="openModal('modalNewCount')">
				<i class="fas fa-plus mr-1"></i> Nuevo Conteo
			</button>
			<button type="button" class="btn btn-sm btn-white border font-weight-bold text-dark bg-white" onclick="openModal('modalQuickScan')">
				<i class="fas fa-barcode mr-1"></i> Escanear UPC
			</button>
			<button type="button" class="btn btn-sm btn-white border font-weight-bold text-dark bg-white" onclick="exportCountsToCsv()">
				<i class="fas fa-download mr-1"></i> Exportar
			</button>
			<button type="button" class="btn btn-sm btn-light border text-muted">
				<i class="fas fa-ellipsis-h"></i>
			</button>
		</div>

		<div class="lp-banner-hero-img">
			<img src="{{ $U('/img/control_center/daily_scan_phone.jpg') }}" alt="Escáner Terminal de Inventario"
				onerror="this.src='{{ $U('/img/control_center/daily_alert_scanner.jpg') }}'">
		</div>
	</div>

	{{-- 3. OCHO TARJETAS KPI (RÉPLICA EXACTA DE LA CAPTURA) --}}
	<div class="pi-kpis-grid-8">
		{{-- KPI 1: Programados --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-green"><i class="fas fa-calendar-alt"></i></div>
				<div class="mc-kpi-label">Programados</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $kpis['scheduled']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-success">
				<i class="fas fa-arrow-up"></i> {{ $kpis['scheduled']['trend'] }} {{ $kpis['scheduled']['subtitle'] }}
			</div>
		</div>

		{{-- KPI 2: En ejecución --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-blue"><i class="fas fa-play"></i></div>
				<div class="mc-kpi-label">En ejecución</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $kpis['in_progress']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-primary">
				<i class="fas fa-arrow-up"></i> {{ $kpis['in_progress']['trend'] }} {{ $kpis['in_progress']['subtitle'] }}
			</div>
		</div>

		{{-- KPI 3: Completados --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-green"><i class="fas fa-check-circle"></i></div>
				<div class="mc-kpi-label">Completados</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $kpis['completed']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-success">
				<i class="fas fa-arrow-up"></i> {{ $kpis['completed']['trend'] }} {{ $kpis['completed']['subtitle'] }}
			</div>
		</div>

		{{-- KPI 4: Vencidos --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
				<div class="mc-kpi-label">Vencidos</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value text-danger">{{ $kpis['overdue']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-danger">
				<i class="fas fa-arrow-up"></i> {{ $kpis['overdue']['trend'] }} {{ $kpis['overdue']['subtitle'] }}
			</div>
		</div>

		{{-- KPI 5: Exactitud --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-green"><i class="fas fa-bullseye"></i></div>
				<div class="mc-kpi-label">Exactitud</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value text-success">{{ $kpis['accuracy']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-success">
				<i class="fas fa-arrow-up"></i> {{ $kpis['accuracy']['trend'] }}
			</div>
		</div>

		{{-- KPI 6: Diferencias --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-red"><i class="fas fa-balance-scale"></i></div>
				<div class="mc-kpi-label">Diferencias</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value text-danger">{{ $kpis['differences']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-danger">
				<i class="fas fa-arrow-down"></i> {{ $kpis['differences']['trend'] }} {{ $kpis['differences']['subtitle'] }}
			</div>
		</div>

		{{-- KPI 7: Valor en conciliación --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-amber"><i class="fas fa-coins"></i></div>
				<div class="mc-kpi-label">Valor en conciliación</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $kpis['value_reconciling']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-success">
				<i class="fas fa-arrow-down"></i> {{ $kpis['value_reconciling']['trend'] }}
			</div>
		</div>

		{{-- KPI 8: Productividad --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-blue"><i class="fas fa-chart-line"></i></div>
				<div class="mc-kpi-label">Productividad</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $kpis['productivity']['formatted'] }}</div>
			</div>
			<div class="mc-kpi-subtext text-success">
				<i class="fas fa-arrow-up"></i> {{ $kpis['productivity']['trend'] }}
			</div>
		</div>
	</div>

	{{-- 4. SIETE PESTAÑAS OPERATIVAS --}}
	<div class="mc-tabs-bar mb-3">
		<button class="mc-tab-btn active" data-tab="tab-panel-general" type="button">
			<i class="fas fa-chart-bar"></i> Panel General
		</button>
		<button class="mc-tab-btn" data-tab="tab-programacion" type="button">
			<i class="fas fa-calendar-alt"></i> Programación
		</button>
		<button class="mc-tab-btn" data-tab="tab-conteo-en-vivo" type="button">
			<i class="fas fa-play-circle"></i> Conteo en Vivo
		</button>
		<button class="mc-tab-btn" data-tab="tab-conteos-ciegos" type="button">
			<i class="fas fa-layer-group"></i> Conteos Ciegos
		</button>
		<button class="mc-tab-btn" data-tab="tab-diferencias" type="button">
			<i class="fas fa-random"></i> Diferencias
		</button>
		<button class="mc-tab-btn" data-tab="tab-validacion-cierre" type="button">
			<i class="fas fa-clipboard-check"></i> Validación y Cierre
		</button>
		<button class="mc-tab-btn" data-tab="tab-historial" type="button">
			<i class="fas fa-history"></i> Historial
		</button>
	</div>

	{{-- 5. BARRA DE FILTROS GLOBALES --}}
	<form method="get" class="mc-filters-card mb-3" id="formFiltersCounts">
		<select name="branch_id" class="mc-filter-select" aria-label="Sucursal" onchange="this.form.submit()">
			<option value="">Todas las sucursales</option>
			@foreach($branches as $b)
			<option value="{{ $b['id'] }}" @if(intval($branch_id ?? 0) === intval($b['id'])) selected @endif>{{ $b['name'] }}</option>
			@endforeach
		</select>

		<select name="department" class="mc-filter-select" aria-label="Departamento" onchange="this.form.submit()">
			<option value="">Todos los departamentos</option>
			@foreach($departments as $dept)
			<option value="{{ $dept }}" @if(($f['department'] ?? '') === $dept) selected @endif>{{ $dept }}</option>
			@endforeach
		</select>

		<select name="count_type" class="mc-filter-select" aria-label="Tipo de Conteo" onchange="this.form.submit()">
			<option value="">Todos los tipos de conteo</option>
			@foreach($countTypes as $ct)
			<option value="{{ $ct }}" @if(($f['count_type'] ?? '') === $ct) selected @endif>{{ $ct }}</option>
			@endforeach
		</select>

		<div class="d-flex align-items-center bg-white border rounded px-2 py-1 gap-2" style="font-size: 0.75rem; color: #475569;">
			<i class="far fa-calendar-alt text-muted mr-1"></i>
			<span>26 oct 2026 – 02 nov 2026</span>
			<i class="fas fa-chevron-down text-muted ml-1" style="font-size: 0.65rem;"></i>
		</div>

		<div class="mc-filter-search ml-2">
			<i class="fas fa-search"></i>
			<input type="text" name="q" id="txtSearchCount" placeholder="Buscar conteo, producto o SKU..." value="{{ $f['q'] ?? '' }}">
		</div>

		<button type="button" class="btn btn-sm btn-light border font-weight-bold text-dark ml-auto" style="font-size: 0.75rem; border-radius: 8px;">
			<i class="fas fa-sliders-h mr-1"></i> Filtros
		</button>
	</form>

	{{-- =========================================================================
	     PANEL 1: PANEL GENERAL (RÉPLICA EXACTA DE LA CAPTURA)
	   ========================================================================= --}}
	<div class="mc-tab-pane" id="pane-tab-panel-general">
		{{-- FILA 1: CUMPLIMIENTO (40%), CONTEOS POR TIPO (35%), ESTADO Y ACCIONES (25%) --}}
		<div class="pi-top-row">
			{{-- Gráfico Cumplimiento de Conteos --}}
			<div class="pi-chart-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h3 class="ic-card-title">Cumplimiento de conteos</h3>
				</div>
				<div class="d-flex align-items-center gap-3 mb-2 small" style="font-size: 0.72rem;">
					<span class="d-flex align-items-center gap-1 font-weight-bold text-success">
						<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981;"></span> Programados
					</span>
					<span class="d-flex align-items-center gap-1 font-weight-bold text-primary">
						<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#3b82f6;"></span> Completados
					</span>
				</div>
				<div style="height: 140px; position: relative;">
					<canvas id="chartCompliance"></canvas>
				</div>
			</div>

			{{-- Donut Conteos por Tipo --}}
			<div class="pi-chart-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h3 class="ic-card-title">Conteos por tipo</h3>
				</div>
				<div class="pi-donut-container">
					<div class="position-relative" style="width: 105px; height: 105px; flex-shrink: 0;">
						<canvas id="chartCountsByType"></canvas>
						<div class="pi-donut-center">
							<div class="pi-donut-big">{{ $typeDonut['total'] }}</div>
							<div class="pi-donut-sub">Conteos</div>
						</div>
					</div>

					<div class="pi-donut-legend-grid">
						@foreach($typeDonut['types'] as $t)
						<div class="pi-donut-legend-row">
							<span>
								<span class="pi-donut-dot" style="background: {{ $t['color'] }};"></span>
								<strong>{{ $t['name'] }}</strong>
							</span>
							<span class="text-muted font-weight-bold">{{ $t['pct'] }}% ({{ $t['count'] }})</span>
						</div>
						@endforeach
					</div>
				</div>
			</div>

			{{-- Panel Derecho: Estado de Conteos y Acciones Rápidas --}}
			<div>
				{{-- Tarjeta Estado de Conteos --}}
				<div class="pi-chart-card mb-2" style="padding: 10px 14px;">
					<h4 class="font-weight-bold mb-2 text-dark" style="font-size: 0.8rem;">Estado de conteos</h4>
					<div class="pi-progress-bar-segmented">
						<div class="pi-seg-completed" style="width: {{ $statusBreakdown['completed']['pct'] }}%;"></div>
						<div class="pi-seg-in-progress" style="width: {{ $statusBreakdown['in_progress']['pct'] }}%;"></div>
						<div class="pi-seg-scheduled" style="width: {{ $statusBreakdown['scheduled']['pct'] }}%;"></div>
					</div>
					<div class="d-flex flex-column gap-1 small" style="font-size: 0.7rem;">
						<div class="d-flex justify-content-between">
							<span><span class="pi-donut-dot" style="background: #10b981;"></span> Completados</span>
							<strong>{{ $statusBreakdown['completed']['pct'] }}% ({{ $statusBreakdown['completed']['count'] }})</strong>
						</div>
						<div class="d-flex justify-content-between">
							<span><span class="pi-donut-dot" style="background: #3b82f6;"></span> En ejecución</span>
							<strong>{{ $statusBreakdown['in_progress']['pct'] }}% ({{ $statusBreakdown['in_progress']['count'] }})</strong>
						</div>
						<div class="d-flex justify-content-between">
							<span><span class="pi-donut-dot" style="background: #f59e0b;"></span> Programados</span>
							<strong>{{ $statusBreakdown['scheduled']['pct'] }}% ({{ $statusBreakdown['scheduled']['count'] }})</strong>
						</div>
					</div>
				</div>

				{{-- Acciones Rápidas (4 Botones) --}}
				<div class="pi-chart-card" style="padding: 10px 14px;">
					<h4 class="font-weight-bold mb-2 text-dark" style="font-size: 0.8rem;">Acciones rápidas</h4>
					<div class="pi-quick-actions-row">
						<button class="pi-quick-btn" type="button" onclick="openModal('modalNewCount')">
							<div class="pi-quick-icon"><i class="fas fa-calendar-plus"></i></div>
							<span class="pi-quick-label">Programar Conteo</span>
						</button>
						<button class="pi-quick-btn" type="button" onclick="openModal('modalQuickScan')">
							<div class="pi-quick-icon"><i class="fas fa-play-circle"></i></div>
							<span class="pi-quick-label">Iniciar Conteo</span>
						</button>
						<button class="pi-quick-btn" type="button" onclick="openModal('modalNewCount')">
							<div class="pi-quick-icon"><i class="fas fa-user-plus"></i></div>
							<span class="pi-quick-label">Asignar Personal</span>
						</button>
						<button class="pi-quick-btn" type="button" onclick="switchTab('tab-diferencias')">
							<div class="pi-quick-icon"><i class="fas fa-balance-scale"></i></div>
							<span class="pi-quick-label">Ver Diferencias</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		{{-- FILA 2: TABLA CONTEOS RECIENTES (75%) Y TAREAS Y ALERTAS (25%) --}}
		<div class="pi-mid-row">
			{{-- Tabla Conteos Recientes --}}
			<div class="mc-table-card p-3">
				<div class="d-flex align-items-center justify-content-between mb-3">
					<h3 class="mc-table-title m-0">
						<i class="fas fa-list text-success mr-2"></i> Conteos recientes
					</h3>
					<a href="javascript:void(0)" onclick="switchTab('tab-historial')" class="small font-weight-bold text-success">
						Ver todos <i class="fas fa-arrow-right ml-1"></i>
					</a>
				</div>

				<div class="table-responsive">
					<table class="table table-sm table-hover m-0" style="font-size: 0.74rem;">
						<thead class="bg-light text-muted">
							<tr>
								<th>N.° Conteo</th>
								<th>Sucursal</th>
								<th>Tipo</th>
								<th>Inicio</th>
								<th>Fin</th>
								<th class="text-right">Productos</th>
								<th class="text-center">Diferencias</th>
								<th class="text-center">Estado</th>
								<th>Responsable</th>
								<th class="text-center">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($recentCounts, 0, 5) as $c)
							<tr>
								<td class="font-weight-bold text-dark">{{ $c['code'] }}</td>
								<td>{{ $c['branch_name'] }}</td>
								<td><span class="badge {{ $c['type_badge'] }}">{{ $c['count_type'] }}</span></td>
								<td>{{ $c['start_time'] ? date('d M Y H:i', strtotime($c['start_time'])) : '—' }}</td>
								<td>{{ $c['end_time'] ? date('d M Y H:i', strtotime($c['end_time'])) : '—' }}</td>
								<td class="text-right font-weight-bold">{{ $c['products_formatted'] }}</td>
								<td class="text-center font-weight-bold {{ floatval($c['differences_units']) > 0 ? 'text-danger' : 'text-muted' }}">
									{{ $c['diff_label'] }}
								</td>
								<td class="text-center">
									<span class="badge {{ $c['status_meta']['badge'] }}">{{ $c['status_meta']['label'] }}</span>
								</td>
								<td>{{ $c['assigned_to'] }}</td>
								<td class="text-center text-nowrap">
									<button type="button" class="btn btn-xs btn-light border text-muted mr-1"
										onclick="openSessionDetails({{ $c['id'] }}, '{{ $c['code'] }}', '{{ $c['branch_name'] }}', '{{ $c['count_type'] }}')" title="Ver detalle de conteo">
										<i class="fas fa-eye text-primary"></i>
									</button>
									<div class="dropdown d-inline-block">
										<button class="btn btn-xs btn-light border text-muted" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
											<i class="fas fa-ellipsis-v"></i>
										</button>
										<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 font-weight-bold" style="font-size: 0.75rem;">
											<a class="dropdown-item" href="javascript:void(0)" onclick="openScanTerminal({{ $c['id'] }}, '{{ $c['code'] }}')">
												<i class="fas fa-barcode text-success mr-2"></i> Abrir Escáner de Conteo
											</a>
											<a class="dropdown-item" href="javascript:void(0)" onclick="orderRecount({{ $c['id'] }}, '{{ $c['code'] }}')">
												<i class="fas fa-redo text-warning mr-2"></i> Solicitar Reconteo
											</a>
											@if(($c['status'] ?? '') === 'in_review')
											<a class="dropdown-item text-primary" href="javascript:void(0)" onclick="reconcileCount({{ $c['id'] }}, '{{ $c['code'] }}')">
												<i class="fas fa-check-double mr-2"></i> Aprobar y Conciliar
											</a>
											@endif
										</div>
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			{{-- Panel Derecho: Tareas y Alertas (4 de la Foto) --}}
			<div class="mc-table-card p-3">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h4 class="font-weight-bold text-dark m-0" style="font-size: 0.8rem;">
						<i class="fas fa-sync text-success mr-1"></i> Tareas y alertas
					</h4>
				</div>

				<div>
					@foreach($tasksAndAlerts as $ta)
					<a href="{{ $U($ta['link']) }}" class="pi-task-item">
						<div class="pi-task-left">
							<i class="fas fa-exclamation-triangle"></i>
							<span class="text-truncate">{{ $ta['title'] }}</span>
						</div>
						<span class="pi-task-time">{{ $ta['time_ago'] }} <i class="fas fa-chevron-right ml-1"></i></span>
					</a>
					@endforeach
				</div>
			</div>
		</div>

		{{-- FILA 3: MÓDULOS RELACIONADOS --}}
		<div class="ic-connected-strip">
			<div class="d-flex align-items-center justify-content-between mb-1">
				<h4 class="font-weight-bold m-0" style="font-size: 0.84rem; color: #172033;">
					<i class="fas fa-project-diagram text-success mr-2"></i> Módulos relacionados
				</h4>
				<a href="{{ $U('/today') }}" class="small font-weight-bold text-success" style="font-size: 0.75rem;">
					Ver mapa del sistema <i class="fas fa-arrow-right ml-1"></i>
				</a>
			</div>

			<div class="ic-connected-grid">
				<a href="{{ $U('/inventory-control') }}" class="ic-connected-card">
					<div class="ic-connected-icon"><i class="fas fa-search-dollar"></i></div>
					<div class="ic-connected-texts">
						<span class="ic-connected-name">Control de Inventario</span>
						<span class="ic-connected-desc">Análisis y riesgos</span>
					</div>
				</a>

				<a href="{{ $U('/reconciliation') }}" class="ic-connected-card">
					<div class="ic-connected-icon"><i class="fas fa-balance-scale"></i></div>
					<div class="ic-connected-texts">
						<span class="ic-connected-name">Conciliación & Sensible</span>
						<span class="ic-connected-desc">Validación de diferencias</span>
					</div>
				</a>

				<a href="{{ $U('/merchandise') }}" class="ic-connected-card">
					<div class="ic-connected-icon"><i class="fas fa-exchange-alt"></i></div>
					<div class="ic-connected-texts">
						<span class="ic-connected-name">Control de Mercancías</span>
						<span class="ic-connected-desc">Movimientos y operaciones</span>
					</div>
				</a>

				<a href="{{ $U('/loss-prevention') }}" class="ic-connected-card">
					<div class="ic-connected-icon"><i class="fas fa-shield-alt"></i></div>
					<div class="ic-connected-texts">
						<span class="ic-connected-name">Prevención de Pérdidas</span>
						<span class="ic-connected-desc">Investigación y expedientes</span>
					</div>
				</a>

				<a href="{{ $U('/kardex') }}" class="ic-connected-card">
					<div class="ic-connected-icon"><i class="fas fa-chart-line"></i></div>
					<div class="ic-connected-texts">
						<span class="ic-connected-name">Kardex Valorizado</span>
						<span class="ic-connected-desc">Movimientos y costos</span>
					</div>
				</a>
			</div>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 2: PROGRAMACIÓN DE CONTEOS
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-programacion">
		<div class="mc-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h2 class="mc-table-title m-0">
						<i class="fas fa-calendar-alt text-success mr-2"></i> Planificación y Programación Inteligente de Conteos
					</h2>
					<p class="small text-muted m-0">Programe conteos cíclicos, ciegos, generales o selectivos asignando empleados y prioridades.</p>
				</div>
				<button class="btn btn-sm btn-success font-weight-bold" onclick="openModal('modalNewCount')">
					<i class="fas fa-plus mr-1"></i> Programar Nuevo Conteo
				</button>
			</div>

			<div class="row">
				<div class="col-md-4 mb-3">
					<div class="card p-3 border h-100">
						<h5 class="font-weight-bold text-dark"><i class="fas fa-sync text-primary mr-2"></i> Conteo Cíclico Automático</h5>
						<p class="small text-muted">Programa rondas semanales por rotación ABC: Clase A cada 7 días, Clase B cada 30 días, Clase C cada 90 días.</p>
						<button class="btn btn-sm btn-outline-primary mt-auto" onclick="toastr.success('Programación cíclica actualizada según matriz ABC');">
							Ejecutar Asignación ABC
						</button>
					</div>
				</div>

				<div class="col-md-4 mb-3">
					<div class="card p-3 border h-100">
						<h5 class="font-weight-bold text-dark"><i class="fas fa-eye-slash text-warning mr-2"></i> Conteos Ciegos por Riesgo</h5>
						<p class="small text-muted">Auditorías sorpresa donde el personal cuenta sin conocer el saldo teórico del sistema para evitar sesgos.</p>
						<button class="btn btn-sm btn-outline-warning mt-auto" onclick="openModal('modalNewCount')">
							Crear Orden Ciega
						</button>
					</div>
				</div>

				<div class="col-md-4 mb-3">
					<div class="card p-3 border h-100">
						<h5 class="font-weight-bold text-dark"><i class="fas fa-mobile-alt text-danger mr-2"></i> Conteo Sensible / Serializado</h5>
						<p class="small text-muted">Escaneo unitario obligatorio por código IMEI y número de serie con enlace directo a Control de Celulares.</p>
						<a href="{{ $U('/loss-prevention/devices') }}" class="btn btn-sm btn-outline-danger mt-auto">
							Abrir Control de Celulares
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 3: CONTEO EN VIVO (ESCANEO Y TERMINAL ZEBRA)
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-conteo-en-vivo">
		<div class="mc-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h2 class="mc-table-title m-0">
						<i class="fas fa-play-circle text-primary mr-2"></i> Terminal de Conteo en Vivo & Escáner UPC
					</h2>
					<p class="small text-muted m-0">Compatible con lectores industriales Zebra, cámaras móviles y lectores de código de barras.</p>
				</div>
				<div class="d-flex gap-2">
					<span class="badge badge-success p-2"><i class="fas fa-wifi mr-1"></i> Modo Online Conectado</span>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<div class="card p-3 border">
						<h5 class="font-weight-bold text-dark mb-2">Entrada de Escaneo</h5>
						<form id="formLiveScanItem">
							<div class="form-group mb-2">
								<label class="small font-weight-bold mb-1">Sesión de Conteo Activa:</label>
								<select class="form-control form-control-sm" id="selLiveSession">
									<option value="1">CNT-01482 · Hallandale Beach (En ejecución)</option>
									<option value="2">CNT-01481 · Miami Beach (Cíclico)</option>
								</select>
							</div>

							<div class="form-group mb-2">
								<label class="small font-weight-bold mb-1">Código de Barras / UPC / SKU / IMEI *</label>
								<div class="input-group input-group-sm">
									<input type="text" class="form-control" id="txtLiveScanBarcode" placeholder="Escanee o teclee el código..." autofocus required>
									<div class="input-group-append">
										<button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Registrar</button>
									</div>
								</div>
							</div>

							<div class="form-group mb-2">
								<label class="small font-weight-bold mb-1">Cantidad Física Contada:</label>
								<input type="number" step="1" class="form-control form-control-sm" id="txtLiveScanQty" value="1" min="0" required>
							</div>
						</form>
					</div>
				</div>

				<div class="col-md-6 mb-3">
					<div class="card p-3 border h-100 bg-light">
						<h5 class="font-weight-bold text-dark mb-2">Último Producto Contado</h5>
						<div id="liveScanResultBox" class="alert alert-info m-0 small">
							<i class="fas fa-barcode fa-2x float-left mr-2 text-primary"></i>
							<strong>Listo para escanear:</strong> Acerque el lector de código de barras o ingrese el UPC del producto para verificar existencias físicas.
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 4: CONTEOS CIEGOS
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-conteos-ciegos">
		<div class="mc-table-card p-4">
			<h2 class="mc-table-title mb-3"><i class="fas fa-layer-group text-warning mr-2"></i> Módulo de Conteos Ciegos (Anti-Sesgo)</h2>
			<div class="alert alert-warning small">
				<i class="fas fa-shield-alt mr-1"></i> <strong>Protocolo Ciego Activo:</strong> Las existencias esperadas permanecen ocultas para el operario que ejecuta el conteo. La variación únicamente se calcula tras cerrar la sesión por supervisión.
			</div>
			<table class="table table-sm table-striped small">
				<thead><tr><th>Código</th><th>Sucursal</th><th>Operario</th><th>Productos a Contar</th><th>Estado</th></tr></thead>
				<tbody>
					<tr><td>CNT-01482</td><td>Hallandale Beach</td><td>jmartinez</td><td>1,248 u.</td><td><span class="badge badge-warning">En ejecución</span></td></tr>
					<tr><td>CNT-01478</td><td>Hallandale Beach</td><td>slopez</td><td>1,520 u.</td><td><span class="badge badge-success">Conciliado</span></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 5: DIFERENCIAS Y DISCREPANCIAS
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-diferencias">
		<div class="mc-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3">
				<h2 class="mc-table-title m-0"><i class="fas fa-random text-danger mr-2"></i> Diferencias Detectadas en Conteos</h2>
				<span class="badge badge-danger p-2">186 u. pendientes de validar</span>
			</div>

			<table class="table table-sm table-hover small">
				<thead><tr><th>Conteo</th><th>Sucursal</th><th>Tipo</th><th>Diferencia</th><th>Impacto USD</th><th>Acción</th></tr></thead>
				<tbody>
					<tr><td>CNT-01481</td><td>Miami Beach</td><td>Cíclico</td><td class="font-weight-bold text-danger">-18 u.</td><td class="font-weight-bold text-danger">-$420.50</td><td><button class="btn btn-xs btn-outline-warning" onclick="toastr.info('Orden de reconteo enviada');">Reconteo</button></td></tr>
					<tr><td>CNT-01480</td><td>Aventura</td><td>General</td><td class="font-weight-bold text-danger">-43 u.</td><td class="font-weight-bold text-danger">-$1,280.00</td><td><button class="btn btn-xs btn-outline-primary" onclick="reconcileCount(3, 'CNT-01480')">Conciliar</button></td></tr>
					<tr><td>CNT-01479</td><td>Sunny Isles</td><td>Selectivo</td><td class="font-weight-bold text-danger">-6 u.</td><td class="font-weight-bold text-danger">-$150.25</td><td><button class="btn btn-xs btn-outline-warning" onclick="toastr.info('Orden de reconteo enviada');">Reconteo</button></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 6: VALIDACIÓN Y CIERRE (GOBERNANZA)
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-validacion-cierre">
		<div class="mc-table-card p-4">
			<h2 class="mc-table-title mb-3"><i class="fas fa-clipboard-check text-success mr-2"></i> Aprobación y Cierre Gerencial</h2>
			<div class="alert alert-info small mb-3">
				<i class="fas fa-lock mr-1"></i> <strong>Segregación de Funciones:</strong> Los supervisores y gerentes generales son los únicos autorizados para aprobar diferencias y asentar ajustes contables en el inventario permanente.
			</div>
			<div class="card p-3 border mb-3">
				<h5 class="font-weight-bold">CNT-01480 · Inventario General Aventura (En Revisión)</h5>
				<p class="small text-muted">Contado por mperez · Diferencias netas: -43 u. (-$1,280.00)</p>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-success font-weight-bold" onclick="reconcileCount(3, 'CNT-01480')"><i class="fas fa-check mr-1"></i> Aprobar y Asentar Ajuste</button>
					<button class="btn btn-sm btn-outline-warning" onclick="toastr.warning('Reconteo solicitado');"><i class="fas fa-redo mr-1"></i> Exigir Reconteo Ciego</button>
				</div>
			</div>
		</div>
	</div>

	{{-- =========================================================================
	     PANEL 7: HISTORIAL DE CONTEOS
	   ========================================================================= --}}
	<div class="mc-tab-pane d-none" id="pane-tab-historial">
		<div class="mc-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3">
				<h2 class="mc-table-title m-0"><i class="fas fa-history text-secondary mr-2"></i> Historial Completo de Conteos</h2>
				<button class="btn btn-sm btn-outline-success font-weight-bold" onclick="exportCountsToCsv()">
					<i class="fas fa-download mr-1"></i> Exportar Auditoría CSV
				</button>
			</div>
			<table class="table table-sm table-striped small" id="tblCountsHistory">
				<thead><tr><th>N.° Conteo</th><th>Sucursal</th><th>Tipo</th><th>Fecha</th><th>Productos</th><th>Diferencias</th><th>Exactitud</th><th>Estado</th></tr></thead>
				<tbody>
					@foreach($recentCounts as $rc)
					<tr>
						<td><strong>{{ $rc['code'] }}</strong></td>
						<td>{{ $rc['branch_name'] }}</td>
						<td><span class="badge {{ $rc['type_badge'] }}">{{ $rc['count_type'] }}</span></td>
						<td>{{ $rc['start_time'] ? date('d/m/Y', strtotime($rc['start_time'])) : '—' }}</td>
						<td>{{ $rc['products_formatted'] }} u.</td>
						<td class="{{ floatval($rc['differences_units']) > 0 ? 'text-danger font-weight-bold' : '' }}">{{ $rc['diff_label'] }}</td>
						<td>{{ $rc['accuracy_pct'] }}%</td>
						<td><span class="badge {{ $rc['status_meta']['badge'] }}">{{ $rc['status_meta']['label'] }}</span></td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

{{-- =========================================================================
     MODALES OPERATIVOS
   ========================================================================= --}}

{{-- Modal 1: Programar Nuevo Conteo --}}
<div class="modal fade" id="modalNewCount" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<form id="formNewPhysicalCount">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title font-weight-bold" style="font-size: 0.95rem;">
						<i class="fas fa-plus-circle mr-1"></i> Programar Nuevo Conteo Físico
					</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body p-3" style="font-size: 0.8rem;">
					<div class="form-group mb-2">
						<label class="font-weight-bold mb-1">Sucursal *</label>
						<select class="form-control form-control-sm" name="branch_id" required>
							@foreach($branches as $b)
							<option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-row">
						<div class="form-group col-md-6 mb-2">
							<label class="font-weight-bold mb-1">Tipo de Conteo *</label>
							<select class="form-control form-control-sm" name="count_type" required>
								<option value="Cíclico">Cíclico</option>
								<option value="Ciego">Ciego (Sin existencia visible)</option>
								<option value="General">General</option>
								<option value="Selectivo">Selectivo</option>
								<option value="Por ubicación">Por ubicación</option>
								<option value="Extraordinario">Extraordinario</option>
							</select>
						</div>
						<div class="form-group col-md-6 mb-2">
							<label class="font-weight-bold mb-1">Operario Asignado *</label>
							<input type="text" class="form-control form-control-sm" name="assigned_to" value="jmartinez" required>
						</div>
					</div>
					<div class="form-group mb-2">
						<label class="font-weight-bold mb-1">Instrucciones Operativas *</label>
						<textarea class="form-control form-control-sm" name="notes" rows="2" required placeholder="Indicar pasillos, niveles o familias a auditar..."></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light p-2">
					<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold">Emitir Orden de Conteo</button>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- Modal 2: Escaneo Rápido UPC --}}
<div class="modal fade" id="modalQuickScan" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title font-weight-bold" style="font-size: 0.95rem;">
					<i class="fas fa-barcode mr-1"></i> Escanear UPC / Identificar Producto
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-3" style="font-size: 0.8rem;">
				<div class="form-group mb-2">
					<label class="font-weight-bold mb-1">Ingrese o Escanee Código UPC / IMEI:</label>
					<input type="text" class="form-control" id="txtQuickScanUpc" placeholder="Apunte el lector láser aquí..." autofocus>
				</div>
				<div id="quickScanResult" class="border rounded p-3 bg-light text-center text-muted small">
					Esperando lectura de código...
				</div>
			</div>
			<div class="modal-footer bg-light p-2">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

{{-- =========================================================================
     JAVASCRIPT OPERATIVO & CHARTS
   ========================================================================= --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
	// 1. Manejo Fluido de Pestañas
	function switchTab(tabId) {
		var targetId = tabId.indexOf('tab-') === 0 ? tabId : ('tab-' + tabId);

		document.querySelectorAll('.mc-tab-btn').forEach(function (b) {
			b.classList.remove('active');
		});
		var activeBtn = document.querySelector('.mc-tab-btn[data-tab="' + targetId + '"]');
		if (activeBtn) {
			activeBtn.classList.add('active');
		}

		document.querySelectorAll('.mc-tab-pane').forEach(function (pane) {
			pane.classList.add('d-none');
		});

		var targetPane = document.getElementById('pane-' + targetId);
		if (targetPane) {
			targetPane.classList.remove('d-none');
		}

		var cleanName = targetId.replace('tab-', '');
		if (history.replaceState) {
			history.replaceState(null, null, '#' + cleanName);
		}
	}
	window.switchTab = switchTab;

	document.querySelectorAll('.mc-tab-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tabId = this.getAttribute('data-tab');
			switchTab(tabId);
		});
	});

	// Restaurar pestaña activa desde URL hash
	var initialHash = (window.location.hash || '').replace('#', '');
	if (initialHash) {
		switchTab(initialHash);
	}

	// 2. Gráfico Cumplimiento de Conteos (Líneas)
	var ctxComp = document.getElementById('chartCompliance');
	if (ctxComp) {
		new Chart(ctxComp, {
			type: 'line',
			data: {
				labels: {!! json_encode($complianceChart['labels']) !!},
				datasets: [
					{
						label: 'Programados',
						data: {!! json_encode($complianceChart['scheduled']) !!},
						borderColor: '#10b981',
						backgroundColor: 'rgba(16, 185, 129, 0.08)',
						borderWidth: 2,
						tension: 0.35,
						fill: true,
						pointRadius: 3,
						pointBackgroundColor: '#10b981'
					},
					{
						label: 'Completados',
						data: {!! json_encode($complianceChart['completed']) !!},
						borderColor: '#3b82f6',
						backgroundColor: 'rgba(59, 130, 246, 0.08)',
						borderWidth: 2,
						tension: 0.35,
						fill: true,
						pointRadius: 3,
						pointBackgroundColor: '#3b82f6'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				scales: {
					xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 9, fontColor: '#64748b' } }],
					yAxes: [{ gridLines: { color: '#f1f5f9' }, ticks: { beginAtZero: true, fontSize: 9, fontColor: '#64748b', maxTicksLimit: 6 } }]
				}
			}
		});
	}

	// 3. Donut Conteos por Tipo
	var ctxType = document.getElementById('chartCountsByType');
	if (ctxType) {
		new Chart(ctxType, {
			type: 'doughnut',
			data: {
				labels: {!! json_encode(array_column($typeDonut['types'], 'name')) !!},
				datasets: [{
					data: {!! json_encode(array_column($typeDonut['types'], 'count')) !!},
					backgroundColor: {!! json_encode(array_column($typeDonut['types'], 'color')) !!},
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutoutPercentage: 72,
				legend: { display: false }
			}
		});
	}

	// 4. Formulario Nuevo Conteo Físico
	var formNew = document.getElementById('formNewPhysicalCount');
	if (formNew) {
		formNew.addEventListener('submit', function (e) {
			e.preventDefault();
			var formData = new FormData(this);
			var data = {};
			formData.forEach(function (v, k) { data[k] = v; });

			toastr.info('Programando orden de conteo físico...');
			Grocy.Api.Post('inventory/counts', data, function (resp) {
				toastr.success(resp.message || 'Conteo programado exitosamente');
				$('#modalNewCount').modal('hide');
				setTimeout(function () { location.reload(); }, 800);
			}, function () {
				toastr.error('Error al programar conteo físico');
			});
		});
	}

	// 5. Escaneo en Vivo
	var formLiveScan = document.getElementById('formLiveScanItem');
	if (formLiveScan) {
		formLiveScan.addEventListener('submit', function (e) {
			e.preventDefault();
			var sessionId = document.getElementById('selLiveSession').value;
			var code = document.getElementById('txtLiveScanBarcode').value.trim();
			var qty = document.getElementById('txtLiveScanQty').value;

			toastr.info('Registrando lectura de código...');
			Grocy.Api.Post('inventory/counts/' + sessionId + '/scan', {
				code: code,
				amount: qty
			}, function (resp) {
				toastr.success(resp.message || 'Línea de conteo registrada');
				document.getElementById('liveScanResultBox').innerHTML = '<div class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> ' + resp.message + '</div>';
				document.getElementById('txtLiveScanBarcode').value = '';
				document.getElementById('txtLiveScanBarcode').focus();
			}, function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al registrar código';
				toastr.error(msg);
			});
		});
	}
});

function openModal(id) {
	$('#' + id).modal('show');
}

function openSessionDetails(id, code, branch, type) {
	toastr.info('Abriendo sesión de conteo ' + code + ' (' + branch + ')');
	switchTab('tab-conteo-en-vivo');
}

function openScanTerminal(id, code) {
	switchTab('tab-conteo-en-vivo');
	toastr.success('Terminal de escaneo activada para ' + code);
}

function orderRecount(id, code) {
	if (confirm('¿Desea solicitar una orden de reconteo ciego para ' + code + '?')) {
		toastr.warning('Orden de reconteo ciego emitida al equipo de auditoría');
	}
}

function reconcileCount(id, code) {
	if (confirm('¿Aprobar y conciliar formalmente el conteo ' + code + ' en el Kardex permanente?')) {
		Grocy.Api.Post('inventory/counts/' + id + '/reconcile', {}, function (resp) {
			toastr.success(resp.message || 'Conteo conciliado formalmente');
			setTimeout(function () { location.reload(); }, 800);
		}, function () {
			toastr.error('Error al conciliar conteo');
		});
	}
}

function exportCountsToCsv() {
	var table = document.getElementById('tblCountsHistory');
	if (!table) return;
	var rows = [];
	table.querySelectorAll('tr').forEach(function (tr) {
		var line = [];
		tr.querySelectorAll('th, td').forEach(function (td) {
			line.push('"' + td.innerText.replace(/"/g, '""').trim() + '"');
		});
		rows.push(line.join(','));
	});
	var csv = rows.join('\n');
	var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	var link = document.createElement('a');
	link.href = URL.createObjectURL(blob);
	link.download = 'daily_conteos_fisicos_' + new Date().toISOString().slice(0, 10) + '.csv';
	link.click();
}
</script>
@stop
