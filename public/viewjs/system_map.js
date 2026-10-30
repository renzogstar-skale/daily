/* DAIRY — Mapa del Sistema (/system-map). SVG propio (sin dependencias): mapa mental, conexiones y auditoría.
 * Datos: GET /api/system-map (SystemMapService, generado desde el código + evidencia de tests/dairy/system_map_build.php). */
(function ()
{
	'use strict';

	var NS = 'http://www.w3.org/2000/svg';
	var ST = {
		verified: { label: 'Verificado', color: '#16a34a', bg: '#f0fdf4' },
		partial: { label: 'Parcial', color: '#ca8a04', bg: '#fefce8' },
		error: { label: 'Error confirmado', color: '#dc2626', bg: '#fef2f2' },
		unverified: { label: 'No verificado', color: '#94a3b8', bg: '#f8fafc' },
		proposed: { label: 'Propuesto', color: '#7c3aed', bg: '#f5f3ff' }
	};
	var SEV_ORDER = { P0: 0, P1: 1, P2: 2, P3: 3, Info: 4 };
	var ROOT = (window.SystemMapConfig || {}).root || '';

	var S = {
		map: null, view: 'mind', mode: 'general', q: '',
		cats: {}, sts: {}, expanded: { root: true }, selected: null,
		tx: { k: 0.8, x: 0, y: 0 }, needFit: true,
		mods: {}, catById: {}, issues: {}, auditSev: '', auditType: '', auditStatus: ''
	};

	var svg = document.getElementById('sm-svg');
	var $panel = $('#sm-panel');
	var vp = null; // <g> con la transformación

	// ---------- utilidades ----------
	function esc(s)
	{
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function el(tag, attrs, parent)
	{
		var n = document.createElementNS(NS, tag);
		for (var k in attrs) { if (attrs[k] != null) n.setAttribute(k, attrs[k]); }
		if (parent) parent.appendChild(n);
		return n;
	}
	function fit(text, px, fs)
	{
		var max = Math.floor(px / (fs * 0.56));
		text = String(text);
		return text.length > max ? text.substr(0, Math.max(1, max - 1)) + '…' : text;
	}
	function statusBadge(st) { return '<span class="sm-status sm-st-' + st + '">' + esc((ST[st] || ST.unverified).label) + '</span>'; }
	function sevBadge(s) { return '<span class="sm-sev sm-sev-' + esc(s) + '">' + esc(s) + '</span>'; }
	function codeLink(file, line)
	{
		if (!file) return '';
		var txt = file + (line ? ':' + line : '');
		if (!ROOT) return '<code>' + esc(txt) + '</code>';
		return '<a href="' + esc('vscode://file/' + ROOT + '/' + file + (line ? ':' + line : '')) + '" title="Abrir en VS Code"><code>' + esc(txt) + '</code></a>';
	}
	function routeLink(url)
	{
		if (!url || url.indexOf('{') >= 0) return url ? '<code>' + esc(url) + '</code>' : '—';
		return '<a href="' + esc(U(url)) + '"><code>' + esc(url) + '</code></a>';
	}
	function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	function catColor(id) { return (S.catById[id] || {}).color || '#64748b'; }

	// ---------- filtros / búsqueda ----------
	function moduleVisible(m)
	{
		return S.cats[m.category] !== false && S.sts[m.status] !== false;
	}
	function moduleHaystack(m)
	{
		if (m._hay) return m._hay;
		var parts = [m.name, m.route, m.description, m.category].concat(
			(m.tabs || []).map(function (t) { return t.label; }),
			(m.buttons || []).map(function (b) { return b.label; }),
			(m.endpoints || []).map(function (e) { return e.method + ' ' + e.path + ' ' + e.handler; }),
			m.services || [], m.tables || [], m.permissions || [], m.views || [],
			(m.extra_routes || []).map(function (r) { return r.path || r; })
		);
		m._hay = norm(parts.join(' | '));
		return m._hay;
	}
	function moduleHit(m) { return S.q !== '' && moduleHaystack(m).indexOf(S.q) >= 0; }

	// ---------- árbol del mapa mental ----------
	function leafNodes(prefix, list, toLabel, opts)
	{
		opts = opts || {};
		var limit = opts.limit || 14;
		var out = list.slice(0, limit).map(function (x, i)
		{
			return { id: prefix + ':' + i, type: 'leaf', label: toLabel(x), status: opts.status ? opts.status(x) : null, ref: x, kind: opts.kind };
		});
		if (list.length > limit) out.push({ id: prefix + ':more', type: 'leaf', label: '… ' + (list.length - limit) + ' más (ver panel)', more: true });
		return out;
	}
	function moduleGroups(m)
	{
		var g = [];
		var add = function (key, label, icon, list, toLabel, opts)
		{
			if (!list || !list.length) return;
			g.push({ id: m.id + '/' + key, type: 'group', label: label + ' (' + list.length + ')', icon: icon, module: m.id, children: leafNodes(m.id + '/' + key, list, toLabel, opts) });
		};
		var testStatus = function (t) { return t.ok === true ? 'verified' : t.ok === false ? 'error' : 'unverified'; };
		if (S.mode === 'general')
		{
			add('screens', 'Pantallas', 'fa-desktop', m.screens || [], function (s) { return s.path; }, { status: function (s) { return !s.exists || s.handler_ok === false ? 'error' : s.sweep ? (s.sweep.ok ? 'verified' : 'error') : 'unverified'; } });
			add('tabs', 'Pestañas', 'fa-folder-open', m.tabs || [], function (t) { return t.label; });
			add('buttons', 'Botones y acciones', 'fa-hand-pointer', m.buttons || [], function (b) { return b.label; });
			add('forms', 'Formularios', 'fa-pen-to-square', (m.forms || []).concat((m.modals || []).map(function (x) { return { id: 'Modal: ' + (x.title || x.id) }; })), function (f) { return f.id; });
		}
		else
		{
			add('endpoints', 'Endpoints', 'fa-plug', m.endpoints || [], function (e) { return e.method + ' ' + e.path; }, { status: function (e) { return e.tested_by && e.tested_by.length ? 'verified' : 'unverified'; } });
			add('services', 'Servicios', 'fa-gears', m.services || [], function (s) { return s; });
			add('tables', 'Tablas', 'fa-database', m.tables || [], function (t) { return t; });
			add('perms', 'Permisos', 'fa-key', m.permissions || [], function (p) { return p; });
		}
		add('tests', 'Pruebas', 'fa-vial', m.tests || [], function (t) { return t.file + (t.checks != null ? ' · ' + t.checks + ' checks' : ''); }, { status: testStatus });
		var conns = (m.connections || []);
		add('conns', 'Conexiones', 'fa-share-nodes', conns, function (c) { return (c.dir === 'out' ? '→ ' : '← ') + ((S.mods[c.with] || {}).name || c.with); }, { status: function (c) { return c.status; } });
		var iss = (m.issues || []).map(function (id) { return S.issues[id]; }).filter(Boolean);
		add('issues', 'Problemas', 'fa-triangle-exclamation', iss, function (i) { return i.severity + ' · ' + i.title; }, { status: function (i) { return i.severity === 'Info' ? 'unverified' : i.severity === 'P3' ? 'partial' : 'error'; } });
		var props = (S.map.proposals || []).filter(function (p) { return p.from === m.id; });
		add('props', 'Propuestas', 'fa-lightbulb', props, function (p) { return p.title; }, { status: function () { return 'proposed'; } });
		return g;
	}
	function architectureNode()
	{
		var a = S.map.architecture || {};
		var src = {
			backend: [a.controllers || [], function (c) { return c.name + (c.core ? ' (Grocy)' : ''); }],
			shared: [a.services || [], function (s) { return s.name + ' · ' + s.lines + ' líneas'; }],
			database: [a.tables || [], function (t) { return t.name + (t.type === 'view' ? ' (vista)' : ''); }],
			permissions: [a.permissions || [], function (p) { return p; }],
			tests: [a.tests || [], function (t) { return t.file + (t.checks != null ? ' · ' + t.checks : ''); }],
			apis: [(S.map.routes || []).filter(function (r) { return r.group === 'api'; }), function (r) { return r.method + ' ' + r.path; }]
		};
		return {
			id: 'cat:arquitectura', type: 'cat', label: 'Arquitectura', color: catColor('arquitectura') || '#334155', icon: 'fa-layer-group', arch: true,
			children: (a.layers || []).map(function (l)
			{
				var s = src[l.id];
				return { id: 'layer:' + l.id, type: 'group', label: l.name + (s ? ' (' + s[0].length + ')' : ''), layer: l, children: s ? leafNodes('layer:' + l.id, s[0], s[1], { limit: 18 }) : [] };
			})
		};
	}
	function buildTree()
	{
		var root = { id: 'root', type: 'root', label: 'DAILY', children: [] };
		S.map.categories.forEach(function (c)
		{
			if (c.id === 'arquitectura' || S.cats[c.id] === false) return;
			var mods = S.map.modules.filter(function (m) { return m.category === c.id && moduleVisible(m); });
			if (!mods.length) return;
			root.children.push({
				id: 'cat:' + c.id, type: 'cat', label: c.name, color: c.color, icon: c.icon, cat: c,
				children: mods.map(function (m) { return { id: 'mod:' + m.id, type: 'module', label: m.name, status: m.status, module: m, color: c.color, children: moduleGroups(m) }; })
			});
		});
		if (S.cats.arquitectura !== false) root.children.push(architectureNode());
		return root;
	}

	// tamaños por tipo
	var W = { root: 150, cat: 180, module: 200, group: 180, leaf: 260 };
	var H = { root: 54, cat: 38, module: 32, group: 28, leaf: 24 };
	var GAPX = 56, GAPY = 8;
	function colX(depth)
	{
		var order = ['root', 'cat', 'module', 'group', 'leaf'];
		var x = W.root / 2;
		for (var i = 1; i < depth; i++) x += W[order[i]] + GAPX;
		return x + GAPX;
	}
	function visibleChildren(n) { return S.expanded[n.id] ? (n.children || []) : []; }
	function subtreeH(n)
	{
		var ch = visibleChildren(n);
		var own = H[n.type] + GAPY;
		if (!ch.length) return own;
		var sum = ch.reduce(function (a, c) { return a + subtreeH(c); }, 0);
		return Math.max(own, sum);
	}
	function layoutMind(root)
	{
		var nodes = [], edges = [];
		root.x = 0; root.y = 0; root.depth = 0;
		nodes.push(root);
		var cats = root.children;
		// reparte categorías a izquierda/derecha equilibrando alto
		var hs = cats.map(subtreeH), total = hs.reduce(function (a, b) { return a + b; }, 0);
		var right = [], left = [], acc = 0;
		cats.forEach(function (c, i) { (acc + hs[i] / 2 <= total / 2 ? right : left).push(c); acc += hs[i]; });
		[[right, 1], [left, -1]].forEach(function (pair)
		{
			var list = pair[0], side = pair[1];
			var h = list.reduce(function (a, c) { return a + subtreeH(c); }, 0);
			var y = -h / 2;
			list.forEach(function (c)
			{
				place(c, 1, side, y, root);
				y += subtreeH(c);
			});
		});
		function place(n, depth, side, top, parent)
		{
			var h = subtreeH(n);
			n.depth = depth; n.side = side;
			n.x = side * (colX(depth) + W[n.type] / 2);
			n.y = top + h / 2;
			nodes.push(n);
			edges.push({ a: parent, b: n });
			var ch = visibleChildren(n);
			var sum = ch.reduce(function (a, c) { return a + subtreeH(c); }, 0);
			var y = top + (h - sum) / 2;
			ch.forEach(function (c) { place(c, depth + 1, side, y, n); y += subtreeH(c); });
		}
		return { nodes: nodes, edges: edges };
	}

	// ---------- render SVG ----------
	function resetSvg()
	{
		while (svg.firstChild) svg.removeChild(svg.firstChild);
		var defs = el('defs', {}, svg);
		Object.keys(ST).concat(['dim']).forEach(function (k)
		{
			var mk = el('marker', { id: 'sm-arrow-' + k, viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 7, markerHeight: 7, orient: 'auto-start-reverse' }, defs);
			el('path', { d: 'M0,0 L10,5 L0,10 z', fill: k === 'dim' ? '#cbd5e1' : ST[k].color }, mk);
		});
		el('rect', { x: -1e5, y: -1e5, width: 2e5, height: 2e5, fill: 'transparent', 'class': 'sm-bg' }, svg);
		vp = el('g', { 'class': 'sm-vp' }, svg);
		applyTx();
	}
	function applyTx() { if (vp) vp.setAttribute('transform', 'translate(' + S.tx.x + ',' + S.tx.y + ') scale(' + S.tx.k + ')'); }

	function drawNode(n, g)
	{
		var w = W[n.type], h = H[n.type];
		var st = n.status ? ST[n.status] : null;
		var cls = 'sm-node' + (S.selected === n.id ? ' selected' : '');
		var grp = el('g', { 'class': cls, transform: 'translate(' + n.x + ',' + n.y + ')', 'data-id': n.id }, g);
		var fill = '#ffffff', stroke = '#cbd5e1', tc = '#0f172a', fw = 500, fs = 12;
		if (n.type === 'root') { fill = '#0f172a'; stroke = '#0f172a'; tc = '#ffffff'; fw = 800; fs = 20; }
		else if (n.type === 'cat') { fill = n.color; stroke = n.color; tc = '#ffffff'; fw = 700; fs = 13; }
		else if (n.type === 'module') { stroke = st ? st.color : '#cbd5e1'; fw = 600; }
		else if (n.type === 'group') { fill = '#f8fafc'; stroke = '#e2e8f0'; tc = '#334155'; fs = 11.5; }
		else { fill = st ? st.bg : '#ffffff'; stroke = st ? st.color : '#e2e8f0'; tc = '#334155'; fs = 11; fw = 400; if (n.more) tc = '#64748b'; }
		el('rect', { x: -w / 2, y: -h / 2, width: w, height: h, rx: n.type === 'root' ? 27 : n.type === 'leaf' ? 5 : 8, fill: fill, stroke: stroke, 'stroke-width': n.type === 'module' ? 2 : 1.2 }, grp);
		if (n.type === 'module')
		{
			el('rect', { x: -w / 2, y: -h / 2, width: 5, height: h, rx: 2, fill: n.color }, grp);
			el('circle', { cx: w / 2 - 12, cy: 0, r: 5, fill: st.color }, grp);
		}
		var tx = el('text', { x: n.type === 'module' ? -w / 2 + 12 : 0, y: fs * 0.36, 'text-anchor': n.type === 'module' ? 'start' : 'middle', 'font-size': fs, 'font-weight': fw, fill: tc }, grp);
		tx.textContent = fit(n.label, w - (n.type === 'module' ? 34 : 16), fs);
		var t = el('title', {}, grp);
		t.textContent = n.label + (st ? ' — ' + st.label : '');
		var ch = n.children || [];
		if (ch.length && n.type !== 'root')
		{
			var ex = !!S.expanded[n.id];
			var cx = n.side > 0 ? w / 2 : -w / 2;
			var tg = el('g', { 'class': 'sm-toggle', transform: 'translate(' + cx + ',0)' }, grp);
			el('circle', { r: 8, fill: '#ffffff', stroke: n.type === 'cat' ? n.color : '#94a3b8', 'stroke-width': 1.5 }, tg);
			var tt = el('text', { y: 4, 'text-anchor': 'middle', 'font-size': 12, 'font-weight': 700, fill: '#475569' }, tg);
			tt.textContent = ex ? '−' : String(ch.length > 99 ? '+' : ch.length);
			if (!ex && ch.length > 9) tt.setAttribute('font-size', 9);
		}
		return grp;
	}

	var lastMind = null;
	function renderMind()
	{
		resetSvg();
		var root = buildTree();
		var L = layoutMind(root);
		lastMind = L;
		var ge = el('g', {}, vp), gn = el('g', {}, vp);
		L.edges.forEach(function (e)
		{
			var a = e.a, b = e.b, s = b.side;
			var x1 = a.x + s * W[a.type] / 2, x2 = b.x - s * W[b.type] / 2;
			if (a.type === 'root') x1 = a.x + s * W.root / 2;
			var mx = (x1 + x2) / 2;
			var color = b.type === 'cat' ? b.color : (b.color || (b.status ? ST[b.status].color : '#cbd5e1'));
			if (b.type === 'group' || b.type === 'leaf') color = '#cbd5e1';
			el('path', { d: 'M' + x1 + ',' + a.y + ' C' + mx + ',' + a.y + ' ' + mx + ',' + b.y + ' ' + x2 + ',' + b.y, fill: 'none', stroke: color, 'stroke-width': b.type === 'cat' ? 3 : b.type === 'module' ? 1.8 : 1.2, 'stroke-opacity': 0.85, 'class': 'sm-edge' }, ge);
		});
		L.nodes.forEach(function (n)
		{
			var g = drawNode(n, gn);
			if (S.q)
			{
				var hit = n.type === 'module' ? moduleHit(n.module) : norm(n.label).indexOf(S.q) >= 0;
				if (hit) g.classList.add('hit');
				else if (n.type !== 'root' && n.type !== 'cat') g.classList.add('dim');
			}
			g._n = n;
		});
		if (S.needFit) { fitView(); S.needFit = false; }
	}

	// ---------- vista de conexiones ----------
	var lastConn = null;
	function renderConn()
	{
		resetSvg();
		var mods = S.map.modules.filter(moduleVisible);
		var byCat = [];
		S.map.categories.forEach(function (c)
		{
			var ms = mods.filter(function (m) { return m.category === c.id; });
			if (ms.length) byCat.push({ cat: c, mods: ms });
		});
		var n = byCat.length || 1;
		var R = Math.max(520, n * 70);
		var pos = {};
		var nodes = [];
		byCat.forEach(function (g, i)
		{
			var ang = -Math.PI / 2 + i * 2 * Math.PI / n;
			var cx = Math.cos(ang) * R * 1.25, cy = Math.sin(ang) * R;
			var h = g.mods.length * (H.module + 6);
			g.x = cx; g.y = cy; g.h = h;
			g.mods.forEach(function (m, j)
			{
				var y = cy - h / 2 + j * (H.module + 6) + H.module / 2 + 14;
				pos[m.id] = { x: cx, y: y };
				nodes.push({ id: 'mod:' + m.id, type: 'module', label: m.name, status: m.status, module: m, color: g.cat.color, x: cx, y: y, side: 1 });
			});
		});
		var conns = (S.map.connections || []).filter(function (c) { return pos[c.from] && pos[c.to]; });
		var gc = el('g', {}, vp), ge = el('g', {}, vp), gn = el('g', {}, vp);
		byCat.forEach(function (g)
		{
			el('rect', { x: g.x - W.module / 2 - 10, y: g.y - g.h / 2 - 16, width: W.module + 20, height: g.h + 26, rx: 12, fill: g.cat.color, 'fill-opacity': 0.06, stroke: g.cat.color, 'stroke-opacity': 0.35, 'stroke-dasharray': '4 3' }, gc);
			var t = el('text', { x: g.x, y: g.y - g.h / 2 - 22, 'text-anchor': 'middle', 'font-size': 13, 'font-weight': 700, fill: g.cat.color }, gc);
			t.textContent = g.cat.name;
		});
		var sel = S.selected && S.selected.indexOf('mod:') === 0 ? S.selected.substr(4) : null;
		var selEdge = S.selected && S.selected.indexOf('conn:') === 0 ? +S.selected.substr(5) : null;
		var degree = {};
		conns.forEach(function (c, i)
		{
			degree[c.from] = (degree[c.from] || 0) + 1; degree[c.to] = (degree[c.to] || 0) + 1;
			var a = pos[c.from], b = pos[c.to];
			var side = a.x <= b.x ? 1 : -1;
			if (Math.abs(a.x - b.x) < 1) side = a.x > 0 ? -1 : 1;
			var x1 = a.x + side * W.module / 2, x2 = b.x - side * W.module / 2;
			if (Math.abs(a.x - b.x) < 1) { x1 = a.x + side * W.module / 2; x2 = b.x + side * W.module / 2; }
			var mx = (x1 + x2) / 2 * 0.35, my = (a.y + b.y) / 2 * 0.35;
			var d = Math.abs(a.x - b.x) < 1
				? 'M' + x1 + ',' + a.y + ' C' + (x1 + side * 90) + ',' + a.y + ' ' + (x2 + side * 90) + ',' + b.y + ' ' + x2 + ',' + b.y
				: 'M' + x1 + ',' + a.y + ' Q' + mx + ',' + my + ' ' + x2 + ',' + b.y;
			var st = c.status === 'verified' ? 'verified' : c.status === 'proposed' ? 'proposed' : 'unverified';
			var involved = sel ? (c.from === sel || c.to === sel) : selEdge === i;
			var dim = (sel || selEdge !== null) && !involved;
			var color = st === 'verified' ? catColor(S.mods[c.from].category) : ST[st].color;
			var p = el('path', { d: d, fill: 'none', stroke: dim ? '#cbd5e1' : color, 'stroke-width': involved ? 3 : 1.6, 'stroke-opacity': dim ? 0.25 : 0.85, 'stroke-dasharray': st === 'verified' ? null : '6 4', 'marker-end': 'url(#sm-arrow-' + (dim ? 'dim' : st === 'verified' ? 'verified' : st) + ')', 'class': 'sm-edge' }, ge);
			if (st === 'verified' && !dim) p.setAttribute('marker-end', 'url(#sm-arrow-verified)');
			var hitp = el('path', { d: d, fill: 'none', stroke: 'transparent', 'stroke-width': 12, 'class': 'sm-node', 'data-id': 'conn:' + i, style: 'cursor:pointer' }, ge);
			var t = el('title', {}, hitp);
			t.textContent = (S.mods[c.from].name) + ' → ' + (S.mods[c.to].name) + ': ' + c.label + ' (' + ST[st].label + ')';
			hitp._n = { id: 'conn:' + i, conn: c };
		});
		nodes.forEach(function (nd)
		{
			var g = drawNode(nd, gn);
			g._n = nd;
			var m = nd.module;
			if (!degree[m.id]) g.setAttribute('opacity', 0.55);
			if (S.q && !moduleHit(m)) g.classList.add('dim');
			else if (S.q) g.classList.add('hit');
		});
		lastConn = { conns: conns };
		if (S.needFit) { fitView(); S.needFit = false; }
	}

	// ---------- vista de auditoría ----------
	function renderAudit()
	{
		var map = S.map, ev = map.evidence || {};
		var html = '';
		// resumen
		var counts = {};
		map.modules.forEach(function (m) { counts[m.status] = (counts[m.status] || 0) + 1; });
		html += '<div class="sm-a-sec"><h6><span><i class="fa-solid fa-gauge-high mr-1"></i>Estado funcional por módulo</span><span class="sm-a-sum">';
		['verified', 'partial', 'error', 'unverified'].forEach(function (k)
		{
			html += '<a href="#" class="sm-a-stf" data-st="' + k + '">' + statusBadge(k) + ' <b>' + (counts[k] || 0) + '</b></a>';
		});
		html += '<span>' + statusBadge('proposed') + ' <b>' + (map.proposals || []).length + '</b></span></span></h6><div class="sm-a-in">';
		html += '<p class="sm-small text-muted mb-2">Verificado = la pantalla carga en el barrido HTTP <b>y</b> pasa al menos una prueba automática que la cubre. Parcial = carga pero sin prueba de sus operaciones o con hallazgos abiertos. ' + (S.auditStatus ? 'Filtrado: ' + statusBadge(S.auditStatus) + ' <a href="#" class="sm-a-stf" data-st="">quitar</a>' : '') + '</p><div class="sm-a-grid">';
		var order = { error: 0, partial: 1, unverified: 2, verified: 3 };
		map.modules.slice().filter(function (m) { return (!S.auditStatus || m.status === S.auditStatus) && moduleVisible(m) && (!S.q || moduleHit(m)); })
			.sort(function (a, b) { return order[a.status] - order[b.status] || a.name.localeCompare(b.name); })
			.forEach(function (m)
			{
				html += '<div class="sm-a-mod" data-mod="' + esc(m.id) + '" style="border-left-color:' + ST[m.status].color + '"><div class="t">' + esc(m.name) + '</div>' +
					'<div class="r"><span class="sm-chip" style="border-color:' + catColor(m.category) + '">' + esc((S.catById[m.category] || {}).name || m.category) + '</span>' + statusBadge(m.status) + '</div>' +
					'<div class="r">' + esc((m.status_reasons || []).join(' · ')) + '</div>' +
					'<div class="n"><span><i class="fa-solid fa-plug"></i> ' + (m.endpoints || []).length + '</span><span><i class="fa-solid fa-vial"></i> ' + (m.tests || []).length + '</span><span><i class="fa-solid fa-triangle-exclamation"></i> ' + (m.issues || []).length + '</span>' + (m.in_mobile ? '<span><i class="fa-solid fa-mobile-screen"></i> móvil</span>' : '') + '</div></div>';
			});
		html += '</div></div></div>';

		// hallazgos
		var types = {};
		map.issues.forEach(function (i) { types[i.type] = 1; });
		var iss = map.issues.filter(function (i)
		{
			if (S.auditSev && i.severity !== S.auditSev) return false;
			if (S.auditType && i.type !== S.auditType) return false;
			if (S.q && norm(i.title + ' ' + i.type + ' ' + i.module).indexOf(S.q) < 0) return false;
			return true;
		}).sort(function (a, b) { return SEV_ORDER[a.severity] - SEV_ORDER[b.severity]; });
		html += '<div class="sm-a-sec"><h6><span><i class="fa-solid fa-list-ol mr-1"></i>Problemas priorizados (' + iss.length + ' de ' + map.issues.length + ')</span><span class="sm-a-filters">' +
			'<select class="custom-select custom-select-sm" id="sm-a-sev"><option value="">Toda severidad</option>' + ['P0', 'P1', 'P2', 'P3', 'Info'].map(function (s) { return '<option' + (S.auditSev === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select>' +
			'<select class="custom-select custom-select-sm" id="sm-a-type"><option value="">Todo tipo</option>' + Object.keys(types).sort().map(function (t) { return '<option' + (S.auditType === t ? ' selected' : '') + '>' + esc(t) + '</option>'; }).join('') + '</select></span></h6>' +
			'<div class="table-responsive"><table class="sm-a-table"><thead><tr><th>Sev.</th><th>Tipo</th><th>Módulo</th><th>Hallazgo</th><th class="sm-hide-xs">Evidencia</th><th class="sm-hide-xs">Recomendación</th></tr></thead><tbody>';
		iss.forEach(function (i)
		{
			var m = S.mods[i.module];
			html += '<tr class="sm-row" data-mod="' + esc(i.module || '') + '"><td>' + sevBadge(i.severity) + '</td><td>' + esc(i.type) + (i.confidence && i.confidence !== 'confirmed' ? '<div class="sm-small">' + esc(i.confidence) + '</div>' : '') + '</td><td>' + esc(m ? m.name : (i.module || '—')) + '</td><td>' + esc(i.title) + '</td>' +
				'<td class="sm-hide-xs">' + (i.evidence || []).map(function (e) { return codeLink(e.file, e.line); }).join('<br>') + '</td><td class="sm-hide-xs">' + esc(i.recommendation || '') + '</td></tr>';
		});
		html += '</tbody></table></div></div>';

		// propuestas
		html += '<div class="sm-a-sec"><h6><span><i class="fa-solid fa-lightbulb mr-1" style="color:' + ST.proposed.color + '"></i>Integraciones propuestas — NO implementadas</span></h6><div class="sm-a-in"><table class="sm-a-table"><thead><tr><th>De → a</th><th>Propuesta</th><th class="sm-hide-xs">Origen</th></tr></thead><tbody>';
		(map.proposals || []).forEach(function (p)
		{
			html += '<tr><td>' + esc((S.mods[p.from] || {}).name || p.from) + ' → ' + esc((S.mods[p.to] || {}).name || p.to) + '</td><td>' + statusBadge('proposed') + ' ' + esc(p.title) + (p.maybe_implemented ? '<div class="sm-small">Posible implementación parcial: ' + esc(p.maybe_implemented) + '</div>' : '') + '</td><td class="sm-hide-xs sm-small">' + esc(p.source) + '</td></tr>';
		});
		html += '</tbody></table></div></div>';

		// evidencia
		html += '<div class="sm-a-sec"><h6><span><i class="fa-solid fa-vial mr-1"></i>Evidencia de ejecución</span><span class="sm-small">' + esc(evidenceLine()) + '</span></h6><div class="sm-a-in"><table class="sm-a-table"><thead><tr><th>Prueba</th><th>Tipo</th><th>Resultado</th><th>Checks</th><th class="sm-hide-xs">Cubre</th></tr></thead><tbody>';
		((map.architecture || {}).tests || []).forEach(function (t)
		{
			var st = t.ok === true ? 'verified' : t.ok === false ? 'error' : 'unverified';
			html += '<tr><td>' + codeLink('tests/dairy/' + t.file) + '</td><td>' + esc(t.kind || '') + '</td><td>' + statusBadge(st) + '</td><td>' + (t.checks != null ? t.checks + (t.fails ? ' (' + t.fails + ' fallidos)' : '') : '—') + '</td><td class="sm-hide-xs sm-small">' + esc((t.services || []).join(', ')) + '</td></tr>';
		});
		html += '</tbody></table><p class="sm-small text-muted mt-2 mb-0">Regenerar con evidencia: <code>php tests/dairy/system_map_build.php --full</code> (pruebas sobre copias de la BD). «Actualizar análisis» solo repite el análisis estático y reutiliza esta evidencia.</p></div></div>';

		// rutas sin módulo
		var un = map.unassigned_routes || [];
		if (un.length)
		{
			html += '<div class="sm-a-sec"><h6><span><i class="fa-solid fa-route mr-1"></i>Rutas web sin módulo asignado (' + un.length + ')</span></h6><div class="sm-a-in">' + un.map(function (r) { return '<span class="sm-chip">' + esc(r.path || r) + '</span>'; }).join('') + '</div></div>';
		}
		$('#sm-audit').html(html);
	}

	// ---------- panel de detalles ----------
	function sec(title, body, count)
	{
		if (!body) return '';
		return '<div class="sm-p-sec"><h6><span>' + title + '</span>' + (count != null ? '<span>' + count + '</span>' : '') + '</h6>' + body + '</div>';
	}
	function ul(items) { return items.length ? '<ul>' + items.join('') + '</ul>' : ''; }
	function showModule(m)
	{
		var c = S.catById[m.category] || {};
		var h = '<div class="sm-p-head"><div class="sm-crumb">DAILY › ' + esc(c.name || m.category) + '</div><h5><i class="fa-solid ' + esc(m.icon || 'fa-cube') + ' mr-1" style="color:' + catColor(m.category) + '"></i>' + esc(m.name) + '</h5>' + statusBadge(m.status) +
			'<div class="sm-p-actions">' + (m.url && m.url.indexOf('{') < 0 ? '<a class="btn btn-sm btn-primary" href="' + esc(U(m.url)) + '"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir pantalla</a>' : '') +
			(m.files && m.files[0] ? '<a class="btn btn-sm btn-outline-secondary" href="' + esc('vscode://file/' + ROOT + '/' + m.files[0].path + ':' + (m.files[0].line || 1)) + '"><i class="fa-solid fa-code"></i> Código</a>' : '') + '</div></div>';
		h += sec('Ruta', routeLink(m.route) + ((m.extra_routes || []).length ? '<div class="sm-small mt-1">También: ' + m.extra_routes.map(function (r) { return '<code>' + esc(r.path || r) + '</code>'; }).join(' ') + '</div>' : '') + (m.description ? '<div class="mt-1">' + esc(m.description) + '</div>' : ''));
		h += sec('Estado de funcionamiento', ul((m.status_reasons || []).map(function (r) { return '<li>' + esc(r) + '</li>'; })));
		h += sec('Pantallas', ul((m.screens || []).map(function (s)
		{
			var sw = s.sweep ? (s.sweep.ok ? ' <span class="text-success">· barrido OK</span>' : ' <span class="text-danger">· barrido FALLA ' + esc(s.sweep.detail || '') + '</span>') : '';
			return '<li>' + routeLink(s.path) + ' <span class="sm-small">' + esc(s.handler || '') + (s.exists === false || s.handler_ok === false ? ' <b class="text-danger">(inexistente)</b>' : '') + sw + '</span>' + ((s.page_permissions || []).length ? '<div class="sm-small">Permisos: ' + esc(s.page_permissions.join(', ')) + '</div>' : '') + '</li>';
		})), (m.screens || []).length);
		h += sec('Pestañas', (m.tabs || []).map(function (t) { return '<span class="sm-chip" title="' + esc(t.file + ':' + t.line) + '">' + esc(t.label) + '</span>'; }).join(''), (m.tabs || []).length);
		h += sec('Funciones: botones y acciones', ul((m.buttons || []).map(function (b) { return '<li>' + esc(b.label) + ' <span class="sm-small">' + codeLink(b.file, b.line) + '</span></li>'; })), (m.buttons || []).length);
		var forms = (m.forms || []).map(function (f) { return '<li><code>#' + esc(f.id) + '</code> <span class="sm-small">' + esc(f.method) + ' · ' + codeLink(f.file, f.line) + '</span></li>'; })
			.concat((m.modals || []).map(function (x) { return '<li>Modal: ' + esc(x.title || x.id) + ' <span class="sm-small">' + codeLink(x.file, x.line) + '</span></li>'; }));
		h += sec('Formularios y modales', ul(forms), forms.length);
		h += sec('Endpoints', ul((m.endpoints || []).map(function (e)
		{
			return '<li><code>' + esc(e.method + ' ' + e.path) + '</code><div class="sm-small">' + esc(e.handler) + ((e.permissions || []).length ? ' · ' + esc(e.permissions.join(', ')) : ' · <span class="text-warning">sin permiso explícito</span>') + ((e.tested_by || []).length ? ' · <span class="text-success">probado</span>' : '') + '</div></li>';
		})), (m.endpoints || []).length);
		if ((m.broken_calls || []).length) h += sec('<span class="text-danger">Llamadas a APIs inexistentes</span>', ul(m.broken_calls.map(function (b) { return '<li><code>' + esc(b.path || b.call || JSON.stringify(b)) + '</code> ' + codeLink(b.file, b.line) + '</li>'; })));
		if ((m.suspect_buttons || []).length) h += sec('<span class="text-warning">Botones sin acción detectada</span>', ul(m.suspect_buttons.map(function (b) { return '<li>' + esc(b.label || b.id) + ' ' + codeLink(b.file, b.line) + '</li>'; })));
		h += sec('Componentes / vistas', (m.views || []).map(function (v) { return '<span class="sm-chip">' + esc(v) + '</span>'; }).join('') + (m.files || []).map(function (f) { return '<div>' + codeLink(f.path, f.line) + '</div>'; }).join(''));
		h += sec('Servicios (dependencias)', (m.services || []).map(function (s) { return '<span class="sm-chip">' + esc(s) + '</span>'; }).join(''), (m.services || []).length);
		h += sec('Tablas / modelos de datos', (m.tables || []).map(function (s) { return '<span class="sm-chip">' + esc(s) + '</span>'; }).join(''), (m.tables || []).length);
		h += sec('Roles autorizados', (m.roles || []).map(function (s) { return '<span class="sm-chip">' + esc(s) + '</span>'; }).join('') + ((m.permissions || []).length ? '<div class="sm-small mt-1">Permisos: ' + esc(m.permissions.join(', ')) + '</div>' : ''));
		h += sec('Módulos conectados', ul((m.connections || []).map(function (c)
		{
			var o = S.mods[c.with];
			return '<li>' + (c.dir === 'out' ? '→ ' : '← ') + '<span class="sm-link" data-goto="' + esc(c.with) + '">' + esc(o ? o.name : c.with) + '</span> ' + statusBadge(c.status === 'verified' ? 'verified' : c.status === 'proposed' ? 'proposed' : 'unverified') + '<div class="sm-small">' + esc(c.label) + '</div></li>';
		})), (m.connections || []).length);
		h += sec('Pruebas automáticas', ul((m.tests || []).map(function (t)
		{
			var st = t.ok === true ? 'verified' : t.ok === false ? 'error' : 'unverified';
			return '<li>' + statusBadge(st) + ' ' + codeLink('tests/dairy/' + t.file) + (t.checks != null ? ' <span class="sm-small">' + t.checks + ' checks' + (t.fails ? ', ' + t.fails + ' fallidos' : '') + '</span>' : '') + '<div class="sm-small">vía ' + esc((t.via || []).join(', ')) + '</div></li>';
		})), (m.tests || []).length);
		h += sec('Móvil', m.in_mobile ? '<span class="text-success"><i class="fa-solid fa-mobile-screen"></i> Presente en DAILY Mobile (/m)</span>' : '<span class="text-muted">Sin equivalente detectado en /m</span>');
		var iss = (m.issues || []).map(function (id) { return S.issues[id]; }).filter(Boolean).sort(function (a, b) { return SEV_ORDER[a.severity] - SEV_ORDER[b.severity]; });
		h += sec('Errores / hallazgos', ul(iss.map(function (i) { return '<li>' + sevBadge(i.severity) + ' <b>' + esc(i.type) + ':</b> ' + esc(i.title) + '<div class="sm-small">' + (i.evidence || []).map(function (e) { return codeLink(e.file, e.line); }).join(' ') + '</div></li>'; })), iss.length);
		h += sec('Recomendaciones de mejora', ul(iss.filter(function (i) { return i.recommendation; }).map(function (i) { return '<li>' + esc(i.recommendation) + '</li>'; }))
			+ ul((S.map.proposals || []).filter(function (p) { return p.from === m.id || p.to === m.id; }).map(function (p) { return '<li>' + statusBadge('proposed') + ' ' + esc(p.title) + '</li>'; })));
		$panel.html(h);
	}
	function showCategory(n)
	{
		var mods = S.map.modules.filter(function (m) { return n.cat && m.category === n.cat.id; });
		var h = '<div class="sm-p-head"><div class="sm-crumb">DAILY</div><h5><i class="fa-solid ' + esc(n.icon || 'fa-folder') + ' mr-1" style="color:' + n.color + '"></i>' + esc(n.label) + '</h5></div>';
		if (n.arch)
		{
			h += sec('Capas', ul(((S.map.architecture || {}).layers || []).map(function (l) { return '<li><b>' + esc(l.name) + '</b><div class="sm-small">' + esc(l.description) + '</div></li>'; })));
		}
		else
		{
			h += sec('Módulos', ul(mods.map(function (m) { return '<li><span class="sm-link" data-goto="' + esc(m.id) + '">' + esc(m.name) + '</span> ' + statusBadge(m.status) + '<div class="sm-small">' + routeLink(m.route) + '</div></li>'; })), mods.length);
		}
		$panel.html(h);
	}
	function showRoot()
	{
		var s = S.map.stats;
		var h = '<div class="sm-p-head"><h5>DAILY</h5><div class="sm-small">' + esc(evidenceLine()) + '</div></div>';
		h += sec('Inventario', ul([
			['Módulos', s.modules], ['Rutas web', s.routes_web], ['Endpoints API', s.routes_api], ['Controladores', s.controllers], ['Servicios', s.services],
			['Vistas Blade', s.views], ['JS por vista', s.viewjs], ['Tablas/vistas BD', s.tables], ['Migraciones', s.migrations], ['Archivos de prueba', s.tests]
		].map(function (r) { return '<li>' + r[0] + ': <b>' + r[1] + '</b></li>'; })));
		h += sec('Capas', ul(((S.map.architecture || {}).layers || []).map(function (l) { return '<li><b>' + esc(l.name) + '</b><div class="sm-small">' + esc(l.description) + '</div></li>'; })));
		$panel.html(h);
	}
	function showConn(c)
	{
		var st = c.status === 'verified' ? 'verified' : c.status === 'proposed' ? 'proposed' : 'unverified';
		var a = S.mods[c.from], b = S.mods[c.to];
		var h = '<div class="sm-p-head"><div class="sm-crumb">Conexión ' + (c.kind === 'proposal' ? 'propuesta' : 'operativa') + '</div><h5>' + esc(a.name) + ' → ' + esc(b.name) + '</h5>' + statusBadge(st) + '</div>';
		h += sec('Qué hace', esc(c.label));
		if (c.evidence) h += sec('Evidencia en el código', codeLink(c.evidence.file, c.evidence.line) + (c.evidence.snippet ? '<pre class="mt-1 mb-0 sm-small" style="white-space:pre-wrap">' + esc(c.evidence.snippet) + '</pre>' : ''));
		else if (st === 'proposed') h += sec('Evidencia', '<span class="text-muted">Sin implementación: propuesta de integración.</span>');
		h += sec('Extremos', '<span class="sm-link" data-goto="' + esc(c.from) + '">' + esc(a.name) + '</span> → <span class="sm-link" data-goto="' + esc(c.to) + '">' + esc(b.name) + '</span>');
		$panel.html(h);
	}
	function showLeaf(n)
	{
		var r = n.ref || {};
		var h = '<div class="sm-p-head"><div class="sm-crumb">' + esc(n.id.split('/')[0].replace('mod:', '')) + '</div><h5 style="word-break:break-word">' + esc(n.label) + '</h5>' + (n.status ? statusBadge(n.status) : '') + '</div>';
		var rows = [];
		Object.keys(r).forEach(function (k)
		{
			if (k.charAt(0) === '_' || r[k] == null || r[k] === '' || (Array.isArray(r[k]) && !r[k].length)) return;
			var v = r[k];
			if (k === 'file') v = codeLink(r.file, r.line);
			else if (k === 'evidence' && Array.isArray(v)) v = v.map(function (e) { return codeLink(e.file, e.line); }).join(' ');
			else v = esc(typeof v === 'object' ? JSON.stringify(v) : v);
			rows.push('<li><b>' + esc(k) + ':</b> ' + v + '</li>');
		});
		if (typeof r === 'string') rows.push('<li>' + esc(r) + '</li>');
		h += sec('Detalle', ul(rows));
		$panel.html(h);
	}

	function selectNode(n)
	{
		S.selected = n.id;
		if (n.conn) showConn(n.conn);
		else if (n.type === 'root') showRoot();
		else if (n.type === 'cat') showCategory(n);
		else if (n.module && n.type === 'module') showModule(n.module);
		else if (n.type === 'group' && n.module) showModule(S.mods[n.module]);
		else if (n.type === 'group' && n.layer) $panel.html('<div class="sm-p-head"><div class="sm-crumb">Arquitectura</div><h5>' + esc(n.layer.name) + '</h5></div>' + sec('Descripción', esc(n.layer.description)));
		else if (n.type === 'leaf') showLeaf(n);
	}
	function gotoModule(id)
	{
		var m = S.mods[id];
		if (!m) return;
		if (S.view === 'mind')
		{
			S.expanded['cat:' + m.category] = true;
			S.selected = 'mod:' + id;
			render();
			var n = lastMind.nodes.filter(function (x) { return x.id === 'mod:' + id; })[0];
			if (n) centerOn(n.x, n.y);
		}
		else if (S.view === 'conn') { S.selected = 'mod:' + id; render(); }
		showModule(m);
	}

	// ---------- zoom / arrastre ----------
	function stageSize() { var r = svg.getBoundingClientRect(); return { w: r.width || 800, h: r.height || 600 }; }
	function fitView()
	{
		if (!vp) return;
		var b = vp.getBBox(), sz = stageSize();
		if (!b.width) return;
		var k = Math.min(1.4, Math.max(0.12, Math.min((sz.w - 40) / b.width, (sz.h - 40) / b.height)));
		S.tx = { k: k, x: sz.w / 2 - (b.x + b.width / 2) * k, y: sz.h / 2 - (b.y + b.height / 2) * k };
		applyTx();
	}
	function centerOn(x, y)
	{
		var sz = stageSize();
		S.tx.k = Math.max(S.tx.k, 0.8);
		S.tx.x = sz.w / 2 - x * S.tx.k; S.tx.y = sz.h / 2 - y * S.tx.k;
		applyTx();
	}
	function zoomAt(f, cx, cy)
	{
		var k = Math.min(3, Math.max(0.08, S.tx.k * f));
		S.tx.x = cx - (cx - S.tx.x) * k / S.tx.k; S.tx.y = cy - (cy - S.tx.y) * k / S.tx.k; S.tx.k = k;
		applyTx();
	}
	svg.addEventListener('wheel', function (e)
	{
		e.preventDefault();
		var r = svg.getBoundingClientRect();
		zoomAt(e.deltaY < 0 ? 1.12 : 1 / 1.12, e.clientX - r.left, e.clientY - r.top);
	}, { passive: false });
	var drag = null, pointers = {};
	svg.addEventListener('pointerdown', function (e)
	{
		pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
		drag = { x: e.clientX, y: e.clientY, tx: S.tx.x, ty: S.tx.y, moved: false, target: e.target };
		try { svg.setPointerCapture(e.pointerId); } catch (ex) { }
	});
	svg.addEventListener('pointermove', function (e)
	{
		var ids = Object.keys(pointers);
		if (ids.length === 2 && pointers[e.pointerId])
		{
			var o = pointers[ids[0] == e.pointerId ? ids[1] : ids[0]];
			var p = pointers[e.pointerId];
			var d0 = Math.hypot(p.x - o.x, p.y - o.y), d1 = Math.hypot(e.clientX - o.x, e.clientY - o.y);
			var r = svg.getBoundingClientRect();
			if (d0 > 0) zoomAt(d1 / d0, (o.x + e.clientX) / 2 - r.left, (o.y + e.clientY) / 2 - r.top);
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			if (drag) drag.moved = true;
			return;
		}
		if (pointers[e.pointerId]) pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
		if (!drag) return;
		var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
		if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 5) return;
		drag.moved = true;
		svg.classList.add('dragging');
		S.tx.x = drag.tx + dx; S.tx.y = drag.ty + dy;
		applyTx();
	});
	function endPointer(e)
	{
		delete pointers[e.pointerId];
		svg.classList.remove('dragging');
		if (!drag) return;
		var d = drag; drag = null;
		if (d.moved || e.type === 'pointercancel') return;
		var g = d.target.closest ? d.target.closest('.sm-node') : null;
		if (!g || !g._n) return;
		var n = g._n;
		var toggled = d.target.closest('.sm-toggle');
		if ((n.children || []).length && n.type !== 'root' && (toggled || n.type === 'cat' || n.type === 'group' || S.selected === n.id))
		{
			S.expanded[n.id] = !S.expanded[n.id];
		}
		selectNode(n);
		render();
	}
	svg.addEventListener('pointerup', endPointer);
	svg.addEventListener('pointercancel', endPointer);

	$('#sm-zoom').on('click', 'button', function ()
	{
		var a = $(this).data('zoom'), sz = stageSize();
		if (a === 'in') zoomAt(1.25, sz.w / 2, sz.h / 2);
		else if (a === 'out') zoomAt(0.8, sz.w / 2, sz.h / 2);
		else if (a === 'fit') fitView();
		else if (a === 'expand')
		{
			S.map.categories.forEach(function (c) { S.expanded['cat:' + c.id] = true; });
			S.needFit = true; render();
		}
		else if (a === 'collapse') { S.expanded = { root: true }; S.needFit = true; render(); }
	});

	// ---------- controles ----------
	function render()
	{
		var mapViews = S.view !== 'audit';
		$('#sm-svg').toggleClass('d-none', !mapViews);
		$('#sm-zoom').toggleClass('d-none', !mapViews);
		$('#sm-audit').toggleClass('d-none', mapViews);
		$('.sm-mode').toggleClass('d-none', S.view !== 'mind');
		if (S.view === 'mind') renderMind();
		else if (S.view === 'conn') renderConn();
		else renderAudit();
	}
	$('.sm-views').on('click', '.nav-link', function (e)
	{
		e.preventDefault();
		$('.sm-views .nav-link').removeClass('active');
		$(this).addClass('active');
		S.view = $(this).data('view');
		S.needFit = true;
		try { history.replaceState(null, '', '#' + S.view); } catch (ex) { }
		render();
	});
	$('.sm-mode').on('click', 'button', function ()
	{
		$('.sm-mode button').removeClass('active');
		$(this).addClass('active');
		S.mode = $(this).data('mode');
		render();
	});
	var searchTimer = null;
	$('#sm-search').on('input', function ()
	{
		var v = this.value;
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function ()
		{
			S.q = norm(v.trim());
			if (S.q && S.view === 'mind')
			{
				// expande las categorías y módulos con coincidencias
				S.map.modules.forEach(function (m)
				{
					if (moduleHit(m))
					{
						S.expanded['cat:' + m.category] = true;
						if (norm(m.name).indexOf(S.q) < 0) S.expanded['mod:' + m.id] = true;
					}
				});
				S.needFit = true;
			}
			render();
		}, 200);
	});
	$('#sm-filters').on('click', function (e) { e.stopPropagation(); });
	$('#sm-filters').on('change', 'input', function ()
	{
		var t = $(this).data('t'), k = $(this).data('k');
		(t === 'cat' ? S.cats : S.sts)[k] = this.checked;
		var off = $('#sm-filters input:not(:checked)').length;
		$('#sm-filter-count').text(off).toggleClass('d-none', !off);
		S.needFit = true;
		render();
	});
	$panel.on('click', '[data-goto]', function () { gotoModule($(this).data('goto')); });
	$('#sm-audit').on('click', '.sm-a-mod, tr.sm-row', function (e)
	{
		if ($(e.target).closest('a').length) return;
		var id = $(this).data('mod');
		if (S.mods[id]) showModule(S.mods[id]);
	});
	$('#sm-audit').on('click', '.sm-a-stf', function (e) { e.preventDefault(); S.auditStatus = $(this).data('st'); renderAudit(); });
	$('#sm-audit').on('change', '#sm-a-sev', function () { S.auditSev = this.value; renderAudit(); });
	$('#sm-audit').on('change', '#sm-a-type', function () { S.auditType = this.value; renderAudit(); });

	$('#sm-refresh').on('click', function ()
	{
		var $b = $(this).prop('disabled', true);
		$b.find('i').addClass('fa-spin');
		Grocy.Api.Post('system-map/refresh', {}, function (map)
		{
			$b.prop('disabled', false).find('i').removeClass('fa-spin');
			load(map);
			toastr.success('Análisis actualizado (' + map.duration_ms + ' ms)');
		}, function (xhr)
		{
			$b.prop('disabled', false).find('i').removeClass('fa-spin');
			var msg = 'No se pudo actualizar el análisis';
			try { msg = JSON.parse(xhr.responseText).error_message || msg; } catch (ex) { }
			toastr.error(msg);
		});
	});

	// ---------- exportación ----------
	function svgString()
	{
		var b = vp.getBBox(), pad = 30;
		var clone = vp.cloneNode(true);
		clone.removeAttribute('transform');
		var s = document.createElementNS(NS, 'svg');
		s.setAttribute('xmlns', NS);
		s.setAttribute('viewBox', (b.x - pad) + ' ' + (b.y - pad) + ' ' + (b.width + pad * 2) + ' ' + (b.height + pad * 2));
		s.setAttribute('width', Math.ceil(b.width + pad * 2));
		s.setAttribute('height', Math.ceil(b.height + pad * 2));
		s.setAttribute('font-family', 'Segoe UI, Roboto, Helvetica, Arial, sans-serif');
		el('rect', { x: b.x - pad, y: b.y - pad, width: b.width + pad * 2, height: b.height + pad * 2, fill: '#ffffff' }, s);
		s.appendChild(svg.querySelector('defs').cloneNode(true));
		s.appendChild(clone);
		$(s).find('.dim').attr('opacity', 0.25);
		return { text: new XMLSerializer().serializeToString(s), w: b.width + pad * 2, h: b.height + pad * 2 };
	}
	function download(blob, name)
	{
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = name;
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
	}
	$('.sm-export').on('click', function ()
	{
		var f = $(this).data('format');
		if (f === 'pdf') { window.print(); return; }
		if (S.view === 'audit') { toastr.info('PNG/SVG exportan el mapa mental o de conexiones; para la auditoría use PDF o el informe .md'); return; }
		var name = 'dairy-' + (S.view === 'mind' ? 'mapa-mental' : 'conexiones');
		var o = svgString();
		if (f === 'svg') { download(new Blob([o.text], { type: 'image/svg+xml' }), name + '.svg'); return; }
		var scale = Math.min(2, 12000 / Math.max(o.w, o.h));
		var img = new Image();
		img.onload = function ()
		{
			var c = document.createElement('canvas');
			c.width = Math.ceil(o.w * scale); c.height = Math.ceil(o.h * scale);
			var ctx = c.getContext('2d');
			ctx.drawImage(img, 0, 0, c.width, c.height);
			c.toBlob(function (blob) { if (blob) download(blob, name + '.png'); else toastr.error('El mapa es demasiado grande para PNG: use SVG'); }, 'image/png');
		};
		img.onerror = function () { toastr.error('No se pudo generar el PNG: use SVG'); };
		img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(o.text);
	});

	// ---------- carga ----------
	function evidenceLine()
	{
		var ev = S.map.evidence || {}, p = [];
		p.push('Análisis: ' + S.map.generated_at);
		if (ev.sweep) p.push('Barrido HTTP ' + (ev.sweep.at || '') + ': ' + (ev.sweep.total - ev.sweep.fails) + '/' + ev.sweep.total + ' OK');
		else p.push('Barrido HTTP: sin evidencia');
		if (ev.tests)
		{
			var files = Object.keys(ev.tests).map(function (k) { return ev.tests[k]; }).filter(function (t) { return t && typeof t === 'object' && 'ok' in t; });
			var okN = files.filter(function (t) { return t.ok; }).length;
			var checks = files.reduce(function (a, t) { return a + (t.checks || 0); }, 0);
			p.push('Pruebas ' + (ev.tests_at || '') + ': ' + okN + '/' + files.length + ' archivos OK, ' + checks + ' checks');
		}
		else p.push('Pruebas: sin evidencia');
		return p.join(' · ');
	}
	function kpis()
	{
		var s = S.map.stats, st = s.status || {};
		var items = [
			['Módulos', s.modules, s.routes_web + ' rutas web', 'fa-cubes', 'var(--dairy-primary)'],
			['Verificados', st.verified || 0, 'carga + prueba OK', 'fa-circle-check', ST.verified.color],
			['Parciales', st.partial || 0, 'sin prueba de operaciones', 'fa-circle-half-stroke', ST.partial.color],
			['Con error', st.error || 0, (st.unverified || 0) + ' sin verificar', 'fa-circle-xmark', ST.error.color],
			['Conexiones', s.connections_verified, s.proposals + ' propuestas', 'fa-share-nodes', 'var(--dairy-info)'],
			['Hallazgos', s.issues, s.routes_api + ' endpoints API', 'fa-triangle-exclamation', 'var(--dairy-warning)']
		];
		$('#sm-kpis').html(items.map(function (k)
		{
			return '<div class="col-xl-2 col-md-4 col-6"><div class="dairy-kpi-card dairy-kpi-uniform" style="--kpi-color:' + k[4] + ';"><div class="d-flex align-items-center mb-2"><div class="dairy-kpi-icon-box mr-2" style="background:var(--dairy-surface-subtle, #f8fafc);"><i class="fa-solid ' + k[3] + '" style="color:' + k[4] + ';"></i></div><div class="dairy-kpi-title mb-0">' + esc(k[0]) + '</div></div><div class="dairy-kpi-value">' + esc(k[1]) + '</div><div class="dairy-kpi-footer"><span>' + esc(k[2]) + '</span></div></div></div>';
		}).join(''));
		$('#sm-meta').html('<span class="sm-pill"><i class="fa-solid fa-clock"></i> ' + esc(evidenceLine()) + '</span><span class="sm-pill">' + esc(s.services) + ' servicios · ' + esc(s.tables) + ' tablas · ' + esc(s.migrations) + ' migraciones · ' + esc(s.tests) + ' archivos de prueba</span>');
	}
	function legend()
	{
		var h = Object.keys(ST).map(function (k) { return '<span><i class="sm-sw" style="border-color:' + ST[k].color + ';background:' + ST[k].bg + '"></i>' + ST[k].label + '</span>'; }).join('');
		h += '<span><i class="sm-ln" style="border-color:#475569"></i>Conexión verificada en código</span><span><i class="sm-ln" style="border-top-style:dashed;border-color:' + ST.proposed.color + '"></i>Propuesta (no implementada)</span>';
		h += '<span class="text-muted">Clic: detalles · clic en (+): expandir · rueda/pellizco: zoom · arrastrar: mover</span>';
		$('#sm-legend').html(h);
	}
	function filters()
	{
		var h = '<h6>Categoría</h6>' + S.map.categories.map(function (c)
		{
			return '<label><input type="checkbox" data-t="cat" data-k="' + esc(c.id) + '"' + (S.cats[c.id] === false ? '' : ' checked') + '><i class="sm-dot" style="background:' + c.color + '"></i>' + esc(c.name) + '</label>';
		}).join('');
		h += '<h6>Estado funcional</h6>' + ['verified', 'partial', 'error', 'unverified'].map(function (k)
		{
			return '<label><input type="checkbox" data-t="st" data-k="' + k + '"' + (S.sts[k] === false ? '' : ' checked') + '><i class="sm-dot" style="background:' + ST[k].color + '"></i>' + ST[k].label + '</label>';
		}).join('');
		$('#sm-filters').html(h);
	}
	function load(map)
	{
		S.map = map;
		S.mods = {}; S.catById = {}; S.issues = {};
		map.modules.forEach(function (m) { S.mods[m.id] = m; });
		map.categories.forEach(function (c) { S.catById[c.id] = c; });
		map.issues.forEach(function (i) { S.issues[i.id] = i; });
		$('#sm-loading').addClass('d-none');
		kpis(); legend(); filters();
		S.needFit = true;
		render();
		if (!S.selected) showRoot();
	}

	var h = (location.hash || '').replace('#', '');
	if (h === 'conn' || h === 'audit')
	{
		S.view = h;
		$('.sm-views .nav-link').removeClass('active').filter('[data-view="' + h + '"]').addClass('active');
	}
	Grocy.Api.Get('system-map', load, function (xhr)
	{
		var msg = 'No se pudo cargar el mapa';
		try { msg = JSON.parse(xhr.responseText).error_message || msg; } catch (ex) { }
		$('#sm-loading').html('<span class="text-danger"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(msg) + '</span>');
	});
	$(window).on('resize', function () { if (S.view !== 'audit') fitView(); });
})();
