/* DAILY — sitio comercial: menú, idioma, animaciones, recorrido, calculadora, formularios y precarga. */
(function () {
	'use strict';
	var doc = document, root = doc.documentElement;
	var $ = function (s, c) { return (c || doc).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };
	var lang = root.lang === 'en' ? 'en' : 'es';

	/* Idioma: recuerda la elección (misma cookie que la aplicación) */
	$$('[data-lang]').forEach(function (a) {
		a.addEventListener('click', function () {
			var l = a.getAttribute('data-lang');
			doc.cookie = 'daily_lang=' + l + ';path=/;max-age=31536000;samesite=lax';
			try { localStorage.setItem('daily_lang', l); } catch (e) { /* almacenamiento no disponible */ }
		});
	});

	/* Header: sombra al hacer scroll */
	var header = $('.s-header');
	var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();

	/* Menús desplegables */
	var dds = $$('.s-dd');
	var closeAll = function (except) {
		dds.forEach(function (d) {
			if (d !== except) { d.classList.remove('is-open'); $('.s-dd__btn', d).setAttribute('aria-expanded', 'false'); }
		});
	};
	var desktop = window.matchMedia('(min-width: 1061px)');
	dds.forEach(function (d) {
		var btn = $('.s-dd__btn', d), timer;
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			// Si el hover acaba de abrirlo, el clic lo mantiene abierto en vez de cerrarlo
			var open = !d.classList.contains('is-open') || Date.now() - (d._hoverAt || 0) < 600;
			closeAll(d);
			d.classList.toggle('is-open', open);
			btn.setAttribute('aria-expanded', String(open));
		});
		d.addEventListener('mouseenter', function () {
			if (!desktop.matches) { return; }
			clearTimeout(timer); closeAll(d);
			if (!d.classList.contains('is-open')) { d._hoverAt = Date.now(); }
			d.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true');
		});
		d.addEventListener('mouseleave', function () {
			if (!desktop.matches) { return; }
			timer = setTimeout(function () { d.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }, 140);
		});
	});
	doc.addEventListener('click', function (e) { if (desktop.matches && !e.target.closest('.s-dd')) { closeAll(); } });
	doc.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		var open = $('.s-dd.is-open');
		closeAll();
		if (open) { $('.s-dd__btn', open).focus(); }
		if (doc.body.classList.contains('s-menu-open')) { toggleMenu(false); burger.focus(); }
	});

	/* Menú móvil */
	var burger = $('.s-burger');
	var burgerLabel = burger.getAttribute('aria-label');
	function toggleMenu(open) {
		root.style.setProperty('--nav-top', Math.max(0, header.getBoundingClientRect().bottom) + 'px');
		doc.body.classList.toggle('s-menu-open', open);
		burger.setAttribute('aria-expanded', String(open));
		burger.setAttribute('aria-label', open ? burger.getAttribute('data-label-close') : burgerLabel);
		$('use', burger).setAttribute('href', open ? '#i-close' : '#i-menu');
		if (!open) { closeAll(); }
	}
	burger.addEventListener('click', function () { toggleMenu(!doc.body.classList.contains('s-menu-open')); });
	$$('.s-nav a').forEach(function (a) { a.addEventListener('click', function () { if (!desktop.matches) { toggleMenu(false); } }); });
	desktop.addEventListener && desktop.addEventListener('change', function () { toggleMenu(false); });

	/* Aparición al hacer scroll */
	var reveal = $$('[data-reveal]');
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
		reveal.forEach(function (el, i) {
			el.style.transitionDelay = Math.min((i % 4) * 60, 180) + 'ms';
			io.observe(el);
		});
	} else {
		reveal.forEach(function (el) { el.classList.add('is-in'); });
	}

	/* Vistas previas: aparecen al cargar; las de pestañas ocultas se cargan solo cuando hacen falta */
	function markLoaded(img) {
		var fig = img.closest('.s-pv');
		var done = function () { img.classList.add('is-loaded'); if (fig) { fig.classList.add('is-loaded'); } };
		if (img.complete && img.naturalWidth) { done(); } else { img.addEventListener('load', done, { once: true }); }
	}
	function loadPanel(p) {
		if (!p || p._loaded) { return; }
		p._loaded = true;
		$$('[data-srcset]', p).forEach(function (el) { el.setAttribute('srcset', el.getAttribute('data-srcset')); el.removeAttribute('data-srcset'); });
		$$('img[data-src]', p).forEach(function (img) { img.src = img.getAttribute('data-src'); img.removeAttribute('data-src'); });
	}
	$$('.s-pv__frame img').forEach(markLoaded);

	/* Ampliar vista previa */
	var zoom = doc.getElementById('s-zoombox');
	if (zoom && zoom.showModal) {
		var zimg = $('img', zoom);
		var openZoom = function (fig) {
			zimg.src = fig.getAttribute('data-zoom');
			var im = $('.s-pv__frame img', fig);
			if (fig.classList.contains('s-live') && window.matchMedia('(max-width: 640px)').matches && fig.getAttribute('data-zoom-m')) { zimg.src = fig.getAttribute('data-zoom-m'); }
			zimg.alt = fig.getAttribute('data-alt') || (im ? im.alt : '');
			zoom.showModal();
			$('.s-zoombox__body', zoom).scrollTo(0, 0);
		};
		doc.addEventListener('click', function (e) {
			var btn = e.target.closest('.s-pv__zoom');
			var frame = !btn && desktop.matches && e.target.closest('.s-pv__frame');
			if (btn || frame) { openZoom((btn || frame).closest('.s-pv, .s-live')); }
		});
		$('.s-zoombox__close', zoom).addEventListener('click', function () { zoom.close(); });
		zoom.addEventListener('click', function (e) { if (e.target === zoom) { zoom.close(); } });
	}

	/* Pestañas accesibles (recorrido y selector de segmentos) */
	function tabs(list) {
		var btns = $$('[role="tab"]', list);
		var panels = btns.map(function (x) { return doc.getElementById(x.getAttribute('aria-controls')); });
		// Recorrido: todos los paneles en la misma celda; se alterna por clase para el fundido cruzado
		var stacked = panels[0] && panels[0].parentNode.hasAttribute('data-tour');
		if (stacked) {
			panels.forEach(function (p, i) { p.hidden = false; p.inert = i !== 0; });
			var first = $('.s-pv__frame img', panels[0]);
			var preloadNext = function () { setTimeout(function () { loadPanel(panels[1]); }, 400); };
			if (first && first.complete && first.naturalWidth) { preloadNext(); } else if (first) { first.addEventListener('load', preloadNext, { once: true }); }
			btns.forEach(function (b, i) {
				var warm = function () { loadPanel(panels[i]); };
				b.addEventListener('pointerenter', warm);
				b.addEventListener('focus', warm);
			});
		}
		var select = function (b, focus) {
			btns.forEach(function (x, i) {
				var on = x === b;
				x.setAttribute('aria-selected', String(on));
				x.tabIndex = on ? 0 : -1;
				var p = panels[i];
				if (!p) { return; }
				if (stacked) {
					p.classList.toggle('is-active', on);
					p.inert = !on;
					if (on) { loadPanel(p); loadPanel(panels[i + 1]); p.dispatchEvent(new CustomEvent('s-tour:show', { bubbles: true })); }
				} else {
					p.hidden = !on;
				}
				if (on) { $$('[data-reveal]', p).forEach(function (r) { r.classList.add('is-in'); }); }
			});
			if (focus) { b.focus(); }
			if (b.scrollIntoView && list.scrollWidth > list.clientWidth) { b.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' }); }
		};
		btns.forEach(function (b, i) {
			b.addEventListener('click', function () { select(b); });
			b.addEventListener('keydown', function (e) {
				var k = e.key, n = null;
				if (k === 'ArrowRight') { n = btns[(i + 1) % btns.length]; }
				if (k === 'ArrowLeft') { n = btns[(i - 1 + btns.length) % btns.length]; }
				if (k === 'Home') { n = btns[0]; }
				if (k === 'End') { n = btns[btns.length - 1]; }
				if (n) { e.preventDefault(); select(n, true); }
			});
		});
	}
	$$('[role="tablist"]').forEach(tabs);

	/* Calculadora de rentabilidad */
	var form = doc.getElementById('roi-form');
	if (form) {
		var nf = new Intl.NumberFormat(lang === 'en' ? 'en-US' : 'es-CO', { maximumFractionDigits: 0 });
		var money = function (v) { return '$' + nf.format(Math.round(v)); };
		var num = function (name) { var v = parseFloat(form.elements[name].value); return isFinite(v) && v > 0 ? v : 0; };
		var calc = function () {
			var parts = {
				shrink: num('sales') * num('shrink') / 100 * num('a_shrink') / 100 * 12,
				opex: num('opex') * num('a_opex') / 100 * 12,
				capital: num('inventory') * num('a_stock') / 100 * num('a_capital') / 100,
				hours: num('employees') * num('a_hours') * num('a_rate') * 12
			};
			var total = parts.shrink + parts.opex + parts.capital + parts.hours;
			var max = Math.max(parts.shrink, parts.opex, parts.capital, parts.hours, 1);
			$('#roi-total').textContent = money(total);
			$('#roi-low').textContent = money(total * 0.5);
			$('#roi-base').textContent = money(total);
			$('#roi-high').textContent = money(total * 1.5);
			Object.keys(parts).forEach(function (k) {
				var row = $('#roi-bars [data-k="' + k + '"]');
				$('b', row).textContent = money(parts[k]);
				$('u', row).style.width = (parts[k] / max * 100) + '%';
			});
			var br = num('branches');
			$('#roi-per').textContent = br > 0 ? (lang === 'en' ? 'Average per branch: ' : 'Promedio por sucursal: ') + money(total / br) + (lang === 'en' ? ' per year' : ' al año') : '';
			var lic = num('a_license') * 12, out = $('#roi-return');
			if (lic > 0) {
				var r = (total - lic) / lic * 100;
				out.hidden = false;
				out.textContent = (lang === 'en' ? 'Estimated return on the annual investment (' : 'Retorno estimado sobre la inversión anual (') + money(lic) + '): ' + nf.format(Math.round(r)) + ' %';
			} else {
				out.hidden = true;
			}
		};
		form.addEventListener('input', calc);
		form.addEventListener('submit', function (e) { e.preventDefault(); });
		calc();
	}

	/* Formularios de demostración y contacto */
	$$('[data-lead-form]').forEach(function (f) {
		var status = $('.s-form__status', f), btn = $('button[type="submit"]', f), msg = f.dataset;
		var plan = new URLSearchParams(location.search).get('plan');
		if (plan && f.elements.message && !f.elements.message.value) {
			f.elements.message.value = (lang === 'en' ? 'Interested in the plan: ' : 'Me interesa el plan: ') + plan.charAt(0).toUpperCase() + plan.slice(1) + '.\n';
		}
		var check = function (el) {
			var v = el.validity, err = '';
			if (v.valueMissing) { err = msg.msgRequired; }
			else if (v.typeMismatch && el.type === 'email') { err = msg.msgEmail; }
			else if (v.patternMismatch) { err = msg.msgPhone; }
			else if (v.tooShort) { err = msg.msgShort; }
			var field = el.closest('.s-field');
			if (field) {
				field.classList.toggle('is-invalid', !!err);
				var out = $('.s-err', field); if (out) { out.textContent = err; }
			}
			el.setAttribute('aria-invalid', err ? 'true' : 'false');
			return !err;
		};
		var inputs = $$('input:not([type=hidden]):not([name=website]), select, textarea', f);
		inputs.forEach(function (el) {
			el.addEventListener('blur', function () { if (el.value) { check(el); } });
			el.addEventListener('input', function () { if (el.closest('.is-invalid')) { check(el); } });
		});
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var bad = inputs.filter(function (el) { return !check(el); });
			status.className = 's-form__status';
			if (bad.length) { status.textContent = msg.msgInvalid; status.classList.add('is-error'); bad[0].focus(); return; }
			btn.disabled = true;
			status.textContent = msg.msgSending;
			var data = new URLSearchParams(new FormData(f));
			data.append('lang', lang);
			fetch(f.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
				.then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, body: j }; }); })
				.then(function (res) {
					if (res.status === 201 || (res.status === 200 && res.body.ok)) {
						f.classList.add('is-done');
						status.textContent = msg.msgOk;
						status.classList.add('is-ok');
						status.setAttribute('tabindex', '-1');
						status.focus();
						return;
					}
					if (res.status === 422 && res.body.errors) {
						res.body.errors.forEach(function (n) {
							var el = f.elements[n]; if (el) { var fl = el.closest('.s-field'); fl.classList.add('is-invalid'); $('.s-err', fl).textContent = msg.msgRequired; }
						});
						status.textContent = msg.msgInvalid;
					} else {
						status.textContent = msg.msgError;
					}
					status.classList.add('is-error');
					btn.disabled = false;
				})
				.catch(function () { status.textContent = msg.msgError; status.classList.add('is-error'); btn.disabled = false; });
		});
	});

	/* Precarga selectiva de páginas internas al pasar el cursor o tocar */
	var done = {};
	var prefetch = function (a) {
		if (!a || a.hasAttribute('data-noprefetch') || a.origin !== location.origin || a.hash && a.pathname === location.pathname) { return; }
		var url = a.pathname + a.search;
		if (done[url] || url === location.pathname + location.search) { return; }
		done[url] = 1;
		var l = doc.createElement('link'); l.rel = 'prefetch'; l.href = url; doc.head.appendChild(l);
	};
	var conn = navigator.connection;
	if (!(conn && (conn.saveData || /2g/.test(conn.effectiveType || '')))) {
		doc.addEventListener('pointerover', function (e) { var a = e.target.closest && e.target.closest('a[href]'); if (a) { prefetch(a); } }, { passive: true });
		doc.addEventListener('touchstart', function (e) { var a = e.target.closest && e.target.closest('a[href]'); if (a) { prefetch(a); } }, { passive: true });
	}
})();
