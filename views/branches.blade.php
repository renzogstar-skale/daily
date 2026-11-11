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

<!-- SCORECARD DE INVENTARIO POR SUCURSAL (FÍSICO · DISPONIBLE · RESERVADO · BLOQUEADO · BODEGA/SALA) -->
{{-- El comparativo comercial (ventas, margen, ticket) vive en Ventas/KPI; aquí solo inventario (InventoryOperationsService) --}}
@php
	$invSum = \Grocy\Services\InventoryOperationsService::GetInstance()->Summary();
	$invK = $invSum['kpis'];
	$invByBranch = array_column($invSum['branches'], null, 'branch_id');
	$scColors = ['success' => ['#047857', '#ecfdf5', 'Sin anomalías'], 'warning' => ['#b45309', '#fffbeb', 'Revisar'], 'danger' => ['#b91c1c', '#fef2f2', 'Stock negativo'], 'nodata' => ['#64748b', '#f1f5f9', 'Sin existencias']];
@endphp
@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Valor del inventario', 'value' => '$' . number_format($invK['value'], 0), 'sub' => number_format($invK['physical'], 0) . ' u físicas', 'icon' => 'fa-boxes-stacked', 'tone' => 'success'],
	['label' => 'Disponible', 'value' => number_format($invK['available'], 0), 'sub' => number_format($invK['reserved'], 0) . ' reservadas · ' . number_format($invK['blocked'], 0) . ' bloqueadas', 'icon' => 'fa-circle-check', 'tone' => 'success'],
	['label' => 'Exactitud de inventario', 'value' => $invK['accuracy_pct'] === null ? '—' : number_format($invK['accuracy_pct'], 1) . '%', 'sub' => $invK['accuracy_counts'] . ' conteos en 90 días', 'icon' => 'fa-bullseye', 'tone' => $invK['accuracy_pct'] !== null && $invK['accuracy_pct'] < 95 ? 'warning' : 'success'],
	['label' => 'Traslados sin recepción', 'value' => $invK['transit_late'] . ' / ' . $invK['transit_open'], 'sub' => 'vencidos / en tránsito', 'icon' => 'fa-truck-ramp-box', 'tone' => $invK['transit_late'] ? 'danger' : 'secondary', 'href' => $U('/transfers')],
	['label' => 'Negativo · Sobrecomprometido', 'value' => $invK['negative'] . ' · ' . $invK['overcommitted'], 'sub' => 'pares producto-sucursal', 'icon' => 'fa-triangle-exclamation', 'tone' => ($invK['negative'] + $invK['overcommitted']) ? 'danger' : 'secondary', 'alert' => ($invK['negative'] + $invK['overcommitted']) > 0],
	['label' => 'Ajustes por aprobar', 'value' => $invK['adjustments_pending'], 'sub' => $invK['counts_open'] . ' conteos abiertos', 'icon' => 'fa-scale-balanced', 'tone' => $invK['adjustments_pending'] ? 'warning' : 'secondary', 'href' => $U('/stockoverview#inv-ops-counts')],
]])

<div class="d-flex align-items-center justify-content-between mb-2 mt-2">
	<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-ranking-star text-primary mr-2"></i>Inventario por Sucursal</span>
	<span class="text-muted dairy-fs-sm">Disponible = físico − bloqueado − reservado · actualizado {{ substr($invSum['generated_at'], 11, 5) }}</span>
</div>
<div class="row mb-3" style="margin-left: -6px; margin-right: -6px;">
	@foreach($scorecards as $sc)
	@php
		$ib = $invByBranch[intval($sc['id'])] ?? null;
		$st = !$ib || $ib['physical'] <= 0 ? 'nodata' : ($ib['negative'] > 0 ? 'danger' : ($ib['overcommitted'] > 0 || $sc['expiring_value'] > 0 ? 'warning' : 'success'));
		$c = $scColors[$st];
		$availPct = $ib && $ib['physical'] > 0 ? min(100, $ib['available'] / $ib['physical'] * 100) : 0;
	@endphp
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
						<div class="text-muted dairy-fs-xs">Valor del inventario</div>
						<div class="dairy-kpi-value mb-0">@if($ib)${{ number_format($ib['value'], 2) }}@else Sin información @endif</div>
					</div>
					<div class="text-right">
						<div class="text-muted dairy-fs-xs">Disponible</div>
						<div class="font-weight-700" style="font-size: var(--dairy-fs-xl); color: {{ $c[0] }};">@if($ib && $ib['physical'] > 0){{ number_format($availPct, 1) }}%@else — @endif</div>
					</div>
				</div>
				<div class="progress mb-3 dairy-bg-subtle" style="height: 5px;" title="Disponible sobre físico">
					<div class="progress-bar" style="width: {{ $availPct }}%; background: var(--dairy-primary);"></div>
				</div>
				<div class="row text-center no-gutters branch-sc-metrics">
					<div class="col-4"><div class="text-muted">Físico</div><strong>{{ number_format($ib['physical'] ?? 0, 0) }}</strong></div>
					<div class="col-4"><div class="text-muted">Reservado</div><strong>{{ number_format($ib['reserved'] ?? 0, 0) }}</strong></div>
					<div class="col-4"><div class="text-muted">Bloqueado</div><strong>{{ number_format($ib['blocked'] ?? 0, 0) }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Bodega</div><strong>{{ number_format($ib['bodega'] ?? 0, 0) }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Sala</div><strong>{{ number_format($ib['sala'] ?? 0, 0) }}</strong></div>
					<div class="col-4 mt-2"><div class="text-muted">Vence ≤7 d</div><strong class="text-warning">${{ number_format($sc['expiring_value'], 2) }}</strong></div>
				</div>
			</div>
			<div class="d-flex border-top dairy-fs-sm">
				<a class="flex-fill text-center py-2 border-right font-weight-600" href="{{ $U('/branch/' . $sc['id'] . '/stock') }}"><i class="fa-solid fa-boxes-stacked mr-1"></i>Stock</a>
				<a class="flex-fill text-center py-2 border-right font-weight-600 text-danger" href="{{ $U('/waste?branch_id=' . $sc['id']) }}"><i class="fa-solid fa-trash-can mr-1"></i>Mermas</a>
				<a class="flex-fill text-center py-2 font-weight-600 text-success" href="{{ $U('/transfers') }}"><i class="fa-solid fa-right-left mr-1"></i>Traslados</a>
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
