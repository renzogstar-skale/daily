@extends('layout.default')

@section('title', 'Almacén y Bodega (WMS) — Gestión Integral de Almacenamiento')

@push('pageStyles')
<link href="{{ $U('/css/daily_warehouse.css?v=', true) }}{{ daily_asset_version('/css/daily_warehouse.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="wms-container">

	<!-- 1. HEADER & SUCURSAL -->
	<div class="wms-header-box">
		<div class="wms-title-wrap">
			<h1>
				<i class="fa-solid fa-warehouse text-info"></i>
				Almacén y Bodega &bull; Centro de Gestión WMS
			</h1>
			<p>
				Programación de muelles, recepción con escáner, control térmico, putaway en racks, picking guiado y logística inversa.
			</p>
		</div>

		<div class="d-flex align-items-center flex-wrap gap-2">
			<span class="wms-badge-live">
				<span class="wms-badge-dot"></span> Motor WMS Activo
			</span>

			<form method="GET" action="{{ $U('/warehouse') }}" class="d-inline-flex align-items-center gap-1">
				<input type="hidden" name="tab" value="{{ $activeTab }}">
				<select name="branch_id" class="form-control form-control-sm font-weight-bold" onchange="this.form.submit()" style="max-width: 200px;">
					@foreach($branches as $b)
						@php $bid = is_object($b) ? $b->id : $b['id']; $bname = is_object($b) ? $b->name : $b['name']; @endphp
						<option value="{{ $bid }}" @if($bid == $selectedBranchId) selected @endif>
							{{ $bname }}
						</option>
					@endforeach
				</select>
			</form>

			<a href="{{ $U('/locations/map') }}" class="btn btn-sm btn-outline-info font-weight-bold" title="Ver plano físico de ubicaciones">
				<i class="fa-solid fa-map-location-dot mr-1"></i> Mapa 2D Bodega
			</a>
			<a href="{{ $U('/scan') }}" class="btn btn-sm btn-outline-primary font-weight-bold" title="Operar con lector de código de barras">
				<i class="fa-solid fa-barcode mr-1"></i> Modo Escáner
			</a>
		</div>
	</div>

	<!-- 2. CICLO LOGÍSTICO WMS (10 ETAPAS INTERACTIVAS) -->
	<div class="wms-cycle-card">
		<div class="wms-cycle-title">
			<span><i class="fa-solid fa-route text-info mr-1"></i> Flujo Operativo de Almacenamiento (WMS Retail)</span>
			<span class="text-muted small">10 Procesos Enlazados con Inventario y Kardex</span>
		</div>
		<div class="wms-cycle-stepper">
			@foreach($dashboard['cycle_steps'] as $cs)
				<div class="wms-cycle-step @if($loop->first) active @endif" onclick="document.querySelector('[data-tab=\'{{ $loop->index == 0 ? 'resumen' : ($loop->index == 1 ? 'programacion' : ($loop->index == 2 ? 'recepcion' : ($loop->index == 4 ? 'putaway' : ($loop->index == 6 ? 'movimientos' : ($loop->index == 7 ? 'picking' : ($loop->index == 9 ? 'existencias' : 'resumen')))))) }}\'])?.click()">
					<div class="wms-step-num">{{ $cs['num'] }}</div>
					<div class="wms-step-name">{{ $cs['name'] }}</div>
					<div class="wms-step-desc">{{ $cs['desc'] }}</div>
				</div>
			@endforeach
		</div>
	</div>

	<!-- 3. INDICADORES PRINCIPALES (8 KPIS WMS) -->
	<div class="wms-kpi-grid">
		<!-- KPI 1: Exactitud de Inventario (IRA) -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['ira']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-green"><i class="fa-solid fa-bullseye"></i></div>
			</div>
			<div class="wms-kpi-value text-success">{{ $dashboard['kpis']['ira']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['ira']['subtext'] }}</span>
				<span class="wms-kpi-trend"><i class="fa-solid fa-circle-check"></i> {{ $dashboard['kpis']['ira']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 2: Productividad Recepción -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['reception_prod']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-sky"><i class="fa-solid fa-truck-ramp-box"></i></div>
			</div>
			<div class="wms-kpi-value text-info">{{ $dashboard['kpis']['reception_prod']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['reception_prod']['subtext'] }}</span>
				<span class="wms-kpi-trend"><i class="fa-solid fa-arrow-up"></i> {{ $dashboard['kpis']['reception_prod']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 3: Tiempo de Putaway -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['putaway_time']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-purple"><i class="fa-solid fa-stopwatch"></i></div>
			</div>
			<div class="wms-kpi-value">{{ $dashboard['kpis']['putaway_time']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['putaway_time']['subtext'] }}</span>
				<span class="text-success font-weight-bold"><i class="fa-solid fa-bolt"></i> {{ $dashboard['kpis']['putaway_time']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 4: Ocupación de Bodega -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['occupancy']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-amber"><i class="fa-solid fa-boxes-stacked"></i></div>
			</div>
			<div class="wms-kpi-value text-primary">{{ $dashboard['kpis']['occupancy']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['occupancy']['subtext'] }}</span>
				<span class="badge badge-light font-weight-bold">{{ $dashboard['kpis']['occupancy']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 5: Productividad de Picking -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['picking_prod']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-sky"><i class="fa-solid fa-cart-flatbed"></i></div>
			</div>
			<div class="wms-kpi-value text-dark">{{ $dashboard['kpis']['picking_prod']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['picking_prod']['subtext'] }}</span>
				<span class="wms-kpi-trend"><i class="fa-solid fa-arrow-up"></i> {{ $dashboard['kpis']['picking_prod']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 6: Pedidos Preparados a Tiempo -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['otif']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-green"><i class="fa-solid fa-circle-check"></i></div>
			</div>
			<div class="wms-kpi-value text-success">{{ $dashboard['kpis']['otif']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['otif']['subtext'] }}</span>
				<span class="wms-kpi-trend"><i class="fa-solid fa-shield-check"></i> {{ $dashboard['kpis']['otif']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 7: Diferencias de Inventario -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['variance']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-amber"><i class="fa-solid fa-scale-unbalanced"></i></div>
			</div>
			<div class="wms-kpi-value text-muted">{{ $dashboard['kpis']['variance']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['variance']['subtext'] }}</span>
				<span class="text-success font-weight-bold">{{ $dashboard['kpis']['variance']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 8: Daños y Cuarentenas -->
		<div class="wms-kpi-card">
			<div class="wms-kpi-head">
				<span class="wms-kpi-label">{{ $dashboard['kpis']['damages']['label'] }}</span>
				<div class="wms-kpi-icon wms-kpi-icon-rose"><i class="fa-solid fa-triangle-exclamation"></i></div>
			</div>
			<div class="wms-kpi-value text-danger">{{ $dashboard['kpis']['damages']['value'] }}</div>
			<div class="wms-kpi-footer">
				<span>{{ $dashboard['kpis']['damages']['subtext'] }}</span>
				<span class="text-danger font-weight-bold">{{ $dashboard['kpis']['damages']['trend'] }}</span>
			</div>
		</div>
	</div>

	<!-- 4. PESTAÑAS DE NAVEGACIÓN WMS -->
	<div class="wms-nav-tabs">
		<button class="wms-tab-btn @if($activeTab == 'resumen') active @endif" data-tab="resumen">
			<i class="fa-solid fa-chart-pie"></i> Resumen & Muelles
		</button>
		<button class="wms-tab-btn @if($activeTab == 'programacion') active @endif" data-tab="programacion">
			<i class="fa-solid fa-calendar-check"></i> Programación Recepción <span class="wms-tab-badge">{{ count($reception['appointments']) }}</span>
		</button>
		<button class="wms-tab-btn @if($activeTab == 'recepcion') active @endif" data-tab="recepcion">
			<i class="fa-solid fa-truck-ramp-box"></i> Inspección & Temperatura <span class="wms-tab-badge">{{ count($inspections) }}</span>
		</button>
		<button class="wms-tab-btn @if($activeTab == 'putaway') active @endif" data-tab="putaway">
			<i class="fa-solid fa-dolly"></i> Ubicación & Putaway <span class="wms-tab-badge">{{ count($putaway['putaway_tasks']) }}</span>
		</button>
		<button class="wms-tab-btn @if($activeTab == 'movimientos') active @endif" data-tab="movimientos">
			<i class="fa-solid fa-right-left"></i> Movimientos Internos
		</button>
		<button class="wms-tab-btn @if($activeTab == 'picking') active @endif" data-tab="picking">
			<i class="fa-solid fa-cart-flatbed"></i> Preparación de Pedidos <span class="wms-tab-badge">{{ count($pickingOrders) }}</span>
		</button>
		<button class="wms-tab-btn @if($activeTab == 'existencias') active @endif" data-tab="existencias">
			<i class="fa-solid fa-boxes-stacked"></i> Control de Existencias & FEFO
		</button>
		<button class="wms-tab-btn @if($activeTab == 'devoluciones') active @endif" data-tab="devoluciones">
			<i class="fa-solid fa-arrow-rotate-left"></i> Devoluciones (RTV) <span class="wms-tab-badge">{{ count($reverseLogistics) }}</span>
		</button>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 1: RESUMEN & MUELLES              -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'resumen') active @endif" id="tab-resumen">
		<div class="wms-card mb-3">
			<div class="wms-card-header">
				<h2 class="wms-card-title">
					<i class="fa-solid fa-truck-front text-info"></i>
					Estado en Vivo de Muelles de Carga y Descarga
				</h2>
				<span class="text-muted small">Bahías activas en sucursal</span>
			</div>

			<div class="wms-dock-grid">
				@foreach($dashboard['docks'] as $dock)
					<div class="wms-dock-card status-{{ $dock['status'] }}">
						<div class="wms-dock-number">
							<span>{{ $dock['dock_number'] }}</span>
							<span class="badge @if($dock['status'] === 'disponible') badge-success @elseif($dock['status'] === 'en_descarga') badge-primary @elseif($dock['status'] === 'ocupado') badge-warning @else badge-secondary @endif text-uppercase" style="font-size: 0.68rem;">
								{{ str_replace('_', ' ', $dock['status']) }}
							</span>
						</div>
						<div class="font-weight-bold text-dark small mb-1">{{ $dock['name'] }}</div>
						<div class="text-muted small mb-2">
							Tipo: <span class="badge badge-light text-capitalize">{{ $dock['dock_type'] }}</span>
						</div>

						@if(!empty($dock['current_carrier']))
							<div class="p-2 bg-light border rounded small mb-2">
								<strong class="d-block text-truncate">{{ $dock['current_carrier'] }}</strong>
								<span class="text-muted font-weight-bold">{{ $dock['current_po_code'] }}</span>
							</div>
						@else
							<div class="p-2 bg-light border rounded small text-muted text-center mb-2">
								Bahía libre para asignación
							</div>
						@endif

						<div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
							<span style="font-size: 0.68rem;" class="text-muted">Cambiar estado:</span>
							<select class="form-control form-control-sm py-0 px-1" style="height: 22px; font-size: 0.7rem; max-width: 110px;" onchange="changeDockStatus({{ $dock['id'] }}, this.value)">
								<option value="disponible" @if($dock['status'] === 'disponible') selected @endif>Disponible</option>
								<option value="ocupado" @if($dock['status'] === 'ocupado') selected @endif>Ocupado</option>
								<option value="en_descarga" @if($dock['status'] === 'en_descarga') selected @endif>En Descarga</option>
								<option value="en_limpieza" @if($dock['status'] === 'en_limpieza') selected @endif>Limpieza</option>
							</select>
						</div>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Resumen de Alertas y Tareas Críticas -->
		<div class="row">
			<div class="col-lg-6 mb-3">
				<div class="wms-card h-100">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-dolly text-primary"></i>
							Tareas de Putaway Urgentes (Muelle $\rightarrow$ Racks)
						</h2>
						<button class="btn btn-xs btn-outline-primary" onclick="document.querySelector('[data-tab=\'putaway\']').click()">Ver todas</button>
					</div>

					<div class="d-flex flex-column gap-2">
						@forelse(array_slice($putaway['putaway_tasks'], 0, 3) as $pt)
							<div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
								<div>
									<strong class="d-block small text-dark">{{ $pt['product_name'] }} ({{ $pt['amount'] }} uds)</strong>
									<span class="text-muted small">Destino: <strong>{{ $pt['suggested_rack'] }}</strong> &bull; Lote: {{ $pt['lot_number'] }}</span>
								</div>
								@if($pt['status'] === 'pendiente')
									<button class="btn btn-xs btn-success font-weight-bold" onclick="confirmPutawayTask({{ $pt['id'] }}, {{ $pt['to_location_id'] }})">
										<i class="fa-solid fa-check mr-1"></i> Ubicar
									</button>
								@else
									<span class="badge badge-secondary">Ubicado</span>
								@endif
							</div>
						@empty
							<div class="text-muted text-center py-3 small">Sin tareas de putaway pendientes</div>
						@endforelse
					</div>
				</div>
			</div>

			<div class="col-lg-6 mb-3">
				<div class="wms-card h-100">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-temperature-half text-warning"></i>
							Control de Cadena de Frío en Almacén
						</h2>
						<span class="badge badge-success font-weight-bold">0 Desviaciones</span>
					</div>

					<div class="d-flex flex-column gap-2">
						<div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-snowflake text-info mr-1"></i> Cámara 1: Lácteos y Derivados</strong>
								<span class="text-muted small">Rango: 2.0°C a 4.0°C</span>
							</div>
							<span class="badge badge-success font-weight-bold py-1 px-2">3.2 °C &bull; Normal</span>
						</div>

						<div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-icicles text-primary mr-1"></i> Cámara 2: Cuarto de Congelación</strong>
								<span class="text-muted small">Rango: -18.0°C a -22.0°C</span>
							</div>
							<span class="badge badge-success font-weight-bold py-1 px-2">-19.4 °C &bull; Normal</span>
						</div>

						<div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-leaf text-success mr-1"></i> Cámara 3: Frutas, Verduras y Carnes</strong>
								<span class="text-muted small">Rango: 1.0°C a 5.0°C</span>
							</div>
							<span class="badge badge-success font-weight-bold py-1 px-2">2.8 °C &bull; Normal</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 2: PROGRAMACIÓN DE RECEPCIÓN      -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'programacion') active @endif" id="tab-programacion">
		<div class="row">
			<div class="col-12 mb-3">
				<div class="wms-card">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-regular fa-calendar-check text-primary"></i>
							Citas de Proveedores Programadas en Muelles
						</h2>
						<span class="badge badge-primary">{{ count($reception['appointments']) }} Citas Registradas</span>
					</div>

					<div class="table-responsive">
						<table class="wms-table">
							<thead>
								<tr>
									<th>Cita / Hora</th>
									<th>Muelle</th>
									<th>Orden (PO)</th>
									<th>Proveedor</th>
									<th>Transportista</th>
									<th>Vehículo / Placa</th>
									<th>Pallets</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
								@foreach($reception['appointments'] as $ap)
									<tr>
										<td>
											<strong>{{ date('d/m/Y H:i', strtotime($ap['appointment_time'])) }}</strong>
										</td>
										<td><span class="badge badge-dark font-weight-bold">{{ $ap['dock_name'] ?? 'Muelle' }}</span></td>
										<td><code>{{ $ap['po_code'] }}</code></td>
										<td><strong>{{ $ap['supplier_name'] }}</strong></td>
										<td>{{ $ap['carrier_name'] ?? 'Directo' }}</td>
										<td><code>{{ $ap['truck_plate'] ?? 'N/D' }}</code> &bull; <span class="small text-muted">{{ $ap['driver_name'] }}</span></td>
										<td><span class="badge badge-light font-weight-bold">{{ $ap['pallet_count'] }} pallets</span></td>
										<td>
											@if($ap['status'] === 'en_descarga')
												<span class="badge badge-primary font-weight-bold">En Descarga</span>
											@elseif($ap['status'] === 'programada')
												<span class="badge badge-info font-weight-bold">Programada</span>
											@elseif($ap['status'] === 'atrasada')
												<span class="badge badge-danger font-weight-bold">Atrasada</span>
											@else
												<span class="badge badge-success font-weight-bold">Completada</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Órdenes de Compra Esperadas -->
			<div class="col-12">
				<div class="wms-card">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-file-invoice text-success"></i>
							Órdenes de Compra Esperadas para Recepción
						</h2>
						<a href="{{ $U('/suppliers') }}" class="btn btn-xs btn-outline-success">Ir a Compras & Procurement</a>
					</div>

					<div class="table-responsive">
						<table class="wms-table">
							<thead>
								<tr>
									<th>Código PO</th>
									<th>Proveedor</th>
									<th>Fecha Entrega Esperada</th>
									<th class="text-right">Valor Estimado</th>
									<th class="text-center">Ítems</th>
									<th class="text-center">Estado PO</th>
								</tr>
							</thead>
							<tbody>
								@foreach($reception['expected_pos'] as $expPo)
									<tr>
										<td><code>{{ $expPo['order_code'] }}</code></td>
										<td><strong>{{ $expPo['supplier_name'] }}</strong></td>
										<td>{{ date('d/m/Y', strtotime($expPo['expected_delivery_date'])) }}</td>
										<td class="text-right font-weight-bold">${{ number_format($expPo['total_estimated_cost'], 2) }}</td>
										<td class="text-center"><span class="badge badge-secondary">{{ $expPo['items_count'] }} SKUs</span></td>
										<td class="text-center">
											<span class="badge badge-outline-primary text-uppercase">{{ $expPo['status'] }}</span>
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 3: INSPECCIÓN & TEMPERATURA       -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'recepcion') active @endif" id="tab-recepcion">
		<div class="wms-card">
			<div class="wms-card-header">
				<h2 class="wms-card-title">
					<i class="fa-solid fa-clipboard-check text-info"></i>
					Inspección de Entrada, Validación de UPC y Control Térmico
				</h2>
				<button class="btn btn-sm btn-info font-weight-bold" id="openInspModalBtn">
					<i class="fa-solid fa-plus-circle mr-1"></i> Nueva Inspección en Muelle
				</button>
			</div>

			<div class="table-responsive">
				<table class="wms-table">
					<thead>
						<tr>
							<th>Fecha / Hora</th>
							<th>Orden / PO</th>
							<th>Producto / SKU</th>
							<th>Lote / Vencimiento</th>
							<th class="text-center">Esperado vs Recibido</th>
							<th class="text-center">Temp. (°C)</th>
							<th class="text-center">Estado Sanitario</th>
							<th>Inspector</th>
							<th>Observaciones</th>
						</tr>
					</thead>
					<tbody>
						@foreach($inspections as $ins)
							<tr>
								<td>{{ date('d/m/Y H:i', strtotime($ins['created_at'])) }}</td>
								<td><code>{{ $ins['po_code'] }}</code></td>
								<td>
									<strong>{{ $ins['product_name'] }}</strong>
									<span class="d-block small text-muted">SKU: {{ $ins['sku'] }}</span>
								</td>
								<td>
									<code>{{ $ins['lot_number'] }}</code>
									<span class="d-block small text-muted">Vence: {{ date('d/m/Y', strtotime($ins['best_before_date'])) }}</span>
								</td>
								<td class="text-center">
									<strong class="text-dark">{{ $ins['received_units'] }}</strong> / {{ $ins['expected_units'] }} uds
									@if($ins['damaged_units'] > 0)
										<span class="badge badge-danger d-block small mt-1">{{ $ins['damaged_units'] }} averiadas</span>
									@endif
								</td>
								<td class="text-center font-weight-bold">
									@if($ins['temp_celsius'] !== null)
										@if($ins['temp_celsius'] <= 4.0)
											<span class="text-success"><i class="fa-solid fa-snowflake mr-1"></i> {{ $ins['temp_celsius'] }}°C</span>
										@elseif($ins['temp_celsius'] <= 8.0)
											<span class="text-warning"><i class="fa-solid fa-temperature-arrow-up mr-1"></i> {{ $ins['temp_celsius'] }}°C</span>
										@else
											<span class="text-danger font-weight-bold"><i class="fa-solid fa-fire mr-1"></i> {{ $ins['temp_celsius'] }}°C</span>
										@endif
									@else
										<span class="text-muted">Ambiente</span>
									@endif
								</td>
								<td class="text-center">
									@if($ins['inspection_status'] === 'conforme')
										<span class="badge badge-success font-weight-bold">Conforme</span>
									@elseif($ins['inspection_status'] === 'cuarentena')
										<span class="badge badge-warning font-weight-bold">Cuarentena</span>
									@else
										<span class="badge badge-danger font-weight-bold">Rechazado</span>
									@endif
								</td>
								<td><span class="small font-weight-bold">{{ $ins['inspector_name'] ?? 'Supervisor' }}</span></td>
								<td><span class="small text-muted">{{ $ins['notes'] }}</span></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 4: UBICACIÓN & PUTAWAY            -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'putaway') active @endif" id="tab-putaway">
		<div class="row">
			<!-- Tareas de Putaway Pendientes -->
			<div class="col-12 mb-3">
				<div class="wms-card">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-dolly text-primary"></i>
							Cola de Tareas de Putaway (Ubicación en Racks)
						</h2>
						<span class="badge badge-primary">{{ count($putaway['putaway_tasks']) }} Tareas</span>
					</div>

					<div class="table-responsive">
						<table class="wms-table">
							<thead>
								<tr>
									<th>Producto</th>
									<th>SKU / Lote</th>
									<th>Origen</th>
									<th>Rack Sugerido</th>
									<th class="text-right">Cantidad</th>
									<th class="text-center">Estado</th>
									<th class="text-center">Acción</th>
								</tr>
							</thead>
							<tbody>
								@foreach($putaway['putaway_tasks'] as $task)
									<tr>
										<td><strong>{{ $task['product_name'] }}</strong></td>
										<td>
											<code>{{ $task['sku'] }}</code>
											<span class="d-block small text-muted">Lote: {{ $task['lot_number'] }}</span>
										</td>
										<td>{{ $task['from_location_name'] }}</td>
										<td>
											<span class="badge badge-light font-weight-bold text-dark border">
												<i class="fa-solid fa-location-dot text-primary mr-1"></i> {{ $task['suggested_rack'] }}
											</span>
										</td>
										<td class="text-right font-weight-bold">{{ $task['amount'] }} uds</td>
										<td class="text-center">
											@if($task['status'] === 'pendiente')
												<span class="badge badge-warning font-weight-bold">Pendiente</span>
											@else
												<span class="badge badge-success font-weight-bold">Ubicado</span>
											@endif
										</td>
										<td class="text-center">
											@if($task['status'] === 'pendiente')
												<button type="button" class="btn btn-xs btn-success font-weight-bold" onclick="confirmPutawayTask({{ $task['id'] }}, {{ $task['to_location_id'] }})">
													<i class="fa-solid fa-check mr-1"></i> Confirmar Putaway
												</button>
											@else
												<span class="text-muted small"><i class="fa-solid fa-check-double text-success"></i> Confirmado</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Mapa de Racks de Bodega -->
			<div class="col-12">
				<div class="wms-card">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-border-all text-info"></i>
							Mapa y Distribución de Posiciones en Bodega
						</h2>
						<a href="{{ $U('/locations/map') }}" class="btn btn-xs btn-outline-info">Abrir Vista Mapa Completo</a>
					</div>

					<div class="wms-racks-grid">
						@foreach(array_slice($putaway['locations'], 0, 8) as $loc)
							<div class="wms-rack-box">
								<div class="wms-rack-name">
									<span>{{ $loc['name'] }}</span>
									<span class="badge @if($loc['storage_class'] === 'chilled') badge-info @elseif($loc['storage_class'] === 'frozen') badge-primary @else badge-light @endif" style="font-size: 0.65rem;">
										{{ $loc['storage_class'] ?? 'Ambiente' }}
									</span>
								</div>
								<div class="small text-muted">
									Código: <code>{{ $loc['label_code'] ?? 'LOC-0' . $loc['id'] }}</code>
								</div>
								<div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top small">
									<span class="text-muted">Stock actual:</span>
									<strong class="text-primary">{{ number_format($loc['current_stock'] ?: 0) }} uds</strong>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 5: MOVIMIENTOS INTERNOS           -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'movimientos') active @endif" id="tab-movimientos">
		<div class="wms-card">
			<div class="wms-card-header">
				<h2 class="wms-card-title">
					<i class="fa-solid fa-right-left text-primary"></i>
					Historial de Movimientos Internos & Reabastecimiento
				</h2>
				<span class="badge badge-light font-weight-bold">Reflejado en Kardex</span>
			</div>

			<div class="table-responsive">
				<table class="wms-table">
					<thead>
						<tr>
							<th>Fecha / Hora</th>
							<th>Producto / SKU</th>
							<th>Ubicación Origen</th>
							<th>Ubicación Destino</th>
							<th class="text-right">Cantidad</th>
							<th>Tipo de Movimiento</th>
							<th>Operador</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($internalMovements as $im)
							<tr>
								<td>{{ date('d/m/Y H:i', strtotime($im['created_at'])) }}</td>
								<td>
									<strong>{{ $im['product_name'] }}</strong>
									<span class="d-block small text-muted">SKU: {{ $im['sku'] }}</span>
								</td>
								<td>{{ $im['from_location_name'] ?? 'Bodega Reserva' }}</td>
								<td><strong>{{ $im['to_location_name'] ?? 'Picking / Piso' }}</strong></td>
								<td class="text-right font-weight-bold">{{ $im['amount'] }} uds</td>
								<td>
									@if($im['movement_type'] === 'reabastecimiento_picking')
										<span class="badge badge-info">Reabastecimiento Picking</span>
									@elseif($im['movement_type'] === 'sala_ventas')
										<span class="badge badge-success">Traslado Sala Ventas</span>
									@else
										<span class="badge badge-secondary">Reubicación Rack</span>
									@endif
								</td>
								<td>{{ $im['operator_name'] ?? 'Almacenero' }}</td>
								<td class="text-center"><span class="badge badge-outline-success font-weight-bold">Completado</span></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 6: PREPARACIÓN DE PEDIDOS (PICKING)-->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'picking') active @endif" id="tab-picking">
		<div class="wms-card">
			<div class="wms-card-header">
				<h2 class="wms-card-title">
					<i class="fa-solid fa-cart-flatbed text-success"></i>
					Cola de Picking & Packing de Pedidos de Clientes
				</h2>
				<a href="{{ $U('/orders') }}" class="btn btn-xs btn-outline-success">Ir a Centro de Despachos</a>
			</div>

			<div class="table-responsive">
				<table class="wms-table">
					<thead>
						<tr>
							<th>Pedido</th>
							<th>Cliente</th>
							<th>Fecha Requerida</th>
							<th>Tipo Entrega</th>
							<th class="text-center">Líneas / Progreso</th>
							<th>Estado</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@foreach($pickingOrders as $ord)
							<tr>
								<td><code>{{ $ord['order_code'] }}</code></td>
								<td><strong>{{ $ord['customer_name'] ?? 'Cliente Corporativo' }}</strong></td>
								<td>{{ date('d/m/Y', strtotime($ord['requested_date'])) }}</td>
								<td><span class="badge badge-light text-uppercase">{{ $ord['delivery_type'] }}</span></td>
								<td class="text-center">
									<div class="d-flex align-items-center justify-content-center gap-1">
										<span class="small font-weight-bold">{{ $ord['picked_items'] }} / {{ $ord['total_items'] }}</span>
										<div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
											<div class="progress-bar bg-success" style="width: {{ $ord['total_items'] > 0 ? round(($ord['picked_items'] / $ord['total_items']) * 100) : 0 }}%;"></div>
										</div>
									</div>
								</td>
								<td>
									@if($ord['status'] === 'dispatched')
										<span class="badge badge-success">Despachado</span>
									@elseif($ord['status'] === 'picking')
										<span class="badge badge-warning">En Picking</span>
									@else
										<span class="badge badge-info">Confirmado</span>
									@endif
								</td>
								<td class="text-center">
									<a href="{{ $U('/dispatch') }}#tab-picking" class="btn btn-xs btn-outline-primary">
										<i class="fa-solid fa-barcode mr-1"></i> Escanear Picking
									</a>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 7: CONTROL DE EXISTENCIAS & FEFO  -->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'existencias') active @endif" id="tab-existencias">
		<div class="row">
			<div class="col-12 mb-3">
				<div class="wms-card">
					<div class="wms-card-header">
						<div class="d-flex align-items-center flex-wrap gap-2">
							<h2 class="wms-card-title mr-3">
								<i class="fa-solid fa-boxes-stacked text-primary"></i>
								Existencias por Ubicación y Rotación FEFO
							</h2>
							<input type="text" id="wmsStockSearch" class="form-control form-control-sm" placeholder="Buscar por SKU, producto o ubicación..." style="max-width: 280px;">
						</div>
						<a href="{{ $U('/kardex') }}" class="btn btn-xs btn-outline-primary">Ver Kardex Valorizado</a>
					</div>

					<div class="table-responsive">
						<table class="wms-table">
							<thead>
								<tr>
									<th>Producto</th>
									<th>SKU</th>
									<th>Departamento</th>
									<th>Ubicación en Bodega</th>
									<th class="text-right">Stock Físico</th>
									<th>Fecha Vencimiento</th>
									<th class="text-center">Semáforo FEFO</th>
								</tr>
							</thead>
							<tbody id="wmsStockTableBody">
								@foreach($stockControl['stock'] as $stk)
									<tr>
										<td><strong>{{ $stk['product_name'] }}</strong></td>
										<td><code>{{ $stk['sku'] }}</code></td>
										<td><span class="badge badge-light">{{ $stk['department'] }}</span></td>
										<td><span class="badge badge-light font-weight-bold text-dark border">{{ $stk['location_name'] }}</span></td>
										<td class="text-right font-weight-bold text-primary">{{ number_format($stk['amount']) }} uds</td>
										<td>{{ date('d/m/Y', strtotime($stk['best_before_date'])) }}</td>
										<td class="text-center">
											@if($stk['days_until_due'] < 0)
												<span class="badge badge-danger">Vencido</span>
											@elseif($stk['days_until_due'] <= 15)
												<span class="badge badge-warning">FEFO Crítico ({{ $stk['days_until_due'] }}d)</span>
											@else
												<span class="badge badge-success">Óptimo ({{ $stk['days_until_due'] }}d)</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Conteos Cíclicos -->
			<div class="col-12">
				<div class="wms-card">
					<div class="wms-card-header">
						<h2 class="wms-card-title">
							<i class="fa-solid fa-clipboard-list text-info"></i>
							Historial de Conteos Cíclicos de Precisión
						</h2>
						<a href="{{ $U('/inventory') }}" class="btn btn-xs btn-outline-info">Ir a Conteos Físicos</a>
					</div>

					<div class="table-responsive">
						<table class="wms-table">
							<thead>
								<tr>
									<th>Fecha</th>
									<th>Producto</th>
									<th>Ubicación</th>
									<th class="text-right">Stock Teórico</th>
									<th class="text-right">Stock Físico Contado</th>
									<th class="text-center">Diferencia</th>
									<th>Resultado / Notas</th>
								</tr>
							</thead>
							<tbody>
								@foreach($stockControl['cycle_counts'] as $cc)
									<tr>
										<td>{{ date('d/m/Y', strtotime($cc['scheduled_date'])) }}</td>
										<td><strong>{{ $cc['product_name'] }}</strong> ({{ $cc['sku'] }})</td>
										<td>{{ $cc['location_name'] }}</td>
										<td class="text-right">{{ number_format($cc['theoretical_stock']) }}</td>
										<td class="text-right font-weight-bold">{{ number_format($cc['counted_stock']) }}</td>
										<td class="text-center font-weight-bold">
											@if($cc['difference_amount'] == 0)
												<span class="text-success"><i class="fa-solid fa-check mr-1"></i> Exacto (0)</span>
											@else
												<span class="text-danger">{{ $cc['difference_amount'] }} uds</span>
											@endif
										</td>
										<td><span class="small text-muted">{{ $cc['notes'] }}</span></td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 8: DEVOLUCIONES (LOGÍSTICA INVERSA)-->
	<!-- ========================================== -->
	<div class="wms-tab-panel @if($activeTab == 'devoluciones') active @endif" id="tab-devoluciones">
		<div class="wms-card">
			<div class="wms-card-header">
				<h2 class="wms-card-title">
					<i class="fa-solid fa-arrow-rotate-left text-danger"></i>
					Módulo de Devoluciones, Clasificación Sanitaria y RTV
				</h2>
				<a href="{{ $U('/returns') }}" class="btn btn-xs btn-outline-danger">Centro de Devoluciones</a>
			</div>

			<div class="table-responsive">
				<table class="wms-table">
					<thead>
						<tr>
							<th>Código Devolución</th>
							<th>Tipo</th>
							<th>Origen / Cliente</th>
							<th>Producto / SKU</th>
							<th class="text-right">Cantidad</th>
							<th>Motivo</th>
							<th>Disposición Sanitaria</th>
							<th>Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($reverseLogistics as $rl)
							<tr>
								<td><code>{{ $rl['return_code'] }}</code></td>
								<td><span class="badge badge-light text-capitalize">{{ $rl['return_type'] }}</span></td>
								<td><strong>{{ $rl['customer_or_origin'] }}</strong></td>
								<td>
									<strong>{{ $rl['product_name'] }}</strong>
									<span class="d-block small text-muted">SKU: {{ $rl['sku'] }}</span>
								</td>
								<td class="text-right font-weight-bold">{{ $rl['amount'] }} uds</td>
								<td><span class="small text-muted">{{ $rl['reason'] }}</span></td>
								<td>
									@if($rl['disposition'] === 'reintegro_stock')
										<span class="badge badge-success">Reintegro a Stock</span>
									@elseif($rl['disposition'] === 'rtv_proveedor')
										<span class="badge badge-warning">Devolución Proveedor (RTV)</span>
									@else
										<span class="badge badge-danger">Baja por Merma</span>
									@endif
								</td>
								<td><span class="badge badge-outline-secondary text-uppercase">{{ $rl['status'] }}</span></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>

<!-- MODAL: REGISTRAR INSPECCIÓN EN MUELLE -->
<div class="wms-modal-overlay" id="wmsInspectionModal">
	<div class="wms-modal-box">
		<div class="wms-modal-header">
			<div class="d-flex align-items-center gap-2">
				<div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center font-weight-bold" style="width: 40px; height: 40px; font-size: 1rem;">
					<i class="fa-solid fa-truck-ramp-box"></i>
				</div>
				<div>
					<h3 class="h5 font-weight-bold m-0">Inspección de Recepción en Muelle</h3>
					<span class="text-muted small">Validación de cantidades, temperatura, lote y control de calidad</span>
				</div>
			</div>
			<button type="button" class="close" id="closeInspModalBtn">&times;</button>
		</div>

		<form id="wmsInspectionForm">
			<input type="hidden" id="inspBranchId" value="{{ $selectedBranchId }}">

			<div class="row">
				<div class="col-md-6 form-group mb-2">
					<label class="small font-weight-bold">Código de Orden (PO)</label>
					<input type="text" id="inspPoCode" class="form-control form-control-sm" value="PO-20261102-L01" required>
				</div>
				<div class="col-md-6 form-group mb-2">
					<label class="small font-weight-bold">SKU del Producto</label>
					<input type="text" id="inspSku" class="form-control form-control-sm" value="DAIRY-LECHE-001" required>
				</div>
			</div>

			<div class="form-group mb-2">
				<label class="small font-weight-bold">Descripción del Producto</label>
				<input type="text" id="inspProdName" class="form-control form-control-sm" value="Leche Entera 1L UHT" required>
			</div>

			<div class="row">
				<div class="col-md-6 form-group mb-2">
					<label class="small font-weight-bold">Cantidad Esperada</label>
					<input type="number" id="inspExpectedQty" class="form-control form-control-sm" value="240" required>
				</div>
				<div class="col-md-6 form-group mb-2">
					<label class="small font-weight-bold">Cantidad Recibida Conforme</label>
					<input type="number" id="inspReceivedQty" class="form-control form-control-sm" value="240" required>
				</div>
			</div>

			<div class="row">
				<div class="col-md-4 form-group mb-2">
					<label class="small font-weight-bold">Temperatura (°C)</label>
					<input type="number" step="0.1" id="inspTemp" class="form-control form-control-sm" value="3.2" placeholder="ej. 3.5">
				</div>
				<div class="col-md-4 form-group mb-2">
					<label class="small font-weight-bold">Número de Lote</label>
					<input type="text" id="inspLot" class="form-control form-control-sm" value="LOT-LEC-2026B" required>
				</div>
				<div class="col-md-4 form-group mb-2">
					<label class="small font-weight-bold">Fecha Vencimiento</label>
					<input type="date" id="inspBbd" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+35 days')) }}" required>
				</div>
			</div>

			<div class="form-group mb-2">
				<label class="small font-weight-bold">Dictamen Sanitario</label>
				<select id="inspStatus" class="form-control form-control-sm">
					<option value="conforme">Conforme (Apto para Putaway en Racks)</option>
					<option value="cuarentena">Cuarentena (Aislamiento Preventivo)</option>
					<option value="rechazado">Rechazado en Muelle</option>
				</select>
			</div>

			<div class="form-group mb-3">
				<label class="small font-weight-bold">Observaciones / Evidencia</label>
				<textarea id="inspNotes" class="form-control form-control-sm" rows="2" placeholder="Detalles de inspección en muelle..."></textarea>
			</div>

			<div class="d-flex justify-content-between align-items-center pt-2 border-top">
				<button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('wmsInspectionModal').classList.remove('active')">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-info font-weight-bold">
					<i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Inspección
				</button>
			</div>
		</form>
	</div>
</div>

@push('pageScripts')
<script src="{{ $U('/js/daily_warehouse.js?v=', true) }}{{ daily_asset_version('/js/daily_warehouse.js') }}"></script>
@endpush
@endsection

