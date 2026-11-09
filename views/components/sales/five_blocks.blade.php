{{-- Ventas y Rentabilidad · tablero principal: cinco bloques prioritarios con los filtros compartidos (SalesDashboardService) --}}
@php
	$sd = $salesDash;
	$ev = $sd['evolution'];
	$cp = $sd['comparison'];
	$pr = $sd['productivity'];
	$fn = $filterNames;
	$per = $sd['period']['label'];
	$scope = $fn['branch'] . ' · ' . $fn['department'] . ($sharedFilters['group_id'] !== null ? ' · ' . $fn['group'] : '') . ($fn['product'] ? ' · «' . $fn['product'] . '»' : '');
	$upd = 'Calculado ' . date('d/m/Y H:i', strtotime($sd['computed_at'])) . ($sd['last_record'] ? ' · último registro ' . date('d/m/Y H:i', strtotime($sd['last_record'])) : ' · sin registros');
	$netDef = $sd['product_filtered'] ? 'Σ subtotal de las líneas vendidas del filtro, en ventas completadas (sin IVA)' : 'Σ (total del ticket − IVA) de ventas completadas; excluye anuladas y devueltas';
	$src = 'sales, sale_items';
	$dm = fn($d) => date('d/m', strtotime($d));
	$detailQs = function ($row) use ($sharedFilters) {
		return \Grocy\Helpers\DailyFilters::Query($sharedFilters, '/sales', ['product' => $row['sku'] ?: $row['id'], 'product_id' => $row['id'], 'tab' => 'productos']);
	};
@endphp
<section class="sdash" id="salesFiveBlocks" data-testid="sales-five-blocks">
	<div class="sdash-context" data-testid="sales-context">
		<span><i class="fa-regular fa-calendar"></i> <strong>{{ $per }}</strong></span>
		<span><i class="fa-solid fa-store"></i> {{ $fn['branch'] }}</span>
		<span><i class="fa-solid fa-layer-group"></i> {{ $fn['department'] }}</span>
		@if($sharedFilters['group_id'] !== null)<span><i class="fa-solid fa-tags"></i> {{ $fn['group'] }}</span>@endif
		@if($fn['product'])<span><i class="fa-solid fa-barcode"></i> {{ $fn['product'] }}</span>@endif
		<span class="sdash-context-cmp">Comparación: período equivalente anterior ({{ $sd['period']['prev_label'] }})</span>
	</div>

	{{-- 1. Ventas y evolución --}}
	<article class="sdash-block" data-testid="block-evolution">
		<header><span class="sdash-n">1</span><h2>Ventas y evolución</h2></header>
		<div class="sdash-kpis">
			@include('components.daily_kpi', ['dkpi' => ['id' => 'net-sales', 'name' => 'Ventas netas', 'value' => $ev['net'], 'unit' => '$', 'period' => $per, 'scope' => $scope,
				'definition' => $netDef, 'source' => $src, 'updated' => $upd, 'delta' => $ev['delta_pct'],
				'comparison' => 'vs. ' . $sd['period']['prev_label'] . ($ev['prev_net'] !== null ? ' ($' . number_format($ev['prev_net'], 0) . ')' : ' (sin ventas)')]])
			@include('components.daily_kpi', ['dkpi' => ['id' => 'daily-avg', 'name' => 'Venta diaria promedio', 'value' => $ev['daily_avg'], 'unit' => '$', 'period' => $per . ' · ' . $sd['period']['days'] . ' días', 'scope' => $scope,
				'definition' => 'Ventas netas del período ÷ días calendario del período (incluye días sin venta)', 'source' => $src, 'updated' => $upd]])
			@include('components.daily_kpi', ['dkpi' => ['id' => 'tickets', 'name' => 'Tickets', 'value' => $ev['tickets'] ?: null, 'unit' => 'n.º', 'period' => $per, 'scope' => $scope,
				'definition' => $sd['product_filtered'] ? 'Ventas completadas que incluyen al menos una línea del filtro' : 'Ventas completadas (un ticket = una venta)', 'source' => 'sales', 'updated' => $upd]])
		</div>
		<div class="sdash-chart-head">
			<div class="sdash-seg" role="tablist" aria-label="Vista de la evolución">
				<button type="button" class="is-active" data-evo="daily">Diaria</button>
				<button type="button" data-evo="hourly">Por hora</button>
				<button type="button" data-evo="weekly">Semanal</button>
				<button type="button" data-evo="cumulative">Acumulada</button>
			</div>
			<span class="sdash-note" id="evoNote">Ventas netas por día · actual vs. período equivalente anterior</span>
		</div>
		<div class="sdash-canvas"><canvas id="sdashEvo" aria-label="Evolución de ventas"></canvas></div>
	</article>

	{{-- 2. Comparación comercial --}}
	<article class="sdash-block" data-testid="block-comparison">
		<header><span class="sdash-n">2</span><h2>Comparación comercial</h2></header>
		<div class="sdash-kpis">
			@include('components.daily_kpi', ['dkpi' => ['id' => 'weekend', 'name' => 'Fin de semana actual', 'value' => $cp['weekend']['net'], 'unit' => '$',
				'period' => $dm($cp['weekend']['from']) . ($cp['weekend']['partial'] ? ' (sábado; domingo aún no ocurre en el período)' : '–' . $dm($cp['weekend']['to'])), 'scope' => $scope,
				'definition' => 'Ventas netas del último sábado y domingo dentro del período; se compara con los mismos días de la semana anterior', 'source' => $src, 'updated' => $upd,
				'delta' => $cp['weekend']['delta_pct'], 'comparison' => 'vs. fin de semana anterior ' . $dm($cp['weekend']['prev_from']) . ($cp['weekend']['partial'] ? '' : '–' . $dm($cp['weekend']['prev_to'])) . ($cp['weekend']['prev_net'] !== null ? ' ($' . number_format($cp['weekend']['prev_net'], 0) . ')' : ' (sin ventas)')]])
			@include('components.daily_kpi', ['dkpi' => ['id' => 'year-ago', 'name' => 'Mismo período del año anterior', 'value' => $cp['year_ago']['net'], 'unit' => '$', 'period' => $cp['year_ago']['label'], 'scope' => $scope,
				'definition' => 'Ventas netas de las mismas fechas un año antes. Sin registros en esas fechas = «Sin información» (no se estima)', 'source' => $src, 'updated' => $upd,
				'delta' => $cp['year_ago']['delta_pct'], 'comparison' => $cp['year_ago']['net'] !== null ? 'variación del período actual frente al año anterior' : 'DAILY no tiene ventas registradas en esas fechas']])
			@include('components.daily_kpi', ['dkpi' => ['id' => 'weekday-share', 'name' => 'Participación entre semana', 'value' => $cp['split']['weekday_pct'], 'unit' => '%', 'period' => $per . ' · ' . $cp['split']['weekday_days'] . ' días lun–vie', 'scope' => $scope,
				'definition' => 'Ventas netas de lunes a viernes ÷ ventas netas del período × 100', 'source' => $src, 'updated' => $upd,
				'comparison' => '$' . number_format($cp['split']['weekday_net'], 0) . ' de lunes a viernes']])
			@include('components.daily_kpi', ['dkpi' => ['id' => 'weekend-share', 'name' => 'Participación fin de semana', 'value' => $cp['split']['weekend_pct'], 'unit' => '%', 'period' => $per . ' · ' . $cp['split']['weekend_days'] . ' días sáb–dom', 'scope' => $scope,
				'definition' => 'Ventas netas de sábado y domingo ÷ ventas netas del período × 100', 'source' => $src, 'updated' => $upd,
				'comparison' => '$' . number_format($cp['split']['weekend_net'], 0) . ' en sábado y domingo']])
		</div>
	</article>

	{{-- 3. Productividad comercial --}}
	<article class="sdash-block" data-testid="block-productivity">
		<header><span class="sdash-n">3</span><h2>Productividad comercial</h2></header>
		@if(!$pr['applies'])
			<p class="sdash-empty" data-testid="productivity-na"><i class="fa-solid fa-circle-info"></i> No aplica con este filtro. {{ $pr['reason'] }}</p>
		@elseif(!$pr['has_data'])
			<p class="sdash-empty"><i class="fa-solid fa-circle-info"></i> Sin información. {{ $pr['reason'] ?? 'No hay turnos programados para la sucursal en el período.' }}</p>
		@else
			@php $pPer = \Grocy\Helpers\DailyFilters::PeriodLabel($sd['period']['from'], $pr['cut']) . ($pr['excludes_today'] ? ' · excluye el día en curso' : ''); @endphp
			<div class="sdash-kpis">
				@include('components.daily_kpi', ['dkpi' => ['id' => 'staff', 'name' => 'Personal asignado', 'value' => $pr['people'], 'unit' => 'personas', 'period' => $pPer, 'scope' => $fn['branch'],
					'definition' => 'Colaboradores distintos con al menos un turno no cancelado en el período (todas las áreas de la tienda)', 'source' => 'employee_shifts', 'updated' => $upd]])
				@include('components.daily_kpi', ['dkpi' => ['id' => 'present-hours', 'name' => 'Horas trabajadas', 'value' => $pr['present_hours'], 'unit' => 'h', 'period' => $pPer, 'scope' => $fn['branch'],
					'definition' => 'Horas presentes = horas programadas (turnos − pausas) − horas de ausencias aprobadas e inasistencias', 'source' => 'employee_shifts, employee_attendance, employee_absences', 'updated' => $upd,
					'comparison' => number_format($pr['scheduled_hours'], 1) . ' h programadas']])
				@include('components.daily_kpi', ['dkpi' => ['id' => 'sales-per-person', 'name' => 'Ventas por colaborador', 'value' => $pr['per_person'], 'unit' => '$/persona', 'period' => $pPer, 'scope' => $fn['branch'],
					'definition' => 'Ventas netas del período ÷ personal asignado', 'source' => 'sales, employee_shifts', 'updated' => $upd,
					'comparison' => 'ventas netas $' . number_format($pr['net'], 0)]])
				@include('components.daily_kpi', ['dkpi' => ['id' => 'sales-per-hour', 'name' => 'Ventas por hora trabajada', 'value' => $pr['per_hour'], 'unit' => '$/h', 'period' => $pPer, 'scope' => $fn['branch'],
					'definition' => 'Ventas netas del período ÷ horas trabajadas (presentes). HOY usa horas programadas: no son comparables entre sí', 'source' => 'sales, employee_shifts', 'updated' => $upd]])
			</div>
			<div class="sdash-chart-head"><span class="sdash-note">Evolución semanal de ventas por hora trabajada (semanas de lunes a domingo; extremos parciales)</span></div>
			<div class="sdash-canvas is-short"><canvas id="sdashProd" aria-label="Evolución de productividad"></canvas></div>
		@endif
	</article>

	{{-- 4. Top 5 productos vendidos --}}
	<article class="sdash-block" data-testid="block-top5">
		<header><span class="sdash-n">4</span><h2>Top 5 productos vendidos</h2><span class="sdash-note">{{ $per }} · {{ $scope }} · participación sobre ${{ number_format($sd['top']['total_lines'], 0) }} vendidos en líneas del filtro</span></header>
		@if(empty($sd['top']['rows']))
			<p class="sdash-empty">Sin información: no hay líneas de venta con este filtro en el período.</p>
		@else
		<div class="table-responsive">
			<table class="table sdash-table mb-0" data-testid="top5-table">
				<thead><tr><th>#</th><th>Producto</th><th>SKU</th><th>Departamento</th><th class="text-right">Unidades</th><th class="text-right">Ventas netas</th><th class="text-right">Participación</th><th></th></tr></thead>
				<tbody>
				@foreach($sd['top']['rows'] as $i => $r)
					<tr>
						<td>{{ $i + 1 }}</td>
						<td class="font-weight-600">{{ $r['name'] }}<div class="sdash-sub">{{ $r['category'] }}</div></td>
						<td><code>{{ $r['sku'] ?: '—' }}</code></td>
						<td>{{ $r['department'] }}</td>
						<td class="text-right">{{ number_format($r['units'], fmod($r['units'], 1) == 0 ? 0 : 2) }}</td>
						<td class="text-right">${{ number_format($r['net'], 2) }}</td>
						<td class="text-right">{{ $r['share_pct'] !== null ? number_format($r['share_pct'], 1) . ' %' : '—' }}</td>
						<td class="text-right"><a class="sdash-link" href="{{ $U('/sales?' . $detailQs($r)) }}" data-testid="top5-detail">Ver detalle</a></td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>
		@endif
	</article>

	{{-- 5. Tráfico de clientes --}}
	<article class="sdash-block" data-testid="block-traffic">
		<header><span class="sdash-n">5</span><h2>Tráfico de clientes</h2></header>
		<div class="sdash-kpis">
			@include('components.daily_kpi', ['dkpi' => ['id' => 'traffic', 'name' => 'Clientes que ingresan', 'value' => null, 'unit' => 'personas', 'period' => $per, 'scope' => $fn['branch'],
				'definition' => 'Conteo de personas que entran a la tienda, por hora', 'source' => 'Sin fuente conectada: DAILY no tiene contador de ingreso (sensor de puerta o cámara de conteo)',
				'comparison' => 'La conversión comercial (tickets ÷ visitantes) no se calcula sin este dato']])
		</div>
		<div class="sdash-chart-head"><span class="sdash-note">Referencia disponible: tickets y ventas netas por hora del período. Un ticket es una compra, no un visitante.</span></div>
		<div class="sdash-canvas is-short"><canvas id="sdashTraffic" aria-label="Tickets y ventas por hora"></canvas></div>
	</article>
</section>

<script type="application/json" id="sdashData">{!! json_encode(['evo' => $ev, 'prod' => $pr['weekly'] ?? [], 'traffic' => $sd['traffic']], JSON_HEX_TAG) !!}</script>
