{{-- Etapa 36: inteligencia de hurto (Censo). $intel = LossCensusService::TheftIntel. Sin datos personales. --}}
@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$I = $intel;
	$BT = $I['by_type'];
	$Rc = $I['recovery'];
	$mods = $I['catalog']['modalities'];
@endphp
<div class="mt-4" id="lt-intel">
	<h6 class="font-weight-700 mb-1">Inteligencia de hurto y fraude</h6>
	<div class="dlx-sub mb-2">Tipos, modalidades, canal de detección y recuperación de los casos <strong>confirmados</strong>. Los sospechosos permanecen en investigación con revisión humana; no se registran datos de personas.</div>
	<div class="dlx-kpis">
		<div class="dlx-kpi dlx-tone-graphite"><div class="k-l">Robo interno</div><div class="k-v">{{ $money($BT['interno']['cost']) }}</div><div class="k-s">{{ $BT['interno']['incidents'] }} caso(s)</div></div>
		<div class="dlx-kpi dlx-tone-red"><div class="k-l">Robo externo</div><div class="k-v">{{ $money($BT['externo']['cost']) }}</div><div class="k-s">{{ $BT['externo']['incidents'] }} caso(s)</div></div>
		<div class="dlx-kpi"><div class="k-l">Fraude POS</div><div class="k-v">{{ $money($BT['fraude_pos']['cost']) }}</div><div class="k-s">{{ $BT['fraude_pos']['incidents'] }} caso(s)</div></div>
		<div class="dlx-kpi" title="Fraude electrónico: tarjeta, transferencia falsa, clonación de facturas"><div class="k-l">Fraude digital</div><div class="k-v">{{ $money($BT['fraude_electronico']['cost']) }}</div><div class="k-s">{{ $BT['fraude_electronico']['incidents'] }} caso(s)</div></div>
		<div class="dlx-kpi dlx-tone-amber"><div class="k-l">Sospechosos</div><div class="k-v">{{ $I['suspected']['count'] }}</div><div class="k-s">{{ $money($I['suspected']['cost']) }} sin confirmar</div></div>
		<div class="dlx-kpi dlx-tone-sage" title="Mercancía {{ $money($Rc['goods']) }} · financiera {{ $money($Rc['financial']) }}"><div class="k-l">Recuperación</div><div class="k-v">{{ $Rc['pct'] !== null ? number_format($Rc['pct'], 1) . '%' : '—' }}</div><div class="k-s">{{ $money($Rc['total']) }} recuperado</div></div>
		<div class="dlx-kpi"><div class="k-l">Merc. recuperada</div><div class="k-v">{{ $money($Rc['goods']) }}</div><div class="k-s">financiera {{ $money($Rc['financial']) }}</div></div>
		<div class="dlx-kpi"><div class="k-l">Incid./1.000 tick.</div><div class="k-v">{{ $I['rate']['per_1000_tickets'] !== null ? number_format($I['rate']['per_1000_tickets'], 2) : '—' }}</div><div class="k-s">{{ number_format($I['rate']['per_month'], 2) }} por mes</div></div>
		<div class="dlx-kpi {{ $I['pending_details'] ? 'dlx-tone-amber' : '' }}"><div class="k-l">Sin modalidad</div><div class="k-v">{{ $I['pending_details'] }}</div><div class="k-s">casos por completar</div></div>
	</div>
	@if($I['incidents'] === 0)
		<div class="dlx-empty">Sin casos confirmados de robo o fraude en el período. Referencia Censo: la recuperación redujo la merma en {{ number_format($Rc['bench_pct'] ?? 0, 2) }}% (datos 2022).</div>
	@else
	<div class="row">
		<div class="col-lg-4 mb-3"><h6 class="font-weight-700 mb-1 small">Por tipo</h6><div class="dlx-chart is-sm"><canvas id="ltiTypes" role="img" aria-label="Pérdida por tipo de hurto"></canvas></div></div>
		<div class="col-lg-8 mb-3"><h6 class="font-weight-700 mb-1 small">Tendencia temporal</h6><div class="dlx-chart is-sm"><canvas id="ltiTrend" role="img" aria-label="Tendencia mensual de incidentes y pérdida"></canvas></div></div>
	</div>
	<div class="row">
		<div class="col-lg-6 mb-3"><h6 class="font-weight-700 mb-1 small">Modalidades</h6><div class="dlx-chart is-sm mb-2"><canvas id="ltiMods" role="img" aria-label="Pérdida por modalidad"></canvas></div>
			<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="Modalidades de hurto"><thead><tr><th>Tipo</th><th>Modalidad</th><th class="text-right">Casos</th><th class="text-right">Pérdida</th></tr></thead>
				<tbody>@foreach($I['modalities'] as $m)<tr><td class="small">{{ $BT[$m['type']]['label'] }}</td><td>{{ $m['label'] }}</td><td class="text-right">{{ $m['incidents'] }}</td><td class="text-right">{{ $money($m['cost']) }}</td></tr>@endforeach</tbody></table></div></div>
		<div class="col-lg-6 mb-3"><h6 class="font-weight-700 mb-1 small">Canal de detección</h6><div class="dlx-chart is-sm mb-2"><canvas id="ltiChan" role="img" aria-label="Casos por canal de detección"></canvas></div>
			<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="Canal de detección"><thead><tr><th>Canal</th><th class="text-right">Casos</th><th class="text-right">Pérdida</th></tr></thead>
				<tbody>@foreach($I['channels'] as $c)<tr><td>{{ $c['label'] }}</td><td class="text-right">{{ $c['incidents'] }}</td><td class="text-right">{{ $money($c['cost']) }}</td></tr>@endforeach</tbody></table></div></div>
	</div>
	<details @if($I['pending_details']) open @endif><summary class="font-weight-600">Casos confirmados: modalidad, canal y recuperación ({{ count($I['events']) }})</summary>
		<div class="table-responsive mt-2"><table class="table table-sm dlx-table" data-export-title="Casos de hurto con detalle">
			<thead><tr><th>Fecha</th><th>Caso</th><th>Tipo</th><th>Modalidad</th><th class="dlx-hide-sm">Canal</th><th class="text-right">Pérdida</th><th class="text-right dlx-hide-sm">Recuperado</th><th></th></tr></thead>
			<tbody>@foreach($I['events'] as $e)
				<tr><td class="small text-nowrap">{{ $e['d'] }}</td><td><a href="{{ $U('/document/waste/' . $e['ref']) }}">{{ $e['doc'] }}</a></td><td class="small">{{ $e['type'] }}</td>
					<td class="small">{!! $e['modality'] ? e($e['modality']) : '<span class="dlx-chip is-amber">sin registrar</span>' !!}</td><td class="small dlx-hide-sm">{{ $e['channel'] ?? '—' }}</td>
					<td class="text-right">{{ $money($e['cost']) }}</td><td class="text-right dlx-hide-sm">{{ $money($e['goods'] + $e['recovered']) }}</td>
					<td><button type="button" class="btn btn-xs btn-light border" onclick="dlxTheftDetail({{ intval($e['ref']) }}, {{ json_encode($e['type_key']) }}, {{ json_encode(['m' => $e['theft_modality'], 'c' => $e['detection_channel'], 'g' => $e['goods'], 'f' => $e['recovered']]) }})">Completar</button></td></tr>
			@endforeach</tbody>
		</table></div>
	</details>
	@endif
	<script>
		(function () {
			var I = {!! json_encode(['by_type' => $BT, 'monthly' => $I['monthly'], 'modalities' => $I['modalities'], 'channels' => $I['channels'], 'incidents' => $I['incidents']]) !!}, C = DairyLoss.colors;
			if (I.incidents) {
				DairyLoss.theftTypes('ltiTypes', I.by_type);
				DairyLoss.theftTrend('ltiTrend', I.monthly);
				DairyLoss.ranked('ltiMods', I.modalities.map(function (m) { return { label: m.label, value: m.cost, key: m.key }; }), C.graphite, 'cost');
				DairyLoss.ranked('ltiChan', I.channels.map(function (c) { return { label: c.label, value: c.incidents, key: c.key }; }), C.teal, 'units');
			}
		})();
		window.dlxTheftDetail = function (id, type, cur) {
			var mods = {!! json_encode($mods) !!}[type] || {}, chans = {!! json_encode($I['catalog']['channels']) !!};
			var o = function (obj, sel) { return '<option value="">Sin registrar</option>' + Object.keys(obj).map(function (k) { return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + DairyUI.esc(obj[k]) + '</option>'; }).join(''); };
			var html = '<div class="form-group"><label>Modalidad</label><select name="theft_modality" class="form-control">' + o(mods, cur.m) + '</select></div>'
				+ '<div class="form-group"><label>Canal de detección</label><select name="detection_channel" class="form-control">' + o(chans, cur.c) + '</select></div>'
				+ '<div class="form-row"><div class="form-group col-6"><label>Mercancía recuperada ($)</label><input type="number" step="0.01" min="0" name="recovered_goods_value" class="form-control" value="' + (cur.g || '') + '"></div>'
				+ '<div class="form-group col-6"><label>Recuperación financiera ($)</label><input type="number" step="0.01" min="0" name="financial_recovery_amount" class="form-control" value="' + (cur.f || '') + '"></div></div>'
				+ '<p class="small text-muted mb-0">No registre nombres ni datos de personas: la investigación se gestiona en Prevención de pérdidas con revisión humana.</p>';
			DairyLoss.detailsDialog(id, 'Detalle del caso', html);
		};
	</script>
</div>
