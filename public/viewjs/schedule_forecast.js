/* Pronóstico laboral (Módulo 17): dotación por franja según ventas y carga operativa, alertas, simulación y aplicación */
(function ($) {
	'use strict';
	var S = window.DairySchedule || {};
	var F = null; // último pronóstico
	var loaded = false;
	var dayNames = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
	var roleKeys = ['ventas', 'reposicion', 'inventario', 'operaciones'];
	var links = { pos: 'pos', procurement: 'procurement', 'quality-expiry': 'quality-expiry', 'cost-centers': 'cost-centers' };

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;'); }
	function errMsg(xhr) { try { return JSON.parse(xhr.responseText).error_message || 'Error'; } catch (e) { return 'No se pudo completar la operación'; } }
	function money(v) { return v == null ? '—' : '$' + Number(v).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function num(v, d) { return v == null ? '—' : Number(v).toLocaleString('es', { maximumFractionDigits: d == null ? 1 : d }); }
	function date() { return $('#fc-date').val(); }
	function qs(extra) { return 'branch_id=' + S.branchId + '&date=' + encodeURIComponent(date()) + (extra || ''); }

	function kpi(label, value, sub, icon, tone, alert) {
		var tones = { primary: ['var(--dairy-primary-subtle)', 'var(--dairy-primary)'], success: ['var(--dairy-success-bg)', 'var(--dairy-success)'], danger: ['var(--dairy-danger-bg)', 'var(--dairy-danger)'], warning: ['var(--dairy-warning-bg)', 'var(--dairy-warning)'], info: ['var(--dairy-info-bg)', 'var(--dairy-info)'], purple: ['var(--dairy-purple-bg)', 'var(--dairy-purple)'] };
		var t = tones[tone] || tones.primary;
		return '<div class="col-xl-2 col-md-4 col-6"><div class="dairy-kpi-card dairy-kpi-uniform" style="--kpi-color: ' + t[1] + ';">' +
			'<div class="d-flex align-items-center mb-2"><div class="dairy-kpi-icon-box mr-2" style="background: ' + t[0] + ';"><i class="fa-solid ' + icon + '" style="color: ' + t[1] + ';"></i></div><div class="dairy-kpi-title mb-0" title="' + esc(label) + '">' + esc(label) + '</div></div>' +
			'<div class="dairy-kpi-value"' + (alert ? ' style="color: ' + t[1] + ';"' : '') + '>' + esc(value) + '</div><div class="dairy-kpi-footer"><span title="' + esc(sub) + '">' + esc(sub) + '</span></div></div></div>';
	}

	function load() {
		$('#fc-refresh').prop('disabled', true);
		Grocy.Api.Get('schedule/forecast?' + qs(), function (f) {
			$('#fc-refresh').prop('disabled', false);
			F = f;
			loaded = true;
			render(f);
		}, function (xhr) {
			$('#fc-refresh').prop('disabled', false);
			toastr.error(errMsg(xhr));
		});
		loadEvents();
		loadAccuracy();
	}

	function render(f) {
		var s = f.sales, st = f.staff, h = f.hours;
		$('#fc-answer').removeClass('d-none alert-info alert-warning alert-success')
			.addClass(f.bands.some(function (b) { return b.diff < 0; }) ? 'alert-warning' : 'alert-success')
			.html('<i class="fa-solid fa-robot mr-1"></i>' + esc(f.answer));
		$('#fc-basis').text(f.branch.name + ' · ' + dayNames[f.weekday] + ' ' + f.date + ' · historia hasta ' + f.history_to);
		$('#fc-kpis').html('<div class="row dairy-kpi-row">' +
			kpi('Ventas proyect.', s.projected == null ? 'Sin historia' : money(s.projected), 'promedio hist. ' + money(s.same_weekday_avg != null ? s.same_weekday_avg : s.avg_daily[30]), 'fa-cash-register', 'primary') +
			kpi('Recomendados', st.recommended, (st.extra > 0 ? '+' + st.extra + ' adicional(es)' : st.extra < 0 ? st.extra + ' sobre lo necesario' : 'sin personal adicional'), 'fa-user-plus', st.extra > 0 ? 'danger' : 'success', st.extra > 0) +
			kpi('Programados', st.scheduled, 'empleados con turno ese día', 'fa-users', 'info') +
			kpi('Horas requeridas', num(h.required) + ' h', num(h.workload) + ' h de trabajo', 'fa-hourglass-half', 'warning') +
			kpi('Horas program.', num(h.scheduled) + ' h', money(f.cost.scheduled) + (f.cost.daily_budget != null ? ' / ppto. ' + money(f.cost.daily_budget) : ''), 'fa-calendar-check', 'purple', f.cost.daily_budget != null && f.cost.scheduled > f.cost.daily_budget) +
			kpi('Cobertura', f.coverage_pct == null ? '—' : num(f.coverage_pct) + '%', 'hora a hora vs. necesarios', 'fa-people-arrows', f.coverage_pct != null && f.coverage_pct < 90 ? 'danger' : 'success', f.coverage_pct != null && f.coverage_pct < 90) +
			'</div>');

		$('#fc-bands tbody').html(f.bands.map(function (b) {
			var cls = b.diff < 0 ? 'text-danger font-weight-600' : b.diff > 0 ? 'text-muted' : 'text-success';
			var roles = roleKeys.map(function (k) { return num(b.roles[k].hours, 1); }).join(' · ');
			return '<tr><td class="text-nowrap">' + esc(b.label) + (b.open_hours === 0 ? ' <span class="small text-muted">cerrado</span>' : '') + '</td>' +
				'<td class="text-right">' + b.needed + '</td><td class="text-right">' + b.scheduled + '</td><td class="text-right ' + cls + '">' + (b.diff > 0 ? '+' : '') + b.diff + '</td>' +
				'<td class="text-right small text-muted text-nowrap">' + roles + '</td>' +
				(f.can_manage ? '<td class="text-right"><button type="button" class="btn btn-sm btn-outline-primary fc-band" data-band="' + b.index + '"' + (b.open_hours === 0 ? ' disabled' : '') + '>Abrir</button></td>' : '') + '</tr>';
		}).join(''));

		var open = f.hourly.filter(function (x) { return x.open || x.scheduled > 0 || x.needed > 0; });
		$('#fc-hourly tbody').html('<tr><td class="small text-muted">Hora</td>' + open.map(function (x) { return '<td class="text-center small text-muted">' + x.hour + '</td>'; }).join('') + '</tr>' +
			'<tr><td class="small text-muted text-nowrap">Prog./nec.</td>' + open.map(function (x) {
				return '<td class="text-center small ' + (x.needed > x.scheduled ? 'sch-gap' : x.scheduled > 0 ? 'sch-ok' : '') + '" title="' + esc(x.work_hours + ' h de trabajo') + '">' + x.scheduled + '<span class="text-muted">/' + x.needed + '</span></td>';
			}).join('') + '</tr>');

		$('#fc-tasks tbody').html(f.tasks.map(function (t) {
			return '<tr><td>' + esc(t.label) + '</td><td class="small text-muted">' + esc(t.qty) + '</td><td class="text-right">' + num(t.hours, 2) + '</td><td class="text-right text-muted">' + num(t.baseline, 2) + '</td><td class="text-right font-weight-600">' + num(t.counted, 2) + '</td></tr>';
		}).join('') + '<tr class="table-light"><td colspan="4" class="font-weight-600">Horas de ventas (' + esc(f.productivity.label) + ')</td><td class="text-right font-weight-600">' + num(h.sales, 2) + '</td></tr>');

		$('#fc-alerts').html(f.alerts.length ? f.alerts.map(function (a, i) {
			var tone = { danger: 'danger', warning: 'warning', info: 'info' }[a.level] || 'info';
			var btn = '';
			if (a.action) {
				if (a.action.type === 'band' && f.can_manage) { btn = '<button type="button" class="btn btn-sm btn-outline-' + tone + ' fc-band" data-band="' + a.action.band + '">' + esc(a.action.label) + '</button>'; }
				else if (a.action.type === 'generate' && f.can_manage) { btn = '<button type="button" class="btn btn-sm btn-outline-' + tone + ' fc-generate">' + esc(a.action.label) + '</button>'; }
				else if (a.action.type === 'link') { btn = '<a class="btn btn-sm btn-outline-' + tone + '" href="' + U('/' + (links[a.action.href] || a.action.href)) + '">' + esc(a.action.label) + '</a>'; }
			}
			return '<div class="border-left border-' + tone + ' pl-2 mb-2" style="border-left-width: 3px !important;"><div class="font-weight-600">' + esc(a.title) + '</div><div class="small text-muted">' + esc(a.cause) + '</div>' + (btn ? '<div class="mt-1">' + btn + '</div>' : '') + '</div>';
		}).join('') : '<div class="small text-muted">Sin alertas para este día.</div>');

		var fx = s.factors, p = f.productivity;
		var wnd = [7, 14, 30, 60, 90].map(function (n) { return n + ' d ' + money(s.avg_daily[n]); }).join(' · ');
		$('#fc-method').html(
			'<div><strong>Ventas:</strong> ' + (s.method === 'same_weekday_blend' ? '70 % media ponderada de los últimos 4 ' + dayNames[f.weekday] + ' + 30 % promedio de 28 días × índice del día (' + num(s.weekday_index, 2) + ')' : s.method === 'avg28' ? 'promedio de 28 días (pocos ' + dayNames[f.weekday] + ' en la historia)' : 'sin ventas históricas') + '.</div>' +
			'<div class="text-muted">Promedios diarios: ' + wnd + '</div>' +
			'<div><strong>Factores:</strong> estacionalidad ×' + fx.seasonality.factor + ' (' + esc(fx.seasonality.note) + '), eventos ×' + fx.events.factor + ', corrección aprendida ×' + fx.learned.factor + (fx.learned.applied ? ' (' + fx.learned.samples + ' días)' : ' (sin días suficientes)') + '.</div>' +
			'<div><strong>Tickets / unidades:</strong> ' + num(s.projected_tickets) + ' / ' + num(s.projected_units) + '.</div>' +
			'<div><strong>Productividad:</strong> ' + (p.value ? money(p.value) + ' por hora laboral' : 'tickets ÷ ' + p.tickets_per_staff_hour + ' por persona-hora') + ' — ' + esc(p.label) + '. ' + (p.includes_tasks ? 'Incluye las tareas habituales: solo se suma el excedente de cada tarea.' : 'No incluye tareas: se suman completas.') + '</div>' +
			'<div><strong>Eficiencia:</strong> ' + Math.round(h.effective_ratio * 100) + ' % de cada hora pagada (descansos y traslados).</div>' +
			(f.departments.length ? '<div><strong>Departamentos (horas de ventas):</strong> ' + f.departments.slice(0, 5).map(function (d) { return esc(d.department) + ' ' + num(d.sales_hours, 1) + ' h'; }).join(' · ') + '</div>' : '') +
			'<div class="text-muted mt-1"><i class="fa-solid fa-plug mr-1"></i>' + esc(f.payroll_connector.message) + '</div>');
	}

	function loadEvents() {
		Grocy.Api.Get('schedule/forecast/events?' + qs('&from=' + date()), function (rows) {
			var kinds = { holiday: 'Festivo', promotion: 'Promoción', event: 'Evento' };
			$('#fc-events tbody').html(rows.length ? rows.map(function (e) {
				return '<tr><td class="text-nowrap">' + esc(e.event_date) + '</td><td>' + esc(e.name) + '<div class="small text-muted">' + esc(kinds[e.kind] || e.kind) + (Number(e.branch_id) === 0 ? ' · toda la red' : '') + '</div></td>' +
					'<td class="text-right small">×' + num(e.sales_factor, 2) + (Number(e.extra_hours) ? '<div class="text-muted">+' + num(e.extra_hours) + ' h</div>' : '') + '</td>' +
					(S.canManage ? '<td class="text-right"><button type="button" class="btn btn-sm btn-link text-danger fc-event-del" data-id="' + e.id + '" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button></td>' : '') + '</tr>';
			}).join('') : '<tr><td class="small text-muted p-2">Sin eventos próximos. Registre festivos y promociones con su efecto esperado en ventas.</td></tr>');
		}, function () { });
	}

	function loadAccuracy() {
		Grocy.Api.Get('schedule/forecast/accuracy?branch_id=' + S.branchId + '&days=14', function (a) {
			if (!a.days.length) { $('#fc-accuracy').html('<div class="small text-muted p-2">' + esc(a.message) + '</div>'); return; }
			$('#fc-accuracy').html('<div class="small p-2">Error (WAPE) ' + num(a.wape_pct) + ' % · sesgo ' + num(a.bias_pct) + ' % · corrección ' + (a.correction.applied ? '×' + a.correction.factor : 'sin aplicar') + '</div>' +
				'<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Día</th><th class="text-right">Venta proy./real</th><th class="text-right">Horas rec./trab.</th><th class="text-right">Pers.</th><th class="text-right">Tareas pend.</th></tr></thead><tbody>' +
				a.days.map(function (d) {
					return '<tr title="' + esc(d.issues.join('\n')) + '"><td class="text-nowrap">' + esc(d.date.substr(5)) + (d.issues.length ? ' <i class="fa-solid fa-triangle-exclamation text-warning"></i>' : '') + '</td>' +
						'<td class="text-right text-nowrap">' + money(d.projected_sales) + ' / ' + money(d.actual_sales) + (d.sales_error_pct != null ? '<div class="small ' + (Math.abs(d.sales_error_pct) > 20 ? 'text-danger' : 'text-muted') + '">' + (d.sales_error_pct > 0 ? '+' : '') + d.sales_error_pct + ' %</div>' : '') + '</td>' +
						'<td class="text-right">' + num(d.recommended_hours) + ' / ' + num(d.worked_hours) + '</td><td class="text-right">' + d.recommended_staff + ' / ' + d.staff_used + '</td><td class="text-right">' + d.tasks_pending + '</td></tr>';
				}).join('') + '</tbody></table></div>');
		}, function () { });
	}

	// Franja: personal disponible, turnos que se pueden ampliar y solicitud de cobertura
	function openBand(index) {
		Grocy.Api.Get('schedule/forecast/band?' + qs('&band=' + index), function (r) {
			var b = F.bands[index];
			var row = function (c, kind) {
				var w = (c.warnings || []).map(function (x) { return x.message; }).join('\n');
				var ot = (c.warnings || []).some(function (x) { return x.code === 'overtime'; });
				var disabled = ot && !S.canOvertime;
				return '<tr><td>' + esc(c.name) + '<div class="small text-muted">' + esc(c.position) + ' · ' + num(c.week_hours) + '/' + num(c.contract_hours) + ' h sem.' + (kind === 'extend' ? ' · turno ' + esc(c.current) : '') + '</div>' +
					(w ? '<div class="small text-warning">' + esc(w) + '</div>' : '') + '</td><td class="text-nowrap small">' + esc(c.proposed.start_time + '–' + c.proposed.end_time) + '</td>' +
					'<td class="text-right"><button type="button" class="btn btn-sm ' + (disabled ? 'btn-light border' : 'btn-primary') + ' fc-assign" data-kind="' + kind + '" data-c="' + esc(JSON.stringify(c)) + '"' + (disabled ? ' disabled title="Genera horas extra: requiere permiso para aprobarlas"' : '') + '>' + (kind === 'extend' ? 'Ampliar' : 'Asignar') + '</button></td></tr>';
			};
			var html = '<p class="small mb-2">Necesarios <strong>' + b.needed + '</strong> · programados <strong>' + b.scheduled + '</strong> · ' + num(b.work_hours, 2) + ' h de trabajo.</p>' +
				'<div class="form-group"><label class="small mb-1" for="fc-band-reason">Motivo del cambio</label><input class="form-control form-control-sm" id="fc-band-reason" value="Cobertura ' + esc(b.label) + ' según pronóstico"></div>' +
				'<h6 class="mt-2">Ampliar turnos</h6>' + (r.extendable.length ? '<table class="table table-sm">' + r.extendable.map(function (c) { return row(c, 'extend'); }).join('') + '</table>' : '<div class="small text-muted mb-2">Ningún turno termina o empieza en el borde de la franja.</div>') +
				'<h6>Personal disponible</h6>' + (r.available.length ? '<table class="table table-sm">' + r.available.map(function (c) { return row(c, 'add'); }).join('') + '</table>' : '<div class="small text-muted mb-2">Nadie disponible sin conflictos.</div>') +
				'<h6>Solicitudes de cobertura abiertas</h6>' + (r.open_requests.length ? '<ul class="small pl-3">' + r.open_requests.map(function (q) { return '<li>' + esc(q.start_time + '–' + q.end_time + ' · ' + q.staff_needed + ' persona(s) · ' + q.reason) + ' <a href="#" class="fc-req-close" data-id="' + q.id + '">marcar cubierta</a></li>'; }).join('') + '</ul>' : '<div class="small text-muted mb-2">Ninguna.</div>');
			var dlg = bootbox.dialog({
				title: 'Franja ' + b.label + ' · ' + F.date, size: 'large', message: html,
				buttons: {
					req: { label: 'Solicitar cobertura', className: 'btn-outline-warning', callback: function () {
						Grocy.Api.Post('schedule/coverage-requests', { branch_id: S.branchId, request_date: F.date, start_time: r.band.from.toString().padStart(2, '0') + ':00', end_time: (r.band.to >= 24 ? '23:59' : r.band.to.toString().padStart(2, '0') + ':00'), staff_needed: Math.max(1, -b.diff), reason: $('#fc-band-reason').val() || 'Cobertura según pronóstico' },
							function () { toastr.success('Solicitud de cobertura registrada'); load(); }, function (xhr) { toastr.error(errMsg(xhr)); });
					} },
					close: { label: 'Cerrar', className: 'btn-light border' }
				}
			});
			dlg.on('click', '.fc-assign', function () {
				var c = JSON.parse($(this).attr('data-c')), kind = $(this).data('kind');
				var p = { type: kind, employee_id: c.employee_id, shift_id: c.shift_id || null, version: c.version || null, start_time: c.proposed.start_time, end_time: c.proposed.end_time, break_minutes: c.break_minutes || 0, reason: kind === 'extend' ? 'Ampliar turno' : 'Asignar personal disponible' };
				apply([p], $('#fc-band-reason').val(), this, function () { dlg.modal('hide'); });
			});
			dlg.on('click', '.fc-req-close', function (e) {
				e.preventDefault();
				Grocy.Api.Post('schedule/coverage-requests/' + $(this).data('id') + '/close', { status: 'filled' }, function () { toastr.success('Solicitud cerrada'); dlg.modal('hide'); load(); }, function (xhr) { toastr.error(errMsg(xhr)); });
			});
		}, function (xhr) { toastr.error(errMsg(xhr)); });
	}

	function apply(proposals, reason, btn, done) {
		var $b = $(btn).prop('disabled', true);
		Grocy.Api.Post('schedule/forecast/apply', { branch_id: S.branchId, date: F.date, proposals: proposals, reason: reason }, function (res) {
			$b.prop('disabled', false);
			res.applied.forEach(function (a) { (a.warnings || []).forEach(function (w) { toastr.warning(w.message); }); });
			toastr.success(res.applied.length + ' cambio(s) aplicado(s). Los turnos nuevos quedan en borrador: publique la semana para notificar.');
			done && done();
			load();
		}, function (xhr) { $b.prop('disabled', false); toastr.error(errMsg(xhr)); });
	}

	// Generar horario recomendado: simulación con antes/después y selección de propuestas
	function generate(allowOt) {
		Grocy.Api.Post('schedule/forecast/recommend', { branch_id: S.branchId, date: date(), allow_overtime: allowOt ? 1 : 0 }, function (plan) {
			var labels = { move: 'Reorganizar', extend: 'Ampliar', add: 'Agregar', request: 'Solicitar cobertura' };
			var metric = function (m) { return 'cobertura ' + (m.coverage_pct == null ? '—' : num(m.coverage_pct) + ' %') + ' · ' + m.gap_staff_hours + ' persona-h faltantes · ' + num(m.scheduled_hours) + ' h · ' + money(m.cost); };
			var html = '<p class="small">' + esc(plan.answer) + '</p>' +
				'<table class="table table-sm small mb-2"><tr><td class="text-muted">Actual</td><td>' + metric(plan.before) + '</td></tr><tr><td class="text-muted">Con la propuesta</td><td class="font-weight-600">' + metric(plan.after) + '</td></tr>' +
				(plan.daily_budget != null ? '<tr><td class="text-muted">Presupuesto del día</td><td>' + money(plan.daily_budget) + (plan.after.cost > plan.daily_budget ? ' <span class="text-danger">excedido</span>' : '') + '</td></tr>' : '') + '</table>' +
				(plan.proposals.length ? '<table class="table table-sm"><thead><tr><th></th><th>Acción</th><th>Empleado</th><th>Horario</th></tr></thead><tbody>' + plan.proposals.map(function (p, i) {
					return '<tr><td><input type="checkbox" class="fc-prop" data-i="' + i + '" checked aria-label="Incluir"></td><td>' + esc(labels[p.type]) + '<div class="small text-muted">' + esc(p.reason) + '</div></td><td>' + esc(p.employee_name || '—') + '</td>' +
						'<td class="text-nowrap">' + (p.from ? '<span class="text-muted">' + esc(p.from) + '</span> → ' : '') + esc(p.start_time + '–' + p.end_time) + (p.staff_needed ? ' · ' + p.staff_needed + ' pers.' : '') +
						((p.warnings || []).length ? '<div class="small text-warning">' + esc(p.warnings.map(function (w) { return w.message; }).join('; ')) + '</div>' : '') + '</td></tr>';
				}).join('') + '</tbody></table>' : '<div class="small text-muted">No hay cambios que mejoren la cobertura.</div>') +
				(plan.requires_overtime.length && !plan.overtime_allowed ? '<div class="small text-warning mb-2">Con horas extra también podrían cubrir: ' + esc(plan.requires_overtime.map(function (e) { return e.name; }).join(', ')) + '.' +
					(plan.can_overtime ? ' <a href="#" class="fc-ot">Simular aprobando horas extra</a>' : ' Requiere permiso para aprobar horas extra.') + '</div>' : '') +
				'<div class="form-group mb-0"><label class="small mb-1" for="fc-plan-reason">Motivo (queda en el historial de cada turno)</label><input class="form-control form-control-sm" id="fc-plan-reason" value="Horario recomendado por pronóstico de ventas ' + esc(plan.date) + '"></div>';
			var dlg = bootbox.dialog({
				title: 'Horario recomendado · ' + plan.date, size: 'large', message: html,
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light border' },
					ok: { label: 'Aplicar seleccionadas', className: 'btn-primary' + (plan.proposals.length ? '' : ' d-none'), callback: function () {
						var sel = dlg.find('.fc-prop:checked').map(function () { return plan.proposals[$(this).data('i')]; }).get();
						if (!sel.length) { toastr.warning('Seleccione al menos una propuesta'); return false; }
						apply(sel, $('#fc-plan-reason').val(), dlg.find('.btn-primary'), function () { dlg.modal('hide'); });
						return false;
					} }
				}
			});
			dlg.on('click', '.fc-ot', function (e) { e.preventDefault(); dlg.modal('hide'); generate(true); });
		}, function (xhr) { toastr.error(errMsg(xhr)); });
	}

	$(document).on('shown.bs.tab', 'a[href="#tab-forecast"]', function () { if (!loaded) { load(); } });
	$(function () { if (location.hash === '#tab-forecast') { $('a[href="#tab-forecast"]').tab('show'); } });
	$('#fc-refresh').on('click', load);
	$('#fc-date').on('change', load);
	$(document).on('click', '#fc-generate, .fc-generate', function () { if (F) { generate(false); } });
	$(document).on('click', '.fc-band', function () { if (F) { openBand(Number($(this).data('band'))); } });
	$(document).on('click', '.fc-event-del', function () {
		Grocy.Api.Post('schedule/forecast/events/' + $(this).data('id') + '/delete', {}, function () { load(); }, function (xhr) { toastr.error(errMsg(xhr)); });
	});
	$('#fc-event-add').on('click', function () {
		var dlg = bootbox.dialog({
			title: 'Festivo, promoción o evento',
			message: '<form class="dairy-form-dialog" autocomplete="off"><div class="form-row">' +
				'<div class="form-group col-6"><label>Fecha</label><input type="date" class="form-control" name="event_date" value="' + esc(date()) + '" required></div>' +
				'<div class="form-group col-6"><label>Tipo</label><select class="custom-select" name="kind"><option value="promotion">Promoción</option><option value="holiday">Festivo</option><option value="event">Evento</option></select></div></div>' +
				'<div class="form-group"><label>Nombre</label><input class="form-control" name="name" required></div><div class="form-row">' +
				'<div class="form-group col-6"><label>Efecto en ventas</label><input type="number" step="0.05" min="0.1" max="5" class="form-control" name="sales_factor" value="1.10"><small class="text-muted">1.10 = +10 %</small></div>' +
				'<div class="form-group col-6"><label>Horas operativas extra</label><input type="number" step="0.5" min="0" class="form-control" name="extra_hours" value="0"></div></div>' +
				'<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="fc-ev-all" name="all"><label class="custom-control-label" for="fc-ev-all">Toda la red (todas las sucursales)</label></div></form>',
			buttons: {
				cancel: { label: 'Cancelar', className: 'btn-light border' },
				ok: { label: 'Guardar', className: 'btn-primary', callback: function () {
					var d = {};
					dlg.find('form').serializeArray().forEach(function (x) { d[x.name] = x.value; });
					d.branch_id = d.all ? 0 : S.branchId;
					Grocy.Api.Post('schedule/forecast/events', d, function () { toastr.success('Evento guardado'); dlg.modal('hide'); load(); }, function (xhr) { toastr.error(errMsg(xhr)); });
					return false;
				} }
			}
		});
	});

	// ---------------- Simulador de escenarios (Etapa 31): solo lectura, se puede guardar y comparar
	var simParams = null;
	var riskTone = { bajo: 'success', medio: 'warning', alto: 'danger', sin_datos: 'secondary' };
	var simRows = [
		['projected_sales', 'Ventas proyectadas', money], ['workload_hours', 'Carga de trabajo (h)', num], ['required_hours', 'Horas-persona requeridas', num],
		['scheduled_hours', 'Horas programadas', num], ['recommended_staff', 'Personal recomendado', num], ['scheduled_staff', 'Personal programado', num],
		['coverage_pct', 'Cobertura %', num], ['gap_staff_hours', 'Persona-h sin cubrir', num], ['scheduled_cost', 'Costo programado', money], ['recommended_cost', 'Costo recomendado (est.)', money]
	];
	var paramLabels = { sales_pct: function (v) { return 'Ventas ' + (v > 0 ? '+' : '') + v + ' %'; }, productivity_pct: function (v) { return 'Productividad ' + (v > 0 ? '+' : '') + v + ' %'; },
		extra_pallets: function (v) { return '+' + v + ' pallets'; }, absent_staff: function (v) { return v + ' ausente(s)'; }, cut_hours: function (v) { return '−' + v + ' h'; }, advance_restock: function () { return 'Reposición adelantada'; } };
	function paramText(p) { return Object.keys(p || {}).map(function (k) { return paramLabels[k] ? paramLabels[k](p[k]) : k; }).join(' · '); }
	function riskBadge(r) { return '<span class="badge badge-' + (riskTone[r] || 'secondary') + '">Riesgo ' + esc(r === 'sin_datos' ? 'sin datos' : r) + '</span>'; }
	function readSim() {
		var p = {};
		$('#fc-sim-form').serializeArray().forEach(function (x) { if (x.value !== '') { p[x.name] = x.name === 'advance_restock' ? true : Number(x.value); } });
		return p;
	}
	function hourStrip(s) {
		return '<div class="table-responsive"><table class="table table-sm mb-0 sch-heat"><tbody><tr><td class="small text-muted">Hora</td>' + s.hourly.map(function (x) { return '<td class="text-center small text-muted">' + x.hour + '</td>'; }).join('') + '</tr>' +
			'<tr><td class="small text-muted text-nowrap">Prog./nec.</td>' + s.hourly.map(function (x) { var g = x.scheduled - x.needed; return '<td class="text-center small ' + (g < 0 ? 'table-danger' : '') + '">' + x.scheduled + '/' + x.needed + '</td>'; }).join('') + '</tr></tbody></table></div>';
	}
	$('#fc-sim-form').on('submit', function (e) {
		e.preventDefault();
		var p = readSim();
		if (!Object.keys(p).length) { toastr.warning('Indique al menos un cambio'); return; }
		var $b = $(this).find('[type=submit]').prop('disabled', true);
		Grocy.Api.Post('schedule/scenarios/simulate', { branch_id: S.branchId, date: date(), params: p }, function (r) {
			$b.prop('disabled', false);
			simParams = r.params;
			$('#fc-sim-save').prop('disabled', false);
			var ch = (r.shift_changes || []).map(function (c) {
				return c.type === 'absent' ? 'Sin turno: ' + esc(c.employee_name) + ' (' + num(c.hours) + ' h)' : c.type === 'absent_unmatched' ? esc(c.count) + ' ausencia(s) sin turno que quitar' : 'Recorte aplicado: ' + c.hours + ' h' + (c.unmatched ? ' (' + c.unmatched + ' h no recortables)' : '');
			});
			$('#fc-sim-result').html('<div class="d-flex flex-wrap align-items-center dairy-gap-2 mb-2"><strong>' + esc(paramText(r.params)) + '</strong>' + riskBadge(r.base.risk) + '<i class="fa-solid fa-arrow-right text-muted"></i>' + riskBadge(r.scenario.risk) + '</div>' +
				(r.has_sales_history ? '' : '<div class="alert alert-warning py-1 small">Sin historia de ventas: el escenario solo refleja mínimos y tareas.</div>') +
				'<div class="table-responsive"><table class="table table-sm mb-2"><thead><tr><th>Indicador</th><th class="text-right">Actual</th><th class="text-right">Escenario</th><th class="text-right">Δ</th></tr></thead><tbody>' +
				simRows.map(function (row) {
					var d = r.delta[row[0]];
					return '<tr><td>' + row[1] + '</td><td class="text-right">' + row[2](r.base[row[0]]) + '</td><td class="text-right font-weight-700">' + row[2](r.scenario[row[0]]) + '</td><td class="text-right ' + (d > 0 ? 'text-body' : 'text-muted') + '">' + (d == null ? '—' : (d > 0 ? '+' : '') + row[2](d)) + '</td></tr>';
				}).join('') + '</tbody></table></div>' +
				(r.scenario.short_hours.length ? '<div class="small mb-1">Horas sin cobertura en el escenario: <strong>' + r.scenario.short_hours.map(function (h) { return h + ':00'; }).join(', ') + '</strong></div>' : '<div class="small text-muted mb-1">El escenario queda cubierto en todas las horas.</div>') +
				hourStrip(r.scenario) + (ch.length ? '<ul class="small text-muted pl-3 mt-2 mb-0"><li>' + ch.join('</li><li>') + '</li></ul>' : '') +
				'<div class="small text-muted mt-2">' + esc(r.note) + '</div>');
		}, function (xhr) { $b.prop('disabled', false); toastr.error(errMsg(xhr)); });
	});
	$('#fc-sim-form').on('input change', function () { simParams = null; $('#fc-sim-save').prop('disabled', true); });
	$('#fc-sim-save').on('click', function () {
		if (!simParams) { return; }
		bootbox.prompt({ title: 'Nombre del escenario', value: paramText(simParams), callback: function (name) {
			if (name === null) { return; }
			Grocy.Api.Post('schedule/scenarios', { branch_id: S.branchId, date: date(), name: name, params: simParams }, function () { toastr.success('Escenario guardado'); loadScenarios(); }, function (xhr) { toastr.error(errMsg(xhr)); });
		} });
	});
	function loadScenarios() {
		if (!$('#fc-sim').length) { return; }
		Grocy.Api.Get('schedule/scenarios?branch_id=' + S.branchId, function (rows) {
			if (!rows.length) { $('#fc-sim-saved').html('<div class="small text-muted">Sin escenarios guardados.</div>'); return; }
			$('#fc-sim-saved').html('<h6 class="mb-2">Escenarios guardados · comparar</h6><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Indicador</th>' +
				rows.map(function (s) { return '<th class="text-right"><div>' + esc(s.name) + '</div><div class="small text-muted font-weight-normal">' + esc(s.date) + ' · ' + esc(paramText(s.params)) + '</div>' +
					'<a href="#" class="small text-danger fc-sim-del" data-id="' + s.id + '">Eliminar</a></th>'; }).join('') + '</tr></thead><tbody>' +
				'<tr><td>Riesgo</td>' + rows.map(function (s) { return '<td class="text-right">' + riskBadge(s.result.scenario.risk) + '</td>'; }).join('') + '</tr>' +
				simRows.map(function (row) { return '<tr><td>' + row[1] + '</td>' + rows.map(function (s) { var d = s.result.delta[row[0]]; return '<td class="text-right">' + row[2](s.result.scenario[row[0]]) + (d ? ' <span class="small text-muted">(' + (d > 0 ? '+' : '') + row[2](d) + ')</span>' : '') + '</td>'; }).join('') + '</tr>'; }).join('') +
				'</tbody></table></div><div class="small text-muted mt-1">Resultados calculados al guardar; vuelva a simular para recalcular con datos actuales.</div>');
		}, function () { });
	}
	$(document).on('click', '.fc-sim-del', function (e) {
		e.preventDefault();
		Grocy.Api.Post('schedule/scenarios/' + $(this).data('id') + '/delete', {}, function () { loadScenarios(); }, function (xhr) { toastr.error(errMsg(xhr)); });
	});
	$(document).on('shown.bs.tab', 'a[href="#tab-forecast"]', loadScenarios);
	$(function () { if (location.hash === '#tab-forecast') { loadScenarios(); } });
})(jQuery);
