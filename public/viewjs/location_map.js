/* ==========================================================================
   DAILY — Mapa de Ubicaciones View JavaScript
   Interactive filtering, branch tabs, location detail drawer, and units modal
   ========================================================================== */

(function ($) {
	'use strict';

	var data = window.DailyLocationMapData || {};
	var stockByLoc = data.stockByLoc || {};
	var unitsByLoc = data.unitsByLoc || {};
	var baseUrl = data.baseUrl || '/';

	var activeBranch = '';
	var activeClass = '';
	var activeStock = '';
	var filterExpiredOnly = false;
	var filterExpiringOnly = false;
	var searchQuery = '';

	// Inicializar pestaña activa
	var $firstTab = $('.daily-branch-tab-btn.is-active');
	if ($firstTab.length) {
		activeBranch = $firstTab.data('branch');
	}

	function applyFilters() {
		var q = searchQuery.toLowerCase().trim();

		$('.daily-location-card').each(function () {
			var $card = $(this);
			var cBranch = $card.data('branch') || '';
			var cClass = $card.data('class') || '';
			var cUnits = parseFloat($card.data('units')) || 0;
			var cExpired = parseFloat($card.data('expired')) || 0;
			var cExpiring = parseFloat($card.data('expiring')) || 0;
			var cName = ($card.data('name') || '').toLowerCase();
			var cCode = ($card.data('code') || '').toLowerCase();
			var locId = $card.data('id');

			// Búsqueda de productos contenidos
			var prodMatch = false;
			if (q && stockByLoc[locId]) {
				stockByLoc[locId].forEach(function (item) {
					if ((item.product_name || '').toLowerCase().indexOf(q) !== -1 ||
						(item.lot_number || '').toLowerCase().indexOf(q) !== -1 ||
						(item.sku || '').toLowerCase().indexOf(q) !== -1) {
						prodMatch = true;
					}
				});
			}

			var matchBranch = !activeBranch || cBranch === activeBranch;
			var matchClass = !activeClass || cClass === activeClass || (activeClass === 'chilled' && cClass === 'refrigerated');
			var matchStock = !activeStock || (activeStock === 'has_stock' && cUnits > 0) || (activeStock === 'empty' && cUnits === 0);
			var matchExpired = !filterExpiredOnly || cExpired > 0;
			var matchExpiring = !filterExpiringOnly || cExpiring > 0;
			var matchSearch = !q || cName.indexOf(q) !== -1 || cCode.indexOf(q) !== -1 || prodMatch;

			if (matchBranch && matchClass && matchStock && matchExpired && matchExpiring && matchSearch) {
				$card.show();
			} else {
				$card.hide();
			}
		});

		// Si la ubicación seleccionada quedó oculta, seleccionar la primera visible
		var $visibleSelected = $('.daily-location-card.is-selected:visible');
		if (!$visibleSelected.length) {
			var $firstVisible = $('.daily-location-card:visible').first();
			if ($firstVisible.length) {
				selectLocation($firstVisible);
			}
		}
	}

	function selectLocation($card) {
		if (!$card || !$card.length) { return; }

		$('.daily-location-card').removeClass('is-selected');
		$card.addClass('is-selected');

		var id = $card.data('id');
		var name = $card.data('name');
		var branch = $card.data('branch');
		var cLabel = $card.data('class-label');
		var cClass = $card.data('class-class');
		var code = $card.data('code');
		var val = $card.data('value');
		var expired = $card.data('expired');
		var expiring = $card.data('expiring');
		var imgSrc = $card.data('img');

		// Actualizar panel lateral
		$('#detail-loc-name').text(name);
		$('#detail-loc-subname').text(name);
		$('#detail-loc-branch').text(branch);
		$('#detail-loc-warehouse').text('Sin almacén');
		$('#detail-loc-zone').text('Sin zona');
		$('#detail-loc-aisle').text('Sin pasillo');
		$('#detail-loc-value').text(val);
		$('#detail-loc-expired').text(expired);
		$('#detail-loc-expiring').text(expiring);

		// Badge de clase
		$('#detail-loc-class-badge').attr('class', 'badge badge-light border ' + cClass).text(cLabel);

		// Imagen hero
		if (imgSrc) {
			$('#detail-loc-hero').css('background-image', 'url(' + imgSrc + ')');
		}

		// Enlaces de acciones
		$('#btn-open-scan').attr('href', baseUrl + 'scan?code=' + encodeURIComponent(code));
		$('#btn-view-stock').attr('href', baseUrl + 'stockoverview?location_id=' + id);
		$('#btn-print-label').attr('href', baseUrl + 'locations/labels?location_id=' + id);
		$('#btn-edit-loc').attr('href', baseUrl + 'location/' + id);

		// Lista de productos
		var items = stockByLoc[id] || [];
		var $pList = $('#detail-products-list');
		if (items.length === 0) {
			$pList.html('<div class="small font-weight-700 text-dark mb-2"><i class="fa-solid fa-boxes-stacked mr-1 text-secondary"></i> Contenido de existencias</div><p class="small text-muted mb-0">Sin existencias registradas en esta ubicación.</p>');
		} else {
			var rowsHtml = items.map(function (item) {
				var bbd = item.best_before_date || '—';
				var isExp = bbd !== '—' && bbd < new Date().toISOString().slice(0, 10);
				return '<tr>' +
					'<td>' +
						'<div class="font-weight-600 text-dark">' + escapeHtml(item.product_name) + '</div>' +
						'<div class="text-muted" style="font-size: 10px;">Lote: ' + escapeHtml(item.lot_number || '—') + ' · Vence: <span class="' + (isExp ? 'text-danger font-weight-700' : '') + '">' + escapeHtml(bbd) + '</span></div>' +
					'</td>' +
					'<td class="text-right font-weight-700 text-nowrap">' +
						Number(item.amount).toLocaleString('es', { maximumFractionDigits: 1 }) + ' ' + escapeHtml(item.unit_name || '') +
					'</td>' +
				'</tr>';
			}).join('');

			$pList.html('<div class="small font-weight-700 text-dark mb-2"><i class="fa-solid fa-boxes-stacked mr-1 text-secondary"></i> Contenido de existencias (' + items.length + ' líneas)</div>' +
				'<table class="table table-sm mb-0 small"><tbody>' + rowsHtml + '</tbody></table>');
		}
	}

	function escapeHtml(text) {
		if (!text) return '';
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/* ---------- Event Listeners ---------- */

	// Pestañas de sucursal
	$('.daily-branch-tab-btn').on('click', function () {
		$('.daily-branch-tab-btn').removeClass('is-active');
		$(this).addClass('is-active');
		activeBranch = $(this).data('branch');
		$('#locmap-current-path').text(activeBranch + ' › Sin almacén › Sin zona › Sin pasillo');
		applyFilters();
	});

	// Botones "Ver ubicaciones" en otras sucursales
	$(document).on('click', '.btn-switch-branch', function () {
		var targetBranch = $(this).data('branch');
		$('.daily-branch-tab-btn').filter('[data-branch="' + targetBranch + '"]').trigger('click');
		$('html, body').animate({ scrollTop: $('.daily-locmap-branch-bar').offset().top - 80 }, 300);
	});

	// Clic en tarjeta de ubicación
	$(document).on('click', '.daily-location-card', function (e) {
		if ($(e.target).closest('.btn-view-units').length) {
			return; // Permitir que el clic en ver unidades abra el modal
		}
		e.preventDefault();
		selectLocation($(this));
	});

	// Filtro de búsqueda en tiempo real
	$('#locmap-search').on('input', function () {
		searchQuery = $(this).val();
		applyFilters();
	});

	// Filtro de clase
	$('#filter-class').on('change', function () {
		activeClass = $(this).val();
		applyFilters();
	});

	// Filtro de existencias
	$('#filter-stock').on('change', function () {
		activeStock = $(this).val();
		applyFilters();
	});

	// Filtro Vencidos
	$('#filter-expired-btn').on('click', function () {
		filterExpiredOnly = !filterExpiredOnly;
		$(this).toggleClass('is-active', filterExpiredOnly);
		applyFilters();
	});

	// Filtro Por vencer (3 días)
	$('#filter-expiring-btn').on('click', function () {
		filterExpiringOnly = !filterExpiringOnly;
		$(this).toggleClass('is-active', filterExpiringOnly);
		applyFilters();
	});

	// Modal Desglose de Unidades
	$(document).on('click', '.btn-view-units', function (e) {
		e.preventDefault();
		var locId = $(this).data('loc-id');
		var locName = $(this).data('loc-name');
		var breakdown = unitsByLoc[locId] || {};

		$('#modal-units-loc-title').html('<i class="fa-solid fa-location-dot mr-1 text-success"></i> ' + escapeHtml(locName));

		var rows = Object.keys(breakdown).map(function (unitName) {
			return '<li class="list-group-item d-flex justify-content-between align-items-center">' +
				'<span><i class="fa-solid fa-boxes-packing mr-2 text-secondary"></i>' + escapeHtml(unitName) + '</span>' +
				'<span class="badge badge-primary badge-pill font-weight-700" style="font-size: 14px;">' +
				Number(breakdown[unitName]).toLocaleString('es', { maximumFractionDigits: 1 }) +
				'</span>' +
			'</li>';
		}).join('');

		if (!rows) {
			rows = '<li class="list-group-item text-muted">Sin existencias registradas</li>';
		}

		$('#modal-units-content').html('<ul class="list-group">' + rows + '</ul>');
		$('#modal-units-breakdown').modal('show');
	});

	// Botones de conmutación de vista (Por sucursal / Lista)
	$('#btn-view-branch').on('click', function () {
		$('.daily-locmap-view-btn').removeClass('is-active');
		$(this).addClass('is-active');
		$('#location-cards-container').removeClass('is-list-view');
	});

	$('#btn-view-list').on('click', function () {
		$('.daily-locmap-view-btn').removeClass('is-active');
		$(this).addClass('is-active');
		$('#location-cards-container').addClass('is-list-view');
	});

	// Cerrar panel de detalle
	$('#btn-close-detail').on('click', function () {
		$('#detail-panel').hide();
	});

	// Aplicar filtros iniciales
	applyFilters();

})(jQuery);
