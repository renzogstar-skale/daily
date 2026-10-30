/* DAIRY Etapa 35 — Centro de Control de pérdidas: paleta, gráficos (Chart.js 2), filtros y acciones.
   Todo gráfico: tooltip, clic → detalle (data-drill), exportación vía tabla espejo (DairyUI.exportTables). */
(function ($) {
	'use strict';
	var C = {
		sage: '#6B8F71', sageSoft: 'rgba(107,143,113,.18)', amber: '#C98A2B', amberSoft: 'rgba(201,138,43,.18)',
		red: '#C8553D', redSoft: 'rgba(200,85,61,.16)', graphite: '#3A3F44', stone: '#8A8F94', sand: '#B9A27E', plum: '#7D5A6B', teal: '#4F7F7A'
	};
	var CLASS_COLORS = { operativa: C.red, comercial: C.amber, ajuste: C.stone, averia: C.plum, robo_interno: C.graphite, robo_externo: C.sand, investigacion: C.teal };
	var YEAR_COLORS = [C.stone, C.sand, C.amber, C.red, C.sage, C.graphite];
	var charts = {};

	function money(v) { return v === null || v === undefined ? '—' : '$' + Number(v).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function fmt(v, metric) {
		if (v === null || v === undefined) { return '—'; }
		return metric === 'pct' ? Number(v).toFixed(2) + '%' : metric === 'units' ? Number(v).toLocaleString('es') + ' u.' : money(v);
	}
	function make(id, cfg) {
		var el = document.getElementById(id);
		if (!el || typeof Chart === 'undefined') { return null; }
		if (charts[id]) { charts[id].destroy(); }
		cfg.options = $.extend(true, { responsive: true, maintainAspectRatio: false, animation: { duration: 250 }, legend: { position: 'bottom', labels: { boxWidth: 10, fontSize: 11 } },
			plugins: { colorschemes: { override: false } } }, cfg.options || {});
		charts[id] = new Chart(el.getContext('2d'), cfg);
		var drill = el.getAttribute('data-drill');
		if (drill) {
			el.style.cursor = 'pointer';
			el.onclick = function (evt) {
				var p = charts[id].getElementAtEvent(evt)[0];
				if (!p) { return; }
				var key = (cfg.drillKeys || cfg.data.labels)[p._index];
				window.location.href = drill.replace('{key}', encodeURIComponent(key));
			};
		}
		return charts[id];
	}
	var moneyAxis = function (metric) { return [{ ticks: { beginAtZero: true, callback: function (v) { return fmt(v, metric || 'cost'); }, fontSize: 10 }, gridLines: { color: 'rgba(0,0,0,.05)' } }]; };
	var tip = function (metric) { return { callbacks: { label: function (t, d) { return (d.datasets[t.datasetIndex].label || '') + ': ' + fmt(t.yLabel !== undefined && t.yLabel !== '' ? t.yLabel : t.xLabel, metric); } } }; };

	var L = {
		colors: C, classColors: CLASS_COLORS, money: money, fmt: fmt, charts: charts,

		// Líneas acumuladas (o mensuales) por año
		years: function (id, cmp, mode) {
			var ds = cmp.years.map(function (y, i) {
				var col = YEAR_COLORS[(YEAR_COLORS.length - cmp.years.length + i) % YEAR_COLORS.length];
				return { label: String(y.year), data: mode === 'monthly' ? y.monthly : y.cumulative, borderColor: col, backgroundColor: col, fill: false, lineTension: 0.2, pointRadius: 3, spanGaps: false, borderWidth: i === cmp.years.length - 1 ? 3 : 2 };
			});
			return make(id, { type: 'line', data: { labels: cmp.labels, datasets: ds }, options: { tooltips: $.extend(tip(cmp.metric), { mode: 'index', intersect: false }), scales: { yAxes: moneyAxis(cmp.metric) } } });
		},
		// Barras apiladas por clase (mensual)
		stacked: function (id, labels, byClass, classes) {
			var ds = classes.map(function (c) { return { label: c.label, data: byClass.map(function (m) { return m ? (m[c.key] || 0) : null; }), backgroundColor: CLASS_COLORS[c.key] }; });
			return make(id, { type: 'bar', data: { labels: labels, datasets: ds }, options: { tooltips: $.extend(tip('cost'), { mode: 'index', intersect: false }), scales: { xAxes: [{ stacked: true, gridLines: { display: false } }], yAxes: [$.extend(moneyAxis('cost')[0], { stacked: true })] } } });
		},
		// Pareto: barras de costo + línea de % acumulado (eje derecho)
		pareto: function (id, rows) {
			rows = rows.filter(function (r) { return r.cost > 0; });
			var cfg = { type: 'bar', drillKeys: rows.map(function (r) { return r.code; }), data: { labels: rows.map(function (r) { return r.code + ' ' + r.name; }), datasets: [
				{ type: 'line', label: '% acumulado', data: rows.map(function (r) { return r.cum_pct; }), borderColor: C.graphite, backgroundColor: C.graphite, pointBackgroundColor: C.graphite, pointBorderColor: C.graphite, fill: false, yAxisID: 'p', pointRadius: 3 },
				{ label: 'Costo', data: rows.map(function (r) { return r.cost; }), backgroundColor: rows.map(function (r) { return r.vital ? C.red : C.sand; }), yAxisID: 'c' }
			] }, options: { tooltips: { callbacks: { label: function (t, d) { return t.datasetIndex === 0 ? '% acumulado: ' + t.yLabel + '%' : 'Costo: ' + money(t.yLabel); } } },
				scales: { xAxes: [{ ticks: { fontSize: 9, callback: function (v) { return String(v).split(' ')[0]; } }, gridLines: { display: false } }],
					yAxes: [$.extend(moneyAxis('cost')[0], { id: 'c', position: 'left' }), { id: 'p', position: 'right', ticks: { min: 0, max: 100, callback: function (v) { return v + '%'; }, fontSize: 10 }, gridLines: { display: false } }] } } };
			return make(id, cfg);
		},
		// Barras horizontales (Top 10, distribución por SKU)
		hbar: function (id, labels, values, color, metric, drillKeys) {
			return make(id, { type: 'horizontalBar', drillKeys: drillKeys, data: { labels: labels, datasets: [{ label: metric === 'units' ? 'Unidades' : 'Costo', data: values, backgroundColor: color || C.red }] },
				options: { legend: { display: false }, tooltips: { callbacks: { label: function (t) { return fmt(t.xLabel, metric); } } }, scales: { xAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return fmt(v, metric); }, fontSize: 10 } }], yAxes: [{ ticks: { fontSize: 10, callback: function (v) { v = String(v); return v.length > 26 ? v.slice(0, 25) + '…' : v; } }, gridLines: { display: false } }] } } });
		},
		donut: function (id, labels, values, colors) {
			return make(id, { type: 'doughnut', data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 1 }] },
				options: { cutoutPercentage: 62, tooltips: { callbacks: { label: function (t, d) { var tot = d.datasets[0].data.reduce(function (a, b) { return a + b; }, 0); var v = d.datasets[0].data[t.index]; return d.labels[t.index] + ': ' + money(v) + ' (' + (tot ? (v / tot * 100).toFixed(1) : 0) + '%)'; } } } } });
		},
		bars: function (id, labels, datasets, metric, stacked) {
			return make(id, { type: 'bar', data: { labels: labels, datasets: datasets }, options: { tooltips: $.extend(tip(metric), { mode: 'index', intersect: false }), scales: { xAxes: [{ stacked: !!stacked, gridLines: { display: false }, ticks: { fontSize: 10 } }], yAxes: [$.extend(moneyAxis(metric)[0], { stacked: !!stacked })] } } });
		},

		/* ---- Etapa 36 · Fase 7: gráficos del Censo (mismos datos operativos; clic → transacción o sección) ---- */
		// Merma real (barras) vs. tolerada (línea discontinua) y acumulado % (eje derecho)
		realVsTolerated: function (id, months, hasTarget) {
			var ms = months.filter(function (m) { return !m.future; });
			var ds = [
				{ type: 'line', label: 'Acumulado % ventas', data: ms.map(function (m) { return m.ytd_pct; }), borderColor: C.teal, backgroundColor: C.teal, fill: false, pointRadius: 2, yAxisID: 'p' },
				{ label: 'Merma real', data: ms.map(function (m) { return m.real; }), backgroundColor: ms.map(function (m) { return m.excess > 0 ? C.red : C.sand; }), yAxisID: 'c' }
			];
			if (hasTarget) {
				ds.unshift({ type: 'line', label: 'Tolerada', data: ms.map(function (m) { return m.tolerated; }), borderColor: C.graphite, backgroundColor: C.graphite, fill: false, pointRadius: 2, borderDash: [5, 4], yAxisID: 'c' });
			}
			return make(id, { type: 'bar', data: { labels: ms.map(function (m) { return m.label; }), datasets: ds }, options: {
				tooltips: { mode: 'index', intersect: false, callbacks: { label: function (t, d) { var ds = d.datasets[t.datasetIndex]; return ds.label + ': ' + (ds.yAxisID === 'p' ? fmt(t.yLabel, 'pct') : money(t.yLabel)); } } },
				scales: { xAxes: [{ gridLines: { display: false } }], yAxes: [$.extend(moneyAxis('cost')[0], { id: 'c', position: 'left' }), { id: 'p', position: 'right', ticks: { beginAtZero: true, callback: function (v) { return v + '%'; }, fontSize: 10 }, gridLines: { display: false } }] } } });
		},
		knownUnknown: function (id, split) {
			return L.donut(id, ['Conocida', 'Desconocida'], [Math.max(0, split.known), Math.max(0, split.unknown)], [C.sage, C.teal]);
		},
		// Barras horizontales con clic al detalle; rows: [{label, value, key}]
		ranked: function (id, rows, color, metric, drill) {
			var el = document.getElementById(id);
			if (el && drill) { el.setAttribute('data-drill', drill); }
			return L.hbar(id, rows.map(function (r) { return r.label; }), rows.map(function (r) { return r.value; }), color, metric, rows.map(function (r) { return r.key; }));
		},
		departments: function (id, rows, drill) {
			return L.ranked(id, rows.map(function (d) { return { label: d.department, value: d.cost, key: d.department }; }), C.amber, 'cost', drill);
		},
		adminFreq: function (id, stats, drill) {
			return L.ranked(id, stats.filter(function (s) { return s.total > 0; }).map(function (s) { return { label: s.code + ' ' + s.name, value: s.total, key: s.code }; }), C.amber, 'units', drill);
		},
		lossRecovery: function (id, e) {
			return L.bars(id, ['Pérdida', 'Recuperado', 'Capital perdido', 'Costo prevención', 'Ahorro medido'], [{ label: 'Importe', data: [e.total, e.rec, e.lost, e.prev, e.saved], backgroundColor: [C.red, C.sage, C.graphite, C.amber, C.teal] }], 'cost');
		},
		controlsEffect: function (id, controls) {
			var k = controls.filter(function (c) { return c.saved !== null; });
			return make(id, { type: 'horizontalBar', data: { labels: k.map(function (c) { return c.name; }), datasets: [
				{ label: 'Ahorro medido', data: k.map(function (c) { return c.saved; }), backgroundColor: C.sage },
				{ label: 'Costo', data: k.map(function (c) { return c.cost_to_date; }), backgroundColor: C.amber }
			] }, options: { tooltips: $.extend(tip('cost'), { mode: 'index', intersect: false, callbacks: { label: function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + money(t.xLabel); } } }),
				scales: { xAxes: [{ ticks: { callback: function (v) { return money(v); }, fontSize: 10 } }], yAxes: [{ ticks: { fontSize: 10 }, gridLines: { display: false } }] } } });
		},
		theftTypes: function (id, byType) {
			var keys = Object.keys(byType), col = { interno: C.graphite, externo: C.red, fraude_pos: C.plum, fraude_electronico: C.amber };
			return L.donut(id, keys.map(function (k) { return byType[k].label; }), keys.map(function (k) { return byType[k].cost; }), keys.map(function (k) { return col[k]; }));
		},
		// Tendencia: costo (barras) e incidentes (línea, eje derecho)
		theftTrend: function (id, monthly) {
			var m = Object.keys(monthly);
			return make(id, { type: 'bar', data: { labels: m, datasets: [
				{ type: 'line', label: 'Incidentes', data: m.map(function (k) { return monthly[k].incidents; }), borderColor: C.graphite, backgroundColor: C.graphite, fill: false, yAxisID: 'n', pointRadius: 3 },
				{ label: 'Pérdida', data: m.map(function (k) { return monthly[k].cost; }), backgroundColor: C.red, yAxisID: 'c' }
			] }, options: { tooltips: { mode: 'index', intersect: false, callbacks: { label: function (t, d) { return t.datasetIndex === 0 ? 'Incidentes: ' + t.yLabel : 'Pérdida: ' + money(t.yLabel); } } },
				scales: { xAxes: [{ gridLines: { display: false } }], yAxes: [$.extend(moneyAxis('cost')[0], { id: 'c', position: 'left' }), { id: 'n', position: 'right', ticks: { beginAtZero: true, precision: 0, fontSize: 10 }, gridLines: { display: false } }] } } });
		},
		// Desperdicios y donaciones: costo por destino final
		destinations: function (id, rows) {
			var col = { destruccion: C.red, donacion: C.sage, compostaje: C.teal, alimento_animal: C.sand, devolucion_proveedor: C.amber, reproceso: C.plum, reciclaje: C.stone, sin_registrar: '#C9CDD1' };
			rows = rows.filter(function (r) { return r.cost > 0; });
			return L.donut(id, rows.map(function (r) { return r.label; }), rows.map(function (r) { return r.cost; }), rows.map(function (r) { return col[r.key] || C.stone; }));
		},
		families: function (id, fams) {
			return make(id, { type: 'bar', data: { labels: fams.map(function (f) { return f.label; }), datasets: [
				{ type: 'line', label: '% de ventas', data: fams.map(function (f) { return f.pct_sales; }), borderColor: C.graphite, backgroundColor: C.graphite, fill: false, showLine: false, pointRadius: 5, yAxisID: 'p' },
				{ label: 'Pérdida', data: fams.map(function (f) { return f.cost; }), backgroundColor: C.amber, yAxisID: 'c' }
			] }, options: { tooltips: { mode: 'index', intersect: false, callbacks: { label: function (t, d) { return t.datasetIndex === 0 ? '% de ventas: ' + fmt(t.yLabel, 'pct') : 'Pérdida: ' + money(t.yLabel); } } },
				scales: { xAxes: [{ gridLines: { display: false } }], yAxes: [$.extend(moneyAxis('cost')[0], { id: 'c', position: 'left' }), { id: 'p', position: 'right', ticks: { beginAtZero: true, callback: function (v) { return v + '%'; }, fontSize: 10 }, gridLines: { display: false } }] } } });
		},
		// Detalle de una merma: destino/peso (todos) y, en hurto, modalidad/canal/recuperación
		detailsDialog: function (wasteId, title, html) {
			L.dialog(title, html, 'loss/census/waste/' + wasteId + '/details', 'Detalle guardado');
		},

		// Filtros: GET conservando la pestaña (hash)
		applyFilters: function (form) {
			var q = new URLSearchParams(window.location.search);
			$(form).find('[name]').each(function () { var v = $(this).val(); if (v === null || v === '' || (Array.isArray(v) && !v.length)) { q.delete(this.name); } else { q.set(this.name, Array.isArray(v) ? v.join(',') : v); } });
			window.location.href = window.location.pathname + '?' + q.toString() + window.location.hash;
			return false;
		},
		toggleSheet: function (btn) { $(btn).closest('.dlx-filter-wrap').toggleClass('is-open'); },

		// Acciones (API con rutas sin '/' inicial)
		post: function (url, body, done, btn) { DairyUI.post(url, body, function (r) { done ? done(r) : (toastr.success('Guardado'), dairyRefreshActiveTab()); }, btn); },
		advance: function (id, to, extra, btn) {
			var body = $.extend({ to: to }, extra || {});
			L.post('loss/price-issues/' + id + '/advance', body, function () { toastr.success('Paso registrado: ' + to); dairyRefreshActiveTab(); }, btn);
		},
		advanceDialog: function (id, to, label, html) {
			DairyUI.formDialog(label, html, function (vals, dlg) { L.closeDialog(dlg); L.advance(id, to, vals); }, 'Registrar');
		},
		dialog: function (title, html, url, okMsg) {
			DairyUI.formDialog(title, html, function (vals, dlg) { L.post(url, vals, function () { L.closeDialog(dlg); toastr.success(okMsg || 'Guardado'); dairyRefreshActiveTab(); }); });
		},
		// Bootstrap ignora hide() mientras el modal aún se está abriendo (respuesta rápida): se cierra al terminar de abrir
		closeDialog: function (dlg) {
			var m = dlg.data('bs.modal');
			if (m && m._isTransitioning && !dlg.data('dlx-closing')) { dlg.data('dlx-closing', true).one('shown.bs.modal', function () { dlg.modal('hide'); }); return; }
			dlg.modal('hide');
		}
	};
	window.DairyLoss = L;
})(jQuery);
