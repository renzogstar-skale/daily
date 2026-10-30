@extends('layout.default')

@section('title', 'Informe ejecutivo de pérdidas')

@section('content')
@php
	$p = $report['payload'] ?? null;
	$s = $p['summary'] ?? null;
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$lightTone = ['critico' => 'danger', 'atencion' => 'warning', 'controlado' => 'success', 'sin_meta' => 'secondary', 'sin_datos' => 'secondary'];
	$prioTone = ['alta' => 'danger', 'media' => 'warning', 'baja' => 'secondary'];
	$typeLabels = \Grocy\Services\LossExecutiveReportService::PERIODS;
@endphp
<style>
	/* Tablas dentro de tarjetas angostas: sin el ancho mínimo global (se recortaban los montos) */
	.ler-fit .table { min-width: 0; }
	@media print { .ler-fit .dairy-op-card { break-inside: avoid; } }
</style>
<div class="ler-fit">

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div class="dairy-page-icon is-purple"><i class="fa-solid fa-file-shield"></i></div>
		<div>
			<h4 class="dairy-page-title">Informe Ejecutivo de Pérdidas y Novedades — Control 360</h4>
			<p class="dairy-page-subtitle">Consolidado de todas las sucursales para el Director de Mermas. Mismas fuentes que Mermas e Inventario; corte al día anterior a la publicación.</p>
		</div>
	</div>
	<div class="col-lg-5 dairy-page-actions d-print-none">
		<div class="btn-group">
			@foreach($typeLabels as $k => $l)
			<a href="{{ $U('/reports/loss-executive?type=' . $k) }}" class="btn {{ $type === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $l }}</a>
			@endforeach
		</div>
		@if($report && $p)
		<button class="btn btn-outline-secondary" onclick="window.print();"><i class="fa-solid fa-file-pdf mr-1"></i>PDF</button>
		<a class="btn btn-outline-secondary" href="{{ $U('/api/reports/loss-executive/' . $report['id'] . '/xlsx') }}"><i class="fa-solid fa-file-excel mr-1"></i>Excel</a>
		@endif
		@if($canManage && $networkUser)
		<button class="btn btn-primary" id="ler-generate" data-type="{{ $type }}"><i class="fa-solid fa-rotate mr-1"></i>Generar ahora</button>
		@endif
	</div>
</div>

@if(!$networkUser)
	<div class="alert alert-warning">Este informe consolida toda la red de sucursales. Su usuario está limitado a sucursales específicas: consulte los módulos de Mermas e Inventario de su sucursal.</div>
@elseif(!$report)
	<div class="alert alert-info">Aún no hay un informe {{ strtolower($typeLabels[$type]) }} publicado. @if($canManage) Use «Generar ahora» o espere la programación configurada abajo. @endif</div>
@elseif($report['status'] === 'error')
	<div class="alert alert-danger"><b>{{ $report['report_code'] }}</b>: la generación falló el {{ $report['generated_at'] }} y no se entregó. Error: {{ $report['error_message'] }}</div>
@endif

@if($p)
	{{-- Estado de consolidación: nunca presentar datos incompletos como definitivos --}}
	<div class="alert {{ $p['completeness'] === 'definitivo' ? 'alert-success' : 'alert-warning' }} d-flex flex-wrap justify-content-between align-items-center">
		<div>
			<b>{{ $report['report_code'] }}</b> · {{ $p['period_label'] }} {{ date('d/m/Y', strtotime($p['from'])) }}@if($p['from'] !== $p['to']) – {{ date('d/m/Y', strtotime($p['to'])) }}@endif
			· Consolidado <b class="text-uppercase">{{ $p['completeness'] }}</b>
			({{ $s['branches_synced'] }}/{{ $s['branches_total'] }} sucursales sincronizadas al corte)
			@if($p['completeness'] !== 'definitivo')<br><small>Hay sucursales sin ventas registradas hasta el corte: las cifras pueden cambiar cuando sincronicen. Ver columna «Sincronización».</small>@endif
		</div>
		<small class="text-muted">Generado {{ $p['generated_at'] }} ({{ $p['timezone'] }}) · origen: {{ ['schedule' => 'programación', 'catch_up' => 'puesta al día al abrir', 'manual' => 'manual'][$report['trigger_type']] ?? $report['trigger_type'] }}
			· integridad {!! $report['integrity_ok'] ? '<span class="text-success">verificada</span>' : '<span class="text-danger">alterada</span>' !!}</small>
	</div>

	{{-- A. RESUMEN EJECUTIVO --}}
	@include('components.dairy_kpi_row', ['kpis' => [
		['label' => 'Sucursales incluidas', 'value' => $s['branches_total'], 'sub' => $s['branches_synced'] . ' sincronizadas al corte', 'icon' => 'fa-shop', 'tone' => $s['branches_synced'] < $s['branches_total'] ? 'warning' : 'success'],
		['label' => 'Valor del inventario', 'value' => $money($s['inventory_value']), 'sub' => number_format($s['inventory_units'], 0) . ' u. · al generar', 'icon' => 'fa-boxes-stacked', 'tone' => 'primary', 'href' => $U('/stockoverview')],
		['label' => 'Pérdida confirmada', 'value' => $money($s['confirmed_loss']), 'sub' => $s['events'] . ' eventos · ' . ($s['variation_pct'] === null ? 'sin período anterior' : ($s['variation_pct'] > 0 ? '+' : '') . $s['variation_pct'] . '% vs. anterior'), 'icon' => 'fa-trash-can', 'tone' => 'danger', 'href' => $U('/waste?start_date=' . $p['from'] . '&end_date=' . $p['to'])],
		['label' => '% merma s/ ventas netas', 'value' => $pct($s['loss_pct_sales']), 'sub' => 'Ventas netas ' . $money($s['net_sales']) . ($s['target'] ? ' · meta ' . $s['target']['budget_pct'] . '%' : ''), 'icon' => 'fa-percent', 'tone' => $lightTone[$s['status']['code']] ?? 'secondary'],
		['label' => 'En investigación', 'value' => $money($s['in_investigation']['value']), 'sub' => $s['in_investigation']['count'] . ' casos abiertos (no suman a la pérdida)', 'icon' => 'fa-magnifying-glass', 'tone' => 'warning', 'href' => $U('/reconciliation')],
		['label' => 'Recuperación financiera', 'value' => $money($s['recovered']), 'sub' => 'de ' . $money($s['recoverable']) . ' recuperable', 'icon' => 'fa-hand-holding-dollar', 'tone' => 'success', 'href' => $U('/waste?start_date=' . $p['from'] . '&end_date=' . $p['to'] . '#tab-recuperacion')],
	]])

	<div class="row">
		<div class="col-lg-5 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-traffic-light mr-2 text-{{ $lightTone[$s['status']['code']] ?? 'secondary' }}"></i>Estado general del control de pérdidas</span></div>
				<div class="dairy-op-body">
					<div class="h4 text-{{ $lightTone[$s['status']['code']] ?? 'secondary' }} mb-2">{{ $s['status']['label'] }}</div>
					<table class="table table-sm mb-2">
						@foreach(['operativa' => 'Operativa', 'comercial' => 'Comercial', 'ajuste' => 'Ajustes de inventario (neto)', 'averia' => 'Averías', 'robo_interno' => 'Hurto interno', 'robo_externo' => 'Hurto externo', 'investigacion' => 'Desconocida / investigación'] as $k => $l)
						<tr><td>{{ $l }}</td><td class="text-right">{{ $money($s['by_class'][$k] ?? 0) }}</td></tr>
						@endforeach
						<tr class="font-weight-bold"><td>Pérdida confirmada</td><td class="text-right">{{ $money($s['confirmed_loss']) }}</td></tr>
						<tr class="text-muted"><td>Período anterior</td><td class="text-right">{{ $money($s['prev_period_loss']) }}</td></tr>
					</table>
					<p class="small text-muted mb-0">Fuente: {{ $s['sources']['loss'] }}. Inventario: {{ $s['sources']['inventory'] }}. @if(!$s['target']) Sin meta de merma configurada para {{ substr($p['to'], 0, 4) }} (Mermas › Censo). @endif</p>
				</div>
			</div>
		</div>
		<div class="col-lg-7 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-magnifying-glass-dollar mr-2 text-warning"></i>Diferencias en investigación</span></div>
				<div class="dairy-op-body">
					<table class="table table-sm mb-0">
						<thead><tr><th>Componente</th><th class="text-right">Casos</th><th class="text-right">Valor</th></tr></thead>
						@foreach($s['in_investigation']['parts'] as $part)
						<tr><td>{{ $part['label'] }}</td><td class="text-right">{{ $part['count'] }}</td><td class="text-right">{{ $money($part['value']) }}</td></tr>
						@endforeach
					</table>
				</div>
			</div>
		</div>
	</div>

	{{-- E. ACCIONES Y RESPONSABLES (prioridades primero) --}}
	<div class="dairy-op-card mb-3">
		<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-list-check mr-2 text-danger"></i>Prioridades del día · acciones y responsables</span><span class="text-muted dairy-fs-sm">{{ count($p['actions']) }} acciones</span></div>
		<div class="dairy-op-body p-0"><div class="table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Qué ocurrió</th><th>Causa</th><th>Evidencia</th><th>Quién interviene</th><th>Acción correctiva</th><th>Fecha límite</th><th>Estado</th></tr></thead>
				<tbody>
				@forelse($p['actions'] as $a)
					<tr>
						<td><a href="{{ $U($a['href']) }}">{{ $a['what'] }}</a></td>
						<td>{{ $a['cause'] }} <span class="badge badge-{{ $a['cause_kind'] === 'confirmada' ? 'dark' : ($a['cause_kind'] === 'probable' ? 'info' : 'light border') }}">{{ $a['cause_kind'] }}</span></td>
						<td class="small">{{ $a['evidence'] }}</td><td>{{ $a['who'] }}</td><td>{{ $a['action'] }}</td>
						<td class="text-nowrap">{{ date('d/m/Y', strtotime($a['due'])) }}</td><td>{{ $a['status'] }}</td>
					</tr>
				@empty
					<tr><td colspan="7" class="text-center text-muted">Sin acciones prioritarias.</td></tr>
				@endforelse
				</tbody>
			</table>
		</div></div>
	</div>

	{{-- C. COMPARATIVO ENTRE SUCURSALES --}}
	@php $maxLoss = max(array_merge([0.01], array_map(fn($b) => abs($b['loss']), $p['branches']))); @endphp
	<div class="dairy-op-card mb-3">
		<div class="dairy-op-header">
			<span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-ranking-star mr-2 text-primary"></i>Comparativo entre sucursales</span>
			<span class="text-muted dairy-fs-sm d-print-none">Clic en un encabezado para ordenar · clic en la sucursal para ver el detalle en Mermas</span>
		</div>
		<div class="dairy-op-body p-0"><div class="table-responsive">
			<table class="table table-sm table-hover mb-0" id="ler-branches">
				<thead><tr>
					<th data-sort="text">Sucursal</th><th class="text-right" data-sort="num">Inventario</th><th class="text-right" data-sort="num">Pérdida</th><th style="width:16%">Distribución</th>
					<th class="text-right" data-sort="num">% Merma</th><th class="text-right" data-sort="num">Novedades</th><th data-sort="text">Estado</th><th data-sort="text">Sincronización</th>
				</tr></thead>
				<tbody>
				@foreach($p['branches'] as $b)
					<tr>
						<td data-v="{{ $b['name'] }}"><a href="{{ $U($b['drill']) }}">{{ $b['name'] }}</a></td>
						<td class="text-right" data-v="{{ $b['inventory'] }}">{{ $money($b['inventory']) }}</td>
						<td class="text-right" data-v="{{ $b['loss'] }}">{{ $money($b['loss']) }}</td>
						<td><div class="dairy-bg-subtle dairy-radius-xs" style="height:8px"><div style="height:8px;width:{{ round(abs($b['loss']) / $maxLoss * 100) }}%;background:var(--dairy-danger)"></div></div></td>
						<td class="text-right" data-v="{{ $b['pct'] ?? -1 }}">{{ $pct($b['pct']) }}</td>
						<td class="text-right" data-v="{{ $b['novelties'] }}">{{ $b['novelties'] }}@if($b['critical']) <span class="badge badge-danger">{{ $b['critical'] }} altas</span>@endif</td>
						<td data-v="{{ $b['status']['label'] }}"><span class="badge badge-{{ $lightTone[$b['status']['code']] ?? 'secondary' }}">{{ $b['status']['label'] }}</span></td>
						<td data-v="{{ $b['sync']['synced'] ? 1 : 0 }}" class="small">
							@if($b['sync']['synced'])<span class="text-success"><i class="fa-solid fa-circle-check"></i> Al día</span>
							@else<span class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> Sin sincronizar</span>@endif
							<br><span class="text-muted">Últ. venta {{ $b['sync']['last_sale_at'] ? substr($b['sync']['last_sale_at'], 0, 16) : 'nunca' }}</span>
						</td>
					</tr>
				@endforeach
				</tbody>
				<tfoot><tr class="font-weight-bold">
					<td>Red ({{ count($p['branches']) }})</td><td class="text-right">{{ $money(array_sum(array_column($p['branches'], 'inventory'))) }}</td>
					<td class="text-right">{{ $money(array_sum(array_column($p['branches'], 'loss'))) }}</td><td></td><td class="text-right">{{ $pct($s['loss_pct_sales']) }}</td>
					<td class="text-right">{{ array_sum(array_column($p['branches'], 'novelties')) }}</td><td colspan="2"></td>
				</tr></tfoot>
			</table>
		</div></div>
	</div>

	{{-- B. NOVEDADES --}}
	<div class="dairy-op-card mb-3">
		<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-bell mr-2 text-warning"></i>Novedades de la pérdida</span><span class="text-muted dairy-fs-sm">{{ $p['novelties']['total'] }} novedades</span></div>
		<div class="dairy-op-body">
			<div class="d-flex flex-wrap dairy-gap-2 mb-2 d-print-none" id="ler-nov-filter">
				<button class="btn btn-sm btn-primary" data-type="">Todas ({{ $p['novelties']['total'] }})</button>
				@foreach($p['novelties']['by_type'] as $k => $t)
					@if($t['count'] > 0)<button class="btn btn-sm btn-outline-secondary" data-type="{{ $k }}">{{ $t['label'] }} ({{ $t['count'] }})</button>@endif
				@endforeach
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0" id="ler-novelties">
					<thead><tr><th>Prioridad</th><th>Novedad</th><th>Sucursal</th><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Valor</th><th>Fecha</th><th>Responsable</th><th>Estado</th><th>Acción requerida</th></tr></thead>
					<tbody>
					@forelse($p['novelties']['items'] as $i => $n)
						<tr data-type="{{ $n['type'] }}" @if($i >= 25) class="ler-more d-none" @endif>
							<td><span class="badge badge-{{ $prioTone[$n['priority']] }}">{{ $n['priority'] }}</span></td>
							<td>{{ $n['type_label'] }}@if($n['doc'])<br><small class="text-muted">{{ $n['doc'] }}</small>@endif</td>
							<td>{{ $n['branch'] ?? '—' }}</td>
							<td>@if($n['product_id'])<a href="{{ $U('/reconciliation?product_id=' . $n['product_id'] . ($n['branch_id'] ? '&branch_id=' . $n['branch_id'] : '')) }}">{{ $n['product'] }}</a>@else{{ $n['product'] ?? '—' }}@endif @if($n['sku'])<br><small class="text-muted">{{ $n['sku'] }}</small>@endif</td>
							<td class="text-right">{{ $n['qty'] === null ? '—' : $n['qty'] }}</td>
							<td class="text-right">{{ $money($n['value']) }}</td>
							<td class="text-nowrap">{{ $n['date'] ? date('d/m/Y', strtotime($n['date'])) : '—' }}</td>
							<td>{{ $n['responsible'] }}</td><td>{{ $n['status'] }}</td>
							<td><a href="{{ $U($n['href']) }}">{{ $n['action'] }}</a></td>
						</tr>
					@empty
						<tr><td colspan="10" class="text-center text-muted">Sin novedades registradas en el período.</td></tr>
					@endforelse
					</tbody>
				</table>
			</div>
			@if(count($p['novelties']['items']) > 25)<button class="btn btn-sm btn-link d-print-none" id="ler-show-more">Ver las {{ count($p['novelties']['items']) - 25 }} restantes</button>@endif
		</div>
	</div>

	{{-- D. PRODUCTOS --}}
	<div class="row">
		@foreach([['top_cost', 'Top 10 SKU por pérdida financiera', 'fa-sack-xmark', 'cost'], ['top_units', 'Top 10 por diferencia en unidades (ajustes)', 'fa-scale-unbalanced', 'adj_units']] as [$key, $title, $icon, $field])
		<div class="col-lg-6 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid {{ $icon }} mr-2 text-danger"></i>{{ $title }}</span></div>
				<div class="dairy-op-body p-0"><table class="table table-sm mb-0">
					@forelse($p['products'][$key] as $i => $x)
						<tr><td class="text-muted">{{ $i + 1 }}</td><td><a href="{{ $U('/reconciliation?product_id=' . $x['product_id']) }}">{{ $x['product'] }}</a><br><small class="text-muted">SKU {{ $x['sku'] ?: '—' }} · UPC/EAN {{ $x['barcode'] ?: '—' }}</small></td>
							<td class="text-right">{{ $field === 'cost' ? $money($x['cost']) : number_format($x['adj_units'], 2) . ' u.' }}</td></tr>
					@empty
						<tr><td class="text-center text-muted">Sin registros en el período.</td></tr>
					@endforelse
				</table></div>
			</div>
		</div>
		@endforeach
		<div class="col-lg-4 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-repeat mr-2 text-warning"></i>Pérdidas recurrentes (90 días)</span></div>
				<div class="dairy-op-body p-0"><table class="table table-sm mb-0">
					@forelse($p['products']['recurring'] as $x)
						<tr><td><a href="{{ $U('/reconciliation?product_id=' . $x['product_id']) }}">{{ $x['product'] }}</a></td><td class="text-right">{{ $x['weeks'] }} sem.</td><td class="text-right">{{ $money($x['cost_90d']) }}</td></tr>
					@empty
						<tr><td class="text-center text-muted">Sin productos con pérdida en 3 o más semanas.</td></tr>
					@endforelse
				</table></div>
			</div>
		</div>
		<div class="col-lg-4 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-shield-halved mr-2 text-purple"></i>Sensibles sin conteo reciente (30 días)</span></div>
				<div class="dairy-op-body p-0"><table class="table table-sm mb-0">
					@forelse($p['products']['sensitive_no_count'] as $x)
						<tr><td><a href="{{ $U('/reconciliation?product_id=' . $x['product_id']) }}">{{ $x['product'] }}</a> <small class="text-muted">{{ $x['sku'] }}</small></td><td class="text-right small">{{ $x['last_count'] ?: 'nunca contado' }}</td></tr>
					@empty
						<tr><td class="text-center text-muted">Todos los productos sensibles tienen conteo reciente.</td></tr>
					@endforelse
				</table></div>
			</div>
		</div>
		<div class="col-lg-4 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-warehouse mr-2 text-info"></i>Diferencias sucursal – CEDIS</span></div>
				<div class="dairy-op-body">
					@if(!$p['cedis']['available'])
						<p class="small text-muted mb-0"><i class="fa-solid fa-circle-info mr-1"></i>{{ $p['cedis']['message'] }}</p>
					@else
						<table class="table table-sm mb-0">
							@forelse($p['cedis']['items'] as $x)
								<tr><td>{{ $x['transfer_code'] }}<br><small>{{ $x['branch'] }}</small></td><td>{{ $x['product'] }}</td><td class="text-right">{{ $x['difference_amount'] }}</td><td>{{ $x['status'] }}</td></tr>
							@empty
								<tr><td class="text-center text-muted">Sin diferencias con el CEDIS.</td></tr>
							@endforelse
						</table>
					@endif
				</div>
			</div>
		</div>
	</div>

	{{-- Integridad y entrega --}}
	<div class="row">
		<div class="col-lg-6 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-link mr-2 text-primary"></i>Integridad de los datos</span></div>
				<div class="dairy-op-body small">
					@if(($p['integrity']['ledger_ok'] ?? null) === null)
						<span class="text-muted">No se pudo verificar el libro de movimientos: {{ $p['integrity']['error'] ?? '' }}</span>
					@else
						Libro inalterable de movimientos: <b class="{{ $p['integrity']['ledger_ok'] ? 'text-success' : 'text-danger' }}">{{ $p['integrity']['ledger_ok'] ? 'sin inconsistencias' : $p['integrity']['issues'] . ' inconsistencias' }}</b>
						· cadena de sellos {{ $p['integrity']['chain_ok'] ? 'íntegra' : 'rota' }} · {{ number_format($p['integrity']['movements']) }} movimientos.
					@endif
					<br>Huella SHA-256 del informe: <code>{{ substr($report['payload_sha256'], 0, 16) }}…</code>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-3">
			<div class="dairy-op-card h-100">
				<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-paper-plane mr-2 text-success"></i>Entrega</span></div>
				<div class="dairy-op-body p-0"><table class="table table-sm mb-0">
					<thead><tr><th>Destinatario</th><th>Canal</th><th>Estado</th><th>Leído</th></tr></thead>
					@forelse($report['deliveries'] as $d)
						<tr><td>{{ $d['user_name'] ?: $d['username'] }}</td><td>{{ $d['channel'] === 'app' ? 'Plataforma' : 'Correo' }}</td>
							<td>@if($d['status'] === 'no_configurado')<span class="text-muted">No configurado</span>@else{{ $d['status'] }}@endif</td><td class="small">{{ $d['read_at'] ? substr($d['read_at'], 0, 16) : '—' }}</td></tr>
					@empty
						<tr><td colspan="4" class="text-muted text-center">Sin destinatarios con permiso de Prevención de pérdidas.</td></tr>
					@endforelse
				</table></div>
			</div>
		</div>
	</div>
@endif

@if($networkUser)
<div class="row d-print-none">
	<div class="col-lg-5 mb-3">
		<div class="dairy-op-card h-100">
			<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-clock mr-2 text-primary"></i>Programación automática</span></div>
			<div class="dairy-op-body p-0"><table class="table table-sm mb-0">
				<thead><tr><th>Informe</th><th>Hora</th><th>Activo</th><th>Próxima</th>@if($canManage)<th></th>@endif</tr></thead>
				@foreach($schedules as $sc)
				<tr data-type="{{ $sc['period_type'] }}">
					<td>{{ $typeLabels[$sc['period_type']] }}</td>
					<td><input type="time" class="form-control form-control-sm ler-time" value="{{ $sc['run_time'] }}" @if(!$canManage) disabled @endif></td>
					<td><input type="checkbox" class="ler-active" @if($sc['active']) checked @endif @if(!$canManage) disabled @endif></td>
					<td class="small">{{ $sc['next_run_at'] ? substr($sc['next_run_at'], 0, 16) : '—' }}</td>
					@if($canManage)<td><button class="btn btn-sm btn-outline-primary ler-save-schedule">Guardar</button></td>@endif
				</tr>
				@endforeach
			</table>
			<p class="small text-muted p-2 mb-0">Zona horaria corporativa: {{ $schedules[0]['timezone'] ?? date_default_timezone_get() }}. Semanal: lunes · mensual: día 1 · anual: 1 de enero.
				Se ejecuta con <code>php bin/dairy_scheduler.php</code> (Programador de tareas de Windows / cron cada 15 min) y, como respaldo, al abrir esta página.
				Sin programador activo y sin visitas, el informe no se genera.</p></div>
		</div>
	</div>
	<div class="col-lg-7 mb-3">
		<div class="dairy-op-card h-100">
			<div class="dairy-op-header"><span class="font-weight-700 text-dark dairy-fs-md"><i class="fa-solid fa-clock-rotate-left mr-2 text-secondary"></i>Historial de informes</span></div>
			<div class="dairy-op-body p-0"><div class="table-responsive" style="max-height:320px">
				<table class="table table-sm table-hover mb-0">
					<thead><tr><th>Código</th><th>Tipo</th><th>Corte</th><th>Consolidado</th><th>Origen</th><th>Generado</th><th>Leído</th></tr></thead>
					@forelse($history as $h)
					<tr @if($report && $h['id'] == $report['id']) class="table-active" @endif>
						<td><a href="{{ $U('/reports/loss-executive?type=' . $h['period_type'] . '&id=' . $h['id']) }}">{{ $h['report_code'] }}</a></td>
						<td>{{ $typeLabels[$h['period_type']] ?? $h['period_type'] }}</td><td class="small">{{ $h['period_to'] }}</td>
						<td>@if($h['status'] === 'error')<span class="badge badge-danger">error</span>@else<span class="badge badge-{{ $h['completeness'] === 'definitivo' ? 'success' : 'warning' }}">{{ $h['completeness'] }}</span>@endif</td>
						<td class="small">{{ $h['trigger_type'] }}</td><td class="small">{{ substr($h['generated_at'], 0, 16) }}</td><td class="small">{{ $h['read_count'] }}/{{ $h['recipients'] }}</td>
					</tr>
					@empty
					<tr><td colspan="7" class="text-center text-muted">Sin informes generados.</td></tr>
					@endforelse
				</table>
			</div></div>
		</div>
	</div>
</div>
@endif
</div>
@stop
