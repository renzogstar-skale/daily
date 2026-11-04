/* DAILY Process Flow — expediente reutilizable.
 * Uso: <button data-process-flow="customer_order:15">Ver expediente</button>  o  DailyProcessFlow.open('waste_record', 42)
 * Todo lo que muestra viene de /api/process-flow/{tipo}/{id}: el progreso se calcula en el backend con los estados reales. */
(function ()
{
	var ICON = { done: 'fa-circle-check text-success', current: 'fa-circle-dot text-primary', blocked: 'fa-circle-pause text-warning', pending: 'fa-circle text-muted',
		skipped: 'fa-circle-minus text-warning', failed: 'fa-circle-xmark text-danger', cancelled: 'fa-circle text-muted', na: 'fa-circle text-light' };
	var OUTCOME = { active: ['En curso', 'primary'], blocked: ['Bloqueado', 'warning'], closed: ['Cerrado', 'success'], interrupted: ['Interrumpido', 'danger'] };

	function esc(v)
	{
		return v === null || v === undefined ? '' : String(v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}

	function url(path)
	{
		return /^(https?:)?\/\//.test(path) || path.charAt(0) === '#' ? path : U(path);
	}

	function render(f)
	{
		var o = OUTCOME[f.outcome] || [f.outcome, 'secondary'];
		var h = '<div class="dpf" data-dpf-type="' + esc(f.type) + '" data-dpf-id="' + esc(f.id) + '">';
		h += '<div class="d-flex flex-wrap align-items-start justify-content-between mb-2"><div><div class="small text-muted">' + esc(f.type_name) + ' · ' + esc(f.module) + '</div>';
		h += '<h5 class="mb-1">' + esc(f.title) + '</h5><span class="badge badge-' + o[1] + '">' + esc(o[0]) + '</span> <span class="badge badge-light">' + esc(f.status_label) + '</span>';
		if (f.overdue) h += ' <span class="badge badge-danger">Vencido</span>';
		h += '</div><div class="text-right small"><div><b>' + esc(f.code) + '</b></div><div>Creado: ' + esc(f.created_at || '—') + '</div><div>Fecha límite: ' + esc(f.due_at || '—') + '</div><div>Prioridad: ' + esc(f.priority) + '</div></div></div>';
		h += '<div class="progress dpf-progress mb-1" title="' + f.steps_done + ' de ' + f.steps_total + ' pasos"><div class="progress-bar bg-' + o[1] + '" style="width:' + f.progress_pct + '%">' + f.progress_pct + '%</div></div>';
		h += '<div class="row small mb-3"><div class="col-sm-4"><span class="text-muted">Paso actual:</span> ' + esc(f.current_step || '—') + '</div><div class="col-sm-4"><span class="text-muted">Próximo:</span> ' + esc(f.next_step || '—') + '</div>';
		h += '<div class="col-sm-4"><span class="text-muted">Responsable:</span> ' + esc(f.owner ? f.owner.name : (f.responsible_role || (f.outcome === 'closed' || f.outcome === 'interrupted' ? '—' : 'Sin asignar'))) + '</div></div>';
		h += '<ol class="dpf-steps list-unstyled mb-3">';
		f.steps.forEach(function (s, i)
		{
			h += '<li class="dpf-step is-' + s.state + '"><i class="fa-solid ' + (ICON[s.state] || 'fa-circle') + '"></i> <b>' + (i + 1) + '. ' + esc(s.label) + '</b> — ' + esc(s.state_label);
			if (s.at || s.by) h += ' <span class="text-muted">· ' + esc(s.at || '') + (s.by ? ' · ' + esc(s.by) : '') + '</span>';
			if (s.note) h += '<div class="small text-muted ml-4">' + esc(s.note) + '</div>';
			h += '</li>';
		});
		h += '</ol>';
		if (f.related.length)
		{
			h += '<h6>Documentos relacionados</h6><ul class="small">';
			f.related.forEach(function (r)
			{
				var inner = r.type && r.id && r.href && ['customer_order', 'waste_record', 'stock_transfer', 'customer_return', 'alert', 'inventory_adjustment', 'quarantine', 'store_task', 'procurement_order', 'count_session'].indexOf(r.type) >= 0;
				h += '<li>' + (inner ? '<a href="#" data-process-flow="' + esc(r.type) + ':' + esc(r.id) + '">' + esc(r.label) + '</a>' : (r.href ? '<a href="' + esc(url(r.href)) + '">' + esc(r.label) + '</a>' : esc(r.label))) + '</li>';
			});
			h += '</ul>';
		}
		if (f.evidence.length)
		{
			h += '<h6>Evidencias</h6><ul class="small">' + f.evidence.map(function (e) { return '<li>' + esc(e.description || e.kind) + ': ' + esc(e.reference) + '</li>'; }).join('') + '</ul>';
		}
		if (f.workflows && f.workflows.length)
		{
			h += '<h6>Procesos manuales vinculados</h6><ul class="small">' + f.workflows.map(function (w) { return '<li>' + esc(w.name) + ' — ' + esc(w.status) + ' (' + w.progress_pct + '%)</li>'; }).join('') + '</ul>';
		}
		h += '<h6>Historial</h6>' + (f.history.length ? '<ul class="small dpf-history">' + f.history.map(function (e)
		{
			return '<li><span class="text-muted">' + esc(e.at) + '</span> · ' + esc(e.action) + (e.user ? ' · <b>' + esc(e.user) + '</b>' : '') + (e.note ? '<div class="text-muted">' + esc(e.note) + '</div>' : '') + '</li>';
		}).join('') + '</ul>' : '<p class="small text-muted">Sin eventos registrados.</p>');
		if (f.next_module) h += '<p class="small mb-0"><span class="text-muted">El resultado continúa en:</span> ' + esc(f.next_module) + '</p>';
		h += '</div>';
		return h;
	}

	function open(type, id)
	{
		var dlg = bootbox.dialog({ title: 'Expediente', size: 'large', onEscape: true, backdrop: true, message: '<p class="text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando expediente…</p>' });
		Grocy.Api.Get('process-flow/' + encodeURIComponent(type) + '/' + encodeURIComponent(id), function (f)
		{
			dlg.find('.bootbox-body').html(render(f));
			dlg.find('.modal-title').text('Expediente ' + f.code);
			dlg.find('.modal-footer').remove();
			dlg.find('.modal-content').append('<div class="modal-footer"><a class="btn btn-primary" href="' + esc(url(f.href)) + '"><i class="fa-solid fa-arrow-right"></i> Continuar en ' + esc(f.module) + '</a>'
				+ '<a class="btn btn-outline-secondary" href="' + esc(U('/process-flow?type=' + f.type + '&id=' + f.id)) + '">Enlace al expediente</a></div>');
		}, function (xhr)
		{
			var msg = (xhr && xhr.responseText) ? (function () { try { return JSON.parse(xhr.responseText).error_message; } catch (e) { return xhr.statusText; } })() : 'Error de red';
			dlg.find('.bootbox-body').html('<div class="alert alert-danger mb-0">No se pudo cargar el expediente: ' + esc(msg || 'Error de red') + '</div>');
		});
	}

	$(document).on('click', '[data-process-flow]', function (e)
	{
		e.preventDefault();
		var p = String($(this).attr('data-process-flow')).split(':');
		if (p.length === 2) open(p[0], p[1]);
	});

	window.DailyProcessFlow = { open: open, render: render, esc: esc };
})();
