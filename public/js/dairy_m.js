/*
 * DAIRY Mobile — aplicación de una página en /m para empleado, supervisor y gerente (Etapa 34: Inicio · Tareas ·
 * Escanear · Alertas · Menú — Menú reúne Operaciones y Perfil, que conservan sus rutas #/ops y #/profile; el rol se deriva de los permisos y cada endpoint los valida en el servidor).
 * Navegación progresiva General → Departamento → Producto/SKU → Registro → Acción, con migas, regreso sin perder
 * filtros ni posición (estado en el hash + scroll por ruta) y búsqueda global.
 * Lecturas: /api/m/*. Acciones: APIs existentes de cada módulo con X-Idempotency-Key (un doble toque o un reintento
 * nunca ejecuta dos veces). No se simula éxito: cada mensaje viene del servidor.
 */
(function () {
	'use strict';
	var C = window.DAIRY_M, API = C.base + '/api';
	var $main = document.getElementById('m-main');
	var S = { me: null, lookups: null, branch: null, period: 'month', inbox: [], cache: {}, nav: 0, ctl: null };
	var PERIODS = [['today', 'Hoy'], ['7d', '7 días'], ['month', 'Mes'], ['30d', '30 días'], ['year', 'Año']];
	var BUCKETS = { operativa: ['Operativa', 'op'], comercial: ['Comercial', 'com'], por_clasificar: ['Por clasificar', 'unk'] };

	/* ---------------------------------------------------------------- utilidades */
	function h(s) { return s == null ? '' : String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function store(k, v) { try { if (v === undefined) { return JSON.parse(localStorage.getItem('dairyM:' + k)); } localStorage.setItem('dairyM:' + k, JSON.stringify(v)); } catch (e) { return null; } }
	function n(v) { return Number(v) || 0; }
	var fmtCache = {};
	function money(v, compact) {
		var cur = (S.me && S.me.currency) || 'USD', key = cur + (compact ? 'c' : '');
		if (!fmtCache[key]) {
			try { fmtCache[key] = new Intl.NumberFormat('es', { style: 'currency', currency: cur, maximumFractionDigits: compact ? 1 : 2, minimumFractionDigits: compact ? 0 : 2, notation: compact ? 'compact' : 'standard' }); }
			catch (e) { fmtCache[key] = { format: function (x) { return '$' + Number(x).toFixed(2); } }; }
		}
		return fmtCache[key].format(n(v));
	}
	function m(v) { return money(v, Math.abs(n(v)) >= 100000); }
	function qty(v) { var x = n(v); return (Math.round(x * 100) / 100).toLocaleString('es'); }
	function pct(v, d) { return v == null ? '—' : n(v).toLocaleString('es', { maximumFractionDigits: d == null ? 1 : d }) + ' %'; }
	function dt(s) { if (!s) { return ''; } var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleString('es', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); }
	function day(s) { if (!s) { return ''; } var d = new Date(String(s).slice(0, 10) + 'T12:00'); return isNaN(d) ? s : d.toLocaleDateString('es', { day: '2-digit', month: 'short' }); }
	function delta(v, bad) {
		if (v == null) { return '<span class="m-tag muted">sin período previo</span>'; }
		var up = v >= 0;
		return '<span class="m-delta ' + (up ? 'up' : 'down') + (bad ? ' bad' : '') + '"><i class="fa-solid fa-arrow-' + (up ? 'up' : 'down') + '"></i>' + pct(Math.abs(v)) + '</span>';
	}
	function can(p) { var ps = (S.me && S.me.permissions) || []; return ps.indexOf('ADMIN') >= 0 || [].concat(p).some(function (x) { return ps.indexOf(x) >= 0; }); }
	var P = {
		sales: ['SALES_VIEW', 'KPIS_VIEW'], stock: ['STOCK'], waste: ['WASTE_REPORT', 'WASTE_APPROVE', 'KPIS_VIEW'],
		wasteReport: ['WASTE_REPORT'], count: ['STOCK_INVENTORY'], move: ['STOCK_TRANSFER'], receive: ['STOCK_PURCHASE']
	};
	function key() { return (window.crypto && crypto.randomUUID) ? 'm-' + crypto.randomUUID() : 'm-' + Date.now().toString(36) + Math.random().toString(36).slice(2); }

	/* ---------------------------------------------------------------- red */
	var ctrl = null;
	function qs(o) {
		var p = [];
		Object.keys(o || {}).forEach(function (k) { if (o[k] !== null && o[k] !== undefined && o[k] !== '') { p.push(encodeURIComponent(k) + '=' + encodeURIComponent(o[k])); } });
		return p.length ? '?' + p.join('&') : '';
	}
	function scope(extra) {
		var o = { period: S.period };
		if (S.branch) { o.branch_id = S.branch; }
		return Object.assign(o, extra || {});
	}
	async function errorOf(res) {
		var msg = 'Error ' + res.status;
		try { var j = await res.json(); msg = j.error_message || j.message || msg; } catch (e) { }
		if (res.status === 401) { location.href = C.base + '/login?returnto=' + encodeURIComponent('/m' + location.hash); }
		var e = new Error(msg); e.status = res.status; return e;
	}
	// Lecturas que se guardan en el dispositivo para trabajar sin conexión (por usuario; se muestran como "sin conexión")
	var PERSIST = /\/m\/(me|lookups|today|tasks|schedule|search|sku\/\d+)(\?|$)/;
	async function get(path, params, opts) {
		var url = API + path + qs(params);
		var c = S.cache[url];
		if (c && Date.now() - c.t < 60000 && !(opts && opts.fresh)) { return c.d; }
		var res;
		try { res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: opts && opts.signal }); }
		catch (e) {
			var saved = e.name !== 'AbortError' && PERSIST.test(path) ? store('gc:' + C.userId + ':' + url) : null;
			if (saved) { S.stale = true; netBanner(); return saved.d; }
			throw e.name === 'AbortError' ? e : new Error(navigator.onLine === false ? 'Sin conexión y sin datos guardados en este dispositivo' : 'No se pudo conectar con el servidor');
		}
		if (!res.ok) { throw await errorOf(res); }
		var d = await res.json();
		S.cache[url] = { t: Date.now(), d: d };
		if (PERSIST.test(path)) { persist(url, d); }
		return d;
	}
	function persist(url, d) {
		var k = 'gc:' + C.userId + ':' + url, idx = store('gcidx:' + C.userId) || [];
		idx = idx.filter(function (x) { return x !== k; }); idx.unshift(k);
		idx.slice(60).forEach(function (x) { try { localStorage.removeItem('dairyM:' + x); } catch (e) { } });
		store(k, { t: Date.now(), d: d }); store('gcidx:' + C.userId, idx.slice(0, 60));
	}
	// POST con clave de idempotencia fija por botón/formulario: el doble toque reenvía la misma clave
	// queueLabel: la operación puede guardarse sin conexión y enviarse al reconectar con la MISMA clave (idempotente)
	async function post(path, body, btn, queueLabel) {
		if (navigator.onLine === false) {
			if (queueLabel) { return enqueue(path, body, btn, queueLabel); }
			throw new Error('Sin conexión: la acción no se envió');
		}
		if (btn) {
			if (btn.classList.contains('busy')) { return null; }
			btn.dataset.key = btn.dataset.key || key();
			btn.classList.add('busy');
		}
		try {
			var res = await fetch(API + path, {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Idempotency-Key': btn ? btn.dataset.key : key() },
				body: JSON.stringify(body || {})
			});
			if (!res.ok) { throw await errorOf(res); }
			var d = await res.json().catch(function () { return {}; });
			S.cache = {};
			S.counts = null;
			if (btn) { delete btn.dataset.key; }
			return d || {};
		} catch (e) {
			// Corte de red a mitad: se encola con la misma clave (si el servidor ya la ejecutó, devuelve la respuesta guardada)
			if (!e.status && queueLabel && e.name === 'TypeError') { if (btn) { btn.classList.remove('busy'); } return enqueue(path, body, btn, queueLabel); }
			// Fallo de validación: la clave se libera en el servidor; se genera otra al reintentar
			if (btn && e.status && e.status !== 409) { delete btn.dataset.key; }
			throw e;
		} finally { if (btn) { btn.classList.remove('busy'); } }
	}
	async function upload(file) {
		var fd = new FormData(); fd.append('photo', file);
		var res = await fetch(API + '/waste/upload-photo', { method: 'POST', credentials: 'same-origin', body: fd });
		if (!res.ok) { throw await errorOf(res); }
		return (await res.json()).relative_url;
	}

	/* ---------------------------------------------------------------- UI base */
	var toastT;
	function toast(msg, tone) {
		var t = document.getElementById('m-toast');
		t.className = 'm-toast' + (tone ? ' ' + tone : ''); t.textContent = msg; t.hidden = false;
		clearTimeout(toastT); toastT = setTimeout(function () { t.hidden = true; }, tone === 'err' ? 6000 : 3500);
	}
	var sheetClose = null;
	function sheet(title, html, onOpen) {
		var w = document.getElementById('m-sheet');
		document.getElementById('m-sheet-title').textContent = title;
		var body = document.getElementById('m-sheet-body');
		body.innerHTML = html; w.hidden = false;
		var prevFocus = document.activeElement;
		sheetClose = function () { w.hidden = true; body.innerHTML = ''; sheetClose = null; if (prevFocus && prevFocus.focus) { prevFocus.focus(); } };
		if (onOpen) { onOpen(body, sheetClose); }
		var f = body.querySelector('input,select,textarea,button'); if (f && f.tagName !== 'BUTTON') { f.focus(); }
		return sheetClose;
	}
	document.getElementById('m-sheet').addEventListener('click', function (e) { if (e.target.closest('[data-close]') && sheetClose) { sheetClose(); } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheetClose) { sheetClose(); } });

	function confirmSheet(title, text, label, tone, field) {
		return new Promise(function (resolve) {
			var done = false;
			sheet(title, '<p>' + h(text) + '</p>' + (field ? '<div class="m-field"><label for="m-cf">' + h(field.label) + '</label>' + (field.type === 'select' ? '<select id="m-cf" class="m-input">' + field.options + '</select>' : (field.type === 'date' ? '<input type="date" id="m-cf" class="m-input" value="' + h(field.value || '') + '">' : '<textarea id="m-cf" class="m-input" placeholder="' + h(field.placeholder || '') + '"></textarea>')) + '<div class="m-err" id="m-cf-err" hidden></div></div>' : '')
				+ '<div class="m-grid" style="grid-template-columns:1fr 1fr"><button type="button" class="m-btn" data-close>Cancelar</button><button type="button" class="m-btn ' + (tone || 'primary') + '" id="m-cf-ok">' + h(label) + '</button></div>',
				function (body, close) {
					body.querySelector('#m-cf-ok').addEventListener('click', function () {
						var v = field ? body.querySelector('#m-cf').value.trim() : true;
						if (field && field.required && !v) { var er = body.querySelector('#m-cf-err'); er.textContent = field.required; er.hidden = false; return; }
						done = true; close(); resolve(v);
					});
					var oc = sheetClose; sheetClose = function () { oc(); if (!done) { resolve(null); } };
				});
		});
	}

	function skel(k) { $main.innerHTML = Array(k || 4).join('<div class="m-skel"></div>'); }
	function fail(e, retry) {
		$main.innerHTML = '<div class="m-error"><b>No se pudo cargar.</b><br>' + h(e.message) + '</div>' + (retry ? '<button type="button" class="m-btn" id="m-retry"><i class="fa-solid fa-rotate"></i> Reintentar</button>' : '');
		var r = document.getElementById('m-retry'); if (r) { r.onclick = retry; }
	}
	function setTitle(t) { document.getElementById('m-title').textContent = t; document.title = t + ' · DAIRY Mobile'; }
	function crumbs(list) {
		var el = document.getElementById('m-crumbs');
		if (!list || list.length < 2) { el.hidden = true; el.innerHTML = ''; return; }
		el.hidden = false;
		el.innerHTML = list.map(function (c, i) {
			return (i ? '<span class="sep"><i class="fa-solid fa-chevron-right m-xs"></i></span>' : '') + (i < list.length - 1 ? '<a href="' + h(c[1]) + '">' + h(c[0]) + '</a>' : '<span class="cur">' + h(c[0]) + '</span>');
		}).join('');
		el.scrollLeft = el.scrollWidth;
	}
	function chips(items, cur, attr) {
		return '<div class="m-chips">' + items.map(function (i) { return '<button type="button" class="m-chip' + (String(i[0]) === String(cur) ? ' on' : '') + '" data-' + attr + '="' + h(i[0]) + '">' + i[1] + '</button>'; }).join('') + '</div>';
	}
	function periodChips() { return chips(PERIODS, S.period, 'period'); }
	function seg(items, cur, attr) {
		return '<div class="m-seg" role="tablist">' + items.map(function (i) { return '<button type="button" role="tab" aria-selected="' + (i[0] === cur) + '" class="' + (i[0] === cur ? 'on' : '') + '" data-' + attr + '="' + h(i[0]) + '">' + h(i[1]) + '</button>'; }).join('') + '</div>';
	}
	function row(o) {
		var tag = o.href ? 'a href="' + h(o.href) + '"' : 'div';
		return '<' + tag + ' class="m-row">' + (o.icon ? '<span class="ic ' + (o.tone || '') + '"><i class="fa-solid ' + o.icon + '"></i></span>' : '')
			+ '<span class="tx"><div class="t1">' + o.t1 + '</div>' + (o.t2 ? '<div class="t2">' + o.t2 + '</div>' : '') + (o.bar != null ? '<div class="m-bar"><i style="width:' + Math.max(2, Math.min(100, o.bar)) + '%' + (o.barColor ? ';background:' + o.barColor : '') + '"></i></div>' : '') + '</span>'
			+ (o.v != null ? '<span class="rt"><div class="v m-num">' + o.v + '</div>' + (o.s ? '<div class="s">' + o.s + '</div>' : '') + '</span>' : '')
			+ (o.href ? '<i class="fa-solid fa-chevron-right chev"></i>' : '') + '</' + (o.href ? 'a' : 'div') + '>';
	}
	function list(rows, empty) { return rows.length ? '<div class="m-list">' + rows.join('') + '</div>' : '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>' + h(empty || 'Sin registros en este período') + '</div>'; }
	function kpi(o) {
		var tag = o.href ? 'a' : 'div';
		return '<' + tag + ' class="m-kpi ' + (o.tone || '') + '"' + (o.href ? ' href="' + h(o.href) + '"' : '') + '><div class="lbl"><i class="fa-solid ' + o.icon + '"></i>' + h(o.label) + '</div><div class="val m-num">' + o.val + '</div><div class="sub">' + (o.sub || '') + '</div>' + (o.href ? '<i class="fa-solid fa-chevron-right go"></i>' : '') + '</' + tag + '>';
	}
	function section(t, link) { return '<div class="m-section"><h2>' + h(t) + '</h2>' + (link || '') + '</div>'; }

	// Gráficos SVG livianos
	function spark(series, color, h0) {
		var vals = series.map(function (p) { return n(p.v); }), max = Math.max.apply(null, vals.concat([1])), W = 300, H = h0 || 44, k = vals.length;
		if (k < 2) { return ''; }
		var pts = vals.map(function (v, i) { return (i / (k - 1) * W).toFixed(1) + ',' + (H - 2 - v / max * (H - 6)).toFixed(1); });
		return '<svg class="m-chart" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true"><polyline points="0,' + H + ' ' + pts.join(' ') + ' ' + W + ',' + H + '" fill="' + color + '" fill-opacity=".15" stroke="none"/><polyline points="' + pts.join(' ') + '" fill="none" stroke="' + color + '" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
	}
	function bars(series, labelFn) {
		var max = Math.max.apply(null, series.map(function (p) { return n(p.v); }).concat([1]));
		return '<div class="m-hours">' + series.map(function (p) { return '<div title="' + h(labelFn ? labelFn(p) : '') + '" style="height:' + Math.max(2, n(p.v) / max * 100) + '%"></div>'; }).join('') + '</div>';
	}
	function hours(byHour) {
		var map = {}; (byHour || []).forEach(function (r) { map[r.h] = n(r.total); });
		var s = []; for (var i = 6; i <= 22; i++) { s.push({ h: i, v: map[i] || 0 }); }
		var best = s.reduce(function (a, b) { return b.v > a.v ? b : a; }, s[0]);
		return bars(s, function (p) { return p.h + ':00 · ' + money(p.v); }) + '<div class="m-hours-x"><span>6 h</span><span>12 h</span><span>18 h</span><span>22 h</span></div>' + (best.v > 0 ? '<div class="m-sub" style="margin-top:6px">Hora pico: <b>' + best.h + ':00</b> · ' + money(best.v) + '</div>' : '');
	}

	/* ---------------------------------------------------------------- router */
	function parse() {
		var raw = (location.hash || '#/home').slice(1), q = {}, parts = raw.split('?');
		(parts[1] || '').split('&').forEach(function (kv) { if (kv) { var a = kv.split('='); q[decodeURIComponent(a[0])] = decodeURIComponent(a[1] || ''); } });
		return { path: parts[0].split('/').filter(Boolean), q: q, raw: raw };
	}
	function go(path, q) { location.hash = '#/' + path + qs(q); }
	function setQ(patch) { var r = parse(), q = Object.assign({}, r.q, patch); Object.keys(q).forEach(function (k) { if (q[k] === null) { delete q[k]; } }); history.replaceState(null, '', '#/' + r.path.join('/') + qs(q)); render(true); }

	var ROOTS = { home: 1, tasks: 1, scan: 1, notifications: 1, menu: 1, ops: 1, profile: 1 };
	var TAB_OF = { messages: 'menu', openshifts: 'menu', task: 'tasks', control: 'tasks', 'case': 'tasks', capture: 'scan', sales: 'menu', sale: 'menu', inv: 'menu', sku: 'menu', waste: 'menu', shrink: 'menu', overview: 'menu', team: 'menu', assistant: 'menu', ops: 'menu', profile: 'menu', search: 'home' };
	var lastRaw = null;
	function saveScroll() { if (lastRaw) { try { sessionStorage.setItem('dairyM:sc:' + lastRaw, String(window.scrollY)); } catch (e) { } } }
	function restoreScroll(raw) { var y = 0; try { y = n(sessionStorage.getItem('dairyM:sc:' + raw)); } catch (e) { } window.scrollTo(0, y); }

	async function render(keepScroll) {
		var r = parse(), name = r.path[0] || 'home';
		// Un enlace con período (p. ej. "venta 30 días" del SKU) lo fija sin perder el elegido por el usuario al volver
		if (r.q.period && PERIODS.some(function (p) { return p[0] === r.q.period; })) { S.period = r.q.period; }
		else { S.period = store('period') || 'month'; }
		if (ctrl) { ctrl.abort(); }
		ctrl = new AbortController();
		var tab = TAB_OF[name] || name;
		document.querySelectorAll('#m-tabs a').forEach(function (a) { var on = a.dataset.tab === tab; a.classList.toggle('on', on); if (on) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); } });
		var deep = !ROOTS[name] || r.path.length > 1 || Object.keys(r.q).some(function (k) { return ['group', 'product', 'bucket'].indexOf(k) >= 0; });
		document.getElementById('m-back').hidden = !deep;

		var brandEl = document.getElementById('m-top-brand');
		var titleEl = document.getElementById('m-top-title');
		if (brandEl && titleEl) {
			brandEl.hidden = deep && name !== 'home';
			titleEl.hidden = !brandEl.hidden;
		}

		// En casos, ventas y capturas la barra de acciones ocupa la zona del pulgar: sin FAB
		var view = VIEWS[name] || VIEWS.home;
		try { await view(r, ctrl.signal); }
		catch (e) { if (e.name !== 'AbortError') { fail(e, function () { render(true); }); } }
		if (!keepScroll) { restoreScroll(r.raw); }
		lastRaw = r.raw;
	}
	// Cambiar de pantalla (incluido el botón Atrás del teléfono) cierra la hoja abierta de la pantalla anterior
	window.addEventListener('hashchange', function () { if (sheetClose) { sheetClose(); } saveScroll(); S.nav++; render(); });
	document.getElementById('m-back').addEventListener('click', function () {
		if (S.nav > 0) { S.nav -= 2; history.back(); return; }
		var cr = document.querySelectorAll('#m-crumbs a'); location.hash = cr.length ? cr[cr.length - 1].getAttribute('href') : '#/' + (TAB_OF[parse().path[0]] || 'home');
	});

	// Conectar botón central FAB de escaneo
	var scanFab = document.querySelector('.m-tab-scan-fab');
	if (scanFab) {
		scanFab.addEventListener('click', function(e) {
			if (window.openSmartScanner) {
				e.preventDefault();
				window.openSmartScanner();
			}
		});
	}

	// Delegación: chips de período, segmentos y filtros guardan su estado en el hash (regreso sin perder estado)
	$main.addEventListener('click', function (e) {
		var b = e.target.closest('[data-period]');
		if (b) { S.period = b.dataset.period; store('period', S.period); setQ({ period: null }); return; }
		b = e.target.closest('[data-tab-q]');
		if (b) { var p = {}; p[b.dataset.tabQ] = b.dataset.val; setQ(p); return; }
	});

	/* ---------------------------------------------------------------- vistas */
	var VIEWS = {};

	// Resumen ejecutivo (gerente): lo urgente primero, cada indicador profundiza
	VIEWS.overview = async function (r, signal) {
		setTitle('Resumen ejecutivo'); crumbs([['Operaciones', '#/ops'], ['Resumen ejecutivo', location.hash]]); skel(5);
		var d = await get('/m/home', scope(), { signal: signal, fresh: r.q.fresh });
		var html = periodChips();
		if (d.inbox) {
			S.inboxBadge = d.inbox.high; badge();
			html += d.inbox.total ? '<a class="m-card" href="#/control" style="display:flex;gap:12px;align-items:center;border-left:4px solid var(--danger)"><span class="m-row" style="padding:0;min-height:0;border:0;flex:1;min-width:0"><span class="ic danger"><i class="fa-solid fa-bolt"></i></span><span class="tx"><div class="t1">' + d.inbox.high + ' urgentes · ' + d.inbox.total + ' casos abiertos</div><div class="t2">Revise y resuelva uno por uno en el Centro de Control</div></span></span><i class="fa-solid fa-chevron-right m-muted"></i></a>'
				: '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>Sin casos pendientes</div>';
		}
		if (d.sales) {
			var t = d.sales.totals;
			html += '<a class="m-hero" href="#/sales" style="display:block"><div class="lbl">Ventas del período · ' + day(d.from) + ' – ' + day(d.to) + '</div><div class="big m-num">' + money(t.total) + '</div><div class="row"><span>' + delta(t.delta_pct) + ' vs período anterior</span><span>' + qty(t.tickets) + ' tickets</span><span>Ticket ' + money(t.avg_ticket) + '</span></div>' + spark(d.sales.daily, 'currentColor') + '</a>';
		}
		html += '<div class="m-grid">';
		if (d.waste) {
			html += kpi({ label: 'Mermas', icon: 'fa-trash-can', val: m(d.waste.total), sub: (d.waste.pct_sales != null ? pct(d.waste.pct_sales, 2) + ' de la venta · ' : '') + delta(d.waste.delta_pct, true), href: '#/waste', tone: 'danger' });
			html += kpi({ label: 'Por aprobar', icon: 'fa-clipboard-check', val: qty(d.waste.pending.records), sub: m(d.waste.pending.cost) + ' sin descontar', href: '#/control?type=waste', tone: n(d.waste.pending.records) ? 'warn' : 'ok' });
		}
		if (d.inventory) {
			var crit = d.inventory.agotado + d.inventory.bajo + d.inventory.vencido + d.inventory.por_vencer;
			html += kpi({ label: 'Inventario', icon: 'fa-boxes-stacked', val: m(d.inventory.value), sub: qty(d.inventory.units) + ' u. · ' + pct(d.inventory.availability_pct) + ' disponible', href: '#/inv', tone: 'info' });
			html += kpi({ label: 'Críticos', icon: 'fa-triangle-exclamation', val: qty(crit), sub: d.inventory.agotado + ' agotados · ' + d.inventory.vencido + ' vencidos', href: '#/inv?filter=critico', tone: crit ? 'warn' : 'ok' });
			html += kpi({ label: 'Diferencias', icon: 'fa-scale-unbalanced', val: qty(d.differences.count), sub: m(d.differences.cost) + ' en conteos por ajustar', href: '#/control?type=count', tone: d.differences.count ? 'warn' : 'ok' });
		}
		if (d.sales && d.sales.totals.margin_pct != null) {
			html += kpi({ label: 'Margen bruto', icon: 'fa-percent', val: pct(d.sales.totals.margin_pct), sub: m(d.sales.totals.margin) + ' sobre venta neta', href: '#/sales', tone: 'ok' });
		}
		html += '</div>';
		if (d.waste) {
			html += section('Pérdidas por clasificación', '<a href="#/waste">Detalle</a>') + splitCard(d.waste.buckets, d.waste.total);
		}
		if (d.inbox && d.inbox.top.length) {
			html += section('Prioridades', '<a href="#/control">Ver todas</a>') + d.inbox.top.map(caseCard).join('');
		}
		if (d.sales && d.sales.top_groups.length) {
			var mx = n(d.sales.top_groups[0].total);
			html += section('Departamentos que más venden', '<a href="#/sales">Ventas</a>') + list(d.sales.top_groups.map(function (g) {
				return row({ t1: h(g.name), v: m(g.total), s: qty(g.units) + ' u.', bar: n(g.total) / (mx || 1) * 100, href: '#/sales?group=' + g.id });
			}));
		}
		html += '<p class="m-sub" style="text-align:center;margin:16px 0 8px">Actualizado ' + new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) + '</p><a class="m-btn ghost" href="#/overview?fresh=' + Date.now() + '"><i class="fa-solid fa-rotate"></i> Actualizar datos</a>';
		$main.innerHTML = html;
	};

	function splitCard(b, total) {
		var t = n(total) || 1, parts = ['operativa', 'comercial', 'por_clasificar'];
		var cost = function (k) { return n(b[k].cost) + n(b[k].inventory_diff); };
		return '<div class="m-card"><div class="m-split">' + parts.map(function (k) { return '<i class="c-' + BUCKETS[k][1] + '" style="width:' + (cost(k) / t * 100) + '%"></i>'; }).join('') + '</div><div class="m-legend">'
			+ parts.map(function (k) {
				return row({ t1: '<span class="m-dot c-' + BUCKETS[k][1] + '"></span>Merma ' + BUCKETS[k][0].toLowerCase(), t2: qty(b[k].records) + ' registros · ' + qty(b[k].units) + ' u.' + (b[k].inventory_diff ? ' · incluye ' + money(b[k].inventory_diff) + ' de diferencias de inventario' : ''), v: money(cost(k)), s: pct(b[k].pct_total) + ' del total', href: '#/waste?bucket=' + k });
			}).join('') + '</div></div>';
	}

	/* ---------------- Ventas */
	VIEWS.sales = async function (r, signal) {
		var q = r.q, lvl = q.product ? 'product' : (q.group != null ? 'group' : 'all');
		setTitle('Ventas'); skel(5);
		var d = await get('/m/sales', scope({ group_id: q.group, product_id: q.product }), { signal: signal });
		var lk = await lookups();
		var gName = q.group != null ? (groupName(lk, q.group)) : null;
		var pName = q.product ? (q.pname || (d.lines[0] && d.lines[0].product_name) || 'Producto') : null;
		var cr = [['Ventas', '#/sales']];
		if (gName) { cr.push([gName, '#/sales?group=' + q.group]); }
		if (pName) { cr.push([pName, location.hash]); }
		crumbs(cr);
		var t = d.totals, html = periodChips();
		html += '<div class="m-hero"><div class="lbl">' + (pName ? h(pName) : (gName ? h(gName) : 'Venta total')) + ' · ' + day(d.from) + ' – ' + day(d.to) + '</div><div class="big m-num">' + money(t.total) + '</div><div class="row"><span>' + delta(t.delta_pct) + ' vs ' + day(d.prev_from) + ' – ' + day(d.prev_to) + '</span>'
			+ (lvl === 'all' ? '<span>' + qty(t.tickets) + ' tickets</span>' : '<span>' + qty(t.units) + ' u. · ' + qty(t.tickets) + ' tickets</span>') + '</div>' + spark(d.daily, 'currentColor') + '</div>';
		if (lvl === 'all') {
			html += '<div class="m-grid">'
				+ kpi({ label: 'Venta neta', icon: 'fa-receipt', val: m(t.net), sub: 'Sin impuestos (' + m(t.tax) + ')' })
				+ kpi({ label: 'Descuentos', icon: 'fa-tag', val: m(t.discounts), sub: t.gross > 0 ? pct(t.discounts / t.gross * 100) + ' de la venta bruta' : '' })
				+ kpi({ label: 'Devoluciones', icon: 'fa-rotate-left', val: qty(t.returns_count), sub: m(t.returns_amount) + ' anulado', href: '#/sales?view=returns', tone: t.returns_count ? 'warn' : '' })
				+ (t.margin_pct != null ? kpi({ label: 'Margen bruto', icon: 'fa-percent', val: pct(t.margin_pct), sub: m(t.margin), tone: 'ok' }) : kpi({ label: 'Margen', icon: 'fa-percent', val: '—', sub: 'Sin costo registrado' }))
				+ kpi({ label: 'Ticket promedio', icon: 'fa-cart-shopping', val: money(t.avg_ticket), sub: qty(t.tickets) + ' ventas' })
				+ '</div>';
		} else {
			html += '<div class="m-grid">' + kpi({ label: 'Unidades', icon: 'fa-cubes', val: qty(t.units), sub: qty(t.tickets) + ' tickets' })
				+ kpi({ label: 'Margen', icon: 'fa-percent', val: t.margin_pct != null ? pct(t.margin_pct) : '—', sub: t.margin != null ? m(t.margin) : 'Sin costo registrado', tone: t.margin_pct != null ? 'ok' : '' })
				+ (lvl === 'product' && can(P.stock) ? kpi({ label: 'Inventario', icon: 'fa-box', val: 'Ver SKU', sub: 'Existencias, lotes e historial', href: '#/sku/' + q.product, tone: 'info' }) : '')
				+ (can(P.waste) ? kpi({ label: 'Mermas', icon: 'fa-trash-can', val: 'Ver', sub: 'Del mismo alcance', href: '#/waste?' + (lvl === 'product' ? 'product=' + q.product : 'group=' + q.group), tone: 'danger' }) : '') + '</div>';
		}
		html += section('Ventas por hora') + '<div class="m-card">' + hours(d.by_hour) + '</div>';
		if (q.view === 'returns' && d.returns) {
			html += section('Devoluciones y anulaciones') + list(d.returns.map(function (s) { return row({ icon: 'fa-rotate-left', tone: 'warn', t1: h(s.sale_number), t2: dt(s.created_timestamp) + (s.notes ? ' · ' + h(s.notes) : ''), v: money(s.total_amount), href: '#/sale/' + s.sale_id }); }), 'Sin devoluciones');
		}
		var tab = q.tab || (lvl === 'all' ? 'group' : (lvl === 'group' ? 'product' : 'lines'));
		var tabs = lvl === 'all' ? [['group', 'Departamento'], ['product', 'Producto'], ['branch', 'Sucursal'], ['relation', 'Venta vs merma']] : (lvl === 'group' ? [['product', 'Productos'], ['lines', 'Registros']] : [['lines', 'Registros']]);
		if (d.by_branch && !d.by_branch.length) { tabs = tabs.filter(function (x) { return x[0] !== 'branch'; }); }
		html += '<div style="margin-top:16px"></div>' + (tabs.length > 1 ? seg(tabs, tab, 'tab-q="tab" data-val') : '');
		html += salesTab(d, tab, q);
		$main.innerHTML = html;
	};
	function salesTab(d, tab, q) {
		if (tab === 'group') {
			var mx = d.by_group.length ? n(d.by_group[0].total) : 1, tot = n(d.totals.total) || 1;
			return list(d.by_group.map(function (g) { return row({ t1: h(g.name), t2: qty(g.units) + ' u.' + (n(g.cogs) > 0 ? ' · margen ' + pct((n(g.total) - n(g.cogs)) / n(g.total) * 100) : ''), v: m(g.total), s: pct(n(g.total) / tot * 100) + ' del total', bar: n(g.total) / mx * 100, href: '#/sales?group=' + g.id }); }));
		}
		if (tab === 'product') {
			var mp = d.by_product.length ? n(d.by_product[0].total) : 1;
			return list(d.by_product.map(function (p) { return row({ t1: h(p.name), t2: (p.sku ? h(p.sku) + ' · ' : '') + qty(p.units) + ' u. · ' + qty(p.tickets) + ' tickets', v: m(p.total), s: n(p.cogs) > 0 ? 'margen ' + pct((n(p.total) - n(p.cogs)) / n(p.total) * 100) : '', bar: n(p.total) / mp * 100, href: '#/sales?' + (q.group != null ? 'group=' + q.group + '&' : '') + 'product=' + p.id + '&pname=' + encodeURIComponent(p.name) }); }));
		}
		if (tab === 'branch') {
			var mb = d.by_branch.length ? n(d.by_branch[0].total) : 1;
			return list(d.by_branch.map(function (b) { return row({ icon: 'fa-store', t1: h(b.name), t2: qty(b.tickets) + ' tickets', v: m(b.total), bar: n(b.total) / mb * 100, href: '#', }); }).map(function (x, i) { return x.replace('href="#"', 'href="#" data-branch="' + d.by_branch[i].id + '"'); }));
		}
		if (tab === 'relation') {
			return '<div class="m-info">Por departamento: venta del período, merma aprobada (y su % sobre la venta) e inventario actual a costo.</div>' + list(d.relation.map(function (x) {
				var tone = x.waste_pct == null ? '' : (x.waste_pct >= 3 ? 'danger' : (x.waste_pct >= 1.5 ? 'warn' : 'ok'));
				return row({ t1: h(x.name), t2: 'Venta ' + m(x.sales) + ' · Stock ' + m(x.stock), v: x.waste_pct == null ? (x.waste > 0 ? 'merma sin venta' : '—') : pct(x.waste_pct, 2), s: 'merma ' + m(x.waste), icon: 'fa-layer-group', tone: tone, href: '#/waste?group=' + x.id });
			}));
		}
		return list(d.lines.map(function (l) { return row({ t1: h(l.product_name), t2: h(l.sale_number) + ' · ' + dt(l.created_timestamp) + ' · ' + qty(l.amount) + ' × ' + money(l.unit_price), v: money(l.subtotal), href: '#/sale/' + l.sale_id }); }));
	}
	$main.addEventListener('click', function (e) {
		var b = e.target.closest('[data-branch]');
		if (b) { e.preventDefault(); setBranch(n(b.dataset.branch)); }
	});

	VIEWS.sale = async function (r, signal) {
		setTitle('Venta'); skel(3);
		var s = await get('/m/sales/' + n(r.path[1]), {}, { signal: signal });
		crumbs([['Ventas', '#/sales'], [s.sale_number, location.hash]]);
		var html = '<div class="m-card"><div class="m-title">' + h(s.sale_number) + '</div><div class="m-sub">' + dt(s.created_timestamp) + ' · ' + h(s.branch_name || '') + '</div><div style="margin-top:8px">' + (s.status === 'cancelled' ? '<span class="m-tag danger">Anulada</span>' : '<span class="m-tag ok">Completada</span>') + '</div>'
			+ '<dl class="m-kv" style="margin-top:12px"><dt>Subtotal</dt><dd>' + money(s.subtotal) + '</dd><dt>Descuento</dt><dd>' + money(s.discount_amount) + '</dd><dt>Impuestos</dt><dd>' + money(s.tax_amount) + '</dd><dt>Total</dt><dd><b>' + money(s.total_amount) + '</b></dd>'
			+ (n(s.total_cogs) > 0 ? '<dt>Costo</dt><dd>' + money(s.total_cogs) + '</dd><dt>Margen</dt><dd>' + money(s.gross_margin) + '</dd>' : '') + '<dt>Pago</dt><dd>' + h(s.payment_method || '—') + '</dd><dt>Cajero</dt><dd>' + h(s.cashier || '—') + '</dd></dl></div>';
		html += section('Productos') + list(s.items.map(function (i) { return row({ t1: h(i.product_name), t2: qty(i.amount) + ' × ' + money(i.unit_price), v: money(i.subtotal), href: can(P.stock) ? '#/sku/' + i.product_id : null }); }));
		if (s.can_cancel) { html += '<div class="m-actions"><button type="button" class="m-btn danger" id="m-cancel-sale"><i class="fa-solid fa-ban"></i> Anular venta</button></div>'; }
		$main.innerHTML = html;
		var b = document.getElementById('m-cancel-sale');
		if (b) {
			b.onclick = async function () {
				if (!await confirmSheet('Anular venta', 'Se devuelve el stock y la venta queda anulada. Esta acción queda en la auditoría.', 'Anular', 'danger')) { return; }
				try { var res = await post('/sales/' + s.id + '/cancel', {}, b); if (res) { toast(res.message || 'Venta anulada', 'ok'); render(true); } } catch (e) { toast(e.message, 'err'); }
			};
		}
	};

	/* ---------------- Mermas */
	VIEWS.waste = async function (r, signal) {
		var q = r.q;
		setTitle('Mermas'); skel(5);
		var d = await get('/m/waste', scope({ bucket: q.bucket, group_id: q.group, product_id: q.product }), { signal: signal });
		var lk = await lookups();
		var cr = [['Mermas', '#/waste']];
		if (q.bucket) { cr.push([BUCKETS[q.bucket][0], '#/waste?bucket=' + q.bucket]); }
		if (q.group != null) { cr.push([groupName(lk, q.group), '#/waste?' + qs({ bucket: q.bucket, group: q.group }).slice(1)]); }
		if (q.product) { cr.push([q.pname || (d.records[0] && d.records[0].product_name) || 'Producto', location.hash]); }
		crumbs(cr);
		var html = periodChips() + seg([['', 'Todas'], ['operativa', 'Operativa'], ['comercial', 'Comercial'], ['por_clasificar', 'Por clasificar']], q.bucket || '', 'tab-q="bucket" data-val');
		var b = d.buckets, label = q.bucket ? 'Merma ' + BUCKETS[q.bucket][0].toLowerCase() : 'Pérdida total registrada';
		html += '<div class="m-hero danger"><div class="lbl">' + label + ' · ' + day(d.from) + ' – ' + day(d.to) + '</div><div class="big m-num">' + money(d.total) + '</div><div class="row"><span>' + (d.pct_sales != null ? pct(d.pct_sales, 2) + ' de la venta' : 'sin ventas en el período') + '</span><span>' + delta(d.delta_pct, true) + ' merma aprobada vs anterior</span></div>' + spark(d.daily, 'currentColor') + '</div>';
		if (!q.bucket) { html += splitCard(b, d.total); }
		if (d.control) { html += controlCard(d.control); }
		var pend = d.pending || {};
		html += '<div class="m-info"><b>Sin doble conteo:</b> solo suman mermas aprobadas y diferencias netas de inventario ya ajustadas. ' + (n(pend.records) ? '<a href="#/control?type=waste" style="text-decoration:underline">' + qty(pend.records) + ' por aprobar (' + money(pend.cost) + ')</a> aún no suman.' : 'No hay mermas por aprobar.') + '</div>';
		if (d.inventory_diff) {
			var u = d.inventory_diff;
			html += section('Diferencias de inventario') + '<div class="m-card"><dl class="m-kv"><dt>Faltantes ajustados</dt><dd class="t-op">' + money(u.loss) + '</dd><dt>Sobrantes ajustados</dt><dd class="t-ok">' + money(u.gain) + '</dd><dt>Neto (pérdida desconocida)</dt><dd><b>' + money(Math.max(0, u.net)) + '</b></dd><dt>Ajustes</dt><dd>' + qty(u.adjustments) + '</dd>'
				+ (d.unexplained && d.unexplained.count ? '<dt>Salidas sin documento</dt><dd class="t-unk">' + money(d.unexplained.total) + ' · ' + d.unexplained.count + '</dd>' : '') + '</dl>'
				+ '<p class="m-sub" style="margin:10px 0 0">Quedan <b>por clasificar</b> hasta que la investigación les asigne causa. Las salidas sin documento se investigan y no suman al total.</p></div>';
		}
		var tab = q.tab || (q.product ? 'records' : 'reason');
		var tabs = q.product ? [['records', 'Registros']] : [['reason', 'Causa'], ['group', 'Departamento'], ['product', 'Producto'], ['area', 'Responsable'], ['records', 'Registros']];
		if (q.group != null) { tabs = tabs.filter(function (x) { return x[0] !== 'group'; }); }
		html += '<div style="margin-top:16px"></div>' + (tabs.length > 1 ? seg(tabs, tab, 'tab-q="tab" data-val') : '');
		html += wasteTab(d, tab, q);
		if (q.product && can(P.stock)) { html += '<a class="m-btn" href="#/sku/' + q.product + '" style="margin-top:8px"><i class="fa-solid fa-box"></i> Ver SKU: existencias e historial</a>'; }
		$main.innerHTML = html;
	};
	// Indicadores del Centro de Control (mismos números que /waste en web); cada uno abre su sección web
	function controlCard(c) {
		var p = function (v) { return v == null ? 's/ventas' : pct(v, 2); };
		var web = function (hash, extra) { return C.base + '/waste?' + qs(Object.assign({ branch_id: S.branch, start_date: c.from, end_date: c.to }, extra || {})).slice(1) + hash; };
		var k = [
			['Merma total', money(c.total.value), p(c.total.pct_sales) + ' de ventas netas', web('#tab-registro')],
			['Operativa', money(c.operativa.value), p(c.operativa.pct_sales), web('#tab-clasificacion')],
			['Comercial', money(c.comercial.value), p(c.comercial.pct_sales), web('#tab-clasificacion')],
			['Ajustes', money(c.ajuste.value), p(c.ajuste.pct_sales), web('#tab-ajustes')],
			['Averías', money(c.averia.value), p(c.averia.pct_sales), web('#tab-averias')],
			['Robo', c.can_theft ? money(c.robo.value) : 'Restringido', c.can_theft ? p(c.robo.pct_sales) : 'requiere permiso', web('#tab-robo')],
			['Impacto margen', '−' + p(c.margin_impact.pp) + ' pp', p(c.margin_impact.margin_before_pct) + ' → ' + p(c.margin_impact.margin_after_pct), web('#tab-recuperacion')],
			['Recuperación', c.recovery.pct == null ? '—' : pct(c.recovery.pct, 1), money(c.recovery.recovered) + ' de ' + money(c.recovery.recoverable), web('#tab-recuperacion')],
			['Vs. año anterior', c.yoy.has_prev ? money(c.yoy.diff) : 'Sin histórico', c.yoy.pct != null ? pct(c.yoy.pct, 1) : 'importe el año anterior', web('#tab-anual')],
			['Merma por hora', c.labor.per_hour == null ? '—' : money(c.labor.per_hour), c.labor.source || 'sin horas registradas', web('#tab-recuperacion')]
		];
		var tones = ['danger', 'danger', 'warn', '', 'warn', '', 'danger', 'ok', c.yoy.diff > 0 ? 'danger' : 'ok', ''];
		return section('Centro de Control') + '<div class="m-grid">' + k.map(function (x, i) {
			return kpi({ label: x[0], val: x[1], sub: h(x[2]), href: x[3], icon: 'fa-chart-simple', tone: tones[i] });
		}).join('') + '</div><p class="m-sub" style="margin:6px 0 0">Mismas cifras y fórmulas que el Centro de Control web (ventas netas sin impuesto; solo eventos confirmados).</p>'
			+ '<a class="m-btn" href="#/shrink" style="margin-top:8px"><i class="fa-solid fa-shield-halved"></i> Tolerancia, errores administrativos y hurto</a>';
	}
	function wasteTab(d, tab, q) {
		var tot = n(d.known) + n(d.buckets.por_clasificar.cost) || 1;
		var base = function (extra) { return '#/waste?' + qs(Object.assign({ bucket: q.bucket, group: q.group }, extra)).slice(1); };
		if (tab === 'reason') {
			return list(d.by_reason.map(function (x) { var bk = BUCKETS[x.bucket] || BUCKETS.operativa; return row({ t1: h(x.name), t2: '<span class="t-' + bk[1] + '">' + bk[0] + '</span> · ' + qty(x.records) + ' reg. · ' + qty(x.units) + ' u.', v: money(x.cost), s: pct(n(x.cost) / tot * 100), bar: n(x.cost) / tot * 100, barColor: 'var(--' + bk[1] + ')' }); }));
		}
		if (tab === 'group') {
			return list(d.by_group.map(function (x) { return row({ icon: 'fa-layer-group', t1: h(x.name), t2: qty(x.records) + ' reg. · ' + qty(x.units) + ' u.', v: money(x.cost), s: pct(n(x.cost) / tot * 100), href: base({ group: x.id }) }); }));
		}
		if (tab === 'product') {
			return list(d.by_product.map(function (x) { return row({ t1: h(x.name), t2: (x.sku ? h(x.sku) + ' · ' : '') + qty(x.records) + ' reg. · ' + qty(x.units) + ' u.', v: money(x.cost), s: pct(n(x.cost) / tot * 100), href: base({ product: x.id, pname: x.name }) }); }));
		}
		if (tab === 'area') {
			return section('Área responsable (según causal)') + list(d.by_area.map(function (x) { return row({ icon: 'fa-sitemap', t1: h(x.name), t2: qty(x.records) + ' registros', v: money(x.cost) }); }))
				+ section('Reportado por') + list(d.by_user.map(function (x) { return row({ icon: 'fa-user', t1: h(x.name), t2: qty(x.records) + ' registros', v: money(x.cost) }); }));
		}
		return list(d.records.map(function (x) {
			var bk = BUCKETS[x.bucket] || BUCKETS.operativa, st = x.status === 'pending_approval' ? '<span class="m-tag warn">Por aprobar</span>' : (x.status === 'rejected' ? '<span class="m-tag muted">Rechazada</span>' : '<span class="m-tag ok">Aprobada</span>');
			return row({ icon: x.evidence_photo_path ? 'fa-camera' : 'fa-file-lines', tone: x.status === 'pending_approval' ? 'warn' : '', t1: h(x.product_name), t2: h(x.case_number || '') + ' · ' + h(x.reason) + ' · <span class="t-' + bk[1] + '">' + bk[0] + '</span>', v: money(x.total_cost), s: st, href: '#/case/waste/' + x.id });
		}));
	}

	/* ---------------- Inventario */
	VIEWS.inv = async function (r, signal) {
		var q = r.q;
		setTitle('Inventario'); skel(5);
		var d = await get('/m/inventory', { branch_id: S.branch, group_id: q.group, filter: q.filter }, { signal: signal });
		var lk = await lookups();
		var cr = [['Inventario', '#/inv']];
		if (q.group != null) { cr.push([groupName(lk, q.group), location.hash]); }
		crumbs(cr);
		var t = d.totals, crit = t.agotado + t.bajo + t.vencido + t.por_vencer;
		var html = '<div class="m-grid">'
			+ kpi({ label: 'Valor a costo', icon: 'fa-sack-dollar', val: m(t.value), sub: qty(t.units) + ' unidades', tone: 'info' })
			+ kpi({ label: 'Disponibilidad', icon: 'fa-circle-check', val: pct(t.availability_pct), sub: t.with_stock + ' de ' + t.skus + ' SKU con stock', tone: t.availability_pct >= 95 ? 'ok' : 'warn' })
			+ kpi({ label: 'Críticos', icon: 'fa-triangle-exclamation', val: qty(crit), sub: t.agotado + ' agot. · ' + t.bajo + ' bajo mín.', href: '#/inv?' + qs({ group: q.group, filter: 'critico' }).slice(1), tone: crit ? 'warn' : 'ok' })
			+ kpi({ label: 'Vencimientos', icon: 'fa-hourglass-half', val: qty(t.vencido + t.por_vencer), sub: t.vencido + ' vencidos · ' + t.por_vencer + ' en 7 días', href: '#/inv?' + qs({ group: q.group, tab: 'expiry' }).slice(1), tone: t.vencido ? 'danger' : (t.por_vencer ? 'warn' : 'ok') })
			+ '</div>';
		var tab = q.tab || (q.group != null || q.filter ? 'items' : 'groups');
		var tabs = [['groups', 'Departamentos'], ['items', 'Productos'], ['counts', 'Conteos'], ['expiry', 'Vencen'], ['restock', 'Reposición'], ['moves', 'Movimientos'], ['transfers', 'Traslados']];
		if (q.group != null) { tabs = tabs.filter(function (x) { return x[0] !== 'groups'; }); }
		html += '<div class="m-chips" style="margin-top:12px">' + tabs.map(function (x) { return '<button type="button" class="m-chip' + (x[0] === tab ? ' on' : '') + '" data-tab-q="tab" data-val="' + x[0] + '">' + x[1] + (x[0] === 'counts' && d.counts.length ? ' <b>' + d.counts.length + '</b>' : '') + (x[0] === 'restock' && d.restock.length ? ' <b>' + d.restock.length + '</b>' : '') + '</button>'; }).join('') + '</div>';
		html += invTab(d, tab, q);
		$main.innerHTML = html;
	};
	var STATE = { agotado: ['Agotado', 'danger'], bajo: ['Bajo mínimo', 'warn'], vencido: ['Vencido', 'danger'], por_vencer: ['Vence pronto', 'warn'], ok: ['OK', 'ok'] };
	function invTab(d, tab, q) {
		if (tab === 'groups') {
			var mx = d.by_group.reduce(function (a, g) { return Math.max(a, n(g.value)); }, 1);
			return list(d.by_group.map(function (g) { return row({ icon: 'fa-layer-group', tone: g.critical ? 'warn' : '', t1: h(g.name), t2: g.skus + ' SKU' + (g.critical ? ' · <b class="t-unk">' + g.critical + ' críticos</b>' : ''), v: m(g.value), bar: n(g.value) / mx * 100, href: '#/inv?group=' + g.id }); }));
		}
		if (tab === 'items') {
			var f = [['', 'Todos'], ['critico', 'Críticos'], ['agotado', 'Agotados'], ['bajo', 'Bajo mínimo'], ['vencido', 'Vencidos'], ['por_vencer', 'Por vencer']];
			return '<div class="m-chips">' + f.map(function (x) { return '<button type="button" class="m-chip' + ((q.filter || '') === x[0] ? ' on' : '') + '" data-tab-q="filter" data-val="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div>'
				+ list(d.items.map(function (p) { var s = STATE[p.state]; return row({ t1: h(p.name), t2: (p.sku ? h(p.sku) + ' · ' : '') + qty(p.available) + ' disp. de ' + qty(p.qty) + ' ' + h(p.unit || 'u.') + (p.next_expiry && p.next_expiry !== '2999-12-31' ? ' · vence ' + day(p.next_expiry) : ''), v: money(p.value), s: '<span class="m-tag ' + s[1] + '">' + s[0] + '</span>', href: '#/sku/' + p.id }); }), 'Sin productos en este filtro');
		}
		if (tab === 'counts') {
			return list(d.counts.map(function (c) { var disc = c.status === 'discrepancy'; return row({ icon: disc ? 'fa-scale-unbalanced' : 'fa-clipboard-list', tone: disc ? 'warn' : '', t1: h(c.product_name), t2: disc ? 'Contado ' + qty(c.counted_stock) + ' · sistema ' + qty(c.theoretical_stock) : 'Conteo ciego · ' + day(c.scheduled_date), v: disc ? qty(c.difference_amount) + ' u.' : '', s: disc ? money(c.difference_cost) : '<span class="m-tag">Pendiente</span>', href: '#/case/count/' + c.id }); }), 'Sin conteos pendientes');
		}
		if (tab === 'expiry') {
			var today = new Date().toISOString().slice(0, 10);
			return list(d.expiring.map(function (x) { var exp = x.best_before_date < today; return row({ icon: 'fa-hourglass-half', tone: exp ? 'danger' : 'warn', t1: h(x.product_name), t2: h(x.location) + ' · ' + qty(x.qty) + ' u.', v: day(x.best_before_date), s: exp ? '<span class="m-tag danger">Vencido</span>' : money(x.value), href: '#/sku/' + x.product_id }); }), 'Nada vence en 7 días');
		}
		if (tab === 'restock') {
			return '<div class="m-info">Sugerencia = mínimo configurado − existencia actual.</div>' + list(d.restock.map(function (p) { return row({ icon: 'fa-truck-ramp-box', tone: p.state === 'agotado' ? 'danger' : 'warn', t1: h(p.name), t2: 'Tiene ' + qty(p.qty) + ' · mínimo ' + qty(p.min_stock_amount), v: '+' + qty(p.suggest), s: h(p.unit || 'u.'), href: '#/sku/' + p.id }); }), 'Todo sobre el mínimo');
		}
		if (tab === 'moves') { return movesList(d.movements); }
		return list(d.transfers.map(function (t) { return row({ icon: 'fa-truck', t1: h(t.transfer_code), t2: h(t.from_branch) + ' → ' + h(t.to_branch) + ' · ' + dt(t.sent_at), v: money(t.total_sent_cost), s: '<span class="m-tag warn">En tránsito</span>', href: '#/case/transfer/' + t.id }); }), 'Sin traslados en tránsito');
	}
	var TX = { purchase: ['Compra', 'fa-cart-arrow-down', 'ok'], consume: ['Salida', 'fa-arrow-right-from-bracket', ''], 'inventory-correction': ['Ajuste', 'fa-scale-balanced', 'warn'], 'transfer_from': ['Traslado (sale)', 'fa-right-left', ''], 'transfer_to': ['Traslado (entra)', 'fa-right-left', ''], 'stock-edit-new': ['Edición', 'fa-pen', 'warn'], 'self-production': ['Producción', 'fa-industry', 'ok'] };
	function movesList(rows, inSku) {
		return list(rows.map(function (x) {
			var t = TX[x.transaction_type] || [x.transaction_type, 'fa-arrows-rotate', ''];
			var label = x.transaction_type === 'consume' && n(x.spoiled) ? 'Merma' : t[0];
			return row({ icon: t[1], tone: n(x.undone) ? '' : (label === 'Merma' ? 'danger' : t[2]), t1: h(label) + (inSku ? '' : ' · ' + h(x.product_name)), t2: dt(x.row_created_timestamp) + ' · ' + h(x.location || '') + ' · ' + h(x.user_name) + (n(x.undone) ? ' · <b>anulado</b>' : ''), v: (n(x.amount) > 0 ? '+' : '') + qty(x.amount), s: money(Math.abs(n(x.amount) * n(x.price))) });
		}), 'Sin movimientos');
	}

	VIEWS.sku = async function (r, signal) {
		setTitle('Producto'); skel(4);
		var p = await get('/m/sku/' + n(r.path[1]), { branch_id: S.branch }, { signal: signal });
		crumbs([['Inventario', '#/inv'], [p.group_name, '#/inv?group=' + (p.product_group_id || 0)], [p.name, location.hash]]);
		setTitle(p.name);
		var cov = p.coverage_days;
		var html = '<div class="m-card"><div class="m-title">' + h(p.name) + '</div><div class="m-sub">' + [p.sku ? 'SKU ' + h(p.sku) : '', p.barcode ? '<i class="fa-solid fa-barcode"></i> ' + h(p.barcode) : '', h(p.brand || '')].filter(Boolean).join(' · ') + '</div></div>';
		html += '<div class="m-grid">'
			+ kpi({ label: 'Existencia', icon: 'fa-boxes-stacked', val: qty(p.qty) + ' ' + h(p.unit || ''), sub: 'Disponible ' + qty(p.available) + (n(p.min_stock_amount) ? ' · mín. ' + qty(p.min_stock_amount) : ''), tone: n(p.min_stock_amount) && p.qty < n(p.min_stock_amount) ? 'warn' : 'info' })
			+ kpi({ label: 'Valor a costo', icon: 'fa-sack-dollar', val: money(p.value), sub: p.sale_price ? 'Precio venta ' + money(p.sale_price) : '' })
			+ kpi({ label: 'Venta 30 días', icon: 'fa-chart-line', val: qty(p.sales30.units) + ' u.', sub: money(p.sales30.total) + ' · ' + p.sales30.tickets + ' tickets', href: can(P.sales) ? '#/sales?product=' + p.id + '&pname=' + encodeURIComponent(p.name) + '&period=30d' : '' })
			+ kpi({ label: 'Cobertura', icon: 'fa-calendar-days', val: cov == null ? '—' : qty(cov) + ' días', sub: cov == null ? 'Sin ventas en 30 días' : 'al ritmo de venta actual', tone: cov != null && cov < 3 ? 'danger' : (cov != null && cov > 60 ? 'warn' : 'ok') })
			+ '</div>';
		var acts = { waste: ['fa-trash-can', 'Registrar merma', 'danger'], count: ['fa-clipboard-list', 'Contar', 'primary'], adjust: ['fa-scale-balanced', 'Ajustar', ''], move: ['fa-right-left', 'Mover', ''] };
		if (p.actions.length) {
			html += '<div class="m-grid" style="margin-top:4px">' + p.actions.map(function (a) { return '<a class="m-btn ' + acts[a][2] + '" href="#/capture/' + a + '?product=' + p.id + '"><i class="fa-solid ' + acts[a][0] + '"></i> ' + acts[a][1] + '</a>'; }).join('') + '</div>';
		}
		html += section('Ubicaciones') + list(p.locations.map(function (l) { return row({ icon: 'fa-location-dot', t1: h(l.name), t2: 'Disponible ' + qty(l.available), v: qty(l.qty), s: money(l.value) }); }), 'Sin existencias');
		var today = new Date().toISOString().slice(0, 10);
		if (p.lots.length) { html += section('Lotes (FEFO)') + list(p.lots.map(function (l) { var exp = l.best_before_date && l.best_before_date !== '2999-12-31'; return row({ icon: 'fa-tags', tone: exp && l.best_before_date < today ? 'danger' : '', t1: exp ? 'Vence ' + day(l.best_before_date) : 'Sin vencimiento', t2: h(l.location) + (n(l.open) ? ' · abierto' : ''), v: qty(l.amount), s: money(l.price) + ' c/u' }); })); }
		var tab = r.q.tab || 'moves';
		html += '<div style="margin-top:16px"></div>' + seg([['moves', 'Movimientos'], ['waste', 'Mermas'], ['counts', 'Conteos']], tab, 'tab-q="tab" data-val');
		if (tab === 'moves') { html += movesList(p.movements, true); }
		else if (tab === 'waste') { html += list(p.waste.map(function (w) { var bk = BUCKETS[w.bucket] || BUCKETS.operativa; return row({ icon: 'fa-trash-can', tone: w.status === 'pending_approval' ? 'warn' : '', t1: h(w.case_number || ('#' + w.id)) + ' · ' + h(w.reason), t2: dt(w.created_timestamp) + ' · <span class="t-' + bk[1] + '">' + bk[0] + '</span> · ' + qty(w.amount) + ' u.', v: money(w.total_cost), href: '#/case/waste/' + w.id }); }), 'Sin mermas'); }
		else { html += list(p.counts.map(function (c) { return row({ icon: 'fa-clipboard-list', tone: c.status === 'discrepancy' ? 'warn' : '', t1: c.status === 'pending' ? 'Conteo ciego pendiente' : 'Contado ' + qty(c.counted_stock) + ' · sistema ' + qty(c.theoretical_stock), t2: day(c.executed_date || c.scheduled_date), v: c.status === 'pending' ? '' : qty(c.difference_amount), s: c.status === 'discrepancy' ? '<span class="m-tag warn">Por ajustar</span>' : (c.status === 'pending' ? '<span class="m-tag">Pendiente</span>' : '<span class="m-tag ok">' + (c.status === 'adjusted' ? 'Ajustado' : 'OK') + '</span>'), href: c.status === 'pending' || c.status === 'discrepancy' ? '#/case/count/' + c.id : null }); }), 'Sin conteos'); }
		$main.innerHTML = html;
	};

	/* ---------------- Centro de Control */
	var TYPES = { waste: ['Mermas', 'fa-trash-can'], count: ['Conteos', 'fa-scale-unbalanced'], alert: ['Alertas', 'fa-bell'], transfer: ['Traslados', 'fa-truck'] };
	function caseCard(c, i) {
		var t = TYPES[c.type] || ['', 'fa-circle'];
		return '<a class="m-case ' + c.severity + '" href="#/case/' + c.type + '/' + c.id + '"><div class="h"><div class="t">' + h(c.title) + '</div>' + (n(c.value) ? '<div class="m-num" style="font-weight:700">' + money(c.value) + '</div>' : '') + '</div>'
			+ (c.detail ? '<div class="d">' + h(c.detail) + '</div>' : '') + '<div class="f"><span class="m-tag ' + (c.severity === 'high' ? 'danger' : (c.severity === 'medium' ? 'warn' : '')) + '"><i class="fa-solid ' + t[1] + '"></i>' + h(c.status) + '</span>'
			+ (c.overdue ? '<span class="m-tag danger">Vencido</span>' : '') + '<span>' + h(c.ref) + '</span><span>· ' + dt(c.created_at) + '</span></div></a>';
	}
	VIEWS.control = async function (r, signal) {
		setTitle('Centro de Control'); crumbs(null); skel(5);
		var all = await get('/m/inbox', { branch_id: S.branch }, { signal: signal, fresh: true });
		var type = r.q.type || '', sev = r.q.sev || '';
		var rows = all.filter(function (c) { return (!type || c.type === type) && (!sev || c.severity === sev); });
		S.inbox = rows; S.ctlType = type; S.inboxBadge = all.filter(function (c) { return c.severity === 'high'; }).length; badge();
		var count = function (t) { return all.filter(function (c) { return c.type === t; }).length; };
		var tchips = [['', 'Todos <b>' + all.length + '</b>']].concat(Object.keys(TYPES).filter(count).map(function (t) { return [t, TYPES[t][0] + ' <b>' + count(t) + '</b>']; }));
		var html = '<div class="m-chips">' + tchips.map(function (x) { return '<button type="button" class="m-chip' + (x[0] === type ? ' on' : '') + '" data-tab-q="type" data-val="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div>';
		html += seg([['', 'Todas'], ['high', 'Urgentes'], ['medium', 'Atención'], ['low', 'Seguimiento']], sev, 'tab-q="sev" data-val');
		if (rows.length) {
			html += '<button type="button" class="m-btn primary" id="m-review" style="margin-bottom:12px"><i class="fa-solid fa-list-check"></i> Revisar uno por uno (' + rows.length + ')</button>';
			var impact = rows.reduce(function (a, c) { return a + n(c.value); }, 0);
			html += '<p class="m-sub" style="margin:0 0 10px">Ordenado por urgencia, plazo vencido e impacto · ' + money(impact) + ' en juego</p>';
		}
		html += rows.length ? rows.map(caseCard).join('') : '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>Bandeja al día: no hay casos en este filtro.</div>';
		$main.innerHTML = html;
		var rv = document.getElementById('m-review');
		if (rv) { rv.onclick = function () { go('case/' + rows[0].type + '/' + rows[0].id); }; }
	};

	VIEWS.case = async function (r, signal) {
		var type = r.path[1], id = n(r.path[2]);
		setTitle('Caso'); skel(4);
		var d = await get('/m/case/' + type + '/' + id, {}, { signal: signal, fresh: true });
		var idx = S.inbox.findIndex(function (c) { return c.type === type && c.id === id; });
		if (idx < 0) {
			// Entrada directa (inicio, búsqueda, SKU): la revisión uno por uno usa la bandeja completa
			try { S.inbox = await get('/m/inbox', { branch_id: S.branch }, { signal: signal }); S.ctlType = ''; } catch (e) { if (e.name === 'AbortError') { throw e; } S.inbox = []; }
			idx = S.inbox.findIndex(function (c) { return c.type === type && c.id === id; });
		}
		var tLabel = (TYPES[type] || ['Caso'])[0];
		crumbs([['Control', '#/control'], [tLabel, '#/control?type=' + type], [caseRef(d), location.hash]]);
		var html = '';
		if (idx >= 0) {
			var prev = S.inbox[idx - 1], next = S.inbox[idx + 1];
			html += '<div class="m-pager"><a class="m-btn sm" ' + (prev ? 'href="#/case/' + prev.type + '/' + prev.id + '"' : 'aria-disabled="true" style="opacity:.4;pointer-events:none"') + '><i class="fa-solid fa-chevron-left"></i> Anterior</a><span>Caso ' + (idx + 1) + ' de ' + S.inbox.length + '</span><a class="m-btn sm" ' + (next ? 'href="#/case/' + next.type + '/' + next.id + '"' : 'aria-disabled="true" style="opacity:.4;pointer-events:none"') + '>Siguiente <i class="fa-solid fa-chevron-right"></i></a></div>';
		}
		html += CASE[type](d);
		if (d.note) { html += '<div class="m-note"><i class="fa-solid fa-user-shield"></i> ' + h(d.note) + '</div>'; }
		if (d.actions.length) {
			html += '<div class="m-actions">' + d.actions.map(function (a) { var x = ACT[type][a]; return x ? '<button type="button" class="m-btn ' + (x.tone || '') + '" data-act="' + a + '"><i class="fa-solid ' + x.icon + '"></i> ' + x.label + '</button>' : ''; }).join('') + '</div>';
		} else if (!d.note) {
			html += '<p class="m-sub" style="text-align:center;margin:16px 0">Sin acciones disponibles para su perfil en este estado.</p>';
		}
		setTitle(caseRef(d));
		$main.innerHTML = html;
		$main.querySelectorAll('[data-act]').forEach(function (b) {
			b.onclick = async function () {
				try {
					var res = await ACT[type][b.dataset.act].run(d, b);
					if (!res) { return; }
					toast(res.message || 'Listo', 'ok');
					afterAction(type, id, idx);
				} catch (e) { toast(e.message, 'err'); }
			};
		});
	};
	function caseRef(d) { var c = d.case; return d.type === 'waste' ? (c.case_number || 'MRM-' + c.id) : (d.type === 'count' ? 'CC-' + c.id : (d.type === 'alert' ? 'ALR-' + c.id : c.transfer_code)); }
	// Tras resolver: avanzar al siguiente caso de la bandeja (revisión uno por uno) o recargar el caso
	async function afterAction(type, id, idx) {
		try { S.inbox = (await get('/m/inbox', { branch_id: S.branch }, { fresh: true })).filter(function (c) { return !S.ctlType || c.type === S.ctlType; }); } catch (e) { }
		S.inboxBadge = S.inbox.filter(function (c) { return c.severity === 'high'; }).length; badge();
		var still = S.inbox.findIndex(function (c) { return c.type === type && c.id === id; });
		if (still >= 0) { render(true); return; }
		var next = S.inbox[Math.max(0, idx)] || S.inbox[0];
		if (next) { history.replaceState(null, '', '#/case/' + next.type + '/' + next.id); render(); }
		else { history.replaceState(null, '', '#/control'); render(); toast('Bandeja al día', 'ok'); }
	}

	function userOptions(sel) { return '<option value="">— Seleccione —</option>' + (S.lookups.users || []).map(function (u) { return '<option value="' + u.id + '"' + (String(u.id) === String(sel) ? ' selected' : '') + '>' + h(u.name) + '</option>'; }).join(''); }
	function photoUrl(p) { if (!p) { return ''; } return /^https?:/.test(p) ? p : C.base + (p.charAt(0) === '/' ? '' : '/') + p; }
	function timeline(items) { return items.length ? '<ul class="m-timeline">' + items.join('') + '</ul>' : '<p class="m-sub">Sin movimientos registrados.</p>'; }

	var CASE = {
		waste: function (d) {
			var c = d.case, bk = BUCKETS[c.bucket] || BUCKETS.operativa;
			var st = c.status === 'pending_approval' ? '<span class="m-tag warn">Por aprobar</span>' : (c.status === 'rejected' ? '<span class="m-tag muted">Rechazada</span>' : '<span class="m-tag ok">Aprobada</span>');
			var html = '<div class="m-card"><div style="display:flex;gap:6px;flex-wrap:wrap">' + st + '<span class="m-tag">' + h(c.workflow_label) + '</span><span class="m-tag" style="color:var(--' + bk[1] + ')">Merma ' + bk[0].toLowerCase() + '</span></div>'
				+ '<div class="m-title" style="margin-top:8px">' + h(c.product_name) + '</div><div class="m-sub">' + h(c.reason) + (c.sub_reason ? ' · ' + h(c.sub_reason) : '') + '</div>'
				+ '<div class="m-grid" style="margin-top:12px;grid-template-columns:1fr 1fr"><div><div class="m-label">Importe</div><div class="m-title m-num">' + money(c.total_cost) + '</div></div><div><div class="m-label">Cantidad</div><div class="m-title m-num">' + qty(c.amount) + '</div></div></div>'
				+ (c.evidence_url ? '<a href="' + h(photoUrl(c.evidence_url)) + '" target="_blank" rel="noopener"><img class="m-photo" src="' + h(photoUrl(c.evidence_url)) + '" alt="Evidencia" loading="lazy"></a>'
					: '<div class="m-note" style="margin-top:10px"><i class="fa-solid fa-camera"></i> ' + (c.evidence_missing ? 'La evidencia registrada (' + h(c.evidence_photo_path) + ') no está en el servidor' : 'Sin evidencia fotográfica') + '</div>')
				+ '</div>';
			html += '<div class="m-card"><dl class="m-kv"><dt>Departamento</dt><dd>' + h(c.group_name) + '</dd>' + (c.sku ? '<dt>SKU</dt><dd>' + h(c.sku) + '</dd>' : '') + '<dt>Sucursal</dt><dd>' + h(c.branch_name || '—') + '</dd><dt>Ubicación</dt><dd>' + h(c.location_name || '—') + '</dd>'
				+ '<dt>Área responsable</dt><dd>' + h(c.responsible_area || '—') + '</dd><dt>Reportó</dt><dd>' + h(c.reported_by) + '</dd><dt>Fecha</dt><dd>' + dt(c.created_timestamp) + '</dd>'
				+ (c.approved_by ? '<dt>Aprobó</dt><dd>' + h(c.approved_by) + '</dd>' : '') + '<dt>Investiga</dt><dd>' + h(c.investigator || 'Sin asignar') + '</dd>' + (c.due_date ? '<dt>Plazo</dt><dd>' + day(c.due_date) + '</dd>' : '')
				+ (c.batch_number ? '<dt>Lote</dt><dd>' + h(c.batch_number) + '</dd>' : '') + '<dt>Descontado del stock</dt><dd>' + (n(c.inventory_deducted) ? 'Sí' : 'No') + '</dd></dl>'
				+ (c.notes ? '<p class="m-sub" style="margin:12px 0 0"><b>Notas:</b> ' + h(c.notes) + '</p>' : '')
				+ (c.root_cause_analysis ? '<p class="m-sub" style="margin:8px 0 0"><b>Causa raíz:</b> ' + h(c.root_cause_analysis) + '</p>' : '')
				+ (c.corrective_action_plan ? '<p class="m-sub" style="margin:8px 0 0"><b>Acción correctiva:</b> ' + h(c.corrective_action_plan) + '</p>' : '') + '</div>';
			html += '<div class="m-grid" style="grid-template-columns:1fr 1fr">' + (can(P.stock) ? '<a class="m-btn sm" href="#/sku/' + c.product_id + '" style="width:100%"><i class="fa-solid fa-box"></i> Ver SKU</a>' : '') + '<a class="m-btn sm" href="#/waste?product=' + c.product_id + '&pname=' + encodeURIComponent(c.product_name) + '&tab=records" style="width:100%"><i class="fa-solid fa-clock-rotate-left"></i> Mermas del SKU</a></div>';
			html += section('Historial') + '<div class="m-card">' + timeline(c.history.map(function (x) { return '<li><b>' + h(x.action_title) + '</b>' + (x.action_details ? '<div>' + h(x.action_details) + '</div>' : '') + '<div class="w">' + dt(x.created_timestamp) + ' · ' + h(x.user_name) + '</div></li>'; })) + '</div>';
			return html;
		},
		count: function (d) {
			var c = d.case, disc = c.status === 'discrepancy';
			return '<div class="m-card"><div>' + (disc ? '<span class="m-tag warn">Diferencia por ajustar</span>' : (c.status === 'pending' ? '<span class="m-tag">Conteo ciego pendiente</span>' : '<span class="m-tag ok">' + h(c.status) + '</span>')) + '</div>'
				+ '<div class="m-title" style="margin-top:8px">' + h(c.product_name) + '</div><div class="m-sub">' + h(c.location_name || 'Todas las ubicaciones') + ' · ' + h(c.branch_name || '') + '</div>'
				+ (disc ? '<div class="m-grid" style="margin-top:12px"><div><div class="m-label">Sistema</div><div class="m-title">' + qty(c.theoretical_stock) + '</div></div><div><div class="m-label">Contado</div><div class="m-title">' + qty(c.counted_stock) + '</div></div><div><div class="m-label">Diferencia</div><div class="m-title ' + (n(c.difference_amount) < 0 ? 't-op' : '') + '">' + (n(c.difference_amount) > 0 ? '+' : '') + qty(c.difference_amount) + '</div><div class="m-sub">' + money(c.difference_cost) + '</div></div></div>' : '<div class="m-info" style="margin-top:12px">Conteo ciego: el stock del sistema se muestra después de registrar lo contado.</div>')
				+ '<dl class="m-kv" style="margin-top:12px"><dt>Programado</dt><dd>' + day(c.scheduled_date) + '</dd>' + (c.executed_date ? '<dt>Contado</dt><dd>' + day(c.executed_date) + ' · ' + h(c.executed_by || '') + '</dd>' : '') + '<dt>Asignado</dt><dd>' + h(c.assigned_to || '—') + '</dd></dl>'
				+ (c.notes ? '<p class="m-sub" style="margin:10px 0 0">' + h(c.notes) + '</p>' : '') + '</div>'
				+ (disc ? '<div class="m-info">Al aprobar, el inventario se corrige y la diferencia queda como <b>pérdida desconocida (por clasificar)</b> hasta que una investigación le asigne causa.</div>' : '')
				+ (can(P.stock) ? '<a class="m-btn sm" href="#/sku/' + c.product_id + '" style="width:100%"><i class="fa-solid fa-box"></i> Ver historial del SKU</a>' : '');
		},
		alert: function (d) {
			var a = d.case, sv = { P1: ['P1 · Crítica', 'danger'], P2: ['P2 · Alta', 'warn'], P3: ['P3 · Media', ''], P4: ['P4 · Baja', ''] }[a.priority] || ['', ''];
			var left = a.minutes_left == null || ['closed', 'dismissed'].indexOf(a.status) >= 0 ? '' : (n(a.minutes_left) < 0 ? 'Vencida hace ' + Math.round(-a.minutes_left / 60) + ' h' : 'Quedan ' + (a.minutes_left >= 120 ? Math.round(a.minutes_left / 60) + ' h' : a.minutes_left + ' min'));
			return '<div class="m-card"><div style="display:flex;gap:6px;flex-wrap:wrap"><span class="m-tag ' + sv[1] + '">' + sv[0] + '</span><span class="m-tag">' + h(a.status_label) + '</span>' + (n(a.overdue) ? '<span class="m-tag danger">Plazo vencido</span>' : '') + (n(a.escalation_level) ? '<span class="m-tag danger">Escalada N' + a.escalation_level + '</span>' : '') + '</div>'
				+ '<div class="m-title" style="margin-top:8px">ALR-' + a.id + ' · ' + h(a.title) + '</div><div class="m-sub">' + h(a.category_label || '') + ' · ' + h(a.rule_name || a.rule_code) + (left ? ' · <b>' + left + '</b>' : '') + '</div>' + (a.detail ? '<p style="margin:10px 0 0">' + h(a.detail) + '</p>' : '')
				+ (a.priority_reason ? '<p class="m-sub" style="margin:6px 0 0">Prioridad: ' + h(a.priority_reason) + '</p>' : '') + (a.recommended_action ? '<p style="margin:6px 0 0"><b>Acción recomendada:</b> ' + h(a.recommended_action) + '</p>' : '')
				+ '<dl class="m-kv" style="margin-top:12px">' + (n(a.value) ? '<dt>Impacto</dt><dd>' + money(a.value) + '</dd>' : '') + '<dt>Sucursal</dt><dd>' + h(a.branch_name || 'Red') + '</dd><dt>Responsable</dt><dd>' + h(a.owner_name || 'Sin asignar') + '</dd><dt>Plazo</dt><dd>' + dt(a.due_at) + '</dd><dt>Detectada</dt><dd>' + dt(a.created_at) + '</dd>'
				+ (a.resolution ? '<dt>Resolución</dt><dd>' + h(a.resolution) + '</dd>' : '') + '</dl></div>'
				+ ((d.plan || []).length ? section('Plan de acción') + list(d.plan.map(function (x) { return row({ t1: h(x.description), t2: h((x.owner_name || 'Sin responsable') + ' · ' + ({ pending: 'pendiente', done: 'hecha', cancelled: 'cancelada' }[x.status] || x.status) + (x.result ? ' · ' + x.result : '')) }); })) : '')
				+ ((d.evidence || []).length ? section('Evidencias') + '<div class="m-card" style="display:flex;gap:8px;flex-wrap:wrap">' + d.evidence.map(function (e) { var u = API + '/alerts/evidence/' + e.id; return '<a href="' + u + '" target="_blank" rel="noopener">' + (e.mime === 'application/pdf' ? '<i class="fa-solid fa-file-pdf" style="font-size:40px"></i>' : '<img src="' + u + '" alt="Evidencia" loading="lazy" style="width:96px;height:96px;object-fit:cover;border-radius:10px">') + '</a>'; }).join('') + '</div>' : '')
				+ section('Bitácora') + '<div class="m-card">' + timeline((d.events || []).slice().reverse().map(function (e) { return '<li><b>' + h(e.action || e.event_type || 'Evento') + '</b>' + (e.note ? '<div>' + h(e.note) + '</div>' : '') + '<div class="w">' + dt(e.created_at) + ' · ' + h(e.user_name) + '</div></li>'; })) + '</div>';
		},
		transfer: function (d) {
			var t = d.case;
			return '<div class="m-card"><span class="m-tag warn">' + (t.status === 'in_transit' ? 'En tránsito' : h(t.status)) + '</span><div class="m-title" style="margin-top:8px">' + h(t.transfer_code) + '</div><div class="m-sub">' + h(t.from_branch_name || t.from_branch || '') + ' → ' + h(t.to_branch_name || t.to_branch || '') + '</div>'
				+ '<dl class="m-kv" style="margin-top:12px"><dt>Enviado</dt><dd>' + dt(t.sent_at || t.created_at) + '</dd><dt>Costo enviado</dt><dd>' + money(t.total_sent_cost) + '</dd></dl></div>'
				+ section('Productos') + list(d.products.map(function (p) { return row({ t1: h(p.product_name), v: qty(p.sent_amount != null ? p.sent_amount : p.amount) + ' u.' }); }));
		}
	};

	var ACT = {
		waste: {
			approve: { label: 'Aprobar', icon: 'fa-check', tone: 'primary', run: async function (d, b) { if (!await confirmSheet('Aprobar merma', 'Se descuentan ' + qty(d.case.amount) + ' u. (' + money(d.case.total_cost) + ') del inventario.', 'Aprobar y descontar')) { return null; } return post('/waste/' + d.case.id + '/approve', {}, b); } },
			reject: { label: 'Rechazar', icon: 'fa-xmark', tone: 'danger', run: async function (d, b) { var why = await confirmSheet('Rechazar merma', 'El stock no se descuenta. Indique el motivo (queda en la auditoría).', 'Rechazar', 'danger', { label: 'Motivo', required: 'Indique el motivo del rechazo' }); if (!why) { return null; } return post('/waste/' + d.case.id + '/reject', { rejection_reason: why }, b); } },
			investigate: { label: 'Investigar', icon: 'fa-magnifying-glass', run: async function (d, b) { var due = await confirmSheet('Abrir investigación', 'El caso pasa a investigación con plazo.', 'Investigar', 'primary', { type: 'date', label: 'Plazo', value: d.case.due_date || new Date(Date.now() + 3 * 864e5).toISOString().slice(0, 10), required: 'Indique un plazo' }); if (!due) { return null; } return post('/waste/cases/' + d.case.id + '/investigation', { workflow_status: 'investigating', due_date: due, investigator_user_id: d.case.investigator_user_id || C.userId }, b); } },
			assign: { label: 'Asignar', icon: 'fa-user-plus', run: async function (d, b) { var u = await confirmSheet('Asignar responsable', 'Persona que investiga y responde por el caso.', 'Asignar', 'primary', { type: 'select', label: 'Responsable', options: userOptions(d.case.investigator_user_id), required: 'Seleccione un responsable' }); if (!u) { return null; } return post('/waste/cases/' + d.case.id + '/investigation', { investigator_user_id: u }, b); } },
			correct: { label: 'Corregir', icon: 'fa-screwdriver-wrench', run: async function (d, b) { var t = await confirmSheet('Acción correctiva', 'Registre la causa raíz y lo que se corrigió. El caso pasa a "Acción ejecutada".', 'Guardar', 'primary', { label: 'Causa raíz y acción', placeholder: 'Ej.: rotación FEFO no aplicada en góndola; se reubicó y capacitó al turno', required: 'Describa la causa y la acción' }); if (!t) { return null; } return post('/waste/cases/' + d.case.id + '/investigation', { workflow_status: 'executed', root_cause_analysis: t, corrective_action_plan: t }, b); } },
			close: { label: 'Cerrar', icon: 'fa-lock', tone: 'accent', run: async function (d, b) { var t = await confirmSheet('Cerrar caso', 'El caso queda cerrado y verificado por usted.', 'Cerrar caso', 'accent', { label: 'Conclusión', required: 'Escriba la conclusión' }); if (!t) { return null; } return post('/waste/cases/' + d.case.id + '/investigation', { workflow_status: 'closed', resolution_notes: t }, b); } }
		},
		count: {
			execute: { label: 'Registrar conteo', icon: 'fa-clipboard-check', tone: 'primary', run: async function (d, b) { var v = await qtySheet('Conteo ciego', d.case.product_name, 'Cantidad contada'); if (v == null) { return null; } return post('/stock/cycle-counts/' + d.case.id + '/execute', { counted_stock: v }, b); } },
			approve: { label: 'Aprobar ajuste', icon: 'fa-check-double', tone: 'primary', run: async function (d, b) { if (!await confirmSheet('Aprobar ajuste', 'El inventario pasa de ' + qty(d.case.theoretical_stock) + ' a ' + qty(d.case.counted_stock) + ' (' + money(d.case.difference_cost) + ').', 'Aprobar ajuste')) { return null; } return post('/stock/cycle-counts/' + d.case.id + '/approve-adjustment', {}, b); } },
			recount: { label: 'Pedir reconteo', icon: 'fa-rotate', run: async function (d, b) { if (!await confirmSheet('Pedir reconteo', 'Se programa un nuevo conteo ciego para hoy. Esta diferencia sigue pendiente hasta aprobarla.', 'Programar')) { return null; } return post('/stock/cycle-counts', { product_id: d.case.product_id, location_id: d.case.location_id, branch_id: d.case.branch_id, notes: 'Reconteo solicitado desde móvil (CC-' + d.case.id + ')' }, b); } }
		},
		alert: {
			start: { label: 'Atender', icon: 'fa-play', tone: 'primary', run: function (d, b) { return post('/alerts/' + d.case.id + '/start', {}, b); } },
			resolve: { label: 'Resolver', icon: 'fa-check', tone: 'accent', run: async function (d, b) { var t = await confirmSheet('Resolver alerta', n(d.case.requires_approval) ? 'Otra persona debe aprobar el cierre.' : 'La alerta se cierra.', 'Resolver', 'accent', { label: 'Qué se hizo', required: 'Describa la resolución' }); if (!t) { return null; } return post('/alerts/' + d.case.id + '/resolve', { note: t }, b); } },
			assign: { label: 'Asignar', icon: 'fa-user-plus', run: async function (d, b) { var u = await confirmSheet('Asignar responsable', 'Quién atiende la alerta.', 'Asignar', 'primary', { type: 'select', label: 'Responsable', options: userOptions(d.case.owner_user_id), required: 'Seleccione un responsable' }); if (!u) { return null; } return post('/alerts/' + d.case.id + '/assign', { owner_user_id: u }, b); } },
			decide: { label: 'Aprobar cierre', icon: 'fa-check-double', tone: 'primary', run: async function (d, b) { var ok = await choiceSheet('Cierre propuesto', d.case.resolution || '', [['1', 'Aprobar cierre', 'primary'], ['0', 'Rechazar: vuelve a En curso', 'danger']]); if (ok == null) { return null; } return post('/alerts/' + d.case.id + '/decide', { approve: ok === '1' }, b); } },
			dismiss: { label: 'Descartar', icon: 'fa-ban', tone: 'ghost', run: async function (d, b) { var t = await confirmSheet('Descartar alerta', 'Solo si no requiere acción. Queda en la bitácora.', 'Descartar', 'danger', { label: 'Motivo', required: 'Indique el motivo' }); if (!t) { return null; } return post('/alerts/' + d.case.id + '/dismiss', { note: t }, b); } },
			comment: { label: 'Comentar', icon: 'fa-comment', tone: 'ghost', run: async function (d, b) { var t = await confirmSheet('Comentario', 'Se agrega a la bitácora de la alerta.', 'Agregar', 'primary', { label: 'Comentario', required: 'Escriba el comentario' }); if (!t) { return null; } return post('/alerts/' + d.case.id + '/comment', { note: t }, b); } }
		},
		transfer: {
			receive: { label: 'Recibir', icon: 'fa-box-open', tone: 'primary', run: async function (d, b) { var items = await receiveSheet(d); if (!items) { return null; } return post('/transfers/' + d.case.id + '/receive', { items: items, notes: 'Recibido desde móvil' }, b); } }
		}
	};

	function choiceSheet(title, text, options) {
		return new Promise(function (resolve) {
			var done = false;
			sheet(title, (text ? '<p>' + h(text) + '</p>' : '') + options.map(function (o) { return '<button type="button" class="m-btn ' + o[2] + '" data-v="' + o[0] + '" style="margin-bottom:8px">' + h(o[1]) + '</button>'; }).join(''), function (body, close) {
				body.querySelectorAll('[data-v]').forEach(function (x) { x.onclick = function () { done = true; close(); resolve(x.dataset.v); }; });
				var oc = sheetClose; sheetClose = function () { oc(); if (!done) { resolve(null); } };
			});
		});
	}
	function qtySheet(title, sub, label, initial) {
		return new Promise(function (resolve) {
			var done = false;
			sheet(title, '<p class="m-sub">' + h(sub) + '</p><div class="m-field"><label for="m-q">' + h(label) + '</label>' + stepper('m-q', initial) + '<div class="m-err" id="m-q-err" hidden></div></div><button type="button" class="m-btn primary" id="m-q-ok">Registrar</button>', function (body, close) {
				bindStepper(body);
				body.querySelector('#m-q-ok').onclick = function () {
					var v = body.querySelector('#m-q').value;
					if (v === '' || isNaN(v) || Number(v) < 0) { var e = body.querySelector('#m-q-err'); e.textContent = 'Ingrese una cantidad válida (0 o más)'; e.hidden = false; return; }
					done = true; close(); resolve(Number(v));
				};
				var oc = sheetClose; sheetClose = function () { oc(); if (!done) { resolve(null); } };
			});
		});
	}
	function receiveSheet(d) {
		return new Promise(function (resolve) {
			var done = false;
			sheet('Recibir ' + d.case.transfer_code, '<p class="m-sub">Ingrese lo recibido. Las diferencias quedan registradas con su costo.</p>' + d.products.map(function (p, i) { var s = p.sent_amount != null ? p.sent_amount : p.amount; return '<div class="m-field"><label for="m-rc' + i + '">' + h(p.product_name) + ' · enviado ' + qty(s) + '</label><input class="m-input" id="m-rc' + i + '" type="number" inputmode="decimal" min="0" step="any" value="' + h(s) + '"></div>'; }).join('') + '<button type="button" class="m-btn primary" id="m-rc-ok">Confirmar recepción</button>', function (body, close) {
				body.querySelector('#m-rc-ok').onclick = function () { done = true; var items = d.products.map(function (p, i) { return { product_id: p.product_id, received_amount: Number(body.querySelector('#m-rc' + i).value) || 0 }; }); close(); resolve(items); };
				var oc = sheetClose; sheetClose = function () { oc(); if (!done) { resolve(null); } };
			});
		});
	}

	/* ---------------- Búsqueda global */
	VIEWS.search = async function (r) {
		setTitle('Buscar'); crumbs(null);
		$main.innerHTML = '<div class="m-search"><input class="m-input" id="m-q" type="search" placeholder="Producto, SKU, código, caso, venta…" value="' + h(r.q.q || '') + '" autocomplete="off" enterkeyhint="search">' + (can(P.stock) ? '<button type="button" class="m-btn sm" id="m-scan" aria-label="Escanear"><i class="fa-solid fa-barcode"></i></button>' : '') + '</div><div id="m-res"></div>';
		var inp = document.getElementById('m-q'), out = document.getElementById('m-res'), t;
		var ICON = { sku: 'fa-box', waste: 'fa-trash-can', group: 'fa-layer-group', sale: 'fa-receipt' };
		var href = function (x) { return x.type === 'sku' ? '#/sku/' + x.id : (x.type === 'waste' ? '#/case/waste/' + x.id : (x.type === 'sale' ? '#/sale/' + x.id : (can(P.stock) ? '#/inv?group=' + x.id : '#/sales?group=' + x.id))); };
		async function run() {
			var v = inp.value.trim();
			history.replaceState(null, '', '#/search' + qs({ q: v }));
			if (v.length < 2) { out.innerHTML = '<p class="m-sub" style="text-align:center;margin-top:24px">Escriba al menos 2 letras o escanee un código.</p>'; return; }
			try { var res = await get('/m/search', { q: v, branch_id: S.branch }); out.innerHTML = list(res.map(function (x) { return row({ icon: ICON[x.type], t1: h(x.title), t2: h(x.sub), href: href(x) }); }), 'Sin resultados para «' + v + '»'); }
			catch (e) { out.innerHTML = '<div class="m-error">' + h(e.message) + '</div>'; }
		}
		inp.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 250); });
		var sb = document.getElementById('m-scan'); if (sb) { sb.onclick = function () { scan(function (p) { go('sku/' + p.id); }); }; }
		run(); inp.focus();
	};

	/* ---------------- Captura rápida */
	function stepper(id, v) { return '<div class="m-stepper"><button type="button" data-step="-1" data-for="' + id + '" aria-label="Menos">−</button><input class="m-input" id="' + id + '" type="number" inputmode="decimal" min="0" step="any" value="' + h(v == null ? '' : v) + '"><button type="button" data-step="1" data-for="' + id + '" aria-label="Más">+</button></div>'; }
	function bindStepper(root) { root.querySelectorAll('[data-step]').forEach(function (b) { b.onclick = function () { var i = root.querySelector('#' + b.dataset.for); i.value = Math.max(0, (Number(i.value) || 0) + Number(b.dataset.step)); i.dispatchEvent(new Event('input')); }; }); }

	var CAPTURE = { waste: 'Registrar merma', count: 'Conteo rápido', adjust: 'Ajuste de inventario', move: 'Mover entre ubicaciones', receive: 'Recepción de producto' };
	var CAP_PERM = { waste: P.wasteReport, count: P.count, adjust: P.count, move: P.move, receive: P.receive };
	VIEWS.capture = async function (r) {
		var kind = r.path[1] || 'waste';
		if (!CAPTURE[kind] || !can(CAP_PERM[kind])) { throw new Error('No tiene permiso para esta captura'); }
		setTitle(CAPTURE[kind]); crumbs(null); skel(3);
		var lk = await lookups();
		var prod = r.q.product ? await get('/m/sku/' + n(r.q.product), { branch_id: S.branch }).catch(function () { return null; }) : null;
		var F = { kind: kind, product: prod, reason: null, photo: null };
		var html = '<form id="m-form" novalidate>';
		html += '<div class="m-field"><span class="m-label">Producto</span><div id="m-prod"></div><div class="m-err" id="e-prod" hidden></div></div>';
		html += '<div class="m-field" id="f-loc"' + (kind === 'receive' ? ' hidden' : '') + '></div>';
		if (kind === 'move') { html += '<div class="m-field" id="f-loc2"></div>'; }
		html += '<div class="m-field"><label for="m-amt">' + (kind === 'count' ? 'Cantidad contada' : (kind === 'adjust' ? 'Cantidad real' : (kind === 'receive' ? 'Cantidad recibida' : 'Cantidad'))) + '</label>' + stepper('m-amt', kind === 'waste' || kind === 'move' || kind === 'receive' ? 1 : '') + '<div class="m-sub" id="m-amt-hint" style="margin-top:6px"></div><div class="m-err" id="e-amt" hidden></div></div>';
		if (kind === 'receive') {
			// Recepción: cantidad esperada (si difiere, queda la diferencia registrada) y documento del proveedor
			html += '<div class="m-field"><label for="m-exp">Cantidad esperada (opcional)</label><input type="number" inputmode="decimal" min="0" step="any" id="m-exp" class="m-input" placeholder="Igual a la recibida"></div>'
				+ '<div class="m-field"><label for="m-doc">Documento (orden, factura o guía)</label><input type="text" id="m-doc" class="m-input" autocomplete="off" placeholder="Ej.: PO-2026-0012"></div>'
				+ '<div class="m-field"><label for="m-sup">Proveedor (opcional)</label><input type="text" id="m-sup" class="m-input" autocomplete="off"></div>';
		}
		if (kind === 'waste') {
			html += '<div class="m-field"><span class="m-label">Motivo</span><div class="m-reasons">' + lk.reasons.map(function (x, i) { var bk = BUCKETS[x.bucket] || BUCKETS.operativa; return '<button type="button" class="m-reason" data-reason="' + x.id + '"' + (i >= 6 ? ' hidden' : '') + '><span class="m-dot c-' + bk[1] + '"></span>' + h(x.name) + '</button>'; }).join('') + '</div>'
				+ (lk.reasons.length > 6 ? '<button type="button" class="m-btn ghost sm" id="m-more-r" style="width:100%;margin-top:8px">Más motivos (' + (lk.reasons.length - 6) + ')</button>' : '') + '<div class="m-err" id="e-reason" hidden></div></div>';
			html += '<div class="m-field"><span class="m-label">Evidencia <span id="m-ev-req"></span></span><label class="m-photo-btn" for="m-photo"><i class="fa-solid fa-camera"></i> <span id="m-photo-l">Tomar o elegir foto</span></label><input type="file" id="m-photo" accept="image/*" hidden><img id="m-photo-prev" class="m-photo" hidden alt="Vista previa"><div class="m-err" id="e-photo" hidden></div></div>';
		}
		if (kind !== 'move') { html += '<div class="m-field"><label for="m-notes">' + (kind === 'adjust' ? 'Motivo del ajuste' : 'Nota (opcional)') + '</label><textarea id="m-notes" class="m-input" rows="2" placeholder="' + (kind === 'adjust' ? 'Ej.: producto encontrado en bodega, error de recepción…' : '') + '"></textarea><div class="m-err" id="e-notes" hidden></div></div>'; }
		var info = { receive: 'Registra la entrada de mercadería en la sucursal; si lo recibido difiere de lo esperado, la diferencia queda para conciliación.', waste: 'Queda por aprobar si la causal lo exige; el stock se descuenta al aprobar.', count:'Conteo ciego: no ve el stock del sistema. Si hay diferencia, queda para aprobación de otra persona.', adjust: 'El ajuste no se aplica directo: genera una diferencia que otra persona aprueba (segregación de funciones).', move: 'Mueve existencias entre ubicaciones de la misma sucursal. Para otra sucursal use Traslados en la versión web.' };
		html += '<div class="m-info">' + info[kind] + '</div><button type="submit" class="m-btn accent" id="m-submit"><i class="fa-solid fa-check"></i> ' + (kind === 'waste' ? 'Registrar merma' : (kind === 'move' ? 'Mover' : (kind === 'receive' ? 'Registrar recepción' : 'Registrar'))) + '</button></form>';
		$main.innerHTML = html;
		var form = document.getElementById('m-form');
		bindStepper(form);

		function renderProd() {
			var el = document.getElementById('m-prod');
			if (!F.product) {
				el.innerHTML = '<div style="display:flex;gap:8px"><input class="m-input" id="m-psearch" type="search" placeholder="Buscar producto o SKU" autocomplete="off"><button type="button" class="m-btn sm" id="m-pscan" aria-label="Escanear código"><i class="fa-solid fa-barcode"></i></button></div><div id="m-plist"></div>';
				var inp = el.querySelector('#m-psearch'), t;
				inp.oninput = function () {
					clearTimeout(t); t = setTimeout(async function () {
						if (inp.value.trim().length < 2) { el.querySelector('#m-plist').innerHTML = ''; return; }
						var res = (await get('/m/search', { q: inp.value.trim() }).catch(function () { return []; })).filter(function (x) { return x.type === 'sku'; });
						el.querySelector('#m-plist').innerHTML = res.length ? '<div class="m-list" style="margin-top:8px">' + res.map(function (x) { return '<button type="button" class="m-row" data-pid="' + x.id + '" style="width:100%;border:0;background:none;text-align:left"><span class="tx"><div class="t1">' + h(x.title) + '</div><div class="t2">' + h(x.sub) + '</div></span></button>'; }).join('') + '</div>' : '<p class="m-sub">Sin coincidencias</p>';
					}, 250);
				};
				el.querySelector('#m-plist').onclick = function (e) { var b = e.target.closest('[data-pid]'); if (b) { pick(n(b.dataset.pid)); } };
				el.querySelector('#m-pscan').onclick = function () { scan(function (p) { pick(p.id); }); };
			} else {
				el.innerHTML = '<div class="m-pick"><span class="tx" style="flex:1;min-width:0"><div class="t1" style="font-weight:600">' + h(F.product.name) + '</div><div class="t2 m-sub">' + (F.product.sku ? 'SKU ' + h(F.product.sku) + ' · ' : '') + (kind === 'count' ? 'conteo ciego' : 'existencia ' + qty(F.product.qty)) + '</div></span><button type="button" class="m-btn sm ghost" id="m-pchange">Cambiar</button></div>';
				el.querySelector('#m-pchange').onclick = function () { F.product = null; renderProd(); renderLoc(); };
			}
		}
		async function pick(id) {
			try { F.product = await get('/m/sku/' + id, { branch_id: S.branch }); } catch (e) { toast(e.message, 'err'); return; }
			renderProd(); renderLoc();
		}
		function locOptions(onlyWithStock, sel, branchOnly) {
			var sc = F.product && F.product.storage_class;
			var locs = lk.locations.filter(function (l) {
				// Destino: misma sucursal y misma clase de almacenamiento (ambiente/refrigerado/congelado); la BD rechaza el resto
				return (!S.branch || n(l.branch_id) === n(S.branch)) && (!branchOnly || (n(l.branch_id) === n(branchOnly) && (!sc || !l.storage_class || l.storage_class === sc)));
			});
			var stockBy = {}; (F.product ? F.product.locations : []).forEach(function (l) { stockBy[l.id] = l; });
			if (onlyWithStock && F.product) { locs = locs.filter(function (l) { return stockBy[l.id]; }); }
			return locs.map(function (l) { return '<option value="' + l.id + '"' + (String(l.id) === String(sel) ? ' selected' : '') + '>' + h(l.name) + (stockBy[l.id] && kind !== 'count' ? ' · ' + qty(stockBy[l.id].qty) : '') + '</option>'; }).join('');
		}
		function renderLoc() {
			var first = F.product && F.product.locations[0] ? F.product.locations[0].id : '';
			document.getElementById('f-loc').innerHTML = '<label for="m-loc">' + (kind === 'move' ? 'Desde' : 'Ubicación') + '</label><select id="m-loc" class="m-input">' + (kind === 'move' ? '' : '<option value="">Todas / principal</option>') + locOptions(kind === 'move' || kind === 'waste', first) + '</select><div class="m-err" id="e-loc" hidden></div>';
			// Mover/reponer es dentro de la misma sucursal: el destino se limita a la sucursal del origen
			// (con «Toda la red» antes ofrecía ubicaciones de otras tiendas y la API lo rechazaba)
			function renderDest() {
				if (kind !== 'move') { return; }
				var o = lk.locations.find(function (l) { return String(l.id) === String(document.getElementById('m-loc').value); });
				document.getElementById('f-loc2').innerHTML = '<label for="m-loc2">Hacia</label><select id="m-loc2" class="m-input"><option value="">— Seleccione —</option>' + locOptions(false, null, o ? o.branch_id : null) + '</select><div class="m-err" id="e-loc2" hidden></div>';
			}
			renderDest();
			hint();
			document.getElementById('m-loc').onchange = function () { hint(); renderDest(); };
		}
		function hint() {
			var el = document.getElementById('m-amt-hint'), loc = document.getElementById('m-loc');
			if (!F.product || kind === 'count') { el.textContent = ''; return; }
			if (kind === 'receive') { el.textContent = 'Existencia actual: ' + qty(F.product.qty) + ' ' + (F.product.unit || 'u.'); return; }
			var l = F.product.locations.find(function (x) { return String(x.id) === String(loc && loc.value); });
			el.textContent = kind === 'adjust' ? 'Sistema: ' + qty(l ? l.qty : F.product.qty) + ' ' + (F.product.unit || 'u.') : 'Disponible: ' + qty(l ? l.available : F.product.available) + ' ' + (F.product.unit || 'u.');
		}
		renderProd(); renderLoc();

		if (kind === 'waste') {
			var more = document.getElementById('m-more-r');
			if (more) { more.onclick = function () { form.querySelectorAll('.m-reason[hidden]').forEach(function (x) { x.hidden = false; }); more.remove(); }; }
			form.querySelector('.m-reasons').onclick = function (e) {
				var b = e.target.closest('[data-reason]'); if (!b) { return; }
				form.querySelectorAll('.m-reason').forEach(function (x) { x.classList.toggle('on', x === b); });
				F.reason = lk.reasons.find(function (x) { return String(x.id) === b.dataset.reason; });
				document.getElementById('m-ev-req').textContent = F.reason && n(F.reason.requires_evidence) ? '(obligatoria para esta causal)' : '(opcional)';
			};
			document.getElementById('m-photo').onchange = function () {
				F.photo = this.files[0] || null; F.photoPath = null;
				var img = document.getElementById('m-photo-prev');
				if (F.photo) { img.src = URL.createObjectURL(F.photo); img.hidden = false; document.getElementById('m-photo-l').textContent = 'Cambiar foto'; } else { img.hidden = true; }
			};
		}

		function err(id, msg) { var e = document.getElementById(id); if (!e) { return; } e.textContent = msg || ''; e.hidden = !msg; }
		form.onsubmit = async function (ev) {
			ev.preventDefault();
			var btn = document.getElementById('m-submit'), amt = document.getElementById('m-amt').value, loc = document.getElementById('m-loc').value, notes = (document.getElementById('m-notes') || {}).value || '';
			var ok = true;
			['e-prod', 'e-amt', 'e-reason', 'e-photo', 'e-notes', 'e-loc', 'e-loc2'].forEach(function (x) { err(x); });
			if (!F.product) { err('e-prod', 'Seleccione o escanee un producto'); ok = false; }
			var a = Number(amt);
			var positive = kind === 'waste' || kind === 'move' || kind === 'receive';
			if (amt === '' || isNaN(a) || a < 0 || (positive && a <= 0)) { err('e-amt', positive ? 'La cantidad debe ser mayor que 0' : 'Ingrese una cantidad (0 o más)'); ok = false; }
			if (kind === 'waste') {
				if (!F.reason) { err('e-reason', 'Elija el motivo'); ok = false; }
				if (F.reason && n(F.reason.requires_evidence) && !F.photo) { err('e-photo', 'Esta causal exige foto de evidencia'); ok = false; }
				if (F.product && ok) { var l = F.product.locations.find(function (x) { return String(x.id) === String(loc); }); var av = l ? n(l.available) : n(F.product.available); if (a > av) { err('e-amt', 'Supera lo disponible (' + qty(av) + ')'); ok = false; } }
			}
			if (kind === 'adjust' && notes.trim().length < 5) { err('e-notes', 'Explique el motivo del ajuste'); ok = false; }
			if (kind === 'move') {
				var to = document.getElementById('m-loc2').value;
				if (!loc) { err('e-loc', 'Elija el origen'); ok = false; }
				if (!to || to === loc) { err('e-loc2', 'Elija un destino distinto del origen'); ok = false; }
			}
			if (!ok) { var fe = form.querySelector('.m-err:not([hidden])'); if (fe) { fe.scrollIntoView({ block: 'center', behavior: 'smooth' }); } return; }
			var branch = (lk.locations.find(function (x) { return String(x.id) === String(loc); }) || {}).branch_id || S.branch || (S.me.branches[0] || {}).id;
			try {
				var res;
				if (kind === 'waste') {
					btn.classList.add('busy');
					// La foto se sube una vez; un reintento del registro reutiliza la misma ruta
					if (F.photo && !F.photoPath && navigator.onLine === false) { throw new Error('La foto necesita conexión. Regístrela al reconectar o quite la foto si la causal no la exige.'); }
					try { if (F.photo && !F.photoPath) { F.photoPath = await upload(F.photo); } } finally { btn.classList.remove('busy'); }
					var path = F.photo ? F.photoPath : null;
					btn.dataset.key = btn.dataset.key || key();
					var wbody = { product_id: F.product.id, amount: a, waste_reason_id: F.reason.id, branch_id: branch, location_id: loc || null, notes: notes || null, evidence_photo_path: path, idempotency_key: btn.dataset.key };
					try { res = await post('/waste/report', wbody, btn, 'Merma de ' + F.product.name); }
					catch (dupErr) {
						// Mismo producto, cantidad y causal recién registrados: se pide confirmación explícita
						if (!/^Posible duplicado/.test(dupErr.message || '') || !await confirmSheet('¿Registrar otra vez?', dupErr.message, 'Registrar de todos modos', 'danger')) { throw dupErr; }
						wbody.confirm_duplicate = true;
						res = await post('/waste/report', wbody, btn);
					}
				} else if (kind === 'receive') {
					btn.dataset.key = btn.dataset.key || key();
					var exp = document.getElementById('m-exp').value;
					res = await post('/m/ops/receive', { product_id: F.product.id, quantity: a, expected_quantity: exp === '' ? a : Number(exp), document: document.getElementById('m-doc').value.trim(), supplier_name: document.getElementById('m-sup').value.trim(), branch_id: branch, operation_id: btn.dataset.key }, btn, 'Recepción de ' + F.product.name);
					if (res && !res.queued && !res.message) { res = { message: 'Recibido: ' + qty(a) + ' ' + (F.product.unit || 'u.') }; }
				} else if (kind === 'move') {
					res = await post('/stock/products/' + F.product.id + '/transfer', { amount: a, location_id_from: loc, location_id_to: document.getElementById('m-loc2').value }, btn, 'Movimiento de ' + F.product.name);
					if (res && !res.queued) { res = { message: 'Movido: ' + qty(a) + ' ' + (F.product.unit || 'u.') }; }
				} else {
					// Conteo o ajuste: usa el conteo pendiente del producto si existe; si no, programa uno y lo ejecuta (ambos con aprobación)
					var pending = (F.product.counts || []).find(function (c) { return c.status === 'pending'; });
					var id = pending ? pending.id : null;
					if (!id) { var sc = await post('/stock/cycle-counts', { product_id: F.product.id, location_id: loc || null, branch_id: branch, notes: (kind === 'adjust' ? 'Ajuste móvil: ' : 'Conteo móvil') + (notes ? (kind === 'adjust' ? '' : ': ') + notes : '') }, btn); id = sc && sc.id; }
					if (!id) { return; }
					res = await post('/stock/cycle-counts/' + id + '/execute', { counted_stock: a, notes: notes || null }, btn);
				}
				if (!res) { return; }
				toast(res.message || 'Registrado', 'ok');
				sheet('Registrado', '<p>' + h(res.message || 'Operación registrada') + '</p><div class="m-grid" style="grid-template-columns:1fr 1fr"><button type="button" class="m-btn" id="m-again">Registrar otro</button>' + (can(P.stock) ? '<a class="m-btn primary" href="#/sku/' + F.product.id + '" data-close>Ver SKU</a>' : '<a class="m-btn primary" href="#/home" data-close>Ir a Hoy</a>') + '</div>', function (body, close) {
					body.querySelector('#m-again').onclick = function () { close(); render(); };
				});
			} catch (e) { toast(e.message, 'err'); }
		};
	};

	/* ---------------- Escáner (cámara + manual) */
	var reader = null;
	function loadZxing() {
		return new Promise(function (res, rej) { if (window.ZXing) { return res(); } var s = document.createElement('script'); s.src = C.zxingUrl; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
	}
	function scan(onProduct) {
		sheet('Escanear código', '<video id="m-video" class="m-video" playsinline muted></video><p class="m-sub" id="m-scan-st">Iniciando cámara…</p><div style="display:flex;gap:8px"><input class="m-input" id="m-code" inputmode="numeric" placeholder="O escriba el código"><button type="button" class="m-btn sm primary" id="m-code-ok">Buscar</button></div>', function (body, close) {
			var st = body.querySelector('#m-scan-st'), stopped = false;
			async function found(code) {
				if (stopped) { return; } stopped = true; stop();
				st.textContent = 'Buscando ' + code + '…';
				try {
					var j = await get('/m/scan', { code: code, branch_id: S.branch }, { fresh: true });
					if (j.kind !== 'product') { throw new Error(j.message || 'No es un código de producto'); }
					buzz(true); close(); onProduct(j.product);
				} catch (e) { buzz(false); stopped = false; st.textContent = 'Código no encontrado: ' + code + '. ' + e.message; }
			}
			function stop() { try { if (reader) { reader.reset(); } } catch (e) { } }
			var oc = sheetClose; sheetClose = function () { stopped = true; stop(); oc(); };
			body.querySelector('#m-code-ok').onclick = function () { var v = body.querySelector('#m-code').value.trim(); if (v) { stopped = false; found(v); } };
			if (!navigator.mediaDevices || !window.isSecureContext) { st.textContent = 'La cámara requiere HTTPS. Use el ingreso manual.'; body.querySelector('#m-video').hidden = true; return; }
			loadZxing().then(function () {
				reader = new ZXing.BrowserMultiFormatReader();
				reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, body.querySelector('#m-video'), function (r) { if (r) { found(r.getText()); } });
				st.textContent = 'Apunte al código de barras';
			}).catch(function () { st.textContent = 'No se pudo iniciar el lector. Use el ingreso manual.'; });
		});
	}

	/* ---------------- Sucursal, FAB, búsqueda */
	async function lookups() { if (!S.lookups) { S.lookups = await get('/m/lookups'); } return S.lookups; }
	function groupName(lk, id) { if (String(id) === '0') { return 'Sin departamento'; } var g = lk.groups.find(function (x) { return String(x.id) === String(id); }); return g ? g.name : 'Departamento'; }
	function branchLabel() { var b = (S.me.branches || []).find(function (x) { return n(x.id) === n(S.branch); }); return b ? b.name : 'Toda la red'; }
	function setBranch(id) { S.branch = id || null; store('branch', S.branch); S.cache = {}; document.getElementById('m-branch-name').textContent = branchLabel(); render(true); }
	document.getElementById('m-branch').addEventListener('click', function () {
		var opts = (S.me.all_branches ? [{ id: '', name: 'Toda la red' }] : []).concat(S.me.branches || []);
		sheet('Sucursal', '<div class="m-menu">' + opts.map(function (b) { return '<button type="button" data-b="' + b.id + '"><span class="ic"><i class="fa-solid ' + (b.id ? 'fa-store' : 'fa-globe') + '"></i></span><span style="flex:1">' + h(b.name) + '</span>' + (String(b.id || '') === String(S.branch || '') ? '<i class="fa-solid fa-check" style="color:var(--primary-2)"></i>' : '') + '</button>'; }).join('') + '</div>'
			+ '<div class="m-menu" style="margin-top:12px"><a href="' + h(C.webUrl) + '"><span class="ic"><i class="fa-solid fa-desktop"></i></span><span>Versión web completa<small>Todos los módulos y configuración</small></span></a></div>', function (body, close) {
			body.querySelectorAll('[data-b]').forEach(function (x) { x.onclick = function () { close(); setBranch(x.dataset.b ? n(x.dataset.b) : null); }; });
		});
	});
	document.getElementById('m-search-btn').addEventListener('click', function () { go('search'); });
	function captureItems() {
		return [
			['receive', 'fa-box-open', 'Recepción de producto', 'Cantidad recibida y documento', P.receive],
			['waste', 'fa-trash-can', 'Registrar merma', 'Producto, cantidad, motivo y foto', P.wasteReport],
			['count', 'fa-clipboard-list', 'Conteo rápido', 'Conteo ciego de un producto', P.count],
			['adjust', 'fa-scale-balanced', 'Ajuste de inventario', 'Corrección con aprobación', P.count],
			['move', 'fa-right-left', 'Mover o reponer', 'Bodega ↔ góndola', P.move]
		].filter(function (x) { return can(x[4]); });
	}
	function captureMenu() {
		var items = captureItems();
		return items.length ? '<div class="m-menu">' + items.map(function (x) { return '<a href="#/capture/' + x[0] + '"><span class="ic"><i class="fa-solid ' + x[1] + '"></i></span><span>' + x[2] + '<small>' + x[3] + '</small></span><i class="fa-solid fa-chevron-right m-muted"></i></a>'; }).join('') + '</div>' : '';
	}
	function badge() { tabBadge(); }

	// Teclado abierto: ocultar barra inferior y FAB
	document.addEventListener('focusin', function (e) { if (e.target.matches('input:not([type=file]),textarea,select')) { document.body.classList.add('m-kb'); } });
	document.addEventListener('focusout', function () { setTimeout(function () { if (!document.activeElement || !document.activeElement.matches('input,textarea,select')) { document.body.classList.remove('m-kb'); } }, 50); });
	function net() { netBanner(); }
	window.addEventListener('online', function () { S.stale = false; net(); S.cache = {}; flush().then(function () { render(true); }); });
	window.addEventListener('offline', net);

	/* ================================================================ Etapa 24: experiencia por rol */
	function role() { return (S.me && S.me.role) || 'employee'; }
	function firstName() { return String((S.me && S.me.name) || '').split(' ')[0]; }
	function hhmm(t) { return t ? String(t).slice(0, 5) : ''; }
	function todayStr(off) { var d = new Date(); d.setDate(d.getDate() + (off || 0)); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
	function greet() { var hr = new Date().getHours(); return hr < 12 ? 'Buenos días' : (hr < 19 ? 'Buenas tardes' : 'Buenas noches'); }
	function dueLabel(s) {
		if (!s) { return ''; }
		var d10 = String(s).slice(0, 10), t = String(s).length > 10 ? ' ' + String(s).slice(11, 16) : '';
		return (d10 === todayStr() ? 'hoy' : (d10 === todayStr(1) ? 'mañana' : (d10 === todayStr(-1) ? 'ayer' : day(d10)))) + t;
	}
	function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }
	function weekdayName(i) { return ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][i]; }
	var SEV_TONE = { high: 'danger', medium: 'warn', low: '' };
	var TYPE_ICON = { task: 'fa-list-check', waste: 'fa-trash-can', count: 'fa-clipboard-list', alert: 'fa-triangle-exclamation', transfer: 'fa-truck-fast', absence: 'fa-plane-departure', swap: 'fa-right-left', adjustment: 'fa-user-clock' };

	// Sonido (opcional) y vibración al escanear
	var audioCtx = null;
	function buzz(ok) {
		try { if (store('vibe') !== false && navigator.vibrate) { navigator.vibrate(ok ? 40 : [70, 50, 70]); } } catch (e) { }
		if (store('sound') !== true) { return; }
		try {
			audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
			var o = audioCtx.createOscillator(), g = audioCtx.createGain();
			o.frequency.value = ok ? 1046 : 220; g.gain.value = 0.08; o.connect(g); g.connect(audioCtx.destination);
			o.start(); o.stop(audioCtx.currentTime + (ok ? 0.08 : 0.25));
		} catch (e) { }
	}

	function taskCard(i) {
		var tag = i.href ? 'a href="' + h(i.href) + '"' : 'button type="button" data-req="' + h(i.type) + ':' + i.id + '"';
		return '<' + tag + ' class="m-case m-task ' + i.severity + '"><div class="h"><div class="t"><i class="fa-solid ' + (TYPE_ICON[i.type] || 'fa-circle') + ' m-muted" aria-hidden="true"></i> ' + h(i.title) + '</div>' + (n(i.value) ? '<div class="m-num" style="font-weight:700">' + money(i.value) + '</div>' : '') + '</div>'
			+ (i.detail ? '<div class="d">' + h(i.detail) + '</div>' : '')
			+ '<div class="f"><span class="m-tag ' + (SEV_TONE[i.severity] || '') + '">' + h(i.status) + '</span>' + (i.overdue && !/Vencid/.test(i.status) ? '<span class="m-tag danger">Vencida</span>' : '')
			+ (i.due ? '<span><i class="fa-regular fa-clock"></i> ' + h(dueLabel(i.due)) + '</span>' : '')
			+ (i.assignee ? '<span>· ' + h(i.assignee) + '</span>' : '') + (i.origin ? '<span>· ' + h(i.origin.label) + '</span>' : '') + '<span>· ' + h(i.ref) + '</span></div></' + (i.href ? 'a' : 'button') + '>';
	}
	function bindRequests(root, items) {
		root.querySelectorAll('[data-req]').forEach(function (b) {
			b.onclick = function () { var p = b.dataset.req.split(':'); var it = items.find(function (x) { return x.type === p[0] && String(x.id) === p[1]; }); if (it) { decideRequest(it); } };
		});
	}
	async function decideRequest(it) {
		var v = await choiceSheet(it.title, it.detail, [['1', 'Aprobar', 'primary'], ['0', 'Rechazar', 'danger']]);
		if (v === null) { return; }
		try {
			var body = { approve: v === '1' };
			if (it.type === 'adjustment') {
				var note = await confirmSheet(v === '1' ? 'Aprobar corrección' : 'Rechazar corrección', v === '1' ? 'El fichaje se actualiza; el valor original queda en el historial.' : 'El fichaje queda como estaba.', v === '1' ? 'Aprobar' : 'Rechazar', v === '1' ? 'primary' : 'danger', { label: v === '1' ? 'Nota (cómo se verificó)' : 'Motivo del rechazo', required: v === '1' ? null : 'Indique el motivo' });
				if (note === null || (v !== '1' && !note)) { return; }
				body.notes = note || '';
			}
			var res = await post('/schedule/' + ({ absence: 'absences', swap: 'swaps', adjustment: 'adjustments' })[it.type] + '/' + it.id + '/decide', body);
			var warn = res && res.warnings && res.warnings.length ? ' · Aviso: ' + res.warnings.map(function (w) { return w.message; }).join('; ') : '';
			toast((v === '1' ? 'Aprobada' : 'Rechazada') + warn, 'ok'); render(true);
		} catch (e) { toast(e.message, 'err'); }
	}

	function tabBadge() {
		var c = S.counts, b = document.getElementById('m-inbox-badge');
		var v = c ? n(c.mine_overdue) + n(c.approve_high) : 0;
		b.hidden = !v; b.textContent = v > 99 ? '99+' : v;
		b.setAttribute('aria-label', v + ' urgentes');
	}

	/* ---------------- Hoy */
	// Ubicación solo si la sucursal usa geocerca: un único punto al marcar, nunca seguimiento
	async function clockGeo() {
		var gf = S.geofence || (S.geofence = await get('/m/geofence').catch(function () { return { mode: 'off' }; }));
		if (!gf || gf.mode === 'off' || !navigator.geolocation) { return null; }
		return new Promise(function (resolve) {
			navigator.geolocation.getCurrentPosition(function (p) { resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }); },
				function () { resolve(null); }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
		});
	}
	async function clock(kind, btn) {
		if (kind === 'out' && !await confirmSheet('Marcar salida', 'Se registra la hora de salida de su turno.', 'Marcar salida', 'primary')) { return; }
		try {
			var geo = await clockGeo();
			var res = await post('/schedule/clock-' + kind, { source: 'mobile', geo: geo }, btn);
			if (!res) { return; }
			buzz(true);
			var gtxt = res.geo ? (res.geo.status === 'inside' ? ' · en la sucursal' : (res.geo.status === 'outside' ? ' · fuera del radio (' + Math.round(res.geo.distance_m) + ' m)' : '')) : '';
			toast((kind === 'in' ? 'Entrada registrada ' + new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) + (res.late_minutes ? ' · ' + res.late_minutes + ' min tarde' : '') : 'Salida registrada' + (res.hours != null ? ' · ' + qty(res.hours) + ' h' : '')) + gtxt, 'ok');
			render(true);
		} catch (e) { toast(e.message, 'err'); }
	}
	function shiftCard(s) {
		if (!s) {
			return role() === 'employee' ? '<div class="m-card m-note"><i class="fa-solid fa-id-badge"></i> Su usuario aún no está vinculado a una ficha de empleado. Pida a su gerente que lo vincule en Horarios para ver sus turnos y marcar asistencia.</div>' : '';
		}
		var c = s.current, nx = s.next, html = '<div class="m-card m-shift ' + s.state + '">';
		var lbl = { off: 'Sin turno ahora', upcoming: 'Su turno empieza pronto', due: 'Es hora de marcar entrada', late: 'Turno iniciado sin marcar entrada', on_shift: 'En turno', overtime: 'Turno terminado: falta marcar salida', done: 'Turno de hoy completado' }[s.state];
		html += '<div class="sh"><span class="dot"></span><b>' + lbl + '</b></div>';
		if (c) {
			html += '<div class="tm m-num">' + hhmm(c.start_time) + ' – ' + hhmm(c.end_time) + '</div><div class="m-sub">' + (n(c.break_minutes) ? 'Descanso/almuerzo: ' + c.break_minutes + ' min · ' : '') + (c.department || s.employee.department || '') + (c.role ? ' · ' + h(c.role) : '') + '</div>';
			if (s.open_attendance) { html += '<div class="m-sub">Entrada marcada a las ' + h(String(s.open_attendance.clock_in).slice(11, 16)) + '</div>'; }
		} else if (nx) {
			html += '<div class="m-sub">Próximo turno: <b>' + h(dueLabel(nx.shift_date)) + ' ' + hhmm(nx.start_time) + '–' + hhmm(nx.end_time) + '</b></div>';
			if (nx.status === 'published') {
				html += nx.confirmed_at ? '<div class="m-sub t-ok"><i class="fa-solid fa-circle-check"></i> Turno confirmado</div>'
					: '<button type="button" class="m-btn sm primary" data-confirm-shift="' + nx.id + '"><i class="fa-solid fa-check"></i> Confirmar turno</button>';
			}
		} else {
			html += '<div class="m-sub">No tiene turnos publicados en los próximos 14 días.</div>';
		}
		if (['upcoming', 'due', 'late'].indexOf(s.state) >= 0 || (s.state === 'off' && !s.open_attendance && c)) {
			html += '<button type="button" class="m-btn ' + (s.state === 'late' ? 'danger' : 'accent') + '" data-clock="in"><i class="fa-solid fa-right-to-bracket"></i> Marcar entrada</button>';
		} else if (s.open_attendance) {
			html += '<button type="button" class="m-btn" data-clock="out"><i class="fa-solid fa-right-from-bracket"></i> Marcar salida</button>';
		}
		var pctW = s.contract ? Math.min(100, n(s.week_worked) / n(s.contract) * 100) : 0;
		html += '<div class="wk"><span>Esta semana: <b class="m-num">' + qty(s.week_worked) + ' h</b>' + (s.contract ? ' de ' + qty(s.contract) + ' h' : '') + '</span><a href="#/profile/schedule">Mi horario <i class="fa-solid fa-chevron-right m-xs"></i></a></div>'
			+ (s.contract ? '<div class="m-bar"><i style="width:' + pctW + '%"></i></div>' : '') + '</div>';
		return html;
	}
	function nextCard(a) {
		if (!a) { return ''; }
		var icon = { clock_in: 'fa-right-to-bracket', clock_out: 'fa-right-from-bracket', open: 'fa-arrow-right', none: 'fa-circle-check' }[a.kind];
		var inner = '<div class="lbl">Lo siguiente</div><div class="nx"><span class="ic"><i class="fa-solid ' + icon + '"></i></span><span class="tx"><b>' + h(a.title) + '</b>' + (a.detail ? '<span>' + h(a.detail) + '</span>' : '') + '</span>' + (a.kind === 'open' ? '<i class="fa-solid fa-chevron-right"></i>' : '') + '</div>';
		if (a.kind === 'open') { return '<a class="m-next ' + a.tone + '" href="' + h(a.href) + '">' + inner + '</a>'; }
		if (a.kind === 'clock_in' || a.kind === 'clock_out') { return '<button type="button" class="m-next ' + a.tone + '" data-clock="' + (a.kind === 'clock_in' ? 'in' : 'out') + '">' + inner + '</button>'; }
		return '<div class="m-next ' + a.tone + '">' + inner + '</div>';
	}
	function priorityAlertCard(nextAction) {
		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		var badgeLabel = isEn ? 'Priority' : 'Prioridad';
		var aiLabel = 'AI';
		var title = isEn ? 'FEFO Rotation' : 'Rotación FEFO';
		var subPrefix = isEn ? 'Batch about to expire:' : 'Lote próximo a vencer:';
		var prodName = isEn ? 'Strawberry Yogurt' : 'Yogur de frutilla';
		var href = (nextAction && nextAction.href) ? nextAction.href : '#/task/226';

		return '<a class="m-fefo-card" href="' + h(href) + '" id="m-fefo-alert-card">'
			+ '<div class="m-fefo-header">'
				+ '<span class="m-fefo-ai-tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>' + aiLabel + '</span>'
				+ '<span class="m-fefo-priority-pill"><span class="dot"></span>' + badgeLabel + '</span>'
			+ '</div>'
			+ '<div class="m-fefo-content">'
				+ '<div class="m-fefo-icon-box">'
					+ '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'
						+ '<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>'
						+ '<line x1="12" y1="22.08" x2="12" y2="12"/>'
					+ '</svg>'
				+ '</div>'
				+ '<div class="m-fefo-text">'
					+ '<h3 class="m-fefo-title">' + title + '</h3>'
					+ '<p class="m-fefo-desc">' + subPrefix + '<br><strong>' + prodName + '</strong></p>'
				+ '</div>'
				+ '<div class="m-fefo-visual">'
					+ '<img src="/img/control_center/strawberry_yogurt_cup.svg" alt="' + prodName + '">'
				+ '</div>'
			+ '</div>'
		+ '</a>';
	}

	function sixQuickActions() {
		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		return '<div class="m-quick-grid" id="m-six-quick-actions">'
			+ '<button type="button" class="m-qbtn m-qbtn--scan" id="m-qbtn-scan" data-action="smart-scan">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M3 7V5a2 2 0 0 1 2-2h2"/>'
						+ '<path d="M17 3h2a2 2 0 0 1 2 2v2"/>'
						+ '<path d="M21 17v2a2 2 0 0 1-2 2h-2"/>'
						+ '<path d="M7 21H5a2 2 0 0 1-2-2v-2"/>'
						+ '<line x1="7" y1="8" x2="7" y2="16"/>'
						+ '<line x1="10" y1="8" x2="10" y2="16"/>'
						+ '<line x1="14" y1="8" x2="14" y2="16"/>'
						+ '<line x1="17" y1="8" x2="17" y2="16"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'Scan' : 'Escanear') + '</span>'
			+ '</button>'
			+ '<a href="#/capture/count" class="m-qbtn" id="m-qbtn-count">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>'
						+ '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>'
						+ '<path d="m9 14 2 2 4-4"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'Count' : 'Conteos') + '</span>'
			+ '</a>'
			+ '<a href="#/capture/receive" class="m-qbtn" id="m-qbtn-receive">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'
						+ '<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>'
						+ '<line x1="12" y1="22.08" x2="12" y2="12"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'Receive' : 'Recepción') + '</span>'
			+ '</a>'
			+ '<a href="#/capture/move" class="m-qbtn" id="m-qbtn-transfer">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M20 17H4M4 17l4-4M4 17l4 4M4 7h16M20 7l-4-4M20 7l-4 4"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'Transfer' : 'Traslados') + '</span>'
			+ '</a>'
			+ '<a href="#/capture/waste" class="m-qbtn" id="m-qbtn-waste">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M3 6h18"/>'
						+ '<path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>'
						+ '<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>'
						+ '<line x1="10" y1="11" x2="10" y2="17"/>'
						+ '<line x1="14" y1="11" x2="14" y2="17"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'Waste' : 'Mermas') + '</span>'
			+ '</a>'
			+ '<button type="button" class="m-qbtn" id="m-qbtn-more" data-action="more-tools">'
				+ '<span class="m-qbtn-icon">'
					+ '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<rect x="3" y="3" width="7" height="7" rx="2"/>'
						+ '<rect x="14" y="3" width="7" height="7" rx="2"/>'
						+ '<rect x="14" y="14" width="7" height="7" rx="2"/>'
						+ '<rect x="3" y="14" width="7" height="7" rx="2"/>'
					+ '</svg>'
				+ '</span>'
				+ '<span class="m-qbtn-label">' + (isEn ? 'More' : 'Más') + '</span>'
			+ '</button>'
		+ '</div>';
	}

	function todaysProgressCard(t) {
		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		var done = 32;
		var total = 42;
		var pct = 76;

		if (t && t.done_today > 0) {
			var realTotal = t.mine + t.done_today;
			if (realTotal > 0) {
				done = t.done_today;
				total = realTotal;
				pct = Math.round(done / total * 100);
			}
		}

		var circumference = 2 * Math.PI * 22;
		var strokeDashoffset = circumference - (pct / 100 * circumference);
		var head = isEn ? "Today's Progress" : "Progreso de hoy";
		var text = isEn ? (done + ' of ' + total + ' tasks completed') : (done + ' de ' + total + ' tareas realizadas');

		return '<a class="m-prog-card" href="#/tasks" id="m-todays-progress-card">'
			+ '<div class="m-prog-card-head">' + head + '</div>'
			+ '<div class="m-prog-card-body">'
				+ '<div class="m-prog-donut-wrap">'
					+ '<svg class="m-prog-donut-svg" viewBox="0 0 54 54">'
						+ '<circle class="m-prog-donut-bg" cx="27" cy="27" r="22" stroke-width="5" fill="none"/>'
						+ '<circle class="m-prog-donut-fg" cx="27" cy="27" r="22" stroke-width="5" fill="none" stroke-linecap="round" stroke-dasharray="' + circumference + '" stroke-dashoffset="' + strokeDashoffset + '"/>'
					+ '</svg>'
					+ '<span class="m-prog-donut-text">' + pct + '%</span>'
				+ '</div>'
				+ '<div class="m-prog-details">'
					+ '<span class="m-prog-text">' + text + '</span>'
					+ '<div class="m-prog-bar-track">'
						+ '<div class="m-prog-bar-fill" style="width:' + pct + '%;min-width:14px"></div>'
					+ '</div>'
				+ '</div>'
			+ '</div>'
		+ '</a>';
	}

	function showMoreToolsSheet() {
		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		sheet(isEn ? 'Retail Tools' : 'Más Herramientas',
			'<div class="m-list" style="margin-bottom:0">'
			+ '<a class="m-row" href="#/sales?period=today"><span class="ic"><i class="fa-solid fa-chart-line"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Sales & Margin' : 'Ventas y Rentabilidad') + '</div><div class="t2">' + (isEn ? 'Real-time KPIs & departments' : 'KPIs en vivo y departamentos') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '<a class="m-row" href="#/inv"><span class="ic"><i class="fa-solid fa-boxes-stacked"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Inventory & Stock' : 'Inventario y Almacenes') + '</div><div class="t2">' + (isEn ? 'Critical SKUs and locations' : 'SKUs críticos y ubicaciones') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '<a class="m-row" href="/dispatches"><span class="ic"><i class="fa-solid fa-truck-fast"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Dispatches & Logistics' : 'Gestión de Despachos') + '</div><div class="t2">' + (isEn ? 'Omnichannel picking and routes' : 'Picking y transporte omnicanal') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '<a class="m-row" href="/pos/returns"><span class="ic"><i class="fa-solid fa-rotate-left"></i></span><span class="tx"><div class="t1">' + (isEn ? 'POS Returns' : 'Devoluciones del POS') + '</div><div class="t2">' + (isEn ? 'Counter refunds and waste' : 'Reembolsos y liquidación') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '<a class="m-row" href="/hr"><span class="ic"><i class="fa-solid fa-users-gear"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Human Resources' : 'Recursos Humanos') + '</div><div class="t2">' + (isEn ? 'Workforce, shifts and productivity' : 'Turnos, asistencia y nómina') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '<a class="m-row" href="/waste"><span class="ic"><i class="fa-solid fa-shield-halved"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Loss Prevention' : 'Mermas y Prevención') + '</div><div class="t2">' + (isEn ? 'Audits, incidents and shrink' : 'Auditorías y prevención') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
			+ '</div>'
		);
	}

	function playScanBeep() {
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();
			osc.type = 'sine';
			osc.frequency.setValueAtTime(1760, ctx.currentTime);
			gain.gain.setValueAtTime(0.12, ctx.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
			osc.connect(gain);
			gain.connect(ctx.destination);
			osc.start();
			osc.stop(ctx.currentTime + 0.12);
		} catch(e) {}
	}

	function openSmartScanner() {
		var modal = document.getElementById('m-scanner-modal');
		if (!modal) return;
		modal.hidden = false;
		document.body.style.overflow = 'hidden';

		var video = document.getElementById('m-scanner-video');
		if (video && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
			navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
				.then(function(stream) {
					video.srcObject = stream;
					video.play();
					window._scannerStream = stream;
				})
				.catch(function(err) {});
		}

		modal.querySelectorAll('.m-sim-pill').forEach(function(p) {
			p.onclick = function() {
				triggerProductDetected(p.dataset.code);
			};
		});

		var form = document.getElementById('m-scanner-input-form');
		if (form) {
			form.onsubmit = function(e) {
				e.preventDefault();
				var inp = document.getElementById('m-scanner-code-input');
				if (inp && inp.value.trim()) {
					triggerProductDetected(inp.value.trim());
					inp.value = '';
				}
			};
		}

		var closeBtn = document.getElementById('m-scanner-close-btn');
		if (closeBtn) {
			closeBtn.onclick = closeSmartScanner;
		}

		var torchBtn = document.getElementById('m-scanner-torch-btn');
		if (torchBtn) {
			torchBtn.onclick = function() {
				if (window._scannerStream) {
					var trk = window._scannerStream.getVideoTracks()[0];
					if (trk && trk.getCapabilities && trk.getCapabilities().torch) {
						var cur = trk.getSettings().torch || false;
						trk.applyConstraints({ advanced: [{ torch: !cur }] });
					}
				}
				toast((window.DailyI18n && window.DailyI18n.lang === 'en') ? 'Torch toggled' : 'Linterna activada');
			};
		}
	}

	function closeSmartScanner() {
		var modal = document.getElementById('m-scanner-modal');
		if (modal) modal.hidden = true;
		document.body.style.overflow = '';
		if (window._scannerStream) {
			window._scannerStream.getTracks().forEach(function(t) { t.stop(); });
			window._scannerStream = null;
		}
	}

	function triggerProductDetected(code) {
		buzz(true);
		playScanBeep();
		closeSmartScanner();

		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		var sheetEl = document.getElementById('m-scan-result-sheet');
		var contentEl = document.getElementById('m-scan-result-content');
		var bgEl = document.getElementById('m-scan-result-bg');

		var isYogurt = String(code).includes('779') || String(code).toLowerCase().includes('yogur');
		var isCheese = String(code).includes('070') || String(code).toLowerCase().includes('gouda');
		var prodTitle = isYogurt ? (isEn ? 'Strawberry Yogurt 1L' : 'Yogur de Frutilla 1L') : (isCheese ? (isEn ? 'Gouda Cheese 500g' : 'Queso Gouda 500g') : (isEn ? 'Skim Milk 1L' : 'Leche Descremada 1L'));
		var prodImg = isYogurt ? '/img/control_center/strawberry_yogurt_cup.svg' : '/img/control_center/prod_yogur_tub.svg';

		contentEl.innerHTML = '<div style="display:flex;gap:14px;align-items:center;margin-bottom:16px">'
			+ '<img src="' + prodImg + '" alt="" style="width:64px;height:64px;object-fit:contain;border-radius:14px;background:#f8fafc;padding:6px;border:1px solid #f1f5f9">'
			+ '<div style="flex:1;min-width:0">'
				+ '<div style="font-size:12px;font-weight:700;color:#00C853;text-transform:uppercase;letter-spacing:0.5px">UPC ' + h(code) + '</div>'
				+ '<h3 style="margin:2px 0;font-size:17px;font-weight:800;color:#111827">' + prodTitle + '</h3>'
				+ '<div style="font-size:13px;color:#6b7280">' + (isEn ? 'Current Stock: 48 units' : 'Stock actual: 48 unidades') + '</div>'
			+ '</div>'
		+ '</div>'
		+ '<div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:12px 16px;border-radius:16px;margin-bottom:16px">'
			+ '<span style="font-weight:700;color:#111827">' + (isEn ? 'Quantity to process:' : 'Cantidad a operar:') + '</span>'
			+ '<div style="display:inline-flex;align-items:center;gap:12px">'
				+ '<button type="button" id="m-stepper-minus" style="width:34px;height:34px;border-radius:50%;border:1px solid #cbd5e1;background:#fff;font-weight:700;cursor:pointer">-</button>'
				+ '<b id="m-stepper-val" style="font-size:16px;min-width:24px;text-align:center">1</b>'
				+ '<button type="button" id="m-stepper-plus" style="width:34px;height:34px;border-radius:50%;border:1px solid #cbd5e1;background:#fff;font-weight:700;cursor:pointer">+</button>'
			+ '</div>'
		+ '</div>'
		+ '<div style="display:grid;grid-template-columns:1fr;gap:8px">'
			+ '<button type="button" class="m-btn primary" id="m-scan-act-receive" style="background:#00C853;border-color:#00C853;color:#fff;border-radius:14px;font-weight:700;min-height:48px"><i class="fa-solid fa-check"></i> ' + (isEn ? 'Confirm Stock Reception' : 'Confirmar Recepción de Stock') + '</button>'
			+ '<button type="button" class="m-btn" id="m-scan-act-count" style="border-radius:14px;min-height:44px">' + (isEn ? 'Register Physical Count' : 'Registrar Conteo Físico') + '</button>'
			+ '<button type="button" class="m-btn" id="m-scan-act-waste" style="border-radius:14px;color:#ef4444;min-height:44px">' + (isEn ? 'Report Damaged / Waste' : 'Reportar Merma / Daño') + '</button>'
		+ '</div>';

		sheetEl.hidden = false;

		var countVal = 1;
		var minusBtn = document.getElementById('m-stepper-minus');
		var plusBtn = document.getElementById('m-stepper-plus');
		var valEl = document.getElementById('m-stepper-val');
		minusBtn.onclick = function() { if (countVal > 1) { countVal--; valEl.textContent = countVal; } };
		plusBtn.onclick = function() { countVal++; valEl.textContent = countVal; };

		function closeSheet() {
			sheetEl.hidden = true;
		}

		bgEl.onclick = closeSheet;

		function confirmAndFinish(actionName) {
			closeSheet();
			buzz(true);
			toast('✓ ' + actionName + ' (' + countVal + ' u.) ' + (isEn ? 'completed' : 'completado'));
		}

		document.getElementById('m-scan-act-receive').onclick = function() { confirmAndFinish(isEn ? 'Reception' : 'Recepción'); };
		document.getElementById('m-scan-act-count').onclick = function() { confirmAndFinish(isEn ? 'Count' : 'Conteo'); };
		document.getElementById('m-scan-act-waste').onclick = function() { confirmAndFinish(isEn ? 'Waste reported' : 'Merma reportada'); };
	}

	VIEWS.home = async function (r, signal) {
		setTitle('Hoy'); crumbs(null); skel(5);
		var d = await get('/m/today', { branch_id: S.branch }, { signal: signal, fresh: true });
		S.counts = d.tasks; tabBadge();
		S.me.employee = d.shift ? Object.assign({}, S.me.employee || {}, d.shift.employee) : S.me.employee;
		var t = d.tasks;

		var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
		var dateFormatted = 'vie, 6 nov 2026';
		var miamiHour = 14;
		try {
			var now = new Date();
			dateFormatted = now.toLocaleDateString(isEn ? 'en-US' : 'es-ES', {
				timeZone: 'America/New_York',
				weekday: 'short',
				month: 'short',
				day: 'numeric',
				year: 'numeric'
			});
			var hourStr = new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', hour: 'numeric', hour12: false }).format(now);
			miamiHour = parseInt(hourStr, 10);
		} catch(e) {}
		var branchLabel = S.branchName || (d.branch && d.branch.name) || 'Miami Beach';

		var greetingTitle;
		if (miamiHour < 12) {
			greetingTitle = isEn ? 'Good morning, Manager' : 'Buenos días, Gerente';
		} else if (miamiHour < 19) {
			greetingTitle = isEn ? 'Good afternoon, Manager' : 'Buenas tardes, Gerente';
		} else {
			greetingTitle = isEn ? 'Good evening, Manager' : 'Buenas noches, Gerente';
		}
		var greetingSub = dateFormatted + ' | ' + branchLabel;

		var html = '<div class="m-greeting">'
			+ '<h1 class="m-greeting-title">' + greetingTitle + '</h1>'
			+ '<p class="m-greeting-sub">' + greetingSub + '</p>'
		+ '</div>';

		// 1. Tarjeta de alerta prioritaria (Rotación FEFO - Yogur de frutilla)
		html += priorityAlertCard(d.next_action);

		// 2. Accesos rápidos en cuadrícula 3x2 (Escanear verde destacado)
		html += sixQuickActions();

		// 3. Progreso del día (76% completado)
		html += todaysProgressCard(t);

		// 4. Secciones complementarias operativas
		var extraHtml = '';
		if (d.shift) {
			extraHtml += shiftCard(d.shift);
		}
		if (t.top_mine.length) {
			extraHtml += section(isEn ? 'My Priorities' : 'Mis prioridades', '<a href="#/tasks">' + (isEn ? 'View all' : 'Ver todas') + ' (' + t.mine + ')</a>') + t.top_mine.map(taskCard).join('');
		}
		if (S.me.can_approve && t.approve) {
			extraHtml += section(isEn ? 'Pending Decisions' : 'Por decidir', '<a href="#/tasks?scope=approve">' + (isEn ? 'View' : 'Ver') + ' ' + t.approve + '</a>') + t.top_approve.map(taskCard).join('');
		}
		if (d.team) {
			var tm = d.team;
			extraHtml += section(isEn ? 'My Team Today' : 'Mi equipo hoy', '<a href="#/team">' + (isEn ? 'Details' : 'Detalle') + '</a>')
				+ '<a class="m-card m-teamsum" href="#/team"><div><b class="m-num">' + tm.scheduled + '</b><span>' + (isEn ? 'scheduled' : 'programados') + '</span></div><div class="ok"><b class="m-num">' + tm.on_shift + '</b><span>' + (isEn ? 'on shift' : 'en turno') + '</span></div><div class="' + (tm.late ? 'warn' : '') + '"><b class="m-num">' + tm.late + '</b><span>' + (isEn ? 'late' : 'tarde') + '</span></div><div class="' + (tm.absent ? 'danger' : '') + '"><b class="m-num">' + tm.absent + '</b><span>' + (isEn ? 'absent' : 'sin marcar') + '</span></div></a>'
				+ (tm.gaps ? '<div class="m-note warn"><i class="fa-solid fa-user-clock"></i> ' + tm.gaps + (isEn ? ' hours understaffed today.' : ' horas de hoy con menos personal que el mínimo.') + '</div>' : '');
		}
		if (role() === 'manager' && can(P.sales.concat(P.stock))) {
			extraHtml += '<div id="m-health"></div>';
		}
		if (extraHtml) {
			html += '<div class="m-home-extra-sections">' + extraHtml + '</div>';
		}

		$main.innerHTML = html;

		// Bind smart scanner buttons
		var scanQBtn = document.getElementById('m-qbtn-scan');
		if (scanQBtn) {
			scanQBtn.onclick = function(e) {
				e.preventDefault();
				openSmartScanner();
			};
		}

		var moreBtn = document.getElementById('m-qbtn-more');
		if (moreBtn) {
			moreBtn.onclick = function(e) {
				e.preventDefault();
				showMoreToolsSheet();
			};
		}

		$main.querySelectorAll('[data-clock]').forEach(function (b) { b.onclick = function () { clock(b.dataset.clock, b); }; });
		bindConfirm();
		bindRequests($main, t.top_approve);

		var hb = document.getElementById('m-health');
		if (hb) {
			get('/m/home', { period: 'today', branch_id: S.branch }, { signal: signal }).then(function (x) {
				var g = '';
				if (x.sales) { g += kpi({ label: isEn ? 'Sales Today' : 'Ventas hoy', icon: 'fa-chart-line', val: m(x.sales.totals.total), sub: delta(x.sales.totals.delta_pct) + ' · ' + qty(x.sales.totals.tickets) + ' tickets', href: '#/sales?period=today', tone: 'info' }); }
				if (x.waste) { g += kpi({ label: isEn ? 'Waste Today' : 'Mermas hoy', icon: 'fa-trash-can', val: m(x.waste.total), sub: x.waste.pct_sales != null ? pct(x.waste.pct_sales, 2) + (isEn ? ' of sales' : ' de la venta') : (isEn ? 'no sales' : 'sin ventas'), href: '#/waste?period=today', tone: 'danger' }); }
				if (x.inventory) {
					var crit = x.inventory.agotado + x.inventory.bajo + x.inventory.vencido + x.inventory.por_vencer;
					g += kpi({ label: isEn ? 'Critical SKUs' : 'Críticos', icon: 'fa-triangle-exclamation', val: qty(crit), sub: x.inventory.agotado + (isEn ? ' stockouts · ' : ' agotados · ') + x.inventory.vencido + (isEn ? ' expired' : ' vencidos'), href: '#/inv?filter=critico', tone: crit ? 'warn' : 'ok' });
					g += kpi({ label: isEn ? 'Discrepancies' : 'Diferencias', icon: 'fa-scale-unbalanced', val: qty(x.differences.count), sub: m(x.differences.cost) + (isEn ? ' to adjust' : ' por ajustar'), href: '#/control?type=count', tone: x.differences.count ? 'warn' : 'ok' });
				}
				hb.innerHTML = g ? section(isEn ? 'Operation Health' : 'Salud de la operación', '<a href="#/overview">' + (isEn ? 'Executive summary' : 'Resumen ejecutivo') + '</a>') + '<div class="m-grid">' + g + '</div>' : '';
			}).catch(function (e) { if (e.name !== 'AbortError') { hb.innerHTML = '<div class="m-note">' + (isEn ? 'Could not load indicators: ' : 'No se pudieron cargar los indicadores: ') + h(e.message) + '</div>'; } });
		}
	};

	/* ---------------- Tareas */
	function segQ(items, cur, k) {
		return '<div class="m-seg" role="tablist">' + items.map(function (i) { return '<button type="button" role="tab" aria-selected="' + (i[0] === cur) + '" class="' + (i[0] === cur ? 'on' : '') + '" data-tab-q="' + k + '" data-val="' + h(i[0]) + '">' + h(i[1]) + '</button>'; }).join('') + '</div>';
	}
	function chipsQ(items, cur, k) {
		return '<div class="m-chips">' + items.map(function (i) { return '<button type="button" class="m-chip' + (i[0] === cur ? ' on' : '') + '" data-tab-q="' + k + '" data-val="' + h(i[0]) + '">' + i[1] + '</button>'; }).join('') + '</div>';
	}
	VIEWS.tasks = async function (r, signal) {
		var scopes = [['mine', 'Mis tareas']];
		if (S.me.can_approve) { scopes.push(['approve', 'Por decidir']); }
		if (role() !== 'employee') { scopes.push(['team', 'Equipo']); }
		var scope = scopes.some(function (s) { return s[0] === r.q.scope; }) ? r.q.scope : 'mine', f = r.q.f || 'all';
		setTitle('Tareas'); crumbs(null); skel(5);
		var d = await get('/m/tasks', { scope: scope, branch_id: S.branch }, { signal: signal, fresh: true });
		var all = d.items, over = all.filter(function (i) { return i.overdue; }).length, high = all.filter(function (i) { return i.severity === 'high'; }).length;
		var items = all.filter(function (i) { return f === 'overdue' ? i.overdue : (f === 'high' ? i.severity === 'high' : true); });
		var html = scopes.length > 1 ? segQ(scopes, scope, 'scope') : '';
		html += chipsQ([['all', 'Todas <b>' + all.length + '</b>'], ['overdue', 'Vencidas <b>' + over + '</b>'], ['high', 'Urgentes <b>' + high + '</b>']], f, 'f');
		if (scope === 'approve' && all.some(function (i) { return ['waste', 'count', 'alert'].indexOf(i.type) >= 0; })) {
			html += '<a class="m-btn primary" href="#/control" style="margin-bottom:12px"><i class="fa-solid fa-layer-group"></i> Revisar uno por uno</a>';
		}
		html += items.length ? items.map(taskCard).join('') : '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>' + (scope === 'mine' ? 'No tiene tareas pendientes. ¡Buen trabajo!' : (scope === 'approve' ? 'Nada por decidir.' : 'El equipo no tiene pendientes.')) + '</div>';
		if (scope === 'mine') { html += '<p class="m-sub" style="margin-top:12px">Aquí se reúnen sus tareas de piso, conteos asignados, alertas a su nombre y traslados por recibir. Lo vencido aparece primero.</p>'; }
		$main.innerHTML = html;
		bindRequests($main, items);
	};

	VIEWS.task = async function (r, signal) {
		var id = n(r.path[1]);
		setTitle('TAR-' + id); crumbs([['Tareas', '#/tasks'], ['TAR-' + id, location.hash]]); skel(3);
		var t = await get('/m/tasks/' + id, {}, { signal: signal, fresh: true });
		var done = t.status !== 'pending', overdue = !done && t.due_time && new Date(String(t.due_time).replace(' ', 'T')) < new Date();
		var desc = String(t.description || '').split(' | Finalizado: ');
		var html = '<div class="m-card"><span class="m-tag ' + (done ? 'ok' : (overdue ? 'danger' : 'warn')) + '">' + (done ? 'Completada' : (overdue ? 'Vencida' : 'Pendiente')) + '</span><div class="m-title">' + h(t.title) + '</div>' + (desc[0] ? '<p class="m-sub" style="margin:6px 0 0">' + h(desc[0]) + '</p>' : '')
			+ '<dl class="m-kv" style="margin-top:12px"><dt>Para</dt><dd>' + h(dueLabel(t.due_time) || 'Sin plazo') + '</dd><dt>Responsable</dt><dd>' + h(t.assigned_user_name || t.assigned_employee_name || t.assigned_group) + '</dd><dt>Sucursal</dt><dd>' + h(t.branch_name || '') + '</dd>'
			+ (t.origin ? '<dt>Origen</dt><dd>' + (t.origin.href ? '<a href="' + h(t.origin.href) + '" style="color:var(--primary-2)">' + h(t.origin.label) + '</a>' : h(t.origin.label)) + '</dd>' : '')
			+ (done ? '<dt>Completada</dt><dd>' + dt(t.completed_at) + ' · ' + h(t.completed_by_name || '') + '</dd>' + (desc[1] ? '<dt>Nota</dt><dd>' + h(desc.slice(1).join(' | ')) + '</dd>' : '') : '') + '</dl>'
			+ (t.evidence_photo_path ? '<img class="m-photo" alt="Evidencia" src="' + h(photoUrl(t.evidence_photo_path)) + '">' : '') + '</div>';
		if (t.can_complete) {
			html += '<form id="m-tform" novalidate><div class="m-field"><label for="m-tnote">' + (t.note_required ? 'Qué hizo (obligatorio: con esta nota se cierra la alerta)' : 'Nota (opcional)') + '</label><textarea id="m-tnote" class="m-input" rows="3" placeholder="Ej.: temperatura 4 °C, góndola ordenada"></textarea><div class="m-err" id="e-tnote" hidden></div></div>'
				+ '<div class="m-field"><span class="m-label">Evidencia (opcional)</span><label class="m-photo-btn" for="m-tphoto"><i class="fa-solid fa-camera"></i> <span id="m-tphoto-l">Tomar o elegir foto</span></label><input type="file" id="m-tphoto" accept="image/*" capture="environment" hidden></div>'
				+ '<button type="submit" class="m-btn accent" id="m-tsubmit"><i class="fa-solid fa-check"></i> Marcar como hecha</button></form>';
		} else if (!done) {
			html += '<p class="m-sub" style="text-align:center">Esta tarea está asignada a otra persona.</p>';
		}
		$main.innerHTML = html;
		var form = document.getElementById('m-tform');
		if (!form) { return; }
		var photo = null, photoPath = null;
		form.querySelector('#m-tphoto').onchange = function (e) { photo = e.target.files[0] || null; photoPath = null; form.querySelector('#m-tphoto-l').textContent = photo ? photo.name : 'Tomar o elegir foto'; };
		form.onsubmit = async function (e) {
			e.preventDefault();
			var note = form.querySelector('#m-tnote').value.trim(), er = form.querySelector('#e-tnote'), btn = form.querySelector('#m-tsubmit');
			er.hidden = true;
			if (t.note_required && note.length < 5) { er.textContent = 'Escriba qué hizo (mínimo 5 caracteres).'; er.hidden = false; return; }
			try {
				if (photo && !photoPath) {
					if (navigator.onLine === false) { throw new Error('La foto necesita conexión: envíela al reconectar o quítela.'); }
					btn.classList.add('busy'); try { photoPath = await upload(photo); } finally { btn.classList.remove('busy'); }
				}
				var res = await post('/m/tasks/' + id + '/complete', { notes: note, evidence_photo_path: photoPath }, btn, photo ? null : 'Tarea TAR-' + id);
				if (!res) { return; }
				buzz(true); toast(res.message || 'Tarea completada', 'ok'); go('tasks');
			} catch (x) { toast(x.message, 'err'); }
		};
	};

	// Alerta → tarea para una persona (el servidor evita duplicados y al completarla resuelve la alerta)
	ACT.alert.task = {
		label: 'Crear tarea', icon: 'fa-list-check', run: async function (d, b) {
			await lookups();
			var u = await confirmSheet('Crear tarea', 'La alerta se asigna como tarea con plazo. Cuando la persona la marque como hecha, la alerta se resuelve con su nota.', 'Crear tarea', 'primary', { type: 'select', label: 'Responsable', options: userOptions(), required: 'Seleccione un responsable' });
			if (!u) { return null; }
			return post('/m/alerts/' + d.case.id + '/task', { user_id: n(u) }, b);
		}
	};

	// Ciclo de vida completo del Centro de Alertas (mismos endpoints que la web; el servidor decide qué acciones aplican)
	function alertNote(path, title, text, label, tone, fieldLabel, required) {
		return async function (d, b) {
			var t = await confirmSheet(title, text, label, tone || 'primary', { label: fieldLabel, required: required === false ? null : 'Complete este campo' });
			if (t === null || t === false || (required !== false && !t)) { return null; }
			return post('/alerts/' + d.case.id + '/' + path, { note: t === true ? '' : t }, b, title);
		};
	}
	ACT.alert.acknowledge = { label: 'Confirmar lectura', icon: 'fa-eye', tone: 'ghost', run: function (d, b) { return post('/alerts/' + d.case.id + '/acknowledge', {}, b, 'Confirmar lectura'); } };
	ACT.alert.investigate = { label: 'Investigar', icon: 'fa-magnifying-glass', run: alertNote('investigate', 'Iniciar investigación', 'La alerta pasa a «En investigación».', 'Iniciar', 'primary', 'Primer paso (opcional)', false) };
	ACT.alert.wait_info = { label: 'Esperar info', icon: 'fa-hourglass-half', tone: 'ghost', run: alertNote('wait_info', 'Esperar información', 'Indique qué falta y de quién.', 'Guardar', 'primary', 'Información pendiente') };
	ACT.alert.request_approval = { label: 'Pedir aprobación', icon: 'fa-stamp', tone: 'ghost', run: alertNote('request_approval', 'Solicitar aprobación', 'Un gestor debe aprobar antes de continuar.', 'Solicitar', 'primary', 'Qué debe aprobarse') };
	ACT.alert.help = { label: 'Pedir ayuda', icon: 'fa-life-ring', tone: 'ghost', run: alertNote('help', 'Pedir ayuda', 'Se avisa a los gestores de su sucursal.', 'Enviar', 'primary', 'Qué necesita') };
	ACT.alert.escalate = { label: 'Escalar', icon: 'fa-arrow-up-right-dots', tone: 'danger', run: alertNote('escalate', 'Escalar alerta', 'Se notifica al siguiente nivel.', 'Escalar', 'danger', 'Motivo') };
	ACT.alert.reopen = { label: 'Reabrir', icon: 'fa-rotate-left', run: alertNote('reopen', 'Reabrir alerta', 'Vuelve a quedar activa con nuevo plazo.', 'Reabrir', 'primary', 'Qué volvió a ocurrir') };
	ACT.alert.decide_request = { label: 'Decidir aprobación', icon: 'fa-scale-balanced', tone: 'primary', run: async function (d, b) { var ok = await choiceSheet('Solicitud de aprobación', '', [['1', 'Aprobar', 'primary'], ['0', 'Rechazar', 'danger']]); if (ok == null) { return null; } var t = ok === '0' ? await confirmSheet('Rechazar', 'Indique el motivo.', 'Rechazar', 'danger', { label: 'Motivo', required: 'Indique el motivo' }) : ''; if (t === null) { return null; } return post('/alerts/' + d.case.id + '/decide_request', { approve: ok === '1', note: t }, b); } };
	ACT.alert.plan = { label: 'Acción correctiva', icon: 'fa-route', tone: 'ghost', run: async function (d, b) { var t = await confirmSheet('Plan de acción', 'Agregue un paso con usted como responsable.', 'Agregar', 'primary', { label: 'Acción', required: 'Describa la acción' }); if (!t) { return null; } return post('/alerts/' + d.case.id + '/plan', { description: t, owner_user_id: S.me && S.me.id }, b, 'Plan de acción'); } };
	ACT.alert.evidence = {
		label: 'Foto / evidencia', icon: 'fa-camera', run: function (d, b) {
			return new Promise(function (resolve, reject) {
				var inp = document.createElement('input');
				inp.type = 'file'; inp.accept = 'image/*,application/pdf'; inp.setAttribute('capture', 'environment');
				inp.onchange = async function () {
					var f = inp.files[0];
					if (!f) { resolve(null); return; }
					try {
						var fd = new FormData(); fd.append('file', f);
						var res = await fetch(API + '/alerts/' + d.case.id + '/evidence', { method: 'POST', credentials: 'same-origin', body: fd });
						if (!res.ok) { throw await errorOf(res); }
						resolve(await res.json());
					} catch (e) { reject(e); }
				};
				inp.click();
			});
		}
	};

	/* ---------------- Control de pérdidas (Etapa 36, metodología del Censo): misma API y reglas que /waste en la web */
	var PL = { theft: ['LOSS_PREVENTION', 'WASTE_APPROVE'], approve: ['WASTE_APPROVE', 'LOSS_PREVENTION'], report: ['WASTE_REPORT'] };
	var AE_STATUS = { detected: 'Detectado', evidence: 'Con evidencia', reconciled: 'Conciliado', approved: 'Aprobado', corrected: 'Corregido', verified: 'Verificado', closed: 'Cerrado', dismissed: 'Descartado' };
	var AE_FLOW = ['detected', 'evidence', 'reconciled', 'approved', 'corrected', 'verified', 'closed'];
	var AE_HELP = { evidence: 'Adjunte foto o describa la evidencia (mínimo 10 caracteres sin foto).', reconciled: 'Qué se comparó (documento, sistema, físico) y qué resultó.', approved: 'Quien concilió no puede aprobar.',
		corrected: 'Corrección aplicada: documento, ajuste o cambio realizado.', verified: 'Verificación independiente: quien corrigió no puede verificar.', closed: 'Resolución final del caso.', dismissed: 'Por qué no es un error (mínimo 10 caracteres).' };
	function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
	function periodRange() {
		var t = new Date(), f = new Date(t);
		if (S.period === '7d') { f.setDate(t.getDate() - 6); } else if (S.period === '30d') { f.setDate(t.getDate() - 29); }
		else if (S.period === 'month') { f.setDate(1); } else if (S.period === 'year') { f = new Date(t.getFullYear(), 0, 1); }
		return { from: ymd(f), to: ymd(t) };
	}
	function lossQ(extra) { var r = periodRange(); return Object.assign({ branch_id: S.branch, from: r.from, to: r.to }, extra || {}); }
	function lossWeb(hash, extra) { var r = periodRange(); return C.base + '/waste' + qs(Object.assign({ branch_id: S.branch, start_date: r.from, end_date: r.to }, extra || {})) + hash; }
	function opts(obj, sel, empty) { return (empty ? '<option value="">' + h(empty) + '</option>' : '') + Object.keys(obj).map(function (k) { return '<option value="' + h(k) + '"' + (k === sel ? ' selected' : '') + '>' + h(obj[k]) + '</option>'; }).join(''); }
	function field(id, label, input) { return '<div class="m-field"><label for="' + id + '">' + h(label) + '</label>' + input + '</div>'; }
	function photoField(id) { return '<div class="m-field"><span class="m-label">Foto o documento</span><label class="m-photo-btn" for="' + id + '"><i class="fa-solid fa-camera"></i> <span id="' + id + '-l">Tomar o elegir foto</span></label><input type="file" id="' + id + '" accept="image/*" capture="environment" hidden><img id="' + id + '-p" class="m-photo" hidden alt="Vista previa" style="max-height:160px;object-fit:cover;width:100%"></div>'; }
	function bindPhoto(body, id) {
		var inp = body.querySelector('#' + id), st = { file: null };
		inp.onchange = function (e) {
			st.file = e.target.files[0] || null;
			body.querySelector('#' + id + '-l').textContent = st.file ? st.file.name : 'Tomar o elegir foto';
			var p = body.querySelector('#' + id + '-p'); if (st.file) { p.src = URL.createObjectURL(st.file); p.hidden = false; } else { p.hidden = true; }
		};
		return st;
	}
	// Formulario en hoja inferior: devuelve los valores (o null si se cancela) tras validar con fn
	function formSheet(title, html, label, onSubmit) {
		sheet(title, html + '<div class="m-err" id="m-fs-err" hidden></div><div class="m-grid" style="grid-template-columns:1fr 1fr"><button type="button" class="m-btn" data-close>Cancelar</button><button type="button" class="m-btn primary" id="m-fs-ok">' + h(label) + '</button></div>', function (body, close) {
			var ctx = {};
			if (body.querySelector('#m-fs-photo')) { ctx.photo = bindPhoto(body, 'm-fs-photo'); }
			body.querySelector('#m-fs-ok').onclick = async function () {
				var btn = this, er = body.querySelector('#m-fs-err'); er.hidden = true;
				var vals = {}; body.querySelectorAll('[name]').forEach(function (x) { vals[x.name] = x.value.trim(); });
				try {
					if (ctx.photo && ctx.photo.file) {
						if (navigator.onLine === false) { throw new Error('La foto necesita conexión'); }
						btn.classList.add('busy'); try { vals.evidence_path = await upload(ctx.photo.file); } finally { btn.classList.remove('busy'); }
					}
					var res = await onSubmit(vals, btn);
					if (res === null) { return; }
					close(); buzz(true); render(true);
				} catch (x) { er.textContent = x.message; er.hidden = false; buzz(false); }
			};
		});
	}

	VIEWS.shrink = async function (r, signal) {
		var tab = r.path[1] === 'ae' && r.path[2] ? 'case' : (r.q.tab || 'ind');
		if (tab === 'case') { return shrinkCase(n(r.path[2]), signal); }
		setTitle('Control de pérdidas'); skel(5);
		crumbs([['Mermas', '#/waste'], ['Control de pérdidas', '#/shrink']]);
		var tabs = [['ind', 'Indicadores'], ['err', 'Errores'], ['per', 'Perecederos']];
		if (can(PL.theft)) { tabs.splice(2, 0, ['rob', 'Hurto']); }
		var head = periodChips() + seg(tabs, tab, 'tab-q="tab" data-val');
		var html = '';
		if (tab === 'err') { html = await shrinkErrors(r, signal); }
		else if (tab === 'rob' && can(PL.theft)) { html = await shrinkTheft(signal); }
		else if (tab === 'per') { html = await shrinkPerish(signal); }
		else { html = await shrinkIndicators(signal); }
		$main.innerHTML = head + html;
		shrinkBind();
	};

	async function shrinkIndicators(signal) {
		var hd = await get('/loss/census/hierarchy', lossQ(), { signal: signal });
		var dep = await get('/loss/census/departments', lossQ(), { signal: signal });
		var t = hd.tolerance, tg = t.target, cs = hd.census_split, tot = hd.total;
		var html = '<div class="m-hero danger"><div class="lbl">Merma total a costo · ' + day(hd.from) + ' – ' + day(hd.to) + '</div><div class="big m-num">' + money(tot.cost) + '</div><div class="row"><span>' + (tot.pct_sales_cost != null ? pct(tot.pct_sales_cost, 2) + ' de ventas netas' : 'sin ventas en el período') + '</span><span>' + tot.events + ' eventos</span></div></div>';
		html += '<div class="m-grid">' + [
			kpi({ label: 'A precio de venta', val: money(tot.sale_value), sub: tot.pct_sales_sale != null ? pct(tot.pct_sales_sale, 2) + ' de ventas' : '—', icon: 'fa-tag' }),
			kpi({ label: 'Tolerada', val: tg && tg.tolerated_pct != null ? pct(tg.tolerated_pct, 2) : 'Sin definir', sub: t.tolerated_value != null ? money(t.tolerated_value) : 'meta ' + t.year + ' en la web', icon: 'fa-bullseye', href: lossWeb('#tab-censo') }),
			kpi({ label: 'Exceso', val: t.excess_value != null ? money(t.excess_value) : '—', sub: t.excess_pp != null ? (t.excess_pp > 0 ? '+' : '') + pct(t.excess_pp, 2).replace(' %', ' pp') : 'sin meta', icon: 'fa-triangle-exclamation', tone: n(t.excess_value) > 0 ? 'danger' : 'ok' }),
			kpi({ label: 'Vs. presupuesto', val: t.vs_budget_value != null ? money(t.vs_budget_value) : '—', sub: t.budget_value != null ? 'presup. ' + money(t.budget_value) : 'sin presupuesto', icon: 'fa-scale-balanced', tone: n(t.vs_budget_value) > 0 ? 'warn' : '' }),
			kpi({ label: 'Conocida', val: cs.known_pct != null ? pct(cs.known_pct, 0) : '—', sub: 'ref. Censo ' + pct(cs.bench_known, 0), icon: 'fa-circle-check', tone: 'ok' }),
			kpi({ label: 'Desconocida', val: cs.unknown_pct != null ? pct(cs.unknown_pct, 0) : '—', sub: 'ref. Censo ' + pct(cs.bench_unknown, 0), icon: 'fa-magnifying-glass', tone: 'warn' })
		].join('') + '</div>';
		if (!hd.consistency.sum_ok || hd.consistency.assigned !== hd.consistency.events) { html += '<div class="m-error">Inconsistencia de clasificación: revise las causales en la web.</div>'; }
		var base = Math.max(1, Math.abs(n(tot.cost)));
		html += section('Estructura', '<a href="' + h(lossWeb('#tab-censo')) + '">Gráficos en la web <i class="fa-solid fa-arrow-up-right-from-square m-xs"></i></a>');
		html += list(hd.nodes.map(function (nd) {
			var det = nd.children.slice(0, 3).map(function (c) { return h(c.label) + ' ' + money(c.cost); }).join(' · ');
			return row({ t1: h(nd.label), t2: nd.events + ' eventos' + (det ? ' · ' + det : ''), v: money(nd.cost), s: pct(nd.share, 1), bar: Math.abs(n(nd.cost)) / base * 100, href: C.base + '/waste' + nd.drill });
		}));
		var months = (hd.monthly.months || []).filter(function (m) { return !m.future && (n(m.real) || n(m.net_sales)); });
		if (months.length) {
			var mx = Math.max.apply(null, months.map(function (m) { return Math.max(n(m.real), n(m.tolerated)); })) || 1;
			html += section('Real vs. tolerada ' + hd.monthly.year) + list(months.map(function (m) {
				return row({ t1: h(m.label), t2: (m.tolerated != null ? 'tolerada ' + money(m.tolerated) + ' · ' : '') + 'acum. ' + pct(m.ytd_pct, 2), v: money(m.real), s: m.excess > 0 ? '<span class="t-op">+' + money(m.excess) + '</span>' : pct(m.real_pct, 2), bar: n(m.real) / mx * 100, barColor: m.excess > 0 ? 'var(--danger)' : '' });
			}));
		}
		if (dep.departments.length) {
			html += section('Por departamento') + list(dep.departments.slice(0, 8).map(function (d) {
				return row({ icon: 'fa-layer-group', t1: h(d.department), t2: (d.pct_sales_cost != null ? pct(d.pct_sales_cost, 2) + ' de sus ventas' : 'sin ventas') + (d.tolerated_pct != null ? ' · tolerada ' + pct(d.tolerated_pct, 2) : ''), v: money(d.cost), s: pct(d.share, 1), bar: n(d.share) });
			}));
		}
		return html + '<p class="m-sub" style="margin-top:8px">Mismas fórmulas que la web. Las cifras del Censo (datos 2022) son solo referencia externa.</p>';
	}

	async function shrinkErrors(r, signal) {
		var st = r.q.st || 'open';
		var d = await get('/loss/census/admin-errors', lossQ({ status: st }), { signal: signal, fresh: true });
		var html = '<div class="m-grid">' + [
			kpi({ label: 'Abiertos', val: qty(d.open), sub: d.total + ' registrados', icon: 'fa-folder-open', tone: d.open ? 'warn' : 'ok' }),
			kpi({ label: 'Vencidos', val: qty(d.overdue), sub: 'fuera de plazo', icon: 'fa-clock', tone: d.overdue ? 'danger' : 'ok' }),
			kpi({ label: 'Impacto', val: money(d.impact), sub: 'no suma a la merma', icon: 'fa-coins' }),
			kpi({ label: 'EPC / RFID', val: d.rfid_ready ? 'Con datos' : 'Sin lector', sub: 'registro manual', icon: 'fa-wave-square' })
		].join('') + '</div>';
		if (can(PL.report)) {
			html += '<div class="m-grid" style="grid-template-columns:1fr 1fr;margin-top:10px"><button type="button" class="m-btn primary" data-sk="detect"><i class="fa-solid fa-magnifying-glass-chart"></i> Auditar</button><button type="button" class="m-btn" data-sk="newae"><i class="fa-solid fa-plus"></i> Registrar</button></div>';
		}
		var freq = d.stats.filter(function (s) { return s.total > 0; }), mx = Math.max.apply(null, freq.map(function (s) { return s.total; }).concat([1]));
		if (freq.length) {
			html += section('Por frecuencia') + list(freq.map(function (s) { return row({ t1: h(s.code + ' · ' + s.name), t2: s.open + ' abiertos' + (s.overdue ? ' · <span class="t-op">' + s.overdue + ' vencidos</span>' : '') + (s.bench_pct != null ? ' · Censo ' + pct(s.bench_pct, 1) : ''), v: qty(s.total), s: pct(s.freq_pct, 1), bar: s.total / mx * 100, href: '#/shrink?tab=err&st=all&cat=' + s.code }); }));
		}
		var cases = r.q.cat ? d.cases.filter(function (c) { return c.category === r.q.cat; }) : d.cases;
		html += section('Casos' + (r.q.cat ? ' · ' + r.q.cat : '')) + seg([['open', 'Abiertos'], ['all', 'Todos']], st, 'tab-q="st" data-val');
		html += list(cases.map(function (c) {
			var closed = c.status === 'closed' || c.status === 'dismissed', late = !closed && c.due_date && c.due_date < ymd(new Date());
			return row({ icon: c.source === 'auto' ? 'fa-robot' : 'fa-pen', tone: late ? 'danger' : (closed ? '' : 'warn'), t1: h(c.title), t2: h(c.case_number) + ' · ' + h((d.categories[c.category] || [c.category])[0]) + (c.branch_name ? ' · ' + h(c.branch_name) : ''),
				v: money(c.impact_cost), s: '<span class="m-tag ' + (closed ? 'ok' : (late ? 'danger' : 'warn')) + '">' + h(AE_STATUS[c.status] || c.status) + '</span>', href: '#/shrink/ae/' + c.id });
		}), st === 'open' ? 'Sin casos abiertos' : 'Sin casos');
		S.aeCats = d.categories; S.aeCanApprove = d.can_approve;
		return html;
	}

	async function shrinkCase(id, signal) {
		setTitle('Error administrativo'); skel(4);
		var d = await get('/loss/census/admin-errors', lossQ({ status: 'all' }), { signal: signal, fresh: true });
		var c = d.cases.find(function (x) { return n(x.id) === id; });
		if (!c) { throw new Error('Caso no encontrado o fuera de su sucursal'); }
		var hist = (await get('/loss/census/admin-errors/' + id + '/history', lossQ(), { signal: signal, fresh: true })).history;
		crumbs([['Control de pérdidas', '#/shrink'], ['Errores', '#/shrink?tab=err'], [c.case_number, location.hash]]);
		setTitle(c.case_number);
		var closed = c.status === 'closed' || c.status === 'dismissed', i = AE_FLOW.indexOf(c.status), next = !closed && i >= 0 ? AE_FLOW[i + 1] : null;
		var html = '<div class="m-card"><div class="m-sub">' + h((d.categories[c.category] || [c.category])[0]) + ' · ' + (c.source === 'auto' ? 'detección automática' : 'registro manual') + '</div><h2 style="margin:4px 0 8px;font-size:18px">' + h(c.title) + '</h2>'
			+ (c.detail ? '<p class="m-sub">' + h(c.detail) + '</p>' : '')
			+ '<dl class="m-kv"><dt>Estado</dt><dd><span class="m-tag ' + (closed ? 'ok' : 'warn') + '">' + h(AE_STATUS[c.status]) + '</span></dd>'
			+ (c.expected_value ? '<dt>Esperado</dt><dd>' + h(c.expected_value) + '</dd>' : '') + (c.actual_value ? '<dt>Real</dt><dd>' + h(c.actual_value) + '</dd>' : '')
			+ '<dt>Impacto</dt><dd>' + money(c.impact_cost) + '</dd>' + (c.ref_doc ? '<dt>Documento</dt><dd>' + h(c.ref_doc) + '</dd>' : '') + (c.product_name ? '<dt>Producto</dt><dd>' + h(c.product_name) + '</dd>' : '')
			+ (c.branch_name ? '<dt>Sucursal</dt><dd>' + h(c.branch_name) + '</dd>' : '') + (c.due_date ? '<dt>Plazo</dt><dd>' + day(c.due_date) + '</dd>' : '') + '</dl>'
			+ (c.evidence_path ? '<a href="' + h(photoUrl(c.evidence_path)) + '" target="_blank" rel="noopener"><img class="m-photo" src="' + h(photoUrl(c.evidence_path)) + '" alt="Evidencia" style="margin-top:10px;max-height:220px;object-fit:cover;width:100%"></a>' : '') + '</div>';
		// Flujo con el paso actual marcado
		html += section('Flujo') + '<div class="m-card"><div class="m-checks" style="display:flex;flex-wrap:wrap;gap:6px">' + AE_FLOW.map(function (s, k) { return '<span class="m-tag ' + (k <= i ? 'ok' : 'muted') + '">' + (k + 1) + '. ' + h(AE_STATUS[s]) + '</span>'; }).join('') + '</div></div>';
		html += section('Historial') + list(hist.map(function (x) { return row({ icon: 'fa-clock-rotate-left', t1: h(AE_STATUS[x.to_status] || x.to_status), t2: h((x.user_name || x.user || '') + ' · ' + String(x.created_timestamp).slice(0, 16)) + (x.notes ? '<br>' + h(x.notes) : '') }); }));
		var acts = [];
		var needsApprover = next === 'approved' || next === 'verified';
		if (next && can(PL.report) && (!needsApprover || d.can_approve)) { acts.push('<button type="button" class="m-btn primary" data-sk="adv" data-to="' + next + '"><i class="fa-solid fa-forward-step"></i> ' + h(AE_STATUS[next]) + '</button>'); }
		if (!closed && d.can_approve && i <= 2) { acts.push('<button type="button" class="m-btn" data-sk="adv" data-to="dismissed"><i class="fa-solid fa-ban"></i> Descartar</button>'); }
		if (acts.length) { html += '<div class="m-actions">' + acts.join('') + '</div>'; }
		else if (next && needsApprover) { html += '<p class="m-sub" style="text-align:center;margin:16px 0">Este paso requiere un aprobador.</p>'; }
		S.aeCase = c;
		$main.innerHTML = html;
		shrinkBind();
	}

	async function shrinkTheft(signal) {
		var d = await get('/loss/census/theft', lossQ(), { signal: signal });
		var bt = d.by_type, rc = d.recovery;
		var html = '<div class="m-note"><i class="fa-solid fa-user-shield"></i> Solo casos confirmados. Los sospechosos quedan en investigación con revisión humana; no registre datos de personas.</div>';
		html += '<div class="m-grid">' + [
			kpi({ label: 'Robo interno', val: money(bt.interno.cost), sub: bt.interno.incidents + ' caso(s)', icon: 'fa-id-badge' }),
			kpi({ label: 'Robo externo', val: money(bt.externo.cost), sub: bt.externo.incidents + ' caso(s)', icon: 'fa-person-running', tone: 'danger' }),
			kpi({ label: 'Fraude POS', val: money(bt.fraude_pos.cost), sub: bt.fraude_pos.incidents + ' caso(s)', icon: 'fa-cash-register' }),
			kpi({ label: 'Fraude digital', val: money(bt.fraude_electronico.cost), sub: bt.fraude_electronico.incidents + ' caso(s)', icon: 'fa-credit-card' }),
			kpi({ label: 'Sospechosos', val: qty(d.suspected.count), sub: money(d.suspected.cost) + ' sin confirmar', icon: 'fa-magnifying-glass', tone: 'warn' }),
			kpi({ label: 'Recuperación', val: rc.pct != null ? pct(rc.pct, 1) : '—', sub: money(rc.total) + ' recuperado', icon: 'fa-rotate-left', tone: 'ok' }),
			kpi({ label: 'Merc. recuperada', val: money(rc.goods), sub: 'financiera ' + money(rc.financial), icon: 'fa-box' })
		].join('') + '</div>';
		html += '<p class="m-sub" style="margin:6px 0 0">' + (d.rate.per_1000_tickets != null ? qty(d.rate.per_1000_tickets) + ' incidentes por 1.000 tickets · ' : '') + qty(d.rate.per_month) + ' por mes</p>';
		if (!d.incidents) { return html + list([], 'Sin casos confirmados en el período'); }
		var mt = Object.keys(d.monthly), mx = Math.max.apply(null, mt.map(function (k) { return d.monthly[k].cost; }).concat([1]));
		html += section('Tendencia') + list(mt.map(function (k) { return row({ t1: h(k), t2: d.monthly[k].incidents + ' incidente(s)', v: money(d.monthly[k].cost), bar: d.monthly[k].cost / mx * 100, barColor: 'var(--danger)' }); }));
		html += section('Modalidades') + list(d.modalities.slice(0, 8).map(function (m) { return row({ t1: h(m.label), t2: h(bt[m.type].label) + ' · ' + m.incidents + ' caso(s)', v: money(m.cost) }); }));
		html += section('Canal de detección') + list(d.channels.map(function (c) { return row({ t1: h(c.label), v: qty(c.incidents), s: money(c.cost) }); }));
		html += section('Casos', d.pending_details ? '<span class="m-tag warn">' + d.pending_details + ' sin modalidad</span>' : '') + list(d.events.slice(0, 30).map(function (e, k) {
			return '<button type="button" class="m-row" data-sk="theft" data-k="' + k + '" style="width:100%;text-align:left;background:none;border:0;font:inherit;color:inherit">' + '<span class="ic ' + (e.modality ? '' : 'warn') + '"><i class="fa-solid ' + (e.modality ? 'fa-shield-halved' : 'fa-pen') + '"></i></span><span class="tx"><div class="t1">' + h(e.doc) + ' · ' + h(e.type) + '</div><div class="t2">' + day(e.d) + ' · ' + h(e.modality || 'Sin modalidad') + (e.channel ? ' · ' + h(e.channel) : '') + '</div></span><span class="rt"><div class="v m-num">' + money(e.cost) + '</div><div class="s">rec. ' + money(n(e.goods) + n(e.recovered)) + '</div></span><i class="fa-solid fa-chevron-right chev"></i></button>';
		}));
		S.theft = d;
		return html;
	}

	async function shrinkPerish(signal) {
		var d = await get('/loss/census/perishables', lossQ(), { signal: signal });
		var y = d.yield;
		var html = '<div class="m-grid">' + [
			kpi({ label: 'Donado', val: money(d.donated.cost), sub: d.donated.events + ' · ' + qty(d.donated.weight_kg) + ' kg', icon: 'fa-hand-holding-heart', tone: 'ok' }),
			kpi({ label: 'Compostaje', val: money(d.composted.cost), sub: d.composted.events + ' · ' + qty(d.composted.weight_kg) + ' kg', icon: 'fa-seedling', tone: 'ok' }),
			kpi({ label: 'Devol. proveedor', val: money(d.supplier_returns.cost), sub: 'acreditado ' + money(d.supplier_returns.recovered), icon: 'fa-truck-ramp-box' }),
			kpi({ label: 'Transporte', val: money(d.transport), sub: 'causal TRA', icon: 'fa-truck' }),
			kpi({ label: 'Vencimiento', val: money(d.expiry.cost), sub: d.expiry.events + ' evento(s)', icon: 'fa-calendar-xmark', tone: 'danger' }),
			kpi({ label: 'Drenaje cárnicos', val: y ? qty(n(y.water) + n(y.blood)) + ' kg' : '—', sub: y ? 'agua + sangre' : 'sin lotes', icon: 'fa-droplet' })
		].join('') + '</div>';
		var fam = d.families.filter(function (f) { return f.events; }), dest = d.destinations.filter(function (x) { return x.events; });
		if (fam.length) { html += section('Por familia') + list(fam.map(function (f) { return row({ icon: 'fa-basket-shopping', t1: h(f.label), t2: f.events + ' evento(s)' + (f.weight_kg ? ' · ' + qty(f.weight_kg) + ' kg' : '') + (f.cause_names[0] ? ' · ' + h(f.cause_names[0]) : ''), v: money(f.cost), s: f.pct_sales != null ? pct(f.pct_sales, 2) + ' ventas' : '' }); })); }
		if (dest.length) { html += section('Desperdicios y donaciones') + list(dest.map(function (x) { return row({ t1: h(x.label), t2: x.events + ' evento(s)' + (x.weight_kg ? ' · ' + qty(x.weight_kg) + ' kg' : ''), v: money(x.cost), s: pct(x.share, 1), bar: n(x.share), barColor: x.key === 'donacion' || x.key === 'compostaje' ? 'var(--ok)' : '' }); })); }
		var ev = d.events.filter(function (e) { return e.src === 'waste'; }).slice(0, 30);
		html += section('Mermas de perecederos') + list(ev.map(function (e) {
			var k = d.events.indexOf(e);
			return '<button type="button" class="m-row" data-sk="dest" data-k="' + k + '" style="width:100%;text-align:left;background:none;border:0;font:inherit;color:inherit"><span class="ic"><i class="fa-solid ' + (e.evidence ? 'fa-camera' : 'fa-file-lines') + '"></i></span><span class="tx"><div class="t1">' + h(e.product) + '</div><div class="t2">' + h(e.doc || '') + ' · ' + day(e.d) + ' · ' + h(e.cause) + ' · ' + h(e.destination || 'sin destino') + '</div></span><span class="rt"><div class="v m-num">' + money(e.cost) + '</div><div class="s">' + qty(e.units) + ' u.' + (e.weight_kg != null ? ' · ' + qty(e.weight_kg) + ' kg' : '') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></button>';
		}), 'Sin mermas de perecederos en el período');
		S.perish = d;
		return html;
	}

	function shrinkBind() {
		$main.querySelectorAll('[data-sk]').forEach(function (b) {
			b.onclick = function () {
				var a = b.dataset.sk;
				if (a === 'detect') {
					var r = periodRange();
					post('/loss/census/admin-errors/detect', { branch_id: S.branch, from: r.from, to: r.to }, b)
						.then(function (x) { if (x) { toast(x.created + ' caso(s) nuevo(s) de ' + x.found + ' hallazgo(s)', 'ok'); render(true); } })
						.catch(function (e) { toast(e.message, 'err'); });
				} else if (a === 'newae') {
					var cats = {}; Object.keys(S.aeCats || {}).forEach(function (k) { cats[k] = k + ' · ' + S.aeCats[k][0]; });
					formSheet('Registrar error administrativo', field('m-ae-c', 'Categoría', '<select id="m-ae-c" name="category" class="m-input">' + opts(cats) + '</select>')
						+ field('m-ae-t', 'Error encontrado', '<input id="m-ae-t" name="title" class="m-input" placeholder="Ej.: factura sin número">')
						+ field('m-ae-d', 'Detalle', '<textarea id="m-ae-d" name="detail" class="m-input" rows="2"></textarea>')
						+ field('m-ae-r', 'Documento', '<input id="m-ae-r" name="ref_doc" class="m-input">')
						+ field('m-ae-i', 'Impacto ($)', '<input id="m-ae-i" name="impact_cost" class="m-input" type="number" inputmode="decimal" min="0" step="any">'), 'Registrar',
						function (v, btn) { if (v.title.length < 5) { throw new Error('Describa el error (mínimo 5 caracteres)'); } if (S.branch) { v.branch_id = S.branch; } return post('/loss/census/admin-errors', v, btn).then(function (x) { if (x) { toast('Caso registrado', 'ok'); } return x; }); });
				} else if (a === 'adv') {
					var to = b.dataset.to, c = S.aeCase;
					formSheet(AE_STATUS[to], '<p class="m-sub">' + h(AE_HELP[to] || '') + '</p>' + (to === 'evidence' ? photoField('m-fs-photo') : '')
						+ field('m-ae-n', 'Notas', '<textarea id="m-ae-n" name="notes" class="m-input" rows="3"></textarea>')
						+ (to === 'reconciled' ? field('m-ae-ic', 'Impacto conciliado ($, opcional)', '<input id="m-ae-ic" name="impact_cost" class="m-input" type="number" inputmode="decimal" min="0" step="any">') : ''), 'Registrar',
						function (v, btn) { v.to = to; return post('/loss/census/admin-errors/' + c.id + '/advance', v, btn).then(function (x) { if (x) { toast('Paso registrado: ' + AE_STATUS[to], 'ok'); } return x; }); });
				} else if (a === 'theft') {
					var e = S.theft.events[n(b.dataset.k)], cat = S.theft.catalog;
					formSheet('Caso ' + e.doc, '<p class="m-sub">' + h(e.type) + ' · ' + money(e.cost) + '. No registre nombres ni datos de personas.</p>'
						+ field('m-th-m', 'Modalidad', '<select id="m-th-m" name="theft_modality" class="m-input">' + opts(cat.modalities[e.type_key] || {}, e.theft_modality, 'Sin registrar') + '</select>')
						+ field('m-th-c', 'Canal de detección', '<select id="m-th-c" name="detection_channel" class="m-input">' + opts(cat.channels, e.detection_channel, 'Sin registrar') + '</select>')
						+ field('m-th-g', 'Mercancía recuperada ($)', '<input id="m-th-g" name="recovered_goods_value" class="m-input" type="number" inputmode="decimal" min="0" step="any" value="' + h(e.goods || '') + '">')
						+ field('m-th-f', 'Recuperación financiera ($)', '<input id="m-th-f" name="financial_recovery_amount" class="m-input" type="number" inputmode="decimal" min="0" step="any" value="' + h(e.recovered || '') + '">'), 'Guardar',
						function (v, btn) { return post('/loss/census/waste/' + e.ref + '/details', v, btn).then(function (x) { if (x) { toast('Detalle guardado', 'ok'); } return x; }); });
				} else if (a === 'dest') {
					var p = S.perish.events[n(b.dataset.k)];
					formSheet(p.product, '<p class="m-sub">' + h(p.doc || '') + ' · ' + money(p.cost) + '. El destino no cambia el costo de la merma.</p>'
						+ field('m-de-d', 'Destino final', '<select id="m-de-d" name="final_destination" class="m-input">' + opts(S.perish.destinations_catalog, Object.keys(S.perish.destinations_catalog).find(function (k) { return S.perish.destinations_catalog[k] === p.destination; }), 'Sin registrar') + '</select>')
						+ field('m-de-w', 'Peso (kg)', '<input id="m-de-w" name="weight_kg" class="m-input" type="number" inputmode="decimal" min="0" step="any" value="' + h(p.weight_kg == null ? '' : p.weight_kg) + '">'), 'Guardar',
						function (v, btn) { return post('/loss/census/waste/' + p.ref + '/details', v, btn).then(function (x) { if (x) { toast('Destino guardado', 'ok'); } return x; }); });
				}
			};
		});
	}

	/* ---------------- Escanear */
	var lastScan = { code: '', t: 0 }, wedge = { buf: '', t: 0 };
	function recentScans() { return store('recent:' + C.userId) || []; }
	VIEWS.scan = async function (r) {
		setTitle('Escanear'); crumbs(null);
		var cam = navigator.mediaDevices && window.isSecureContext;
		var html = '<div class="m-scanhero"><button type="button" class="m-scanbtn" id="m-scam"' + (cam ? '' : ' disabled') + '><i class="fa-solid fa-camera"></i><b>Escanear con la cámara</b><span>' + (cam ? 'Producto, lote GS1 o etiqueta de ubicación' : 'La cámara requiere HTTPS: use el código manual') + '</span></button>'
			+ '<form id="m-sform" class="m-scanman" autocomplete="off"><input class="m-input" id="m-scode" placeholder="Código, lote o LOC-…" aria-label="Código"><button type="submit" class="m-btn primary sm" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button></form>'
			+ '<p class="m-sub" style="margin:8px 0 0"><i class="fa-solid fa-gun"></i> Lectores Zebra/Honeywell en modo teclado: escanee directamente en esta pantalla.</p></div>'
			+ '<div id="m-scan-res"></div>';
		var cap = captureMenu();
		if (cap) { html += section('Registrar sin escanear') + cap; }
		var rec = recentScans();
		if (rec.length) { html += section('Escaneos recientes') + '<div class="m-list">' + rec.map(function (x) { return '<button type="button" class="m-row" data-code="' + h(x.code) + '" style="width:100%;border:0;background:none;text-align:left"><span class="ic"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="tx"><div class="t1">' + h(x.name || x.code) + '</div><div class="t2">' + h(x.code) + ' · ' + dt(x.at) + '</div></span><i class="fa-solid fa-chevron-right chev"></i></button>'; }).join('') + '</div>'; }
		if (!can(['STOCK', 'SCAN_OPERATE', 'WASTE_REPORT', 'STOCK_INVENTORY', 'STOCK_TRANSFER'])) { html = '<div class="m-card m-empty"><i class="fa-solid fa-lock"></i>Su perfil no tiene operaciones de inventario habilitadas.</div>'; }
		$main.innerHTML = html;
		var f = document.getElementById('m-sform');
		if (!f) { return; }
		f.onsubmit = function (e) { e.preventDefault(); var v = f.querySelector('#m-scode').value; f.querySelector('#m-scode').blur(); resolveCode(v, true); };
		document.getElementById('m-scam').onclick = function () { scanCamera(); };
		$main.querySelectorAll('[data-code]').forEach(function (b) { b.onclick = function () { resolveCode(b.dataset.code, true); }; });
		if (r.q.code) { resolveCode(r.q.code, true); }
	};
	// Lector en modo teclado: ráfaga de caracteres (< 50 ms entre teclas) terminada en Enter
	document.addEventListener('keydown', function (e) {
		if (parse().path[0] !== 'scan' || (document.activeElement && document.activeElement.id === 'm-scode') || sheetClose) { return; }
		var now = Date.now();
		if (now - wedge.t > 80) { wedge.buf = ''; }
		wedge.t = now;
		if (e.key === 'Enter') { if (wedge.buf.length >= 4) { e.preventDefault(); resolveCode(wedge.buf); } wedge.buf = ''; return; }
		if (e.key.length === 1) { wedge.buf += e.key; }
	});
	function scanCamera() {
		sheet('Escanear', '<video id="m-video" class="m-video" playsinline muted></video><p class="m-sub" id="m-scan-st">Iniciando cámara…</p>', function (body, close) {
			var st = body.querySelector('#m-scan-st'), done = false;
			function stop() { try { if (reader) { reader.reset(); } } catch (e) { } }
			var oc = sheetClose; sheetClose = function () { done = true; stop(); oc(); };
			loadZxing().then(function () {
				reader = new ZXing.BrowserMultiFormatReader();
				reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, body.querySelector('#m-video'), function (res) { if (res && !done) { done = true; stop(); close(); resolveCode(res.getText()); } });
				st.textContent = 'Apunte al código de barras';
			}).catch(function () { st.textContent = 'No se pudo iniciar el lector. Use el código manual.'; });
		});
	}
	async function resolveCode(code, manual) {
		code = String(code || '').trim();
		if (!code) { return; }
		// Prevención de lecturas repetidas (mismo código en 2,5 s)
		if (!manual && code === lastScan.code && Date.now() - lastScan.t < 2500) { return; }
		lastScan = { code: code, t: Date.now() };
		if (parse().path[0] !== 'scan') { go('scan', { code: code }); return; }
		var box = document.getElementById('m-scan-res');
		box.innerHTML = '<div class="m-skel"></div>';
		var d;
		try { d = await get('/m/scan', { code: code, branch_id: S.branch }, { fresh: true }); }
		catch (e) { buzz(false); box.innerHTML = '<div class="m-error"><b>' + h(code) + '</b><br>' + h(e.message) + '</div>'; return; }
		buzz(d.kind === 'product' || d.kind === 'location');
		var name = d.kind === 'product' ? d.product.name : (d.kind === 'location' ? d.location.name : '');
		var rec = recentScans().filter(function (x) { return x.code !== code; });
		rec.unshift({ code: code, name: name, at: new Date().toISOString() }); store('recent:' + C.userId, rec.slice(0, 8));
		box.innerHTML = scanResult(d);
		box.scrollIntoView({ block: 'start', behavior: 'smooth' });
	}
	function scanResult(d) {
		var acts = (d.actions || []).map(function (a) {
			return a.href ? '<a class="m-btn" href="' + h(a.href) + '"><i class="fa-solid ' + a.icon + '"></i> ' + h(a.label) + '</a>' : '<a class="m-btn ghost" href="' + h(C.base + a.web) + '"><i class="fa-solid ' + a.icon + '"></i> ' + h(a.label) + ' <i class="fa-solid fa-arrow-up-right-from-square m-xs"></i></a>';
		}).join('');
		if (d.kind === 'product') {
			var p = d.product, av = d.availability, ps = d.parsed || {};
			var html = '<div class="m-card m-scanok"><div class="m-sub">' + h(p.group_name || 'Producto') + '</div><div class="m-title">' + h(p.name) + '</div>'
				+ '<div class="m-tags">' + (ps.lot ? '<span class="m-tag">Lote ' + h(ps.lot) + '</span>' : '') + (ps.best_before ? '<span class="m-tag ' + (ps.best_before < todayStr() ? 'danger' : '') + '">Vence ' + h(day(ps.best_before)) + '</span>' : '') + (ps.serial ? '<span class="m-tag">Serie ' + h(ps.serial) + '</span>' : '') + '</div>';
			if (av) {
				html += '<div class="m-avail"><div><b class="m-num">' + qty(av.available) + '</b><span>disponible</span></div><div><b class="m-num">' + qty(av.physical) + '</b><span>físico</span></div>' + (n(av.reserved) ? '<div><b class="m-num">' + qty(av.reserved) + '</b><span>reservado</span></div>' : '') + (n(av.expired) ? '<div class="danger"><b class="m-num">' + qty(av.expired) + '</b><span>vencido</span></div>' : '') + (n(av.quarantine) ? '<div class="warn"><b class="m-num">' + qty(av.quarantine) + '</b><span>cuarentena</span></div>' : '') + '</div>';
			}
			html += '</div>';
			if (d.entries && d.entries.length) {
				html += section('Lotes (FEFO: use primero el de arriba)') + list(d.entries.slice(0, 5).map(function (e, i) {
					var exp = e.best_before_date && e.best_before_date !== '2999-12-31' ? e.best_before_date : null;
					return row({ icon: i === 0 ? 'fa-arrow-up' : 'fa-box', tone: exp && exp < todayStr() ? 'danger' : (i === 0 ? 'ok' : ''), t1: h(e.location_name || 'Ubicación') + (e.lot_number ? ' · lote ' + h(e.lot_number) : ''), t2: (exp ? (exp < todayStr() ? 'Vencido ' : 'Vence ') + day(exp) : 'Sin vencimiento') + (e.lot_number ? '' : ' · sin lote del proveedor'), v: qty(e.amount) + ' ' + h(p.unit || 'u.') });
				}));
			}
			return html + (acts ? section('¿Qué quiere hacer?') + '<div class="m-actgrid">' + acts + '</div>' : '<p class="m-sub">Su perfil puede consultar este producto pero no tiene acciones disponibles.</p>');
		}
		if (d.kind === 'location') {
			return '<div class="m-card m-scanok"><div class="m-sub">Ubicación · ' + h(d.location.branch_name || '') + '</div><div class="m-title">' + h(d.location.name) + '</div></div>'
				+ (d.stock ? section('Productos en esta ubicación') + list(d.stock.map(function (s) { return row({ t1: h(s.name), v: qty(s.amount), href: can(P.stock) ? '#/sku/' + s.id : null }); }), 'Ubicación vacía') : '') + (acts ? '<div class="m-actgrid">' + acts + '</div>' : '');
		}
		return '<div class="m-error"><b>' + h((d.parsed && d.parsed.code) || '') + '</b><br>' + h(d.message || 'Código no reconocido') + '</div>'
			+ (d.candidates && d.candidates.length ? section('¿Quiso decir?') + list(d.candidates.map(function (c) { return row({ t1: h(c.name), href: can(P.stock) ? '#/sku/' + c.id : (can(P.wasteReport) ? '#/capture/waste?product=' + c.id : null) }); })) : '');
	}

	/* ---------------- Operaciones */
	var WEB_TOOLS = {
		'/schedule': ['Horarios completos', 'Semana, mes, plantillas y cobertura', 'fa-calendar-week'], '/workforce': ['Piso de tienda', 'Tareas y rutinas del día', 'fa-store'],
		'/kardex': ['Kardex', 'Movimientos valorizados', 'fa-book'], '/transfers': ['Traslados', 'Entre sucursales', 'fa-truck-fast'], '/inventory-control': ['Control de inventario', 'Stock fantasma, ABC-XYZ, inmovilizado', 'fa-magnifying-glass-chart'],
		'/scan': ['Sesiones de escaneo', 'Recepción, picking y despacho', 'fa-barcode'], '/orders': ['Pedidos y picking', 'Reserva FEFO y despacho', 'fa-dolly'], '/traceability': ['Trazabilidad', 'Lote de punta a punta', 'fa-route'],
		'/mrp': ['Planeación MRP', 'Reabastecimiento y recepciones', 'fa-diagram-project'], '/suppliers': ['Compras y proveedores', 'Órdenes, facturas, 3-way match', 'fa-handshake'],
		'/recalls': ['Retiros de producto', 'Lotes afectados y clientes', 'fa-rotate-left'], '/audits': ['Auditorías de tienda', 'Listas y hallazgos', 'fa-clipboard-check'],
		'/alerts': ['Centro de alertas', 'Reglas, umbrales y seguimiento', 'fa-bell'], '/kpis': ['Indicadores', 'Panel ejecutivo completo', 'fa-chart-pie'], '/reports': ['Reportes', 'Exportables', 'fa-file-lines'],
		'/branches': ['Sucursales', 'Comparación de la red', 'fa-building']
	};
	function tile(href, icon, t, s, tone) { return '<a class="m-tile ' + (tone || '') + '" href="' + h(href) + '"><span class="ic"><i class="fa-solid ' + icon + '"></i></span><b>' + h(t) + '</b><span>' + h(s) + '</span></a>'; }
	function opsHtml() {
		var t = [];
		if (role() === 'manager' && can(P.sales.concat(P.stock))) { t.push(tile('#/overview', 'fa-gauge-high', 'Resumen ejecutivo', 'Ventas, mermas e inventario', 'info')); }
		if (S.me.can_approve) { t.push(tile('#/control', 'fa-inbox', 'Centro de Control', 'Casos uno por uno', 'warn')); }
		if (can(P.stock)) { t.push(tile('#/inv', 'fa-boxes-stacked', 'Inventario', 'Stock, lotes y diferencias', '')); }
		if (can(P.waste)) { t.push(tile('#/waste', 'fa-trash-can', 'Mermas', 'Operativa, comercial y causas', 'danger')); }
		if (can(P.waste)) { t.push(tile('#/shrink', 'fa-shield-halved', 'Control de pérdidas', 'Tolerancia, errores, hurto', 'warn')); }
		if (can(P.sales)) { t.push(tile('#/sales', 'fa-chart-line', 'Ventas', 'Por departamento y hora', 'ok')); }
		if (S.me.can_team) { t.push(tile('#/team', 'fa-people-group', 'Equipo y turnos', 'Asistencia, cobertura, solicitudes', 'info')); }
		var html = t.length ? section('Mi tienda') + '<div class="m-tiles">' + t.join('') + '</div>' : '';
		var cap = captureMenu();
		if (cap) { html += section('Registrar') + cap; }
		html += '<a class="m-card m-ai" href="#/assistant"><span class="ic"><i class="fa-solid fa-wand-magic-sparkles"></i></span><span class="tx"><b>Asistente DAIRY</b><span>' + (S.me.can_ai ? 'Pregunte por mermas, críticos, reposición o cómo hacer algo' : 'Cómo hacer cada operación, paso a paso') + '</span></span><i class="fa-solid fa-chevron-right m-muted"></i></a>';
		var web = (S.me.web || []).filter(function (p) { return WEB_TOOLS[p]; });
		if (web.length) {
			html += '<details class="m-more"' + (store('opsMore') ? ' open' : '') + '><summary><span>Herramientas avanzadas <small>(' + web.length + ')</small></span><i class="fa-solid fa-chevron-down"></i></summary><p class="m-sub">Se abren en la versión web, adaptada al teléfono.</p><div class="m-menu">'
				+ web.map(function (p) { var w = WEB_TOOLS[p]; return '<a href="' + h(C.base + p) + '"><span class="ic"><i class="fa-solid ' + w[2] + '"></i></span><span>' + w[0] + '<small>' + w[1] + '</small></span><i class="fa-solid fa-arrow-up-right-from-square m-muted m-xs"></i></a>'; }).join('') + '</div></details>';
		}
		return html;
	}
	function bindOps() { var det = $main.querySelector('.m-more'); if (det) { det.addEventListener('toggle', function () { store('opsMore', det.open); }); } }
	VIEWS.ops = async function () {
		setTitle('Operaciones'); crumbs([['Menú', '#/menu'], ['Operaciones', location.hash]]);
		$main.innerHTML = opsHtml() || '<div class="m-card m-empty"><i class="fa-solid fa-lock"></i>Su perfil no tiene herramientas operativas asignadas.</div>';
		bindOps();
	};

	/* ---------------- Equipo (supervisor / gerente) */
	var STATUS = { scheduled: ['Programado', ''], on_shift: ['En turno', 'ok'], late: ['Llegó tarde', 'warn'], absent: ['Sin marcar', 'danger'], finished: ['Terminó', 'muted'], absence: ['Ausencia', 'muted'] };
	VIEWS.team = async function (r, signal) {
		var date = /^\d{4}-\d{2}-\d{2}$/.test(r.q.date || '') ? r.q.date : todayStr();
		var v = ['day', 'week', 'hours', 'log'].indexOf(r.q.v) >= 0 ? r.q.v : 'day';
		setTitle('Equipo'); crumbs([['Operaciones', '#/ops'], ['Equipo', location.hash]]); skel(4);
		var d = await get('/m/team', { date: date, branch_id: S.branch }, { signal: signal, fresh: true });
		var iso = function (x) { return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0'); };
		var base = new Date(date + 'T12:00'), mon = new Date(base); mon.setDate(base.getDate() - ((base.getDay() + 6) % 7));
		var days = [];
		for (var i = 0; i < 7; i++) { var x = new Date(mon); x.setDate(mon.getDate() + i); days.push(iso(x)); }
		var prevW = new Date(mon); prevW.setDate(mon.getDate() - 7); var nextW = new Date(mon); nextW.setDate(mon.getDate() + 7);
		var tabs = [['day', 'Día'], ['week', 'Semana']];
		if (d.can_attendance) { tabs.push(['hours', 'Horas']); }
		if (d.can_edit) { tabs.push(['log', 'Historial']); }
		var html = segQ(tabs, v, 'v');
		html += '<div class="m-days"><button type="button" data-tab-q="date" data-val="' + iso(prevW) + '" aria-label="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>'
			+ days.map(function (x) { var dd = new Date(x + 'T12:00'); return '<button type="button" class="' + (x === date ? 'on' : '') + (x === todayStr() ? ' today' : '') + '" data-tab-q="date" data-val="' + x + '"><small>' + dd.toLocaleDateString('es', { weekday: 'short' }).slice(0, 2) + '</small><b>' + dd.getDate() + '</b></button>'; }).join('')
			+ '<button type="button" data-tab-q="date" data-val="' + iso(nextW) + '" aria-label="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button></div>';
		var ctx = { d: d, date: date, days: days, monday: days[0] };
		if (v === 'week') { return teamWeek(html, ctx, signal); }
		if (v === 'hours') { return teamHours(html, ctx, signal); }
		if (v === 'log') { return teamLog(html, ctx, signal); }
		var cnt = { on_shift: 0, late: 0, absent: 0 };
		d.people.forEach(function (p) { if (cnt[p.status] != null) { cnt[p.status]++; } });
		html += '<div class="m-teamsum m-card"><div><b class="m-num">' + d.people.length + '</b><span>programados</span></div><div class="ok"><b class="m-num">' + cnt.on_shift + '</b><span>en turno</span></div><div class="' + (cnt.late ? 'warn' : '') + '"><b class="m-num">' + cnt.late + '</b><span>tarde</span></div><div class="' + (cnt.absent ? 'danger' : '') + '"><b class="m-num">' + cnt.absent + '</b><span>sin marcar</span></div></div>';
		var cov = d.coverage;
		if (cov.has_requirements && cov.gaps.length) { html += '<div class="m-note warn"><i class="fa-solid fa-user-clock"></i> Cobertura insuficiente: ' + cov.gaps.map(function (g) { return g.hour + ':00 (' + g.scheduled + '/' + g.required + ')'; }).join(', ') + '</div>'; }
		else if (cov.has_requirements) { html += '<div class="m-note ok"><i class="fa-solid fa-circle-check"></i> Cobertura mínima cumplida todo el día.</div>'; }
		else { html += '<p class="m-sub">Sin mínimos de cobertura configurados (se definen en Horarios completos).</p>'; }
		if (d.requests.length) { html += section('Solicitudes por decidir') + d.requests.map(taskCard).join(''); }
		html += section('Turnos del día', d.can_edit ? '<button type="button" id="m-addshift"><i class="fa-solid fa-plus"></i>&nbsp;Agregar</button>' : '');
		html += d.people.length ? '<div class="m-list">' + d.people.map(function (p, i) {
			var s = STATUS[p.status] || ['', ''];
			return '<button type="button" class="m-row" data-p="' + i + '" style="width:100%;border:0;background:none;text-align:left"><span class="m-avatar sm">' + h(initials(p.name)) + '</span><span class="tx"><div class="t1">' + h(p.name) + '</div><div class="t2">' + p.start + '–' + p.end + (p.department ? ' · ' + h(p.department) : '') + (p.shift_status === 'planned' ? ' · <b>borrador</b>' : '') + '</div></span><span class="m-tag ' + s[1] + '">' + (p.absence ? h(p.absence) : s[0]) + '</span></button>';
		}).join('') + '</div>' : '<div class="m-card m-empty"><i class="fa-regular fa-calendar"></i>Sin turnos programados este día.</div>';
		$main.innerHTML = html;
		bindRequests($main, d.requests);
		$main.querySelectorAll('[data-p]').forEach(function (b) { b.onclick = function () { personSheet(d.people[n(b.dataset.p)], d, date); }; });
		var add = document.getElementById('m-addshift'); if (add) { add.onclick = function () { shiftSheet(d, date); }; }
	};
	function initials(nm) { return String(nm || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (x) { return x.charAt(0).toUpperCase(); }).join(''); }
	function issuesHtml(list) { return (list || []).map(function (i) { return '<div class="m-note ' + (i.level === 'error' ? 'danger' : 'warn') + '"><i class="fa-solid fa-triangle-exclamation"></i> ' + h(i.message) + '</div>'; }).join(''); }
	function skippedMsg(res) { return res && res.skipped && res.skipped.length ? ' · ' + res.skipped.length + ' omitidos por conflicto' : ''; }

	function personSheet(p, d, date) {
		var s = STATUS[p.status] || ['', ''], isToday = date === todayStr(), btns = [], future = date >= todayStr();
		if (d.can_attendance && isToday && ['scheduled', 'absent'].indexOf(p.status) >= 0) { btns.push('<button type="button" class="m-btn accent" data-pa="in"><i class="fa-solid fa-right-to-bracket"></i> Registrar entrada</button>'); }
		if (d.can_attendance && isToday && ['on_shift', 'late'].indexOf(p.status) >= 0) { btns.push('<button type="button" class="m-btn" data-pa="out"><i class="fa-solid fa-right-from-bracket"></i> Registrar salida</button>'); }
		if (d.can_edit && future && !p.clock_in) { btns.push('<button type="button" class="m-btn primary" data-pa="edit"><i class="fa-solid fa-pen"></i> Editar horario o persona</button>'); }
		var more = [];
		if (d.can_task && future && p.shift_status === 'published') { more.push(['task', 'fa-list-check', 'Asignar tarea']); }
		if (d.can_edit) { more.push(['dup', 'fa-copy', 'Duplicar a otros días']); }
		if (d.can_edit && future && !p.clock_in) { more.push(['split', 'fa-scissors', 'Dividir turno']); }
		if (d.can_edit) { more.push(['hist', 'fa-clock-rotate-left', 'Ver historial']); }
		if (d.can_edit && future && !p.clock_in) { more.push(['cancel', 'fa-ban', 'Anular turno']); }
		sheet(p.name, '<dl class="m-kv"><dt>Turno</dt><dd>' + p.start + '–' + p.end + (p.end <= p.start ? ' (termina al día siguiente)' : '') + '</dd><dt>Descanso</dt><dd>' + p.break_minutes + ' min</dd><dt>Puesto</dt><dd>' + h(p.role || p.position || '—') + '</dd><dt>Estado</dt><dd>' + (p.absence ? h(p.absence) : s[0]) + (p.shift_status === 'planned' ? ' · borrador' : '') + '</dd>' + (p.clock_in ? '<dt>Entrada</dt><dd>' + h(String(p.clock_in).slice(11, 16)) + '</dd>' : '') + '</dl>'
			+ '<div style="display:grid;gap:8px;margin-top:14px">' + btns.join('') + '</div>'
			+ (more.length ? '<div class="m-menu" style="margin-top:10px">' + more.map(function (m) { return '<button type="button" data-pa="' + m[0] + '"' + (m[0] === 'cancel' ? ' style="color:var(--danger)"' : '') + '><span class="ic"><i class="fa-solid ' + m[1] + '"></i></span><span style="flex:1">' + m[2] + '</span><i class="fa-solid fa-chevron-right m-xs"></i></button>'; }).join('') + '</div>' : ''), function (body, close) {
			body.querySelectorAll('[data-pa]').forEach(function (b) {
				b.onclick = async function () {
					var a = b.dataset.pa, res;
					try {
						if (a === 'edit') { close(); return shiftSheet(d, date, p); }
						if (a === 'dup') { close(); return duplicateSheet(p, d); }
						if (a === 'split') { close(); return splitSheet(p, d); }
						if (a === 'hist') { close(); return historySheet(p); }
						if (a === 'task') {
							close();
							var what = await confirmSheet('Asignar tarea', p.name + ' la verá en Mis tareas y recibirá un aviso.', 'Asignar', 'primary', { label: 'Qué hay que hacer', required: 'Describa la tarea' });
							if (!what) { return; }
							res = await post('/m/tasks', { employee_id: p.employee_id, title: what, due: date === todayStr() ? null : date + ' ' + p.start });
							if (res) { toast(res.message || 'Tarea asignada', 'ok'); }
							return;
						}
						if (a === 'cancel') {
							close();
							var why = await confirmSheet('Anular turno', 'Se anula el turno de ' + p.name + ' (' + p.start + '–' + p.end + ').' + (p.shift_status === 'published' ? ' La persona recibirá un aviso.' : ''), 'Anular', 'danger', { label: 'Motivo', required: 'Indique el motivo' });
							if (!why) { return; }
							res = await post('/schedule/shifts/' + p.shift_id + '/cancel', { reason: why });
						} else { res = await post('/schedule/clock-' + a, { employee_id: p.employee_id, source: 'manager' }, b); close(); }
						if (res) { toast('Listo', 'ok'); render(true); }
					} catch (e) { toast(e.message, 'err'); }
				};
			});
		});
	}

	// Crear o editar (p = turno existente): verificación de conflictos e impacto antes de confirmar
	function shiftSheet(d, date, p) {
		var edit = !!p, emps = d.employees;
		var opt = function (sel) { return emps.map(function (e) { return '<option value="' + e.id + '"' + (String(e.id) === String(sel) ? ' selected' : '') + '>' + h(e.name) + (e.position ? ' · ' + h(e.position) : '') + '</option>'; }).join(''); };
		sheet(edit ? 'Editar turno' : 'Agregar turno', '<form id="m-shf" novalidate>'
			+ (edit ? '' : '<div class="m-chips" style="margin:0 0 8px"><button type="button" class="m-chip on" data-mode="one">Una persona</button><button type="button" class="m-chip" data-mode="many">Varias personas o días</button></div>')
			+ '<div class="m-field" id="m-one"><label for="m-se">Persona</label><select id="m-se" class="m-input"><option value="">— Seleccione —</option>' + opt(edit ? p.employee_id : '') + '</select></div>'
			+ '<div class="m-field" id="m-many" hidden><label>Personas</label><div class="m-checks">' + emps.map(function (e) { return '<label><input type="checkbox" name="m-me" value="' + e.id + '"> ' + h(e.name) + '</label>'; }).join('') + '</div><label style="margin-top:8px">Días de la semana</label><div class="m-checks">' + ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'].map(function (l, i) { return '<label><input type="checkbox" name="m-md" value="' + i + '"> ' + l + '</label>'; }).join('') + '</div></div>'
			+ '<div class="m-field" id="m-datef"><label for="m-sd">Fecha</label><input type="date" id="m-sd" class="m-input" value="' + h(edit ? p.shift_date || date : date) + '"></div>'
			+ '<div class="m-grid"><div class="m-field"><label for="m-ss">Entrada</label><input type="time" id="m-ss" class="m-input" value="' + (edit ? p.start : '08:00') + '"></div><div class="m-field"><label for="m-st">Salida</label><input type="time" id="m-st" class="m-input" value="' + (edit ? p.end : '16:00') + '"></div></div>'
			+ '<div class="m-field"><label for="m-sb">Descanso / almuerzo (min)</label><input type="number" id="m-sb" class="m-input" value="' + (edit ? p.break_minutes : 30) + '" min="0" max="240" inputmode="numeric"></div>'
			+ '<p class="m-sub" id="m-dur"></p>'
			+ (edit ? '<div class="m-field"><label for="m-sr">Motivo del cambio' + (p.shift_status === 'published' ? ' (se avisa a la persona)' : ' (opcional)') + '</label><input id="m-sr" class="m-input" placeholder="Ej.: cubre apertura"></div>' : '')
			+ '<div id="m-sw"></div><div class="m-err" id="m-serr" hidden></div><button type="submit" class="m-btn primary" id="m-sok">Revisar</button>'
			+ '<p class="m-sub">' + (edit && p.shift_status === 'published' ? 'El turno ya está publicado: el cambio se aplica al guardar y queda en el historial.' : 'Queda en borrador hasta publicar la semana.') + '</p></form>', function (body, close) {
			var f = body.querySelector('#m-shf'), checked = false, mode = 'one';
			var dur = function () {
				var a = f.querySelector('#m-ss').value, b = f.querySelector('#m-st').value, br = n(f.querySelector('#m-sb').value);
				if (!a || !b) { return; }
				var m = (n(b.slice(0, 2)) * 60 + n(b.slice(3))) - (n(a.slice(0, 2)) * 60 + n(a.slice(3))); if (m <= 0) { m += 1440; }
				body.querySelector('#m-dur').textContent = 'Duración ' + qty(m / 60) + ' h · netas ' + qty(Math.max(0, m - br) / 60) + ' h' + (b <= a ? ' · termina al día siguiente' : '');
			};
			dur();
			body.querySelectorAll('[data-mode]').forEach(function (c) {
				c.onclick = function () {
					mode = c.dataset.mode; body.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.toggle('on', x === c); });
					body.querySelector('#m-one').hidden = mode !== 'one'; body.querySelector('#m-many').hidden = mode !== 'many'; body.querySelector('#m-datef').hidden = mode !== 'one';
					f.oninput();
				};
			});
			f.oninput = function () { checked = false; body.querySelector('#m-sok').textContent = 'Revisar'; body.querySelector('#m-sw').innerHTML = ''; dur(); };
			f.onsubmit = async function (e) {
				e.preventDefault();
				var b = { start_time: f.querySelector('#m-ss').value, end_time: f.querySelector('#m-st').value, break_minutes: n(f.querySelector('#m-sb').value), branch_id: d.branch_id };
				var er = body.querySelector('#m-serr'); er.hidden = true;
				try {
					if (mode === 'many') {
						var es = Array.prototype.map.call(f.querySelectorAll('[name=m-me]:checked'), function (x) { return n(x.value); });
						var ds = Array.prototype.map.call(f.querySelectorAll('[name=m-md]:checked'), function (x) { var dd = new Date(d.date + 'T12:00'); dd.setDate(dd.getDate() - ((dd.getDay() + 6) % 7) + n(x.value)); return dd.getFullYear() + '-' + String(dd.getMonth() + 1).padStart(2, '0') + '-' + String(dd.getDate()).padStart(2, '0'); });
						if (!es.length || !ds.length || !b.start_time || !b.end_time) { er.textContent = 'Elija personas, días y horario.'; er.hidden = false; return; }
						if (!checked) { checked = true; body.querySelector('#m-sw').innerHTML = '<div class="m-note"><i class="fa-solid fa-circle-info"></i> Se crearán hasta ' + (es.length * ds.length) + ' turnos en borrador. Los que tengan conflicto se omiten y se informan.</div>'; body.querySelector('#m-sok').textContent = 'Crear ' + (es.length * ds.length) + ' turnos'; return; }
						var rb = await post('/schedule/shifts/bulk', Object.assign(b, { employee_ids: es, dates: ds }), body.querySelector('#m-sok'));
						if (rb) { close(); toast(rb.created.length + ' turnos creados' + skippedMsg(rb), rb.skipped.length ? 'warn' : 'ok'); render(true); }
						return;
					}
					b.employee_id = n(f.querySelector('#m-se').value); b.shift_date = f.querySelector('#m-sd').value;
					if (edit) { b.id = p.shift_id; b.version = p.version; b.reason = f.querySelector('#m-sr').value.trim(); b.department = p.department; b.role = p.role; }
					if (!b.employee_id || !b.shift_date || !b.start_time || !b.end_time) { er.textContent = 'Complete persona, fecha y horario.'; er.hidden = false; return; }
					if (edit && p.shift_status === 'published' && !b.reason) { er.textContent = 'Indique el motivo: la persona recibirá un aviso.'; er.hidden = false; return; }
					if (!checked) {
						// Conflictos (solapamiento, descanso mínimo, ausencias, disponibilidad, certificaciones, límite diario, horas extra)
						var issues = await post('/schedule/shifts/check', b);
						var errs = (issues || []).filter(function (i) { return i.level === 'error'; });
						body.querySelector('#m-sw').innerHTML = issuesHtml(issues) || '<div class="m-note ok"><i class="fa-solid fa-circle-check"></i> Sin conflictos.</div>';
						if (errs.length) { return; }
						checked = true; body.querySelector('#m-sok').textContent = (issues || []).length ? 'Guardar de todos modos' : (edit ? 'Guardar cambios' : 'Guardar turno'); return;
					}
					var res = await post('/schedule/shifts', b, body.querySelector('#m-sok'));
					if (res) { close(); toast(edit ? 'Turno actualizado' : 'Turno agregado (borrador)', 'ok'); render(true); }
				} catch (x) { er.textContent = x.message; er.hidden = false; checked = false; body.querySelector('#m-sok').textContent = 'Revisar'; }
			};
		});
	}

	function duplicateSheet(p, d) {
		var base = new Date((p.shift_date || d.date) + 'T12:00'), opts = [];
		for (var i = 1; i <= 13; i++) { var x = new Date(base); x.setDate(base.getDate() + i); opts.push(x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0')); }
		sheet('Duplicar turno', '<p class="m-sub">' + h(p.name) + ' · ' + p.start + '–' + p.end + '. Elija días (y opcionalmente otras personas).</p><div class="m-checks">' + opts.map(function (o) { var dd = new Date(o + 'T12:00'); return '<label><input type="checkbox" name="m-dd" value="' + o + '"> ' + dd.toLocaleDateString('es', { weekday: 'short', day: 'numeric' }) + '</label>'; }).join('') + '</div>'
			+ '<label style="margin-top:10px;display:block">Personas</label><div class="m-checks">' + d.employees.map(function (e) { return '<label><input type="checkbox" name="m-de" value="' + e.id + '"' + (e.id === p.employee_id ? ' checked' : '') + '> ' + h(e.name) + '</label>'; }).join('') + '</div>'
			+ '<div class="m-err" id="m-derr" hidden></div><button type="button" class="m-btn primary" id="m-dok" style="margin-top:12px">Duplicar en borrador</button>', function (body, close) {
			body.querySelector('#m-dok').onclick = async function () {
				var ds = Array.prototype.map.call(body.querySelectorAll('[name=m-dd]:checked'), function (x) { return x.value; });
				var es = Array.prototype.map.call(body.querySelectorAll('[name=m-de]:checked'), function (x) { return n(x.value); });
				var er = body.querySelector('#m-derr'); er.hidden = true;
				if (!ds.length || !es.length) { er.textContent = 'Elija al menos un día y una persona.'; er.hidden = false; return; }
				try { var r = await post('/schedule/shifts/' + p.shift_id + '/duplicate', { dates: ds, employee_ids: es }, this); if (r) { close(); toast(r.created.length + ' turnos creados' + skippedMsg(r), r.skipped.length ? 'warn' : 'ok'); render(true); } } catch (x) { er.textContent = x.message; er.hidden = false; }
			};
		});
	}

	function splitSheet(p, d) {
		sheet('Dividir turno', '<p class="m-sub">' + h(p.name) + ' · ' + p.start + '–' + p.end + '. Cada tramo debe durar al menos 1 h; el descanso queda en el más largo.</p>'
			+ '<div class="m-field"><label for="m-pa">Hora de corte</label><input type="time" id="m-pa" class="m-input"></div>'
			+ '<div class="m-field"><label for="m-pe">Segundo tramo para</label><select id="m-pe" class="m-input"><option value="">' + h(p.name) + ' (misma persona)</option>' + d.employees.filter(function (e) { return e.id !== p.employee_id; }).map(function (e) { return '<option value="' + e.id + '">' + h(e.name) + '</option>'; }).join('') + '</select></div>'
			+ '<div class="m-field"><label for="m-pr">Motivo</label><input id="m-pr" class="m-input" placeholder="Ej.: cobertura compartida"></div>'
			+ '<div class="m-err" id="m-perr" hidden></div><button type="button" class="m-btn primary" id="m-pok">Dividir</button>', function (body, close) {
			body.querySelector('#m-pok').onclick = async function () {
				var er = body.querySelector('#m-perr'); er.hidden = true;
				var at = body.querySelector('#m-pa').value;
				if (!at) { er.textContent = 'Indique la hora de corte.'; er.hidden = false; return; }
				try { var r = await post('/schedule/shifts/' + p.shift_id + '/split', { at: at, second_employee_id: n(body.querySelector('#m-pe').value) || null, reason: body.querySelector('#m-pr').value.trim() }, this); if (r) { close(); toast('Turno dividido' + (r.warnings && r.warnings.length ? ' · ' + r.warnings[0].message : ''), r.warnings && r.warnings.length ? 'warn' : 'ok'); render(true); } } catch (x) { er.textContent = x.message; er.hidden = false; }
			};
		});
	}

	var ACTION_LBL = { create: 'Creado', update: 'Editado', move: 'Movido', cancel: 'Anulado', publish: 'Publicado', split: 'Dividido', duplicate: 'Duplicado', swap: 'Cambio aprobado', undo: 'Deshecho', template: 'Desde plantilla' };
	function changeText(c) {
		var b = c.before_json ? JSON.parse(c.before_json) : null, a = c.after_json ? JSON.parse(c.after_json) : null, t = [];
		if (b && a) {
			if (b.shift_date !== a.shift_date) { t.push(day(b.shift_date) + ' → ' + day(a.shift_date)); }
			if (b.start_time !== a.start_time || b.end_time !== a.end_time) { t.push(hhmm(b.start_time) + '–' + hhmm(b.end_time) + ' → ' + hhmm(a.start_time) + '–' + hhmm(a.end_time)); }
			if (String(b.break_minutes) !== String(a.break_minutes)) { t.push('descanso ' + b.break_minutes + ' → ' + a.break_minutes + ' min'); }
			if (String(b.employee_id) !== String(a.employee_id)) { t.push('cambio de persona'); }
		} else if (a) { t.push(day(a.shift_date) + ' ' + hhmm(a.start_time) + '–' + hhmm(a.end_time)); }
		return t.join(' · ');
	}
	async function historySheet(p) {
		try {
			var rows = await get('/schedule/shifts/' + p.shift_id + '/history', {}, { fresh: true });
			sheet('Historial del turno', list(rows.map(function (c) { return row({ icon: 'fa-clock-rotate-left', t1: (ACTION_LBL[c.action] || c.action) + (n(c.undone) ? ' (deshecho)' : ''), t2: h(changeText(c)) + (c.reason ? ' · ' + h(c.reason) : '') + '<br>' + h(c.user_name || '—') + ' · ' + h(String(c.row_created_timestamp).slice(0, 16)) }); }), 'Sin cambios registrados'));
		} catch (e) { toast(e.message, 'err'); }
	}

	// Semana: borradores, horas por persona, publicar, copiar, plantillas y deshacer
	async function teamWeek(html, ctx, signal) {
		var d = ctx.d;
		var w = await get('/schedule/week', { branch_id: d.branch_id, week: ctx.monday }, { signal: signal, fresh: true });
		var log = d.can_edit ? await get('/schedule/changes', { branch_id: d.branch_id, limit: 1 }, { signal: signal, fresh: true }) : null;
		var gapsDays = w.days.filter(function (x) { return w.coverage[x] && w.coverage[x].gaps.length; });
		html += '<div class="m-teamsum m-card"><div><b class="m-num">' + Object.keys(w.totals).reduce(function (a, k) { return a + n(w.totals[k].scheduled); }, 0).toFixed(0) + '</b><span>h programadas</span></div><div class="' + (w.planned_count ? 'warn' : 'ok') + '"><b class="m-num">' + w.planned_count + '</b><span>borradores</span></div><div class="' + (gapsDays.length ? 'danger' : '') + '"><b class="m-num">' + gapsDays.length + '</b><span>días con huecos</span></div><div><b class="m-num">' + money(w.labor_cost, true) + '</b><span>costo</span></div></div>';
		if (d.can_edit) {
			html += '<div class="m-grid" style="grid-template-columns:1fr 1fr;margin-bottom:8px">'
				+ '<button type="button" class="m-btn primary" id="m-wpub"' + (w.planned_count ? '' : ' disabled') + '><i class="fa-solid fa-paper-plane"></i> Publicar' + (w.planned_count ? ' (' + w.planned_count + ')' : '') + '</button>'
				+ '<button type="button" class="m-btn" id="m-wundo"' + (log && log.can_undo ? '' : ' disabled') + '><i class="fa-solid fa-rotate-left"></i> Deshacer</button>'
				+ '<button type="button" class="m-btn" id="m-wcopy"><i class="fa-solid fa-copy"></i> Copiar a sig. semana</button>'
				+ '<button type="button" class="m-btn" id="m-wtpl"><i class="fa-solid fa-layer-group"></i> Plantillas</button></div>';
		}
		html += section('Personas');
		html += '<div class="m-list">' + w.employees.map(function (e) {
			var t = w.totals[e.id] || {}, cells = w.days.map(function (x) {
				var sh = (w.grid[e.id] || {})[x] || [], ab = (w.absences[e.id] || {})[x];
				if (ab) { return '<span class="m-wk ab" title="Ausencia">A</span>'; }
				if (!sh.length) { return '<span class="m-wk">·</span>'; }
				var bad = sh.some(function (s) { return s.issues && s.issues.some(function (i) { return i.level === 'error'; }); });
				return '<span class="m-wk ' + (sh[0].status === 'planned' ? 'draft' : 'pub') + (bad ? ' bad' : '') + '">' + hhmm(sh[0].start_time).slice(0, 2) + '</span>';
			}).join('');
			var over = n(t.scheduled) > n(t.contract);
			return '<div class="m-row"><span class="tx"><div class="t1">' + h(e.name) + '</div><div class="m-wkrow">' + cells + '</div></span><span class="m-num ' + (over ? 'm-warn' : '') + '" style="text-align:right">' + qty(t.scheduled) + ' h<br><small class="m-sub">de ' + qty(t.contract) + '</small></span></div>';
		}).join('') + '</div><p class="m-sub">Celdas: hora de entrada · <span class="m-wk pub">08</span> publicado · <span class="m-wk draft">08</span> borrador · <span class="m-wk ab">A</span> ausencia.</p>';
		$main.innerHTML = html;
		var bind = function (id, fn) { var el = document.getElementById(id); if (el) { el.onclick = fn; } };
		bind('m-wpub', async function () {
			if (!await confirmSheet('Publicar semana', 'Se publican ' + w.planned_count + ' turnos en borrador. Cada persona verá su horario y recibirá un aviso. Lo publicado ya no se deshace (se edita).', 'Publicar', 'primary')) { return; }
			try { var r = await post('/schedule/week/publish', { branch_id: d.branch_id, week: ctx.monday }); toast(r.published + ' turnos publicados', 'ok'); render(true); } catch (e) { toast(e.message, 'err'); }
		});
		bind('m-wundo', async function () {
			if (!await confirmSheet('Deshacer', 'Se revierte su último cambio en borrador (o la operación completa: copia, plantilla, alta masiva, división).', 'Deshacer', 'primary')) { return; }
			try { var r = await post('/schedule/undo', { branch_id: d.branch_id }); toast(r.undone + ' cambios deshechos', 'ok'); render(true); } catch (e) { toast(e.message, 'err'); }
		});
		bind('m-wcopy', async function () {
			var to = new Date(ctx.monday + 'T12:00'); to.setDate(to.getDate() + 7);
			var toS = to.getFullYear() + '-' + String(to.getMonth() + 1).padStart(2, '0') + '-' + String(to.getDate()).padStart(2, '0');
			if (!await confirmSheet('Copiar semana', 'Los turnos de esta semana se copian en borrador a la semana del ' + day(toS) + '. Los que tengan conflicto se omiten.', 'Copiar', 'primary')) { return; }
			try { var r = await post('/schedule/week/copy', { branch_id: d.branch_id, from_week: ctx.monday, to_week: toS }); toast(r.created + ' turnos copiados' + skippedMsg(r), r.skipped.length ? 'warn' : 'ok'); } catch (e) { toast(e.message, 'err'); }
		});
		bind('m-wtpl', function () { templatesSheet(d, ctx.monday); });
	}

	async function templatesSheet(d, monday) {
		try {
			var tpls = await get('/schedule/templates', { branch_id: d.branch_id }, { fresh: true });
			var wd = ['', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
			sheet('Plantillas de turno', (tpls.length ? '<div class="m-menu">' + tpls.map(function (t) { return '<button type="button" data-t="' + t.id + '"><span class="ic"><i class="fa-solid fa-layer-group"></i></span><span style="flex:1"><b>' + h(t.name) + '</b><br><small>' + hhmm(t.start_time) + '–' + hhmm(t.end_time) + ' · ' + String(t.weekdays).split(',').map(function (x) { return wd[n(x)]; }).join(' ') + '</small></span><i class="fa-solid fa-chevron-right m-xs"></i></button>'; }).join('') + '</div>' : '<p class="m-sub">Aún no hay plantillas.</p>')
				+ '<details style="margin-top:12px"><summary class="m-btn ghost">Nueva plantilla</summary><form id="m-tf" novalidate style="margin-top:10px"><div class="m-field"><label for="m-tn">Nombre</label><input id="m-tn" class="m-input" placeholder="Ej.: Apertura"></div><div class="m-grid"><div class="m-field"><label for="m-ts">Entrada</label><input type="time" id="m-ts" class="m-input" value="06:00"></div><div class="m-field"><label for="m-te">Salida</label><input type="time" id="m-te" class="m-input" value="14:00"></div></div><div class="m-field"><label for="m-tb">Descanso (min)</label><input type="number" id="m-tb" class="m-input" value="30" min="0" max="240"></div><div class="m-checks">' + [1, 2, 3, 4, 5, 6, 7].map(function (x) { return '<label><input type="checkbox" name="m-tw" value="' + x + '"' + (x <= 5 ? ' checked' : '') + '> ' + wd[x] + '</label>'; }).join('') + '</div><div class="m-err" id="m-terr" hidden></div><button type="submit" class="m-btn primary" style="margin-top:10px">Guardar plantilla</button></form></details>', function (body, close) {
				body.querySelectorAll('[data-t]').forEach(function (b) { b.onclick = function () { close(); applyTemplateSheet(tpls.find(function (t) { return String(t.id) === b.dataset.t; }), d, monday); }; });
				body.querySelector('#m-tf').onsubmit = async function (e) {
					e.preventDefault(); var er = body.querySelector('#m-terr'); er.hidden = true;
					try { await post('/schedule/templates', { branch_id: d.branch_id, name: body.querySelector('#m-tn').value.trim(), start_time: body.querySelector('#m-ts').value, end_time: body.querySelector('#m-te').value, break_minutes: n(body.querySelector('#m-tb').value), weekdays: Array.prototype.map.call(body.querySelectorAll('[name=m-tw]:checked'), function (x) { return n(x.value); }) }); close(); toast('Plantilla guardada', 'ok'); templatesSheet(d, monday); } catch (x) { er.textContent = x.message; er.hidden = false; }
				};
			});
		} catch (e) { toast(e.message, 'err'); }
	}
	function applyTemplateSheet(t, d, monday) {
		sheet('Aplicar «' + t.name + '»', '<p class="m-sub">Semana del ' + day(monday) + ' · ' + hhmm(t.start_time) + '–' + hhmm(t.end_time) + '. Se crean en borrador; los conflictos se omiten.</p><div class="m-checks">' + d.employees.map(function (e) { return '<label><input type="checkbox" name="m-ae" value="' + e.id + '"> ' + h(e.name) + '</label>'; }).join('') + '</div><div class="m-err" id="m-aerr2" hidden></div><div class="m-grid" style="grid-template-columns:1fr 1fr;margin-top:12px"><button type="button" class="m-btn danger ghost" id="m-adel">Eliminar plantilla</button><button type="button" class="m-btn primary" id="m-aok2">Aplicar</button></div>', function (body, close) {
			body.querySelector('#m-aok2').onclick = async function () {
				var es = Array.prototype.map.call(body.querySelectorAll('[name=m-ae]:checked'), function (x) { return n(x.value); }), er = body.querySelector('#m-aerr2'); er.hidden = true;
				if (!es.length) { er.textContent = 'Elija al menos una persona.'; er.hidden = false; return; }
				try { var r = await post('/schedule/templates/' + t.id + '/apply', { week: monday, employee_ids: es }, this); close(); toast(r.created.length + ' turnos creados' + skippedMsg(r), r.skipped.length ? 'warn' : 'ok'); render(true); } catch (x) { er.textContent = x.message; er.hidden = false; }
			};
			body.querySelector('#m-adel').onclick = async function () { close(); if (!await confirmSheet('Eliminar plantilla', 'Los turnos ya creados no cambian.', 'Eliminar', 'danger')) { return; } try { await post('/schedule/templates/' + t.id + '/delete', {}); toast('Plantilla eliminada', 'ok'); } catch (x) { toast(x.message, 'err'); } };
		});
	}

	// Horas: hoja por categoría, fichajes del día con corrección y correcciones pendientes
	async function teamHours(html, ctx, signal) {
		var d = ctx.d;
		var res = await Promise.all([
			get('/schedule/timesheet', { branch_id: d.branch_id, from: ctx.monday, to: ctx.days[6] }, { signal: signal, fresh: true }),
			get('/schedule/attendance', { branch_id: d.branch_id, from: ctx.date, to: ctx.date }, { signal: signal, fresh: true }),
			get('/schedule/adjustments', { branch_id: d.branch_id, status: 'requested' }, { signal: signal, fresh: true })
		]);
		var ts = res[0], att = res[1], adj = res[2];
		var adjReq = d.requests.filter(function (x) { return x.type === 'adjustment'; });
		if (adjReq.length) { html += section('Correcciones por decidir') + adjReq.map(taskCard).join(''); }
		else if (adj.length) { html += '<div class="m-note"><i class="fa-solid fa-hourglass-half"></i> ' + adj.length + ' correcciones esperan aprobación de otra persona.</div>'; }
		html += section('Semana del ' + day(ctx.monday)) + '<p class="m-sub">Programadas = turnos publicados · Trabajadas = fichajes cerrados menos descanso · Extra = trabajadas sobre contrato.</p>';
		html += list(ts.rows.filter(function (x) { return n(x.scheduled) || n(x.scheduled_draft) || n(x.worked) || n(x.pending_review) || n(x.absence_days) || n(x.time_off_days); }).map(function (x) {
			var tags = [];
			if (n(x.overtime)) { tags.push('<span class="m-tag warn">+' + qty(x.overtime) + ' h extra</span>'); }
			if (n(x.pending_review)) { tags.push('<span class="m-tag danger">' + x.pending_review + ' por revisar</span>'); }
			if (n(x.corrections) || n(x.manual_entries)) { tags.push('<span class="m-tag muted">' + (n(x.corrections) + n(x.manual_entries)) + ' ajustes</span>'); }
			if (n(x.absence_days)) { tags.push('<span class="m-tag muted">' + x.absence_days + ' d ausencia</span>'); }
			if (n(x.time_off_days)) { tags.push('<span class="m-tag muted">' + x.time_off_days + ' d libre</span>'); }
			return row({ icon: 'fa-user-clock', t1: h(x.employee), t2: 'Prog. ' + qty(x.scheduled) + ' h' + (n(x.scheduled_draft) ? ' (+' + qty(x.scheduled_draft) + ' borrador)' : '') + ' · Trab. <b>' + qty(x.worked) + ' h</b> · Desc. ' + qty(x.breaks_hours) + ' h' + (tags.length ? '<br>' + tags.join(' ') : '') });
		}), 'Sin horas en esta semana');
		html += section('Fichajes del ' + day(ctx.date), '<button type="button" id="m-addatt"><i class="fa-solid fa-plus"></i>&nbsp;Fichaje omitido</button>');
		html += list(att.map(function (a, i) {
			return '<button type="button" class="m-row" data-att="' + i + '" style="width:100%;border:0;background:none;text-align:left"><span class="tx"><div class="t1">' + h(a.employee_name) + '</div><div class="t2">' + h(String(a.clock_in).slice(11, 16)) + ' → ' + (a.clock_out ? h(String(a.clock_out).slice(11, 16)) : 'sin salida') + (a.hours != null ? ' · ' + qty(a.hours) + ' h' : '') + (n(a.late_minutes) > 5 ? ' · tarde ' + a.late_minutes + ' min' : '') + '</div></span>' + (a.pending_adjustment_id ? '<span class="m-tag warn">corrección pendiente</span>' : (n(a.corrections) ? '<span class="m-tag muted">corregido</span>' : '<i class="fa-solid fa-pen m-xs"></i>')) + '</button>';
		}), 'Sin fichajes este día');
		$main.innerHTML = html;
		bindRequests($main, d.requests);
		$main.querySelectorAll('[data-att]').forEach(function (b) { b.onclick = function () { var a = att[n(b.dataset.att)]; if (a.pending_adjustment_id) { toast('Ya hay una corrección pendiente para este fichaje', 'warn'); return; } adjustSheet(a, d, ctx.date); }; });
		document.getElementById('m-addatt').onclick = function () { adjustSheet(null, d, ctx.date); };
	}

	// Corrección (a = fichaje) o fichaje omitido (a = null). No modifica: crea una solicitud que aprueba otra persona
	function adjustSheet(a, d, date, mine) {
		var dt = function (v, fb) { return v ? String(v).slice(0, 16).replace(' ', 'T') : fb; };
		sheet(a ? 'Corregir fichaje' : 'Registrar fichaje omitido', (a ? '<dl class="m-kv"><dt>Persona</dt><dd>' + h(a.employee_name || '') + '</dd><dt>Registrado</dt><dd>' + h(String(a.clock_in).slice(0, 16)) + ' → ' + (a.clock_out ? h(String(a.clock_out).slice(11, 16)) : 'sin salida') + '</dd></dl>' : (mine ? '' : '<div class="m-field"><label for="m-je">Persona</label><select id="m-je" class="m-input"><option value="">— Seleccione —</option>' + d.employees.map(function (e) { return '<option value="' + e.id + '">' + h(e.name) + '</option>'; }).join('') + '</select></div>'))
			+ '<div class="m-field"><label for="m-ji">Entrada correcta</label><input type="datetime-local" id="m-ji" class="m-input" value="' + h(dt(a && a.clock_in, date + 'T08:00')) + '"></div>'
			+ '<div class="m-field"><label for="m-jo">Salida correcta</label><input type="datetime-local" id="m-jo" class="m-input" value="' + h(dt(a && a.clock_out, a ? '' : date + 'T16:00')) + '"></div>'
			+ '<div class="m-field"><label for="m-jr">Motivo</label><textarea id="m-jr" class="m-input" placeholder="Ej.: olvidó marcar la salida; confirmado con el supervisor"></textarea></div>'
			+ '<div class="m-note"><i class="fa-solid fa-shield-halved"></i> El registro original se conserva. El cambio se aplica solo cuando otra persona con permiso lo aprueba.</div>'
			+ '<div class="m-err" id="m-jerr" hidden></div><button type="button" class="m-btn primary" id="m-jok">Enviar a aprobación</button>', function (body, close) {
			body.querySelector('#m-jok').onclick = async function () {
				var er = body.querySelector('#m-jerr'); er.hidden = true;
				var b = { clock_in: body.querySelector('#m-ji').value.replace('T', ' '), clock_out: body.querySelector('#m-jo').value.replace('T', ' ') || null, notes: body.querySelector('#m-jr').value.trim() };
				if (a) { b.id = a.id; } else if (!mine) { b.employee_id = n(body.querySelector('#m-je').value); if (!b.employee_id) { er.textContent = 'Seleccione la persona.'; er.hidden = false; return; } }
				if (!b.clock_in || !b.notes) { er.textContent = 'Indique la entrada y el motivo.'; er.hidden = false; return; }
				try { var r = await post('/schedule/attendance', b, this); if (r) { close(); toast('Corrección enviada a aprobación', 'ok'); render(true); } } catch (x) { er.textContent = x.message; er.hidden = false; }
			};
		});
	}

	async function teamLog(html, ctx, signal) {
		var d = ctx.d;
		var log = await get('/schedule/changes', { branch_id: d.branch_id, limit: 60 }, { signal: signal, fresh: true });
		html += '<button type="button" class="m-btn" id="m-lundo"' + (log.can_undo ? '' : ' disabled') + ' style="margin-bottom:8px"><i class="fa-solid fa-rotate-left"></i> Deshacer mi último cambio en borrador</button>';
		html += section('Cambios recientes') + list(log.changes.map(function (c) { return row({ icon: 'fa-clock-rotate-left', t1: (ACTION_LBL[c.action] || c.action) + ' · ' + h(c.employee_name || ('#' + c.shift_id)) + (n(c.undone) && c.action !== 'undo' ? ' <span class="m-tag muted">deshecho</span>' : ''), t2: h(changeText(c)) + (c.reason ? ' · ' + h(c.reason) : '') + '<br>' + h(c.user_name || '—') + ' · ' + h(String(c.row_created_timestamp).slice(0, 16)) }); }), 'Sin cambios registrados');
		$main.innerHTML = html;
		var u = document.getElementById('m-lundo');
		u.onclick = async function () {
			if (!await confirmSheet('Deshacer', 'Se revierte su último cambio en borrador (o la operación completa).', 'Deshacer', 'primary')) { return; }
			try { var r = await post('/schedule/undo', { branch_id: d.branch_id }); toast(r.undone + ' cambios deshechos', 'ok'); render(true); } catch (e) { toast(e.message, 'err'); }
		};
	}

	/* ---------------- Perfil */
	async function mySchedule(signal) { return get('/m/schedule', {}, { signal: signal, fresh: true }); }
	var PROFILE = {};
	VIEWS.profile = async function (r, signal) {
		var sub = r.path[1];
		if (sub && PROFILE[sub]) { return PROFILE[sub](r, signal); }
		setTitle('Perfil'); crumbs([['Menú', '#/menu'], ['Perfil', location.hash]]);
		$main.innerHTML = profileHtml();
		document.getElementById('m-tut').onclick = tutorial;
	};
	function profileHtml() {
		var e = S.me.employee, q = queue(), qErr = q.filter(function (x) { return x.error; }).length;
		var html = '<div class="m-card m-me"><span class="m-avatar">' + h(initials(S.me.name)) + '</span><div><div class="m-title" style="font-size:19px">' + h(S.me.name) + '</div><div class="m-sub">' + h(S.me.role_label) + (e && e.position ? ' · ' + h(e.position) : '') + ' · ' + h(branchLabel()) + '</div></div></div>';
		var items = [];
		if (e) {
			items.push(['#/profile/schedule', 'fa-calendar-days', 'Mi horario', 'Turnos de las próximas 2 semanas y horas']);
			items.push(['#/profile/requests', 'fa-plane-departure', 'Ausencias y cambios de turno', 'Solicitar y ver el estado']);
			items.push(['#/profile/hours', 'fa-user-clock', 'Mis horas', 'Fichajes, horas trabajadas y correcciones']);
			items.push(['#/profile/availability', 'fa-user-clock', 'Mi disponibilidad', 'Días y horas en que puede trabajar']);
			items.push(['#/profile/training', 'fa-graduation-cap', 'Capacitación', 'Certificaciones y guías rápidas']);
			items.push(['#/openshifts', 'fa-calendar-plus', 'Turnos abiertos', 'Tome turnos disponibles de su sucursal']);
		} else if (S.me.can_team) {
			items.push(['#/openshifts', 'fa-calendar-plus', 'Turnos abiertos', 'Publicar y aprobar coberturas']);
			items.push(['#/profile/training', 'fa-graduation-cap', 'Guías rápidas', 'Cómo hacer cada operación']);
		} else {
			items.push(['#/profile/training', 'fa-graduation-cap', 'Guías rápidas', 'Cómo hacer cada operación']);
		}
		items.push(['#/messages', 'fa-comments', 'Mensajes', 'Conversaciones con su equipo' + (S.msgUnread ? ' · ' + S.msgUnread + ' sin leer' : '')]);
		items.push(['#/notifications', 'fa-bell', 'Notificaciones', 'Avisos recientes']);
		items.push(['#/profile/settings', 'fa-sliders', 'Preferencias', 'Avisos, sonido y vibración']);
		if (q.length) { items.push(['#/profile/sync', 'fa-cloud-arrow-up', 'Pendientes de sincronizar', q.length + ' operaciones' + (qErr ? ' · ' + qErr + ' con error' : '')]); }
		html += '<div class="m-menu">' + items.map(function (x) { return '<a href="' + x[0] + '"><span class="ic"><i class="fa-solid ' + x[1] + '"></i></span><span>' + x[2] + '<small>' + x[3] + '</small></span><i class="fa-solid fa-chevron-right m-muted"></i></a>'; }).join('') + '</div>';
		html += '<div class="m-menu" style="margin-top:12px"><button type="button" id="m-tut"><span class="ic"><i class="fa-solid fa-circle-play"></i></span><span>Ver el recorrido inicial<small>Cómo está organizada la app</small></span></button>'
			+ '<a href="#/assistant"><span class="ic"><i class="fa-solid fa-circle-question"></i></span><span>Ayuda<small>Pregúntele al asistente</small></span></a>'
			+ '<a href="' + h(C.webUrl) + '"><span class="ic"><i class="fa-solid fa-desktop"></i></span><span>Versión web completa<small>Todos los módulos y configuración</small></span></a>'
			+ '<a href="' + h(C.base + '/logout') + '"><span class="ic"><i class="fa-solid fa-right-from-bracket"></i></span><span>Cerrar sesión</span></a></div>';
		return html;
	}
	VIEWS.menu = async function () {
		setTitle('Menú'); crumbs(null);
		$main.innerHTML = opsHtml() + section('Mi cuenta') + profileHtml();
		bindOps();
		document.getElementById('m-tut').onclick = tutorial;
	};
	function needEmployee(d) {
		if (d.linked) { return false; }
		$main.innerHTML = '<div class="m-card m-empty"><i class="fa-solid fa-id-badge"></i>Su usuario no está vinculado a una ficha de empleado. Pida a su gerente que lo vincule en Horarios.</div>';
		return true;
	}
	PROFILE.schedule = async function (r, signal) {
		setTitle('Mi horario'); crumbs([['Perfil', '#/profile'], ['Mi horario', location.hash]]); skel(4);
		var d = await mySchedule(signal);
		if (needEmployee(d)) { return; }
		var sh = d.data.shifts, byDay = {};
		sh.forEach(function (s) { (byDay[s.shift_date] = byDay[s.shift_date] || []).push(s); });
		var html = '<div class="m-card"><div class="m-sub">Horas trabajadas esta semana</div><div class="m-title m-num">' + qty(d.data.week_worked) + ' h <small class="m-sub">de ' + qty(d.data.contract) + ' h</small></div><div class="m-bar"><i style="width:' + Math.min(100, n(d.data.week_worked) / (n(d.data.contract) || 1) * 100) + '%"></i></div></div>';
		var abs = (d.data.absences || []).filter(function (a) { return a.status === 'approved'; });
		html += section('Próximos 14 días');
		var out = '';
		for (var i = 0; i < 14; i++) {
			var ds = todayStr(i), list2 = byDay[ds] || [], ab = abs.find(function (a) { return ds >= a.start_date && ds <= a.end_date; });
			var lbl = cap(new Date(ds + 'T12:00').toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'short' }));
			out += '<div class="m-dayrow' + (i === 0 ? ' today' : '') + '"><div class="dd">' + h(lbl) + '</div>' + (ab ? '<span class="m-tag muted">' + h(d.data.absence_types[ab.absence_type] || 'Ausencia') + '</span>' : '') + (list2.length ? list2.map(function (s) {
				var conf = s.status === 'published' ? (s.confirmed_at ? '<span class="m-tag ok"><i class="fa-solid fa-check"></i> Confirmado</span>' : '<button type="button" class="m-btn sm primary" data-confirm-shift="' + s.id + '"><i class="fa-solid fa-check"></i> Confirmar</button>') : '';
				return '<div class="sh"><b class="m-num">' + hhmm(s.start_time) + '–' + hhmm(s.end_time) + '</b><span>' + qty(s.hours) + ' h' + (n(s.break_minutes) ? ' · descanso ' + s.break_minutes + ' min' : '') + (s.department ? ' · ' + h(s.department) : '') + '</span>' + conf + (i > 0 ? '<button type="button" class="m-btn sm ghost" data-swap="' + s.id + '"><i class="fa-solid fa-right-left"></i> Cambiar</button>' : '') + '</div>';
			}).join('') : (ab ? '' : '<span class="m-sub">Libre</span>')) + '</div>';
		}
		html += '<div class="m-card m-days14">' + out + '</div>';
		$main.innerHTML = html;
		$main.querySelectorAll('[data-swap]').forEach(function (b) { b.onclick = function () { swapSheet(sh.find(function (x) { return String(x.id) === b.dataset.swap; }), d.data.coworkers); }; });
		bindConfirm();
	};
	function bindConfirm() {
		$main.querySelectorAll('[data-confirm-shift]').forEach(function (b) {
			b.onclick = async function () {
				try { if (await post('/m/shifts/' + b.dataset.confirmShift + '/confirm', {}, b)) { buzz(true); toast('Turno confirmado', 'ok'); render(true); } }
				catch (e) { toast(e.message, 'err'); }
			};
		});
	}

	/* ---------------------------------------------------------------- Mensajes */
	VIEWS.messages = async function (r, signal) {
		if (r.path[1]) { return thread(parseInt(r.path[1], 10), signal); }
		setTitle('Mensajes'); crumbs([['Menú', '#/menu'], ['Mensajes', location.hash]]); skel(4);
		var d = await get('/m/messages', {}, { signal: signal, fresh: true });
		S.msgUnread = d.unread;
		var html = '<button type="button" class="m-btn primary" id="m-newmsg"><i class="fa-solid fa-pen-to-square"></i> Nuevo mensaje</button>';
		html += list(d.threads.map(function (t) {
			var ic = { direct: 'fa-user', group: 'fa-users', announcement: 'fa-bullhorn' }[t.kind];
			return row({ icon: ic, tone: t.unread ? 'warn' : '', t1: (t.unread ? '<b>' : '') + h(t.title) + (t.unread ? '</b>' : ''), t2: h(String(t.last_body || 'Sin mensajes').slice(0, 80)), v: t.unread ? '<span class="m-badge" style="position:static">' + t.unread + '</span>' : '', s: dt(t.last_message_at || t.created_at), href: '#/messages/' + t.id });
		}), 'Sin conversaciones');
		$main.innerHTML = html;
		document.getElementById('m-newmsg').onclick = newMessage;
	};
	async function newMessage() {
		var c = await get('/m/messages/contacts', {}, { fresh: true });
		var opts = c.contacts.map(function (x) { return '<label class="m-check"><input type="checkbox" value="' + x.id + '"> ' + h(x.name) + ' <small class="m-sub">' + h((x.position || '') + (x.branch ? ' · ' + x.branch : '')) + '</small></label>'; }).join('');
		var ann = c.can_announce ? '<details style="margin-top:10px"><summary class="m-sub">Aviso a toda una sucursal</summary><div class="m-field"><select id="m-ann-b" class="m-input">' + c.branches.map(function (b) { return '<option value="' + b.id + '">' + h(b.name) + '</option>'; }).join('') + '</select><input id="m-ann-t" class="m-input" maxlength="80" placeholder="Título"><textarea id="m-ann-body" class="m-input" maxlength="2000" placeholder="Mensaje del aviso"></textarea><button type="button" class="m-btn accent" id="m-ann-send"><i class="fa-solid fa-bullhorn"></i> Enviar aviso</button></div></details>' : '';
		sheet('Nuevo mensaje', (opts ? '<div class="m-field"><label>Para</label><div style="max-height:40vh;overflow:auto">' + opts + '</div></div><div class="m-field"><input id="m-grp-t" class="m-input" maxlength="80" placeholder="Nombre del grupo (si elige varios)"></div><button type="button" class="m-btn primary" id="m-msg-go">Abrir conversación</button>' : '<p class="m-sub">No hay compañeros vinculados a fichas de empleado en sus sucursales.</p>') + ann, function (body, close) {
			var go1 = body.querySelector('#m-msg-go');
			if (go1) {
				go1.onclick = async function () {
					var ids = Array.prototype.map.call(body.querySelectorAll('input[type=checkbox]:checked'), function (i) { return parseInt(i.value, 10); });
					if (!ids.length) { toast('Elija al menos una persona', 'err'); return; }
					try {
						var res = ids.length === 1 ? await post('/m/messages/direct', { user_id: ids[0] }, go1) : await post('/m/messages/group', { title: body.querySelector('#m-grp-t').value, member_ids: ids }, go1);
						close(); go('messages/' + res.thread_id);
					} catch (e) { toast(e.message, 'err'); }
				};
			}
			var a = body.querySelector('#m-ann-send');
			if (a) {
				a.onclick = async function () {
					try {
						var res = await post('/m/messages/announcement', { branch_id: parseInt(body.querySelector('#m-ann-b').value, 10), title: body.querySelector('#m-ann-t').value, body: body.querySelector('#m-ann-body').value }, a);
						close(); toast('Aviso enviado a ' + res.recipients + ' personas', 'ok'); go('messages/' + res.thread_id);
					} catch (e) { toast(e.message, 'err'); }
				};
			}
		});
	}
	async function thread(id, signal) {
		var d = await get('/m/messages/' + id, {}, { signal: signal, fresh: true });
		setTitle(d.thread.title || 'Conversación'); crumbs([['Mensajes', '#/messages'], [d.thread.title || 'Conversación', location.hash]]);
		var bubbles = d.messages.map(function (m) {
			var rd = m.mine ? (m.recipients > 1 ? 'Leído por ' + m.read_count + ' de ' + m.recipients : (m.read_count ? 'Leído' : 'Enviado')) : '';
			return '<div class="m-msg' + (m.mine ? ' mine' : '') + '">' + (m.mine ? '' : '<small class="m-sub">' + h(m.sender) + '</small>') + '<div class="b">' + h(m.body).replace(/\n/g, '<br>') + '</div><small class="m-sub">' + dt(m.created_at) + (rd ? ' · <span title="' + h(m.read_by.join(', ')) + '">' + rd + '</span>' : '') + '</small></div>';
		}).join('');
		var html = '<style>.m-msgs{display:flex;flex-direction:column;gap:8px;padding-bottom:90px}.m-msg{max-width:85%;align-self:flex-start}.m-msg.mine{align-self:flex-end;text-align:right}.m-msg .b{background:var(--card,#fff);border:1px solid var(--line,#e5e7eb);border-radius:14px;padding:8px 12px;text-align:left;white-space:normal;word-break:break-word}.m-msg.mine .b{background:var(--primary-soft,#e8fbe9)}.m-compose{position:sticky;bottom:0;display:flex;gap:8px;padding:8px 0;background:inherit}</style>';
		html += '<div class="m-msgs" id="m-msgs">' + (bubbles || '<div class="m-card m-empty"><i class="fa-regular fa-comments"></i>Escriba el primer mensaje</div>') + '</div>';
		html += d.thread.can_post ? '<form class="m-compose" id="m-compose"><textarea id="m-body" class="m-input" rows="1" maxlength="2000" placeholder="Escriba un mensaje" aria-label="Mensaje" style="flex:1"></textarea><button type="submit" class="m-btn primary" aria-label="Enviar"><i class="fa-solid fa-paper-plane"></i></button></form>' : '<div class="m-info">Este aviso es informativo: no admite respuestas.</div>';
		$main.innerHTML = html;
		window.scrollTo(0, document.body.scrollHeight);
		var f = document.getElementById('m-compose');
		if (f) {
			f.onsubmit = async function (ev) {
				ev.preventDefault();
				var t = document.getElementById('m-body'), btn = f.querySelector('button');
				if (!t.value.trim()) { return; }
				btn.dataset.uuid = btn.dataset.uuid || key().slice(2);
				try { await post('/m/messages/' + id, { body: t.value, client_uuid: btn.dataset.uuid }, btn); delete btn.dataset.uuid; render(true); }
				catch (e) { toast(e.message, 'err'); }
			};
		}
		// Actualización mientras la conversación está abierta (sin servidor de tiempo real: consulta cada 10 s)
		var here = location.hash;
		clearInterval(S.msgPoll);
		S.msgPoll = setInterval(function () { if (location.hash !== here || document.hidden) { if (location.hash !== here) { clearInterval(S.msgPoll); } return; } var t = document.getElementById('m-body'); if (!t || !t.value) { render(true); } }, 10000);
	}

	/* ---------------------------------------------------------------- Turnos abiertos */
	VIEWS.openshifts = async function (r, signal) {
		setTitle('Turnos abiertos'); crumbs([['Menú', '#/menu'], ['Turnos abiertos', location.hash]]); skel(4);
		var d = await get('/m/open-shifts', {}, { signal: signal, fresh: true });
		var lbl = function (x) { return h(dueLabel(x.request_date)) + ' ' + hhmm(x.start_time) + '–' + hhmm(x.end_time); };
		var st = { pending: ['Pendiente', 'warn'], approved: ['Asignado', 'ok'], rejected: ['No asignado', 'danger'], withdrawn: ['Retirado', 'muted'] };
		var html = '';
		if (d.linked) {
			html += section('Disponibles en su sucursal');
			html += d.open.length ? '<div class="m-list">' + d.open.map(function (x) {
				var my = x.my_status && st[x.my_status];
				var act = my ? '<span class="m-tag ' + my[1] + '">' + my[0] + '</span>' : (x.blocking.length ? '<span class="m-tag danger" title="' + h(x.blocking.join('; ')) + '">No disponible</span>' : '<button type="button" class="m-btn sm primary" data-claim="' + x.id + '">' + (x.auto_approve ? 'Tomar' : 'Solicitar') + '</button>');
				return '<div class="m-row"><span class="ic info"><i class="fa-solid fa-calendar-plus"></i></span><span class="tx"><div class="t1">' + lbl(x) + '</div><div class="t2">' + h([x.role, x.department, x.reason].filter(Boolean).join(' · ')) + ' · ' + x.remaining + ' cupo(s)' + (x.blocking.length ? '<br><span class="t-danger">' + h(x.blocking.join('; ')) + '</span>' : (x.warnings.length ? '<br><span class="t-warn">' + h(x.warnings.join('; ')) + '</span>' : '')) + '</div></span><span class="rt">' + act + '</span></div>';
			}).join('') + '</div>' : '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>No hay turnos abiertos ahora</div>';
			if (d.mine.length) {
				html += section('Mis solicitudes') + list(d.mine.map(function (c) {
					var s = st[c.status];
					return row({ icon: 'fa-hand', tone: s[1], t1: lbl(c), t2: h(c.decision_notes || c.reason || ''), v: '<span class="m-tag ' + s[1] + '">' + s[0] + '</span>', s: c.status === 'pending' ? '<button type="button" class="m-btn sm ghost" data-withdraw="' + c.id + '">Retirar</button>' : '' });
				}));
			}
		} else if (!d.can_manage) {
			html += '<div class="m-card m-empty"><i class="fa-solid fa-id-badge"></i>Su usuario no está vinculado a una ficha de empleado. Pida a su gerente que lo vincule en Horarios.</div>';
		}
		if (d.can_manage) {
			html += section('Gestión (gerente)', '<button type="button" class="m-btn sm accent" id="m-os-new"><i class="fa-solid fa-plus"></i> Publicar turno</button>');
			html += list(d.manager.requests.map(function (q) {
				var claims = q.claims.map(function (c) { return '<div style="display:flex;gap:6px;align-items:center;margin-top:6px"><span style="flex:1">' + h(c.employee) + (c.position ? ' <small class="m-sub">' + h(c.position) + '</small>' : '') + '</span><button type="button" class="m-btn sm primary" data-decide="' + c.id + '" data-ok="1">Aprobar</button><button type="button" class="m-btn sm ghost" data-decide="' + c.id + '" data-ok="0">Rechazar</button></div>'; }).join('');
				return '<div class="m-row"><span class="ic ' + (q.claims.length ? 'warn' : '') + '"><i class="fa-solid fa-calendar-plus"></i></span><span class="tx"><div class="t1">' + lbl(q) + ' · ' + h(q.branch || '') + '</div><div class="t2">' + q.filled + '/' + q.staff_needed + ' cubiertos' + (q.auto_approve ? ' · aprobación automática' : '') + (q.role ? ' · ' + h(q.role) : '') + '</div>' + claims + '</span></div>';
			}), 'Sin turnos abiertos publicados');
		}
		$main.innerHTML = html;
		$main.querySelectorAll('[data-claim]').forEach(function (b) { b.onclick = async function () { try { var res = await post('/m/open-shifts/' + b.dataset.claim + '/claim', {}, b); if (res) { buzz(true); toast(res.status === 'approved' ? 'Turno asignado: confírmelo en Mi horario' : 'Solicitud enviada a su gerente', 'ok'); render(true); } } catch (e) { toast(e.message, 'err'); } }; });
		$main.querySelectorAll('[data-withdraw]').forEach(function (b) { b.onclick = async function () { try { await post('/m/open-shift-claims/' + b.dataset.withdraw + '/withdraw', {}, b); render(true); } catch (e) { toast(e.message, 'err'); } }; });
		$main.querySelectorAll('[data-decide]').forEach(function (b) {
			b.onclick = async function () {
				var ok = b.dataset.ok === '1', notes = null;
				if (!ok) { notes = await confirmSheet('Rechazar solicitud', 'El empleado verá el motivo.', 'Rechazar', 'danger', { label: 'Motivo', required: 'Indique el motivo' }); if (!notes) { return; } }
				try { var res = await post('/m/open-shift-claims/' + b.dataset.decide + '/decide', { approve: ok ? 1 : 0, notes: notes }, b); toast(ok ? 'Turno asignado y publicado' + (res.request_closed ? ' · cupos completos' : '') : 'Solicitud rechazada', 'ok'); render(true); } catch (e) { toast(e.message, 'err'); }
			};
		});
		var nb = document.getElementById('m-os-new');
		if (nb) { nb.onclick = openShiftSheet; }
	};
	function openShiftSheet() {
		var br = (S.me.branches || []).map(function (b) { return '<option value="' + b.id + '"' + (String(b.id) === String(S.branch) ? ' selected' : '') + '>' + h(b.name) + '</option>'; }).join('');
		sheet('Publicar turno abierto', '<div class="m-field"><label for="os-b">Sucursal</label><select id="os-b" class="m-input">' + br + '</select></div>'
			+ '<div class="m-field"><label for="os-d">Fecha</label><input type="date" id="os-d" class="m-input" min="' + todayStr(0) + '" value="' + todayStr(1) + '"></div>'
			+ '<div class="m-grid" style="grid-template-columns:1fr 1fr"><div class="m-field"><label for="os-s">Inicio</label><input type="time" id="os-s" class="m-input" value="08:00"></div><div class="m-field"><label for="os-e">Fin</label><input type="time" id="os-e" class="m-input" value="14:00"></div></div>'
			+ '<div class="m-grid" style="grid-template-columns:1fr 1fr"><div class="m-field"><label for="os-n">Personas</label><input type="number" id="os-n" class="m-input" min="1" max="20" value="1"></div><div class="m-field"><label for="os-r">Puesto (opcional)</label><input id="os-r" class="m-input" maxlength="60"></div></div>'
			+ '<div class="m-field"><label for="os-why">Motivo</label><input id="os-why" class="m-input" maxlength="200" placeholder="Ej. refuerzo por recepción"></div>'
			+ '<label class="m-check"><input type="checkbox" id="os-auto"> Asignar automáticamente al primero que cumpla las reglas</label>'
			+ '<button type="button" class="m-btn primary" id="os-go"><i class="fa-solid fa-paper-plane"></i> Publicar y avisar</button>', function (body, close) {
			var b = body.querySelector('#os-go');
			b.onclick = async function () {
				var v = function (id) { return body.querySelector(id).value; };
				try {
					var res = await post('/m/open-shifts', { branch_id: parseInt(v('#os-b'), 10), request_date: v('#os-d'), start_time: v('#os-s'), end_time: v('#os-e'), staff_needed: parseInt(v('#os-n'), 10), role: v('#os-r'), reason: v('#os-why'), auto_approve: body.querySelector('#os-auto').checked ? 1 : 0 }, b);
					close(); toast('Turno publicado · avisados ' + res.notified_users + ' empleados', 'ok'); render(true);
				} catch (e) { toast(e.message, 'err'); }
			};
		});
	}
	async function swapSheet(s, coworkers) {
		if (!coworkers.length) { toast('No hay compañeros activos en su sucursal para proponer el cambio', 'err'); return; }
		var opts = '<option value="">— Seleccione —</option>' + coworkers.map(function (c) { return '<option value="' + c.id + '">' + h(c.name) + (c.position ? ' · ' + h(c.position) : '') + '</option>'; }).join('');
		var to = await confirmSheet('Pedir cambio de turno', 'Turno del ' + dueLabel(s.shift_date) + ' ' + hhmm(s.start_time) + '–' + hhmm(s.end_time) + '. Su gerente aprueba el cambio; hasta entonces el turno sigue siendo suyo.', 'Enviar solicitud', 'primary', { type: 'select', label: 'Compañero que tomaría el turno', options: opts, required: 'Elija a un compañero' });
		if (!to) { return; }
		try { await post('/schedule/swaps', { shift_id: s.id, to_employee_id: n(to) }); toast('Solicitud enviada a su gerente', 'ok'); go('profile/requests'); } catch (e) { toast(e.message, 'err'); }
	}
	PROFILE.requests = async function (r, signal) {
		setTitle('Solicitudes'); crumbs([['Perfil', '#/profile'], ['Solicitudes', location.hash]]); skel(4);
		var d = await mySchedule(signal);
		if (needEmployee(d)) { return; }
		var st = { requested: ['Pendiente', 'warn'], approved: ['Aprobada', 'ok'], rejected: ['Rechazada', 'danger'] };
		var html = '<button type="button" class="m-btn primary" id="m-newabs"><i class="fa-solid fa-plus"></i> Solicitar ausencia</button><p class="m-sub">Para cambiar un turno, ábralo en <a href="#/profile/schedule" style="color:var(--primary-2)">Mi horario</a> y toque «Cambiar».</p>';
		html += section('Ausencias') + list((d.data.absences || []).map(function (a) { var s = st[a.status] || [a.status, '']; return row({ icon: 'fa-plane-departure', t1: h(d.data.absence_types[a.absence_type] || a.absence_type) + ' · ' + day(a.start_date) + (a.end_date !== a.start_date ? ' → ' + day(a.end_date) : ''), t2: h(a.notes || ''), v: '<span class="m-tag ' + s[1] + '">' + s[0] + '</span>' }); }), 'Sin solicitudes de ausencia');
		html += section('Cambios de turno') + list((d.data.swaps || []).map(function (w) { var s = st[w.status] || [w.status, '']; return row({ icon: 'fa-right-left', t1: h(w.from_name) + ' → ' + h(w.to_name), t2: day(w.shift_date) + ' ' + hhmm(w.start_time) + '–' + hhmm(w.end_time), v: '<span class="m-tag ' + s[1] + '">' + s[0] + '</span>' }); }), 'Sin cambios de turno');
		$main.innerHTML = html;
		document.getElementById('m-newabs').onclick = function () { absenceSheet(d.data.absence_types); };
	};
	function absenceSheet(types) {
		sheet('Solicitar ausencia', '<form id="m-abf" novalidate><div class="m-field"><label for="m-at">Tipo</label><select id="m-at" class="m-input">' + Object.keys(types).map(function (k) { return '<option value="' + k + '">' + h(types[k]) + '</option>'; }).join('') + '</select></div>'
			+ '<div class="m-grid"><div class="m-field"><label for="m-af">Desde</label><input type="date" id="m-af" class="m-input" value="' + todayStr(1) + '"></div><div class="m-field"><label for="m-ato">Hasta</label><input type="date" id="m-ato" class="m-input" value="' + todayStr(1) + '"></div></div>'
			+ '<div class="m-field"><label for="m-an">Comentario (opcional)</label><textarea id="m-an" class="m-input" rows="2"></textarea></div><div class="m-err" id="m-aerr" hidden></div><button type="submit" class="m-btn primary" id="m-aok">Enviar solicitud</button></form>', function (body, close) {
			var f = body.querySelector('#m-abf');
			f.onsubmit = async function (e) {
				e.preventDefault();
				var b = { absence_type: f.querySelector('#m-at').value, start_date: f.querySelector('#m-af').value, end_date: f.querySelector('#m-ato').value, notes: f.querySelector('#m-an').value.trim() }, er = body.querySelector('#m-aerr');
				if (!b.start_date || !b.end_date || b.end_date < b.start_date) { er.textContent = 'Revise las fechas: «hasta» no puede ser anterior a «desde».'; er.hidden = false; return; }
				try { var res = await post('/schedule/absences', b, body.querySelector('#m-aok')); if (res) { close(); toast('Solicitud enviada', 'ok'); render(true); } } catch (x) { er.textContent = x.message; er.hidden = false; }
			};
		});
	}
	PROFILE.hours = async function (r, signal) {
		setTitle('Mis horas'); crumbs([['Perfil', '#/profile'], ['Mis horas', location.hash]]); skel(3);
		if (needEmployee(await mySchedule(signal))) { return; }
		var from = new Date(); from.setDate(from.getDate() - 13);
		var fs = from.getFullYear() + '-' + String(from.getMonth() + 1).padStart(2, '0') + '-' + String(from.getDate()).padStart(2, '0');
		var att = await get('/schedule/attendance', { mine: 1, from: fs, to: todayStr() }, { signal: signal, fresh: true });
		var total = att.reduce(function (a, x) { return a + n(x.hours); }, 0);
		var html = '<div class="m-teamsum m-card"><div><b class="m-num">' + att.length + '</b><span>fichajes (14 días)</span></div><div><b class="m-num">' + qty(total) + '</b><span>h registradas</span></div><div class="' + (att.some(function (x) { return x.pending_adjustment_id; }) ? 'warn' : '') + '"><b class="m-num">' + att.filter(function (x) { return x.pending_adjustment_id; }).length + '</b><span>en revisión</span></div></div>';
		html += '<button type="button" class="m-btn" id="m-myom"><i class="fa-solid fa-plus"></i> Olvidé marcar un turno</button><p class="m-sub">Si una hora está mal, tóquela y solicite la corrección. Su gerente la revisa; el registro original no se borra.</p>';
		html += list(att.map(function (a, i) { return '<button type="button" class="m-row" data-ma="' + i + '" style="width:100%;border:0;background:none;text-align:left"><span class="tx"><div class="t1">' + day(a.clock_in) + ' · ' + h(String(a.clock_in).slice(11, 16)) + ' → ' + (a.clock_out ? h(String(a.clock_out).slice(11, 16)) : 'sin salida') + '</div><div class="t2">' + (a.hours != null ? qty(a.hours) + ' h' : 'en curso') + (a.shift_date ? ' · turno ' + hhmm(a.start_time) + '–' + hhmm(a.end_time) : '') + '</div></span>' + (a.pending_adjustment_id ? '<span class="m-tag warn">en revisión</span>' : (n(a.corrections) ? '<span class="m-tag muted">corregido</span>' : '')) + '</button>'; }), 'Sin fichajes en los últimos 14 días');
		$main.innerHTML = html;
		$main.querySelectorAll('[data-ma]').forEach(function (b) { b.onclick = function () { var a = att[n(b.dataset.ma)]; if (a.pending_adjustment_id) { toast('Ya hay una corrección en revisión', 'warn'); return; } adjustSheet(a, null, todayStr(), true); }; });
		document.getElementById('m-myom').onclick = function () { adjustSheet(null, null, todayStr(), true); };
	};
	PROFILE.availability = async function (r, signal) {
		setTitle('Mi disponibilidad'); crumbs([['Perfil', '#/profile'], ['Disponibilidad', location.hash]]); skel(4);
		var d = await mySchedule(signal);
		if (needEmployee(d)) { return; }
		var av = {}; (d.data.availability || []).forEach(function (a) { av[a.weekday] = a; });
		var html = '<p class="m-sub">Indique en qué horario puede trabajar cada día. Deje un día apagado si no está disponible. Su gerente lo ve al armar los turnos.</p><form id="m-avf"><div class="m-card">';
		for (var i = 1; i <= 7; i++) {
			var a = av[i];
			html += '<div class="m-avrow"><label class="m-switch"><input type="checkbox" data-wd="' + i + '"' + (a ? ' checked' : '') + '><span></span>' + weekdayName(i) + '</label><div class="tt"><input type="time" class="m-input" data-s="' + i + '" value="' + h(a ? hhmm(a.start_time) : '08:00') + '" aria-label="Desde ' + weekdayName(i) + '"><span>–</span><input type="time" class="m-input" data-e="' + i + '" value="' + h(a ? hhmm(a.end_time) : '17:00') + '" aria-label="Hasta ' + weekdayName(i) + '"></div></div>';
		}
		html += '</div><div class="m-err" id="m-averr" hidden></div><button type="submit" class="m-btn primary" id="m-avok">Guardar disponibilidad</button></form>';
		$main.innerHTML = html;
		var f = document.getElementById('m-avf');
		var sync = function () { f.querySelectorAll('[data-wd]').forEach(function (c) { var w = c.dataset.wd; f.querySelector('[data-s="' + w + '"]').disabled = f.querySelector('[data-e="' + w + '"]').disabled = !c.checked; }); };
		f.onchange = sync; sync();
		f.onsubmit = async function (e) {
			e.preventDefault();
			var days = [];
			f.querySelectorAll('[data-wd]:checked').forEach(function (c) { var w = c.dataset.wd; days.push({ weekday: n(w), start_time: f.querySelector('[data-s="' + w + '"]').value, end_time: f.querySelector('[data-e="' + w + '"]').value }); });
			try { var res = await post('/schedule/employees/' + d.data.employee.id + '/availability', { days: days }, document.getElementById('m-avok')); if (res) { toast('Disponibilidad guardada', 'ok'); } }
			catch (x) { var er = document.getElementById('m-averr'); er.textContent = x.message; er.hidden = false; }
		};
	};
	PROFILE.training = async function (r, signal) {
		setTitle('Capacitación'); crumbs([['Perfil', '#/profile'], ['Capacitación', location.hash]]); skel(3);
		var html = '';
		if (S.me.employee) {
			var d = await mySchedule(signal);
			var certs = (d.data && d.data.certifications) || [];
			html += section('Mis certificaciones') + list(certs.map(function (c) {
				var exp = c.expires_date, st = !exp ? ['Vigente', 'ok'] : (exp < todayStr() ? ['Vencida', 'danger'] : (exp <= todayStr(30) ? ['Vence pronto', 'warn'] : ['Vigente', 'ok']));
				return row({ icon: 'fa-certificate', tone: st[1], t1: h(c.name), t2: exp ? 'Vence ' + day(exp) : 'Sin vencimiento', v: '<span class="m-tag ' + st[1] + '">' + st[0] + '</span>' });
			}), 'Sin certificaciones registradas');
		}
		html += section('Guías rápidas') + '<div class="m-menu">' + HELP.filter(function (x) { return !x.perm || can(x.perm); }).map(function (x, i) { return '<button type="button" data-help="' + i + '"><span class="ic"><i class="fa-solid ' + x.icon + '"></i></span><span>' + h(x.q) + '</span><i class="fa-solid fa-chevron-right m-muted"></i></button>'; }).join('') + '</div>'
			+ '<p class="m-sub">Los cursos con evaluación aún no están disponibles en DAILY; las certificaciones las registra su gerente en Horarios.</p>';
		$main.innerHTML = html;
		var avail = HELP.filter(function (x) { return !x.perm || can(x.perm); });
		$main.querySelectorAll('[data-help]').forEach(function (b) { b.onclick = function () { var x = avail[n(b.dataset.help)]; sheet(x.q, helpHtml(x)); }; });
	};
	PROFILE.settings = async function () {
		setTitle('Preferencias'); crumbs([['Perfil', '#/profile'], ['Preferencias', location.hash]]);
		var nd = S.notif || await get('/m/notifications', { branch_id: S.branch }, { fresh: true });
		var perm = 'Notification' in window ? Notification.permission : 'unsupported';
		var currentTheme = localStorage.getItem('daily_theme') || 'green';
		var html = section('Tema de Identidad Visual') + '<div class="m-card">'
			+ '<div class="m-row" style="flex-direction:column;align-items:flex-start;gap:8px">'
			+ '<select id="m-theme-select" class="m-input" style="font-weight:600">'
			+ '<option value="green"' + (currentTheme === 'green' ? ' selected' : '') + '>DAILY Verde — predeterminado</option>'
			+ '<option value="red"' + (currentTheme === 'red' ? ' selected' : '') + '>DAILY Rojo</option>'
			+ '<option value="yellow"' + (currentTheme === 'yellow' ? ' selected' : '') + '>DAILY Amarillo</option>'
			+ '</select></div></div>';
		html += section('Avisos en la app') + '<div class="m-card">' + Object.keys(nd.types).map(function (k) { return '<label class="m-switch row"><span>' + h(nd.types[k]) + '</span><input type="checkbox" data-np="' + k + '"' + (nd.prefs[k] ? ' checked' : '') + '><span></span></label>'; }).join('') + '</div>';
		html += section('Avisos del sistema') + '<div class="m-card">' + (perm === 'unsupported' ? '<p class="m-sub">Este navegador no admite notificaciones.</p>' : '<label class="m-switch row"><span>Mostrar avisos aunque la app esté en segundo plano' + (perm === 'denied' ? '<small class="m-sub" style="display:block">Bloqueadas en el navegador: actívelas en la configuración del sitio.</small>' : '') + '</span><input type="checkbox" id="m-push"' + (store('push') && perm === 'granted' ? ' checked' : '') + (perm === 'denied' ? ' disabled' : '') + '><span></span></label>') + '</div>';
		html += section('Escáner') + '<div class="m-card"><label class="m-switch row"><span>Vibrar al escanear</span><input type="checkbox" id="m-vibe"' + (store('vibe') !== false ? ' checked' : '') + '><span></span></label><label class="m-switch row"><span>Sonido al escanear</span><input type="checkbox" id="m-sound"' + (store('sound') === true ? ' checked' : '') + '><span></span></label></div>'
			+ '<p class="m-sub">El tema claro u oscuro sigue la configuración del teléfono.</p>';
		$main.innerHTML = html;
		$main.querySelectorAll('[data-np]').forEach(function (c) {
			c.onchange = async function () {
				var p = {}; $main.querySelectorAll('[data-np]').forEach(function (x) { p[x.dataset.np] = x.checked; });
				try { await post('/m/prefs', { notifications: p }); S.notif = null; toast('Preferencias guardadas', 'ok'); pollNotif(); } catch (e) { c.checked = !c.checked; toast(e.message, 'err'); }
			};
		});
		var pu = document.getElementById('m-push');
		if (pu) { pu.onchange = async function () { if (pu.checked && Notification.permission !== 'granted') { var g = await Notification.requestPermission(); if (g !== 'granted') { pu.checked = false; toast('Permiso de notificaciones no concedido', 'err'); return; } } store('push', pu.checked); }; }
		document.getElementById('m-vibe').onchange = function (e) { store('vibe', e.target.checked); if (e.target.checked) { buzz(true); } };
		document.getElementById('m-sound').onchange = function (e) { store('sound', e.target.checked); if (e.target.checked) { buzz(true); } };
		var ts = document.getElementById('m-theme-select');
		if (ts) {
			ts.onchange = function() {
				var val = ts.value;
				localStorage.setItem('daily_theme', val);
				if (val === 'red' || val === 'yellow') {
					document.documentElement.setAttribute('data-theme', val);
				} else {
					document.documentElement.removeAttribute('data-theme');
				}
				toast('Tema ' + (val === 'red' ? 'Rojo' : (val === 'yellow' ? 'Amarillo' : 'Verde')) + ' aplicado', 'ok');
			};
		}
	};
	PROFILE.sync = async function () {
		setTitle('Sincronización'); crumbs([['Perfil', '#/profile'], ['Sincronización', location.hash]]);
		var q = queue();
		var html = '<p class="m-sub">Operaciones guardadas sin conexión. Se envían solas al reconectar; cada una lleva una clave única, así que nunca se registran dos veces.</p>'
			+ (q.length ? '<div class="m-list">' + q.map(function (x) { return '<div class="m-row"><span class="ic ' + (x.error ? 'danger' : 'warn') + '"><i class="fa-solid ' + (x.error ? 'fa-circle-exclamation' : 'fa-clock') + '"></i></span><span class="tx"><div class="t1">' + h(x.label) + '</div><div class="t2" style="white-space:normal">' + (x.error ? h(x.error) : 'En espera') + ' · ' + dt(x.at) + '</div></span><button type="button" class="m-btn sm ghost" data-del="' + h(x.key) + '" aria-label="Descartar"><i class="fa-solid fa-trash"></i></button></div>'; }).join('') + '</div>' : '<div class="m-card m-empty"><i class="fa-solid fa-circle-check"></i>Todo sincronizado</div>')
			+ (q.length ? '<button type="button" class="m-btn primary" id="m-retry-q"><i class="fa-solid fa-rotate"></i> Reintentar ahora</button>' : '');
		$main.innerHTML = html;
		$main.querySelectorAll('[data-del]').forEach(function (b) { b.onclick = async function () { if (await confirmSheet('Descartar operación', 'No se enviará al servidor. Si la necesita, regístrela de nuevo.', 'Descartar', 'danger')) { setQueue(queue().filter(function (x) { return x.key !== b.dataset.del; })); render(true); } }; });
		var rq = document.getElementById('m-retry-q');
		if (rq) { rq.onclick = async function () { setQueue(queue().map(function (x) { delete x.error; return x; })); await flush(); render(true); }; }
	};

	/* ---------------- Notificaciones */
	async function pollNotif() {
		try {
			var d = await get('/m/notifications', { branch_id: S.branch }, { fresh: true });
			S.notif = d;
			var notifBadge = document.getElementById('m-notif-badge');
			if (notifBadge) {
				var nVal = d.unread ? (d.unread > 99 ? '99+' : d.unread) : '1';
				notifBadge.textContent = nVal;
				notifBadge.hidden = false;
				notifBadge.setAttribute('aria-label', nVal + ' notificaciones');
			}
			var alertsBadge = document.getElementById('m-alerts-badge');
			if (alertsBadge) {
				var aVal = d.unread ? (d.unread > 99 ? '99+' : d.unread) : '3';
				alertsBadge.textContent = aVal;
				alertsBadge.hidden = false;
				alertsBadge.setAttribute('aria-label', aVal + ' alertas');
			}
			document.getElementById('m-notif-btn').setAttribute('aria-label', 'Notificaciones' + (d.unread ? ' (' + d.unread + ' sin leer)' : ''));
			systemNotify(d.items.filter(function (i) { return i.unread; }));
		} catch (e) { }
	}
	// Avisos del sistema: solo nuevos (clave estable ya avisada no se repite) y solo si el usuario lo activó
	function systemNotify(items) {
		if (!store('push') || !('Notification' in window) || Notification.permission !== 'granted' || !items.length) { return; }
		var sent = store('notified:' + C.userId) || [];
		var fresh = items.filter(function (i) { return sent.indexOf(i.key) < 0; }).slice(0, 3);
		if (!fresh.length) { return; }
		navigator.serviceWorker && navigator.serviceWorker.ready.then(function (reg) {
			fresh.forEach(function (i) { reg.showNotification(i.title, { body: i.detail || '', tag: i.key, icon: C.base + '/img/icon-1024.png', data: { url: C.base + '/m' + i.href } }); });
		}).catch(function () { });
		store('notified:' + C.userId, fresh.map(function (i) { return i.key; }).concat(sent).slice(0, 300));
	}
	VIEWS.notifications = async function (r, signal) {
		setTitle('Alertas y avisos'); crumbs(null); skel(4);
		var d = await get('/m/notifications', { branch_id: S.branch }, { signal: signal, fresh: true });
		var today = d.items.filter(function (i) { return String(i.at).slice(0, 10) === todayStr(); }), older = d.items.filter(function (i) { return String(i.at).slice(0, 10) !== todayStr(); });
		var card = function (i) { return '<a class="m-notif' + (i.unread ? ' unread' : '') + '" href="' + h(i.href) + '"><span class="ic ' + i.tone + '"><i class="fa-solid ' + i.icon + '"></i></span><span class="tx"><b>' + h(i.title) + '</b>' + (i.detail ? '<span>' + h(i.detail) + '</span>' : '') + '<small>' + dt(i.at) + '</small></span></a>'; };
		var html = d.items.length ? (today.length ? section('Hoy') + '<div class="m-list">' + today.map(card).join('') + '</div>' : '') + (older.length ? section('Últimos 7 días') + '<div class="m-list">' + older.map(card).join('') + '</div>' : '')
			: '<div class="m-card m-empty"><i class="fa-regular fa-bell"></i>Sin notificaciones recientes</div>';
		html += '<a class="m-btn ghost" href="#/profile/settings" style="margin-top:12px"><i class="fa-solid fa-sliders"></i> Elegir qué avisos recibir</a>';
		$main.innerHTML = html;
		if (d.unread) { post('/m/notifications/seen', {}).then(function () { document.getElementById('m-notif-badge').hidden = true; document.getElementById('m-alerts-badge').hidden = true; }).catch(function () { }); }
	};

	/* ---------------- Asistente */
	var HELP = [
		{ q: '¿Cómo registro una merma?', icon: 'fa-trash-can', perm: P.wasteReport, steps: ['Toque «Escanear» y lea el código del producto (o use «Registrar sin escanear»).', 'Elija «Registrar merma».', 'Indique cantidad, motivo y ubicación; si el motivo lo pide, tome una foto.', 'Toque «Registrar merma». Si requiere aprobación, el stock se descuenta cuando su supervisor la apruebe.'], k: 'merma registrar perdida dañado vencido roto' },
		{ q: '¿Cómo cuento un producto?', icon: 'fa-clipboard-list', perm: P.count, steps: ['Escanee el producto y elija «Contar».', 'Cuente lo que ve físicamente: el sistema no le muestra la cantidad esperada (conteo ciego).', 'Si hay diferencia, otra persona aprueba el ajuste.'], k: 'contar conteo inventario ciclico diferencia' },
		{ q: '¿Cómo marco mi entrada y salida?', icon: 'fa-right-to-bracket', steps: ['En «Hoy» verá su turno.', 'Toque «Marcar entrada» al llegar y «Marcar salida» al terminar.', 'Si su usuario no está vinculado a un empleado, pida a su gerente que lo vincule.'], k: 'entrada salida marcar fichar asistencia turno' },
		{ q: '¿Cómo pido un día libre o vacaciones?', icon: 'fa-plane-departure', steps: ['Vaya a Perfil → Ausencias y cambios de turno.', 'Toque «Solicitar ausencia», elija tipo y fechas.', 'Su gerente la aprueba o rechaza; recibirá un aviso.'], k: 'ausencia vacaciones permiso libre incapacidad' },
		{ q: '¿Cómo cambio un turno con un compañero?', icon: 'fa-right-left', steps: ['Perfil → Mi horario.', 'En el turno, toque «Cambiar» y elija al compañero.', 'El cambio vale cuando lo aprueba su gerente.'], k: 'cambio intercambio turno compañero' },
		{ q: '¿Cómo completo una tarea?', icon: 'fa-list-check', steps: ['Abra «Tareas»: lo vencido aparece primero.', 'Toque la tarea, haga el trabajo y escriba una nota si hace falta.', 'Toque «Marcar como hecha». Si nació de una alerta, la alerta se cierra con su nota.'], k: 'tarea completar hecha pendiente' },
		{ q: '¿Cómo muevo o repongo producto?', icon: 'fa-right-left', perm: P.move, steps: ['Escanee el producto y elija «Mover / reponer».', 'Indique desde dónde y hacia dónde, y la cantidad.', 'Toque «Mover».'], k: 'mover reponer reposicion bodega gondola traslado' },
		{ q: '¿Qué pasa si me quedo sin conexión?', icon: 'fa-wifi', steps: ['Las mermas, movimientos y tareas sin foto se guardan en el teléfono y se envían solos al reconectar.', 'Nunca se registran dos veces: cada operación lleva una clave única.', 'Si alguna falla al enviarse, la verá en Perfil → Pendientes de sincronizar.'], k: 'conexion offline internet sin red sincronizar' },
		{ q: '¿Cómo apruebo mermas y ajustes?', icon: 'fa-clipboard-check', perm: ['WASTE_APPROVE', 'INVENTORY_ADJUST_APPROVE'], steps: ['Tareas → «Por decidir» → «Revisar uno por uno».', 'Revise evidencia, costo y motivo, y apruebe o rechace.', 'No puede aprobar lo que usted mismo registró (segregación de funciones).'], k: 'aprobar rechazar ajuste merma decidir' }
	];
	function helpHtml(x) { return '<ol class="m-steps">' + x.steps.map(function (s) { return '<li>' + h(s) + '</li>'; }).join('') + '</ol><p class="m-sub"><i class="fa-solid fa-book-open"></i> Guía de uso de DAILY.</p>'; }
	function answerHtml(a) {
		var SEC = { observed: ['Datos observados', 'fa-database', 'info'], estimate: ['Estimación', 'fa-calculator', 'warn'], recommendation: ['Recomendación', 'fa-lightbulb', 'ok'] };
		return '<b>' + h(a.title || '') + '</b>' + (a.sections || []).map(function (s) {
			var m0 = SEC[s.type] || [s.type, 'fa-circle', ''];
			var items = (s.items || []).map(function (it) { var tx = typeof it === 'string' ? it : (it.text || ''); return '<li>' + h(tx) + (it.href ? ' <a href="' + h(C.base + it.href) + '">Abrir</a>' : '') + '</li>'; }).join('');
			var tbl = s.table && s.table.rows && s.table.rows.length ? '<div class="m-tbl">' + s.table.rows.slice(0, 6).map(function (rw) { return '<div>' + rw.map(function (c, ci) { return '<span' + (ci ? ' class="m-num"' : '') + '>' + h(c) + '</span>'; }).join('') + '</div>'; }).join('') + '</div>' : '';
			return items || tbl ? '<div class="m-ans ' + m0[2] + '"><div class="k"><i class="fa-solid ' + m0[1] + '"></i> ' + m0[0] + '</div>' + (items ? '<ul>' + items + '</ul>' : '') + tbl + '</div>' : '';
		}).join('') + (a.sources ? '<p class="m-sub" style="font-size:12px;margin:6px 0 0">Fuente: ' + h(a.sources.join(', ')) + '</p>' : '');
	}
	VIEWS.assistant = async function () {
		setTitle('Asistente DAIRY'); crumbs([['Operaciones', '#/ops'], ['Asistente', location.hash]]);
		var help = HELP.filter(function (x) { return !x.perm || can(x.perm); });
		var sugg = S.me.can_ai ? ['¿Por qué subió la merma?', 'Productos críticos', '¿Qué debo reponer?', 'Anomalías de esta semana'] : [];
		$main.innerHTML = '<div class="m-chat" id="m-chat"><div class="m-msg bot">Hola' + (firstName() ? ' ' + h(firstName()) : '') + '. ' + (S.me.can_ai ? 'Puedo explicarle cómo hacer una operación o responder con los datos reales de su tienda (separo lo observado de lo recomendado).' : 'Puedo explicarle paso a paso cómo hacer cada operación.') + '</div></div>'
			+ '<div class="m-chips" id="m-sugg">' + sugg.map(function (s) { return '<button type="button" class="m-chip" data-ask="' + h(s) + '"><i class="fa-solid fa-chart-simple"></i>' + h(s) + '</button>'; }).join('') + help.slice(0, 6).map(function (x) { return '<button type="button" class="m-chip" data-ask="' + h(x.q) + '"><i class="fa-solid fa-book-open"></i>' + h(x.q) + '</button>'; }).join('') + '</div>'
			+ '<form id="m-askf" class="m-askbar"><input class="m-input" id="m-askq" placeholder="Escriba su pregunta" maxlength="500" autocomplete="off" aria-label="Pregunta"><button type="submit" class="m-btn primary sm" aria-label="Enviar"><i class="fa-solid fa-paper-plane"></i></button></form>';
		var chat = document.getElementById('m-chat');
		function add(cls, html) { var d = document.createElement('div'); d.className = 'm-msg ' + cls; d.innerHTML = html; chat.appendChild(d); d.scrollIntoView({ block: 'end', behavior: 'smooth' }); return d; }
		async function ask(q) {
			q = q.trim(); if (!q) { return; }
			add('me', h(q));
			var norm = q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
			var hit = help.find(function (x) { return x.q === q; }) || help.find(function (x) { return x.k.split(' ').filter(function (w) { return norm.indexOf(w) >= 0; }).length >= 2; });
			if (hit && (!S.me.can_ai || /como|que pasa/.test(norm))) { add('bot', '<b>' + h(hit.q) + '</b>' + helpHtml(hit)); return; }
			if (!S.me.can_ai) { add('bot', hit ? '<b>' + h(hit.q) + '</b>' + helpHtml(hit) : 'No encontré una guía para eso. Pruebe con: ' + help.slice(0, 3).map(function (x) { return '«' + h(x.q) + '»'; }).join(', ') + '. Para consultas de datos, pida acceso a su gerente.'); return; }
			var wait = add('bot', '<span class="m-typing"><i></i><i></i><i></i></span>');
			try { var a = await post('/ai/ask', { question: q, branch_id: S.branch }); wait.innerHTML = answerHtml(a || {}); }
			catch (e) { wait.innerHTML = 'No pude responder: ' + h(e.message); }
		}
		document.getElementById('m-sugg').onclick = function (e) { var b = e.target.closest('[data-ask]'); if (b) { ask(b.dataset.ask); } };
		document.getElementById('m-askf').onsubmit = function (e) { e.preventDefault(); var i = document.getElementById('m-askq'); var v = i.value; i.value = ''; ask(v); };
	};

	/* ---------------- Recorrido inicial */
	function tutorial() {
		var steps = [
			['fa-sun', 'Bienvenido a DAIRY', '«Hoy» le dice qué hacer ahora: su turno, lo siguiente por hacer y sus prioridades.'],
			['fa-list-check', 'Tareas', 'Todo lo pendiente en un solo lugar. Lo vencido aparece primero y cada tarea lleva a su origen.'],
			['fa-barcode', 'Escanear', 'El botón central. Escanee un producto, lote o ubicación y verá solo lo que su perfil puede hacer.'],
			['fa-bell', 'Alertas', 'Avisos y alertas de su tienda, lo no leído primero. Cada aviso abre su proceso.'],
			['fa-bars', 'Menú', (role() === 'employee' ? 'Registros rápidos (merma, conteo, reposición) y el asistente' : 'Su tienda: ' + (role() === 'manager' ? 'resumen ejecutivo, ' : '') + 'control, inventario, mermas, equipo y herramientas avanzadas') + '; abajo, su cuenta: horario, ausencias, disponibilidad. Puede repetir este recorrido desde aquí.']
		];
		var i = 0;
		function draw(body, close) {
			var s = steps[i];
			body.innerHTML = '<div class="m-tut"><span class="ic"><i class="fa-solid ' + s[0] + '"></i></span><h3>' + h(s[1]) + '</h3><p>' + h(s[2]) + '</p><div class="dots">' + steps.map(function (x, k) { return '<i class="' + (k === i ? 'on' : '') + '"></i>'; }).join('') + '</div></div>'
				+ '<div class="m-grid" style="grid-template-columns:1fr 1fr"><button type="button" class="m-btn ghost" id="m-tskip">' + (i ? 'Atrás' : 'Saltar') + '</button><button type="button" class="m-btn primary" id="m-tnext">' + (i === steps.length - 1 ? 'Empezar' : 'Siguiente') + '</button></div>';
			// sheetClose (no close): incluye el guardado de "recorrido visto"
			body.querySelector('#m-tnext').onclick = function () { if (i < steps.length - 1) { i++; draw(body, close); } else if (sheetClose) { sheetClose(); } };
			body.querySelector('#m-tskip').onclick = function () { if (i) { i--; draw(body, close); } else if (sheetClose) { sheetClose(); } };
		}
		sheet('Recorrido', '', function (body, close) {
			draw(body, close);
			var oc = sheetClose; sheetClose = function () { oc(); if (!S.me.onboarded) { S.me.onboarded = true; post('/m/prefs', { onboarded: 1 }).catch(function () { }); } };
		});
	}

	/* ---------------- Cola sin conexión */
	function queue() { return store('queue:' + C.userId) || []; }
	function setQueue(q) { store('queue:' + C.userId, q); netBanner(); }
	function enqueue(path, body, btn, label) {
		var k = (btn && btn.dataset.key) || (body && body.idempotency_key) || key();
		if (btn) { delete btn.dataset.key; btn.classList.remove('busy'); }
		var q = queue();
		// Misma operación tocada dos veces sin conexión (otra clave, mismo contenido): se guarda una sola vez
		var sig = function (b) { var c = Object.assign({}, b || {}); delete c.idempotency_key; return JSON.stringify(c); };
		var same = q.find(function (x) { return x.key === k || (!x.error && x.path === path && sig(x.body) === sig(body)); });
		if (same) { return { queued: true, message: label + ': ya estaba guardado; se enviará una sola vez al reconectar' }; }
		q.push({ key: k, path: path, body: body || {}, label: label, at: new Date().toISOString() });
		setQueue(q);
		return { queued: true, message: label + ': guardado sin conexión; se enviará al reconectar' };
	}
	async function flush() {
		var q = queue();
		if (S.flushing || navigator.onLine === false || !q.some(function (x) { return !x.error; })) { return; }
		S.flushing = true;
		var sent = 0;
		try {
			for (var i = 0; i < q.length; i++) {
				var it = q[i];
				if (it.error) { continue; }
				try {
					var res = await fetch(API + it.path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Idempotency-Key': it.key }, body: JSON.stringify(it.body) });
					if (res.ok) { it.done = true; sent++; }
					else if (res.status === 409) { continue; }
					// Sesión vencida o falla temporal del servidor: queda en espera (misma clave) hasta reintentar
					else if (res.status === 401) { toast('Su sesión venció: inicie sesión para enviar las operaciones en espera', 'err'); break; }
					else if (res.status >= 500) { break; }
					else { it.error = (await errorOf(res)).message; }
				} catch (e) { break; }
			}
		} finally {
			setQueue(q.filter(function (x) { return !x.done; }));
			S.flushing = false;
		}
		if (sent) { S.cache = {}; toast(sent + (sent === 1 ? ' operación sincronizada' : ' operaciones sincronizadas'), 'ok'); }
		if (queue().some(function (x) { return x.error; })) { toast('Algunas operaciones no se pudieron enviar: revíselas en Perfil', 'err'); }
	}
	function netBanner() {
		var el = document.getElementById('m-offline'), q = queue(), err = q.filter(function (x) { return x.error; }).length, wait = q.length - err;
		if (navigator.onLine === false || S.stale) {
			el.hidden = false;
			el.innerHTML = '<i class="fa-solid fa-wifi"></i> Sin conexión: verá los últimos datos guardados en este teléfono. Mermas, movimientos y tareas sin foto se envían al reconectar.' + (wait ? ' <b>' + wait + ' en espera.</b>' : '');
		} else if (err) {
			el.hidden = false;
			el.innerHTML = '<a href="#/profile/sync"><i class="fa-solid fa-circle-exclamation"></i> ' + err + ' operaciones no se pudieron sincronizar · Revisar</a>';
		} else { el.hidden = true; }
	}
	document.getElementById('m-notif-btn').addEventListener('click', function () { go('notifications'); });
	var optsBtn = document.getElementById('m-opts-btn');
	if (optsBtn) {
		optsBtn.addEventListener('click', function () {
			var isEn = (window.DailyI18n && window.DailyI18n.lang === 'en');
			sheet(isEn ? 'Options & Profile' : 'Opciones y Perfil', 
				'<div class="m-list" style="margin-bottom:0">'
				+ '<a class="m-row" href="#/profile"><span class="ic"><i class="fa-solid fa-user"></i></span><span class="tx"><div class="t1">' + (isEn ? 'My Profile' : 'Mi Perfil') + '</div><div class="t2">' + h(S.me.role_label || 'Gerente') + '</div></span><i class="fa-solid fa-chevron-right chev"></i></a>'
				+ '<button type="button" class="m-row" id="m-sheet-branch-btn" style="width:100%;border:0;background:none;text-align:left"><span class="ic"><i class="fa-solid fa-store"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Branch / Store' : 'Sucursal') + '</div><div class="t2">' + h(branchLabel()) + '</div></span><i class="fa-solid fa-chevron-right chev"></i></button>'
				+ '<a class="m-row" href="/today"><span class="ic"><i class="fa-solid fa-desktop"></i></span><span class="tx"><div class="t1">' + (isEn ? 'Open Desktop Web' : 'Abrir versión Web') + '</div><div class="t2">DAILY Enterprise Platform</div></span><i class="fa-solid fa-arrow-up-right-from-square chev"></i></a>'
				+ '<a class="m-row" href="/logout"><span class="ic danger"><i class="fa-solid fa-right-from-bracket"></i></span><span class="tx"><div class="t1" style="color:var(--danger)">' + (isEn ? 'Log out' : 'Cerrar sesión') + '</div></span></a>'
				+ '</div>',
				function(body, close) {
					var bBtn = body.querySelector('#m-sheet-branch-btn');
					if (bBtn) {
						bBtn.onclick = function() {
							close();
							branchPicker();
						};
					}
				}
			);
		});
	}

	/* ---------------- arranque */
	(async function start() {
		net();
		try { S.me = await get('/m/me'); }
		catch (e) { fail(e, start); return; }
		S.period = store('period') || 'month';
		var b = store('branch');
		var ids = (S.me.branches || []).map(function (x) { return n(x.id); });
		S.branch = b && ids.indexOf(n(b)) >= 0 ? n(b) : (S.me.all_branches ? null : (ids[0] || null));
		document.getElementById('m-branch-name').textContent = branchLabel();
		document.querySelectorAll('#m-tabs a[data-perm]').forEach(function (a) { if (!can(P[a.dataset.perm])) { a.classList.add('m-off'); a.setAttribute('aria-disabled', 'true'); a.style.opacity = '.35'; a.style.pointerEvents = 'none'; } });
		if (C.swUrl && 'serviceWorker' in navigator && window.isSecureContext) { navigator.serviceWorker.register(C.swUrl, { scope: C.base + '/' }).catch(function () { }); }
		if (!location.hash) { history.replaceState(null, '', '#/home'); }
		netBanner();
		render();
		if (!S.me.onboarded) { setTimeout(tutorial, 400); }
		pollNotif(); flush();
		// Reloj en tiempo real de Miami para la barra de estado iOS
		function tickMiami() {
			var el = document.getElementById('m-status-time');
			if (el) {
				try {
					el.textContent = new Intl.DateTimeFormat('en-US', {
						timeZone: 'America/New_York',
						hour: 'numeric',
						minute: '2-digit',
						hour12: false
					}).format(new Date());
				} catch(e) {}
			}
		}
		tickMiami();
		setInterval(tickMiami, 10000);
		setInterval(function () { if (!document.hidden) { pollNotif(); } }, 120000);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) { pollNotif(); flush(); } });
	})();
})();
