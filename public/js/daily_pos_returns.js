/*
 * DAILY — Devoluciones comerciales del POS (views/pos_returns.blade.php + services/PosReturnService.php).
 * Busca el ticket, muestra lo devolvible, arma la devolución (productos, cantidades, motivo y estado), reparte el
 * reembolso entre los medios originales (con tope) o emite nota de crédito, pide autorización cuando corresponde y
 * envía con una referencia única (reintentos no duplican). El servidor valida todo de nuevo.
 */
(function ($) {
	'use strict';
	var B = JSON.parse(document.getElementById('ret-boot').textContent), S = B.settings;
	var E = null, ref = null, current = null;

	function r2(v) { return Math.round((Number(v) + 1e-7) * 100) / 100; }
	function money(v) { v = Number(v || 0); return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function esc(s) { return DairyUI.esc(s); }
	function uuid() { return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'ret-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14); }
	function errMsg(x) { return x && x.responseText ? DairyUI.errMsg(x) : (x && x.message) || 'Error'; }
	function api(m, url, body) { return new Promise(function (ok, ko) { if (m === 'GET') Grocy.Api.Get(url, ok, ko); else Grocy.Api.Post(url, body || {}, ok, ko); }); }
	function step(n) { $('.pos-ret-step').each(function () { $(this).toggleClass('is-active', parseInt($(this).data('step'), 10) <= n); }); }

	/* ------------------------------------------------------------ búsqueda */
	function load(q) {
		api('GET', 'pos/returns/lookup?' + $.param({ branch_id: B.branchId, q: q })).then(function (e) { E = e; ref = uuid(); render(); }, function (x) { E = null; $('#ret-sale').empty(); $('#saleEmptyPrompt').show(); toastr.warning(errMsg(x)); step(0); });
	}
	$('#ret-search-form').on('submit', function (e) { e.preventDefault(); load($.trim($('#posSearchQuery').val())); });
	$(document).on('click', '[data-ticket]', function () { $('#posSearchQuery').val($(this).data('ticket')); load($(this).data('ticket')); });

	function options(map) { return Object.keys(map).map(function (k) { return '<option value="' + k + '">' + esc(map[k]) + '</option>'; }).join(''); }
	function render() {
		var s = E.sale;
		$('#saleEmptyPrompt').hide();
		step(E.eligible ? 3 : 2);
		var lines = E.lines.map(function (l) {
			var dis = !E.eligible || l.returnable <= 0;
			return '<tr data-line="' + l.sale_item_id + '"><td><strong>' + esc(l.name) + '</strong><div class="small text-muted">' + esc(l.sku || '') + (l.perishable ? ' · perecedero' : '') + '</div></td>'
				+ '<td class="text-right">' + l.sold + '</td><td class="text-right">' + l.returned + '</td><td class="text-right text-success font-weight-bold">' + l.returnable + '</td>'
				+ '<td class="text-right">' + money(l.paid - l.refunded) + '</td>'
				+ '<td><input type="number" class="form-control form-control-sm ret-qty" min="0" max="' + l.returnable + '" step="1" value="0" aria-label="Cantidad a devolver"' + (dis ? ' disabled' : '') + '></td>'
				+ '<td><select class="form-control form-control-sm ret-reason" aria-label="Motivo"' + (dis ? ' disabled' : '') + '><option value="">Motivo…</option>' + options(B.reasons) + '</select></td>'
				+ '<td><select class="form-control form-control-sm ret-cond" aria-label="Estado"' + (dis ? ' disabled' : '') + '>' + options(B.conditions) + '</select></td>'
				+ '<td class="text-right ret-line-amt">$0.00</td></tr>';
		}).join('');
		var methods = Object.keys(E.refundable_by_method);
		$('#ret-sale').html(
			'<div class="pos-sale-banner"><div><span class="badge badge-success px-2 py-1 font-weight-bold mr-2">' + esc(s.number) + '</span><strong class="text-dark">' + esc(s.customer) + '</strong> · <span class="text-muted small">' + esc(s.branch) + ' · cajero ' + esc(s.cashier || '') + '</span></div>'
			+ '<div class="small"><span class="mr-3">Fecha: <strong>' + esc(String(s.datetime).slice(0, 16)) + '</strong> (' + s.days + ' días)</span><span class="mr-3">Total: <strong class="text-success">' + money(s.total) + '</strong></span><span>Por devolver: <strong>' + money(E.refundable_total) + '</strong></span></div></div>'
			+ (E.eligible ? '' : '<div class="alert alert-danger py-2 small" data-testid="ret-blocked">' + E.blocks.map(esc).join('<br>') + '</div>')
			+ '<div class="table-responsive bg-white rounded border mb-3"><table class="table table-sm mb-0" style="font-size: 0.85rem;"><thead class="thead-light"><tr><th>Producto</th><th class="text-right">Vendidas</th><th class="text-right">Devueltas</th><th class="text-right">Disponibles</th><th class="text-right">Saldo pagado</th><th style="width:90px">Devolver</th><th style="width:190px">Motivo</th><th style="width:120px">Estado</th><th class="text-right">Importe</th></tr></thead><tbody>' + lines + '</tbody></table></div>'
			+ (E.eligible ? '<div class="row"><div class="col-lg-7 mb-3"><div class="border rounded p-3 h-100"><div class="font-weight-bold mb-2">Resolución</div>'
				+ '<div class="btn-group btn-group-toggle mb-3 flex-wrap" role="group" aria-label="Resolución">'
				+ '<label class="btn btn-sm btn-outline-success active"><input type="radio" name="ret-res" value="refund" checked> Reembolso</label>'
				+ '<label class="btn btn-sm btn-outline-success"><input type="radio" name="ret-res" value="credit_note"> Nota de crédito</label>'
				+ '<label class="btn btn-sm btn-outline-success"><input type="radio" name="ret-res" value="exchange"> Cambio de producto</label></div>'
				+ '<div id="ret-refund-box">' + (methods.length ? methods.map(function (m) {
					return '<div class="form-row align-items-center mb-2" data-method="' + m + '"><div class="col-4 small">' + esc(B.methods[m] || m) + ' <span class="text-muted">(hasta ' + money(E.refundable_by_method[m]) + ')</span></div>'
						+ '<div class="col-3"><input type="number" class="form-control form-control-sm ret-pay" min="0" step="0.01" value="0" aria-label="Importe en ' + esc(B.methods[m] || m) + '"></div>'
						+ (m === 'cash' ? '<div class="col-5"><select class="form-control form-control-sm" id="ret-register" aria-label="Caja">' + B.registers.map(function (r) { return '<option>' + esc(r) + '</option>'; }).join('') + '</select></div>'
							: '<div class="col-5"><input type="text" class="form-control form-control-sm ret-ref" maxlength="40" placeholder="Código de reverso (sin datos de tarjeta)" aria-label="Código de reverso"></div>') + '</div>';
				}).join('') : '<p class="small text-warning mb-2">El medio de pago de esta venta no tiene desglose: solo se puede devolver en nota de crédito.</p>')
				+ '<div class="form-row align-items-center mb-2" data-method="credit_note"><div class="col-4 small">Nota de crédito</div><div class="col-3"><input type="number" class="form-control form-control-sm ret-pay" min="0" step="0.01" value="0" aria-label="Importe en nota de crédito"></div></div></div>'
				+ '<p class="small text-muted mb-0" id="ret-res-note"></p></div></div>'
				+ '<div class="col-lg-5 mb-3"><div class="border rounded p-3 h-100"><div class="d-flex justify-content-between"><span>Importe a devolver</span><strong id="ret-total" data-testid="ret-total">$0.00</strong></div>'
				+ '<div class="d-flex justify-content-between small text-muted"><span>Asignado a medios</span><span id="ret-assigned">$0.00</span></div>'
				+ '<input type="text" class="form-control form-control-sm my-2" id="ret-notes" maxlength="200" placeholder="Observaciones (opcional)" aria-label="Observaciones">'
				+ '<button type="button" class="btn btn-success btn-block font-weight-bold" id="ret-submit" disabled><i class="fa-solid fa-check mr-1"></i> Registrar devolución</button>'
				+ '<p class="small text-muted mt-2 mb-0">Lo devuelto queda en el módulo de devoluciones para su inspección física; no vuelve al stock vendible desde aquí.</p></div></div></div>' : ''));
		recalc();
	}

	/* ------------------------------------------------------------ cálculo (igual que PosReturnService::Process) */
	function selection() {
		var out = [], total = 0;
		E.lines.forEach(function (l) {
			var $r = $('tr[data-line="' + l.sale_item_id + '"]'), q = Math.min(parseFloat($r.find('.ret-qty').val()) || 0, l.returnable), amt = 0;
			if (q > 0) {
				amt = Math.abs(q - l.returnable) < 0.0001 ? r2(l.paid - l.refunded) : r2(Math.min(q * l.unit_refund, l.paid - l.refunded));
				out.push({ sale_item_id: l.sale_item_id, quantity: q, reason: $r.find('.ret-reason').val(), condition: $r.find('.ret-cond').val() });
			}
			$r.find('.ret-line-amt').text(money(amt));
			total += amt;
		});
		return { lines: out, total: r2(total) };
	}
	function resolution() { return $('input[name=ret-res]:checked').val() || 'refund'; }
	function recalc(autofill) {
		if (!E || !E.eligible) return;
		var sel = selection(), res = resolution();
		$('#ret-total').text(money(sel.total));
		$('#ret-refund-box').toggle(res === 'refund');
		$('#ret-res-note').text(res === 'refund' ? 'Reparta el importe entre los medios con que se pagó (o nota de crédito).' : res === 'credit_note' ? 'Se emite una nota de crédito por el importe, canjeable en el punto de venta.' : 'Cambio: se emite una nota de crédito por lo devuelto y se canjea en el punto de venta al cobrar el producto nuevo.');
		if (autofill && res === 'refund') {
			var left = sel.total;
			$('#ret-refund-box [data-method]').each(function () {
				var m = $(this).data('method'), cap = m === 'credit_note' ? Infinity : E.refundable_by_method[m], v = r2(Math.min(left, cap));
				$(this).find('.ret-pay').val(v.toFixed(2)); left = r2(left - v);
			});
		}
		var assigned = 0;
		$('#ret-refund-box .ret-pay').each(function () { assigned += parseFloat($(this).val()) || 0; });
		assigned = r2(assigned);
		$('#ret-assigned').text(money(assigned));
		var ok = sel.lines.length > 0 && sel.total > 0 && sel.lines.every(function (l) { return l.reason; }) && (res !== 'refund' || Math.abs(assigned - sel.total) < 0.005);
		$('#ret-submit').prop('disabled', !ok);
		step(ok ? 4 : 3);
	}
	$('#ret-sale').on('input change', '.ret-qty, .ret-reason, .ret-cond', function () { recalc(true); });
	$('#ret-sale').on('input', '.ret-pay', function () { recalc(false); });
	$('#ret-sale').on('change', 'input[name=ret-res]', function () { $('input[name=ret-res]').closest('label').removeClass('active'); $(this).closest('label').addClass('active'); recalc(true); });

	/* ------------------------------------------------------------ autorización y envío */
	function authorize(amount) {
		return new Promise(function (ok, ko) {
			var $m = $('#modal-pos-auth'), done = false;
			$('#pos-auth-desc').text('Devolución de ' + money(amount) + ' sobre el límite del cajero (' + money(S.return_auth_threshold) + ').');
			$('#auth-reason').val(''); $('#auth-user, #auth-pass').val('');
			$('#form-pos-auth').off('submit').on('submit', function (e) {
				e.preventDefault();
				api('POST', 'pos/authorize', { kind: 'refund', branch_id: B.branchId, sale_id: E.sale.id, amount: amount, username: $('#auth-user').val(), password: $('#auth-pass').val(), reason: $('#auth-reason').val() })
					.then(function (a) { done = true; $('#auth-pass').val(''); $m.modal('hide'); ok(a); }, function (x) { toastr.error(errMsg(x)); });
			});
			$m.off('hidden.bs.modal').on('hidden.bs.modal', function () { $('#auth-pass').val(''); if (!done) ko(new Error('Autorización cancelada')); });
			$m.modal('show');
		});
	}
	function send(authId) {
		var sel = selection(), res = resolution(), refunds = [];
		if (res === 'refund') {
			$('#ret-refund-box [data-method]').each(function () {
				var v = r2(parseFloat($(this).find('.ret-pay').val()) || 0);
				if (v > 0) refunds.push({ method: $(this).data('method'), amount: v, reference: $(this).find('.ret-ref').val() || null });
			});
		}
		return api('POST', 'pos/returns/process', { return_ref: ref, sale_id: E.sale.id, register: $('#ret-register').val() || B.registers[0], resolution: res, lines: sel.lines, refunds: refunds, auth_id: authId || null, notes: $('#ret-notes').val() });
	}
	$('#ret-sale').on('click', '#ret-submit', function () {
		var $b = $(this).prop('disabled', true), total = selection().total;
		send(null).catch(function (x) {
			var msg = errMsg(x);
			if (/autorizaci/i.test(msg) && total > S.return_auth_threshold) return authorize(total).then(function (a) { return send(a.id); });
			throw new Error(msg);
		}).then(function (r) {
			toastr.success('Devolución ' + r['return'].code + ' registrada' + (r.replayed ? ' (ya estaba registrada: no se duplicó)' : ''));
			voucher(r); step(7);
			load(E.sale.number);
		}).catch(function (e) { if (e && e.message !== 'Autorización cancelada') toastr.error(e.message); $b.prop('disabled', false); });
	});

	/* ------------------------------------------------------------ comprobante */
	var RES = { refund: 'Reembolso', credit_note: 'Nota de crédito', exchange: 'Cambio (nota de crédito)' };
	function voucher(r) {
		current = r;
		var h = r['return'];
		$('#ret-voucher').html('<div class="pos-rc-head"><strong>' + esc(h.branch || '') + '</strong><div>' + esc(h.address || '') + '</div><div>Devolución <strong>' + esc(h.code) + '</strong> · ' + esc(String(h.created_at).slice(0, 16)) + '</div>'
			+ '<div>Ticket original ' + esc(h.sale_number) + ' (' + esc(String(h.sold_at).slice(0, 10)) + ') · ' + esc(RES[h.resolution] || h.resolution) + '</div></div>'
			+ '<table class="pos-rc-lines">' + r.lines.map(function (l) { return '<tr><td>' + esc(l.name) + '<br><small>' + Number(l.quantity) + ' u. · ' + esc(l.reason || '') + '</small></td><td class="text-right">' + money(l.amount) + '</td></tr>'; }).join('') + '</table>'
			+ '<div class="pos-rc-tot"><div class="is-total"><span>Total devuelto</span><span>' + money(h.amount) + '</span></div>'
			+ r.refunds.map(function (f) { return '<div><span>' + esc(B.methods[f.method] || f.method) + (f.reference ? ' · ' + esc(f.reference) : '') + '</span><span>' + money(f.amount) + '</span></div>'; }).join('') + '</div>'
			+ (h.credit_note ? '<div class="pos-rc-copy text-center">NOTA DE CRÉDITO ' + esc(h.credit_note) + '<br>Saldo ' + money(h.credit_balance) + ' · vence ' + esc(h.expires_at || '') + '</div>' : '')
			+ '<div class="pos-rc-foot">Cliente: ' + esc(h.customer || 'Público general') + ' · Atendió: ' + esc(h.cashier || '') + '<br>Productos enviados a inspección.</div>');
		$('#modalPosReturnVoucher').modal('show');
	}
	$('#btn-voucher-print').on('click', function () { window.print(); });

	/* ------------------------------------------------------------ historial */
	function history() {
		api('GET', 'pos/returns/history?' + $.param({ branch_id: B.branchId, from: $('#ret-h-from').val(), to: $('#ret-h-to').val(), q: $('#ret-h-q').val() })).then(function (rows) {
			$('#ret-h-body').html(rows.length ? rows.map(function (r) {
				return '<tr><td>' + esc(String(r.created_at).slice(0, 16)) + '</td><td><strong>' + esc(r.code) + '</strong></td><td>' + esc(r.sale_number) + '</td><td>' + esc(r.customer || 'Público general') + '</td><td>' + esc(RES[r.resolution] || r.resolution)
					+ '</td><td>' + esc((r.methods || '').split(', ').map(function (m) { return B.methods[m] || m; }).join(', ')) + '</td><td class="text-right">' + Number(r.items_count) + '</td><td class="text-right">' + money(r.amount)
					+ '</td><td>' + esc(r.approved || '—') + '</td><td>' + (r.pending_inspection > 0 ? r.pending_inspection + ' pendiente(s)' : 'Inspeccionada') + '</td><td><button type="button" class="btn btn-xs btn-light border" data-ret="' + r.id + '">Comprobante</button></td></tr>';
			}).join('') : '<tr><td colspan="11" class="text-muted">Sin devoluciones en el período.</td></tr>');
		}, function (x) { toastr.error(errMsg(x)); });
	}
	$('#ret-h-from, #ret-h-to').on('change', history);
	$('#ret-h-q').on('input', function () { clearTimeout(this._t); this._t = setTimeout(history, 300); });
	$('#tab-historial-tab').on('shown.bs.tab', history);
	$('#ret-h-body').on('click', '[data-ret]', function () { api('GET', 'pos/returns/' + $(this).data('ret')).then(voucher, function (x) { toastr.error(errMsg(x)); }); });

	step(1);
	window.DailyPosReturns = { selection: function () { return E ? selection() : null; }, eligibility: function () { return E; } };
})(jQuery);
