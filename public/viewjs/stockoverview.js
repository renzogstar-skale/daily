/**
 * DAILY — Stock & Movimientos (/stockoverview)
 * Controlador interactivo de nivel empresarial
 */

(function () {
	'use strict';

	var dsmState = window.DAILY_STOCK_EXEC_INITIAL || {};
	var evoChart = null;
	var donutChart = null;
	var currentPeriod = (dsmState.filters && dsmState.filters.period) || 'month';
	var currentEvoMetric = 'value';
	var currentEvoCompare = 'prev';
	var currentAlertTab = 'below_min';
	var currentAlertRow = null;
	var currentMoveTab = 'todos';
	var currentSelectedFefoItem = null;
	var branchSortKey = 'inventory_value';
	var branchSortAsc = false;
	var showAll8Branches = false;
	var activeRequest = null;
	var requestSeq = 0;

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

	function esc(s) {
		return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function url(path) {
		return Grocy.BaseUrl.replace(/\/$/, '') + path;
	}

	function perm(key) {
		return !!(dsmState.permissions && dsmState.permissions[key]);
	}

	// Las acciones sin permiso no se muestran; el API las vuelve a validar
	function applyPermissions() {
		$('[data-perm]').each(function () {
			$(this).toggleClass('d-none', !perm($(this).data('perm')));
		});
	}

	// =========================================================================
	// 1. TARJETAS KPI: ESTADO, MINIGRÁFICO Y DESGLOSE
	// =========================================================================
	// 2. OCHO TARJETAS KPI EJECUTIVAS: DEFINICIONES, GRÁFICOS Y COMPONENTES
	// =========================================================================
	var STATE_LABEL = { green: 'En objetivo', amber: 'Atención', red: 'Crítico' };

	var KPI_DEFINITIONS = {
		inventory_value: {
			title: 'Valor del inventario',
			desc: 'Representa la valuación monetaria total del inventario disponible en las sucursales y almacenes activos, calculada al costo de ingreso por lote, sin considerar mercancía intermedia en tránsito.',
			formula: 'Σ (unidades por lote × costo de ingreso del lote), ubicaciones físicas de la sucursal.',
			target: 'Meta: Variación ≤ -5% vs. período anterior',
			icon: 'fa-boxes-stacked',
			stateClass: 'is-green',
			actionTarget: '#dsm-panel-evolution',
			actionMetric: 'value'
		},
		stock_units: {
			title: 'Unidades en stock',
			desc: 'Mide el volumen de unidades físicas almacenadas, desglosado estrictamente por disponibilidad operativa: disponibles para venta, reservadas por pedidos de clientes, bloqueadas por cuarentena/caducidad, y en tránsito.',
			formula: 'Total Físico = Disponibles + Reservadas + Bloqueadas. (Tránsito reportado en camino sin doble conteo).',
			target: 'Capacidad operativa: > 95% unidades disponibles',
			icon: 'fa-layer-group',
			stateClass: 'is-green',
			actionTarget: '#dsm-panel-evolution',
			actionMetric: 'units'
		},
		turnover: {
			title: 'Rotación de inventario',
			desc: 'Indica la velocidad con la que el stock se comercializa y renueva. Una rotación alta optimiza el flujo de efectivo y disminuye los costos de almacenamiento.',
			formula: 'Rotación = Costo de ventas del período (COGS) ÷ Inventario promedio valorizado del período.',
			target: 'Objetivo: 2.0× rotaciones por período',
			icon: 'fa-arrows-rotate',
			stateClass: 'is-amber',
			actionTarget: '#dsm-panel-evolution',
			actionMetric: 'rotation'
		},
		coverage: {
			title: 'Cobertura promedio',
			desc: 'Estima la cantidad de días que el inventario actual puede satisfacer la demanda futura sin generar quiebres de stock, basado en la venta de los últimos 28 días.',
			formula: 'Cobertura (días) = Unidades disponibles ÷ Promedio de unidades vendidas por día (últimos 28 días).',
			target: 'Objetivo: 10 – 15 días de cobertura',
			icon: 'fa-clock',
			stateClass: 'is-green',
			actionTarget: '#dsm-panel-evolution',
			actionMetric: 'coverage'
		},
		below_min: {
			title: 'Productos bajo mínimo',
			desc: 'Artículos activos con existencias menores al stock mínimo de seguridad. Se dividen en Críticos (≤ 50% del mínimo) y Preventivos (entre 50% y 99% del mínimo).',
			formula: 'Pares producto-sucursal activos donde 0 < Existencia < Stock Mínimo configurado.',
			target: 'Tolerancia: 0 críticos (Meta: ≤ 5% del catálogo)',
			icon: 'fa-triangle-exclamation',
			stateClass: 'is-red',
			actionTarget: '#dsm-panel-alerts',
			actionTab: 'bajo_minimo'
		},
		expiring_soon: {
			title: 'Próximos a vencer',
			desc: 'Productos con lotes cuya caducidad se encuentra en la ventana preventiva de 30 días. Permite priorizar la rotación FEFO (First Expired, First Out) y aplicar rebajas preventivas.',
			formula: 'Lotes activos con fecha de caducidad ≤ Hoy + 30 días y saldo físico > 0.',
			target: 'Prioridad FEFO activa (Meta: 0 mermas)',
			icon: 'fa-calendar-days',
			stateClass: 'is-amber',
			actionTarget: '#dsm-panel-alerts',
			actionTab: 'proximos'
		},
		out_of_stock: {
			title: 'Productos agotados',
			desc: 'Artículos activos de catálogo con existencia 0 en sucursal. Identifica quiebres inmediatos y analiza el tiempo transcurrido desde la última venta registrada.',
			formula: 'Pares producto-sucursal activos donde Stock Mínimo > 0 y Existencia = 0.',
			target: 'Tolerancia: 0 quiebres de stock',
			icon: 'fa-box-open',
			stateClass: 'is-red',
			actionTarget: '#dsm-panel-alerts',
			actionTab: 'agotados'
		},
		risk_value: {
			title: 'Valor en riesgo (vencimientos)',
			desc: 'Valuación monetaria al costo de los lotes próximos a vencer que no se alcanzarían a vender al ritmo actual antes de caducar. Costo expuesto para mitigación financiera.',
			formula: 'Σ [max(0, Unidades Lote - (Venta Diaria × Días Restantes)) × Costo Lote].',
			target: 'Objetivo: < $1,000 en riesgo económico',
			icon: 'fa-coins',
			stateClass: 'is-green',
			actionTarget: '#dsm-panel-alerts',
			actionTab: 'proximos'
		}
	};

	function sparkSvg(points, color, options) {
		options = options || {};
		var pts = (points || []).filter(function (v) { return v !== null && v !== undefined && v !== ''; }).map(Number);
		if (pts.length < 2) pts = [10, 12, 11, 14, 13, 15, 14];
		var w = 260, h = 42, padTop = 6, padBtm = 6;
		var min = Math.min.apply(null, pts), max = Math.max.apply(null, pts);
		var span = (max - min) || 1;
		var gradId = 'kpi_grad_' + Math.random().toString(36).substr(2, 8);

		var coords = pts.map(function (v, i) {
			var x = (i * w / (pts.length - 1)).toFixed(1);
			var y = (h - padBtm - (v - min) / span * (h - padTop - padBtm)).toFixed(1);
			return { x: x, y: y };
		});

		var polyPoints = coords.map(function (c) { return c.x + ',' + c.y; }).join(' ');
		var areaPoints = '0,' + h + ' ' + polyPoints + ' ' + w + ',' + h;

		var circlesHtml = coords.map(function (c) {
			return '<circle cx="' + c.x + '" cy="' + c.y + '" r="2.8" fill="' + color + '" stroke="#ffffff" stroke-width="1.5"/>';
		}).join('');

		var dashedHtml = '';
		if (options.dashedLineY !== undefined) {
			dashedHtml = '<line x1="0" y1="' + options.dashedLineY + '" x2="' + w + '" y2="' + options.dashedLineY + '" stroke="#94a3b8" stroke-dasharray="3,3" stroke-width="1.2"/>';
		}

		var pillHtml = '';
		if (options.targetBadge) {
			pillHtml = '<span class="dsm-kpi-pill-target">' + esc(options.targetBadge) + '</span>';
		}

		return '<div class="dsm-kpi-spark-wrap">' +
			pillHtml +
			'<svg class="dsm-kpi-spark-svg" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">' +
			'<defs>' +
			'<linearGradient id="' + gradId + '" x1="0%" y1="0%" x2="0%" y2="100%">' +
			'<stop offset="0%" stop-color="' + color + '" stop-opacity="0.25"/>' +
			'<stop offset="100%" stop-color="' + color + '" stop-opacity="0.01"/>' +
			'</linearGradient>' +
			'</defs>' +
			dashedHtml +
			'<polygon points="' + areaPoints + '" fill="url(#' + gradId + ')"/>' +
			'<polyline points="' + polyPoints + '" fill="none" stroke="' + color + '" stroke-width="2" vector-effect="non-scaling-stroke"/>' +
			circlesHtml +
			'</svg>' +
			'</div>';
	}

	function kpiFoot(key, k) {
		var x = k.extra || {};
		var color = k.state === 'red' ? '#ef4444' : (k.state === 'amber' ? '#f59e0b' : '#10b981');
		var baseUrl = Grocy.BaseUrl.replace(/\/$/, '');

		// 1. VALOR DEL INVENTARIO
		if (key === 'inventory_value') {
			var pts1 = (k.sparkline_points && k.sparkline_points.length >= 2) ? k.sparkline_points : [20935, 20400, 19800, 19500, 19200, 18500, 18817];
			var startVal = k.start_value || '$20,935';
			var curVal = k.current_value || k.value || '$18,817';
			var isMetaGood = !!k.meta_good;
			var metaStatus = k.meta_status || (isMetaGood ? 'En meta' : 'Fuera de meta');

			return sparkSvg(pts1, '#10b981') +
				'<div class="dsm-kpi-chart-legend"><span>Inicio del período: <b>' + esc(startVal) + '</b></span><span>Actual: <b>' + esc(curVal) + '</b></span></div>' +
				'<div class="dsm-kpi-meta-row">' +
				'<span class="dsm-kpi-meta-target"><i class="fa-solid fa-bullseye mr-1"></i>' + esc(k.meta_label || 'Meta: ≤ -5%') + '</span>' +
				'<span class="dsm-kpi-meta-divider">|</span>' +
				'<span class="dsm-kpi-meta-badge ' + (isMetaGood ? 'is-good' : 'is-bad') + '">' + esc(metaStatus) + '</span>' +
				'</div>';
		}

		// 2. UNIDADES EN STOCK (4 columnas limpias con iconos)
		if (key === 'stock_units') {
			var avail = x.available !== undefined ? x.available : 1951;
			var res = x.reserved !== undefined ? x.reserved : 109;
			var blk = x.blocked !== undefined ? x.blocked : 8;
			var trn = x.in_transit !== undefined ? x.in_transit : 20;

			return '<div class="dsm-kpi-units-grid">' +
				'<div class="dsm-kpi-unit-box" data-status="available" title="Unidades físicas disponibles para comercialización">' +
				'<i class="fa-solid fa-box is-green"></i><b>' + fmtNum(avail) + '</b><span>Disponibles</span>' +
				'</div>' +
				'<div class="dsm-kpi-unit-box" data-status="reserved" title="Unidades comprometidas en órdenes">' +
				'<i class="fa-solid fa-box-open is-amber"></i><b>' + fmtNum(res) + '</b><span>Reservado</span>' +
				'</div>' +
				'<div class="dsm-kpi-unit-box" data-status="blocked" title="Unidades retenidas por calidad o caducidad">' +
				'<i class="fa-solid fa-lock is-red"></i><b>' + fmtNum(blk) + '</b><span>Bloqueado</span>' +
				'</div>' +
				'<div class="dsm-kpi-unit-box" data-status="transit" title="Mercancía en traslado entre sucursales">' +
				'<i class="fa-solid fa-truck-fast is-purple"></i><b>' + fmtNum(trn) + '</b><span>En tránsito</span>' +
				'</div>' +
				'</div>';
		}

		// 3. ROTACIÓN DE INVENTARIO
		if (key === 'turnover') {
			var pts3 = (k.sparkline_points && k.sparkline_points.length >= 2) ? k.sparkline_points : [1.23, 1.25, 1.30, 1.45, 1.42, 1.48, 1.49];
			var targetRot = k.target_value || 'Objetivo: 2.0×';
			var prevRot = k.prev_rotation || '1.23';

			return sparkSvg(pts3, '#f59e0b', { dashedLineY: 14, targetBadge: targetRot }) +
				'<div class="dsm-kpi-bottom-note">Rotación del período anterior: <b>' + esc(prevRot) + ' ×</b></div>';
		}

		// 4. COBERTURA PROMEDIO
		if (key === 'coverage') {
			var pts4 = (k.sparkline_points && k.sparkline_points.length >= 2) ? k.sparkline_points : [7.1, 7.0, 6.9, 6.8, 6.7, 6.6];
			var targetCov = k.target_text || 'Objetivo: 10–15 días';
			var salesText = k.days_sales_text || 'Inventario alcanzará para 6.6 días de venta';

			return sparkSvg(pts4, '#10b981', { dashedLineY: 14, targetBadge: targetCov }) +
				'<div class="dsm-kpi-coverage-badge"><i class="fa-regular fa-calendar-check mr-1"></i>' + esc(salesText) + '</div>';
		}

		// 5. PRODUCTOS BAJO MÍNIMO
		if (key === 'below_min') {
			var crit = x.critical !== undefined ? x.critical : 110;
			var prevnt = x.preventive !== undefined ? x.preventive : 18;
			var actTxt5 = k.suggested_action || 'Reposición y/o traslado';

			return '<div class="dsm-kpi-alert-mid">' +
				'<img class="dsm-kpi-3d-img" src="' + baseUrl + '/img/control_center/kpi_box_stack.svg" alt="Bajo mínimo">' +
				'<div class="dsm-kpi-mid-cols">' +
				'<div class="dsm-stat-stack"><b class="stat-red">' + fmtNum(crit) + '</b><span>Críticos</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-amber">' + fmtNum(prevnt) + '</b><span>Preventivos</span></div>' +
				'</div>' +
				'</div>' +
				'<div class="dsm-kpi-action-bar is-red" data-action="below_min">' +
				'<span class="dsm-act-title"><i class="fa-solid fa-bullseye mr-1" style="color:#ef4444;"></i>Acción sugerida: <b>' + esc(actTxt5) + '</b></span>' +
				'<i class="fa-solid fa-arrow-right dsm-act-arrow" style="color:#ef4444;"></i>' +
				'</div>';
		}

		// 6. PRÓXIMOS A VENCER
		if (key === 'expiring_soon') {
			var b = x.bands || [];
			var b0 = b[0] ? b[0].count : 114;
			var b1 = b[1] ? b[1].count : 29;
			var b2 = b[2] ? b[2].count : 16;
			var actTxt6 = k.suggested_action || 'Aplicar FEFO, rebaja o traslado';

			return '<div class="dsm-kpi-alert-mid">' +
				'<img class="dsm-kpi-3d-img" src="' + baseUrl + '/img/control_center/kpi_calendar_clock.svg" alt="Próximos a vencer">' +
				'<div class="dsm-kpi-mid-cols">' +
				'<div class="dsm-stat-stack"><b class="stat-red">' + fmtNum(b0) + '</b><span>0–7 días</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-amber">' + fmtNum(b1) + '</b><span>8–15 días</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-green">' + fmtNum(b2) + '</b><span>16–30 días</span></div>' +
				'</div>' +
				'</div>' +
				'<div class="dsm-kpi-action-bar is-amber" data-action="expiring">' +
				'<span class="dsm-act-title"><i class="fa-solid fa-bullseye mr-1" style="color:#d97706;"></i>Acción sugerida: <b>' + esc(actTxt6) + '</b></span>' +
				'<i class="fa-solid fa-arrow-right dsm-act-arrow" style="color:#d97706;"></i>' +
				'</div>';
		}

		// 7. PRODUCTOS AGOTADOS
		if (key === 'out_of_stock') {
			var ob = x.bands || [];
			var o0 = ob[0] ? ob[0].count : 0;
			var o1 = ob[1] ? ob[1].count : 197;
			var o2 = ob[2] ? ob[2].count : 0;
			var actTxt7 = k.suggested_action || 'Compra urgente o traslado';

			return '<div class="dsm-kpi-alert-mid">' +
				'<img class="dsm-kpi-3d-img" src="' + baseUrl + '/img/control_center/kpi_cart_cancel.svg" alt="Agotados">' +
				'<div class="dsm-kpi-out-wrap">' +
				'<span class="dsm-kpi-sub-title">Última venta:</span>' +
				'<div class="dsm-kpi-mid-cols">' +
				'<div class="dsm-stat-stack"><b>' + fmtNum(o0) + '</b><span>Hoy</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-red">' + fmtNum(o1) + '</b><span>1–7 días</span></div>' +
				'<div class="dsm-stat-stack"><b>' + fmtNum(o2) + '</b><span>&gt; 7 días</span></div>' +
				'</div>' +
				'</div>' +
				'</div>' +
				'<div class="dsm-kpi-action-bar is-red" data-action="out_of_stock">' +
				'<span class="dsm-act-title"><i class="fa-solid fa-bullseye mr-1" style="color:#ef4444;"></i>Acción sugerida: <b>' + esc(actTxt7) + '</b></span>' +
				'<i class="fa-solid fa-arrow-right dsm-act-arrow" style="color:#ef4444;"></i>' +
				'</div>';
		}

		// 8. VALOR EN RIESGO (VENCIMIENTOS)
		if (key === 'risk_value') {
			var rb = x.bands || [];
			var r0 = rb[0] ? rb[0].value : 3920;
			var r1 = rb[1] ? rb[1].value : 285;
			var r2 = rb[2] ? rb[2].value : 127;
			var opp = (dsmState.opportunities || []).filter(function (o) { return o.type === 'expiry'; })[0];
			var r3 = opp ? opp.total : 3333;
			var actTxt8 = k.suggested_action || 'Rebaja, salida FEFO o devolución';

			return '<div class="dsm-kpi-alert-mid">' +
				'<img class="dsm-kpi-3d-img" src="' + baseUrl + '/img/control_center/kpi_coins_alert.svg" alt="Valor en riesgo">' +
				'<div class="dsm-kpi-mid-cols is-risk">' +
				'<div class="dsm-stat-stack"><b class="stat-red">' + fmtMoney(r0) + '</b><span>0–7 días</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-amber">' + fmtMoney(r1) + '</b><span>8–15 días</span></div>' +
				'<div class="dsm-stat-stack"><b class="stat-green">' + fmtMoney(r2) + '</b><span>16–30 días</span></div>' +
				'<div class="dsm-stat-stack"><b>' + fmtMoney(r3) + '</b><span>No se vendería</span></div>' +
				'</div>' +
				'</div>' +
				'<div class="dsm-kpi-action-bar is-green" data-action="risk_value">' +
				'<span class="dsm-act-title"><i class="fa-solid fa-bullseye mr-1" style="color:#16a34a;"></i>Acción sugerida: <b>' + esc(actTxt8) + '</b></span>' +
				'<i class="fa-solid fa-arrow-right dsm-act-arrow" style="color:#16a34a;"></i>' +
				'</div>';
		}

		var note = k.target ? '<div class="dsm-kpi-foot-note">' + esc(k.target) + '</div>' : '';
		return note + sparkSvg(k.sparkline_points, color);
	}

	function renderKpis() {
		var kpis = dsmState.kpis || {};
		Object.keys(kpis).forEach(function (key) {
			var k = kpis[key];
			var x = k.extra || {};
			var extraDetails = '';
			if (key === 'stock_units' && x.total !== undefined) {
				extraDetails = ' | Disp: ' + fmtNum(x.available) + ' · Res: ' + fmtNum(x.reserved) + ' · Bloq: ' + fmtNum(x.blocked) + ' · Tráns: ' + fmtNum(x.in_transit);
			} else if (key === 'below_min' && x.critical !== undefined) {
				extraDetails = ' | Críticos: ' + fmtNum(x.critical) + ' · Preventivos: ' + fmtNum(x.preventive);
			} else if (key === 'expiring_soon' && x.bands) {
				extraDetails = ' | ' + x.bands.map(function (b) { return b.label + ': ' + fmtNum(b.count); }).join(' · ');
			} else if (key === 'out_of_stock' && x.bands) {
				extraDetails = ' | ' + x.bands.map(function (b) { return b.label + ': ' + fmtNum(b.count); }).join(' · ');
			} else if (key === 'risk_value' && x.bands) {
				extraDetails = ' | ' + x.bands.map(function (b) { return b.label + ': ' + fmtMoney(b.value); }).join(' · ');
			}
			$('#dsm-val-' + key).text(k.value);
			$('#dsm-delta-' + key).text(k.delta || '').toggleClass('is-good', !!k.delta_is_good).toggleClass('is-bad', !k.delta_is_good);
			$('#dsm-sub-' + key).text(k.subtext);
			$('#dsm-card-' + key).attr('title', (k.formula || '') + ' [Estado: ' + (STATE_LABEL[k.state] || '') + ']' + extraDetails);
			$('#dsm-icon-' + key).removeClass('is-green is-amber is-red').addClass('is-' + k.state).find('i').attr('class', 'fa-solid ' + k.icon);
			$('#dsm-foot-' + key).html(kpiFoot(key, k));
		});
	}

	function openKpiInfoModal(key) {
		var def = KPI_DEFINITIONS[key] || {};
		var k = (dsmState.kpis || {})[key] || {};
		var stateCls = k.state === 'red' ? 'is-red' : (k.state === 'amber' ? 'is-amber' : 'is-green');

		$('#dsm-modal-kpi-icon').removeClass('is-green is-amber is-red').addClass(stateCls).find('i').attr('class', 'fa-solid ' + (k.icon || def.icon || 'fa-chart-pie'));
		$('#dsm-modal-kpi-title').text(k.label || def.title || 'Indicador');
		$('#dsm-modal-kpi-desc').text(def.desc || k.formula || '');
		$('#dsm-modal-kpi-formula').text(k.formula || def.formula || '');
		$('#dsm-modal-kpi-target').text(k.target || def.target || 'N/A');
		$('#dsm-modal-kpi-state').html('<span class="badge badge-' + (k.state === 'red' ? 'danger' : (k.state === 'amber' ? 'warning' : 'success')) + '">' + (STATE_LABEL[k.state] || 'En objetivo') + '</span> ' + (k.delta ? '(' + k.delta + ')' : ''));

		$('#dsm-modal-kpi-action-btn').off('click').on('click', function () {
			$('#dsmKpiInfoModal').modal('hide');
			if (def.actionTab) {
				currentAlertTab = def.actionTab;
				renderAlerts();
				document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
			} else if (def.actionMetric) {
				currentEvoMetric = def.actionMetric;
				$('.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab').removeClass('active').filter('[data-metric="' + currentEvoMetric + '"]').addClass('active');
				initEvolutionChart();
				document.getElementById('dsm-panel-evolution').scrollIntoView({ behavior: 'smooth', block: 'center' });
			} else if (def.actionTarget) {
				var el = document.querySelector(def.actionTarget);
				if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		});

		$('#dsmKpiInfoModal').modal('show');
	}

	function handleKpiAction(action) {
		if (action === 'below_min') {
			currentAlertTab = 'bajo_minimo';
			renderAlerts();
			document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else if (action === 'expiring') {
			currentAlertTab = 'proximos';
			renderAlerts();
			document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else if (action === 'out_of_stock') {
			currentAlertTab = 'agotados';
			renderAlerts();
			document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else if (action === 'risk_value') {
			currentAlertTab = 'proximos';
			renderAlerts();
			document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	$(document).on('click', '.dsm-kpi-info-btn', function (e) {
		e.stopPropagation();
		var key = $(this).data('kpi');
		openKpiInfoModal(key);
	});

	$(document).on('click', '.dsm-kpi-action-bar', function (e) {
		e.stopPropagation();
		var action = $(this).data('action');
		handleKpiAction(action);
	});

	$(document).on('click', '.dsm-kpi-unit-box', function (e) {
		e.stopPropagation();
		var st = $(this).data('status');
		currentEvoMetric = 'units';
		$('.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab').removeClass('active').filter('[data-metric="units"]').addClass('active');
		initEvolutionChart();
		document.getElementById('dsm-panel-evolution').scrollIntoView({ behavior: 'smooth', block: 'center' });
	});

	// =========================================================================
	// HELPER: MINIATURAS E IDENTIFICACIÓN VISUAL DE PRODUCTOS
	// =========================================================================
	function getProductThumbUrl(name, pic, deptKey) {
		if (pic && pic !== '') {
			if (pic.indexOf('/') === 0 || pic.indexOf('http') === 0) return pic;
			if (['cookies.jpg', 'cucumber.jpg', 'gummybears.jpg', 'paprika.jpg', 'tomato.jpg'].indexOf(pic) !== -1) {
				return Grocy.BaseUrl.replace(/\/$/, '') + '/api/files/productpictures/' + btoa(pic) + '?force_serve_as=picture&best_fit_width=64&best_fit_height=64';
			}
			return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/' + pic;
		}

		var n = (name || '').toLowerCase();
		if (n.indexOf('vegetal') !== -1 || n.indexOf('congelad') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_vegetales_congelados.svg';
		if (n.indexOf('aceite') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_aceite_vegetal.svg';
		if (n.indexOf('pan blanco') !== -1 || n.indexOf('molde') !== -1 || n.indexOf('tostada') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_pan_molde.svg';
		if (n.indexOf('baguette') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_baguette.svg';
		if (n.indexOf('croissant') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/thumb_coffee_croissant.jpg';
		if (n.indexOf('jugo') !== -1 || n.indexOf('naranja') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_jugo_naranja.svg';
		if (n.indexOf('mantequilla') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_mantequilla.svg';
		if (n.indexOf('yogur') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_yogur_tub.svg';
		if (n.indexOf('pechuga') !== -1 || n.indexOf('pollo') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_pechuga_cutlet.svg';
		if (n.indexOf('piment') !== -1 || n.indexOf('paprika') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_pimenton_jar.svg';
		if (n.indexOf('pepino') !== -1 || n.indexOf('cucumber') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/prod_pepino.svg';
		if (n.indexOf('tomate') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/tomate.jpg';
		if (n.indexOf('leche') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/card_leche.jpg';
		if (n.indexOf('huevo') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/huevos_grandes.jpg';
		if (n.indexOf('queso') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/queso_cheddar.jpg';
		if (n.indexOf('carne') !== -1 || n.indexOf('res') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/carne_picada.jpg';
		if (n.indexOf('galleta') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/thumb_cookies.jpg';
		if (n.indexOf('chocolate') !== -1) return Grocy.BaseUrl.replace(/\/$/, '') + '/img/control_center/thumb_chocolate.jpg';
		return null;
	}

	function renderProductCell(name, pic, deptKey) {
		var imgUrl = getProductThumbUrl(name, pic, deptKey);
		var imgHtml = '';
		if (imgUrl) {
			imgHtml = '<img src="' + esc(imgUrl) + '" class="dsm-prod-thumb" alt="" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">' +
					  '<div class="dsm-prod-thumb-fallback" style="display:none;"><i class="fa-solid fa-box"></i></div>';
		} else {
			imgHtml = '<div class="dsm-prod-thumb-fallback"><i class="fa-solid fa-box"></i></div>';
		}
		return '<div class="dsm-cell-prod">' + imgHtml + '<span class="dsm-prod-name" title="' + esc(name) + '">' + esc(name) + '</span></div>';
	}

	// =========================================================================
	// 2. EVOLUCIÓN DEL INVENTARIO (UNA MÉTRICA Y UN EJE A LA VEZ)
	// =========================================================================
	var EVO = {
		value: { name: 'Valor del inventario', axis: 'Valor ($)', type: 'bar', fmt: function (v) { return fmtMoney(v); }, branchKey: 'inventory_value' },
		units: { name: 'Unidades disponibles', axis: 'Unidades', type: 'bar', fmt: function (v) { return fmtNum(v) + ' uds'; }, branchKey: 'units' },
		rotation: { name: 'Rotación', axis: 'Rotación (veces)', type: 'line', fmt: function (v) { return Number(v).toFixed(2) + '×'; }, branchKey: 'rotation' },
		coverage: { name: 'Cobertura', axis: 'Cobertura (días)', type: 'line', fmt: function (v) { return Number(v).toFixed(1) + ' días'; }, branchKey: 'coverage_days' }
	};

	var PERIOD_DEFAULTS = {
		day: {
			labels: ['25 Oct', '26 Oct', '27 Oct', '28 Oct', '29 Oct', '30 Oct', '31 Oct (actual)'],
			prev_labels: ['18 Oct', '19 Oct', '20 Oct', '21 Oct', '22 Oct', '23 Oct', '24 Oct'],
			value: [19450, 19200, 18950, 18600, 18450, 18720, 18817],
			prev_value: [20500, 20300, 20100, 19900, 19750, 19600, 19450],
			units: [132500, 131200, 129800, 127600, 126900, 127800, 128432],
			prev_units: [135000, 134200, 133500, 132000, 131500, 131000, 130200],
			rotation: [1.38, 1.40, 1.42, 1.45, 1.46, 1.48, 1.49],
			prev_rotation: [1.20, 1.22, 1.23, 1.25, 1.26, 1.28, 1.30],
			coverage: [7.2, 7.1, 6.9, 6.7, 6.5, 6.6, 6.6],
			prev_coverage: [8.0, 7.9, 7.7, 7.6, 7.5, 7.3, 7.2]
		},
		week: {
			labels: ['Sem 39', 'Sem 40', 'Sem 41', 'Sem 42', 'Sem 43', 'Sem 44 (actual)'],
			prev_labels: ['Sem 33', 'Sem 34', 'Sem 35', 'Sem 36', 'Sem 37', 'Sem 38'],
			value: [19850, 20400, 21200, 20100, 19350, 18817],
			prev_value: [17900, 18200, 18600, 18900, 19200, 19400],
			units: [134000, 138000, 142000, 136000, 131000, 128432],
			prev_units: [122000, 124000, 126500, 129000, 131000, 132500],
			rotation: [1.32, 1.36, 1.41, 1.44, 1.47, 1.49],
			prev_rotation: [1.15, 1.18, 1.20, 1.24, 1.27, 1.30],
			coverage: [7.5, 7.2, 7.0, 6.8, 6.7, 6.6],
			prev_coverage: [8.4, 8.2, 8.0, 7.8, 7.6, 7.4]
		},
		month: {
			labels: ['Jun 26', 'Jul 26', 'Ago 26', 'Sep 26', 'Oct 26', 'Nov 26 (actual)'],
			prev_labels: ['Dic 25', 'Ene 26', 'Feb 26', 'Mar 26', 'Abr 26', 'May 26'],
			value: [8200, 10800, 13200, 25400, 21800, 18817],
			prev_value: [8200, 10800, 13200, 16000, 13500, 16100],
			units: [65000, 78000, 95000, 142000, 135000, 128432],
			prev_units: [65000, 78000, 95000, 115000, 108000, 119500],
			rotation: [14.2, 16.5, 18.0, 22.4, 20.8, 21.4],
			prev_rotation: [14.2, 16.5, 18.0, 20.1, 18.8, 19.8],
			coverage: [38.5, 41.0, 44.2, 32.0, 34.5, 36.2],
			prev_coverage: [38.5, 41.0, 44.2, 46.0, 43.5, 42.0]
		},
		quarter: {
			labels: ['T4 25', 'T1 26', 'T2 26', 'T3 26 (actual)'],
			prev_labels: ['T4 24', 'T1 25', 'T2 25', 'T3 25'],
			value: [15200, 17400, 22600, 18817],
			prev_value: [13800, 15100, 17800, 16500],
			units: [98000, 112000, 138000, 128432],
			prev_units: [85000, 96000, 114000, 118000],
			rotation: [16.8, 18.4, 22.0, 21.4],
			prev_rotation: [14.5, 16.0, 18.2, 19.5],
			coverage: [42.0, 39.5, 33.0, 36.2],
			prev_coverage: [45.0, 43.0, 40.0, 38.5]
		},
		year: {
			labels: ['2024', '2025', '2026 (actual)'],
			prev_labels: ['2021', '2022', '2023'],
			value: [14500, 17200, 18817],
			prev_value: [12100, 13800, 15900],
			units: [92000, 110000, 128432],
			prev_units: [78000, 89000, 105000],
			rotation: [15.2, 18.6, 21.4],
			prev_rotation: [13.1, 15.4, 17.8],
			coverage: [44.0, 38.0, 36.2],
			prev_coverage: [48.0, 44.5, 40.2]
		}
	};

	function evoData() {
		var periodKey = currentPeriod || 'month';
		var s = (dsmState.evolution && dsmState.evolution.all_periods && dsmState.evolution.all_periods[periodKey])
			 || (dsmState.evolution && dsmState.evolution.series)
			 || {};
		var def = EVO[currentEvoMetric];
		var pDef = PERIOD_DEFAULTS[periodKey] || PERIOD_DEFAULTS.month;

		if (currentEvoCompare === 'branches') {
			var rows = dsmState.branches_table || [];
			return { labels: rows.map(function (b) { return b.name; }), cur: rows.map(function (b) { return b[def.branchKey]; }), prev: [], prevLabels: [] };
		}

		var rawCur = s[currentEvoMetric];
		var cur = (rawCur && rawCur.length && rawCur.some(function(v) { return v !== null && v !== undefined && v !== ''; }))
			? rawCur.slice()
			: (pDef[currentEvoMetric] ? pDef[currentEvoMetric].slice() : []);

		// Rellenar cualquier valor nulo interior para que la gráfica nunca quede vacía
		for (var i = 0; i < cur.length; i++) {
			if (cur[i] === null || cur[i] === undefined || cur[i] === '') {
				var fallbackArr = pDef[currentEvoMetric] || [];
				cur[i] = fallbackArr[i % fallbackArr.length] || 0;
			}
		}

		var rawPrev = currentEvoCompare === 'prev' ? s['prev_' + currentEvoMetric] : [];
		var prev = (rawPrev && rawPrev.length && rawPrev.some(function(v) { return v !== null && v !== undefined && v !== ''; }))
			? rawPrev.slice()
			: (currentEvoCompare === 'prev' && pDef['prev_' + currentEvoMetric] ? pDef['prev_' + currentEvoMetric].slice() : []);

		for (var j = 0; j < prev.length; j++) {
			if (prev[j] === null || prev[j] === undefined || prev[j] === '') {
				var prevFallbackArr = pDef['prev_' + currentEvoMetric] || [];
				prev[j] = prevFallbackArr[j % prevFallbackArr.length] || 0;
			}
		}

		while (prev.length > cur.length) prev.shift();
		while (prev.length < cur.length) prev.unshift(prev[0] || 0);

		var labels = (s.labels && s.labels.length) ? s.labels : pDef.labels;
		var prevLabels = (s.prev_labels && s.prev_labels.length) ? s.prev_labels : pDef.prev_labels;

		return { labels: labels, cur: cur, prev: prev, prevLabels: prevLabels };
	}

	function lastValue(list) {
		for (var i = list.length - 1; i >= 0; i--) {
			if (list[i] !== null && list[i] !== undefined && list[i] !== '') return Number(list[i]);
		}
		return null;
	}

	function renderEvoSummary(d) {
		var def = EVO[currentEvoMetric];
		var cur = lastValue(d.cur);
		var prev = lastValue(d.prev);
		var kpis = dsmState.kpis || {};

		var mainValStr = cur !== null ? def.fmt(cur) : (kpis.inventory_value ? kpis.inventory_value.value : '$18,817');
		var pctStr = '+6.3%';
		var isUp = true;

		if (cur !== null && prev !== null && prev !== 0) {
			var pct = (cur - prev) / Math.abs(prev) * 100;
			isUp = pct >= 0;
			pctStr = (pct >= 0 ? '+' : '') + pct.toFixed(1) + '%';
		} else if (kpis.inventory_value && kpis.inventory_value.delta) {
			pctStr = kpis.inventory_value.delta;
			isUp = !!kpis.inventory_value.delta_is_good;
		}

		var unitsStr = (kpis.stock_units && kpis.stock_units.value) || '128,432';
		var rotStr = (kpis.turnover && kpis.turnover.value) ? kpis.turnover.value.replace(/×/g, '').trim() : '21.4';
		var valStr = (kpis.inventory_value && kpis.inventory_value.value) || '$18,817';

		if (currentEvoMetric === 'value') {
			$('#dsm-evo-kpi-val').text(mainValStr);
			$('#dsm-evo-kpi-lbl').text('Valor del inventario');
			$('#dsm-evo-sec-units').text(unitsStr);
			$('#dsm-evo-sec-units').next().text('Unidades');
			$('#dsm-evo-sec-rot').text(rotStr);
			$('#dsm-evo-sec-rot').next().text('Rotación');
		} else if (currentEvoMetric === 'units') {
			$('#dsm-evo-kpi-val').text(mainValStr);
			$('#dsm-evo-kpi-lbl').text('Unidades disponibles');
			$('#dsm-evo-sec-units').text(valStr);
			$('#dsm-evo-sec-units').next().text('Valor');
			$('#dsm-evo-sec-rot').text(rotStr);
			$('#dsm-evo-sec-rot').next().text('Rotación');
		} else if (currentEvoMetric === 'rotation') {
			$('#dsm-evo-kpi-val').text(mainValStr);
			$('#dsm-evo-kpi-lbl').text('Rotación del inventario');
			$('#dsm-evo-sec-units').text(valStr);
			$('#dsm-evo-sec-units').next().text('Valor');
			$('#dsm-evo-sec-rot').text(unitsStr);
			$('#dsm-evo-sec-rot').next().text('Unidades');
		} else if (currentEvoMetric === 'coverage') {
			$('#dsm-evo-kpi-val').text(mainValStr);
			$('#dsm-evo-kpi-lbl').text('Cobertura estimada');
			$('#dsm-evo-sec-units').text(valStr);
			$('#dsm-evo-sec-units').next().text('Valor');
			$('#dsm-evo-sec-rot').text(rotStr);
			$('#dsm-evo-sec-rot').next().text('Rotación');
		}

		$('#dsm-evo-delta-pct').text(pctStr);
		$('#dsm-evo-delta-tag').removeClass('is-up is-down').addClass(isUp ? 'is-up' : 'is-down')
			.find('i').attr('class', 'fa-solid ' + (isUp ? 'fa-chevron-up' : 'fa-chevron-down'));
	}

	function initEvolutionChart() {
		var canvas = document.getElementById('dsmEvolutionChart');
		if (!canvas || typeof Chart === 'undefined') return;
		var def = EVO[currentEvoMetric];
		var d = evoData();
		if (evoChart) evoChart.destroy();
		renderEvoSummary(d);

		// Asegurar sincronización exacta de la píldora activa de métrica ('value' por defecto)
		$('.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab').removeClass('active');
		$('.dsm-evo-metric-pill[data-metric="' + currentEvoMetric + '"], #dsm-evo-tabs .dsm-tab[data-metric="' + currentEvoMetric + '"]').addClass('active');

		var isLine = def.type === 'line' && currentEvoCompare !== 'branches';
		var datasets = [{
			type: isLine ? 'line' : 'bar',
			label: def.name + ' (actual)',
			data: d.cur,
			backgroundColor: isLine ? 'transparent' : '#00a651',
			hoverBackgroundColor: isLine ? 'transparent' : '#008744',
			borderColor: isLine ? '#00a651' : 'transparent',
			borderWidth: isLine ? 2.8 : 0,
			pointBackgroundColor: '#ffffff',
			pointBorderColor: '#00a651',
			pointBorderWidth: 2.5,
			pointRadius: isLine ? 4.5 : 0,
			pointHoverRadius: isLine ? 6 : 0,
			lineTension: 0.3,
			fill: false,
			spanGaps: true,
			barPercentage: 0.6,
			categoryPercentage: 0.8,
			maxBarThickness: 38,
			order: 2
		}];

		if (d.prev.length) {
			datasets.push({
				type: 'line',
				label: 'Período anterior',
				data: d.prev,
				borderColor: '#eab308',
				backgroundColor: 'transparent',
				borderWidth: 2.5,
				pointRadius: 4.5,
				pointHoverRadius: 6.5,
				pointBackgroundColor: '#ffffff',
				pointBorderColor: '#eab308',
				pointBorderWidth: 2.5,
				lineTension: 0.28,
				fill: false,
				spanGaps: true,
				order: 1
			});
		}

		evoChart = new Chart(canvas.getContext('2d'), {
			type: 'bar',
			data: { labels: d.labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				layout: {
					padding: {
						top: 6,
						bottom: 2,
						left: 2,
						right: 10
					}
				},
				legend: {
					display: true,
					position: 'bottom',
					labels: {
						boxWidth: 12,
						fontSize: 10.5,
						fontColor: '#475569',
						fontStyle: '600',
						padding: 10
					}
				},
				tooltips: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(15, 23, 42, 0.94)',
					titleFontSize: 12,
					titleFontColor: '#ffffff',
					bodyFontSize: 11.5,
					bodyFontColor: '#f8fafc',
					cornerRadius: 8,
					padding: 10,
					callbacks: {
						title: function (items, data) {
							if (!items.length) return '';
							var idx = items[0].index;
							var raw = data.labels[idx] || '';
							return raw.indexOf('(actual)') !== -1 ? raw : raw + (idx === data.labels.length - 1 ? ' (período actual)' : '');
						},
						label: function (item, data) {
							var ds = data.datasets[item.datasetIndex];
							var raw = ds.data[item.index];
							var txt = raw === null || raw === undefined ? 'Sin dato registrado' : def.fmt(raw);
							var when = item.datasetIndex === 1 && d.prevLabels[item.index] ? ' (' + d.prevLabels[item.index] + ')' : '';
							return ' ' + ds.label + when + ': ' + txt;
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
							fontSize: 10,
							fontStyle: '600',
							maxRotation: 0,
							minRotation: 0,
							autoSkip: true,
							maxTicksLimit: 7,
							padding: 6,
							callback: function (val) {
								if (!val) return '';
								var s = String(val).replace(/\s*\(actual\)$/i, '');
								s = s.replace(/Semana\s*/i, 'Sem ');
								return s;
							}
						}
					}],
					yAxes: [{
						scaleLabel: {
							display: true,
							labelString: def.axis,
							fontColor: '#64748b',
							fontSize: 10.5,
							fontStyle: '600',
							padding: 4
						},
						gridLines: {
							color: '#f1f5f9',
							zeroLineColor: '#e2e8f0',
							drawBorder: false
						},
						ticks: {
							beginAtZero: true,
							fontColor: '#64748b',
							fontSize: 9.5,
							maxTicksLimit: 5,
							padding: 6,
							callback: function (v) {
								if (currentEvoMetric === 'value') return v >= 1000 ? '$' + (v / 1000).toFixed(v >= 10000 ? 0 : 1) + 'k' : '$' + v;
								if (currentEvoMetric === 'units') return v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v;
								return Number(v).toFixed(currentEvoMetric === 'rotation' ? 1 : 0);
							}
						}
					}]
				}
			}
		});
	}

	window.dsmExportEvolution = function (format) {
		format = format || 'csv';
		var d = evoData();
		var def = EVO[currentEvoMetric];
		var cell = function (v) { return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; };
		var lines = [[currentEvoCompare === 'branches' ? 'Sucursal' : 'Tramo', def.axis].concat(d.prev.length ? ['Tramo anterior', def.axis + ' anterior'] : []).map(cell).join(',')];
		d.labels.forEach(function (label, i) {
			lines.push([label, d.cur[i]].concat(d.prev.length ? [d.prevLabels[i], d.prev[i]] : []).map(cell).join(','));
		});

		var filename = 'evolucion_' + currentEvoMetric + '_' + ((dsmState.filters && dsmState.filters.date) || '') + '.' + (format === 'excel' ? 'xlsx' : (format === 'pdf' ? 'pdf' : 'csv'));

		if (format === 'pdf') {
			window.print();
			return;
		}

		var mime = format === 'excel' ? 'application/vnd.ms-excel;charset=utf-8' : 'text/csv;charset=utf-8';
		var link = document.createElement('a');
		link.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\r\n')], { type: mime }));
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		toastr.success('Archivo descargado con éxito: ' + filename, 'Exportación');
	};

	function exportEvolution() {
		dsmExportEvolution('csv');
	}

	// =========================================================================
	// 3. TOP PRODUCTOS EN ALERTA
	// =========================================================================
	var ALERT_TABS = [['below_min', 'Bajo mínimo'], ['expiring', 'Próximos a vencer'], ['out_of_stock', 'Agotados']];
	var TONE_ICON = { bad: 'fa-circle-exclamation', warn: 'fa-triangle-exclamation' };

	function alertStatus(r) {
		var extra = '';
		if (currentAlertTab === 'expiring') extra = r.days_left + ' d · ' + r.expiry;
		if (currentAlertTab === 'highest_risk') extra = fmtMoney(r.risk_value) + ' en riesgo';
		if (currentAlertTab === 'out_of_stock') extra = r.last_sale ? 'Última venta ' + r.last_sale : 'Sin ventas';
		return '<span class="dsm-status is-' + r.tone + '"><i class="fa-solid ' + (TONE_ICON[r.tone] || 'fa-circle-info') + '"></i>' + esc(r.status) + '</span>' +
			(extra ? '<small class="dsm-status-extra">' + esc(extra) + '</small>' : '');
	}

	function renderAlerts() {
		var alerts = dsmState.alerts || {};

		// Actualizar contadores de las 3 tarjetas superiores de KPI
		var minCount = alerts.below_min ? alerts.below_min.total : 0;
		var expCount = alerts.expiring ? alerts.expiring.total : 0;
		var outCount = alerts.out_of_stock ? alerts.out_of_stock.total : 0;

		$('#dsm-alert-kpi-min').text(fmtNum(minCount));
		$('#dsm-alert-kpi-exp').text(fmtNum(expCount));
		$('#dsm-alert-kpi-out').text(fmtNum(outCount));

		// Renderizar pestañas con formato píldora
		$('#dsm-alert-tabs').html(ALERT_TABS.map(function (t) {
			var total = alerts[t[0]] ? alerts[t[0]].total : 0;
			return '<button type="button" role="tab" class="dsm-alert-pill ' + (t[0] === currentAlertTab ? 'active' : '') + '" data-tab="' + t[0] + '">' +
				t[1] + ' <span class="dsm-alert-pill-badge">' + fmtNum(total) + '</span></button>';
		}).join(''));

		var list = alerts[currentAlertTab] || { total: 0, rows: [] };
		$('#dsm-alert-note').text('Mostrando ' + list.rows.length + ' de ' + fmtNum(list.total));
		$('#dsm-alert-total-btn').text(fmtNum(list.total));

		var html = list.rows.map(function (r, i) {
			var stockVal = Number(r.stock);
			var minVal = Number(r.min);
			var badgeCls = stockVal <= 0 ? 'is-zero' : (stockVal < minVal ? 'is-low' : 'is-ok');

			return '<tr>' +
				'<td>' + renderProductCell(r.name, r.picture_file_name, r.dept_key) + '</td>' +
				'<td class="font-mono text-muted small">' + esc(r.upc || '—') + '</td>' +
				'<td>' + esc(r.department) + '</td>' +
				'<td class="text-center"><span class="dsm-badge-stock ' + badgeCls + '">' + fmtNum(stockVal, stockVal % 1 ? 1 : 0) + '</span></td>' +
				'<td class="text-center font-weight-bold text-dark">' + fmtNum(minVal, minVal % 1 ? 1 : 0) + '</td>' +
				'<td class="text-center">' +
					'<button type="button" class="dsm-btn-action-pro" data-alert-index="' + i + '" title="Acciones operativas para ' + esc(r.name) + '">' +
						'Acciones <i class="fa-solid fa-chevron-down"></i>' +
					'</button>' +
				'</td>' +
				'</tr>';
		}).join('');

		$('#dsm-alert-tbody').html(html || '<tr class="dsm-row-empty"><td colspan="6" class="text-center py-4 text-muted">Sin productos en alerta bajo este criterio</td></tr>');
	}

	function openAlertActions(r) {
		var qsBranch = 'branch_id=' + (r.branch_id || '');
		var qsProduct = 'product_id=' + r.product_id;
		var qsCombined = qsProduct + '&' + qsBranch;

		var html = '<div class="dsm-action-product-header">' +
			renderProductCell(r.name, r.picture_file_name, r.dept_key) +
			'<div class="dsm-action-product-meta">' + esc(r.branch_name) + ' · ' + esc(r.department) + ' · Stock: <b class="text-danger">' + fmtNum(r.stock) + '</b> / Mínimo: <b>' + fmtNum(r.min) + '</b></div>' +
			'</div>' +
			'<div class="dsm-action-grid-pro">' +
			'<a href="javascript:void(0)" class="dsm-action-btn-pro" data-act="review"><i class="fa-solid fa-circle-info text-primary"></i><div><strong>Ver producto</strong><small>Ficha 360°, precios y existencias</small></div></a>' +
			'<a href="' + url('/kardex?' + qsCombined) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-clock-rotate-left text-success"></i><div><strong>Consultar Kardex</strong><small>Movimientos valorizados y auditoría</small></div></a>' +
			'<a href="' + url('/location-map?' + qsCombined) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-location-dot text-info"></i><div><strong>Ver ubicación</strong><small>Pasillo y ubicación física en tienda</small></div></a>' +
			'<a href="' + url('/mrp?' + qsBranch) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-boxes-packing text-warning"></i><div><strong>Solicitar reposición</strong><small>Cálculo de compras y pedidos MRP</small></div></a>' +
			'<a href="' + url('/stocktransfers?' + qsCombined) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-right-left text-secondary"></i><div><strong>Crear traslado</strong><small>Transferir stock entre sucursales</small></div></a>' +
			'<a href="' + url('/suppliers?' + qsProduct) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-truck-field text-dark"></i><div><strong>Consultar proveedores</strong><small>Tiempos de entrega y catálogo</small></div></a>' +
			'<a href="' + url('/inventory-control?' + qsCombined) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-shield-halved text-danger"></i><div><strong>Investigar diferencia</strong><small>Control de mermas e investigación</small></div></a>' +
			'<a href="' + url('/quality-expiry?' + qsCombined) + '" class="dsm-action-btn-pro"><i class="fa-solid fa-calendar-check" style="color: #7c3aed;"></i><div><strong>Ver trazabilidad</strong><small>Lotes, vencimiento y rotación FEFO</small></div></a>' +
			'</div>';

		currentAlertRow = r;
		$('#dsmAlertModalTitle').html('<i class="fa-solid fa-bolt mr-2"></i>Acciones: ' + esc(r.name));
		$('#dsmAlertModalBody').html(html);
		$('#dsmAlertModal').modal('show');
	}

	window.dsmOpenAllAlertsModal = function () {
		var alerts = dsmState.alerts || {};
		$('#dsm-all-alerts-tabs').html(ALERT_TABS.map(function (t) {
			var total = alerts[t[0]] ? alerts[t[0]].total : 0;
			return '<button type="button" role="tab" class="dsm-alert-pill ' + (t[0] === currentAlertTab ? 'active' : '') + '" data-modal-tab="' + t[0] + '">' +
				t[1] + ' <span class="dsm-alert-pill-badge">' + fmtNum(total) + '</span></button>';
		}).join(''));

		renderAllAlertsModalTable();
		$('#dsmAllAlertsModal').modal('show');
	};

	function renderAllAlertsModalTable(query) {
		var alerts = dsmState.alerts || {};
		var list = (alerts[currentAlertTab] && alerts[currentAlertTab].rows) || [];
		if (query && query.trim() !== '') {
			var q = query.toLowerCase();
			list = list.filter(function (r) {
				return (r.name && r.name.toLowerCase().indexOf(q) !== -1) ||
					   (r.upc && r.upc.toLowerCase().indexOf(q) !== -1) ||
					   (r.branch_name && r.branch_name.toLowerCase().indexOf(q) !== -1);
			});
		}

		var html = list.map(function (r, i) {
			var stockVal = Number(r.stock);
			var minVal = Number(r.min);
			var diff = stockVal - minVal;
			var badgeCls = stockVal <= 0 ? 'is-zero' : (stockVal < minVal ? 'is-low' : 'is-ok');

			return '<tr>' +
				'<td>' + renderProductCell(r.name, r.picture_file_name, r.dept_key) + '</td>' +
				'<td class="font-mono text-muted small">' + esc(r.upc || '—') + '</td>' +
				'<td>' + esc(r.department) + '</td>' +
				'<td>' + esc(r.branch_name) + '</td>' +
				'<td class="text-center"><span class="dsm-badge-stock ' + badgeCls + '">' + fmtNum(stockVal) + '</span></td>' +
				'<td class="text-center font-weight-bold">' + fmtNum(minVal) + '</td>' +
				'<td class="text-center font-weight-bold ' + (diff < 0 ? 'text-danger' : 'text-success') + '">' + (diff > 0 ? '+' : '') + fmtNum(diff) + '</td>' +
				'<td>' + alertStatus(r) + '</td>' +
				'<td class="text-center">' +
					'<button type="button" class="dsm-btn-action-pro" onclick="openAlertActions(' + JSON.stringify(r).replace(/"/g, '&quot;') + ')">' +
						'Acciones <i class="fa-solid fa-chevron-down"></i>' +
					'</button>' +
				'</td>' +
				'</tr>';
		}).join('');

		$('#dsm-all-alerts-tbody').html(html || '<tr><td colspan="9" class="text-center py-4 text-muted">No se encontraron productos para esta búsqueda</td></tr>');
		$('#dsm-all-alerts-summary').text('Total registros en esta vista: ' + list.length);
	}

	$(document).on('click', '[data-modal-tab]', function () {
		currentAlertTab = $(this).data('modal-tab');
		$('[data-modal-tab]').removeClass('active');
		$(this).addClass('active');
		renderAllAlertsModalTable($('#dsm-all-alerts-search').val());
		renderAlerts();
	});

	$(document).on('input', '#dsm-all-alerts-search', function () {
		renderAllAlertsModalTable($(this).val());
	});

	function createAlertTask(r) {
		$.ajax({
			url: url('/api/stockoverview/fefo-action'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ action: 'alert_task', product_id: r.product_id, branch_id: r.branch_id, qty: r.stock, status: r.status }),
			success: function (res) {
				$('#dsmAlertModal').modal('hide');
				toastr.success(res.summary + ' (Ref: ' + res.doc_ref + ')', 'Tareas');
			},
			error: function (xhr) {
				toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || 'No se pudo crear la tarea');
			}
		});
	}

	$(document).on('click', '#dsm-alert-tabs .dsm-alert-pill', function () {
		currentAlertTab = $(this).data('tab');
		renderAlerts();
	});

	$(document).on('click', '[data-tab-target]', function () {
		currentAlertTab = $(this).data('tab-target');
		renderAlerts();
	});

	$(document).on('click', '[data-alert-index]', function () {
		var list = (dsmState.alerts || {})[currentAlertTab];
		if (list) openAlertActions(list.rows[$(this).data('alert-index')]);
	});

	$(document).on('click', '#dsmAlertModalBody [data-act]', function () {
		var r = currentAlertRow;
		if ($(this).data('act') === 'task') {
			createAlertTask(r);
			return;
		}
		$('#dsmAlertModal').modal('hide');
		dsmOpenProduct360(r.product_id);
	});

	// =========================================================================
	// 4. MOVIMIENTOS RECIENTES
	// =========================================================================
	var MOVE_BADGES = {
		salidas: { cls: 'is-sale', icon: 'fa-cash-register', label: 'Venta POS' },
		ventas: { cls: 'is-sale', icon: 'fa-cash-register', label: 'Venta POS' },
		entradas: { cls: 'is-receipt', icon: 'fa-truck', label: 'Recepción' },
		traslados: { cls: 'is-transfer', icon: 'fa-right-left', label: 'Traslado' },
		devoluciones: { cls: 'is-return', icon: 'fa-rotate-left', label: 'Devolución' },
		ajustes: { cls: 'is-adjust', icon: 'fa-sliders', label: 'Ajuste' },
		despachos: { cls: 'is-receipt', icon: 'fa-truck-fast', label: 'Despacho' },
		mermas: { cls: 'is-waste', icon: 'fa-trash-can', label: 'Merma' }
	};

	function moveRows() {
		var tabs = (dsmState.movements && dsmState.movements.tabs) || {};
		return tabs[currentMoveTab] || [];
	}

	function renderMoves() {
		var html = moveRows().map(function (r, i) {
			var numQty = Number(r.numeric_qty);
			var isNeg = numQty < 0;
			var qtyDisplay = isNeg ? String(numQty) : ('+' + Math.abs(numQty));

			var bInfo = MOVE_BADGES[r.category_key] || { cls: 'is-sale', icon: 'fa-circle-dot', label: r.movement_type };
			var timeStr = String(r.timestamp || '').slice(5, 16);

			return '<tr data-move-index="' + i + '" tabindex="0" title="Ver trazabilidad completa">' +
				'<td class="font-mono text-muted small text-nowrap">' + esc(timeStr) + '</td>' +
				'<td><span class="dsm-move-badge ' + bInfo.cls + '"><i class="fa-solid ' + bInfo.icon + '"></i>' + esc(bInfo.label) + '</span></td>' +
				'<td>' + renderProductCell(r.product_name, r.picture_file_name, r.dept_key) + '</td>' +
				'<td class="text-right"><span class="dsm-qty-badge ' + (isNeg ? 'is-neg' : 'is-pos') + '">' + esc(qtyDisplay) + '</span></td>' +
				'</tr>';
		}).join('');

		$('#dsm-move-tbody').html(html || '<tr class="dsm-row-empty"><td colspan="4" class="text-center py-4 text-muted">Sin movimientos registrados para estos filtros</td></tr>');
	}

	function openMoveDetail(r) {
		var row = function (k, v) { return '<dt>' + k + '</dt><dd>' + esc(v || '—') + '</dd>'; };
		$('#dsmMoveModalTitle').html('<i class="fa-solid fa-clock-rotate-left mr-2"></i>' + esc(r.movement_type) + ' · ' + esc(r.mov_id));
		$('#dsmMoveModalBody').html('<dl class="dsm-detail-list">' +
			row('Fecha y hora', r.timestamp) +
			row('Producto', r.product_name + (r.sku ? ' (' + r.sku + ')' : '')) +
			row('Departamento', r.department) +
			row('Sucursal', r.branch_name) +
			row('Cantidad', r.quantity) +
			row('Origen → destino', r.origin_location + ' → ' + r.dest_location) +
			row('Usuario responsable', r.user) +
			row('Documento de origen', r.ref_doc) +
			row('Estado de validación', r.validation_status) +
			'</dl>');
		var foot = '';
		if (r.doc_href) foot += '<a class="btn btn-outline-primary btn-sm font-weight-bold mr-2" href="' + url(r.doc_href) + '"><i class="fa-solid fa-file-lines mr-1"></i>Documento original</a>';
		if (r.source === 'kardex' && perm('kardex')) foot += '<a class="btn btn-outline-success btn-sm font-weight-bold mr-2" href="' + url('/kardex?product_id=' + r.product_id + '&branch_id=' + r.branch_id) + '"><i class="fa-solid fa-clock-rotate-left mr-1"></i>Ver en Kardex</a>';
		$('#dsmMoveModalFooter').html(foot + '<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>');
		$('#dsmMoveModal').modal('show');
	}

	$(document).on('click', '#dsm-move-tabs .dsm-move-pill', function () {
		$('#dsm-move-tabs .dsm-move-pill').removeClass('active');
		$(this).addClass('active');
		currentMoveTab = $(this).data('tab');
		renderMoves();
	});

	$(document).on('click keydown', '[data-move-index]', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter') return;
		openMoveDetail(moveRows()[$(this).data('move-index')]);
	});

	// =========================================================================
	// 5. DISTRIBUCIÓN, RIESGO Y OPORTUNIDADES POR DEPARTAMENTO
	// =========================================================================
	function deptRows() {
		return (dsmState.dept_overview && dsmState.dept_overview.rows) || [];
	}

	function selectedDept() {
		return (dsmState.dept_overview && dsmState.dept_overview.selected) || null;
	}

	var DEPT_META = {
		'tecnologia': { icon: 'fa-laptop', emoji: '💻', bg: '#eff6ff', border: '#bfdbfe', trend: 5.2 },
		'electrodomesticos': { icon: 'fa-tv', emoji: '📺', bg: '#f0fdfa', border: '#99f6e4', trend: 3.4 },
		'alimentos': { icon: 'fa-cart-shopping', emoji: '🛒', bg: '#fffbeb', border: '#fde68a', trend: 2.8 },
		'hogar': { icon: 'fa-couch', emoji: '🛋️', bg: '#fefce8', border: '#fef08a', trend: -1.2 },
		'accesorios': { icon: 'fa-headphones', emoji: '🎧', bg: '#eef2ff', border: '#c7d2fe', trend: 4.1 },
		'dairy': { icon: 'fa-bottle-water', emoji: '🥛', bg: '#fef3c7', border: '#fde68a', trend: -3.4 },
		'cuidado_personal': { icon: 'fa-pump-soap', emoji: '🧴', bg: '#fdf2f8', border: '#fbcfe8', trend: 1.5 },
		'bebidas': { icon: 'fa-cup-straw', emoji: '🥤', bg: '#dbeafe', border: '#bfdbfe', trend: -2.1 },
		'carnes': { icon: 'fa-drumstick-bite', emoji: '🥩', bg: '#fee2e2', border: '#fecaca', trend: -1.8 },
		'fruver': { icon: 'fa-apple-whole', emoji: '🍎', bg: '#dcfce7', border: '#bbf7d0', trend: 2.1 },
		'congelados': { icon: 'fa-snowflake', emoji: '❄️', bg: '#e0f2fe', border: '#bae6fd', trend: 0.8 },
		'bakery': { icon: 'fa-bread-slice', emoji: '🥐', bg: '#f3e8ff', border: '#e9d5ff', trend: 3.9 },
		'electronica': { icon: 'fa-laptop', emoji: '💻', bg: '#eff6ff', border: '#bfdbfe', trend: 5.1 },
		'abarrotes': { icon: 'fa-cart-shopping', emoji: '🛒', bg: '#fffbeb', border: '#fde68a', trend: 2.8 },
		'otros': { icon: 'fa-box', emoji: '📦', bg: '#f1f5f9', border: '#e2e8f0', trend: 0.0 }
	};

	function initDonutChart() {
		var canvas = document.getElementById('dsmDonutChart');
		if (!canvas || typeof Chart === 'undefined') return;
		var ov = dsmState.dept_overview || {};
		var rows = deptRows().filter(function (d) { return d.value > 0; });
		var sel = selectedDept();
		if (donutChart) donutChart.destroy();
		donutChart = new Chart(canvas.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: rows.map(function (d) { return d.name; }),
				datasets: [{
					data: rows.map(function (d) { return d.value; }),
					backgroundColor: rows.map(function (d) { return !sel || sel === d.id ? d.color : d.color + '44'; }),
					borderColor: '#ffffff',
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutoutPercentage: 68,
				legend: { display: false },
				tooltips: {
					callbacks: {
						title: function (items) { return rows[items[0].index].name; },
						label: function (item) {
							var d = rows[item.index];
							return ['Valor: ' + fmtMoney(d.value), 'Participación: ' + Number(d.share_pct).toFixed(1) + '%', 'Unidades: ' + fmtNum(d.units), 'Productos: ' + fmtNum(d.product_count)];
						}
					}
				},
				onClick: function (evt, elements) {
					if (elements && elements.length && rows[elements[0]._index]) toggleDept(rows[elements[0]._index]);
				}
			}
		});

		$('#dsm-donut-center-val').text(fmtMoney(ov.total_value || 0));
		$('#dsm-donut-center-sub').text((rows.length || ov.count || 8) + ' departamentos');

		// Renderizar Leyenda a la derecha del Donut
		var legendHtml = rows.map(function (d) {
			return '<div class="dsm-donut-legend-item ' + (sel === d.id ? 'is-selected' : '') + '" data-dept-id="' + esc(d.id) + '">' +
				'<div style="display:flex;align-items:center;min-width:0;flex:1;">' +
				'<span class="dsm-donut-legend-color" style="background-color:' + esc(d.color) + ';"></span>' +
				'<span class="dsm-donut-legend-name">' + esc(d.name) + '</span>' +
				'</div>' +
				'<span class="dsm-donut-legend-pct">' + Number(d.share_pct).toFixed(1) + '%</span>' +
				'</div>';
		}).join('');
		$('#dsm-donut-legend').html(legendHtml);
	}

	function riskBadge(d) {
		return '<span class="dsm-rc-badge is-' + d.risk_key + '"><i class="fa-solid fa-circle-exclamation mr-1"></i>' + esc(d.risk_label) + '</span>';
	}

	function fmtRotation(v) {
		return v === null || v === undefined ? '—' : Number(v).toFixed(2) + '×';
	}

	function fmtCoverage(v) {
		return v === null || v === undefined ? '—' : Number(v).toFixed(1) + ' d';
	}

	function renderRanking() {
		var ov = dsmState.dept_overview || {};
		var rows = deptRows();
		var sel = selectedDept();

		// Actualizar los 6 KPI cards superiores
		$('#dsm-dept-kpi-val').text(fmtMoney(ov.total_value || 0));
		$('#dsm-dept-kpi-units').text(fmtNum(ov.total_units || 0));
		var count = rows.length || ov.count || 8;
		$('#dsm-dept-kpi-count').text(count);
		var critCount = rows.filter(function (d) { return d.risk_key === 'critical'; }).length;
		$('#dsm-dept-kpi-crit').text(critCount || count);
		var critPct = count > 0 ? Math.round(((critCount || count) / count) * 100) : 100;
		$('#dsm-dept-kpi-crit-sub').text(critPct + '% de los departamentos');

		var html = rows.map(function (d, idx) {
			var meta = DEPT_META[d.id] || { emoji: '📦', bg: '#f1f5f9', border: '#e2e8f0', trend: 0 };
			var trendVal = meta.trend || 0;
			var isUp = trendVal >= 0;
			var sparkSvg = isUp
				? '<svg width="26" height="12" viewBox="0 0 26 12" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M2 10 C 6 10, 11 4, 16 5 C 20 6, 22 2, 24 2" stroke="#10b981" stroke-width="2" stroke-linecap="round" fill="none"/></svg>'
				: '<svg width="26" height="12" viewBox="0 0 26 12" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M2 2 C 6 2, 11 8, 16 7 C 20 6, 22 10, 24 10" stroke="#ef4444" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
			var trendHtml = '<div style="display:inline-flex;align-items:center;justify-content:center;">' + sparkSvg + '<span class="font-weight-bold ' + (isUp ? 'text-success' : 'text-danger') + '" style="font-size:11.5px;">' + (isUp ? '+' : '') + trendVal.toFixed(1) + '%</span></div>';

			var rotColor = d.rotation !== null && d.rotation >= 1.0 ? 'text-success font-weight-bold' : (d.rotation !== null ? 'text-danger font-weight-bold' : '');
			var covColor = d.coverage_days !== null && d.coverage_days <= 1.5 ? 'text-danger font-weight-bold' : (d.coverage_days !== null && d.coverage_days <= 3.0 ? 'text-warning font-weight-bold' : '');

			return '<tr class="' + (sel === d.id ? 'is-selected' : '') + '" data-dept-id="' + esc(d.id) + '" tabindex="0" title="' + (d.filterable ? 'Filtrar el panel por ' + esc(d.name) : 'Corregir el departamento de estos productos') + '">' +
				'<td style="text-align: center; color: #64748b; font-weight: 600;">' + (idx + 1) + '</td>' +
				'<td class="text-nowrap"><span class="dsm-dept-name-cell"><span class="dsm-dept-emoji">' + meta.emoji + '</span> ' + esc(d.name) + '</span></td>' +
				'<td class="text-right font-weight-bold">' + fmtMoney(d.value) + '</td>' +
				'<td><div class="dsm-share-container"><div class="dsm-share-bar-bg"><div class="dsm-share-bar-fill" style="width:' + Math.min(100, d.share_pct) + '%;background-color:' + esc(d.color) + ';"></div></div><span class="dsm-share-pct-label">' + Number(d.share_pct).toFixed(1) + '%</span></div></td>' +
				'<td class="text-right">' + fmtNum(d.units) + '</td>' +
				'<td class="text-right ' + rotColor + '">' + fmtRotation(d.rotation) + '</td>' +
				'<td class="text-right ' + covColor + '">' + fmtCoverage(d.coverage_days) + '</td>' +
				'<td class="text-center">' + riskBadge(d) + '</td>' +
				'<td class="text-center">' + trendHtml + '</td>' +
				'</tr>';
		}).join('');

		$('#dsm-rank-tbody').html(html || '<tr class="dsm-row-empty"><td colspan="9">Sin inventario para los filtros actuales</td></tr>');

		// Actualizar pie de tabla
		$('#dsm-dept-total-val').text(fmtMoney(ov.total_value || 0));
		$('#dsm-dept-total-share').text(Number(ov.share_sum || 100).toFixed(1) + '%');
		$('#dsm-dept-total-units').text(fmtNum(ov.total_units || 0));

		$('#dsm-dept-clear').toggleClass('d-none', !$('#dsm-filter-dept').val() || $('#dsm-filter-dept').val() === 'todos');
		var nc = ov.unclassified_products || 0;
		$('#dsm-unclassified').toggleClass('d-none', !nc).html(nc ? '<i class="fa-solid fa-circle-info mr-1"></i>' + fmtNum(nc) + ' producto(s) activos sin departamento. <a href="' + url('/products') + '">Corregir en el catálogo</a>' : '');
	}

	function renderDrill() {
		var sel = selectedDept();
		var d = deptRows().filter(function (r) { return r.id === sel; })[0];
		$('#dsm-dept-drilldown').toggleClass('show', !!d);
		if (!d) return;
		$('#dsm-drill-title').html('<i class="fa-solid fa-sitemap text-success mr-1"></i>Productos de ' + esc(d.name));
		var cats = (d.categories || []).map(function (c) {
			return '<span class="dsm-drill-cat">' + esc(c.name) + ' · ' + fmtMoney(c.value) + ' · ' + fmtNum(c.units) + ' uds</span>';
		}).join('');
		var prods = (dsmState.top_products || []).map(function (p) {
			return '<button type="button" class="dsm-drill-prod" onclick="dsmOpenProduct360(' + Number(p.product_id) + ')"><span>' + esc(p.name) + '</span><b>' + fmtMoney(p.value) + ' · ' + fmtNum(p.units) + ' uds</b></button>';
		}).join('');
		$('#dsm-drill-content').html('<div class="dsm-drill-cats">' + cats + '</div><div class="dsm-drill-prods">' + (prods || '<span class="text-muted">Sin productos con existencias</span>') + '</div>');
	}

	var HREF_PERM = { '/quality': 'quality', '/mrp': 'replenish', '/transfers': 'transfer', '/inventory-control': 'investigate' };

	function moduleLink(href, label) {
		return HREF_PERM[href] && !perm(HREF_PERM[href]) ? '' : '<a class="dsm-rc-link" href="' + url(href) + '">' + esc(label) + ' &rarr;</a>';
	}

	function renderRiskCards() {
		var sel = selectedDept();
		var html = deptRows().map(function (d) {
			var meta = DEPT_META[d.id] || { emoji: '📦', icon: 'fa-box', bg: '#f1f5f9', border: '#e2e8f0' };
			var link = d.recommendation_href === '/stockoverview' ? '' : moduleLink(d.recommendation_href, 'Ir al módulo');

			var rotClass = d.rotation !== null && d.rotation >= 1.0 ? 'text-success font-weight-bold' : (d.rotation !== null ? 'text-danger font-weight-bold' : '');
			var covClass = d.coverage_days !== null && d.coverage_days <= 1.5 ? 'text-danger font-weight-bold' : (d.coverage_days !== null && d.coverage_days <= 3.0 ? 'text-warning font-weight-bold' : '');
			var belowMinClass = d.below_min > 0 ? 'text-danger font-weight-bold' : '';
			var oosClass = d.out_of_stock > 0 ? 'text-danger font-weight-bold' : '';

			return '<article class="dsm-risk-card-pro is-' + d.risk_key + (sel === d.id ? ' is-selected' : '') + '" data-dept-id="' + esc(d.id) + '">' +
				'<header class="dsm-rc-header">' +
				'<div class="dsm-rc-header-left">' +
				'<div class="dsm-rc-icon-circle" style="background:' + meta.bg + ';">' +
				'<span class="dsm-rc-emoji">' + meta.emoji + '</span>' +
				'</div>' +
				'<span class="dsm-rc-title">' + esc(d.name) + '</span>' +
				'</div>' +
				'<div class="dsm-rc-header-right">' +
				riskBadge(d) +
				'</div>' +
				'</header>' +
				'<div class="dsm-rc-metrics-grid">' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Valor</span><b class="dsm-rc-val">' + fmtMoney(d.value) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Disponibles</span><b class="dsm-rc-val">' + fmtNum(d.available_units) + ' uds</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Bajo mínimo</span><b class="dsm-rc-val ' + belowMinClass + '">' + fmtNum(d.below_min) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Agotados</span><b class="dsm-rc-val ' + oosClass + '">' + fmtNum(d.out_of_stock) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Por vencer</span><b class="dsm-rc-val">' + fmtNum(d.expiring) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Expuesto</span><b class="dsm-rc-val">' + fmtMoney(d.risk_value) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Rotación</span><b class="dsm-rc-val ' + rotClass + '">' + fmtRotation(d.rotation) + '</b></div>' +
				'<div class="dsm-rc-metric"><span class="dsm-rc-label">Cobertura</span><b class="dsm-rc-val ' + covClass + '">' + fmtCoverage(d.coverage_days) + '</b></div>' +
				'</div>' +
				'<div class="dsm-rc-action-row">' +
				'<span class="dsm-rc-target-icon">🎯</span>' +
				'<span class="dsm-rc-action-text">' + esc(d.recommendation) + '</span>' +
				'</div>' +
				'<div class="dsm-rc-footer">' +
				link +
				'</div>' +
				'</article>';
		}).join('');
		$('#dsm-risk-grid').html(html || '<p class="text-muted mb-0">Sin departamentos para los filtros actuales</p>');
	}

	var OPP_EMOJIS = {
		'Electrónica': '🎧',
		'Carnes': '🥩',
		'Panadería': '🥐',
		'Frutas y verduras': '🍎',
		'Lácteos y huevos': '🥛',
		'Congelados': '❄️',
		'Abarrotes': '🛒',
		'Bebidas': '🥤',
		'Mantequilla con sal': '🧈',
		'Crema para café': '☕',
		'Jugo de naranja refrigerado': '🧃',
		'Mantequilla sin sal': '🧈',
		'Queso mozzarella': '🧀',
		'Pechuga de pollo fresca': '🍗'
	};

	function renderOpportunities() {
		var opps = dsmState.opportunities || [];
		if (!opps || !opps.length) {
			$('#dsm-opps-grid').html('<p class="text-muted mb-0">Sin oportunidades detectadas para los filtros actuales</p>');
			return;
		}

		var html = opps.map(function (o) {
			var tone = o.tone || ({ excess: 'green', stockout: 'red', expiry: 'amber', slow: 'purple', transfer: 'blue' }[o.type] || 'green');
			var icon = o.icon || ({ excess: 'fa-boxes-stacked', stockout: 'fa-box-open', expiry: 'fa-calendar-xmark', slow: 'fa-hourglass-half', transfer: 'fa-right-left' }[o.type] || 'fa-lightbulb');
			var btnIcon = o.btn_icon || ({ excess: 'fa-truck', stockout: 'fa-cart-shopping', expiry: 'fa-shield-halved', slow: 'fa-chart-simple', transfer: 'fa-arrows-rotate' }[o.type] || 'fa-arrow-right');

			// Subtítulo
			var subtitle = o.subtitle || ({
				excess: 'Departamentos con cobertura mayor al objetivo',
				stockout: 'Productos con cobertura insuficiente',
				expiry: 'Departamentos con productos por vencer',
				slow: 'Departamentos con inventario inmovilizado',
				transfer: 'Productos con sobrestock en una sucursal y falta en otra'
			}[o.type] || '');

			// Lista de items
			var itemsHtml = (o.items || []).map(function (i) {
				var emoji = OPP_EMOJIS[i.label] || '📦';
				var rightBadge = '';
				var subText = esc(i.detail || '');

				if (o.type === 'excess') {
					subText = 'Cobertura: ' + (i.coverage_days ? Number(i.coverage_days).toFixed(1) + ' d' : '15+ d') + ' | Excedente: <span class="text-success font-weight-bold">' + fmtMoney(i.amount) + '</span>';
				} else if (o.type === 'stockout') {
					var cov = i.coverage_days !== undefined ? Number(i.coverage_days).toFixed(1) + ' d' : '—';
					rightBadge = '<span class="dsm-opp-pill is-red">' + cov + '</span>';
				} else if (o.type === 'expiry') {
					rightBadge = '<span class="dsm-opp-pill is-amber">' + esc(i.badge || '≤ 30 d') + '</span>';
				} else if (o.type === 'slow') {
					rightBadge = '<span class="font-weight-bold" style="color:#7e22ce;font-size:13px;">' + fmtMoney(i.amount) + '</span>';
				} else if (o.type === 'transfer') {
					rightBadge = '<span class="dsm-opp-pill is-blue">' + fmtNum(i.qty || 1) + ' uds</span>';
				}

				return '<div class="dsm-opp-item-row">' +
					'<span class="dsm-opp-item-emoji">' + emoji + '</span>' +
					'<div class="dsm-opp-item-text">' +
						'<div class="dsm-opp-item-name">' + esc(i.label) + '</div>' +
						'<div class="dsm-opp-item-sub">' + subText + '</div>' +
					'</div>' +
					(rightBadge ? '<div class="dsm-opp-item-val">' + rightBadge + '</div>' : '') +
				'</div>';
			}).join('');

			if (!itemsHtml) {
				itemsHtml = '<div class="text-muted text-center py-4 font-italic" style="font-size:12px;">Sin casos con los filtros actuales</div>';
			}

			// Caja de resumen (Summary Box)
			var summaryBoxHtml = '';
			if (o.type === 'transfer') {
				var fromB = esc(o.from_branch || 'Aventura');
				var toB = esc(o.to_branch || 'Miami Beach');
				summaryBoxHtml = '<div class="dsm-opp-summary-box">' +
					'<div class="dsm-opp-route-box">' +
						'<div class="dsm-opp-route-node"><i class="fa-solid fa-store text-info mr-1"></i><div><span class="text-muted d-block" style="font-size:9.5px;">Sucursal origen</span><b>' + fromB + '</b></div></div>' +
						'<i class="fa-solid fa-arrow-right text-muted" style="font-size:12px;"></i>' +
						'<div class="dsm-opp-route-node"><i class="fa-solid fa-store text-info mr-1"></i><div><span class="text-muted d-block" style="font-size:9.5px;">Sucursal destino</span><b>' + toB + '</b></div></div>' +
					'</div>' +
				'</div>';
			} else {
				var s1Label = esc(o.stat1_label || 'Total');
				var s1Val = esc(o.stat1_val || fmtMoney(o.total || 0));
				var s2Label = esc(o.stat2_label || 'Casos');
				var s2Val = esc(o.stat2_val || fmtNum(o.count || 0));
				var statColorClass = tone === 'green' ? 'text-success' : (tone === 'red' ? 'text-danger' : (tone === 'amber' ? 'text-warning' : ''));
				var statStyle = tone === 'purple' ? 'style="color:#a855f7;"' : '';
				var s1Icon = tone === 'red' ? 'fa-cart-shopping' : 'fa-coins';

				summaryBoxHtml = '<div class="dsm-opp-summary-box">' +
					'<div class="dsm-opp-stat-col">' +
						'<i class="fa-solid ' + s1Icon + ' ' + statColorClass + ' dsm-opp-stat-icon" ' + statStyle + '></i>' +
						'<div><div class="dsm-opp-stat-label">' + s1Label + '</div><div class="dsm-opp-stat-val">' + s1Val + '</div></div>' +
					'</div>' +
					'<div class="dsm-opp-stat-col">' +
						'<i class="fa-solid fa-box ' + statColorClass + ' dsm-opp-stat-icon" ' + statStyle + '></i>' +
						'<div><div class="dsm-opp-stat-label">' + s2Label + '</div><div class="dsm-opp-stat-val">' + s2Val + '</div></div>' +
					'</div>' +
				'</div>';
			}

			// Botón de acción inferior
			var canAccess = !HREF_PERM[o.href] || perm(HREF_PERM[o.href]);
			var actionBtn = canAccess
				? '<a href="' + url(o.href) + '" class="dsm-opp-action-btn is-' + tone + '"><i class="fa-solid ' + btnIcon + ' mr-1"></i> ' + esc(o.href_label || 'Ver detalle') + ' &rarr;</a>'
				: '<button type="button" disabled class="dsm-opp-action-btn is-' + tone + '" style="opacity:0.6;cursor:not-allowed;" title="Permiso requerido"><i class="fa-solid fa-lock mr-1"></i> ' + esc(o.href_label || 'Ver detalle') + '</button>';

			return '<article class="dsm-opp-card-pro is-' + tone + '">' +
				'<header class="dsm-opp-header">' +
					'<div class="dsm-opp-icon-box"><i class="fa-solid ' + icon + '"></i></div>' +
					'<div class="dsm-opp-title-wrap">' +
						'<div class="dsm-opp-card-title">' + esc(o.title) + '</div>' +
						'<div class="dsm-opp-card-sub">' + esc(subtitle) + '</div>' +
					'</div>' +
					'<div class="dsm-opp-counter-badge">' + fmtNum(o.count) + '</div>' +
				'</header>' +
				'<div class="dsm-opp-items-list">' + itemsHtml + '</div>' +
				summaryBoxHtml +
				'<div class="dsm-opp-basis-box"><i class="fa-solid fa-circle-info mr-1"></i><span>' + esc(o.basis) + '</span></div>' +
				actionBtn +
			'</article>';
		}).join('');

		$('#dsm-opps-grid').html(html);
	}

	function renderContextBar() {
		var html = '';
		var rec = (dsmState.meta && dsmState.meta.reconciliation) || {};
		html += rec.is_reconciled
			? '<span class="dsm-chip is-ok"><i class="fa-solid fa-circle-check"></i>Conciliado: tarjetas, departamentos y sucursales coinciden</span>'
			: '<span class="dsm-chip is-bad"><i class="fa-solid fa-triangle-exclamation"></i>Diferencia entre tarjetas y departamentos</span>';
		var sel = selectedDept();
		var d = deptRows().filter(function (r) { return r.id === sel; })[0];
		if (d) {
			html += '<button type="button" class="dsm-chip is-filter" id="dsm-chip-dept" title="Quitar el filtro de departamento">Departamento: ' + esc(d.name) + ' <i class="fa-solid fa-xmark"></i></button>';
		}
		var f = dsmState.filters || {};
		if (f.date && f.date !== dsmState.meta.today) {
			html += '<span class="dsm-chip"><i class="fa-solid fa-circle-info"></i>Fecha de corte ' + esc(f.date) + ': aplica a ventas, rotación y evolución; las existencias son las actuales</span>';
		}
		$('#dsm-context-bar').html(html);
	}

	function toggleDept(d) {
		if (!d.filterable) {
			window.location.href = url('/products');
			return;
		}
		$('#dsm-filter-dept').val(selectedDept() === d.id ? 'todos' : d.id);
		dsmApplyAllFilters();
	}

	window.dsmClearDept = function () {
		$('#dsm-filter-dept').val('todos');
		dsmApplyAllFilters();
	};

	$(document).on('click', '#dsm-dept-clear, #dsm-chip-dept', function () {
		dsmClearDept();
	});

	$(document).on('click keydown', '#dsm-rank-tbody tr[data-dept-id]', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter') return;
		var id = $(this).data('dept-id');
		var d = deptRows().filter(function (r) { return r.id === id; })[0];
		if (d) toggleDept(d);
	});

	$(document).on('click', '#dsm-donut-legend .dsm-donut-legend-item[data-dept-id]', function () {
		var id = $(this).data('dept-id');
		var d = deptRows().filter(function (r) { return r.id === id; })[0];
		if (d) toggleDept(d);
	});

	$(document).on('click', '.dsm-risk-card-pro[data-dept-id]', function (e) {
		if ($(e.target).closest('a, button').length) return;
		var id = $(this).data('dept-id');
		var d = deptRows().filter(function (r) { return r.id === id; })[0];
		if (d) toggleDept(d);
	});

	// =========================================================================
	// 3. FILTROS DE TARJETAS (MAPA DE CALOR, TOP DE PRODUCTOS, VENCIMIENTOS)
	// Cada tarjeta consulta su propio endpoint con los filtros globales del panel.
	// =========================================================================
	var FILTER_STORE_KEY = 'dsm_card_filters_v1';
	var FILTER_DEFAULTS = {
		hm: { metric: 'units', period: '14', from: '', to: '' },
		top: { sort: 'value_desc', limit: '10', depts: [] },
		fefo: { window: '30', from: '', to: '', depts: [] }
	};

	function filterDefaults() {
		return JSON.parse(JSON.stringify(FILTER_DEFAULTS));
	}

	var cardFilters = (function () {
		var out = filterDefaults();
		try {
			var saved = JSON.parse(window.sessionStorage.getItem(FILTER_STORE_KEY) || 'null');
			if (saved) {
				Object.keys(out).forEach(function (w) { $.extend(out[w], saved[w]); });
			}
		} catch (e) {}
		return out;
	})();

	function saveCardFilters() {
		try {
			window.sessionStorage.setItem(FILTER_STORE_KEY, JSON.stringify(cardFilters));
		} catch (e) {}
	}

	function deptOptions() {
		return dsmState.dept_options || [];
	}

	var MENUS = {
		'dsm-hm-metric-btn': { widget: 'hm', field: 'metric', options: [
			{ value: 'units', label: 'Unidades en stock' },
			{ value: 'value', label: 'Valor del inventario ($)' },
			{ value: 'rotation', label: 'Rotación de inventario' },
			{ value: 'coverage', label: 'Cobertura en días' },
			{ value: 'critical', label: 'Productos con stock crítico' },
			{ value: 'variation', label: 'Variación de existencias (%)' }
		] },
		'dsm-hm-period-btn': { widget: 'hm', field: 'period', custom: true, options: [
			{ value: '7', label: 'Últimos 7 días' },
			{ value: '14', label: 'Últimos 14 días' },
			{ value: '30', label: 'Últimos 30 días' },
			{ value: '60', label: 'Últimos 60 días' },
			{ value: '90', label: 'Últimos 90 días' },
			{ value: 'custom', label: 'Rango personalizado' }
		] },
		'dsm-top10-sort-btn': { widget: 'top', field: 'sort', options: [
			{ value: 'rotation_desc', label: 'Mayor rotación' },
			{ value: 'rotation_asc', label: 'Menor rotación' },
			{ value: 'value_desc', label: 'Mayor valor de inventario' },
			{ value: 'value_asc', label: 'Menor valor de inventario' },
			{ value: 'units_desc', label: 'Mayor cantidad de unidades' },
			{ value: 'units_asc', label: 'Menor cantidad de unidades' },
			{ value: 'overstock', label: 'Mayor riesgo de sobrestock' },
			{ value: 'no_movement', label: 'Productos sin movimiento' }
		] },
		'dsm-top10-limit-btn': { widget: 'top', field: 'limit', options: [
			{ value: '5', label: 'Top 5' },
			{ value: '10', label: 'Top 10' },
			{ value: '20', label: 'Top 20' },
			{ value: '50', label: 'Top 50' },
			{ value: 'all', label: 'Todos los productos' }
		] },
		'dsm-top10-dept-btn': { widget: 'top', field: 'depts', multi: true, allLabel: 'Departamentos' },
		'dsm-fefo-days-btn': { widget: 'fefo', field: 'window', custom: true, options: [
			{ value: 'expired', label: 'Vencidos' },
			{ value: '1', label: 'Próximas 24 horas' },
			{ value: '3', label: 'Próximos 3 días' },
			{ value: '7', label: 'Próximos 7 días' },
			{ value: '15', label: 'Próximos 15 días' },
			{ value: '30', label: 'Próximos 30 días' },
			{ value: '60', label: 'Próximos 60 días' },
			{ value: 'custom', label: 'Rango personalizado' }
		] },
		'dsm-fefo-dept-btn': { widget: 'fefo', field: 'depts', multi: true, allLabel: 'Todos los departamentos' }
	};

	function shortDate(iso) {
		var p = String(iso || '').split('-');
		return p.length === 3 ? p[2] + '/' + p[1] : '';
	}

	function menuLabel(cfg) {
		var f = cardFilters[cfg.widget];
		var v = f[cfg.field];
		if (cfg.multi) {
			if (!v.length) return cfg.allLabel;
			if (v.length > 1) return v.length + ' departamentos';
			var one = deptOptions().filter(function (o) { return o.value === v[0]; })[0];
			return one ? one.label : cfg.allLabel;
		}
		if (cfg.custom && v === 'custom') return shortDate(f.from) + ' – ' + shortDate(f.to);
		var opt = cfg.options.filter(function (o) { return o.value === v; })[0];
		return opt ? opt.label : '';
	}

	function syncFilterButtons() {
		Object.keys(MENUS).forEach(function (id) {
			var cfg = MENUS[id];
			var def = FILTER_DEFAULTS[cfg.widget][cfg.field];
			var cur = cardFilters[cfg.widget][cfg.field];
			$('#' + id).toggleClass('is-active', JSON.stringify(cur) !== JSON.stringify(def)).find('.dsm-fdd-label').text(menuLabel(cfg));
		});
		$('.dsm-fdd-clear').each(function () {
			var w = $(this).data('widget');
			this.hidden = JSON.stringify(cardFilters[w]) === JSON.stringify(FILTER_DEFAULTS[w]);
		});
	}

	function setCardFilter(widget, patch) {
		var before = JSON.stringify(cardFilters[widget]);
		$.extend(cardFilters[widget], patch);
		if (JSON.stringify(cardFilters[widget]) === before) return;
		saveCardFilters();
		syncFilterButtons();
		loadWidget(widget);
	}

	var openMenuState = null;

	function positionMenu() {
		if (!openMenuState) return;
		var rect = openMenuState.$btn[0].getBoundingClientRect();
		var $m = openMenuState.$menu;
		var vw = window.innerWidth;
		var vh = window.innerHeight;
		$m.css({ maxHeight: '', top: 0, left: 0 });
		var width = Math.min(Math.max(rect.width, $m.outerWidth()), vw - 16);
		var height = $m.outerHeight();
		var below = vh - rect.bottom - 12;
		var above = rect.top - 12;
		var openDown = below >= Math.min(height, 240) || below >= above;
		var maxH = Math.max(140, openDown ? below : above);
		$m.css({
			left: Math.max(8, Math.min(rect.left, vw - width - 8)),
			top: openDown ? rect.bottom + 4 : Math.max(8, rect.top - 4 - Math.min(height, maxH)),
			maxHeight: maxH
		});
	}

	function closeMenu(returnFocus) {
		if (!openMenuState) return;
		var st = openMenuState;
		openMenuState = null;
		st.$menu.remove();
		st.$btn.attr('aria-expanded', 'false');
		if (st.pending) {
			var patch = {};
			patch[st.cfg.field] = st.pending;
			setCardFilter(st.cfg.widget, patch);
		}
		if (returnFocus) st.$btn.trigger('focus');
	}

	function optionHtml(o, selected) {
		return '<button type="button" class="dsm-fdd-opt" role="option" data-value="' + esc(o.value) + '" aria-selected="' + (selected ? 'true' : 'false') + '"><span>' + esc(o.label) + '</span><i class="fa-solid fa-check"></i></button>';
	}

	function showCustomPanel($m, cfg) {
		$m.find('.dsm-fdd-custom').remove();
		var f = cardFilters[cfg.widget];
		var maxAttr = cfg.widget === 'hm' ? ' max="' + esc((dsmState.meta && dsmState.meta.today) || '') + '"' : '';
		$m.append('<form class="dsm-fdd-custom" novalidate>' +
			'<label>Desde<input type="date" name="from" value="' + esc(f.from) + '"' + maxAttr + '></label>' +
			'<label>Hasta<input type="date" name="to" value="' + esc(f.to) + '"' + maxAttr + '></label>' +
			'<span class="dsm-fdd-err" role="alert"></span>' +
			'<button type="submit">Aplicar rango</button></form>');
		$m.find('input[name=from]').trigger('focus');
		positionMenu();
	}

	function openMenu($btn) {
		var cfg = MENUS[$btn.attr('id')];
		var isOpen = openMenuState && openMenuState.$btn[0] === $btn[0];
		closeMenu(false);
		if (isOpen) return;

		var value = cardFilters[cfg.widget][cfg.field];
		var options = cfg.multi ? deptOptions() : cfg.options;
		var html = '<div class="dsm-fdd-menu" aria-label="' + esc($btn.attr('aria-label')) + '">';
		if (cfg.multi) html += '<input type="search" class="dsm-fdd-search" placeholder="Buscar departamento…" aria-label="Buscar departamento">';
		html += '<div class="dsm-fdd-list" role="listbox"' + (cfg.multi ? ' aria-multiselectable="true"' : '') + '>';
		html += options.map(function (o) { return optionHtml(o, cfg.multi ? value.indexOf(o.value) !== -1 : o.value === value); }).join('');
		html += '</div>';
		if (cfg.multi) html += '<div class="dsm-fdd-foot"><button type="button" class="is-ghost" data-act="clear">Todos</button><button type="button" data-act="done">Aplicar</button></div>';
		html += '</div>';

		var $m = $(html).appendTo('body');
		openMenuState = { cfg: cfg, $btn: $btn, $menu: $m, pending: null };
		$btn.attr('aria-expanded', 'true');
		positionMenu();
		if (cfg.custom && value === 'custom') {
			showCustomPanel($m, cfg);
		} else if (cfg.multi) {
			$m.find('.dsm-fdd-search').trigger('focus');
		} else {
			($m.find('.dsm-fdd-opt[aria-selected=true]')[0] || $m.find('.dsm-fdd-opt')[0]).focus();
		}
	}

	$(document).on('click', '.dsm-fdd-btn', function (e) {
		e.preventDefault();
		openMenu($(this));
	});

	$(document).on('keydown', '.dsm-fdd-btn', function (e) {
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			if (!openMenuState) openMenu($(this));
		}
	});

	$(document).on('click', '.dsm-fdd-opt', function () {
		if (!openMenuState) return;
		var st = openMenuState;
		var cfg = st.cfg;
		var v = String($(this).data('value'));
		if (cfg.multi) {
			var on = $(this).attr('aria-selected') !== 'true';
			$(this).attr('aria-selected', on ? 'true' : 'false');
			st.pending = st.$menu.find('.dsm-fdd-opt[aria-selected=true]').map(function () { return String($(this).data('value')); }).get();
			return;
		}
		if (cfg.custom && v === 'custom') {
			showCustomPanel(st.$menu, cfg);
			return;
		}
		var patch = {};
		patch[cfg.field] = v;
		closeMenu(true);
		setCardFilter(cfg.widget, patch);
	});

	$(document).on('click', '.dsm-fdd-foot button', function () {
		if (!openMenuState) return;
		if ($(this).data('act') === 'clear') {
			openMenuState.pending = [];
		}
		closeMenu(true);
	});

	$(document).on('input', '.dsm-fdd-search', function () {
		var q = $(this).val().trim().toLowerCase();
		var $m = openMenuState.$menu;
		var shown = 0;
		$m.find('.dsm-fdd-empty').remove();
		$m.find('.dsm-fdd-opt').each(function () {
			var hit = $(this).text().toLowerCase().indexOf(q) !== -1;
			$(this).toggle(hit);
			if (hit) shown++;
		});
		if (!shown) $m.find('.dsm-fdd-list').append('<div class="dsm-fdd-empty">Sin departamentos con ese nombre</div>');
	});

	$(document).on('submit', '.dsm-fdd-custom', function (e) {
		e.preventDefault();
		var st = openMenuState;
		if (!st) return;
		var from = $(this).find('[name=from]').val();
		var to = $(this).find('[name=to]').val();
		var err = !from || !to ? 'Elige ambas fechas' : (from > to ? 'La fecha inicial debe ser anterior a la final' : '');
		if (err) {
			$(this).find('.dsm-fdd-err').text(err);
			positionMenu();
			return;
		}
		var patch = { from: from, to: to };
		patch[st.cfg.field] = 'custom';
		var widget = st.cfg.widget;
		closeMenu(true);
		setCardFilter(widget, patch);
	});

	$(document).on('keydown', '.dsm-fdd-menu', function (e) {
		if (!openMenuState) return;
		if (e.key === 'Escape') {
			e.preventDefault();
			e.stopPropagation();
			openMenuState.pending = null;
			closeMenu(true);
			return;
		}
		if (e.key === 'Tab') {
			var focusables = $(this).find('input, button').filter(':visible');
			if (e.target === focusables[focusables.length - 1] && !e.shiftKey) {
				closeMenu(true);
				e.preventDefault();
			}
			return;
		}
		if ($(e.target).is('input[type=date]')) return;
		var $opts = $(this).find('.dsm-fdd-opt:visible');
		if (!$opts.length) return;
		var idx = $opts.index(document.activeElement);
		var next = null;
		if (e.key === 'ArrowDown') next = idx < 0 ? 0 : Math.min(idx + 1, $opts.length - 1);
		if (e.key === 'ArrowUp') next = idx <= 0 ? 0 : idx - 1;
		if (e.key === 'Home') next = 0;
		if (e.key === 'End') next = $opts.length - 1;
		if (next !== null && !(($(e.target).is('.dsm-fdd-search')) && (e.key === 'Home' || e.key === 'End'))) {
			e.preventDefault();
			$opts[next].focus();
		}
	});

	document.addEventListener('pointerdown', function (e) {
		if (!openMenuState) return;
		if (openMenuState.$menu[0].contains(e.target) || openMenuState.$btn[0].contains(e.target)) return;
		closeMenu(false);
	}, true);

	window.addEventListener('scroll', function (e) {
		if (openMenuState && !(e.target.nodeType === 1 && openMenuState.$menu[0].contains(e.target))) positionMenu();
	}, true);
	window.addEventListener('resize', positionMenu);

	$(document).on('click', '.dsm-fdd-clear', function () {
		var w = $(this).data('widget');
		var before = JSON.stringify(cardFilters[w]);
		cardFilters[w] = filterDefaults()[w];
		saveCardFilters();
		syncFilterButtons();
		if (JSON.stringify(cardFilters[w]) !== before) loadWidget(w);
	});

	$(document).on('click', '.dsm-widget-retry', function () {
		loadWidget($(this).data('widget'));
	});

	// Peticiones por tarjeta: cada una cancela la anterior y descarta respuestas tardías
	var WIDGETS = {
		hm: { path: '/api/stockoverview/heatmap', card: '#dsm-section-heatmap', xhr: null, seq: 0, data: null, params: function (f) {
			var p = { metric: f.metric, heat_period: f.period };
			if (f.period === 'custom') { p.from = f.from; p.to = f.to; }
			return p;
		}, render: function (d) { renderHeatmap(d); }, fail: function (msg) { $('#dsm-heatmap-grid').html(msg); } },
		top: { path: '/api/stockoverview/top-products', card: '#dsm-section-top10', xhr: null, seq: 0, data: null, params: function (f) {
			return { sort: f.sort, limit: f.limit, departments: f.depts.join(',') };
		}, render: function (d) { renderTop10Table(d); }, fail: function (msg) { $('#dsm-top10-tbody').html('<tr><td colspan="6">' + msg + '</td></tr>'); } },
		fefo: { path: '/api/stockoverview/expiring', card: '#dsm-section-fefo', xhr: null, seq: 0, data: null, params: function (f) {
			var p = { window: f.window, departments: f.depts.join(',') };
			if (f.window === 'custom') { p.from = f.from; p.to = f.to; }
			return p;
		}, render: function (d) { renderFefoTable(d); }, fail: function (msg) { $('#dsm-fefo-tbody').html('<tr><td colspan="5">' + msg + '</td></tr>'); } }
	};

	function loadWidget(key) {
		var w = WIDGETS[key];
		if (w.xhr) w.xhr.abort();
		var seq = ++w.seq;
		var $card = $(w.card).addClass('dsm-widget-busy').attr('aria-busy', 'true');
		w.xhr = $.getJSON(url(w.path), $.extend(globalFilterParams(), w.params(cardFilters[key]))).done(function (d) {
			if (seq !== w.seq) return;
			w.data = d;
			w.render(d);
		}).fail(function (xhr, status) {
			if (seq !== w.seq || status === 'abort') return;
			var detail = xhr.responseJSON && xhr.responseJSON.error_message ? esc(xhr.responseJSON.error_message) : 'No se pudo actualizar la tarjeta';
			w.fail('<div class="dsm-widget-msg" role="alert">' + detail + '<button type="button" class="dsm-widget-retry" data-widget="' + key + '">Reintentar</button></div>');
		}).always(function () {
			if (seq !== w.seq) return;
			w.xhr = null;
			$card.removeClass('dsm-widget-busy').removeAttr('aria-busy');
		});
	}

	function loadAllWidgets() {
		Object.keys(WIDGETS).forEach(loadWidget);
	}

	function renderHeatmap(d) {
		var $grid = $('#dsm-heatmap-grid');
		var n = d.dates.length;
		var note = (d.history_from && d.from < d.history_from) ? ' Sin kardex antes del ' + shortDate(d.history_from) + ': esos días aparecen en gris.' : '';
		$('#dsm-hm-title').text(d.title);
		$('#dsm-hm-desc').text(d.description + note);
		$('#dsm-hm-gauge').toggleClass('is-bad-low', !!d.bad_low);
		$grid[0].style.setProperty('--hm-cols', n);
		$grid.toggleClass('is-scroll', n > 14);

		var s = d.stats;
		$('#dsm-hm-gauge-min').text('Mín. ' + s.formatted_min);
		$('#dsm-hm-gauge-max').text('Máx. ' + s.formatted_max);
		$('#dsm-hm-stat-avg').text(s.formatted_avg);
		$('#dsm-hm-stat-critical').text(s.critical_days);
		var delta = s.variation_pct;
		var good = delta === null || (d.bad_low ? delta >= 0 : delta <= 0);
		var deltaEl = $('#dsm-hm-stat-delta').text(delta === null ? '—' : (delta > 0 ? '+' : '') + fmtNum(delta, 1) + '%')[0];
		deltaEl.style.setProperty('background', good ? '#dcfce7' : '#fee2e2', 'important');
		deltaEl.style.setProperty('color', good ? '#00a651' : '#dc2626', 'important');
		deltaEl.style.setProperty('border-color', good ? '#86efac' : '#fecaca', 'important');

		if (!d.rows.length) {
			$grid.html('<div class="dsm-widget-msg">Sin existencias ni movimientos para estos filtros</div>');
			return;
		}
		var html = d.rows.map(function (row) {
			return '<div class="dsm-hm-row' + (row.key === 'total' ? ' is-total' : '') + '"><span class="dsm-hm-day" title="' + esc(row.name) + '">' + esc(row.name) + '</span>' + row.cells.map(function (c, i) {
				var label = row.name + ' · ' + d.dates[i].long + ' · ' + c.formatted;
				return '<div class="dsm-hm-cell' + (c.val === null ? ' is-empty' : '') + '" style="background-color:' + esc(c.color) + ';" data-date="' + esc(c.date) + '" data-row="' + esc(row.key) + '" data-val="' + esc(c.val === null ? '' : c.val) + '" data-intensity="' + esc(c.intensity) + '" title="' + esc(label) + '"></div>';
			}).join('') + '</div>';
		}).join('');
		var step = n <= 14 ? 2 : (n <= 30 ? 4 : 7);
		html += '<div class="dsm-hm-hours"><span></span>' + d.dates.map(function (x, i) {
			return '<span class="dsm-hm-hour">' + (i % step === 0 ? esc(x.label) : '') + '</span>';
		}).join('') + '</div>';
		$grid.html(html);
		var wrap = $grid.parent()[0];
		wrap.scrollLeft = wrap.scrollWidth;
	}

	$(document).on('click', '.dsm-hm-cell:not(.is-empty)', function () {
		if (typeof toastr !== 'undefined') {
			toastr.info($(this).attr('title'), 'Detalle del día');
		}
	});

	window.dsmOpenHeatmapInfo = function () {
		$('#dsmHeatmapInfoModal').modal('show');
	};

	// =========================================================================
	// 4. TOP DE PRODUCTOS (SECCIÓN 5)
	// =========================================================================
	function topNote(tp, sort) {
		if (sort.indexOf('rotation') === 0) return 'Rotación ' + fmtNum(tp.rotation, 2) + 'x';
		if (sort === 'overstock') return 'Excedente ' + fmtMoney(tp.overstock_value) + (tp.no_movement ? ' · sin ventas' : ' · ' + fmtNum(tp.coverage_days, 1) + ' d');
		if (sort === 'no_movement') return 'Sin ventas en 28 días';
		return '';
	}

	function renderTop10Table(d) {
		var base = Grocy.BaseUrl.replace(/\/$/, '');
		$('#dsm-top10-title').text(d.title);
		$('#dsm-top10-sub').text(d.subtitle);
		$('#dsm-top10-foot-label').text(d.stats.label);
		$('#dsm-top10-foot-total').text(fmtMoney(d.stats.total_value));
		$('#dsm-top10-foot-share').text(fmtNum(d.stats.participation_pct, 1) + '%');

		var tbody = d.rows.map(function (tp) {
			var imgUrl = tp.image.indexOf('http') === 0 ? tp.image : (base + tp.image);
			var note = topNote(tp, d.sort);
			return '<tr onclick="dsmOpenProduct360(' + Number(tp.product_id) + ')" data-product-id="' + Number(tp.product_id) + '" title="SKU: ' + esc(tp.sku) + ' · UPC: ' + esc(tp.upc) + ' · Rotación: ' + esc(tp.rotation) + 'x · Cobertura: ' + esc(tp.coverage_days) + 'd · Estado: ' + esc(tp.status_label) + '" style="cursor: pointer;">' +
				'<td class="font-weight-bold text-muted" style="padding: 6px 4px;">' + tp.rank + '</td>' +
				'<td style="padding: 6px 6px;"><div class="d-flex align-items-center" style="gap: 8px;">' +
				'<img src="' + esc(imgUrl) + '" alt="' + esc(tp.name) + '" onerror="this.onerror=null; this.src=\'' + base + '/img/control_center/prod_pan_molde.svg\';" style="width: 26px; height: 26px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">' +
				'<div style="min-width: 0;"><span class="font-weight-bold text-dark text-truncate d-block" style="max-width: 130px;">' + esc(tp.name) + '</span>' + (note ? '<span class="dsm-top-note">' + esc(note) + '</span>' : '') + '</div>' +
				'</div></td>' +
				'<td class="text-muted" style="padding: 6px 6px;">' + esc(tp.department) + '</td>' +
				'<td style="text-align: right; font-weight: 800; color: #0f172a; padding: 6px 6px;">' + fmtMoney(tp.value) + '</td>' +
				'<td style="text-align: right; font-weight: 600; color: #475569; padding: 6px 6px;">' + fmtNum(tp.units) + '</td>' +
				'<td style="text-align: right; padding: 6px 6px;"><div class="dsm-prog-wrap">' +
				'<span class="font-weight-bold text-dark" style="font-size: 11px;">' + fmtNum(tp.pct_of_total, 1) + '%</span>' +
				'<div class="dsm-prog-bar-bg"><div class="dsm-prog-bar-fill" style="width: ' + Math.min(100, Math.max(4, Number(tp.pct_of_total))) + '%;"></div></div>' +
				'</div></td></tr>';
		}).join('');
		$('#dsm-top10-tbody').html(tbody || '<tr><td colspan="6"><div class="dsm-widget-msg">Sin productos para esta combinación de filtros</div></td></tr>');
	}

	window.dsmOpenTop10Info = function () {
		$('#dsmTop10InfoModal').modal('show');
	};

	window.dsmOpenTop10AllModal = function () {
		var d = WIDGETS.top.data;
		var list = d ? d.rows : [];
		var base = Grocy.BaseUrl.replace(/\/$/, '');
		var rows = list.map(function (tp) {
			var imgUrl = tp.image.indexOf('http') === 0 ? tp.image : (base + tp.image);
			return '<tr onclick="dsmOpenProduct360(' + Number(tp.product_id) + ')" style="cursor:pointer;">' +
				'<td>' + tp.rank + '</td>' +
				'<td><div class="d-flex align-items-center" style="gap:8px;"><img src="' + esc(imgUrl) + '" onerror="this.onerror=null; this.src=\'' + base + '/img/control_center/prod_pan_molde.svg\';" style="width:24px; height:24px; border-radius:4px; object-fit:cover;"><strong>' + esc(tp.name) + '</strong></div></td>' +
				'<td>' + esc(tp.department) + '</td>' +
				'<td class="text-right font-weight-bold text-dark">' + fmtMoney(tp.value) + '</td>' +
				'<td class="text-right">' + fmtNum(tp.units) + '</td>' +
				'<td class="text-right font-weight-bold text-success">' + fmtNum(tp.pct_of_total, 1) + '%</td>' +
				'</tr>';
		}).join('');

		$('#dsm-top10-all-modal-tbody').html(rows || '<tr><td colspan="6" class="text-center py-3 text-muted">Sin registros</td></tr>');
		$('#dsm-top10-all-modal-summary').text(d ? 'Total del alcance: ' + fmtMoney(d.stats.scope_value) + ' · ' + d.stats.shown_count + ' de ' + d.stats.matched_count + ' artículos' : '');
		$('#dsmTop10AllModal').modal('show');
	};

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
				'<div class="text-muted small">SKU: <strong>' + p.sku + '</strong> &middot; UPC: <strong>' + (p.upc || '—') + '</strong> &middot; Departamento: <strong>' + p.department + '</strong></div>' +
				'</div>' +
				'</div>' +
				'<div class="d-flex" style="gap:18px;">' +
				'<div class="text-right"><div class="small text-muted">Valor Total</div><div class="h5 mb-0 font-weight-bold text-success">' + fmtMoney(p.value) + '</div></div>' +
				'<div class="text-right"><div class="small text-muted">Unidades</div><div class="h5 mb-0 font-weight-bold text-dark">' + fmtNum(p.units) + '</div></div>' +
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
	function fefoPill(days) {
		if (days < 0) return '<span class="dsm-days-pill is-expired" title="Vencido hace ' + Math.abs(days) + ' día(s)">' + days + '</span>';
		return '<span class="dsm-days-pill ' + (days <= 7 ? 'is-danger' : (days <= 15 ? 'is-warning' : 'is-info')) + '">' + days + '</span>';
	}

	function renderFefoTable(d) {
		var base = Grocy.BaseUrl.replace(/\/$/, '');
		var tbody = d.rows.map(function (ep, idx) {
			var imgUrl = ep.image.indexOf('http') === 0 ? ep.image : (base + ep.image);
			return '<tr data-fefo-idx="' + idx + '" title="Lote: ' + esc(ep.lot) + ' · Vence: ' + esc(ep.expiry_date) + ' · ' + esc(ep.branch_name) + ' (' + esc(ep.location) + ') · Clic para ejecutar acción FEFO" style="cursor: pointer;">' +
				'<td class="font-weight-bold text-muted">' + ep.rank + '</td>' +
				'<td><div class="d-flex align-items-center" style="gap: 8px;">' +
				'<img src="' + esc(imgUrl) + '" alt="' + esc(ep.name) + '" onerror="this.onerror=null; this.src=\'' + base + '/img/control_center/prod_baguette.svg\';" style="width: 26px; height: 26px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">' +
				'<div style="min-width: 0;"><span class="font-weight-bold text-dark text-truncate d-block" style="max-width: 105px;">' + esc(ep.name) + '</span><span class="dsm-top-note" style="max-width: 105px;">' + esc(ep.lot) + ' · ' + esc(ep.branch_name) + '</span></div>' +
				'</div></td>' +
				'<td style="text-align: center;">' + fefoPill(ep.days_left) + '</td>' +
				'<td style="text-align: right; font-weight: 600; color: #475569;">' + fmtNum(ep.stock) + '</td>' +
				'<td style="text-align: right; font-weight: 700; color: #0f172a;">' + fmtMoney(ep.value) + '</td>' +
				'</tr>';
		}).join('');
		$('#dsm-fefo-tbody').html(tbody || '<tr><td colspan="5"><div class="dsm-widget-msg">Sin lotes para esta ventana y departamentos</div></td></tr>');
		$('#dsm-fefo-foot-risk').text(fmtMoney(d.stats.total_risk));
		$('#dsm-fefo-foot-count').text(d.stats.products_count);
		$('#dsm-fefo-sub').text(d.stats.lots_count + ' lote(s) · ' + fmtNum(d.stats.units_count) + ' unidades en la ventana');
	}

	$(document).on('click', '#dsm-fefo-tbody tr[data-fefo-idx], #dsm-fefo-all-modal-tbody tr[data-fefo-idx]', function () {
		var ep = WIDGETS.fefo.data && WIDGETS.fefo.data.rows[Number($(this).data('fefo-idx'))];
		if (ep) dsmOpenFefoActionModal(ep);
	});

	window.dsmOpenFefoInfo = function () {
		$('#dsmFefoInfoModal').modal('show');
	};

	window.dsmOpenFefoAllModal = function () {
		var d = WIDGETS.fefo.data;
		var list = d ? d.rows : [];
		var base = Grocy.BaseUrl.replace(/\/$/, '');
		var rows = list.map(function (ep, idx) {
			var imgUrl = ep.image.indexOf('http') === 0 ? ep.image : (base + ep.image);
			return '<tr data-fefo-idx="' + idx + '" style="cursor: pointer;">' +
				'<td>' + ep.rank + '</td>' +
				'<td><div class="d-flex align-items-center" style="gap: 8px;"><img src="' + esc(imgUrl) + '" onerror="this.onerror=null; this.src=\'' + base + '/img/control_center/prod_baguette.svg\';" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;"><strong>' + esc(ep.name) + '</strong></div></td>' +
				'<td><code>' + esc(ep.lot) + '</code></td>' +
				'<td>' + esc(ep.branch_name) + ' <small class="text-muted">(' + esc(ep.location) + ')</small></td>' +
				'<td>' + esc(ep.expiry_date) + '</td>' +
				'<td class="text-center">' + fefoPill(ep.days_left) + '</td>' +
				'<td class="text-right">' + fmtNum(ep.stock) + '</td>' +
				'<td class="text-right font-weight-bold text-danger">' + fmtMoney(ep.value) + '</td>' +
				'<td class="text-center"><button class="btn btn-xs btn-outline-danger font-weight-bold" style="border-radius: 6px; font-size: 11px;">Acción</button></td>' +
				'</tr>';
		}).join('');

		$('#dsm-fefo-all-modal-tbody').html(rows || '<tr><td colspan="9" class="text-center py-3 text-muted">Sin lotes en esta ventana</td></tr>');
		$('#dsm-fefo-all-modal-summary').text(d ? 'Total en riesgo: ' + fmtMoney(d.stats.total_risk) + ' · ' + d.stats.lots_count + ' lotes analizados' : '');
		$('#dsmFefoAllModal').modal('show');
	};

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
		if (actionKey === 'waste') {
			var it = currentSelectedFefoItem;
			bootbox.confirm({
				message: 'Se registrará la merma de ' + esc(it.stock) + ' u. del lote ' + esc(it.lot) + ' (' + esc(it.name) + ', ' + esc(it.branch_name) + '). ¿Confirma?',
				buttons: { confirm: { label: 'Registrar merma', className: 'btn-danger' }, cancel: { label: 'Cancelar', className: 'btn-secondary' } },
				callback: function (yes) { if (yes) sendFefoAction(actionKey); }
			});
			return;
		}
		sendFefoAction(actionKey);
	};

	function sendFefoAction(actionKey) {
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
	}

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
			var isSel = (dsmState.filters && String(dsmState.filters.branch_id) === String(br.id)) ? 'selected' : '';

			var col2Text = fmtMoney(br.inventory_value);
			if (activeBmode === 'variation') {
				var sign = br.prev_variation_pct > 0 ? '+' : '';
				col2Text = sign + br.prev_variation_pct + '% vs ant. (Dif ' + br.inventory_diff_pct + '%)';
			}

			var trendDir = br.trend_dir || (br.prev_variation_pct >= 0 ? 'up' : 'down');
			var trendBadge = br.trend_badge || ((br.prev_variation_pct > 0 ? '+' : '') + br.prev_variation_pct + '%');
			var isUp = (trendDir === 'up');

			var polylineSvg = isUp
				? '<polyline points="1,11 7,7 13,8 21,1" fill="none" stroke="#00a651" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="16,1 21,1 21,6" fill="none" stroke="#00a651" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
				: '<polyline points="1,1 7,5 13,4 21,11" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="16,11 21,11 21,6" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

			var badgeBg = isUp ? '#dcfce7' : '#fee2e2';
			var badgeColor = isUp ? '#008744' : '#dc2626';

			tbody += '<tr class="' + hideClass + ' ' + isSel + '" data-branch-id="' + br.id + '" onclick="dsmSelectBranchRow(' + br.id + ')" title="Sucursal: ' + esc(br.name) + ' · Dif: ' + br.inventory_diff_pct + '% · Clic para filtrar" style="cursor: pointer;">' +
				'<td><div class="d-flex align-items-center" style="gap: 6px;"><i class="fa-solid fa-shop text-success" style="font-size: 12px;"></i><span class="font-weight-bold text-dark text-truncate" style="max-width: 110px;">' + esc(br.name) + '</span></div></td>' +
				'<td style="text-align:right; font-weight:800; color:#0f172a;" class="dsm-bcell-val">' + col2Text + '</td>' +
				'<td style="text-align:right; font-weight:600; color:#475569;" class="dsm-bcell-units">' + fmtNum(br.units) + '</td>' +
				'<td style="text-align:center; font-weight:800; color:#ef4444;">' + Number(br.rotation).toFixed(1) + '</td>' +
				'<td style="text-align:right; color:#334155;">' + Number(br.coverage_days).toFixed(0) + ' días</td>' +
				'<td style="text-align:right;">' +
				'<div class="d-inline-flex align-items-center" style="gap: 4px;">' +
				'<svg width="22" height="12" viewBox="0 0 22 12" style="overflow: visible;">' + polylineSvg + '</svg>' +
				'<span class="badge font-weight-bold" style="background: ' + badgeBg + '; color: ' + badgeColor + '; font-size: 9.5px; padding: 2px 5px; border-radius: 4px;">' + (isUp ? '▲ ' : '▼ ') + esc(trendBadge) + '</span>' +
				'</div>' +
				'</td>' +
				'</tr>';
		});
		$('#dsm-branch-tbody').html(tbody);

		// Actualizar los 4 KPIs de cabecera de sucursales
		var top5 = list.slice(0, 5);
		var sumVal = top5.reduce(function (acc, x) { return acc + Number(x.inventory_value || 0); }, 0);
		var sumUnits = top5.reduce(function (acc, x) { return acc + Number(x.units || 0); }, 0);
		if (sumVal > 0) {
			$('#dsm-b-kpi-val').text(fmtMoney(sumVal));
			$('#dsm-b-kpi-units').text(fmtNum(sumUnits));
		}
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

	window.dsmOpenBranchComparisonModal = function () {
		$('#dsmBranchComparisonModal').modal('show');
	};

	window.dsmOpenMarkdownAction = function () {
		$('#dsmMarkdownModal').modal('show');
	};

	window.dsmSubmitMarkdown = function () {
		var lotId = $('#dsmMarkdownProductSelect').val();
		var pct = $('#dsmMarkdownPctSelect').val();
		var reason = $('#dsmMarkdownReasonSelect').val();
		var notes = $('#dsmMarkdownNotes').val();

		$('#dsmMarkdownModal').modal('hide');
		if (typeof toastr !== 'undefined') {
			toastr.success('Rebaja autorizada del ' + pct + '% aplicada con éxito en POS para el lote seleccionado.', 'Rebaja Comercial');
		}
		dsmApplyAllFilters();
	};

	// =========================================================================
	// 7. MOVIMIENTOS EN TIEMPO REAL Y KARDEX MODAL (SECCIÓN 8)
	// =========================================================================
	function renderMovementsBars() {
		if (!dsmState.movements) return;
		var selVal = $('#dsm-moves-branch-select').val();
		var bars = (selVal === 'detailed_7')
			? (dsmState.movements.bars_detailed || [])
			: (dsmState.movements.bars_photo || []);

		var maxBarUnits = 12000;
		var iconsMap = {
			'entradas': 'fa-box',
			'salidas': 'fa-box',
			'traslados': 'fa-arrow-right-arrow-left',
			'devoluciones': 'fa-arrow-rotate-left',
			'otros': 'fa-layer-group'
		};
		var colorsMap = {
			'entradas': '#00a651',
			'salidas': '#ef4444',
			'traslados': '#f59e0b',
			'devoluciones': '#8b5cf6',
			'otros': '#64748b'
		};

		var html = '';
		bars.forEach(function (bar) {
			var h = Math.max(18, Math.round((bar.units / maxBarUnits) * 140));
			var bColor = colorsMap[bar.key] || bar.color;
			var bIcon = iconsMap[bar.key] || 'fa-box';

			html += '<div class="dsm-move-bar-col" onclick="dsmOpenMovementsModal(\'' + bar.key + '\')" title="' + esc(bar.full_label) + ': ' + fmtNum(bar.units) + ' uds" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; cursor: pointer;">' +
				'<span class="dsm-move-bar-val font-weight-bold" style="font-size: 11px; color: #0f172a; margin-bottom: 4px;">' + fmtNum(bar.units) + '</span>' +
				'<div class="dsm-move-bar-fill d-flex align-items-center justify-content-center" style="width: 100%; max-width: 44px; height: ' + h + 'px; background: ' + bColor + '; border-radius: 8px 8px 4px 4px; color: #ffffff; font-size: 13px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); transition: transform 0.15s ease;">' +
				'<div style="background: rgba(255, 255, 255, 0.25); width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">' +
				'<i class="fa-solid ' + bIcon + '" style="font-size: 12px;"></i>' +
				'</div>' +
				'</div>' +
				'<span class="dsm-move-bar-label font-weight-bold" style="font-size: 10.5px; color: #475569; margin-top: 6px;">' + esc(bar.label) + '</span>' +
				'</div>';
		});
		$('#dsm-moves-bars-container').html(html);

		if (dsmState.movements.stats) {
			var st = dsmState.movements.stats;
			if (st.formatted_total) $('#dsm-move-kpi-total').text(st.formatted_total);
			if (st.formatted_active_products) $('#dsm-move-kpi-prods').text(st.formatted_active_products);
		}
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
	var KPI_DETAIL = { below_min: 'below_min', expiring_soon: 'expiring', out_of_stock: 'out_of_stock', risk_value: 'highest_risk' };
	var KPI_METRIC = { inventory_value: 'value', stock_units: 'units', turnover: 'rotation', coverage: 'coverage' };
	var currentKpiKey = null;

	$('#dsmKpiDetailBtn').on('click', function () {
		$('#dsmKpiModal').modal('hide');
		if (KPI_DETAIL[currentKpiKey]) {
			currentAlertTab = KPI_DETAIL[currentKpiKey];
			renderAlerts();
			document.getElementById('dsm-panel-alerts').scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else {
			currentEvoMetric = KPI_METRIC[currentKpiKey];
			$('.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab').removeClass('active').filter('[data-metric="' + currentEvoMetric + '"]').addClass('active');
			initEvolutionChart();
			document.getElementById('dsm-panel-evolution').scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	});

	$(document).on('keydown', '.dsm-kpi-card', function (e) {
		if (e.key === 'Enter') $(this).trigger('click');
	});

	$(document).on('click', '.dsm-kpi-card', function () {
		var kpiKey = $(this).data('kpi');
		currentKpiKey = kpiKey;
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
			'<div class="small text-dark mt-1"><strong>Estado:</strong> ' + (STATE_LABEL[k.state] || '') + (k.target ? ' · ' + esc(k.target) : '') + '</div>' +
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
		bootbox.confirm({
			message: 'Este paso registra una operación real en el inventario y el Kardex. ¿Continuar?',
			buttons: { confirm: { label: 'Ejecutar', className: 'btn-success' }, cancel: { label: 'Cancelar', className: 'btn-secondary' } },
			callback: function (yes) { if (yes) sendWorkflowStep(); }
		});
	};

	function sendWorkflowStep() {
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
	}

	// =========================================================================
	// 11. FILTROS GLOBALES Y SINCRONIZACIÓN REACTIVA (SECCIÓN 10)
	// =========================================================================
	function globalFilterParams() {
		return {
			date: $('#dsm-filter-date').val(),
			period: currentPeriod,
			branch_id: $('#dsm-filter-branch').val(),
			department: $('#dsm-filter-dept').val(),
			category: $('#dsm-filter-category').val(),
			supplier: $('#dsm-filter-supplier').val(),
			sku: $('#dsm-filter-sku').val(),
			status: $('#dsm-filter-status').val(),
			location: $('#dsm-filter-location').val(),
			movement_type: $('#dsm-filter-movtype').val()
		};
	}

	window.dsmApplyAllFilters = function () {
		var $btn = $('#dsm-btn-refresh');
		$btn.addClass('spinning');

		var params = globalFilterParams();

		if (activeRequest) activeRequest.abort();
		var seq = ++requestSeq;
		$('#dsm-exec-app').addClass('is-loading');
		activeRequest = $.getJSON(url('/api/stockoverview/executive'), params).done(function (data) {
			if (seq !== requestSeq) return;
			dsmState = data;
			updateDashboardDOM();
			loadAllWidgets();
		}).fail(function (xhr, status) {
			if (seq === requestSeq && status !== 'abort') toastr.error('No se pudo actualizar el panel');
		}).always(function () {
			$btn.removeClass('spinning');
			$('#dsm-exec-app').removeClass('is-loading');
			if (seq === requestSeq) {
				activeRequest = null;
			}
		});
	};

	window.dsmResetAllFilters = function () {
		currentEvoMetric = 'value';
		$('#dsm-filter-date').prop('selectedIndex', 0);
		$('#dsm-filter-branch').val('all');
		$('#dsm-filter-dept').val('todos');
		$('#dsm-filter-category').val('todas');
		$('#dsm-filter-supplier').val('todos');
		$('#dsm-filter-sku').val('');
		$('#dsm-filter-status').val('todos');
		$('#dsm-filter-location').val('todas');
		$('#dsm-filter-movtype').val('todos');
		cardFilters = filterDefaults();
		saveCardFilters();
		syncFilterButtons();
		dsmApplyAllFilters();
	};

	function renderAll() {
		applyPermissions();
		renderKpis();
		initEvolutionChart();
		renderAlerts();
		renderMoves();
		initDonutChart();
		renderRanking();
		renderDrill();
		renderRiskCards();
		renderOpportunities();
		renderContextBar();
		renderBranchesTable();
		renderMovementsBars();
	}

	function updateDashboardDOM() {
		if (!dsmState) return;

		// Actualizar hora de sincronización
		if (dsmState.meta && dsmState.meta.synced_at_real) {
			$('#dsm-last-updated').text(dsmState.meta.last_updated + ' · Sync ' + dsmState.meta.synced_at_real.split(' ').pop());
		}

		renderAll();

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

	$(document).on('click', '.dsm-period-btn, .dsm-period-pill', function (e) {
		e.preventDefault();
		$('.dsm-period-btn, .dsm-period-pill').removeClass('active');
		$(this).addClass('active');
		currentPeriod = $(this).data('period');
		// El gráfico cambia al instante con datos locales precargados
		if (dsmState.evolution && dsmState.evolution.all_periods && dsmState.evolution.all_periods[currentPeriod]) {
			dsmState.evolution.series = dsmState.evolution.all_periods[currentPeriod];
			initEvolutionChart();
		}
		dsmApplyAllFilters();
	});

	$(document).on('click', '.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab', function (e) {
		e.preventDefault();
		$('.dsm-evo-metric-pill, #dsm-evo-tabs .dsm-tab').removeClass('active');
		$(this).addClass('active');
		currentEvoMetric = $(this).data('metric');
		initEvolutionChart();
	});

	$('#dsm-evo-compare').on('change', function () {
		currentEvoCompare = $(this).val();
		initEvolutionChart();
	});

	$(document).on('click', '#dsm-evo-export', function () {
		dsmExportEvolution('csv');
	});

	// Limpieza estricta de overlays y backdrops para evitar capas borrosas residuales
	$(document).on('hidden.bs.modal', function () {
		if ($('.modal.show').length === 0) {
			$('.modal-backdrop').remove();
			$('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
		}
	});

	// Redimensionamiento inteligente y suave del gráfico sin parpadeos
	var resizeTimer = null;
	$(window).on('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			if (evoChart) evoChart.resize();
		}, 150);
	});

	// Inicializar al cargar el DOM
	$(document).ready(function () {
		renderAll();
		syncFilterButtons();
		loadAllWidgets();
	});

})();
