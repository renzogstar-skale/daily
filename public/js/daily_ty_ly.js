/**
 * DAILY — Comparativo de Ventas TY vs. LY (Rendimiento por Departamento)
 * Orden, filtro de categoría y desglose por departamento sin recargar la página.
 */
(function () {
	'use strict';

	var root, body, wrap, data, filters, sortMode = 'sales', openKey = null, drillCache = {}, reqSeq = 0;

	document.addEventListener('DOMContentLoaded', function () {
		root = document.getElementById('tylySection');
		if (!root) return;
		body = document.getElementById('tylyBody');
		wrap = document.getElementById('tylyTableWrap');
		filters = JSON.parse(root.getAttribute('data-filters') || '{}');
		try { data = JSON.parse(document.getElementById('tylyData').textContent); } catch (e) { data = null; }

		root.querySelectorAll('[data-tyly-sort]').forEach(function (btn) {
			btn.addEventListener('click', function () { setSort(btn.getAttribute('data-tyly-sort')); });
		});
		document.getElementById('tylyCategory').addEventListener('change', function () { changeCategory(this.value); });
		body.addEventListener('click', onRowActivate);
		body.addEventListener('keydown', function (e) {
			if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('tyly-row')) { e.preventDefault(); onRowActivate(e); }
		});
	});

	/* ---------- formato ---------- */
	function money(v) {
		var n = Math.round(Number(v || 0));
		return (n < 0 ? '−$' : '$') + Math.abs(n).toLocaleString('en-US');
	}
	function pct(v) {
		if (v === null || v === undefined) return 'Nuevo';
		return (v > 0 ? '+' : v < 0 ? '−' : '') + Math.abs(v).toFixed(1) + ' %';
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function icons() {
		if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons();
	}
	function query(extra) {
		var p = new URLSearchParams();
		Object.keys(filters).forEach(function (k) { if (filters[k] !== null && filters[k] !== '') p.set(k, filters[k]); });
		Object.keys(extra || {}).forEach(function (k) { p.set(k, extra[k]); });
		return p.toString();
	}

	/* ---------- orden ---------- */
	function setSort(mode) {
		sortMode = mode;
		root.querySelectorAll('[data-tyly-sort]').forEach(function (b) {
			var on = b.getAttribute('data-tyly-sort') === mode;
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		if (data) renderRows();
	}
	function sorted() {
		var rows = (data.departments || []).slice();
		var v = function (r) { return r.var_pct === null ? Infinity : r.var_pct; };
		if (sortMode === 'growth') rows.sort(function (a, b) { return v(b) - v(a); });
		else if (sortMode === 'drop') rows.sort(function (a, b) { return a.diff - b.diff; });
		else rows.sort(function (a, b) { return b.ty - a.ty; });
		return rows;
	}

	/* ---------- tabla ---------- */
	function renderRows() {
		var rows = sorted();
		if (!rows.length) {
			body.innerHTML = stateRow('inbox', 'Sin ventas en el período', 'Amplíe las fechas o quite filtros de sucursal, departamento o categoría.');
			icons();
			return;
		}
		var max = 1;
		rows.forEach(function (r) { max = Math.max(max, r.ty, r.ly); });
		body.innerHTML = rows.map(function (d) {
			var down = d.diff < 0, flags = '';
			if (d.stockouts > 0) flags += '<span class="tyly-flag is-out" title="Productos vendidos en el período sin existencia disponible"><i data-lucide="package-x"></i>' + d.stockouts + ' agotados</span>';
			if (d.low_stock > 0) flags += '<span class="tyly-flag is-low" title="Productos bajo el mínimo de inventario"><i data-lucide="package-minus"></i>' + d.low_stock + ' bajo mín.</span>';
			if (d.returns_amount > 0) flags += '<span class="tyly-flag is-ret" title="Devoluciones de clientes en el período"><i data-lucide="undo-2"></i>' + money(d.returns_amount) + ' dev.</span>';
			if (!flags) flags = '<span class="tyly-flag is-none">Sin alertas</span>';
			var open = openKey === d.key;
			return '<tr class="tyly-row ' + (down ? 'is-down' : 'is-up') + (open ? ' is-open' : '') + '" data-key="' + esc(d.key) + '" tabindex="0" role="button" aria-expanded="' + open + '" aria-label="Ver desglose de ' + esc(d.name) + '">'
				+ '<td><span class="tyly-dept"><span class="tyly-dept-icon" style="--dept:' + esc(d.color) + ';"><i data-lucide="' + esc(d.icon) + '"></i></span>'
				+ '<span class="tyly-dept-name">' + esc(d.name) + '<small>' + d.share_pct.toFixed(1) + ' % de la venta</small></span></span></td>'
				+ '<td class="text-right tyly-num">' + money(d.ty) + '</td>'
				+ '<td class="text-right tyly-num tyly-muted">' + money(d.ly) + '</td>'
				+ '<td class="text-right tyly-num tyly-diff">' + money(d.diff) + '</td>'
				+ '<td class="text-right"><span class="tyly-pill ' + (down ? 'is-down' : 'is-up') + '"><i data-lucide="' + (down ? 'arrow-down-right' : 'arrow-up-right') + '"></i>' + pct(d.var_pct) + '</span></td>'
				+ '<td class="tyly-col-bar"><span class="tyly-bars" aria-hidden="true"><span class="tyly-bar is-ty" style="width:' + (d.ty / max * 100).toFixed(1) + '%"></span><span class="tyly-bar is-ly" style="width:' + (d.ly / max * 100).toFixed(1) + '%"></span></span></td>'
				+ '<td class="tyly-col-signals">' + flags + '</td>'
				+ '<td class="tyly-col-chevron"><i data-lucide="chevron-down"></i></td></tr>'
				+ (open ? drillRowHtml(d.key) : '');
		}).join('');
		icons();
	}

	function renderSummary() {
		var t = data.totals || {};
		var set = function (k, txt, cls) {
			var el = root.querySelector('[data-tyly-total="' + k + '"]');
			if (!el) return;
			if (el.firstChild && el.firstChild.nodeType === 3) el.firstChild.nodeValue = txt + ' ';
			else el.textContent = txt;
			if (cls !== undefined) { el.classList.toggle('is-down', cls); el.classList.toggle('is-up', !cls); }
		};
		set('ty', money(t.ty));
		set('ly', money(t.ly));
		set('diff', money(t.diff), t.diff < 0);
		set('var', pct(t.var_pct));
		set('declining', String(t.declining_count || 0));
		set('gap', money(t.declining_gap));
	}

	function stateRow(icon, title, text, retry) {
		return '<tr class="tyly-state-row"><td colspan="8"><div class="tyly-state' + (retry ? ' is-error' : '') + '"><i data-lucide="' + icon + '"></i><strong>' + esc(title) + '</strong><span>' + esc(text) + '</span>'
			+ (retry ? '<button type="button" class="tyly-retry" data-tyly-retry="' + esc(retry) + '"><i data-lucide="rotate-cw"></i>Reintentar</button>' : '') + '</div></td></tr>';
	}

	/* ---------- categoría (filtro compartido) ---------- */
	function changeCategory(value) {
		filters.group_id = value === '' ? null : parseInt(value, 10);
		var url = new URL(window.location.href);
		if (value === '') url.searchParams.delete('group_id'); else url.searchParams.set('group_id', value);
		history.replaceState(history.state, '', url.toString());
		openKey = null;
		drillCache = {};
		load();
	}

	function load() {
		var seq = ++reqSeq;
		wrap.setAttribute('aria-busy', 'true');
		wrap.classList.add('is-loading');
		fetch(root.getAttribute('data-api') + '?' + query(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
			.then(function (res) {
				if (seq !== reqSeq) return;
				data = res;
				renderSummary();
				renderRows();
			})
			.catch(function () {
				if (seq !== reqSeq) return;
				body.innerHTML = stateRow('cloud-off', 'No se pudo actualizar el comparativo', 'Revise la conexión e intente de nuevo.', 'list');
				icons();
			})
			.then(function () {
				if (seq !== reqSeq) return;
				wrap.classList.remove('is-loading');
				wrap.setAttribute('aria-busy', 'false');
			});
	}

	/* ---------- desglose ---------- */
	function onRowActivate(e) {
		var retry = e.target.closest('[data-tyly-retry]');
		if (retry) {
			if (retry.getAttribute('data-tyly-retry') === 'list') load();
			else { delete drillCache[openKey]; renderRows(); fetchDrill(openKey); }
			return;
		}
		if (e.target.closest('.tyly-drill-row')) return;
		var row = e.target.closest('.tyly-row');
		if (!row) return;
		var key = row.getAttribute('data-key');
		openKey = openKey === key ? null : key;
		renderRows();
		if (openKey && !drillCache[openKey]) fetchDrill(openKey);
		if (openKey) {
			var opened = body.querySelector('.tyly-row[data-key="' + openKey + '"]');
			if (opened) opened.focus({ preventScroll: true });
		}
	}

	function drillRowHtml(key) {
		var c = drillCache[key];
		var inner;
		if (!c || c.loading) {
			inner = '<div class="tyly-drill is-loading" aria-busy="true"><div class="tyly-skel"></div><div class="tyly-skel"></div><div class="tyly-skel is-short"></div><span class="sr-only">Cargando desglose…</span></div>';
		} else if (c.error) {
			inner = '<div class="tyly-drill">' + '<table class="tyly-inner"><tbody>' + stateRow('cloud-off', 'No se pudo cargar el desglose', 'Revise la conexión e intente de nuevo.', 'drill') + '</tbody></table></div>';
		} else {
			inner = drillHtml(c.data);
		}
		return '<tr class="tyly-drill-row"><td colspan="8">' + inner + '</td></tr>';
	}

	function fetchDrill(key) {
		drillCache[key] = { loading: true };
		var seq = reqSeq;
		fetch(root.getAttribute('data-api') + '?' + query({ drill: key }), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
			.then(function (res) { if (seq === reqSeq) drillCache[key] = { data: res.drill }; })
			.catch(function () { if (seq === reqSeq) drillCache[key] = { error: true }; })
			.then(function () { if (seq === reqSeq && openKey === key) renderRows(); });
	}

	function linkWith(base, extra) {
		var p = new URLSearchParams(query(extra));
		['start_date', 'end_date'].forEach(function (k) { if (base.indexOf('stockouts') > -1) p.delete(k); });
		return base + '?' + p.toString();
	}

	function drillHtml(d) {
		if (!d) return '';
		var s = d.signals || {};
		var stockUrl = linkWith(root.getAttribute('data-stockouts-url'), { department: d.key });
		var cards = ''
			+ signalCard('package-x', 'is-out', 'Agotados en caída', s.stockout_products + ' productos', money(s.stockout_gap) + ' vs. LY', stockUrl, 'Ver agotados')
			+ signalCard('package-minus', 'is-low', 'Bajo mínimo en caída', s.low_products + ' productos', money(s.low_gap) + ' vs. LY', stockUrl, 'Revisar reposición')
			+ signalCard('undo-2', 'is-ret', 'Devoluciones del período', money(s.returns_amount), s.returns_qty + ' unidades', root.getAttribute('data-returns-url'), 'Ver devoluciones');

		var prodRows = (d.products || []).map(function (p) {
			var down = p.diff < 0;
			var st = p.stock_state === 'out' ? '<span class="tyly-flag is-out">Agotado</span>' : p.stock_state === 'low' ? '<span class="tyly-flag is-low">Bajo mín.</span>' : '<span class="tyly-stock-ok">' + Number(p.on_hand).toLocaleString('en-US') + '</span>';
			var href = linkWith(root.getAttribute('data-products-url'), { tab: 'productos', product: p.sku || p.id });
			return '<tr><td><a class="tyly-prod" href="' + esc(href) + '">' + esc(p.name) + '</a><small class="tyly-sku">' + esc(p.sku || '—') + '</small></td>'
				+ '<td class="text-right tyly-num">' + money(p.ty) + '</td>'
				+ '<td class="text-right tyly-num tyly-muted">' + money(p.ly) + '</td>'
				+ '<td class="text-right"><span class="tyly-pill is-sm ' + (down ? 'is-down' : 'is-up') + '">' + pct(p.var_pct) + '</span></td>'
				+ '<td class="text-right">' + st + '</td>'
				+ '<td class="text-right tyly-num">' + (p.returns_qty > 0 ? p.returns_qty : '—') + '</td></tr>';
		}).join('');
		var branchRows = (d.branches || []).map(function (b) {
			var down = b.diff < 0;
			return '<tr><td>' + esc(b.name) + '</td><td class="text-right tyly-num">' + money(b.ty) + '</td><td class="text-right tyly-num tyly-muted">' + money(b.ly) + '</td>'
				+ '<td class="text-right tyly-num tyly-diff ' + (down ? 'is-down' : 'is-up') + '">' + money(b.diff) + '</td>'
				+ '<td class="text-right"><span class="tyly-pill is-sm ' + (down ? 'is-down' : 'is-up') + '">' + pct(b.var_pct) + '</span></td></tr>';
		}).join('');
		var empty = '<tr><td colspan="6" class="tyly-inner-empty">Sin ventas en el período</td></tr>';

		return '<div class="tyly-drill">'
			+ '<div class="tyly-signals">' + cards + '</div>'
			+ '<div class="tyly-drill-grid">'
			+ '<div class="tyly-panel"><h3>Productos y SKU <small>' + d.falling_products + ' de ' + d.products_total + ' bajo LY · mayores caídas primero</small></h3>'
			+ '<div class="tyly-scroll"><table class="tyly-inner"><thead><tr><th>Producto / SKU</th><th class="text-right">TY</th><th class="text-right">LY</th><th class="text-right">Var.</th><th class="text-right">Existencia</th><th class="text-right">Dev.</th></tr></thead><tbody>' + (prodRows || empty) + '</tbody></table></div></div>'
			+ '<div class="tyly-panel"><h3>Sucursales <small>mayores caídas primero</small></h3>'
			+ '<div class="tyly-scroll"><table class="tyly-inner"><thead><tr><th>Sucursal</th><th class="text-right">TY</th><th class="text-right">LY</th><th class="text-right">Diferencia</th><th class="text-right">Var.</th></tr></thead><tbody>' + (branchRows || empty) + '</tbody></table></div></div>'
			+ '</div></div>';
	}

	function signalCard(icon, cls, label, value, sub, href, cta) {
		return '<a class="tyly-signal ' + cls + '" href="' + esc(href) + '"><span class="tyly-signal-icon"><i data-lucide="' + icon + '"></i></span>'
			+ '<span class="tyly-signal-body"><span class="tyly-signal-label">' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(sub) + '</small></span>'
			+ '<span class="tyly-signal-cta">' + esc(cta) + '<i data-lucide="arrow-right"></i></span></a>';
	}
})();
