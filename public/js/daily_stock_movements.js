/* ==========================================================================
   DAILY — STOCK & MOVIMIENTOS (/stockoverview)
   Pestañas, filtros del encabezado (mismo comportamiento que el Tablero de Ventas), gráficos y detalle filtrado.
   Datos: #smvData (StockMovementsBoardService) y /api/stockoverview/board/*.
   ========================================================================== */
(function () {
	'use strict';

	var FILTER_KEYS = ['branch_id', 'department', 'group_id', 'subcategory', 'product', 'start_date', 'end_date'];
	var DETAIL_KEYS = ['sign', 'status', 'type', 'from', 'date', 'group', 'group_by', 'late', 'all'];
	var MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	var root, data = {}, charts = {}, detailReq = 0, current = { kind: null, params: {} };

	function api(path) { return (window.Grocy && Grocy.BaseUrl ? Grocy.BaseUrl : '') + '/api' + path; }
	function esc(v) { return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function num(v, d) { var n = Number(v || 0); return n.toLocaleString('en-US', { minimumFractionDigits: d || 0, maximumFractionDigits: d === undefined ? 2 : d }); }
	function money(v) { var n = Number(v || 0); return (n < 0 ? '−' : '') + '$' + num(Math.abs(n), 2); }
	function dayLabel(iso) { var p = String(iso).split('-'); return parseInt(p[2], 10) + ' ' + MONTHS[parseInt(p[1], 10) - 1]; }
	function extend(a, b) { var o = {}; [a, b].forEach(function (s) { Object.keys(s || {}).forEach(function (k) { o[k] = s[k]; }); }); return o; }
	function filterQs(extra) {
		var src = new URLSearchParams(window.location.search), qs = new URLSearchParams();
		FILTER_KEYS.forEach(function (k) { if (src.get(k)) qs.set(k, src.get(k)); });
		Object.keys(extra || {}).forEach(function (k) { if (extra[k] !== '' && extra[k] !== null && extra[k] !== undefined) qs.set(k, extra[k]); });
		return qs.toString();
	}

	var TOOLTIP = {
		backgroundColor: 'rgba(15, 23, 42, 0.94)', titleFontColor: '#ffffff', titleFontStyle: 'bold', titleFontSize: 12,
		bodyFontColor: '#ffffff', bodyFontSize: 11, borderColor: 'rgba(255, 255, 255, 0.15)', borderWidth: 1, cornerRadius: 8, xPadding: 10, yPadding: 10
	};
	var GRID_Y = { color: 'rgba(226, 232, 240, 0.8)', borderDash: [3, 3], drawBorder: false, zeroLineColor: 'rgba(148, 163, 184, 0.6)' };
	var TICK = { fontColor: '#64748b', fontSize: 10.5 };
	function pointer(e, els) { if (e && e.target) e.target.style.cursor = els.length ? 'pointer' : 'default'; }

	/* ---------------------------------------------------------------- Pestañas y URL */

	function showTab(tab, opts) {
		opts = opts || {};
		root.querySelectorAll('.smv-pane').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === tab); });
		root.querySelectorAll('.smv-tab').forEach(function (b) {
			var on = b.getAttribute('data-smv-tab') === tab && (tab !== 'detalle' || b.getAttribute('data-detail') === (opts.kind || current.kind));
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		root.querySelectorAll('.smv-top-tab').forEach(function (b) {
			var bTab = b.getAttribute('data-smv-tab');
			var on = (bTab === tab)
				|| (bTab === 'lotes' && tab === 'existencias')
				|| (bTab === 'almacenes' && tab === 'operaciones')
				|| (bTab === 'movimientos' && tab === 'detalle' && (opts.kind || current.kind) === 'movements')
				|| (bTab === 'conteos' && tab === 'detalle' && (opts.kind || current.kind) === 'counts');
			b.classList.toggle('on', on);
		});
		var url = new URL(window.location.href);
		DETAIL_KEYS.concat(['detail']).forEach(function (k) { url.searchParams.delete(k); });
		if (tab === 'resumen') url.searchParams.delete('tab'); else url.searchParams.set('tab', tab);
		if (tab === 'detalle' && opts.kind) {
			url.searchParams.set('detail', opts.kind);
			Object.keys(opts.params || {}).forEach(function (k) { if (opts.params[k] !== '' && opts.params[k] !== undefined) url.searchParams.set(k, opts.params[k]); });
		}
		if (tab !== 'operaciones' && url.hash.indexOf('#inv-ops') === 0) url.hash = '';
		history.replaceState(null, '', url.toString());
		// Gráficos de pestañas ocultas: Chart.js recalcula su tamaño al hacerse visibles
		setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 30);
		if (!opts.noScroll) window.scrollTo({ top: Math.max(0, root.getBoundingClientRect().top + window.pageYOffset - 70), behavior: 'smooth' });
	}

	function openDetail(kind, params) {
		current = { kind: kind, params: params || {} };
		showTab('detalle', { kind: kind, params: current.params });
		loadDetail();
	}

	/* ---------------------------------------------------------------- Detalle filtrado */

	var STATUS_CLASS = { 'Por conciliar': 'is-red', 'Por aprobar': 'is-yellow', 'Ajuste confirmado': 'is-green', 'Sin diferencia': 'is-green', 'Recepción parcial': 'is-yellow' };
	var LINK_LABEL = { '/inventory': 'Abrir Conteos físicos ', '/kardex': 'Abrir Kardex ', '/transfers': 'Abrir Traslados ', '/mrp': 'Abrir Compras ', '/inventory/stockouts': 'Abrir Productos agotados ' };

	function cell(col, row) {
		var key = col[0], v = row[key];
		if (v === null || v === undefined || v === '') return '—';
		if (key === 'status') {
			var cls = STATUS_CLASS[v] || (/atrasado/.test(v) ? 'is-red' : (/parcial|tránsito/i.test(v) ? 'is-yellow' : ''));
			return '<span class="smv-status ' + cls + '">' + esc(v) + '</span>';
		}
		if (key === 'value') return '<span class="' + (v < 0 ? 'smv-neg' : (v > 0 ? 'smv-pos' : '')) + '">' + money(v) + '</span>';
		if (key === 'difference' || key === 'qty') {
			var s = (v > 0 ? '+' : (v < 0 ? '−' : '')) + num(Math.abs(v), Math.abs(v - Math.round(v)) > 0 ? 3 : 0);
			return '<span class="' + (v < 0 ? 'smv-neg' : (v > 0 ? 'smv-pos' : '')) + '">' + s + (row.unit ? ' <small class="text-muted">' + esc(row.unit) + '</small>' : '') + '</span>';
		}
		if (typeof v === 'number') return num(v, Math.abs(v - Math.round(v)) > 0 ? 2 : 0);
		if (key === 'sku') return '<span class="ded-sku-badge">' + esc(v) + '</span>';
		return esc(v);
	}

	function loadDetail() {
		var kind = current.kind, params = current.params, seq = ++detailReq;
		var table = document.getElementById('smvDetailTable');
		var sign = document.getElementById('smvDetailSign'), type = document.getElementById('smvDetailType');
		sign.hidden = kind !== 'counts';
		type.hidden = kind !== 'movements';
		if (kind === 'counts') sign.value = params.status === 'discrepancy' ? 'discrepancy' : (params.sign || 'diff');
		if (kind === 'movements') type.value = params.type || '';
		document.getElementById('smvDetailTitle').textContent = 'Cargando…';
		document.getElementById('smvDetailMeta').textContent = '';
		table.innerHTML = '<tbody><tr><td class="smv-detail-loading">Cargando detalle…</td></tr></tbody>';
		fetch(api('/stockoverview/board/detail/' + encodeURIComponent(kind) + '?' + filterQs(params)), { credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error_message || r.statusText); return j; }); })
			.then(function (d) {
				if (seq !== detailReq) return;
				var scope = root.querySelector('.smv-scope');
				document.getElementById('smvDetailTitle').textContent = d.title;
				document.getElementById('smvDetailMeta').textContent = num(d.total) + ' registros' + (d.total > d.rows.length ? ' · se muestran ' + num(d.rows.length) : '') + (scope ? ' · ' + scope.textContent.trim() : '');
				var note = document.getElementById('smvDetailNote');
				note.hidden = !d.note;
				note.textContent = d.note || '';
				var link = document.getElementById('smvDetailLink');
				link.hidden = !d.link;
				if (d.link) {
					link.href = (window.Grocy ? Grocy.BaseUrl : '') + d.link;
					link.firstChild.textContent = LINK_LABEL[d.link] || 'Abrir módulo ';
				}
				if (!d.rows.length) {
					table.innerHTML = '<tbody><tr><td class="smv-detail-loading">Sin registros para los filtros aplicados</td></tr></tbody>';
					return;
				}
				var head = '<thead><tr>' + d.columns.map(function (c) { return '<th class="' + (c[2] === 'r' ? 'is-r' : '') + '">' + esc(c[1]) + '</th>'; }).join('') + '</tr></thead>';
				var body = '<tbody>' + d.rows.map(function (r) {
					return '<tr>' + d.columns.map(function (c) { return '<td class="' + (c[2] === 'r' ? 'is-r' : '') + '">' + cell(c, r) + '</td>'; }).join('') + '</tr>';
				}).join('') + '</tbody>';
				table.innerHTML = head + body;
			})
			.catch(function (e) {
				if (seq !== detailReq) return;
				document.getElementById('smvDetailTitle').textContent = 'Detalle no disponible';
				table.innerHTML = '<tbody><tr><td class="smv-detail-loading">' + esc(e.message) + '</td></tr></tbody>';
			});
	}

	/* ---------------------------------------------------------------- Gráficos (Chart.js 2) */

	function qtyText(q) { return num(q.u, 2) + ' u' + (q.kg ? ' · ' + num(q.kg, 2) + ' kg' : '') + (q.L ? ' · ' + num(q.L, 2) + ' L' : ''); }

	function initDifferences() {
		var c = document.getElementById('smvChartDiff');
		if (!c || !data.differences) return;
		var g = data.differences.groups;
		charts.diff = new Chart(c, {
			type: 'bar',
			data: {
				labels: g.map(function (x) { return x.label; }),
				datasets: [
					{ label: 'Faltantes', data: g.map(function (x) { return x.short_value; }), backgroundColor: '#ef4444', hoverBackgroundColor: '#dc2626', maxBarThickness: 16 },
					{ label: 'Sobrantes', data: g.map(function (x) { return x.over_value; }), backgroundColor: '#10b981', hoverBackgroundColor: '#059669', maxBarThickness: 16 }
				]
			},
			options: {
				responsive: true, maintainAspectRatio: false, legend: { display: false },
				tooltips: extend(TOOLTIP, { mode: 'index', intersect: false, callbacks: {
					label: function (t) {
						var x = g[t.index], side = t.datasetIndex === 0 ? 'short' : 'over';
						return (side === 'short' ? 'Faltantes: −$' : 'Sobrantes: +$') + num(t.yLabel, 2) + ' · ' + x[side + '_lines'] + ' líneas · ' + qtyText(x[side + '_qty']);
					}
				} }),
				scales: {
					xAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: extend(TICK, { autoSkip: false, maxRotation: 0, callback: function (v) { return String(v).length > 14 ? String(v).slice(0, 13) + '…' : v; } }) }],
					yAxes: [{ gridLines: GRID_Y, ticks: extend(TICK, { beginAtZero: true, callback: function (v) { return '$' + num(v, 0); } }) }]
				},
				onClick: function (e) {
					var el = charts.diff.getElementAtEvent(e)[0];
					if (!el) return;
					openDetail('counts', { sign: el._datasetIndex === 1 ? 'over' : 'short', group_by: data.differences.by, group: g[el._index].key });
				},
				hover: { onHover: pointer }
			}
		});
	}

	function initFlows() {
		var c = document.getElementById('smvChartFlows');
		if (!c || !data.flows) return;
		var f = data.flows, s = f.series;
		var defs = [
			['receptions', 'Recepciones', '#10b981'], ['transfers_in', 'Traslados (entrada)', '#6ee7b7'], ['returns', 'Devoluciones', '#0d9488'], ['adjustments_in', 'Ajustes (+)', '#eab308'],
			['sales', 'Ventas POS', '#94a3b8'], ['dispatches', 'Despachos', '#64748b'], ['waste', 'Mermas', '#ef4444'], ['transfers_out', 'Traslados (salida)', '#facc15'], ['adjustments_out', 'Ajustes (−)', '#f59e0b']
		];
		var bucketOf = { adjustments_in: 'adjustments', adjustments_out: 'adjustments' };
		charts.flows = new Chart(c, {
			type: 'bar',
			data: {
				labels: f.labels.map(dayLabel),
				datasets: defs.map(function (d) { return { label: d[1], data: s[d[0]], backgroundColor: d[2], stack: 'u', maxBarThickness: 12, _key: d[0] }; })
			},
			options: {
				responsive: true, maintainAspectRatio: false, legend: { display: false },
				tooltips: extend(TOOLTIP, { mode: 'index', intersect: false,
					filter: function (t) { return Number(t.yLabel) !== 0; },
					callbacks: {
						title: function (items) { return items.length ? f.labels[items[0].index] + ' · piezas' : ''; },
						label: function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + (t.yLabel > 0 ? '+' : '−') + num(Math.abs(t.yLabel), 2) + ' u'; }
					}
				}),
				scales: {
					xAxes: [{ stacked: true, gridLines: { display: false, drawBorder: false }, ticks: extend(TICK, { maxRotation: 0, autoSkip: true, maxTicksLimit: 7 }) }],
					yAxes: [{ stacked: true, gridLines: GRID_Y, ticks: extend(TICK, { callback: function (v) { return num(v, 0); } }) }]
				},
				onClick: function (e) {
					var el = charts.flows.getElementAtEvent(e)[0];
					if (!el) return;
					var key = charts.flows.data.datasets[el._datasetIndex]._key;
					openDetail('movements', { date: f.labels[el._index], type: bucketOf[key] || key });
				},
				hover: { onHover: pointer }
			}
		});
	}

	function seriesData(sd) {
		return {
			labels: sd.labels.map(dayLabel),
			datasets: [
				{ label: 'Stock', data: sd.stock, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.12)', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: true, lineTension: 0.25 },
				{ label: 'Mínimo', data: sd.min, borderColor: '#eab308', backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 4], pointRadius: 0, fill: false }
			]
		};
	}

	function initSeries() {
		var c = document.getElementById('smvChartSeries');
		if (!c || !data.series) return;
		var sd = data.series;
		charts.series = new Chart(c, {
			type: 'line',
			data: seriesData(sd),
			options: {
				responsive: true, maintainAspectRatio: false, legend: { display: false },
				tooltips: extend(TOOLTIP, { mode: 'index', intersect: false, callbacks: {
					title: function (items) { return items.length ? sd.labels[items[0].index] + ' · ' + sd.sku : ''; },
					label: function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + num(t.yLabel, 2) + ' ' + sd.unit; }
				} }),
				scales: {
					xAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: extend(TICK, { maxRotation: 0, autoSkip: true, maxTicksLimit: 6 }) }],
					yAxes: [{ gridLines: GRID_Y, ticks: extend(TICK, { beginAtZero: true, callback: function (v) { return num(v, 0); } }) }]
				}
			}
		});
		var sel = document.getElementById('smvSeriesSelect');
		if (sel) sel.addEventListener('change', function () {
			var p = this.value.split(':');
			fetch(api('/stockoverview/board/series?' + filterQs({ series_branch: p[0], series_product: p[1] })), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					if (!d || !d.labels) return;
					sd = d;
					charts.series.data = seriesData(d);
					charts.series.update();
				});
		});
	}

	function initCompliance() {
		var c = document.getElementById('smvChartCompliance');
		if (!c || !data.compliance) return;
		var cp = data.compliance;
		charts.compliance = new Chart(c, {
			type: 'line',
			data: {
				labels: cp.labels.map(dayLabel),
				datasets: [
					{ label: 'Completados', data: cp.completed, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.14)', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: true, lineTension: 0.2 },
					{ label: 'Programados', data: cp.scheduled, borderColor: '#94a3b8', backgroundColor: 'transparent', borderWidth: 2, borderDash: [4, 4], pointRadius: 0, fill: false, lineTension: 0.2 }
				]
			},
			options: {
				responsive: true, maintainAspectRatio: false,
				legend: { display: true, position: 'bottom', labels: { boxWidth: 10, fontSize: 10.5, fontColor: '#64748b' } },
				tooltips: extend(TOOLTIP, { mode: 'index', intersect: false, callbacks: {
					title: function (items) { return items.length ? 'Acumulado al ' + cp.labels[items[0].index] : ''; },
					label: function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + num(t.yLabel, 0); },
					footer: function (items) { var s = cp.scheduled[items[0].index], k = cp.completed[items[0].index]; return s ? 'Cumplimiento: ' + num(Math.min(k, s) / s * 100, 1) + ' %' : ''; }
				} }),
				scales: {
					xAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: extend(TICK, { maxRotation: 0, autoSkip: true, maxTicksLimit: 6 }) }],
					yAxes: [{ gridLines: GRID_Y, ticks: extend(TICK, { beginAtZero: true, precision: 0 }) }]
				},
				onClick: function () { openDetail('counts', { sign: 'all' }); }
			}
		});
	}

	function initTypes() {
		var c = document.getElementById('smvChartTypes');
		if (!c || !data.types) return;
		var t = data.types, total = t.reduce(function (a, x) { return a + x.count; }, 0);
		charts.types = new Chart(c, {
			type: 'doughnut',
			data: { labels: t.map(function (x) { return x.label; }), datasets: [{ data: t.map(function (x) { return x.count; }), backgroundColor: t.map(function (x) { return x.color; }), borderWidth: 2, borderColor: '#ffffff' }] },
			options: {
				responsive: true, maintainAspectRatio: false, cutoutPercentage: 72, legend: { display: false },
				tooltips: extend(TOOLTIP, { callbacks: { label: function (it) {
					var x = t[it.index];
					return x.label + ': ' + num(x.count) + ' mov. (' + (total ? num(x.count / total * 100, 1) : '0') + ' %) · ' + qtyText(x.qty);
				} } }),
				onClick: function (e) { var el = charts.types.getElementAtEvent(e)[0]; if (el) openDetail('movements', { type: t[el._index].key }); },
				hover: { onHover: pointer }
			}
		});
	}

	/* ---------------------------------------------------------------- Filtros del encabezado (como Ventas) */

	function initDropdowns() {
		document.addEventListener('click', function (e) {
			if (!e.target.closest('.ded-dropdown-container')) root.querySelectorAll('.ded-dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); });
		});
		root.querySelectorAll('.ded-dropdown-toggle').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var menu = this.closest('.ded-dropdown-container').querySelector('.ded-dropdown-menu');
				var open = menu.classList.contains('show');
				root.querySelectorAll('.ded-dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); });
				if (!open) menu.classList.add('show');
			});
		});
		function go(set) {
			var url = new URL(window.location.href);
			Object.keys(set).forEach(function (k) { if (set[k]) url.searchParams.set(k, set[k]); else url.searchParams.delete(k); });
			window.location.href = url.toString();
		}
		root.querySelectorAll('[data-select-branch]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); go({ branch_id: this.getAttribute('data-select-branch') }); }); });
		root.querySelectorAll('[data-select-dept]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); go({ department: this.getAttribute('data-select-dept') }); }); });
		root.querySelectorAll('[data-select-range]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var r = this.getAttribute('data-select-range'), t = new Date(), from, to;
				var fmt = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
				to = fmt(t);
				if (r === '30d') from = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - 29));
				else if (r === 'today') from = to;
				else if (r === 'yesterday') from = to = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - 1));
				else if (r === 'week') from = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - ((t.getDay() + 6) % 7)));
				else if (r === 'month') from = fmt(new Date(t.getFullYear(), t.getMonth(), 1));
				else if (r === 'prev_month') { from = fmt(new Date(t.getFullYear(), t.getMonth() - 1, 1)); to = fmt(new Date(t.getFullYear(), t.getMonth(), 0)); }
				if (from && to) go({ start_date: from, end_date: to });
			});
		});
	}

	/* ---------------------------------------------------------------- Inicio */

	function init() {
		root = document.getElementById('stockMovementsBoard');
		if (!root) return;
		try { data = JSON.parse((document.getElementById('smvData') || {}).textContent || '{}'); } catch (e) { data = {}; }
		if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
		initDropdowns();

		// Abrir detalle desde tarjetas, filas, alertas y botones (data-smv-open + data-params)
		root.addEventListener('click', function (e) {
			var el = e.target.closest('[data-smv-open]');
			if (el && root.contains(el)) {
				e.preventDefault();
				e.stopPropagation();
				var p = {};
				try { p = JSON.parse(el.getAttribute('data-params') || '{}'); } catch (x) { p = {}; }
				openDetail(el.getAttribute('data-smv-open'), p);
				return;
			}
			var tab = e.target.closest('[data-smv-tab]');
			if (tab && root.contains(tab)) {
				e.preventDefault();
				var tName = tab.getAttribute('data-smv-tab');
				var kind = tab.getAttribute('data-detail');
				if (tName === 'movimientos') {
					openDetail('movements', {});
				} else if (tName === 'conteos') {
					openDetail('counts', { sign: 'diff' });
				} else if (tName === 'lotes') {
					showTab('existencias');
				} else if (tName === 'almacenes') {
					showTab('operaciones');
				} else if (tName === 'detalle' && kind) {
					openDetail(kind, kind === 'counts' ? { sign: 'diff' } : {});
				} else {
					showTab(tName);
				}
			}
		});
		root.querySelectorAll('.ded-kpi-card[data-smv-open]').forEach(function (c) {
			c.setAttribute('tabindex', '0');
			c.setAttribute('role', 'button');
			c.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); c.click(); } });
		});
		document.getElementById('smvDetailSign').addEventListener('change', function () {
			current.params = this.value === 'discrepancy' ? { status: 'discrepancy' } : extend(current.params, { sign: this.value, status: '' });
			showTab('detalle', { kind: 'counts', params: current.params, noScroll: true });
			loadDetail();
		});
		document.getElementById('smvDetailType').addEventListener('change', function () {
			current.params = extend(current.params, { type: this.value });
			showTab('detalle', { kind: 'movements', params: current.params, noScroll: true });
			loadDetail();
		});

		if (typeof Chart !== 'undefined') {
			[initDifferences, initFlows, initSeries, initCompliance, initTypes].forEach(function (fn) { try { fn(); } catch (err) { console.error('Stock & Movimientos:', err); } });
		}

		// Estado inicial: ?tab=detalle&detail=… (enlace compartido) o #inv-ops (enlaces existentes a la operación de inventario)
		var q = new URLSearchParams(window.location.search);
		if (window.location.hash.indexOf('#inv-ops') === 0) {
			showTab('operaciones', { noScroll: true });
		} else if (q.get('tab') === 'detalle' && q.get('detail')) {
			var params = {};
			DETAIL_KEYS.forEach(function (k) { if (q.get(k)) params[k] = q.get(k); });
			current = { kind: q.get('detail'), params: params };
			showTab('detalle', { kind: current.kind, params: params, noScroll: true });
			loadDetail();
		}
		window.addEventListener('hashchange', function () { if (window.location.hash.indexOf('#inv-ops') === 0) showTab('operaciones', { noScroll: true }); });
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
