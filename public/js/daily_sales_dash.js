// Ventas · cinco bloques: gráficos (Chart.js 2.9) con los datos que el servidor ya filtró
(function () {
	var el = document.getElementById('sdashData');
	if (!el || typeof Chart === 'undefined') return;
	var D = JSON.parse(el.textContent);
	var money = function (v) { return '$' + Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); };
	var dm = function (d) { var p = d.split('-'); return p[2] + '/' + p[1]; };
	var hours = []; for (var h = 0; h < 24; h++) hours.push((h < 10 ? '0' : '') + h + ':00');
	var C1 = '#00a63e', C2 = '#94a3b8', C3 = '#2563eb';
	var base = function (yFmt) {
		return {
			responsive: true, maintainAspectRatio: false, legend: { position: 'bottom', labels: { boxWidth: 12 } },
			tooltips: { mode: 'index', intersect: false, callbacks: { label: function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + yFmt(t.yLabel); } } },
			scales: { yAxes: [{ ticks: { beginAtZero: true, callback: yFmt } }], xAxes: [{ gridLines: { display: false } }] }
		};
	};

	// 1. Evolución: diaria / por hora / semanal / acumulada
	var evo = D.evo, chart = null;
	var views = {
		daily: function () { return { type: 'line', labels: evo.series.map(function (s) { return dm(s.d); }), a: evo.series.map(function (s) { return s.v; }), b: evo.series.map(function (s) { return s.prev; }), la: 'Período actual', lb: 'Período anterior', note: 'Ventas netas por día · actual vs. período equivalente anterior (mismo número de días)' }; },
		hourly: function () { return { type: 'bar', labels: hours, a: evo.hourly, b: evo.hourly_prev, la: 'Período actual', lb: 'Período anterior', note: 'Ventas netas por hora del día · suma de todos los días del período' }; },
		weekly: function () { return { type: 'bar', labels: evo.weeks.map(function (w) { return 'Sem. ' + dm(w.week) + (w.days < 7 ? ' (' + w.days + ' d)' : ''); }), a: evo.weeks.map(function (w) { return w.v; }), b: null, la: 'Ventas netas de la semana', note: 'Semanas de lunes a domingo; las semanas con menos de 7 días del período se indican' }; },
		cumulative: function () { return { type: 'line', labels: evo.series.map(function (s) { return dm(s.d); }), a: evo.series.map(function (s) { return s.acc; }), b: evo.series.map(function (s) { return s.acc_prev; }), la: 'Acumulado actual', lb: 'Acumulado período anterior', note: 'Ventas netas acumuladas día a día dentro del período' }; }
	};
	function drawEvo(k) {
		var v = views[k](), c = document.getElementById('sdashEvo');
		if (!c) return;
		if (chart) chart.destroy();
		var ds = [{ label: v.la, data: v.a, borderColor: C1, backgroundColor: v.type === 'bar' ? C1 : 'rgba(0,166,62,.08)', borderWidth: 2, pointRadius: 2, fill: v.type === 'line', lineTension: 0.25 }];
		if (v.b) ds.push({ label: v.lb, data: v.b, borderColor: C2, backgroundColor: v.type === 'bar' ? C2 : 'transparent', borderWidth: 2, borderDash: v.type === 'line' ? [5, 4] : undefined, pointRadius: 0, fill: false, lineTension: 0.25 });
		chart = new Chart(c, { type: v.type, data: { labels: v.labels, datasets: ds }, options: base(money) });
		var n = document.getElementById('evoNote'); if (n) n.textContent = v.note;
	}
	document.querySelectorAll('[data-evo]').forEach(function (b) {
		b.addEventListener('click', function () {
			document.querySelectorAll('[data-evo]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
			drawEvo(b.getAttribute('data-evo'));
		});
	});
	drawEvo('daily');

	// 3. Productividad semanal
	var pc = document.getElementById('sdashProd');
	if (pc && D.prod.length) {
		new Chart(pc, { type: 'bar', data: { labels: D.prod.map(function (w) { return 'Sem. ' + dm(w.week); }), datasets: [{ label: 'Ventas por hora trabajada', data: D.prod.map(function (w) { return w.per_hour; }), backgroundColor: C3 }] }, options: base(function (v) { return '$' + Number(v || 0).toFixed(0) + '/h'; }) });
	}

	// 5. Tickets y ventas por hora (referencia; no es tráfico)
	var tc = document.getElementById('sdashTraffic');
	if (tc) {
		var o = base(function (v) { return Number(v || 0).toLocaleString('en-US'); });
		o.scales.yAxes = [{ id: 't', position: 'left', ticks: { beginAtZero: true }, scaleLabel: { display: true, labelString: 'Tickets' } }, { id: 'v', position: 'right', gridLines: { display: false }, ticks: { beginAtZero: true, callback: money }, scaleLabel: { display: true, labelString: 'Ventas netas' } }];
		o.tooltips.callbacks.label = function (t, d) { return d.datasets[t.datasetIndex].label + ': ' + (t.datasetIndex === 1 ? money(t.yLabel) : t.yLabel); };
		new Chart(tc, { type: 'bar', data: { labels: hours, datasets: [
			{ label: 'Tickets por hora', yAxisID: 't', data: D.traffic.tickets_by_hour, backgroundColor: '#cbd5e1' },
			{ label: 'Ventas netas por hora', yAxisID: 'v', type: 'line', data: D.traffic.net_by_hour, borderColor: C1, backgroundColor: 'transparent', pointRadius: 2, fill: false }
		] }, options: o });
	}
})();
