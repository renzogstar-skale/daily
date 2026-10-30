/* ==========================================================================
   DAIRY ENTERPRISE — MENU & SIDEBAR INTERACTION LOGIC
   Sidebar jerárquico: categorías en acordeón (una abierta a la vez), contraíble a iconos
   con menú flotante por categoría, estado persistente y navegación por teclado.
   ========================================================================== */

(function($) {
	'use strict';

	var $nav = $('#dairySidenav');
	var OPEN_KEY = 'dairy_nav_open';

	function store(key, value) {
		try { window.localStorage.setItem(key, value); } catch (e) { }
	}

	function read(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}

	function isCollapsed() {
		return $('body').hasClass('sidenav-toggled') && window.innerWidth >= 768;
	}

	// Tooltips de categoría (solo visibles con el sidebar contraído, ver CSS)
	$nav.find('[data-toggle="tooltip"]').tooltip({
		template: '<div class="tooltip navbar-sidenav-tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>',
		boundary: 'window',
		trigger: 'hover focus'
	});

	// --------------------------------------------------------------------------
	// Acordeón de categorías
	// --------------------------------------------------------------------------
	function setOpen($cat, open) {
		$cat.toggleClass('is-open', open);
		$cat.children('.dnav-cat-btn').attr('aria-expanded', open ? 'true' : 'false');
	}

	function openCategory($cat) {
		$nav.find('.dnav-cat.is-open').not($cat).each(function() { setOpen($(this), false); });
		setOpen($cat, true);
		store(OPEN_KEY, $cat.data('cat'));
	}

	function closeCategory($cat) {
		setOpen($cat, false);
		store(OPEN_KEY, '');
	}

	// --------------------------------------------------------------------------
	// Menú flotante (sidebar contraído)
	// --------------------------------------------------------------------------
	function closeFlyout() {
		$('body').removeClass('dnav-flyout-open');
		$nav.find('.dnav-cat.is-flyout').removeClass('is-flyout').children('.dnav-cat-btn').attr('aria-expanded', 'false');
	}

	function openFlyout($cat) {
		closeFlyout();
		var $btn = $cat.children('.dnav-cat-btn');
		$btn.tooltip('hide');
		$cat.addClass('is-flyout');
		$('body').addClass('dnav-flyout-open');
		$('.navbar-sidenav-tooltip').remove();
		$btn.attr('aria-expanded', 'true');
		positionFlyout($cat);
	}

	function positionFlyout($cat) {
		var $wrap = $cat.children('.dnav-sub-wrap');
		var rect = $cat.children('.dnav-cat-btn')[0].getBoundingClientRect();
		$wrap.css({ top: '8px', maxHeight: '' });
		var height = Math.min($wrap[0].scrollHeight + 2, window.innerHeight - 16);
		var top = Math.max(8, Math.min(rect.top, window.innerHeight - height - 8));
		$wrap.css({ top: top + 'px', maxHeight: (window.innerHeight - top - 8) + 'px' });
	}

	$nav.on('click', '.dnav-cat-btn', function(e) {
		e.preventDefault();
		var $cat = $(this).closest('.dnav-cat');
		if (isCollapsed()) {
			if ($cat.hasClass('is-flyout')) {
				closeFlyout();
			} else {
				openFlyout($cat);
				// Solo al abrir con teclado se mueve el foco al primer elemento
				if (e.originalEvent && e.originalEvent.detail === 0) $cat.find('.dnav-sub a.dnav-link, .dnav-sub .dnav-group-btn').filter(':visible').first().trigger('focus');
			}
			return;
		}
		if ($cat.hasClass('is-open')) {
			closeCategory($cat);
		} else {
			openCategory($cat);
		}
	});

	// Grupo anidado (Administrar datos maestros)
	$nav.on('click', '.dnav-group-btn', function(e) {
		e.preventDefault();
		var $group = $(this).closest('.dnav-group');
		var open = !$group.hasClass('is-open');
		$group.toggleClass('is-open', open);
		$(this).attr('aria-expanded', open ? 'true' : 'false');
		if (isCollapsed()) {
			var $cat = $group.closest('.dnav-cat');
			setTimeout(function() { positionFlyout($cat); }, 230);
		}
	});

	$(document).on('click', function(e) {
		if (!$(e.target).closest('.dnav-cat.is-flyout').length) {
			closeFlyout();
		}
	});

	// Recordar la categoría al navegar desde un enlace
	$nav.on('click', 'a.dnav-link', function() {
		store(OPEN_KEY, $(this).closest('.dnav-cat').data('cat'));
		closeFlyout();
		if (window.innerWidth < 768) {
			$('#sidebarResponsive').collapse('hide');
		}
	});

	// Enlaces con fragmento en la misma página (p. ej. #tab-simulator): actualizar el activo sin recargar
	$(window).on('hashchange', function() {
		var $link = $nav.find('a.dnav-link').filter(function() {
			return this.pathname === window.location.pathname && this.hash && this.hash === window.location.hash;
		}).first();
		if (!$link.length) {
			$link = $nav.find('a.dnav-link').filter(function() {
				return this.pathname === window.location.pathname && !this.hash;
			}).first();
		}
		if (!$link.length) {
			return;
		}
		$nav.find('.dnav-item.is-active').removeClass('is-active');
		$nav.find('.dnav-cat.is-active').removeClass('is-active');
		$nav.find('[aria-current="page"]').removeAttr('aria-current');
		$link.attr('aria-current', 'page').parent().addClass('is-active');
		var $cat = $link.closest('.dnav-cat').addClass('is-active');
		if (!isCollapsed()) {
			openCategory($cat);
		}
	});

	// --------------------------------------------------------------------------
	// Teclado: flechas entre elementos visibles, Escape cierra
	// --------------------------------------------------------------------------
	$nav.on('keydown', function(e) {
		var key = e.key;
		if (key === 'Escape') {
			var $flyout = $nav.find('.dnav-cat.is-flyout');
			if ($flyout.length) {
				closeFlyout();
				$flyout.children('.dnav-cat-btn').trigger('focus');
				e.preventDefault();
			} else {
				var $cat = $(document.activeElement).closest('.dnav-cat');
				if ($cat.hasClass('is-open')) {
					closeCategory($cat);
					$cat.children('.dnav-cat-btn').trigger('focus');
					e.preventDefault();
				}
			}
			return;
		}
		if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(key) === -1) {
			return;
		}
		var $focusables = $nav.find('.dnav-cat-btn, a.dnav-link, .dnav-group-btn').filter(function() {
			return $(this).is(':visible') && $(this).css('visibility') !== 'hidden';
		});
		var idx = $focusables.index(document.activeElement);
		if (idx === -1) {
			return;
		}
		e.preventDefault();
		if (key === 'ArrowDown') idx = Math.min(idx + 1, $focusables.length - 1);
		if (key === 'ArrowUp') idx = Math.max(idx - 1, 0);
		if (key === 'Home') idx = 0;
		if (key === 'End') idx = $focusables.length - 1;
		$focusables.eq(idx).trigger('focus');
	});

	// --------------------------------------------------------------------------
	// Contadores (solo con datos reales; se ocultan en 0)
	// --------------------------------------------------------------------------
	window.DairyNav = {
		setBadge: function(name, count) {
			var $badge = $nav.find('[data-dnav-badge="' + name + '"]');
			var n = parseInt(count, 10) || 0;
			$badge.text(n > 99 ? '99+' : n).toggleClass('d-none', n <= 0);
			$badge.closest('.dnav-cat').find('.dnav-dot').toggleClass('d-none', n <= 0);
			if (n > 0) {
				$badge.attr('aria-label', n + ' pendientes');
			}
		}
	};

	// --------------------------------------------------------------------------
	// Sidebar contraído / expandido
	// --------------------------------------------------------------------------
	function setSidebarState(state, savePreference) {
		if (savePreference === undefined) {
			savePreference = true;
		}

		closeFlyout();
		if (state === "collapsed") {
			$("body").addClass("sidenav-toggled");
			$("#headerCollapseBtn, #sidebarExpandBtn").attr("aria-expanded", "false");
		} else {
			$("body").removeClass("sidenav-toggled");
			$("#headerCollapseBtn, #sidebarExpandBtn").attr("aria-expanded", "true");
		}
		if (savePreference) {
			store("dairy_sidebar_state", state);
			store("sidebar_state", state);
		}

		// Recalcular gráficos y tablas al terminar la transición
		setTimeout(function() {
			window.dispatchEvent(new Event('resize'));
		}, 220);
	}

	$(document).on("click", "#headerCollapseBtn, #sidebarExpandBtn, #sidenavToggler", function(e) {
		e.preventDefault();
		var newState = $("body").hasClass("sidenav-toggled") ? "expanded" : "collapsed";
		setSidebarState(newState, true);
		$(newState === "collapsed" ? "#sidebarExpandBtn" : "#headerCollapseBtn").trigger("focus");
	});

	function desiredState() {
		var width = window.innerWidth;
		var savedState = read("dairy_sidebar_state") || read("sidebar_state");
		if (width < 768) {
			return "mobile";
		}
		if (width < 1024) {
			return savedState === "expanded" ? "expanded" : "collapsed";
		}
		return savedState === "collapsed" ? "collapsed" : "expanded";
	}

	var lastState = null;
	function handleResponsiveSidebar() {
		var state = desiredState();
		if (state === lastState) {
			return;
		}
		lastState = state;
		if (state === "mobile") {
			$("body").removeClass("sidenav-toggled");
			closeFlyout();
		} else if ((state === "collapsed") !== $("body").hasClass("sidenav-toggled")) {
			setSidebarState(state, false);
		}
	}

	handleResponsiveSidebar();

	var resizeTimeout;
	$(window).on("resize", function() {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(handleResponsiveSidebar, 150);
		closeFlyout();
	});

	// Activar transiciones solo después del primer pintado
	window.requestAnimationFrame(function() {
		window.requestAnimationFrame(function() {
			$("body").removeClass("dnav-preload");
		});
	});

	// Asegurar que la herramienta activa sea visible
	var $active = $nav.find('.dnav-item.is-active').first();
	if ($active.length && !isCollapsed()) {
		var navEl = $nav[0];
		var offset = $active[0].getBoundingClientRect().top - navEl.getBoundingClientRect().top;
		if (offset > navEl.clientHeight - 60) {
			navEl.scrollTop += offset - navEl.clientHeight / 2;
		}
	}

})(jQuery);
