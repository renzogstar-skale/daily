/*
 * DAILY — Punto de venta (views/pos.blade.php + services/PosService.php).
 * El cálculo del ticket replica PosService::Quote (precio con rebaja vigente, descuento prorrateado, impuesto por
 * departamento); el servidor lo recalcula y rechaza un total distinto. Cada ticket tiene un client_ref (UUID) fijo hasta
 * cobrarse: reintentos y la cola sin conexión (DairyOffline) nunca cobran dos veces.
 */
(function ($) {
	'use strict';
	var B = JSON.parse(document.getElementById('pos-boot').textContent);
	var S = B.settings;
	var cart = [], ref = null, method = 'cash', discountAuth = null, shift = B.shift, held = B.held || [];

	function r2(v) { return Math.round((Number(v) + 1e-7) * 100) / 100; }
	function money(v) { v = Number(v || 0); return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function esc(s) { return DairyUI.esc(s); }
	function uuid() { return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'pos-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14); }
	function icons() { if (window.lucide) lucide.createIcons(); }
	function errMsg(x) { return x && x.responseText ? DairyUI.errMsg(x) : (x && x.message) || 'Error'; }
	function api(m, url, body) {
		return new Promise(function (ok, ko) {
			if (m === 'GET') Grocy.Api.Get(url, ok, ko); else Grocy.Api.Post(url, body || {}, ok, ko);
		});
	}

	/* ------------------------------------------------------------ catálogo y escaneo */
	function filter() {
		var q = $.trim($('#pos-search-input').val()).toLowerCase(), cat = $('.pos-cat-pill.active').data('category') || 'ALL', n = 0;
		$('.pos-product-card').each(function () {
			var $c = $(this), ok = (cat === 'ALL' || String($c.data('category')) === cat) && (!q || String($c.data('search')).indexOf(q) >= 0);
			$c.toggle(ok); if (ok) n++;
		});
		$('#pos-catalog-empty').toggleClass('d-none', n > 0);
		$('#pos-search-clear').toggle(!!q);
	}
	function cardById(id) { return $('.pos-product-card[data-id="' + id + '"]'); }
	function scan(code) {
		code = $.trim(code);
		if (!code) return;
		var lc = code.toLowerCase(), $m = null;
		$('.pos-product-card').each(function () {
			var $c = $(this);
			if (String($c.data('sku')).toLowerCase() === lc || (' ' + String($c.data('barcodes')).toLowerCase() + ' ').indexOf(' ' + lc + ' ') >= 0) { $m = $c; return false; }
		});
		if ($m) { add($m); $('#pos-search-input').val(''); filter(); return; }
		api('GET', 'pos/lookup?code=' + encodeURIComponent(code)).then(function (r) {
			var $c = cardById(r.product_id);
			if ($c.length) { add($c); $('#pos-search-input').val(''); filter(); } else toastr.warning('El producto no está activo en el catálogo de venta');
		}, function (x) { toastr.warning(errMsg(x)); });
	}
	$('#pos-search-input').on('input', filter).on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); scan($(this).val()); } });
	$('#pos-search-clear, #btn-reset-catalog-filters').on('click', function () { $('#pos-search-input').val(''); $('.pos-cat-pill').removeClass('active').first().addClass('active'); filter(); });
	$('#btn-focus-scanner').on('click', function () { $('#pos-search-input').focus().select(); });
	$(document).on('click', '.pos-cat-pill', function () { $('.pos-cat-pill').removeClass('active'); $(this).addClass('active'); filter(); });
	$(document).on('click', '.pos-btn-add', function (e) { e.stopPropagation(); add($(this).closest('.pos-product-card')); });

	/* ------------------------------------------------------------ carrito */
	function add($c, qty) {
		qty = qty || 1;
		var id = parseInt($c.data('id'), 10), stock = parseFloat($c.data('stock')) || 0, line = cart.find(function (l) { return l.product_id === id; });
		if ((line ? line.amount : 0) + qty > stock + 1e-6) { toastr.warning('Sin existencias suficientes de ' + $c.data('name') + ' en la sucursal'); return; }
		if (!ref) ref = uuid();
		if (line) line.amount += qty;
		else cart.push({ product_id: id, name: String($c.data('name')), sku: String($c.data('sku')), price: parseFloat($c.data('price')), list: parseFloat($c.data('list-price')),
			tax_rate: parseFloat($c.data('tax-rate')) || 0, amount: qty, stock: stock });
		render();
	}
	function render() {
		var $l = $('#pos-cart-items').empty();
		if (!cart.length) {
			$l.html('<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg"><i data-lucide="shopping-bag" style="width:36px;height:36px;margin:0 auto 8px;opacity:0.4;display:block;"></i>'
				+ '<div class="font-weight-bold text-dark" style="font-size:12px;">El ticket está vacío</div><div class="small mt-1">Escanee un código o pulse <strong>Agregar</strong> en un producto.</div></div>');
		}
		cart.forEach(function (l, i) {
			$l.append('<div class="pos-ticket-item-row" data-index="' + i + '"><div><div class="pos-item-title" title="' + esc(l.name) + '">' + esc(l.name) + '</div><div class="pos-item-sub">' + esc(l.sku)
				+ (l.price < l.list ? ' · rebaja' : '') + (l.tax_rate ? ' · imp. ' + l.tax_rate + ' %' : '') + '</div></div>'
				+ '<div class="text-right font-weight-bold text-dark">' + money(l.price) + '</div>'
				+ '<div class="text-center"><div class="pos-qty-ctrl"><button type="button" class="pos-qty-btn" data-act="dec" data-index="' + i + '" aria-label="Restar">-</button><span class="pos-qty-val">' + l.amount
				+ '</span><button type="button" class="pos-qty-btn" data-act="inc" data-index="' + i + '" aria-label="Sumar">+</button></div></div>'
				+ '<div class="text-right font-weight-bold text-dark">' + money(r2(l.price * l.amount)) + '</div>'
				+ '<div class="text-right"><button type="button" class="pos-item-del-btn" data-act="del" data-index="' + i + '" title="Quitar"><i data-lucide="trash-2" style="width:12px;height:12px;"></i></button></div></div>');
		});
		$('#pos-ticket-title').text(cart.length ? 'Ticket ' + ref.slice(0, 8).toUpperCase() : 'Ticket nuevo');
		icons();
		totals();
	}
	$('#pos-cart-items').on('click', '[data-act]', function () {
		var i = parseInt($(this).data('index'), 10), l = cart[i], act = $(this).data('act');
		if (!l) return;
		if (act === 'inc') { if (l.amount + 1 > l.stock + 1e-6) { toastr.warning('Sin existencias suficientes'); return; } l.amount++; }
		else if (act === 'dec') { l.amount--; if (l.amount <= 0) cart.splice(i, 1); }
		else cart.splice(i, 1);
		render();
	});
	$('#btn-clear-cart').on('click', function () {
		if (!cart.length) return;
		bootbox.confirm('¿Vaciar el ticket actual?', function (y) { if (y) reset(); });
	});
	function reset() {
		cart = []; ref = null; discountAuth = null; credit = null; $('#pos-credit-code').val(''); $('#pos-credit-info').text('El saldo que no cubra la nota se cobra en efectivo.');
		$('#pos-discount-input').val(0); $('#pos-discount-reason').val(''); $('#pos-cash-received, #pos-payment-ref, #pos-mixed-cash, #pos-mixed-card, #pos-mixed-transfer').val('');
		render();
	}

	/* ------------------------------------------------------------ totales (igual que PosService::Quote) */
	function quote() {
		var sub = 0;
		cart.forEach(function (l) { l.sub = r2(l.price * l.amount); sub += l.sub; });
		sub = r2(sub);
		var type = $('#pos-discount-type').val(), val = Math.max(0, parseFloat($('#pos-discount-input').val()) || 0);
		var disc = type === 'pct' ? r2(sub * Math.min(val, 100) / 100) : r2(Math.min(val, sub));
		var pct = sub > 0 ? Math.round(disc / sub * 10000) / 100 : 0, left = disc, tax = 0;
		cart.forEach(function (l, i) {
			var share = i === cart.length - 1 ? left : r2(sub > 0 ? disc * l.sub / sub : 0);
			left = r2(left - share);
			tax += r2((l.sub - share) * l.tax_rate / 100);
		});
		tax = r2(tax);
		return { subtotal: sub, discount: disc, pct: pct, type: type, value: val, tax: tax, total: r2(sub - disc + tax), items: cart.reduce(function (a, l) { return a + l.amount; }, 0) };
	}
	function totals() {
		var q = quote();
		$('#pos-item-count-badge').text(q.items + (q.items === 1 ? ' ítem' : ' ítems'));
		$('#pos-subtotal-label').text('Subtotal (' + q.items + ' ítems)');
		$('#pos-subtotal-display').text(money(q.subtotal));
		$('#pos-discount-display').text('-' + money(q.discount));
		$('#pos-tax-display').text(money(q.tax));
		$('#pos-total-display').text(money(q.total));
		$('#pos-discount-reason-row').toggleClass('d-none', q.discount <= 0);
		var authOk = discountAuth && discountAuth.pct + 0.001 >= q.pct;
		$('#pos-discount-auth').toggleClass('d-none', !authOk).find('span').text(authOk ? 'Autorizado ' + discountAuth.pct + ' %' : '');
		$('#btn-submit-sale-label').text('Cobrar ' + money(q.total));
		$('#btn-submit-sale').prop('disabled', !cart.length || !shift);
		var recv = parseFloat($('#pos-cash-received').val()) || 0;
		$('#pos-change-display').text(money(Math.max(0, recv - q.total)));
		var covered = ['cash', 'card', 'transfer'].reduce(function (a, k) { return a + (parseFloat($('#pos-mixed-' + k).val()) || 0); }, 0);
		var rem = r2(q.total - covered);
		$('#pos-mixed-status-badge').text(Math.abs(rem) < 0.005 ? 'Cubierto' : (rem > 0 ? 'Pendiente: ' + money(rem) : 'Excede: ' + money(-rem)));
		return q;
	}
	$('#pos-discount-input, #pos-discount-type').on('input change', function () { totals(); });
	$('#pos-cash-received, #pos-mixed-cash, #pos-mixed-card, #pos-mixed-transfer').on('input', totals);
	$('.pos-cash-chip').on('click', function () {
		var v = $(this).data('quick-cash'), t = quote().total;
		$('#pos-cash-received').val(v === 'exact' ? t.toFixed(2) : parseFloat(v).toFixed(2));
		totals();
	});
	$('.pos-pay-tab').on('click', function () {
		$('.pos-pay-tab').removeClass('active'); $(this).addClass('active'); method = $(this).data('method');
		$('#pos-panel-cash').toggleClass('d-none', method !== 'cash');
		$('#pos-panel-noncash').toggleClass('d-none', method !== 'card' && method !== 'transfer' && method !== 'mixed');
		$('#pos-panel-mixed').toggleClass('d-none', method !== 'mixed');
		$('#pos-panel-credit').toggleClass('d-none', method !== 'credit_note');
		$('#pos-panel-points').toggleClass('d-none', method !== 'points');
		if (method === 'points') refreshCustomerLoyalty();
	});

	/* ------------------------------------------------------------ fidelización (puntos en POS) */
	var customerLoyalty = null;
	function refreshCustomerLoyalty() {
		var cid = parseInt($('#pos-customer-select').val(), 10) || 1;
		var isAnon = parseInt($('#pos-customer-select option:selected').data('anon'), 10) === 1 || cid <= 1;
		if (isAnon) {
			customerLoyalty = null;
			$('#pos-customer-loyalty-badge').addClass('d-none').text('');
			$('#pos-points-info').text('Seleccione un cliente registrado (no Público General) para canjear puntos.');
			return Promise.resolve(null);
		}
		return api('GET', 'pos/customers/' + cid + '/loyalty').then(function (r) {
			customerLoyalty = r;
			if (!r.enabled) {
				$('#pos-customer-loyalty-badge').addClass('d-none').text('');
				$('#pos-points-info').text('Programa de puntos desactivado en CRM.');
				return r;
			}
			$('#pos-customer-loyalty-badge').removeClass('d-none').text('Puntos disponibles: ' + r.balance + ' pts (' + money(r.balance_value) + ')');
			$('#pos-points-info').text('Saldo: ' + r.balance + ' pts = ' + money(r.balance_value) + '. El resto del ticket se cobra en efectivo.');
			return r;
		}, function () {
			customerLoyalty = null;
			$('#pos-points-info').text('No se pudo consultar el saldo de puntos.');
		});
	}
	$('#pos-customer-select').on('change', refreshCustomerLoyalty);
	$('#btn-reset-customer').on('click', function () { setTimeout(refreshCustomerLoyalty, 20); });
	$('#pos-points-refresh').on('click', refreshCustomerLoyalty);
	$('#pos-points-max').on('click', function () {
		if (!customerLoyalty || !customerLoyalty.enabled) { toastr.info('Consulte primero un cliente con puntos activos'); return; }
		var t = quote().total, maxAmt = r2(Math.min(t, parseFloat(customerLoyalty.balance_value) || 0));
		$('#pos-points-amount').val(maxAmt.toFixed(2));
	});
	refreshCustomerLoyalty();

	/* ------------------------------------------------------------ nota de crédito */
	var credit = null;
	$('#pos-credit-check').on('click', function () {
		var code = $.trim($('#pos-credit-code').val());
		if (!code) return;
		api('GET', 'pos/credit-notes/' + encodeURIComponent(code)).then(function (n) {
			credit = n.usable ? { code: n.code, balance: parseFloat(n.balance) } : null;
			$('#pos-credit-info').text(n.usable ? 'Saldo ' + money(n.balance) + ' · ' + (n.customer || 'Público general') + '. El resto se cobra en efectivo.' : 'Nota no utilizable (' + (n.status === 'used' ? 'sin saldo' : 'vencida o anulada') + ')');
		}, function (x) { credit = null; $('#pos-credit-info').text(errMsg(x)); });
	});

	/* ------------------------------------------------------------ autorización de supervisor */
	function authorize(kind, ctx, desc, reason) {
		return new Promise(function (ok, ko) {
			var $m = $('#modal-pos-auth'), done = false;
			$('#pos-auth-desc').text(desc); $('#auth-reason').val(reason || ''); $('#auth-user, #auth-pass').val('');
			$('#form-pos-auth').off('submit').on('submit', function (e) {
				e.preventDefault();
				var body = $.extend({ kind: kind, branch_id: B.branchId, shift_id: shift ? shift.id : null, username: $('#auth-user').val(), password: $('#auth-pass').val(), reason: $('#auth-reason').val() }, ctx);
				api('POST', 'pos/authorize', body).then(function (a) { done = true; $('#auth-pass').val(''); $m.modal('hide'); ok(a); }, function (x) { toastr.error(errMsg(x)); });
			});
			$m.off('hidden.bs.modal').on('hidden.bs.modal', function () { $('#auth-pass').val(''); if (!done) { if ($rc.length) $rc.modal('show'); ko(new Error('Autorización cancelada')); } });
			$m.one('shown.bs.modal', function () { $(reason ? '#auth-user' : '#auth-reason').focus(); });
			// Un solo diálogo a la vez: el recibo se oculta mientras el supervisor autoriza y vuelve si se cancela
			var $rc = $('#modal-pos-receipt.show');
			if ($rc.length) { $rc.one('hidden.bs.modal', function () { $m.modal('show'); }).modal('hide'); } else $m.modal('show');
		});
	}

	/* ------------------------------------------------------------ cobro */
	function payments(q) {
		var refTxt = $.trim($('#pos-payment-ref').val());
		if (method === 'cash') {
			var recv = parseFloat($('#pos-cash-received').val());
			if (isNaN(recv) || recv === 0) recv = q.total;
			if (recv + 0.001 < q.total) throw new Error('El efectivo recibido es menor que el total');
			return [{ method: 'cash', amount: q.total, tendered: r2(recv) }];
		}
		if (method === 'card' || method === 'transfer') return [{ method: method, amount: q.total, reference: refTxt }];
		if (method === 'credit_note') {
			if (!credit || credit.code !== $.trim($('#pos-credit-code').val())) throw new Error('Consulte la nota de crédito antes de cobrar');
			var use = r2(Math.min(credit.balance, q.total)), out2 = [{ method: 'credit_note', amount: use, reference: credit.code }];
			if (q.total - use > 0.004) out2.push({ method: 'cash', amount: r2(q.total - use), tendered: r2(q.total - use) });
			return out2;
		}
		if (method === 'points') {
			var cid = parseInt($('#pos-customer-select').val(), 10) || 1;
			if (!customerLoyalty || customerLoyalty.anonymous || cid <= 1) throw new Error('Seleccione un cliente registrado para canjear puntos');
			if (!customerLoyalty.enabled) throw new Error('El programa de puntos no está activo');
			var reqAmt = r2(parseFloat($('#pos-points-amount').val()) || 0);
			if (reqAmt <= 0) reqAmt = r2(Math.min(parseFloat(customerLoyalty.balance_value) || 0, q.total));
			if (reqAmt <= 0 || reqAmt > q.total + 0.004) throw new Error('Indique un monto en puntos válido entre 0 y ' + money(q.total));
			if (reqAmt > parseFloat(customerLoyalty.balance_value) + 0.004) throw new Error('Saldo insuficiente (' + money(customerLoyalty.balance_value) + ' disponibles)');
			var outP = [{ method: 'points', amount: reqAmt }];
			if (q.total - reqAmt > 0.004) outP.push({ method: 'cash', amount: r2(q.total - reqAmt), tendered: r2(q.total - reqAmt) });
			return outP;
		}
		var out = [];
		['cash', 'card', 'transfer'].forEach(function (k) {
			var v = r2(parseFloat($('#pos-mixed-' + k).val()) || 0);
			if (v > 0) out.push(k === 'cash' ? { method: k, amount: v, tendered: v } : { method: k, amount: v, reference: refTxt });
		});
		var sum = r2(out.reduce(function (a, p) { return a + p.amount; }, 0));
		if (Math.abs(sum - q.total) > 0.005) throw new Error('Los importes del pago mixto (' + money(sum) + ') deben sumar exactamente ' + money(q.total));
		return out;
	}
	$('#btn-submit-sale').on('click', function () {
		var q = totals(), $b = $(this), pays;
		if (!cart.length || !shift) return;
		if (q.discount > 0 && $.trim($('#pos-discount-reason').val()).length < 3) { toastr.warning('Indique el motivo del descuento'); $('#pos-discount-reason').focus(); return; }
		try { pays = payments(q); } catch (e) { toastr.warning(e.message); return; }
		if (navigator.onLine === false && pays.some(function (p) { return p.method !== 'cash' && !p.reference; })) { toastr.warning('Sin conexión, la tarjeta o transferencia requiere el código de autorización'); return; }
		var needAuth = q.discount > 0 && q.pct > S.cashier_max_discount_pct + 0.001 && !(discountAuth && discountAuth.pct + 0.001 >= q.pct);
		var step = needAuth ? authorize('discount', { pct: q.pct, amount: q.discount }, 'Descuento de ' + q.pct + ' % (' + money(q.discount) + ') sobre el límite del cajero (' + S.cashier_max_discount_pct + ' %).', $('#pos-discount-reason').val())
			.then(function (a) { discountAuth = { id: a.id, pct: a.pct }; totals(); }) : Promise.resolve();
		step.then(function () {
			var body = { client_ref: ref, branch_id: B.branchId, register: B.register, shift_id: shift.id, customer_id: parseInt($('#pos-customer-select').val(), 10) || 1,
				items: cart.map(function (l) { return { product_id: l.product_id, amount: l.amount }; }), expected_total: q.total, payments: pays,
				discount: q.discount > 0 ? { type: q.type, value: q.value, reason: $('#pos-discount-reason').val(), auth_id: discountAuth ? discountAuth.id : null } : {},
				offline_created_at: new Date().toISOString() };
			$b.prop('disabled', true);
			return DairyOffline.send('/pos/checkout', body, { key: ref, label: 'Venta ' + money(q.total) + ' · ' + B.register }).then(function (r) {
				var change = pays.reduce(function (a, p) { return a + (p.tendered ? p.tendered - p.amount : 0); }, 0);
				if (r.queued) toastr.warning('Sin conexión: la venta quedó guardada y se registrará al volver la red (no la cobre de nuevo).');
				else { toastr.success('Venta ' + r.data.sale.number + ' registrada' + (r.data.replayed ? ' (ya estaba registrada: no se cobró dos veces)' : '') + (change > 0 ? ' · Cambio ' + money(change) : '')); receipt(r.data, false); refreshShift(); }
				reset();
			});
		}).catch(function (e) { if (e && e.message !== 'Autorización cancelada') toastr.error(e.message || 'No se pudo registrar la venta'); }).then(function () { totals(); });
	});

	/* ------------------------------------------------------------ suspender y recuperar */
	$('#btn-hold-sale').on('click', function () {
		if (!cart.length) return;
		var q = quote();
		DairyUI.formDialog('Suspender ticket', '<div class="form-group mb-0"><label>Referencia</label><input type="text" class="form-control" name="label" maxlength="60" placeholder="Ej. cliente busca billetera"></div>', function (d, dlg) {
			api('POST', 'pos/hold', { branch_id: B.branchId, customer_id: parseInt($('#pos-customer-select').val(), 10) || 1, label: d.label, discount_type: q.type, discount_value: q.value,
				discount_reason: $('#pos-discount-reason').val(), tax_rate: 0, items: cart.map(function (l) { return { product_id: l.product_id, amount: l.amount, unit_price: l.price }; }) })
				.then(function (h) { dlg.modal('hide'); held.unshift({ id: parseInt(h.held_sale.id, 10), label: h.held_sale.label, total: q.total, items: q.items }); $('#pos-held-count').text(held.length); toastr.success('Ticket suspendido'); reset(); },
					function (x) { toastr.error(errMsg(x)); });
		}, 'Suspender');
	});
	$('#btn-recall-sale').on('click', function () {
		if (!held.length) { toastr.info('No hay tickets suspendidos en esta sucursal'); return; }
		if (cart.length) { toastr.warning('Cobre o suspenda el ticket actual antes de recuperar otro'); return; }
		var html = '<div class="list-group">' + held.map(function (h) { return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-hold="' + h.id + '"><span>' + esc(h.label) + '</span><strong>' + money(h.total) + '</strong></button>'; }).join('') + '</div>';
		var dlg = bootbox.dialog({ title: 'Tickets suspendidos', message: html, onEscape: true });
		dlg.on('click', '[data-hold]', function () {
			var id = $(this).data('hold');
			api('POST', 'pos/hold/' + id + '/recall', { branch_id: B.branchId }).then(function (r) {
				dlg.modal('hide');
				held = held.filter(function (h) { return h.id !== id; }); $('#pos-held-count').text(held.length);
				var missing = 0;
				r.items.forEach(function (it) { var $c = cardById(it.product_id); if ($c.length) add($c, parseFloat(it.amount)); else missing++; });
				if (r.discount && r.discount.value > 0) { $('#pos-discount-type').val(r.discount.type); $('#pos-discount-input').val(r.discount.value); $('#pos-discount-reason').val(r.discount.reason || ''); }
				$('#pos-customer-select').val(r.customer_id);
				totals();
				toastr.success('Ticket recuperado' + (missing ? ' · ' + missing + ' producto(s) ya no disponibles' : ''));
			}, function (x) { toastr.error(errMsg(x)); });
		});
	});

	/* ------------------------------------------------------------ recibo, reimpresión y anulación */
	var current = null;
	function receipt(r, isCopy, copyNo) {
		current = r;
		var s = r.sale, pm = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', credit_note: 'Nota de crédito', points: 'Canje de puntos' };
		var html = '<div class="pos-rc-head"><strong>' + esc(s.branch) + '</strong><div>' + esc(s.branch_address || '') + '</div><div>' + esc(s.register || '') + ' · Cajero ' + esc(s.cashier || '') + '</div>'
			+ '<div>Ticket <strong>' + esc(s.number) + '</strong> · ' + esc(s.datetime) + '</div>'
			+ (copyNo ? '<div class="pos-rc-copy">COPIA N.º ' + copyNo + '</div>' : '') + (s.status !== 'completed' ? '<div class="pos-rc-copy">ANULADA</div>' : '') + '</div>'
			+ '<table class="pos-rc-lines">' + r.items.map(function (i) { return '<tr><td>' + esc(i.name) + '<br><small>' + Number(i.amount) + ' × ' + money(i.unit_price) + '</small></td><td class="text-right">' + money(i.subtotal) + '</td></tr>'; }).join('') + '</table>'
			+ '<div class="pos-rc-tot"><div><span>Subtotal</span><span>' + money(s.subtotal) + '</span></div>' + (s.discount > 0 ? '<div><span>Descuentos</span><span>-' + money(s.discount) + '</span></div>' : '')
			+ '<div><span>Impuesto</span><span>' + money(s.tax) + '</span></div><div class="is-total"><span>Total</span><span>' + money(s.total) + '</span></div>'
			+ r.payments.map(function (p) { return '<div><span>' + (pm[p.method] || p.method) + (p.reference ? ' · ' + esc(p.reference) : '') + '</span><span>' + money(p.tendered || p.amount) + '</span></div>' + (p.change_given > 0 ? '<div><span>Cambio</span><span>' + money(p.change_given) + '</span></div>' : ''); }).join('')
			+ '</div><div class="pos-rc-foot">Cliente: ' + esc(s.customer) + '<br>Conserve este recibo para cambios y devoluciones.</div>';
		$('#pos-receipt').html(html);
		$('#btn-receipt-void').toggle(s.status === 'completed' && !!shift && s.shift_id === shift.id);
		$('#modal-pos-receipt').modal('show');
	}
	$('#btn-receipt-print').on('click', function () { window.print(); });
	$('#btn-receipt-reprint').on('click', function () {
		if (!current) return;
		var id = current.sale.id, prints = (current.prints || []).length - 1, go = function (authId) {
			return api('POST', 'pos/sales/' + id + '/reprint', { auth_id: authId || null }).then(function (r) { receipt(r, true, r.copy_no); setTimeout(function () { window.print(); }, 300); });
		};
		(prints >= S.free_reprints ? authorize('reprint', { sale_id: id }, 'El recibo ' + current.sale.number + ' ya tiene ' + prints + ' reimpresión(es).').then(function (a) { return go(a.id); }) : go())
			.catch(function (x) { if (x && x.message !== 'Autorización cancelada') toastr.error(errMsg(x)); });
	});
	$('#btn-receipt-void').on('click', function () {
		if (!current) return;
		var s = current.sale;
		authorize('void', { sale_id: s.id, amount: s.total }, 'Anular la venta ' + s.number + ' por ' + money(s.total) + '. El stock se reintegra y el pago sale del turno.').then(function (a) {
			return api('POST', 'pos/sales/' + s.id + '/void', { reason: $('#auth-reason').val() || 'Anulación autorizada', auth_id: a.id });
		}).then(function (r) { toastr.success('Venta ' + r.sale.number + ' anulada'); receipt(r); refreshShift(); loadTx(); })
			.catch(function (x) { if (x && x.message !== 'Autorización cancelada') toastr.error(errMsg(x)); });
	});

	/* ------------------------------------------------------------ transacciones */
	function loadTx() {
		var p = $.param({ branch_id: B.branchId, date: $('#pos-tx-date').val(), status: $('#pos-tx-status').val(), q: $('#pos-tx-q').val() });
		api('GET', 'pos/transactions?' + p).then(function (rows) {
			var st = { completed: 'Completada', cancelled: 'Anulada', void: 'Anulada' }, pm = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', mixed: 'Mixto', credit_note: 'Nota de crédito' };
			$('#pos-tx-tbody').html(rows.length ? rows.map(function (r) {
				return '<tr><td>' + esc(String(r.datetime).slice(11, 16)) + '</td><td>' + esc(r.number) + '</td><td>' + esc(r.register || '—') + '</td><td>' + esc(r.cashier || '') + '</td><td>' + esc(r.customer || 'Público general') + '</td><td>'
					+ esc(pm[r.payment_method] || r.payment_method) + '</td><td class="text-right">' + money(r.total) + '</td><td>' + (r.status === 'completed' ? st.completed : '<span class="ded-pill-red">' + (st[r.status] || r.status) + '</span>')
					+ '</td><td class="text-right">' + r.prints + '</td><td><button type="button" class="ded-select-btn" data-tx="' + r.id + '">Ver</button></td></tr>';
			}).join('') : '<tr><td colspan="10" class="ded-text-muted-italics">Sin transacciones con estos filtros.</td></tr>');
		}, function (x) { toastr.error(errMsg(x)); });
	}
	$('#pos-tx-date, #pos-tx-status').on('change', loadTx);
	$('#pos-tx-q').on('input', function () { clearTimeout(this._t); this._t = setTimeout(loadTx, 300); });
	$('#pos-tx-tbody').on('click', '[data-tx]', function () { api('GET', 'pos/sales/' + $(this).data('tx')).then(function (r) { receipt(r); }, function (x) { toastr.error(errMsg(x)); }); });
	$('#pill-tab-transacciones').on('shown.bs.tab', loadTx);

	/* ------------------------------------------------------------ turno, movimientos, arqueo y cierre */
	function renderShift() {
		var open = !!shift;
		$('#pos-closed-banner').toggleClass('d-none', open);
		$('#pos-shift-pill').toggleClass('is-open', open).toggleClass('is-closed', !open);
		$('#pos-shift-pill-text').text(open ? 'Turno abierto · ' + String(shift.opened_at).slice(11, 16) + ' · ' + (shift.opened_by || '') : 'Caja cerrada');
		$('#btn-open-shift').prop('disabled', open);
		$('#btn-cash-drop, #btn-cash-payin').prop('disabled', !open);
		if (!open) { $('#pos-shift-summary').html('<p class="ded-text-muted-italics mb-0">' + esc(B.register) + ' no tiene turno abierto.</p>'); $('#pos-expected-display').text('—'); arqueo(); totals(); return; }
		var m = shift.by_method;
		$('#pos-shift-summary').html('<div class="ded-comp-col">'
			+ [['Apertura', String(shift.opened_at).slice(0, 16) + ' · ' + (shift.opened_by || '')], ['Fondo inicial', money(shift.opening_amount)], ['Ventas', shift.sales_count + ' · ' + money(shift.total_sales)],
				['Anuladas', String(shift.voided_count)], ['Descuentos', money(shift.discount_total)], ['Efectivo', money(m.cash)], ['Tarjeta', money(m.card)], ['Transferencia', money(m.transfer)],
				['Retiros / ingresos', '-' + money(shift.drops) + ' / +' + money(shift.payins)], ['Efectivo esperado en gaveta', '<strong>' + money(shift.expected_cash) + '</strong>']]
				.map(function (r) { return '<div class="ded-comp-row"><span class="ded-comp-label">' + r[0] + '</span><span class="ded-comp-val">' + (r[0].indexOf('esperado') >= 0 ? r[1] : esc(r[1])) + '</span></div>'; }).join('') + '</div>');
		$('#pos-expected-display').text(money(shift.expected_cash));
		arqueo(); totals();
	}
	function refreshShift() {
		api('GET', 'pos/shifts/current?' + $.param({ branch_id: B.branchId, register: B.register })).then(function (r) { shift = r.shift; renderShift(); });
	}
	function counted() {
		var t = 0, bd = {};
		$('#pos-denoms input').each(function () { var q = parseInt($(this).val(), 10) || 0; if (q > 0) { bd[$(this).data('denom')] = q; t += q * parseFloat($(this).data('denom')); } });
		return { total: r2(t), breakdown: bd };
	}
	function arqueo() {
		var c = counted();
		$('#pos-counted-display').text(money(c.total));
		if (!shift) { $('#pos-diff-display').text('—'); $('#btn-close-shift').prop('disabled', true); return c; }
		var d = r2(c.total - shift.expected_cash);
		$('#pos-diff-display').html(Math.abs(d) < 0.005 ? 'Cuadra' : '<span class="' + (Math.abs(d) > S.cash_diff_tolerance ? 'ded-pill-red' : '') + '">' + (d > 0 ? 'Sobrante ' : 'Faltante ') + money(Math.abs(d)) + '</span>');
		$('#btn-close-shift').prop('disabled', false);
		return $.extend(c, { diff: d });
	}
	$('#pos-denoms').on('input', 'input', arqueo);
	$('#form-open-shift').on('submit', function (e) {
		e.preventDefault();
		var reg = $.trim($('#shift-register').val()) || B.register;
		api('POST', 'pos/shifts/open', { branch_id: B.branchId, register: reg, shift_name: $('#shift-name').val(), opening_amount: $('#shift-opening').val() }).then(function (s) {
			$('#modal-pos-open-shift').modal('hide');
			if (reg !== B.register) { location.href = location.pathname + '?' + $.param({ branch_id: B.branchId, register: reg }); return; }
			shift = s; renderShift(); toastr.success('Turno abierto con fondo ' + money(s.opening_amount));
		}, function (x) { toastr.error(errMsg(x)); });
	});
	function movement(kind) {
		DairyUI.formDialog(kind === 'drop' ? 'Retiro a bóveda' : 'Ingreso de efectivo', '<div class="form-group"><label>Monto</label><input type="number" class="form-control" name="amount" min="0.01" step="0.01" required></div>'
			+ '<div class="form-group mb-0"><label>Motivo</label><input type="text" class="form-control" name="reason" maxlength="120" required></div>', function (d, dlg) {
			api('POST', 'pos/shifts/' + shift.id + '/movement', { kind: kind, amount: d.amount, reason: d.reason }).then(function (s) { dlg.modal('hide'); shift = s; renderShift(); toastr.success('Movimiento registrado'); }, function (x) { toastr.error(errMsg(x)); });
		}, 'Registrar');
	}
	$('#btn-cash-drop').on('click', function () { movement('drop'); });
	$('#btn-cash-payin').on('click', function () { movement('payin'); });
	$('#btn-close-shift').on('click', function () {
		var a = arqueo(), reason = $.trim($('#pos-close-reason').val());
		var pend = 0;
		DairyOffline.items().then(function (items) {
			pend = items.filter(function (i) { return i.path === '/pos/checkout' && i.state === 'pending'; }).length;
			if (pend) { toastr.warning(pend + ' venta(s) sin conexión aún no sincronizadas: sincronice antes de cerrar el turno'); return; }
			var need = Math.abs(a.diff) > S.cash_diff_tolerance + 0.001;
			if (need && reason.length < 3) { toastr.warning('Explique la diferencia de caja antes de cerrar'); $('#pos-close-reason').focus(); return; }
			var step = need ? authorize('cash_difference', { amount: a.diff }, 'Diferencia de arqueo de ' + money(a.diff) + ' en ' + B.register + '.', reason) : Promise.resolve(null);
			step.then(function (au) {
				return api('POST', 'pos/shifts/' + shift.id + '/close', { counted_cash: a.total, breakdown: a.breakdown, reason: reason, auth_id: au ? au.id : null });
			}).then(function (s) {
				toastr.success('Turno cerrado · diferencia ' + money(s.cash_difference));
				shift = null; renderShift();
				$('#pos-shift-summary').html('<div class="ded-comp-col">' + [['Cierre', String(s.closed_at).slice(0, 16)], ['Esperado', money(s.expected_cash)], ['Contado', money(s.counted_cash)], ['Diferencia', money(s.cash_difference)], ['Ventas', s.sales_count + ' · ' + money(s.total_sales)]]
					.map(function (r) { return '<div class="ded-comp-row"><span class="ded-comp-label">' + r[0] + '</span><span class="ded-comp-val">' + esc(r[1]) + '</span></div>'; }).join('') + '</div>');
				$('#pos-denoms input').val(0); $('#pos-close-reason').val(''); arqueo();
			}).catch(function (x) { if (x && x.message !== 'Autorización cancelada') toastr.error(errMsg(x)); });
		});
	});

	/* ------------------------------------------------------------ conexión */
	function net(st) {
		var on = navigator.onLine !== false, pend = st ? st.items.filter(function (i) { return i.path === '/pos/checkout' && i.state === 'pending'; }).length : 0;
		$('#pos-net-pill').toggleClass('is-offline', !on).find('span').text(on ? (pend ? pend + ' venta(s) por sincronizar' : 'En línea') : 'Sin conexión' + (pend ? ' · ' + pend + ' pendiente(s)' : ''));
	}
	if (window.DairyOffline) DairyOffline.onChange(net);
	window.addEventListener('online', function () { net(); refreshShift(); });
	window.addEventListener('offline', function () { net(); });

	$('#btn-reset-customer').on('click', function () { $('#pos-customer-select').val(1); });
	render(); renderShift(); filter(); icons();
	window.DailyPos = { cart: function () { return cart; }, quote: quote, ref: function () { return ref; } };
})(jQuery);
