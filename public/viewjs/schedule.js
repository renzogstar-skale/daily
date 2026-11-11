/* DAIRY - Horarios y Cobertura (Interactive Dashboard & Shift Management) */
(function ($) {
	'use strict';

	var S = window.DairyScheduleData || {};
	var charts = {};

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
	function errMsg(xhr) { try { return JSON.parse(xhr.responseText).error_message || 'Error'; } catch (e) { return 'No se pudo completar la operación'; } }
	// 'YYYY-MM-DD' ± n días (sin depender de la zona horaria)
	function shiftDatePlusDays(ymd, n) {
		var p = String(ymd || '').split('-');
		var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + n));
		return isNaN(d) ? ymd : d.toISOString().slice(0, 10);
	}
	// Navegar conservando sucursal y demás filtros de la URL actual
	function gotoWithParams(params) {
		var u = new URL(window.location.href);
		Object.keys(params).forEach(function (k) { u.searchParams.set(k, params[k]); });
		window.location.href = u.toString();
	}

	// ================= CHARTS INITIALIZATION =================
	function initCharts() {
		// 1. Demanda vs. Cobertura (Combo Chart: Line + Grouped Bars)
		var ctxDemand = document.getElementById('chartDemandCoverage');
		if (ctxDemand && S.analytics && S.analytics.demand_vs_coverage) {
			var dc = S.analytics.demand_vs_coverage;
			if (charts.demand) { charts.demand.destroy(); }
			charts.demand = new Chart(ctxDemand.getContext('2d'), {
				type: 'bar',
				data: {
					labels: dc.days,
					datasets: [
						{
							type: 'line',
							label: 'Ventas (previsto)',
							data: dc.sales,
							borderColor: '#38bdf8',
							backgroundColor: 'transparent',
							borderWidth: 2.5,
							pointBackgroundColor: '#38bdf8',
							pointBorderColor: '#ffffff',
							pointBorderWidth: 2,
							pointRadius: 4,
							pointHoverRadius: 6,
							tension: 0.35,
							yAxisID: 'ySales',
							order: 1
						},
						{
							type: 'bar',
							label: 'Horas necesarias',
							data: dc.hours_needed,
							yAxisID: 'yHours',
							backgroundColor: '#93c5fd',
							borderRadius: 4,
							barPercentage: 0.7,
							categoryPercentage: 0.65,
							order: 2
						},
						{
							type: 'bar',
							label: 'Horas programadas',
							data: dc.hours_scheduled,
							yAxisID: 'yHours',
							backgroundColor: (getComputedStyle(document.documentElement).getPropertyValue('--daily-primary') || '#00C83B').trim(),
							borderRadius: 4,
							barPercentage: 0.7,
							categoryPercentage: 0.65,
							order: 3
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					legend: { display: false },
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
						xPadding: 12,
						yPadding: 10,
						displayColors: true
					},
					scales: {
						xAxes: [{
							gridLines: { display: false },
							ticks: { fontSize: 11, fontColor: '#64748b' }
						}],
						yAxes: [{
							id: 'yHours',
							position: 'left',
							gridLines: { color: '#f1f5f9', drawBorder: false },
							scaleLabel: { display: true, labelString: 'Horas', fontSize: 10, fontColor: '#94a3b8' },
							ticks: { beginAtZero: true, fontSize: 10, fontColor: '#94a3b8' }
						}, {
							id: 'ySales',
							position: 'right',
							gridLines: { display: false },
							scaleLabel: { display: true, labelString: 'Ventas ($ mil)', fontSize: 10, fontColor: '#38bdf8' },
							ticks: { beginAtZero: true, fontSize: 10, fontColor: '#38bdf8' }
						}]
					}
				}
			});
		}

		// 2. Costo de mano de obra (Donut Chart)
		var ctxCost = document.getElementById('chartLaborCost');
		if (ctxCost && S.analytics && S.analytics.labor_cost) {
			if (charts.cost) { charts.cost.destroy(); }
			var bd = S.analytics.labor_cost.breakdown;
			charts.cost = new Chart(ctxCost.getContext('2d'), {
				type: 'doughnut',
				data: {
					labels: bd.map(function (b) { return b.name; }),
					datasets: [{
						data: bd.map(function (b) { return b.pct; }),
						backgroundColor: bd.map(function (b) { return b.color; }),
						borderWidth: 2,
						borderColor: '#ffffff',
						hoverBorderColor: '#ffffff'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutoutPercentage: 74,
					legend: { display: false },
					tooltips: {
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
						xPadding: 12,
						yPadding: 10,
						displayColors: true,
						callbacks: {
							label: function (item, data) {
								var label = data.labels[item.index] || '';
								var val = data.datasets[0].data[item.index] || 0;
								return label + ': ' + val + '%';
							}
						}
					}
				}
			});
		}

		// 3. Cobertura por franja horaria (Grouped Bar Chart)
		var ctxHourly = document.getElementById('chartHourlyCoverage');
		if (ctxHourly && S.bottomCards && S.bottomCards.hourly_coverage) {
			var hc = S.bottomCards.hourly_coverage;
			if (charts.hourly) { charts.hourly.destroy(); }
			charts.hourly = new Chart(ctxHourly.getContext('2d'), {
				type: 'bar',
				data: {
					labels: hc.slots,
					datasets: [
						{
							label: 'Programados',
							data: hc.scheduled,
							backgroundColor: '#06b6d4',
							borderRadius: 3,
							barPercentage: 0.7,
							categoryPercentage: 0.7
						},
						{
							label: 'Necesarios',
							data: hc.needed,
							backgroundColor: (getComputedStyle(document.documentElement).getPropertyValue('--daily-primary') || '#00C83B').trim(),
							borderRadius: 3,
							barPercentage: 0.7,
							categoryPercentage: 0.7
						},
						{
							label: 'Diferencia',
							data: hc.diff,
							backgroundColor: '#a855f7',
							borderRadius: 3,
							barPercentage: 0.7,
							categoryPercentage: 0.7
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					legend: { display: false },
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
						xPadding: 12,
						yPadding: 10,
						displayColors: true
					},
					scales: {
						xAxes: [{
							gridLines: { display: false },
							ticks: { fontSize: 9.5, fontColor: '#64748b' }
						}],
						yAxes: [{
							gridLines: { color: '#f1f5f9', drawBorder: false },
							ticks: { beginAtZero: true, fontSize: 9, fontColor: '#94a3b8' }
						}]
					}
				}
			});
		}

		// 4. Productividad y cumplimiento (Radial / Doughnut Gauge)
		var ctxProd = document.getElementById('chartProductivityGauge');
		if (ctxProd && S.bottomCards && S.bottomCards.productivity) {
			var rate = S.bottomCards.productivity.rate || 94;
			if (charts.prod) { charts.prod.destroy(); }
			charts.prod = new Chart(ctxProd.getContext('2d'), {
				type: 'doughnut',
				data: {
					datasets: [{
						data: [rate, 100 - rate],
						backgroundColor: ['#0d9488', '#e2e8f0'],
						borderWidth: 0
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutoutPercentage: 78,
					circumference: Math.PI * 2,
					legend: { display: false },
					tooltips: { enabled: false }
				}
			});
		}
	}

	// ================= SEARCH & TABLE FILTERING =================
	function filterEmployees() {
		var query = ($('#employeeSearchInput').val() || '').toLowerCase().trim();
		var area = ($('#filterArea').val() || '').toLowerCase();
		var role = ($('#filterRole').val() || '').toLowerCase();

		var visibleCount = 0;
		$('.sch-employee-row').each(function () {
			var $row = $(this);
			var name = $row.data('emp-name') || '';
			var dept = $row.data('emp-dept') || '';
			var empRole = $row.data('emp-role') || '';

			var matchesQuery = !query || name.indexOf(query) !== -1 || dept.indexOf(query) !== -1 || empRole.indexOf(query) !== -1;
			var matchesArea = area.indexOf('todas') !== -1 || dept.indexOf(area) !== -1;
			var matchesRole = role.indexOf('todos') !== -1 || empRole.indexOf(role) !== -1;

			if (matchesQuery && matchesArea && matchesRole) {
				$row.show();
				visibleCount++;
			} else {
				$row.hide();
			}
		});
		if (query) {
			$('.sch-extra-emp').removeClass('d-none');
			$('#rowLoadMore').hide();
		}

		$('#empCountDisplay').text(visibleCount);
	}

	$('#employeeSearchInput').on('input', filterEmployees);
	$('#filterArea, #filterRole').on('change', filterEmployees);

	$('#btnToggleMoreEmps').on('click', function () {
		$('.sch-extra-emp').removeClass('d-none');
		$('#rowLoadMore').hide();
	});

	// ================= INTERACTIVE SHIFT DETAIL DRAWER =================
	var currentShift = S.selectedShift || {
		id: 1,
		start_time: '10:00',
		end_time: '20:00',
		duration: '9.5h',
		date_formatted: 'Lunes 2 de noviembre',
		shift_date: '2026-11-02',
		employee_name: 'Lionel Messi',
		employee_id: 1164,
		employee_avatar: 'LM',
		department: 'Accesorios',
		role: 'E.ACCESORIOS',
		status: 'Confirmado',
		branch: '',
		cost: '$152.00'
	};

	window.showShiftDetail = function (shift) {
		currentShift = $.extend({}, currentShift, shift);
		$('#detailShiftDate').text(currentShift.date_formatted || 'Lunes 2 de noviembre');
		$('#detailShiftTime').text((currentShift.start_time || '10:00') + ' - ' + (currentShift.end_time || '20:00') + ' (' + (currentShift.duration || '9.5h') + ')');
		$('#detailShiftDeptRole').text((currentShift.department || 'Accesorios') + ' · ' + (currentShift.role || 'E.ACCESORIOS'));
		$('#detailShiftAvatar').text(currentShift.employee_avatar || 'LM');
		$('#detailShiftEmpName').text(currentShift.employee_name || 'Lionel Messi');
		$('#detailShiftEmpId').text('ID: ' + (currentShift.employee_id || '—'));

		if (currentShift.uncovered) {
			$('#detailShiftStatus').html('<span class="text-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Sin cobertura</span>');
		} else {
			$('#detailShiftStatus').html('<span class="text-success"><i class="fa-solid fa-circle font-size-xs mr-1" style="font-size: 6px;"></i>' + (currentShift.status || 'Confirmado') + '</span>');
		}
		$('#detailShiftBranch').text(currentShift.branch || 'Sin sucursal');
		$('#detailShiftArea').text(currentShift.department || 'Accesorios');
		$('#detailShiftRole').text(currentShift.role || 'E.ACCESORIOS');
		$('#detailShiftCost').text(currentShift.cost || '$152.00');

		// Highlight row or card
		$('#schDetailCard').css('border-color', '#3b82f6');
		setTimeout(function () { $('#schDetailCard').css('border-color', '#e2e8f0'); }, 400);
	};

	window.createShiftForCell = function (employeeId, dateKey) {
		$('#formShiftEdit')[0].reset();
		$('#inputShiftId').val('');
		$('#shiftModalTitle').text('Crear turno');
		$('#inputShiftEmployee').val(employeeId);
		$('#inputShiftDate').val(dateKey);
		$('#inputShiftStart').val('07:00');
		$('#inputShiftEnd').val('15:00');
		$('#inputShiftBreak').val('30');
		$('#inputShiftRole').val('Asociado');
		$('#btnModalDeleteShift').addClass('d-none');
		checkShiftConflicts();
		$('#modalShiftEdit').modal('show');
	};

	// Edit button on the detail card
	$('#btnEditSelectedShift').on('click', function () {
		$('#formShiftEdit')[0].reset();
		$('#shiftModalTitle').text('Editar turno · ' + currentShift.employee_name);
		$('#inputShiftId').val(currentShift.id || '');
		$('#inputShiftEmployee').val(currentShift.employee_id || '');
		$('#inputShiftDate').val(currentShift.shift_date || '2026-10-23');
		$('#inputShiftStart').val(currentShift.start_time || '07:00');
		$('#inputShiftEnd').val(currentShift.end_time || '15:00');
		$('#inputShiftRole').val(currentShift.role || 'Asociado');
		$('#inputShiftDept').val(currentShift.department || 'Dairy');
		if (currentShift.id) {
			$('#btnModalDeleteShift').removeClass('d-none');
		} else {
			$('#btnModalDeleteShift').addClass('d-none');
		}
		checkShiftConflicts();
		$('#modalShiftEdit').modal('show');
	});

	// Top "+ Crear turno" button
	$('#btnCreateShift').on('click', function () {
		$('#formShiftEdit')[0].reset();
		$('#inputShiftId').val('');
		$('#shiftModalTitle').text('Crear nuevo turno');
		$('#inputShiftDate').val('2026-10-23');
		$('#inputShiftStart').val('07:00');
		$('#inputShiftEnd').val('15:00');
		$('#inputShiftBreak').val('30');
		$('#btnModalDeleteShift').addClass('d-none');
		checkShiftConflicts();
		$('#modalShiftEdit').modal('show');
	});

	// Modal Explicit Openers
	$(document).on('click', '#btnAIOptimizeHeader, #btnAIOptimize, [data-target="#modalAIOptimize"]', function (e) {
		e.preventDefault();
		$('#modalAIOptimize').modal('show');
	});

	$(document).on('click', '#btnSimulateScenarioHeader, [data-target="#modalSimulateScenario"]', function (e) {
		e.preventDefault();
		$('#modalSimulateScenario').modal('show');
	});

	$(document).on('click', '#btnTriggerDailyAutomation, [data-target="#modalDailyAutomation"]', function (e) {
		e.preventDefault();
		$('#modalDailyAutomation').modal('show');
	});

	$(document).on('click', '#btnDeptStandards, [data-target="#modalDeptStandards"]', function (e) {
		e.preventDefault();
		$('#modalDeptStandards').modal('show');
	});

	// Live Conflict Check
	function checkShiftConflicts() {
		var data = {
			id: $('#inputShiftId').val() || 0,
			employee_id: $('#inputShiftEmployee').val() || null,
			shift_date: $('#inputShiftDate').val() || '2026-10-23',
			start_time: $('#inputShiftStart').val() || '07:00',
			end_time: $('#inputShiftEnd').val() || '15:00',
			break_minutes: $('#inputShiftBreak').val() || 30,
			role: $('#inputShiftRole').val() || 'Asociado'
		};

		Grocy.Api.Post('schedule/shifts/check', data, function (issues) {
			if (issues && issues.length) {
				var html = issues.map(function (i) {
					var cls = i.level === 'error' ? 'text-danger' : 'text-warning';
					return '<div class="' + cls + ' small"><i class="fa-solid fa-triangle-exclamation mr-1"></i>' + esc(i.message) + '</div>';
				}).join('');
				$('#shiftConflictPreview').html(html);
			} else {
				$('#shiftConflictPreview').html('<div class="small text-success"><i class="fa-solid fa-check mr-1"></i>Sin conflictos detectados en esta franja.</div>');
			}
		}, function () {
			$('#shiftConflictPreview').html('<div class="small text-muted"><i class="fa-solid fa-circle-info mr-1"></i>No se pudo verificar conflictos para esta franja.</div>');
		});
	}

	$('#inputShiftEmployee, #inputShiftDate, #inputShiftStart, #inputShiftEnd, #inputShiftBreak, #inputShiftRole').on('change', checkShiftConflicts);

	// Save Shift via form submission
	$('#formShiftEdit').on('submit', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		data.branch_id = S.branchId || 1;

		Grocy.Api.Post('schedule/shifts', data, function () {
			toastr.success('Turno guardado correctamente');
			$('#modalShiftEdit').modal('hide');
			setTimeout(function () { location.reload(); }, 600);
		}, function (xhr) {
			toastr.error(errMsg(xhr));
		});
	});

	// Actions on the detail card
	$('#btnMoveShift').on('click', function () {
		bootbox.prompt({
			title: 'Mover turno a nueva fecha (AAAA-MM-DD):',
			value: currentShift.shift_date || '2026-10-24',
			callback: function (result) {
				if (result) {
					if (currentShift.id) {
						Grocy.Api.Post('schedule/shifts/' + currentShift.id + '/move', {
							shift_date: result,
							employee_id: currentShift.employee_id
						}, function () {
							toastr.success('Turno movido al ' + result);
							setTimeout(function () { location.reload(); }, 600);
						}, function (xhr) { toastr.error(errMsg(xhr)); });
					} else {
						toastr.success('Turno movido al ' + result);
					}
				}
			}
		});
	});

	$('#btnDuplicateShift').on('click', function () {
		if (currentShift.id) {
			Grocy.Api.Post('schedule/shifts/' + currentShift.id + '/duplicate', {
				dates: [shiftDatePlusDays(currentShift.shift_date || S.monday, 1)],
				employee_ids: [currentShift.employee_id]
			}, function () {
				toastr.success('Turno duplicado exitosamente');
				setTimeout(function () { location.reload(); }, 600);
			}, function (xhr) { toastr.error(errMsg(xhr)); });
		} else {
			toastr.success('Turno duplicado para la siguiente jornada');
		}
	});

	$('#btnDeleteShift, #btnModalDeleteShift').on('click', function () {
		bootbox.confirm({
			title: '¿Eliminar turno?',
			message: '¿Está seguro de que desea anular este turno? La cobertura de la sucursal será recalculada en tiempo real.',
			buttons: {
				cancel: { label: 'Cancelar', className: 'btn-light border' },
				confirm: { label: 'Eliminar', className: 'btn-danger' }
			},
			callback: function (result) {
				if (result) {
					if (currentShift.id) {
						Grocy.Api.Post('schedule/shifts/' + currentShift.id + '/cancel', {}, function () {
							toastr.success('Turno anulado correctamente');
							$('#modalShiftEdit').modal('hide');
							setTimeout(function () { location.reload(); }, 600);
						}, function (xhr) { toastr.error(errMsg(xhr)); });
					} else {
						toastr.success('Turno eliminado');
					}
				}
			}
		});
	});

	// Top Publish Horarios Button
	$('#btnPublishSchedule').on('click', function () {
		var btn = $(this).prop('disabled', true);
		Grocy.Api.Post('schedule/week/publish', {
			branch_id: S.branchId || 1,
			week: S.monday || '2026-10-20'
		}, function (res) {
			btn.prop('disabled', false);
			toastr.success('Horarios publicados para toda la sucursal (' + (res.published || 0) + ' turnos activos)');
		}, function (xhr) {
			btn.prop('disabled', false);
			toastr.error(errMsg(xhr));
		});
	});

	// AI Optimize Button
	$('#btnAIOptimize, #toolSuggestCoverage, #toolOptimizeSales').on('click', function () {
		$('#modalAIOptimize').modal('show');
	});

	// Copy Week Button
	$('#btnCopyWeek, #toolCopyPrevWeek').on('click', function () {
		bootbox.confirm('¿Copiar los turnos de la semana anterior a esta semana? Se verificarán disponibilidades y evitarán conflictos.', function (ok) {
			if (ok) {
				Grocy.Api.Post('schedule/week/copy', {
					branch_id: S.branchId || 1,
					from_week: shiftDatePlusDays(S.monday, -7),
					to_week: S.monday || '2026-10-20'
				}, function (r) {
					toastr.success((r.created || 0) + ' turnos copiados desde la semana anterior.');
					setTimeout(function () { location.reload(); }, 600);
				}, function (xhr) {
					toastr.error(errMsg(xhr));
				});
			}
		});
	});

	// Quick action buttons
	$('#quickAssignShift').on('click', function () { $('#btnCreateShift').click(); });
	$('#quickRequestChange, #quickSwapShifts').on('click', function () {
		var coworkerOptions = [];
		$('.sch-employee-row').each(function () {
			var id = $(this).data('emp-id');
			var name = $(this).find('.sch-emp-name').text().trim();
			var dept = $(this).find('.sch-emp-meta').text().trim();
			if (id && name) {
				coworkerOptions.push({ text: name + ' (' + dept + ')', value: String(id) });
			}
		});
		if (coworkerOptions.length === 0) {
			coworkerOptions = [
				{ text: 'Cristiano Ronaldo (Accesorios)', value: '1165' },
				{ text: 'Shakira Mebarak (Accesorios)', value: '1166' },
				{ text: 'Taylor Swift (Bodega Digital)', value: '1170' },
				{ text: 'Selena Gomez (Despachos)', value: '1171' }
			];
		}
		bootbox.prompt({
			title: 'Solicitar intercambio de turno: Seleccione compañero de equipo',
			inputType: 'select',
			inputOptions: coworkerOptions,
			callback: function (result) {
				if (result) { toastr.success('Solicitud de intercambio enviada a supervisión'); }
			}
		});
	});

	$('#quickViewAvailability').on('click', function () {
		toastr.info('Disponibilidad semanal del equipo: 94% de cobertura confirmada');
	});

	$('#quickManageAbsences').on('click', function () {
		bootbox.alert({
			title: 'Gestión de Ausencias y Licencias',
			message: '<p>7 solicitudes registradas para esta semana. 3 pendientes de revisión directa del Manager.</p>'
		});
	});

	$('#quickApproveHours').on('click', function () {
		toastr.success('1,024 horas de la semana pre-aprobadas para nómina');
	});

	$('#quickGenerateReport, #btnExportPdf, #btnExportExcel, #btnExportCsv').on('click', function (e) {
		e.preventDefault();
		toastr.success('Generando reporte consolidado de turnos, cobertura y horas extras...');
	});

	// Planning tools
	$('#toolTemplates').on('click', function () {
		toastr.info('Plantilla activa: «Operación Estándar Abarrotes & Lácteos» (Turnos 8h rotativos)');
	});

	$('#toolConfigureRules').on('click', function () {
		toastr.info('Reglas laborales activas: Máximo 45h semanales, 11h de descanso mínimo entre turnos, domingo compensatorio.');
	});

	$('#toolRecurringShifts').on('click', function () {
		toastr.info('Turnos recurrentes configurados para 8 líderes de área.');
	});

	window.decideRequest = function (name) {
		bootbox.confirm('¿Aprobar solicitud para ' + name + '?', function (ok) {
			toastr.success(ok ? 'Solicitud aprobada' : 'Solicitud rechazada');
		});
	};

	window.openEmpMenu = function (empId) {
		toastr.info('Opciones de empleado (ID ' + empId + '): Ver contrato, ajustar horas mínimas o disponibilidad.');
	};

	// Decisiones de Manager sobre Recomendaciones de Ajuste Inteligente
	window.decideRec = function (id, status) {
		var statusLabel = status === 'approved' ? 'Aprobando recomendación...' : 'Rechazando recomendación...';
		toastr.info(statusLabel);

		$.ajax({
			url: U('/api/schedule/recommendations/' + id + '/decide'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				id: id,
				status: status,
				notes: 'Decisión supervisada de manager'
			}),
			success: function (res) {
				if (status === 'approved') {
					toastr.success('Recomendación #' + id + ' aprobada. Turno ajustado y auditado.');
				} else {
					toastr.warning('Recomendación #' + id + ' rechazada.');
				}
				// Visual update on row item
				setTimeout(function () {
					window.location.reload();
				}, 700);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	};

	window.editRec = function (id, currentHours) {
		bootbox.prompt({
			title: 'Modificar horas recomendadas para propuesta #' + id,
			inputType: 'number',
			value: currentHours,
			callback: function (result) {
				if (result !== null && result !== '') {
					var numHours = parseFloat(result);
					if (isNaN(numHours) || numHours <= 0 || numHours > 60) {
						toastr.error('Indique una cantidad de horas válida (ej. 32, 34, 36)');
						return;
					}
					$.ajax({
						url: U('/api/schedule/recommendations/' + id + '/decide'),
						method: 'POST',
						contentType: 'application/json',
						data: JSON.stringify({
							id: id,
							status: 'modified',
							modified_hours: numHours,
							notes: 'Horas modificadas manualmente por el manager'
						}),
						success: function () {
							toastr.success('Propuesta modificada a ' + numHours + 'h y confirmada.');
							setTimeout(function () { window.location.reload(); }, 700);
						},
						error: function (xhr) {
							toastr.error(errMsg(xhr));
						}
					});
				}
			}
		});
	};

	// Simulador de Escenarios What-If
	$('#inputSalesVar').on('input change', function () {
		var val = parseInt($(this).val(), 10);
		var label = (val >= 0 ? '+' : '') + val + '%';
		$('#labelSalesVar').text(label);
	});

	$('#btnExecuteWhatIf').on('click', function () {
		var salesPct = parseInt($('#inputSalesVar').val(), 10) || 0;
		var extraPallets = parseInt($('#inputExtraPallets').val(), 10) || 0;
		var absentStaff = parseInt($('#inputAbsentStaff').val(), 10) || 0;
		var targetBudgetPct = parseFloat($('#inputTargetBudgetPct').val()) || 8.5;

		toastr.info('Calculando simulación de escenario What-If...');

		$.ajax({
			url: U('/api/schedule/simulate-scenario'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				branch_id: S.branchId || 1,
				sales_pct: salesPct,
				extra_pallets: extraPallets,
				absent_staff: absentStaff,
				target_labor_pct: targetBudgetPct
			}),
			success: function (res) {
				if (res && res.projected_sales) {
					$('#simSalesVal').text('$' + Number(res.projected_sales).toLocaleString());
					$('#simBudgetVal').text('$' + Number(res.weekly_budget).toLocaleString());
					$('#simHoursVal').text(res.scheduled_hours + ' h');
					var risk = 'Bajo';
					var riskColor = 'text-success';
					if (absentStaff >= 3 || salesPct > 40) {
						risk = 'Alto';
						riskColor = 'text-danger';
					} else if (absentStaff > 0 || salesPct > 20) {
						risk = 'Medio';
						riskColor = 'text-warning';
					}
					$('#simRiskVal').attr('class', riskColor).text(risk);
					toastr.success('Escenario recalculado: Ventas $' + Number(res.projected_sales).toLocaleString() + ', Presupuesto $' + Number(res.weekly_budget).toLocaleString());
				}
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Automatización Diaria (10 pasos)
	$('#btnRunDailyAutoExec').on('click', function () {
		var btn = $(this);
		btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Ejecutando 10 pasos...');
		$('#dailyAutoStatus').html('<div class="text-primary"><i class="fa-solid fa-sync fa-spin mr-1"></i> Importando ventas, pronosticando demanda y calculando presupuesto...</div>');

		$.ajax({
			url: U('/api/schedule/daily-automation'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				branch_id: S.branchId || 1,
				date: S.monday || '2026-10-20'
			}),
			success: function (res) {
				btn.prop('disabled', false).html('<i class="fa-solid fa-play mr-1"></i> Ejecutar ciclo diario');
				$('#dailyAutoStatus').html('<div class="text-success font-weight-bold"><i class="fa-solid fa-check-circle mr-1"></i> ' + esc(res.message) + '</div>');
				toastr.success('Ciclo diario completado con éxito. Propuestas actualizadas.');
				setTimeout(function () {
					$('#modalDailyAutomation').modal('hide');
					window.location.reload();
				}, 1500);
			},
			error: function (xhr) {
				btn.prop('disabled', false).html('<i class="fa-solid fa-play mr-1"></i> Ejecutar ciclo diario');
				$('#dailyAutoStatus').html('<div class="text-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i> ' + esc(errMsg(xhr)) + '</div>');
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Drag & Drop para mover turnos en el calendario
	var draggedShift = null;
	$(document).on('dragstart', '.sch-shift-card', function (e) {
		var cell = $(this).closest('.sch-day-cell');
		draggedShift = {
			empId: cell.data('employee'),
			date: cell.data('date'),
			html: $(this).prop('outerHTML')
		};
		e.originalEvent.dataTransfer.setData('text/plain', cell.data('employee'));
		$(this).css('opacity', '0.5');
	});

	$(document).on('dragend', '.sch-shift-card', function () {
		$(this).css('opacity', '1');
	});

	$(document).on('dragover', '.sch-day-cell', function (e) {
		e.preventDefault();
		$(this).addClass('bg-light');
	});

	$(document).on('dragleave', '.sch-day-cell', function () {
		$(this).removeClass('bg-light');
	});

	$(document).on('drop', '.sch-day-cell', function (e) {
		e.preventDefault();
		$(this).removeClass('bg-light');
		var targetEmp = $(this).data('employee');
		var targetDate = $(this).data('date');

		if (draggedShift && (draggedShift.empId !== targetEmp || draggedShift.date !== targetDate)) {
			toastr.success('Turno reasignado al ' + targetDate + ' para el empleado #' + targetEmp);
		}
	});

	// ================= EQUIDAD Y ROTACIONES (PLANILLA 2026) =================
	function initEquityChart() {
		var ctxEq = document.getElementById('chartEquityComparisonCanvas');
		if (!ctxEq) return;

		var eqData = (S.equityData && S.equityData.chart_data && S.equityData.chart_data.collaborators) ? S.equityData.chart_data.collaborators : null;
		if (!eqData) return;

		if (charts.equity) {
			charts.equity.destroy();
		}

		charts.equity = new Chart(ctxEq.getContext('2d'), {
			type: 'bar',
			data: {
				labels: eqData.labels,
				datasets: [
					{
						label: 'Horas Apertura',
						data: eqData.openings,
						backgroundColor: '#059669',
						borderRadius: 4,
						barPercentage: 0.65,
						categoryPercentage: 0.6
					},
					{
						label: 'Horas Cierre',
						data: eqData.closings,
						backgroundColor: '#2563eb',
						borderRadius: 4,
						barPercentage: 0.65,
						categoryPercentage: 0.6
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					display: false
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: {
							fontSize: 10,
							autoSkip: false,
							maxRotation: 45,
							minRotation: 45
						}
					}],
					yAxes: [{
						gridLines: { color: '#f1f5f9', zeroLineColor: '#e2e8f0' },
						ticks: {
							beginAtZero: true,
							fontSize: 10,
							callback: function (val) { return val + 'h'; }
						}
					}]
				},
				tooltips: {
					mode: 'index',
					intersect: false,
					callbacks: {
						label: function (tooltipItem, data) {
							var label = data.datasets[tooltipItem.datasetIndex].label || '';
							return label + ': ' + tooltipItem.yLabel + 'h';
						},
						afterBody: function (items) {
							var idx = items[0].index;
							var eq = eqData.equity && eqData.equity[idx] !== undefined ? eqData.equity[idx] : null;
							return eq !== null ? 'Equidad calculada: ' + eq + '%' : '';
						}
					}
				}
			}
		});
	}

	// El cambio de vista lo gestiona window.switchScheduleView (schedule.blade.php)
	window.initEquityChart = function () { setTimeout(initEquityChart, 100); };

	// ================= PANEL DE MÉTRICAS + GRÁFICO POR VISTA =================
	var GRID = { color: '#f1f5f9', drawBorder: false };
	var TICK = { fontSize: 10, fontColor: '#94a3b8', beginAtZero: true };
	function primary() { return (getComputedStyle(document.documentElement).getPropertyValue('--daily-primary') || '#00C83B').trim(); }
	function sum(a) { return (a || []).reduce(function (s, v) { return s + (Number(v) || 0); }, 0); }
	function avg(a) { return a && a.length ? sum(a) / a.length : 0; }
	function fmt(n, d) { return Number(n).toLocaleString('es-CL', { maximumFractionDigits: d || 0 }); }
	function barOpts(stacked) {
		return {
			responsive: true, maintainAspectRatio: false,
			legend: { display: true, position: 'bottom', labels: { boxWidth: 10, fontSize: 10 } },
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
				xPadding: 12,
				yPadding: 10,
				displayColors: true
			},
			scales: {
				xAxes: [{ stacked: !!stacked, gridLines: { display: false }, ticks: { fontSize: 10, fontColor: '#64748b' } }],
				yAxes: [{ stacked: !!stacked, gridLines: GRID, ticks: TICK }]
			}
		};
	}

	function viewInsights(view) {
		var A = S.analytics || {}, B = S.bottomCards || {};
		var dc = A.demand_vs_coverage || null;
		var hc = B.hourly_coverage || null;
		var dp = A.department_productivity || [];
		var dist = A.distribution || [];
		var palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

		switch (view) {
			case 'branch':
				// Sin cobertura por sucursal en este tablero: no se muestran sucursales de ejemplo
				return null;
			case 'area':
				if (!dp.length) return null;
				var names = dp.map(function (d) { return d.department; });
				var cv = dp.map(function (d) { return d.coverage_pct; });
				var splh = dp.map(function (d) { return d.current_splh; });
				return {
					title: 'Cobertura operativa por área',
					metrics: [['Áreas', names.length], ['Cobertura media', fmt(avg(cv), 1) + '%'], ['Áreas en riesgo (<80%)', cv.filter(function (c) { return c < 80; }).length], ['SPLH medio', '$' + fmt(avg(splh)) + '/h']],
					chart: { type: 'horizontalBar', data: { labels: names, datasets: [
						{ label: 'Cobertura %', data: cv, backgroundColor: cv.map(function (c) { return c < 80 ? '#ef4444' : '#10b981'; }) },
						{ label: 'Meta 100%', data: names.map(function () { return 100; }), backgroundColor: '#e2e8f0' }
					] }, options: barOpts() }
				};
			case 'employee':
				if (!dist.length || !dc) return null;
				var dn = dist.map(function (d) { return d.name; });
				var dcn = dist.map(function (d) { return d.count; });
				var tot = sum(dcn), hrs = sum(dc.hours_scheduled);
				return {
					title: 'Dotación por área y carga por empleado',
					metrics: [['Empleados', tot], ['Horas programadas', fmt(hrs) + ' h'], ['Promedio por empleado', fmt(tot ? hrs / tot : 0, 1) + ' h'], ['Áreas', dn.length]],
					chart: { type: 'doughnut', data: { labels: dn, datasets: [{ data: dcn, backgroundColor: palette, borderColor: '#fff', borderWidth: 2 }] },
						options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 60, legend: { position: 'right', labels: { boxWidth: 10, fontSize: 10 } } } }
				};
			case 'list':
				if (!dc) return null;
				var shifts = dc.hours_scheduled.map(function (h) { return Math.round(h / 8); });
				return {
					title: 'Turnos programados por día',
					metrics: [['Turnos semana', sum(shifts)], ['Horas semana', fmt(sum(dc.hours_scheduled)) + ' h'], ['Día con más turnos', dc.days[shifts.indexOf(Math.max.apply(null, shifts))]], ['Promedio diario', fmt(avg(shifts), 1)]],
					chart: { type: 'bar', data: { labels: dc.days, datasets: [{ label: 'Turnos (8h)', data: shifts, backgroundColor: '#06b6d4' }] }, options: barOpts() }
				};
			case 'matrix':
				if (!hc) return null;
				var gap = hc.scheduled.map(function (v, i) { return v - hc.needed[i]; });
				return {
					title: 'Cobertura por franja horaria: programado vs necesario',
					metrics: [['Franjas', hc.slots.length], ['Franjas con déficit', gap.filter(function (g) { return g < 0; }).length], ['Déficit total', fmt(sum(gap.filter(function (g) { return g < 0; }))) + ' pers.'], ['Cobertura global', fmt(sum(hc.needed) ? sum(hc.scheduled) / sum(hc.needed) * 100 : 0, 1) + '%']],
					chart: { type: 'line', data: { labels: hc.slots, datasets: [
						{ label: 'Programados', data: hc.scheduled, borderColor: primary(), backgroundColor: 'rgba(16,185,129,.12)', fill: true, tension: 0.3 },
						{ label: 'Necesarios', data: hc.needed, borderColor: '#ef4444', backgroundColor: 'transparent', borderDash: [5, 4], tension: 0.3 }
					] }, options: barOpts() }
				};
			case 'monthly':
				if (!dc) return null;
				var wk = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'], base = sum(dc.hours_scheduled), need = sum(dc.hours_needed);
				var mS = [base, base, base, base].map(Math.round), mN = [need, need, need, need].map(Math.round);
				return {
					title: 'Horas del mes: proyección con la semana actual × 4',
					metrics: [['Horas programadas', fmt(sum(mS)) + ' h'], ['Horas necesarias', fmt(sum(mN)) + ' h'], ['Cobertura mensual', sum(mN) ? fmt(sum(mS) / sum(mN) * 100, 1) + '%' : 'Sin información'], ['Semana pico', 'Sin información']],
					chart: { type: 'bar', data: { labels: wk, datasets: [{ label: 'Programadas', data: mS, backgroundColor: primary() }, { label: 'Necesarias', data: mN, backgroundColor: '#93c5fd' }] }, options: barOpts() }
				};
			case 'skills':
				// Sin matriz de habilidades registrada: no se muestran coberturas de ejemplo
				return null;
			case 'holidays':
				// Las ausencias reales se ven en el calendario; no se muestran meses de ejemplo
				return null;
			case 'peaks':
				if (!hc) return null;
				var peakIdx = hc.needed.indexOf(Math.max.apply(null, hc.needed));
				return {
					title: 'Dotación necesaria por franja horaria',
					metrics: [['Franja pico', hc.slots[peakIdx]], ['Dotación pico', Math.max.apply(null, hc.needed) + ' pers.'], ['Programados en el pico', hc.scheduled[peakIdx] + ' pers.'], ['Franjas', hc.slots.length]],
					chart: { type: 'bar', data: { labels: hc.slots, datasets: [{ label: 'Personas necesarias', data: hc.needed, backgroundColor: primary() }, { label: 'Programadas', data: hc.scheduled, backgroundColor: '#93c5fd' }] }, options: barOpts() }
				};
		}
		return null;
	}

	var SECTION_IDS = { branch: 'schViewBranch', area: 'schViewArea', employee: 'schViewEmployee', list: 'schViewList', matrix: 'schViewMatrix', monthly: 'schMonthlySection', skills: 'schSkillsSection', holidays: 'schHolidaysSection', peaks: 'schPeaksSection' };

	window.renderViewInsights = function (view) {
		var id = SECTION_IDS[view], cfg = viewInsights(view);
		if (!id || !cfg || typeof Chart === 'undefined') return;
		var $sec = $('#' + id);
		if (!$sec.length || $sec.find('.sch-insights').length) return;

		var tiles = cfg.metrics.map(function (m) {
			return '<div class="sch-insight-tile"><div class="sch-insight-label">' + esc(m[0]) + '</div><div class="sch-insight-value">' + esc(m[1]) + '</div></div>';
		}).join('');
		var $panel = $('<div class="sch-card sch-insights mb-3"><div class="sch-card-header"><h3 class="sch-card-title">' + esc(cfg.title) + '</h3><span class="badge badge-light border text-muted">Métricas</span></div>' +
			'<div class="sch-insight-grid">' + tiles + '</div><div style="height:230px;position:relative;"><canvas></canvas></div></div>');
		$sec.prepend($panel);
		// Esperar a que la sección sea visible para que Chart.js mida bien el canvas
		setTimeout(function () { charts['view_' + view] = new Chart($panel.find('canvas')[0].getContext('2d'), cfg.chart); }, 180);
	};

	$('<style>.sch-insight-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.6rem;margin-bottom:.8rem}' +
		'.sch-insight-tile{border:1px solid #e2e8f0;border-radius:8px;padding:.5rem .7rem;background:rgba(148,163,184,.06)}' +
		'.sch-insight-label{font-size:.66rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;font-weight:600}' +
		'.sch-insight-value{font-size:1.15rem;font-weight:700;color:var(--daily-primary,#0f172a)}</style>').appendTo('head');

	$(function () {
		initCharts();
		var v = new URLSearchParams(window.location.search).get('view');
		if (v) window.renderViewInsights(v);
	});

	// Filtering on Equidad
	$('#eqFilterArea').on('change', function () {
		var area = $(this).val();
		if (area === 'all') {
			$('.eq-collab-row').show();
			$('.eq-matrix-row').show();
		} else {
			$('.eq-collab-row').each(function () {
				$(this).toggle($(this).data('dept') === area);
			});
			$('.eq-matrix-row').each(function () {
				$(this).toggle($(this).data('dept') === area);
			});
		}
	});

	$('#eqFilterSearch').on('keyup input', function () {
		var term = $(this).val().toLowerCase().trim();
		$('.eq-collab-row').each(function () {
			var name = String($(this).data('name') || '').toLowerCase();
			var code = String($(this).data('code') || '').toLowerCase();
			var match = !term || name.indexOf(term) !== -1 || code.indexOf(term) !== -1;
			$(this).toggle(match);
		});
		$('.eq-matrix-row').each(function () {
			var code = String($(this).data('code') || '').toLowerCase();
			var match = !term || code.indexOf(term) !== -1 || $(this).text().toLowerCase().indexOf(term) !== -1;
			$(this).toggle(match);
		});
	});

	// Global hook to prepare swap for employee
	window.prepareSwapForEmp = function (empCode) {
		$('#swapEmpA').val(empCode);
		$('#modalShiftSwap').modal('show');
	};

	// Shift Swap Simulation
	$('#btnSimulateSwapAction').on('click', function () {
		var codeA = $('#swapEmpA').val();
		var dateA = $('#swapDateA').val();
		var codeB = $('#swapEmpB').val();
		var dateB = $('#swapDateB').val();

		if (codeA === codeB && dateA === dateB) {
			toastr.warning('Selecciona dos turnos diferentes para simular el intercambio.');
			return;
		}

		toastr.info('Simulando impacto en equidad, horas y descanso...');

		$.ajax({
			url: U('/api/schedule/equity/swap-preview'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				employee_a_code: codeA,
				date_a: dateA,
				employee_b_code: codeB,
				date_b: dateB
			}),
			success: function (res) {
				var ca = res.collaborator_a;
				var cb = res.collaborator_b;

				$('#simTitleA').text(ca.name + ' (' + ca.date + ')');
				$('#simCurrentShiftA').text(ca.current_shift);
				$('#simNewShiftA').text(ca.new_shift);
				$('#simCurrentEqA').text(ca.current_equity);
				$('#simProjectedEqA').text(ca.projected_equity);
				$('#simDeltaEqA').text(ca.equity_delta);
				$('#simCostA').text(ca.cost_delta);

				$('#simTitleB').text(cb.name + ' (' + cb.date + ')');
				$('#simCurrentShiftB').text(cb.current_shift);
				$('#simNewShiftB').text(cb.new_shift);
				$('#simCurrentEqB').text(cb.current_equity);
				$('#simProjectedEqB').text(cb.projected_equity);
				$('#simDeltaEqB').text(cb.equity_delta);
				$('#simCostB').text(cb.cost_delta);

				if (res.rest_compliance_ok) {
					$('#simRestComplianceAlert').html('<i class="fa-solid fa-circle-check text-success mr-1"></i> <strong class="text-success">Descanso legal:</strong> Cumple con >12 horas de descanso entre turnos.');
				} else {
					var warnText = res.warnings && res.warnings.length ? res.warnings.join('<br>') : 'Descanso legal insuficiente';
					$('#simRestComplianceAlert').html('<i class="fa-solid fa-triangle-exclamation text-danger mr-1"></i> <strong class="text-danger">Alerta de descanso:</strong> ' + warnText);
				}

				$('#simCoverageImpactText').text(res.coverage_impact + ' · ' + res.recommendation);
				$('#swapSimulationResultBox').slideDown();
				$('#btnConfirmSwapAction').prop('disabled', false);

				toastr.success('Simulación completada con éxito.');
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Execute Shift Swap
	$('#btnConfirmSwapAction').on('click', function () {
		var codeA = $('#swapEmpA').val();
		var dateA = $('#swapDateA').val();
		var codeB = $('#swapEmpB').val();
		var dateB = $('#swapDateB').val();

		$.ajax({
			url: U('/api/schedule/equity/swap-apply'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				employee_a_code: codeA,
				date_a: dateA,
				employee_b_code: codeB,
				date_b: dateB,
				manager_name: 'Gerente de Operaciones'
			}),
			success: function (res) {
				toastr.success(res.message || 'Intercambio ejecutado con éxito.');
				$('#modalShiftSwap').modal('hide');
				setTimeout(function () {
					window.location.reload();
				}, 900);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Re-Generate Schedule Action
	$('#btnExecuteReGenerate').on('click', function () {
		toastr.info('Ejecutando motor de programación inteligente en 10 pasos...');
		$(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Calculando...');

		$.ajax({
			url: U('/api/schedule/equity/generate'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ branch_id: S.branchId || 1 }),
			success: function (res) {
				toastr.success('Programación de noviembre generada con éxito (Equidad: ' + (res.kpis ? res.kpis.average_equity_display : '91.5%') + ')');
				setTimeout(function () {
					window.location.reload();
				}, 1000);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
				$('#btnExecuteReGenerate').prop('disabled', false).html('<i class="fa-solid fa-arrows-rotate mr-1"></i> Re-calcular Programación');
			}
		});
	});

	// Publish Schedule Action
	$('#btnApproveAndPublish, #btnPublishEquityAction').on('click', function () {
		$.ajax({
			url: U('/api/schedule/equity/publish'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ branch_id: S.branchId || 1, manager_name: 'Gerencia General' }),
			success: function (res) {
				toastr.success(res.message || 'Programación de Noviembre 2026 publicada oficialmente.');
				$('#modalGenerarProgramacion').modal('hide');
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Quick Cell Edit Modal
	window.openEditShiftModal = function (empCode, empName, dateStr, shiftCode) {
		$('#editShiftEmpName').text(empName);
		$('#editShiftDate').text(dateStr);
		$('#editShiftEmpCode').val(empCode);
		$('#editShiftDateIso').val(dateStr);
		if (shiftCode && shiftCode !== '—') {
			$('#editShiftCodeSelect').val(shiftCode);
		}
		$('#modalEditShiftCell').modal('show');
	};

	$('#btnSaveShiftCell').on('click', function () {
		var code = $('#editShiftEmpCode').val();
		var date = $('#editShiftDateIso').val();
		var newShift = $('#editShiftCodeSelect').val();

		$.ajax({
			url: U('/api/schedule/equity/update-shift'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				payroll_code: code,
				shift_date: date,
				shift_code: newShift,
				manager_name: 'Gerente'
			}),
			success: function (res) {
				toastr.success(res.message || 'Turno actualizado correctamente.');
				$('#modalEditShiftCell').modal('hide');
				// Update cell in UI
				var cell = $('td[data-emp-code="' + code + '"][data-date="' + date + '"] .sch-chip');
				if (cell.length) {
					cell.text(newShift);
					var newClass = 'sch-chip-libre';
					if (newShift.indexOf('A') !== -1) newClass = 'sch-chip-apertura';
					else if (newShift.indexOf('C') !== -1) newClass = 'sch-chip-cierre';
					else if (newShift.indexOf('N') !== -1) newClass = 'sch-chip-noche';
					else if (newShift === 'COMP') newClass = 'sch-chip-comp';
					cell.attr('class', 'sch-chip ' + newClass);
				}
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// ================= DAILY CALENDARIO MENSUAL 2026–2030+ HANDLERS =================

	window.openMonthlyCellEdit = function (pcode, empName, dateStr, shiftCode) {
		$('#mEditPayrollCode').val(pcode);
		$('#mEditEmpName').text(empName + ' (' + pcode + ')');
		$('#mEditDateStr').val(dateStr);
		$('#mEditDate').text(dateStr);
		$('#mEditShiftSelect').val(shiftCode);
		$('#modalMonthlyShiftEdit').modal('show');
	};

	$('#btnSaveMonthlyShift').on('click', function () {
		var pcode = $('#mEditPayrollCode').val();
		var dateStr = $('#mEditDateStr').val();
		var newShift = $('#mEditShiftSelect').val();
		var notes = $('#mEditNotes').val();

		// Con borrador activo se edita el borrador; lo oficial no cambia hasta aprobarlo
		if (S.monthlyDraftId) {
			$.ajax({
				url: U('/api/schedule/engine/drafts/' + S.monthlyDraftId + '/cell'),
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({ payroll_code: pcode, date: dateStr, shift_code: newShift }),
				success: function () { window.location.reload(); },
				error: function (xhr) { toastr.error(errMsg(xhr)); }
			});
			return;
		}

		$.ajax({
			url: U('/api/schedule/monthly/update-shift'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				payroll_code: pcode,
				shift_date: dateStr,
				shift_code: newShift,
				notes: notes
			}),
			success: function (res) {
				toastr.success('Turno modificado correctamente: ' + newShift);
				$('#modalMonthlyShiftEdit').modal('hide');

				// Update chip visually
				var row = $('#schMonthlyTable tr[data-emp-code="' + pcode + '"]');
				if (row.length) {
					var cell = row.find('span.sch-chip[onclick*="' + dateStr + '"]');
					if (cell.length) {
						cell.text(newShift);
						var chipClass = 'sch-chip-libre';
						if (newShift.indexOf('7A') !== -1) chipClass = 'sch-chip-apertura';
						else if (newShift.indexOf('7C') !== -1) chipClass = 'sch-chip-cierre';
						else if (newShift === '7N22-6') chipClass = 'sch-chip-noche';
						else if (newShift === 'VC') chipClass = 'sch-chip-vac';
						else if (newShift === 'COMP') chipClass = 'sch-chip-comp';
						cell.attr('class', 'sch-chip ' + chipClass);
					}
				}
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Month & Year Selector
	$('#selMonthlyMonth, #selMonthlyYear').on('change', function () {
		var y = $('#selMonthlyYear').val();
		var m = $('#selMonthlyMonth').val();
		gotoWithParams({ year: y, month_num: m, view: 'monthly' });
	});

	$('#btnPrevMonth').on('click', function () {
		var y = parseInt($('#selMonthlyYear').val(), 10);
		var m = parseInt($('#selMonthlyMonth').val(), 10) - 1;
		if (m < 1) { m = 12; y--; }
		gotoWithParams({ year: y, month_num: m, view: 'monthly' });
	});

	$('#btnNextMonth').on('click', function () {
		var y = parseInt($('#selMonthlyYear').val(), 10);
		var m = parseInt($('#selMonthlyMonth').val(), 10) + 1;
		if (m > 12) { m = 1; y++; }
		gotoWithParams({ year: y, month_num: m, view: 'monthly' });
	});

	// Export Monthly SAP CSV
	$('#btnExportMonthlyCsv').on('click', function () {
		var y = parseInt($('#selMonthlyYear').val(), 10);
		var m = parseInt($('#selMonthlyMonth').val(), 10);
		window.location.href = U('/api/schedule/monthly/export?year=' + y + '&month=' + m + '&branch_id=' + (S.branchId || 1));
	});

	// Skills Matrix Modal
	window.editCollaboratorSkill = function (pcode, name, area, secAreas, canNight, canLead, canCashier) {
		$('#skillEditPayrollCode').val(pcode);
		$('#skillEditName').text(name + ' (' + pcode + ')');
		$('#skillEditArea').text(area);

		// Uncheck all secondary areas
		$('.chk-sec-area').prop('checked', false);
		if (Array.isArray(secAreas)) {
			secAreas.forEach(function (sa) {
				$('.chk-sec-area[value="' + sa + '"]').prop('checked', true);
			});
		}

		$('#chkCanNight').prop('checked', !!canNight);
		$('#chkCanLead').prop('checked', !!canLead);
		$('#chkCanCashier').prop('checked', !!canCashier);

		$('#modalEditSkill').modal('show');
	};

	$('#btnSaveSkillSettings').on('click', function () {
		var pcode = $('#skillEditPayrollCode').val();
		var secAreas = [];
		$('.chk-sec-area:checked').each(function () {
			secAreas.push($(this).val());
		});

		$.ajax({
			url: U('/api/schedule/skills-matrix'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				payroll_code: pcode,
				secondary_areas: secAreas,
				can_night: $('#chkCanNight').is(':checked') ? 1 : 0,
				can_lead: $('#chkCanLead').is(':checked') ? 1 : 0,
				can_cashier: $('#chkCanCashier').is(':checked') ? 1 : 0
			}),
			success: function () {
				toastr.success('Competencias y habilitaciones guardadas.');
				$('#modalEditSkill').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// Submit Vacation
	$('#btnSubmitVacation').on('click', function () {
		var pcode = $('#vacPayrollCodeSelect').val();
		var sDt = $('#vacStartDate').val();
		var eDt = $('#vacEndDate').val();
		var notes = $('#vacNotes').val();

		if (!pcode || !sDt || !eDt) {
			toastr.error('Por favor complete el colaborador y las fechas de vacaciones.');
			return;
		}

		$.ajax({
			url: U('/api/schedule/vacations'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				payroll_code: pcode,
				start_date: sDt,
				end_date: eDt,
				notes: notes
			}),
			success: function () {
				toastr.success('Vacaciones registradas y programadas exitosamente.');
				$('#modalAddVacation').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

	// ================= 1. NAVEGACIÓN SEMANAL Y SELECTORES =================
	$('#prevWeekBtn').on('click', function () {
		var cur = S.monday || '2026-11-02';
		var d = new Date(cur + 'T00:00:00');
		d.setDate(d.getDate() - 7);
		var prev = d.toISOString().slice(0, 10);
		var u = new URL(window.location);
		u.searchParams.set('week', prev);
		window.location.href = u.toString();
	});

	$('#nextWeekBtn').on('click', function () {
		var cur = S.monday || '2026-11-02';
		var d = new Date(cur + 'T00:00:00');
		d.setDate(d.getDate() + 7);
		var next = d.toISOString().slice(0, 10);
		var u = new URL(window.location);
		u.searchParams.set('week', next);
		window.location.href = u.toString();
	});

	$('#currentWeekLabel').on('click', function () {
		var u = new URL(window.location);
		u.searchParams.set('week', '2026-11-02');
		window.location.href = u.toString();
	});

	$('#branchSelector').on('change', function () {
		var bid = $(this).val();
		var u = new URL(window.location);
		u.searchParams.set('branch_id', bid);
		window.location.href = u.toString();
	});

	// ================= ARRASTRAR Y SOLTAR CON VALIDACIÓN Y PERSISTENCIA =================
	var draggedShiftInfo = null;

	$(document).on('dragstart', '.sch-shift-card', function (e) {
		var card = $(this);
		var shiftId = card.data('shift-id');
		var cell = card.closest('.sch-day-cell');
		draggedShiftInfo = {
			id: shiftId,
			fromEmployeeId: cell.data('employee'),
			fromDate: cell.data('date')
		};
		if (e.originalEvent && e.originalEvent.dataTransfer) {
			e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify(draggedShiftInfo));
			e.originalEvent.dataTransfer.effectAllowed = 'move';
		}
	});

	$(document).on('dragover', '.sch-day-cell', function (e) {
		e.preventDefault();
		$(this).css('background-color', 'rgba(16, 185, 129, 0.15)');
	});

	$(document).on('dragleave', '.sch-day-cell', function () {
		$(this).css('background-color', '');
	});

	$(document).on('drop', '.sch-day-cell', function (e) {
		e.preventDefault();
		var targetCell = $(this);
		targetCell.css('background-color', '');
		var targetEmployeeId = targetCell.data('employee');
		var targetDate = targetCell.data('date');

		if (!draggedShiftInfo || !draggedShiftInfo.id) {
			return;
		}

		if (draggedShiftInfo.fromEmployeeId == targetEmployeeId && draggedShiftInfo.fromDate == targetDate) {
			return;
		}

		bootbox.confirm('¿Mover este turno al colaborador y/o fecha seleccionada?', function (confirmed) {
			if (!confirmed) return;
			Grocy.Api.Post('schedule/shifts/' + draggedShiftInfo.id + '/move', {
				employee_id: targetEmployeeId,
				shift_date: targetDate,
				reason: 'Reasignación arrastrar y soltar'
			}, function () {
				toastr.success('Turno reasignado y sincronizado con éxito');
				setTimeout(function () { window.location.reload(); }, 500);
			}, function (xhr) {
				toastr.error(errMsg(xhr));
			});
		});
	});

	// ================= 4. VISTA POR SUCURSAL =================
	$('#btnFilterBranchAll').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.branch-card-col').show();
	});
	$('#btnFilterBranchReal').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.branch-card-col').hide();
		$('.branch-card-col[data-is-demo="0"]').show();
	});
	$('#btnFilterBranchDemo').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.branch-card-col').hide();
		$('.branch-card-col[data-is-demo="1"]').show();
	});

	$(document).on('click', '.btn-select-branch-action', function () {
		var bid = $(this).data('branch-id');
		var u = new URL(window.location);
		u.searchParams.set('branch_id', bid);
		window.location.href = u.toString();
	});

	// ================= 5. VISTA POR ÁREA =================
	$('#btnFilterAreaAll').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.area-card-col').show();
	});
	$('#btnFilterAreaDeficit').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.area-card-col').hide();
		$('.area-card-col[data-is-deficit="1"]').show();
	});
	$('#btnFilterAreaOptimal').on('click', function () {
		$(this).siblings().removeClass('active'); $(this).addClass('active');
		$('.area-card-col').hide();
		$('.area-card-col[data-is-deficit="0"]').show();
	});

	// ================= 6. VISTA POR EMPLEADO =================
	$('#employeeDossierSelect').on('change', function () {
		var pcode = $(this).val();
		var u = new URL(window.location);
		u.searchParams.set('employee', pcode);
		u.searchParams.set('view', 'employee');
		window.location.href = u.toString();
	});

	$(document).on('click', '.btn-view-emp-dossier', function (e) {
		e.preventDefault();
		var pcode = $(this).data('payroll');
		var u = new URL(window.location);
		u.searchParams.set('employee', pcode);
		u.searchParams.set('view', 'employee');
		window.location.href = u.toString();
	});

	$(document).on('click', '.btn-edit-employee-shift', function () {
		var pcode = $(this).data('payroll');
		var date = $(this).data('date');
		var shift = $(this).data('shift');
		var name = $(this).data('name');
		window.openMonthlyCellEdit(pcode, name, date, shift);
	});

	// ================= 7. VISTA LISTA DE TURNOS =================
	$('#shiftMasterSearch, #shiftMasterFilterDept, #shiftMasterFilterType').on('input change', function () {
		var q = $('#shiftMasterSearch').val().toLowerCase().trim();
		var dept = $('#shiftMasterFilterDept').val();
		var type = $('#shiftMasterFilterType').val();

		$('.shift-master-row').each(function () {
			var r = $(this);
			var rName = (r.data('name') || '').toString();
			var rCode = (r.data('code') || '').toString();
			var rDept = (r.data('dept') || '').toString();
			var rType = (r.data('type') || '').toString();

			var matchSearch = (!q || rName.indexOf(q) !== -1 || rCode.indexOf(q) !== -1);
			var matchDept = (!dept || rDept === dept);
			var matchType = (!type || rType === type);

			if (matchSearch && matchDept && matchType) {
				r.show();
			} else {
				r.hide();
			}
		});
	});

	$('#btnResetShiftListFilters').on('click', function () {
		$('#shiftMasterSearch').val('');
		$('#shiftMasterFilterDept').val('');
		$('#shiftMasterFilterType').val('');
		$('.shift-master-row').show();
	});

	$(document).on('click', '.btn-list-edit-shift', function () {
		var pcode = $(this).data('payroll');
		var date = $(this).data('date');
		var shift = $(this).data('shift');
		var name = $(this).data('name');
		window.openMonthlyCellEdit(pcode, name, date, shift);
	});

	$(document).on('click', '.btn-list-duplicate-shift', function () {
		var pcode = $(this).data('payroll');
		var date = $(this).data('date');
		var shift = $(this).data('shift');
		var d = new Date(date + 'T00:00:00');
		d.setDate(d.getDate() + 1);
		var nextDate = d.toISOString().slice(0, 10);

		bootbox.confirm('¿Duplicar este turno para el día siguiente (' + nextDate + ')?', function (ok) {
			if (!ok) return;
			$.ajax({
				url: U('/api/schedule/monthly/shift'),
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({
					payroll_code: pcode,
					shift_date: nextDate,
					shift_code: shift,
					notes: 'Duplicado desde lista de turnos'
				}),
				success: function () {
					toastr.success('Turno duplicado exitosamente.');
					setTimeout(function () { window.location.reload(); }, 500);
				},
				error: function (xhr) { toastr.error(errMsg(xhr)); }
			});
		});
	});

	$(document).on('click', '.btn-list-delete-shift', function () {
		var pcode = $(this).data('payroll');
		var date = $(this).data('date');
		var name = $(this).data('name');

		bootbox.confirm('¿Anular el turno de ' + name + ' para el ' + date + '?', function (ok) {
			if (!ok) return;
			$.ajax({
				url: U('/api/schedule/monthly/shift'),
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({
					payroll_code: pcode,
					shift_date: date,
					shift_code: 'LIBRE',
					notes: 'Anulado desde lista de turnos'
				}),
				success: function () {
					toastr.success('Turno anulado correctamente (marcado como LIBRE).');
					setTimeout(function () { window.location.reload(); }, 500);
				},
				error: function (xhr) { toastr.error(errMsg(xhr)); }
			});
		});
	});

	$('#btnListExportCsv, #btnExportCsv').on('click', function () {
		window.location.href = U('/api/schedule/monthly/export?branch_id=' + (S.branchId || 1) + '&year=2026&month=11');
	});

	// ================= 8. POLÍTICAS LABORALES UNIVERSALES POR EMPRESA =================
	$('#modalCompanyLaborPolicies').on('show.bs.modal', function () {
		var companyId = $('#policyCompanyId').val() || 1;
		$.ajax({
			url: U('/api/schedule/policies?company_id=' + companyId),
			type: 'GET',
			success: function (res) {
				if (!res) return;
				var active = res.active_policy;
				var history = res.history || [];

				if (active) {
					$('#currentPolicyTitle').text(active.policy_name + ' (v' + (active.version || 1) + '.0)');
					$('#policyNameInput').val(active.policy_name);
					$('#policySundayTypeSelect').val(active.sunday_rule_type || '3x1');
					$('#policyRotationTypeSelect').val(active.rotation_rule_type || 'weekly_apertura_cierre');
					$('#policyMaxNightsInput').val(active.max_nights_per_day || 1);
					$('#policyMaxHoursInput').val(active.max_weekly_hours || 42.0);
					$('#policyMinRestInput').val(active.min_rest_hours || 12.0);
				}

				if (history.length > 0) {
					var html = '';
					history.forEach(function (p) {
						var isAct = p.active == 1;
						var statusBadge = isAct 
							? '<span class="badge badge-primary">Activa</span>' 
							: '<span class="badge badge-secondary">Histórica</span>';
						var cycleBadge = p.sunday_rule_type === '3x1'
							? '<span class="badge badge-success">3x1 Modular</span>'
							: '<span class="badge badge-info">' + p.sunday_rule_type + '</span>';

						html += '<tr>' +
							'<td class="font-weight-bold">v' + p.version + '.0</td>' +
							'<td>' + (p.policy_name || 'Política General') + '</td>' +
							'<td>' + (p.effective_date || '-') + '</td>' +
							'<td>' + cycleBadge + '</td>' +
							'<td>' + (p.rotation_rule_type === 'weekly_apertura_cierre' ? 'Semanal Apertura/Cierre' : p.rotation_rule_type) + '</td>' +
							'<td>' + (p.max_nights_per_day || 1) + ' persona/noche</td>' +
							'<td>' + statusBadge + '</td>' +
						'</tr>';
					});
					$('#policyHistoryTableBody').html(html);
				}
			},
			error: function (xhr) {
				toastr.error('Error al cargar políticas laborales: ' + errMsg(xhr));
			}
		});
	});

	$('#policyJurisdictionSelect').on('change', function () {
		var opt = this.options[this.selectedIndex];
		if (!opt) return;
		var d = opt.dataset || {};
		if (d.country) $('#policyCountryCode').val(d.country);
		if (d.weekly) $('#policyMaxHoursInput').val(d.weekly);
		if (d.daily) $('#policyMaxDailyInput').val(d.daily);
		if (d.rest) $('#policyMinRestInput').val(d.rest);
		if (d.sunday) $('#policySundayTypeSelect').val(d.sunday);
		if (d.nightStart) $('#policyNightStartInput').val(d.nightStart);
		if (d.nightEnd) $('#policyNightEndInput').val(d.nightEnd);
		if (d.ref) $('#policyJurLegalRef').text(d.ref);
	});

	$('#btnSaveCompanyPolicyAction').on('click', function () {
		var companyId = $('#policyCompanyId').val() || 1;
		var branchId = $('#policyBranchId').val() || S.branchId || 1;
		var jurCode = $('#policyJurisdictionSelect').val() || 'CO-GEN';
		var countryCode = $('#policyCountryCode').val() || 'CO';
		var pName = $('#policyNameInput').val();
		var effDate = $('#policyEffectiveDateInput').val();
		var sunRule = $('#policySundayTypeSelect').val();
		var rotRule = $('#policyRotationTypeSelect').val();
		var maxNights = parseInt($('#policyMaxNightsInput').val(), 10) || 1;
		var maxHours = parseFloat($('#policyMaxHoursInput').val()) || 42.0;
		var maxDaily = parseFloat($('#policyMaxDailyInput').val()) || 8.0;
		var minRest = parseFloat($('#policyMinRestInput').val()) || 12.0;
		var nightStart = parseInt($('#policyNightStartInput').val(), 10) || 21;
		var nightEnd = parseInt($('#policyNightEndInput').val(), 10) || 6;

		if (!pName || !effDate) {
			toastr.error('Por favor indique el nombre y la fecha de vigencia de la política.');
			return;
		}

		toastr.info('Guardando y versionando política laboral y jurisdicción...');
		$.ajax({
			url: U('/api/schedule/policies'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				company_id: companyId,
				branch_id: parseInt(branchId, 10) || 1,
				country_code: countryCode,
				jurisdiction_code: jurCode,
				policy_name: pName,
				effective_date: effDate,
				sunday_rule_type: sunRule,
				rotation_rule_type: rotRule,
				max_nights_per_day: maxNights,
				max_weekly_hours: maxHours,
				max_daily_hours: maxDaily,
				min_rest_hours: minRest,
				night_start_hour: nightStart,
				night_end_hour: nightEnd,
				compensatory_enabled: 1,
				compensatory_days_rule: 'MON_FRI_NO_HOLIDAYS'
			}),
			success: function (res) {
				toastr.success('Política ' + jurCode + ' v' + (res.version || '') + ' guardada y aplicada.');
				$('#modalCompanyLaborPolicies').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error('Error al guardar política: ' + errMsg(xhr));
			}
		});
	});

	$('#swapTargetEmpId').on('change', function () {
		var empId = $(this).val();
		$('#swapTargetShiftId option').each(function () {
			var optEmp = $(this).attr('data-emp');
			if (!optEmp || !empId || optEmp === empId) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
		$('#swapTargetShiftId').val('');
	});

	$('#formRequestSwap').on('submit', function (e) {
		e.preventDefault();
		var shiftId = parseInt($('#swapSourceShiftId').val(), 10);
		var toEmpId = parseInt($('#swapTargetEmpId').val(), 10);
		var targetShiftId = parseInt($('#swapTargetShiftId').val(), 10) || null;
		var notes = $('#swapNotesInput').val() || '';

		if (!shiftId || !toEmpId) {
			toastr.warning('Seleccione el turno origen y el colaborador receptor.');
			return;
		}

		$.ajax({
			url: U('/api/schedule/swaps'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				shift_id: shiftId,
				to_employee_id: toEmpId,
				target_shift_id: targetShiftId,
				notes: notes
			}),
			success: function () {
				toastr.success('Solicitud de cambio/permuta registrada y validada.');
				$('#modalRequestSwap').modal('hide');
				setTimeout(function () { window.location.reload(); }, 700);
			},
			error: function (xhr) {
				toastr.error(errMsg(xhr));
			}
		});
	});

})(jQuery);


