@extends('layout.default')

@section('title', 'Control de inventario')

@section('content')
@include('components.alert_strip', ['alertCategories' => ['inventory']])
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$m = $abcxyz['matrix'];
	$lostValue = array_sum(array_column($phantom, 'lost_sales_value'));
	$phantomStockValue = array_sum(array_column($phantom, 'stock_value'));
	$cellTone = ['AX' => 'success', 'AY' => 'success', 'AZ' => 'warning', 'BX' => 'info', 'BY' => 'info', 'BZ' => 'warning', 'CX' => 'secondary', 'CY' => 'secondary', 'CZ' => 'danger'];
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
		<div>
			<h4 class="dairy-page-title">Control de inventario</h4>
			<p class="dairy-page-subtitle">Detecta stock que existe en el sistema pero probablemente no en góndola, y clasifica cada producto por valor y estabilidad de la demanda.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right dairy-page-actions">
		<form method="get" class="d-inline-flex">
			<select name="branch_id" class="custom-select custom-select-sm w-auto" onchange="this.form.submit()" aria-label="Sucursal">
				@foreach($branches as $b)<option value="{{ $b['id'] }}" @if($b['id'] == $branchId) selected @endif>{{ $b['name'] }}</option>@endforeach
			</select>
		</form>
		@include('components.dairy_export')
	</div>
</div>

@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Stock Fantasma', 'value' => count($phantom), 'sub' => 'productos a contar', 'icon' => 'fa-ghost', 'tone' => count($phantom) ? 'danger' : 'success', 'alert' => count($phantom) > 0],
	['label' => 'Venta Perdida', 'value' => $money($lostValue), 'sub' => 'estimada en la racha sin venta', 'icon' => 'fa-cart-arrow-down', 'tone' => $lostValue > 0 ? 'warning' : 'success'],
	['label' => 'Valor en Duda', 'value' => $money($phantomStockValue), 'sub' => 'stock en sistema a costo', 'icon' => 'fa-scale-unbalanced', 'tone' => 'primary'],
	['label' => 'Clase AX', 'value' => $m['AX']['count'], 'sub' => 'alto valor y estable', 'icon' => 'fa-star', 'tone' => 'success'],
	['label' => 'Clase AZ', 'value' => $m['AZ']['count'], 'sub' => 'alto valor, errática', 'icon' => 'fa-wave-square', 'tone' => 'warning'],
	['label' => 'Clase CZ', 'value' => $m['CZ']['count'], 'sub' => 'candidatos a depurar', 'icon' => 'fa-box-archive', 'tone' => 'info'],
]])

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-ghost text-danger mr-2"></i>Posible stock fantasma</span>
		@if($canCount && count($phantom))
		<button type="button" class="btn btn-sm btn-primary" id="ic-count-all"><i class="fa-solid fa-list-check mr-1"></i>Programar conteo ciego ({{ count(array_filter($phantom, fn($p) => !$p['count_pending'])) }})</button>
		@endif
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Producto</th><th class="text-right">Stock sistema</th><th class="text-right">Días con venta</th><th>Última venta</th><th class="text-right">Días sin venta</th><th class="text-right" title="Probabilidad de que la racha sin ventas sea casual">Prob. casual</th><th class="text-right">Venta perdida</th><th></th></tr></thead>
				<tbody>
					@forelse($phantom as $p)
					<tr>
						<td class="font-weight-600">{{ $p['product_name'] }}</td>
						<td class="text-right">{{ $p['system_stock'] }}</td>
						<td class="text-right">{{ $p['sell_frequency_pct'] }} %<div class="small text-muted">{{ $p['sale_days'] }} de {{ $p['observed_days'] }} días</div></td>
						<td>{{ $p['last_sale'] }}</td>
						<td class="text-right text-danger font-weight-600">{{ $p['days_without_sale'] }}</td>
						<td class="text-right">{{ round($p['probability'] * 100, 2) }} %</td>
						<td class="text-right">{{ $money($p['lost_sales_value']) }}</td>
						<td class="text-right text-nowrap">
							@if($p['count_pending'])
							<span class="badge badge-info">Conteo pendiente</span>
							@elseif($canCount)
							<button type="button" class="btn btn-sm btn-outline-primary ic-count" data-product="{{ $p['product_id'] }}">Contar</button>
							@endif
							<a class="btn btn-sm btn-light border" href="{{ $U('/kardex?product_id=' . $p['product_id']) }}" title="Kardex"><i class="fa-solid fa-book-open"></i></a>
						</td>
					</tr>
					@empty
					<tr><td colspan="8" class="text-center text-muted py-4">Sin señales de stock fantasma: todos los productos con venta regular y stock se están vendiendo con normalidad.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		<div class="small text-muted p-2">Regla: un producto que se vende en una fracción f de los días y lleva g días sin venta con stock en sistema tiene probabilidad (1−f)<sup>g</sup> de que sea casual. Bajo el umbral de la regla «Stock fantasma» (Centro de Alertas) se sugiere un conteo ciego. Requiere al menos 4 días con venta en 8 semanas.</div>
	</div>
</div>

{{-- Etapa 21: rotación — lotes inmovilizados con recomendación por reglas verificables --}}
@php
	$actIcon = ['write_off' => ['fa-trash-can', 'danger', 'Dar de baja'], 'markdown' => ['fa-tags', 'warning', 'Rebaja'], 'transfer' => ['fa-right-left', 'info', 'Trasladar'], 'return' => ['fa-truck-arrow-right', 'primary', 'Devolver'], 'count' => ['fa-list-check', 'secondary', 'Contar']];
	$actHref = ['write_off' => '/waste/report?product_id=', 'markdown' => '/quality?product_id=', 'transfer' => '/transfers?product_id=', 'return' => '/suppliers?product_id='];
@endphp
<div class="card mb-3" id="ic-stagnant">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-hourglass-half text-warning mr-2"></i>Lotes inmovilizados (sin salidas en {{ $stagnant['days'] }} días)</span>
		<span class="small text-muted">{{ $stagnant['count'] }} lote(s) · {{ $money($stagnant['total_value']) }} a costo</span>
	</div>
	<div class="card-body p-0">
		@if($stagnant['count'] === 0)
		<p class="small text-muted p-3 mb-0">Sin lotes inmovilizados en esta sucursal.</p>
		@else
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Producto</th><th>Ubicación</th><th>Lote</th><th class="text-right">Cant.</th><th class="text-right">Valor</th><th class="text-right">Días sin salida</th><th>Vence</th><th>Recomendación</th><th></th></tr></thead>
				<tbody>
					@foreach(array_slice($stagnant['lots'], 0, 100) as $l)
					@php [$ic, $tone, $btn] = $actIcon[$l['action']]; @endphp
					<tr>
						<td>{{ $l['product_name'] }}</td>
						<td>{{ $l['location_name'] }}</td>
						<td>{{ $l['lot_number'] ?? '—' }}</td>
						<td class="text-right">{{ round($l['amount'], 2) }}</td>
						<td class="text-right">{{ $money($l['value']) }}</td>
						<td class="text-right">{{ $l['days_without_outflow'] }}</td>
						<td class="@if($l['days_left'] !== null && $l['days_left'] < 0) text-danger font-weight-700 @endif">{{ $l['days_left'] === null ? '—' : $l['best_before_date'] }}</td>
						<td class="small"><span class="badge badge-{{ $tone }} mr-1"><i class="fa-solid {{ $ic }}"></i></span>{{ $l['recommendation'] }}</td>
						<td class="text-nowrap">
							@if($l['action'] === 'count')
								@if($canCount)<button type="button" class="btn btn-xs btn-outline-secondary ic-count" data-product="{{ $l['product_id'] }}">Conteo ciego</button>@endif
							@else
								<a class="btn btn-xs btn-outline-{{ $tone }}" href="{{ $U($actHref[$l['action']] . $l['product_id']) }}">{{ $btn }}</a>
							@endif
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		@endif
		<div class="small text-muted p-2">Reglas determinísticas, en orden: vencido → baja; dentro de una regla de rebaja FEFO → rebaja; otra sucursal lo vendió en 28 días → traslado; proveedor conocido y compra reciente → evaluar devolución; si no → conteo de verificación. Plazo configurable con <code>STAGNANT_LOT_DAYS</code>.</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-5 mb-3">
		<div class="card h-100">
			<div class="card-header font-weight-700 text-dark"><i class="fa-solid fa-table-cells text-primary mr-2"></i>Matriz ABC-XYZ</div>
			<div class="card-body">
				<table class="table table-sm table-bordered text-center mb-2 dairy-keep-table">
					<thead><tr><th></th><th title="Demanda estable (CV ≤ 0.5)">X estable</th><th title="Variable (CV ≤ 1)">Y variable</th><th title="Errática o sin venta">Z errática</th></tr></thead>
					<tbody>
						@foreach(['A' => '80 % venta', 'B' => '15 %', 'C' => '5 %'] as $a => $lbl)
						<tr>
							<th class="text-left">{{ $a }} <span class="small text-muted">{{ $lbl }}</span></th>
							@foreach(['X', 'Y', 'Z'] as $x)
							<td class="ic-cell" role="button" tabindex="0" data-cell="{{ $a . $x }}" title="{{ $m[$a . $x]['policy'] }}">
								<span class="badge badge-{{ $cellTone[$a . $x] }} dairy-fs-md">{{ $m[$a . $x]['count'] }}</span>
								<div class="small text-muted">{{ $money($m[$a . $x]['revenue']) }}</div>
							</td>
							@endforeach
						</tr>
						@endforeach
					</tbody>
				</table>
				<div class="small text-muted">ABC por venta de 90 días (Pareto); XYZ por coeficiente de variación de las unidades semanales ({{ $abcxyz['weeks'] }} semanas). Toque una celda para filtrar.</div>
				<div class="alert alert-light border small mt-2 mb-0" id="ic-policy">Seleccione una celda para ver la política recomendada.</div>
			</div>
		</div>
	</div>
	<div class="col-lg-7 mb-3">
		<div class="card h-100">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="font-weight-700 text-dark">Productos <span id="ic-filter-label" class="text-muted small"></span></span>
				<button type="button" class="btn btn-sm btn-light border d-none" id="ic-clear">Ver todos</button>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive dairy-scroll-400">
					<table class="table table-sm table-hover mb-0" id="ic-items">
						<thead><tr><th>Producto</th><th>Clase</th><th class="text-right">Venta 90 d</th><th class="text-right">Unid./sem.</th><th class="text-right">CV</th><th class="text-right">Stock</th></tr></thead>
						<tbody>
							@forelse($abcxyz['items'] as $i)
							<tr data-cell="{{ $i['cell'] }}">
								<td>{{ $i['product_name'] }}</td>
								<td><span class="badge badge-{{ $cellTone[$i['cell']] }}">{{ $i['cell'] }}</span></td>
								<td class="text-right">{{ $money($i['revenue_90d']) }}</td>
								<td class="text-right">{{ $i['weekly_mean'] }}</td>
								<td class="text-right">{{ $i['cv'] ?? '—' }}</td>
								<td class="text-right">{{ $i['stock'] }}</td>
							</tr>
							@empty
							<tr><td colspan="6" class="text-center text-muted py-4">Sin productos con stock o ventas en la sucursal.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.DairyInventoryControl = { branchId: {{ intval($branchId) }}, policies: {!! json_encode(array_map(fn($c) => $c['policy'], $m)) !!}, pending: {!! json_encode(array_values(array_map(fn($p) => $p['product_id'], array_filter($phantom, fn($p) => !$p['count_pending'])))) !!} };
</script>
@stop
