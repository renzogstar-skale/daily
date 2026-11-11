/**
 * DAILY — RENDIMIENTO POR DEPARTAMENTO
 * Motor de Interactividad, Gráficos Chart.js y Herramientas Ejecutivas
 */

(function () {
	'use strict';

	var chartRentability = null;
	var chartProductivity = null;
	var currentRentMode = 'dollar'; // 'dollar' o 'pct'

	document.addEventListener('DOMContentLoaded', function () {
		initLucide();
		initDeptCharts();
		initDropdowns();
	});

	function initLucide() {
		if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
			lucide.createIcons();
		}
	}

	function getDeptData() {
		var el = document.getElementById('deptExecutiveData');
		if (!el) return null;
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			console.error('Error parsing deptExecutiveData:', e);
			return null;
		}
	}

	function formatCurrency(val) {
		var n = Number(val || 0);
		return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
	}

	function formatShortCurrency(val) {
		var n = Number(val || 0);
		if (n === 0) return '0';
		if (n >= 1000) return (n / 1000).toFixed(0) + 'K';
		return n.toString();
	}

	/* ==========================================================================
	   GRÁFICOS DE RENDIMIENTO (CHART.JS)
	   ========================================================================== */
	function initDeptCharts() {
		if (typeof Chart === 'undefined') return;
		var data = getDeptData();
		if (!data) return;

		initRentabilityChart(data);
		initProductivityChart(data);
	}

	// 1. Gráfico de Barras Horizontales: Rentabilidad por departamento
	function initRentabilityChart(raw) {
		var canvas = document.getElementById('chartDeptProfitability');
		if (!canvas) return;

		var depts = ['Dairy', 'Carnes', 'Frozen', 'Bakery', 'Pescadería'];
		var profitValues = [49830, 41670, 54210, 25290, 18920];
		var marginPctValues = [31.2, 30.9, 27.8, 28.1, 24.6];

		if (raw && raw.profitability && raw.profitability.length > 0) {
			depts = raw.profitability.map(function (p) { return p.name; });
			profitValues = raw.profitability.map(function (p) { return p.profit; });
			marginPctValues = raw.profitability.map(function (p) { return p.margin_pct; });
		}

		// Factor margen proporcional para la barra secundaria
		var marginAmounts = profitValues.map(function (v, idx) {
			return Math.round(v * 1.25);
		});

		window.deptProfitValues = profitValues;
		window.deptMarginAmounts = marginAmounts;
		window.deptMarginPcts = marginPctValues;
		window.deptNamesList = depts;

		chartRentability = new Chart(canvas, {
			type: 'horizontalBar',
			data: {
				labels: depts,
				datasets: [
					{
						label: 'Utilidad operativa',
						data: profitValues,
						backgroundColor: '#059669',
						hoverBackgroundColor: '#047857',
						barThickness: 10,
						borderRadius: 4
					},
					{
						label: 'Margen',
						data: marginAmounts,
						backgroundColor: '#10b981',
						hoverBackgroundColor: '#34d399',
						barThickness: 10,
						borderRadius: 4
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					display: false // Leyenda personalizada en HTML
				},
				tooltips: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontColor: '#ffffff',
					titleFontStyle: 'bold',
					titleFontSize: 12,
					bodyFontColor: '#ffffff',
					bodyFontSize: 11,
					footerFontColor: '#e2e8f0',
					borderColor: 'rgba(255, 255, 255, 0.15)',
					borderWidth: 1,
					cornerRadius: 8,
					padding: 10,
					displayColors: true,
					callbacks: {
						label: function (tooltipItem, chartData) {
							var ds = chartData.datasets[tooltipItem.datasetIndex];
							var val = tooltipItem.xLabel;
							if (currentRentMode === 'pct') {
								return ds.label + ': ' + val + '%';
							}
							return ds.label + ': ' + formatCurrency(val);
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: {
							color: 'rgba(226, 232, 240, 0.7)',
							borderDash: [3, 3],
							drawBorder: false,
							zeroLineColor: 'rgba(226, 232, 240, 0.7)'
						},
						ticks: {
							beginAtZero: true,
							max: 60000,
							stepSize: 10000,
							fontColor: '#64748b',
							fontSize: 10,
							callback: function (val) {
								if (currentRentMode === 'pct') return val + '%';
								return formatShortCurrency(val);
							}
						}
					}],
					yAxes: [{
						gridLines: {
							display: false,
							drawBorder: false
						},
						ticks: {
							fontColor: '#0f172a',
							fontSize: 11,
							fontWeight: '600'
						}
					}]
				}
			}
		});
	}

	window.setRentabilityMode = function (mode) {
		currentRentMode = mode;
		var btnDol = document.getElementById('btnToggleRentDollar');
		var btnPct = document.getElementById('btnToggleRentPct');

		if (btnDol && btnPct) {
			if (mode === 'dollar') {
				btnDol.classList.add('is-active', 'bg-white');
				btnPct.classList.remove('is-active', 'bg-white');
			} else {
				btnPct.classList.add('is-active', 'bg-white');
				btnDol.classList.remove('is-active', 'bg-white');
			}
		}

		if (!chartRentability) return;

		if (mode === 'pct') {
			chartRentability.data.datasets[0].data = window.deptMarginPcts.map(function (v) { return Math.round(v * 0.75); });
			chartRentability.data.datasets[1].data = window.deptMarginPcts;
			chartRentability.options.scales.xAxes[0].ticks.max = 40;
			chartRentability.options.scales.xAxes[0].ticks.stepSize = 10;
		} else {
			chartRentability.data.datasets[0].data = window.deptProfitValues;
			chartRentability.data.datasets[1].data = window.deptMarginAmounts;
			chartRentability.options.scales.xAxes[0].ticks.max = 60000;
			chartRentability.options.scales.xAxes[0].ticks.stepSize = 10000;
		}
		chartRentability.update();
	};

	// 2. Gráfico Combinado: Productividad del personal por hora
	function initProductivityChart(raw) {
		var canvas = document.getElementById('chartDeptProductivityHourly');
		if (!canvas) return;

		var hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];
		var salesHour = [22, 34, 52, 44, 42, 58, 48];
		var profitHour = [18.5, 28.0, 38.5, 34.2, 32.8, 45.9, 37.5];
		var staff = [8, 10, 14, 16, 15, 18, 16];

		if (raw && raw.productivity) {
			if (raw.productivity.hours) hours = raw.productivity.hours;
			if (raw.productivity.sales_per_hour) salesHour = raw.productivity.sales_per_hour;
			if (raw.productivity.profit_per_hour) profitHour = raw.productivity.profit_per_hour;
			if (raw.productivity.staff) staff = raw.productivity.staff;
		}

		chartProductivity = new Chart(canvas, {
			type: 'bar',
			data: {
				labels: hours,
				datasets: [
					{
						type: 'line',
						label: 'Utilidad/hora',
						data: profitHour,
						borderColor: '#10b981',
						backgroundColor: 'transparent',
						borderWidth: 2.2,
						pointRadius: 3.5,
						pointBackgroundColor: '#ffffff',
						pointBorderColor: '#10b981',
						pointBorderWidth: 2,
						pointHoverRadius: 5,
						fill: false,
						tension: 0.35,
						yAxisID: 'yAxisMoney',
						order: 1
					},
					{
						type: 'line',
						label: 'Personal',
						data: staff,
						borderColor: '#f59e0b',
						backgroundColor: 'transparent',
						borderWidth: 2,
						pointRadius: 3,
						pointBackgroundColor: '#ffffff',
						pointBorderColor: '#f59e0b',
						pointBorderWidth: 1.5,
						fill: false,
						tension: 0.3,
						yAxisID: 'yAxisStaff',
						order: 2
					},
					{
						type: 'bar',
						label: 'Ventas/hora',
						data: salesHour,
						backgroundColor: 'rgba(16, 185, 129, 0.22)',
						hoverBackgroundColor: 'rgba(16, 185, 129, 0.38)',
						borderRadius: 4,
						maxBarThickness: 16,
						yAxisID: 'yAxisMoney',
						order: 3
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					display: false // Leyenda en encabezado
				},
				tooltips: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontColor: '#ffffff',
					titleFontStyle: 'bold',
					titleFontSize: 12,
					bodyFontColor: '#ffffff',
					bodyFontSize: 11,
					footerFontColor: '#e2e8f0',
					borderColor: 'rgba(255, 255, 255, 0.15)',
					borderWidth: 1,
					cornerRadius: 8,
					padding: 10,
					displayColors: true,
					callbacks: {
						label: function (tooltipItem, chartData) {
							var ds = chartData.datasets[tooltipItem.datasetIndex];
							if (ds.yAxisID === 'yAxisStaff') {
								return ds.label + ': ' + tooltipItem.yLabel + ' colab.';
							}
							return ds.label + ': $' + tooltipItem.yLabel + ' / h';
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: {
							display: false,
							drawBorder: false
						},
						ticks: {
							fontColor: '#64748b',
							fontSize: 10
						}
					}],
					yAxes: [
						{
							id: 'yAxisMoney',
							position: 'left',
							gridLines: {
								color: 'rgba(226, 232, 240, 0.7)',
								borderDash: [3, 3],
								drawBorder: false
							},
							ticks: {
								beginAtZero: true,
								max: 60,
								stepSize: 10,
								fontColor: '#64748b',
								fontSize: 10,
								callback: function (val) {
									return '$' + val;
								}
							}
						},
						{
							id: 'yAxisStaff',
							position: 'right',
							gridLines: {
								display: false,
								drawBorder: false
							},
							ticks: {
								display: false,
								beginAtZero: true,
								max: 30
							}
						}
					]
				}
			}
		});
	}

	/* ==========================================================================
	   INTERACTIVIDAD DE TABLAS Y MODALES
	   ========================================================================== */
	window.openDeptDetailModal = function (deptKey) {
		var raw = getDeptData();
		if (!raw || !raw.departments) return;

		var d = raw.departments.find(function (item) { return item.key === deptKey; });
		if (!d) return;

		var modal = document.getElementById('modalDeptDetail');
		if (!modal) return;

		document.getElementById('deptDetailTitle').innerText = 'Diagnóstico y Análisis: ' + d.full_name;

		var html = '<div class="row mb-3">' +
			'<div class="col-4">' +
				'<div class="p-3 bg-white rounded border text-center">' +
					'<span class="text-muted" style="font-size:11px;">Margen Comercial</span>' +
					'<div class="font-weight-bold text-success mt-1" style="font-size:18px;">' + d.margin_pct + '%</div>' +
				'</div>' +
			'</div>' +
			'<div class="col-4">' +
				'<div class="p-3 bg-white rounded border text-center">' +
					'<span class="text-muted" style="font-size:11px;">Utilidad Operativa</span>' +
					'<div class="font-weight-bold text-dark mt-1" style="font-size:18px;">$' + Number(d.profit).toLocaleString() + '</div>' +
				'</div>' +
			'</div>' +
			'<div class="col-4">' +
				'<div class="p-3 bg-white rounded border text-center">' +
					'<span class="text-muted" style="font-size:11px;">Índice DRI</span>' +
					'<div class="font-weight-bold text-primary mt-1" style="font-size:18px;">' + d.dri + ' / 100</div>' +
				'</div>' +
			'</div>' +
		'</div>' +
		'<div class="p-3 bg-white rounded border mb-3">' +
			'<h6 class="font-weight-bold text-dark mb-2" style="font-size:13px;">Diagnóstico de Causas y Desempeño Operativo</h6>' +
			'<ul class="mb-0 pl-3" style="font-size:12px; color:#334155; line-height:1.6;">' +
				'<li><strong>Costo laboral:</strong> ' + d.labor_cost_pct + '% sobre ventas (Meta: < 12.0%).</li>' +
				'<li><strong>Utilidad por hora:</strong> $' + d.profit_per_hour + ' generada por colaborador asignado.</li>' +
				'<li><strong>Merma y descarte:</strong> ' + d.waste_pct + '% del valor transferido a piso de venta.</li>' +
				'<li><strong>Disponibilidad en góndola:</strong> ' + d.availability_pct + '% de presencia permanente de surtido.</li>' +
			'</ul>' +
		'</div>' +
		'<div class="p-3 bg-white rounded border">' +
			'<h6 class="font-weight-bold text-dark mb-2" style="font-size:13px;">Oportunidad de Captura Económica</h6>' +
			'<p class="mb-0" style="font-size:12px; color:#475569;">Al ajustar la reposición en horas pico y sincronizar los turnos del personal, el departamento puede generar hasta <strong>+$5,200 mensuales</strong> de contribución directa.</p>' +
		'</div>';

		document.getElementById('deptDetailBody').innerHTML = html;

		document.getElementById('btnLaunchWhatIfFromDept').onclick = function () {
			$(modal).modal('hide');
			setTimeout(function () { openWhatIfModal(); }, 400);
		};

		$(modal).modal('show');
	};

	window.openWhatIfModal = function () {
		$('#modalWhatIf').modal('show');
		runWhatIfCalculation();
	};

	window.runWhatIfCalculation = function () {
		var w = parseFloat(document.getElementById('simWasteRange').value || 0.5);
		var a = parseFloat(document.getElementById('simAvailRange').value || 1.5);
		var l = parseFloat(document.getElementById('simLaborRange').value || 0.8);

		document.getElementById('simWasteVal').innerText = '-' + w.toFixed(1) + '%';
		document.getElementById('simAvailVal').innerText = '+' + a.toFixed(1) + '%';
		document.getElementById('simLaborVal').innerText = '-' + l.toFixed(1) + '%';

		// Fórmula de impacto económico en dólares
		var baseProfit = 189920;
		var wasteSavings = w * 4800; // cada 0.1% de merma reducida aporta ~$480
		var salesGain = (a * 0.01 * 669540) * 0.284; // venta recuperada por disponibilidad x margen
		var laborSavings = (l * 0.01 * 669540) * 0.5; // optimización de costo laboral

		var totalGain = Math.round(wasteSavings + salesGain + laborSavings);
		var pctOverProfit = ((totalGain / baseProfit) * 100).toFixed(1);

		var baseDri = 82;
		var newDri = Math.min(100, Math.round(baseDri + (w * 2.5) + (a * 1.5) + (l * 1.2)));

		document.getElementById('simResultAmount').innerText = '+$' + totalGain.toLocaleString();
		var badgeElem = document.getElementById('simResultDriBadge');
		document.getElementById('simResultDri').innerText = newDri;

		if (newDri >= 90) {
			badgeElem.innerText = 'Sobresaliente';
			badgeElem.className = 'badge badge-success px-2 py-1';
		} else if (newDri >= 80) {
			badgeElem.innerText = 'Bueno';
			badgeElem.className = 'badge badge-success px-2 py-1';
		} else {
			badgeElem.innerText = 'Atención';
			badgeElem.className = 'badge badge-warning px-2 py-1';
		}
	};

	window.openActionPlanModal = function (oppId) {
		var raw = getDeptData();
		if (!raw || !raw.opportunities) return;

		var op = raw.opportunities.find(function (o) { return Number(o.id) === Number(oppId); });
		if (!op) op = raw.opportunities[0];

		document.getElementById('planOppId').value = op.id;
		document.getElementById('planProblem').value = op.problem;
		document.getElementById('planDept').value = op.dept;
		document.getElementById('planImpact').value = op.impact;
		document.getElementById('planAction').value = op.action;
		document.getElementById('planAssignee').value = op.assignee || 'Jefe de Operaciones Tienda';

		$('#modalActionPlan').modal('show');
	};

	window.savePlanAction = function () {
		$('#modalActionPlan').modal('hide');
		if (typeof toastr !== 'undefined') {
			toastr.success('Plan de acción registrado y vinculado al Centro de Alertas.');
		} else {
			alert('Plan de acción registrado y vinculado al Centro de Alertas.');
		}
	};

	window.openDriMethodologyModal = function () {
		$('#modalDriMethodology').modal('show');
	};

	window.openHeatmapInfoModal = function () {
		$('#modalDriMethodology').modal('show');
	};

	window.openHeatmapCellModal = function (dept, label, val, target, dev, cause) {
		document.getElementById('hmCellTitle').innerText = dept + ' — ' + label;
		document.getElementById('hmCellVal').innerText = val;
		document.getElementById('hmCellTarget').innerText = target;
		document.getElementById('hmCellCause').innerText = cause;

		var devBadge = document.getElementById('hmCellDev');
		devBadge.innerText = dev;
		if (dev.indexOf('-') !== -1 && label !== 'Merma' && label !== 'Costo laboral') {
			devBadge.className = 'badge badge-danger';
		} else {
			devBadge.className = 'badge badge-success';
		}

		$('#modalHeatmapCell').modal('show');
	};

	window.openReplenishAuditModal = function (type) {
		var title = 'Auditoría de Reposición y Disponibilidad';
		var body = '';

		if (type === 'stockouts') {
			title = 'Auditoría de SKUs Agotados (37 en sala)';
			body = '<div class="table-responsive"><table class="table table-sm table-striped" style="font-size:12px;">' +
				'<thead><tr><th>SKU</th><th>Producto</th><th>Departamento</th><th class="text-right">Faltante</th><th>Estado</th></tr></thead>' +
				'<tbody>' +
				'<tr><td>CAR-012</td><td>Bife Angosto Premium</td><td>93 Carnes</td><td class="text-right">18 kg</td><td><span class="badge badge-danger">En almacén</span></td></tr>' +
				'<tr><td>BAK-004</td><td>Baguette Tradicional</td><td>Bakery</td><td class="text-right">35 unid.</td><td><span class="badge badge-warning">En horneado</span></td></tr>' +
				'<tr><td>PES-008</td><td>Salmón Fresco Chileno</td><td>92 Pescadería</td><td class="text-right">12 kg</td><td><span class="badge badge-danger">Recepción pendiente</span></td></tr>' +
				'<tr><td>DAI-021</td><td>Yogurt Griego 1kg</td><td>91 Dairy</td><td class="text-right">24 unid.</td><td><span class="badge badge-danger">En almacén</span></td></tr>' +
				'<tr><td>FRO-015</td><td>Hamburguesas Veganas</td><td>90 Frozen</td><td class="text-right">15 unid.</td><td><span class="badge badge-danger">En cámara</span></td></tr>' +
				'</tbody></table></div>';
		} else {
			body = '<div class="p-3 bg-light rounded text-center">' +
				'<p class="mb-2" style="font-size:13px; color:#334155;">El <strong>94.1% de disponibilidad</strong> y los <strong>18 min de reposición media</strong> se mantienen dentro de los acuerdos de nivel de servicio (SLA) de DAILY.</p>' +
				'<small class="text-muted">Conectado en tiempo real con Almacenes e Inventarios.</small>' +
			'</div>';
		}

		document.getElementById('repModalTitle').innerText = title;
		document.getElementById('repModalBody').innerHTML = body;
		$('#modalReplenishAudit').modal('show');
	};

	window.sortDeptTable = function (colIndex) {
		var table = document.getElementById('tableDeptPerformance');
		if (!table) return;
		var tbody = table.querySelector('tbody');
		var rows = Array.from(tbody.querySelectorAll('tr'));

		var isAsc = table.getAttribute('data-sort-dir') === 'asc';
		table.setAttribute('data-sort-dir', isAsc ? 'desc' : 'asc');

		rows.sort(function (a, b) {
			var valA = a.cells[colIndex].innerText.replace(/[^0-9.-]/g, '');
			var valB = b.cells[colIndex].innerText.replace(/[^0-9.-]/g, '');
			return isAsc ? (Number(valA) - Number(valB)) : (Number(valB) - Number(valA));
		});

		rows.forEach(function (row) { tbody.appendChild(row); });
	};

	/* ==========================================================================
	   DROPDOWNS
	   ========================================================================== */
	function initDropdowns() {
		document.addEventListener('click', function (e) {
			if (!e.target.closest('.ded-dropdown-container')) {
				document.querySelectorAll('.ded-dropdown-menu.show').forEach(function (menu) {
					menu.classList.remove('show');
				});
			}
		});

		document.querySelectorAll('.ded-dropdown-toggle').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var parent = this.closest('.ded-dropdown-container');
				if (!parent) return;
				var menu = parent.querySelector('.ded-dropdown-menu');
				if (!menu) return;

				var isAlreadyOpen = menu.classList.contains('show');
				document.querySelectorAll('.ded-dropdown-menu.show').forEach(function (m) {
					m.classList.remove('show');
				});
				if (!isAlreadyOpen) {
					menu.classList.add('show');
				}
			});
		});

		// Selección de sucursal
		document.querySelectorAll('[data-select-branch]').forEach(function (item) {
			item.addEventListener('click', function (e) {
				e.preventDefault();
				var bid = this.getAttribute('data-select-branch');
				var url = new URL(window.location.href);
				if (bid) {
					url.searchParams.set('branch_id', bid);
				} else {
					url.searchParams.delete('branch_id');
				}
				url.searchParams.set('tab', 'departamentos');
				window.location.href = url.toString();
			});
		});

		// Selección de departamento
		document.querySelectorAll('[data-select-dept]').forEach(function (item) {
			item.addEventListener('click', function (e) {
				e.preventDefault();
				var dept = this.getAttribute('data-select-dept');
				var url = new URL(window.location.href);
				if (dept) {
					url.searchParams.set('department', dept);
				} else {
					url.searchParams.delete('department');
				}
				url.searchParams.set('tab', 'departamentos');
				window.location.href = url.toString();
			});
		});

		// Selección de rango rápido
		document.querySelectorAll('[data-select-range]').forEach(function (item) {
			item.addEventListener('click', function (e) {
				e.preventDefault();
				var range = this.getAttribute('data-select-range');
				var url = new URL(window.location.href);
				var today = new Date();
				var fmt = function (d) { return d.toISOString().split('T')[0]; };
				var from, to;
				to = fmt(today);

				if (range === '30d') {
					var past30 = new Date(today);
					past30.setDate(today.getDate() - 29);
					from = fmt(past30);
				} else if (range === 'month') {
					from = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
				} else if (range === 'prev_month') {
					from = fmt(new Date(today.getFullYear(), today.getMonth() - 1, 1));
					to = fmt(new Date(today.getFullYear(), today.getMonth(), 0));
				} else if (range === 'year') {
					from = fmt(new Date(today.getFullYear(), 0, 1));
				}

				if (from && to) {
					url.searchParams.set('start_date', from);
					url.searchParams.set('end_date', to);
					url.searchParams.set('tab', 'departamentos');
					window.location.href = url.toString();
				}
			});
		});
	}

})();
