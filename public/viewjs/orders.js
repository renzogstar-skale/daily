/* DAILY: Módulo «Pedidos de clientes» (/orders) — Filtros, detalle interactivo, reserva FEFO, preparación, empaque, despacho y exportación */
(function ($) {
	'use strict';

	var D = window.DairyOrders || {};
	var U = window.DairyUI || {};
	var esc = U.esc || function (s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	};
	var baseUrl = (typeof Grocy !== 'undefined' && Grocy.BaseUrl) ? Grocy.BaseUrl : '';

	var allOrders = Array.isArray(D.allOrders) ? D.allOrders : [];
	var detailsMap = D.detailsMap || {};
	var currentOrderId = D.selectedId ? parseInt(D.selectedId, 10) : null;
	var filteredOrders = [];

	var monthsEs = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

	function fmtMoney(v) {
		var n = parseFloat(v) || 0;
		return '$' + n.toFixed(2);
	}

	function fmtDateShort(dStr) {
		if (!dStr || String(dStr).length < 10) return 'Sin fecha';
		var clean = String(dStr).substring(0, 10);
		var parts = clean.split('-');
		if (parts.length !== 3) return clean;
		var y = parts[0];
		var m = parseInt(parts[1], 10) - 1;
		var d = parseInt(parts[2], 10);
		return d + ' ' + (monthsEs[m] || parts[1]) + ' ' + y;
	}

	function fmtDateDayMonth(dStr) {
		if (!dStr || String(dStr).length < 10) return 'Sin fecha';
		var clean = String(dStr).substring(0, 10);
		var parts = clean.split('-');
		if (parts.length !== 3) return clean;
		var m = parseInt(parts[1], 10) - 1;
		var d = parseInt(parts[2], 10);
		return d + ' ' + (monthsEs[m] || parts[1]);
	}

	function fmtStepTime(dtStr) {
		if (!dtStr || String(dtStr).length < 10) return 'Pendiente';
		var s = String(dtStr);
		var dPart = fmtDateDayMonth(s);
		var tPart = s.length >= 16 ? s.substring(11, 16) : '';
		return tPart ? (dPart + ', ' + tPart) : dPart;
	}

	function goOrderUrl(orderId) {
		var b = $('#filter-branch').val() || '';
		var st = $('#filter-status').val() || 'open';
		var q = '?id=' + encodeURIComponent(orderId) + '&status=' + encodeURIComponent(st) + (b ? '&branch_id=' + encodeURIComponent(b) : '');
		window.location.href = baseUrl + '/orders' + q;
	}

	// =========================================================================
	// 1. FILTRADO, BÚSQUEDA, ORDENAMIENTO Y ACTUALIZACIÓN DE KPIS
	// =========================================================================
	function getEarliestOpenDate() {
		var minD = null;
		allOrders.forEach(function (o) {
			if (o.status !== 'dispatched' && o.status !== 'cancelled' && o.requested_date) {
				if (!minD || o.requested_date < minD) {
					minD = o.requested_date;
				}
			}
		});
		return minD || new Date().toISOString().substring(0, 10);
	}

	function applyFiltersAndRender() {
		var query = $.trim($('#orders-search-input').val()).toLowerCase();
		var branchVal = $('#filter-branch').val();
		var statusVal = $('#filter-status').val() || 'open';
		var dateVal = $('#filter-date').val() || '30d';
		var payVal = $('#filter-payment').val() || 'all';
		var delivVal = $('#filter-delivery').val() || 'all';
		var sortVal = $('#orders-sort-select').val() || 'recent';
		var refDate = getEarliestOpenDate();

		// Subconjunto base para KPIs (filtrado por sucursal, búsqueda, fecha, pago y entrega)
		var baseForKpis = allOrders.filter(function (o) {
			if (branchVal && String(o.branch_id) !== String(branchVal)) return false;
			if (payVal !== 'all' && o.payment_status !== payVal) return false;
			if (delivVal !== 'all' && o.delivery_type !== delivVal) return false;

			if (dateVal === 'today') {
				if (!o.requested_date || o.requested_date > refDate) return false;
			} else if (dateVal === 'overdue') {
				var isOver = (o.requested_date && o.requested_date < refDate && o.status !== 'dispatched' && o.status !== 'cancelled') || o.has_shortage;
				if (!isOver) return false;
			}

			if (query) {
				var blob = (
					(o.order_code || '') + ' ' +
					(o.customer_name || '') + ' ' +
					(o.branch_name || '') + ' ' +
					(o.product_search || '')
				).toLowerCase();
				if (blob.indexOf(query) === -1) return false;
			}
			return true;
		});

		// Actualizar las 4 tarjetas KPI con base en el contexto filtrado
		var openList = baseForKpis.filter(function (o) { return o.status !== 'dispatched' && o.status !== 'cancelled'; });
		var prepList = baseForKpis.filter(function (o) { return o.status === 'confirmed' || o.status === 'picking'; });
		var dispList = baseForKpis.filter(function (o) { return o.status === 'picked' || o.status === 'packed'; });
		var openValSum = openList.reduce(function (acc, o) { return acc + (parseFloat(o.total) || 0); }, 0);

		$('#kpi-open-count').text(openList.length);
		$('#kpi-prepare-count').text(prepList.length);
		$('#kpi-dispatch-count').text(dispList.length);
		$('#kpi-open-value').text(fmtMoney(openValSum));

		// Filtrar por estado seleccionado para la tabla
		filteredOrders = baseForKpis.filter(function (o) {
			if (statusVal === 'all') return true;
			if (statusVal === 'open') return o.status !== 'dispatched' && o.status !== 'cancelled';
			if (statusVal === 'to_prepare') return o.status === 'confirmed' || o.status === 'picking';
			if (statusVal === 'to_dispatch') return o.status === 'picked' || o.status === 'packed';
			return o.status === statusVal;
		});

		// Ordenar
		filteredOrders.sort(function (a, b) {
			if (sortVal === 'total_desc') {
				return (parseFloat(b.total) || 0) - (parseFloat(a.total) || 0);
			}
			if (sortVal === 'delivery_asc') {
				var da = a.requested_date || '9999-12-31';
				var db = b.requested_date || '9999-12-31';
				return da < db ? -1 : (da > db ? 1 : (b.id - a.id));
			}
			return parseInt(b.id, 10) - parseInt(a.id, 10);
		});

		renderOrdersTable();

		// Asegurar que haya un pedido seleccionado válido dentro de los resultados visibles
		var stillVisible = filteredOrders.some(function (o) { return parseInt(o.id, 10) === currentOrderId; });
		if (!stillVisible) {
			currentOrderId = filteredOrders.length > 0 ? parseInt(filteredOrders[0].id, 10) : null;
			renderSelectedOrderDetail();
		} else {
			$('#orders-list-tbody tr.order-row').removeClass('is-selected');
			$('#orders-list-tbody tr.order-row[data-id="' + currentOrderId + '"]').addClass('is-selected');
		}
	}

	function renderOrdersTable() {
		$('#orders-list-heading').text('Pedidos (' + filteredOrders.length + ')');
		var $tbody = $('#orders-list-tbody');
		$tbody.empty();

		if (filteredOrders.length === 0) {
			$tbody.html(
				'<tr><td colspan="5" class="text-center text-muted py-5">' +
					'<i class="fa-solid fa-inbox fa-2x mb-2 d-block text-slate-300"></i>' +
					'<div class="font-weight-bold text-slate-700">No hay pedidos que coincidan con los filtros</div>' +
					'<div class="small mt-1">Prueba cambiar el estado a "Todos" o haz clic en "Limpiar".</div>' +
				'</td></tr>'
			);
			return;
		}

		filteredOrders.forEach(function (o) {
			var oid = parseInt(o.id, 10);
			var isSel = (oid === currentOrderId);
			var delivDateText = o.requested_date ? ('Entrega ' + fmtDateDayMonth(o.requested_date)) : 'Sin fecha compromiso';
			var rowHtml =
				'<tr class="order-row ' + (isSel ? 'is-selected' : '') + '" data-id="' + oid + '">' +
					'<td>' +
						'<span class="order-code-link">' + esc(o.order_code) + '</span>' +
						'<div class="order-sub-meta">' + esc(o.branch_name) + ' · ' + (o.lines || 0) + ' línea(s)</div>' +
					'</td>' +
					'<td>' +
						'<div class="font-weight-bold text-slate-800">' + esc(o.customer_name) + '</div>' +
						'<div class="order-sub-meta">' + esc(o.branch_name) + ' · ' + esc(o.delivery_type_label || 'Retiro en tienda') + '</div>' +
					'</td>' +
					'<td>' +
						'<div class="font-weight-bold text-slate-800">' + esc(fmtDateShort(o.created_at)) + '</div>' +
						'<div class="order-sub-meta">' + esc(delivDateText) + '</div>' +
					'</td>' +
					'<td class="text-right">' +
						'<div class="font-weight-bold text-slate-900">' + fmtMoney(o.total) + '</div>' +
						'<div class="order-sub-meta">' + esc(o.payment_label || 'Por facturar') + '</div>' +
					'</td>' +
					'<td class="text-right">' +
						'<div class="d-inline-flex align-items-center" style="gap:8px;">' +
							'<span class="order-status-pill st-' + esc(o.status) + '">' + esc(o.status_label) + '</span>' +
							'<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>' +
						'</div>' +
					'</td>' +
				'</tr>';
			$tbody.append(rowHtml);
		});
	}

	// =========================================================================
	// 2. RENDERIZADO DINÁMICO DEL PANEL DE DETALLE Y SEGUIMIENTO
	// =========================================================================
	function renderSelectedOrderDetail() {
		var $panel = $('#orders-detail-content');
		$('#or-detail').attr('data-id', currentOrderId || '');

		if (!currentOrderId || !detailsMap[currentOrderId]) {
			$panel.html(
				'<div class="text-center py-5 my-auto text-muted">' +
					'<i class="fa-solid fa-box-open fa-2x mb-2 d-block text-slate-300"></i>' +
					'<div class="font-weight-bold text-slate-700">Ningún pedido seleccionado</div>' +
					'<div class="small mt-1">Selecciona un pedido de la lista izquierda para consultar su disponibilidad, reserva FEFO y acciones operativas.</div>' +
				'</div>'
			);
			return;
		}

		var det = detailsMap[currentOrderId];
		var o = det.order;
		var items = det.items || [];
		var stepOrder = { confirmed: 1, picking: 1, picked: 2, packed: 3, dispatching: 4, dispatched: 4 };
		var currRank = stepOrder[o.status] || 0;
		var can = D.can || {};

		// Menú Más Acciones
		var menuItemsHtml = '';
		if (o.status === 'draft' && can.manage) {
			menuItemsHtml += '<a class="dropdown-item py-2 or-act" href="#" data-action="confirm" data-id="' + o.id + '"><i class="fa-solid fa-lock text-primary mr-2"></i>Confirmar y reservar stock</a>';
		}
		if ((o.status === 'confirmed' || o.status === 'picking') && can.pick) {
			menuItemsHtml += '<a class="dropdown-item py-2" href="' + baseUrl + '/scan?pick=' + o.id + '"><i class="fa-solid fa-barcode text-warning mr-2"></i>Preparar con escáner (Picking)</a>';
			menuItemsHtml += '<a class="dropdown-item py-2 or-act" href="#" data-action="pick-fefo" data-id="' + o.id + '"><i class="fa-solid fa-bolt text-success mr-2"></i>Asignar lotes FEFO sugeridos</a>';
		}
		if (o.status === 'picked' && can.pick) {
			menuItemsHtml += '<a class="dropdown-item py-2" href="#" id="or-pack" data-id="' + o.id + '"><i class="fa-solid fa-box text-primary mr-2"></i>Registrar empaque (Bultos)</a>';
		}
		if (o.status === 'packed' && can.dispatch) {
			menuItemsHtml += '<a class="dropdown-item py-2 or-act" href="#" data-action="dispatch" data-id="' + o.id + '"><i class="fa-solid fa-truck-fast text-success mr-2"></i>Despachar y generar venta</a>';
		}
		menuItemsHtml += '<a class="dropdown-item py-2" href="#" id="btn-menu-prep-detail" data-id="' + o.id + '"><i class="fa-solid fa-clipboard-check text-info mr-2"></i>Ver lotes FEFO e historial</a>';
		menuItemsHtml += '<a class="dropdown-item py-2" href="' + baseUrl + '/dispatch?id=' + o.id + '"><i class="fa-solid fa-route text-secondary mr-2"></i>Abrir en módulo de Despachos</a>';
		if (o.sale_id) {
			menuItemsHtml += '<a class="dropdown-item py-2" href="' + baseUrl + '/sales?id=' + o.sale_id + '"><i class="fa-solid fa-receipt text-success mr-2"></i>Ver comprobante (' + esc(o.sale_number) + ')</a>';
		}
		if (o.status !== 'dispatched' && o.status !== 'dispatching' && o.status !== 'cancelled' && can.manage) {
			menuItemsHtml += '<div class="dropdown-divider"></div><a class="dropdown-item py-2 text-danger" href="#" id="or-cancel" data-id="' + o.id + '"><i class="fa-solid fa-ban mr-2"></i>Anular pedido y liberar reserva</a>';
		}

		// Línea de tiempo
		var stepperHtml = '';
		if (o.status === 'cancelled') {
			stepperHtml =
				'<div class="alert alert-danger mb-0 py-2 px-3 small" style="border-radius:10px;">' +
					'<i class="fa-solid fa-ban mr-1"></i>' +
					'<strong>Pedido anulado:</strong> ' + esc(o.cancel_reason || 'Sin motivo especificado') +
					' (' + esc(fmtStepTime(o.cancelled_at)) + ') · Reserva de inventario liberada.' +
				'</div>';
		} else {
			var draftStep = o.status === 'draft'
				? '<div class="orders-step-item is-current">' +
						'<div class="orders-step-circle"><i class="fa-solid fa-pen"></i></div>' +
						'<div class="orders-step-title">Borrador</div>' +
						'<div class="orders-step-time">' + esc(fmtStepTime(o.created_at)) + '</div>' +
				  '</div>'
				: '';

			stepperHtml =
				'<div class="orders-stepper-wrap">' +
					'<div class="orders-stepper">' +
						draftStep +
						'<div class="orders-step-item ' + (currRank >= 1 ? 'is-done' : 'is-pending') + '">' +
							'<div class="orders-step-circle"><i class="fa-solid ' + (currRank >= 1 ? 'fa-check' : 'fa-clock') + '"></i></div>' +
							'<div class="orders-step-title">Confirmado</div>' +
							'<div class="orders-step-time">' + esc(o.confirmed_at ? fmtStepTime(o.confirmed_at) : 'Reserva stock') + '</div>' +
						'</div>' +
						'<div class="orders-step-item ' + (currRank >= 2 ? 'is-done' : (o.status === 'picking' ? 'is-current' : 'is-pending')) + '">' +
							'<div class="orders-step-circle"><i class="fa-solid ' + (currRank >= 2 ? 'fa-check' : 'fa-barcode') + '"></i></div>' +
							'<div class="orders-step-title">' + (o.status === 'picking' ? 'En preparación' : 'Preparado') + '</div>' +
							'<div class="orders-step-time">' + esc(o.picked_at ? fmtStepTime(o.picked_at) : 'Picking FEFO') + '</div>' +
						'</div>' +
						'<div class="orders-step-item ' + (currRank >= 3 ? 'is-done' : 'is-pending') + '">' +
							'<div class="orders-step-circle"><i class="fa-solid ' + (currRank >= 3 ? 'fa-check' : 'fa-box') + '"></i></div>' +
							'<div class="orders-step-title">Empacado</div>' +
							'<div class="orders-step-time">' + esc(o.packed_at ? fmtStepTime(o.packed_at) : (o.packages ? o.packages + ' bulto(s)' : 'Pendiente')) + '</div>' +
						'</div>' +
						'<div class="orders-step-item ' + (currRank >= 4 ? 'is-done' : 'is-pending') + '">' +
							'<div class="orders-step-circle"><i class="fa-solid ' + (currRank >= 4 ? 'fa-check' : 'fa-clock') + '"></i></div>' +
							'<div class="orders-step-title">Despachado</div>' +
							'<div class="orders-step-time">' + esc(o.dispatched_at ? fmtStepTime(o.dispatched_at) : 'Venta y Kardex') + '</div>' +
						'</div>' +
					'</div>' +
				'</div>';
		}

		// Filas de productos
		var prodRowsHtml = items.map(function (it) {
			var unitShort = String(it.unit_name || 'pza').toLowerCase();
			var phys = parseFloat(it.physical_available) || 0;
			var req = parseFloat(it.amount) || 0;
			var picked = parseFloat(it.picked_amount) || 0;
			var res = parseFloat(it.reserved_amount) || 0;
			var dotClass = phys <= 0 ? 'is-zero' : (phys < req ? 'is-low' : '');
			var firstPick = (it.item_picks && it.item_picks.length) ? it.item_picks[0] : null;
			var firstSug = (it.suggested && it.suggested.length) ? it.suggested[0] : null;

			var lotInfo = '';
			if (firstPick) {
				lotInfo = ' · <span class="text-success font-weight-bold">Lote ' + esc(firstPick.lot_number || 'FEFO') + '</span>';
			} else if (firstSug) {
				var bbdTxt = firstSug.best_before_date === '2999-12-31' ? 'N/A' : firstSug.best_before_date;
				lotInfo = ' · <span class="text-info">Sug. FEFO: ' + esc(firstSug.lot_number || 'Lote') + ' (vence ' + esc(bbdTxt) + ')</span>';
			}

			var prepDiff = ((o.status === 'picked' || o.status === 'packed' || o.status === 'dispatched') && Math.abs(picked - req) > 0.001)
				? '<div class="small text-warning font-weight-bold">Prep: ' + picked + '</div>'
				: '';

			var resCell = '';
			if (res > 0) {
				resCell = res + ' ' + esc(unitShort);
			} else if (o.status === 'dispatched') {
				resCell = '<span class="text-success small font-weight-bold">Despachado</span>';
			} else {
				resCell = '<span class="text-muted small">0 ' + esc(unitShort) + '</span>';
			}

			return (
				'<tr>' +
					'<td>' +
						'<div class="orders-prod-cell">' +
							'<img src="' + baseUrl + esc(it.image_url) + '" alt="" class="orders-prod-thumb">' +
							'<div>' +
								'<div class="font-weight-bold text-slate-900">' + esc(it.product_name) + '</div>' +
								'<div class="small text-muted" style="font-size:0.68rem;">' + fmtMoney(it.unit_price) + ' / ' + esc(unitShort) + lotInfo + '</div>' +
							'</div>' +
						'</div>' +
					'</td>' +
					'<td class="text-center font-weight-bold">' + req + ' ' + esc(unitShort) + prepDiff + '</td>' +
					'<td class="text-center"><span class="orders-avail-dot ' + dotClass + '"></span><span class="text-slate-700 font-weight-bold">' + phys + ' ' + esc(unitShort) + '</span></td>' +
					'<td class="text-right font-weight-bold">' + resCell + '</td>' +
				'</tr>'
			);
		}).join('');

		// Botón principal del pie según estado
		var primaryBtnHtml = '';
		if (o.status === 'draft' && can.manage) {
			primaryBtnHtml = '<button type="button" class="orders-btn-primary-act or-act" data-action="confirm" data-id="' + o.id + '"><i class="fa-solid fa-lock"></i><span>Confirmar y reservar</span></button>';
		} else if ((o.status === 'confirmed' || o.status === 'picking') && can.pick) {
			primaryBtnHtml =
				'<a href="' + baseUrl + '/scan?pick=' + o.id + '" class="orders-btn-secondary-act"><i class="fa-solid fa-barcode text-warning"></i><span>Picking con escáner</span></a>' +
				'<button type="button" class="orders-btn-primary-act or-act" data-action="pick-fefo" data-id="' + o.id + '"><i class="fa-solid fa-bolt"></i><span>Completar preparación FEFO</span></button>';
		} else if (o.status === 'picked' && can.pick) {
			primaryBtnHtml = '<button type="button" class="orders-btn-primary-act" id="btn-footer-pack" data-id="' + o.id + '"><i class="fa-solid fa-box"></i><span>Empacar pedido</span></button>';
		} else if (o.status === 'packed' && can.dispatch) {
			primaryBtnHtml = '<button type="button" class="orders-btn-primary-act" id="btn-prepare-dispatch" data-id="' + o.id + '"><i class="fa-solid fa-truck"></i><span>Preparar despacho</span></button>';
		} else if (o.status === 'dispatched' && o.sale_id) {
			primaryBtnHtml = '<a href="' + baseUrl + '/sales?id=' + o.sale_id + '" class="orders-btn-primary-act"><i class="fa-solid fa-receipt"></i><span>Ver comprobante ' + esc(o.sale_number) + '</span></a>';
		}

		var payPillClass = o.payment_status === 'invoiced' ? 'st-dispatched' : (o.payment_status === 'cancelled' ? 'st-cancelled' : 'st-confirmed');
		var noteBadge = o.notes
			? '<span class="small text-muted" title="' + esc(o.notes) + '"><i class="fa-regular fa-comment-dots mr-1"></i>' + esc(String(o.notes).substring(0, 48)) + '</span>'
			: '';

		var html =
			'<div class="orders-detail-top">' +
				'<div>' +
					'<div class="orders-detail-code-row">' +
						'<h3 class="orders-detail-code">' + esc(o.order_code) + '</h3>' +
						'<span class="order-status-pill st-' + esc(o.status) + '">' + esc(o.status_label) + '</span>' +
						(o.has_shortage ? '<span class="badge badge-warning font-weight-bold">Con diferencia / faltante</span>' : '') +
					'</div>' +
					'<div class="orders-detail-customer"><strong>' + esc(o.customer_name) + '</strong> · ' + esc(o.branch_name) + '</div>' +
					'<div class="orders-detail-dates">Creado el ' + esc(fmtStepTime(o.created_at)) + ' · Entrega estimada: ' + esc(o.requested_date ? fmtDateShort(o.requested_date) : 'Sin fecha comprometida') + '</div>' +
				'</div>' +
				'<div class="dropdown">' +
					'<button type="button" class="orders-btn-more dropdown-toggle" data-toggle="dropdown" aria-expanded="false">' +
						'<i class="fa-solid fa-ellipsis"></i><span>Más acciones</span>' +
					'</button>' +
					'<div class="dropdown-menu dropdown-menu-right shadow border-0" style="border-radius:11px; font-size:0.81rem; min-width:230px;">' +
						menuItemsHtml +
					'</div>' +
				'</div>' +
			'</div>' +
			stepperHtml +
			'<div class="orders-detail-split">' +
				'<div>' +
					'<div class="d-flex align-items-center justify-content-between mb-2">' +
						'<div class="orders-prod-box-title mb-0">Productos (' + items.length + ')</div>' +
						noteBadge +
					'</div>' +
					'<div class="orders-prod-table-wrap">' +
						'<table class="orders-prod-table">' +
							'<thead><tr><th>PRODUCTO</th><th class="text-center">CANTIDAD</th><th class="text-center">DISPONIBILIDAD</th><th class="text-right">RESERVA</th></tr></thead>' +
							'<tbody>' + prodRowsHtml + '</tbody>' +
						'</table>' +
					'</div>' +
				'</div>' +
				'<div class="orders-summary-sidebox">' +
					'<div class="orders-sum-block">' +
						'<div class="sum-label">Total del pedido</div>' +
						'<div class="sum-total">' + fmtMoney(det.total) + '</div>' +
						'<div class="small text-muted mt-1" style="font-size:0.68rem;">Subtotal: ' + fmtMoney(det.total) + ' · Desc: $0.00</div>' +
					'</div>' +
					'<div class="orders-sum-meta-item">' +
						'<i class="fa-regular fa-calendar"></i>' +
						'<div>' +
							'<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Fecha de entrega</div>' +
							'<div class="font-weight-bold text-slate-900">' + esc(o.requested_date ? fmtDateShort(o.requested_date) : 'Sin fecha') + '</div>' +
						'</div>' +
					'</div>' +
					'<div class="orders-sum-meta-item">' +
						'<i class="fa-regular fa-credit-card"></i>' +
						'<div>' +
							'<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Estado de pago</div>' +
							'<span class="order-status-pill ' + payPillClass + '" style="margin-top:3px;">' + esc(o.payment_label) + '</span>' +
						'</div>' +
					'</div>' +
					'<div class="orders-sum-meta-item">' +
						'<i class="fa-solid fa-box-archive"></i>' +
						'<div>' +
							'<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Entrega y empaque</div>' +
							'<div class="font-weight-bold text-slate-800" style="font-size:0.76rem;">' + esc(o.delivery_type_label || 'Retiro en tienda') + (o.packages ? ' · ' + o.packages + ' bulto(s)' : '') + '</div>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="orders-detail-footer">' +
				'<button type="button" class="orders-btn-secondary-act" id="btn-open-prep-modal" data-id="' + o.id + '">' +
					'<i class="fa-solid fa-clipboard-list text-slate-600"></i><span>Ver preparación</span>' +
				'</button>' +
				primaryBtnHtml +
			'</div>';

		$panel.html(html);
	}

	// Eventos de búsqueda, filtros y selección de fila
	$('#orders-search-input').on('input', applyFiltersAndRender);
	$('#filter-branch, #filter-status, #filter-date, #filter-payment, #filter-delivery, #orders-sort-select').on('change', applyFiltersAndRender);

	$('#btn-clear-filters').on('click', function () {
		$('#orders-search-input').val('');
		$('#filter-branch').val('');
		$('#filter-status').val('open');
		$('#filter-date').val('30d');
		$('#filter-payment').val('all');
		$('#filter-delivery').val('all');
		$('#orders-sort-select').val('recent');
		$('.orders-kpi-card').removeClass('is-active-filter');
		applyFiltersAndRender();
	});

	$('.orders-kpi-card').on('click', function () {
		var f = $(this).data('kpi-filter');
		$('.orders-kpi-card').removeClass('is-active-filter');
		$(this).addClass('is-active-filter');
		$('#filter-status').val(f);
		applyFiltersAndRender();
	});

	$('.orders-priority-pill').on('click', function () {
		var pf = $(this).data('priority-filter');
		if (pf === 'to_dispatch' || pf === 'ready') {
			$('#filter-status').val('to_dispatch');
			$('#filter-date').val('all');
		} else if (pf === 'overdue') {
			$('#filter-status').val('open');
			$('#filter-date').val('overdue');
		}
		applyFiltersAndRender();
	});

	$('#btn-view-all-priorities').on('click', function (e) {
		e.preventDefault();
		$('#filter-status').val('open');
		$('#filter-date').val('all');
		applyFiltersAndRender();
	});

	$(document).on('click', '#orders-list-tbody tr.order-row', function () {
		var oid = parseInt($(this).data('id'), 10);
		if (!oid) return;
		currentOrderId = oid;
		$('#orders-list-tbody tr.order-row').removeClass('is-selected');
		$(this).addClass('is-selected');
		renderSelectedOrderDetail();
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', baseUrl + '/orders?id=' + oid);
		}
	});

	// =========================================================================
	// 3. MODAL «VER PREPARACIÓN»: LOTES FEFO SUGERIDOS / PREPARADOS E HISTORIAL
	// =========================================================================
	function openPrepModal(orderId) {
		var det = detailsMap[orderId];
		if (!det) return;
		var o = det.order;
		var items = det.items || [];
		var picks = det.picks || [];
		var history = o.history || [];

		$('#prep-modal-title').text('Seguimiento de Preparación y Lotes FEFO · ' + o.order_code);

		var linesRows = items.map(function (it) {
			var unit = it.unit_name || 'pza';
			var sugs = (it.suggested || []).map(function (sg) {
				var bbd = sg.best_before_date === '2999-12-31' ? 'Sin venc.' : sg.best_before_date;
				return '<span class="badge badge-light border mr-1">' + esc(sg.lot_number || 'Lote') + ' · ' + esc(sg.location_name) + ' · vence ' + esc(bbd) + ' (libre ' + sg.free + ')</span>';
			}).join('');
			return (
				'<tr>' +
					'<td class="font-weight-bold">' + esc(it.product_name) + '</td>' +
					'<td class="text-center">' + it.amount + ' ' + esc(unit) + '</td>' +
					'<td class="text-center font-weight-bold ' + (it.picked_amount >= it.amount ? 'text-success' : 'text-warning') + '">' + it.picked_amount + ' ' + esc(unit) + '</td>' +
					'<td class="text-center">' + it.free_available + ' ' + esc(unit) + '</td>' +
					'<td class="small">' + (sugs || '<span class="text-muted">Completado / Sin pendiente</span>') + '</td>' +
				'</tr>'
			);
		}).join('');

		var picksSection = '';
		if (picks.length > 0) {
			var pickRows = picks.map(function (p) {
				var fefoBadge = parseInt(p.fefo_ok, 10) === 1
					? '<span class="badge badge-success">✓ FEFO OK</span>'
					: '<span class="badge badge-warning">Fuera de FEFO</span>';
				return (
					'<tr>' +
						'<td>' + esc(p.product_name) + '</td>' +
						'<td><code>' + esc(p.lot_number || p.stock_id) + '</code></td>' +
						'<td>' + esc(p.location_name) + '</td>' +
						'<td class="text-center font-weight-bold">' + p.amount + '</td>' +
						'<td class="text-center">' + fefoBadge + '</td>' +
						'<td class="small text-muted">' + esc(p.username || 'Operador') + ' · ' + esc(p.picked_at || '') + '</td>' +
					'</tr>'
				);
			}).join('');
			picksSection =
				'<h6 class="font-weight-bold text-slate-800 mt-3 mb-2" style="font-size:0.83rem;"><i class="fa-solid fa-boxes-stacked text-success mr-1"></i>Lotes Asignados en Picking (' + picks.length + ')</h6>' +
				'<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:0.79rem;">' +
					'<thead class="bg-light"><tr><th>Producto</th><th>Lote</th><th>Ubicación</th><th class="text-center">Cant.</th><th class="text-center">FEFO</th><th>Registrado por</th></tr></thead>' +
					'<tbody>' + pickRows + '</tbody>' +
				'</table></div>';
		}

		var histRows = history.map(function (h) {
			return (
				'<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3" style="font-size:0.79rem;">' +
					'<div><strong class="text-slate-900">' + esc(h.step) + '</strong> <span class="text-muted">· ' + esc(h.detail) + '</span></div>' +
					'<span class="small text-muted">' + esc(h.user) + ' · ' + esc(h.timestamp) + '</span>' +
				'</li>'
			);
		}).join('');

		var bodyHtml =
			'<div class="mb-2 d-flex justify-content-between align-items-center flex-wrap">' +
				'<div class="small text-muted">Cliente: <strong>' + esc(o.customer_name) + '</strong> · Sucursal: <strong>' + esc(o.branch_name) + '</strong></div>' +
				'<span class="order-status-pill st-' + esc(o.status) + '">' + esc(o.status_label) + '</span>' +
			'</div>' +
			'<h6 class="font-weight-bold text-slate-800 mb-2" style="font-size:0.83rem;"><i class="fa-solid fa-list-check text-primary mr-1"></i>Avance por Línea y Sugerencia FEFO</h6>' +
			'<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:0.79rem;">' +
				'<thead class="bg-light"><tr><th>Producto</th><th class="text-center">Solicitado</th><th class="text-center">Preparado</th><th class="text-center">Libre Sucursal</th><th>Lote Sugerido (FEFO)</th></tr></thead>' +
				'<tbody>' + linesRows + '</tbody>' +
			'</table></div>' +
			picksSection +
			'<h6 class="font-weight-bold text-slate-800 mt-3 mb-2" style="font-size:0.83rem;"><i class="fa-solid fa-clock-rotate-left text-secondary mr-1"></i>Historial de Auditoría del Pedido</h6>' +
			'<ul class="list-group list-group-flush border rounded">' + histRows + '</ul>';

		$('#prep-modal-body').html(bodyHtml);

		var footerHtml = '<button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cerrar</button><div class="d-flex" style="gap:8px;">';
		if (o.status === 'confirmed' || o.status === 'picking') {
			footerHtml += '<a href="' + baseUrl + '/scan?pick=' + o.id + '" class="btn btn-warning btn-sm font-weight-bold"><i class="fa-solid fa-barcode mr-1"></i>Abrir en Escáner</a>';
			footerHtml += '<button type="button" class="btn btn-success btn-sm font-weight-bold or-act" data-action="pick-fefo" data-id="' + o.id + '"><i class="fa-solid fa-bolt mr-1"></i>Completar Preparación FEFO</button>';
		} else {
			footerHtml += '<a href="' + baseUrl + '/scan?pick=' + o.id + '" class="btn btn-outline-primary btn-sm font-weight-bold"><i class="fa-solid fa-barcode mr-1"></i>Ver en Operaciones con Escáner</a>';
		}
		footerHtml += '</div>';
		$('#prep-modal-footer').html(footerHtml);

		$('#modal-order-prep').modal('show');
	}

	$(document).on('click', '#btn-open-prep-modal, #btn-menu-prep-detail', function (e) {
		e.preventDefault();
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		openPrepModal(oid);
	});

	// =========================================================================
	// 4. CREAR NUEVO PEDIDO CON CONSULTA DE DISPONIBILIDAD ANTES DE CONFIRMAR
	// =========================================================================
	function buildProductLineHtml(branchId) {
		var bId = parseInt(branchId, 10) || (D.branches && D.branches[0] ? parseInt(D.branches[0].id, 10) : 1);
		var opts = '<option value="">Seleccionar producto…</option>' + (D.products || []).map(function (p) {
			var free = (p.free_by_branch && p.free_by_branch[bId] != null) ? parseFloat(p.free_by_branch[bId]) : 0;
			var price = parseFloat(p.list_price) || 0;
			return '<option value="' + p.id + '" data-price="' + price.toFixed(2) + '" data-free="' + free + '" data-unit="' + esc(p.unit_name || 'pza') + '">' +
				esc(p.name) + ' · $' + price.toFixed(2) + ' (Libre: ' + free + ' ' + esc(p.unit_name || 'pza') + ')' +
				'</option>';
		}).join('');

		return (
			'<div class="form-row align-items-center or-line mb-2 pb-2 border-bottom">' +
				'<div class="col-md-6 mb-1 mb-md-0">' +
					'<select class="custom-select custom-select-sm or-product" aria-label="Producto">' + opts + '</select>' +
					'<div class="small text-muted mt-1 or-avail-hint">Selecciona un producto para ver disponibilidad</div>' +
				'</div>' +
				'<div class="col-4 col-md-2">' +
					'<input type="number" min="0.1" step="1" class="form-control form-control-sm or-amount font-weight-bold" value="1" placeholder="Cant." aria-label="Cantidad">' +
				'</div>' +
				'<div class="col-6 col-md-3 text-right">' +
					'<div class="font-weight-bold text-slate-900 or-line-subtotal">$0.00</div>' +
					'<div class="small text-muted or-line-unitprice">P. lista</div>' +
				'</div>' +
				'<div class="col-2 col-md-1 text-right">' +
					'<button type="button" class="btn btn-sm btn-outline-danger or-del" aria-label="Quitar línea"><i class="fa-solid fa-trash-can"></i></button>' +
				'</div>' +
			'</div>'
		);
	}

	function updateNewOrderModalTotals($dlg) {
		var bId = parseInt($dlg.find('#or-branch').val(), 10) || 1;
		var grandTotal = 0;
		var hasStockWarning = false;

		$dlg.find('.or-line').each(function () {
			var $row = $(this);
			var pid = parseInt($row.find('.or-product').val(), 10);
			var qty = parseFloat($row.find('.or-amount').val()) || 0;
			var prodObj = (D.products || []).find(function (p) { return parseInt(p.id, 10) === pid; });

			if (!prodObj) {
				$row.find('.or-avail-hint').html('<span class="text-muted">Selecciona un producto para consultar stock libre</span>');
				$row.find('.or-line-subtotal').text('$0.00');
				$row.find('.or-line-unitprice').text('P. lista');
				return;
			}

			var free = (prodObj.free_by_branch && prodObj.free_by_branch[bId] != null) ? parseFloat(prodObj.free_by_branch[bId]) : 0;
			var price = parseFloat(prodObj.list_price) || 0;
			var unit = prodObj.unit_name || 'pza';
			var sub = qty * price;
			grandTotal += sub;

			$row.find('.or-line-subtotal').text(fmtMoney(sub));
			$row.find('.or-line-unitprice').text(fmtMoney(price) + ' / ' + unit);

			if (qty > free + 0.0001) {
				hasStockWarning = true;
				$row.find('.or-avail-hint').html(
					'<span class="text-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Solicitado (' + qty + ') supera el stock libre en sucursal (' + free + ' ' + esc(unit) + ')</span>'
				);
			} else {
				$row.find('.or-avail-hint').html(
					'<span class="text-success font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i>Disponible para reservar: ' + free + ' ' + esc(unit) + '</span>'
				);
			}
		});

		$dlg.find('#or-new-grand-total').text(fmtMoney(grandTotal));
		$dlg.find('#or-stock-warning-banner').toggleClass('d-none', !hasStockWarning);
	}

	$('#or-new').on('click', function () {
		var defaultBranch = $('#filter-branch').val() || D.branchId || (D.branches && D.branches[0] ? D.branches[0].id : 1);
		var todayIso = new Date().toISOString().substring(0, 10);

		var html =
			'<div class="form-row">' +
				'<div class="col-md-6 form-group">' +
					'<label class="small font-weight-bold" for="or-branch">Sucursal de despacho</label>' +
					'<select id="or-branch" name="branch_id" class="custom-select custom-select-sm">' +
						(D.branches || []).map(function (b) {
							return '<option value="' + b.id + '" ' + (String(b.id) === String(defaultBranch) ? 'selected' : '') + '>' + esc(b.name) + '</option>';
						}).join('') +
					'</select>' +
				'</div>' +
				'<div class="col-md-6 form-group">' +
					'<label class="small font-weight-bold" for="or-customer">Cliente</label>' +
					'<select id="or-customer" name="customer_id" class="custom-select custom-select-sm">' +
						(D.customers || []).map(function (c) {
							return '<option value="' + c.id + '">' + esc(c.name) + (c.tax_id ? ' · ' + esc(c.tax_id) : '') + '</option>';
						}).join('') +
					'</select>' +
				'</div>' +
			'</div>' +
			'<div class="form-row">' +
				'<div class="col-md-4 form-group">' +
					'<label class="small font-weight-bold" for="or-date">Fecha comprometida</label>' +
					'<input type="date" id="or-date" name="requested_date" class="form-control form-control-sm" value="' + todayIso + '" min="' + todayIso + '">' +
				'</div>' +
				'<div class="col-md-4 form-group">' +
					'<label class="small font-weight-bold" for="or-delivery-type">Modalidad de entrega</label>' +
					'<select id="or-delivery-type" name="delivery_type" class="custom-select custom-select-sm">' +
						'<option value="pickup">Retiro en tienda (Pickup)</option>' +
						'<option value="delivery">Despacho a domicilio (Ruta)</option>' +
					'</select>' +
				'</div>' +
				'<div class="col-md-4 form-group">' +
					'<label class="small font-weight-bold" for="or-notes">Notas / Observaciones</label>' +
					'<input type="text" id="or-notes" name="notes" class="form-control form-control-sm" placeholder="Instrucciones de empaque o entrega">' +
				'</div>' +
			'</div>' +
			'<div class="d-flex justify-content-between align-items-center mb-2">' +
				'<label class="small font-weight-bold mb-0">Productos del pedido <span class="text-muted font-weight-normal">(verifica disponibilidad en tiempo real)</span></label>' +
				'<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" id="or-add"><i class="fa-solid fa-plus mr-1"></i>Agregar línea</button>' +
			'</div>' +
			'<div id="or-lines">' + buildProductLineHtml(defaultBranch) + '</div>' +
			'<div class="alert alert-warning small py-2 px-3 mt-2 d-none" id="or-stock-warning-banner">' +
				'<i class="fa-solid fa-triangle-exclamation mr-1"></i>' +
				'Una o más líneas superan el stock libre de la sucursal. Para confirmar y reservar debes ajustar la cantidad o guardar el pedido como <strong>Borrador</strong>.' +
			'</div>' +
			'<div class="d-flex justify-content-between align-items-center bg-light p-2 rounded border mt-2">' +
				'<div class="custom-control custom-checkbox">' +
					'<input type="checkbox" class="custom-control-input" id="or-confirm" name="confirm" value="1" checked>' +
					'<label class="custom-control-label small font-weight-bold" for="or-confirm">Confirmar pedido y reservar stock inmediatamente</label>' +
				'</div>' +
				'<div class="text-right">' +
					'<span class="small text-muted mr-1">Total estimado:</span>' +
					'<strong class="text-success" style="font-size:1.05rem;" id="or-new-grand-total">$0.00</strong>' +
				'</div>' +
			'</div>';

		var dlg = U.formDialog('Nuevo pedido de cliente', html, function (data, d) {
			data.items = d.find('.or-line').map(function () {
				return {
					product_id: $(this).find('.or-product').val(),
					amount: $(this).find('.or-amount').val()
				};
			}).get().filter(function (l) { return l.product_id && parseFloat(l.amount) > 0; });

			if (!data.items.length) {
				toastr.warning('Agrega al menos un producto válido al pedido.');
				return;
			}
			U.post('orders', data, function (r) {
				d.modal('hide');
				toastr.success('Pedido ' + r.order.order_code + ' ' + (r.order.status === 'confirmed' ? 'confirmado y stock reservado' : 'guardado como borrador'));
				goOrderUrl(r.order.id);
			}, d.find('.btn-primary'));
		}, 'Guardar pedido');

		dlg.find('.modal-dialog').addClass('modal-lg');
		dlg.on('click', '#or-add', function () {
			dlg.find('#or-lines').append(buildProductLineHtml(dlg.find('#or-branch').val()));
			updateNewOrderModalTotals(dlg);
		});
		dlg.on('click', '.or-del', function () {
			if (dlg.find('.or-line').length > 1) {
				$(this).closest('.or-line').remove();
				updateNewOrderModalTotals(dlg);
			}
		});
		dlg.on('change input', '#or-branch, .or-product, .or-amount', function () {
			updateNewOrderModalTotals(dlg);
		});
	});

	// =========================================================================
	// 5. ACCIONES DE FLUJO: CONFIRMAR, PREPARAR FEFO, EMPACAR, DESPACHAR, ANULAR
	// =========================================================================
	$(document).on('click', '.or-act', function (e) {
		e.preventDefault();
		var action = $(this).data('action');
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		var btn = this;
		if (!oid) return;

		var msgMap = {
			'confirm': 'Se verificará la disponibilidad libre y se reservará el stock para este pedido (el POS no podrá vender esas unidades). ¿Confirmar pedido?',
			'pick-fefo': 'Se asignarán automáticamente los lotes disponibles en orden estricto FEFO para todas las líneas del pedido. ¿Completar preparación?',
			'dispatch': 'Se registrará la venta del pedido y se descontarán del inventario exactamente los lotes preparados (una sola vez). ¿Confirmar despacho?'
		};
		var successMap = {
			'confirm': 'Pedido confirmado y stock reservado exitosamente',
			'pick-fefo': 'Preparación FEFO completada y lotes asignados',
			'dispatch': 'Pedido despachado y venta generada en Kardex'
		};

		bootbox.confirm(msgMap[action] || '¿Ejecutar esta acción?', function (ok) {
			if (ok) {
				$('#modal-order-prep').modal('hide');
				U.post('orders/' + oid + '/' + action, {}, function () {
					toastr.success(successMap[action] || 'Acción completada');
					goOrderUrl(oid);
				}, btn);
			}
		});
	});

	$(document).on('click', '#or-pack, #btn-footer-pack', function (e) {
		e.preventDefault();
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		if (!oid) return;
		var det = detailsMap[oid];
		var currentPackages = (det && det.order && det.order.packages) ? det.order.packages : 2;

		U.formDialog(
			'Registrar empaque del pedido',
			'<div class="form-group mb-2">' +
				'<label class="small font-weight-bold" for="or-packages">Cantidad de bultos / cajas térmicas selladas (1 a 999)</label>' +
				'<input type="number" min="1" max="999" id="or-packages" name="packages" class="form-control font-weight-bold" value="' + currentPackages + '" required>' +
			'</div>' +
			'<p class="small text-muted mb-0">Al marcar el pedido como <strong>Empacado</strong>, quedará listo en la cola de despacho.</p>',
			function (data, d) {
				U.post('orders/' + oid + '/pack', data, function () {
					d.modal('hide');
					toastr.success('Pedido empacado correctamente');
					goOrderUrl(oid);
				}, d.find('.btn-primary'));
			},
			'Confirmar empaque'
		);
	});

	// Modal de Preparar Despacho
	$(document).on('click', '#btn-prepare-dispatch', function (e) {
		e.preventDefault();
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		var det = detailsMap[oid];
		if (!det) return;
		var o = det.order;

		$('#btn-go-dispatch-module').attr('href', baseUrl + '/dispatch?id=' + o.id);
		$('#btn-confirm-dispatch-now').data('id', o.id);

		var bodyHtml =
			'<div class="p-2 mb-3 rounded border bg-light">' +
				'<div class="d-flex justify-content-between align-items-center">' +
					'<strong>' + esc(o.order_code) + ' · ' + esc(o.customer_name) + '</strong>' +
					'<span class="badge badge-success">' + fmtMoney(det.total) + '</span>' +
				'</div>' +
				'<div class="small text-muted mt-1">' +
					esc(o.branch_name) + ' · ' + esc(o.delivery_type_label || 'Retiro en tienda') +
					(o.packages ? ' · <strong>' + o.packages + ' bulto(s)</strong>' : '') +
				'</div>' +
			'</div>' +
			'<p class="small text-slate-700 mb-2">' +
				'<i class="fa-solid fa-shield-halved text-success mr-1"></i>' +
				'<strong>Control de inventario sin duplicación:</strong> La preparación reservó las unidades sin descontar stock físico. Al despachar se emite la venta y se descuenta una única vez sobre los lotes FEFO asignados.' +
			'</p>' +
			'<p class="small text-muted mb-0">' +
				'Puedes completar el despacho directamente aquí o abrir el módulo de <strong>Despachos y Validaciones</strong> para gestionar PIN/QR de retiro, transportista o incidencias de entrega.' +
			'</p>';

		$('#dispatch-modal-body').html(bodyHtml);
		$('#modal-order-dispatch').modal('show');
	});

	$('#btn-confirm-dispatch-now').on('click', function () {
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		var btn = this;
		if (!oid) return;
		U.post('orders/' + oid + '/dispatch', {}, function () {
			$('#modal-order-dispatch').modal('hide');
			toastr.success('Pedido despachado y venta registrada');
			goOrderUrl(oid);
		}, btn);
	});

	$(document).on('click', '#or-cancel', function (e) {
		e.preventDefault();
		var oid = parseInt($(this).data('id'), 10) || currentOrderId;
		if (!oid) return;
		U.formDialog(
			'Anular pedido y liberar reserva',
			'<div class="form-group mb-2">' +
				'<label class="small font-weight-bold" for="or-reason">Motivo de anulación</label>' +
				'<input type="text" id="or-reason" name="reason" class="form-control" placeholder="Ej. Solicitud del cliente / Cambio de programación" required>' +
			'</div>' +
			'<p class="small text-muted mb-0">Se liberará de inmediato la reserva de inventario para que las unidades vuelvan a estar disponibles en la sucursal.</p>',
			function (data, d) {
				U.post('orders/' + oid + '/cancel', data, function () {
					d.modal('hide');
					toastr.info('Pedido anulado y reserva liberada');
					goOrderUrl(oid);
				}, d.find('.btn-primary'));
			},
			'Confirmar anulación'
		);
	});

	// =========================================================================
	// 6. EXPORTACIÓN DE PEDIDOS FILTRADOS (CSV, TSV, IMPRIMIR)
	// =========================================================================
	function exportFilteredOrders(sep, ext) {
		if (!filteredOrders || filteredOrders.length === 0) {
			toastr.warning('No hay pedidos en la lista actual para exportar.');
			return;
		}
		var headers = ['Pedido', 'Cliente', 'Sucursal', 'Creado', 'Fecha Entrega', 'Lineas', 'Total', 'Estado', 'Estado Pago', 'Modalidad Entrega'];
		var lines = [headers.join(sep)];
		filteredOrders.forEach(function (o) {
			var row = [
				o.order_code || '',
				'"' + String(o.customer_name || '').replace(/"/g, '""') + '"',
				'"' + String(o.branch_name || '').replace(/"/g, '""') + '"',
				o.created_at || '',
				o.requested_date || '',
				o.lines || 0,
				(parseFloat(o.total) || 0).toFixed(2),
				o.status_label || o.status || '',
				o.payment_label || '',
				o.delivery_type_label || ''
			];
			lines.push(row.join(sep));
		});
		var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'daily-pedidos-clientes-' + new Date().toISOString().substring(0, 10) + '.' + ext;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	$('#btn-export-orders-csv').on('click', function (e) {
		e.preventDefault();
		exportFilteredOrders(',', 'csv');
	});

	$('#btn-export-orders-tsv').on('click', function (e) {
		e.preventDefault();
		exportFilteredOrders('\t', 'tsv');
	});

	$('#btn-export-orders-print').on('click', function (e) {
		e.preventDefault();
		window.print();
	});

	// Inicializar estado de tabla y KPIs al cargar (incluyendo contexto de cliente si viene desde /customers)
	var urlParams = new URLSearchParams(window.location.search);
	var filterCustId = urlParams.get('customer_id');
	if (filterCustId && Array.isArray(D.customers)) {
		var matchedCust = D.customers.find(function (c) { return String(c.id) === String(filterCustId); });
		if (matchedCust && matchedCust.name) {
			$('#orders-search-input').val(matchedCust.name);
			$('#filter-status').val('all');
		}
	}
	applyFiltersAndRender();
})(jQuery);
