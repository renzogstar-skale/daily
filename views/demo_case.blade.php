@extends('layout.default')

@section('title', 'Caso demo · Yogur natural 500 g')

@php
	$c = $case;
	$money = fn($v) => $v === null ? 'Sin información' : '$' . number_format((float) $v, 2);
	$qty = fn($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2);
	$link = fn(string $k) => isset($links[$k]) ? $U($links[$k][0]) : null;
	$stepLink = ['po' => 'po', 'receive' => 'receive', 'move' => 'kardex', 'pos' => 'pos', 'reserve' => 'order', 'pick' => 'order', 'deliver' => 'order',
		'fefo' => 'task', 'block' => 'block', 'waste' => 'waste', 'approve' => 'kardex', 'opex' => 'finance'];
	$done = $c['steps'];
	$kx = [];
	foreach ($c['kardex'] as $row)
	{
		$key = $row['transaction_type'] . ($row['spoiled'] ? '_spoiled' : '');
		$kx[$key] = ($kx[$key] ?? 0) + (float) $row['qty'];
	}
	$kxLabels = ['purchase' => 'Entrada por recepción', 'transfer_from' => 'Traslado: sale de bodega', 'transfer_to' => 'Traslado: entra a góndola',
		'consume' => 'Salidas por venta (caja + pedido)', 'consume_spoiled' => 'Baja por vencimiento (merma)'];
	$started = !empty($done);
@endphp

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-route"></i></div>
		<div>
			<h4 class="dairy-page-title">Caso demo · Yogur natural 500 g</h4>
			<p class="dairy-page-subtitle">SKU {{ \Grocy\Services\DemoRetailCaseService::SKU }} · lote {{ \Grocy\Services\DemoRetailCaseService::LOT }} · Hallandale Beach (SUC-01) · Lácteos y huevos.
				Cada cifra se calcula con los registros que crearon Compras, Inventario, POS, Despachos, Calidad y Mermas.</p>
		</div>
	</div>
	<div class="col-lg-5 dairy-page-actions" id="dcase-actions">
		@if($enabled && $canRun)
		<button type="button" class="btn btn-sm btn-primary" data-dcase="next" @if(!$c['next']) disabled @endif><i class="fa-solid fa-forward-step"></i> Siguiente paso</button>
		<button type="button" class="btn btn-sm btn-outline-primary" data-dcase="pre_close" @if(isset($done[$c['pre_close']])) disabled @endif>Hasta antes del cierre</button>
		<button type="button" class="btn btn-sm btn-outline-primary" data-dcase="all" @if(!$c['next']) disabled @endif>Completar</button>
		<button type="button" class="btn btn-sm btn-outline-danger" data-dcase="reset" @if(!$started) disabled @endif><i class="fa-solid fa-rotate-left"></i> Restablecer</button>
		@elseif(!$enabled)
		<span class="badge badge-secondary">Disponible solo en modo demo</span>
		@endif
	</div>
</div>

<div class="alert alert-light border small mb-3" data-testid="dcase-clock">
	<i class="fa-regular fa-clock mr-1"></i>Reloj demo: <b>{{ $c['cut'] ? substr($c['cut'], 0, 16) : 'caso sin iniciar' }}</b>
	@if($c['started_at']) · iniciado {{ substr($c['started_at'], 0, 16) }} @endif
	· Corte: {{ $c['next'] ? (isset($done[$c['pre_close']]) ? 'antes del cierre' : 'en curso') : 'cierre del caso' }}.
	Importes del caso filtrado (SKU); los totales de la sucursal y de la red incluyen además el resto de la operación demo.
</div>

<div class="row" id="dcase-kpis">
	<div class="col-12 col-sm-6 col-lg-3 mb-3">@include('components.daily_kpi', ['dkpi' => ['name' => 'Pendiente en la orden', 'value' => $c['po'] ? (float) $c['po']['pending'] : null, 'unit' => 'u.', 'period' => 'Orden ' . ($c['refs']['po_code'] ?? '—'), 'scope' => 'Hallandale Beach', 'definition' => 'Pedidas − recibidas; no forma parte del stock', 'source' => 'procurement_order_items', 'id' => 'dcase-po-pending']])</div>
	<div class="col-12 col-sm-6 col-lg-3 mb-3">@include('components.daily_kpi', ['dkpi' => ['name' => 'Disponible en inventario', 'value' => $started ? $qty($c['stock']['available']) . ' u. · ' . $money($c['stock']['value']) : null, 'period' => 'Al corte', 'scope' => 'Lote LAC-001', 'definition' => 'Físico − reservado − bloqueado; valorizado al costo', 'source' => 'stock, pedidos, cuarentena', 'id' => 'dcase-stock']])</div>
	<div class="col-12 col-sm-6 col-lg-3 mb-3">@include('components.daily_kpi', ['dkpi' => ['name' => 'Ventas netas', 'value' => $started ? (float) $c['pnl']['net'] : null, 'unit' => '$', 'period' => 'Caso', 'scope' => $qty($c['sales']['qty']) . ' u.', 'definition' => 'Caja + pedido entregado, sin impuestos ni descuentos', 'source' => 'sale_items', 'id' => 'dcase-sales']])</div>
	<div class="col-12 col-sm-6 col-lg-3 mb-3">@include('components.daily_kpi', ['dkpi' => ['name' => 'Resultado operativo', 'value' => isset($done['opex']) ? (float) $c['pnl']['result'] : null, 'unit' => '$', 'period' => 'Caso', 'scope' => 'Después de merma y gastos', 'definition' => 'Utilidad bruta − merma − gastos operativos', 'source' => 'Ventas, Mermas, gastos del caso', 'id' => 'dcase-result']])</div>
</div>

<div class="row">
	<div class="col-lg-5 mb-3">
		<div class="card h-100">
			<div class="card-header"><b><i class="fa-solid fa-list-check text-primary mr-1"></i>Prioridades del turno (HOY)</b></div>
			<div class="card-body p-2" data-testid="dcase-priorities">
				@forelse($priorities as $p)
				<a class="d-block border rounded p-2 mb-2 text-reset dcase-prio @if($p['done']) is-done @endif" href="{{ $U($p['href']) }}">
					<div class="d-flex justify-content-between align-items-start">
						<b class="small">{{ $p['what'] }}</b>
						<span class="badge {{ $p['done'] ? 'badge-success' : 'badge-warning' }} ml-2">{{ $p['done'] ? 'Resuelto' : 'Pendiente' }}</span>
					</div>
					<div class="small text-muted">Hallandale Beach · {{ $p['who'] }} · plazo {{ $p['due'] ?: '—' }}</div>
					<div class="small text-primary">{{ $p['action'] }} →</div>
				</a>
				@empty
				<p class="text-muted small mb-0">Inicie el caso para ver la recepción parcial, la tarea FEFO y la merma por aprobar.</p>
				@endforelse
			</div>
		</div>
	</div>
	<div class="col-lg-7 mb-3">
		<div class="card h-100">
			<div class="card-header"><b><i class="fa-solid fa-timeline text-primary mr-1"></i>Recorrido Compras → Finanzas</b></div>
			<ol class="list-group list-group-flush dcase-steps" data-testid="dcase-steps">
				@foreach($stepsDef as $code => [$label, $module])
				<li class="list-group-item py-2 d-flex flex-wrap align-items-start">
					<span class="mr-2 {{ isset($done[$code]) ? 'text-success' : 'text-muted' }}"><i class="fa-solid {{ isset($done[$code]) ? 'fa-circle-check' : 'fa-circle' }}"></i></span>
					<div class="flex-grow-1 min-w-0">
						<div class="small"><b>{{ $label }}</b></div>
						<div class="small text-muted">{{ $module }} @if(isset($done[$code])) · {{ substr($done[$code], 11, 5) }} @endif</div>
					</div>
					@if(isset($done[$code]) && ($h = $link($stepLink[$code])))
					<a class="small dcase-step-link" href="{{ $h }}">{{ $links[$stepLink[$code]][1] }} →</a>
					@endif
				</li>
				@endforeach
			</ol>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-6 mb-3">
		<div class="card h-100">
			<div class="card-header"><b><i class="fa-solid fa-coins text-primary mr-1"></i>Resultado del caso</b></div>
			<div class="card-body p-0">
				<table class="table table-sm mb-0 dcase-table" data-testid="dcase-pnl">
					<tbody>
						<tr><td>Ventas netas <span class="text-muted small">(caja {{ $money($c['sales']['pos']) }} + pedido {{ $money($c['sales']['order']) }})</span></td><td class="text-right"><a href="{{ $link('pos') ?? '#' }}">{{ $money($c['pnl']['net']) }}</a></td></tr>
						<tr><td>Costo de venta</td><td class="text-right">−{{ $money($c['pnl']['cogs']) }}</td></tr>
						<tr class="font-weight-bold"><td>Utilidad bruta @if($c['pnl']['gross_pct'] !== null)<span class="text-muted small">({{ number_format($c['pnl']['gross_pct'], 1) }} %)</span>@endif</td><td class="text-right">{{ $started ? $money($c['pnl']['gross']) : 'Sin información' }}</td></tr>
						<tr><td>Merma (vencimiento) @if($c['waste']['pending_cost'] > 0)<span class="badge badge-warning">{{ $money($c['waste']['pending_cost']) }} por aprobar</span>@endif</td><td class="text-right"><a href="{{ $link('waste') ?? '#' }}">−{{ $money($c['pnl']['waste']) }}</a></td></tr>
						<tr><td>Gastos operativos del caso <span class="text-muted small">{{ $c['refs']['expense']['code'] ?? '' }}</span></td><td class="text-right">{{ isset($done['opex']) ? '−' . $money($c['pnl']['opex']) : 'Pendiente' }}</td></tr>
						<tr class="font-weight-bold"><td>Resultado operativo</td><td class="text-right">{{ isset($done['opex']) ? $money($c['pnl']['result']) : 'Pendiente' }}</td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-lg-6 mb-3">
		<div class="card h-100">
			<div class="card-header d-flex justify-content-between"><b><i class="fa-solid fa-book text-primary mr-1"></i>Kardex del lote</b>@if($link('kardex'))<a class="small" href="{{ $link('kardex') }}">Abrir Kardex →</a>@endif</div>
			<div class="card-body p-0">
				<table class="table table-sm mb-0 dcase-table" data-testid="dcase-kardex">
					<tbody>
						@foreach($kxLabels as $k => $label)
						@if(isset($kx[$k]))<tr><td>{{ $label }}</td><td class="text-right">{{ $kx[$k] > 0 ? '+' : '' }}{{ $qty($kx[$k]) }}</td></tr>@endif
						@endforeach
						<tr class="font-weight-bold"><td>Existencia al corte <span class="text-muted small">(bodega {{ $qty($c['stock']['store']) }} · góndola {{ $qty($c['stock']['shelf']) }} · reservado {{ $qty($c['stock']['reserved']) }} · bloqueado {{ $qty($c['stock']['blocked']) }})</span></td><td class="text-right">{{ $qty($c['stock']['qty']) }} u. · {{ $money($c['stock']['value']) }}</td></tr>
						@if($c['po'])<tr><td>Saldo pendiente en la orden (no es stock)</td><td class="text-right">{{ $qty($c['po']['pending']) }} u.</td></tr>@endif
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@if($started)
<div class="card mb-3">
	<div class="card-header"><b><i class="fa-solid fa-link text-primary mr-1"></i>Registros del caso</b></div>
	<div class="card-body d-flex flex-wrap dcase-links" data-testid="dcase-links">
		@foreach($links as $k => [$href, $label])
		<a class="btn btn-sm btn-light border mr-2 mb-2" href="{{ $U($href) }}">{{ $label }}</a>
		@endforeach
	</div>
</div>
@endif
@stop

@push('pageStyles')
<style>
	.dcase-table td { padding-left: 1rem; padding-right: 1rem; vertical-align: top; }
	.dcase-table td:last-child { white-space: nowrap; text-align: right; }
	.dcase-table td:first-child { overflow-wrap: anywhere; }
	.dcase-step-link { margin-left: auto; padding-left: .5rem; text-align: right; }
	@media (max-width: 575.98px) {
		.dcase-step-link { flex-basis: 100%; padding-left: 1.6rem; text-align: left; }
		.dcase-table, .dcase-table tbody { display: block; width: 100% !important; min-width: 0 !important; }
		.dcase-table tr { display: flex; flex-wrap: wrap; justify-content: space-between; border-top: 1px solid var(--bs-border-color, rgba(128,128,128,.25)); }
		.dcase-table td { display: block; border: 0 !important; white-space: normal !important; }
		.dcase-table td:first-child { flex: 1 1 60%; min-width: 0; }
		.dcase-table td:last-child { flex: 0 0 auto; margin-left: auto; white-space: nowrap !important; }
		#dcase-actions .btn { flex: 1 1 45%; }
		#dcase-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
	}
</style>
@endpush

@push('pageScripts')
<script>
(function ()
{
	var busy = false;
	$('#dcase-actions').on('click', '[data-dcase]', function ()
	{
		var action = $(this).data('dcase');
		if (busy) return;
		busy = true;
		var $b = $('#dcase-actions [data-dcase]').prop('disabled', true);
		$(this).prepend('<i class="fa-solid fa-spinner fa-spin mr-1"></i>');
		Grocy.Api.Post('demo/case/' + action, {}, function () { window.location.reload(); }, function (xhr)
		{
			busy = false;
			$b.prop('disabled', false).find('.fa-spinner').remove();
			var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo completar la acción';
			if (window.toastr) toastr.error(msg); else console.error(msg);
		});
	});
})();
</script>
@endpush
