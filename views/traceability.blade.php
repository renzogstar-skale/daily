@extends('layout.default')

@section('title', 'Trazabilidad de lote')

@section('content')
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$n = fn($v) => rtrim(rtrim(number_format(floatval($v), 3, '.', ''), '0'), '.');
	$catTone = ['received' => 'success', 'sold' => 'primary', 'wasted' => 'danger', 'adjust_in' => 'info', 'adjust_out' => 'warning', 'consumed_undocumented' => 'danger', 'other' => 'secondary',
		'quarantine' => 'warning', 'recall' => 'danger', 'waste_pending' => 'warning', 'expiry' => 'danger'];
	$docUrl = function ($d) use ($U) {
		if (!$d) { return null; }
		return match ($d['type']) {
			'sale' => $U('/sales'),
			'scan' => $U('/scan?session=' . $d['id']),
			default => $U('/document/' . $d['type'] . '/' . $d['id']),
		};
	};
@endphp

<div class="row align-items-center mb-3 dairy-page-header d-print-none">
	<div class="col-md-6 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-route"></i></div>
		<div>
			<h4 class="dairy-page-title">Trazabilidad de lote</h4>
			<p class="dairy-page-subtitle">Desde que el producto entra (proveedor, OC, recepción) hasta que se vende, se traslada, vence o se pierde, con documento, usuario y saldo conciliado.</p>
		</div>
	</div>
	<div class="col-md-6 dairy-page-actions">
		<form method="get" class="d-flex dairy-gap-2" autocomplete="off">
			<input type="text" name="q" class="form-control" value="{{ $q }}" data-dairy-scan-input placeholder="Lote, serie, GS1, VTA-, REC-, PED-, TRA- o id de entrada" aria-label="Buscar trazabilidad">
			<button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
			@if($result && count($result['groups']))<button type="button" class="btn btn-outline-secondary" onclick="window.print()" aria-label="Imprimir"><i class="fa-solid fa-print"></i></button>@endif
		</form>
	</div>
</div>

@if(!$result)
<div class="card">
	<div class="card-body">
		<p class="mb-2">Busque por cualquier punto de la cadena:</p>
		<ul class="small mb-3">
			<li><b>Lote o serie del proveedor</b> (o escanee la etiqueta GS1 con el lector): recorrido completo hacia adelante y clientes que lo recibieron.</li>
			<li><b>Venta VTA-, pedido PED-</b>: hacia atrás, de qué lote, proveedor y recepción salió lo vendido.</li>
			<li><b>Recepción REC- o traslado TRA-</b>: qué pasó después con todo lo recibido o enviado.</li>
		</ul>
		<form method="get" class="form-row align-items-end">
			<div class="col-md-6 form-group mb-md-0">
				<label for="tr-product">O elija un producto para ver sus lotes</label>
				<select id="tr-product" name="product_id" class="custom-select" onchange="this.form.submit()">
					<option value="">Producto…</option>
					@foreach($products as $p)<option value="{{ $p['id'] }}" @if($productId == $p['id']) selected @endif>{{ $p['name'] }}</option>@endforeach
				</select>
			</div>
		</form>
		@if($productLots)
		<div class="table-responsive mt-3">
			<table class="table table-sm mb-0">
				<thead><tr><th>Lote</th><th>Entrada</th><th>Vence</th><th class="text-right">Saldo</th><th></th></tr></thead>
				<tbody>
					@foreach($productLots as $l)
					<tr>
						<td>{{ $l['lot_number'] ?? 'sin lote del proveedor' }}@if($l['serial_number']) · serie {{ $l['serial_number'] }}@endif</td>
						<td>{{ substr($l['first_seen'], 0, 10) }} <span class="small text-muted">{{ $l['stock_id'] }}</span></td>
						<td>{{ $l['best_before_date'] === '2999-12-31' ? '—' : $l['best_before_date'] }}</td>
						<td class="text-right">{{ $n($l['on_hand']) }}</td>
						<td><a class="btn btn-xs btn-outline-primary" href="{{ $U('/traceability?q=' . urlencode($l['stock_id'])) }}">Trazar</a></td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		@endif
	</div>
</div>
@elseif(count($result['groups']) === 0)
<div class="alert alert-warning">{{ $result['message'] }}</div>
@else
@foreach($result['groups'] as $g)
@php $s = $g['summary']; @endphp
<div class="card mb-4 dairy-trace">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<span class="font-weight-700"><i class="fa-solid fa-box mr-2 text-primary"></i>{{ $g['product']['name'] }}
			<span class="badge badge-light border ml-1">{{ $g['lot_number'] ? 'Lote ' . $g['lot_number'] : 'Entrada ' . implode(', ', $g['stock_ids']) }}</span>
			@if($g['best_before_date'])<span class="badge badge-{{ $g['expired'] ? 'danger' : 'light border' }} ml-1">vence {{ $g['best_before_date'] }}</span>@endif
			<span class="badge badge-{{ $s['balanced'] ? 'success' : 'danger' }} ml-1">{{ $s['balanced'] ? 'Concilia' : 'No concilia' }}</span>
		</span>
		<span class="d-print-none text-nowrap">
			<a class="btn btn-sm btn-outline-secondary" href="{{ $U('/kardex?product_id=' . $g['product']['id'] . (count($g['stock_ids']) === 1 ? '&stock_id=' . $g['stock_ids'][0] : '')) }}"><i class="fa-solid fa-book-open mr-1"></i>Kardex</a>
			@if(count($g['customers']) || $s['on_hand'] > 0)<a class="btn btn-sm btn-outline-danger" href="{{ $U('/recalls?product_id=' . $g['product']['id']) }}"><i class="fa-solid fa-rotate-left mr-1"></i>Retiro</a>@endif
			@if($g['expired'] && $s['on_hand'] > 0)<a class="btn btn-sm btn-danger" href="{{ $U('/waste/report?product_id=' . $g['product']['id']) }}"><i class="fa-solid fa-trash-can mr-1"></i>Dar de baja</a>@endif
		</span>
	</div>
	<div class="card-body">
		@foreach($g['alerts'] as [$tone, $text])
		<div class="alert alert-{{ $tone }} py-2 small mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $text }}</div>
		@endforeach

		@include('components.dairy_kpi_row', ['kpis' => [
			['label' => 'Recibido', 'value' => $n($s['received']), 'sub' => $money($s['received_value']), 'icon' => 'fa-truck-ramp-box', 'tone' => 'success'],
			['label' => 'Vendido', 'value' => $n($s['sold']), 'sub' => count($g['customers']) . ' cliente(s)', 'icon' => 'fa-receipt', 'tone' => 'primary'],
			['label' => 'Merma', 'value' => $n($s['wasted']), 'sub' => count($g['waste_cases']) . ' caso(s)', 'icon' => 'fa-trash-can', 'tone' => $s['wasted'] > 0 ? 'danger' : 'success'],
			['label' => 'Ajustes', 'value' => '+' . $n($s['adjust_in']) . ' / −' . $n($s['adjust_out']), 'sub' => 'conteos y ediciones', 'icon' => 'fa-scale-balanced', 'tone' => 'info'],
			['label' => 'Sin Documento', 'value' => $n($s['consumed_undocumented']), 'sub' => 'salidas', 'icon' => 'fa-circle-question', 'tone' => $s['consumed_undocumented'] > 0 ? 'danger' : 'success', 'alert' => $s['consumed_undocumented'] > 0],
			['label' => 'Saldo', 'value' => $n($s['on_hand']), 'sub' => $s['balanced'] ? 'concilia con movimientos' : 'diferencia ' . $n($s['difference']), 'icon' => 'fa-boxes-stacked', 'tone' => $s['balanced'] ? 'success' : 'danger'],
		]])

		<div class="row">
			<div class="col-lg-4 mb-3">
				<h6 class="font-weight-700"><i class="fa-solid fa-right-to-bracket mr-1 text-success"></i>Origen</h6>
				@forelse($g['origin'] as $o)
				<div class="border dairy-radius-md p-2 mb-2 small">
					<div class="font-weight-700">{{ $o['supplier'] ?? 'Proveedor no registrado' }}</div>
					@if($o['receipt'])
					<div>Recepción <a href="{{ $docUrl($o['document']) }}">{{ $o['receipt']['receipt_code'] }}</a> · OC <a href="{{ $U('/document/order/' . $o['receipt']['order_id']) }}">{{ $o['receipt']['order_code'] }}</a></div>
					<div>{{ substr($o['receipt']['received_at'], 0, 16) }} · recibió {{ $o['receipt']['received_by'] }}@if($o['receipt']['temperature_on_arrival'] !== null) · {{ $o['receipt']['temperature_on_arrival'] }} °C @endif</div>
					@if($o['receipt']['supplier_document'])<div>Guía/factura {{ $o['receipt']['supplier_document'] }}</div>@endif
					@else
					<div class="text-warning">Entrada directa {{ substr($o['at'], 0, 16) }} (sin recepción contra OC) · {{ $o['user'] }}</div>
					@endif
					<div>{{ $n($o['qty']) }} {{ $g['product']['unit'] }} a {{ $money($o['unit_cost']) }} · {{ $o['where'] }}</div>
				</div>
				@empty
				<p class="small text-muted">Sin entrada registrada.</p>
				@endforelse

				<h6 class="font-weight-700 mt-3"><i class="fa-solid fa-users mr-1 text-primary"></i>Clientes que lo recibieron</h6>
				@forelse($g['customers'] as $c)
				<div class="small mb-1"><b>{{ $c['customer_name'] }}</b>@if($c['tax_id']) · {{ $c['tax_id'] }}@endif · {{ $n($c['qty']) }} u. · último {{ $c['last'] }}<br><span class="text-muted">{{ implode(', ', array_column($c['sales'], 'sale_number')) }}</span></div>
				@empty
				<p class="small text-muted">No se ha vendido.</p>
				@endforelse

				<h6 class="font-weight-700 mt-3"><i class="fa-solid fa-location-dot mr-1 text-secondary"></i>Dónde está hoy</h6>
				@forelse(array_filter($g['current'], fn($c) => floatval($c['amount']) > 0) as $c)
				<div class="small">{{ $c['visible'] ? ($c['is_transit'] ? 'En tránsito' : $c['branch_name'] . ' · ' . $c['location_name']) : 'Otra sucursal' }}: <b>{{ $n($c['amount']) }}</b></div>
				@empty
				<p class="small text-muted">Sin saldo: el lote se agotó.</p>
				@endforelse
				@if(count($g['branches']))<div class="small text-muted mt-1">Pasó por: {{ implode(' → ', $g['branches']) }}</div>@endif
			</div>
			<div class="col-lg-8 mb-3">
				<h6 class="font-weight-700"><i class="fa-solid fa-timeline mr-1 text-primary"></i>Recorrido</h6>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Fecha</th><th>Evento</th><th class="text-right">Cant.</th><th>Dónde</th><th>Documento</th><th>Usuario / detalle</th></tr></thead>
						<tbody>
							@foreach($g['events'] as $e)
							<tr class="@if($e['category'] === 'consumed_undocumented' || $e['category'] === 'expiry') table-danger @endif">
								<td class="text-nowrap">{{ substr($e['at'], 0, 16) }}</td>
								<td><span class="badge badge-{{ $catTone[$e['category']] ?? 'secondary' }}">{{ $e['label'] }}</span>@if(!empty($e['transit'])) <span class="small text-muted">(tránsito)</span>@endif</td>
								<td class="text-right">{{ $e['kind'] === 'move' ? ($e['qty'] > 0 ? '+' : '') . $n($e['qty']) : $n($e['qty']) }}</td>
								<td class="small">{{ $e['where'] ?? '—' }}</td>
								<td>@if($e['document'])<a href="{{ $docUrl($e['document']) }}">{{ $e['document']['folio'] }}</a>@else<span class="text-muted">—</span>@endif</td>
								<td class="small">{{ $e['user'] ?? '' }}@if(!empty($e['detail']))<div class="text-muted">{{ $e['detail'] }}</div>@endif</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endforeach
@endif
@stop
