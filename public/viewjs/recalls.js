/* Retiros de producto: alta con vista previa de lotes, confirmación por sucursal, cierre y anulación */
(function ($) {
	'use strict';
	var S = window.DairyRecalls || {};
	var UI = window.DairyUI;
	var kv = function (o) { return Object.keys(o).map(function (k) { return { id: k, name: o[k] }; }); };

	// 1. Apertura del diálogo de nuevo retiro (desde header o botón de estado vacío)
	$(document).on('click', '#rc-new, #rc-new-empty', function () {
		var html =
			'<div class="form-group"><label class="font-weight-600">Producto a retirar</label><select name="product_id" class="custom-select" required><option value="">Seleccione un producto…</option>' + UI.options(S.products) + '</select></div>' +
			'<div class="form-row"><div class="form-group col-6"><label class="font-weight-600">Vence desde (opcional)</label><input type="date" name="best_before_from" class="form-control"></div>' +
			'<div class="form-group col-6"><label class="font-weight-600">Vence hasta (opcional)</label><input type="date" name="best_before_to" class="form-control"></div></div>' +
			'<div class="small text-muted mb-3"><i class="fa-solid fa-circle-info mr-1 text-primary"></i>Si deja las fechas en blanco, se retendrán todos los lotes con existencia del producto.</div>' +
			'<div class="form-group"><label class="font-weight-600">Clase de riesgo</label><select name="severity" class="custom-select">' + UI.options(kv(S.severities), 'class_2') + '</select></div>' +
			'<div class="form-group"><label class="font-weight-600">Disposición final</label><select name="disposition" class="custom-select">' + UI.options(kv(S.dispositions), 'destroy') + '</select></div>' +
			'<div class="form-group"><label class="font-weight-600">Proveedor <span class="text-muted small">(por defecto el registrado en la ficha del producto)</span></label><select name="supplier_id" class="custom-select"><option value="">— Predeterminado del producto —</option>' + UI.options(S.suppliers) + '</select></div>' +
			'<div class="form-group"><label class="font-weight-600">Motivo del retiro</label><textarea name="reason" class="form-control" rows="2" required placeholder="Aviso del proveedor, alerta de autoridad sanitaria, hallazgo crítico de calidad o inocuidad…"></textarea></div>' +
			'<div class="rc-preview p-2 border rounded bg-light small mt-2"></div>';

		var dlg = UI.formDialog('Nuevo retiro de producto', html, function (data, d) {
			UI.post('recalls', data, function (r) {
				toastr.success(r.recall_code + ': ' + r.lots + ' lote(s) retenidos en ' + r.branches + ' sucursal(es)');
				d.modal('hide');
				location.href = U('/recalls?id=' + r.id);
			}, d.find('.btn-primary'));
		}, 'Retener lotes y bloquear venta');

		var preview = function () {
			var f = dlg.find('form');
			var pid = f.find('[name=product_id]').val();
			var $p = dlg.find('.rc-preview');
			if (!pid) {
				$p.html('<span class="text-muted"><i class="fa-solid fa-circle-question mr-1"></i>Seleccione un producto para previsualizar los lotes y existencias a retener.</span>');
				return;
			}
			$p.html('<span class="text-muted"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Calculando lotes afectados en la red…</span>');
			UI.get('recalls/preview?product_id=' + pid + '&best_before_from=' + (f.find('[name=best_before_from]').val() || '') + '&best_before_to=' + (f.find('[name=best_before_to]').val() || ''), function (rows) {
				if (!rows || !rows.length) {
					$p.html('<div class="alert alert-warning mb-0 py-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No hay existencias libres en inventario que coincidan con estos parámetros.</div>');
					return;
				}
				var total = 0;
				$p.html('<div class="font-weight-700 text-dark mb-1"><i class="fa-solid fa-boxes-stacked mr-1 text-success"></i>Lotes que serán puestos en cuarentena:</div>' + rows.map(function (r) {
					total += Number(r.amount);
					return '<div class="py-1 border-bottom border-white">' +
						(Number(r.is_transit) ? '<span class="badge badge-warning mr-1">En tránsito</span> ' : '') +
						'<strong>' + UI.esc(r.branch_name) + '</strong> · ' + UI.esc(r.location_name) + ' · vence ' + UI.esc(r.best_before_date) + ' · <span class="badge badge-secondary">' + Number(r.amount) + ' u.</span>' +
						'</div>';
				}).join('') + '<div class="mt-2 font-weight-700 text-success"><i class="fa-solid fa-lock mr-1"></i>Total a bloquear: ' + total + ' unidades</div>');
			});
		};

		preview();
		dlg.on('change', '[name=product_id],[name=best_before_from],[name=best_before_to]', preview);
	});

	// 2. Confirmación de lote separado físicamente por la sucursal
	$(document).on('click', '.rc-confirm', function () {
		var $b = $(this);
		var html = '<div class="alert alert-info py-2 small mb-3"><i class="fa-solid fa-location-dot mr-1"></i>' + UI.esc($b.data('label')) + '</div>' +
			'<div class="form-group"><label class="font-weight-600">Cantidad física encontrada y separada</label><input type="number" step="any" min="0" name="found_amount" class="form-control" value="' + $b.data('amount') + '" required></div>' +
			'<div class="form-group"><label class="font-weight-600">Observación <span class="text-muted small font-weight-normal">(obligatoria si difiere del sistema)</span></label><input type="text" name="notes" class="form-control" placeholder="Ubicación física de resguardo, motivo de diferencia, precinto…"></div>';

		UI.formDialog('Confirmar separación de lote', html, function (data, d) {
			UI.post('recalls/items/' + $b.data('id') + '/confirm', data, function (r) {
				toastr.success(r.message);
				d.modal('hide');
				UI.reload();
			}, d.find('.btn-primary'));
		}, 'Confirmar lote separado');
	});

	// 3. Cierre de retiro
	$('#rc-close').on('click', function () {
		var id = $(this).data('id');
		var html = '<p class="text-dark">Al cerrar el retiro, todo el stock retenido y separado se dará de baja como <strong>merma de calidad a costo real</strong> en el kardex.</p>' +
			'<p class="small text-muted mb-3"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>Si la disposición es <em>Devolver al proveedor</em>, se creará automáticamente el registro de reclamo y nota de crédito en el módulo de compras.</p>' +
			'<div class="form-group"><label class="font-weight-600">Notas de cierre</label><textarea name="notes" class="form-control" rows="2" placeholder="Número de acta, transportista de retiro, disposición final acordada…"></textarea></div>';

		UI.formDialog('Cerrar y liquidar retiro', html, function (data, d) {
			UI.post('recalls/' + id + '/close', data, function (r) {
				toastr.success(r.message);
				d.modal('hide');
				UI.reload();
			}, d.find('.btn-primary'));
		}, 'Cerrar retiro y dar de baja');
	});

	// 4. Anulación de retiro
	$('#rc-cancel').on('click', function () {
		var id = $(this).data('id');
		var html = '<p class="text-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Los lotes retenidos en cuarentena volverán a estar disponibles inmediatamente para la venta.</p>' +
			'<div class="form-group"><label class="font-weight-600">Motivo de anulación (obligatorio)</label><input type="text" name="reason" class="form-control" required placeholder="Notificación revocada por el proveedor, error de digitación…"></div>';

		UI.formDialog('Anular retiro de producto', html, function (data, d) {
			UI.post('recalls/' + id + '/cancel', data, function () {
				toastr.success('Retiro anulado y stock liberado de cuarentena');
				d.modal('hide');
				UI.reload();
			}, d.find('.btn-primary'));
		}, 'Anular retiro');
	});

	// 5. Filtros en vivo (Tabs, Búsqueda y Dropdown de Estado)
	var activeFilter = 'all'; // 'all', 'open', 'closed', 'cancelled'

	function applyFilters() {
		var q = ($('#rc-search').val() || '').toLowerCase().trim();
		var visibleCount = 0;
		var totalRows = $('.rc-row').length;

		if (totalRows === 0) {
			return;
		}

		$('.rc-row').each(function () {
			var $r = $(this);
			var st = $r.data('status');
			var txt = ($r.data('search') || '').toString();

			var matchesTab = (activeFilter === 'all') || (st === activeFilter);
			var matchesSearch = (q === '') || (txt.indexOf(q) !== -1);

			if (matchesTab && matchesSearch) {
				$r.show();
				visibleCount++;
			} else {
				$r.hide();
			}
		});

		if (visibleCount === 0 && totalRows > 0) {
			$('#rc-no-results-row').show();
		} else {
			$('#rc-no-results-row').hide();
		}
	}

	// Pestañas (Todos, Abiertos, Cerrados)
	$('.daily-rc-tab-btn').on('click', function () {
		$('.daily-rc-tab-btn').removeClass('is-active');
		$(this).addClass('is-active');
		activeFilter = $(this).data('filter');
		$('#rc-filter-status-label').text($(this).text().split(' ')[0]);
		applyFilters();
	});

	// Dropdown de estado
	$('.rc-status-opt').on('click', function (e) {
		e.preventDefault();
		$('.rc-status-opt').removeClass('active font-weight-600');
		$(this).addClass('active font-weight-600');
		activeFilter = $(this).data('status');
		$('#rc-filter-status-label').text($(this).text());

		// Sincronizar con los botones pill si coincide
		$('.daily-rc-tab-btn').removeClass('is-active');
		if (activeFilter === 'all') {
			$('#rc-tab-all').addClass('is-active');
		} else if (activeFilter === 'open') {
			$('#rc-tab-open').addClass('is-active');
		} else if (activeFilter === 'closed') {
			$('#rc-tab-closed').addClass('is-active');
		}

		applyFilters();
	});

	// Entrada de búsqueda
	$('#rc-search').on('input', function () {
		applyFilters();
	});

})(jQuery);
