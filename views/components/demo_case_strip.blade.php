{{-- Caso demo (DAI-YOG-500) en el tablero dueño de cada paso: qué muestra este módulo y enlace al registro exacto. Solo modo demo y caso iniciado. --}}
@php
	$dcView = $viewName ?? '';
	$dcMap = [
		'today' => 'today', 'alert_center' => 'alerts', 'mrp' => 'buy', 'suppliers_procurement' => 'buy', 'purchase' => 'buy',
		'stockoverview' => 'stock', 'kardex' => 'stock', 'stockjournal' => 'stock', 'inventory_stockouts' => 'stock', 'traceability' => 'quality',
		'sales_history' => 'sales', 'pos' => 'sales', 'customers' => 'customer', 'customer_profile' => 'customer', 'orders' => 'order', 'dispatch' => 'order',
		'waste_overview' => 'waste', 'waste_approvals' => 'waste', 'executive_dashboard' => 'finance', 'cost_centers' => 'finance', 'reports_hub' => 'finance',
	];
	$dcKind = $dcMap[$dcView] ?? null;
	$dc = null;
	if ($dcKind && \Grocy\Services\DemoRetailCaseService::Enabled())
	{
		try
		{
			$dcSvc = \Grocy\Services\DemoRetailCaseService::GetInstance();
			if (!empty($dcSvc->State()['steps']))
			{
				$dc = $dcSvc->Summary();
				$dcLinks = $dcSvc->Links($dc['refs']);
				$dcPri = $dcKind === 'today' ? $dcSvc->Priorities() : [];
			}
		}
		catch (\Throwable $e)
		{
			$dc = null;
		}
	}
	$dcM = fn($v) => '$' . number_format((float) $v, 2);
	$dcQ = fn($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2);
	$dcA = function (array $keys) use (&$dcLinks, $U) {
		$out = [];
		foreach ($keys as $k)
		{
			if (isset($dcLinks[$k])) { $out[] = '<a href="' . e($U($dcLinks[$k][0])) . '">' . e($dcLinks[$k][1]) . '</a>'; }
		}
		return implode(' · ', $out);
	};
@endphp
@if($dc)
<div class="dcase-strip alert alert-light border d-flex flex-wrap align-items-center small mb-2" data-testid="demo-case-strip" role="note">
	<a class="badge badge-primary mr-2" href="{{ $U('/demo/case') }}"><i class="fa-solid fa-route mr-1"></i>Caso demo</a>
	<span class="mr-2 text-muted">{{ \Grocy\Services\DemoRetailCaseService::SKU }} · {{ \Grocy\Services\DemoRetailCaseService::LOT }} · Hallandale Beach · {{ substr($dc['cut'] ?? '', 0, 16) }}</span>
	<span class="dcase-strip-body">
	@switch($dcKind)
		@case('today')
			@foreach($dcPri as $p)
			<a class="d-inline-block mr-2 @if($p['done']) text-success @endif" href="{{ $U($p['href']) }}"><i class="fa-solid {{ $p['done'] ? 'fa-circle-check' : 'fa-circle-exclamation text-warning' }}"></i> {{ $p['what'] }} · {{ $p['who'] }}</a>
			@endforeach
			@break
		@case('alerts')
			Alertas del caso: {!! $dcA(['alert_receipt', 'alert_fefo']) !!}
			@break
		@case('buy')
			@if($dc['po'])Orden {{ $dc['refs']['po_code'] }}: {{ $dcQ($dc['po']['ordered']) }} pedidas · {{ $dcQ($dc['po']['received']) }} recibidas · <b>{{ $dcQ($dc['po']['pending']) }} pendientes</b> · @endif{!! $dcA(['po', 'lot']) !!}
			@break
		@case('stock')
			Existencia {{ $dcQ($dc['stock']['qty']) }} u. · {{ $dcM($dc['stock']['value']) }} (bodega {{ $dcQ($dc['stock']['store']) }}, góndola {{ $dcQ($dc['stock']['shelf']) }}, reservado {{ $dcQ($dc['stock']['reserved']) }}, bloqueado {{ $dcQ($dc['stock']['blocked']) }}) · {!! $dcA(['kardex', 'lot']) !!}
			@break
		@case('quality')
			6 u. en riesgo del lote {{ \Grocy\Services\DemoRetailCaseService::LOT }} · bloqueadas {{ $dcQ($dc['stock']['blocked']) }} · baja {{ $dcQ($dc['waste']['qty']) }} u. · {!! $dcA(['task', 'block', 'waste', 'kardex']) !!}
			@break
		@case('sales')
			Ventas del caso {{ $dcM($dc['sales']['net']) }} · {{ $dcQ($dc['sales']['qty']) }} u. (caja {{ $dcM($dc['sales']['pos']) }} + pedido {{ $dcM($dc['sales']['order']) }}) · {!! $dcA(['pos', 'order']) !!}
			@break
		@case('customer')
			Cliente del pedido: compra {{ $dcQ($dc['sales']['order_qty']) }} u. por {{ $dcM($dc['sales']['order']) }} · {!! $dcA(['customer', 'order']) !!}
			@break
		@case('order')
			{!! $dcA(['order']) !!} · 4 u. · {{ $dc['sales']['order'] > 0 ? 'entregado, ' . $dcM($dc['sales']['order']) : 'en curso' }}
			@break
		@case('waste')
			Merma del caso: {{ $dc['waste']['cost'] > 0 ? $dcQ($dc['waste']['qty']) . ' u. por ' . $dcM($dc['waste']['cost']) . ' aprobada' : ($dc['waste']['pending_cost'] > 0 ? $dcM($dc['waste']['pending_cost']) . ' por aprobar (impacto pendiente)' : 'sin registrar') }} · {!! $dcA(['waste', 'kardex']) !!}
			@break
		@case('finance')
			Caso: ventas {{ $dcM($dc['pnl']['net']) }} · costo {{ $dcM($dc['pnl']['cogs']) }} · utilidad bruta {{ $dcM($dc['pnl']['gross']) }} · merma {{ $dcM($dc['pnl']['waste']) }}@if($dc['waste']['pending_cost'] > 0) (+{{ $dcM($dc['waste']['pending_cost']) }} por aprobar)@endif ·
			@if(isset($dc['steps']['opex']))gastos {{ $dcM($dc['pnl']['opex']) }} · <b>resultado operativo {{ $dcM($dc['pnl']['result']) }}</b>@else gastos y resultado operativo: pendientes del cierre @endif
			· <a href="{{ $U('/demo/case') }}">Ver estado de resultados</a>
			@break
	@endswitch
	</span>
</div>
@endif
