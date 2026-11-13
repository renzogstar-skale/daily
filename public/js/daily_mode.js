/* DAILY — modo Día / Noche global (DailyTheme).
   Estado único para todas las páginas: <html class="daily-dark"> (alias heredados
   html.daily-night y body.night-mode). Preferencia 'light' | 'dark' | 'system' en
   localStorage + cookie daily_mode (el servidor la usa para pintar la clase en el
   primer byte) + ajuste de usuario night_mode (otros dispositivos).

   Adaptador: los módulos traen colores fijos (#fff, #f8fafc, #1f2937...) que los
   tokens no alcanzan. En modo noche se marcan, sin tocar datos ni estructura:
     .dk-surface / .dk-raise  fondos claros neutros  → superficies oscuras
     .dk-tint (+ --dk-tint)   fondos pastel          → mismo tono, translúcido
     .dk-ink (+ --dk-c)       textos sin contraste    → versión clara del mismo tono
     .dk-bt/br/bb/bl          bordes claros           → borde sutil
     .dk-fill / .dk-stroke    SVG en línea
   Todas las reglas viven bajo html.daily-dark: al volver a Día no queda rastro. */
(function (w, d)
{
	var root = d.documentElement;
	var KEY = 'daily_mode';
	var mq = w.matchMedia ? w.matchMedia('(prefers-color-scheme: dark)') : null;
	var reduceMq = w.matchMedia ? w.matchMedia('(prefers-reduced-motion: reduce)') : null;

	function valid(v) { return v === 'dark' || v === 'light' || v === 'system'; }

	function readPref()
	{
		var v = null;
		try { v = localStorage.getItem(KEY); } catch (e) { }
		if (!valid(v))
		{
			var c = d.cookie.match(/(?:^|;\s*)daily_mode=(dark|light|system)/);
			v = c ? c[1] : w.DAILY_MODE_SERVER;
		}
		return valid(v) ? v : 'system';
	}

	function resolve(p) { return p === 'system' ? (mq && mq.matches ? 'dark' : 'light') : p; }

	var pref = readPref();
	var mode = resolve(pref);

	function syncControls()
	{
		var dark = mode === 'dark';
		var btns = d.querySelectorAll('[data-daily-mode-toggle]');
		for (var i = 0; i < btns.length; i++)
		{
			btns[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
			btns[i].setAttribute('aria-label', dark ? 'Cambiar a modo día' : 'Cambiar a modo noche');
			btns[i].setAttribute('title', dark ? 'Modo noche activo · cambiar a modo día' : 'Modo día activo · cambiar a modo noche');
		}
		var meta = d.getElementById('metaThemeColor');
		if (meta)
		{
			if (!meta.hasAttribute('data-day-color')) meta.setAttribute('data-day-color', meta.getAttribute('content') || '#00C83B');
			meta.setAttribute('content', dark ? '#0b1220' : meta.getAttribute('data-day-color'));
		}
	}

	function paint(m)
	{
		var dark = m === 'dark';
		root.classList.toggle('daily-dark', dark);
		root.classList.toggle('daily-night', dark);
		root.style.colorScheme = dark ? 'dark' : 'light';
		if (d.body) d.body.classList.toggle('night-mode', dark);
		syncControls();
	}

	paint(mode);

	function save(p)
	{
		try { localStorage.setItem(KEY, p); } catch (e) { }
		d.cookie = 'daily_mode=' + p + '; path=/; max-age=31536000; SameSite=Lax';
		try
		{
			if (w.Grocy && Grocy.UserId > 0 && Grocy.FrontendHelpers && Grocy.FrontendHelpers.SaveUserSetting)
			{
				var v = p === 'dark' ? 'on' : (p === 'light' ? 'off' : 'follow-system');
				if (!Grocy.UserSettings || Grocy.UserSettings.night_mode !== v) Grocy.FrontendHelpers.SaveUserSetting('night_mode', v);
			}
		}
		catch (e) { }
	}

	function emit()
	{
		var ev;
		try { ev = new CustomEvent('daily:modechange', { detail: { mode: mode, preference: pref } }); }
		catch (e) { ev = d.createEvent('CustomEvent'); ev.initCustomEvent('daily:modechange', false, false, { mode: mode, preference: pref }); }
		d.dispatchEvent(ev);
	}

	// Cambio inmediato sin recargar: un fundido de 200 ms (View Transitions) o, sin
	// soporte, transiciones de color cortas. Con movimiento reducido: instantáneo.
	function apply(m, animate)
	{
		if (m === mode && root.classList.contains('daily-dark') === (m === 'dark')) { syncControls(); return; }
		mode = m;
		var run = function ()
		{
			paint(m);
			if (m === 'dark') adapt(d.body, true);
			else if (w.DailyCrystal) w.DailyCrystal.refresh();
			emit();
		};
		var reduce = reduceMq && reduceMq.matches;
		if (!animate || reduce || !d.body) { run(); return; }
		if (d.startViewTransition && !d.hidden)
		{
			root.classList.add('daily-mode-vt');
			var t;
			try { t = d.startViewTransition(run); }
			catch (e) { root.classList.remove('daily-mode-vt'); run(); return; }
			var done = function () { root.classList.remove('daily-mode-vt'); };
			t.finished.then(done, done);
			return;
		}
		root.classList.add('daily-mode-anim');
		run();
		setTimeout(function () { root.classList.remove('daily-mode-anim'); }, 260);
	}

	function setPreference(p, opts)
	{
		if (!valid(p)) return;
		opts = opts || {};
		pref = p;
		if (opts.persist !== false) save(p);
		apply(resolve(p), opts.animate !== false);
	}

	if (mq)
	{
		var onSystem = function () { if (pref === 'system') apply(resolve('system'), true); };
		if (mq.addEventListener) mq.addEventListener('change', onSystem); else if (mq.addListener) mq.addListener(onSystem);
	}

	// Otras pestañas / iframes de DAILY: mismo tema al instante
	w.addEventListener('storage', function (e)
	{
		if (e.key !== KEY || !valid(e.newValue)) return;
		pref = e.newValue;
		apply(resolve(pref), false);
	});

	// Página restaurada desde la caché de avance/retroceso con otro tema
	w.addEventListener('pageshow', function (e)
	{
		if (!e.persisted) return;
		var p = readPref();
		if (p !== pref || resolve(p) !== mode) { pref = p; apply(resolve(p), false); }
	});

	d.addEventListener('click', function (e)
	{
		var b = e.target.closest && e.target.closest('[data-daily-mode-toggle]');
		if (!b) return;
		e.preventDefault();
		w.DailyTheme.toggle();
	});

	/* ======================================================================
	   Adaptador de colores fijos (solo modo noche)
	   ====================================================================== */
	var SKIP = 'canvas, img, video, picture, iframe, object, embed, script, style, noscript, template, .leaflet-container, [data-dk-skip], .dk-keep';
	var SVG_SHAPES = { text: 1, tspan: 1, rect: 1, circle: 1, ellipse: 1, path: 1, line: 1, polyline: 1, polygon: 1 };
	var DK = ['dk-surface', 'dk-raise', 'dk-tint', 'dk-ink', 'dk-bt', 'dk-br', 'dk-bb', 'dk-bl', 'dk-fill', 'dk-fill-bg', 'dk-stroke'];
	var MAX = 25000;

	function parse(c)
	{
		var m = c && c.match(/^rgba?\((\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?)(?:,\s*([\d.]+))?\)$/);
		return m ? [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]] : null;
	}

	function lum(c)
	{
		var f = function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
		return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
	}

	function hsl(c)
	{
		var r = c[0] / 255, g = c[1] / 255, b = c[2] / 255;
		var mx = Math.max(r, g, b), mn = Math.min(r, g, b), h = 0, s = 0, l = (mx + mn) / 2;
		if (mx !== mn)
		{
			var dd = mx - mn;
			s = l > 0.5 ? dd / (2 - mx - mn) : dd / (mx + mn);
			h = mx === r ? (g - b) / dd + (g < b ? 6 : 0) : (mx === g ? (b - r) / dd + 2 : (r - g) / dd + 4);
			h *= 60;
		}
		return [h, s, l];
	}

	// Clasificación de un fondo: 'surface' (blanco), 'raise' (gris claro), 'tint' (pastel) o null
	function bgKind(c)
	{
		if (!c || c[3] < 0.35) return null;
		var x = hsl(c);
		if (x[1] < 0.14 || (x[2] > 0.9 && x[1] < 0.3))
		{
			if (x[2] >= 0.93) return 'surface';
			if (x[2] >= 0.8) return 'raise';
			return null;
		}
		return x[2] >= 0.84 ? 'tint' : null;
	}

	function tintOf(c)
	{
		var x = hsl(c);
		return 'hsla(' + Math.round(x[0]) + ', ' + Math.round(Math.min(90, Math.max(45, x[1] * 100))) + '%, 50%, ' + (c[3] < 0.7 ? 0.12 : 0.16) + ')';
	}

	function contrast(a, b)
	{
		var la = lum(a), lb = lum(b);
		return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
	}

	// Primer color utilizable de un degradado (ignora reflejos casi transparentes)
	function gradColor(img)
	{
		if (!img || img === 'none' || img.indexOf('gradient') < 0) return null;
		var re = /rgba?\([^)]+\)/g, m;
		while ((m = re.exec(img)))
		{
			var c = parse(m[0]);
			if (c && c[3] >= 0.35) return c;
		}
		return null;
	}

	function gradAllLight(img)
	{
		var re = /rgba?\([^)]+\)/g, m, n = 0;
		while ((m = re.exec(img)))
		{
			var c = parse(m[0]);
			if (!c || c[3] < 0.2) continue;
			n++;
			var k = bgKind(c);
			if (k !== 'surface' && k !== 'raise') return false;
		}
		return n > 0 && img.indexOf('url(') < 0;
	}

	var bodyBg = [11, 18, 32, 1];
	var bgCache = null;

	// Fondo efectivo detrás de un elemento (tras aplicar el modo noche)
	function effBg(el)
	{
		var chain = [];
		for (var p = el; p && p.nodeType === 1; p = p.parentElement)
		{
			if (bgCache.has(p)) { var hit = bgCache.get(p); for (var j = 0; j < chain.length; j++) bgCache.set(chain[j], hit); return hit; }
			chain.push(p);
			var cs = getComputedStyle(p);
			var c = parse(cs.backgroundColor);
			var res = null;
			if (c && c[3] >= 0.35) res = c;
			else
			{
				var img = cs.backgroundImage;
				if (img && img !== 'none')
				{
					if (img.indexOf('url(') >= 0) res = false; // imagen: no se adivina el contraste
					else res = gradColor(img);
				}
			}
			if (res !== null)
			{
				for (var k = 0; k < chain.length; k++) bgCache.set(chain[k], res);
				return res;
			}
		}
		for (var q = 0; q < chain.length; q++) bgCache.set(chain[q], bodyBg);
		return bodyBg;
	}

	function hasText(el)
	{
		var t = el.tagName;
		if (t === 'INPUT' || t === 'SELECT' || t === 'TEXTAREA') return true;
		for (var n = el.firstChild; n; n = n.nextSibling) if (n.nodeType === 3 && n.nodeValue.trim()) return true;
		return false;
	}

	function lighter(c, target)
	{
		var x = hsl(c);
		var s = x[1] < 0.14 ? 0 : Math.min(0.85, Math.max(0.45, x[1]));
		return 'hsl(' + Math.round(x[0]) + ', ' + Math.round(s * 100) + '%, ' + Math.round(target * 100) + '%)';
	}

	// Sin cajas propias: display:none (se omite) o display:contents (sus hijos sí tienen cajas). Se decide por
	// geometría: getComputedStyle sobre un elemento display:none no se cachea y cuesta ~7 ms en páginas grandes;
	// con las tablas → tarjetas del móvil (miles de celdas de detalle ocultas) congelaba /mrp en modo noche.
	function contentsBox(el)
	{
		for (var c = el.firstElementChild; c; c = c.nextElementSibling) if (c.getClientRects().length) return true;
		return false;
	}

	function collect(start)
	{
		var list = [];
		(function walk(el)
		{
			if (list.length >= MAX) return;
			if (el.matches(SKIP)) return;
			var isSvg = el.namespaceURI === 'http://www.w3.org/2000/svg';
			if (!isSvg && !el.getClientRects().length && !contentsBox(el)) return;
			list.push(el);
			for (var c = el.firstElementChild; c; c = c.nextElementSibling) walk(c);
		})(start);
		return list;
	}

	var observer = null;

	// Dos fases por pasada: primero solo lecturas y luego las clases (un solo recálculo
	// de estilos). Pasada 1: fondos y bordes; pasada 2: textos contra el fondo ya oscuro.
	function adapt(start)
	{
		if (!root.classList.contains('daily-dark') || !start || start.nodeType !== 1) return;
		if (start !== d.body && start.closest && start.closest(SKIP)) return;
		var list = collect(start);
		var writes = [];
		var i, el, cs, cls;

		for (i = 0; i < list.length; i++)
		{
			el = list[i];
			cs = getComputedStyle(el);
			cls = null;
			if (el.namespaceURI === 'http://www.w3.org/2000/svg')
			{
				var tag = el.tagName.toLowerCase();
				if (!SVG_SHAPES[tag]) continue;
				var fill = parse(cs.fill), stroke = parse(cs.stroke);
				if (tag !== 'text' && tag !== 'tspan')
				{
					var fk = bgKind(fill);
					if ((fk === 'surface' || fk === 'raise') && !el.classList.contains('dk-fill-bg')) (cls = cls || []).push('dk-fill-bg');
				}
				var sk = bgKind(stroke);
				if (stroke && cs.stroke !== 'none' && (sk === 'surface' || sk === 'raise') && !el.classList.contains('dk-stroke')) (cls = cls || []).push('dk-stroke');
				if (cls) writes.push(el, cls, null);
				continue;
			}
			var vars = null;
			if (!el.classList.contains('dk-surface') && !el.classList.contains('dk-raise') && !el.classList.contains('dk-tint'))
			{
				var bg = parse(cs.backgroundColor);
				var img = cs.backgroundImage;
				var kind = null;
				if (img && img !== 'none')
				{
					if (gradAllLight(img)) kind = (bg && bgKind(bg)) || 'surface';
					else if (bg && bg[3] >= 0.35 && img.indexOf('url(') < 0 && !gradColor(img)) kind = bgKind(bg);
				}
				else kind = bgKind(bg);
				if (kind && (el.offsetWidth >= 8 && el.offsetHeight >= 8 || el.tagName === 'TR' || el.tagName === 'TD' || el.tagName === 'TH'))
				{
					if (kind === 'tint') { cls = ['dk-tint']; vars = { '--dk-tint': tintOf(bg || gradColor(img)) }; }
					else cls = [kind === 'surface' ? 'dk-surface' : 'dk-raise'];
				}
			}
			var sides = ['Top', 'Right', 'Bottom', 'Left'], names = ['dk-bt', 'dk-br', 'dk-bb', 'dk-bl'];
			for (var s = 0; s < 4; s++)
			{
				if (el.classList.contains(names[s]) || parseFloat(cs['border' + sides[s] + 'Width']) <= 0 || cs['border' + sides[s] + 'Style'] === 'none') continue;
				var bk = bgKind(parse(cs['border' + sides[s] + 'Color']));
				if (bk === 'surface' || bk === 'raise') (cls = cls || []).push(names[s]);
			}
			if (cls) writes.push(el, cls, vars);
		}
		flush(writes);

		// Pasada 2: textos
		bgCache = new Map();
		writes = [];
		for (i = 0; i < list.length; i++)
		{
			el = list[i];
			if (el.classList.contains('dk-ink') || el.classList.contains('dk-fill')) continue;
			var svg = el.namespaceURI === 'http://www.w3.org/2000/svg';
			if (svg && el.tagName !== 'text' && el.tagName !== 'tspan') continue;
			if (!svg && !hasText(el)) continue;
			cs = getComputedStyle(el);
			var col = parse(svg ? cs.fill : cs.color);
			if (!col || col[3] < 0.5 || (cs.webkitTextFillColor && /rgba\(0, 0, 0, 0\)|transparent/.test(cs.webkitTextFillColor))) continue;
			var back = effBg(el);
			if (!back) continue;
			if (lum(back) > 0.32) continue; // texto sobre un fondo de color fuerte o claro que se conserva
			if (contrast(col, back) >= 4.3) continue;
			var x = hsl(col);
			// Grises: niveles del tema (principal / secundario / atenuado); colores: mismo tono aclarado
			var target = x[1] < 0.14 ? (x[2] < 0.3 ? 0.92 : (x[2] < 0.45 ? 0.82 : 0.7)) : 0.72;
			writes.push(el, [svg ? 'dk-fill' : 'dk-ink'], { '--dk-c': lighter(col, target) });
		}
		bgCache = null;
		flush(writes);
	}

	function flush(writes)
	{
		for (var w2 = 0; w2 < writes.length; w2 += 3)
		{
			var el = writes[w2];
			el.classList.add.apply(el.classList, writes[w2 + 1]);
			var v = writes[w2 + 2];
			if (v) for (var k in v) el.style.setProperty(k, v[k]);
		}
		// Las clases propias no deben disparar otra pasada
		if (observer) observer.takeRecords();
	}

	function strip(el)
	{
		// classList.remove escribe el atributo aunque la clase no esté: solo se toca si hay algo que quitar
		for (var i = 0; i < DK.length; i++) if (el.classList.contains(DK[i])) el.classList.remove(DK[i]);
		el.style.removeProperty('--dk-c');
		el.style.removeProperty('--dk-tint');
	}

	// Contenido nuevo (AJAX, modales, menús, pestañas): se procesa en el siguiente cuadro,
	// antes de pintarse, para que no aparezca un instante en blanco.
	var queue = [], raf = 0;
	function enqueue(el)
	{
		queue.push(el);
		if (!raf) raf = (w.requestAnimationFrame || setTimeout)(flushQueue);
	}
	function flushQueue()
	{
		raf = 0;
		var batch = queue; queue = [];
		if (!root.classList.contains('daily-dark')) return;
		// Cambios masivos (p. ej. tablas → tarjetas en móvil marcan miles de celdas): una sola pasada sobre el body.
		// Procesarlos uno a uno fuerza un recálculo de estilos por elemento y congela páginas grandes (/mrp).
		if (batch.length > 150) { adapt(d.body); return; }
		var seen = new Set();
		for (var i = 0; i < batch.length; i++)
		{
			var el = batch[i];
			if (!el.isConnected || seen.has(el)) continue;
			var covered = false;
			for (var p = el.parentElement; p; p = p.parentElement) if (seen.has(p)) { covered = true; break; }
			if (covered) continue;
			seen.add(el);
			adapt(el);
		}
	}

	function observe()
	{
		if (observer || !w.MutationObserver) return;
		observer = new MutationObserver(function (muts)
		{
			if (!root.classList.contains('daily-dark')) return;
			for (var i = 0; i < muts.length; i++)
			{
				var m = muts[i];
				if (m.type === 'childList')
				{
					for (var j = 0; j < m.addedNodes.length; j++)
					{
						var n = m.addedNodes[j];
						if (n.nodeType === 1) enqueue(n);
						else if (n.nodeType === 3 && m.target.nodeType === 1) enqueue(m.target);
					}
				}
				else if (m.target.nodeType === 1 && m.target.tagName !== 'CANVAS' && m.target.tagName !== 'HTML' && m.target.tagName !== 'BODY')
				{
					// La vista cambió el estado del elemento (activo, abierto...): se reevalúa desde cero
					strip(m.target);
					enqueue(m.target);
				}
			}
			// Lo que strip() acaba de cambiar no es un cambio de la vista: sin esto el observador se realimenta
			// sin fin (la página nunca termina de cargar, la pestaña crece a varios GB y nada responde)
			observer.takeRecords();
		});
		observer.observe(d.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
	}

	function boot()
	{
		paint(mode);
		observe();
		if (mode === 'dark') adapt(d.body);
	}

	w.DailyTheme = {
		mode: function () { return mode; },
		preference: function () { return pref; },
		set: function (p, opts) { setPreference(p, opts); },
		toggle: function () { setPreference(mode === 'dark' ? 'light' : 'dark'); },
		// Pasada síncrona: el layout la llama antes de #page-content-end, mientras el primer
		// cuadro sigue bloqueado, así una página en modo noche no se pinta primero en blanco.
		boot: boot,
		refresh: function (el) { if (mode === 'dark') adapt(el || d.body); }
	};

	// Sin la llamada del layout (p. ej. otras plantillas), arranca al terminar el HTML
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { if (!observer) boot(); });
	else boot();
	// Pestañas, desplegables y modales de Bootstrap (eventos de jQuery)
	d.addEventListener('DOMContentLoaded', function ()
	{
		if (!w.jQuery) return;
		jQuery(d).on('shown.bs.tab shown.bs.collapse shown.bs.modal shown.bs.dropdown', function (e)
		{
			if (mode === 'dark') enqueue((e.target.closest && e.target.closest('.modal, .dropdown, .collapse, .tab-content, .tab-pane')) || d.body);
		});
	});
	w.addEventListener('load', function ()
	{
		var idle = w.requestIdleCallback || function (fn) { return setTimeout(fn, 60); };
		idle(function () { if (mode === 'dark') adapt(d.body); }, { timeout: 1000 });
	});
})(window, document);
