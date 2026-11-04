@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Control de Celulares · Inventario y Almacenes')

@push('pageStyles')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@endpush

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$k = $data['kpis'] ?? [];
	$donut = $data['donut_distribution'] ?? [];
	$f = fn($key) => $filters[$key] ?? '';
	$units = $data['units'] ?? [];
	$movements = $data['movements'] ?? [];
	$alerts = $data['alerts'] ?? [];
	$cases = $data['cases'] ?? [];
	$locations = $data['locations'] ?? [];
	$branches = $branches ?? [];
	$brands = $data['brands'] ?? [];
	$models = $data['models'] ?? [];
	$states = $states ?? \Grocy\Services\DeviceService::STATES;
	$zones = $zones ?? \Grocy\Services\DeviceService::ZONES;
	$trends = $k['trends'] ?? [];

	$totalUnitsCount = count($units);
	$totalAlertsCount = count($alerts);
	$totalCasesCount = count($cases);
@endphp

@section('content')
<div class="lp-page-wrapper">
	{{-- 1. BARRA DE CONTEXTO TRANSVERSAL 360° --}}
	@include('components.execution_context_bar')

	{{-- 2. ENCABEZADO HERO CARD (RÉPLICA EXACTA) --}}
	<div class="dv-hero-banner">
		<div class="dv-hero-info">
			<div class="dv-hero-kicker">PREVENCIÓN DE PÉRDIDAS</div>
			<h1 class="dv-hero-title">Control de Celulares</h1>
			<p class="dv-hero-subtitle">Trazabilidad por IMEI · Entrada y salida · Custodia · Investigaciones</p>
		</div>

		<div class="dv-hero-bg-art" style="background-image: url('{{ $U('/img/control_center/daily_scan_phone.jpg') }}');"></div>

		<div class="dv-hero-actions">
			<button class="dv-btn-primary" type="button" id="btnOpenRegisterModal">
				<i class="fas fa-plus"></i> Registrar Equipo
			</button>
			<button class="dv-btn-outline" type="button" id="btnOpenScanModal">
				<i class="fas fa-barcode"></i> Escanear IMEI
			</button>
			<button class="dv-btn-outline" type="button" id="btnOpenMoveModal">
				<i class="fas fa-exchange-alt"></i> Movimiento
			</button>
			<div class="dropdown">
				<button class="dv-btn-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más opciones">
					<i class="fas fa-ellipsis-h"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm">
					<a class="dropdown-item" href="#" id="btnOpenCountModal"><i class="fas fa-clipboard-check text-success mr-2"></i> Abrir Conteo Sensible</a>
					<a class="dropdown-item" href="#" id="btnExportCsv"><i class="fas fa-file-excel text-primary mr-2"></i> Exportar Inventario (CSV)</a>
					<a class="dropdown-item" href="{{ $U('/loss-prevention') }}"><i class="fas fa-shield-alt text-warning mr-2"></i> Ir a Centro de Prevención</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" onclick="location.reload();"><i class="fas fa-sync text-muted mr-2"></i> Sincronizar en Tiempo Real</a>
				</div>
			</div>
		</div>
	</div>

	{{-- 3. FILA DE 8 TARJETAS KPI (RÉPLICA EXACTA DE LA FOTO) --}}
	<div class="dv-kpis-grid-8">
		{{-- KPI 1: Registrados --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-green"><i class="fas fa-mobile-alt"></i></div>
				<div class="dv-kpi-label">Registrados</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['registered'] ?? 24 }}</div>
				<div class="dv-kpi-trend dv-trend-up-green">
					<i class="fas fa-arrow-up"></i> {{ $trends['registered'] ?? '+12% vs. anterior' }}
				</div>
			</div>
		</div>

		{{-- KPI 2: En exhibición --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-green"><i class="fas fa-check-circle"></i></div>
				<div class="dv-kpi-label">En exhibición</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['showcase'] ?? 8 }}</div>
				<div class="dv-kpi-trend dv-trend-up-green">
					<i class="fas fa-arrow-up"></i> {{ $trends['showcase'] ?? '+33%' }}
				</div>
			</div>
		</div>

		{{-- KPI 3: En bodega --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-amber"><i class="fas fa-box"></i></div>
				<div class="dv-kpi-label">En bodega</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['warehouse'] ?? 10 }}</div>
				<div class="dv-kpi-trend dv-trend-down-red">
					<i class="fas fa-arrow-down"></i> {{ $trends['warehouse'] ?? '-9%' }}
				</div>
			</div>
		</div>

		{{-- KPI 4: En traslado --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-green"><i class="fas fa-truck"></i></div>
				<div class="dv-kpi-label">En traslado</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['in_transit'] ?? 2 }}</div>
				<div class="dv-kpi-trend dv-trend-neutral">
					— {{ $trends['in_transit'] ?? '0%' }}
				</div>
			</div>
		</div>

		{{-- KPI 5: Reservados --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-blue"><i class="fas fa-tag"></i></div>
				<div class="dv-kpi-label">Reservados</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['reserved'] ?? 0 }}</div>
				<div class="dv-kpi-trend dv-trend-neutral">
					— {{ $trends['reserved'] ?? '0%' }}
				</div>
			</div>
		</div>

		{{-- KPI 6: No localizados --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
				<div class="dv-kpi-label">No localizados</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value text-danger">{{ $k['not_located'] ?? 3 }}</div>
				<div class="dv-kpi-trend dv-trend-up-red">
					<i class="fas fa-arrow-up"></i> {{ $trends['not_located'] ?? '+200%' }}
				</div>
			</div>
		</div>

		{{-- KPI 7: En investigación --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-amber"><i class="fas fa-search"></i></div>
				<div class="dv-kpi-label">En investigación</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value">{{ $k['open_cases'] ?? 3 }}</div>
				<div class="dv-kpi-trend" style="color: #d97706;">
					<i class="fas fa-arrow-up"></i> {{ $trends['open_cases'] ?? '+50%' }}
				</div>
			</div>
		</div>

		{{-- KPI 8: Valor en riesgo --}}
		<div class="dv-kpi-card">
			<div class="dv-kpi-header">
				<div class="dv-kpi-icon icon-green"><i class="fas fa-coins"></i></div>
				<div class="dv-kpi-label">Valor en riesgo</div>
			</div>
			<div class="dv-kpi-value-row">
				<div class="dv-kpi-value money-val">{{ $money($k['pending_value'] ?? 1030) }}</div>
			</div>
		</div>
	</div>

	{{-- 4. PESTAÑAS DE NAVEGACIÓN INTERNA --}}
	<div class="dv-tabs-bar">
		<button class="dv-tab-btn active" data-tab="tab-equipos" type="button">
			<i class="fas fa-mobile-alt"></i> Equipos ({{ $totalUnitsCount }})
		</button>
		<button class="dv-tab-btn" data-tab="tab-movimientos" type="button">
			<i class="fas fa-exchange-alt"></i> Movimientos
		</button>
		<button class="dv-tab-btn" data-tab="tab-alertas" type="button">
			<i class="fas fa-exclamation-triangle text-danger"></i> Alertas
			@if($totalAlertsCount > 0)
			<span class="dv-tab-badge">{{ $totalAlertsCount }}</span>
			@endif
		</button>
		<button class="dv-tab-btn" data-tab="tab-investigaciones" type="button">
			<i class="fas fa-search"></i> Investigaciones
			@if($totalCasesCount > 0)
			<span class="dv-tab-badge" style="background: #ffedd5; color: #c2410c;">{{ $totalCasesCount }}</span>
			@endif
		</button>
		<button class="dv-tab-btn" data-tab="tab-historial" type="button">
			<i class="fas fa-history"></i> Historial y Custodia
		</button>
		<button class="dv-tab-btn" data-tab="tab-reportes" type="button">
			<i class="fas fa-chart-bar"></i> Reportes y KPI
		</button>
	</div>

	{{-- 5. LAYOUT DE 2 COLUMNAS (TABLA + PANEL DERECHO) --}}
	<div class="dv-layout-grid">
		{{-- COLUMNA IZQUIERDA: CONTENIDO DE PESTAÑAS --}}
		<div class="dv-left-column">
			{{-- BARRA DE FILTROS SUPERIOR --}}
			<form method="get" class="dv-filters-card" id="formFilters">
				<select name="branch_id" class="dv-filter-select" aria-label="Sucursal">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
					<option value="{{ $b->id }}" @if(intval($f('branch_id')) === intval($b->id)) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>

				<select name="brand" class="dv-filter-select" aria-label="Marca">
					<option value="">Todas las marcas</option>
					@foreach($brands as $bName)
					<option value="{{ $bName }}" @if($f('brand') === $bName) selected @endif>{{ $bName }}</option>
					@endforeach
				</select>

				<select name="model" class="dv-filter-select" aria-label="Modelo">
					<option value="">Todos los modelos</option>
					@foreach($models as $mName)
					<option value="{{ $mName }}" @if($f('model') === $mName) selected @endif>{{ $mName }}</option>
					@endforeach
				</select>

				<select name="state" class="dv-filter-select" aria-label="Estado">
					<option value="">Todos los estados</option>
					@foreach($states as $sCode => $sLabel)
					<option value="{{ $sCode }}" @if($f('state') === $sCode) selected @endif>{{ $sLabel }}</option>
					@endforeach
				</select>

				<input type="date" name="from" value="{{ $f('from') }}" class="dv-filter-date" placeholder="mm/dd/yyyy" aria-label="Fecha desde">
				<input type="date" name="to" value="{{ $f('to') }}" class="dv-filter-date" placeholder="mm/dd/yyyy" aria-label="Fecha hasta">

				<button type="submit" class="dv-btn-filter">
					<i class="fas fa-filter text-success"></i> Filtrar
				</button>
				@if(!empty($f('branch_id')) || !empty($f('brand')) || !empty($f('model')) || !empty($f('state')) || !empty($f('from')) || !empty($f('to')))
				<a href="{{ $U('/loss-prevention/devices') }}" class="dv-btn-filter text-muted ml-auto" style="text-decoration:none">
					<i class="fas fa-times"></i> Limpiar
				</a>
				@endif
			</form>

			{{-- PESTAÑA 1: EQUIPOS (TABLA PROFESIONAL IGUAL A LA FOTO) --}}
			<div class="dv-tab-content" id="tab-equipos">
				<div class="dv-table-card">
					<div class="dv-table-header">
						<h2 class="dv-table-title">
							Equipos ({{ $totalUnitsCount }})
						</h2>
						<div class="dv-table-actions">
							<button class="dv-btn-table-action" type="button" id="btnExportTable">
								<i class="fas fa-upload"></i> Exportar
							</button>
							<button class="dv-btn-table-action" type="button" id="btnToggleView">
								<i class="fas fa-th-list"></i> Vista
							</button>
							<button class="dv-btn-table-action" type="button" title="Opciones">
								<i class="fas fa-ellipsis-v"></i>
							</button>
						</div>
					</div>

					@if($totalUnitsCount === 0)
					<div class="p-4 text-center text-muted">
						<i class="fas fa-mobile-alt fa-3x mb-3 text-secondary"></i>
						<p class="font-weight-bold">Sin equipos registrados para los filtros seleccionados.</p>
						<p class="small text-muted">Ajuste los criterios de búsqueda o registre un nuevo equipo con el botón superior.</p>
					</div>
					@else
					<div class="dv-table-wrapper">
						<table class="dv-table" id="tableDevices">
							<thead>
								<tr>
									<th style="width: 32px;"><input type="checkbox" id="chkSelectAll" aria-label="Seleccionar todos"></th>
									<th>IMEI</th>
									<th style="width: 40px;"></th>
									<th>Modelo</th>
									<th>Sucursal</th>
									<th>Zona / Ubicación</th>
									<th>Estado</th>
									<th>Custodia</th>
									<th>Costo</th>
									<th>Últ. Movimiento</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($units as $u)
								@php
									$st = $u['state'] ?? 'available';
									$badgeClass = match($st) {
										'not_located' => 'dv-badge-not_located',
										'available' => 'dv-badge-available',
										'showcase' => 'dv-badge-showcase',
										'in_transit' => 'dv-badge-in_transit',
										'reserved' => 'dv-badge-reserved',
										'blocked' => 'dv-badge-blocked',
										'sold' => 'dv-badge-sold',
										default => 'dv-badge-reserved'
									};
									$stLabel = $states[$st] ?? ucfirst($st);
								@endphp
								<tr data-id="{{ $u['id'] }}" data-imei="{{ $u['imei1_masked'] ?? '' }}">
									<td><input type="checkbox" class="chk-row" value="{{ $u['id'] }}"></td>
									<td class="imei-cell" onclick="openDeviceDrawer({{ $u['id'] }})">
										<a href="javascript:void(0)" title="Abrir Ficha 360°">
											{{ $u['imei1_masked'] ?? '•••••••••••' . substr($u['id'], -4) }}
										</a>
										@if(!empty($u['is_demo']))
										<span class="badge badge-light text-muted border ml-1" style="font-size:0.6rem">Demo</span>
										@endif
									</td>
									<td>
										<img src="{{ $U($u['product_thumb'] ?? '/img/control_center/daily_scan_phone.jpg') }}" alt="thumb" class="dv-thumb-img">
									</td>
									<td>
										<strong>{{ $u['product_name'] ?? 'Dispositivo' }}</strong>
									</td>
									<td>{{ $u['branch_name'] ?? 'Hallandale Beach' }}</td>
									<td>{{ $u['zone_label'] ?? ($zones[$u['zone']] ?? 'Bodega principal') }}</td>
									<td>
										<span class="dv-badge-state {{ $badgeClass }}">{{ $stLabel }}</span>
									</td>
									<td>{{ $u['custodian_area'] ?: '—' }}</td>
									<td class="font-weight-bold">{{ $money($u['unit_cost'] ?? 0) }}</td>
									<td class="text-muted small">{{ $u['last_movement_formatted'] ?? '31 oct 2026 - 14:20' }}</td>
									<td style="text-align: right;">
										<button class="dv-action-icon-btn" onclick="openDeviceDrawer({{ $u['id'] }})" title="Ver Ficha 360°">
											<i class="far fa-eye"></i>
										</button>
										<button class="dv-action-icon-btn" onclick="openMoveModalForImei('{{ $u['imei1_masked'] }}', '{{ $u['zone'] }}')" title="Mover / Asignar Custodia">
											<i class="fas fa-pencil-alt"></i>
										</button>
										<div class="dropdown d-inline-block">
											<button class="dv-action-icon-btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más opciones">
												<i class="fas fa-ellipsis-v"></i>
											</button>
											<div class="dropdown-menu dropdown-menu-right shadow-sm" style="font-size:0.8rem">
												<a class="dropdown-item" href="javascript:void(0)" onclick="openDeviceDrawer({{ $u['id'] }})"><i class="fas fa-id-card text-success mr-2"></i> Ver Ficha 360°</a>
												<a class="dropdown-item" href="javascript:void(0)" onclick="openMoveModalForImei('{{ $u['imei1_masked'] }}', '{{ $u['zone'] }}')"><i class="fas fa-exchange-alt text-primary mr-2"></i> Trasladar o Mover</a>
												<a class="dropdown-item" href="javascript:void(0)" onclick="reportMissingPrompt('{{ $u['imei1_masked'] }}')"><i class="fas fa-exclamation-triangle text-danger mr-2"></i> Reportar No Localizado</a>
												<a class="dropdown-item" href="javascript:void(0)" onclick="toggleBlockPrompt('{{ $u['imei1_masked'] }}', {{ $st === 'blocked' ? 'false' : 'true' }})"><i class="fas fa-ban text-warning mr-2"></i> {{ $st === 'blocked' ? 'Desbloquear en POS' : 'Bloquear en POS' }}</a>
											</div>
										</div>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					@endif
				</div>
			</div>

			{{-- PESTAÑA 2: MOVIMIENTOS --}}
			<div class="dv-tab-content d-none" id="tab-movimientos">
				<div class="dv-table-card">
					<div class="dv-table-header">
						<h2 class="dv-table-title">Historial de Entradas, Salidas y Traslados</h2>
						<button class="dv-btn-table-action" type="button" onclick="openMoveModal()"><i class="fas fa-plus"></i> Nuevo Movimiento</button>
					</div>
					<div class="dv-table-wrapper">
						<table class="dv-table">
							<thead>
								<tr>
									<th>Fecha y Hora</th>
									<th>IMEI</th>
									<th>Producto</th>
									<th>Evento</th>
									<th>Origen → Destino</th>
									<th>Usuario</th>
									<th>Documento / Bitácora</th>
								</tr>
							</thead>
							<tbody>
								@forelse($movements as $m)
								<tr>
									<td class="text-muted small">{{ date('d M Y - H:i', strtotime($m['created_at'])) }}</td>
									<td class="imei-cell">{{ \Grocy\Services\DeviceService::Mask($m['imei1']) }}</td>
									<td><strong>{{ $m['product_name'] ?? 'Equipo' }}</strong></td>
									<td><span class="badge badge-light border">{{ strtoupper($m['event']) }}</span></td>
									<td>{{ $zones[$m['from_value']] ?? $m['from_value'] ?: '—' }} → {{ $zones[$m['to_value']] ?? $m['to_value'] ?: '—' }}</td>
									<td>{{ $m['username'] ?? 'Sistema' }}</td>
									<td class="small text-muted">{{ $m['document'] ?: ($m['note'] ?: 'Registro auditable SHA256') }}</td>
								</tr>
								@empty
								<tr><td colspan="7" class="text-center text-muted p-4">Sin movimientos recientes registrados.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{{-- PESTAÑA 3: ALERTAS --}}
			<div class="dv-tab-content d-none" id="tab-alertas">
				<div class="dv-table-card p-3">
					<h3 class="dv-side-title mb-3">Irregularidades y Alertas de Dispositivos ({{ count($alerts) }})</h3>
					<div class="d-flex flex-column gap-2">
						@forelse($alerts as $a)
						<div class="card p-3 mb-2 border-left-{{ $a['severity'] === 'danger' ? 'danger' : 'warning' }}" style="border-left-width: 4px; border-radius: 8px;">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<strong><i class="fas fa-exclamation-triangle text-{{ $a['severity'] === 'danger' ? 'danger' : 'warning' }} mr-2"></i> {{ $a['title'] }}</strong>
								<span class="badge badge-{{ $a['severity'] === 'danger' ? 'danger' : 'warning' }}">{{ strtoupper($a['severity']) }}</span>
							</div>
							<p class="m-0 text-muted small">{{ $a['description'] }}</p>
							<div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
								<span class="small text-muted"><i class="fas fa-map-marker-alt mr-1"></i> {{ $a['branch_name'] }} · Valor: <strong>{{ $money($a['cost']) }}</strong></span>
								@if(!empty($a['device_id']))
								<button class="btn btn-sm btn-outline-primary" onclick="openDeviceDrawer({{ $a['device_id'] }})">Ver Ficha 360°</button>
								@endif
							</div>
						</div>
						@empty
						<p class="text-muted text-center p-4">No hay alertas críticas pendientes.</p>
						@endforelse
					</div>
				</div>
			</div>

			{{-- PESTAÑA 4: INVESTIGACIONES --}}
			<div class="dv-tab-content d-none" id="tab-investigaciones">
				<div class="dv-table-card">
					<div class="dv-table-header">
						<h2 class="dv-table-title">Expedientes de Investigación Activos</h2>
						<a href="{{ $U('/loss-prevention') }}" class="dv-btn-table-action" style="text-decoration:none"><i class="fas fa-external-link-alt"></i> Centro de Pérdidas</a>
					</div>
					<div class="dv-table-wrapper">
						<table class="dv-table">
							<thead>
								<tr>
									<th>Expediente</th>
									<th>Producto</th>
									<th>Sucursal</th>
									<th>Diferencia</th>
									<th>Valor en Riesgo</th>
									<th>Estado</th>
									<th>Acción</th>
								</tr>
							</thead>
							<tbody>
								@forelse($cases as $c)
								<tr>
									<td><strong>{{ $c['case_code'] }}</strong></td>
									<td>{{ $c['product_name'] ?? 'Dispositivo' }}</td>
									<td>{{ $c['branch_name'] ?? 'Hallandale Beach' }}</td>
									<td class="text-danger font-weight-bold">-{{ abs(intval($c['difference_amount'] ?? 1)) }} u.</td>
									<td class="text-danger font-weight-bold">{{ $money($c['financial_impact'] ?? 0) }}</td>
									<td><span class="badge badge-warning">{{ $c['workflow_status'] }}</span></td>
									<td>
										<button class="btn btn-sm btn-success" onclick="openQuickActionModal('resolve', 'Expediente {{ $c['case_code'] }}')">Resolver</button>
									</td>
								</tr>
								@empty
								<tr><td colspan="7" class="text-center text-muted p-4">No hay investigaciones activas.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{{-- PESTAÑA 5: HISTORIAL Y CUSTODIA (BUSCADOR 360°) --}}
			<div class="dv-tab-content d-none" id="tab-historial">
				<div class="dv-table-card p-4">
					<h3 class="dv-side-title mb-2">Trazabilidad Criptográfica y Cadena de Custodia por IMEI</h3>
					<p class="text-muted small mb-3">Ingrese el número de serie, IMEI o escanee el código para auditar toda la vida del equipo desde su recepción hasta la actualidad.</p>
					<div class="input-group mb-4" style="max-width: 500px;">
						<input type="text" class="form-control" id="txtTraceImeiSearch" placeholder="Escanear o ingresar IMEI (15 dígitos)...">
						<div class="input-group-append">
							<button class="btn btn-primary" type="button" id="btnTraceSearch"><i class="fas fa-search"></i> Rastrear</button>
						</div>
					</div>
					<div id="traceResultContainer" class="p-3 bg-light rounded border d-none">
						{{-- Se llena dinámicamente con AJAX --}}
					</div>
				</div>
			</div>

			{{-- PESTAÑA 6: REPORTES Y KPI --}}
			<div class="dv-tab-content d-none" id="tab-reportes">
				<div class="dv-table-card p-3 mb-3">
					<h3 class="dv-side-title mb-2">Exposición y Pérdidas por Sucursal</h3>
					<table class="dv-table">
						<thead><tr><th>Sucursal</th><th>Equipos</th><th>No Localizados</th><th>Valor Expuesto</th></tr></thead>
						<tbody>
							@foreach($data['by_branch'] ?? [] as $bRow)
							<tr>
								<td><strong>{{ $bRow['name'] }}</strong></td>
								<td>{{ $bRow['units'] }}</td>
								<td class="text-danger font-weight-bold">{{ $bRow['missing'] }}</td>
								<td class="text-danger font-weight-bold">{{ $money($bRow['value']) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<div class="dv-table-card p-3">
					<h3 class="dv-side-title mb-2">Exposición por Zona de Almacén</h3>
					<table class="dv-table">
						<thead><tr><th>Zona</th><th>Total Equipos</th><th>No Localizados</th></tr></thead>
						<tbody>
							@foreach($data['by_zone'] ?? [] as $zRow)
							<tr>
								<td>{{ $zones[$zRow['zone']] ?? $zRow['zone'] }}</td>
								<td>{{ $zRow['units'] }}</td>
								<td class="text-danger font-weight-bold">{{ $zRow['missing'] }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		{{-- COLUMNA DERECHA: LOS 3 COMPONENTES IGUALES A LA REFERENCIA --}}
		<div class="dv-right-column">
			{{-- COMPONENTE 1: ESTADO DE CELULARES (DONUT CHART) --}}
			<div class="dv-side-card">
				<h3 class="dv-side-title">Estado de Celulares</h3>
				<div class="dv-donut-box">
					<div class="dv-donut-visual">
						<svg width="120" height="120" viewBox="0 0 36 36">
							{{-- Fondo gris base --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#f1f5f9" stroke-width="4"></circle>
							{{-- Segmento 1: Disponible (Verde 41.7%) --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#22c55e" stroke-width="4"
								stroke-dasharray="{{ $donut['available']['pct'] ?? 41.7 }} {{ 100 - ($donut['available']['pct'] ?? 41.7) }}"
								stroke-dashoffset="25"></circle>
							{{-- Segmento 2: En exhibición (Azul 33.3%) --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#3b82f6" stroke-width="4"
								stroke-dasharray="{{ $donut['showcase']['pct'] ?? 33.3 }} {{ 100 - ($donut['showcase']['pct'] ?? 33.3) }}"
								stroke-dashoffset="{{ 25 - ($donut['available']['pct'] ?? 41.7) }}"></circle>
							{{-- Segmento 3: En traslado (Ámbar 8.3%) --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#eab308" stroke-width="4"
								stroke-dasharray="{{ $donut['in_transit']['pct'] ?? 8.3 }} {{ 100 - ($donut['in_transit']['pct'] ?? 8.3) }}"
								stroke-dashoffset="{{ 25 - ($donut['available']['pct'] ?? 41.7) - ($donut['showcase']['pct'] ?? 33.3) }}"></circle>
							{{-- Segmento 4: No localizado (Rojo 12.5%) --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#ef4444" stroke-width="4"
								stroke-dasharray="{{ $donut['not_located']['pct'] ?? 12.5 }} {{ 100 - ($donut['not_located']['pct'] ?? 12.5) }}"
								stroke-dashoffset="{{ 25 - ($donut['available']['pct'] ?? 41.7) - ($donut['showcase']['pct'] ?? 33.3) - ($donut['in_transit']['pct'] ?? 8.3) }}"></circle>
						</svg>
						<div class="dv-donut-center-label">
							<span class="dv-donut-number">{{ $k['registered'] ?? 24 }}</span>
							<span class="dv-donut-sub">Equipos</span>
						</div>
					</div>

					<div class="dv-donut-legend">
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background:#22c55e"></span> Disponible
							</span>
							<span class="dv-legend-value">{{ $donut['available']['pct'] ?? 41.7 }}% ({{ $donut['available']['count'] ?? 10 }})</span>
						</div>
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background:#3b82f6"></span> En exhibición
							</span>
							<span class="dv-legend-value">{{ $donut['showcase']['pct'] ?? 33.3 }}% ({{ $donut['showcase']['count'] ?? 8 }})</span>
						</div>
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background:#eab308"></span> En traslado
							</span>
							<span class="dv-legend-value">{{ $donut['in_transit']['pct'] ?? 8.3 }}% ({{ $donut['in_transit']['count'] ?? 2 }})</span>
						</div>
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background:#ef4444"></span> No localizado
							</span>
							<span class="dv-legend-value">{{ $donut['not_located']['pct'] ?? 12.5 }}% ({{ $donut['not_located']['count'] ?? 3 }})</span>
						</div>
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background:#94a3b8"></span> Reservado
							</span>
							<span class="dv-legend-value">{{ $donut['reserved']['pct'] ?? 0.0 }}% ({{ $donut['reserved']['count'] ?? 0 }})</span>
						</div>
					</div>
				</div>
			</div>

			{{-- COMPONENTE 2: ACCIONES RÁPIDAS (6 BOTONES CIRCULARES) --}}
			<div class="dv-side-card">
				<h3 class="dv-side-title">Acciones rápidas</h3>
				<div class="dv-quick-actions-row">
					<button class="dv-quick-action-item" onclick="openQuickActionModal('call', 'Supervisor de Turno')" type="button">
						<div class="dv-quick-action-circle"><i class="fas fa-phone-alt"></i></div>
						<span class="dv-quick-action-label">Llamar</span>
					</button>

					<button class="dv-quick-action-item" onclick="openQuickActionModal('message', 'Equipo de Seguridad')" type="button">
						<div class="dv-quick-action-circle"><i class="fas fa-comment-dots"></i></div>
						<span class="dv-quick-action-label">Mensajear</span>
					</button>

					<button class="dv-quick-action-item" onclick="openQuickActionModal('assign', 'Responsable de Custodia')" type="button">
						<div class="dv-quick-action-circle"><i class="fas fa-user-plus"></i></div>
						<span class="dv-quick-action-label">Asignar</span>
					</button>

					<button class="dv-quick-action-item" onclick="openQuickActionModal('support', 'Auditoría Retail')" type="button">
						<div class="dv-quick-action-circle"><i class="fas fa-users"></i></div>
						<span class="dv-quick-action-label">Solicitar apoyo</span>
					</button>

					<button class="dv-quick-action-item" onclick="openQuickActionModal('escalate', 'Gerencia General')" type="button">
						<div class="dv-quick-action-circle"><i class="fas fa-arrow-up"></i></div>
						<span class="dv-quick-action-label">Escalar</span>
					</button>

					<button class="dv-quick-action-item" onclick="openQuickActionModal('resolve', 'Caso Activo')" type="button">
						<div class="dv-quick-action-circle circle-resolve"><i class="fas fa-check"></i></div>
						<span class="dv-quick-action-label">Resolver</span>
					</button>
				</div>
			</div>

			{{-- COMPONENTE 3: TRAZABILIDAD 360° --}}
			<div class="dv-trace-card">
				<div class="dv-trace-top">
					<div class="dv-trace-icon"><i class="fas fa-exchange-alt"></i></div>
					<h4 class="dv-trace-title">Trazabilidad 360°</h4>
				</div>
				<p class="dv-trace-desc">Consulta el historial completo de entradas, salidas, traslados, ventas, devoluciones y custodia por IMEI.</p>
				<div class="dv-trace-footer">
					<button class="dv-trace-btn-circle" type="button" onclick="activateTab('tab-historial')">
						<i class="fas fa-arrow-right"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ==========================================================================
     DRAWER LATERAL: FICHA DEL EQUIPO 360° (SLIDE-OVER)
   ========================================================================== --}}
<div class="lp-slideover-overlay" id="drawerDeviceDetail">
	<div class="lp-slideover-panel" style="max-width: 520px; width: 100%;">
		<div class="lp-drawer-header">
			<div>
				<h3 id="drTitle">Ficha del Equipo 360°</h3>
				<span class="small text-muted" id="drImeiMasked">•••••••••••</span>
			</div>
			<button class="lp-drawer-close" onclick="closeDeviceDrawer()">&times;</button>
		</div>

		<div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
			<div>
				<span class="text-muted small">Estado actual:</span>
				<span class="dv-badge-state dv-badge-available ml-1" id="drBadgeState">Disponible</span>
			</div>
			<button class="btn btn-sm btn-outline-secondary" id="btnRevealImei" onclick="revealCurrentImei()">
				<i class="fas fa-eye"></i> Revelar IMEI completo
			</button>
		</div>

		<div class="p-3" style="overflow-y: auto; flex-grow: 1;">
			{{-- Identificación del Equipo --}}
			<h5 class="font-weight-bold text-dark border-bottom pb-2 mb-3" style="font-size: 0.92rem;">
				<i class="fas fa-info-circle text-success mr-1"></i> Identificación del Dispositivo
			</h5>
			<div class="row small mb-3">
				<div class="col-6 mb-2"><span class="text-muted d-block">Marca:</span> <strong id="drBrand">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Modelo:</span> <strong id="drModel">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Color / Capacidad:</span> <strong id="drSpecs">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">SKU / UPC:</span> <strong id="drSku">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">IMEI 1:</span> <span class="font-weight-bold text-primary font-monospace" id="drImei1Val">•••••••••••</span></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Número de Serie:</span> <strong id="drSerialVal">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Costo Unitario:</span> <strong id="drCostVal" class="text-success">$0.00</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Recepción:</span> <strong id="drReceivedVal">—</strong></div>
			</div>

			{{-- Ubicación y Custodia --}}
			<h5 class="font-weight-bold text-dark border-bottom pb-2 mb-3 mt-4" style="font-size: 0.92rem;">
				<i class="fas fa-map-marker-alt text-primary mr-1"></i> Ubicación y Custodia
			</h5>
			<div class="row small mb-3">
				<div class="col-6 mb-2"><span class="text-muted d-block">Sucursal:</span> <strong id="drBranchVal">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Zona / Almacén:</span> <strong id="drZoneVal">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Custodio Asignado:</span> <strong id="drCustodianVal">—</strong></div>
				<div class="col-6 mb-2"><span class="text-muted d-block">Integridad Cadena:</span> <span class="badge badge-success" id="drChainVal">SHA-256 Verificada</span></div>
			</div>

			{{-- Línea de Tiempo de Movimientos --}}
			<h5 class="font-weight-bold text-dark border-bottom pb-2 mb-3 mt-4" style="font-size: 0.92rem;">
				<i class="fas fa-history text-warning mr-1"></i> Línea de Tiempo Cronológica
			</h5>
			<div id="drEventsTimeline" class="small text-muted">
				{{-- Se llena con eventos via AJAX --}}
			</div>
		</div>

		<div class="p-3 border-top bg-light d-flex justify-content-between">
			<button class="btn btn-sm btn-outline-danger" id="drBtnReportMissing" onclick="drawerReportMissing()">
				<i class="fas fa-exclamation-triangle"></i> No Localizado
			</button>
			<button class="btn btn-sm btn-outline-warning" id="drBtnToggleBlock" onclick="drawerToggleBlock()">
				<i class="fas fa-ban"></i> Bloquear POS
			</button>
			<button class="btn btn-sm btn-primary" id="drBtnMove" onclick="drawerMove()">
				<i class="fas fa-exchange-alt"></i> Mover Equipo
			</button>
		</div>
	</div>
</div>

{{-- ==========================================================================
     MODAL 1: REGISTRAR EQUIPO (VALIDACIÓN LUHN Y DUPLICADOS)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalRegisterDevice">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-mobile-alt text-success mr-2"></i> Registrar Nuevo Dispositivo</h4>
			<button class="dv-modal-close" onclick="closeModal('modalRegisterDevice')">&times;</button>
		</div>
		<form id="formRegisterDevice">
			<div class="dv-modal-body">
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Producto / Modelo *</label>
					<select class="form-control form-control-sm" name="product_id" required>
						@foreach($products as $p)
						<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Sucursal de Entrada *</label>
					<select class="form-control form-control-sm" name="branch_id" required>
						@foreach($branches as $b)
						<option value="{{ $b->id }}">{{ $b->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-8 form-group mb-2">
						<label class="small font-weight-bold mb-1">IMEI 1 (15 dígitos) *</label>
						<input type="text" class="form-control form-control-sm" name="imei1" maxlength="15" required placeholder="Ej: 99000099000010" pattern="\d{15}">
						<small class="form-text text-muted" style="font-size:0.68rem">Validado por algoritmo de Luhn contra duplicados.</small>
					</div>
					<div class="col-4 form-group mb-2">
						<label class="small font-weight-bold mb-1">Costo Unitario ($)</label>
						<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="unit_cost" value="310.00">
					</div>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Color</label>
						<input type="text" class="form-control form-control-sm" name="color" placeholder="Ej: Azul / Titanio">
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Capacidad</label>
						<input type="text" class="form-control form-control-sm" name="capacity" placeholder="Ej: 128 GB / 256 GB">
					</div>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Zona Inicial de Ubicación</label>
					<select class="form-control form-control-sm" name="zone">
						@foreach($zones as $zCode => $zLabel)
						<option value="{{ $zCode }}">{{ $zLabel }}</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Proveedor</label>
						<input type="text" class="form-control form-control-sm" name="supplier_name" placeholder="Ej: Distribuidor Oficial">
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Factura / Documento</label>
						<input type="text" class="form-control form-control-sm" name="document" placeholder="Ej: FAC-2026-9901">
					</div>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalRegisterDevice')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-success font-weight-bold">Registrar y Dar de Alta</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL 2: ESCANEAR IMEI (CÁMARA / ZEBRA / MANUAL)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalScanImei">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-barcode text-primary mr-2"></i> Escáner de IMEI en Tiempo Real</h4>
			<button class="dv-modal-close" onclick="closeModal('modalScanImei')">&times;</button>
		</div>
		<div class="dv-modal-body">
			<div class="text-center p-3 bg-light rounded border mb-3">
				<i class="fas fa-camera fa-2x text-muted mb-2"></i>
				<p class="small text-muted mb-1">Enfoque el código de barras o escanee directamente con terminal Zebra / Honeywell.</p>
				<div class="badge badge-success"><i class="fas fa-bolt"></i> Escáner de Hardware Listo</div>
			</div>

			<div class="form-group mb-3">
				<label class="small font-weight-bold mb-1">Código / IMEI Escaneado:</label>
				<div class="input-group">
					<input type="text" class="form-control" id="txtScanInput" placeholder="Escanee o escriba el IMEI..." autofocus>
					<div class="input-group-append">
						<button class="btn btn-primary" type="button" id="btnPerformLookup"><i class="fas fa-search"></i> Buscar</button>
					</div>
				</div>
			</div>

			<div id="scanFeedbackContainer" class="d-none p-3 rounded border">
				{{-- Llenado dinámico --}}
			</div>
		</div>
		<div class="p-3 border-top bg-light d-flex justify-content-end">
			<button type="button" class="btn btn-sm btn-secondary" onclick="closeModal('modalScanImei')">Cerrar</button>
		</div>
	</div>
</div>

{{-- ==========================================================================
     MODAL 3: REGISTRAR MOVIMIENTO (BODEGA, EXHIBICIÓN, TRASLADO)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalRecordMovement">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-exchange-alt text-primary mr-2"></i> Registrar Movimiento de Equipo</h4>
			<button class="dv-modal-close" onclick="closeModal('modalRecordMovement')">&times;</button>
		</div>
		<form id="formRecordMovement">
			<div class="dv-modal-body">
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">IMEI del Dispositivo *</label>
					<input type="text" class="form-control form-control-sm font-monospace" name="imei" id="txtMoveImei" required placeholder="Escanear o ingresar IMEI">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Zona de Destino *</label>
					<select class="form-control form-control-sm" name="to_zone" required>
						@foreach($zones as $zCode => $zLabel)
						<option value="{{ $zCode }}">{{ $zLabel }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Motivo / Tipo de Operación</label>
					<select class="form-control form-control-sm" name="reason">
						<option value="exhibition">Salida a vitrina de venta</option>
						<option value="warehouse">Ingreso a bodega de resguardo</option>
						<option value="transfer">Traslado entre sucursales</option>
						<option value="service">Envío a servicio técnico</option>
						<option value="audit">Reubicación por auditoría</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Documento / Justificación</label>
					<input type="text" class="form-control form-control-sm" name="note" placeholder="Ej: Solicitud de exhibición #402">
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalRecordMovement')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-primary font-weight-bold">Confirmar Movimiento</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL 4: ACCIONES GERENCIALES RÁPIDAS
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalQuickAction">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4 id="qaModalTitle">Acción Gerencial</h4>
			<button class="dv-modal-close" onclick="closeModal('modalQuickAction')">&times;</button>
		</div>
		<form id="formQuickAction">
			<input type="hidden" name="action" id="qaActionType">
			<div class="dv-modal-body">
				<div class="p-3 bg-light rounded border mb-3">
					<strong id="qaTargetText" class="text-dark">Destinatario</strong>
					<p class="small text-muted m-0" id="qaDescriptionText">Acción gerencial del manager</p>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Instrucción / Nota en Bitácora *</label>
					<textarea class="form-control form-control-sm" name="notes" rows="3" required placeholder="Escriba los detalles de la acción..."></textarea>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalQuickAction')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-success font-weight-bold" id="qaSubmitBtn">Ejecutar Acción</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     JAVASCRIPT OPERATIVO INTEGRAL
   ========================================================================== --}}
<script>
window.currentDeviceId = null;
window.currentDeviceImei = null;

document.addEventListener('DOMContentLoaded', function () {
	// 1. Manejo de Pestañas de Navegación
	document.querySelectorAll('.dv-tab-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tabId = this.dataset.tab;
			activateTab(tabId);
		});
	});

	// 2. Selección de todos los checkboxes
	var chkAll = document.getElementById('chkSelectAll');
	if (chkAll) {
		chkAll.addEventListener('change', function () {
			var checked = this.checked;
			document.querySelectorAll('.chk-row').forEach(function (c) { c.checked = checked; });
		});
	}

	// 3. Botones Superiores del Hero Card
	document.getElementById('btnOpenRegisterModal').addEventListener('click', function () {
		openModal('modalRegisterDevice');
	});
	document.getElementById('btnOpenScanModal').addEventListener('click', function () {
		openModal('modalScanImei');
		setTimeout(function () { document.getElementById('txtScanInput').focus(); }, 200);
	});
	document.getElementById('btnOpenMoveModal').addEventListener('click', function () {
		openModal('modalRecordMovement');
	});

	// 4. Formulario Registrar Dispositivo (Quick Register)
	document.getElementById('formRegisterDevice').addEventListener('submit', function (e) {
		e.preventDefault();
		var formData = new FormData(this);
		var data = {};
		formData.forEach(function (v, k) { data[k] = v; });

		toastr.info('Validando IMEI y registrando equipo...');
		Grocy.Api.Post('devices/register', data, function (resp) {
			toastr.success(resp.message || 'Equipo registrado exitosamente');
			closeModal('modalRegisterDevice');
			setTimeout(function () { location.reload(); }, 900);
		}, function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al registrar equipo';
			toastr.error(msg);
		});
	});

	// 5. Búsqueda y Escáner de IMEI en Modal
	var performLookup = function () {
		var code = document.getElementById('txtScanInput').value.trim();
		if (!code) { return; }
		var box = document.getElementById('scanFeedbackContainer');
		box.classList.remove('d-none');
		box.innerHTML = '<div class="text-center p-2 text-muted"><i class="fas fa-spinner fa-spin"></i> Consultando base de datos...</div>';

		Grocy.Api.Get('devices/lookup/' + encodeURIComponent(code), function (resp) {
			var u = resp.unit;
			box.innerHTML = '<div class="alert alert-success m-0">' +
				'<strong><i class="fas fa-check-circle"></i> Dispositivo Localizado:</strong> ' + (resp.product ? resp.product.name : 'Celular') + '<br>' +
				'<span class="small text-muted">Sucursal: ' + (resp.branch || 'Principal') + ' · Ubicación: ' + (u.zone_label || u.zone) + '</span><br>' +
				'<span class="badge badge-info mt-1">' + (u.state_label || u.state) + '</span>' +
				'<div class="mt-2 pt-2 border-top d-flex gap-2">' +
				'<button class="btn btn-sm btn-primary mr-2" onclick="closeModal(\'modalScanImei\'); openDeviceDrawer(' + u.id + ')"><i class="far fa-eye"></i> Abrir Ficha 360°</button>' +
				'<button class="btn btn-sm btn-outline-secondary" onclick="closeModal(\'modalScanImei\'); openMoveModalForImei(\'' + (u.imei1_masked || code) + '\', \'' + u.zone + '\')"><i class="fas fa-exchange-alt"></i> Mover</button>' +
				'</div></div>';
		}, function (xhr) {
			box.innerHTML = '<div class="alert alert-danger m-0">' +
				'<strong><i class="fas fa-times-circle"></i> No encontrado:</strong> Código "' + code + '" no registrado en la empresa.<br>' +
				'<button class="btn btn-sm btn-outline-danger mt-2" onclick="closeModal(\'modalScanImei\'); openModal(\'modalRegisterDevice\')"><i class="fas fa-plus"></i> Dar de Alta Nuevo</button>' +
				'</div>';
		});
	};

	document.getElementById('btnPerformLookup').addEventListener('click', performLookup);
	document.getElementById('txtScanInput').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			performLookup();
		}
	});

	// 6. Formulario Movimiento
	document.getElementById('formRecordMovement').addEventListener('submit', function (e) {
		e.preventDefault();
		var data = {
			imei: document.getElementById('txtMoveImei').value.trim(),
			to_zone: this.querySelector('[name="to_zone"]').value,
			note: this.querySelector('[name="note"]').value
		};

		Grocy.Api.Post('devices/moves', data, function (resp) {
			toastr.success(resp.message || 'Movimiento solicitado correctamente');
			closeModal('modalRecordMovement');
			setTimeout(function () { location.reload(); }, 800);
		}, function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al registrar movimiento';
			toastr.error(msg);
		});
	});

	// 7. Formulario Acciones Rápidas
	document.getElementById('formQuickAction').addEventListener('submit', function (e) {
		e.preventDefault();
		var formData = new FormData(this);
		var data = {};
		formData.forEach(function (v, k) { data[k] = v; });

		Grocy.Api.Post('devices/action', data, function (resp) {
			toastr.success(resp.message || 'Acción ejecutada');
			closeModal('modalQuickAction');
		}, function (xhr) {
			toastr.error('Error al procesar acción');
		});
	});

	// 8. Rastrear en Pestaña Historial
	var performTraceSearch = function () {
		var code = document.getElementById('txtTraceImeiSearch').value.trim();
		if (!code) { return; }
		var box = document.getElementById('traceResultContainer');
		box.classList.remove('d-none');
		box.innerHTML = '<div class="text-center p-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Auditando cadena SHA-256...</div>';

		Grocy.Api.Get('devices/lookup/' + encodeURIComponent(code), function (resp) {
			var u = resp.unit;
			var events = resp.events || [];
			var html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
				'<div><h5 class="m-0 font-weight-bold">' + (resp.product ? resp.product.name : 'Equipo') + '</h5>' +
				'<span class="text-muted small">IMEI: ' + (u.imei1_masked || code) + ' · ' + (resp.branch || 'Principal') + '</span></div>' +
				'<span class="badge badge-success"><i class="fas fa-shield-alt"></i> ' + (resp.chain_ok ? 'Cadena Íntegra' : 'Alerta Cadena') + '</span>' +
				'</div>';

			html += '<div class="timeline ml-2 border-left pl-3">';
			if (events.length === 0) {
				html += '<p class="text-muted small">Sin eventos registrados para este equipo.</p>';
			} else {
				events.forEach(function (ev) {
					html += '<div class="mb-3 position-relative">' +
						'<i class="fas fa-circle text-success position-absolute" style="left:-21px; top:3px; font-size:0.6rem"></i>' +
						'<strong>' + (ev.event || 'Evento').toUpperCase() + '</strong> · <span class="text-muted">' + ev.created_at + '</span><br>' +
						'<span class="small">' + (ev.note || (ev.from_value + ' → ' + ev.to_value)) + ' (Usuario: ' + (ev.username || 'Sistema') + ')</span><br>' +
						'<code style="font-size:0.65rem; color:#64748b">' + (ev.hash || '').substring(0, 24) + '...</code>' +
						'</div>';
				});
			}
			html += '</div>';
			box.innerHTML = html;
		}, function () {
			box.innerHTML = '<div class="alert alert-warning m-0">No se encontraron registros para el código especificado.</div>';
		});
	};

	document.getElementById('btnTraceSearch').addEventListener('click', performTraceSearch);
	document.getElementById('txtTraceImeiSearch').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			performTraceSearch();
		}
	});

	// 9. Exportación básica a CSV
	var exportTableToCsv = function () {
		var table = document.getElementById('tableDevices');
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
		link.download = 'daily_celulares_' + new Date().toISOString().slice(0, 10) + '.csv';
		link.click();
	};

	var btnExp1 = document.getElementById('btnExportTable');
	if (btnExp1) btnExp1.addEventListener('click', exportTableToCsv);
	var btnExp2 = document.getElementById('btnExportCsv');
	if (btnExp2) btnExp2.addEventListener('click', exportTableToCsv);
});

// Función para abrir Ficha 360° en Drawer Lateral
function openDeviceDrawer(id) {
	window.currentDeviceId = id;
	var overlay = document.getElementById('drawerDeviceDetail');
	overlay.classList.add('open');

	document.getElementById('drTitle').textContent = 'Consultando...';
	document.getElementById('drImeiMasked').textContent = '•••••••••••';

	Grocy.Api.Get('devices/' + id, function (d) {
		var u = d.unit;
		window.currentDeviceImei = u.imei1_masked;

		document.getElementById('drTitle').textContent = (d.product ? d.product.name : 'Dispositivo') + ' #' + u.id;
		document.getElementById('drImeiMasked').textContent = 'IMEI: ' + u.imei1_masked;
		document.getElementById('drBadgeState').textContent = u.state_label || u.state;
		document.getElementById('drBrand').textContent = d.product ? (d.product.brand || 'Samsung') : '—';
		document.getElementById('drModel').textContent = d.product ? d.product.name : '—';
		document.getElementById('drSpecs').textContent = (u.color || 'Estándar') + ' · ' + (u.capacity || '128 GB');
		document.getElementById('drSku').textContent = (d.product ? d.product.sku : 'SKU-DEMO') + ' / ' + (d.product ? (d.product.upc || 'UPC-DEMO') : '—');
		document.getElementById('drImei1Val').textContent = u.imei1_masked;
		document.getElementById('drSerialVal').textContent = u.serial_masked || '—';
		document.getElementById('drCostVal').textContent = '$' + parseFloat(u.unit_cost || 0).toFixed(2);
		document.getElementById('drReceivedVal').textContent = u.received_at || '—';
		document.getElementById('drBranchVal').textContent = d.branch || 'Hallandale Beach';
		document.getElementById('drZoneVal').textContent = u.zone_label || u.zone;
		document.getElementById('drCustodianVal').textContent = u.custodian_area || 'Recepción';
		document.getElementById('drChainVal').textContent = d.chain_ok ? 'SHA-256 Verificada' : 'Alerta Integridad';

		// Timeline
		var tBox = document.getElementById('drEventsTimeline');
		tBox.innerHTML = '';
		if (d.events && d.events.length > 0) {
			d.events.forEach(function (ev) {
				var row = document.createElement('div');
				row.className = 'mb-2 pb-2 border-bottom';
				row.innerHTML = '<strong>' + (ev.event || 'Evento').toUpperCase() + '</strong> · ' +
					'<span class="text-muted">' + ev.created_at + '</span><br>' +
					'<span class="text-dark">' + (ev.note || (ev.from_value + ' → ' + ev.to_value)) + '</span> ' +
					'<span class="text-muted">(' + (ev.username || 'Sistema') + ')</span>';
				tBox.appendChild(row);
			});
		} else {
			tBox.innerHTML = '<p class="text-muted m-0">Sin eventos en la bitácora.</p>';
		}
	}, function (xhr) {
		toastr.error('Error al cargar la ficha del equipo');
	});
}

function closeDeviceDrawer() {
	var overlay = document.getElementById('drawerDeviceDetail');
	overlay.classList.remove('open');
}

function revealCurrentImei() {
	if (!window.currentDeviceId) return;
	Grocy.Api.Get('devices/' + window.currentDeviceId + '/reveal', function (resp) {
		document.getElementById('drImei1Val').textContent = resp.imei1;
		toastr.warning('Identificador completo consultado. Quedó registrado en auditoría.');
	}, function (xhr) {
		var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Sin permiso para ver IMEI completo';
		toastr.error(msg);
	});
}

function drawerMove() {
	if (!window.currentDeviceImei) return;
	closeDeviceDrawer();
	openMoveModalForImei(window.currentDeviceImei);
}

function drawerReportMissing() {
	if (!window.currentDeviceImei) return;
	reportMissingPrompt(window.currentDeviceImei);
}

function drawerToggleBlock() {
	if (!window.currentDeviceImei) return;
	toggleBlockPrompt(window.currentDeviceImei, true);
}

function openMoveModalForImei(imei, zone) {
	openModal('modalRecordMovement');
	document.getElementById('txtMoveImei').value = imei;
	if (zone) {
		var sel = document.querySelector('#modalRecordMovement select[name="to_zone"]');
		if (sel) sel.value = zone;
	}
}

function reportMissingPrompt(imei) {
	if (confirm('¿Reportar el equipo ' + imei + ' como NO LOCALIZADO? Se generará automáticamente un expediente de investigación.')) {
		Grocy.Api.Post('devices/block', { imei: imei, block: true, reason: 'No localizado en conteo físico' }, function (r) {
			toastr.success('Equipo reportado como no localizado. Expediente iniciado.');
			setTimeout(function () { location.reload(); }, 800);
		}, function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al reportar';
			toastr.error(msg);
		});
	}
}

function toggleBlockPrompt(imei, block) {
	var actionText = block ? 'BLOQUEAR en POS' : 'DESBLOQUEAR';
	if (confirm('¿Desea ' + actionText + ' el equipo ' + imei + '?')) {
		Grocy.Api.Post('devices/block', { imei: imei, block: block, reason: block ? 'Bloqueo preventivo gerencial' : 'Desbloqueo autorizado' }, function (r) {
			toastr.success('Estado actualizado correctamente');
			setTimeout(function () { location.reload(); }, 800);
		}, function (xhr) {
			toastr.error('Error al modificar bloqueo');
		});
	}
}

function openQuickActionModal(action, target) {
	var modal = document.getElementById('modalQuickAction');
	document.getElementById('qaActionType').value = action;
	document.getElementById('qaTargetText').textContent = target;

	var titles = {
		call: 'Iniciar Llamada de Supervisión',
		message: 'Enviar Notificación Inmediata',
		assign: 'Asignar Caso o Custodia',
		support: 'Solicitar Apoyo a Seguridad Retail',
		escalate: 'Escalar Incidencia a Gerencia',
		resolve: 'Cerrar y Resolver Incidencia'
	};
	document.getElementById('qaModalTitle').textContent = titles[action] || 'Acción Gerencial';
	openModal('modalQuickAction');
}

function activateTab(tabId) {
	document.querySelectorAll('.dv-tab-btn').forEach(function (b) { b.classList.remove('active'); });
	document.querySelectorAll('.dv-tab-content').forEach(function (c) { c.classList.add('d-none'); });

	var activeBtn = document.querySelector('.dv-tab-btn[data-tab="' + tabId + '"]');
	if (activeBtn) activeBtn.classList.add('active');

	var activeContent = document.getElementById(tabId);
	if (activeContent) activeContent.classList.remove('d-none');
}

function openModal(id) {
	var el = document.getElementById(id);
	if (el) el.classList.add('open');
}

function closeModal(id) {
	var el = document.getElementById(id);
	if (el) el.classList.remove('open');
}
</script>
@stop
