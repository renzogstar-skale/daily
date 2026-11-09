{{--
	Indicador normalizado DAILY: nunca una cifra sin contexto. Uso: @include('components.daily_kpi', ['dkpi' => [...]])
	(un solo arreglo para no heredar variables de la vista que lo incluye).
	Claves: name, value (null = «Sin información»), unit ('$', '%', 'h', 'n.º', '$/h', ...), period, scope (sucursal/departamento),
	definition, source, updated, comparison (texto), delta (número, opcional), deltaUnit ('%' o 'pp'), id (data-testid).
--}}
@php
	$k = array_merge(['value' => null, 'unit' => '', 'scope' => null, 'updated' => null, 'comparison' => null, 'delta' => null, 'deltaUnit' => '%', 'id' => null], $dkpi);
	$ku = $k['unit'];
	$kv = $k['value'];
	if ($kv === null) $shown = null;
	elseif (is_string($kv)) $shown = $kv;
	elseif (in_array($ku, ['$', '$/h', '$/persona'], true)) $shown = '$' . number_format((float) $kv, abs($kv) >= 1000 ? 0 : 2);
	elseif ($ku === '%') $shown = number_format((float) $kv, 1) . ' %';
	else $shown = number_format((float) $kv, fmod((float) $kv, 1) == 0 ? 0 : 1);
	$kd = $k['delta'];
@endphp
<div class="dkpi" data-testid="kpi-{{ $k['id'] ?? \Illuminate\Support\Str::slug($k['name']) }}">
	<div class="dkpi-head">
		<span class="dkpi-name">{{ $k['name'] }}</span>
		@if($ku !== '' && !in_array($ku, ['$', '%'], true))<span class="dkpi-unit">{{ $ku }}</span>@endif
	</div>
	<div class="dkpi-value @if($shown === null) is-empty @endif">{{ $shown ?? 'Sin información' }}</div>
	@if(!empty($k['comparison']))
	<div class="dkpi-comp">
		@if($kd !== null)<span class="dkpi-delta {{ $kd >= 0 ? 'is-up' : 'is-down' }}">{{ $kd >= 0 ? '▲ +' : '▼ ' }}{{ number_format($kd, 1) }} {{ $k['deltaUnit'] }}</span>@endif
		<span>{{ $k['comparison'] }}</span>
	</div>
	@endif
	<dl class="dkpi-meta">
		<div><dt>Período</dt><dd>{{ $k['period'] }}</dd></div>
		@if(!empty($k['scope']))<div><dt>Alcance</dt><dd>{{ $k['scope'] }}</dd></div>@endif
		<div><dt>Definición</dt><dd>{{ $k['definition'] }}</dd></div>
		<div><dt>Fuente</dt><dd>{{ $k['source'] }}</dd></div>
		@if(!empty($k['updated']))<div><dt>Actualización</dt><dd>{{ $k['updated'] }}</dd></div>@endif
	</dl>
</div>
