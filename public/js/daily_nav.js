/*
 * Navegación continua DAILY (ver public/css/daily_nav.css). DAILY es una app multipágina (Blade):
 * cada dashboard lo renderiza el servidor. Mientras llega la siguiente página se mantiene visible el
 * la página anterior con una barra de progreso; nunca se oculta ni se blanquea la aplicación.
 */
(function (w, d) {
	'use strict';
	var root = d.documentElement, timer = null, guard = null, slow = null, prevActive = null;
	function bar() {
		if (d.querySelector('.daily-nav-bar') || !d.body) return;
		var b = d.createElement('div'); b.className = 'daily-nav-bar'; b.setAttribute('aria-hidden', 'true');
		d.body.appendChild(b);
	}
	// Respuesta inmediata en el menú: el destino queda marcado como activo en el mismo cuadro del clic
	function markMenu(a) {
		var item = a.closest('#dairySidenav .dnav-item');
		if (!item || item.classList.contains('is-active')) return;
		var nav = d.getElementById('dairySidenav');
		prevActive = nav.querySelector('.dnav-item.is-active');
		if (prevActive) prevActive.classList.remove('is-active');
		item.classList.add('is-active');
	}
	function start(a) {
		clearTimeout(timer); clearTimeout(guard);
		bar(); root.classList.add('daily-nav-pending');
		if (a) markMenu(a);
		// Cursor de espera y atenuación solo si la respuesta tarda
		timer = setTimeout(function () { root.classList.add('daily-nav-loading'); root.setAttribute('aria-busy', 'true'); }, 120);
		guard = setTimeout(stop, 20000);
		// Respuesta que no llega: aviso con «Reintentar» en lugar de una espera sin fin (daily_errors.js)
		var href = a && a.href;
		slow = setTimeout(function () {
			if (w.DailyNotice && root.classList.contains('daily-nav-pending')) {
				w.DailyNotice(root.lang === 'en' ? 'This page is taking longer than usual.' : 'La página está tardando más de lo normal.', function () { location.href = href || location.href; });
			}
		}, 10000);
	}
	function stop() {
		clearTimeout(timer); clearTimeout(guard); clearTimeout(slow);
		root.classList.remove('daily-nav-loading', 'daily-nav-pending'); root.removeAttribute('aria-busy');
		// Vuelta desde bfcache o navegación cancelada: el menú vuelve a la página que se está viendo
		var cur = d.querySelector('#dairySidenav .dnav-item.is-active');
		if (prevActive && cur && cur !== prevActive) { cur.classList.remove('is-active'); prevActive.classList.add('is-active'); }
		prevActive = null;
	}

	// Precarga deshabilitada en entorno de servidor de desarrollo para evitar saturar el hilo único
	var prefetched = {};
	function prefetch(a) {
		return;
	}

	d.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a || a.target && a.target !== '_self' || a.hasAttribute('download') || a.hasAttribute('data-toggle') || a.hasAttribute('data-daily-lang')) return;
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
		var url;
		try { url = new URL(a.href, location.href); } catch (err) { return; }
		if (url.origin !== location.origin || /\/api\//.test(url.pathname)) return;
		if (url.pathname === location.pathname && url.search === location.search) return; // solo cambia el hash
		// Deja que otros manejadores decidan (p. ej. confirmaciones); si navegan, se verá el skeleton
		// (microtarea: corre antes del siguiente pintado, así la respuesta visual cae en el cuadro del clic)
		Promise.resolve().then(function () { if (!e.defaultPrevented) start(a); });
		// Un manejador posterior (p. ej. en window) pudo cancelar la navegación: se revierte la respuesta visual
		setTimeout(function () { if (e.defaultPrevented) stop(); }, 0);
	});
	// El fondo de <html> sigue al modo noche aunque se cambie sin recargar
	d.addEventListener('DOMContentLoaded', function () {
		var sync = function () { root.classList.toggle('daily-night', d.body.classList.contains('night-mode')); };
		sync();
		if (w.MutationObserver) new MutationObserver(sync).observe(d.body, { attributes: true, attributeFilter: ['class'] });
	});
	// Al llegar con View Transition, solo el contenido central entra con fundido (ver daily_nav.css)
	w.addEventListener('pagereveal', function (e) {
		var c = e.viewTransition && d.getElementById('page-content');
		if (!c) return;
		c.style.viewTransitionName = 'daily-content';
		// finished se rechaza si la transición se cancela (p. ej. «Page already revealed»): no es un error de la página
		var clear = function () { c.style.viewTransitionName = ''; };
		e.viewTransition.finished.then(clear, clear);
	});
	// Volver con el botón atrás desde la caché (bfcache) o una descarga que no navega
	w.addEventListener('pageshow', stop);
	w.addEventListener('pagehide', function () { clearTimeout(guard); });
	d.addEventListener('visibilitychange', function () { if (d.visibilityState === 'visible' && root.classList.contains('daily-nav-loading')) { clearTimeout(guard); guard = setTimeout(stop, 8000); } });

	// =========================================================================
	// Interacción del Menú de Perfil de Usuario (Escape para cerrar, Tema)
	// =========================================================================
	d.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			var menu = d.querySelector('.daily-user-profile-item.show, .daily-user-profile-item .dropdown-menu.show');
			if (menu) {
				var items = d.querySelectorAll('.daily-user-profile-item, .daily-user-profile-item .dropdown-menu');
				items.forEach(function (el) { el.classList.remove('show'); });
				var btn = d.getElementById('dailyUserProfileDropdown');
				if (btn) btn.setAttribute('aria-expanded', 'false');
			}
		}
	});

	// El botón "Tema" del perfil lleva data-daily-mode-toggle: lo atiende DailyTheme (daily_mode.js)
})(window, document);
