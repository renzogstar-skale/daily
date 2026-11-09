/* ==========================================================================
   DAIRY — Experiencia móvil: drawer, escáner, notificaciones y PWA
   ========================================================================== */
(function($) {
	'use strict';
	var cfg = window.DairyMobile || { can: {} };

	function store(key, val) {
		try { if (val === undefined) { return window.localStorage.getItem(key); } window.localStorage.setItem(key, val); } catch (e) { return null; }
	}

	/* ---------- Drawer móvil: fondo, cierre al navegar y gesto de deslizar ---------- */
	var $drawer = $('#sidebarResponsive');
	var $backdrop = $('<div class="dairy-drawer-backdrop" aria-hidden="true"></div>').appendTo('body');
	$drawer.on('show.bs.collapse', function() { $('body').addClass('dairy-drawer-open'); });
	$drawer.on('hide.bs.collapse', function() { $('body').removeClass('dairy-drawer-open'); });
	$backdrop.on('click', function() { $drawer.collapse('hide'); });
	$(document).on('click', '[data-dairy-open-drawer]', function() {
		setTimeout(function() { $drawer.collapse('show'); }, 200);
	});
	$drawer.on('click', 'a.nav-link[href]:not(.nav-link-collapse):not(.dropdown-toggle), a.dropdown-item[href]', function() {
		if (window.innerWidth < 768) { $drawer.collapse('hide'); }
	});
	var touchX = null;
	$drawer.on('touchstart', function(e) { touchX = e.originalEvent.touches[0].clientX; });
	$drawer.on('touchend', function(e) {
		if (touchX !== null && touchX - e.originalEvent.changedTouches[0].clientX > 70) { $drawer.collapse('hide'); }
		touchX = null;
	});

	/* ---------- Tablas anchas sin envoltorio: desplazamiento horizontal ---------- */
	function wrapTables() {
		if (window.innerWidth >= 992) { return; }
		$('#page-content table.table').each(function() {
			var $t = $(this);
			if ($t.closest('.table-responsive, .dataTables_wrapper, .dataTables_scrollBody, .modal').length || $t.data('dairyWrapped')) { return; }
			$t.data('dairyWrapped', true).wrap('<div class="table-responsive dairy-table-scroll"></div>');
		});
	}
	$(wrapTables);

	/* ---------- Móvil: tablas → tarjetas con datos prioritarios y detalle desplegable (lista Fiori) ----------
	   Título = primera columna con texto; 4 columnas siguientes visibles; acciones siempre visibles; el resto
	   en "Detalle". Las tablas de comparación (.dairy-keep-table, kardex, turnos, presupuesto) mantienen columnas
	   con desplazamiento horizontal localizado. Solo marca clases: el CSS aplica únicamente < 768 px. */
	var mqMobile = window.matchMedia('(max-width: 767.98px)');
	var KEEP_TABLE = '.dairy-keep-table, .sch-grid, .sch-heat, .dairy-budget-table, #kx-table';
	function isActionCell($c) {
		if (!$c.find('.btn, button, select, input:not([type=hidden]), .dropdown').length) { return false; }
		return $.trim($c.clone().find('.btn, button, select, input, .dropdown, .badge').remove().end().text()).length === 0;
	}
	function cardTable(table) {
		var $t = $(table);
		if (!$t.is('table') || $t.is(KEEP_TABLE) || $t.closest('.dataTables_scrollHead, .dataTables_scrollFoot, .daterangepicker').length) { return; }
		var heads = $t.find('> thead > tr:last-child > th, > thead > tr:last-child > td').map(function() { return $.trim($(this).text()); }).get();
		if (heads.length < 4) { return; }
		var titleCol = -1;
		$.each(heads, function(i, h) { if (titleCol < 0 && /^(producto|product|nombre|name|artículo|empleado|proveedor|cliente)(\s|$)/i.test(h)) { titleCol = i; } });
		$t.addClass('dairy-card-table');
		$t.find('> tbody > tr:not([data-dairy-card])').each(function() {
			var $tr = $(this).attr('data-dairy-card', '1');
			var $cells = $tr.children('td, th');
			if ($cells.length === 1) {
				if (!$cells.hasClass('dataTables_empty')) { $tr.addClass('dairy-row-heading'); }
				return;
			}
			var pos = 0, title = null, shown = 0, hidden = 0;
			$cells.each(function() {
				var $c = $(this), col = pos;
				if (!$c.is('[data-label]')) { $c.attr('data-label', heads[pos] || ''); }
				pos += parseInt($c.attr('colspan') || '1', 10);
				if (col === titleCol && $.trim($c.text()).length > 0 && !title) { title = $c.addClass('dairy-cell-title'); return; }
				var hasText = $.trim($c.text()).length > 0 || $c.find('img, i, .badge, input').length > 0;
				if (isActionCell($c)) { $c.addClass('dairy-cell-actions'); }
				else if (!title && titleCol < 0 && $.trim($c.text()).length > 0) { title = $c.addClass('dairy-cell-title'); }
				else if (!hasText) { $c.addClass('dairy-cell-detail'); }
				else if (shown < 4) { shown++; }
				else { $c.addClass('dairy-cell-detail'); hidden++; }
			});
			if (title && hidden > 0) {
				title.append('<button type="button" class="dairy-card-more d-md-none" aria-expanded="false">Detalle <i class="fa-solid fa-chevron-down"></i></button>');
			}
		});
	}
	function cardTables() {
		if (!mqMobile.matches) { return; }
		$('#page-content table.table, .modal table.table').each(function() { cardTable(this); });
	}
	$(document).on('click', '.dairy-card-more', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var $tr = $(this).closest('tr').toggleClass('is-open');
		$(this).attr('aria-expanded', $tr.hasClass('is-open') ? 'true' : 'false');
	});
	$(cardTables);
	$(document).on('draw.dt init.dt shown.bs.modal shown.bs.tab', function() { setTimeout(cardTables, 0); });
	if (window.MutationObserver) {
		var cardTimer = null;
		new MutationObserver(function() {
			clearTimeout(cardTimer);
			cardTimer = setTimeout(cardTables, 120);
		}).observe(document.body, { childList: true, subtree: true });
	}
	if (mqMobile.addEventListener) { mqMobile.addEventListener('change', cardTables); }

	/* ---------- Borrador de formularios (form[data-dairy-draft]): no se pierde lo escrito al navegar ----------
	   Solo en esta pestaña (sessionStorage); no guarda archivos ni campos ocultos. Se limpia con DairyDraft.clear(). */
	var DRAFT_FIELDS = 'input[id]:not([type=file]):not([type=hidden]):not([type=password]), select[id], textarea[id]';
	function draftKey(name) { return 'dairyDraft:' + name; }
	function draftRead(name) {
		try { return JSON.parse(window.sessionStorage.getItem(draftKey(name)) || 'null'); } catch (e) { return null; }
	}
	window.DairyDraft = {
		clear: function(name) { try { window.sessionStorage.removeItem(draftKey(name)); } catch (e) { /* sin almacenamiento */ } }
	};
	$(function() {
		$('form[data-dairy-draft]').each(function() {
			var $form = $(this), name = $form.data('dairyDraft');
			var data = draftRead(name);
			// Un enlace con parámetros (p. ej. ?product_id= desde Calidad o el escáner) tiene prioridad sobre el borrador
			if (data && !window.location.search) {
				$form.find(DRAFT_FIELDS).each(function() {
					if (!(this.id in data)) { return; }
					if (this.type === 'checkbox' || this.type === 'radio') { this.checked = !!data[this.id]; }
					else { $(this).val(data[this.id]); }
					$(this).trigger('change');
				});
				toastr.info('Se recuperó el borrador sin enviar');
			}
			$form.on('input change', DRAFT_FIELDS, function(e) {
				if (!e.originalEvent || !e.originalEvent.isTrusted) { return; } // solo lo que escribe la persona
				var out = {};
				$form.find(DRAFT_FIELDS).each(function() { out[this.id] = (this.type === 'checkbox' || this.type === 'radio') ? this.checked : $(this).val(); });
				try { window.sessionStorage.setItem(draftKey(name), JSON.stringify(out)); } catch (e) { /* sin almacenamiento */ }
			});
		});
	});

	/* ---------- Teclado abierto: oculta barra inferior y centra el campo enfocado ---------- */
	var KB_FIELDS = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=file]):not([type=range]), textarea, select';
	$(document).on('focusin', KB_FIELDS, function() {
		if (!mqMobile.matches) { return; }
		var el = this;
		$('body').addClass('dairy-kb-open');
		setTimeout(function() { if (document.activeElement === el && el.scrollIntoView) { el.scrollIntoView({ block: 'center' }); } }, 300);
	});
	$(document).on('focusout', KB_FIELDS, function() {
		setTimeout(function() { if (!$(document.activeElement).is(KB_FIELDS)) { $('body').removeClass('dairy-kb-open'); } }, 100);
	});

	/* ---------- Escáner de códigos ---------- */
	var reader = null, stream = null, busy = false, session = 0;

	function loadZxing(cb) {
		if (window.ZXing) { return cb(); }
		$.ajax({ url: cfg.zxingUrl, dataType: 'script', cache: true }).done(cb).fail(function() { setStatus('No se pudo cargar el lector. Usa el ingreso manual.'); });
	}

	function setStatus(t) { $('#dairyScanStatus').text(t); }

	function stopCamera() {
		session++;
		if (reader) { try { reader.reset(); } catch (e) { } reader = null; }
		if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); stream = null; }
		$('#dairyScanTorch').addClass('d-none');
	}

	function startCamera() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.isSecureContext) {
			$('#dairyScanVideoWrap').addClass('d-none');
			setStatus('La cámara requiere HTTPS. Escribe el código manualmente.');
			return;
		}
		$('#dairyScanVideoWrap').removeClass('d-none');
		var mine = ++session;
		loadZxing(function() {
			if (mine !== session) { return; }
			var video = document.getElementById('dairyScanVideo');
			reader = new ZXing.BrowserMultiFormatReader();
			reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, video, function(result) {
				if (result && !busy) {
					if (navigator.vibrate) { navigator.vibrate(60); }
					lookup(result.getText());
				}
			}).then(function() {
				if (mine !== session) { if (video.srcObject) { video.srcObject.getTracks().forEach(function(t) { t.stop(); }); } return; }
				stream = video.srcObject;
				var track = stream && stream.getVideoTracks()[0];
				if (track && track.getCapabilities && track.getCapabilities().torch) { $('#dairyScanTorch').removeClass('d-none'); }
			}).catch(function() {
				if (mine !== session) { return; }
				$('#dairyScanVideoWrap').addClass('d-none');
				if (!busy) { setStatus('Sin acceso a la cámara. Escribe el código manualmente.'); }
			});
		});
	}

	var torchOn = false;
	$('#dairyScanTorch').on('click', function() {
		var track = stream && stream.getVideoTracks()[0];
		if (!track) { return; }
		torchOn = !torchOn;
		track.applyConstraints({ advanced: [{ torch: torchOn }] }).catch(function() { });
	});

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

	function actionBtn(href, icon, label, cls) {
		return '<a class="btn ' + (cls || 'btn-outline-primary') + ' dairy-scan-action" href="' + U(href) + '"><i class="fa-solid ' + icon + '"></i><span>' + label + '</span></a>';
	}

	function hasPerm(name) {
		var p = (Grocy.UserPermissions || []).filter(function(i) { return i.permission_name === name; })[0];
		return !p || p.has_permission != 0;
	}

	function lookup(code) {
		code = $.trim(code || '');
		if (!code) { return; }
		busy = true;
		setStatus('Buscando "' + code + '"…');
		Grocy.Api.Get('stock/products/by-barcode/' + encodeURIComponent(code), function(d) {
			stopCamera();
			$('#dairyScanVideoWrap').addClass('d-none');
			var id = d.product.id, qu = d.quantity_unit_stock ? d.quantity_unit_stock.name : '';
			var html = '<div class="dairy-scan-card"><div class="font-weight-bold">' + esc(d.product.name) + '</div>'
				+ '<div class="small text-muted">Código ' + esc(code) + (d.location ? ' · ' + esc(d.location.name) : '') + '</div>'
				+ '<div class="mt-1">Stock: <strong>' + esc(d.stock_amount) + ' ' + esc(qu) + '</strong>'
				+ (d.next_due_date && d.next_due_date !== '2999-12-31' ? ' · Vence ' + esc(d.next_due_date) : '') + '</div></div>'
				+ '<div class="dairy-scan-actions">';
			if (cfg.can.waste) { html += actionBtn('/waste/report?product_id=' + id, 'fa-trash-can', 'Merma', 'btn-outline-danger'); }
			if (hasPerm('STOCK_CONSUME')) { html += actionBtn('/consume?product=' + id, 'fa-utensils', 'Consumir'); }
			if (hasPerm('STOCK_PURCHASE')) { html += actionBtn('/purchase?product=' + id, 'fa-cart-plus', 'Recibir'); }
			if (cfg.can.inventory) { html += actionBtn('/inventory?product=' + id, 'fa-list-check', 'Conteo'); }
			if (cfg.can.kardex) { html += actionBtn('/kardex?product_id=' + id, 'fa-book-open', 'Kardex'); }
			if (cfg.can.stock) { html += actionBtn('/stockentries?product=' + id, 'fa-boxes-stacked', 'Lotes'); }
			html += '</div><button type="button" class="btn btn-link btn-block mt-1" id="dairyScanAgain"><i class="fa-solid fa-rotate-right mr-1"></i>Escanear otro</button>';
			$('#dairyScanResult').html(html).removeClass('d-none');
			setStatus('Producto encontrado.');
			busy = false;
		}, function() {
			setStatus('Código "' + code + '" no registrado. Intenta de nuevo o escríbelo.');
			setTimeout(function() { busy = false; }, 1500);
		});
	}

	$(document).on('click', '[data-dairy-scan]', function() {
		setTimeout(function() { $('#dairyScanModal').modal('show'); }, $(this).is('[data-dismiss]') ? 250 : 0);
	});
	$('#dairyScanModal').on('shown.bs.modal', function() {
		$('#dairyScanResult').addClass('d-none').empty();
		$('#dairyScanInput').val('');
		setStatus('Apunta la cámara al código de barras o QR.');
		busy = false;
		startCamera();
	}).on('hidden.bs.modal', stopCamera);
	$('#dairyScanManual').on('submit', function(e) { e.preventDefault(); lookup($('#dairyScanInput').val()); });
	$(document).on('click', '#dairyScanAgain', function() {
		$('#dairyScanResult').addClass('d-none').empty();
		busy = false;
		startCamera();
	});

	/* ---------- PWA: service worker e instalación ---------- */
	var swReg = null;
	if ('serviceWorker' in navigator) {
		if (cfg.swUrl && window.isSecureContext) {
			navigator.serviceWorker.register(cfg.swUrl, { scope: Grocy.BaseUrl.replace(/\/?$/, '/') })
				.then(function(r) { swReg = r; }).catch(function() { });
		} else {
			// En entorno de escritorio/desarrollo, desregistrar para no saturar peticiones
			navigator.serviceWorker.getRegistrations().then(function(regs) {
				for (var i = 0; i < regs.length; i++) {
					regs[i].unregister();
				}
			}).catch(function() { });
		}
	}
	var installEvt = null;
	window.addEventListener('beforeinstallprompt', function(e) {
		e.preventDefault();
		installEvt = e;
		$('#dairyInstallApp').removeClass('d-none');
	});
	$('#dairyInstallApp').on('click', function() {
		if (!installEvt) { return; }
		installEvt.prompt();
		installEvt.userChoice.finally(function() { installEvt = null; $('#dairyInstallApp').addClass('d-none'); });
	});

	/* ---------- Notificaciones de alertas críticas (merma, vencimiento, frío) ---------- */
	function notifyState() {
		var $b = $('#dairyNotifyToggle');
		if (!('Notification' in window)) { $b.addClass('d-none'); return; }
		var on = Notification.permission === 'granted' && store('dairy_notify') !== 'off';
		$b.toggleClass('is-on', on).find('span').text(on ? 'Notificaciones activas' : 'Activar alertas');
	}
	$('#dairyNotifyToggle').on('click', function() {
		if (Notification.permission === 'granted') {
			store('dairy_notify', store('dairy_notify') === 'off' ? 'on' : 'off');
			notifyState();
		} else if (Notification.permission !== 'denied') {
			Notification.requestPermission().then(function() { store('dairy_notify', 'on'); notifyState(); });
		} else {
			toastr.warning('Las notificaciones están bloqueadas en el navegador.');
		}
	});
	$(notifyState);

	// Recibe las alertas que ya consulta el layout (/api/ai/alerts) y notifica solo las críticas nuevas
	window.DairyMobile.notifyAlerts = function(alerts) {
		if (!('Notification' in window) || Notification.permission !== 'granted' || store('dairy_notify') === 'off' || !Array.isArray(alerts)) { return; }
		var seen = (store('dairy_notified') || '').split('|');
		var key = function(a) { return String(a.title) + ':' + String(a.description || ''); };
		var fresh = alerts.filter(function(a) { return a.severity === 'danger' && seen.indexOf(key(a)) === -1; });
		if (!fresh.length) { return; }
		var title = fresh.length === 1 ? fresh[0].title : fresh.length + ' alertas críticas en DAIRY';
		var opts = { body: fresh.length === 1 ? (fresh[0].description || '') : fresh.slice(0, 3).map(function(a) { return '• ' + a.title; }).join('\n'), icon: U('/img/icon-1024.png'), tag: 'dairy-alerts', data: { url: U('/today') } };
		if (swReg && swReg.showNotification) { swReg.showNotification(title, opts); } else { try { new Notification(title, opts); } catch (e) { } }
		store('dairy_notified', seen.concat(fresh.map(key)).slice(-60).join('|'));
	};
})(jQuery);
