@extends('layout.default')

@section('title', 'Ciclo 2: Planeación y Compras')

@section('content')
@include('components.alert_strip', ['alertCategories' => ['purchasing']])
@php
	$sum = $mrp['summary'];
	$fmt = fn($val) => '$' . number_format(floatval($val), 2);
	$statusMeta = [
		'reorder_needed' => ['Reponer', 'danger'],
		'overstock_risk' => ['Sobreinventario', 'warning'],
		'no_history' => ['Sin historial', 'secondary'],
		'normal' => ['Normal', 'success'],
	];
	$orderStatusMeta = [
		'draft' => ['Borrador', 'secondary'],
		'approved' => ['Aprobada', 'primary'],
		'sent' => ['Enviada', 'info'],
		'partially_received' => ['Recepción parcial', 'warning'],
		'received' => ['Recibida', 'success'],
		'closed' => ['Cerrada con faltante', 'dark'],
		'cancelled' => ['Cancelada', 'light'],
	];
	$items = $mrp['items'];
	$canManage = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_PROCUREMENT_MANAGE]);
	$canReceive = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_PROCUREMENT_RECEIVE]);

	usort($items, function($a, $b) {
		$rank = ['reorder_needed' => 0, 'overstock_risk' => 1, 'no_history' => 2, 'normal' => 3];
		return [$rank[$a['status']], -$a['projected_spoilage_cost']] <=> [$rank[$b['status']], -$b['projected_spoilage_cost']];
	});
	$withWarnings = count(array_filter($items, fn($i) => !empty($i['warnings'])));
	$spoilageItems = count(array_filter($items, fn($i) => $i['projected_spoilage'] > 0 || $i['expired_stock'] > 0));
	$draftOrdersCount = count(array_filter($orders, fn($o) => $o->status === 'draft'));
@endphp

<!-- ENCABEZADO CORPORATIVO DAILY -->
<div class="row align-items-center mb-3 daily-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-cart-flatbed"></i></div>
		<div>
			<h4 class="dairy-page-title mb-0">Ciclo 2: Planeación y Compras</h4>
			<p class="dairy-page-subtitle mb-0">Determinación de necesidades MRP, pronóstico con estacionalidad, gestión de proveedores, órdenes y abastecimiento continuo.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right dairy-page-actions d-flex flex-wrap justify-content-md-end align-items-center" style="gap: 6px;">
		<form method="get" class="d-inline-flex align-items-center" id="mrp-filter-form">
			<select name="branch_id" class="custom-select custom-select-sm mr-1" onchange="this.form.submit()" style="max-width: 170px;">
				<option value="">Todas las sucursales</option>
				@foreach($branches as $b)
				<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
				@endforeach
			</select>
			<select id="mrp-horizon" name="horizon_days" class="custom-select custom-select-sm mr-1" onchange="this.form.submit()" style="max-width: 110px;">
				@foreach([7, 14, 21, 30, 60] as $h)
				<option value="{{ $h }}" @if($horizonDays == $h) selected @endif>{{ $h }} días</option>
				@endforeach
			</select>
		</form>

		@if($canManage)
		<div class="btn-group btn-group-sm">
			<button id="mrp-generate-orders" class="btn btn-primary" @if($sum['reorder_needed_count'] == 0) disabled @endif title="Generar órdenes borrador agrupadas por proveedor">
				<i class="fa-solid fa-file-invoice-dollar mr-1"></i>Generar Órdenes
			</button>
			<button class="btn btn-outline-success" data-toggle="modal" data-target="#modalBulkApprove" @if($draftOrdersCount == 0) disabled @endif title="Aprobar todas las órdenes en borrador">
				<i class="fa-solid fa-check-double mr-1"></i>Aprobar Borradores ({{ $draftOrdersCount }})
			</button>
		</div>
		<button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modalSolicitarCotizacion" title="Solicitar Cotización Formal (RFQ)">
			<i class="fa-solid fa-file-pen mr-1"></i>Solicitar Cotización
		</button>
		<button id="mrp-receiving-rules" class="btn btn-sm btn-outline-secondary" title="Reglas de vida útil remanente y temperatura">
			<i class="fa-solid fa-clipboard-check mr-1"></i>Reglas Calidad
		</button>
		@endif
	</div>
</div>

<!-- LOS 8 KPIS ESTRATÉGICOS DEL CICLO 2 -->
<div class="row mb-3">
	<!-- KPI 1: Precisión de Pronóstico -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">1. Precisión Pronóstico</span>
				<span class="badge @if($procurementKpis['forecast_accuracy'] >= 80) badge-success @elseif($procurementKpis['forecast_accuracy'] >= 65) badge-warning @else badge-danger @endif">
					WAPE: {{ $procurementKpis['forecast_wape'] }}%
				</span>
			</div>
			<div class="h3 font-weight-bold text-dark mt-2 mb-0">{{ $procurementKpis['forecast_accuracy'] }}%</div>
			<div class="small text-muted mt-1">Sesgo comercial: {{ ($procurementKpis['forecast_bias'] >= 0 ? '+' : '') . $procurementKpis['forecast_bias'] }}%</div>
		</div>
	</div>

	<!-- KPI 2: Cumplimiento Proveedor (OTIF) -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">2. Cumplimiento OTIF</span>
				<span class="badge badge-success"><i class="fa-solid fa-truck-ramp-box mr-1"></i>En fecha y forma</span>
			</div>
			<div class="h3 font-weight-bold text-success mt-2 mb-0">{{ $procurementKpis['otif'] }}%</div>
			<div class="small text-muted mt-1">Sobre entregas auditadas de la red</div>
		</div>
	</div>

	<!-- KPI 3: Lead Time Promedio -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">3. Lead Time Promedio</span>
				<span class="badge badge-primary"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Tiempo reposición</span>
			</div>
			<div class="h3 font-weight-bold text-primary mt-2 mb-0">{{ $procurementKpis['avg_lead_time'] }} <span class="h6 text-muted font-weight-normal">días</span></div>
			<div class="small text-muted mt-1">Plazo medio de entrega de proveedores</div>
		</div>
	</div>

	<!-- KPI 4: Costo de Compra Real -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">4. Costo de Compra</span>
				<span class="badge badge-light border text-muted">Ingresado al stock</span>
			</div>
			<div class="h3 font-weight-bold text-dark mt-2 mb-0">{{ $fmt($procurementKpis['purchases_cost']) }}</div>
			<div class="small text-muted mt-1">Total de recepciones aceptadas</div>
		</div>
	</div>

	<!-- KPI 5: Órdenes Pendientes -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">5. Órdenes Pendientes</span>
				<span class="badge badge-warning text-dark">{{ $procurementKpis['pending_orders_count'] }} activas</span>
			</div>
			<div class="h3 font-weight-bold text-dark mt-2 mb-0">{{ $fmt($procurementKpis['pending_orders_cost']) }}</div>
			<div class="small text-muted mt-1">En borrador, aprobadas y en tránsito</div>
		</div>
	</div>

	<!-- KPI 6: Pedidos Atrasados -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">6. Pedidos Atrasados</span>
				<span class="badge @if($procurementKpis['overdue_orders_count'] > 0) badge-danger @else badge-success @endif">
					{{ $procurementKpis['overdue_orders_count'] > 0 ? 'Demora activa' : 'Al día' }}
				</span>
			</div>
			<div class="h3 font-weight-bold @if($procurementKpis['overdue_orders_count'] > 0) text-danger @else text-success @endif mt-2 mb-0">
				{{ $procurementKpis['overdue_orders_count'] }} <span class="h6 text-muted font-weight-normal">pedidos</span>
			</div>
			<div class="small text-muted mt-1">{{ $fmt($procurementKpis['overdue_orders_cost']) }} con fecha vencida</div>
		</div>
	</div>

	<!-- KPI 7: Cobertura Proyectada -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">7. Cobertura Proyectada</span>
				<span class="badge badge-info"><i class="fa-solid fa-boxes-stacked mr-1"></i>Inventario neto</span>
			</div>
			<div class="h3 font-weight-bold text-info mt-2 mb-0">{{ $procurementKpis['avg_coverage'] }} <span class="h6 text-muted font-weight-normal">días</span></div>
			<div class="small text-muted mt-1">Autonomía promedio en la red</div>
		</div>
	</div>

	<!-- KPI 8: Tasa de Abastecimiento (Fill Rate) -->
	<div class="col-xl-3 col-md-6 mb-2">
		<div class="card shadow-sm border-0 h-100 p-3">
			<div class="d-flex justify-content-between align-items-center">
				<span class="text-muted small text-uppercase font-weight-bold">8. Fill Rate (Tasa Abasto)</span>
				<span class="badge badge-success"><i class="fa-solid fa-circle-check mr-1"></i>Servicio</span>
			</div>
			<div class="h3 font-weight-bold text-success mt-2 mb-0">{{ $procurementKpis['fill_rate'] }}%</div>
			<div class="small text-muted mt-1">Unidades recibidas vs solicitadas</div>
		</div>
	</div>
</div>

<!-- CICLO OPERATIVO: LOS 12 PASOS DE ABASTECIMIENTO -->
<div class="card shadow-sm border-0 mb-3">
	<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center py-2">
		<span><i class="fa-solid fa-diagram-project text-primary mr-2"></i>Ciclo Operativo de Abastecimiento Retail (12 Fases)</span>
		<span class="badge badge-light border text-muted">Cadena de Suministro Cerrada</span>
	</div>
	<div class="card-body p-2 bg-light">
		<div class="d-flex flex-wrap justify-content-between text-center" style="gap: 4px; font-size: 0.78rem;">
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">1. Pronóstico</strong>
				<span class="text-muted">Demanda + WAPE</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">2. Stock FEFO</strong>
				<span class="text-muted">Neto disponible</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">3. Necesidades</strong>
				<span class="text-muted">ROP & Seguridad</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">4. Evaluación</strong>
				<span class="text-muted">Scorecard OTIF</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">5. Cotización</strong>
				<span class="text-muted">RFQ & Costo</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">6. Solicitud</strong>
				<span class="text-muted">Borrador OC</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">7. Aprobación</strong>
				<span class="text-muted">Gerencial / Lote</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">8. Emisión</strong>
				<span class="text-muted">Enviada a Prov.</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">9. Seguimiento</strong>
				<span class="text-muted">En tránsito</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">10. Recepción</strong>
				<span class="text-muted">Calidad e Inocuidad</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">11. Desempeño</strong>
				<span class="text-muted">3 vías & Faltantes</span>
			</div>
			<div class="d-flex align-items-center text-muted"><i class="fa-solid fa-angle-right"></i></div>
			<div class="bg-white p-2 rounded shadow-sm flex-fill border">
				<strong class="text-primary d-block">12. Replanif.</strong>
				<span class="text-muted">Recálculo continuo</span>
			</div>
		</div>
	</div>
</div>

<!-- NAVEGACIÓN PRINCIPAL: LAS 4 HERRAMIENTAS DE ABASTECIMIENTO -->
<div class="nav daily-level-tabs flex-wrap mb-3" role="tablist" style="gap: 4px;">
	<button type="button" class="nav-link daily-level-tab @if($activeTab === 'mrp' || !$activeTab) active @endif" data-toggle="pill" data-target="#tab-mrp" role="tab" id="pill-mrp">
		<i class="fa-solid fa-calculator mr-1"></i>
		<span>1. Planificación MRP</span>
		<span class="daily-level-badge badge-danger">{{ $sum['reorder_needed_count'] }}</span>
	</button>
	<button type="button" class="nav-link daily-level-tab @if($activeTab === 'proveedores') active @endif" data-toggle="pill" data-target="#tab-proveedores" role="tab" id="pill-proveedores">
		<i class="fa-solid fa-truck-field mr-1"></i>
		<span>2. Proveedores & Desempeño</span>
		<span class="daily-level-badge">{{ count($suppliers) }}</span>
	</button>
	<button type="button" class="nav-link daily-level-tab @if($activeTab === 'ordenes') active @endif" data-toggle="pill" data-target="#tab-ordenes" role="tab" id="pill-ordenes">
		<i class="fa-solid fa-file-invoice mr-1"></i>
		<span>3. Órdenes de Compra & Recepción</span>
		<span class="daily-level-badge">{{ count($orders) }}</span>
	</button>
	<button type="button" class="nav-link daily-level-tab @if($activeTab === 'abastecimiento') active @endif" data-toggle="pill" data-target="#tab-abastecimiento" role="tab" id="pill-abastecimiento">
		<i class="fa-solid fa-truck-ramp-box mr-1"></i>
		<span>4. Abastecimiento & Tránsito</span>
		<span class="daily-level-badge badge-warning">{{ count($inTransitOrders) > 0 ? count($inTransitOrders) : count($priorities['emergency']) }}</span>
	</button>
</div>

<!-- CONTENEDOR DE PESTAÑAS -->
<div class="tab-content" id="mrpTabContent">

	<!-- ============================================================== -->
	<!-- PESTAÑA 1: PLANIFICACIÓN MRP                                   -->
	<!-- ============================================================== -->
	<div class="tab-pane fade @if($activeTab === 'mrp' || !$activeTab) show active @endif" id="tab-mrp" role="tabpanel">

		<!-- TARJETAS DE RESUMEN MRP -->
		<div class="row mb-3">
			<div class="col-md-3 col-sm-6 mb-2">
				<div class="p-3 bg-white rounded shadow-sm border">
					<div class="text-muted small text-uppercase font-weight-bold">A Reponer Urgente</div>
					<div class="h4 font-weight-bold text-danger mb-0 mt-1">{{ $sum['reorder_needed_count'] }} <span class="h6 text-muted font-weight-normal">productos</span></div>
					<div class="small text-muted">Bajo punto de reorden (ROP)</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-2">
				<div class="p-3 bg-white rounded shadow-sm border">
					<div class="text-muted small text-uppercase font-weight-bold">Inversión Propuesta</div>
					<div class="h4 font-weight-bold text-primary mb-0 mt-1">{{ $fmt($sum['total_suggested_order_cost']) }}</div>
					<div class="small text-muted">Costo total de órdenes sugeridas</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-2">
				<div class="p-3 bg-white rounded shadow-sm border">
					<div class="text-muted small text-uppercase font-weight-bold">Riesgo Sobreinventario</div>
					<div class="h4 font-weight-bold text-warning mb-0 mt-1">{{ $sum['overstock_risk_count'] }} <span class="h6 text-muted font-weight-normal">productos</span></div>
					<div class="small text-muted">Cobertura supera el 80% de vida útil</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6 mb-2">
				<div class="p-3 bg-white rounded shadow-sm border">
					<div class="text-muted small text-uppercase font-weight-bold">Merma Proyectada FEFO</div>
					<div class="h4 font-weight-bold @if($sum['projected_spoilage_cost'] > 0) text-danger @else text-success @endif mb-0 mt-1">{{ $fmt($sum['projected_spoilage_cost']) }}</div>
					<div class="small text-muted">{{ $spoilageItems }} productos expuestos en {{ $horizonDays }} días</div>
				</div>
			</div>
		</div>

		<!-- PRECISIÓN Y AJUSTES DE PRONÓSTICO -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-body py-2 d-flex flex-wrap align-items-center">
				<div class="mr-4"><span class="small text-muted text-uppercase font-weight-bold">Pronóstico Motor Laboral & Ventas:</span></div>
				<div class="mr-4"><span class="small text-muted">Error WAPE:</span> <strong class="@if(($forecast['wape_pct'] ?? 0) > 30) text-warning @else text-success @endif">{{ $forecast['wape_pct'] ?? '—' }}%</strong></div>
				<div class="mr-4"><span class="small text-muted">Sesgo de Demanda:</span> <strong>{{ $forecast['bias_pct'] !== null ? ($forecast['bias_pct'] > 0 ? '+' : '') . $forecast['bias_pct'] . '%' : '—' }}</strong></div>
				<div class="mr-4"><span class="small text-muted">Quiebres Actuales:</span> <strong class="@if(count($forecast['stockouts'] ?? [])) text-danger @endif">{{ count($forecast['stockouts'] ?? []) }} productos</strong></div>
				@php $fo = $mrp['forecast'] ?? null; @endphp
				@if($fo)
				<div class="mr-3 small text-muted">
					Ajustes: <strong>{{ $fo['correction']['applied'] ? 'corrección ×' . $fo['correction']['factor'] : 'normal' }}</strong> ·
					<strong>{{ $fo['seasonality']['applied'] ? 'estacionalidad ×' . $fo['seasonality']['factor'] : 'sin estacionalidad' }}</strong>
				</div>
				@endif
			</div>
		</div>

		<!-- LISTA DE NECESIDADES MRP -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 p-3">
				<div class="d-flex flex-wrap justify-content-between align-items-center">
					<div>
						<span class="font-weight-bold text-dark h6 mb-0">
							<i class="fa-solid fa-table-list text-primary mr-2"></i>Lista de Necesidades de Abastecimiento MRP
						</span>
						<span class="badge badge-light border text-muted ml-2">{{ count($items) }} productos analizados</span>
					</div>
					<div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
						<div class="btn-group btn-group-sm" role="group">
							<button type="button" class="btn btn-outline-secondary btn-mrp-filter active" data-filter="all">Todos</button>
							<button type="button" class="btn btn-outline-danger btn-mrp-filter" data-filter="reorder">A Reponer</button>
							<button type="button" class="btn btn-outline-warning btn-mrp-filter" data-filter="overstock">Sobreinventario</button>
						</div>
						<div class="input-group input-group-sm" style="max-width: 240px;">
							<div class="input-group-prepend">
								<span class="input-group-text bg-light border-right-0"><i class="fa-solid fa-magnifying-glass"></i></span>
							</div>
							<input type="text" id="searchMrpInput" class="form-control border-left-0" placeholder="Buscar por producto o categoría...">
						</div>
					</div>
				</div>
			</div>
			<div class="table-responsive">
				<table id="mrp-table" class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Producto / Depto</th>
							<th class="text-right" title="Stock físico actual en almacén">Stock Actual</th>
							<th class="text-right" title="Stock en cuarentena o por vencer">No Disp.</th>
							<th class="text-right" title="Stock usable - merma proyectada + órdenes abiertas">Disp. Neto</th>
							<th class="text-right" title="Velocidad media de venta diaria">VMD</th>
							<th class="text-right" title="Días de inventario disponible">Cobertura</th>
							<th class="text-right" title="Stock de seguridad">SS</th>
							<th class="text-right" title="Punto de reorden">ROP</th>
							<th class="text-center">Estado</th>
							<th class="text-right" title="Cantidad calculada por el motor MRP">Sugerido</th>
							<th class="text-right">Costo Est.</th>
							<th>Proveedor</th>
							<th class="text-center">Acciones</th>
						</tr>
					</thead>
					<tbody>
						@forelse($items as $i)
						@php
							$sm = $statusMeta[$i['status']] ?? [$i['status'], 'secondary'];
							$isReorder = ($i['status'] === 'reorder_needed');
						@endphp
						<tr class="mrp-row @if($isReorder) table-danger-light @endif"
							data-name="{{ strtolower($i['product_name'] . ' ' . $i['category_name']) }}"
							data-status="{{ $i['status'] }}">
							<td>
								<div class="font-weight-600 text-dark">{{ $i['product_name'] }}</div>
								<div class="small text-muted">{{ $i['category_name'] }}</div>
							</td>
							<td class="text-right">{{ $i['current_stock'] }} <span class="text-muted small">{{ $i['qu_name'] }}</span></td>
							<td class="text-right @if($i['quarantine_stock'] > 0 || $i['projected_spoilage'] > 0) text-danger font-weight-bold @else text-muted @endif">
								{{ ($i['quarantine_stock'] + $i['projected_spoilage']) > 0 ? ($i['quarantine_stock'] + $i['projected_spoilage']) : '—' }}
							</td>
							<td class="text-right font-weight-bold @if($i['net_available'] <= $i['rop']) text-danger @else text-success @endif">
								{{ $i['net_available'] }}
							</td>
							<td class="text-right font-weight-600">{{ number_format($i['vmd'], 1) }}</td>
							<td class="text-right">
								<span class="badge @if($i['days_coverage'] < 3) badge-danger @elseif($i['days_coverage'] < 7) badge-warning @else badge-light border @endif">
									{{ $i['days_coverage'] < 90 ? number_format($i['days_coverage'], 1) . ' d' : '>90 d' }}
								</span>
							</td>
							<td class="text-right text-muted small">{{ number_format($i['safety_stock'], 0) }}</td>
							<td class="text-right text-muted small">{{ number_format($i['rop'], 0) }}</td>
							<td class="text-center">
								<span class="badge badge-{{ $sm[1] }}">{{ $sm[0] }}</span>
							</td>
							<td class="text-right font-weight-bold @if($i['suggested_order'] > 0) text-primary @endif">
								{{ $i['suggested_order'] > 0 ? $i['suggested_order'] . ' ' . $i['qu_name'] : '—' }}
							</td>
							<td class="text-right font-weight-bold">{{ $i['estimated_cost'] > 0 ? $fmt($i['estimated_cost']) : '—' }}</td>
							<td class="small">
								@if($i['supplier_id'])
								<span class="font-weight-600 text-dark">{{ $i['supplier_name'] }}</span>
								<div class="text-muted small">LT: {{ $i['lead_time_days'] }}d · ${{ number_format($i['purchase_price'], 2) }}/u</div>
								@else
								<span class="badge badge-warning text-dark"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Sin proveedor</span>
								@endif
							</td>
							<td class="text-center">
								<div class="btn-group btn-group-sm">
									@if($i['supplier_id'])
									<button type="button" class="btn btn-outline-primary btn-xs btn-quick-rfq"
										data-product-id="{{ $i['product_id'] }}"
										data-product-name="{{ $i['product_name'] }}"
										data-supplier-id="{{ $i['supplier_id'] }}"
										data-qty="{{ max(1, $i['suggested_order']) }}"
										title="Solicitar cotización">
										<i class="fa-solid fa-file-pen"></i>
									</button>
									@endif
									<a href="{{ $U('/kardex?product_id=' . $i['product_id']) }}" class="btn btn-outline-secondary btn-xs" title="Ver Kardex">
										<i class="fa-solid fa-book-open"></i>
									</a>
								</div>
							</td>
						</tr>
						@empty
						<tr><td colspan="13" class="text-center text-muted py-3">No hay productos calculados en el horizonte.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ============================================================== -->
	<!-- PESTAÑA 2: PROVEEDORES Y DESEMPEÑO                             -->
	<!-- ============================================================== -->
	<div class="tab-pane fade @if($activeTab === 'proveedores') show active @endif" id="tab-proveedores" role="tabpanel">

		<!-- DIRECTORIO DE PROVEEDORES Y SCORECARD -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-truck-field text-primary mr-2"></i>
					<span>Directorio de Proveedores y Evaluación de Desempeño (Scorecard OTIF)</span>
				</div>
				<button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#modalSolicitarCotizacion">
					<i class="fa-solid fa-file-pen mr-1"></i>Solicitar Cotización (RFQ)
				</button>
			</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Proveedor</th>
							<th>Contacto & Canales Directos</th>
							<th class="text-center">Lead Time</th>
							<th class="text-right">Pedido Mínimo</th>
							<th class="text-center">Términos Pago</th>
							<th class="text-center">Calificación</th>
							<th class="text-center">Entregas</th>
							<th class="text-center">OTIF %</th>
							<th class="text-center">Incidencias</th>
							<th class="text-center">Acciones</th>
						</tr>
					</thead>
					<tbody>
						@forelse($suppliers as $s)
						@php
							$otifVal = floatval($s['otif_percentage'] ?? 100);
							$cleanPhone = preg_replace('/[^0-9]/', '', $s['phone'] ?? '');
							$waText = urlencode("Hola {$s['contact_name']}, le contacto desde DAILY Supermercados para coordinar abastecimiento.");
						@endphp
						<tr>
							<td>
								<div class="font-weight-bold text-dark">{{ $s['name'] }}</div>
								<div class="small text-muted">{{ $s['address'] ?? 'Parque Industrial' }}</div>
							</td>
							<td>
								<div class="font-weight-600 text-dark">{{ $s['contact_name'] ?? 'Ejecutivo de Cuenta' }}</div>
								<div class="d-flex align-items-center mt-1" style="gap: 6px;">
									@if(!empty($s['phone']))
									<a href="https://wa.me/{{ $cleanPhone }}?text={{ $waText }}" target="_blank" class="btn btn-xs btn-outline-success" title="Enviar WhatsApp">
										<i class="fa-brands fa-whatsapp"></i>
									</a>
									<a href="tel:{{ $s['phone'] }}" class="btn btn-xs btn-outline-secondary" title="Llamar {{ $s['phone'] }}">
										<i class="fa-solid fa-phone"></i>
									</a>
									@endif
									@if(!empty($s['email']))
									<a href="mailto:{{ $s['email'] }}?subject={{ urlencode('DAILY - Solicitud de Pedido / Abastecimiento') }}" class="btn btn-xs btn-outline-primary" title="Enviar correo a {{ $s['email'] }}">
										<i class="fa-solid fa-envelope"></i>
									</a>
									@endif
								</div>
							</td>
							<td class="text-center font-weight-bold">{{ $s['lead_time_days'] ?? 2 }} días</td>
							<td class="text-right">{{ $fmt($s['min_order_amount'] ?? 0) }}</td>
							<td class="text-center"><span class="badge badge-light border">{{ $s['payment_terms'] ?? 'Contado' }}</span></td>
							<td class="text-center">
								<span class="badge badge-warning text-dark font-weight-bold">
									⭐ {{ number_format($s['rating'] ?? 5, 1) }}
								</span>
							</td>
							<td class="text-center font-weight-bold">{{ number_format($s['deliveries_count'] ?? 0) }}</td>
							<td class="text-center">
								<span class="badge @if($otifVal >= 90) badge-success @elseif($otifVal >= 75) badge-warning @else badge-danger @endif">
									{{ number_format($otifVal, 1) }}%
								</span>
							</td>
							<td class="text-center">
								@if(($s['pending_incidents_count'] ?? 0) > 0)
								<span class="badge badge-danger">{{ $s['pending_incidents_count'] }} abiertas</span>
								@else
								<span class="badge badge-success"><i class="fa-solid fa-check"></i> 0</span>
								@endif
							</td>
							<td class="text-center">
								<button type="button" class="btn btn-xs btn-outline-primary btn-rfq-supplier" data-supplier-id="{{ $s['id'] }}" data-supplier-name="{{ $s['name'] }}" title="Nueva Solicitud de Cotización">
									<i class="fa-solid fa-file-pen mr-1"></i>Cotizar
								</button>
							</td>
						</tr>
						@empty
						<tr><td colspan="10" class="text-center text-muted py-3">Sin proveedores registrados.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- INCIDENCIAS E INCUMPLIMIENTOS CON PROVEEDORES -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-triangle-exclamation text-warning mr-2"></i>
					<span>Incidencias e Incumplimientos con Proveedores</span>
				</div>
				<span class="badge badge-light border text-muted">Gestión de Reclamos y Penalizaciones</span>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Fecha</th>
							<th>Proveedor</th>
							<th>Tipo de Incidencia</th>
							<th>Severidad</th>
							<th>Descripción</th>
							<th class="text-right">Impacto en Costo</th>
							<th>Estado</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse($incidents as $inc)
						<tr>
							<td class="text-muted small">{{ $inc['incident_date'] }}</td>
							<td class="font-weight-600">{{ $inc['supplier_name'] ?? 'Proveedor' }}</td>
							<td><span class="badge badge-light border text-uppercase">{{ $inc['incident_type'] }}</span></td>
							<td>
								<span class="badge @if($inc['severity'] === 'critical') badge-danger @elseif($inc['severity'] === 'high') badge-warning @else badge-secondary @endif">
									{{ $inc['severity'] }}
								</span>
							</td>
							<td class="small">{{ $inc['description'] }}</td>
							<td class="text-right font-weight-bold @if($inc['cost_impact'] > 0) text-danger @endif">{{ $fmt($inc['cost_impact']) }}</td>
							<td>
								<span class="badge @if($inc['status'] === 'resolved') badge-success @elseif($inc['status'] === 'open') badge-danger @else badge-warning @endif">
									{{ $inc['status'] === 'resolved' ? 'Resuelto' : ($inc['status'] === 'open' ? 'Abierto' : $inc['status']) }}
								</span>
							</td>
							<td class="text-center">
								@if($inc['status'] !== 'resolved')
								<button type="button" class="btn btn-xs btn-outline-success btn-resolve-incident"
									data-incident-id="{{ $inc['id'] }}"
									data-supplier-name="{{ $inc['supplier_name'] ?? '' }}"
									data-desc="{{ $inc['description'] }}"
									title="Resolver y conciliar penalización">
									<i class="fa-solid fa-check mr-1"></i>Resolver
								</button>
								@else
								<span class="text-muted small"><i class="fa-solid fa-circle-check text-success"></i> Conciliado</span>
								@endif
							</td>
						</tr>
						@empty
						<tr><td colspan="8" class="text-center text-muted py-3">No hay incidencias registradas con proveedores.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- SOLICITUDES DE COTIZACIÓN (RFQS) GENERADAS -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-file-contract text-info mr-2"></i>
					<span>Solicitudes de Cotización Emitidas (RFQs)</span>
				</div>
				<span class="badge badge-light border text-muted">Historial de Negociación</span>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Folio RFQ</th>
							<th>Proveedor</th>
							<th>Sucursal</th>
							<th>Título / Requerimiento</th>
							<th>Fecha Límite</th>
							<th>Estado</th>
							<th>Fecha Emisión</th>
						</tr>
					</thead>
					<tbody>
						@forelse($rfqs as $rfq)
						<tr>
							<td class="font-mono font-weight-bold text-primary">{{ $rfq['rfq_code'] }}</td>
							<td class="font-weight-600">{{ $rfq['supplier_name'] }}</td>
							<td>{{ $rfq['branch_name'] }}</td>
							<td>{{ $rfq['title'] }}</td>
							<td class="text-muted small">{{ $rfq['deadline_date'] }}</td>
							<td><span class="badge badge-info">{{ $rfq['status'] }}</span></td>
							<td class="text-muted small">{{ $rfq['created_at'] }}</td>
						</tr>
						@empty
						<tr><td colspan="7" class="text-center text-muted py-3">No hay solicitudes de cotización recientes.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

	</div>

	<!-- ============================================================== -->
	<!-- PESTAÑA 3: ÓRDENES DE COMPRA Y RECEPCIÓN                       -->
	<!-- ============================================================== -->
	<div class="tab-pane fade @if($activeTab === 'ordenes') show active @endif" id="tab-ordenes" role="tabpanel">

		<!-- ÓRDENES DE COMPRA -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 p-3 d-flex flex-wrap justify-content-between align-items-center">
				<div>
					<span class="font-weight-bold text-dark h6 mb-0">
						<i class="fa-solid fa-file-invoice text-primary mr-2"></i>Órdenes de Compra (Procurement Orders)
					</span>
					<span class="badge badge-light border text-muted ml-2">{{ count($orders) }} órdenes</span>
				</div>
				<div class="d-flex align-items-center" style="gap: 8px;">
					@if($canManage && $draftOrdersCount > 0)
					<button class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalBulkApprove">
						<i class="fa-solid fa-check-double mr-1"></i>Aprobar Todas las Borrador ({{ $draftOrdersCount }})
					</button>
					@endif
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Orden</th>
							<th>Proveedor</th>
							<th>Sucursal</th>
							<th class="text-right">Líneas</th>
							<th class="text-right">Costo Estimado</th>
							<th>Estado</th>
							<th>Creada</th>
							<th>Aprobó</th>
							<th title="Fecha de entrega comprometida (base del OTIF)">Entrega Comprometida</th>
							<th class="text-center">Acciones</th>
						</tr>
					</thead>
					<tbody>
						@forelse($orders as $o)
						@php $om = $orderStatusMeta[$o->status] ?? [$o->status, 'secondary']; @endphp
						<tr>
							<td class="font-mono font-weight-bold text-dark">{{ $o->order_code }}</td>
							<td class="font-weight-600">{{ $o->supplier_name }}</td>
							<td>{{ $o->branch_name }}</td>
							<td class="text-right">{{ $o->item_count }}</td>
							<td class="text-right font-weight-bold">{{ $fmt($o->total_estimated_cost) }}</td>
							<td><span class="badge badge-{{ $om[1] }}">{{ $om[0] }}</span></td>
							<td class="small text-muted">{{ $o->created_at }}</td>
							<td class="small">{{ $o->approved_by_name ?? '—' }}</td>
							<td class="small font-weight-600 @if($o->status === 'sent' && $o->expected_delivery_date < date('Y-m-d')) text-danger @endif">
								{{ $o->expected_delivery_date ?? '—' }}
							</td>
							<td class="text-center text-nowrap">
								@if($o->status === 'draft' && $canManage)
								<button class="btn btn-xs btn-outline-success mrp-approve-order" data-order-id="{{ $o->id }}">Aprobar</button>
								@endif
								@if($o->status === 'approved' && $canManage)
								<button class="btn btn-xs btn-outline-primary mrp-send-order" data-order-id="{{ $o->id }}">Enviar</button>
								@endif
								@if(in_array($o->status, ['approved', 'sent', 'partially_received']) && $canReceive)
								<button class="btn btn-xs btn-success mrp-receive-order" data-order-id="{{ $o->id }}"><i class="fa-solid fa-truck-ramp-box mr-1"></i>Recibir</button>
								@endif
								@if(in_array($o->status, ['draft', 'approved', 'sent', 'partially_received']) && $canManage)
								@php $isCancel = in_array($o->status, ['draft', 'approved']); @endphp
								<button class="btn btn-xs btn-outline-danger mrp-close-order" data-order-id="{{ $o->id }}" data-cancel="{{ $isCancel ? 1 : 0 }}">{{ $isCancel ? 'Cancelar' : 'Cerrar' }}</button>
								@endif
								@if(in_array($o->status, ['partially_received', 'received', 'closed']) && $canManage)
								<button class="btn btn-xs btn-outline-dark mrp-invoice-order" data-order-id="{{ $o->id }}" title="Registrar factura del proveedor y conciliar de 3 vías"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>Factura</button>
								@endif
								<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/order/' . $o->id) }}" title="Documento imprimible"><i class="fa-solid fa-print"></i></a>
							</td>
						</tr>
						@empty
						<tr><td colspan="10" class="text-center text-muted py-3">Sin órdenes de compra registradas.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- RECEPCIONES DE MERCANCÍA CONFIRMADAS -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-truck-ramp-box text-success mr-2"></i>
					<span>Recepciones de Mercancía Confirmadas (Ingreso Atómico al Kardex)</span>
				</div>
				<span class="badge badge-light border text-muted">Inocuidad, Temperatura & FEFO</span>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Recepción</th>
							<th>Orden</th>
							<th>Proveedor</th>
							<th>Doc. Proveedor</th>
							<th class="text-right">Temperatura</th>
							<th class="text-right">Ingresado (Aceptado)</th>
							<th class="text-right">Rechazado</th>
							<th>Recibió</th>
							<th>Fecha</th>
							<th class="text-center">Comprobante</th>
						</tr>
					</thead>
					<tbody>
						@forelse($receipts as $r)
						<tr>
							<td class="font-mono font-weight-bold text-dark">{{ $r['receipt_code'] }}</td>
							<td class="font-mono">{{ $r['order_code'] }}</td>
							<td>{{ $r['supplier_name'] }}</td>
							<td class="small">{{ $r['supplier_document'] ?? '—' }}</td>
							<td class="text-right">{{ $r['temperature_on_arrival'] !== null ? $r['temperature_on_arrival'] . ' °C' : '—' }}</td>
							<td class="text-right font-weight-bold text-success">{{ $fmt($r['total_accepted_cost']) }}</td>
							<td class="text-right @if($r['total_rejected_cost'] > 0) text-danger font-weight-bold @else text-muted @endif">
								{{ $r['total_rejected_cost'] > 0 ? $fmt($r['total_rejected_cost']) : '—' }}
							</td>
							<td class="small">{{ $r['received_by_name'] }}</td>
							<td class="small text-muted">{{ $r['received_at'] }}</td>
							<td class="text-center text-nowrap">
								<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/receipt/' . $r['id']) }}" title="Imprimir Comprobante"><i class="fa-solid fa-print"></i></a>
								@if($r['stock_transaction_id'])
								<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/stockjournal') }}" title="Ver en Diario de Stock"><i class="fa-solid fa-book"></i></a>
								@endif
							</td>
						</tr>
						@empty
						<tr><td colspan="10" class="text-center text-muted py-3">Sin recepciones registradas en el período.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- FACTURAS DE PROVEEDOR Y CONCILIACIÓN DE 3 VÍAS -->
		@if($canManage)
		@php $invMeta = \Grocy\Services\SupplierInvoiceService::STATUS; @endphp
		<div class="card shadow-sm border-0 mb-3" id="invoices">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-file-invoice-dollar text-primary mr-2"></i>
					<span>Facturas de Proveedor · Conciliación de 3 Vías (OC vs Recepción vs Factura)</span>
				</div>
				<span class="badge badge-light border text-muted">Tolerancia de Precio: {{ GROCY_INVOICE_PRICE_TOLERANCE_PCT }}%</span>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Folio Interno</th>
							<th>Factura Prov.</th>
							<th>Orden</th>
							<th>Proveedor</th>
							<th>Fecha</th>
							<th class="text-right">Total Facturado</th>
							<th class="text-right" title="Cobrado sin recibir o con sobreprecio">Monto en Riesgo</th>
							<th>Estado</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse($invoices as $inv)
						@php $im = $invMeta[$inv['status']] ?? [$inv['status'], 'secondary']; @endphp
						<tr>
							<td class="font-mono font-weight-bold">{{ $inv['invoice_code'] }}</td>
							<td class="font-weight-600">{{ $inv['invoice_number'] }}</td>
							<td class="font-mono">{{ $inv['order_code'] }}</td>
							<td>{{ $inv['supplier_name'] }}</td>
							<td class="small">{{ $inv['invoice_date'] }}</td>
							<td class="text-right font-weight-bold">{{ $fmt($inv['total']) }}</td>
							<td class="text-right font-weight-bold @if($inv['amount_at_risk'] > 0) text-danger @else text-success @endif">
								{{ $fmt($inv['amount_at_risk']) }}
							</td>
							<td><span class="badge badge-{{ $im[1] }}">{{ $im[0] }}</span></td>
							<td class="text-center">
								<button class="btn btn-xs btn-outline-primary mrp-invoice-detail"
									data-invoice-id="{{ $inv['id'] }}"
									data-code="{{ $inv['invoice_code'] }}"
									data-pending="{{ $inv['status'] === 'pending_decision' ? 1 : 0 }}">
									<i class="fa-solid fa-eye mr-1"></i>Conciliar
								</button>
							</td>
						</tr>
						@empty
						<tr><td colspan="9" class="text-center text-muted py-3">No hay facturas registradas para conciliar.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
		@endif

	</div>

	<!-- ============================================================== -->
	<!-- PESTAÑA 4: ABASTECIMIENTO, TRÁNSITO Y PRIORIDADES              -->
	<!-- ============================================================== -->
	<div class="tab-pane fade @if($activeTab === 'abastecimiento') show active @endif" id="tab-abastecimiento" role="tabpanel">

		<!-- SECCIÓN 1: MERCANCÍA EN TRÁNSITO Y PEDIDOS ATRASADOS -->
		<div class="row mb-3">
			<div class="col-lg-6 mb-3">
				<div class="card shadow-sm border-0 h-100">
					<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
						<div>
							<i class="fa-solid fa-truck-moving text-info mr-2"></i>
							<span>Mercancía en Tránsito (Órdenes Enviadas)</span>
						</div>
						<span class="badge badge-info">{{ count($inTransitOrders) }} activas</span>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead class="thead-light">
								<tr>
									<th>Orden</th>
									<th>Proveedor</th>
									<th>Sucursal</th>
									<th class="text-right">Monto</th>
									<th class="text-center">Llegada Estimada</th>
								</tr>
							</thead>
							<tbody>
								@forelse($inTransitOrders as $to)
								<tr>
									<td class="font-mono font-weight-bold text-dark">{{ $to->order_code }}</td>
									<td class="small">{{ $to->supplier_name }}</td>
									<td class="small">{{ $to->branch_name }}</td>
									<td class="text-right font-weight-bold">{{ $fmt($to->total_estimated_cost) }}</td>
									<td class="text-center">
										@if($to->is_overdue)
										<span class="badge badge-danger">Atrasado {{ abs($to->days_until_delivery) }}d</span>
										@else
										<span class="badge badge-success">En {{ $to->days_until_delivery }} días</span>
										@endif
									</td>
								</tr>
								@empty
								<tr><td colspan="5" class="text-center text-muted py-3">No hay órdenes en tránsito actualmente.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-lg-6 mb-3">
				<div class="card shadow-sm border-0 h-100">
					<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
						<div>
							<i class="fa-solid fa-triangle-exclamation text-danger mr-2"></i>
							<span>Pedidos Atrasados (Riesgo de Quiebre)</span>
						</div>
						<span class="badge @if(count($overdueOrdersList) > 0) badge-danger @else badge-success @endif">
							{{ count($overdueOrdersList) }} demoras
						</span>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead class="thead-light">
								<tr>
									<th>Orden</th>
									<th>Proveedor</th>
									<th class="text-right">Monto en Riesgo</th>
									<th class="text-center">Demora</th>
									<th class="text-center">Acción</th>
								</tr>
							</thead>
							<tbody>
								@forelse($overdueOrdersList as $oo)
								<tr>
									<td class="font-mono font-weight-bold text-danger">{{ $oo->order_code }}</td>
									<td class="small font-weight-600">{{ $oo->supplier_name }}</td>
									<td class="text-right font-weight-bold text-danger">{{ $fmt($oo->total_estimated_cost) }}</td>
									<td class="text-center font-weight-bold text-danger">{{ abs($oo->days_until_delivery) }} días vencida</td>
									<td class="text-center">
										<button type="button" class="btn btn-xs btn-outline-danger mrp-close-order" data-order-id="{{ $oo->id }}" data-cancel="0">
											Cerrar con Faltante
										</button>
									</td>
								</tr>
								@empty
								<tr><td colspan="5" class="text-center text-muted py-3">¡Excelente! No hay órdenes atrasadas con proveedores.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- SECCIÓN 2: MATRIZ DE PRIORIDADES DE REPOSICIÓN -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-layer-group text-primary mr-2"></i>
					<span>Prioridades de Reposición Inmediata</span>
				</div>
				<span class="badge badge-light border text-muted">Clasificación por Nivel de Riesgo Operativo</span>
			</div>
			<div class="card-body">
				<div class="row">
					<!-- Nivel 1: Emergencia -->
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 border-danger bg-light">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="badge badge-danger text-uppercase">1. Emergencia</span>
								<strong class="text-danger">{{ count($priorities['emergency']) }} SKUs</strong>
							</div>
							<h6 class="font-weight-bold text-dark">Quiebre Inminente</h6>
							<p class="small text-muted mb-2">Stock agotado o cobertura menor a 2 días con demanda activa.</p>
							<div class="small font-weight-bold text-dark mb-1">SKUs Críticos:</div>
							<ul class="list-unstyled small mb-0">
								@forelse(array_slice($priorities['emergency'], 0, 4) as $em)
								<li class="d-flex justify-content-between py-1 border-bottom">
									<span class="text-truncate" style="max-width: 170px;">{{ $em['product_name'] }}</span>
									<span class="font-weight-bold text-danger">{{ $em['suggested_order'] }} {{ $em['qu_name'] }}</span>
								</li>
								@empty
								<li class="text-muted">Sin emergencias de stock.</li>
								@endforelse
							</ul>
						</div>
					</div>

					<!-- Nivel 2: Crítico -->
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 border-warning bg-light">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="badge badge-warning text-dark text-uppercase">2. Crítico</span>
								<strong class="text-dark">{{ count($priorities['critical']) }} SKUs</strong>
							</div>
							<h6 class="font-weight-bold text-dark">Punto de Reorden (ROP)</h6>
							<p class="small text-muted mb-2">Cobertura menor o igual al Lead Time del proveedor asignado.</p>
							<div class="small font-weight-bold text-dark mb-1">SKUs a Ordenar:</div>
							<ul class="list-unstyled small mb-0">
								@forelse(array_slice($priorities['critical'], 0, 4) as $cr)
								<li class="d-flex justify-content-between py-1 border-bottom">
									<span class="text-truncate" style="max-width: 170px;">{{ $cr['product_name'] }}</span>
									<span class="font-weight-bold text-primary">{{ $cr['suggested_order'] }} {{ $cr['qu_name'] }}</span>
								</li>
								@empty
								<li class="text-muted">Sin productos en punto de reorden crítico.</li>
								@endforelse
							</ul>
						</div>
					</div>

					<!-- Nivel 3: Normal -->
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 border-success bg-light">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="badge badge-success text-uppercase">3. Normal</span>
								<strong class="text-success">{{ count($priorities['normal']) }} SKUs</strong>
							</div>
							<h6 class="font-weight-bold text-dark">Programación Regular</h6>
							<p class="small text-muted mb-2">Abastecimiento estándar programado dentro del horizonte de {{ $horizonDays }} días.</p>
							<div class="small font-weight-bold text-dark mb-1">SKUs en Calendario:</div>
							<ul class="list-unstyled small mb-0">
								@forelse(array_slice($priorities['normal'], 0, 4) as $nr)
								<li class="d-flex justify-content-between py-1 border-bottom">
									<span class="text-truncate" style="max-width: 170px;">{{ $nr['product_name'] }}</span>
									<span class="font-weight-bold text-muted">{{ $nr['suggested_order'] }} {{ $nr['qu_name'] }}</span>
								</li>
								@empty
								<li class="text-muted">Sin pedidos normales pendientes.</li>
								@endforelse
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- SECCIÓN 3: COBERTURA PROYECTADA POR CATEGORÍA -->
		<div class="card shadow-sm border-0 mb-3">
			<div class="card-header bg-white border-0 font-weight-bold d-flex justify-content-between align-items-center">
				<div>
					<i class="fa-solid fa-boxes-packing text-success mr-2"></i>
					<span>Cobertura Proyectada e Inversión de Reposición por Categoría</span>
				</div>
				<span class="badge badge-light border text-muted">Días de Autonomía Departamental</span>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead class="thead-light">
						<tr>
							<th>Categoría / Departamento</th>
							<th class="text-center">Productos</th>
							<th class="text-center">Quiebres Actuales</th>
							<th class="text-right">Cobertura Promedio</th>
							<th class="text-right">Inversión Requerida</th>
							<th>Semáforo de Abastecimiento</th>
						</tr>
					</thead>
					<tbody>
						@forelse($categoryCoverage as $cc)
						<tr>
							<td class="font-weight-bold text-dark">{{ $cc['name'] }}</td>
							<td class="text-center">{{ $cc['product_count'] }}</td>
							<td class="text-center @if($cc['stockouts'] > 0) text-danger font-weight-bold @else text-muted @endif">{{ $cc['stockouts'] }}</td>
							<td class="text-right font-weight-bold">{{ $cc['avg_coverage'] }} días</td>
							<td class="text-right font-weight-bold text-primary">{{ $fmt($cc['reorder_cost']) }}</td>
							<td>
								<div class="progress" style="height: 18px;">
									<div class="progress-bar @if($cc['avg_coverage'] < 5) bg-danger @elseif($cc['avg_coverage'] < 14) bg-warning @else bg-success @endif"
										role="progressbar"
										style="width: {{ min(100, $cc['avg_coverage'] * 3.5) }}%;">
										{{ $cc['avg_coverage'] }}d
									</div>
								</div>
							</td>
						</tr>
						@empty
						<tr><td colspan="6" class="text-center text-muted py-3">Sin desglose por categoría.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

	</div>

</div>

<!-- ============================================================== -->
<!-- MODALES DEL CICLO 2 (RECEPCIÓN, FACTURA, COTIZACIÓN, ACCIONES) -->
<!-- ============================================================== -->

<!-- MODAL: REGISTRAR FACTURA PROVEEDOR (CONCILIACIÓN 3 VÍAS) -->
<div class="modal fade" id="mrp-invoice-modal" tabindex="-1">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-file-invoice-dollar text-primary mr-2"></i>Factura del proveedor: <span id="mrp-invoice-code"></span></h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<div class="form-row mb-3">
					<div class="col-md-3"><label class="small text-muted font-weight-600 mb-0">Número de factura</label><input type="text" id="mrp-invoice-number" class="form-control form-control-sm" required></div>
					<div class="col-md-3"><label class="small text-muted font-weight-600 mb-0">Fecha</label><input type="date" id="mrp-invoice-date" class="form-control form-control-sm" required></div>
					<div class="col-md-2"><label class="small text-muted font-weight-600 mb-0">Impuestos / IVA</label><input type="number" step="any" min="0" id="mrp-invoice-tax" class="form-control form-control-sm" value="0"></div>
					<div class="col-md-4"><label class="small text-muted font-weight-600 mb-0">Notas</label><input type="text" id="mrp-invoice-notes" class="form-control form-control-sm"></div>
				</div>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Producto</th><th class="text-right">Pedido</th><th class="text-right">Recibido</th><th class="text-right">Precio OC</th><th style="width:130px">Cant. facturada</th><th style="width:140px">Precio unit. fact.</th></tr></thead>
						<tbody id="mrp-invoice-lines">
							<tr>
								<td><strong>Aceite Vegetal Premium 1L</strong><br><small class="text-muted">SKU-1002</small></td>
								<td class="text-right">100 unids</td>
								<td class="text-right font-weight-bold text-success">100 unids</td>
								<td class="text-right">$12.50</td>
								<td><input type="number" class="form-control form-control-sm text-right" value="100"></td>
								<td><input type="number" step="0.01" class="form-control form-control-sm text-right font-weight-bold" value="12.50"></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary" id="mrp-invoice-save"><i class="fa-solid fa-save mr-1"></i>Guardar y conciliar</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: RECIBIR MERCANCÍA CON CONTROL DE CALIDAD -->
<div class="modal fade" id="mrp-receive-modal" tabindex="-1">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<form id="mrp-receive-form">
				<div class="modal-header py-2">
					<h5 class="modal-title font-weight-700"><i class="fa-solid fa-truck-ramp-box text-success mr-2"></i>Recibir e inspeccionar orden <span id="mrp-receive-code"></span></h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body p-3">
					<div class="form-row mb-3">
						<div class="col-md-4">
							<label class="small text-muted font-weight-600 mb-0">Proveedor / Destino</label>
							<input type="text" id="mrp-receive-supplier" class="form-control form-control-sm bg-light" readonly>
						</div>
						<div class="col-md-3">
							<label class="small text-muted font-weight-600 mb-0">Documento del proveedor (remisión/factura)</label>
							<input type="text" id="mrp-receive-doc" class="form-control form-control-sm" placeholder="Ej: REM-1234">
						</div>
						<div class="col-md-2">
							<label class="small text-muted font-weight-600 mb-0">Temperatura de llegada (°C)</label>
							<input type="number" step="0.1" id="mrp-receive-temp" class="form-control form-control-sm" placeholder="Opcional">
						</div>
						<div class="col-md-3">
							<label class="small text-muted font-weight-600 mb-0">Notas de inspección</label>
							<input type="text" id="mrp-receive-notes" class="form-control form-control-sm" placeholder="Sello de transporte, estado del empaque…">
						</div>
					</div>
					<div class="alert alert-light border small text-muted mb-2">
						<i class="fa-solid fa-circle-info text-info mr-1"></i>Solo la cantidad <strong>Aceptada</strong> ingresará al stock y generará Kardex. Lo <strong>Rechazado</strong> abrirá automáticamente un registro de incidencia con el proveedor.
					</div>
					<div class="table-responsive">
						<table class="table table-sm mb-0">
							<thead>
								<tr>
									<th>Producto</th>
									<th class="text-right">Pendiente</th>
									<th style="width:110px">Entregado</th>
									<th style="width:110px">Rechazado</th>
									<th>Motivo rechazo</th>
									<th style="width:135px">Vencimiento</th>
									<th style="width:130px">Lote proveedor</th>
									<th style="width:150px">Ubicación física</th>
									<th style="width:100px">Precio unit.</th>
								</tr>
							</thead>
							<tbody id="mrp-receive-lines">
								<tr>
									<td><strong>Leche Entera Alquería 1L</strong><br><small class="text-muted">SKU-1004</small></td>
									<td class="text-right font-weight-bold">50 unids</td>
									<td><input type="number" class="form-control form-control-sm text-right font-weight-bold" value="50"></td>
									<td><input type="number" class="form-control form-control-sm text-right" value="0"></td>
									<td><input type="text" class="form-control form-control-sm" placeholder="Sin novedad"></td>
									<td><input type="date" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+30 days')) }}"></td>
									<td><input type="text" class="form-control form-control-sm" value="LOT-ALQ-099"></td>
									<td><input type="text" class="form-control form-control-sm" value="Cámara Fría 01"></td>
									<td class="text-right font-weight-bold">$1.85</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-check mr-1"></i>Contabilizar recepción</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: REGLAS DE RECEPCIÓN E INOCUIDAD -->
<div class="modal fade" id="mrp-rules-modal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-clipboard-check text-primary mr-2"></i>Reglas de recepción de mercancía</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<p class="small text-muted">Criterios de inocuidad exigidos en la inspección de llegada: vida útil remanente mínima y rango de temperatura admisible.</p>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Producto</th><th class="text-right">Vida útil base</th><th style="width:110px">Mín. remanente</th><th style="width:90px">T. mín (°C)</th><th style="width:90px">T. máx (°C)</th><th></th></tr></thead>
						<tbody id="mrp-rules-lines">
							<tr>
								<td><strong>Lácteos y Derivados (Yogurt, Leche, Quesos)</strong></td>
								<td class="text-right">30 días</td>
								<td><span class="badge badge-warning text-dark font-weight-bold">&ge; 75% vida útil</span></td>
								<td class="text-center font-weight-bold text-info">2.0 °C</td>
								<td class="text-center font-weight-bold text-info">6.0 °C</td>
								<td class="text-right"><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Regla Activa</span></td>
							</tr>
							<tr>
								<td><strong>Carnes y Aves Refrigeradas</strong></td>
								<td class="text-right">15 días</td>
								<td><span class="badge badge-warning text-dark font-weight-bold">&ge; 80% vida útil</span></td>
								<td class="text-center font-weight-bold text-info">0.0 °C</td>
								<td class="text-center font-weight-bold text-info">4.0 °C</td>
								<td class="text-right"><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Regla Activa</span></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: SOLICITAR COTIZACIÓN FORMAL (RFQ) -->
<div class="modal fade" id="modalSolicitarCotizacion" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-file-pen mr-2"></i>Solicitar Cotización Formal (RFQ)
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formSolicitarCotizacion">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Proveedor a Cotizar</label>
						<select name="supplier_id" id="rfqSupplierSelect" class="form-control" required>
							@foreach($suppliers as $sp)
							<option value="{{ $sp['id'] }}">{{ $sp['name'] }} (Lead Time: {{ $sp['lead_time_days'] ?? 2 }}d)</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Sucursal Solicitante</label>
						<select name="branch_id" class="form-control">
							@foreach($branches as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Título del Requerimiento</label>
						<input type="text" name="title" id="rfqTitleInput" class="form-control" value="Cotización de Abastecimiento Retail" required>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Fecha Límite de Recepción de Propuesta</label>
						<input type="date" name="deadline_date" class="form-control" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Notas o Especificaciones para el Proveedor</label>
						<textarea name="notes" id="rfqNotesInput" class="form-control" rows="3" placeholder="Indicar condiciones comerciales, empaque, volumen requerido..."></textarea>
					</div>
					<div class="alert alert-light border small text-muted mb-0">
						<i class="fa-solid fa-paper-plane text-primary mr-1"></i>Se registrará un folio oficial RFQ y se habilitarán los enlaces directos para envío formal por Email y WhatsApp.
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary btn-sm font-weight-bold">
						<i class="fa-solid fa-paper-plane mr-1"></i>Generar y Enviar Solicitud
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: RESOLVER INCUMPLIMIENTO CON PROVEEDOR -->
<div class="modal fade" id="modalResolverIncumplimiento" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-handshake-angle mr-2"></i>Resolver Incumplimiento con Proveedor
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formResolverIncumplimiento">
				<input type="hidden" name="incident_id" id="resolveIncidentId">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold small">Proveedor Afectado</label>
						<input type="text" id="resolveSupplierName" class="form-control bg-light" readonly>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Descripción del Incumplimiento</label>
						<p class="small text-muted p-2 bg-light rounded border mb-0" id="resolveIncidentDesc">-</p>
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Impacto Económico / Penalización Acordada ($)</label>
						<input type="number" step="0.01" min="0" name="cost_impact" id="resolveCostImpact" class="form-control" value="0.00">
					</div>
					<div class="form-group">
						<label class="font-weight-bold small">Notas de Resolución y Conciliación</label>
						<textarea name="resolution_notes" class="form-control" rows="3" placeholder="Acuerdo alcanzado: reposición física, nota de crédito aplicada, compensación..." required></textarea>
					</div>
				</div>
				<div class="modal-footer py-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-success btn-sm font-weight-bold">
						<i class="fa-solid fa-check-circle mr-1"></i>Marcar Incidencia como Resuelta
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: APROBACIÓN MASIVA DE ÓRDENES EN BORRADOR -->
<div class="modal fade" id="modalBulkApprove" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-check-double mr-2"></i>Aprobar Todas las Órdenes en Borrador
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<p class="mb-2">¿Confirmas la aprobación gerencial de las <strong>{{ $draftOrdersCount }}</strong> órdenes de compra en borrador?</p>
				<div class="alert alert-light border small text-muted mb-0">
					<i class="fa-solid fa-circle-info text-info mr-1"></i>Una vez aprobadas, las órdenes quedarán listas para su envío formal a los proveedores y computarán como entradas planificadas en el cálculo MRP.
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" id="btnConfirmBulkApprove" class="btn btn-success btn-sm font-weight-bold">
					<i class="fa-solid fa-check mr-1"></i>Confirmar Aprobación Masiva
				</button>
			</div>
		</div>
	</div>
</div>

@stop

@push('pageScripts')
<script>
	function mrpPost(path, body, okMsg) {
		Grocy.FrontendHelpers.BeginUiBusy();
		$.ajax({
			url: Grocy.BaseUrl + '/api' + path,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(body || {})
		}).done(function(res) {
			toastr.success((res && res.message) || okMsg);
			setTimeout(function() { location.reload(); }, 700);
		}).fail(function(xhr) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || xhr.statusText);
		});
	}

	function mrpEsc(v) {
		return $('<div>').text(v === null || v === undefined ? '' : v).html();
	}

	function mrpFail(xhr) {
		Grocy.FrontendHelpers.EndUiBusy();
		toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || xhr.statusText);
	}

	// 1. Buscador y Filtro Reactivo de la Tabla MRP
	var searchMrpInput = document.getElementById('searchMrpInput');
	var mrpFilterBtns = document.querySelectorAll('.btn-mrp-filter');
	var currentMrpFilter = 'all';

	function filterMrpTable() {
		var q = (searchMrpInput ? searchMrpInput.value : '').toLowerCase().trim();
		var rows = document.querySelectorAll('#mrp-table tbody tr.mrp-row');

		rows.forEach(function(row) {
			var name = row.getAttribute('data-name') || '';
			var status = row.getAttribute('data-status') || '';

			var matchesQuery = !q || name.indexOf(q) >= 0;
			var matchesFilter = true;

			if (currentMrpFilter === 'reorder') {
				matchesFilter = (status === 'reorder_needed');
			} else if (currentMrpFilter === 'overstock') {
				matchesFilter = (status === 'overstock_risk');
			}

			row.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
		});
	}

	if (searchMrpInput) {
		searchMrpInput.addEventListener('input', filterMrpTable);
	}

	mrpFilterBtns.forEach(function(btn) {
		btn.addEventListener('click', function() {
			mrpFilterBtns.forEach(function(b) { b.classList.remove('active'); });
			this.classList.add('active');
			currentMrpFilter = this.getAttribute('data-filter') || 'all';
			filterMrpTable();
		});
	});

	// 2. Generación y Aprobación de Órdenes
	$('#mrp-generate-orders').on('click', function() {
		bootbox.confirm('¿Generar órdenes borrador (una por proveedor) con las cantidades sugeridas por el motor MRP?', function(ok) {
			if (ok) mrpPost('/mrp/orders/generate', { branch_id: @json($selectedBranchId) }, 'Órdenes generadas');
		});
	});

	$('.mrp-approve-order').on('click', function() {
		var id = $(this).data('order-id');
		bootbox.confirm('¿Aprobar la orden de reabastecimiento?', function(ok) {
			if (ok) mrpPost('/mrp/order/' + id + '/approve', {}, 'Orden aprobada');
		});
	});

	$('#btnConfirmBulkApprove').on('click', function() {
		$('#modalBulkApprove').modal('hide');
		mrpPost('/mrp/orders/bulk-approve', { branch_id: @json($selectedBranchId) }, 'Órdenes aprobadas masivamente');
	});

	$('.mrp-send-order').on('click', function() {
		var id = $(this).data('order-id');
		bootbox.prompt({
			title: 'Fecha de entrega comprometida por el proveedor',
			inputType: 'date',
			value: moment().add(2, 'days').format('YYYY-MM-DD'),
			callback: function(date) {
				if (date) mrpPost('/mrp/order/' + id + '/send', { expected_delivery_date: date }, 'Orden enviada');
			}
		});
	});

	$('.mrp-close-order').on('click', function() {
		var id = $(this).data('order-id');
		var cancel = $(this).data('cancel') == 1;
		bootbox.prompt({
			title: cancel ? 'Motivo de la cancelación' : 'Cerrar con faltante (lo pendiente deja de esperarse). Motivo:',
			callback: function(reason) {
				if (reason === null) return;
				if (!reason.trim()) { toastr.warning('Indique el motivo'); return false; }
				mrpPost('/mrp/order/' + id + '/close', { reason: reason }, cancel ? 'Orden cancelada' : 'Orden cerrada');
			}
		});
	});

	// 3. Acciones de Cotización (RFQ)
	$('.btn-quick-rfq').on('click', function() {
		var pId = $(this).data('product-id');
		var pName = $(this).data('product-name');
		var sId = $(this).data('supplier-id');
		var qty = $(this).data('qty');

		$('#rfqSupplierSelect').val(sId);
		$('#rfqTitleInput').val('Cotización de Reposición: ' + pName);
		$('#rfqNotesInput').val('Se requiere cotización formal para ' + qty + ' unidades del producto ' + pName + '.');
		$('#modalSolicitarCotizacion').modal('show');
	});

	$('.btn-rfq-supplier').on('click', function() {
		var sId = $(this).data('supplier-id');
		var sName = $(this).data('supplier-name');

		$('#rfqSupplierSelect').val(sId);
		$('#rfqTitleInput').val('Cotización de Catálogo General - ' + sName);
		$('#modalSolicitarCotizacion').modal('show');
	});

	$('#formSolicitarCotizacion').on('submit', function(e) {
		e.preventDefault();
		var supplierId = $('#rfqSupplierSelect').val();
		var branchId = $('[name="branch_id"]', this).val();
		var title = $('#rfqTitleInput').val();
		var deadline = $('[name="deadline_date"]', this).val();
		var notes = $('#rfqNotesInput').val();

		mrpPost('/mrp/quotations/request', {
			supplier_id: supplierId,
			branch_id: branchId,
			title: title,
			deadline_date: deadline,
			notes: notes
		}, 'Solicitud de cotización registrada');
	});

	// 4. Resolución de Incumplimientos
	$('.btn-resolve-incident').on('click', function() {
		var id = $(this).data('incident-id');
		var sName = $(this).data('supplier-name');
		var desc = $(this).data('desc');

		$('#resolveIncidentId').val(id);
		$('#resolveSupplierName').val(sName);
		$('#resolveIncidentDesc').text(desc);
		$('#modalResolverIncumplimiento').modal('show');
	});

	$('#formResolverIncumplimiento').on('submit', function(e) {
		e.preventDefault();
		var id = $('#resolveIncidentId').val();
		var notes = $('[name="resolution_notes"]', this).val();
		var cost = $('#resolveCostImpact').val();

		mrpPost('/procurement/incidents/' + id + '/resolve', {
			resolution_notes: notes,
			cost_impact: cost,
			status: 'resolved'
		}, 'Incidencia resuelta');
	});

	// 5. Facturas de Proveedor (Conciliación de 3 Vías)
	var mrpInvoiceOrderId = null;
	$('.mrp-invoice-order').on('click', function() {
		mrpInvoiceOrderId = $(this).data('order-id');
		$.getJSON(Grocy.BaseUrl + '/api/mrp/order/' + mrpInvoiceOrderId).done(function(d) {
			$('#mrp-invoice-code').text(d.order.order_code + ' · ' + d.order.supplier_name);
			$('#mrp-invoice-number, #mrp-invoice-notes').val('');
			$('#mrp-invoice-tax').val(0);
			$('#mrp-invoice-date').val(moment().format('YYYY-MM-DD'));
			$('#mrp-invoice-lines').html(d.items.map(function(i) {
				var ordered = i.approved_amount !== null ? i.approved_amount : i.suggested_amount;
				return '<tr data-item="' + i.id + '"><td>' + mrpEsc(i.product_name) + '</td><td class="text-right">' + mrpEsc(ordered) + '</td><td class="text-right">' + mrpEsc(i.received_amount) + '</td>'
					+ '<td class="text-right">' + (i.unit_price !== null ? '$' + Number(i.unit_price).toFixed(2) : '—') + '</td>'
					+ '<td><input type="number" min="0" step="any" class="form-control form-control-sm mrp-inv-qty" value="' + mrpEsc(i.received_amount) + '"></td>'
					+ '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm mrp-inv-price" value="' + mrpEsc(i.unit_price !== null ? i.unit_price : 0) + '"></td></tr>';
			}).join(''));
			$('#mrp-invoice-modal').modal('show');
		}).fail(mrpFail);
	});

	$('#mrp-invoice-save').on('click', function() {
		var items = $('#mrp-invoice-lines tr').map(function() {
			return { order_item_id: $(this).data('item'), invoiced_amount: $(this).find('.mrp-inv-qty').val(), unit_price: $(this).find('.mrp-inv-price').val() };
		}).get();
		$.ajax({
			url: Grocy.BaseUrl + '/api/mrp/order/' + mrpInvoiceOrderId + '/invoice',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				invoice_number: $('#mrp-invoice-number').val(),
				invoice_date: $('#mrp-invoice-date').val(),
				tax_amount: $('#mrp-invoice-tax').val(),
				notes: $('#mrp-invoice-notes').val(),
				items: items
			})
		}).done(function(r) {
			if (r.status === 'matched') {
				toastr.success(r.invoice_code + ' conciliada sin diferencias');
			} else {
				toastr.warning(r.invoice_code + ' con diferencias: $' + Number(r.amount_at_risk).toFixed(2) + ' en riesgo. Requiere aprobación gerencial.');
			}
			setTimeout(function() { location.reload(); }, 900);
		}).fail(mrpFail);
	});

	var issueLabel = { qty_over_received: 'Factura más de lo recibido', qty_over_ordered: 'Factura más de lo pedido', price_over_po: 'Precio sobre la OC' };
	$('.mrp-invoice-detail').on('click', function() {
		var id = $(this).data('invoice-id'), code = $(this).data('code'), pending = $(this).data('pending') == 1;
		$.getJSON(Grocy.BaseUrl + '/api/mrp/invoices/' + id + '/items').done(function(rows) {
			var html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Producto</th><th class="text-right">Pedido</th><th class="text-right">Recibido</th><th class="text-right">Facturado</th><th class="text-right">Precio OC</th><th class="text-right">Precio fact.</th><th>Diferencia</th></tr></thead><tbody>'
				+ rows.map(function(r) {
					var iss = (r.issues || '').split(',').filter(Boolean).map(function(c) { return '<span class="badge badge-danger mr-1">' + issueLabel[c] + '</span>'; }).join('');
					return '<tr><td>' + mrpEsc(r.product_name) + '</td><td class="text-right">' + r.ordered_amount + '</td><td class="text-right">' + r.received_amount + '</td><td class="text-right">' + (Number(r.previously_invoiced) + Number(r.invoiced_amount)) + '</td>'
						+ '<td class="text-right">' + (r.order_unit_price !== null ? '$' + Number(r.order_unit_price).toFixed(2) : '—') + '</td><td class="text-right">$' + Number(r.invoice_unit_price).toFixed(2) + '</td>'
						+ '<td>' + (iss || '<span class="text-success">OK</span>') + (Number(r.amount_at_risk) > 0 ? ' <strong class="text-danger">$' + Number(r.amount_at_risk).toFixed(2) + '</strong>' : '') + '</td></tr>';
				}).join('') + '</tbody></table></div>';
			var buttons = { close: { label: 'Cerrar', className: 'btn-light border' } };
			if (pending) {
				var decide = function(approve) {
					bootbox.prompt({
						title: approve ? 'Aprobar pagando la diferencia. Motivo:' : 'Rechazar la factura (reclamar al proveedor). Motivo:',
						callback: function(reason) {
							if (reason === null) return;
							if (!reason.trim()) { toastr.warning('Indique el motivo'); return false; }
							mrpPost('/mrp/invoices/' + id + '/decide', { approve: approve, reason: reason }, approve ? 'Factura aprobada con diferencia' : 'Factura rechazada');
						}
					});
				};
				buttons.reject = { label: 'Rechazar', className: 'btn-outline-danger', callback: function() { decide(false); } };
				buttons.approve = { label: 'Aprobar con diferencia', className: 'btn-warning', callback: function() { decide(true); } };
			}
			bootbox.dialog({ title: 'Conciliación ' + mrpEsc(code), message: html, size: 'extra-large', buttons: buttons });
		}).fail(mrpFail);
	});

	// 6. Recepción de Mercancía con Control de Calidad
	var mrpReceiveOrderId = null;
	$('.mrp-receive-order').on('click', function() {
		mrpReceiveOrderId = $(this).data('order-id');
		$.getJSON(Grocy.BaseUrl + '/api/mrp/order/' + mrpReceiveOrderId).done(function(d) {
			$('#mrp-receive-code').text(d.order.order_code);
			$('#mrp-receive-supplier').val(d.order.supplier_name + ' → ' + d.order.branch_name);
			$('#mrp-receive-doc, #mrp-receive-temp, #mrp-receive-notes').val('');
			var locOptions = function(selected) {
				return d.locations.map(function(l) {
					return '<option value="' + l.id + '"' + (l.id == selected ? ' selected' : '') + '>' + mrpEsc(l.name) + '</option>';
				}).join('');
			};
			var rows = d.items.filter(function(i) { return i.pending_amount > 0; }).map(function(i) {
				var rule = [];
				if (i.min_remaining_days) rule.push('mín. ' + i.min_remaining_days + ' d de vida útil');
				if (i.min_temperature !== null || i.max_temperature !== null) rule.push((i.min_temperature ?? '—') + ' a ' + (i.max_temperature ?? '—') + ' °C');
				var bbd = i.default_best_before_days > 0 ? moment().add(i.default_best_before_days, 'days').format('YYYY-MM-DD') : '';
				return '<tr data-item-id="' + i.id + '">' +
					'<td><span class="font-weight-600">' + mrpEsc(i.product_name) + '</span>' + (rule.length ? '<div class="small text-muted">' + mrpEsc(rule.join(' · ')) + '</div>' : '') + '</td>' +
					'<td class="text-right text-nowrap">' + i.pending_amount + ' ' + mrpEsc(i.qu_name) + '</td>' +
					'<td><input type="number" step="any" min="0" max="' + i.pending_amount + '" class="form-control form-control-sm rc-delivered" value="' + i.pending_amount + '"></td>' +
					'<td><input type="number" step="any" min="0" class="form-control form-control-sm rc-rejected" value="0"></td>' +
					'<td><input type="text" class="form-control form-control-sm rc-reason" placeholder="Dañado, faltante…"></td>' +
					'<td><input type="date" class="form-control form-control-sm rc-bbd" value="' + bbd + '"></td>' +
					'<td><input type="text" maxlength="40" class="form-control form-control-sm rc-lot" placeholder="Lote" aria-label="Lote del proveedor"></td>' +
					'<td><select class="custom-select custom-select-sm rc-location">' + locOptions(i.product_location_id) + '</select></td>' +
					'<td><input type="number" step="any" min="0" class="form-control form-control-sm rc-price" value="' + i.unit_price + '"></td>' +
					'</tr>';
			});
			$('#mrp-receive-lines').html(rows.join('') || '<tr><td colspan="8" class="text-center text-muted">Nada pendiente de recibir.</td></tr>');
			$('#mrp-receive-modal').modal('show');
		}).fail(mrpFail);
	});

	$('#mrp-receive-form').on('submit', function(e) {
		e.preventDefault();
		var items = $('#mrp-receive-lines tr[data-item-id]').map(function() {
			var r = $(this);
			return {
				order_item_id: r.data('item-id'),
				delivered_amount: r.find('.rc-delivered').val(),
				rejected_amount: r.find('.rc-rejected').val(),
				reject_reason: r.find('.rc-reason').val(),
				best_before_date: r.find('.rc-bbd').val(),
				lot_number: r.find('.rc-lot').val(),
				location_id: r.find('.rc-location').val(),
				unit_price: r.find('.rc-price').val()
			};
		}).get();
		mrpPost('/mrp/order/' + mrpReceiveOrderId + '/receive', {
			items: items,
			supplier_document: $('#mrp-receive-doc').val(),
			temperature_on_arrival: $('#mrp-receive-temp').val(),
			notes: $('#mrp-receive-notes').val()
		}, 'Recepción registrada y stock actualizado');
	});

	// 7. Reglas de Recepción
	$('#mrp-receiving-rules').on('click', function() {
		$.getJSON(Grocy.BaseUrl + '/api/mrp/receiving-rules').done(function(rules) {
			var v = function(x) { return x === null || x === undefined ? '' : x; };
			$('#mrp-rules-lines').html(rules.map(function(r) {
				return '<tr data-product-id="' + r.product_id + '">' +
					'<td>' + mrpEsc(r.product_name) + '</td>' +
					'<td class="text-right">' + (r.default_best_before_days > 0 ? r.default_best_before_days + ' d' : '—') + '</td>' +
					'<td><input type="number" min="0" class="form-control form-control-sm rr-days" value="' + v(r.min_remaining_shelf_life_days) + '"></td>' +
					'<td><input type="number" step="0.1" class="form-control form-control-sm rr-tmin" value="' + v(r.min_temperature) + '"></td>' +
					'<td><input type="number" step="0.1" class="form-control form-control-sm rr-tmax" value="' + v(r.max_temperature) + '"></td>' +
					'<td><button type="button" class="btn btn-xs btn-outline-primary rr-save">Guardar</button></td>' +
					'</tr>';
			}).join(''));
			$('#mrp-rules-modal').modal('show');
		}).fail(mrpFail);
	});

	$('#mrp-rules-lines').on('click', '.rr-save', function() {
		var r = $(this).closest('tr');
		$.ajax({
			url: Grocy.BaseUrl + '/api/mrp/receiving-rules/' + r.data('product-id'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				min_remaining_shelf_life_days: r.find('.rr-days').val(),
				min_temperature: r.find('.rr-tmin').val(),
				max_temperature: r.find('.rr-tmax').val()
			})
		}).done(function(res) {
			toastr.success((res && res.message) || 'Regla guardada');
		}).fail(mrpFail);
	});
</script>
@endpush
