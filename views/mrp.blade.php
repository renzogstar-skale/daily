@extends('layout.default')

@section('title', 'Planificación MRP')

@section('content')
@include('components.alert_strip', ['alertCategories' => ['purchasing']])
@php
	$sum = $mrp['summary'];
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
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-diagram-project"></i></div>
		<div>
			<h4 class="dairy-page-title">Planificación MRP</h4>
			<p class="dairy-page-subtitle">Reposición por punto de pedido con pronóstico, disponible neto FEFO y tope por vida útil para prevenir merma y sobreinventario.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right dairy-page-actions">
		<form method="get" class="d-inline-flex align-items-center mr-2">
			@if($selectedBranchId)<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">@endif
			<label class="small text-muted mb-0 mr-1" for="mrp-horizon">Horizonte</label>
			<select id="mrp-horizon" name="horizon_days" class="custom-select custom-select-sm" onchange="this.form.submit()">
				@foreach([7, 14, 21, 30] as $h)
				<option value="{{ $h }}" @if($horizonDays == $h) selected @endif>{{ $h }} días</option>
				@endforeach
			</select>
		</form>
		@if($canManage)
		<button id="mrp-receiving-rules" class="btn btn-outline-secondary mr-1" title="Vida útil remanente mínima y rango de temperatura aceptados en la recepción">
			<i class="fa-solid fa-clipboard-check mr-1"></i>Reglas de recepción
		</button>
		<button id="mrp-generate-orders" class="btn btn-primary" @if($sum['reorder_needed_count'] == 0) disabled @endif>
			<i class="fa-solid fa-file-invoice-dollar mr-1"></i>Generar órdenes
		</button>
		@endif
		@include('components.dairy_export')
	</div>
</div>

@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'A Reponer', 'value' => $sum['reorder_needed_count'], 'sub' => 'bajo punto de pedido', 'icon' => 'fa-cart-plus', 'tone' => $sum['reorder_needed_count'] > 0 ? 'danger' : 'success', 'alert' => $sum['reorder_needed_count'] > 0],
	['label' => 'Costo Sugerido', 'value' => '$' . number_format($sum['total_suggested_order_cost'], 2), 'sub' => 'órdenes propuestas', 'icon' => 'fa-sack-dollar', 'tone' => 'primary'],
	['label' => 'Sobreinventario', 'value' => $sum['overstock_risk_count'], 'sub' => 'cobertura > 80% vida útil', 'icon' => 'fa-boxes-stacked', 'tone' => $sum['overstock_risk_count'] > 0 ? 'warning' : 'success', 'alert' => $sum['overstock_risk_count'] > 0],
	['label' => 'Merma Proyectada', 'value' => '$' . number_format($sum['projected_spoilage_cost'], 2), 'sub' => $spoilageItems . ' productos en ' . $horizonDays . ' días', 'icon' => 'fa-hourglass-end', 'tone' => $sum['projected_spoilage_cost'] > 0 ? 'danger' : 'success', 'alert' => $sum['projected_spoilage_cost'] > 0, 'href' => $U('/waste') . '#prevencion'],
	['label' => 'Sin Historial', 'value' => $sum['no_history_count'], 'sub' => 'repone solo a mínimo', 'icon' => 'fa-chart-line', 'tone' => 'secondary'],
	['label' => 'Con Advertencias', 'value' => $withWarnings, 'sub' => 'datos maestros incompletos', 'icon' => 'fa-triangle-exclamation', 'tone' => $withWarnings > 0 ? 'warning' : 'success'],
]])

{{-- Precisión del pronóstico (backtest del mismo método) y quiebres actuales --}}
<div class="card mb-3">
	<div class="card-body py-2 d-flex flex-wrap align-items-center dairy-gap-3">
		<div class="mr-3"><span class="small text-muted text-uppercase font-weight-bold">Pronóstico · {{ $forecast['weeks'] }} sem.</span></div>
		<div class="mr-3"><span class="small text-muted">WAPE</span> <strong class="@if(($forecast['wape_pct'] ?? 0) > 50) text-danger @elseif(($forecast['wape_pct'] ?? 0) > 30) text-warning @else text-success @endif">{{ $forecast['wape_pct'] !== null ? $forecast['wape_pct'] . '%' : '—' }}</strong></div>
		<div class="mr-3"><span class="small text-muted">Sesgo</span> <strong>{{ $forecast['bias_pct'] !== null ? ($forecast['bias_pct'] > 0 ? '+' : '') . $forecast['bias_pct'] . '%' : '—' }}</strong> <span class="small text-muted">@if(($forecast['bias_pct'] ?? 0) > 5) sobrepronóstico (riesgo de merma) @elseif(($forecast['bias_pct'] ?? 0) < -5) subpronóstico (riesgo de quiebre) @endif</span></div>
		<div class="mr-3"><span class="small text-muted">Quiebres actuales</span> <strong class="@if(count($forecast['stockouts'])) text-danger @endif">{{ count($forecast['stockouts']) }}</strong>@if(count($forecast['stockouts']))<span class="small text-muted"> · {{ implode(', ', array_slice(array_column($forecast['stockouts'], 'product_name'), 0, 3)) }}{{ count($forecast['stockouts']) > 3 ? '…' : '' }}</span>@endif</div>
		@php $fo = $mrp['forecast'] ?? null; @endphp
		@if($fo)
		<div class="mr-3" id="mrp-forecast-adj"><span class="small text-muted">Ajustes del pronóstico de ventas</span>
			<strong>{{ $fo['correction']['applied'] ? 'corrección ×' . $fo['correction']['factor'] : 'sin corrección' }}</strong>
			<span class="small text-muted">· {{ $fo['seasonality']['applied'] ? 'estacionalidad ×' . $fo['seasonality']['factor'] : 'sin estacionalidad anual' }}
			· {{ count($fo['events']) ? count($fo['events']) . ' evento(s): ' . implode(', ', array_map(fn($e) => $e['name'] . ' ' . $e['date'] . ' ×' . $e['sales_factor'], array_slice($fo['events'], 0, 3))) : 'sin eventos' }}</span>
		</div>
		@endif
		<div class="small text-muted ml-auto">{{ $forecast['actual_units'] > 0 ? 'Venta real evaluada: ' . $forecast['actual_units'] . ' u.' : 'Sin ventas suficientes para evaluar' }}</div>
	</div>
</div>

@php
	$countBy = fn($st) => count(array_filter($orders, fn($o) => in_array($o->status, (array) $st)));
@endphp
@include('components.dairy_process_steps', ['title' => 'Ciclo de compra paso a paso', 'steps' => [
	['Revisar necesidades', $sum['reorder_needed_count'] . ' productos a reponer', $sum['reorder_needed_count'] > 0 ? 'current' : 'done'],
	['Generar órdenes', 'una por proveedor', 'pending'],
	['Aprobar', $countBy('draft') . ' en borrador', $countBy('draft') > 0 ? 'current' : 'pending'],
	['Enviar al proveedor', $countBy('approved') . ' aprobadas sin enviar', $countBy('approved') > 0 ? 'current' : 'pending'],
	['Recibir e inspeccionar', $countBy(['sent', 'partially_received']) . ' por recibir', $countBy(['sent', 'partially_received']) > 0 ? 'current' : 'pending'],
	['Gestionar rechazos', 'incidencias en Proveedores', 'pending'],
]])

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-table-list text-primary mr-2"></i>Lista de necesidades</span>
		<span class="text-muted small">Nivel de servicio {{ round($mrp['service_level'] * 100) }}% · generado {{ $mrp['generated_at'] }}</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table id="mrp-table" class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Producto</th>
						<th>Estado</th>
						<th class="text-right" title="Stock total en la sucursal">Stock</th>
						<th class="text-right" title="Vencido + cuarentena + merma proyectada FEFO">No disp.</th>
						<th class="text-right" title="Órdenes MRP en borrador o aprobadas">En orden</th>
						<th class="text-right" title="Utilizable + en orden">Disp. neto</th>
						<th class="text-right" title="Venta media diaria ponderada (4 semanas)">VMD</th>
						<th class="text-right" title="z · σ · √LT, mínimo el stock mínimo">SS</th>
						<th class="text-right" title="Punto de pedido = VMD · LT + SS">ROP</th>
						<th class="text-right" title="Días de cobertura del stock utilizable">Cobertura</th>
						<th class="text-right">Pedido</th>
						<th class="text-right">Costo</th>
						<th>Proveedor</th>
					</tr>
				</thead>
				<tbody>
					@foreach($items as $i)
					@php $meta = $statusMeta[$i['status']]; $unavailable = $i['expired_stock'] + $i['quarantine_stock'] + $i['projected_spoilage']; @endphp
					<tr>
						<td>
							<span class="font-weight-600 text-dark">{{ $i['product_name'] }}</span>
							<div class="text-muted small">{{ $i['category_name'] }} · LT {{ $i['lead_time_days'] }} d @if($i['best_before_days'] > 0)· vida útil {{ $i['best_before_days'] }} d @endif</div>
							@foreach($i['warnings'] as $w)
							<div class="small text-warning"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $w }}</div>
							@endforeach
						</td>
						<td><span class="badge badge-{{ $meta[1] }}">{{ $meta[0] }}</span></td>
						<td class="text-right">{{ $i['current_stock'] }}</td>
						<td class="text-right @if($unavailable > 0) text-danger font-weight-600 @endif" title="Vencido {{ $i['expired_stock'] }} · cuarentena {{ $i['quarantine_stock'] }} · merma proyectada {{ $i['projected_spoilage'] }} (${{ number_format($i['projected_spoilage_cost'], 2) }})">{{ round($unavailable, 2) }}</td>
						<td class="text-right">{{ $i['open_receipts'] }}</td>
						<td class="text-right font-weight-600">{{ $i['net_available'] }}</td>
						<td class="text-right">{{ $i['vmd'] }}@if(!empty($i['seasonal']))<i class="fa-solid fa-wave-square text-info ml-1" title="Demanda con patrón semanal (índice Do–Sá: {{ implode(' · ', $i['dow_index']) }}); el punto de pedido y la sugerencia lo aplican"></i>@endif</td>
						<td class="text-right">{{ $i['safety_stock'] }}</td>
						<td class="text-right">{{ $i['rop'] }}</td>
						<td class="text-right">{{ $i['status'] === 'no_history' ? '—' : $i['days_coverage'] . ' d' }}</td>
						<td class="text-right font-weight-700 @if($i['suggested_order'] > 0) text-primary @endif">{{ $i['suggested_order'] > 0 ? $i['suggested_order'] . ' ' . $i['qu_name'] : '—' }}</td>
						<td class="text-right">{{ $i['estimated_cost'] > 0 ? '$' . number_format($i['estimated_cost'], 2) : '—' }}</td>
						<td class="small">{{ $i['supplier_name'] }}</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-file-invoice text-primary mr-2"></i>Órdenes de compra</span>
		<span class="text-muted small ml-2">Borrador → Aprobada → Enviada → Recibida. Lo pendiente de recibir cuenta como entrada planificada en el MRP.</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Orden</th>
						<th>Proveedor</th>
						<th>Sucursal</th>
						<th class="text-right">Líneas</th>
						<th class="text-right">Costo estimado</th>
						<th>Estado</th>
						<th>Creada</th>
						<th>Aprobó</th>
						<th title="Fecha de entrega comprometida (base del OTIF)">Entrega</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@forelse($orders as $o)
					@php $om = $orderStatusMeta[$o->status] ?? [$o->status, 'secondary']; @endphp
					<tr>
						<td class="font-mono font-weight-600">{{ $o->order_code }}</td>
						<td>{{ $o->supplier_name }}</td>
						<td>{{ $o->branch_name }}</td>
						<td class="text-right">{{ $o->item_count }}</td>
						<td class="text-right">${{ number_format($o->total_estimated_cost, 2) }}</td>
						<td><span class="badge badge-{{ $om[1] }}">{{ $om[0] }}</span></td>
						<td class="small">{{ $o->created_at }}</td>
						<td class="small">{{ $o->approved_by_name ?? '—' }}</td>
						<td class="small">{{ $o->expected_delivery_date ?? '—' }}</td>
						<td class="text-right text-nowrap">
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
							<button class="btn btn-xs btn-outline-dark mrp-invoice-order" data-order-id="{{ $o->id }}" title="Registrar factura del proveedor y conciliar con la OC y lo recibido"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>Factura</button>
							@endif
							<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/order/' . $o->id) }}" title="Documento imprimible"><i class="fa-solid fa-print"></i></a>
						</td>
					</tr>
					@empty
					<tr><td colspan="10" class="text-center text-muted py-3">Sin órdenes generadas.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-truck-ramp-box text-success mr-2"></i>Recepciones de mercancía</span>
		<span class="text-muted small ml-2">Lo aceptado entra al stock como compra; lo rechazado abre una incidencia con el proveedor.</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Recepción</th>
						<th>Orden</th>
						<th>Proveedor</th>
						<th>Doc. proveedor</th>
						<th class="text-right">Temp.</th>
						<th class="text-right">Ingresado</th>
						<th class="text-right">Rechazado</th>
						<th>Recibió</th>
						<th>Fecha</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@forelse($receipts as $r)
					<tr>
						<td class="font-mono font-weight-600">{{ $r['receipt_code'] }}</td>
						<td class="font-mono">{{ $r['order_code'] }}</td>
						<td>{{ $r['supplier_name'] }}</td>
						<td class="small">{{ $r['supplier_document'] ?? '—' }}</td>
						<td class="text-right">{{ $r['temperature_on_arrival'] !== null ? $r['temperature_on_arrival'] . ' °C' : '—' }}</td>
						<td class="text-right">${{ number_format($r['total_accepted_cost'], 2) }}</td>
						<td class="text-right @if($r['total_rejected_cost'] > 0) text-danger font-weight-600 @endif">{{ $r['total_rejected_cost'] > 0 ? '$' . number_format($r['total_rejected_cost'], 2) . ' (' . $r['rejected_lines'] . ' lín.)' : '—' }}</td>
						<td class="small">{{ $r['received_by_name'] }}</td>
						<td class="small">{{ $r['received_at'] }}</td>
						<td class="text-right text-nowrap">
							<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/receipt/' . $r['id']) }}" title="Documento imprimible"><i class="fa-solid fa-print"></i></a>
							@if($r['stock_transaction_id'])
							<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/stockjournal') }}" title="Transacción {{ $r['stock_transaction_id'] }} en el diario de stock"><i class="fa-solid fa-book"></i></a>
							@endif
						</td>
					</tr>
					@empty
					<tr><td colspan="10" class="text-center text-muted py-3">Sin recepciones registradas.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

@if($canManage)
@php $invMeta = \Grocy\Services\SupplierInvoiceService::STATUS; @endphp
<div class="card mb-3" id="invoices">
	<div class="card-header">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary mr-2"></i>Facturas de proveedor · conciliación de 3 vías</span>
		<span class="text-muted small ml-2">Cada línea se compara con la orden (cantidad y precio) y con lo recibido. Tolerancia de precio: {{ GROCY_INVOICE_PRICE_TOLERANCE_PCT }} %.</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Folio</th>
						<th>Factura</th>
						<th>Orden</th>
						<th>Proveedor</th>
						<th>Fecha</th>
						<th class="text-right">Total</th>
						<th class="text-right" title="Cobrado sin recibir + sobreprecio">En riesgo</th>
						<th>Estado</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@forelse($invoices as $inv)
					@php $im = $invMeta[$inv['status']] ?? [$inv['status'], 'secondary']; @endphp
					<tr>
						<td class="font-mono font-weight-600">{{ $inv['invoice_code'] }}</td>
						<td>{{ $inv['invoice_number'] }}</td>
						<td class="font-mono">{{ $inv['order_code'] }}</td>
						<td>{{ $inv['supplier_name'] }}</td>
						<td class="small">{{ $inv['invoice_date'] }}</td>
						<td class="text-right">${{ number_format($inv['total'], 2) }}</td>
						<td class="text-right @if($inv['amount_at_risk'] > 0) text-danger font-weight-600 @endif">{{ $inv['amount_at_risk'] > 0 ? '$' . number_format($inv['amount_at_risk'], 2) : '—' }}</td>
						<td><span class="badge badge-{{ $im[1] }}">{{ $im[0] }}</span>@if($inv['decision_reason'])<div class="small text-muted">{{ $inv['decision_reason'] }}</div>@endif</td>
						<td class="text-right text-nowrap">
							<button class="btn btn-xs btn-outline-secondary mrp-invoice-detail" data-invoice-id="{{ $inv['id'] }}" data-code="{{ $inv['invoice_code'] }}" data-pending="{{ $inv['status'] === 'discrepancy' ? 1 : 0 }}">Detalle</button>
							<a class="btn btn-xs btn-outline-secondary" href="{{ $U('/document/invoice/' . $inv['id']) }}" target="_blank" title="Documento imprimible"><i class="fa-solid fa-print"></i></a>
						</td>
					</tr>
					@empty
					<tr><td colspan="9" class="text-center text-muted py-3">Sin facturas registradas. Use "Factura" en una orden con recepción.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="modal fade" id="mrp-invoice-modal" tabindex="-1">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Registrar factura · <span id="mrp-invoice-code" class="font-mono"></span></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="form-row">
					<div class="form-group col-md-4"><label>N.º factura del proveedor</label><input type="text" class="form-control" id="mrp-invoice-number"></div>
					<div class="form-group col-md-3"><label>Fecha</label><input type="date" class="form-control" id="mrp-invoice-date"></div>
					<div class="form-group col-md-2"><label>Impuestos</label><input type="number" min="0" step="0.01" class="form-control" id="mrp-invoice-tax" value="0"></div>
					<div class="form-group col-md-3"><label>Nota</label><input type="text" class="form-control" id="mrp-invoice-notes"></div>
				</div>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Producto</th><th class="text-right">Pedido</th><th class="text-right">Recibido</th><th class="text-right">Precio OC</th><th style="width:120px">Cant. facturada</th><th style="width:130px">Precio factura</th></tr></thead>
						<tbody id="mrp-invoice-lines"></tbody>
					</table>
				</div>
				<div class="small text-muted mt-2">Se precargan lo recibido y el precio de la orden; escriba lo que dice la factura. Las diferencias se calculan al guardar.</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="mrp-invoice-save">Registrar y conciliar</button>
			</div>
		</div>
	</div>
</div>
@endif

<div class="modal fade" id="mrp-receive-modal" tabindex="-1">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa-solid fa-truck-ramp-box mr-2"></i>Recepción contra orden <span id="mrp-receive-code" class="font-mono"></span></h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="mrp-receive-form">
				<div class="modal-body">
					<div class="form-row">
						<div class="form-group col-md-4">
							<label class="small font-weight-600">Proveedor → sucursal</label>
							<input type="text" class="form-control form-control-sm" id="mrp-receive-supplier" readonly>
						</div>
						<div class="form-group col-md-3">
							<label class="small font-weight-600" for="mrp-receive-doc">Guía / factura del proveedor</label>
							<input type="text" class="form-control form-control-sm" id="mrp-receive-doc">
						</div>
						<div class="form-group col-md-2">
							<label class="small font-weight-600" for="mrp-receive-temp">Temperatura (°C)</label>
							<input type="number" step="0.1" class="form-control form-control-sm" id="mrp-receive-temp">
						</div>
						<div class="form-group col-md-3">
							<label class="small font-weight-600" for="mrp-receive-notes">Notas</label>
							<input type="text" class="form-control form-control-sm" id="mrp-receive-notes">
						</div>
					</div>
					<div class="table-responsive">
						<table class="table table-sm mb-0">
							<thead>
								<tr>
									<th>Producto</th>
									<th class="text-right">Pendiente</th>
									<th>Entregado</th>
									<th>Rechazado</th>
									<th>Motivo rechazo</th>
									<th>Vence</th>
									<th>Lote proveedor</th>
									<th>Ubicación</th>
									<th>Costo unit.</th>
								</tr>
							</thead>
							<tbody id="mrp-receive-lines"></tbody>
						</table>
					</div>
					<p class="small text-muted mb-0 mt-2">La línea se rechaza automáticamente si la vida útil remanente es menor al mínimo o la temperatura está fuera de rango (ver Reglas de recepción).</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-success"><i class="fa-solid fa-check mr-1"></i>Contabilizar recepción</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="mrp-rules-modal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fa-solid fa-clipboard-check mr-2"></i>Reglas de recepción por producto</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<p class="small text-muted">Sin regla de vida útil se exige el {{ round((defined('GROCY_RECEIVING_MIN_SHELF_LIFE_PCT') ? GROCY_RECEIVING_MIN_SHELF_LIFE_PCT : 0.5) * 100) }}% de la vida útil por defecto del producto. Deje vacío para no controlar.</p>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th>Producto</th>
								<th class="text-right">Vida útil</th>
								<th>Mín. remanente (d)</th>
								<th>Temp. mín. (°C)</th>
								<th>Temp. máx. (°C)</th>
								<th></th>
							</tr>
						</thead>
						<tbody id="mrp-rules-lines"></tbody>
					</table>
				</div>
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

	$('#mrp-generate-orders').on('click', function() {
		bootbox.confirm('¿Generar órdenes borrador (una por proveedor) con las cantidades sugeridas?', function(ok) {
			if (ok) mrpPost('/mrp/orders/generate', { branch_id: @json($selectedBranchId) }, 'Órdenes generadas');
		});
	});

	$('.mrp-approve-order').on('click', function() {
		var id = $(this).data('order-id');
		bootbox.confirm('¿Aprobar la orden de reabastecimiento?', function(ok) {
			if (ok) mrpPost('/mrp/order/' + id + '/approve', {}, 'Orden aprobada');
		});
	});

	function mrpEsc(v) {
		return $('<div>').text(v === null || v === undefined ? '' : v).html();
	}

	function mrpFail(xhr) {
		toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || xhr.statusText);
	}

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
			title: cancel ? 'Motivo de la cancelación' : 'Cerrar con faltante (lo pendiente deja de esperarse). Motivo',
			callback: function(reason) {
				if (reason === null) return;
				if (!reason.trim()) { toastr.warning('Indique el motivo'); return false; }
				mrpPost('/mrp/order/' + id + '/close', { reason: reason }, cancel ? 'Orden cancelada' : 'Orden cerrada');
			}
		});
	});

	// ---------- Facturas de proveedor (conciliación de 3 vías) ----------
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
		$.ajax({ url: Grocy.BaseUrl + '/api/mrp/order/' + mrpInvoiceOrderId + '/invoice', method: 'POST', contentType: 'application/json',
			data: JSON.stringify({ invoice_number: $('#mrp-invoice-number').val(), invoice_date: $('#mrp-invoice-date').val(), tax_amount: $('#mrp-invoice-tax').val(), notes: $('#mrp-invoice-notes').val(), items: items })
		}).done(function(r) {
			if (r.status === 'matched') { toastr.success(r.invoice_code + ' conciliada sin diferencias'); }
			else { toastr.warning(r.invoice_code + ' con diferencias: $' + Number(r.amount_at_risk).toFixed(2) + ' en riesgo. Debe aprobarla o rechazarla otro usuario.'); }
			setTimeout(function() { location.hash = 'invoices'; location.reload(); }, 900);
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
					bootbox.prompt({ title: approve ? 'Aprobar pagando la diferencia. Motivo' : 'Rechazar la factura (reclamar al proveedor). Motivo', callback: function(reason) {
						if (reason === null) return;
						if (!reason.trim()) { toastr.warning('Indique el motivo'); return false; }
						mrpPost('/mrp/invoices/' + id + '/decide', { approve: approve, reason: reason }, approve ? 'Factura aprobada con diferencia' : 'Factura rechazada');
					} });
				};
				buttons.reject = { label: 'Rechazar', className: 'btn-outline-danger', callback: function() { decide(false); } };
				buttons.approve = { label: 'Aprobar con diferencia', className: 'btn-warning', callback: function() { decide(true); } };
			}
			bootbox.dialog({ title: 'Conciliación ' + mrpEsc(code), message: html, size: 'extra-large', buttons: buttons });
		}).fail(mrpFail);
	});
	if (location.hash === '#invoices' && document.getElementById('invoices')) { document.getElementById('invoices').scrollIntoView(); }

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
		}, 'Recepción registrada');
	});

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
			toastr.success((res && res.message) || 'Guardado');
		}).fail(mrpFail);
	});
</script>
@endpush
