/* DAILY: operaciones con escáner (consulta, conteo guiado, movimiento, recepción contra OC, preparación de pedidos) */
(function ($) {
	'use strict';
	var P = window.DairyScanPage || {}, U = window.DairyUI, esc = U.esc;
	var mode = 'lookup', session = null, cameraOn = false;
	var TYPES = { count: 'Conteo guiado', move: 'Movimiento', receive: 'Recepción contra OC', pick: 'Preparación de pedido' };
	var TITLES = {
		lookup: 'Consultar producto, lote o ubicación',
		count: 'Conteo guiado por ubicación',
		move: 'Mover existencias entre ubicaciones',
		receive: 'Recepción contra orden de compra',
		pick: 'Preparación de pedido de cliente'
	};
	var SUBMITS = {
		lookup: 'Consultar',
		count: 'Escanear unidad',
		move: 'Mover producto',
		receive: 'Recibir producto',
		pick: 'Validar producto'
	};
	var HELP = {
		count: 'Escanee cada unidad o indique la cantidad. Al cerrar se crea un conteo por producto; las diferencias pasan a aprobación de ajuste (el stock no cambia hasta aprobar).',
		move: 'Escanee lo que mueve del origen al destino. No puede mover más de lo que hay en el origen. Al cerrar se contabiliza en el kardex.',
		receive: 'Escanee los productos de la orden (GS1 trae lote y vencimiento). No se acepta más de lo pendiente. Al cerrar se crea la recepción REC y el stock entra con su lote.',
		pick: 'Tome primero el lote sugerido (FEFO). Si escanea otro lote se avisa. Al cerrar se asignan los lotes al pedido (el stock se descuenta al despachar).'
	};

	$('#sc-device span').text(DairyScan.deviceId());
	if (DairyScan.adapters.bridge.available()) {
		$('#sc-reader').html('<i class="fa-solid fa-microchip mr-1"></i><span>Lector industrial conectado</span>');
	}
	DairyScan.adapters.camera.setUrl(P.zxingUrl);
	DairyScan.start('wedge');
	DairyScan.start('bridge').catch(function () { /* sin SDK: modo teclado/cámara */ });
	DairyScan.onCode(function (evt) { handle(evt); });

	function msg(text, tone) {
		$('#sc-msg').attr('class', 'small mt-2 font-weight-600 text-' + (tone || 'muted')).text(text || '');
	}
	function num(v) {
		return v == null ? '—' : Number(v).toLocaleString('es', { maximumFractionDigits: 3 });
	}

	function updateHeroVisibility() {
		var hasResult = $('#sc-result').children().length > 0;
		var inSession = !!(session && session.status === 'open');
		var isSetup = !$('#sc-setup').hasClass('d-none');
		var showHero = (mode === 'lookup') && !hasResult && !inSession && !isSetup;
		$('#sc-hero-empty').toggleClass('d-none', !showHero);
	}

	/* ---------- modos ---------- */
	$('#sc-modes').on('click', 'a, .daily-op-card', function (e) {
		e.preventDefault();
		var targetMode = $(this).data('mode');
		if (!targetMode) { return; }
		if (session && session.status === 'open' && targetMode !== mode) {
			toastr.info('Cierre o anule la sesión ' + session.folio + ' antes de cambiar de operación');
			return;
		}
		setMode(targetMode);
	});

	function setMode(m) {
		mode = m;
		$('#sc-modes a, #sc-modes .daily-op-card').removeClass('active is-active').filter('[data-mode="' + m + '"]').addClass('active is-active');
		$('#sc-workstation-title').text(TITLES[m] || 'Operaciones con escáner');
		$('#sc-submit-label').text(SUBMITS[m] || 'Consultar');
		$('#sc-result').empty();
		$('#step-3').removeClass('is-active');

		$('#sc-session').toggleClass('d-none', !session || m === 'lookup');
		$('#sc-setup').toggleClass('d-none', m === 'lookup' || !!(session && session.status === 'open'));
		$('#sc-session-extra').toggleClass('d-none', m === 'lookup');
		$('#sc-setup-title').text('Nueva sesión: ' + (TYPES[m] || ''));
		$('#sc-setup [data-for]').each(function () {
			$(this).toggleClass('d-none', $(this).data('for').split(' ').indexOf(m) < 0);
		});
		$('[data-label-move]').toggleClass('d-none', m !== 'move');
		$('[data-label-pick]').toggleClass('d-none', m !== 'pick');
		$('[data-label-other]').toggleClass('d-none', m === 'move' || m === 'pick');
		fillSetup();
		$('#sc-input').attr('placeholder', m === 'lookup' ? 'Escanea o escribe código, SKU, lote o LOC-…' : 'Escanea el producto (o la etiqueta LOC- de la ubicación)').trigger('focus');
		updateHeroVisibility();
	}

	function fillSetup() {
		var b = parseInt($('#sc-branch').val(), 10);
		var locs = ((P.form && P.form.locations) || []).filter(function (l) { return l.branch_id === b; });
		var opts = '<option value="">Seleccione…</option>' + locs.map(function (l) {
			return '<option value="' + l.id + '">' + esc(l.name) + (l.path.length > 1 ? ' · ' + esc(l.path.slice(1).join(' › ')) : '') + ' (' + esc(l.label_code) + ')</option>';
		}).join('');
		$('#sc-location, #sc-target').html(opts);
		var orders = ((P.form && P.form.orders) || []).filter(function (o) { return o.branch_id === b; });
		var co = ((P.form && P.form.customer_orders) || []).filter(function (o) { return o.branch_id === b; });
		$('#sc-corder').html(co.length ? co.map(function (o) {
			return '<option value="' + o.id + '"' + (P.pickId === o.id ? ' selected' : '') + '>' + esc(o.order_code) + ' · ' + esc(o.customer_name) + '</option>';
		}).join('') : '<option value="">Sin pedidos confirmados</option>');
		$('#sc-order').html(orders.length ? orders.map(function (o) {
			return '<option value="' + o.id + '">' + esc(o.order_number) + ' · ' + esc(o.supplier_name || '') + '</option>';
		}).join('') : '<option value="">Sin órdenes aprobadas/enviadas</option>');
	}
	$('#sc-branch').on('change', fillSetup);

	$('#sc-setup-form').on('submit', function (e) {
		e.preventDefault();
		var body = {
			type: mode,
			branch_id: $('#sc-branch').val(),
			location_id: $('#sc-location').val() || null,
			target_location_id: $('#sc-target').val() || null,
			order_id: $('#sc-order').val() || null,
			customer_order_id: mode === 'pick' ? ($('#sc-corder').val() || null) : null,
			device_id: DairyScan.deviceId()
		};
		U.post('scan/sessions', body, function (r) {
			render(r);
			toastr.success((r.resumed ? 'Sesión retomada ' : 'Sesión ') + r.session.folio);
		}, $(this).find('button[type=submit]'));
	});

	/* ---------- captura ---------- */
	$('#sc-form').on('submit', function (e) {
		e.preventDefault();
		var code = $('#sc-input').val().trim();
		if (!code) {
			msg('Ingrese un código o escanee una etiqueta', 'warning');
			return;
		}
		$('#sc-input').val('');
		handle({
			code: code,
			source: 'manual',
			device_id: DairyScan.deviceId(),
			scanned_at: new Date().toISOString(),
			client_uuid: DairyScan.uuid()
		});
	});

	$('#sc-camera').on('click', function () {
		if (cameraOn) {
			DairyScan.stop('camera');
			cameraOn = false;
			$('#sc-video-wrap').addClass('d-none');
			$('#sc-pill-keyboard').trigger('click');
			return;
		}
		$('#sc-video-wrap').removeClass('d-none');
		DairyScan.start('camera', document.getElementById('sc-video')).then(function () {
			cameraOn = true;
			$('.daily-mode-pill').removeClass('is-active');
			$('#sc-pill-camera').addClass('is-active');
			$('#sc-mode-help-text').text('Cámara activa: apunta el visor hacia el código de barras o QR.');
			$('#sc-reader').html('<i class="fa-solid fa-camera mr-1"></i><span>Cámara activa</span>');
		}, function (err) {
			$('#sc-video-wrap').addClass('d-none');
			msg((err && err.message) || 'No se pudo abrir la cámara (permiso denegado o dispositivo sin cámara). Use el ingreso manual.', 'danger');
		});
	});

	/* ---------- modo de entrada pills ---------- */
	$('#sc-pill-keyboard').on('click', function () {
		$('.daily-mode-pill').removeClass('is-active');
		$(this).addClass('is-active');
		if (cameraOn) {
			DairyScan.stop('camera');
			cameraOn = false;
			$('#sc-video-wrap').addClass('d-none');
		}
		$('#sc-mode-help-text').text('Con lector tipo teclado, enfoca este campo y escanea.');
		$('#sc-reader').html('<i class="fa-solid fa-keyboard mr-1"></i><span>Modo teclado activo</span>');
		$('#sc-input').trigger('focus');
	});

	$('#sc-pill-camera').on('click', function () {
		if (!cameraOn) {
			$('#sc-camera').trigger('click');
		}
	});

	$('#sc-pill-manual').on('click', function () {
		$('.daily-mode-pill').removeClass('is-active');
		$(this).addClass('is-active');
		if (cameraOn) {
			DairyScan.stop('camera');
			cameraOn = false;
			$('#sc-video-wrap').addClass('d-none');
		}
		$('#sc-mode-help-text').text('Escribe el código manualmente y presiona Enter o el botón Consultar.');
		$('#sc-reader').html('<i class="fa-solid fa-pen mr-1"></i><span>Entrada manual</span>');
		$('#sc-input').trigger('focus');
	});

	function handle(evt) {
		var code = evt.code;
		// Etiqueta de ubicación durante la configuración de sesión: la selecciona
		if (/^LOC-/i.test(code) && mode !== 'lookup' && !(session && session.status === 'open')) {
			var loc = ((P.form && P.form.locations) || []).filter(function (l) { return l.label_code.toUpperCase() === code.toUpperCase(); })[0];
			if (!loc) {
				DairyScan.feedback(false);
				msg('Ubicación ' + code + ' no encontrada o sin acceso', 'danger');
				return;
			}
			$('#sc-branch').val(loc.branch_id);
			fillSetup();
			var target = (mode === 'move' && $('#sc-location').val()) ? '#sc-target' : '#sc-location';
			$(target).val(loc.id);
			DairyScan.feedback(true);
			msg('Ubicación ' + loc.name + ' seleccionada', 'success');
			return;
		}
		if (mode === 'lookup' || !session || session.status !== 'open') {
			return lookup(code);
		}
		addLine(evt);
	}

	function lookup(code) {
		msg('Buscando ' + code + '…');
		Grocy.Api.Get('scan/resolve?code=' + encodeURIComponent(code) + '&branch_id=' + encodeURIComponent($('#sc-branch').val() || ''), function (r) {
			DairyScan.feedback(r.kind !== 'unknown');
			msg('');
			$('#sc-result').html(renderLookup(r));
			$('#step-3').addClass('is-active');
			updateHeroVisibility();
		}, function (xhr) {
			DairyScan.feedback(false);
			msg(U.errMsg(xhr), 'danger');
			updateHeroVisibility();
		});
	}

	function renderLookup(r) {
		var p = r.parsed || {}, gs1 = p.format === 'gs1' ? '<div class="small text-muted mb-2"><i class="fa-solid fa-barcode mr-1"></i>GS1 · GTIN ' + esc(p.gtin || '—') + (p.lot ? ' · lote ' + esc(p.lot) : '') + (p.best_before ? ' · vence ' + esc(p.best_before) : '') + (p.serial ? ' · serie ' + esc(p.serial) : '') + '</div>' : '';
		if (r.kind === 'unknown') {
			return '<div class="daily-result-card"><div class="text-warning font-weight-700 mb-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>' + esc(r.message) + '</div>' + gs1 +
				(r.candidates.length ? '<div class="list-group">' + r.candidates.map(function (c) {
					return '<a class="list-group-item list-group-item-action font-weight-600" href="#" data-lookup="P' + c.id + '">' + esc(c.name) + '</a>';
				}).join('') + '</div>' : '<p class="small text-muted mb-0">Asocie el código al producto en Datos maestros → Productos → Códigos de barras.</p>') + '</div>';
		}
		if (r.kind === 'location') {
			var l = r.location;
			return '<div class="daily-result-card"><div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-0 font-weight-700"><i class="fa-solid fa-location-dot mr-2 text-success"></i>' + esc(l.name) + ' <span class="badge badge-light border ml-1">' + esc(l.label_code) + '</span></h4><div class="small text-muted mt-1">' + esc(l.path.join(' › ')) + (l.capacity_units ? ' · capacidad ' + num(l.capacity_units) : '') + '</div></div><a class="btn btn-sm btn-outline-secondary" href="' + U_('/locations') + '"><i class="fa-solid fa-map mr-1"></i>Ubicaciones</a></div>' +
				(r.stock.length ? '<div class="table-responsive"><table class="table table-sm mb-0 table-hover"><thead class="thead-light"><tr><th>Producto</th><th class="text-right">Stock</th><th>Próx. vencimiento</th><th class="text-right">Valor</th></tr></thead><tbody>' +
					r.stock.map(function (s) {
						return '<tr><td><a href="#" class="font-weight-600 text-primary" data-lookup="P' + s.product_id + '">' + esc(s.name) + '</a></td><td class="text-right font-weight-700">' + num(s.amount) + '</td><td>' + esc(s.next_expiry === '2999-12-31' ? '—' : s.next_expiry) + '</td><td class="text-right">' + U.money(s.value) + '</td></tr>';
					}).join('') +
					'</tbody></table></div>' : '<p class="p-3 small text-muted mb-0">Ubicación sin stock actualmente.</p>') + '</div>';
		}
		var a = r.availability, pr = r.product, today = new Date().toISOString().slice(0, 10);
		var actions = '<a class="btn btn-sm btn-outline-secondary mr-1" href="' + U_('/kardex?product_id=' + pr.id) + '"><i class="fa-solid fa-book-open mr-1"></i>Kardex</a>' +
			'<a class="btn btn-sm btn-outline-secondary mr-1" href="' + U_('/stockoverview?product_id=' + pr.id) + '"><i class="fa-solid fa-boxes-stacked mr-1"></i>Stock</a>' +
			(P.can.waste ? ' <a class="btn btn-sm btn-outline-danger" href="' + U_('/waste/report?product_id=' + pr.id) + '"><i class="fa-solid fa-file-circle-plus mr-1"></i>Registrar merma</a>' : '');
		var lc = a.last_count ? 'Último conteo ' + esc(a.last_count.executed_date || '') + ': sistema ' + num(a.last_count.theoretical_stock) + ' / físico ' + num(a.last_count.counted_stock) : 'Sin conteos registrados';
		var prodImg = pr.picture_file_name ? '<img src="' + U_('/api/files/productpictures/' + btoa(pr.picture_file_name)) + '" class="daily-result-prod-img" alt="' + esc(pr.name) + '">' : '<div class="daily-result-prod-img"><i class="fa-solid fa-box"></i></div>';
		var skuBadge = pr.sku ? '<span class="badge badge-light border mr-1">SKU: ' + esc(pr.sku) + '</span>' : '';
		var brandInfo = pr.brand ? ' · ' + esc(pr.brand) : '';
		var unitInfo = pr.unit ? ' · Unidad: ' + esc(pr.unit) : '';

		return '<div class="daily-result-card">' +
			'<div class="daily-result-header">' +
				'<div class="daily-result-prod-box">' + prodImg +
					'<div><div class="daily-result-prod-name">' + esc(pr.name) + '</div>' +
					'<div class="daily-result-prod-meta">' + skuBadge + esc(pr.group_name || 'Sin grupo') + brandInfo + unitInfo + '</div></div>' +
				'</div>' +
				'<div>' + actions + '</div>' +
			'</div>' + gs1 +
			'<div class="daily-stock-tiles-grid">' +
				'<div class="daily-stock-tile"><div class="daily-stock-tile-label">Físico</div><div class="daily-stock-tile-val">' + num(a.physical) + '</div></div>' +
				'<div class="daily-stock-tile is-available"><div class="daily-stock-tile-label">Disponible</div><div class="daily-stock-tile-val">' + num(a.available) + '</div></div>' +
				'<div class="daily-stock-tile ' + (a.quarantine > 0 ? 'is-warning' : '') + '"><div class="daily-stock-tile-label">Cuarentena</div><div class="daily-stock-tile-val">' + num(a.quarantine) + '</div></div>' +
				'<div class="daily-stock-tile ' + (a.expired > 0 ? 'is-danger' : '') + '"><div class="daily-stock-tile-label">Vencido</div><div class="daily-stock-tile-val">' + num(a.expired) + '</div></div>' +
				'<div class="daily-stock-tile ' + (a.committed_waste > 0 ? 'is-warning' : '') + '"><div class="daily-stock-tile-label">Merma pend.</div><div class="daily-stock-tile-val">' + num(a.committed_waste) + '</div></div>' +
				'<div class="daily-stock-tile"><div class="daily-stock-tile-label">Reservado</div><div class="daily-stock-tile-val text-muted">' + (a.reserved != null ? num(a.reserved) : '—') + '</div></div>' +
			'</div>' +
			'<div class="small text-muted mb-3"><i class="fa-solid fa-clock-rotate-left mr-1"></i>' + lc + '</div>' +
			(r.entries.length ? '<div class="table-responsive"><table class="table table-sm mb-0 table-hover"><thead class="thead-light"><tr><th>Ubicación</th><th>Lote / serie</th><th>Vence</th><th class="text-right">Cant.</th></tr></thead><tbody>' +
				r.entries.map(function (e) {
					var exp = e.best_before_date === '2999-12-31' ? '—' : e.best_before_date, late = exp !== '—' && exp < today;
					return '<tr class="' + (e.matches_lot ? 'table-info font-weight-600' : '') + '"><td>' + esc(e.location_name) + ' <span class="small text-muted">' + esc(e.label_code || '') + '</span></td><td><a href="' + U_('/traceability?q=' + encodeURIComponent(e.stock_id)) + '" title="Ver trazabilidad" class="text-primary font-weight-600">' + esc(e.lot_number || e.serial_number || 'entrada ' + String(e.stock_id).slice(-5)) + '</a></td><td class="' + (late ? 'text-danger font-weight-700' : '') + '">' + esc(exp) + '</td><td class="text-right font-weight-700">' + num(e.amount) + '</td></tr>';
				}).join('') + '</tbody></table><div class="small text-muted mt-2"><i class="fa-solid fa-arrow-down-short-wide mr-1"></i>Orden FEFO: consuma primero las filas superiores para evitar mermas por vencimiento.</div></div>' : '<div class="p-3 bg-light rounded text-muted small"><i class="fa-solid fa-circle-exclamation mr-1 text-warning"></i>Sin stock registrado en esta sucursal.</div>') +
			'</div>';
	}
	function U_(path) { return Grocy.BaseUrl + path; }
	$('#sc-result').on('click', '[data-lookup]', function (e) {
		e.preventDefault();
		lookup($(this).data('lookup'));
	});

	/* ---------- sesión ---------- */
	function addLine(evt) {
		var body = {
			client_uuid: evt.client_uuid,
			raw_code: evt.code,
			device_id: evt.device_id,
			scanned_at: evt.scanned_at
		};
		var amt = $('#sc-amount').val(), lot = $('#sc-lot').val().trim(), bbd = $('#sc-bbd').val();
		if (amt) { body.amount = amt; }
		if (lot) { body.lot_number = lot; }
		if (bbd) { body.best_before_date = bbd; }
		var path = '/scan/sessions/' + session.id + '/lines';
		var sender = window.DairyOffline ? DairyOffline.send(path, body, { label: 'Lectura ' + evt.code + ' (' + session.folio + ')' }) : new Promise(function (ok, ko) {
			Grocy.Api.Post(path.slice(1), body, function (d) { ok({ data: d }); }, function (x) { var er = new Error(U.errMsg(x)); ko(er); });
		});
		sender.then(function (r) {
			if (r.queued) {
				DairyScan.feedback(true);
				msg('Sin conexión: lectura en cola (' + evt.code + ')', 'warning');
				return;
			}
			var d = r.data;
			DairyScan.feedback(!d.ignored);
			msg(d.ignored ? d.message : (d.duplicate ? d.message : '✓ ' + (d.line ? (findName(d, d.line.product_id) + ' +' + num(d.line.amount)) : 'Registrado')), d.ignored ? 'warning' : 'success');
			$('#sc-amount').val('');
			(d.warnings || []).forEach(function (w) { toastr.warning(w, '', { timeOut: 8000 }); });
			render(d);
		}, function (err) {
			DairyScan.feedback(false);
			msg(err.message, 'danger');
		});
	}

	function findName(d, pid) {
		var t = (d.totals || []).filter(function (x) { return String(x.product_id) === String(pid); })[0];
		return t ? t.product_name : 'Producto';
	}

	function render(d) {
		session = d.session;
		session.id = parseInt(session.id, 10);
		var open = session.status === 'open';
		if (session.type !== mode) {
			mode = session.type;
			$('#sc-modes a, #sc-modes .daily-op-card').removeClass('active is-active').filter('[data-mode="' + mode + '"]').addClass('active is-active');
			$('#sc-workstation-title').text(TITLES[mode] || 'Operaciones con escáner');
			$('#sc-submit-label').text(SUBMITS[mode] || 'Consultar');
		}
		$('#sc-setup').toggleClass('d-none', open);
		$('#sc-session-extra').toggleClass('d-none', !open);
		$('#sc-session').removeClass('d-none');
		$('#sc-result').empty();
		$('#step-3').addClass('is-active');
		updateHeroVisibility();

		var where = session.type === 'pick' ? esc(session.customer_order_code || '') + (session.location_name ? ' · ' + esc(session.location_name) : '') : session.type === 'move' ? esc(session.location_name) + ' → ' + esc(session.target_name) : session.type === 'receive' ? esc(session.order_number) + ' → ' + esc(session.location_name || '') : esc(session.location_name);
		$('#sc-session-title').html('<i class="fa-solid fa-barcode mr-1 text-primary"></i>' + esc(session.folio) + ' · ' + esc(session.type_label) + ' · ' + where + ' <span class="badge badge-' + (open ? 'warning' : session.status === 'closed' ? 'success' : 'secondary') + ' ml-1">' + (open ? 'Abierta' : session.status === 'closed' ? 'Cerrada' : 'Anulada') + '</span>');
		$('#sc-close, #sc-cancel').toggleClass('d-none', !open);
		var result = d.result ? '<div class="alert alert-success mb-2">' + esc(d.result.message || 'Contabilizado') + '</div>' + resultLinks(d) : '';
		$('#sc-session-help').html(result + (open ? esc(HELP[session.type] || '') + (session.blind ? ' <b>Conteo ciego</b>: el stock del sistema se muestra al cerrar.' : '') : ''));
		var expected = {};
		(d.expected || []).forEach(function (e) { expected[e.product_id] = e; });
		var rows = {};
		(d.totals || []).forEach(function (t) { rows[t.product_id] = { name: t.product_name, amount: t.amount, scans: t.scans }; });
		Object.keys(expected).forEach(function (k) { rows[k] = rows[k] || { name: expected[k].product_name, amount: 0, scans: 0 }; });
		$('#sc-exp-head').text(session.type === 'receive' ? 'Pendiente OC' : session.type === 'pick' ? 'Pendiente pedido' : session.type === 'move' ? 'En origen' : 'Sistema');
		$('#sc-totals tbody').html(Object.keys(rows).map(function (k) {
			var e = expected[k], exp = !e ? '—' : (session.type === 'receive' || session.type === 'pick') ? num(e.pending) : (e.system_amount == null ? '<span class="text-muted">oculto</span>' : num(e.system_amount));
			var diff = e && e.system_amount != null && session.type === 'count' && !open ? Number(rows[k].amount) - Number(e.system_amount) : null;
			var sug = e && e.suggested && e.suggested.length && open ? '<div class="small text-muted">FEFO: ' + e.suggested.slice(0, 2).map(function (x) { return esc(x.lot_number || 'sin lote') + ' · ' + esc(x.location_name) + ' · vence ' + esc(x.best_before_date === '2999-12-31' ? '—' : x.best_before_date); }).join(' | ') + '</div>' : '';
			return '<tr><td>' + esc(rows[k].name) + sug + '</td><td class="text-right font-weight-700">' + num(rows[k].amount) + '</td><td class="text-right">' + exp + (diff ? ' <span class="badge badge-' + (diff < 0 ? 'danger' : 'warning') + '">' + (diff > 0 ? '+' : '') + num(diff) + '</span>' : '') + '</td><td class="text-right">' + rows[k].scans + '</td></tr>';
		}).join('') || '<tr><td colspan="4" class="text-muted small">Sin lecturas todavía</td></tr>');
		var lines = d.lines || [];
		$('#sc-lines-count').text(lines.filter(function (l) { return !l.voided_at; }).length);
		$('#sc-lines tbody').html(lines.map(function (l) {
			var v = !!l.voided_at;
			return '<tr class="' + (v ? 'text-muted dairy-strike' : '') + '"><td>' + esc((l.scanned_at || '').slice(11, 19)) + '</td><td>' + esc(l.product_name) + '</td><td class="text-right">' + num(l.amount) + '</td><td>' + esc(l.lot_number || l.serial_number || '—') + '</td><td>' + esc(l.best_before_date || '—') + '</td><td class="small">' + esc(l.device_id || '') + '</td><td>' +
				(open && !v ? '<button type="button" class="btn btn-xs btn-outline-danger sc-void" data-id="' + l.id + '" aria-label="Anular lectura"><i class="fa-solid fa-xmark"></i></button>' : (v ? 'anulada' : '')) + '</td></tr>';
		}).join(''));
		if (open) { $('#sc-input').trigger('focus'); }
	}

	function resultLinks(d) {
		var r = d.result || {};
		if (r.receipts) { return r.receipts.map(function (x) { return '<a class="btn btn-sm btn-outline-secondary mr-1 mb-2" target="_blank" href="' + U_('/document/receipt/' + x.id) + '"><i class="fa-solid fa-print mr-1"></i>' + esc(x.receipt_code) + '</a>'; }).join(''); }
		if (r.order_id) { return '<a class="btn btn-sm btn-outline-secondary mb-2" href="' + U_('/orders?id=' + r.order_id) + '"><i class="fa-solid fa-box mr-1"></i>Empacar y despachar</a>'; }
		if (r.counts && r.differences) { return '<a class="btn btn-sm btn-outline-secondary mb-2" href="' + U_('/waste#tab-conteos') + '"><i class="fa-solid fa-scale-balanced mr-1"></i>Aprobar ajustes en Conteos</a>'; }
		return '';
	}

	$('#sc-lines').on('click', '.sc-void', function () {
		var id = $(this).data('id');
		U.post('scan/lines/' + id + '/void', {}, function (r) { render(r); toastr.info('Lectura anulada'); }, this);
	});

	$('#sc-close').on('click', function () {
		var extra = session.type === 'count' ? '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="sc-missing" name="include_missing" value="1"><label class="custom-control-label" for="sc-missing">Contar como 0 los productos con stock en la ubicación que no se escanearon</label></div>'
			: session.type === 'receive' ? '<div class="form-group"><label for="sc-temp">Temperatura al llegar (°C)</label><input type="number" step="0.1" class="form-control" id="sc-temp" name="temperature_on_arrival"></div><div class="form-group"><label for="sc-doc">Guía / factura del proveedor</label><input type="text" class="form-control" id="sc-doc" name="supplier_document"></div>' : '';
		U.formDialog('Cerrar ' + session.folio, '<p>Se contabilizará la sesión. Esta acción no se puede deshacer desde aquí.</p>' + extra, function (data, dlg) {
			U.post('scan/sessions/' + session.id + '/close', data, function (r) { dlg.modal('hide'); render(r); toastr.success((r.result && r.result.message) || 'Sesión cerrada'); }, dlg.find('.btn-primary'));
		}, 'Cerrar y contabilizar');
	});

	$('#sc-cancel').on('click', function () {
		U.formDialog('Anular ' + session.folio, '<div class="form-group"><label for="sc-reason">Motivo</label><input type="text" class="form-control" id="sc-reason" name="reason" required></div><p class="small text-muted mb-0">No se contabiliza ninguna lectura.</p>', function (data, dlg) {
			U.post('scan/sessions/' + session.id + '/cancel', data, function (r) { dlg.modal('hide'); render(r); session = null; toastr.info('Sesión anulada'); updateHeroVisibility(); }, dlg.find('.btn-primary'));
		}, 'Anular');
	});

	$(document).on('click', '.sc-open', function (e) {
		e.preventDefault();
		openSession($(this).data('id'));
	});

	function openSession(id) {
		Grocy.Api.Get('scan/sessions/' + id, function (r) { render(r); }, function (xhr) { toastr.error(U.errMsg(xhr)); });
	}

	/* ---------- modal de configuración avanzada ---------- */
	$('#sc-toggle-adv-config').on('click', function (e) {
		e.preventDefault();
		$('#sc-cfg-device-val').text(DairyScan.deviceId());
		$('#modal-scanner-config').modal('show');
	});

	$('#sc-test-beep').on('click', function () {
		DairyScan.feedback(true);
	});

	$('#sc-reset-device-id').on('click', function () {
		try {
			localStorage.removeItem('dairy_device_id');
			var newId = DairyScan.deviceId();
			$('#sc-cfg-device-val').text(newId);
			$('#sc-device span').text(newId);
			toastr.info('Identificador regenerado: ' + newId);
		} catch (err) {
			toastr.warning('No se pudo regenerar el identificador');
		}
	});

	/* ---------- modal de sincronización ---------- */
	$('#sc-view-sync-status, #sc-offline-strip, #sc-view-compat-info').on('click', function (e) {
		e.preventDefault();
		$('#modal-sync-details').modal('show');
	});

	$('#sc-check-conn-btn').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Comprobando…');
		Grocy.Api.Get('system/info', function () {
			$btn.prop('disabled', false).html('<i class="fa-solid fa-check mr-1 text-success"></i> Conexión verificada');
			$('#sc-network-status').html('<i class="fa-solid fa-circle text-success" style="font-size: 8px;"></i> <span>En línea</span>');
			toastr.success('Conexión con el servidor confirmada');
		}, function () {
			$btn.prop('disabled', false).html('<i class="fa-solid fa-xmark mr-1 text-danger"></i> Sin respuesta');
			$('#sc-network-status').html('<i class="fa-solid fa-circle text-danger" style="font-size: 8px;"></i> <span>Desconectado</span>');
			toastr.error('No se pudo contactar al servidor');
		});
	});

	setMode(P.pickId ? 'pick' : 'lookup');
	if (P.openId) {
		openSession(P.openId);
	} else if (P.initialCode) {
		lookup(P.initialCode);
	}
})(jQuery);
