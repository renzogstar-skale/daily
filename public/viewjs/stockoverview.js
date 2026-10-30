/**
 * DAILY — Stock & Movimientos (/stockoverview)
 * Controlador interactivo de nivel empresarial
 */

(function () {
	'use strict';

	var dsmState = window.DAILY_STOCK_EXEC_INITIAL || {};
	var evoChart = null;
	var donutChart = null;
	var showUnitsDataset = false;
	var currentPeriod = 'month';
	var currentEvoView = 'value';
	var currentDonutMetric = 'value';
	var currentSelectedFefoItem = null;
	var branchSortKey = 'inventory_value';
	var branchSortAsc = false;
	var showAll8Branches = false;

	function fmtMoney(v, decimals) {
		var d = typeof decimals === 'number' ? decimals : 0;
		var n = Number(v || 0);
		return '$' + n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
	}

	function fmtNum(v, decimals) {
		var d = typeof decimals === 'number' ? decimals : 0;
		var n = Number(v || 0);
		return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
	}

	// =========================================================================
	// 1. INICIALIZACIÓN DE GRÁFICOS (CHART.JS v2 / v3 COMPATIBLE)
	// =========================================================================
	function initEvolutionChart() {
		var canvas = document.getElementById('dsmEvolutionChart');
		if (!canvas || typeof Chart === 'undefined') return;
		var ctx = canvas.getContext('2d');

		// Sin serie registrada no se dibuja una de referencia
		var evo = (dsmState.evolution && dsmState.evolution.series) ? dsmState.evolution.series : { labels: [], value: [], units: [], rotation: [], coverage: [] };

		if (evoChart) {
			evoChart.destroy();
		}

		var barData = evo.value;
		var lineData = evo.rotation;
		var barLabel = 'Valor del inventario ($)';
		var lineLabel = 'Rotación';
		var labels = evo.labels;

		if (currentEvoView === 'units') {
			barData = evo.units;
			barLabel = 'Unidades disponibles';
		} else if (currentEvoView === 'coverage') {
			lineData = evo.coverage;
			lineLabel = 'Cobertura (días)';
		} else if (currentEvoView === 'branch_compare' && dsmState.evolution && dsmState.evolution.branch_compare) {
			var bc = dsmState.evolution.branch_compare;
			labels = bc.labels;
			barData = bc.value;
			lineData = bc.rotation;
			barLabel = 'Valor por Sucursal ($)';
		}

		$('#dsm-leg-bars-text').text(barLabel);
		$('#dsm-leg-line-text').text(lineLabel);

		var datasets = [
			{
				type: 'line',
				label: lineLabel,
				data: lineData,
				yAxisID: 'y-axis-right',
				borderColor: '#eab308',
				backgroundColor: 'transparent',
				borderWidth: 2.5,
				pointBackgroundColor: '#ffffff',
				pointBorderColor: '#eab308',
				pointBorderWidth: 2,
				pointRadius: 3.5,
				pointHoverRadius: 5,
				lineTension: 0.35,
				tension: 0.35,
				fill: false,
				order: 1
			},
			{
				type: 'bar',
				label: barLabel,
				data: barData,
				yAxisID: 'y-axis-left',
				backgroundColor: '#22c55e',
				hoverBackgroundColor: '#16a34a',
				borderRadius: 4,
				barPercentage: 0.58,
				categoryPercentage: 0.72,
				order: 2
			}
		];

		if (currentEvoView === 'year_compare' && evo.prev_year_value) {
			datasets.push({
				type: 'bar',
				label: 'Año anterior ($)',
				data: evo.prev_year_value,
				yAxisID: 'y-axis-left',
				backgroundColor: '#94a3b8',
				borderRadius: 4,
				barPercentage: 0.58,
				categoryPercentage: 0.72,
				order: 3
			});
		} else if (showUnitsDataset) {
			datasets.push({
				type: 'bar',
				label: 'Unidades (x30 escala)',
				data: (evo.units || []).map(function (u) { return u * 30; }),
				yAxisID: 'y-axis-left',
				backgroundColor: '#94a3b8',
				borderRadius: 4,
				barPercentage: 0.58,
				categoryPercentage: 0.72,
				order: 3
			});
		}

		var isChartV3 = Chart.version && parseInt(Chart.version.split('.')[0], 10) >= 3;
		var options = isChartV3 ? {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: {
				'y-axis-left': {
					type: 'linear',
					position: 'left',
					beginAtZero: true,
					ticks: {
						callback: function (v) {
							if (currentEvoView === 'units') return fmtNum(v);
							return v === 0 ? '$0' : '$' + (v / 1000000).toFixed(1) + 'M';
						}
					}
				},
				'y-axis-right': {
					type: 'linear',
					position: 'right',
					beginAtZero: true,
					grid: { drawOnChartArea: false },
					ticks: {
						callback: function (v) { return Number(v).toFixed(1); }
					}
				}
			}
		} : {
			responsive: true,
			maintainAspectRatio: false,
			legend: { display: false },
			tooltips: {
				mode: 'index',
				intersect: false
			},
			scales: {
				xAxes: [{
					gridLines: { display: false },
					ticks: { fontColor: '#64748b', fontSize: 11 }
				}],
				yAxes: [
					{
						id: 'y-axis-left',
						type: 'linear',
						position: 'left',
						ticks: {
							beginAtZero: true,
							fontColor: '#64748b',
							fontSize: 11,
							callback: function (v) {
								if (currentEvoView === 'units') return fmtNum(v);
								if (v === 0) return '$0';
								if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
								return '$' + fmtNum(v);
							}
						},
						gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0' }
					},
					{
						id: 'y-axis-right',
						type: 'linear',
						position: 'right',
						ticks: {
							beginAtZero: true,
							max: currentEvoView === 'coverage' ? 25 : 5.0,
							stepSize: currentEvoView === 'coverage' ? 5 : 1.0,
							fontColor: '#64748b',
							fontSize: 11,
							callback: function (v) { return Number(v).toFixed(1); }
						},
						gridLines: { drawOnChartArea: false }
					}
				]
			}
		};

		evoChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: datasets
			},
			options: options
		});
	}

	function initDonutChart() {
		var canvas = document.getElementById('dsmDonutChart');
		if (!canvas || typeof Chart === 'undefined') return;
		var ctx = canvas.getContext('2d');

		var depts = (dsmState.donut && dsmState.donut.departments) ? dsmState.donut.departments : [];
		var labels = depts.map(function (d) { return d.name; });
		var colors = depts.map(function (d) { return d.color; });
		var values = depts.map(function (d) {
			if (currentDonutMetric === 'units') return d.units;
			if (currentDonutMetric === 'pct') return d.share_pct;
			return d.value;
		});

		if (donutChart) {
			donutChart.destroy();
		}

		donutChart = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: colors,
					borderColor: '#ffffff',
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutoutPercentage: 68,
				cutout: '68%',
				legend: { display: false },
				plugins: { legend: { display: false } },
				onClick: function (evt, elements) {
					if (elements && elements.length > 0) {
						var idx = elements[0]._index !== undefined ? elements[0]._index : elements[0].index;
						if (depts[idx]) {
							dsmSelectDepartment(depts[idx].id);
						}
					}
				}
			}
		});

		updateDonutListAndCenter();
	}

	function updateDonutListAndCenter() {
		if (!dsmState.donut) return;
		var depts = dsmState.donut.departments || [];
		var centerVal = dsmState.donut.center_value || 'Sin información';
		var centerSub = dsmState.donut.center_sub || '100%';

		if (currentDonutMetric === 'units') {
			centerVal = fmtNum(dsmState.donut.center_numeric_units || 0);
			centerSub = 'Unidades';
		} else if (currentDonutMetric === 'pct') {
			centerVal = '100.0%';
			centerSub = fmtMoney(dsmState.donut.center_numeric_value || 0);
		}

		$('#dsm-donut-center-val').text(centerVal);
		$('#dsm-donut-center-sub').text(centerSub);

		var html = '';
		depts.forEach(function (d) {
			var rightMain = fmtMoney(d.reference_display_value !== undefined ? d.reference_display_value : d.value);
			if (currentDonutMetric === 'units') {
				rightMain = fmtNum(d.units) + ' uds';
			} else if (currentDonutMetric === 'pct') {
				rightMain = Number(d.share_pct).toFixed(1) + '%';
			}
			var isAct = (dsmState.filters && dsmState.filters.department === d.id) ? 'active' : '';
			html += '<div class="dsm-dept-row ' + isAct + '" data-dept-id="' + d.id + '" title="Clic para filtrar y ver categorías, subcategorías, SKU, lote y ubicación de ' + d.name + '">' +
				'<div class="dsm-dept-left">' +
				'<span class="dsm-dept-dot" style="background:' + d.color + ';"></span>' +
				'<span>' + d.name + '</span>' +
				'</div>' +
				'<div class="dsm-dept-right">' +
				'<span class="dsm-dept-pct">' + Number(d.share_pct).toFixed(1) + '%</span>' +
				'<span class="dsm-dept-val">' + rightMain + '</span>' +
				'</div>' +
				'</div>';
		});
		$('#dsm-dept-list').html(html);
	}

	// =========================================================================
	// 2. PROFUNDIZACIÓN JERÁRQUICA DE DEPARTAMENTO (SECCIÓN 3)
	// =========================================================================
	window.dsmSelectDepartment = function (deptId) {
		var depts = (dsmState.donut && dsmState.donut.departments) ? dsmState.donut.departments : [];
		var found = null;
		for (var i = 0; i < depts.length; i++) {
			if (depts[i].id === deptId) {
				found = depts[i];
				break;
			}
		}

		if (found && found.categories) {
			$('#dsm-drill-title').html('<i class="fa-solid fa-sitemap text-success mr-1"></i>' + found.name + ' &rsaquo; Categoría &rsaquo; Subcategoría &rsaquo; SKU &rsaquo; Lote y Ubicación');
			var cHtml = '';
			found.categories.forEach(function (cat) {
				cHtml += '<div class="mb-2 p-2 rounded bg-light border">' +
					'<div class="d-flex justify-content-between font-weight-bold text-dark">' +
					'<span><i class="fa-solid fa-folder-open text-warning mr-1"></i>Categoría: ' + cat.name + '</span>' +
					'<span>' + fmtMoney(cat.value) + ' (' + fmtNum(cat.units) + ' uds)</span>' +
					'</div>';
				(cat.subcategories || []).forEach(function (sub) {
					cHtml += '<div class="mt-1 pl-3 text-muted" style="font-size:11px;">' +
						'&bull; <strong>' + sub.name + '</strong> &rarr; SKU <code>' + sub.sku + '</code> (' + sub.product + ') &middot; ' +
						'Lote: <span class="badge badge-light border">' + sub.lot + '</span> &middot; ' +
						'Ubicación: <strong>' + sub.location + '</strong> (' + fmtNum(sub.units) + ' uds / ' + fmtMoney(sub.value) + ')' +
						'</div>';
				});
				cHtml += '</div>';
			});
			$('#dsm-drill-content').html(cHtml);
			$('#dsm-dept-drilldown').addClass('show');
		}

		$('#dsm-filter-dept').val(deptId);
		dsmApplyAllFilters();
	};

	window.dsmClearDeptDrilldown = function () {
		$('#dsm-dept-drilldown').removeClass('show');
		$('#dsm-filter-dept').val('todos');
		dsmApplyAllFilters();
	};

	$(document).on('click', '.dsm-dept-row', function () {
		var deptId = $(this).data('dept-id');
		if ($('#dsm-filter-dept').val() === deptId) {
			dsmClearDeptDrilldown();
		} else {
			dsmSelectDepartment(deptId);
		}
	});

	// =========================================================================
	// 3. MAPA DE CALOR ADAPTATIVO (SECCIÓN 4)
	// =========================================================================
	function updateHeatmapMetric(metricKey) {
		var titles = {
			units: 'Mapa de calor de existencias (unidades)',
			stockout_risk: 'Mapa de calor de riesgo de agotamiento (%)',
			movements_vol: 'Mapa de calor de volumen de movimientos',
			replenishment: 'Mapa de calor de actividad de reposición',
			hourly_demand: 'Mapa de calor de demanda por hora'
		};
		$('#dsm-hm-title').text(titles[metricKey] || titles.units);

		var legends = (dsmState.heatmap && dsmState.heatmap.legends && dsmState.heatmap.legends[metricKey])
			? dsmState.heatmap.legends[metricKey]
			: { min_label: 'Menor stock', max_label: 'Mayor stock' };

		$('#dsm-hm-min-label').text(legends.min_label);
		$('#dsm-hm-max-label').text(legends.max_label);

		$('.dsm-hm-cell').each(function () {
			var $cell = $(this);
			var period = $cell.attr('data-period');
			var rawMetrics = $cell.attr('data-metrics');
			if (!rawMetrics) return;
			try {
				var mObj = JSON.parse(rawMetrics);
				var m = mObj[metricKey] || mObj.units;
				$cell.attr('title', period + ' · ' + m.formatted + ' (' + m.status + ')');
			} catch (e) {}
		});
	}

	$('#dsm-hm-metric-select').on('change', function () {
		updateHeatmapMetric($(this).val());
	});

	$(document).on('click', '.dsm-hm-cell', function () {
		var title = $(this).attr('title');
		if (typeof toastr !== 'undefined' && title) {
			toastr.info(title, 'Detalle horario de inventario');
		}
	});

	// =========================================================================
	// 4. TOP 10 PRODUCTOS Y ORDENAMIENTO (SECCIÓN 5)
	// =========================================================================
	function renderTop10Table() {
		var list = (dsmState.top_products || []).slice();
		var sortBy = $('#dsm-top10-sort-select').val() || 'value';

		list.sort(function (a, b) {
			if (sortBy === 'units') return b.units - a.units;
			if (sortBy === 'rotation') return b.rotation - a.rotation;
			if (sortBy === 'risk') return b.risk_score - a.risk_score;
			return b.value - a.value;
		});

		var tbody = '';
		list.forEach(function (tp, idx) {
			var extraCol = tp.formatted_units || fmtNum(tp.units);
			if (sortBy === 'rotation') {
				extraCol = Number(tp.rotation).toFixed(1) + 'x (' + tp.coverage_days + 'd)';
			} else if (sortBy === 'risk') {
				extraCol = tp.status_label + ' (' + fmtNum(tp.units) + ')';
			}
			var imgUrl = Grocy.BaseUrl.replace(/\/$/, '') + tp.image;
			tbody += '<tr onclick="dsmOpenProduct360(' + tp.product_id + ')" title="SKU: ' + tp.sku + ' · UPC: ' + tp.upc + ' · Rotación: ' + tp.rotation + 'x · Cobertura: ' + tp.coverage_days + 'd · Estado: ' + tp.status_label + '">' +
				'<td class="font-weight-bold text-muted">' + (idx + 1) + '</td>' +
				'<td><div class="dsm-prod-cell"><img src="' + imgUrl + '" alt="' + tp.name + '" class="dsm-prod-thumb"><span class="dsm-prod-name">' + tp.name + '</span></div></td>' +
				'<td class="text-muted">' + tp.department + '</td>' +
				'<td style="text-align:right; font-weight:800; color:#0f172a;">' + fmtMoney(tp.value) + '</td>' +
				'<td style="text-align:right; font-weight:600;">' + extraCol + '</td>' +
				'</tr>';
		});
		if (!tbody) {
			tbody = '<tr><td colspan="5" class="text-center text-muted py-3">Sin productos para los filtros seleccionados</td></tr>';
		}
		$('#dsm-top10-tbody').html(tbody);
	}

	$('#dsm-top10-sort-select').on('change', function () {
		renderTop10Table();
	});

	// Ficha 360° de Producto
	window.dsmOpenProduct360 = function (productId) {
		$('#dsmProduct360Modal').modal('show');
		$('#dsmProduct360Body').html('<div class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Consultando ficha 360° y trazabilidad multisucursal...</div>');

		$('#dsm360LinkTrace').attr('href', Grocy.BaseUrl.replace(/\/$/, '') + '/traceability?product_id=' + productId);
		$('#dsm360LinkKardex').attr('href', Grocy.BaseUrl.replace(/\/$/, '') + '/kardex?product_id=' + productId);
		$('#dsm360LinkCount').attr('href', Grocy.BaseUrl.replace(/\/$/, '') + '/inventory?product=' + productId);

		$.getJSON(Grocy.BaseUrl.replace(/\/$/, '') + '/api/stockoverview/product/' + productId, function (res) {
			var p = res.product || {};
			var imgUrl = Grocy.BaseUrl.replace(/\/$/, '') + (p.image || '/img/control_center/leche_entera_galon.jpg');

			var html = '<div class="d-flex align-items-center justify-content-between flex-wrap p-3 mb-3 rounded border bg-light">' +
				'<div class="d-flex align-items-center" style="gap:14px;">' +
				'<img src="' + imgUrl + '" style="width:56px; height:56px; border-radius:10px; object-fit:cover; border:1px solid #cbd5e1;">' +
				'<div>' +
				'<h5 class="mb-1 font-weight-bold text-dark">' + p.name + ' <span class="badge badge-success ml-1">' + (p.status_label || 'Activo') + '</span></h5>' +
				'<div class="text-muted small">SKU: <strong>' + p.sku + '</strong> &middot; UPC: <strong>' + (p.upc || '7501055300014') + '</strong> &middot; Departamento: <strong>' + p.department + '</strong></div>' +
				'</div>' +
				'</div>' +
				'<div class="d-flex" style="gap:18px;">' +
				'<div class="text-right"><div class="small text-muted">Valor Total</div><div class="h5 mb-0 font-weight-bold text-success">' + fmtMoney(p.value) + '</div></div>' +
				'<div class="text-right"><div class="small text-muted">Unidades</div><div class="h5 mb-0 font-weight-bold text-dark">' + fmtNum(p.units) + '</div></div>' +
				'<div class="text-right"><div class="small text-muted">Rotación / Cobertura</div><div class="h5 mb-0 font-weight-bold text-primary">' + p.rotation + 'x / ' + p.coverage_days + 'd</div></div>' +
				'</div>' +
				'</div>';

			html += '<div class="row">' +
				'<div class="col-md-6 mb-3">' +
				'<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-store text-primary mr-1"></i>Existencias por Sucursal y Ubicación</h6>' +
				'<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;"><thead class="thead-light"><tr><th>Sucursal</th><th>Ubicación</th><th class="text-right">Unidades</th><th class="text-right">Valor</th><th>Estado</th></tr></thead><tbody>';
			(res.branches_stock || []).forEach(function (bs) {
				html += '<tr><td><strong>' + bs.branch + '</strong></td><td>' + bs.location + '</td><td class="text-right font-weight-bold">' + fmtNum(bs.units) + '</td><td class="text-right">' + bs.value + '</td><td><span class="badge badge-light border">' + bs.status + '</span></td></tr>';
			});
			html += '</tbody></table></div>';

			html += '<div class="col-md-6 mb-3">' +
				'<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-layer-group text-warning mr-1"></i>Lotes Activos y Orden FEFO</h6>' +
				'<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;"><thead class="thead-light"><tr><th>Lote</th><th class="text-right">Cant.</th><th>Vencimiento</th><th>Ubicación</th><th>Prioridad</th></tr></thead><tbody>';
			(res.active_lots || []).forEach(function (lot) {
				html += '<tr><td><code>' + lot.lot + '</code></td><td class="text-right font-weight-bold">' + fmtNum(lot.qty) + '</td><td>' + lot.expiry + ' (' + lot.days + 'd)</td><td>' + lot.location + '</td><td><span class="badge badge-info">' + lot.state + '</span></td></tr>';
			});
			html += '</tbody></table></div></div>';

			html += '<div class="row">' +
				'<div class="col-md-6">' +
				'<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-cash-register text-success mr-1"></i>Últimas Ventas y Salidas</h6>' +
				'<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;"><thead class="thead-light"><tr><th>Fecha</th><th>Documento</th><th>Sucursal</th><th class="text-right">Unidades</th><th class="text-right">Importe</th></tr></thead><tbody>';
			(res.recent_sales || []).forEach(function (s) {
				html += '<tr><td>' + s.date + '</td><td>' + s.doc + '</td><td>' + s.branch + '</td><td class="text-right text-danger font-weight-bold">' + s.units + '</td><td class="text-right">' + s.amount + '</td></tr>';
			});
			html += '</tbody></table></div>';

			html += '<div class="col-md-6">' +
				'<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-clipboard-check text-info mr-1"></i>Recepciones OC, Devoluciones y Conteos</h6>' +
				'<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;"><thead class="thead-light"><tr><th>Operación</th><th>Doc.</th><th>Cantidad</th><th>Ubicación</th><th>Estado</th></tr></thead><tbody>';
			(res.receptions_and_returns || []).forEach(function (rr) {
				html += '<tr><td><strong>' + rr.type + '</strong></td><td><code>' + rr.doc + '</code></td><td class="font-weight-bold">' + rr.qty + '</td><td>' + rr.location + '</td><td><span class="badge badge-success">' + rr.status + '</span></td></tr>';
			});
			html += '</tbody></table></div></div>';

			$('#dsmProduct360Body').html(html);
		});
	};

	// =========================================================================
	// 5. RIESGO DE VENCIMIENTO Y ACCIONES FEFO (SECCIÓN 6)
	// =========================================================================
	function renderFefoTable() {
		var list = dsmState.expiring_products || [];
		var tbody = '';
		list.forEach(function (ep, idx) {
			var imgUrl = Grocy.BaseUrl.replace(/\/$/, '') + ep.image;
			var jsonStr = JSON.stringify(ep).replace(/"/g, '&quot;');
			tbody += '<tr onclick="dsmOpenFefoActionModal(' + jsonStr + ')" title="Lote: ' + ep.lot + ' · Vence: ' + ep.expiry_date + ' · ' + ep.branch_name + ' (' + ep.location + ') · Estado: ' + ep.fefo_state + '">' +
				'<td class="font-weight-bold text-muted">' + (idx + 1) + '</td>' +
				'<td><div class="dsm-prod-cell"><img src="' + imgUrl + '" alt="' + ep.name + '" class="dsm-prod-thumb"><span class="dsm-prod-name">' + ep.name + '</span></div></td>' +
				'<td style="text-align:center;"><span class="dsm-days-badge">' + ep.days_left + '</span></td>' +
				'<td style="text-align:right; font-weight:600;">' + fmtNum(ep.stock) + '</td>' +
				'<td style="text-align:right; font-weight:700; color:#0f172a;">' + fmtMoney(ep.value) + '</td>' +
				'</tr>';
		});
		if (!tbody) {
			tbody = '<tr><td colspan="5" class="text-center text-muted py-3">Sin lotes próximos a vencer en este umbral</td></tr>';
		}
		$('#dsm-fefo-tbody').html(tbody);
	}

	$('#dsm-fefo-days-select').on('change', function () {
		dsmApplyAllFilters();
	});

	window.dsmOpenFefoActionModal = function (ep) {
		currentSelectedFefoItem = ep;
		var html = '<div class="d-flex justify-content-between align-items-center flex-wrap">' +
			'<div>' +
			'<h6 class="mb-1 font-weight-bold text-dark">' + ep.name + ' <span class="badge badge-danger ml-1">' + ep.days_left + ' días restantes</span></h6>' +
			'<div class="small text-muted">Lote: <code>' + ep.lot + '</code> &middot; Vencimiento: <strong>' + ep.expiry_date + '</strong> &middot; Sucursal: <strong>' + ep.branch_name + '</strong> (' + ep.location + ')</div>' +
			'</div>' +
			'<div class="text-right">' +
			'<div class="small text-muted">Unidades / Valor en Riesgo</div>' +
			'<div class="h5 mb-0 font-weight-bold text-danger">' + ep.stock + ' uds &middot; ' + fmtMoney(ep.value) + '</div>' +
			'</div>' +
			'</div>';
		$('#dsmFefoLotSummary').html(html);
		$('#dsmFefoModal').modal('show');
	};

	window.dsmSubmitFefoAction = function (actionKey) {
		if (!currentSelectedFefoItem) return;
		var payload = {
			action: actionKey,
			lot: currentSelectedFefoItem.lot,
			product_name: currentSelectedFefoItem.name,
			sku: currentSelectedFefoItem.sku,
			qty: currentSelectedFefoItem.stock,
			value: currentSelectedFefoItem.value,
			branch_name: currentSelectedFefoItem.branch_name,
			product_id: currentSelectedFefoItem.product_id,
			stock_id: currentSelectedFefoItem.stock_id,
			location_id: currentSelectedFefoItem.location_id,
			branch_id: currentSelectedFefoItem.branch_id,
			operation_id: 'fefo-' + actionKey + '-' + (currentSelectedFefoItem.stock_id || '') + '-' + Date.now(),
			notes: $('#dsmFefoNotes').val()
		};

		$.ajax({
			url: Grocy.BaseUrl.replace(/\/$/, '') + '/api/stockoverview/fefo-action',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function (res) {
				$('#dsmFefoModal').modal('hide');
				if (res.redirect) {
					window.location.href = Grocy.BaseUrl.replace(/\/$/, '') + res.redirect;
					return;
				}
				if (typeof toastr !== 'undefined') {
					toastr.success(res.summary + (res.doc_ref ? ' (Ref: ' + res.doc_ref + ')' : ''), 'Acción FEFO');
				}
				dsmApplyAllFilters();
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al procesar acción FEFO';
				if (typeof toastr !== 'undefined') toastr.error(msg);
			}
		});
	};

	// =========================================================================
	// 6. TENDENCIA POR SUCURSAL (SECCIÓN 7 — 8 SUCURSALES COMPARABLES)
	// =========================================================================
	function renderBranchesTable() {
		var list = (dsmState.branches_table || []).slice();
		list.sort(function (a, b) {
			var va = a[branchSortKey];
			var vb = b[branchSortKey];
			if (typeof va === 'string') {
				return branchSortAsc ? va.localeCompare(vb) : vb.localeCompare(va);
			}
			return branchSortAsc ? (va - vb) : (vb - va);
		});

		var activeBmode = $('.dsm-branch-pill[data-bmode].active').data('bmode') || 'value';
		var tbody = '';
		list.forEach(function (br, idx) {
			var hideClass = (!showAll8Branches && idx >= 5) ? 'd-none dsm-extra-branch-row' : '';
			var iconClass = br.status === 'optimal' ? 'is-optimal' : (br.status === 'warning' ? 'is-warning' : 'is-critical');
			var faIcon = br.status === 'optimal' ? 'fa-check' : (br.status === 'warning' ? 'fa-exclamation' : 'fa-xmark');
			var isSel = (dsmState.filters && String(dsmState.filters.branch_id) === String(br.id)) ? 'selected' : '';

			var col2Text = fmtMoney(br.inventory_value);
			if (activeBmode === 'variation') {
				var sign = br.prev_variation_pct > 0 ? '+' : '';
				col2Text = sign + br.prev_variation_pct + '% vs ant. (Dif ' + br.inventory_diff_pct + '%)';
			}

			tbody += '<tr class="' + hideClass + ' ' + isSel + '" data-branch-id="' + br.id + '" onclick="dsmSelectBranchRow(' + br.id + ')" title="Diferencia inventario: ' + br.inventory_diff_pct + '% · Variación período ant.: ' + (br.prev_variation_pct > 0 ? '+' : '') + br.prev_variation_pct + '%">' +
				'<td class="font-weight-bold text-dark">' + br.name + '</td>' +
				'<td style="text-align:right; font-weight:800;">' + col2Text + '</td>' +
				'<td style="text-align:right; font-weight:600;">' + fmtNum(br.units) + '</td>' +
				'<td style="text-align:center; font-weight:700; color:#ef4444;">' + Number(br.rotation).toFixed(1) + '</td>' +
				'<td style="text-align:center; color:#334155;">' + Number(br.coverage_days).toFixed(0) + ' días</td>' +
				'<td style="text-align:center;"><span class="dsm-state-icon ' + iconClass + '"><i class="fa-solid ' + faIcon + '"></i></span></td>' +
				'</tr>';
		});
		$('#dsm-branch-tbody').html(tbody);
	}

	window.dsmSortBranches = function (key) {
		if (branchSortKey === key) {
			branchSortAsc = !branchSortAsc;
		} else {
			branchSortKey = key;
			branchSortAsc = false;
		}
		renderBranchesTable();
	};

	window.dsmSelectBranchRow = function (branchId) {
		var cur = $('#dsm-filter-branch').val();
		if (String(cur) === String(branchId)) {
			$('#dsm-filter-branch').val('all');
		} else {
			$('#dsm-filter-branch').val(String(branchId));
		}
		dsmApplyAllFilters();
	};

	$(document).on('click', '.dsm-branch-pill[data-bmode]', function () {
		$('.dsm-branch-pill[data-bmode]').removeClass('active');
		$(this).addClass('active');
		var mode = $(this).data('bmode');
		if (mode === 'variation') {
			$('#dsm-bcol-val').text('Variación / Dif.');
		} else {
			$('#dsm-bcol-val').text('Valor inventario');
		}
		renderBranchesTable();
	});

	$('#dsm-btn-toggle-all-branches').on('click', function () {
		showAll8Branches = !showAll8Branches;
		$(this).toggleClass('active', showAll8Branches);
		renderBranchesTable();
	});

	// =========================================================================
	// 7. MOVIMIENTOS EN TIEMPO REAL Y KARDEX MODAL (SECCIÓN 8)
	// =========================================================================
	function renderMovementsBars() {
		if (!dsmState.movements) return;
		var selVal = $('#dsm-moves-branch-select').val();
		var bars = (selVal === 'detailed_7')
			? (dsmState.movements.bars_detailed || [])
			: (dsmState.movements.bars_photo || []);

		var maxMove = 1;
		bars.forEach(function (b) { if (b.units > maxMove) maxMove = b.units; });

		var html = '';
		bars.forEach(function (bar) {
			var h = Math.max(14, Math.round((bar.units / maxMove) * 145));
			html += '<div class="dsm-move-bar-col" onclick="dsmOpenMovementsModal(\'' + bar.key + '\')" title="' + bar.full_label + ': ' + fmtNum(bar.units) + ' uds">' +
				'<span class="dsm-move-bar-val">' + fmtNum(bar.units) + '</span>' +
				'<div class="dsm-move-bar-fill" style="height:' + h + 'px; background:' + bar.color + ';"></div>' +
				'<span class="dsm-move-bar-label">' + bar.label + '</span>' +
				'</div>';
		});
		$('#dsm-moves-bars-container').html(html);
	}

	$('#dsm-moves-branch-select').on('change', function () {
		var val = $(this).val();
		if (val !== 'all' && val !== 'detailed_7') {
			$('#dsm-filter-branch').val(val);
			dsmApplyAllFilters();
		} else {
			renderMovementsBars();
		}
	});

	window.dsmOpenMovementsModal = function (catKey) {
		$('#dsmMovementsModal').modal('show');
		dsmRenderMovementsModalRows(catKey || 'todos');
	};

	window.dsmFilterModalMovements = function (catKey, btn) {
		$(btn).siblings().removeClass('active');
		$(btn).addClass('active');
		dsmRenderMovementsModalRows(catKey);
	};

	function dsmRenderMovementsModalRows(catKey) {
		var ledger = (dsmState.movements && dsmState.movements.ledger) ? dsmState.movements.ledger : [];
		var rows = ledger.filter(function (r) {
			if (!catKey || catKey === 'todos') return true;
			if (catKey === 'otros') return ['despachos', 'ajustes', 'mermas'].indexOf(r.category_key) !== -1;
			return r.category_key === catKey;
		});

		var tbody = '';
		rows.forEach(function (r) {
			var qtyColor = String(r.quantity).indexOf('-') === 0 ? 'text-danger' : 'text-success';
			tbody += '<tr>' +
				'<td><code>' + r.mov_id + '</code></td>' +
				'<td class="text-muted">' + r.timestamp + '</td>' +
				'<td><strong>' + r.movement_type + '</strong></td>' +
				'<td>' + r.product_name + ' <span class="badge badge-light border">' + r.sku + '</span></td>' +
				'<td class="text-right font-weight-bold ' + qtyColor + '">' + r.quantity + '</td>' +
				'<td>' + r.branch_name + '</td>' +
				'<td>' + r.origin_location + ' &rarr; ' + r.dest_location + '</td>' +
				'<td>' + r.user + '</td>' +
				'<td><code>' + r.ref_doc + '</code></td>' +
				'<td><span class="badge badge-' + (r.badge_class || 'success') + '">' + r.validation_status + '</span></td>' +
				'</tr>';
		});
		if (!tbody) {
			tbody = '<tr><td colspan="10" class="text-center text-muted py-3">No hay movimientos registrados para este filtro</td></tr>';
		}
		$('#dsmMovementsModalTbody').html(tbody);
	}

	// =========================================================================
	// 8. DECISIONES PRIORITARIAS (SECCIÓN 9)
	// =========================================================================
	window.dsmHandleDecisionClick = function (dec) {
		if (!dec) return;
		if (dec.action_type === 'open_fefo_modal') {
			var firstLot = (dsmState.expiring_products && dsmState.expiring_products[0]) ? dsmState.expiring_products[0] : null;
			if (firstLot) {
				dsmOpenFefoActionModal(firstLot);
				return;
			}
		}
		dsmOpenAllDecisionsModal();
	};

	window.dsmOpenAllDecisionsModal = function () {
		$('#dsmDecisionsModal').modal('show');
		dsmRenderDecisionsModalRows('all');
	};

	window.dsmFilterDecisionsModal = function (sev, btn) {
		$(btn).siblings().removeClass('active');
		$(btn).addClass('active');
		dsmRenderDecisionsModalRows(sev);
	};

	function dsmRenderDecisionsModalRows(sev) {
		var all = (dsmState.priority_decisions && dsmState.priority_decisions.all) ? dsmState.priority_decisions.all : [];
		var rows = all.filter(function (d) {
			return !sev || sev === 'all' || d.severity === sev;
		});

		var tbody = '';
		rows.forEach(function (d) {
			var badgeMap = { red: 'badge-danger', yellow: 'badge-warning text-dark', green: 'badge-success' };
			var hrefUrl = Grocy.BaseUrl.replace(/\/$/, '') + d.href;
			tbody += '<tr>' +
				'<td><span class="badge ' + (badgeMap[d.severity] || 'badge-secondary') + '">' + d.category_label + ' (' + d.priority + ')</span></td>' +
				'<td><strong>' + d.title + '</strong></td>' +
				'<td><div>' + d.line1 + '</div><div class="text-muted small">' + d.line2 + '</div></td>' +
				'<td>' + d.owner + '</td>' +
				'<td class="text-muted">' + d.date + '</td>' +
				'<td><span class="badge badge-light border">' + d.status + '</span></td>' +
				'<td class="text-right"><a href="' + hrefUrl + '" class="btn btn-xs btn-outline-success font-weight-bold">' + d.action_label + ' &rarr;</a></td>' +
				'</tr>';
		});
		$('#dsmDecisionsModalTbody').html(tbody);
	}

	// =========================================================================
	// 9. DESGLOSE DE TARJETA KPI Y CONCILIACIÓN (SECCIÓN 1 & 13)
	// =========================================================================
	$(document).on('click', '.dsm-kpi-card', function () {
		var kpiKey = $(this).data('kpi');
		var kpis = dsmState.kpis || {};
		var k = kpis[kpiKey];
		if (!k) return;

		var recon = (dsmState.meta && dsmState.meta.reconciliation) ? dsmState.meta.reconciliation : {};
		$('#dsmKpiModalTitle').html('<i class="fa-solid ' + k.icon + ' text-success mr-2"></i>' + k.label + ' — Fórmula y Conciliación');

		var html = '<div class="p-3 mb-3 rounded border bg-light">' +
			'<div class="d-flex justify-content-between align-items-center">' +
			'<div><span class="text-muted small text-uppercase font-weight-bold">Resultado Actual</span><div class="h3 mb-0 font-weight-bold text-dark">' + k.value + '</div></div>' +
			'<div class="text-right"><span class="text-muted small">Variación vs. Período Anterior</span><div class="h5 mb-0 font-weight-bold ' + (k.delta_is_good ? 'text-success' : 'text-danger') + '">' + k.delta + ' (' + k.subtext + ')</div></div>' +
			'</div>' +
			'<hr class="my-2">' +
			'<div class="small text-dark"><strong>Fórmula y Definición:</strong> ' + k.formula + '</div>' +
			'</div>';

		html += '<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-scale-balanced text-success mr-1"></i>Verificación de Conciliación Transversal</h6>' +
			'<table class="table table-sm table-bordered mb-0" style="font-size:12px;">' +
			'<thead class="thead-light"><tr><th>Componente / Fuente</th><th class="text-right">Valor Monetario</th><th class="text-right">Unidades Físicas</th><th>Estado de Conciliación</th></tr></thead>' +
			'<tbody>' +
			(function () {
				// Estado calculado con las cifras recibidas: conciliado solo si coinciden (tolerancia $1)
				var base = Number(recon.kpi_valuation || 0);
				var badge = function (v) {
					if (recon.kpi_valuation === undefined || v === undefined) return '<span class="badge badge-secondary">Sin información</span>';
					return Math.abs(Number(v) - base) <= 1 ? '<span class="badge badge-success">Conciliado</span>' : '<span class="badge badge-warning">Diferencia ' + fmtMoney(Number(v) - base) + '</span>';
				};
				var row = function (label, v, u) {
					return '<tr><td><strong>' + label + '</strong></td><td class="text-right font-weight-bold">' + (v === undefined ? 'Sin información' : fmtMoney(v)) + '</td><td class="text-right font-weight-bold">' + (u === undefined ? '—' : fmtNum(u) + ' uds') + '</td><td>' + badge(v) + '</td></tr>';
				};
				return row('1. Tarjetas KPI Superiores', recon.kpi_valuation, recon.kpi_units) +
					row('2. Suma Departamentos (Gráfico Donut)', recon.donut_sum_valuation, recon.donut_sum_units) +
					row('3. Suma de sucursales', recon.branches_sum_valuation, recon.branches_sum_units) +
					row('4. Lotes físicos (stock)', recon.sqlite_local_valuation, undefined);
			})() +
			'</tbody></table>';

		$('#dsmKpiModalBody').html(html);
		$('#dsmKpiModal').modal('show');
	});

	// =========================================================================
	// 10. FLUJO OPERATIVO INTEGRADO DE 10 PASOS (SECCIÓN 11)
	// =========================================================================
	window.dsmOpenWorkflowModal = function () {
		$('#dsmWfResultBox').addClass('d-none').html('');
		dsmToggleWfOptions();
		$('#dsmWorkflowModal').modal('show');
	};

	window.dsmToggleWfOptions = function () {
		var st = $('#dsmWfStepType').val();
		$('#dsmWfReturnRow').toggleClass('d-none', st !== 'return');
		$('#dsmWfSupervisorRow').toggleClass('d-none', st !== 'supervisor_decision');
	};

	window.dsmExecuteWorkflowStep = function () {
		var prodParts = ($('#dsmWfProduct').val() || 'Leche Entera 1L|DAI-001|10.0').split('|');
		var payload = {
			step_type: $('#dsmWfStepType').val(),
			product_name: prodParts[0],
			sku: prodParts[1],
			unit_cost: parseFloat(prodParts[2] || '10'),
			qty: parseFloat($('#dsmWfQty').val() || '100'),
			branch_name: $('#dsmWfBranch').val(),
			location: $('#dsmWfLocation').val(),
			return_state: $('#dsmWfReturnState').val(),
			decision: $('#dsmWfDecision').val(),
			operation_id: 'wf-' + Date.now()
		};

		$.ajax({
			url: Grocy.BaseUrl.replace(/\/$/, '') + '/api/stockoverview/workflow',
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function (res) {
				var stepsHtml = '<strong><i class="fa-solid fa-circle-check mr-1"></i>Trazabilidad Confirmada (' + res.doc_ref + '):</strong><ul class="mb-0 pl-3 mt-1">';
				(res.steps_completed || []).forEach(function (s) {
					stepsHtml += '<li>' + s + '</li>';
				});
				stepsHtml += '</ul>';
				$('#dsmWfResultBox').removeClass('d-none alert-danger').addClass('alert-success').html(stepsHtml);
				if (typeof toastr !== 'undefined') {
					toastr.success('Operación ' + res.doc_ref + ' registrada. Saldo: ' + res.stock_before + ' → ' + res.stock_after + ' u.', 'Flujo Integrado');
				}
				dsmApplyAllFilters();
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al ejecutar paso operativo';
				$('#dsmWfResultBox').removeClass('d-none alert-success').addClass('alert-danger').html('<i class="fa-solid fa-triangle-exclamation mr-1"></i>' + msg);
			}
		});
	};

	window.dsmResetDemoWorkflow = function () {
		$.post(Grocy.BaseUrl.replace(/\/$/, '') + '/api/stockoverview/reset', function (res) {
			$('#dsmWfResultBox').removeClass('d-none alert-danger').addClass('alert-info').text((res && res.message) || '');
			dsmApplyAllFilters();
		});
	};

	// =========================================================================
	// 11. FILTROS GLOBALES Y SINCRONIZACIÓN REACTIVA (SECCIÓN 10)
	// =========================================================================
	window.dsmApplyAllFilters = function () {
		var $btn = $('#dsm-btn-refresh');
		$btn.addClass('spinning');

		var params = {
			date: $('#dsm-filter-date').val(),
			period: currentPeriod,
			branch_id: $('#dsm-filter-branch').val(),
			department: $('#dsm-filter-dept').val(),
			category: $('#dsm-filter-category').val(),
			supplier: $('#dsm-filter-supplier').val(),
			sku: $('#dsm-filter-sku').val(),
			status: $('#dsm-filter-status').val(),
			location: $('#dsm-filter-location').val(),
			movement_type: $('#dsm-filter-movtype').val(),
			fefo_days: $('#dsm-fefo-days-select').val()
		};

		$.getJSON(Grocy.BaseUrl.replace(/\/$/, '') + '/api/stockoverview/executive', params, function (data) {
			dsmState = data;
			updateDashboardDOM();
			$btn.removeClass('spinning');
		}).fail(function () {
			$btn.removeClass('spinning');
		});
	};

	window.dsmResetAllFilters = function () {
		$('#dsm-filter-date').prop('selectedIndex', 0);
		$('#dsm-filter-branch').val('all');
		$('#dsm-filter-dept').val('todos');
		$('#dsm-filter-category').val('todas');
		$('#dsm-filter-supplier').val('todos');
		$('#dsm-filter-sku').val('');
		$('#dsm-filter-status').val('todos');
		$('#dsm-filter-location').val('todas');
		$('#dsm-filter-movtype').val('todos');
		$('#dsm-fefo-days-select').val('30');
		$('#dsm-dept-drilldown').removeClass('show');
		dsmApplyAllFilters();
	};

	function updateDashboardDOM() {
		if (!dsmState) return;

		// Actualizar hora de sincronización
		if (dsmState.meta && dsmState.meta.synced_at_real) {
			$('#dsm-last-updated').text(dsmState.meta.last_updated + ' · Sync ' + dsmState.meta.synced_at_real.split(' ').pop());
		}

		// Actualizar las 8 tarjetas KPI
		var kpis = dsmState.kpis || {};
		Object.keys(kpis).forEach(function (kKey) {
			var k = kpis[kKey];
			$('#dsm-val-' + kKey).text(k.value);
			$('#dsm-delta-' + kKey).text(k.delta).toggleClass('is-good', !!k.delta_is_good).toggleClass('is-bad', !k.delta_is_good);
			$('#dsm-sub-' + kKey).text(k.subtext);
			$('#dsm-card-' + kKey).attr('title', k.formula);
		});

		// Actualizar gráficos y tablas
		initEvolutionChart();
		initDonutChart();
		renderTop10Table();
		renderFefoTable();
		renderBranchesTable();
		renderMovementsBars();

		// Actualizar decisiones prioritarias destacadas
		var decs = (dsmState.priority_decisions && dsmState.priority_decisions.featured) ? dsmState.priority_decisions.featured : [];
		var decHtml = '';
		decs.forEach(function (dec) {
			var jsonStr = JSON.stringify(dec).replace(/"/g, '&quot;');
			decHtml += '<div class="dsm-decision-item is-' + dec.severity + '" onclick="dsmHandleDecisionClick(' + jsonStr + ')" title="Responsable: ' + dec.owner + ' · Prioridad: ' + dec.priority + '">' +
				'<div class="dsm-dec-left">' +
				'<div class="dsm-dec-icon"><i class="fa-solid ' + dec.icon + '"></i></div>' +
				'<div class="dsm-dec-text">' +
				'<span class="dsm-dec-title">' + dec.title + '</span>' +
				'<span class="dsm-dec-sub">' + dec.line1 + '</span>' +
				'<span class="dsm-dec-sub">' + dec.line2 + '</span>' +
				'</div>' +
				'</div>' +
				'<i class="fa-solid fa-chevron-right dsm-dec-arrow"></i>' +
				'</div>';
		});
		$('#dsm-decisions-featured').html(decHtml);
	}

	// Eventos de controles superiores y gráficos
	$('#dsm-filter-date, #dsm-filter-branch, #dsm-filter-dept, #dsm-filter-category').on('change', function () {
		dsmApplyAllFilters();
	});

	$('#dsm-btn-refresh').on('click', function () {
		dsmApplyAllFilters();
		if (typeof toastr !== 'undefined') {
			toastr.success('Indicadores, gráficos y trazabilidad sincronizados.', 'Stock & Movimientos');
		}
	});

	$('#dsm-btn-adv-filters').on('click', function () {
		$(this).toggleClass('active');
		$('#dsm-advanced-filters-panel').toggleClass('show');
	});

	$('.dsm-period-pill').on('click', function () {
		$('.dsm-period-pill').removeClass('active');
		$(this).addClass('active');
		currentPeriod = $(this).data('period');
		if (dsmState.evolution && dsmState.evolution.all_periods && dsmState.evolution.all_periods[currentPeriod]) {
			dsmState.evolution.series = dsmState.evolution.all_periods[currentPeriod];
			initEvolutionChart();
		} else {
			dsmApplyAllFilters();
		}
	});

	$('#dsm-evo-view-select').on('change', function () {
		currentEvoView = $(this).val();
		initEvolutionChart();
	});

	$('#dsm-leg-units').on('click', function () {
		showUnitsDataset = !showUnitsDataset;
		initEvolutionChart();
	});

	$('#dsm-donut-metric-select').on('change', function () {
		currentDonutMetric = $(this).val();
		initDonutChart();
	});

	// Inicializar al cargar el DOM
	$(document).ready(function () {
		initEvolutionChart();
		initDonutChart();
	});

})();
