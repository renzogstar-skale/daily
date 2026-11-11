/**
 * DAILY · Centro de Alertas — Mando Operativo Inteligente
 * Controlador interactivo de dashboard, tabla split-screen, panel de seguimiento,
 * stepper de resolución, filtros en tiempo real y gráficos Chart.js.
 */
(function ($) {
	'use strict';

	// ==========================================================================
	// 1. ALERTAS REGISTRADAS (tabla dairy_alerts vía /api/alerts/list)
	//    Son las mismas filas que generan las reglas de HOY, Inventario, Calidad, Mermas y Personal.
	// ==========================================================================
	var INCIDENTS = [];

	// Hora de referencia para plazos y tiempo transcurrido (se actualiza en cada carga)
	var NOW = new Date();

	var PRIO = { P1: ['Crítica', 'is-critical'], P2: ['Alta', 'is-high'], P3: ['Media', 'is-medium'], P4: ['Baja', 'is-low'] };
	var CAT_LABEL = { inventory: 'Inventario', waste: 'Mermas', quality: 'Calidad', operations: 'Operaciones', staff: 'Personal', workforce: 'Personal', sales: 'Ventas',
		purchasing: 'Compras', security: 'Seguridad', systems: 'Sistemas', other: 'Otras' };
	var AVATAR = ['#F59E0B', '#3B82F6', '#8B5CF6', '#10B981', '#EF4444', '#06B6D4'];

	function isoMin(s) { return s ? String(s).replace(' ', 'T').slice(0, 16) : null; }
	function money(v) { return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

	// Estado del flujo registrado → estado de la bandeja
	function uiStatus(a) {
		switch (a.status) {
			case 'open': return a.owner_user_id ? 'pending' : 'unassigned';
			case 'assigned': return 'pending';
			case 'investigating': return 'investigating';
			case 'pending_approval': return 'validation';
			case 'closed': case 'dismissed': return 'resolved';
			default: return 'in_progress';
		}
	}

	var BRANCH_IDS = {};
	function fromApi(a) {
		if (a.branch_id && a.branch_name) BRANCH_IDS[a.branch_name] = a.branch_id;
		var code = uiStatus(a);
		var st = STATUS[code];
		var p = PRIO[a.priority] || PRIO.P3;
		var owner = a.owner_name || 'Sin asignar';
		var created = isoMin(a.created_at), due = isoMin(a.due_at);
		var c = parseDemoDate(created), d = parseDemoDate(due);
		var val = parseFloat(a.value) || 0;
		var cat = CAT_LABEL[a.category] || 'Otras';
		return {
			id: String(a.id), dbStatus: a.status, allowed: a.allowed || [],
			priority: p[0], priorityCode: a.priority, prioClass: p[1],
			title: a.title, subtitle: a.rule_name || 'Regla automática',
			category: cat, categoryFull: cat + (a.rule_name ? ' / ' + a.rule_name : ''),
			branch: a.branch_name || 'Red',
			responsible: {
				name: owner, role: a.owner_user_id ? 'Responsable' : 'Pendiente de asignar', bg: a.owner_user_id ? AVATAR[a.owner_user_id % AVATAR.length] : '#94A3B8',
				initials: a.owner_user_id ? owner.split(' ').map(function (n) { return n[0]; }).join('').slice(0, 2).toUpperCase() : '—'
			},
			status: st.label, statusCode: code, statusClass: st.cls, currentStep: st.step,
			impact: val > 0 ? money(val) : 'Sin impacto', impactVal: val,
			createdAt: created, slaHours: (c && d && d > c) ? (d - c) / 3600000 : null, resolvedAt: isoMin(a.resolved_at),
			activity: a.updated_at ? fmtDateTime(parseDemoDate(isoMin(a.updated_at))) : '',
			href: a.href || null, productId: a.product_id || null,
			productsCount: a.product_id ? 1 : 0,
			products: a.product_id ? [{ code: '#' + a.product_id, name: a.product_name || 'Producto', theor: '—', pos: '—', diff: '—', impact: val > 0 ? money(val) : '—' }] : [],
			timelineEvents: [{ time: created ? created.slice(11, 16) : '—', user: 'Sistema DAILY', role: a.rule_name || 'Registro', text: a.detail || a.title }],
			evidences: [],
			actions: a.recommended_action ? [{ title: a.recommended_action, done: code === 'resolved' }] : []
		};
	}

	var STATUS_COUNTS = null, TAB_COUNTS = null;

	function fetchAlerts(status) {
		return $.ajax({ url: Grocy.BaseUrl + '/api/alerts/list', data: { status: status, per: 200, sort: 'priority' }, dataType: 'json' });
	}

	var MONTHS = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];

	function pad2(n) { return (n < 10 ? '0' : '') + n; }

	function parseDemoDate(s) {
		var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(s || '');
		return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
	}

	function toDemoIso(d) {
		return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
	}

	function fmtDateTime(d) {
		return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ' - ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
	}

	function fmtDuration(min) {
		min = Math.round(Math.abs(min));
		var h = Math.floor(min / 60), m = min % 60;
		if (h >= 24) return Math.floor(h / 24) + ' d ' + (h % 24) + ' h';
		if (!h) return m + ' min';
		return h + ' h' + (m ? ' ' + pad2(m) + ' min' : '');
	}

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Estado del plazo: ok | risk | overdue | none (sin SLA) | closed | invalid (marcas inconsistentes)
	function computeSla(it) {
		var created = parseDemoDate(it.createdAt);
		if (!created) return { state: 'invalid', reason: 'Sin fecha de creación válida' };
		var end = NOW;
		if (it.statusCode === 'resolved') {
			end = parseDemoDate(it.resolvedAt);
			if (!end) return { state: 'invalid', reason: 'Resuelta sin fecha de cierre' };
		}
		if (created > NOW || end < created) return { state: 'invalid', reason: 'La fecha de creación es posterior al cierre o a la hora actual' };

		var elapsed = (end - created) / 60000;
		var limit = it.slaHours ? it.slaHours * 60 : null;
		var deadline = limit ? new Date(created.getTime() + limit * 60000) : null;
		if (it.statusCode === 'resolved') return { state: 'closed', elapsed: elapsed, limit: limit, deadline: deadline, met: limit ? elapsed <= limit : null };
		if (!limit) return { state: 'none', elapsed: elapsed };

		var remaining = limit - elapsed;
		var state = remaining < 0 ? 'overdue' : (remaining <= Math.max(60, limit * 0.25) ? 'risk' : 'ok');
		return { state: state, elapsed: elapsed, limit: limit, deadline: deadline, remaining: remaining };
	}

	// Texto corto del plazo (tabla, kanban, cronología y panel usan el mismo)
	function slaShort(sla) {
		switch (sla.state) {
			case 'ok':
			case 'risk': return 'Vence en ' + fmtDuration(sla.remaining);
			case 'overdue': return 'Vencida hace ' + fmtDuration(sla.remaining);
			case 'closed': return 'Cerrada en ' + fmtDuration(sla.elapsed);
			case 'none': return 'Sin SLA configurado';
			default: return 'Fechas inconsistentes';
		}
	}

	function normalizeIncident(it) {
		if (it.slaHours === undefined) it.slaHours = null;
		var c = parseDemoDate(it.createdAt);
		it.created = c ? fmtDateTime(c) : 'Sin fecha';
		return it;
	}

	var STATUS = {
		investigating: { label: 'En investigación', cls: 'is-investigating', step: 3 },
		pending: { label: 'Pendiente', cls: 'is-pending', step: 2 },
		in_progress: { label: 'En proceso', cls: 'is-in-progress', step: 3 },
		validation: { label: 'En validación', cls: 'is-validation', step: 4 },
		resolved: { label: 'Resuelta', cls: 'is-resolved', step: 5 },
		unassigned: { label: 'Sin asignar', cls: 'is-unassigned', step: 1 }
	};

	// ==========================================================================
	// 2. ESTADO DE LA BANDEJA, TABLA Y PANEL DERECHO
	// ==========================================================================

	var state = {
		load: 'loading',      // loading | ready | error
		filtered: [],
		page: 1,
		pageSize: 10,
		selectedId: null,
		checked: {}           // casillas para acciones masivas (independientes de la fila seleccionada)
	};
	var currentFilter = 'all';

	function findIncident(id) {
		return INCIDENTS.find(function (x) { return x.id === id; }) || null;
	}

	function selectedIncident() {
		return state.selectedId ? findIncident(state.selectedId) : null;
	}

	function checkedIds() {
		return Object.keys(state.checked).filter(function (id) { return state.checked[id] && findIncident(id); });
	}

	// Carga de las alertas registradas: activas + cerradas de los últimos 7 días (para «Resueltas» y el tiempo de resolución)
	var RESOLVED_WINDOW_DAYS = 7;
	function loadIncidents() {
		state.load = 'loading';
		renderTable();
		$.when(fetchAlerts('active'), fetchAlerts('done')).done(function (act, done) {
			NOW = new Date();
			var since = new Date(NOW.getTime() - RESOLVED_WINDOW_DAYS * 86400000);
			var urlBranch = new URLSearchParams(window.location.search).get('branch');
			STATUS_COUNTS = act[0].status_counts || null;
			TAB_COUNTS = act[0].counts || null;
			INCIDENTS = act[0].rows.map(fromApi).concat(done[0].rows.map(fromApi).filter(function (it) {
				var r = parseDemoDate(it.resolvedAt);
				return r && r >= since;
			}));
			INCIDENTS.forEach(normalizeIncident);
			state.load = 'ready';
			if (!state.selectedId && INCIDENTS.length) state.selectedId = INCIDENTS[0].id;
			populateFilterOptions();
			if (urlBranch) $('#alcFilterBranch').val(urlBranch);
			refreshAll();
			renderSummary();
		}).fail(function () {
			state.load = 'error';
			renderTable();
		});
	}

	// Tarjetas y gráficos calculados con las mismas alertas de la bandeja
	var CAT_COLORS = { 'Inventario': '#3B82F6', 'Mermas': '#F97316', 'Calidad': '#EAB308', 'Compras': '#B45309', 'Personal': '#06B6D4', 'Ventas': '#008E48', 'Sistemas': '#64748B', 'Seguridad': '#B91C1C', 'Operaciones': '#0EA5E9', 'Otras': '#94A3B8' };
	var charts = {};
	function renderSummary() {
		var active = INCIDENTS.filter(function (it) { return it.statusCode !== 'resolved'; });
		var resolved = INCIDENTS.filter(function (it) { return it.statusCode === 'resolved'; });
		var todayKey = toDemoIso(NOW).slice(0, 10);
		var overdue = active.filter(function (it) { return computeSla(it).state === 'overdue'; }).length;
		var resolvedToday = resolved.filter(function (it) { return (it.resolvedAt || '').slice(0, 10) === todayKey; }).length;
		var durations = resolved.map(function (it) { return computeSla(it); }).filter(function (s) { return s.state === 'closed'; }).map(function (s) { return s.elapsed; });
		var avgMin = durations.length ? durations.reduce(function (a, b) { return a + b; }, 0) / durations.length : null;
		var kpi = {
			all: active.length,
			critical: active.filter(function (it) { return it.priorityCode === 'P1'; }).length,
			overdue: overdue,
			unassigned: active.filter(function (it) { return it.statusCode === 'unassigned'; }).length,
			resolved: resolvedToday
		};
		// Totales del servidor (todas las activas del filtro, no solo la página cargada)
		if (TAB_COUNTS) { kpi.all = TAB_COUNTS.all; kpi.critical = TAB_COUNTS.P1; kpi.overdue = TAB_COUNTS.overdue; kpi.unassigned = TAB_COUNTS.unassigned; }
		$('.daily-kpi-stat-card').each(function () {
			var tab = $(this).data('filter-tab');
			var $n = $(this).find('.daily-kpi-big-number');
			if (tab && kpi[tab] !== undefined) $n.text(kpi[tab]);
			else if (!tab) $n.text(avgMin === null ? 'Sin información' : fmtDuration(avgMin));
		});
		// Sin serie comparable registrada: se ocultan las variaciones porcentuales
		$('.daily-kpi-stat-card .daily-kpi-badge, .daily-impact-val-row .daily-kpi-badge').hide();
		$('.daily-kpi-stat-card[data-filter-tab="resolved"] .daily-kpi-subtext').text(resolved.length + ' cerradas en ' + RESOLVED_WINDOW_DAYS + ' días');
		$('.daily-kpi-stat-card:not([data-filter-tab]) .daily-kpi-subtext').text(durations.length ? 'Promedio de ' + durations.length + ' cierres (' + RESOLVED_WINDOW_DAYS + ' días)' : 'Sin cierres en ' + RESOLVED_WINDOW_DAYS + ' días');

		// Estado general: resueltas (7 días) / en proceso / sin actividad / vencidas
		var inProc = active.filter(function (it) { return ['investigating', 'in_progress', 'validation', 'pending'].indexOf(it.statusCode) !== -1 && computeSla(it).state !== 'overdue'; }).length;
		var idle = active.filter(function (it) { return it.statusCode === 'unassigned' && computeSla(it).state !== 'overdue'; }).length;
		// La bandeja trae una página (200): el estado de resolución usa los conteos del servidor sobre todas las activas
		var statusData = STATUS_COUNTS ? [resolved.length, STATUS_COUNTS.in_process, STATUS_COUNTS.idle, STATUS_COUNTS.overdue] : [resolved.length, inProc, idle, overdue];
		var statusTotal = statusData.reduce(function (a, b) { return a + b; }, 0);
		var $st = $('#chartStatusResolution').closest('.daily-donut-with-legend');
		$st.find('.daily-donut-center-pct').text(statusTotal ? Math.round(resolved.length / statusTotal * 100) + '%' : 'Sin información');
		$st.find('.daily-legend-row b').each(function (i) { $(this).text(statusData[i]); });

		// Categorías presentes en las alertas activas
		var byCat = {};
		active.forEach(function (it) { byCat[it.category] = (byCat[it.category] || 0) + 1; });
		var cats = Object.keys(byCat).sort(function (a, b) { return byCat[b] - byCat[a]; });
		var $cat = $('#chartCategoryAlerts').closest('.daily-donut-with-legend');
		$cat.find('.daily-donut-center-pct').text(active.length);
		$cat.find('.daily-donut-legend-list').html(cats.length ? cats.map(function (c) {
			return '<div class="daily-legend-row"><span><span class="daily-dot-legend" style="background: ' + (CAT_COLORS[c] || '#94A3B8') + ';"></span>' + esc(c) + '</span><b>' + byCat[c] + '</b></div>';
		}).join('') : '<div class="daily-legend-row"><span>Sin alertas activas</span></div>');

		// Impacto: suma de valores registrados en alertas activas; las alertas sin valor no suman cero, se informan aparte
		var withValue = active.filter(function (it) { return it.impactVal > 0; });
		var impact = withValue.reduce(function (a, it) { return a + it.impactVal; }, 0);
		$('.daily-impact-main-amount').text(withValue.length ? money(impact) : 'Sin información');
		$('.daily-impact-sub').html('Pérdida potencial registrada<br>' + withValue.length + ' de ' + active.length + ' alertas activas con valor');

		// Tendencia de 7 días: creadas y resueltas por día
		var days = [], created = [], closed = [], impactDays = [];
		for (var i = 6; i >= 0; i--) {
			var dt = new Date(NOW.getTime() - i * 86400000), key = toDemoIso(dt).slice(0, 10);
			days.push(dt.getDate() + ' ' + MONTHS[dt.getMonth()].replace('.', ''));
			created.push(INCIDENTS.filter(function (it) { return (it.createdAt || '').slice(0, 10) === key; }).length);
			closed.push(resolved.filter(function (it) { return (it.resolvedAt || '').slice(0, 10) === key; }).length);
			impactDays.push(Math.round(active.filter(function (it) { return (it.createdAt || '').slice(0, 10) === key; }).reduce(function (a, it) { return a + it.impactVal; }, 0)));
		}
		drawCharts(statusData, cats, cats.map(function (c) { return byCat[c]; }), days, created, closed, impactDays);
	}

	// Las pestañas (y las tarjetas que las abren) cuentan alertas activas; «Resueltas» muestra las cerradas del período
	function matchesPill(it, pill) {
		if (pill === 'resolved') return it.statusCode === 'resolved';
		if (it.statusCode === 'resolved') return false;
		if (pill === 'critical') return it.priorityCode === 'P1';
		if (pill === 'overdue') return computeSla(it).state === 'overdue';
		if (pill === 'unassigned') return it.statusCode === 'unassigned';
		if (pill === 'in_progress') return it.statusCode === 'in_progress' || it.statusCode === 'investigating';
		if (pill === 'resolved') return it.statusCode === 'resolved';
		return true;
	}

	function applyFilters(resetPage) {
		var q = ($('#alcTableSearch').val() || '').toLowerCase().trim();
		var cat = $('#alcFilterCategory').val();
		var branch = $('#alcFilterBranch').val();
		var st = $('#alcFilterStatus').val();

		state.filtered = INCIDENTS.filter(function (it) {
			if (!matchesPill(it, currentFilter)) return false;
			if (cat && it.category !== cat) return false;
			if (branch && it.branch !== branch) return false;
			if (st && it.status !== st) return false;
			if (q) {
				var text = ('#' + it.id + ' ' + it.title + ' ' + it.subtitle + ' ' + it.category + ' ' + it.branch + ' ' + it.responsible.name + ' ' + it.status).toLowerCase();
				if (text.indexOf(q) === -1) return false;
			}
			return true;
		});

		var pages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
		if (resetPage) state.page = 1;
		state.page = Math.min(Math.max(1, state.page), pages);

		// El detalle se conserva solo si la alerta sigue dentro de los resultados
		if (state.selectedId && !state.filtered.some(function (x) { return x.id === state.selectedId; })) {
			state.selectedId = null;
		}

		renderTable();
		bindDetailPanel(selectedIncident());
	}

	function refreshAll() {
		updatePillCounts();
		applyFilters(false);
		populateKanban();
		populateTimeline();
	}

	function updatePillCounts() {
		$('.daily-filter-pill-btn').each(function () {
			var pill = $(this).data('filter');
			var n = INCIDENTS.filter(function (it) { return matchesPill(it, pill); }).length;
			var node = this.lastChild;
			if (node && node.nodeType === 3) node.nodeValue = node.nodeValue.replace(/\(\d+\)/, '(' + n + ')');
		});
	}

	// Las opciones de categoría y sucursal salen de las alertas cargadas
	function populateFilterOptions() {
		[['#alcFilterCategory', 'category', 'Todas las categorías'], ['#alcFilterBranch', 'branch', 'Todas las sucursales']].forEach(function (cfg) {
			var $sel = $(cfg[0]);
			var current = $sel.val();
			var values = INCIDENTS.map(function (it) { return it[cfg[1]]; }).filter(function (v, i, a) { return v && a.indexOf(v) === i; }).sort();
			$sel.html('<option value="">' + cfg[2] + '</option>' + values.map(function (v) { return '<option value="' + esc(v) + '">' + esc(v) + '</option>'; }).join(''));
			if (values.indexOf(current) !== -1) $sel.val(current);
		});
	}

	function renderMessageRow(icon, title, sub, extra) {
		$('#incidentsTableBody').html(
			'<tr class="daily-table-msg-row"><td colspan="11">' +
				'<div class="daily-table-msg">' + icon + '<b>' + title + '</b>' + (sub ? '<span>' + sub + '</span>' : '') + (extra || '') + '</div>' +
			'</td></tr>'
		);
	}

	function renderTable() {
		var $tbody = $('#incidentsTableBody');
		var $summary = $('#dailyTableSummary');
		$('#checkAllRows').prop('checked', false).prop('indeterminate', false);

		if (state.load === 'loading') {
			renderMessageRow('<i class="fa-solid fa-spinner fa-spin"></i>', 'Cargando alertas…', '');
			$summary.text('Cargando alertas…');
			renderPagination(0);
			return;
		}
		if (state.load === 'error') {
			renderMessageRow('<i class="fa-solid fa-triangle-exclamation text-danger"></i>', 'No se pudieron cargar las alertas', 'Revisa la conexión e inténtalo de nuevo.',
				'<button type="button" class="btn btn-sm btn-outline-success mt-2" id="alcRetryLoad"><i class="fa-solid fa-rotate-right mr-1"></i>Reintentar</button>');
			$summary.text('Error al cargar las alertas');
			renderPagination(0);
			return;
		}

		var total = state.filtered.length;
		if (!total) {
			renderMessageRow('<i class="fa-solid fa-filter-circle-xmark text-muted"></i>', 'No hay alertas para los filtros seleccionados', 'Cambia la búsqueda o los filtros para ver más resultados.',
				'<button type="button" class="btn btn-sm btn-light border mt-2" id="alcClearFilters">Limpiar filtros</button>');
			$summary.text('Sin resultados · 0 alertas');
			renderPagination(0);
			updateBulkInfo();
			return;
		}

		var start = (state.page - 1) * state.pageSize;
		var rows = state.filtered.slice(start, start + state.pageSize);
		$tbody.empty();

		rows.forEach(function (item) {
			var sla = computeSla(item);
			var isSel = item.id === state.selectedId;
			var timeCls = (sla.state === 'overdue' || sla.state === 'risk') ? 'is-red' : (sla.state === 'invalid' ? 'is-warn' : '');
			var timeMain = (sla.state === 'invalid') ? '—' : fmtDuration(sla.elapsed);
			var impactSub = item.impactVal > 0 ? 'Estimado' : 'Sin impacto';
			var tr = document.createElement('tr');
			tr.className = isSel ? 'is-selected' : '';
			tr.setAttribute('data-id', item.id);
			tr.setAttribute('tabindex', '0');
			tr.setAttribute('aria-selected', isSel ? 'true' : 'false');

			tr.innerHTML =
				'<td class="daily-check-cell" data-label="Marcar"><input type="checkbox" class="row-checkbox" data-id="' + esc(item.id) + '" aria-label="Marcar #' + esc(item.id) + ' para acciones masivas"' + (state.checked[item.id] ? ' checked' : '') + '></td>' +
				'<td data-label="Prioridad"><span class="daily-prio-badge ' + esc(item.prioClass) + '">▲ ' + esc(item.priority) + '</span></td>' +
				'<td data-label="ID"><span class="daily-inc-id">#' + esc(item.id) + '</span></td>' +
				'<td data-label="Incidencia">' +
					'<div class="daily-inc-content">' +
						'<span class="daily-inc-title">' + esc(item.title) + '' + '</span>' +
						'<span class="daily-inc-subtitle">' + esc(item.subtitle) + '</span>' +
					'</div>' +
				'</td>' +
				'<td data-label="Categoría"><span class="text-muted">' + esc(item.category) + '</span></td>' +
				'<td data-label="Sucursal"><span class="text-muted">' + esc(item.branch) + '</span></td>' +
				'<td data-label="Responsable">' +
					'<div class="daily-resp-cell">' +
						'<div class="daily-avatar-circle" style="background: ' + esc(item.responsible.bg) + ';">' + esc(item.responsible.initials) + '</div>' +
						'<div class="daily-resp-info">' +
							'<span class="daily-resp-name">' + esc(item.responsible.name) + '</span>' +
							'<span class="daily-resp-role">' + esc(item.responsible.role) + '</span>' +
						'</div>' +
					'</div>' +
				'</td>' +
				'<td data-label="Tiempo">' +
					'<div class="daily-time-cell">' +
						'<span class="daily-time-main">' + timeMain + '</span>' +
						'<div class="daily-time-sub ' + timeCls + '">' + esc(slaShort(sla)) + '</div>' +
					'</div>' +
				'</td>' +
				'<td data-label="Estado"><span class="daily-state-pill ' + esc(item.statusClass) + '">' + esc(item.status) + '</span></td>' +
				'<td data-label="Impacto"><span class="daily-impact-cell">' + esc(item.impact) + '</span><span class="daily-impact-sub">' + impactSub + '</span></td>' +
				'<td class="daily-dots-cell"><button type="button" class="daily-action-dots-btn" data-act-id="' + esc(item.id) + '" title="Ver detalle" aria-label="Ver detalle de #' + esc(item.id) + '"><i class="fa-solid fa-ellipsis-vertical"></i></button></td>';

			$tbody.append(tr);
		});

		$summary.text('Mostrando ' + (start + 1) + '-' + (start + rows.length) + ' de ' + total + ' alerta' + (total === 1 ? '' : 's'));
		renderPagination(total);
		updateBulkInfo();
	}

	function renderPagination(total) {
		var $p = $('#dailyPagination').empty();
		var pages = Math.ceil(total / state.pageSize);
		if (pages < 1) { $p.hide(); return; }
		$p.show();

		var cur = state.page;
		var btn = function (label, page, opts) {
			opts = opts || {};
			return '<button type="button" class="daily-page-btn' + (opts.active ? ' is-active' : '') + '" data-page="' + page + '"' +
				(opts.disabled ? ' disabled' : '') + (opts.aria ? ' aria-label="' + opts.aria + '"' : '') + (opts.active ? ' aria-current="page"' : '') + '>' + label + '</button>';
		};
		var html = btn('‹', cur - 1, { disabled: cur <= 1, aria: 'Página anterior' });
		var list = [];
		for (var i = 1; i <= pages; i++) {
			if (i === 1 || i === pages || Math.abs(i - cur) <= 1) list.push(i);
			else if (list[list.length - 1] !== '…') list.push('…');
		}
		list.forEach(function (n) {
			html += (n === '…') ? '<span class="px-1 text-muted">…</span>' : btn(n, n, { active: n === cur });
		});
		html += btn('›', cur + 1, { disabled: cur >= pages, aria: 'Página siguiente' });
		$p.html(html);
	}

	function updateBulkInfo() {
		var n = checkedIds().length;
		$('#alcBulkInfo').toggle(n > 0);
		$('#alcBulkCount').text(n + (n === 1 ? ' marcada' : ' marcadas'));
	}

	function stepTime(item, s, step, sla) {
		if (s === 1) return item.created;
		if (s < step) return 'Completada';
		if (s === step) return step === 5 ? 'Cerrada' : ('En curso' + (sla.state !== 'invalid' ? ' (' + fmtDuration(sla.elapsed) + ')' : ''));
		return 'Pendiente';
	}

	function bindDetailPanel(item) {
		var $panel = $('#dailyDetailPanel');
		$('#incidentsTableBody tr').removeClass('is-selected').attr('aria-selected', 'false');

		if (!item) {
			$panel.addClass('is-empty').removeClass('is-mobile-open');
			$('body').removeClass('alc-detail-open');
			return;
		}
		$panel.removeClass('is-empty');
		$('#incidentsTableBody tr[data-id="' + item.id + '"]').addClass('is-selected').attr('aria-selected', 'true');

		var sla = computeSla(item);

		// Encabezado del panel
		$('#panelDetailId').text('#' + item.id);
		$('#panelDetailPriority').attr('class', 'daily-prio-badge ' + item.prioClass).text('▲ ' + item.priority);
		$('#panelDetailStatusText').text(item.status);
		$('#panelDetailTitle').text(item.title);
		$('#panelDetailSubtitle').text(item.subtitle);
		if (item.href) $('#panelDetailSource').attr('href', Grocy.BaseUrl + item.href).show(); else $('#panelDetailSource').hide();

		// Pestaña resumen
		$('#panelFactBranch').text(item.branch);
		$('#panelFactCategory').text(item.categoryFull || item.category);
		$('#panelFactCreated').text(item.created);
		$('#panelFactActivity').text(item.activity);
		$('#panelFactImpact').text(item.impact + (item.impactVal > 0 ? ' (estimado)' : ''));
		$('#panelFactAvatar').css('background', item.responsible.bg).text(item.responsible.initials);
		$('#panelFactOwnerName').text(item.responsible.name);
		$('#panelFactOwnerRole').text(item.responsible.role);
		$('#panelFactElapsed').html((sla.state === 'invalid' ? '—' : esc(fmtDuration(sla.elapsed))) +
			' <span class="small font-weight-bold ' + (sla.state === 'overdue' || sla.state === 'risk' ? 'text-danger' : 'text-muted') + '" id="panelFactDueNotice">(' + esc(slaShort(sla)) + ')</span>');

		// Stepper de 5 pasos
		var step = item.currentStep || 3;
		for (var s = 1; s <= 5; s++) {
			var $el = $('#step' + s);
			$el.removeClass('is-completed is-current is-pending');
			if (s < step || (s === 5 && step === 5)) {
				$el.addClass('is-completed');
				$el.find('.daily-step-circle').html('<i class="fa-solid fa-check"></i>');
			} else if (s === step) {
				$el.addClass('is-current');
				$el.find('.daily-step-circle').text(s);
			} else {
				$el.addClass('is-pending');
				$el.find('.daily-step-circle').text(s);
			}
			$el.find('.daily-step-time').text(stepTime(item, s, step, sla));
		}

		// Tarjeta SLA: misma fecha límite y mismo reloj que la tabla
		var policy = item.slaHours ? 'Tiempo límite: ' + item.slaHours + ' h para prioridad ' + item.priority.toLowerCase() + ' · vence ' + fmtDateTime(sla.deadline || new Date(parseDemoDate(item.createdAt).getTime() + item.slaHours * 3600000)).split(' - ')[1] : '';
		var slaUi = {
			ok: ['SLA en tiempo', policy, 'Queda', fmtDuration(sla.remaining), 'is-ok'],
			risk: ['▲ SLA en riesgo', policy, 'Queda', fmtDuration(sla.remaining), 'is-risk'],
			overdue: ['▲ SLA vencido', policy, 'Vencida hace', fmtDuration(sla.remaining), 'is-overdue'],
			closed: ['✓ Incidencia resuelta', sla.met === false ? 'Cerrada fuera del plazo de ' + item.slaHours + ' h.' : (policy ? 'Cerrada dentro del plazo de ' + item.slaHours + ' h.' : 'Cerrada.'), 'Cerrada en', fmtDuration(sla.elapsed || 0), 'is-closed'],
			none: ['Sin SLA configurado', 'Esta categoría no tiene un plazo de atención definido.', '', '—', 'is-none'],
			invalid: ['Marcas de tiempo inconsistentes', (sla.reason || '') + '. No se calcula el plazo.', '', '—', 'is-none']
		}[sla.state];
		$('#panelSlaCard').removeClass('is-ok is-risk is-overdue is-closed is-none').addClass(slaUi[4]).removeAttr('style');
		$('#panelSlaTitle').text(slaUi[0]).removeAttr('style');
		$('#panelSlaDesc').text(slaUi[1]);
		$('#panelSlaCard .daily-sla-rem-lbl').text(slaUi[2]);
		$('#panelSlaRemaining').text(slaUi[3]).removeAttr('style');

		$('#panelTabProductsBtn').text('Productos (' + (item.productsCount || item.products.length) + ')');

		// Actividad
		var $act = $('#panelActivityList').empty();
		if (item.timelineEvents && item.timelineEvents.length) {
			item.timelineEvents.forEach(function (ev) {
				$act.append(
					'<div class="d-flex align-items-start gap-2 mb-3 pb-2 border-bottom">' +
						'<div class="badge badge-light border text-dark font-mono font-weight-bold p-1 small" style="min-width: 48px; text-align: center;">' + esc(ev.time) + '</div>' +
						'<div class="flex-grow-1" style="font-size: 0.76rem;">' +
							'<div class="font-weight-700 text-dark">' + esc(ev.user) + ' <span class="text-muted font-weight-normal">(' + esc(ev.role) + ')</span></div>' +
							'<div class="text-secondary mt-1">' + esc(ev.text) + '</div>' +
						'</div>' +
					'</div>'
				);
			});
		} else {
			$act.html('<p class="text-muted small text-center py-4">Sin actividad adicional registrada.</p>');
		}

		// Evidencias
		var $ev = $('#panelEvidenceList').empty();
		if (item.evidences && item.evidences.length) {
			item.evidences.forEach(function (file) {
				var fIcon = file.type === 'pdf' ? 'fa-file-pdf text-danger' : (file.type === 'img' ? 'fa-file-image text-primary' : 'fa-file-lines text-secondary');
				$ev.append(
					'<div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light rounded border">' +
						'<div class="d-flex align-items-center gap-2">' +
							'<i class="fa-solid ' + fIcon + ' fa-lg"></i>' +
							'<div>' +
								'<div class="font-weight-700 text-dark small">' + esc(file.name) + '</div>' +
								'<div class="text-muted" style="font-size: 0.68rem;">' + esc(file.size) + ' • ' + esc(file.date) + '</div>' +
							'</div>' +
						'</div>' +
						'<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toastr.info(\'Descargando evidencia...\');"><i class="fa-solid fa-download"></i></button>' +
					'</div>'
				);
			});
		} else {
			$ev.html('<div class="text-center py-4 text-muted small"><i class="fa-solid fa-paperclip fa-2x mb-2 d-block"></i>No hay evidencias adjuntas aún.<br><button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnUploadEvModal"><i class="fa-solid fa-upload mr-1"></i>Adjuntar ahora</button></div>');
		}

		// Productos relacionados
		var $prodBody = $('#panelProductsTableBody').empty();
		if (item.products && item.products.length) {
			item.products.forEach(function (p) {
				$prodBody.append(
					'<tr>' +
						'<td><code>' + esc(p.code) + '</code></td>' +
						'<td><span class="font-weight-600 text-dark">' + esc(p.name) + '</span></td>' +
						'<td>' + esc(p.theor) + '</td>' +
						'<td>' + esc(p.pos) + '</td>' +
						'<td class="text-right font-weight-bold text-danger">' + esc(p.diff) + '</td>' +
					'</tr>'
				);
			});
		} else {
			$prodBody.html('<tr><td colspan="5" class="text-center py-3 text-muted">Esta alerta no tiene productos relacionados.</td></tr>');
		}

		// Acciones recomendadas
		var $actList = $('#panelActionsChecklist').empty();
		if (item.actions && item.actions.length) {
			item.actions.forEach(function (act, idx) {
				var chk = act.done ? 'checked' : '';
				var strike = act.done ? 'style="text-decoration: line-through; color: #94A3B8;"' : 'class="font-weight-600 text-dark"';
				$actList.append(
					'<div class="custom-control custom-checkbox mb-2 p-2 bg-light rounded border">' +
						'<input type="checkbox" class="custom-control-input act-check-item" id="actCheck' + idx + '" ' + chk + '>' +
						'<label class="custom-control-label small" for="actCheck' + idx + '" ' + strike + '>' + esc(act.title) + '</label>' +
					'</div>'
				);
			});
		} else {
			$actList.html('<p class="text-muted small text-center py-4">No hay protocolo de acciones predefinido.</p>');
		}
	}

	// Selección de una alerta para ver su detalle (en móvil abre el panel como hoja a pantalla completa)
	function selectIncident(id, fromUser) {
		var item = findIncident(id);
		if (!item) return;
		state.selectedId = id;
		bindDetailPanel(item);
		// Expediente registrado: bitácora, evidencias y acciones correctivas de la alerta
		Grocy.Api.Get('alerts/' + id, function (d) {
			var ev = (d.events || []).slice().reverse();
			if (ev.length) item.timelineEvents = ev.map(function (e) {
				return { time: String(e.created_at || '').slice(11, 16), user: e.user_name || 'Sistema DAILY', role: String(e.created_at || '').slice(0, 10), text: e.note || e.action };
			});
			item.evidences = (d.evidence || []).map(function (e) {
				return { name: e.original_name || e.file_name, size: Math.max(1, Math.round((e.size || 0) / 1024)) + ' KB', date: String(e.created_at || '').slice(0, 16), type: /^image\//.test(e.mime || '') ? 'img' : 'pdf' };
			});
			var acts = (d.actions || []).map(function (a) { return { title: a.description, done: a.status === 'done' }; });
			if (acts.length) item.actions = acts;
			if (state.selectedId === id) bindDetailPanel(item);
		});
		if (fromUser && window.matchMedia('(max-width: 768px)').matches) {
			$('#dailyDetailPanel').addClass('is-mobile-open').scrollTop(0);
			$('body').addClass('alc-detail-open');
		}
	}

	// Registro de actividad común a todas las acciones del panel
	function logActivity(item, text) {
		var now = new Date(NOW.getTime());
		item.timelineEvents.unshift({ time: pad2(now.getHours()) + ':' + pad2(now.getMinutes()), user: 'Usted', role: 'Sesión actual', text: text });
		item.activity = fmtDateTime(now);
	}

	function setStatus(item, code) {
		var st = STATUS[code] || STATUS.investigating;
		item.status = st.label;
		item.statusCode = code;
		item.statusClass = st.cls;
		item.currentStep = st.step;
		if (code === 'resolved') item.resolvedAt = toDemoIso(NOW);
		else delete item.resolvedAt;
	}

	// ==========================================================================
	// 3. INICIALIZACIÓN DE GRÁFICOS CHART.JS EXACTOS A LA FOTO
	// ==========================================================================

	function drawCharts(statusData, catLabels, catData, days, created, closed, impactDays) {
		if (typeof Chart === 'undefined') return;

		Chart.defaults.global.defaultFontFamily = "'Inter', system-ui, -apple-system, sans-serif";
		Chart.defaults.global.defaultFontColor = '#64748B';
		Chart.defaults.global.defaultFontSize = 11;
		var tip = { backgroundColor: 'rgba(15, 23, 42, 0.94)', titleFontColor: '#FFFFFF', titleFontStyle: 'bold', titleFontSize: 12, bodyFontColor: '#FFFFFF', bodyFontSize: 11, footerFontColor: '#E2E8F0', borderColor: 'rgba(255, 255, 255, 0.15)', borderWidth: 1, cornerRadius: 8, xPadding: 12, yPadding: 10, displayColors: true };
		Object.keys(charts).forEach(function (k) { charts[k].destroy(); });
		charts = {};

		// 1. Donut: estado general de resolución
		var ctx1 = document.getElementById('chartStatusResolution');
		if (ctx1) {
			charts.status = new Chart(ctx1, {
				type: 'doughnut',
				data: { labels: ['Resueltas', 'En proceso', 'Sin actividad', 'Vencidas'], datasets: [{ data: statusData, backgroundColor: ['#008E48', '#F59E0B', '#94A3B8', '#EF4444'], borderWidth: 2, borderColor: '#FFFFFF' }] },
				options: { maintainAspectRatio: false, cutoutPercentage: 76, legend: { display: false }, tooltips: tip }
			});
		}

		// 2. Donut: alertas activas por categoría
		var ctx2 = document.getElementById('chartCategoryAlerts');
		if (ctx2) {
			charts.category = new Chart(ctx2, {
				type: 'doughnut',
				data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: catLabels.map(function (c) { return CAT_COLORS[c] || '#94A3B8'; }), borderWidth: 2, borderColor: '#FFFFFF' }] },
				options: { maintainAspectRatio: false, cutoutPercentage: 76, legend: { display: false }, tooltips: tip }
			});
		}

		// 3. Impacto registrado de las alertas activas, por día de creación (7 días)
		var ctx3 = document.getElementById('chartImpactLossBars');
		if (ctx3) {
			charts.impact = new Chart(ctx3, {
				type: 'bar',
				data: { labels: days, datasets: [{ data: impactDays, backgroundColor: '#EF4444', borderRadius: 3, barPercentage: 0.85 }] },
				options: { maintainAspectRatio: false, legend: { display: false }, tooltips: $.extend({}, tip, { callbacks: { label: function (t) { return money(t.yLabel); } } }), scales: { xAxes: [{ display: false }], yAxes: [{ display: false, ticks: { beginAtZero: true } }] } }
			});
		}

		// 4. Tendencia: creadas y resueltas por día (7 días)
		var ctx4 = document.getElementById('chartAlertsTrend');
		if (ctx4) {
			var line = function (label, data, color, bg) {
				return { label: label, data: data, borderColor: color, backgroundColor: bg, borderWidth: 2, pointRadius: 2, pointHoverRadius: 4, fill: true, lineTension: 0.35 };
			};
			charts.trend = new Chart(ctx4, {
				type: 'line',
				data: { labels: days, datasets: [line('Creadas', created, '#EF4444', 'rgba(239, 68, 68, 0.12)'), line('Resueltas', closed, '#008E48', 'rgba(0, 142, 72, 0.10)')] },
				options: {
					maintainAspectRatio: false, legend: { display: false }, tooltips: $.extend({}, tip, { mode: 'index', intersect: false }),
					scales: { xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10, maxRotation: 0 } }], yAxes: [{ ticks: { beginAtZero: true, precision: 0, fontSize: 10 }, gridLines: { color: '#F1F5F9', drawBorder: false } }] }
				}
			});
		}
	}

	// ==========================================================================
	// 4. VISTA KANBAN Y VISTA CRONOLOGÍA
	// ==========================================================================

	function populateKanban() {
		var stages = {
			detected: $('#kanban-detected').empty(),
			assigned: $('#kanban-assigned').empty(),
			investigating: $('#kanban-investigating').empty(),
			validation: $('#kanban-validation').empty(),
			resolved: $('#kanban-resolved').empty()
		};
		var counts = { detected: 0, assigned: 0, investigating: 0, validation: 0, resolved: 0 };

		INCIDENTS.forEach(function (item) {
			var st = item.statusCode === 'in_progress' ? 'investigating' : (item.statusCode === 'pending' ? 'assigned' : (item.statusCode === 'unassigned' ? 'detected' : item.statusCode));
			if (!stages[st]) st = 'investigating';
			counts[st]++;

			var card = document.createElement('div');
			card.className = 'daily-kanban-card' + (item.id === state.selectedId ? ' is-selected' : '');
			card.setAttribute('data-id', item.id);
			card.innerHTML =
				'<div class="d-flex justify-content-between align-items-center mb-1">' +
					'<span class="daily-inc-id">#' + esc(item.id) + '</span>' +
					'<span class="daily-prio-badge ' + esc(item.prioClass) + '" style="font-size: 0.68rem; padding: 1px 5px;">' + esc(item.priority) + '</span>' +
				'</div>' +
				'<div class="font-weight-700 text-dark small text-truncate">' + esc(item.title) + '</div>' +
				'<div class="d-flex justify-content-between align-items-center mt-2" style="font-size: 0.68rem;">' +
					'<span class="text-muted"><i class="fa-solid fa-store mr-1"></i>' + esc(item.branch) + '</span>' +
					'<span class="font-weight-bold text-danger">' + esc(item.impact) + '</span>' +
				'</div>';
			stages[st].append(card);
		});

		Object.keys(counts).forEach(function (k) {
			$('.daily-kanban-col[data-stage="' + k + '"] .daily-kanban-col-head .badge').text(counts[k]);
		});
	}

	function populateTimeline() {
		var $box = $('#timelineTimelineContent').empty();
		var overdue = 0;
		INCIDENTS.forEach(function (item) {
			var sla = computeSla(item);
			if (sla.state === 'overdue') overdue++;
			var isBad = sla.state === 'overdue';
			var barColor = isBad ? '#EF4444' : (sla.state === 'risk' ? '#F97316' : (sla.state === 'none' || sla.state === 'invalid' ? '#94A3B8' : '#008E48'));
			var pct = (sla.state === 'closed' || isBad) ? 100 : (sla.limit ? Math.min(100, Math.round(sla.elapsed / sla.limit * 100)) : 0);

			$box.append(
				'<div class="mb-3 p-3 bg-light rounded border">' +
					'<div class="d-flex justify-content-between align-items-center mb-1">' +
						'<div>' +
							'<span class="font-weight-bold text-dark">#' + esc(item.id) + ' · ' + esc(item.title) + '</span>' +
							'<span class="badge badge-light border ml-2">' + esc(item.branch) + '</span>' +
						'</div>' +
						'<span class="small font-weight-bold ' + (isBad || sla.state === 'risk' ? 'text-danger' : 'text-success') + '">' + esc(slaShort(sla)) + '</span>' +
					'</div>' +
					'<div class="progress" style="height: 6px; background: #E2E8F0;">' +
						'<div class="progress-bar" style="width: ' + pct + '%; background: ' + barColor + ';"></div>' +
					'</div>' +
					'<div class="d-flex justify-content-between text-muted small mt-1" style="font-size: 0.68rem;">' +
						'<span>Inicio: ' + esc(item.created) + '</span>' +
						'<span>Responsable: ' + esc(item.responsible.name) + '</span>' +
					'</div>' +
				'</div>'
			);
		});
		$('#timelineOverdueBadge').html('<i class="fa-solid fa-triangle-exclamation mr-1"></i>' + overdue + ' fuera de SLA objetivo');
	}

	// ==========================================================================
	// 5. EVENTOS INTERACTIVOS (CLICS, FILTROS, ACCIONES Y MODALES)
	// ==========================================================================

	$(document).ready(function () {
		loadIncidents();

		// Fila de la tabla: abre el detalle. La casilla queda reservada para acciones masivas.
		$(document).on('click', '#dailyIncidentsTable tbody tr[data-id]', function (e) {
			if ($(e.target).closest('.daily-check-cell').length) return;
			selectIncident($(this).attr('data-id'), true);
		});
		$(document).on('keydown', '#dailyIncidentsTable tbody tr[data-id]', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				selectIncident($(this).attr('data-id'), true);
			}
		});

		// Casillas de acciones masivas
		$(document).on('change', '.row-checkbox', function () {
			state.checked[$(this).data('id')] = this.checked;
			updateBulkInfo();
		});
		$('#checkAllRows').on('change', function () {
			var on = this.checked;
			$('.row-checkbox').each(function () {
				this.checked = on;
				state.checked[$(this).data('id')] = on;
			});
			updateBulkInfo();
		});
		$(document).on('click', '#alcBulkClear', function () {
			state.checked = {};
			renderTable();
		});

		// Paginación y tamaño de página
		$(document).on('click', '#dailyPagination .daily-page-btn[data-page]', function () {
			state.page = parseInt($(this).attr('data-page'), 10) || 1;
			renderTable();
			bindDetailPanel(selectedIncident());
		});
		$('#pageSizeSelect').on('change', function () {
			state.pageSize = parseInt(this.value, 10) || 10;
			applyFilters(true);
		});

		// Estados de la tabla: reintentar carga y limpiar filtros
		$(document).on('click', '#alcRetryLoad', function () {
			loadIncidents();
		});
		$(document).on('click', '#alcClearFilters', function () {
			$('#alcTableSearch').val('');
			$('#alcFilterCategory, #alcFilterBranch, #alcFilterStatus').val('');
			$('.daily-filter-pill-btn[data-filter="all"]').trigger('click');
		});

		// Cierre del detalle en móvil
		$(document).on('click', '#panelMobileClose', function () {
			$('#dailyDetailPanel').removeClass('is-mobile-open');
			$('body').removeClass('alc-detail-open');
		});

		// Menú de tres puntos: abre el detalle de esa fila
		$(document).on('click', '.daily-action-dots-btn', function (e) {
			e.stopPropagation();
			selectIncident($(this).data('act-id').toString(), true);
		});

		// Clic en tarjeta Kanban
		$(document).on('click', '.daily-kanban-card', function () {
			var id = $(this).attr('data-id');
			if (findIncident(id)) {
				selectIncident(id, true);
				populateKanban();
				toastr.info('Incidencia #' + id + ' abierta en el panel de seguimiento');
			}
		});

		// Pestañas del Panel Derecho (Resumen / Actividad / Evidencias / Productos / Acciones)
		$(document).on('click', '.daily-detail-tab-btn', function () {
			$('.daily-detail-tab-btn').removeClass('is-active');
			$(this).addClass('is-active');

			var tab = $(this).data('panel-tab');
			$('.daily-panel-tab-content').hide();
			if (tab === 'summary') $('#panelContentSummary').show();
			if (tab === 'activity') $('#panelContentActivity').show();
			if (tab === 'evidence') $('#panelContentEvidence').show();
			if (tab === 'products') $('#panelContentProducts').show();
			if (tab === 'actions') $('#panelContentActions').show();
		});

		// Conmutador de Modos de Vista (Lista / Kanban / Cronología)
		$('.daily-view-mode-btn').on('click', function () {
			$('.daily-view-mode-btn').removeClass('is-active');
			$(this).addClass('is-active');

			var mode = $(this).data('view');
			$('#viewModeList').toggle(mode === 'list');
			$('#viewModeKanban').toggle(mode === 'kanban');
			$('#viewModeTimeline').toggle(mode === 'timeline');
		});

		// Filtros Pill superiores (Todas, Críticas, Vencidas, Sin asignar, En proceso, Resueltas)
		$(document).on('click', '.daily-filter-pill-btn', function () {
			$('.daily-filter-pill-btn').removeClass('is-active');
			$(this).addClass('is-active');
			currentFilter = $(this).data('filter');
			applyFilters(true);
		});

		// Clic en KPI cards superiores filtra la tabla
		$(document).on('click', '.daily-kpi-stat-card[data-filter-tab]', function () {
			var tab = $(this).data('filter-tab');
			$('.daily-filter-pill-btn[data-filter="' + tab + '"]').trigger('click');
		});

		// Búsqueda en vivo y filtros desplegables (vuelven a la primera página)
		$('#alcTableSearch').on('input', function () {
			applyFilters(true);
		});
		$('#alcFilterCategory, #alcFilterBranch, #alcFilterStatus').on('change', function () {
			applyFilters(true);
		});

		// Cambio de estado de la incidencia seleccionada
		$(document).on('click', '[data-set-status]', function (e) {
			e.preventDefault();
			var item = selectedIncident();
			if (!item) return;
			var code = $(this).data('set-status');
			// Transición registrada en el flujo de la alerta (la API valida estado, rol y responsable)
			var map = { investigating: ['investigate', {}], in_progress: ['start', {}], validation: ['request_approval', { note: 'Solicitud de validación desde el Centro de Alertas' }],
				resolved: ['resolve', { note: 'Resuelta desde el Centro de Alertas' }] };
			if (!map[code]) {
				toastr.warning('«Pendiente» se asigna al designar un responsable: use «Asignar».');
				return;
			}
			postAction(item, map[code][0], map[code][1]);
		});

		// ======================================================================
		// 6. MODALES Y ACCIONES FUNCIONALES
		// ======================================================================

		function requireSelected() {
			var item = selectedIncident();
			if (!item) toastr.warning('Selecciona una alerta de la tabla primero');
			return item;
		}

		// Acción sobre la alerta registrada; al terminar se recarga la bandeja desde el servidor
		function postAction(item, action, body, done) {
			Grocy.Api.Post('alerts/' + item.id + '/' + action, body || {},
				function (res) {
					toastr.success((res && res.message) || 'Acción registrada');
					if (done) done();
					loadIncidents();
				},
				function (xhr) {
					toastr.error((xhr && xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo registrar la acción');
				});
		}

		// Botón: Registrar acción
		$('#btnRegisterAction').on('click', function () {
			var item = requireSelected();
			if (!item) return;
			var html =
				'<div class="form-group">' +
					'<label class="font-weight-bold">Incidencia activa</label>' +
					'<input type="text" class="form-control bg-light" value="#' + esc(item.id) + ' — ' + esc(item.title) + '" readonly>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Tipo de acción ejecutada</label>' +
					'<select class="custom-select" id="regActionType">' +
						'<option value="Inspección de campo">Inspección física en tienda</option>' +
						'<option value="Conciliación POS">Conciliación de tickets POS vs Kardex</option>' +
						'<option value="Rotación FEFO">Reubicación por rotación FEFO</option>' +
						'<option value="Ajuste autorizado">Ajuste de inventario autorizado</option>' +
						'<option value="Revisión de cámaras">Revisión de videograbaciones</option>' +
					'</select>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Detalle y observaciones</label>' +
					'<textarea class="form-control" id="regActionNote" rows="3" placeholder="Describe los hallazgos y el resultado obtenido..."></textarea>' +
				'</div>';

			bootbox.dialog({
				title: '<i class="fa-solid fa-pen-to-square text-success mr-2"></i>Registrar acción operativa',
				message: html,
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light' },
					confirm: {
						label: 'Registrar acción',
						className: 'btn-success',
						callback: function () {
							var note = $('#regActionNote').val();
							var type = $('#regActionType').val();
							if (!note) {
								toastr.warning('Por favor ingrese las observaciones');
								return false;
							}
							postAction(item, 'comment', { note: type + ': ' + note });
						}
					}
				}
			});
		});

		// Botón: Adjuntar evidencia
		$(document).on('click', '#btnAttachEvidence, #btnUploadEvModal', function () {
			var item = requireSelected();
			if (!item) return;
			var html =
				'<div class="form-group">' +
					'<label class="font-weight-bold">Seleccionar archivo (Foto, reporte PDF o log)</label>' +
					'<input type="file" class="form-control-file" id="evFileInput" accept="image/*,application/pdf,.txt,.log,.xlsx">' +
					'<small class="text-muted d-block mt-1">En dispositivos móviles activa la cámara fotográfica. Se guardan fotos (JPG, PNG, WEBP, HEIC) y PDF en el expediente de la alerta.</small>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Descripción de la evidencia</label>' +
					'<input type="text" class="form-control" id="evFileDesc" placeholder="Ej. Acta de conteo físico firmada por almacén">' +
				'</div>';

			bootbox.dialog({
				title: '<i class="fa-solid fa-camera text-primary mr-2"></i>Adjuntar evidencia fotográfica o documental',
				message: html,
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light' },
					confirm: {
						label: 'Adjuntar',
						className: 'btn-primary',
						callback: function () {
							var file = ($('#evFileInput')[0] || {}).files;
							file = file && file[0];
							var desc = $('#evFileDesc').val() || (file ? file.name : '');
							if (!file) {
								toastr.warning('Seleccione un archivo');
								return false;
							}
							var fd = new FormData();
							fd.append('file', file);
							fd.append('note', desc);
							$.ajax({ url: Grocy.BaseUrl + '/api/alerts/' + item.id + '/evidence', type: 'POST', data: fd, processData: false, contentType: false })
								.done(function () {
									item.evidences.unshift({ name: file.name, size: Math.max(1, Math.round(file.size / 1024)) + ' KB', date: fmtDateTime(new Date()), type: /^image\//.test(file.type) ? 'img' : 'pdf' });
									toastr.success('Evidencia guardada en el expediente de la alerta #' + item.id);
									refreshAll();
								})
								.fail(function (xhr) {
									toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo guardar la evidencia');
								});
						}
					}
				}
			});
		});

		// Botón: Asignar responsable (alerta seleccionada) o asignación masiva (alertas marcadas)
		$(document).on('click', '#btnAssignResponsible, #alc-btn-bulk-assign, #alcBulkAssign', function (e) {
			e.preventDefault();
			var bulk = this.id !== 'btnAssignResponsible';
			var targets = bulk ? checkedIds().map(findIncident) : [requireSelected()].filter(Boolean);
			if (!targets.length) {
				if (bulk) toastr.warning('Marca una o más alertas con la casilla para asignarlas en bloque');
				return;
			}
			// Usuarios registrados de DAILY
			Grocy.Api.Get('users', function (users) {
				openAssign(targets, bulk, (users || []).map(function (u) {
					return { id: u.id, name: u.display_name || u.username };
				}));
			}, function () { toastr.error('No se pudo cargar la lista de usuarios'); });
		});

		function openAssign(targets, bulk, usersList) {
			var current = targets.length === 1 ? targets[0].responsible.name : '';
			var opts = usersList.map(function (u) {
				return '<option value="' + esc(u.id) + '"' + (u.name === current ? ' selected' : '') + '>' + esc(u.name) + '</option>';
			}).join('');

			var html =
				'<div class="form-group">' +
					'<label class="font-weight-bold">' + (bulk ? 'Asignar ' + targets.length + ' alertas marcadas' : 'Alerta #' + esc(targets[0].id) + ' — ' + esc(targets[0].title)) + '</label>' +
					'<select class="custom-select" id="assignUserSelect">' + opts + '</select>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Instrucciones / Prioridad de atención</label>' +
					'<textarea class="form-control" rows="2" id="assignNote" placeholder="Indica el plazo y pasos clave que debe ejecutar..."></textarea>' +
				'</div>';

			bootbox.dialog({
				title: '<i class="fa-solid fa-user-plus text-primary mr-2"></i>Asignar responsable de resolución',
				message: html,
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light' },
					confirm: {
						label: 'Asignar',
						className: 'btn-primary',
						callback: function () {
							var uid = parseInt($('#assignUserSelect').val(), 10);
							if (!uid) return false;
							var note = $('#assignNote').val();
							var pending = targets.length;
							targets.forEach(function (item) {
								Grocy.Api.Post('alerts/' + item.id + '/assign', { owner_user_id: uid, note: note },
									function () { if (--pending === 0) { toastr.success(targets.length === 1 ? 'Responsable asignado' : targets.length + ' alertas asignadas'); loadIncidents(); } },
									function (xhr) { pending--; toastr.error('#' + item.id + ': ' + ((xhr.responseJSON && xhr.responseJSON.error_message) || 'no se pudo asignar')); });
							});
							if (bulk) state.checked = {};
						}
					}
				}
			});
		}

		// Botón: Crear nueva alerta manual
		$('#alc-btn-create-manual').on('click', function (e) {
			e.preventDefault();
			var html =
				'<div class="form-row">' +
					'<div class="col-md-6 form-group">' +
						'<label class="font-weight-bold">Categoría</label>' +
						'<select class="custom-select" id="newAlertCat">' +
							'<option value="inventory">Inventario</option>' +
							'<option value="waste">Mermas</option>' +
							'<option value="quality">Calidad</option>' +
							'<option value="operations">Operaciones y despachos</option>' +
							'<option value="security">Seguridad</option>' +
							'<option value="sales">Ventas</option>' +
							'<option value="staff">Personal</option>' +
							'<option value="purchasing">Compras</option>' +
						'</select>' +
					'</div>' +
					'<div class="col-md-6 form-group">' +
						'<label class="font-weight-bold">Prioridad</label>' +
						'<select class="custom-select" id="newAlertPrio">' +
							'<option value="P1">Crítica (P1) · SLA 3 h</option>' +
							'<option value="P2">Alta (P2) · SLA 12 h</option>' +
							'<option value="P3" selected>Media (P3) · SLA 24 h</option>' +
							'<option value="P4">Baja (P4) · SLA 48 h</option>' +
						'</select>' +
					'</div>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Título de la incidencia</label>' +
					'<input type="text" class="form-control" id="newAlertTitle" placeholder="Ej. Diferencia de inventario en bodega central">' +
				'</div>' +
				'<div class="form-row">' +
					'<div class="col-md-6 form-group">' +
						'<label class="font-weight-bold">Sucursal</label>' +
						'<select class="custom-select" id="newAlertBranch">' +
							// Mismo catálogo de sucursales que el filtro de la página
							Object.keys(BRANCH_IDS).sort().map(function (n) { return $('<option>').val(BRANCH_IDS[n]).text(n)[0].outerHTML; }).join('') +
						'</select>' +
					'</div>' +
					'<div class="col-md-6 form-group">' +
						'<label class="font-weight-bold">Impacto económico estimado ($)</label>' +
						'<input type="number" step="0.01" class="form-control" id="newAlertImpact" placeholder="0.00">' +
					'</div>' +
				'</div>' +
				'<div class="form-group">' +
					'<label class="font-weight-bold">Descripción detallada</label>' +
					'<textarea class="form-control" id="newAlertDesc" rows="2" placeholder="Detalla productos, lotes o causas preliminares..."></textarea>' +
				'</div>';

			bootbox.dialog({
				title: '<i class="fa-solid fa-triangle-exclamation text-success mr-2"></i>Registrar nueva incidencia operativa',
				message: html,
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light' },
					confirm: {
						label: 'Crear alerta',
						className: 'btn-success',
						callback: function () {
							var title = $('#newAlertTitle').val();
							if (!title) {
								toastr.warning('Por favor ingrese el título de la alerta');
								return false;
							}
							var pCode = $('#newAlertPrio').val();
							var imp = parseFloat($('#newAlertImpact').val());
							Grocy.Api.Post('alerts', {
								title: title, detail: $('#newAlertDesc').val() || null, category: $('#newAlertCat').val(), priority: pCode,
								due_hours: { P1: 3, P2: 12, P3: 24, P4: 48 }[pCode], branch_id: $('#newAlertBranch').val(), value: isNaN(imp) ? null : imp,
								client_token: 'alc-' + Date.now()
							}, function (res) {
								state.selectedId = String(res.id);
								toastr.success(res.message || 'Alerta creada');
								loadIncidents();
							}, function (xhr) {
								toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo crear la alerta');
							});
						}
					}
				}
			});
		});

		// Botón: Escanear reglas de control
		$('#alc-btn-scan-rules').on('click', function (e) {
			e.preventDefault();
			toastr.info('Evaluando las reglas activas con los registros actuales…');
			Grocy.Api.Post('alerts/scan', {}, function (res) {
				toastr.success('Escaneo completado: ' + ((res && res.created) || 0) + ' alerta(s) nueva(s).');
				loadIncidents();
			}, function (xhr) {
				toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo ejecutar el escaneo');
			});
		});

		$('#alcColumnsBtn').on('click', function (e) {
			e.preventDefault();
			toastr.info('La configuración de columnas no está disponible en esta vista.');
		});

		// Reglas de detección y automatizaciones
		var AUTO = null;
		function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
		function errMsg(xhr, def) { return (xhr && xhr.responseJSON && xhr.responseJSON.error_message) || def; }
		var EXEC_BADGE = { success: 'success', skipped: 'secondary', suppressed: 'secondary', deferred: 'warning', retrying: 'warning', failed: 'danger', pending: 'light' };
		var EXEC_LABEL = { success: 'Correcta', skipped: 'Omitida', suppressed: 'Suprimida', deferred: 'Diferida', retrying: 'En reintento', failed: 'Fallida', pending: 'Pendiente' };
		function execRows(list) {
			return (list || []).map(function (x) {
				var res = x.error ? esc(x.error) : (x.result_json ? esc(x.result_json).slice(0, 160) : '');
				var retry = ['failed', 'retrying', 'deferred'].indexOf(x.status) >= 0 ? '<button class="btn btn-sm btn-link text-success p-0 alc-exec-retry" data-id="' + x.id + '">Reintentar</button>' : '';
				return '<tr><td>' + x.id + '</td><td class="text-nowrap">' + esc(x.created_at) + '</td><td>' + esc(x.rule_name) + '</td><td>' + esc((AUTO && AUTO.events[x.event_trigger]) || x.event_trigger) +
					(x.entity_type === 'alert' ? ' · alerta #' + x.entity_id : (x.entity_id ? ' · ' + esc(x.entity_type) + ' #' + x.entity_id : '')) +
					'</td><td>' + esc(x.branch_name || '—') + '</td><td><span class="badge badge-' + (EXEC_BADGE[x.status] || 'light') + '">' + esc(EXEC_LABEL[x.status] || x.status) + '</span>' + (x.attempts > 1 ? ' <span class="text-muted">×' + x.attempts + '</span>' : '') +
					'</td><td class="text-muted" style="max-width:280px">' + res + '</td><td>' + retry + '</td></tr>';
			}).join('') || '<tr><td colspan="8" class="text-muted">Sin ejecuciones registradas.</td></tr>';
		}
		function renderAuto() {
			var branches = {}; AUTO.branches.forEach(function (b) { branches[b.id] = b.name; });
			$('#alcAutoRows').html(AUTO.automations.map(function (r) {
				return '<tr><td><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input alc-auto-toggle" id="alcAt' + r.id + '" data-id="' + r.id + '"' + (r.is_active == 1 ? ' checked' : '') + '><label class="custom-control-label" for="alcAt' + r.id + '"></label></div></td>' +
					'<td><strong>' + esc(r.name) + '</strong> <span class="badge badge-light border">' + esc(r.priority) + '</span><div class="text-muted">' + esc(r.description || '') + '</div></td>' +
					'<td>' + esc(AUTO.events[r.event_trigger] || r.event_trigger) + '<div class="text-monospace text-muted">' + esc(r.condition_json) + '</div></td>' +
					'<td>' + esc(AUTO.actions[r.action_type] || r.action_type) + '</td>' +
					'<td>' + esc(r.branch_id ? branches[r.branch_id] || ('#' + r.branch_id) : 'Todas') + (r.department ? ' · ' + esc(r.department) : '') + (r.active_from ? '<div class="text-muted">' + esc(r.active_from + '–' + r.active_to) + '</div>' : '') + '</td>' +
					'<td class="text-right"><span class="text-success">' + r.runs_ok + '</span>' + (r.runs_failed > 0 ? ' · <span class="text-danger">' + r.runs_failed + ' con falla</span>' : '') + '</td>' +
					'<td class="text-nowrap"><button class="btn btn-sm btn-link text-success p-0 alc-auto-edit" data-id="' + r.id + '">Editar</button></td></tr>';
			}).join('') || '<tr><td colspan="7" class="text-muted">Sin automatizaciones.</td></tr>');
			$('#alcDetectRows').html(AUTO.detection.map(function (r) {
				var th = r.threshold === null ? '<span class="text-muted">—</span>' : '<input type="number" step="any" min="0" class="form-control form-control-sm" style="width:90px" name="threshold" value="' + esc(r.threshold) + '" title="' + esc(r.threshold_label || '') + '">';
				var sev = '<select class="form-control form-control-sm" name="severity">' + [['danger', 'Crítica'], ['warning', 'Atención'], ['info', 'Informativa']].map(function (s) { return '<option value="' + s[0] + '"' + (r.severity === s[0] ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select>';
				return '<tr data-code="' + esc(r.code) + '"><td><input type="checkbox" name="enabled"' + (r.enabled == 1 ? ' checked' : '') + '></td><td><strong>' + esc(r.name) + '</strong><div class="text-muted">' + esc(r.threshold_label || r.description || '') + '</div></td>' +
					'<td>' + th + '</td><td>' + sev + '</td><td><input type="number" min="1" class="form-control form-control-sm" style="width:70px" name="due_hours" value="' + esc(r.due_hours) + '"></td>' +
					'<td><input type="number" min="1" class="form-control form-control-sm" style="width:70px" name="escalate_after_hours" value="' + esc(r.escalate_after_hours || '') + '" placeholder="= plazo"></td>' +
					'<td><input type="number" min="0" class="form-control form-control-sm" style="width:70px" name="cooldown_hours" value="' + esc(r.cooldown_hours) + '"></td>' +
					'<td><button class="btn btn-sm btn-link text-success p-0 alc-detect-save">Guardar</button></td></tr>';
			}).join(''));
			$('#alcExecRows').html(execRows(AUTO.executions));
			var f = $('#alcAutoForm');
			f.find('[name=event_trigger]').html($.map(AUTO.events, function (l, k) { return '<option value="' + k + '">' + esc(l) + '</option>'; }).join(''));
			f.find('[name=action_type]').html($.map(AUTO.actions, function (l, k) { return '<option value="' + k + '">' + esc(l) + '</option>'; }).join(''));
			f.find('[name=branch_id]').html('<option value="">Todas</option>' + AUTO.branches.map(function (b) { return '<option value="' + b.id + '">' + esc(b.name) + '</option>'; }).join(''));
			f.find('[name=owner_user_id]').html('<option value="">El de la alerta o la sucursal</option>' + AUTO.users.map(function (u) { return '<option value="' + u.id + '">' + esc(u.name) + '</option>'; }).join(''));
		}
		function loadAuto() {
			Grocy.Api.Get('alerts/automation', function (res) { AUTO = res; renderAuto(); }, function (xhr) { toastr.error(errMsg(xhr, 'No se pudo cargar la configuración')); });
		}
		function ruleBody(r, over) {
			var b = { name: r.name, description: r.description, event_trigger: r.event_trigger, condition_json: r.condition_json, action_type: r.action_type, action_payload_json: r.action_payload_json,
				priority: r.priority, is_active: r.is_active, branch_id: r.branch_id, department: r.department, owner_user_id: r.owner_user_id, active_from: r.active_from, active_to: r.active_to,
				cooldown_minutes: r.cooldown_minutes, max_retries: r.max_retries };
			return $.extend(b, over || {});
		}
		$('#alc-btn-rules-cfg').on('click', function (e) {
			e.preventDefault();
			$('#alcAutoForm').addClass('d-none');
			$('#alcRulesModal').modal('show');
			loadAuto();
		});
		$('#alcAutoNew').on('click', function () {
			var f = $('#alcAutoForm').removeClass('d-none');
			f[0].reset(); f.find('[name=id]').val(''); f.find('[name=condition_json]').val('{}'); f.find('[name=action_payload_json]').val('{"title": "Atender: {title}", "due_hours": 4}');
			$('#alcAutoChanges').empty();
		});
		$('#alcAutoCancel').on('click', function () { $('#alcAutoForm').addClass('d-none'); });
		$(document).on('click', '.alc-auto-edit', function () {
			var id = $(this).data('id'), r = AUTO.automations.filter(function (x) { return x.id == id; })[0], f = $('#alcAutoForm').removeClass('d-none');
			$.each(ruleBody(r), function (k, v) { if (k !== 'is_active') f.find('[name=' + k + ']').val(v == null ? '' : v); });
			f.find('[name=id]').val(id); f.find('[name=is_active]').prop('checked', r.is_active == 1);
			Grocy.Api.Get('alerts/automation/' + id + '/executions', function (res) {
				$('#alcAutoChanges').html('<div class="font-weight-bold mb-1">Últimas ejecuciones</div><div class="table-responsive"><table class="table table-sm"><tbody>' + execRows(res.executions.slice(0, 10)) + '</tbody></table></div>' +
					'<div class="font-weight-bold mb-1">Historial de cambios</div>' + (res.changes.map(function (c) { return '<div class="text-muted">' + esc(c.created_at) + ' · ' + esc(c.user_name || 'sistema') + ' · ' + esc(Object.keys(JSON.parse(c.changes)).join(', ')) + '</div>'; }).join('') || '<div class="text-muted">Sin cambios.</div>'));
			});
		});
		$('#alcAutoForm').on('submit', function (e) {
			e.preventDefault();
			var f = $(this), id = f.find('[name=id]').val(), body = {};
			f.serializeArray().forEach(function (x) { if (x.name !== 'id') body[x.name] = x.value; });
			body.is_active = f.find('[name=is_active]').is(':checked') ? 1 : 0;
			Grocy.Api.Post('alerts/automation' + (id ? '/' + id : ''), body, function () { toastr.success('Automatización guardada'); f.addClass('d-none'); loadAuto(); },
				function (xhr) { toastr.error(errMsg(xhr, 'No se pudo guardar')); });
		});
		$(document).on('change', '.alc-auto-toggle', function () {
			var cb = $(this), r = AUTO.automations.filter(function (x) { return x.id == cb.data('id'); })[0];
			Grocy.Api.Post('alerts/automation/' + r.id, ruleBody(r, { is_active: cb.is(':checked') ? 1 : 0 }), function () { toastr.success(cb.is(':checked') ? 'Automatización activada' : 'Automatización desactivada'); loadAuto(); },
				function (xhr) { cb.prop('checked', !cb.is(':checked')); toastr.error(errMsg(xhr, 'No se pudo guardar')); });
		});
		$(document).on('click', '.alc-detect-save', function () {
			var tr = $(this).closest('tr'), body = { enabled: tr.find('[name=enabled]').is(':checked') ? 1 : 0, severity: tr.find('[name=severity]').val(), due_hours: tr.find('[name=due_hours]').val(),
				escalate_after_hours: tr.find('[name=escalate_after_hours]').val(), cooldown_hours: tr.find('[name=cooldown_hours]').val() };
			if (tr.find('[name=threshold]').length) body.threshold = tr.find('[name=threshold]').val();
			Grocy.Api.Post('alerts/rules/' + encodeURIComponent(tr.data('code')), body, function () { toastr.success('Regla guardada'); loadAuto(); }, function (xhr) { toastr.error(errMsg(xhr, 'No se pudo guardar la regla')); });
		});
		$(document).on('click', '.alc-exec-retry', function () {
			Grocy.Api.Post('alerts/automation/executions/' + $(this).data('id') + '/retry', {}, function (r) { toastr[r.status === 'success' ? 'success' : 'warning']('Reintento: ' + (EXEC_LABEL[r.status] || r.status) + (r.error ? ' · ' + r.error : '')); loadAuto(); },
				function (xhr) { toastr.error(errMsg(xhr, 'No se pudo reintentar')); });
		});

		// Selector de período: ventana de alertas cerradas para «Resueltas», tiempo de resolución y tendencia de cierres
		function fmtDay(d) { return pad2(d.getDate()) + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }
		function setPeriod(p) {
			var now = new Date(), from;
			if (p === 'today') { from = now; RESOLVED_WINDOW_DAYS = 1; $('#daily-period-label').text('Hoy'); }
			else if (p === '30d') { from = new Date(now.getTime() - 29 * 86400000); RESOLVED_WINDOW_DAYS = 30; $('#daily-period-label').text('Últimos 30 días'); }
			else if (p === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); RESOLVED_WINDOW_DAYS = now.getDate(); $('#daily-period-label').text('Mes en curso'); }
			else { from = new Date(now.getTime() - 6 * 86400000); RESOLVED_WINDOW_DAYS = 7; $('#daily-period-label').text('Últimos 7 días'); }
			$('#daily-period-sub').text(p === 'today' ? fmtDay(now) : fmtDay(from) + ' - ' + fmtDay(now));
		}
		setPeriod('7d');
		$('[data-period]').on('click', function (e) {
			e.preventDefault();
			if ($(this).data('period') === 'custom') toastr.info('El rango personalizado no está disponible en esta vista: se muestran los últimos 7 días.');
			setPeriod($(this).data('period'));
			loadIncidents();
		});

	});

})(jQuery);
