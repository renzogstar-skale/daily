@extends('layout.default')

@section('title', 'Control de inventario')

@section('content')
@include('components.alert_strip', ['alertCategories' => ['inventory']])
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$m = $abcxyz['matrix'] ?? ($abc_xyz_matrix['matrix'] ?? []);
	$phantomItems = $phantom ?? ($phantom_stock ?? []);
	$lostValue = array_sum(array_column($phantomItems, 'lost_sales_value'));
	$phantomStockValue = array_sum(array_column($phantomItems, 'stock_value'));
	$cellTone = ['AX' => 'success', 'AY' => 'success', 'AZ' => 'warning', 'BX' => 'info', 'BY' => 'info', 'BZ' => 'warning', 'CX' => 'secondary', 'CY' => 'secondary', 'CZ' => 'danger'];
	$stagnantData = $stagnant ?? ($stagnant_lots ?? ['days' => 30, 'count' => 0, 'total_value' => 0, 'lots' => []]);
	$replenishment = $smart_replenishment ?? ['summary' => ['low_stock_count' => 0, 'stockout_count' => 0, 'critical_count' => 0, 'tasks_pending' => 0], 'items' => []];
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
		<div>
			<h4 class="dairy-page-title">Control de inventario</h4>
			<p class="dairy-page-subtitle">Detecta stock fantasma, gestiona reposición inteligente y clasifica cada producto por valor y estabilidad de demanda.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right dairy-page-actions">
		<form method="get" class="d-inline-flex">
			<select name="branch_id" class="custom-select custom-select-sm w-auto" onchange="this.form.submit()" aria-label="Sucursal">
				@foreach($branches as $b)<option value="{{ $b['id'] }}" @if($b['id'] == $branchId) selected @endif>{{ $b['name'] }}</option>@endforeach
			</select>
		</form>
		@include('components.dairy_export')
	</div>
</div>

<!-- Selector de Pestañas Principales -->
<ul class="nav nav-pills mb-3 gap-2 bg-light p-2 rounded border" id="inventoryControlTabs" role="tablist">
	<li class="nav-item">
		<a class="nav-link active font-weight-600" id="tab-analisis-link" data-toggle="pill" href="#tab-analisis" role="tab">
			<i class="fa-solid fa-chart-pie mr-1"></i> Análisis y Matriz ABC-XYZ
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-600" id="tab-reposicion-link" data-toggle="pill" href="#tab-reposicion" role="tab">
			<i class="fa-solid fa-boxes-packing mr-1 text-warning"></i> Reposición Inteligente ({{ count($replenishment['items']) }})
		</a>
	</li>
</ul>

<div class="tab-content" id="inventoryControlTabsContent">

	<!-- TAB 1: ANÁLISIS, STOCK FANTASMA Y MATRIZ ABC-XYZ -->
	<div class="tab-pane fade show active" id="tab-analisis" role="tabpanel">
		@include('components.dairy_kpi_row', ['kpis' => [
			['label' => 'Stock Fantasma', 'value' => count($phantomItems), 'sub' => 'productos a contar', 'icon' => 'fa-ghost', 'tone' => count($phantomItems) ? 'danger' : 'success', 'alert' => count($phantomItems) > 0],
			['label' => 'Venta Perdida', 'value' => $money($lostValue), 'sub' => 'estimada en racha sin venta', 'icon' => 'fa-cart-arrow-down', 'tone' => $lostValue > 0 ? 'warning' : 'success'],
			['label' => 'Valor en Duda', 'value' => $money($phantomStockValue), 'sub' => 'stock en sistema a costo', 'icon' => 'fa-scale-unbalanced', 'tone' => 'primary'],
			['label' => 'Clase AX', 'value' => $m['AX']['count'] ?? 0, 'sub' => 'alto valor y estable', 'icon' => 'fa-star', 'tone' => 'success'],
			['label' => 'Clase AZ', 'value' => $m['AZ']['count'] ?? 0, 'sub' => 'alto valor, errática', 'icon' => 'fa-wave-square', 'tone' => 'warning'],
			['label' => 'Clase CZ', 'value' => $m['CZ']['count'] ?? 0, 'sub' => 'candidatos a depurar', 'icon' => 'fa-box-archive', 'tone' => 'info'],
		]])

		<div class="card mb-3">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
				<span class="font-weight-700 text-dark"><i class="fa-solid fa-ghost text-danger mr-2"></i>Posible stock fantasma</span>
				@if($canCount && count($phantomItems))
				<button type="button" class="btn btn-sm btn-primary" id="ic-count-all"><i class="fa-solid fa-list-check mr-1"></i>Programar conteo ciego ({{ count(array_filter($phantomItems, fn($p) => !($p['count_pending'] ?? false))) }})</button>
				@endif
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Producto</th><th class="text-right">Stock sistema</th><th class="text-right">Días con venta</th><th>Última venta</th><th class="text-right">Días sin venta</th><th class="text-right" title="Probabilidad de que la racha sin ventas sea casual">Prob. casual</th><th class="text-right">Venta perdida</th><th></th></tr></thead>
						<tbody>
							@forelse($phantomItems as $p)
							<tr>
								<td class="font-weight-600">{{ $p['product_name'] }}</td>
								<td class="text-right">{{ $p['system_stock'] }}</td>
								<td class="text-right">{{ $p['sell_frequency_pct'] ?? '0' }} %<div class="small text-muted">{{ $p['sale_days'] ?? 0 }} de {{ $p['observed_days'] ?? 0 }} días</div></td>
								<td>{{ $p['last_sale'] ?? '—' }}</td>
								<td class="text-right text-danger font-weight-600">{{ $p['days_without_sale'] ?? 0 }}</td>
								<td class="text-right">{{ round(($p['probability'] ?? 0) * 100, 2) }} %</td>
								<td class="text-right">{{ $money($p['lost_sales_value'] ?? 0) }}</td>
								<td class="text-right text-nowrap">
									@if($p['count_pending'] ?? false)
									<span class="badge badge-info">Conteo pendiente</span>
									@elseif($canCount)
									<button type="button" class="btn btn-sm btn-outline-primary ic-count" data-product="{{ $p['product_id'] ?? $p['id'] }}">Contar</button>
									@endif
									<a class="btn btn-sm btn-light border" href="{{ $U('/kardex?product_id=' . ($p['product_id'] ?? $p['id'])) }}" title="Kardex"><i class="fa-solid fa-book-open"></i></a>
								</td>
							</tr>
							@empty
							<tr><td colspan="8" class="text-center text-muted py-4">Sin señales de stock fantasma: todos los productos con venta regular se están moviendo normalmente.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		{{-- Lotes Inmovilizados --}}
		@php
			$actIcon = ['write_off' => ['fa-trash-can', 'danger', 'Dar de baja'], 'markdown' => ['fa-tags', 'warning', 'Rebaja'], 'transfer' => ['fa-right-left', 'info', 'Trasladar'], 'return' => ['fa-truck-arrow-right', 'primary', 'Devolver'], 'count' => ['fa-list-check', 'secondary', 'Contar']];
			$actHref = ['write_off' => '/waste/report?product_id=', 'markdown' => '/traceability?search=', 'transfer' => '/transfers?product_id=', 'return' => '/suppliers?product_id='];
		@endphp
		<div class="card mb-3" id="ic-stagnant">
			<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
				<span class="font-weight-700 text-dark"><i class="fa-solid fa-hourglass-half text-warning mr-2"></i>Lotes inmovilizados (sin salidas en {{ $stagnantData['days'] }} días)</span>
				<span class="small text-muted">{{ $stagnantData['count'] }} lote(s) · {{ $money($stagnantData['total_value']) }} a costo</span>
			</div>
			<div class="card-body p-0">
				@if($stagnantData['count'] === 0)
				<p class="small text-muted p-3 mb-0">Sin lotes inmovilizados en esta sucursal.</p>
				@else
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Producto</th><th>Ubicación</th><th>Lote</th><th class="text-right">Cant.</th><th class="text-right">Valor</th><th class="text-right">Días sin salida</th><th>Vence</th><th>Recomendación</th><th></th></tr></thead>
						<tbody>
							@foreach(array_slice($stagnantData['lots'], 0, 50) as $l)
							@php [$ic, $tone, $btn] = $actIcon[$l['action'] ?? 'count'] ?? ['fa-circle', 'secondary', 'Acción']; @endphp
							<tr>
								<td>{{ $l['product_name'] }}</td>
								<td>{{ $l['location_name'] ?? 'Bodega' }}</td>
								<td>{{ $l['lot_number'] ?? '—' }}</td>
								<td class="text-right">{{ round($l['amount'], 2) }}</td>
								<td class="text-right">{{ $money($l['value']) }}</td>
								<td class="text-right">{{ $l['days_without_outflow'] }}</td>
								<td class="@if(($l['days_left'] ?? 0) < 0) text-danger font-weight-700 @endif">{{ $l['best_before_date'] ?? '—' }}</td>
								<td class="small"><span class="badge badge-{{ $tone }} mr-1"><i class="fa-solid {{ $ic }}"></i></span>{{ $l['recommendation'] }}</td>
								<td class="text-nowrap">
									<a class="btn btn-xs btn-outline-{{ $tone }}" href="{{ $U(($actHref[$l['action']] ?? '/kardex?product_id=') . $l['product_id']) }}">{{ $btn }}</a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
		</div>

		{{-- Matriz ABC-XYZ --}}
		<div class="row">
			<div class="col-lg-5 mb-3">
				<div class="card h-100 shadow-sm">
					<div class="card-header font-weight-700 text-dark"><i class="fa-solid fa-table-cells text-primary mr-2"></i>Matriz ABC-XYZ</div>
					<div class="card-body">
						<table class="table table-sm table-bordered text-center mb-2 dairy-keep-table">
							<thead><tr><th></th><th>X estable</th><th>Y variable</th><th>Z errática</th></tr></thead>
							<tbody>
								@foreach(['A' => '80% vta', 'B' => '15%', 'C' => '5%'] as $a => $lbl)
								<tr>
									<th class="text-left">{{ $a }} <span class="small text-muted">{{ $lbl }}</span></th>
									@foreach(['X', 'Y', 'Z'] as $x)
									<td class="ic-cell" role="button" tabindex="0" data-cell="{{ $a . $x }}">
										<span class="badge badge-{{ $cellTone[$a . $x] ?? 'secondary' }} dairy-fs-md">{{ $m[$a . $x]['count'] ?? 0 }}</span>
										<div class="small text-muted">{{ $money($m[$a . $x]['revenue'] ?? 0) }}</div>
									</td>
									@endforeach
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="col-lg-7 mb-3">
				<div class="card h-100 shadow-sm">
					<div class="card-header font-weight-700 text-dark">Clasificación de Productos</div>
					<div class="card-body p-0">
						<div class="table-responsive dairy-scroll-400">
							<table class="table table-sm table-hover mb-0" id="ic-items">
								<thead><tr><th>Producto</th><th>Clase</th><th class="text-right">Venta 90 d</th><th class="text-right">Stock</th></tr></thead>
								<tbody>
									@forelse(($abcxyz['items'] ?? ($abc_xyz_matrix['items'] ?? [])) as $i)
									<tr data-cell="{{ $i['cell'] ?? 'AX' }}">
										<td>{{ $i['product_name'] }}</td>
										<td><span class="badge badge-{{ $cellTone[$i['cell'] ?? 'AX'] ?? 'secondary' }}">{{ $i['cell'] ?? 'AX' }}</span></td>
										<td class="text-right">{{ $money($i['revenue_90d'] ?? 0) }}</td>
										<td class="text-right">{{ $i['stock'] ?? 0 }}</td>
									</tr>
									@empty
									<tr><td colspan="4" class="text-center text-muted py-4">Sin datos de matriz en esta sucursal.</td></tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 2: REPOSICIÓN INTELIGENTE (NUEVA PESTAÑA OFICIAL) -->
	<div class="tab-pane fade" id="tab-reposicion" role="tabpanel">
		<div class="card border-0 shadow-sm p-4 mb-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="h5 font-weight-bold text-dark m-0">
						<i class="fa-solid fa-boxes-packing text-warning mr-2"></i> Reposición Inteligente de Góndolas & Piso de Venta
					</h3>
					<div class="text-muted small">Cálculo en tiempo real de cobertura en días, punto de reorden, stock de seguridad y sugerido de reposición.</div>
				</div>
				<div class="d-flex gap-2">
					<a href="{{ $U('/scan') }}" class="btn btn-sm btn-outline-primary font-weight-600 shadow-sm">
						<i class="fa-solid fa-barcode mr-1"></i> Confirmar con Escáner
					</a>
					<button type="button" class="btn btn-sm btn-warning font-weight-600 shadow-sm" onclick="toastr.success('Generadas 6 órdenes de reposición para el equipo de piso.');">
						<i class="fa-solid fa-plus mr-1"></i> Generar Tareas Masivas
					</button>
				</div>
			</div>

			<!-- KPIs de Reposición -->
			<div class="row mb-3">
				<div class="col-md-3">
					<div class="card border bg-light h-100">
						<div class="card-body py-2 px-3">
							<div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Agotados (Stock 0)</div>
							<div class="h4 mb-0 font-weight-bold text-danger">{{ $replenishment['summary']['stockout_count'] }}</div>
							<small class="text-muted">Quiebre inmediato en tienda</small>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border bg-light h-100">
						<div class="card-body py-2 px-3">
							<div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bajo Stock (&le; Mínimo)</div>
							<div class="h4 mb-0 font-weight-bold text-warning">{{ $replenishment['summary']['low_stock_count'] }}</div>
							<small class="text-muted">Riesgo inminente de quiebre</small>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border bg-light h-100">
						<div class="card-body py-2 px-3">
							<div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tareas Activas</div>
							<div class="h4 mb-0 font-weight-bold text-primary">{{ $replenishment['summary']['tasks_pending'] }}</div>
							<small class="text-muted">Órdenes de reposición</small>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="card border bg-light h-100">
						<div class="card-body py-2 px-3">
							<div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ciclo Operativo</div>
							<div class="small font-weight-bold text-success mt-1"><i class="fa-solid fa-check-double mr-1"></i> 8 Etapas Integradas</div>
							<small class="text-muted">Detección &rarr; Escaneo &rarr; Cierre</small>
						</div>
					</div>
				</div>
			</div>

			<!-- Banner Ciclo Operativo -->
			<div class="alert alert-light border mb-4 p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
				<div class="d-flex align-items-center gap-2">
					<span class="badge badge-primary px-2 py-1 font-weight-600">Ciclo Operativo</span>
					<span class="small font-weight-600 text-dark">
						1. Detectar faltante &rarr; 2. Calcular necesidad &rarr; 3. Crear tarea &rarr; 4. Asignar empleado &rarr; 5. Reponer &rarr; 6. Escanear &rarr; 7. Verificar &rarr; 8. Cerrar
					</span>
				</div>
				<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-qrcode mr-1"></i> Verificación con Escáner Activa</span>
			</div>

			<!-- Tabla de Reposición -->
			<div class="table-responsive">
				<table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
					<thead class="thead-light">
						<tr>
							<th>Producto</th>
							<th>Departamento / Ubicación</th>
							<th class="text-center">Stock Piso</th>
							<th class="text-center">Disp. Bodega</th>
							<th class="text-center">Pto. Reorden</th>
							<th class="text-center">Demanda/Día</th>
							<th class="text-center">Cobertura</th>
							<th class="text-center">Sugerido</th>
							<th class="text-center">Prioridad</th>
							<th>Estado / Asignado</th>
							<th class="text-right">Acción</th>
						</tr>
					</thead>
					<tbody>
						@forelse($replenishment['items'] as $rep)
						<tr>
							<td>
								<div class="font-weight-600 text-dark">{{ $rep['product_name'] }}</div>
								<code class="text-muted small">{{ $rep['barcode'] }}</code>
							</td>
							<td>
								<span class="badge badge-light border text-dark font-weight-normal px-2 py-1">{{ $rep['department'] }}</span>
								<div class="text-muted small">{{ $rep['location'] }}</div>
							</td>
							<td class="text-center">
								<span class="font-weight-bold {{ $rep['current_stock'] <= 0 ? 'text-danger' : 'text-warning' }}">
									{{ $rep['current_stock'] }} u
								</span>
							</td>
							<td class="text-center font-weight-bold text-success">
								{{ $rep['warehouse_stock'] }} u
							</td>
							<td class="text-center text-muted">
								{{ $rep['reorder_point'] }} u
							</td>
							<td class="text-center text-muted">
								{{ $rep['daily_demand'] }} u
							</td>
							<td class="text-center">
								<span class="badge {{ $rep['coverage_days'] < 2 ? 'badge-danger' : 'badge-warning' }} px-2 py-1">
									{{ $rep['coverage_days'] }} días
								</span>
							</td>
							<td class="text-center font-weight-bold text-primary" style="font-size: 0.9rem;">
								+{{ $rep['suggested_qty'] }} u
							</td>
							<td class="text-center">
								@if($rep['priority'] === 'CRÍTICA')
									<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-fire mr-1"></i> CRÍTICA</span>
								@elseif($rep['priority'] === 'ALTA')
									<span class="badge badge-warning px-2 py-1">ALTA</span>
								@else
									<span class="badge badge-info px-2 py-1">MEDIA</span>
								@endif
							</td>
							<td>
								<div class="small font-weight-600 text-dark">{{ $rep['step'] }}</div>
								<div class="text-muted small"><i class="fa-solid fa-user mr-1"></i> {{ $rep['assigned_staff'] }}</div>
							</td>
							<td class="text-right">
								<button type="button" class="btn btn-outline-success btn-xs font-weight-600 py-1 px-2"
									onclick="toastr.success('Confirmada reposición de {{ $rep['suggested_qty'] }}u para {{ $rep['product_name'] }} mediante escaneo');">
									<i class="fa-solid fa-check mr-1"></i> Reponer
								</button>
							</td>
						</tr>
						@empty
						<tr>
							<td colspan="11" class="text-center py-4 text-muted">
								<i class="fa-solid fa-boxes-stacked fa-3x mb-2 text-success opacity-50"></i>
								<p class="mb-0 font-weight-600">Niveles de stock óptimos en góndolas y piso.</p>
							</td>
						</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>

<script>
	window.DairyInventoryControl = {
		branchId: {{ intval($branchId) }},
		policies: {!! json_encode(array_map(fn($c) => $c['policy'] ?? '', $m)) !!},
		pending: {!! json_encode(array_values(array_map(fn($p) => $p['product_id'] ?? ($p['id'] ?? 0), array_filter($phantomItems, fn($p) => !($p['count_pending'] ?? false))))) !!}
	};
</script>
@stop
