@extends('layout.default')

@section('title', 'Expedientes y Procesos')

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-lg-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-diagram-next"></i></div>
		<div>
			<h4 class="dairy-page-title">Expedientes y Procesos</h4>
			<p class="dairy-page-subtitle">Cada documento abierto de la operación con su paso actual, responsable y fecha límite. El progreso sale del estado real de cada documento.</p>
		</div>
	</div>
	<div class="col-lg-5 dairy-page-actions">
		<select id="dpf-type" class="custom-select custom-select-sm w-auto">
			<option value="">Todos los procesos</option>
			@foreach($types as $t)
			<option value="{{ $t['code'] }}" @if($openType === $t['code']) selected @endif>{{ $t['name'] }}</option>
			@endforeach
		</select>
		<button type="button" id="dpf-reload" class="btn btn-sm btn-primary"><i class="fa-solid fa-rotate"></i> Actualizar</button>
	</div>
</div>
<div class="card mb-3" id="dpf-exceptions-card">
	<div class="card-header d-flex justify-content-between align-items-center">
		<b><i class="fa-solid fa-triangle-exclamation text-danger"></i> Centro de Excepciones · operaciones bloqueadas o inconsistentes</b>
		<a href="{{ $U('/alerts') }}" class="small">Gestionar en Centro de Alertas →</a>
	</div>
	<div class="card-body p-2" id="dpf-exceptions"><p class="text-muted small mb-0"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p></div>
</div>
<div id="dpf-summary" class="row mb-2"></div>
<div id="dpf-inbox"><p class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando bandeja…</p></div>
@stop

@push('pageScripts')
<script>
(function ()
{
	var esc = DailyProcessFlow.esc;
	function load()
	{
		var type = $('#dpf-type').val();
		$('#dpf-inbox').html('<p class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando bandeja…</p>');
		Grocy.Api.Get('process-flow/inbox?limit=25' + (type ? '&type=' + encodeURIComponent(type) : ''), function (groups)
		{
			var sum = '', h = '';
			groups.forEach(function (g)
			{
				sum += '<div class="col-6 col-md-3 col-xl-2 mb-2"><div class="card h-100"><div class="card-body p-2"><div class="small text-muted">' + esc(g.name) + '</div><div class="h5 mb-0">' + g.total + '</div>'
					+ '<div class="small">' + (g.overdue ? '<span class="text-danger">' + g.overdue + ' vencidos</span> · ' : '') + (g.unassigned ? g.unassigned + ' sin responsable' : '') + '</div></div></div></div>';
				h += '<div class="card dpf-inbox-card mb-3"><div class="card-header d-flex justify-content-between"><b>' + esc(g.name) + ' <span class="badge badge-secondary">' + g.total + '</span></b><a href="' + esc(U(g.href)) + '">' + esc(g.module) + ' →</a></div>';
				if (!g.items.length)
				{
					h += '<div class="card-body text-muted small">Sin documentos abiertos.</div></div>';
					return;
				}
				h += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Expediente</th><th>Estado</th><th>Progreso</th><th>Paso actual</th><th>Próximo</th><th>Responsable</th><th>Límite</th></tr></thead><tbody>';
				g.items.forEach(function (f)
				{
					h += '<tr' + (f.overdue ? ' class="table-danger"' : '') + '><td><a href="#" data-process-flow="' + esc(f.type) + ':' + f.id + '"><b>' + esc(f.code) + '</b></a><div class="small text-muted">' + esc(f.title) + '</div></td>'
						+ '<td>' + esc(f.status_label) + '</td><td><div class="progress dpf-mini"><div class="progress-bar" style="width:' + f.progress_pct + '%"></div></div><span class="small">' + f.steps_done + '/' + f.steps_total + '</span></td>'
						+ '<td>' + esc(f.current_step || '—') + '</td><td>' + esc(f.next_step || '—') + '</td><td>' + esc(f.owner ? f.owner.name : (f.responsible_role || 'Sin asignar')) + '</td><td>' + esc(f.due_at || '—') + '</td></tr>';
				});
				h += '</tbody></table></div>' + (g.total > g.items.length ? '<div class="card-footer small text-muted">Mostrando ' + g.items.length + ' de ' + g.total + '</div>' : '') + '</div>';
			});
			$('#dpf-summary').html(sum);
			$('#dpf-inbox').html(h || '<div class="alert alert-info">No tiene acceso a ningún proceso.</div>');
		}, function ()
		{
			$('#dpf-inbox').html('<div class="alert alert-danger">No se pudo cargar la bandeja. <button class="btn btn-sm btn-outline-danger" id="dpf-retry">Reintentar</button></div>');
		});
	}
	function loadExceptions()
	{
		Grocy.Api.Get('process-flow/exceptions', function (x)
		{
			if (!x.groups.length)
			{
				$('#dpf-exceptions').html('<p class="text-success small mb-0"><i class="fa-solid fa-circle-check"></i> Sin operaciones bloqueadas ni inconsistencias activas.</p>');
				return;
			}
			var h = '<p class="small mb-2"><b>' + x.total + '</b> excepciones activas · <span class="text-danger">' + x.critical + ' críticas</span> · $' + x.value.toFixed(2) + ' comprometidos</p><div class="accordion" id="dpf-exc-acc">';
			x.groups.forEach(function (g, i)
			{
				h += '<div class="border rounded mb-1"><a class="d-flex justify-content-between p-2 text-body" data-toggle="collapse" href="#dpf-exc-' + i + '">'
					+ '<span><span class="badge badge-' + (g.severity === 'danger' ? 'danger' : 'warning') + '">' + g.count + '</span> ' + esc(g.name) + '</span>'
					+ '<span class="small text-muted">' + (g.overdue ? g.overdue + ' vencidas · ' : '') + (g.unassigned ? g.unassigned + ' sin responsable' : '') + '</span></a>'
					+ '<div id="dpf-exc-' + i + '" class="collapse" data-parent="#dpf-exc-acc"><div class="px-2 pb-2 small"><div class="text-muted mb-1">Qué hacer: ' + esc(g.action || '—') + '</div><ul class="mb-0">'
					+ g.items.map(function (it)
					{
						return '<li' + (it.overdue ? ' class="text-danger"' : '') + '><a href="' + esc(U(it.href || '/alerts')) + '">' + esc(it.title) + '</a> · ' + esc(it.status) + ' · ' + esc(it.owner || 'sin responsable') + (it.due_at ? ' · límite ' + esc(it.due_at) : '') + '<div class="text-muted">' + esc(it.detail || '') + '</div></li>';
					}).join('') + '</ul>' + (g.count > g.items.length ? '<div class="text-muted">… y ' + (g.count - g.items.length) + ' más en el Centro de Alertas</div>' : '') + '</div></div></div>';
			});
			$('#dpf-exceptions').html(h + '</div>');
		}, function (xhr)
		{
			$('#dpf-exceptions-card').toggle(xhr && xhr.status !== 400);
			$('#dpf-exceptions').html('<p class="text-danger small mb-0">No se pudieron cargar las excepciones.</p>');
		});
	}
	loadExceptions();
	$('#dpf-reload').on('click', function () { load(); loadExceptions(); });
	$('#dpf-type').on('change', load);
	$(document).on('click', '#dpf-retry', load);
	load();
	@if($openType && $openId)
	DailyProcessFlow.open(@json($openType), {{ $openId }});
	@endif
})();
</script>
@endpush
