@php
	$L = $loss;
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$knownPct = $L['gross_loss'] > 0 ? round($L['known']['total'] / $L['gross_loss'] * 100) : 0;
@endphp
<div class="card mb-3 dairy-bordered">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-start flex-wrap dairy-gap-2 mb-3">
			<div>
				<h5 class="font-weight-bold text-dark mb-1"><i class="fa-solid fa-layer-group text-danger mr-2"></i>Pérdida total clasificada</h5>
				<p class="text-muted mb-0 dairy-fs-sm">{{ $L['from'] }} a {{ $L['to'] }} · merma registrada + diferencias de inventario + salidas sin documento − recuperado del proveedor.</p>
			</div>
			<div class="text-right">
				<div class="dairy-fs-xl font-weight-bold text-danger">{{ $money($L['net_loss']) }}</div>
				<div class="small text-muted">pérdida neta{{ $L['net_loss_pct_sales'] !== null ? ' · ' . $L['net_loss_pct_sales'] . ' % de la venta' : ' · sin ventas en el período' }}</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-sm mb-3 dairy-keep-table">
				<thead><tr><th>Componente</th><th class="text-right">Monto</th><th>Qué significa</th></tr></thead>
				<tbody>
					<tr><td class="font-weight-600">Merma conocida</td><td class="text-right">{{ $money($L['known']['total']) }}</td><td class="small text-muted">Casos aprobados con causal ({{ $knownPct }} % de la pérdida bruta).</td></tr>
					@foreach($L['known']['by_class'] as $k)
					<tr><td class="pl-4 small">{{ ucfirst($k['class']) }}</td><td class="text-right small">{{ $money($k['cost']) }}</td><td class="small text-muted">{{ $k['records'] }} registro(s)</td></tr>
					@endforeach
					<tr><td class="font-weight-600">Pérdida desconocida</td><td class="text-right">{{ $money(max(0, $L['unknown']['net'])) }}</td><td class="small text-muted">Ajustes de inventario: faltantes {{ $money($L['unknown']['loss']) }} − sobrantes {{ $money($L['unknown']['gain']) }} ({{ $L['unknown']['adjustments'] }} ajuste(s)).</td></tr>
					<tr><td class="font-weight-600">Salidas sin documento</td><td class="text-right @if($L['unexplained_outflows']['total'] > 0) text-danger @endif">{{ $money($L['unexplained_outflows']['total']) }}</td><td class="small text-muted">{{ $L['unexplained_outflows']['count'] }} consumo(s) sin venta, merma ni traslado: investigar.</td></tr>
					<tr class="table-light"><td class="font-weight-700">Pérdida bruta</td><td class="text-right font-weight-700">{{ $money($L['gross_loss']) }}</td><td></td></tr>
					<tr><td class="font-weight-600">Recuperado (notas de crédito)</td><td class="text-right text-success">−{{ $money($L['recovered']) }}</td><td class="small text-muted">Créditos cobrados a proveedores.</td></tr>
					<tr class="table-light"><td class="font-weight-700">Pérdida neta</td><td class="text-right font-weight-700 text-danger">{{ $money($L['net_loss']) }}</td><td></td></tr>
					<tr><td class="text-muted">Rechazos en recepción</td><td class="text-right text-muted">{{ $money($L['receiving_rejections']) }}</td><td class="small text-muted">No entraron al inventario: a cargo del proveedor (no suma).</td></tr>
					<tr><td class="text-muted">Ventas sin descargo</td><td class="text-right @if($L['pos_gap']['total'] > 0) text-warning @endif">{{ $money($L['pos_gap']['total']) }}</td><td class="small text-muted">{{ $L['pos_gap']['sales'] }} venta(s) sin movimiento de stock: el inventario está sobrestimado (no suma; origina stock fantasma).</td></tr>
				</tbody>
			</table>
		</div>

		<div class="row">
			<div class="col-lg-6 mb-3">
				<h6 class="font-weight-700">Salidas sin documento</h6>
				<div class="table-responsive dairy-scroll-400">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Fecha</th><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Valor</th><th>Usuario</th></tr></thead>
						<tbody>
							@forelse($L['unexplained_outflows']['rows'] as $r)
							<tr><td class="small">{{ substr($r['row_created_timestamp'], 0, 16) }}</td><td><a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}">{{ $r['product_name'] }}</a></td><td class="text-right">{{ $r['amount'] }}</td><td class="text-right">{{ $money($r['value']) }}</td><td class="small">{{ $r['user_name'] }}</td></tr>
							@empty
							<tr><td colspan="5" class="text-center text-muted py-3">Todas las salidas tienen documento.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
			<div class="col-lg-6 mb-3">
				<h6 class="font-weight-700">Conciliación POS ↔ inventario</h6>
				<div class="table-responsive dairy-scroll-400">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Venta</th><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Costo</th></tr></thead>
						<tbody>
							@forelse($L['pos_gap']['rows'] as $r)
							<tr><td class="small">{{ $r['sale_number'] ?: '#' . $r['sale_id'] }}<div class="text-muted">{{ substr($r['created_timestamp'], 0, 16) }}</div></td><td>{{ $r['product_name'] }}</td><td class="text-right">{{ $r['amount'] }}</td><td class="text-right">{{ $money($r['cost']) }}</td></tr>
							@empty
							<tr><td colspan="4" class="text-center text-muted py-3">Todas las ventas descontaron inventario.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="small text-muted">Cada hallazgo genera una alerta con responsable en el <a href="{{ $U('/alerts') }}">Centro de Alertas</a> (reglas «Salidas sin documento» y «Venta sin descargo»).</div>
	</div>
</div>
