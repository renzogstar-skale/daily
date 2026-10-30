{{-- Etapa 36: perecederos por familia y destino final (desperdicios, donaciones, compostaje). $dest = LossCensusService::PerishableDestinations --}}
@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$kg = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . ' kg';
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$D = $dest;
	$Y = $D['yield'];
	$famTotal = array_sum(array_column($D['families'], 'cost'));
@endphp
<div class="mt-4" id="lp-destinos">
	<div class="d-flex justify-content-between align-items-start flex-wrap dairy-gap-2">
		<div><h6 class="font-weight-700 mb-1">Perecederos por familia, causal y destino final</h6>
			<div class="dlx-sub mb-2">Eventos confirmados de la fuente única (mermas aprobadas, ajustes y averías): unidades, peso, costo, % de ventas, causal y evidencia. El destino final (donación, compostaje…) no cambia el costo de la merma: solo indica qué se hizo con el producto.</div></div>
		<a class="btn btn-sm btn-light border" href="#tab-conteos" onclick="$('#tab-conteos-link').tab('show'); return false;"><i class="fa-solid fa-bell mr-1"></i>Alertas de inventario selectivo</a>
	</div>
	<div class="dlx-kpis">
		<div class="dlx-kpi dlx-tone-sage"><div class="k-l">Donado</div><div class="k-v">{{ $money($D['donated']['cost']) }}</div><div class="k-s">{{ $D['donated']['events'] }} evento(s) · {{ $kg($D['donated']['weight_kg']) }}</div></div>
		<div class="dlx-kpi dlx-tone-sage"><div class="k-l">Compostaje</div><div class="k-v">{{ $money($D['composted']['cost']) }}</div><div class="k-s">{{ $D['composted']['events'] }} evento(s) · {{ $kg($D['composted']['weight_kg']) }}</div></div>
		<div class="dlx-kpi dlx-tone-amber" title="{{ $D['supplier_returns']['count'] }} devolución(es) a proveedor"><div class="k-l">Devol. proveedor</div><div class="k-v">{{ $money($D['supplier_returns']['cost']) }}</div><div class="k-s">acreditado {{ $money($D['supplier_returns']['recovered']) }}</div></div>
		<div class="dlx-kpi"><div class="k-l">Transporte</div><div class="k-v">{{ $money($D['transport']) }}</div><div class="k-s">causal TRA{{ $Y && $Y['transport'] ? ' · ' . $kg($Y['transport']) . ' en lotes' : '' }}</div></div>
		<div class="dlx-kpi"><div class="k-l">Roturas</div><div class="k-v">{{ $money($D['breakage']['cost']) }}</div><div class="k-s">{{ $D['breakage']['events'] }} evento(s)</div></div>
		<div class="dlx-kpi"><div class="k-l">Daño externo</div><div class="k-v">{{ $money($D['external_damage']['cost']) }}</div><div class="k-s">{{ $D['external_damage']['events'] }} evento(s)</div></div>
		<div class="dlx-kpi dlx-tone-red"><div class="k-l" title="Vencimiento y error de rotación FEFO">Vencimiento</div><div class="k-v">{{ $money($D['expiry']['cost']) }}</div><div class="k-s">{{ $D['expiry']['events'] }} evento(s)</div></div>
		<div class="dlx-kpi dlx-tone-graphite"><div class="k-l">Drenaje cárnicos</div><div class="k-v">{{ $Y ? $kg($Y['water'] + $Y['blood']) : '—' }}</div><div class="k-s" title="{{ $Y ? 'Agua ' . $kg($Y['water']) . ' · sangre ' . $kg($Y['blood']) : '' }}">{{ $Y ? 'agua + sangre' : 'sin lotes' }}</div></div>
	</div>
	<div class="row">
		<div class="col-lg-7 mb-3"><h6 class="font-weight-700 mb-1 small">Pérdida por familia (barras) y % de sus ventas (puntos)</h6>
			@if($famTotal != 0)
				<div class="dlx-chart is-sm"><canvas id="lpdFam" role="img" aria-label="Pérdida por familia de perecederos"></canvas></div>
			@else
				<div class="dlx-empty">Sin eventos en Fruver, Carnes y pollo, Lácteos o Panadería en el período.</div>
			@endif
		</div>
		<div class="col-lg-5 mb-3"><h6 class="font-weight-700 mb-1 small">Desperdicios y donaciones (destino final)</h6>
			@if(array_sum(array_column($D['destinations'], 'cost')) > 0)
				<div class="dlx-chart is-sm"><canvas id="lpdDest" role="img" aria-label="Merma por destino final"></canvas></div>
			@else
				<div class="dlx-empty">Sin mermas en el período.</div>
			@endif
		</div>
	</div>
	<div class="row">
		<div class="col-lg-7 mb-3">
			<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="Perecederos por familia">
				<thead><tr><th>Familia</th><th class="text-right">Eventos</th><th class="text-right">Unid.</th><th class="text-right">Peso</th><th class="text-right">Costo</th><th class="text-right">% ventas</th><th class="dlx-hide-sm">Causal principal</th><th class="text-right dlx-hide-sm">Con evidencia</th></tr></thead>
				<tbody>@foreach($D['families'] as $x)
					<tr><td>{{ $x['label'] }}</td><td class="text-right">{{ $x['events'] }}</td><td class="text-right">{{ number_format($x['units'], 2) }}</td><td class="text-right">{{ $x['weight_kg'] ? $kg($x['weight_kg']) : '—' }}</td><td class="text-right">{{ $money($x['cost']) }}</td><td class="text-right">{{ $pct($x['pct_sales']) }}</td>
						<td class="small dlx-hide-sm">{{ $x['cause_names'][0] ?? '—' }}</td><td class="text-right dlx-hide-sm">{{ $x['events'] ? $x['with_evidence'] . '/' . $x['events'] : '—' }}</td></tr>
				@endforeach</tbody>
			</table></div>
		</div>
		<div class="col-lg-5 mb-3">
			<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="Merma por destino final">
				<thead><tr><th>Destino</th><th class="text-right">Eventos</th><th class="text-right">Peso</th><th class="text-right">Costo</th><th class="text-right">%</th></tr></thead>
				<tbody>@foreach($D['destinations'] as $x)<tr><td>{{ $x['label'] }}</td><td class="text-right">{{ $x['events'] }}</td><td class="text-right">{{ $x['weight_kg'] ? $kg($x['weight_kg']) : '—' }}</td><td class="text-right">{{ $money($x['cost']) }}</td><td class="text-right">{{ $pct($x['share']) }}</td></tr>@endforeach</tbody>
			</table></div>
		</div>
	</div>
	<details><summary class="font-weight-600">Eventos de perecederos ({{ count($D['events']) }})</summary>
		<div class="table-responsive mt-2"><table class="table table-sm dlx-table" data-export-title="Eventos de perecederos">
			<thead><tr><th>Fecha</th><th>Documento</th><th>Familia</th><th>Producto</th><th class="text-right">Unid.</th><th class="text-right">Peso</th><th class="text-right">Costo</th><th class="dlx-hide-sm">Causal</th><th>Destino</th><th>Evidencia</th><th></th></tr></thead>
			<tbody>@foreach($D['events'] as $e)
				@php $docUrl = ['waste' => '/document/waste/', 'adjustment' => null, 'inventory_adjustment' => '/document/adjustment/', 'damage' => null][$e['src']] ?? null; @endphp
				<tr><td class="small text-nowrap">{{ $e['d'] }}</td><td class="small">@if($docUrl)<a href="{{ $U($docUrl . $e['ref']) }}">{{ $e['doc'] ?: $e['uid'] }}</a>@else<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}">{{ $e['doc'] ?: ($e['src'] === 'damage' ? 'Avería' : 'Ajuste de stock') }}</a>@endif</td>
					<td class="small">{{ $e['family'] }}</td><td>{{ $e['product'] }}</td><td class="text-right">{{ number_format($e['units'], 2) }}</td><td class="text-right">{{ $e['weight_kg'] !== null ? $kg($e['weight_kg']) : '—' }}</td>
					<td class="text-right">{{ $money($e['cost']) }}</td><td class="small dlx-hide-sm">{{ $e['cause'] }}</td><td class="small">{{ $e['destination'] ?? '—' }}</td>
					<td class="small">@if($e['evidence'])<a href="{{ $U($e['evidence']) }}" target="_blank" rel="noopener"><i class="fa-solid fa-camera"></i> ver</a>@else — @endif</td>
					<td>@if($e['src'] === 'waste')<button type="button" class="btn btn-xs btn-light border" onclick="dlxDestDetail({{ intval($e['ref']) }})">Destino</button>@endif</td></tr>
			@endforeach</tbody>
		</table></div>
	</details>
	<script>
		(function () {
			var F = {!! json_encode($D['families']) !!}, R = {!! json_encode($D['destinations']) !!};
			if (document.getElementById('lpdFam')) { DairyLoss.families('lpdFam', F); }
			if (document.getElementById('lpdDest')) { DairyLoss.destinations('lpdDest', R); }
		})();
		window.dlxDestDetail = function (id) {
			var d = {!! json_encode($D['destinations_catalog']) !!};
			var html = '<div class="form-group"><label>Destino final</label><select name="final_destination" class="form-control"><option value="">Sin registrar</option>' + Object.keys(d).map(function (k) { return '<option value="' + k + '">' + DairyUI.esc(d[k]) + '</option>'; }).join('') + '</select></div>'
				+ '<div class="form-group"><label>Peso (kg)</label><input type="number" step="0.001" min="0" name="weight_kg" class="form-control"></div>';
			DairyLoss.detailsDialog(id, 'Destino final de la merma', html);
		};
	</script>
</div>
