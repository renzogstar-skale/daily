/**
 * DAILY - Despachos y Logística OMS/WMS (viewjs)
 * Ciclo canónico 7 etapas: Pedido -> Reserva -> Picking -> Packing -> Despacho -> Entrega -> Conciliación
 */
(function ($) {
	'use strict';

	var currentOrderId = window.CurrentOrderId || 0;
	var ordersMap = window.DispatchOrdersMap || {};

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function extractErr(xhr, fallback) {
		if (xhr && xhr.responseJSON) {
			return xhr.responseJSON.error_message || xhr.responseJSON.message || fallback;
		}
		return (xhr && xhr.responseText) || fallback;
	}

	var statusLabels = {
		draft: 'Pedido Nuevo',
		confirmed: 'Reservado',
		picking: 'En Picking',
		picked: 'Picking Validado',
		packed: 'Empacado / Etiquetado',
		dispatched: 'Despachado',
		cancelled: 'Cancelado'
	};

	function renderActionButtons(o) {
		var oid = o.id;
		var code = escapeHtml(o.order_code);
		var isPickup = (o.delivery_type === 'pickup');
		var st = o.status || 'draft';
		var delSt = o.delivery_status || 'pending';
		var html = '';

		if (st === 'draft') {
			html += '<button type="button" class="dp-btn-green" onclick="reserveOrder(' + oid + ')"><i class="fa-solid fa-lock"></i><span>Reservar stock</span></button>';
		}
		if (st === 'confirmed' || st === 'picking' || st === 'picked') {
			html += '<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalFastScanVerify"><i class="fa-solid fa-barcode"></i><span>Escanear UPC</span></button>';
			html += '<button type="button" class="dp-btn-green" onclick="openPackOrderModal(' + oid + ', \'' + code + '\')"><i class="fa-solid fa-box-open"></i><span>Empacar y Etiquetar</span></button>';
		}
		if (st === 'packed') {
			html += '<button type="button" class="dp-btn-green" onclick="openConfirmExitModal(' + oid + ', \'' + code + '\')"><i class="fa-solid fa-truck-fast"></i><span>Confirmar Salida</span></button>';
		}
		if (st === 'dispatched' && delSt !== 'delivered' && delSt !== 'partial' && delSt !== 'rejected') {
			if (isPickup) {
				html += '<button type="button" class="dp-btn-green" id="btnOpenVerifyPickupModal" data-toggle="modal" data-target="#modalVerifyPickup"><i class="fa-solid fa-key"></i><span>Validar retiro PIN</span></button>';
			} else {
				html += '<button type="button" class="dp-btn-green" id="btnOpenDeliveryModal" data-toggle="modal" data-target="#modalRecordDelivery"><i class="fa-solid fa-truck-arrow-right"></i><span>Registrar Entrega / POD</span></button>';
			}
		}

		html += '<button type="button" class="dp-btn-outline" onclick="openDispatchLabelModal(' + oid + ')"><i class="fa-solid fa-tag"></i><span>Etiqueta</span></button>';
		html += '<button type="button" class="dp-btn-cyan" onclick="openScheduleTransportModal(' + oid + ', \'' + code + '\')"><i class="fa-solid fa-truck"></i><span>Transporte</span></button>';
		html += '<button type="button" class="dp-btn-outline text-warning border-warning" onclick="openReportIncidentModal(' + oid + ', \'' + code + '\')"><i class="fa-solid fa-triangle-exclamation"></i><span>Incidencia</span></button>';

		if (st === 'dispatched' && !o.conciliated_at) {
			html += '<button type="button" class="dp-btn-outline font-weight-bold" onclick="conciliateOrder(' + oid + ')"><i class="fa-solid fa-scale-balanced"></i><span>Conciliar Cierre</span></button>';
		}
		$('.dp-detail-actions').html(html);
	}

	// 1. Selector reactivo de pedido
	function selectOrder(orderId) {
		currentOrderId = parseInt(orderId, 10) || 0;
		window.CurrentOrderId = currentOrderId;

		$('.dp-order-card').removeClass('is-active');
		$('.dp-order-card[data-id="' + orderId + '"]').addClass('is-active');

		var data = ordersMap[orderId] || ordersMap[String(orderId)];
		if (!data) return;

		var o = data.order;
		var isPickup = (o.delivery_type === 'pickup');
		var summary = data.summary || {};
		var lines = data.lines || [];
		var variances = data.variances || [];
		var incidents = data.incidents || [];
		var stages = o.stages || [];

		// Sync hidden order ids for modals
		$('#schedOrderId').val(o.id);
		$('#exitOrderId').val(o.id);
		$('#packOrderId').val(o.id);
		$('#incidentOrderId').val(o.id);
		$('#pickingOrderId').val(o.id);
		$('#inputCarrierName').val(o.carrier_name || '');

		// Header info
		$('#dpDetailCode').text(o.order_code);
		var typeTag = $('#dpDetailTypeTag');
		typeTag.removeClass('dp-tag-pickup dp-tag-delivery');
		if (isPickup) {
			typeTag.addClass('dp-tag-pickup').text('Retiro en tienda');
		} else {
			typeTag.addClass('dp-tag-delivery').text('Domicilio');
		}

		var stBadge = $('#dpDetailStatusBadge');
		stBadge.removeClass('st-packed st-dispatched st-prepared');
		var badgeText = statusLabels[o.status] || o.status;
		if (o.conciliated_at) {
			stBadge.addClass('st-dispatched').text('Conciliado');
		} else if (o.status === 'dispatched') {
			stBadge.addClass('st-dispatched').text(o.delivery_status === 'delivered' ? 'Entregado (POD)' : 'Despachado');
		} else if (o.status === 'picking' || o.status === 'picked') {
			stBadge.addClass('st-prepared').text(badgeText);
		} else {
			stBadge.addClass('st-packed').text(badgeText);
		}

		var metaHtml = 'Cliente: <strong class="text-dark">' + escapeHtml(o.customer_name || 'Cliente General') + '</strong> · Sucursal: <strong class="text-dark">' + escapeHtml(o.branch_name || 'Matriz') + '</strong>';
		if (o.label_code) {
			metaHtml += ' · Etiqueta: <strong class="text-success">' + escapeHtml(o.label_code) + '</strong>';
		}
		$('#dpDetailMeta').html(metaHtml);

		renderActionButtons(o);

		// 7-stage stepper track
		var stepperHtml = '';
		stages.forEach(function (stg, idx) {
			var cls = stg.state === 'done' ? 'is-done' : (stg.state === 'current' ? 'is-current' : '');
			var circleContent = stg.state === 'done' ? '<i class="fa-solid fa-check"></i>' : String(idx + 1);
			stepperHtml += '<div class="dp-stepper-step ' + cls + '"><div class="dp-step-circle">' + circleContent + '</div>';
			stepperHtml += '<div class="dp-step-label">' + (idx + 1) + '. ' + escapeHtml(stg.label) + '<br><small>' + escapeHtml(stg.sub) + '</small></div></div>';
		});
		$('#dpStepperTrack').html(stepperHtml);

		// Reconcile Table
		var show7 = $('#toggle7Stages').is(':checked');
		var rowsHtml = '';
		lines.forEach(function (l) {
			var stg = l.stages;
			var isPrepDiff = (stg['2_prepared'] < stg['1_requested']);
			var isRecvZero = (stg['5_received'] == 0 && stg['1_requested'] > 0);

			rowsHtml += '<tr>';
			rowsHtml += '<td><div class="dp-product-cell">';
			rowsHtml += '<img src="' + (l.product_image || '/img/control_center/prod_crema_leche.jpg') + '" alt="' + escapeHtml(l.product_name) + '" class="dp-prod-img">';
			rowsHtml += '<div><div class="dp-prod-name">' + escapeHtml(l.product_name) + '</div>';
			rowsHtml += '<div class="dp-prod-sku">SKU: ' + escapeHtml(l.sku) + ' · UPC: ' + escapeHtml(l.upc) + '</div>';
			if (l.sku_warning) {
				rowsHtml += '<div class="dp-badge-sku-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>Revisar identificación</span></div>';
			}
			rowsHtml += '</div></div></td>';
			rowsHtml += '<td class="dp-val-req">' + stg['1_requested'] + '</td>';
			rowsHtml += '<td class="dp-val-prep ' + (isPrepDiff ? 'is-diff' : '') + '">' + stg['2_prepared'] + '</td>';
			rowsHtml += '<td class="dp-val-scan">' + stg['3_scanned'] + '</td>';
			rowsHtml += '<td class="dp-val-disp">' + stg['4_dispatched'] + '</td>';
			rowsHtml += '<td class="dp-val-recv ' + (isRecvZero ? 'is-zero' : '') + '">' + stg['5_received'] + '</td>';
			rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + '">' + stg['6_returned'] + '</td>';
			rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + ' ' + (stg['7_final_difference'] != 0 ? 'text-danger font-weight-bold' : '') + '">' + stg['7_final_difference'] + '</td>';
			rowsHtml += '</tr>';
		});

		rowsHtml += '<tr class="dp-totals-row">';
		rowsHtml += '<td><div class="dp-totals-cell-left">TOTALES (Unidades)</div><div class="dp-totals-cell-sub">Cantidades reales por etapa.</div></td>';
		rowsHtml += '<td class="dp-val-req">' + (summary.total_requested || 0) + '</td>';
		rowsHtml += '<td class="dp-val-prep">' + (summary.total_prepared || 0) + '</td>';
		rowsHtml += '<td class="dp-val-scan">' + (summary.total_scanned || 0) + '</td>';
		rowsHtml += '<td class="dp-val-disp">' + (summary.total_dispatched || 0) + '</td>';
		rowsHtml += '<td class="dp-val-recv">' + (summary.total_received || 0) + '</td>';
		rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + '">' + (summary.total_returned || 0) + '</td>';
		rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + '">' + (summary.total_final_diff || 0) + '</td>';
		rowsHtml += '</tr>';

		$('#dpReconcileTable tbody').html(rowsHtml);

		// Pending variance default for modal
		var firstPendingVar = variances.find(function (v) { return v.resolution_status === 'pending'; });
		if (firstPendingVar) {
			$('#resolveVarianceId').val(firstPendingVar.id);
			$('#resolveVarianceProductName').text(firstPendingVar.product_name || 'Variación');
			$('#resolveVarianceDiffQty').text('Diferencia: ' + firstPendingVar.difference_qty);
		} else {
			$('#resolveVarianceId').val(0);
			$('#resolveVarianceProductName').text('Sin variaciones pendientes en este pedido');
			$('#resolveVarianceDiffQty').text('-');
		}

		// Amber Alert Box
		if (summary.has_unreconciled_shortage && summary.unreconciled_items && summary.unreconciled_items.length > 0) {
			var alertHtml = '<div class="dp-alert-unreconciled-left"><i class="fa-solid fa-triangle-exclamation dp-alert-unreconciled-icon"></i><div>';
			alertHtml += '<div class="dp-alert-unreconciled-title">Cantidades por conciliar</div>';
			alertHtml += '<div class="dp-alert-unreconciled-text">';
			summary.unreconciled_items.forEach(function (ui) {
				alertHtml += '<strong>' + escapeHtml(ui.product_name) + ':</strong> ' + ui.requested + ' solicitadas / ' + ui.prepared + ' preparadas.<br>';
			});
			alertHtml += '<span class="text-muted">Valide diferencias o registre incidencia antes de cerrar conciliación.</span></div></div></div>';
			alertHtml += '<button type="button" class="dp-btn-amber" data-toggle="modal" data-target="#modalResolveVariance"><i class="fa-solid fa-gavel"></i><span>Revisar diferencias</span></button>';

			if ($('#dpAlertUnreconciled').length === 0) {
				$('.dp-reconcile-wrap').after('<div class="dp-alert-unreconciled" id="dpAlertUnreconciled">' + alertHtml + '</div>');
			} else {
				$('#dpAlertUnreconciled').html(alertHtml).removeClass('d-none');
			}
		} else {
			$('#dpAlertUnreconciled').addClass('d-none');
		}

		// Update dynamic panes
		$('#pane-detalle .dp-info-notice .text-muted').html(
			'Pedido <strong class="text-dark">' + escapeHtml(o.order_code) + '</strong> · Canal: ' + escapeHtml(o.channel || 'POS') +
			' · Etiqueta: <strong>' + escapeHtml(o.label_code || 'Pendiente Packing') + '</strong> · Bultos: <strong>' + (o.packages_count || o.packages || 1) + '</strong>.'
		);

		var varPaneHtml = '';
		if (variances.length === 0 && incidents.length === 0) {
			varPaneHtml = '<div class="p-3 bg-light rounded text-center text-muted small">Sin variaciones ni incidencias logísticas registradas en este pedido.</div>';
		} else {
			variances.forEach(function (v) {
				varPaneHtml += '<div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center"><div>';
				varPaneHtml += '<strong class="text-dark">' + escapeHtml(v.product_name) + '</strong> ';
				varPaneHtml += '<span class="badge ' + (v.stage === 'preparation' ? 'badge-warning' : 'badge-danger') + ' ml-1">' + escapeHtml(v.variance_type) + '</span> ';
				varPaneHtml += '<span class="text-muted ml-2">Diferencia: <strong>' + v.difference_qty + '</strong></span>';
				varPaneHtml += '<div class="small text-muted">' + escapeHtml(v.resolution_notes || 'Pendiente autorización') + '</div></div><div>';
				if (v.resolution_status === 'pending') {
					varPaneHtml += '<button class="btn btn-xs btn-danger font-weight-bold" onclick="openResolveVarianceModal(' + v.id + ', \'' + escapeHtml(v.product_name) + '\', \'' + v.difference_qty + '\')">Resolver</button>';
				} else {
					varPaneHtml += '<span class="badge badge-success">' + escapeHtml(v.resolution_status) + '</span>';
				}
				varPaneHtml += '</div></div>';
			});
			incidents.forEach(function (inc) {
				varPaneHtml += '<div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center"><div>';
				varPaneHtml += '<span class="badge badge-warning mr-1">' + escapeHtml(inc.incident_type) + '</span> ';
				varPaneHtml += '<strong class="text-dark">' + escapeHtml(inc.description) + '</strong>';
				varPaneHtml += '<div class="small text-muted">' + escapeHtml(inc.created_at) + (inc.resolution_notes ? (' · Resolución: ' + escapeHtml(inc.resolution_notes)) : '') + '</div></div><div>';
				if ((inc.resolution_status || 'open') === 'open') {
					varPaneHtml += '<button class="btn btn-xs btn-warning font-weight-bold" onclick="openResolveIncidentModal(' + inc.id + ', \'' + escapeHtml(inc.description) + '\')">Cerrar Incidencia</button>';
				} else {
					varPaneHtml += '<span class="badge badge-success">Resuelta</span>';
				}
				varPaneHtml += '</div></div>';
			});
		}
		$('#pane-variaciones').html(varPaneHtml);

		var coldOk = parseInt(o.cold_chain_temp_ok || 0, 10) === 1;
		$('#pane-evidencia').html(
			'<div class="card p-3 border-0 bg-light"><div class="d-flex justify-content-between align-items-center mb-2">' +
			'<strong class="text-dark"><i class="fa-solid fa-file-signature text-success mr-1"></i>Prueba de Entrega (POD) y Cadena de Frío</strong>' +
			'<span class="badge ' + (coldOk ? 'badge-success' : 'badge-secondary') + '">' + (coldOk ? 'Frío Verificado (≤ 4°C)' : 'Por verificar en entrega') + '</span></div>' +
			'<p class="small text-muted mb-1">Transporte / Guía: <strong>' + escapeHtml(o.carrier_name || 'Sin asignar') + '</strong> · ' + escapeHtml(o.carrier_tracking_number || 'Sin guía') + '</p>' +
			'<p class="small text-muted mb-1">Firma / Receptor: <strong>' + escapeHtml(o.delivery_signature_name || 'Sin captura POD aún') + '</strong></p>' +
			'<p class="small text-muted mb-0">Folio / Evidencia POD: <strong>' + escapeHtml(o.delivery_evidence_url || 'Pendiente de evidencia') + '</strong></p></div>'
		);

		$('#pane-historial').html(
			'<ul class="list-unstyled small text-muted mb-0">' +
			'<li class="mb-1"><i class="fa-solid fa-file-circle-plus mr-1 text-primary"></i>1. Pedido registrado: <strong>' + escapeHtml(o.created_at) + '</strong></li>' +
			'<li class="mb-1"><i class="fa-solid fa-lock mr-1 text-info"></i>2. Reserva confirmada: <strong>' + escapeHtml(o.confirmed_at || 'Pendiente') + '</strong></li>' +
			'<li class="mb-1"><i class="fa-solid fa-barcode mr-1 text-success"></i>3. Picking / Escaneo: <strong>' + escapeHtml(o.picked_at || 'Pendiente') + '</strong></li>' +
			'<li class="mb-1"><i class="fa-solid fa-box-open mr-1 text-success"></i>4. Packing & Etiqueta: <strong>' + escapeHtml(o.packed_at || 'Pendiente') + (o.label_code ? ' (' + escapeHtml(o.label_code) + ')' : '') + '</strong></li>' +
			'<li class="mb-1"><i class="fa-solid fa-truck mr-1 text-primary"></i>5. Salida muelle: <strong>' + escapeHtml(o.dispatched_at || 'Pendiente') + '</strong></li>' +
			'<li class="mb-1"><i class="fa-solid fa-signature mr-1 text-success"></i>6. Entrega POD: <strong>' + escapeHtml(o.delivery_time || o.pickup_verified_at || 'Pendiente') + '</strong></li>' +
			'<li><i class="fa-solid fa-scale-balanced mr-1 text-dark"></i>7. Conciliación final: <strong>' + escapeHtml(o.conciliated_at || 'Pendiente de cierre') + '</strong></li>' +
			'</ul>'
		);
	}

	window.SelectOrder = selectOrder;

	$(document).on('click', '.dp-order-card', function (e) {
		e.preventDefault();
		selectOrder($(this).data('id'));
	});

	$('#toggle7Stages').on('change', function () {
		if ($(this).is(':checked')) {
			$('.dp-col-7stage').removeClass('d-none');
		} else {
			$('.dp-col-7stage').addClass('d-none');
		}
	});

	$('.dp-pill-btn').on('click', function () {
		$('.dp-pill-btn').removeClass('is-active');
		$(this).addClass('is-active');
		applyFilters();
	});

	$('#dpOrderSearch').on('input', applyFilters);

	function applyFilters() {
		var activeFilter = $('.dp-pill-btn.is-active').data('filter') || 'all';
		var query = $('#dpOrderSearch').val().toLowerCase().trim();

		$('.dp-order-card').each(function () {
			var card = $(this);
			var type = card.data('type');
			var hasDiff = String(card.data('hasdiff')) === '1';
			var text = card.text().toLowerCase();

			var matchFilter = true;
			if (activeFilter === 'pickup' && type !== 'pickup') matchFilter = false;
			if (activeFilter === 'delivery' && type !== 'delivery') matchFilter = false;
			if (activeFilter === 'variances' && !hasDiff) matchFilter = false;

			var matchQuery = !query || text.indexOf(query) !== -1;
			card.toggleClass('d-none', !(matchFilter && matchQuery));
		});
	}

	$('.dp-tab-item').on('click', function () {
		$('.dp-tab-item').removeClass('is-active');
		$(this).addClass('is-active');
		var target = $(this).data('tab');
		$('.dp-tab-pane').addClass('d-none');
		$('#pane-' + target).removeClass('d-none');
	});

	// 2. Validar Entrega Cliente Recoge (PIN + POD)
	$('#btnSubmitVerifyPickup').on('click', function () {
		var pin = $('#inputPickupPinCode').val().trim();
		var rec = $('#inputPickupReceiverName').val().trim();
		if (!pin || pin.length < 4) {
			toastr.error('Por favor ingrese el PIN de retiro de 6 dígitos o token QR.');
			return;
		}

		toastr.info('Verificando código de retiro en servidor...');
		$.ajax({
			url: U('/api/retail/dispatch/verify-pickup'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				pin_code: pin,
				signature_name: rec || null
			}),
			success: function (res) {
				toastr.success(res.message || 'Retiro verificado exitosamente.');
				$('#modalVerifyPickup').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al validar el PIN de retiro.'));
			}
		});
	});

	// 3. Registrar Entrega de Ruta a Domicilio (POD obligatoria + parcial/rechazo)
	$('#btnSubmitRecordDelivery').on('click', function () {
		var delivStatus = $('#selDeliveryStatus').val();
		var carrier = $('#inputCarrierName').val().trim();
		var sig = $('#inputDeliverySignature').val().trim();
		var evi = $('#inputDeliveryEvidenceUrl').val().trim();
		var inc = $('#txtRouteIncidents').val().trim();
		var tempOk = $('#chkColdChainOk').is(':checked') ? 1 : 0;

		if ((delivStatus === 'delivered' || delivStatus === 'partial') && (sig.length < 3 || !evi)) {
			toastr.error('Debe ingresar receptor/firma (mín. 3 letras) y folio/evidencia POD.');
			return;
		}
		if (delivStatus === 'rejected' && !inc) {
			toastr.error('Para entregas rechazadas documente el motivo en Incidencias.');
			return;
		}

		toastr.info('Guardando conciliación POD de entrega...');
		$.ajax({
			url: U('/api/retail/dispatch/record-delivery'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				carrier_name: carrier,
				delivery_signature_name: sig,
				delivery_evidence_url: evi,
				route_incidents: inc,
				cold_chain_temp_ok: tempOk,
				delivery_status: delivStatus
			}),
			success: function (res) {
				toastr.success(res.message || 'Entrega POD registrada.');
				$('#modalRecordDelivery').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al registrar la entrega.'));
			}
		});
	});

	// 4. Escaneo Rápido de UPC / PDA
	function performFastScan() {
		var barcode = $('#inputFastScanBarcode').val().trim();
		var qty = parseFloat($('#inputFastScanQty').val()) || 1.0;
		if (!barcode) return;

		var banner = $('#fastScanResultBanner');
		var icon = $('#fastScanResultIcon');
		var msg = $('#fastScanResultMessage');
		var details = $('#fastScanResultDetails');

		banner.removeClass('d-none alert-success alert-warning alert-danger text-success text-warning text-danger');

		$.ajax({
			url: U('/api/retail/dispatch/scan-upc'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				barcode: barcode,
				quantity: qty
			}),
			success: function (res) {
				banner.removeClass('d-none');
				if (res.status === 'match') {
					banner.addClass('alert-success text-success');
					icon.html('<i class="fa-solid fa-circle-check"></i>');
					msg.text(res.message);
					details.text('Validado contra cantidad reservada (' + res.scanned_qty + '/' + res.expected_qty + ').');
					toastr.success(res.message);
				} else if (res.blocked || res.status === 'overage' || res.status === 'mismatch') {
					banner.addClass('alert-danger text-danger');
					icon.html('<i class="fa-solid fa-ban"></i>');
					msg.text(res.message);
					details.text(res.blocked ? 'Operación bloqueada: no se permite exceder la reserva.' : 'El código escaneado no corresponde al pedido.');
					toastr.error(res.message);
				} else {
					banner.addClass('alert-warning text-warning');
					icon.html('<i class="fa-solid fa-triangle-exclamation"></i>');
					msg.text(res.message);
					details.text('Progreso actual: ' + res.scanned_qty + '/' + res.expected_qty + ' u.');
					toastr.warning(res.message);
				}
				$('#inputFastScanBarcode').val('').focus();
			},
			error: function (xhr) {
				banner.removeClass('d-none').addClass('alert-danger text-danger');
				icon.html('<i class="fa-solid fa-circle-xmark"></i>');
				var err = extractErr(xhr, 'Error en lectura de escáner');
				msg.text(err);
				details.text('');
				toastr.error(err);
			}
		});
	}

	$('#btnTriggerFastScan').on('click', performFastScan);
	$('#inputFastScanBarcode').on('keypress', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			performFastScan();
		}
	});

	// 5. Resolver Variación Logística
	$('#btnSubmitResolveVariance').on('click', function () {
		var vid = parseInt($('#resolveVarianceId').val(), 10) || 0;
		var status = $('#selResolutionStatus').val();
		var notes = $('#txtResolutionNotes').val().trim();

		if (!vid) {
			toastr.warning('No hay una variación pendiente seleccionada para este pedido.');
			return;
		}
		if (!notes) {
			toastr.error('Por favor ingrese la justificación logística.');
			return;
		}

		toastr.info('Registrando dictamen logístico...');
		$.ajax({
			url: U('/api/retail/dispatch/resolve-variance'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				variance_id: vid,
				resolution_status: status,
				notes: notes
			}),
			success: function () {
				toastr.success('Variación resuelta y autorizada.');
				$('#modalResolveVariance').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al resolver la variación.'));
			}
		});
	});

	if (currentOrderId) {
		selectOrder(currentOrderId);
	}

})(jQuery);
