@extends('layout.default')

@section('title', 'Productos agotados')

@section('content')
@php
	$rows = $result['rows'];
	$c = $result['counts'];
	$fn = $filterNames;
	$scope = $fn['branch'] . ' · ' . $fn['department'] . ($filters['group_id'] !== null ? ' · ' . $fn['group'] : '');
	$now = date('d/m/Y H:i');
	$moves = ['purchase' => 'Compra', 'consume' => 'Venta/consumo', 'inventory-correction' => 'Ajuste de inventario', 'transfer_from' => 'Traslado (salida)', 'transfer_to' => 'Traslado (entrada)', 'product-opened' => 'Apertura', 'stock-edit-old' => 'Edición', 'stock-edit-new' => 'Edición', 'self-production' => 'Producción'];
	$qty = fn($v) => number_format($v, fmod($v, 1) == 0 ? 0 : 2);
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-box-open"></i></div>
		<div>
			<h4 class="dairy-page-title">Productos agotados</h4>
			<p class="dairy-page-subtitle">Busque por SKU, código de barras o nombre y vea en qué sucursal falta, cuánto hay registrado y disponible, su último movimiento y si ya hay reposición en curso.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right"><a href="{{ $U('/inventory-control') }}" class="btn btn-light border btn-sm"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i>Control de inventario</a></div>
</div>

@include('components.daily_filter_bar', ['fbRoute' => '/inventory/stockouts', 'fbFilters' => $filters, 'fbOptions' => $filterOptions])

<div class="sdash-kpis mb-3">
	@include('components.daily_kpi', ['dkpi' => ['id' => 'stockouts', 'name' => 'Productos agotados', 'value' => $c['out'], 'unit' => 'pares producto-sucursal', 'period' => 'Existencias al ' . $now, 'scope' => $scope,
		'definition' => $result['searching'] ? 'Coincidencias de la búsqueda con existencia física ≤ 0 en la sucursal' : 'Producto activo del surtido (stock mínimo > 0, no serializado) con existencia física ≤ 0 en la sucursal, sin contar ubicaciones de tránsito. Misma regla que HOY', 'source' => 'products, stock, locations', 'updated' => 'Calculado ' . $now]])
	@include('components.daily_kpi', ['dkpi' => ['id' => 'blocked', 'name' => 'Sin disponible para venta', 'value' => $c['blocked'], 'unit' => 'pares producto-sucursal', 'period' => 'Existencias al ' . $now, 'scope' => $scope,
		'definition' => 'Hay existencia física, pero cuarentena + vencido + merma pendiente + reservas de pedidos la consumen por completo', 'source' => 'stock, quarantine_items, waste_records, customer_orders', 'updated' => 'Calculado ' . $now]])
</div>

<div class="sdash-block" data-testid="stockouts-table-block">
	<header><h2>{{ $result['searching'] ? 'Resultado de la búsqueda «' . $filters['product'] . '»' : 'Agotados y sin disponible' }}</h2><span class="sdash-note">{{ count($rows) }} filas · {{ $result['branches'] }} sucursales evaluadas</span></header>
	@if(!$rows)
		<p class="sdash-empty" data-testid="stockouts-empty">{{ $result['searching'] ? 'Ningún producto coincide con «' . $filters['product'] . '» en las sucursales del filtro.' : 'No hay productos agotados ni sin disponible con este filtro.' }}</p>
	@else
	<div class="table-responsive">
		<table class="table sdash-table mb-0" data-testid="stockouts-table">
			<thead><tr><th>Estado</th><th>Producto</th><th>SKU</th><th>Departamento</th><th>Sucursal</th><th class="text-right">Stock registrado</th><th class="text-right">Disponible</th><th class="text-right">Mínimo</th><th>Último movimiento</th><th>Reposición</th><th></th></tr></thead>
			<tbody>
			@foreach(array_slice($rows, 0, 400) as $r)
				<tr data-testid="stockout-row">
					<td><span class="dso-state {{ $r['state'] === 'out' ? 'is-out' : ($r['state'] === 'ok' ? 'is-ok' : 'is-blocked') }}">{{ $r['state_label'] }}</span></td>
					<td class="font-weight-600">{{ $r['name'] }}<div class="sdash-sub">{{ $r['category'] }}</div></td>
					<td><code>{{ $r['sku'] ?: '—' }}</code></td>
					<td>{{ $r['department'] }}</td>
					<td>{{ $r['branch'] }}</td>
					<td class="text-right">{{ $qty($r['physical']) }}</td>
					<td class="text-right">{{ $qty($r['available']) }}</td>
					<td class="text-right">{{ $r['min'] > 0 ? $qty($r['min']) : '—' }}</td>
					<td>{{ $r['last_move'] ? date('d/m/Y H:i', strtotime($r['last_move'])) : 'Sin movimientos' }}@if($r['last_move_type'])<div class="sdash-sub">{{ $moves[$r['last_move_type']] ?? $r['last_move_type'] }}</div>@endif</td>
					<td>{{ $r['replenishment'] ?? ($r['state'] === 'ok' ? 'No requerida' : 'Sin reposición en curso') }}</td>
					<td class="text-right text-nowrap">
						<a class="sdash-link" href="{{ $U('/kardex?' . http_build_query(['product_id' => $r['product_id'], 'branch_id' => $r['branch_id']])) }}" data-testid="stockout-detail">Detalle</a>
						· <a class="sdash-link" href="{{ $U('/sales?' . http_build_query(['branch_id' => $r['branch_id'], 'product' => $r['sku'] ?: $r['product_id']])) }}">Ventas</a>
					</td>
				</tr>
			@endforeach
			</tbody>
		</table>
	</div>
	@if(count($rows) > 400)<p class="sdash-note mt-2">Se muestran las primeras 400 filas: filtre por sucursal o departamento para acotar.</p>@endif
	@endif
</div>
@stop
