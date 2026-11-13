/**
 * DAILY — Informes comerciales de Ventas y Rentabilidad (views/components/sales/sales_reports.blade.php)
 * Dibuja los gráficos de cada sección solo cuando su pestaña está visible, maneja la selección de departamento y
 * sucursal, ordena y pagina tablas y exporta cada informe a Excel (.xlsx) y PDF con los mismos datos de pantalla.
 */
(function () {
	'use strict';

	var D = null;
	var built = {};
	var charts = {};
	var GREEN = '#10b981', GREEN_D = '#059669', GRAY = '#94a3b8', AMBER = '#f59e0b', RED = '#ef4444';
	var PALETTE = ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6', '#ec4899', '#64748b', '#eab308', '#2563eb'];
	var MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	var NA = 'Sin información';

	/* ------------------------------------------------------------------ formato */
	function money(v, dec) {
		if (v === null || v === undefined) return NA;
		return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0 });
	}
	function short(v) {
		var n = Number(v || 0), a = Math.abs(n);
		if (a >= 1e6) return '$' + (n / 1e6).toFixed(1) + 'M';
		if (a >= 1e3) return '$' + Math.round(n / 1e3) + 'K';
		return '$' + Math.round(n);
	}
	function pct(v) { return v === null || v === undefined ? NA : Number(v).toFixed(1) + ' %'; }
	function signed(v, suffix) {
		if (v === null || v === undefined) return '<span class="ded-text-muted-italics">' + NA + '</span>';
		return v >= 0 ? '<span class="ded-kpi-badge-green">▲ +' + v.toFixed(1) + (suffix || ' %') + '</span>' : '<span class="ded-pill-red">▼ ' + v.toFixed(1) + (suffix || ' %') + '</span>';
	}
	function dayLabel(iso) { var p = String(iso).split('-'); return parseInt(p[2], 10) + ' ' + MONTHS[parseInt(p[1], 10) - 1]; }
	function monthLabel(ym) { var p = String(ym).split('-'); return MONTHS[parseInt(p[1], 10) - 1] + ' ' + p[0].slice(2); }
	function cut(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
	function esc(s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function alpha(hex, a) {
		var h = hex.replace('#', '');
		return 'rgba(' + parseInt(h.substr(0, 2), 16) + ',' + parseInt(h.substr(2, 2), 16) + ',' + parseInt(h.substr(4, 2), 16) + ',' + a + ')';
	}

	/* ------------------------------------------------------------------ Chart.js (mismo estilo que el Tablero de Ventas + contraste suave claro/oscuro) */
	function isDark() { return document.documentElement.classList.contains('daily-dark'); }
	function gridCfg() {
		var c = isDark() ? 'rgba(148, 163, 184, 0.14)' : 'rgba(226, 232, 240, 0.8)';
		return { color: c, borderDash: [3, 3], drawBorder: false, zeroLineColor: c };
	}
	function tickCfg() { return { fontColor: isDark() ? '#94a3b8' : '#64748b', fontSize: 10.5 }; }
	function donutBorder() { return isDark() ? '#1e293b' : '#ffffff'; }
	var TOOLTIP = { mode: 'index', intersect: false, backgroundColor: 'rgba(15, 23, 42, 0.94)', titleFontColor: '#ffffff', titleFontStyle: 'bold', titleFontSize: 12, bodyFontColor: '#ffffff', bodyFontSize: 11, footerFontColor: '#e2e8f0', borderColor: 'rgba(255, 255, 255, 0.15)', borderWidth: 1, cornerRadius: 8, xPadding: 12, yPadding: 10, displayColors: true };
	var GRID = gridCfg();
	var TICK = tickCfg();

	function tip(fmt, extra) {
		var t = Object.assign({}, TOOLTIP, extra || {});
		t.callbacks = Object.assign({ label: function (it, data) { var ds = data.datasets[it.datasetIndex]; var v = ds.data[it.index]; return (ds.label ? ds.label + ': ' : '') + (fmt || money)(v && typeof v === 'object' ? v.y : v); } }, (extra && extra.callbacks) || {});
		return t;
	}
	function xCat(opts) { return [Object.assign({ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({ autoSkip: true, maxRotation: 0, autoSkipPadding: 10 }, tickCfg()) }, opts || {})]; }
	function yMoney(opts) { return [Object.assign({ gridLines: gridCfg(), ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: short }, tickCfg()) }, opts || {})]; }
	function base(type, data, options) {
		return { type: type, data: data, options: Object.assign({ responsive: true, maintainAspectRatio: false, animation: { duration: 350 }, legend: { display: false } }, options) };
	}
	function make(id, cfg) {
		var c = document.getElementById(id);
		if (!c || typeof Chart === 'undefined') return null;
		if (charts[id]) charts[id].destroy();
		charts[id] = new Chart(c, cfg);
		return charts[id];
	}
	function legendOn() { return { display: true, position: 'bottom', labels: { boxWidth: 10, fontSize: 10.5, fontColor: isDark() ? '#cbd5e1' : '#475569', padding: 10 } }; }
	function hbar(id, labels, values, colors, fmt, full) {
		return make(id, base('horizontalBar', { labels: labels, datasets: [{ data: values, backgroundColor: colors, hoverBackgroundColor: colors, maxBarThickness: 16 }] }, {
			tooltips: tip(fmt, { mode: 'nearest', intersect: true, callbacks: { title: function (it) { return full ? full[it[0].index] : it[0].label; } } }),
			scales: { xAxes: [{ gridLines: gridCfg(), ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: fmt === pct ? function (v) { return v + ' %'; } : short }, tickCfg()) }], yAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({}, tickCfg(), { fontSize: 10 }) }] }
		}));
	}

	/* ------------------------------------------------------------------ 1. Tablero de ventas */
	function buildDashboard() {
		var d = D.dashboard;
		var trend = function (n) {
			var days = d.trend90.days.slice(-n), net = d.trend90.net.slice(-n);
			var lastSale = -1; net.forEach(function (v, i) { if (v > 0) lastSale = i; });
			make('srepDashTrend', base('bar', { labels: days.map(dayLabel), datasets: [{ label: 'Ventas netas', data: net, backgroundColor: net.map(function (v, i) { return i === lastSale ? GREEN : alpha(GRAY, 0.42); }), hoverBackgroundColor: net.map(function (v, i) { return i === lastSale ? GREEN_D : alpha(GRAY, 0.7); }), maxBarThickness: 13 }] },
				{ tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
		};
		trend(30);
		document.querySelectorAll('[data-srep-window]').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('[data-srep-window]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				trend(parseInt(b.getAttribute('data-srep-window'), 10));
			});
		});

		// Curva por hora: solo el rango de horas con ventas
		var first = d.hourly.net.findIndex(function (v) { return v > 0; });
		var last = 23 - d.hourly.net.slice().reverse().findIndex(function (v) { return v > 0; });
		if (first < 0) { first = 8; last = 20; }
		var ctx = document.getElementById('srepDashHourly');
		if (ctx) {
			var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 190);
			g.addColorStop(0, 'rgba(16, 185, 129, 0.32)');
			g.addColorStop(1, 'rgba(16, 185, 129, 0)');
			make('srepDashHourly', base('line', { labels: d.hourly.labels.slice(first, last + 1), datasets: [
				{ label: 'Ventas netas', yAxisID: 'net', data: d.hourly.net.slice(first, last + 1), borderColor: GREEN, backgroundColor: g, borderWidth: 2.2, pointRadius: 2.5, pointBackgroundColor: GREEN, pointBorderColor: '#fff', tension: 0.35, fill: true },
				{ label: 'Transacciones', yAxisID: 'tk', data: d.hourly.tickets.slice(first, last + 1), borderColor: AMBER, backgroundColor: 'transparent', borderWidth: 1.8, borderDash: [5, 4], pointRadius: 0, tension: 0.35, fill: false }
			] }, { tooltips: tip(money, { callbacks: { label: function (it, data) { var v = data.datasets[it.datasetIndex].data[it.index]; return it.datasetIndex === 1 ? 'Transacciones: ' + Number(v).toLocaleString('en-US') : 'Ventas netas: ' + money(v); } } }),
				scales: { xAxes: xCat(), yAxes: yMoney({ id: 'net' }).concat([{ id: 'tk', position: 'right', gridLines: { display: false, drawBorder: false }, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, precision: 0 }, TICK) }]) } }));
		}

		// Ventas por canal o por medio de pago (barras apiladas por día)
		var mixColors = [GREEN, '#3b82f6', AMBER, '#8b5cf6', RED, GRAY, '#0ea5e9'];
		var mix = function (key) {
			var rows = d[key] || [];
			make('srepDashChannels', base('bar', { labels: d.days.map(dayLabel), datasets: rows.map(function (r, i) {
				return { label: r.name, data: r.series, backgroundColor: mixColors[i % mixColors.length], maxBarThickness: 13, stack: 'mix' };
			}) }, { legend: legendOn(), tooltips: tip(money), scales: { xAxes: xCat({ stacked: true }), yAxes: yMoney({ stacked: true }) } }));
			var note = document.getElementById('srepDashMixNote');
			if (note) note.textContent = rows.map(function (r) { return r.name + ' ' + pct(r.share_pct); }).join(' · ');
		};
		mix('channels');
		document.querySelectorAll('[data-srep-mix]').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('[data-srep-mix]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				mix(b.getAttribute('data-srep-mix'));
			});
		});

		// Alertas y top 5 llevan a la pestaña dueña del detalle
		document.querySelectorAll('#srep-dashboard [data-srep-goto]').forEach(function (el) {
			el.addEventListener('click', function () {
				var pill = document.querySelector('.ded-nav-tab#pill-' + el.getAttribute('data-srep-goto')) || document.getElementById('pill-' + el.getAttribute('data-srep-goto'));
				if (pill) { pill.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
			});
		});

		var vp = d.vs_prev;
		make('srepDashVsPrev', base('line', { labels: vp.days.map(dayLabel), datasets: [
			{ label: 'Actual', data: vp.net, borderColor: GREEN, backgroundColor: 'transparent', borderWidth: 2.2, pointRadius: 0, tension: 0.3 },
			{ label: 'Anterior', data: vp.prev_net, borderColor: GRAY, backgroundColor: 'transparent', borderWidth: 1.8, borderDash: [4, 3], pointRadius: 0, tension: 0.3 }
		] }, { tooltips: tip(money, { callbacks: { title: function (it) { var i = it[0].index; return dayLabel(vp.days[i]) + (vp.prev_days[i] ? ' vs. ' + dayLabel(vp.prev_days[i]) : ''); } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		var cu = d.cumulative, sets = [{ label: 'Acumulado', data: cu.net, borderColor: GREEN, backgroundColor: 'rgba(16, 185, 129, 0.10)', borderWidth: 2.2, pointRadius: 0, fill: true, tension: 0.2 }];
		if (cu.goal) sets.push({ label: 'Meta acumulada', data: cu.goal, borderColor: AMBER, backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 4], pointRadius: 0, fill: false });
		make('srepDashCum', base('line', { labels: cu.days.map(dayLabel), datasets: sets }, { tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
	}

	/* ------------------------------------------------------------------ 2. Ventas por departamento (100 % comercial) */
	function buildDepartments() {
		var dp = D.departments;
		var P = D.period || {};
		deptConfig();
		var allRows = dp.rows || [];
		var rows = allRows.filter(function (r) { return !r.no_sales; });
		if (!rows.length) return;

		var name = function (r) { return (r.code ? r.code + ' · ' : '') + r.name; };
		var fmtD = function (iso) { var p = String(iso || '').split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; };
		var periodTxt = P.from && P.to ? fmtD(P.from) + ' – ' + fmtD(P.to) : '';
		var prevTxt = P.prev_from && P.prev_to ? fmtD(P.prev_from) + ' – ' + fmtD(P.prev_to) : '';
		var fSignedM = function (v, dec) {
			if (v === null || v === undefined) return NA;
			var n = Number(v);
			return (n >= 0 ? '+$' : '-$') + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: dec || 2, maximumFractionDigits: dec || 2 });
		};
		var fmtQty = function (v) { return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }); };

		var selectedDept = '';
		var selectedSubcat = '';
		var selectedSkuId = 0;
		var selectedSet = [];
		var evoGran = 'daily';
		var hourMode = 'net';
		var growthKey = 'week';
		var cumMode = 'dept';
		var detailGran = 'day';
		var bars = null, donut = null;

		function scopeRows() {
			if (selectedSet.length) {
				var f = rows.filter(function (r) { return selectedSet.indexOf(r.key) >= 0; });
				if (f.length) return f;
			}
			return rows;
		}
		function scopeTotalNet(list) {
			return list.reduce(function (s, r) { return s + (r.net || 0); }, 0);
		}

		/* ---------- Gráficos 1, 2, 3, 4, 5, 6, 8 + Crecimiento multi-horizonte ---------- */
		function renderBarsAndDonut() {
			var list = scopeRows();
			var lbls = list.map(name);
			var totalConsol = D.dashboard && D.dashboard.net ? D.dashboard.net : scopeTotalNet(rows);
			var idxSel = selectedDept ? list.findIndex(function (x) { return x.key === selectedDept; }) : -1;
			var bgColors = list.map(function (r, i) { return (idxSel < 0 || i === idxSel) ? r.color : alpha(r.color, 0.25); });

			bars = make('srepDeptBars', base('horizontalBar', {
				labels: lbls,
				datasets: [{ label: 'Ventas netas', data: list.map(function (r) { return r.net; }), backgroundColor: bgColors, hoverBackgroundColor: list.map(function (r) { return r.color; }), maxBarThickness: 17 }]
			}, {
				tooltips: tip(money, {
					mode: 'nearest', intersect: true,
					callbacks: {
						title: function (it) { return lbls[it[0].index] + ' · ' + periodTxt; },
						label: function (it) {
							var r = list[it.index];
							return 'Ventas netas: ' + money(r.net, 2) + ' (' + pct(r.share_pct) + ' del total)';
						}
					}
				}),
				scales: {
					xAxes: [{ gridLines: gridCfg(), ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: short }, tickCfg()) }],
					yAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({}, tickCfg(), { fontSize: 10.5 }) }]
				}
			}));

			donut = make('srepDeptDonut', base('doughnut', {
				labels: lbls,
				datasets: [{ data: list.map(function (r) { return r.net; }), backgroundColor: bgColors, borderWidth: 2, borderColor: donutBorder() }]
			}, {
				cutoutPercentage: 72,
				tooltips: tip(money, {
					mode: 'nearest', intersect: true,
					callbacks: {
						title: function (it) { return lbls[it[0].index] + ' · ' + periodTxt; },
						label: function (it) {
							var r = list[it.index];
							var sh = totalConsol > 0 ? r.net / totalConsol * 100 : r.share_pct;
							return 'Participación: ' + pct(sh) + ' · Ventas: ' + money(r.net, 2);
						}
					}
				})
			}));
			if (bars) bars.canvas.onclick = function (e) { var a = bars.getElementAtEvent(e)[0]; if (a && list[a._index]) pick(list[a._index].key); };
			if (donut) donut.canvas.onclick = function (e) { var a = donut.getElementAtEvent(e)[0]; if (a && list[a._index]) pick(list[a._index].key); };
		}

		function renderEvo() {
			var list = selectedDept ? rows.filter(function (r) { return r.key === selectedDept; }) : scopeRows().slice(0, 5);
			if (!list.length) list = rows.slice(0, 5);
			var isWeekly = evoGran === 'weekly' && dp.weeks && dp.weeks.labels && dp.weeks.labels.length;
			var xLabels = isWeekly ? dp.weeks.labels : dp.days.map(dayLabel);
			var totalShown = scopeTotalNet(list);
			make('srepDeptEvo', base('line', {
				labels: xLabels,
				datasets: list.map(function (r) {
					return {
						label: name(r),
						data: isWeekly ? (r.weekly || []) : (dp.series[r.key] || []),
						borderColor: r.color,
						backgroundColor: list.length === 1 ? alpha(r.color, 0.14) : 'transparent',
						fill: list.length === 1,
						borderWidth: 2,
						pointRadius: isWeekly ? 3 : 0,
						tension: 0.3
					};
				})
			}, {
				legend: legendOn(),
				tooltips: tip(money, {
					callbacks: {
						title: function (it) { return (isWeekly ? xLabels[it[0].index] : fmtD(dp.days[it[0].index])) + ' · ' + periodTxt; },
						label: function (it, data) {
							var ds = data.datasets[it.datasetIndex], v = ds.data[it.index] || 0;
							var r = list[it.datasetIndex];
							var daySum = data.datasets.reduce(function (s, d) { return s + (d.data[it.index] || 0); }, 0);
							var sh = daySum > 0 ? v / daySum * 100 : (totalShown > 0 && r ? r.share_pct : 0);
							return ds.label + ': ' + money(v, 2) + ' (' + pct(sh) + ')';
						}
					}
				}),
				scales: { xAxes: xCat(), yAxes: yMoney() }
			}));
			var note = document.getElementById('srepDeptEvoNote');
			if (note) {
				var baseTxt = list.length === 1 ? name(list[0]) : (selectedSet.length ? list.length + ' seleccionados' : 'Cinco principales');
				note.textContent = baseTxt + ' · ' + (isWeekly ? 'Vista semanal' : 'Vista diaria') + ' · ' + periodTxt;
			}
		}

		function renderVsPrev() {
			var list = scopeRows();
			var lbls = list.map(name);
			var tot = D.dashboard && D.dashboard.net ? D.dashboard.net : scopeTotalNet(rows);
			make('srepDeptVsPrev', base('bar', {
				labels: lbls.map(function (l) { return cut(l, 14); }),
				datasets: [
					{ label: 'Actual (' + periodTxt + ')', data: list.map(function (r) { return r.net; }), backgroundColor: GREEN, maxBarThickness: 14 },
					{ label: 'Anterior (' + prevTxt + ')', data: list.map(function (r) { return r.prev_net; }), backgroundColor: GRAY, maxBarThickness: 14 }
				]
			}, {
				tooltips: tip(money, {
					callbacks: {
						title: function (it) { return lbls[it[0].index]; },
						label: function (it, data) {
							var r = list[it.index], v = data.datasets[it.datasetIndex].data[it.index];
							if (it.datasetIndex === 0) {
								return 'Actual (' + periodTxt + '): ' + money(v, 2) + ' · Part. ' + pct(tot > 0 ? v / tot * 100 : r.share_pct) + ' · Var. ' + (r.delta_pct !== null ? (r.delta_pct >= 0 ? '+' : '') + r.delta_pct.toFixed(1) + ' %' : NA);
							}
							return 'Anterior (' + prevTxt + '): ' + money(v, 2);
						}
					}
				}),
				scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45, minRotation: 0 }, tickCfg()) }), yAxes: yMoney() }
			}));
		}

		function renderVsLy() {
			if (!dp.ly_has_data || !document.getElementById('srepDeptVsLy')) return;
			var list = scopeRows();
			var lbls = list.map(name);
			var lyTot = dp.ly_total || 0;
			make('srepDeptVsLy', base('bar', {
				labels: lbls.map(function (l) { return cut(l, 14); }),
				datasets: [
					{ label: 'Año actual (' + periodTxt + ')', data: list.map(function (r) { return r.net; }), backgroundColor: GREEN, maxBarThickness: 14 },
					{ label: 'Año anterior (LY)', data: list.map(function (r) { return r.ly_net !== null ? r.ly_net : 0; }), backgroundColor: '#64748b', maxBarThickness: 14 }
				]
			}, {
				tooltips: tip(money, {
					callbacks: {
						title: function (it) { return lbls[it[0].index] + ' · TY vs. LY'; },
						label: function (it) {
							var r = list[it.index];
							if (it.datasetIndex === 0) {
								var yoY = r.ly_delta_pct !== null ? ((r.ly_delta_pct >= 0 ? '+' : '') + r.ly_delta_pct.toFixed(1) + ' %') : NA;
								return 'Año actual (' + periodTxt + '): ' + money(r.net, 2) + ' · vs. LY: ' + yoY;
							}
							return r.ly_net !== null ? ('Año anterior: ' + money(r.ly_net, 2) + (lyTot > 0 ? ' (' + pct(r.ly_net / lyTot * 100) + ')' : '')) : NA;
						}
					}
				}),
				scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45, minRotation: 0 }, tickCfg()) }), yAxes: yMoney() }
			}));
		}

		function renderHourly() {
			var hr = dp.hourly;
			var hours = [];
			for (var h = hr.first === null ? 0 : hr.first; h <= (hr.last === null ? 23 : hr.last); h++) hours.push(h);
			var list = selectedDept ? rows.filter(function (r) { return r.key === selectedDept; }) : scopeRows().slice(0, 5);
			if (!list.length) list = rows.slice(0, 5);
			var isShare = hourMode === 'share';
			make('srepDeptHourly', base('line', {
				labels: hours.map(function (x) { return (x < 10 ? '0' : '') + x + ':00'; }),
				datasets: list.map(function (r) {
					var s = hr.series[r.key] || [];
					var tot = s.reduce(function (a, v) { return a + v; }, 0);
					return {
						label: name(r),
						data: hours.map(function (x) { return isShare ? (tot > 0 ? Math.round((s[x] || 0) / tot * 1000) / 10 : 0) : (s[x] || 0); }),
						borderColor: r.color,
						backgroundColor: list.length === 1 ? alpha(r.color, 0.14) : 'transparent',
						fill: list.length === 1,
						borderWidth: 2,
						pointRadius: 2,
						tension: 0.3
					};
				})
			}, {
				legend: legendOn(),
				tooltips: tip(isShare ? pct : money, {
					callbacks: {
						title: function (it) { return 'Hora ' + it[0].label + ' · ' + periodTxt; },
						label: function (it, data) {
							var r = list[it.datasetIndex], s = hr.series[r.key] || [], hVal = hours[it.index];
							var tot = s.reduce(function (a, v) { return a + v; }, 0);
							var rawV = s[hVal] || 0, sh = tot > 0 ? rawV / tot * 100 : 0;
							return name(r) + ': ' + money(rawV, 2) + ' (' + pct(sh) + ' del día)';
						}
					}
				}),
				scales: {
					xAxes: xCat(),
					yAxes: isShare ? [{ gridLines: gridCfg(), ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }, tickCfg()) }] : yMoney()
				}
			}));
		}

		function renderCumGoal() {
			if (!dp.has_goals || !document.getElementById('srepDeptCumGoal')) return;
			var note = document.getElementById('srepDeptCumNote');
			if (cumMode === 'curve') {
				var singleR = selectedDept ? rows.filter(function (r) { return r.key === selectedDept; })[0] : null;
				var sCum = singleR ? singleR.cumulative : (dp.cumulative_total || []);
				var gCum = singleR ? singleR.goal_cumulative : dp.goal_cumulative_total;
				var who = singleR ? name(singleR) : 'Consolidado departamentos';
				var datasets = [{ label: 'Ventas acumuladas (' + who + ')', data: sCum || [], borderColor: GREEN, backgroundColor: alpha(GREEN, 0.14), fill: true, borderWidth: 2.2, pointRadius: 0, tension: 0.2 }];
				if (gCum && gCum.length) {
					datasets.push({ label: 'Meta acumulada', data: gCum, borderColor: '#64748b', borderDash: [5, 4], backgroundColor: 'transparent', fill: false, borderWidth: 1.8, pointRadius: 0, tension: 0.1 });
				}
				make('srepDeptCumGoal', base('line', { labels: dp.days.map(dayLabel), datasets: datasets }, {
					legend: legendOn(),
					tooltips: tip(money, {
						callbacks: {
							title: function (it) { return fmtD(dp.days[it[0].index]) + ' · ' + who; },
							label: function (it, data) {
								var v = data.datasets[it.datasetIndex].data[it.index];
								var gv = gCum && gCum[it.index] ? gCum[it.index] : null;
								var sv = sCum[it.index] || 0;
								var cPct = gv > 0 ? ' (' + pct(sv / gv * 100) + ' de meta)' : '';
								return data.datasets[it.datasetIndex].label + ': ' + money(v, 2) + (it.datasetIndex === 0 ? cPct : '');
							}
						}
					}),
					scales: { xAxes: xCat(), yAxes: yMoney() }
				}));
				if (note) note.textContent = who + ' · Curva acumulada diaria vs. meta prorrateada (' + periodTxt + ').';
				return;
			}
			var withG = scopeRows().filter(function (r) { return r.goal !== null; });
			var lbls = withG.map(name);
			make('srepDeptCumGoal', base('horizontalBar', {
				labels: lbls,
				datasets: [
					{ label: 'Ventas acumuladas', data: withG.map(function (r) { return r.net; }), backgroundColor: withG.map(function (r) { return r.goal_pct >= 100 ? GREEN : r.color; }), maxBarThickness: 12 },
					{ label: 'Meta del período', data: withG.map(function (r) { return r.goal; }), backgroundColor: isDark() ? 'rgba(148,163,184,0.35)' : '#cbd5e1', maxBarThickness: 12 }
				]
			}, {
				legend: legendOn(),
				tooltips: tip(money, {
					mode: 'index', intersect: true,
					callbacks: {
						title: function (it) { return lbls[it[0].index] + ' · ' + periodTxt; },
						label: function (it) {
							var r = withG[it.index];
							return it.datasetIndex === 0
								? 'Acumulado real: ' + money(r.net, 2) + ' (' + pct(r.goal_pct) + ' de meta)'
								: 'Meta comercial: ' + money(r.goal, 2);
						}
					}
				}),
				scales: { xAxes: [{ gridLines: gridCfg(), ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: short }, tickCfg()) }], yAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({}, tickCfg(), { fontSize: 10 }) }] }
			}));
			if (note) note.textContent = 'Período ' + periodTxt + ': acumulado real frente a la meta comercial de cada departamento.';
		}

		function renderGrowth(key) {
			growthKey = key;
			var list = scopeRows();
			var lbls = list.map(name);
			var vals = list.map(function (r) { return r.growth[key]; });
			var gw = (dp.growth && dp.growth[key]) || {};
			var gwSpan = gw.from && gw.to ? fmtD(gw.from) + ' – ' + fmtD(gw.to) : periodTxt;
			var ch = hbar('srepDeptGrowth', lbls, vals.map(function (v) { return v === null ? 0 : v; }), vals.map(function (v) { return v === null ? (isDark() ? '#334155' : '#e2e8f0') : (v >= 0 ? GREEN : RED); }),
				function (v) { return (v >= 0 ? '+' : '') + Number(v).toFixed(1) + ' %'; });
			if (ch) {
				ch.options.tooltips.callbacks.title = function (it) { return lbls[it[0].index] + ' · ' + gwSpan; };
				ch.options.tooltips.callbacks.label = function (it) {
					var v = vals[it.index], r = list[it.index];
					return v === null ? NA + ' (sin venta en ventana comparable)' : 'Crecimiento: ' + (v >= 0 ? '+' : '') + v.toFixed(1) + ' % · Ventas: ' + money(r.net, 2) + ' (' + pct(r.share_pct) + ')';
				};
				ch.options.scales.xAxes[0].ticks.callback = function (v) { return v + ' %'; };
				ch.options.scales.xAxes[0].ticks.beginAtZero = true;
				ch.update();
			}
			document.querySelectorAll('#srep-departments [data-srep-growth]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-srep-growth') === key); });
			document.querySelectorAll('#srep-departments [data-srep-growth-note]').forEach(function (p) { p.hidden = p.getAttribute('data-srep-growth-note') !== key; });
		}

		function renderAllCharts() {
			renderBarsAndDonut();
			renderEvo();
			renderVsPrev();
			renderVsLy();
			renderHourly();
			renderCumGoal();
			renderGrowth(growthKey);
		}

		/* ---------- Fase 4: Detalle y drilldown Departamento -> Subcategoría -> SKU ---------- */
		var detail = document.getElementById('srepDeptDetail');
		var select = document.getElementById('srepDeptSelect');

		function renderDetailTimeChart(r) {
			var canvas = document.getElementById('srepDeptDetailTimeChart');
			if (!canvas) return;
			var xL = [], yD = [], titlePrefix = '';
			if (detailGran === 'hour') {
				var hr = dp.hourly, sH = (hr.series && hr.series[r.key]) || [];
				var fH = hr.first === null ? 0 : hr.first, lH = hr.last === null ? 23 : hr.last;
				for (var h = fH; h <= lH; h++) { xL.push((h < 10 ? '0' : '') + h + ':00'); yD.push(sH[h] || 0); }
				titlePrefix = 'Hora ';
			} else if (detailGran === 'week') {
				xL = (dp.weeks && dp.weeks.labels) || [];
				yD = r.weekly || [];
			} else if (detailGran === 'month') {
				xL = (dp.months && dp.months.labels) || [];
				yD = r.monthly || [];
			} else {
				xL = dp.days.map(dayLabel);
				yD = dp.series[r.key] || [];
			}
			var totY = yD.reduce(function (a, v) { return a + (v || 0); }, 0);
			make('srepDeptDetailTimeChart', base(detailGran === 'month' || detailGran === 'week' ? 'bar' : 'line', {
				labels: xL,
				datasets: [{ label: name(r), data: yD, borderColor: r.color, backgroundColor: detailGran === 'month' || detailGran === 'week' ? alpha(r.color, 0.78) : alpha(r.color, 0.16), fill: true, borderWidth: 2, pointRadius: detailGran === 'day' ? 1.5 : 3, tension: 0.3, maxBarThickness: 26 }]
			}, {
				tooltips: tip(money, {
					callbacks: {
						title: function (it) { return name(r) + ' · ' + titlePrefix + it[0].label; },
						label: function (it) {
							var v = yD[it.index] || 0, sh = totY > 0 ? v / totY * 100 : 0;
							return 'Ventas: ' + money(v, 2) + ' (' + pct(sh) + ' de la serie)';
						}
					}
				}),
				scales: { xAxes: xCat(), yAxes: yMoney() }
			}));
		}

		function productListHtml(list, r, emptyMsg) {
			if (!list || !list.length) return '<span class="ded-text-muted-italics">' + esc(emptyMsg) + '</span>';
			return '<div class="srep-scroll"><table class="ded-table-top5"><thead><tr><th>Producto</th><th class="text-right">Ventas</th><th class="text-right">Unidades</th><th class="text-right">Part. depto.</th></tr></thead><tbody>'
				+ list.map(function (p) {
					return '<tr class="srep-clickable-row" data-drill-sub="' + esc(p.subcategory) + '" data-drill-sku="' + p.id + '" title="Ver ficha comercial de ' + esc(p.name) + ' · Precio prom. ' + esc(money(p.avg_unit_price, 2)) + '">'
						+ '<td><strong>' + esc(cut(p.name, 34)) + '</strong><br><small class="text-muted">' + esc(p.sku || 'Sin SKU') + ' · ' + esc(p.subcategory) + '</small></td>'
						+ '<td class="text-right ded-table-price">' + esc(money(p.net, 2)) + '</td>'
						+ '<td class="text-right ded-table-num">' + esc(fmtQty(p.units)) + '</td>'
						+ '<td class="text-right ded-table-share">' + esc(pct(p.share_dept_pct)) + '</td></tr>';
				}).join('') + '</tbody></table></div>';
		}

		function renderDetail() {
			if (!detail) return;
			if (!selectedDept) {
				var chips = rows.map(function (x) {
					return '<button type="button" class="ded-select-btn" data-srep-pick-chip="' + esc(x.key) + '"><span class="ded-dept-dot" style="background:' + esc(x.color) + '"></span><span>' + esc(name(x)) + ' (' + esc(pct(x.share_pct)) + ')</span></button>';
				}).join('');
				detail.innerHTML = '<div class="srep-detail-block">'
					+ '<div class="d-flex flex-wrap" style="gap:6px;">' + chips + '</div>'
					+ '</div>';
				detail.querySelectorAll('[data-srep-pick-chip]').forEach(function (b) {
					b.addEventListener('click', function () { pick(b.getAttribute('data-srep-pick-chip')); });
				});
				return;
			}
			var r = rows.filter(function (x) { return x.key === selectedDept; })[0];
			if (!r) return;

			var subs = r.subcategories || [];
			var subObj = selectedSubcat ? subs.filter(function (s) { return s.name === selectedSubcat || s.key === selectedSubcat; })[0] : null;
			var skuObj = subObj && selectedSkuId ? (subObj.skus || []).filter(function (p) { return p.id === selectedSkuId; })[0] : null;
			if (!skuObj && selectedSkuId && subObj === null) {
				subs.some(function (s) {
					var m = (s.skus || []).filter(function (p) { return p.id === selectedSkuId; })[0];
					if (m) { subObj = s; skuObj = m; return true; }
					return false;
				});
			}

			var crumb = '<div class="srep-breadcrumb" data-testid="srep-drill-breadcrumb">'
				+ '<button type="button" class="srep-crumb-btn" data-crumb="root">Todos los departamentos</button>'
				+ '<span class="srep-crumb-sep">›</span>'
				+ (subObj ? '<button type="button" class="srep-crumb-btn" data-crumb="dept">' + esc(name(r)) + '</button>' : '<span class="srep-crumb-cur">' + esc(name(r)) + '</span>')
				+ (subObj ? '<span class="srep-crumb-sep">›</span>' + (skuObj ? '<button type="button" class="srep-crumb-btn" data-crumb="sub">' + esc(subObj.name) + '</button>' : '<span class="srep-crumb-cur">' + esc(subObj.name) + '</span>') : '')
				+ (skuObj ? '<span class="srep-crumb-sep">›</span><span class="srep-crumb-cur">' + esc(skuObj.name) + '</span>' : '')
				+ '</div>';

			var html = crumb;
			if (skuObj) {
				var shareSub = subObj && subObj.net > 0 ? skuObj.net / subObj.net * 100 : null;
				var skuMetrics = [
					['SKU / Código', skuObj.sku || NA, skuObj.subcategory],
					['Ventas netas', money(skuObj.net, 2), periodTxt],
					['Ventas brutas', money(skuObj.gross, 2), 'Descuentos: ' + money(skuObj.discounts, 2)],
					['Unidades vendidas', fmtQty(skuObj.units), 'Precio prom.: ' + money(skuObj.avg_unit_price, 2)],
					['Tickets con SKU', skuObj.tickets !== null ? skuObj.tickets.toLocaleString('en-US') : NA, 'Tickets donde se vendió'],
					['Participación en subcategoría', pct(shareSub), subObj.name],
					['Participación en depto.', pct(skuObj.share_dept_pct), name(r)],
					['Participación total', pct(skuObj.share_total_pct), 'Total comercial'],
					['Período anterior', skuObj.prev_net !== null ? money(skuObj.prev_net, 2) : NA, prevTxt],
					['Crecimiento vs. anterior', signed(skuObj.delta_pct), skuObj.delta_abs !== null ? fSignedM(skuObj.delta_abs) : '']
				];
				html += '<div class="srep-drill-metrics" data-testid="srep-sku-detail">' + skuMetrics.map(function (m) {
					return '<div class="srep-drill-stat"><span class="srep-drill-stat-label">' + esc(m[0]) + '</span><div class="srep-drill-stat-val">' + (/^</.test(m[1]) ? m[1] : esc(m[1])) + '</div><span class="srep-drill-stat-sub">' + esc(m[2]) + '</span></div>';
				}).join('') + '</div>';
			} else if (subObj) {
				var subMetrics = [
					['Ventas netas', money(subObj.net, 2), periodTxt],
					['Ventas brutas', money(subObj.gross, 2), 'Descuentos: ' + money(subObj.discounts, 2)],
					['Participación en depto.', pct(subObj.share_dept_pct), name(r)],
					['Participación total', pct(subObj.share_total_pct), 'Total comercial'],
					['Unidades vendidas', fmtQty(subObj.units), 'Precio prom.: ' + money(subObj.avg_unit_price, 2)],
					['Tickets subcategoría', subObj.tickets !== null ? subObj.tickets.toLocaleString('en-US') : NA, 'Sin duplicar tickets'],
					['Crecimiento vs. anterior', signed(subObj.delta_pct), subObj.delta_abs !== null ? fSignedM(subObj.delta_abs) : NA]
				];
				html += '<div class="srep-drill-metrics" data-testid="srep-subcat-detail">' + subMetrics.map(function (m) {
					return '<div class="srep-drill-stat"><span class="srep-drill-stat-label">' + esc(m[0]) + '</span><div class="srep-drill-stat-val">' + (/^</.test(m[1]) ? m[1] : esc(m[1])) + '</div><span class="srep-drill-stat-sub">' + esc(m[2]) + '</span></div>';
				}).join('') + '</div>';
				html += '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">SKUs en ' + esc(subObj.name) + ' (' + (subObj.skus || []).length + ')</h3><span class="srep-note mb-0">Haz clic en un SKU para ver su ficha</span></div>';
				if (!(subObj.skus && subObj.skus.length)) {
					html += '<span class="ded-text-muted-italics">' + NA + '</span>';
				} else {
					html += '<div class="srep-scroll"><table class="ded-table-top5" data-testid="srep-subcat-skus"><thead><tr><th>SKU</th><th>Producto</th><th class="text-right">Ventas netas</th><th class="text-right">Unidades</th><th class="text-right">Precio prom.</th><th class="text-right">Tickets</th><th class="text-right">Part. depto.</th><th class="text-right">Var. %</th></tr></thead><tbody>'
						+ subObj.skus.map(function (p) {
							return '<tr class="srep-clickable-row" data-drill-sub="' + esc(subObj.name) + '" data-drill-sku="' + p.id + '">'
								+ '<td><code>' + esc(p.sku || '—') + '</code></td><td><strong>' + esc(p.name) + '</strong></td>'
								+ '<td class="text-right ded-table-price" title="' + esc(money(p.net, 2)) + '">' + esc(money(p.net, 2)) + '</td>'
								+ '<td class="text-right ded-table-num">' + esc(fmtQty(p.units)) + '</td>'
								+ '<td class="text-right ded-table-num">' + esc(money(p.avg_unit_price, 2)) + '</td>'
								+ '<td class="text-right ded-table-num">' + esc(p.tickets !== null ? p.tickets.toLocaleString('en-US') : NA) + '</td>'
								+ '<td class="text-right ded-table-share">' + esc(pct(p.share_dept_pct)) + '</td>'
								+ '<td class="text-right">' + signed(p.delta_pct) + '</td></tr>';
						}).join('') + '</tbody></table></div>';
				}
				html += '</div>';
			} else {
				var discPct = r.gross > 0 ? (r.discounts / r.gross * 100) : null;
				var deptMetrics = [
					['Ventas netas', money(r.net, 2), periodTxt],
					['Ventas brutas', money(r.gross, 2), 'Antes de descuentos'],
					['Descuentos comerciales', money(r.discounts, 2), discPct !== null ? pct(discPct) + ' sobre bruto' : ''],
					['Unidades vendidas', fmtQty(r.units), (r.sku_count || 0) + ' SKUs vendidos'],
					['Tickets del departamento', r.tickets.toLocaleString('en-US'), 'Tickets con productos del depto.'],
					['Unidades por ticket', r.units_per_ticket !== null ? r.units_per_ticket.toFixed(2) : NA, 'Unidades ÷ tickets'],
					['Precio promedio / unidad', r.avg_unit_price !== null ? money(r.avg_unit_price, 2) : NA, 'Ventas netas ÷ unidades'],
					['Participación total', pct(r.share_pct), 'Posición #' + (r.rank || '—')],
					['Crec. vs. período anterior', signed(r.prev_period_delta_pct), fSignedM(r.prev_period_delta_abs)],
					['Crec. vs. año anterior (LY)', r.ly_delta_pct !== null ? signed(r.ly_delta_pct) : NA, r.ly_net !== null ? 'LY: ' + money(r.ly_net, 2) : 'Sin historial LY'],
					['Meta y cumplimiento', r.goal !== null ? pct(r.goal_pct) : NA, r.goal !== null ? 'Meta ' + money(r.goal, 2) + ' (' + fSignedM(r.goal_gap) + ')' : 'Sin meta asignada']
				];
				html += '<div class="srep-drill-metrics" data-testid="srep-dept-metrics">' + deptMetrics.map(function (m) {
					return '<div class="srep-drill-stat"><span class="srep-drill-stat-label">' + esc(m[0]) + '</span><div class="srep-drill-stat-val">' + (/^</.test(m[1]) ? m[1] : esc(m[1])) + '</div><span class="srep-drill-stat-sub">' + esc(m[2]) + '</span></div>';
				}).join('') + '</div>';

				// Gráfico temporal por departamento: Hora | Día | Semana | Mes
				html += '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">Ventas por hora, día, semana y mes · ' + esc(name(r)) + '</h3>'
					+ '<div class="ded-card-tools srep-seg" role="group">'
					+ [['hour', 'Por hora'], ['day', 'Por día'], ['week', 'Por semana'], ['month', 'Por mes']].map(function (g) {
						return '<button type="button" class="ded-select-btn' + (detailGran === g[0] ? ' is-active' : '') + '" data-srep-detail-gran="' + g[0] + '">' + g[1] + '</button>';
					}).join('') + '</div></div>'
					+ '<div class="ded-chart-canvas-box" style="height:210px;"><canvas id="srepDeptDetailTimeChart"></canvas></div></div>';

				// Top 5 y Bottom 5 productos
				html += '<div class="srep-drill-grid">'
					+ '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">Top 5 productos por ventas</h3></div>'
					+ productListHtml(r.top5_products, r, 'Sin productos en el período') + '</div>'
					+ '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">Bottom 5 productos por ventas</h3></div>'
					+ productListHtml(r.bottom5_products, r, (r.sku_count <= 5 ? 'Todos los SKUs vendidos (' + r.sku_count + ') están en el Top 5' : 'Sin productos adicionales')) + '</div>'
					+ '</div>';

				// Ventas por sucursal + Subcategorías del departamento
				var maxB = (r.by_branch && r.by_branch[0]) ? r.by_branch[0].net : 1;
				html += '<div class="srep-drill-grid">'
					+ '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">Ventas por sucursal · ' + esc(r.name) + '</h3></div>'
					+ (r.by_branch && r.by_branch.length ? '<div class="srep-scroll"><table class="ded-table-top5"><thead><tr><th>Sucursal</th><th class="text-right">Ventas netas</th><th class="text-right">% Depto.</th></tr></thead><tbody>'
						+ r.by_branch.map(function (b) {
							var w = maxB > 0 ? Math.max(4, Math.round(b.net / maxB * 100)) : 0;
							return '<tr><td>' + esc(b.name) + '<div class="srep-bar-track"><div class="srep-bar-fill" style="width:' + w + '%;background:' + esc(r.color) + '"></div></div></td>'
								+ '<td class="text-right ded-table-price" title="' + esc(money(b.net, 2)) + '">' + esc(money(b.net, 2)) + '</td>'
								+ '<td class="text-right ded-table-share">' + esc(pct(b.share_dept_pct)) + '</td></tr>';
						}).join('') + '</tbody></table></div>' : '<span class="ded-text-muted-italics">' + NA + '</span>')
					+ '</div>'
					+ '<div class="srep-drill-box"><div class="srep-drill-box-head"><h3 class="srep-drill-box-title">Subcategorías de ' + esc(r.name) + '</h3><span class="srep-note mb-0">Clic para ver SKUs</span></div>'
					+ (subs.length ? '<div class="srep-scroll"><table class="ded-table-top5" data-testid="srep-dept-subcats"><thead><tr><th>Subcategoría</th><th class="text-right">Ventas</th><th class="text-right">% Depto.</th><th class="text-right">SKUs</th></tr></thead><tbody>'
						+ subs.map(function (s) {
							var clk = !s.is_archive_remainder ? ' class="srep-clickable-row" data-drill-sub="' + esc(s.name) + '"' : '';
							return '<tr' + clk + '><td><strong>' + esc(s.name) + '</strong></td>'
								+ '<td class="text-right ded-table-price" title="' + esc(money(s.net, 2)) + '">' + esc(money(s.net, 2)) + '</td>'
								+ '<td class="text-right ded-table-share">' + esc(pct(s.share_dept_pct)) + '</td>'
								+ '<td class="text-right ded-table-num">' + esc(s.sku_count) + '</td></tr>';
						}).join('') + '</tbody></table></div>' : '<span class="ded-text-muted-italics">' + NA + '</span>')
					+ '</div>'
					+ '</div>';
			}

			detail.innerHTML = '<div class="srep-detail-block">' + html + '</div>';
			detail.querySelectorAll('[data-crumb]').forEach(function (b) {
				b.addEventListener('click', function () {
					var c = b.getAttribute('data-crumb');
					if (c === 'root') { selectedDept = ''; selectedSubcat = ''; selectedSkuId = 0; if (select) select.value = ''; show(''); }
					else if (c === 'dept') { selectedSubcat = ''; selectedSkuId = 0; renderDetail(); }
					else if (c === 'sub') { selectedSkuId = 0; renderDetail(); }
				});
			});
			detail.querySelectorAll('[data-srep-detail-gran]').forEach(function (b) {
				b.addEventListener('click', function () {
					detailGran = b.getAttribute('data-srep-detail-gran');
					detail.querySelectorAll('[data-srep-detail-gran]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
					renderDetailTimeChart(r);
				});
			});
			detail.querySelectorAll('[data-drill-sub]').forEach(function (tr) {
				tr.addEventListener('click', function () {
					selectedSubcat = tr.getAttribute('data-drill-sub') || '';
					selectedSkuId = parseInt(tr.getAttribute('data-drill-sku') || '0', 10) || 0;
					renderDetail();
				});
			});
			if (!subObj && !skuObj) renderDetailTimeChart(r);
		}

		function show(key, subcatName, skuId) {
			selectedDept = key || '';
			selectedSubcat = subcatName || '';
			selectedSkuId = skuId || 0;
			if (select) select.value = selectedDept;
			document.querySelectorAll('#srep-departments [data-srep-dept-row]').forEach(function (tr) {
				tr.classList.toggle('is-selected', !!selectedDept && tr.getAttribute('data-srep-dept-row') === selectedDept);
			});
			renderDetail();
			renderAllCharts();
		}

		var pick = function (key) {
			if (!key) { show(''); return; }
			show(selectedDept === key && !selectedSubcat ? '' : key);
		};

		if (select) select.addEventListener('change', function () { show(select.value); });
		document.querySelectorAll('#srep-departments [data-srep-dept]').forEach(function (el) {
			el.addEventListener('click', function () { pick(el.getAttribute('data-srep-dept')); });
		});

		/* ---------- Toggles de gráficos (Diaria/Semanal, Hora, Acumulado, Crecimiento, Heatmap) ---------- */
		document.querySelectorAll('#srep-departments [data-srep-evo]').forEach(function (b) {
			b.addEventListener('click', function () {
				evoGran = b.getAttribute('data-srep-evo');
				document.querySelectorAll('#srep-departments [data-srep-evo]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				renderEvo();
			});
		});
		document.querySelectorAll('#srep-departments [data-srep-hour]').forEach(function (b) {
			b.addEventListener('click', function () {
				hourMode = b.getAttribute('data-srep-hour');
				document.querySelectorAll('#srep-departments [data-srep-hour]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				renderHourly();
			});
		});
		document.querySelectorAll('#srep-departments [data-srep-cum]').forEach(function (b) {
			b.addEventListener('click', function () {
				cumMode = b.getAttribute('data-srep-cum');
				document.querySelectorAll('#srep-departments [data-srep-cum]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				renderCumGoal();
			});
		});
		document.querySelectorAll('#srep-departments [data-srep-growth]').forEach(function (b) {
			b.addEventListener('click', function () { renderGrowth(b.getAttribute('data-srep-growth')); });
		});
		document.querySelectorAll('#srep-departments [data-srep-heat]').forEach(function (b) {
			b.addEventListener('click', function () {
				var m = b.getAttribute('data-srep-heat');
				var attr = m === 'share' ? 'data-share' : (m === 'dept_share' ? 'data-dept-share' : 'data-net');
				document.querySelectorAll('#srep-departments [data-srep-heat]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				document.querySelectorAll('#srepDeptHeat td[data-net]').forEach(function (td) { td.textContent = td.getAttribute(attr); });
			});
		});

		/* ---------- Fase 5: Búsqueda, multi-selección y expansión de subcategorías en Tabla Analítica ---------- */
		var table = document.getElementById('srepDeptTable');
		var searchInput = document.getElementById('srepDeptSearch');
		var multiBar = document.getElementById('srepDeptMultiBar');

		function updateMultiBar() {
			if (!multiBar) return;
			if (!selectedSet.length) {
				multiBar.hidden = true;
				multiBar.innerHTML = '';
				return;
			}
			var selRows = rows.filter(function (r) { return selectedSet.indexOf(r.key) >= 0; });
			var sumN = selRows.reduce(function (s, r) { return s + (r.net || 0); }, 0);
			var sumP = selRows.reduce(function (s, r) { return s + (r.prev_net || 0); }, 0);
			var sumU = selRows.reduce(function (s, r) { return s + (r.units || 0); }, 0);
			var sumSh = selRows.reduce(function (s, r) { return s + (r.share_pct || 0); }, 0);
			var dPct = sumP > 0 ? (sumN - sumP) / sumP * 100 : null;
			multiBar.hidden = false;
			multiBar.innerHTML = '<div><strong>' + selectedSet.length + ' seleccionados:</strong> Netas <strong>' + esc(money(sumN, 2)) + '</strong> (' + esc(pct(sumSh)) + ') · Anterior ' + esc(money(sumP, 2)) + ' · Var. ' + signed(dPct) + ' · Unidades ' + esc(fmtQty(sumU)) + '</div>'
				+ '<button type="button" class="ded-select-btn" id="srepDeptMultiClear">Limpiar selección</button>';
			var clr = document.getElementById('srepDeptMultiClear');
			if (clr) {
				clr.addEventListener('click', function () {
					selectedSet = [];
					document.querySelectorAll('#srepDeptTable .srep-dept-chk').forEach(function (c) { c.checked = false; });
					updateMultiBar();
					renderAllCharts();
				});
			}
		}

		function applySearch() {
			if (!table || !searchInput) return;
			var q = searchInput.value.trim().toLowerCase();
			table.querySelectorAll('tbody tr[data-srep-dept-row]').forEach(function (tr) {
				var k = tr.getAttribute('data-srep-dept-row');
				var nm = tr.getAttribute('data-dept-name') || tr.textContent.toLowerCase();
				var vis = !q || nm.indexOf(q) >= 0;
				tr.style.display = vis ? '' : 'none';
				table.querySelectorAll('tbody tr.srep-subcat-row[data-parent-dept="' + k + '"]').forEach(function (sr) {
					sr.style.display = vis ? '' : 'none';
				});
			});
		}
		if (searchInput) searchInput.addEventListener('input', applySearch);

		if (table) {
			table.querySelectorAll('.srep-dept-chk').forEach(function (chk) {
				chk.addEventListener('click', function (e) { e.stopPropagation(); });
				chk.addEventListener('change', function () {
					var k = chk.value;
					if (chk.checked && selectedSet.indexOf(k) < 0) selectedSet.push(k);
					else if (!chk.checked) selectedSet = selectedSet.filter(function (x) { return x !== k; });
					updateMultiBar();
					renderAllCharts();
				});
			});
			table.querySelectorAll('[data-srep-expand]').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var key = btn.getAttribute('data-srep-expand');
					var parentTr = btn.closest('tr');
					var existing = table.querySelectorAll('tbody tr.srep-subcat-row[data-parent-dept="' + key + '"]');
					if (existing.length) {
						Array.prototype.forEach.call(existing, function (x) { x.remove(); });
						btn.classList.remove('is-open');
						btn.setAttribute('aria-expanded', 'false');
						return;
					}
					var deptObj = allRows.filter(function (r) { return r.key === key; })[0];
					var subs = (deptObj && deptObj.subcategories) || [];
					if (!subs.length) return;
					btn.classList.add('is-open');
					btn.setAttribute('aria-expanded', 'true');
					var anchor = parentTr;
					subs.forEach(function (s) {
						var tr = document.createElement('tr');
						tr.className = 'srep-subcat-row' + (s.is_archive_remainder ? '' : ' srep-clickable-row');
						tr.setAttribute('data-parent-dept', key);
						if (!s.is_archive_remainder) tr.setAttribute('data-sub-name', s.name);
						tr.innerHTML = '<td class="srep-subcat-name" data-col-sticky>↳ ' + esc(s.name) + ' <small class="text-muted">(' + esc(s.sku_count) + ' SKUs)</small></td>'
							+ '<td class="text-right ded-table-price" title="' + esc(money(s.net, 2)) + '">' + esc(money(s.net, 2)) + '</td>'
							+ '<td class="text-right ded-table-share" title="' + esc(pct(s.share_dept_pct)) + ' del depto.">' + esc(pct(s.share_total_pct)) + '</td>'
							+ '<td class="text-right ded-table-num">' + esc(s.prev_net !== null ? money(s.prev_net, 2) : NA) + '</td>'
							+ '<td class="text-right ded-table-num ' + (s.delta_abs !== null ? (s.delta_abs >= 0 ? 'srep-val-pos' : 'srep-val-neg') : '') + '">' + esc(s.delta_abs !== null ? fSignedM(s.delta_abs) : NA) + '</td>'
							+ '<td class="text-right">' + signed(s.delta_pct) + '</td>'
							+ '<td class="text-right ded-table-num">' + esc(fmtQty(s.units)) + '</td>'
							+ '<td class="text-right ded-table-num">' + esc(s.tickets !== null ? s.tickets.toLocaleString('en-US') : NA) + '</td>'
							+ '<td class="text-right ded-table-num">—</td>'
							+ '<td class="text-right ded-table-share">—</td>';
						if (!s.is_archive_remainder) {
							tr.addEventListener('click', function () {
								show(key, s.name, 0);
								var drillCard = document.getElementById('srepDeptDrillCard');
								if (drillCard && drillCard.scrollIntoView) drillCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
							});
						}
						anchor.parentNode.insertBefore(tr, anchor.nextSibling);
						anchor = tr;
					});
				});
			});
			table.querySelectorAll('tbody tr[data-srep-dept-row]').forEach(function (tr) {
				tr.addEventListener('click', function (e) {
					if (e.target && (e.target.closest('button') || e.target.closest('input'))) return;
					pick(tr.getAttribute('data-srep-dept-row'));
				});
			});
		}

		// Reconstruye las gráficas ante cambio de tema claro/oscuro sin recargar
		var initialKey = (select && select.value) || '';
		show(initialKey);
		if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
	}

	/* Editor de departamentos: código, nombre, color, orden y valores de producto (POST /api/sales/departments/config) */
	function deptConfig() {
		var box = document.getElementById('srepDeptConfig');
		var raw = document.getElementById('srepDeptConfigData');
		if (!box || !raw || box.getAttribute('data-ready')) return;
		box.setAttribute('data-ready', '1');
		var cfg = JSON.parse(raw.textContent);
		var body = box.querySelector('#srepDeptConfigTable tbody');
		var msg = document.getElementById('srepDeptConfigMsg');
		var row = function (d) {
			var tr = document.createElement('tr');
			tr.setAttribute('data-key', d.key || '');
			tr.innerHTML = '<td><input type="number" class="form-control" data-f="sort_order" value="' + esc(d.sort_order) + '" style="width:80px;"' + (d.key === 'otros' ? ' disabled' : '') + '></td>'
				+ '<td><input type="text" class="form-control" data-f="code" maxlength="12" value="' + esc(d.code || '') + '" placeholder="—" style="width:80px;"></td>'
				+ '<td><input type="text" class="form-control" data-f="name" maxlength="60" value="' + esc(d.name) + '"></td>'
				+ '<td><input type="color" class="form-control" data-f="color" value="' + esc(d.color || '#64748B') + '"></td>'
				+ '<td>' + (d.key === 'otros' ? '<span class="ded-text-muted-italics">Todo lo no asignado</span>' : '<input type="text" class="form-control srep-cfg-values" data-f="source_values" value="' + esc((d.values || []).join(', ')) + '">') + '</td>'
				+ '<td>' + (d.base ? '' : '<button type="button" class="ded-select-btn" data-del title="Eliminar departamento"><i class="fa-solid fa-trash"></i></button>') + '</td>';
			var del = tr.querySelector('[data-del]');
			if (del) del.addEventListener('click', function () { tr.hidden = !tr.hidden; tr.setAttribute('data-delete', tr.hidden ? '1' : ''); });
			body.appendChild(tr);
		};
		cfg.departments.forEach(row);
		var vals = document.getElementById('srepDeptConfigValues');
		if (vals) vals.textContent = 'Valores en productos activos: ' + cfg.product_values.map(function (v) { return v.value + ' (' + v.products + ')'; }).join(', ');
		document.getElementById('srepDeptConfigAdd').addEventListener('click', function () { row({ key: '', name: '', color: '#0891B2', sort_order: 500, values: [], base: false }); });
		document.getElementById('srepDeptConfigSave').addEventListener('click', function () {
			var out = [];
			body.querySelectorAll('tr').forEach(function (tr) {
				var d = { key: tr.getAttribute('data-key') };
				if (tr.getAttribute('data-delete') === '1') { if (d.key) out.push({ key: d.key, delete: true }); return; }
				tr.querySelectorAll('[data-f]').forEach(function (i) { d[i.getAttribute('data-f')] = i.value; });
				if (!d.key && !String(d.name || '').trim()) return;
				if (typeof d.source_values === 'string') d.source_values = d.source_values.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
				out.push(d);
			});
			msg.textContent = 'Guardando…';
			if (typeof Grocy === 'undefined' || !Grocy.Api) { msg.textContent = 'No fue posible guardar: API no disponible.'; return; }
			Grocy.Api.Post('sales/departments/config', { departments: out }, function () {
				msg.textContent = 'Guardado. Recalculando…';
				window.location.reload();
			}, function (xhr) {
				var e = null;
				try { e = JSON.parse(xhr.responseText).error_message; } catch (x) { }
				msg.textContent = 'No se guardó: ' + (e || 'error del servidor');
			});
		});
	}

	/* ------------------------------------------------------------------ 3. Productos de venta */
	function buildProducts() {
		var p = D.products;
		if (!p.rows.length) return;
		hbar('srepProdNet', p.top_net.map(function (r) { return cut(r.name, 26); }), p.top_net.map(function (r) { return r.net; }), GREEN, money, p.top_net.map(function (r) { return r.name + (r.sku ? ' · ' + r.sku : ''); }));
		make('srepProdUnits', base('bar', { labels: p.top_units.map(function (r) { return cut(r.name, 12); }), datasets: [{ label: 'Unidades', data: p.top_units.map(function (r) { return r.units; }), backgroundColor: '#0ea5e9', maxBarThickness: 22 }] },
			{ tooltips: tip(function (v) { return Math.round(v).toLocaleString('en-US') + ' unidades'; }, { callbacks: { title: function (it) { return p.top_units[it[0].index].name; } } }),
				scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 50, minRotation: 30 }, TICK, { fontSize: 9.5 }) }), yAxes: [{ gridLines: GRID, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5 }, TICK) }] } }));

		var byId = {};
		p.rows.forEach(function (r) { byId[r.id] = r; });
		make('srepProdEvo', base('line', { labels: p.days.map(dayLabel), datasets: p.top5.map(function (id, i) {
			return { label: cut(byId[id].name, 22), data: p.series[id] || [], borderColor: PALETTE[i], backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, tension: 0.3 };
		}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		make('srepProdBranch', base('bar', { labels: p.branches.map(function (b) { return cut(b.name, 12); }), datasets: p.top5.map(function (id, i) {
			return { label: cut(byId[id].name, 22), data: p.branch_matrix[id] || [], backgroundColor: PALETTE[i], maxBarThickness: 8 };
		}) }, { legend: legendOn(), tooltips: tip(money, { callbacks: { title: function (it) { return p.branches[it[0].index].name; } } }), scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45 }, TICK, { fontSize: 9.5 }) }), yAxes: yMoney() } }));

		var vr = p.gainers.concat(p.losers);
		if (vr.length) {
			hbar('srepProdVar', vr.map(function (r) { return cut(r.name, 24); }), vr.map(function (r) { return r.delta_abs; }), vr.map(function (r) { return r.delta_abs >= 0 ? GREEN : RED; }),
				function (v) { return (v >= 0 ? '+' : '−') + money(Math.abs(v)); }, vr.map(function (r) { return r.name + ' · ' + (r.delta_pct >= 0 ? '+' : '') + r.delta_pct.toFixed(1) + ' %'; }));
		}
		treemap(document.getElementById('srepProdTreemap'), p.rows, D.dashboard.net);

		var body = document.querySelector('#srepProdTable tbody[data-srep-rows]');
		if (body) {
			body.innerHTML = p.rows.map(function (r, i) {
				var dv = r.prev_net === null ? '<span class="ded-text-muted-italics">' + NA + '</span>' : (r.prev_net > 0 ? signed(r.delta_pct) : '<span class="ded-text-muted-italics">Nuevo en el período</span>');
				return '<tr><td data-v="' + (i + 1) + '"><span class="ded-rank-circle">' + (i + 1) + '</span></td>'
					+ '<td><div class="ded-prod-name">' + esc(r.name) + '</div></td><td><span class="ded-sku-badge">' + esc(r.sku || '—') + '</span></td>'
					+ '<td>' + esc(r.dept) + '</td><td>' + esc(r.category) + '</td>'
					+ '<td class="ded-table-num" data-v="' + r.units + '" data-export-value="' + r.units + '">' + Math.round(r.units).toLocaleString('en-US') + '</td>'
					+ '<td class="ded-table-price" data-v="' + r.net + '" data-export-value="' + r.net + '">' + money(r.net, 2) + '</td>'
					+ '<td class="ded-table-share" data-v="' + (r.share_pct === null ? -1 : r.share_pct) + '">' + pct(r.share_pct) + '</td>'
					+ '<td class="text-right" data-v="' + (r.delta_pct === null ? -99999 : r.delta_pct) + '" data-export-value="' + (r.prev_net === null ? NA : r.prev_net > 0 ? (r.delta_pct === null ? NA : r.delta_pct + ' %') : 'Nuevo en el período') + '">' + dv + '</td>'
					+ '<td>' + esc(r.leader || NA) + '</td></tr>';
			}).join('');
			paginate(document.getElementById('srepProdTable'));
		}
	}

	// Treemap «squarified» en HTML: 20 productos principales + resto
	function treemap(el, rows, total) {
		if (!el) return;
		var top = rows.filter(function (r) { return r.net > 0; }).slice(0, 20);
		var rest = total - top.reduce(function (s, r) { return s + r.net; }, 0);
		var items = top.map(function (r, i) { return { name: r.name, v: r.net, c: PALETTE[i % PALETTE.length] }; });
		if (rest > 0.5) items.push({ name: 'Resto de productos', v: rest, c: '#94a3b8' });
		var W = el.clientWidth || 400, H = el.clientHeight || 195, sum = items.reduce(function (s, i) { return s + i.v; }, 0);
		if (!sum) { el.innerHTML = '<span class="ded-text-muted-italics">' + NA + '</span>'; return; }
		var scale = W * H / sum, out = [], x = 0, y = 0, w = W, h = H, rowItems = [];
		items.forEach(function (i) { i.a = i.v * scale; });
		var worst = function (r, side) { var s = 0, mx = 0, mn = Infinity; r.forEach(function (i) { s += i.a; mx = Math.max(mx, i.a); mn = Math.min(mn, i.a); }); return Math.max(side * side * mx / (s * s), (s * s) / (side * side * mn)); };
		var layout = function (r) {
			var s = r.reduce(function (t, i) { return t + i.a; }, 0);
			if (w >= h) { var cw = s / h, cy = y; r.forEach(function (i) { var ch = i.a / cw; out.push([i, x, cy, cw, ch]); cy += ch; }); x += cw; w -= cw; }
			else { var rh = s / w, cx = x; r.forEach(function (i) { var cw2 = i.a / rh; out.push([i, cx, y, cw2, rh]); cx += cw2; }); y += rh; h -= rh; }
		};
		var queue = items.slice();
		while (queue.length) {
			var side = Math.min(w, h), next = queue[0];
			if (!rowItems.length || worst(rowItems.concat([next]), side) <= worst(rowItems, side)) { rowItems.push(queue.shift()); }
			else { layout(rowItems); rowItems = []; }
		}
		if (rowItems.length) layout(rowItems);
		el.innerHTML = out.map(function (o) {
			var i = o[0], share = (i.v / total * 100).toFixed(1);
			var big = o[3] > 70 && o[4] > 34;
			return '<div class="srep-tm-cell" style="left:' + o[1].toFixed(1) + 'px;top:' + o[2].toFixed(1) + 'px;width:' + o[3].toFixed(1) + 'px;height:' + o[4].toFixed(1) + 'px;background:' + i.c + '" title="' + esc(i.name) + ': ' + money(i.v) + ' · ' + share + ' %">'
				+ (big ? '<strong>' + esc(i.name) + '</strong>' + share + ' %' : '') + '</div>';
		}).join('');
	}

	/* ------------------------------------------------------------------ 4. Sucursales */
	function buildBranches() {
		var b = D.branches;
		// Solo sucursales con datos: las «Sin datos» quedan en la tabla, nunca como cero en los gráficos
		var rows = b.rows.filter(function (r) { return !r.no_data; });
		if (!rows.length) return;
		var color = function (i) { return PALETTE[i % PALETTE.length]; };
		var colors = rows.map(function (r, i) { return color(i); });
		document.querySelectorAll('#srep-branches [data-srep-color]').forEach(function (el) { el.style.backgroundColor = color(parseInt(el.getAttribute('data-srep-color'), 10)); });
		var labels = rows.map(function (r) { return r.name; });
		var full = rows.map(function (r) { return r.name + (r.code ? ' (' + r.code + ')' : ''); });
		var rankColor = function (r) { return r.rank ? GREEN : '#cbd5e1'; };
		var bars = hbar('srepBranchBars', rows.map(function (r) { return (r.rank ? r.rank + '. ' : '— ') + r.name; }), rows.map(function (r) { return r.net; }), rows.map(rankColor), null, full);
		if (bars) bars.options.tooltips.callbacks.afterLabel = function (it) { var r = rows[it.index]; return r.rank ? 'Participación: ' + pct(r.share_pct) : 'Período incompleto: ' + r.active_days + ' de ' + r.network_days + ' días'; };
		var donut = make('srepBranchDonut', base('doughnut', { labels: full, datasets: [{ data: rows.map(function (r) { return r.net; }), backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
			{ cutoutPercentage: 72, tooltips: tip(function (v) { return money(v) + ' · ' + pct(b.reconciliation.total > 0 ? v / b.reconciliation.total * 100 : null); }, { mode: 'nearest' }) }));
		make('srepBranchVsPrev', base('bar', { labels: labels.map(function (l) { return cut(l, 12); }), datasets: [
			{ label: 'Actual', data: rows.map(function (r) { return r.net; }), backgroundColor: GREEN, maxBarThickness: 14 },
			{ label: 'Anterior', data: rows.map(function (r) { return r.prev_net; }), backgroundColor: GRAY, maxBarThickness: 14 }
		] }, { tooltips: tip(money, { callbacks: { title: function (it) { return full[it[0].index]; } } }), scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45 }, TICK, { fontSize: 9.5 }) }), yAxes: yMoney() } }));

		// Crecimiento semanal, mensual y anual (ventanas de igual duración)
		var growth = function (key) {
			var vals = rows.map(function (r) { return r.growth[key]; });
			var ch = hbar('srepBranchGrowth', labels, vals.map(function (v) { return v === null ? 0 : v; }), vals.map(function (v) { return v === null ? '#e2e8f0' : (v >= 0 ? GREEN : RED); }),
				function (v) { return (v >= 0 ? '+' : '') + Number(v).toFixed(1) + ' %'; }, full);
			if (ch) {
				ch.options.tooltips.callbacks.label = function (it) { var v = vals[it.index]; return v === null ? NA + ' (sin venta en la ventana anterior)' : (v >= 0 ? '+' : '') + v.toFixed(1) + ' %'; };
				ch.options.scales.xAxes[0].ticks.callback = function (v) { return v + ' %'; };
				ch.update();
			}
			document.querySelectorAll('#srep-branches [data-srep-bgrowth]').forEach(function (x) { x.classList.toggle('is-active', x.getAttribute('data-srep-bgrowth') === key); });
			document.querySelectorAll('#srep-branches [data-srep-bgrowth-note]').forEach(function (p) { p.hidden = p.getAttribute('data-srep-bgrowth-note') !== key; });
		};
		document.querySelectorAll('#srep-branches [data-srep-bgrowth]').forEach(function (x) { x.addEventListener('click', function () { growth(x.getAttribute('data-srep-bgrowth')); }); });
		growth('week');

		// Ticket promedio (línea) y transacciones (barras)
		make('srepBranchTicket', base('bar', { labels: labels.map(function (l) { return cut(l, 12); }), datasets: [
			{ type: 'line', label: 'Ticket promedio', data: rows.map(function (r) { return r.avg_ticket; }), borderColor: GREEN, backgroundColor: GREEN, fill: false, borderWidth: 2, pointRadius: 3, yAxisID: 'tk' },
			{ label: 'Transacciones', data: rows.map(function (r) { return r.tickets; }), backgroundColor: '#cbd5e1', maxBarThickness: 18, yAxisID: 'tx' }
		] }, { tooltips: tip(money, { mode: 'index', callbacks: { title: function (it) { return full[it[0].index]; }, label: function (it, data) {
			var v = data.datasets[it.datasetIndex].data[it.index];
			return it.datasetIndex === 0 ? 'Ticket promedio: ' + money(v, 2) + ' (' + signed(rows[it.index].avg_ticket_delta) + ')' : 'Transacciones: ' + Number(v).toLocaleString('en-US');
		} } }), scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45 }, TICK, { fontSize: 9.5 }) }),
			yAxes: [{ id: 'tx', position: 'left', gridLines: GRID, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: function (v) { return Number(v).toLocaleString('en-US'); } }, TICK) },
				{ id: 'tk', position: 'right', gridLines: { display: false }, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: function (v) { return '$' + v; } }, TICK) }] } }));

		// Margen bruto por tienda
		var mg = rows.filter(function (r) { return r.margin_pct !== null; }).sort(function (a, c) { return c.margin_pct - a.margin_pct; });
		var mch = hbar('srepBranchMargin', mg.map(function (r) { return r.name; }), mg.map(function (r) { return r.margin_pct; }), mg.map(function (r) { return r.margin_delta_pp === null || r.margin_delta_pp >= 0 ? GREEN : AMBER; }), pct,
			mg.map(function (r) { return r.name + (r.code ? ' (' + r.code + ')' : ''); }));
		if (mch) mch.options.tooltips.callbacks.afterLabel = function (it) { var r = mg[it.index]; return 'Utilidad bruta: ' + money(r.gross_profit, 2) + (r.margin_delta_pp === null ? '' : ' · ' + (r.margin_delta_pp >= 0 ? '+' : '') + r.margin_delta_pp.toFixed(1) + ' pp vs. anterior'); };

		// Ventas por hora (cinco principales o la seleccionada; ventas o % del día)
		var hourId = '', hourMode = 'net';
		var H = b.hourly;
		var hourly = function () {
			if (H.first === null) return;
			var list = hourId ? rows.filter(function (r) { return String(r.id) === String(hourId); }) : rows.slice(0, 5);
			var hours = []; for (var x = H.first; x <= H.last; x++) hours.push(x);
			var isShare = hourMode === 'share';
			make('srepBranchHourly', base('line', { labels: hours.map(function (x) { return (x < 10 ? '0' : '') + x + ':00'; }), datasets: list.map(function (r) {
				var s = H.series[r.id] || [], i = rows.indexOf(r);
				var tot = s.reduce(function (a, v) { return a + v; }, 0);
				return { label: r.name, data: hours.map(function (x) { return isShare ? (tot > 0 ? Math.round((s[x] || 0) / tot * 1000) / 10 : 0) : (s[x] || 0); }), borderColor: color(i), backgroundColor: hourId ? alpha(color(i), 0.12) : 'transparent', fill: !!hourId, borderWidth: 2, pointRadius: 2, tension: 0.3 };
			}) }, { legend: legendOn(), tooltips: tip(isShare ? pct : money), scales: { xAxes: xCat(), yAxes: isShare ? [{ gridLines: GRID, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }, TICK) }] : yMoney() } }));
		};
		document.querySelectorAll('#srep-branches [data-srep-bhour]').forEach(function (x) {
			x.addEventListener('click', function () {
				hourMode = x.getAttribute('data-srep-bhour');
				document.querySelectorAll('#srep-branches [data-srep-bhour]').forEach(function (y) { y.classList.toggle('is-active', y === x); });
				hourly();
			});
		});

		// Evolución diaria del período y evolución histórica mensual
		var evo = function (id) {
			var list = id ? rows.filter(function (r) { return String(r.id) === String(id); }) : rows.filter(function (r) { return b.top5.indexOf(r.id) >= 0; });
			make('srepBranchEvo', base('line', { labels: b.days.map(dayLabel), datasets: list.map(function (r) {
				var i = rows.indexOf(r);
				return { label: r.name, data: b.series[r.id] || [], borderColor: color(i), backgroundColor: id ? alpha(color(i), 0.12) : 'transparent', fill: !!id, borderWidth: 2, pointRadius: 0, tension: 0.3 };
			}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
			var hm = b.history;
			if (document.getElementById('srepBranchHistory')) {
				make('srepBranchHistory', base('line', { labels: hm.months.map(monthLabel), datasets: list.map(function (r) {
					var i = rows.indexOf(r);
					return { label: r.name, data: hm.series[r.id] || [], borderColor: color(i), backgroundColor: id ? alpha(color(i), 0.12) : 'transparent', fill: !!id, borderWidth: 2, pointRadius: 2, tension: 0.25, spanGaps: false };
				}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
			}
			['srepBranchEvoNote', 'srepBranchHistNote'].forEach(function (n) { var el = document.getElementById(n); if (el) el.textContent = id ? full[rows.indexOf(list[0])] : 'Cinco principales'; });
		};

		var detail = document.getElementById('srepBranchDetail');
		var select = document.getElementById('srepBranchSelect');
		var G = b.growth;
		var show = function (id) {
			var r = rows.filter(function (x) { return String(x.id) === String(id); })[0];
			var d = D.dashboard;
			var items = r ? [
				['Posición', r.rank ? r.rank + ' de ' + b.ranked + (r.prev_rank ? ' (antes ' + r.prev_rank + ')' : '') : 'Período incompleto (' + r.active_days + ' de ' + r.network_days + ' días)'],
				['Ventas netas', money(r.net, 2)], ['Participación en la red', pct(r.share_pct)], ['Período anterior', money(r.prev_net, 2)], ['Variación', signed(r.delta_pct)],
				['Crecimiento semanal', signed(r.growth.week)], ['Crecimiento mensual', signed(r.growth.month)], ['Crecimiento anual', signed(r.growth.year)],
				['Meta', money(r.goal)], ['Cumplimiento', pct(r.goal_pct)], ['Transacciones', Number(r.tickets).toLocaleString('en-US')], ['Ticket promedio', money(r.avg_ticket, 2)],
				['Variación del ticket', signed(r.avg_ticket_delta)], ['Utilidad bruta', money(r.gross_profit, 2)], ['Margen bruto', pct(r.margin_pct)], ['Venta por día operado', money(r.net_per_day, 2)]
			] : [
				['Sucursales con venta', String(rows.length) + (b.no_data.length ? ' (' + b.no_data.length + ' sin datos)' : '')], ['Ventas netas', money(d.net, 2)], ['Meta', money(d.goal)], ['Cumplimiento', pct(d.goal_pct)],
				['Transacciones', d.tickets.toLocaleString('en-US')], ['Ticket promedio', money(d.avg_ticket, 2)], ['Variación', signed(d.growth_pct)],
				['Crecimiento semanal', signed(G.week.delta_pct)], ['Crecimiento mensual', signed(G.month.delta_pct)], ['Crecimiento anual', signed(G.year.delta_pct)]
			];
			detail.innerHTML = items.map(function (i) { return '<div class="ded-comp-row"><span class="ded-comp-label">' + i[0] + '</span><span class="ded-comp-val">' + (/^</.test(i[1]) ? i[1] : esc(i[1])) + '</span></div>'; }).join('');
			var idx = rows.findIndex(function (x) { return String(x.id) === String(id); });
			if (bars) { bars.data.datasets[0].backgroundColor = rows.map(function (x, i) { return idx < 0 || i === idx ? rankColor(x) : alpha(rankColor(x), 0.25); }); bars.update(); }
			if (donut) { donut.data.datasets[0].backgroundColor = colors.map(function (c, i) { return idx < 0 || i === idx ? c : alpha(c, 0.25); }); donut.update(); }
			document.querySelectorAll('#srep-branches [data-srep-branch-row]').forEach(function (tr) { tr.classList.toggle('is-selected', !!id && tr.getAttribute('data-srep-branch-row') === String(id)); });
			document.querySelectorAll('#srepBranchMap [data-srep-branch]').forEach(function (n) { n.classList.toggle('is-selected', !!id && n.getAttribute('data-srep-branch') === String(id)); });
			evo(id);
			hourId = id;
			hourly();
		};
		if (select) select.addEventListener('change', function () { show(select.value); });
		var pick = function (id) { if (select) { select.value = select.value === String(id) ? '' : String(id); show(select.value); } };
		document.querySelectorAll('#srep-branches [data-srep-branch]').forEach(function (n) { n.addEventListener('click', function () { pick(n.getAttribute('data-srep-branch')); }); });
		[bars, donut].forEach(function (ch) {
			if (ch) ch.canvas.addEventListener('click', function (e) { var a = ch.getElementAtEvent(e)[0]; if (a) pick(rows[a._index].id); });
		});
		show('');
	}

	/* ------------------------------------------------------------------ 5. Historial de ventas */
	function buildHistory() {
		var h = D.history;
		var timeline = function (grain) {
			var t = h.timeline[grain];
			var fmt = grain === 'day' ? dayLabel : (grain === 'week' ? function (d) { return 'Sem. ' + dayLabel(d); } : (grain === 'month' ? monthLabel : String));
			var isBar = grain === 'month' || grain === 'year';
			make('srepHistTimeline', base(isBar ? 'bar' : 'line', { labels: t.labels.map(fmt), datasets: [{ label: 'Ventas netas', data: t.net, borderColor: GREEN, backgroundColor: isBar ? GREEN : 'rgba(16, 185, 129, 0.12)', fill: !isBar, borderWidth: isBar ? 0 : 2.2, pointRadius: grain === 'week' ? 3 : 0, tension: 0.3, maxBarThickness: 28 }] },
				{ tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
		};
		timeline('day');
		document.querySelectorAll('[data-srep-grain]').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('[data-srep-grain]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				timeline(b.getAttribute('data-srep-grain'));
			});
		});

		make('srepHistYears', base('bar', { labels: h.years.map(function (y) { return y.has_data ? String(y.year) : y.year + ' (sin información)'; }), datasets: [{ label: 'Ventas netas', data: h.years.map(function (y) { return y.net; }), backgroundColor: h.years.map(function (y, i) { return i === h.years.length - 1 ? GREEN : '#0ea5e9'; }), maxBarThickness: 46 }] },
			{ tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		var withData = h.years.filter(function (y) { return y.has_data; });
		make('srepHistOverlay', base('line', { labels: MONTHS, datasets: withData.map(function (y, i) {
			return { label: String(y.year), data: y.months, borderColor: [GREEN, '#0ea5e9', AMBER][withData.length - 1 - i] || PALETTE[i], backgroundColor: 'transparent', borderWidth: 2, pointRadius: 2, spanGaps: false, tension: 0.3 };
		}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		make('srepHistWeekday', base('bar', { labels: h.weekday.labels, datasets: [
			{ type: 'line', label: 'Promedio por día', data: h.weekday.avg, borderColor: AMBER, backgroundColor: 'transparent', borderWidth: 2, pointRadius: 3, pointBackgroundColor: AMBER, fill: false, yAxisID: 'avg', order: 1 },
			{ label: 'Total', data: h.weekday.net, backgroundColor: GREEN, maxBarThickness: 28, yAxisID: 'tot', order: 2 }
		] }, { tooltips: tip(), scales: { xAxes: xCat(), yAxes: [Object.assign(yMoney()[0], { id: 'tot', position: 'left' }), Object.assign(yMoney()[0], { id: 'avg', position: 'right', gridLines: { display: false, drawBorder: false } })] } }));

		make('srepHistWeekends', base('bar', { labels: h.weekends.map(function (w) { return dayLabel(w.sat); }), datasets: [{ label: 'Sábado + domingo', data: h.weekends.map(function (w) { return w.net; }), backgroundColor: h.weekends.map(function (w, i) { return i === h.weekends.length - 1 ? GREEN : alpha(GREEN, 0.45); }), maxBarThickness: 22 }] },
			{ tooltips: tip(money, { callbacks: { title: function (it) { var w = h.weekends[it[0].index]; return dayLabel(w.sat) + ' – ' + dayLabel(w.sun); } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		// Calendario: columnas = semanas (lunes a domingo)
		var cal = document.getElementById('srepHistCalendar');
		if (cal && h.calendar.length) {
			var max = Math.max.apply(null, h.calendar.map(function (c) { return c[1]; })) || 1;
			var lead = (new Date(h.calendar[0][0] + 'T00:00:00').getDay() + 6) % 7;
			var html = '';
			for (var i = 0; i < lead; i++) html += '<i class="is-out"></i>';
			h.calendar.forEach(function (c) {
				html += '<i class="' + (c[1] > 0 ? '' : 'is-zero') + '" style="--srep-a:' + (c[1] / max).toFixed(2) + '" title="' + c[0].split('-').reverse().join('/') + ': ' + (c[1] > 0 ? money(c[1]) : 'sin ventas') + '"></i>';
			});
			cal.innerHTML = html;
		}

		// Curva acumulada: actual, comparación y año anterior por número de día del período
		var cu = h.cumulative, n = Math.max(cu.net.length, cu.prev.length, cu.ly ? cu.ly.length : 0);
		var idx = []; for (var j = 1; j <= n; j++) idx.push('Día ' + j);
		var cds = [{ label: 'Período actual', data: cu.net, borderColor: GREEN, backgroundColor: 'rgba(16, 185, 129, 0.10)', fill: true, borderWidth: 2.2, pointRadius: 0, tension: 0.2 },
			{ label: 'Comparación (' + cu.prev_label + ')', data: cu.prev, borderColor: GRAY, backgroundColor: 'transparent', borderDash: [5, 4], borderWidth: 2, pointRadius: 0, tension: 0.2 }];
		if (cu.ly) cds.push({ label: 'Año anterior', data: cu.ly, borderColor: '#0ea5e9', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, tension: 0.2 });
		make('srepHistCumulative', base('line', { labels: idx, datasets: cds }, { legend: legendOn(), tooltips: tip(money, { callbacks: { title: function (it) { var i = it[0].index; return (cu.labels[i] ? dayLabel(cu.labels[i]) : '') + ' · ' + idx[i]; } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		// Semana contra semana: lunes a domingo
		var w = h.wow;
		make('srepHistWow', base('bar', { labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'], datasets: [
			{ label: 'Semana actual', data: w.days.map(function (d) { return d.net; }), backgroundColor: GREEN, maxBarThickness: 18 },
			{ label: 'Semana anterior', data: w.days.map(function (d) { return d.prev_net; }), backgroundColor: GRAY, maxBarThickness: 18 }
		] }, { legend: legendOn(), tooltips: tip(money, { callbacks: { title: function (it) { var d = w.days[it[0].index]; return dayLabel(d.date) + ' vs. ' + dayLabel(d.prev_date); } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		// Histórico mensual por dimensión (las 6 primeras series en el gráfico; todas en la tabla)
		var dimData = { branch: h.by_branch, dept: h.by_dept, sku: h.by_sku, channel: h.by_channel };
		var dim = function (key) {
			var rows = (dimData[key] || []).slice(0, 6);
			make('srepHistDim', base('line', { labels: h.months.map(monthLabel), datasets: rows.map(function (r, i) {
				return { label: cut(r.name, 28), data: r.months, borderColor: PALETTE[i % PALETTE.length], backgroundColor: 'transparent', borderWidth: 2, pointRadius: 2, spanGaps: false, tension: 0.3 };
			}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
			document.querySelectorAll('[data-srep-hdim]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-srep-hdim') === key); });
			document.querySelectorAll('[data-srep-hdim-table]').forEach(function (t) { t.hidden = t.getAttribute('data-srep-hdim-table') !== key; });
		};
		dim('branch');
		document.querySelectorAll('[data-srep-hdim]').forEach(function (b) { b.addEventListener('click', function () { dim(b.getAttribute('data-srep-hdim')); }); });
		initAudit();
	}

	/* Correcciones autorizadas y cierres comerciales (API /sales/history/...) */
	function initAudit() {
		var root = document.getElementById('srepHistAudit');
		if (!root || !window.DairyUI) return;
		var reason = '<div class="form-group"><label>Motivo (obligatorio)</label><textarea class="form-control" name="reason" rows="2" required minlength="5"></textarea></div>';
		var send = function (url, body, dlg) { DairyUI.post(url, body, function () { dlg.modal('hide'); toastr.success('Registrado'); DairyUI.reload(); }); };
		root.addEventListener('click', function (e) {
			var b = e.target.closest('[data-hist-void], [data-hist-date], [data-hist-close]');
			if (!b) return;
			if (b.hasAttribute('data-hist-void')) {
				DairyUI.formDialog('Anular copia ' + b.getAttribute('data-ticket'), '<p class="small">Se conserva el ticket ' + esc(b.getAttribute('data-keep')) + '. La copia se cancela y su inventario se reintegra.</p>' + reason, function (d, dlg) {
					send('sales/history/corrections', { kind: 'duplicate', sale_id: parseInt(b.getAttribute('data-hist-void'), 10), reason: d.reason }, dlg);
				}, 'Anular copia');
			} else if (b.hasAttribute('data-hist-date')) {
				DairyUI.formDialog('Corregir fecha de venta · ' + b.getAttribute('data-ticket'), '<div class="form-group"><label>Fecha y hora de venta</label><input type="datetime-local" class="form-control" name="datetime" value="' + esc(b.getAttribute('data-recorded')).replace(' ', 'T') + '" required>'
					+ '<small class="form-text text-muted">Registrada como venta del ' + esc(b.getAttribute('data-sold')) + '; grabada el ' + esc(b.getAttribute('data-recorded')) + '.</small></div>' + reason, function (d, dlg) {
					send('sales/history/corrections', { kind: 'date', sale_id: parseInt(b.getAttribute('data-hist-date'), 10), datetime: d.datetime, reason: d.reason }, dlg);
				}, 'Corregir fecha');
			} else {
				DairyUI.formDialog('Cierre comercial · ' + b.getAttribute('data-label'), '<p class="small">Se guardará la venta neta, los tickets y las devoluciones de toda la empresa en este período.</p><div class="form-group"><label>Observaciones</label><textarea class="form-control" name="notes" rows="2"></textarea></div>', function (d, dlg) {
					send('sales/history/closures', { type: b.getAttribute('data-hist-close'), from: b.getAttribute('data-from'), notes: d.notes }, dlg);
				}, 'Cerrar período');
			}
		});
	}

	/* ------------------------------------------------------------------ 6. Rentabilidad */
	function buildProfitability() {
		var p = D.profitability, dl = p.daily.days.map(dayLabel);
		var pctAxis = [{ gridLines: GRID, ticks: Object.assign({ maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }, TICK) }];
		make('srepPfMargin', base('line', { labels: dl, datasets: [{ label: 'Margen bruto', data: p.daily.margin_pct, borderColor: GREEN, backgroundColor: 'rgba(16, 185, 129, 0.10)', fill: true, borderWidth: 2.2, pointRadius: 0, tension: 0.3, spanGaps: false }] },
			{ tooltips: tip(pct), scales: { xAxes: xCat(), yAxes: pctAxis } }));
		make('srepPfNetCogs', base('bar', { labels: dl, datasets: [
			{ label: 'Ventas netas', data: p.daily.net, backgroundColor: GREEN, maxBarThickness: 10 },
			{ label: 'Costo de mercancía vendida', data: p.daily.cogs, backgroundColor: AMBER, maxBarThickness: 10 }
		] }, { tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
		var profitFmt = function (v) { return money(v); };
		hbar('srepPfDept', p.by_department.map(function (r) { return r.name; }), p.by_department.map(function (r) { return r.profit; }), p.by_department.map(function (r) { return r.color; }), profitFmt,
			p.by_department.map(function (r) { return r.name + ' · margen ' + pct(r.margin_pct); }));
		hbar('srepPfBranch', p.by_branch.map(function (r) { return r.name; }), p.by_branch.map(function (r) { return r.profit; }), GREEN, profitFmt,
			p.by_branch.map(function (r) { return r.name + ' · margen ' + pct(r.margin_pct); }));
		make('srepPfScatter', base('scatter', { datasets: [{ label: 'Productos', data: p.scatter.map(function (s) { return { x: s.net, y: s.margin_pct }; }), backgroundColor: alpha(GREEN, 0.55), borderColor: GREEN_D, pointRadius: 3.5, pointHoverRadius: 5 }] }, {
			tooltips: Object.assign({}, TOOLTIP, { mode: 'nearest', intersect: true, callbacks: { label: function (it) { var s = p.scatter[it.index]; return cut(s.name, 40) + ': ' + money(s.net) + ' · ' + pct(s.margin_pct); } } }),
			scales: { xAxes: [{ gridLines: GRID, scaleLabel: { display: true, labelString: 'Ventas netas', fontColor: '#64748b', fontSize: 10.5 }, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 6, callback: short }, TICK) }],
				yAxes: [{ gridLines: GRID, scaleLabel: { display: true, labelString: 'Margen bruto %', fontColor: '#64748b', fontSize: 10.5 }, ticks: Object.assign({ maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }, TICK) }] }
		}));
		make('srepPfTrend', base('bar', { labels: p.monthly.labels.map(monthLabel), datasets: [
			{ type: 'line', label: 'Margen bruto', data: p.monthly.margin_pct, borderColor: AMBER, backgroundColor: 'transparent', borderWidth: 2, pointRadius: 3, pointBackgroundColor: AMBER, fill: false, yAxisID: 'm', order: 1 },
			{ label: 'Utilidad bruta', data: p.monthly.profit, backgroundColor: GREEN, maxBarThickness: 26, yAxisID: 'p', order: 2 }
		] }, { tooltips: tip(money, { callbacks: { label: function (it, data) { var ds = data.datasets[it.datasetIndex]; var v = ds.data[it.index]; return ds.label + ': ' + (ds.yAxisID === 'm' ? pct(v) : money(v)); } } }),
			scales: { xAxes: xCat(), yAxes: [Object.assign(yMoney()[0], { id: 'p', position: 'left' }), { id: 'm', position: 'right', gridLines: { display: false, drawBorder: false }, ticks: Object.assign({ maxTicksLimit: 5, callback: function (v) { return v + ' %'; } }, TICK) }] } }));
	}

	/* ------------------------------------------------------------------ tablas: orden, búsqueda y paginado */
	var PAGE = 25;
	function paginate(table) {
		if (!table.classList.contains('srep-paged')) return;
		var all = table.getAttribute('data-expanded') === '1', q = (table.getAttribute('data-q') || '').toLowerCase(), n = 0;
		table.querySelectorAll('tbody tr').forEach(function (tr) {
			var match = !q || tr.textContent.toLowerCase().indexOf(q) >= 0;
			var visible = match && (all || q || n < PAGE);
			if (match) n++;
			tr.classList.toggle('srep-hidden', !visible);
		});
	}
	function initTables(root) {
		root.querySelectorAll('.srep-sortable').forEach(function (table) {
			table.querySelectorAll('th[data-sort]').forEach(function (th, col) {
				th.addEventListener('click', function () {
					var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
					var asc = !th.classList.contains('is-asc');
					table.querySelectorAll('th').forEach(function (x) { x.classList.remove('is-asc', 'is-desc'); });
					th.classList.add(asc ? 'is-asc' : 'is-desc');
					var num = th.getAttribute('data-sort') === 'num';
					var body = table.tBodies[0];
					var allRows = Array.prototype.slice.call(body.rows);
					var childMap = {};
					var trs = allRows.filter(function (tr) {
						var p = tr.getAttribute('data-parent-dept');
						if (p) {
							childMap[p] = childMap[p] || [];
							childMap[p].push(tr);
							return false;
						}
						return true;
					});
					trs.sort(function (a, b) {
						var x = a.cells[idx], y = b.cells[idx];
						var vx = num ? parseFloat(x.getAttribute('data-v') || x.textContent.replace(/[^0-9.\-]/g, '')) : x.textContent.trim().toLowerCase();
						var vy = num ? parseFloat(y.getAttribute('data-v') || y.textContent.replace(/[^0-9.\-]/g, '')) : y.textContent.trim().toLowerCase();
						return (vx > vy ? 1 : vx < vy ? -1 : 0) * (asc ? 1 : -1);
					});
					trs.forEach(function (tr) {
						body.appendChild(tr);
						var key = tr.getAttribute('data-srep-dept-row');
						if (key && childMap[key]) {
							childMap[key].forEach(function (sr) { body.appendChild(sr); });
						}
					});
					paginate(table);
				});
			});
			paginate(table);
		});
		root.querySelectorAll('[data-srep-search]').forEach(function (input) {
			var table = document.querySelector(input.getAttribute('data-srep-search'));
			input.addEventListener('input', function () { table.setAttribute('data-q', input.value.trim()); paginate(table); });
		});
		root.querySelectorAll('[data-srep-more]').forEach(function (btn) {
			var table = document.querySelector(btn.getAttribute('data-srep-more'));
			var label = btn.textContent;
			btn.addEventListener('click', function () {
				var on = table.getAttribute('data-expanded') !== '1';
				table.setAttribute('data-expanded', on ? '1' : '0');
				btn.textContent = on ? 'Ver solo los ' + PAGE + ' primeros' : label;
				paginate(table);
			});
		});
	}

	/* ------------------------------------------------------------------ exportación */
	function sectionTitle(sec) { var t = sec.querySelector('.srep-head .ded-card-title'); return t ? t.textContent.trim() : 'Informe comercial'; }
	function sheetsOf(sec) {
		var sheets = [];
		var head = [['Informe', sectionTitle(sec)], ['Pregunta', (sec.querySelector('.srep-head .ded-subtitle') || {}).textContent || ''], ['Alcance', ((sec.querySelector('[data-testid="srep-period"]') || {}).textContent || '').replace(/\s+/g, ' ').trim()], ['Generado', new Date().toLocaleString()], []];
		head.push(['Indicador', 'Valor', 'Detalle']);
		sec.querySelectorAll('.srep-kpis .ded-kpi-card, .srep-dept-kpis-5 .ded-kpi-card').forEach(function (k) {
			var g = function (s) { var e = k.querySelector(s); return e ? e.textContent.replace(/\s+/g, ' ').trim() : ''; };
			head.push([g('.ded-kpi-label'), g('.ded-kpi-val') || g('.srep-acc'), g('.ded-kpi-sub')]);
		});
		sheets.push(['Indicadores', head]);
		sec.querySelectorAll('table').forEach(function (t, i) {
			var rows = [Array.prototype.map.call(t.tHead.rows[0].cells, function (c) { return (c.getAttribute('data-export-value') || c.textContent).replace(/[▲▼]/g, '').trim(); })];
			Array.prototype.forEach.call(t.tBodies[0].rows, function (tr) {
				if (tr.style && tr.style.display === 'none') return;
				rows.push(Array.prototype.map.call(tr.cells, function (c) {
					var v = c.getAttribute('data-export-value');
					if (v === null && c.hasAttribute('data-v') && /\$|%/.test(c.textContent) && parseFloat(c.getAttribute('data-v')) >= 0) v = c.getAttribute('data-v');
					v = v !== null ? v : c.textContent.replace(/\s+/g, ' ').trim();
					return /^-?\d+(\.\d+)?$/.test(v) ? Number(v) : v;
				}));
			});
			sheets.push([t.getAttribute('data-export-name') || ('Tabla ' + (i + 1)), rows]);
		});
		if (sec.getAttribute('data-srep') === 'departments' && D && D.departments && D.departments.rows) {
			var subSheet = [['Departamento', 'Subcategoría', 'Ventas netas', 'Part. Depto %', 'Part. Total %', 'Anterior', 'Variación $', 'Variación %', 'Unidades', 'Tickets', 'Precio promedio', 'SKUs']];
			D.departments.rows.forEach(function (r) {
				(r.subcategories || []).forEach(function (s) {
					subSheet.push([r.name, s.name, s.net, s.share_dept_pct, s.share_total_pct, s.prev_net !== null ? s.prev_net : NA, s.delta_abs !== null ? s.delta_abs : NA, s.delta_pct !== null ? s.delta_pct : NA, s.units, s.tickets !== null ? s.tickets : NA, s.avg_unit_price !== null ? s.avg_unit_price : NA, s.sku_count]);
				});
			});
			if (subSheet.length > 1) sheets.push(['Subcategorías', subSheet]);
		}
		sec.querySelectorAll('canvas').forEach(function (c) {
			var ch = charts[c.id];
			if (!ch) return;
			var title = ((c.closest('.ded-card') || {}).querySelector ? c.closest('.ded-card').querySelector('.ded-card-title').textContent.trim() : c.id);
			var ds = ch.data.datasets, rows;
			if (ch.config.type === 'scatter') {
				rows = [['Producto', 'Ventas netas', 'Margen bruto %']].concat(D.profitability.scatter.map(function (s) { return [s.name, s.net, s.margin_pct]; }));
			} else {
				rows = [['Etiqueta'].concat(ds.map(function (d) { return d.label || 'Valor'; }))];
				(ch.data.labels || []).forEach(function (l, i) { rows.push([String(l)].concat(ds.map(function (d) { var v = d.data[i]; return v === null || v === undefined ? '' : v; }))); });
			}
			sheets.push([title, rows]);
		});
		return sheets;
	}

	// XLSX mínimo (ZIP sin compresión + SpreadsheetML) para no depender de librerías externas
	function xlsx(sheets) {
		var x = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, ''); };
		var col = function (n) { var s = ''; n++; while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
		var used = {}, names = sheets.map(function (s) {
			var n = String(s[0]).replace(/[\[\]:*?\/\\]/g, ' ').slice(0, 28).trim() || 'Hoja', b = n, k = 2;
			while (used[n.toLowerCase()]) n = b.slice(0, 26) + ' ' + (k++);
			used[n.toLowerCase()] = 1;
			return n;
		});
		var files = [];
		files.push(['[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			+ names.map(function (n, i) { return '<Override PartName="/xl/worksheets/sheet' + (i + 1) + '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'; }).join('') + '</Types>']);
		files.push(['_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>']);
		files.push(['xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
			+ names.map(function (n, i) { return '<sheet name="' + x(n) + '" sheetId="' + (i + 1) + '" r:id="rId' + (i + 1) + '"/>'; }).join('') + '</sheets></workbook>']);
		files.push(['xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			+ names.map(function (n, i) { return '<Relationship Id="rId' + (i + 1) + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' + (i + 1) + '.xml"/>'; }).join('') + '</Relationships>']);
		sheets.forEach(function (s, si) {
			var body = s[1].map(function (row, ri) {
				return '<row r="' + (ri + 1) + '">' + row.map(function (v, ci) {
					var ref = col(ci) + (ri + 1);
					if (typeof v === 'number' && isFinite(v)) return '<c r="' + ref + '"><v>' + v + '</v></c>';
					return v === '' || v === null || v === undefined ? '' : '<c r="' + ref + '" t="inlineStr"><is><t xml:space="preserve">' + x(v) + '</t></is></c>';
				}).join('') + '</row>';
			}).join('');
			files.push(['xl/worksheets/sheet' + (si + 1) + '.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' + body + '</sheetData></worksheet>']);
		});
		return zip(files);
	}
	var CRC = (function () { var t = [], c; for (var n = 0; n < 256; n++) { c = n; for (var k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
	function crc32(b) { var c = 0xFFFFFFFF; for (var i = 0; i < b.length; i++) c = CRC[(c ^ b[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; }
	function zip(files) {
		var enc = new TextEncoder(), parts = [], central = [], offset = 0;
		var u16 = function (v) { return [v & 255, (v >>> 8) & 255]; }, u32 = function (v) { return [v & 255, (v >>> 8) & 255, (v >>> 16) & 255, (v >>> 24) & 255]; };
		files.forEach(function (f) {
			var name = enc.encode(f[0]), data = enc.encode(f[1]), crc = crc32(data);
			var common = [].concat(u16(20), u16(0x0800), u16(0), u16(0), u16(0x21), u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0));
			var local = new Uint8Array([].concat(u32(0x04034b50), common));
			parts.push(local, name, data);
			central.push(new Uint8Array([].concat(u32(0x02014b50), u16(20), common, u16(0), u16(0), u16(0), u32(0), u32(offset))), name);
			offset += local.length + name.length + data.length;
		});
		var csize = central.reduce(function (s, p) { return s + p.length; }, 0);
		var end = new Uint8Array([].concat(u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length), u32(csize), u32(offset), u16(0)));
		return new Blob(parts.concat(central, [end]), { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
	}
	function fileName(sec, ext) {
		var p = D && D.period ? D.period.from + '_' + D.period.to : 'periodo';
		return 'ventas_' + sec.getAttribute('data-srep') + '_' + p + '.' + ext;
	}
	function exportExcel(sec) {
		ensure(sec.getAttribute('data-srep'));
		var blob = xlsx(sheetsOf(sec));
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = fileName(sec, 'xlsx');
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
	}
	// CSV (UTF-8 con BOM, separador «;» para Excel en español): cada hoja como un bloque con su título
	function exportCsv(sec) {
		ensure(sec.getAttribute('data-srep'));
		var q = function (v) { v = v === null || v === undefined ? '' : String(v); return /[";\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
		var out = [];
		sheetsOf(sec).forEach(function (s) { out.push(q(s[0])); s[1].forEach(function (r) { out.push(r.map(q).join(';')); }); out.push(''); });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob(['﻿' + out.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
		a.download = fileName(sec, 'csv');
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
	}
	function exportPdf(sec) {
		ensure(sec.getAttribute('data-srep'));
		var clone = sec.cloneNode(true);
		var src = sec.querySelectorAll('canvas'), dst = clone.querySelectorAll('canvas');
		Array.prototype.forEach.call(src, function (c, i) {
			var img = document.createElement('img');
			try { img.src = c.toDataURL('image/png'); } catch (e) { }
			img.style.cssText = 'width:100%;height:auto;max-height:' + (c.parentNode.clientHeight || 260) + 'px;object-fit:contain;';
			dst[i].parentNode.style.height = 'auto';
			dst[i].replaceWith(img);
		});
		clone.querySelectorAll('.srep-export, [data-srep-more], .srep-search, .srep-search-wrap, .srep-dept-chk, .srep-expand-btn, select').forEach(function (e) { e.remove(); });
		clone.querySelectorAll('tr.srep-hidden').forEach(function (e) { e.classList.remove('srep-hidden'); });
		var w = window.open('', '_blank');
		if (!w) { if (window.toastr) toastr.warning('Permita las ventanas emergentes para exportar el PDF.'); return; }
		var css = Array.prototype.map.call(document.querySelectorAll('link[rel="stylesheet"]'), function (l) { return '<link rel="stylesheet" href="' + l.href + '">'; }).join('');
		w.document.open();
		w.document.write('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' + esc(sectionTitle(sec)) + ' · DAILY</title>' + css
			+ '<style>@page{size:A4 landscape;margin:10mm}html,body{background:#fff!important}body{padding:0;margin:0}.ded-card,.ded-kpi-card{break-inside:avoid}.srep-scroll{overflow:visible}.ded-row-2,.ded-row-3{break-inside:avoid}</style></head><body class="'
			+ esc(document.body.className) + '">' + clone.outerHTML + '<script>window.onload=function(){setTimeout(function(){window.focus();window.print();},400);};<\/script></body></html>');
		w.document.close();
	}

	/* ------------------------------------------------------------------ arranque diferido por pestaña */
	var BUILDERS = { dashboard: buildDashboard, departments: buildDepartments, products: buildProducts, branches: buildBranches, history: buildHistory, profitability: buildProfitability };
	function ensure(key) {
		if (built[key] || !D || !BUILDERS[key]) return;
		built[key] = true;
		try { BUILDERS[key](); } catch (e) { if (window.console) console.warn('Informe ' + key + ':', e); }
	}
	function buildVisible() {
		Object.keys(BUILDERS).forEach(function (k) {
			var el = document.getElementById('srep-' + k);
			if (el && el.offsetParent !== null) ensure(k);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var raw = document.getElementById('srepData');
		if (!raw) return;
		try { D = JSON.parse(raw.textContent); } catch (e) { return; }
		document.querySelectorAll('.srep').forEach(initTables);
		document.querySelectorAll('[data-srep-export]').forEach(function (b) {
			b.addEventListener('click', function () {
				var sec = document.getElementById('srep-' + b.getAttribute('data-section'));
				var k = b.getAttribute('data-srep-export');
				if (k === 'excel') exportExcel(sec); else if (k === 'csv') exportCsv(sec); else exportPdf(sec);
			});
		});
		document.querySelectorAll('[data-srep-compare]').forEach(function (b) {
			b.addEventListener('click', function () {
				var url = new URL(window.location.href);
				url.searchParams.set('tab', 'departamentos');
				url.searchParams.set('compare_with', b.getAttribute('data-srep-compare'));
				window.location.href = url.toString();
			});
		});
		buildVisible();
		var mo = new MutationObserver(function () { setTimeout(buildVisible, 40); });
		document.querySelectorAll('#salesMainTabContent > .tab-pane').forEach(function (p) { mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
		var lastDark = isDark();
		var refreshChartTheme = function () {
			var g = gridCfg(), t = tickCfg(), db = donutBorder(), leg = isDark() ? '#cbd5e1' : '#475569';
			GRID = g; TICK = t;
			Object.keys(charts).forEach(function (id) {
				var ch = charts[id];
				if (!ch || !ch.canvas || !document.body.contains(ch.canvas)) return;
				try {
					if (ch.config && ch.config.type === 'doughnut' && ch.data && ch.data.datasets && ch.data.datasets[0]) {
						ch.data.datasets[0].borderColor = db;
					}
					if (ch.options && ch.options.legend && ch.options.legend.labels) {
						ch.options.legend.labels.fontColor = leg;
					}
					if (ch.options && ch.options.scales) {
						['xAxes', 'yAxes'].forEach(function (ax) {
							(ch.options.scales[ax] || []).forEach(function (s) {
								if (s.gridLines && s.gridLines.display !== false) {
									s.gridLines.color = g.color;
									s.gridLines.zeroLineColor = g.zeroLineColor;
								}
								if (s.ticks) s.ticks.fontColor = t.fontColor;
								if (s.scaleLabel) s.scaleLabel.fontColor = t.fontColor;
							});
						});
					}
					ch.update(0);
				} catch (e) {}
			});
		};
		var themeMo = new MutationObserver(function () {
			var nextDark = isDark();
			if (nextDark === lastDark) return;
			lastDark = nextDark;
			refreshChartTheme();
		});
		themeMo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
		window.addEventListener('resize', function () { var t = document.getElementById('srepProdTreemap'); if (t && built.products && t.offsetParent) treemap(t, D.products.rows, D.dashboard.net); });
		if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
	});

	window.DailySalesReports = { ensure: ensure, charts: charts, sheets: function (k) { return sheetsOf(document.getElementById('srep-' + k)); } };
})();
