/**
 * DAILY - Despachos y Validaciones (viewjs)
 * Conciliación en 7 etapas, cliente recoge vs. domicilio y escaneo rápido UPC
 */
(function ($) {
	'use strict';

	var currentOrderId = window.CurrentOrderId || 0;
	var ordersMap = window.DispatchOrdersMap || {};

	// Helper to format currency
	function formatMoney(val) {
		return '$' + parseFloat(val || 0).toFixed(2);
	}

	// 1. Selector reactivo de pedido (0ms de latencia)
	function selectOrder(orderId) {
		currentOrderId = orderId;
		window.CurrentOrderId = orderId;

		// Highlight card
		$('.dp-order-card').removeClass('is-active');
		$('.dp-order-card[data-id="' + orderId + '"]').addClass('is-active');

		var data = ordersMap[orderId] || ordersMap[String(orderId)];
		if (!data) return;

		var o = data.order;
		var isPickup = (o.delivery_type === 'pickup');
		var summary = data.summary;
		var lines = data.lines || [];
		var variances = data.variances || [];

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
		if (o.status === 'dispatched') {
			stBadge.addClass('st-dispatched').text('Despachado');
		} else if (o.status === 'picked') {
			stBadge.addClass('st-prepared').text('Preparado');
		} else {
			stBadge.addClass('st-packed').text('Empacado');
		}

		$('#dpDetailMeta').html('Cliente: <strong class="text-dark">' + (o.customer_name || 'Cliente General') + '</strong> · Sucursal: <strong class="text-dark">' + (o.branch_name || 'Matriz') + '</strong>');

		// Actions buttons
		if (isPickup) {
			$('#btnOpenVerifyPickupModal').removeClass('d-none');
			$('#btnOpenDeliveryModal').addClass('d-none');
		} else {
			$('#btnOpenVerifyPickupModal').addClass('d-none');
			$('#btnOpenDeliveryModal').removeClass('d-none');
		}

		// Stepper track
		var stepperHtml = '';
		if (isPickup) {
			var isDisp = (o.status === 'dispatched');
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">1. Pedido<br>Recibido</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">2. Preparación<br>Picking</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">3. Validación<br>UPC</div></div>';
			stepperHtml += '<div class="dp-stepper-step ' + (isDisp ? 'is-done' : 'is-current') + '"><div class="dp-step-circle">' + (isDisp ? '<i class="fa-solid fa-check"></i>' : '4') + '</div><div class="dp-step-label">4. Listo para<br>retiro</div></div>';
			stepperHtml += '<div class="dp-stepper-step ' + (isDisp ? 'is-done' : '') + '"><div class="dp-step-circle">' + (isDisp ? '<i class="fa-solid fa-check"></i>' : '5') + '</div><div class="dp-step-label">5. Verificar<br>PIN o QR</div></div>';
			stepperHtml += '<div class="dp-stepper-step ' + (isDisp ? 'is-done' : '') + '"><div class="dp-step-circle">' + (isDisp ? '<i class="fa-solid fa-check"></i>' : '6') + '</div><div class="dp-step-label">6. Entregado</div></div>';
		} else {
			var isDisp = (o.status === 'dispatched');
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">1. Pedido<br>Recibido</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">2. Preparación<br>Picking</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">3. Control<br>Calidad</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">4. Empaque<br>Bultos</div></div>';
			stepperHtml += '<div class="dp-stepper-step is-done"><div class="dp-step-circle"><i class="fa-solid fa-check"></i></div><div class="dp-step-label">5. Despachado<br>Ruta</div></div>';
			stepperHtml += '<div class="dp-stepper-step ' + (isDisp ? 'is-done' : 'is-current') + '"><div class="dp-step-circle">' + (isDisp ? '<i class="fa-solid fa-check"></i>' : '6') + '</div><div class="dp-step-label">6. Entregado</div></div>';
			stepperHtml += '<div class="dp-stepper-step ' + (isDisp ? 'is-done' : '') + '"><div class="dp-step-circle">' + (isDisp ? '<i class="fa-solid fa-check"></i>' : '7') + '</div><div class="dp-step-label">7. Conciliado</div></div>';
		}
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
			rowsHtml += '<img src="' + (l.product_image || '/img/control_center/prod_crema_leche.jpg') + '" alt="' + l.product_name + '" class="dp-prod-img">';
			rowsHtml += '<div><div class="dp-prod-name">' + l.product_name + '</div>';
			rowsHtml += '<div class="dp-prod-sku">SKU: ' + l.sku + ' · UPC: ' + l.upc + '</div>';
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

		// Totals row
		rowsHtml += '<tr class="dp-totals-row">';
		rowsHtml += '<td><div class="dp-totals-cell-left">TOTALES (Unidades)</div><div class="dp-totals-cell-sub">Cantidades reportadas. Validar unidades.</div></td>';
		rowsHtml += '<td class="dp-val-req">' + summary.total_requested + '</td>';
		rowsHtml += '<td class="dp-val-prep">' + summary.total_prepared + '</td>';
		rowsHtml += '<td class="dp-val-scan">' + summary.total_scanned + '</td>';
		rowsHtml += '<td class="dp-val-disp">' + summary.total_dispatched + '</td>';
		rowsHtml += '<td class="dp-val-recv">' + summary.total_received + '</td>';
		rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + '">' + summary.total_returned + '</td>';
		rowsHtml += '<td class="dp-col-7stage ' + (show7 ? '' : 'd-none') + '">' + summary.total_final_diff + '</td>';
		rowsHtml += '</tr>';

		$('#dpReconcileTable tbody').html(rowsHtml);

		// Amber Alert Box
		if (summary.has_unreconciled_shortage && summary.unreconciled_items && summary.unreconciled_items.length > 0) {
			var alertHtml = '<div class="dp-alert-unreconciled-left"><i class="fa-solid fa-triangle-exclamation dp-alert-unreconciled-icon"></i><div>';
			alertHtml += '<div class="dp-alert-unreconciled-title">Cantidades por conciliar</div>';
			alertHtml += '<div class="dp-alert-unreconciled-text">';
			summary.unreconciled_items.forEach(function (ui) {
				alertHtml += '<strong>' + ui.product_name + ':</strong> ' + ui.requested + ' solicitadas / ' + ui.prepared + ' preparadas.<br>';
			});
			alertHtml += '<span class="text-muted">Verifica sustituciones, faltantes o cambios autorizados antes de cerrar.</span></div></div></div>';
			alertHtml += '<button type="button" class="dp-btn-amber" data-toggle="modal" data-target="#modalResolveVariance"><i class="fa-solid fa-gavel"></i><span>Revisar diferencias</span></button>';

			if ($('#dpAlertUnreconciled').length === 0) {
				$('.dp-reconcile-wrap').after('<div class="dp-alert-unreconciled" id="dpAlertUnreconciled">' + alertHtml + '</div>');
			} else {
				$('#dpAlertUnreconciled').html(alertHtml).removeClass('d-none');
			}
		} else {
			$('#dpAlertUnreconciled').addClass('d-none');
		}

		// Evidence label
		$('#lblEvidenceReceiver').text('Representante de ' + (o.customer_name || 'Cliente'));
	}

	// Click en tarjeta de pedido
	$(document).on('click', '.dp-order-card', function (e) {
		e.preventDefault();
		var oid = $(this).data('id');
		selectOrder(oid);
	});

	// Toggle 7 etapas
	$('#toggle7Stages').on('change', function () {
		var checked = $(this).is(':checked');
		if (checked) {
			$('.dp-col-7stage').removeClass('d-none');
		} else {
			$('.dp-col-7stage').addClass('d-none');
		}
	});

	// Filtros por píldoras
	$('.dp-pill-btn').on('click', function () {
		$('.dp-pill-btn').removeClass('is-active');
		$(this).addClass('is-active');
		var filter = $(this).data('filter');
		applyFilters();
	});

	// Búsqueda en vivo
	$('#dpOrderSearch').on('input', function () {
		applyFilters();
	});

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

			var matchQuery = true;
			if (query && text.indexOf(query) === -1) matchQuery = false;

			if (matchFilter && matchQuery) {
				card.removeClass('d-none');
			} else {
				card.addClass('d-none');
			}
		});
	}

	// Pestañas inferiores (Detalle, Variaciones, Evidencia, Historial)
	$('.dp-tab-item').on('click', function () {
		$('.dp-tab-item').removeClass('is-active');
		$(this).addClass('is-active');
		var target = $(this).data('tab');

		$('.dp-tab-pane').addClass('d-none');
		$('#pane-' + target).removeClass('d-none');
	});

	// 2. Validar Entrega Cliente Recoge (PIN de 6 dígitos protegido)
	$('#btnSubmitVerifyPickup').on('click', function () {
		var pin = $('#inputPickupPinCode').val().trim();
		if (!pin || pin.length < 4) {
			toastr.error('Por favor ingrese el PIN de retiro de 6 dígitos.');
			return;
		}

		toastr.info('Verificando código de retiro en servidor...');
		$.ajax({
			url: U('/api/retail/dispatch/verify-pickup'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				pin_code: pin
			}),
			success: function (res) {
				toastr.success(res.message || 'Retiro verificado exitosamente.');
				$('#modalVerifyPickup').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				var err = xhr.responseJSON ? xhr.responseJSON.error_message : xhr.responseText;
				toastr.error(err || 'Error al validar el PIN de retiro.');
			}
		});
	});

	// 3. Registrar Entrega de Ruta a Domicilio
	$('#btnSubmitRecordDelivery').on('click', function () {
		var carrier = $('#inputCarrierName').val().trim();
		var sig = $('#inputDeliverySignature').val().trim();
		var inc = $('#inputRouteIncidents').val().trim();
		var tempOk = $('#chkDeliveryTempOk').is(':checked') ? 1 : 0;

		toastr.info('Guardando conciliación de entrega de ruta...');
		$.ajax({
			url: U('/api/retail/dispatch/record-delivery'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				carrier_name: carrier,
				delivery_signature_name: sig,
				route_incidents: inc,
				cold_chain_temp_ok: tempOk,
				delivery_status: 'delivered'
			}),
			success: function (res) {
				toastr.success(res.message || 'Despacho a domicilio registrado.');
				$('#modalRecordDelivery').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				var err = xhr.responseJSON ? xhr.responseJSON.error_message : xhr.responseText;
				toastr.error(err || 'Error al registrar la entrega.');
			}
		});
	});

	// 4. Escaneo Rápido de UPC
	function performFastScan() {
		var barcode = $('#inputFastScanBarcode').val().trim();
		if (!barcode) return;

		var banner = $('#fastScanResultBanner');
		var icon = $('#fastScanResultIcon');
		var msg = $('#fastScanResultMessage');
		var details = $('#fastScanResultDetails');

		banner.removeClass('d-none alert-success alert-warning alert-danger');

		$.ajax({
			url: U('/api/retail/dispatch/scan-upc'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: currentOrderId,
				barcode: barcode,
				quantity: 1.0
			}),
			success: function (res) {
				banner.removeClass('d-none');
				if (res.status === 'match') {
					banner.addClass('alert-success text-success');
					icon.html('<i class="fa-solid fa-circle-check"></i>');
					msg.text(res.message);
					details.text('Coincidencia verificada contra línea de pedido.');
					toastr.success('Coincidencia validada.');
				} else if (res.status === 'warning' || res.status === 'overage') {
					banner.addClass('alert-warning text-warning');
					icon.html('<i class="fa-solid fa-triangle-exclamation"></i>');
					msg.text(res.message);
					details.text('Diferencia detectada: ' + res.difference + ' un. (Impacto: $' + res.financial_impact + ')');
					toastr.warning(res.message);
				} else {
					banner.addClass('alert-danger text-danger');
					icon.html('<i class="fa-solid fa-circle-xmark"></i>');
					msg.text(res.message);
					details.text('El producto no pertenece al pedido.');
					toastr.error(res.message);
				}
				$('#inputFastScanBarcode').val('').focus();
			},
			error: function (xhr) {
				banner.removeClass('d-none').addClass('alert-danger text-danger');
				icon.html('<i class="fa-solid fa-circle-xmark"></i>');
				var err = xhr.responseJSON ? xhr.responseJSON.error_message : 'Error en lectura de escáner';
				msg.text(err);
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

	// 5. Resolver Variación Tipificada
	$('#btnSubmitResolveVariance').on('click', function () {
		var vid = $('#resolveVarianceId').val();
		var status = $('#selResolutionStatus').val();
		var notes = $('#txtResolutionNotes').val().trim();

		if (!notes) {
			toastr.error('Por favor ingrese la justificación de la resolución.');
			return;
		}

		toastr.info('Registrando autorización de variación...');
		$.ajax({
			url: U('/api/retail/dispatch/resolve-variance'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				variance_id: vid,
				resolution_status: status,
				resolution_notes: notes
			}),
			success: function (res) {
				toastr.success('Variación resuelta y autorizada.');
				$('#modalResolveVariance').modal('hide');
				setTimeout(function () { window.location.reload(); }, 600);
			},
			error: function (xhr) {
				var err = xhr.responseJSON ? xhr.responseJSON.error_message : xhr.responseText;
				toastr.error(err || 'Error al resolver la variación.');
			}
		});
	});

})(jQuery);
