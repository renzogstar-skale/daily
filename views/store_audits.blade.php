@extends('layout.default')

@section('title', 'Auditorías de tienda')

@section('content')
@php
	$scored = array_filter($comparison, fn($c) => $c['avg_score'] !== null);
	$netAvg = count($scored) ? round(array_sum(array_column($scored, 'avg_score')) / count($scored), 1) : null;
	$stale = count(array_filter($comparison, fn($c) => !$c['last_audit'] || $c['last_audit'] < date('Y-m-d', strtotime('-30 days'))));
	$openTasks = array_sum(array_column($comparison, 'open_tasks'));
	$critical = array_sum(array_column($comparison, 'critical_failures'));
	$resultMeta = ['pass' => ['Cumple', 'success'], 'fail' => ['No cumple', 'danger'], 'na' => ['N/A', 'secondary']];
	$scoreTone = fn($s) => $s === null ? 'secondary' : ($s >= 90 ? 'success' : ($s >= 80 ? 'warning' : 'danger'));
@endphp

<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-warning"><i class="fa-solid fa-clipboard-list"></i></div>
		<div>
			<h4 class="dairy-page-title">Auditorías de tienda</h4>
			<p class="dairy-page-subtitle">Checklist ponderado por sucursal. Cada incumplimiento crea una tarea correctiva en Piso de Tienda con plazo; si vence, escala al gerente.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right dairy-page-actions">
		@if($canEditTemplates)<button class="btn btn-light border" id="aud-templates"><i class="fa-solid fa-pen-to-square mr-1"></i>Plantillas</button>@endif
		<button class="btn btn-primary" id="aud-start" @if(!count($templates)) disabled @endif><i class="fa-solid fa-play mr-1"></i>Nueva auditoría</button>
		@include('components.dairy_export')
	</div>
</div>

@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Puntaje Red', 'value' => $netAvg !== null ? $netAvg . ' %' : '—', 'sub' => 'promedio 90 días', 'icon' => 'fa-gauge-high', 'tone' => $scoreTone($netAvg)],
	['label' => 'Auditorías 90 d', 'value' => array_sum(array_column($comparison, 'audits')), 'sub' => 'completadas', 'icon' => 'fa-clipboard-check', 'tone' => 'primary'],
	['label' => 'Sin Auditar 30 d', 'value' => $stale, 'sub' => 'sucursales', 'icon' => 'fa-building-circle-exclamation', 'tone' => $stale ? 'warning' : 'success', 'alert' => $stale > 0],
	['label' => 'Correctivas', 'value' => $openTasks, 'sub' => 'tareas abiertas', 'icon' => 'fa-screwdriver-wrench', 'tone' => $openTasks ? 'warning' : 'success', 'href' => $U('/workforce')],
	['label' => 'Fallas Críticas', 'value' => $critical, 'sub' => 'en 90 días', 'icon' => 'fa-triangle-exclamation', 'tone' => $critical ? 'danger' : 'success'],
]])

@if($selected)
@php $done = $selected['status'] === 'completed'; @endphp
<div class="card mb-3" id="aud-detail" data-id="{{ $selected['id'] }}">
	<div class="card-header d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<div>
			<span class="font-mono font-weight-700">{{ $selected['audit_code'] }}</span> · {{ $selected['branch_name'] }} · {{ $selected['template_name'] }}
			@if($done)<span class="badge badge-{{ $scoreTone(floatval($selected['score'])) }} ml-1">{{ $selected['score'] }} %</span>@else<span class="badge badge-info ml-1">En curso</span>@endif
		</div>
		<a class="btn btn-sm btn-light border" href="{{ $U('/audits') }}" aria-label="Cerrar detalle"><i class="fa-solid fa-xmark"></i></a>
	</div>
	<div class="card-body">
		@foreach($selected['items'] as $it)
		<div class="aud-item" data-item="{{ $it['item_id'] }}">
			<div class="d-flex justify-content-between flex-wrap dairy-gap-2">
				<div class="font-weight-600">@if($it['is_critical'])<span class="badge badge-danger mr-1">Crítico</span>@endif{{ $it['question'] }} <span class="small text-muted">· peso {{ $it['weight'] + 0 }}</span></div>
				@if($done)
				<div>@if($it['result'])<span class="badge badge-{{ $resultMeta[$it['result']][1] }}">{{ $resultMeta[$it['result']][0] }}</span>@endif
					@if($it['task_id'])<span class="badge badge-{{ $it['task_status'] === 'pending' ? 'warning' : 'success' }}">Tarea {{ $it['task_status'] === 'pending' ? 'abierta' : 'cerrada' }}</span>@endif</div>
				@else
				<div class="btn-group btn-group-sm btn-group-toggle aud-choice" data-toggle="buttons">
					@foreach($resultMeta as $k => $rm)
					<label class="btn btn-outline-{{ $rm[1] }} @if($it['result'] === $k) active @endif"><input type="radio" name="r{{ $it['item_id'] }}" value="{{ $k }}" @if($it['result'] === $k) checked @endif>{{ $rm[0] }}</label>
					@endforeach
				</div>
				@endif
			</div>
			@if($done)
			@if($it['comment'])<div class="small text-muted mt-1">{{ $it['comment'] }}</div>@endif
			@else
			<input type="text" class="form-control form-control-sm mt-2 aud-comment" placeholder="Hallazgo (obligatorio si no cumple)" value="{{ $it['comment'] }}">
			@endif
		</div>
		@endforeach
		@if(!$done)
		<div class="form-group mt-3"><label for="aud-notes">Observaciones generales</label><textarea id="aud-notes" class="form-control" rows="2"></textarea></div>
		<div class="text-right">
			<button class="btn btn-light border" id="aud-save">Guardar avance</button>
			<button class="btn btn-success" id="aud-complete"><i class="fa-solid fa-flag-checkered mr-1"></i>Cerrar auditoría</button>
		</div>
		@elseif($selected['notes'])
		<div class="small text-muted mt-2">Observaciones: {{ $selected['notes'] }}</div>
		@endif
	</div>
</div>
@endif

<div class="row">
	<div class="col-lg-6 mb-3">
		<div class="card h-100">
			<div class="card-header font-weight-700 text-dark"><i class="fa-solid fa-ranking-star text-warning mr-2"></i>Comparativo por sucursal · 90 días</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Sucursal</th><th class="text-right">Promedio</th><th class="text-right">Última</th><th>Fecha</th><th class="text-right">Críticas</th><th class="text-right">Correctivas</th></tr></thead>
						<tbody>
							@forelse($comparison as $c)
							<tr>
								<td class="font-weight-600">{{ $c['branch_name'] }}</td>
								<td class="text-right"><span class="badge badge-{{ $scoreTone($c['avg_score'] !== null ? floatval($c['avg_score']) : null) }}">{{ $c['avg_score'] !== null ? $c['avg_score'] . ' %' : 'Sin datos' }}</span></td>
								<td class="text-right">{{ $c['last_score'] !== null ? $c['last_score'] . ' %' : '—' }}</td>
								<td class="small">{{ $c['last_audit'] ? substr($c['last_audit'], 0, 10) : 'Nunca' }}</td>
								<td class="text-right @if($c['critical_failures']) text-danger font-weight-600 @endif">{{ $c['critical_failures'] }}</td>
								<td class="text-right">{{ $c['open_tasks'] }}</td>
							</tr>
							@empty
							<tr><td colspan="6" class="text-center text-muted py-4">Sin sucursales.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-6 mb-3">
		<div class="card h-100">
			<div class="card-header font-weight-700 text-dark"><i class="fa-solid fa-list text-primary mr-2"></i>Auditorías</div>
			<div class="card-body p-0">
				<div class="table-responsive dairy-scroll-400">
					<table class="table table-sm table-hover mb-0">
						<thead><tr><th>Folio</th><th>Sucursal</th><th>Auditor</th><th class="text-right">Puntaje</th><th class="text-right">Hallazgos</th><th></th></tr></thead>
						<tbody>
							@forelse($audits as $a)
							<tr>
								<td class="font-mono">{{ $a['audit_code'] }}<div class="small text-muted">{{ substr($a['completed_at'] ?? $a['started_at'], 0, 16) }}</div></td>
								<td>{{ $a['branch_name'] }}</td>
								<td class="small">{{ $a['auditor'] }}</td>
								<td class="text-right">@if($a['status'] === 'completed')<span class="badge badge-{{ $scoreTone(floatval($a['score'])) }}">{{ $a['score'] }} %</span>@else<span class="badge badge-info">En curso</span>@endif</td>
								<td class="text-right">{{ $a['fails'] }}@if($a['open_tasks'])<div class="small text-warning">{{ $a['open_tasks'] }} abiertas</div>@endif</td>
								<td class="text-right"><a class="btn btn-sm btn-light border" href="{{ $U('/audits?id=' . $a['id']) }}">{{ $a['status'] === 'completed' ? 'Ver' : 'Continuar' }}</a></td>
							</tr>
							@empty
							<tr><td colspan="6" class="text-center text-muted py-4">Sin auditorías. Inicie la primera con «Nueva auditoría».</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.DairyAudits = { templates: {!! json_encode($templates) !!}, branches: {!! json_encode($branches) !!} };
</script>
@stop
