@extends('layout.default')

@section('title', 'Sucursales y Almacenes')

@section('content')
@include('components.execution_context_bar')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-building"></i></div>
		<div>
			<h4 class="dairy-page-title">Sucursales y Almacenes</h4>
			<p class="dairy-page-subtitle">Gestión de unidades operativas, almacenes físicos y aislamiento de inventarios por sucursal.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right dairy-page-actions">
		<a href="{{ $U('/stockoverview') }}" class="btn btn-outline-secondary">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>Inventario Global
		</a>
		@include('components.dairy_export')
	</div>
</div>

<!-- SCORECARD COMPARATIVO DE SUCURSALES (VENTAS · MARGEN · MERMA · RIESGO) -->
@php
	$scTotalSales = array_sum(array_column($scorecards, 'net_sales'));
	$scTotalWaste = array_sum(array_column($scorecards, 'waste_cost'));
	$scTotalOpen = array_sum(array_column($scorecards, 'open_cases'));
	$scTotalStock = array_sum(array_column($scorecards, 'stock_value'));
	$scWastePct = $scTotalSales > 0 ? $scTotalWaste / $scTotalSales * 100 : 0;
	$scColors = ['success' => ['#047857', '#ecfdf5', 'Dentro de referencia'], 'warning' => ['#b45309', '#fffbeb', 'Sobre referencia'], 'danger' => ['#b91c1c', '#fef2f2', 'Sobre tope corporativo'], 'nodata' => ['#64748b', '#f1f5f9', 'Sin información']];
@endphp
@php
	$scInTarget = count(array_filter($scorecards, function($sc) { return $sc['status'] === 'success'; }));
	$scSorted = array_values(array_filter($scorecards, fn($sc) => !empty($sc['has_data'])));
	$scWithData = count($scSorted);
	usort($scSorted, function($x, $y) { return $x['waste_pct'] <=> $y['waste_pct']; });
	$scBest = $scSorted[0] ?? null;
	$scWorst = count($scSorted) > 0 ? $scSorted[count($scSorted) - 1] : null;
	$scGap = ($scBest && $scWorst) ? $scWorst['waste_pct'] - $scBest['waste_pct'] : 0;
@endphp
@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Sucursales en Meta', 'value' => $scInTarget . ' / ' . $scWithData, 'sub' => 'merma ≤ 1.51% · ' . (count($scorecards) - $scWithData) . ' sin información', 'icon' => 'fa-bullseye', 'tone' => $scInTarget == $scWithData ? 'success' : 'warning', 'alert' => $scInTarget < $scWithData],
	['label' => 'Merma de la Red', 'value' => number_format($scWastePct, 2) . '%', 'sub' => '$' . number_format($scTotalWaste, 2) . ' en el mes', 'icon' => 'fa-trash-can', 'tone' => $scWastePct <= 1.51 ? 'success' : ($scWastePct <= 2.5 ? 'warning' : 'danger'), 'alert' => $scWastePct > 1.51, 'href' => $U('/waste')],
	['label' => 'Menor Merma', 'value' => $scBest ? number_format($scBest['waste_pct'], 2) . '%' : '—', 'sub' => $scBest ? $scBest['name'] : 'sin datos', 'icon' => 'fa-medal', 'tone' => 'success'],
	['label' => 'Mayor Merma', 'value' => $scWorst ? number_format($scWorst['waste_pct'], 2) . '%' : '—', 'sub' => $scWorst ? $scWorst['name'] : 'sin datos', 'icon' => 'fa-arrow-trend-up', 'tone' => 'danger', 'alert' => true],
	['label' => 'Brecha de Merma', 'value' => number_format($scGap, 2) . ' pts', 'sub' => 'peor vs. mejor sucursal', 'icon' => 'fa-arrows-left-right', 'tone' => 'purple'],
	['label' => 'Casos Abiertos', 'value' => $scTotalOpen, 'sub' => 'pendientes de cierre', 'icon' => 'fa-folder-open', 'tone' => $scTotalOpen > 0 ? 'warning' : 'secondary', 'href' => $U('/waste/approvals')],
]])

<div class="d-flex align-items-center justify-content-between mb-2 mt-2">
	<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-ranking-star text-primary mr-2"></i>Scorecard por Sucursal</span>
	<span class="text-muted dairy-fs-sm">Período {{ date('d/m/Y', strtotime($startDate)) }} – {{ date('d/m/Y', strtotime($endDate)) }} · semáforo de merma s/ventas</span>
</div>
<div class="row mb-3" style="margin-left: -6px; margin-right: -6px;">
	@foreach($scorecards as $sc)
	@php $c = $scColors[$sc['status']]; @endphp
	<div class="col-xl-4 col-md-6 mb-3" style="padding-left: 6px; padding-right: 6px;">
		<div class="dairy-op-card branch-scorecard" style="--sc-color: {{ $c[0] }};">
			<div class="dairy-op-header">
				<div class="d-flex align-items-center">
					<div class="dairy-kpi-icon-box mr-2 dairy-bg-subtle"><i class="fa-solid fa-shop text-primary"></i></div>
					<div>
						<div class="font-weight-700 text-dark dairy-fs-md dairy-lh-12">{{ $sc['name'] }}</div>
						<div class="text-muted dairy-fs-xs">{{ str_replace('DEMO-', 'EJ-', $sc['code']) }}</div>
					</div>
				</div>
				<span class="dairy-pill-badge" style="background: {{ $c[1] }}; color: {{ $c[0] }};"><i class="fa-solid fa-circle mr-1 dairy-fs-2xs"></i>{{ $c[2] }}</span>
			</div>
			<div class="dairy-op-body">
				<div class="d-flex justify-content-between align-items-end mb-1">
					<div>
						<div class="text-muted dairy-fs-xs">Ventas netas</div>
						<div class="dairy-kpi-value mb-0">@if(!empty($sc['has_data']))${{ number_format($sc['net_sales'], 2) }}@else Sin información @endif</div>
					</div>
					<div class="text-right">
						<div class="text-muted dairy-fs-xs">Merma s/ventas</div>
						<div class="font-weight-700" style="font-size: var(--dairy-fs-xl); color: {{ $c[0] }};">@if(!empty($sc['has_data'])){{ number_format($sc['waste_pct'], 2) }}%@else — @endif</div>
					</div>
				</div>
				<div class="progress mb-3 dairy-bg-subtle" style="height: 5px;" title="Participación relativa en ventas">
					<div class="progress-bar" style="width: {{ $sc['sales_share'] }}%; background: var(--dairy-primary);"></div>
				</div>
				<div class="row text-center no-gutters branch-sc-metrics">
					<div class="col-4"><div class="text-muted">Margen</div><strong>{{ number_format($sc['margin_pct'], 1) }}%</strong></div>
					<div class="col-4"><div class="text-muted">Ticket prom.</div><strong>${{ number_format($sc['avg_ticket'], 2) }}</strong></div>
					<div class="col-4"><div class="text-muted">Tickets</div><strong>{{ $sc['tickets'] }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Merma $</div><strong class="text-danger">${{ number_format($sc['waste_cost'], 2) }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Casos abiertos</div><strong>{{ $sc['open_cases'] }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Vence ≤7 d</div><strong class="text-warning">${{ number_format($sc['expiring_value'], 2) }}</strong></div>
				</div>
			</div>
			<div class="d-flex border-top dairy-fs-sm">
				<a class="flex-fill text-center py-2 border-right font-weight-600" href="{{ $U('/branch/' . $sc['id'] . '/stock') }}"><i class="fa-solid fa-boxes-stacked mr-1"></i>Stock</a>
				<a class="flex-fill text-center py-2 border-right font-weight-600 text-danger" href="{{ $U('/waste?branch_id=' . $sc['id']) }}"><i class="fa-solid fa-trash-can mr-1"></i>Mermas</a>
				<a class="flex-fill text-center py-2 font-weight-600 text-success" href="{{ $U('/sales?branch_id=' . $sc['id']) }}"><i class="fa-solid fa-receipt mr-1"></i>Ventas</a>
			</div>
		</div>
	</div>
	@endforeach
</div>

<div class="d-flex align-items-center mb-2">
	<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-address-book text-primary mr-2"></i>Directorio de Sucursales</span>
</div>

<div class="card shadow-sm border-0">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-hover table-striped mb-0 text-sm">
				<thead class="thead-light">
					<tr>
						<th># ID</th>
						<th>Nombre de la Sucursal</th>
						<th>Código</th>
						<th>Dirección</th>
						<th>Teléfono de Contacto</th>
						<th class="text-center">Estado</th>
						<th class="text-center">Acciones</th>
					</tr>
				</thead>
				<tbody>
					@foreach($branches as $b)
					<tr>
						<td class="font-weight-bold text-muted">#{{ $b->id }}</td>
						<td class="font-weight-bold text-dark">
							<i class="fa-solid fa-shop text-primary mr-1"></i>{{ $b->name }}
						</td>
						<td><span class="badge badge-light border">{{ str_replace('DEMO-', 'EJ-', $b->code) }}</span></td>
						<td class="text-muted">{{ $b->address ?? '-' }}</td>
						<td>{{ $b->phone ?? '-' }}</td>
						<td class="text-center">
							@if($b->active == 1)
							<span class="badge badge-success">Operativa</span>
							@else
							<span class="badge badge-secondary">Inactiva</span>
							@endif
						</td>
						<td class="text-center">
							<a href="{{ $U('/branch/' . $b->id . '/stock') }}" class="btn btn-sm btn-outline-primary" title="Ver Existencias en esta Sucursal">
								<i class="fa-solid fa-boxes-stacked mr-1"></i>Ver Stock
							</a>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

@if(\Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_BRANCH_MANAGE]))
{{-- Acceso por sucursal: usuarios sin marcas ven toda la red; con marcas, solo esas sucursales (datos, KPIs y operaciones) --}}
<div class="d-flex align-items-center mt-4 mb-2">
	<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-user-lock text-primary mr-2"></i>Acceso de usuarios por sucursal</span>
</div>
<div class="card shadow-sm border-0">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm mb-0" id="branch-access-table">
				<thead class="thead-light">
					<tr>
						<th>Usuario</th>
						@foreach($branches as $b)<th class="text-center">{{ $b->name }}</th>@endforeach
						<th>Alcance</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach($userAccess as $ua)
					<tr data-user="{{ $ua['id'] }}">
						<td class="font-weight-600">{{ $ua['username'] }} <span class="small text-muted">{{ $ua['display'] }}</span></td>
						@foreach($branches as $b)
						<td class="text-center"><label class="dairy-check-hit"><input type="checkbox" class="branch-access-cb" value="{{ $b->id }}" @if(in_array($b->id, $ua['branch_ids'])) checked @endif @if($ua['is_admin']) disabled @endif aria-label="{{ $b->name }}"></label></td>
						@endforeach
						<td class="small">@if($ua['is_admin'])<span class="badge badge-dark">Administrador · toda la red</span>@elseif(count($ua['branch_ids']))<span class="badge badge-warning text-dark">{{ count($ua['branch_ids']) }} sucursal(es)</span>@else<span class="badge badge-light border">Toda la red</span>@endif</td>
						<td class="text-right">@unless($ua['is_admin'])<button type="button" class="btn btn-sm btn-primary branch-access-save">Guardar</button>@endunless</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		<div class="small text-muted p-2">Sin ninguna sucursal marcada el usuario ve toda la red. Los administradores siempre ven toda la red.</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	$(document).on('click', '.branch-access-save', function () {
		var $tr = $(this).closest('tr'), $btn = $(this).prop('disabled', true);
		var ids = $tr.find('.branch-access-cb:checked').map(function () { return parseInt(this.value); }).get();
		Grocy.Api.Post('branches/user-access/' + $tr.data('user'), { branch_ids: ids }, function () {
			toastr.success('Acceso actualizado');
			setTimeout(function () { location.reload(); }, 500);
		}, function (xhr) {
			$btn.prop('disabled', false);
			var m = 'No se pudo guardar'; try { m = JSON.parse(xhr.responseText).error_message || m; } catch (e) { } toastr.error(m);
		});
	});
});
</script>
@endif
@stop
