/* Inventario y Almacenes — sección «Operación de inventario» de /stockoverview.
 * Lee /api/inventory-ops/* (InventoryOperationsService). Cada pestaña se pide al abrirla y se cachea por sucursal;
 * el escáner acepta pistolas láser (escriben el código y envían Enter) y teclado del móvil. */
(function () {
	'use strict';
	var $root = $('#inv-ops');
	if (!$root.length) return;

	var CAN = { approve: $root.data('can-approve') == 1, transfer: $root.data('can-transfer') == 1, count: $root.data('can-count') == 1 };
	var ME = Number($root.data('user-id')) || 0;
	var cache = {};
	var tab = 'positions';
	var kardexProduct = null;

	function esc(s) { return DairyUI.esc(s); }
	function err(x) { return x && x.responseText ? DairyUI.errMsg(x) : (x && x.message) || 'Error'; }
	function get(url, q) {
		var qs = $.param($.extend({}, q || {}));
		return new Promise(function (ok, ko) { Grocy.Api.Get(url + (qs ? (url.indexOf('?') >= 0 ? '&' : '?') + qs : ''), ok, ko); });
	}
	function post(url, body) { return new Promise(function (ok, ko) { Grocy.Api.Post(url, body || {}, ok, ko); }); }
	function branch() { var v = $('#dsm-filter-branch').val(); return v && v !== 'all' ? v : ''; }
	function bq(extra) { var b = branch(); return $.extend(b ? { branch_id: b } : {}, extra || {}); }
	function n(v, d) { if (v === null || v === undefined || v === '') return '—'; return Number(v).toLocaleString(undefined, { maximumFractionDigits: d === undefined ? 2 : d }); }
	function money(v) { return v === null || v === undefined ? '—' : '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
	function signed(v) { var x = Number(v); return '<span class="' + (x < 0 ? 'iop-neg' : 'iop-pos') + '">' + (x > 0 ? '+' : '') + n(x) + '</span>'; }
	function pill(state, label) { return '<span class="iop-pill is-' + esc(state) + '">' + esc(label || state) + '</span>'; }
	function busy($b, on) { $b.prop('disabled', on); }
	function loading(msg) { $('#iop-panel').html('<div class="iop-loading">' + esc(msg || 'Cargando') + '</div>'); }
	function fail(x) { $('#iop-panel').html('<div class="iop-error">' + esc(err(x)) + '</div>'); }
	function table(cols, rows, opts) {
		opts = opts || {};
		if (!rows.length) return '<div class="iop-empty">' + esc(opts.empty || 'Sin registros') + '</div>';
		var h = '<div class="iop-table-wrap"><table class="iop-table is-stack"><thead><tr>' + cols.map(function (c) { return '<th' + (c.num ? ' class="num"' : '') + '>' + esc(c.l) + '</th>'; }).join('') + '</tr></thead><tbody>';
		rows.forEach(function (r, i) {
			h += '<tr' + (opts.click ? ' class="is-click" data-i="' + i + '"' : '') + '>' + cols.map(function (c) { return '<td data-l="' + esc(c.l) + '"' + (c.num ? ' class="num"' : '') + '>' + c.f(r) + '</td>'; }).join('') + '</tr>';
		});
		return h + '</tbody></table></div>';
	}
	function cached(key, loader) {
		var k = key + '|' + branch();
		if (!cache[k]) cache[k] = loader().catch(function (e) { delete cache[k]; throw e; });
		return cache[k];
	}
	function invalidate() { cache = {}; }

	var STATE = { negative: 'Negativo', overcommitted: 'Sobrecomprometido', out: 'Agotado', below_min: 'Bajo mínimo', ok: 'Normal', empty: 'Sin stock' };

	/* ------------------------------------------------------------ resumen: ciclo + KPI */
	function loadSummary() {
		return cached('summary', function () { return get('inventory-ops/summary', bq()); }).then(function (s) {
			var k = s.kpis;
			$('#iop-generated').text('Actualizado ' + String(s.generated_at).slice(11, 16));
			$('#iop-cycle').html(s.cycle.map(function (c, i) {
				return '<button type="button" class="iop-step" data-href="' + esc(c.href) + '" title="' + esc(c.pending_label) + '">' +
					'<span class="iop-step-n">' + (i + 1) + '</span> <span class="iop-step-label">' + esc(c.label) + '</span><span class="iop-step-owner">' + esc(c.owner) + '</span>' +
					'<span class="iop-step-meta"><span>' + (c.done_7d === null ? '' : n(c.done_7d, 0) + ' <span class="iop-muted">7 d</span>') + '</span>' +
					'<span class="iop-step-pend' + (c.pending ? '' : ' is-zero') + '">' + n(c.pending, 0) + ' <span class="iop-muted">' + esc(c.pending_label) + '</span></span></span></button>';
			}).join(''));
			var acc = k.accuracy_pct === null ? '—' : n(k.accuracy_pct, 1) + '%';
			var cards = [
				['Físico', n(k.physical, 0), money(k.value), ''],
				['Reservado', n(k.reserved, 0), 'pedidos confirmados', ''],
				['Bloqueado', n(k.blocked, 0), 'cuarentena, vencido, merma', k.blocked > 0 ? 'is-warn' : ''],
				['Disponible', n(k.available, 0), 'para vender o despachar', ''],
				['Bodega / Sala', n(k.bodega, 0) + ' / ' + n(k.sala, 0), n(k.other_locations, 0) + ' en otras ubicaciones', ''],
				['Exactitud', acc, k.accuracy_counts ? n(k.accuracy_hits, 0) + ' de ' + n(k.accuracy_counts, 0) + ' conteos' : 'sin conteos en 90 días', k.accuracy_pct !== null && k.accuracy_pct < 95 ? 'is-warn' : ''],
				['Cobertura · Rotación', (k.coverage_days === null ? '—' : n(k.coverage_days, 1) + ' d') + ' · ' + (k.rotation === null ? '—' : n(k.rotation, 1) + '×'), k.coverage_note || k.rotation_note || 'salidas reales', k.coverage_note || k.rotation_note ? 'is-warn' : ''],
				['Negativo · Sobrecomprometido', n(k.negative, 0) + ' · ' + n(k.overcommitted, 0), 'pares producto-sucursal', (k.negative + k.overcommitted) > 0 ? 'is-bad' : '']
			];
			var f = s.formulas;
			var tips = ['', '', f.available, f.available, '', f.accuracy, f.coverage + ' · ' + f.rotation, ''];
			$('#iop-kpis').html(cards.map(function (c, i) {
				return '<div class="iop-kpi ' + c[3] + '"' + (tips[i] ? ' title="' + esc(tips[i]) + '"' : '') + '><span class="iop-kpi-label">' + esc(c[0]) + '</span><span class="iop-kpi-val">' + c[1] + '</span><span class="iop-kpi-sub">' + esc(c[2]) + '</span></div>';
			}).join(''));
			badge('counts', k.counts_open + k.adjustments_pending);
			badge('replenishment', k.replenishment_suggested + k.replenishment_tasks);
			badge('transfers', k.transit_late, k.transit_open);
			return s;
		}).catch(function (x) { $('#iop-kpis').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
	}
	function badge(id, v, soft) {
		var $b = $('#iop-b-' + id);
		var val = v || soft || 0;
		$b.prop('hidden', !val).text(val);
	}

	/* ------------------------------------------------------------ pestañas */
	var TABS = {
		positions: function () {
			// ?iop_state=out|below_min#inv-ops-positions (enlaces desde HOY) se aplica solo en la primera carga
			var q = $('#iop-pos-q').val() || '', st = $('#iop-pos-state').length ? ($('#iop-pos-state').val() || '') : (new URLSearchParams(location.search).get('iop_state') || '');
			if (!STATE[st]) st = '';
			paint('<div class="iop-toolbar"><input class="iop-grow" id="iop-pos-q" placeholder="Producto, SKU o UPC" value="' + esc(q) + '">' +
				'<select id="iop-pos-state"><option value="">Todos los estados</option>' + Object.keys(STATE).map(function (s) { return '<option value="' + s + '"' + (s === st ? ' selected' : '') + '>' + STATE[s] + '</option>'; }).join('') + '</select></div>' +
				'<div class="iop-note">Disponible = físico − cuarentena − vencido − merma por aprobar − reservado.</div><div id="iop-pos-body"><div class="iop-loading">Cargando existencias</div></div>');
			get('inventory-ops/positions', bq({ q: q, status: st, limit: 200 })).then(function (d) {
				$('#iop-pos-body').html('<div class="iop-note">' + n(d.total, 0) + ' pares producto-sucursal' + (d.total > d.rows.length ? ' · se muestran ' + d.rows.length : '') + '</div>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc(r.sku || '') + (r.barcode ? ' · ' + esc(r.barcode) : '') + '</span>'; } },
					{ l: 'Sucursal', f: function (r) { return esc(r.branch_name); } },
					{ l: 'Físico', num: 1, f: function (r) { return n(r.physical); } },
					{ l: 'Bodega', num: 1, f: function (r) { return n(r.bodega); } },
					{ l: 'Sala', num: 1, f: function (r) { return n(r.sala); } },
					{ l: 'Reservado', num: 1, f: function (r) { return n(r.reserved); } },
					{ l: 'Bloqueado', num: 1, f: function (r) { return n(r.blocked); } },
					{ l: 'Disponible', num: 1, f: function (r) { return '<span class="iop-strong">' + n(r.available) + '</span>' + (r.shortfall > 0 ? '<br><span class="iop-neg">faltan ' + n(r.shortfall) + '</span>' : ''); } },
					{ l: 'Estado', f: function (r) { return pill(r.state, STATE[r.state]); } }
				], d.rows, { click: true, empty: 'Sin existencias con estos filtros' }));
				$('#iop-pos-body tr.is-click').on('click', function () { var r = d.rows[$(this).data('i')]; openKardex(r.product_id, r.name); });
			}).catch(function (x) { $('#iop-pos-body').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
		},
		locations: function () {
			loading('Cargando ubicaciones');
			cached('locations', function () { return get('inventory-ops/locations', bq()); }).then(function (d) {
				var zones = '<div class="iop-grid2">' + d.zones.map(function (z) { return '<div class="iop-box"><h3>' + esc(z.label) + '</h3><span class="iop-strong">' + n(z.units, 0) + '</span> u · ' + money(z.value) + '<br><span class="iop-muted">' + n(z.locations, 0) + ' ubicaciones</span></div>'; }).join('') + '</div>';
				paint(zones + '<div class="iop-note">Bodega = zona de almacenamiento; Sala de ventas = piso de venta. Ocupación contra la capacidad registrada.</div>' + table([
					{ l: 'Ubicación', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc([r.label_code, r.aisle && ('Pasillo ' + r.aisle), r.shelf && ('Estante ' + r.shelf), r.bin].filter(Boolean).join(' · ')) + '</span>'; } },
					{ l: 'Sucursal', f: function (r) { return esc(r.branch_name); } },
					{ l: 'Zona', f: function (r) { return esc(r.zone_label); } },
					{ l: 'Productos', num: 1, f: function (r) { return n(r.products, 0); } },
					{ l: 'Unidades', num: 1, f: function (r) { return n(r.units); } },
					{ l: 'Valor', num: 1, f: function (r) { return money(r.value); } },
					{ l: 'Ocupación', num: 1, f: function (r) { return r.occupancy_pct === null ? '<span class="iop-muted">sin capacidad</span>' : n(r.occupancy_pct, 1) + '%' + (r.occupancy_note ? '<br><span class="iop-neg">' + esc(r.occupancy_note) + '</span>' : ''); } },
					{ l: 'Vencido', num: 1, f: function (r) { return r.expired > 0 ? '<span class="iop-neg">' + n(r.expired) + '</span>' : '0'; } }
				], d.locations));
			}).catch(fail);
		},
		kardex: function () {
			paint('<div class="iop-toolbar"><input class="iop-grow" id="iop-kx-q" placeholder="Escanear o escribir producto, SKU o UPC" value="' + esc(kardexProduct ? kardexProduct.name : '') + '">' +
				'<input type="date" id="iop-kx-from" value="' + isoDays(-30) + '"><input type="date" id="iop-kx-to" value="' + isoDays(0) + '"><button class="iop-btn is-primary" id="iop-kx-go">Ver kardex</button>' +
				'<a class="iop-btn" href="' + U('/kardex') + '" style="line-height:34px">Kardex valorizado</a></div><div id="iop-kx-body">' +
				(kardexProduct ? '<div class="iop-loading">Cargando kardex</div>' : '<div class="iop-empty">Seleccione un producto (también desde Existencias o con el escáner)</div>') + '</div>');
			$('#iop-kx-go').on('click', findKardex);
			$('#iop-kx-q').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findKardex(); } });
			if (kardexProduct) renderKardex();
		},
		counts: function () {
			loading('Cargando conteos y ajustes');
			cached('counts', function () { return get('inventory-ops/adjustments', bq()); }).then(function (d) {
				var pending = d.counts.filter(function (c) { return c.status === 'pending'; });
				var disc = d.counts.filter(function (c) { return c.status === 'discrepancy'; });
				var h = '<div class="iop-note">Un conteo nunca cambia el stock: la diferencia queda pendiente y la aplica un aprobador distinto de quien contó.</div>';
				h += '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Conteos programados (ciegos)</h3>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.product) + '</span>'; } },
					{ l: 'Ubicación', f: function (r) { return esc(r.location || 'Toda la sucursal') + '<br><span class="iop-muted">' + esc(r.branch) + '</span>'; } },
					{ l: 'Contado', f: function (r) { return CAN.count ? '<span style="display:flex;gap:4px;justify-content:flex-end"><input type="number" min="0" step="any" inputmode="decimal" class="iop-cnt" data-id="' + r.id + '" style="width:90px;height:34px;border:1px solid var(--dsm-border);border-radius:8px;padding:0 8px"><button class="iop-btn is-primary iop-cnt-go" data-id="' + r.id + '">Registrar</button></span>' : '—'; } }
				], pending, { empty: 'Sin conteos pendientes' });
				h += '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Diferencias por conciliar</h3>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.product) + '</span><br><span class="iop-muted">' + esc(r.branch) + ' · ' + esc(r.location || '') + ' · contó ' + esc(r.counted_by || '') + '</span>'; } },
					{ l: 'Sistema', num: 1, f: function (r) { return n(r.theoretical_stock); } },
					{ l: 'Contado', num: 1, f: function (r) { return n(r.counted_stock); } },
					{ l: 'Diferencia', num: 1, f: function (r) { return signed(r.difference_amount) + '<br><span class="iop-muted">' + money(r.difference_cost) + '</span>'; } },
					{ l: 'Acción', f: function (r) { return CAN.approve ? '<button class="iop-btn is-primary iop-cc-ok" data-id="' + r.id + '">Aprobar ajuste</button>' : '<span class="iop-muted">Requiere aprobador</span>'; } }
				], disc, { empty: 'Sin diferencias pendientes' });
				h += '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Ajustes solicitados</h3>' + table([
					{ l: 'Ajuste', f: function (r) { return '<span class="iop-strong">' + esc(r.adjustment_number) + '</span><br><span class="iop-muted">' + esc(r.created_timestamp) + ' · ' + esc(r.requested_by || '') + '</span>'; } },
					{ l: 'Producto', f: function (r) { return esc(r.product) + '<br><span class="iop-muted">' + esc(r.branch) + (r.location ? ' · ' + esc(r.location) : '') + '</span>'; } },
					{ l: 'Sistema → contado', num: 1, f: function (r) { return n(r.recorded_stock) + ' → ' + n(r.counted_stock); } },
					{ l: 'Diferencia', num: 1, f: function (r) { return signed(r.difference_amount) + '<br><span class="iop-muted">' + money(r.total_cost_impact) + '</span>'; } },
					{ l: 'Acción', f: function (r) {
						if (!CAN.approve) return '<span class="iop-muted">Requiere aprobador</span>';
						if (Number(r.requested_by_user_id) === ME) return '<span class="iop-muted">Lo solicitó usted: otro usuario debe aprobar</span>';
						return '<span style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end"><button class="iop-btn is-primary iop-adj-ok" data-id="' + r.id + '">Aprobar</button><button class="iop-btn is-danger iop-adj-no" data-id="' + r.id + '">Rechazar</button></span>';
					} }
				], d.adjustments, { empty: 'Sin ajustes pendientes de aprobación' });
				paint(h);
				$('.iop-cnt-go').on('click', function () {
					var id = $(this).data('id'), v = $('.iop-cnt[data-id=' + id + ']').val(), $b = $(this);
					if (v === '') { toastr.warning('Ingrese la cantidad contada'); return; }
					busy($b, true);
					post('stock/cycle-counts/' + id + '/execute', { counted_stock: v }).then(function (r) { toastr.success(r.message); refresh(); }).catch(function (x) { busy($b, false); toastr.error(err(x)); });
				});
				$('.iop-cc-ok').on('click', function () { act($(this), 'stock/cycle-counts/' + $(this).data('id') + '/approve-adjustment', {}); });
				$('.iop-adj-ok').on('click', function () { act($(this), 'inventory-ops/adjustments/' + $(this).data('id') + '/approve', {}); });
				$('.iop-adj-no').on('click', function () {
					var $b = $(this);
					var reason = window.prompt('Motivo del rechazo');
					if (!reason) return;
					act($b, 'inventory-ops/adjustments/' + $b.data('id') + '/reject', { reason: reason });
				});
			}).catch(fail);
		},
		replenishment: function () {
			loading('Calculando reposición');
			cached('replenishment', function () { return get('inventory-ops/replenishment', bq({ limit: 200 })); }).then(function (d) {
				var h = '<div class="iop-note">' + esc(d.rule) + '. El stock se mueve solo al confirmar la tarea.</div>';
				h += '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Tareas abiertas</h3>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.product_name) + '</span><br><span class="iop-muted">' + esc(r.sku || '') + '</span>'; } },
					{ l: 'De → a', f: function (r) { return esc(r.from_location || '') + ' → ' + esc(r.to_location || ''); } },
					{ l: 'Cantidad', num: 1, f: function (r) { return n(r.amount); } },
					{ l: 'Acción', f: function (r) { return CAN.transfer ? '<span style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end"><input type="number" min="0" step="any" inputmode="decimal" class="iop-rp-q" data-id="' + r.id + '" value="' + esc(r.amount) + '" style="width:80px;height:34px;border:1px solid var(--dsm-border);border-radius:8px;padding:0 8px"><button class="iop-btn is-primary iop-rp-ok" data-id="' + r.id + '">Confirmar</button><button class="iop-btn is-danger iop-rp-no" data-id="' + r.id + '">Cancelar</button></span>' : '—'; } }
				], d.tasks, { empty: 'Sin tareas abiertas' });
				var branchId = branch();
				h += '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Sugerencias (sala bajo mínimo con stock en bodega)</h3>' +
					(CAN.transfer && d.suggestions.some(function (s) { return !s.task_open; }) ? (branchId ? '<div class="iop-toolbar"><button class="iop-btn is-primary" id="iop-rp-create">Crear tareas seleccionadas</button></div>' : '<div class="iop-note is-warn">Elija una sucursal en el filtro superior para crear tareas.</div>') : '') +
					table([
						{ l: '', f: function (r) { return r.task_open || !branchId ? '' : '<input type="checkbox" class="iop-rp-sel" data-p="' + r.product_id + '" data-q="' + r.suggested + '" checked aria-label="Seleccionar">'; } },
						{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc(r.branch) + '</span>'; } },
						{ l: 'Mínimo', num: 1, f: function (r) { return n(r.min_stock); } },
						{ l: 'Sala', num: 1, f: function (r) { return r.sala <= 0 ? '<span class="iop-neg">' + n(r.sala) + '</span>' : n(r.sala); } },
						{ l: 'Bodega', num: 1, f: function (r) { return n(r.bodega); } },
						{ l: 'Reponer', num: 1, f: function (r) { return '<span class="iop-strong">' + n(r.suggested) + '</span>' + (r.task_open ? '<br><span class="iop-muted">tarea abierta</span>' : ''); } }
					], d.suggestions, { empty: 'Sin productos para reponer' });
				paint(h);
				$('#iop-rp-create').on('click', function () {
					var items = $('.iop-rp-sel:checked').map(function () { return { product_id: $(this).data('p'), amount: $(this).data('q') }; }).get();
					if (!items.length) { toastr.warning('Seleccione al menos un producto'); return; }
					act($(this), 'inventory-ops/replenishment', { branch_id: branchId, items: items });
				});
				$('.iop-rp-ok').on('click', function () { var id = $(this).data('id'); act($(this), 'inventory-ops/replenishment/' + id + '/confirm', { amount: $('.iop-rp-q[data-id=' + id + ']').val() }); });
				$('.iop-rp-no').on('click', function () { var r = window.prompt('Motivo de la cancelación'); if (r) act($(this), 'inventory-ops/replenishment/' + $(this).data('id') + '/cancel', { reason: r }); });
			}).catch(fail);
		},
		transfers: function () {
			loading('Cargando traslados en tránsito');
			cached('transfers', function () { return get('inventory-ops/transfers', bq()); }).then(function (d) {
				paint('<div class="iop-toolbar"><a class="iop-btn is-primary" href="' + U('/transfers') + '" style="line-height:34px">Crear o recibir traslado</a></div>' +
					'<div class="iop-note">Entre tiendas solo se traslada con documento: la mercancía queda en tránsito (no disponible) hasta que el destino confirma la recepción. Más de ' + d.late_hours + ' h sin confirmar es anomalía.</div>' + table([
						{ l: 'Traslado', f: function (r) { return '<span class="iop-strong">' + esc(r.code) + '</span><br><span class="iop-muted">' + esc(r.sent_at || '') + '</span>'; } },
						{ l: 'Origen → destino', f: function (r) { return esc(r.from_branch) + ' → ' + esc(r.to_branch); } },
						{ l: 'Enviado', num: 1, f: function (r) { return n(r.sent); } },
						{ l: 'Por recibir', num: 1, f: function (r) { return n(r.units); } },
						{ l: 'Horas', num: 1, f: function (r) { return r.late ? '<span class="iop-neg">' + n(r.hours, 0) + '</span>' : n(r.hours, 0); } },
						{ l: 'Estado', f: function (r) { return r.late ? pill('critical', 'Sin confirmar') : pill('warning', 'En tránsito'); } }
					], d.rows, { click: true, empty: 'Sin traslados en tránsito' }));
				$('#iop-panel tr.is-click').on('click', function () { location.href = U('/transfers'); });
			}).catch(fail);
		},
		anomalies: function () {
			loading('Buscando anomalías');
			cached('anomalies', function () { return get('inventory-ops/anomalies', bq()); }).then(function (d) {
				var TYPES = { negative: 'Stock negativo', overcommitted: 'Sobrecomprometido', count_difference: 'Diferencia de conteo', transfer_unconfirmed: 'Traslado sin recepción', adjustment_not_applied: 'Ajuste sin aplicar', duplicate: 'Posible duplicado' };
				var SEV = { critical: 'Crítica', high: 'Alta', medium: 'Media', low: 'Baja' };
				var type = $('#iop-an-type').val() || '';
				var rows = d.items.filter(function (i) { return !type || i.type === type; });
				var seal = d.ledger_seal || {};
				paint('<div class="iop-toolbar"><select id="iop-an-type"><option value="">Todas (' + d.total + ')</option>' + Object.keys(d.counts).map(function (t) { return '<option value="' + t + '"' + (t === type ? ' selected' : '') + '>' + esc(TYPES[t] || t) + ' (' + d.counts[t] + ')</option>'; }).join('') + '</select>' +
					'<span class="iop-note">' + (seal.ok === false ? '<span class="iop-neg">Libro de movimientos con sello roto</span>' : 'Libro de movimientos sellado') + '</span></div>' + table([
						{ l: 'Severidad', f: function (r) { return pill(r.severity, SEV[r.severity]); } },
						{ l: 'Tipo', f: function (r) { return esc(TYPES[r.type] || r.type); } },
						{ l: 'Producto / documento', f: function (r) { return '<span class="iop-strong">' + esc(r.product) + '</span><br><span class="iop-muted">' + esc(r.branch || '') + '</span>'; } },
						{ l: 'Cantidad', num: 1, f: function (r) { return n(r.value); } },
						{ l: 'Explicación', f: function (r) { return esc(r.detail); } }
					], rows, { click: true, empty: 'Sin anomalías' }));
				$('#iop-an-type').on('change', function () { TABS.anomalies(); });
				$('#iop-panel tr.is-click').on('click', function () {
					var r = rows[$(this).data('i')];
					if (r.type === 'transfer_unconfirmed') location.href = U('/transfers');
					else if (r.type === 'count_difference') select('counts');
					else if (r.product_id) openKardex(r.product_id, r.product);
				});
				badge('anomalies', d.total);
			}).catch(fail);
		},
		coverage: function () {
			loading('Calculando antigüedad, cobertura y rotación');
			Promise.all([
				cached('aging', function () { return get('inventory-ops/aging', bq()); }),
				cached('coverage', function () { return get('inventory-ops/coverage', bq({ limit: 150 })); })
			]).then(function (res) {
				var a = res[0], c = res[1];
				var max = Math.max.apply(null, [1].concat(a.buckets.map(function (b) { return b.value; })));
				var bars = '<div class="iop-box"><h3>Antigüedad del inventario (días)</h3><div class="iop-bars">' + a.buckets.map(function (b) {
					return '<div class="iop-bar"><span class="iop-strong">' + esc(b.bucket) + '</span><span class="iop-bar-track"><span class="iop-bar-fill" style="display:block;width:' + Math.round(b.value / max * 100) + '%"></span></span><span class="num">' + money(b.value) + (b.share_pct === null ? '' : ' · ' + n(b.share_pct, 1) + '%') + '</span></div>';
				}).join('') + '</div><div class="iop-note">' + esc(a.basis) + '</div></div>';
				var CS = { short: 'Cobertura corta', stagnant: 'Sin salidas', excess: 'Exceso', ok: 'Normal' };
				var counts = '<div class="iop-box"><h3>Cobertura y rotación (' + c.window_days + ' d de salidas)</h3>' + Object.keys(CS).map(function (k) { return pill(k, CS[k] + ': ' + (c.counts[k] || 0)); }).join(' ') +
					'<div class="iop-note">Corta &lt; 3 días · Exceso &gt; 60 días. Los extremos se explican por fila.</div></div>';
				paint('<div class="iop-grid2">' + bars + counts + '</div>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc(r.branch) + '</span>'; } },
					{ l: 'Físico', num: 1, f: function (r) { return n(r.physical); } },
					{ l: 'Salidas/día', num: 1, f: function (r) { return n(r.daily_exits); } },
					{ l: 'Cobertura', num: 1, f: function (r) { return r.coverage_days === null ? '—' : n(r.coverage_days, 1) + ' d'; } },
					{ l: 'Rotación', num: 1, f: function (r) { return r.rotation === null ? '—' : n(r.rotation, 1) + '×'; } },
					{ l: 'Estado', f: function (r) { return pill(r.state, CS[r.state]) + (r.note ? '<br><span class="iop-muted">' + esc(r.note) + '</span>' : ''); } }
				], c.rows, { click: true }) + (a.oldest.length ? '<h3 class="iop-title" style="font-size:13px;margin:12px 0 4px">Lotes de más de 90 días</h3>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc(r.branch) + '</span>'; } },
					{ l: 'Unidades', num: 1, f: function (r) { return n(r.units); } },
					{ l: 'Valor', num: 1, f: function (r) { return money(r.value); } },
					{ l: 'Edad máx.', num: 1, f: function (r) { return n(r.max_age, 0) + ' d'; } }
				], a.oldest) : ''));
				$('#iop-panel .iop-table').first().find('tr.is-click').on('click', function () { var r = c.rows[$(this).data('i')]; openKardex(r.product_id, r.name); });
			}).catch(fail);
		},
		lots: function () {
			var days = $('#iop-lot-days').val() || '30', q = $('#iop-lot-q').val() || '';
			paint('<div class="iop-toolbar"><input class="iop-grow" id="iop-lot-q" placeholder="Producto, SKU o lote" value="' + esc(q) + '"><select id="iop-lot-days">' + [7, 15, 30, 60, 90].map(function (d) { return '<option value="' + d + '"' + (String(d) === days ? ' selected' : '') + '>Vencen en ≤ ' + d + ' días</option>'; }).join('') + '</select>' +
				'<a class="iop-btn" href="' + U('/traceability') + '" style="line-height:34px">Trazabilidad de lotes</a></div><div id="iop-lot-body"><div class="iop-loading">Cargando lotes</div></div>');
			get('inventory-ops/lots', bq({ days: days, q: q })).then(function (d) {
				var LS = { expired: 'Vencido', critical: '≤ 7 días', warning: 'Por vencer' };
				$('#iop-lot-body').html('<div class="iop-note">' + esc(d.fefo_rule) + '</div>' + table([
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span><br><span class="iop-muted">' + esc(r.sku || '') + '</span>'; } },
					{ l: 'Lote', f: function (r) { return esc(r.lot_number || r.stock_id) + (Number(r.in_quarantine) ? '<br>' + pill('high', 'En cuarentena') : ''); } },
					{ l: 'Ubicación', f: function (r) { return esc(r.location) + '<br><span class="iop-muted">' + esc(r.branch) + '</span>'; } },
					{ l: 'Vence', f: function (r) { return esc(r.best_before_date); } },
					{ l: 'Días', num: 1, f: function (r) { return Number(r.days_left) < 0 ? '<span class="iop-neg">' + n(r.days_left, 0) + '</span>' : n(r.days_left, 0); } },
					{ l: 'Unidades', num: 1, f: function (r) { return n(r.amount); } },
					{ l: 'Valor', num: 1, f: function (r) { return money(r.value); } },
					{ l: 'Estado', f: function (r) { return pill(r.state, LS[r.state]); } }
				], d.rows, { empty: 'Sin lotes que venzan en el período' }));
			}).catch(function (x) { $('#iop-lot-body').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
		},
		reservations: function () {
			loading('Cargando reservas');
			cached('reservations', function () { return get('inventory-ops/reservations', bq()); }).then(function (d) {
				var ST = { confirmed: 'Confirmado', picking: 'En preparación', picked: 'Preparado', packed: 'Empacado', dispatching: 'Despachando' };
				paint('<div class="iop-note">' + esc(d.rule) + ' Total reservado: <span class="iop-strong">' + n(d.total_units) + '</span> u.</div>' + table([
					{ l: 'Pedido', f: function (r) { return '<span class="iop-strong">' + esc(r.code) + '</span><br><span class="iop-muted">' + esc(r.customer || '') + '</span>'; } },
					{ l: 'Sucursal', f: function (r) { return esc(r.branch); } },
					{ l: 'Estado', f: function (r) { return pill('warning', ST[r.status] || r.status); } },
					{ l: 'Reservado', num: 1, f: function (r) { return n(r.units); } },
					{ l: 'Antigüedad', num: 1, f: function (r) { return r.age_hours === null ? '—' : n(r.age_hours, 0) + ' h'; } },
					{ l: 'Productos', f: function (r) { return r.lines.map(function (l) { return esc(l.product) + ' <span class="iop-muted">× ' + n(l.reserved) + '</span>'; }).join('<br>'); } }
				], d.orders, { click: true, empty: 'Sin pedidos con reserva' }));
				$('#iop-panel tr.is-click').on('click', function () { location.href = U('/dispatch'); });
			}).catch(fail);
		},
		audit: function () {
			var bk = $('#iop-au-bk').val() || '', from = $('#iop-au-from').val() || isoDays(-7), to = $('#iop-au-to').val() || isoDays(0);
			var BK = { receptions: 'Recepción (Compras)', sales: 'Venta (POS)', dispatches: 'Despacho', waste: 'Merma', returns: 'Devolución', transfers_in: 'Traslado entrada', transfers_out: 'Traslado salida', transit: 'En tránsito', internal: 'Movimiento interno', adjustments: 'Ajuste' };
			paint('<div class="iop-toolbar"><select id="iop-au-bk"><option value="">Todos los orígenes</option>' + Object.keys(BK).map(function (k) { return '<option value="' + k + '"' + (k === bk ? ' selected' : '') + '>' + BK[k] + '</option>'; }).join('') + '</select>' +
				'<input type="date" id="iop-au-from" value="' + from + '"><input type="date" id="iop-au-to" value="' + to + '"><button class="iop-btn is-primary" id="iop-au-go">Filtrar</button></div><div id="iop-au-body"><div class="iop-loading">Cargando movimientos</div></div>');
			$('#iop-au-go').on('click', TABS.audit);
			get('inventory-ops/audit', bq({ bucket: bk, from: from, to: to, limit: 300 })).then(function (d) {
				var mods = Object.keys(d.by_module).map(function (m) { return pill('ok', m + ': ' + d.by_module[m]); }).join(' ');
				$('#iop-au-body').html('<div class="iop-note">' + esc(d.note) + '</div><div class="iop-toolbar">' + mods + '</div>' + table([
					{ l: 'Fecha', f: function (r) { return esc(r.recorded_at) + '<br><span class="iop-muted">' + esc(r.movement_code) + '</span>'; } },
					{ l: 'Origen', f: function (r) { return esc(r.movement_label) + '<br><span class="iop-muted">' + esc(r.source_module) + '</span>'; } },
					{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.product) + '</span><br><span class="iop-muted">' + esc(r.location_name || '') + ' · ' + esc(r.branch) + '</span>'; } },
					{ l: 'Cantidad', num: 1, f: function (r) { return signed(r.amount); } },
					{ l: 'Usuario', f: function (r) { return esc(r.user_name || '—'); } },
					{ l: 'Control', f: function (r) { return (Number(r.undone) ? pill('high', 'Deshecho') + ' ' : '') + (Number(r.sealed) ? pill('ok', 'Sellado') : pill('warning', 'Sin sello')); } }
				], d.rows, { click: true, empty: 'Sin movimientos en el período' }));
				$('#iop-au-body tr.is-click').on('click', function () { var r = d.rows[$(this).data('i')]; openKardex(r.product_id, r.product); });
			}).catch(function (x) { $('#iop-au-body').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
		}
	};

	function paint(html) { $('#iop-panel').html(html); }
	function isoDays(d) { var t = new Date(Date.now() + d * 86400000); return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0'); }
	function act($b, url, body) {
		busy($b, true);
		return post(url, body).then(function (r) { toastr.success((r && r.message) || 'Listo'); refresh(); }).catch(function (x) { busy($b, false); toastr.error(err(x)); });
	}
	function refresh() { invalidate(); loadSummary(); TABS[tab](); }
	function select(t) {
		tab = t;
		$('.iop-tab').attr('aria-selected', 'false').filter('[data-tab=' + t + ']').attr('aria-selected', 'true');
		TABS[t]();
	}

	/* ------------------------------------------------------------ kardex */
	function openKardex(productId, name) {
		kardexProduct = { id: productId, name: name };
		select('kardex');
		$root[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
	}
	function findKardex() {
		var q = ($('#iop-kx-q').val() || '').trim();
		if (!q) return;
		if (kardexProduct && q === kardexProduct.name) { renderKardex(); return; }
		$('#iop-kx-body').html('<div class="iop-loading">Buscando producto</div>');
		get('inventory-ops/scan/' + encodeURIComponent(q), bq()).then(function (s) {
			if (s.found) { kardexProduct = { id: s.product.id, name: s.product.name }; $('#iop-kx-q').val(s.product.name); renderKardex(); return; }
			return get('inventory-ops/positions', bq({ q: q, limit: 15 })).then(function (d) {
				var seen = {}, opts = d.rows.filter(function (r) { if (seen[r.product_id]) return false; seen[r.product_id] = 1; return true; });
				if (!opts.length) { $('#iop-kx-body').html('<div class="iop-empty">Sin coincidencias</div>'); return; }
				$('#iop-kx-body').html('<div class="iop-note">Elija el producto</div>' + table([{ l: 'Producto', f: function (r) { return '<span class="iop-strong">' + esc(r.name) + '</span> <span class="iop-muted">' + esc(r.sku || '') + '</span>'; } }], opts, { click: true }));
				$('#iop-kx-body tr.is-click').on('click', function () { var r = opts[$(this).data('i')]; kardexProduct = { id: r.product_id, name: r.name }; $('#iop-kx-q').val(r.name); renderKardex(); });
			});
		}).catch(function (x) { $('#iop-kx-body').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
	}
	function renderKardex() {
		$('#iop-kx-body').html('<div class="iop-loading">Cargando kardex</div>');
		get('inventory-ops/kardex', bq({ product_id: kardexProduct.id, from: $('#iop-kx-from').val(), to: $('#iop-kx-to').val() })).then(function (d) {
			var head = '<div class="iop-grid2"><div class="iop-box"><h3>' + esc(d.product ? d.product.name : '') + '</h3>' +
				'<span class="iop-muted">Saldo inicial</span> <span class="iop-strong">' + n(d.opening_balance) + '</span> · <span class="iop-muted">Entradas</span> <span class="iop-pos">+' + n(d.entries) + '</span> · <span class="iop-muted">Salidas</span> <span class="iop-neg">−' + n(d.exits) + '</span> · <span class="iop-muted">Saldo final</span> <span class="iop-strong">' + n(d.closing_balance) + '</span>' +
				(d.truncated ? '<div class="iop-note is-warn">Período con más movimientos de los mostrados: acorte el rango.</div>' : '') +
				(d.reconciliation_note ? '<div class="iop-note is-warn">' + esc(d.reconciliation_note) + '</div>' : '') + '</div></div>';
			$('#iop-kx-body').html(head + table([
				{ l: 'Fecha', f: function (r) { return esc(r.recorded_at); } },
				{ l: 'Movimiento', f: function (r) { return esc(r.movement_label) + '<br><span class="iop-muted">' + esc(r.source_module) + ' · ' + esc(r.operation_ref || '') + '</span>'; } },
				{ l: 'Ubicación', f: function (r) { return esc(r.location_name || '') + '<br><span class="iop-muted">' + esc(r.branch_name) + '</span>'; } },
				{ l: 'Lote', f: function (r) { return esc(r.lot_number || '') + (r.best_before_date && r.best_before_date !== '2999-12-31' ? '<br><span class="iop-muted">vence ' + esc(r.best_before_date) + '</span>' : ''); } },
				{ l: 'Cantidad', num: 1, f: function (r) { return Number(r.undone) ? '<s>' + n(r.amount) + '</s> ' + pill('high', 'Deshecho') : signed(r.amount); } },
				{ l: 'Saldo', num: 1, f: function (r) { return r.balance_after === null ? '—' : n(r.balance_after); } },
				{ l: 'Usuario', f: function (r) { return esc(r.user_name || '—'); } }
			], d.rows, { empty: 'Sin movimientos en el período' }));
		}).catch(function (x) { $('#iop-kx-body').html('<div class="iop-error">' + esc(err(x)) + '</div>'); });
	}

	/* ------------------------------------------------------------ escáner */
	function scan(code) {
		var $r = $('#iop-scan-result');
		$r.prop('hidden', false).html('<div class="iop-loading">Buscando ' + esc(code) + '</div>');
		get('inventory-ops/scan/' + encodeURIComponent(code), bq()).then(function (s) {
			if (!s.found) { $r.html('<div class="iop-scan-head"><span class="iop-neg">' + esc(s.message) + ': ' + esc(code) + '</span><button class="iop-btn" id="iop-scan-close">Cerrar</button></div>'); return; }
			var p = s.position, prod = s.product;
			var bLoc = s.locations.filter(function (l) { return !branch() || String(l.branch_id) === branch(); });
			$r.html('<div class="iop-scan-head"><div><span class="iop-strong" style="font-size:15px">' + esc(prod.name) + '</span><br><span class="iop-muted">' + esc(prod.sku || '') + ' · ' + esc(code) + ' (' + esc(s.matched_by) + ')</span></div><button class="iop-btn" id="iop-scan-close">Cerrar</button></div>' +
				'<div class="iop-scan-nums">' + [['Físico', p.physical], ['Reservado', p.reserved], ['Bloqueado', p.blocked], ['Disponible', p.available], ['Bodega', p.bodega], ['Sala', p.sala]].map(function (x) { return '<div class="iop-scan-num"><span>' + x[0] + '</span><b>' + n(x[1]) + '</b></div>'; }).join('') + '</div>' +
				(p.shortfall > 0 ? '<div class="iop-note is-warn">Reservado y bloqueado superan al físico en ' + n(p.shortfall) + ' u.</div>' : '') +
				'<div class="iop-note">' + bLoc.slice(0, 6).map(function (l) { return esc(l.name) + ' (' + esc(l.branch) + '): <span class="iop-strong">' + n(l.amount) + '</span>' + (l.lot_number ? ' · ' + esc(l.lot_number) : ''); }).join('<br>') + '</div>' +
				'<div class="iop-scan-actions"><button class="iop-btn is-primary" id="iop-scan-kx">Kardex</button>' +
				(CAN.count ? (s.pending_count_id ? '<button class="iop-btn" id="iop-scan-goc">Contar (conteo pendiente)</button>' : (branch() && bLoc.length ? '<button class="iop-btn" id="iop-scan-cnt">Programar conteo selectivo</button>' : '')) : '') + '</div>');
			$('#iop-scan-kx').on('click', function () { openKardex(prod.id, prod.name); });
			$('#iop-scan-goc').on('click', function () { select('counts'); });
			$('#iop-scan-cnt').on('click', function () {
				act($(this), 'stock/cycle-counts', { product_id: prod.id, branch_id: branch(), location_id: bLoc[0].id, notes: 'Conteo selectivo por escaneo' });
			});
		}).catch(function (x) { $r.html('<div class="iop-error">' + esc(err(x)) + '</div>'); }).then(function () {
			$('#iop-scan-close').on('click', function () { $r.prop('hidden', true).empty(); $('#iop-scan-input').focus(); });
		});
	}
	$('#iop-scan-form').on('submit', function (e) {
		e.preventDefault();
		var code = ($('#iop-scan-input').val() || '').trim();
		if (!code) return;
		$('#iop-scan-input').val('').focus();
		scan(code);
	});

	/* ------------------------------------------------------------ eventos */
	$root.on('click', '.iop-tab', function () { select($(this).data('tab')); });
	$root.on('click', '.iop-step', function () {
		var h = String($(this).data('href') || '');
		if (h.charAt(0) === '#') select(h.slice(1));
		else if (h) location.href = U(h);
	});
	$root.on('change', '#iop-pos-state, #iop-lot-days', function () { TABS[tab](); });
	var typing;
	$root.on('input', '#iop-pos-q, #iop-lot-q', function () { clearTimeout(typing); typing = setTimeout(function () { var el = document.activeElement && document.activeElement.id; TABS[tab](); if (el) { var $e = $('#' + el); $e.focus(); var v = $e.val(); $e[0].setSelectionRange(v.length, v.length); } }, 350); });
	$('#dsm-filter-branch').on('change', function () { loadSummary(); TABS[tab](); });

	// Carga diferida: el resumen se pide cuando la sección se acerca a la vista (no compite con el tablero principal)
	var started = false;
	function start() {
		if (started) return;
		started = true;
		loadSummary();
		var hash = location.hash.replace('#inv-ops-', '');
		select(TABS[hash] ? hash : 'positions');
	}
	if (location.hash.indexOf('#inv-ops') === 0) { start(); $root[0].scrollIntoView(); }
	else if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (es) { if (es.some(function (e) { return e.isIntersecting; })) { io.disconnect(); start(); } }, { rootMargin: '400px' });
		io.observe($root[0]);
	}
	else start();
})();
