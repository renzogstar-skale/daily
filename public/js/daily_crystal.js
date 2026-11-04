/* DAILY Crystal: marca como .crystal-surface las superficies blancas opacas con
   radio (tarjetas de cada módulo) para que reciban el cristal global sin tocar
   cada vista. Solo agrega clases; no altera datos, eventos ni estructura. */
(function ()
{
	if (!document.body.classList.contains('crystal-ui')) return;

	var SKIP = 'table, thead, tbody, tr, td, th, .modal, .dropdown-menu, .daterangepicker, svg, canvas, .dsm-fdd-menu, #mainNav, .navbar-sidenav, .leaflet-container';
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
		var nodes = root.querySelectorAll ? root.querySelectorAll('div, section, article, aside, form, li, a') : [];
		var list = [root].concat(Array.prototype.slice.call(nodes));
		for (var i = 0; i < list.length; i++)
		{
			var el = list[i];
			if (!el || el.nodeType !== 1 || !TAGS[el.tagName] || el.classList.contains('crystal-surface') || el.hasAttribute('data-crystal-skip')) continue;
			if (el.closest(SKIP)) continue;
			var cs = getComputedStyle(el);
			if (cs.backgroundImage !== 'none') continue;
			// Lienzos de página opacos (envoltorios a todo el ancho): transparentes para que se vea el fondo ambiental
			if (isLight(cs.backgroundColor) && (parseFloat(cs.borderTopLeftRadius) || 0) < 6 && el.offsetWidth >= canvasMin && cs.position !== 'sticky' && cs.position !== 'fixed' && !el.closest('.crystal-surface, .card'))
			{
				el.classList.add('crystal-canvas');
				continue;
			}
			if (!isWhite(cs.backgroundColor)) continue;
			if ((parseFloat(cs.borderTopLeftRadius) || 0) < 6) continue;
			if (el.offsetWidth < 120 || el.offsetHeight < 44) continue;

			el.classList.add('crystal-surface');
			var parent = el.parentElement && el.parentElement.closest('.crystal-surface, .card');
			if (parent) el.classList.add('crystal-surface--nested');
			if (cs.position === 'fixed' || cs.position === 'sticky') el.classList.add('crystal-surface--floating');
			else if (!parent && (el.tagName === 'A' || cs.cursor === 'pointer')) el.classList.add('crystal-surface--interactive');
		}
	}

	function run() { tag(document.getElementById('page-content') || document.body); }

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
	window.addEventListener('load', run);

	// Contenido que llega por AJAX: se procesa en lotes, en tiempo libre.
	var pending = [], timer = null;
	var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 60); };
	new MutationObserver(function (muts)
	{
		for (var i = 0; i < muts.length; i++)
		{
			var added = muts[i].addedNodes;
			for (var j = 0; j < added.length; j++) if (added[j].nodeType === 1) pending.push(added[j]);
		}
		if (pending.length && !timer)
		{
			timer = setTimeout(function ()
			{
				idle(function ()
				{
					var batch = pending; pending = []; timer = null;
					for (var k = 0; k < batch.length; k++) if (batch[k].isConnected) tag(batch[k]);
				});
			}, 120);
		}
	}).observe(document.getElementById('page-content') || document.body, { childList: true, subtree: true });

	window.DailyCrystal = { refresh: run };
})();
