@php require_frontend_packages(['chartjs']); @endphp
@extends('layout.default')

@section('title', 'Control Integral de Mercancías · Inventario y Almacenes')

@push('pageStyles')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@endpush

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$fmt = fn($v) => number_format(floatval($v), 0, '.', ',');

	$k1 = $d['kpi_photo_primary'] ?? [
		'total_products' => ['value' => 248632, 'variation' => '+5.2%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
		'exits_today' => ['value' => 12480, 'variation' => '+8.7%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
		'transfers_in_progress' => ['value' => 36, 'variation' => '+12.5%', 'is_up' => true, 'subtitle' => '5 sucursales'],
		'receptions_today' => ['value' => 24830, 'variation' => '+6.3%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
		'inventory_differences' => ['value' => 18, 'variation' => '+50%', 'is_up' => true, 'subtitle' => '4 sucursales'],
		'value_at_risk' => ['value' => 56780.00, 'variation' => '+18.4%', 'is_up' => true, 'subtitle' => 'Todas las sucursales'],
		'turnover_days' => ['value' => 23.4, 'variation' => '-5.7%', 'is_up' => false, 'subtitle' => 'Todas las sucursales'],
	];

	$k2 = $d['kpi_photo_secondary'] ?? [
		'available_stock' => 312540,
		'in_transit' => 8732,
		'reserved' => 6421,
		'quarantine' => 1248,
		'pending_adjustments' => 12,
		'active_traceability' => 98.7,
		'process_compliance' => 96.3,
	];

	$workflowStages = $d['workflow_stages'] ?? [];
	$officialMovements = $d['official_movements'] ?? [];
	$merchDonut = $d['merchandise_donut'] ?? ['total_label' => '248.6K', 'items' => []];
	$charts = $d['analytics_charts'] ?? [];
	$branches = $branches ?? [];
	$departments = $d['departments'] ?? [];
	$products = $products ?? [];
	$f = $f ?? [];

	$stockOverviewList = $d['stock_overview_data'] ?? [];
	$transfersList = $d['transfers_data'] ?? [];
	$differencesList = $d['differences_data'] ?? [];
	$adjustmentsList = $d['adjustments_data'] ?? [];
	$traceInitial = $d['trace_initial'] ?? null;
@endphp

@section('content')
<div class="lp-page-wrapper">
	{{-- 1. BARRA DE CONTEXTO TRANSVERSAL 360° --}}
	@include('components.execution_context_bar')

	{{-- 2. ENCABEZADO HERO CARD (RÉPLICA EXACTA) --}}
	<div class="mc-hero-banner">
		<div class="mc-hero-info">
			<div class="mc-hero-kicker">INVENTARIO Y ALMACENES</div>
			<h1 class="mc-hero-title">
				<i class="fas fa-boxes mc-hero-title-icon"></i> Control Integral de Mercancías
			</h1>
			<p class="mc-hero-subtitle">Entradas · Salidas · Traslados · Recepciones · Diferencias · Trazabilidad</p>
		</div>

		<div class="mc-hero-bg-art" style="background-image: url('{{ $U('/img/control_center/depto_bodega.jpg') }}');"></div>

		<div class="mc-hero-actions">
			<button class="dv-btn-primary" type="button" id="btnOpenNewMoveModal">
				<i class="fas fa-plus"></i> Registrar Movimiento
			</button>
			<button class="dv-btn-outline" type="button" id="btnOpenScanUpcModal">
				<i class="fas fa-barcode"></i> Escanear UPC
			</button>
			<button class="dv-btn-outline" type="button" id="btnExportFullReport">
				<i class="fas fa-chart-line"></i> Generar Reporte
			</button>
			<div class="dropdown">
				<button class="dv-btn-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más opciones">
					<i class="fas fa-ellipsis-h"></i>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm">
					<a class="dropdown-item" href="{{ $U('/reconciliation') }}"><i class="fas fa-balance-scale text-success mr-2"></i> Abrir Conciliación</a>
					<a class="dropdown-item" href="{{ $U('/transfers') }}"><i class="fas fa-truck text-primary mr-2"></i> Módulo de Traslados</a>
					<a class="dropdown-item" href="{{ $U('/inventory') }}"><i class="fas fa-clipboard-list text-warning mr-2"></i> Conteos Físicos</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" onclick="location.reload();"><i class="fas fa-sync text-muted mr-2"></i> Actualizar Flujos</a>
				</div>
			</div>
		</div>
	</div>

	{{-- BANNER DE DISTINCIÓN DE PROCESO (PROCESO B vs PROCESO A) --}}
	<div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius:12px; border-left:4px solid #10b981 !important;">
		<div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
			<div>
				<span class="badge badge-success text-uppercase font-weight-bold px-2 py-1 mb-1" style="font-size:0.72rem;">Proceso B: Registro de Inventario</span>
				<h5 class="font-weight-bold text-dark mb-0">Administración Regular de Stock y Movimientos</h5>
				<small class="text-muted">
					Operaciones: Entradas de compra, recepciones ASN, salidas, traslados entre almacenes, conteos físicos, diferencias y ajustes auditados.
				</small>
			</div>
			<div class="mt-2 mt-md-0 d-flex align-items-center" style="gap:8px;">
				<a href="{{ $U('/waste#tab-registro') }}" class="btn btn-sm btn-outline-danger font-weight-600" title="Ir al registro de bajas definitivas por merma">
					<i class="fa-solid fa-trash-can mr-1"></i>Ir a Proceso A: Registro de Merma
				</a>
				<a href="{{ $U('/waste') }}" class="btn btn-sm btn-outline-secondary font-weight-600">
					<i class="fa-solid fa-chart-pie mr-1 text-primary"></i>Cuadro de Control de Mermas
				</a>
			</div>
		</div>
	</div>

	{{-- 2. FILA DE 7 KPIS PRINCIPALES (RÉPLICA EXACTA) --}}
	<div class="mc-kpis-grid-7">
		{{-- KPI 1: Total de productos --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-green"><i class="fas fa-cube"></i></div>
				<div class="mc-kpi-label">Total de productos</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $fmt($k1['total_products']['value']) }}</div>
				<div class="mc-kpi-trend text-success"><i class="fas fa-arrow-up"></i> {{ $k1['total_products']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['total_products']['subtitle'] }}</div>
		</div>

		{{-- KPI 2: Salidas hoy --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-red"><i class="fas fa-sign-out-alt"></i></div>
				<div class="mc-kpi-label">Salidas hoy</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $fmt($k1['exits_today']['value']) }}</div>
				<div class="mc-kpi-trend text-danger"><i class="fas fa-arrow-up"></i> {{ $k1['exits_today']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['exits_today']['subtitle'] }}</div>
		</div>

		{{-- KPI 3: Traslados en proceso --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-blue"><i class="fas fa-truck"></i></div>
				<div class="mc-kpi-label">Traslados en proceso</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $fmt($k1['transfers_in_progress']['value']) }}</div>
				<div class="mc-kpi-trend text-primary"><i class="fas fa-arrow-up"></i> {{ $k1['transfers_in_progress']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['transfers_in_progress']['subtitle'] }}</div>
		</div>

		{{-- KPI 4: Recepciones hoy --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-amber"><i class="fas fa-box-open"></i></div>
				<div class="mc-kpi-label">Recepciones hoy</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $fmt($k1['receptions_today']['value']) }}</div>
				<div class="mc-kpi-trend text-warning"><i class="fas fa-arrow-up"></i> {{ $k1['receptions_today']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['receptions_today']['subtitle'] }}</div>
		</div>

		{{-- KPI 5: Diferencias de inventario --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
				<div class="mc-kpi-label">Diferencias de inventario</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value text-danger">{{ $k1['inventory_differences']['value'] }}</div>
				<div class="mc-kpi-trend text-danger"><i class="fas fa-arrow-up"></i> {{ $k1['inventory_differences']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['inventory_differences']['subtitle'] }}</div>
		</div>

		{{-- KPI 6: Valor en riesgo --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-purple"><i class="fas fa-tag"></i></div>
				<div class="mc-kpi-label">Valor en riesgo</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value" style="font-size: 1.15rem;">{{ $money($k1['value_at_risk']['value']) }}</div>
				<div class="mc-kpi-trend text-purple" style="color: #7e22ce;"><i class="fas fa-arrow-up"></i> {{ $k1['value_at_risk']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['value_at_risk']['subtitle'] }}</div>
		</div>

		{{-- KPI 7: Rotación (días) --}}
		<div class="mc-kpi-card">
			<div class="mc-kpi-top">
				<div class="mc-kpi-icon icon-cyan"><i class="fas fa-sync-alt"></i></div>
				<div class="mc-kpi-label">Rotación (días)</div>
			</div>
			<div class="mc-kpi-value-row">
				<div class="mc-kpi-value">{{ $k1['turnover_days']['value'] }}</div>
				<div class="mc-kpi-trend text-success"><i class="fas fa-arrow-down"></i> {{ $k1['turnover_days']['variation'] }}</div>
			</div>
			<div class="mc-kpi-subtext">{{ $k1['turnover_days']['subtitle'] }}</div>
		</div>
	</div>

	{{-- 5. TIRA DE MÉTRICAS SECUNDARIAS (7 MÉTRICAS COMPACTAS) --}}
	<div class="mc-secondary-strip">
		<div class="mc-sec-item" title="Existencias físicas en bodega descontando reservas y movimientos comprometidos">
			<i class="fas fa-warehouse mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Disponibilidad en bodega</span>
				<span class="mc-sec-value">{{ $fmt($k2['available_stock']) }}</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-truck-loading mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Productos en tránsito</span>
				<span class="mc-sec-value">{{ $fmt($k2['in_transit']) }}</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-clipboard-check mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Productos en reserva</span>
				<span class="mc-sec-value">{{ $fmt($k2['reserved']) }}</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-shield-virus mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Productos en cuarentena</span>
				<span class="mc-sec-value">{{ $fmt($k2['quarantine']) }}</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-sliders-h mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Ajustes pendientes</span>
				<span class="mc-sec-value">{{ $k2['pending_adjustments'] }}</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-certificate mc-sec-icon"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Trazabilidad activa</span>
				<span class="mc-sec-value text-success">{{ $k2['active_traceability'] }}%</span>
			</div>
		</div>

		<div class="mc-sec-item">
			<i class="fas fa-check-circle mc-sec-icon text-success"></i>
			<div class="mc-sec-text">
				<span class="mc-sec-label">Cumplimiento de procesos</span>
				<span class="mc-sec-value text-success">{{ $k2['process_compliance'] }}%</span>
			</div>
		</div>
	</div>

	{{-- 4. PESTAÑAS OPERATIVAS (RÉPLICA EXACTA DE LA FOTO) --}}
	<div class="mc-tabs-bar">
		<button class="mc-tab-btn active" data-tab="tab-movimientos" type="button">
			<i class="fas fa-exchange-alt"></i> Movimientos
		</button>
		<button class="mc-tab-btn" data-tab="tab-stock-mov" type="button">
			<i class="fas fa-boxes"></i> Stock & Movimientos
		</button>
		<button class="mc-tab-btn" data-tab="tab-traslados" type="button">
			<i class="fas fa-truck"></i> Traslados
		</button>
		<button class="mc-tab-btn" data-tab="tab-diferencias" type="button">
			<i class="fas fa-random"></i> Diferencias
		</button>
		<button class="mc-tab-btn" data-tab="tab-ajustes" type="button">
			<i class="fas fa-sliders-h"></i> Ajustes
		</button>
		<button class="mc-tab-btn" data-tab="tab-trazabilidad" type="button">
			<i class="fas fa-eye"></i> Trazabilidad
		</button>
		<button class="mc-tab-btn" data-tab="tab-recepcion-asn" type="button">
			<i class="fas fa-file-invoice text-primary"></i> Recepción ASN
		</button>
		<button class="mc-tab-btn" data-tab="tab-reportes-kpi" type="button">
			<i class="fas fa-chart-bar"></i> Reportes y KPI
		</button>
	</div>

	{{-- 8. BARRA DE FILTROS SUPERIOR --}}
	<form method="get" class="mc-filters-card" id="formFiltersMerch">
		<select name="branch_id" class="mc-filter-select" aria-label="Sucursal" onchange="this.form.submit()">
			<option value="">Todas las sucursales</option>
			@foreach($branches as $b)
			<option value="{{ $b->id }}" @if(intval($f['branch_id'] ?? 0) === intval($b->id)) selected @endif>{{ $b->name }}</option>
			@endforeach
		</select>

		<select name="department" class="mc-filter-select" aria-label="Departamento" onchange="this.form.submit()">
			<option value="">Todos los departamentos</option>
			@foreach($departments as $dept)
			<option value="{{ $dept }}" @if(($f['department'] ?? '') === $dept) selected @endif>{{ $dept }}</option>
			@endforeach
		</select>

		<select name="bucket" class="mc-filter-select" aria-label="Tipo de Movimiento" onchange="this.form.submit()">
			<option value="">Todos los tipos</option>
			<option value="receptions" @if(($f['bucket'] ?? '') === 'receptions') selected @endif>Recepciones</option>
			<option value="sales" @if(($f['bucket'] ?? '') === 'sales') selected @endif>Salidas / Ventas</option>
			<option value="transfers_out" @if(($f['bucket'] ?? '') === 'transfers_out') selected @endif>Traslados</option>
			<option value="adjustments" @if(($f['bucket'] ?? '') === 'adjustments') selected @endif>Ajustes</option>
		</select>

		<div class="d-flex align-items-center gap-1">
			<input type="text" class="mc-filter-input" value="26 oct 2026 - 02 nov 2026" style="width: 190px;" readonly>
		</div>

		<div class="input-group" style="width: 250px;">
			<input type="text" class="form-control form-control-sm mc-filter-input" placeholder="Buscar por producto, SKU, lote..." id="txtQuickSearch">
		</div>

		<button type="submit" class="dv-btn-filter">
			<i class="fas fa-filter text-success"></i> Filtros
		</button>

		<div class="ml-auto d-flex align-items-center gap-2">
			<button type="button" class="btn btn-sm btn-light border" title="Vista Cuadrícula / Tabla"><i class="fas fa-th"></i></button>
		</div>
	</form>

	{{-- 9. LAYOUT PRINCIPAL (2 COLUMNAS) --}}
	<div class="mc-layout-grid">
		{{-- COLUMNA IZQUIERDA (74%): TABLA PRINCIPAL Y 4 GRÁFICOS --}}
		<div class="mc-left-column">
			{{-- PANE 1: MOVIMIENTOS (TAB OFICIAL DE LA FOTO) --}}
			<div class="mc-tab-pane" id="pane-tab-movimientos">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<h2 class="mc-table-title">Movimientos de Mercancías</h2>
						<div class="d-flex align-items-center gap-2">
							<button class="dv-btn-table-action" type="button" id="btnExportMovements">
								<i class="fas fa-upload"></i> Exportar
							</button>
							<button class="dv-btn-table-action" type="button">
								<i class="fas fa-th-list"></i>
							</button>
						</div>
					</div>

					<div class="table-responsive">
						<table class="mc-table" id="tableMerchandise">
							<thead>
								<tr>
									<th style="width: 30px;"><input type="checkbox" id="chkSelectAllMoves"></th>
									<th>Fecha / Hora</th>
									<th>Tipo</th>
									<th>Documento</th>
									<th>Producto</th>
									<th>SKU / UPC</th>
									<th>Sucursal</th>
									<th>Zona / Ubicación</th>
									<th style="text-align: right;">Cantidad</th>
									<th style="text-align: right;">Costo (USD)</th>
									<th>Usuario</th>
									<th>Estado</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($officialMovements as $m)
								<tr>
									<td><input type="checkbox" class="chk-move"></td>
									<td class="text-muted small">{{ $m['datetime'] }}</td>
									<td>
										<span class="mc-badge-type {{ $m['type_class'] }}">{{ $m['type'] }}</span>
									</td>
									<td><strong>{{ $m['document'] }}</strong></td>
									<td><strong>{{ $m['product'] }}</strong></td>
									<td><code>{{ $m['sku'] }}</code></td>
									<td>{{ $m['branch'] }}</td>
									<td>{{ $m['location'] }}</td>
									<td style="text-align: right;" class="font-weight-bold {{ $m['quantity'] < 0 ? 'text-danger' : 'text-dark' }}">
										{{ $m['quantity'] > 0 ? '+' : '' }}{{ $m['quantity'] }}
									</td>
									<td style="text-align: right;" class="font-weight-bold">{{ $money($m['cost']) }}</td>
									<td>{{ $m['user'] }}</td>
									<td>
										<span class="mc-badge-status {{ $m['status_class'] }}">{{ $m['status'] }}</span>
									</td>
									<td style="text-align: right;">
										<button class="dv-action-icon-btn" onclick="openTraceDrawer(9004, '{{ $m['product'] }}')" title="Ver Trazabilidad 360°">
											<i class="far fa-eye"></i>
										</button>
										<button class="dv-action-icon-btn" title="Opciones">
											<i class="fas fa-ellipsis-v"></i>
										</button>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>

					<div class="p-3 bg-light border-top d-flex justify-content-between align-items-center small text-muted">
						<div>Mostrando 1 - 5 de 1,248 registros</div>
						<div class="d-flex align-items-center gap-2">
							<button class="btn btn-sm btn-outline-secondary py-0 px-2">&lt;</button>
							<span class="badge badge-success px-2 py-1">1</span>
							<button class="btn btn-sm btn-link py-0 px-2 text-dark">2</button>
							<button class="btn btn-sm btn-link py-0 px-2 text-dark">3</button>
							<button class="btn btn-sm btn-link py-0 px-2 text-dark">4</button>
							<button class="btn btn-sm btn-link py-0 px-2 text-dark">5</button>
							<span>...</span>
							<button class="btn btn-sm btn-link py-0 px-2 text-dark">200</button>
							<button class="btn btn-sm btn-outline-secondary py-0 px-2">&gt;</button>
						</div>
						<div>Registros por página: <strong>5</strong></div>
					</div>
				</div>
			</div>

			{{-- PANE 2: STOCK & MOVIMIENTOS --}}
			<div class="mc-tab-pane d-none" id="pane-tab-stock-mov">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<div>
							<h2 class="mc-table-title"><i class="fas fa-boxes text-success mr-2"></i> Stock Actual & Movimientos por Producto</h2>
							<div class="text-muted small">Catálogo valorizado, existencias mínimas y rotación por sucursal</div>
						</div>
						<div class="d-flex align-items-center gap-2">
							<button class="dv-btn-primary btn-sm" type="button" onclick="openModal('modalRecordMovement')">
								<i class="fas fa-plus"></i> Registrar Entrada
							</button>
							<button class="dv-btn-outline btn-sm text-danger" type="button" onclick="openModal('modalRecordMovement')">
								<i class="fas fa-minus"></i> Registrar Salida
							</button>
							<button class="dv-btn-table-action" type="button" id="btnExportStock">
								<i class="fas fa-file-csv"></i> Exportar
							</button>
						</div>
					</div>

					<div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
						<div class="d-flex align-items-center gap-3">
							<span class="badge badge-light border px-2 py-1"><strong>312,540</strong> u. Totales</span>
							<span class="badge badge-light border px-2 py-1"><strong>$1,842,500.00</strong> Valoración</span>
							<span class="badge badge-light border px-2 py-1"><strong>1,248</strong> SKUs</span>
							<span class="badge badge-warning px-2 py-1"><strong>14</strong> Alerta Stock Mínimo</span>
						</div>
						<div class="input-group" style="width: 280px;">
							<input type="text" class="form-control form-control-sm" placeholder="Filtrar por SKU o Producto..." id="txtFilterStock">
						</div>
					</div>

					<div class="table-responsive">
						<table class="mc-table" id="tableStockOverview">
							<thead>
								<tr>
									<th>Producto</th>
									<th>SKU</th>
									<th>Sucursal</th>
									<th>Ubicación</th>
									<th style="text-align: right;">Stock Actual</th>
									<th style="text-align: right;">Mínimo</th>
									<th style="text-align: right;">Precio Venta</th>
									<th>Estado</th>
									<th>Último Movimiento</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($stockOverviewList as $stk)
								<tr class="stock-row">
									<td><strong>{{ $stk['name'] }}</strong></td>
									<td><code>{{ $stk['sku'] }}</code></td>
									<td>{{ $stk['branch'] }}</td>
									<td><span class="text-muted small">{{ $stk['location'] }}</span></td>
									<td style="text-align: right;" class="font-weight-bold {{ $stk['current_stock'] <= $stk['min_stock'] ? 'text-danger' : 'text-dark' }}">
										{{ $fmt($stk['current_stock']) }}
									</td>
									<td style="text-align: right;" class="text-muted">{{ $fmt($stk['min_stock']) }}</td>
									<td style="text-align: right;" class="font-weight-bold">${{ number_format($stk['sale_price'], 2) }}</td>
									<td>
										@if($stk['status'] === 'out_of_stock')
											<span class="badge badge-danger">Agotado</span>
										@elseif($stk['status'] === 'low_stock')
											<span class="badge badge-warning">Bajo Mínimo</span>
										@elseif($stk['status'] === 'excess_stock')
											<span class="badge badge-info">Sobrestock</span>
										@else
											<span class="badge badge-success">Óptimo</span>
										@endif
									</td>
									<td class="text-muted small">{{ $stk['last_in'] }}</td>
									<td style="text-align: right;">
										<button class="btn btn-sm btn-light border py-0 px-2" onclick="openTraceDrawer({{ $stk['id'] }}, '{{ $stk['name'] }}')" title="Trazabilidad 360°">
											<i class="fas fa-route text-success"></i>
										</button>
										<button class="btn btn-sm btn-light border py-0 px-2 ml-1" onclick="openModal('modalRecordMovement')" title="Entrada / Salida">
											<i class="fas fa-exchange-alt"></i>
										</button>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{{-- PANE 3: TRASLADOS --}}
			<div class="mc-tab-pane d-none" id="pane-tab-traslados">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<div>
							<h2 class="mc-table-title"><i class="fas fa-truck text-primary mr-2"></i> Traslados y Transferencias Inter-Sucursales</h2>
							<div class="text-muted small">Despachos entre almacenes, custodia en tránsito y conciliación de recepciones</div>
						</div>
						<div class="d-flex align-items-center gap-2">
							<button class="dv-btn-primary btn-sm" type="button" onclick="openModal('modalNewTransfer')">
								<i class="fas fa-plus"></i> Nueva Solicitud de Traslado
							</button>
						</div>
					</div>

					<div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
						<div class="d-flex align-items-center gap-2 small">
							<span class="badge badge-info px-2 py-1">1. Solicitados (3)</span> &rarr;
							<span class="badge badge-primary px-2 py-1">2. Aprobados (2)</span> &rarr;
							<span class="badge badge-warning px-2 py-1">3. En Tránsito (8)</span> &rarr;
							<span class="badge badge-danger px-2 py-1">4. Con Diferencia (2)</span> &rarr;
							<span class="badge badge-success px-2 py-1">5. Conciliados (18)</span>
						</div>
					</div>

					<div class="table-responsive">
						<table class="mc-table" id="tableTransfers">
							<thead>
								<tr>
									<th>Código Guía</th>
									<th>Fecha / Hora</th>
									<th>Origen &rarr; Destino</th>
									<th>Producto</th>
									<th>SKU</th>
									<th style="text-align: right;">Enviadas</th>
									<th style="text-align: right;">Recibidas</th>
									<th style="text-align: right;">Diferencia</th>
									<th>Transportista / Guía</th>
									<th>Estado</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($transfersList as $t)
								<tr>
									<td><strong>{{ $t['transfer_code'] }}</strong></td>
									<td class="text-muted small">{{ $t['created_at'] }}</td>
									<td>
										<span class="badge badge-light border">{{ $t['from_branch_name'] }}</span>
										<i class="fas fa-arrow-right text-muted mx-1" style="font-size: 0.7rem;"></i>
										<span class="badge badge-light border">{{ $t['to_branch_name'] }}</span>
									</td>
									<td><strong>{{ $t['product_name'] ?? 'Producto' }}</strong></td>
									<td><code>{{ $t['sku'] ?? 'SKU' }}</code></td>
									<td style="text-align: right;" class="font-weight-bold">{{ $t['sent_units'] }}</td>
									<td style="text-align: right;">{{ $t['received_units'] ?: '—' }}</td>
									<td style="text-align: right;" class="font-weight-bold {{ ($t['difference_units'] ?? 0) != 0 ? 'text-danger' : 'text-muted' }}">
										{{ ($t['difference_units'] ?? 0) != 0 ? ($t['difference_units'] . ' u.') : '0 u.' }}
									</td>
									<td class="small text-muted">{{ $t['carrier'] ?? 'Logística Interna' }}</td>
									<td>
										<span class="mc-badge-status {{ $t['status_class'] ?? 'info' }}">{{ $t['status_label'] ?? $t['status'] }}</span>
									</td>
									<td style="text-align: right;">
										@if(($t['status'] ?? '') === 'in_transit' || ($t['status'] ?? '') === 'warning')
										<button class="btn btn-sm btn-success py-0 px-2" onclick="openReceiveTransferModal({{ $t['id'] }}, '{{ $t['transfer_code'] }}', '{{ $t['product_name'] ?? 'Producto' }}', {{ $t['sent_units'] }})">
											<i class="fas fa-check"></i> Recibir
										</button>
										@elseif(($t['difference_units'] ?? 0) != 0)
										<button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="toastr.warning('Diferencia de {{ $t['difference_units'] }} unidades enviada al expediente DIF-2026-081 en Conciliación');">
											<i class="fas fa-exclamation-triangle"></i> Discrepancia
										</button>
										@else
										<button class="btn btn-sm btn-light border py-0 px-2" onclick="toastr.success('Guía {{ $t['transfer_code'] }} conciliada conforme al 100%.');">
											<i class="far fa-file-alt"></i> Guía
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

			{{-- PANE 4: DIFERENCIAS --}}
			<div class="mc-tab-pane d-none" id="pane-tab-diferencias">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<div>
							<h2 class="mc-table-title"><i class="fas fa-exclamation-triangle text-danger mr-2"></i> Discrepancias e Inconsistencias Operativas</h2>
							<div class="text-muted small">Faltantes, sobrantes, recepciones incompletas y diferencias en traslados</div>
						</div>
						<div class="d-flex align-items-center gap-2">
							<button class="dv-btn-primary btn-sm" type="button" onclick="openModal('modalRecordMovement')">
								<i class="fas fa-plus"></i> Reportar Inconsistencia
							</button>
						</div>
					</div>

					<div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
						<div class="d-flex align-items-center gap-2">
							<button class="btn btn-sm btn-light border active">Todas (18)</button>
							<button class="btn btn-sm btn-light border">Faltantes (11)</button>
							<button class="btn btn-sm btn-light border">Sobrantes (2)</button>
							<button class="btn btn-sm btn-light border">Recepciones (3)</button>
							<button class="btn btn-sm btn-light border">Traslados (2)</button>
						</div>
					</div>

					<div class="table-responsive">
						<table class="mc-table" id="tableDifferences">
							<thead>
								<tr>
									<th>Expediente</th>
									<th>Tipo Discrepancia</th>
									<th>Producto</th>
									<th>SKU</th>
									<th>Documento</th>
									<th>Sucursal</th>
									<th style="text-align: right;">Esperado</th>
									<th style="text-align: right;">Registrado</th>
									<th style="text-align: right;">Diferencia</th>
									<th style="text-align: right;">Impacto ($)</th>
									<th>Prioridad</th>
									<th>Estado</th>
									<th>Responsable</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($differencesList as $d)
								<tr>
									<td><strong>{{ $d['case_ref'] }}</strong></td>
									<td><span class="badge badge-light border">{{ $d['type'] }}</span></td>
									<td><strong>{{ $d['product'] }}</strong></td>
									<td><code>{{ $d['sku'] }}</code></td>
									<td>{{ $d['document'] }}</td>
									<td>{{ $d['branch'] }}</td>
									<td style="text-align: right;">{{ $d['expected'] }}</td>
									<td style="text-align: right;">{{ $d['registered'] }}</td>
									<td style="text-align: right;" class="font-weight-bold {{ $d['diff'] < 0 ? 'text-danger' : 'text-success' }}">
										{{ $d['diff'] > 0 ? '+' : '' }}{{ $d['diff'] }} u.
									</td>
									<td style="text-align: right;" class="font-weight-bold {{ $d['impact'] < 0 ? 'text-danger' : 'text-success' }}">
										${{ number_format(abs($d['impact']), 2) }}
									</td>
									<td>
										<span class="badge badge-{{ $d['priority_class'] }}">{{ $d['priority'] }}</span>
									</td>
									<td>
										<span class="mc-badge-status {{ $d['status_class'] }}">{{ $d['status'] }}</span>
									</td>
									<td class="small">{{ $d['responsible'] }}</td>
									<td style="text-align: right;">
										<button class="btn btn-sm btn-light border py-0 px-2" onclick="handleDiffAction({{ $d['id'] }}, 'investigate', '{{ $d['case_ref'] }}')" title="Investigar">
											<i class="fas fa-search text-primary"></i>
										</button>
										<button class="btn btn-sm btn-light border py-0 px-2 ml-1" onclick="handleDiffAction({{ $d['id'] }}, 'recount', '{{ $d['case_ref'] }}')" title="Solicitar Reconteo">
											<i class="fas fa-redo text-warning"></i>
										</button>
										<button class="btn btn-sm btn-light border py-0 px-2 ml-1" onclick="handleDiffAction({{ $d['id'] }}, 'resolve', '{{ $d['case_ref'] }}')" title="Resolver">
											<i class="fas fa-check text-success"></i>
										</button>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			{{-- PANE 5: AJUSTES --}}
			<div class="mc-tab-pane d-none" id="pane-tab-ajustes">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<div>
							<h2 class="mc-table-title"><i class="fas fa-sliders-h text-purple mr-2"></i> Centro de Autorización de Ajustes de Inventario</h2>
							<div class="text-muted small">Segregación de funciones: Ningún ajuste modifica existencias hasta ser formalmente autorizado por Gerencia</div>
						</div>
						<div class="d-flex align-items-center gap-2">
							<button class="dv-btn-primary btn-sm" type="button" onclick="openModal('modalStockAdjustment')">
								<i class="fas fa-plus"></i> Solicitar Ajuste
							</button>
						</div>
					</div>

					<div class="p-3 bg-light border-bottom">
						<div class="d-flex align-items-center gap-2 small text-dark">
							<i class="fas fa-shield-alt text-success fa-lg mr-2"></i>
							<div>
								<strong>Control Interno & Auditoría:</strong> Las solicitudes de ajuste registradas permanecen en estado <code>pending</code>. Al ser aprobadas por Gerencia, el sistema ejecuta la corrección contable, actualiza el Kardex e inserta el evento en la bitácora inalterable.
							</div>
						</div>
					</div>

					<div class="table-responsive">
						<table class="mc-table" id="tableAdjustments">
							<thead>
								<tr>
									<th>Código</th>
									<th>Fecha / Hora</th>
									<th>Tipo</th>
									<th>Producto</th>
									<th>SKU</th>
									<th>Sucursal</th>
									<th style="text-align: right;">Stock Ant.</th>
									<th style="text-align: right;">Variación</th>
									<th style="text-align: right;">Stock Resultante</th>
									<th>Motivo Justificado</th>
									<th>Solicitante</th>
									<th>Estado</th>
									<th style="text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($adjustmentsList as $a)
								<tr>
									<td><strong>{{ $a['code'] }}</strong></td>
									<td class="text-muted small">{{ $a['created_at'] }}</td>
									<td><span class="badge badge-light border">{{ $a['adjustment_type'] }}</span></td>
									<td><strong>{{ $a['product_name'] ?? 'Producto' }}</strong></td>
									<td><code>{{ $a['sku'] ?? 'SKU' }}</code></td>
									<td>{{ $a['branch_name'] ?? 'Sucursal' }}</td>
									<td style="text-align: right;">{{ $a['stock_before'] }}</td>
									<td style="text-align: right;" class="font-weight-bold {{ $a['delta'] < 0 ? 'text-danger' : 'text-success' }}">
										{{ $a['delta'] > 0 ? '+' : '' }}{{ $a['delta'] }}
									</td>
									<td style="text-align: right;" class="font-weight-bold">{{ $a['stock_after'] }}</td>
									<td class="small">{{ $a['reason'] }}</td>
									<td class="small text-muted">{{ $a['requester_name'] ?? 'Usuario' }}</td>
									<td>
										@if($a['status'] === 'approved')
											<span class="badge badge-success"><i class="fas fa-check-circle"></i> Aprobado</span>
										@elseif($a['status'] === 'rejected')
											<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Rechazado</span>
										@else
											<span class="badge badge-warning"><i class="fas fa-clock"></i> Pendiente</span>
										@endif
									</td>
									<td style="text-align: right;">
										@if($a['status'] === 'pending')
										<button class="btn btn-sm btn-success py-0 px-2" onclick="decideAdjustment({{ $a['id'] }}, 'approve')">
											<i class="fas fa-check"></i> Aprobar
										</button>
										<button class="btn btn-sm btn-outline-danger py-0 px-2 ml-1" onclick="decideAdjustment({{ $a['id'] }}, 'reject')">
											<i class="fas fa-times"></i> Rechazar
										</button>
										@else
										<button class="btn btn-sm btn-light border py-0 px-2" onclick="toastr.info('Ajuste {{ $a['code'] }} auditado formalmente en libro central.');">
											<i class="fas fa-file-invoice"></i> Acta
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

			{{-- PANE 6: TRAZABILIDAD 360° --}}
			<div class="mc-tab-pane d-none" id="pane-tab-trazabilidad">
				<div class="mc-table-card">
					<div class="mc-table-header">
						<div>
							<h2 class="mc-table-title"><i class="fas fa-route text-success mr-2"></i> Trazabilidad Operativa 360° de Mercancías</h2>
							<div class="text-muted small">Rastreo de vida útil: Proveedor &rarr; Recepción &rarr; Almacén &rarr; Traslado &rarr; Piso de Venta &rarr; Despacho POS</div>
						</div>
					</div>

					<div class="p-3 bg-light border-bottom">
						<form id="formSearchTrace" class="d-flex align-items-center gap-2">
							<div class="input-group" style="max-width: 480px;">
								<input type="text" class="form-control" id="txtTraceQueryInput" placeholder="Buscar por SKU (LCH-001), UPC, Lote, o Nombre..." value="LCH-001">
								<div class="input-group-append">
									<button class="btn btn-success" type="submit"><i class="fas fa-search"></i> Consultar Recorrido</button>
								</div>
							</div>
							<span class="text-muted small ml-2">Ejemplos: <code>LCH-001</code>, <code>ARR-005</code>, <code>POL-002</code>, <code>CEL-GAL-S24</code></span>
						</form>
					</div>

					<div class="p-3">
						<div class="card p-3 mb-3 border bg-white rounded">
							<div class="d-flex justify-content-between align-items-center flex-wrap">
								<div>
									<h4 class="m-0 font-weight-bold" id="traceViewProdName">Leche Entera 1L Pasteurizada</h4>
									<div class="text-muted small mt-1">
										SKU: <code id="traceViewSku">LCH-001</code> · Categoría: <strong>Alimentos / Lácteos</strong> · Lote Activo: <strong>LOT-2026-LCH-09</strong>
									</div>
								</div>
								<div class="text-right">
									<span class="badge badge-success px-2 py-1 font-size-base">Stock en Tienda: 250 u.</span>
									<div class="text-muted small mt-1">Costo: $1.15 | Venta: $1.80</div>
								</div>
							</div>
						</div>

						<h5 class="font-weight-bold mb-3 small text-uppercase text-muted">Línea de Tiempo Operativa (6 Etapas del Ciclo de Vida)</h5>
						<div id="traceViewTimeline" class="border-left pl-3 ml-2 mb-4">
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-success position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>1. Proveedor & Orden de Compra</strong> · <span class="badge badge-success py-0">Completado (24 oct 2026)</span>
								<div class="small text-muted mt-1">Doc: <code>OC-2026-9041</code> · Proveedor: Colanta Lácteos S.A. · 300 unidades emitidas por compras_central</div>
							</div>
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-success position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>2. Recepción Física & Control Calidad</strong> · <span class="badge badge-success py-0">Completado (26 oct 2026)</span>
								<div class="small text-muted mt-1">Doc: <code>REC-014672</code> · Lector UPC validado · Temperatura: 3.8 °C conforme · Recibido por jmartinez</div>
							</div>
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-success position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>3. Almacenamiento & Ubicación Fría</strong> · <span class="badge badge-success py-0">Completado (28 oct 2026)</span>
								<div class="small text-muted mt-1">Ubicación: Cámara Fría #2 - Rack L-03 · Lote: LOT-2026-LCH-09 · Vencimiento: 25 nov 2026</div>
							</div>
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-warning position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>4. Traslado Inter-Sucursal</strong> · <span class="badge badge-warning py-0">En Tránsito (30 oct 2026)</span>
								<div class="small text-muted mt-1">Guía: <code>TRA-008328</code> · Bodega Central &rarr; Aventura · Faltante reportado: -2 u. en investigación</div>
							</div>
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-info position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>5. Piso de Venta / Góndola</strong> · <span class="badge badge-info py-0">Exhibición Activa</span>
								<div class="small text-muted mt-1">Sucursal Hallandale Beach · Góndola Lácteos 01 · 250 unidades exhibidas</div>
							</div>
							<div class="mb-3 position-relative">
								<i class="fas fa-circle text-success position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>
								<strong>6. Despacho / Ventas POS</strong> · <span class="badge badge-success py-0">Activo (Hoy)</span>
								<div class="small text-muted mt-1">42 tickets registrados hoy mediante puntos de venta · Último ticket: POS-2026-11892</div>
							</div>
						</div>

						<h5 class="font-weight-bold mb-2 small text-uppercase text-muted">Historial Auditado de Movimientos (Kardex & Domain Bus)</h5>
						<div id="traceViewHistoryTable">
							<table class="table table-sm table-striped m-0 small">
								<thead>
									<tr><th>Fecha</th><th>Operación</th><th>Documento</th><th>Cantidad</th><th>Usuario</th><th>Hash Auditoría</th></tr>
								</thead>
								<tbody>
									<tr><td>02 nov 2026 · 10:24</td><td><span class="badge badge-success">Recepción</span></td><td>REC-014672</td><td>+250 u.</td><td>jmartinez</td><td><code>sha256:7f8a9...c4b</code></td></tr>
									<tr><td>30 oct 2026 · 14:20</td><td><span class="badge badge-info">Traslado Salida</span></td><td>TRA-008328</td><td>-50 u.</td><td>rgiovine</td><td><code>sha256:3d2e1...8f0</code></td></tr>
									<tr><td>28 oct 2026 · 11:00</td><td><span class="badge badge-secondary">Almacenamiento</span></td><td>ALM-0091</td><td>300 u.</td><td>almacen_c</td><td><code>sha256:4a5b6...9e2</code></td></tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			{{-- PANE 7: REPORTES Y KPI --}}
			{{-- PANE 8: RECEPCIÓN ASN (AVISOS DE DESPACHO ANTICIPADO) --}}
<div class="mc-tab-pane d-none" id="pane-tab-recepcion-asn">
	<div class="mc-table-card">
		<div class="mc-table-header d-flex justify-content-between align-items-center">
			<div>
				<h2 class="mc-table-title mb-0">Recepción Avanzada de Mercancías · ASN (Advanced Shipping Notice)</h2>
				<p class="small text-muted mb-0">Control de manifiestos electrónicos, validación de remisiones proveedor y detección de discrepancias en muelle.</p>
			</div>
			<div class="d-flex align-items-center gap-2">
				<a href="{{ $U('/scan') }}" class="dv-btn-primary">
					<i class="fas fa-barcode"></i> Escanear en Muelle
				</a>
				<a href="{{ $U('/action-center') }}" class="dv-btn-outline">
					<i class="fas fa-shield-alt text-warning"></i> Action Center
				</a>
			</div>
		</div>

		{{-- KPIs de Recepción ASN --}}
		<div class="p-3 bg-light border-bottom">
			<div class="row">
				<div class="col-md-3">
					<div class="card border-0 shadow-sm p-3 bg-white">
						<div class="text-muted small font-weight-bold">AVISOS ASN REGISTRADOS</div>
						<div class="h4 font-weight-bold text-dark mb-0">{{ count($d['asn_receipts'] ?? []) }} <span class="small font-weight-normal text-muted">manifiestos</span></div>
						<div class="small text-muted mt-1"><i class="fas fa-check-circle text-success"></i> 100% integrados EDI/B2B</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border-0 shadow-sm p-3 bg-white">
						<div class="text-muted small font-weight-bold">EN PROCESO DE MUELLE</div>
						<div class="h4 font-weight-bold text-warning mb-0">1 <span class="small font-weight-normal text-muted">en andén</span></div>
						<div class="small text-muted mt-1">Descarga y conteo activo</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border-0 shadow-sm p-3 bg-white">
						<div class="text-muted small font-weight-bold">CONCILIADOS SIN NOVEDAD</div>
						<div class="h4 font-weight-bold text-success mb-0">96.8%</div>
						<div class="small text-muted mt-1">Exactitud física vs manifiesto</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border-0 shadow-sm p-3 bg-white">
						<div class="text-muted small font-weight-bold">DISCREPANCIAS DETECTADAS</div>
						<div class="h4 font-weight-bold text-danger mb-0">1 <span class="small font-weight-normal text-muted">novedad</span></div>
						<div class="small text-muted mt-1">-5 unids. (Yogur Griego 500g)</div>
					</div>
				</div>
			</div>
		</div>

		{{-- Listado de ASNs y Detalle de Líneas --}}
		<div class="p-3">
			@foreach(($d['asn_receipts'] ?? []) as $asn)
			<div class="card border mb-4 shadow-sm">
				<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div class="d-flex align-items-center gap-3">
						<div class="rounded-circle p-2 bg-light text-primary border" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
							<i class="fas fa-truck-loading font-weight-bold"></i>
						</div>
						<div>
							<div class="font-weight-bold text-dark h6 mb-0">
								{{ $asn['asn_number'] }} · {{ $asn['supplier_name'] }}
								@if($asn['status'] === 'in_dock')
									<span class="badge badge-warning text-dark ml-2"><i class="fas fa-spinner fa-spin"></i> En Muelle de Descarga</span>
								@elseif($asn['status'] === 'matched')
									<span class="badge badge-success ml-2"><i class="fas fa-check"></i> Conciliado Completo</span>
								@else
									<span class="badge badge-secondary ml-2"><i class="fas fa-route"></i> En Ruta / Esperado</span>
								@endif
							</div>
							<div class="small text-muted">
								<span><i class="fas fa-warehouse mr-1"></i> {{ $asn['branch_name'] }}</span> · 
								<span><i class="fas fa-file-alt mr-1"></i> Doc Remisión: <strong>{{ $asn['doc_reference'] }}</strong></span> · 
								<span><i class="fas fa-calendar mr-1"></i> Fecha Arribo: {{ $asn['expected_delivery_date'] }}</span>
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-sm btn-outline-secondary" onclick="alert('Descargando copia del manifiesto ASN {{ $asn['asn_number'] }} en formato PDF...');">
							<i class="fas fa-file-pdf mr-1 text-danger"></i> Manifiesto
						</button>
						@if($asn['status'] === 'in_dock')
						<a href="{{ $U('/scan') }}" class="btn btn-sm btn-success font-weight-bold">
							<i class="fas fa-barcode mr-1"></i> Escaneo Ciego en Muelle
						</a>
						@endif
					</div>
				</div>

				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead class="thead-light">
								<tr>
									<th>UPC / Código</th>
									<th>Producto / Descripción</th>
									<th class="text-right">Esperado (ASN)</th>
									<th class="text-right">Recibido (Físico)</th>
									<th class="text-right">Diferencia</th>
									<th class="text-right">Costo Unit.</th>
									<th>Observación / Estado</th>
									<th class="text-center">Acción</th>
								</tr>
							</thead>
							<tbody>
								@foreach($asn['items'] as $item)
								<tr class="@if($item['discrepancy_qty'] < 0) table-danger @elseif($item['discrepancy_qty'] > 0) table-warning @endif">
									<td><code>{{ $item['product_barcode'] }}</code></td>
									<td class="font-weight-600 text-dark">{{ $item['product_name'] }}</td>
									<td class="text-right font-weight-bold">{{ $item['expected_qty'] }}</td>
									<td class="text-right font-weight-bold">{{ $item['received_qty'] }}</td>
									<td class="text-right">
										@if($item['discrepancy_qty'] == 0)
											<span class="text-success font-weight-bold"><i class="fas fa-check"></i> 0</span>
										@elseif($item['discrepancy_qty'] < 0)
											<span class="text-danger font-weight-bold">{{ $item['discrepancy_qty'] }} (Faltante)</span>
										@else
											<span class="text-warning font-weight-bold">+{{ $item['discrepancy_qty'] }} (Sobrante)</span>
										@endif
									</td>
									<td class="text-right">${{ number_format($item['unit_cost'], 2) }}</td>
									<td>
										@if($item['discrepancy_qty'] != 0)
											<span class="badge badge-danger">{{ $item['notes'] }}</span>
										@else
											<span class="badge badge-success">{{ $item['notes'] }}</span>
										@endif
									</td>
									<td class="text-center">
										@if($item['discrepancy_qty'] < 0)
										<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold" title="Abrir incidencia en Action Center">
											<i class="fas fa-exclamation-triangle mr-1"></i> Escalar
										</a>
										@else
										<span class="text-muted small"><i class="fas fa-check text-success"></i> OK</span>
										@endif
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
				<div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
					<div class="small text-muted">
						Total líneas: <strong>{{ count($asn['items']) }}</strong> · Unidades esperadas: <strong>{{ $asn['total_expected_qty'] }}</strong> · Unidades verificadas: <strong>{{ $asn['total_received_qty'] }}</strong>
					</div>
					<div>
						@if($asn['status'] === 'in_dock')
						<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" onclick="alert('ASN {{ $asn['asn_number'] }} conciliado con novedad. Acta de reclamación enviada a proveedor.');">
							<i class="fas fa-stamp mr-1"></i> Cerrar Recepción y Generar Acta
						</button>
						@else
						<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-lock mr-1"></i> Recepción Contabilizada</span>
						@endif
					</div>
				</div>
			</div>
			@endforeach
		</div>
	</div>
</div>
<div class="mc-tab-pane d-none" id="pane-tab-reportes-kpi">
				<div class="mc-table-card p-4">
					<h2 class="mc-table-title mb-3"><i class="fas fa-chart-pie text-success mr-2"></i> Reportes Ejecutivos & Indicadores de Circulación</h2>
					<div class="row">
						<div class="col-md-4 mb-3">
							<div class="card p-3 border h-100">
								<h5 class="font-weight-bold">Balance de Existencias</h5>
								<p class="small text-muted">Reporte oficial valorizado con corte a la fecha actual para contabilidad y auditoría externa.</p>
								<button class="btn btn-sm btn-outline-success mt-auto" onclick="toastr.success('Generando Balance Oficial de Mercancías...');"><i class="fas fa-download"></i> Descargar Excel</button>
							</div>
						</div>
						<div class="col-md-4 mb-3">
							<div class="card p-3 border h-100">
								<h5 class="font-weight-bold">Índice de Rotación</h5>
								<p class="small text-muted">Días promedio de inventario por departamento y rotación de productos clave (23.4 días).</p>
								<button class="btn btn-sm btn-outline-primary mt-auto" onclick="toastr.success('Exportando Informe de Rotación...');"><i class="fas fa-download"></i> Descargar PDF</button>
							</div>
						</div>
						<div class="col-md-4 mb-3">
							<div class="card p-3 border h-100">
								<h5 class="font-weight-bold">Auditoría de Inconsistencias</h5>
								<p class="small text-muted">Detalle de faltantes, mermas de traslado y conciliaciones pendientes por sucursal.</p>
								<button class="btn btn-sm btn-outline-danger mt-auto" onclick="toastr.success('Generando Auditoría de Inconsistencias...');"><i class="fas fa-download"></i> Descargar Reporte</button>
							</div>
						</div>
					</div>
				</div>
			</div>

			{{-- 4 GRÁFICOS COMPACTOS DE ANALÍTICA (RÉPLICA DE LA FOTO) --}}
			<div class="mc-analytics-grid-4">
				{{-- Gráfico 1: Entradas vs Salidas --}}
				<div class="mc-chart-card">
					<div class="mc-chart-title">Entradas vs Salidas</div>
					<div style="height: 120px;">
						<canvas id="chartEntriesVsExits"></canvas>
					</div>
				</div>

				{{-- Gráfico 2: Movimientos por tipo --}}
				<div class="mc-chart-card">
					<div class="mc-chart-title">Movimientos por tipo</div>
					<div class="d-flex align-items-center justify-content-between" style="height: 120px;">
						<div style="width: 80px; height: 80px; position: relative;">
							<canvas id="chartMovesByType"></canvas>
							<div style="position: absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; font-size:0.7rem; font-weight:800;">
								1,248<br><span style="font-size:0.55rem; color:#64748b; font-weight:600;">Total</span>
							</div>
						</div>
						<div class="small" style="font-size: 0.65rem; line-height: 1.3;">
							<div><span style="color:#06b6d4">●</span> Entrada: 45.2%</div>
							<div><span style="color:#ef4444">●</span> Salida: 28.7%</div>
							<div><span style="color:#3b82f6">●</span> Traslado: 12.4%</div>
							<div><span style="color:#8b5cf6">●</span> Ajuste: 7.3%</div>
							<div><span style="color:#10b981">●</span> Recepción: 4.8%</div>
						</div>
					</div>
				</div>

				{{-- Gráfico 3: Top productos con mayor movimiento --}}
				<div class="mc-chart-card">
					<div class="mc-chart-title">Top productos mayor movimiento</div>
					<div class="d-flex flex-column gap-1" style="font-size: 0.68rem;">
						@foreach($charts['top_products'] ?? [] as $tp)
						<div>
							<div class="d-flex justify-content-between mb-1">
								<span class="text-truncate" style="max-width: 140px;">{{ $tp['name'] }}</span>
								<strong>{{ $tp['moves'] }}</strong>
							</div>
							<div class="progress" style="height: 4px;">
								<div class="progress-bar bg-success" style="width: {{ round(100 * $tp['moves'] / 300) }}%;"></div>
							</div>
						</div>
						@endforeach
					</div>
				</div>

				{{-- Gráfico 4: Diferencias por departamento --}}
				<div class="mc-chart-card">
					<div class="mc-chart-title">Diferencias por departamento</div>
					<div style="height: 120px;">
						<canvas id="chartDiffByDept"></canvas>
					</div>
				</div>
			</div>
		</div>

		{{-- COLUMNA DERECHA (26%): DONUT ESTADO, ACCIONES 2X2 Y TRAZABILIDAD 360° --}}
		<div class="mc-right-column">
			{{-- TARJETA 1: ESTADO DE MERCANCÍAS --}}
			<div class="dv-side-card">
				<h3 class="dv-side-title">Estado de Mercancías</h3>
				<div class="dv-donut-box">
					<div class="dv-donut-visual">
						<svg width="120" height="120" viewBox="0 0 36 36">
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#f1f5f9" stroke-width="4"></circle>
							{{-- Disponible 62.3% --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#22c55e" stroke-width="4"
								stroke-dasharray="62.3 37.7" stroke-dashoffset="25"></circle>
							{{-- En tránsito 12.8% --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#f59e0b" stroke-width="4"
								stroke-dasharray="12.8 87.2" stroke-dashoffset="{{ 25 - 62.3 }}"></circle>
							{{-- En bodega 11.4% --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#06b6d4" stroke-width="4"
								stroke-dasharray="11.4 88.6" stroke-dashoffset="{{ 25 - 62.3 - 12.8 }}"></circle>
							{{-- En reserva 6.1% --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#8b5cf6" stroke-width="4"
								stroke-dasharray="6.1 93.9" stroke-dashoffset="{{ 25 - 62.3 - 12.8 - 11.4 }}"></circle>
							{{-- En cuarentena 3.7% --}}
							<circle cx="18" cy="18" r="15.915" fill="none" stroke="#ef4444" stroke-width="4"
								stroke-dasharray="3.7 96.3" stroke-dashoffset="{{ 25 - 62.3 - 12.8 - 11.4 - 6.1 }}"></circle>
						</svg>
						<div class="dv-donut-center-label">
							<span class="dv-donut-number">{{ $merchDonut['total_label'] }}</span>
							<span class="dv-donut-sub">Productos</span>
						</div>
					</div>

					<div class="dv-donut-legend">
						@foreach($merchDonut['items'] as $it)
						<div class="dv-legend-row">
							<span class="dv-legend-label">
								<span class="dv-legend-dot" style="background: {{ $it['color'] }}"></span> {{ $it['label'] }}
							</span>
							<span class="dv-legend-value">{{ $it['pct'] }}% ({{ $fmt($it['count']) }})</span>
						</div>
						@endforeach
					</div>
				</div>
			</div>

			{{-- TARJETA 2: ACCIONES RÁPIDAS (2X2 GRID) --}}
			<div class="dv-side-card">
				<h3 class="dv-side-title">Acciones rápidas</h3>
				<div class="mc-quick-grid-2x2">
					<button class="mc-quick-card-btn" type="button" onclick="openModal('modalRecordMovement')">
						<div class="mc-quick-card-icon"><i class="fas fa-clipboard-check"></i></div>
						<span class="mc-quick-card-label">Registrar Recepción</span>
					</button>

					<button class="mc-quick-card-btn" type="button" onclick="openModal('modalNewTransfer')">
						<div class="mc-quick-card-icon"><i class="fas fa-truck"></i></div>
						<span class="mc-quick-card-label">Nuevo Traslado</span>
					</button>

					<button class="mc-quick-card-btn" type="button" onclick="openModal('modalStockAdjustment')">
						<div class="mc-quick-card-icon"><i class="fas fa-sliders-h"></i></div>
						<span class="mc-quick-card-label">Ajuste de Inventario</span>
					</button>

					<button class="mc-quick-card-btn" type="button" onclick="openModal('modalScanUpc')">
						<div class="mc-quick-card-icon"><i class="fas fa-barcode"></i></div>
						<span class="mc-quick-card-label">Escanear Producto</span>
					</button>
				</div>
			</div>

			{{-- TARJETA 3: TRAZABILIDAD 360° --}}
			<div class="dv-trace-card">
				<div class="dv-trace-top">
					<div class="dv-trace-icon"><i class="fas fa-boxes"></i></div>
					<h4 class="dv-trace-title">Trazabilidad 360°</h4>
				</div>
				<p class="dv-trace-desc">Consulta el historial completo de movimientos, entradas, salidas, traslados y devoluciones por producto, lote o SKU.</p>
				<div class="dv-trace-footer">
					<button class="dv-trace-btn-circle" type="button" onclick="openTraceDrawer(9004, 'Leche Entera 1L')">
						<i class="fas fa-arrow-right"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ==========================================================================
     DRAWER LATERAL: TRAZABILIDAD OPERATIVA 360°
   ========================================================================== --}}
<div class="lp-slideover-overlay" id="drawerTraceJourney">
	<div class="lp-slideover-panel" style="max-width: 520px; width: 100%;">
		<div class="lp-drawer-header">
			<div>
				<h3 id="drTraceProductName">Trazabilidad Operativa 360°</h3>
				<span class="small text-muted" id="drTraceProductSku">SKU: LCH-001</span>
			</div>
			<button class="lp-drawer-close" onclick="closeModal('drawerTraceJourney')">&times;</button>
		</div>

		<div class="p-3 bg-light border-bottom">
			<div class="small font-weight-bold text-success mb-1"><i class="fas fa-route"></i> Flujo de Circulación Física</div>
			<div class="d-flex justify-content-between small text-muted">
				<span>Proveedor</span> &rarr; <span>Recepción</span> &rarr; <span>Almacén</span> &rarr; <span>Traslado</span> &rarr; <span>Venta</span>
			</div>
		</div>

		<div class="p-3" style="overflow-y: auto; flex-grow: 1;">
			<h5 class="font-weight-bold mb-3 small text-uppercase text-muted">Etapas del Recorrido</h5>
			<div id="drTraceStagesContainer">
				{{-- Llenado dinámico --}}
			</div>

			<h5 class="font-weight-bold mt-4 mb-2 small text-uppercase text-muted">Historial de Operaciones en Libro Central</h5>
			<div id="drTraceHistoryContainer" class="small">
				{{-- Llenado dinámico --}}
			</div>
		</div>

		<div class="p-3 border-top bg-light d-flex justify-content-end">
			<button class="btn btn-sm btn-secondary" onclick="closeModal('drawerTraceJourney')">Cerrar</button>
		</div>
	</div>
</div>

{{-- ==========================================================================
     MODAL: REGISTRAR MOVIMIENTO (ENTRADA, SALIDA, TRASLADO, AJUSTE)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalRecordMovement">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-boxes text-success mr-2"></i> Registrar Movimiento de Mercancía</h4>
			<button class="dv-modal-close" onclick="closeModal('modalRecordMovement')">&times;</button>
		</div>
		<form id="formRecordMerchMovement">
			<div class="dv-modal-body">
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Tipo de Movimiento *</label>
					<select class="form-control form-control-sm" name="type" required>
						<option value="reception">Recepción de Mercancía (Entrada)</option>
						<option value="dispatch">Despacho / Salida de Almacén</option>
						<option value="transfer">Traslado entre Sucursales</option>
						<option value="adjustment">Ajuste de Inventario</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Producto *</label>
					<select class="form-control form-control-sm" name="product_id" required>
						@foreach($products as $p)
						<option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['sku'] ?? 'SKU' }})</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Sucursal Origen *</label>
						<select class="form-control form-control-sm" name="branch_id" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Cantidad *</label>
						<input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" value="10" required>
					</div>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Costo Unitario ($)</label>
						<input type="number" step="0.01" min="0" class="form-control form-control-sm" name="price" value="5.00">
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Documento Asociado</label>
						<input type="text" class="form-control form-control-sm" name="document" placeholder="Ej: REC-2026-004">
					</div>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalRecordMovement')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-success font-weight-bold">Confirmar Movimiento</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL: ESCANEAR UPC (INTELIGENTE)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalScanUpc">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-barcode text-primary mr-2"></i> Escaneo de Mercancía UPC / EAN</h4>
			<button class="dv-modal-close" onclick="closeModal('modalScanUpc')">&times;</button>
		</div>
		<div class="dv-modal-body">
			<div class="text-center p-3 bg-light rounded border mb-3">
				<i class="fas fa-qrcode fa-2x text-muted mb-2"></i>
				<p class="small text-muted mb-1">Compatible con cámara de celular, Zebra TC-52/57 y lectores Honeywell.</p>
				<span class="badge badge-success"><i class="fas fa-check"></i> Escáner Listo</span>
			</div>
			<div class="form-group mb-3">
				<label class="small font-weight-bold mb-1">Código de Barras Escaneado:</label>
				<div class="input-group">
					<input type="text" class="form-control" id="txtScanUpcInput" placeholder="Escanee o ingrese código UPC..." autofocus>
					<div class="input-group-append">
						<button class="btn btn-primary" type="button" id="btnScanUpcLookup"><i class="fas fa-search"></i> Buscar</button>
					</div>
				</div>
			</div>
			<div id="scanUpcResultBox" class="d-none p-3 rounded border"></div>
		</div>
		<div class="p-3 border-top bg-light d-flex justify-content-end">
			<button type="button" class="btn btn-sm btn-secondary" onclick="closeModal('modalScanUpc')">Cerrar</button>
		</div>
	</div>
</div>

{{-- ==========================================================================
     MODAL: NUEVO TRASLADO INTER-SUCURSAL
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalNewTransfer">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-truck text-primary mr-2"></i> Nuevo Traslado Entre Sucursales</h4>
			<button class="dv-modal-close" onclick="closeModal('modalNewTransfer')">&times;</button>
		</div>
		<form id="formNewTransfer">
			<input type="hidden" name="type" value="transfer">
			<div class="dv-modal-body">
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Producto a Trasladar *</label>
					<select class="form-control form-control-sm" name="product_id" required>
						@foreach($products as $p)
						<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Sucursal Origen *</label>
						<select class="form-control form-control-sm" name="branch_id" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Sucursal Destino *</label>
						<select class="form-control form-control-sm" name="to_branch_id" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}" @if($loop->iteration == 2) selected @endif>{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Cantidad *</label>
						<input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" value="50" required>
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Guía de Despacho / Doc.</label>
						<input type="text" class="form-control form-control-sm" name="document" placeholder="Ej: TRA-2026-901">
					</div>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalNewTransfer')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-primary font-weight-bold">Emitir Traslado</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL: AJUSTE DE INVENTARIO
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalStockAdjustment">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-sliders-h text-warning mr-2"></i> Ajuste Formal de Inventario</h4>
			<button class="dv-modal-close" onclick="closeModal('modalStockAdjustment')">&times;</button>
		</div>
		<form id="formStockAdjustment">
			<input type="hidden" name="type" value="adjustment">
			<div class="dv-modal-body">
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Producto *</label>
					<select class="form-control form-control-sm" name="product_id" required>
						@foreach($products as $p)
						<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="row">
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Sucursal *</label>
						<select class="form-control form-control-sm" name="branch_id" required>
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-6 form-group mb-2">
						<label class="small font-weight-bold mb-1">Cantidad Ajuste (+ o -) *</label>
						<input type="number" step="0.01" class="form-control form-control-sm" name="amount" value="-2" required>
					</div>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Justificación / Acta de Auditoría *</label>
					<textarea class="form-control form-control-sm" name="document" rows="2" required placeholder="Indique la causa del ajuste autorizada..."></textarea>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalStockAdjustment')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-warning font-weight-bold">Aplicar Asiento de Ajuste</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL: CONFIRMAR RECEPCIÓN DE TRASLADO (CON CÁLCULO DE DIFERENCIAS)
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalReceiveTransfer">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-truck-loading text-success mr-2"></i> Recepción de Traslado Inter-Sucursal</h4>
			<button class="dv-modal-close" onclick="closeModal('modalReceiveTransfer')">&times;</button>
		</div>
		<form id="formReceiveTransfer">
			<input type="hidden" name="transfer_id" id="recTransferId" value="">
			<div class="dv-modal-body">
				<div class="p-2 mb-3 bg-light rounded border">
					<div class="d-flex justify-content-between mb-1">
						<span>Guía Traslado: <strong id="recTransferCode">TRA-000000</strong></span>
						<span>Producto: <strong id="recTransferProd">Producto</strong></span>
					</div>
					<div class="text-muted small">
						Cantidad Enviada por Sucursal Origen: <strong id="recSentUnits" class="text-dark">0</strong> unidades
					</div>
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Cantidad Físicamente Recibida *</label>
					<input type="number" step="0.01" min="0" class="form-control" name="received_qty" id="txtRecReceivedQty" value="0" required>
				</div>

				<div class="p-2 mb-2 rounded border" id="boxRecDiffStatus" style="background: #f8fafc;">
					<div class="d-flex justify-content-between align-items-center">
						<span class="small font-weight-bold">Diferencia Detectada:</span>
						<span class="font-weight-bold" id="lblRecDifference">0 u. (Conforme)</span>
					</div>
				</div>

				<div class="form-group mb-2" id="groupRecDiffReason" style="display: none;">
					<label class="small font-weight-bold mb-1 text-danger">Justificación de la Discrepancia *</label>
					<select class="form-control form-control-sm mb-2" name="diff_type" id="selRecDiffType">
						<option value="faltante_transito">Faltante en transporte / Custodia</option>
						<option value="dano_transito">Mercancía dañada / Rotura en ruta</option>
						<option value="error_origen">Diferencia de conteo en despacho origen</option>
						<option value="sobrante">Sobrante físico no documentado</option>
					</select>
					<textarea class="form-control form-control-sm" name="notes" id="txtRecNotes" rows="2" placeholder="Describa el estado del precinto, guía o embalaje..."></textarea>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalReceiveTransfer')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-success font-weight-bold" id="btnConfirmReceiveTransfer">Confirmar Ingreso a Almacén</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     MODAL: GESTIÓN DE DISCREPANCIAS E INCONSISTENCIAS
   ========================================================================== --}}
<div class="dv-modal-overlay" id="modalDiffAction">
	<div class="dv-modal-dialog">
		<div class="dv-modal-header">
			<h4><i class="fas fa-exclamation-triangle text-danger mr-2"></i> Gestión de Inconsistencia Operativa</h4>
			<button class="dv-modal-close" onclick="closeModal('modalDiffAction')">&times;</button>
		</div>
		<form id="formDiffAction">
			<input type="hidden" name="diff_id" id="diffActionId" value="">
			<input type="hidden" name="action_type" id="diffActionType" value="investigate">
			<div class="dv-modal-body">
				<div class="p-2 mb-3 bg-light rounded border">
					<div class="d-flex justify-content-between mb-1">
						<span>Caso / Ref: <strong id="diffActionCaseRef">DIF-0000</strong></span>
						<span class="badge badge-warning" id="diffActionStatusBadge">En investigación</span>
					</div>
					<div class="small text-muted" id="diffActionDesc">Faltante reportado en custodia de mercancías.</div>
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Acción a Ejecutar *</label>
					<select class="form-control form-control-sm" id="selDiffActionChoice">
						<option value="investigate">Abrir Expediente de Investigación Formal</option>
						<option value="recount">Solicitar Reconteo de Inmediato</option>
						<option value="propose_adjustment">Proponer Ajuste a Gerencia</option>
						<option value="resolve">Resolver y Enviar a Conciliación</option>
					</select>
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold mb-1">Notas de Auditoría / Bitácora *</label>
					<textarea class="form-control form-control-sm" id="txtDiffActionNotes" rows="3" required placeholder="Detalle las comprobaciones realizadas..."></textarea>
				</div>
			</div>
			<div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
				<button type="button" class="btn btn-sm btn-secondary mr-2" onclick="closeModal('modalDiffAction')">Cerrar</button>
				<button type="submit" class="btn btn-sm btn-primary font-weight-bold">Registrar Acción</button>
			</div>
		</form>
	</div>
</div>

{{-- ==========================================================================
     JAVASCRIPT OPERATIVO & CHARTS
   ========================================================================== --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
	// 1. Manejo Integral de las Pestañas Operativas
	function switchTab(tabId) {
		// Normalizar ID
		var targetId = tabId.indexOf('tab-') === 0 ? tabId : ('tab-' + tabId);

		document.querySelectorAll('.mc-tab-btn').forEach(function (b) {
			b.classList.remove('active');
		});
		var activeBtn = document.querySelector('.mc-tab-btn[data-tab="' + targetId + '"]');
		if (activeBtn) {
			activeBtn.classList.add('active');
		}

		// Ocultar todos los paneles
		document.querySelectorAll('.mc-tab-pane').forEach(function (pane) {
			pane.classList.add('d-none');
		});

		// Mostrar panel objetivo
		var targetPane = document.getElementById('pane-' + targetId);
		if (targetPane) {
			targetPane.classList.remove('d-none');
		}

		// Actualizar URL hash sin salto de página
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

	// Restaurar pestaña activa al cargar si viene en hash o query param
	var initialHash = (window.location.hash || '').replace('#', '');
	var urlParams = new URLSearchParams(window.location.search);
	var initialParam = urlParams.get('tab') || initialHash;
	if (initialParam) {
		switchTab(initialParam);
	}

	// 2. Select All Checkboxes
	var chkAll = document.getElementById('chkSelectAllMoves');
	if (chkAll) {
		chkAll.addEventListener('change', function () {
			var checked = this.checked;
			document.querySelectorAll('.chk-move').forEach(function (c) { c.checked = checked; });
		});
	}

	// 3. Botones Superiores
	document.getElementById('btnOpenNewMoveModal').addEventListener('click', function () {
		openModal('modalRecordMovement');
	});
	document.getElementById('btnOpenScanUpcModal').addEventListener('click', function () {
		openModal('modalScanUpc');
		setTimeout(function () { document.getElementById('txtScanUpcInput').focus(); }, 200);
	});

	// 4. Formulario Registrar Movimiento
	var bindMovementSubmit = function (formId, modalId) {
		var form = document.getElementById(formId);
		if (!form) return;
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var formData = new FormData(this);
			var data = {};
			formData.forEach(function (v, k) { data[k] = v; });

			toastr.info('Procesando movimiento en libro central...');
			Grocy.Api.Post('merchandise/movement', data, function (resp) {
				toastr.success(resp.message || 'Movimiento registrado con éxito');
				closeModal(modalId);
				setTimeout(function () { location.reload(); }, 800);
			}, function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al registrar movimiento';
				toastr.error(msg);
			});
		});
	};

	bindMovementSubmit('formRecordMerchMovement', 'modalRecordMovement');
	bindMovementSubmit('formNewTransfer', 'modalNewTransfer');
	bindMovementSubmit('formStockAdjustment', 'modalStockAdjustment');

	// 5. Escaneo Inteligente UPC
	var performUpcLookup = function () {
		var code = document.getElementById('txtScanUpcInput').value.trim();
		if (!code) return;
		var box = document.getElementById('scanUpcResultBox');
		box.classList.remove('d-none');
		box.innerHTML = '<div class="text-center p-2 text-muted"><i class="fas fa-spinner fa-spin"></i> Identificando código...</div>';

		Grocy.Api.Get('merchandise/scan/' + encodeURIComponent(code), function (resp) {
			if (resp && resp.found && resp.product) {
				var p = resp.product;
				box.innerHTML = '<div class="alert alert-success m-0">' +
					'<strong><i class="fas fa-check-circle"></i> ' + p.name + '</strong><br>' +
					'<span class="small text-muted">SKU: ' + (p.sku || code) + ' · Existencia Actual: <strong>' + p.current_stock + ' u.</strong> · Depto: ' + (p.department || 'General') + '</span><br>' +
					'<div class="mt-2 pt-2 border-top d-flex gap-2">' +
					'<button class="btn btn-sm btn-primary mr-2" onclick="closeModal(\'modalScanUpc\'); openTraceDrawer(' + p.id + ', \'' + p.name + '\')"><i class="far fa-eye"></i> Trazabilidad 360°</button>' +
					'<button class="btn btn-sm btn-success" onclick="closeModal(\'modalScanUpc\'); openModal(\'modalRecordMovement\')"><i class="fas fa-plus"></i> Registrar Movimiento</button>' +
					'</div></div>';
			} else {
				box.innerHTML = '<div class="alert alert-warning m-0">Código "' + code + '" no registrado en catálogo de mercancías.</div>';
			}
		}, function () {
			box.innerHTML = '<div class="alert alert-danger m-0">Error de conexión al escanear.</div>';
		});
	};

	document.getElementById('btnScanUpcLookup').addEventListener('click', performUpcLookup);
	document.getElementById('txtScanUpcInput').addEventListener('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			performUpcLookup();
		}
	});

	// 6. Filtro en vivo para Stock Overview
	var txtFilterStock = document.getElementById('txtFilterStock');
	if (txtFilterStock) {
		txtFilterStock.addEventListener('input', function () {
			var q = this.value.toLowerCase().trim();
			document.querySelectorAll('#tableStockOverview tbody tr').forEach(function (tr) {
				var text = tr.innerText.toLowerCase();
				tr.style.display = text.indexOf(q) !== -1 ? '' : 'none';
			});
		});
	}

	// 7. Buscador de Trazabilidad 360°
	var formSearchTrace = document.getElementById('formSearchTrace');
	if (formSearchTrace) {
		formSearchTrace.addEventListener('submit', function (e) {
			e.preventDefault();
			var q = document.getElementById('txtTraceQueryInput').value.trim();
			if (!q) return;

			toastr.info('Consultando trazabilidad 360°...');
			Grocy.Api.Get('merchandise/trace/search?q=' + encodeURIComponent(q), function (resp) {
				toastr.success('Recorrido recuperado con éxito');
				if (resp.product) {
					document.getElementById('traceViewProdName').textContent = resp.product.name;
					document.getElementById('traceViewSku').textContent = resp.product.sku || q;
				}
				if (resp.stages && resp.stages.length > 0) {
					var sHtml = '';
					resp.stages.forEach(function (st, idx) {
						var iconCol = st.status === 'complete' ? 'text-success' : (st.status === 'in_progress' ? 'text-warning' : 'text-info');
						var badgeCol = st.status === 'complete' ? 'badge-success' : (st.status === 'in_progress' ? 'badge-warning' : 'badge-info');
						sHtml += '<div class="mb-3 position-relative">' +
							'<i class="fas fa-circle ' + iconCol + ' position-absolute" style="left: -21px; top: 3px; font-size: 0.65rem;"></i>' +
							'<strong>' + (idx + 1) + '. ' + st.stage + '</strong> · <span class="badge ' + badgeCol + ' py-0">' + (st.date || 'Auditado') + '</span>' +
							'<div class="small text-muted mt-1">' + st.desc + '</div>' +
							'</div>';
					});
					document.getElementById('traceViewTimeline').innerHTML = sHtml;
				}
			}, function () {
				toastr.error('No se pudo encontrar trazabilidad para el código especificado');
			});
		});
	}

	// 8. Formulario Recepción de Traslado
	var formRec = document.getElementById('formReceiveTransfer');
	if (formRec) {
		formRec.addEventListener('submit', function (e) {
			e.preventDefault();
			var id = document.getElementById('recTransferId').value;
			var recQty = parseFloat(document.getElementById('txtRecReceivedQty').value) || 0;
			var sentQty = parseFloat(document.getElementById('recSentUnits').textContent) || 0;
			var notes = document.getElementById('txtRecNotes').value;
			var diffType = document.getElementById('selRecDiffType').value;

			Grocy.Api.Post('merchandise/transfers/' + id + '/receive', {
				received_qty: recQty,
				sent_qty: sentQty,
				diff_type: diffType,
				notes: notes
			}, function (resp) {
				toastr.success(resp.message || 'Recepción confirmada con éxito');
				closeModal('modalReceiveTransfer');
				setTimeout(function () { location.reload(); }, 900);
			}, function (xhr) {
				toastr.error('Error al procesar la recepción');
			});
		});
	}

	// Cálculo en vivo de diferencia al recibir traslado
	var txtRecReceivedQty = document.getElementById('txtRecReceivedQty');
	if (txtRecReceivedQty) {
		txtRecReceivedQty.addEventListener('input', function () {
			var rec = parseFloat(this.value) || 0;
			var sent = parseFloat(document.getElementById('recSentUnits').textContent) || 0;
			var diff = rec - sent;
			var lbl = document.getElementById('lblRecDifference');
			var diffBox = document.getElementById('groupRecDiffReason');

			if (diff === 0) {
				lbl.textContent = '0 u. (Totalmente Conforme)';
				lbl.className = 'font-weight-bold text-success';
				diffBox.style.display = 'none';
			} else if (diff < 0) {
				lbl.textContent = diff + ' u. (Faltante Detectado)';
				lbl.className = 'font-weight-bold text-danger';
				diffBox.style.display = 'block';
			} else {
				lbl.textContent = '+' + diff + ' u. (Sobrante Detectado)';
				lbl.className = 'font-weight-bold text-warning';
				diffBox.style.display = 'block';
			}
		});
	}

	// 9. Formulario Acción de Diferencia
	var formDiff = document.getElementById('formDiffAction');
	if (formDiff) {
		formDiff.addEventListener('submit', function (e) {
			e.preventDefault();
			var diffId = document.getElementById('diffActionId').value;
			var action = document.getElementById('selDiffActionChoice').value;
			var notes = document.getElementById('txtDiffActionNotes').value;

			Grocy.Api.Post('merchandise/differences/' + diffId + '/action', {
				action: action,
				notes: notes
			}, function (resp) {
				toastr.success(resp.message || 'Acción registrada con éxito');
				closeModal('modalDiffAction');
				setTimeout(function () { location.reload(); }, 900);
			}, function () {
				toastr.error('Error al ejecutar la acción');
			});
		});
	}

	// 10. Exportar Reportes
	var exportMovements = function () {
		var table = document.getElementById('tableMerchandise');
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
		link.download = 'daily_mercancias_' + new Date().toISOString().slice(0, 10) + '.csv';
		link.click();
	};

	var btnExp = document.getElementById('btnExportMovements');
	if (btnExp) btnExp.addEventListener('click', exportMovements);
	var btnRep = document.getElementById('btnExportFullReport');
	if (btnRep) btnRep.addEventListener('click', exportMovements);
	var btnExpStock = document.getElementById('btnExportStock');
	if (btnExpStock) btnExpStock.addEventListener('click', exportMovements);

	// 11. Render de los 4 Gráficos Compactos
	initMerchandiseCharts();
});

// Helpers Globales para Acciones Operativas

function openReceiveTransferModal(id, code, prod, sent) {
	document.getElementById('recTransferId').value = id;
	document.getElementById('recTransferCode').textContent = code;
	document.getElementById('recTransferProd').textContent = prod;
	document.getElementById('recSentUnits').textContent = sent;
	document.getElementById('txtRecReceivedQty').value = sent;
	document.getElementById('lblRecDifference').textContent = '0 u. (Totalmente Conforme)';
	document.getElementById('lblRecDifference').className = 'font-weight-bold text-success';
	document.getElementById('groupRecDiffReason').style.display = 'none';
	openModal('modalReceiveTransfer');
}

function handleDiffAction(id, action, ref) {
	document.getElementById('diffActionId').value = id;
	document.getElementById('diffActionCaseRef').textContent = ref;
	document.getElementById('selDiffActionChoice').value = action;
	document.getElementById('txtDiffActionNotes').value = '';
	openModal('modalDiffAction');
}

function decideAdjustment(id, decision) {
	var actionLabel = decision === 'approve' ? 'aprobar y asentar en existencias' : 'rechazar formalmente';
	if (!confirm('¿Confirma que desea ' + actionLabel + ' el ajuste de inventario #' + id + '?')) {
		return;
	}

	toastr.info('Procesando autorización de ajuste...');
	Grocy.Api.Post('merchandise/adjustments/' + id + '/decide', {
		decision: decision,
		reason: decision === 'approve' ? 'Aprobado formalmente por Gerencia' : 'Rechazado tras revisión de auditoría'
	}, function (resp) {
		toastr.success(resp.message || 'Ajuste procesado');
		setTimeout(function () { location.reload(); }, 900);
	}, function (xhr) {
		var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al procesar el ajuste';
		toastr.error(msg);
	});
}

function initMerchandiseCharts() {
	// Gráfico 1: Entradas vs Salidas
	var ctx1 = document.getElementById('chartEntriesVsExits');
	if (ctx1) {
		new Chart(ctx1, {
			type: 'bar',
			data: {
				labels: ['26 oct', '27 oct', '28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
				datasets: [
					{ label: 'Entradas', data: [180, 160, 210, 250, 195, 240, 220, 260], backgroundColor: '#06b6d4', borderRadius: 3 },
					{ label: 'Salidas', data: [140, 155, 175, 210, 180, 220, 210, 245], backgroundColor: '#ef4444', borderRadius: 3 }
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: { grid: { display: false } }, y: { display: false } }
			}
		});
	}

	// Gráfico 2: Movimientos por tipo (Donut)
	var ctx2 = document.getElementById('chartMovesByType');
	if (ctx2) {
		new Chart(ctx2, {
			type: 'doughnut',
			data: {
				labels: ['Entrada', 'Salida', 'Traslado', 'Ajuste', 'Recepción'],
				datasets: [{
					data: [45.2, 28.7, 12.4, 7.3, 4.8],
					backgroundColor: ['#06b6d4', '#ef4444', '#3b82f6', '#8b5cf6', '#10b981'],
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '70%',
				plugins: { legend: { display: false }, tooltip: { enabled: true } }
			}
		});
	}

	// Gráfico 4: Diferencias por departamento
	var ctx4 = document.getElementById('chartDiffByDept');
	if (ctx4) {
		new Chart(ctx4, {
			type: 'bar',
			data: {
				labels: ['Abarrotes', 'Lácteos', 'Bebidas', 'Carnes', 'Higiene'],
				datasets: [{
					data: [8, 6, 4, 2, 1],
					backgroundColor: ['#38bdf8', '#38bdf8', '#f59e0b', '#ef4444', '#a855f7'],
					borderRadius: 3
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: { grid: { display: false }, ticks: { font: { size: 9 } } }, y: { display: false } }
			}
		});
	}
}

// Drawer de Trazabilidad 360°
function openTraceDrawer(productId, productName) {
	var drawer = document.getElementById('drawerTraceJourney');
	drawer.classList.add('open');

	document.getElementById('drTraceProductName').textContent = productName || 'Trazabilidad Operativa';
	var stagesBox = document.getElementById('drTraceStagesContainer');
	var historyBox = document.getElementById('drTraceHistoryContainer');
	stagesBox.innerHTML = '<div class="text-center p-2 text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando etapas...</div>';
	historyBox.innerHTML = '';

	Grocy.Api.Get('merchandise/trace/' + productId, function (resp) {
		// Etapas
		var sHtml = '<div class="border-left pl-3 ml-2">';
		(resp.stages || []).forEach(function (st) {
			var iconColor = st.status === 'complete' ? 'text-success' : (st.status === 'in_progress' ? 'text-warning' : 'text-muted');
			sHtml += '<div class="mb-3 position-relative">' +
				'<i class="fas fa-circle ' + iconColor + ' position-absolute" style="left:-21px; top:3px; font-size:0.6rem"></i>' +
				'<strong>' + st.stage + '</strong> · <span class="text-muted small">' + st.date + '</span><br>' +
				'<span class="text-muted small">' + st.desc + '</span>' +
				'</div>';
		});
		sHtml += '</div>';
		stagesBox.innerHTML = sHtml;

		// Historial de movimientos
		var hHtml = '<table class="table table-sm table-striped m-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Doc</th></tr></thead><tbody>';
		(resp.history || []).forEach(function (h) {
			hHtml += '<tr><td>' + (h.effective_at || h.recorded_at).slice(0, 16) + '</td><td>' + h.bucket + '</td><td>' + h.amount + '</td><td>' + (h.document || '—') + '</td></tr>';
		});
		hHtml += '</tbody></table>';
		historyBox.innerHTML = hHtml;
	}, function () {
		stagesBox.innerHTML = '<div class="alert alert-warning m-0">No se pudieron recuperar las etapas del producto.</div>';
	});
}

function filterByStage(stage) {
	toastr.info('Filtrando operaciones en etapa: ' + stage);
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
