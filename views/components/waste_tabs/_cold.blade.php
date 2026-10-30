@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$exc = $cold['excursions'];
	$trendK = array_keys($cold['trend']);
@endphp
<div class="dlx-section">
	<h5>Cadena de frío: excursiones y producto comprometido</h5>
	<div class="dlx-sub">Rango refrigeración {{ $cold['ranges']['refrigerated'][0] }}–{{ $cold['ranges']['refrigerated'][1] }} °C · congelación ≤ {{ $cold['ranges']['freezer_max'] }} °C · {{ $cold['readings'] }} lecturas. Un producto expuesto nunca se libera automáticamente: cuarentena → evaluación de calidad → decisión autorizada.</div>
	<div class="dlx-kpis">
		<div class="dlx-kpi {{ count($exc) ? 'dlx-tone-red' : 'dlx-tone-sage' }}"><div class="k-l">Excursiones</div><div class="k-v">{{ count($exc) }}</div><div class="k-s">{{ count(array_filter($exc, fn($e) => $e['ongoing'])) }} en curso</div></div>
		<div class="dlx-kpi dlx-tone-amber"><div class="k-l">Exposición total</div><div class="k-v">{{ array_sum(array_column($exc, 'minutes')) }} min</div><div class="k-s">fuera de rango</div></div>
		<div class="dlx-kpi dlx-tone-red"><div class="k-l">Valor en riesgo</div><div class="k-v">{{ $money(array_sum(array_column($exc, 'value_at_risk'))) }}</div><div class="k-s">lotes hoy en esas ubicaciones</div></div>
		<div class="dlx-kpi"><div class="k-l">Merma por frío</div><div class="k-v">{{ $money(array_sum(array_map(fn($r) => floatval($r['cost']), $cold['cold_chain_loss']))) }}</div><div class="k-s">causal CFR confirmada</div></div>
		<a class="dlx-kpi" href="{{ $U('/quality') }}"><div class="k-l">Calidad</div><div class="k-v"><i class="fa-solid fa-arrow-right"></i></div><div class="k-s">registrar lectura / cuarentena</div></a>
	</div>
	@if(!$cold['readings'])
		<div class="dlx-empty">Sin lecturas de temperatura en el período. Conecte sensores o registre lecturas en Calidad.<div><a class="btn btn-sm btn-outline-dark" href="{{ $U('/quality') }}">Ir a Calidad</a></div></div>
	@else
	<div class="row">
		<div class="col-lg-5 mb-3"><h6 class="font-weight-700 mb-1">Tendencia: lecturas fuera de rango por día</h6><div class="dlx-chart is-sm"><canvas id="lcTrend" role="img" aria-label="Tendencia de cadena de frío"></canvas></div></div>
		<div class="col-lg-7 mb-3">
			<h6 class="font-weight-700 mb-1">Excursiones</h6>
			@forelse($exc as $e)
			<div class="border rounded p-2 mb-2">
				<div class="d-flex justify-content-between flex-wrap"><strong>{{ $e['location'] }}</strong><span class="dlx-chip {{ $e['ongoing'] ? 'is-red' : 'is-amber' }}">{{ $e['ongoing'] ? 'en curso' : 'cerrada' }} · {{ $e['minutes'] }} min · máx {{ $e['max_temp'] }} °C</span></div>
				<div class="small text-muted">{{ $e['start'] }} → {{ $e['end'] ?? 'ahora' }} · {{ $e['readings'] }} lectura(s) fuera de rango @if($e['action'])· {{ $e['action'] }}@endif</div>
				@if($e['lots'])
				<table class="table table-sm dlx-table mb-0 mt-1" data-export-title="Lotes comprometidos {{ $e['location'] }}"><thead><tr><th>Producto</th><th class="text-right">Cant.</th><th>Vence</th><th class="text-right">Valor</th><th></th></tr></thead><tbody>
					@foreach($e['lots'] as $l)<tr><td>{{ $l['name'] }}</td><td class="text-right">{{ floatval($l['amount']) }}</td><td>{{ $l['best_before_date'] }}</td><td class="text-right">{{ $money($l['value']) }}</td>
						<td class="text-right">@if($l['quarantined'])<span class="dlx-chip is-amber">en cuarentena</span>@elseif($canQuarantine)<button class="btn btn-sm btn-outline-danger" onclick='DairyLoss.post("quality/quarantine", {product_id: {{ $l['product_id'] }}, stock_id: {!! json_encode($l['stock_id']) !!}, location_id: {{ $e['location_id'] }}, branch_id: {{ $e['branch_id'] }}, amount: {{ floatval($l['amount']) }}, reason: {!! json_encode('Excursión de temperatura ' . $e['location'] . ' (' . $e['minutes'] . ' min, máx ' . $e['max_temp'] . ' °C)') !!}}, function(){ toastr.success("Lote en cuarentena: pendiente de evaluación de calidad"); dairyRefreshActiveTab(); }, this);'>Cuarentena</button>@endif</td></tr>@endforeach
				</tbody></table>
				@else<div class="small text-muted">Sin lotes hoy en la ubicación.</div>@endif
			</div>
			@empty
			<div class="dlx-empty">Sin excursiones de temperatura en el período.</div>
			@endforelse
		</div>
	</div>
	<script>(function () { var t = {!! json_encode($cold['trend']) !!}, k = {!! json_encode($trendK) !!};
		DairyLoss.bars('lcTrend', k, [{ label: 'Fuera de rango', data: k.map(function (d) { return t[d].out; }), backgroundColor: DairyLoss.colors.red }, { label: 'En rango', data: k.map(function (d) { return t[d].readings - t[d].out; }), backgroundColor: DairyLoss.colors.sageSoft }], 'units', true); })();</script>
	@endif
</div>
