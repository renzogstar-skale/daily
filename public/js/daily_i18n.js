/*
 * DAILY i18n — idioma global ES / EN.
 * El español es el idioma fuente (las vistas Blade y los JS lo emiten); el catálogo
 * window.DAILY_I18N_EN (public/i18n/daily_en.js, generado desde localization/daily/en/*.json)
 * traduce cada texto por clave = texto fuente normalizado. Las cifras se convierten en
 * parámetros {0},{1}… para que «Hace 3 días» use la plantilla «Hace {0} días».
 * Traduce nodos de texto, atributos visibles, título, diálogos nativos y gráficos Chart.js
 * (sin tocar sus datos), y vuelve al español sin recargar.
 */
(function (w, d) {
	'use strict';
	var KEY = 'daily_lang';
	var ATTRS = ['title', 'placeholder', 'aria-label', 'alt', 'data-original-title', 'data-content', 'data-title', 'data-placeholder', 'label'];
	var ATTRSET = {};
	ATTRS.forEach(function (a) { ATTRSET[a] = 1; });
	var SKIP_TAGS ={ SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, CODE: 1, PRE: 1, TEXTAREA: 1, TEMPLATE: 1 };
	var LETTER = /[A-Za-zÀ-ÿ]{2}/;
	var NUM = /[-+]?\d+(?:[.,:\/]\d+)*/g;

	var cat = {};      // es → en
	var lower = {};    // es en minúsculas → en
	var rev = {};      // en → es (para volver al español en gráficos y atributos)
	var cache = {};
	var hasWild = false;
	var textOrig = new WeakMap();  // nodo de texto → {es, en}
	var attrOrig = new WeakMap();  // elemento → {attr: {es, en}}
	var missing = {};

	function readLang() {
		var l = null;
		try { l = localStorage.getItem(KEY); } catch (e) { }
		if (!l) { var m = d.cookie.match(/(?:^|;\s*)daily_lang=(es|en)/); l = m ? m[1] : null; }
		if (!l && w.Grocy && Grocy.UserSettings && Grocy.UserSettings.daily_lang) l = Grocy.UserSettings.daily_lang;
		return l === 'en' ? 'en' : 'es';
	}
	var lang = readLang();

	function norm(s) { return String(s).replace(/\s+/g, ' ').trim(); }

	function load(dict) {
		cat = dict || {}; lower = {}; rev = {}; cache = {}; hasWild = false;
		for (var k in cat) {
			if (!cat.hasOwnProperty(k)) continue;
			if (!hasWild && k.indexOf('{*}') >= 0) hasWild = true;
			lower[k.toLowerCase()] = cat[k];
			if (!rev[cat[k]]) rev[cat[k]] = k;
		}
	}

	function matchCase(src, out) {
		if (src.length > 1 && src === src.toUpperCase() && src !== src.toLowerCase()) return out.toUpperCase();
		var c = src.charAt(0);
		if (c !== c.toLowerCase() && out) return out.charAt(0).toUpperCase() + out.slice(1);
		return out;
	}

	function lookup(s) {
		if (cat.hasOwnProperty(s)) return cat[s];
		var l = s.toLowerCase();
		if (lower.hasOwnProperty(l)) return matchCase(s, lower[l]);
		return null;
	}

	// Plantilla con cifras: «12 alertas abiertas» → «{0} alertas abiertas»
	function byTemplate(s) {
		var nums = [];
		var tpl = s.replace(NUM, function (m) { nums.push(m); return '{' + (nums.length - 1) + '}'; });
		if (!nums.length) return null;
		var t = lookup(tpl);
		if (t === null) return null;
		return t.replace(/\{(\d+)\}/g, function (m, i) { return nums[+i] !== undefined ? nums[+i] : m; });
	}

	var MONTHS = { enero: 'January', febrero: 'February', marzo: 'March', abril: 'April', mayo: 'May', junio: 'June', julio: 'July', agosto: 'August', septiembre: 'September', setiembre: 'September', octubre: 'October', noviembre: 'November', diciembre: 'December',
		ene: 'Jan', feb: 'Feb', mar: 'Mar', abr: 'Apr', may: 'May', jun: 'Jun', jul: 'Jul', ago: 'Aug', sep: 'Sep', sept: 'Sep', oct: 'Oct', nov: 'Nov', dic: 'Dec' };
	var DAYS = { lunes: 'Monday', martes: 'Tuesday', 'miércoles': 'Wednesday', miercoles: 'Wednesday', jueves: 'Thursday', viernes: 'Friday', 'sábado': 'Saturday', sabado: 'Saturday', domingo: 'Sunday',
		lun: 'Mon', mar: 'Tue', 'mié': 'Wed', mie: 'Wed', jue: 'Thu', vie: 'Fri', 'sáb': 'Sat', sab: 'Sat', dom: 'Sun' };
	var MRE = '(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre|ene|feb|mar|abr|may|jun|jul|ago|sept|sep|oct|nov|dic)';
	var DRE = '(lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo|lun|mar|mié|mie|jue|vie|sáb|sab|dom)';
	var reDayMonth = new RegExp('\\b(\\d{1,2})(?:\\s+de)?\\s+' + MRE + '\\.?(?:,?\\s*(?:de\\s+|del\\s+)?(\\d{4}))?(?![A-Za-zÀ-ÿ])', 'gi');
	var reMonthYear = new RegExp('(^|[^A-Za-zÀ-ÿ])' + MRE + '\\.?(?:\\s+de)?\\s+(\\d{4})\\b', 'gi');
	var reWeekday = new RegExp('(^|[^A-Za-zÀ-ÿ])' + DRE + '\\.?(?=[\\s,]+\\d|,)', 'gi');
	function cap(src, out) { return src.charAt(0) === src.charAt(0).toUpperCase() && src === src.toUpperCase() && src.length > 1 ? out.toUpperCase() : out; }
	function dates(s) {
		var o = s.replace(reWeekday, function (m, pre, dname) { return pre + cap(dname, DAYS[dname.toLowerCase()] || dname); })
			.replace(reDayMonth, function (m, day, mon, yr) { var en = MONTHS[mon.toLowerCase()]; return en ? cap(mon, en) + ' ' + day + (yr ? ', ' + yr : '') : m; })
			.replace(reMonthYear, function (m, pre, mon, yr) { var en = MONTHS[mon.toLowerCase()]; return en ? pre + cap(mon, en) + ' ' + yr : m; });
		return o === s ? null : o;
	}

	var SEP = /(\s+[·•|—–\/→]\s+|\s+-\s+|:\s+|\s*\(\s*|\s*\)\s*|,\s+|;\s+|\s+\+\s+|\s*[=]\s*)/;

	function translateCore(s, depth) {
		var r = lookup(s);
		if (r !== null) return r;
		r = byTemplate(s);
		if (r !== null) return r;
		// Prefijo con comodín: «Bodega Seca {*}» → «Dry Storage {*}»
		if (hasWild && depth < 3) {
			var ws = s.split(' ');
			for (var k = Math.min(ws.length - 1, 6); k >= 1; k--) {
				var wk = ws.slice(0, k).join(' ') + ' {*}';
				var wv = lookup(wk);
				if (wv === null && /\d/.test(wk)) wv = byTemplate(wk);
				if (wv !== null) { var rest = ws.slice(k).join(' '), rt = translateCore(rest, depth + 1); return wv.replace('{*}', rt !== null ? rt : rest); }
			}
		}
		// Bordes sin letras (iconos, viñetas, puntuación, cifras): «• Guardar:» → «• Save:»
		var m = s.match(/^([^A-Za-zÀ-ÿ¿¡{]*)(.*?)([^A-Za-zÀ-ÿ}]*)$/);
		if (m && (m[1] || m[3]) && m[2] && m[2] !== s) {
			var core = translateCore(m[2], depth + 1);
			if (core !== null) return m[1].replace(/[¿¡]/g, '') + core + m[3];
		}
		if (depth < 3) {
			var parts = s.split(SEP);
			if (parts.length > 1) {
				var changed = false;
				for (var i = 0; i < parts.length; i += 2) {
					var p = parts[i];
					if (!p || !LETTER.test(p)) continue;
					var tp = translateCore(p, depth + 1);
					if (tp !== null && tp !== p) { parts[i] = tp; changed = true; }
				}
				var dj = parts.join('');
				var dd = dates(dj);
				if (dd !== null) return dd;
				if (changed) return dj;
			}
		}
		return dates(s);
	}

	// Traduce un texto ES → EN (o devuelve el original). Conserva espacios de los bordes.
	function t(str, params) {
		if (str === null || str === undefined) return str;
		str = String(str);
		var out = str;
		if (lang === 'en' && LETTER.test(str)) {
			var lead = str.match(/^\s*/)[0], trail = str.match(/\s*$/)[0];
			var s = norm(str);
			if (cache.hasOwnProperty(s)) out = cache[s];
			else {
				var r = translateCore(s, 0);
				if (r === null) { missing[s] = (missing[s] || 0) + 1; r = s; }
				cache[s] = r; out = r;
			}
			out = out === s ? str : lead + out + trail;
		}
		if (params) out = out.replace(/\{(\w+)\}/g, function (m, k) { return params[k] !== undefined ? params[k] : m; });
		return out;
	}
	// Valor original en español de un texto ya traducido (para gráficos/atributos)
	function toEs(s) { var n = norm(s); return rev.hasOwnProperty(n) ? rev[n] : s; }

	// skipMemo vive solo durante un lote del observador: el parser inserta miles de nodos hermanos y cada uno
	// volvía a subir por todos sus ancestros (~0,7 s por página grande en EN)
	var skipMemo = null;
	function skipEl(el) {
		var path = skipMemo ? [] : null, res = false;
		for (var e = el; e && e.nodeType === 1; e = e.parentElement) {
			if (skipMemo) { var m = skipMemo.get(e); if (m !== undefined) { res = m; break; } }
			if (SKIP_TAGS[e.tagName] || e.isContentEditable || e.hasAttribute('data-i18n-skip') || e.classList.contains('notranslate') || e.classList.contains('note-editable')) { res = true; break; }
			if (path) path.push(e);
		}
		if (path) for (var i = 0; i < path.length; i++) skipMemo.set(path[i], res);
		return res;
	}

	function doText(n) {
		var v = n.nodeValue;
		if (!v || !LETTER.test(v)) return;
		var o = textOrig.get(n);
		if (lang === 'en') {
			if (o && v === o.en) return;
			var en = t(v);
			if (en !== v) { textOrig.set(n, { es: v, en: en }); n.nodeValue = en; }
		} else if (o) {
			if (v === o.en) n.nodeValue = o.es;
			textOrig.delete(n);
		}
	}

	function doAttrs(el) {
		var at = el.attributes;
		if (!at || !at.length) return;
		var store = attrOrig.get(el);
		for (var i = 0; i < at.length; i++) {
			var a = at[i].name;
			if (!ATTRSET[a]) continue;
			if (a === 'label' && el.tagName !== 'OPTGROUP' && el.tagName !== 'OPTION') continue;
			var v = el.getAttribute(a);
			if (!v || !LETTER.test(v)) continue;
			if (a === 'data-content' && /</.test(v)) continue;
			var o = store && store[a];
			if (lang === 'en') {
				if (o && v === o.en) continue;
				var en = t(v);
				if (en !== v) { store = store || {}; store[a] = { es: v, en: en }; el.setAttribute(a, en); }
			} else if (o) {
				if (v === o.en) el.setAttribute(a, o.es);
				delete store[a];
			}
		}
		if (store) attrOrig.set(el, store);
		if (el.tagName === 'INPUT' && /^(button|submit|reset)$/i.test(el.type) && el.value) {
			var ov = store && store.value;
			if (lang === 'en') { if (!ov || el.value !== ov.en) { var ev = t(el.value); if (ev !== el.value) { store = store || {}; store.value = { es: el.value, en: ev }; el.value = ev; attrOrig.set(el, store); } } }
			else if (ov) { if (el.value === ov.en) el.value = ov.es; delete store.value; }
		}
	}

	function walk(root) {
		if (!root) return;
		if (root.nodeType === 3) { if (root.parentElement && !skipEl(root.parentElement)) doText(root); return; }
		if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) return;
		if (root.nodeType === 1 && skipEl(root)) return;
		if (root.nodeType === 1) doAttrs(root);
		var tw = d.createTreeWalker(root, 5 /* ELEMENT | TEXT */, {
			acceptNode: function (n) {
				if (n.nodeType === 1) return (SKIP_TAGS[n.tagName] || n.isContentEditable || n.hasAttribute('data-i18n-skip') || n.classList.contains('notranslate')) ? 2 : 1;
				return 1;
			}
		});
		var n;
		while ((n = tw.nextNode())) { if (n.nodeType === 3) doText(n); else doAttrs(n); }
	}

	function doTitle() {
		var o = d.__i18nTitle;
		if (lang === 'en') { if (!o || d.title !== o.en) { var en = t(d.title); if (en !== d.title) { d.__i18nTitle = { es: d.title, en: en }; d.title = en; } } }
		else if (o) { if (d.title === o.en) d.title = o.es; d.__i18nTitle = null; }
	}

	// Observador: traduce lo que el parser o el JS de cada módulo inserte
	var obs = null;
	function onMut(list) {
		skipMemo = new Map();
		try { onMutBatch(list); } finally { skipMemo = null; }
	}
	function onMutBatch(list) {
		for (var i = 0; i < list.length; i++) {
			var m = list[i];
			if (m.type === 'childList') { for (var j = 0; j < m.addedNodes.length; j++) walk(m.addedNodes[j]); }
			else if (m.type === 'characterData') { if (m.target.parentElement && !skipEl(m.target.parentElement)) doText(m.target); }
			else if (m.type === 'attributes') { if (!skipEl(m.target)) doAttrs(m.target); }
		}
		obs.takeRecords(); // descarta las mutaciones propias
		if (d.querySelector('title')) doTitle();
	}
	function observe() {
		if (obs || !w.MutationObserver) return;
		obs = new MutationObserver(onMut);
		obs.observe(d.documentElement, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS.concat(['value']) });
	}

	// Chart.js 2: traduce en el dibujo (ejes, leyenda, tooltips, títulos) sin modificar los datos
	function wrapFn(owner, key) {
		var f = owner && owner[key];
		if (typeof f !== 'function' || f.__i18n) return;
		var g = function () { return tr(f.apply(this, arguments)); };
		g.__i18n = true; g.__orig = f;
		owner[key] = g;
	}
	function tr(v) {
		if (lang !== 'en') return v;
		if (typeof v === 'string') return t(v);
		if (Array.isArray(v)) return v.map(function (x) { return typeof x === 'string' ? t(x) : x; });
		return v;
	}
	function chartTitle(o) {
		if (!o || !o.text) return;
		if (o.__es === undefined || (o.text !== o.__en && o.text !== o.__es)) o.__es = o.text;
		o.__en = tr(o.__es);
		o.text = lang === 'en' ? o.__en : o.__es;
	}
	function prepChart(ch) {
		var op = ch.options || {};
		chartTitle(op.title);
		var sc = op.scales || {};
		[].concat(sc.xAxes || [], sc.yAxes || []).forEach(function (ax) {
			if (!ax) return;
			ax.ticks = ax.ticks || {};
			wrapFn(ax.ticks, 'callback');
			if (ax.scaleLabel && ax.scaleLabel.labelString) { var sl = ax.scaleLabel; sl.text = sl.labelString; chartTitle(sl); sl.labelString = sl.text; }
		});
		if (op.scale) {
			if (op.scale.pointLabels) { if (!op.scale.pointLabels.callback) op.scale.pointLabels.callback = function (v) { return v; }; wrapFn(op.scale.pointLabels, 'callback'); }
		}
		if (op.legend && op.legend.labels) {
			var gl = op.legend.labels;
			if (!gl.generateLabels || !gl.generateLabels.__i18n) {
				var base = gl.generateLabels || (w.Chart && Chart.defaults.global.legend.labels.generateLabels);
				if (base) { gl.generateLabels = function (c) { var items = base.call(this, c); if (lang === 'en') items.forEach(function (it) { if (typeof it.text === 'string') it.text = t(it.text); }); return items; }; gl.generateLabels.__i18n = true; }
			}
		}
		var cb = op.tooltips && op.tooltips.callbacks;
		if (cb) for (var k in cb) wrapFn(cb, k);
		if (ch.scales) for (var id in ch.scales) { var s = ch.scales[id]; if (s && s.options && s.options.ticks) { wrapFn(s.options.ticks, 'callback'); } }
	}
	function hookCharts() {
		if (!w.Chart || !Chart.plugins || Chart.__i18nHooked) return;
		Chart.__i18nHooked = true;
		Chart.plugins.register({ id: 'dailyI18n', beforeUpdate: prepChart, beforeInit: prepChart });
	}
	function refreshCharts() {
		if (!w.Chart || !Chart.instances) return;
		for (var k in Chart.instances) { try { prepChart(Chart.instances[k]); Chart.instances[k].update(0); } catch (e) { } }
	}

	// Diálogos nativos
	['alert', 'confirm', 'prompt'].forEach(function (fn) {
		var orig = w[fn];
		if (!orig) return;
		w[fn] = function (msg) { var a = [].slice.call(arguments); if (typeof msg === 'string') a[0] = t(msg); return orig.apply(w, a); };
	});

	function applyMoment() {
		if (w.moment && moment.locale) { try { moment.locale(lang === 'en' ? 'en' : (moment.locales && moment.locales().indexOf('es') >= 0 ? 'es' : moment.locale())); } catch (e) { } }
	}

	function reveal() { d.documentElement.classList.remove('daily-i18n-pending'); }

	function apply() {
		if (obs) obs.takeRecords();
		d.documentElement.setAttribute('lang', lang);
		walk(d.body || d.documentElement);
		if (obs) obs.takeRecords();
		doTitle();
		applyMoment();
		refreshCharts();
		syncWidgets();
		reveal();
	}

	function syncWidgets() {
		var els = d.querySelectorAll('[data-daily-lang-current]');
		for (var i = 0; i < els.length; i++) els[i].textContent = lang.toUpperCase();
		var opts = d.querySelectorAll('[data-daily-lang]');
		for (var j = 0; j < opts.length; j++) {
			var on = opts[j].getAttribute('data-daily-lang') === lang;
			opts[j].classList.toggle('active', on);
			opts[j].setAttribute('aria-checked', on ? 'true' : 'false');
		}
	}

	function loadCatalog(cb) {
		if (w.DAILY_I18N_EN) { load(w.DAILY_I18N_EN); cb(); return; }
		var s = d.createElement('script');
		s.src = (w.DAILY_I18N_SRC || '/i18n/daily_en.js');
		s.onload = function () { load(w.DAILY_I18N_EN); cb(); };
		s.onerror = function () { reveal(); };
		d.head.appendChild(s);
	}

	function persist(l) {
		try { localStorage.setItem(KEY, l); } catch (e) { }
		d.cookie = 'daily_lang=' + l + ';path=/;max-age=31536000;samesite=lax';
		try {
			if (w.Grocy && Grocy.UserSettings && Grocy.UserSettings.daily_lang !== l && w.Grocy.Api && typeof w.Grocy.Api.Put === 'function') {
				Grocy.UserSettings.daily_lang = l;
				Grocy.Api.Put('user/settings/daily_lang', { value: l }, function () { }, function () { });
			}
		} catch (e) { }
	}

	function set(l) {
		l = l === 'en' ? 'en' : 'es';
		var prev = lang;
		persist(l);
		if (l === prev) { syncWidgets(); return; }
		lang = l;
		if (l === 'en') loadCatalog(function () { observe(); apply(); }); else apply();
		try { d.dispatchEvent(new CustomEvent('daily:lang', { detail: { lang: l } })); } catch (e) { }
	}

	var LOCALES = { es: 'es-CO', en: 'en-US' };
	var api = {
		get lang() { return lang; },
		t: t,
		toEs: toEs,
		set: set,
		apply: function () { if (lang === 'en') apply(); },
		locale: function () { return LOCALES[lang]; },
		number: function (v, o) { return Number(v || 0).toLocaleString(LOCALES[lang], o); },
		date: function (v, o) { return new Date(v).toLocaleDateString(LOCALES[lang], o || { year: 'numeric', month: 'short', day: 'numeric' }); },
		missing: function () { return missing; },
		hookCharts: hookCharts,
		hasKey: function (s) { return cat.hasOwnProperty(norm(s)); }
	};
	w.DailyI18n = api;

	// Arranque: con EN el catálogo ya viene cargado en <head> (cookie) y se observa desde el inicio
	// No se oculta la página: el observador traduce cada nodo a medida que el parser lo inserta,
	// antes del primer pintado, así que nunca hay pantalla en blanco ni un primer cuadro en español.
	if (lang === 'en') {
		loadCatalog(function () { observe(); if (d.body) walk(d.body); });
	} // En español no hay nada que traducir: el observador se activa al cambiar a EN
	var boot = function () {
		hookCharts(); syncWidgets();
		if (lang === 'en') loadCatalog(function () { doTitle(); applyMoment(); refreshCharts(); mark(); });
		else mark();
	};
	function mark() { if (w.performance && performance.mark) { try { performance.mark('daily-i18n-ready'); } catch (e) { } } }
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
	w.addEventListener('load', function () {
		hookCharts();
		if (lang === 'en') refreshCharts();
		// En español se precarga el catálogo en reposo: el cambio a inglés queda inmediato
		else if (!w.DAILY_I18N_EN && w.DAILY_I18N_SRC) {
			var pre = function () { var l = d.createElement('link'); l.rel = 'prefetch'; l.as = 'script'; l.href = w.DAILY_I18N_SRC; d.head.appendChild(l); };
			if (w.requestIdleCallback) requestIdleCallback(pre, { timeout: 5000 }); else setTimeout(pre, 2000);
		}
	});
	// Selector global (delegado): <a data-daily-lang="en">
	d.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('[data-daily-lang]');
		if (!a) return;
		e.preventDefault();
		set(a.getAttribute('data-daily-lang'));
	});
	// Sincroniza otras pestañas abiertas
	w.addEventListener('storage', function (e) { if (e.key === KEY && e.newValue && e.newValue !== lang) set(e.newValue); });
})(window, document);
