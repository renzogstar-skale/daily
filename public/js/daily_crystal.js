/* DAILY Crystal: marca como .crystal-surface las superficies blancas opacas con
   radio (tarjetas de cada módulo) para que reciban el cristal global sin tocar
   cada vista. Solo agrega clases; no altera datos, eventos ni estructura. */
(function ()
{
	if (!document.body.classList.contains('crystal-ui')) return;
	if (document.body.classList.contains('daily-login-body')) return;

	var SKIP = 'table, thead, tbody, tr, td, th, .modal, .dropdown-menu, .daterangepicker, svg, canvas, .dsm-fdd-menu, #mainNav, .navbar-sidenav, .leaflet-container, .daily-login-layout, .daily-glass-card';
	var TAGS = { DIV: 1, SECTION: 1, ARTICLE: 1, ASIDE: 1, FORM: 1, LI: 1, A: 1, BUTTON: 0 };

	function isWhite(bg)
	{
		// rgb(255, 255, 255) o rgba(255, 255, 255, a) con a >= 0.9
		var m = bg.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/);
		if (!m) return false;
		var a = m[4] === undefined ? 1 : parseFloat(m[4]);
		return a >= 0.9 && +m[1] >= 250 && +m[2] >= 250 && +m[3] >= 250;
	}

	function isLight(bg)
	{
		var m = bg.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/);
		return !!m && (m[4] === undefined || parseFloat(m[4]) >= 0.9) && +m[1] >= 236 && +m[2] >= 236 && +m[3] >= 236;
	}

	var canvasMin = 0;

	function tag(root)
	{
		var pc = document.getElementById('page-content');
		canvasMin = (pc ? pc.clientWidth : window.innerWidth) * 0.85;
		if (!root || root.nodeType !== 1 || root.closest(SKIP)) return;
		// Iconos, textos y similares sin contenedores dentro no pueden ser superficies (el POS agrega ~700 <i>)
		if (!TAGS[root.tagName] && !root.querySelector('div, section, article, aside, form, li, a')) return;
		// Nodos que llegan por AJAX dentro de contenido oculto u omitido (content-visibility): medirlos forzaría su layout
		if (root.checkVisibility && !root.checkVisibility({ contentVisibilityAuto: true }) && getComputedStyle(root).display !== 'contents') return;
		// Recorrido con poda: no se entra en tablas/menús (SKIP) ni en subárboles no renderizados (pestañas
		// ocultas, display:none). Antes se pedía getComputedStyle a cada div/li/a de la página, lo que obligaba
		// a calcular estilos de pestañas ocultas (2,6 s en /suppliers) para elementos que igual se descartaban
		// por medir 0 px.
		var list = [];
		(function walk(el)
		{
			if (el !== root && el.matches(SKIP)) return;
			if (!el.getClientRects().length && getComputedStyle(el).display !== 'contents') return;
			list.push(el);
			var first = el.firstElementChild;
			if (!first) return;
			// content-visibility: auto fuera de pantalla (p. ej. tarjetas del POS): medir sus hijos forzaría
			// el layout de cada tarjeta omitida (5 s en /pos). Su contenido no se pinta, no hay nada que marcar.
			if (getComputedStyle(el).contentVisibility !== 'visible' && first.checkVisibility && !first.checkVisibility({ contentVisibilityAuto: true })) return;
			for (var c = first; c; c = c.nextElementSibling) walk(c);
		})(root);
		// Dos fases: primero solo lecturas, luego las clases (evita recalcular estilos en cada elemento)
		var writes = [];
		for (var i = 0; i < list.length; i++)
		{
			var el = list[i];
			if (!TAGS[el.tagName] || el.classList.contains('crystal-surface') || el.classList.contains('crystal-canvas') || el.hasAttribute('data-crystal-skip')) continue;
			var cs = getComputedStyle(el);
			if (cs.backgroundImage !== 'none') continue;
			var bg = cs.backgroundColor;
			if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;
			var radius = parseFloat(cs.borderTopLeftRadius) || 0;
			// Lienzos de página opacos (envoltorios a todo el ancho): transparentes para que se vea el fondo ambiental
			if (radius < 6 && isLight(bg) && el.offsetWidth >= canvasMin && cs.position !== 'sticky' && cs.position !== 'fixed' && !surfaceAncestor(el))
			{
				writes.push(el, ['crystal-canvas']);
				continue;
			}
			if (radius < 6 || !isWhite(bg)) continue;
			if (el.offsetWidth < 120 || el.offsetHeight < 44) continue;

			var cls = ['crystal-surface'];
			var parent = surfaceAncestor(el);
			if (parent) cls.push('crystal-surface--nested');
			if (cs.position === 'fixed' || cs.position === 'sticky') cls.push('crystal-surface--floating');
			else if (!parent && (el.tagName === 'A' || cs.cursor === 'pointer')) cls.push('crystal-surface--interactive');
			el.__crystal = 1;
			writes.push(el, cls);
		}
		for (var w = 0; w < writes.length; w += 2) writes[w].classList.add.apply(writes[w].classList, writes[w + 1]);
	}

	function surfaceAncestor(el)
	{
		for (var p = el.parentElement; p; p = p.parentElement)
		{
			if (p.__crystal || p.classList.contains('crystal-surface') || p.classList.contains('card')) return p;
		}
		return null;
	}

	// Blur con presupuesto: superficies muy altas o más allá de las primeras 40 van sin desenfoque
	function budget(root)
	{
		var n = 0, lite = [], all = root.querySelectorAll('.card, .crystal-surface');
		for (var i = 0; i < all.length; i++)
		{
			var el = all[i];
			if (el.classList.contains('crystal-surface--nested') || el.classList.contains('crystal-surface--lite') || (el.parentElement && el.parentElement.closest('.card, .crystal-surface'))) continue;
			if (++n > 40 || el.offsetHeight > 1400) lite.push(el);
		}
		for (var k = 0; k < lite.length; k++) lite[k].classList.add('crystal-surface--lite');
	}

	// Cifras de KPI sin dato ("Sin información", "Sin meta", "—"): se muestran como estado, no como número
	var VALUE_SEL = '[class*="kpi-value"], [class*="kpi-val"], .daily-pulse-metric-value, .today-exec-spark-val';
	function markEmpty(el)
	{
		el.classList.toggle('crystal-value--empty', !/\d/.test(el.textContent));
	}
	function markAllEmpty(root)
	{
		var els = root.querySelectorAll(VALUE_SEL);
		for (var i = 0; i < els.length; i++) markEmpty(els[i]);
	}

	function run()
	{
		var root = document.getElementById('page-content') || document.body;
		tag(root);
		budget(root);
		markAllEmpty(root);
	}

	var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 60); };
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
	window.addEventListener('load', function () { idle(run, { timeout: 800 }); });

	// Contenido que llega por AJAX: se procesa en lotes, en tiempo libre.
	var pending = [], timer = null;
	new MutationObserver(function (muts)
	{
		for (var i = 0; i < muts.length; i++)
		{
			var added = muts[i].addedNodes, t = muts[i].target;
			// .text() de jQuery reemplaza el nodo de texto de la cifra
			if (t.nodeType === 1 && t.matches(VALUE_SEL)) markEmpty(t);
			for (var j = 0; j < added.length; j++) if (added[j].nodeType === 1) { pending.push(added[j]); markAllEmpty(added[j]); if (added[j].matches(VALUE_SEL)) markEmpty(added[j]); }
		}
		if (pending.length && !timer)
		{
			timer = setTimeout(function ()
			{
				idle(function ()
				{
					var batch = pending; pending = []; timer = null;
					var touched = false;
					for (var k = 0; k < batch.length; k++)
					{
						// Nodos descendientes de otro del lote ya se recorren con él
						if (!batch[k].isConnected || (k && batch[k].parentElement && batch.indexOf(batch[k].parentElement) >= 0)) continue;
						tag(batch[k]); touched = true;
					}
					if (touched) budget(document.getElementById('page-content') || document.body);
				}, { timeout: 500 });
			}, 120);
		}
	}).observe(document.getElementById('page-content') || document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });

	// Reactividad inmediata al cambiar de pestaña o mostrar contenido oculto
	document.addEventListener('click', function (e)
	{
		var tabTrigger = e.target.closest && e.target.closest('[data-toggle="pill"], [data-toggle="tab"], .ded-nav-tab, .nav-link, [role="tab"], .daily-level-tab');
		if (tabTrigger)
		{
			setTimeout(run, 40);
			setTimeout(run, 200);
		}
	});

	if (window.jQuery)
	{
		window.jQuery(document).on('shown.bs.tab', function ()
		{
			run();
			setTimeout(run, 150);
		});
	}

	window.DailyCrystal = { refresh: run };
})();
