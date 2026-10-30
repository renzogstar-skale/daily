@php require_frontend_packages(['bwipjs']); @endphp

@extends('layout.default')

@section('title', 'Etiquetas de ubicación')

@section('content')
@php
	$classLabel = ['ambient' => 'Ambiente', 'chilled' => 'Refrigerado', 'frozen' => 'Congelado'];
@endphp
<div class="row align-items-center mb-3 dairy-page-header d-print-none">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-qrcode"></i></div>
		<div>
			<h4 class="dairy-page-title">Etiquetas de ubicación</h4>
			<p class="dairy-page-subtitle">Imprima y pegue una etiqueta por ubicación: al escanearla en Operaciones con escáner se selecciona la ubicación o se ve su contenido.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right dairy-page-actions">
		<form method="get" class="d-inline-flex dairy-gap-2">
			<select name="branch_id" class="custom-select custom-select-sm w-auto" onchange="this.form.submit()" aria-label="Sucursal">
				<option value="">Todas las sucursales</option>
				@foreach($branches as $b)<option value="{{ $b['id'] }}" @if($b['id'] == $branchId) selected @endif>{{ $b['name'] }}</option>@endforeach
			</select>
			<select name="type" class="custom-select custom-select-sm w-auto" onchange="this.form.submit()" aria-label="Simbología">
				<option value="qrcode" @if($symbology === 'qrcode') selected @endif>QR</option>
				<option value="datamatrix" @if($symbology === 'datamatrix') selected @endif>Data Matrix</option>
				<option value="code128" @if($symbology === 'code128') selected @endif>Code 128</option>
			</select>
		</form>
		<button type="button" class="btn btn-sm btn-primary ml-1" onclick="window.print()"><i class="fa-solid fa-print mr-1"></i>Imprimir</button>
		<a class="btn btn-sm btn-outline-secondary ml-1" href="{{ $U('/locations') }}"><i class="fa-solid fa-pen mr-1"></i>Editar ubicaciones</a>
	</div>
</div>

@if(count($locations) === 0)
<div class="card"><div class="card-body text-muted">No hay ubicaciones activas para esta selección.</div></div>
@else
<div class="dairy-label-grid">
	@foreach($locations as $l)
	@php
		$path = array_values(array_filter([$l['warehouse'], $l['zone'], $l['aisle'] ? 'P' . $l['aisle'] : null, $l['shelf'] ? 'E' . $l['shelf'] : null, $l['bin'] ? 'U' . $l['bin'] : null]));
	@endphp
	<div class="dairy-label @if($symbology === 'code128') is-linear @endif">
		<canvas data-code="{{ $l['label_code'] }}" data-type="{{ $symbology }}" role="img" aria-label="Código {{ $l['label_code'] }}"></canvas>
		<div class="min-w-0">
			<div class="font-weight-700 text-truncate">{{ $l['name'] }}</div>
			<div class="small">{{ $l['label_code'] }}</div>
			<div class="small text-muted text-truncate">{{ $l['branch_name'] ?? 'Sin sucursal' }}@if(count($path)) · {{ implode(' › ', $path) }}@endif</div>
			@if($l['storage_class'])<div class="small text-muted">{{ $classLabel[$l['storage_class']] ?? $l['storage_class'] }}@if($l['capacity_units']) · cap. {{ floatval($l['capacity_units']) }}@endif</div>@endif
		</div>
	</div>
	@endforeach
</div>
@endif
@stop
