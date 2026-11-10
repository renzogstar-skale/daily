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

	/* ------------------------------------------------------------------ Chart.js (mismo estilo que el Tablero de Ventas) */
	var TOOLTIP = { mode: 'index', intersect: false, backgroundColor: 'rgba(15, 23, 42, 0.92)', titleFontColor: '#ffffff', titleFontSize: 12, bodyFontSize: 11, cornerRadius: 8, xPadding: 10, yPadding: 10 };
	var GRID = { color: 'rgba(226, 232, 240, 0.8)', borderDash: [3, 3], drawBorder: false, zeroLineColor: 'rgba(226, 232, 240, 0.8)' };
	var TICK = { fontColor: '#64748b', fontSize: 10.5 };

	function tip(fmt, extra) {
		var t = Object.assign({}, TOOLTIP, extra || {});
		t.callbacks = Object.assign({ label: function (it, data) { var ds = data.datasets[it.datasetIndex]; var v = ds.data[it.index]; return (ds.label ? ds.label + ': ' : '') + (fmt || money)(v && typeof v === 'object' ? v.y : v); } }, (extra && extra.callbacks) || {});
		return t;
	}
	function xCat(opts) { return [Object.assign({ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({ autoSkip: true, maxRotation: 0, autoSkipPadding: 10 }, TICK) }, opts || {})]; }
	function yMoney(opts) { return [Object.assign({ gridLines: GRID, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: short }, TICK) }, opts || {})]; }
	function base(type, data, options) {
		return { type: type, data: data, options: Object.assign({ responsive: true, maintainAspectRatio: false, animation: { duration: 400 }, legend: { display: false } }, options) };
	}
	function make(id, cfg) {
		var c = document.getElementById(id);
		if (!c || typeof Chart === 'undefined') return null;
		if (charts[id]) charts[id].destroy();
		charts[id] = new Chart(c, cfg);
		return charts[id];
	}
	function legendOn() { return { display: true, position: 'bottom', labels: { boxWidth: 10, fontSize: 10.5, fontColor: '#475569', padding: 10 } }; }
	function hbar(id, labels, values, colors, fmt, full) {
		return make(id, base('horizontalBar', { labels: labels, datasets: [{ data: values, backgroundColor: colors, hoverBackgroundColor: colors, maxBarThickness: 16 }] }, {
			tooltips: tip(fmt, { mode: 'nearest', intersect: true, callbacks: { title: function (it) { return full ? full[it[0].index] : it[0].label; } } }),
			scales: { xAxes: [{ gridLines: GRID, ticks: Object.assign({ beginAtZero: true, maxTicksLimit: 5, callback: fmt === pct ? function (v) { return v + ' %'; } : short }, TICK) }], yAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: Object.assign({}, TICK, { fontSize: 10 }) }] }
		}));
	}

	/* ------------------------------------------------------------------ 1. Tablero de ventas */
	function buildDashboard() {
		var d = D.dashboard;
		var trend = function (n) {
			var days = d.trend90.days.slice(-n), net = d.trend90.net.slice(-n);
			make('srepDashTrend', base('bar', { labels: days.map(dayLabel), datasets: [{ label: 'Ventas netas', data: net, backgroundColor: GREEN, hoverBackgroundColor: GREEN_D, maxBarThickness: 13 }] },
				{ tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
		};
		trend(30);
		document.querySelectorAll('[data-srep-window]').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('[data-srep-window]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
				trend(parseInt(b.getAttribute('data-srep-window'), 10));
			});
		});

		if (d.goal) {
			var done = Math.min(d.net, d.goal);
			make('srepDashGoal', base('doughnut', { labels: ['Ventas netas', 'Pendiente'], datasets: [{ data: [done, Math.max(d.goal - d.net, 0)], backgroundColor: [d.goal_pct >= 100 ? GREEN : (d.goal_pct >= 85 ? AMBER : RED), '#e2e8f0'], borderWidth: 0 }] },
				{ cutoutPercentage: 78, tooltips: tip(money, { mode: 'nearest' }) }));
		}

		// Curva por hora: solo el rango de horas con ventas
		var first = d.hourly.net.findIndex(function (v) { return v > 0; });
		var last = 23 - d.hourly.net.slice().reverse().findIndex(function (v) { return v > 0; });
		if (first < 0) { first = 8; last = 20; }
		var ctx = document.getElementById('srepDashHourly');
		if (ctx) {
			var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 190);
			g.addColorStop(0, 'rgba(16, 185, 129, 0.32)');
			g.addColorStop(1, 'rgba(16, 185, 129, 0)');
			make('srepDashHourly', base('line', { labels: d.hourly.labels.slice(first, last + 1), datasets: [{ label: 'Ventas netas', data: d.hourly.net.slice(first, last + 1), borderColor: GREEN, backgroundColor: g, borderWidth: 2.2, pointRadius: 2.5, pointBackgroundColor: GREEN, pointBorderColor: '#fff', tension: 0.35, fill: true }] },
				{ tooltips: tip(money, { callbacks: { afterLabel: function (it) { return 'Transacciones: ' + d.hourly.tickets[first + it.index].toLocaleString('en-US'); } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));
		}

		var vp = d.vs_prev;
		make('srepDashVsPrev', base('line', { labels: vp.days.map(dayLabel), datasets: [
			{ label: 'Actual', data: vp.net, borderColor: GREEN, backgroundColor: 'transparent', borderWidth: 2.2, pointRadius: 0, tension: 0.3 },
			{ label: 'Anterior', data: vp.prev_net, borderColor: GRAY, backgroundColor: 'transparent', borderWidth: 1.8, borderDash: [4, 3], pointRadius: 0, tension: 0.3 }
		] }, { tooltips: tip(money, { callbacks: { title: function (it) { var i = it[0].index; return dayLabel(vp.days[i]) + (vp.prev_days[i] ? ' vs. ' + dayLabel(vp.prev_days[i]) : ''); } } }), scales: { xAxes: xCat(), yAxes: yMoney() } }));

		var cu = d.cumulative, sets = [{ label: 'Acumulado', data: cu.net, borderColor: GREEN, backgroundColor: 'rgba(16, 185, 129, 0.10)', borderWidth: 2.2, pointRadius: 0, fill: true, tension: 0.2 }];
		if (cu.goal) sets.push({ label: 'Meta acumulada', data: cu.goal, borderColor: AMBER, backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 4], pointRadius: 0, fill: false });
		make('srepDashCum', base('line', { labels: cu.days.map(dayLabel), datasets: sets }, { tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
	}

	/* ------------------------------------------------------------------ 2. Ventas por departamento */
	function buildDepartments() {
		var dp = D.departments, rows = dp.rows;
		if (!rows.length) return;
		var labels = rows.map(function (r) { return r.name; });
		var bars = hbar('srepDeptBars', labels, rows.map(function (r) { return r.net; }), rows.map(function (r) { return r.color; }));
		var donut = make('srepDeptDonut', base('doughnut', { labels: labels, datasets: [{ data: rows.map(function (r) { return r.net; }), backgroundColor: rows.map(function (r) { return r.color; }), borderWidth: 2, borderColor: '#fff' }] },
			{ cutoutPercentage: 72, tooltips: tip(function (v) { return money(v) + ' · ' + pct(D.dashboard.net > 0 ? v / D.dashboard.net * 100 : null); }, { mode: 'nearest' }) }));
		make('srepDeptVsPrev', base('bar', { labels: labels.map(function (l) { return cut(l, 14); }), datasets: [
			{ label: 'Actual', data: rows.map(function (r) { return r.net; }), backgroundColor: GREEN, maxBarThickness: 14 },
			{ label: 'Anterior', data: rows.map(function (r) { return r.prev_net; }), backgroundColor: GRAY, maxBarThickness: 14 }
		] }, { tooltips: tip(money, { callbacks: { title: function (it) { return labels[it[0].index]; } } }), scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45, minRotation: 0 }, TICK) }), yAxes: yMoney() } }));

		var evo = function (key) {
			var list = key ? rows.filter(function (r) { return r.key === key; }) : rows.slice(0, 5);
			make('srepDeptEvo', base('line', { labels: dp.days.map(dayLabel), datasets: list.map(function (r) {
				return { label: r.name, data: dp.series[r.key] || [], borderColor: r.color, backgroundColor: key ? alpha(r.color, 0.12) : 'transparent', fill: !!key, borderWidth: 2, pointRadius: 0, tension: 0.3 };
			}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
			var note = document.getElementById('srepDeptEvoNote');
			if (note) note.textContent = key ? list[0].name : 'Cinco principales';
		};
		evo('');

		var detail = document.getElementById('srepDeptDetail');
		var select = document.getElementById('srepDeptSelect');
		var show = function (key) {
			var r = rows.filter(function (x) { return x.key === key; })[0];
			var tot = { net: 0, prev: 0, tickets: D.dashboard.tickets, units: 0 };
			rows.forEach(function (x) { tot.net += x.net; tot.prev += x.prev_net; tot.units += x.units; });
			var items = r ? [
				['Ventas netas', money(r.net, 2)], ['Participación', pct(r.share_pct)], ['Período anterior', money(r.prev_net, 2)], ['Variación', signed(r.delta_pct)],
				['Transacciones', r.tickets.toLocaleString('en-US')], ['Ticket promedio del depto.', r.tickets ? money(r.net / r.tickets, 2) : NA], ['Unidades', Math.round(r.units).toLocaleString('en-US')], ['Meta', money(r.goal)], ['Cumplimiento', pct(r.goal_pct)]
			] : [
				['Ventas netas', money(tot.net, 2)], ['Departamentos con venta', String(rows.length)], ['Período anterior', money(tot.prev, 2)],
				['Variación', signed(tot.prev > 0 ? (tot.net - tot.prev) / tot.prev * 100 : null)], ['Transacciones', tot.tickets.toLocaleString('en-US')], ['Unidades', Math.round(tot.units).toLocaleString('en-US')]
			];
			detail.innerHTML = items.map(function (i) { return '<div class="ded-comp-row"><span class="ded-comp-label">' + i[0] + '</span><span class="ded-comp-val">' + (/^</.test(i[1]) ? i[1] : esc(i[1])) + '</span></div>'; }).join('');
			var idx = rows.findIndex(function (x) { return x.key === key; });
			if (bars) { bars.data.datasets[0].backgroundColor = rows.map(function (x, i) { return idx < 0 || i === idx ? x.color : alpha(x.color, 0.25); }); bars.update(); }
			if (donut) { donut.data.datasets[0].backgroundColor = rows.map(function (x, i) { return idx < 0 || i === idx ? x.color : alpha(x.color, 0.25); }); donut.update(); }
			document.querySelectorAll('#srep-departments [data-srep-dept-row]').forEach(function (tr) { tr.classList.toggle('is-selected', !!key && tr.getAttribute('data-srep-dept-row') === key); });
			evo(key);
		};
		if (select) select.addEventListener('change', function () { show(select.value); });
		var pick = function (key) { if (select) { select.value = select.value === key ? '' : key; show(select.value); } };
		document.querySelectorAll('#srep-departments [data-srep-dept]').forEach(function (el) { el.addEventListener('click', function () { pick(el.getAttribute('data-srep-dept')); }); });
		[bars, donut].forEach(function (ch) {
			if (ch) ch.canvas.addEventListener('click', function (e) { var a = ch.getElementAtEvent(e)[0]; if (a) pick(rows[a._index].key); });
		});
		show('');
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
		var b = D.branches, rows = b.rows;
		if (!rows.length) return;
		var labels = rows.map(function (r) { return r.name; });
		var bars = hbar('srepBranchBars', labels.map(function (l, i) { return (i + 1) + '. ' + l; }), rows.map(function (r) { return r.net; }), GREEN);
		make('srepBranchVsPrev', base('bar', { labels: labels.map(function (l) { return cut(l, 12); }), datasets: [
			{ label: 'Actual', data: rows.map(function (r) { return r.net; }), backgroundColor: GREEN, maxBarThickness: 14 },
			{ label: 'Anterior', data: rows.map(function (r) { return r.prev_net; }), backgroundColor: GRAY, maxBarThickness: 14 }
		] }, { tooltips: tip(money, { callbacks: { title: function (it) { return labels[it[0].index]; } } }), scales: { xAxes: xCat({ ticks: Object.assign({ autoSkip: false, maxRotation: 45 }, TICK, { fontSize: 9.5 }) }), yAxes: yMoney() } }));

		var evo = function (id) {
			var list = id ? rows.filter(function (r) { return String(r.id) === String(id); }) : rows.filter(function (r) { return b.top5.indexOf(r.id) >= 0; });
			make('srepBranchEvo', base('line', { labels: b.days.map(dayLabel), datasets: list.map(function (r, i) {
				return { label: r.name, data: b.series[r.id] || [], borderColor: id ? GREEN : PALETTE[i], backgroundColor: id ? 'rgba(16, 185, 129, 0.12)' : 'transparent', fill: !!id, borderWidth: 2, pointRadius: 0, tension: 0.3 };
			}) }, { legend: legendOn(), tooltips: tip(), scales: { xAxes: xCat(), yAxes: yMoney() } }));
			var note = document.getElementById('srepBranchEvoNote');
			if (note) note.textContent = id ? list[0].name : 'Cinco principales';
		};

		var detail = document.getElementById('srepBranchDetail');
		var select = document.getElementById('srepBranchSelect');
		var show = function (id) {
			var r = rows.filter(function (x) { return String(x.id) === String(id); })[0];
			var d = D.dashboard;
			var items = r ? [
				['Posición', r.rank + ' de ' + rows.length], ['Ventas netas', money(r.net, 2)], ['Participación', pct(r.share_pct)], ['Meta', money(r.goal)], ['Cumplimiento', pct(r.goal_pct)],
				['Transacciones', r.tickets.toLocaleString('en-US')], ['Ticket promedio', money(r.avg_ticket, 2)], ['Variación', signed(r.delta_pct)]
			] : [
				['Sucursales con venta', String(rows.length)], ['Ventas netas', money(d.net, 2)], ['Meta', money(d.goal)], ['Cumplimiento', pct(d.goal_pct)],
				['Transacciones', d.tickets.toLocaleString('en-US')], ['Ticket promedio', money(d.avg_ticket, 2)], ['Variación', signed(d.growth_pct)]
			];
			detail.innerHTML = items.map(function (i) { return '<div class="ded-comp-row"><span class="ded-comp-label">' + i[0] + '</span><span class="ded-comp-val">' + (/^</.test(i[1]) ? i[1] : esc(i[1])) + '</span></div>'; }).join('');
			var idx = rows.findIndex(function (x) { return String(x.id) === String(id); });
			if (bars) { bars.data.datasets[0].backgroundColor = rows.map(function (x, i) { return idx < 0 || i === idx ? GREEN : alpha(GREEN, 0.25); }); bars.update(); }
			document.querySelectorAll('#srep-branches [data-srep-branch-row]').forEach(function (tr) { tr.classList.toggle('is-selected', !!id && tr.getAttribute('data-srep-branch-row') === String(id)); });
			document.querySelectorAll('#srepBranchMap [data-srep-branch]').forEach(function (n) { n.classList.toggle('is-selected', !!id && n.getAttribute('data-srep-branch') === String(id)); });
			evo(id);
		};
		if (select) select.addEventListener('change', function () { show(select.value); });
		var pick = function (id) { if (select) { select.value = select.value === String(id) ? '' : String(id); show(select.value); } };
		document.querySelectorAll('#srepBranchMap [data-srep-branch]').forEach(function (n) { n.addEventListener('click', function () { pick(n.getAttribute('data-srep-branch')); }); });
		if (bars) bars.canvas.addEventListener('click', function (e) { var a = bars.getElementAtEvent(e)[0]; if (a) pick(rows[a._index].id); });
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
					var trs = Array.prototype.slice.call(body.rows);
					trs.sort(function (a, b) {
						var x = a.cells[idx], y = b.cells[idx];
						var vx = num ? parseFloat(x.getAttribute('data-v') || x.textContent.replace(/[^0-9.\-]/g, '')) : x.textContent.trim().toLowerCase();
						var vy = num ? parseFloat(y.getAttribute('data-v') || y.textContent.replace(/[^0-9.\-]/g, '')) : y.textContent.trim().toLowerCase();
						return (vx > vy ? 1 : vx < vy ? -1 : 0) * (asc ? 1 : -1);
					});
					trs.forEach(function (tr) { body.appendChild(tr); });
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
		sec.querySelectorAll('.srep-kpis .ded-kpi-card').forEach(function (k) {
			var g = function (s) { var e = k.querySelector(s); return e ? e.textContent.replace(/\s+/g, ' ').trim() : ''; };
			head.push([g('.ded-kpi-label'), g('.ded-kpi-val') || g('.srep-acc'), g('.ded-kpi-sub')]);
		});
		sheets.push(['Indicadores', head]);
		sec.querySelectorAll('table').forEach(function (t, i) {
			var rows = [Array.prototype.map.call(t.tHead.rows[0].cells, function (c) { return (c.getAttribute('data-export-value') || c.textContent).replace(/[▲▼]/g, '').trim(); })];
			Array.prototype.forEach.call(t.tBodies[0].rows, function (tr) {
				rows.push(Array.prototype.map.call(tr.cells, function (c) {
					var v = c.getAttribute('data-export-value');
					if (v === null && c.hasAttribute('data-v') && /\$|%/.test(c.textContent) && parseFloat(c.getAttribute('data-v')) >= 0) v = c.getAttribute('data-v');
					v = v !== null ? v : c.textContent.replace(/\s+/g, ' ').trim();
					return /^-?\d+(\.\d+)?$/.test(v) ? Number(v) : v;
				}));
			});
			sheets.push([t.getAttribute('data-export-name') || ('Tabla ' + (i + 1)), rows]);
		});
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
		clone.querySelectorAll('.srep-export, [data-srep-more], .srep-search, select').forEach(function (e) { e.remove(); });
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
				if (b.getAttribute('data-srep-export') === 'excel') exportExcel(sec); else exportPdf(sec);
			});
		});
		buildVisible();
		var mo = new MutationObserver(function () { setTimeout(buildVisible, 40); });
		document.querySelectorAll('#salesMainTabContent > .tab-pane').forEach(function (p) { mo.observe(p, { attributes: true, attributeFilter: ['class'] }); });
		window.addEventListener('resize', function () { var t = document.getElementById('srepProdTreemap'); if (t && built.products && t.offsetParent) treemap(t, D.products.rows, D.dashboard.net); });
		if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
	});

	window.DailySalesReports = { ensure: ensure, charts: charts, sheets: function (k) { return sheetsOf(document.getElementById('srep-' + k)); } };
})();
