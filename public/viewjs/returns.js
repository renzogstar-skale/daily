/**
 * DAILY - Devoluciones Logísticas (Logística Inversa - 6 Etapas)
 * Ciclo: Solicitud -> Recepción (UPC) -> Inspección -> Clasificación -> Disposición -> Conciliación
 * Separación estricta de devoluciones físicas vs devoluciones comerciales POS.
 */
(function ($) {
	'use strict';

	var returnsData = window.RETURNS_DATA || [];
	var currentSelectedId = null;
	var activeEvidenceDataUrl = '';
	var activeInspectPhotoUrl = '';

	// Inicializar selección en la primera fila logística visible
	for (var k = 0; k < returnsData.length; k++) {
		if (parseInt(returnsData[k].is_pos_return || 0, 10) === 0) {
			currentSelectedId = returnsData[k].id;
			break;
		}
	}
	if (!currentSelectedId && returnsData.length > 0) {
		currentSelectedId = returnsData[0].id;
	}

	function escapeHtml(str) {
		return String(str === undefined || str === null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatMoney(amount) {
		var n = parseFloat(amount) || 0;
		return '$' + n.toFixed(2);
	}

	function formatQty(q) {
		var n = parseFloat(q) || 0;
		return (parseInt(n, 10) === n) ? String(parseInt(n, 10)) : n.toFixed(1);
	}

	function newIdempotencyKey() {
		return 'rma-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
	}

	function findCase(id) {
		for (var i = 0; i < returnsData.length; i++) {
			if (String(returnsData[i].id) === String(id)) {
				return returnsData[i];
			}
		}
		return null;
	}

	// Comprimir / leer imagen a DataURL para persistir evidencia real
	function readImageFile(fileInput, callback) {
		var files = fileInput && fileInput.files ? fileInput.files : null;
		if (!files || !files.length) {
			callback('');
			return;
		}
		var reader = new FileReader();
		reader.onload = function (ev) {
			callback(ev.target.result || '');
		};
		reader.readAsDataURL(files[0]);
	}

	// --------------------------------------------------------------------------
	// 1. SELECCIÓN DE EXPEDIENTE Y PANEL DE DETALLE REACTIVO (6 ETAPAS)
	// --------------------------------------------------------------------------
	function selectCase(returnId) {
		var item = findCase(returnId);
		if (!item) return;

		currentSelectedId = item.id;

		$('.ret-table-row').removeClass('is-active');
		$('.ret-table-row[data-id="' + item.id + '"]').addClass('is-active');

		var rmaNum = item.rma_number || item.return_code;
		var stMeta = item.stage_meta || { num: 1, name: item.status, action: 'Gestionar' };
		var dstMeta = item.dest_meta || { title: item.classification || 'Cuarentena', detail_notice: 'Destino operativo.' };

		$('#detailRma').text(rmaNum);
		$('#detailFolio').text(item.return_code);
		$('#detailStatusBadge').text(stMeta.name || item.status);

		$('#detailProductImg').attr('src', U(item.thumbnail || '/img/control_center/daily_scan_crate.jpg')).attr('alt', item.product_name || '');
		$('#detailProductName').text(item.product_name || '');
		$('#detailProductQty').text(
			'Solicitado: ' + formatQty(item.quantity) + ' u. · Recibido: ' + formatQty(item.received_quantity) + ' u. · Conciliado: ' + formatQty(item.conciliated_quantity) + ' u.'
		);
		$('#detailProductCost').text('Costo inventario: ' + formatMoney(item.total_cost));

		$('#detailOrigin').text((item.return_type || '').replace(/_/g, ' '));
		$('#detailUpcScanned').text(item.upc_scanned || item.sku || 'Sin escanear');
		$('#detailOrderCode').text((item.order_code && item.order_code !== 'Sin pedido directo') ? item.order_code : 'Directo en muelle');
		$('#detailBranchName').text(item.branch_name || 'Sin sucursal');
		$('#detailCreatedAt').text((item.created_at || '').substring(0, 16));

		$('#detailDestName').text(dstMeta.title || item.classification);
		$('#detailNoticeText').text(dstMeta.detail_notice || '');

		if (parseInt(item.stock_reentered || 0, 10) === 1) {
			$('#detailStockReentryBadge').html('<span class="text-success font-weight-bold"><i class="fa-solid fa-check-double mr-1"></i>Reingresado a inventario (' + escapeHtml(item.stock_reentry_tx_id || '') + ')</span>');
		} else {
			$('#detailStockReentryBadge').text('Sin reingreso automático al stock');
		}

		// Stepper 6 etapas
		var stepNum = parseInt(stMeta.num || 1, 10);
		$('#detailStepperLabel').html('Etapa ' + stepNum + ' de 6 &middot; ' + escapeHtml(stMeta.name || item.status));

		var dotsHtml = '';
		for (var s = 1; s <= 6; s++) {
			var dotClass = '';
			var content = s;
			if (s < stepNum || (stepNum === 6 && s === 6)) {
				dotClass = 'done';
				content = '<i class="fa-solid fa-check"></i>';
			} else if (s === stepNum) {
				dotClass = 'active';
				content = s;
			}
			dotsHtml += '<div class="ret-step-circle ' + dotClass + '" data-step="' + s + '">' + content + '</div>';
		}
		$('#detailStepperDots').html(dotsHtml);

		$('#detailPrimaryActionText').text(stMeta.action || 'Gestionar etapa');
		$('#btnDetailPrimaryAction')
			.data('id', item.id)
			.data('code', item.return_code)
			.data('status', item.status)
			.data('stage', stepNum);

		$('#btnDetailOpenReceive').data('id', item.id);
		$('#btnDetailEvidence, .btn-register-evidence-detail').data('id', item.id).data('code', rmaNum);

		// Pestaña Inspección
		$('#detailInspectionNotes').text(item.inspection_notes || 'Sin inspección física registrada.');
		$('#detailConditionGrade').text((item.condition_grade || 'sin_inspeccionar').replace(/_/g, ' '));
		$('#detailInspectorName').text(item.inspector_name || 'Pendiente');
		$('#detailAuthorizerName').text(item.authorized_by_name || 'Pendiente');
		if (parseInt(item.is_perishable, 10) === 1) {
			$('#detailPerishableBadge').html('<span class="badge badge-danger">Perecedero (Bloqueo sanitario)</span>');
		} else {
			$('#detailPerishableBadge').html('<span class="badge badge-light border">No perecedero</span>');
		}
		$('#detailQtyResalable').text(formatQty(item.resalable_quantity || 0));
		$('#detailQtyDamaged').text(formatQty(item.damaged_quantity || 0));
		$('#detailQtyRtv').text(formatQty(item.rtv_quantity || 0));
		$('#detailQtyRepairable').text(formatQty(item.repairable_quantity || 0));

		// Pestaña Evidencia fotográfica real
		var photos = item.evidence_photos || [];
		if (photos.length > 0) {
			var phHtml = '';
			photos.forEach(function (ph) {
				var src = (ph.photo_data || '').indexOf('data:') === 0 ? ph.photo_data : U(ph.photo_data || '/img/control_center/daily_scan_crate.jpg');
				phHtml += '<div class="ret-evidence-thumb">' +
					'<img src="' + escapeHtml(src) + '" alt="Evidencia">' +
					'<div class="ret-evidence-caption">' + escapeHtml(ph.caption || 'Evidencia') + ' &middot; ' + escapeHtml((ph.created_at || '').substring(0, 10)) + '</div>' +
					'</div>';
			});
			$('#detailEvidenceGallery').html(phHtml);
		} else {
			$('#detailEvidenceGallery').html('<div class="p-3 text-center border rounded bg-light small text-muted" style="grid-column: span 2;">Sin fotografías adjuntas todavía.</div>');
		}

		// Pestaña Auditoría real
		var logs = item.audit_log || [];
		if (logs.length > 0) {
			var audHtml = '';
			logs.forEach(function (aud) {
				var act = (aud.action || '').replace(/_/g, ' ');
				act = act.charAt(0).toUpperCase() + act.slice(1);
				audHtml += '<li class="mb-2">' +
					'<div class="font-weight-bold text-dark">' + escapeHtml(act) + ' (' + escapeHtml(aud.stage || '') + ')</div>' +
					'<div class="text-muted">' + escapeHtml(aud.details || '') + '</div>' +
					'<div class="text-muted" style="font-size: 0.7rem;">' + escapeHtml((aud.created_at || '').substring(0, 16)) + ' &middot; ' + escapeHtml(aud.username || 'Sistema') + '</div>' +
					'</li>';
			});
			$('#detailHistoryList').html(audHtml);
		} else {
			$('#detailHistoryList').html('<li class="text-muted">Sin eventos registrados.</li>');
		}

		// Tarjeta Conciliación Económica/Unidades
		$('#econCaseCost').text(formatMoney(item.total_cost));
		$('#econQtyCompare').text(formatQty(item.quantity) + ' / ' + formatQty(item.received_quantity) + ' u.');
		$('#econConciliatedQty').text(formatQty(item.conciliated_quantity) + ' u.');
		var recovVal = parseFloat(item.recoverable_value) || 0;
		$('#econRecoverable').text(recovVal > 0 ? formatMoney(recovVal) : '—');
		var credVal = parseFloat(item.supplier_credit_amount) || 0;
		$('#econSupplierCredit').text(credVal > 0 ? formatMoney(credVal) : '—');
		var lossVal = parseFloat(item.definitive_loss_value) || 0;
		$('#econLoss').text(lossVal > 0 ? formatMoney(lossVal) : '—');

		if ($(window).width() < 1080) {
			var panelOffset = $('#returnsDetailPanel').offset();
			if (panelOffset) {
				$('html, body').animate({ scrollTop: panelOffset.top - 70 }, 300);
			}
		}
	}

	$(document).on('click', '.ret-table-row', function () {
		selectCase($(this).data('id'));
	});

	$(document).on('click', '.btn-select-row', function (e) {
		e.preventDefault();
		e.stopPropagation();
		selectCase($(this).data('id'));
	});

	$('#btnDetailClose').on('click', function () {
		if ($(window).width() < 1080) {
			$('html, body').animate({ scrollTop: $('.ret-table-card').offset().top - 20 }, 250);
		}
	});

	// --------------------------------------------------------------------------
	// 2. PESTAÑAS DEL DETALLE Y FILTROS DE CANAL / ETAPA / BÚSQUEDA
	// --------------------------------------------------------------------------
	$('.ret-detail-tab-btn').on('click', function () {
		var targetPane = $(this).data('pane');
		$('.ret-detail-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.ret-detail-pane-content').addClass('d-none');
		$('#' + targetPane).removeClass('d-none');
	});

	function applyTableFilters() {
		var activePill = $('.ret-tab-pill.active').data('tab-filter') || 'logistics';
		var q = ($('#searchReturnsTable').val() || '').toLowerCase().trim();
		var checkedStages = [];
		$('.filter-check:checked').each(function () {
			if ($(this).val() !== 'all') checkedStages.push($(this).val());
		});

		var firstVisibleId = null;
		$('.ret-table-row').each(function () {
			var $row = $(this);
			var channel = $row.data('channel');
			var group = $row.data('group');
			var st = $row.data('status');
			var search = ($row.data('search') || '').toString();

			var matchPill = true;
			if (activePill === 'logistics') matchPill = (channel === 'logistics');
			else if (activePill === 'pos_handoff') matchPill = (channel === 'pos_handoff');
			else if (activePill === 'en_proceso') matchPill = (group === 'en_proceso');
			else if (activePill === 'conciliados') matchPill = (group === 'conciliados');

			var matchStage = (checkedStages.length === 0 || checkedStages.indexOf(st) !== -1);
			var matchSearch = (!q || search.indexOf(q) !== -1);

			if (matchPill && matchStage && matchSearch) {
				$row.show();
				if (firstVisibleId === null) firstVisibleId = $row.data('id');
			} else {
				$row.hide();
			}
		});

		if (firstVisibleId !== null && $('.ret-table-row[data-id="' + currentSelectedId + '"]').is(':hidden')) {
			selectCase(firstVisibleId);
		}
	}

	$('.ret-tab-pill').on('click', function () {
		$('.ret-tab-pill').removeClass('active');
		$(this).addClass('active');
		applyTableFilters();
	});

	$('.ret-lc-step').on('click', function () {
		var stage = $(this).data('stage-filter');
		$('.ret-lc-step').removeClass('is-active');
		$(this).addClass('is-active');
		$('.filter-check').prop('checked', false);
		$('#chkFilter_' + stage).prop('checked', true);
		$('.ret-tab-pill').removeClass('active');
		$('#tabFilterProcess').addClass('active');
		if (stage === 'conciliacion') {
			$('.ret-tab-pill').removeClass('active');
			$('#tabFilterClosed').addClass('active');
		}
		applyTableFilters();
	});

	$('#searchReturnsTable').on('input', applyTableFilters);

	$('.filter-check').on('change', function () {
		var val = $(this).val();
		if (val === 'all' && $(this).is(':checked')) {
			$('.filter-check').not(this).prop('checked', false);
		} else if ($(this).is(':checked')) {
			$('#chkFilterAll').prop('checked', false);
		} else if ($('.filter-check:checked').length === 0) {
			$('#chkFilterAll').prop('checked', true);
		}
		applyTableFilters();
	});

	// --------------------------------------------------------------------------
	// 3. ESCANEO RÁPIDO UPC / RMA
	// --------------------------------------------------------------------------
	function runQuickUpcLookup() {
		var code = $('#inputQuickUpcScan').val().trim();
		if (!code) {
			toastr.warning('Escanee un código UPC, SKU o número RMA.');
			return;
		}
		$.ajax({
			url: U('/api/retail/returns/scan-upc') + '?code=' + encodeURIComponent(code),
			type: 'GET',
			success: function (res) {
				if (res.match_type === 'rma' && res.return) {
					selectCase(res.return.id);
					openReceiveModal(res.return.id, code);
					return;
				}
				if (res.open_returns && res.open_returns.length > 0) {
					var targetId = res.open_returns[0].id;
					selectCase(targetId);
					openReceiveModal(targetId, code);
					toastr.success('UPC identificado para ' + (res.product.name || '') + '.');
				} else if (res.product) {
					toastr.info('Producto ' + res.product.name + ' reconocido sin RMA pendiente. Abriendo solicitud...');
					$('#inputReturnProductId').val(res.product.id).trigger('change');
					$('#modalNewReturn').modal('show');
				}
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Código no reconocido.');
			}
		});
	}

	$('#btnQuickUpcScan').on('click', runQuickUpcLookup);
	$('#inputQuickUpcScan').on('keypress', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			runQuickUpcLookup();
		}
	});

	// --------------------------------------------------------------------------
	// 4. MODAL 1: NUEVA SOLICITUD RMA (CON IDEMPOTENCY KEY)
	// --------------------------------------------------------------------------
	$('#modalNewReturn').on('show.bs.modal', function () {
		$('#inputReturnIdempotencyKey').val(newIdempotencyKey());
	});

	$('#inputReturnProductId').on('change', function () {
		var cost = $(this).find(':selected').data('cost');
		if (cost) {
			$('#inputReturnUnitCost').val(parseFloat(cost).toFixed(2));
		}
	});

	$('#btnSubmitNewReturn').on('click', function () {
		var pid = $('#inputReturnProductId').val();
		var qty = parseFloat($('#inputReturnQuantity').val()) || 0;
		var cost = parseFloat($('#inputReturnUnitCost').val()) || 1.0;
		var rType = $('#inputReturnType').val();
		var reason = $('#inputReturnReason').val().trim();
		var orderId = $('#inputReturnOrderId').val();
		var supplierId = $('#inputReturnSupplierId').val();
		var branchId = $('#inputReturnBranchId').val();
		var refDoc = $('#inputReturnRefDoc').val().trim();
		var idem = $('#inputReturnIdempotencyKey').val() || newIdempotencyKey();

		if (!pid || qty <= 0) {
			toastr.error('Seleccione un producto y cantidad mayor a cero.');
			return;
		}
		if (!reason) {
			toastr.error('Ingrese el motivo de la solicitud RMA.');
			return;
		}

		$.ajax({
			url: U('/api/retail/returns'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				product_id: parseInt(pid, 10),
				quantity: qty,
				unit_cost: cost,
				return_type: rType,
				reason: reason,
				order_id: orderId ? parseInt(orderId, 10) : null,
				supplier_id: supplierId ? parseInt(supplierId, 10) : null,
				branch_id: branchId ? parseInt(branchId, 10) : 1,
				reference_doc: refDoc || null,
				idempotency_key: idem
			}),
			success: function (res) {
				toastr.success('Solicitud ' + (res.rma_number || res.return_code || '') + ' registrada.');
				$('#modalNewReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al generar RMA.');
			}
		});
	});

	// --------------------------------------------------------------------------
	// 5. ENRUTADOR CONTEXTUAL DEL CICLO DE 6 ETAPAS
	// --------------------------------------------------------------------------
	function openReceiveModal(returnId, prefilledUpc) {
		var item = findCase(returnId || currentSelectedId);
		if (!item) return;
		$('#receiveReturnId').val(item.id);
		$('#receiveRmaTitle').text(item.rma_number || item.return_code);
		$('#receiveProductLabel').text((item.product_name || '') + ' (SKU: ' + (item.sku || 'N/A') + ')');
		$('#receiveExpectedQty').text(formatQty(item.quantity));
		$('#inputReceiveQty').val(parseFloat(item.received_quantity) > 0 ? item.received_quantity : item.quantity);
		$('#inputReceiveUpc').val(prefilledUpc || item.primary_barcode || item.sku || '');
		$('#inputReceiveNotes').val('');
		$('#modalReceiveReturn').modal('show');
	}

	function openInspectModal(returnId) {
		var item = findCase(returnId || currentSelectedId);
		if (!item) return;
		$('#inspectReturnId').val(item.id);
		$('#inspectRmaTitle').text(item.rma_number || item.return_code);
		$('#inspectReceivedQty').text(formatQty(item.received_quantity));
		if (item.condition_grade && item.condition_grade !== 'sin_inspeccionar') {
			$('#inputInspectGrade').val(item.condition_grade);
		}
		$('#inputInspectNotes').val(item.inspection_notes || '');
		activeInspectPhotoUrl = '';
		$('#inputInspectPhoto').val('');
		$('#modalInspectReturn').modal('show');
	}

	function syncClassifyBlockRules() {
		var item = findCase($('#classifyReturnId').val());
		if (!item) return;
		var grade = item.condition_grade || 'sin_inspeccionar';
		var blockedByQuality = (['danado', 'vencido', 'defectuoso_fabrica'].indexOf(grade) !== -1) ||
			(parseInt(item.is_perishable || 0, 10) === 1 && parseInt(item.is_pos_return || 0, 10) === 1);

		if (blockedByQuality) {
			$('#optClassifyReventa').prop('disabled', true);
			$('#inputQtyResalable').val(0).prop('readonly', true);
			$('#alertClassifyDamageBlock').removeClass('d-none');
			if ($('#inputClassifyDest').val() === 'reincorporacion') {
				$('#inputClassifyDest').val(grade === 'defectuoso_fabrica' ? 'devolucion_proveedor' : 'merma_definitiva');
			}
		} else {
			$('#optClassifyReventa').prop('disabled', false);
			$('#inputQtyResalable').prop('readonly', false);
			$('#alertClassifyDamageBlock').addClass('d-none');
		}
	}

	function applyPresetSplitFromDest() {
		var dest = $('#inputClassifyDest').val();
		var tot = parseFloat($('#classifyReceivedTotal').val()) || 0;
		$('#inputQtyResalable').val(dest === 'reincorporacion' ? tot : 0);
		$('#inputQtyDamaged').val(['merma_definitiva', 'destruccion', 'donacion'].indexOf(dest) !== -1 ? tot : 0);
		$('#inputQtyRtv').val(dest === 'devolucion_proveedor' ? tot : 0);
		$('#inputQtyRepairable').val(['reparacion', 'recuperacion'].indexOf(dest) !== -1 ? tot : 0);
	}

	$('#inputClassifyDest').on('change', applyPresetSplitFromDest);

	function openClassifyModal(returnId) {
		var item = findCase(returnId || currentSelectedId);
		if (!item) return;
		var rec = parseFloat(item.received_quantity) || 0;
		$('#classifyReturnId').val(item.id);
		$('#classifyReceivedTotal').val(rec);
		$('#classifyRmaTitle').text(item.rma_number || item.return_code);
		$('#classifyGradeBadge').text((item.condition_grade || 'sin_inspeccionar').replace(/_/g, ' '));
		$('#classifyTotalLabel').text(formatQty(rec));

		var cls = item.classification && item.classification !== 'cuarentena' && item.classification !== 'pendiente_inspeccion'
			? item.classification
			: (item.condition_grade === 'danado' || item.condition_grade === 'vencido' ? 'merma_definitiva' : (item.condition_grade === 'defectuoso_fabrica' ? 'devolucion_proveedor' : (item.condition_grade === 'reparable' ? 'reparacion' : 'reincorporacion')));
		$('#inputClassifyDest').val(cls);
		if (item.supplier_id) $('#inputClassifySupplier').val(item.supplier_id);
		$('#inputClassifyRepairNotes').val(item.repair_notes || '');

		syncClassifyBlockRules();
		applyPresetSplitFromDest();
		$('#modalClassifyReturn').modal('show');
	}

	function openDispositionModal(returnId) {
		var item = findCase(returnId || currentSelectedId);
		if (!item) return;
		$('#dispositionReturnId').val(item.id);
		$('#dispositionRmaTitle').text(item.rma_number || item.return_code);
		$('#dispositionDestLabel').text((item.dest_meta && item.dest_meta.title) ? item.dest_meta.title : item.classification);
		$('#dispositionBreakdownText').text(
			'Reventa: ' + formatQty(item.resalable_quantity) + ' · Dañado: ' + formatQty(item.damaged_quantity) +
			' · RTV: ' + formatQty(item.rtv_quantity) + ' · Reparación: ' + formatQty(item.repairable_quantity)
		);
		if (parseFloat(item.resalable_quantity || 0) > 0) {
			$('#dispositionStockBox').removeClass('d-none');
			if (item.target_location_id) $('#inputDispositionLocation').val(item.target_location_id);
		} else {
			$('#dispositionStockBox').addClass('d-none');
		}
		$('#inputDispositionNotes').val(item.disposition_notes || '');
		$('#modalDispositionReturn').modal('show');
	}

	function openConciliateModal(returnId) {
		var item = findCase(returnId || currentSelectedId);
		if (!item) return;
		var dispSum = (parseFloat(item.resalable_quantity) || 0) + (parseFloat(item.damaged_quantity) || 0) + (parseFloat(item.rtv_quantity) || 0) + (parseFloat(item.repairable_quantity) || 0);
		$('#conciliateReturnId').val(item.id);
		$('#conciliateRmaTitle').text(item.rma_number || item.return_code);
		$('#concReqQty').text(formatQty(item.quantity) + ' u.');
		$('#concRecQty').text(formatQty(item.received_quantity) + ' u.');
		$('#concDispQty').text(formatQty(dispSum) + ' u.');
		$('#inputConciliateQty').val(item.received_quantity || dispSum);
		$('#inputConciliateNotes').val('');
		$('#modalConciliateReturn').modal('show');
	}

	$(document).on('click', '.btn-stage-action', function () {
		var id = $(this).data('id') || currentSelectedId;
		var status = $(this).data('status') || 'solicitud';
		if (status === 'solicitud') openReceiveModal(id);
		else if (status === 'recepcion') openInspectModal(id);
		else if (status === 'inspeccion') openClassifyModal(id);
		else if (status === 'clasificacion') openDispositionModal(id);
		else if (status === 'disposicion') openConciliateModal(id);
		else $('.ret-detail-tab-btn[data-pane="tabPaneHistory"]').click();
	});

	$('#btnDetailOpenReceive').on('click', function () {
		openReceiveModal($(this).data('id') || currentSelectedId);
	});

	// Submit Etapa 2: Recepción
	$('#btnSubmitReceiveReturn').on('click', function () {
		var id = $('#receiveReturnId').val();
		$.ajax({
			url: U('/api/retail/returns/' + id + '/receive'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				upc_scanned: $('#inputReceiveUpc').val().trim(),
				received_quantity: parseFloat($('#inputReceiveQty').val()) || 0,
				notes: $('#inputReceiveNotes').val().trim()
			}),
			success: function (res) {
				toastr.success(res.message || 'Recepción física confirmada.');
				$('#modalReceiveReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error en recepción física.');
			}
		});
	});

	// Submit Etapa 3: Inspección
	$('#inputInspectPhoto').on('change', function () {
		readImageFile(this, function (url) { activeInspectPhotoUrl = url; });
	});

	$('#btnSubmitInspectReturn').on('click', function () {
		var id = $('#inspectReturnId').val();
		$.ajax({
			url: U('/api/retail/returns/' + id + '/inspect'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				condition_grade: $('#inputInspectGrade').val(),
				inspection_notes: $('#inputInspectNotes').val().trim(),
				photo_data: activeInspectPhotoUrl
			}),
			success: function (res) {
				toastr.success(res.message || 'Inspección registrada.');
				$('#modalInspectReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al registrar inspección.');
			}
		});
	});

	// Submit Etapa 4: Clasificación
	$('#btnSubmitClassifyReturn').on('click', function () {
		var id = $('#classifyReturnId').val();
		$.ajax({
			url: U('/api/retail/returns/' + id + '/classify'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				classification: $('#inputClassifyDest').val(),
				resalable_quantity: parseFloat($('#inputQtyResalable').val()) || 0,
				damaged_quantity: parseFloat($('#inputQtyDamaged').val()) || 0,
				rtv_quantity: parseFloat($('#inputQtyRtv').val()) || 0,
				repairable_quantity: parseFloat($('#inputQtyRepairable').val()) || 0,
				supplier_id: $('#inputClassifySupplier').val() ? parseInt($('#inputClassifySupplier').val(), 10) : null,
				repair_notes: $('#inputClassifyRepairNotes').val().trim()
			}),
			success: function () {
				toastr.success('Clasificación por unidad guardada.');
				$('#modalClassifyReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al clasificar mercancía.');
			}
		});
	});

	// Submit Etapa 5: Disposición Autorizada
	$('#btnSubmitDispositionReturn').on('click', function () {
		var id = $('#dispositionReturnId').val();
		$.ajax({
			url: U('/api/retail/returns/' + id + '/disposition'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				authorize_stock_reentry: $('#chkAuthorizeStockReentry').is(':checked'),
				target_location_id: $('#inputDispositionLocation').val() ? parseInt($('#inputDispositionLocation').val(), 10) : null,
				notes: $('#inputDispositionNotes').val().trim()
			}),
			success: function (res) {
				toastr.success(res.message || 'Disposición autorizada y ejecutada.');
				$('#modalDispositionReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error en disposición.');
			}
		});
	});

	// Submit Etapa 6: Conciliación Final
	$('#btnSubmitConciliateReturn').on('click', function () {
		var id = $('#conciliateReturnId').val();
		$.ajax({
			url: U('/api/retail/returns/' + id + '/conciliate'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				conciliated_quantity: parseFloat($('#inputConciliateQty').val()) || 0,
				conciliation_notes: $('#inputConciliateNotes').val().trim()
			}),
			success: function (res) {
				toastr.success(res.message || 'RMA conciliado y cerrado.');
				$('#modalConciliateReturn').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al conciliar unidades.');
			}
		});
	});

	// --------------------------------------------------------------------------
	// 6. REGISTRO FOTOGRÁFICO REAL (/api/retail/returns/{id}/evidence)
	// --------------------------------------------------------------------------
	$(document).on('click', '.btn-register-evidence-detail', function () {
		var id = $(this).data('id') || currentSelectedId;
		var item = findCase(id);
		if (!item) return;
		$('#evidenceReturnId').val(item.id);
		$('#evidenceCaseFolio').val(item.rma_number || item.return_code);
		$('#evidenceFileInput').val('');
		$('#evidenceNotesInput').val('');
		$('#evidencePreviewBox').addClass('d-none');
		activeEvidenceDataUrl = '';
		$('#modalRegisterEvidence').modal('show');
	});

	$('#evidenceFileInput').on('change', function () {
		readImageFile(this, function (url) {
			activeEvidenceDataUrl = url;
			if (url) {
				$('#evidencePreviewImg').attr('src', url);
				$('#evidencePreviewBox').removeClass('d-none');
			}
		});
	});

	$('#btnSaveEvidence').on('click', function () {
		var id = $('#evidenceReturnId').val() || currentSelectedId;
		var notes = $('#evidenceNotesInput').val().trim();
		if (!activeEvidenceDataUrl && !notes) {
			toastr.error('Adjunte una fotografía o escriba una observación visual.');
			return;
		}
		$.ajax({
			url: U('/api/retail/returns/' + id + '/evidence'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				photo_data: activeEvidenceDataUrl,
				caption: notes || 'Evidencia fotográfica de inspección'
			}),
			success: function () {
				toastr.success('Evidencia fotográfica guardada en el expediente.');
				$('#modalRegisterEvidence').modal('hide');
				setTimeout(function () { window.location.reload(); }, 500);
			},
			error: function (xhr) {
				toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al guardar evidencia.');
			}
		});
	});

	// --------------------------------------------------------------------------
	// 7. EXPORTACIÓN CSV / EXCEL LOGÍSTICA (SIN COLUMNAS DE REEMBOLSOS POS)
	// --------------------------------------------------------------------------
	$('#btnExportCsv, #btnExportExcel').on('click', function (e) {
		e.preventDefault();
		if (!returnsData.length) {
			toastr.warning('No hay datos para exportar.');
			return;
		}

		var csv = 'RMA,Folio,Fecha,Canal,Producto,SKU,Tipo,Solicitada,Recibida,Conciliada,Reventa,Danado,RTV,Reparacion,Condicion,Destino,Etapa,Reingreso_TX,Costo_Inv\n';
		returnsData.forEach(function (r) {
			var line = [
				r.rma_number || r.return_code,
				r.return_code,
				r.created_at,
				parseInt(r.is_pos_return || 0, 10) === 1 ? 'POS_FISICO' : 'LOGISTICA_RMA',
				'"' + String(r.product_name || '').replace(/"/g, '""') + '"',
				r.sku || '',
				r.return_type,
				r.quantity,
				r.received_quantity || 0,
				r.conciliated_quantity || 0,
				r.resalable_quantity || 0,
				r.damaged_quantity || 0,
				r.rtv_quantity || 0,
				r.repairable_quantity || 0,
				r.condition_grade || '',
				r.classification || '',
				r.status,
				r.stock_reentry_tx_id || '',
				r.total_cost
			].join(',');
			csv += line + '\n';
		});

		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.setAttribute('download', 'DAILY_Devoluciones_Logisticas_' + new Date().toISOString().slice(0, 10) + '.csv');
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		toastr.success('Reporte logístico exportado exitosamente.');
	});

	$('[data-toggle="tooltip"]').tooltip();
})(jQuery);
