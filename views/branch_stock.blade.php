@extends('layout.default')

@section('title', 'Inventario de ' . ($branch ? $branch->name : 'Sucursal'))

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-boxes-stacked"></i></div>
		<div>
			<h4 class="dairy-page-title">Inventario: {{ $branch->name }}</h4>
			<p class="dairy-page-subtitle">Existencias físicas, valuación y ubicaciones aisladas en la sucursal <strong>{{ str_replace('DEMO-', 'EJ-', $branch->code) }}</strong>.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right dairy-page-actions">
		<a href="{{ $U('/branches') }}" class="btn btn-outline-secondary">
			<i class="fa-solid fa-arrow-left mr-1"></i>Volver a Sucursales
		</a>
	</div>
</div>

<div class="card shadow-sm border-0">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-hover table-striped mb-0 text-sm">
				<thead class="thead-light">
					<tr>
						<th>Producto</th>
						<th>Ubicación en Tienda</th>
						<th class="text-right">Cantidad en Existencia</th>
						<th class="text-right">Precio Promedio Compra</th>
						<th class="text-right">Valuación Total</th>
					</tr>
				</thead>
				<tbody>
					@forelse($stock as $item)
					<tr>
						<td class="font-weight-bold text-dark">{{ $item->product_name }}</td>
						<td><span class="badge badge-light border">{{ $item->location_name }}</span></td>
						<td class="text-right font-weight-bold">{{ $item->total_amount }} {{ $item->qu_name }}</td>
						<td class="text-right text-muted">${{ number_format($item->avg_price ?? 0, 2) }}</td>
						<td class="text-right font-weight-bold text-success">${{ number_format($item->total_value ?? 0, 2) }}</td>
					</tr>
					@empty
					<tr>
						<td colspan="5" class="text-center py-5 text-muted">
							<i class="fa-solid fa-box-open fa-3x text-light mb-2"></i>
							<p class="mb-0">No hay productos registrados en esta sucursal.</p>
						</td>
					</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>
@stop
