// Programación automática dentro de 3. Vista mensual. Solo presenta y decide: las reglas están en el servidor.
(function () {
	var MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
	var DOW = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
	var SEVERITY = { legal: ['Bloqueante', 'danger'], coverage: ['Obligatoria', 'warning'], warn: ['Optimizable', 'secondary'] };
	var CONTROL_VIEWS = ['year', 'planta', 'sundays', 'comps', 'nights', 'absences', 'requests'];
	var REQUESTS = { VACACIONES: 'Vacaciones', PERMISO: 'Permiso', CAMBIO_TURNO: 'Cambio de turno', COMPENSATORIO: 'Compensatorio', RESTRICCION: 'Restricción', INTERCAMBIO: 'Intercambio de turnos', INCAPACIDAD: 'Incapacidad' };
	var data = null, control = null, view = 'calendar', weekIndex = 0, focusEmployee = null, hourlyDate = null, loaded = false;

	function esc(v) { return $('<div>').text(v === null || v === undefined ? '' : v).html(); }
	function branch() { return (window.DairyScheduleData && window.DairyScheduleData.branchId) || 1; }
	function year() { return parseInt($('#selMonthlyYear').val(), 10); }
	function month() { return parseInt($('#selMonthlyMonth').val(), 10); }
	function errorOf(xhr) { return (xhr.responseJSON && (xhr.responseJSON.error_message || xhr.responseJSON.error)) || 'No se pudo completar la acción'; }
	function post(url, body) {
		return $.ajax({ url: U('/api/schedule/engine/' + url), type: 'POST', contentType: 'application/json', data: JSON.stringify(body || {}) }).fail(function (xhr) { toastr.error(errorOf(xhr)); });
	}
	function dayOf(d) { return new Date(d + 'T12:00:00'); }
	function dates() {
		var out = [], d = dayOf(data.start), end = dayOf(data.end);
		for (; d <= end; d.setDate(d.getDate() + 1)) {
			out.push(d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2));
		}
		return out;
	}
	function chipOf(c) {
		var t = c ? c.type : 'LIBRE';
		return 'sch-chip-' + ({ APERTURA: 'apertura', CIERRE: 'cierre', NOCHE: 'noche', COMPENSATORIO: 'comp', VACACIONES: 'vac', INCAPACIDAD: 'inc', PERMISO: 'inc', INTERMEDIO: 'intermedio' }[t] || 'libre');
	}
	function people() {
		var dept = $('#genDept').val();
		return data.employees.filter(function (e) { return !dept || e.department === dept; });
	}
	function isOff(c) { return ['LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'].indexOf(c.type) >= 0; }
	function chip(e, d) {
		var c = (data.grid[e.payroll_code] || {})[d];
		return '<span class="sch-chip ' + chipOf(c) + ' gen-cell" data-code="' + esc(e.payroll_code) + '" data-date="' + d + '" title="' + esc(c ? (c.notes || c.start + '–' + c.end) : '') + '">' + esc(c ? c.code : '—') + '</span>';
	}

	function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }

	// Semana de lunes a domingo; los días de otro mes se muestran vacíos para que todas las semanas tengan las mismas columnas
	function weekTable(days) {
		var h = '<table class="sch-matrix-table" style="min-width: 740px; table-layout: fixed;"><thead><tr><th class="th-sticky-emp" style="width: 160px; min-width: 160px;">Colaborador</th>';
		days.forEach(function (d) {
			var w = dayOf(d).getDay(), hol = data.holidays[d];
			h += '<th class="' + (w === 0 ? 'is-sunday ' : '') + (hol ? 'is-holiday' : '') + '" title="' + esc(hol || '') + '">' + DOW[w] + '<br>' + d.slice(8) + '</th>';
		});
		h += '<th style="width: 48px;" title="Horas de esta semana dentro del mes">TH</th></tr></thead><tbody>';
		people().forEach(function (e) {
			var row = data.grid[e.payroll_code] || {}, hours = 0;
			h += '<tr><td class="td-sticky-emp" style="min-width: 160px;"><div class="font-weight-bold text-dark" style="font-size: 0.74rem; white-space: normal;">' + esc(e.name) + '</div><div class="text-muted" style="font-size: 0.65rem;"><code>' + esc(e.payroll_code) + '</code> · G-' + esc(e.group) + '</div></td>';
			days.forEach(function (d) {
				var c = row[d], inMonth = d >= data.start && d <= data.end;
				if (c && !isOff(c)) hours += c.hours;
				h += '<td class="px-0 py-1' + (dayOf(d).getDay() === 0 ? ' is-sunday' : '') + (data.holidays[d] ? ' is-holiday' : '') + '">' + (inMonth ? chip(e, d) : '<span class="text-muted" title="Otro mes">—</span>') + '</td>';
			});
			h += '<td class="font-weight-bold text-center px-0">' + Math.round(hours * 10) / 10 + 'h</td></tr>';
		});
		return h + '</tbody></table>';
	}

	function renderWeek() {
		var weeks = [], first = dayOf(data.start);
		first.setDate(first.getDate() - (first.getDay() + 6) % 7);
		while (iso(first) <= data.end) {
			var week = [];
			for (var i = 0; i < 7; i++) {
				var d = new Date(first);
				d.setDate(first.getDate() + i);
				week.push(iso(d));
			}
			weeks.push(week);
			first.setDate(first.getDate() + 7);
		}
		weekIndex = Math.min(weekIndex, weeks.length - 1);
		var days = weeks[weekIndex], label = function (d) { return parseInt(d.slice(8), 10) + ' ' + MONTHS[parseInt(d.slice(5, 7), 10) - 1].toLowerCase(); };
		return '<div class="d-flex align-items-center mb-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="genPrevWeek" aria-label="Semana anterior"' + (weekIndex === 0 ? ' disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button><strong class="mx-2">Semana ' + (weekIndex + 1) + ' de ' + weeks.length + '</strong><span class="text-muted small mr-2">' + label(days[0]) + ' – ' + label(days[6]) + '</span><button type="button" class="btn btn-sm btn-outline-secondary" id="genNextWeek" aria-label="Semana siguiente"' + (weekIndex === weeks.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button></div>' + weekTable(days);
	}

	function renderEmployee() {
		var list = people();
		if (!list.length) return '<p class="text-muted mb-0">Sin colaboradores.</p>';
		if (!list.some(function (e) { return e.payroll_code === focusEmployee; })) focusEmployee = list[0].payroll_code;
		var e = list.filter(function (x) { return x.payroll_code === focusEmployee; })[0], s = e.history;
		var h = '<select class="custom-select custom-select-sm mb-3" id="genEmployee" style="max-width: 340px;" aria-label="Empleado">';
		list.forEach(function (x) { h += '<option value="' + esc(x.payroll_code) + '"' + (x.payroll_code === focusEmployee ? ' selected' : '') + '>' + esc(x.name) + '</option>'; });
		h += '</select><div class="row small mb-3">';
		[['Último domingo libre', s.last_free_sunday], ['Próximo domingo libre', e.next_free_sunday], ['Domingos trabajados recientes', (s.last_worked_sundays || []).join(', ')], ['Última apertura', s.last_opening], ['Último cierre', s.last_closing],
			['Última noche', s.last_night], ['Compensatorios pendientes', (s.pending_comps || []).length], ['Aperturas / cierres / noches (historia)', s.openings + ' / ' + s.closings + ' / ' + s.nights], ['Días de ausencia (historia)', s.absent_days], ['Horas del mes', e.hours]].forEach(function (p) {
			h += '<div class="col-md-3 col-6 mb-2"><div class="text-muted">' + esc(p[0]) + '</div><strong>' + esc(p[1] === null || p[1] === '' ? '—' : p[1]) + '</strong></div>';
		});
		h += '</div><div class="d-flex flex-wrap" style="gap: 4px;">';
		dates().forEach(function (d) {
			h += '<div class="border rounded text-center small p-1" style="width: 100px;"><div class="text-muted">' + DOW[dayOf(d).getDay()] + ' ' + d.slice(8) + '</div>' + chip(e, d) + '</div>';
		});
		return h + '</div>';
	}

	function renderHourly() {
		var all = dates();
		if (all.indexOf(hourlyDate) < 0) hourlyDate = all[0];
		var counts = {}, h;
		for (h = 0; h < 24; h++) counts[h] = 0;
		people().forEach(function (e) {
			var c = (data.grid[e.payroll_code] || {})[hourlyDate];
			if (!c || isOff(c) || !c.start) return;
			var s = parseInt(c.start, 10), end = parseInt(c.end, 10) + (c.end.slice(3) === '00' ? 0 : 1);
			if (c.end < c.start) end = 24;
			for (h = s; h < end; h++) counts[h]++;
		});
		var max = Math.max.apply(null, Object.keys(counts).map(function (k) { return counts[k]; }).concat([1]));
		var out = '<select class="custom-select custom-select-sm mb-3" id="genHourlyDate" style="max-width: 200px;" aria-label="Fecha">';
		all.forEach(function (d) { out += '<option value="' + d + '"' + (d === hourlyDate ? ' selected' : '') + '>' + DOW[dayOf(d).getDay()] + ' ' + d + '</option>'; });
		out += '</select><table class="table table-sm mb-0" style="max-width: 560px;"><tbody>';
		for (h = 5; h < 24; h++) {
			out += '<tr><td style="width: 70px;">' + ('0' + h).slice(-2) + ':00</td><td><span class="d-inline-block bg-success align-middle" style="height: 14px; width: ' + Math.round(counts[h] / max * 380) + 'px;"></span> <strong>' + counts[h] + '</strong></td></tr>';
		}
		return out + '</tbody></table>';
	}

	function renderAlerts() {
		var h = '<div class="row mb-3">';
		data.report.checks.forEach(function (c) {
			h += '<div class="col-md-4 small mb-1"><i class="fa-solid ' + (c.bad ? 'fa-triangle-exclamation text-warning' : 'fa-circle-check text-success') + ' mr-1"></i>' + esc(c.label) + (c.bad ? ': <strong>' + c.bad + '</strong>' : '') + '</div>';
		});
		h += '</div><table class="table table-sm small mb-0"><thead><tr><th>Tipo</th><th>Fecha</th><th>Persona</th><th>Regla y detalle</th><th>Causa</th><th>Alternativas</th><th>Gerencia</th></tr></thead><tbody>';
		var dept = $('#genDept').val();
		data.report.issues.filter(function (i) { return !dept || i.department === dept || (i.detail || '').indexOf(dept) === 0; }).forEach(function (i) {
			var s = SEVERITY[i.severity];
			h += '<tr><td><span class="badge badge-' + s[1] + '">' + s[0] + '</span></td><td>' + esc(i.date) + '</td><td>' + esc(i.name) + '</td><td>' + esc(i.detail) + '</td><td>' + esc(i.cause) + '</td><td>' + esc((i.alternatives || []).join(' · ')) + '</td><td>' + (i.needs_management ? 'Requiere' : '—') + '</td></tr>';
		});
		return h + '</tbody></table>';
	}

	function inDept(rows) {
		var dept = $('#genDept').val();
		return rows.filter(function (r) { return !dept || r.department === dept; });
	}

	function table(head, body) {
		return '<table class="table table-sm small mb-0"><thead><tr>' + head.map(function (t) { return '<th>' + esc(t) + '</th>'; }).join('') + '</tr></thead><tbody>' + (body || '<tr><td colspan="' + head.length + '" class="text-muted">Sin registros.</td></tr>') + '</tbody></table>';
	}

	function cells(values) {
		return '<tr>' + values.map(function (v) { return '<td>' + (v === null || v === undefined || v === '' ? '—' : v) + '</td>'; }).join('') + '</tr>';
	}

	function renderYear() {
		var head = ['Colaborador'].concat(MONTHS.map(function (m) { return m.slice(0, 3); }));
		return '<p class="small text-muted">Horas · domingos trabajados/libres · compensatorios · noches. Los meses sin programar muestran la proyección de domingos libres, que cambia si hay excepciones.</p>' + table(head, inDept(control.year).map(function (e) {
			return cells([esc(e.name)].concat(Object.keys(e.months).map(function (k) {
				var m = e.months[k];
				if (!m) return '';
				if (m.hours === undefined) return '<span class="text-muted" title="Proyección">Libre ' + m.projected.map(function (d) { return d.slice(8); }).join(', ') + '</span>';
				return Math.round(m.hours) + ' h · ' + m.sundays + '/' + m.free + ' · ' + m.comps + ' C · ' + m.nights + ' N' + (m.absent ? ' · ' + m.absent + ' aus.' : '');
			})));
		}).join(''));
	}

	function renderPlanta() {
		var rows = inDept(control.planta).map(function (p) {
			var limits = p.restrictions.map(function (r) { return '<span class="badge badge-warning mr-1" title="' + esc(r.reason) + '">' + esc(r.restriction_type + (r.night_mode ? ' ' + r.night_mode : '')) + ' hasta ' + esc(r.end_date) + '</span><a href="#" class="gen-del-restriction small" data-id="' + r.id + '">quitar</a>'; }).join(' ');
			return cells(['<strong>' + esc(p.name) + '</strong><br><code>' + esc(p.payroll_code) + '</code>' + (p.temporary ? ' <span class="badge badge-secondary">temporal</span>' : ''), esc(p.department) + '<br><span class="text-muted">' + esc(p.cargo) + '</span>', esc(p.contract_type) || '<span class="text-danger">Pendiente</span>', p.weekly_hours + ' h', esc(p.status), p.availability_days === null ? '<span class="text-danger">Pendiente</span>' : p.availability_days + ' días', esc(p.allowed_shifts.join(', ')) || 'Todos', limits, p.vacation_days + ' / ' + p.sick_days, p.sunday_streak, p.next_free_sunday ? shortDate(p.next_free_sunday) : '', p.pending_comps, p.nights_total, p.openings + ' / ' + p.closings,
				esc(p.missing.join(', ')), '<button type="button" class="btn btn-sm btn-outline-secondary gen-config" data-code="' + esc(p.payroll_code) + '">Configurar</button>']);
		}).join('');
		return restrictionForm() + table(['Colaborador', 'Área y cargo', 'Contrato', 'Horas', 'Estado', 'Disponibilidad', 'Turnos permitidos', 'Restricciones', 'Vac./Inc. mes', 'Domingos seguidos', 'Próximo libre', 'Comp. pendientes', 'Noches', 'Apert./Cierres', 'Por configurar', ''], rows);
	}

	function personOptions() {
		return control.planta.map(function (p) { return '<option value="' + esc(p.payroll_code) + '">' + esc(p.name) + '</option>'; }).join('');
	}

	function restrictionForm() {
		return '<details class="mb-3"><summary class="font-weight-600 small">Nueva restricción</summary><div class="form-row mt-2 small" id="genRestriction"><div class="col-md-3 mb-2"><select class="custom-select custom-select-sm" name="payroll_code" aria-label="Colaborador">' + personOptions() + '</select></div><div class="col-md-2 mb-2"><select class="custom-select custom-select-sm" name="restriction_type" aria-label="Tipo"><option>PARCIAL</option><option>NOCTURNO</option><option>TOTAL</option></select></div><div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="start_date" aria-label="Inicio"></div><div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="end_date" aria-label="Fin"></div><div class="col-md-3 mb-2"><input class="form-control form-control-sm" name="reason" placeholder="Motivo"></div>' +
			'<div class="col-md-2 mb-2"><input class="form-control form-control-sm" name="weekdays" placeholder="Días 1-7: 1,2,3" title="Parcial"></div><div class="col-md-2 mb-2"><input type="time" class="form-control form-control-sm" name="window_start" title="Parcial: inicio más temprano"></div><div class="col-md-2 mb-2"><input type="time" class="form-control form-control-sm" name="window_end" title="Parcial: salida más tardía"></div><div class="col-md-2 mb-2"><input type="number" class="form-control form-control-sm" name="max_weekly_hours" placeholder="Horas máx." title="Parcial"></div><div class="col-md-2 mb-2"><select class="custom-select custom-select-sm" name="night_mode" aria-label="Nocturno"><option value="">Modo nocturno</option><option value="DENY">Prohíbe</option><option value="ALLOW">Habilita</option></select></div><div class="col-md-2 mb-2"><input class="form-control form-control-sm" name="approved_by" placeholder="Aprobó"></div><div class="col-md-10 mb-2"><input class="form-control form-control-sm" name="notes" placeholder="Observaciones"></div><div class="col-md-2 mb-2"><button type="button" class="btn btn-sm btn-success btn-block" id="genSaveRestriction">Guardar</button></div></div></details>';
	}

	function renderSundays() {
		var h = '';
		var exceptions = [];
		control.sundays.forEach(function (s) { s.exceptions.forEach(function (e) { exceptions.push(esc(s.name) + ': ' + shortDate(e.original_sunday) + ' → ' + (e.new_sunday ? shortDate(e.new_sunday) : 'sin alternativa') + ' (' + esc(e.reason) + ')'); }); });
		if (exceptions.length) h += '<div class="alert alert-warning small"><strong>Excepciones aprobadas:</strong> ' + exceptions.join('; ') + '</div>';
		var dates = [];
		control.sundays.forEach(function (s) { s.ledger.forEach(function (l) { if (dates.indexOf(l.date) < 0) dates.push(l.date); }); });
		dates.sort();
		var label = { TRABAJADO: 'T', LIBRE: 'L', VACACIONES: 'VC', INCAPACIDAD: 'INC', PERMISO: 'P', COMPENSATORIO: 'C' };
		return h + table(['Colaborador'].concat(dates.map(shortDate)).concat(['Seguidos', 'Próximo libre']), inDept(control.sundays).map(function (s) {
			var by = {};
			s.ledger.forEach(function (l) { by[l.date] = l; });
			return cells([esc(s.name)].concat(dates.map(function (d) { var l = by[d]; return l ? '<span title="' + esc(l.notes || '') + '">' + (label[l.status] || l.status) + (l.holiday ? '*' : '') + '</span>' : ''; })).concat([s.streak, s.next_free ? shortDate(s.next_free) : '']));
		}).join(''));
	}

	function renderComps() {
		var tone = { PENDIENTE: 'warning', PROGRAMADO: 'primary', DISFRUTADO: 'success', REPROGRAMADO: 'info' }, rows = '';
		inDept(control.compensatories).forEach(function (c) {
			c.items.forEach(function (i) {
				rows += cells([esc(c.name), esc(c.department), i.origin_sunday ? shortDate(i.origin_sunday) : 'Sin origen en planilla', i.date ? shortDate(i.date) : '', '<span class="badge badge-' + tone[i.status] + '">' + i.status + '</span>' + (i.overdue ? ' <span class="badge badge-danger">Vencido</span>' : ''), i.due_date ? shortDate(i.due_date) : '', i.previous_date ? shortDate(i.previous_date) : '', esc(i.entitlement)]);
			});
		});
		return table(['Colaborador', 'Área', 'Domingo', 'Fecha', 'Estado', 'Límite', 'Fecha anterior', 'Domingo ocasional/habitual'], rows);
	}

	function renderNights() {
		var r = control.rates;
		return '<p class="small text-muted">Noche legal desde las ' + r.night_from + ':00 hasta las 06:00, recargo ' + r.night_pct + ' %; dominical y festivo ' + r.sunday_pct + ' %. Capacidad = horas contratadas del mes: no hay un tope legal mensual fijo de noches. El almuerzo se descuenta en proporción porque la planilla no dice a qué hora se toma.</p>' + table(['Colaborador', 'Área', 'Autorizado', 'Noches mes', 'Máx. por semana', 'Noches historia', 'Horas noche efectivas', 'Horas nocturnas legales (noche)', 'En domingo o festivo', 'Nocturnas legales de todos sus turnos', 'Horas trabajadas', 'Capacidad', 'Horas de recargo'], inDept(control.nights).map(function (n) {
			return cells([esc(n.name), esc(n.department), n.authorized ? 'Sí' : 'No', n.nights, n.max_week, n.total_history, n.effective, n.night_hours, n.rest_hours, n.legal_night_all, n.worked_hours, n.capacity_hours, n.premium_hours]);
		}).join(''));
	}

	function renderAbsences() {
		return table(['Colaborador', 'Área', 'Tipo', 'Desde', 'Hasta', 'Días en el mes'], inDept(control.absences).map(function (a) { return cells([esc(a.name), esc(a.department), esc(a.type), shortDate(a.from), shortDate(a.to), a.days]); }).join(''));
	}

	function renderRequests() {
		var form = '<div class="form-row small mb-3" id="genRequest"><div class="col-md-2 mb-2"><select class="custom-select custom-select-sm" name="request_type" aria-label="Tipo">' + Object.keys(REQUESTS).map(function (k) { return '<option value="' + k + '">' + REQUESTS[k] + '</option>'; }).join('') + '</select></div><div class="col-md-3 mb-2"><select class="custom-select custom-select-sm" name="payroll_code" aria-label="Colaborador">' + personOptions() + '</select></div><div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="start_date" aria-label="Desde"></div><div class="col-md-2 mb-2"><input type="date" class="form-control form-control-sm" name="end_date" aria-label="Hasta"></div><div class="col-md-3 mb-2"><input class="form-control form-control-sm" name="reason" placeholder="Motivo"></div>' +
			'<div class="col-md-3 mb-2"><input class="form-control form-control-sm" name="shift_code" placeholder="Cambio: código de turno"></div><div class="col-md-3 mb-2"><select class="custom-select custom-select-sm" name="with_payroll_code" aria-label="Intercambio con"><option value="">Intercambio con…</option>' + personOptions() + '</select></div><div class="col-md-3 mb-2"><input type="date" class="form-control form-control-sm" name="origin_sunday" title="Compensatorio: domingo de origen"></div><div class="col-md-3 mb-2"><button type="button" class="btn btn-sm btn-success btn-block" id="genSaveRequest">Registrar solicitud</button></div></div>';
		return form + table(['#', 'Tipo', 'Colaborador', 'Fechas', 'Motivo', 'Estado', 'Resultado', ''], inDept(control.requests).map(function (r) {
			var result = r.result ? (r.result.conflict || (r.result.coverage_count ? r.result.coverage_count + ' falta(s) de cobertura' : 'Cobertura sin novedad')) : '';
			var action = r.status === 'pendiente' ? '<button type="button" class="btn btn-sm btn-success gen-decide" data-id="' + r.id + '" data-approve="1">Aprobar</button> <button type="button" class="btn btn-sm btn-outline-secondary gen-decide" data-id="' + r.id + '" data-approve="0">Rechazar</button>' : (r.status === 'aprobada' ? '<button type="button" class="btn btn-sm btn-outline-success gen-apply" data-id="' + r.id + '">Aplicar</button>' : '');
			return cells([r.id, esc(REQUESTS[r.request_type] || r.request_type), esc(r.name), shortDate(r.start_date) + (r.end_date !== r.start_date ? ' – ' + shortDate(r.end_date) : ''), esc(r.reason), esc(r.status), esc(result), action]);
		}).join(''));
	}

	function renderControl() {
		if (!control) {
			loadControl();
			return '<p class="text-muted mb-0">Cargando…</p>';
		}
		return view === 'year' ? renderYear() : view === 'planta' ? renderPlanta() : view === 'sundays' ? renderSundays() : view === 'comps' ? renderComps() : view === 'nights' ? renderNights() : view === 'absences' ? renderAbsences() : renderRequests();
	}

	function loadControl() {
		$.getJSON(U('/api/schedule/engine/control'), { branch_id: branch(), year: year(), month: month() }).done(function (res) {
			control = res;
			$('#genRequestCount').text(res.requests.filter(function (r) { return r.status === 'pendiente'; }).length || '');
			if (CONTROL_VIEWS.indexOf(view) >= 0) renderBody();
		}).fail(function (xhr) { toastr.error(errorOf(xhr)); });
	}

	function field(box, name) { return $(box + ' [name="' + name + '"]').val(); }

	function renderEquity() {
		var h = '<table class="table table-sm small mb-0"><thead><tr><th>Colaborador</th><th>Área</th><th>Grupo</th><th>Aperturas</th><th>Cierres</th><th>Noches</th><th>Domingos trabajados</th><th>Domingos libres</th><th>Próximo libre</th><th>Horas</th><th>Equidad</th></tr></thead><tbody>';
		people().forEach(function (e) {
			h += '<tr><td>' + esc(e.name) + '</td><td>' + esc(e.department) + '</td><td>' + esc(e.group) + '</td><td>' + e.openings + '</td><td>' + e.closings + '</td><td>' + e.night + '</td><td>' + e.sundays_worked + '</td><td>' + e.sundays_free + '</td><td>' + esc(e.next_free_sunday || '—') + '</td><td>' + e.hours + '</td><td>' + (e.equity_pct === null ? '—' : e.equity_pct + ' %') + '</td></tr>';
		});
		return h + '</tbody></table>';
	}

	function shortDate(d) {
		return parseInt(d.slice(8), 10) + ' ' + MONTHS[parseInt(d.slice(5, 7), 10) - 1].toLowerCase().slice(0, 3);
	}

	function confirmCard(icon, tone, title, help, body) {
		return '<div class="col-lg-6 mb-3"><div class="border rounded h-100 p-3"><div class="d-flex align-items-center mb-1"><i class="fa-solid ' + icon + ' text-' + tone + ' mr-2"></i><strong>' + esc(title) + '</strong></div><p class="small text-muted mb-2">' + esc(help) + '</p>' + body + '</div></div>';
	}

	function personLine(name, sub, action) {
		return '<div class="d-flex align-items-center justify-content-between flex-wrap border-top py-2" style="gap: 0.5rem;"><div><div class="font-weight-bold text-dark small">' + esc(name) + '</div><div class="text-muted" style="font-size: 0.72rem;">' + esc(sub) + '</div></div><div class="d-flex align-items-center flex-wrap" style="gap: 0.35rem;">' + action + '</div></div>';
	}

	// Sección "Por confirmar": cada punto muestra su contexto y se resuelve aquí mismo cuando depende de un dato
	function renderConfirm() {
		var conf = data.confirmations, byType = function (t) { return conf.filter(function (c) { return c.type === t; }); };
		if (!conf.length) return '<div class="text-center py-4"><i class="fa-solid fa-circle-check text-success h3"></i><p class="font-weight-bold mb-0">No hay puntos por confirmar en ' + MONTHS[month() - 1] + '.</p></div>';
		var kinds = { vacation: 'Vacaciones', sick: 'Incapacidad', permission: 'Permiso' }, h = '<p class="small text-muted mb-3">' + conf.length + ' punto(s) por revisar. Al aprobar el mes se deja constancia de que los aceptaste.</p><div class="row">';
		var open = byType('open_absence'), asked = byType('requested_absence'), diff = byType('sunday_sheet_diff')[0], temp = byType('temporary_codes')[0], prev = byType('previous_month')[0];
		byType('legal_review').forEach(function (c) { h += confirmCard('fa-scale-balanced', 'danger', 'Concepto jurídico pendiente', c.detail, ''); });
		byType('pending_config').forEach(function (c) { h += confirmCard('fa-user-gear', 'warning', 'Datos de planta pendientes', c.detail + ' Se completan en la vista Planta.', ''); });
		if (open.length) {
			h += confirmCard('fa-notes-medical', 'danger', 'Ausencias sin fecha de regreso', 'No reciben turnos hasta registrar la fecha. Después vuelve a generar el mes.', open.map(function (c) {
				return personLine(c.name, c.department + ' · incapacidad desde ' + shortDate(c.start_date), '<input type="date" class="form-control form-control-sm gen-return-date" style="width: 150px;" min="' + c.start_date + '" aria-label="Fecha de regreso de ' + esc(c.name) + '"><button type="button" class="btn btn-sm btn-success gen-return" data-id="' + c.absence_id + '">Registrar regreso</button>');
			}).join(''));
		}
		if (asked.length) {
			h += confirmCard('fa-calendar-xmark', 'warning', 'Solicitudes de ausencia sin decidir', 'La programación las ignora mientras no se decidan. Si apruebas una, vuelve a generar el mes.', asked.map(function (c) {
				var range = c.start_date === c.end_date ? shortDate(c.start_date) : shortDate(c.start_date) + ' – ' + shortDate(c.end_date);
				return personLine(c.name, c.department + ' · ' + (kinds[c.absence_type] || c.absence_type) + ' · ' + range, c.status === 'requested' ? '<button type="button" class="btn btn-sm btn-success gen-absence" data-id="' + c.absence_id + '" data-approve="1">Aprobar</button><button type="button" class="btn btn-sm btn-outline-secondary gen-absence" data-id="' + c.absence_id + '" data-approve="0">Rechazar</button>' : '<span class="badge badge-secondary">Pendiente</span>');
			}).join(''));
		}
		if (temp) {
			h += confirmCard('fa-id-badge', 'primary', 'Códigos temporales', 'Siguen en la programación con este código hasta que nómina confirme el definitivo.', temp.people.map(function (p) {
				return personLine(p.name, p.department, '<code>' + esc(p.code) + '</code>');
			}).join(''));
		}
		if (prev) {
			h += confirmCard('fa-link', 'danger', 'Continuidad con el mes anterior', prev.detail, '');
		}
		if (diff) {
			h += '<div class="col-12 mb-3"><div class="border rounded p-3"><div class="d-flex align-items-center mb-1"><i class="fa-solid fa-calendar-week text-primary mr-2"></i><strong>Hoja dominical distinta de la programación real</strong></div><p class="small text-muted mb-2">Se continúa el ciclo real de ' + diff.people.length + ' persona(s). Si la hoja es la correcta, ajusta sus domingos en el calendario antes de aprobar.</p><div style="overflow-x: auto;"><table class="table table-sm small mb-0"><thead><tr><th>Colaborador</th><th>Área</th><th>Grupo</th><th>Según la hoja</th><th>Libre real</th><th>Próximo libre</th></tr></thead><tbody>' + diff.people.map(function (p) {
				var e = data.employees.filter(function (x) { return x.name === p.name; })[0];
				return '<tr><td class="font-weight-600">' + esc(p.name) + '</td><td>' + esc(p.department) + '</td><td>' + esc(p.group) + '</td><td>' + shortDate(p.sheet_free) + '</td><td><strong>' + shortDate(p.real_free) + '</strong></td><td>' + (e && e.next_free_sunday ? shortDate(e.next_free_sunday) : '—') + '</td></tr>';
			}).join('') + '</tbody></table></div></div></div>';
		}
		return h + '</div>';
	}

	function renderVersions() {
		if (!data.versions.length) return '<p class="text-muted mb-0">Este mes aún no tiene versiones generadas.</p>';
		var label = { draft: 'Borrador', approved: 'Aprobada', discarded: 'Reemplazada' }, h = '';
		data.versions.forEach(function (v) {
			h += '<div class="mb-3"><strong>Versión ' + v.version + '</strong> · ' + esc(label[v.status] || v.status) + ' · generó ' + esc(v.created_by || '—') + ' el ' + esc(v.created_at) + (v.decided_by ? ' · decidió ' + esc(v.decided_by) + ' el ' + esc(v.decided_at) : '') + '<ul class="small mb-0">';
			v.log.forEach(function (l) { h += '<li>' + esc(l.created_at) + ' · ' + esc(l.user || '—') + ' · ' + esc(l.detail) + '</li>'; });
			h += '</ul></div>';
		});
		return h;
	}

	// El calendario es la matriz mensual de siempre: solo se filtra por departamento
	function filterMatrix() {
		var dept = $('#genDept').val();
		$('#schMonthlyTable tbody tr').each(function () {
			var own = $(this).attr('data-dept') || $(this).attr('data-dept-head');
			$(this).toggle(!dept || own === dept);
		});
	}

	function renderBody() {
		filterMatrix();
		$('#genCalendar').toggle(view === 'calendar');
		$('#genBody').toggle(view !== 'calendar');
		if (!data || view === 'calendar') return;
		$('#genBody').html(CONTROL_VIEWS.indexOf(view) >= 0 ? renderControl() : view === 'confirm' ? renderConfirm() : view === 'week' ? renderWeek() : view === 'employee' ? renderEmployee() : view === 'hourly' ? renderHourly() : view === 'alerts' ? renderAlerts() : view === 'equity' ? renderEquity() : renderVersions());
	}

	function render() {
		var r = data.report, c = r.coverage.summary, isDraft = data.source === 'draft';
		$('#genRecalc').prop('disabled', !isDraft);
		$('#genApprove').prop('disabled', !isDraft || !r.can_approve);
		$('#genAlertCount').text(r.counts.legal + r.counts.coverage + r.counts.warn);
		var tiles = [['Incumplimientos obligatorios', r.counts.legal, r.counts.legal ? 'text-danger' : 'text-success'], ['Faltas de cobertura', r.counts.coverage, r.counts.coverage ? 'text-warning' : 'text-success'], ['Advertencias', r.counts.warn, 'text-dark'],
			['Días-área cubiertos', c.green + c.yellow + ' / ' + (c.green + c.yellow + c.red), 'text-primary'], ['Por confirmar', data.confirmations.length, data.confirmations.length ? 'text-warning' : 'text-success'], ['Versiones del mes', data.versions.length, 'text-dark']];
		$('#genKpis').html(tiles.map(function (t) { return '<div class="col-6 col-md-3 col-xl-2 mb-2"><div class="p-2 bg-white rounded border text-center shadow-sm"' + (t[0] === 'Por confirmar' ? ' id="genKpiConfirm" role="button" tabindex="0" style="cursor: pointer;" title="Ver los puntos por confirmar"' : '') + '><div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">' + esc(t[0]) + '</div><div class="h5 mb-0 font-weight-bold ' + t[2] + '">' + esc(t[1]) + '</div></div></div>'; }).join(''));
		$('#genConfirmCount').text(data.confirmations.length || '');
		renderBody();
	}

	function load() {
		$.getJSON(U('/api/schedule/engine/dashboard'), { branch_id: branch(), year: year(), month: month() }).done(function (res) {
			data = res;
			var keep = $('#genDept').val(), depts = [];
			res.employees.forEach(function (e) { if (depts.indexOf(e.department) < 0) depts.push(e.department); });
			$('#genDept').html('<option value="">Todos</option>' + depts.map(function (d) { return '<option' + (d === keep ? ' selected' : '') + '>' + esc(d) + '</option>'; }).join(''));
			// El modal de edición ofrece todos los códigos de Turnos SAP
			var known = $('#mEditShiftSelect option').map(function () { return this.value; }).get();
			var extra = res.sap_codes.concat(['INC', 'PERM']).filter(function (c) { return known.indexOf(c) < 0 && /^(7|INC$|PERM$)/.test(c); });
			if (extra.length) $('#mEditShiftSelect').append('<optgroup label="Otros turnos SAP">' + extra.map(function (c) { return '<option>' + esc(c) + '</option>'; }).join('') + '</optgroup>');
			render();
		}).fail(function (xhr) { toastr.error(errorOf(xhr)); });
	}

	function build() {
		var run = function () {
			toastr.info('Generando la programación…');
			post('build', { branch_id: branch(), year: year(), month: month() }).done(function () { window.location.reload(); });
		};
		data && data.source === 'draft' ? bootbox.confirm('Esto reemplaza el borrador actual y sus ediciones manuales. ¿Continuar?', function (ok) { if (ok) run(); }) : run();
	}

	function approve() {
		post('drafts/' + data.draft_id + '/approve', { accept_coverage_gaps: true, accept_confirmations: true }).done(function () { window.location.reload(); });
	}

	function askApproval() {
		var r = data.report, lines = [];
		if (r.counts.coverage) lines.push(r.counts.coverage + ' falta(s) de cobertura quedarán en el Centro de Alertas.');
		data.confirmations.forEach(function (c) { lines.push(c.summary || c.detail); });
		var message = '<p>Al aprobar se reemplazan los días futuros de ' + MONTHS[month() - 1] + ' y se publican a los empleados.</p>' + (lines.length ? '<p class="mb-1"><strong>Acepta lo siguiente:</strong></p><ul class="small">' + lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' : '');
		var confirm = function () { bootbox.confirm({ title: 'Aprobar programación', message: message, callback: function (ok) { if (ok) approve(); } }); };
		if (data.state.published && !data.state.reopened) {
			bootbox.prompt({ title: 'El mes ya está publicado. Motivo para reabrirlo', inputType: 'textarea', callback: function (reason) {
				if (reason === null) return;
				post('reopen', { branch_id: branch(), year: year(), month: month(), reason: reason }).done(function (state) { data.state = state; confirm(); });
			} });
			return;
		}
		confirm();
	}

	window.initScheduleGenerator = function () {
		if (loaded) return;
		loaded = true;
		load();
	};

	$(function () {
		$(document).on('change', '#genDept', renderBody);
		$('#genBuild').on('click', build);
		$('#genRecalc').on('click', function () { post('drafts/' + data.draft_id + '/recalculate').done(function () { window.location.reload(); }); });
		$('#genApprove').on('click', askApproval);
		$('#genExport').on('click', function () {
			if (data && !data.report.can_approve) { toastr.error('Corrija los incumplimientos obligatorios antes de descargar.'); return; }
			window.location.href = U('/api/schedule/engine/export') + '?branch_id=' + branch() + '&year=' + year() + '&month=' + month();
		});
		$(document).on('click', '#genTabs [data-gen-view]', function () {
			view = $(this).data('gen-view');
			$('#genTabs button').removeClass('btn-success').addClass('btn-outline-secondary');
			$(this).removeClass('btn-outline-secondary').addClass('btn-success');
			renderBody();
		});
		$(document).on('click', '#genSaveRestriction', function () {
			var box = '#genRestriction', days = (field(box, 'weekdays') || '').split(',').map(function (x) { return parseInt(x, 10); }).filter(function (x) { return x >= 1 && x <= 7; });
			post('restrictions', { payroll_code: field(box, 'payroll_code'), restriction_type: field(box, 'restriction_type'), start_date: field(box, 'start_date'), end_date: field(box, 'end_date'), reason: field(box, 'reason'), weekdays: days, window_start: field(box, 'window_start'), window_end: field(box, 'window_end'), max_weekly_hours: field(box, 'max_weekly_hours'), night_mode: field(box, 'night_mode'), approved_by: field(box, 'approved_by'), notes: field(box, 'notes') })
				.done(function () { toastr.success('Restricción guardada. Vuelva a generar el mes.'); loadControl(); });
		});
		$(document).on('click', '.gen-del-restriction', function (e) {
			e.preventDefault();
			post('restrictions/' + $(this).data('id') + '/delete').done(loadControl);
		});
		$(document).on('click', '.gen-config', function () {
			var code = $(this).data('code') + '', p = control.planta.filter(function (x) { return x.payroll_code === code; })[0];
			bootbox.dialog({ title: 'Configurar ' + esc(p.name), message: '<div id="genConfig"><label class="small">Tipo de contrato</label><input class="form-control form-control-sm mb-2" name="contract_type" value="' + esc(p.contract_type || '') + '"><label class="small">Turnos permitidos (códigos separados por coma; vacío = todos)</label><input class="form-control form-control-sm mb-2" name="allowed_shifts" value="' + esc(p.allowed_shifts.join(',')) + '"><label class="small">Secuencia de compensatorio (1 = lunes … 5 = viernes)</label><input class="form-control form-control-sm mb-2" name="comp_sequence" value="' + esc(p.comp_sequence || '') + '"></div>',
				buttons: { cancel: { label: 'Cancelar' }, ok: { label: 'Guardar', className: 'btn-success', callback: function () {
					post('collaborators/' + encodeURIComponent(code) + '/config', { contract_type: field('#genConfig', 'contract_type'), allowed_shifts: field('#genConfig', 'allowed_shifts'), comp_sequence: field('#genConfig', 'comp_sequence') }).done(loadControl);
				} } } });
		});
		$(document).on('click', '#genSaveRequest', function () {
			var box = '#genRequest', payload = { shift_code: field(box, 'shift_code'), with_payroll_code: field(box, 'with_payroll_code'), origin_sunday: field(box, 'origin_sunday') };
			post('requests', { request_type: field(box, 'request_type'), payroll_code: field(box, 'payroll_code'), start_date: field(box, 'start_date'), end_date: field(box, 'end_date') || field(box, 'start_date'), reason: field(box, 'reason'), payload: payload })
				.done(function () { toastr.success('Solicitud registrada.'); loadControl(); });
		});
		function afterRequest(res) {
			if (res.conflict) toastr.warning(res.conflict); else if (res.status === 'aplicada') toastr.success('Solicitud aplicada. ' + (res.coverage_count ? res.coverage_count + ' falta(s) de cobertura en esas fechas.' : 'Cobertura sin novedad.'));
			load();
			loadControl();
		}
		$(document).on('click', '.gen-decide', function () { post('requests/' + $(this).data('id') + '/decide', { approve: $(this).data('approve') === 1 }).done(afterRequest); });
		$(document).on('click', '.gen-apply', function () { post('requests/' + $(this).data('id') + '/apply').done(afterRequest); });
		$(document).on('click', '#genKpiConfirm', function () { $('#genTabs [data-gen-view="confirm"]').click(); });
		$(document).on('click', '#genPrevWeek', function () { weekIndex = Math.max(0, weekIndex - 1); renderBody(); });
		$(document).on('click', '#genNextWeek', function () { weekIndex++; renderBody(); });
		$(document).on('change', '#genEmployee', function () { focusEmployee = $(this).val(); renderBody(); });
		$(document).on('change', '#genHourlyDate', function () { hourlyDate = $(this).val(); renderBody(); });
		$(document).on('click', '#genBody .gen-cell', function () {
			var code = $(this).data('code') + '', date = $(this).data('date'), person = data.employees.filter(function (e) { return e.payroll_code === code; })[0];
			window.openMonthlyCellEdit(code, person ? person.name : code, date, $(this).text());
		});
		$(document).on('click', '.gen-return', function () {
			var date = $(this).siblings('.gen-return-date').val();
			if (!date) { toastr.warning('Indique la fecha de regreso.'); return; }
			post('absences/' + $(this).data('id') + '/end', { return_date: date }).done(function () { toastr.success('Regreso registrado. Vuelva a generar el mes.'); load(); });
		});
		$(document).on('click', '.gen-absence', function () {
			var approved = $(this).data('approve') === 1;
			post('absences/' + $(this).data('id') + '/decide', { approve: approved }).done(function () { toastr.success(approved ? 'Ausencia aprobada. Vuelva a generar el mes.' : 'Solicitud rechazada.'); load(); });
		});
	});
})();
