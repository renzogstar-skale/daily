{{-- ==========================================================================
     DAILY — COMPARATIVO DE VENTAS TY vs. LY (dentro de Rendimiento por Departamento)
     Misma base de ventas y filtros compartidos (DailyFilters); el desglose por
     departamento (productos/SKU, sucursales, agotados, devoluciones) se carga bajo demanda.
     ========================================================================== --}}
@php
	$tl = $tyLy ?? ['departments' => [], 'totals' => [], 'period' => [], 'ly_source' => 'real'];
	$tlT = $tl['totals'] ?? [];
	$tlDemo = ($tl['ly_source'] ?? 'real') === 'demo';
	$tlMoney = fn($v) => ($v < 0 ? '−$' : '$') . number_format(abs($v), 0);
	$tlPct = fn($v) => $v === null ? 'Nuevo' : (($v > 0 ? '+' : ($v < 0 ? '−' : '')) . number_format(abs($v), 1) . ' %');
	$tlMax = max(1, ...array_map(fn($d) => max($d['ty'], $d['ly']), $tl['departments'] ?: [['ty' => 1, 'ly' => 1]]));
	$tlFilters = array_intersect_key($sharedFilters ?? [], array_flip(\Grocy\Helpers\DailyFilters::ALL));
@endphp

<section class="ded-card tyly" id="tylySection" aria-labelledby="tylyTitle"
	data-api="{{ $U('/api/sales/ty-ly') }}"
	data-stockouts-url="{{ $U('/inventory/stockouts') }}"
	data-returns-url="{{ $U('/pos/returns') }}"
	data-products-url="{{ $U('/sales') }}"
	data-filters='@json($tlFilters)'>

	<header class="tyly-head">
		<div class="tyly-head-title">
			<span class="tyly-head-icon" aria-hidden="true"><i data-lucide="git-compare-arrows"></i></span>
			<div>
				<h2 class="ded-card-title" id="tylyTitle">Comparativo de Ventas TY vs. LY</h2>
				<p class="tyly-sub">
					<span id="tylyPeriod">{{ $tl['period']['label'] ?? '' }}</span>
					<span class="tyly-sub-sep">vs.</span>
					<span id="tylyPeriodLy">{{ $tl['period']['ly_label'] ?? '' }}</span>
				</p>
			</div>
		</div>
		<div class="tyly-tools">
			<span class="tyly-source {{ $tlDemo ? 'is-demo' : 'is-real' }}" id="tylySource"
				title="{{ $tlDemo ? 'No hay ventas del año anterior en la base: LY es una referencia de ejemplo calculada para la demostración. TY sí es la venta registrada.' : 'LY calculado con las ventas registradas del mismo período del año anterior.' }}">
				<i data-lucide="{{ $tlDemo ? 'flask-conical' : 'database' }}"></i>
				<span>{{ $tlDemo ? 'LY de ejemplo (demo)' : 'LY real' }}</span>
			</span>
			<label class="tyly-select" title="Categoría (filtro compartido)">
				<i data-lucide="tags"></i>
				<select id="tylyCategory" aria-label="Categoría">
					<option value="">Todas las categorías</option>
					@foreach(($filterOptions['groups'] ?? []) as $g)
					<option value="{{ $g['id'] }}" @if(($sharedFilters['group_id'] ?? null) === intval($g['id'])) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</label>
			<div class="tyly-seg" role="group" aria-label="Ordenar">
				<button type="button" class="is-active" data-tyly-sort="sales" aria-pressed="true"><i data-lucide="bar-chart-3"></i><span>Ventas</span></button>
				<button type="button" data-tyly-sort="growth" aria-pressed="false"><i data-lucide="trending-up"></i><span>Crecimiento</span></button>
				<button type="button" data-tyly-sort="drop" aria-pressed="false"><i data-lucide="trending-down"></i><span>Caída</span></button>
			</div>
		</div>
	</header>

	<div class="tyly-summary" id="tylySummary">
		<div class="tyly-stat">
			<span class="tyly-stat-label">Ventas TY</span>
			<strong class="tyly-stat-value" data-tyly-total="ty">{{ $tlMoney($tlT['ty'] ?? 0) }}</strong>
		</div>
		<div class="tyly-stat">
			<span class="tyly-stat-label">Ventas LY @if($tlDemo)<em class="tyly-demo-tag">demo</em>@endif</span>
			<strong class="tyly-stat-value" data-tyly-total="ly">{{ $tlMoney($tlT['ly'] ?? 0) }}</strong>
		</div>
		<div class="tyly-stat">
			<span class="tyly-stat-label">Diferencia</span>
			<strong class="tyly-stat-value {{ ($tlT['diff'] ?? 0) < 0 ? 'is-down' : 'is-up' }}" data-tyly-total="diff">{{ $tlMoney($tlT['diff'] ?? 0) }}
				<small data-tyly-total="var">{{ $tlPct($tlT['var_pct'] ?? null) }}</small></strong>
		</div>
		<div class="tyly-stat is-alert">
			<span class="tyly-stat-label">Departamentos bajo LY</span>
			<strong class="tyly-stat-value is-down" data-tyly-total="declining">{{ $tlT['declining_count'] ?? 0 }}
				<small data-tyly-total="gap">{{ $tlMoney($tlT['declining_gap'] ?? 0) }}</small></strong>
		</div>
	</div>

	<div class="tyly-table-wrap" id="tylyTableWrap" aria-busy="false">
		<table class="tyly-table" aria-label="Ventas por departamento, este año contra el año anterior">
			<thead>
				<tr>
					<th scope="col">Departamento</th>
					<th scope="col" class="text-right">Ventas TY</th>
					<th scope="col" class="text-right">Ventas LY</th>
					<th scope="col" class="text-right">Diferencia</th>
					<th scope="col" class="text-right">Var. %</th>
					<th scope="col" class="tyly-col-bar">TY vs. LY</th>
					<th scope="col" class="tyly-col-signals">Dónde investigar</th>
					<th scope="col" class="tyly-col-chevron"><span class="sr-only">Desglose</span></th>
				</tr>
			</thead>
			<tbody id="tylyBody">
				@forelse($tl['departments'] as $d)
				@php $down = $d['diff'] < 0; @endphp
				<tr class="tyly-row {{ $down ? 'is-down' : 'is-up' }}" data-key="{{ $d['key'] }}" tabindex="0" role="button" aria-expanded="false"
					aria-label="Ver desglose de {{ $d['name'] }}">
					<td>
						<span class="tyly-dept">
							<span class="tyly-dept-icon" style="--dept: {{ $d['color'] }};"><i data-lucide="{{ $d['icon'] }}"></i></span>
							<span class="tyly-dept-name">{{ $d['name'] }}<small>{{ number_format($d['share_pct'], 1) }} % de la venta</small></span>
						</span>
					</td>
					<td class="text-right tyly-num">{{ $tlMoney($d['ty']) }}</td>
					<td class="text-right tyly-num tyly-muted">{{ $tlMoney($d['ly']) }}</td>
					<td class="text-right tyly-num tyly-diff">{{ $tlMoney($d['diff']) }}</td>
					<td class="text-right"><span class="tyly-pill {{ $down ? 'is-down' : 'is-up' }}"><i data-lucide="{{ $down ? 'arrow-down-right' : 'arrow-up-right' }}"></i>{{ $tlPct($d['var_pct']) }}</span></td>
					<td class="tyly-col-bar">
						<span class="tyly-bars" aria-hidden="true">
							<span class="tyly-bar is-ty" style="width: {{ round($d['ty'] / $tlMax * 100, 1) }}%"></span>
							<span class="tyly-bar is-ly" style="width: {{ round($d['ly'] / $tlMax * 100, 1) }}%"></span>
						</span>
					</td>
					<td class="tyly-col-signals">
						@if($d['stockouts'] > 0)<span class="tyly-flag is-out" title="Productos vendidos en el período sin existencia disponible"><i data-lucide="package-x"></i>{{ $d['stockouts'] }} agotados</span>@endif
						@if($d['low_stock'] > 0)<span class="tyly-flag is-low" title="Productos bajo el mínimo de inventario"><i data-lucide="package-minus"></i>{{ $d['low_stock'] }} bajo mín.</span>@endif
						@if($d['returns_amount'] > 0)<span class="tyly-flag is-ret" title="Devoluciones de clientes en el período"><i data-lucide="undo-2"></i>{{ $tlMoney($d['returns_amount']) }} dev.</span>@endif
						@if($d['stockouts'] == 0 && $d['low_stock'] == 0 && $d['returns_amount'] <= 0)<span class="tyly-flag is-none">Sin alertas</span>@endif
					</td>
					<td class="tyly-col-chevron"><i data-lucide="chevron-down"></i></td>
				</tr>
				@empty
				<tr class="tyly-state-row"><td colspan="8">
					<div class="tyly-state">
						<i data-lucide="inbox"></i>
						<strong>Sin ventas en el período</strong>
						<span>Amplíe las fechas o quite filtros de sucursal, departamento o categoría.</span>
					</div>
				</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</section>
<script type="application/json" id="tylyData">{!! json_encode($tl, JSON_HEX_TAG | JSON_HEX_APOS) !!}</script>
