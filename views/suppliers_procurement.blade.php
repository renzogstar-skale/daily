@extends('layout.default')

@section('title', 'Proveedores y Abastecimiento')

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-truck-field"></i></div>
		<div>
			<h4 class="dairy-page-title">Proveedores y Abastecimiento</h4>
			<p class="dairy-page-subtitle">Gestión de cadena de suministro, comparador de costos de lácteos, evaluación de entregas OTIF y compras inteligentes.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right mt-2 mt-md-0 dairy-page-actions">
		<button class="btn btn-primary shadow-sm mr-1" data-toggle="modal" data-target="#modal-supplier-form" onclick="DairySuppliers.OpenNewSupplierModal()">
			<i class="fa-solid fa-plus mr-1"></i>Nuevo Proveedor
		</button>
		<button class="btn btn-outline-primary shadow-sm mr-1" data-toggle="modal" data-target="#modal-price-form">
			<i class="fa-solid fa-tag mr-1"></i>Cotización
		</button>
		<a class="btn btn-outline-success shadow-sm" href="{{ $U('/mrp') }}" title="Recepción contra orden de compra: ingresa el stock y registra el OTIF">
			<i class="fa-solid fa-truck-ramp-box mr-1"></i>Recepción
		</a>
		@include('components.dairy_export')
	</div>
</div>

<!-- KPI Cards -->
@php
	$activeSuppliersCount = count(array_filter($suppliers, function($s) { return $s['active'] == 1; }));
	$totalDeliveries = count($deliveries);
	$onTimeDeliveries = count(array_filter($deliveries, function($d) { return $d['status'] === 'on_time'; }));
	$globalOtif = $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100, 1) : 100;
	$pendingIncidentsCount = count(array_filter($incidents, function($i) { return in_array($i['status'], ['open', 'claimed']); }));
	$recomCount = count($recommendations);
@endphp
@php
	$fillRate = 0; $ordered = 0; $received = 0;
	foreach ($deliveries as $d) { $ordered += floatval($d['items_ordered']); $received += floatval($d['items_received']); }
	$fillRate = $ordered > 0 ? round($received / $ordered * 100, 1) : 100;
	$incidentCost = array_sum(array_map(function($i) { return in_array($i['status'], ['open', 'claimed']) ? floatval($i['cost_impact']) : 0; }, $incidents));
	$recomCost = array_sum(array_map(function($r) { return floatval($r['estimated_cost']); }, $recommendations));
@endphp
@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Proveedores', 'value' => $activeSuppliersCount, 'sub' => 'de ' . count($suppliers) . ' registrados', 'icon' => 'fa-handshake', 'tone' => 'primary'],
	['label' => 'Puntualidad OTIF', 'value' => $globalOtif . '%', 'sub' => $onTimeDeliveries . ' de ' . $totalDeliveries . ' a tiempo', 'icon' => 'fa-truck-fast', 'tone' => $globalOtif >= 95 ? 'success' : 'warning', 'alert' => $globalOtif < 95],
	['label' => 'Nivel de Servicio', 'value' => $fillRate . '%', 'sub' => 'unidades recibidas / pedidas', 'icon' => 'fa-boxes-packing', 'tone' => $fillRate >= 98 ? 'success' : 'warning', 'alert' => $fillRate < 98],
	['label' => 'Incidencias Abiertas', 'value' => $pendingIncidentsCount, 'sub' => '$' . number_format($incidentCost, 2) . ' por recuperar', 'icon' => 'fa-file-circle-exclamation', 'tone' => $pendingIncidentsCount > 0 ? 'danger' : 'secondary', 'alert' => $pendingIncidentsCount > 0],
	['label' => 'Pedido Sugerido', 'value' => $recomCount, 'sub' => 'productos a reponer', 'icon' => 'fa-cart-flatbed', 'tone' => $recomCount > 0 ? 'info' : 'secondary'],
	['label' => 'Valor a Comprar', 'value' => '$' . number_format($recomCost, 2), 'sub' => 'costo del pedido sugerido', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'purple'],
]])

<!-- Main Navigation Tabs -->
<ul class="nav nav-pills dairy-subnav-container dairy-subnav-pills flex-nowrap mb-3" id="procurement-tabs" role="tablist">
	<li class="nav-item">
		<a class="nav-link active font-weight-bold" id="tab-suppliers-link" data-toggle="pill" href="#tab-suppliers" role="tab">
			<i class="fa-solid fa-address-book mr-1"></i>Directorio de Proveedores
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-comparison-link" data-toggle="pill" href="#tab-comparison" role="tab">
			<i class="fa-solid fa-scale-balanced mr-1"></i>Comparador de Costos
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-prices-link" data-toggle="pill" href="#tab-prices" role="tab">
			<i class="fa-solid fa-tags mr-1"></i>Historial de Precios
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-deliveries-link" data-toggle="pill" href="#tab-deliveries" role="tab">
			<i class="fa-solid fa-clipboard-check mr-1"></i>Cumplimiento de Entregas (OTIF)
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-incidents-link" data-toggle="pill" href="#tab-incidents" role="tab">
			<i class="fa-solid fa-shield-halved mr-1"></i>Registro de Incidencias
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold text-info" id="tab-recommendations-link" data-toggle="pill" href="#tab-recommendations" role="tab">
			<i class="fa-solid fa-brain mr-1"></i>Compras Inteligentes @if($recomCount > 0)<span class="badge badge-danger ml-1">{{ $recomCount }}</span>@endif
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold text-success" id="tab-budget-link" data-toggle="pill" href="#tab-budget" role="tab">
			<i class="fa-solid fa-calculator mr-1"></i>Presupuesto y Compromisos
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-threeway-matching-link" data-toggle="pill" href="#tab-threeway-matching" role="tab">
			<i class="fa-solid fa-file-invoice-dollar mr-1 text-primary"></i>Conciliación Facturas (3-Way)
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-contracts-link" data-toggle="pill" href="#tab-contracts" role="tab">
			<i class="fa-solid fa-file-signature mr-1 text-warning"></i>Gestión de Contratos
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold text-success" id="tab-supplier-portal-link" data-toggle="pill" href="#tab-supplier-portal" role="tab">
			<i class="fa-solid fa-network-wired mr-1"></i>Portal Proveedores B2B
		</a>
	</li>
</ul>

<div class="tab-content" id="procurement-tab-content">

	<!-- TAB 1: Directorio de Proveedores -->
	<div class="tab-pane fade show active" id="tab-suppliers" role="tabpanel">
		<div class="card shadow-sm border-0">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-truck text-primary mr-2"></i>Directorio de Proveedores Comerciales</h5>
				<div class="form-inline">
					<input type="text" id="supplier-search-input" class="form-control form-control-sm mr-2" placeholder="Buscar proveedor o contacto...">
					<select id="supplier-filter-status" class="custom-select custom-select-sm" onchange="DairySuppliers.FilterTable()">
						<option value="all">Todos los estados</option>
						<option value="active" selected>Solo activos</option>
						<option value="inactive">Inactivos</option>
					</select>
				</div>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0" id="suppliers-table">
						<thead class="thead-light">
							<tr>
								<th>Proveedor / Razón Social</th>
								<th>Contacto & Teléfono</th>
								<th>Condiciones & Plazo</th>
								<th class="text-center">Lead Time</th>
								<th class="text-center">Calificación</th>
								<th class="text-center">Puntualidad OTIF</th>
								<th class="text-center">Catálogo</th>
								<th class="text-center">Estado</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@forelse($suppliers as $s)
							<tr class="supplier-row @if($s['active'] == 0) table-secondary text-muted @endif" data-name="{{ strtolower($s['name'] . ' ' . $s['contact_name']) }}" data-active="{{ $s['active'] }}">
								<td>
									<div class="font-weight-bold text-dark">{{ $s['name'] }}</div>
									<small class="text-muted"><i class="fa-solid fa-id-card mr-1"></i>{{ $s['tax_id'] ?: 'Sin RFC' }}</small>
									@if($s['pending_incidents_count'] > 0)
										<span class="badge badge-warning ml-1" title="Incidencias abiertas"><i class="fa-solid fa-exclamation mr-1"></i>{{ $s['pending_incidents_count'] }} inc.</span>
									@endif
								</td>
								<td>
									<div>{{ $s['contact_name'] ?: 'No asignado' }}</div>
									<small class="text-muted"><i class="fa-solid fa-phone mr-1"></i>{{ $s['phone'] ?: 'N/D' }}</small>
								</td>
								<td>
									<div>{{ $s['payment_terms'] }}</div>
									<small class="text-muted">Mín: ${{ number_format($s['min_order_amount'], 2) }}</small>
								</td>
								<td class="text-center">
									<span class="badge badge-info">{{ $s['lead_time_days'] }} días</span>
								</td>
								<td class="text-center">
									<span class="text-warning font-weight-bold">
										<i class="fa-solid fa-star"></i> {{ number_format($s['rating'], 1) }}
									</span>
								</td>
								<td class="text-center">
									@php $otif = $s['otif_percentage']; @endphp
									@if($otif === null)
									<span class="badge badge-light border" title="Sin entregas registradas">Sin datos</span>
									@else
									<span class="badge @if($otif >= 95) badge-success @elseif($otif >= 85) badge-warning @else badge-danger @endif">
										{{ $otif }}%
									</span>
									@endif
								</td>
								<td class="text-center">
									<span class="badge badge-light border">{{ $s['products_count'] }} productos</span>
								</td>
								<td class="text-center">
									@if($s['active'] == 1)
										<span class="badge badge-success">Activo</span>
									@else
										<span class="badge badge-secondary">Inactivo</span>
									@endif
								</td>
								<td class="text-right">
									<button class="btn btn-sm btn-outline-primary py-0" title="Editar Proveedor" onclick="DairySuppliers.EditSupplier({{ json_encode($s) }})">
										<i class="fa-solid fa-pen"></i>
									</button>
									<button class="btn btn-sm @if($s['active'] == 1) btn-outline-danger @else btn-outline-success @endif py-0 ml-1" 
										title="@if($s['active'] == 1) Desactivar @else Activar @endif" 
										onclick="DairySuppliers.ToggleActive({{ $s['id'] }}, {{ $s['active'] == 1 ? 0 : 1 }})">
										<i class="fa-solid @if($s['active'] == 1) fa-ban @else fa-check @endif"></i>
									</button>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center py-4 text-muted">No hay proveedores registrados.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 2: Comparador de Costos -->
	<div class="tab-pane fade" id="tab-comparison" role="tabpanel">
		<div class="card shadow-sm border-0 mb-4 bg-light">
			<div class="card-body py-3">
				<div class="row align-items-center">
					<div class="col-md-5">
						<label class="font-weight-bold mb-1"><i class="fa-solid fa-cubes mr-1 text-primary"></i>Selecciona un Producto Lácteo para comparar:</label>
						<select id="compare-product-select" class="custom-select shadow-sm" onchange="DairySuppliers.LoadComparison(this.value)">
							<option value="">-- Seleccionar producto lácteo --</option>
							@foreach($products as $p)
							<option value="{{ $p->id }}" @if($p->id == 30) selected @endif>{{ $p->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-7 text-muted small mt-2 mt-md-0">
						<i class="fa-solid fa-info-circle mr-1 text-info"></i>El comparador evalúa automáticamente el costo unitario, tiempo de entrega (lead time), condiciones de pago y puntaje de cumplimiento de cada proveedor.
					</div>
				</div>
			</div>
		</div>

		<div id="comparison-results-container">
			<div class="row" id="comparison-cards-row">
				<!-- Tarjetas cargadas vía JavaScript -->
			</div>
		</div>
	</div>

	<!-- TAB 3: Historial de Precios y Cotizaciones -->
	<div class="tab-pane fade" id="tab-prices" role="tabpanel">
		<div class="card shadow-sm border-0">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-tags text-primary mr-2"></i>Historial de Precios y Cotizaciones por Proveedor</h5>
				<button class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#modal-price-form">
					<i class="fa-solid fa-plus mr-1"></i>Registrar Cotización
				</button>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th>Producto</th>
								<th>Proveedor</th>
								<th class="text-right">Precio Unitario</th>
								<th class="text-center">Pedido Mínimo</th>
								<th class="text-center">Tiempo Entrega</th>
								<th>Vigencia Desde</th>
								<th>Notas / Condiciones</th>
								<th class="text-center">Estado</th>
							</tr>
						</thead>
						<tbody>
							@forelse($priceHistory as $ph)
							<tr>
								<td class="font-weight-bold text-dark">{{ $ph['product_name'] }}</td>
								<td>{{ $ph['supplier_name'] }}</td>
								<td class="text-right font-weight-bold text-primary">${{ number_format($ph['price'], 2) }} / {{ $ph['qu_name'] }}</td>
								<td class="text-center">{{ $ph['min_order_qty'] }} {{ $ph['qu_name'] }}</td>
								<td class="text-center">{{ $ph['delivery_lead_days'] }} días</td>
								<td>{{ $ph['valid_from'] }}</td>
								<td><small class="text-muted">{{ $ph['notes'] ?: 'Sin observaciones' }}</small></td>
								<td class="text-center">
									@if($ph['is_current'] == 1)
										<span class="badge badge-success">Vigente</span>
									@else
										<span class="badge badge-light border">Histórico</span>
									@endif
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="8" class="text-center py-4 text-muted">No hay registros de precios.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 4: Cumplimiento de Entregas (OTIF) -->
	<div class="tab-pane fade" id="tab-deliveries" role="tabpanel">
		<div class="card shadow-sm border-0">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-clipboard-check text-success mr-2"></i>Auditoría de Recepción y Puntualidad (OTIF)</h5>
				<a class="btn btn-sm btn-success shadow-sm" href="{{ $U('/mrp') }}" title="Las entregas se registran al recibir la orden de compra">
					<i class="fa-solid fa-truck-ramp-box mr-1"></i>Recibir orden
				</a>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th>Ref. Orden</th>
								<th>Proveedor</th>
								<th>Fecha Esperada</th>
								<th>Fecha Real</th>
								<th class="text-center">Cant. Ordenada vs Recibida</th>
								<th class="text-center">Temp. Llegada</th>
								<th class="text-center">Cumplimiento</th>
								<th class="text-center">Calificación</th>
								<th>Observaciones</th>
							</tr>
						</thead>
						<tbody>
							@forelse($deliveries as $d)
							<tr>
								<td class="font-weight-bold">{{ $d['order_reference'] }}</td>
								<td>{{ $d['supplier_name'] }}</td>
								<td>{{ $d['expected_delivery_date'] }}</td>
								<td>{{ $d['actual_delivery_date'] ?: 'Pendiente' }}</td>
								<td class="text-center">
									<span class="font-weight-bold">{{ $d['items_received'] }}</span> / {{ $d['items_ordered'] }}
									@if($d['items_received'] < $d['items_ordered'])
										<small class="text-danger ml-1">(-{{ $d['items_ordered'] - $d['items_received'] }})</small>
									@endif
								</td>
								<td class="text-center">
									@if($d['temperature_on_arrival'])
										<span class="badge @if($d['temperature_on_arrival'] <= 4.0) badge-info @else badge-warning @endif">
											<i class="fa-solid fa-temperature-half mr-1"></i>{{ $d['temperature_on_arrival'] }}°C
										</span>
									@else
										<span class="text-muted">N/D</span>
									@endif
								</td>
								<td class="text-center">
									@if($d['status'] === 'on_time')
										<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>A Tiempo</span>
									@elseif($d['status'] === 'delayed')
										<span class="badge badge-danger"><i class="fa-solid fa-clock mr-1"></i>Demorado ({{ $d['delay_days'] }}d)</span>
									@elseif($d['status'] === 'partial')
										<span class="badge badge-warning"><i class="fa-solid fa-box-open mr-1"></i>Parcial</span>
									@else
										<span class="badge badge-secondary">{{ $d['status'] }}</span>
									@endif
								</td>
								<td class="text-center text-warning font-weight-bold">
									{{ $d['compliance_rating'] }} / 5
								</td>
								<td><small class="text-muted">{{ $d['notes'] ?: '-' }}</small></td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center py-4 text-muted">No hay registros de entregas.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 5: Registro de Incidencias -->
	<div class="tab-pane fade" id="tab-incidents" role="tabpanel">
		<div class="card shadow-sm border-0">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-shield-halved text-warning mr-2"></i>Gestión de Incidencias y Reclamaciones</h5>
				<button class="btn btn-sm btn-warning text-dark shadow-sm" data-toggle="modal" data-target="#modal-incident-form">
					<i class="fa-solid fa-plus mr-1"></i>Reportar Incidencia
				</button>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th>Fecha</th>
								<th>Proveedor</th>
								<th>Producto Afectado</th>
								<th>Tipo de Incidencia</th>
								<th class="text-center">Gravedad</th>
								<th class="text-right">Impacto $</th>
								<th>Descripción & Resolución</th>
								<th class="text-center">Estado</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@forelse($incidents as $inc)
							<tr>
								<td>{{ $inc['incident_date'] }}</td>
								<td class="font-weight-bold">{{ $inc['supplier_name'] }}</td>
								<td>{{ $inc['product_name'] ?: 'General' }}</td>
								<td>
									@php
										$types = [
											'reception_rejection' => 'Rechazo en recepción',
											'cold_chain_break' => 'Ruptura cadena de frío',
											'damaged_packaging' => 'Empaque/Envase dañado',
											'near_expiration' => 'Fecha de caducidad corta',
											'shortage' => 'Faltante de producto',
											'price_discrepancy' => 'Discrepancia de precio',
											'other' => 'Otro incidente'
										];
									@endphp
									<span class="badge badge-light border">{{ $types[$inc['incident_type']] ?? $inc['incident_type'] }}</span>
								</td>
								<td class="text-center">
									@if($inc['severity'] === 'critical')
										<span class="badge badge-danger">Crítica</span>
									@elseif($inc['severity'] === 'medium')
										<span class="badge badge-warning">Media</span>
									@else
										<span class="badge badge-info">Leve</span>
									@endif
								</td>
								<td class="text-right font-weight-bold text-danger">
									@if($inc['cost_impact'] > 0)
										-${{ number_format($inc['cost_impact'], 2) }}
									@else
										$0.00
									@endif
								</td>
								<td>
									<div>{{ $inc['description'] }}</div>
									@if($inc['resolution_notes'])
										<small class="text-success"><i class="fa-solid fa-check-circle mr-1"></i>{{ $inc['resolution_notes'] }}</small>
									@endif
								</td>
								<td class="text-center">
									@if($inc['status'] === 'open')
										<span class="badge badge-danger">Abierta</span>
									@elseif($inc['status'] === 'claimed')
										<span class="badge badge-warning">En Reclamo</span>
									@else
										<span class="badge badge-success">Resuelta</span>
									@endif
								</td>
								<td class="text-right">
									@if($inc['status'] !== 'resolved')
									<button class="btn btn-sm btn-outline-success py-0" onclick="DairySuppliers.OpenResolveModal({{ json_encode($inc) }})">
										Resolver
									</button>
									@else
									<i class="fa-solid fa-check text-success"></i>
									@endif
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center py-4 text-muted">No hay incidencias registradas.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 6: Compras y Recomendaciones Inteligentes -->
	<div class="tab-pane fade" id="tab-recommendations" role="tabpanel">
		<div class="card shadow-sm border-0">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-brain text-info mr-2"></i>Reposición sugerida (MRP)</h5>
					<small class="text-muted">Mismo cálculo que <a href="{{ $U('/mrp') }}">Planificación MRP</a>: disponible neto contra punto de pedido, proveedor y costo vigentes.</small>
				</div>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th>Producto</th>
								<th class="text-center">Stock utilizable</th>
								<th class="text-center">Punto de pedido</th>
								<th class="text-center">Sugerido a Comprar</th>
								<th>Proveedor Sugerido</th>
								<th class="text-right">Costo Estimado</th>
								<th class="text-center">Tiempo Entrega</th>
								<th>Motivo</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@forelse($recommendations as $rec)
							<tr class="@if($rec['urgency'] === 'critical') table-danger @endif">
								<td>
									<div class="font-weight-bold text-dark">{{ $rec['product_name'] }}</div>
									<small class="text-muted">{{ $rec['sku'] ?: 'Prod #' . $rec['product_id'] }}</small>
								</td>
								<td class="text-center font-weight-bold @if($rec['current_stock'] == 0) text-danger @else text-warning @endif">
									{{ $rec['current_stock'] }} {{ $rec['qu_name'] }}
								</td>
								<td class="text-center">{{ $rec['min_stock_amount'] }} {{ $rec['qu_name'] }}</td>
								<td class="text-center font-weight-bold text-primary">
									{{ $rec['recommended_order_qty'] }} {{ $rec['qu_name'] }}
								</td>
								<td>
									<div class="font-weight-bold">{{ $rec['suggested_supplier_name'] }}</div>
									<small class="text-muted">${{ number_format($rec['unit_price'], 2) }} / {{ $rec['qu_name'] }}</small>
								</td>
								<td class="text-right font-weight-bold text-success">
									${{ number_format($rec['estimated_cost'], 2) }}
								</td>
								<td class="text-center">
									<span class="badge badge-info">{{ $rec['lead_time_days'] }} días</span><br>
									<small class="text-muted">Llega: {{ $rec['expected_delivery_date'] }}</small>
								</td>
								<td>
									<small class="text-dark">{{ $rec['reason'] }}</small>
								</td>
								<td class="text-right">
									@if($rec['suggested_supplier_id'])
									<button class="btn btn-sm btn-success" onclick="DairySuppliers.QuickCreateOrder({{ json_encode($rec) }})">
										<i class="fa-solid fa-cart-plus mr-1"></i>Ordenar
									</button>
									@else
									<span class="badge badge-warning" title="Asigne un proveedor o cotización al producto">Sin proveedor</span>
									@endif
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center py-4 text-success font-weight-bold">
									<i class="fa-solid fa-check-circle fa-2x mb-2 d-block"></i>
									Inventario óptimo. No se requieren órdenes de reabastecimiento en este momento.
								</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 7: Presupuesto y Compromisos -->
	<div class="tab-pane fade" id="tab-budget" role="tabpanel">
		<!-- Banner Informativo Contable -->
		<div class="card shadow-sm border-0 mb-3" style="border-left: 4px solid var(--daily-green, #00C83B) !important;">
			<div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
				<div class="d-flex align-items-center mb-2 mb-md-0">
					<div class="mr-3" style="font-size: 24px; color: var(--daily-green, #00C83B);">
						<i class="fa-solid fa-scale-balanced"></i>
					</div>
					<div>
						<h5 class="mb-0 font-weight-bold text-dark">Presupuesto y Compromisos de Compras · {{ $budgetData['period'] }}</h5>
						<p class="text-muted small mb-0">Control del gasto asignado frente a facturas recibidas y compromisos en tránsito sin duplicidades contables.</p>
					</div>
				</div>
				<div class="d-flex align-items-center">
					<span class="badge badge-light border px-2 py-1 mr-2 text-muted" title="Cálculos demostrativos sobre catálogo comercial">
						<i class="fa-solid fa-circle-info mr-1 text-primary"></i>Datos de ejemplo
					</span>
					<button type="button" class="btn btn-sm btn-outline-success" onclick="toastr.info('Presupuesto calculado en base a metas comerciales y órdenes de compra del período.');">
						<i class="fa-solid fa-rotate mr-1"></i>Conciliar saldos
					</button>
				</div>
			</div>
		</div>

		<!-- 4 KPI Cards de Presupuesto -->
		<div class="row mb-3">
			<div class="col-md-3 col-6 mb-2">
				<div class="card shadow-sm border-0 h-100 p-3" style="border-top: 3px solid #3b82f6 !important;">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="small font-weight-bold text-muted text-uppercase">Presupuesto Asignado</span>
						<i class="fa-solid fa-wallet text-primary"></i>
					</div>
					<h3 class="font-weight-bold text-dark mb-0">${{ number_format($budgetData['summary']['assigned'], 2) }}</h3>
					<small class="text-muted" title="{{ $budgetData['note'] ?? '' }}">Período {{ $budgetData['period'] }}</small>
				</div>
			</div>
			<div class="col-md-3 col-6 mb-2">
				<div class="card shadow-sm border-0 h-100 p-3" style="border-top: 3px solid #10b981 !important;">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="small font-weight-bold text-muted text-uppercase">Gasto Registrado</span>
						<i class="fa-solid fa-receipt text-success"></i>
					</div>
					<h3 class="font-weight-bold text-success mb-0">${{ number_format($budgetData['summary']['registered'], 2) }}</h3>
					<small class="text-muted">{{ ($budgetData['summary']['assigned'] > 0 ? round(($budgetData['summary']['registered'] / $budgetData['summary']['assigned']) * 100, 1) : 0) }}% facturado / recepcionado</small>
				</div>
			</div>
			<div class="col-md-3 col-6 mb-2">
				<div class="card shadow-sm border-0 h-100 p-3" style="border-top: 3px solid #f59e0b !important;">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="small font-weight-bold text-muted text-uppercase">Gasto Comprometido</span>
						<i class="fa-solid fa-truck-ramp-box text-warning"></i>
					</div>
					<h3 class="font-weight-bold text-warning mb-0">${{ number_format($budgetData['summary']['committed'], 2) }}</h3>
					<small class="text-muted">{{ ($budgetData['summary']['assigned'] > 0 ? round(($budgetData['summary']['committed'] / $budgetData['summary']['assigned']) * 100, 1) : 0) }}% OC en tránsito / contratos</small>
				</div>
			</div>
			<div class="col-md-3 col-6 mb-2">
				<div class="card shadow-sm border-0 h-100 p-3" style="border-top: 3px solid #8b5cf6 !important;">
					<div class="d-flex justify-content-between align-items-center mb-1">
						<span class="small font-weight-bold text-muted text-uppercase">Saldo Disponible</span>
						<i class="fa-solid fa-piggy-bank text-purple"></i>
					</div>
					<h3 class="font-weight-bold text-purple mb-0">${{ number_format($budgetData['summary']['available'], 2) }}</h3>
					<small class="text-muted">{{ ($budgetData['summary']['assigned'] > 0 ? round(($budgetData['summary']['available'] / $budgetData['summary']['assigned']) * 100, 1) : 0) }}% remanente para compras</small>
				</div>
			</div>
		</div>

		<!-- Nota Contable Antiduplicidad -->
		<div class="alert alert-secondary bg-white border shadow-sm mb-4 d-flex align-items-start" style="font-size: 13px;">
			<i class="fa-solid fa-shield-halved text-primary mr-2 mt-1"></i>
			<div>
				<strong>Regla de cálculo contable (Anti-duplicidad):</strong>
				El gasto registrado contempla facturas ingresadas o mercancías ya recepcionadas en bodega. El gasto comprometido refleja órdenes de compra emitidas pendientes de entrega o contratos fijos. Al registrarse la recepción física de una orden, su importe se descuenta del saldo comprometido y se suma al registrado, garantizando que ninguna orden se contabilice doblemente.
			</div>
		</div>

		<div class="row">
			<!-- Columna Izquierda: Desglose Jerárquico Categoría -> Sucursal -> Registro -->
			<div class="col-lg-7 mb-4">
				<div class="card shadow-sm border-0 h-100">
					<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
						<h6 class="font-weight-bold text-dark mb-0">
							<i class="fa-solid fa-folder-tree text-primary mr-2"></i>Desglose por Categoría y Sucursal
						</h6>
						<small class="text-muted">Clic para ver sucursales y documentos</small>
					</div>
					<div class="card-body p-0">
						<div class="accordion" id="budget-category-accordion">
							@foreach($budgetData['categories'] as $cIdx => $cat)
							<div class="border-bottom">
								<div class="p-3 d-flex flex-wrap justify-content-between align-items-center" style="cursor: pointer; background: #fff;" data-toggle="collapse" data-target="#cat-collapse-{{ $cIdx }}">
									<div class="d-flex align-items-center" style="min-width: 200px;">
										<i class="fa-solid {{ $cat['icon'] }} text-muted mr-2" style="width: 20px;"></i>
										<div>
											<strong class="text-dark">{{ $cat['name'] }}</strong>
											<div class="small text-muted">Asignado: ${{ number_format($cat['assigned'], 2) }}</div>
										</div>
									</div>
									<div class="d-flex align-items-center my-1 my-sm-0" style="gap: 15px;">
										<div class="text-right" style="min-width: 120px;">
											<div class="small font-weight-bold text-dark">
												${{ number_format($cat['registered'] + $cat['committed'], 2) }}
												<span class="text-muted font-weight-normal">({{ $cat['pct'] }}%)</span>
											</div>
											<div class="progress" style="height: 6px; width: 110px;">
												<div class="progress-bar bg-success" style="width: {{ ($cat['assigned'] > 0 ? min(100, round(($cat['registered'] / $cat['assigned']) * 100)) : 0) }}%" title="Registrado: ${{ number_format($cat['registered'], 2) }}"></div>
												<div class="progress-bar bg-warning" style="width: {{ ($cat['assigned'] > 0 ? min(100, round(($cat['committed'] / $cat['assigned']) * 100)) : 0) }}%" title="Comprometido: ${{ number_format($cat['committed'], 2) }}"></div>
											</div>
										</div>
										<span class="badge badge-light border text-muted">
											Disp: ${{ number_format($cat['available'], 2) }}
										</span>
										<i class="fa-solid fa-chevron-down text-muted small"></i>
									</div>
								</div>

								<!-- Detalle por Sucursal y Registros de la Categoría -->
								<div id="cat-collapse-{{ $cIdx }}" class="collapse {{ $cIdx === 0 ? 'show' : '' }}" data-parent="#budget-category-accordion">
									<div class="bg-light p-3 border-top">
										@foreach($cat['branches'] as $br)
										<div class="card border-0 shadow-xs bg-white mb-2">
											<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
												<strong class="small text-primary"><i class="fa-solid fa-store mr-1"></i>{{ $br['branch_name'] }}</strong>
												<span class="small text-muted">Asig: ${{ number_format($br['assigned'], 2) }} | Disp: <strong>${{ number_format($br['available'], 2) }}</strong></span>
											</div>
											<div class="table-responsive p-0">
												<table class="table table-sm table-hover mb-0 text-sm" style="font-size: 12px;">
													<thead class="thead-light">
														<tr>
															<th>Tipo</th>
															<th>Folio</th>
															<th>Proveedor</th>
															<th>Fecha</th>
															<th class="text-right">Monto</th>
															<th class="text-center">Estado</th>
														</tr>
													</thead>
													<tbody>
														@forelse($br['records'] as $rec)
														<tr>
															<td><span class="text-muted">{{ $rec['type'] }}</span></td>
															<td class="font-weight-bold font-mono">{{ str_replace('DEMO-', 'EJ-', $rec['code']) }}</td>
															<td class="text-truncate" style="max-width: 140px;">{{ $rec['supplier'] }}</td>
															<td class="text-muted">{{ $rec['date'] }}</td>
															<td class="text-right font-weight-bold">${{ number_format($rec['amount'], 2) }}</td>
															<td class="text-center">
																<span class="badge {{ $rec['status_class'] }}" style="font-size: 11px;">{{ $rec['status'] }}</span>
															</td>
														</tr>
														@empty
														<tr>
															<td><span class="text-muted">Orden Compra</span></td>
															<td class="font-weight-bold font-mono">PO-{{ date('Ymd') }}-B01</td>
															<td class="text-truncate" style="max-width: 140px;">Proveedor Preferente</td>
															<td class="text-muted">{{ date('Y-m-d') }}</td>
															<td class="text-right font-weight-bold">${{ number_format(max(150, $br['assigned'] * 0.12), 2) }}</td>
															<td class="text-center">
																<span class="badge badge-success" style="font-size: 11px;">Planificado</span>
															</td>
														</tr>
														@endforelse
													</tbody>
												</table>
											</div>
										</div>
										@endforeach
									</div>
								</div>
							</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>

			<!-- Columna Derecha: Calendario y Agenda de Próximos Compromisos -->
			<div class="col-lg-5 mb-4">
				<div class="card shadow-sm border-0 h-100">
					<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
						<h6 class="font-weight-bold text-dark mb-0">
							<i class="fa-regular fa-calendar-check text-success mr-2"></i>Próximos Compromisos y Calendario
						</h6>
						<span class="badge badge-success px-2 py-1">{{ count($budgetData['commitments']) }} programados</span>
					</div>
					<div class="card-body p-0">
						<div class="list-group list-group-flush">
							@foreach($budgetData['commitments'] as $com)
							<div class="list-group-item p-3 border-bottom">
								<div class="d-flex justify-content-between align-items-start mb-1">
									<div>
										<span class="badge badge-light border text-dark font-mono mr-1">
											<i class="fa-regular fa-calendar mr-1 text-primary"></i>{{ date('d M', strtotime($com['date'])) }}
										</span>
										<strong class="text-dark">{{ $com['concept'] }}</strong>
									</div>
									<span class="badge {{ $com['status_class'] }}">{{ $com['status'] }}</span>
								</div>
								<div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
									<div>
										<i class="fa-solid fa-truck mr-1"></i>{{ $com['supplier'] }}
										<span class="mx-1">·</span>
										<i class="fa-solid fa-user-tie mr-1"></i>{{ $com['responsible'] }}
									</div>
									<div class="text-right">
										<strong class="text-dark font-weight-bold mr-2" style="font-size: 14px;">${{ number_format($com['amount'], 2) }}</strong>
										<a href="{{ $U($com['href']) }}" class="btn btn-xs btn-outline-primary" title="Ver registro asociado">
											Ver <i class="fa-solid fa-arrow-right ml-1"></i>
										</a>
									</div>
								</div>
							</div>
							@endforeach
						</div>
					</div>
					<div class="card-footer bg-light py-2 text-center text-muted small">
						<i class="fa-solid fa-info-circle mr-1"></i>Los pagos y recepciones se sincronizan con Planificación MRP y Gestión de Equipos.
					</div>
				</div>
			</div>
		</div>
	</div>


	<!-- TAB 8: Conciliación de Facturas (3-Way Matching) -->
	<div class="tab-pane fade" id="tab-threeway-matching" role="tabpanel">
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">FACTURAS EN CONCILIACIÓN</div>
					<div class="h4 font-weight-bold text-dark mb-0">$58,400 <span class="small font-weight-normal text-muted">por liberar</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-file-invoice mr-1 text-primary"></i> 18 facturas del mes</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">COINCIDENCIA EXACTA (3-WAY)</div>
					<div class="h4 font-weight-bold text-success mb-0">88.5%</div>
					<div class="small text-muted mt-1">PO = Recepción = Factura</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">DISCREPANCIA DE PRECIO</div>
					<div class="h4 font-weight-bold text-warning mb-0">$1,250 <span class="small font-weight-normal text-muted">en disputa</span></div>
					<div class="small text-muted mt-1">Precio factura &gt; Precio PO</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">FACTURAS RETENIDAS</div>
					<div class="h4 font-weight-bold text-danger mb-0">2 facturas</div>
					<div class="small text-muted mt-1">Bloqueo de pago preventivo</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary mr-2"></i>Matriz 3-Way Matching (Orden de Compra vs Muelle vs Factura)</h5>
					<div class="small text-muted">Validación estricta de precios cotizados, cantidades efectivamente recibidas y montos cobrados por el proveedor.</div>
				</div>
				<div class="d-flex gap-2">
					<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
						<i class="fa-solid fa-shield-halved mr-1"></i> Escalar a Action Center
					</a>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Orden Compra (PO)</th>
							<th>Recepción Muelle (GR)</th>
							<th>Factura Proveedor</th>
							<th>Proveedor</th>
							<th class="text-right">Cant. PO</th>
							<th class="text-right">Cant. Recibida</th>
							<th class="text-right">Precio PO</th>
							<th class="text-right">Precio Factura</th>
							<th class="text-right">Total Factura</th>
							<th>Estado Matching</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>PO-2026-0810</code></td>
							<td><code>GR-449102</code></td>
							<td><strong>FAC-A-99124</strong></td>
							<td>Lácteos del Valle S.A.</td>
							<td class="text-right">150 unids</td>
							<td class="text-right font-weight-bold text-success">150 unids</td>
							<td class="text-right">$1.15</td>
							<td class="text-right">$1.15</td>
							<td class="text-right font-weight-bold">$172.50</td>
							<td><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Coincidencia 100%</span></td>
							<td class="text-center">
								<button class="btn btn-xs btn-outline-success font-weight-bold py-0 px-2" onclick="alert('Factura liberada para programación de pago.');">
									<i class="fa-solid fa-thumbs-up mr-1"></i> Liberar Pago
								</button>
							</td>
						</tr>
						<tr class="table-danger">
							<td><code>PO-2026-0811</code></td>
							<td><code>GR-449103</code></td>
							<td><strong>FAC-B-11029</strong></td>
							<td>Avícola Santa Rita</td>
							<td class="text-right">200 kg</td>
							<td class="text-right font-weight-bold text-danger">180 kg</td>
							<td class="text-right">$3.20</td>
							<td class="text-right font-weight-bold text-danger">$3.45</td>
							<td class="text-right font-weight-bold text-danger">$690.00</td>
							<td><span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sobreprecio y Faltante</span></td>
							<td class="text-center">
								<button class="btn btn-xs btn-danger font-weight-bold py-0 px-2" onclick="alert('Factura retenida. Notificación de discrepancia enviada a compras y contabilidad.');">
									<i class="fa-solid fa-hand mr-1"></i> Retener Pago
								</button>
							</td>
						</tr>
						<tr class="table-warning">
							<td><code>PO-2026-0812</code></td>
							<td><code>GR-449105</code></td>
							<td><strong>FAC-C-33812</strong></td>
							<td>Distribuidora Fruver SAS</td>
							<td class="text-right">400 kg</td>
							<td class="text-right font-weight-bold text-success">400 kg</td>
							<td class="text-right">$0.90</td>
							<td class="text-right font-weight-bold text-warning">$0.95</td>
							<td class="text-right font-weight-bold">$380.00</td>
							<td><span class="badge badge-warning text-dark"><i class="fa-solid fa-circle-exclamation mr-1"></i> Diferencia Centavos (+$20.00)</span></td>
							<td class="text-center">
								<button class="btn btn-xs btn-outline-warning font-weight-bold py-0 px-2" onclick="alert('Nota de débito generada automáticamente por $20.00.');">
									<i class="fa-solid fa-file-invoice mr-1"></i> Nota Débito
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- TAB 9: Gestión de Contratos -->
	<div class="tab-pane fade" id="tab-contracts" role="tabpanel">
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">CONTRATOS MARCO VIGENTES</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ count($supplierContracts ?? []) }} <span class="small font-weight-normal text-muted">contratos</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-file-signature text-success mr-1"></i> Cláusulas formalizadas</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">POR VENCER (&lt; 90 DÍAS)</div>
					<div class="h4 font-weight-bold text-warning mb-0">1 <span class="small font-weight-normal text-muted">contrato</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-clock mr-1 text-warning"></i> Avícola Santa Rita (Revisión)</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">TÉRMINO MEDIO DE CRÉDITO</div>
					<div class="h4 font-weight-bold text-info mb-0">30 días</div>
					<div class="small text-muted mt-1">Plazo de pago a proveedores</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">AHORRO POR REBATE</div>
					<div class="h4 font-weight-bold text-success mb-0">$6,400</div>
					<div class="small text-muted mt-1">Descuentos por pronto pago y volumen</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-file-signature text-warning mr-2"></i>Contratos de Proveedores y Condiciones Comerciales</h5>
					<div class="small text-muted">Términos de pago, pedido mínimo, descuentos pactados, cláusulas de renovación y alertas de caducidad.</div>
				</div>
				<div>
					<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Abriendo formulario de nuevo contrato comercial.');">
						<i class="fa-solid fa-plus mr-1"></i> Nuevo Contrato
					</button>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Código</th>
							<th>Proveedor</th>
							<th>Título / Objeto</th>
							<th>Vigencia</th>
							<th class="text-right">Plazo Pago</th>
							<th class="text-right">Pedido Mínimo</th>
							<th>Condiciones / Descuento</th>
							<th>Responsable</th>
							<th>Estado</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse(($supplierContracts ?? []) as $ctr)
						<tr class="@if($ctr['status'] === 'expiring_soon') table-warning @endif">
							<td><code>{{ $ctr['contract_code'] }}</code></td>
							<td class="font-weight-bold text-dark">{{ $ctr['supplier_name'] }}</td>
							<td>{{ $ctr['title'] }}</td>
							<td class="small text-muted">{{ date('d/m/Y', strtotime($ctr['start_date'])) }} al {{ date('d/m/Y', strtotime($ctr['end_date'])) }}</td>
							<td class="text-right font-weight-bold">{{ $ctr['payment_terms_days'] }} días</td>
							<td class="text-right">${{ number_format($ctr['minimum_order_val'], 2) }}</td>
							<td><span class="badge badge-light border">{{ $ctr['discount_terms'] }}</span></td>
							<td class="small">{{ $ctr['responsible_name'] }}</td>
							<td>
								@if($ctr['status'] === 'active')
									<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Vigente</span>
								@else
									<span class="badge badge-warning text-dark"><i class="fa-solid fa-clock mr-1"></i> Por Vencer</span>
								@endif
							</td>
							<td class="text-center">
								<button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="alert('Descargando contrato digitalizado en PDF...');">
									<i class="fa-solid fa-file-pdf text-danger"></i> PDF
								</button>
							</td>
						</tr>
						@empty
						<tr>
							<td><code>CTR-2026-ALQ01</code></td>
							<td class="font-weight-bold text-dark">Alquería Lácteos de Colombia</td>
							<td>Abastecimiento continuo de cadena fría y lácteos</td>
							<td class="small text-muted">{{ date('01/01/Y') }} al {{ date('31/12/Y') }}</td>
							<td class="text-right font-weight-bold">30 días</td>
							<td class="text-right">$5,000,000</td>
							<td><span class="badge badge-light border">Descuento 4.5% pronto pago</span></td>
							<td class="small">Jefe de Compras</td>
							<td><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Vigente</span></td>
							<td class="text-center"><button class="btn btn-xs btn-outline-secondary py-0 px-2"><i class="fa-solid fa-file-pdf text-danger"></i> PDF</button></td>
						</tr>
						<tr>
							<td><code>CTR-2026-SAM02</code></td>
							<td class="font-weight-bold text-dark">Samsung Electronics Colombia</td>
							<td>Distribución autorizada telefonía y televisores</td>
							<td class="small text-muted">{{ date('01/01/Y') }} al {{ date('31/12/Y') }}</td>
							<td class="text-right font-weight-bold">45 días</td>
							<td class="text-right">$25,000,000</td>
							<td><span class="badge badge-light border">Rebate trimestral 3.0%</span></td>
							<td class="small">Gerencia Comercial</td>
							<td><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i> Vigente</span></td>
							<td class="text-center"><button class="btn btn-xs btn-outline-secondary py-0 px-2"><i class="fa-solid fa-file-pdf text-danger"></i> PDF</button></td>
						</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- TAB 10: Portal de Proveedores B2B -->
	<div class="tab-pane fade" id="tab-supplier-portal" role="tabpanel">
		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-network-wired text-success mr-2"></i>Portal de Proveedores B2B (DAILY Vendor Portal)</h5>
				<div class="small text-muted">Arquitectura y acceso seguro para proveedores comerciales autorizados.</div>
			</div>
			<div class="card-body">
				<div class="alert alert-info">
					<i class="fa-solid fa-circle-info mr-2"></i>
					El <strong>Portal B2B de Proveedores</strong> permite a los aliados comerciales consultar sus órdenes de compra activas, agendar citas de descarga en muelle, cargar manifiestos ASN electrónicos y consultar el estado contable de sus facturas en tiempo real.
				</div>

				<div class="row">
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 bg-light">
							<div class="d-flex align-items-center mb-2">
								<div class="rounded-circle p-2 bg-primary text-white mr-2" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
									<i class="fa-solid fa-cart-shopping"></i>
								</div>
								<strong class="text-dark">1. Órdenes de Compra</strong>
							</div>
							<p class="small text-muted mb-2">Proveedores reciben órdenes en firme en formato EDI o PDF para iniciar alistamiento.</p>
							<div class="small font-weight-bold text-success"><i class="fa-solid fa-circle mr-1" style="font-size:8px;"></i> 14 órdenes enviadas este mes</div>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 bg-light">
							<div class="d-flex align-items-center mb-2">
								<div class="rounded-circle p-2 bg-warning text-dark mr-2" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
									<i class="fa-solid fa-calendar-check"></i>
								</div>
								<strong class="text-dark">2. Citas de Muelle (Slotting)</strong>
							</div>
							<p class="small text-muted mb-2">Reserva de horario de llegada en andén para evitar filas y cuellos de botella en recepción.</p>
							<div class="small font-weight-bold text-primary"><i class="fa-solid fa-truck mr-1"></i> 6 citas programadas para hoy</div>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="border rounded p-3 h-100 bg-light">
							<div class="d-flex align-items-center mb-2">
								<div class="rounded-circle p-2 bg-success text-white mr-2" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
									<i class="fa-solid fa-file-invoice"></i>
								</div>
								<strong class="text-dark">3. Carga ASN y Facturas</strong>
							</div>
							<p class="small text-muted mb-2">Carga automática de remisión electrónica y facturas fiscales XML/PDF para conciliación 3-Way.</p>
							<div class="small font-weight-bold text-info"><i class="fa-solid fa-cloud-arrow-up mr-1"></i> Carga B2B activa</div>
						</div>
					</div>
				</div>

				<div class="d-flex justify-content-between align-items-center p-3 border rounded bg-white mt-2">
					<div>
						<span class="font-weight-bold text-dark">URL del Portal de Acceso Proveedores:</span>
						<code class="ml-2">https://portal.dailyretail.com/b2b/suppliers</code>
					</div>
					<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" onclick="alert('Enlace de invitación generado con token seguro de autenticación.');">
						<i class="fa-solid fa-key mr-1"></i> Generar Invitación Proveedor
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: Nuevo / Editar Proveedor -->
<div class="modal fade" id="modal-supplier-form" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title" id="supplier-modal-title"><i class="fa-solid fa-truck mr-2"></i>Proveedor Comercial</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-supplier" onsubmit="DairySuppliers.SubmitSupplier(event)">
				<div class="modal-body">
					<input type="hidden" id="supplier-id" name="id" value="">
					<div class="row">
						<div class="col-md-8 form-group">
							<label class="font-weight-bold">Nombre o Razón Social *</label>
							<input type="text" class="form-control" id="supplier-name" name="name" required placeholder="Ej. Lácteos San Pedro S.A.">
						</div>
						<div class="col-md-4 form-group">
							<label class="font-weight-bold">RFC / Identificación Fiscal</label>
							<input type="text" class="form-control" id="supplier-tax-id" name="tax_id" placeholder="LSP-990101-XX1">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Contacto Principal</label>
							<input type="text" class="form-control" id="supplier-contact" name="contact_name" placeholder="Nombre del representante">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Teléfono</label>
							<input type="text" class="form-control" id="supplier-phone" name="phone" placeholder="+52 55 ...">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Correo Electrónico</label>
							<input type="email" class="form-control" id="supplier-email" name="email" placeholder="contacto@proveedor.com">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Dirección / Planta</label>
							<input type="text" class="form-control" id="supplier-address" name="address" placeholder="Parque industrial o bodega">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Lead Time (Días de entrega)</label>
							<input type="number" class="form-control" id="supplier-lead-time" name="lead_time_days" value="2" min="1" max="60">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Pedido Mínimo ($)</label>
							<input type="number" step="0.01" class="form-control" id="supplier-min-order" name="min_order_amount" value="0.00">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Condiciones de Pago</label>
							<input type="text" class="form-control" id="supplier-payment-terms" name="payment_terms" placeholder="p. ej. Crédito 15 días">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Calificación Inicial (1-5)</label>
							<input type="number" step="0.1" class="form-control" id="supplier-rating" name="rating" value="5.0" min="1" max="5">
						</div>
						<div class="col-md-3 form-group">
							<label class="font-weight-bold">Estado</label>
							<select class="custom-select" id="supplier-active" name="active">
								<option value="1">Activo</option>
								<option value="0">Inactivo</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Descripción y Notas</label>
						<textarea class="form-control" id="supplier-description" name="description" rows="2" placeholder="Flota refrigerada, requerimientos de entrega, etc."></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fa-solid fa-save mr-1"></i>Guardar Proveedor</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: Nueva Cotización / Precio -->
<div class="modal fade" id="modal-price-form" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title"><i class="fa-solid fa-tag mr-2"></i>Registrar Precio / Cotización</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-price" onsubmit="DairySuppliers.SubmitPrice(event)">
				<div class="modal-body">
					<div class="form-group">
						<label class="font-weight-bold">Proveedor *</label>
						<select class="custom-select" name="supplier_id" required>
							@foreach($suppliers as $s)
							<option value="{{ $s['id'] }}">{{ $s['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Producto Lácteo *</label>
						<select class="custom-select" name="product_id" required>
							@foreach($products as $p)
							<option value="{{ $p->id }}">{{ $p->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Precio Unitario ($) *</label>
							<input type="number" step="0.01" class="form-control font-weight-bold" name="price" required placeholder="0.00">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Pedido Mínimo (Unidades)</label>
							<input type="number" step="1" class="form-control" name="min_order_qty" value="10">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Días de Entrega</label>
							<input type="number" class="form-control" name="delivery_lead_days" value="2">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Vigente Desde</label>
							<input type="date" class="form-control" name="valid_from" value="{{ date('Y-m-d') }}">
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Condiciones / Notas</label>
						<input type="text" class="form-control" name="notes" placeholder="Descuento por volumen, incluye flete, etc.">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fa-solid fa-save mr-1"></i>Guardar Cotización</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: Reportar Incidencia -->
<div class="modal fade" id="modal-incident-form" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-warning text-dark">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Reportar Incidencia con Proveedor</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-incident" onsubmit="DairySuppliers.SubmitIncident(event)">
				<div class="modal-body">
					<div class="form-group">
						<label class="font-weight-bold">Proveedor Afectado *</label>
						<select class="custom-select" name="supplier_id" required>
							@foreach($suppliers as $s)
							<option value="{{ $s['id'] }}">{{ $s['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Producto Involucrado (Opcional)</label>
						<select class="custom-select" name="product_id">
							<option value="">-- No específico / Toda la orden --</option>
							@foreach($products as $p)
							<option value="{{ $p->id }}">{{ $p->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Tipo de Incidencia *</label>
							<select class="custom-select" name="incident_type" required>
								<option value="cold_chain_break">Ruptura cadena de frío</option>
								<option value="damaged_packaging">Empaque/Envase dañado</option>
								<option value="near_expiration">Fecha de caducidad corta</option>
								<option value="shortage">Faltante de producto</option>
								<option value="price_discrepancy">Discrepancia de precio</option>
								<option value="other">Otro</option>
							</select>
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Gravedad</label>
							<select class="custom-select" name="severity">
								<option value="low">Leve</option>
								<option value="medium" selected>Media</option>
								<option value="critical">Crítica</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Impacto Económico Estimado ($)</label>
							<input type="number" step="0.01" class="form-control" name="cost_impact" value="0.00">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold">Estado Inicial</label>
							<select class="custom-select" name="status">
								<option value="open">Abierta</option>
								<option value="claimed">En Reclamo Formal</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Descripción de lo Ocurrido *</label>
						<textarea class="form-control" name="description" rows="3" required placeholder="Detalla el problema, lote y evidencia encontrada..."></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-warning font-weight-bold"><i class="fa-solid fa-save mr-1"></i>Registrar Incidencia</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: Resolver Incidencia -->
<div class="modal fade" id="modal-resolve-incident" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-success text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-check-circle mr-2"></i>Resolver Incidencia Comercial</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-resolve-incident" onsubmit="DairySuppliers.SubmitResolveIncident(event)">
				<div class="modal-body">
					<input type="hidden" id="resolve-incident-id" name="id">
					<p id="resolve-incident-desc" class="text-muted"></p>
					<div class="form-group">
						<label class="font-weight-bold">Resolución / Acuerdo con Proveedor *</label>
						<textarea class="form-control" id="resolve-notes" name="resolution_notes" rows="3" required placeholder="Ej. Proveedor emitió nota de crédito por $XX o reenvió producto nuevo..."></textarea>
					</div>
					<div class="form-group">
						<label class="font-weight-bold">Costo Final Asumido ($)</label>
						<input type="number" step="0.01" class="form-control" id="resolve-cost" name="cost_impact" value="0.00">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i>Marcar Resuelta</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('pageScripts')
<script>
var DairySuppliers = {
	Init: function() {
		this.LoadComparison(30); // Cargar Leche entera 1 galón por defecto
	},

	FilterTable: function() {
		var query = $('#supplier-search-input').val().toLowerCase();
		var status = $('#supplier-filter-status').val();

		$('.supplier-row').each(function() {
			var name = $(this).data('name');
			var active = $(this).data('active');

			var matchesQuery = name.indexOf(query) !== -1;
			var matchesStatus = true;
			if (status === 'active' && active != 1) matchesStatus = false;
			if (status === 'inactive' && active != 0) matchesStatus = false;

			if (matchesQuery && matchesStatus) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	},

	OpenNewSupplierModal: function() {
		$('#form-supplier')[0].reset();
		$('#supplier-id').val('');
		$('#supplier-modal-title').html('<i class="fa-solid fa-plus mr-2"></i>Nuevo Proveedor');
	},

	EditSupplier: function(s) {
		$('#supplier-id').val(s.id);
		$('#supplier-name').val(s.name);
		$('#supplier-tax-id').val(s.tax_id || '');
		$('#supplier-contact').val(s.contact_name || '');
		$('#supplier-phone').val(s.phone || '');
		$('#supplier-email').val(s.email || '');
		$('#supplier-address').val(s.address || '');
		$('#supplier-lead-time').val(s.lead_time_days || 2);
		$('#supplier-min-order').val(s.min_order_amount || 0);
		$('#supplier-payment-terms').val(s.payment_terms || '');
		$('#supplier-rating').val(s.rating || 5);
		$('#supplier-active').val(s.active);
		$('#supplier-description').val(s.description || '');

		$('#supplier-modal-title').html('<i class="fa-solid fa-pen mr-2"></i>Editar Proveedor: ' + s.name);
		$('#modal-supplier-form').modal('show');
	},

	ToggleActive: function(id, newState) {
		var action = newState === 1 ? 'activar' : 'desactivar';
		bootbox.confirm('¿Deseas ' + action + ' este proveedor?', function(ok) {
			if (!ok) return;

			Grocy.FrontendHelpers.BeginUiBusy();
			fetch(Grocy.BaseUrl + '/api/suppliers/' + id + '/toggle-active', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ active: newState })
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				Grocy.FrontendHelpers.EndUiBusy();
				if (res.success) {
					toastr.success(res.message);
					window.location.reload();
				} else {
					toastr.error(res.error_message || 'Error al cambiar estado');
				}
			})
			.catch(function() {
				Grocy.FrontendHelpers.EndUiBusy();
				toastr.error('Error de conexión');
			});
		});
	},

	SubmitSupplier: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/suppliers', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-supplier-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al guardar');
			}
		})
		.catch(function() {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error('Error de red al guardar proveedor');
		});
	},

	LoadComparison: function(productId) {
		if (!productId) {
			$('#comparison-cards-row').html('<div class="col-12 text-center py-4 text-muted">Selecciona un producto para comparar.</div>');
			return;
		}

		$('#comparison-cards-row').html('<div class="col-12 text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i><br>Consultando cotizaciones de proveedores...</div>');

		fetch(Grocy.BaseUrl + '/api/procurement/comparison/' + productId)
		.then(function(r) { return r.json(); })
		.then(function(options) {
			if (!options || options.length === 0) {
				$('#comparison-cards-row').html('<div class="col-12 text-center py-4 text-muted"><i class="fa-solid fa-info-circle fa-2x mb-2 d-block"></i>No hay cotizaciones registradas para este producto. Registra una con el botón "+ Cotización".</div>');
				return;
			}

			var html = '';
			options.forEach(function(opt) {
				var badgeHtml = '';
				if (opt.is_best_price) {
					badgeHtml += '<span class="badge badge-success px-2 py-1 mr-1"><i class="fa-solid fa-trophy mr-1"></i>Mejor Precio</span>';
				}
				if (opt.is_fastest_delivery) {
					badgeHtml += '<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-bolt mr-1"></i>Entrega Más Rápida</span>';
				}

				html += '<div class="col-lg-4 col-md-6 mb-3">' +
					'<div class="card shadow-sm h-100 border ' + (opt.is_best_price ? 'border-success' : '') + '">' +
						'<div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">' +
							'<h6 class="mb-0 font-weight-bold text-dark">' + opt.supplier_name + '</h6>' +
							'<div>' + badgeHtml + '</div>' +
						'</div>' +
						'<div class="card-body">' +
							'<div class="text-center my-2">' +
								'<span class="text-muted small">Costo por unidad</span>' +
								'<h2 class="font-weight-bold text-primary mb-0">$' + parseFloat(opt.price).toFixed(2) + '</h2>' +
							'</div>' +
							'<hr class="my-2">' +
							'<ul class="list-unstyled small mb-3">' +
								'<li class="mb-1"><strong><i class="fa-solid fa-truck mr-1 text-muted"></i>Tiempo de Entrega:</strong> ' + opt.delivery_lead_days + ' días</li>' +
								'<li class="mb-1"><strong><i class="fa-solid fa-boxes-stacked mr-1 text-muted"></i>Pedido Mínimo:</strong> ' + opt.min_order_qty + ' unidades</li>' +
								'<li class="mb-1"><strong><i class="fa-solid fa-credit-card mr-1 text-muted"></i>Condiciones:</strong> ' + (opt.payment_terms || 'Sin definir') + '</li>' +
								'<li class="mb-1"><strong><i class="fa-solid fa-star text-warning mr-1"></i>Calificación:</strong> ' + (opt.supplier_rating != null ? opt.supplier_rating + ' / 5.0' : 'Sin calificar') + '</li>' +
								'<li class="mb-1"><strong><i class="fa-solid fa-chart-line text-success mr-1"></i>Puntualidad OTIF:</strong> ' + (opt.otif_percentage != null ? opt.otif_percentage + '%' : 'sin entregas registradas') + '</li>' + '<li class="mb-1"><strong><i class="fa-solid fa-ranking-star text-primary mr-1"></i>Puntaje:</strong> ' + opt.score + ' / 100 <small class="text-muted">(' + (opt.score_basis || []).join(', ') + ')</small></li>' +
							'</ul>' +
							'<button class="btn btn-outline-primary btn-block btn-sm" onclick="DairySuppliers.SelectForProcurement(' + opt.supplier_id + ', ' + productId + ', ' + opt.price + ')">' +
								'<i class="fa-solid fa-check mr-1"></i>Seleccionar para Compra' +
							'</button>' +
						'</div>' +
					'</div>' +
				'</div>';
			});

			$('#comparison-cards-row').html(html);
		});
	},

	SubmitPrice: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/procurement/prices', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-price-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al guardar');
			}
		});
	},

	SubmitIncident: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/procurement/incidents', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-incident-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al guardar');
			}
		});
	},

	OpenResolveModal: function(inc) {
		$('#resolve-incident-id').val(inc.id);
		$('#resolve-incident-desc').text('Incidencia #' + inc.id + ' (' + inc.supplier_name + '): ' + inc.description);
		$('#resolve-cost').val(inc.cost_impact || 0);
		$('#modal-resolve-incident').modal('show');
	},

	SubmitResolveIncident: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/procurement/incidents', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success('Incidencia resuelta satisfactoriamente');
				$('#modal-resolve-incident').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error');
			}
		});
	},

	QuickCreateOrder: function(rec) {
		bootbox.confirm('¿Generar orden de compra borrador por ' + rec.recommended_order_qty + ' ' + rec.qu_name + ' de ' + rec.product_name + ' con el proveedor ' + rec.suggested_supplier_name + '?', function(ok) {
			if (!ok) return;
			// Orden de compra real (procurement_orders): se aprueba, envía y recibe en Planificación MRP
			Grocy.FrontendHelpers.BeginUiBusy();
			fetch(Grocy.BaseUrl + '/api/mrp/order/create', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					supplier_id: rec.suggested_supplier_id,
					branch_id: 1,
					items: [{ product_id: rec.product_id, amount: rec.recommended_order_qty, unit_price: rec.unit_price }]
				})
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				Grocy.FrontendHelpers.EndUiBusy();
				if (res.error_message) {
					toastr.error(res.error_message);
					return;
				}
				toastr.success((res.message || 'Orden creada') + '. Apruébela, envíela y recíbala en Planificación MRP.');
				window.location.reload();
			})
			.catch(function() {
				Grocy.FrontendHelpers.EndUiBusy();
				toastr.error('Error de conexión');
			});
		});
	},

	SelectForProcurement: function(supplierId, productId, price) {
		toastr.info('Proveedor seleccionado como fuente preferente de abastecimiento.');
		$('#tab-recommendations-link').tab('show');
	}
};

$(document).ready(function() {
	DairySuppliers.Init();
	$('#supplier-search-input').on('keyup', function() {
		DairySuppliers.FilterTable();
	});
});
</script>
@endpush
