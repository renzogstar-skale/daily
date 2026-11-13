/* ==========================================================================
   DAILY · Capa móvil (< 768 px): filtros compactos, indicadores esenciales y gráficos táctiles.
   Solo reorganiza lo que ya existe (nada se elimina) y se desactiva al volver a escritorio.
   Complementa dairy_mobile.js (drawer, tablas → tarjetas, teclado, escáner).
   ========================================================================== */
(function() {
	'use strict';
	var mq = window.matchMedia('(max-width: 767.98px)');
	// Ajuste a la pantalla también en tableta (≤ 991 px o táctil hasta 1199 px); igual que la consulta de daily_mobile.css
	var mqFit = window.matchMedia('(max-width: 991.98px), (pointer: coarse) and (max-width: 1199.98px)');
	var es = (document.documentElement.lang || 'es').indexOf('en') !== 0;

	/* ---------- Filtros: una barra «Filtros · n» que abre los controles originales como hoja inferior ----------
	   Se detecta el primer grupo de filtros del dashboard (≥ 2 selectores o selector + fecha). Los controles no se
	   mueven de su sitio en el DOM: la misma caja pasa a ser una hoja fija, así sus eventos y estilos siguen igual. */
	var FILTER_BOX = '[class*="filter"], [class*="filtro"], [id*="filter"], [id*="Filter"], form[role="search"]';
	function findFilterBox() {
		var pc = document.getElementById('page-content');
		if (!pc) { return null; }
		var selects = pc.querySelectorAll('select, input[type="date"], input[type="month"], input[name*="date"], input[id*="date"], input[id*="range"]');
		for (var i = 0; i < selects.length; i++) {
			var s = selects[i];
			if (s.closest('.modal, .dm-filter-sheet, table, .dataTables_wrapper, .dairy-sheet') || !s.offsetParent) { continue; }
			// Caja de filtros más externa que todavía sea «filtro» y quede en la parte alta de la página
			var box = null;
			for (var p = s.parentElement; p && p !== pc; p = p.parentElement) {
				if (p.matches(FILTER_BOX) && p.querySelectorAll('select, input:not([type=hidden]):not([type=checkbox]):not([type=radio])').length >= 2) { box = p; }
			}
			if (box && box.getBoundingClientRect().top + window.scrollY < window.innerHeight * 1.6 && box.getBoundingClientRect().height > 60) { return box; }
		}
		return null;
	}
	function activeCount(box) {
		var n = 0;
		box.querySelectorAll('select').forEach(function(s) { if (s.selectedIndex > 0) { n++; } });
		return n;
	}
	function summary(box) {
		var parts = [];
		box.querySelectorAll('select').forEach(function(s) {
			if (s.offsetParent === null && !box.classList.contains('dm-filter-sheet')) { return; }
			var o = s.options[s.selectedIndex];
			if (o && o.text.trim()) { parts.push(o.text.trim()); }
		});
		return parts.slice(0, 3).join(' · ');
	}
	var filterBox = null, filterBar = null, backdrop = null;
	function closeFilters() {
		if (!filterBox) { return; }
		filterBox.classList.remove('is-open');
		document.body.classList.remove('dm-filters-open');
		if (filterBar) { filterBar.querySelector('button').setAttribute('aria-expanded', 'false'); refreshBar(); }
	}
	function refreshBar() {
		if (!filterBar || !filterBox) { return; }
		var n = activeCount(filterBox);
		filterBar.querySelector('.dm-filter-count').textContent = n ? String(n) : '';
		filterBar.querySelector('.dm-filter-summary').textContent = summary(filterBox);
	}
	function setupFilters() {
		if (!mq.matches || filterBox || document.body.classList.contains('embedded')) { return; }
		var box = findFilterBox();
		if (!box || box.hasAttribute('data-dm-keep')) { return; }
		filterBox = box;
		box.classList.add('dm-filter-sheet');
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-label', es ? 'Filtros' : 'Filters');
		var head = document.createElement('div');
		head.className = 'dm-filter-head';
		head.innerHTML = '<strong>' + (es ? 'Filtros' : 'Filters') + '</strong><button type="button" class="btn btn-primary btn-sm dm-filter-done">' + (es ? 'Listo' : 'Done') + '</button>';
		box.insertBefore(head, box.firstChild);
		filterBar = document.createElement('div');
		filterBar.className = 'dm-filter-bar';
		filterBar.innerHTML = '<button type="button" class="dm-filter-btn" aria-expanded="false"><i class="fa-solid fa-sliders"></i><span>' + (es ? 'Filtros' : 'Filters') + '</span><b class="dm-filter-count"></b></button><span class="dm-filter-summary"></span>';
		box.parentNode.insertBefore(filterBar, box);
		backdrop = document.createElement('div');
		backdrop.className = 'dm-filter-backdrop';
		document.body.appendChild(backdrop);
		filterBar.querySelector('button').addEventListener('click', function() {
			box.classList.add('is-open');
			document.body.classList.add('dm-filters-open');
			this.setAttribute('aria-expanded', 'true');
			var first = box.querySelector('select, input:not([type=hidden])');
			if (first && first.focus) { first.focus({ preventScroll: true }); }
		});
		head.querySelector('.dm-filter-done').addEventListener('click', closeFilters);
		backdrop.addEventListener('click', closeFilters);
		box.addEventListener('change', function() { setTimeout(refreshBar, 0); });
		document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeFilters(); } });
		refreshBar();
	}
	function teardownFilters() {
		if (!filterBox) { return; }
		closeFilters();
		filterBox.classList.remove('dm-filter-sheet');
		filterBox.removeAttribute('role');
		var head = filterBox.querySelector('.dm-filter-head');
		if (head) { head.remove(); }
		if (filterBar) { filterBar.remove(); }
		if (backdrop) { backdrop.remove(); }
		filterBox = filterBar = backdrop = null;
	}

	/* ---------- Indicadores esenciales: en cada rejilla de KPI se ven los 4 primeros; el resto tras «Ver más» ---------- */
	var KPI_RE = /(^|\s)[a-z0-9_-]*kpi(-card|-tile|-box|-item)?(\s|$)|(^|\s)kpi-hero-card(\s|$)/i;
	function isKpi(el) {
		if (!el || el.nodeType !== 1) { return false; }
		if (KPI_RE.test(el.className || '')) { return true; }
		var c = el.firstElementChild;
		return !!c && el.childElementCount === 1 && KPI_RE.test(c.className || '');
	}
	function setupKpis() {
		if (!mq.matches) { return; }
		var pc = document.getElementById('page-content');
		if (!pc) { return; }
		var seen = [];
		pc.querySelectorAll('[class*="kpi"]').forEach(function(k) {
			var grid = isKpi(k) ? k.parentElement : null;
			if (!isKpi(k) && k.parentElement && isKpi(k.parentElement)) { return; }
			if (!grid || seen.indexOf(grid) >= 0 || grid.hasAttribute('data-dm-kpis') || grid.closest('.modal, table, .dm-filter-sheet')) { return; }
			seen.push(grid);
			var items = Array.prototype.filter.call(grid.children, isKpi);
			if (items.length < 6 || items.length < grid.childElementCount * 0.7) { return; }
			grid.setAttribute('data-dm-kpis', '1');
			grid.classList.add('dm-kpi-collapsed');
			items.slice(4).forEach(function(i) { i.classList.add('dm-kpi-extra'); });
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'dm-kpi-more';
			btn.setAttribute('aria-expanded', 'false');
			var more = items.length - 4;
			var label = function(open) { return open ? (es ? 'Ver menos indicadores' : 'Show fewer indicators') : (es ? 'Ver ' + more + ' indicadores más' : 'Show ' + more + ' more indicators'); };
			btn.innerHTML = '<span>' + label(false) + '</span><i class="fa-solid fa-chevron-down"></i>';
			btn.addEventListener('click', function() {
				var open = grid.classList.toggle('dm-kpi-collapsed') === false;
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				btn.firstChild.textContent = label(open);
				if (open) { window.dispatchEvent(new Event('resize')); } // gráficos dentro de KPI se redibujan
			});
			grid.parentNode.insertBefore(btn, grid.nextSibling);
		});
	}
	function teardownKpis() {
		document.querySelectorAll('[data-dm-kpis]').forEach(function(g) {
			g.removeAttribute('data-dm-kpis');
			g.classList.remove('dm-kpi-collapsed');
			g.querySelectorAll('.dm-kpi-extra').forEach(function(i) { i.classList.remove('dm-kpi-extra'); });
			if (g.nextElementSibling && g.nextElementSibling.classList.contains('dm-kpi-more')) { g.nextElementSibling.remove(); }
		});
	}

	/* ---------- Gráficos Chart.js 2.9: leyenda abajo, toque por índice, ejes sin rotar y fuentes legibles ---------- */
	if (window.Chart && Chart.plugins) {
		Chart.plugins.register({
			id: 'dailyMobile',
			beforeInit: function(chart) {
				if (!mq.matches) { return; }
				var o = chart.options || {}, type = chart.config.type;
				if (o.legend && o.legend.display !== false && o.legend.position !== 'bottom' && type !== 'doughnut' && type !== 'pie') { o.legend.position = 'bottom'; }
				if (o.legend && o.legend.labels) { o.legend.labels.boxWidth = Math.min(o.legend.labels.boxWidth || 12, 12); o.legend.labels.fontSize = Math.max(o.legend.labels.fontSize || 11, 11); }
				if (o.tooltips && /^(line|bar|horizontalBar)$/.test(type)) { o.tooltips.mode = 'index'; o.tooltips.intersect = false; }
				if (o.hover && /^(line|bar|horizontalBar)$/.test(type)) { o.hover.mode = 'index'; o.hover.intersect = false; }
				var axes = o.scales ? (o.scales.xAxes || []).concat(o.scales.yAxes || []) : [];
				axes.forEach(function(a) {
					a.ticks = a.ticks || {};
					a.ticks.autoSkip = a.ticks.autoSkip !== false;
					a.ticks.autoSkipPadding = Math.max(a.ticks.autoSkipPadding || 0, 8);
					a.ticks.maxRotation = 0;
					a.ticks.fontSize = Math.max(a.ticks.fontSize || 11, 11);
				});
			}
		});
	}

	/* ---------- Gráficos aplastados: la rejilla de escritorio (3–4 columnas) que los contiene pasa a una columna ---------- */
	function stackChartGrids() {
		var vw = document.documentElement.clientWidth;
		document.querySelectorAll('#page-content canvas').forEach(function(c) {
			var w = c.offsetWidth, h = c.offsetHeight;
			// Solo gráficos legibles (no sparklines ni anillos de tamaño fijo del diseño)
			if (!w || h < 60 || w >= Math.min(220, vw * 0.55) || (c.parentElement && /width:\s*\d+px/.test(c.parentElement.getAttribute('style') || ''))) { return; }
			for (var p = c.parentElement, depth = 0; p && p.id !== 'page-content' && depth < 6; p = p.parentElement, depth++) {
				var cs = getComputedStyle(p);
				if (p.classList.contains('dm-grid-stack') || p.classList.contains('dm-flex-stack')) { return; }
				if (cs.display === 'grid' && cs.gridTemplateColumns.split(' ').length > 1) { p.classList.add('dm-grid-stack'); window.dispatchEvent(new Event('resize')); return; }
				if (cs.display === 'flex' && cs.flexDirection === 'row' && p.childElementCount > 1 && cs.flexWrap === 'nowrap') { p.classList.add('dm-flex-stack'); window.dispatchEvent(new Event('resize')); return; }
			}
		});
	}
	function unstackChartGrids() {
		document.querySelectorAll('.dm-grid-stack, .dm-flex-stack').forEach(function(g) { g.classList.remove('dm-grid-stack', 'dm-flex-stack'); });
	}

	/* ---------- Ajuste a la pantalla: nada recortado ni fuera de ella ----------
	   Filas flex sin wrap (barras de botones, cabeceras), textos nowrap y rejillas que no encogen desbordan su caja
	   y quedan cortadas por un overflow:hidden. Se detectan por geometría (scrollWidth > clientWidth, sin estilos
	   en la búsqueda) y solo se marcan con clases; los contenedores con scroll horizontal propio se respetan. */
	var FIT_SKIP = 'table, .dairy-card-table, .table-responsive, .dataTables_wrapper, [data-dairy-scroll-x], .nav-tabs, .dairy-subnav-pills, .dm-filter-sheet, canvas, svg, .modal, .dropdown-menu, .fc, .sch-grid, [data-dm-keep]';
	function fitPass() {
		var pc = document.getElementById('page-content');
		if (!pc) { return 0; }
		var marked = 0, all = pc.getElementsByTagName('*');
		var over = [];
		for (var i = 0; i < all.length && i < 60000; i++) {
			var e = all[i];
			if (e.scrollWidth > e.clientWidth + 2 && e.clientWidth > 0) { over.push(e); }
		}
		over.forEach(function(e) {
			if (e.closest(FIT_SKIP)) { return; }
			var cs = getComputedStyle(e);
			// Scroll propio, o recorte intencional con «…» o líneas limitadas: se respeta el diseño
			var boxy = cs.display.indexOf('flex') >= 0 || cs.display.indexOf('grid') >= 0; // en flex/grid el «…» no se aplica: el texto se corta
			if (cs.overflowX === 'auto' || cs.overflowX === 'scroll' || (cs.textOverflow === 'ellipsis' && !boxy) || (cs.webkitLineClamp !== 'none' && cs.webkitLineClamp)) { return; }
			// Hijos más anchos que el contenedor (ítems flex con min-width:auto, cajas con ancho mínimo): pueden encogerse
			for (var c = e.firstElementChild; c; c = c.nextElementSibling) {
				// Tabla más ancha que su caja sin scroll: la caja pasa a desplazarse en horizontal (la tabla no se deforma)
				if (c.tagName === 'TABLE' && c.offsetWidth > e.clientWidth + 2 && !c.classList.contains('dairy-card-table')) { e.classList.add('dm-scroll-x'); marked++; continue; }
				if (c.offsetWidth > e.clientWidth + 2 && !c.classList.contains('dm-shrink') && !c.matches(FIT_SKIP)) { c.classList.add('dm-shrink'); marked++; }
			}
			if (cs.display.indexOf('flex') >= 0 && cs.flexDirection.indexOf('row') === 0 && cs.flexWrap === 'nowrap') { e.classList.add('dm-wrap'); marked++; }
			else if (cs.display.indexOf('grid') >= 0) { e.classList.add('dm-grid-fit'); marked++; }
			else if (cs.whiteSpace === 'nowrap' || cs.whiteSpace === 'pre') { e.classList.add('dm-text-wrap'); marked++; }
			else if (e.parentElement && e.firstElementChild === null) { e.classList.add('dm-text-wrap'); marked++; }
		});
		return marked;
	}
	function fitScreen() {
		// Envolver una fila puede hacer que sus hijos desborden a su vez: hasta tres pasadas
		for (var n = 0; n < 3 && fitPass() > 0; n++) { /* repetir */ }
	}
	function unfitScreen() {
		document.querySelectorAll('.dm-wrap, .dm-grid-fit, .dm-text-wrap, .dm-shrink, .dm-scroll-x').forEach(function(e) { e.classList.remove('dm-wrap', 'dm-grid-fit', 'dm-text-wrap', 'dm-shrink', 'dm-scroll-x'); });
	}

	/* ---------- Cifras grandes que se parten en dos líneas ($475,99 / 2): se reduce la fuente hasta que quepan ---------- */
	var NUM_RE = /^[\s$€£+\-−–]*[\d.,\s]+[\s%kKMB]*(u\.|US\$|USD)?\s*$/;
	function fitNumbers() {
		document.querySelectorAll('#page-content *').forEach(function(e) {
			if (e.childElementCount > 0 || !e.offsetHeight) { return; }
			var t = e.textContent;
			if (!t || t.length > 18 || !NUM_RE.test(t)) { return; }
			var cs = getComputedStyle(e), fs = parseFloat(cs.fontSize);
			if (fs < 18) { return; } // solo cifras destacadas (KPI), no celdas de tabla
			var lh = parseFloat(cs.lineHeight) || fs * 1.2;
			var size = fs;
			while ((e.offsetHeight > lh * 1.5 || (e.clientWidth > 0 && e.scrollWidth > e.clientWidth + 1)) && size > 13) {
				size -= 2;
				e.style.fontSize = size + 'px';
				lh = lh * (size / (size + 2));
			}
			if (size < fs) { e.classList.add('dm-num-fit'); e.style.whiteSpace = 'nowrap'; if (e.scrollWidth > e.clientWidth + 1) { e.style.whiteSpace = ''; } }
		});
	}
	function unfitNumbers() {
		document.querySelectorAll('.dm-num-fit').forEach(function(e) { e.classList.remove('dm-num-fit'); e.style.fontSize = ''; e.style.whiteSpace = ''; });
	}

	function apply() {
		if (mq.matches) { setupFilters(); setupKpis(); stackChartGrids(); fitNumbers(); }
		else { teardownFilters(); teardownKpis(); unstackChartGrids(); unfitNumbers(); }
		if (mqFit.matches) { fitScreen(); } else { unfitScreen(); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function() { setTimeout(apply, 0); }); }
	else { setTimeout(apply, 0); }
	// Dashboards que pintan KPI por AJAX: segundo intento cuando la página termina de cargar
	window.addEventListener('load', function() { setTimeout(apply, 400); });
	// Contenido que llega después (AJAX, pestañas, «Ver más»): nuevo ajuste agrupado. Solo se observan nodos y textos
	// nuevos; las clases y estilos que pone esta capa no vuelven a disparar el ajuste.
	if (window.MutationObserver) {
		var refit = null;
		new MutationObserver(function() {
			if (!mqFit.matches) { return; }
			clearTimeout(refit);
			refit = setTimeout(function() { if (mq.matches) { setupKpis(); stackChartGrids(); fitNumbers(); } fitScreen(); }, 450);
		}).observe(document.getElementById('page-content') || document.body, { childList: true, subtree: true, characterData: true });
	}
	document.addEventListener('shown.bs.tab', function() { if (mqFit.matches) { setTimeout(fitScreen, 50); } });
	if (window.jQuery) { jQuery(document).on('shown.bs.tab shown.bs.collapse', function() { if (mqFit.matches) { setTimeout(function() { fitScreen(); if (mq.matches) { stackChartGrids(); } }, 50); } }); }
	[mq, mqFit].forEach(function(m) { if (m.addEventListener) { m.addEventListener('change', apply); } else if (m.addListener) { m.addListener(apply); } });
})();
