@extends('layout.default')

@section('title', 'Centros de costo')

@section('content')
@php
	$t = $report['total'];
	$money = fn($v) => ($v < 0 ? '-' : '') . '$' . number_format(abs($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format($v, $v < 10 ? 2 : 1) . '%';
	$delta = function($v) {
		if ($v === null) return '<span class="text-muted">—</span>';
		$cls = $v > 0 ? 'text-danger' : ($v < 0 ? 'text-success' : 'text-muted');
		$icon = $v > 0 ? 'fa-arrow-up' : ($v < 0 ? 'fa-arrow-down' : 'fa-minus');
		return '<span class="' . $cls . ' text-nowrap"><i class="fa-solid ' . $icon . ' mr-1"></i>' . number_format(abs($v), 2) . ' pp</span>';
	};
	$monthLabel = fn($m) => ucfirst(\Carbon\Carbon::parse($m . '-01')->locale('es')->isoFormat('MMMM YYYY'));
	$selectedBranchName = collect($report['branches'])->firstWhere('id', $selectedBranchId)['name'] ?? 'toda la red';
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-building-columns"></i></div>
		<div>
			<h4 class="dairy-page-title">Centros de costo</h4>
			<p class="dairy-page-subtitle">Cada sucursal es un centro de costo: venta, costo, margen y merma por sucursal y categoría, comparados con el mes anterior.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right dairy-page-actions">
		<form method="get" class="d-inline-flex align-items-center">
			@if($selectedBranchId)<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">@endif
			<label class="small text-muted mb-0 mr-2" for="cc-month">Mes</label>
			<input id="cc-month" type="month" name="month" class="form-control form-control-sm" value="{{ $report['month'] }}" max="{{ date('Y-m') }}" onchange="this.form.submit()">
		</form>
		@include('components.dairy_export')
	</div>
</div>

@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Ventas Netas', 'value' => $money($t['sales']), 'sub' => 'mes anterior ' . $money($t['prev_sales']), 'icon' => 'fa-cash-register', 'tone' => 'primary'],
	['label' => 'Margen Bruto', 'value' => $pct($t['margin_pct']), 'sub' => $money($t['margin']) . ' sobre CMV real', 'icon' => 'fa-scale-balanced', 'tone' => 'success'],
	['label' => 'Merma', 'value' => $money($t['waste']), 'sub' => $pct($t['waste_pct']) . ' de la venta', 'icon' => 'fa-trash-can', 'tone' => 'danger', 'alert' => ($t['waste_pct_delta'] ?? 0) > 0, 'href' => $U('/waste')],
	['label' => 'Margen tras Merma', 'value' => $pct($t['net_margin_pct']), 'sub' => $money($t['net_margin']), 'icon' => 'fa-sack-dollar', 'tone' => 'info'],
]])

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-building text-primary mr-2"></i>Por sucursal · {{ $monthLabel($report['month']) }}</span>
		<span class="text-muted small">Δ merma = cambio en puntos porcentuales frente a {{ $monthLabel($report['previous_month']) }}</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Centro de costo</th>
						<th class="text-right">Ventas</th>
						<th class="text-right">CMV</th>
						<th class="text-right">Margen</th>
						<th class="text-right">Merma</th>
						<th class="text-right">Merma % venta</th>
						<th class="text-right">Δ merma</th>
						<th class="text-right">Margen tras merma</th>
						<th class="text-right" title="Presupuesto de merma proporcional a los días transcurridos">Ppto. merma</th>
						<th class="text-right" title="Merma real − presupuesto a la fecha">Desvío</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach($report['branches'] as $b)
					<tr class="@if($selectedBranchId == $b['id']) table-active @endif">
						<td><span class="font-weight-600 text-dark">{{ $b['name'] }}</span>@if($b['code'])<span class="text-muted small ml-1">{{ str_replace('DEMO-', 'EJ-', $b['code']) }}</span>@endif</td>
						<td class="text-right">{{ $money($b['sales']) }}</td>
						<td class="text-right">{{ $money($b['cogs']) }}</td>
						<td class="text-right text-nowrap">{{ $money($b['margin']) }} <span class="text-muted small">{{ $pct($b['margin_pct']) }}</span></td>
						<td class="text-right @if($b['waste'] > 0) text-danger @endif">{{ $money($b['waste']) }}</td>
						<td class="text-right font-weight-600">{{ $pct($b['waste_pct']) }}</td>
						<td class="text-right">{!! $delta($b['waste_pct_delta']) !!}</td>
						<td class="text-right font-weight-600 text-nowrap">{{ $money($b['net_margin']) }} <span class="text-muted small">{{ $pct($b['net_margin_pct']) }}</span></td>
						<td class="text-right text-muted">{{ $b['waste_budget_to_date'] !== null ? $money($b['waste_budget_to_date']) : '—' }}</td>
						<td class="text-right font-weight-600 @if(($b['waste_budget_deviation'] ?? 0) > 0) text-danger @elseif($b['waste_budget_deviation'] !== null) text-success @endif">{{ $b['waste_budget_deviation'] !== null ? ($b['waste_budget_deviation'] > 0 ? '+' : '') . $money($b['waste_budget_deviation']) : '—' }}</td>
						<td class="text-right">
							<a class="btn btn-xs btn-outline-secondary" href="?{{ http_build_query(['month' => $report['month'], 'branch_id' => $b['id']]) }}" title="Ver categorías de esta sucursal"><i class="fa-solid fa-layer-group"></i></a>
						</td>
					</tr>
					@endforeach
				</tbody>
				<tfoot>
					<tr class="font-weight-700">
						<td>Total red</td>
						<td class="text-right">{{ $money($t['sales']) }}</td>
						<td class="text-right">{{ $money($t['cogs']) }}</td>
						<td class="text-right">{{ $money($t['margin']) }}</td>
						<td class="text-right">{{ $money($t['waste']) }}</td>
						<td class="text-right">{{ $pct($t['waste_pct']) }}</td>
						<td class="text-right">{!! $delta($t['waste_pct_delta']) !!}</td>
						<td class="text-right">{{ $money($t['net_margin']) }}</td>
						<td class="text-right">{{ $t['waste_budget_to_date'] !== null ? $money($t['waste_budget_to_date']) : '—' }}</td>
						<td class="text-right @if(($t['waste_budget_deviation'] ?? 0) > 0) text-danger @endif">{{ $t['waste_budget_deviation'] !== null ? ($t['waste_budget_deviation'] > 0 ? '+' : '') . $money($t['waste_budget_deviation']) : '—' }}</td>
						<td></td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</div>

{{-- Presupuesto mensual por centro de costo: alimenta metas del Panel Ejecutivo, desvíos y Arturito --}}
@php $canBudget = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_BUDGET_MANAGE]); @endphp
<div class="card mb-3" id="budgets">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-sack-dollar text-primary mr-2"></i>Presupuesto · {{ $monthLabel($report['month']) }}</span>
		<span class="text-muted small">Vacío = usa la red o la configuración (merma ≤ {{ GROCY_WASTE_TARGET_PCT }}% s/{{ \Grocy\Services\BudgetService::BASES[GROCY_WASTE_PCT_BASE] ?? 'ventas netas' }}, margen ≥ {{ GROCY_MARGIN_TARGET_PCT }}%, DSI ≤ {{ GROCY_DSI_TARGET_DAYS }} d)</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm mb-0 dairy-budget-table">
				<thead>
					<tr>
						<th>Centro de costo</th>
						<th class="text-right">Ventas meta</th>
						<th class="text-right">Merma ppto. $</th>
						<th class="text-right">Laboral ppto. $</th>
						<th class="text-right">Merma meta %</th>
						<th class="text-right">Margen meta %</th>
						<th class="text-right">DSI meta (d)</th>
						@if($canBudget)<th></th>@endif
					</tr>
				</thead>
				<tbody>
					@foreach(array_merge([['id' => 0, 'name' => 'Red consolidada', 'targets' => $t['targets']]], $report['branches']) as $b)
					@php $tg = $b['targets']; $own = fn($k) => in_array($tg['sources'][$k], [$b['id'] ? 'budget_branch' : 'budget_network']); @endphp
					<tr data-branch="{{ $b['id'] }}">
						<td class="font-weight-600 text-dark">{{ $b['name'] }}</td>
						@foreach([['sales_target', 'sales'], ['waste_budget_amount', 'waste_amount'], ['labor_budget_amount', 'labor'], ['waste_target_pct', 'waste_pct'], ['margin_target_pct', 'margin'], ['dsi_target_days', 'dsi']] as [$field, $src])
						<td class="text-right">
							@if($canBudget)
							<input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" name="{{ $field }}" value="{{ $own($src) ? $tg[$field] : '' }}" placeholder="{{ $tg[$field] !== null ? $tg[$field] : '—' }}" aria-label="{{ $field }}">
							@else
							{{ $tg[$field] ?? '—' }}
							@endif
						</td>
						@endforeach
						@if($canBudget)<td class="text-right"><button type="button" class="btn btn-sm btn-primary dairy-budget-save">Guardar</button></td>@endif
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

@if($canBudget)
<script>
document.addEventListener('DOMContentLoaded', function () {
	$(document).on('click', '.dairy-budget-save', function () {
		var $tr = $(this).closest('tr'), body = { branch_id: $tr.data('branch'), period: '{{ $report['month'] }}' };
		$tr.find('input').each(function () { body[this.name] = this.value; });
		var $btn = $(this).prop('disabled', true);
		Grocy.Api.Post('budgets', body, function () {
			toastr.success('Presupuesto guardado');
			setTimeout(function () { location.reload(); }, 600);
		}, function (xhr) {
			$btn.prop('disabled', false);
			var m = 'No se pudo guardar'; try { m = JSON.parse(xhr.responseText).error_message || m; } catch (e) { } toastr.error(m);
		});
	});
});
</script>
@endif

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
		<span class="font-weight-700 text-dark"><i class="fa-solid fa-layer-group text-primary mr-2"></i>Por categoría · {{ $selectedBranchName }}</span>
		@if($selectedBranchId)
		<a class="small" href="?{{ http_build_query(['month' => $report['month']]) }}">Ver toda la red</a>
		@endif
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead>
					<tr>
						<th>Categoría</th>
						<th class="text-right">Ventas</th>
						<th class="text-right">Margen %</th>
						<th class="text-right">Merma</th>
						<th class="text-right">Merma % venta</th>
						<th class="text-right">Δ merma</th>
						<th class="text-right">Margen tras merma</th>
					</tr>
				</thead>
				<tbody>
					@forelse($report['categories'] as $c)
					<tr>
						<td class="font-weight-600 text-dark">{{ $c['name'] }}</td>
						<td class="text-right">{{ $money($c['sales']) }}</td>
						<td class="text-right">{{ $pct($c['margin_pct']) }}</td>
						<td class="text-right @if($c['waste'] > 0) text-danger @endif">{{ $money($c['waste']) }}</td>
						<td class="text-right font-weight-600">@if($c['sales'] == 0 && $c['waste'] > 0)<span class="text-danger" title="Merma sin ventas en el mes">sin venta</span>@else{{ $pct($c['waste_pct']) }}@endif</td>
						<td class="text-right">{!! $delta($c['waste_pct_delta']) !!}</td>
						<td class="text-right">{{ $money($c['net_margin']) }}</td>
					</tr>
					@empty
					<tr><td colspan="7" class="text-center text-muted py-3">Sin ventas ni mermas en el mes.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

{{-- SECCIÓN EXPANSIÓN: PRESUPUESTOS POR DEPARTAMENTO --}}
<div class="card mb-4 border-0 shadow-sm">
	<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
		<div>
			<h6 class="font-weight-bold text-dark mb-0">
				<i class="fa-solid fa-calculator text-primary mr-2"></i>Presupuestos por Departamento · Control Mensual y Anual
			</h6>
			<div class="small text-muted">Comparativo de presupuesto asignado frente a gasto real ejecutado y alertas de desviación.</div>
		</div>
		<div class="d-flex gap-2">
			<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Abriendo asistente de reasignación presupuestal entre departamentos.');">
				<i class="fa-solid fa-plus mr-1"></i> Asignar Presupuesto
			</button>
			<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
				<i class="fa-solid fa-shield-halved mr-1"></i> Action Center
			</a>
		</div>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead class="thead-light">
					<tr>
						<th>Sucursal</th>
						<th>Departamento</th>
						<th>Ejercicio</th>
						<th class="text-right">Ppto. Mensual</th>
						<th class="text-right">Ppto. Anual</th>
						<th class="text-right">Gasto Real Mes</th>
						<th class="text-right">Desviación %</th>
						<th class="text-right">Proyección Cierre</th>
						<th class="text-center">Estado Alerta</th>
						<th class="text-center">Acción</th>
					</tr>
				</thead>
				<tbody>
					@foreach(($departmentBudgets ?? []) as $budg)
					<tr class="@if($budg['alert_status'] === 'warning') table-warning @endif">
						<td class="font-weight-bold text-dark"><i class="fa-solid fa-building mr-1 text-muted"></i> {{ $budg['branch_name'] }}</td>
						<td><strong>{{ $budg['department'] }}</strong></td>
						<td><span class="badge badge-light border">{{ $budg['fiscal_year'] }}</span></td>
						<td class="text-right">${{ number_format($budg['monthly_budget'], 2) }}</td>
						<td class="text-right">${{ number_format($budg['annual_budget'], 2) }}</td>
						<td class="text-right font-weight-bold">${{ number_format($budg['actual_spent'], 2) }}</td>
						<td class="text-right font-weight-bold @if($budg['variance_pct'] > 0) text-danger @else text-success @endif">
							@if($budg['variance_pct'] > 0)+@endif{{ number_format($budg['variance_pct'], 1) }}%
						</td>
						<td class="text-right text-muted font-weight-600">
							${{ number_format($budg['actual_spent'] * 12, 2) }}
						</td>
						<td class="text-center">
							@if($budg['alert_status'] === 'on_track')
								<span class="badge badge-success"><i class="fa-solid fa-circle-check mr-1"></i> Bajo Control</span>
							@else
								<span class="badge badge-warning text-dark"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Desvío &gt; 5%</span>
							@endif
						</td>
						<td class="text-center">
							<button class="btn btn-xs btn-outline-secondary py-0 px-2 font-weight-bold" onclick="alert('Historial de compras y gastos imputados al departamento.');">
								<i class="fa-solid fa-receipt mr-1"></i> Detalle
							</button>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>
@stop
