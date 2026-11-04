@extends('layout.default')

@section('title', 'Equipo ' . $d['unit']['imei1_masked'])

@section('content')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@php
	$u = $d['unit'];
	$danger = in_array($u['state'], ['not_located', 'blocked'], true);
@endphp

<div class="lp-page">
	<div class="lp-head">
		<div>
			<h1>{{ $d['product']['name'] }}</h1>
			<p>{{ $d['branch'] }} · {{ $u['zone_label'] }}</p>
		</div>
		<div class="lp-filters">
			@if($u['is_demo'])<span class="lp-badge lp-demo">Datos de demostración</span>@endif
			<span class="lp-badge {{ $danger ? 'lp-danger' : 'lp-neutral' }}"><i class="fa-solid {{ $danger ? 'fa-circle-exclamation' : 'fa-mobile-screen-button' }}"></i> {{ $u['state_label'] }}</span>
			<a class="lp-btn" href="{{ $U('/loss-prevention/devices') }}">Volver</a>
		</div>
	</div>

	<div class="lp-grid-3">
		<div>
			<div class="lp-card">
				<h2>Historial de custodia <span class="lp-badge {{ $d['chain_ok'] ? 'lp-success' : 'lp-danger' }}"><i class="fa-solid {{ $d['chain_ok'] ? 'fa-lock' : 'fa-circle-exclamation' }}"></i> {{ $d['chain_ok'] ? 'Íntegro' : 'Alterado' }}</span></h2>
				<ul class="lp-timeline">
					@foreach($d['events'] as $e)
					<li><span>{{ $e['created_at'] }}</span><span><strong>{{ ucfirst(str_replace('_', ' ', $e['event'])) }}</strong><small>{{ $e['document'] }} {{ $e['note'] }}</small></span><span>{{ $e['username'] ?: 'Sistema' }}@if($e['confirmed_name'])<small>Confirmó: {{ $e['confirmed_name'] }}</small>@endif</span></li>
					@endforeach
				</ul>
			</div>
		</div>
		<div class="lp-side">
			<div class="lp-card">
				<h2>Identificación</h2>
				<dl class="lp-dl">
					<dt>IMEI 1</dt><dd id="dvImei1">{{ $u['imei1_masked'] }}</dd>
					<dt>IMEI 2</dt><dd id="dvImei2">{{ $u['imei2_masked'] ?: '—' }}</dd>
					<dt>Serie</dt><dd id="dvSerial">{{ $u['serial_masked'] ?: '—' }}</dd>
					<dt>Marca</dt><dd>{{ $d['product']['brand'] ?: '—' }}</dd>
					<dt>Color</dt><dd>{{ $u['color'] ?: '—' }}</dd>
					<dt>Capacidad</dt><dd>{{ $u['capacity'] ?: '—' }}</dd>
					<dt>SKU</dt><dd>{{ $d['product']['sku'] }}</dd>
					<dt>UPC</dt><dd>{{ $d['product']['upc'] ?: '—' }}</dd>
				</dl>
				@if($d['can_reveal'])
				<button class="lp-btn mt-2" id="dvReveal" type="button">Ver identificadores completos</button>
				<p class="lp-note">La consulta queda registrada.</p>
				@endif
			</div>
			<div class="lp-card">
				<h2>Origen y custodia</h2>
				<dl class="lp-dl">
					<dt>Proveedor</dt><dd>{{ $u['supplier_name'] ?: '—' }}</dd>
					<dt>Orden</dt><dd>{{ $u['order_code'] ?: '—' }}</dd>
					<dt>Recepción</dt><dd>{{ $d['receipt']['code'] ?? '—' }}</dd>
					<dt>Ingreso</dt><dd>{{ $u['received_at'] }}</dd>
					<dt>Ubicación</dt><dd>{{ $d['location'] }} · {{ $u['zone_label'] }}</dd>
					<dt>Costo</dt><dd>${{ number_format($u['unit_cost'], 2) }}</dd>
					<dt>Último registro confirmado</dt><dd>{{ $u['last_confirmed_at'] ?: '—' }}</dd>
					<dt>Área</dt><dd>{{ $u['custodian_area'] ?: '—' }}</dd>
					<dt>Custodio del registro</dt><dd>{{ $d['custodian'] ?: '—' }}</dd>
					@if($d['sale'])<dt>Venta</dt><dd>{{ $d['sale']['sale_number'] }}</dd>@endif
				</dl>
				<p class="lp-note">La custodia es un dato de trazabilidad: no atribuye responsabilidad por una diferencia.</p>
			</div>
			@if($d['case'])
			<div class="lp-card">
				<h2>Expediente</h2>
				<a class="lp-btn" href="{{ $U('/loss-prevention/case/' . $d['case']['id']) }}">{{ $d['case']['case_code'] }}</a>
			</div>
			@endif
		</div>
	</div>
</div>

<script>
window.addEventListener('load', function ()
{
	var b = document.getElementById('dvReveal');
	if (b)
	{
		b.addEventListener('click', function ()
		{
			Grocy.Api.Get('devices/{{ intval($u['id']) }}/reveal', function (r)
			{
				document.getElementById('dvImei1').textContent = r.imei1;
				document.getElementById('dvImei2').textContent = r.imei2 || '—';
				document.getElementById('dvSerial').textContent = r.serial_number || '—';
			}, function () { toastr.error('Sin permiso'); });
		});
	}
});
</script>
@stop
